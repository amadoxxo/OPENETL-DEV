<?php

namespace App\Http\Modulos\Parametros\SectorCambiario\DebidaDiligencia;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\SectorCambiario\DebidaDiligencia\MainParametrosDebidaDiligencia;

class ParametrosDebidaDiligencia extends MainParametrosDebidaDiligencia {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'ddi_id',
        'ddi_codigo',
        'ddi_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    // RELACIONES
    /**
     * Relación con el modelo de usuarios.
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
    // FIN RELACIONES

    // SCOPES
    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo.
     * 
     * @param Builder $query Query de la consulta
     * @param string  $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral(Builder $query, string $texto): Builder {
        return $query->orWhere( function ($query) use ($texto) {
            $query->where('ddi_codigo', 'like', '%'.$texto.'%')
            ->orWhere('ddi_descripcion', 'like', '%'.$texto.'%')
            ->orWhere('fecha_vigencia_desde', 'like', '%'.$texto.'%')
            ->orWhere('fecha_vigencia_hasta', 'like', '%'.$texto.'%')
            ->orWhere('estado', $texto)
            ->orWhere(function($query) use ($texto){
                $query->with('getUsuarioCreacion')
                ->wherehas('getUsuarioCreacion', function ($query) use ($texto) {
                    $query->where('usuario_creacion', 'like', '%'.$texto.'%');
                });
            });            
        });
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas.
     * 
     * @param Builder $query Query de la consulta
     * @param string  $columnaOrden string columna sobre la cual se debe ordenar
     * @param string  $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn(Builder $query, string $columnaOrden = 'fecha_modificacion', string $ordenDireccion = 'desc'): Builder {
        switch($columnaOrden){
            case 'codigo':
                $orderBy = 'ddi_codigo';
                break;
            case 'descripcion':
                $orderBy = 'ddi_descripcion';
                break;
            case 'vigencia_desde':
                $orderBy = 'fecha_vigencia_desde';
                break;
            case 'vigencia_hasta':
                $orderBy = 'fecha_vigencia_hasta';
                break;
            case 'fecha_creacion':
            case 'fecha_modificacion':
            case 'estado':
                $orderBy = $columnaOrden;
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
