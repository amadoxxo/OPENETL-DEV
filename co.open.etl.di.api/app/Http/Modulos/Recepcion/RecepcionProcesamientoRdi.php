<?php
namespace App\Http\Modulos\Recepcion;

use Validator;
use App\Traits\DiTrait;
use App\Traits\RecepcionTrait;
use App\Http\Modulos\Recepcion\Recepcion;
use openEtl\Tenant\Servicios\TenantService;
use App\Http\Modulos\Recepcion\RecepcionException;
use App\Http\Modulos\Recepcion\ParserXml\ParserXml;
use openEtl\Tenant\Servicios\Recepcion\TenantRecepcionService;
use openEtl\Tenant\Helpers\Recepcion\TenantXmlUblExtractorHelper;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RecepcionProcesamientoRdi {
    use DiTrait, RecepcionTrait;

    /**
     * Instancia del servicio del paquete Tenant
     *
     * @var TenantService
     */
    protected $tenantService;

    /**
     * Instancia del servicio para recepción del paquete Tenant
     *
     * @var TenantRecepcionService
     */
    protected $tenantRecepcionService;

    /**
     * Instancia del helper TenantXmlUblExtractorHelper
     *
     * @var TenantXmlUblExtractorHelper
     */
    protected $tenantXmlExtractorHelper;

    /**
     * Instancia de la clase ParserXml de recepción
     *
     * @var ParserXml
     */
    protected $parserXml;

    /**
     * Constructor de la clase
     *
     * @param TenantService $tenantService Instancia de la clase mediante inyección de dependencias
     * @param TenantRecepcionService $tenantRecepcionService Instancia de la clase mediante inyección de dependencias
     * @param TenantXmlUblExtractorHelper $tenantXmlExtractorHelper Instancia de la clase mediante inyección de dependencias
     * @param ParserXml $parserXml Instancia de la clase mediante inyección de dependencias
     */
    public function __construct(TenantService $tenantService, TenantRecepcionService $tenantRecepcionService, TenantXmlUblExtractorHelper $tenantXmlExtractorHelper, ParserXml $parserXml) {
        $this->parserXml                = $parserXml;
        $this->tenantService            = $tenantService;
        $this->tenantRecepcionService   = $tenantRecepcionService;
        $this->tenantXmlExtractorHelper = $tenantXmlExtractorHelper;
    }
    /**
     * 
     * Realiza las validaciones sobre el array que contiene la información de los documentos a procesar.
     *
     * @param array $arrInfoDocumentos Array con la información de los documentos a procesar
     * @return array Array conteniendo los errores de validación
     */
    private function validarInfoDocumentos(array $arrInfoDocumentos): array {
        $reglasPrincipales = [
            'ofe_identificacion' => 'required|string',
            'pro_identificacion' => 'nullable|string',
            'usu_identificacion' => 'required|string',
            'lote'               => 'nullable|string',
            'origen'             => 'required|string|in:MANUAL,RPA',
            'documentos'         => 'required|array|min:1'
        ];

        $reglasDocumentos = [
            'nombre' => 'required|string',
            'cufe'   => 'nullable|string',
            'xml'    => 'nullable|string',
            'pdf'    => 'nullable|string'
        ];

        $arrErroresValidacion     = [];
        $validarReglasPrincipales = Validator::make($arrInfoDocumentos, $reglasPrincipales);
        if($validarReglasPrincipales->fails())
            $arrErroresValidacion = array_merge($arrErroresValidacion, $validarReglasPrincipales->errors()->all());

        if(array_key_exists('documentos', $arrInfoDocumentos)) {
            foreach($arrInfoDocumentos['documentos'] as $index => $documento) {
                $erroresDocumento   = [];
                $validarDocumento   = Validator::make($documento, $reglasDocumentos);

                if($validarDocumento->fails())
                    array_map(function($error) use (&$erroresDocumento) {
                        $erroresDocumento[] = $error;
                    }, $validarDocumento->errors()->all());

                if(
                    (!array_key_exists('cufe', $documento) && !array_key_exists('xml', $documento)) ||
                    (!array_key_exists('cufe', $documento) && array_key_exists('xml', $documento) && empty($documento['xml'])) ||
                    (!array_key_exists('xml', $documento) && array_key_exists('cufe', $documento) && empty($documento['cufe'])) ||
                    (array_key_exists('cufe', $documento) && empty($documento['cufe']) && array_key_exists('xml', $documento) && empty($documento['xml']))
                )
                    $erroresDocumento[] = 'Los índices cufe y xml estan vacios o no existen';

                if(!empty($erroresDocumento))
                    $arrErroresValidacion[] = 'Documento en índice [' . $index . ']: [' . implode(' || ', $erroresDocumento) . ']';
            }
        }

        return $arrErroresValidacion;
    }

    /**
     * Realiza una petición al microservicio DO para generar la RG del documento.
     *
     * @param array $errores Array de errores del proceso
     * @param string $nombre Nombre del documento en procesamiento
     * @param string $contenidoPdf Contenido del PDF que llega en la información del documento a procesar
     * @param string $contenidoXml Contenido del XML que llega en la información del documento a procesar
     * @return string
     */
    private function generarRg(array &$errores, string $nombre, string $contenidoPdf, string $contenidoXml): string {
        $contenido = !empty($contenidoPdf) ? base64_decode($contenidoPdf) : '';
        if($this->tenantRecepcionService->validatePdfContent($contenido) || empty($contenidoPdf)) {
            // Extracción de datos a partir del xml
            $jsonXML = json_decode(base64_decode(($this->tenantXmlExtractorHelper)($contenidoXml)), true);

            // Petición al microservicio DO para la generación de la representación gráfica de recepción usando el json de respuesta del extractor
            try {
                $generarRg = $this->tenantService->peticionMicroservicio('DO', 'POST', '/api/pdf/recepcion/generar-representacion-grafica', $jsonXML, 'json');

                if($generarRg->successful())
                    return base64_encode($generarRg->body());
                else
                    return '';
            } catch (\Throwable $e) {
                $respuesta = $e->response->json();

                if(array_key_exists('message', $respuesta) && array_key_exists('errors', $respuesta)) {
                    $errores[] = ['Advertencia' => 'Documento [' . $nombre . ']: ' . $respuesta['message'] . ' [' . (implode(' || ', $respuesta['errors'])) . ']'];
                } else {
                    $errores[] = ['Advertencia' => 'Documento [' . $nombre . ']: ' . $respuesta['message']];
                }
            }
        }

        return '';
    } 

    /**
     * Crea el agendamiento para el estado GETSTATUS para los documentos procesados.
     *
     * @param array $documentosProcesados Array de los documentos procesados
     * @param int $epm_id ID del registro en etl_emails_procesamiento_manual
     * @return void
     */
    private function crearAgendamientosGetStatus(array $documentosProcesados, int $epm_id = 0): void {
        // Asocia el documento creado con los anexos del correo cuando se envía el valor del epm_id
        if($epm_id != 0) {
            foreach ($documentosProcesados as $documento) {
                if($documento['ofe_recepcion_fnc_activo'] == 'SI')
                    $this->asociarAnexosCorreo($epm_id, $documento['cdo_id'], auth()->user(), 'COMMAND');
            }
        }

        foreach(array_chunk($documentosProcesados, auth()->user()->getBaseDatos->bdd_cantidad_procesamiento_getstatus) as $bloque) {
            $nuevoAgendamiento = Recepcion::creaNuevoAgendamiento(auth()->user()->getBaseDatos->bdd_id, 'RGETSTATUS', count($bloque));
            
            foreach($bloque as $documento) {
                Recepcion::creaNuevoEstadoDocumentoRecepcion(
                    $documento['cdo_id'],
                    'GETSTATUS',
                    null,
                    null,
                    null,
                    null,
                    $nuevoAgendamiento,
                    auth()->user()->usu_id,
                    null,
                    null
                );
            }
        }
    }
    
    /**
     * Ejecuta el procesamiento de los documentos contenidos en un string json.
     * 
     * Este método puede ser utilizado desde diferentes instancias en el desarrollo en donde se requiera procesar información de documentos en Recepción (estado RDI)
     * 
     * Ejemplo de la cadena json a recibir y que debe cumplir las reglas de validación:
     * {
     *      "ofe_identificacion": "800123456",
     *      "pro_identificacion": "",
     *      "usu_identificacion": "123456",
     *      "lote_procesamiento": "20221123140344905_d60289e8-d630-4170-9edf-73e88d399671",
     *      "origen": "RPA",
     *      "documentos": [{ // Puede incluir uno o varios documentos
     *          "nombre": "fb5380e5bdc35bde..30e97dcc5bc602a701",
     *          "cufe": null, // No es obligatorio cuando se envía xml
     *          "xml": "PD94bWwgdmVyc2lv", // No es obligatorio cuando se envía cufe
     *          "pdf": "JVBERi0xLjQKMyAwI" // Puede o no ser enviado
     *      }]
     *  }
     *
     * @param int $age_id ID del agendamiento que esta siendo procesado. Es últil para poder encadenar los errores generados de manera global
     * @param string $infoDocumentos Cadena json valida con la información de los documentos
     * @return array Array conteniendo errores generados durante el proceso para que puedan ser registros por el método que llama a procesarDocumentos
     */
    public function procesarDocumentos(int $age_id, string $infoDocumentos): array { 
        $arrInfoDocumentos = json_decode($infoDocumentos, true);
        $validaciones      = $this->validarInfoDocumentos($arrInfoDocumentos);

        if(!empty($validaciones))
            return [
                'message' => 'Errores al procesar los documentos',
                'errors'  => [
                    'Agendamiento [' . $age_id . ']' => $validaciones
                ]
            ];

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo'])
            ->where('ofe_identificacion', $arrInfoDocumentos['ofe_identificacion'])
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe)
            return [
                'message' => 'Errores al procesar los documentos',
                'errors'  => [
                    'Agendamiento [' . $age_id . ']' => 'El OFE con identificación [' . $arrInfoDocumentos['ofe_identificacion'] . '] no existe o se encuentra inactivo'
                ]
            ];

        $errores              = [];
        $documentosProcesados = [];
        foreach($arrInfoDocumentos['documentos'] as $documento) {
            $this->reconectarDB();
            
            if(!array_key_exists('xml', $documento) || (array_key_exists('xml', $documento) && empty($documento['xml']))) {
                try {
                    $consultaXml = $this->tenantService->peticionMicroservicio('DO', 'POST', '/api/recepcion/documentos/procesar-peticion-proceso-rdi', [
                        'ofe_id'               => $ofe->ofe_id,
                        'cufe'                 => $documento['cufe'],
                        'metodo_consulta_dian' => 'GetXmlByDocumentKey'
                    ], 'json');

                    if($consultaXml->successful())
                        $documento['xml'] = $consultaXml->json()['data']['xml'];
                    else 
                        if(array_key_exists('message', $consultaXml->json()) && array_key_exists('errors', $consultaXml->json()))
                            $errores[] = [
                                'documento' => $documento['nombre'],
                                'errors' => [$consultaXml->json()['message'] . ' [' . (implode(' || ', $consultaXml->json()['errors'])) . ']'],
                                'fecha_procesamiento' => date('Y-m-d'),
                                'hora_procesamiento'  => date('H:i:s'),
                            ];
                } catch (\Throwable $e) {
                    $respuesta = $e->response->json();

                    if(array_key_exists('message', $respuesta) && array_key_exists('errors', $respuesta))
                        $errores[] = ['errors' => 'Documento [' . $documento['nombre'] . ']: ' . $respuesta['message'] . ' [' . (implode(' || ', $respuesta['errors'])) . ']'];
                    else
                        $errores[] = ['errors' => 'Documento [' . $documento['nombre'] . ']: ' . $respuesta['message']];
                }
            }

            if(!empty($documento['xml'])) {
                // Inicia el procesamiento del XML
                try {
                    $this->parserXml->setLote($arrInfoDocumentos['lote_procesamiento']);
                    $documentoProcesado = $this->parserXml->Parser($arrInfoDocumentos['origen'], $documento['xml'], (array_key_exists('pdf', $documento) && !empty($documento['pdf']) ? $documento['pdf'] : null), $documento['nombre']);

                    if(array_key_exists('procesado', $documentoProcesado) && $documentoProcesado['procesado']) {
                        // Solamente se incluyen en el array los documentos cuyo OFE tenga configurado el evento GETSTAUS en la columna ofe_recepcion_eventos_contratados_titulo_valor
                        $documentoOfeGetStatus = RepCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id'])
                            ->where('cdo_id', $documentoProcesado['cdo_id'])
                            ->with([
                                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_recepcion,ofe_recepcion_eventos_contratados_titulo_valor,ofe_recepcion_fnc_activo,estado'
                            ])
                            ->whereHas('getConfiguracionObligadoFacturarElectronicamente', function($query) {
                                $query->where('estado', 'ACTIVO')
                                    ->where('ofe_recepcion', 'SI')
                                    ->whereJsonContains('ofe_recepcion_eventos_contratados_titulo_valor',['evento'=>'GETSTATUS']);
                            })
                            ->first();

                        if($documentoOfeGetStatus) {
                            $documentosProcesados[] = [
                                'cdo_id'                   => $documentoProcesado['cdo_id'],
                                'ofe_recepcion_fnc_activo' => $ofe->ofe_recepcion_fnc_activo
                            ];
                        }
                    }

                    if(!empty($documento['xml']) && ((array_key_exists('pdf', $documento) && !empty($documento['pdf']) || !array_key_exists('pdf', $documento)))) {
                        $pdf = $this->generarRg($errores, $documento['nombre'], (array_key_exists('pdf', $documento) && !empty($documento['pdf']) ? $documento['pdf'] : ''), $documento['xml']);

                        if(!empty($pdf)) {
                            $documentoRecepcion = RepCabeceraDocumentoDaop::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'fecha_creacion'])
                                ->find($documentoProcesado['cdo_id']);
                            $this->guardarArchivoEnDisco($arrInfoDocumentos['ofe_identificacion'], $documentoRecepcion, 'recepcion', 'rg', 'pdf', $pdf, 'rg_' . $documento['nombre'] . '.pdf');
                        }
                    }
                } catch (\Exception $e) {
                    $documentoElectronico = '';
                    if ($e instanceof RecepcionException) {
                        $documentoElectronico = $e->getDocumento();
                    }

                    $errores[] = [
                        'documento'           => (!is_array($documentoElectronico) && !empty($documentoElectronico)) ? $documentoElectronico : '',
                        'consecutivo'         => (is_array($documentoElectronico) && array_key_exists('cdo_consecutivo', $documentoElectronico)) ? $documentoElectronico['cdo_consecutivo'] : '',
                        'prefijo'             => (is_array($documentoElectronico) && array_key_exists('cdo_prefijo', $documentoElectronico)) ? $documentoElectronico['cdo_prefijo'] : '',
                        'errors'              => [$e->getMessage()],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'archivo'             => '',
                        'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                    ];
                }
            }
        }

        if(!empty($documentosProcesados))
            $this->crearAgendamientosGetStatus($documentosProcesados, (array_key_exists('epm_id', $arrInfoDocumentos) && !empty($arrInfoDocumentos['epm_id']) ? $arrInfoDocumentos['epm_id'] : 0));

        return [
            'message' => 'Procesamiento finalizado',
            'errors'  => $errores
        ];
    }
}