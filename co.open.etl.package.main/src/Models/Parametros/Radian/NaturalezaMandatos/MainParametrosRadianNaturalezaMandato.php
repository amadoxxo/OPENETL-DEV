<?php

namespace openEtl\Main\Models\Parametros\Radian\NaturalezaMandatos;

use openEtl\Main\Models\MainModel;

/**
 * @property int      $nma_id
 * @property string   $nma_codigo
 * @property string   $nma_descripcion
 * @property string   $fecha_vigencia_desde
 * @property string   $fecha_vigencia_hasta
 * @property int      $usuario_creacion
 * @property string   $fecha_creacion
 * @property string   $fecha_modificacion
 * @property string   $estado
 * @property string   $fecha_actualizacion
 */
class MainParametrosRadianNaturalezaMandato extends MainModel {
    /**
     * Tabla relacionada con el modelo.
     * 
     * @var string
     */
    protected $table = 'etl_radian_naturaleza_mandatos';

    /**
     * Llave primaria de la tabla.
     * 
     * @var string
     */
    protected $primaryKey = 'nma_id';

    /**
     * Reglas de validación para el recurso crear.
     * 
     * @var array
     */
    public static $rules = [
        'nma_codigo'              => 'required|string|max:5',
        'nma_descripcion'         => 'required|string|max:100',
        'fecha_vigencia_desde'    => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta'    => 'nullable|date_format:Y-m-d H:i:s'
    ];

    /**
     * Reglas de validación para el recurso actualizar.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'nma_codigo'              => 'nullable|string|max:5',
        'nma_descripcion'         => 'nullable|string|max:100',
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
        'nma_codigo',
        'nma_descripcion',
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
        'nma_id',
        'nma_codigo',
        'nma_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
