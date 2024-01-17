<?php

namespace App\Http\Modulos\Configuracion\Contactos;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use openEtl\Tenant\Models\Configuracion\contactos\TenantConfiguracionContacto;
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
        'con_codigo',
        'con_nombre',
        'con_direccion',
        'con_telefono',
        'con_correo',
        'con_observaciones',
        'con_tipo',
        'fecha_creacion',
        'fecha_modificacion',
        'usuario_creacion', 
        'estado',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionAdquirente'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo Obligado a Facturar Electrónicamente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con el modelo del adquirente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionAdquirente() {
        return $this->belongsTo(ConfiguracionAdquirente::class, 'adq_id');
    }
    // FIN RELACIONES
}
