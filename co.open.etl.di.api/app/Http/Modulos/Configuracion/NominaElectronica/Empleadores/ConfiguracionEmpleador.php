<?php

namespace App\Http\Modulos\Configuracion\NominaElectronica\Empleadores;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Configuracion\NominaElectronica\Empleadores\TenantConfiguracionEmpleador;
use App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologico;

class ConfiguracionEmpleador extends TenantConfiguracionEmpleador {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'emp_id',
        'tdo_id',
        'emp_identificacion',
        'emp_razon_social',
        'emp_primer_apellido',
        'emp_segundo_apellido',
        'emp_primer_nombre',
        'emp_otros_nombres',
        'pai_id',
        'dep_id',
        'mun_id',
        'emp_direccion',
        'emp_telefono',
        'emp_web',
        'emp_correo',
        'emp_twitter',
        'emp_facebook',
        'sft_id',
        'emp_archivo_certificado',
        'emp_password_certificado',
        'emp_vencimiento_certificado',
        'emp_ticket_vencimiento',
        'bdd_id_rg',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getProveedorTecnologico',
        'getBaseDatosRg'
    ];

    /**
     * Relación con el modelo usuario.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }

    /**
     * Relación con el Proveedor Tecnológico.
     * 
     * @return BelongsTo
     */
    public function getProveedorTecnologico() {
        return $this->belongsTo(ConfiguracionSoftwareProveedorTecnologico::class, 'sft_id')->select([
            'sft_id',
            'sft_identificador',
            'sft_nit_proveedor_tecnologico',
            'sft_razon_social_proveedor_tecnologico'
        ]);
    }

    /**
     * Relación con el modelo Base Datos.
     *
     * @return BelongsTo
     */
    public function getBaseDatosRg() {
        return $this->belongsTo(AuthBaseDatos::class, 'bdd_id_rg', 'bdd_id');
    }
}
