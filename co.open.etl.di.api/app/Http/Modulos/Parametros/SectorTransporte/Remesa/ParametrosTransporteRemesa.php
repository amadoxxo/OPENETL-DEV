<?php

namespace App\Http\Modulos\Parametros\SectorTransporte\Remesa;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\SectorTransporte\Remesa\MainParametrosTransporteRemesa;

class ParametrosTransporteRemesa extends MainParametrosTransporteRemesa {
    protected $visible = [
        'trm_id',
        'trm_codigo',
        'trm_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion'
    ];

    // INICIO RELACIÓN
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
    // FIN RELACION
}