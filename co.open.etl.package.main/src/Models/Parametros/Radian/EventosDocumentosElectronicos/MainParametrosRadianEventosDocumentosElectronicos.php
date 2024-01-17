<?php

namespace openEtl\Main\Models\Parametros\Radian\EventosDocumentosElectronicos;

use openEtl\Main\Models\MainModel;

/**
 * @property int    $ede_id
 * @property string $ede_codigo
 * @property string $ede_descripcion
 * @property string $fecha_vigencia_desde
 * @property string $fecha_vigencia_hasta
 * @property int    $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosRadianEventosDocumentosElectronicos extends MainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_radian_eventos_documentos_electronicos';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'ede_id';

    /**
     * @var array
     */
    public static $rules = [
        'ede_codigo'              => 'required|string|max:5',
        'ede_descripcion'         => 'required|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'ede_codigo'              => 'nullable|string|max:5',
        'ede_descripcion'         => 'nullable|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string',
        'estado'                  => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'ede_codigo',
        'ede_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'ede_id',
        'ede_codigo',
        'ede_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
