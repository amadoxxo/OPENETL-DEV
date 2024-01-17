<?php
namespace App\Http\Modulos\Documentos\EtlEventosNotificacionDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use openEtl\Tenant\Models\Documentos\EtlEventosNotificacionDocumentosDaop\TenantEtlEventoNotificacionDocumentoDaop;

class EtlEventoNotificacionDocumentoDaop extends TenantEtlEventoNotificacionDocumentoDaop {
    protected $visible = [
        'evt_id',
        'cdo_id',
        'evt_evento',
        'evt_correos',
        'evt_amazonses_id',
        'evt_fecha_hora',
        'evt_json',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getCabeceraDocumentosDaop'
    ];

    // RELACIONES
    /**
     * Relación con EtlCabeceraDocumentosDop.
     * 
     * @return BelongsTo
     */
    public function getCabeceraDocumentosDaop() {
        return $this->belongsTo(EtlCabeceraDocumentoDaop::class, 'cdo_id');
    }
}
