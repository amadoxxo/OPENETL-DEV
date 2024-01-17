<?php

namespace App\Http\Modulos\DataInputWorker\FNC;

use Validator;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use App\Traits\DiTrait;
use Illuminate\Support\Facades\Log;
use App\Http\Models\AdoAgendamiento;
use function GuzzleHttp\Promise\queue;
use Illuminate\Support\Facades\Storage;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\DataInputWorker\Json\JsonEtlBuilder;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\DataInputWorker\Json\JsonDocumentBuilder;
use App\Http\Modulos\DataInputWorker\Utils\ProcesarArchivoPgp;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccion;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;


/**
 * Clase Gestora para el procesamiento de Cargas por medio de excel.
 *
 * Class ParserExcel
 * @package App\Http\Modulos\DataInputWorker
 */
class ParserExcelFNC {
    use PackageMainTrait;

    /**
     * Constante para el control maximo de memoria
     */
    private const MAX_MEMORY = '512M';

    /**
     * Columnas sanitizadas para la interpretacion de cada registro.
     *
     * @var array
     */
    private $keys = [];

    /**
     * Nombre de las columnas reales del excel.
     *
     * @var array
     */
    private $nombresReales = [];

    /**
     * Data del documento de excel.
     *
     * @var array
     */
    private $data = [];

    /**
     * Tipo de documentos que seran cargados.
     *
     * @var string
     */
    private $tipo;

    /**
     * Contiene las columnas que forman parte de la información adicional de cabecera.
     *
     * @var array
     */
    private $informacionAdicionalCabecera = [];

    /**
     * Contiene las columnas que forman parte de la información adicional items.
     *
     * @var array
     */
    private $informacionAdicionalItems = [];

    /**
     * Lista de elementos que presentaron fallas en la carga de excel - Validaciones.
     *
     * @var array
     */
    private $documentosConFallas = [];

    /**
     * Lista de elementos que en la carga de excel presentaron datos consistentes. No implica que cumplan con los filtros
     * de validación para ser registrados, solo que seran agendados para su validación total de integridad y posible
     * registro en una tenant de openEtl.
     *
     * @var array
     */
    private $documentosParaAgendamiento = [];

    /**
     * Lista de posibles impuestos identificados en el documento de excel
     *
     * @var array
     */
    private $listaImpuestos = [];

    /**
     * Lista de posibles impuestos identificados en el documento de excel
     *
     * @var array
     */
    private $tipo_documento = null;



    /**
     * Lista de posibles retenciones identificadas en el documento de excel
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
     * Lista de tipo de documentos registrados y activos en etl_openmain
     *
     * @var array
     */
    private $tipoDocumentos = [];

    /**
     * Reglas de validacion para el documento actual.
     *
     * @var array
     */
    private $rules = [];

    /**
     * Contiene las reglas de validación basicas para documentos de tipo FC
     *
     * @var
     */
    private $rulesFC =  [
        //OBLIGATORIOS CABECERA
        'nit_ofe' => 'required|string|max:20', //ofe_id
        'nit_adquiriente' => 'required|string', // adq_id
        'resolucion_facturacion' => 'required|string|max:20', //rfa_id
        'prefijo' => 'required|string|max:5', //cdo_prefijo
        'consecutivo' => 'required|string|max:20', // cdo_consecutivo
        'cod_tipo_operacion' => 'required|string|max:2', //top_id
        'cod_tipo_documento' => 'required|string|max:2', //top_id
        'fecha_factura' => 'required', // cdo_fecha
        'hora_factura' => 'required|date_format:H:i:s', //hora_factura
        'fecha_vencimiento' => 'required', //cdo_fecha_vencimiento
        'observacion' => 'nullable|string', // cdo_observacion
        'representacion_grafica_documento' => 'required|string', //cdo_representacion_grafica_documento
        'representacion_grafica_acuse_de_recibo' => 'required|string',// cdo_representacion_grafica_acuse
        'diferencia_redondeo_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'diferencia_redondeo_moneda_nacional' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'cod_forma_de_pago' => 'required',

        //ITEM OBLIGATORIOS
        'cod_clasificacion_producto' => 'required',
        'tipo_servicio' => 'nullable',
        'descripcion' => 'string',
        'cantidad_real_sobre_la_cual_aplica_el_precio' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'cod_producto' => ['required'],
        'cod_precio_referencia' => 'nullable',
        'valor_unitario' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'total_sin_primas' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_unitario_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'total_sin_primas_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'cod_unidad_medida' => 'required',
        'cod_medio_de_pago' => 'nullable',

        //TRIBUTOS
        'base_iva' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_iva' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_iva' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_iva_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_iva_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'motivo_exencion_iva' => 'nullable',
        'base_impuesto_consumo' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_impuesto_consumo' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_impuesto_consumo'  => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_ica' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_ica' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_ica' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        
        //CARGOS
        'valor_total_prima_tecnical_support' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_tecnical_support' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_tecnical_support_en_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_tecnical_support_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_tecnical_support_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_social' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_social' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_social' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_social_en_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_social_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_otros_cargos' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_otros_cargos' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_otros_cargos' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_otros_cargos_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_otros_cargos_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_recargo_vat' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_recargo_vat' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_recargo_vat' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_recargo_vat_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_recargo_vat_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_las_prima_permanente' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_las_prima_permanente' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_permanente' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_las_prima_permanente_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_permanentes_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],

        //DAD
        'numero_orden_de_compra' => 'nullable',
        'fecha_orden_de_compra' => 'nullable',
        'cod_condiciones_entrega' => 'nullable',
    ];

    /**
     * Contiene las reglas de validación basicas para documentos de tipo NCND
     *
     * @var
     */
    private $rulesNCND =  [
        //OBLIGATORIOS CABECERA
        'nit_ofe' => 'required|string|max:20', //ofe_id
        'nit_adquiriente' => 'required|string|max:20', // adq_id
        'prefijo_factura' => 'required',
        'cufe' => 'required',
        'prefijo' => 'required|string|max:5', //cdo_prefijo
        'consecutivo' => 'required|string|max:20', // cdo_consecutivo
        'cod_tipo_operacion' => 'required|string|max:2', //top_id
        'cod_tipo_documento' => 'required|string|max:2',
        'fecha_factura' => 'required',
        'hora_factura' => 'required|date_format:H:i:s',
        'fecha' => 'required', // cdo_fecha
        'hora' => 'required|date_format:H:i:s', //hora_factura
        'fecha_vencimiento' => 'required', //cdo_fecha_vencimiento
        'observacion' => 'nullable|string', // cdo_observacion
        'representacion_grafica_documento' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'], //cdo_representacion_grafica_documento
        'representacion_grafica_acuse_de_recibo' => 'required|string',// cdo_representacion_grafica_acuse
        'diferencia_redondeo_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'diferencia_redondeo_moneda_nacional' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'cod_forma_de_pago' => 'nullable',
        'motivo_pedido' => 'nullable',

        //ITEM OBLIGATORIOS
        'cod_clasificacion_producto' => 'required',
        'tipo_servicio' => 'nullable',
        'descripcion' => 'string',
        'cantidad_real_sobre_la_cual_aplica_el_precio' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'cod_producto' => ['required'],
        'cod_precio_referencia' => 'nullable',
        'valor_unitario' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'total_sin_primas' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_unitario_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'total_sin_primas_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'cod_unidad_medida' => 'required',
        'cod_medio_de_pago' => 'nullable',

        //TRIBUTOS
        'base_iva' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_iva' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_iva' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_iva_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_iva_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'motivo_exencion_iva' => 'nullable',
        'base_impuesto_consumo' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_impuesto_consumo' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_impuesto_consumo'  => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_ica' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_ica' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_ica' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],

        //CARGOS
        'valor_total_prima_tecnical_support' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_tecnical_support' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_tecnical_support_en_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_tecnical_support_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_tecnical_support_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_social' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_social' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_social' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_social_en_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_social_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_otros_cargos' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_otros_cargos' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_otros_cargos' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_otros_cargos_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_otros_cargos_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_recargo_vat' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_la_prima_recargo_vat' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_recargo_vat' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_la_prima_recargo_vat_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_recargo_vat_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_las_prima_permanente' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'porcentaje_de_las_prima_permanente' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_permanente' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'base_de_las_prima_permanente_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
        'valor_total_prima_permanentes_moneda_extranjera' => ['nullable', 'regex:/^(\-)?((((\d+)|(\d{1,3})(\,\d{3})*)(\.\d{1,6})?)|(((\d+)|(\d{1,3})(\.\d{3})*)(\,\d{1,6})?))$/'],
    ];

    /**
     * ParserExcel constructor.
     */
    public function __construct() {
        set_time_limit(0);
        ini_set('memory_limit', self::MAX_MEMORY);

        ParametrosTributo::where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) {
                $key = strtolower($this->sanear_string($item->tri_nombre));
                $key = str_replace(' ', '_', $key);
                $this->impuestosRegistrados[$key] = $item->tri_codigo;
            });

        ParametrosTipoDocumentoElectronico::where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) {
                $key = strtolower($this->sanear_string($item->tri_nombre));
                $key = str_replace(' ', '_', $key);
                $this->tipoDocumentos[$key] = $item->tde_descripcion;
            });
    }

    /**
     * Efectua el proceso de registro de un archivo de excel para su posterior carga en DI por medio de procesamiento de
     * documentos en segundo plano
     *
     * @param string $archivo
     * @param string $tipo
     * @param int $ofe_id
     * @param string $nombreRealArchivo
     * @return array
     * @throws \Exception
     */
    public function run(string $archivo, string $tipo, int $ofe_id, string $nombreRealArchivo = '') {
        $this->tipo = $tipo;
        $response = $this->parserExcel($archivo, $ofe_id,  $tipo, $nombreRealArchivo);
        if (!$response['error']) {
            $response = $this->validarColumnas($ofe_id);
            $errores = [];
            $message = '';

            // Si no hay error
            if (!$response['error']) {
                $message = $this->procesador($ofe_id);
                foreach ($this->documentosConFallas as $doc)
                    foreach ($doc['errores'] as $err)
                        $errores[] = $err;
            } else {
                $errores = $response['errores'];
            }
            $resultado = [
                'error' => !empty($errores),
                'errores' => $errores,
                'message' => $message
            ];
        } else {
            $resultado = [
                'error' => !empty($errores),
                'errores' => $response['error'],
                'message' => 'Error al procesar el archivo'
            ];
        }

        return $resultado;
    }


    /**
     * Retorna las columnas que tiene asociado el archivo en funcion de su tipo.
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe
     * @param string $modo
     * @return array
     */
    private function getColumnas(ConfiguracionObligadoFacturarElectronicamente $ofe, $modo = 'FC') {
        $columnas = [];
        $clasificacion = ($modo === 'FC') ? 'FC' : 'NC-ND';
        $todas = $ofe->ofe_columnas_personalizadas[$clasificacion]['columnas'];
        usort($todas, function ($a, $b){
            return $a['orden'] > $b['orden'];
        });
        foreach ($todas as $pvt)
            $columnas[] = trim($pvt['nombre']);
        return $columnas;
    }

    /**
     * Obtiene la data de un archivo de Excel.
     *
     * @param string $archivo
     * @param $ofe_id
     * @param string $modo
     * @param string $nombreRealArchivo
     * @return array
     * @throws \Exception
     */
    public function parserExcel($archivo, $ofe_id, $modo = 'FC', $nombreRealArchivo = '') {
        $request = request();
        // Obtiene la extensión original del archivo
        DiTrait::setFilesystemsInfo();
        $storagePath = Storage::disk(config('variables_sistema.ETL_ENCRIPTADOS'))->getDriver()->getAdapter()->getPathPrefix();
        if (empty($nombreRealArchivo))
            $nombreRealArchivo = $request->file('archivo')->getClientOriginalName();

        $extension = explode('.', $nombreRealArchivo);
        $objUser = auth()->user();
        $tempfilecsv = $storagePath . Uuid::uuid4()->toString() . '.csv';
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_columnas_personalizadas'])
            ->where('ofe_id', $ofe_id)
            ->first();
        $cifrado = false;
        // Aplica cuando es un archivo pgp, en cuyo caso se debe desencriptar, generar el excel y continuar el procesamiento
        if(strtolower($extension[count($extension) - 1]) == 'pgp') {
            $columnasDefault = $this->getColumnas($ofe, $modo);
            $baseDatos = $objUser->getBaseDatos->bdd_nombre;

            $archivoPGP = new ProcesarArchivoPgp($request, $baseDatos, $columnasDefault, $nombreRealArchivo);
            $archivoCSV = $archivoPGP->procesar($archivo);

            if($archivoCSV['error'] == '') {
                $tempfilecsv =  $archivoCSV['archivo'];
                $cifrado = true;
            } else {
                return [
                    'error' => true,
                    'message' => 'Error: ' . $archivoCSV['error']
                ];
            }
        } else {
            // Construyendo el csv
            exec("ssconvert $archivo $tempfilecsv");
        }

        $registros = [];
        if (($handle = fopen($tempfilecsv, "r")) !== false) {
            while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                $registros[] = $data;
            }
            fclose($handle);
        }

        if ($cifrado) {
            @unlink($tempfilecsv);
        }

        $header = $registros[0];
        $this->keys = [];
        $this->data = [];
        $mode = 'cdo';

        $keysToReplace = [
            'cargo_descripcion',
            'cargo_porcentaje',
            'cargo_base',
            'cargo_valor',
            'cargo_base_moneda_extranjera',
            'cargo_valor_moneda_extranjera',
            'descuento_codigo',
            'descuento_descripcion',
            'descuento_porcentaje',
            'descuento_base',
            'descuento_valor',
            'descuento_base_moneda_extranjera',
            'descuento_valor_moneda_extranjera'
        ];

        foreach ($header as $i => $k) {
            $key = trim(strtolower($this->sanear_string(str_replace(' ', '_', trim($k)))));
            if (in_array($key, $keysToReplace) !== false)
                $key = $mode . '_' . $key;
            $this->keys[] = $key;
        }

        $N = count($this->keys);
        for ($i = 1; $i < count($registros); $i++) {
            $row = $registros[$i];
            $newrow = [];
            for ($j = 0; $j < $N; $j++) {
                $value = array_key_exists($j, $row) ? $row[$j] : '';
                if (in_array($this->keys[$j], ColumnsExcelFNC::$fixFecha) && !empty($value))
                    $value = str_replace('/', '-', $value);
                $newrow[trim($this->keys[$j])] = $value;
            }
            $this->data[] = $newrow;
        }

        @unlink($archivo);
        @unlink($tempfilecsv);
        return ['error' => false];
    }



    /**
     * Agrega una retención a la lista de retenciones siempre y cuando esta no se halla registrado ya.
     *
     * @param string $retencion
     */
    private function agregarRetencion(string $retencion) {
        $retencion = $this->sanear_string($retencion);
        if (!in_array($retencion, $this->listaRetenciones))
            $this->listaRetenciones[] = $retencion;
    }

    /**
     * Agrega un impuesto a la lista de impuestos siempre y cuando este no se halla registrado ya.
     *
     * @param string $impuesto
     */
    private function agregarImpuesto(string $impuesto) {
        $impuesto = $this->sanear_string($impuesto);
        if (!in_array($impuesto, $this->listaImpuestos))
            $this->listaImpuestos[] = $impuesto;
    }

    /**
     * Determina cuales son los impuestos y retenciones que el usuario ha definido en los campos optativos de items.
     *
     * @return array
     */
    private function determinarImpuestosRetenciones() {
        return array_filter($this->informacionAdicionalItems, function ($item) {
            $impuestos = [
                ['pattern' => '/^porcentaje_[a-zA-Z0-9_]{0,255}_impuesto$/', 'remove' => ['porcentaje_', '_impuesto']],
                ['pattern' => '/^impuesto_unidad_[a-zA-Z0-9_]{0,255}_impuesto_unidad$/', 'remove' => ['impuesto_unidad_', '_impuesto_unidad']],
                ['pattern' => '/^base_[a-zA-Z0-9_]{0,255}_impuesto_moneda_extranjera$/', 'remove' => ['base_', '_impuesto_moneda_extranjera']],
                ['pattern' => '/^base_[a-zA-Z0-9_]{0,255}_impuesto$/', 'remove' => ['base_', '_impuesto']],
                ['pattern' => '/^valor_[a-zA-Z0-9_]{0,255}_impuesto_moneda_extranjera$/', 'remove' => ['valor_', '_impuesto_moneda_extranjera']],
                ['pattern' => '/^valor_[a-zA-Z0-9_]{0,255}_impuesto$/', 'remove' => ['valor_', '_impuesto']],
                ['pattern' => '/^impuesto_unidad_[a-zA-Z0-9_]{0,255}_impuesto_base_moneda_extranjera$/', 'remove' => ['impuesto_unidad_', '_impuesto_base_moneda_extranjera']],
                ['pattern' => '/^impuesto_unidad_[a-zA-Z0-9_]{0,255}_impuesto_base$/', 'remove' => ['impuesto_unidad_', '_impuesto_base']],
                ['pattern' => '/^impuesto_unidad_[a-zA-Z0-9_]{0,255}_impuesto_valor_unitario_moneda_extranjera$/', 'remove' => ['impuesto_unidad_', '_impuesto_valor_unitario_moneda_extranjera']],
                ['pattern' => '/^impuesto_unidad_[a-zA-Z0-9_]{0,255}_impuesto_valor_unitario$/', 'remove' => ['impuesto_unidad_', '_impuesto_valor_unitario']],
            ];

            $retenciones = [
                ['pattern' => '/^porcentaje_[a-zA-Z0-9_]{0,255}_retencion$/', 'remove' => ['porcentaje_', '_retencion']],
                ['pattern' => '/^retencion_unidad_[a-zA-Z0-9_]{0,255}_retencion_unidad$/', 'remove' => ['retencion_unidad_', '_retencion_unidad']],
                ['pattern' => '/^base_[a-zA-Z0-9_]{0,255}_retencion_moneda_extranjera$/', 'remove' => ['base_', '_retencion_moneda_extranjera']],
                ['pattern' => '/^base_[a-zA-Z0-9_]{0,255}_retencion$/', 'remove' => ['base_', '_retencion']],
                ['pattern' => '/^valor_[a-zA-Z0-9_]{0,255}_retencion_moneda_extranjera$/', 'remove' => ['valor_', '_retencion_moneda_extranjera']],
                ['pattern' => '/^valor_[a-zA-Z0-9_]{0,255}_retencion$/', 'remove' => ['valor_', '_retencion']],
                ['pattern' => '/^retencion_unidad_[a-zA-Z0-9_]{0,255}_retencion_base_moneda_extranjera$/', 'remove' => ['retencion_unidad_', '_retencion_base_moneda_extranjera']],
                ['pattern' => '/^retencion_unidad_[a-zA-Z0-9_]{0,255}_retencion_base$/', 'remove' => ['retencion_unidad_', '_retencion_base']],
                ['pattern' => '/^retencion_unidad_[a-zA-Z0-9_]{0,255}_retencion_valor_unitario_moneda_extranjera$/', 'remove' => ['retencion_unidad_', '_retencion_valor_unitario_moneda_extranjera']],
                ['pattern' => '/^retencion_unidad_[a-zA-Z0-9_]{0,255}_retencion_valor_unitario$/', 'remove' => ['retencion_unidad_', '_retencion_valor_unitario']],
            ];

            foreach ($impuestos as $k => $impuesto) {
                if (preg_match($impuesto['pattern'], $item)) {
                    $this->agregarImpuesto(str_replace($impuesto['remove'], ['', ''], $item));
                    return true;
                }
            }

            foreach ($retenciones as $k => $retencion) {
                if (preg_match($retencion['pattern'], $item)) {
                    $this->agregarRetencion(str_replace($retencion['remove'], ['', ''], $item));
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Validad que la columnas de documentos adicionales este formada correctamente.
     *
     * @param $registro
     * @param int $fila
     * @return array
     */
    private function validarDocumentosAdicionales($registro, int $fila) {
        if (array_key_exists('documento_adicional', $registro) && !empty($registro['documento_adicional'])) {
            if (is_string($registro['documento_adicional'])) {
                $datos = explode('|', $registro['documento_adicional']);
                foreach ($datos as $dat)
                    if (!preg_match('/[0-9a-zA-Z]{2}~[0-9a-zA-Z]{1,5}~[0-9a-zA-Z]{1,20}~[0-9a-zA-Z]{1,1000}~[0-9]{4}-[0-9]{2}-[0-9]{2}/', $dat))
                        return ['error' => true, 'errores' => ["El campo cdo_documento_adicional no tiene el formato adecuado en la fila [{$fila}]"]];
            } else {
                return ['error' => true, errores => ["El campo cdo_documento_adicional no tiene el formato adecuado en la fila [{$fila}]"]];
            }
        }
        return ['error' => false];
    }

    /**
     * Evalua si el excel contiene una seccion de impuestos completa o no.
     *
     * @param array $camposOpcionalesItems
     * @param array $colecion
     * @param string $modo
     * @return array
     */
    private function evaluarObjetosTributo(array $camposOpcionalesItems, array $colecion, $modo = 'impuesto') {
        $errores = [];
        $formatos = [
            "porcentaje_%s_{$modo}",
            "{$modo}_unidad_%s_{$modo}_unidad",
            "base_%s_{$modo}_moneda_extranjera",
            "base_%s_{$modo}",
            "valor_%s_{$modo}_moneda_extranjera",
            "valor_%s_{$modo}",
            "{$modo}_unidad_%s_{$modo}_base_moneda_extranjera",
            "{$modo}_unidad_%s_{$modo}_base",
            "{$modo}_unidad_%s_{$modo}_valor_unitario_moneda_extranjera",
            "{$modo}_unidad_%s_{$modo}_valor_unitario",
        ];

        $articulo = $modo === 'impuesto' ? 'el' : 'la';

        foreach ($colecion as $grupo) {
            foreach ($formatos as $col) {
                $key = sprintf($col, $grupo);
                if (!in_array($key, $camposOpcionalesItems)) {
                    $columna = strtoupper(str_replace('_', ' ', $key));
                    $errores[] = "Para {$articulo} {$modo} {$grupo} no existe una columan en el archivo de excel llamada {$columna} en la sección de correspondiente de items";
                } else
                    $this->rules[$key] = 'numeric';
            }
        }

        return $errores;
    }

    /**
     * Verifica la estructura de las columnas del excel
     *
     * @param $ofe_id
     * @return array
     */
    private function validarColumnas($ofe_id) {
        $faltantesObligatoriosCabecera = [];
        $faltantesAdicionalCabecera = [];
        $faltantesOptativasCabecera = [];
        $faltantesObligatoriosItems = [];
        $faltantesAdicionalItems = [];
        $faltantesCargosItem = [];

        /*
         * Verificando la estructura de las columnas obligatorias en cabecera
         */
        if ($this->tipo !== ConstantsDataInput::FC && $this->tipo !== ConstantsDataInput::NC_ND)
            return ['error' => true, 'errores' => ['El tipo de documento no es soportado']];
        $verificarCabecera = ($this->tipo === ConstantsDataInput::FC) ? ColumnsExcelFNC::$columnasObligatoriasCabeceraFC :
            ColumnsExcelFNC::$columnasObligatoriasCabeceraNCND;

        $excluir = ($this->tipo === ConstantsDataInput::FC) ? ColumnsExcelFNC::$excluirValidacionCabeceraFC :
            ColumnsExcelFNC::$excluirValidacionCabeceraNCND;

        $intersectCabecera = array_intersect($verificarCabecera, $this->keys);
        $FaltantesColumnasCabecera = array_diff($verificarCabecera, $intersectCabecera);
        $FaltantesColumnasCabecera = array_diff($FaltantesColumnasCabecera, $excluir);
        if (!empty($FaltantesColumnasCabecera)) {
            foreach ($FaltantesColumnasCabecera as $key=>$column) {
                $faltantesObligatoriosCabecera[] = "Falta la Columna: " . strtoupper(str_replace('_', ' ', $column));
            }
        }

        /*
         * Determinando los campos adicionales que se gestiona en el apartado de cabecera del
         * documento
         */
        $this->columnasAdicionalCabecera = array_intersect(ColumnsExcelFNC::$informacionAdicionalCabecera, $this->keys);
        $this->informacionAdicionalCabecera = array_diff(ColumnsExcelFNC::$informacionAdicionalCabecera, $this->columnasAdicionalCabecera);
        if (!empty($this->informacionAdicionalCabecera)) {
            foreach ($this->informacionAdicionalCabecera as $key=>$column) {
                $faltantesAdicionalCabecera[] = "Falta la Columna: " . strtoupper(str_replace('_', ' ', $column));
            }
        }

        /*
         * Determinando los campos Optativas de cabecera del documento
         */
        $this->columnasOptativasCabecera = array_intersect(ColumnsExcelFNC::$columnasOptativasCabecera, $this->keys);

        $this->faltantesOptativasCabecera = array_diff(ColumnsExcelFNC::$columnasOptativasCabecera, $this->columnasOptativasCabecera);
        $excluirOptativas = ($this->tipo === ConstantsDataInput::FC) ? [] :
            ColumnsExcelFNC::$excluirValidacionOptativaNCND;
        $this->faltantesOptativasCabecera = array_diff($this->faltantesOptativasCabecera, $excluirOptativas);
        if (!empty($this->faltantesOptativasCabecera)) {
            foreach ($this->faltantesOptativasCabecera as $key=>$column) {
                $faltantesOptativasCabecera[] = "Falta la Columna: " . strtoupper(str_replace('_', ' ', $column));
            }
        }

        /*
        * Verificando la estructura de columnas obligatorias en items.
        */
        $this->columnasObligatoriasItems = array_intersect(ColumnsExcelFNC::$columnasObligatoriasItems, $this->keys);
        $this->faltantesObligatoriasItems = array_diff(ColumnsExcelFNC::$columnasObligatoriasItems, $this->columnasObligatoriasItems);
        if (!empty($this->faltantesObligatoriasItems)) {
            foreach ($this->faltantesObligatoriasItems as $key=>$column) {
                $faltantesObligatoriosItems[] = "Falta la Columna: " . strtoupper(str_replace('_', ' ', $column));
            }
        }

        /*
         * Determinando los campos de informacion adicional del documento
         */
        $this->columnasAdicionalItems = array_intersect(ColumnsExcelFNC::$columnasAdicionalItems, $this->keys);
        $this->faltantesAdicionalItems = array_diff(ColumnsExcelFNC::$columnasAdicionalItems, $this->columnasAdicionalItems);
        if (!empty($this->faltantesAdicionalItems)) {
            foreach ($this->faltantesAdicionalItems as $key=>$column) {
                $faltantesAdicionalItems[] = "Falta la Columna: " . strtoupper(str_replace('_', ' ', $column));
            }
        }

        /*
         * Determinando los campos de los cargos del documento
         */
        $this->columnasCargosItem = [];
        foreach (ColumnsExcelFNC::$columnasCargosItem as $cargosItem){
            $columnasCargosItem = array_intersect($cargosItem, $this->keys);
            array_push($this->columnasCargosItem, $columnasCargosItem);
            $this->faltantesCargosItem = array_diff($cargosItem, $columnasCargosItem);
            foreach ($this->faltantesCargosItem as $key=>$column) {
                $faltantesCargosItem[] = "Falta la Columna: " . strtoupper(str_replace('_', ' ', $column));
            }
        }

        $errores = array_merge($faltantesObligatoriosCabecera, $faltantesAdicionalCabecera,
            $faltantesObligatoriosItems, $faltantesAdicionalItems, $faltantesCargosItem, $faltantesOptativasCabecera);

        if (count($errores)) {
            return [
                'error' => true,
                'errores' => $errores
            ];
        }

        return ['error' => false];
    }

    /**
     * Transforma la data segun las parametros enviados.
     *
     * @param array $config array de configuracion para poder aplicar algunas de los tipos de filtros
     * @param string $columna_value valor de la columna que se intenta transformar.
     * @param array $registros Referencia de todos los registros de la data que se intenta transformar.
     * @param array $extra_data Variable usada para pasar datos extras cuando se ejecuta el tipo de transformacion Callback
     * return string nuevo valor de la columna.
     * @return Carbon|\Carbon\CarbonInterface|string
     * @throws \Exception
     */
    private function transform(array $config, string $columna_value, array &$registros = [], ...$extra_data) {
        switch ($config['type']){
            case 'date':
                try {
                    $date = Carbon::parse(trim($columna_value));
                    $date = $date->toDateString();
                } catch (\Exception $e) {
                    $date = '';
                }
                return $date;
                break;
            case 'callback':
                if (!empty($config['do']) && is_callable($config['do'])) {
                    $response = (string) $config['do']($columna_value, $registros, $extra_data);
                    return $response;
                }
            default:
                return $columna_value;
                break;
        }
    }

    /**
     * Determina si los impuestos que se han definido en el excel existen y estan activos en etl_openmain.
     *
     * @param array $errores
     */
    private function checkImpuestosRetenciones(array &$errores) {
        foreach ($this->listaImpuestos as $impuesto) {
            if (array_key_exists(strtolower($impuesto), $this->impuestosRegistrados) == 0) {
                $errores[] = "El impuesto $impuesto no esta registrado";
            }
        }

        foreach ($this->listaRetenciones as $retencion) {
            if (array_key_exists(strtolower($retencion), $this->impuestosRegistrados) == 0) {
                $errores[] = "Para la retención $retencion no esta registrada";
            }
        }
    }

    /**
     * Ejecuta el registro de la data.
     *
     * @param int $ofe_id ID del OFE
     * @return string
     * @throws \Exception
     */
    private function procesador($ofe_id) {
        $documentos = [];
        // Agrupando la data por documentos
        foreach ($this->data as $k => $item) {
            if (array_key_exists('prefijo', $item) && array_key_exists('consecutivo', $item)) {
                $key = $item['prefijo'] . $item['consecutivo'];
                if (array_key_exists($key, $documentos))
                    $documentos[$key][] = ['data' => $item, 'linea' => $k + 2];
                else
                    $documentos[$key] = [['data' => $item, 'linea' => $k + 2]];
            }
        }

        //Verificando cada documento
        foreach ($documentos as $key => $doc) {
            $response = $this->validarDocumento($doc);
            if ($response['error'])
                $this->documentosConFallas[$key] = $response;
            else {
                if (in_array($response['cabecera']['obligatorias']['cod_tipo_documento'], ConstantsDataInput::codigosValidos ))
                    $this->documentosParaAgendamiento[$key] = $response;
                else {
                    $this->documentosConFallas[$key] = [
                        'error' => true,
                        'errores' => ['No se puede determinar el tipo de ' . $response['cabecera']['obligatorias']['prefijo'] . $response['cabecera']['obligatorias']['consecutivo'] . 'El tipo de operación no es valido.']
                    ];
                }
            }
        }

        if (!empty($this->documentosParaAgendamiento)) {
            $user = auth()->user();
            $ofe  = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_prioridad_agendamiento'])
                ->where('ofe_id', $ofe_id)
                ->first();

            $prioridad = !empty($ofe->ofe_prioridad_agendamiento) ? $ofe->ofe_prioridad_agendamiento : null;

            // Divide los documentos por bloques de acuerdo al máximo permitido en la BD para EDI
            $grupos = array_chunk($this->documentosParaAgendamiento, $user->getBaseDatos->bdd_cantidad_procesamiento_edi);
            foreach ($grupos as $grupo) {
                $jsonBuilder = new JsonEtlBuilder($this->tipo);
                foreach ($grupo as $doc) {
                    $jsonDoc = new JsonDocumentBuilder($doc['cabecera'], $doc['items'] ?? [],
                        $this->listaImpuestos ?? [], $this->listaRetenciones ?? [], $this->impuestosRegistrados ?? [], 'FNC', 'manual');
                    $jsonDoc->setColumnasInformacionAdicionalCabecera($this->informacionAdicionalCabecera);
                    $jsonDoc->setColumnasInformacionAdicionalItems(ColumnsExcelFNC::$columnasAdicionalItems);
                    $jsonDoc->setColumnasCargoslItems($this->columnasCargosItem);
                    $jsonDoc->setTipoDocumento($this->tipo);
                    $jsonBuilder->addDocument($jsonDoc);
                }
                $json = $jsonBuilder->build();

                if ($this->tipo != ConstantsDataInput::FC) {
                    $depurar_json = (array) json_decode($json);
                    if (!empty($depurar_json['documentos']->{ConstantsDataInput::ND})){
                        unset(
                            //ND
                            $depurar_json['documentos']->{ConstantsDataInput::ND}[0]->prefijo_factura,
                            $depurar_json['documentos']->{ConstantsDataInput::ND}[0]->consecutivo_factura,
                            $depurar_json['documentos']->{ConstantsDataInput::ND}[0]->observacion_correccion,
                            $depurar_json['documentos']->{ConstantsDataInput::ND}[0]->cufe_factura,
                            $depurar_json['documentos']->{ConstantsDataInput::ND}[0]->hora_factura,
                            $depurar_json['documentos']->{ConstantsDataInput::ND}[0]->fecha_factura
                        );
                    } else {
                        unset($depurar_json['documentos']->{ConstantsDataInput::ND});
                    }

                    if (!empty($depurar_json['documentos']->{ConstantsDataInput::NC})){
                        unset(
                            //NC
                            $depurar_json['documentos']->{ConstantsDataInput::NC}[0]->prefijo_factura,
                            $depurar_json['documentos']->{ConstantsDataInput::NC}[0]->consecutivo_factura,
                            $depurar_json['documentos']->{ConstantsDataInput::NC}[0]->observacion_correccion,
                            $depurar_json['documentos']->{ConstantsDataInput::NC}[0]->cufe_factura,
                            $depurar_json['documentos']->{ConstantsDataInput::NC}[0]->hora_factura,
                            $depurar_json['documentos']->{ConstantsDataInput::NC}[0]->fecha_factura
                        );
                    } else {
                        unset($depurar_json['documentos']->{ConstantsDataInput::NC});
                    }
                    $json = json_encode($depurar_json);
                }

                // Crea el agendamiento en el sistema
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $user->usu_id,
                    'bdd_id'                  => $user->bdd_id,
                    'age_proceso'             => 'EDI',
                    'age_cantidad_documentos' => 1,
                    'age_prioridad'           => $prioridad,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);

                // Graba la información del Json en la tabla de programacion de procesamientos de Json
                EtlProcesamientoJson::create([
                    'pjj_tipo' => 'EDI',
                    'pjj_json' => $json,
                    'pjj_procesado' => 'NO',
                    'age_id' => $agendamiento->age_id,
                    'age_estado_proceso_json' => null,
                    'usuario_creacion' => $user->usu_id,
                    'estado' => 'ACTIVO',
                ]);
            }

            $N = count($this->documentosParaAgendamiento);
            if ($N === 1)
                $message = "Se ha agendado 1 documento para su procesamiento en background";
            else
                $message = "Se ha agendado $N documentos para su procesamiento en background";
        } else
            $message = "No se han agendado documentos";

        return $message;
    }

    /**
     * Evalua que cada fila del excel correspondiente a un documento contenga información consistente entre si
     * @param $documento
     * @return array
     * @throws \Exception
     */
    private function validarDocumento($documento) {
        $errores = [];
        $cabecera = [];

        /*
         * Inicializando el registro de cabecera para verificar la consistencia del mismo con cada una de las demas filas
         * (Si las hay)
         */
        $columnasCabecera = ($this->tipo === ConstantsDataInput::FC) ? ColumnsExcelFNC::$columnasObligatoriasCabeceraFC : ColumnsExcelFNC::$columnasObligatoriasCabeceraNCND;
        $excluir = ($this->tipo === ConstantsDataInput::FC) ? ColumnsExcelFNC::$excluirValidacionCabeceraFC : ColumnsExcelFNC::$excluirValidacionCabeceraNCND;
        $__registro = $documento[0]['data'];
        $columnasCabecera = array_diff($columnasCabecera, $excluir);
        foreach ($columnasCabecera as $col) {
            $cabecera[$col] = $__registro[$col];
        }

        //Guardamos los campos de informacion adicional de cabecera
        $columnasInformacionAdicionalCabecera = ColumnsExcelFNC::$informacionAdicionalCabecera;
        $InformacionAdicionalCabecera = [];
        foreach ($columnasInformacionAdicionalCabecera as $col) {
            $InformacionAdicionalCabecera[$col] = $__registro[$col];
        }

        //Guardamos los campos optativos de cabecera
        $columnasOptativasCabecera = ColumnsExcelFNC::$columnasOptativasCabecera;
        $informacionColumnasOptativasCabecera = [];
        $excluirOptativas = ($this->tipo === ConstantsDataInput::FC) ? [] :
            ColumnsExcelFNC::$excluirValidacionOptativaNCND;
        $columnasOptativasCabecera = array_diff($columnasOptativasCabecera, $excluirOptativas);

        if (!empty($columnasOptativasCabecera)) {
            foreach ($columnasOptativasCabecera as $col) {
                $index = ColumnsExcelFNC::$MapColumnasOptativasCabecera[$col];
                if (is_string($index)){
                    $informacionColumnasOptativasCabecera[$index] = $__registro[$col];
                } elseif (is_array($index)) {
                    $callback_value = (string) $this->transform($index, $__registro[$col]);
                    $informacionColumnasOptativasCabecera[$index['label']] = $callback_value;
                }
            }
        }

        $items = [];
        foreach ($documento as $item) {
            $__registro = $item['data'];

            $opcionalesItems = [];
            $adicionalesItems = [];
            $cargos_extendido = [];
            $datos = [];
            foreach (ColumnsExcelFNC::$columnasObligatoriasItems as $col) {
                //MAPEAMOS LOS DATOS
                $datos[ColumnsExcelFNC::$MapColumnasItemObligatorios[$col]] = $__registro[$col];
            }

            foreach (ColumnsExcelFNC::$columnasCargosItem as $key=>$cargosItem){
                foreach ($cargosItem as $col) {
                    $cargos_extendido[$key][ColumnsExcelFNC::$MapColumnasCargosItem[$key][$col]] = $__registro[$col];
                }
            }

            foreach (ColumnsExcelFNC::$columnasAdicionalItems as $col) {
                $adicionalesItems[$col] = $__registro[$col];
            }
            $items[] = [
                'obligatorias' => $datos,
                'opcionales' => $opcionalesItems,
                'cargos_extendido' => $cargos_extendido,
                'informacion_adicional' => $adicionalesItems
            ];
        }


//        $respuesta = $this->validarDocumentosAdicionales($__registro, $fila);
//        if ($respuesta['error'])
//            $errores = array_merge($errores, $respuesta['errores']);

//         Si hay mas de un item validamos que los datos de cabecera sean iguales en todos
        if (!empty($documento)) {
            $cantidad_documentos = count($documento);
            for ($i = 1; $i < $cantidad_documentos; $i++) {
                $__registro = $documento[$i]['data'];
                $fila = $documento[$i]['linea'];
                foreach ($columnasCabecera as $col) {
                    if ($__registro[$col] !== $cabecera[$col])
                        $errores[] = "El registro $i presentan inconsistencias en columna de cabecera ". ($this->nombresReales[$col] ?? '') ." de la fila $fila";
                }

                //Validamos el documento
                $validador = Validator::make($__registro, $this->rules);
                if ($validador->fails()) {
                    $fallas = $validador->errors()->all();
                    foreach ($fallas as $reg) {
                        $errores[] = "$reg en la fila [{$fila}]";
                    }
                }

//                $respuesta = $this->validarDocumentosAdicionales($__registro, $fila);
//                if ($respuesta['error'])
//                    $errores = array_merge($errores, $respuesta['errores']);
            }

            // Si se trata de una NC o ND se debe cambiar el tipo de documento a su valor real
            if ($this->tipo !== ConstantsDataInput::FC) {
                if (array_key_exists('cod_tipo_documento', $cabecera)) {
                    if (strtolower($cabecera['cod_tipo_documento']) === 'nc')
                        $cabecera['cod_tipo_documento'] = '91';
                    if (strtolower($cabecera['cod_tipo_documento']) === 'nd')
                        $cabecera['cod_tipo_documento'] = '92';
                }
            }
        }

        $fila = $documento[0]['linea'];
        if ($this->tipo === ConstantsDataInput::FC) {
            $this->tipo_documento = ConstantsDataInput::FC;
            if ($cabecera['cod_tipo_documento'] !== '01' && $cabecera['cod_tipo_documento'] !== '02' && $cabecera['cod_tipo_documento'] !== '03' && $cabecera['cod_tipo_documento'] !== '04') {
                $errores[] = "El tipo de documento no se corresponde para algun tipo de factura en la fila [{$fila}]";
            }
        } elseif ($this->tipo === ConstantsDataInput::NC) {
            $this->tipo_documento = ConstantsDataInput::NC;
            if ($cabecera['cod_tipo_documento'] !== '91' && $cabecera['cod_tipo_documento'] !== '92') {
                $errores[] = "El tipo de documento no se corresponde para notas credito o notas debito en la fila [{$fila}]";
            }
        } elseif ($this->tipo === ConstantsDataInput::ND) {
            $this->tipo_documento = ConstantsDataInput::ND;
            if ($cabecera['cod_tipo_documento'] !== '92' && $cabecera['cod_tipo_documento'] !== '92') {
                $errores[] = "El tipo de documento no se corresponde para notas credito o notas debito en la fila [{$fila}]";
            }
        }


        // TRANSFORMACIONES DE DATOS SEGUN LAS VALIDACIONES QUE DE DEBEN APLICAR
        $cabecera = $this->transformDatosCabecera($cabecera, $items, $__registro);
        $items = $this->transformDatosObligatoriosItems($cabecera, $items);
        $items = $this->transformDatosAdicionalesItems($cabecera, $items);
        $items = $this->transformDatosCargosItems($cabecera, $items);

        $this->rules = ($this->tipo === ConstantsDataInput::FC) ? $this->rulesFC : $this->rulesNCND;
        //Validamos el documento
        $validador = Validator::make($__registro, $this->rules);
        if ($validador->fails()) {
            $fallas = $validador->errors()->all();
            foreach ($fallas as $reg) {
                $errores[] = "$reg en la fila [{$fila}]";
            }
        }

        $respuesta = [
            'error' => !empty($errores),
            'errores' => $errores
        ];
        if ($this->tipo !== ConstantsDataInput::FC && array_key_exists('motivo_pedido', $cabecera)) {
            $InformacionAdicionalCabecera['motivo_pedido'] = $cabecera['motivo_pedido'];
            if ($cabecera['motivo_pedido'] === '') {
                if ($cabecera['cod_tipo_documento'] === '91' || $cabecera['cod_tipo_documento'] === '92') {
                    $tipo = $cabecera['cod_tipo_documento'] === '91' ? 'NC' : 'ND';
                }
                else
                    $tipo = $cabecera['cod_tipo_documento'];
                $concepto = ParametrosConceptoCorreccion::select(['cco_id', 'cco_tipo', 'cco_codigo', 'cco_descripcion'])
                    ->where('cco_codigo', $cabecera['cod_correccion'])
                    ->where('cco_tipo', $tipo)
                    ->first();

                if (!is_null($concepto))
                    $cabecera['motivo_pedido'] = $concepto->cco_descripcion;
            }
        }

        $respuesta['cabecera'] = [
            'obligatorias' => $cabecera,
            'opcionales' => $informacionColumnasOptativasCabecera,
            'informacion_adicional' => $InformacionAdicionalCabecera
        ];

        $respuesta['items'] = $items;
        return $respuesta;
    }

    /**
     * Evalua la existencia de un grupo de datos dentro de DAD
     *
     * @param array $conjunto
     * @param array $grupo
     * @return array
     */
    private function evaluarGrupo(array $conjunto, array $grupo) {
        $contador = 0;
        $noexisten = [];
        foreach ($grupo as $item) {
            if (in_array($item, $conjunto))
                $contador++;
            else
                $noexisten[] = $item;
        }
        // Si hay algo en el grupo pero no son todos los elementos
        if ($contador > 0 && $contador < count($grupo))
            return [
                'error' => true,
                'faltantes' => $noexisten
            ];
        return ['error' => false];
    }

    /**
     * Registra los campos faltantes para indicar un error en el formato del archivo.
     *
     * @param $errores
     * @param $nombreSeccion
     * @param $respuesta
     */
    private function registrarFallasFaltantes(&$errores, $nombreSeccion, $respuesta) {
        if ($respuesta['error']) {
            if (count($respuesta['faltantes']) > 0) {
                $nombres = [];
                foreach ($respuesta['faltantes'] as $item)
                    $nombres[] = strtoupper(str_replace('_', ' ', $item));
                $campos = implode(',', $nombres);
                $errores[] = "En la sección $nombreSeccion faltan las columnas: $campos";
            }
        }
    }

    /**
     * Retorna un Closure que permite modificar los campos segun si la moneda final es COP o no.
     *
     * Cambia el valor de un campo por el otro segun el parametro $field
     *
     * @param Mixed $field Campo por el cual se quiere realizar el cambio
     * @param array $cabecera Referencia de los campos de cabecera.
     * @return \Closure Function que modifica los campos adicional de item.
     */
    private function getClosureToChangeAnotherFieldInformacionAdicionalItem($field, &$cabecera){
        return $this->getClosureToChangeAnotherFieldObligatoriasItem($field, $cabecera, 'informacion_adicional');
    }

    /**
     * Retorna un Closure que permite modificar los campos segun si la moneda final es COP o no.
     *
     * Cambia el valor de un campo por el otro segun el parametro $field
     *
     * @param Mixed $field Campo por el cual se quiere realizar el cambio
     * @param array $cabecera Referencia de los campos de cabecera.
     * @param string $item_type Tipo de item que se quiere modificar (obligatorias, informacion_adicional, cargos, opcionales)
     * @return \Closure Function que modifica los campos Obligatorias de item.
     */
    private function getClosureToChangeAnotherFieldObligatoriasItem($field, &$cabecera, $item_type='obligatorias'){
        return function ($columna_value, &$item) use (&$cabecera, $field, $item_type) {
            /**
             * Si MONEDA_FINAL es diferente de COP, se guarda en este campo el valor de la variable $field,
             * sino se guarda el valor de esta columna.
             */
            $cod_moneda = $cabecera['cod_moneda'];
            $obligatoriasItem = $item[$item_type] ?? $item;

            if ($cod_moneda != 'COP') {
                return $obligatoriasItem[$field];
            }
            return $columna_value;
        };
    }

    /**
     * Retorna un Closure que permite modificar los campos a zero segun si la moneda final es COP o no.
     *
     * Cambia el valor del campo a zero.
     *
     * @param array $cabecera Referencia de los campos de cabecera.
     * @return \Closure Function que modifica los campos items.
     */
    private function getClosureToValidateFieldToZero(&$cabecera){
        return function ($columna_value) use (&$cabecera) {
            /**
             * Si MONEDA_FINAL es diferente de COP,
             * guardar en este campo el valor cero.
             * Sino guardar el valor de esta columna.
             */
            $cod_moneda = $cabecera['cod_moneda'];

            if ($cod_moneda !== 'COP') {
                return '0.00';
            }
            return $columna_value;
        };
    }

    /**
     * Ejecuta las funciones de transformacion de datos de cabecera.
     *
     * @param array $cabecera campos de cabecera del documento.
     * @param array $items campos de items del documento.
     * @param array todos los registros (items y cabecera combinados)
     * return array $cabecera.
     * @return array
     * @throws \Exception
     */
    private function transformDatosCabecera($cabecera, $items, $registro) {
        // TRANSFORMACIONES DE DATOS SEGUN LAS VALIDACIONES QUE DE DEBEN APLICAR
        /**
         * Validar Campos de Cabecera
         */
        $transformarColumnasCabecera  = [
            'mon_codigo_extranjera' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$registros) use (&$cabecera, $items) {
                    /**
                     * Si esta columna es igual a COP o vacia, en mon_codigo guardar COP
                     * y en mon_codigo_extranjera guarda null.
                     * Si no, recorrer todos los items:
                     * Si algun valor de la columna TOTAL SIN PRIMAS es mayor a cero,
                     * en mon_codigo guardar COP y en mon_codigo_extranjera guardar el valor de esta columna.
                     * Si para todos los items la columna TOTAL SIN PRIMAS tienen valor cero o es vacia,
                     * en mon_codigo guardar el valor de esta columna y en mon_codigo_extranjera guarda null.
                     * Crear una variable de control, que para este excel nombraremos MONEDA_FINAL,
                     * si cod_moneda es diferente de COP se debe marcar
                     * cdo_envio_dian_moneda_extranjera se debe guardar con valor SI."
                     *
                     */

                    $valor_inicial_moneda = $cabecera['cod_moneda'];
                    if ($valor_inicial_moneda == 'COP' || empty($valor_inicial_moneda)) {
                        $cabecera['cod_moneda'] = 'COP';
                        return null;
                    }

                    foreach ($items as $item) {
                        $obligatorias = $item['obligatorias'];
                        if (!empty($obligatorias['total']) && $obligatorias['total'] > 0) {
                            $cabecera['cod_moneda'] = 'COP';
                            return $valor_inicial_moneda;
                        }
                    }
                    $cabecera['cod_moneda'] = $valor_inicial_moneda;
                    return null;
                },
            ],
            'cdo_envio_dian_moneda_extranjera' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$registros) use (&$cabecera) {

                    /**
                     * Si mon_codigo es diferente de mon_codigo_extranjera, y el campo mon_codigo_extranjera es
                     * diferente de null,
                     * en el campo cdo_envio_dian_moneda_extranjera se debe guardar con valor SI.
                     */

                    $moneda = $cabecera['cod_moneda'];
                    $moneda_extranjera = $cabecera['mon_codigo_extranjera'];
                    if ($moneda != $moneda_extranjera && !empty($moneda_extranjera)){
                        return 'SI';
                    }
                    return 'NO';
                },
            ],
            'fecha_vencimiento_pago' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$registros) use (&$cabecera) {
                    /**
                     * Si la forma de pago es 2, se debe guardar cdo_medios_pago.men_fecha_vencimiento
                     * con el valor de la columna FECHA VENCIMIENTO
                     */
                    if ($cabecera['cod_forma_de_pago'] == '2') {
                        return $cabecera['fecha_vencimiento'];
                    }
                    return '';
                },
            ],
            'adq_identificacion_autorizado' => [
                'type' => 'callback',
                'do' => function () {}
            ],
            'diferencia_redondeo_moneda_extranjera' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$registros) use (&$cabecera) {
                    return $cabecera['diferencia_redondeo_moneda_extranjera'];
                },
            ],
            'fecha_factura' => [
                "label" => "fecha_factura",
                "type" => "date",
                "format" => "Y-m-d",
            ],
            'fecha' => [
                "label" => "fecha_factura",
                "type" => "date",
                "format" => "Y-m-d",
            ],
            'fecha_vencimiento' => [
                "label" => "fecha_vencimiento",
                "type" => "date",
                "format" => "Y-m-d",
            ],
            'fecha_trm' => [
                "label" => "fecha_trm",
                "type" => "date",
                "format" => "Y-m-d",
            ],
            'fecha_emision' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$registros) use (&$cabecera) {
                    return "{$cabecera['fecha_factura']}";
                }
            ],
            'cod_medio_de_pago' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$registros) use (&$cabecera) {
                    if ($columna_value == '2'){
                        $cabecera['fecha_vencimiento_pago'] = $cabecera['fecha_vencimiento'];
                    }
                    return $columna_value;
                }
            ]
        ];

        foreach ($transformarColumnasCabecera as $key=>$config) {
            $cabecera[$key] = $this->transform($config, $registro[$key] ?? '', $registro);
        }
        //FIN DE LAS TRANFORMACIONES DE DATOS DE CABECERA

        return $cabecera;
    }

    /**
     * Ejecuta las funciones de transformacion de datos de Items obligatorios.
     *
     * @param array $cabecera campos de cabecera del documento.
     * @param array $items campos de items del documento.
     * return array $items.
     * @return array
     */
    private function transformDatosObligatoriosItems($cabecera, $items) {
        // TRANSFORMACIONES DE DATOS SEGUN LAS VALIDACIONES QUE DE DEBEN APLICAR
        /**
         * Validar Campos de Items
         */
        $transformarColumnasItems  = [
            'cod_precio_referencia' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$item) use (&$cabecera) {

                    /**
                     * Si MONEDA_FINAL es diferente de COP, se debe guardar en ddo_precio_referencia.ddo_valor_muestra
                     * el valor de la columna TOTAL SIN PRIMAS MONEDA EXTRANJERA, y en los campos
                     * ddo_precio_referencia.ddo_valor_muestra_moneda_extranjera,
                     * ddo_total y ddo_total_moneda_extranjera el valor cero.
                     * Si no se cumple la condición, se debe guardar en ddo_precio_referencia.ddo_valor_muestra
                     * el valor de la columna TOTAL SIN PRIMAS y en
                     * ddo_precio_referencia.ddo_valor_muestra_moneda_extranjera el valor de la columna TOTAL SIN PRIMAS
                     * MONEDA EXTRANJERA, y en los campos ddo_total y ddo_total_moneda_extranjera el valor cero.
                     * COD PRECIO REFERENCIA => Se debe modificar que esta logica solo se hace si esta columna es diferente de vacio,
                     * si no es vacia se debe incluir el campo ddo_indicador_muestra con true, si es vacia no se
                     * debe incluir la sección de ddo_precio_referencia.
                     */
                    $cod_moneda = $cabecera['cod_moneda'];
                    $obligatoriasItem = &$item['obligatorias'];

                    if (!empty($columna_value)) {
                        if ($cod_moneda != 'COP') {
                            $obligatoriasItem['valor_muestra'] = $obligatoriasItem['total_moneda_extranjera'];
                            $obligatoriasItem['valor_muestra_moneda_extranjera'] = 0;
                            $obligatoriasItem['total'] = 0;
                            $obligatoriasItem['total_moneda_extranjera'] = 0;
                        } else {
                            $obligatoriasItem['valor_muestra'] = $obligatoriasItem['total'];
                            $obligatoriasItem['valor_muestra_moneda_extranjera'] = $obligatoriasItem['total_moneda_extranjera'];
                            $obligatoriasItem['total'] = 0;
                            $obligatoriasItem['total_moneda_extranjera'] = 0;
                        }
                    }
                    return $columna_value;
                },
            ],
            'valor_unitario' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('valor_unitario_moneda_extranjera', $cabecera),
            ],
            'total_sin_primas' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('total_moneda_extranjera', $cabecera),
            ],
            'descripcion_2' => [
                'type' => 'callback',
                'do' => function () {},
            ],
            'descripcion_3' => [
                'type' => 'callback',
                'do' => function () {},
            ],
            'cantidad_paquete' => [
                'type' => 'callback',
                'do' => function () {},
            ],
            'muestra_comercial' => [
                'type' => 'callback',
                'do' => function () {},
            ],
            'nit_mandatario' => [
                'type' => 'callback',
                'do' => function () {},
            ],

            // TRIBUTOS TRANSFORM
            'base_iva' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('base_iva_moneda_extranjera', $cabecera),
            ],
            'valor_iva' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('valor_iva_moneda_extranjera', $cabecera),
            ],
            'base_iva_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera),
            ],
            'valor_iva_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera)
            ],
            'base_impuesto_consumo_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera)
            ],
            'base_impuesto_consumo' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('base_impuesto_consumo_moneda_extranjera', $cabecera),
            ],
            'valor_impuesto_consumo_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera)
            ],
            'valor_impuesto_consumo' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('valor_impuesto_consumo_moneda_extranjera', $cabecera),
            ],
            'base_ica_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera)
            ],
            'base_ica' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('base_ica_moneda_extranjera', $cabecera),
            ],
            'valor_ica_moneda_extranjera'  => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera)
            ],
            'valor_ica' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('valor_ica_moneda_extranjera', $cabecera),
             ],

            //EXTRAS DATA
            'valor_unitario_moneda_extranjera' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$item) use (&$cabecera) {
                    /**
                     * Si MONEDA_FINAL es diferente de COP,  guardar en este campo el valor cero.
                     * Sino guardar el valor de esta columna.
                     */
                    $cod_moneda = $cabecera['cod_moneda'];

                    if ($cod_moneda != 'COP') {
                        return 0;
                    }
                    return $columna_value;
                },
            ],
            'total' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldObligatoriasItem('total_moneda_extranjera', $cabecera),
            ],
            'total_moneda_extranjera' => [
                'type' => 'callback',
                'do' => function ($columna_value) use (&$cabecera) {
                    /**
                     * Si MONEDA_FINAL es diferente de COP,  guardar en este campo el valor cero.
                     * Sino guardar el valor de esta columna.
                     */
                    $cod_moneda = $cabecera['cod_moneda'];
                    if ($cod_moneda != 'COP') {
                        return 0;
                    }
                    return $columna_value;
                },
            ],
            'ddo_indicador_muestra' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$registros) use (&$cabecera, &$items) {
                    if (!empty($registros['obligatorias']['cod_precio_referencia'] ?? null)){
                        return true;
                    } else {
                        unset($registros['obligatorias']['cod_precio_referencia']);
                    }
                    return null;
                },
            ]
        ];

        $items = array_map(function ($item) use ($transformarColumnasItems, $cabecera) {
            foreach ($transformarColumnasItems as $key=>$config) {
                $item['obligatorias'][$key] = $this->transform($config, $item['obligatorias'][$key] ?? '', $item);
            }

            if($cabecera['representacion_grafica_documento'] == 1) { // Ingles
                $item['valor'] = 'Price';
                $item['moneda'] = 'Currency';
                $item['unidadBase'] = 'Base Unit';
                $item['cantidadBase'] = 'Base Amount';
                $item['valorItem'] = "Value";
            } elseif($cabecera['representacion_grafica_documento'] == 2) { // Español
                $item['valor'] = 'Precio';
                $item['moneda'] = 'Moneda';
                $item['unidadBase'] = 'Unidad Base';
                $item['cantidadBase'] = 'Cantidad Base';
                $item['valorItem'] = "Valor";
            }

            return $item;
        }, $items);

        //FIN DE LAS TRANFORMACIONES DE DATOS DE CABECERA
        return $items;
    }

    /**
     * Retorna un Closure que permite insertar el campo ddo_cargo_razon a los items segun unas condiciones.
     *
     * @param array $cabecera Referencia de los campos de cabecera.
     * @param string $tipo_cargo tipo de cargo que se desea modificar en el closure que se retorna
     * return \Closure
     * @return \Closure
     */
    private function insertar_razon($cabecera, $tipo_cargo){
        return function ($columna_value, &$item) use (&$cabecera, $tipo_cargo){
            /**
             * Adicional en el campo ddo_cargos.razon debe guardarse concatenada la información
             * de las siguientes columnas: PRIMA TECNICAL SUPPORT . ' - ' . $valor . ': ' .
             * number_format(VALOR PRIMA TECNICAL SUPPORT, 2, '.', '') . ' - ' . $moneda . ': ' .
             * MONEDA PRIMA TECNICAL SUPPORT. ' - ' . $unidadBase . ': ' . UNIDAD BASE PRIMA TECNICAL SUPPORT .
             * ' - ' . $cantidadBase . ': ' . CANTIDAD BASE PRIMA TECNICAL SUPPORT
             * donde $valor, $moneda, $unidadBase, $cantidadBase, $valorItem tienen el texto descrito en la
             * columna DESCRIPCION 2 Este cargo solo se debe guardar
             * si el valor de la columna VALOR PRIMA TECNICAL SUPPORT es mayor a cero.
             */


            $informacion_adicional = $item['informacion_adicional'];
            $cargo = &$item['cargos_extendido'][$tipo_cargo];
            $cargo['ddo_cargo_razon'] = '';
            switch ($tipo_cargo){
                case 'tecnical_support':
                    $columna_prima = $informacion_adicional['prima_' . $tipo_cargo];
                    $unidad_base   = $informacion_adicional['unidad_base_prima_' . $tipo_cargo];
                    $cantidad_base = $informacion_adicional['cantidad_base_prima_' . $tipo_cargo];
                    $moneda_prima  = $informacion_adicional['moneda_prima_' . $tipo_cargo];
                    break;
                case 'prima_social':
                    $columna_prima = $informacion_adicional[$tipo_cargo];
                    $unidad_base   = $informacion_adicional['unidad_base_' . $tipo_cargo];
                    $cantidad_base = $informacion_adicional['cantidad_base_' . $tipo_cargo];
                    $moneda_prima  = $informacion_adicional['moneda_' . $tipo_cargo];
                    break;
                case 'otros_cargos':
                    $columna_prima = $informacion_adicional['prima_' . $tipo_cargo];
                    $unidad_base   = $informacion_adicional['unidad_base_prima_' . $tipo_cargo];
                    $cantidad_base = $informacion_adicional['cantidad_base_prima_' . $tipo_cargo];
                    $moneda_prima  = $informacion_adicional['moneda_prima_' . $tipo_cargo];
                    break;
                case 'recargo_vat':
                    $columna_prima = $informacion_adicional['prima_' . $tipo_cargo];
                    $unidad_base   = $informacion_adicional['unidad_base_prima_' . $tipo_cargo];
                    $cantidad_base = $informacion_adicional['moneda_prima_' . $tipo_cargo];
                    break;
                case 'prima_permanentes':
                    $columna_prima = $informacion_adicional[$tipo_cargo];
                    $unidad_base   = $informacion_adicional['unidad_base_' . $tipo_cargo];
                    $cantidad_base = $informacion_adicional['cantidad_base_' . $tipo_cargo];
                    $moneda_prima  = $informacion_adicional['moneda_' . $tipo_cargo];
                    break;
            }

            if (!isset($columna_prima) || (isset($columna_prima) && $columna_prima == '')){
                $cargo = [];
            }

            if (!empty($cargo)) {
                $cargo['ddo_cargo_razon'] =  $columna_value . ' - ' . ($item['valor'] ?? ''). ': '
                    . number_format((int)($cargo['ddo_cargo_valor'] ?? 0), 2, '.', '')
                    . ' - '
                    . ($item['moneda'] ?? '')
                    . ': '
                    . ($moneda_prima ?? '')
                    . ' - '
                    . ($item['unidadBase'] ?? '')
                    . ': '
                    . ($unidad_base ?? '')
                    . ' - '
                    . ($item['cantidadBase'] ?? '')
                    . ': '
                    . ($cantidad_base ?? '');
            }

            return $columna_value;
        };
    }

    /**
     * Ejecuta las funciones de transformacion de datos de Items Adicionales.
     *
     * @param array $cabecera campos de cabecera del documento.
     * @param array $items campos de items del documento.
     * return array $items.
     * @return array
     */
    private function transformDatosAdicionalesItems($cabecera, $items) {

        // TRANSFORMACIONES DE DATOS SEGUN LAS VALIDACIONES QUE DE DEBEN APLICAR
        /**
         * Validar Campos de Items
         */
        $transformarColumnasItems  = [
            'total' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldInformacionAdicionalItem('total_moneda_extranjera', $cabecera),
            ],
            'total_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera),
            ],
            'descripcion_2' => [
                'type' => 'callback',
                'do' => function ($columna_value, &$item) use (&$cabecera) {
                    /**
                     * Adicional hay que guardar esta información en la sección ddo_notas del item,
                     * para esto se debe explotar este campo por el caracter |
                     * y guardar ese array en notas, tener en cuenta que solo se deben guardar aquellas
                     * posisciones donde el trim de la posición sea diferente de vacio.
                     * Adicional por cada una de las posiciones del array que se genero se debe
                     * preguntar si la linea contiene el caracter ^, si no contiene ^ se guarda
                     * en la posicion del array de notas el texto como viene, sino se debe  hacer un explode
                     * por ese caracter, si al menos una de las posiciones es diferente de vacio (hay que hacer trim),
                     * se debe conformar el texto de esa nota de la siguiente manera, sino se ignora la linea:
                     * trim(posicion[0]) . ' - ' . $valor . ': ' . number_format(floatval(trim(posicion[1])), 2, '.', '')
                     * . ' - ' . $moneda . ': ' . trim(posicion[2]) . ' - ' . $unidadBase . ': ' . trim(posicion[4])
                     * . ' - ' . $cantidadBase . ': ' . trim(posicion[3]) . ' - '. $valorItem . ': '
                     * . number_format(floatval(trim(posicion[5])), 2, '.', '')
                     * donde $valor, $moneda, $unidadBase, $cantidadBase, $valorItem tienen el siguiente texto,
                     * segun la representacion grafica enviada:
                     */
                    
                    $item['obligatorias']['notas'] = $columna_value;
                    return $columna_value;
                },
            ],
            'prima_tecnical_support' => [
                'type' => 'callback',
                'do' => $this->insertar_razon($cabecera, 'tecnical_support')
            ],
            'prima_social' => [
                'type' => 'callback',
                'do' => $this->insertar_razon($cabecera, 'prima_social')
            ],
            'prima_otros_cargos' => [
                'type' => 'callback',
                'do' => $this->insertar_razon($cabecera, 'otros_cargos')
            ],
            'prima_recargo_vat' => [
                'type' => 'callback',
                'do' => $this->insertar_razon($cabecera, 'recargo_vat')
            ],
            'prima_permanentes' => [
                'type' => 'callback',
                'do' => $this->insertar_razon($cabecera, 'prima_permanente')
            ],
        ];

        $items = array_map(function ($item) use ($transformarColumnasItems, $cabecera) {

            if ($cabecera['cod_moneda'] !== 'COP') {
                $ajusteCargos = [
                    'tecnical_support' => [
                        'base_de_la_prima_tecnical_support' => 'base_de_la_prima_tecnical_support_en_moneda_extranjera',
                        'valor_total_prima_tecnical_support' => 'valor_total_prima_tecnical_support_moneda_extranjera',
                        'porcentaje_de_la_prima_tecnical_support' => 'porcentaje_de_la_prima_tecnical_support_moneda_extranjera'
                    ],
                    'prima_social' => [
                        'valor_total_prima_social' => 'valor_total_prima_social_moneda_extranjera',
                        'base_de_la_prima_social' => 'base_de_la_prima_social_en_moneda_extranjera'
                    ],
                    'otros_cargos' => [
                        'base_de_la_prima_otros_cargos' => 'base_de_la_prima_otros_cargos_moneda_extranjera',
                        'valor_total_prima_otros_cargos' => 'valor_total_prima_otros_cargos_moneda_extranjera'
                    ],
                    'recargo_vat' => [
                        'base_de_la_prima_recargo_vat' => 'base_de_la_prima_recargo_vat_moneda_extranjera',
                        'valor_total_prima_recargo_vat' => 'valor_total_prima_recargo_vat_moneda_extranjera'
                    ],
                    'prima_permanente' => [
                        'base_de_las_prima_permanente' => 'base_de_las_prima_permanente_moneda_extranjera',
                        'valor_total_prima_permanente' => 'valor_total_prima_permanentes_moneda_extranjera'
                    ],
                ];

                foreach ($ajusteCargos as $cargo => $cambios) {
                    if (array_key_exists($cargo, $item['cargos_extendido']) && !empty($item['cargos_extendido'])) {
                        foreach ($cambios as $local => $extranjera) {
                            if (array_key_exists($local, $item['cargos_extendido'][$cargo]) && array_key_exists($local, $item['cargos_extendido'][$extranjera])) {
                                $item['cargos_extendido'][$cargo][$local] = $item['cargos_extendido'][$cargo][$extranjera];
                                $item['cargos_extendido'][$cargo][$extranjera] = '0.00';
                            }
                        }
                    }
                }

                foreach ($transformarColumnasItems as $key=>$config) {
                    $item['informacion_adicional'][$key] = $this->transform($config, $item['informacion_adicional'][$key] ?? '', $item);
                }
            }

            return $item;
        }, $items);

        //FIN DE LAS TRANFORMACIONES DE DATOS DE CABECERA
        return $items;
    }

    /**
     * Ejecuta las funciones de transformacion de Cargos.
     *
     * @param array $cabecera campos de cabecera del documento.
     * @param array $items campos de items del documento.
     * return array $items.
     * @return array
     */
    private function transformDatosCargosItems($cabecera, $items) {
        // TRANSFORMACIONES DE DATOS SEGUN LAS VALIDACIONES QUE DE DEBEN APLICAR
        /**
         * Validar Campos de Items
         */
        $transformarColumnasItems  = [
            'ddo_cargo_base' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldInformacionAdicionalItem('ddo_cargo_base_moneda_extranjera', $cabecera),
            ],
            'ddo_cargo_valor' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldInformacionAdicionalItem('ddo_cargo_valor_moneda_extranjera', $cabecera),
            ],
            'ddo_cargo_base_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera),
            ],
            'ddo_cargo_valor_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera),
            ],
            'ddo_cargo_porcentaje' => [
                'type' => 'callback',
                'do' => $this->getClosureToChangeAnotherFieldInformacionAdicionalItem('ddo_cargo_porcentaje_moneda_extranjera', $cabecera),
            ],
            'ddo_cargo_porcentaje_moneda_extranjera' => [
                'type' => 'callback',
                'do' => $this->getClosureToValidateFieldToZero($cabecera),
            ],
        ];

        $items = array_map(function ($item) use ($transformarColumnasItems) {
            foreach ($transformarColumnasItems as $key=>$config) {
                foreach ($item['cargos_extendido'] as $index=>$cargo) {
                    if (!empty($cargo)) {
                        $item['cargos_extendido'][$index][$key] = $this->transform($config, $item['cargos_extendido'][$index][$key] ?? '', $item['cargos_extendido'][$index], $index);
                    }
                }
            }

            return $item;
        }, $items);

        $newItems = [];
        foreach ($items as $item) {
            $filtrados = [];
            if (array_key_exists('cargos_extendido', $item)) {
                foreach ($item['cargos_extendido'] as $key => $cargo) {
                    if ((isset($cargo['ddo_cargo_base']) && !empty($cargo['ddo_cargo_base']) && ($cargo['ddo_cargo_base'] !== '0,00' || $cargo['ddo_cargo_base'] !== '0.00')) ||
                        (isset($cargo['ddo_cargo_base_moneda_extranjera']) && !empty($cargo['ddo_cargo_base_moneda_extranjera'] && ($cargo['ddo_cargo_base_moneda_extranjera'] !== '0,00' || $cargo['ddo_cargo_base_moneda_extranjera'] !== '0.00'))))
                        $filtrados[$key] = $cargo;
                }
                $item['cargos_extendido'] = $filtrados;
                $newItems[] = $item;
            }
        }

        //FIN DE LAS TRANFORMACIONES DE DATOS DE CABECERA
        return $newItems;
    }

    /**
     * @param $opcionales
     * @return array
     */
    private function validarGruposDad(array $opcionales) {
        $errores = [];

        // cdo_descuentos
        $grupo = [
            'cdo_descuento_codigo',
            'cdo_descuento_descripcion',
            'cdo_descuento_porcentaje',
            'cdo_descuento_base',
            'cdo_descuento_valor',
            'cdo_descuento_base_moneda_extranjera',
            'cdo_descuento_valor_moneda_extranjera',
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Detalles de Descuento', $response);

        return $errores;
    }

}
