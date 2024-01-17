<?php

namespace openEtl\Tenant\Models\ProyectosEspeciales\Recepcion\Emssanar\Configuracion\CentrosOperaciones;

use openEtl\Tenant\Models\TenantMainModel;

/**
 * @property int $cop_id
 * @property string $cop_descripcion
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class TenantCentroOperacion extends TenantMainModel {
    /**
     * Tabla asociada con el modelo.
     * 
     * @var string
     */
    protected $table = 'pry_centros_operaciones';

    /**
     * Llave primaria del modelo.
     * 
     * @var string
     */
    protected $primaryKey = 'cop_id';

    /**
     * Reglas de creación en el modelo.
     * 
     * @var array
     */
    public static $rules = [
        'cop_descripcion' => 'required|string|max:255',
    ];

    /**
     * Reglas de actualización en el modelo.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'cop_descripcion' => 'nullable|string|max:255',
        'estado'          => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * Atributos asignables en masa.
     * 
     * @var array
     */
    protected $fillable = [
        'cop_id',
        'cop_descripcion',
        'usuario_creacion',
        'estado'
    ];

    /**
     * Atributos visibles del modelo.
     * 
     * @var array
     */
    protected $visible = [
        'cop_id',
        'cop_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
