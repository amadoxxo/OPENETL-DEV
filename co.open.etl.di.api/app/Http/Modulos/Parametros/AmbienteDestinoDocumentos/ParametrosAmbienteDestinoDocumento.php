<?php

namespace App\Http\Modulos\Parametros\AmbienteDestinoDocumentos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\AmbienteDestinoDocumentos\MainParametrosAmbienteDestinoDocumento;

class ParametrosAmbienteDestinoDocumento extends MainParametrosAmbienteDestinoDocumento {
    protected $visible = [
        'add_id',
        'add_codigo', 
        'add_descripcion',
        'add_url',
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
    // FIN RELACIÓN
}