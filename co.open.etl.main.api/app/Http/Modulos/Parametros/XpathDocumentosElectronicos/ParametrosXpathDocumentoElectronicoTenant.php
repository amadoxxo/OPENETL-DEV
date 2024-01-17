<?php

namespace App\Http\Modulos\Parametros\XpathDocumentosElectronicos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Parametros\XpathDocumentosElectronicos\TenantParametrosXpathDocumentoElectronico;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ParametrosXpathDocumentoElectronicoTenant extends TenantParametrosXpathDocumentoElectronico {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'xde_id',
        'ofe_id',
        'xde_aplica_para',
        'xde_descripcion',
        'xde_xpath',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente'
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
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     * 
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->where( function ($query) use ($texto) {
            $query->where('xde_aplica_para', 'like', '%'.$texto.'%')
                ->orWhere('xde_descripcion', 'like', '%'.$texto.'%')
                ->orWhere('xde_xpath', 'like', '%'.$texto.'%')
                ->orWhere('estado', $texto)
                ->orWhereHas('getConfiguracionObligadoFacturarElectronicamente', function($queryHas) use ($texto) {
                    $queryHas->where('ofe_otros_nombres', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_primer_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_primer_nombre', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_razon_social', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_segundo_apellido', 'like', '%'.$texto.'%');
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
            case 'oferente':
                $orderBy = 'ofe_id';
                break;
            case 'aplica_para':
                $orderBy = 'xde_aplica_para';
                break;
            case 'descripcion':
                $orderBy = 'xde_descripcion';
                break;
            case 'creacion':
                $orderBy = 'fecha_creacion';
                break;
            case 'modificacion':
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
