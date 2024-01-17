<?php

namespace App\Http\Modulos\Parametros\SectorSalud\DocumentosIdentificacion;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\SectorSalud\DocumentosIdentificacion\MainParametrosSaludDocumentoIdentificacion;

class ParametrosSaludDocumentoIdentificacion extends MainParametrosSaludDocumentoIdentificacion {
    protected $visible = [
        'sdi_id',
        'sdi_codigo',
        'sdi_descripcion',
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