<?php

namespace App\Http\Modulos\Parametros\Unidades;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\Unidades\MainParametrosUnidad;

class ParametrosUnidad extends MainParametrosUnidad {
    protected $visible = [
        'und_id',
        'und_codigo', 
        'und_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    // INICIO RELACIÓN
    /**
     * Ralación con el modelo usuario.
     *
     * @return                                                                                              
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }
    // FIN RELACION
}