<?php
namespace App\Http\Modulos\FacturacionWeb\Parametros\Cargos;

use openEtl\Tenant\Models\Documentos\FacturacionWeb\EtlFacturacionWebCargos\TenantEtlFacturacionWebCargo;

class EtlFacturacionWebCargo extends TenantEtlFacturacionWebCargo {
    protected $visible = [
        'dmc_id',
        'ofe_id',
        'dmc_codigo',
        'dmc_descripcion',
        'dmc_porcentaje',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];
}