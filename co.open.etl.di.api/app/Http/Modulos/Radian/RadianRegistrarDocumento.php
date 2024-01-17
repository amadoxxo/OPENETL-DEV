<?php
namespace App\Http\Modulos\Radian;

use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use App\Http\Models\AdoAgendamiento;
use openEtl\Tenant\Servicios\TenantService;
use App\Http\Modulos\Radian\ParserXml\ParserXml;
use App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActor;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;


class RadianRegistrarDocumento {

    /**
     * contador de documentos que pudieron ser registrados
     *
     * @var int
     */
    private $exitosos = 0;

    /**
     * Contador de documentos que no pueden ser registrados
     *
     * @var int
     */
    private $fallidos = 0;

    /**
     * Lista con errores encontrados.
     *
     * @var array
     */
    private $errors = [];

    /**
     * Lista documentos procesados
     *
     * @var array
     */
    private $procesados = [];

    /**
     * Lista documentos no procesados
     *
     * @var array
     */
    private $noProcesados = [];

    /**
     * Instancia del servicio del paquete Tenant
     *
     * @var TenantService
     */
    protected $tenantService;

    /**
     * RadianRegistrarDocumento constructor.
     * 
     * @param TenantService $tenantService Instancia de la clase mediante inyección de dependencias
     */
    public function __construct(TenantService $tenantService) {
        $this->tenantService = $tenantService;
    }

    /**
     * Efectua el procesamiento del la data.
     *
     * @param request $request  Data que puede venir desde el comando o el endpoint
     * @return array Documentos procesados exitosos y fallidos 
     */
    public function run(Request $request): array {
        // Usuario Autenticado
        $user           = auth()->user();
        $newDocument    = [];

        if (!$request->filled('documentos')) {
            return response()->json([
                'message' => 'Error al registrar el Json',
                'errors'  => ['No se encontraron documentos para procesar en la petición']
            ], 400);
        }

        $documentos = ($request->origen == 'MANUAL') ? json_decode($request->documentos, true) : $request->documentos;
        $loteProcesamiento = $this->buildLote();
        
        foreach ($documentos as $documento) {
            $this->errors = [];

            $actor = RadianActor::select('act_id', 'act_identificacion')    
                ->where('act_identificacion', $documento['act_identificacion'])
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$actor) {
                $this->errors[] = 'El actor con identificacion '. $documento['act_identificacion'] .' no existe o está inactivo';
                $this->fallidos++;
            } else {
                try {
                    $consultaXml = $this->tenantService->peticionMicroservicio('DO', 'POST', '/api/radian/documentos/procesar-peticion-proceso-radian-crear-documentos', [
                        'act_id'               => $actor->act_id,
                        'cufe'                 => $documento['cufe'],
                        'metodo_consulta_dian' => 'GetXmlByDocumentKey'
                    ], 'json');
    
                    if($consultaXml->successful()) {
                        try {
                            $newDocument['xml'] = $consultaXml->json()['data']['xml'];
                            // Inicia el procesamiento del XML
                            $parserXml = new ParserXml($loteProcesamiento);
                            $documentoParser = $parserXml->Parser($request->origen, $newDocument['xml'], $actor->act_identificacion, $documento['rol_id']);
                            
                            if ($documentoParser['procesado']) {
                                array_push($this->procesados, $documentoParser);
                                $this->exitosos++;
                            } else {
                                array_push($this->noProcesados, $documentoParser);
                                $this->fallidos++;
                            }
                        } catch (\Throwable $e) {
                            $respuesta = $e->getMessage();
                            if($respuesta !== '')
                                $this->errors[] = 'CUFE [' . $documento['cufe'] . ']: ' . $respuesta ;
                        }

                    } else {
                        if(array_key_exists('message', $consultaXml->json()) && array_key_exists('errors', $consultaXml->json()))
                            $this->errors[] = 'CUFE [' . $documento['cufe'] . ']: ' . $consultaXml->json()['message'] . ' [' . (implode(' || ', $consultaXml->json()['errors'])) . ']';
                    } 
                } catch (\Throwable $e) {
                    $respuesta = $e->response->json();
    
                    if(array_key_exists('message', $respuesta) && array_key_exists('errors', $respuesta))
                        $this->errors[] = 'CUFE [' . $documento['cufe'] . ']: ' . $respuesta['message'] . ' [' . (implode(' || ', $respuesta['errors'])) . ']';
                    else
                        $this->errors[] = 'CUFE [' . $documento['cufe'] . ']: ' . $respuesta['message'];
                }
            }

            if (!empty($this->errors)) {
                $arrDocumento           = (array)$documento;
                $arrDocumento['errors'] = $this->errors;
                array_push($this->noProcesados, $arrDocumento);
                $this->fallidos++;
            }
        }

        //Se crea el estado RADGETSTATUS para un documento exitoso
        $arrResProcesadosExitosos = [];
        $arrResProcesadosFallidos = [];

        if($this->exitosos > 0) {
            $agendamiento = AdoAgendamiento::create([
                'usu_id'                  => $user->usu_id,
                'bdd_id'                  => $user->bdd_id,
                'age_proceso'             => 'RADGETSTATUS',
                'age_cantidad_documentos' => $this->exitosos,
                'age_prioridad'           => null,
                'usuario_creacion'        => $user->usu_id,
                'estado'                  => 'ACTIVO'
            ]);

            foreach ($this->procesados as $procesado) {
                $arrResProcesadosExitosos[] = $this->buildDataResponse($procesado, $user->usu_id, $agendamiento->age_id);
            }
        } 
        
        if ($this->fallidos > 0){
            foreach ($this->noProcesados as $noProcesado) {
                $arrResProcesadosFallidos[] = $this->buildDataResponse($noProcesado, $user->usu_id);
            }
        }

        return [
            'message'               => "Bloque de informacion procesado bajo el lote $loteProcesamiento",
            'lote'                  => $loteProcesamiento,
            'documentos_procesados' => $arrResProcesadosExitosos,
            'documentos_fallidos'   => $arrResProcesadosFallidos
        ];
    }

    /**
     * Agenda un estado y arma un Objeto para retornarlo al método run.
     *
     * @param array     $arrDocumento   Data del documento que se procesó
     * @param int       $user           Id del Usuario autenticado
     * @param int|null  $agendamiento   Id del agendamiento en la tabla Ado agendamiento que se generó
     * @return \stdClass Documentos procesados exitosos y fallidos 
     */
    public function buildDataResponse(array $arrDocumento, int $user, int $agendamiento = null): \stdClass {
        if (!is_null($agendamiento)) {
            RadianEstadoDocumentoDaop::create([
                'cdo_id'                    => $arrDocumento['cdo_id'],
                'est_estado'                => 'GETSTATUS',
                'est_resultado'             => null,
                'est_inicio_proceso'        => null,
                'est_fin_proceso'           => null,
                'est_ejecucion'             => null,
                'est_tiempo_procesamiento'  => null,
                'est_informacion_adicional' => null,
                'age_id'                    => $agendamiento,
                'age_usu_id'                => $user,
                'usuario_creacion'          => $user,
                'estado'                    => 'ACTIVO'
            ]);
        }

        $objDocumentos                      =  new \stdClass();
        $objDocumentos->cdo_id              =  (array_key_exists('cdo_id', $arrDocumento)) ? $arrDocumento['cdo_id'] : null;
        $objDocumentos->act_identificacion  =  (array_key_exists('act_identificacion', $arrDocumento)) ? $arrDocumento['act_identificacion'] : null;
        $objDocumentos->cufe                =  (array_key_exists('cufe', $arrDocumento)) ? $arrDocumento['cufe'] : null;
        $objDocumentos->errors              =  (array_key_exists('errors', $arrDocumento)) ? $arrDocumento['errors'] : null;
        $objDocumentos->fecha_procesamiento =  (array_key_exists('fecha_procesamiento', $arrDocumento)) ? $arrDocumento['fecha_procesamiento'] : date('Y-m-d');
        $objDocumentos->hora_procesamiento  =  (array_key_exists('hora_procesamiento', $arrDocumento)) ? $arrDocumento['hora_procesamiento'] : date('H:i:s');
        $resProcesado                       =  $objDocumentos;

        return $resProcesado;
    }

    /**
     * UUID utilizado en el campo cdo_lote.
     * 
     * @return string Lote bajo el cual se procesa
     */
    public function buildLote(): string {
        $uuid    = Uuid::uuid4();
        $dateObj = \DateTime::createFromFormat('U.u', microtime(TRUE));
        $msg     = $dateObj->format('u');
        $msg    /= 1000;
        $dateObj->setTimeZone(new \DateTimeZone('America/Bogota'));
        $dateTime = $dateObj->format('YmdHis').intval($msg);
        $lote = $dateTime . '_' . $uuid->toString();

        return $lote;
    }
}