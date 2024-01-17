<?php
namespace App\Http\Modulos\Recepcion\Documentos\RepFatDocumentosDaop;

use openEtl\Tenant\Models\Recepcion\Documentos\RepFatDocumentosDaop\TenantRepFatDocumentoDaop;

class RepFatDocumentoDaop extends TenantRepFatDocumentoDaop {
    protected $visible = [
        'cdo_id',
        'cdo_clasificacion',
        'gtr_id',
        'tde_id',
        'top_id',
        'cdo_lote',
        'ofe_id',
        'pro_id',
        'rfa_prefijo',
        'cdo_consecutivo',
        'cdo_fecha',
        'cdo_cufe',
        'cdo_fecha_validacion_dian',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
