<?php

namespace App\Http\Modulos\Recepcion\Configuracion\Proveedores;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use openEtl\Tenant\Models\Recepcion\Configuracion\Proveedores\TenantRepProveedor;
use App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacita;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
use App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores\RepUsuarioPortalProveedores;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionProveedor extends TenantRepProveedor {
    protected $visible = [
        'pro_id',
        'ofe_id',
        'pro_identificacion',
        'pro_id_personalizado',
        'pro_razon_social',
        'pro_nombre_comercial',
        'pro_primer_apellido',
        'pro_segundo_apellido',
        'pro_primer_nombre',
        'pro_otros_nombres',
        'nombre_completo',
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
        'pro_correos_notificacion',
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
        'getTiempoAceptacionTacita',
        'getCodigoPostal',
        'getParametroDomicilioFiscalPais',
        'getParametroDomicilioFiscalDepartamento',
        'getParametroDomicilioFiscalMunicipio',
        'getCodigoPostalDomicilioFiscal',
        'getUsuariosPortales',
        'getProveedorGruposTrabajo'
    ];

    /**
     *  Obtiene el valor de la razón social del proveedor a partir de nombre y apellidos.
     *
     * @param  string $value Valor del campo
     * @return string
     */
    public function getProRazonSocialAttribute($value){
        if(empty($value)){
            return str_replace('  ', ' ', trim($this->pro_primer_nombre.' '.$this->pro_otros_nombres.' '.$this->pro_primer_apellido.' '.$this->pro_segundo_apellido));
        } else {
            return $value;
        }
    }

    /**
     * Obtiene el valor del campo usuario recepción.
     *
     * @param  string $value Valor del campo
     * @return string
     */
    public function getProUsuariosRecepcionAttribute($value){
        if(!empty($value)){
            return json_decode($value);
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
     * Relación con el modelo de grupos de trabajo proveedores.
     *
     * @return HasMany
     */
    public function getProveedorGruposTrabajo() {
        return $this->hasMany(ConfiguracionGrupoTrabajoProveedor::class, 'pro_id');
    }

    /**
     * Relación con el modelo Obligado a Facturar Electrónicamente.
     *
     * @return BelongsTo
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

    /**
     * Relación con el modelo Parámetro País.
     *
     * @return BelongsTo
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
     * @return BelongsTo
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
     * @return BelongsTo
     */
    public function getParametroMunicipio() {
        return $this->belongsTo(ParametrosMunicipio::class, 'mun_id')->select([
            'mun_id',
            'mun_descripcion',
            'mun_codigo',
        ]);
    }

    /**
     * Relación con el modelo Tipo Organización Jurídica.
     *
     * @return BelongsTo
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
     * @return BelongsTo
     */
    public function getRegimenFiscal() {
        return $this->belongsTo(ParametrosRegimenFiscal::class, 'rfi_id')->select([
            'rfi_id',
            'rfi_codigo', 
            'rfi_descripcion',
        ]);
    }

    /**
     * Relación con el modelo Tiempo Aceptación Tacita.
     *
     * @return BelongsTo
     */
    public function getTiempoAceptacionTacita() {
        return $this->belongsTo(SistemaTiempoAceptacionTacita::class, 'tat_id')->select([
            'tat_id',
            'tat_codigo',
            'tat_descripcion',
            'tat_segundos',
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
     * Relación con el modelo Domicilio Fiscal Pais.
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
     * Relación con el modelo Usuarios Portal Proveedores.
     *
     * @return HasMany
     */
    public function getUsuariosPortales() {
        return $this->hasMany(RepUsuarioPortalProveedores::class, 'pro_id');
    }
    // FIN RELACIONES

    // SCOPES
    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     * 
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->where( function ($query) use ($texto) {
            $query->where('pro_identificacion', 'like', '%'.$texto.'%')
            ->orWhere('pro_razon_social', 'like', '%'.$texto.'%')
            ->orWhere('pro_primer_apellido', 'like', '%'.$texto.'%')
            ->orWhere('pro_segundo_apellido', 'like', '%'.$texto.'%')
            ->orWhere('pro_primer_nombre', 'like', '%'.$texto.'%')
            ->orWhere('pro_otros_nombres', 'like', '%'.$texto.'%')
            ->orWhereRaw("REPLACE(CONCAT(COALESCE(pro_primer_nombre, ''), ' ', COALESCE(pro_otros_nombres, ''), ' ', COALESCE(pro_primer_apellido, ''), ' ', COALESCE(pro_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $texto . '%'])
            ->orWhere('estado', $texto)
            ->orWhere(function($query) use ($texto){
                $query->with('getConfiguracionObligadoFacturarElectronicamente')
                ->wherehas('getConfiguracionObligadoFacturarElectronicamente', function ($query) use ($texto) {
                    $query->where('ofe_razon_social', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_primer_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_segundo_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_primer_nombre', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_otros_nombres', 'like', '%'.$texto.'%')
                        ->orWhereRaw("REPLACE(CONCAT(COALESCE(ofe_primer_nombre, ''), ' ', COALESCE(ofe_otros_nombres, ''), ' ', COALESCE(ofe_primer_apellido, ''), ' ', COALESCE(ofe_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $texto . '%']);
                });
            });
        });
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas
     * 
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'identificacion', $ordenDireccion = 'asc'){
        switch($columnaOrden){
            case 'identificacion':
                $orderBy = 'pro_identificacion';
                break;
            case 'nombre':
                $orderBy = 'pro_razon_social';
                break;
            case 'creado':
                $orderBy = 'fecha_creacion';
                break;
            case 'modificado':
                $orderBy = 'fecha_modificacion';
                break;
            case 'estado':
                $orderBy = 'estado';
                break;
            default:
                $orderBy = 'fecha_modificacion';
                break;
        }

        if( strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'asc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }
    // END SCOPES

}
