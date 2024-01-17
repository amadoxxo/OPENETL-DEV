<?php

namespace openEtl\Main\Models\Parametros\SectorCambiario\DebidaDiligencia;

use openEtl\Main\Models\MainModel;

/**
* @property int $ddi_id
* @property string $ddi_codigo
* @property string $ddi_descripcion
* @property datetime $fecha_vigencia_desde
* @property datetime $fecha_vigencia_hasta
* @property int $usuario_creacion
* @property string $fecha_creacion
* @property string $fecha_modificacion
* @property string $estado
* @property string $fecha_actualizacion
*/
class MainParametrosDebidaDiligencia extends MainModel {
    /**
     * Tabla relacionada con el modelo.
     *
     * @var string
     */
    protected $table = 'etl_debida_diligencia';

    /**
     * Llave primaria de la tabla.
     *
     * @var string
     */
    protected $primaryKey = 'ddi_id';

    /**
     * Reglas de validación para la creación de un registro.
     *
     * @var array
     */
    public static $rules = [
        'ddi_codigo'           => 'required|string|max:10',
        'ddi_descripcion'      => 'required|string|max:255',
        'fecha_vigencia_desde' => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta' => 'nullable|date_format:Y-m-d H:i:s'
    ];

    /**
     * Reglas de validación para la actualización de un registro.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'ddi_codigo'           => 'nullable|string|max:10',
        'ddi_descripcion'      => 'nullable|string|max:255',
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
        'ddi_codigo',
        'ddi_descripcion',
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
        'ddi_id',
        'ddi_codigo',
        'ddi_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
