<?php

namespace App\Http\Modulos\Configuracion\UsuariosEcm;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Configuracion\UsuariosEcm\TenantConfiguracionUsuarioEcm;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionUsuarioEcm extends TenantConfiguracionUsuarioEcm {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'use_id',
        'ofe_id',
        'usu_id',
        'use_rol',
        'fecha_creacion',
        'fecha_modificacion',
        'usuario_creacion',
        'estado',
        'getUsuarioCreacion',
        'getUsuarioEcm',
        'getConfiguracionObligadoFacturarElectronicamente'
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
     * Relación con el modelo usuario para el usuario de openECM.
     *
     * @return BelongsTo
     */
    public function getUsuarioEcm() {
        return $this->belongsTo(User::class, 'usu_id')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
            'usu_email',
            'estado'
        ]);
    }

    /**
     * Relación con el modelo del Oferente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
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
        $query->join('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'etl_usuarios_ecm.usu_id');
        $query = $query->where( function ($query) use ($texto) {
            $query->where('usuarios.usu_identificacion', 'like', '%'.$texto.'%')
            ->orWhere('usuarios.usu_nombre', 'like', '%'.$texto.'%')
            ->orWhere('usuarios.usu_email', 'like', '%'.$texto.'%')
            ->orWhere('usuarios.estado', 'like', '%'.$texto.'%')
            ->orWhere('etl_usuarios_ecm.estado', 'like', '%'.$texto.'%');
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
    public function scopeOrderByColumn($query, $columnaOrden = 'codigo', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'identificacion':
                return $query->leftJoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'etl_usuarios_ecm.usu_id')
                    ->orderBy('usuarios.usu_identificacion', $ordenDireccion);
            break;
            case 'nombres':
                return $query->leftJoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'etl_usuarios_ecm.usu_id')
                    ->orderBy('usuarios.usu_nombre', $ordenDireccion);
            break;
            case 'email':
                return $query->leftJoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'etl_usuarios_ecm.usu_id')
                    ->orderBy('usuarios.usu_email', $ordenDireccion);
            break;
            case 'estado_usuario':
                return $query->leftJoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'etl_usuarios_ecm.usu_id')
                    ->orderBy('usuarios.estado', $ordenDireccion);
            break;
            case 'estado':
                $orderBy = 'estado';
            break;
            default:
                $orderBy = 'fecha_modificacion';
            break;
        }

        if(strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'desc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }
    // END SCOPES
}
