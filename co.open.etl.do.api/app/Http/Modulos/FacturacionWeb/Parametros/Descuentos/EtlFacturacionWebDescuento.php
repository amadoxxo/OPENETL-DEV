<?php
namespace App\Http\Modulos\FacturacionWeb\Parametros\Descuentos;

use openEtl\Tenant\Models\Documentos\FacturacionWeb\EtlFacturacionWebDescuentos\TenantEtlFacturacionWebDescuento;

class EtlFacturacionWebDescuento extends TenantEtlFacturacionWebDescuento {
    protected $visible = [
        'dmd_id',
        'ofe_id',
        'dmd_codigo',
        'dmd_descripcion',
        'dmd_porcentaje',
        'cde_id',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getCodigoDescuento',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];
}