<?php

namespace App\Http\Modulos\Configuracion\Proveedores;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\parametros\paises\ParametrosPais;
use App\Http\Modulos\parametros\municipios\ParametrosMunicipio;
use App\Http\Modulos\parametros\departamentos\ParametrosDepartamento;
use openEtl\Tenant\Models\Configuracion\Proveedores\TenantConfiguracionProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionProveedor extends TenantConfiguracionProveedor {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'pro_id',
        'ofe_id',
        'tdo_id',
        'tpe_id',
        'pro_identificacion',
        'pro_razon_social',
        'pro_nombre_comercial',
        'pro_primer_apellido',
        'pro_segundo_apellido',
        'pro_primer_nombre',
        'pro_otros_nombres',
        'pai_id',
        'dep_id',
        'mun_id',
        'pro_direccion',
        'pro_localidad',
        'pro_telefono',
        'pro_correo',
        'tre_id',
        'usu_relaciones',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreador',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getParametroTipoDocumento',
        'getParametroTipoPersona',
        'getParametroPais',
        'getParametroDepartamento',
        'getParametroMunicipio',
        'getParametroTipoRegimen'
    ];

    /**
     * Obtiene la razón social del proveedor.
     *
     * @param  string $value
     * @return void
     */
    public function getProRazonSocialAttribute($value){
        if(empty($value)){
            return trim($this->pro_primer_nombre.' '.$this->pro_otros_nombres.' '.$this->pro_primer_apellido.' '.$this->pro_segundo_apellido);
        } else {
            return $value;
        }
    }

    // INICIO RELACIONES
    /**
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     */
    public function getUsuarioCreador() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }

    /**
     * Relación con el modelo Obligado a Facturar Electrónicamente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con el modelo Paramentro País.
     *
     * @return BelongsTo
     */
    public function getParametroPais() {
        return $this->belongsTo(ParametrosPais::class, 'pai_id');
    }

    /**
     * Relación con el modelo Departamento.
     *
     * @return BelongsTo
     */
    public function getParametroDepartamento() {
        return $this->belongsTo(ParametrosDepartamento::class, 'dep_id');
    }

    /**
     * Relación con el modelo Municipio.
     *
     * @return BelongsTo
     */
    public function getParametroMunicipio(){
        return $this->belongsTo(ParametrosMunicipio::class, 'mun_id');
    }
    // FIN RELACIONES
}
