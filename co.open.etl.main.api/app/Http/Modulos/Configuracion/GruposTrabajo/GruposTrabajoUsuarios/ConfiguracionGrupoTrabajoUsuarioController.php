<?php

namespace App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios;

use Validator;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use openEtl\Tenant\Traits\TenantTrait;
use App\Http\Controllers\OpenTenantController;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuario;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionGrupoTrabajoUsuarioController extends OpenTenantController {

    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = '';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = '';

    /**
     * Modelo relacionado a la paramétrica.
     *
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ConfiguracionGrupoTrabajoUsuario::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = '';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = '';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = '';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = '';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = '';

    /**
     * Propiedad para almacenar los errores.
     *
     * @var Array
     */
    protected $errors = [];

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = '';

    /**
     * Nombre del archivo Excel.
     * 
     * @var String
     */
    public $nombreArchivo = 'usuarios_asociados';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $nombreCampoIdentificacion = null;

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
     * Indica si alguno de los Ofes del sistema aplica para el proyecto de FNC.
     * 
     * @var Bool
     */
    public $ofeAplicaFNC = false;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarUsuario,ConfiguracionGrupoTrabajoAsociarUsuarioEditarUsuariosAsociados,ConfiguracionGrupoTrabajoAsociarUsuarioNuevo,ConfiguracionGrupoTrabajoAsociarUsuarioCambiarEstado,ConfiguracionGrupoTrabajoAsociarUsuarioSubir,ConfiguracionGrupoTrabajoAsociarUsuarioDescargarExcel,ConfiguracionGrupoTrabajoAsociarUsuarioVerUsuariosAsociados'
        ])->except([
            'store',
            'consultarGrupoUsuarioAsociado',
            'actualizarGrupoUsuarioAsociado',
            'cambiarEstado',
            'listarUsuariosAsociados',
            'generarInterfaceGruposTrabajoUsuarios',
            'cargarGruposTrabajoUsuarios',
            'getListaErroresGruposTrabajoUsuarios',
            'descargarListaErroresGruposTrabajoUsuarios',
            'searchUsuariosValidador'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarUsuarioNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarUsuarioCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarUsuarioVerUsuariosAsociados'
        ])->only([
            'listarUsuariosAsociados'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarUsuarioSubir'
        ])->only([
            'generarInterfaceGruposTrabajoUsuarios',
            'cargarGruposTrabajoUsuarios',
            'getListaErroresGruposTrabajoUsuarios',
            'descargarListaErroresGruposTrabajoUsuarios'
        ]);

        $user = auth()->user();
        if ($user) {
            // Se obtiene el valor de la variable del sistema para identificar el nombre del grupo
            TenantTrait::GetVariablesSistemaTenant();
            $variableSistema    = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
            $arrVariableSistema = json_decode($variableSistema, true);

            $this->nombre                  = ucwords($arrVariableSistema['singular']);
            $this->nombrePlural            = ucwords($arrVariableSistema['plural']);
            $this->mensajeErrorCreacion422 = 'No Fue Posible Asociar el Usuario a ' . $arrVariableSistema['singular'];
            $this->mensajeErroresCreacion  = 'Errores al Asociar el Usuario a ' . $arrVariableSistema['singular'];
            $this->mensajeObjectNotFound   = 'El Id del Usuario y ' . $arrVariableSistema['singular'] . ' [%s] no Existe';
            $this->mensajeObjectDisabled   = 'El Id del Usuario y ' . $arrVariableSistema['singular'] . ' [%s] Esta Inactivo';

            // Se almacenan las columnas que se generan en la interfaz de Excel
            $this->columnasExcel = [
                'NIT OFE',
                'CODIGO ' . strtoupper($this->nombre),
                'EMAIL',
                'ESTADO'
            ];

            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo'])
                ->where('ofe_recepcion_fnc_activo', 'SI')
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if ($ofe) {
                $this->ofeAplicaFNC = true;
                array_splice($this->columnasExcel, 3, 0, ['USUARIO GESTOR', 'USUARIO VALIDADOR']);
            }
        }
    }

    /**
     * Configura las reglas para poder asociar un usuario a un grupo de trabajo.
     *
     * @return mixed
     */
    private function getRules() {
        $rules = array_merge($this->className::$rules);

        unset(
            $rules['usu_id'],
            $rules['gtr_id']
        );

        $rules['ofe_identificacion'] = 'required|string|max:20';
        $rules['usu_identificacion'] = 'required|string|max:20';
        $rules['gtr_codigo']         = 'required|array';

        return $rules;
    }

    /**
     * Devuelve una lista paginada de registros.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response
     * @throws \Exception
     */
    public function getListaGruposTrabajoUsuarios(Request $request) {
        $user = auth()->user();

        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'etl_grupos_trabajo_usuarios.gtu_id',
            'etl_grupos_trabajo_usuarios.gtr_id',
            'etl_grupos_trabajo_usuarios.usu_id',
            'etl_grupos_trabajo_usuarios.gtu_usuario_gestor',
            'etl_grupos_trabajo_usuarios.gtu_usuario_validador',
            'etl_grupos_trabajo_usuarios.usuario_creacion',
            'etl_grupos_trabajo_usuarios.fecha_creacion',
            'etl_grupos_trabajo_usuarios.fecha_modificacion',
            'etl_grupos_trabajo_usuarios.estado',
            'etl_grupos_trabajo_usuarios.fecha_actualizacion'
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getUsuario:usu_id,usu_email,usu_identificacion,usu_nombre',
            'getGrupoTrabajo.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,ofe_recepcion_fnc_activo',
            'getGrupoTrabajo:gtr_id,ofe_id,gtr_codigo,gtr_nombre,gtr_correos_notificacion'
        ];

        $whereHasConditions = [];
        if(!empty($user->bdd_id_rg)) {
            $whereHasConditions[] = [
                'relation' => 'getGrupoTrabajo.getConfiguracionObligadoFacturarElectronicamente',
                'function' => function($query) use ($user) {
                    $query->where('bdd_id_rg', $user->bdd_id_rg);
                }
            ];
        } else {
            $whereHasConditions[] = [
                'relation' => 'getGrupoTrabajo.getConfiguracionObligadoFacturarElectronicamente',
                'function' => function($query) {
                    $query->whereNull('bdd_id_rg');
                }
            ];
        }

        $exportacion = [
            'columnas' => [
                'ofe_identificacion' => [
                    'label' => 'NIT OFE',
                    'inner_relation' => true,
                    'origin_relation' => ['name' => 'getGrupoTrabajo'],
                    'destiny_relation' => ['name' => 'getConfiguracionObligadoFacturarElectronicamente', 'field' => 'ofe_identificacion']
                ],
                'gtr_id' => [
                    'multiple' => true,
                    'relation' => 'getGrupoTrabajo',
                    'fields' => [
                        [
                            'label' => 'CODIGO ' . strtoupper($this->nombre),
                            'field' => 'gtr_codigo'
                        ],
                        [
                            'label' => 'NOMBRE ' . strtoupper($this->nombre),
                            'field' => 'gtr_nombre'
                        ]
                    ]
                ],
                'usu_id' => [
                    'multiple' => true,
                    'relation' => 'getUsuario',
                    'fields' => [
                        [
                            'label' => 'EMAIL',
                            'field' => 'usu_email'
                        ],
                        [
                            'label' => 'IDENTIFICACION USUARIO',
                            'field' => 'usu_identificacion'
                        ],
                        [
                            'label' => 'NOMBRES',
                            'field' => 'usu_nombre'
                        ]
                    ]
                ],
                'gtu_usuario_gestor' => 'Usuario Gestor',
                'gtu_usuario_validador' => 'Usuario Validador',
                'usuario_creacion' => [
                    'label' => 'Usuario Creacion',
                    'relation' => ['name' => 'getUsuarioCreacion', 'field' => 'usu_nombre']
                ],
                'fecha_creacion' =>  [
                    'label' => 'Fecha Creación',
                    'type' => self::TYPE_CARBON
                ],
                'fecha_modificacion' =>  [
                    'label' => 'Fecha Modificación',
                    'type' => self::TYPE_CARBON
                ],
                'estado' => 'Estado'
            ],
            'titulo' => $this->nombreArchivo
        ];

        // Si no existen Ofes que apliquen para el proyecto de FNC no se pintan los campos de tipo de usuario
        if (!$this->ofeAplicaFNC) {
            unset(
                $exportacion['columnas']['gtu_usuario_gestor'],
                $exportacion['columnas']['gtu_usuario_validador']
            );
        }

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Permite asociar un usuario a un grupo de trabajo.
     *
     * @param Request $request Parámetros de la petición
     * @return void
     */
    public function store(Request $request) {
        $this->errors  = [];
        $procesarDatos = [];
        $validador     = Validator::make($request->all(), $this->getRules());
        if (!$validador->fails()) {
            // Se realiza la validación de los datos
            $respuesta = $this->validarDatos($request->all(), false);

            if (!empty($respuesta)) {
                foreach ($request->gtr_codigo as $key => $value) {
                    // Valida que el grupo de trabajo exista para el Ofe seleccionado
                    $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id', 'gtr_nombre', 'gtr_correos_notificacion'])
                        ->where('ofe_id', $respuesta['ofe_id'])
                        ->where('gtr_codigo', $value)
                        ->where('estado', 'ACTIVO')
                        ->first();
                
                    if (!$grupoTrabajo) {
                        $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con código [{$value}] no existe para el OFE con identificación [{$request->ofe_identificacion}] o se encuentra en estado INACTIVO."]);
                    } else {
                        $existe = $this->className::where('gtr_id', $grupoTrabajo->gtr_id)
                            ->where('usu_id', $respuesta['usu_id'])
                            ->first();

                        if (!$existe) {
                            $respuesta['gtr_id'] = $grupoTrabajo->gtr_id;
                            $procesarDatos[$key] = $respuesta;
                        } else {
                            $this->errors = $this->adicionarError($this->errors, ["El Usuario con identificación [{$request->usu_identificacion}] ya existe asociado a {$this->nombre} con código [{$value}]."]);
                        }
                    } 
                }
            }

            if (empty($this->errors) && !empty($procesarDatos)) {
                foreach ($procesarDatos as $key => $value) {
                    $existe = $this->className::where('gtr_id', $value['gtr_id'])
                        ->where('usu_id', $value['usu_id'])
                        ->first();

                    if (!$existe) {
                        $data['gtr_id']                = $value['gtr_id'];
                        $data['usu_id']                = $value['usu_id'];
                        $data['gtu_usuario_gestor']    = (array_key_exists('gtu_usuario_gestor', $value) ? $value['gtu_usuario_gestor'] : NULL);
                        $data['gtu_usuario_validador'] = (array_key_exists('gtu_usuario_validador', $value) ? $value['gtu_usuario_validador'] : NULL);
                        $data['estado']                = 'ACTIVO';
                        $data['usuario_creacion']      = auth()->user()->usu_id;
                        $obj = $this->className::create($data);
                    }
                }

                return response()->json([
                    'success' => true,
                ], 200);
            }

            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors'  => $this->errors
            ], 422);
        }

        return response()->json([
            'message' => $this->mensajeErroresCreacion,
            'errors'  => $validador->errors()->all()
        ], 400);
    }

    /**
     * Genera una interfaz para asociar los usuarios a los grupos de trabajo mediante un Excel.
     *
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceGruposTrabajoUsuarios() {
        return $this->generarInterfaceToTenant($this->columnasExcel, $this->nombreArchivo);  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de grupos de trabajo usuarios.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response
     * @throws \Exception
     */
    public function cargarGruposTrabajoUsuarios(Request $request){
        set_time_limit(0);
        ini_set('memory_limit','512M');
        $objUser = auth()->user();

        if($request->hasFile('archivo')){
            $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
            if (
                (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
                !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
            )
                return response()->json([
                    'message' => 'Errores al asociar los usuarios a un(a) ' . $this->nombre,
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
                        $columna['nombre_'.strtolower($this->sanear_string(str_replace(' ', '_', $this->nombre)))],
                        $columna['identificacion_usuario'],
                        $columna['nombres'],
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
                    'message' => 'Errores al asociar los usuarios a un(a) ' . $this->nombre,
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $codigoGrupo   = "codigo_" . $this->sanear_string(strtolower($this->nombre));
            $arrErrores    = [];
            $arrResultado  = [];
            $arrExisteOfe  = [];
            $arrExisteUsuario = [];
            $arrExisteGrupoTrabajo = [];
            $arrGrupoTrabajoUsuarioProceso = [];

            foreach ($data as $fila => $columnas) {
                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrGrupoTrabajoUsuario = [];
                $arrFaltantes = $this->checkFields($Acolumnas, [
                    'nit_ofe',
                    $codigoGrupo,
                    'email'
                ], $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {
                    $arrGrupoTrabajoUsuario['gtu_id'] = 0;

                    if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)){
                        $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                    } else {
                        $objExisteOfe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $columnas->nit_ofe)
                            ->validarAsociacionBaseDatos()
                            ->where('estado', 'ACTIVO')
                            ->first();

                        if ($objExisteOfe) {
                            $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                        }
                    }

                    if (!empty($objExisteOfe)) {
                        $arrGrupoTrabajoUsuario['usu_id'] = null;
                        if (array_key_exists($columnas->email, $arrExisteUsuario)){
                            $objExisteUsuario = $arrExisteUsuario[$columnas->email];
                        } else {
                            $objExisteUsuario = User::where('usu_email', $columnas->email)
                                ->where('estado', 'ACTIVO')
                                ->first();

                            if ($objExisteUsuario) {
                                $arrExisteUsuario[$columnas->email] = $objExisteUsuario;
                            }
                        }

                        if (empty($objExisteUsuario)) {
                            $arrErrores = $this->adicionarError($arrErrores, ["El usuario con email {$columnas->email} no existe o encuentra INACTIVO."], $fila);
                        } {
                            if (!empty($objExisteUsuario->bdd_id_rg) && $objExisteUsuario->bdd_id_rg != $objExisteOfe->bdd_id_rg) {
                                $arrErrores = $this->adicionarError($arrErrores, ["El usuario con email {$columnas->email} pertenece a una base de datos diferente a la del OFE seleccionado."], $fila);
                            }
                        }

                        $arrGrupoTrabajoUsuario['gtr_id'] = null;
                        if (array_key_exists($columnas->$codigoGrupo, $arrExisteGrupoTrabajo)){
                            $objExisteGrupoTrabajo = $arrExisteGrupoTrabajo[$columnas->$codigoGrupo];
                        } else {
                            $objExisteGrupoTrabajo = ConfiguracionGrupoTrabajo::where('estado', 'ACTIVO')
                                ->where('gtr_codigo', $columnas->$codigoGrupo)
                                ->where('ofe_id', $objExisteOfe->ofe_id)
                                ->first();

                            if ($objExisteGrupoTrabajo) {
                                $arrExisteGrupoTrabajo[$columnas->$codigoGrupo] = $objExisteGrupoTrabajo;
                            }
                        }

                        if (empty($objExisteGrupoTrabajo)) {
                            $arrErrores = $this->adicionarError($arrErrores, ["{$this->nombre} con código {$columnas->$codigoGrupo} no existe para el OFE {$columnas->nit_ofe} o se encuentra INACTIVO."], $fila);
                        }

                        $arrGrupoTrabajoUsuario['gtu_usuario_gestor']    = null;
                        $arrGrupoTrabajoUsuario['gtu_usuario_validador'] = null;
                        if ($objExisteOfe->ofe_recepcion_fnc_activo == 'SI') {
                            if ((!isset($columnas->usuario_gestor) || empty($columnas->usuario_gestor)) && (!isset($columnas->usuario_validador) || empty($columnas->usuario_validador)))
                                $arrErrores = $this->adicionarError($arrErrores, ["Debe seleccionar el tipo de usuario, Usuario Gestor y/o Usuario Validador."], $fila);
                            elseif (isset($columnas->usuario_gestor) && !empty($columnas->usuario_gestor) && $columnas->usuario_gestor != 'SI' && $columnas->usuario_gestor != 'NO')
                                $arrErrores = $this->adicionarError($arrErrores, ["El campo usuario gestor debe contener SI o NO como valor."], $fila);
                            elseif (isset($columnas->usuario_validador) && !empty($columnas->usuario_validador) && $columnas->usuario_validador != 'SI' && $columnas->usuario_validador != 'NO')
                                $arrErrores = $this->adicionarError($arrErrores, ["El campo usuario validador debe contener SI o NO como valor."], $fila);
                        
                            $arrGrupoTrabajoUsuario['gtu_usuario_gestor']    = isset($columnas->usuario_gestor) && !empty($columnas->usuario_gestor) ? $columnas->usuario_gestor : null;
                            $arrGrupoTrabajoUsuario['gtu_usuario_validador'] = isset($columnas->usuario_validador) && !empty($columnas->usuario_validador) ? $columnas->usuario_validador : null;
                        }

                        if (!empty($objExisteUsuario) && !empty($objExisteGrupoTrabajo)) {
                            $arrGrupoTrabajoUsuario['usu_id'] = $objExisteUsuario->usu_id;
                            $arrGrupoTrabajoUsuario['gtr_id'] = $objExisteGrupoTrabajo->gtr_id;

                            $objExisteGrupoTrabajoUsuario = $this->className::where('gtr_id', $objExisteGrupoTrabajo->gtr_id)
                                ->where('usu_id', $objExisteUsuario->usu_id)
                                ->first();

                            if (!empty($objExisteGrupoTrabajoUsuario)) {
                                $arrGrupoTrabajoUsuario['gtu_id'] = $objExisteGrupoTrabajoUsuario->gtu_id;
                            }
                        }
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ["El OFE con identificación {$columnas->nit_ofe} no existe o se encuentra INACTIVO."], $fila);
                    }

                    $llaveGrupoTrabajo = $columnas->nit_ofe . '|' . $columnas->$codigoGrupo . '|' . $columnas->email;

                    if (array_key_exists($llaveGrupoTrabajo, $arrGrupoTrabajoUsuarioProceso)) {
                        $arrErrores = $this->adicionarError($arrErrores, ["El usuario con email {$columnas->email} asociado a {$this->nombre} con código {$columnas->$codigoGrupo} ya existe en otras filas."], $fila);
                    } else {
                        $arrGrupoTrabajoUsuarioProceso[$llaveGrupoTrabajo] = true;
                    }

                    $arrGrupoTrabajoUsuario['estado'] = (isset($columnas->estado) && $columnas->estado != '') ? $this->sanitizarStrings($columnas->estado) : 'ACTIVO';
                    if(count($arrErrores) == 0){
                        $reglas = $this->className::$rules;
                        $objValidator = Validator::make($arrGrupoTrabajoUsuario, $reglas);

                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrGrupoTrabajoUsuario;
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
                    'estado'                  => 'ACTIVO'
                ]);

                EtlProcesamientoJson::create([
                    'pjj_tipo'                => ProcesarCargaParametricaCommand::$TYPE_ASOCIARUSUARIO,
                    'pjj_json'                => json_encode([]),
                    'pjj_procesado'           => 'SI',
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'pjj_errores'             => json_encode($arrErrores),
                    'age_id'                  => $agendamiento->age_id,
                    'usuario_creacion'        => $objUser->usu_id,
                    'fecha_creacion'          => date('Y-m-d H:i:s'),
                    'estado'                  => 'ACTIVO'
                ]);

                return response()->json([
                    'message' => 'Errores al asociar los usuarios a un(a) ' . $this->nombre,
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            } else {
                $bloque_grupo_trabajo_usuario = [];
                foreach ($arrResultado as $grupo_trabajo_usuario) {
                    $data = [
                        "gtu_id"                => $grupo_trabajo_usuario['gtu_id'],
                        "gtr_id"                => $grupo_trabajo_usuario['gtr_id'],
                        "usu_id"                => $grupo_trabajo_usuario['usu_id'],
                        "gtu_usuario_gestor"    => $grupo_trabajo_usuario['gtu_usuario_gestor'],
                        "gtu_usuario_validador" => $grupo_trabajo_usuario['gtu_usuario_validador'],
                        "estado"                => $grupo_trabajo_usuario['estado']
                    ];

                    array_push($bloque_grupo_trabajo_usuario, $data);
                }

                if (!empty($bloque_grupo_trabajo_usuario)) {
                    $bloques = array_chunk($bloque_grupo_trabajo_usuario, 100);
                    foreach ($bloques as $bloque) {
                        $agendamiento = AdoAgendamiento::create([
                            'usu_id'                  => $objUser->usu_id,
                            'age_proceso'             => ProcesarCargaParametricaCommand::$NOMBRE_COMANDO,
                            'age_cantidad_documentos' => count($bloque),
                            'age_prioridad'           => null,
                            'usuario_creacion'        => $objUser->usu_id,
                            'fecha_creacion'          => date('Y-m-d H:i:s'),
                            'estado'                  => 'ACTIVO'
                        ]);
                        
                        if ($agendamiento) {
                            EtlProcesamientoJson::create([
                                'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_ASOCIARUSUARIO,
                                'pjj_json'         => json_encode($bloque),
                                'pjj_procesado'    => 'NO',
                                'age_id'           => $agendamiento->age_id,
                                'usuario_creacion' => $objUser->usu_id,
                                'fecha_creacion'   => date('Y-m-d H:i:s'),
                                'estado'           => 'ACTIVO'
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
                'message' => 'Errores al asociar los usuarios a un(a) ' . $this->nombre,
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Permite realizar las validaciones correspondientes sobre la data enviada en la petición de asociar usuarios y cambiar estado.
     *
     * @param  Array   $data Información que llega en el requests
     * @param  Boolean $cambiarEstado Identifica si llega por la acción de cambiar estado
     * @return Array Con el id del grupo de trabajo, usuario y ofe 
     */
    private function validarDatos(Array $data, bool $cambiarEstado) {
        $returnData = [];
        // Obtiene la información del usuario autenticado
        $this->user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_recepcion_fnc_activo', 'bdd_id_rg'])
            ->where('ofe_identificacion', $data['ofe_identificacion'])
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if ($ofe) {
            // Valida que el usuario exista y que pertenezca a la misma BD del OFE
            $usuario = User::select('usu_id', 'usu_identificacion', 'bdd_id_rg')
                ->where('usu_identificacion', $data['usu_identificacion'])
                ->where('usu_email', $data['usu_email']);
                if (!empty($ofe->bdd_id_rg)) {
                    $usuario = $usuario->where('bdd_id_rg', $ofe->bdd_id_rg);
                } else {
                    $usuario = $usuario->whereNull('bdd_id_rg');
                }

            $usuario = $usuario->where('bdd_id_rg', $ofe->bdd_id_rg)
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$usuario) {
                $this->errors = $this->adicionarError($this->errors, ["El Usuario con identificación [{$data['usu_identificacion']}] no existe, se encuentra en estado INACTIVO o pertenece a una base de datos diferente a la del OFE seleccionado."]);
            }

            if ($cambiarEstado) {
                // Valida que el grupo de trabajo exista para el Ofe seleccionado
                $grupoTrabajo = ConfiguracionGrupoTrabajo::select('gtr_id')
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('gtr_codigo', $data['gtr_codigo'])
                    ->where('estado', 'ACTIVO')
                    ->first();

                if (!$grupoTrabajo) {
                    $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con código {$data['gtr_codigo']} no existe para el OFE con identificación {$data['ofe_identificacion']} o se encuentra en estado INACTIVO."]);
                }
            } else {
                if ($ofe->ofe_recepcion_fnc_activo == 'SI') {
                    if ((!array_key_exists('gtu_usuario_gestor', $data) || empty($data['gtu_usuario_gestor'])) && (!array_key_exists('gtu_usuario_validador', $data) || empty($data['gtu_usuario_validador'])))
                        $this->errors = $this->adicionarError($this->errors, ["Debe seleccionar el tipo de usuario, Usuario Gestor y/o Usuario Validador."]);
                    elseif (array_key_exists('gtu_usuario_gestor', $data) && !empty($data['gtu_usuario_gestor']) && $data['gtu_usuario_gestor'] != 'SI' && $data['gtu_usuario_gestor'] != 'NO')
                        $this->errors = $this->adicionarError($this->errors, ["El campo usuario gestor debe contener SI o NO como valor."]);
                    elseif (array_key_exists('gtu_usuario_validador', $data) && !empty($data['gtu_usuario_validador']) && $data['gtu_usuario_validador'] != 'SI' && $data['gtu_usuario_validador'] != 'NO')
                        $this->errors = $this->adicionarError($this->errors, ["El campo usuario validador debe contener SI o NO como valor."]);
                
                    if (empty($this->errors)) {
                        $returnData['gtu_usuario_gestor']    = array_key_exists('gtu_usuario_gestor', $data) && !empty($data['gtu_usuario_gestor']) ? $data['gtu_usuario_gestor'] : null;
                        $returnData['gtu_usuario_validador'] = array_key_exists('gtu_usuario_validador', $data) && !empty($data['gtu_usuario_validador']) ? $data['gtu_usuario_validador'] : null;
                    }
                }
            }
        } else {
            $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación {$data['ofe_identificacion']} no existe o se encuentra en estado INACTIVO."]);
        }

        if (empty($this->errors)) {
            $returnData['ofe_id'] = $ofe->ofe_id;
            $returnData['usu_id'] = $usuario->usu_id;
            if ($cambiarEstado) 
                $returnData['gtr_id'] = $grupoTrabajo->gtr_id;
        }

        return $returnData;
    }

    /**
     * Cambia el estado de los usuarios asociados a los grupos de trabajo.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response
     */
    public function cambiarEstado(Request $request) {
        $this->errors = [];

        foreach ($request->all() as $registro) {
            if(!is_array($registro)) continue;

            // Se realiza la validación de los datos
            $respuesta = $this->validarDatos($registro, true);

            if (!empty($respuesta)) {
                $modelo = $this->className::where('gtr_id', $respuesta['gtr_id'])
                    ->where('usu_id', $respuesta['usu_id'])
                    ->first();

                if ($modelo) {
                    $strEstado = ($modelo->estado == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                    $modelo->update(['estado' => $strEstado]);

                    if(!$modelo)
                        $this->errors = $this->adicionarError($this->errors, ["No fue posible actualizar el usuario con identificación [{$registro['usu_identificacion']}] asociado a {$this->nombre} con código [{$registro['gtr_codigo']}]."]);
                } else {
                    $this->errors = $this->adicionarError($this->errors, ["El Usuario con identificación [{$registro['usu_identificacion']}] asociado a {$this->nombre} con código [{$registro['gtr_codigo']}] no existe."]);
                }
            }
        }

        if (!empty($this->errors)) {
            return response()->json([
                'message' => 'Error al cambiar el estado',
                'errors' => $this->errors
            ], 422);
        } else {
            return response()->json([
                'message' => 'Se cambio el estado exitosamente.',
            ], 201);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de grupos de trabajo usuarios.
     * 
     * @return Response
     */
    public function getListaErroresGruposTrabajoUsuarios() {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_ASOCIARUSUARIO);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de grupos de trabajo usuarios.
     *
     * @return Response
     */
    public function descargarListaErroresGruposTrabajoUsuarios() {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_ASOCIARUSUARIO, true, 'carga_asociar_usuarios_log_errores');
    }

    /**
     * Retorna la lista de los usuarios asociados a un grupo de trabajo específico.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response
     */
    public function listarUsuariosAsociados(Request $request) {
        // Valida que el grupo de trabajo exista para el Ofe en proceso
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
            ->where('ofe_identificacion', $request->nitOfe)
            ->validarAsociacionBaseDatos()
            ->first();

        if (!$ofe) {
            return response()->json([
                'message' => "Error al listar los usuarios asociados",
                'errors'  => ["El Ofe con identificación [{$request->nitOfe}] no existe"]
            ], 422);
        }

        // Valida que el grupo de trabajo exista para el Ofe en proceso
        $grupoTrabajo = ConfiguracionGrupoTrabajo::select('gtr_id')
            ->where('ofe_id', $ofe->ofe_id)
            ->where('gtr_codigo', $request->codigoGrupo)
            ->first();

        if (!$grupoTrabajo) {
            return response()->json([
                'message' => "Error al listar los usuarios asociados",
                'errors'  => ["{$this->nombre} con código [{$request->codigoGrupo}] no existe para el OFE con identificación [{$request->nitOfe}]."]
            ], 422);
        }

        $totalUsuariosAsociados = $this->className::where('gtr_id', $grupoTrabajo->gtr_id)->count();
        $length = isset($request->length) ? $request->length : 10;
        $start = isset($request->start) ? $request->start : 0;

        $buscador = $this->className::select([
                'etl_grupos_trabajo_usuarios.gtu_id',
                'etl_grupos_trabajo_usuarios.gtr_id',
                'etl_grupos_trabajo_usuarios.usu_id'
            ])
            ->with('getUsuario')
            ->where('gtr_id', $grupoTrabajo->gtr_id);

        if (isset($request->buscar) && !empty(trim($request->buscar))) {
            $buscador->where(function($query) use ($request){
                $query->with('getUsuario')
                ->wherehas('getUsuario', function ($query) use ($request) {
                    $query->where('usu_identificacion', 'like', '%'.$request->buscar.'%')
                    ->orWhere('usu_nombre', 'like', '%'.$request->buscar.'%')
                    ->orWhere('usu_email', 'like', '%'.$request->buscar.'%')
                    ->orWhere('estado', 'like', '%'.$request->buscar.'%');
                });
            });
        }

        $buscador->OrderByColumn($request->columnaOrden, $request->ordenDireccion);
        $filtrados = $buscador->count();
        if ($length !== -1 && $length !== '-1')
            $buscador->skip($start)->take($length);
        $registros = $buscador->get();

        return response()->json([
            'data'      => $registros,
            'filtrados' => $filtrados,
            'total'     => $totalUsuariosAsociados
        ], 200);
    }

    /**
     * Obtiene un grupo de trabajo especificado por la identificación del Usuario al que está asociado y el código del grupo.
     *
     * @param string $gtr_codigo Código del grupo de trabajo
     * @param string $ofe_identificacion Identificacion del usuario
     * @param string $usu_email Email del usuario
     * @return JsonResponse
     */
    public function consultarGrupoUsuarioAsociado(string $gtr_codigo, string $ofe_identificacion, string $usu_email): JsonResponse {
        $arrRelaciones = [
            'getUsuario:usu_id,usu_email,usu_identificacion,usu_nombre',
            'getGrupoTrabajo.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,ofe_recepcion_fnc_activo',
            'getGrupoTrabajo:gtr_id,ofe_id,gtr_codigo,gtr_nombre,gtr_correos_notificacion'
        ];

        $objetoModel = $this->consultarRegistroGrupoTrabajoUsuario($gtr_codigo, $ofe_identificacion, $usu_email);

        if (empty($objetoModel['error'])) {
            $objetoModel = $objetoModel['model']->with($arrRelaciones)->first();
    
            if($objetoModel) {
                $arrUsuarioAsociado = $objetoModel->toArray();
    
                return response()->json([
                    'data' => $arrUsuarioAsociado
                ], 200);
            }
        }

        return response()->json([
            'errors'  => [sprintf("{$this->nombre} con código [%s], para el OFE con identificación [%s] no existe o se encuentra en estado INACTIVO.", $gtr_codigo, $ofe_identificacion,)],
            'message' => 'Error al obtener la información'
        ], 422);
    }

    /**
     * Obtiene un grupo de trabajo especificado por la identificación del Usuario al que está asociado y el código del grupo para actualizarlo.
     *
     * @param  Request $request Parámetros de la petición
     * @param string   $gtr_codigo Código del grupo de trabajo
     * @param string   $ofe_identificacion Identificación del usuario
     * @param string   $usu_email Email del usuario
     * @return Response
     */
    public function actualizarGrupoUsuarioAsociado(Request $request, string $gtr_codigo, string $ofe_identificacion, string $usu_email) {
        $this->errors = [];
        $data = [];

        $validadorRequest = Validator::make($request->all(), $this->getRules());
        $validadorParams  = Validator::make([
            'gtr_codigo' => $gtr_codigo,
            'ofe_identificacion' => $ofe_identificacion,
            'usu_email' => $usu_email
        ], [
            'gtr_codigo' => 'required|string',
            'ofe_identificacion' => 'required|string',
            'usu_email' => 'required|string'
        ]);

        if (!$validadorRequest->fails() && !$validadorParams->fails()) {
            $objetoModel = $this->consultarRegistroGrupoTrabajoUsuario($gtr_codigo, $ofe_identificacion, $usu_email);
            if (empty($objetoModel['error'])) {
                $objetoModel = $objetoModel['model']->first();
            } else {
                return response()->json([
                    'message' => "Error al actualizar el registro",
                    'error'   => [$objetoModel['error']]
                ], 422);
            }

            $newObjetoRequest = $this->consultarRegistroGrupoTrabajoUsuario($request->gtr_codigo[0], $request->ofe_identificacion, $request->usu_email);
            if (empty($newObjetoRequest['error'])) {
                $newObjetoRequest = $newObjetoRequest['model']->first();

                if ($objetoModel->gtu_id == $newObjetoRequest->gtu_id) {
                    $data['gtu_usuario_gestor']    = ($request->filled('gtu_usuario_gestor') && ($request->gtu_usuario_gestor == 'SI' || $request->gtu_usuario_gestor == 'NO')) ? $request->gtu_usuario_gestor : NULL;
                    $data['gtu_usuario_validador'] = ($request->filled('gtu_usuario_validador') && ($request->gtu_usuario_validador == 'SI' || $request->gtu_usuario_validador == 'NO')) ? $request->gtu_usuario_validador : NULL;
                    $objetoModel->update($data);

                    return response()->json([
                        'message' => "Registro actualizado correctamente para {$this->nombre}"
                    ], 200);
                } else {
                    $this->errors = $this->adicionarError($this->errors, ["Ya existe la asociación para {$this->nombre} con el OFE {$request->ofe_identificacion} - Email {$request->usu_email} y Código {$request->gtr_codigo[0]}."]);

                    return response()->json([
                        'message' => "Error al actualizar el registro",
                        'error'   => $this->errors
                    ], 422);
                }
            } else {
                return response()->json([
                    'error' => [$newObjetoRequest['error']]
                ], 422);
            }
        } else {
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors'  => array_merge($validadorRequest->errors()->all(), $validadorParams->errors()->all())
            ], 400);
        }
    }

    /**
     * Hace la consulta de validación de existencia del OFE, grupo de Trabajo y Usuario.
     *
     * @param string $gtr_codigo Código del grupo de trabajo
     * @param string $ofe_identificacion Identificación del usuario
     * @param string $usu_email Email del usuario
     * @return array
     */
    private function consultarRegistroGrupoTrabajoUsuario(string $gtr_codigo, string $ofe_identificacion, string $usu_email): array {
        $arrReturn['error'] = '';

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (!$ofe) {
            $arrReturn['error'] = "El Ofe con identificación [{$ofe_identificacion}] no existe.";
            return $arrReturn;
        }

        // Valida que el grupo de trabajo exista para el usuario asociado
        $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id', 'gtr_codigo'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('gtr_codigo', $gtr_codigo)
            ->first();
        
        if (!$grupoTrabajo) {
            $arrReturn['error'] = "{$this->nombre} con código [{$gtr_codigo}] no existe para el OFE con identificación [{$ofe_identificacion}].";
            return $arrReturn;
        }

        $objUsu = User::select(['usu_id', 'usu_nombre'])
            ->where('usu_email', $usu_email)
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$objUsu) {
            $arrReturn['error'] =  "El Usuario con Email [{$usu_email}] no existe.";
            return $arrReturn;
        }
        
        $objetoModel = $this->className::select(['gtu_id', 'gtr_id', 'usu_id', 'gtu_usuario_gestor', 'gtu_usuario_validador'])
            ->where('gtr_id', $grupoTrabajo->gtr_id)
            ->where('usu_id', $objUsu->usu_id);

        $arrReturn['error'] = '';
        $arrReturn['model'] = $objetoModel;

        return $arrReturn;
    }

    /**
     * Obtiene los usuarios validador según el término a buscar.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchUsuariosValidador(Request $request) {
        $data = [];

        $this->className::select([
                'etl_grupos_trabajo_usuarios.gtu_id', 
                'etl_grupos_trabajo_usuarios.gtr_id',
                'etl_grupos_trabajo_usuarios.usu_id'
            ])
            ->with(['getUsuario:usu_id,usu_email,usu_identificacion,usu_nombre'])
            ->leftjoin('etl_openmain.auth_usuarios as usuario', 'usuario.usu_id', '=', 'etl_grupos_trabajo_usuarios.usu_id')
            ->where(function ($query) use ($request) {
                $query->where('usuario.usu_nombre', 'like', '%' . $request->buscar . '%')
                    ->orWhere('usuario.usu_identificacion', 'like', '%' . $request->buscar . '%');
            })
            ->where('gtu_usuario_validador', 'SI')
            ->get()
            ->unique('usu_id')
            ->map(function ($item) use (&$data) {
                $data[] = [
                    'usu_id'                    => $item->getUsuario->usu_id,
                    'usu_email'                 => $item->getUsuario->usu_email,
                    'usu_identificacion'        => $item->getUsuario->usu_identificacion,
                    'usu_nombre'                => $item->getUsuario->usu_nombre,
                    'usu_identificacion_nombre' => $item->getUsuario->usu_identificacion . ' - ' . $item->getUsuario->usu_nombre
                ];
            });

        return response()->json([
            'data' => $data
        ], 200);
    }
}
