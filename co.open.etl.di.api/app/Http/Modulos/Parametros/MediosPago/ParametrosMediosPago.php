<?php

namespace App\Http\Modulos\Parametros\MediosPago;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\MediosPago\MainParametrosMedioPago;

class ParametrosMediosPago extends MainParametrosMedioPago {
    protected $visible = [
        'mpa_id',
        'mpa_codigo',
        'mpa_descripcion',
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