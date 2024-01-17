<?php

namespace App\Http\Modulos\Configuracion\TributosOfesAdquirentes;

use App\Http\Models\User;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use openEtl\Tenant\Models\Configuracion\TributosOfesAdquirentes\TenantConfiguracionTributosOfesAdquirentes;

/**
 * Clase gestora de tributos para oferentes y adquirentes
 *
 * Class TributosOfesAdquirentes
 * @package App\Http\Modulos\Configuracion\TributosOfesAdquirentes
 */
class TributosOfesAdquirentes extends TenantConfiguracionTributosOfesAdquirentes
{
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
        'fecha_actualizacion',
        'getDetalleTributo'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo usuario.
     * 
     * @var Model
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }

    /**
     * Relación con el modelo usuario.
     * 
     * @var Model
     */
    public function getDetalleTributo() {
        return $this->belongsTo(ParametrosTributo::class, 'tri_id');
    }
    // FIN RELACIONES



}