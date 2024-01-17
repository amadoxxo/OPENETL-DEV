<?php

namespace App\Http\Modulos\FacturacionWeb\Parametros\Descuentos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use openEtl\Main\Traits\FechaVigenciaValidations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuento;
use openEtl\Tenant\Models\Documentos\FacturacionWeb\EtlFacturacionWebDescuentos\TenantEtlFacturacionWebDescuento;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlFacturacionWebDescuento extends TenantEtlFacturacionWebDescuento {
    use FechaVigenciaValidations;

    protected $visible = [
        'dmd_id',
        'ofe_id',
        'dmd_codigo',
        'dmd_descripcion',
        'dmd_porcentaje',
        'cde_id',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getCodigoDescuento',
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
     * Relación con el modelo códigos de descuento.
     *
     * @return BelongsTo
     */
    public function getCodigoDescuento() {
        return $this->belongsTo(ParametrosCodigoDescuento::class, 'cde_id');
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
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     *
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->orWhere( function ($query) use ($texto) {
            $query->where('dmd_codigo', 'like', '%'.$texto.'%')
            ->orWhere('dmd_descripcion', 'like', '%'.$texto.'%')
            ->orWhere('dmd_porcentaje', 'like', '%'.$texto.'%')
            ->orWhere('estado', $texto)
            ->orWhere(function($query) use ($texto){
                $query->with('getConfiguracionObligadoFacturarElectronicamente')
                    ->wherehas('getConfiguracionObligadoFacturarElectronicamente', function ($query) use ($texto) {
                        $query->where('ofe_identificacion', 'like', '%'.$texto.'%');
                    });
            })
            ->orWhereRaw('exists (select * from `etl_openmain`.`etl_codigos_descuentos` where `etl_facturacion_web_descuentos`.`cde_id` = `etl_openmain`.`etl_codigos_descuentos`.`cde_id` and (`cde_codigo` like ? or `cde_descripcion` like ?))', ['%'.$texto.'%', '%'.$texto.'%']);
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
        $camposSelect = [
            "etl_facturacion_web_descuentos.dmd_id", 
            "etl_facturacion_web_descuentos.ofe_id", 
            "etl_facturacion_web_descuentos.dmd_codigo", 
            "etl_facturacion_web_descuentos.dmd_descripcion", 
            "etl_facturacion_web_descuentos.dmd_porcentaje", 
            "etl_facturacion_web_descuentos.cde_id", 
            "etl_facturacion_web_descuentos.usuario_creacion", 
            "etl_facturacion_web_descuentos.fecha_creacion", 
            "etl_facturacion_web_descuentos.fecha_modificacion", 
            "etl_facturacion_web_descuentos.estado",
            "etl_facturacion_web_descuentos.fecha_actualizacion"
        ];

        switch($columnaOrden){
            case 'ofe_identificacion':
                return $query->select($camposSelect)
                    ->join('etl_obligados_facturar_electronicamente', 'etl_obligados_facturar_electronicamente.ofe_id', '=', 'etl_facturacion_web_descuentos.ofe_id')
                    ->orderBy('etl_obligados_facturar_electronicamente.ofe_identificacion', $ordenDireccion);
                break;
            case 'codigo':
                $orderBy = 'dmd_codigo';
                break;
            case 'descripcion':
                $orderBy = 'dmd_descripcion';
                break;
            case 'porcentaje':
                $orderBy = 'dmd_porcentaje';
                break;
            case 'codigo_descuento':
                return $query->select($camposSelect)
                    ->join('etl_openmain.etl_codigos_descuentos', 'etl_openmain.etl_codigos_descuentos.cde_id', '=', 'etl_facturacion_web_descuentos.cde_id')
                    ->orderBy('etl_openmain.etl_codigos_descuentos.cde_codigo', $ordenDireccion);
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