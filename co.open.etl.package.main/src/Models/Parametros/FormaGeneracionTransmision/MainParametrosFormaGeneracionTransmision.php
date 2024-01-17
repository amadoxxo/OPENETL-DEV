<?php

namespace openEtl\Main\Models\Parametros\FormaGeneracionTransmision;

use openEtl\Main\Models\MainModel;

/**
 * @property int $fgt_id
 * @property string $fgt_codigo
 * @property string $fgt_descripcion
 * @property string $fecha_vigencia_desde
 * @property string $fecha_vigencia_hasta
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosFormaGeneracionTransmision extends MainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_forma_generacion_transmision';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'fgt_id';

    /**
     * @var array
     */
    public static $rules = [
        'fgt_codigo'              => 'required|string|max:2',
        'fgt_descripcion'         => 'required|string|max:100',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'fgt_codigo'              => 'nullable|string|max:2',
        'fgt_descripcion'         => 'nullable|string|max:100',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string',
        'estado'                  => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'fgt_codigo',
        'fgt_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'fgt_id',
        'fgt_codigo',
        'fgt_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
