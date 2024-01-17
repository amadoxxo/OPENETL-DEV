<?php

namespace App\Http\Modulos\Configuracion\ObservacionesGeneralesFactura;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use openEtl\Tenant\Models\Configuracion\ObservacionesGeneralesFactura\TenantConfiguracionObservacionesGeneralesFactura;

class ConfiguracionObservacionesGeneralesFactura extends TenantConfiguracionObservacionesGeneralesFactura {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'ogf_id', 
        'ofe_id',
        'ogf_observacion',
        'fecha_creacion',
        'fecha_modificacion',
        'usuario_creacion', 
        'estado',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];

    // INICIO RELACION
    /**
     * Relación con el modelo Obligado a Facturar Electrónicamente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }
    // FIN RELACION
}
