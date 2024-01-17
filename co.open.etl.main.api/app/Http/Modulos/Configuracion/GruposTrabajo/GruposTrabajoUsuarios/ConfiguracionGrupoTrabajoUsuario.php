<?php

namespace App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use openEtl\Tenant\Models\Configuracion\GruposTrabajo\ConfiguracionGruposTrabajoUsuarios\TenantConfiguracionGrupoTrabajoUsuario;

class ConfiguracionGrupoTrabajoUsuario extends TenantConfiguracionGrupoTrabajoUsuario {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'gtu_id',
        'gtr_id',
        'usu_id',
        'gtu_usuario_gestor',
        'gtu_usuario_validador',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getUsuario',
        'getGrupoTrabajo'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo usuario de creación.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }

    /**
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     */
    public function getUsuario() {
        return $this->belongsTo(User::class, 'usu_id');
    }

    /**
     * Relación con el modelo grupo trabajo.
     *
     * @return BelongsTo
     */
    public function getGrupoTrabajo() {
        return $this->belongsTo(ConfiguracionGrupoTrabajo::class, 'gtr_id');
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
        $query->join('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'etl_grupos_trabajo_usuarios.usu_id');
        $query = $query->where( function ($query) use ($texto) {
            $query->where('etl_grupos_trabajo_usuarios.estado', 'like', '%'.$texto.'%')
            ->orWhere(function($query) use ($texto){
                $query->with('getGrupoTrabajo')
                ->wherehas('getGrupoTrabajo', function ($query) use ($texto) {
                    $query->where('gtr_codigo', 'like', '%'.$texto.'%')
                    ->orWhere('gtr_nombre', 'like', '%'.$texto.'%');
                });
            })
            ->orWhere(function($query) use ($texto){
                $query->with('getGrupoTrabajo.getConfiguracionObligadoFacturarElectronicamente')
                ->wherehas('getGrupoTrabajo.getConfiguracionObligadoFacturarElectronicamente', function ($query) use ($texto) {
                    $query->where('ofe_identificacion', 'like', '%'.$texto.'%')
                    ->orWhere('ofe_razon_social', 'like', '%'.$texto.'%')
                    ->orWhere('ofe_primer_apellido', 'like', '%'.$texto.'%')
                    ->orWhere('ofe_segundo_apellido', 'like', '%'.$texto.'%')
                    ->orWhere('ofe_primer_nombre', 'like', '%'.$texto.'%')
                    ->orWhere('ofe_otros_nombres', 'like', '%'.$texto.'%');
                });
            })
            ->orWhere('usuarios.usu_identificacion', 'like', '%'.$texto.'%')
            ->orWhere('usuarios.usu_email', 'like', '%'.$texto.'%');
        });
        
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
    public function scopeOrderByColumn($query, $columnaOrden = 'modificado', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'nit_oferente':
                return $query->join('etl_grupos_trabajo as grupos_trabajo', 'grupos_trabajo.gtr_id', '=', 'etl_grupos_trabajo_usuarios.gtr_id')
                    ->join('etl_obligados_facturar_electronicamente as oferentes', 'oferentes.ofe_id', '=', 'grupos_trabajo.ofe_id')
                    ->orderBy('oferentes.ofe_identificacion', $ordenDireccion);
                break;
            case 'identificacion':
                return $query->join('etl_openmain.auth_usuarios as usuario', 'usuario.usu_id', '=', 'etl_grupos_trabajo_usuarios.usu_id')
                    ->orderBy('usuario.usu_identificacion', $ordenDireccion);
                break;
            case 'nombres':
                return $query->join('etl_openmain.auth_usuarios as usuario', 'usuario.usu_id', '=', 'etl_grupos_trabajo_usuarios.usu_id')
                    ->orderBy('usuario.usu_nombre', $ordenDireccion);
                break;
            case 'email':
                return $query->join('etl_openmain.auth_usuarios as usuario', 'usuario.usu_id', '=', 'etl_grupos_trabajo_usuarios.usu_id')
                    ->orderBy('usuario.usu_email', $ordenDireccion);
                break;
            case 'grupo_trabajo':
                return $query->join('etl_grupos_trabajo as grupo', 'grupo.gtr_id', '=', 'etl_grupos_trabajo_usuarios.gtr_id')
                    ->orderBy('grupo.gtr_codigo', $ordenDireccion);
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