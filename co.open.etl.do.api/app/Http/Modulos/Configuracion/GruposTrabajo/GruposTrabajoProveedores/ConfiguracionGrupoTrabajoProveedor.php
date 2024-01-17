<?php

namespace App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use openEtl\Tenant\Models\Configuracion\GruposTrabajo\ConfiguracionGruposTrabajoProveedores\TenantConfiguracionGrupoTrabajoProveedor;

class ConfiguracionGrupoTrabajoProveedor extends TenantConfiguracionGrupoTrabajoProveedor {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'gtp_id',
        'gtr_id',
        'pro_id',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo usuario de creación.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }

    /**
     * Relación con el modelo proveedor.
     *
     * @return BelongsTo
     */
    public function getProveedor() {
        return $this->belongsTo(ConfiguracionProveedor::class, 'pro_id');
    }

    /**
     * Relación con el modelo grupo trabajo.
     *
     * @return BelongsTo
     */
    public function getGrupoTrabajo() {
        return $this->belongsTo(ConfiguracionGrupoTrabajo::class, 'gtr_id');
    }
    // FIN RELACIONES
}