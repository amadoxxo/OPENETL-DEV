<?php

namespace App\Http\Modulos\Parametros\CodigosPostales;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\CodigosPostales\MainParametrosCodigoPostal;

class ParametrosCodigoPostal extends MainParametrosCodigoPostal {
    protected $visible = [
        'cpo_id',
        'cpo_codigo', 
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
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
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }
    // FIN RELACION
}