<?php

namespace App\Http\Modulos\DataInputWorker\DHL;

use Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\DataInputWorker\DHL\Utils\MapXpathDhl;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\DataInputWorker\DHL\Utils\MapCamposDHL;
use App\Http\Modulos\DataInputWorker\Json\JsonDocumentBuilder;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Clase Gestora para el procesamiento de Cargas por medio de xml.
 *
 * Class ParserXml
 * @package App\Http\Modulos\DataInputWorker
 */
class ParserXmlDHL
{
    use PackageMainTrait;

    /**
     * @var
     */
    protected $stringError;
    /**
     * @var \SimpleXMLElement
     */
    protected $xml;

    /**
     * @var bool
     */
    protected $procesar = true;

    /**
     * @var array
     */
    protected $errores = [];
    /**
     * contiene toda la data.
     *
     * @var array
     */
    protected $data = [];
    /**
     * contiene el nombre del archivo a procesar.
     *
     * @var string;
     */
    protected $filename;

    /**
     * Indica el tipo de docuemento que se esta procesando.
     *
     * @var string
     */
    public $cdo_clasificacion = '';

    /**
     * Lista de impuestos registrados y activos en etl_openmain
     *
     * @var array
     */
    private $impuestosRegistrados = [];

    /**
     * Contiene el numero de documento.
     *
     * @var null|string
     */
    public $numDocumento = '';

    /**
     * Datos de cabecera
     *
     * @var array
     */
    public $cabecera = [];

    /**
     * Datos de Items
     *
     * @var array
     */
    public $items = [];

    /**
     * Lista de impuestos
     *
     * @var array
     */
    public $listaImpuestos = [];

    /**
     * Lista de retenciones
     *
     * @var array
     */
    public $listaRetenciones = [];

    /**
     * Contiene el nodo raiz del XML
     *
     * @var string
     */
    private $root;

    /**
     * ParserXmlDHL constructor.
     * @param $filename
     */
    public function __construct($filename)
    {
        // Inhabilita los errores libxml y permite capturarlos para su manipulación
        libxml_use_internal_errors(true);
        $this->filename = $filename;
        ParametrosTributo::where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) {
                $key = strtolower($this->sanear_string($item->tri_nombre));
                $key = str_replace(' ', '_', $key);
                $this->impuestosRegistrados[$key] = $item->tri_codigo;
            });
    }

    /**
     *
     */
    private function getTipo()
    {
        if ($this->root === "notacredito") {
            $this->cdo_clasificacion = 'NC';
        } elseif ($this->root === "notadebito") {
            $this->cdo_clasificacion = 'ND';
        } elseif ($this->root === "factura") {
            $this->cdo_clasificacion = 'FC';
        }
    }

    /**
     * Nos indica si hay errores.
     *
     * @return bool
     */
    public function hasError()
    {
        return !empty($this->errores);
    }

    /**
     * Retorna la lista de errores de ejecución.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errores;
    }

    /**
     * Retorna el valor de una clave en particular de un array.
     *
     * @param array $array
     * @param string $key
     * @return mixed|string
     */
    private function getValue(array $array, string $key) {
        return array_key_exists($key, $array) ? $array[$key] : '';
    }

    /**
     * Proceso primario del Parseo de las empresas DHL.
     *
     * @param string $ofe_identificacion
     * @return null|mixed
     */
    public function run(string $ofe_identificacion)
    {
        $this->xml = simplexml_load_file($this->filename);
        if (!$this->xml) {
            $this->procesar = false;
            $this->errorXml(libxml_get_errors());
            return null;
        }
        $this->root = $this->xml->getName();
        $this->getTipo();
        if (is_null($this->cdo_clasificacion)) {
            $this->errores[] = "El archivo {$this->filename} no contiene una etiqueta de documento valido en su nodo raíz";
            return null;
        }
        try {
            $cabecera = $this->getCabecera();
            if ($cabecera['ofe_identificacion'] !== $ofe_identificacion) {
                $archivo = basename($this->filename);
                $this->errores = ["El NIT enviado en el archivo [{$cabecera['ofe_identificacion']}] no Corresponde al NIT del OFE [{$ofe_identificacion}]. ({$archivo} )"];
                return null;
            }
            $this->numDocumento = $this->getValue($cabecera, 'rfa_prefijo') . $this->getValue($cabecera, 'cdo_consecutivo');
            $montosCop = [];
            $montosExt = [];
            $resolucion = '';
            $vencimiento = [];
            //Se recorren los campos del array  $camposMontosCOP
            $arrayMontosCop = MapXpathDhl::$camposMontosCOP;
            foreach ($arrayMontosCop as $key => $item) {
                $objeto = $this->getValueByXpath($item);
                if (!is_null($objeto)) {
                    $montosCop[$key] = (string)$objeto;
                }
            }
            $result = intval($montosCop['subtotal_ip']) + intval($montosCop['subtotal_pcc']);
            $montosCop['subtotal'] = (string)$result;

            //Se recorren los campos del array  $arrayMontosExt
            $arrayMontosExt = MapXpathDhl::$camposMontosEXT;
            foreach ($arrayMontosExt as $key => $item) {
                $objeto = $this->getValueByXpath($item);
                if (!is_null($objeto)) {
                    if ($key === 'base_iva_moneda_extranjera')
                        $montosExt['baseiva_moneda_extranjera'] = (string)$objeto;
                    else
                        $montosExt[$key] = (string)$objeto;
                }
            }

            $result = intval($montosExt['subtotal_ip_moneda_extranjera']) + intval($montosExt['subtotal_pcc_moneda_extranjera']);
            $montosExt['subtotal_moneda_extranjera'] = (string)$result;

            if (isset($this->xml->identificaciondeldocumento->tipo)) {
                $this->data["tipo"] = (string)$this->xml->identificaciondeldocumento->tipo;
                if ($this->data["tipo"] == "01") {
                    $this->data["tipo"] = ConstantsDataInput::FC;
                    if (isset($this->xml->emisor->resolucionlinea1) || $this->xml->emisor->resolucionlinea2 || $this->xml->emisor->resolucionlinea3 || $this->xml->emisor->resolucionlinea4 || $this->xml->emisor->resolucionlinea5 || $this->xml->emisor->resolucionlinea6) {
                        $resolucion = trim((string)$this->xml->emisor->resolucionlinea1) . "" . (string)$this->xml->emisor->resolucionlinea2 . "" . (string)$this->xml->emisor->resolucionlinea3 . "" . (string)$this->xml->emisor->resolucionlinea4 . "" . (string)$this->xml->emisor->resolucionlinea5 . "" . (string)$this->xml->emisor->resolucionlinea6;
                    } else {
                        $resolucion = "";
                    }
                }

                $cabecera['informacion_adicional']['resolucion_facturacion'] = $resolucion;

                if ($this->data["tipo"] == "07") {
                    $this->data["tipo"] = ConstantsDataInput::NC;
                }
                if ($this->data["tipo"] == "08") {
                    $this->data["tipo"] = ConstantsDataInput::ND;
                }
            } else {
                $this->data["tipo"] = "";
            }

            //Items, impuestos y retenciones
            $items = $this->getDataItems($ofe_identificacion, $cabecera);

            // Se recorren los items para verificar si existe el índice 'department' en algún item
            // y en caso tal agregarlo a la información adicional de cabecera, adicionalmente se debe
            // agregar al campo ddo_notas del item (solamente si se trata de FC) porque se debe enviar
            // en el xml-ubl para cada item (ticket ETL-63)
            foreach($items as $key => $item) {
                if(array_key_exists('department', $item)) {
                    if(!array_key_exists('department', $cabecera['informacion_adicional']) && trim($item['department']) != '')
                        $cabecera['informacion_adicional']['department'] = $item['department'];

                    if ($this->data["tipo"] == "FC")
                        $items[$key]['ddo_notas'] = 'DEPARTMENT: ' . trim($item['department']);
                }
            }

            //Excluyendo los campos de informacion adicional que no deben enviarse en el XML-UBL
            if (isset($cabecera['informacion_adicional']['ciudad_prestacion_servicio'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'ciudad_prestacion_servicio';
            }
            if (isset($cabecera['informacion_adicional']['guia_hija'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'guia_hija';
            }
            if (isset($cabecera['informacion_adicional']['procedencia'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'procedencia';
            }
            if (isset($cabecera['informacion_adicional']['proveedor'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'proveedor';
            }
            if (isset($cabecera['informacion_adicional']['piezas'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'piezas';
            }
            if (isset($cabecera['informacion_adicional']['peso_volumen'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'peso_volumen';
            }
            if (isset($cabecera['informacion_adicional']['descripcion'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'descripcion';
            }
            if (isset($cabecera['informacion_adicional']['cssv'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'cssv';
            }
            if (isset($cabecera['informacion_adicional']['file'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'file';
            }
            if (isset($cabecera['informacion_adicional']['guia_master'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'guia_master';
            }
            if (isset($cabecera['informacion_adicional']['aerol_naviera'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'aerol_naviera';
            }
            if (isset($cabecera['informacion_adicional']['tipo_cambio'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'tipo_cambio';
            }
            if (isset($cabecera['informacion_adicional']['valor_moneda_org'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'valor_moneda_org';
            }
            if (isset($cabecera['informacion_adicional']['correos_receptor'])) {
                $cabecera['informacion_adicional']['cdo_informacion_adicional_excluir_xml'][] = 'correos_receptor';
            }
            if (empty($this->errores)) {
                $this->preparteData($cabecera, $items, $montosCop, $montosExt);
            }
        }
        catch (\Exception $e) {
            // Log::info($e);
            $this->errorXml(libxml_get_errors());
        }
        return null;
    }

    private function toFile($in)
    {
        $handler = fopen('salida.json', 'w');
        fputs($handler, $in);
        fclose($handler);
    }

    /**
     * Extrae la data de cabecera del XML.
     *
     * @return array
     */
    private function getCabecera()
    {
        $cabecera = [];
        $informacionAdicional = [];
        //Se recorren los campos del array  $arrayCabecera
        $arrayCabecera = $this->cdo_clasificacion === ConstantsDataInput::FC ?
            MapXpathDhl::$xpathArrayCabeceraFC : MapXpathDhl::$xpathArrayCabeceraNCND;

        foreach ($arrayCabecera as $key => $item) {
            $objeto = $this->getValueByXpath($item);
            $cabecera[$key] = !is_null($objeto) ? trim((string)$objeto) : '';
        }

        // Se comenta codigo ya que el ERP de DHL Global puede generar los consecutivos dentro del rango de la resolucion
        /*if ($this->cdo_clasificacion === ConstantsDataInput::FC && array_key_exists('ofe_identificacion', $arrayCabecera)) {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'sft_id'])
                ->with(['getConfiguracionSoftwareProveedorTecnologico', 'getResolucionFacturacion'])
                ->where('ofe_identificacion', $cabecera['ofe_identificacion'])
                ->first();
            if(!is_null($ofe) && !is_null($ofe->getConfiguracionSoftwareProveedorTecnologico)
                && $ofe->getConfiguracionSoftwareProveedorTecnologico->add_id == 2) {
                $ultimoConsecutivo = EtlCabeceraDocumentoDaop::select('cdo_consecutivo')
                    ->where('cdo_clasificacion', 'FC')
                    ->where('ofe_id', $ofe->ofe_id)
                    ->orderBy('cdo_consecutivo', 'desc')
                    ->first();
                if (!is_null($ultimoConsecutivo)) {
                    $cdo_consecutivo = $ultimoConsecutivo->cdo_consecutivo + 1;
                } else {
                    $cdo_consecutivo = $ofe->getResolucionFacturacion->rfa_consecutivo_inicial;
                }

                $cabecera['cdo_consecutivo'] = "$cdo_consecutivo";
            }
        }*/

        //Se recorren los campos del array  $arrayInformacionAdicional
        $arrayInformacionAdicional = MapXpathDhl::$xpathArrayInformacionAdicional;
        foreach ($arrayInformacionAdicional as $key => $item) {
            $objeto = $this->getValueByXpath($item);
            $informacionAdicional[$key] = !is_null($objeto) ? (string)$objeto : '';
        }
        $informacionAdicional['fecha'] = date("Y-m-d");

        //Se recorren los campos fechas del array informacionAdicional
        $camposFechaAdicional = MapCamposDHL::$camposFechasAdicional;
        $observacionesAdicionales = [];
        foreach ($camposFechaAdicional as $item) {
            if (array_key_exists($item, $informacionAdicional) && !empty($informacionAdicional[$item]))
                $informacionAdicional[$item] = $this->formatDate($informacionAdicional[$item]);
        }

        $informacionAdicional['cdo_procesar_documento'] = 'SI';


        $observaciones = [];
        //Se recorren los campos del array  observaciones
        $arrayObservaciones = MapXpathDhl::$xpathCamposObservaciones;
        foreach ($arrayObservaciones as $key => $item) {
            $objeto = $this->getValueByXpath($item);
            $observaciones[] = !is_null($objeto) ? (string)$objeto : '';
        }
        $__observaciones = '';
        foreach ($observaciones as $obs) {
            if (trim($obs) !== '')
                $__observaciones = $__observaciones . trim($obs) . ' ';
        }

        $cabecera['cdo_representacion_grafica_documento'] = (string)"1";
        $cabecera['cdo_representacion_grafica_acuse'] = (string)"1";
        $cabecera['observacion'] = [$__observaciones];
        $cabecera['informacion_adicional'] = $informacionAdicional;
        $cabecera['cdo_fecha'] = date("Y-m-d");
        $cabecera['cdo_hora']  = date('H:i:s');

        if ($cabecera['fpa_codigo'] == "2" || !empty($cabecera['cdo_vencimiento'])) {
            $cabecera['men_fecha_vencimiento'] = $this->formatDate($cabecera['cdo_vencimiento']);
        }

        switch ($cabecera['tde_codigo']) {
            case '07':
                $cabecera['tde_codigo'] = '91';
                break;
            case '08':
                $cabecera['tde_codigo'] = '92';
                break;
        }

        //Se recorren los campos hora se les da formato
        $camposHora = $this->cdo_clasificacion === ConstantsDataInput::FC ? MapCamposDHL::$camposHoraFC : MapCamposDHL::$camposHoraNCND;
        foreach ($camposHora as $item) {
            if (array_key_exists($item, $cabecera) && !empty($cabecera[$item]))
                $cabecera[$item] = date("h:i:s");
        }

        //Se recorren los campos fechas del array de cabecera se les da formato
        $camposFecha = $this->cdo_clasificacion === ConstantsDataInput::FC ? MapCamposDHL::$camposFechasFC : MapCamposDHL::$camposFechasNCND;
        foreach ($camposFecha as $item) {
            if (array_key_exists($item, $cabecera) && !empty($cabecera[$item]))
                $cabecera[$item] = $this->formatDate($cabecera[$item]);
        }

        // Para las NC DHL Global no envía el nodo tipoOeracion por lo que se debe aplicar las siguientes validaciones:
        //  - Si la FECHA DE LA FACTURA REFERENCIA (<fecharef>) en la Nota Credito es menor a 2019-11-11 se debe colocar como tipo de operacion el codigo 23 (Nota credito de factiura de 2242) 
        //  - Si la FECHA DE LA FACTURA REFERENCIA (<fecharef>) en la Nota Credito es mayor o igual a 2019-11-11 se debe colocar como tipo de operacion el codigo 20 (Nota credito de factiura electronica)
        if($this->cdo_clasificacion === ConstantsDataInput::NC) {
            $fechaFacturaReferencia = Carbon::parse($cabecera['fecha_emision']);
            $fechaCorteTopCodigo    = Carbon::parse('2019-11-11');

            if($fechaFacturaReferencia < $fechaCorteTopCodigo)
                $cabecera['top_codigo'] = '23';
            else
                $cabecera['top_codigo'] = '20';
        }

        return $cabecera;
    }

    /**
     * Obtiene la data asociada a los items
     *
     * @param string $ofe_identificacion Identificacion del OFE del documento
     * @param array  $cabecera Datos Cabecera
     * @return array
     */
    private function getDataItems($ofe_identificacion, $cabecera)
    {
        $ingresosTercerosItems = $this->getValueByXpath('//detalleingresosterceros');
        $ingresosPropiosItems = $this->getValueByXpath('//detalleingresospropios');

        if (!is_null($ingresosTercerosItems)) {
            // Si solo es un elemento vendra un objeto
            $ingresosTercerosItems = (array)$ingresosTercerosItems;
            if (is_object($ingresosTercerosItems['item']))
                $ingresosTercerosItems = ['item' => array($ingresosTercerosItems['item'])];
        } else
            $ingresosTercerosItems = ['item' => []];

        if (!is_null($ingresosPropiosItems)) {
            // Si solo es un elemento vendra un objeto
            $ingresosPropiosItems = (array)$ingresosPropiosItems;
            if (is_object($ingresosPropiosItems['item']))
                $ingresosPropiosItems = ['item' => array($ingresosPropiosItems['item'])];
        } else
            $ingresosPropiosItems = ['item' => []];

        $ddo_secuencia = 1;
        $items = []; // Array de Arrays
        //Ingresos terceros
        if (!empty($ingresosTercerosItems['item']) && is_array($ingresosTercerosItems['item'])) {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'tdo_id'])
                ->with(['getParametroTipoDocumento'])
                ->where('ofe_identificacion', $ofe_identificacion)
                ->first();
            foreach ($ingresosTercerosItems['item'] as $k => $item) {
                $itemsprocesado = $this->loadItem($item);
                if (!is_null($itemsprocesado)) {
                    $itemsprocesado['ddo_tipo']                  = 'PCC';
                    $itemsprocesado['nit_mandatario']            = $ofe_identificacion;
                    $itemsprocesado['tipo_documento_mandatario'] = $ofe->getParametroTipoDocumento->tdo_codigo;
                    if ($cabecera['top_codigo'] == '11'){
                        $itemsprocesado['ddo_identificador'] = '1';
                    }
                    $this->processItem($item, $itemsprocesado, (string)$ddo_secuencia, $k, true);
                    $ddo_secuencia++;
                    $items[] = $itemsprocesado;
                }
            }
        }
        //Ingresos propios
        if (!empty($ingresosPropiosItems['item']) && is_array($ingresosPropiosItems['item'])) {
            foreach ($ingresosPropiosItems['item'] as $k => $item) {
                $itemsprocesado = $this->loadItem($item);
                if (!is_null($itemsprocesado)) {
                    $itemsprocesado['ddo_tipo'] = 'IP';
                    if ($cabecera['top_codigo'] == '11'){
                        $itemsprocesado['ddo_identificador'] = '0';
                    }
                    $this->processItem($item, $itemsprocesado, (string)$ddo_secuencia, $k);
                    $ddo_secuencia++;
                    $items[] = $itemsprocesado;
                }
            }
        }
        return $items;
    }

    /**
     * Procesa un item
     *
     * @param $item
     * @param array $itemsprocesado
     * @param string $ddo_secuencia
     * @param $indice
     * @param bool $excepcionIva
     */
    private function processItem($item, array &$itemsprocesado, string $ddo_secuencia, $indice, $excepcionIva = false)
    {
        $itemsprocesado['ddo_secuencia'] = (string)$ddo_secuencia;
        $itemsprocesado['ddo_informacion_adicional'] = $this->loadAdicionales($item);
        $propiedadesAdicionalesItem = $this->loadPropiedades($item);
        if(!empty($propiedadesAdicionalesItem)) {
            $itemsprocesado['ddo_propiedades_adicionales'] = $propiedadesAdicionalesItem;
        }
        $items[] = $itemsprocesado;

        $impuestos = $this->loadImpuesto($item, $indice, $this->listaImpuestos);
        if (!is_null($impuestos) && !empty($impuestos))
            $itemsprocesado['impuestos'] = $impuestos;
        if( $excepcionIva) {
            if (is_null($impuestos) && empty($impuestos))
                $impuestos = [];
            $impuestos['iva'] = [
                    'valor_iva_impuesto' => '0.00',
                    'base_iva_impuesto' => isset($item->montocop) ? (string)$item->montocop : '0.00',
                    'porcentaje_iva_impuesto' => '0.00',
                    'iid_motivo_exencion' => 'PAGO POR CUENTA DEL CLIENTE (REINTEGRO DE CAPITAL)'
                ];
            $itemsprocesado['impuestos'] = $impuestos;
        }

        $retenciones = $this->loadRetenciones($item, $indice, $this->listaRetenciones);
        if (!is_null($retenciones))
            $itemsprocesado['retenciones'] = $retenciones;
    }

    /**
     * Ajusta la data de items para que pueda ser procesada por el JsonDocumentBuilder.
     *
     * @param $cabecera
     * @param $items
     * @param $camposMontosCOP
     * @param $camposMontosEXT
     */
    private function preparteData($cabecera, $items, $camposMontosCOP, $camposMontosEXT) {
        $montos = array_merge($camposMontosCOP, $camposMontosEXT);
        $columnasObligatoriasCab = $this->cdo_clasificacion === ConstantsDataInput::FC ?
            MapCamposDHL::$columnasObligatoriasCabeceraFC : MapCamposDHL::$columnasObligatoriasCabeceraNCND;
        $obligatoriasCabecera = [];
        foreach ($columnasObligatoriasCab as $campo => $referencia) {
            if (array_key_exists($referencia, $cabecera))
                $obligatoriasCabecera[$campo] = $cabecera[$referencia];
        }

        if ($this->cdo_clasificacion === ConstantsDataInput::FC) {
            unset($obligatoriasCabecera['cod_concepto_correccion']);
            unset($obligatoriasCabecera['observacion_correccion']);
        }

        $opcionales = [];
        foreach (MapCamposDHL::$columnasAnticipos as $campo => $referencia) {
            if (array_key_exists($referencia, $cabecera))
                $opcionales[$campo] = $cabecera[$referencia];
        }

        $obligatoriasCabecera['nit_autorizado'] = '';
        $obligatoriasCabecera['cod_moneda_extranjera'] = '';
        $obligatoriasCabecera['enviar_a_la_dian_en_moneda_extranjera'] = '';

        if (array_key_exists('mon_codigo', $cabecera) && $cabecera['mon_codigo'] !== 'COP') {
            $obligatoriasCabecera['cod_moneda_extranjera'] = $cabecera['mon_codigo'];
            $obligatoriasCabecera['cod_moneda'] = 'COP';
            $obligatoriasCabecera['trm'] = '1.00';
            $obligatoriasCabecera['fecha_trm'] = date('Y-m-d');
        }
        else {
            $obligatoriasCabecera['cod_moneda'] = 'COP';
            $obligatoriasCabecera['cod_moneda_extranjera'] = null;
            $obligatoriasCabecera['trm'] = null;
            $obligatoriasCabecera['fecha_trm'] = null;
        }


        $this->items = [];
        $mapaImpuestoEncontrado = [];
        $mapaRetencionEncontrado = [];
        foreach ($items as $item) {
            $obligatorias = [];
            foreach (MapCamposDHL::$columnasObligatoriasItems as $campo => $referencia) {
                if (array_key_exists($referencia, $item))
                    $obligatorias[$campo] = $item[$referencia];
            }
            $obligatorias['informacion_adicional'] = [];
            $opcionales = [];
            if (array_key_exists('ddo_identificador', $item)) {
                $opcionales['ddo_identificador'] = $item['ddo_identificador'];
            }
            if (array_key_exists('impuestos', $item)) {
                if (is_array($item['impuestos'])) {
                    foreach ($item['impuestos'] as $tipo => $grupo) {
                        $opcionales = array_merge($opcionales, $grupo);
                        $mapaImpuestoEncontrado[$tipo] = true;
                    }
                }
            }
            if (array_key_exists('retenciones', $item)) {
                if (is_array($item['retenciones'])) {
                    foreach ($item['retenciones'] as $tipo => $grupo) {
                        $opcionales = array_merge($opcionales, $grupo);
                        $mapaRetencionEncontrado[$tipo] = true;
                    }
                }
            }
            foreach (MapCamposDHL::$columnasAnticipos as $campo => $referencia) {
                if (array_key_exists($referencia, $cabecera))
                    $opcionales[$campo] = $cabecera[$referencia];
            }

            $this->listaImpuestos = array_unique($this->listaImpuestos);
            $this->listaRetenciones = array_unique($this->listaRetenciones);

            $this->items[] = [
                'obligatorias' => $obligatorias,
                'informacion_adicional' => array_key_exists('ddo_informacion_adicional', $item) ? $item['ddo_informacion_adicional'] : [],
                'propiedades_adicionales' => array_key_exists('ddo_propiedades_adicionales', $item) ? $item['ddo_propiedades_adicionales'] : [],
                'opcionales' => $opcionales
            ];
        }

        $rentencionesCDO = $this->loadRetenciones(null, -1, $this->listaRetenciones);
        $impuestosCDO = $this->loadImpuesto(null, -1, $this->listaImpuestos);
        $impuestosCabecera = [];
        $retencionesCabecera = [];

        foreach ($impuestosCDO as $tipo => $grupo) {
            if (!array_key_exists($tipo, $mapaImpuestoEncontrado))
                $impuestosCabecera = array_merge($impuestosCabecera, $grupo);
        }
        foreach ($rentencionesCDO as $tipo => $grupo) {
            if (!array_key_exists($tipo, $mapaRetencionEncontrado))
                $retencionesCabecera = array_merge($retencionesCabecera, $grupo);
        }

        //Se deben incluir la siguiente informacion en las notas del xml-ubl
        $observacionesAdicionales = [];

        if (isset($cabecera['informacion_adicional']['ciudad_prestacion_servicio'])) {
            $observacionesAdicionales[] = "CIUDAD-PRESTACION-SERVICIO:".$cabecera['informacion_adicional']['ciudad_prestacion_servicio'];
        }
        if (isset($cabecera['informacion_adicional']['guia_hija'])) {
            $observacionesAdicionales[] = "GUIA-HIJA:".$cabecera['informacion_adicional']['guia_hija'];
        }
        if (isset($cabecera['informacion_adicional']['procedencia'])) {
            $observacionesAdicionales[] = "PROCEDENCIA:".$cabecera['informacion_adicional']['procedencia'];
        }
        if (isset($cabecera['informacion_adicional']['proveedor'])) {
            $observacionesAdicionales[] = "PROVEEDOR:".$cabecera['informacion_adicional']['proveedor'];
        }
        if (isset($cabecera['informacion_adicional']['piezas'])) {
            $observacionesAdicionales[] = "PIEZAS:".$cabecera['informacion_adicional']['piezas'];
        }
        if (isset($cabecera['informacion_adicional']['peso_volumen'])) {
            $observacionesAdicionales[] = "PESO-VOLUMEN:".$cabecera['informacion_adicional']['peso_volumen'];
        }
        if (isset($cabecera['informacion_adicional']['descripcion'])) {
            $observacionesAdicionales[] = "DESCRIPCION:".$cabecera['informacion_adicional']['descripcion'];
        }
        if (isset($cabecera['informacion_adicional']['cssv'])) {
            $observacionesAdicionales[] = "CSSV:".$cabecera['informacion_adicional']['cssv'];
        }
        if (isset($cabecera['informacion_adicional']['file'])) {
            $observacionesAdicionales[] = "FILE:".$cabecera['informacion_adicional']['file'];
        }
        if (isset($cabecera['informacion_adicional']['guia_master'])) {
            $observacionesAdicionales[] = "GUIA-MASTER:".$cabecera['informacion_adicional']['guia_master'];
        }
        if (isset($cabecera['informacion_adicional']['aerol_naviera'])) {
            $observacionesAdicionales[] = "AEROLINEA-NAVIERA:".$cabecera['informacion_adicional']['aerol_naviera'];
        }
        if (isset($cabecera['informacion_adicional']['tipo_cambio'])) {
            $observacionesAdicionales[] = "TASA-CAMBIO:".$cabecera['informacion_adicional']['tipo_cambio'];
        }
        if (isset($cabecera['informacion_adicional']['valor_moneda_org'])) {
            $observacionesAdicionales[] = "VALOR-MONEDA-ORG:".$cabecera['informacion_adicional']['valor_moneda_org'];
        }
        if (isset($montos['subtotal_pcc'])) {
            $observacionesAdicionales[] = "SUBTOTAL-INGRESOS-TERCEROS-COP:".$montos['subtotal_pcc'];
        }
        if (isset($montos['subtotal_ip'])) {
            $observacionesAdicionales[] = "SUBTOTAL-INGRESOS-PROPIOS-COP:".$montos['subtotal_ip'];
        }
        if (isset($montos['subtotal'])) {
            $observacionesAdicionales[] = "SUBTOTAL-COP:".$montos['subtotal'];
        }
        if (isset($montos['moneda']) && $montos['moneda'] != 'COP') {
            if (isset($montos['subtotal_pcc'])) {
                $observacionesAdicionales[] = "SUBTOTAL-INGRESOS-TERCEROS-".$montos['moneda'] . ':' . $montos['subtotal_pcc_moneda_extranjera'];
            }
            if (isset($montos['subtotal_ip'])) {
                $observacionesAdicionales[] = "SUBTOTAL-INGRESOS-PROPIOS-".$montos['moneda'] . ':' . $montos['subtotal_ip_moneda_extranjera'];
            }
            if (isset($montos['subtotal'])) {
                $observacionesAdicionales[] = "SUBTOTAL-".$montos['moneda'] . ':' . $montos['subtotal_moneda_extranjera'];
            }
        }

        $obligatoriasCabecera['observacion'] = array_merge($obligatoriasCabecera['observacion'],$observacionesAdicionales);

        $this->cabecera = [
            'obligatorias' => $obligatoriasCabecera,
            'informacion_adicional' => array_merge($cabecera['informacion_adicional'], $montos),
            'opcionales' => $opcionales,
            'impuestos' => $impuestosCabecera,
            'retenciones' => $retencionesCabecera
        ];
    }

    /**
     * Ajusta la informacion de un impuesto o retencion para que pueda ser procesda por el JsonDocumentBuilder.
     *
     * @param array $data
     * @param $indiceItem
     * @param $indiceImpuestoRetencion
     * @param string $label
     * @param string $modo
     * @param null $lista
     * @return array
     */
    private function buildItemImpuesto(array $data, $indiceItem, $indiceImpuestoRetencion, string $label, $modo = '', &$lista = null)
    {
        $complemento = ($modo !== '') ? '_' . $modo : '';
        $objeto = [];
        $labelImpuesto = '';
        if (array_key_exists('tri_codigo', $data)) {
            $labelImpuesto = $this->getLabelImpuesto($data['tri_codigo']);
            if (!is_null($lista))
                $lista[] = $labelImpuesto;
            $objeto["valor_{$labelImpuesto}{$complemento}"] = array_key_exists('iid_valor', $data) ? $data['iid_valor'] : '0.00';
            $objeto["valor_{$labelImpuesto}{$complemento}"] = array_key_exists('iid_valor', $data) ? $data['iid_valor'] : '0.00';
            $objeto["base_{$labelImpuesto}{$complemento}"] = array_key_exists('iid_base', $data) ? $data['iid_base'] : '0.00';
            $objeto["porcentaje_{$labelImpuesto}{$complemento}"] = array_key_exists('iid_porcentaje', $data) ? $data['iid_porcentaje'] : '0.00';
        } else {
            if ($indiceItem !== -1)
                $this->errores[] = "No se pudo determinar el tipo ({$data['tri_codigo']}) de {$label} para el registro {$indiceImpuestoRetencion} en el item {$indiceItem}";
            else
                $this->errores[] = "No se pudo determinar el tipo ({$data['tri_codigo']}) de {$label} para el registro {$indiceImpuestoRetencion}";
        }
        return ['impuesto' => $objeto, 'codigo' => $labelImpuesto];
    }

    /**
     * Retorna el nombre de un impuesto en funcion de su código.
     *
     * @param string $codigo
     * @return string
     */
    private function getLabelImpuesto(string $codigo)
    {
        $impuesto = '';
        switch ($codigo) {
            case '01':
                $impuesto = 'iva';
                break;
            case '03':
                $impuesto = 'ica';
                break;
            case '04':
                $impuesto = 'impuesto_consumo';
                break;
            default:
                return $codigo;
        }
        return $impuesto;
    }

    /**
     * Funcion para cargar los items del array estatico
     * @param $item
     * @return array
     */
    private function loadItem($item)
    {
        $dataItem = [];
        foreach (MapXpathDhl::$xpathCamposItems as $campo => $xpath) {
            $objeto = $this->getValueByXpath($xpath, $item);
            $dataItem[$campo] = !is_null($objeto) ? (string)$objeto : '';
        }
        return $dataItem;

    }

    /**
     * Funcion para cargar los impuestos del array estatico.
     *
     * @param $item
     * @param $indiceItem
     * @param null $listaAgregados
     * @param $excepcionIva
     * @return array
     */
    private function loadImpuesto($item, $indiceItem, &$listaAgregados = null)
    {
        $__impuestos = [];
        if (!is_null($item))
            $impuestos = $this->getValueByXpath('impuestos', $item);
        else
            $impuestos = $this->getValueByXpath("//{$this->root}/impuestos");

        if (!is_null($impuestos)) {
            // Si solo es un elemento vendra un objeto
            $impuestos = (array)$impuestos;
            if (is_object($impuestos['tipo']))
                $impuestos = ['tipo' => array($impuestos['tipo'])];
        } else
            $impuestos = ['tipo' => []];

        $reglas = !is_null($item) ? MapXpathDhl::$xpathImpuestosItems : MapXpathDhl::$xpathImpuestosCabecera;
        foreach ($impuestos['tipo'] as $k => $impuesto) {
            $dataItemImpuesto = [];
            foreach ($reglas as $key => $xpath) {
                $objeto = $this->getValueByXpath($xpath, $impuesto);
                if (!is_null($objeto))
                    $dataItemImpuesto[$key] = (string)$objeto;
            }
            $dataItemImpuesto = $this->buildItemImpuesto($dataItemImpuesto, $indiceItem, $k, 'impuesto', 'impuesto', $listaAgregados);
            $__impuestos[$dataItemImpuesto['codigo']] = $dataItemImpuesto['impuesto'];
        }

        return $__impuestos;
    }

    /**
     * Funcion para cargar las retenciones del array estatico.
     *
     * @param $item
     * @param $indiceItem
     * @param null $listaAgregados
     * @return array
     */
    private function loadRetenciones($item, $indiceItem, &$listaAgregados = null)
    {
        $__retenciones = [];
        if (!is_null($item))
            $retenciones = $this->getValueByXpath('autoretenciones', $item);
        else
            $retenciones = $this->getValueByXpath("//{$this->root}/autoretenciones", $item);
        if (!is_null($retenciones)) {
            // Si solo es un elemento vendra un objeto
            $retenciones = (array)$retenciones;
            if (is_object($retenciones['tipo']))
                $retenciones = ['tipo' => array($retenciones['tipo'])];
        } else
            $retenciones = ['tipo' => []];

        foreach ($retenciones['tipo'] as $k => $retencion) {
            $dataItemRetencion = [];
            $sw = false;
            foreach (MapXpathDhl::$xpathRetenciones as $key => $xpath) {
                $objeto = $this->getValueByXpath($xpath, $retencion);
                if (!is_null($objeto)) {
                    $dataItemRetencion[$key] = (string)$objeto;
                    if ($dataItemRetencion[$key] !== '')
                        $sw = true;
                }
            }
            if ($sw) {
                $dataItemRetencion = $this->buildItemImpuesto($dataItemRetencion, $indiceItem, $k, 'retención', 'retencion', $listaAgregados);
                $__retenciones[$dataItemRetencion['codigo']] = $dataItemRetencion['impuesto'];
            }
        }
        return $__retenciones;
    }

    /**
     * Funcion para cargar la información adicional del array estatico
     * @param $item
     * @return array
     */
    private function loadAdicionales($item)
    {
        $dataAdicionales = [];
        foreach (MapXpathDhl::$camposItemsInformacionAdicional as $key => $campo) {
            if (isset($item->{$key})) {
                $dataAdicionales[$campo] = (string)$item->{$key};
            } else {
                $dataAdicionales[$campo] = "";
            }
        }
        //incluyendo los campos que deben excluirse al momento de generar el XML-UBL
        if (isset($dataAdicionales['descripcion'])) {
            $dataAdicionales['ddo_informacion_adicional_excluir_xml'][] = 'descripcion';
        }
        return $dataAdicionales;
    }

    /**
     * Funcion para cargar las propiedades adicionales del array estatico
     * @param $item
     * @return array
     */
    private function loadPropiedades($item)
    {
        $dataPropiedades = [];
        foreach (MapXpathDhl::$camposItemsPropiedadesAdicionales as $key => $campo) {
            if (isset($item->{$key})) {
                $dataPropiedades[$campo] = (string)$item->{$key};
            }
        }
        return $dataPropiedades;
    }

    /**
     * Funcion para formatear las fechas de los array estaticos
     * @param $date
     * @return array
     */
    private function formatDate(string $date)
    {
        try {
            if (preg_match('/^([0-2][0-9]|(3)[0-1])(\/)(((0)[0-9])|((1)[0-2]))(\/)\d{4}$/', $date)) {
                $date = Carbon::parse(str_replace('/', '-', $date));
                $date = $date->toDateString();
                $date = date("Y-m-d", strtotime($date));
            }
        } catch (\Exception $e) {
            $date = '';
        }
        return $date;
    }

    /**
     * Obtiene el valor de un nodo dado su XPATH
     *
     * @param string $xpath
     * @param null $customXML
     * @return \SimpleXMLElement|null
     */
    private function getValueByXpath(string $xpath, $customXML = null)
    {
        if (is_null($customXML)) {
            $obj = $this->xml->xpath($xpath);
            if ($obj)
                return $obj[0];
        } else {
            $obj = $customXML->xpath($xpath);
            if ($obj)
                return $obj[0];
        }
        return null;
    }

    /**
     * Traduce el error generado al cargar intentar cargar un archivo XMl
     *
     * @param $errores
     */
    private function errorXml($errores)
    {
        foreach ($errores as $error) {
            $stringError = '';
            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $stringError .= 'Advertencia ' . $error->code . ': ';
                    break;
                case LIBXML_ERR_ERROR:
                    $stringError .= 'Error ' . $error->code . ': ';
                    break;
                case LIBXML_ERR_FATAL:
                    $stringError .= 'Error Fatal ' . $error->code . ': ';
                    break;
            }
            $stringError .= trim($error->message) . ' en la Línea: ' . $error->line . ', Columna: ' . $error->column;
            if ($error->file)
                $stringError .= ' (Archivo: ' . $error->file . ')';
            $this->errores[] = $stringError;
        }
    }

    /**
     * Retorna un objeto del tipo JsonDocumentBUilder
     *
     */
    public function getJsonDocumentBuilder()
    {
        $json = new JsonDocumentBuilder($this->cabecera, $this->items, $this->listaImpuestos, $this->listaRetenciones, $this->impuestosRegistrados);
        // Log::info((array)$json);
        return $json;
    }
}