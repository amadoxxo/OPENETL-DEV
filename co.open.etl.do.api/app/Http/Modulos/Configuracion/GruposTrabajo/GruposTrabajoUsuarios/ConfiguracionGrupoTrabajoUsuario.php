<?php

namespace App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use openEtl\Tenant\Models\Configuracion\GruposTrabajo\ConfiguracionGruposTrabajoUsuarios\TenantConfiguracionGrupoTrabajoUsuario;

class ConfiguracionGrupoTrabajoUsuario extends TenantConfiguracionGrupoTrabajoUsuario {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'gtu_id',
        'gtr_id',
        'usu_id',
        'gtu_usuario_gestor',
        'gtu_usuario_validador',
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
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     */
    public function getUsuario() {
        return $this->belongsTo(User::class, 'usu_id');
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