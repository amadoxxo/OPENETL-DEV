<?php

namespace App\Http\Modulos\Parametros\Tributos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\Tributos\MainParametrosTributo;

class ParametrosTributo extends MainParametrosTributo {
    protected $visible = [
        'tri_id',
        'tri_codigo',
        'tri_nombre',
        'tri_tipo',
        'tri_descripcion',
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
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }
    // FIN RELACION
}
