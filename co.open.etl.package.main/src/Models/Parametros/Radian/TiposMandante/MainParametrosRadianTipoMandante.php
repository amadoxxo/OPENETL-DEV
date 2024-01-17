<?php

namespace openEtl\Main\Models\Parametros\Radian\TiposMandante;

use openEtl\Main\Models\MainModel;

/**
 * @property int      $tma_id
 * @property string   $tma_codigo
 * @property string   $tma_descripcion
 * @property string   $fecha_vigencia_desde
 * @property string   $fecha_vigencia_hasta
 * @property int      $usuario_creacion
 * @property string   $fecha_creacion
 * @property string   $fecha_modificacion
 * @property string   $estado
 * @property string   $fecha_actualizacion
 */
class MainParametrosRadianTipoMandante extends MainModel {
    /**
     * Tabla relacionada con el modelo.
     * 
     * @var string
     */
    protected $table = 'etl_radian_tipo_mandante';

    /**
     * Llave primaria de la tabla.
     * 
     * @var string
     */
    protected $primaryKey = 'tma_id';

    /**
     * Reglas de validación para el recurso crear.
     * 
     * @var array
     */
    public static $rules = [
        'tma_codigo'              => 'required|string|max:20',
        'tma_descripcion'         => 'required|string|max:100',
        'fecha_vigencia_desde'    => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta'    => 'nullable|date_format:Y-m-d H:i:s'
    ];

    /**
     * Reglas de validación para el recurso actualizar.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'tma_codigo'              => 'nullable|string|max:20',
        'tma_descripcion'         => 'nullable|string|max:100',
        'fecha_vigencia_desde'    => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta'    => 'nullable|date_format:Y-m-d H:i:s',
        'estado'                  => 'nullable|string|in:ACTIVO,INACTIVO'
    ];

    /**
     * Los atributos que son asignables en masa.
     * 
     * @var array
     */
    protected $fillable = [
        'tma_codigo',
        'tma_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'tma_id',
        'tma_codigo',
        'tma_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
