<?php

namespace App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;
use openEtl\Tenant\Models\Configuracion\SoftwareProveedoresTecnologicos\TenantConfiguracionSoftwareProveedorTecnologico;

class ConfiguracionSoftwareProveedorTecnologico extends TenantConfiguracionSoftwareProveedorTecnologico {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'sft_id',
        'sft_identificador',
        'sft_pin',
        'sft_nombre',
        'sft_fecha_registro',
        'add_id',
        'sft_aplica_para',
        'sft_nit_proveedor_tecnologico',
        'sft_razon_social_proveedor_tecnologico',
        'sft_testsetid',
        'bdd_id_rg',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getAmbienteDestino',
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
     * Relación con el Ambiente Destino.
     *
     * @return BelongsTo
     */
    public function getAmbienteDestino() {
        return $this->belongsTo(ParametrosAmbienteDestinoDocumento::class, 'add_id')->select([
            'add_id',
            'add_codigo',
            'add_descripcion',
            'add_url',
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
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     *
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->orWhere( function ($query) use ($texto) {
            $query->where('sft_identificador', 'like', '%'.$texto.'%')
            ->orWhere('sft_pin', 'like', '%'.$texto.'%')
            ->orWhere('sft_nombre', 'like', '%'.$texto.'%')
            ->orWhere('sft_aplica_para', 'like', '%'.$texto.'%')
            ->orWhere('sft_nit_proveedor_tecnologico', 'like', '%'.$texto.'%')
            ->orWhere('sft_razon_social_proveedor_tecnologico', 'like', '%'.$texto.'%')
            ->orWhere('sft_fecha_registro', 'like', '%'.$texto.'%')
            ->orWhere('estado', $texto);
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
    public function scopeOrderByColumn($query, $columnaOrden = 'codigo', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'codigo':
                $orderBy = 'sft_identificador';
                break;
            case 'pin':
                $orderBy = 'sft_pin';
                break;
            case 'nombre':
                $orderBy = 'sft_nombre';
                break;
            case 'aplica_para':
                $orderBy = 'sft_aplica_para';
                break;
            case 'nit':
                $orderBy = 'sft_nit_proveedor_tecnologico';
                break;
            case 'razon_social':
                $orderBy = 'sft_razon_social_proveedor_tecnologico';
                break;
            case 'fecha_registro':
                $orderBy = 'sft_fecha_registro';
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
            $ordenDireccion = 'desc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }
    // END SCOPES
}