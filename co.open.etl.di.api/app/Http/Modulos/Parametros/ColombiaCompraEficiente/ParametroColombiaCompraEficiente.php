<?php

namespace App\Http\Modulos\Parametros\ColombiaCompraEficiente;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\ColombiaCompraEficiente\MainParametrosColombiaCompraEficiente;

class ParametroColombiaCompraEficiente extends MainParametrosColombiaCompraEficiente {
    protected $visible = [
        'cce_id',
        'cce_codigo', 
        'cce_descripcion',
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