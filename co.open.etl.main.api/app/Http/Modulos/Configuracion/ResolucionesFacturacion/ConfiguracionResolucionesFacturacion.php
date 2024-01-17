<?php

namespace App\Http\Modulos\Configuracion\ResolucionesFacturacion;

use App\Http\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\Configuracion\ResolucionesFacturacion\TenantConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionResolucionesFacturacion extends TenantConfiguracionResolucionesFacturacion {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'rfa_id',
        'ofe_id',
        'rfa_resolucion',
        'rfa_prefijo',
        'rfa_clave_tecnica',
        'rfa_tipo',
        'rfa_fecha_desde',
        'rfa_fecha_hasta',
        'rfa_consecutivo_inicial',
        'rfa_consecutivo_final',
        'cdo_control_consecutivos',
        'cdo_consecutivo_provisional',
        'rfa_dias_aviso',
        'rfa_consecutivos_aviso',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'usuario_creacion',
        'estado',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];

    /**
     * Mutador que permite establecer el valor de la columna en null cuando el valor recibido es diferente de SI.
     *
     * @param string $value Valor recibido
     * @return void
     */
    public function setCdoControlConsecutivosAttribute($value) {
        $this->attributes['cdo_control_consecutivos'] = $value != 'SI' ? null : $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna en null cuando el valor recibido es diferente de SI.
     *
     * @param string $value Valor recibido
     * @return void
     */
    public function setCdoConsecutivoProvisionalAttribute($value) {
        $this->attributes['cdo_consecutivo_provisional'] = $value != 'SI' ? null : $value;
    }

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
     * Relación con el modelo OFE.
     * 
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente()
    {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id')->select([
            'ofe_id',
            'sft_id',
            'tdo_id',
            'toj_id',
            'ofe_identificacion',
            'ofe_razon_social',
            'ofe_nombre_comercial',
            'ofe_primer_apellido',
            'ofe_segundo_apellido',
            'ofe_primer_nombre',
            'ofe_otros_nombres',
        ]);
    }
    // FIN RELACIONES

    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     *
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto)
    {
        $comodin = "'%$texto%'";
        $sqlOfes = "etl_resoluciones_facturacion.ofe_id IN (SELECT etl_obligados_facturar_electronicamente.ofe_id FROM `etl_obligados_facturar_electronicamente` WHERE `ofe_identificacion` LIKE {$comodin})";
        $query->whereRaw($sqlOfes)
        ->orWhere('rfa_resolucion', 'like', '%' . $texto . '%')
        ->orWhere('rfa_prefijo', 'like', '%' . $texto . '%')
        ->orWhere('rfa_fecha_desde', 'like', '%' . $texto . '%')
        ->orWhere('rfa_fecha_hasta', 'like', '%' . $texto . '%')
        ->orWhere('rfa_consecutivo_inicial', 'like', '%' . $texto . '%')
        ->orWhere('rfa_consecutivo_final', 'like', '%' . $texto . '%')
        ->orWhere('etl_resoluciones_facturacion.estado',  $texto )
        ->orWhereHas('getConfiguracionObligadoFacturarElectronicamente', function ($query) use ($texto) {
            $query->where('etl_resoluciones_facturacion.ofe_id', 'like', '%' . $texto . '%');
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
    public function scopeOrderByColumn($query, $columnaOrden = 'ofe', $ordenDireccion = 'asc')
    {
        if( strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'asc';

        switch ($columnaOrden) {
            case 'ofe':
                return $query->select(["etl_resoluciones_facturacion.ofe_id", 
                                        "etl_resoluciones_facturacion.rfa_id", 
                                        "etl_resoluciones_facturacion.rfa_resolucion", 
                                        "etl_resoluciones_facturacion.rfa_prefijo", 
                                        "etl_resoluciones_facturacion.rfa_clave_tecnica", 
                                        "etl_resoluciones_facturacion.rfa_fecha_desde", 
                                        "etl_resoluciones_facturacion.rfa_fecha_hasta", 
                                        "etl_resoluciones_facturacion.rfa_consecutivo_inicial", 
                                        "etl_resoluciones_facturacion.rfa_consecutivo_final", 
                                        "etl_resoluciones_facturacion.usuario_creacion", 
                                        "etl_resoluciones_facturacion.estado",
                    ])
                    ->join('etl_obligados_facturar_electronicamente', 'etl_obligados_facturar_electronicamente.ofe_id', '=', 'etl_resoluciones_facturacion.ofe_id')
                    ->orderBy('etl_obligados_facturar_electronicamente.ofe_identificacion', $ordenDireccion);
            case 'resolucion':
                $orderBy = 'rfa_resolucion';
                break;
            case 'prefijo':
                $orderBy = 'rfa_prefijo';
                break;
            case 'fecha_desde':
                $orderBy = 'rfa_fecha_desde';
                break;
            case 'fecha_hasta':
                $orderBy = 'rfa_fecha_hasta';
                break;
            case 'consecutivo_inicial':
                $orderBy = DB::Raw('abs(rfa_consecutivo_inicial)');
                break;
            case 'consecutivo_final':
                $orderBy = DB::Raw('abs(rfa_consecutivo_final)');
                break;
            case 'estado':
                $orderBy = 'estado';
                break;
            default:
                $orderBy = 'fecha_modificacion';
                break;
        }

        return $query->orderBy($orderBy, $ordenDireccion);
    }
}