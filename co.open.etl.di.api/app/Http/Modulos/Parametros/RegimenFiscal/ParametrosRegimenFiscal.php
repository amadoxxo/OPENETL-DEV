<?php

namespace App\Http\Modulos\Parametros\RegimenFiscal;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\RegimenFiscal\MainParametrosRegimenFiscal;

class ParametrosRegimenFiscal extends MainParametrosRegimenFiscal {
    protected $visible = [
        'rfi_id',
        'rfi_codigo', 
        'rfi_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    // INICIO RELACION

    /**
     * Ralaciòn con el modelo usuario.
     *
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }
    // FIN RELACION
}