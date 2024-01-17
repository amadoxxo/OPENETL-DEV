<?php

namespace App\Http\Modulos\DataInputWorker;

class KeyMap
{
    /**
     * Reglas para procesar la Cabecera de Documentos
     * @var array
     */
    public static $clavesCDO = [
        'cdo_origen',
        'tde_codigo',
        'top_codigo',
        'ofe_identificacion',
        'ofe_identificacion_multiples',
        'adq_identificacion',
        'adq_id_personalizado',
        'adq_identificacion_multiples',
        'adq_identificacion_autorizado',
        'rfa_resolucion',
        'rfa_prefijo',
        'cdo_consecutivo',
        'cdo_fecha',
        'cdo_hora',
        'cdo_vencimiento',
        'cdo_observacion',
        'cdo_representacion_grafica_documento',
        'cdo_representacion_grafica_acuse',
        'cdo_trm',
        'cdo_trm_fecha',
        'cdo_envio_dian_moneda_extranjera',
        'cdo_valor_sin_impuestos',
        'cdo_valor_sin_impuestos_moneda_extranjera',
        'cdo_impuestos',
        'cdo_impuestos_moneda_extranjera',
        'cdo_retenciones',
        'cdo_retenciones_moneda_extranjera',
        'cdo_total',
        'cdo_total_moneda_extranjera',
        'cdo_cargos',
        'cdo_cargos_moneda_extranjera',
        'cdo_descuentos',
        'cdo_descuentos_moneda_extranjera',
        'cdo_retenciones_sugeridas',
        'cdo_retenciones_sugeridas_moneda_extranjera',
        'cdo_anticipo',
        'cdo_anticipo_moneda_extranjera',
        'cdo_redondeo',
        'cdo_redondeo_moneda_extranjera',
        'cdo_informacion_adicional',
        'cdo_conceptos_correccion',
        'cdo_documento_referencia',
        'mon_codigo_extranjera',
        'mon_codigo',
        'atributo_top_codigo_id'
    ];

    /**
     * Reglas para procesar los Datos Adicionales de Cabecera
     * @var array
     */
    public static $clavesDAD = [
        'dad_orden_referencia',
        'dad_despacho_referencia',
        'dad_recepcion_referencia',
        'cdo_documento_adicional',
        'dad_condiciones_entrega',
        'dad_entrega_bienes_despacho',
        'dad_terminos_entrega',
        'dad_moneda_alternativa',
        'cdo_periodo_facturacion',
        'cdo_interoperabilidad',
        'cdo_documento_referencia_linea',
        'cdo_referencia_adquirente'
    ];

    /**
     * Reglas para procesar los Items
     * @var array
     */
    public static $clavesItem = [
        'ddo_tipo_item',
        'ddo_secuencia',
        'cpr_codigo',
        'ddo_codigo',
        'ddo_descripcion_uno',
        'ddo_descripcion_dos',
        'ddo_descripcion_tres',
        'ddo_notas',
        'ddo_cantidad',
        'ddo_cantidad_paquete',
        'und_codigo',
        'ddo_valor_unitario',
        'ddo_valor_unitario_moneda_extranjera',
        'ddo_total',
        'ddo_total_moneda_extranjera',
        'ddo_indicador_muestra',
        'ddo_datos_tecnicos',
        'ddo_marca',
        'ddo_modelo',
        'ddo_codigo_vendedor',
        'ddo_codigo_vendedor_subespecificacion',
        'ddo_codigo_fabricante',
        'ddo_codigo_fabricante_subespecificacion',
        'ddo_nombre_fabricante',
        'pai_codigo',
        'ddo_propiedades_adicionales',
        'ddo_nit_mandatario',
        'ddo_tdo_codigo_mandatario',
        'ddo_identificador',
        'ddo_cargos',
        'ddo_descuentos',
        'ddo_detalle_retenciones_sugeridas',
        'ddo_informacion_adicional',
        'ddo_identificacion_comprador',
        'ddo_precio_referencia',
        'ddo_detalle_retenciones_sugeridas'
    ];

    /**
     * Reglas para procesar los Medios de pago
     * @var array
     */
    public static $clavesMediosPago = [
        'fpa_codigo',
        'atributo_fpa_codigo_id',
        'atributo_fpa_codigo_name',
        'mpa_codigo',
        'men_fecha_vencimiento',
        'men_identificador_pago'
    ];

    /**
     * Reglas para procesar los Medios de pago
     * @var array
     */
    public static $clavesAnticipos = [
        'ant_identificacion',
        'ant_valor',
        'ant_valor_moneda_extranjera',
        'ant_fecha_recibido',
        'ant_fecha_realizado',
        'ant_hora_realizado',
        'ant_instrucciones',
    ];

    // etl_cargos_descuentos_documentos_daop
    /**
     * Reglas para procesar las Retenciones Sugeridas
     * @var array
     */
    public static $clavesRetencionesSugeridas = [
        'tipo',
        'razon',
        'porcentaje',
        'valor_moneda_nacional',
        'valor_moneda_extranjera'
    ];

    /**
     * Reglas para procesar los Cargos
     * @var array
     */
    public static $clavesCargos = [
        'razon',
        'porcentaje',
        'valor_moneda_nacional',
        'valor_moneda_extranjera'
    ];

    /**
     * Reglas para procesar los Descuentos
     * @var array
     */
    public static $clavesDescuentos = [
        'cde_codigo',
        'razon',
        'porcentaje',
        'valor_moneda_nacional',
        'valor_moneda_extranjera'
    ];

    /**
     * Reglas para procesar los impuestos
     * @var array
     */
    public static $clavesImpuestos = [
        'ddo_secuencia',
        'tri_codigo',
        'iid_valor',
        'iid_valor_moneda_extranjera',
        'iid_redondeo_agregado',
        'iid_redondeo_agregado_moneda_extranjera',
        'iid_motivo_exencion',
        'iid_porcentaje',
        'iid_unidad',
        'iid_nombre_figura_tributaria'
    ];

    /**
     * Reglas para procesar las autoretenciones
     * @var array
     */
    public static $clavesAutoretenciones = [
        'ddo_secuencia',
        'tri_codigo',
        'iid_valor',
        'iid_valor_moneda_extranjera',
        'iid_redondeo_agregado', 
        'iid_redondeo_agregado_moneda_extranjera',
        'iid_motivo_exencion',
        'iid_porcentaje',
        'iid_nombre_figura_tributaria'
    ];

    /**
     * Reglas para procesar los documentos referencia
     * @var array
     */
    public static $clavesCdoDocumentoReferencia = [
        'clasificacion',
        'prefijo',
        'consecutivo',
        'cufe',
        'fecha_emision',
        'atributo_consecutivo_id',
        'atributo_consecutivo_name',
        'atributo_consecutivo_agency_id',
        'atributo_consecutivo_version_id',
        'uuid',
        'atributo_uuid_name',
        'codigo_tipo_documento',
        'atributo_codigo_tipo_documento_list_uri',
        'tipo_documento'
    ];

    /**
     * Reglas para procesar los documentos adicionales
     * @var array
     */
    public static $clavesCdoDocumentoAdicional = [
        'seccion',
        'rod_codigo',
        'atributo_rod_codigo_list_uri',
        'prefijo',
        'consecutivo',
        'cufe',
        'fecha_emision',
        'atributo_consecutivo_id',
        'atributo_consecutivo_name',
        'atributo_consecutivo_agency_id',
        'atributo_consecutivo_version_id',
        'uuid',
        'atributo_uuid_name',
        'tipo_documento'
    ];

    /**
     * Reglas para procesar los documentos referencia x linea
     * @var array
     */
    public static $clavesCdoDocumentoReferenciaLinea = [
        'prefijo',
        'consecutivo',
        'atributo_consecutivo_id',
        'atributo_consecutivo_name',
        'atributo_consecutivo_agency_id',
        'atributo_consecutivo_version_id',
        'valor',
        'atributo_valor_moneda',
        'atributo_valor_concepto'
    ];

    /**
     * Reglas para procesar los documentos Referencia Adquirente
     * @var array
     */
    public static $cdoReferenciaAdquirente = [
        'id',
        'atributo_id_name',
        'nombre',
        'postal_address_codigo_pais',
        'postal_address_descripcion_pais',
        'residence_address_id',
        'residence_address_atributo_id_name',
        'residence_address_nombre_ciudad',
        'residence_address_direccion',
        'codigo_pais',
        'descripcion_pais'
    ];

    public static $claveCdoMedioPago = 'cdo_medios_pago';

    public static $claveCdoDetalleAnticipos = 'cdo_detalle_anticipos';

    public static $claveCdoRetencionesSugeridas = 'cdo_detalle_retenciones_sugeridas';

    public static $claveCdoDetalleCargos = 'cdo_detalle_cargos';

    public static $claveCdoDetalleDescuentos = 'cdo_detalle_descuentos';

    public static $claveItems = 'items';

    public static $claveTributos = 'tributos';

    public static $claveRetenciones = 'retenciones';

}
