<?php

namespace App\Http\Modulos\Parametros\ClasificacionProductos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\ClasificacionProductos\MainParametrosClasificacionProducto;


class ParametrosClasificacionProducto extends MainParametrosClasificacionProducto {
    protected $visible = [
        'cpr_id',
        'cpr_codigo', 
        'cpr_nombre', 
        'cpr_identificador',
        'cpr_descripcion',
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
