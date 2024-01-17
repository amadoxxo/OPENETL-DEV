<?php

namespace App\Http\Modulos\Parametros\PreciosReferencia;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\PreciosReferencia\MainParametrosPrecioReferencia;

class ParametrosPrecioReferencia extends MainParametrosPrecioReferencia {
    protected $visible = [
        'pre_id',
        'pre_codigo', 
        'pre_descripcion',
        'usuario_creacion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    // INICIO RELACION

    /**
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }
    // FIN RELACION
}