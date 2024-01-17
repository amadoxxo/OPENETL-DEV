<?php

namespace App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Configuracion\GruposTrabajo\ConfiguracionGruposTrabajo\TenantConfiguracionGrupoTrabajo;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionGrupoTrabajo extends TenantConfiguracionGrupoTrabajo {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'gtr_id',
        'ofe_id',
        'gtr_codigo',
        'gtr_nombre',
        'gtr_correos_notificacion',
        'gtr_servicio',
        'gtr_por_defecto',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente'
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
        return $query->where('gtr_codigo', 'like', '%'.$texto.'%')
            ->orWhere('gtr_nombre', 'like', '%'.$texto.'%')
            ->orWhere('etl_grupos_trabajo.estado', 'like', '%'.$texto.'%')
            ->orWhereHas('getConfiguracionObligadoFacturarElectronicamente', function($queryHas) use ($texto) {
                $queryHas->where('ofe_identificacion', 'like', '%'.$texto.'%');
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
            case 'oferente':
                return $query->join('etl_obligados_facturar_electronicamente as oferente', 'oferente.ofe_id', '=', 'etl_grupos_trabajo.ofe_id')
                    ->orderBy('oferente.ofe_identificacion', $ordenDireccion);
                break;
            case 'codigo':
                $orderBy = 'gtr_codigo';
                break;
            case 'nombre':
                $orderBy = 'gtr_nombre';
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