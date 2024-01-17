<?php

namespace App\Http\Modulos\Configuracion\UsuariosEcm;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Configuracion\UsuariosEcm\TenantConfiguracionUsuarioEcm;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionUsuarioEcm extends TenantConfiguracionUsuarioEcm {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'use_id',
        'ofe_id',
        'usu_id',
        'use_rol',
        'fecha_creacion',
        'fecha_modificacion',
        'usuario_creacion',
        'estado',
        'getUsuarioCreacion',
        'getUsuarioEcm',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];

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

    /**
     * Relación con el modelo usuario para el usuario de openECM.
     *
     * @return BelongsTo
     */
    public function getUsuarioEcm() {
        return $this->belongsTo(User::class, 'usu_id')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
            'usu_email',
            'estado'
        ]);
    }

    /**
     * Relación con el modelo del Oferente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }
}
