<?php

namespace App\Http\Modulos\Configuracion\ConsecutivosArchivosEnviadosOfes;

use openEtl\Tenant\Models\Configuracion\ConsecutivosArchivosEnviadosOfes\TenantConfiguracionConsecutivoArchivosEnviadosOfe;

class ConfiguracionConsecutivoArchivosEnviadosOfe extends TenantConfiguracionConsecutivoArchivosEnviadosOfe {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'cae_id',
        'ofe_id',
        'act_id',
        'emp_id',
        'cae_anno',
        'cae_fv',
        'cae_nc',
        'cae_nd',
        'cae_ds',
        'cae_ar',
        'cae_ad',
        'cae_z',
        'cae_nie',
        'cae_niae',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
