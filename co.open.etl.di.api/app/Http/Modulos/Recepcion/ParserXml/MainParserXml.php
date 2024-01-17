<?php
/**
 * Calse principal del proceso Parser.
 * 
 * Agrupa métodos que pueden ser llamados desde la clase de procesamiento
 */
namespace App\Http\Modulos\Recepcion\ParserXml;

use Illuminate\Support\Carbon;
use App\Http\Modulos\Recepcion\Recepcion;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\Radian\RadianException;
use App\Http\Modulos\Recepcion\RecepcionException;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaop;

class MainParserXml extends Recepcion {
    use PackageMainTrait;

    /**
     * XML en procesamiento
     *
     * @var object
     */
    public $xml = null;

    /**
     * Lote de procesamiento
     *
     * @var string
     */
    public $lote = null;

    public function __construct() {
        parent::__construct();
    }

    /**
     * Registra los namespaces obligatorios de un documento electrónico.
     * 
     * En el proceso se tiene en cuenta cualquier otro namespace que pudiera existir.
     *
     * @return void
     */
    public function registrarNamespaces() {
        $namespaces = $this->namespaces;

        // Se registran los namespaces que deberían estar incluidos en el documento para poder procesarlo
        if(!empty($this->rootNS)) {
            $this->xml->registerXPathNamespace($this->rootNS, $this->namespaces[$this->rootNS]);
            unset($namespaces[$this->rootNS]);
        }

        if(array_key_exists('cac', $this->namespaces))
            $this->xml->registerXPathNamespace('cac', $this->namespaces['cac']);
        else
            $this->xml->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        unset($namespaces['cac']);

        if(array_key_exists('cbc', $this->namespaces))
            $this->xml->registerXPathNamespace('cbc', $this->namespaces['cbc']);
        else
            $this->xml->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        unset($namespaces['cbc']);

        if(array_key_exists('ext', $this->namespaces))
            $this->xml->registerXPathNamespace('ext', $this->namespaces['ext']);
        else
            $this->xml->registerXPathNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        unset($namespaces['ext']);

        if(array_key_exists('sts', $this->namespaces))
            $this->xml->registerXPathNamespace('sts', $this->namespaces['sts']);
        else
            $this->xml->registerXPathNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');

        unset($namespaces['sts']);

        if(array_key_exists('xades', $this->namespaces))
            $this->xml->registerXPathNamespace('xades', $this->namespaces['xades']);
        else
            $this->xml->registerXPathNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

        unset($namespaces['xades']);

        if(array_key_exists('xades141', $this->namespaces))
            $this->xml->registerXPathNamespace('xades141', $this->namespaces['xades141']);
        else
            $this->xml->registerXPathNamespace('xades141', 'http://uri.etsi.org/01903/v1.4.1#');

        unset($namespaces['xades141']);

        if(array_key_exists('ds', $this->namespaces))
            $this->xml->registerXPathNamespace('ds', $this->namespaces['ds']);
        else
            $this->xml->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        unset($namespaces['ds']);

        if(array_key_exists('xsi', $this->namespaces))
            $this->xml->registerXPathNamespace('xsi', $this->namespaces['xsi']);
        else
            $this->xml->registerXPathNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');    

        unset($namespaces['xsi']);

        foreach($namespaces as $indice => $namespace) {
            $this->xml->registerXPathNamespace($indice, $namespaces[$indice]);
        }
    }

    /**
     * Obtiene el valor de un nodo dado su XPATH
     *
     * @param string $xpath
     * @return SimpleXMLElement|null
     */
    public function getValueByXpath(string $xpath) {
        try {
            $obj = $this->xml->xpath($xpath);
            if ($obj) {
                return trim($obj[0]);
            }

            return null;
        } catch (\Exception $e) {
            $this->errors[] = 'Estructura inválida, los namespaces no se encuentran definidos correctamente. [' . $e->getMessage() . '] - Nodo [' . $xpath . ']';
            $this->registrarNamespaces();

            // Se intenta obtener nuevamente el nodo
            $obj = $this->xml->xpath($xpath);
            if ($obj) {
                return trim($obj[0]);
            }

            return null;
        }
    }

    /**
     * Obtiene el valor de un attributo en un nodo XML
     *
     * @param string $nodo
     * @param string $atributo
     * @return string
     */
    public function getAttributeByXpath(string $nodo, string $atributo) {
        $cdo_clasificacion = $this->cdoClasificacion();

        try {
            $obj = $this->xml->xpath($nodo);
            if ($obj) {
                if(!empty($obj[0][$atributo])) {
                    return trim($obj[0][$atributo]);
                } else {
                    $retornaError = true;
                    if (strpos($nodo, 'cac:PaymentMeans') !== false)
                        $retornaError = false;

                    if (($cdo_clasificacion == "DS" || $cdo_clasificacion == "DS_NC") && strpos($nodo, 'cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode') !== false)
                        $retornaError = false;

                    if($retornaError)
                        $this->errors[] = 'Estructura invalida, no se encontró el valor del atributo - Nodo [' . $nodo . '] - Atributo [' . $atributo . ']';

                    return null;
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->errors[] = 'Estructura invalida, los namespaces no se encuentran definidos correctamente. [' . $e->getMessage() . '] - Nodo [' . $nodo . '] - Atributo [' . $atributo . ']';
            $this->registrarNamespaces();  
            
            // Se intenta obtener nuevamente el atributo
            $obj = $this->xml->xpath($nodo);
            if ($obj) {
                if(!empty($obj[0][$atributo])) {
                    return trim($obj[0][$atributo]);
                } else {
                    $retornaError = true;
                    if (strpos($nodo, 'cac:PaymentMeans') !== false)
                        $retornaError = false;

                    if (($cdo_clasificacion == "DS" || $cdo_clasificacion == "DS_NC") && strpos($nodo, 'cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode') !== false)
                        $retornaError = false;

                    if($retornaError)
                        $this->errors[] = 'Estructura invalida, No se encontró el valor del atributo - Nodo [' . $nodo . '] - Atributo [' . $atributo . ']';

                    return null;
                }
            }

            return null;
        }
    }

    /**
     * Obtiene los datos de un Proveedor, Oferente o Adquirente en un documento electrónico.
     *
     * @param string  $identificacionEmisor   Identificacion del Emisor
     * @param string  $identificacionReceptor Identificacion del Receptor
     * @param bool $radian Identifica si se usa para Radian
     * @param bool $dataAdq Identifica si se usa para Radian y obtiene los datos de los nodos del ADQ
     * @return array  $proveedor|$oferente|$adquirente Array que contiene la información del proveedor y que permitirá su creación/actualización
     */
    public function obtenerDatosPersona(string $identificacionEmisor, string $identificacionReceptor, bool $radian = false, bool $dataAdq = false): array {
        $proveedor  = [];
        $adquirente = [];
        $cdo_clasificacion = $this->cdoClasificacion();

        $nodo = '';
        if (($cdo_clasificacion == 'DS' || $cdo_clasificacion == 'DS_NC') || ($radian && $dataAdq))
            $nodo = 'AccountingCustomerParty';
        else
            $nodo = 'AccountingSupplierParty';

        $toj_codigo = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cbc:AdditionalAccountID");
        if($toj_codigo == '1') {
            $razonSocial     = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:RegistrationName");
            $nombreComercial = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyName/cbc:Name");
            if(empty($nombreComercial))
                $nombreComercial = $razonSocial;

            $primerNombre    = '';
            $otrosNombres    = '';
            $primerApellido  = '';
            $segundoApellido = '';
        } else {
            $razonSocial     = '';
            $nombreComercial = '';
            $primerNombre    = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:Person/cbc:FirstName");
            $otrosNombres    = '';
            $primerApellido  = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:Person/cbc:FamilyName");
            $segundoApellido = '';

            if(empty($primerNombre) && empty($primerApellido)) {
                $personaNatural = explode(' ', (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:RegistrationName"), 2);
                $primerNombre   = array_key_exists(0, $personaNatural) ? $personaNatural[0] : '';
                $primerApellido = array_key_exists(1, $personaNatural) ? $personaNatural[1] : '';
            }

            if(empty($primerNombre) || empty($primerApellido)) {
                $personaNatural = explode(' ', (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyName/cbc:Name"), 2);
                $primerNombre   = array_key_exists(0, $personaNatural) ? $personaNatural[0] : '';
                $primerApellido = array_key_exists(1, $personaNatural) ? $personaNatural[1] : '';
            }
        }

        //Si el Codigo Postal contiene mas de 10 caractres, se omite el envio del campo
        $postalZone = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cbc:PostalZone");
        if (strlen($postalZone) > 10) {
            $postalZone = '';
        }

        $postalZoneFiscal = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cbc:PostalZone");
        if (strlen($postalZoneFiscal) > 10) {
            $postalZoneFiscal = '';
        }

        $responsabilidadesFiscales = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode");

        // El separador permitido para el correo del ofe es la coma (,)
        // algunos PT estan enviando punto y coma (;)
        // por lo que se hace necesario reemplazar el caracter
        // Cuando el proveedor envia un correo que no contiene el caracter @ 
        // se debe guardar vacio en la plataforma
        // se debe procesar porque la DIAN valida documentos con correos no validos
        $emails = explode(',', str_replace(";", ",", (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:Contact/cbc:ElectronicMail")));
        // Recorre los emails para validarlo uno a uno
        $correosValidos = [];
        foreach ($emails as $email) {
            $validatorEmail = $this->validationEmailRule(trim($email));
            if (empty($validatorEmail['errors']))
                $correosValidos[] = trim($email);
        }
        $correo = implode(",",$correosValidos);
        $correo = (substr_count($correo, "@") > 0) ? $correo : null;

        if(!$radian && !$dataAdq) {
            $proveedor = [
                'ofe_identificacion'             => $identificacionEmisor,
                'pro_identificacion'             => $identificacionReceptor,
                'toj_codigo'                     => $toj_codigo,
                'tdo_codigo'                     => (
                                                    (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:CompanyID", "schemeName") ?
                                                    (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:CompanyID", "schemeName") :
                                                    (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyLegalEntity/cbc:CompanyID", "schemeName")
                                                ),
                'pro_razon_social'               => $razonSocial,
                'pro_nombre_comercial'           => $nombreComercial,
                'pro_primer_apellido'            => $primerApellido,
                'pro_segundo_apellido'           => $segundoApellido,
                'pro_primer_nombre'              => $primerNombre,
                'pro_otros_nombres'              => $otrosNombres,
                'pai_codigo'                     => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cac:Country/cbc:IdentificationCode"),
                'dep_codigo'                     => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cbc:CountrySubentityCode"),
                'mun_codigo'                     => substr((string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cbc:ID"), 2, 3),
                'cpo_codigo'                     => $postalZone,
                'pro_direccion'                  => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cac:AddressLine/cbc:Line"),
                'pro_telefono'                   => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:Contact/cbc:Telephone"),
                'pai_codigo_domicilio_fiscal'    => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:Country/cbc:IdentificationCode"),
                'dep_codigo_domicilio_fiscal'    => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cbc:CountrySubentityCode"),
                'mun_codigo_domicilio_fiscal'    => substr((string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cbc:ID"), 2, 3),
                'cpo_codigo_domicilio_fiscal'    => $postalZoneFiscal,
                'pro_direccion_domicilio_fiscal' => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line"),
                'pro_correo'                     => $correo,
                'rfi_codigo'                     => (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode", "listName"),
                'ref_codigo'                     => !empty($responsabilidadesFiscales) ? explode(';', $responsabilidadesFiscales) : [],
                'pro_matricula_mercantil'        => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:Name"),
                'origen'                         => 'procesamiento_documentos'
            ];
            return $proveedor;
        } elseif($radian && !$dataAdq) {
            $oferente = [
                'ofe_identificacion'        => $identificacionEmisor,
                'ofe_razon_social'          => ($razonSocial !== '') ? $razonSocial : $primerNombre.' '.$primerApellido,
                'ofe_nombre_comercial'      => $nombreComercial,
                'ofe_otros_nombres'         => $otrosNombres,
                'origen'                    => 'procesamiento_documentos',
                'ofe_informacion_adicional' => $this->buildObjectInformacionAdicional($nodo, $toj_codigo, $postalZone, $postalZoneFiscal, $correo, $responsabilidadesFiscales)
            ];

            return $oferente;
        } elseif ($radian && $dataAdq) {
            $adquirente = [
                'adq_identificacion'        => $identificacionReceptor,
                'adq_razon_social'          => ($razonSocial !== '') ? $razonSocial : $primerNombre.' '.$primerApellido,
                'adq_nombre_comercial'      => $nombreComercial,
                'adq_otros_nombres'         => $otrosNombres,
                'origen'                    => 'procesamiento_documentos',
                'adq_informacion_adicional' => $this->buildObjectInformacionAdicional($nodo, $toj_codigo, $postalZone, $postalZoneFiscal, $correo, $responsabilidadesFiscales)
            ];

            return $adquirente;
        }
    }

    /**
     * Obtiene los documentos referencia dentro de un XML-UBL
     *
     * @param string $cdoClasificacion Clasificacion del documento electrónico que se esta procesando
     * @param string $docOrigen Clasificación de documento contra el que se requiere comparar
     * @param string $tipoDocReferencia Clasificación de documento sobre el cual se buscarán los documentos referencia
     * @param array $documentosReferencia Array conteniendo la información relacionada con los documentos referencia
     * @return void
     */
    public function getDocumentReference($cdoClasificacion, $docOrigen, $tipoDocReferencia, &$documentosReferencia) {
        if($cdoClasificacion == $docOrigen) {
            switch($tipoDocReferencia) {
                case 'FC':
                case 'DS':
                    $nodo = 'Invoice';
                    break;
                case 'NC':
                    $nodo = 'CreditNote';
                    break;
                case 'ND':
                    $nodo = 'DebitNote';
                    break;
            }
            if(!empty($this->xml->xpath("//{$this->tipoDocumento}/cac:BillingReference[1]/cac:{$nodo}DocumentReference/cbc:ID"))) {
                for($i = 1; $i <= count($this->xml->xpath("//{$this->tipoDocumento}/cac:BillingReference")); $i++) {
                    $documentosReferencia[] = [
                        'clasificacion' => $tipoDocReferencia,
                        'prefijo'       => '',
                        'consecutivo'   => isset($this->xml->xpath("//{$this->tipoDocumento}/cac:BillingReference[{$i}]/cac:{$nodo}DocumentReference/cbc:ID")[0]) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:BillingReference[{$i}]/cac:{$nodo}DocumentReference/cbc:ID")[0] : '',
                        'cufe'          => isset($this->xml->xpath("//{$this->tipoDocumento}/cac:BillingReference[{$i}]/cac:{$nodo}DocumentReference/cbc:UUID")[0]) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:BillingReference[{$i}]/cac:{$nodo}DocumentReference/cbc:UUID")[0] : '',
                        'fecha_emision' => isset($this->xml->xpath("//{$this->tipoDocumento}/cac:BillingReference[{$i}]/cac:{$nodo}DocumentReference/cbc:IssueDate")[0]) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:BillingReference[{$i}]/cac:{$nodo}DocumentReference/cbc:IssueDate")[0] : '',
                    ];
                }
            }
        }
    }

    /**
     * Obtiene la información sobre identificadores de pago de un Xml-Ubl.
     *
     * @param int $indicePaymentMeans Indice del nodo PaymentMeans que se esta procesando
     * @return array|null $identificadoresPago Array conteniendo la información de identificadores de pago
     */
    public function getMenIdentificadorPago(int $indicePaymentMeans) {
        $identificadoresPago = [];
        if(!empty($this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$indicePaymentMeans}]/cbc:PaymentID[1]"))) {
            for($i = 1; $i <= count($this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$indicePaymentMeans}]/cbc:PaymentID")); $i++) {
                $identificadoresPago[] = [
                    'id' => isset($this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$indicePaymentMeans}]/cbc:PaymentID[{$i}]")[0]) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$indicePaymentMeans}]/cbc:PaymentID[{$i}]")[0] : null
                ];
            }
        }

        return !empty($identificadoresPago) ? $identificadoresPago : null;
    }

    /**
     * Obtiene los datos del documento electrónico
     *
     * @param string  $origen Origen de procesamiento del documento electrónico
     * @param string  $cdo_clasificacion Clasificacion del documento electrónico
     * @param string  $id_actor_ofe ID del OFE o del ACTOR
     * @param string  $pro_id ID del Proveedor
     * @param bool $radian Identifica si viene desde Radian la ejecución
     * @return array  Array que contiene la información del documento y de documentos adicionales
     */
    public function obtenerDatosDocumento(string $origen, string $cdo_clasificacion, string $id_actor_ofe, string $pro_id, bool $radian = false): array {
        $id              = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:ID");
        $rfa_resolucion  = ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS') ?
            (string) $this->getValueByXpath("//{$this->tipoDocumento}/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:InvoiceControl/sts:InvoiceAuthorization") :
            null;
        $rfa_prefijo     = ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS') ?
            (string) $this->getValueByXpath("//{$this->tipoDocumento}/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:InvoiceControl/sts:AuthorizedInvoices/sts:Prefix") :
            '';

        if(($rfa_prefijo != '' && $rfa_prefijo != null))
            $cdo_consecutivo = substr($id, 0, strpos($id, $rfa_prefijo)) . '' . substr($id, strpos($id, $rfa_prefijo) + strlen($rfa_prefijo));
        else
            $cdo_consecutivo = $id;

        if($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC')
            $tde_id = $this->obtieneDatoParametrico($this->parametricas['tipoDocumentoElectronico'], 'tde_codigo', (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:{$this->tipoDocumentoOriginal}TypeCode"), 'tde_id');
        elseif($cdo_clasificacion == 'ND')
            $tde_id = $this->obtieneDatoParametrico($this->parametricas['tipoDocumentoElectronico'], 'tde_codigo', '92', 'tde_id');

        if($tde_id == null) {
            $this->errors[] = 'El código [' . (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:{$this->tipoDocumentoOriginal}TypeCode") . '] no existe en la paramétrica [tipoDocumentoElectronico]';
        }

        $top_id = $this->obtieneDatoParametrico($this->parametricas['tipoOperacion'], 'top_codigo', (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:CustomizationID"), 'top_id');
        if($top_id == null)
            $this->errors[] = 'El código [' . (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:CustomizationID") . '] no existe en la paramétrica [tipoOperacion]';
        
        $cdo_cufe = ((string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:UUID")) ? (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:UUID") : null;

        if(!$radian)
            // Verifica que el documento no exista para el tde_id, ofe_id, pro_id, rfa_prefijo, cdo_consecutivo, cdo_cufe (llave única)
            $documentoExiste = RepCabeceraDocumentoDaop::select(['cdo_id', 'estado'])
                ->where('tde_id', $tde_id)
                ->where('ofe_id', $id_actor_ofe)
                ->where('pro_id', $pro_id);
        else 
            // Verifica que el documento no exista para el tde_id, act_id, top_id, rfa_prefijo, cdo_consecutivo, cdo_cufe (llave única)
            $documentoExiste = RadianCabeceraDocumentoDaop::select(['cdo_id', 'estado'])
            ->where('tde_id', $tde_id)
            ->where('top_id', $top_id)
            ->where('act_id', $id_actor_ofe);
            

        $documentoExiste = $documentoExiste->where('cdo_consecutivo', $cdo_consecutivo)
            ->where(function($query) use ($rfa_prefijo) {
                if($rfa_prefijo == '' || $rfa_prefijo == null)
                    $query->whereRaw("(rfa_prefijo IS NULL OR rfa_prefijo = '')");
                else
                    $query->where('rfa_prefijo', $rfa_prefijo);
            })
            ->with([
                'getAcuseRecibo:est_id,cdo_id',
                'getReciboBien:est_id,cdo_id',
                'getAceptado:est_id,cdo_id',
                'getAceptadoT:est_id,cdo_id',
                'getRechazado:est_id,cdo_id'
            ])
            ->where('cdo_cufe', $cdo_cufe)
            ->first();

        $modulo               = (!$radian) ? 'Recepción' : 'Radian';
        $textoEstadoActivo    = 'El documento [' . $rfa_prefijo . $cdo_consecutivo . '] existe con estado ACTIVO en openETL - '.$modulo.', para poder actualizarlo su estado debe ser INACTIVO';
        $textoEstadoInactivo  = 'El documento [' . $rfa_prefijo . $cdo_consecutivo . '] no puede ser actualizado debido a que ya se registraron eventos en la DIAN';
        $arrPrefijoConsecutivo = ['cdo_prefijo' => $rfa_prefijo,'cdo_consecutivo' => $cdo_consecutivo];

        if($documentoExiste && $documentoExiste->estado == 'ACTIVO' && !$radian) {
            throw new RecepcionException(
                $textoEstadoActivo,
                409,
                null,
                $arrPrefijoConsecutivo
            );
        } elseif ($documentoExiste && $documentoExiste->estado == 'ACTIVO' && $radian) {
            throw new RadianException(
                $textoEstadoActivo,
                409,
                null,
                $arrPrefijoConsecutivo
            );
        }

        if(
            $documentoExiste && $documentoExiste->estado == 'INACTIVO' && 
            (
                $documentoExiste->getAcuseRecibo || $documentoExiste->getReciboBien || $documentoExiste->getAceptado || $documentoExiste->getAceptadoT || $documentoExiste->getRechazado
            )
        ) {
            if(!$radian)
                throw new RecepcionException(
                    $textoEstadoInactivo,
                    409,
                    null,
                    $arrPrefijoConsecutivo
                );
            else
                throw new RadianException(
                    $textoEstadoInactivo,
                    409,
                    null,
                    $arrPrefijoConsecutivo
                );
        }

        // Hora del documento
        $cdo_hora = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:IssueTime");
        if(strstr($cdo_hora, '-'))
            list($cdo_hora, $timezone) = explode('-', $cdo_hora);

        // Fecha de vencimiento
        $cdo_vencimiento = null;
        if($cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') {
            $cdo_vencimiento = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentMeans[0]/cbc:PaymentDueDate");
        } else {
            $cdo_vencimiento = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:DueDate");
            if($cdo_vencimiento == '')
                $cdo_vencimiento = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentMeans[0]/cbc:PaymentDueDate");
        }

        if(empty($cdo_vencimiento))
            $this->errors[] = 'Documento sin fecha de vencimiento';

        // Medios y formas de pago
        $mediosPago = [];
        for($i = 1; $i <= count($this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans")); $i++) {
            $fpa_codigo = isset($this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:ID")[0]) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:ID")[0] : null;
            $fpa_id = $this->obtieneDatoParametrico($this->parametricas['formasPago'], 'fpa_codigo', $fpa_codigo, 'fpa_id');
            if(empty($fpa_id))
                $this->errors[] = 'El código [' . $fpa_codigo . '] no existe en la paramétrica [formas de pago]';
            
            $mpa_codigo = isset($this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:PaymentMeansCode")[0]) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:PaymentMeansCode")[0] : null;
            $mpa_id = $this->obtieneDatoParametrico($this->parametricas['mediosPago'], 'mpa_codigo', $mpa_codigo, 'mpa_id');
            if(empty($mpa_id))
                $this->errors[] = 'El código [' . $mpa_codigo . '] no existe en la paramétrica [medios de pago]';

            $mpa_informacion_adicional = [];
            if(!empty((string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:ID", "schemeID")))
                $mpa_informacion_adicional['atributo_fpa_cpdigo_id'] = (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:ID", "schemeID");
            if(!empty((string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:ID", "schemeName")))
                $mpa_informacion_adicional['atributo_fpa_codigo_name'] = (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:ID", "schemeName");

            if(!empty($fpa_id) && !empty($fpa_id)) {
                $identificadorPago   = $this->getMenIdentificadorPago($i);
                $menFechaVencimiento = isset($this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:PaymentDueDate")[0]) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:PaymentMeans[{$i}]/cbc:PaymentDueDate")[0] : null;

                if($fpa_codigo == '1' && empty($menFechaVencimiento))
                    $menFechaVencimiento = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:IssueDate");
                elseif($fpa_codigo == '2' && empty($menFechaVencimiento))
                    $menFechaVencimiento = Carbon::parse((string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:IssueDate"))->addDays(30)->format('Y-m-d');

                $mediosPago[] = [
                    'fpa_id'                    => $fpa_id,
                    'mpa_id'                    => $mpa_id,
                    'men_fecha_vencimiento'     => $menFechaVencimiento,
                    'men_identificador_pago'    => !empty($identificadorPago) ? json_encode($identificadorPago) : null,
                    'mpa_informacion_adicional' => !empty($mpa_informacion_adicional) ? json_encode($mpa_informacion_adicional) : null
                ];
            }
        }

        // Observaciones del documento
        $observaciones = [];
        foreach($this->xml->xpath("//{$this->tipoDocumento}/cbc:Note") as $index => $value) {
            $observaciones[] = (string) $value;
        }

        // Documentos Referencia
        $documentosReferencia = [];
        $this->getDocumentReference($cdo_clasificacion, 'FC', 'NC', $documentosReferencia);
        $this->getDocumentReference($cdo_clasificacion, 'FC', 'ND', $documentosReferencia);
        $this->getDocumentReference($cdo_clasificacion, 'NC', 'FC', $documentosReferencia);
        $this->getDocumentReference($cdo_clasificacion, 'ND', 'FC', $documentosReferencia);
        $this->getDocumentReference($cdo_clasificacion, 'DS_NC', 'DS', $documentosReferencia);

        // Conceptos de corrección
        $conceptosCorreccion = [];
        if($cdo_clasificacion == 'NC' || $cdo_clasificacion == 'ND' || $cdo_clasificacion == 'DS_NC') {
            for($i = 1; $i <= count($this->xml->xpath("//{$this->tipoDocumento}/cac:DiscrepancyResponse")); $i++) {
                // Observaciones de la corrección
                $observacionesCorreccion = [];
                foreach($this->xml->xpath("//{$this->tipoDocumento}/cac:DiscrepancyResponse[{$i}]/cbc:Description") as $index => $value) {
                    $observacionesCorreccion[] = (string) $value;
                }

                $conceptosCorreccion[] = [
                    'id_referencia'              => (array_key_exists('0', $this->xml->xpath("//{$this->tipoDocumento}/cac:DiscrepancyResponse[{$i}]/cbc:ReferenceID"))) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:DiscrepancyResponse[{$i}]/cbc:ReferenceID")[0] : '',
                    'cco_codigo'                 => (array_key_exists('0', $this->xml->xpath("//{$this->tipoDocumento}/cac:DiscrepancyResponse[{$i}]/cbc:ResponseCode"))) ? (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:DiscrepancyResponse[{$i}]/cbc:ResponseCode")[0] : '',
                    'cdo_observacion_correccion' => $observacionesCorreccion
                ];
            }
        }

        $mon_id = $this->obtieneDatoParametrico($this->parametricas['moneda'], 'mon_codigo', (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:DocumentCurrencyCode"), 'mon_id');
        if($mon_id == null) {
            $this->errors[] = 'El código [' . (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:DocumentCurrencyCode") . '] no existe en la paramétrica [moneda]';
        }

        $mon_id_extranjera = '';
        if((string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentExchangeRate/cbc:TargetCurrencyCode") != '') {
            $mon_id_extranjera = $this->obtieneDatoParametrico($this->parametricas['moneda'], 'mon_codigo', (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentExchangeRate/cbc:TargetCurrencyCode"), 'mon_id');
            if($mon_id_extranjera == null) {
                $this->errors[] = 'El código [' . (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentExchangeRate/cbc:TargetCurrencyCode") . '] no existe en la paramétrica [moneda] para moneda extranjera';
            }
        }

        $cdo_impuestos = 0;
        if($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') {
            $cdo_impuestos = ((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount")) - ((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:LineExtensionAmount"));
        } else {
            $cdo_impuestos = ((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:TaxInclusiveAmount")) - ((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:LineExtensionAmount"));
        }

        $documento = [
            'cdo_origen'                 => $origen,
            'cdo_clasificacion'          => $cdo_clasificacion,
            'tde_id'                     => $tde_id ? (string) $tde_id : null,
            'top_id'                     => $top_id ? (string) $top_id : null,
            'cdo_lote'                   => $this->lote,
            'pro_id'                     => ($pro_id !== '') ? (string) $pro_id : null,
            'rfa_resolucion'             => $rfa_resolucion ? $rfa_resolucion : null,
            'rfa_prefijo'                => $rfa_prefijo != '' && $rfa_prefijo != null ? (string) $rfa_prefijo : '',
            'cdo_consecutivo'            => (string) $cdo_consecutivo,
            'cdo_fecha'                  => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:IssueDate"),
            'cdo_hora'                   => $cdo_hora,
            'cdo_vencimiento'            => !empty($cdo_vencimiento) ? $cdo_vencimiento : null,
            'cdo_observacion'            => !empty($observaciones) ? json_encode($observaciones) : null,
            'cdo_documento_referencia'   => !empty($documentosReferencia) ? json_encode($documentosReferencia) : null,
            'cdo_conceptos_correccion'   => !empty($conceptosCorreccion) ? json_encode($conceptosCorreccion) : null,
            'mon_id'                     => $mon_id ? (string) $mon_id : null,
            'mon_id_extranjera'          => $mon_id_extranjera ? (string) $mon_id_extranjera : null,
            'cdo_trm'                    => ((string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentExchangeRate/cbc:CalculationRate") != '') ?
                (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentExchangeRate/cbc:CalculationRate") :
                null,
            'cdo_trm_fecha'              => ((string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentExchangeRate/cbc:Date") != '') ?
                (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:PaymentExchangeRate/cbc:Date") :
                null,
            'cdo_valor_sin_impuestos'    => ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') ? 
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:LineExtensionAmount")) :
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:LineExtensionAmount")),
            'cdo_impuestos'              => $this->numberFormat($cdo_impuestos),
            'cdo_total'                  => ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') ? 
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount")) :
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:TaxInclusiveAmount")),
            'cdo_cargos'                 => ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') ? 
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:ChargeTotalAmount")) :
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:ChargeTotalAmount")),
            'cdo_descuentos'             => ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') ? 
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount")) :
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:AllowanceTotalAmount")),
            'cdo_anticipo'               => ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') ? 
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:PrepaidAmount")) :
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:PrepaidAmount")),
            'cdo_redondeo'               => ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') ? 
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:PayableRoundingAmount")) :
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:PayableRoundingAmount")),
            'cdo_valor_a_pagar'          => ($cdo_clasificacion == 'FC' || $cdo_clasificacion == 'DS' || $cdo_clasificacion == 'NC' || $cdo_clasificacion == 'DS_NC') ? 
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:LegalMonetaryTotal/cbc:PayableAmount")) :
                $this->numberFormat((float) $this->getValueByXpath("//{$this->tipoDocumento}/cac:RequestedMonetaryTotal/cbc:PayableAmount")),
            'cdo_cufe'                   => $cdo_cufe,
            'cdo_algoritmo_cufe'         => ((string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cbc:UUID", "schemeName")) ?
                (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cbc:UUID", "schemeName") :
                null,
            'cdo_qr'                     => ((string) $this->getValueByXpath("//{$this->tipoDocumento}/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:QRCode")) ?
                (string) $this->getValueByXpath("//{$this->tipoDocumento}/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:QRCode") :
                null,
            'cdo_signaturevalue'         => ((string) $this->getValueByXpath("//{$this->tipoDocumento}/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature/ds:SignatureValue")) ?
                (string) $this->getValueByXpath("//{$this->tipoDocumento}/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature/ds:SignatureValue") :
                null,
            'cdo_medios_pago'            => !empty($mediosPago) ? $mediosPago : null
        ];

        if(!$radian)
            $documento['ofe_id'] = (string) $id_actor_ofe;
        else
            $documento['act_id'] = (string) $id_actor_ofe;

        // Documentos Adicionales
        $documentosAdicionales = [];
        /**
         OJO - Es obligatorio para tipo de factura 03 pero hay PTs que lo incluyen igual y considero que debemos registrarlo si viene el dato
         */
        // if($cdo_clasificacion == 'FC' && (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:{$this->tipoDocumentoOriginal}TypeCode") == '03') {
        if($cdo_clasificacion == 'FC') {
            for($i = 1; $i <= count($this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference")); $i++) {
                $documentosAdicionales[] = [
                    'rod_codigo'    => (array_key_exists('0', $this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference[{$i}]/cbc:DocumentTypeCode"))) ?
                        (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference[{$i}]/cbc:DocumentTypeCode")[0] : 
                        '',
                    'prefijo'       => '',
                    'consecutivo'   => (array_key_exists('0', $this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference[{$i}]/cbc:ID"))) ?
                        (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference[{$i}]/cbc:ID")[0] : 
                        '',
                    'cufe'          => (array_key_exists('0', $this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference[{$i}]/cbc:UUID"))) ? 
                        (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference[{$i}]/cbc:UUID")[0] : 
                        '',
                    'fecha_emision' => (array_key_exists('0', $this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference[{$i}]/cbc:IssueDate"))) ? 
                        (string) $this->xml->xpath("//{$this->tipoDocumento}/cac:AdditionalDocumentReference[{$i}]/cbc:IssueDate")[0] : 
                        ''
                ];
            }
        }

        $documento['datos_adicionales_documento']['cdo_documento_adicional'] = (!empty($documentosAdicionales)) ? $documentosAdicionales : null;

        // Orden Referencia
        $documento['datos_adicionales_documento']['dad_orden_referencia'] = null;
        $referencia             = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:OrderReference/cbc:ID");
        $fechaEmisionReferencia = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:OrderReference/cbc:IssueDate");

        if($referencia)
            $documento['datos_adicionales_documento']['dad_orden_referencia']['referencia'] = $referencia;

        if($fechaEmisionReferencia)
            $documento['datos_adicionales_documento']['dad_orden_referencia']['fecha_emision_referencia'] = $fechaEmisionReferencia;

        return $documento;
    }

    /**
     * Construye un objeto de información adicional para un proveedor o adquirente
     *
     * @param string $nodo       Lugar del que se extrae la información del xml
     * @param string $tojCodigo tipo de organización juridica
     * @param string $postalZone Código postal
     * @param string $postalZoneFiscal código postal de domicilio fiscal
     * @param string $correo      Correo del proveedor o adquirente
     * @param string $responsabilidadesFiscales responsabilidades fiscales del proveedor o adquirente
     * @return \stdClass Objeto que contiene la información adicional para un proveedor o adquirente
     */
    public function buildObjectInformacionAdicional(string $nodo, string $tojCodigo, string $postalZone, string $postalZoneFiscal, string $correo, string $responsabilidadesFiscales): \stdClass {
        $obj_informacion_adicional                              = new \stdClass();
        $obj_informacion_adicional->toj_codigo                  = $tojCodigo;
        $obj_informacion_adicional->tdo_codigo                  = (
                                                                    (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:CompanyID", "schemeName") ?
                                                                    (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:CompanyID", "schemeName") :
                                                                    (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyLegalEntity/cbc:CompanyID", "schemeName")
                                                                );
        $obj_informacion_adicional->pai_codigo                  = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cac:Country/cbc:IdentificationCode");
        $obj_informacion_adicional->dep_codigo                  = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cbc:CountrySubentityCode");
        $obj_informacion_adicional->mun_codigo                  = substr((string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cbc:ID"), 2, 3);
        $obj_informacion_adicional->mun_descripcion             = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cbc:CityName"); 
        $obj_informacion_adicional->cpo_codigo                  = $postalZone;
        $obj_informacion_adicional->direccion                   = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PhysicalLocation/cac:Address/cac:AddressLine/cbc:Line");
        $obj_informacion_adicional->telefono                    = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:Contact/cbc:Telephone");
        $obj_informacion_adicional->pai_codigo_domicilio_fiscal = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:Country/cbc:IdentificationCode");
        $obj_informacion_adicional->dep_codigo_domicilio_fiscal = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cbc:CountrySubentityCode");
        $obj_informacion_adicional->mun_codigo_domicilio_fiscal = substr((string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cbc:ID"), 2, 3);
        $obj_informacion_adicional->cpo_codigo_domicilio_fiscal = $postalZoneFiscal;
        $obj_informacion_adicional->direccion_domicilio_fiscal  = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line");
        $obj_informacion_adicional->correo                      = $correo;
        $obj_informacion_adicional->rfi_codigo                  = (string) $this->getAttributeByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode", "listName");
        $obj_informacion_adicional->ref_codigo                  = !empty($responsabilidadesFiscales) ? explode(';', $responsabilidadesFiscales) : [];
        $obj_informacion_adicional->matricula_mercantil         = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:{$nodo}/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:Name");
        return $obj_informacion_adicional;
    }
}