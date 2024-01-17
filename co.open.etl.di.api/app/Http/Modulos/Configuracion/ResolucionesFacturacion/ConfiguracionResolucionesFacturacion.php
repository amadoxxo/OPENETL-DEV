<?php

namespace App\Http\Modulos\Configuracion\ResolucionesFacturacion;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Configuracion\ResolucionesFacturacion\TenantConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionResolucionesFacturacion extends TenantConfiguracionResolucionesFacturacion {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'rfa_id',
        'ofe_id',
        'rfa_id',
        'rfa_resolucion',
        'rfa_prefijo',
        'rfa_clave_tecnica',
        'rfa_tipo',
        'rfa_fecha_desde',
        'rfa_fecha_hasta',
        'rfa_consecutivo_inicial',
        'rfa_consecutivo_final',
        'cdo_control_consecutivos',
        'cdo_consecutivo_provisional',
        'fecha_creacion',
        'fecha_modificacion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];

    /**
     * Relación con el modelo Obligado a Facturar Electrónicamente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }
}