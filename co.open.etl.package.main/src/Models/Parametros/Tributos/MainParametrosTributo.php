<?php

namespace openEtl\Main\Models\Parametros\Tributos;

use openEtl\Main\Models\MainModel;

/**
* @property int $tri_id
* @property string $tri_codigo
* @property string $tri_nombre
* @property string $tri_descripcion
* @property string $tri_tipo
* @property string $tri_aplica_persona
* @property string $tri_aplica_para_personas
* @property string $tri_aplica_tributo
* @property string $tri_aplica_para_tributo
* @property datetime $fecha_vigencia_desde
* @property datetime $fecha_vigencia_hasta
* @property int $usuario_creacion
* @property string $fecha_creacion
* @property string $fecha_modificacion
* @property string $estado
* @property string $fecha_actualizacion
*/
class MainParametrosTributo extends MainModel {
    protected $table = 'etl_tributos';
    protected $primaryKey = 'tri_id';

    public static $rules = [
        'tri_codigo'               => 'required|string|max:2',
        'tri_nombre'               => 'required|string|max:100',
        'tri_descripcion'          => 'required|string|max:255',
        'tri_tipo'                 => 'required|string|in:TRIBUTO,TRIBUTO-UNIDAD,RETENCION,RETENCION-UNIDAD',
        'tri_aplica_persona'       => 'nullable|string|in:SI,NO',
        'tri_aplica_para_personas' => 'nullable|string|max:20',
        'tri_aplica_tributo'       => 'nullable|string|in:SI,NO',
        'tri_aplica_para_tributo'  => 'nullable|string|max:20',
        'fecha_vigencia_desde'     => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta'     => 'nullable|date_format:Y-m-d H:i:s'
    ];

    public static $rulesUpdate = [
        'tri_codigo'               => 'nullable|string|max:2',
        'tri_nombre'               => 'nullable|string|max:100',
        'tri_descripcion'          => 'nullable|string|max:255',
        'tri_tipo'                 => 'nullable|string|in:TRIBUTO,TRIBUTO-UNIDAD,RETENCION,RETENCION-UNIDAD',
        'tri_aplica_persona'       => 'nullable|string|in:SI,NO',
        'tri_aplica_para_personas' => 'nullable|string|max:20',
        'tri_aplica_tributo'       => 'nullable|string|in:SI,NO',
        'tri_aplica_para_tributo'  => 'nullable|string|max:20',
        'fecha_vigencia_desde'     => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta'     => 'nullable|date_format:Y-m-d H:i:s',
        'estado'                   => 'nullable|string|in:ACTIVO,INACTIVO'
    ];

    protected $fillable = [
        'tri_codigo',
        'tri_nombre',
        'tri_descripcion',
        'tri_tipo',
        'tri_aplica_persona',
        'tri_aplica_para_personas',
        'tri_aplica_tributo',
        'tri_aplica_para_tributo',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    protected $visible = [
        'tri_id',
        'tri_codigo',
        'tri_nombre',
        'tri_descripcion',
        'tri_tipo',
        'tri_aplica_persona',
        'tri_aplica_para_personas',
        'tri_aplica_tributo',
        'tri_aplica_para_tributo',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
