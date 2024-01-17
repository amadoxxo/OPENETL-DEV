<?php

namespace App\Http\Modulos\Parametros\PartidasArancelarias;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\PartidasArancelarias\MainParametrosPartidaArancelaria;

class ParametrosPartidaArancelaria extends MainParametrosPartidaArancelaria {
    protected $visible = [
        'par_id',
        'par_codigo', 
        'par_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    // INICIO RELACION
    /**
     * Ralación con el modelo usuario.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }
    // FIN RELACION
}