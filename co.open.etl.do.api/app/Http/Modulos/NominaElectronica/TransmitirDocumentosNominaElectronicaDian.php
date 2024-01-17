<?php
namespace App\Http\Modulos\NominaElectronica;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\TransmitirDocumentosDian\FirmarEnviarSoap;
use App\Http\Modulos\NominaElectronica\DsnEstadosDocumentosDaop\DsnEstadoDocumentoDaop;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaop;

class TransmitirDocumentosNominaElectronicaDian extends Controller {
    use DoTrait;

    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Procesa los documentos de nómina electrónica que se transmitirán a al DIAN.
     *
     * @param array $documentos Array que contiene los IDs de los documentos de nómina electrónica que se deben transmitir a la DIAN
     * @param array $estados Array que contiene información de los estados de los documentos de nómina electrónica que se deben transmitir a la DIAN
     * @param User $user Usuario relacionado con el procesamiento
     * @return array Array que contiene información sobre el procesamiento de los documentos, ya sea por fallas/errores o por transmisiones efectivas
     */
    public function transmitirDocumentosNominaElectronicaDian(array $documentos, array $estados, User $user) {
        // Verifica que el parámetro recibido sea un array
        if(!is_array($documentos)) {
            return [
                'success' => false,
                'error'   => 'El parámetro recibido para realizar la transmisión de documentos de nómina electrónica a la Dian debe ser del tipo Array y por cada posición del array contener el ID del documento a procesar',
                'codigo'  => 409
            ];
        }

        // Array de objetos de paramétricas
        $parametricas = [
            'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_metodo', 'add_descripcion', 'add_url', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()->toArray()
        ];

        $bdUser = $user->getBaseDatos->bdd_nombre;
        if(!empty($user->bdd_id_rg)) {
            $bdUser = $user->getBaseDatosRg->bdd_nombre;
        }

        $procesados = [];
        $metodosBase = new MetodosBase();
        $firmarSoap  = new FirmarEnviarSoap();
        foreach($documentos as $cdn_id) {
            // Marca el inicio de procesamiento del documento en el estado correspondiente
            DsnEstadoDocumentoDaop::find($estados[$cdn_id]['est_id'])
                ->update([
                    'est_ejecucion'      => 'ENPROCESO',
                    'est_inicio_proceso' => date('Y-m-d H:i:s')
                ]);

            // Actualiza el microtime de inicio de procesamiento del documento
            $estados[$cdn_id]['inicio'] = microtime(true);

            // Obtiene el XML firmado
            $xmlFirmado = DsnEstadoDocumentoDaop::select(['est_id', 'cdn_id', 'est_informacion_adicional'])
                ->where('cdn_id', $cdn_id)
                ->where('est_estado', 'XML')
                ->where('est_resultado', 'EXITOSO')
                ->orderBy('est_id', 'desc')
                ->with([
                    'getCabeceraNomina:cdn_id,emp_id,cdn_prefijo,cdn_consecutivo,cdn_clasificacion,cdn_cune,cdn_nombre_archivos,fecha_creacion',
                    'getCabeceraNomina.getEmpleador:emp_id,sft_id,emp_identificacion,emp_archivo_certificado,emp_password_certificado,bdd_id_rg',
                    'getCabeceraNomina.getEmpleador.getProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getCabeceraNomina.getEmpleador.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->first();

            $urlAmbienteDestino    = $metodosBase->obtieneDatoParametrico($parametricas['ambienteDestino'], 'add_id', $xmlFirmado->getCabeceraNomina->getEmpleador->getProveedorTecnologico->add_id, 'add_url');
            $metodoAmbienteDestino = $metodosBase->obtieneDatoParametrico($parametricas['ambienteDestino'], 'add_id', $xmlFirmado->getCabeceraNomina->getEmpleador->getProveedorTecnologico->add_id, 'add_metodo');

            // Los nombres de los archivos xml y zip fueron generados en el microservicio UBL
            $continuar = true;
            if(empty($xmlFirmado->getCabeceraNomina->cdn_nombre_archivos)) {
                $procesados[$cdn_id] = [
                    'respuestaProcesada' => [
                        'IsValid'                    => 'false',
                        'StatusCode'                 => '',
                        'StatusDescription'          => 'No se generaron los nombres de archivos correctamente',
                        'StatusMessage'              => '',
                        'XmlDocumentKey'             => '',
                        'ErrorMessage'               => '',
                        'estado'                     => 'FALLIDO',
                        'estadoInformacionAdicional' => '',
                        'cdnFechaValidacionDian'     => ''
                    ],
                    'xmlRespuestaDian'  => ''
                ];
                $continuar = false;
            }

            if($continuar) {
                $nombreArchivos = json_decode($xmlFirmado->getCabeceraNomina->cdn_nombre_archivos);
                $archivoXml     = $nombreArchivos->xml;
                $archivoZip     = $nombreArchivos->zip;
                $rutaArchivoZip = storage_path().'/'.$archivoZip;

                if(!empty($xmlFirmado->getCabeceraNomina->getEmpleador->bdd_id_rg)) {
                    $bdFirma = $xmlFirmado->getCabeceraNomina->getEmpleador->getBaseDatosRg->bdd_nombre;
                } else {
                    $bdFirma = $bdUser;
                }
                
                $bdFirma     = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdFirma);
                $certificado = config('variables_sistema.PATH_CERTIFICADOS') . '/' . $bdFirma . '/' .
                    $xmlFirmado->getCabeceraNomina->getEmpleador->emp_archivo_certificado;

                $oZip = new \ZipArchive();
                $oZip->open($rutaArchivoZip, \ZipArchive::OVERWRITE | \ZipArchive::CREATE);

                $informacionAdicional = ($xmlFirmado->est_informacion_adicional != '') ? json_decode($xmlFirmado->est_informacion_adicional, true) : [];
                $xml = $this->obtenerArchivoDeDisco(
                    'nomina',
                    $xmlFirmado->getCabeceraNomina->getEmpleador->emp_identificacion,
                    $xmlFirmado->getCabeceraNomina,
                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                );

                $oZip->addFromString($archivoXml, $xml);
                $oZip->close();

                $zipContent  = base64_encode(file_get_contents($rutaArchivoZip));
                @unlink($rutaArchivoZip);

                // Transmisión a la DIAN
                $transmitir = $this->transmitirXml(
                    $firmarSoap,
                    $certificado,
                    $xmlFirmado->getCabeceraNomina->getEmpleador->emp_password_certificado,
                    $archivoZip,
                    $zipContent,
                    $urlAmbienteDestino,
                    $metodoAmbienteDestino,
                    $xmlFirmado->getCabeceraNomina->getEmpleador->getProveedorTecnologico->sft_testsetid
                );
                
                if(array_key_exists('error', $transmitir) && empty($transmitir['error'])) {
                    // La respuesta de la DIAN se procesa para poder definir si la transmisión fue exitosa o fallida
                    $this->procesarRespuestaDian(
                        $firmarSoap,
                        $transmitir['rptaDian'],
                        $metodoAmbienteDestino,
                        $urlAmbienteDestino,
                        $xmlFirmado->getCabeceraNomina->cdn_cune,
                        $certificado,
                        $xmlFirmado->getCabeceraNomina->getEmpleador->emp_password_certificado,
                        $procesados,
                        $cdn_id,
                        null,
                        false
                    );

                    if(array_key_exists('reconsultarDocumento', $procesados[$cdn_id]) && $procesados[$cdn_id]['reconsultarDocumento']) {
                        // El documento debe ser consultado en la DIAN para establecer si existe o no
                        $consultaDian = $this->ConsultarDocumentosNominaElectronicaDian(
                            [$cdn_id],
                            [
                                $cdn_id => $estados[$cdn_id]
                            ],
                            $user,
                            false,
                            true
                        );

                        $procesados[$cdn_id] = $consultaDian[$cdn_id];
                    }
                } else {
                    $procesados[$cdn_id] = [
                        'respuestaProcesada' => [
                            'IsValid'                    => 'false',
                            'StatusCode'                 => '',
                            'StatusDescription'          => $transmitir['error'],
                            'StatusMessage'              => '',
                            'XmlDocumentKey'             => '',
                            'ErrorMessage'               => '',
                            'estado'                     => 'FALLIDO',
                            'estadoInformacionAdicional' => (array_key_exists('exceptions', $transmitir) && $transmitir['exceptions'] != '') ? json_encode($transmitir['exceptions']) : null,
                            'cdnFechaValidacionDian'     => ''
                        ],
                        'xmlRespuestaDian'  => ''
                    ];
                }
            }

            $nombreArchivoDisco = null;
            if(!empty($procesados[$cdn_id]['xmlRespuestaDian'])) {
                $nombreArchivoDisco = $this->guardarArchivoEnDisco(
                    $xmlFirmado->getCabeceraNomina->getEmpleador->emp_identificacion,
                    $xmlFirmado->getCabeceraNomina,
                    'nomina',
                    'xmlDian',
                    'xml',
                    base64_encode($procesados[$cdn_id]['xmlRespuestaDian'])
                );
            }

            // Actualiza el estado del documento que se encuentra en procesamiento
            $this->actualizarEstadoDocumentoTransmitido(
                $cdn_id,
                $estados[$cdn_id]['est_id'],
                $procesados[$cdn_id]['respuestaProcesada']['estado'],
                ($procesados[$cdn_id]['respuestaProcesada']['estado'] === 'EXITOSO') ? $procesados[$cdn_id]['respuestaProcesada']['StatusDescription'] : $procesados[$cdn_id]['respuestaProcesada']['StatusDescription'] . ' // ' . $procesados[$cdn_id]['respuestaProcesada']['ErrorMessage'],
                (!empty($procesados[$cdn_id]['respuestaProcesada'])) ? $procesados[$cdn_id]['respuestaProcesada'] : null,
                (!empty($procesados[$cdn_id]['respuestaProcesada']['estadoInformacionAdicional'])) ? $procesados[$cdn_id]['respuestaProcesada']['estadoInformacionAdicional'] : null,
                date('Y-m-d H:i:s'),
                number_format((microtime(true) - $estados[$cdn_id]['inicio']), 3, '.', ''),
                'FINALIZADO',
                $procesados[$cdn_id]['respuestaProcesada']['cdnFechaValidacionDian'],
                $nombreArchivoDisco
            );
        }

        return $procesados;
    }

    /**
     * Envia un documento electrónico firmado al Web Service de la DIAN.
     *
     * @param  FirmarEnviarSoap  $firmarSoap  Instancia de la clase que permite el firmado electrónico del SOAP
     * @param  string  $certificado  Nombre del archivo del certificado firmante
     * @param  string  $password  Clave en base64 del certificado firmante
     * @param  string  $archivoZip  Nombre del archivo ZIP
     * @param  string  $zipContent  Contenido del archivo ZIP
     * @param  string  $urlAmbienteDestino  URL del ambiente destino del documento
     * @param  string  $metodoAmbienteDestino  Método del ambiente destino del documento
     * @param  string  $testSetId  ID del set de pruebas para habilitacion
     * @return  array  $transmitir  Array con índices de error y respuesta Dian
     */
    public function transmitirXml(FirmarEnviarSoap $firmarSoap, string $certificado, string $password, string $archivoZip, string $zipContent, string $urlAmbienteDestino, string $metodoAmbienteDestino, string $testSetId = null) {
        try {
            $xmlRequest  = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">';
                $xmlRequest .= '<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">';
                    $xmlRequest .= '<wsa:Action>http://wcf.dian.colombia/IWcfDianCustomerServices/' . $metodoAmbienteDestino . '</wsa:Action>';
                    $xmlRequest .= '<wsa:To xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">https://gtpa-webservices-input-test.azurewebsites.net/WcfDianCustomerServices.svc?wsdl</wsa:To>';
                $xmlRequest .= '</soap:Header>';
                $xmlRequest .= '<soap:Body>';
                    $xmlRequest .= '<wcf:' . $metodoAmbienteDestino . '>';
                        if($metodoAmbienteDestino != 'SendEventUpdateStatus') {
                            $xmlRequest .= '<wcf:fileName>'.$archivoZip.'</wcf:fileName>';
                        }
                        $xmlRequest .= '<wcf:contentFile>'.$zipContent.'</wcf:contentFile>';
                        if($metodoAmbienteDestino == 'SendTestSetAsync') {
                            $xmlRequest .= '<wcf:testSetId>' . $testSetId . '</wcf:testSetId>';
                        }
                    $xmlRequest .= '</wcf:' . $metodoAmbienteDestino . '>';
                $xmlRequest .= '</soap:Body>';
            $xmlRequest .= '</soap:Envelope>';

            $transmitir = $firmarSoap->firmarSoapXML($xmlRequest, $certificado, $password, $urlAmbienteDestino);

            return $transmitir;
        } catch (\Exception $e) {
            return [
                'error'      => 'Error en la transmisión a la DIAN',
                'exceptions' => ['Transmisión a la DIAN: ' . $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()],
                'rptaDian'   => ''
            ];
        }
    }

    /**
     * Inicializa el Soap con el cual se accederá a un método en el webservice de la DIAN.
     *
     * @param string $metodo Método a consumir en el webservice de la DIAN
     * @param string $urlAmbienteDestino URL Ambiente destino
     * @param string $cufe Cufe del documento a consutlar
     * @return string $xmlRequest Soap incializado
     */
    public function inicializarSoapDian(string $metodo, string $urlAmbienteDestino, string $cufe) {
        $xmlRequest  = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">';
            $xmlRequest .= '<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">';
                $xmlRequest .= '<wsa:Action>http://wcf.dian.colombia/IWcfDianCustomerServices/' . $metodo . '</wsa:Action>';
                $xmlRequest .= '<wsa:To xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">' . $urlAmbienteDestino . '</wsa:To>';
            $xmlRequest .= '</soap:Header>';
            $xmlRequest .= '<soap:Body>';
                $xmlRequest .= '<wcf:' . $metodo . '>';
                    $xmlRequest .= '<wcf:trackId>' . $cufe . '</wcf:trackId>';
                $xmlRequest .= '</wcf:' . $metodo . '>';
            $xmlRequest .= '</soap:Body>';
        $xmlRequest .= '</soap:Envelope>';
        
        return $xmlRequest;
    }

    /**
     * Procesa la respuesta recibida desde el WS de la DIAN.
     *
     * @param  FirmarEnviarSoap  $firmarSoap  Instancia de la clase que permite el firmado electrónico del SOAP, se requiere en el proceso de habilitacion para consultar el estado del documento en la DIAN
     * @param string $rptaDian Respuesta XML recibida desde el WS de la DIAN
     * @param string $metodoAmbienteDestino Método del amnbiente destino, sive para concatenar string de los nodos que contienen información
     * @param string $urlAmbienteDestino  URL del ambiente destino del documento
     * @param string $cuneDocumento Cufe del documento en procesamiento, se requiere en el proceso de habilitacion para consultar el estado del documento en la DIAN y se requiere para verificarlo frente a la respuesta de la DIAN
     * @param string $certificado Certificado con el que se firmará el SOAP, se requiere en el proceso de habilitacion para consultar el estado del documento en la DIAN
     * @param string $password Password del certificado con el que se firmará el SOAP, se requiere en el proceso de habilitacion para consultar el estado del documento en la DIAN
     * @param array $procesados Array de documentos procesados
     * @param int $cdn_id ID del documento que esta siendo procesado
     * @param array $estadoInformacionAdicional Informacion adicional del estado del documento, puede ser null y se recibe cuando se llama nuevamente este método debido a que el método de ambiente destino es SendTestSetAsync
     * @param bool $esReconsulta Indica si el llamado al método está relacionado o no con una nueva consulta del documento
     * @return void
     */
    public function procesarRespuestaDian(FirmarEnviarSoap $firmarSoap, string $rptaDian, string $metodoAmbienteDestino, string $urlAmbienteDestino = null, string $cuneDocumento = null, string $certificado = null, string $password = null, array &$procesados, int $cdn_id, array $estadoInformacionAdicional = null, bool $esReconsulta = false) {
        try {
            $respuesta   = [];
            $oXML        = new \SimpleXMLElement($rptaDian);
            $vNameSpaces = $oXML->getNamespaces(true);

            $nodoResponse = $metodoAmbienteDestino . 'Response';
            $nodoResult   = $metodoAmbienteDestino . 'Result';

            $oBody = $oXML->children($vNameSpaces['s'])
                ->Body
                ->children($vNameSpaces[''])
                ->$nodoResponse
                ->children($vNameSpaces[''])
                ->$nodoResult
                ->children($vNameSpaces['b']);

            // En la respuesta emitida por el proceso de habilitacion Metodo SendTestSetAsync, 
            // se incluye le nivel DianResponse
            if (isset($oBody->DianResponse)) {
                $oBody = $oBody->DianResponse;
            }

            // Método de habilitacion en la DIAN
            if($metodoAmbienteDestino == 'SendTestSetAsync') {
                // En ambiente habilitacion se consulta por zipkey retornado
                $sendTestSetAsyncCune = (string)$oBody->ZipKey;

                $estadoInformacionAdicional = [
                    'SendTestSetAsyncResult' => [
                        'xmlRespuesta'     => base64_encode($rptaDian),
                        'zipKey'           => $sendTestSetAsyncCune,
                        'ProcessedMessage' => ''
                    ]
                ];

                if (isset($oBody->ErrorMessageList) && is_object($oBody->ErrorMessageList) && array_key_exists('c', $vNameSpaces)) {
                    $estadoInformacionAdicional['SendTestSetAsyncResult']['ProcessedMessage'] = (string)$oBody->ErrorMessageList->children($vNameSpaces['c'])->XmlParamsResponseTrackId->children($vNameSpaces['c'])->ProcessedMessage;
                }

                if($sendTestSetAsyncCune != '') {
                    // Se hace una pausa de 15sg en el procesamiento para poder consultar el zipKey si se obtuvo dentro de la respuesta incial
                    sleep(15);

                    // Arma el Soap de para la consulta del documento
                    $iniSoapConsultaDian         = $this->inicializarSoapDian('GetStatusZip', $urlAmbienteDestino, $sendTestSetAsyncCune);
                    $rptaConsultaEstadoDocumento = $firmarSoap->firmarSoapXML($iniSoapConsultaDian, $certificado, $password, $urlAmbienteDestino);

                    if(array_key_exists('error', $rptaConsultaEstadoDocumento) && empty($rptaConsultaEstadoDocumento['error'])) {
                        // La respuesta de la DIAN se procesa para poder definir si la transmisión fue exitosa o fallida
                        $this->procesarRespuestaDian(
                            $firmarSoap,
                            $rptaConsultaEstadoDocumento['rptaDian'],
                            'GetStatusZip',
                            null,
                            null,
                            null,
                            null,
                            $procesados,
                            $cdn_id,
                            $estadoInformacionAdicional,
                            false
                        );
                    } else {
                        $procesados[$cdn_id] = [
                            'respuestaProcesada' => [
                                'IsValid'                    => 'false',
                                'StatusCode'                 => '',
                                'StatusDescription'          => $rptaConsultaEstadoDocumento['error'],
                                'StatusMessage'              => '',
                                'XmlDocumentKey'             => '',
                                'ErrorMessage'               => '',
                                'estado'                     => 'FALLIDO',
                                'estadoInformacionAdicional' => (array_key_exists('exceptions', $rptaConsultaEstadoDocumento) && $rptaConsultaEstadoDocumento['exceptions'] != '') ? json_encode($rptaConsultaEstadoDocumento['exceptions']) : null,
                                'cdnFechaValidacionDian'     => ''
                            ],
                            'xmlRespuestaDian'  => ''
                        ];
                    }
                } else {
                    $respuesta['IsValid']                    = false;
                    $respuesta['StatusCode']                 = '';
                    $respuesta['StatusDescription']          = (string)$oBody->ErrorMessageList->children($vNameSpaces['c'])->XmlParamsResponseTrackId->children($vNameSpaces['c'])->ProcessedMessage;
                    $respuesta['StatusMessage']              = '';
                    $respuesta['XmlDocumentKey']             = '';
                    $respuesta['estado']                     = 'FALLIDO';
                    $respuesta['cdnFechaValidacionDian']     = '';
                    $respuesta['estadoInformacionAdicional'] = json_encode($estadoInformacionAdicional);
                    $respuesta['ErrorMessage']               = '';

                    $procesados[$cdn_id] = [
                        'respuestaProcesada' => $respuesta,
                        'xmlRespuestaDian'   => $rptaDian
                    ];
                }
            } else {
                if(isset($oBody->XmlBase64Bytes) && $oBody->XmlBase64Bytes != '') {
                    $oDomtree                   = new \DOMDocument();
                    $oDomtree->loadXML(base64_decode($oBody->XmlBase64Bytes));

                    $responseCode = $oDomtree->getElementsByTagName('ResponseCode')->item(0)->nodeValue;
                    $description  = !empty($oDomtree->getElementsByTagName('Description')->item(0)->nodeValue) ? $oDomtree->getElementsByTagName('Description')->item(0)->nodeValue : $oDomtree->getElementsByTagName('Description')->item(1)->nodeValue;
                    $issueDate    = $oDomtree->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                    $issueTime    = $oDomtree->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                    $estado       = ($responseCode == '01' || $responseCode == '02') ? 'EXITOSO' : 'FALLIDO';

                    $estadoInformacionAdicional['ResponseCode'] = '<cac:DocumentResponse><cac:Response><cbc:ResponseCode>' . $responseCode . '</cbc:ResponseCode><cbc:Description>' . $description . '</cbc:Description></cac:Response></cac:DocumentResponse>';
                    $estadoInformacionAdicional['IssueDate']    = '<cbc:IssueDate>' . $issueDate . '</cbc:IssueDate>';
                    $estadoInformacionAdicional['IssueTime']    = '<cbc:IssueTime>' . $issueTime . '</cbc:IssueTime>';
                    $cdnFechaValidacionDian                     = $issueDate . ' ' . $issueTime;

                    if(strstr($rptaDian, 'Documento procesado anteriormente')) {
                        $estado         = 'EXITOSO';
                        $mensajeRegla90 = true;
                    }
                } elseif(isset($oBody->XmlBase64Bytes) && $oBody->XmlBase64Bytes == '') {
                    // Se marca como exitoso, si IsValid es igual a true
                    // o si siendo false, se retorna en StatusCode sea 99 y
                    // el mensaje de error es la Regla: 90, Rechazo: Documento procesado anteriormente.
                    // posteriormente se valida que sea el mismo cufe
                    $mensajeRegla90 = false;
                    if (
                        isset($oBody->ErrorMessage) &&
                        is_object($oBody->ErrorMessage) &&
                        array_key_exists('c', $vNameSpaces)
                    ) {
                        $oErrorMessage = $oBody->ErrorMessage->children($vNameSpaces['c']);
                        foreach ($oErrorMessage as $error) {
                            if (substr_count($error,"Regla: 90, Rechazo: Documento procesado anteriormente.") > 0) {
                                $mensajeRegla90 = true;
                            }
                        }
                    }

                    if(
                        (isset($oBody->IsValid) && (string)$oBody->IsValid == 'true') || 
                        (
                            isset($oBody->IsValid) && (string)$oBody->IsValid == 'false' && 
                            isset($oBody->StatusCode) && (string)$oBody->StatusCode == '99' && 
                            $mensajeRegla90 == true
                        )
                    ) {
                        $estado = 'EXITOSO';
                    } else {
                        $estado = 'FALLIDO';
                    }
                    $estadoInformacionAdicional = ($estadoInformacionAdicional != null) ? $estadoInformacionAdicional : '';
                    $cdnFechaValidacionDian     = '';
                } else {
                    $estado = 'FALLIDO';
                    $estadoInformacionAdicional = ($estadoInformacionAdicional != null) ? $estadoInformacionAdicional : '';
                    $cdnFechaValidacionDian     = '';
                }

                $respuesta['IsValid']                    = (isset($oBody->IsValid)) ? (string)$oBody->IsValid : '';
                $respuesta['StatusCode']                 = (isset($oBody->StatusCode)) ? (string)$oBody->StatusCode : '';
                $respuesta['StatusDescription']          = (isset($oBody->StatusDescription)) ? (string)$oBody->StatusDescription : '';
                $respuesta['StatusMessage']              = (isset($oBody->StatusMessage)) ? (string)$oBody->StatusMessage : '';
                $respuesta['XmlDocumentKey']             = (isset($oBody->XmlDocumentKey)) ? (string)$oBody->XmlDocumentKey : '';
                $respuesta['estado']                     = $estado;
                $respuesta['estadoInformacionAdicional'] = ($metodoAmbienteDestino == 'GetStatusZip') ? $estadoInformacionAdicional : (($estadoInformacionAdicional != '') ? json_encode($estadoInformacionAdicional) : '');
                $respuesta['cdnFechaValidacionDian']     = $cdnFechaValidacionDian;
                $respuesta['ErrorMessage']               = '';

                // Se debe verificar el CUNE retornado por la DIAN porque se han encontrado situaciones en donde la DIAN responde para otro CUNE
                if(!empty($cuneDocumento) && $cuneDocumento != $respuesta['XmlDocumentKey'] && $respuesta['StatusCode'] != '500') {
                    $mensajeRegla90             = false; // Se cambia a false, indicado que es documento fallido para que no realice la nueva consulta
                    $respuesta['estado']        ='FALLIDO';
                    $respuesta['ErrorMessage'] .= 'XmlDocumentKey retornado por la DIAN [' . $respuesta['XmlDocumentKey'] . '] no corresponde con el CUNE del documento de nómina electrónica electrónico [' . $cuneDocumento . '].';
                }

                if (
                    isset($oBody->ErrorMessage) &&
                    is_object($oBody->ErrorMessage) &&
                    array_key_exists('c', $vNameSpaces)
                ) {
                    $oErrorMessage = $oBody->ErrorMessage->children($vNameSpaces['c']);

                    foreach ($oErrorMessage as $error) {
                        $respuesta['ErrorMessage'] .= $error."~";
                    }
                    $respuesta['ErrorMessage'] = substr($respuesta['ErrorMessage'], 0, -1);

                    if($respuesta['estado'] == 'EXITOSO') {
                        if ($estadoInformacionAdicional != null) {
                            $estadoInformacionAdicional['conNotificacion'] = true;
                        } else {
                            $estadoInformacionAdicional = ['conNotificacion' => true];
                        }                        
                        $respuesta['estadoInformacionAdicional'] = ($metodoAmbienteDestino == 'GetStatusZip') ? $estadoInformacionAdicional : json_encode($estadoInformacionAdicional);
                    }
                } else {
                    if($respuesta['estado'] == 'EXITOSO') {
                        if ($estadoInformacionAdicional != null) {
                            $estadoInformacionAdicional['conNotificacion'] = false;
                        } else {
                            $estadoInformacionAdicional = ['conNotificacion' => true];
                        }
                        $respuesta['estadoInformacionAdicional'] = ($metodoAmbienteDestino == 'GetStatusZip') ? $estadoInformacionAdicional : json_encode($estadoInformacionAdicional);
                    }
                }
                
                $procesados[$cdn_id] = [
                    'respuestaProcesada'   => $respuesta,
                    'xmlRespuestaDian'     => $rptaDian,
                    'reconsultarDocumento' => isset($mensajeRegla90) && !$esReconsulta ? $mensajeRegla90 : false // $mensajeRegla90 solo se genera y es true cuando la DIAN retornó una respuesta con error a la transmisión, en ese caso se debe volver a consultar el documento para verificar si quedó o no registrado
                ];
            }
        } catch (\Exception $e) {
            $estadoInformacionAdicional = ['Procesar Respuesta Dian - Archivo: ' . $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()];
            $respuesta['IsValid']                    = false;
            $respuesta['StatusCode']                 = '';
            $respuesta['StatusDescription']          = 'Error al procesar Respuesta Dian';
            $respuesta['StatusMessage']              = '';
            $respuesta['XmlDocumentKey']             = '';
            $respuesta['ErrorMessage']               = $e->getMessage();
            $respuesta['estado']                     = 'FALLIDO';
            $respuesta['estadoInformacionAdicional'] = ($metodoAmbienteDestino == 'GetStatusZip') ? $estadoInformacionAdicional : json_encode($estadoInformacionAdicional);
            $respuesta['cdnFechaValidacionDian']     = '';

            $procesados[$cdn_id] = [
                'respuestaProcesada' => $respuesta,
                'xmlRespuestaDian'   => $rptaDian
            ];
        }
    }

    /**
     * Actualiza el estado de un documento de nómina electrónica transmitido a la DIAN.
     *
     * @param int $cdn_id ID del documento que se actualizará
     * @param int $est_id ID del estado que se actualizará
     * @param string $estadoResultado Resultado del procesamiento estado
     * @param string $mensajeResultado Mensaje del resultado de procesamiento del estado
     * @param string $xmlRespuestaDian Respuesta recibida por parte de la DIAN en formato XML
     * @param array $respuestaObject Documento Json conteniendo información relacionada con el procesamiento del estado
     * @param string|array|null $estadoInformacionAdicional Json conteniendo información del código de respuesta, fecha y hora de validación en la DIAN
     * @param string $fechaHoraFinProceso Fecha y hora de finalización del procesamiento
     * @param float $tiempoProcesamiento Tiempo total del procesamiento en segundos
     * @param string $estadoEjecucion Estado de la ejecución del procesamiento del estado
     * @param string $cdnFechaValidacionDian Concatenación de la fecha y hora en la cual la DIAN procesó la información
     * @param string $nombreArchivoDisco Nombre del archivo en disco resultado del proceso ejecutado
     * @return void
     */
    public function actualizarEstadoDocumentoTransmitido(int $cdn_id, int $est_id, string $estadoResultado, string $mensajeResultado, array $respuestaObject, $estadoInformacionAdicional = null, string $fechaHoraFinProceso, float $tiempoProcesamiento, string $estadoEjecucion, string $cdnFechaValidacionDian, string $nombreArchivoDisco = null) {
        $estado = DsnEstadoDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
            ->where('est_id', $est_id)
            ->first();

        // Si el estado tenia información registrada en información adicional se debe conservar
        if (!is_array($estadoInformacionAdicional)) {
            $estadoInformacionAdicional = $estadoInformacionAdicional != null ? json_decode($estadoInformacionAdicional, true) : [];
        }   

        if($estado->est_informacion_adicional != '') {
            $informacionAdicionalExiste = json_decode($estado->est_informacion_adicional, true);
            $estadoInformacionAdicional = array_merge($informacionAdicionalExiste, $estadoInformacionAdicional);
        }

        if(!is_null($nombreArchivoDisco))
            $estadoInformacionAdicional =  array_merge($estadoInformacionAdicional, ['est_xml' => $nombreArchivoDisco]);

        $estado->update([
                'est_resultado'             => $estadoResultado,
                'est_mensaje_resultado'     => $mensajeResultado,
                'est_object'                => $respuestaObject,
                'est_informacion_adicional' => empty($estadoInformacionAdicional) ? null : json_encode($estadoInformacionAdicional),
                'est_fin_proceso'           => $fechaHoraFinProceso,
                'est_tiempo_procesamiento'  => $tiempoProcesamiento,
                'est_ejecucion'             => $estadoEjecucion,
            ]);

        if($cdnFechaValidacionDian != '') {
            DsnCabeceraDocumentoNominaDaop::select([
                'cdn_id',
                'cdn_fecha_validacion_dian'
            ])
                ->find($cdn_id)
                ->update([
                    'cdn_fecha_validacion_dian' => $cdnFechaValidacionDian
                ]);
        }
    }

    /**
     * Crea un nuevo estado para un documento de nómina electrónica.
     *
     * @param int $cdn_id ID del documento para el cual se crea el estado
     * @param string $estado Nombre del estado a crear
     * @param int $age_id ID del agendamiento relacionado con el estado
     * @param int $usu_id ID del usuario que crea el nuevo estado
     * @param array $informacionAdicional Array conteniendo información adicional sobre el agendamiento
     * @return void
     */
    public function creaNuevoEstadoDocumentoNomina(int $cdn_id, string $estado, int $age_id, int $usu_id, array $informacionAdicional = null) {
        DsnEstadoDocumentoDaop::create([
            'cdn_id'                    => $cdn_id,
            'est_estado'                => $estado,
            'age_id'                    => $age_id,
            'age_usu_id'                => $usu_id,
            'est_informacion_adicional' => ($informacionAdicional != null && $informacionAdicional != '') ? json_encode($informacionAdicional) : null,
            'usuario_creacion'          => $usu_id,
            'estado'                    => 'ACTIVO'
        ]);
    }

    /**
     * Consultar estado de documentos enviados en la DIAN - Genera un agendamieto nuevo para DO.
     *
     * @param Request $request
     * @return Response
     */
    public function agendarConsultaDocumentosEnviadosDnEstadoDian(Request $request) {
        try {
            // Usuario autenticado
            $user   = auth()->user();
            $cdnIds = explode(',', $request->json('cdnIds'));

            if(!empty($cdnIds)) {
                // Crea un agendamiento para DO para poder consultar los estados a través del método GetStatus
                $agendamientoDO = DoTrait::crearNuevoAgendamiento('DONOMINA', $user->usu_id, $user->getBaseDatos->bdd_id, count($cdnIds), null);

                $exitosos = [];
                $fallidos = [];
                // Para cada documento en el request se crea el estado DO que permitirá realizar la consulta del estado en la DIAN
                foreach($cdnIds as $cdn_id) {
                    // verifica que el documento exista en el sistema y que tenga un estado XML Exitoso
                    $existe = DsnCabeceraDocumentoNominaDaop::select(['cdn_id', 'cdn_prefijo', 'cdn_consecutivo'])
                        ->where('cdn_id', $cdn_id)
                        ->with(['getXmlDocumento'])
                        ->first();

                    if($existe && isset($existe->getXmlDocumento) && $existe->getXmlDocumento != null) {
                        $this->creaNuevoEstadoDocumentoNomina($cdn_id, 'DO', $agendamientoDO->age_id, $user->usu_id, ['metodo' => 'GetStatus']);
                        $exitosos[] = $existe->cdn_prefijo . $existe->cdn_consecutivo;
                    } elseif($existe && (!isset($existe->getXmlDocumento) || $existe->getXmlDocumento == null)) {
                        $fallidos[] = $existe->cdn_prefijo . $existe->cdn_consecutivo;
                    } else {
                        $fallidos[] = 'cdn_id = ' . $cdn_id;
                    }
                }

                $mensaje = '';
                if(!empty($exitosos)) {
                    $mensaje = 'Los siguientes documentos fueron agendados para consultar su estado en la DIAN: [' . implode(', ', $exitosos) . ']';
                }
                if(!empty($fallidos)) {
                    $mensaje .= ((!empty($exitosos)) ? ' - ' : '') . 'Los siguientes documentos no existen o no cuentan con un estado XML exitoso: [' . implode(', ', $fallidos) . ']';
                }

                return response()->json([
                    'message' => $mensaje
                ], 201);
            } else {
                return response()->json([
                    'message' => 'Error al intentar agendar documentos para consultar su estado en la DIAN',
                    'errors'  => ['No se recibieron IDs de documentos']
                ], 409);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al intentar agendar los documentos para consultar su estado en la DIAN',
                'errors'  => [$e->getMessage()]
            ], 422);
        }
    }

    /**
     * Procesa los documentos de nómina electrónica que se consultarán en al DIAN.
     *
     * @param array $documentos Array que contiene los IDs de los documentos que se deben consultar en la DIAN
     * @param array $estados Array que contiene información relacionada con los estados de los documentos que se están consultando
     * @param User $user Usuario relacionado con el procesamiento
     * @param bool $esAgendamiento Indica si el llamado al método está relacionado o no con un agendamiento
     * @param bool $esReconsulta Indica si el llamado al método está relacionado o no con una nueva consulta del documento
     * @return array Array que contiene información sobre el procesamiento de los documentos, ya sea por fallas/errores o por consultas efectivas
     */
    public function ConsultarDocumentosNominaElectronicaDian(array $documentos, array $estados, User $user, bool $esAgendamiento = true, $esReconsulta = false) {
        // Verifica que el parámetro recibido sea un array
        if(!is_array($documentos)) {
            return [
                'success' => false,
                'error'   => 'El parámetro recibido para realizar la consulta de documentos de nómnina electrónica en la Dian debe ser del tipo Array y por cada posición del array contener el ID del documento a procesar',
                'codigo'  => 409
            ];
        }

        // Array de objetos de paramétricas
        $parametricas = [
            'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_metodo', 'add_descripcion', 'add_url', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()->toArray()
        ];

        $bdUser = $user->getBaseDatos->bdd_nombre;
        if(!empty($user->bdd_id_rg)) {
            $bdUser = $user->getBaseDatosRg->bdd_nombre;
        }

        $procesados  = [];
        $metodosBase = new MetodosBase();
        $firmarSoap  = new FirmarEnviarSoap($esReconsulta);
        foreach($documentos as $cdn_id) {
            if($esAgendamiento) {
                // Marca el inicio de procesamiento del documento en el estado correspondiente
                DsnEstadoDocumentoDaop::find($estados[$cdn_id]['est_id'])
                    ->update([
                        'est_ejecucion'      => 'ENPROCESO',
                        'est_inicio_proceso' => date('Y-m-d H:i:s')
                    ]);

                // Actualiza el microtime de inicio de procesamiento del documento
                $estados[$cdn_id]['inicio'] = microtime(true);
            }

            // Obtiene el XML firmado
            $xmlFirmado = DsnEstadoDocumentoDaop::select(['est_id', 'cdn_id', 'est_informacion_adicional'])
                ->where('cdn_id', $cdn_id)
                ->where('est_estado', 'XML')
                ->where('est_resultado', 'EXITOSO')
                ->orderBy('est_id', 'desc')
                ->with([
                    'getCabeceraNomina:cdn_id,emp_id,cdn_prefijo,cdn_consecutivo,cdn_clasificacion,cdn_cune,cdn_nombre_archivos,fecha_creacion',
                    'getCabeceraNomina.getEmpleador:emp_id,sft_id,emp_identificacion,emp_archivo_certificado,emp_password_certificado,bdd_id_rg',
                    'getCabeceraNomina.getEmpleador.getProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getCabeceraNomina.getEmpleador.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->first();

            if(!empty($xmlFirmado->getCabeceraNomina->getEmpleador->bdd_id_rg)) {
                $bdFirma = $xmlFirmado->getCabeceraNomina->getEmpleador->getBaseDatosRg->bdd_nombre;
            } else {
                $bdFirma = $bdUser;
            }

            $bdFirma = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdFirma);
            $certificado = config('variables_sistema.PATH_CERTIFICADOS') . '/' . $bdFirma . '/' . $xmlFirmado->getCabeceraNomina->getEmpleador->emp_archivo_certificado;

            if($xmlFirmado) {
                $urlAmbienteDestino          = $metodosBase->obtieneDatoParametrico($parametricas['ambienteDestino'], 'add_id', $xmlFirmado->getCabeceraNomina->getEmpleador->getProveedorTecnologico->add_id, 'add_url');
                $soapConsultar               = $this->inicializarSoapDian('GetStatus', $urlAmbienteDestino, $xmlFirmado->getCabeceraNomina->cdn_cune);
                $rptaConsultaEstadoDocumento = $firmarSoap->firmarSoapXML(
                    $soapConsultar,
                    $certificado,
                    $xmlFirmado->getCabeceraNomina->getEmpleador->emp_password_certificado,
                    $urlAmbienteDestino
                );

                if(array_key_exists('error', $rptaConsultaEstadoDocumento) && empty($rptaConsultaEstadoDocumento['error'])) {
                    // La respuesta de la DIAN se procesa para poder definir si la transmisión fue exitosa o fallida
                    $this->procesarRespuestaDian(
                        $firmarSoap,
                        $rptaConsultaEstadoDocumento['rptaDian'],
                        'GetStatus',
                        null,
                        null,
                        null,
                        null,
                        $procesados,
                        $cdn_id,
                        json_decode($estados[$cdn_id]['estadoInformacionAdicional'], true),
                        $esReconsulta
                    );
                } else {
                    $procesados[$cdn_id] = [
                        'respuestaProcesada' => [
                            'IsValid'                    => 'false',
                            'StatusCode'                 => '',
                            'StatusDescription'          => $rptaConsultaEstadoDocumento['error'],
                            'StatusMessage'              => '',
                            'XmlDocumentKey'             => '',
                            'ErrorMessage'               => '',
                            'estado'                     => 'FALLIDO',
                            'estadoInformacionAdicional' => (array_key_exists('exceptions', $rptaConsultaEstadoDocumento) && $rptaConsultaEstadoDocumento['exceptions'] != '') ? json_encode($rptaConsultaEstadoDocumento['exceptions']) : null,
                            'cdnFechaValidacionDian'     => ''
                        ],
                        'xmlRespuestaDian'  => ''
                    ];
                }
            } else {
                $procesados[$cdn_id] = [
                    'respuestaProcesada' => [
                        'IsValid'                    => 'false',
                        'StatusCode'                 => '',
                        'StatusDescription'          => 'Documento con ID ' . $cdn_id . 'No existe en openETL',
                        'StatusMessage'              => '',
                        'XmlDocumentKey'             => '',
                        'ErrorMessage'               => '',
                        'estado'                     => 'FALLIDO',
                        'estadoInformacionAdicional' => '',
                        'cdnFechaValidacionDian'     => ''
                    ],
                    'xmlRespuestaDian'  => ''
                ];
            }

            if($esAgendamiento) {
                $nombreArchivoDisco = null;
                if((!empty($procesados[$cdn_id]['xmlRespuestaDian']))) {
                    $nombreArchivoDisco = $this->guardarArchivoEnDisco(
                        $xmlFirmado->getCabeceraNomina->getEmpleador->emp_identificacion,
                        $xmlFirmado->getCabeceraNomina,
                        'nomina',
                        'xmlDian',
                        'xml',
                        base64_encode($procesados[$cdn_id]['xmlRespuestaDian'])
                    );
                }

                // Actualiza el estado del documento que se encuentra en procesamiento
                $this->actualizarEstadoDocumentoTransmitido(
                    $cdn_id,
                    $estados[$cdn_id]['est_id'],
                    $procesados[$cdn_id]['respuestaProcesada']['estado'],
                    ($procesados[$cdn_id]['respuestaProcesada']['estado'] === 'EXITOSO') ? $procesados[$cdn_id]['respuestaProcesada']['StatusDescription'] : $procesados[$cdn_id]['respuestaProcesada']['StatusDescription'] . ' // ' . $procesados[$cdn_id]['respuestaProcesada']['ErrorMessage'],
                    (!empty($procesados[$cdn_id]['respuestaProcesada'])) ? $procesados[$cdn_id]['respuestaProcesada'] : null,
                    (!empty($procesados[$cdn_id]['respuestaProcesada']['estadoInformacionAdicional'])) ? $procesados[$cdn_id]['respuestaProcesada']['estadoInformacionAdicional'] :  null,
                    date('Y-m-d H:i:s'),
                    number_format((microtime(true) - $estados[$cdn_id]['inicio']), 3, '.', ''),
                    'FINALIZADO',
                    $procesados[$cdn_id]['respuestaProcesada']['cdnFechaValidacionDian'],
                    $nombreArchivoDisco
                );
            }
        }

        return $procesados;
    }
}
