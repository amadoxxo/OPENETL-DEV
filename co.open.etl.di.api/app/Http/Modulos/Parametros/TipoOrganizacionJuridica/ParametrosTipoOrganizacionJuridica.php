<?php

namespace App\Http\Modulos\Parametros\TipoOrganizacionJuridica;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\TipoOrganizacionJuridica\MainParametrosTipoOrganizacionJuridica;

class ParametrosTipoOrganizacionJuridica extends MainParametrosTipoOrganizacionJuridica{
    protected $visible = [
        'toj_id',
        'toj_codigo', 
        'toj_descripcion',
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