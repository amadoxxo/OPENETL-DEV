<?php
namespace App\Http\Modulos\Documentos\EtlConsecutivosDocumentos;

class EtlConsecutivoDocumento extends TenantEtlConsecutivoDocumentos {
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
