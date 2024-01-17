<?php

namespace App\Http\Modulos\Parametros\TarifasImpuesto;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use openEtl\Main\Models\Parametros\TarifasImpuesto\MainParametrosTarifaImpuesto;

class ParametrosTarifaImpuesto extends MainParametrosTarifaImpuesto {
    protected $visible = [
        'tim_id',
        'tri_id',
        'tim_tarifa',
        'tim_porcentaje',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getTributo'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo usuario.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }

    /**
     * Relación con el modelo tributo.
     * 
     * @return BelongsTo
     */
    public function getTributo() {
        return $this->belongsTo(ParametrosTributo::class, 'tri_id');
    }
    // FIN RELACIONES
}