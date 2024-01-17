<?php

namespace App\Http\Modulos\Parametros\TiposOperacion;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\TiposOperacion\MainParametrosTipoOperacion;

class ParametrosTipoOperacion extends MainParametrosTipoOperacion {
    protected $visible = [
        'top_id',
        'top_codigo', 
        'top_descripcion',
        'top_aplica_para',
        'top_sector',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
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