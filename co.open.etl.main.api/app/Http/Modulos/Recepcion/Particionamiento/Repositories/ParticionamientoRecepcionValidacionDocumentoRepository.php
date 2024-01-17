<?php
namespace App\Http\Modulos\Recepcion\Particionamiento\Repositories;

use Illuminate\Http\Request;
use App\Traits\GruposTrabajoTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepFatDocumentosDaop\RepFatDocumentoDaop;
use App\Http\Modulos\Recepcion\Particionamiento\Helpers\HelperRecepcionParticionamiento;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Clase encargada del procesamiento lógico frente al motor de base de datos.
 */
class ParticionamientoRecepcionValidacionDocumentoRepository {
    
    use GruposTrabajoTrait;

    /**
     * Nombre de la conexión Tenant por defecto a la base de datos.
     */
    public const CONEXION = 'conexion01';

    /**
     * Nombre de referencia a la tabla rep_fat_documentos_daop.
     * 
     * @var string
     */
    protected $tablaFat = 'rep_fat_documentos_daop';

    /**
     * Constructor de la clase.
     *
     * @return void
     */
    public function __construct() {
        TenantTrait::GetVariablesSistemaTenant();
    }

    /**
     * Consulta de la Validación de Documentos.
     *
     * @param Request $request Parámetros de la petición
     * @return Collection
     */
    public function consultarValidacionDocumentos(Request $request): Collection {
        $columnasSelect = [
            $this->tablaFat.'.cdo_id',
            $this->tablaFat.'.cdo_origen',
            $this->tablaFat.'.cdo_clasificacion',
            $this->tablaFat.'.gtr_id',
            $this->tablaFat.'.tde_id',
            $this->tablaFat.'.fpa_id',
            $this->tablaFat.'.cdo_lote',
            $this->tablaFat.'.ofe_id',
            $this->tablaFat.'.pro_id',
            $this->tablaFat.'.rfa_prefijo',
            $this->tablaFat.'.cdo_consecutivo',
            $this->tablaFat.'.cdo_fecha',
            $this->tablaFat.'.cdo_cufe',
            $this->tablaFat.'.mon_id',
            $this->tablaFat.'.mon_id_extranjera',
            $this->tablaFat.'.cdo_valor_a_pagar',
            $this->tablaFat.'.cdo_valor_a_pagar_moneda_extranjera',
            $this->tablaFat.'.cdo_documentos_anexos',
            $this->tablaFat.'.cdo_rdi',
            $this->tablaFat.'.cdo_fecha_validacion_dian',
            $this->tablaFat.'.cdo_estado_dian',
            $this->tablaFat.'.cdo_get_status',
            $this->tablaFat.'.cdo_get_status_error',
            $this->tablaFat.'.cdo_acuse_recibo',
            $this->tablaFat.'.cdo_acuse_recibo_error',
            $this->tablaFat.'.cdo_recibo_bien',
            $this->tablaFat.'.cdo_recibo_bien_error',
            $this->tablaFat.'.cdo_estado_eventos_dian',
            $this->tablaFat.'.cdo_estado_eventos_dian_resultado',
            $this->tablaFat.'.cdo_notificacion_evento_dian',
            $this->tablaFat.'.cdo_notificacion_evento_dian_resultado',
            $this->tablaFat.'.cdo_validacion',
            $this->tablaFat.'.cdo_validacion_valor',
            $this->tablaFat.'.cdo_transmision_erp',
            $this->tablaFat.'.cdo_transmision_opencomex',
            $this->tablaFat.'.cdo_usuario_responsable',
            $this->tablaFat.'.cdo_usuario_responsable_recibidos',
            $this->tablaFat.'.cdo_data_operativa',
            $this->tablaFat.'.estado',
            $this->tablaFat.'.fecha_creacion',
        ];

        if($request->filled('excel') && $request->excel == true) {
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasCabeceraSelect('get_cabecera_documentos', [
                'cdo_hora',
                'cdo_vencimiento',
                'cdo_observacion',
                'cdo_valor_sin_impuestos',
                'cdo_impuestos',
                'cdo_cargos',
                'cdo_descuentos',
                'cdo_redondeo',
                'cdo_valor_a_pagar',
                'cdo_anticipo',
                'cdo_retenciones'
            ]));

            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasOtrasTablasTenantSelect('get_moneda', ['mon_codigo']));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasEstadosSelect('get_estado_validacion_validado'));
        }

        $ofe                = $this->getOfeGrupos($request->ofe_id);
        $paginador          = $this->setParametrosPaginador($request);
        $consultaDocumentos = RepFatDocumentoDaop::select($columnasSelect)
            ->where($this->tablaFat.'.ofe_id', $request->ofe_id)
            ->when($request->filled('pro_id') && !empty($request->pro_id), function ($query) use ($request) {
                $query->whereIn($this->tablaFat.'.pro_id', $request->pro_id);
            })
            ->when($request->filled('cdo_origen'), function ($query) use ($request) {
                $query->where($this->tablaFat.'.cdo_origen', $request->cdo_origen);
            }, function($queryElse) {
                $queryElse->whereIn($this->tablaFat.'.cdo_origen', ['RPA', 'MANUAL', 'NO-ELECTRONICO', 'CORREO']);
            })
            ->when($request->filled('cdo_clasificacion'), function ($query) use ($request) {
                $query->where($this->tablaFat.'.cdo_clasificacion', $request->cdo_clasificacion);
            })
            ->when($request->filled('rfa_prefijo'), function ($query) use ($request) {
                $query->where($this->tablaFat.'.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function ($query) use ($request) {
                $query->where($this->tablaFat.'.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('estado'), function ($query) use ($request) {
                $query->where($this->tablaFat.'.estado', $request->estado);
            })
            ->when($request->filled('cdo_fecha_validacion_dian_desde') && $request->filled('cdo_fecha_validacion_dian_hasta'), function ($query) use ($request) {
                $query->whereBetween($this->tablaFat.'.cdo_fecha_validacion_dian', [$request->cdo_fecha_validacion_dian_desde . ' 00:00:00', $request->cdo_fecha_validacion_dian_hasta . ' 23:59:59']);
            })
            ->when($request->filled('estado_validacion'), function ($query) use ($request) {
                $query->where($this->tablaFat.'.cdo_validacion', $request->estado_validacion);
            }, function ($query) {
                $query->whereIn($this->tablaFat.'.cdo_validacion', ['PENDIENTE','VALIDADO','RECHAZADO','PAGADO']);
            })
            ->when($request->filled('filtro_grupos_trabajo_usuario'), function($query) use ($request) {
                $query->filtroGruposTrabajoTracking($request);
            })
            ->when($request->filled('cdo_usuario_responsable_recibidos'), function($query) use ($request) {
                $query->where($this->tablaFat.'.cdo_usuario_responsable_recibidos', $request->cdo_usuario_responsable_recibidos['usu_id']);
            })
            ->when($request->filled('campo_validacion') && $request->filled('valor_campo_validacion'), function ($query) use ($request) {
                $query->where($this->tablaFat.'.cdo_validacion_valor_campos_adicionales_' . $request->campo_validacion, $request->valor_campo_validacion);
            })
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        'ofe_recepcion_fnc_activo',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                    ]);
                },
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                        'pro_id',
                        'pro_identificacion',
                        DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                    ]);
                },
                'getConfiguracionProveedor.getProveedorGruposTrabajo' => function($query) {
                    $query->select(['pro_id', 'gtr_id'])
                        ->where('estado', 'ACTIVO')
                        ->with([
                            'getGrupoTrabajo' => function($query) {
                                $query->select('gtr_id', 'gtr_codigo', 'gtr_nombre')
                                    ->where('estado', 'ACTIVO');
                            }
                        ]);
                },
                'getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo:gtr_id,ofe_id',
                'getTipoDocumentoElectronico:tde_id,tde_codigo,tde_descripcion',
                'getGrupoTrabajo:gtr_id,gtr_codigo,gtr_nombre',
                'getParametrosMoneda:mon_id,mon_codigo',
                'getParametrosMonedaExtranjera:mon_id,mon_codigo',
                'getParametrosFormaPago:fpa_id,fpa_codigo,fpa_descripcion'
            ])
            ->whereHas('getConfiguracionProveedor', function($query) use ($request) {
                $query->select([
                    'pro_id'
                ]);
                $query = $this->verificaRelacionUsuarioProveedor($query, $request->ofe_id, false, true);
                $query->when($request->filled('transmision_opencomex'), function ($query) {
                    $nitsIntegracionOpenComex = explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'));
                    $query->whereIn('pro_identificacion', $nitsIntegracionOpenComex);
                });
            })
            ->rightJoin('etl_obligados_facturar_electronicamente', function($query) {
                $query->whereRaw($this->tablaFat.'.ofe_id = etl_obligados_facturar_electronicamente.ofe_id')
                    ->where(function($query) {
                        if(!empty(auth()->user()->bdd_id_rg))
                            $query->where('etl_obligados_facturar_electronicamente' . '.bdd_id_rg', auth()->user()->bdd_id_rg);
                        else
                            $query->whereNull('etl_obligados_facturar_electronicamente' . '.bdd_id_rg');
                    });
            });

        $documentos = clone $consultaDocumentos;
        $documentos = $documentos->when($request->columnaOrden == 'cdo_fecha', function ($query) use ($request, $paginador) {
                $query->where(function($query) use ($request, $paginador) {
                    $query->where(function($query) use ($request, $paginador) {
                        $query->where($this->tablaFat.'.cdo_fecha', '>=', $request->cdo_fecha_desde)
                            ->where($this->tablaFat.'.cdo_id', $paginador->signoComparacion, $paginador->idComparacion);
                    })
                    ->where(function($query) use ($request, $paginador) {
                        $query->where($this->tablaFat.'.cdo_fecha', '<=', $request->cdo_fecha_hasta)
                            ->where($this->tablaFat.'.cdo_id', $paginador->signoComparacion, $paginador->idComparacion);
                    });
                });
            }, function($query) use ($request) {
                $query->whereBetween($this->tablaFat.'.cdo_fecha', [$request->cdo_fecha_desde, $request->cdo_fecha_hasta]);
            })
            ->orderByColumn($this->tablaFat.$request->columnaOrden, $paginador->ordenDireccion)
            ->orderBy($this->tablaFat.'.cdo_id', $paginador->ordenDireccion);

        if($request->filled('excel') && $request->excel == true) {
            return collect([
                'query' => $documentos,
                'ofe'   => $ofe
            ]);
        } else {
            $documentos = $documentos->limit($request->length)
                ->get();

            return collect([
                'query'      => $consultaDocumentos,
                'documentos' => $request->ordenDireccion != $paginador->ordenDireccion ? $documentos->reverse()->values() : $documentos
            ]);
        }
    }

    /**
     * Retonar el modelo del OFE con la relación de los grupos de trabajo.
     *
     * @param int $ofeId ID del OFE
     * @return ConfiguracionObligadoFacturarElectronicamente
     */
    private function getOfeGrupos(int $ofeId): ConfiguracionObligadoFacturarElectronicamente {
        return ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo'])
            ->where('ofe_id', $ofeId)
            ->with([
                'getGruposTrabajo' => function($query) {
                    $query->select(['gtr_id', 'ofe_id'])
                        ->where('estado', 'ACTIVO');
                }
            ])
            ->first();
    }

    /**
     * Establece algunos parámetros requeridos para la paginación por cursor.
     *
     * @param Request $request Parámetros de la petición
     * @return \stdClass
     */
    private function setParametrosPaginador(Request $request): \stdClass {
        $pagSiguiente = $request->filled('pag_siguiente') ? json_decode(base64_decode($request->pag_siguiente)) : json_decode(json_encode([]));
        $pagAnterior  = $request->filled('pag_anterior') ? json_decode(base64_decode($request->pag_anterior)) : json_decode(json_encode([]));

        if(!$request->filled('pag_siguiente') && !$request->filled('pag_anterior') && strtolower($request->ordenDireccion) == 'asc') {
            $signoComparacion = '>';
            $idComparacion    = 0;
            $ordenDireccion   = 'asc';
        } elseif(!$request->filled('pag_siguiente') && !$request->filled('pag_anterior') && strtolower($request->ordenDireccion) == 'desc') {
            $signoComparacion = '<=';
            $idComparacion    = RepFatDocumentoDaop::select('cdo_id')->orderBy('cdo_id', 'desc')->first()->cdo_id; // Último cdo_id de toda la tabla
            $ordenDireccion   = 'desc';
        }
        // Define signo de comparación para el cdo_id teniendo en cuenta:
        // - Si es página siguiente y el ordenamiento es asc, el signo es >
        // - Si es página anterior y el ordenamiento es asc, el signo es <
        // - Si es página siguiente y el ordenamiento es desc, el signo es <
        // - Si es página anterior y el ordenamiento es desc, el signo es >
        elseif(isset($pagSiguiente->apuntarSiguientes) && $pagSiguiente->apuntarSiguientes && $request->ordenDireccion == 'asc') {
            $signoComparacion = '>';
            $idComparacion    = $pagSiguiente->cdoId;
            $ordenDireccion   = 'asc';
        } elseif(isset($pagAnterior->apuntarSiguientes) && !$pagAnterior->apuntarSiguientes && $request->ordenDireccion == 'asc') {
            $signoComparacion = '<';
            $idComparacion    = $pagAnterior->cdoId;
            $ordenDireccion   = 'desc';
        } elseif(isset($pagSiguiente->apuntarSiguientes) && $pagSiguiente->apuntarSiguientes && $request->ordenDireccion == 'desc') {
            $signoComparacion = '<';
            $idComparacion    = $pagSiguiente->cdoId;
            $ordenDireccion   = 'desc';
        } elseif(isset($pagAnterior->apuntarSiguientes) && !$pagAnterior->apuntarSiguientes && $request->ordenDireccion == 'desc') {
            $signoComparacion = '>';
            $idComparacion    = $pagAnterior->cdoId;
            $ordenDireccion   = 'asc';
        }

        return json_decode(json_encode([
            'signoComparacion' => $signoComparacion,
            'idComparacion'    => $idComparacion,
            'ordenDireccion'   => $ordenDireccion
        ]));
    }

    /**
     * Permite filtrar los documentos electrónicos teniendo en cuenta la configuración de grupos de trabajo a nivel de usuario autenticado y proveedores.
     * 
     * Si el usuario autenticado esta configurado en algún grupo de trabajo, solamente se deben listar
     * documentos electrónicos de los proveedores asociados con ese mismo grupo o grupos de trabajo
     * Si el usuario autenticado no esta configurado en ningún grupo de trabajo, se verifica si el usuario está
     * relacionado directamente con algún proveedor para mostrar solamente documentos de esos proveedores
     * Si no se da ninguna de las anteriores condiciones, el usuario autenticado debe poder ver todos los documentos electrónicos de todos los proveedores
     *
     * @param Builder|EloquentBuilder $query Consulta que está en procesamiento
     * @param int $ofeId ID del OFE para el cual se está haciendo la consulta
     * @param bool $usuarioGestor Indica cuando se debe tener en cuenta que se trate de un usuario gestor
     * @param bool $usuarioValidador Indica cuando se debe tener en cuenta que se trate de un usuario validador
     * @param bool $queryBuilder Indica que el método es llamado desde una construcción con QueryBuilder y no con Eloquent
     * @param \StdClass $tablasProcesos Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Builder|EloquentBuilder Retorna una instancia del Query Builder cuando $queryBuilder es true, de lo contrario retorna una instancia de Eloquent Builder
     */
    public function verificaRelacionUsuarioProveedor($query, int $ofeId, bool $usuarioGestor = false, bool $usuarioValidador = false, bool $queryBuilder = false, \stdClass $tablasProceso = null) {
        $user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select('ofe_recepcion_fnc_activo')
            ->where('ofe_id', $ofeId)
            ->first();

        $gruposTrabajoUsuario = $this->getGruposTrabajoUsuarioAutenticado($ofe, $usuarioGestor, $usuarioValidador);

        if(!empty($gruposTrabajoUsuario)) {
            if($queryBuilder)
                $query->whereRaw('EXISTS 
                    (
                        SELECT gtp_id, gtr_id, pro_id FROM ' . $tablasProceso->tablaGruposTrabajoProveedor . '
                        WHERE ' . $tablasProceso->tablaProveedores . '.pro_id = ' . $tablasProceso->tablaGruposTrabajoProveedor . '.pro_id
                        AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                        AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.estado = "ACTIVO"
                    )');
            else
                $query->whereHas('getProveedorGruposTrabajo', function($gtrProveedor) use ($gruposTrabajoUsuario) {
                    $gtrProveedor->select(['gtp_id', 'gtr_id', 'pro_id'])
                        ->whereIn('gtr_id', $gruposTrabajoUsuario)
                        ->where('estado', 'ACTIVO');
                });
        } else {
            // Verifica si el usuario autenticado esta asociado con uno o varios proveedores para mostrar solo los documentos de ellos, de lo contrario mostrar los documentos de todos los proveedores en la BD
            $consultaProveedoresUsuario = ConfiguracionProveedor::select(['pro_id'])
                ->where('ofe_id', $ofeId)
                ->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%')
                ->where('estado', 'ACTIVO')
                ->get();

            if($consultaProveedoresUsuario->count() > 0)
                $query->when(!$tablasProceso, function($query) use ($ofeId) {
                        return $query->where('ofe_id', $ofeId);
                    }, function($query) use ($tablasProceso, $ofeId) {
                        return $query->where($tablasProceso->tablaDocs . '.ofe_id', $ofeId);
                    })
                    ->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%');
        }

        return $query;
    }

    /**
     * Teniendo en cuenta la consulta que se está realizando, se realiza una consulta adicional para determinar is existe información antes o depués
     * de los resultados a retornar para definir si se debe o no mostrar la información del link anterior o siguiente.
     *
     * @param EloquentBuilder $consultaDocumentos Clon de la consulta en procesamiento
     * @param integer $cdoId ID a tener en cuenta para la consulta
     * @param string $fechaDesde Fecha de inicio de consulta
     * @param string $fechaHasta Fecha de final de consulta
     * @param string $columnaOrdenamiento Columna mediante la cual se realiza el ordenamiento de la consulta
     * @param string $ordenamientoConsulta Ordenamiento de la consulta
     * @param string $tipoLink Tipo de link  a mostrar
     * @return bool
     */
    public function mostrarLinkAnteriorSiguiente(EloquentBuilder $consultaDocumentos, int $cdoId, string $fechaDesde, string $fechaHasta, string $columnaOrdenamiento, string $ordenamientoConsulta, string $tipoLink): bool {
        switch($tipoLink) {
            case 'anterior':
                $consulta = $consultaDocumentos->whereBetween('cdo_fecha', [$fechaDesde, $fechaHasta])
                    ->where('cdo_id', ($ordenamientoConsulta == 'asc' ? '<' : '>'), $cdoId);
                break;
            case 'siguiente':
                $consulta = $consultaDocumentos->whereBetween('cdo_fecha', [$fechaDesde, $fechaHasta])
                    ->where('cdo_id', ($ordenamientoConsulta == 'asc' ? '>' : '<'), $cdoId);
                break;
        }

        $consulta = $consulta->orderByColumn($columnaOrdenamiento, $ordenamientoConsulta)
            ->orderBy('cdo_id', $ordenamientoConsulta)
            ->first();

        if($consulta)
            return true;
        else
            return false;
    }

    /**
     * Agrega los Joins correspondientes a la consulta de los documentos recibidos para la generación del Excel.
     *
     * @param EloquentBuilder $query Consulta en procesamiento
     * @param string $particion Sufijo de la tabla sobre la cual se debe hacer el join
     * @return EloquentBuilder
     */
    public function joinsExcelValidacionDocumentos(EloquentBuilder $query, string $particion): EloquentBuilder {
        TenantTrait::GetVariablesSistemaTenant();
        return $query
            // Este rightJoin garantiza que la consulta obtenga los documentos que existan en la partición que se está procesando
            ->rightJoin('rep_cabecera_documentos_' . $particion . ' as get_cabecera_documentos', function($query) {
                $query->where(function($query) {
                    // Columnas que no están presentes en la tabla FAT deben consultarse en la tabla de cabecera
                    $query = HelperRecepcionParticionamiento::relacionOtrasTablas($query, $this->tablaFat, 'get_cabecera_documentos', 'cdo_id', [
                        'cdo_hora',
                        'cdo_vencimiento',
                        'cdo_observacion',
                        'cdo_valor_sin_impuestos',
                        'cdo_impuestos',
                        'cdo_cargos',
                        'cdo_descuentos',
                        'cdo_redondeo',
                        'cdo_valor_a_pagar',
                        'cdo_anticipo',
                        'cdo_retenciones'
                    ]);
                });
            })
            ->leftJoin('etl_openmain.etl_monedas as get_moneda', function($query) {
                $query->where(function($query) {
                    $query = HelperRecepcionParticionamiento::relacionOtrasTablas($query, $this->tablaFat, 'get_moneda', 'mon_id', ['mon_codigo']);
                });
            })
            ->leftJoin('rep_estados_documentos_' . $particion . ' as get_estado_validacion_validado', function($query) use ($particion) {
                $query->where(function($query) use ($particion) {
                    $query = HelperRecepcionParticionamiento::relacionEstadoUltimo($query, $this->tablaFat, 'get_estado_validacion_validado', 'rep_estados_documentos_' . $particion, 'VALIDACION', 'VALIDADO', '');
                });
            });
    }

    /**
     * Verifica la existencia de una tabla de acuerdo a la conexión establecida.
     *
     * @param string $conexion Nombre de la conexión actual a la base de datos tenant
     * @param string $tabla Nombre de la tabla a verificar
     * @return boolean
     */
    public function existeTabla(string $conexion, string $tabla): bool {
        return Schema::connection($conexion)->hasTable($tabla);
    }

}