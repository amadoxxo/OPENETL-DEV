<?php

namespace App\Http\Modulos\Parametros\ConceptosCorreccion;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\ConceptosCorreccion\MainParametrosConceptoCorreccion;

class ParametrosConceptoCorreccion extends MainParametrosConceptoCorreccion {
    protected $visible = [
        'cco_id',
        'cco_tipo',
        'cco_codigo', 
        'cco_descripcion',
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
        return $this->belongsTo(User::class, 'usuario_creacion');
    }
    // FIN RELACION
}