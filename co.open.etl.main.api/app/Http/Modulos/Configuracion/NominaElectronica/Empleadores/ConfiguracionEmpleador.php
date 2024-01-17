<?php

namespace App\Http\Modulos\Configuracion\NominaElectronica\Empleadores;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
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
        'nombre_completo',
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
        'emp_prioridad_agendamiento',
        'bdd_id_rg',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getTipoDocumento',
        'getParametrosPais',
        'getParametrosDepartamento',
        'getParametrosMunicipio',
        'getProveedorTecnologico',
        'getBaseDatosRg'
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
     * Relación con el modelo de Software Proveedor Tecnológico.
     *
     * @return BelongsTo
     */
    public function getProveedorTecnologico() {
        return $this->belongsTo(ConfiguracionSoftwareProveedorTecnologico::class, 'sft_id')->select([
            'sft_id',
            'sft_identificador',
            'sft_pin',
            'sft_nombre',
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
    // FIN RELACIONES

    // SCOPES
    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo.
     * 
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->where( function ($query) use ($texto) {
            $query->where('emp_identificacion', 'like', '%'.$texto.'%')
            ->orWhere('emp_razon_social', 'like', '%'.$texto.'%')
            ->orWhere('emp_primer_apellido', 'like', '%'.$texto.'%')
            ->orWhere('emp_segundo_apellido', 'like', '%'.$texto.'%')
            ->orWhere('emp_primer_nombre', 'like', '%'.$texto.'%')
            ->orWhere('emp_otros_nombres', 'like', '%'.$texto.'%')
            ->orWhere('estado', $texto);
        });
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas.
     * 
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'modificado', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'identificacion':
                $orderBy = 'emp_identificacion';
                break;
            case 'razon_nombres':
                return $query->orderBy('emp_razon_social', $ordenDireccion)
                    ->orderBy('emp_primer_nombre', $ordenDireccion);
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
    // END SCOPES
}
