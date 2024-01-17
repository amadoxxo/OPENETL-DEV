<?php
/**
 * Clase principal del proceso de Radian en openETL.
 * 
 * Agrupa métodos que pueden ser llamados desde cualquier parte del proyecto en donde se importe la clase
 */
namespace App\Http\Modulos\Radian\ParserXml;

use Validator;
use App\Traits\DiTrait;
use App\Http\Modulos\Radian\RadianException;
use App\Http\Modulos\Recepcion\ParserXml\MainParserXml;
use App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActor;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianMediosPagoDocumentosDaop\RadianMedioPagoDocumentoDaop;

class ParserXml extends MainParserXml {
    use DiTrait;

    /**
     * Tipos de operaciones permitidas.
     *
     * @var array
     */
    private $tiposOperacionPermitidas = ['1', '2', '3', '4'];
    
    /**
     * Constructor de la clase.
     * 
     * @param string $lote Lote de procesamiento
     */
    public function __construct(string $lote = '') {
        parent::__construct();

        $this->lote = $lote;
    }

    /**
     * Permite obtener el tipo de documento electrónico que se está procesando.
     *
     * @param string $xml Xml-Ubl en procesamiento
     * @return void
     */
    public function definirTipoDocumento(string $xml) {
        $xml                 = str_replace('xmlns=', 'ns=', $xml);
        $this->xml           = new \SimpleXMLElement($xml, LIBXML_NOERROR);
        $this->tipoDocumento = $this->tipoDocumentoOriginal = $this->xml->getName();
        $this->namespaces    = $this->xml->getDocNamespaces();
        $this->rootNS        = array_search('urn:oasis:names:specification:ubl:schema:xsd:' . $this->tipoDocumentoOriginal . '-2', $this->namespaces);

        $testNode = $this->rootNS . ':' . $this->tipoDocumento;
        if(!empty($this->rootNS) && !empty((string) $this->getValueByXpath("//{$testNode}/cbc:ID")))
            $this->tipoDocumento = $testNode;

        $this->codigoTipoDocumento = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:InvoiceTypeCode");
    }

    /**
     * Procesa un archivo Xml-Ubl.
     * 
     * @param string $origen Origen del procesamiento del documento
     * @param string $xml Contenido en base64 del archivo Xml-Ubl a procesar
     * @param string $actorIdentificacion Número Identificación del Actor 
     * @param string $rol_id Rol que tiene registrado el JSON del documento que se está procesando
     * @return array Array con dos posiciones, procesado y cdo_id o errors
     */
    public function Parser(string $origen, string $xml, string $actorIdentificacion, string $rol_id): array {
        try {
            // Usuario Autenticado
            $user              = auth()->user();
            $errors            = [];
            $inicioProceso     = date('Y-m-d H:i:s');
            $tsInicioProceso   = microtime(true);
            $prefijoConsecutivo = '';

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

                // OFE
                $identificacionEmisor = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID");

                // ADQUIRENTE
                $identificacionReceptor = (string) $this->getValueByXpath("//{$this->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID");
                
                // Verifica que exista el ACTOR y que se encuentre activo para tomar el act_id que pasa al método de obtener datos documento
                $existeActor = RadianActor::select(['act_id'])
                    ->where('act_identificacion', $actorIdentificacion)
                    ->validarAsociacionBaseDatos()
                    ->where('estado', 'ACTIVO')
                    ->first();

                if(!$existeActor)
                    throw new RadianException(
                        'En el Documento [' . $id . '], el ACTOR [' . $actorIdentificacion . '] No existe o su estado es INACTIVO.',
                        422,
                        null
                    );

                //Datos Ofe
                $arrDataOfe = $this->obtenerDatosPersona($identificacionEmisor, '', true);

                //Datos Adq
                $arrDataAdq = $this->obtenerDatosPersona('', $identificacionReceptor, true, true);

                //Se guarda el registro en las tablas de Radian
                $arrDataDocumento = $this->obtenerDatosDocumento($origen, $cdo_clasificacion, $existeActor->act_id,  '', true);

                $arrDataDocumento['rol_id']                    = $rol_id;
                $arrDataDocumento['estado']                    = 'ACTIVO';
                $arrDataDocumento['usuario_creacion']          = $user->usu_id;
                $arrDataDocumento['adq_identificacion']        = $arrDataAdq['adq_identificacion'];
                $arrDataDocumento['adq_nombre']                = $arrDataAdq['adq_razon_social'];
                $arrDataDocumento['ofe_identificacion']        = $arrDataOfe['ofe_identificacion'];
                $arrDataDocumento['ofe_nombre']                = $arrDataOfe['ofe_razon_social'];
                $arrDataDocumento['ofe_informacion_adicional'] = json_encode($arrDataOfe['ofe_informacion_adicional']);
                $arrDataDocumento['adq_informacion_adicional'] = json_encode($arrDataAdq['adq_informacion_adicional']);
                $arrDataDocumento['cdo_nombre_archivos']       = json_encode(['xml_ubl' => $arrDataDocumento['rfa_prefijo'].''.$arrDataDocumento['cdo_consecutivo'].'.xml']);
                $prefijoConsecutivo                            = (!empty($arrDataDocumento['cdo_consecutivo']) && !empty($arrDataDocumento['rfa_prefijo'])) ? $arrDataDocumento['rfa_prefijo'].''.$arrDataDocumento['cdo_consecutivo']: '';

                //Reglas de validación del modelo de Cabecera para Radian
                $reglas = RadianCabeceraDocumentoDaop::$rules;

                //Guarda el campo top_id y el fpa_id
                $top_id = (array_key_exists('top_id', $arrDataDocumento)) ? $arrDataDocumento['top_id'] : null;
                $fpa_id = (array_key_exists('cdo_medios_pago', $arrDataDocumento) && !empty($arrDataDocumento['cdo_medios_pago'][0])) ? $arrDataDocumento['cdo_medios_pago'][0]['fpa_id'] : null;

                $documentoRadian = null;
                if(!is_null($top_id) && in_array($top_id, $this->tiposOperacionPermitidas) && !empty($fpa_id) && $fpa_id == '2') {
                    $validador = Validator::make($arrDataDocumento, $reglas);
                    if($validador->fails()) {
                        $errors[] = 'Errores al validar la información del documento: [' . implode(' // ', $validador->errors()->all()) . ']';
                    } else {
                        $documentoRadian = RadianCabeceraDocumentoDaop::create($arrDataDocumento);
                        // Medios de pago
                        if(!empty($arrDataDocumento['cdo_medios_pago'])) {
                            foreach ($arrDataDocumento['cdo_medios_pago'] as $item) {
                                $item['cdo_id']           = $documentoRadian->cdo_id;
                                $item['usuario_creacion'] = $user->usu_id;
                                $item['estado']           = 'ACTIVO';
                                $mediosPagoRadian         = new RadianMedioPagoDocumentoDaop($item);
                                $mediosPagoRadian->save();
                            }
                        }

                        $archivoXml  = $this->guardarArchivoEnDisco($actorIdentificacion, $documentoRadian, 'radian', 'xml', 'xml', $xmlOriginal, 'xml_' . $prefijoConsecutivo . '.xml');

                        // Crea el estado RADDI correspondiente
                        $estadoInformacionAdicional = [
                            'documento'   => $prefijoConsecutivo,
                            'consecutivo' => (string) $this->getValueByXpath("//{$this->tipoDocumento}/cbc:ID"),
                            'est_xml'     => $archivoXml
                        ];

                        RadianEstadoDocumentoDaop::create([
                            'cdo_id'                    => $documentoRadian->cdo_id,
                            'est_estado'                => 'RADDI',
                            'est_resultado'             => 'EXITOSO',
                            'est_inicio_proceso'        => $inicioProceso,
                            'est_fin_proceso'           => date('Y-m-d H:i:s'),
                            'est_ejecucion'             => 'FINALIZADO',
                            'est_tiempo_procesamiento'  => number_format((microtime(true) - $tsInicioProceso), 3, '.', ''),
                            'est_informacion_adicional' => ($estadoInformacionAdicional != null && !empty($estadoInformacionAdicional)) ? json_encode($estadoInformacionAdicional) : null,
                            'age_id'                    => null,
                            'age_usu_id'                => null,
                            'usuario_creacion'          => $user->usu_id,
                            'estado'                    => 'ACTIVO'
                        ]);
                    }

                    return [
                        'procesado'           => true,
                        'cdo_id'              => $documentoRadian->cdo_id,
                        'act_identificacion'  => $actorIdentificacion,
                        'cufe'                => $documentoRadian->cdo_cufe,
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s')
                    ];
                } else {
                    $errors[] = 'El Documento [' . $id . '], no posee como tipo de operacion: '. implode(", ", $this->tiposOperacionPermitidas) .' o la forma de pago no es credito';
                    return [
                        'procesado'           => false,
                        'act_identificacion'  => $actorIdentificacion,
                        'cufe'                => $arrDataDocumento['cdo_cufe'],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'errors'              => $errors
                    ];
                }

            } else {
                if ($this->tipoDocumentoOriginal == 'ApplicationResponse') {
                    // Se indica que es un application response y se pasa el archivo a fallidos
                    // No se hace nada
                    // dump('No se procesa, documento ApplicationResponse');
                } else {
                    throw new RadianException(
                        'No fue posible determinar la clasificación del documento [cdo_clasificacion]',
                        422,
                        null,
                    );
                }
            }
        } catch (\Exception $e) {
            throw new RadianException(
                $e->getMessage(),
                422,
                null
            );
        }
    }
}