<?php

namespace openEtl\Tenant\Models\Configuracion\ResolucionesFacturacion;

use openEtl\Tenant\Models\TenantMainModel;

/**
 * @property int $rfa_id
 * @property int $ofe_id
 * @property string $rfa_tipo
 * @property string $rfa_prefijo
 * @property string $rfa_resolucion
 * @property string $rfa_clave_tecnica
 * @property string $rfa_fecha_desde
 * @property string $rfa_fecha_hasta
 * @property string $rfa_consecutivo_inicial
 * @property string $rfa_consecutivo_final
 * @property string $cdo_control_consecutivos
 * @property string $cdo_consecutivo_provisional
 * @property string $rfa_dias_aviso
 * @property string $rfa_consecutivos_aviso
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class TenantConfiguracionResolucionesFacturacion extends TenantMainModel {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'etl_resoluciones_facturacion';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'rfa_id';

    /**
     * @var array
     */
    public static $rules = [
        'ofe_id'                          => 'required|numeric',
        'rfa_tipo'                        => 'nullable|string|max:20|in:AUTORIZACION,HABILITACION,CONTINGENCIA,DOCUMENTO_SOPORTE',
        'rfa_prefijo'                     => 'nullable|string|max:5',
        'rfa_resolucion'                  => 'required|string|max:20',
        'rfa_clave_tecnica'               => 'nullable|string',
        'rfa_fecha_desde'                 => 'required|date',
        'rfa_fecha_hasta'                 => 'required|date|after:rfa_fecha_desde',
        'rfa_consecutivo_inicial'         => 'required|string|max:20',
        'rfa_consecutivo_final'           => 'required|string|max:20',
        'cdo_control_consecutivos'        => 'nullable|string|max:2|in:SI,NO',
        'cdo_consecutivo_provisional'     => 'nullable|string|max:2|in:SI,NO',
        'rfa_dias_aviso'                  => 'nullable|string|max:4',
        'rfa_consecutivos_aviso'          => 'nullable|string|max:4'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'ofe_id'                          => 'nullable|numeric',
        'rfa_tipo'                        => 'nullable|string|max:20|in:AUTORIZACION,HABILITACION,CONTINGENCIA,DOCUMENTO_SOPORTE',
        'rfa_prefijo'                     => 'nullable|string|max:5',
        'rfa_resolucion'                  => 'nullable|string|max:20',
        'rfa_clave_tecnica'               => 'nullable|string',
        'rfa_fecha_desde'                 => 'nullable|date',
        'rfa_fecha_hasta'                 => 'nullable|date|after:rfa_fecha_desde',
        'rfa_consecutivo_inicial'         => 'nullable|string|max:20',
        'rfa_consecutivo_final'           => 'nullable|string|max:20',
        'cdo_control_consecutivos'        => 'nullable|string|max:2|in:SI,NO',
        'cdo_consecutivo_provisional'     => 'nullable|string|max:2|in:SI,NO',
        'rfa_dias_aviso'                  => 'nullable|string|max:4',
        'rfa_consecutivos_aviso'          => 'nullable|string|max:4',
        'estado'                          => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'ofe_id',
        'rfa_tipo',
        'rfa_prefijo',
        'rfa_resolucion',
        'rfa_clave_tecnica',
        'rfa_fecha_desde',
        'rfa_fecha_hasta',
        'rfa_consecutivo_inicial',
        'rfa_consecutivo_final',
        'cdo_control_consecutivos',
        'cdo_consecutivo_provisional',
        'rfa_dias_aviso',
        'rfa_consecutivos_aviso',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'rfa_id',
        'ofe_id',
        'rfa_tipo',
        'rfa_prefijo',
        'rfa_resolucion',
        'rfa_clave_tecnica',
        'rfa_fecha_desde',
        'rfa_fecha_hasta',
        'rfa_consecutivo_inicial',
        'rfa_consecutivo_final',
        'cdo_control_consecutivos',
        'cdo_consecutivo_provisional',
        'rfa_dias_aviso',
        'rfa_consecutivos_aviso',
        'fecha_creacion',
        'fecha_modificacion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

}
