<?php

namespace App\Http\Modulos\Parametros\SectorCambiario\MandatosProfesional;

use App\Http\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\SectorCambiario\MandatosProfesional\MainParametrosMandatoProfesional;

class ParametrosMandatoProfesional extends MainParametrosMandatoProfesional {
    protected $visible = [
        'cmp_id',
        'cmp_codigo',
        'cmp_significado',
        'cmp_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
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
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     * 
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->orWhere( function ($query) use ($texto) {
            $query->where('cmp_codigo', 'like', '%'.$texto.'%')
            ->orWhere('cmp_significado', 'like', '%'.$texto.'%')
            ->orWhere('cmp_descripcion', 'like', '%'.$texto.'%')
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
                $orderBy = DB::Raw('abs(cmp_codigo)');
                break;
            case 'creado':
                $orderBy = 'fecha_creacion';
                break;
            case 'descripcion':
                $orderBy = 'cmp_descripcion';
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