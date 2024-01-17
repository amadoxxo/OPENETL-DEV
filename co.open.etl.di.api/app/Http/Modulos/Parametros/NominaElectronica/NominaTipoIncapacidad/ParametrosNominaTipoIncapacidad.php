<?php

namespace App\Http\Modulos\Parametros\NominaElectronica\NominaTipoIncapacidad;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\NominaElectronica\NominaTipoIncapacidad\MainParametrosNominaTipoIncapacidad;

class ParametrosNominaTipoIncapacidad extends MainParametrosNominaTipoIncapacidad {

    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'nti_id',
        'nti_codigo',
        'nti_descripcion',
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
