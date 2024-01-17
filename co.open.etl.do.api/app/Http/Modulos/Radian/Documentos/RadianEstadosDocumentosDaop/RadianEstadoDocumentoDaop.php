<?php

namespace App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Radian\RadianEstadosDocumentosDaop\TenantRadianEstadoDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaop;

class RadianEstadoDocumentoDaop extends TenantRadianEstadoDocumentoDaop {
    protected $visible = [
        'est_id',
        'cdo_id',
        'est_estado',
        'est_resultado',
        'est_mensaje_resultado',
        'est_object',
        'est_correos',
        'est_motivo_rechazo',
        'est_informacion_adicional',
        'age_id',
        'age_usu_id',
        'est_inicio_proceso',
        'est_fin_proceso',
        'est_tiempo_procesamiento',
        'est_ejecucion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getRadCabeceraDocumentosDaop'
    ];

    //INICIO RELACIONES

    /**
     * Relación con el modelo de Radián Actores.
     * 
     * @return BelongsTo
     */
    public function getRadCabeceraDocumentosDaop() {
        return $this->belongsTo(RadianCabeceraDocumentoDaop::class, 'cdo_id');
    }
    
    //FIN RELACIONES
}
