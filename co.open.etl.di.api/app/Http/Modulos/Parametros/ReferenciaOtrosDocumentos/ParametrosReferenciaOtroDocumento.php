<?php

namespace App\Http\Modulos\Parametros\ReferenciaOtrosDocumentos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\ReferenciaOtrosDocumentos\MainParametrosReferenciaOtroDocumento;

class ParametrosReferenciaOtroDocumento extends MainParametrosReferenciaOtroDocumento {
    protected $visible = [
        'rod_id',
        'rod_codigo', 
        'rod_descripcion',
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