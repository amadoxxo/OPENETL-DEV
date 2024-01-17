<?php

namespace openEtl\Main\Models\Parametros\Radian\AlcanceMandatos;

use openEtl\Main\Models\MainModel;

/**
 * @property int    $ama_id
 * @property string $ama_numero_evento
 * @property string $ama_documento
 * @property string $ama_facultades_sne
 * @property string $ama_facultades_pt
 * @property string $ama_facultades_factor
 * @property string $ama_notas
 * @property string $fecha_vigencia_desde
 * @property string $fecha_vigencia_hasta
 * @property int    $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosRadianAlcanceMandatos extends MainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_radian_alcance_mandatos';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'ama_id';

    /**
     * @var array
     */
    public static $rules = [
        'ama_numero_evento'       => 'required|string|max:5',
        'ama_documento'           => 'required|string|max:255',
        'ama_facultades_sne'      => 'required|string|max:20',
        'ama_facultades_pt'       => 'required|string|max:20',
        'ama_facultades_factor'   => 'required|string|max:20',
        'ama_notas'               => 'required|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'ama_numero_evento'       => 'nullable|string|max:5',
        'ama_documento'           => 'nullable|string|max:255',
        'ama_facultades_sne'      => 'nullable|string|max:20',
        'ama_facultades_pt'       => 'nullable|string|max:20',
        'ama_facultades_factor'   => 'nullable|string|max:20',
        'ama_notas'               => 'nullable|string|max:255',
        'fecha_vigencia_desde'    => 'nullable|string',
        'fecha_vigencia_hasta'    => 'nullable|string',
        'estado'                  => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'ama_numero_evento',
        'ama_documento',
        'ama_facultades_sne',
        'ama_facultades_pt',
        'ama_facultades_factor',
        'ama_notas',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'ama_id',
        'ama_numero_evento',
        'ama_documento',
        'ama_facultades_sne',
        'ama_facultades_pt',
        'ama_facultades_factor',
        'ama_notas',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
