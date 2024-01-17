<?php

namespace App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente;

use App\Http\Models\User;
use App\Http\Models\AuthBaseDatos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Configuracion\Contactos\ConfiguracionContacto;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Configuracion\TributosOfesAdquirentes\TributosOfesAdquirentes;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Configuracion\ObservacionesGeneralesFactura\ConfiguracionObservacionesGeneralesFactura;
use App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologico;
use openEtl\Tenant\Models\Configuracion\ObligadosFacturarElectronicamente\TenantConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionObligadoFacturarElectronicamente extends TenantConfiguracionObligadoFacturarElectronicamente {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
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
        'pai_id',
        'dep_id',
        'mun_id',
        'cpo_id',
        'ofe_direccion',
        'ofe_direcciones_adicionales',
        'pai_id_domicilio_fiscal',
        'dep_id_domicilio_fiscal',
        'mun_id_domicilio_fiscal',
        'cpo_id_domicilio_fiscal',
        'ofe_direccion_domicilio_fiscal',
        'ofe_telefono',
        'ofe_web',
        'ofe_correo',
        'ofe_twitter',
        'ofe_facebook',
        'rfi_id',
        'ref_id',
        'ofe_matricula_mercantil',
        'ofe_actividad_economica',
        'ofe_archivo_certificado',
        'ofe_password_certificado',
        'ofe_representacion_grafica',
        'ofe_excel_personalizado',
        'ofe_columnas_personalizadas',
        'ofe_filtros',
        'ofe_envio_notificacion_amazon_ses',
        'ofe_conexion_smtp',
        'ofe_eventos_notificacion',
        'ofe_conexion_ftp',
        'ofe_procesar_directorio_ftp',
        'ofe_tipo_archivo_directorio_ftp',
        'ofe_cadisoft_activo',
        'ofe_cadisoft_configuracion',
        'ofe_cadisoft_ultima_ejecucion',
        'ofe_motivo_rechazo',
        'ofe_tiene_representacion_grafica_personalizada',
        'ofe_campos_personalizados_factura_generica',
        'ofe_asunto_correos',
        'tat_id',
        'ofe_mostrar_seccion_correos_notificacion',
        'ofe_correos_notificacion',
        'ofe_notificacion_un_solo_correo',
        'ofe_correos_autorespuesta',
        'ofe_tarifa_emision',
        'ofe_reenvio_notificacion_contingencia',
        'ofe_emision',
        'ofe_recepcion',
        'ofe_documento_soporte',
        'ofe_recepcion_eventos_contratados_titulo_valor',
        'ofe_recepcion_transmision_erp',
        'ofe_recepcion_conexion_erp',
        'bdd_id_rg',
        'ofe_identificador_unico_adquirente',
        'ofe_informacion_personalizada_adquirente',
        'ofe_prioridad_agendamiento',
        'ofe_recepcion_fnc_activo',
        'ofe_recepcion_fnc_configuracion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getBaseDatosRg',
        'getUsuarioCreacion',
        'getParametrosDepartamento',
        'getParametrosMunicipio',
        'getParametrosPais',
        'getConfiguracionSoftwareProveedorTecnologico',
        'getConfiguracionObservacionesGeneralesFactura',
        'getContactos',
        'getAdquirentes',
        'getParametrosRegimenFiscal',
        'getResolucionesFacturacion',
        'getParametroDomicilioFiscalPais',
        'getParametroDomicilioFiscalDepartamento',
        'getParametroDomicilioFiscalMunicipio',
        'getTipoOrganizacionJuridica',
        'getResponsabilidadFiscal',
        'getTributos',
        'getCodigoPostal',
        'getCodigoPostalDomicilioFiscal',
        'getParametroTipoDocumento'
    ];

    /**
     * Relación con el modelo bases de datos.
     *
     * @return BelongsTo
     */
    public function getBaseDatosRg() {
        return $this->belongsTo(AuthBaseDatos::class, 'bdd_id_rg', 'bdd_id');
    }

    /**
     * Obtiene la razón social del Ofe a partir de sus nombres y apellidos.
     *
     * @return string
     */
    public function getOfeRazonSocialAttribute($value){
        if(empty($value)){
            return str_replace('  ', ' ', trim($this->ofe_primer_nombre.' '.$this->ofe_otros_nombres.' '.$this->ofe_primer_apellido.' '.$this->ofe_segundo_apellido));
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
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }


    /**
     * Relación con el Tipo Documento.
     *
     * @return BelongsTo
     */
    public function getTipoOrganizacionJuridica() {
        return $this->belongsTo(ParametrosTipoOrganizacionJuridica::class, 'toj_id')->select([
            'toj_id',
            'toj_descripcion',
            'toj_codigo',
        ]);
    }

    /**
     * Relación con el modelo Departamento.
     *
     * @return BelongsTo
     */
    public function getParametrosDepartamento() {
        return $this->belongsTo(ParametrosDepartamento::class, 'dep_id')->select([
            'dep_id',
            'dep_descripcion',
            'dep_codigo',
        ]);
    }

    /**
     * Relación con el modelo Municipio.
     *
     * @return BelongsTo
     */
    public function getParametrosMunicipio() {
        return $this->belongsTo(ParametrosMunicipio::class, 'mun_id')->select([
            'mun_id',
            'mun_descripcion',
            'mun_codigo',
        ]);
    }

    /**
     * Relación con el modelo País.
     *
     * @return BelongsTo
     */
    public function getParametrosPais() {
        return $this->belongsTo(ParametrosPais::class, 'pai_id')->select([
            'pai_id',
            'pai_descripcion',
            'pai_codigo',
        ]);
    }

    /**
     * Relación con el modelo Software Proveeedor Tecnológico.
     *
     * @return BelongsTo
     */
    public function getConfiguracionSoftwareProveedorTecnologico() {
        return $this->belongsTo(ConfiguracionSoftwareProveedorTecnologico::class, 'sft_id')->select([
            'sft_id',
            'sft_identificador',
            'sft_pin',
            'sft_nombre',
            'add_id'
        ]);
    }

    /**
     * Relación con el modelo Observaciones Generales Factura.
     *
     * @return HasMany
     */
    public function getConfiguracionObservacionesGeneralesFactura() {
        return $this->hasMany(ConfiguracionObservacionesGeneralesFactura::class, 'ofe_id')->select([
            'ogf_id',
            'ofe_id',
            'ogf_observacion',
        ]);
    }

    /**
     * Relación con el modelo Contactos.
     *
     * @return HasMany
     */
    public function getContactos(){
        return $this->hasMany(ConfiguracionContacto::class, 'ofe_id')->select([
            'con_id',
            'ofe_id',
            'con_nombre',
            'con_direccion',
            'con_telefono',
            'con_correo',
            'con_observaciones',
            'con_tipo',
        ]);
    }

    /**
     * Relación con el modelo Adquirentes.
     *
     * @return HasMany
     */
    public function getAdquirentes(){
        return $this->hasMany(ConfiguracionAdquirente::class, 'ofe_id')->select([
            'adq_id',
            'ofe_id',
            'adq_identificacion',
            'adq_razon_social',
            'adq_nombre_comercial',
            'adq_primer_apellido',
            'adq_segundo_apellido',
            'adq_primer_nombre',
            'adq_otros_nombres',
            'adq_tipo_adquirente',
            'adq_tipo_autorizado',
            'adq_tipo_responsable_entrega',
        ]);
    }

    /**
     * Relación con el modelo Tipo Régimen.
     *
     * @return BelongsTo
     */
    public function getParametrosRegimenFiscal() {
        return $this->belongsTo(ParametrosRegimenFiscal::class, 'rfi_id')->select([
            'rfi_id',
            'rfi_codigo',
            'rfi_descripcion',
        ]);
    }

    /**
     * Relación con el modelo Domicilio Fiscal País.
     *
     * @return BelongsTo
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
     * @return BelongsTo
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
     * @return BelongsTo
     */
    public function getParametroDomicilioFiscalMunicipio() {
        return $this->belongsTo(ParametrosMunicipio::class, 'mun_id_domicilio_fiscal')->select([
            'mun_id',
            'mun_descripcion',
            'mun_codigo',
        ]);
    }

    /**
     * Relación con el modelo Responsabilidad Fiscal.
     *
     * @return BelongsTo
     */
    public function getResponsabilidadFiscal() {
        return $this->belongsTo(ParametrosResponsabilidadFiscal::class, 'ref_id')->select([
            'ref_id',
            'ref_codigo',
            'ref_descripcion',
        ]);
    }

    /**
     * Relación con el modelo Resolución Facturación.
     *
     * @return HasMany
     */
    public function getResolucionesFacturacion() {
        return $this->hasMany(ConfiguracionResolucionesFacturacion::class, 'ofe_id')->select([
            'rfa_id',
            'ofe_id',
            'rfa_resolucion',
            'rfa_prefijo',
            'rfa_clave_tecnica',
            'rfa_fecha_desde',
            'rfa_fecha_hasta',
            'rfa_consecutivo_inicial',
            'rfa_consecutivo_final',
        ]);
    }

    /**
     * Relación con el modelo Tributos.
     *
     * @return HasMany
     */
    public function getTributos() {
        return $this->hasMany(TributosOfesAdquirentes::class, 'ofe_id')->select([
            'ofe_id',
            'tri_id',
        ]);
    }

    /**
     * Relación con el modelo Códigos Postales.
     *
     * @return BelongsTo
     */
    public function getCodigoPostal() {
        return $this->belongsTo(ParametrosCodigoPostal::class, 'cpo_id')->select([
            'cpo_id',
            'cpo_codigo',
        ]);
    }

    /**
     * Relación con el modelo Códigos Postales - Domicilio Fiscal.
     *
     * @return BelongsTo
     */
    public function getCodigoPostalDomicilioFiscal() {
        return $this->belongsTo(ParametrosCodigoPostal::class, 'cpo_id_domicilio_fiscal')->select([
            'cpo_id',
            'cpo_codigo',
        ]);
    }

    /**
     * Relación con el modelo Tipo Documento.
     *
     * @return BelongsTo
     */
    public function getParametroTipoDocumento() {
        return $this->belongsTo(ParametrosTipoDocumento::class, 'tdo_id')->select([
            'tdo_id',
            'tdo_codigo',
            'tdo_descripcion',
        ]);
    }
    // FIN RELACIONES

    // SCOPE
    /**
     * Local Scope que permite filtrar los Ofes asociados a la base de datos del usuario autenticado.
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeValidarAsociacionBaseDatos(Builder $query): Builder {
        return $query->when(!empty(auth()->user()->bdd_id_rg), function ($query) {
                return $query->where('bdd_id_rg', auth()->user()->bdd_id_rg);
            }, function ($query) {
                return $query->whereNull('bdd_id_rg');
            });
    }
}


