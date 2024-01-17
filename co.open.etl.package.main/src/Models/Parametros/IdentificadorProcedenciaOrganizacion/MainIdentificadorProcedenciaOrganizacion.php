<?php

namespace openEtl\Main\Models\Parametros\IdentificadorProcedenciaOrganizacion;

use openEtl\Main\Models\MainModel;

class MainIdentificadorProcedenciaOrganizacion extends MainModel {
    /**
     * Tabla relacionada con el modelo.
     *
     * @var string
     */
    protected $table = 'etl_identificador_procedencia_organizacion';

    /**
     * Llave primaria de la tabla.
     *
     * @var string
     */
    protected $primaryKey = 'ipo_id';

    /**
     * Reglas de validación para el recurso crear.
     *
     * @var array
     */
    public static $rules = [
        'ipo_codigo'                    => 'required|string|max:10',
        'ipo_descripcion'               => 'required|string|max:200',
        'fecha_vigencia_desde'          => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta'          => 'nullable|date_format:Y-m-d H:i:s'
    ];

    /**
     * Reglas de validación para el recurso actualizar.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'ipo_codigo'                    => 'nullable|string|max:10',
        'ipo_descripcion'               => 'nullable|string|max:200',
        'fecha_vigencia_desde'          => 'nullable|date_format:Y-m-d H:i:s',
        'fecha_vigencia_hasta'          => 'nullable|date_format:Y-m-d H:i:s',
        'estado'                        => 'nullable|string|in:ACTIVO,INACTIVO',
    ];

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'ipo_codigo',
        'ipo_descripcion',
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
        'ipo_id',
        'ipo_codigo',
        'ipo_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
