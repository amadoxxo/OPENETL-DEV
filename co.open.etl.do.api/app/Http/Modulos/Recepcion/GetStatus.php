<?php
namespace App\Http\Modulos\Recepcion;

use Validator;
use Carbon\Carbon;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Http\Response as ResponseHttp;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\TransmitirDocumentosDian\FirmarEnviarSoap;
use App\Http\Modulos\TransmitirDocumentosDian\TransmitirDocumentosDian;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class GetStatus extends Controller {
    use TenantTrait, FechaVigenciaValidations, DoTrait;
    
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Crea un nuevo estado para un documento electrónico en recepción.
     *
     * @param int $cdo_id ID del documento para el cual se crea el estado
     * @param string $estado Nombre del estado a crear
     * @param string $resultado Resultado del estado
     * @param dateTime $inicioProceso Fecha y hora de inicio del procesamiento
     * @param dateTime $finProceso Fecha y hora del final del procesamiento
     * @param timestamp $tiempoProcesamiento Tiempo de procesamiento
     * @param int $ageId del agendamiento relacionado con el estado
     * @param int $ageUsuId del usuario que crea el nuevo estado
     * @param array $estadoInformacionAdicional Información adicional del estado
     * @param string $ejecucion Estado de ejecucion del proceso
     * @return void
     */
    public static function creaNuevoEstadoDocumentoRecepcion($cdo_id, $estado, $resultado, $inicioProceso = null, $finProceso = null, $tiempoProcesamiento = null, $ageId = null, $ageUsuId = null, $estadoInformacionAdicional = null, $ejecucion = null) {
        $user = auth()->user();

        RepEstadoDocumentoDaop::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estado,
            'est_resultado'             => $resultado,
            'est_inicio_proceso'        => $inicioProceso,
            'est_fin_proceso'           => $finProceso,
            'est_tiempo_procesamiento'  => $tiempoProcesamiento,
            'age_id'                    => $ageId,
            'age_usu_id'                => $ageUsuId,
            'est_informacion_adicional' => ($estadoInformacionAdicional != null && !empty($estadoInformacionAdicional)) ? json_encode($estadoInformacionAdicional) : null,
            'usuario_creacion'          => $user->usu_id,
            'est_ejecucion'             => $ejecucion,
            'estado'                    => 'ACTIVO'
        ]);
    }

    /**
     * Actualiza el estado de un documento.
     *
     * @param int $cdo_id ID del documento en procesamiento
     * @param int $est_id ID del estado a actualizar
     * @param string $resultado Resultado del estado
     * @param string $mensaje Mensaje del estado
     * @param object $respuestaObject Objeto de respuesta de la DIAN
     * @param dateTime $finProceso Fecha y hora del final del procesamiento
     * @param timestamp $tiempoProcesamiento Tiempo de procesamiento
     * @param array $estadoInformacionAdicional Información adicional del estado
     * @param string $ejecucion Estado de ejecucion del proceso
     * @param dateTime $cdoFechaValidacionDian Fecha de validación en la DIAN
     * @param string $estado Estado que se ejecutaba sobre el documento
     * @param string $nombreArchivoDisco Nombre del archivo guardado en disco
     * @param bool $radian Indica si se usa para el proceso de Radian
     * @return void
     */
    public static function actualizaEstadoDocumento($cdo_id, $est_id, $resultado, $mensaje = null, $respuestaObject = null, $finProceso = null, $tiempoProcesamiento = null, $estadoInformacionAdicional = null, $ejecucion = null, $cdoFechaValidacionDian = null, $estado = null, $nombreArchivoDisco = null, $radian = false): void {
        $estado = (!$radian) ? RepEstadoDocumentoDaop::select(['est_id', 'est_informacion_adicional']) : RadianEstadoDocumentoDaop::select(['est_id', 'est_informacion_adicional']);
        $estado = $estado->where('est_id', $est_id)->first();

        // Si el estado tenia información registrada en información adicional se debe conservar
        $estadoInformacionAdicional = $estadoInformacionAdicional != null ? ((is_array($estadoInformacionAdicional)) ? $estadoInformacionAdicional : json_decode($estadoInformacionAdicional, true)) : [];
        if($estado->est_informacion_adicional != '') {
            $informacionAdicionalExiste = json_decode($estado->est_informacion_adicional, true);
            $estadoInformacionAdicional = array_merge($informacionAdicionalExiste, $estadoInformacionAdicional);
        }

        if(!empty($nombreArchivoDisco))
            $estadoInformacionAdicional = array_merge($estadoInformacionAdicional, ['est_xml' => $nombreArchivoDisco]);

        $estado->update([
                'est_resultado'             => $resultado,
                'est_mensaje_resultado'     => $mensaje,
                'est_object'                => $respuestaObject,
                'est_informacion_adicional' => empty($estadoInformacionAdicional) ? null : json_encode($estadoInformacionAdicional),
                'est_fin_proceso'            => $finProceso,
                'est_tiempo_procesamiento'  => $tiempoProcesamiento,
                'est_ejecucion'             => $ejecucion
            ]);

        $documento = (!$radian) ? RepCabeceraDocumentoDaop::select(['cdo_id','cdo_fecha_validacion_dian']) : RadianCabeceraDocumentoDaop::select(['cdo_id','cdo_fecha_validacion_dian']);
        $documento = $documento->find($cdo_id);

        if($cdoFechaValidacionDian != '' && $cdoFechaValidacionDian != null) {
            $documento->update([
                    'cdo_fecha_validacion_dian' => $cdoFechaValidacionDian
                ]);
        }

        if(is_array($estadoInformacionAdicional) && array_key_exists('created', $estadoInformacionAdicional)) {
            $created = str_replace(['<u:Created>', '</u:Created>'], ['', ''], $estadoInformacionAdicional['created']);
            $fecha   = Carbon::parse($created)->format('Y-m-d H:i:s');

            switch($estado) {
                case 'acuse':
                    $documento->update([
                        'cdo_fecha_acuse' => $fecha
                    ]);
                    break;
                case 'aceptacion':
                    $documento->update([
                        'cdo_fecha_estado' => $fecha,
                        'cdo_estado'       => 'ACEPTADO'
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
     * Consulta documentos en la DIAN de acuerdo al proceso GETSTATUS.
     *
     * @param AdoAgendamiento $agendamiento Modelo del agendamiento en proceso
     * @param User $user Modelo del usuario relacionado con el agendamiento (usuario autenticado)
     * @param array $documentosConsultar Array de documentos a consultar
     * @param array $estadosConsultar Array de los estados de documentos a consultar
     * @param bool  $radian Boolean que indica si se ejecuta para el proceso de RADIAN
     * @return void
     */
    public function consultarDocumentos(AdoAgendamiento $agendamiento, User $user, array $documentosConsultar, array $estadosConsultar, bool $radian = false): void {
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

        foreach($documentosConsultar as $cdo_id => $documento) {
            try {
                if (!$radian) {
                    $modeloDocumento = RepEstadoDocumentoDaop::find($estadosConsultar[$cdo_id]['est_id']);
                } else {
                    $modeloDocumento = RadianEstadoDocumentoDaop::find($estadosConsultar[$cdo_id]['est_id']);
                }

                //Variables que cambian dependiendo del proceso: Recepción o Radian
                $archivoCertificado      = (!$radian) ? $documentosConsultar[$cdo_id]['ofe_archivo_certificado'] : $documentosConsultar[$cdo_id]['act_archivo_certificado'];
                $passwordCertificado     = (!$radian) ? $documentosConsultar[$cdo_id]['ofe_password_certificado'] : $documentosConsultar[$cdo_id]['act_password_certificado'];
                $documentoIdentificacion = (!$radian) ? $documentosConsultar[$cdo_id]['documento']->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion : $documentosConsultar[$cdo_id]['documento']->getRadActores->act_identificacion;
                $proceso                 = (!$radian) ? 'recepcion' : 'radian';
                // Marca el inicio de procesamiento del documento en el estado correspondiente
                $modeloDocumento->update([
                    'est_ejecucion'      => 'ENPROCESO',
                    'est_inicio_proceso' => date('Y-m-d H:i:s')
                ]);

                // Actualiza el microtime de inicio de procesamiento del documento
                $estadosConsultar[$cdo_id]['inicio'] = microtime(true);
                if(!$radian && $documento['cdo_clasificacion'] != 'DS' && $documento['cdo_clasificacion'] != 'DS_NC') {
                    if(empty($documentosConsultar[$cdo_id]['ambiente_destino']))
                        throw new \Exception('El Software de Proveedor Tecnológico para Documento Electrónico no se encuentra parametrizado.');

                    $urlAmbienteDestino = $metodosBase->obtieneDatoParametrico($parametricas['ambienteDestino'], 'add_id', $documentosConsultar[$cdo_id]['ambiente_destino'], 'add_url');
                } elseif (!$radian) {
                    if(empty($documentosConsultar[$cdo_id]['ambiente_destino_ds']))
                        throw new \Exception('El Software de Proveedor Tecnológico para Documento Soporte no se encuentra parametrizado.');

                    $urlAmbienteDestino = $metodosBase->obtieneDatoParametrico($parametricas['ambienteDestino'], 'add_id', $documentosConsultar[$cdo_id]['ambiente_destino_ds'], 'add_url');
                } else {
                    $urlAmbienteDestino = $metodosBase->obtieneDatoParametrico($parametricas['ambienteDestino'], 'add_id', $documentosConsultar[$cdo_id]['ambiente_destino'], 'add_url');
                }

                $soapConsultar = $classTransmitir->inicializarSoapDian('GetStatus', $urlAmbienteDestino, $documentosConsultar[$cdo_id]['cdo_cufe']);
                $rptaConsultaEstadoDocumento = $firmarSoap->firmarSoapXML(
                    $soapConsultar,
                    config('variables_sistema.PATH_CERTIFICADOS') . '/' . $documentosConsultar[$cdo_id]['bdd_nombre'] . '/' . $archivoCertificado,
                    $passwordCertificado,
                    $urlAmbienteDestino
                );

                if(array_key_exists('error', $rptaConsultaEstadoDocumento) && empty($rptaConsultaEstadoDocumento['error'])) {
                    // La respuesta de la DIAN se procesa para poder definir si la transmisión fue exitosa o fallida
                    $respuestaDianProcesada = $classTransmitir->procesarRespuestaDian(
                        $firmarSoap,
                        $rptaConsultaEstadoDocumento['rptaDian'],
                        'GetStatus',
                        null,
                        null,
                        null,
                        null,
                        $procesados,
                        $cdo_id,
                        null
                    );
                } else {
                    $procesados[$cdo_id] = [
                        'respuestaProcesada' => [
                            'IsValid'                    => 'false',
                            'StatusCode'                 => '',
                            'StatusDescription'          => $rptaConsultaEstadoDocumento['error'],
                            'StatusMessage'              => '',
                            'XmlDocumentKey'             => '',
                            'ErrorMessage'               => '',
                            'estado'                     => 'FALLIDO',
                            'estadoInformacionAdicional' => (array_key_exists('exceptions', $rptaConsultaEstadoDocumento) && $rptaConsultaEstadoDocumento['exceptions'] != '') ? json_encode($rptaConsultaEstadoDocumento['exceptions']) : null,
                            'cdoFechaValidacionDian'     => ''
                        ],
                        'xmlRespuestaDian'  => ''
                    ];
                }

                $nombreArchivoDisco = null;
                if(!empty($procesados[$cdo_id]['xmlRespuestaDian'])) {
                    $nombreArchivoDisco = $this->guardarArchivoEnDisco(
                        $documentoIdentificacion,
                        $documentosConsultar[$cdo_id]['documento'],
                        $proceso,
                        'getStatusDian',
                        'xml',
                        base64_encode($procesados[$cdo_id]['xmlRespuestaDian'])
                    );
                }

                self::actualizaEstadoDocumento(
                    $cdo_id,
                    $estadosConsultar[$cdo_id]['est_id'],
                    $procesados[$cdo_id]['respuestaProcesada']['estado'],
                    ($procesados[$cdo_id]['respuestaProcesada']['estado'] === 'EXITOSO') ? $procesados[$cdo_id]['respuestaProcesada']['StatusDescription'] : $procesados[$cdo_id]['respuestaProcesada']['StatusDescription'] . ' // ' . $procesados[$cdo_id]['respuestaProcesada']['ErrorMessage'],
                    (!empty($procesados[$cdo_id]['respuestaProcesada'])) ? $procesados[$cdo_id]['respuestaProcesada'] : null,
                    date('Y-m-d H:i:s'),
                    number_format((microtime(true) - $estadosConsultar[$cdo_id]['inicio']), 3, '.', ''),
                    (!empty($procesados[$cdo_id]['respuestaProcesada']['estadoInformacionAdicional'])) ? $procesados[$cdo_id]['respuestaProcesada']['estadoInformacionAdicional'] :  null,
                    'FINALIZADO',
                    $procesados[$cdo_id]['respuestaProcesada']['cdoFechaValidacionDian'],
                    'GetStatus',
                    $nombreArchivoDisco,
                    $radian
                );

                // Si el OFE tiene parametrizado el evento ACUSERECIBO automático se agenda el estado automáticamente
                if($procesados[$cdo_id]['respuestaProcesada']['estado'] === 'EXITOSO') {
                    $eventosContratados = (!$radian) ? $documento['documento']->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_eventos_contratados_titulo_valor : [];
                    
                    $generacionAutomatica = false;
                    if (is_array($eventosContratados) && !empty($eventosContratados)) {
                        foreach ($eventosContratados as $evento) {
                            if ($evento['evento'] == 'ACUSERECIBO') {
                                $generacionAutomatica = (array_key_exists('generacion_automatica', $evento) && $evento['generacion_automatica'] == "SI") ? true : false;
                                break;
                            }
                        }
                    }

                    if ($generacionAutomatica && $documento['cdo_clasificacion'] == 'FC' && $documento['cdo_origen'] != 'NO-ELECTRONICO') {
                        $parametros['ofeId']              = $documento['documento']->getConfiguracionObligadoFacturarElectronicamente->ofe_id;
                        $parametros['cdoIds']             = $cdo_id;
                        $parametros['proceso_automatico'] = true;
                        self::peticionMicroservicio('MAIN', 'POST', '/api/recepcion/documentos/agendar-acuse-recibo', $parametros, 'form_params');
                    }
                } else {
                    // Verifica la cantidad de estados GETSTATUS fallidos del documento
                    $gsFallidos = $modeloDocumento::select(['est_id'])
                        ->where('cdo_id', $cdo_id)
                        ->when(!$radian, function($query){
                            $query->where('est_estado', 'GETSTATUS');
                        }, function($query) {
                            $query->where('est_estado', 'RADGETSTATUS');
                        })
                        ->where('est_resultado', 'FALLIDO')->count();

                    if($gsFallidos < 3) {
                        // Verifica si el xmlRespuestaDian no está vacio y si NO es un XML
                        $esXml = true;
                        if($procesados[$cdo_id]['xmlRespuestaDian'] == '' || $procesados[$cdo_id]['xmlRespuestaDian'] == null) {
                            $esXml = false;
                        } else {
                            $oXML        = new \SimpleXMLElement($procesados[$cdo_id]['xmlRespuestaDian']);
                            $vNameSpaces = $oXML->getNamespaces(true);
                            if(!is_array($vNameSpaces) || empty($vNameSpaces)) {
                                $esXml = false;
                            }
                        }

                        // Si no es un XML válido se agenda GETSTATUSR
                        if(!$esXml) {
                            $ageProceso = (!$radian) ? 'RGETSTATUS' : 'RADGETSTATUS';
                            $agendamientoGSR = DoTrait::crearNuevoAgendamiento($ageProceso, $user->usu_id, $agendamiento->bdd_id, 1, $agendamiento->age_prioridad);
                            self::creaNuevoEstadoDocumentoRecepcion(
                                $cdo_id,
                                'GETSTATUS',
                                null,
                                null,
                                null,
                                null,
                                $agendamientoGSR->age_id,
                                $user->usu_id,
                                [
                                    'agendamiento' => $ageProceso
                                ],
                                null
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                $arrExcepciones = [];
                $arrExcepciones[] = [
                    'documento'           => (array_key_exists('cdo_clasificacion', $documentosConsultar[$cdo_id])) ? $documentosConsultar[$cdo_id]['cdo_clasificacion'] : '',
                    'consecutivo'         => (array_key_exists('cdo_consecutivo', $documentosConsultar[$cdo_id])) ? $documentosConsultar[$cdo_id]['cdo_consecutivo'] : '',
                    'prefijo'             => (array_key_exists('rfa_prefijo', $documentosConsultar[$cdo_id])) ? $documentosConsultar[$cdo_id]['rfa_prefijo'] : '',
                    'errors'              => [$e->getMessage()],
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s'),
                    'archivo'             => '',
                    'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                ];

                self::actualizaEstadoDocumento(
                    $cdo_id,
                    $estadosConsultar[$cdo_id]['est_id'],
                    'FALLIDO',
                    $e->getMessage(),
                    null,
                    date('Y-m-d H:i:s'),
                    number_format((microtime(true) - $estadosConsultar[$cdo_id]['inicio']), 3, '.', ''),
                    $arrExcepciones,
                    'FINALIZADO',
                    null,
                    null,
                    null,
                    $radian
                );
            }
        }
    }

    /**
     * Realiza una consulta única a los web services de la DIAN.
     *
     * @param TransmitirDocumentosDian $classTransmitir Clase que contiene métodos relacionados con la transmisión/consulta de información en la DIAN
     * @param FirmarEnviarSoap $firmarSoap Clase que permite firmar electrónicamente un SOAP y transmitirlo a la DIAN
     * @param string $metodoConsulta Metodo de la DIAN a través del cual se realiza la consulta
     * @param string $urlAmbienteDestino Ambiente de destino
     * @param string $datoConsultar Puede ser el CUFE del documento electrónico o la concatenación mediante comas de la identificación del OFE, NIT SPT e Identificador del Software, dependiendo del método de consulta en el servicio web de la DIAN
     * @param string $bddNombre Nombre de la base de datos del OFE
     * @param string $ofeArchivoCertificado Path al certificado firmante del OFE
     * @param string $ofePasswordCertificado Password del certificado firmante del OFE
     * @return array Array conteniendo información sobre errores generados en el proceso y la respuesta de la DIAN
     */
    public function requestDianWS(TransmitirDocumentosDian $classTransmitir, FirmarEnviarSoap $firmarSoap, string $metodoConsulta, string $urlAmbienteDestino, string $datoConsultar, string $bddNombre, string $ofeArchivoCertificado, string $ofePasswordCertificado) {
        if($metodoConsulta != 'GetNumberingRange')
            $soapConsultar = $classTransmitir->inicializarSoapDian($metodoConsulta, $urlAmbienteDestino, $datoConsultar);
        else
            $soapConsultar = $classTransmitir->inicializarSoapResoluciones($metodoConsulta, $urlAmbienteDestino, $datoConsultar);

        return $firmarSoap->firmarSoapXML(
            $soapConsultar,
            config('variables_sistema.PATH_CERTIFICADOS') . '/' . $bddNombre . '/' . $ofeArchivoCertificado,
            $ofePasswordCertificado,
            $urlAmbienteDestino
        );
    }

    /**
     * Obtiene el nombre de la base de datos del proceso teniendo en cuenta el usuario autenticado y la configuración del OFE
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE
     * @return string Nombre de la base de datos relacionada con el proceso
     */
    public function getNombreBaseDatos(ConfiguracionObligadoFacturarElectronicamente $ofe): string {
        $user   = auth()->user();
        $bdUser = $user->getBaseDatos->bdd_nombre;
        if(!empty($user->bdd_id_rg))
            $bdUser = $user->getBaseDatosRg->bdd_nombre;

        if(!empty($ofe->bdd_id_rg))
            $bddNombre = $ofe->getBaseDatosRg->bdd_nombre;
        else
            $bddNombre = $bdUser;

        return str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);
    }

    /**
     * Procesa una petición originada en el microservicio DI - proceso RDI para consultar un documento en la DIAN mediante el CUFE o CUDE
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function procesarPeticionProcesoRdi(Request $request): JsonResponse {
        try {
            $validacion = Validator::make($request->all(), [
                    'ofe_id'               => 'required|numeric',
                    'cufe'                 => 'required|string',
                    'metodo_consulta_dian' => 'required|string'
                ]);

            if($validacion->fails())
                return response()->json([
                    'message' => 'Errores de validación en los parámetros de la petición',
                    'errors'  => $validacion->errors()->all()
                ], ResponseHttp::HTTP_BAD_REQUEST);

            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg', 'sft_id', 'ofe_archivo_certificado', 'ofe_password_certificado'])
                ->with([
                    'getBaseDatosRg:bdd_id,bdd_nombre',
                    'getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id'
                ])
                ->where('ofe_id', $request->ofe_id)
                ->where('ofe_recepcion', 'SI')
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$ofe)
                return response()->json([
                    'message' => 'Errores de validación en los parámetros de la petición',
                    'errors'  => ['El OFE con id [' . $request->ofe_id . '] no existe, no tiene activo el servicio de recepción o se encuentra inactivo']
                ], ResponseHttp::HTTP_BAD_REQUEST);

            $classMetodosBase = new MetodosBase();
            $classTransmitir  = new TransmitirDocumentosDian();
            $classFirmarSoap  = new FirmarEnviarSoap();

            // Array de objetos de paramétricas
            $parametricas = [
                'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_metodo', 'add_descripcion', 'add_url', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                    ->where('estado', 'ACTIVO')
                    ->get()->toArray()
            ];

            $urlAmbienteDestino = $classMetodosBase->obtieneDatoParametrico(
                $parametricas['ambienteDestino'],
                'add_id',
                $ofe->getConfiguracionSoftwareProveedorTecnologico->add_id,
                'add_url'
            );

            $obtenerXmlDocumento = $this->requestDianWS(
                $classTransmitir,
                $classFirmarSoap,
                $request->metodo_consulta_dian,
                $urlAmbienteDestino,
                $request->cufe,
                $this->getNombreBaseDatos($ofe),
                $ofe->ofe_archivo_certificado,
                $ofe->ofe_password_certificado
            );

            if(array_key_exists('error', $obtenerXmlDocumento) && !empty($obtenerXmlDocumento['error']))
                throw new \Exception('Error al consultar el CUFE en la DIAN: ' . $obtenerXmlDocumento['error']);

            $xmlString = $this->obtenerXmlBytesBase64($obtenerXmlDocumento['rptaDian'], $request->metodo_consulta_dian);
            if (!$xmlString['error'] && !empty($xmlString['string']))
                return response()->json([
                    'data' => [
                        'xml' => $xmlString['string']
                    ]
                ], ResponseHttp::HTTP_OK);
            elseif($xmlString['error'] && !empty($xmlString['string']))
                return response()->json([
                    'message' => 'Error al procesar la petición',
                    'errors'  => [(string) $xmlString['string']]
                ], ResponseHttp::HTTP_NOT_FOUND);
            else
                return response()->json([
                    'message' => 'Error al procesar la petición',
                    'errors'  => ['No fue posible consultar el documento en la DIAN']
                ], ResponseHttp::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al procesar la petición',
                'errors'  => ['DO - GetStatus (Línea ' . $e->getLine() . '): ' . $e->getMessage()]
            ], ResponseHttp::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}