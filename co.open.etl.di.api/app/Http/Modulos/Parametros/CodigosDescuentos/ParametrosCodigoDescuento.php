<?php

namespace App\Http\Modulos\Parametros\CodigosDescuentos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\CodigosDescuentos\MainParametrosCodigoDescuento;

class ParametrosCodigoDescuento extends MainParametrosCodigoDescuento {
    protected $visible = [
        'cde_id',
        'cde_codigo', 
        'cde_descripcion',
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