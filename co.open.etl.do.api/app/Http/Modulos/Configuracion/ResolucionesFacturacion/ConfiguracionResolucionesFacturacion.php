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
        'rfa_dias_aviso',
        'rfa_consecutivos_aviso',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'usuario_creacion',
        'estado',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];

    // INICIO RELACION
    /**
     * Relación con el usuario de creación.
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
     * Relación con el modelo Obligado a Facturar Electrónicamente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }
    // FIN RELACION
}
