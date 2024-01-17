<?php

namespace App\Http\Modulos\DataInputWorker\Excel;

/**
 * Clase que mapea las columnas de un archivo excel
 *
 * Class ColumnsExcel
 * @package App\Http\Modulos\DataInputWorker
 */
class ColumnsExcel
{
    /**
     * Asociacion de nombre entre las columnas del excel y los campos json
     * @var array
     */
    public static $translateColumn = [
        'tipo_documento'         => 'tde_codigo',
        'tipo_operacion'         => 'top_codigo',
        'nit_ofe'                => 'ofe_identificacion',
        'nit_adquirente'         => 'adq_identificacion',
        'id_personalizado'       => 'adq_id_personalizado',
        'nit_autorizado'         => 'adq_identificacion_autorizado',
        'resolucion_facturacion' => 'rfa_resolucion',
        'prefijo'                => 'rfa_prefijo',
        'consecutivo'            => 'cdo_consecutivo',
        'fecha'                  => 'cdo_fecha',
        'hora'                   => 'cdo_hora',
        'fecha_vencimiento'      => 'cdo_vencimiento',

        // cdo_documento_referencia Array de 1
        'prefijo_factura'     => 'prefijo',
        'consecutivo_factura' => 'consecutivo',
        'cufe_factura'        => 'cufe',
        'fecha_factura'       => 'fecha_emision',

        // cdo_conceptos_correccion Array de 1
        'cod_concepto_correccion' => 'cco_codigo',
        'observacion_correccion'  => 'cdo_observacion_correccion',

        'observacion'                            => 'cdo_observacion',
        'cod_forma_de_pago'                      => 'fpa_codigo',
        'cod_medio_de_pago'                      => 'mpa_codigo',
        'fecha_vencimiento_pago'                 => 'men_fecha_vencimiento',
        'identificador_pago'                     => 'men_identificador_pago',
        'cod_moneda'                             => 'cod_moneda',
        'cod_moneda_extranjera'                  => 'cod_moneda_extranjera',
        'trm'                                    => 'cdo_trm',
        'fecha_trm'                              => 'cdo_trm_fecha',
        'enviar_a_la_dian_en_moneda_extranjera'  => 'cdo_envio_dian_moneda_extranjera',
        'representacion_grafica_documento'       => 'cdo_representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo' => 'cdo_representacion_grafica_acuse',
        //'documento_adicional'                  => 'rod_codigo~prefijo~consecutivo~cufe~fecha_emision|',

        'fecha_inicio_periodo' => 'dad_periodo_fecha_inicio',
        'hora_inicio_periodo'  => 'dad_periodo_hora_inicio',
        'fecha_fin_periodo'    => 'dad_periodo_fecha_fin',
        'hora_fin_periodo'     => 'dad_periodo_hora_fin',

        // En el json esto es un array
        'orden_referencia'               => 'referencia',
        'fecha_emision_orden_referencia' => 'fecha_emision_referencia',

        // En el json esto es un array
        'despacho_referencia'               => 'referencia',
        'fecha_emision_despacho_referencia' => 'fecha_emision_referencia',

        // En el json esto es un array
        'recepcion_referencia'             => 'referencia',
        'fecha_emision_recpcion_referecia' => 'fecha_emision_referencia',

        'entrega_bienes_fecha'                => 'dad_entrega_bienes_fecha',
        'entrega_bienes_hora'                 => 'dad_entrega_bienes_hora',
        'entrega_bienes_cod_pais'             => 'pai_codigo_entrega_bienes',
        'entrega_bienes_cod_departamento'     => 'dep_codigo_entrega_bienes',
        'entrega_bienes_cod_ciudad_municipio' => 'mun_codigo_entrega_bienes',
        'entrega_bienes_cod_postal'           => 'dad_entrega_bienes_codigo_postal',
        'entrega_bienes_direccion'            => 'dad_entrega_bienes_direccion',

        'entrega_bienes_despacho_identificacion_transportador' => 'dad_entrega_bienes_despacho_identificacion_transportador',
        'entrega_bienes_despacho_identificacion_transporte'    => 'dad_entrega_bienes_despacho_identificacion_transporte',
        'entrega_bienes_despacho_tipo_transporte'              => 'dad_entrega_bienes_despacho_tipo_transporte',
        'entrega_bienes_despacho_fecha_solicitada'             => 'dad_entrega_bienes_despacho_fecha_solicitada',
        'entrega_bienes_despacho_hora_solicitada'              => 'dad_entrega_bienes_despacho_hora_solicitada',
        'entrega_bienes_despacho_fecha_estimada'               => 'dad_entrega_bienes_despacho_fecha_estimada',
        'entrega_bienes_despacho_hora_estimada'                => 'dad_entrega_bienes_despacho_hora_estimada',
        'entrega_bienes_despacho_fecha_real'                   => 'dad_entrega_bienes_despacho_fecha_real',
        'entrega_bienes_despacho_hora_real'                    => 'dad_entrega_bienes_despacho_hora_real',
        'entrega_bienes_despacho_cod_pais'                     => 'pai_codigo_entrega_bienes_despacho',
        'entrega_bienes_despacho_cod_departamento'             => 'dep_codigo_entrega_bienes_despacho',
        'entrega_bienes_despacho_cod_ciudad_municipio'         => 'mun_codigo_entrega_bienes_despacho',
        'entrega_bienes_despacho_cod_postal'                   => 'dad_entrega_bienes_despacho_codigo_postal',
        'entrega_bienes_despacho_direccion'                    => 'dad_entrega_bienes_despacho_direccion',

        'terminos_entrega_condiciones_pago'                => 'dad_terminos_entrega_condiciones_pago',
        'terminos_entrega_cod_condicion_entrega_incoterms' => 'cen_codigo',
        'terminos_entrega_cod_pais'                        => 'pai_codigo_terminos_entrega',
        'terminos_entrega_cod_departamento'                => 'dep_codigo_terminos_entrega',
        'terminos_entrega_cod_ciudad_municipio'            => 'mun_codigo_terminos_entrega',
        'terminos_entrega_cod_postal_cod_postal'           => 'dad_terminos_entrega_codigo_postal',
        'terminos_entrega_direccion_terminos'              => 'dad_terminos_entrega_direccion',

        'anticipo_identificado_pago'       => 'ant_identificacion',
        'anticipo_valor'                   => 'ant_valor',
        'anticipo_valor_moneda_extranjera' => 'ant_valor_moneda_extranjera',
        'anticipo_fecha_recibio'           => 'ant_fecha_recibido',

        'cdo_cargo_descripcion' => 'razon',
        'cdo_cargo_porcentaje'  => 'porcentaje',
        // valor_moneda_nacional
        'cdo_cargo_base'  => 'base',
        'cdo_cargo_valor' => 'valor',
        // valor_moneda_extranjera
        'cdo_cargo_base_moneda_extranjera'  => 'base',
        'cdo_cargo_valor_moneda_extranjera' => 'valor',

        'cdo_descuento_codigo'      => 'cde_codigo',
        'cdo_descuento_descripcion' => 'razon',
        'cdo_descuento_porcentaje'  => 'porcentaje',
        // valor_moneda_nacional
        'cdo_descuento_base'  => 'base',
        'cdo_descuento_valor' => 'valor',
        // valor_moneda_extranjera
        'cdo_descuento_base_moneda_extranjera'  => 'base',
        'cdo_descuento_valor_moneda_extranjera' => 'valor',

        'tipo_item'                        => 'ddo_tipo_item',
        'cod_clasificacion_producto'       => 'cpr_codigo',
        'cod_producto'                     => 'ddo_codigo',
        'descripcion_1'                    => 'ddo_descripcion_uno',
        'descripcion_2'                    => 'ddo_descripcion_dos',
        'descripcion_3'                    => 'ddo_descripcion_tres',
        'notas'                            => 'ddo_notas',
        'cantidad'                         => 'ddo_cantidad',
        'cantidad_paquete'                 => 'ddo_cantidad_paquete',
        'cod_unidad_medida'                => 'und_codigo',
        'valor_unitario'                   => 'ddo_valor_unitario',
        'total'                            => 'ddo_total',
        'valor_unitario_moneda_extranjera' => 'ddo_valor_unitario_moneda_extranjera',
        'total_moneda_extranjera'          => 'ddo_total_moneda_extranjera',
        'muestra_comercial'                => 'ddo_indicador_muestra',
        'cod_precio_referencia'            => 'pre_codigo',
        'valor_muestra'                    => 'ddo_valor_muestra',
        'valor_muestra_moneda_extranjera'  => 'ddo_valor_muestra_moneda_extranjera',
        // Array de objetos
        'datos_tecnicos'                        => 'ddo_datos_tecnicos',
        'nit_mandatario'                        => 'ddo_nit_mandatario',
        'cod_tipo_documento_mandatario'         => 'ddo_tdo_codigo_mandatario',
        'ddo_identificador'                     => 'ddo_identificador',
        'marca'                                 => 'ddo_marca',
        'modelo'                                => 'ddo_modelo',
        'cod_vendedor'                          => 'ddo_codigo_vendedor',
        'cod_vendedor_subespecificacion'        => 'ddo_codigo_vendedor_subespecificacion',
        'cod_fabricante'                        => 'ddo_codigo_fabricante',
        'cod_fabricante_subespecificacion'      => 'ddo_codigo_fabricante_subespecificacion',
        'nombre_fabricante'                     => 'ddo_nombre_fabricante',
        'nombre_clasificacion_producto'         => 'cpr_codigo',
        'cod_pais_de_origen'                    => 'pai_codigo',
        'ddo_cargo_descripcion'                 => 'razon',
        'ddo_cargo_porcentaje'                  => 'porcentaje',
        'ddo_cargo_base'                        => 'base',
        'ddo_cargo_valor'                       => 'valor',
        'ddo_cargo_base_moneda_extranjera'      => 'base',
        'ddo_cargo_valor_moneda_extranjera'     => 'valor',
        'ddo_descuento_descripcion'             => 'razon',
        'ddo_descuento_porcentaje'              => 'porcentaje',
        'ddo_descuento_base'                    => 'base',
        'ddo_descuento_valor'                   => 'valor',
        'ddo_descuento_base_moneda_extranjera'  => 'base',
        'ddo_descuento_valor_moneda_extranjera' => 'valor',
    ];

    public static $translateRetencionesCdo = [
        'retencion_sugerida_reteiva_descripcion' => 'razon',
        'retencion_sugerida_reteiva_porcentaje'  => 'porcentaje',
        // valor_moneda_nacional
        'retencion_sugerida_reteiva_base'  => 'base',
        'retencion_sugerida_reteiva_valor' => 'valor',
        //valor_moneda_extranjera
        'retencion_sugerida_reteiva_base_moneda_extranjera'  => 'base',
        'retencion_sugerida_reteiva_valor_moneda_extranjera' => 'valor',

        'retencion_sugerida_retefuente_descripcion' => 'razon',
        'retencion_sugerida_retefuente_porcentaje'  => 'porcentaje',
        // valor_moneda_nacional
        'retencion_sugerida_retefuente_base'  => 'base',
        'retencion_sugerida_retefuente_valor' => 'valor',
        // valor_moneda_extranjera
        'retencion_sugerida_retefuente_base_moneda_extranjera'  => 'base',
        'retencion_sugerida_retefuente_valor_moneda_extranjera' => 'valor',

        'retencion_sugerida_reteica_descripcion' => 'razon',
        'retencion_sugerida_reteica_porcentaje'  => 'porcentaje',
        // valor_moneda_nacional
        'retencion_sugerida_reteica_base'  => 'base',
        'retencion_sugerida_reteica_valor' => 'valor',
        // valor_moneda_extranjera
        'retencion_sugerida_reteica_base_moneda_extranjera'  => 'base',
        'retencion_sugerida_reteica_valor_moneda_extranjera' => 'valor'
    ];

    public static $iva = [
        'base_iva'                    => 'iid_base',
        'porcentaje_iva'              => 'iid_porcentaje',
        'valor_iva'                   => 'iid_valor',
        'base_iva_moneda_extranjera'  => 'iid_base_moneda_extranjera',
        'valor_iva_moneda_extranjera' => 'iid_valor_moneda_extranjera',
        'motivo_exencion_iva'         => 'iid_motivo_exencion',
    ];

    public static $ica = [
        'base_ica'                    => 'iid_base',
        'porcentaje_ica'              => 'iid_porcentaje',
        'valor_ica'                   => 'iid_valor',
        'base_ica_moneda_extranjera'  => 'iid_base_moneda_extranjera',
        'valor_ica_moneda_extranjera' => 'iid_valor_moneda_extranjera',
    ];

    public static $impuestoConsumo = [
        'base_impuesto_consumo'                    => 'iid_base',
        'porcentaje_impuesto_consumo'              => 'iid_porcentaje',
        'valor_impuesto_consumo'                   => 'iid_valor',
        'base_impuesto_consumo_moneda_extranjera'  => 'iid_base_moneda_extranjera',
        'valor_impuesto_consumo_moneda_extranjera' => 'iid_valor_moneda_extranjera',
    ];


    public static $translateImpuestos = [
        //impuesto_porcentaje
        'porcentaje_%s'             => 'iid_porcentaje',
        'base_%s'                   => 'iid_base',
        'base_%s_moneda_extranjera' => 'iid_base_moneda_extranjera',

        'valor_%s' => '',
        'valor_%s_moneda_extranjera' => '',

        //impuesto_unidad
        'impuesto_unidad_%s_unidad'                           => 'und_codigo',
        'impuesto_unidad_%s_base'                             => 'iid_base_unidad_medida',
        'impuesto_unidad_%s_valor_unitario'                   => 'iid_valor_unitario',
        'impuesto_unidad_%s_base_moneda_extranjera'           => 'iid_base_unidad_medida_moneda_extranjera',
        'impuesto_unidad_%s_valor_unitario_moneda_extranjera' => 'iid_valor_unitario_moneda_extranjera'
    ];

    public static $translateRetencionesDDO = [
        //impuesto_porcentaje
        'porcentaje_%s'             => 'iid_porcentaje',
        'base_%s'                   => 'iid_base',
        'base_%s_moneda_extranjera' => 'iid_base_moneda_extranjera',

        'valor_%s' => '',
        'valor_%s_moneda_extranjera' => '',

        //impuesto_unidad
        'impuesto_unidad_%s_unidad'                           => 'und_codigo',
        'impuesto_unidad_%s_base'                             => 'iid_base_unidad_medida',
        'impuesto_unidad_%s_valor_unitario'                   => 'iid_valor_unitario',
        'impuesto_unidad_%s_base_moneda_extranjera'           => 'iid_base_unidad_medida_moneda_extranjera',
        'impuesto_unidad_%s_valor_unitario_moneda_extranjera' => 'iid_valor_unitario_moneda_extranjera'
    ];

    /**
     * Columnas obligatorias en los excel de facturas para cabecera.
     *
     * @var array
     */
    public static $columnasObligatoriasCabeceraFC = [
        'tipo_documento',
        'tipo_operacion',
        'nit_ofe',
        'nit_adquirente',
        'nit_autorizado',
        'resolucion_facturacion',
        'prefijo',
        'consecutivo',
        'fecha',
        'hora',
        'fecha_vencimiento',
        'observacion',
        'cod_forma_de_pago',
        'cod_medio_de_pago',
        'fecha_vencimiento_pago',
        'identificador_pago',
        'cod_moneda',
        'cod_moneda_extranjera',
        'trm',
        'fecha_trm',
        'enviar_a_la_dian_en_moneda_extranjera',
        'representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo'
    ];

    /**
     * Columnas obligatorias en los excel de notas crédito/débito para cabecera.
     *
     * @var array
     */
    public static $columnasObligatoriasCabeceraNCND = [
        'tipo_documento',
        'tipo_operacion',
        'nit_ofe',
        'nit_adquirente',
        'nit_autorizado',
        'prefijo',
        'consecutivo',
        'fecha',
        'hora',
        'fecha_vencimiento',
        'prefijo_factura',
        'consecutivo_factura',
        'cufe_factura',
        'fecha_factura',
        'cod_concepto_correccion',
        'observacion_correccion',
        'observacion',
        'cod_forma_de_pago',
        'cod_medio_de_pago',
        'fecha_vencimiento_pago',
        'identificador_pago',
        'cod_moneda',
        'cod_moneda_extranjera',
        'trm',
        'fecha_trm',
        'enviar_a_la_dian_en_moneda_extranjera',
        'representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo',
    ];

    /**
     * Columnas de cabecera para los Documentos Electrónicos.
     *
     * @var array
     */
    public static $columnasCabeceraMapa = [
        'tipo_documento'                         => 'tde_codigo',
        'tipo_operacion'                         => 'top_codigo',
        'nit_ofe'                                => 'ofe_identificacion',
        'nit_adquirente'                         => 'adq_identificacion',
        'id_personalizado'                       => 'adq_id_personalizado',
        'nit_autorizado'                         => 'adq_identificacion_autorizado',
        'resolucion_facturacion'                 => 'rfa_resolucion',
        'prefijo'                                => 'rfa_prefijo',
        'consecutivo'                            => 'cdo_consecutivo',
        'fecha'                                  => 'cdo_fecha',
        'hora'                                   => 'cdo_hora',
        'fecha_vencimiento'                      => 'cdo_vencimiento',
        'prefijo_factura'                        => 'prefijo',
        'consecutivo_factura'                    => 'consecutivo',
        'cufe_factura'                           => 'cufe',
        'fecha_factura'                          => 'fecha_emision',
        'cod_concepto_correccion'                => 'cod_concepto_correccion',
        'observacion_correccion'                 => 'observacion_correccion',
        'observacion'                            => 'cdo_observacion',
        'cod_forma_de_pago'                      => 'fpa_codigo',
        'cod_medio_de_pago'                      => 'mpa_codigo',
        'fecha_vencimiento_pago'                 => 'men_fecha_vencimiento',
        'identificador_pago'                     => 'men_identificador_pago',
        'cod_moneda'                             => 'mon_codigo',
        'cod_moneda_extranjera'                  => 'mon_codigo_extranjera',
        'trm'                                    => 'cdo_trm',
        'fecha_trm'                              => 'cdo_trm_fecha',
        'enviar_a_la_dian_en_moneda_extranjera'  => 'cdo_envio_dian_moneda_extranjera',
        'representacion_grafica_documento'       => 'cdo_representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo' => 'cdo_representacion_grafica_acuse',
        'cdo_redondeo'                           => 'cdo_redondeo',
        'cdo_redondeo_moneda_extranjera'         => 'cdo_redondeo_moneda_extranjera'
    ];

    /**
     * Columnas de cabecera para los Documentos Soporte.
     *
     * @var array
     */
    public static $columnasCabeceraMapaDS = [
        'tipo_documento'                         => 'tde_codigo',
        'tipo_operacion'                         => 'top_codigo',
        'nit_receptor'                           => 'ofe_identificacion',
        'nit_vendedor'                           => 'adq_identificacion',
        'id_personalizado'                       => 'adq_id_personalizado',
        'nit_autorizado'                         => 'adq_identificacion_autorizado',
        'resolucion_facturacion'                 => 'rfa_resolucion',
        'prefijo'                                => 'rfa_prefijo',
        'consecutivo'                            => 'cdo_consecutivo',
        'fecha'                                  => 'cdo_fecha',
        'hora'                                   => 'cdo_hora',
        'fecha_vencimiento'                      => 'cdo_vencimiento',
        'prefijo_factura'                        => 'prefijo',
        'consecutivo_factura'                    => 'consecutivo',
        'cufe_factura'                           => 'cufe',
        'fecha_factura'                          => 'fecha_emision',
        'cod_concepto_correccion'                => 'cod_concepto_correccion',
        'observacion_correccion'                 => 'observacion_correccion',
        'observacion'                            => 'cdo_observacion',
        'cod_forma_de_pago'                      => 'fpa_codigo',
        'cod_medio_de_pago'                      => 'mpa_codigo',
        'fecha_vencimiento_pago'                 => 'men_fecha_vencimiento',
        'identificador_pago'                     => 'men_identificador_pago',
        'cod_moneda'                             => 'mon_codigo',
        'cod_moneda_extranjera'                  => 'mon_codigo_extranjera',
        'trm'                                    => 'cdo_trm',
        'fecha_trm'                              => 'cdo_trm_fecha',
        'enviar_a_la_dian_en_moneda_extranjera'  => 'cdo_envio_dian_moneda_extranjera',
        'representacion_grafica_documento'       => 'cdo_representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo' => 'cdo_representacion_grafica_acuse',
        'cdo_redondeo'                           => 'cdo_redondeo',
        'cdo_redondeo_moneda_extranjera' => 'cdo_redondeo_moneda_extranjera'
    ];

    /**
     * Columnas optativas que se incluyen en el bloque de cabecera del excel.
     *
     * @var array
     */
    public static $columnasOptativasCabecera = [
        'documento_adicional',
        'fecha_inicio_periodo',
        'hora_inicio_periodo',
        'fecha_fin_periodo',
        'hora_fin_periodo',
        'orden_referencia',
        'fecha_emision_orden_referencia',
        'despacho_referencia',
        'fecha_emision_despacho_referencia',
        'recepcion_referencia',
        'fecha_emision_recpcion_referecia',
        'entrega_bienes_fecha_salida',
        'entrega_bienes_hora_salida',
        'entrega_bienes_cod_pais',
        'entrega_bienes_cod_departamento',
        'entrega_bienes_cod_ciudad_municipio',
        'entrega_bienes_cod_postal',
        'entrega_bienes_direccion',
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
        'entrega_bienes_despacho_direccion',
        'terminos_entrega_condiciones_pago',
        'terminos_entrega_cod_condicion_entrega_incoterms',
        'terminos_entrega_cod_pais',
        'terminos_entrega_cod_departamento',
        'terminos_entrega_cod_ciudad_municipio',
        'terminos_entrega_cod_postal_cod_postal',
        'terminos_entrega_direccion_terminos',
        'anticipo_identificado_pago',
        'anticipo_valor',
        'anticipo_valor_moneda_extranjera',
        'anticipo_fecha_recibio',
        'cdo_cargo_descripcion',
        'cdo_cargo_porcentaje',
        'cdo_cargo_base',
        'cdo_cargo_valor',
        'cdo_cargo_base_moneda_extranjera',
        'cdo_cargo_valor_moneda_extranjera',
        'cdo_descuento_codigo',
        'cdo_descuento_descripcion',
        'cdo_descuento_porcentaje',
        'cdo_descuento_base',
        'cdo_descuento_valor',
        'cdo_descuento_base_moneda_extranjera',
        'cdo_descuento_valor_moneda_extranjera',
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
        'retencion_sugerida_reteica_valor_moneda_extranjera',
        'descuento_codigo',
        'redondeo',
        'redondeo_moneda_extranjera'
    ];

    /**
     * Columnas obligatorias en los excel para los items de Documento Electrónico.
     * @var array
     */
    public static $columnasObligatoriasItems = [
        'tipo_item',
        'cod_clasificacion_producto',
        'cod_producto',
        'descripcion_1',
        'descripcion_2',
        'descripcion_3',
        'notas',
        'cantidad',
        'cantidad_paquete',
        'cod_unidad_medida',
        'valor_unitario',
        'total',
        'valor_unitario_moneda_extranjera',
        'total_moneda_extranjera',
        'muestra_comercial',
        'cod_precio_referencia',
        'valor_muestra',
        'valor_muestra_moneda_extranjera',
        'datos_tecnicos',
        'nit_mandatario',
        'cod_tipo_documento_mandatario',
        'base_iva',
        'porcentaje_iva',
        'valor_iva',
        'base_iva_moneda_extranjera',
        'valor_iva_moneda_extranjera',
        'motivo_exencion_iva',
        'base_impuesto_consumo',
        'porcentaje_impuesto_consumo',
        'valor_impuesto_consumo',
        'base_impuesto_consumo_moneda_extranjera',
        'valor_impuesto_consumo_moneda_extranjera',
        'base_ica',
        'porcentaje_ica',
        'valor_ica',
        'base_ica_moneda_extranjera',
        'valor_ica_moneda_extranjera',
    ];

    /**
     * Columnas obligatorias en los excel para los items de Documento Soporte
     * @var array
     */
    public static $columnasObligatoriasItemsDS = [
        'tipo_item',
        'cod_clasificacion_producto',
        'cod_producto',
        'descripcion_1',
        'descripcion_2',
        'descripcion_3',
        'notas',
        'cantidad',
        'cantidad_paquete',
        'cod_unidad_medida',
        'valor_unitario',
        'total',
        'valor_unitario_moneda_extranjera',
        'total_moneda_extranjera',
        'muestra_comercial',
        'cod_precio_referencia',
        'valor_muestra',
        'valor_muestra_moneda_extranjera',
        'datos_tecnicos',
        'cod_forma_generacion_y_transmision',
        'base_iva',
        'porcentaje_iva',
        'valor_iva',
        'base_iva_moneda_extranjera',
        'valor_iva_moneda_extranjera',
        'motivo_exencion_iva',
    ];

    public static $columnasOptativasItems = [
        'ddo_identificador',
        'marca',
        'modelo',
        'cod_vendedor',
        'cod_vendedor_subespecificacion',
        'cod_fabricante',
        'cod_fabricante_subespecificacion',
        'nombre_fabricante',
        'nombre_clasificacion_producto',
        'cod_pais_de_origen',
        'ddo_cargo_descripcion',
        'ddo_cargo_porcentaje',
        'ddo_cargo_base',
        'ddo_cargo_valor',
        'ddo_cargo_base_moneda_extranjera',
        'ddo_cargo_valor_moneda_extranjera',
        'ddo_descuento_codigo',
        'ddo_descuento_descripcion',
        'ddo_descuento_porcentaje',
        'ddo_descuento_base',
        'ddo_descuento_valor',
        'ddo_descuento_base_moneda_extranjera',
        'ddo_descuento_valor_moneda_extranjera'
    ];

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
        'entrega_bienes_fecha_salida',
        'entrega_bienes_despacho_fecha_solicitada',
        'entrega_bienes_despacho_fecha_estimada',
        'entrega_bienes_despacho_fecha_real',
        'anticipo_fecha_recibio'
    ];

}
