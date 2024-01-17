<?php

namespace openEtl\Main\Models\Parametros\Radian\TiposOperacion;

use openEtl\Main\Models\MainModel;

/**
 * @property int    $tor_id
 * @property string $tor_codigo
 * @property string $tor_descripcion
 * @property int    $ede_id
 * @property string $fecha_vigencia_desde
 * @property string $fecha_vigencia_hasta
 * @property int    $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosRadianTiposOperacion extends MainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_radian_tipos_operacion';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'tor_id';

    /**
     * @var array
     */
    public static $rules = [
        'tor_codigo'              => 'required|string|max:5',
        'tor_descripcion'         => 'required|string|max:255',
        'ede_id'                  => 'required|integer',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'tor_codigo'              => 'nullable|string|max:5',
        'tor_descripcion'         => 'nullable|string|max:255',
        'ede_id'                  => 'nullable|integer',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string',
        'estado'                  => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'tor_codigo',
        'tor_descripcion',
        'ede_id',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'tor_id',
        'tor_codigo',
        'tor_descripcion',
        'ede_id',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
