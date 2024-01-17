<?php
namespace App\Http\Modulos\Documentos;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use App\Traits\GruposTrabajoTrait;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Controllers\OpenTenantController;
use App\Repositories\Emision\EtlCabeceraDocumentoRepository;
use App\Repositories\Recepcion\RepCabeceraDocumentoRepository;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDocumentosAnexosDaop\EtlDocumentoAnexoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\NominaElectronica\DsnEstadosDocumentosDaop\DsnEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepDocumentosAnexosDaop\RepDocumentoAnexoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaop;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaop;
use App\Http\Modulos\Documentos\EtlEventosNotificacionDocumentosDaop\EtlEventoNotificacionDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * clase base que imprime y lista los documentos segun los parametros de busquedas enviados en la Request.
 *
 * la funcion de esta clase es ayudar a mejorar y centrarlizar la busquedas de los documentos
 * y mejorar el dinamismo que se puede tener a futuro desde una misma base de codigo.
 * @var Array
 */
class BaseDocumentosController extends OpenTenantController {
    use GruposTrabajoTrait;

    /**
     * todas las columnas que pueden ser listadas.
     *
     * @var Array
     */
    public $columns = [];


    /**
     * Reservada para almacenar la request.
     *
     * @var Illuminate\Http\Request
     */
    public $request;

    /**
     * parametro por defecto si y solo si no se encuentra el dato en la request.
     *
     * numero desde el cual se comienza a tomar los datos de la consulta (offset)
     * @var Array
     */
    protected $default_start = 0;

    /**
     * parametro por defecto si y solo si no se encuentra el dato en la request.
     *
     * numero que delimita el numero de registros a tomar de la consulta (limit)
     * @var Array
     */    
    protected $default_limit = 10;

    //GETTERS
    /**
     * obtiene de forma limpia el parametro start enviado en el request.
     *
     * @return String
     */
    protected function __getParameterStart(){
        return (blank($this->request->get('start')))? $this->default_start : $this->request->start;
    }

    /**
     * obtiene de forma limpia el parametro length enviado en el request.
     *
     * @return String
     */
    protected function __getParameterLength(){
        return (blank($this->request->get('length')))? $this->default_limit : $this->request->length;
    }

    /**
     * obtiene de forma limpia el parametro de la fecha desde enviado en el request.
     *
     * @return String
     */
    protected function __getParameterFechaDesde(){
        if (strpos($this->request->path(), 'nomina-electronica') !== false) {
            return (blank($this->request->get('cdn_fecha_desde')))? '' : $this->request->cdn_fecha_desde;
        } else {
            return (blank($this->request->get('cdo_fecha_desde')))? '' : $this->request->cdo_fecha_desde;
        }
    }

    /**
     * obtiene de forma limpia el parametro ordenDireccion enviado en el request.
     *
     * @return String
     */
    protected function __getParameterColumnaOrden(){
        return (blank($this->request->get('ordenDireccion')))? '' : $this->request->columnaOrden;
    }

    /**
     * obtiene de forma limpia el parametro ordenDireccion enviado en el request.
     *
     * @return String
     */
    protected function __getParameterOrdenDireccion() {
        if(strtolower($this->request->get('ordenDireccion')) !== 'asc' && strtolower($this->request->get('ordenDireccion')) !== 'desc')
            $this->request->ordenDireccion = 'asc';

        return $this->request->ordenDireccion;
    }

    /**
     * obtiene de forma limpia el parametro de la fecha hasta enviado en el request.
     *
     * @return String
     */
    protected function __getParameterFechaHasta(){
        if (strpos($this->request->path(), 'nomina-electronica') !== false) {
            return (blank($this->request->get('cdn_fecha_hasta')))? '' : $this->request->cdn_fecha_hasta;
        } else {
            return (blank($this->request->get('cdo_fecha_hasta')))? '' : $this->request->cdo_fecha_hasta;
        }
    }

    /**
     * obtiene de forma limpia el parametro buscar enviado en el request.
     *
     * @return String
     */
    protected function __getParameterBuscar(){
        return (blank($this->request->get('buscar')))? '' : trim($this->request->buscar);
    }

    /**
     * obtiene de forma limpia el parametro fechaCargue enviado en el request.
     *
     * @return String
     */
    protected function __getParameterFechaCargue(){
        return (blank($this->request->get('fechaCargue')))? '' : trim($this->request->fechaCargue);
    }


    /**
     * inicializa los datos de la request, verificando que esten seteados correctamente.
     *
     * @return Void
     */
    protected function init_request_to_list(){
        $this->request = request();
        $this->request->start = $this->__getParameterStart();
        $this->request->length = $this->__getParameterLength();
        $this->request->from_date = $this->__getParameterFechaDesde();
        $this->request->to_date = $this->__getParameterFechaHasta();
        $this->request->order_by = $this->__getParameterColumnaOrden();
        $this->request->order_direction = $this->__getParameterOrdenDireccion();
        $this->request->search = $this->__getParameterBuscar();
        $this->request->fecha_cargue = $this->__getParameterFechaCargue();
    }

    /**
     * Agrega condiciones a las condiciones principales.
     *
     * @param Array $conditions Este array debe contener las validaciones requeridas al cual seran agregadas las condiciones opcionales.
     * @param Array $optional_conditionals Este array debe no ser vacio y contener las validacion extras que seran añadidas a las consultas principales si y solo si sus valores fueran enviados en el request 
     * @return Array
     */
    private function __addOptionalConditionals(array $conditions, array $optional_conditionals) {
        if (!empty($optional_conditionals)){
            foreach ($optional_conditionals as $condition_field) {
                if (is_string($condition_field)) {
                    if ($this->request->has($condition_field)){
                        $conditions[$condition_field] = $this->request->{$condition_field};
                    }
                }
                else if (is_array($condition_field)){
                    $conditions =  array_merge($conditions, $condition_field);
                }
            }
        }
        return $conditions;
    }

    /**
     * Agrega condiciones a las condiciones speciales a la consulta que se esta creando.
     *
     * @param Illuminate\Database\Eloquent\Builder $builder consulta que se esta creando a la cual se le agregaran mas condiciones usandos los metodos de laravel.
     * @param Array $optional_conditionals contiene las condiciones especiales.
     * @return Illuminate\Database\Eloquent\Builder
     */
    private function __addSpecialConditionals(Builder $builder, array $special_conditionals) {
        if (!empty($special_conditionals)){
            foreach ($special_conditionals as $key => $special_condition) {
                switch (strtolower($special_condition['type'])) {
                    case 'null':
                        $builder->whereNull($special_condition['field']);
                        break;
                    case 'notnull':
                        $builder->whereNotNull($special_condition['field']);
                        break;
                    case 'between':
                        $builder->whereBetween($special_condition['field'], $special_condition['value']);
                        break;
                    case 'whereraw':
                        $builder->whereRaw($special_condition['query']);
                        break;
                    case 'in':
                        $builder->whereIn($special_condition['field'], $special_condition['value']);
                        break;
                    case 'or':
                        $builder->orWhere($special_condition['field'],$special_condition['operator'] ?? '=', $special_condition['value']);
                        break;
                    case 'and':
                        $builder->Where($special_condition['field'],$special_condition['operator'] ?? '=', $special_condition['value']);
                        break;
                    case 'ifbetween':
                        $builder->whereRaw("(
                            CASE
                                WHEN " . $special_condition['field1'] . " IS NULL THEN
                                    CAST(CONCAT(" . $special_condition['field2'] . ", ' ', " . $special_condition['field3'] . ") AS datetime) BETWEEN '" . $special_condition['value'][0] . "' AND '" . $special_condition['value'][1] . "'
                                ELSE
                                    " . $special_condition['field1'] . " BETWEEN '" . $special_condition['value'][0] . "' AND '" . $special_condition['value'][1] . "'
                            END
                        )");
                        break;
                    case 'join':
                        $builder->join($special_condition['table'], function($join) use ($special_condition) {
                            if (!empty($special_condition['on_conditions']) && is_array($special_condition['on_conditions']) ){
                                foreach ($special_condition['on_conditions'] as $on_condition) {
                                    $join->on($on_condition['from'], $on_condition['operator'] ?? '=', $on_condition['to']);
                                }
                            } else if (isset($special_condition['where_conditions'])) {
                                throw new \Exception('Special On Conditions have to be a array');
                            }

                            if (!empty($special_condition['where_conditions']) && is_array($special_condition['where_conditions']) ){
                                foreach ($special_condition['where_conditions'] as $where_condition) {
                                    $join->where($where_condition['from'], $where_condition['operator'] ?? '=', $where_condition['to']);
                                }
                            } else if (isset($special_condition['where_conditions'])) {
                                throw new \Exception('Special Conditions have to be a array');
                            }
                        });
                        break;
                    default:
                        # code...
                        break;
                }
            }
        }
        return $builder;
    }

    /**
     * Agrega condiciones a las condiciones speciales a la consulta que se esta creando.
     *
     * @param array $required_where_conditions Condiciones principales que no deben ser vacias.
     * @param array $search_method Scope de busqueda a usar el cual debe estar presente en el Modelo EtlCabeceraDocumentoDaop
     * @param array $optional_where_conditions Contiene condiciones opcionales, es decir, seran validadas para saber si fueros enviadas en la Request.
     * @param array $special_where_conditions Contiene las condiciones que seran agregadas a Builder de Eloquent.
     * @param array $relations Arreglo de relaciones que tendra la data retornada
     * @param bool $returnData Flag para retornar o no la data dentro de una Collection.
     * @param bool $total Flag para enviar toda la data o realizar la omision de algunos datos.
     * @param string|array $whereHas Relacion sobre la cual se debe validar existencia de información
     * @param string $whereDoesntHave Relaciones que un documento no debería tener
     * @param string $proceso Proceso para el cual se deben listar los documentos
     * @param string $tipoRespuesta Proceso para el cual se deben listar los documentos
     *
     * @return \Illuminate\Http\Response|\Illuminate\Database\Eloquent\Collection Depende de Flag Aplicado.
     * @throws \Exception
     */
    protected function listDocuments(array $required_where_conditions, string $search_method, array $optional_where_conditions = [], array $special_where_conditions = [], array $relations = [], bool $returnData = false, bool $total = false, $whereHas = null, string $whereDoesntHave = null, string $proceso = '', string $tipoRespuesta = '') {
        //INIT REQUEST
        if(empty($this->request))
            $this->init_request_to_list();

        if (empty($required_where_conditions)){
            throw new \Exception("required_where_conditions array cannot to be empty", 1);
        }

        $required_where_conditions = $this->__addOptionalConditionals($required_where_conditions, $optional_where_conditions);

        $this->request->ordenDireccion = ($this->request->columnaOrden == null) ? '' : $this->request->ordenDireccion;

        if(empty($proceso) || $proceso == 'emision' || $proceso == 'documento_soporte')
            $class = EtlCabeceraDocumentoDaop::class;
        elseif($proceso == 'recepcion' || $proceso == 'portal_proveedores' || $proceso == 'recepcion_documentos_procesados')
            $class = RepCabeceraDocumentoDaop::class;
        elseif($proceso == 'nomina_electronica')
            $class = DsnCabeceraDocumentoNominaDaop::class;
        elseif($proceso == 'radian')
            $class = RadianCabeceraDocumentoDaop::class;

        $documents = $class::select($this->columns)
            ->with($relations)
            ->where($required_where_conditions);

        if($proceso == 'recepcion') {
            $documents->whereHas('getConfiguracionProveedor', function($query) {
                $recepcionCabeceraRepository = new RepCabeceraDocumentoRepository();
                if(stristr($this->request->url(), 'validacion') !== false || ($this->request->filled('pjj_tipo') && $this->request->pjj_tipo == 'RVALIDACION'))
                    $query = $recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $this->request->ofe_id, false, true);
                else
                    $query = $recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $this->request->ofe_id, true, false);

                if(filled($this->request->transmision_opencomex)) {
                    TenantTrait::GetVariablesSistemaTenant();
                    $nitsIntegracionOpenComex = explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'));
                    $query->whereIn('pro_identificacion', $nitsIntegracionOpenComex);
                }
            });

            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo'])
                ->where('ofe_id', $this->request->ofe_id)
                ->with([
                    'getGruposTrabajo' => function($query) {
                        $query->select(['gtr_id', 'ofe_id'])
                            ->where('estado', 'ACTIVO');
                    }
                ])
                ->first();

            if($ofe->ofe_recepcion_fnc_activo == 'SI') {
                if($this->request->filled('filtro_grupos_trabajo_usuario')) {
                    $documents->where(function($query) {
                        $query->where('gtr_id', $this->request->filtro_grupos_trabajo_usuario)
                            ->orWhere(function($query) {
                                $query->whereNull('gtr_id')
                                    ->whereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) {
                                        $query->where('estado', 'ACTIVO');
                                    }, '=', 1)
                                    ->whereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) {
                                        $query->where('estado', 'ACTIVO')
                                            ->where('gtr_id', $this->request->filtro_grupos_trabajo_usuario);
                                    });
                            });
                    });
                } else {
                    if(stristr($this->request->url(), 'validacion') !== false || ($this->request->filled('pjj_tipo') && $this->request->pjj_tipo == 'RVALIDACION'))
                        $gruposTrabajoUsuario = $this->getGruposTrabajoUsuarioAutenticado($ofe, false, true);
                    else
                        $gruposTrabajoUsuario = $this->getGruposTrabajoUsuarioAutenticado($ofe, true, false);

                    $documents->filtroGruposTrabajo($this->request, $ofe, $gruposTrabajoUsuario);
                }

            }
        }

        if($proceso == 'emision') {
            if (!$this->request->filled('cdo_clasificacion')) {
                $documents = $documents->whereIn('cdo_clasificacion', ['FC','NC','ND']);
            }
        } elseif($proceso == 'documento_soporte') {
            if (!$this->request->filled('cdo_clasificacion')) {
                $documents = $documents->whereIn('cdo_clasificacion', ['DS','DS_NC']);
            }
        } elseif($proceso == 'recepcion') {
            if ($this->request->filled('cdo_clasificacion') && ($this->request->cdo_clasificacion == 'DS' || $this->request->cdo_clasificacion == 'DS_NC') && ($this->request->filled('estado_acuse_recibo') || $this->request->filled('estado_recibo_bien') || $this->request->filled('estado_eventos_dian')) ) {
                $documents = $documents->whereIn('cdo_clasificacion', ['FC','NC','ND']);
            } elseif ($this->request->filled('cdo_clasificacion') && ($this->request->cdo_clasificacion !== 'DS' && $this->request->cdo_clasificacion !== 'DS_NC') && ($this->request->filled('estado_acuse_recibo') || $this->request->filled('estado_recibo_bien') || $this->request->filled('estado_eventos_dian'))) {
                $documents = $documents->whereIn('cdo_clasificacion', ['FC','NC','ND']);
            } elseif (!$this->request->filled('cdo_clasificacion') && ($this->request->filled('estado_acuse_recibo') || $this->request->filled('estado_recibo_bien') || $this->request->filled('estado_eventos_dian'))) {
                $documents = $documents->whereIn('cdo_clasificacion', ['FC','NC','ND']);
            } elseif ($this->request->filled('cdo_clasificacion') && ($this->request->cdo_clasificacion == 'DS' || $this->request->cdo_clasificacion == 'DS_NC') && (!$this->request->filled('estado_acuse_recibo') && !$this->request->filled('estado_recibo_bien') && !$this->request->filled('estado_eventos_dian'))) {
                $documents = $documents->whereIn('cdo_clasificacion', ['DS','DS_NC']);
            }
        }

        $whereHasTransmisionErp       = false;
        $whereHasTransmisionOpencomex = false;
        if(!empty($whereHas)) {
            if ($this->request->filled('estado_validacion') && is_array($whereHas)) {
                $documents->where(function($query) use (&$whereHas, &$whereDoesntHave) {
                    $indicesEliminar = [];
                    for($i = 0; $i < count($whereHas); $i++) {
                        if(array_key_exists('relation', $whereHas[$i]) && $whereHas[$i]['relation'] == 'getValidacionUltimo') {
                            if(array_key_exists('function', $whereHas[$i]))
                                $query->orWhereHas($whereHas[$i]['relation'], $whereHas[$i]['function']);

                            if(array_key_exists('query', $whereHas[$i]))
                                $query->orWhereRaw($whereHas[$i]['query']);

                            $indicesEliminar[] = $i;
                        }
                    }

                    foreach($indicesEliminar as $indice) unset($whereHas[$indice]);
                    $whereHas = array_values($whereHas);

                    if(!empty($whereDoesntHave) and stristr($whereDoesntHave, 'getEstadosValidacion') !== false) {
                        $query->orWhereDoesntHave('getEstadosValidacion');

                        $whereDoesntHave = explode(',', $whereDoesntHave);
                        $whereDoesntHave = array_diff($whereDoesntHave, ['getEstadosValidacion']);
                        $whereDoesntHave = implode(',', $whereDoesntHave);
                    }
                });
            }
            
            $documents->where(function($query) use ($whereHas, &$whereHasTransmisionErp, &$whereHasTransmisionOpencomex) {
                if(!is_array($whereHas)) {
                    $arrWhereHas = explode(',', $whereHas);
                    for($i = 0; $i < count($arrWhereHas); $i++) {
                        if($i == 0)
                            $query->whereHas($arrWhereHas[$i]);
                        else
                            if($arrWhereHas[$i] != 'getAcuseRecibo')
                                $query->orWhereHas($arrWhereHas[$i]);
                            else
                                $query->whereHas($arrWhereHas[$i]);
                    }
                } elseif(is_array($whereHas)) {
                    for($i = 0; $i < count($whereHas); $i++) {
                        if(array_key_exists('relation', $whereHas[$i]) && !array_key_exists('function', $whereHas[$i])) {
                            $whereHasTransmisionErp       = $whereHas[$i]['relation'] == 'getTransmisionErp' && !$whereHasTransmisionErp ? true : false;
                            $whereHasTransmisionOpencomex = $whereHas[$i]['relation'] == 'getOpencomexCxp' && !$whereHasTransmisionOpencomex ? true : false;

                            if($i == 0)
                                $query->whereHas($whereHas[$i]['relation']);
                            else
                                if(
                                    $whereHas[$i]['relation'] != 'getAcuseRecibo' && 
                                    $whereHas[$i]['relation'] != 'getReciboBien' && 
                                    $whereHas[$i]['relation'] != 'getConfiguracionObligadoFacturarElectronicamente' && 
                                    $whereHas[$i]['relation'] != 'getEmpleador' &&
                                    (
                                        $whereHas[$i]['relation'] == 'getMaximoEstadoDocumento' && 
                                        ($this->request->filled('estado_eventos_dian') && count($this->request->estado_eventos_dian) > 1)
                                    )
                                )
                                    $query->orWhereHas($whereHas[$i]['relation']);
                                else
                                    $query->whereHas($whereHas[$i]['relation']);
                        } elseif(array_key_exists('relation', $whereHas[$i]) && array_key_exists('function', $whereHas[$i])) {
                            if($whereHas[$i]['relation'] == 'getTransmisionErp' && !$whereHasTransmisionErp) $whereHasTransmisionErp = true;
                            if($whereHas[$i]['relation'] == 'getOpencomexCxp' && !$whereHasTransmisionOpencomex) $whereHasTransmisionOpencomex = true;
                            
                            if($i == 0)
                                $query->whereHas($whereHas[$i]['relation'], $whereHas[$i]['function']);
                            else
                                if(
                                    $whereHas[$i]['relation'] != 'getAcuseRecibo' && 
                                    $whereHas[$i]['relation'] != 'getReciboBien' && 
                                    $whereHas[$i]['relation'] != 'getConfiguracionObligadoFacturarElectronicamente' && 
                                    $whereHas[$i]['relation'] != 'getEmpleador' &&
                                    (
                                        $whereHas[$i]['relation'] == 'getMaximoEstadoDocumento' && 
                                        ($this->request->filled('estado_eventos_dian') && count($this->request->estado_eventos_dian) > 1)
                                    )
                                )
                                    $query->orWhereHas($whereHas[$i]['relation'], $whereHas[$i]['function']);
                                else
                                    $query->whereHas($whereHas[$i]['relation'], $whereHas[$i]['function']);
                        }
                    }
                }
            });
        }

        if($whereDoesntHave) {
            if(!strstr($whereDoesntHave, 'getTransmisionErp') || !$whereHasTransmisionErp) {
                $documents->where(function($query) use ($whereDoesntHave) {
                    $whereDoesntHave = explode(',', $whereDoesntHave);
                    foreach ($whereDoesntHave as $condition) {
                        $query->whereDoesntHave($condition);
                    }
                });
            } elseif(!strstr($whereDoesntHave, 'getOpencomexCxp') || !$whereHasTransmisionOpencomex) {
                $documents->where(function($query) use ($whereDoesntHave) {
                    $whereDoesntHave = explode(',', $whereDoesntHave);
                    foreach ($whereDoesntHave as $condition) {
                        $query->whereDoesntHave($condition);
                    }
                });
            } else {
                $documents->orWhere(function($query) use ($whereDoesntHave) {
                    $whereDoesntHave = explode(',', $whereDoesntHave);
                    foreach ($whereDoesntHave as $condition) {
                        $query->whereDoesntHave($condition);
                    }
                });
            }
        }
        
        //specials conditions
        $documents = $this->__addSpecialConditionals($documents, $special_where_conditions);

        if ($this->request->has('estado_dian') && ($this->request->estado_dian == 'en_proceso' || (is_array($this->request->estado_dian) && in_array('en_proceso', $this->request->estado_dian)))) {
            switch($proceso) {
                case "emision":
                    $documents = $documents->where(function($query) {
                        $query->where(function($query) {
                            $query->whereHas('getEdiEnProceso')
                                ->orWhereHas('getUblEnProceso')
                                ->orWhereHas('getDoEnProceso')
                                ->orWhereHas('getUblAttachedDocumentEnProceso')
                                ->orWhereHas('getNotificacionEnProceso');
                        })->orWhere(function($query) {
                            $query->whereHas('getEdiFinalizado')
                                ->whereDoesntHave('getEstadoUbl');
                        })->orWhere(function($query) {
                            $query->whereHas('getEdiFinalizado')
                                ->whereHas('getUblFinalizado')
                                ->whereDoesntHave('getEstadoDo');
                        })->orWhere(function($query) {
                            $query->whereHas('getEdiFinalizado')
                                ->whereHas('getUblFinalizado')
                                ->whereHas('getDoFinalizado')
                                ->whereDoesntHave('getEstadoUblattacheddocument');
                        })->orWhere(function($query) {
                            $query->whereHas('getEdiFinalizado')
                                ->whereHas('getUblFinalizado')
                                ->whereHas('getDoFinalizado')
                                ->whereHas('getUblAttachedDocumentFinalizado')
                                ->whereDoesntHave('getEstadoNotificacion');
                        });
                    });
                break;
                case "nomina_electronica":
                    $documents = $documents->where(function($query) {
                        $query->where(function($query) {
                            $query->whereHas('getNdiEnProceso')
                                ->orWhereHas('getXmlEnProceso')
                                ->orWhereHas('getDoEnProceso');
                        })->orWhere(function($query) {
                            $query->whereHas('getNdiFinalizado')
                                ->whereDoesntHave('getEstadoXml');
                        })->orWhere(function($query) {
                            $query->whereHas('getNdiFinalizado')
                                ->whereHas('getXmlExitoso')
                                ->whereDoesntHave('getEstadoDo');
                        });
                    });
                break;
            }
        }

        if ($proceso == 'recepcion' && $this->request->filled('estado_eventos_dian') && $this->request->filled('resEventosDian') && ($this->request->resEventosDian == 'exitoso' || $this->request->resEventosDian == 'fallido')) {
            $documents->where(function($query) {
                $query->when(in_array('aceptado_tacitamente', $this->request->estado_eventos_dian), function($query) {
                    $query->when($this->request->resEventosDian == 'exitoso',function($query) {
                        $query->orWhereHas('getMaximoEstadoDocumento', function($query) {
                            $query->where('est_estado', 'ACEPTACIONT')
                                ->where('est_resultado', 'EXITOSO');
                        });
                    }, function($query) {
                        $query->orWhereHas('getMaximoEstadoDocumento', function($query) {
                            $query->where('est_estado', 'ACEPTACIONT')
                                ->where('est_resultado', 'FALLIDO');
                        });
                    });
                })->when(in_array('aceptacion_expresa', $this->request->estado_eventos_dian), function($query) {
                    $query->when($this->request->resEventosDian == 'exitoso',function($query) {
                        $query->orWhereHas('getMaximoEstadoDocumento', function($query) {
                            $query->where('est_estado', 'ACEPTACION')
                                ->where('est_resultado', 'EXITOSO');
                        });
                    }, function($query) {
                        $query->orWhereHas('getMaximoEstadoDocumento', function($query) {
                            $query->where('est_estado', 'ACEPTACION')
                                ->where('est_resultado', 'FALLIDO');
                        });
                    });
                })->when(in_array('reclamo_rechazo', $this->request->estado_eventos_dian), function($query) {
                    $query->when($this->request->resEventosDian == 'exitoso',function($query) {
                        $query->orWhereHas('getMaximoEstadoDocumento', function($query) {
                            $query->where('est_estado', 'RECHAZO')
                                ->where('est_resultado', 'EXITOSO');
                        });
                    }, function($query) {
                        $query->orWhereHas('getMaximoEstadoDocumento', function($query) {
                            $query->where('est_estado', 'RECHAZO')
                                ->where('est_resultado', 'FALLIDO');
                        });
                    });
                });
            });
        }

        // FROM:TO
        if($proceso != 'recepcion' && $proceso != 'portal_proveedores' && $proceso != 'nomina_electronica') {
            if (!empty($this->request->from_date) && !empty($this->request->to_date)){
                $documents->whereBetween('cdo_fecha', [$this->request->from_date, $this->request->to_date]);
            }
        } elseif ($proceso == 'nomina_electronica') {
            if (!empty($this->request->from_date) && !empty($this->request->to_date)){
                $documents->whereBetween('cdn_fecha_emision', [$this->request->from_date . ' 00:00:00', $this->request->to_date . ' 23:59:59']);
            }
        }

        if (!empty(trim($this->request->search))) {
            if (!empty($search_method)){
                $documents->{$search_method}($this->request->search);
            }
        }

        $filter_rows = $documents->count();

        if (!empty($this->request->order_by) && !empty($this->request->order_direction)){
            $documents->orderByColumn($this->request->order_by, $this->request->order_direction);
        }
        
        if (!$total && isset($this->request->length) && $this->request->length !== -1 && $this->request->length !== '-1'){
            $documents = $documents->skip($this->request->start)
                ->take($this->request->length);
        }
        
        if ($returnData){
            // Las consultas se realizan por cada 1000 documentos para evitar problemas de memoria en el procesamiento
            $coleccionDocumentos = new \Illuminate\Database\Eloquent\Collection;
            $documents->chunk(1000, function($resultado) use (&$coleccionDocumentos) {
                $coleccionDocumentos = $coleccionDocumentos->merge($resultado);
            });
            return $coleccionDocumentos;
        }

        $documentos = $documents->get();

        if ($search_method == 'BusquedaEnviados' && $proceso != 'nomina_electronica'){
            // Si se encontraron eventos de notificación para el documento, los agrupa mediante los correos notificados
            $documentos = $documentos->map(function($documento) {
                if($documento->has('getEventoNotificacionDocumentoDaop') && $documento->getEventoNotificacionDocumentoDaop->isNotEmpty()) {
                    $eventos   = $documento->getEventoNotificacionDocumentoDaop->groupBy('evt_correos')->toArray();
                    $documento = $documento->toArray();
                    $documento['get_evento_notificacion_documento_daop'] = $eventos;

                }
                return $documento;
            });
        }

        $respuesta = [
            "total"     => $filter_rows == 0 ? 0 : $class::count(),
            "filtrados" => $filter_rows,
            "data"      => $documentos,
        ];

        if ($tipoRespuesta == 'array') {
            return $respuesta;
        } 

        return response()->json($respuesta, Response::HTTP_OK);
    }

    /**
     * ejecuta un callback si los datos enviados estan correctos.
     *
     * @param Array $doc un documento a listar.
     * @param Array $column contiene las acciones a realizar con la columna del documento.
     * 
     * @return String
     */
    public function runCallback(array $doc, array $column) {
        if (array_key_exists('do', $column) && is_callable($column['do'])){
            try {
                $main_value = $doc[$column['field']];
            } catch (\Exception $e){
                $main_value = null;
            }

            //guarda los datos extra que se requieren en el callback
            $extra_data = [];

            //Buscamos la data extra dentro del documento.
            if (array_key_exists('extra_data', $column) && !empty($column['extra_data']) && is_array($column['extra_data']) ) {
                foreach ($column['extra_data'] as $key => $field) {
                    try {
                        $extra_data[$field] = $doc[$field];
                    }
                    catch (\Exception $e){
                        $extra_data[$field] = null;
                    }
                }
            } else if (array_key_exists('extra_data', $column) && !is_array($column['extra_data'])) {
                throw new \Exception("extra_data index only can be a Array", 1);
            }
            //RUN CALLBACK
            $value = $column['do']($main_value, $extra_data);
        } else {
            throw new \Exception("The do index must to exist and be a Closure", 1);
        }

        return $value;
    }

    /**
     * imprime el excel segun los parametros pasados.
     *
     * @param Collection $documents Todos los registros de los documentos a imprimir en el excel.
     * @param array $titles Titulos que tendra el Excel.
     * @param array $columns Columnas que van hacer impresas en el excel (Debe estar en orden a los titulos).
     * example:
     * [
     * 'field_name',
     * 'relation.field_name',
     * [
     * 'do' => function ($field, $extra_data) { },
     * 'extra_data' => [
     * 'field_name',
     * 'relation.field_name',
     * ],
     * 'field' => 'adq_razon_social',
     * 'validation_type'=> 'callback'
     * ],
     * .......
     * ]
     * @param string $file_title Nombre del archivo
     * @param boolean $proceso_background Indica si se trata de un proceso en background o no para retornar la descarga del archivo o solo el nombre del archivo
     *
     * @return Response
     * @throws \Exception
     */
    protected function printExcelDocumentos(Collection $documents, array $titles, array $columns, $file_title, $proceso_background = false){
        set_time_limit(0);
        ini_set('memory_limit', '4096M');

        // Array que pasa al Excel
        $arrDocumentos = $documents->map(function ($document) use ($columns) {
            $arr_doc = [];
            $isArray = false;
            if($document instanceof \stdClass)
                $doc = Arr::dot((array) $document);
            else
                $doc = Arr::dot($document->toArray());

            //Añadimos columnas al array
            foreach ($columns as $column) {
                if (is_array($column)){
                    switch ($column['validation_type']) {
                        case 'callback':
                            $value = $this->runCallback($doc, $column);
                            break;
                        default:
                            # code...
                            break;
                    }
                } else {
                    if($column == 'estado_dian' || $column == 'resultado_dian') {
                        $value = $this->definirEstadoDian($doc, $column);
                    } elseif($column == 'motivo_rechazo') {
                        $value = $this->obtenerMotivoRechazo($doc, $column);
                    } elseif($column == 'estado_validacion') {
                        $isArray = true;
                        $value = $this->obtenerEstadoValidacion($doc);
                    } elseif($column == 'grupo_trabajo') {
                        $isArray = false;
                        $value = $this->definirGrupoTrabajo($doc);
                    } else {
                        $value = $doc[$column] ?? '';
                    }
                }

                if ($isArray) {
                    foreach ($value as $item) {
                        array_push($arr_doc, $item);
                    }
                } else
                    array_push($arr_doc, $value);
            }
            return $arr_doc;
        });

        $arrDocumentos = $arrDocumentos->toArray();

        date_default_timezone_set('America/Bogota');
        $nombreArchivo = $file_title. '_' . date('YmdHis');
        $archivoExcel = $this->toExcel($titles, $arrDocumentos, $nombreArchivo);

        if(!$proceso_background) {
            $headers = [
                header('Access-Control-Expose-Headers: Content-Disposition')
            ];

            return response()
                ->download($archivoExcel, $nombreArchivo . '.xlsx', $headers)
                ->deleteFileAfterSend(true);
        } else {
            return [
                'ruta'   => $archivoExcel,
                'nombre' => $nombreArchivo . '.xlsx'
            ];
        }
    }

    /**
     * Define el estado del documento en la DIAN y retorna igualmente el mensaje de resultado.
     *
     * @param array $doc Array del documento en procesamiento
     * @param string $column Columa que indica el tipo de información a retornar
     * @return string estado en la Dian o Mensaje de Resultado de la Dian
     */
    public function definirEstadoDian($doc, $column) {
        if(array_key_exists('get_documento_aprobado.est_id', $doc) && $doc['get_documento_aprobado.est_id'] != null) {
            $estadoDian    = 'Aprobado';
            $resultadoDian = $doc['get_documento_aprobado.est_mensaje_resultado'];
        } elseif(array_key_exists('get_documento_aprobado_notificacion.est_id', $doc) && $doc['get_documento_aprobado_notificacion.est_id'] != null) {
            $estadoDian    = 'Aprobado con Notificación';
            $resultadoDian = $doc['get_documento_aprobado_notificacion.est_mensaje_resultado'];
        } elseif(array_key_exists('get_documento_rechazado.est_id', $doc) && $doc['get_documento_rechazado.est_id'] != null) {
            $estadoDian    = 'Rechazado';
            $resultadoDian = $doc['get_documento_rechazado.est_mensaje_resultado'];
        } else {
            $estadoDian    = '';
            $resultadoDian = '';
        }
        if($column == 'estado_dian') {
            return $estadoDian;
        } elseif($column == 'resultado_dian') {
            return $resultadoDian;
        }
    }

    /**
     * Obtiene el motivo de rechazo de un documento, si existe el mismo.
     *
     * @param array $doc Array del documento en procesamiento
     * @param string $column Columa que indica el tipo de información a retornar
     * @return string $motivoRechazo Código y/o motivo del rechazo del documento
     */
    public function obtenerMotivoRechazo($doc, $column) {
        if(array_key_exists('get_rechazado.est_motivo_rechazo.motivo_rechazo', $doc) && $doc['get_rechazado.est_motivo_rechazo.motivo_rechazo'] != null) {
            $motivoRechazo = $doc['get_rechazado.est_motivo_rechazo.motivo_rechazo'];
        } else {
            $motivoRechazo = '';
        }
        return $motivoRechazo;
    }

    /**
     * Obtiene el motivo de rechazo de un documento, si existe el mismo.
     *
     * @param array $doc Array del documento en procesamiento
     * @return array
     */
    public function obtenerEstadoValidacion(array $doc): array {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_recepcion_fnc_activo','ofe_recepcion_fnc_configuracion'])
            ->where('ofe_id', $doc['ofe_id'])
            ->where('estado', 'ACTIVO')
            ->first();

        $configuracionFNC = json_decode($ofe['ofe_recepcion_fnc_configuracion']);
        $columnasEvento = [];

        foreach ($configuracionFNC->evento_recibo_bien as $value) {
            
            if(array_key_exists('get_estado_validacion_en_proceso_pendiente.est_informacion_adicional', $doc) && $doc['get_estado_validacion_en_proceso_pendiente.est_informacion_adicional'] != null) {
                $informacionAdicional = json_decode($doc['get_estado_validacion_en_proceso_pendiente.est_informacion_adicional'], true);
                if (isset($informacionAdicional['campos_adicionales'])) {
                    foreach ($informacionAdicional['campos_adicionales'] as $item) {
                        if ($item['campo'] == strtolower($this->sanear_string($value->campo))) {
                            $columnasEvento[] = $item['valor'];
                        }
                    }
                }
            } elseif (array_key_exists('get_recibo_bien_ultimo.est_informacion_adicional', $doc) && $doc['get_recibo_bien_ultimo.est_informacion_adicional'] != null) {
                $informacionAdicional = json_decode($doc['get_recibo_bien_ultimo.est_informacion_adicional'], true);
                if (isset($informacionAdicional['campos_adicionales'])) {
                    foreach ($informacionAdicional['campos_adicionales'] as $item) {
                        if ($item['campo'] == strtolower($this->sanear_string($value->campo))) {
                            $columnasEvento[] = $item['valor'];
                        }
                    }
                }
            }
        }

        return $columnasEvento;
    }

    /**
     * Define el grupo de trabajo al cual se encuentra asignado un documento.
     *
     * @param  array $doc Array del documento en procesamiento
     * @return array
     */
    public function definirGrupoTrabajo(array $doc): string {
        $grupoTrabajo = '';

        if(array_key_exists('get_grupo_trabajo.gtr_id', $doc) && $doc['get_grupo_trabajo.gtr_id'] != null) {
            $grupoTrabajo = $doc['get_grupo_trabajo.gtr_codigo'] . ' - ' . $doc['get_grupo_trabajo.gtr_nombre'];
        } else {
            $arrDoc = Arr::undot($doc);
            if(
                array_key_exists('get_proveedor_grupos_trabajo', $arrDoc['get_configuracion_proveedor']) &&
                !empty($arrDoc['get_configuracion_proveedor']['get_proveedor_grupos_trabajo']) &&
                count($arrDoc['get_configuracion_proveedor']['get_proveedor_grupos_trabajo']) == 1
            )
                $grupoTrabajo = $arrDoc['get_configuracion_proveedor']['get_proveedor_grupos_trabajo'][0]['get_grupo_trabajo']['gtr_codigo'] . ' - ' .
                    $arrDoc['get_configuracion_proveedor']['get_proveedor_grupos_trabajo'][0]['get_grupo_trabajo']['gtr_nombre'];
        }

        return $grupoTrabajo;
    }

    /**
     * Permite obtener los estados de un documento en específico.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function estadosDocumento(Request $request): JsonResponse {
        if (!$request->filled('proceso')) {
            return response()->json([
                'message' => 'Error al consultar los estados del documento',
                'errors' => ['El tipo de proceso es requerido.']
            ], 422);
        }

        $documentoId = '';
        // Se realizan las validaciones dependiendo del proceso que llega
        switch ($request->proceso) {
            case 'nomina-electronica':
                // El id del documento es requerido
                if (!$request->filled('cdn_id')) {
                    return response()->json([
                        'message' => 'Error al consultar los estados del documento',
                        'errors' => ['El Id del documento es requerido.']
                    ], 422);
                }

                $class       = DsnEstadoDocumentoDaop::class;
                $documentoId = 'cdn_id';
                break;
            case 'emision':
            case 'recepcion':
            case 'radian':
                // El id del documento es requerido
                if (!$request->filled('cdo_id')) {
                    return response()->json([
                        'message' => 'Error al consultar los estados del documento',
                        'errors' => ['El Id del documento es requerido.']
                    ], 422);
                }

                if ($request->proceso == 'emision')
                    $class = EtlEstadosDocumentoDaop::class;
                elseif ($request->proceso == 'recepcion')
                    $class = RepEstadoDocumentoDaop::class;
                else
                    $class = RadianEstadoDocumentoDaop::class;

                $documentoId = 'cdo_id';

                $eventosNotificacion = EtlEventoNotificacionDocumentoDaop::select(['evt_id', 'cdo_id', 'evt_evento', 'evt_correos', 'evt_amazonses_id', 'evt_fecha_hora', 'evt_json'])
                    ->where('cdo_id', $request->cdo_id)
                    ->get();

                break;
            default:
                //no hace nada
                break;
        }

        $respuesta    = array();
        $ultimoEstado = array();
        $estados = $class::select(
                [
                    'est_id',
                    $documentoId,
                    'est_estado',
                    'est_resultado', 
                    'est_mensaje_resultado',
                    'est_object',
                    'est_correos',
                    $request->proceso != 'nomina-electronica' ? 'est_motivo_rechazo' : 'est_fin_proceso',
                    'est_informacion_adicional',
                    'est_inicio_proceso',
                    'est_ejecucion',
                    'usuario_creacion',
                    'fecha_creacion'
                ]
            )
            ->with(['getUsuarioCreacion'])
            ->where($documentoId, $request->{$documentoId})
            ->when($request->filled('tracking') && $request->tracking == 'validacion-documentos', function ($query) {
                return $query->where('est_estado', 'VALIDACION');
            })
            ->when(!$request->filled('tracking') && $request->proceso == 'recepcion', function ($query) {
                return $query->where('est_mensaje_resultado', 'not like', '%SecureBlackbox library exception%')
                    ->where('est_mensaje_resultado', 'not like', '%Ha ocurrido un error. Por favor inténtelo de nuevo%');
            })
            ->orderBy('est_id', 'desc')
            ->get();

        if($estados->isEmpty() && $request->proceso == 'emision') {
            $emisionCabeceraRepository = new EtlCabeceraDocumentoRepository;
            $docFat = $emisionCabeceraRepository->consultarDocumentoFatByCdoId($request->{$documentoId});
                    
            if($docFat) {
                $particion = Carbon::parse($docFat->cdo_fecha_validacion_dian)->format('Ym');
                
                $tblEstados = new EtlEstadosDocumentoDaop;
                $tblEstados->setTable('etl_estados_documentos_' . $particion);
                $estados = $tblEstados->select(
                        [
                            'est_id',
                            $documentoId,
                            'est_estado',
                            'est_resultado', 
                            'est_mensaje_resultado',
                            'est_object',
                            'est_correos',
                            'est_motivo_rechazo',
                            'est_informacion_adicional',
                            'est_inicio_proceso',
                            'est_ejecucion',
                            'fecha_creacion'
                        ]
                    )
                    ->where($documentoId, $request->{$documentoId})
                    ->orderBy('est_id', 'desc')
                    ->get();

                $tblEventosNotificacion = new EtlEventoNotificacionDocumentoDaop;
                $tblEventosNotificacion->setTable('etl_eventos_notificacion_documentos_' . $particion);
    
                $eventosNotificacion = $tblEventosNotificacion->select(['evt_id', 'cdo_id', 'evt_evento', 'evt_correos', 'evt_amazonses_id', 'evt_fecha_hora', 'evt_json'])
                    ->where($documentoId, $request->{$documentoId})
                    ->orderBy('fecha_creacion', 'asc')
                    ->get();
            }
        } elseif($estados->isEmpty() && $request->proceso == 'recepcion') {
            $recepcionCabeceraRepository = new RepCabeceraDocumentoRepository;
            $docFat = $recepcionCabeceraRepository->consultarDocumentoFatByCdoId($request->{$documentoId});
                    
            if($docFat) {
                $particion = Carbon::parse($docFat->cdo_fecha)->format('Ym');
                
                $tblEstados = new RepEstadoDocumentoDaop;
                $tblEstados->setTable('rep_estados_documentos_' . $particion);
                $estados = $tblEstados->select(
                        [
                            'est_id',
                            $documentoId,
                            'est_estado',
                            'est_resultado', 
                            'est_mensaje_resultado',
                            'est_object',
                            'est_correos',
                            'est_motivo_rechazo',
                            'est_informacion_adicional',
                            'est_inicio_proceso',
                            'est_ejecucion',
                            'fecha_creacion'
                        ]
                    )
                    ->where($documentoId, $request->{$documentoId})
                    ->when(!$request->filled('tracking'), function ($query) {
                        return $query->where(function($query) {
                            return $query->where('est_mensaje_resultado', 'not like', '%SecureBlackbox library exception%')
                                ->where('est_mensaje_resultado', 'not like', '%Ha ocurrido un error. Por favor inténtelo de nuevo%');
                        });
                    })
                    ->orderBy('est_id', 'desc')
                    ->get();
            }
        }

        if ($request->filled('tracking') && ($request->tracking == 'validacion-documentos' || $request->tracking == 'recibidos')) {
            $estadoValidacionEnProcesoPendienteAsignado = false;
            foreach ($estados as $estado) {
                // Para el estado VALIDACION se retornan todos los registros, teniendo en cuenta que para los que son PENDIENTE o ENPROCESO se deja solo el más reciente
                if ($estado->est_estado == 'VALIDACION') {
                    if(!$estadoValidacionEnProcesoPendienteAsignado && ($estado->est_resultado == 'ENPROCESO' || $estado->est_resultado == 'PENDIENTE')) {
                        $respuesta[] = $estado;
                        $estadoValidacionEnProcesoPendienteAsignado = true;
                    } elseif($estado->est_resultado != 'ENPROCESO' && $estado->est_resultado != 'PENDIENTE') {
                        $respuesta[] = $estado;
                    }
                // Para el estado ASIGNAR_DEPENDENCIA se retornan todos los registros
                } elseif ($estado->est_estado == 'ASIGNAR_DEPENDENCIA') {
                    $respuesta[] = $estado;
                } else {
                    // Se realiza la validación para retornar el último registro de cada estado
                    if (!in_array($estado->est_estado, $ultimoEstado)) {
                        $estado->est_informacion_adicional = json_decode($estado->est_informacion_adicional);
                        $ultimoEstado[] = $estado->est_estado;
                        $respuesta[]    = $estado;
                    }
                }
            }
        } else {
            foreach ($estados as $estado) {
                // Se realiza la validación para retornar el último registro de cada estado
                if (!in_array($estado->est_estado, $ultimoEstado)) {
                    $estado->est_informacion_adicional = json_decode($estado->est_informacion_adicional);
                    $ultimoEstado[] = $estado->est_estado;
                    $respuesta[]    = $estado;
                }
            }
        }

        if (empty($respuesta)) {
            return response()->json([
                'message' => 'Errores al consultar los estados del documento',
                'errors' => ['No existen estados para el documento seleccionado.']
            ], 404);
        }

        if ($request->proceso == 'nomina-electronica') {
            $datos = [
                "data" => $respuesta,
            ];
        } else {
            $datos = [
                "data" => $respuesta,
                "eventos_notificacion" => $eventosNotificacion
            ];
        }

        return response()->json($datos, 200);
    }

    /**
     * Permite obtener los documentos anexos de un documento en específico.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function documentosAnexos(Request $request) {
        if (!$request->filled('proceso')) {
            return response()->json([
                'message' => 'Error al consultar los documentos anexos',
                'errors' => ['El tipo de proceso es requerido.']
            ], 422);
        }

        if (!$request->filled('cdo_id')) {
            return response()->json([
                'message' => 'Error al consultar los documentos anexos',
                'errors' => ['El Id del documento es requerido.']
            ], 422);
        }

        if ($request->proceso == 'emision') {
            $class = EtlDocumentoAnexoDaop::class;
        } else {
            $class = RepDocumentoAnexoDaop::class;
        }

        $documentosAnexos = $class::select(['dan_id','cdo_id','dan_lote','dan_uuid','dan_tamano','dan_nombre','dan_descripcion','fecha_creacion'])
            ->where('cdo_id', $request->cdo_id)
            ->get();

        if ($documentosAnexos->isEmpty() && $request->proceso == 'emision') {
            $docFat = EtlFatDocumentoDaop::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha_validacion_dian'])
                ->where('cdo_id', $request->cdo_id)
                ->first();

            if($docFat) {
                $particion = Carbon::parse($docFat->cdo_fecha_validacion_dian)->format('Ym');
    
                $tblAnexos = new EtlDocumentoAnexoDaop;
                $tblAnexos->setTable('etl_documentos_anexos_' . $particion);
    
                $documentosAnexos = $tblAnexos->select(['dan_id','cdo_id','dan_lote','dan_uuid','dan_tamano','dan_nombre','dan_descripcion','fecha_creacion'])
                    ->where('cdo_id', $request->cdo_id)
                    ->get();
            }
        } elseif ($documentosAnexos->isEmpty() && $request->proceso == 'recepcion') {
            $recepcionCabeceraRepository = new RepCabeceraDocumentoRepository;
            $docFat = $recepcionCabeceraRepository->consultarDocumentoFatByCdoId($request->cdo_id);

            if($docFat) {
                $particion = Carbon::parse($docFat->cdo_fecha)->format('Ym');
    
                $tblAnexos = new RepDocumentoAnexoDaop;
                $tblAnexos->setTable('rep_documentos_anexos_' . $particion);
    
                $documentosAnexos = $tblAnexos->select(['dan_id','cdo_id','dan_lote','dan_uuid','dan_tamano','dan_nombre','dan_descripcion','fecha_creacion'])
                    ->where('cdo_id', $request->cdo_id)
                    ->get();
            }
        }

        if ($documentosAnexos->isEmpty()) {
            return response()->json([
                'message' => 'Errores al consultar los documentos anexos',
                'errors' => ['No existen documentos anexos para el documento seleccionado.']
            ], 404);
        }

        return response()->json([
            "data" => $documentosAnexos,
        ], 200);
    }
}