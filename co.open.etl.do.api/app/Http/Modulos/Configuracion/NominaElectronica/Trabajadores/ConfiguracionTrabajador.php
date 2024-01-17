<?php

namespace App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Configuracion\NominaElectronica\Trabajadores\TenantConfiguracionTrabajador;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;

class ConfiguracionTrabajador extends TenantConfiguracionTrabajador {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'tra_id',
        'emp_id',
        'tdo_id',
        'tra_identificacion',
        'tra_primer_apellido',
        'tra_segundo_apellido',
        'tra_primer_nombre',
        'tra_otros_nombres',
        'pai_id',
        'dep_id',
        'mun_id',
        'tra_direccion',
        'tra_telefono',
        'tra_codigo',
        'tra_fecha_ingreso',
        'tra_fecha_retiro',
        'tra_sueldo',
        'ntc_id',
        'ntt_id',
        'nst_id',
        'tra_alto_riesgo',
        'tra_salario_integral',
        'tra_entidad_bancaria',
        'tra_tipo_cuenta',
        'tra_numero_cuenta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getEmpleador'
    ];

    // INICIO RELACIONES
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
     * Relación con el empleador.
     * 
     * @return BelongsTo
     */
    public function getEmpleador() {
        return $this->belongsTo(ConfiguracionEmpleador::class, 'emp_id')->select([
            'emp_id',
            'emp_identificacion',
            'emp_razon_social'
        ]);
    }
    // FIN RELACIONES
}
