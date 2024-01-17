<?php

namespace App\Http\Modulos\Parametros\SectorSalud\DocumentoReferenciado;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\SectorSalud\DocumentoReferenciado\MainParametrosSaludDocumentoReferenciado;

class ParametrosSaludDocumentoReferenciado extends MainParametrosSaludDocumentoReferenciado {
    protected $visible = [
        'sdr_id',
        'sdr_codigo',
        'sdr_descripcion',
        'sdr_observacion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion'
    ];

    // INICIO RELACION
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