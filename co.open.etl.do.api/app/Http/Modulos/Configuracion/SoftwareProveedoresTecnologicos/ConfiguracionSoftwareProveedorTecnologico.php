<?php

namespace App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos;

use openEtl\Tenant\Models\Configuracion\SoftwareProveedoresTecnologicos\TenantConfiguracionSoftwareProveedorTecnologico;

class ConfiguracionSoftwareProveedorTecnologico extends TenantConfiguracionSoftwareProveedorTecnologico {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'sft_id',
        'sft_identificador',
        'sft_pin',
        'sft_nombre',
        'sft_fecha_registro',
        'add_id',
        'sft_nit_proveedor_tecnologico',
        'sft_testsetid',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}