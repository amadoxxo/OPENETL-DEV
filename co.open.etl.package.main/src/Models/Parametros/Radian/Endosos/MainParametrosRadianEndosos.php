<?php

namespace openEtl\Main\Models\Parametros\Radian\Endosos;

use openEtl\Main\Models\MainModel;

/**
 * @property int    $end_id
 * @property string $end_codigo
 * @property string $end_nombre
 * @property string $end_descripcion
 * @property string $fecha_vigencia_desde
 * @property string $fecha_vigencia_hasta
 * @property int    $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosRadianEndosos extends MainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_radian_endosos';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'end_id';

    /**
     * @var array
     */
    public static $rules = [
        'end_codigo'              => 'required|string|max:5',
        'end_nombre'              => 'required|string|max:20',
        'end_descripcion'         => 'required|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'end_codigo'              => 'nullable|string|max:5',
        'end_nombre'              => 'nullable|string|max:20',
        'end_descripcion'         => 'nullable|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string',
        'estado'                  => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'end_codigo',
        'end_nombre',
        'end_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'end_id',
        'end_codigo',
        'end_nombre',
        'end_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
