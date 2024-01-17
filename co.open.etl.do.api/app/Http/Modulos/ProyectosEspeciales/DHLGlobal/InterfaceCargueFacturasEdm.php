<?php
namespace App\Http\Modulos\ProyectosEspeciales\DHLGlobal;

use Validator;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class InterfaceCargueFacturasEdm extends Controller {
    use DoTrait;

    /**
     * Nombre de la base de datos sobre la cual se ejecuta el comando.
     *
     * @var string
     */
    public const BASE_DATOS = 'etl_dhlgfxxx';

    /**
     * Nits de los OFEs en la base de datos a procesar.
     *
     * @var array
     */
    public const NITS_OFES = [
        '860030380',
        '830002397',
        '830025224',
        '860038063',
    ];

    /**
     * Valor para el nodo Country en los XML.
     *
     * @var string
     */
    public const COUNTRY = 'CO';

    /**
     * Extensión para el archivo PDF.
     *
     * @var string
     */
    public const PDF_EXT = '.pdf';

    /**
     * Extensión para el archivo XML.
     *
     * @var string
     */
    public const XML_EXT = '.xml';

    /**
     * Nombre del folder en donde se almacenan los archivos XML.
     *
     * @var string
     */
    public const FOLDER_XML = 'Index';

    /**
     * Nombre del folder en donde se almacenan los archivos PDF.
     *
     * @var string
     */
    public const FOLDER_PDF = 'Image';

    /**
     * Path para los archivos con department igual a COT.
     *
     * @var string
     */
    private const PATH_COT = '/chroot/itmr4/itmr4/ITMR4/CO/CDZ/Images/';

    /**
     * Path para los archivos con department diferente de COT.
     *
     * @var string
     */
    private const PATH_NO_COT = '/chroot/itmr4/itmr4/ITMR4/CO/TMS/Images/';


    /**
     * Array de tipos de documentos que aplican para EDM.
     * 
     * @var array
     */
    public const DOCUMENT_TYPES = [
        'FEA' => [
            'FileNet_Document_Type' => '448',
            'FileNet_Class'         => 'Invoices'
        ],
        'FIA' => [
            'FileNet_Document_Type' => '578',
            'FileNet_Class'         => 'Invoices'
        ],
        'FFE' => [
            'FileNet_Document_Type' => '448',
            'FileNet_Class'         => 'Invoices'
        ],
        'FFI' => [
            'FileNet_Document_Type' => '578',
            'FileNet_Class'         => 'Invoices'
        ],
        'FLE' => [
            'FileNet_Document_Type' => '448',
            'FileNet_Class'         => 'Invoices'
        ],
        'FLI' => [
            'FileNet_Document_Type' => '578',
            'FileNet_Class'         => 'Invoices'
        ],
        'WFE' => [
            'FileNet_Document_Type' => '448',
            'FileNet_Class'         => 'Invoices'
        ],
        'WFI' => [
            'FileNet_Document_Type' => '578',
            'FileNet_Class'         => 'Invoices'
        ],
        'WLE' => [
            'FileNet_Document_Type' => '448',
            'FileNet_Class'         => 'Invoices'
        ],
        'WLI' => [
            'FileNet_Document_Type' => '578',
            'FileNet_Class'         => 'Invoices'
        ],
        'AFX' => [
            'FileNet_Document_Type' => '448',
            'FileNet_Class'         => 'Invoices'
        ],
        'SFX' => [
            'FileNet_Document_Type' => '448',
            'FileNet_Class'         => 'Invoices'
        ],
        'COT' => [
            'FileNet_Document_Type' => '704',
            'FileNet_Class'         => 'CDZDGFCustomsServicesInvoice'
        ]
    ];

    /**
     * Array en donde se almacenan los prefijos y consecutivos de los documentos transmitidos.
     * 
     * Utilizado para retornar el mensaje de respuesta cuando la petición de procesamiento tiene origen en el frontend
     *
     * @var array
     */
    public $arrDocumentosTransmitidos = [];

    /**
     * Array en donde se almacenan los errores generados en el proceso.
     * 
     * Utilizado para retornar el mensaje de respuesta cuando la petición de procesamiento tiene origen en el frontend
     *
     * @var array
     */
    public $arrErrores = [];

    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:EmisionTransmitirEdm'
        ])->only([
            'transmitirEdm'
        ]);
    }

    /**
     * Crea un estado para el documento.
     *
     * @param int $cdo_id ID del documento
     * @param string $estadoDescripcion Descripción del estado
     * @param string $resultado Resultado del estado
     * @param string $mensajeResultado Mensaje de resultado del estado
     * @param string|null $informacionAdicional Objeto conteniendo información relacionada con el objeto
     * @param int|null $age_id ID agendamiento
     * @param int|null $age_usu_id ID del usuario relacionadoc con el agendamiento
     * @param string $inicio Fecha y hora de inicio de procesamiento
     * @param string $fin Fecha y hora final de procesamiento
     * @param float $tiempo Tiempo de procesamiento
     * @param string $ejecucion Estado final de procesamiento
     * @param int $usuario ID del usuario relacionado con el procesamiento
     * @param string $estadoRegistro Estado del registro
     * @return void
     */
    public function crearEstado(int $cdo_id, string $estadoDescripcion, string $resultado, string $mensajeResultado, string $informacionAdicional = null, int $age_id = null, int $age_usu_id = null, string $inicio, string $fin, float $tiempo, string $ejecucion, int $usuario, string $estadoRegistro) {
        EtlEstadosDocumentoDaop::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estadoDescripcion,
            'est_resultado'             => $resultado,
            'est_mensaje_resultado'     => $mensajeResultado,
            'est_informacion_adicional' => $informacionAdicional,
            'age_id'                    => $age_id,
            'age_usu_id'                => $age_usu_id,
            'est_inicio_proceso'        => $inicio,
            'est_fin_proceso'           => $fin,
            'est_tiempo_procesamiento'  => $tiempo,
            'est_ejecucion'             => $ejecucion,
            'usuario_creacion'          => $usuario,
            'estado'                    => $estadoRegistro,
        ]);
    }

    /**
     * Obtiene los IDs de los OFEs incluidos en el array NITS_OFES.
     *
     * @return array Array con los IDs de los OFEs
     */
    public function getIdsOfes() {
        return ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->whereIn('ofe_identificacion', self::NITS_OFES)
            ->where('estado', 'ACTIVO')
            ->get()
            ->pluck('ofe_id')
            ->values()
            ->toArray();
    }

    /**
     * Obtiene los documentos que cumplen las condiciones para EDM.
     *
     * @param array $arrCdoIds Array con los IDs que se desean transmitir, el parámetro aplica cuanod la petición tiene como origen el frontend
     * @return EtlCabeceraDocumentoDaop Collección de documentos que cumplen las condiciones para EDM
     */
    public function getDocumentosEdm(array $arrCdoIds = []) {
        $documentos = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'fecha_creacion'])
            ->where('cdo_clasificacion', 'FC')
            ->whereIn('ofe_id', $this->getIdsOfes())
            ->whereHas('getDoDocumento')
            ->whereHas('getNotificacionDocumento')
            ->whereHas('getDadDocumentosDaop', function ($query) {
                $primero = true;
                foreach(self::DOCUMENT_TYPES as $documentType => $documentTypeArray) {
                    if($primero) {
                        $primero = false;
                        $query->whereJsonContains('cdo_informacion_adicional->department', $documentType);
                    } else
                        $query->orWhereJsonContains('cdo_informacion_adicional->department', $documentType);
                }
            });

        if(empty($arrCdoIds))
            $documentos = $documentos->doesntHave('getEstadoTransmisionEdm')
                ->withCount('getEstadosTransmisionEdmFallido');
        else
            $documentos = $documentos->whereIn('cdo_id', $arrCdoIds);

        $documentos = $documentos->with([
                'getDoDocumento:est_id,cdo_id,est_informacion_adicional',
                'getNotificacionDocumento:est_id,cdo_id,est_informacion_adicional',
                'getDadDocumentosDaop:dad_id,cdo_id,cdo_informacion_adicional',
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_conexion_ftp'
            ])
            ->get();

        return $documentos;
    }

    /**
     * Crea el XML que debe enviarse al SFTP
     *
     * @param EtlCabeceraDocumentoDaop $documento Documento del cual se extrae la información
     * @return array Array conteniendo el nombre de los archivos y el XML en base64 que debe enviarse al SFTP
     */
    public function crearXml(EtlCabeceraDocumentoDaop $documento) {
        $oDomtree = new \DOMDocument('1.0', 'UTF-8');

        $oDomtree->preserveWhiteSpace = false;
        $oDomtree->formatOutput       = true;

        $oXmlRaiz = $oDomtree->createElement('Index-File');
        $oDomtree->appendChild($oXmlRaiz);

        // Fecha y hora con milisegundos del momento de generación de la interface
        $dateObj = \DateTime::createFromFormat('U.u', microtime(TRUE));
        $msg     = $dateObj->format('u');
        $msg    /= 1000;
        $dateObj->setTimeZone(new \DateTimeZone('America/Bogota'));

        $fechaGeneracion = $dateObj->format('mdY').$dateObj->format('His').intval($msg);

        $fileName = self::COUNTRY .
            (array_key_exists('procedencia', $documento->getDadDocumentosDaop->cdo_informacion_adicional) ? substr(str_replace(' ', '', trim($documento->getDadDocumentosDaop->cdo_informacion_adicional['procedencia'])), 0, 3) : '') . '_' .
            self::DOCUMENT_TYPES[$documento->getDadDocumentosDaop->cdo_informacion_adicional['department']]['FileNet_Class'] . '_' .
            $fechaGeneracion;

        if($documento->getDadDocumentosDaop->cdo_informacion_adicional['department'] != 'COT') {
            $oXmlRaiz_FILE = $oDomtree->createElement('FILE', self::PATH_NO_COT . $fileName . self::PDF_EXT);
            $oXmlRaiz->appendChild($oXmlRaiz_FILE);
        } else {
            $oXmlRaiz_FILE = $oDomtree->createElement('FILE', self::PATH_COT . $fileName . self::PDF_EXT);
            $oXmlRaiz->appendChild($oXmlRaiz_FILE);
        }

        $oXmlRaiz_DOCUMENTCLASS = $oDomtree->createElement('DOCUMENTCLASS', self::DOCUMENT_TYPES[$documento->getDadDocumentosDaop->cdo_informacion_adicional['department']]['FileNet_Class']);
        $oXmlRaiz->appendChild($oXmlRaiz_DOCUMENTCLASS);

        $oXmlRaiz_ShipmentID = $oDomtree->createElement('ShipmentID', array_key_exists('file', $documento->getDadDocumentosDaop->cdo_informacion_adicional) ? $documento->getDadDocumentosDaop->cdo_informacion_adicional['file'] : '');
        $oXmlRaiz->appendChild($oXmlRaiz_ShipmentID);

        $oXmlRaiz_Country = $oDomtree->createElement('Country', self::COUNTRY);
        $oXmlRaiz->appendChild($oXmlRaiz_Country);

        $oXmlRaiz_DocumentType = $oDomtree->createElement('DocumentType', self::DOCUMENT_TYPES[$documento->getDadDocumentosDaop->cdo_informacion_adicional['department']]['FileNet_Document_Type']);
        $oXmlRaiz->appendChild($oXmlRaiz_DocumentType);

        return [
            'fileName' => $fileName,
            'xml'      => base64_encode($this->eliminarCaracteresBOM($oDomtree->saveXML($oDomtree)))
        ];
    }

    /**
     * Obtiene la representación gráfica del documento electrónico.
     *
     * @param EtlCabeceraDocumentoDaop $documento Documento del cual se extrae la información
     * @return string Pdf en base 64 que debe enviarse al SFTP
     */
    public function getPdf(EtlCabeceraDocumentoDaop $documento) {
        $estInformacionAdicional = !empty($documento->getNotificacionDocumento->est_informacion_adicional) ? json_decode($documento->getNotificacionDocumento->est_informacion_adicional, true) : [];

        $pdf = $this->obtenerArchivoDeDisco(
            'emision',
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento,
            array_key_exists('est_archivo', $estInformacionAdicional) && !empty($estInformacionAdicional['est_archivo']) ? $estInformacionAdicional['est_archivo'] : ''
        );

        return !empty($pdf) ? base64_encode($this->eliminarCaracteresBOM($pdf)) : '';
    }

    /**
     * Envia el XML y PDF de un documento electrónico al SFTP del OFE.
     *
     * @param EtlCabeceraDocumentoDaop $documento Documento del cual se extrae la información
     * @param array $xml Array conteniendo el nombre de archivo y XML en base64 que se debe enviar
     * @param string $pdf PDF en base 64 que se debe enviar
     * @param string $fechaInicio Fecha y hora de inicio del procesamiento
     * @param string $inicioProcesamiento Timestamp del inicio del procesamiento
     * @param bool $origenWeb Indica si el origen del procesamiento es una petición desde el frontend
     * @return void
     */
    public function enviarSftp(EtlCabeceraDocumentoDaop $documento, array $xml, string $pdf, string $fechaInicio, string $inicioProcesamiento, bool $origenWeb = false) {

        if($documento->getDadDocumentosDaop->cdo_informacion_adicional['department'] != 'COT') {
            $pathPdf = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['edm_core'] . '/' . self::FOLDER_PDF . '/';
            $pathXml = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['edm_core'] . '/' . self::FOLDER_XML . '/';
        } else {
            $pathPdf = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['edm_cdz'] . '/' . self::FOLDER_PDF . '/';
            $pathXml = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['edm_cdz'] . '/' . self::FOLDER_XML . '/';
        }

        $this->configurarDiscoSftpDhlGlobal($documento->getConfiguracionObligadoFacturarElectronicamente);
        $sendXml = Storage::disk('SftpDhlGlobal')->put($pathXml . $xml['fileName'] . self::XML_EXT, base64_decode($xml['xml']));
        $sendPdf = Storage::disk('SftpDhlGlobal')->put($pathPdf . $xml['fileName'] . self::PDF_EXT, base64_decode($pdf));

        if($sendXml && $sendPdf)
            $this->crearEstado(
                $documento->cdo_id,
                'TRANSMISION_EDM',
                'EXITOSO',
                'Archivos EDM transmitidos',
                null,
                null,
                null,
                $fechaInicio,
                date('Y-m-d H:i:s'),
                number_format((microtime(true) - $inicioProcesamiento), 3, '.', ''),
                'FINALIZADO',
                auth()->user()->usu_id,
                'ACTIVO'
            );

        $this->arrDocumentosTransmitidos[] = $documento->rfa_prefijo . $documento->cdo_consecutivo;
    }

    /**
     * Configura un disco en tiempo de ejecución para el SFTP de DHL Global
     *
     * @param App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente $ofe Modelo OFE
     * @return void
     */
    private function configurarDiscoSftpDhlGlobal(ConfiguracionObligadoFacturarElectronicamente $ofe) {
        config([
            'filesystems.disks.SftpDhlGlobal' => [
                'driver'   => 'sftp',
                'host'     => $ofe->ofe_conexion_ftp['edm_host'],
                'port'     => $ofe->ofe_conexion_ftp['edm_port'],
                'username' => $ofe->ofe_conexion_ftp['edm_username'],
                'password' => $ofe->ofe_conexion_ftp['edm_password'],
            ]
        ]);
    }

    /**
     * Inicia el procesamiento de un archivo para su transmisión a EDM.
     *
     * @param EtlCabeceraDocumentoDaop $documento Documento del cual se extrae la información
     * @param bool $origenWeb Indica si el origen del procesamiento es una petición desde el frontend
     * @return void
     */
    public function procesar(EtlCabeceraDocumentoDaop $documento, bool $origenWeb = false) {
        $fechaInicio         = date('Y-m-d H:i:s');
        $inicioProcesamiento = microtime(true);

        if($documento->get_estados_transmision_edm_fallido_count < 3 || $origenWeb == true) {
            try {
                $xml = $this->crearXml($documento);
                $pdf = $this->getPdf($documento);

                if(!empty($xml['xml']) && !empty($pdf))
                    $this->enviarSftp($documento, $xml, $pdf, $fechaInicio, $inicioProcesamiento, $origenWeb);
                else
                    if(!$origenWeb)
                        $this->crearEstado(
                            $documento->cdo_id,
                            'TRANSMISION_EDM',
                            'FALLIDO',
                            'No se pudo generar/obtener el XML y/o el PDF',
                            null,
                            null,
                            null,
                            $fechaInicio,
                            date('Y-m-d H:i:s'),
                            number_format((microtime(true) - $inicioProcesamiento), 3, '.', ''),
                            'FINALIZADO',
                            auth()->user()->usu_id,
                            'ACTIVO'
                        );
                    $this->arrErrores[] = 'No se pudo generar/obtener el XML y/o el PDF del Documento ' . $documento->rfa_prefijo . $documento->cdo_consecutivo;
            } catch (\Exception $e) {
                if(!$origenWeb)
                    $this->crearEstado(
                        $documento->cdo_id,
                        'TRANSMISION_EDM',
                        'FALLIDO',
                        'Se generó un error al procesar el envio a EDM',
                        json_encode([[
                            'traza' => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                        ]]),
                        null,
                        null,
                        $fechaInicio,
                        date('Y-m-d H:i:s'),
                        number_format((microtime(true) - $inicioProcesamiento), 3, '.', ''),
                        'FINALIZADO',
                        auth()->user()->usu_id,
                        'ACTIVO'
                    );
                $this->arrErrores[] = 'Se generó un error al procesar el envio a EDM para el Documento ' . $documento->rfa_prefijo . $documento->cdo_consecutivo . ': '. $e->getMessage();
            }
        }
    }

    /**
     * Permite la transmisión de documentos a EDM.
     *
     * @param Request $request
     * @return Response
     */
    public function transmitirEdm(Request $request) {
        $objValidator = Validator::make($request->all(), [
            'cdoIds' => 'required|string'
        ]);

        if (!empty($objValidator->errors()->all()))
            return response()->json([
                'message' => 'Transmisión EDM',
                'errors'  => ['La petición esta mal formado, no se enviaron los IDs de los documentos a transmitir']
            ], Response::HTTP_BAD_REQUEST);

        $cdoIds     = explode(',', $request->cdoIds);
        $documentos = $this->getDocumentosEdm($cdoIds);

        if(!$documentos->isEmpty())
            $documentos->map(function ($documento) {
                $this->procesar($documento, true);
            });
        else
            return response()->json([
                'message' => 'Transmisión EDM',
                'errors'  => ['Los documentos no cumplen con las condiciones para la transmisión a EDM']
            ], Response::HTTP_BAD_REQUEST);

        $complementaMensaje = '';
        if (count($this->arrDocumentosTransmitidos) > 0)
            $complementaMensaje .= 'Los siguientes documentos fueron transmitidos a EDM: [' . implode(' - ', $this->arrDocumentosTransmitidos) . ']. ';
        if(count($cdoIds) != count($this->arrDocumentosTransmitidos))
            $complementaMensaje .= 'La petición incluía documentos que no fueron transmitidos porque no cumplen con las condiciones para la transmisión a EDM.';

        if (!empty($this->arrErrores) && count($this->arrErrores) > 0)
            return response()->json([
                'message' => $complementaMensaje,
                'errors'  => $this->arrErrores
            ], Response::HTTP_BAD_REQUEST);

        return response()->json([
            'message' => trim($complementaMensaje)
        ], Response::HTTP_OK);
    }
}