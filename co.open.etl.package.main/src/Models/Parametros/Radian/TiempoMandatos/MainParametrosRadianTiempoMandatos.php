<?php

namespace openEtl\Main\Models\Parametros\Radian\TiempoMandatos;

use openEtl\Main\Models\MainModel;

/**
 * @property int $tie_id
 * @property string $tie_codigo
 * @property string $tie_nombre
 * @property string $tie_descripcion
 * @property string $fecha_vigencia_desde
 * @property string $fecha_vigencia_hasta
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosRadianTiempoMandatos extends MainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_radian_tiempo_mandatos';
    
    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'tie_id';

    /**
     * @var array
     */
    public static $rules = [
        'tie_codigo'              => 'required|string|max:5',
        'tie_nombre'              => 'required|string|max:100',
        'tie_descripcion'         => 'required|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'tie_codigo'              => 'nullable|string|max:5',
        'tie_nombre'              => 'nullable|string|max:100',
        'tie_descripcion'         => 'nullable|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string',
        'estado'                  => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'tie_codigo',
        'tie_nombre',
        'tie_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'tie_id',
        'tie_codigo',
        'tie_nombre',
        'tie_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
