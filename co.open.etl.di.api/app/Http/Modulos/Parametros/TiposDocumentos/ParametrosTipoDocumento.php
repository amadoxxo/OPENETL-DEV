<?php

namespace App\Http\Modulos\Parametros\TiposDocumentos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\TiposDocumentos\MainParametrosTipoDocumento;

class ParametrosTipoDocumento extends MainParametrosTipoDocumento {
    protected $visible = [
        'tdo_id',
        'tdo_codigo', 
        'tdo_descripcion',
        'tdo_aplica_para',
        'usuario_creacion',
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