<?php
namespace App\Http\Modulos\ProyectosEspeciales\DHLExpress\DocumentosCCO;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Unidades\ParametrosUnidad;
use openEtl\Tenant\Models\ProyectosEspeciales\DHLExpress\TenantPryProductoPickupCash;
use App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProducto;

class PryProductoPickupCash extends TenantPryProductoPickupCash {
    protected $visible = [
        'dpr_id',
        'cpr_id',
        'dpr_codigo',
        'dpr_descripcion_uno',
        'dpr_descripcion_dos',
        'und_id',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getClasificacionProducto',
        'getUnidadProducto'
    ];

    // RELACIONES
    /**
     * Relación con la Clasificación de Producto.
     *
     * @return BelongsTo
     */
    public function getClasificacionProducto() {
        return $this->belongsTo(ParametrosClasificacionProducto::class, 'cpr_id');
    }

    /**
     * Relación con la unidad de producto.
     *
     * @return BelongsTo
     */
    public function getUnidadProducto() {
        return $this->belongsTo(ParametrosUnidad::class, 'und_id');
    }
    // FIN RELACIONES
}
