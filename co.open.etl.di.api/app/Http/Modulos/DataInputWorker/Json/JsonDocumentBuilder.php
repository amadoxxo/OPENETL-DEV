<?php
namespace App\Http\Modulos\DataInputWorker\Json;

use Illuminate\Support\Facades\Log;
use App\Http\Modulos\DataInputWorker\Utils\VP;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use function Symfony\Component\Debug\Tests\testHeader;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\DataInputWorker\Excel\ColumnsExcel;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Parametros\SectorSalud\DocumentoReferenciado\ParametrosSaludDocumentoReferenciado;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Construye el json para un documento que sera procesado por medio de DI
 *
 * Class JsonDocumentBuilder
 * @package App\Http\Modulos\DataInputWorker\Json
 */
class JsonDocumentBuilder {
    use FechaVigenciaValidations;
    
    /**
     * Almacena la información de la cabecera de documento, los campos adicionales y la información adicional que el
     * documento puede gestionar.
     *
     * @var mixed
     */
    private $cabecera;

    /**
     * Almacena la informacion a ser cargada en los iteme, incluye la información adicional de items y la imformación
     * optativa asociada a los mismos
     *
     * @var array
     */
    private $items = [];

    /**
     *  Contiene las columnas adicionales de cabecera que se han incluido en el excel.
     *
     * @var array
     */
    private $columnasOptativasCabecera = [];

    /**
     * Contiene las columnas que forman parte de la información adicional de cabecera.
     *
     * @var array
     */
    private $columnasInformacionAdicionalCabecera = [];

    /**
     *  Contiene las columnas adicionales de cabecera que se han incluido en el excel.
     *
     * @var array
     */
    private $columnasOptativasItems = [];

    /**
     *  Contiene las columnas de retenciones sugeridas de items (no obligtorias) que pueden llegar en el excel.
     *
     * @var array
     */
    private $columnasRetencionesSugeridasItems = [
        'retencion_sugerida_reteiva_descripcion',
        'retencion_sugerida_reteiva_porcentaje',
        'retencion_sugerida_reteiva_base',
        'retencion_sugerida_reteiva_valor',
        'retencion_sugerida_reteiva_base_moneda_extranjera',
        'retencion_sugerida_reteiva_valor_moneda_extranjera',
        'retencion_sugerida_retefuente_descripcion',
        'retencion_sugerida_retefuente_porcentaje',
        'retencion_sugerida_retefuente_base',
        'retencion_sugerida_retefuente_valor',
        'retencion_sugerida_retefuente_base_moneda_extranjera',
        'retencion_sugerida_retefuente_valor_moneda_extranjera',
        'retencion_sugerida_reteica_descripcion',
        'retencion_sugerida_reteica_porcentaje',
        'retencion_sugerida_reteica_base',
        'retencion_sugerida_reteica_valor',
        'retencion_sugerida_reteica_base_moneda_extranjera',
        'retencion_sugerida_reteica_valor_moneda_extranjera'
    ];

    /**
     * Contiene las columnas que forman parte de la información adicional items.
     *
     * @var array
     */
    private $columnasInformacionAdicionalItems = [];

    /**
     * Lista de impuestos que se utilizan el excel.
     *
     * @var array
     */
    private $listaImpuestos = [];

    /**
     * Lista de retenciones que se utilizan el excel.
     *
     * @var array
     */
    private $listaRetenciones = [];

    /**
     * Lista de impuestos registrados y activos en etl_openmain
     *
     * @var array
     */
    private $impuestosRegistrados = [];

    /**
     * Total Valor de los Items sin Impuestos.
     *
     * @var float
     */
    private $cdo_valor_sin_impuestos = 0.0;

    /**
     * Total Valor sin Impuestos Moneda Extranjera.
     *
     * @var float
     */
    private $cdo_valor_sin_impuestos_moneda_extranjera = 0.0;

    /**
     * Total Impuestos.
     *
     * @var float
     */
    private $cdo_impuestos = 0.0;

    /**
     * Total Impuestos Moneda Extranjera.
     *
     * @var float
     */
    private $cdo_impuestos_moneda_extranjera = 0.0;

    /**
     * Total Retenciones.
     *
     * @var float
     */
    private $cdo_retenciones = 0.0;

    /**
     * Total Retenciones Moneda Extranjera.
     *
     * @var float
     */
    private $cdo_retenciones_moneda_extranjera = 0.0;

    /**
     * Total (cdo_valor_sin_impuestos + cdo_impuestos).
     *
     * @var float
     */
    private $cdo_total = 0.0;

    /**
     * Total Moneda Extranjera.
     *
     * @var float
     */
    private $cdo_total_moneda_extranjera = 0.0;

    /**
     * Total Cargos.
     *
     * @var float
     */
    private $cdo_cargos = 0.0;

    /**
     * Total Cargos Moneda Extranjera.
     *
     * @var float
     */
    private $cdo_cargos_moneda_extranjera = 0.0;

    /**
     * Total Descuentos.
     *
     * @var float
     */
    private $cdo_descuentos = 0.0;

    /**
     * Total Descuentos Moneda Extranjera.
     *
     * @var float
     */
    private $cdo_descuentos_moneda_extranjera = 0.0;

    /**
     * Total Retneciones Suegeridas.
     *
     * @var float
     */
    private $cdo_retenciones_sugeridas = 0.0;

    /**
     * Total Retenciones Sugeridas Moneda Extranjera.
     *
     * @var float
     */
    private $cdo_retenciones_sugeridas_moneda_extranjera = 0.0;

    /**
     * Total Anticipos.
     *
     * @var float
     */
    private $cdo_anticipo = 0.0;

    /**
     * Total Anticipos Moneda Extranjera.
     *
     * @var float
     */
    private $cdo_anticipo_moneda_extranjera = 0.0;

    /**
     * Array para recibir la información relacionado con Sector Salud
     *
     * @var array
     */
    private $sectorSalud = [];

    /**
     * JsonDocumentBuilder constructor.
     *
     * @param mixed $cabecera Información de cabecera del documento
     * @param array $items Items del documento
     * @param array $listaImpuestos Impuestos del documento
     * @param array $listaRetenciones Retenciones del documento
     * @param array $impuestosRegistrados
     * @param string|null $client Define el cliente de Open para el cual se está realizando el proceso
     * @param string|null $origen Origen de procesamiento, ej: manual
     * @param array $sectorSalud
     * 
     */
    public function __construct($cabecera, array $items, array $listaImpuestos, array $listaRetenciones, array $impuestosRegistrados, string $client = null, string $origen = null, array $sectorSalud = []){
        $this->cabecera             = $cabecera;
        $this->items                = $items;
        $this->listaImpuestos       = $listaImpuestos;
        $this->listaRetenciones     = $listaRetenciones;
        $this->impuestosRegistrados = $impuestosRegistrados;
        $this->client               = $client;
        $this->origen               = $origen;
        $this->sectorSalud          = $sectorSalud;

        //SETTING EXCEL CLASS TO BE USED.
        if (!empty($this->client)) {
            $this->classExcel = "App\\Http\\Modulos\\DataInputWorker\\{$this->client}\\ColumnsExcel{$this->client}";
        } else  {
            $this->classExcel = ColumnsExcel::class;
        }
    }

    /**
     * Asigna la informción de cabecera de documento, la adicional y la opcional al mismo.
     *
     * @param mixed $cabecera
     */
    public function setCabecera($cabecera): void {
        $this->cabecera = $cabecera;
    }

    /**
     * Agrega la data de un nuevo item del documento, su información adicional y la información adicional que puede estar
     * asociada al mismo.
     *
     * @param $data
     */
    public function additem($data): void  {
        $this->items[] = $data;
    }

    /**
     * Construye un documento a ser registrado en funcion de los datos obtenidos del excel.
     *
     * @param string $docType
     * @return array
     */
    public function buildDocumento(string $docType) {
        $documento = [];
        $tributos = [];
        $retenciones = [];

        $this->insertarDatosCabeceraObligatorios($documento, $docType, $tributos, $retenciones);

        $items = [];
        foreach ($this->items as $k => $item) {
            // Verifica si en la informacicón adicional del item existen retenciones sugeridas
            // En ese caso, las retenciones sugeridas deben agregarse en ddo_detalle_retenciones_sugeridas del item
            $arrReteIva = [];
            $arrReteIca = [];
            $arrReteFte = [];
            foreach($item['informacion_adicional'] as $clave => $valor) {
                if(in_array($clave, $this->columnasRetencionesSugeridasItems) && strstr($clave, 'reteiva') !== false) {
                    $arrReteIva[$clave] = $valor;
                    unset($item['informacion_adicional'][$clave]);
                }

                if(in_array($clave, $this->columnasRetencionesSugeridasItems) && strstr($clave, 'reteica') !== false) {
                    $arrReteIca[$clave] = $valor;
                    unset($item['informacion_adicional'][$clave]);
                }

                if(in_array($clave, $this->columnasRetencionesSugeridasItems) && strstr($clave, 'retefuente') !== false) {
                    $arrReteFte[$clave] = $valor;
                    unset($item['informacion_adicional'][$clave]);
                }
            }

            if(!array_key_exists('ddo_detalle_retenciones_sugeridas', $item)) $item['ddo_detalle_retenciones_sugeridas'] = [];
            if(!empty($arrReteIva)) $this->agregarRetencionesSugeridas($item, $arrReteIva, 'reteiva', 'item');
            if(!empty($arrReteIca)) $this->agregarRetencionesSugeridas($item, $arrReteIca, 'reteica', 'item');
            if(!empty($arrReteFte)) $this->agregarRetencionesSugeridas($item, $arrReteFte, 'retefuente', 'item');

            $ddo_secuencia = $k + 1;
            $items[] = $this->insertarDatosItem($item, "$ddo_secuencia", $tributos, $retenciones, $docType, $documento['cdo_fecha']);
        }

        $documento['items'] = $items;

        if (count($tributos))
            $documento['tributos'] = $tributos;

        if (count($retenciones))
            $documento['retenciones'] = $retenciones;

        $this->sumarizar($documento, $docType);

        if(!empty($this->sectorSalud))
            $this->buildSectorSalud($documento);

        return $documento;
    }

    /**
     * Obtiene la lista de descuentos extendidos.
     *
     * @param $descuentosExtendidos
     * @param bool $cabecera
     * @return array
     */
    private function getDescuentosExtendidos($descuentosExtendidos, $cabecera = false) {
        $extendidos = [];
        foreach ($descuentosExtendidos as $key => $descuento) {
            $nombreColumnas = [
                "descuento_{$key}"             => 'razon',
                "descuento_{$key}_descripcion" => 'razon',
                "descuento_{$key}_porcentaje"  => 'porcentaje'
            ];

            if ($cabecera)
                $nombreColumnas["descuento_{$key}_codigo"] = 'cde_codigo';

            $__descuento = $this->buildSubSection([], $descuento, ['porcentaje'], $nombreColumnas);
            $__descuento['nombre'] = $this->getNameCargoDescuento($key);

            $nombreColumnas = [
                "descuento_{$key}_base" => 'base',
                "descuento_{$key}_valor" => 'valor'
            ];
            $datos = $this->buildSubSection([], $descuento, ['base', 'valor'], $nombreColumnas);
            if (!is_null($datos))
                $__descuento['valor_moneda_nacional'] = $datos;

            $nombreColumnas = [
                "descuento_{$key}_base_moneda_extranjera" => 'base',
                "descuento_{$key}_valor_moneda_extranjera" => 'valor'
            ];
            $datos = $this->buildSubSection([], $descuento, ['base', 'valor'], $nombreColumnas);
            if (!is_null($datos))
                $__descuento['valor_moneda_extranjera'] = $datos;

            //Solo se crea el descuento si hay otros campos adicionales al nombre
            if (!(count($__descuento) == 1 && array_key_exists('nombre', $__descuento))) {
                $extendidos[] = $__descuento;
            }
        }
        return $extendidos;
    }

    /**
     * Agrega los datos de cabecera y modelos opcionales al documento.
     * 
     * @param $documento  Información del documento electrónico
     * @param $docType    Tipo del documento FC|NC|ND|DS
     * @param array $tributos     Tributos del documento electrónico
     * @param array $retenciones  Retenciones del documento electrónico
     */
    private function insertarDatosCabeceraObligatorios(&$documento, $docType, array &$tributos, array &$retenciones) {
        if ($docType === ConstantsDataInput::FC) {
            $columnasCabecera = $this->classExcel::$columnasObligatoriasCabeceraFC;
        } elseif ($docType === ConstantsDataInput::DS) {
            $columnasCabecera = array_replace($this->classExcel::$columnasObligatoriasCabeceraFC, array(2 => "nit_receptor", 3 => "nit_vendedor"));
        } elseif ($docType === ConstantsDataInput::DS_NC) {
            $columnasCabecera = array_replace($this->classExcel::$columnasObligatoriasCabeceraNCND, array(2 => "nit_receptor", 3 => "nit_vendedor"));
        } else {
            $columnasCabecera = $this->classExcel::$columnasObligatoriasCabeceraNCND;
        }

        $columnasCabecera = array_merge($columnasCabecera, ['cdo_redondeo', 'cdo_redondeo_moneda_extranjera']);
        $evitar = [
            'cod_forma_de_pago',
            'cod_medio_de_pago',
            'fecha_vencimiento_pago',
            'identificador_pago',
            'observacion'
        ];
        $numericas = ['trm'];
        // Agregado las columnas obligatorias de cabecera
        $obligatoriasCabecera = $this->cabecera['obligatorias'];
        foreach ($columnasCabecera as $col) {
            if (!in_array($col, $evitar)) {
                if (in_array($col, $numericas) && isset($obligatoriasCabecera[$col])) {
                    if ($this->client == 'FNC'){
                        if ($this->tipo == ConstantsDataInput::FC) {
                            $documento[$this->classExcel::$columnasCabeceraMapaFC[$col]] = $this->numberFormat($obligatoriasCabecera[$col]);
                            continue;
                        }
                    }
                    $documento[$this->classExcel::$columnasCabeceraMapa[$col]] = $this->numberFormat($obligatoriasCabecera[$col]);
                }
                else {
                    if ($this->client == 'FNC'){
                        if ($this->tipo == ConstantsDataInput::FC) {
                            if (isset($obligatoriasCabecera[$col]))
                                $documento[$this->classExcel::$columnasCabeceraMapaFC[$col]] = $obligatoriasCabecera[$col];
                            continue;
                        }
                    }
                    if (array_key_exists($col, $obligatoriasCabecera)) {
                        $columnasMapa = ($docType === ConstantsDataInput::DS || $docType === ConstantsDataInput::DS_NC) ? $this->classExcel::$columnasCabeceraMapaDS : $this->classExcel::$columnasCabeceraMapa;
                        $documento[$columnasMapa[$col]] = $obligatoriasCabecera[$col];
                    }
                }
            }
        }

        $obligatoriasCabecera = array_merge($documento, $obligatoriasCabecera);

        if (is_array($obligatoriasCabecera['observacion']))
            $documento[$this->classExcel::$columnasCabeceraMapa['observacion']] = $obligatoriasCabecera['observacion'];
        else
            $documento[$this->classExcel::$columnasCabeceraMapa['observacion']] = explode('|', trim($obligatoriasCabecera['observacion']));
        if (!isset($documento['cdo_redondeo'])) {
            $documento['cdo_redondeo'] = "0.00";
        }

        if (!isset($documento['cdo_redondeo_moneda_extranjera'])) {
            $documento['cdo_redondeo_moneda_extranjera'] = "0.00";
        }

        if (empty((float) $documento['cdo_redondeo'])) {
            $documento['cdo_redondeo'] = "0.00";
        } else {
            $documento['cdo_redondeo'] = $this->numberFormat($documento['cdo_redondeo']);
        }

        if (empty((float) $documento['cdo_redondeo_moneda_extranjera']))
            $documento['cdo_redondeo_moneda_extranjera'] = "0.00";
        else
            $documento['cdo_redondeo_moneda_extranjera'] = $this->numberFormat($documento['cdo_redondeo_moneda_extranjera']);

        if ($docType == ConstantsDataInput::NC || $docType == ConstantsDataInput::ND || $docType == ConstantsDataInput::NC_ND || $docType == ConstantsDataInput::DS_NC) {
            $nombreColumnas = [
                'prefijo_factura',
                'consecutivo_factura',
                'cufe_factura',
                'fecha_factura',
            ];

            if ($this->client == 'FNC') {
                array_push($nombreColumnas, 'fecha_emision');
            }

            $cdo_documento_referencia = $this->buildSubSection($nombreColumnas, $obligatoriasCabecera);

            if ($cdo_documento_referencia) {
                $cdo_documento_referencia['clasificacion'] = ($docType == ConstantsDataInput::DS_NC) ? 'DS' : 'FC';
                $documento['cdo_documento_referencia'] = [$cdo_documento_referencia];
            }

            // cdo_conceptos_correccion -> solo para notas de credito y debito
            $nombreColumnas = [
                'cod_concepto_correccion',
                'observacion_correccion'
            ];

            $conceptosCorreccion = $this->buildSubSection($nombreColumnas, $obligatoriasCabecera);
            if ($conceptosCorreccion) {
                $documento['cdo_conceptos_correccion'] = [$conceptosCorreccion];
                unset($obligatoriasCabecera['cod_concepto_correccion']);
                unset($obligatoriasCabecera['cod_concepto_correccion']);
            }
        }

        // Medios pago
        $nombreColumnas = [
            'cod_forma_de_pago',
            'cod_medio_de_pago',
            'fecha_vencimiento_pago'
        ];
        $mediosPago = $this->buildSubSection($nombreColumnas, $obligatoriasCabecera);

        if ($mediosPago) {
            $dentificadoresPago = [];
            if (!empty($obligatoriasCabecera['identificador_pago'])) {
                $ids = explode('|', $obligatoriasCabecera['identificador_pago']);
                foreach ($ids as $id)
                    $dentificadoresPago[] = ['id' => $id];
            }

            $mediosPago['men_identificador_pago'] = $dentificadoresPago;

            if(!empty($this->sectorSalud) && array_key_exists('modalidades_contratacion_pago', $this->cabecera['informacion_adicional'])) {
                if(!empty($this->cabecera['informacion_adicional']['modalidades_contratacion_pago'])) {
                    $mediosPago = array_slice($mediosPago, 0, 1, true) +
                        ['atributo_fpa_codigo_id' => $this->cabecera['informacion_adicional']['modalidades_contratacion_pago']] +
                        array_slice($mediosPago, 1, count($mediosPago) - 1, true) ;

                    $mediosPago = array_slice($mediosPago, 0, 2, true) +
                        ['atributo_fpa_codigo_name' => 'salud_modalidades_pago.gc'] +
                        array_slice($mediosPago, 2, count($mediosPago) - 1, true) ;
                }
                
                unset($this->cabecera['informacion_adicional']['modalidades_contratacion_pago']);
            }

            $documento['cdo_medios_pago'] = [$mediosPago];
        }

        if(!empty($this->sectorSalud) && array_key_exists('futura_operacion_acreditacion', $this->cabecera['informacion_adicional'])) {
            if(!empty($this->cabecera['informacion_adicional']['futura_operacion_acreditacion']))
                $documento['atributo_top_codigo_id '] = $this->cabecera['informacion_adicional']['futura_operacion_acreditacion'];
            
            unset($this->cabecera['informacion_adicional']['futura_operacion_acreditacion']);
        }

        // Información adicional de cabecera
        $documento['cdo_informacion_adicional'] = $this->cabecera['informacion_adicional'];

        /*
         * Columnas opcionales
         */
        $opcionalesCabecera = $this->cabecera['opcionales'];
        // cdo_documento_adicional
        if (!empty($opcionalesCabecera['documento_adicional'])) {
            $adicionales = explode('|', $opcionalesCabecera['documento_adicional']);
            foreach ($adicionales as $item) {
                $valores = explode('~', $item);
                if (count($valores) === 5) {
                    $documentosAdicionales[] = [
                        'rod_codigo' => $valores[0],
                        'prefijo' => $valores[1],
                        'consecutivo' => $valores[2],
                        'cufe' => $valores[3],
                        'fecha_emision' => $valores[4]
                    ];
                }
            }
            if (isset($documentosAdicionales) && count($documentosAdicionales))
                $documento['cdo_documento_adicional'] =  $documentosAdicionales;
        }

        // cdo_periodo_facturacion
        $nombreColumnas = [
            'fecha_inicio_periodo',
            'hora_inicio_periodo',
            'fecha_fin_periodo',
            'hora_fin_periodo'
        ];
        $periodoFacturacion = $this->buildSubSection($nombreColumnas, $opcionalesCabecera);
        if ($periodoFacturacion)
            $documento['cdo_periodo_facturacion'] = $periodoFacturacion;

        // dad_orden_referencia
        $nombreColumnas = [
            'orden_referencia',
            'fecha_emision_orden_referencia'
        ];
        $ordenReferencia = $this->buildSubSection($nombreColumnas, $opcionalesCabecera);
        if ($ordenReferencia)
            $documento['dad_orden_referencia'] = $ordenReferencia;

        // dad_despacho_referencia
        $nombreColumnas = [
            'despacho_referencia',
            'fecha_emision_despacho_referencia'
        ];
        $despachoReferencia = $this->buildSubSection($nombreColumnas, $opcionalesCabecera);
        if ($despachoReferencia)
            $documento['dad_despacho_referencia'] = $despachoReferencia;

        // dad_recepcion_referencia
        $nombreColumnas = [
            'recepcion_referencia',
            'fecha_emision_recpcion_referecia'
        ];
        $recepcionReferencia = $this->buildSubSection($nombreColumnas, $opcionalesCabecera);
        if ($recepcionReferencia)
            $documento['dad_recepcion_referencia'] = $recepcionReferencia;

        // dad_condiciones_entrega
        $nombreColumnas = [
            'entrega_bienes_fecha_salida',
            'entrega_bienes_hora_salida',
            'entrega_bienes_cod_pais',
            'entrega_bienes_cod_departamento',
            'entrega_bienes_cod_ciudad_municipio',
            'entrega_bienes_cod_postal',
            'entrega_bienes_direccion',
        ];
        $condicionesEntrega = $this->buildSubSection($nombreColumnas, $opcionalesCabecera);
        if($condicionesEntrega)
            $documento['dad_condiciones_entrega'] = $condicionesEntrega;

        // dad_entrega_bienes_despacho
        $nombreColumnas = [
            'entrega_bienes_despacho_identificacion_transportador',
            'entrega_bienes_despacho_identificacion_transporte',
            'entrega_bienes_despacho_tipo_transporte',
            'entrega_bienes_despacho_fecha_solicitada',
            'entrega_bienes_despacho_hora_solicitada',
            'entrega_bienes_despacho_fecha_estimada',
            'entrega_bienes_despacho_hora_estimada',
            'entrega_bienes_despacho_fecha_real',
            'entrega_bienes_despacho_hora_real',
            'entrega_bienes_despacho_cod_pais',
            'entrega_bienes_despacho_cod_departamento',
            'entrega_bienes_despacho_cod_ciudad_municipio',
            'entrega_bienes_despacho_cod_postal',
            'entrega_bienes_despacho_direccion'
        ];
        $entregaBienesDespacho = $this->buildSubSection($nombreColumnas, $opcionalesCabecera);
        if($entregaBienesDespacho)
            $documento['dad_entrega_bienes_despacho'] = $entregaBienesDespacho;

        // dad_terminos_entrega
        $nombreColumnas = [
            'terminos_entrega_condiciones_pago',
            'terminos_entrega_cod_condicion_entrega_incoterms',
            'terminos_entrega_cod_pais',
            'terminos_entrega_cod_departamento',
            'terminos_entrega_cod_ciudad_municipio',
            'terminos_entrega_cod_postal_cod_postal',
            'terminos_entrega_direccion_terminos'
        ];
        $terminosEntrega = $this->buildSubSection($nombreColumnas, $opcionalesCabecera);
        if ($terminosEntrega)
            $documento['dad_terminos_entrega'] = $terminosEntrega;

        // cdo_cargos
        $nombreColumnas = ['cdo_cargo_descripcion', 'cdo_cargo_porcentaje'];
        $cargos = $this->buildSubSection($nombreColumnas, $opcionalesCabecera, ['cdo_cargo_porcentaje']);
        if ($cargos  && !empty($cargos)) {
            $nombreColumnas = ['cdo_cargo_base', 'cdo_cargo_valor'];
            $cargos['valor_moneda_nacional'] = $this->buildSubSection($nombreColumnas, $opcionalesCabecera, ['cdo_cargo_base', 'cdo_cargo_valor']);
            $nombreColumnas = ['cdo_cargo_base_moneda_extranjera', 'cdo_cargo_valor_moneda_extranjera'];
            $cargos['valor_moneda_extranjera'] = $this->buildSubSection($nombreColumnas, $opcionalesCabecera, ['cdo_cargo_base_moneda_extranjera', 'cdo_cargo_valor_moneda_extranjera']);
            $documento['cdo_detalle_cargos'] = [$cargos];
        } else {
            if (array_key_exists('cargos_extendido', $this->cabecera)) {
                $cargosExtendidos = $this->cabecera['cargos_extendido'];
                $documento['cdo_detalle_cargos'] = [];
                foreach ($cargosExtendidos as $Key => $cargo) {
                    $__cargo = $this->buildCargo($cargo, $Key);
                    if (!is_null($__cargo))
                        $documento['cdo_detalle_cargos'][] = $__cargo;
                }
            }
        }

        // cdo_descuentos
        if (!array_key_exists('descuentos_extendido', $this->cabecera) || empty($this->cabecera['descuentos_extendido'])) {
            $nombreColumnas = ['cdo_descuento_descripcion', 'cdo_descuento_porcentaje', 'cdo_descuento_codigo'];
            $descuentos = $this->buildSubSection($nombreColumnas, $opcionalesCabecera, ['cdo_descuento_porcentaje']);
            if ($descuentos && !empty($descuentos)) {
                $nombreColumnas = ['cdo_descuento_base', 'cdo_descuento_valor'];
                $descuentos['valor_moneda_nacional'] = $this->buildSubSection($nombreColumnas, $opcionalesCabecera, ['cdo_descuento_base', 'cdo_descuento_valor']);
                $nombreColumnas = ['cdo_descuento_base_moneda_extranjera', 'cdo_cargo_valor_moneda_extranjera'];
                $descuentos['valor_moneda_extranjera'] = $this->buildSubSection($nombreColumnas, $opcionalesCabecera, ['cdo_descuento_base_moneda_extranjera', 'cdo_cargo_valor_moneda_extranjera']);
                $documento['cdo_detalle_descuentos'] = [$descuentos];
            }
        } else {
            $descuentosExtendidos = $this->cabecera['descuentos_extendido'];
            $documento['cdo_detalle_descuentos'] = $this->getDescuentosExtendidos($descuentosExtendidos, true);
        }

        // cdo_detalle_anticipos
        $nombreColumnas = [
            'anticipo_identificado_pago',
            'anticipo_valor',
            'anticipo_valor_moneda_extranjera',
            'anticipo_fecha_recibio',
        ];

        $anticipo = $this->buildSubSection($nombreColumnas, $opcionalesCabecera, ['anticipo_identificado_pago', 'anticipo_valor', 'anticipo_valor_moneda_extranjera']);
        if (!is_null($anticipo) && count($anticipo) > 0 && isset($anticipo['ant_valor']) && floatval($anticipo['ant_valor']) > 0) {
            $documento['cdo_detalle_anticipos'] = [$anticipo];
        }

        if(!array_key_exists('cdo_detalle_retenciones_sugeridas', $documento)) $documento['cdo_detalle_retenciones_sugeridas'] = [];
        $this->agregarRetencionesSugeridas($documento, $opcionalesCabecera, 'reteiva', 'documento');
        $this->agregarRetencionesSugeridas($documento, $opcionalesCabecera, 'retefuente', 'documento');
        $this->agregarRetencionesSugeridas($documento, $opcionalesCabecera, 'reteica', 'documento');

        // Impuestos y retenciones de cabecera
        if (array_key_exists('impuestos', $this->cabecera)) {
            $temp = $this->agregarTributoRetencion($this->cabecera['impuestos'], null, $this->listaImpuestos, true, 'impuesto');
            if (!empty($temp))
                $tributos = array_merge($tributos, $temp);
        }

        if (array_key_exists('retenciones', $this->cabecera)) {
            $temp = $this->agregarTributoRetencion($this->cabecera['retenciones'], null, $this->listaImpuestos, true, 'impuesto');
            if (!empty($temp))
                $retenciones = array_merge($tributos, $temp);
        }
    }

    /**
     * Agrega una retención sugerida al documento o al item.
     *
     * @param array $arrDocItem Array del documento o del item en el cual se agregará la retención
     * @param array $arrOriginalData Array que contiene la data de la retención sugerida a agregar
     * @param string $nombreRetencion Nombre de la retención
     * @param string $afectacion Indica si al proceso afecta al documento o al item
     */
    private function agregarRetencionesSugeridas(array &$documento, array $opcionalesCabecera, string $nombreRetencion, string $afectacion = 'documento') {
        if (
            array_key_exists("retencion_sugerida_{$nombreRetencion}_descripcion", $opcionalesCabecera) &&
            array_key_exists("retencion_sugerida_{$nombreRetencion}_porcentaje", $opcionalesCabecera) &&
            array_key_exists("retencion_sugerida_{$nombreRetencion}_base", $opcionalesCabecera) &&
            array_key_exists("retencion_sugerida_{$nombreRetencion}_valor", $opcionalesCabecera) &&
            array_key_exists("retencion_sugerida_{$nombreRetencion}_base_moneda_extranjera", $opcionalesCabecera) &&
            array_key_exists("retencion_sugerida_{$nombreRetencion}_valor_moneda_extranjera", $opcionalesCabecera)
        ) {
            $valorLocal = $this->numberFormat($opcionalesCabecera["retencion_sugerida_{$nombreRetencion}_valor"]);
            $valorExtranjera = $this->numberFormat($opcionalesCabecera["retencion_sugerida_{$nombreRetencion}_base_moneda_extranjera"]);
            if ($valorLocal !== '0.00' || $valorExtranjera !== '0.00') {
                $retencion = [
                    'tipo' => mb_strtoupper($nombreRetencion),
                    'razon' => $opcionalesCabecera["retencion_sugerida_{$nombreRetencion}_descripcion"],
                    'porcentaje' => $this->numberFormat($opcionalesCabecera["retencion_sugerida_{$nombreRetencion}_porcentaje"]),
                    'valor_moneda_nacional' => [
                        'base' => $this->numberFormat($opcionalesCabecera["retencion_sugerida_{$nombreRetencion}_base"]),
                        'valor' => $valorLocal
                    ],
                    'valor_moneda_extranjera' => [
                        'base' => $this->numberFormat($opcionalesCabecera["retencion_sugerida_{$nombreRetencion}_base_moneda_extranjera"]),
                        'valor' => $valorExtranjera
                    ]
                ];

                if($afectacion == 'documento')
                    $documento['cdo_detalle_retenciones_sugeridas'][] = $retencion;
                else
                    $documento['ddo_detalle_retenciones_sugeridas'][] = $retencion;
            }
        }
    }

    /**
     * Retorna el valor del porcentaje.
     *
     * @param $registro
     * @param $extendido
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValueForIddPorcentaje($registro, $extendido, $item, $modo) {
        $key = !$extendido ? "porcentaje_{$item}" : "porcentaje_{$item}_{$modo}";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Retorna el valor para el impuesta en moneda local.
     *
     * @param $registro
     * @param $extendido
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValueForIddValor($registro, $extendido, $item, $modo) {
        $key = !$extendido ? "valor_{$item}" : "valor_{$item}_{$modo}";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Retorna la base para el impuesta en moneda local.
     *
     * @param $registro
     * @param $extendido
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValueForIddBase($registro, $extendido, $item, $modo) {
        $key = !$extendido ? "base_{$item}" : "base_{$item}_{$modo}";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Retorna el codigo de la unidad asociada al tributo-retencion.
     *
     * @param $registro
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValueForUndCodigo($registro, $item, $modo) {
        $key = "{$modo}_unidad_{$item}_{$modo}_unidad";
        if (array_key_exists($key, $registro))
            return $registro[$key];
        return '';
    }

    /**
     * Retorna la base para la unidad de medida en moneda local.
     *
     * @param $registro
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValueForUnidadMedida($registro, $item, $modo) {
        $key = "{$modo}_unidad_{$item}_{$modo}_base";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Retorna la base para la unidad de medida en moneda extranjera.
     *
     * @param $registro
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValueForUnidadMedidaMonedaExtranjera($registro, $item, $modo) {
        $key = "{$modo}_unidad_{$item}_{$modo}_base_moneda_extranjera";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Retorna valor unitario para la unidad de medida en moneda local.
     *
     * @param $registro
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValorUnitarioForUnidadMedida($registro, $item, $modo) {
        $key = "{$modo}_unidad_{$item}_{$modo}_valor_unitario";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Retorna valor unitario para la unidad de medida en moneda extranjera.
     *
     * @param $registro
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValorUnitarioForUnidadMedidaMonedaExtranjera($registro, $item, $modo) {
        $key = "{$modo}_unidad_{$item}_{$modo}_valor_unitario_moneda_extranjera";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Retorna el valor para el impuesta en moneda extranjera.
     *
     * @param $registro
     * @param $extendido
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValueForIddValorMonedaExtranjera($registro, $extendido, $item, $modo) {
        $key = !$extendido ? "valor_{$item}_moneda_extranjera" : "valor_{$item}_{$modo}_moneda_extranjera";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Retorna la base para el impuesta en moneda extranjera.
     *
     * @param $registro
     * @param $extendido
     * @param $item
     * @param $modo
     * @return string
     */
    private function getValueForIddBaseMonedaExtranjera($registro, $extendido, $item, $modo) {
        $key = !$extendido ? "base_{$item}_moneda_extranjera" : "base_{$item}_{$modo}_moneda_extranjera";
        if (array_key_exists($key, $registro))
            return $this->numberFormat($registro[$key]);
        return '0.00';
    }

    /**
     * Construye una sección de impuesto o renteciones.
     *
     * @param array $registro
     * @param $ddo_secuencia
     * @param array $listaVerificar
     * @param bool $extendido
     * @param string $modo
     * @return array
     */
    private function agregarTributoRetencion(array $registro, $ddo_secuencia, array $listaVerificar, bool $extendido = false, string $modo = 'impuesto') {
        $procesado = [];
        $objetos = [];
        foreach ($listaVerificar as $item) {
            $item = strtolower($item);
            if (array_key_exists($item, $procesado))
                continue;
            else
                $procesado[$item] = true;
            $codigo = '';
            if ($item === 'iva')
                $codigo = '01';
            elseif($item === 'impuesto_consumo')
                $codigo = '04';
            elseif ($item === 'ica')
                $codigo = '03';
            else
                $codigo = $item;
            if (array_key_exists( !$extendido ? "valor_{$item}" : "valor_{$item}_{$modo}" , $registro)) {
                $objeto = [
                    'ddo_secuencia' => $ddo_secuencia,
                    'tri_codigo' => $codigo,
                    'iid_valor' => $this->getValueForIddValor($registro, $extendido, $item, $modo),
                    'iid_valor_moneda_extranjera' => $this->getValueForIddValorMonedaExtranjera($registro, $extendido, $item, $modo),
                    'iid_porcentaje' => [
                        "iid_base" => $this->getValueForIddBase($registro, $extendido, $item, $modo),
                        "iid_base_moneda_extranjera" => $this->getValueForIddBaseMonedaExtranjera($registro, $extendido, $item, $modo),
                        "iid_porcentaje" => $this->getValueForIddPorcentaje($registro, $extendido, $item, $modo),
                    ],
                ];

                if (strtolower($item) === 'iva') {
                    $objeto['iid_motivo_exencion'] = array_key_exists('iid_motivo_exencion', $registro) ? $registro['iid_motivo_exencion'] : '';
                }

                if (strtolower($item) === 'ica') {
                    $objeto['iid_motivo_exencion'] = array_key_exists('icd_motivo_exencion', $registro) ? $registro['icd_motivo_exencion'] : '';
                }

                if (strtolower($item) === 'impuesto_consumo') {
                    $objeto['iid_motivo_exencion'] = array_key_exists('ivd_motivo_exencion', $registro) ? $registro['ivd_motivo_exencion'] : '';
                }

                if ($extendido && $this->getValueForUndCodigo($registro, $item, $modo) !== '') {
                    $objeto["iid_unidad"] = [
                        "und_codigo" => $this->getValueForUndCodigo($registro, $item, $modo),
                        "iid_base_unidad_medida" => $this->getValueForUnidadMedida($registro, $item, $modo),
                        "iid_base_unidad_medida_moneda_extranjera" => $this->getValueForUnidadMedidaMonedaExtranjera($registro, $item, $modo),
                        "iid_valor_unitario" => $this->getValorUnitarioForUnidadMedida($registro, $item, $modo),
                        "iid_valor_unitario_moneda_extranjera" => $this->getValorUnitarioForUnidadMedidaMonedaExtranjera($registro, $item, $modo)
                    ];
                }
                $objetos[] = $objeto;
            }
        }

        $newObjects = [];
        foreach ($objetos as $objeto) {
            if ((isset($objeto['iid_porcentaje']['iid_base']) && floatval($objeto['iid_porcentaje']['iid_base']) > 0.00) ||
                (isset($objeto['iid_porcentaje']['iid_base_moneda_extranjera']) && floatval($objeto['iid_porcentaje']['iid_base_moneda_extranjera']) > 0.00))
                $newObjects[] = $objeto;
        }

        return $newObjects;
    }

    /**
     * Construye una sub-sección de datos en base a un mapeo definido por $nombreColumnas.
     *
     * @param array $nombreColumnas
     * @param array $datos
     * @param array $numericos
     * @param array $customMap
     * @return array
     */
    private function buildSubSection(array $nombreColumnas, array &$datos, $numericos = [], $customMap = []) {
        $valores = [];
        if (empty($customMap)) {
            foreach ($nombreColumnas as $col) {
                if (array_key_exists($col, $datos) && $datos[$col] != null && $datos[$col] != '') {
                    if (in_array($col, $numericos))
                        $valores[$this->classExcel::$translateColumn[$col]] = $this->numberFormat($datos[$col]);
                    else
                        $valores[$this->classExcel::$translateColumn[$col]] = $datos[$col];
                }
            }
        }
        else {
            foreach ($customMap as $key => $col) {
                if (array_key_exists($key, $datos) && $datos[$key] != null && $datos[$key] != '') {
                    if (in_array($col, $numericos))
                        $valores[$col] = $this->numberFormat($datos[$key]);
                    else
                        $valores[$col] = $datos[$key];
                }
            }
        }
        return !empty($valores) ? $valores : null;
    }

    private function hasValue(array $data, $key) {
        return array_key_exists($key, $data) && !empty($data[$key]);
    }

    /**
     * Construye los datos de un item y modelos opcionales al item.
     *
     * @param array $registro Array con la informanción del documento
     * @param string $ddo_secuencia Secuencia del ítem
     * @param array $tributos Array para almcenar los tributos
     * @param array $retenciones Array para almcenar las retenciones
     * @param string $docType Tipo del documento en proceso
     * @param string $fechaDocumento Fecha del documento en proceso
     * @return array
     */
    private function insertarDatosItem(array $registro, string $ddo_secuencia, array &$tributos, array &$retenciones, string $docType, string $fechaDocumento) {
        $item = ['ddo_secuencia' => $ddo_secuencia];
        $columnasDetalles = [
            'tipo_item',
            'cod_clasificacion_producto',
            'cod_producto',
            'descripcion_1',
            'descripcion_2',
            'descripcion_3',
            'cantidad',
            'cantidad_paquete',
            'cod_unidad_medida',
            'valor_unitario',
            'total',
            'valor_unitario_moneda_extranjera',
            'total_moneda_extranjera',
            'muestra_comercial',
            'nit_mandatario',
            'cod_tipo_documento_mandatario'
        ];

        $numericas = [
            'cantidad',
            'cantidad_paquete',
            'valor_unitario',
            'total',
            'valor_unitario_moneda_extranjera',
            'total_moneda_extranjera',
        ];

        $obligatorias = $registro['obligatorias'];
        foreach ($columnasDetalles as $col) {
            if (array_key_exists($col, $obligatorias)) {
                if (in_array($col, $numericas)) {
                    $item[$this->classExcel::$translateColumn[$col]] = $this->numberFormat($obligatorias[$col]);
                } else {
                    $item[$this->classExcel::$translateColumn[$col]] = $obligatorias[$col];
                }
            }
        }

        if ($this->hasValue($obligatorias, 'cod_precio_referencia') || $this->hasValue($obligatorias, 'valor_muestra') || $this->hasValue($obligatorias, 'valor_muestra_moneda_extranjera')) {
            $ddo_precio_referencia = [];
            if (array_key_exists('cod_precio_referencia', $obligatorias))
                $ddo_precio_referencia['pre_codigo'] = $obligatorias['cod_precio_referencia'];
            if (array_key_exists('valor_muestra', $obligatorias))
                $ddo_precio_referencia['ddo_valor_muestra'] = $obligatorias['valor_muestra'];
            if (array_key_exists('valor_muestra_moneda_extranjera', $obligatorias))
                $ddo_precio_referencia['ddo_valor_muestra_moneda_extranjera'] = $obligatorias['valor_muestra_moneda_extranjera'];

            $item['ddo_precio_referencia'] = $ddo_precio_referencia;
        }

        // Para los documentos soporte de debe incluir la sección ddo_fecha_compra
        if ($docType == ConstantsDataInput::DS || $docType == ConstantsDataInput::DS_NC) {
            $item['ddo_fecha_compra'] = [
                'fecha_compra' => $fechaDocumento,
                'codigo'       => $obligatorias['cod_forma_generacion_y_transmision']
            ];
        }

        if (array_key_exists('notas', $obligatorias)) {
            $ddo_notas = explode('|', $obligatorias['notas']);
            $item['ddo_notas'] = [];
            foreach ($ddo_notas as $key => $nota) {
                if (!empty(trim($nota))) {
                    $item['ddo_notas'][] = trim($nota);
                }
            }

            if ($this->client) {
                foreach ($item['ddo_notas'] as $key=>$nota) {
                    $posicion = explode('^', $nota);

                    if (count($posicion) > 1) {
                        $valor = $registro['valor'] ?? '';
                        $moneda = $registro['moneda'] ?? '';
                        $unidadBase = $registro['unidadBase'] ?? '';
                        $cantidadBase = $registro['cantidadBase'] ?? '';
                        $valorItem = $registro['valorItem'] ?? '';

                        $item['ddo_notas'][$key] = trim(
                            trim($posicion[0] ?? '')
                            . ' - '
                            . $valor . ': '
                            . number_format(floatval(trim($posicion[1] ?? '')), 2, '.', '')
                            . ' - ' . $moneda . ': ' . trim($posicion[2] ?? '') . ' - '
                            . $unidadBase . ': ' . trim($posicion[4] ?? '') . ' - ' . $cantidadBase . ': '
                            . trim($posicion[3] ?? '') . ' - '. $valorItem . ': ' .
                            number_format(floatval(trim($posicion[5] ?? '')), 2, '.', '')
                        );
                    }
                }
            }
        }

        // ddo_datos_tecnicos
        $datosTecnicos = [];
        if (array_key_exists('datos_tecnicos', $obligatorias) && !empty($obligatorias['datos_tecnicos'])) {
            $datos = explode('|', $obligatorias['datos_tecnicos']);
            foreach ($datos as $dato)
                $datosTecnicos[] = ['descripcion' => $dato];
        }
        $item['ddo_datos_tecnicos'] = $datosTecnicos;

        // Agregando campos adicionales
        $opcionales = $registro['opcionales'];
        $cargos_extendido = $registro['cargos_extendido'] ?? [];
        $nombreColumnas = [
            'ddo_identificador',
            'marca',
            'modelo',
            'cod_vendedor',
            'cod_vendedor_subespecificacion',
            'cod_fabricante',
            'cod_fabricante_subespecificacion',
            'nombre_fabricante',
            'nombre_clasificacion_producto',
            'cod_pais_de_origen'
        ];
        foreach ($nombreColumnas as $col) {
            if (array_key_exists($col, $opcionales))
                $item[$this->classExcel::$translateColumn[$col]] = $opcionales[$col];
        }

        if (empty($cargos_extendido)) {
            // ddo_cargos
            $nombreColumnas = ['ddo_cargo_descripcion', 'ddo_cargo_porcentaje'];
            $section = $this->buildSubSection($nombreColumnas, $opcionales);
            if ($section) {
                $cargos[] = $section;
                $key = key($cargos);
                $nombreColumnas = ['ddo_cargo_base', 'ddo_cargo_valor'];
                $cargos[$key]['valor_moneda_nacional'] = $this->buildSubSection($nombreColumnas, $opcionales);
                $nombreColumnas = ['ddo_cargo_base_moneda_extranjera', 'ddo_cargo_valor_moneda_extranjera'];
                $cargos[$key]['valor_moneda_extranjera'] = $this->buildSubSection($nombreColumnas, $opcionales);
                $item['ddo_cargos'] = $cargos;
            }
        } else {
            //Recorremos por que los cargos fueron eviados como Array.
            if (!array_key_exists('excel_general', $registro) || !$registro['excel_general']) {
                foreach (array_values($cargos_extendido) as $key=>$cargo) {
                    $nombreColumnas = ['ddo_cargo_descripcion', 'ddo_cargo_porcentaje', 'ddo_cargo_razon'];
                    $cargos = $this->buildSubSection($nombreColumnas, $cargo);
                    if ($cargos) {
                        $nombreColumnas = ['ddo_cargo_base', 'ddo_cargo_valor'];
                        $cargos['valor_moneda_nacional'] = $this->buildSubSection($nombreColumnas, $cargo);
                        $nombreColumnas = ['ddo_cargo_base_moneda_extranjera', 'ddo_cargo_valor_moneda_extranjera'];
                        $cargos['valor_moneda_extranjera'] = $this->buildSubSection($nombreColumnas, $cargo);
                        $item['ddo_cargos'][] = (array)$cargos;
                    }
                }
            } else {
                $item['ddo_cargos'] = [];
                foreach ($cargos_extendido as $Key => $cargo) {
                    $__cargo = $this->buildCargo($cargo, $Key);
                    if (!is_null($__cargo))
                        $item['ddo_cargos'][] = $__cargo;
                }
            }
        }

        // ddo_descuentos
        if (array_key_exists('descuentos_extendido', $registro) && !empty($registro['descuentos_extendido'])) {
            $item['ddo_descuentos'] = $this->getDescuentosExtendidos($registro['descuentos_extendido']);
        }
        else {
            $nombreColumnas = ['ddo_descuento_descripcion', 'ddo_descuento_porcentaje'];
            $section = $this->buildSubSection($nombreColumnas, $opcionales);
            if ($section) {
                $descuentos[] = $section;
                $key = key($descuentos);
                $nombreColumnas = ['ddo_descuento_base', 'ddo_descuento_valor'];
                $descuentos[$key]['valor_moneda_nacional'] = $this->buildSubSection($nombreColumnas, $opcionales);
                $nombreColumnas = ['ddo_descuento_base_moneda_extranjera', 'ddo_cargo_valor_moneda_extranjera'];
                $descuentos[$key]['valor_moneda_extranjera'] = $this->buildSubSection($nombreColumnas, $opcionales);
                $item['ddo_descuentos'] = $descuentos;
            }
        }

        // ddo_informacion_adicional
        $item['ddo_informacion_adicional'] = $registro['informacion_adicional'];
        
        // ddo_propiedades_adicionales
        if(array_key_exists('propiedades_adicionales', $registro))
            $item['ddo_propiedades_adicionales'] = $registro['propiedades_adicionales'];

        $nombreImpuestos = ['iva', 'impuesto_consumo', 'ica'];
        // tributos
        $temp = $this->agregarTributoRetencion($obligatorias, $ddo_secuencia, $nombreImpuestos);
        if (!empty($temp))
            $tributos = array_merge($tributos, $temp);

        // tributos
        $temp = $this->agregarTributoRetencion($opcionales, $ddo_secuencia, $this->listaImpuestos, true, 'impuesto');
        if (!empty($temp))
            $tributos = array_merge($tributos, $temp);

        // retenciones
        $temp = $this->agregarTributoRetencion($opcionales, $ddo_secuencia, $this->listaRetenciones, true, 'retencion');
        if (!empty($temp))
            $retenciones = array_merge($retenciones, $temp);

        if (array_key_exists('ddo_indicador_muestra', $item) && !empty($item['ddo_indicador_muestra'])) {
            if (strtoupper($item['ddo_indicador_muestra']) === 'SI')
                $item['ddo_indicador_muestra'] = 'true';
            if (strtoupper($item['ddo_indicador_muestra']) === 'NO')
                $item['ddo_indicador_muestra'] = 'false';
        } else {
            if (array_key_exists('ddo_precio_referencia', $item) &&
                array_key_exists('pre_codigo', $item['ddo_precio_referencia']) &&
                $item['ddo_precio_referencia']['pre_codigo'] !== '')
                $item['ddo_indicador_muestra'] = 'true';
        }

        // Retenciones sugeridas para el Item
        if(!array_key_exists('ddo_detalle_retenciones_sugeridas', $item))
            $item['ddo_detalle_retenciones_sugeridas'] = $registro['ddo_detalle_retenciones_sugeridas'];

        if(!empty($this->sectorSalud) && $this->hasValue($obligatorias, 'id_autorizacion_erp_eps')) {
            $item['ddo_identificacion_comprador'] = [[
                'id' => $obligatorias['id_autorizacion_erp_eps'],
                'atributo_consecutivo_id' => 'AutorizaID-ERP/EPS'
            ]];
        }

        return $item;
    }

    private function getNameCargoDescuento($str) {
        if (is_string($str)) {
            $str = ucfirst($str);
            return str_replace('_', ' ', $str);
        } elseif (is_numeric($str)) {
            $str = (string) $str;
            return str_replace('_', ' ', $str);
        }
        return null;
    }

    /**
     * Construye una sección de cargos extendidos.
     *
     * @param $registro
     * @param $nombre
     * @return array
     */
    private function buildCargo($registro, $nombre) {
        $nombreColumnas = [
            "cargo_{$nombre}"             => 'razon',
            "cargo_{$nombre}_descripcion" => 'razon',
            "cargo_{$nombre}_porcentaje"  => 'porcentaje',
        ];
        $__cargo = $this->buildSubSection([], $registro, ['porcentaje'], $nombreColumnas);
        if (!is_null($__cargo)) {
            $__cargo['nombre'] = $this->getNameCargoDescuento($nombre);

            $nombreColumnas = [
                "cargo_{$nombre}_base" => 'base',
                "cargo_{$nombre}_valor" => 'valor',
            ];

            $datos = $this->buildSubSection([], $registro, ['base', 'valor'], $nombreColumnas);
            if (!is_null($datos))
                $__cargo['valor_moneda_nacional'] = $datos;

            $nombreColumnas = [
                "cargo_{$nombre}_base_moneda_extranjera" => 'base',
                "cargo_{$nombre}_valor_moneda_extranjera" => 'valor',
            ];

            $datos = $this->buildSubSection([], $registro, ['base', 'valor'], $nombreColumnas);
            if (!is_null($datos))
                $__cargo['valor_moneda_extranjera'] = $datos;
        }
        return $__cargo;
    }

    /**
     * Establece las columnas optativas en cabecera para efectuar el mapeo de datos.
     *
     * @param array $columnasOptativasCabecera
     * @return JsonDocumentBuilder
     */
    public function setColumnasOptativasCabecera(array $columnasOptativasCabecera): JsonDocumentBuilder
    {
        $this->columnasOptativasCabecera = $columnasOptativasCabecera;
        return $this;
    }

    /**
     * Establece las columnas de información en cabecera para efectuar el mapeo de datos.
     *
     * @param array $columnasInformacionAdicionalCabecera
     * @return JsonDocumentBuilder
     */
    public function setColumnasInformacionAdicionalCabecera(array $columnasInformacionAdicionalCabecera): JsonDocumentBuilder
    {
        $this->columnasInformacionAdicionalCabecera = $columnasInformacionAdicionalCabecera;
        return $this;
    }

    /**
     * Establece el tipo de documento que se esta procesando.
     *
     * @param string $tipo
     * @return JsonDocumentBuilder
     */
    public function setTipoDocumento(string $tipo): JsonDocumentBuilder
    {
        $this->tipo = $tipo;
        return $this;
    }

    /**
     * Establece las columnas de Cargos Extendidos.
     *
     * @param array $columnasCargosItem
     * @return JsonDocumentBuilder
     */
    public function setColumnasCargoslItems($columnasCargosItem) {
        $this->columnasCargosItem = $columnasCargosItem;
        return $this;
    }

    /**
     * Establece las columnas optativas en items para efectuar el mapeo de datos.
     *
     * @param array $columnasOptativasItems
     * @return JsonDocumentBuilder
     */
    public function setColumnasOptativasItems(array $columnasOptativasItems): JsonDocumentBuilder
    {
        $this->columnasOptativasItems = $columnasOptativasItems;
        return $this;
    }

    /**
     * Establece las columnas adicionales en cabecera para efectuar el mapeo de datos.
     *
     * @param array $columnasInformacionAdicionalItems
     * @return JsonDocumentBuilder
     */
    public function setColumnasInformacionAdicionalItems(array $columnasInformacionAdicionalItems): JsonDocumentBuilder
    {
        $this->columnasInformacionAdicionalItems = $columnasInformacionAdicionalItems;
        return $this;
    }

    /**
     * Calcula los totales del documento y los inserta en el mismo para generar su json.
     *
     * @param array $documento Array para almacenar la información del documento
     * @param array $docType   Indica el tipo de documento en proceso
     */
    private function sumarizar(array &$documento, string $docType) {
        if (array_key_exists('cdo_detalle_cargos', $documento) && is_array($documento['cdo_detalle_cargos'])) {
            $this->cdo_cargos = 0;
            $this->cdo_cargos_moneda_extranjera = 0;

            $arrCargos       = [];
            $arrCargosMonExt =  [];

            foreach ($documento['cdo_detalle_cargos'] as $cargo) {
                if (!is_null($cargo)) {
                    if ( array_key_exists('valor_moneda_nacional', $cargo) && is_array($cargo['valor_moneda_nacional']) && array_key_exists('valor', $cargo['valor_moneda_nacional'])) {
                        $this->cdo_cargos += $this->getNumeric($cargo['valor_moneda_nacional']['valor']);
                        $arrCargos[] = $this->getNumeric($cargo['valor_moneda_nacional']['valor']);
                    }

                    if (array_key_exists('valor_moneda_extranjera', $cargo) && is_array($cargo['valor_moneda_extranjera']) && array_key_exists('valor', $cargo['valor_moneda_extranjera'])) {
                        $this->cdo_cargos_moneda_extranjera += $this->getNumeric($cargo['valor_moneda_extranjera']['valor']);
                        $arrCargosMonExt[] = $this->getNumeric($cargo['valor_moneda_extranjera']['valor']);
                    }
                }
            }

            $this->cdo_cargos = VP::redondeo($this->cdo_cargos, VP::maxDecimales($arrCargos));
            $this->cdo_cargos_moneda_extranjera = VP::redondeo($this->cdo_cargos_moneda_extranjera, VP::maxDecimales($arrCargosMonExt));
        }


        if (array_key_exists('cdo_detalle_descuentos', $documento) && is_array($documento['cdo_detalle_descuentos'])) {
            $this->cdo_descuentos = 0;
            $this->cdo_descuentos_moneda_extranjera = 0;

            $arrDescuentos       = [];
            $arrDescuentosMonExt = [];
            
            foreach ($documento['cdo_detalle_descuentos'] as $descuento) {
                if (array_key_exists('valor_moneda_nacional', $descuento) && is_array($descuento['valor_moneda_nacional']) && array_key_exists('valor', $descuento['valor_moneda_nacional'])) {
                    $this->cdo_descuentos += $this->getNumeric($descuento['valor_moneda_nacional']['valor']);
                    $arrDescuentos[] = $this->getNumeric($descuento['valor_moneda_nacional']['valor']);
                }

                if (array_key_exists('valor_moneda_extranjera', $descuento) && is_array($descuento['valor_moneda_extranjera']) && array_key_exists('valor', $descuento['valor_moneda_extranjera'])) {
                    $this->cdo_descuentos_moneda_extranjera += $this->getNumeric($descuento['valor_moneda_extranjera']['valor']);
                    $arrDescuentosMonExt[] = $this->getNumeric($descuento['valor_moneda_extranjera']['valor']);
                }
            }

            $this->cdo_descuentos = VP::redondeo($this->cdo_descuentos, VP::maxDecimales($arrDescuentos));
            $this->cdo_descuentos_moneda_extranjera = VP::redondeo($this->cdo_descuentos_moneda_extranjera, VP::maxDecimales($arrDescuentosMonExt));
        }

        if (array_key_exists('cdo_detalle_anticipos', $documento)) {
            foreach ($documento['cdo_detalle_anticipos'] as $anticipo) {
                $this->cdo_anticipo = $this->getNumeric($this->numberFormat($anticipo['ant_valor']));
                if (array_key_exists('ant_valor_moneda_extranjera', $anticipo))
                    $this->cdo_anticipo_moneda_extranjera = $this->getNumeric($this->numberFormat($anticipo['ant_valor_moneda_extranjera']));
            }
        }
        
        if (array_key_exists('retenciones', $documento)) {
            foreach ($documento['retenciones'] as $retencion) {
                if (array_key_exists('iid_valor', $retencion) && is_numeric($retencion['iid_valor']))
                    $this->cdo_retenciones += $retencion['iid_valor'];
                if (array_key_exists('iid_valor_moneda_extranjera', $retencion) && is_numeric($retencion['iid_valor_moneda_extranjera']))
                    $this->cdo_retenciones_moneda_extranjera += $retencion['iid_valor_moneda_extranjera'];
            }
        }

        if (array_key_exists('tributos', $documento)) {
            foreach ($documento['tributos'] as $impuesto) {
                if (array_key_exists('iid_valor', $impuesto) && is_numeric($impuesto['iid_valor']) ) {
                    $this->cdo_impuestos += $impuesto['iid_valor'];
                }
                if (array_key_exists('iid_valor_moneda_extranjera', $impuesto) && is_numeric($impuesto['iid_valor_moneda_extranjera'])) {
                    $this->cdo_impuestos_moneda_extranjera += $impuesto['iid_valor_moneda_extranjera'];
                }
            }
        }

        if (array_key_exists('cdo_detalle_retenciones_sugeridas', $documento)) {
            foreach ($documento['cdo_detalle_retenciones_sugeridas'] as $retenciones_sugerida) {
                if (is_numeric($retenciones_sugerida['valor_moneda_nacional']['valor'])) {
                    $this->cdo_retenciones_sugeridas += $retenciones_sugerida['valor_moneda_nacional']['valor'];
                }
                if (array_key_exists('valor_moneda_extranjera', $retenciones_sugerida) && is_numeric($retenciones_sugerida['valor_moneda_extranjera']['valor'])
                    && is_numeric($retenciones_sugerida['valor_moneda_extranjera']['valor'])) {
                    $this->cdo_retenciones_sugeridas_moneda_extranjera += $retenciones_sugerida['valor_moneda_extranjera']['valor'];
                }
            }
        }

        foreach ($documento['items'] as $item) {
            // Moneda Nacional
            if (is_numeric($item['ddo_total'])) {
                $this->cdo_valor_sin_impuestos += $item['ddo_total'];
            }

            // Moneda Extranjera
            if (array_key_exists('ddo_total_moneda_extranjera', $item) && is_numeric($item['ddo_total_moneda_extranjera'])) {
                if (is_numeric($item['ddo_total_moneda_extranjera']))
                    $this->cdo_valor_sin_impuestos_moneda_extranjera += $item['ddo_total_moneda_extranjera'];
            }

            if($this->origen == 'manual') {
                // Si existen cargos a nivel de item, se deben sumar al cdo_valor_sin_impuestos
                if(array_key_exists('ddo_cargos', $item) && !empty($item['ddo_cargos'])) {
                    foreach ($item['ddo_cargos'] as $cargo) {
                        $this->cdo_valor_sin_impuestos += (array_key_exists('valor_moneda_nacional', $cargo) && !empty($cargo['valor_moneda_nacional']) && array_key_exists('valor', $cargo['valor_moneda_nacional']) && is_numeric($cargo['valor_moneda_nacional']['valor'])) ? $cargo['valor_moneda_nacional']['valor'] : 0;
                        $this->cdo_valor_sin_impuestos_moneda_extranjera += (array_key_exists('valor_moneda_extranjera', $cargo) && !empty($cargo['valor_moneda_extranjera']) && array_key_exists('valor', $cargo['valor_moneda_extranjera']) && is_numeric($cargo['valor_moneda_extranjera']['valor'])) ? $cargo['valor_moneda_extranjera']['valor'] : 0;
                    }
                }

                // Si existen descuentos a nivel de item, se deben restar al cdo_valor_sin_impuestos
                if(array_key_exists('ddo_descuentos', $item) && !empty($item['ddo_descuentos'])) {
                    foreach ($item['ddo_descuentos'] as $descuento) {
                        $this->cdo_valor_sin_impuestos -= (array_key_exists('valor_moneda_nacional', $descuento) && !empty($descuento['valor_moneda_nacional']) && array_key_exists('valor', $descuento['valor_moneda_nacional']) && is_numeric($descuento['valor_moneda_nacional']['valor'])) ? $descuento['valor_moneda_nacional']['valor'] : 0;
                        $this->cdo_valor_sin_impuestos_moneda_extranjera -= (array_key_exists('valor_moneda_extranjera', $descuento) && !empty($descuento['valor_moneda_extranjera']) && array_key_exists('valor', $descuento['valor_moneda_extranjera']) && is_numeric($descuento['valor_moneda_extranjera']['valor'])) ? $descuento['valor_moneda_extranjera']['valor'] : 0;
                    }
                }
                
                // Si existen retenciones sugeridas a nivel de item, se deben sumar al cdo_retenciones_sugeridas
                if(array_key_exists('ddo_detalle_retenciones_sugeridas', $item) && !empty($item['ddo_detalle_retenciones_sugeridas'])) {
                    foreach ($item['ddo_detalle_retenciones_sugeridas'] as $retenciones_sugerida) {
                        if (is_numeric($retenciones_sugerida['valor_moneda_nacional']['valor'])) {
                            $this->cdo_retenciones_sugeridas += $retenciones_sugerida['valor_moneda_nacional']['valor'];
                        }
                        if (array_key_exists('valor_moneda_extranjera', $retenciones_sugerida) && is_numeric($retenciones_sugerida['valor_moneda_extranjera']['valor'])
                            && is_numeric($retenciones_sugerida['valor_moneda_extranjera']['valor'])) {
                            $this->cdo_retenciones_sugeridas_moneda_extranjera += $retenciones_sugerida['valor_moneda_extranjera']['valor'];
                        }
                    }
                }
            }
        }

        $this->cdo_total = $this->cdo_valor_sin_impuestos + $this->cdo_impuestos;
        $this->cdo_total_moneda_extranjera = $this->cdo_valor_sin_impuestos_moneda_extranjera + $this->cdo_impuestos_moneda_extranjera;

        $documento['cdo_cargos'] = $this->numberFormat($this->cdo_cargos);
        $documento['cdo_cargos_moneda_extranjera'] = $this->numberFormat($this->cdo_cargos_moneda_extranjera);
        // Para los documentos soporte no aplican los campos de anticipos
        if ($docType !== ConstantsDataInput::DS && $docType !== ConstantsDataInput::DS_NC) {
            $documento['cdo_anticipo'] = $this->numberFormat($this->cdo_anticipo);
            $documento['cdo_anticipo_moneda_extranjera'] = $this->numberFormat($this->cdo_anticipo_moneda_extranjera);
        }
        $documento['cdo_retenciones'] = $this->numberFormat($this->cdo_retenciones);
        $documento['cdo_retenciones_moneda_extranjera'] = $this->numberFormat($this->cdo_retenciones_moneda_extranjera);
        $documento['cdo_impuestos'] = $this->numberFormat($this->cdo_impuestos);
        $documento['cdo_impuestos_moneda_extranjera'] = $this->numberFormat($this->cdo_impuestos_moneda_extranjera);
        $documento['cdo_descuentos'] = $this->numberFormat($this->cdo_descuentos);
        $documento['cdo_descuentos_moneda_extranjera'] = $this->numberFormat($this->cdo_descuentos_moneda_extranjera);
        $documento['cdo_retenciones_sugeridas'] = $this->numberFormat($this->cdo_retenciones_sugeridas);
        $documento['cdo_retenciones_sugeridas_moneda_extranjera'] = $this->numberFormat($this->cdo_retenciones_sugeridas_moneda_extranjera);
        $documento['cdo_valor_sin_impuestos'] = $this->numberFormat($this->cdo_valor_sin_impuestos);
        $documento['cdo_valor_sin_impuestos_moneda_extranjera'] = $this->numberFormat($this->cdo_valor_sin_impuestos_moneda_extranjera);
        $documento['cdo_total'] = $this->numberFormat($this->cdo_total);
        $documento['cdo_total_moneda_extranjera'] = $this->numberFormat($this->cdo_total_moneda_extranjera);
    }

    /**
     * Formatea un valor numérico.
     *
     * @param mixed $value
     * @return string
     */
    private function numberFormat($value) {
        if (empty($value))
            return "0.00";
        if (is_integer($value))
            $value = (string) $value;
        if (is_float($value))
            $value = (string) $value;

        if (preg_match("/^(\-)?((\d{1,3})(\,\d{3})*)(\.\d{1,6})?$/",  $value)) {
            $value = str_replace(',', '', $value);
            return number_format($value, 2, '.', '');
        }
        elseif (preg_match("/^(\-)?((\d{1,3})(\.\d{3})*)(\,\d{1,6})?$/",  $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
            return number_format($value, 2, '.', '');
        }
        elseif (preg_match("/^(\-)?(\d+)(\.\d{1,6})?$/",  $value)) {
            return number_format($value, 2, '.', '');
        }elseif(preg_match("/^(\-)?(\d+)(\,\d{1,6})?$/",  $value)) {
            $value = str_replace(',', '.', $value);
            return number_format($value, 2, '.', '');
        }
        return $value;
    }

    /**
     * Convierte un valor numerico que esta en un string en un flotante.
     *
     * @param $in
     * @return float
     */
    private function getNumeric($in) {
        if (is_string($in) && is_numeric($in))
            return floatval($in);
        return 0.0;
    }

    /**
     * Obtiene la descripción de un país mediante su código.
     *
     * @param string $pai_codigo Código del país
     * @return string Descripción del país
     */
    private function obtenerDescripcionPais($pai_codigo) {
        $objeto = ParametrosPais::select(['pai_id', 'pai_descripcion'])
            ->where('estado', 'ACTIVO')
            ->where('pai_codigo', $pai_codigo)
            ->get()
            ->groupBy('pai_codigo')
            ->map(function ($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if($vigente['vigente'])
                    return $vigente['registro'];
            })->first();

        if ($objeto)
            return $objeto->pai_descripcion;
        else
            return '';
    }

    /**
     * Construye las propiedades del objeto json del documento relacionadas con el Sector Salud.
     *
     * @param array $documento Documento en procesamiento
     * @return void
     */
    private function buildSectorSalud(&$documento) {
        $parametricas['saludDocumentoReferenciado'] = [];
        ParametrosSaludDocumentoReferenciado::select(['sdr_id', 'sdr_codigo', 'sdr_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy('sdr_codigo', 'desc')
            ->get()
            ->groupBy('sdr_codigo')
            ->map(function ($item) use (&$parametricas) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if($vigente['vigente'])
                    $parametricas['saludDocumentoReferenciado'][$vigente['registro']->sdr_codigo] = $vigente['registro'];
            });

        // Documento Referencia
        if(!empty($this->sectorSalud['documento_referencia'])) {
            // Si esta posición del array no existe debe ser creada, esto se puede dar debido a que para las NC y ND esta posición se crea previamente
            if (!array_key_exists('cdo_documento_referencia', $documento))
                $documento['cdo_documento_referencia'] = [];

            foreach ($this->sectorSalud['documento_referencia'] as $documentoReferencia) {
                if(array_key_exists($documentoReferencia['codigo_tipo_documento'], $parametricas['saludDocumentoReferenciado']))
                    $tipoDocumento = $parametricas['saludDocumentoReferenciado'][$documentoReferencia['codigo_tipo_documento']]->sdr_descripcion;
                else
                    $tipoDocumento = array_key_exists('descripcion_tipo_documento', $documentoReferencia) ? $documentoReferencia['descripcion_tipo_documento'] : '';

                $documento['cdo_documento_referencia'][] = [
                    'clasificacion'                           => $documentoReferencia['clasificacion'],
                    'prefijo'                                 => $documentoReferencia['prefijo'],
                    'consecutivo'                             => $documentoReferencia['consecutivo'],
                    'atributo_consecutivo_id'                 => $documentoReferencia['id_usuario_servicio_salud'],
                    'atributo_consecutivo_name'               => $documentoReferencia['operacion_recaudo'],
                    'atributo_consecutivo_agency_id'          => $documentoReferencia['codigo_prestador_servicios_salud'],
                    'atributo_consecutivo_version_id'         => $documentoReferencia['numero_autorizacion'],
                    'cufe'                                    => '',
                    'uuid'                                    => $documentoReferencia['cufe___cude___id_autorizacion_dian'],
                    'atributo_uuid_name'                      => $documentoReferencia['tipo_operacion_realizada'],
                    'fecha_emision'                           => $documentoReferencia['fecha_emision'],
                    'codigo_tipo_documento'                   => $documentoReferencia['codigo_tipo_documento'],
                    'atributo_codigo_tipo_documento_list_uri' => 'TipoDocumento-2.1_SSalud.gc',
                    'tipo_documento'                          => $tipoDocumento
                ];
            }
        }
        
        // Documento Adicional
        if(!empty($this->sectorSalud['documento_adicional'])) {
            // Si esta posición del array no existe debe ser creada, esto se puede dar debido a que cuando el top_codigo es 03 se crea previamente
            if (!array_key_exists('cdo_documento_adicional', $documento))
                $documento['cdo_documento_adicional'] = [];
            
            foreach ($this->sectorSalud['documento_adicional'] as $documentoAdicional) {
                if(array_key_exists($documentoAdicional['codigo_tipo_documento'], $parametricas['saludDocumentoReferenciado']))
                    $tipoDocumento = $parametricas['saludDocumentoReferenciado'][$documentoAdicional['codigo_tipo_documento']]->sdr_descripcion;
                else
                    $tipoDocumento = array_key_exists('descripcion_tipo_documento', $documentoAdicional) ? $documentoAdicional['descripcion_tipo_documento'] : '';

                $documento['cdo_documento_adicional'][] = [
                    'seccion'                         => $documentoAdicional['seccion'],
                    'rod_codigo'                      => $documentoAdicional['codigo_tipo_documento'],
                    'atributo_rod_codigo_list_uri'    => 'TipoDocumento-2.1_SSalud.gc',
                    'prefijo'                         => $documentoAdicional['prefijo'],
                    'consecutivo'                     => $documentoAdicional['consecutivo'],
                    'atributo_consecutivo_id'         => $documentoAdicional['id_usuario_servicio_salud'],
                    'atributo_consecutivo_name'       => $documentoAdicional['operacion_recaudo'],
                    'atributo_consecutivo_agency_id'  => $documentoAdicional['codigo_prestador_servicios_salud'],
                    'atributo_consecutivo_version_id' => $documentoAdicional['numero_autorizacion'],
                    'cufe'                            => '',
                    'uuid'                            => $documentoAdicional['cufe___cude___id_autorizacion_dian'],
                    'atributo_uuid_name'              => $documentoAdicional['tipo_operacion_realizada'],
                    'fecha_emision'                   => $documentoAdicional['fecha_emision'],
                    'tipo_documento'                  => $tipoDocumento
                ];
            }
        }

        // Documento Referencia Línea
        if(!empty($this->sectorSalud['documento_referencia_linea'])) {
            $documento['cdo_documento_referencia_linea'] = [];
            foreach ($this->sectorSalud['documento_referencia_linea'] as $documentoReferenciaLinea) {
                $documento['cdo_documento_referencia_linea'][] = [
                    'prefijo'                         => $documentoReferenciaLinea['prefijo'],
                    'consecutivo'                     => $documentoReferenciaLinea['consecutivo'],
                    'atributo_consecutivo_id'         => $documentoReferenciaLinea['id_usuario_servicio_salud'],
                    'atributo_consecutivo_name'       => $documentoReferenciaLinea['operacion_recaudo'],
                    'atributo_consecutivo_agency_id'  => $documentoReferenciaLinea['codigo_prestador_servicios_salud'],
                    'atributo_consecutivo_version_id' => $documentoReferenciaLinea['numero_autorizacion'],
                    'valor'                           => $documentoReferenciaLinea['valor'],
                    'atributo_valor_moneda'           => $documentoReferenciaLinea['cod_moneda_valor'],
                    'atributo_valor_concepto'         => $documentoReferenciaLinea['concepto_recaudo']
                ];
            }
        }

        // Referencia Adquirente
        if(!empty($this->sectorSalud['referencia_adquirente'])) {
            $documento['cdo_referencia_adquirente'] = [];
            foreach ($this->sectorSalud['referencia_adquirente'] as $referenciaAdquirente) {
                $documento['cdo_referencia_adquirente'][] = [
                    'id'                                 => $referenciaAdquirente['id_usuario'],
                    'atributo_id_name'                   => $referenciaAdquirente['tipo_documento_usuario'],
                    'tdo_codigo'                         => $referenciaAdquirente['cod_tipo_documento'],
                    'nombres_usuario_beneficiario'       => $referenciaAdquirente['nombres_usuario'],
                    'apellidos_usuario_beneficiario'     => $referenciaAdquirente['apellidos_usuario'],
                    'nombre'                             => $referenciaAdquirente['nombre_entidad_expedidora_adquirente'],
                    'postal_address_codigo_pais'         => $referenciaAdquirente['codigo_pais_entidad_expedidora_adquirente'],
                    'postal_address_descripcion_pais'    => $this->obtenerDescripcionPais($referenciaAdquirente['codigo_pais_entidad_expedidora_adquirente']),
                    'residence_address_id'               => $referenciaAdquirente['codigo_dane_ciudad_usuario'],
                    'residence_address_atributo_id_name' => 'city code DANE',
                    'residence_address_nombre_ciudad'    => $referenciaAdquirente['nombre_dane_ciudad_usuario'],
                    'residence_address_direccion'        => [$referenciaAdquirente['direccion_usuario']],
                    'codigo_pais'                        => $referenciaAdquirente['codigo_pais_usuario'],
                    'descripcion_pais'                   => $this->obtenerDescripcionPais($referenciaAdquirente['codigo_pais_usuario'])
                ];
            }
        }

        // Interoperabilidad Sector Salud
        if(!empty($this->sectorSalud['extension_sector_salud'])) {
            $cdoInteroperabilidad = [];
            foreach ($this->sectorSalud['extension_sector_salud'] as $extensionSectorSalud) {
                $informacionAdicional = [];

                if(!empty($extensionSectorSalud['codigo_prestador']))
                    $informacionAdicional[] =  [
                        'nombre' => 'CODIGO_PRESTADOR',
                        'valor'  => $extensionSectorSalud['codigo_prestador']
                    ];
                    
                if(!empty($extensionSectorSalud['tipo_documento_identificacion']))
                    $informacionAdicional[] =  [
                        'nombre'        => 'TIPO_DOCUMENTO_IDENTIFICACION',
                        'valor'         => $extensionSectorSalud['tipo_documento_identificacion'],
                        'atributo_name' => 'salud_identificacion.gc',
                        'atributo_id'   => $extensionSectorSalud['tipo_documento_identificacion_id']
                    ];

                if(!empty($extensionSectorSalud['numero_documento_identificacion']))
                    $informacionAdicional[] =  [
                        'nombre' => 'NUMERO_DOCUMENTO_IDENTIFICACION',
                        'valor'  => $extensionSectorSalud['numero_documento_identificacion']
                    ];

                if(!empty($extensionSectorSalud['primer_apellido']))
                    $informacionAdicional[] =  [
                        'nombre' => 'PRIMER_APELLIDO',
                        'valor'  => $extensionSectorSalud['primer_apellido']
                    ];

                if(!empty($extensionSectorSalud['segundo_apellido']))
                    $informacionAdicional[] =  [
                        'nombre' => 'SEGUNDO_APELLIDO',
                        'valor'  => $extensionSectorSalud['segundo_apellido']
                    ];

                if(!empty($extensionSectorSalud['primer_nombre']))
                    $informacionAdicional[] =  [
                        'nombre' => 'PRIMER_NOMBRE',
                        'valor'  => $extensionSectorSalud['primer_nombre']
                    ];

                if(!empty($extensionSectorSalud['segundo_nombre']))
                    $informacionAdicional[] =  [
                        'nombre' => 'SEGUNDO_NOMBRE',
                        'valor'  => $extensionSectorSalud['segundo_nombre']
                    ];

                if(!empty($extensionSectorSalud['tipo_usuario']))
                    $informacionAdicional[] =  [
                        'nombre'        => 'TIPO_USUARIO',
                        'valor'         => $extensionSectorSalud['tipo_usuario'],
                        'atributo_name' => 'salud_tipo_usuario.gc',
                        'atributo_id'   => $extensionSectorSalud['tipo_usuario_id']
                    ];

                if(!empty($extensionSectorSalud['modalidad_contratacion']))
                    $informacionAdicional[] =  [
                        'nombre'        => 'MODALIDAD_CONTRATACION',
                        'valor'         => $extensionSectorSalud['modalidad_contratacion'],
                        'atributo_name' => 'salud_modalidad_contratacion.gc',
                        'atributo_id'   => $extensionSectorSalud['modalidad_contratacion_id']
                    ];

                if(!empty($extensionSectorSalud['cobertura_plan_beneficios']))
                    $informacionAdicional[] =  [
                        'nombre'        => 'COBERTURA_PLAN_BENEFICIOS',
                        'valor'         => $extensionSectorSalud['cobertura_plan_beneficios'],
                        'atributo_name' => 'salud_cobertura.gc',
                        'atributo_id'   => $extensionSectorSalud['cobertura_plan_beneficios_id']
                    ];

                if(!empty($extensionSectorSalud['numero_autorizacion']))
                    $informacionAdicional[] =  [
                        'nombre' => 'NUMERO_AUTORIZACION',
                        'valor'  => $extensionSectorSalud['numero_autorizacion']
                    ];

                if(!empty($extensionSectorSalud['numero_mipres']))
                    $informacionAdicional[] =  [
                        'nombre' => 'NUMERO_MIPRES',
                        'valor'  => $extensionSectorSalud['numero_mipres']
                    ];

                if(!empty($extensionSectorSalud['numero_entrega_mipres']))
                    $informacionAdicional[] =  [
                        'nombre' => 'NUMERO_ENTREGA_MIPRES',
                        'valor'  => $extensionSectorSalud['numero_entrega_mipres']
                    ];

                if(!empty($extensionSectorSalud['numero_contrato']))
                    $informacionAdicional[] =  [
                        'nombre' => 'NUMERO_CONTRATO',
                        'valor'  => $extensionSectorSalud['numero_contrato']
                    ];

                if(!empty($extensionSectorSalud['numero_poliza']))
                    $informacionAdicional[] =  [
                        'nombre' => 'NUMERO_POLIZA',
                        'valor'  => $extensionSectorSalud['numero_poliza']
                    ];

                if(!empty($extensionSectorSalud['copago']))
                    $informacionAdicional[] =  [
                        'nombre' => 'COPAGO',
                        'valor'  => $extensionSectorSalud['copago']
                    ];

                if(!empty($extensionSectorSalud['cuota_moderadora']))
                    $informacionAdicional[] =  [
                        'nombre' => 'CUOTA_MODERADORA',
                        'valor'  => $extensionSectorSalud['cuota_moderadora']
                    ];

                if(!empty($extensionSectorSalud['cuota_recuperacion']))
                    $informacionAdicional[] =  [
                        'nombre' => 'CUOTA_RECUPERACION',
                        'valor'  => $extensionSectorSalud['cuota_recuperacion']
                    ];

                if(!empty($extensionSectorSalud['pagos_compartidos']))
                    $informacionAdicional[] =  [
                        'nombre' => 'PAGOS_COMPARTIDOS',
                        'valor'  => $extensionSectorSalud['pagos_compartidos']
                    ];

                $key = $extensionSectorSalud['prefijo_cabecera'] . $extensionSectorSalud['consecutivo_cabecera'] . $extensionSectorSalud['acto_administrativo'];
                if(!array_key_exists($key, $cdoInteroperabilidad)) {
                    $cdoInteroperabilidad[$key] = [
                        'informacion_general' => [
                            [
                                'nombre' => 'Responsable',
                                'valor'  => 'url www.minsalud.gov.co'
                            ],
                            [
                                'nombre' => 'Tipo, identificador:año del acto administrativo',
                                'valor'  => $extensionSectorSalud['acto_administrativo']
                            ]
                        ],
                        'interoperabilidad' => [
                            'grupo' => 'Sector Salud',
                            'collection' => [
                                [
                                    'nombre' => 'Usuario',
                                    'informacion_adicional' => $informacionAdicional
                                ]
                            ]
                        ]
                    ];
                } else {
                    $cdoInteroperabilidad[$key]
                        ['interoperabilidad']['collection'][] = [
                            'nombre' => 'Usuario',
                            'informacion_adicional' => $informacionAdicional
                        ];
                }
            }

            if(!empty($cdoInteroperabilidad))
                $documento['cdo_interoperabilidad'] = array_values($cdoInteroperabilidad);
        }
    }
}
