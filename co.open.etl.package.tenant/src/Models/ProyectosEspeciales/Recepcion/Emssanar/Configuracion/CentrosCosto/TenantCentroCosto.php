<?php

namespace openEtl\Tenant\Models\ProyectosEspeciales\Recepcion\Emssanar\Configuracion\CentrosCosto;

use openEtl\Tenant\Models\TenantMainModel;

/**
 * @property int $cco_id
 * @property string $cco_codigo
 * @property string $cco_descripcion
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class TenantCentroCosto extends TenantMainModel {
    /**
     * Tabla asociada con el modelo.
     * 
     * @var string
     */
    protected $table = 'pry_centros_costo';

    /**
     * Llave primaria del modelo.
     * 
     * @var string
     */
    protected $primaryKey = 'cco_id';

    /**
     * Reglas de creación en el modelo.
     * 
     * @var array
     */
    public static $rules = [
        'cco_codigo'      => 'required|string|max:20',
        'cco_descripcion' => 'required|string|max:255',
    ];

    /**
     * Reglas de actualización en el modelo.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'cco_codigo'      => 'nullable|string|max:20',
        'cco_descripcion' => 'nullable|string|max:255',
        'estado'          => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * Atributos asignables en masa.
     * 
     * @var array
     */
    protected $fillable = [
        'cco_id',
        'cco_codigo',
        'cco_descripcion',
        'usuario_creacion',
        'estado'
    ];

    /**
     * Atributos visibles del modelo.
     * 
     * @var array
     */
    protected $visible = [
        'cco_id',
        'cco_codigo',
        'cco_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
