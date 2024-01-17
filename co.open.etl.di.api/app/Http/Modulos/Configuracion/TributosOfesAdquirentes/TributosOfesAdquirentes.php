<?php

namespace App\Http\Modulos\Configuracion\TributosOfesAdquirentes;

use openEtl\Tenant\Models\Configuracion\TributosOfesAdquirentes\TenantConfiguracionTributosOfesAdquirentes;

/**
 * Clase gestora de tributos para oferentes y adquirentes
 *
 * Class TributosOfesAdquirentes
 * @package App\Http\Modulos\Configuracion\TributosOfesAdquirentes
 */
class TributosOfesAdquirentes extends TenantConfiguracionTributosOfesAdquirentes {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'toa_id',
        'ofe_id',
        'adq_id',
        'tri_id',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
