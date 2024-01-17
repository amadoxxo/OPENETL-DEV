<?php
namespace App\Http\Modulos\Documentos\EtlConsecutivosDocumentos;

use openEtl\Tenant\Models\Documentos\EtlConsecutivosDocumentos\TenantEtlConsecutivoDocumento;

class EtlConsecutivoDocumento extends TenantEtlConsecutivoDocumento {
    protected $visible = [
        'cdo_id',
        'ofe_id',
        'rfa_id',
        'cdo_tipo_consecutivo',
        'cdo_periodo',
        'rfa_prefijo',
        'cdo_consecutivo',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
