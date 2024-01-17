<?php

namespace App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use openEtl\Tenant\Models\Documentos\EtlEstadosDocumentosDaop\TenantEtlEstadoDocumentoDaop;

/**
 * Gestiona los estados de Documentos electrónicos
 *
 * Class EtlEstadosDocumentosDaop
 * @package App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop
 */
class EtlEstadosDocumentoDaop extends TenantEtlEstadoDocumentoDaop
{
    /**
     * @var array
     */
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
        'getCabeceraDocumentosDaop'
    ];

    // INICIO RELACION
    /**
     * Relación con EtlCabeceraDocumentosDoap.
     * 
     * @return BelongsTo
     */
    public function getCabeceraDocumentosDaop() {
        return $this->belongsTo(EtlCabeceraDocumentoDaop::class, 'cdo_id');
    }
    // FIN RELACION
}
