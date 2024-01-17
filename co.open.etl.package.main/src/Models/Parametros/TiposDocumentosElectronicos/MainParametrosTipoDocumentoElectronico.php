<?php

namespace openEtl\Main\Models\Parametros\TiposDocumentosElectronicos;

use openEtl\Main\Models\MainModel;

/**
* @property int $tde_id
* @property string $tde_codigo
* @property string $tde_descripcion
* @property string $tde_aplica_para
* @property datetime $fecha_vigencia_desde
* @property datetime $fecha_vigencia_hasta
* @property int $usuario_creacion
* @property string $fecha_creacion
* @property string $fecha_modificacion
* @property string $estado
* @property string $fecha_actualizacion
*/
class MainParametrosTipoDocumentoElectronico extends MainModel {
    protected $table = 'etl_tipos_documentos_electronicos';
    protected $primaryKey = 'tde_id';

    public static $rules = [
        'tde_codigo'           => 'required|string|max:5',
        'tde_descripcion'      => 'required|string|max:255',
        'tde_aplica_para'      => 'required|string|max:20',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s'
    ];

    public static $rulesUpdate = [
        'tde_codigo'           => 'nullable|string|max:5',
        'tde_descripcion'      => 'nullable|string|max:255',
        'tde_aplica_para'      => 'nullable|string|max:20',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s',
        'estado'               => 'nullable|string|in:ACTIVO,INACTIVO'
    ];

    protected $fillable = [
        'tde_codigo',
        'tde_descripcion',
        'tde_aplica_para',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    protected $visible = [
        'tde_id',
        'tde_codigo',
        'tde_descripcion',
        'tde_aplica_para',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}

