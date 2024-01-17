<?php

namespace App\Http\Modulos\Parametros\CondicionesEntrega;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\CondicionesEntrega\MainParametrosCondicionEntrega;

class ParametrosCondicionEntrega extends MainParametrosCondicionEntrega {
    protected $visible = [
        'cen_id',
        'cen_codigo', 
        'cen_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    // INICIO RELACIóN
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