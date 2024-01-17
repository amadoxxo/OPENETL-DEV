<?php

namespace App\Http\Modulos\Configuracion\Contactos;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use openEtl\Tenant\Models\Configuracion\Contactos\TenantConfiguracionContacto;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionContacto extends TenantConfiguracionContacto {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'con_id',
        'ofe_id',
        'adq_id',
        'con_nombre',
        'con_direccion',
        'con_telefono',
        'con_correo',
        'con_observaciones',
        'con_tipo',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionAdquirente',
    ];

    // INICIO RELACIONES
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
     * Relación con el modelo OFE.
     * 
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id')->select([
            'ofe_id',
            'sft_id',
            'tdo_id',
            'toj_id',
            'ofe_identificacion',
            'ofe_razon_social',
            'ofe_nombre_comercial',
            'ofe_primer_apellido',
            'ofe_segundo_apellido',
            'ofe_primer_nombre',
            'ofe_otros_nombres',
        ]);
    }
    /**
     * Relación con el modelo Adquirentes.
     * 
     * @return HasMany
     */
    public function getConfiguracionAdquirente() {
        return $this->hasMany(ConfiguracionAdquirente::class, 'ofe_id')->select([
            'adq_id',
            'ofe_id',
            'adq_identificacion',
            'adq_razon_social',
            'adq_nombre_comercial',
            'adq_primer_apellido',
            'adq_segundo_apellido',
            'adq_primer_nombre',
            'adq_otros_nombres',
            'adq_tipo_adquirente',
            'adq_tipo_autorizado',
            'adq_tipo_responsable_entrega',
        ]);
    }
    // FIN RELACIONES
}
