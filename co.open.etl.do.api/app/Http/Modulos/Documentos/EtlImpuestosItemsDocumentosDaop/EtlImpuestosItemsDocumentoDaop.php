<?php

namespace App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop\EtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use openEtl\Tenant\Models\Documentos\EtlImpuestosItemsDocumentosDaop\TenantEtlImpuestoItemDocumentoDaop;

class EtlImpuestosItemsDocumentoDaop extends TenantEtlImpuestoItemDocumentoDaop {
    protected $visible = [
        'iid_id',
        'cdo_id',
        'ddo_id',
        'tri_id',
        'iid_tipo',
        'iid_nombre_figura_tributaria',
        'iid_base',
        'iid_base_moneda_extranjera',
        'iid_porcentaje',
        'iid_cantidad',
        'und_id',
        'iid_valor_unitario',
        'iid_valor_unitario_moneda_extranjera',
        'iid_valor',
        'iid_valor_moneda_extranjera',
        'iid_redondeo_agregado',
        'iid_redondeo_agregado_moneda_extranjera',
        'iid_motivo_exencion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'fecha_actualizacion',
        'getCabeceraDocumentosDaop',
        'getDetalleDocumentosDaop'
    ];

    // INICIO RELACIONES
    /**
     * Relación con EtlCabeceraDocumentosDaop.
     * 
     * @return BelongsTo
     */
    public function getCabeceraDocumentosDaop() {
        return $this->belongsTo(EtlCabeceraDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Relación con EtlDetalleDocumentosDaop.
     *
     * @return BelongsTo
     */
    public function getDetalleDocumentosDaop() {
        return $this->belongsTo(EtlDetalleDocumentoDaop::class, 'ddo_id');
    }
    // FIN RELACIONES
}
