<?php
namespace App\Http\Modulos\Recepcion\Documentos\RepFatDocumentosDaop;

use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use openEtl\Tenant\Models\Recepcion\Documentos\RepFatDocumentosDaop\TenantRepFatDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class RepFatDocumentoDaop extends TenantRepFatDocumentoDaop {
    protected $visible = [
        'cdo_id',
        'cdo_origen',
        'cdo_clasificacion',
        'gtr_id',
        'tde_id',
        'top_id',
        'fpa_id',
        'cdo_lote',
        'ofe_id',
        'pro_id',
        'rfa_prefijo',
        'cdo_consecutivo',
        'cdo_fecha',
        'cdo_cufe',
        'mon_id',
        'mon_id_extranjera',
        'cdo_valor_a_pagar',
        'cdo_valor_a_pagar_moneda_extranjera',
        'cdo_documentos_anexos',
        'cdo_rdi',
        'cdo_fecha_validacion_dian',
        'cdo_estado_dian',
        'cdo_get_status',
        'cdo_get_status_error',
        'cdo_acuse_recibo',
        'cdo_acuse_recibo_error',
        'cdo_recibo_bien',
        'cdo_recibo_bien_error',
        'cdo_estado_eventos_dian',
        'cdo_estado_eventos_dian_fecha',
        'cdo_estado_eventos_dian_resultado',
        'cdo_notificacion_evento_dian',
        'cdo_notificacion_evento_dian_resultado',
        'cdo_transmision_erp',
        'cdo_transmision_opencomex',
        'cdo_validacion',
        'cdo_validacion_valor',
        'cdo_usuario_responsable',
        'cdo_usuario_responsable_recibidos',
        'cdo_data_operativa',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionProveedor',
        'getParametrosMoneda',
        'getParametrosMonedaExtranjera',
        'getParametrosFormaPago',
        'getTipoDocumentoElectronico',
        'getUsuarioResponsable',
        'getUsuarioResponsableRecibidos',
    ];

    /**
     * Relación con la cabecera del documento.
     *
     * @return BelongsTo
     */
    public function getCabecera() {
        return $this->belongsTo(RepCabeceraDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Usuario responsable (Procesos FNC).
     *
     * @return BelongsTo
     */
    public function getUsuarioResponsable() {
        return $this->belongsTo(User::class, 'cdo_usuario_responsable');
    }

    /**
     * Usuario responsable en el tracking de documento recibidos.
     *
     * @return BelongsTo
     */
    public function getUsuarioResponsableRecibidos() {
        return $this->belongsTo(User::class, 'cdo_usuario_responsable_recibidos');
    }

    /**
     * Mutador que formatea a dos decimales el valor a pagar.
     *
     * @param null|float $value
     * @return string
     */
    public function getCdoValorAPagarAttribute($value) {
        return number_format($value, 2, ' . ', '');
    }

    /**
     * Mutador que formatea a dos decimales el valor a pagar en moneda extranjera.
     *
     * @param null|float $value
     * @return void
     */
    public function getCdoValorAPagarMonedaExtranjeraAttribute($value) {
        return number_format($value, 2, ' . ', '');
    }

    /**
     * Relación con OFEs.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con Proveedores.
     *
     * @return BelongsTo
     */
    public function getConfiguracionProveedor() {
        return $this->belongsTo(ConfiguracionProveedor::class, 'pro_id');
    }

    /**
     * Relación con Moneda.
     *
     * @return BelongsTo
     */
    public function getParametrosMoneda() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id');
    }

    /**
     * Relación con Moneda Extranjera.
     *
     * @return BelongsTo
     */
    public function getParametrosMonedaExtranjera() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id_extranjera');
    }

    /**
     * Relación con Forma de Pago.
     *
     * @return BelongsTo
     */
    public function getParametrosFormaPago() {
        return $this->belongsTo(ParametrosFormaPago::class, 'fpa_id');
    }

    /**
     * Retorna el Grupo de Trabajo al que pertenece el documento, siempre que el grupo este activo.
     *
     * @return BelongsTo
     */
    public function getGrupoTrabajo() {
        return $this->belongsTo(ConfiguracionGrupoTrabajo::class, 'gtr_id');
    }

    /**
     * Relación con el tipo de documento electrónico.
     *
     * @return BelongsTo
     */
    public function getTipoDocumentoElectronico() {
        return $this->belongsTo(ParametrosTipoDocumentoElectronico::class, 'tde_id');
    }

    /**
     * Scope que permite ordenar querys por diferentes columnas.
     *
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn(Builder $query, string $columnaOrden = 'fecha', string $ordenDireccion = 'desc'): Builder{
        switch($columnaOrden){
            case 'lote':
                $orderBy = 'cdo_lote';
                break;
            case 'clasificacion':
                $orderBy = 'cdo_clasificacion';
                break;
            case 'documento': // TODO: SE PUEDE ORDENAR PERO POR EL ABS ES MÁS DEMORADO
                return $query->orderBy('rfa_prefijo', $ordenDireccion)
                    ->orderBy(DB::Raw('abs(cdo_consecutivo)'), $ordenDireccion);
                break;
            case 'proveedor': // TODO: NO TIENE INDICE SOBRE ESAS COLUMNAS EN LA TABLA DE PROVEEDORES, POR LO TANTO NO DEBEMOS PERMITIR ORDENAR
                return $query->join('rep_proveedores as proveedor', 'proveedor.pro_id', '=', 'rep_cabecera_documentos_daop.pro_id')
                    ->orderBy(DB::Raw('IF(proveedor.pro_razon_social IS NULL OR proveedor.pro_razon_social = "", CONCAT(COALESCE(proveedor.pro_primer_nombre, ""), " ", COALESCE(proveedor.pro_primer_apellido, "")), proveedor.pro_razon_social)'), $ordenDireccion);
                break;
            case 'fecha':
                $orderBy = 'cdo_fecha';
                break;
            case 'moneda': // TODO: NO TIENE INDICE POR LO TANTO NO DEBEMOS PERMITIR ORDENAR
                return $query->join('etl_openmain.etl_monedas as monedas', 'monedas.mon_id', '=', 'rep_cabecera_documentos_daop.mon_id')
                    ->orderBy('monedas.mon_codigo', $ordenDireccion);
                break;
            case 'valor':
                $orderBy = 'cdo_valor_a_pagar';
                break;
            case 'estado':
                $orderBy = 'estado';
                break;
            case 'origen':
                $orderBy = 'cdo_origen';
                break;
            case 'usuario_responsable':
                return $query->leftjoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'rep_cabecera_documentos_daop.cdo_usuario_responsable')
                    ->orderBy('usuarios.usu_nombre', $ordenDireccion);
                break;
            case 'usuario_responsable_recibidos':
                return $query->leftjoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'rep_cabecera_documentos_daop.cdo_usuario_responsable_recibidos')
                    ->orderBy('usuarios.usu_nombre', $ordenDireccion);
                break;
            default:
                $orderBy = 'cdo_fecha';
                break;
        }

        if( strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'asc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }

    /**
     * Permite filtrar documentos asignados a un único grupo de trabajo, varios grupos de trabajo o la combinación de los dos anteriores
     * teniendo en cuenta los grupos a los cuales esta asignado el usuario autenticado.
     * 
     * Aplica solamente para los OFES que tengan configurados grupos de trabajo
     *
     * @param Builder $query Consulta en ejecuión
     * @param Request $request Parámetros de la petición en curso
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE con la relación a los grupos de trabajo
     * @param array $gruposTrabajoUsuario Array con los IDs de los grupos de trabajo a los que el usuario autenticado se encuentra asignado
     * @return Builder
     */
    public function scopeFiltroGruposTrabajoUsuarioAutenticado(Builder $query, Request $request, ConfiguracionObligadoFacturarElectronicamente $ofe, array $gruposTrabajoUsuario): Builder {
        return $query->when($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo == 'unico' && $ofe->getGruposTrabajo->count() > 0, function($query) use ($gruposTrabajoUsuario) {
            $query->has('getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo', '>', 0)
                ->where(function($query) use ($gruposTrabajoUsuario) {
                    $query->whereHas('getGrupoTrabajo', function($query) use ($gruposTrabajoUsuario) {
                        $query->whereIn('gtr_id', $gruposTrabajoUsuario);
                    })
                    ->orWhereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                        $query->where('estado', 'ACTIVO')
                            ->when(empty($gruposTrabajoUsuario), function ($query) {
                                $query->whereNull('gtr_id');
                            });
                    }, '=', 1);
                });
        })
        ->when($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo == 'compartido' && $ofe->getGruposTrabajo->count() > 0, function($query) use ($gruposTrabajoUsuario) {
            $query->has('getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo', '>', 0)
                ->where(function($query) use ($gruposTrabajoUsuario) {
                    $query->where(function($query) use ($gruposTrabajoUsuario) {
                        $query->doesntHave('getGrupoTrabajo')
                            ->orWhereHas('getGrupoTrabajo', function($query) use ($gruposTrabajoUsuario) {
                                $query->where('estado', 'INACTIVO')
                                    ->whereIn('gtr_id', $gruposTrabajoUsuario);
                            });
                    })
                    ->whereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                        $query->where('estado', 'ACTIVO')
                            ->when(empty($gruposTrabajoUsuario), function ($query) {
                                $query->whereNull('gtr_id');
                            });
                    }, '>', 1);
                });
        })
        ->when(!$request->filled('filtro_grupos_trabajo') && $ofe->getGruposTrabajo->count() > 0, function($query) use ($gruposTrabajoUsuario) {
            $query->has('getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo', '>', 0)
                ->where(function($query) use ($gruposTrabajoUsuario) {
                    $query->where(function($query) use ($gruposTrabajoUsuario) {
                        $query->whereHas('getGrupoTrabajo', function($query) use ($gruposTrabajoUsuario) {
                            $query->whereIn('gtr_id', $gruposTrabajoUsuario);
                        })
                        ->orWhereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                            $query->where('estado', 'ACTIVO')
                                ->when(empty($gruposTrabajoUsuario), function ($query) {
                                    $query->whereNull('gtr_id');
                                });
                        }, '=', 1);
                    })
                    ->orWhere(function($query) use ($gruposTrabajoUsuario) {
                        $query->where(function($query) use ($gruposTrabajoUsuario) {
                            $query->doesntHave('getGrupoTrabajo')
                                ->orWhereHas('getGrupoTrabajo', function($query) use ($gruposTrabajoUsuario) {
                                    $query->where('estado', 'INACTIVO')
                                        ->whereIn('gtr_id', $gruposTrabajoUsuario);
                                });
                        })
                        ->whereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                            $query->where('estado', 'ACTIVO')
                                ->when(empty($gruposTrabajoUsuario), function ($query) {
                                    $query->whereNull('gtr_id');
                                });
                        }, '>', 1);
                    });
                });
        });
    }

    /**
     * Permite filtrar documentos asignados a un único grupo de trabajo, teniendo en cuenta si el documento esta asignado directamente a un grupo
     * o si el proveedor del documento pertenece a un solo grupo y dicho grupo corresponde al seleccionado en el filtro.
     *
     * @param Builder $query Consulta en ejecuión
     * @param Request $request Parámetros de la petición en curso
     * @return Builder
     */
    public function scopeFiltroGruposTrabajoTracking(Builder $query, Request $request): Builder {
        return $query->where(function($query) use ($request) {
            $query->where('gtr_id', $request->filtro_grupos_trabajo_usuario)
                ->orWhere(function($query) use ($request) {
                    $query->whereNull('gtr_id')
                        ->whereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($request) {
                            $query->where('estado', 'ACTIVO')
                                ->groupBy('pro_id')
                                ->havingRaw('count(*) = 1 and count(case when gtr_id = ' . $request->filtro_grupos_trabajo_usuario . ' then 1 end) = 1');
                        });
                });
        });
    }

    /**
     * Permite filtrar documentos dependiendo del estado de validación.
     * 
     * Aplicable solamente a OFEs de FNC
     *
     * @param Builder $query Consulta en ejecuión
     * @param Request $request Parámetros de la petición en curso
     * @return Builder
     */
    public function scopeFiltroEstadoValidacionDocumento(Builder $query, Request $request): Builder {
        return $query->where(function($query) use ($request) {
            foreach($request->estado_validacion as $indice => $estadoValidacion) {
                $estResultado = '';

                switch($estadoValidacion) {
                    case 'pendiente':
                        $estResultado = 'PENDIENTE';
                        break;
                    case 'validado':
                        $estResultado = 'VALIDADO';
                        break;
                    case 'rechazado':
                        if(!in_array('sin_gestion_rechazado', $request->estado_validacion))
                            $estResultado = 'RECHAZADO';
                        break;
                    case 'pagado':
                        $estResultado = 'PAGADO';
                        break;
                }

                if($indice == 0 && !empty($estResultado) && $estadoValidacion != 'sin_gestion' && $estadoValidacion != 'sin_gestion_rechazado')
                    $query->where('cdo_validacion', $estResultado);
                elseif($indice == 0 && empty($estResultado) && $estadoValidacion == 'sin_gestion')
                    $query->whereNull('cdo_validacion');
                elseif($indice == 0 && empty($estResultado) && $estadoValidacion == 'sin_gestion_rechazado')
                    $query->where(function($query) {
                        $query->whereNull('cdo_validacion')
                            ->orWhere('cdo_validacion', 'RECHAZADO');
                    });
                elseif($indice != 0 && !empty($estResultado) && $estadoValidacion != 'sin_gestion' && $estadoValidacion != 'sin_gestion_rechazado')
                    $query->orWhere('cdo_validacion', $estResultado);
                elseif($indice != 0 && empty($estResultado) && $estadoValidacion == 'sin_gestion' && !in_array('sin_gestion_rechazado', $request->estado_validacion))
                    $query->orWhereNull('cdo_validacion');
                elseif($indice != 0 && empty($estResultado) && $estadoValidacion == 'sin_gestion_rechazado')
                    $query->orWhere(function($query) {
                        $query->whereNull('cdo_validacion')
                            ->orWhere('cdo_validacion', 'RECHAZADO');
                    });
            }
        });
    }
}
