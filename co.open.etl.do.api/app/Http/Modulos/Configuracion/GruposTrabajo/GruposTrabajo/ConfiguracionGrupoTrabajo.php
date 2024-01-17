<?php

namespace App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Configuracion\GruposTrabajo\ConfiguracionGruposTrabajo\TenantConfiguracionGrupoTrabajo;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionGrupoTrabajo extends TenantConfiguracionGrupoTrabajo {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'gtr_id',
        'ofe_id',
        'gtr_codigo',
        'gtr_nombre',
        'gtr_correos_notificacion',
        'gtr_servicio',
        'gtr_por_defecto',
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
     * Relación con el modelo del Oferente.
     * 
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }
    // FIN RELACIONES
}