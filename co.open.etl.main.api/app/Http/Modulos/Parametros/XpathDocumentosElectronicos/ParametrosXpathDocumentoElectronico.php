<?php

namespace App\Http\Modulos\Parametros\XpathDocumentosElectronicos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\XpathDocumentosElectronicos\MainParametrosXpathDocumentoElectronico;

class ParametrosXpathDocumentoElectronico extends MainParametrosXpathDocumentoElectronico {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'xde_id',
        'xde_aplica_para',
        'xde_descripcion',
        'xde_xpath',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

    // RELACIONES
    /**
     * Relación con el usuario de creación.
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
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     * 
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->orWhere( function ($query) use ($texto) {
            $query->where('xde_aplica_para', 'like', '%'.$texto.'%')
            ->orWhere('xde_descripcion', 'like', '%'.$texto.'%')
            ->orWhere('xde_xpath', 'like', '%'.$texto.'%')
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
            case 'aplica_para':
                $orderBy = 'xde_aplica_para';
                break;
            case 'descripcion':
                $orderBy = 'xde_descripcion';
                break;
            case 'xpath':
                $orderBy = 'xde_xpath';
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
