<?php

namespace App\Http\Modulos\FacturacionWeb\Parametros\Productos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Unidades\ParametrosUnidad;
use App\Http\Modulos\Parametros\PartidasArancelarias\ParametrosPartidaArancelaria;
use App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProducto;
use App\Http\Modulos\Parametros\ColombiaCompraEficiente\ParametroColombiaCompraEficiente;
use openEtl\Tenant\Models\Documentos\FacturacionWeb\EtlFacturacionWebProductos\TenantEtlFacturacionWebProducto;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlFacturacionWebProducto extends TenantEtlFacturacionWebProducto {
    protected $visible = [
        'dmp_id',
        'ofe_id',
        'cpr_id',
        'dmp_codigo',
        'dmp_descripcion_uno',
        'dmp_descripcion_dos',
        'dmp_descripcion_dos_editable',
        'dmp_descripcion_tres',
        'dmp_descripcion_tres_editable',
        'dmp_nota',
        'dmp_nota_editable',
        'dmp_tipo_item',
        'und_id',
        'dmp_cantidad_paquete',
        'dmp_valor',
        'dmp_tributos',
        'dmp_autoretenciones',
        'dmp_retenciones_sugeridas',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getClasificacionProducto',
        'getUnidad',
        'getPartidaArancelaria',
        'getColombiaCompraEficiente'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     * 
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }

    /**
     * Relación con el modelo OFEs.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }
    
    /**
     * Relación con el modelo Clasificación de Producto.
     *
     * @return BelongsTo
     */
    public function getClasificacionProducto() {
        return $this->belongsTo(ParametrosClasificacionProducto::class, 'cpr_id');
    }

    /**
     * Relación con el modelo Unidad.
     *
     * @return BelongsTo
     */
    public function getUnidad() {
        return $this->belongsTo(ParametrosUnidad::class, 'und_id');
    }

    /**
     * Relación con el modelo Partidas Arancelarias.
     * 
     * Esta relación se utiliza cuando el código de la clasificación del producto es 020 y la relación se construye del código del producto hacia el código de la Partida Arancelaría.
     *
     * @return BelongsTo
     */
    public function getPartidaArancelaria() {
        return $this->belongsTo(ParametrosPartidaArancelaria::class, 'dmp_codigo', 'par_codigo');
    }

    /**
     * Relación con el modelo Colombia Compra Eficiente.
     * 
     * Esta relación se utiliza cuando el código de la clasificación del producto es 001 y la relación se construye del código del producto hacia el código de Colombia Compra Eficiente.
     *
     * @return BelongsTo
     */
    public function getColombiaCompraEficiente() {
        return $this->belongsTo(ParametroColombiaCompraEficiente::class, 'dmp_codigo', 'cce_codigo');
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
            $query->where('dmp_codigo', 'like', '%'.$texto.'%')
            ->orWhere('dmp_descripcion_uno', 'like', '%'.$texto.'%')
            ->orWhere('dmp_descripcion_dos', 'like', '%'.$texto.'%')
            ->orWhere('dmp_descripcion_tres', 'like', '%'.$texto.'%')
            ->orWhere('dmp_nota', 'like', '%'.$texto.'%')
            ->orWhere('dmp_tipo_item', 'like', '%'.$texto.'%')
            ->orWhere('dmp_cantidad_paquete', 'like', '%'.$texto.'%')
            ->orWhere('dmp_valor', 'like', '%'.$texto.'%')
            ->orWhere('dmp_tributos', 'like', '%'.$texto.'%')
            ->orWhere('dmp_autoretenciones', 'like', '%'.$texto.'%')
            ->orWhere('dmp_retenciones_sugeridas', 'like', '%'.$texto.'%')
            ->orWhere('estado', $texto)
            ->orWhere(function($query) use ($texto) {
                $query->with('getConfiguracionObligadoFacturarElectronicamente')
                    ->whereHas('getConfiguracionObligadoFacturarElectronicamente', function ($query) use ($texto) {
                        $query->where('ofe_razon_social', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_nombre_comercial', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_primer_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_segundo_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_primer_nombre', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_otros_nombres', 'like', '%'.$texto.'%')
                        ->orWhere('ofe_identificacion', 'like', '%'.$texto.'%');
                    });
            })
            ->orWhereRaw('exists (select * from `etl_openmain`.`etl_clasificacion_productos` where `etl_facturacion_web_productos`.`cpr_id` = `etl_openmain`.`etl_clasificacion_productos`.`cpr_id` and (`etl_openmain`.`etl_clasificacion_productos`.`cpr_codigo` like "%' . $texto . '%" or `etl_openmain`.`etl_clasificacion_productos`.`cpr_nombre` like "%' . $texto . '%" or `etl_openmain`.`etl_clasificacion_productos`.`cpr_descripcion` like "%' . $texto . '%"))')
            ->orWhereRaw('exists (select * from `etl_openmain`.`etl_unidades` where `etl_facturacion_web_productos`.`und_id` = `etl_openmain`.`etl_unidades`.`und_id` and (`etl_openmain`.`etl_unidades`.`und_codigo` like "%' . $texto . '%" or `etl_openmain`.`etl_unidades`.`und_descripcion` like "%' . $texto . '%"))');
        });
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas
     *
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'codigo', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'dmp_codigo':
                $orderBy = 'dmp_codigo';
                break;
            case 'dmp_descripcion_uno':
                $orderBy = 'dmp_descripcion_uno';
                break;
            case 'creado':
                $orderBy = 'fecha_creacion';
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
