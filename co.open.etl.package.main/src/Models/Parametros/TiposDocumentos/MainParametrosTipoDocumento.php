<?php

namespace openEtl\Main\Models\Parametros\TiposDocumentos;

use openEtl\Main\Models\MainModel;

/**
* @property int $tdo_id
* @property string $tdo_codigo
* @property string $tdo_descripcion
* @property string $tdo_aplica_para
* @property datetime $fecha_vigencia_desde
* @property datetime $fecha_vigencia_hasta
* @property int $usuario_creacion
* @property string $fecha_creacion
* @property string $fecha_modificacion
* @property string $estado
* @property string $fecha_actualizacion
*/
class MainParametrosTipoDocumento extends MainModel {
    /**
     * Tabla relacionada con el modelo.
     *
     * @var string
     */
    protected $table = 'etl_tipos_de_documentos';

    /**
     * Llave primaria de la tabla.
     *
     * @var string
     */
    protected $primaryKey = 'tdo_id';

    /**
     * Reglas de validación para la creación de un registro.
     *
     * @var array
     */
    public static $rules = [
        'tdo_codigo'           => 'required|string|max:2',
        'tdo_descripcion'      => 'required|string|max:100',
        'tdo_aplica_para'      => 'nullable|string|max:20',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s'
    ];

    /**
     * Reglas de validación para la actualización de un registro.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'tdo_codigo'           => 'required|string|max:2',
        'tdo_descripcion'      => 'required|string|max:100',
        'tdo_aplica_para'      => 'nullable|string|max:20',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s',
        'estado'               => 'nullable|string|in:ACTIVO,INACTIVO'
    ];

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'tdo_codigo',
        'tdo_descripcion',
        'tdo_aplica_para',
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
        'tdo_id',
        'tdo_codigo',
        'tdo_descripcion',
        'tdo_aplica_para',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
