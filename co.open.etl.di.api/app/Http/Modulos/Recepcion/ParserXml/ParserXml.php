<?php
/**
 * Clase principal del proceso de recepción en openETL.
 * 
 * Agrupa métodos que pueden ser llamados desde cualquier parte del proyecto en donde se importe la clase
 */
namespace App\Http\Modulos\Recepcion\ParserXml;

use App\Traits\DiTrait;
use App\Http\Modulos\Recepcion\Recepcion;
use App\Http\Modulos\Recepcion\RecepcionException;
use App\Http\Modulos\Recepcion\ParserXml\MainParserXml;

class ParserXml extends MainParserXml {
    use DiTrait;
    
    /**
     * Constructor de la clase.
     * 
     * @param string $lote Lote de procesamiento, si se recibe vacio o no se recibe se puede usar el método setLote() para asignar el valor posterior a la inicialización de la clase
     */
    public function __construct(string $lote = '') {
        parent::__construct();

        $this->lote = $lote;
    }

    /**
     * Permite establecer la propiedad $lote (lote de procesamiento) definida en la clase MainParserXml.
     *
     * @param string $lote Lote de procesamiento
     * @return void
     */
    public function setLote(string $lote): void {
        $this->lote = $lote;
    }

    /**
     * Permite obtener el tipo de documento electrónico que se está procesando.
     *
     * @param string $xml Xml-Ubl en procesamiento
     * @return void
     */
    public function definirTipoDocumento($xml) {
        $xml                 = str_replace('xmlns=', 'ns=', $xml);
        $this->xml           = new \SimpleXMLElement($xml, LIBXML_NOERROR);
        $this->tipoDocumento = $this->tipoDocumentoOriginal = $this->xml->getName();
        $this->namespaces    = $this->xml->getDocNamespaces();
        $this->rootNS        = array_search('urn:oasis:names:specification:ubl:schema:xsd:' . $this->tipoDocumentoOriginal . '-2', $this->namespaces);

        $testNode = $this->rootNS . ':' . $this->tipoDocumento;
        if(!empty($this->rootNS) && !empty((string) $this->getValueByXpath("//{$testNode}/cbc:ID")))
            $this->tipoDocumento = $testNode;

        $this->codigoTipoDocumento = ($this->tipoDocumento == "CreditNote") ? (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:CreditNoteTypeCode") :
            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:InvoiceTypeCode");
    }

    /**
     * Procesa un archivo Xml-Ubl.
     * 
     * @param string $origen Origen del procesamiento del documento
     * @param string $xml Contenido en base64 del archivo Xml-Ubl a procesar
     * @param string|null $pdf Contenido en base64 del archivo PDF a procesar
     * @param string $nombreArchivosXmlPdf Nombre de los archivos xml y pdf que se encuentra en procesamiento
     * @param string|null $ofeRuta Nombre de la carpeta donde se encuentra el archivo zip que se esta procesando
     * @return array Array con dos posiciones, procesado y cdo_id
     */
    public function Parser(string $origen, string $xml, $pdf = null, string $nombreArchivosXmlPdf, $ofeRuta = null): array {
        try {
            $inicioProceso   = date('Y-m-d H:i:s');
            $tsInicioProceso = microtime(true);

            $xmlOriginal = $xml;
            $xml         = base64_decode($xml);
            $this->definirTipoDocumento($xml);

            if($this->tipoDocumentoOriginal == 'AttachedDocument') {
                // Obtiene el xml-ubl dentro del attached document para poder continuar con el procesamiento en este método
                $xml = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:Attachment/cac:ExternalReference/cbc:Description");
                $this->definirTipoDocumento($xml);
            }

            $cdo_clasificacion = $this->cdoClasificacion();
            if ($cdo_clasificacion != "") {
                $id = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:ID");

                // Proveedor
                $pro_identificacion = ($cdo_clasificacion == 'DS' || $cdo_clasificacion == 'DS_NC') ?
                    (
                        (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID") ?
                            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID") :
                            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID")
                    ) :
                    (
                        (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID") ?
                            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID") :
                            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID")
                    );

                // OFE
                $ofe_identificacion = ($cdo_clasificacion == 'DS' || $cdo_clasificacion == 'DS_NC') ? 
                    (
                        (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID") ?
                            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID") :
                            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID")
                    ) :
                    (
                        (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID") ?
                            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID") :
                            (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID")
                    );

                // Verifica que la identificación del Adquirente (OFE en openETL) corresponda con el dato en el nombre de la carpeta que se esta procesando
                if($ofeRuta != null && $ofeRuta != $ofe_identificacion)
                    throw new \Exception('La identificación del OFE [' . $ofe_identificacion . '] no corresponde con la registrada en la carpeta de entrada [' . $ofeRuta . ']');

                // Verifica que exista el OFE y que se encuentre activo
                $ofeId = $this->existeOfe($ofe_identificacion);
                if($ofeId == '')
                    throw new RecepcionException(
                        'En el Documento [' . $id . '], el OFE [' . $ofe_identificacion . '] No existe o su estado es INACTIVO.',
                        422,
                        null,
                        $nombreArchivosXmlPdf
                    );

                // Se obtienen las variables del sistema tenant que indican si de puede crear/actualizar un proveedor
                $crearActualizarProveedor = $this->variablesTenantCrearActualizarProveedor();

                // Verifica si el proveedor existe en el sistema para validar frente a variables del sistema si se debe crear/actualizar
                $existeProveedorId = $this->existeProveedor($ofeId, $pro_identificacion);

                //Varibale que indica si se debe crear o actualizar el Proveedor
                //Se incluye esta variable para el caso en donde el proveedor exista y no se tenga permisos de actualizacion
                $procesarProveedor = false;

                if($existeProveedorId == '' && $crearActualizarProveedor['crearProveedor']) {
                    $procesarProveedor = true;
                    $metodoHttp        = 'POST';
                    $endpoint          = 'configuracion/proveedores';
                } elseif($existeProveedorId != '' && $crearActualizarProveedor['actualizarProveedor']) {
                    $procesarProveedor = true;
                    $metodoHttp        = 'PUT';
                    $endpoint          = 'configuracion/proveedores/' . $ofe_identificacion . '/' . $pro_identificacion;
                } elseif($existeProveedorId == '' && !$crearActualizarProveedor['crearProveedor']) {
                    throw new RecepcionException(
                        'Documento [' . $id . '], Proveedor [' . $pro_identificacion . ']: No existe, su estado es INACTIVO o no tiene habilitado el permiso correspondiente a creación de proveedores.',
                        422,
                        null,
                        $nombreArchivosXmlPdf
                    );
                } elseif($existeProveedorId != '' && !$crearActualizarProveedor['actualizarProveedor']) {
                    //El proveedor existe y no se actualiza, porque el usuario no tiene permiso de actualizacion
                    $proId = $existeProveedorId ;
                }

                // Procesa la información del proveedor para validar si existe y crearlo/actualizarlo si las opciones están configuradas
                if ($procesarProveedor) {
                    $proId = $this->procesarProveedor($metodoHttp, $endpoint, $this->obtenerDatosPersona($ofe_identificacion, $pro_identificacion), $nombreArchivosXmlPdf);
                }

                $nombreArchivos['xml_ubl'] = $nombreArchivosXmlPdf . '.xml';
                if(!empty($pdf))
                    $nombreArchivos['pdf'] = $nombreArchivosXmlPdf . '.pdf';

                // Obtiene el array con la información del documento electrónico
                $documento = $this->obtenerDatosDocumento($origen, $cdo_clasificacion, $ofeId, ($existeProveedorId != '') ? $existeProveedorId : $proId);
                $documento['cdo_nombre_archivos'] = json_encode($nombreArchivos);

                // Crea/Actualiza el documento en el sistema (solo se actualiza si el documento existe con ESTADO INACTIVO)
                $documentoRecepcion = $this->procesarDocumentoRecepcion($documento, $origen);

                if(!empty($pdf))
                    $archivoPdf  = $this->guardarArchivoEnDisco($ofe_identificacion, $documentoRecepcion, 'recepcion', 'rg', 'pdf', $pdf, 'rg_' . $nombreArchivosXmlPdf . '.pdf');
                
                $archivoJson = $this->guardarArchivoEnDisco($ofe_identificacion, $documentoRecepcion, 'recepcion', 'json', 'json', base64_encode(json_encode($documento)));
                $archivoXml  = $this->guardarArchivoEnDisco($ofe_identificacion, $documentoRecepcion, 'recepcion', 'xml', 'xml', $xmlOriginal, 'xml_' . $nombreArchivosXmlPdf . '.xml');

                // Crea el estado RDI correspondiente
                $estadoInformacionAdicional = [
                    'documento'   => $nombreArchivosXmlPdf,
                    'consecutivo' => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:ID"),
                    'est_archivo' => 'rg_' . $nombreArchivosXmlPdf . '.pdf',
                    'est_xml'     => 'xml_' . $nombreArchivosXmlPdf . '.xml',
                    'est_json'    => $archivoJson
                ];

                if(!empty($this->errors))
                    $estadoInformacionAdicional['inconsistencia'] = $this->errors;

                Recepcion::creaNuevoEstadoDocumentoRecepcion(
                    $documentoRecepcion->cdo_id,
                    'RDI',
                    'EXITOSO',
                    $inicioProceso,
                    date('Y-m-d H:i:s'),
                    number_format((microtime(true) - $tsInicioProceso), 3, '.', ''),
                    null,
                    null,
                    $estadoInformacionAdicional,
                    'FINALIZADO'
                );

                return [
                    'procesado' => true,
                    'cdo_id'    => $documentoRecepcion->cdo_id
                ];
            } else {
                if ($this->tipoDocumentoOriginal == 'ApplicationResponse') {
                    // Se indica que es un application response y se pasa el archivo a fallidos
                    // No se hace nada
                    // dump('No se procesa, documento ApplicationResponse');
                } else {
                    throw new RecepcionException(
                        'No fue posible determinar la clasificación del documento [cdo_clasificacion]',
                        422,
                        null,
                        $nombreArchivosXmlPdf
                    );
                }
            }
        } catch (\Exception $e) {
            throw new RecepcionException(
                $e->getMessage(),
                422,
                null,
                $nombreArchivosXmlPdf
            );
        }
    }
}