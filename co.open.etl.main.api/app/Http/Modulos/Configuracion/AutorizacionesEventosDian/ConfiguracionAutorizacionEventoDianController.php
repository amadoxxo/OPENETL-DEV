<?php

namespace App\Http\Modulos\Configuracion\AutorizacionesEventosDian;

use stdClass;
use Validator;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\OfeAdqValidations;
use openEtl\Tenant\Traits\TenantTrait;
use App\Http\Controllers\OpenTenantController;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuario;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionAutorizacionEventoDianController extends OpenTenantController{
    use OfeAdqValidations;

    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Autorización Evento DIAN';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Autorizaciones Eventos Dian';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ConfiguracionAutorizacionEventoDian::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear la Autorización Evento DIAN';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear la Autorización Evento DIAN';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar la Autorización Evento DIAN';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar la Autorización Evento DIAN';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id de la Autorización Evento DIAN [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id de la Autorización Evento DIAN [%s] Esta Inactivo';

    /**
     * Propiedad Contiene las datos del usuario autenticado.
     *
     * @var Object
     */
    protected $user;

    /**
     * Nombre del campo que contiene los valores de identificación de los registros a modificar.
     * 
     * @var String
     */
    public $nombreDatoCambiarEstado = 'usuarios';

    /**
     * Nombre del campo que contiene el Id del registro.
     * 
     * @var String
     */
    public $nombreCampoIdentificacion = 'use_id';

    /**
     * Extensiones permitidas para el cargue de registros.
     * 
     * @var Array
     */
    public $arrExtensionesPermitidas = ['xlsx', 'xls'];

    /**
     * Almacena las columnas que se generan en la interfaz de Excel.
     * 
     * @var Array
     */
    public $columnasExcel = [];

    /**
    * Constructor
    */
    public function __construct() {
        TenantTrait::GetVariablesSistemaTenant();
        $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
        $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();
            
        $this->columnasExcel = [
            'NIT OFE',
            'NIT PROVEEDOR',
            'CODIGO ' . (isset($gruposTrabajo->singular) ? $gruposTrabajo->singular : 'GRUPO TRABAJO'),
            'EMAIL USUARIO OPENETL',
            'CODIGO TIPO DOCUMENTO',
            'IDENTIFICACION',
            'NOMBRES',
            'APELLIDOS',
            'CARGO',
            'AREA',
            'ACUSE DE RECIBO',
            'RECLAMO',
            'RECIBO BIEN',
            'ACEPTACION EXPRESA'
        ];

        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAutorizacionesEventosDian,ConfiguracionAutorizacionesEventosDianNuevo,ConfiguracionAutorizacionesEventosDianEditar,ConfiguracionAutorizacionesEventosDianVer,ConfiguracionAutorizacionesEventosDianCambiarEstado,ConfiguracionAutorizacionesEventosDianSubir,ConfiguracionAutorizacionesEventosDianDescargarExcel'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'busqueda',
            'generarInterfaceAutorizacionesEventosDian',
            'cargarAutorizacionesEventosDian',
            'getListaErroresAutorizacionesEventosDian',
            'descargarListaErroresAutorizacionesEventosDian'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAutorizacionesEventosDianNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAutorizacionesEventosDianEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAutorizacionesEventosDianVer,ConfiguracionAutorizacionesEventosDianEditar'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAutorizacionesEventosDianVer'
        ])->only([
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAutorizacionesEventosDianCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAutorizacionesEventosDianSubir'
        ])->only([
            'generarInterfaceAutorizacionesEventosDian',
            'cargarAutorizacionesEventosDian',
            'getListaErroresAutorizacionesEventosDian',
            'descargarListaErroresAutorizacionesEventosDian'
        ]);
    }

    /**
     * Muestra una lista de usuarios eventos.
     *
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function getListaAutorizacionesEventosDian(Request $request) {
        $user = auth()->user();

        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'etl_autorizaciones_eventos_dian.use_id',
            'etl_autorizaciones_eventos_dian.ofe_id',
            'etl_autorizaciones_eventos_dian.pro_id',
            'etl_autorizaciones_eventos_dian.gtr_id',
            'etl_autorizaciones_eventos_dian.usu_id',
            'etl_autorizaciones_eventos_dian.tdo_id',
            'etl_autorizaciones_eventos_dian.use_identificacion',
            'etl_autorizaciones_eventos_dian.use_nombres',
            'etl_autorizaciones_eventos_dian.use_apellidos',
            'etl_autorizaciones_eventos_dian.use_cargo',
            'etl_autorizaciones_eventos_dian.use_area',
            'etl_autorizaciones_eventos_dian.use_acuse_recibo',
            'etl_autorizaciones_eventos_dian.use_recibo_bien',
            'etl_autorizaciones_eventos_dian.use_aceptacion_expresa',
            'etl_autorizaciones_eventos_dian.use_reclamo',
            'etl_autorizaciones_eventos_dian.usuario_creacion',
            'etl_autorizaciones_eventos_dian.estado',
            'etl_autorizaciones_eventos_dian.fecha_modificacion',
            'etl_autorizaciones_eventos_dian.fecha_creacion'
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente' => function ($query) {
                $query->select(['ofe_id', 'ofe_identificacion'])
                    ->selectRaw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo');
            },
            'getUsuarioAutorizacionEventoDian',
            'getConfiguracionProveedor' => function ($query) {
                $query->select(['pro_id', 'pro_identificacion'])
                    ->selectRaw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo');
            },
            'getConfiguracionGrupoTrabajo',
            'getTipoDocumento'
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

        TenantTrait::GetVariablesSistemaTenant();
        $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
        $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();

        $exportacion = [
            'columnas' => [
                [
                    'multiple' => true,
                    'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                    'fields' => [
                        [
                            'label' => 'NIT OFE',
                            'field' => 'ofe_identificacion'
                        ],
                        [
                            'label' => 'RAZON SOCIAL OFE',
                            'field' => 'nombre_completo'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getConfiguracionProveedor',
                    'fields' => [
                        [
                            'label' => 'NIT PROVEEDOR',
                            'field' => 'pro_identificacion'
                        ],
                        [
                            'label' => 'RAZON SOCIAL PROVEEDOR',
                            'field' => 'nombre_completo'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getConfiguracionGrupoTrabajo',
                    'fields' => [
                        [
                            'label' => 'CODIGO ' . (isset($gruposTrabajo->singular) ? $gruposTrabajo->singular : 'GRUPO TRABAJO'),
                            'field' => 'gtr_codigo'
                        ],
                        [
                            'label' => (isset($gruposTrabajo->singular) ? $gruposTrabajo->singular : 'GRUPO TRABAJO'),
                            'field' => 'gtr_nombre'
                        ]
                    ]
                ],
                [
                    'multiple' => false,
                    'relation' => 'getUsuarioAutorizacionEventoDian',
                    'fields' => [
                        [
                            'label' => 'EMAIL USUARIO OPENETL',
                            'field' => 'usu_email'
                        ],
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getTipoDocumento',
                    'fields' => [
                        [
                            'label' => 'CODIGO TIPO DOCUMENTO',
                            'field' => 'tdo_codigo'
                        ],
                        [
                            'label' => 'TIPO DOCUMENTO',
                            'field' => 'tdo_descripcion'
                        ]
                    ]
                ],
                'use_identificacion' => 'IDENTIFICACION',
                'use_nombres' => 'NOMBRES',
                'use_apellidos' => 'APELLIDOS',
                [
                    'multiple' => false,
                    'relation' => 'getUsuarioAutorizacionEventoDian',
                    'fields' => [
                        [
                            'label' => 'EMAIL',
                            'field' => 'usu_email'
                        ],
                    ]
                ],
                'use_cargo' => 'CARGO',
                'use_area' => 'AREA',
                'use_acuse_recibo' => 'ACUSE DE RECIBO',
                'use_reclamo' => 'RECLAMO',
                'use_recibo_bien' => 'RECIBO BIEN',
                'use_aceptacion_expresa' => 'ACEPTACION EXPRESA',
                'estado' =>  [
                    'label' => 'ESTADO AUTORIZACION',
                ],
                [
                    'multiple' => false,
                    'relation' => 'getUsuarioAutorizacionEventoDian',
                    'fields' => [
                        [
                            'label' => 'ESTADO USUARIO',
                            'field' => 'estado'
                        ],
                    ]
                ],
                'usuario_creacion' => [
                    'label' => 'USUARIO CREACION',
                    'relation' => ['name' => 'getUsuarioCreacion', 'field' => 'usu_nombre']
                ],
                'fecha_creacion' =>  [
                    'label' => 'FECHA CREACION',
                    'type' => self::TYPE_CARBON
                ],
                'fecha_modificacion' =>  [
                    'label' => 'FECHA MODIFICACION',
                    'type' => self::TYPE_CARBON
                ]
            ],
            'titulo' => 'autorizaciones_eventos_dian'
        ];

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Permite definir si un usuario esta asociado con determinado grupo de trabajo.
     *
     * @param ConfiguracionGrupoTrabajo $grupoTrabajoComparar Colección del grupo de trabajo con el cual comparar
     * @param int $usuId ID del usuario
     * @param string $usuIdentificacion Identificación del usuario
     * @return array Array conteniendo un error de verificación o un array vacio
     */
    private function usuarioGrupoRelacionados($grupoTrabajoComparar, $usuId, $usuIdentificacion) {
        TenantTrait::GetVariablesSistemaTenant();
        $sysVarGruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
        $sysVarGruposTrabajo = !empty($sysVarGruposTrabajo) ? json_decode($sysVarGruposTrabajo) : new \stdClass();

        $gruposTrabajoUsuario = ConfiguracionGrupoTrabajoUsuario::select()
            ->where('usu_id', $usuId)
            ->get()
            ->pluck('gtr_id')
            ->values()
            ->toArray();

        if(!in_array($grupoTrabajoComparar->gtr_id, $gruposTrabajoUsuario)) {
            return ["Usuario [" . $usuIdentificacion . "] no se encuentra asociado con el(la) " . (isset($sysVarGruposTrabajo->singular) ? $sysVarGruposTrabajo->singular : 'GRUPO TRABAJO') . " [{$grupoTrabajoComparar->gtr_nombre}]."];
        } else {
            return [];
        }
    }

    /**
     * Almacena una configuración de eventos Dian recién creado en el almacenamiento.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request) {
        TenantTrait::GetVariablesSistemaTenant();
        $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
        $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();

        $this->user = auth()->user();
        $data = $request->all();
        $validador = Validator::make($request->all(), [
            'ofe_identificacion'     => 'required|string|max:20',
            'pro_identificacion'     => 'nullable|string|max:20',
            'gtr_codigo'             => 'nullable|string|max:10',
            'usu_email'              => 'nullable|string|max:255',
            'tdo_codigo'             => 'required|string|max:2',
            'use_identificacion'     => 'required|string|max:20',
            'use_nombres'            => 'required|string|max:100',
            'use_apellidos'          => 'required|string|max:100',
            'use_cargo'              => 'nullable|string|max:100',
            'use_area'               => 'nullable|string|max:100',
            'use_acuse_recibo'       => 'nullable|string|max:2|in:SI,NO',
            'use_reclamo'            => 'nullable|string|max:2|in:SI,NO',
            'use_recibo_bien'        => 'nullable|string|max:2|in:SI,NO',
            'use_aceptacion_expresa' => 'nullable|string|max:2|in:SI,NO'
        ]);

        if (!$validador->fails()) {
            if(!$request->filled('pro_identificacion') && !$request->filled('gtr_codigo') && !$request->filled('usu_email'))
                return response()->json([
                    'message' => 'Error al crear la Autorización Evento DIAN',
                    'errors' => ["Verifique que la petición incluya adicional al OFE (Receptor), el Proveedor (Emisor) o el Usuario o el (la) " . $gruposTrabajo->singular]
                ], 400);

            if(!$request->filled('use_acuse_recibo') && !$request->filled('use_recibo_bien') && !$request->filled('use_aceptacion_expresa') && !$request->filled('use_reclamo'))
                return response()->json([
                    'message' => 'Error al crear la Autorización Evento DIAN',
                    'errors' => ["No indicó el Evento DIAN a configurar"]
                ], 400);

            // Válida que el ofe procesado exista y que pertenezca a la misma base de datos del usuario autenticado
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$ofe)
                return response()->json([
                    'message' => 'Error al crear la Autorización Evento DIAN',
                    'errors' => ["El OFE seleccionado no existe o se encuentra Inactivo."]
                ], 404);

            // Inicia la construcción de la coonsulta para válida si ya existe un registro conforme a los parámetros recibidos
            $existe = $this->className::where('ofe_id', $ofe->ofe_id);

            if($request->filled('pro_identificacion')) {
                $proveedor = ConfiguracionProveedor::select(['pro_id', 'pro_identificacion'])
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('pro_identificacion', $request->pro_identificacion)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if (!$proveedor)
                    return response()->json([
                        'message' => 'Error al crear la Autorización Evento DIAN',
                        'errors' => ["El Proveedor seleccionado no existe o se encuentra Inactivo"]
                    ], 404);

                $existe = $existe->where('pro_id', $proveedor->pro_id);
            }

            if($request->filled('gtr_codigo')) {
                $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id', 'gtr_codigo', 'gtr_nombre'])
                    ->where('gtr_codigo', $request->gtr_codigo)
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if (!$grupoTrabajo)
                    return response()->json([
                        'message' => 'Error al crear la Autorización Evento DIAN',
                        'errors' => ["El(la) " . $gruposTrabajo->singular . " seleccionado(a) no existe o se encuentra Inactivo"]
                    ], 404);

                $existe = $existe->where('gtr_id', $grupoTrabajo->gtr_id);
            }

            if($request->filled('usu_email')) {
                // Válida que el usuario procesado exista y que pertenezca a la misma base de datos del usuario autenticado
                $usuario = User::select(['usu_id', 'usu_email', 'bdd_id', 'bdd_id_rg'])
                    ->where('usu_email', $request->usu_email)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($usuario === null) {
                    return response()->json([
                        'message' => 'Error al crear la Autorización Evento DIAN',
                        'errors' => ["El Usuario {$request->ofe_identificacion} no existe."]
                    ], 404);
                }

                if((
                    !empty($this->user->bdd_id) && $this->user->bdd_id != $usuario->bdd_id) &&
                    (!empty($this->user->bdd_id_rg) && $this->user->bdd_id_rg != $usuario->bdd_id_rg)
                ) {
                    return response()->json([
                        'message' => 'Error al crear la Autorización Evento DIAN',
                        'errors' => ["Usuario no válido."]
                    ], 422);
                }

                // Si se envío grupo de trabajo, se debe verificar que el usuario este relacionado con dicho grupo de trabajo
                if(isset($grupoTrabajo->gtr_id)) {
                    $relacionados = $this->usuarioGrupoRelacionados($grupoTrabajo, $usuario->usu_id, $request->usu_email);
                    if(!empty($relacionados)) {
                        return response()->json([
                            'message' => 'Error al crear la Autorización Evento DIAN',
                            'errors' => $relacionados
                        ], 422);
                    }
                }

                $existe = $existe->where('usu_id', $usuario->usu_id);
            }
            $existe = $existe->first();

            if ($existe) {
                return response()->json([
                    'message' => 'Error al modificar la Autorización Evento DIAN',
                    'errors' => ["La Autorización Evento DIAN conforme a los parámetros recibidos ya existe."]
                ], 409);
            }

            $documento = ParametrosTipoDocumento::select(['tdo_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('estado', 'ACTIVO')
                ->where('tdo_codigo', $request->tdo_codigo)
                ->get()
                ->groupBy('tdo_codigo')
                ->map( function($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        return $vigente['registro'];
                    }
                })->first();

            if(!$documento) {
                return response()->json([
                    'message' => 'Error al modificar la Autorización Evento DIAN',
                    'errors' => ["El Código de Tipo Documento ya no Está Vigente, se Encuentra INACTIVO o no Existe."]
                ], 409);
            }

            unset($data['gtr_codigo']);
            unset($data['ofe_identificacion']);
            unset($data['pro_identificacion']);
            unset($data['usu_email']);
            $data['ofe_id'] = $ofe->ofe_id;
            $data['pro_id'] = isset($proveedor->pro_id) ? $proveedor->pro_id : null;
            $data['gtr_id'] = isset($grupoTrabajo->gtr_id) ? $grupoTrabajo->gtr_id : null;
            $data['usu_id'] = isset($usuario->usu_id) ? $usuario->usu_id : null;
            $data['tdo_id'] = $documento->tdo_id;

            $data['estado'] = 'ACTIVO';
            $data['usuario_creacion'] = auth()->user()->usu_id;
            $obj = $this->className::create($data);

            if($obj){
                return response()->json([
                    'success' => true,
                    'use_id'  => $obj->use_id
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Error al crear la Autorización Evento DIAN',
                    'errors' => []
                ], 422);
            }
        }
        return response()->json([
            'message' => 'Error al crear la Autorización Evento DIAN',
            'errors' => $validador->errors()->all()
        ], 400);
    }
    
    /**
    * Muestra la configuración de eventos Dian especificada.
    *
    * @param  string  $id Cadena conteniendo la identificación del OFE, identificación del proveedor, código del grupo de trabajo y email de usuario
    * @return Response
    */
    public function show($id) {
        $objectoModel = $this->getAutorizacionEventoDian($id, [
            'getUsuarioCreacion:usu_id,usu_email,usu_nombre',
            'getTipoDocumento:tdo_id,tdo_codigo,tdo_descripcion',
            'getUsuarioAutorizacionEventoDian:usu_id,usu_identificacion,usu_nombre,usu_email',
            'getConfiguracionProveedor:pro_id,pro_identificacion,pro_razon_social,pro_primer_nombre,pro_otros_nombres,pro_primer_apellido,pro_segundo_apellido',
            'getConfiguracionGrupoTrabajo:gtr_id,ofe_id,gtr_codigo,gtr_nombre,gtr_servicio,gtr_por_defecto',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_primer_nombre,ofe_otros_nombres,ofe_primer_apellido,ofe_segundo_apellido',
            'getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo:gtr_id,ofe_id,gtr_codigo,gtr_nombre'
        ]);
        
        if ($objectoModel){
            return response()->json([
                'data' => $objectoModel
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => "Se produjo un error al procesar la información",
            'errors' => ["No Se Encontró la {$this->nombre}"]
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Retorna una configuración de eventos Dian en función de su oferente y usuario.
     *
     * @param string $id $id Identificador del registro procesado
     * @param array $relaciones
     * @return mixed
     */
    private function getAutorizacionEventoDian($id, array $relaciones = []) {
        list($ofe_identificacion, $pro_identificacion, $gtr_codigo, $usu_email) = explode(':', $id);

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();
        $ofe_id = '';
        if ($ofe)  {
            $ofe_id = $ofe->ofe_id;
        }

        // Inicia la construcción de la consulta de la autorización de eventos DIAN
        $autorizacionEventoDian = $this->className::select([
            'use_id',
            'ofe_id',
            'pro_id',
            'gtr_id',
            'usu_id',
            'tdo_id',
            'use_identificacion',
            'use_nombres',
            'use_apellidos',
            'use_cargo',
            'use_area',
            'use_acuse_recibo',
            'use_reclamo',
            'use_recibo_bien',
            'use_aceptacion_expresa',
            'usuario_creacion',
            'fecha_creacion',
            'fecha_modificacion',
            'estado'
        ])
            ->where('ofe_id', $ofe_id);

        if(!empty($pro_identificacion)) {
            $proveedor = ConfiguracionProveedor::select(['pro_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_identificacion', $pro_identificacion)
                ->first();
            if ($proveedor)  {
                $autorizacionEventoDian = $autorizacionEventoDian->where('pro_id', $proveedor->pro_id);
            }
        }

        if(!empty($gtr_codigo)) {
            $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id'])
                ->where('gtr_codigo', $gtr_codigo)
                ->where('ofe_id', $ofe->ofe_id)
                ->first();
            if ($grupoTrabajo)  {
                $autorizacionEventoDian = $autorizacionEventoDian->where('gtr_id', $grupoTrabajo->gtr_id);
            }
        }

        if(!empty($usu_email)) {
            $usuario = User::select(['usu_id'])
                ->where('usu_email', $usu_email)
                ->first();
            if ($usuario)  {
                $autorizacionEventoDian = $autorizacionEventoDian->where('usu_id', $usuario->usu_id);
            }
        }

        if (!empty($relaciones))
            $autorizacionEventoDian = $autorizacionEventoDian->with($relaciones);

        return $autorizacionEventoDian->first();
    }

    /**
     * Verifica si un registro de autorización evento DIAN existe previamente
     *
     * @param stdClass $registroOriginal
     * @return ConfiguracionAutorizacionEventoDian $existeRegistro Registro preexistente
     */
    private function consultarRegistroOriginal(stdClass $registroOriginal) {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('estado', 'ACTIVO')
            ->where('ofe_identificacion', $registroOriginal->ofe_identificacion)
            ->first();
            
        $existeRegistro = $this->className::where('ofe_id', $ofe->ofe_id);

        if(isset($registroOriginal->pro_identificacion) && !empty($registroOriginal->pro_identificacion)) {
            $proveedor = ConfiguracionProveedor::select(['pro_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_identificacion', $registroOriginal->pro_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();

            $existeRegistro = $existeRegistro->where('pro_id', ($proveedor ? $proveedor->pro_id : null));
        }

        if(isset($registroOriginal->gtr_codigo) && !empty($registroOriginal->gtr_codigo)) {
            $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id'])
                ->where('gtr_codigo', $registroOriginal->gtr_codigo)
                ->where('ofe_id', $ofe->ofe_id)
                ->where('estado', 'ACTIVO')
                ->first();

            $existeRegistro = $existeRegistro->where('gtr_id', ($grupoTrabajo ? $grupoTrabajo->gtr_id : null));
        }

        if(isset($registroOriginal->usu_email) && !empty($registroOriginal->usu_email)) {
            $usuario = User::select(['usu_id'])
                ->where('usu_email', $registroOriginal->usu_email)
                ->where('estado', 'ACTIVO')
                ->first();

            $existeRegistro = $existeRegistro->where('usu_id', ($usuario ? $usuario->usu_id : null));
        }

        $existeRegistro = $existeRegistro->first();

        return $existeRegistro;
    }
    
    /**
     * Actualiza la configuración de eventos Dian especificado en el almacenamiento.
     *
     * @param  Request  $request
     * @param  string  $id Cadena conteniendo la identificación del OFE, identificación del proveedor, código del grupo de trabajo y email de usuario
     * @return Response
     */
    public function update(Request $request, $id){
        TenantTrait::GetVariablesSistemaTenant();
        $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
        $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();

        $this->user = auth()->user();
        $data = $request->all();
        $validador = Validator::make($request->all(), [
            'ofe_identificacion'     => 'required|string|max:20',
            'pro_identificacion'     => 'nullable|string|max:20',
            'gtr_codigo'             => 'nullable|string|max:10',
            'usu_email'              => 'nullable|string|max:255',
            'tdo_codigo'             => 'required|string|max:2',
            'use_identificacion'     => 'required|string|max:100',
            'use_nombres'            => 'required|string|max:100',
            'use_apellidos'          => 'required|string|max:100',
            'use_cargo'              => 'nullable|string|max:100',
            'use_area'               => 'nullable|string|max:100',
            'use_acuse_recibo'       => 'nullable|string|max:2|in:SI,NO',
            'use_reclamo'            => 'nullable|string|max:2|in:SI,NO',
            'use_recibo_bien'        => 'nullable|string|max:2|in:SI,NO',
            'use_aceptacion_expresa' => 'nullable|string|max:2|in:SI,NO'
        ]);
        if (!$validador->fails()) {
            if(!$request->filled('pro_identificacion') && !$request->filled('gtr_codigo') && !$request->filled('usu_email'))
                return response()->json([
                    'message' => 'Error al actualizar la Autorización Evento DIAN',
                    'errors' => ["Verifique que la petición incluya adicional al OFE (Receptor), el Proveedor (Emisor) o el Usuario o el (la) " . $gruposTrabajo->singular]
                ], 400);

            if(!$request->filled('use_acuse_recibo') && !$request->filled('use_recibo_bien') && !$request->filled('use_aceptacion_expresa') && !$request->filled('use_reclamo'))
                return response()->json([
                    'message' => 'Error al actualizar la Autorización Evento DIAN',
                    'errors' => ["No indicó el Evento DIAN a configurar"]
                ], 400);

            $registroOriginal = $this->consultarRegistroOriginal(json_decode($request->registro_original));
            if(!$registroOriginal)
                return response()->json([
                    'message' => 'Error al actualizar la Autorización Evento DIAN',
                    'errors' => ["La Autorización Evento Dian que intenta actualizar no existe"]
                ], 400);

            // Válida que el ofe procesado exista y que pertenezca a la misma base de datos del usuario autenticado
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$ofe)
                return response()->json([
                    'message' => 'Error al actualizar la Autorización Evento DIAN',
                    'errors' => ["El OFE seleccionado no existe o se encuentra Inactivo."]
                ], 404);

            $autEventosDianExiste = $this->className::where('ofe_id', $ofe->ofe_id);

            if($request->filled('pro_identificacion')) {
                $proveedor = ConfiguracionProveedor::select(['pro_id', 'pro_identificacion'])
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('pro_identificacion', $request->pro_identificacion)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if (!$proveedor)
                    return response()->json([
                        'message' => 'Error al actualizar la Autorización Evento DIAN',
                        'errors' => ["El Proveedor seleccionado no existe o se encuentra Inactivo"]
                    ], 404);

                $autEventosDianExiste = $autEventosDianExiste->where('pro_id', $proveedor->pro_id);
            }

            if($request->filled('gtr_codigo')) {
                $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id', 'gtr_codigo', 'gtr_nombre'])
                    ->where('gtr_codigo', $request->gtr_codigo)
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if (!$grupoTrabajo)
                    return response()->json([
                        'message' => 'Error al actualizar la Autorización Evento DIAN',
                        'errors' => ["El(la) " . $gruposTrabajo->singular . " seleccionado(a) no existe o se encuentra Inactivo"]
                    ], 404);

                $autEventosDianExiste = $autEventosDianExiste->where('gtr_id', $grupoTrabajo->gtr_id);
            }

            if($request->filled('usu_email')) {
                // Válida que el usuario procesado exista y que pertenezca a la misma base de datos del usuario autenticado
                $usuario = User::select(['usu_id', 'usu_email', 'bdd_id', 'bdd_id_rg'])
                    ->where('usu_email', $request->usu_email)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($usuario === null) {
                    return response()->json([
                        'message' => 'Error al actualizar la Autorización Evento DIAN',
                        'errors' => ["El Usuario {$request->ofe_identificacion} no existe."]
                    ], 404);
                }

                if((
                    !empty($this->user->bdd_id) && $this->user->bdd_id != $usuario->bdd_id) &&
                    (!empty($this->user->bdd_id_rg) && $this->user->bdd_id_rg != $usuario->bdd_id_rg)
                ) {
                    return response()->json([
                        'message' => 'Error al actualizar la Autorización Evento DIAN',
                        'errors' => ["Usuario no válido."]
                    ], 422);
                }

                // Si se envío grupo de trabajo, se debe verificar que el usuario este relacionado con dicho grupo de trabajo
                if(isset($grupoTrabajo->gtr_id)) {
                    $relacionados = $this->usuarioGrupoRelacionados($grupoTrabajo, $usuario->usu_id, $request->usu_email);
                    if(!empty($relacionados)) {
                        return response()->json([
                            'message' => 'Error al actualizar la Autorización Evento DIAN',
                            'errors' => $relacionados
                        ], 422);
                    }
                }

                $autEventosDianExiste = $autEventosDianExiste->where('usu_id', $usuario->usu_id);
            }

            $autEventosDianExiste = $autEventosDianExiste->where('use_id', '!=', $registroOriginal->use_id)
                ->first();

            if ($autEventosDianExiste) {
                return response()->json([
                    'message' => 'Error al modificar la Autorización Evento DIAN',
                    'errors' => ["La Autorización Evento DIAN conforme a los parámetros recibidos existe con otro ID."]
                ], 409);
            }

            $documento = ParametrosTipoDocumento::select(['tdo_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('estado', 'ACTIVO')
                ->where('tdo_codigo', $request->tdo_codigo)
                ->get()
                ->groupBy('tdo_codigo')
                ->map( function($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        return $vigente['registro'];
                    }
                })->first();

            if(!$documento) {
                return response()->json([
                    'message' => 'Error al modificar la Autorización Evento DIAN',
                    'errors' => ["El Código de Tipo Documento ya no Está Vigente, se Encuentra INACTIVO o no Existe."]
                ], 409);
            }

            unset($data['gtr_codigo']);
            unset($data['ofe_identificacion']);
            unset($data['pro_identificacion']);
            unset($data['usu_email']);
            $data['ofe_id'] = $ofe->ofe_id;
            $data['pro_id'] = isset($proveedor->pro_id) ? $proveedor->pro_id : null;
            $data['gtr_id'] = isset($grupoTrabajo->gtr_id) ? $grupoTrabajo->gtr_id : null;
            $data['usu_id'] = isset($usuario->usu_id) ? $usuario->usu_id : null;
            $data['tdo_id'] = $documento->tdo_id;
            $obj = $registroOriginal->update($data);

            if($obj){
                return response()->json([
                    'success' => true
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Error al modificar la Autorización Evento DIAN',
                    'errors' => []
                ], 422);
            }
        }
        return response()->json([
            'message' => 'Error al modificar la Autorización Evento DIAN',
            'errors' => $validador->errors()->all()
        ], 400);
    }

    /**
     * Cambia el estado de los usuarios eventos en el almacenamiento.
     *
     * @param Request $request
     * @return Response
     */
    public function cambiarEstado(Request $request) {
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Genera una Interfaz de usuarios eventos para guardar en Excel.
     *
     * @param Request $request
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceAutorizacionesEventosDian(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'autorizaciones_eventos_dian');  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de usuarios eventos.
     *
     * @param Request $request
     * @return
     * @throws \Exception
     */
    public function cargarAutorizacionesEventosDian(Request $request){
        set_time_limit(0);
        ini_set('memory_limit','512M');
        $objUser = auth()->user();

        TenantTrait::GetVariablesSistemaTenant();
        $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
        $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();

        if($request->hasFile('archivo')){
            $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
            if (
                (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
                !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
            )
                return response()->json([
                    'message' => 'Errores al guardar los Usuarios Autorizados Eventos',
                    'errors'  => ['Solo se permite la carga de archivos EXCEL.']
                ], 409);

            $data = $this->parserExcel($request);
            if (!empty($data)) {
                // Se obtinen las columnas de la interfaz sanitizadas
                $tempColumnas = [];
                foreach ($this->columnasExcel as $k) {
                    $tempColumnas[] = strtolower($this->sanear_string(str_replace(' ', '_', $k)));
                }

                // Se obtienen las columnas del excel cargado
                $columnas = [];
                foreach ($data as $fila => $columna) {
                    // Se eliminan las columnas que son propias del excel generado desde el Tracking
                    unset(
                        $columna['razon_social_ofe'],
                        $columna['razon_social_proveedor'],
                        $columna[strtolower($this->sanear_string(str_replace(' ', '_', $gruposTrabajo->singular)))],
                        $columna['tipo_documento'],
                        $columna['email'],
                        $columna['estado_autorizacion'],
                        $columna['estado_usuario'],
                        $columna['usuario_creacion'],
                        $columna['fecha_creacion'],
                        $columna['fecha_modificacion']
                    );

                    $columnas = (array)$columna;
                    break;
                }

                // Valida que las columnas del excel cargado correspondan con las columnas de la interfaz
                $diferenciasFaltan = array_diff($tempColumnas, array_keys($columnas));
                $diferenciasSobran = array_diff(array_keys($columnas), $tempColumnas);
                if (!empty($diferenciasFaltan) || !empty($diferenciasSobran)) {
                    $errores = [];
                    if(!empty($diferenciasFaltan))
                        $errores[] = 'Faltan las columnas: ' . strtoupper(str_replace('_', ' ', implode(', ', $diferenciasFaltan)));

                    if(!empty($diferenciasSobran))
                        $errores[] = 'Sobran las columnas: ' . strtoupper(str_replace('_', ' ', implode(', ', $diferenciasSobran)));

                    return response()->json([
                        'message' => 'La estructura del archivo no corresponde con la interfaz solicitada',
                        'errors'  => $errores
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => 'Errores al guardar los Usuarios Autorizados Eventos',
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores                       = [];
            $arrResultado                     = [];
            $arrExisteOfe                     = [];
            $arrExisteUsuario                 = [];
            $arrExisteProveedor               = [];
            $arrExisteGrupoTrabajo            = [];
            $arrExisteUsuarioAutorizadoEvento = [];

            ParametrosTipoDocumento::where('estado', 'ACTIVO')->get()
                ->groupBy('tdo_codigo')
                ->map(function ($doc) use (&$arrExisteTipoDocumento) {
                    $vigente = $this->validarVigenciaRegistroParametrica($doc);
                    if ($vigente['vigente']) {
                        $arrExisteTipoDocumento[$vigente['registro']->tdo_codigo] = $vigente['registro'];
                    }
                });

            foreach ($data as $fila => $columnas) {
                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrAutorizacionEventoDian = [];
                $arrFaltantes = $this->checkFields($Acolumnas, [
                    'nit_ofe',
                    'codigo_tipo_documento',
                    'identificacion',
                    'nombres',
                    'apellidos',
                ], $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {
                    // nit_ofe
                    $arrAutorizacionEventoDian['ofe_id'] = 0;
                    $arrAutorizacionEventoDian['nit_ofe'] = $columnas->nit_ofe;
                    if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)){
                        $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                    } else {
                        $objExisteOfe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $columnas->nit_ofe)
                            ->validarAsociacionBaseDatos()
                            ->where('estado', 'ACTIVO')
                            ->first();
                        $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                    }

                    if (empty($objExisteOfe)){
                        $arrErrores = $this->adicionarError($arrErrores, ["El Nit del Ofe [{$columnas->nit_ofe}] no existe o se encuentra INACTIVO."], $fila);
                    } else {
                        // nit_proveedor
                        $arrAutorizacionEventoDian['pro_id'] = 0;
                        if (array_key_exists($columnas->nit_proveedor, $arrExisteProveedor)){
                            $objExisteProveedor = $arrExisteProveedor[$columnas->nit_proveedor];
                        } else {
                            $objExisteProveedor = ConfiguracionProveedor::where('ofe_id', $objExisteOfe->ofe_id)
                                ->where('pro_identificacion', $columnas->nit_proveedor)
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $arrExisteProveedor[$columnas->nit_proveedor] = $objExisteProveedor;
                        }

                        if (isset($columnas->nit_proveedor) && !empty($columnas->nit_proveedor) && empty($objExisteProveedor)) {
                            $arrErrores = $this->adicionarError($arrErrores, ["El Nit del Proveedor [{$columnas->nit_proveedor}] no existe o se encuentra INACTIVO."], $fila);
                        }

                        // codigo_grupo_trabajo
                        $arrAutorizacionEventoDian['gtr_id'] = 0;
                        $codigoGrupoTrabajo = 'codigo_' . (isset($gruposTrabajo->singular) ? strtolower($this->sanear_string($gruposTrabajo->singular)) : 'grupo_trabajo');
                        if (array_key_exists($columnas->$codigoGrupoTrabajo, $arrExisteGrupoTrabajo)){
                            $objExisteGrupoTrabajo = $arrExisteGrupoTrabajo[$columnas->$codigoGrupoTrabajo];
                        } else {
                            $objExisteGrupoTrabajo = ConfiguracionGrupoTrabajo::where('estado', 'ACTIVO')->where('gtr_codigo', $columnas->$codigoGrupoTrabajo)
                                ->first();
                            $arrExisteGrupoTrabajo[$columnas->$codigoGrupoTrabajo] = $objExisteGrupoTrabajo;
                        }

                        if (isset($columnas->$codigoGrupoTrabajo) && !empty($columnas->$codigoGrupoTrabajo) && empty($objExisteGrupoTrabajo)){
                            $arrErrores = $this->adicionarError($arrErrores, ["El código para " . (isset($gruposTrabajo->singular) ? $gruposTrabajo->singular : 'GRUPO TRABAJO') . " [{$columnas->$codigoGrupoTrabajo}] no existe o se encuentra INACTIVO."], $fila);
                        }
                        
                        // email_usuario_openetl
                        $arrAutorizacionEventoDian['usu_id'] = 0;
                        $arrAutorizacionEventoDian['email_usuario_openetl'] = $columnas->email_usuario_openetl;
                        if (array_key_exists($columnas->email_usuario_openetl, $arrExisteUsuario)){
                            $objExisteUsuario = $arrExisteUsuario[$columnas->email_usuario_openetl];
                        } else {
                            $objExisteUsuario = User::where('estado', 'ACTIVO')->where('usu_email', $columnas->email_usuario_openetl)
                                ->first();
                            $arrExisteUsuario[$columnas->email_usuario_openetl] = $objExisteUsuario;
                        }

                        if (isset($columnas->email_usuario_openetl) && !empty($columnas->email_usuario_openetl) && empty($objExisteUsuario)){
                            $arrErrores = $this->adicionarError($arrErrores, ["El Usuario [{$columnas->email_usuario_openetl}] no existe o se encuentra INACTIVO."], $fila);
                        }

                        // Si el usuario autenticado tiene base de datos asignada
                        if(!empty($objUser->bdd_id_rg) && $objUser->bdd_id_rg != $objExisteUsuario->bdd_id_rg &&
                            !empty($objUser->bdd_id) && $objUser->bdd_id != $objExisteUsuario->bdd_id )
                        {
                            $arrErrores = $this->adicionarError($arrErrores, ['Usuario ' . $columnas->email_usuario_openetl . ' no válido.'], $fila);
                        }

                        // Si llega grupo de trabajo y usuario, el usuario debe pertenecen al mismo grupo de trabajo enviado
                        if($objExisteGrupoTrabajo && $objExisteUsuario) {
                            $relacionados = $this->usuarioGrupoRelacionados($objExisteGrupoTrabajo, $objExisteUsuario->usu_id, $columnas->email_usuario_openetl);
                            if(!empty($relacionados)) {
                                $arrErrores = $this->adicionarError($arrErrores, $relacionados, $fila);
                            }
                        }

                        $arrAutorizacionEventoDian['use_id'] = 0;
                        if (array_key_exists($columnas->nit_ofe.'-'.$columnas->nit_proveedor.'-'.$columnas->$codigoGrupoTrabajo.'-'.$columnas->email_usuario_openetl, $arrExisteUsuarioAutorizadoEvento)){
                            $objExisteUsuarioAutorizadoEvento = $arrExisteUsuarioAutorizadoEvento[$columnas->nit_ofe.'-'.$columnas->nit_proveedor.'-'.$columnas->$codigoGrupoTrabajo.'-'.$columnas->email_usuario_openetl];
                            $arrAutorizacionEventoDian['use_id'] = $objExisteUsuarioAutorizadoEvento->use_id;
                        } else {
                            $objExisteUsuarioAutorizadoEvento = $this->className::where('ofe_id', $objExisteOfe->ofe_id)
                                ->where('pro_id', $objExisteProveedor ? $objExisteProveedor->pro_id : null)
                                ->where('gtr_id', $objExisteGrupoTrabajo ? $objExisteGrupoTrabajo->gtr_id : null)
                                ->where('usu_id', $objExisteUsuario ? $objExisteUsuario->usu_id : null)
                                ->first();

                            if ($objExisteUsuarioAutorizadoEvento){
                                $arrExisteUsuarioAutorizadoEvento[$columnas->nit_ofe.'-'.$columnas->nit_proveedor.'-'.$columnas->$codigoGrupoTrabajo.'-'.$columnas->email_usuario_openetl] = $objExisteUsuarioAutorizadoEvento;
                                $arrAutorizacionEventoDian['use_id'] = $objExisteUsuarioAutorizadoEvento->use_id;
                            }
                        }

                        if (array_key_exists($columnas->codigo_tipo_documento, $arrExisteTipoDocumento)) {
                            $objExisteTipoDocumento = $arrExisteTipoDocumento[$columnas->codigo_tipo_documento];
                            $arrAutorizacionEventoDian['tdo_id'] = $objExisteTipoDocumento->tdo_id;
                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Código del Tipo de Documento [' . $columnas->codigo_tipo_documento . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                        }

                        if (property_exists($columnas, 'acuse_de_recibo') && isset($columnas->acuse_de_recibo) && !empty($columnas->acuse_de_recibo)) {
                            if ($columnas->acuse_de_recibo !== 'SI' && $columnas->acuse_de_recibo !== 'NO' && $columnas->acuse_de_recibo !== "") {
                                $arrErrores = $this->adicionarError($arrErrores, ['El campo acuse de recibo debe contener SI o NO como valor.'], $fila);
                            }
                        }

                        if (property_exists($columnas, 'reclamo') && isset($columnas->reclamo) && !empty($columnas->reclamo)) {
                            if ($columnas->reclamo !== 'SI' && $columnas->reclamo !== 'NO' && $columnas->reclamo !== "") {
                                $arrErrores = $this->adicionarError($arrErrores, ['El campo reclamo debe contener SI o NO como valor.'], $fila);
                            }
                        }

                        if (property_exists($columnas, 'recibo_bien') && isset($columnas->recibo_bien) && !empty($columnas->recibo_bien)) {
                            if ($columnas->recibo_bien !== 'SI' && $columnas->recibo_bien !== 'NO' && $columnas->recibo_bien !== "") {
                                $arrErrores = $this->adicionarError($arrErrores, ['El campo recibo bien debe contener SI o NO como valor.'], $fila);
                            }
                        }

                        if (property_exists($columnas, 'aceptacion_expresa') && isset($columnas->aceptacion_expresa) && !empty($columnas->aceptacion_expresa)) {
                            if ($columnas->aceptacion_expresa !== 'SI' && $columnas->aceptacion_expresa !== 'NO' && $columnas->aceptacion_expresa !== "") {
                                $arrErrores = $this->adicionarError($arrErrores, ['El campo aceptación expresa debe contener SI o NO como valor.'], $fila);
                            }
                        }

                        $existeUse = $this->className::select(['use_id'])
                            ->where('ofe_id', $objExisteOfe->ofe_id);

                        if(isset($objExisteProveedor->pro_id) && !empty($objExisteProveedor->pro_id))
                            $existeUse = $existeUse->where('pro_id', $objExisteProveedor->pro_id);
                        else
                            $existeUse = $existeUse->whereNull('pro_id');

                        if(isset($objExisteGrupoTrabajo->gtr_id) && !empty($objExisteGrupoTrabajo->gtr_id))
                            $existeUse = $existeUse->where('gtr_id', $objExisteGrupoTrabajo->gtr_id);
                        else
                            $existeUse = $existeUse->whereNull('gtr_id');

                        if(isset($objExisteUsuario->usu_id) && !empty($objExisteUsuario->usu_id))
                            $existeUse = $existeUse->where('usu_id', $objExisteUsuario->usu_id);
                        else
                            $existeUse = $existeUse->whereNull('usu_id');
                                
                        $existeUse = $existeUse->first();
                            
                        if (
                            (!isset($objExisteProveedor->pro_id) || (isset($objExisteProveedor->pro_id) && empty($objExisteProveedor->pro_id))) &&
                            (!isset($objExisteGrupoTrabajo->gtr_id) || (isset($objExisteGrupoTrabajo->gtr_id) && empty($objExisteGrupoTrabajo->gtr_id))) &&
                            (!isset($objExisteUsuario->usu_id) || (isset($objExisteUsuario->usu_id) && empty($objExisteUsuario->usu_id)))
                        ) {
                            $arrErrores = $this->adicionarError($arrErrores, ['Verifique que cualquiera de las columnas para Proveedor, ' . (isset($gruposTrabajo->singular) ? $gruposTrabajo->singular : 'GRUPO TRABAJO') . ' o Identificación tenga valor.'], $fila);
                        }
                        
                        $arrAutorizacionEventoDian['ofe_id']                 = $objExisteOfe->ofe_id;
                        $arrAutorizacionEventoDian['pro_id']                 = isset($objExisteProveedor->pro_id) && !empty($objExisteProveedor->pro_id) ? $objExisteProveedor->pro_id : null;
                        $arrAutorizacionEventoDian['gtr_id']                 = isset($objExisteGrupoTrabajo->gtr_id) && !empty($objExisteGrupoTrabajo->gtr_id) ? $objExisteGrupoTrabajo->gtr_id : null;
                        $arrAutorizacionEventoDian['usu_id']                 = isset($objExisteUsuario->usu_id) && !empty($objExisteUsuario->usu_id) ? $objExisteUsuario->usu_id : null;
                        $arrAutorizacionEventoDian['use_identificacion']     = $this->sanitizarStrings($columnas->identificacion);
                        $arrAutorizacionEventoDian['use_nombres']            = $this->sanitizarStrings($columnas->nombres);
                        $arrAutorizacionEventoDian['use_apellidos']          = $this->sanitizarStrings($columnas->apellidos);
                        $arrAutorizacionEventoDian['use_cargo']              = $this->sanitizarStrings($columnas->cargo);
                        $arrAutorizacionEventoDian['use_area']               = $this->sanitizarStrings($columnas->area);
                        $arrAutorizacionEventoDian['use_acuse_recibo']       = $this->sanitizarStrings($columnas->acuse_de_recibo);
                        $arrAutorizacionEventoDian['use_reclamo']            = $this->sanitizarStrings($columnas->reclamo);
                        $arrAutorizacionEventoDian['use_recibo_bien']        = $this->sanitizarStrings($columnas->recibo_bien);
                        $arrAutorizacionEventoDian['use_aceptacion_expresa'] = $this->sanitizarStrings($columnas->aceptacion_expresa);
                    }

                    if(count($arrErrores) == 0){
                        $objValidator = Validator::make($arrAutorizacionEventoDian, $this->className::$rules);
                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrAutorizacionEventoDian;
                        }
                    }
                }
                if ($fila % 500 === 0)
                    $this->renovarConexion($objUser);
            }

            if(!empty($arrErrores)){
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $objUser->usu_id,
                    'age_proceso'             => 'FINALIZADO',
                    'age_cantidad_documentos' => 0,
                    'age_prioridad'           => null,
                    'usuario_creacion'        => $objUser->usu_id,
                    'fecha_creacion'          => date('Y-m-d H:i:s'),
                    'estado'                  => 'ACTIVO',
                ]);

                EtlProcesamientoJson::create([
                    'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_AUTEVENTOSDIAN,
                    'pjj_json' => json_encode([]),
                    'pjj_procesado' => 'SI',
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'pjj_errores' => json_encode($arrErrores),
                    'age_id' => $agendamiento->age_id,
                    'usuario_creacion' => $objUser->usu_id,
                    'fecha_creacion' => date('Y-m-d H:i:s'),
                    'estado' => 'ACTIVO'
                ]);

                return response()->json([
                    'message' => 'Errores al guardar las Autorizaciones Eventos Dian',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            } else {
                $bloque_autorizaciones_eventos_dian = [];
                foreach ($arrResultado as $autorizacion_eventos_dian) {
                    $data = [
                        'use_id'                => $autorizacion_eventos_dian['use_id'],
                        'tdo_id'                => $autorizacion_eventos_dian['tdo_id'],
                        'use_identificacion'    => !empty($autorizacion_eventos_dian['use_identificacion']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_identificacion']) : null,
                        'use_nombres'           => !empty($autorizacion_eventos_dian['use_nombres']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_nombres']) : null,
                        'use_apellidos'         => !empty($autorizacion_eventos_dian['use_apellidos']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_apellidos']): null,
                        'use_cargo'             => !empty($autorizacion_eventos_dian['use_cargo']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_cargo']): null,
                        'use_area'              => !empty($autorizacion_eventos_dian['use_area']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_area']): null,
                        'use_acuse_recibo'      => !empty($autorizacion_eventos_dian['use_acuse_recibo']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_acuse_recibo']): null,
                        'use_recibo_bien'       => !empty($autorizacion_eventos_dian['use_recibo_bien']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_recibo_bien']): null,
                        'use_aceptacion_expresa'=> !empty($autorizacion_eventos_dian['use_aceptacion_expresa']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_aceptacion_expresa']): null,
                        'use_reclamo'           => !empty($autorizacion_eventos_dian['use_reclamo']) ? $this->sanitizarStrings($autorizacion_eventos_dian['use_reclamo']): null,
                    ];

                    if (array_key_exists('ofe_id', $autorizacion_eventos_dian) && $autorizacion_eventos_dian['ofe_id'] !== 0 && array_key_exists('usu_id', $autorizacion_eventos_dian) && $autorizacion_eventos_dian['usu_id'] !== 0) {
                        $data['ofe_id'] = $autorizacion_eventos_dian['ofe_id'];
                        $data['usu_id'] = $autorizacion_eventos_dian['usu_id'];
                    }

                    if (array_key_exists('pro_id', $autorizacion_eventos_dian))
                        $data['pro_id'] = $autorizacion_eventos_dian['pro_id'] == 0 ? null : $autorizacion_eventos_dian['pro_id'];

                    if (array_key_exists('gtr_id', $autorizacion_eventos_dian))
                        $data['gtr_id'] = $autorizacion_eventos_dian['gtr_id'] == 0 ? null : $autorizacion_eventos_dian['gtr_id'];

                    array_push($bloque_autorizaciones_eventos_dian, $data);
                }

                if (!empty($bloque_autorizaciones_eventos_dian)) {
                    $bloques = array_chunk($bloque_autorizaciones_eventos_dian, 100);
                    foreach ($bloques as $bloque) {

                        $agendamiento = AdoAgendamiento::create([
                            'usu_id'                  => $objUser->usu_id,
                            'age_proceso'             => ProcesarCargaParametricaCommand::$NOMBRE_COMANDO,
                            'age_cantidad_documentos' => count($bloque),
                            'age_prioridad'           => null,
                            'usuario_creacion'        => $objUser->usu_id,
                            'fecha_creacion'          => date('Y-m-d H:i:s'),
                            'estado'                  => 'ACTIVO',
                        ]);
                        
                        if ($agendamiento) {
                            EtlProcesamientoJson::create([
                                'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_AUTEVENTOSDIAN,
                                'pjj_json' => json_encode($bloque),
                                'pjj_procesado' => 'NO',
                                'age_id' => $agendamiento->age_id,
                                'usuario_creacion' => $objUser->usu_id,
                                'fecha_creacion' => date('Y-m-d H:i:s'),
                                'estado' => 'ACTIVO'
                            ]);
                        }
                    }
                }
                
                return response()->json([
                    'success' => true
                ], 200);                    
            }
        } else {
            return response()->json([
                'message' => 'Errores al guardar las Autorizaciones Eventos Dian',
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de usuarios eventos.
     * 
     * @return Response
     */
    public function getListaErroresAutorizacionesEventosDian(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_AUTEVENTOSDIAN);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de usuarios eventos.
     *
     * @return Response
     */
    public function descargarListaErroresAutorizacionesEventosDian(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_AUTEVENTOSDIAN, true, 'carga_autorizaciones_eventos_dian_log_errores');
    }

    /**
     * Toma los errores generados y los mezcla en un solo arreglo para dar respuesta al usuario.
     *
     * @param Array $arrErrores
     * @param Array $objValidator
     * @return void
     */
    private function adicionarErrorLocal($arrErrores, $objValidator){
        foreach($objValidator as $error){
            array_push($arrErrores, $error);
        }
        
        return $arrErrores;
    }

    /**
     * Efectua un proceso de busqueda en la parametrica usuarios eventos.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function busqueda(Request $request) {
        $columnas = [
            'use_id',
            'ofe_id',
            'usu_id',
            'tdo_id',
            'use_identificacion',
            'use_nombres',
            'use_apellidos',
            'use_cargo',
            'use_area',
            'use_acuse_recibo',
            'use_recibo_bien',
            'use_aceptacion_expresa',
            'use_reclamo',
            'estado'
        ];
        $incluir = ['getAmbienteDestino'];

        return $this->procesadorBusqueda($request, $columnas, $incluir);
    }
}
