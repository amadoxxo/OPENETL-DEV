<?php

namespace openEtl\Main\Models\Parametros\ResponsabilidadesFiscales;

use openEtl\Main\Models\MainModel;

/**
* @property int $ref_id
* @property string $ref_codigo
* @property string $ref_descripcion
* @property string $ref_aplica_para
* @property datetime $fecha_vigencia_desde
* @property datetime $fecha_vigencia_hasta
* @property int $usuario_creacion
* @property string $fecha_creacion
* @property string $fecha_modificacion
* @property string $estado
* @property string $fecha_actualizacion
*/
class MainParametrosResponsabilidadFiscal extends MainModel {
    protected $table = 'etl_responsabilidades_fiscales';
    protected $primaryKey = 'ref_id';

    public static $rules = [
        'ref_codigo'           => 'required|string|max:20',
        'ref_descripcion'      => 'required|string|max:100',
        'ref_aplica_para'      => 'nullable|string|max:20',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s'
    ];

    public static $rulesUpdate = [
        'ref_codigo'           => 'nullable|string|max:20',
        'ref_descripcion'      => 'nullable|string|max:100',
        'ref_aplica_para'      => 'nullable|string|max:20',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s',
        'estado'               => 'nullable|string|in:ACTIVO,INACTIVO'
    ];

    protected $fillable = [
        'ref_codigo',
        'ref_descripcion',
        'ref_aplica_para',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    protected $visible = [
        'ref_id',
        'ref_codigo',
        'ref_descripcion',
        'ref_aplica_para',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}

