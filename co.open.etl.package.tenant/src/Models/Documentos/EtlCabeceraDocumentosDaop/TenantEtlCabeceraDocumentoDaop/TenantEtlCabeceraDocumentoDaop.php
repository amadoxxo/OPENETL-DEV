<?php

namespace openEtl\Tenant\Models\Documentos\EtlCabeceraDocumentosDaop;

use openEtl\Tenant\Models\TenantMainModel;

/**
 * @property int $cdo_id
 * @property int $ofe_id
 * @property int $ofe_id_multiples
 * @property int $adq_id
 * @property int $adq_id_multiples
 * @property int $adq_id_autorizado
 * @property int $rfa_id
 * @property string $cdo_origen
 * @property string $cdo_clasificacion
 * @property int $tde_id
 * @property int $top_id
 * @property string $cdo_lote
 * @property string $rfa_prefijo
 * @property string $cdo_consecutivo
 * @property string $cdo_fecha
 * @property string $cdo_hora
 * @property string $cdo_vencimiento
 * @property string $cdo_observacion
 * @property string $cdo_representacion_grafica_documento
 * @property string $cdo_representacion_grafica_acuse
 * @property string $cdo_documento_referencia
 * @property string $cdo_conceptos_correccion
 * @property int $mon_id
 * @property int $mon_id_extranjera
 * @property float $cdo_trm
 * @property string $cdo_trm_fecha
 * @property string $cdo_envio_dian_moneda_extranjera
 * @property float $cdo_valor_sin_impuestos
 * @property float $cdo_valor_sin_impuestos_moneda_extranjera
 * @property float $cdo_impuestos
 * @property float $cdo_impuestos_moneda_extranjera
 * @property float $cdo_retenciones
 * @property float $cdo_retenciones_moneda_extranjera
 * @property float $cdo_total
 * @property float $cdo_total_moneda_extranjera
 * @property float $cdo_cargos
 * @property float $cdo_cargos_moneda_extranjera
 * @property float $cdo_descuentos
 * @property float $cdo_descuentos_moneda_extranjera
 * @property float $cdo_retenciones_sugeridas
 * @property float $cdo_retenciones_sugeridas_moneda_extranjera
 * @property float $cdo_anticipo
 * @property float $cdo_anticipo_moneda_extranjera
 * @property float $cdo_redondeo
 * @property float $cdo_redondeo_moneda_extranjera
 * @property float $cdo_valor_a_pagar
 * @property float $cdo_valor_a_pagar_moneda_extranjera
 * @property string $cdo_cufe
 * @property string $cdo_qr
 * @property string $cdo_signaturevalue
 * @property string $cdo_procesar_documento
 * @property string $cdo_fecha_procesar_documento
 * @property string $cdo_fecha_validacion_dian
 * @property string $cdo_fecha_recibo_bien
 * @property string $cdo_fecha_acuse
 * @property string $cdo_estado
 * @property string $cdo_fecha_estado
 * @property string $cdo_fecha_archivo_salida
 * @property string $cdo_contingencia
 * @property json $cdo_nombre_archivos
 * @property json $cdo_atributos
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class TenantEtlCabeceraDocumentoDaop extends TenantMainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_cabecera_documentos_daop';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'cdo_id';

    /**
     * @var array
     */
    public static $rules = [
        'ofe_id'                                      => 'required|numeric',
        'ofe_id_multiples'                            => 'nullable|json',
        'adq_id'                                      => 'required|numeric',
        'adq_id_multiples'                            => 'nullable|json',
        'adq_id_autorizado'                           => 'nullable|numeric',
        'rfa_id'                                      => 'nullable|numeric',
        'cdo_origen'                                  => 'required|in:MANUAL,INTEGRACION',
        'cdo_clasificacion'                           => 'required|in:FC,NC,ND,DS,DS-NC',
        'tde_id'                                      => 'required|numeric',
        'top_id'                                      => 'required|numeric',
        'cdo_lote'                                    => 'required|string|max:60',
        'rfa_prefijo'                                 => 'nullable|string|max:5',
        'cdo_consecutivo'                             => 'required|string|max:20',
        'cdo_fecha'                                   => 'required|date_format:Y-m-d|before:tomorrow',
        'cdo_hora'                                    => 'required|date_format:H:i:s',
        'cdo_vencimiento'                             => 'nullable|date_format:Y-m-d|after_or_equal:cdo_fecha',
        'cdo_observacion'                             => 'nullable|json',
        'cdo_representacion_grafica_documento'        => 'required|string|max:3',
        'cdo_representacion_grafica_acuse'            => 'required|string|max:3',
        'cdo_documento_referencia'                    => 'nullable|json',
        'cdo_conceptos_correccion'                    => 'nullable|json',
        'mon_id'                                      => 'required|numeric',
        'mon_id_extranjera'                           => 'nullable|numeric',
        'cdo_trm'                                     => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_trm_fecha'                               => 'nullable|date_format:Y-m-d',
        'cdo_envio_dian_moneda_extranjera'            => 'nullable|string|in:SI,NO',
        'cdo_valor_sin_impuestos'                     => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_valor_sin_impuestos_moneda_extranjera'   => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_impuestos'                               => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_impuestos_moneda_extranjera'             => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_retenciones'                             => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_retenciones_moneda_extranjera'           => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_total'                                   => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_total_moneda_extranjera'                 => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_cargos'                                  => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_cargos_moneda_extranjera'                => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_descuentos'                              => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_descuentos_moneda_extranjera'            => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_retenciones_sugeridas'                   => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_retenciones_sugeridas_moneda_extranjera' => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_anticipo'                                => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_anticipo_moneda_extranjera'              => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_redondeo'                                => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_redondeo_moneda_extranjera'              => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_valor_a_pagar'                           => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_valor_a_pagar_moneda_extranjera'         => 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_cufe'                                    => 'nullable|string',
        'cdo_qr'                                      => 'nullable|string',
        'cdo_signaturevalue'                          => 'nullable|string',
        'cdo_procesar_documento'                      => 'nullable|string|in:SI,NO',
        'cdo_fecha_procesar_documento'                => 'nullable|date_format:Y-m-d H:i:s',
        'cdo_fecha_validacion_dian'                   => 'nullable|string|max:100',
        'cdo_fecha_recibo_bien'                       => 'nullable|string|max:100',
        'cdo_fecha_acuse'                             => 'nullable|date_format:Y-m-d H:i:s',
        'cdo_estado'                                  => 'nullable|string|max:20',
        'cdo_fecha_estado'                            => 'nullable|date_format:Y-m-d H:i:s',
        'cdo_fecha_archivo_salida'                    => 'nullable|date_format:Y-m-d H:i:s',
        'cdo_contingencia'                            => 'nullable|string|in:SI,NO',
        'cdo_nombre_archivos'                         => 'nullable|json',
        'cdo_atributos'                               => 'nullable|json'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'ofe_id'                                      => 'nullable|numeric',
        'ofe_id_multiples'                            => 'nullable|json',
        'adq_id'                                      => 'nullable|numeric',
        'adq_id_multiples'                            => 'nullable|json',
        'adq_id_autorizado'                           => 'nullable|numeric',
        'rfa_id'                                      => 'nullable|numeric',
        'cdo_origen'                                  => 'nullable|in:MANUAL,INTEGRACION',
        'cdo_clasificacion'                           => 'nullable|in:FC,NC,ND,DS,DS-NC',
        'tde_id'                                      => 'nullable|numeric',
        'top_id'                                      => 'nullable|numeric',
        'cdo_lote'                                    => 'nullable|string|max:60',
        'rfa_prefijo'                                 => 'nullable|string|max:5',
        'cdo_consecutivo'                             => 'nullable|string|max:20',
        'cdo_fecha'                                   => 'nullable|date_format:Y-m-d|before:tomorrow',
        'cdo_hora'                                    => 'nullable|date_format:H:i:s',
        'cdo_vencimiento'                             => 'nullable|date_format:Y-m-d|after_or_equal:cdo_fecha',
        'cdo_observacion'                             => 'nullable|json',
        'cdo_representacion_grafica_documento'        => 'nullable|string|max:3',
        'cdo_representacion_grafica_acuse'            => 'nullable|string|max:3',
        'cdo_documento_referencia'                    => 'nullable|json',
        'cdo_conceptos_correccion'                    => 'nullable|json',
        'mon_id'                                      => 'nullable|numeric',
        'mon_id_extranjera'                           => 'nullable|numeric',
        'cdo_trm'                                     => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_trm_fecha'                               => 'nullable|date_format:Y-m-d',
        'cdo_envio_dian_moneda_extranjera'            => 'nullable|string|in:SI,NO',
        'cdo_valor_sin_impuestos'                     => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_valor_sin_impuestos_moneda_extranjera'   => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_impuestos'                               => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_impuestos_moneda_extranjera'             => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_retenciones'                             => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_retenciones_moneda_extranjera'           => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_total'                                   => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_total_moneda_extranjera'                 => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_cargos'                                  => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_cargos_moneda_extranjera'                => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_descuentos'                              => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_descuentos_moneda_extranjera'            => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_retenciones_sugeridas'                   => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_retenciones_sugeridas_moneda_extranjera' => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_anticipo'                                => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_anticipo_moneda_extranjera'              => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_redondeo'                                => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_redondeo_moneda_extranjera'              => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_valor_a_pagar'                           => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_valor_a_pagar_moneda_extranjera'         => 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{2,6})?$/',
        'cdo_cufe'                                    => 'nullable|string',
        'cdo_qr'                                      => 'nullable|string',
        'cdo_signaturevalue'                          => 'nullable|string',
        'cdo_procesar_documento'                      => 'nullable|string|in:SI,NO',
        'cdo_fecha_procesar_documento'                => 'nullable|date_format:Y-m-d H:i:s',
        'cdo_fecha_validacion_dian'                   => 'nullable|string|max:100',
        'cdo_fecha_recibo_bien'                       => 'nullable|string|max:100',
        'cdo_fecha_acuse'                             => 'nullable|date_format:Y-m-d H:i:s',
        'cdo_estado'                                  => 'nullable|string|max:20',
        'cdo_fecha_estado'                            => 'nullable|date_format:Y-m-d H:i:s',
        'cdo_fecha_archivo_salida'                    => 'nullable|date_format:Y-m-d H:i:s',
        'cdo_contingencia'                            => 'nullable|string|in:SI,NO',
        'cdo_nombre_archivos'                         => 'nullable|json',
        'cdo_atributos'                               => 'nullable|json',
        'estado'                                      => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'ofe_id',
        'ofe_id_multiples',
        'adq_id',
        'adq_id_multiples',
        'adq_id_autorizado',
        'rfa_id',
        'cdo_origen',
        'cdo_clasificacion',
        'tde_id',
        'top_id',
        'cdo_lote',
        'rfa_prefijo',
        'cdo_consecutivo',
        'cdo_fecha',
        'cdo_hora',
        'cdo_vencimiento',
        'cdo_observacion',
        'cdo_representacion_grafica_documento',
        'cdo_representacion_grafica_acuse',
        'cdo_documento_referencia',
        'cdo_conceptos_correccion',
        'mon_id',
        'mon_id_extranjera',
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
        'cdo_valor_a_pagar',
        'cdo_valor_a_pagar_moneda_extranjera',
        'cdo_cufe',
        'cdo_qr',
        'cdo_signaturevalue',
        'cdo_procesar_documento',
        'cdo_fecha_procesar_documento',
        'cdo_fecha_validacion_dian',
        'cdo_fecha_recibo_bien',
        'cdo_fecha_acuse',
        'cdo_estado',
        'cdo_fecha_estado',
        'cdo_fecha_archivo_salida',
        'cdo_contingencia',
        'cdo_nombre_archivos',
        'cdo_atributos',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'ofe_id',
        'ofe_id_multiples',
        'adq_id',
        'adq_id_multiples',
        'adq_id_autorizado',
        'rfa_id',
        'cdo_origen',
        'cdo_clasificacion',
        'tde_id',
        'top_id',
        'cdo_lote',
        'rfa_prefijo',
        'cdo_consecutivo',
        'cdo_fecha',
        'cdo_hora',
        'cdo_vencimiento',
        'cdo_observacion',
        'cdo_representacion_grafica_documento',
        'cdo_representacion_grafica_acuse',
        'cdo_documento_referencia',
        'cdo_conceptos_correccion',
        'mon_id',
        'mon_id_extranjera',
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
        'cdo_valor_a_pagar',
        'cdo_valor_a_pagar_moneda_extranjera',
        'cdo_cufe',
        'cdo_qr',
        'cdo_signaturevalue',
        'cdo_procesar_documento',
        'cdo_fecha_procesar_documento',
        'cdo_fecha_validacion_dian',
        'cdo_fecha_recibo_bien',
        'cdo_fecha_acuse',
        'cdo_estado',
        'cdo_fecha_estado',
        'cdo_fecha_archivo_salida',
        'cdo_contingencia',
        'cdo_nombre_archivos',
        'cdo_atributos',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

    public function getCdoTotalAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoTotalMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoValorAPagarAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoValorAPagarMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoValorSinImpuestosAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoValorSinImpuestosMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoImpuestosAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoImpuestosMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoRetencionesAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoRetencionesMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoCargosAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoCargosMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoDescuentosAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoDescuentosMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoRetencionesSugeridasAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoRetencionesSugeridasMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoAnticipoAttribute($value) {
        return number_format($value, 2, '.', '');
    }

    public function getCdoAnticipoMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, '.', '');
    }
 }
