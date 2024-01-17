<?php
namespace App\Http\Modulos\Recepcion\TransmisionErp\Commons\Emssanar;

use App\Http\Traits\DoTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\RecepcionTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\Recepcion\TransmisionErp\Commons\MethodsTrait;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class EmssanarController extends Controller {
    use DoTrait, MethodsTrait, RecepcionTrait, PackageMainTrait;

    /**
     * Cantidad de documentos que se pueden consultar por procesamiento.
     *
     * @var int
     */
    private $cantidadDocumentosConsultar = 200;
    

    /**
     * Fecha de inicio de transmisión hacia el ERP..
     *
     * @var string
     */
    public $fechaInicioTransmision = null;

    /**
     * Fecha de inicio de transmisión hacia el ERP..
     *
     * @var string
     */
    public $fechaFinTransmision = null;
    
    /**
     * Constructor de la clase
     *
     * @param Collection $oferente Colección con información del OFE en procesamiento
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Procesa los documentos que deban ser enviados.
     * 
     * @param integer $limiteIntentos Límite de inentos de transmisión
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Ofe relacionado con la transmisión
     * @param string $cdoIds IDs de documentos que se desean transmitir, este parámetro llega cuando el proceso es llamado desde el cliente web a través de la ruta /recepcion/documentos/transmitir-erp
     * @return void
     */
    public function procesar($limiteIntentos, $ofe, $cdoIds = null) {
        $documentos = RepCabeceraDocumentoDaop::select([
            'cdo_id',
            'rfa_prefijo',
            'cdo_consecutivo',
            'cdo_clasificacion',
            'cdo_observacion',
            'ofe_id',
            'pro_id',
            'cdo_documento_referencia',
            'cdo_nombre_archivos',
            'fecha_creacion'
        ]);

        if ($cdoIds == null)
            $documentos = $documentos->whereDoesntHave('getRepEstadosDocumentoDaop', function($query) {
                $query->select(['est_id', 'cdo_id'])
                    ->where('est_estado', 'TRANSMISIONERP')
                    ->where('est_ejecucion', 'ENPROCESO')
                    ->latest();
            });

        $documentos = $documentos->with([
            'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                $query->select([
                    'ofe_id',
                    'ofe_identificacion',
                    DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_razon_social'),
                    'ofe_recepcion_conexion_erp'
                ]);
            },
            'getConfiguracionProveedor' => function($query) {
                $query->select([
                    'pro_id',
                    'pro_identificacion',
                    'pro_integracion_erp',
                    'pro_correos_notificacion',
                    DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as pro_razon_social')
                ]);
            },
            'getRepEstadosDocumentoDaop:est_id,cdo_id,est_estado,est_informacion_adicional'
        ])
        ->withCount([
            'getTransmisionErpFallido' => function($query) {
                $query->where('est_estado', 'TRANSMISIONERP')
                    ->where('est_resultado', 'FALLIDO')
                    ->where('est_ejecucion', 'FINALIZADO');
            }
        ])
        ->where('ofe_id', $ofe->ofe_id)
        ->whereIn('cdo_clasificacion', ['FC','NC','ND'])
        ->where('estado', 'ACTIVO');

        if ($cdoIds != null) {
            $documentos = $documentos->whereIn('cdo_id', explode(',', $cdoIds));
        } else {
            if (!empty($this->fechaFinTransmision))
                $documentos = $documentos->whereBetween('cdo_fecha', [$this->fechaInicioTransmision, $this->fechaFinTransmision]);
            else 
                $documentos = $documentos->where('cdo_fecha', '>=', $this->fechaInicioTransmision);

            $documentos = $documentos->doesntHave('getTransmisionErpExitoso');
        }
        $documentos = $documentos->orderBy('fecha_creacion', 'asc');
        
        dump($documentos->toSql());
        dump($documentos->getBindings());

        $registrosProcesados = 0;
        $documentos = $documentos->get()
            ->map(function($documento) use ($limiteIntentos, $cdoIds, &$registrosProcesados) {
                $registrosProcesados++;
                if($registrosProcesados == $this->cantidadDocumentosConsultar) {
                    $registrosProcesados = 0;
                    $this->reiniciarConexion(auth()->user()->getBaseDatos);
                }

                if(
                    ($documento->get_transmision_erp_fallido_count < $limiteIntentos && $cdoIds == null) ||
                    $cdoIds != null
                ) {
                    //Inicio del proceso
                    $inicioMicrotime = microtime(true);

                    $estTranmisionErp = RepEstadoDocumentoDaop::select(['est_id', 'est_estado', 'est_resultado', 'est_ejecucion'])
                        ->where('cdo_id', $documento->cdo_id)
                        ->where('est_estado', 'TRANSMISIONERP')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    $procesar = true;
                    if(
                        !$estTranmisionErp || 
                        $cdoIds != null || 
                        ($estTranmisionErp->est_resultado == 'FALLIDO'  && $estTranmisionErp->est_ejecucion == 'FINALIZADO')
                    ) {
                        $estTranmisionErp = $this->creaNuevoEstadoDocumentoRecepcion(
                            $documento->cdo_id,
                            'TRANSMISIONERP',
                            null,
                            null,
                            date('Y-m-d H:i:s'),
                            null,
                            null,
                            0,
                            auth()->user()->usu_id,
                            null,
                            'ENPROCESO'
                        );
                    } else {
                        if(
                            $estTranmisionErp->est_ejecucion == 'ENPROCESO' || 
                            ($estTranmisionErp->est_resultado == 'EXITOSO'  && $estTranmisionErp->est_ejecucion == 'FINALIZADO')
                        )
                            $procesar = false;
                    }

                    if($procesar || $cdoIds != null) {
                        //Buscando el ultimo estado RDI exitoso
                        $estadoRDI = [];
                        foreach($documento->getRepEstadosDocumentoDaop as $estado) {
                            if ($estado->est_estado == "RDI")
                                $estadoRDI = $estado->est_informacion_adicional;
                        }
                        $informacionAdicional = !empty($estadoRDI) ? json_decode($estadoRDI, true) : [];

                        // Se obtiene el archivo xml-ubl del documento guardado en disco
                        $xmlUbl = $this->obtenerArchivoDeDisco(
                            'recepcion',
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );
                        $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

                        try {
                            //Se Extrae la informacion a enviar por conexion ODBC al ERP de EMSSANAR
                            $informacionDocumento = $this->extraerInformacionXmlUbl($documento->cdo_clasificacion, base64_encode($xmlUbl));

                            if(!empty($informacionDocumento)) {
                                // Se guarda el regisgtro en la base de datos de emssanar
                                $this->guardarDocumentoEmssanar($documento, $estTranmisionErp, $inicioMicrotime, $informacionDocumento);
                            } else {
                                $strMensajeResultado = 'Error al extraer los datos del XML-UBL.';
                                $arrRpta   = [];
                                $arrRpta[] = [
                                    'respuesta_erp'        => ['Resultado' => $strMensajeResultado],
                                    'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
                                    'traza'                => null
                                ];

                                $this->actualizaEstadoDocumentoRecepcion(
                                    $estTranmisionErp->est_id,
                                    'FALLIDO',
                                    $strMensajeResultado,
                                    date('Y-m-d H:i:s'),
                                    number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                                    $arrRpta,
                                    'FINALIZADO'
                                );
                            }
                        } catch (\Exception $e) {
                            // dump($e->getMessage());
                            // dump($e->getTraceAsString());
                            $arrExcepciones   = [];
                            $arrExcepciones[] = [
                                'respuesta_erp'        => ['Resultado' => 'Envío fallido del documento [' . trim($documento->rfa_prefijo) . $documento->cdo_consecutivo . ']'],
                                'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
                                'traza'                => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                            ];
                            
                            $this->actualizaEstadoDocumentoRecepcion(
                                $estTranmisionErp->est_id,
                                'FALLIDO',
                                $e->getMessage(),
                                date('Y-m-d H:i:s'),
                                number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                                $arrExcepciones,
                                'FINALIZADO'
                            );
                        }
                    }
            }
        });
    }

    /**
     * Obtiene el tipo de documento de identificación.
     *
     * @param array $value Array con la información del nodo correspondiente
     * @return mixed
     */
    private function obtenerTipoDocumentoIdentificacion(array $value) {
        if(array_key_exists('@attributes', $value) && array_key_exists('schemeID', $value['@attributes']) && !empty($value['@attributes']['value']))
            return $value['@attributes']['value'];
        else
            return !empty($value['Value']) ? substr($this->sanear_string($value['Value']), 0, 2) : null;
    }

    /**
     * Permite Extraer la informacion del XML-UBL asociado al documento de recepcion
     * 
     * @param string $aplicaPara Indica la clasificación del documento
     * @param string $xmlUbl xml-ubl del documento
     * @return array Informacion extraida del documento
     */
    public function extraerInformacionXmlUbl(string $aplicaPara, string $xmlUbl) {
        $xml = base64_decode($xmlUbl);

        // Eliminando namespace no estandar
        $xml = $this->eliminarNsNoEstandar($xml);

        // Generando el objeto xml
        $xmlUbl = $this->definirTipoDocumento($xml);
        if($this->tipoDocumentoOriginal == 'AttachedDocument') {
            // Obtiene el xml-ubl dentro del attached document para poder continuar con el procesamiento en este método
            $xml    = $this->getValueByXpath($xmlUbl, "//{$this->tipoDocumentoOriginal}/cac:Attachment/cac:ExternalReference/cbc:Description");
            $xmlUbl = $this->definirTipoDocumento((string) $xml[0]);
        }

        $this->registrarNamespaces($xmlUbl->getNameSpaces(true), $xmlUbl);

        $tipoDocumento = '';
        switch($aplicaPara) {
            case 'FC':
                $tipoDocumento = 'Invoice';
                break;
            case 'NC':
                $tipoDocumento = 'CreditNote';
                break;
            case 'ND':
                $tipoDocumento = 'DebitNote';
                break;
        }

        // Array de respuesta
        $arrRespuesta = [];

        if ($aplicaPara == "FC") {
            //Extrayendo Informacion General del documento
            $informacionGeneral = array(
                array('campoBaseDatos' => 'factura_autorizada',         'path' => '/cbc:ID'),
                array('campoBaseDatos' => 'autorizacion_desde',         'path' => '/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:InvoiceControl/sts:AuthorizationPeriod/cbc:StartDate'),
                array('campoBaseDatos' => 'autorizacion_hasta',         'path' => '/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:InvoiceControl/sts:AuthorizationPeriod/cbc:EndDate'),
                array('campoBaseDatos' => 'prefijo_factura_autorizada', 'path' => '/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:InvoiceControl/sts:AuthorizedInvoices/sts:Prefix'),
                array('campoBaseDatos' => 'factura_autorizada_desde',   'path' => '/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:InvoiceControl/sts:AuthorizedInvoices/sts:From'),
                array('campoBaseDatos' => 'factura_aurizada_hasta',     'path' => '/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:InvoiceControl/sts:AuthorizedInvoices/sts:To'),
                array('campoBaseDatos' => 'nit_prestador',              'path' => '/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID'),
                array('campoBaseDatos' => 'valor_inicial',              'path' => '/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount'),
                array('campoBaseDatos' => 'impuesto',                   'path' => '/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'),
                array('campoBaseDatos' => 'valor_factura',              'path' => '/cac:LegalMonetaryTotal/cbc:PayableAmount'),
                array('campoBaseDatos' => 'correo_envio',               'path' => '/cac:AccountingSupplierParty/cac:Party/cac:Contact/cbc:ElectronicMail'),
                array('campoBaseDatos' => 'CUFE',                       'path' => '/cbc:UUID'),
                array('campoBaseDatos' => 'fecha_emision_factura',      'path' => '/cbc:IssueDate'),
                array('campoBaseDatos' => 'r84_fecha_inicio_servicio',  'path' => '/cac:InvoicePeriod/cbc:StartDate'),
                array('campoBaseDatos' => 'r84_fecha_final_servicio',   'path' => '/cac:InvoicePeriod/cbc:EndDate')
            );

            foreach ($informacionGeneral as $informacionCampo) {
                // Se obtiene la información del XPath en el XML
                $valuesXpath = $this->getValueByXpath($xmlUbl, "//{$tipoDocumento}{$informacionCampo['path']}");
                if (!empty($valuesXpath)) {
                    foreach ($valuesXpath as $value) {
                        $arrRespuesta[$informacionCampo['campoBaseDatos']] = $value;
                    }
                }
            }

            // Extrayendo informacion Sector Salud
            $pathSectorSalud = '/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/CustomTagGeneral/Interoperabilidad/Group/Collection/AdditionalInformation';
            $valuesXpath = $this->getAllByXpath($xmlUbl, "//{$tipoDocumento}{$pathSectorSalud}");
            if (!empty($valuesXpath)) {
                // dump($valuesXpath);
                foreach ($valuesXpath as $value) {
                    switch ($value['Name']) {
                        case 'COBERTURA_PLAN_BENEFICIOS':
                            $arrRespuesta['plan_beneficio']   = $value['Value'];
                            $arrRespuesta['r84_cobertura_id'] = $value['Value'];
                            break;
                        case 'MODALIDAD_CONTRATACION':
                            $arrRespuesta['modalidad']                    = $value['Value'];
                            $arrRespuesta['r84_mod_contratacion_pago_id'] = $value['Value'];
                            break;
                        case 'CODIGO_PRESTADOR':
                            $arrRespuesta['r84_cod_prestador'] = $value['Value'];
                            break;
                        case 'TIPO_DOCUMENTO_IDENTIFICACION':
                            $arrRespuesta['tipo_identificacion'] = $this->obtenerTipoDocumentoIdentificacion($value);
                            break;
                        case 'NUMERO_DOCUMENTO_IDENTIFICACION':
                            $arrRespuesta['numero_identificacion'] = $value['Value'];
                            break;
                        case 'PRIMER_APELLIDO':
                            $arrRespuesta['r84_primer_apellido'] = $value['Value'];
                            break;
                        case 'SEGUNDO_APELLIDO':
                            $arrRespuesta['r84_segundo_apellido'] = $value['Value'];
                            break;
                        case 'PRIMER_NOMBRE':
                            $arrRespuesta['r84_primer_nombre'] = $value['Value'];
                            break;
                        case 'SEGUNDO_NOMBRE':
                            $arrRespuesta['r84_segundo_nombre'] = $value['Value'];
                            break;
                        case 'TIPO_USUARIO':
                            $arrRespuesta['r84_tipo_usuario_id'] = $value['Value'];
                            break;
                        case 'NUMERO_AUTORIZACION':
                            $arrRespuesta['r84_numero_autorizacion'] = $value['Value'];
                            break;
                        case 'NUMERO_MIPRES':
                            $arrRespuesta['r84_numero_prescripcion_mipres'] = $value['Value'];
                            break;
                        case 'NUMERO_ENTREGA_MIPRES':
                            $arrRespuesta['r84_id_suministro_mipres'] = $value['Value'];
                            break;
                        case 'NUMERO_CONTRATO':
                            $arrRespuesta['r84_numero_contrato'] = $value['Value'];
                            break;
                        case 'NUMERO_POLIZA':
                            $arrRespuesta['r84_numero_poliza'] = $value['Value'];
                            break;
                        case 'COPAGO':
                            $arrRespuesta['r84_copago'] = $value['Value'];
                            break;
                        case 'CUOTA_MODERADORA':
                            $arrRespuesta['r84_cuota_moderadora'] = $value['Value'];
                            break;
                        case 'CUOTA_RECUPERACION':
                            $arrRespuesta['r84_cuota_recuperacion'] = $value['Value'];
                            break;
                        case 'PAGOS_COMPARTIDOS':
                            $arrRespuesta['r84_pagos_compartidos_pv'] = $value['Value'];
                            break;
                        default:
                            // No aplica condicoin
                            break;
                    }
                }
            }
        }

        if ($aplicaPara == "NC" || $aplicaPara == "ND") {
            $informacionGeneral = array(
                array('campoBaseDatos' => 'valor_total',              'path' => '/cac:' . ($aplicaPara == 'NC' ? 'LegalMonetaryTotal' : 'RequestedMonetaryTotal') . '/cbc:PayableAmount'),  
                array('campoBaseDatos' => 'concepto_movimiento_id',   'path' => '/cac:DiscrepancyResponse/cbc:ResponseCode'),  
                array('campoBaseDatos' => 'factura_autorizada',       'path' => '/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'),
                array('campoBaseDatos' => 'cufe',                     'path' => '/cac:BillingReference/cac:InvoiceDocumentReference/cbc:UUID'),
                array('campoBaseDatos' => 'nit_prestador',            'path' => '/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID'),
                array('campoBaseDatos' => 'numero_nota',              'path' => '/cbc:ID'),
                array('campoBaseDatos' => 'CUDE',                     'path' => '/cbc:UUID'),
                array('campoBaseDatos' => 'fecha_emision_movimiento', 'path' => '/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature/ds:Object/xades:QualifyingProperties/xades:SignedProperties/xades:SignedSignatureProperties/xades:SigningTime'),
                array('campoBaseDatos' => 'valor_inicial',            'path' => '/cac:' . ($aplicaPara == 'NC' ? 'LegalMonetaryTotal' : 'RequestedMonetaryTotal') . '/cbc:LineExtensionAmount'),
                array('campoBaseDatos' => 'impuesto',                 'path' => '/cac:' . ($aplicaPara == 'NC' ? 'LegalMonetaryTotal' : 'RequestedMonetaryTotal') . '/cbc:TaxExclusiveAmount'),
                array('campoBaseDatos' => 'descripcion',              'path' => '/cac:' . ($aplicaPara == 'NC' ? 'CreditNote' : 'DebitNote') . 'Line[1]/cac:Item/cbc:Description'),
            );
        }

        if ($aplicaPara == "NC" || $aplicaPara == "ND") {
            foreach ($informacionGeneral as $informacionCampo) {
                // Se obtiene la información del XPath en el XML
                $valuesXpath = $this->getValueByXpath($xmlUbl, "//{$tipoDocumento}{$informacionCampo['path']}");
                if (!empty($valuesXpath)) {
                    foreach ($valuesXpath as $value) {
                        if ($informacionCampo['campoBaseDatos'] == 'fecha_emision_movimiento') {
                            $fecha = explode('T', $value);
                            $arrRespuesta[$informacionCampo['campoBaseDatos']] = $fecha[0];
                        } else {
                            $arrRespuesta[$informacionCampo['campoBaseDatos']] = $value;
                        }
                    }
                }
            }
        }

        // Campos comunes para todos los documentos
        // Tipo Documento
        $arrRespuesta['tipo_documento'] = $aplicaPara;

        return $arrRespuesta;
    }

    /**
     * Configura la conexión con el servidor SQL de Emssanar.
     *
     * @param array $ofeRecepcionConexionErp Array conteniendo la información de conexión ERP del OFE
     * @return void
     */
    private function setSqlServerConnection(array $ofeRecepcionConexionErp) {
        Config::set('database.connections.emssanar', array(
            'driver'   => 'sqlsrv',
            'host'     => $ofeRecepcionConexionErp['sql_server']['host'],
            'port'     => $ofeRecepcionConexionErp['sql_server']['port'],
            'database' => $ofeRecepcionConexionErp['sql_server']['database'],
            'username' => $ofeRecepcionConexionErp['sql_server']['username'],
            'password' => $ofeRecepcionConexionErp['sql_server']['password'],
            'trust_server_certificate' => true
        ));
    }

    /**
     * Guarda la informacion del documento en la base de datos de emssanar.
     *
     * @param RepCabeceraDocumentoDaop $documento Instancia del documento a enviar por correo
     * @param RepEstadoDocumentoDaop $estTranmisionErp Instancia del estado TRANSMISIONERP
     * @param integer  $inicioMicrotime Timestamp del inciio del proceso
     * @param array    $informacionDocumento Array la informacion del documento
     * 
     * @return void
     */
    private function guardarDocumentoEmssanar($documento, $estTranmisionErp, $inicioMicrotime, $informacionDocumento) {
        // Variable para registro de errores en el proceso
        $errores = [];

        // Variable para registro de mensajes informativos
        $msjInformativo = [];

        // Busando el nombre del archivo del xml-ubl procesado
        if(!empty($documento->cdo_nombre_archivos)) {
            $nombreArchivos = json_decode($documento->cdo_nombre_archivos, true);
            $informacionDocumento['nombre_archivo'] = array_key_exists('xml_ubl', $nombreArchivos) && !empty($nombreArchivos['xml_ubl']) ? $nombreArchivos['xml_ubl'] : $documento->rfa_prefijo . $documento->cdo_consecutivo . '.xml';
        } else {
            $informacionDocumento['nombre_archivo'] = $documento->rfa_prefijo . $documento->cdo_consecutivo . '.xml';
        }

        // Estableciendo Conexion con la base de datos y servidor de Emssanar
        $this->setSqlServerConnection($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_conexion_erp);

        // Guardando Datos del documento electronico segun su clasificacion
        switch ($informacionDocumento['tipo_documento']) {
            case 'FC':
                // Guardando la factura electronica, tabla: factura_electronica

                // Campos a guardar
                // factura_autorizada	            varchar(50)	    Concatenacion de prefijo_factura_autorizada + numero de factura
                // autorizacion_desde	            date	        fecha inicial de autorizacion de numeracion de facturacion electronica
                // autorizacion_hasta	            date	        fecha final de autorizacion de numeracion de facturacion electronica
                // prefijo_factura_autorizada	    varchar(20)	    Prefijo de la factura
                // factura_autorizada_desde	        varchar(20)	    numeracion  inicial de autorizacion de facturacion electronica
                // factura_aurizada_hasta	        varchar(20)	    numeracion  final de autorizacion de facturacion electronica
                // nombre_archivo	                varchar(150)	nombre del documento xml
                // nit_prestador	                varchar(20)	    Nit del prestador 
                // numero_factura	                varchar(30)	    Numero de factura sin prefijo
                // nombre_plano	                    varchar(50)	    nombre del documento xml
                // tipo_identificacion	            char(2)	        campo2 Resolucion 506  tipo de identificacion del usuario al que se le presta el servicio,
                // numero_identificacion	        varchar(20)	    campo3 Resolucion 506 numero de identificacion del usuario al que se le presta el servicio
                // codigo_habilitacion	            varchar(12)	    codigo habilitacion del prestador
                // plan_beneficio	                varchar(max)	Se registra la entidad responsable de financiar la cobertura o plan de beneficios, y de pagar la prestación de los servicios y tecnologías de salud incluidas en la factura de venta
                // tipo_servicio	                int(4)	        se envia null
                // modalidad	                    varchar(max)	se registra la modalidad de contratación y de pago pactaoa objeto de facturación
                // estado_factura_id	            int(4)	        se envia null
                // fecha_proceso	                date	        fecha de lectura y almacenamiento en base de datos Emssanar
                // valor_inicial	                float(8)	    Valor inicial de la factura
                // impuesto	                      float(8)	    impuesto  de la factura
                // valor_factura	                float(8)	    Valor de la factura
                // correo_envio	                    varchar(120)	Correo emisor de la factura
                // CUFE	                            varchar(max)	Codigo unico de facturacion electronica
                // fecha_emision_factura	        date	        Fecha de generacion de la factura
                // r84_cod_prestador	            varchar(20)	    campo1 Resolucion 506 (CODIGO_PRESTADOR)
                // r84_primer_apellido	            varchar(max)	campo4 Resolucion 506 (PRIMER_APELLIDO)
                // r84_segundo_apellido	            varchar(max)	campo5 Resolucion 506 (SEGUNDO_APELLIDO)
                // r84_primer_nombre	            varchar(max)	campo6 Resolucion 506 (PRIMER_NOMBRE)
                // r84_segundo_nombre	            varchar(max)	campo7 Resolucion 506 (SEGUNDO_NOMBRE)
                // r84_tipo_usuario_id	            varchar(max)	campo8 Resolucion 506 (TIPO_USUARIO)
                // r84_mod_contratacion_pago_id	    varchar(max)	campo9 Resolucion 506 (MODALIDAD_CONTRATACION)
                // r84_cobertura_id	                varchar(max)	campo10 Resolucion 506 (COBERTURA_PLAN_BENEFICIOS)
                // r84_numero_autorizacion	        varchar(max)	campo11 Resolucion 506 (NUMERO_AUTORIZACION)
                // r84_numero_prescripcion_mipres	varchar(max)	campo12 Resolucion 506 (NUMERO_MIPRES)
                // r84_id_suministro_mipres	        varchar(max)	campo13 Resolucion 506 (NUMERO_ENTREGA_MIPRES)
                // r84_numero_contrato	            varchar(max)	campo14 Resolucion 506 (NUMERO_CONTRATO)
                // r84_numero_poliza	            varchar(max)	campo15 Resolucion 506 (NUMERO_POLIZA)
                // r84_fecha_inicio_servicio	    date	        Fecha inicio periodo
                // r84_fecha_final_servicio	        date	        Fecha fin periodo
                // r84_copago	                    float(8)	    campo16 Resolucion 506 (COPAGO)
                // r84_cuota_moderadora	            float(8)	    campo17 Resolucion 506 (CUOTA_MODERADORA)
                // r84_cuota_recuperacion	        float(8)	    campo18 Resolucion 506 (CUOTA_RECUPERACION)
                // r84_pagos_compartidos_pv	        float(8)	    campo19 Resolucion 506 (PAGOS_COMPARTIDOS)

                try {
                    $nombreArchivo = explode('.', $informacionDocumento['nombre_archivo']);
                    $informacionDocumento['nombre_plano']   = substr($nombreArchivo[0], 0, 45) . '.' . $nombreArchivo[count($nombreArchivo) - 1];
                    $informacionDocumento['numero_factura'] = str_replace($informacionDocumento['prefijo_factura_autorizada'], '', $informacionDocumento['factura_autorizada']);
                    $informacionDocumento['fecha_proceso']  = date('Y-m-d');
                    $informacionDocumento['tipo_servicio']  = null;
                    
                    if(array_key_exists('r84_cod_prestador', $informacionDocumento))
                        $informacionDocumento['codigo_habilitacion'] = $informacionDocumento['r84_cod_prestador'];


                    //Imprimiendo documento
                    dump($informacionDocumento['tipo_documento']."~".$informacionDocumento['factura_autorizada']."~".$informacionDocumento['CUFE']);
                    
                    // Eliminando la columna tipo_documento 
                    unset($informacionDocumento['tipo_documento']);

                    $existeDocumento = DB::connection('emssanar')
                        ->table('factura_electronica')
                        ->where('CUFE', $informacionDocumento['CUFE'])
                        ->value('id');

                    if(!empty($existeDocumento)) {
                        $msjInformativo[] = 'La Factura con CUFE [' . $informacionDocumento['CUFE'] . '] ya existe en la base de datos de Emssanar';
                    } else {
                        $crearRegistro = DB::connection('emssanar')
                            ->table('factura_electronica')
                            ->insert($informacionDocumento);
    
                        if(!$crearRegistro) 
                            $errores[] = 'No fue posible crear el registro en la base de datos de Emssanar';
                    }
                } catch(\Exception $e) {
                    $errores[] = $e->getMessage();
                    dump($e->getMessage());
                    dump($e->getTraceAsString());
                }
                break;
            default:
                //Imprimiendo documento
                dump($informacionDocumento['tipo_documento']."~".$informacionDocumento['numero_nota']."~".$informacionDocumento['CUDE']);

                // Guardando notas debitos y credito, tabla: movimiento
                $existeDocumento = DB::connection('emssanar')
                    ->table('movimiento')
                    ->where('CUDE', $informacionDocumento['CUDE'])
                    ->value('id');

                if(!empty($existeDocumento)) {
                    $msjInformativo[] = 'La ' . ($informacionDocumento['tipo_documento'] == 'NC' ? 'Nota Crédito' : 'Nota Débito') . ' con CUDE [' . $informacionDocumento['CUDE'] . '] ya existe en la base de datos de Emssanar';
                } else {
                    try {
                        // Inicializando autoincremental del documento referecia
                        $informacionDocumento['factura_electronica_id'] = null;
                        
                        // Validando que la nota credito o debito tenga documento refencia
                        if (array_key_exists('cufe', $informacionDocumento) && !empty($informacionDocumento['cufe'])) {
                            // Buscando el autoincremental del documento referencia en la base de datos de emssanar
                            // En la tabla factura_electronica campo CUFE con lo enviado en $informacionDocumento['cufe']
                            $idFactura = DB::connection('emssanar')
                                ->table('factura_electronica')
                                ->where('CUFE', $informacionDocumento['cufe'])
                                ->value('id');
                            
                            if(empty($idFactura)) {
                                $msjInformativo[] = 'No se encontró el documento referencia con CUFE [' . $informacionDocumento['cufe'] . '] en la base de datos de Emssanar';
                            } else {
                                $informacionDocumento['factura_electronica_id'] = $idFactura;
                            }
                        }

                        // Buscando el autoincremental del concepto de rechazo en la base de datos de emssanar
                        // Para las NC:
                        // Select a la tabla concepto_movimiento por el campo codigo y tipo_movimiento_id = 1, debe enviarse el campo id
                        // Para las ND:
                        // Select a la tabla concepto_movimiento por el campo codigo y tipo_movimiento_id = 2, debe enviarse el campo id
                        // El campo codigo se buscar con el campo $informacionDocumento['concepto_movimiento_id']
                        $idMovimiento = null;
                        if (array_key_exists('concepto_movimiento_id', $informacionDocumento) && !empty($informacionDocumento['concepto_movimiento_id'])) {
                            $idMovimiento = DB::connection('emssanar')
                                ->table('concepto_movimiento')
                                ->when(
                                    $informacionDocumento['tipo_documento'] == 'NC',
                                    function($query) use ($informacionDocumento) {
                                        return $query->where('codigo', $informacionDocumento['concepto_movimiento_id'])
                                            ->where('tipo_movimiento_id', 1);
                                    },
                                    function($query) use ($informacionDocumento) {
                                        return $query->where('codigo', $informacionDocumento['concepto_movimiento_id'])
                                            ->where('tipo_movimiento_id', 2);
                                    }
                                )->value('id');
                        }
                        $informacionDocumento['concepto_movimiento_id'] = !empty($idMovimiento) ? $idMovimiento : (($informacionDocumento['tipo_documento'] == 'NC') ? 6 : 4);

                        // Campos a guardar
                        // factura_electronica_id	  int(8)	      identificador factura electronica
                        // valor_total	            float(8)	    valor total Nota
                        // concepto_movimiento_id	  int(4)	      identificador del movimiento
                        // factura_autorizada	      varchar(50)	  Concatenacion de prefijo_factura_autorizada + numero de factura
                        // nit_prestador	          varchar(20)	  nit prestador
                        // nombre_archivo	          varchar(150)  nombre del documento xml
                        // numero_nota	            varchar(50)	  numero de nota credito o debito
                        // CUDE	                    varchar(max)  requisito de las notas debito o credito
                        // fecha_emision_movimiento	date	        fecha cuando se realizó el movimiento

                        // Cuando se trata de una NC o ND con referencia documento se debe eliminar el índice 'cufe' del array
                        $cufe = null;
                        if (array_key_exists('cufe', $informacionDocumento) && !empty($informacionDocumento['cufe'])) {
                            $cufe = $informacionDocumento['cufe'];
                            unset($informacionDocumento['cufe']);
                        }

                        // Eliminando la columna tipo_documento 
                        $informacionDocumento['tipo_movimiento'] = $informacionDocumento['tipo_documento'] == 'NC' ? 1 : 2;
                        unset($informacionDocumento['tipo_documento']);

                        $crearRegistro = DB::connection('emssanar')
                            ->table('movimiento')
                            ->insert($informacionDocumento);

                        if(!$crearRegistro)
                            $errores[] = 'No fue posible crear el registro en la base de datos de Emssanar';

                        // Obtiene el ID del registro de la Nota que se acaba de crear
                        $idNota = DB::connection('emssanar')
                            ->table('movimiento')
                            ->where('CUDE', $informacionDocumento['CUDE'])
                            ->value('id');

                        // Actualiza la información de la Factura con la información de la Nota
                        if (!empty($informacionDocumento['factura_electronica_id'])) {
                            $datosActualizacion = [
                                'tipo_movimiento'        => $informacionDocumento['tipo_movimiento'],
                                'id_interno_nota'        => (int) $idNota,
                                'numero_nota'            => $informacionDocumento['numero_nota'],
                                'valor_nota'             => (float) $informacionDocumento['valor_total'],
                                'descripcion_movimiento' => $informacionDocumento['descripcion'],
                                'CUDE'                   => $informacionDocumento['CUDE']
                              ];

                            DB::connection('emssanar')
                                ->table('factura_electronica')
                                ->where('id', $informacionDocumento['factura_electronica_id'])
                                ->update($datosActualizacion);
                        }
                    } catch(\Exception $e) {
                        $errores[] = $e->getMessage();
                        dump($e->getMessage());
                        dump($e->getTraceAsString());
                    }
                }

                break;
        }

        if (empty($errores)) {
            $arrRpta   = [];
            $arrRpta[] = [
                'respuesta_erp'        => ['Resultado' => 'Envío exitoso del documento [' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '].'.(!empty($msjInformativo) ? ' '. trim(implode('. ', $msjInformativo)) : '')],
                'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
                'traza'                => null
            ];
    
            $this->actualizaEstadoDocumentoRecepcion(
                $estTranmisionErp->est_id,
                'EXITOSO',
                'FINALIZADO',
                date('Y-m-d H:i:s'),
                number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                $arrRpta,
                'FINALIZADO'
            );
        } else {
            $strMensajeResultado = trim(implode(" | ", $errores));
            $arrRpta   = [];
            $arrRpta[] = [
                'respuesta_erp'        => ['Resultado' => $strMensajeResultado],
                'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
                'traza'                => null
            ];

            $this->actualizaEstadoDocumentoRecepcion(
                $estTranmisionErp->est_id,
                'FALLIDO',
                $strMensajeResultado,
                date('Y-m-d H:i:s'),
                number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                $arrRpta,
                'FINALIZADO'
            );
        }
    }
}
