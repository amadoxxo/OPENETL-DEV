<?php

namespace App\Http\Modulos\Recepcion\Configuracion\Proveedores;

use App\Http\Models\User;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use openEtl\Tenant\Models\Recepcion\Configuracion\Proveedores\TenantRepProveedor;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionProveedor extends TenantRepProveedor {
    protected $visible = [
        'pro_id',
        'ofe_id',
        'pro_identificacion',
        'pro_razon_social',
        'pro_nombre_comercial',
        'pro_primer_apellido',
        'pro_segundo_apellido',
        'pro_primer_nombre',
        'pro_otros_nombres',
        'tdo_id',
        'toj_id',
        'pai_id',
        'dep_id',
        'mun_id',
        'cpo_id',
        'pro_direccion',
        'pro_telefono',
        'pai_id_domicilio_fiscal',
        'dep_id_domicilio_fiscal',
        'mun_id_domicilio_fiscal',
        'cpo_id_domicilio_fiscal',
        'pro_direccion_domicilio_fiscal',
        'pro_correo',
        'rfi_id',
        'ref_id',
        'pro_matricula_mercantil',
        'pro_usuarios_recepcion',
        'pro_integracion_erp',
        'tat_id',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getParametroTipoDocumento',
        'getParametroPais',
        'getParametroDepartamento',
        'getParametroMunicipio',
        'getParametroTipoOrganizacionJuridica',
        'getRegimenFiscal',
        'getResponsabilidadFiscal',
        'getCodigoPostal',
        'getParametroDomicilioFiscalPais',
        'getParametroDomicilioFiscalDepartamento',
        'getParametroDomicilioFiscalMunicipio',
        'getCodigoPostalDomicilioFiscal',
        'getProveedorGruposTrabajo'
    ];

    public function getProRazonSocialAttribute($value){
        if(empty($value)){
            return trim($this->pro_primer_nombre.' '.$this->pro_otros_nombres.' '.$this->pro_primer_apellido.' '.$this->pro_segundo_apellido);
        } else {
            return $value;
        }
    }

    public function getProUsuariosRecepcionAttribute($value){
        if(!empty($value)){
            return implode(',', json_decode($value, true));
        } else {
            return $value;
        }
    }

    // INICIO RELACIONES

    /**
     * Relación con el modelo usuario.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }

    /**
     * Relación con el modelo Obligado a Facturar Electronicamente.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id')->select([
            'ofe_id',
            'sft_id',
            'tdo_id',
            'toj_id',
            'ofe_identificacion',
            'ofe_razon_social',
            'ofe_nombre_comercial',
            'ofe_primer_apellido',
            'ofe_segundo_apellido',
            'ofe_primer_nombre',
            'ofe_otros_nombres',
        ]);
    }

    /**
     * Relación con el modelo Responsabilidad Fiscal.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getResponsabilidadFiscal() {
        return $this->belongsTo(ParametrosResponsabilidadFiscal::class, 'ref_id')->select([
            'ref_id',
            'ref_codigo',
            'ref_descripcion',
        ]);
    }

    /**
     * Relación con el modelo Tipo Documento.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getParametroTipoDocumento() {
        return $this->belongsTo(ParametrosTipoDocumento::class, 'tdo_id')->select([
            'tdo_id',
            'tdo_codigo', 
            'tdo_descripcion',
        ]);
    }

    /**
     * Relación con el modelo Paramentro Pais.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getParametroPais() {
        return $this->belongsTo(ParametrosPais::class, 'pai_id')->select([
            'pai_id',
            'pai_descripcion',
            'pai_codigo',
        ]);
    }

    /**
     * Relación con el modelo Departamento.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getParametroDepartamento() {
        return $this->belongsTo(ParametrosDepartamento::class, 'dep_id')->select([
            'dep_id',
            'dep_descripcion',
            'dep_codigo',
        ]);
    }

    /**
     * Relación con el modelo Municipio.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getParametroMunicipio() {
        return $this->belongsTo(ParametrosMunicipio::class, 'mun_id')->select([
            'mun_id',
            'mun_descripcion',
            'mun_codigo',
        ]);
    }

    /**
     * Relación con el modelo Tipo Organizacion Juridica.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getParametroTipoOrganizacionJuridica() {
        return $this->belongsTo(ParametrosTipoOrganizacionJuridica::class, 'toj_id')->select([
            'toj_id',
            'toj_descripcion',
            'toj_codigo',
        ]);
    }

    /**
     * Relación con el modelo Regimen Fiscal.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getRegimenFiscal() {
        return $this->belongsTo(ParametrosRegimenFiscal::class, 'rfi_id')->select([
            'rfi_id',
            'rfi_codigo', 
            'rfi_descripcion',
        ]);
    }

    /**
     * Relación con el modelo Codigos Postales.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getCodigoPostal() {
        return $this->belongsTo(ParametrosCodigoPostal::class, 'cpo_id')->select([
            'cpo_id',
            'cpo_codigo',
        ]);
    }

    /**
     * Relación con el modelo Domicilio Fiscal Pais.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getParametroDomicilioFiscalPais() {
        return $this->belongsTo(ParametrosPais::class, 'pai_id_domicilio_fiscal')->select([
            'pai_id',
            'pai_descripcion',
            'pai_codigo',
        ]);
    }

    /**
     * Relación con el modelo Domicilio Fiscal Departamento.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getParametroDomicilioFiscalDepartamento() {
        return $this->belongsTo(ParametrosDepartamento::class, 'dep_id_domicilio_fiscal')->select([
            'dep_id',
            'dep_descripcion',
            'dep_codigo',
        ]);
    }

    /**
     * Relación con el modelo Domicilio Fiscal Municipio.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getParametroDomicilioFiscalMunicipio() {
        return $this->belongsTo(ParametrosMunicipio::class, 'mun_id_domicilio_fiscal')->select([
            'mun_id',
            'mun_descripcion',
            'mun_codigo',
        ]);
    }

    /**
     * Relación con el modelo Codigos Postales - Domicilio Fiscal.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getCodigoPostalDomicilioFiscal() {
        return $this->belongsTo(ParametrosCodigoPostal::class, 'cpo_id_domicilio_fiscal')->select([
            'cpo_id',
            'cpo_codigo',
        ]);
    }

    /**
     * Relación con el modelo  de grupos de trabajo proveedores.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getProveedorGruposTrabajo() {
        return $this->hasMany(ConfiguracionGrupoTrabajoProveedor::class, 'pro_id');
    }
    // FIN RELACIONES

}
