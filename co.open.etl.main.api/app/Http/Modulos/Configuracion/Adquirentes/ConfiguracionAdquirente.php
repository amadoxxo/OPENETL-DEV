<?php

namespace App\Http\Modulos\Configuracion\Adquirentes;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Configuracion\Contactos\ConfiguracionContacto;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedor;
use openEtl\Tenant\Models\Configuracion\Adquirentes\TenantConfiguracionAdquirente;
use App\Http\Modulos\Configuracion\TributosOfesAdquirentes\TributosOfesAdquirentes;
use App\Http\Modulos\Configuracion\UsuariosPortalClientes\EtlUsuarioPortalClientes;
use App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacita;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
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
        'id',
        'identificacion',
        'nombre_completo',
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
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getParametroTipoDocumento',
        'getParametroPais',
        'getParametroDepartamento',
        'getParametroMunicipio',
        'getContactos',
        'getParametroTipoOrganizacionJuridica',
        'getRegimenFiscal',
        'getProcedenciaVendedor',
        'getTributos',
        'getResponsabilidadFiscal',
        'getTiempoAceptacionTacita',
        'getCodigoPostal',
        'getParametroDomicilioFiscalPais',
        'getParametroDomicilioFiscalDepartamento',
        'getParametroDomicilioFiscalMunicipio',
        'getCodigoPostalDomicilioFiscal',
        'getUsuariosPortales'
    ];

    /**
     * Obtiene la razón social del adquirente a partir de los nombres y apellidos.
     *
     * @param  string $value Valor del campo
     * @return string
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
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
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
            'pai_id',
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
            'pai_id',
            'mun_descripcion',
            'mun_codigo',
        ]);
    }

    /**
     * Relación con el modelo Contactos.
     *
     * @return HasMany
     */
    public function getContactos() {
        return $this->hasMany(ConfiguracionContacto::class, 'adq_id')->select([
            'con_id',
            'adq_id',
            'con_nombre',
            'con_direccion',
            'con_telefono',
            'con_correo',
            'con_observaciones',
            'con_tipo',
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
     * Relación con el modelo Régimen Fiscal.
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
     * Relación con el modelo Procedencia Vendedor.
     *
     * @return BelongsTo
     */
    public function getProcedenciaVendedor() {
        return $this->belongsTo(ParametrosProcedenciaVendedor::class, 'ipv_id')->select([
            'ipv_id',
            'ipv_codigo', 
            'ipv_descripcion',
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
     * Relación con el modelo Tributos.
     *
     * @return HasMany
     */
    public function getTributos() {
        return $this->hasMany(TributosOfesAdquirentes::class, 'adq_id')->select([
            'adq_id',
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
            'pai_id',
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
            'pai_id',
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
     * Relación con el modelo Usuarios Portal Clientes.
     *
     * @return HasMany
     */
    public function getUsuariosPortales() {
        return $this->hasMany(EtlUsuarioPortalClientes::class, 'adq_id');
    }
    // FIN RELACIONES

    // MUTTATORS
    // Mutadores que permite establecer el valor de la columna a null cuando su valor es vacío
    /**
     * Mutador que permite establecer el valor de la columna a null cuando su valor es vacío.
     *
     * @param string $value
     * @return void
     */
    public function setAdqRazonSocialAttribute($value) {
        if($value == '')
            $this->attributes['adq_razon_social'] = null;
        else
            $this->attributes['adq_razon_social'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna a null cuando su valor es vacío.
     *
     * @param string $value
     * @return void
     */
    public function setAdqNombreComercialAttribute($value) {
        if($value == '')
            $this->attributes['adq_nombre_comercial'] = null;
        else
            $this->attributes['adq_nombre_comercial'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna a null cuando su valor es vacío.
     *
     * @param string $value
     * @return void
     */
    public function setAdqPrimerNombreAttribute($value) {
        if($value == '')
            $this->attributes['adq_primer_nombre'] = null;
        else
            $this->attributes['adq_primer_nombre'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna a null cuando su valor es vacío.
     *
     * @param string $value
     * @return void
     */
    public function setAdqOtrosNombresAttribute($value) {
        if($value == '')
            $this->attributes['adq_otros_nombres'] = null;
        else
            $this->attributes['adq_otros_nombres'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna a null cuando su valor es vacío.
     *
     * @param string $value
     * @return void
     */
    public function setAdqPrimerApellidoAttribute($value) {
        if($value == '')
            $this->attributes['adq_primer_apellido'] = null;
        else
            $this->attributes['adq_primer_apellido'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna a null cuando su valor es vacío.
     *
     * @param string $value
     * @return void
     */
    public function setAdqSegundoApellidoAttribute($value) {
        if($value == '')
            $this->attributes['adq_segundo_apellido'] = null;
        else
            $this->attributes['adq_segundo_apellido'] = $value;
    }

    // SCOPES
    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo.
     * 
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        $query = $query->where( function ($query) use ($texto) {
            $query->where('adq_identificacion', 'like', '%'.$texto.'%')
            ->orWhere('adq_razon_social', 'like', '%'.$texto.'%')
            ->orWhere('adq_nombre_comercial', 'like', '%'.$texto.'%')
            ->orWhere('adq_primer_apellido', 'like', '%'.$texto.'%')
            ->orWhere('adq_segundo_apellido', 'like', '%'.$texto.'%')
            ->orWhere('adq_primer_nombre', 'like', '%'.$texto.'%')
            ->orWhere('adq_otros_nombres', 'like', '%'.$texto.'%')
            ->orWhere('ofe_id', 'like', '%'.$texto.'%')
            ->orWhereRaw("REPLACE(CONCAT(COALESCE(adq_primer_nombre, ''), ' ', COALESCE(adq_otros_nombres, ''), ' ', COALESCE(adq_primer_apellido, ''), ' ', COALESCE(adq_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $texto . '%'])
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

        $query = $this->getTipoAdquirente($query);

        return $query;
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas.
     * 
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'identificacion', $ordenDireccion = 'asc'){

        switch($columnaOrden) {
            case 'identificacion':
                $orderBy = 'adq_identificacion';
                break;
            case 'razon':
                $orderBy = 'adq_razon_social';
                break;
            case 'nombre':
                $orderBy = 'adq_nombre_comercial';
                break;
            case 'ofe':
                $orderBy = 'ofe_id';
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

        $query = $this->getTipoAdquirente($query);

        if( strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'desc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }
    // END SCOPES

    /**
     * Agrega la condición de tipo de Adquirente.
     * 
     * @param Builder $query
     * @return Builder
     */
    private function getTipoAdquirente($query){
        //variable local para almacenar la request.
        $request = request();

        if ($request->has('tipoAdquirente')) {
            if ($request->tipoAdquirente == 'adquirente'){
                $query->where('adq_tipo_adquirente', 'SI');
            }
            else if ($request->tipoAdquirente == 'autorizado'){
                $query->where('adq_tipo_autorizado', 'SI');
            }
            else if ($request->tipoAdquirente == 'responsable'){
                $query->where('adq_tipo_responsable_entrega', 'SI');
            } 
            else if ($request->tipoAdquirente == 'vendedor'){
                $query->where('adq_tipo_vendedor_ds', 'SI');
            }
        }

        return $query;
    }
}
