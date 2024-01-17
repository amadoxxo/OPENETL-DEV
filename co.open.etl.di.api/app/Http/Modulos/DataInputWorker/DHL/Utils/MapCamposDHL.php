<?php

namespace App\Http\Modulos\DataInputWorker\DHL\Utils;

/**
 * Contiene el mapeo de campos de las empresas de DHL Agencia, GlobalForwarding, ZonaFranca
 *
 * Class MapCamposDHL
 * @package App\Http\Modulos\DataInputWorker\DHL\Utils
 */
class MapCamposDHL
{
    public static $camposFechasFC = [
        'cdo_fecha',
        'cdo_vencimiento',
        'ant_fecha_recibido',
        'cdo_vencimiento',
        'fecha_emision',
        'fecha_trm',
    ];
    public static $camposFechasNCND = [
        'cdo_fecha',
        'cdo_vencimiento',
        'ant_fecha_recibido',
        'cdo_vencimiento',
        'fecha_emision',
        'fecha_trm',
    ];
    public static $camposHoraFC = [
        'cdo_hora',
    ];
    public static $camposHoraNCND = [
        'cdo_hora',
    ];
    public static $camposFechasAdicional = [
        'fecha',
        'vencimiento',
    ];
    // Mapeo
    /**
     * Columnas obligatorias en los excel de facturas para cabecera
     *
     * @var array
     */
    public static $columnasObligatoriasCabeceraFC = [
        'tipo_documento'                         => 'tde_codigo',
        'tipo_operacion'                         => 'top_codigo',
        'nit_ofe'                                => 'ofe_identificacion',
        'nit_adquirente'                         => 'nit_receptor',
        'resolucion_facturacion'                 => 'rfa_resolucion',
        'prefijo'                                => 'rfa_prefijo',
        'consecutivo'                            => 'cdo_consecutivo',
        'fecha'                                  => 'cdo_fecha',
        'hora'                                   => 'cdo_hora',
        'fecha_vencimiento'                      => 'cdo_vencimiento',
        'cod_forma_de_pago'                      => 'fpa_codigo',
        'cod_medio_de_pago'                      => 'mpa_codigo',
        'identificador_pago'                     => 'men_identificador_pago_id',
        'cod_moneda'                             => 'mon_codigo',
        'cod_moneda_extranjera'                  => 'mon_codigo_moneda_extranjera',
        'trm'                                    => 'trm',
        'fecha_trm'                              => 'cdo_trm_fecha',
        'observacion'                            => 'observacion',
        'enviar_a_la_dian_en_moneda_extranjera'  => 'enviar_a_la_dian_en_moneda_extranjera',
        'representacion_grafica_documento'       => 'cdo_representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo' => 'cdo_representacion_grafica_acuse',
        'informacion_adicional'                  => 'informacion_adicional',
        'fecha_vencimiento_pago'                 => 'men_fecha_vencimiento',
        'cdo_redondeo'                           => 'cdo_redondeo',
        'cdo_redondeo_moneda_extranjera'         => 'cdo_redondeo_moneda_extranjera',
    ];

    /**
     * Columnas obligatorias en los excel de notas credito/debito para cabecera
     *
     * @var array
     */
    public static $columnasObligatoriasCabeceraNCND = [
        'tipo_documento'                         => 'tde_codigo',
        'tipo_operacion'                         => 'top_codigo',
        'nit_ofe'                                => 'ofe_identificacion',
        'nit_adquirente'                         => 'nit_receptor',
        'prefijo'                                => 'rfa_prefijo',
        'consecutivo'                            => 'cdo_consecutivo',
        'fecha'                                  => 'cdo_fecha',
        'hora'                                   => 'cdo_hora',
        'fecha_vencimiento'                      => 'cdo_vencimiento',
        'cod_forma_de_pago'                      => 'fpa_codigo',
        'cod_medio_de_pago'                      => 'mpa_codigo',
        'prefijo_factura'                        => 'prefijo',
        'consecutivo_factura'                    => 'consecutivo_factura',
        'cufe_factura'                           => 'cufe',
        'fecha_factura'                          => 'fecha_emision',
        'cod_concepto_correccion'                => 'cco_codigo',
        'observacion_correccion'                 => 'cdo_observacion_correccion',
        'cod_moneda'                             => 'mon_codigo',
        'cod_moneda_extranjera'                  => 'mon_codigo_moneda_extranjera',
        'trm'                                    => 'trm',
        'fecha_trm'                              => 'fecha_trm',
        'fecha_vencimiento_pago'                 => 'men_fecha_vencimiento',
        'observacion'                            => 'observacion',
        'enviar_a_la_dian_en_moneda_extranjera'  => 'enviar_a_la_dian_en_moneda_extranjera',
        'representacion_grafica_documento'       => 'cdo_representacion_grafica_documento',
        'representacion_grafica_acuse_de_recibo' => 'cdo_representacion_grafica_acuse',
        'informacion_adicional'                  => 'informacion_adicional',
        'cdo_redondeo'                           => 'cdo_redondeo',
        'cdo_redondeo_moneda_extranjera'         => 'cdo_redondeo_moneda_extranjera',
    ];

    public static $columnasAnticipos = [
        'anticipo_identificado_pago'       => 'ant_identificacion',
        'anticipo_fecha_recibio'           => 'ant_fecha_recibido',
        'anticipo_valor'                   => 'ant_valor',
        'anticipo_valor_moneda_extranjera' => 'ant_valor_moneda_extranjera',
    ];

    /**
     * Columnas obligatorias en los excel para los items
     * @var array
     */
    public static $columnasObligatoriasItems = [
        'tipo_item'                        => 'ddo_tipo',
        'cod_clasificacion_producto'       => 'cpr_codigo',
        'cod_producto'                     => 'ddo_codigo',
        'descripcion_1'                    => 'descripcion_uno',
        'descripcion_2'                    => 'descripcion_dos',
        'descripcion_3'                    => 'descripcion_tres',
        'notas'                            => 'ddo_notas',
        'cantidad'                         => 'ddo_cantidad',
        'cantidad_paquete',
        'cod_unidad_medida'                => 'und_codigo',
        'valor_unitario'                   => 'ddo_valor_unitario',
        'total'                            => 'ddo_total',
        'valor_unitario_moneda_extranjera' => 'ddo_valor_unitario_moneda_extranjera',
        'total_moneda_extranjera'          => 'ddo_total_moneda_extranjera',
        'muestra_comercial',
        'cod_precio_referencia',
        'valor_muestra',
        'valor_muestra_moneda_extranjera',
        'datos_tecnicos',
        'nit_mandatario'                   => 'nit_mandatario',
        'cod_tipo_documento_mandatario'    => 'tipo_documento_mandatario',
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
        'informacion_adicional'           => 'ddo_informacion_adicional'
    ];
}