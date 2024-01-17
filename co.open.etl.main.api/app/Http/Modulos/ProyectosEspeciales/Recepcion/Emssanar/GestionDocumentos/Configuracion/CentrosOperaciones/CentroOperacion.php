<?php

namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\ProyectosEspeciales\Recepcion\Emssanar\Configuracion\CentrosOperaciones\TenantCentroOperacion;


class CentroOperacion extends TenantCentroOperacion {
    /**
     * Atributos visibles del modelo.
     * 
     * @var array
     */
    protected $visible = [
        'cop_id',
        'cop_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
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
     * Local Scope que permite ordenar querys por diferentes columnas.
     * 
     * @param Builder $query
     * @param string $columnaOrden string columna sobre la cual se debe ordenar
     * @param string $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn(Builder $query, string $columnaOrden = 'fecha_modificacion', string $ordenDireccion = 'desc'): Builder {
        switch($columnaOrden){
            case 'descripcion':
                $orderBy = 'cop_descripcion';
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

    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo.
     * 
     * @param Builder $query Query de la consulta
     * @param string $texto Cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral(Builder $query, string $texto): Builder {
        return $query->where( function ($query) use ($texto) {
            $query->where('cop_descripcion', 'like', '%'.$texto.'%');
        });
    }
    // END SCOPES
}
