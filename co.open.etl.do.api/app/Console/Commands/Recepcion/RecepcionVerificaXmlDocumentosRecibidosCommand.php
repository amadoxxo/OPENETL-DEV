<?php
namespace App\Console\Commands\Recepcion;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use App\Http\Modulos\RgController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Http\Modulos\Recepcion\GetStatus;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\TransmitirDocumentosDian\FirmarEnviarSoap;
use openEtl\Tenant\Helpers\Recepcion\TenantXmlUblExtractorHelper;
use App\Http\Modulos\TransmitirDocumentosDian\TransmitirDocumentosDian;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class RecepcionVerificaXmlDocumentosRecibidosCommand extends Command {
    use DoTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-verifica-xml-documentos-recibidos {bdd_nombre : Nombre de la base de datos} {fecha_creacion : Fecha de creación del Documento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permite verificar si existe el archivo XML para los documentos.';

    /**
     * Nombre de la conexión Tenant por defecto a la base de datos.
     *
     * @var string
     */
    protected $connection = 'conexion01';

    /**
     * Instancia de la base de datos sobre la que se está trabajando.
     *
     * @var AuthBaseDatos
     */
    protected $baseDatos;

    /**
     * Instancia de la clase MetodosBase.
     *
     * @var MetodosBase
     */
    protected $classMetodosBase;

    /**
     * Instancia de la clase GetStatus.
     *
     * @var GetStatus
     */
    protected $classGetStatus;

    /**
     * Instancia de la clase TransmitirDocumentosDian.
     *
     * @var TransmitirDocumentosDian
     */
    protected $classTransmitir;

    /**
     * Instancia de la clase FirmarEnviarSoap.
     *
     * @var FirmarEnviarSoap
     */
    protected $classFirmarSoap;

    /**
     * Instancia de la clase RgController.
     *
     * @var RgController
     */
    protected $rgController;

    /**
     * Array de paramétricas de openETL.
     *
     * @var array
     */
    protected $parametricas;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();

        // Array de objetos de paramétricas
        $this->parametricas = [
            'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_metodo', 'add_descripcion', 'add_url', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()->toArray()
        ];
    }

    /**
     * Execute the console command.
     * 
     * @return void
     */
    public function handle() {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $this->baseDatos = AuthBaseDatos::select(['bdd_id', 'bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password'])
            ->where('bdd_nombre', $this->argument('bdd_nombre'))
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$this->baseDatos) {
            $this->error('La base de datos [' . $this->argument('bdd_nombre') . '] no existe o se encuentra inactiva');
            die();
        }

        // Usuario a autenticar para poder acceder a los modelos tenant
        $user = User::where('bdd_id', $this->baseDatos->bdd_id)
            ->where('usu_type', 'ADMINISTRADOR')
            ->where('estado', 'ACTIVO')
            ->first();
    
        if(!$user) {
            $this->error('No se encontró el usuario [ADMINISTRADOR] de la base de datos [' . $this->argument('bdd_nombre') . '] o el usuario se encuentra inactivo');
            die();
        }

        auth()->login($user);

        // Inicialización de clases a usar
        $this->classMetodosBase = new MetodosBase();
        $this->rgController     = new RgController();
        $this->classTransmitir  = new TransmitirDocumentosDian();
        $this->classFirmarSoap  = new FirmarEnviarSoap();
        $this->classGetStatus   = new GetStatus();

        $this->info('Documentos sin archivo en Disco');
        $this->info('===================================================');

        RepCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha', 'cdo_hora', 'cdo_cufe', 'fecha_creacion'])
            ->where('fecha_creacion', '>=', $this->argument('fecha_creacion') . ' 00:00:00')
            ->whereNotNull('cdo_cufe')
            ->with([
                'getRdi:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
                'getGetStatus:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,ofe_archivo_certificado,ofe_password_certificado',
                'getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id',
                'getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id'
            ])
            ->get()
            ->map(function($documento) {
                try {
                    $this->reiniciarConexion($this->baseDatos);
                    
                    if ($documento->getRdi) {
                        $fechaHoraDoc = explode(' ', $documento->fecha_creacion);
                        $fechaDoc     = explode('-', $fechaHoraDoc[0]);
                        $horaDoc      = explode(':', $fechaHoraDoc[1]);

                        // Se construye la ruta del disco
                        $ruta = config('variables_sistema.RUTA_DISCO_DOCUMENTOS_ELECTRONICOS') . '/' . 
                            $this->argument('bdd_nombre') . '/' . $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/recepcion/' . 
                            $fechaDoc[0] . '/' . $fechaDoc[1]  . '/' . $fechaDoc[2] . '/' . 
                            $horaDoc[0] . '/' . $horaDoc[1] . '/' . $documento->cdo_id;

                        // Se obtiene el nombre del archivo
                        $informacionAdicional = !empty($documento->getRdi->est_informacion_adicional) ? json_decode($documento->getRdi->est_informacion_adicional, true) : [];
                        $nombreArchivo        = array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null;

                        if(!File::isFile($ruta . '/' . $nombreArchivo)) {
                            // $this->info('Para el documento con ID [' . $documento->cdo_id . '] y número [' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '] no existe el archivo con nombre ' . $nombreArchivo);
                            // $this->obtenerXmlDianGenerarEstados($documento);
                            $this->info($documento->ofe_id."~".$documento->cdo_cufe);
                        }
                    } else {
                        $this->info($documento->ofe_id."~".$documento->cdo_cufe);
                        // $this->info('Para el documento con ID [' . $documento->cdo_id . '] y número [' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '] no existe el archivo');
                        // $this->obtenerXmlDianGenerarEstados($documento);
                    }

                    // $this->line('');
                    // $this->line('----------------------------------------------------');
                } catch (\Exception $e) {
                    $this->error('Error en archivo: [' . $e->getFile() . '] - línea: [' . $e->getLine() . '] - Mensaje: ' . $e->getMessage());
                }
            });

        DB::disconnect($this->connection);
    }

    /**
     * Procesa un documento para poder obtener el XML desde la DIAN, crear archivos en disco y crear los estados correspondientes.
     *
     * @param RepCabeceraDocumentoDaop $documento Instancia del documento a procesar
     * @return void
     */
    private function obtenerXmlDianGenerarEstados(RepCabeceraDocumentoDaop $documento): void {
        if(!empty($nombreArchivo)) {
            $nombreArchivo = explode('.', $nombreArchivo);
            $nombreArchivo = $nombreArchivo[0];
        } else {
            $nombreArchivo = $documento->rfa_prefijo . $documento->cdo_consecutivo . (str_replace(['-', ':', ' '], [''], ($documento->cdo_fecha . $documento->cdo_hora)));
        }

        if($documento->getRdi) {
            $informacionAdicional = !empty($documento->getRdi->est_informacion_adicional) ? json_decode($documento->getRdi->est_informacion_adicional, true) : [];
            if(array_key_exists('est_xml', $informacionAdicional) && !empty($informacionAdicional['est_xml']))
                $nombreXml = $informacionAdicional['est_xml'];
            else
                $nombreXml = $documento->rfa_prefijo . $documento->cdo_consecutivo . (str_replace(['-', ':', ' '], [''], ($documento->cdo_fecha . $documento->cdo_hora))) . '.xml';

            if(array_key_exists('est_json', $informacionAdicional) && !empty($informacionAdicional['est_json']))
                $nombreJson = $informacionAdicional['est_json'];
            else
                $nombreJson = $documento->rfa_prefijo . $documento->cdo_consecutivo . (str_replace(['-', ':', ' '], [''], ($documento->cdo_fecha . $documento->cdo_hora))) . '.json';

            if(array_key_exists('est_archivo', $informacionAdicional) && !empty($informacionAdicional['est_archivo']))
                $nombrePdf = $informacionAdicional['est_archivo'];
            else
                $nombrePdf = $documento->rfa_prefijo . $documento->cdo_consecutivo . (str_replace(['-', ':', ' '], [''], ($documento->cdo_fecha . $documento->cdo_hora))) . '.pdf';

            $informacionAdicional['est_xml']     = $nombreXml;
            $informacionAdicional['est_archivo'] = $nombrePdf;
            $informacionAdicional['est_json']    = $nombreJson;
        } else {
            $nombreXml  = $documento->rfa_prefijo . $documento->cdo_consecutivo . (str_replace(['-', ':', ' '], [''], ($documento->cdo_fecha . $documento->cdo_hora))) . '.xml';
            $nombreJson = $documento->rfa_prefijo . $documento->cdo_consecutivo . (str_replace(['-', ':', ' '], [''], ($documento->cdo_fecha . $documento->cdo_hora))) . '.json';
            $nombrePdf  = $documento->rfa_prefijo . $documento->cdo_consecutivo . (str_replace(['-', ':', ' '], [''], ($documento->cdo_fecha . $documento->cdo_hora))) . '.pdf';
        }

        $this->info('Intentando obtener archivo XML de la DIAN');

        if($documento->cdo_clasificacion != 'DS' && $documento->cdo_clasificacion != 'DS_NC') {
            if(empty($documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico)) {
                $this->error('El Software de Proveedor Tecnológico para Documento Electrónico no se encuentra parametrizado.');
                return;
            }

            $urlAmbienteDestino = $this->classMetodosBase->obtieneDatoParametrico(
                $this->parametricas['ambienteDestino'],
                'add_id',
                $documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id,
                'add_url'
            );
        } else {
            if(empty($documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs)) {
                $this->error('El Software de Proveedor Tecnológico para Documento Soporte no se encuentra parametrizado.');
                return;
            }

            $urlAmbienteDestino = $this->classMetodosBase->obtieneDatoParametrico(
                $this->parametricas['ambienteDestino'],
                'add_id',
                $documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->add_id,
                'add_url'
            );
        }

        $obtenerXmlDocumento = $this->classGetStatus->requestDianWS(
            $this->classTransmitir,
            $this->classFirmarSoap,
            'GetXmlByDocumentKey',
            $urlAmbienteDestino,
            $documento->cdo_cufe,
            $this->baseDatos->bdd_nombre,
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_archivo_certificado,
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_password_certificado
        );

        $archivoGuardado = null;
        if(array_key_exists('rptaDian', $obtenerXmlDocumento) && !empty($obtenerXmlDocumento['rptaDian']))
            $archivoGuardado = $this->guardarXmlDocumento(
                $documento,
                $obtenerXmlDocumento['rptaDian'],
                'GetXmlByDocumentKey',
                $nombreXml
            );
        
        if(empty($archivoGuardado)) {
            $this->error('No fue posible obtener el XML de la DIAN');
            return;
        }
            
        $this->info('Archivo XML descargado de la DIAN y guardado');
        $this->info('Extrayendo información del documento para guardado del archivo JSON y generación de la RG');

        // Archivo JSON
        $extractorXml = new TenantXmlUblExtractorHelper();
        $jsonXML      = base64_decode($extractorXml($archivoGuardado['xml']));
        $this->guardarArchivoEnDisco(
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento,
            'recepcion',
            '',
            'json',
            base64_encode($jsonXML),
            $nombreJson
        );

        $this->info('Archivo JSON generado y guardado');
        $this->info('Intentando generar la RG del documento');

        // Representación Gráfica
        $request = new Request();
        $request->merge([
            'proceso_interno' => true,
            'json'            => $jsonXML
        ]);

        $rg = $this->rgController->recepcionGenerarRg($request);

        if(isset($rg->original) && array_key_exists('errors', $rg->original)) {
            $this->error(html_entity_decode(implode(' // ', $rg->original['errors'])));
            return;
        } elseif(array_key_exists('errors', $rg) && !empty($rg['errors'])) {
            $this->error(implode(' // ', $rg['errors']));
            return;
        } elseif(!array_key_exists('pdf', $rg) || (array_key_exists('pdf', $rg) && empty($rg['pdf']))) {
            $this->error('No fue posible generar la Representación Gráfica del documento');
            return;
        }

        $this->guardarArchivoEnDisco(
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento,
            'recepcion',
            '',
            'pdf',
            base64_encode($rg['pdf']),
            $nombrePdf
        );

        $this->info('RG generada y guardada');

        if(!$documento->getRdi) {
            $this->crearEstado(
                $documento->cdo_id,
                'RDI',
                'EXITOSO',
                json_encode([
                    'est_xml'        => $nombreXml,
                    'est_archivo'    => $nombrePdf,
                    'est_json'       => $nombreJson,
                    'documento'      => $nombreXml,
                    'consecutivo'    => $documento->rfa_prefijo . $documento->cdo_consecutivo,
                    'inconsistencia' => null
                ]),
                null,
                null,
                'FINALIZADO'
            );

            $this->info('Estado RDI creado');
        } else {
            $documento->getRdi->update([
                'est_informacion_adicional' => json_encode($informacionAdicional)
            ]);
        }

        if(!$documento->getGetStatus) {
            $ageGetStatus = $this->crearAgendamiento('RGETSTATUS');

            $this->crearEstado(
                $documento->cdo_id,
                'GETSTATUS',
                null,
                null,
                $ageGetStatus->age_id,
                auth()->user()->usu_id,
                'FINALIZADO'
            );

            $this->info('Estado GETSTATUS creado');
        }

        return;
    }

    /**
     * Guarda en el disco del servidor el XML de un evento obtenido en una consulta a la DIAN.
     *
     * @param RepCabeceraDocumentoDaop $documento Instancia del documento en procesamiento
     * @param string $rptaDian Respuesta de la DIAN a la consulta del XML del evento
     * @param string $metodoWsDian Método del WS de la DIAN utilizado para la consulta 
     * @param string $nombreArchivo Nombre de archivo del XML con el que se debe guardar en disco
     * @return string|null
     */
    private function guardarXmlDocumento(RepCabeceraDocumentoDaop $documento, string $rptaDian, string $metodoWsDian, string $nombreArchivo) {
        libxml_use_internal_errors(true);

        $oXML         = new \SimpleXMLElement($rptaDian);
        $vNameSpaces  = $oXML->getNamespaces(true);
        $nodoResponse = $metodoWsDian . 'Response';
        $nodoResult   = $metodoWsDian . 'Result';

        $oBody = $oXML->children($vNameSpaces['s'])
            ->Body
            ->children($vNameSpaces[''])
            ->$nodoResponse
            ->children($vNameSpaces[''])
            ->$nodoResult
            ->children($vNameSpaces['b']);

        if((isset($oBody->XmlBase64Bytes) && $oBody->XmlBase64Bytes != '') || (isset($oBody->XmlBytesBase64) && $oBody->XmlBytesBase64 != '')) {
            // Valida si el xml recibido en la respuesta contiene un string XML válido
            $xmlString = base64_encode($this->eliminarCaracteresBOM(base64_decode($oBody->XmlBase64Bytes ? $oBody->XmlBase64Bytes : $oBody->XmlBytesBase64)));
            $xmlObject = simplexml_load_string(base64_decode($xmlString));
            if ($xmlObject !== false) {
                return [
                    'xml' => $xmlString,
                    'nombre_archivo' => $this->guardarArchivoEnDisco(
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            'recepcion',
                            '',
                            'xml',
                            $xmlString,
                            $nombreArchivo
                        )
                ];
            }
        }

        return null;
    }

    /**
     * Crea un agendamiento en el sistema.
     *
     * @param string $ageProceso Proceso a agendar
     * @return AdoAgendamiento
     */
    private function crearAgendamiento(string $ageProceso): AdoAgendamiento {
        return AdoAgendamiento::create([
            'usu_id'                  => auth()->user()->usu_id,
            'bdd_id'                  => !empty(auth()->user()->getBaseDatosRg) ? auth()->user()->getBaseDatosRg->bdd_id : auth()->user()->getBaseDatos->bdd_id,
            'age_proceso'             => $ageProceso,
            'age_cantidad_documentos' => 1,
            'age_prioridad'           => null,
            'usuario_creacion'        => auth()->user()->usu_id,
            'estado'                  => 'ACTIVO'
        ]);
    }

    /**
     * Crea un estado para el documento.
     *
     * @param int $cdo_id ID del documento
     * @param string $estadoDescripcion Descripción del estado
     * @param string|null $resultado Resultado del estado
     * @param string|null $informacionAdicional Objeto conteniendo información relacionada con el objeto
     * @param int|null $ageId ID del agendamiento
     * @param int|null $ageUsuId ID del usuario del agendamiento
     * @param string|null $ejecucion Estado final de procesamiento
     * @return void
     */
    private function crearEstado(int $cdo_id, string $estadoDescripcion, $resultado = null, $informacionAdicional = null, $ageId = null, $ageUsuId = null, $ejecucion = null): void {
        RepEstadoDocumentoDaop::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estadoDescripcion,
            'est_resultado'             => $resultado,
            'est_informacion_adicional' => $informacionAdicional,
            'age_id'                    => $ageId,
            'age_usu_id'                => $ageUsuId,
            'est_ejecucion'             => $ejecucion,
            'usuario_creacion'          => auth()->user()->usu_id,
            'estado'                    => 'ACTIVO',
        ]);
    }
}
