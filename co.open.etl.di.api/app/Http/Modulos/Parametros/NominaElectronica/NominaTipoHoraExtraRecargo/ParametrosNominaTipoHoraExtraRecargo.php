<?php

namespace App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\MainParametrosNominaTipoHoraExtraRecargo;

class ParametrosNominaTipoHoraExtraRecargo extends MainParametrosNominaTipoHoraExtraRecargo {

    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'nth_id',
        'nth_codigo',
        'nth_descripcion',
        'nth_porcentaje',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
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
}
