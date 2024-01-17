<?php

namespace App\Http\Modulos\DataInputWorker\FNC;

/**
 * Clase que mapea las columnas de un archivo excel
 *
 * Class ColumnsExcel
 * @package App\Http\Modulos\DataInputWorker
 */
class ColumnsExcelFNC
{
    /**
     * Asociacion de nombre entre las columnas del excel y los campos json
     * @var array
     */
    public static $translateColumn = [

        //ITEM
        'tipo_item' => 'ddo_tipo_item',
        'cod_clasificacion_producto' => 'cpr_codigo',
        'cod_producto' => 'ddo_codigo',
        'descripcion_1' => 'ddo_descripcion_uno',
        'descripcion_2' => 'ddo_descripcion_dos',
        'descripcion_3' => 'ddo_descripcion_tres',
        'cantidad' => 'ddo_cantidad',
        'cantidad_paquete' => 'ddo_cantidad_paquete',
        'cod_unidad_medida' => 'und_codigo',
        'valor_unitario' => 'ddo_valor_unitario',
        'total' => 'ddo_total',
        'valor_unitario_moneda_extranjera' => 'ddo_valor_unitario_moneda_extranjera',
        'total_moneda_extranjera' => 'ddo_total_moneda_extranjera',
        'muestra_comercial' => 'ddo_indicador_muestra',
        'cod_precio_referencia' => 'pre_codigo',
        'valor_muestra' => 'ddo_valor_muestra',
        'valor_muestra_moneda_extranjera' => 'ddo_valor_muestra_moneda_extranjera',
        'nit_mandatario' => 'ddo_nit_mandatario',
        'cufe_factura' => 'cufe',
        'cod_concepto_correccion' => 'cco_codigo',
        'observacion_correccion' => 'cdo_observacion_correccion',

        //CABECERA
        'cod_forma_de_pago' => 'fpa_codigo',
        'cod_medio_de_pago' => 'mpa_codigo',
        'mon_moneda_extranjera' => 'mon_moneda_extranjera',
        'fecha_vencimiento_pago' => 'men_fecha_vencimiento',
        'adq_identificacion_autorizado' => 'adq_identificacion_autorizado',
        'cdo_representacion_grafica_documento' => 'cdo_representacion_grafica_documento',
        'cdo_redondeo' => 'cdo_redondeo',
        'cdo_redondeo_moneda_extranjera' => 'cdo_redondeo_moneda_extranjera',
        'cdo_envio_dian_moneda_extranjera' => 'cdo_envio_dian_moneda_extranjera',
        'cdo_conceptos_correccion' => 'cod_concepto_correccion',

        //NC
        'prefijo_factura' => 'prefijo',
        'consecutivo_factura' => 'consecutivo',
        'fecha_factura' => 'fecha_emision',
        'cdo_hora' => 'cdo_hora',
        'fecha_emision' => 'fecha_emision',

        // En el json esto es un array
        'orden_referencia' => 'referencia',
        'fecha_emision_orden_referencia' => 'fecha_emision_referencia',
        'terminos_entrega_direccion_terminos' => 'cen_codigo',


        //CARGOS
        'ddo_cargo_descripcion' => 'razon',
        'ddo_cargo_porcentaje' => 'porcentaje',
        'ddo_cargo_valor' => 'valor',
        'valor_moneda_extranjera' => 'valor',
        'ddo_cargo_base' => 'base',
        'ddo_cargo_base_moneda_extranjera' => 'base',
        'ddo_cargo_valor_moneda_extranjera' => 'valor',
        'base_impuesto_consumo_moneda_extranjera' => 'base_impuesto_consumo_moneda_extranjera',
        'ddo_cargo_razon' => 'razon',
        'notas' => 'ddo_notas',
    ];


    //CABECERA OBLIGATORIAS FC
    /**
     * Columnas obligatorias en los excel de facturas para cabecera
     *
     * @var array
     */
    public static $columnasObligatoriasCabeceraFC = [
        'cod_tipo_documento',
        'cod_tipo_operacion',
        'nit_ofe',
        'nit_adquiriente',
        'resolucion_facturacion',
        'prefijo',
        'consecutivo',
        'fecha_factura',
        'hora_factura',
        'fecha_vencimiento',
        'observacion',
        'cod_forma_de_pago',
        'cod_medio_de_pago',
        'cod_moneda',
        'trm',
        'fecha_trm',
        'representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo',
        'representacion_grafica_documento',
        'diferencia_redondeo_moneda_extranjera',
        'diferencia_redondeo_moneda_nacional',
        //EXCLUIDAS, DOESN'T REQUIRED
        'mon_codigo_extranjera',
        'fecha_vencimiento_pago',
        'adq_identificacion_autorizado',
        'cdo_envio_dian_moneda_extranjera',
    ];

    public static $excluirValidacionCabeceraFC = [
        'mon_codigo_extranjera',
        'fecha_vencimiento_pago',
        'adq_identificacion_autorizado',
        'cdo_envio_dian_moneda_extranjera',
    ];

    public static $columnasCabeceraMapa = [
        'cod_tipo_documento' => 'tde_codigo',
        'cod_tipo_operacion'  => 'top_codigo',
        'nit_ofe' => 'ofe_identificacion',
        'nit_adquiriente' => 'adq_identificacion',
        'resolucion_facturacion' => 'rfa_resolucion',
        'prefijo' => 'rfa_prefijo',
        'consecutivo' => 'cdo_consecutivo',
        'fecha_factura' => 'fecha_factura',
        'fecha_vencimiento' => 'cdo_vencimiento',
        'observacion' => 'cdo_observacion' ,
        'cod_forma_de_pago' => 'fpa_codigo',
        'cod_medio_de_pago' => 'mpa_codigo',
        'cod_moneda' => 'mon_codigo',
        'mon_codigo_extranjera' => 'mon_codigo_extranjera',
        'trm' => 'cdo_trm',
        'fecha_trm' => 'cdo_trm_fecha',
        'representacion_grafica_documento' => 'cdo_representacion_grafica_documento' ,
        'representacion_grafica_acuse_de_recibo' => 'cdo_representacion_grafica_acuse',
        'fecha_vencimiento_pago' => 'men_fecha_vencimiento',
        'adq_identificacion_autorizado' => 'adq_identificacion_autorizado',
        'diferencia_redondeo_moneda_extranjera' => 'cdo_redondeo_moneda_extranjera',
        'diferencia_redondeo_moneda_nacional' => 'cdo_redondeo',
        'cdo_envio_dian_moneda_extranjera' => 'cdo_envio_dian_moneda_extranjera',
        'cod_correccion' => 'cod_concepto_correccion',
        'fecha' => 'cdo_fecha',
        'hora' => 'cdo_hora',
        'prefijo_factura' => 'prefijo_factura',
        'consecutivo_factura' => 'consecutivo_factura',
        'hora_factura'  => 'hora_factura',
        'cufe' => 'cufe_factura',
        'motivo_pedido' => 'observacion_correccion',
    ];

    public static $columnasCabeceraMapaFC = [
        'cod_tipo_documento' => 'tde_codigo',
        'cod_tipo_operacion'  => 'top_codigo',
        'nit_ofe' => 'ofe_identificacion',
        'nit_adquiriente' => 'adq_identificacion',
        'resolucion_facturacion' => 'rfa_resolucion',
        'prefijo' => 'rfa_prefijo',
        'consecutivo' => 'cdo_consecutivo',
        'fecha_vencimiento' => 'cdo_vencimiento',
        'observacion' => 'cdo_observacion' ,
        'cod_forma_de_pago' => 'fpa_codigo',
        'cod_medio_de_pago' => 'mpa_codigo',
        'cod_moneda' => 'mon_codigo',
        'mon_codigo_extranjera' => 'mon_codigo_extranjera',
        'trm' => 'cdo_trm',
        'fecha_trm' => 'cdo_trm_fecha',
        'representacion_grafica_documento' => 'cdo_representacion_grafica_documento' ,
        'representacion_grafica_acuse_de_recibo' => 'cdo_representacion_grafica_acuse',
        'fecha_vencimiento_pago' => 'men_fecha_vencimiento',
        'adq_identificacion_autorizado' => 'adq_identificacion_autorizado',
        'diferencia_redondeo_moneda_extranjera' => 'cdo_redondeo_moneda_extranjera',
        'diferencia_redondeo_moneda_nacional' => 'cdo_redondeo',
        'cdo_envio_dian_moneda_extranjera' => 'cdo_envio_dian_moneda_extranjera',
        'cod_correccion' => 'cod_concepto_correccion',
        'fecha_factura' => 'cdo_fecha',
        'hora_factura' => 'cdo_hora',
        'prefijo_factura' => 'prefijo_factura',
        'consecutivo_factura' => 'consecutivo_factura',
        'cufe' => 'cufe_factura',
        'motivo_pedido' => 'observacion_correccion',
    ];
    //FIN DE CABECERA OBLIGATORIAS FC


    //CABECERA OBLIGATORIAS DE NC
    /**
     * Columnas obligatorias en los excel de notas credito/debito para cabecera
     *
     * @var array
     */
    public static $columnasObligatoriasCabeceraNCND = [
        'cod_tipo_documento',
        'cod_tipo_operacion',
        'cod_correccion',
        'nit_ofe',
        'nit_adquiriente',
        'prefijo',
        'consecutivo',
        'fecha',
        'prefijo_factura',
        'consecutivo_factura',
        'fecha_factura',
        'hora_factura',
        'cufe',
        'hora',
        'motivo_pedido',
        'fecha_vencimiento',
        'observacion',
        'cod_forma_de_pago',
        'cod_medio_de_pago',
        'cod_moneda',
        'trm',
        'fecha_trm',
        'representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo',
        'representacion_grafica_documento',
        'diferencia_redondeo_moneda_extranjera',
        'diferencia_redondeo_moneda_nacional',
        //EXCLUIDAS, DOESN'T REQUIRED
        'mon_codigo_extranjera',
        'fecha_vencimiento_pago',
        'adq_identificacion_autorizado',
        'cdo_envio_dian_moneda_extranjera',
    ];

    public static $excluirValidacionCabeceraNCND = [
        'mon_codigo_extranjera',
        'fecha_vencimiento_pago',
        'adq_identificacion_autorizado',
        'cdo_envio_dian_moneda_extranjera',
    ];

    //FIN CABECERA OBLIGATORIAS DE NC


    //Colunmas Optativas de Cabecera
    public static $translateColumnsOptativas = [
        'fecha_emision_referencia' => 'fecha_emision_orden_referencia',
        'terminos_entrega_direccion_terminos' => 'terminos_entrega_direccion_terminos',
    ];

    public static $MapColumnasOptativasCabecera = [
        'fecha_orden_de_compra' => [
            'label' => 'fecha_emision_orden_referencia',
            'type' => 'date',
            'format' => 'Y-m-d'
        ],
        'numero_orden_de_compra' => 'orden_referencia',
        'cod_condiciones_entrega' => 'terminos_entrega_direccion_terminos',
        'cdo_porcentaje_de_prima_permanente' => 'porcentaje',
    ];

    public static $columnasOptativasCabecera = [
        'fecha_orden_de_compra',
        'numero_orden_de_compra',
        'cod_condiciones_entrega',
    ];

    public static $excluirValidacionOptativaNCND = [
        'fecha_orden_de_compra',
        'numero_orden_de_compra',
        'cod_condiciones_entrega',
    ];
    //FIN Colunmas Optativas de Cabecera

    public static $translateRetencionesCdo = [
        'retencion_sugerida_reteiva_descripcion' => 'razon',
        'retencion_sugerida_reteiva_porcentaje' => 'porcentaje',
        // valor_moneda_nacional
        'retencion_sugerida_reteiva_base' => 'base',
        'retencion_sugerida_reteiva_valor' => 'valor',
        //valor_moneda_extranjera
        'retencion_sugerida_reteiva_base_moneda_extranjera' => 'base',
        'retencion_sugerida_reteiva_valor_moneda_extranjera' => 'valor',

        'retencion_sugerida_retefuente_descripcion' => 'razon',
        'retencion_sugerida_retefuente_porcentaje' => 'porcentaje',
        // valor_moneda_nacional
        'retencion_sugerida_retefuente_base' => 'base',
        'retencion_sugerida_retefuente_valor' => 'valor',
        // valor_moneda_extranjera
        'retencion_sugerida_retefuente_base_moneda_extranjera' => 'base',
        'retencion_sugerida_retefuente_valor_moneda_extranjera' => 'valor',

        'retencion_sugerida_reteica_descripcion' => 'razon',
        'retencion_sugerida_reteica_porcentaje' => 'porcentaje',
        // valor_moneda_nacional
        'retencion_sugerida_reteica_base' => 'base',
        'retencion_sugerida_reteica_valor' => 'valor',
        // valor_moneda_extranjera
        'retencion_sugerida_reteica_base_moneda_extranjera' => 'base',
        'retencion_sugerida_reteica_valor_moneda_extranjera' => 'valor'
    ];

    public static $iva = [
        'base_iva' => 'iid_base',
        'porcentaje_iva' => 'iid_porcentaje',
        'valor_iva' => 'iid_valor',
        'base_iva_moneda_extranjera' => 'iid_base_moneda_extranjera',
        'valor_iva_moneda_extranjera' => 'iid_valor_moneda_extranjera',
        'motivo_exencion_iva' => 'iid_motivo_exencion',
    ];

    public static $ica = [
        'base_ica' => 'iid_base',
        'porcentaje_ica' => 'iid_porcentaje',
        'valor_ica' => 'iid_valor',
        'base_ica_moneda_extranjera' => 'iid_base_moneda_extranjera',
        'valor_ica_moneda_extranjera' => 'iid_valor_moneda_extranjera',
        'motivo_exencion_ica' => 'icd_motivo_exencion',
    ];

    public static $impuestoConsumo = [
        'base_impuesto_consumo' => 'iid_base',
        'porcentaje_impuesto_consumo' => 'iid_porcentaje',
        'valor_impuesto_consumo' => 'iid_valor',
        'base_impuesto_consumo_moneda_extranjera' => 'iid_base_moneda_extranjera',
        'valor_impuesto_consumo_moneda_extranjera' => 'iid_valor_moneda_extranjera',
        'motivo_exencion_impuesto_consumo' => 'ivd_motivo_exencion'
    ];


    public static $translateImpuestos = [
        //impuesto_porcentaje
        'porcentaje_%s' => 'iid_porcentaje',
        'base_%s' => 'iid_base',
        'base_%s_moneda_extranjera' => 'iid_base_moneda_extranjera',

        'valor_%s' => '',
        'valor_%s_moneda_extranjera' => '',

        //impuesto_unidad
        'impuesto_unidad_%s_unidad' => 'und_codigo',
        'impuesto_unidad_%s_base' => 'iid_base_unidad_medida',
        'impuesto_unidad_%s_valor_unitario' => 'iid_valor_unitario',
        'impuesto_unidad_%s_base_moneda_extranjera' => 'iid_base_unidad_medida_moneda_extranjera',
        'impuesto_unidad_%s_valor_unitario_moneda_extranjera' => 'iid_valor_unitario_moneda_extranjera'
    ];

    public static $translateRetencionesDDO = [
        //impuesto_porcentaje
        'porcentaje_%s' => 'iid_porcentaje',
        'base_%s' => 'iid_base',
        'base_%s_moneda_extranjera' => 'iid_base_moneda_extranjera',

        'valor_%s' => '',
        'valor_%s_moneda_extranjera' => '',

        //impuesto_unidad
        'impuesto_unidad_%s_unidad' => 'und_codigo',
        'impuesto_unidad_%s_base' => 'iid_base_unidad_medida',
        'impuesto_unidad_%s_valor_unitario' => 'iid_valor_unitario',
        'impuesto_unidad_%s_base_moneda_extranjera' => 'iid_base_unidad_medida_moneda_extranjera',
        'impuesto_unidad_%s_valor_unitario_moneda_extranjera' => 'iid_valor_unitario_moneda_extranjera'
    ];



    /**
     * Columnas optativas que se incluyen en el bloque de cabecera del excel.
     *
     * @var array
     */
    public static $informacionAdicionalCabecera = [
        'logo_cabecera',
        'logo_marca',
        'sociedad',
        'org_vtas',
        'clase_factura',
        'nombre_1',
        'nombre_2',
        'nombre_3',
        'nombre_4',
        'resolucion_dian',
        'texto_impresion_fax',
        'regimen_formularios_factura1',
        'regimen_formularios_factura2',
        'direccion_telefono_fax_ofe',
        'ciudad_ofe',
        'cliente_direccion_1',
        'cliente_direccion_2',
        'cliente_direccion_3',
        'cliente_direccion_4',
        'cliente_direccion_5',
        'pais',
        'forma_de_pago',
        'importe_total',
        'peso_neto',
        'unidad_neta',
        'cantidad_total',
        'factor_de_conversion',
        'valor_en_letras',
        'datos_para_pago',
        'instrucciones_de_embarque',
        'entrega',
        'incoterm_2',
        'prioridad',
        'prima_permanentes',
    ];

    // ITEM OBLIGADOS
    /**
     * Columnas obligatorias en los excel para los items
     * @var array
     */
    public static $columnasObligatoriasItems = [
        'cod_clasificacion_producto',
        'tipo_servicio',
        'descripcion',
        'cantidad_real_sobre_la_cual_aplica_el_precio',
        'cod_producto',
        'cod_precio_referencia',
        'valor_unitario',
        'total_sin_primas',
        'valor_unitario_moneda_extranjera',
        'total_sin_primas_moneda_extranjera',
        'valor_unitario',
        'cod_unidad_medida',

        //TRIBUTOS
        'base_iva',
        'porcentaje_iva',
        'valor_iva',
        'base_iva_moneda_extranjera',
        'valor_iva_moneda_extranjera',
        'motivo_exencion_iva',
        'base_impuesto_consumo',
        'porcentaje_impuesto_consumo',
        'valor_impuesto_consumo',
        'motivo_exencion_impuesto_consumo',
//        'base_impuesto_consumo_moneda_extranjera',
//        'valor_impuesto_consumo_moneda_extranjera',
        'base_ica',
        'porcentaje_ica',
        'valor_ica',
        'motivo_exencion_ica',
//        'base_ica_moneda_extranjera',
//        'valor_ica_moneda_extranjera',
    ];

    public static $MapColumnasItemObligatorios = [
        'cod_clasificacion_producto' => 'cod_clasificacion_producto',
        'tipo_servicio' => 'tipo_item',
        'descripcion' => 'descripcion_1',
        'cantidad_real_sobre_la_cual_aplica_el_precio' => 'cantidad',
        'cod_producto' => 'cod_producto',
        'cod_precio_referencia' => 'cod_precio_referencia',
        'valor_unitario' => 'valor_unitario',
        'total_sin_primas' => 'total',
        'valor_unitario_moneda_extranjera' => 'valor_unitario_moneda_extranjera',
        'total_sin_primas_moneda_extranjera' => 'total_moneda_extranjera',
        'cod_unidad_medida' => 'cod_unidad_medida',


        //TRIBUTOS
        'base_iva' => 'base_iva',
        'porcentaje_iva' => 'porcentaje_iva',
        'valor_iva' => 'valor_iva',
        'base_iva_moneda_extranjera' => 'base_iva_moneda_extranjera',
        'valor_iva_moneda_extranjera' => 'valor_iva_moneda_extranjera',
        'motivo_exencion_iva' => 'iid_motivo_exencion',
        'base_impuesto_consumo' => 'base_impuesto_consumo',
        'porcentaje_impuesto_consumo' => 'porcentaje_impuesto_consumo',
        'valor_impuesto_consumo' => 'valor_impuesto_consumo',
        'motivo_exencion_impuesto_consumo' => 'ivd_motivo_exencion',
//        'base_impuesto_consumo_moneda_extranjera',
//        'valor_impuesto_consumo_moneda_extranjera',
        'base_ica' => 'base_ica',
        'porcentaje_ica' => 'porcentaje_ica',
        'valor_ica' => 'valor_ica',
        'motivo_exencion_ica' => 'icd_motivo_exencion',
//        'base_ica_moneda_extranjera',
//        'valor_ica_moneda_extranjera',

    ];
    //FIN DE ITEMS OBLIGATORIOS


    public static $columnasAdicionalItems = [
        'codigo_servicio',
        'cantidad',
        'total',
        'total_moneda_extranjera',
        'unidad_item',
        'cantidad_base_item',
        'unidad_base_item',
        'moneda_item',
        'descripcion_2',
        // Prima Tecnical Support
        'prima_tecnical_support',
        'valor_prima_tecnical_support',
        'moneda_prima_tecnical_support',
        'unidad_base_prima_tecnical_support',
        'cantidad_base_prima_tecnical_support',
        'base_de_la_prima_tecnical_support',
        'porcentaje_de_la_prima_tecnical_support',
        'valor_total_prima_tecnical_support',
        'base_de_la_prima_tecnical_support_en_moneda_extranjera',
        'porcentaje_de_la_prima_tecnical_support_moneda_extranjera',
        'valor_total_prima_tecnical_support_moneda_extranjera',
        // Prima Social
        'prima_social',
        'valor_prima_social',
        'moneda_prima_social',
        'unidad_base_prima_social',
        'cantidad_base_prima_social',
        'base_de_la_prima_social',
        'porcentaje_de_la_prima_social',
        'valor_total_prima_social',
        'base_de_la_prima_social_en_moneda_extranjera',
        'porcentaje_de_la_prima_social_en_moneda_extranjera',
        'valor_total_prima_social_moneda_extranjera',
        // Prima Otros Cargos
        'prima_otros_cargos',
        'valor_prima_otros_cargos',
        'moneda_prima_otros_cargos',
        'unidad_base_prima_otros_cargos',
        'cantidad_base_prima_otros_cargos',
        'base_de_la_prima_otros_cargos',
        'porcentaje_de_la_prima_otros_cargos',
        'valor_total_prima_otros_cargos',
        'base_de_la_prima_otros_cargos_moneda_extranjera',
        'porcentaje_de_la_prima_otros_cargos_moneda_extranjera',
        'valor_total_prima_otros_cargos_moneda_extranjera',
        // Prima Recargo VAT
        'prima_recargo_vat',
        'valor_prima_recargo_vat',
        'moneda_prima_recargo_vat',
        'unidad_base_prima_recargo_vat',
        'cantidad_base_prima_recargo_vat',
        'base_de_la_prima_recargo_vat',
        'porcentaje_de_la_prima_recargo_vat',
        'valor_total_prima_recargo_vat',
        'base_de_la_prima_recargo_vat_moneda_extranjera',
        'porcentaje_de_la_prima_recargo_vat_moneda_extranjera',
        'valor_total_prima_recargo_vat_moneda_extranjera',
        // Prima Permanentes
        'prima_permanentes',
        'valor_prima_permanentes',
        'moneda_prima_permanentes',
        'unidad_base_prima_permanentes',
        'cantidad_base_prima_permanentes',
        'base_de_prima_permanente',
        'porcentaje_de_prima_permanente',
        'valor_total_prima_permanente',
        'base_de_prima_permanente_moneda_extranjera',
        'porcentaje_de_prima_permanente_moneda_extranjera',
        'valor_total_prima_permanente_moneda_extranjera'
    ];

    //CARGOS FC
    public static $columnasCargosItem = [
        'tecnical_support' => [
            'valor_total_prima_tecnical_support',
            'base_de_la_prima_tecnical_support',
            'porcentaje_de_la_prima_tecnical_support',
            'base_de_la_prima_tecnical_support_en_moneda_extranjera',
            'valor_total_prima_tecnical_support_moneda_extranjera',
            'porcentaje_de_la_prima_tecnical_support_moneda_extranjera'
        ],
        'prima_social' => [
            'base_de_la_prima_social',
            'porcentaje_de_la_prima_social',
            'valor_total_prima_social',
            'base_de_la_prima_social_en_moneda_extranjera',
            'valor_total_prima_social_moneda_extranjera',
            'porcentaje_de_la_prima_social_en_moneda_extranjera'
        ],
        'otros_cargos' => [
            'base_de_la_prima_otros_cargos',
            'porcentaje_de_la_prima_otros_cargos',
            'valor_total_prima_otros_cargos',
            'base_de_la_prima_otros_cargos_moneda_extranjera',
            'valor_total_prima_otros_cargos_moneda_extranjera',
            'porcentaje_de_la_prima_otros_cargos_moneda_extranjera'
        ],
        'recargo_vat' => [
            'base_de_la_prima_recargo_vat',
            'porcentaje_de_la_prima_recargo_vat',
            'valor_total_prima_recargo_vat',
            'base_de_la_prima_recargo_vat_moneda_extranjera',
            'valor_total_prima_recargo_vat_moneda_extranjera',
            'porcentaje_de_la_prima_recargo_vat_moneda_extranjera'
        ],
        'prima_permanente' => [
//            'moneda_prima_permanentes',
//            'unidad_base_prima_permanentes',
//            'cantidad_base_prima_permanentes',
            'base_de_prima_permanente',
            'porcentaje_de_prima_permanente',
            'valor_total_prima_permanente',
            'base_de_prima_permanente_moneda_extranjera',
            'valor_total_prima_permanente_moneda_extranjera',
            'porcentaje_de_prima_permanente_moneda_extranjera'
        ],
    ];

    public static $MapColumnasCargosItem = [
        'tecnical_support' => [
            'valor_total_prima_tecnical_support' => 'ddo_cargo_valor',
            'base_de_la_prima_tecnical_support' => 'ddo_cargo_base',
            'porcentaje_de_la_prima_tecnical_support' => 'ddo_cargo_porcentaje',
            'base_de_la_prima_tecnical_support_en_moneda_extranjera' => 'ddo_cargo_base_moneda_extranjera',
            'valor_total_prima_tecnical_support_moneda_extranjera' => 'ddo_cargo_valor_moneda_extranjera',
            'porcentaje_de_la_prima_tecnical_support_moneda_extranjera' => 'ddo_cargo_porcentaje_moneda_extranjera',
        ],
        'prima_social' => [
            'valor_total_prima_social' => 'ddo_cargo_valor',
            'base_de_la_prima_social' => 'ddo_cargo_base',
            'porcentaje_de_la_prima_social' => 'ddo_cargo_porcentaje',
            'base_de_la_prima_social_en_moneda_extranjera' => 'ddo_cargo_base_moneda_extranjera',
            'valor_total_prima_social_moneda_extranjera' => 'ddo_cargo_valor_moneda_extranjera',
            'porcentaje_de_la_prima_social_en_moneda_extranjera' => 'ddo_cargo_porcentaje_moneda_extranjera'
        ],
        'otros_cargos' => [
            'base_de_la_prima_otros_cargos' => 'ddo_cargo_base',
            'porcentaje_de_la_prima_otros_cargos' => 'ddo_cargo_porcentaje',
            'valor_total_prima_otros_cargos' => 'ddo_cargo_valor',
            'base_de_la_prima_otros_cargos_moneda_extranjera' => 'ddo_cargo_base_moneda_extranjera',
            'valor_total_prima_otros_cargos_moneda_extranjera' => 'ddo_cargo_valor_moneda_extranjera',
            'porcentaje_de_la_prima_otros_cargos_moneda_extranjera' => 'ddo_cargo_porcentaje_moneda_extranjera'
        ],
        'recargo_vat' => [
            'base_de_la_prima_recargo_vat' => 'ddo_cargo_base',
            'porcentaje_de_la_prima_recargo_vat' => 'ddo_cargo_porcentaje',
            'valor_total_prima_recargo_vat' => 'ddo_cargo_valor',
            'base_de_la_prima_recargo_vat_moneda_extranjera' => 'ddo_cargo_base_moneda_extranjera',
            'valor_total_prima_recargo_vat_moneda_extranjera' => 'ddo_cargo_valor_moneda_extranjera',
            'porcentaje_de_la_prima_recargo_vat_moneda_extranjera' => 'ddo_cargo_porcentaje_moneda_extranjera'
        ],
        'prima_permanente' => [
//            'moneda_prima_permanentes',
//            'unidad_base_prima_permanentes' => '',
//            'cantidad_base_prima_permanentes',
            'base_de_prima_permanente' => 'ddo_cargo_base',
            'porcentaje_de_prima_permanente' => 'ddo_cargo_porcentaje',
            'valor_total_prima_permanente' => 'ddo_cargo_valor',
            'base_de_prima_permanente_moneda_extranjera' => 'ddo_cargo_base_moneda_extranjera',
            'valor_total_prima_permanente_moneda_extranjera' => 'ddo_cargo_valor_moneda_extranjera',
            'porcentaje_de_prima_permanente_moneda_extranjera' => 'ddo_cargo_porcentaje_moneda_extranjera'
        ],

    ];
    //FIN DE CARGOS FC

    /**
     * Campos que cuyo valor date debe ser reformateado
     * @var array
     */
    public static $fixFecha =[
        'fecha',
        'fecha_vencimiento',
        'fecha_factura',
        'fecha_vencimiento_pago',
        'fecha_trm',
        'fecha_inicio_periodo',
        'fecha_fin_periodo',
        'fecha_emision_orden_referencia',
        'fecha_emision_despacho_referencia',
        'fecha_emision_recpcion_referecia',
        'anticipo_fecha_recibio',
    ];

}
