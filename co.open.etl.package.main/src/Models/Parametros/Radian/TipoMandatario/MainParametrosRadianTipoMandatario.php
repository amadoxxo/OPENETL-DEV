<?php

namespace openEtl\Main\Models\Parametros\Radian\TipoMandatario;

use openEtl\Main\Models\MainModel;

/**
 * @property int $tim_id
 * @property string $tim_codigo
 * @property string $tim_descripcion
 * @property string $fecha_vigencia_desde
 * @property string $fecha_vigencia_hasta
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosRadianTipoMandatario extends MainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_radian_tipo_mandatario';
    
    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'tim_id';

    /**
     * @var array
     */
    public static $rules = [
        'tim_codigo'              => 'required|string|max:10',
        'tim_descripcion'         => 'required|string|max:100',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'tim_codigo'              => 'nullable|string|max:10',
        'tim_descripcion'         => 'nullable|string|max:100',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string',
        'estado'                  => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'tim_codigo',
        'tim_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'tim_id',
        'tim_codigo',
        'tim_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
