<?php

namespace App\Http\Modulos\Parametros\ResponsabilidadesFiscales;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\ResponsabilidadesFiscales\MainParametrosResponsabilidadFiscal;

class ParametrosResponsabilidadFiscal extends MainParametrosResponsabilidadFiscal {
    protected $visible = [
        'ref_id',
        'ref_codigo',
        'ref_descripcion',
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