<?php

namespace openEtl\Main\Models\Parametros\Radian\Factor;

use openEtl\Main\Models\MainModel;

/**
 * @property int    $fac_id
 * @property string $fac_codigo
 * @property string $fac_descripcion
 * @property string $fecha_vigencia_desde
 * @property string $fecha_vigencia_hasta
 * @property int    $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosRadianFactor extends MainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_radian_factor';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'fac_id';

    /**
     * @var array
     */
    public static $rules = [
        'fac_codigo'              => 'required|string|max:5',
        'fac_descripcion'         => 'required|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'fac_codigo'              => 'nullable|string|max:5',
        'fac_descripcion'         => 'nullable|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string',
        'estado'                  => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'fac_codigo',
        'fac_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'fac_id',
        'fac_codigo',
        'fac_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
