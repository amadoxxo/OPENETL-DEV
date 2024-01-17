<?php
namespace App\Http\Modulos\Recepcion\Documentos\RepDocumentosAnexosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use openEtl\Tenant\Models\Recepcion\Documentos\RepDocumentosAnexosDaop\TenantRepDocumentoAnexoDaop;

class RepDocumentoAnexoDaop extends TenantRepDocumentoAnexoDaop{
    protected $visible = [
        'dan_id',
        'cdo_id',
        'dan_lote',
        'dan_uuid',
        'dan_tamano',
        'dan_nombre',
        'dan_descripcion',
        'dan_envio_openecm',
        'dan_respuesta_envio_openecm',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getCabeceraDocumentosDaop'
    ];

    // INICIO RELACION
    /**
     * Relación con RepCabeceraDocumentosDaop.
     * 
     * @return BeLongsTo
     */
    public function getCabeceraDocumentosDaop() {
        return $this->belongsTo(RepCabeceraDocumentoDaop::class, 'cdo_id');
    }
    // FIN RELACION
}
