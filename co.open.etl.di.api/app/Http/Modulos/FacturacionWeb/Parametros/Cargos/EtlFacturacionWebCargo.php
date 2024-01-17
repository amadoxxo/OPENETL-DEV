<?php

namespace App\Http\Modulos\FacturacionWeb\Parametros\Cargos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Documentos\FacturacionWeb\EtlFacturacionWebCargos\TenantEtlFacturacionWebCargo;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlFacturacionWebCargo extends TenantEtlFacturacionWebCargo {
    protected $visible = [
        'dmc_id',
        'ofe_id',
        'dmc_codigo',
        'dmc_descripcion',
        'dmc_porcentaje',
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
     * Relación con el modelo de OFEs.
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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $texto cadena de texto a buscar
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->orWhere( function ($query) use ($texto) {
            $query->where('dmc_codigo', 'like', '%'.$texto.'%')
            ->orWhere('dmc_descripcion', 'like', '%'.$texto.'%')
            ->orWhere('dmc_porcentaje', 'like', '%'.$texto.'%')
            ->orWhere('estado', $texto)
            ->orWhere(function($query) use ($texto){
                $query->with('getConfiguracionObligadoFacturarElectronicamente')
                ->wherehas('getConfiguracionObligadoFacturarElectronicamente', function ($query) use ($texto) {
                    $query->where('ofe_identificacion', 'like', '%'.$texto.'%');
                });
            });
        });
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'codigo', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'ofe_identificacion':
                return $query->select(["etl_facturacion_web_cargos.dmc_id", 
                        "etl_facturacion_web_cargos.ofe_id", 
                        "etl_facturacion_web_cargos.dmc_codigo", 
                        "etl_facturacion_web_cargos.dmc_descripcion", 
                        "etl_facturacion_web_cargos.dmc_porcentaje", 
                        "etl_facturacion_web_cargos.usuario_creacion", 
                        "etl_facturacion_web_cargos.fecha_creacion", 
                        "etl_facturacion_web_cargos.fecha_modificacion", 
                        "etl_facturacion_web_cargos.estado", 
                        "etl_facturacion_web_cargos.fecha_actualizacion"
                    ])
                    ->join('etl_obligados_facturar_electronicamente', 'etl_obligados_facturar_electronicamente.ofe_id', '=', 'etl_facturacion_web_cargos.ofe_id')
                    ->orderBy('etl_obligados_facturar_electronicamente.ofe_identificacion', $ordenDireccion);
                break;
            case 'codigo':
                $orderBy = 'dmc_codigo';
                break;
            case 'descripcion':
                $orderBy = 'dmc_descripcion';
                break;
            case 'porcentaje':
                $orderBy = 'dmc_porcentaje';
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