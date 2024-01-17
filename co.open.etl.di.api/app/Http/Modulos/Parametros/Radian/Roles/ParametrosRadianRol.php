<?php

namespace App\Http\Modulos\Parametros\Radian\Roles;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\Radian\Roles\MainParametrosRadianRol;

class ParametrosRadianRol extends MainParametrosRadianRol {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'rol_id',
        'rol_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion'
    ];

    /**
     * Relación con el modelo usuario de creación.
     * 
     * @var BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }
}