<?php
namespace App\Http\Modulos\EventStatusUpdate;

use Validator;
use Carbon\Carbon;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Modulos\Recepcion\GetStatus;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\TransmitirDocumentosDian\FirmarEnviarSoap;
use App\Http\Modulos\TransmitirDocumentosDian\TransmitirDocumentosDian;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaop;

class EventStatusUpdate extends Controller {
    use FechaVigenciaValidations, DoTrait;
    
    /**
     * Proceso sobre el cual se actualizará el estado en la DIAN.
     *
     * @var string
     */
    public $proceso = null;

    /**
     * Nombre de la clase principal de cabecera de documento.
     *
     * @var string
     */
    public $classCabecera = null;

    /**
     * Nombre de la clase principal de estados de documento.
     *
     * @var string
     */
    public $classEstado = null;

    /**
     * Constructor de la clase.
     *
     * @param string $proceso Proceso sobre el cual se actualizará el estado en la DIAN
     */
    public function __construct($proceso) {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->proceso = $proceso;

        switch($proceso) {
            case 'recepcion':
                $this->classCabecera = RepCabeceraDocumentoDaop::class;
                $this->classEstado   = RepEstadoDocumentoDaop::class;
                break;
            case 'emision':
                $this->classCabecera = EtlCabeceraDocumentoDaop::class;
                $this->classEstado   = EtlEstadosDocumentoDaop::class;
                break;
            case 'radian':
                $this->classCabecera = RadianCabeceraDocumentoDaop::class;
                $this->classEstado   = RadianEstadoDocumentoDaop::class;
                break;
        }
    }

    /**
     * Actualiza el estado de un documento.
     *
     * @param int $cdo_id ID del documento en procesamiento
     * @param int $est_id ID del estado a actualizar
     * @param string $resultado Resultado del estado
     * @param string $mensaje Mensaje del estado
     * @param object $respuestaObject Objeto de respuesta de la DIAN
     * @param string $finProceso Fecha y hora del final del procesamiento
     * @param string $tiempoProcesamiento Tiempo de procesamiento
     * @param string|array $estadoInformacionAdicional Información adicional del estado
     * @param string $ejecucion Estado de ejecucion del proceso
     * @param string $cdoFechaValidacionDian Fecha de validación en la DIAN
     * @param string $nombreEstado Estado que se ejecutaba sobre el documento
     * @param string $nombreArchivoDisco Nombre del archivo en disco
     * @return void
     */
    public function actualizaEstadoDocumento(int $cdo_id, int $est_id, string $resultado, string $mensaje = '', array $respuestaObject = null, string $finProceso = '', string $tiempoProcesamiento = '', $estadoInformacionAdicional = null, string $ejecucion = '', string $cdoFechaValidacionDian = null, string $nombreEstado = '', string $nombreArchivoDisco = null): void {
        $estado = $this->classEstado::select(['est_id', 'est_informacion_adicional'])
            ->where('est_id', $est_id)
            ->first();

        // Si el estado tenia información registrada en información adicional se debe conservar
        $estadoInformacionAdicional = $estadoInformacionAdicional != null ? json_decode($estadoInformacionAdicional, true) : [];
        if($estado->est_informacion_adicional != '') {
            $informacionAdicionalExiste = json_decode($estado->est_informacion_adicional, true);
            $estadoInformacionAdicional = array_merge($informacionAdicionalExiste, $estadoInformacionAdicional);
        }

        if(!is_null($nombreArchivoDisco)) {
            $estadoInformacionAdicional = array_merge($estadoInformacionAdicional, ['est_xml' => $nombreArchivoDisco]);
        }

        $estado->update([
                'est_resultado'             => $resultado,
                'est_mensaje_resultado'     => $mensaje,
                'est_object'                => $respuestaObject,
                'est_informacion_adicional' => empty($estadoInformacionAdicional) ? null : json_encode($estadoInformacionAdicional),
                'est_fin_proceso'           => $finProceso,
                'est_tiempo_procesamiento'  => $tiempoProcesamiento,
                'est_ejecucion'             => $ejecucion
            ]);

        $documento = $this->classCabecera::select([
            'cdo_id',
            'cdo_fecha_validacion_dian'
        ])
            ->find($cdo_id);

        if($cdoFechaValidacionDian != '' && $cdoFechaValidacionDian != null) {
            $documento->update([
                    'cdo_fecha_validacion_dian' => $cdoFechaValidacionDian
                ]);
        }

        if(is_array($estadoInformacionAdicional) && array_key_exists('created', $estadoInformacionAdicional) && $resultado == 'EXITOSO') {
            $created = str_replace(['<u:Created>', '</u:Created>'], ['', ''], $estadoInformacionAdicional['created']);
            $fecha   = Carbon::parse($created)->format('Y-m-d H:i:s');

            switch($nombreEstado) {
                case 'acuse':
                    $documento->update([
                        'cdo_fecha_acuse' => $fecha
                    ]);
                    break;
                case 'recibo':
                    $documento->update([
                        'cdo_fecha_recibo_bien' => $fecha
                    ]);
                    break;
                case 'aceptacion':
                    $documento->update([
                        'cdo_fecha_estado' => $fecha,
                        'cdo_estado'       => 'ACEPTADO'
                    ]);
                    break;
                case 'aceptaciont':
                    $documento->update([
                        'cdo_fecha_estado' => $fecha,
                        'cdo_estado'       => 'ACEPTADOT'
                    ]);
                    break;
                case 'rechazo':
                    $documento->update([
                        'cdo_fecha_estado' => $fecha,
                        'cdo_estado'       => 'RECHAZADO'
                    ]);
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Realiza el Acuse de Recibo, Recibo de Bien, Aceptación o Rechazo de documentos eletrónicos en la DIAN.
     *
     * @param AdoAgendamiento $agendamiento Modelo del agendamiento en proceso
     * @param User $user Modelo del usuario relacionado con el agendamiento (usuario autenticado)
     * @param array $documentosProcesar Array de documentos para los cuales se actualizará el estado en la DIAN
     * @param array $estadosProcesar Array de los estados de documentos para los cuales se actualizará el estado en la DIAN
     * @param string $estado Estado del documento que se actualizará en la DIAN
     * @param string $prefijoArchivoDisco Prefijo que se aplica al nombre del archivo que se creará en disco
     * @return array
     */
    public function sendEventStatusUpdate(AdoAgendamiento $agendamiento, User $user, array $documentosProcesar, array $estadosProcesar, string $estado, string $prefijoArchivoDisco = ''): array {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Array de objetos de paramétricas
        $parametricas = [
            'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_metodo', 'add_descripcion', 'add_url', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()->toArray()
        ];

        $procesados      = [];
        $metodosBase     = new MetodosBase();
        $classTransmitir = new TransmitirDocumentosDian();
        $firmarSoap      = new FirmarEnviarSoap();
        $classGetStatus  = new GetStatus();
        foreach($documentosProcesar as $cdo_id => $documento) {
            try {
                // Variables que intercambian dependiendo del proceso 
                $paramProcesoConsecutivoZip = ($this->proceso !== 'radian') ? '' : 'radian';
                $id_actor_ofe        = ($this->proceso !== 'radian') ? $documentosProcesar[$cdo_id]['ofe_id'] : $documentosProcesar[$cdo_id]['act_id'];
                $identificacion      = ($this->proceso !== 'radian') ? $documentosProcesar[$cdo_id]['ofe_identificacion'] : $documentosProcesar[$cdo_id]['act_identificacion'];
                $archivoCertificado  = ($this->proceso !== 'radian') ? $documentosProcesar[$cdo_id]['ofe_archivo_certificado'] : $documentosProcesar[$cdo_id]['act_archivo_certificado'];
                $passwordCertificado = ($this->proceso !== 'radian') ? $documentosProcesar[$cdo_id]['ofe_password_certificado'] : $documentosProcesar[$cdo_id]['act_password_certificado'];

                // Marca el inicio de procesamiento del documento en el estado correspondiente
                $this->classEstado::find($estadosProcesar[$cdo_id]['est_id'])
                    ->update([
                        'est_ejecucion'      => 'ENPROCESO',
                        'est_inicio_proceso' => date('Y-m-d H:i:s')
                    ]);

                // Actualiza el microtime de inicio de procesamiento del documento
                $estadosProcesar[$cdo_id]['inicio'] = microtime(true);

                // Se obtiene el nombre del archivo del registro de cabecera
                $archivoXml = $documentosProcesar[$cdo_id]['nombre_archivos']['xml_' . $estado];

                // Obtiene el consecutivo para el zip con el que se enviará el AR del status del documento
                $consecutivoZip = DoTrait::obtenerConsecutivoArchivo($id_actor_ofe, $user->usu_id, 'z', $paramProcesoConsecutivoZip);

                // Procesamiento a nivel de archivo a transmitir
                $nombreArchivo = str_pad($identificacion, 10, '0', STR_PAD_LEFT) .
                    config('variables_sistema.CODIGO_PT') .
                    date('y');

                $archivoZip     = 'z' . $nombreArchivo . DoTrait::DecToHex($consecutivoZip) . '.zip';
                $rutaArchivoZip = storage_path() . '/' . $archivoZip;
                $certificado    = config('variables_sistema.PATH_CERTIFICADOS') . '/' . $documentosProcesar[$cdo_id]['bdd_nombre'] . '/' . $archivoCertificado;
                
                $oZip = new \ZipArchive();
                $oZip->open($rutaArchivoZip, \ZipArchive::OVERWRITE | \ZipArchive::CREATE);
                $oZip->addFromString($archivoXml, base64_decode($documentosProcesar[$cdo_id]['xml_ubl']));
                $oZip->close();

                $zipContent  = base64_encode(file_get_contents($rutaArchivoZip));
                @unlink($rutaArchivoZip);

                if ($this->proceso == 'radian')
                    $documentosProcesar[$cdo_id]['cdo_clasificacion'] = 'FC';

                if($documentosProcesar[$cdo_id]['cdo_clasificacion'] != 'DS' && $documentosProcesar[$cdo_id]['cdo_clasificacion'] != 'DS_NC') {
                    if(empty($documentosProcesar[$cdo_id]['ambiente_destino']))
                        throw new \Exception('El Software de Proveedor Tecnológico para Documento Electrónico no se encuentra parametrizado.');
    
                    $metodoAmbienteDestino = $metodosBase->obtieneDatoParametrico(
                        $parametricas['ambienteDestino'],
                        'add_id',
                        $documentosProcesar[$cdo_id]['ambiente_destino'],
                        'add_metodo'
                    );
    
                    //Si el ambiente destino es habilitacion debe consumirse SendTestSetAsync, sino SendEventUpdateStatus;
                    $metodoAmbienteDestino = ($metodoAmbienteDestino == 'SendTestSetAsync') ? $metodoAmbienteDestino : 'SendEventUpdateStatus';
    
                    $urlAmbienteDestino = $metodosBase->obtieneDatoParametrico(
                        $parametricas['ambienteDestino'],
                        'add_id',
                        $documentosProcesar[$cdo_id]['ambiente_destino'],
                        'add_url'
                    );

                    $testSetId = $documentosProcesar[$cdo_id]['test_set_id'];
                } else {
                    if($this->proceso !== 'radian' && empty($documentosProcesar[$cdo_id]['ambiente_destino_ds']))
                        throw new \Exception('El Software de Proveedor Tecnológico para Documento Soporte no se encuentra parametrizado.');
    
                    $metodoAmbienteDestino = $metodosBase->obtieneDatoParametrico(
                        $parametricas['ambienteDestino'],
                        'add_id',
                        $documentosProcesar[$cdo_id]['ambiente_destino_ds'],
                        'add_metodo'
                    );

                    //Si el ambiente destino es habilitacion debe consumirse SendTestSetAsync, sino SendEventUpdateStatus;
                    $metodoAmbienteDestino = ($metodoAmbienteDestino == 'SendTestSetAsync') ? $metodoAmbienteDestino : 'SendEventUpdateStatus';
    
                    $urlAmbienteDestino = $metodosBase->obtieneDatoParametrico(
                        $parametricas['ambienteDestino'],
                        'add_id',
                        $documentosProcesar[$cdo_id]['ambiente_destino_ds'],
                        'add_url'
                    );

                    $testSetId = $documentosProcesar[$cdo_id]['test_set_id_ds'];
                }

                // Transmisión a la DIAN
                $rptaDianDocumento = $classTransmitir->transmitirXml(
                    $firmarSoap,
                    $certificado,
                    $passwordCertificado,
                    $archivoZip,
                    $zipContent,
                    $urlAmbienteDestino,
                    $metodoAmbienteDestino,
                    $testSetId
                );

                // Si el estado tenia información registrada en información adicional se debe conservar
                $estadoInformacionAdicional = (array_key_exists('est_informacion_adicional', $documentosProcesar[$cdo_id]) && $documentosProcesar[$cdo_id]['est_informacion_adicional'] != null) ? json_decode($documentosProcesar[$cdo_id]['est_informacion_adicional'], true) : [];

                $cudeEvento = $classTransmitir->obtenerCodigoCudeEventoDian($metodosBase, $documentosProcesar[$cdo_id]['xml_ubl']);
                $estadoInformacionAdicional['cude'] = $cudeEvento['cude'];

                if(array_key_exists('error', $rptaDianDocumento) && empty($rptaDianDocumento['error'])) {
                    // La respuesta de la DIAN se procesa para poder definir si la transmisión fue exitosa o fallida
                    $classTransmitir->procesarRespuestaDian(
                        $firmarSoap,
                        $rptaDianDocumento['rptaDian'],
                        $metodoAmbienteDestino,
                        $urlAmbienteDestino,
                        null,
                        $certificado,
                        $passwordCertificado,
                        $procesados,
                        $cdo_id,
                        $estadoInformacionAdicional
                    );

                    if(array_key_exists('reconsultarEvento', $procesados[$cdo_id]) && $procesados[$cdo_id]['reconsultarEvento']) {
                        // Se deben consultar en la DIAN los eventos del documentos para verificar si el evento que esta siendo procesado esta registrado o no y poder obtener el AR y el XML-UBL
                        $consultarEventos = $classGetStatus->requestDianWS(
                            $classTransmitir,
                            $firmarSoap,
                            'GetStatusEvent',
                            $urlAmbienteDestino,
                            $documentosProcesar[$cdo_id]['cdo_cufe'],
                            $documentosProcesar[$cdo_id]['bdd_nombre'],
                            $archivoCertificado,
                            $passwordCertificado
                        );

                        $resultadoConsultaEvento = $classTransmitir->procesarRespuestaDianEventos($metodosBase, $consultarEventos, $documentosProcesar[$cdo_id]['xml_ubl']);

                        if($resultadoConsultaEvento['existeEvento']) {
                            // Obtiene el ApplicationResponse del registro del evento
                            $obtenerXmlEvento = $classGetStatus->requestDianWS(
                                $classTransmitir,
                                $firmarSoap,
                                'GetXmlByDocumentKey',
                                $urlAmbienteDestino,
                                $resultadoConsultaEvento['uuidEvento'],
                                $documentosProcesar[$cdo_id]['bdd_nombre'],
                                $archivoCertificado,
                                $passwordCertificado
                            );

                            if(array_key_exists('rptaDian', $obtenerXmlEvento) && !empty($obtenerXmlEvento['rptaDian']))
                                $this->guardarXmlEvento($this->classCabecera, $cdo_id, $identificacion, $estadoInformacionAdicional, $obtenerXmlEvento['rptaDian'], 'GetXmlByDocumentKey');

                            // Obtiene el ApplicationResponse de la respuesta de la DIAN al registro del evento
                            $obtenerArEvento = $classGetStatus->requestDianWS(
                                $classTransmitir,
                                $firmarSoap,
                                'GetStatus',
                                $urlAmbienteDestino,
                                $resultadoConsultaEvento['uuidEvento'],
                                $documentosProcesar[$cdo_id]['bdd_nombre'],
                                $archivoCertificado,
                                $passwordCertificado
                            );

                            $classTransmitir->procesarRespuestaDian(
                                $firmarSoap,
                                $obtenerArEvento['rptaDian'],
                                'GetStatus',
                                $urlAmbienteDestino,
                                null,
                                $certificado,
                                $passwordCertificado,
                                $procesados,
                                $cdo_id,
                                $estadoInformacionAdicional
                            );
                        } else {
                            // Si el evento no existe en la DIAN o se generó algún error se deja el registro correspondiente de acuerdo al siguiente retorno del resultado de la consulta del evento:
                            //      El evento no existe en la DIAN => error contiene 'El evento con código [XXX] no existe en la DIAN'
                            //      No se logró obtener el AR desde la respuesta de la DIAN => error contiene 'No fue posible obtener el ApplicationResponse de la respuesta de la DIAN'
                            //      Se generó una excepción en el proceso de consulta de los estados en la DIAN => error contiene el mensaje de la excepción generada

                            $procesados[$cdo_id] = [
                                'respuestaProcesada' => [
                                    'IsValid'                    => array_key_exists('IsValid', $procesados[$cdo_id]['respuestaProcesada']) ? $procesados[$cdo_id]['respuestaProcesada']['IsValid'] : '',
                                    'StatusCode'                 => array_key_exists('StatusCode', $procesados[$cdo_id]['respuestaProcesada']) ? $procesados[$cdo_id]['respuestaProcesada']['StatusCode'] : '',
                                    'StatusDescription'          => array_key_exists('StatusDescription', $procesados[$cdo_id]['respuestaProcesada']) ? $procesados[$cdo_id]['respuestaProcesada']['StatusDescription'] : '',
                                    'StatusMessage'              => $resultadoConsultaEvento['error'],
                                    'XmlDocumentKey'             => array_key_exists('XmlDocumentKey', $procesados[$cdo_id]['respuestaProcesada']) ? $procesados[$cdo_id]['respuestaProcesada']['XmlDocumentKey'] : '',
                                    'ErrorMessage'               => array_key_exists('ErrorMessage', $procesados[$cdo_id]['respuestaProcesada']) ? $procesados[$cdo_id]['respuestaProcesada']['ErrorMessage'] : '',
                                    'estado'                     => 'FALLIDO',
                                    'estadoInformacionAdicional' => json_encode($estadoInformacionAdicional),
                                    'cdoFechaValidacionDian'     => array_key_exists('cdoFechaValidacionDian', $procesados[$cdo_id]['respuestaProcesada']) ? $procesados[$cdo_id]['respuestaProcesada']['cdoFechaValidacionDian'] : ''
                                ],
                                'xmlRespuestaDian'  => array_key_exists('xmlRespuestaDian', $procesados[$cdo_id]['respuestaProcesada']) ? $procesados[$cdo_id]['respuestaProcesada']['xmlRespuestaDian'] : ''
                            ];
                        }
                    }

                    $procesados[$cdo_id]['respuestaProcesada']['usuario'] = [
                        'nombre'         => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_nombre) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_nombre)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_nombre: $user->usu_nombre,
                        'correo'         => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_correo) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_correo)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_correo: $user->usu_email,
                        'identificacion' => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion: $user->usu_identificacion
                    ];

                    $procesados[$cdo_id]['respuestaProcesada']['estadoInformacionAdicional'] = array_merge(
                        !is_array($procesados[$cdo_id]['respuestaProcesada']['estadoInformacionAdicional']) ? json_decode($procesados[$cdo_id]['respuestaProcesada']['estadoInformacionAdicional'], true) : $procesados[$cdo_id]['respuestaProcesada']['estadoInformacionAdicional'],
                        [
                        'usuario' => [
                            'nombre'         => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_nombre) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_nombre)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_nombre: $user->usu_nombre,
                            'correo'         => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_correo) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_correo)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_correo: $user->usu_email,
                            'identificacion' => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion: $user->usu_identificacion
                        ]
                    ]);
                } elseif(array_key_exists('error', $rptaDianDocumento) && !empty($rptaDianDocumento['error'])) {
                    $procesados[$cdo_id] = [
                        'respuestaProcesada' => [
                            'code'    => '',
                            'message' => $rptaDianDocumento['error'],
                            'created' => '',
                            'estado'  => 'FALLIDO',
                            'usuario' => [
                                'nombre'         => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_nombre) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_nombre)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_nombre: $user->usu_nombre,
                                'correo'         => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_correo) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_correo)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_correo: $user->usu_email,
                                'identificacion' => (isset($estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion) && !empty($estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion)) ? $estadoInformacionAdicional->usuario_portal_clientes->usu_identificacion: $user->usu_identificacion
                            ],
                            'estadoInformacionAdicional' => (array_key_exists('exceptions', $rptaDianDocumento) && $rptaDianDocumento['exceptions'] != '') ? json_encode($rptaDianDocumento['exceptions']) : null
                        ],
                        'xmlRespuestaDian'  => ''
                    ];
                }

                $nombreArchivoDisco = null;
                if(!empty($procesados[$cdo_id]['xmlRespuestaDian'])) {
                    $documento = $this->classCabecera::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'fecha_creacion'])->find($cdo_id);

                    $nombreArchivoDisco = $this->guardarArchivoEnDisco(
                        $identificacion,
                        $documento,
                        $this->proceso,
                        $prefijoArchivoDisco,
                        'xml',
                        base64_encode($procesados[$cdo_id]['xmlRespuestaDian'])
                    );
                }

                $mensajeResultado = (isset($procesados[$cdo_id]['respuestaProcesada']['message'])) ? $procesados[$cdo_id]['respuestaProcesada']['message'] : ((isset($procesados[$cdo_id]['respuestaProcesada']['StatusMessage'])) ? $procesados[$cdo_id]['respuestaProcesada']['StatusMessage'] : null);
                if ($procesados[$cdo_id]['respuestaProcesada']['estado'] != "EXITOSO") {
                    // Concatenando StatusDescription y ErrorMessage
                    $cMensajeError = '';
                    if (isset($procesados[$cdo_id]['respuestaProcesada']['StatusDescription']) && !empty(($procesados[$cdo_id]['respuestaProcesada']['StatusDescription'])))
                        $cMensajeError .= $procesados[$cdo_id]['respuestaProcesada']['StatusDescription'] . ' // ';
                    if (isset($procesados[$cdo_id]['respuestaProcesada']['ErrorMessage']) && !empty($procesados[$cdo_id]['respuestaProcesada']['ErrorMessage']))
                        $cMensajeError .= $procesados[$cdo_id]['respuestaProcesada']['ErrorMessage'] . ' // ';
                    $mensajeResultado .= substr($cMensajeError, 0 , -4);
                }

                $this->actualizaEstadoDocumento(
                    $cdo_id,
                    $estadosProcesar[$cdo_id]['est_id'],
                    $procesados[$cdo_id]['respuestaProcesada']['estado'],
                    $mensajeResultado . ' // CUDE Evento: ' . $cudeEvento['cude'],
                    (!empty($procesados[$cdo_id]['respuestaProcesada'])) ? $procesados[$cdo_id]['respuestaProcesada'] : null,
                    date('Y-m-d H:i:s'),
                    number_format((microtime(true) - $estadosProcesar[$cdo_id]['inicio']), 3, '.', ''),
                    (!empty($procesados[$cdo_id]['respuestaProcesada']['estadoInformacionAdicional'])) ? json_encode($procesados[$cdo_id]['respuestaProcesada']['estadoInformacionAdicional']) :  null,
                    'FINALIZADO',
                    null,
                    $estado,
                    $nombreArchivoDisco
                );
            } catch (\Exception $e) {
                //Estado FALLIDO, para no agendar notificacion
                $procesados[$cdo_id]['respuestaProcesada']['estado'] = 'FALLIDO';

                $arrExcepciones = [];
                $arrExcepciones[] = [
                    'documento'           => (array_key_exists('cdo_clasificacion', $documentosProcesar[$cdo_id])) ? $documentosProcesar[$cdo_id]['cdo_clasificacion'] : '',
                    'consecutivo'         => (array_key_exists('cdo_consecutivo', $documentosProcesar[$cdo_id])) ? $documentosProcesar[$cdo_id]['cdo_consecutivo'] : '',
                    'prefijo'             => (array_key_exists('rfa_prefijo', $documentosProcesar[$cdo_id])) ? $documentosProcesar[$cdo_id]['rfa_prefijo'] : '',
                    'errors'              => [$e->getMessage()],
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s'),
                    'archivo'             => '',
                    'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                ];

                $this->actualizaEstadoDocumento(
                    $cdo_id,
                    $estadosProcesar[$cdo_id]['est_id'],
                    'FALLIDO',
                    $e->getMessage(),
                    null,
                    date('Y-m-d H:i:s'),
                    number_format((microtime(true) - $estadosProcesar[$cdo_id]['inicio']), 3, '.', ''),
                    json_encode($arrExcepciones),
                    'FINALIZADO',
                    null,
                    $estado
                );
            }
        }

        return $procesados;
    }

    /**
     * Crea un nuevo agendamiento para un proceso determinado
     * 
     * @param string $proceso Nombre del proceso a agendar
     * @param Illuminate\Database\Eloquent\Collection $agendamiento Colección del agendamiento en procesamiento
     * @param Illuminate\Database\Eloquent\Collection $user Colección del usuario relacionado con el agendamiento
     * 
     * @return Illuminate\Database\Eloquent\Collection $nuevoAgendamiento Colección del nuevo agendamiento
     */
    public function nuevoAgendamiento($proceso, $agendamiento, $user, $cantidadDocumentos) {
        return AdoAgendamiento::create([
            'usu_id'                  => $agendamiento->usu_id,
            'bdd_id'                  => $user->getBaseDatos->bdd_id, 
            'age_proceso'             => $proceso,
            'age_cantidad_documentos' => $cantidadDocumentos,
            'age_prioridad'           => $agendamiento->age_prioridad,
            'usuario_creacion'        => $agendamiento->usuario_creacion,
            'estado'                  => 'ACTIVO'
        ]);
    }

    /**
     * Crea un nuevo estado para un documento electrónico.
     *
     * @param int $cdo_id ID del documento para el cual se crea el estado
     * @param string $estadoPrevio Nombre del estado previo del documento, que da origen al nuevo estado
     * @param string $estadoCrear Nombre del estado a crear
     * @param int $age_id ID del agendamiento relacionado con el estado
     * @param int $usu_id ID del usuario que crea el nuevo estado
     * @param array $informacion_adicional Información adicional del estado
     * @return void
     */
    public function creaNuevoEstadoDocumento($cdo_id, $estadoPrevio, $estadoCrear, $age_id, $usu_id, $informacion_adicional = []) {
        if(!empty($estadoPrevio)) {
            $motivo_rechazo = null;
            $consultaEstadoPrevio = $this->classEstado::select(['est_id', 'est_motivo_rechazo', 'est_informacion_adicional'])
                ->where('cdo_id', $cdo_id)
                ->where('est_estado', $estadoPrevio)
                ->where('est_resultado', 'EXITOSO')
                ->where('est_ejecucion', 'FINALIZADO')
                ->orderBy('est_id', 'desc')
                ->first();

            if($consultaEstadoPrevio) {
                $motivo_rechazo        = $consultaEstadoPrevio->est_motivo_rechazo;
                $informacion_adicional = !empty($consultaEstadoPrevio->est_informacion_adicional) ? array_merge(json_decode($consultaEstadoPrevio->est_informacion_adicional, true), $informacion_adicional) : [];
            }
        }

        $this->classEstado::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estadoCrear,
            'est_motivo_rechazo'        => isset($motivo_rechazo) && !empty($motivo_rechazo) ? $motivo_rechazo : null,
            'age_id'                    => $age_id,
            'age_usu_id'                => $usu_id,
            'est_informacion_adicional' => !empty($informacion_adicional) ? json_encode($informacion_adicional) : null,
            'usuario_creacion'          => $usu_id,
            'estado'                    => 'ACTIVO'
        ]);
    }

    /**
     * Consulta de eventos DIAN.
     *
     * @param Request $request Parámetros de la petición
     * @return array|Response
     */
    public function consultarEventosDian(Request $request) {
        try {
            $reglas = [
                'cdo_id'                    => 'required|numeric',
                'codigos_eventos_dian'      => 'required|string',
                'retornar_ar'               => 'required|boolean',
                'codigo_evento_ar_retornar' => 'required|string'
            ];

            $validador = Validator::make($request->all(), $reglas);
            if($validador->fails())
                return response()->json([
                    'message' => 'Error en la consulta de eventos DIAN',
                    'errors'  => $validador->errors()->all()
                ], 400);

            $metodosBase     = new MetodosBase();
            $classGetStatus  = new GetStatus();
            $classTransmitir = new TransmitirDocumentosDian();
            $classFirmarSoap = new FirmarEnviarSoap();

            $parametricas = [
                'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_metodo', 'add_descripcion', 'add_url', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                    ->where('estado', 'ACTIVO')
                    ->get()->toArray()
            ];

            $documento = $this->classCabecera::select(['cdo_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'ofe_id', 'cdo_cufe'])
                ->where('cdo_id', $request->cdo_id)
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_archivo_certificado,ofe_password_certificado,bdd_id_rg',
                    'getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid',
                    'getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->first();

            if(!$documento)
                return response()->json([
                    'message' => 'Error en la consulta de eventos DIAN',
                    'errors'  => ['El documento para el cual intenta consultar eventos DIAN no existe en openETL']
                ], 400);

            if($documento->cdo_clasificacion != 'DS' && $documento->cdo_clasificacion != 'DS_NC') {
                if(empty($documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico))
                    throw new \Exception('El Software de Proveedor Tecnológico para Documento Electrónico no se encuentra parametrizado.');

                $urlAmbienteDestino = $metodosBase->obtieneDatoParametrico(
                    $parametricas['ambienteDestino'],
                    'add_id',
                    $documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id,
                    'add_url'
                );
            } else {
                if(empty($documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs))
                    throw new \Exception('El Software de Proveedor Tecnológico para Documento Soporte no se encuentra parametrizado.');

                $urlAmbienteDestino = $metodosBase->obtieneDatoParametrico(
                    $parametricas['ambienteDestino'],
                    'add_id',
                    $documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->add_id,
                    'add_url'
                );
            }

            $user = auth()->user();
            $bdUser = $user->getBaseDatos->bdd_nombre;
            if(!empty($user->bdd_id_rg))
                $bdUser = $user->getBaseDatosRg->bdd_nombre;

            if(!empty($documento->getConfiguracionObligadoFacturarElectronicamente->bdd_id_rg)) {
                $bddNombre = $documento->getConfiguracionObligadoFacturarElectronicamente->getBaseDatosRg->bdd_nombre;
            } else {
                $bddNombre = $bdUser;
            }

            $bddNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);

            if(empty($documento->cdo_cufe))
                throw new \Exception('Documento sin cufe [' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '] OFE [' . $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '] Base de datos [' . $bddNombre . ']', 1);

            $consultarEventos = $classGetStatus->requestDianWS(
                $classTransmitir,
                $classFirmarSoap,
                'GetStatusEvent',
                $urlAmbienteDestino,
                $documento->cdo_cufe,
                $bddNombre,
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_archivo_certificado,
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_password_certificado
            );

            if(stristr($consultarEventos['error'], 'timeout') !== false)
                throw new \Exception($consultarEventos['error']);

            $codigosEventos = explode(',', $request->codigos_eventos_dian);

            $respuesta['eventos_dian'] = [];
            foreach($codigosEventos as $codigoEventoDian) {
                $respuesta['eventos_dian'][$codigoEventoDian] = $classTransmitir->procesarRespuestaDianEventos($metodosBase, $consultarEventos, null, $codigoEventoDian);

                if($request->retornar_ar && $respuesta['eventos_dian'][$codigoEventoDian]['existeEvento'] && $codigoEventoDian == $request->codigo_evento_ar_retornar) {
                    $obtenerArEvento = $classGetStatus->requestDianWS(
                        $classTransmitir,
                        $classFirmarSoap,
                        'GetStatus',
                        $urlAmbienteDestino,
                        $respuesta['eventos_dian'][$codigoEventoDian]['uuidEvento'],
                        $bddNombre,
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_archivo_certificado,
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_password_certificado
                    );

                    $respuesta['eventos_dian'][$codigoEventoDian]['arEvento'] = base64_encode($obtenerArEvento['rptaDian']);
                }
            }

            if($request->filled('array_response') && $request->array_response)
                return $respuesta;
            else
                return response()->json([
                    'data' => $respuesta
                ], 200);
        } catch (\Exception $e) {
            $respuesta = [
                'message' => 'Error al consultar eventos DIAN',
                'errors'  => [$e->getMessage()]
            ];

            if($request->filled('array_response') && $request->array_response)
                return $respuesta;
            else
                return response()->json($respuesta, 400);
        }
    }
}