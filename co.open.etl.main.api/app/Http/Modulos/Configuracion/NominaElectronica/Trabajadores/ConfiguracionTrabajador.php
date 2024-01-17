<?php

namespace App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores;

use App\Traits\MainTrait;
use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContrato;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;
use App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajador;
use App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajador;
use openEtl\Tenant\Models\Configuracion\NominaElectronica\Trabajadores\TenantConfiguracionTrabajador;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaop;

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
        'nombre_completo',
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
        'getEmpleador',
        'getTipoDocumento',
        'getParametrosPais',
        'getParametrosDepartamento',
        'getParametrosMunicipio',
        'getParametrosTipoContrato',
        'getParametrosTipoTrabajador',
        'getParametrosSubtipoTrabajador'
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
            'emp_razon_social',
            'emp_primer_apellido',
            'emp_segundo_apellido',
            'emp_primer_nombre',
            'emp_otros_nombres'
        ]);
    }

    /**
     * Relación con el modelo de Tipo Documento.
     *
     * @return BelongsTo
     */
    public function getTipoDocumento() {
        return $this->belongsTo(ParametrosTipoDocumento::class, 'tdo_id')->select([
            'tdo_id',
            'tdo_codigo',
            'tdo_descripcion'
        ]);
    }

    /**
     * Relación con el modelo de País.
     * 
     * @return BelongsTo
     */
    public function getParametrosPais() {
        return $this->belongsTo(ParametrosPais::class, 'pai_id')->select([
            'pai_id',
            'pai_codigo',
            'pai_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta'
        ]);
    }

    /**
     * Relación con el modelo de Departamentos.
     *
     * @return BelongsTo
     */
    public function getParametrosDepartamento() {
        return $this->belongsTo(ParametrosDepartamento::class, 'dep_id')->select([
            'dep_id',
            'dep_codigo',
            'dep_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta'
        ]);
    }

    /**
     * Relación con el modelo de Municipio.
     *
     * @return BelongsTo
     */
    public function getParametrosMunicipio() {
        return $this->belongsTo(ParametrosMunicipio::class, 'mun_id')->select([
            'mun_id',
            'mun_codigo',
            'mun_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta'
        ]);
    }

    /**
     * Relación con el modelo de Tipo de Contrato.
     *
     * @return BelongsTo
     */
    public function getParametrosTipoContrato() {
        return $this->belongsTo(ParametrosTipoContrato::class, 'ntc_id')->select([
            'ntc_id',
            'ntc_codigo',
            'ntc_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta'
        ]);
    }

    /**
     * Relación con el modelo de Tipo de Trabajador.
     *
     * @return BelongsTo
     */
    public function getParametrosTipoTrabajador() {
        return $this->belongsTo(ParametrosTipoTrabajador::class, 'ntt_id')->select([
            'ntt_id',
            'ntt_codigo',
            'ntt_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta'
        ]);
    }

    /**
     * Relación con el modelo de Subtipo de Trabajador.
     *
     * @return BelongsTo
     */
    public function getParametrosSubtipoTrabajador() {
        return $this->belongsTo(ParametrosSubtipoTrabajador::class, 'nst_id')->select([
            'nst_id',
            'nst_codigo',
            'nst_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta'
        ]);
    }

    /**
     * Relación con DsnCabeceraDocumentoNominaDaop.
     *
     * @return HasMany
     */
    public function getDsnCabeceraDocumentoNominaDaop() {
        return $this->hasMany(DsnCabeceraDocumentoNominaDaop::class, 'tra_id');
    }
    // FIN RELACIONES

    // MUTTATORS
    /**
     * Obtiene el valor del sueldo para ser formateado.
     *
     * @param  float  $value Valor del campo sueldo
     * @return float
     */
    public function getTraSueldoAttribute($value)
    {
        return MainTrait::formatearValor($value, true);
    }

    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $texto cadena de texto a buscar
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->where( function ($query) use ($texto) {
            $query->where('tra_identificacion', 'like', '%'.$texto.'%')
            ->orWhere('tra_primer_apellido', 'like', '%'.$texto.'%')
            ->orWhere('tra_segundo_apellido', 'like', '%'.$texto.'%')
            ->orWhere('tra_primer_nombre', 'like', '%'.$texto.'%')
            ->orWhere('tra_otros_nombres', 'like', '%'.$texto.'%')
            ->orWhere('estado', $texto)
            ->orWhere(function($query) use ($texto){
                $query->with('getEmpleador')
                ->wherehas('getEmpleador', function ($query) use ($texto) {
                    $query->where('usuario_creacion', 'like', '%'.$texto.'%')
                    ->orWhere('emp_razon_social', 'like', '%'.$texto.'%')
                    ->orWhere('emp_primer_apellido', 'like', '%'.$texto.'%')
                    ->orWhere('emp_segundo_apellido', 'like', '%'.$texto.'%')
                    ->orWhere('emp_primer_nombre', 'like', '%'.$texto.'%')
                    ->orWhere('emp_otros_nombres', 'like', '%'.$texto.'%');
                });
            });
        });
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'modificado', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'nombre_empleador':
                return $query->join('dsn_empleadores as empleador', 'empleador.emp_id', '=', 'dsn_trabajadores.emp_id')
                    ->orderBy('empleador.emp_razon_social', $ordenDireccion)
                    ->orderBy('empleador.emp_primer_nombre', $ordenDireccion);
                break;
            case 'identificacion':
                $orderBy = 'tra_identificacion';
                break;
            case 'nombre_completo':
                $orderBy = 'tra_primer_nombre';
                break;
            case 'estado':
                $orderBy = 'estado';
                break;
            default:
                $orderBy = 'fecha_modificacion';
                break;
        }

        if( strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'desc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }
}
