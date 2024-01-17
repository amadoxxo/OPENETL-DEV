<?php

namespace openEtl\Main\Models\Parametros\ConceptosCorreccion;

use openEtl\Main\Models\MainModel;

/**
* @property int $cco_id
* @property string $cco_tipo
* @property string $cco_codigo
* @property string $cco_descripcion
* @property datetime $fecha_vigencia_desde
* @property datetime $fecha_vigencia_hasta
* @property int $usuario_creacion
* @property string $fecha_creacion
* @property string $fecha_modificacion
* @property string $estado
* @property string $fecha_actualizacion
*/
class MainParametrosConceptoCorreccion extends MainModel {

    protected $table = 'etl_conceptos_correccion';
    protected $primaryKey = 'cco_id';

    public static $rules = [
        'cco_tipo'             => 'required|string|in:NC,ND',
        'cco_codigo'           => 'required|string|max:10',
        'cco_descripcion'      => 'required|string|max:255',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s'
    ];

    public static $rulesUpdate = [
        'cco_tipo'             => 'required|string|in:NC,ND',
        'cco_codigo'           => 'required|string|max:10',
        'cco_descripcion'      => 'required|string|max:255',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s',
        'estado'               => 'string|in:ACTIVO,INACTIVO'
    ];

    protected $fillable = [
        'cco_tipo',
        'cco_codigo',
        'cco_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    protected $visible = [
        'cco_id',
        'cco_tipo',
        'cco_codigo',
        'cco_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
