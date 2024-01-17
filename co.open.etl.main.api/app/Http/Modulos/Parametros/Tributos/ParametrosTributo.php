<?php

namespace App\Http\Modulos\Parametros\Tributos;

use App\Http\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\Tributos\MainParametrosTributo;

class ParametrosTributo extends MainParametrosTributo {
    protected $visible = [
        'tri_id',
        'tri_codigo',
        'tri_nombre',
        'tri_tipo',
        'tri_aplica_persona',
        'tri_aplica_para_personas',
        'tri_aplica_tributo',
        'tri_aplica_para_tributo',
        'tri_descripcion',
        'tri_codigo_descripion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    // INICIO RELACION

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
    // FIN RELACION

    // SCOPES
    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo.
     * 
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->orWhere( function ($query) use ($texto) {
            $query->where('tri_codigo', 'like', '%'.$texto.'%')
            ->orWhere('tri_descripcion', 'like', '%'.$texto.'%')
            ->orWhere('tri_nombre', 'like', '%'.$texto.'%')
            ->orWhere('tri_tipo', 'like', '%'.$texto.'%')
            ->orWhere('tri_aplica_persona', 'like', '%'.$texto.'%')
            ->orWhere('tri_aplica_para_personas', 'like', '%'.$texto.'%')
            ->orWhere('tri_aplica_tributo', 'like', '%'.$texto.'%')
            ->orWhere('tri_aplica_para_tributo', 'like', '%'.$texto.'%')
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
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'modificado', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'codigo':
                $orderBy = DB::Raw('abs(tri_codigo)');
                break;
            case 'creado':
                $orderBy = 'fecha_creacion';
                break;
            case 'nombre':
                $orderBy = 'tri_nombre';
                break;
            case 'tipo':
                $orderBy = 'tri_tipo';
                break;
            case 'descripcion':
                $orderBy = 'tri_descripcion';
                break;
            case 'aplica_persona':
                $orderBy = 'tri_aplica_persona';
                break;
            case 'aplica_para_personas':
                $orderBy = 'tri_aplica_para_personas';
                break;
            case 'aplica_tributo':
                $orderBy = 'tri_aplica_tributo';
                break;
            case 'aplica_para_tributo':
                $orderBy = 'tri_aplica_para_tributo';
                break;
            case 'vigencia_desde':
                $orderBy = 'fecha_vigencia_desde';
                break;
            case 'vigencia_hasta':
                $orderBy = 'fecha_vigencia_hasta';
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
