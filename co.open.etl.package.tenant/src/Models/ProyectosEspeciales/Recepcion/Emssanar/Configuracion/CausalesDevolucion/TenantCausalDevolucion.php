<?php

namespace openEtl\Tenant\Models\ProyectosEspeciales\Recepcion\Emssanar\Configuracion\CausalesDevolucion;

use openEtl\Tenant\Models\TenantMainModel;

/**
 * @property int $cde_id
 * @property string $cde_descripcion
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class TenantCausalDevolucion extends TenantMainModel {
    /**
     * Tabla asociada con el modelo.
     * 
     * @var string
     */
    protected $table = 'pry_causales_devolucion';

    /**
     * Llave primaria del modelo.
     * 
     * @var string
     */
    protected $primaryKey = 'cde_id';

    /**
     * Reglas de creación en el modelo.
     * 
     * @var array
     */
    public static $rules = [
        'cde_descripcion' => 'required|string|max:255',
    ];

    /**
     * Reglas de actualización en el modelo.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'cde_descripcion' => 'nullable|string|max:255',
        'estado'          => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * Atributos asignables en masa.
     * 
     * @var array
     */
    protected $fillable = [
        'cde_id',
        'cde_descripcion',
        'usuario_creacion',
        'estado'
    ];

    /**
     * Atributos visibles del modelo.
     * 
     * @var array
     */
    protected $visible = [
        'cde_id',
        'cde_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
