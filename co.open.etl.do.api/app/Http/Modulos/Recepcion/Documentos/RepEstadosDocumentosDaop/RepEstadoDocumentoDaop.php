<?php

namespace App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop;

use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use openEtl\Tenant\Models\Recepcion\Documentos\RepEstadosDocumentosDaop\TenantRepEstadoDocumentoDaop;

class RepEstadoDocumentoDaop extends TenantRepEstadoDocumentoDaop {
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
        'getRepCabeceraDocumentosDaop'
    ];

    // RELACIONES
    /**
     * Relación con RepCabeceraDocumentosDop
     */
    public function getRepCabeceraDocumentosDaop() {
        return $this->belongsTo(RepCabeceraDocumentoDaop::class, 'cdo_id');
    }
}
