<?php

namespace App\Http\Modulos\Configuracion\Adquirentes;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\parametros\paises\ParametrosPais;
use App\Http\Modulos\parametros\municipios\ParametrosMunicipio;
use App\Http\Modulos\Configuracion\Contactos\ConfiguracionContacto;
use App\Http\Modulos\parametros\departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\parametros\TiposDocumentos\ParametrosTipoDocumento;
use openEtl\Tenant\Models\Configuracion\Adquirentes\TenantConfiguracionAdquirente;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionAdquirente extends TenantConfiguracionAdquirente {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'adq_id',
        'ofe_id',
        'adq_identificacion',
        'adq_id_personalizado',
        'adq_informacion_personalizada',
        'adq_razon_social',
        'adq_nombre_comercial',
        'adq_primer_apellido',
        'adq_segundo_apellido',
        'adq_primer_nombre',
        'adq_otros_nombres',
        'adq_tipo_adquirente',
        'adq_tipo_autorizado',
        'adq_tipo_responsable_entrega',
        'adq_tipo_vendedor_ds',
        'tdo_id',
        'toj_id',
        'ipv_id',
        'pai_id',
        'dep_id',
        'mun_id',
        'cpo_id',
        'adq_direccion',
        'adq_telefono',
        'pai_id_domicilio_fiscal',
        'dep_id_domicilio_fiscal',
        'mun_id_domicilio_fiscal',
        'cpo_id_domicilio_fiscal',
        'adq_direccion_domicilio_fiscal',
        'adq_nombre_contacto',
        'adq_fax',
        'adq_notas',
        'adq_correo',
        'adq_correos_notificacion',
        'rfi_id',
        'ref_id',
        'adq_matricula_mercantil',
        'adq_campos_representacion_grafica',
        'tat_id',
        'adq_reenvio_notificacion_contingencia',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getParametroDepartamento',
        'getParametroPais',
        'getParametroMunicipio',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getParametroTipoDocumento',
        'getParametroRegimenFiscal',
        'getUsuarioCreador',
        'getContactos'
    ];

    /**
     * Obtiene la razon social del adquirente.
     *
     * @param  mixed $value
     * @return void
     */
    public function getAdqRazonSocialAttribute($value){
        if(empty($value)){
            return str_replace('  ', ' ', trim($this->adq_primer_nombre.' '.$this->adq_otros_nombres.' '.$this->adq_primer_apellido.' '.$this->adq_segundo_apellido));
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
        return $this->belongsTo(User::class, 'usu_identificacion');
    }

    /**
     * Relación con el modelo Obligado a Facturar Electronicamente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con el modelo Tipo Documento.
     *
     * @return BelongsTo
     */
    public function getParametroTipoDocumento() {
        return $this->belongsTo(ParametrosTipoDocumento::class, 'tdo_id');
    }

    /**
     * Relación con el modelo Regimen Fiscal.
     *
     * @return BelongsTo
     */
    public function getParametroRegimenFiscal() {
        return $this->belongsTo(ParametrosRegimenFiscal::class, 'rfi_id');
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

    /**
     * Relación con el modelo Contactos.
     *
     * @return HasMany
     */
    public function getContactos() {
        return $this->hasMany(ConfiguracionContacto::class, 'adq_id');
    }
    // FIN RELACIONES
}
