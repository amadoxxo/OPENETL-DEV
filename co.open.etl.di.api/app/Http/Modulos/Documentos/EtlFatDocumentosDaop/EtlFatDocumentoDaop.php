<?php
namespace App\Http\Modulos\Documentos\EtlFatDocumentosDaop;

use openEtl\Tenant\Models\Documentos\EtlFatDocumentosDaop\TenantEtlFatDocumentoDaop;

class EtlFatDocumentoDaop extends TenantEtlFatDocumentoDaop {
    /**
     * @var array
     */
    protected $visible = [
        'cdo_id',
        'cdo_clasificacion',
        'tde_id',
        'top_id',
        'cdo_lote',
        'ofe_id',
        'adq_id',
        'rfa_id',
        'rfa_prefijo',
        'cdo_consecutivo',
        'cdo_fecha',
        'cdo_cufe',
        'cdo_fecha_validacion_dian',
        'cdo_fecha_inicio_consulta_eventos',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
