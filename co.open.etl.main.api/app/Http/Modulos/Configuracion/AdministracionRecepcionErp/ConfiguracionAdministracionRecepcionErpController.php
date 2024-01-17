<?php

namespace App\Http\Modulos\Configuracion\AdministracionRecepcionErp;

use Validator;
use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronico;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoTenant;
use App\Http\Modulos\Configuracion\AdministracionRecepcionErp\ConfiguracionAdministracionRecepcionErp;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionAdministracionRecepcionErpController extends OpenTenantController {
    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Administración Recepción ERP';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Administración Recepción ERP';

    /**
     * Modelo relacionado a la parametrica.
     * 
     * @var Model
     */
    public $className = ConfiguracionAdministracionRecepcionErp::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear la Administración Recepción ERP';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear la Administración Recepción ERP';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar la Administración Recepción ERP';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar la Administración Recepción ERP';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id de la Administración Recepción ERP [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id de la Administración Recepción ERP [%s] Esta Inactivo';

    /**
     * Propiedad contiene los datos del usuario autenticado.
     *
     * @var Object
     */
    protected $user;

    /**
     * Propiedad para almacenar los errores.
     *
     * @var Array
     */
    protected $errors = [];

    /**
     * Nombre del dato para cambiar los estados.
     *
     * @var String
     */
    public $nombreDatoCambiarEstado = 'erp';

    /**
     * Nombre del campo de identificación.
     *
     * @var String
     */
    public $nombreCampoIdentificacion = 'ate_id';

    /**
     * Extensiones permitidas para el cargue de registros.
     * 
     * @var Array
     */
    public $arrExtensionesPermitidas = ['xlsx', 'xls'];

    /**
    * Constructor
    */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdministracionRecepcionERP,ConfiguracionAdministracionRecepcionERPNuevo,ConfiguracionAdministracionRecepcionERPEditar,ConfiguracionAdministracionRecepcionERPVer,ConfiguracionAdministracionRecepcionERPCambiarEstado,ConfiguracionAdministracionRecepcionERPSubir,ConfiguracionAdministracionRecepcionERPDescargarExcel'
        ])->except([
            'store',
            'update',
            'show',
            'cambiarEstado',
            'buscarCondicionNgSelect'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdministracionRecepcionERPNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdministracionRecepcionERPEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdministracionRecepcionERPVer,ConfiguracionAdministracionRecepcionERPEditar'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdministracionRecepcionERPCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);
    }

    /**
     * Muestra una lista de Administración Recepción ERP.
     *
     * @param  Request
     * @return void
     */
    public function getListaAdministracionRecepcionErp(Request $request) {
        $user = auth()->user();
        
        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'ate_id',
            'ofe_id',
            'ate_erp',
            'ate_grupo',
            'ate_descripcion',
            'ate_aplica_para',
            'ate_deben_aplica',
            'xde_id_main',
            'xde_id_tenant',
            'ate_condicion',
            'ate_valor',
            'ate_accion',
            'ate_accion_titulo',
            'xde_accion_id_main',
            'xde_accion_id_tenant',
            'usuario_creacion',
            'fecha_creacion',
            'fecha_modificacion',
            'estado'
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getParametrosXpathDocumentoElectronico',
            'getParametrosXpathDocumentoElectronicoTenant',
            'getConfiguracionObligadoFacturarElectronicamente'
        ];

        $whereHasConditions = [];
        if(!empty($user->bdd_id_rg)) {
            $whereHasConditions[] = [
                'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                'function' => function($query) use ($user) {
                    $query->where('bdd_id_rg', $user->bdd_id_rg);
                }
            ];
        } else {
            $whereHasConditions[] = [
                'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                'function' => function($query) {
                    $query->whereNull('bdd_id_rg');
                }
            ];
        }

        $exportacion = [
            'columnas' => [
                'ofe_id' => [
                    'multiple' => true,
                    'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                    'fields'   => [
                        [
                            'label' => 'NIT',
                            'field' => 'ofe_identificacion'
                        ],
                        [
                            'label' => 'OFE - RECEPTOR',
                            'field' => 'ofe_razon_social'
                        ]
                    ]
                ],
                'ate_erp'                   => 'ERP',
                'ate_grupo'                 => 'GRUPO',
                'ate_descripcion'           => 'DESCRIPCION REGLA',
                'ate_aplica_para'           => 'TIPO DOCUMENTO',
                'ate_deben_aplica'          => 'APLICAN',
                'xde_condicion_descripcion' => 'DESCRIPCION XPATH',
                'ate_condicion'             => 'CONDICION',
                'ate_valor'                 => 'VALOR',
                'ate_accion'                => 'ACCION',
                'ate_accion_titulo'         => 'TITULO',
                'xde_accion_descripcion'    => 'DESCRIPCION XPATH TITULO',
                'fecha_creacion' => [
                    'label' => 'FECHA CREACION',
                    'type'  => self::TYPE_CARBON
                ],
                'fecha_modificacion' => [
                    'label' => 'FECHA MODIFICACION',
                    'type'  => self::TYPE_CARBON
                ],
                'estado' => [
                    'label' => 'ESTADO',
                ]
            ],
            'titulo' => 'administracion_recepcion_erp'
        ];

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'rep_administracion_transmision_erp');
    }

    /**
     * Configura las reglas para poder actualizar o crear los datos de la administración recepción ERP.
     *
     * @return mixed
     */
    private function getRules($recurso = 'store') {
        $rules = ($recurso === 'store') ? array_merge($this->className::$rules) : array_merge($this->className::$rulesUpdate);
        unset(
            $rules['ofe_id'],
            $rules['ate_grupo'],
            $rules['ate_condicion'],
            $rules['ate_valor'],
            $rules['ate_accion_titulo']
        );

        return $rules;
    }

    /**
    * Almacena una Administración Recepción ERP recién creado en el almacenamiento.
    *
    * @param  Request  $request
    * @return Response
    */
    public function store(Request $request) {
        $this->errors = [];
        // Usuario autenticado
        $this->user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$ofe)
            $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación {$request->ofe_identificacion} no existe o se encuentra en estado INACTIVO."]);

        if(!empty($this->errors)) {
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors' => $this->errors
            ], 400);
        }

        $registros = $this->deconstruirERP($request, $ofe->ofe_id);

        foreach ($registros as $key => $value) {
            $validador = Validator::make($value, $this->getRules());
            if ($validador->fails()) {
                $this->errors = $this->adicionarError($this->errors, ["Condición [{$key}] no es válida."]);
            }
        }

        if(!empty($this->errors)) {
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors' => $this->errors
            ], 400);
        }

        if (empty($this->errors)) {
            $grupo = "";

            foreach ($registros as $key => $registro) {
                if(!empty($registro['xde_id_main'])) {
                    $xpathMain = ParametrosXpathDocumentoElectronico::select(['xde_id', 'xde_descripcion', 'xde_xpath'])
                        ->where('xde_id', $registro['xde_id_main'])
                        ->where('estado', 'ACTIVO')
                        ->first();
                    
                    if (!$xpathMain)
                        $this->errors = $this->adicionarError($this->errors, ["El XPath {$registro['xde_id_main']} no existe o se encuentra en estado INACTIVO."]);
                }

                if(!empty($registro['xde_id_tenant'])) {
                    $xpathTenant = ParametrosXpathDocumentoElectronicoTenant::select(['xde_id', 'xde_descripcion', 'xde_xpath'])
                        ->where('xde_id', $registro['xde_id_tenant'])
                        ->where('ofe_id', $ofe->ofe_id)
                        ->where('estado', 'ACTIVO')
                        ->first();
                    
                    if (!$xpathTenant)
                        $this->errors = $this->adicionarError($this->errors, ["El XPath {$registro['xde_id_tenant']} no existe o se encuentra en estado INACTIVO."]);
                }

                if (!empty($this->errors)) {
                    return response()->json([
                        'message' => $this->mensajeErroresCreacion,
                        'errors'  => $this->errors
                    ], 409);
                }

                $registro['ofe_id']           = $ofe->ofe_id;
                $registro['usuario_creacion'] = $this->user->usu_id;
                $registro['estado']           = 'ACTIVO';
                $grupo                        = $registro['ate_grupo'];

                try {
                    $objCreate = $this->className::create($registro);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => $this->mensajeErroresCreacion,
                        'errors' => []
                    ], 422);
                }
            } // FIN FOREACH

            return response()->json([
                'success' => true,
                'ate_grupo'  => $grupo
            ], 200);
        }
    }

    /**
     * Muestra un registro de Administración Recepción ERP especificada.
     *
     * @param  int  $id Identificador del registro procesado
     * @return Response
     */
    public function show($id) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getParametrosXpathDocumentoElectronico',
            'getParametrosXpathDocumentoElectronicoTenant',
            'getConfiguracionObligadoFacturarElectronicamente'
        ];
        $consulta = $this->className::where('ate_grupo', $id)
            ->with($arrRelaciones)
            ->get();

        $registros = $this->reconstruirERP($consulta);

        return response()->json([
            'data'  => $registros
        ], 200);
    }

    /**
    * Actualiza la Administración Recepción ERP especificada en el almacenamiento.
    *
    * @param  Request $request
    * @param  String $ate_grupo Id del grupo de Condiciones
    * @return Response
    */
    public function update(Request $request, $ate_grupo){
        $this->errors = [];
        // Usuario autenticado
        $this->user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$ofe)
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors' => ["El OFE con identificación {$request->ofe_identificacion} no existe o se encuentra en estado INACTIVO."]
            ], 400);

        // Se eliminan los registros que se envian en el request en el array de eliminados.
        $arrIdsEliminar = $request->filled('ids_eliminar') ? $request->ids_eliminar : [];
        unset($request['ids_eliminar']);

        if (!empty($arrIdsEliminar)) {
            $this->className::select(['ate_id'])
                ->whereIn('ate_id', $arrIdsEliminar)
                ->delete();
        }

        $registros = $this->deconstruirERP($request, $ofe->ofe_id, 'update', $ate_grupo);

        foreach ($registros as $key => $value) {
            $validador = Validator::make($value, $this->getRules('update'));
            if ($validador->fails()) {
                $this->errors = $this->adicionarError($this->errors, ["Condición [{$key}] no es válida."]);
            }
        }

        if(!empty($this->errors)) {
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors' => $this->errors
            ], 400);
        }

        if (empty($this->errors)) {
            foreach ($registros as $key => $registro) {
                if(!empty($registro['xde_id_main'])) {
                    $xpathMain = ParametrosXpathDocumentoElectronico::select(['xde_id', 'xde_descripcion', 'xde_xpath'])
                        ->where('xde_id', $registro['xde_id_main'])
                        ->where('estado', 'ACTIVO')
                        ->first();
                    
                    if (!$xpathMain)
                        $this->errors = $this->adicionarError($this->errors, ["El XPath {$registro['xde_id_main']} no existe o se encuentra en estado INACTIVO."]);
                }
        
                if(!empty($registro['xde_id_tenant'])) {
                    $xpathTenant = ParametrosXpathDocumentoElectronicoTenant::select(['xde_id', 'xde_descripcion', 'xde_xpath'])
                        ->where('xde_id', $registro['xde_id_tenant'])
                        ->where('ofe_id', $ofe->ofe_id)
                        ->where('estado', 'ACTIVO')
                        ->first();
                    
                    if (!$xpathTenant)
                        $this->errors = $this->adicionarError($this->errors, ["El XPath {$registro['xde_id_tenant']} no existe o se encuentra en estado INACTIVO."]);
                }

                if (!empty($this->errors)) {
                    return response()->json([
                        'message' => $this->mensajeErroresModificacion,
                        'errors'  => $this->errors
                    ], 409);
                }

                if ($registro['ate_accion'] === 'NO_TRANSMITIR' || $registro['ate_accion'] === 'EXCLUIR_CIERRE') {
                    $registro['xde_accion_id_main']   = null;
                    $registro['xde_accion_id_tenant'] = null;
                }

                try {
                    $consulta = $this->className::where('ate_id', $registro['ate_id'])
                        ->first();
                    
                    if ($consulta) {
                        $this->className::where('ate_id', $registro['ate_id'])->update($registro);
                    } else {
                        $registro['usuario_creacion'] = $this->user->usu_id;
                        $this->className::create($registro);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => $this->mensajeErroresModificacion,
                        'errors' => [$e->getMessage()]
                    ], 422);
                }
            }

            return response()->json([
                'success' => true
            ], 200);
        }
    }

    /**
    * Cambia el estado de una lista de registros seleccionados.
    *
    * @param  Request $request
    * @return Response
    */
    public function cambiarEstado(Request $request){
        $arrErrores = [];

        foreach ($request->all() as $grupo) {
            if(!is_array($grupo)) continue;

            $registro = $this->className::select(['ate_id', 'ate_grupo', 'estado'])
                ->where('ate_grupo', $grupo['ate_grupo'])
                ->get()
                ->map(function ($registro) {
                    $strEstado = ($registro->estado == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                    $registro->update(['estado' => $strEstado]);

                    if (!$registro) {
                        $this->adicionarErrorArray($arrErrores, ["Errores Al Actualizar la {$this->nombre} [{$registro->ate_grupo}]"]);
                    }
                });
            
            if (!$registro) {
                $this->adicionarErrorArray($arrErrores, ["El Grupo {$this->nombre} [{$grupo['ate_grupo']}] No Existe."]);
            }
        }

        if(empty($arrErrores)) {
            return response()->json([
                'success' => true
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'message' => "Error Al Cambiar El Estado De La {$this->nombrePlural} Seleccionadas",
                'errors' => $arrErrores
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Busca las condiciones en base a un criterio predictivo.
     *
     * @param  String $valorBuscar Valor a buscar
     * @param  String $aplicaPara Tipo de Documento
     * @param  String $ofe_identificacion Identificacion del OFE
     * @return Response
     */
    public function buscarCondicionNgSelect($valorBuscar, $aplicaPara, $ofe_identificacion) {
        $user    = auth()->user();
        $comodin = "'%$valorBuscar%'";
        $sqlRaw  = "xde_descripcion LIKE $comodin";
        $arrDataMain = [];
        $arrDataTenant = [];
        $arrAplicaPara = explode('-', $aplicaPara);

        $consultaMain = ParametrosXpathDocumentoElectronico::select(['xde_id', 'xde_descripcion'])
            ->where('estado', 'ACTIVO')
            ->whereRaw($sqlRaw);
            if (!empty($arrAplicaPara)) {
                $consultaMain = $consultaMain->where( function($query) use ($arrAplicaPara) {
                    $flag = true;
                    foreach ($arrAplicaPara as $key => $value) {
                        if ($flag) {
                            $query = $query->where('xde_aplica_para', 'LIKE', '%'.$value.'%');
                            $flag = false;
                        } else {
                            $query = $query->orWhere('xde_aplica_para', 'LIKE', '%'.$value.'%');
                        }
                    }
                });
            }
        $consultaMain = $consultaMain->get();
        
        $consultaTenant = ParametrosXpathDocumentoElectronicoTenant::select(['xde_id', 'xde_descripcion'])
            ->where('estado', 'ACTIVO')
            ->whereRaw($sqlRaw)
            ->whereHas('getConfiguracionObligadoFacturarElectronicamente', function ($query) use($ofe_identificacion) {
                $query->where('ofe_identificacion', $ofe_identificacion);
            });
            if (!empty($arrAplicaPara)) {
                $consultaTenant = $consultaTenant->where( function($query) use ($arrAplicaPara) {
                    $flag = true;
                    foreach ($arrAplicaPara as $key => $value) {
                        if ($flag) {
                            $query = $query->where('xde_aplica_para', 'LIKE', '%'.$value.'%');
                            $flag = false;
                        } else {
                            $query = $query->orWhere('xde_aplica_para', 'LIKE', '%'.$value.'%');
                        }
                    }
                });
            }
        $consultaTenant = $consultaTenant->get();

        foreach ($consultaMain as $item) {
            $data = $item->toArray();
            $data['origen'] = 'main';
            $data['accion_origen'] = 'main';
            $data['xde_accion_id'] = $item['xde_id'];
            $data['xde_accion_descripcion'] = $item['xde_descripcion'];
            $arrDataMain[] = $data;
        }

        foreach ($consultaTenant as $item) {
            $data = $item->toArray();
            $data['origen'] = 'tenant';
            $data['accion_origen'] = 'tenant';
            $data['xde_accion_id'] = $item['xde_id'];
            $data['xde_accion_descripcion'] = $item['xde_descripcion'];
            $arrDataTenant[] = $data;
        }

        $arrData = array_merge($arrDataMain, $arrDataTenant);
        return response()->json([
            'data' => $arrData
        ], 200);
    }

    /**
    * Construye la data para crear o actualizar un registro.
    *
    * @param  Request $request
    * @param  String $ofe_id Id del OFE
    * @param  String $recurso Define si se crea o se actualiza el registro
    * @param  String $grupo Grupo de los registros
    * @return Response
    */
    private function deconstruirERP($request, $ofe_id, $recurso = 'store', $grupo = null) {
        $data = $request->all();
        $condiciones = !empty($request->condicionesGlobales) ? $request->condicionesGlobales : [];
        $arrData = [];
        $ate_grupo = ($recurso === 'store') ? Uuid::uuid4()->toString() : $grupo;

        $idAccion = explode("-", $data['xde_accion_id']);
        if($data['accion_origen'] == "main") {
            $data['xde_accion_id_main'] = $idAccion[0];
            $data['xde_accion_id_tenant'] = null;
        } elseif($data['accion_origen'] == "tenant") {
            $data['xde_accion_id_tenant'] = $idAccion[0];
            $data['xde_accion_id_main'] = null;
        }

        if ($data['ate_accion_titulo'] == '') {
            $data['ate_accion_titulo'] = '';
        }
        $data['ate_aplica_para'] = is_array($data['ate_aplica_para']) ? implode(",", $data['ate_aplica_para']) : $data['ate_aplica_para'];
        unset(
            $data['xde_accion_descripcion'],
            $data['condicionesGlobales'],
            $data['ofe_identificacion'],
            $data['xde_accion_id'],
            $data['accion_origen']
        );

        foreach ($condiciones as $value) {
            $registro = [];
            $id = explode("-", $value['xde_id']);
            if($value['origen'] == "main") {
                $registro['xde_id_main'] = $id[0];
                $registro['xde_id_tenant'] = null;

            } else {
                $registro['xde_id_tenant'] = $id[0];
                $registro['xde_id_main'] = null;
            }
            if($recurso === 'update')
                $registro['ate_id'] = $value['ate_id'];

            $registro['ate_condicion'] = $value['ate_condicion'];
            $registro['ate_valor']     = $value['ate_valor'];
            $registro['ate_grupo']     = $ate_grupo;
            $registro['ofe_id']        = $ofe_id;

            $arrData[] = array_merge($data, $registro);
        }

        return $arrData;
    }

    /**
    * Construye la data para cargar los registro.
    *
    * @param  Object $registros Data de los registros a cargar
    * @return Response
    */
    private function reconstruirERP($registros) {
        $arrRegistros = $registros->toArray();
        $arrData = [];
        $condicion = [];
        $arrCondiciones = [];
        $cont = 0;

        foreach ($arrRegistros as $registro) {
            $accion = '';
            $origen = '';
            $origenAccion = '';
            $xpathAccion['xde_descripcion'] = '';

            if(!empty($registro['xde_accion_id_main'])) {
                $accion = $registro['xde_accion_id_main'];
                $origenAccion = 'main';
                $xpathAccion = ParametrosXpathDocumentoElectronico::select(['xde_descripcion'])
                        ->where('xde_id', $registro['xde_accion_id_main'])
                        ->where('estado', 'ACTIVO')
                        ->first();

            } elseif(!empty($registro['xde_accion_id_tenant'])) {
                $accion = $registro['xde_accion_id_tenant'];
                $origenAccion = 'tenant';
                $xpathAccion = ParametrosXpathDocumentoElectronicoTenant::select(['xde_descripcion'])
                        ->where('xde_id', $registro['xde_accion_id_tenant'])
                        ->where('ofe_id', $registro['ofe_id'])
                        ->where('estado', 'ACTIVO')
                        ->first();
            }

            if($cont === 0) {
                $arrData = [
                    'ofe_id'                                               => $registro['ofe_id'],
                    'ate_erp'                                              => $registro['ate_erp'],
                    'ate_grupo'                                            => $registro['ate_grupo'],
                    'ate_descripcion'                                      => $registro['ate_descripcion'],
                    'ate_aplica_para'                                      => $registro['ate_aplica_para'],
                    'ate_deben_aplica'                                     => $registro['ate_deben_aplica'],
                    'ate_accion'                                           => $registro['ate_accion'],
                    'ate_accion_titulo'                                    => $registro['ate_accion_titulo'],
                    "accion_origen"                                        => $origenAccion,
                    "xde_accion_id"                                        => $accion,
                    "xde_accion_descripcion"                               => $xpathAccion['xde_descripcion'],
                    'usuario_creacion'                                     => $registro['usuario_creacion'],
                    'fecha_creacion'                                       => $registro['fecha_creacion'],
                    'fecha_modificacion'                                   => $registro['fecha_modificacion'],
                    'estado'                                               => $registro['estado'],
                    'get_usuario_creacion'                                 => $registro['get_usuario_creacion'],
                    'get_configuracion_obligado_facturar_electronicamente' => $registro['get_configuracion_obligado_facturar_electronicamente']
                ];
            }

            if(!empty($registro['get_parametros_xpath_documento_electronico'])) {
                $condiciones = $registro['get_parametros_xpath_documento_electronico'];
                $origen = 'main';
            } elseif(!empty($registro['get_parametros_xpath_documento_electronico_tenant'])) {
                $condiciones = $registro['get_parametros_xpath_documento_electronico_tenant'];
                $origen = 'tenant';
            }

            $condicion['xde_id']          = $condiciones['xde_id'] . '-' . $origen;
            $condicion['origen']          = $origen;
            $condicion['xde_descripcion'] = $condiciones['xde_descripcion'];
            $condicion['ate_condicion']   = $registro['ate_condicion'];
            $condicion['ate_valor']       = $registro['ate_valor'];
            $condicion['ate_id']          = $registro['ate_id'];

            $arrCondiciones[] =  $condicion;

            $cont++;
        }

        $arrData['condicionesGlobales'] = $arrCondiciones;

        return collect($arrData);
    }
}
