<?php

namespace App\Http\Controllers\Users;

use Mail;
use Validator;
use App\Http\Models\User;
use App\Traits\MainTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\Sistema\Roles\SistemaRol;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class UserController extends OpenTenantController {
    use PackageMainTrait;

    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'usuario';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'usuarios';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = User::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el usuario';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el usuario';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el usuario';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el usuario';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del usuario [%s] No Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id del usuario [%s] Esta Inactivo';

    /**
     * Password por defecto que es asignada a usuarios creados mediante cargue de archivos de Excel.
     * 
     * @var String
     */
    public $password = '0p3n3tl';

    public $nombreDatoCambiarEstado = null;

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
    public $columnasExcel = [
        'NOMBRE COMPLETO',
        'EMAIL',
        'IDENTIFICACION',
        'DIRECCION',
        'TELEFONO',
        'MOVIL',
        'TIPO USUARIO',
        'CODIGOS ROLES'
    ];

    /**
     * Middlewares que serán utilizados en el controlador
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:AdministracionUsuarios,AdministracionUsuariosNuevo,AdministracionUsuariosEditar,
            ActualizarPerfilUsuario,AdministracionUsuariosVer,AdministracionUsuariosCambiarEstado,AsociarUsuariosOfes'
        ])->except([
            'show',
            'store',
            'update',
            'getDetalleUsuario',
            'cambiarEstado',
            'cambiarPassword',
            'obtenerListaUsuarios',
            'getDatosUsuarioAutenticado',
            'updateDatosUsuarioAutenticado',
            'buscarUsuarios',
            'obtenerUsuarios'
        ]);

        $this->middleware([
            'VerificaMetodosRol:AdministracionUsuariosNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:AdministracionUsuariosEditar,ActualizarPerfilUsuario'
        ])->only([
            'show',
            'update',
            'cambiarPassword',
        ]);
        
        $this->middleware([
            'VerificaMetodosRol:AdministracionUsuariosVer'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:AdministracionUsuariosCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:AsociarUsuariosOfes'
        ])->only([
            'obtenerUsuarios'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        if(!isset($request['usu_type'])) {
            $request['usu_type'] = "OPERATIVO";
        }

        $validatorEmail = $this->validationEmailRule($request->usu_email);

        if (!empty($validatorEmail['errors']))
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors'  => $validatorEmail['errors']
            ], 404);

        $rules = User::$rules;
        unset($rules['bdd_id']);
        // Validación conforme a reglas del modelo
        $objValidatorUser = Validator::make($request->all(), $rules);
        
        if($objValidatorUser->fails()){
            return response()->json([
                'message' => 'Errores al crear el Usuario',
                'errors' => $objValidatorUser->errors()->all()
            ], 400);
        }
        
        // Se valida que el email no exista en los usuarios
        $existe = User::where('usu_email', $request->usu_email)
            ->first();

        if(!$existe){
            // Genera una clave de acceso aleatoria
            $password=str_random(10);

            // Usuario autenticado
            $usuario_auth = auth()->user();

            // Verifica que la BD seleccionada exista
            $exiteBddIdRg = MainTrait::existeBddIdRg($request, 'crear');
            if(is_array($exiteBddIdRg)) {
                return response()->json($exiteBddIdRg, 404);
            }

            // Se crea el usuario correspondiente
            $user = User::create([
                'usu_nombre'         => $request->usu_nombre,
                'usu_email'          => $request->usu_email,
                'usu_password'       => Hash::make($password),
                'usu_identificacion' => $request->usu_identificacion,
                'usu_direccion'      => $request->usu_direccion,
                'usu_telefono'       => $request->usu_telefono,
                'usu_movil'          => $request->usu_movil,
                'usu_type'           => $request->usu_type,
                'bdd_id'             => $usuario_auth->bdd_id,
                'bdd_id_rg'          => $usuario_auth->bdd_id_rg,
                'estado'             => 'ACTIVO',
                'usuario_creacion'   => $usuario_auth->usu_id
            ]);

            if (!$user) {
                return response()->json([
                    'message' => 'Se presentaron errores al intentar crear el usuario.',
                    'errors' => []
                ], 422);
            }

            try {
                // Se envía el correo con los datos de acceso a openETL
                $data=[
                    "usu_email" => $request->usu_email,
                    "clave" => $password,
                    "user" => $user,
                    "app_url" => config('variables_sistema.APP_URL_WEB'),
                    "remite" => config('variables_sistema.EMPRESA'),
                    "direccion" => config('variables_sistema.DIRECCION'),
                    "ciudad" => config('variables_sistema.CIUDAD'),
                    "telefono" => config('variables_sistema.TELEFONO'),
                    "web" => config('variables_sistema.WEB'),
                    "email" => config('variables_sistema.EMAIL'),
                    "facebook" => config('variables_sistema.FACEBOOK'),
                    "twitter" => config('variables_sistema.TWITTER')
                ];

                MainTrait::setMailInfo();
                Mail::send(
                    'emails.nuevoUsuario',
                    $data,
                    function ($message) use ($user){
                        $message->from(config('variables_sistema.EMAIL'), env('APP_NAME'));
                        $message->sender(config('variables_sistema.EMAIL'), env('APP_NAME'));
                        $message->subject('Datos de acceso a openETL');
                        $message->to($user->usu_email, $user->usu_nombre);
                    }
                );
            } catch (\Exception $e) {

                return response()->json([
                    'message' => 'Usuario creado con éxito.',
                    'errors' => ['Se presentaron errores al enviar correo.', $e->getMessage()],
                    'usu_id' => $user->usu_id
                ], 409);
            }
            return response()->json([
                'message' => 'Usuario creado con éxito.',
                'usu_id' => $user->usu_id
            ], 201);
        } else { // Email existe
            return response()->json([
                'message' => 'Errores en la Creación del Usuario',
                'errors' => ['El correo electrónico ['.$request->usu_email.'] ya existe.']
            ], 409);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function show($id) {
        return $this->procesadorShow($id, ['getRolesUsuario', 'getUsuarioCreacion', 'getBaseDatosRg:bdd_id,bdd_alias']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $chequear = [
            [
                'field' => 'usu_email',
                'message' => 'El correo electrónico [%s] ya existe.'
            ]
        ];
        $validatorEmail = $this->validationEmailRule($request->usu_email);
        if (!empty($validatorEmail['errors']))
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors'  => $validatorEmail['errors']
            ], 404);

        // Verifica que la BD seleccionada exista
        $exiteBddIdRg = MainTrait::existeBddIdRg($request, 'actualizar');
        if(is_array($exiteBddIdRg)) {
            return response()->json($exiteBddIdRg, 404);
        }

        $usuario_auth = auth()->user();
        
        $request->merge([
            'bdd_id_rg' => $usuario_auth->bdd_id_rg
        ]);
        

        $columnas = ['usu_nombre', 'usu_email', 'usu_identificacion', 'usu_direccion', 'usu_telefono', 'usu_movil', 'usu_type', 'estado', 'bdd_id_rg'];
        return $this->procesadorSimpleUpdate($request, $id, $columnas, 'usu_id', $chequear, true);
    }

    /**
     * Devuelve una lista paginada de usuarios.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function getListaUsuarios(Request $request) {
        // Base de datos del usuario autenticado
        $user = auth()->user();
        if(empty($user->bdd_id_rg)) {
            $filtros = [
                'AND' => [
                    ['bdd_id', '=', $user->bdd_id],
                    ['bdd_id_rg', '=', null]
                ]
            ];
        } else {
            $filtros = [
                'AND' => [
                    ['bdd_id_rg', '=', $user->bdd_id_rg]
                ]
            ];
        }

        $adminRol     = SistemaRol::where('rol_codigo', 'superadmin')->first();
        $mesaayudaRol = SistemaRol::where('rol_codigo', 'usuarioma')->first();
        if($user->esSuperadmin()) {
            $queryRaws = [
                "usu_id NOT IN (SELECT usu_id FROM `sys_roles_usuarios` WHERE rol_id = {$adminRol->rol_id})"
            ];
        } else {
            $queryRaws = [
                "usu_id NOT IN (SELECT usu_id FROM `sys_roles_usuarios` WHERE rol_id IN ($adminRol->rol_id, $mesaayudaRol->rol_id))"
            ];
        }

        $condiciones = [
            'filters' => $filtros,
            'queryRaws' => $queryRaws
        ];

        $columnas = [
            'usu_id',
            'usu_nombre',
            'usu_email',
            'usu_identificacion',
            'usu_direccion',
            'usu_telefono',
            'usu_movil',
            'usu_type',
            'bdd_id_rg',
            'estado',
            'usuario_creacion',
            'fecha_creacion',
            'fecha_modificacion'
        ];

        $relaciones = [
            'getBaseDatosRg:bdd_id,bdd_alias'
        ];

        $exportacion = [
            'columnas' => [
                'usu_nombre'         => 'Nombre Completo',
                'usu_email'          => 'Email',
                'usu_identificacion' => 'Identificación',
                'usu_direccion'      => 'Dirección',
                'usu_telefono'       => 'Telefono',
                'usu_movil'          => 'Movil',
                'usu_type'           => 'Tipo Usuario',
                'estado'             => 'Estado',
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
            'titulo' => 'usuarios'
        ];
        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, [], 'usuarios');
    }

    /**
     * 
     * Cambia el estado de los registros.
     * 
     * @param Request $request
     * @param $ids
     * 
     * @return response
     * 
     */
    public function cambiarEstado(Request $request) {
        $arrObjetos  = $request->all();
        $arrErrores = [];

        foreach($arrObjetos as $idObj){
            if(is_numeric($idObj)) {
                $objeto = $this->className::find($idObj);
                
                if($objeto){
                    if($objeto->usu_type != 'INTEGRACION') {
                        $strEstado = ($objeto->estado == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                        $objeto->update(['estado' => $strEstado]);

                        if(!$objeto)
                            $this->adicionarErrorArray($arrErrores, ["Errores Al Actualizar {$this->nombre} [{$idObj}]"]);
                    } else
                        $this->adicionarErrorArray($arrErrores, ["El {$this->nombre} [{$idObj}] es del tipo INTEGRACION y no es posible cambiar su estado"]);
                } else
                    $this->adicionarErrorArray($arrErrores, ["El {$this->nombre} [{$idObj}] No Existe."]);
            }
        }

        if(empty($arrErrores)) {
            return response()->json([
                'success' => true
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'message' => "Cambio de estado de {$this->nombrePlural} procesado a excepcion de los listados en errores",
                'errors' => $arrErrores
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Genera una Interfaz de Usuarios para guardar en Excel.
     *
     * @return void
     */
    public function generarInterfaceUsuarios() {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'usuarios');
    }

    /**
     * Gestiona la carga masiva de usuarios por medio de archivos de excel.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function cargarUsuarios(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $objUser = auth()->user();

        if ($request->hasFile('archivo')) {
            $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
            if (
                (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
                !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
            )
                return response()->json([
                    'message' => 'Errores al guardar los Usuarios',
                    'errors'  => ['Solo se permite la carga de archivos EXCEL.']
                ], 409);

            $data = $this->parserExcel($request);
            if (!empty($data)) {
                // Se obtinen las columnas de la interfaz sanitizadas
                $tempColumnas = [];
                foreach ($this->columnasExcel as $k) {
                    if ($k !== "CODIGOS ROLES")
                        $tempColumnas[] = strtolower($this->sanear_string(str_replace(' ', '_', $k)));
                }

                // Se obtienen las columnas del excel cargado
                $columnas = [];
                foreach ($data as $fila => $columna) {
                    // Se eliminan las columnas que son propias del excel generado desde el Tracking
                    unset(
                        $columna['estado'],
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
                    'message' => 'Errores al guardar los Usuarios',
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores   = [];
            $arrResultado = [];
            $arrEmails    = [];

            foreach ($data as $fila => $columnas) {
                $Acolumnas = $columnas;
                $columnas = (object) $columnas; 

                $nCantidadVacias = 0;
                foreach($Acolumnas as $key => $value) {
                    $Acolumnas[$key] = trim($value);
                    if ($Acolumnas[$key] == ""){
                        $nCantidadVacias++;
                    }
                }

                if ($nCantidadVacias == count($Acolumnas)){
                    unset($data[$fila]);
                } else {
                    $arrUsuarios = [];
                    $arrFaltantes = $this->checkFields($Acolumnas, [
                        'nombre_completo',
                        'email',
                        'identificacion',
                        'telefono',
                        'tipo_usuario',
                    ], $fila);

                    if(!empty($arrFaltantes)){
                        $vacio = $this->revisarArregloVacio($columnas);

                        if($vacio) {
                            $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes);
                        } else {
                            unset($data[$fila]);
                        }
                    } else {
                        $email = $this->soloEmail($columnas->email);
                        if (!empty($email)){
                            if (array_key_exists($email, $arrEmails)) {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Correo ' . $columnas->email . ' ya existe en otras filas.'], $fila);
                            }
                            else {
                                $arrUsuarios['email'] = $email;
                                $arrEmails[$email] = true;
                            }
                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Correo ' . $columnas->email . ' No es valido.'], $fila);
                        }

                        $userExists = $this->className::select(['usu_id', 'bdd_id', 'bdd_id_rg', 'usu_type'])->where('usu_email', $email)->first();
                        $arrUsuarios['usu_id'] = 0;

                        // El usuario autenticado tiene base de datos asignada
                        if(!empty($objUser->bdd_id_rg)) {
                            if (empty($userExists)) {
                                $arrUsuarios['bdd_id']       = $objUser->bdd_id;
                                $arrUsuarios['bdd_id_rg']    = $objUser->bdd_id_rg;
                                $arrUsuarios['usu_password'] = Hash::make($this->password);

                                if(trim($columnas->tipo_usuario) == 'INTEGRACION' && trim($columnas->estado) == 'INACTIVO')
                                    $arrErrores = $this->adicionarError($arrErrores, ['No se permite la creación de usuarios del tipo [INTEGRACION] con estado [INACTIVO]'], $fila);
                            } else {
                                if($userExists->bdd_id_rg != $objUser->bdd_id_rg) {
                                    $arrErrores = $this->adicionarError($arrErrores, ['Usuario no válido.'], $fila);
                                } else {
                                    $arrUsuarios['usu_id']    = $userExists->usu_id;
                                    $arrUsuarios['bdd_id']    = $userExists->bdd_id;
                                    $arrUsuarios['bdd_id_rg'] = $userExists->bdd_id_rg;
                                }

                                if($userExists->usu_type == 'INTEGRACION')
                                    $arrErrores = $this->adicionarError($arrErrores, ['No se permite la actualización de usuarios del tipo [INTEGRACION]'], $fila);
                            }
                        } else {
                            if (empty($userExists)) {
                                $arrUsuarios['bdd_id']       = $objUser->bdd_id;
                                $arrUsuarios['bdd_id_rg']    = null;
                                $arrUsuarios['usu_password'] = Hash::make($this->password);

                                if(trim($columnas->tipo_usuario) == 'INTEGRACION' && trim($columnas->estado) == 'INACTIVO')
                                    $arrErrores = $this->adicionarError($arrErrores, ['No se permite la creación de usuarios del tipo [INTEGRACION] con estado [INACTIVO]'], $fila);
                            } else {
                                $arrUsuarios['usu_id']    = $userExists->usu_id;
                                $arrUsuarios['bdd_id']    = $userExists->bdd_id;
                                $arrUsuarios['bdd_id_rg'] = $userExists->bdd_id_rg;

                                if($userExists->usu_type == 'INTEGRACION')
                                    $arrErrores = $this->adicionarError($arrErrores, ['No se permite la actualización de usuarios del tipo [INTEGRACION]'], $fila);
                            }
                        }

                        $arrUsuarios['roles'] = array();
                        if (isset($columnas->codigos_roles) && !empty(trim($columnas->codigos_roles))) {
                            $roles = explode(',', $columnas->codigos_roles);
                            foreach ($roles as $key => $rol_codigo) {
                                $existeRol = SistemaRol::where('rol_codigo', $rol_codigo)->first();
                                if (empty($existeRol)){
                                    $arrErrores = $this->adicionarError($arrErrores, ['El Rol' . $rol_codigo . ' No existe.'], $fila);
                                } else {
                                    $arrUsuarios['roles'][] = $existeRol->rol_id;
                                }
                            }
                        }

                        $arrUsuarios['usu_email']          = $this->soloEmail($columnas->email);
                        $arrUsuarios['usu_nombre']         = $this->sanitizarStrings($columnas->nombre_completo);
                        $arrUsuarios['usu_identificacion'] = $this->sanitizarStrings($columnas->identificacion);
                        $arrUsuarios['usu_telefono']       = $this->sanitizarStrings($columnas->telefono);
                        $arrUsuarios['usu_direccion']      = $this->sanitizarStrings($columnas->direccion);
                        $arrUsuarios['usu_type']           = $this->sanitizarStrings($columnas->tipo_usuario);

                        $arrUsuarios['usu_movil'] = '';
                        if (isset($columnas->movil)){
                            $arrUsuarios['usu_movil'] = $this->sanitizarStrings($columnas->movil);
                        }

                        if(empty($arrErrores)){
                            $rules = User::$rules;
                            unset($rules['bdd_id']);
                            $objValidator = Validator::make($arrUsuarios, $rules);

                            if(!empty($objValidator->errors()->all())) {
                                $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                            } else {
                                $arrResultado[] = $arrUsuarios;
                            }
                        }
                    }
                }
                if ($fila % 500 === 0){
                    $this->renovarConexion($objUser);
                }
            }

            if(!empty($arrErrores)) {
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
                    'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_USU,
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
                    'message' => 'Errores al guardar los Usuarios',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);

            } else {
                $insertUsuarios = [];
                foreach ($arrResultado as $usuario) {                        
                    $data = [
                        'usu_id'             => $usuario['usu_id'],
                        'usu_nombre'         => $this->sanitizarStrings($usuario['usu_nombre']),
                        'usu_email'          => $this->soloEmail($usuario['usu_email']),
                        'usu_identificacion' => $this->sanitizarStrings($usuario['usu_identificacion']),
                        'usu_direccion'      => $this->sanitizarStrings($usuario['usu_direccion']),
                        'usu_telefono'       => $this->sanitizarStrings($usuario['usu_telefono']),
                        'usu_movil'          => $this->sanitizarStrings($usuario['usu_movil']),
                        'usu_type'           => $this->sanitizarStrings($usuario['usu_type']),
                        'bdd_id'             => $usuario['bdd_id'],
                        'bdd_id_rg'          => $usuario['bdd_id_rg'],
                        'roles'              => $usuario['roles']
                    ];

                    if(array_key_exists('usu_password', $usuario) && !empty($usuario['usu_password'])) {
                        $data['usu_password'] = $usuario['usu_password'];
                    }

                    array_push($insertUsuarios, $data);
                }

                if (count($insertUsuarios) > 0) {
                    $bloques = array_chunk($insertUsuarios, 100);
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
                                'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_USU,
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
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de usuarios.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListaErroresUsuarios(Request $request)
    {
        // Usuario autenticado
        $user = auth()->user();

        if ($user->usu_type == 'ADMINISTRADOR' || $user->usu_type == 'MA') {
            // Se obtiene la colección conforme a los parámetros en el request
            $cargues = EtlProcesamientoJson::select('pjj_id', 'pjj_tipo', 'pjj_errores', 'usuario_creacion', 'fecha_creacion')
                ->where('fecha_creacion', '>=', $request->fechaCargue . ' 00:00:00')
                ->where('fecha_creacion', '<=', $request->fechaCargue . ' 23:59:59')
                ->where('pjj_procesado', 'SI')
                ->where('pjj_tipo', ProcesarCargaParametricaCommand::$TYPE_USU)
                ->whereNotNull('pjj_errores')
                ->UsuariosBaseDatos();
        } else {
            // Se obtiene la colección conforme a los parámetros en el request
            $cargues = EtlProcesamientoJson::select('pjj_id', 'pjj_tipo', 'pjj_errores', 'usuario_creacion', 'fecha_creacion')
                ->where('usuario_creacion', $user->usu_id)
                ->where('fecha_creacion', '>=', $request->fechaCargue . ' 00:00:00')
                ->where('fecha_creacion', '<=', $request->fechaCargue . ' 23:59:59')
                ->where('pjj_procesado', 'SI')
                ->where('pjj_tipo', ProcesarCargaParametricaCommand::$TYPE_USU)
                ->whereNotNull('pjj_errores');
        }

        $cargues = $cargues->orderBy('fecha_creacion', 'desc')->get();
        if ($cargues) {

            // La colección resultante debe ser reorganizada en otra colección
            // dado que por cada registro en la colección original, pueden
            // existir múltiples documentos con múltiples errores
            $totalErrores = 0;
            $newErroresCargues = [];
            // Itera sobre la colección resultando para crear la nueva
            $cargues->map(function ($cargue) use (&$newErroresCargues, &$totalErrores, $request) {
                $errores = json_decode($cargue->pjj_errores);

                if (!empty($errores)){
                    foreach ($errores as $documento => $erroresDocumento) {

                        $erroresDocumento = (array)$erroresDocumento;
                        foreach ($erroresDocumento as $key => $value) {
                            if (is_object($erroresDocumento[$key])) {
                                $erroresDocumento[$key] = implode('<br>', (array)(json_encode($erroresDocumento[$key])));
                            } else {
                                $erroresDocumento[$key] = $erroresDocumento[$key];
                            }
                        }

                        if ($request->buscar != '') {
                            $cadenaErrores = implode(' - ', $erroresDocumento);
                            if (
                                stripos($cadenaErrores, $request->buscar) !== false ||
                                stripos($documento, $request->buscar) !== false ||
                                $documento == $request->buscar ||
                                stripos($cargue->fecha_creacion, $request->buscar) !== false
                            ) {
                                $newErroresCargues[$totalErrores]['pjj_id'] = $cargue->pjj_id;
                                $newErroresCargues[$totalErrores]['pjj_tipo'] = $cargue->pjj_tipo;
                                $newErroresCargues[$totalErrores]['usuario_creacion'] = $cargue->usuario_creacion;
                                $newErroresCargues[$totalErrores]['fecha_creacion'] = $cargue->fecha_creacion->format('Y-m-d H:i:s');
                                $newErroresCargues[$totalErrores]['documento'] = $documento;
                                $newErroresCargues[$totalErrores]['errores'] = implode('<br>', $erroresDocumento);
                            }
                        } else {
                            $newErroresCargues[$totalErrores]['pjj_id'] = $cargue->pjj_id;
                            $newErroresCargues[$totalErrores]['pjj_tipo'] = $cargue->pjj_tipo;
                            $newErroresCargues[$totalErrores]['usuario_creacion'] = $cargue->usuario_creacion;
                            $newErroresCargues[$totalErrores]['fecha_creacion'] = $cargue->fecha_creacion->format('Y-m-d H:i:s');
                            $newErroresCargues[$totalErrores]['documento'] = $documento;
                            $newErroresCargues[$totalErrores]['errores'] = implode('<br>', $erroresDocumento);
                        }
                        $totalErrores++;
                    }
                }
            });
            // Genera la nueva colección y la pagina conforme a los parametros recibidos
            $erroresCargues = array_slice($newErroresCargues, $request->start, $request->length );
        } else {
            $erroresCargues = [];
        }

        return response()->json([
            "total" => $totalErrores,
            "filtrados" => count($newErroresCargues),
            "data" => $erroresCargues
        ], 200);
    }

    /*
     * Retorna una lista en Excel de errores de cargue de usuarios.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function descargarListaErroresUsuarios(Request $request)
    {
        // Usuario autenticado
        $user = auth()->user();

        if ($user->usu_type == 'ADMINISTRADOR' || $user->usu_type == 'MA') {
            $cargues = EtlProcesamientoJson::select('pjj_id', 'pjj_tipo', 'pjj_errores', 'usuario_creacion', 'fecha_creacion')
                ->where('fecha_creacion', '>=', $request->fechaCargue . ' 00:00:00')
                ->where('fecha_creacion', '<=', $request->fechaCargue . ' 23:59:59')
                ->where('pjj_procesado', 'SI')
                ->where('pjj_tipo', ProcesarCargaParametricaCommand::$TYPE_USU)
                ->whereNotNull('pjj_errores')
                ->UsuariosBaseDatos();
        } else {
            // Se obtiene la colección conforme a los parámetros en el request
            $cargues = EtlProcesamientoJson::select('pjj_id', 'pjj_tipo', 'pjj_errores', 'usuario_creacion', 'fecha_creacion')
                ->where('usuario_creacion', $user->usu_id)
                ->where('fecha_creacion', '>=', $request->fechaCargue . ' 00:00:00')
                ->where('fecha_creacion', '<=', $request->fechaCargue . ' 23:59:59')
                ->where('pjj_procesado', 'SI')
                ->where('pjj_tipo', ProcesarCargaParametricaCommand::$TYPE_USU)
                ->whereNotNull('pjj_errores');
        }
        $cargues = $cargues->orderBy('fecha_creacion', 'desc')->get();


        if ($cargues) {
            // La colección resultante debe ser reorganizada en otra colección
            // dado que por cada registro en la colección original, pueden
            // existir múltiples documentos con múltiples errores
            $totalErrores = 0;
            $newCollectionCargues = [];
            // Itera sobre la colección resultando para crear la nueva
            $cargues->map(function ($cargue) use (&$newCollectionCargues, &$totalErrores, $request) {
                $errores = json_decode($cargue->pjj_errores);

                foreach ($errores as $documento => $erroresDocumento) {
                    $erroresDocumento = (array) $erroresDocumento;
                    foreach ($erroresDocumento as $key => $value) {
                        if (is_object($erroresDocumento[$key])) {
                            $erroresDocumento[$key] = implode('<br>', (array)(json_encode($erroresDocumento[$key])));
                        } else {
                            $erroresDocumento[$key] = $erroresDocumento[$key];
                        }
                    }

                    if ($request->buscar != '') {
                        $cadenaErrores = implode(' - ', $erroresDocumento);
                        if (
                            stripos($cadenaErrores, $request->buscar) !== false ||
                            stripos($documento, $request->buscar) !== false ||
                            $documento == $request->buscar ||
                            stripos($cargue->fecha_creacion, $request->buscar) !== false
                        ) {
                            $newCollectionCargues[$totalErrores]['pjj_id'] = $cargue->pjj_id;
                            $newCollectionCargues[$totalErrores]['pjj_tipo'] = $cargue->pjj_tipo;
                            $newCollectionCargues[$totalErrores]['usuario_creacion'] = $cargue->usuario_creacion;
                            $newCollectionCargues[$totalErrores]['fecha_creacion'] = $cargue->fecha_creacion->format('Y-m-d H:i:s');
                            $newCollectionCargues[$totalErrores]['documento'] = $documento;
                            $newCollectionCargues[$totalErrores]['errores'] = implode('<br>', $erroresDocumento);
                        }
                    } else {
                        $newCollectionCargues[$totalErrores]['pjj_id'] = $cargue->pjj_id;
                        $newCollectionCargues[$totalErrores]['pjj_tipo'] = $cargue->pjj_tipo;
                        $newCollectionCargues[$totalErrores]['usuario_creacion'] = $cargue->usuario_creacion;
                        $newCollectionCargues[$totalErrores]['fecha_creacion'] = $cargue->fecha_creacion->format('Y-m-d H:i:s');
                        $newCollectionCargues[$totalErrores]['documento'] = $documento;
                        $newCollectionCargues[$totalErrores]['errores'] = implode('<br>', $erroresDocumento);
                    }
                    $totalErrores++;
                }
            });
            // Genera la nueva colección y la pagina conforme a los parametros recibidos
            $newCollectionCargues = collect($newCollectionCargues);
            $newCollectionCargues = $newCollectionCargues
                ->forPage($request->start, $request->length == -1 ? $totalErrores : $request->length);
        } else {
            $newCollectionCargues = collect();
        }

        $objDocumentos = $newCollectionCargues->values();

        // Si existen resultados se genera el archivo de excel
        if ($objDocumentos->count() > 0) {
            // Array que pasa al Excel
            $arrDocumentos = [];

            // Itera sobre la colección de documentos para armar
            // el array que se integrará en el archivo de Excel
            $objDocumentos->each(function ($documento) use (&$arrDocumentos) {
                $arrDocumentos[] = [
                    $documento['fecha_creacion'],
                    $documento['errores']
                ];
            });

            $titulos = [
                'FECHA CARGUE',
                'ERRORES'
            ];

            $nombreArchivo = 'carga_usuarios_log_errores_' . $request->fechaCargue;
            $archivoExcel = $this->toExcel($titulos, $arrDocumentos, $nombreArchivo);

            $headers = [
                header('Access-Control-Expose-Headers: Content-Disposition')
            ];
    
            return response()
                ->download($archivoExcel, $nombreArchivo . '.xlsx', $headers)
                ->deleteFileAfterSend(true);
        } else {
            return response()->json([
                'message' => 'Error',
                'errors' => ['Sin resultados en la consulta']
            ], 400);
        }
    }

    /**
     * Obtiene una lista de usuarios del sistema relacionados con la BD a la cual pertenece el usuario autenticacado
     *
     * @param string $buscarValor
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerUsuarios($buscarValor) {
        // Usuario autenticado
        $user = auth()->user();
        $data = [];
        $adminRol = SistemaRol::where('rol_codigo', 'superadmin')->first();
        User::whereRaw("(usu_email LIKE '%$buscarValor%' OR usu_identificacion LIKE '%$buscarValor%' OR usu_nombre LIKE '%$buscarValor%')")
            ->where('usu_type', '!=', 'INTEGRACION')
            ->where('bdd_id', $user->getBaseDatos->bdd_id)
            ->whereRaw("usu_id IN (SELECT usu_id FROM `sys_roles_usuarios` WHERE rol_id != {$adminRol->rol_id})")
            ->get()->map(function ($item) use (&$data) {
                $data[] = [
                    'usu_email'                 => $item->usu_email,
                    'usu_identificacion'        => $item->usu_identificacion,
                    'usu_nombre'                => $item->usu_nombre,
                    'usu_identificacion_nombre' => $item->usu_identificacion . ' - ' . $item->usu_nombre
                ];
            });
        return response()->json(['data' => $data,], 200);
    }

    /**
     * Retorna una lista de usuarios en función de sus IDs
     *
     * @param $ids
     * @return mixed
     */
    public function obtenerListaUsuarios($ofe_idententificacion)
    {
        // Se omite el estado inactivo porque aunque este inactivo el ofe tiene usuarios asociados
        $ofe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $ofe_idententificacion)
            ->validarAsociacionBaseDatos()
            ->first();
        if ($ofe === null)
            return response()->json([
                'message' => "El oferente $ofe_idententificacion no existe"
            ], 404);

        $consulta = sprintf('JSON_CONTAINS(`usu_relaciones`,\'{"oferentes": ["%s"]}\')', $ofe->ofe_identificacion);
        $data = [];
        User::select(['usu_email', 'usu_identificacion', 'usu_nombre'])->whereRaw($consulta)
            ->where('estado', 'ACTIVO')->get()
            ->map(function ($item) use (&$data){
                $data[] = [
                    'usu_email'                 => $item->usu_email,
                    'usu_identificacion'        => $item->usu_identificacion,
                    'usu_nombre'                => $item->usu_nombre,
                    'usu_identificacion_nombre' => $item->usu_identificacion . ' - ' . $item->usu_nombre
                ];
            });
        return response()->json(['data' => $data,], 200);
    }

    /**
     * Cambia el password del Usuario.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  $usuario
     * @return \Illuminate\Http\Response
     */
    public function cambiarPassword(Request $request, $usuario) {

        // Validación conforme a reglas del modelo
        $objValidatorUser = Validator::make([$request->password], ['usu_password' => 'nullable|min:6|max:20']);
        if($objValidatorUser->fails()){
            return response()->json([
                'message' => 'Errores en la Actualización de la clave del Usuario',
                'errors' => $objValidatorUser->errors()->all()
            ], 422);
        }

        $user = User::find($usuario);

        if ($user->estado == 'INACTIVO'){
            return response()->json([
                'message' => 'Errores en la Actualización del Usuario',
                'errors' => ['No se permiten actualizar registros en estado INACTIVO.']
            ], 400);
        }
        
        $dataErrores = array();

        if ($user) {
            $user->update(['usu_password' => Hash::make($request->password)]);
        } else {
            $dataErrores[] = 'El Id del Usuario ['.$usuario.'] no existe.';
        }


        if (count($dataErrores) == 0) {
            return response()->json([
                'message' => 'Clave de Usuario modificada con éxito.',
                'usu_id' => $user->usu_id
            ], 200);
        } else {
            return response()->json([
                'message' => 'Cambiar Clave de Usuario',
                'errors' => $dataErrores
            ], 400);
        }
    }

    /**
     * Retorna un lista con los proveedores a los que el usuario puede gestionarle documentos
     *
     * @param Request $request
     * @param $usuario
     * @return \Illuminate\Http\JsonResponse
     */
    public function proveedoresGestionables(Request $request, $usuario) {
        $consulta = sprintf('JSON_CONTAINS(`usu_relaciones`,\'{"usuarios": [%d]}\')', $usuario);
        $data = [];
        ConfiguracionProveedor::select(['pro_identificacion', 'pro_razon_social'])->whereRaw($consulta)
            ->where('estado', 'ACTIVO   ')->get()
            ->map(function ($item) use (&$data){
                $data[] = ['pro_identificacion' => $item->pro_identificacion, 'pro_razon_social' => $item->pro_razon_social];
            });
        return response()->json(['proveedores' => $data], 200);
    }

    /**
     * Retorna los datos del usuario autenticado en el sistema
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatosUsuarioAutenticado() {
        return response()->json([
            'data' => auth()->user()
        ], 200);
    }

    /**
     * Actualiza los datos del usuario autenticado en el sistema
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDatosUsuarioAutenticado(Request $request) {
        // Usuario autenticado
        $user = auth()->user();

        // Si llega en los parámetros el bdd_id, se omite porque ese campo no se debe poder cambiar
        if($request->has('bdd_id')) {
            unset($request['bdd_id']);
        }

        // Si llega en los parámetros el usu_type, se omite porque ese campo no se debe poder cambiar
        if($request->has('usu_type')) {
            unset($request['usu_type']);
        }
        
        return $this->update($request, $user->usu_id);
    }

    /**
     * Obtiene una lista de usuarios del sistema relacionados con la BD a la cual pertenece el usuario autenticacado.
     * 
     * Lista los usuarios donde el campo usu_type sea igual a OPERATIVO.
     * 
     * El parámetro consultasAdicionales llega como base64 toda vez que que contiene un string Json y es necesario recibirlo sin codificación url teniendo en cuenta que el método del endpoint es GET
     *
     * @param string $buscarValor Valor para buscar el usuario
     * @param string $consultasAdicionales String json en base64 que puede contener pares modelo => columna|valor y que permite realizar búsquedas de registros en diferentes tablas del sistema para relacionar data contra los usurios filtrados
     * @return \Illuminate\Http\JsonResponse
     */
    public function buscarUsuarios($buscarValor, $consultasAdicionales = '') {
        // Usuario autenticado
        $user = auth()->user();
        $data = [];
        $adminRol = SistemaRol::where('rol_codigo', 'superadmin')->first();
        $usuarios = User::whereRaw("(usu_email LIKE '%$buscarValor%' OR usu_identificacion LIKE '%$buscarValor%' OR usu_nombre LIKE '%$buscarValor%')")
            ->where('usu_type', 'OPERATIVO')
            ->where('bdd_id', $user->getBaseDatos->bdd_id);

        if(!empty($consultasAdicionales)) {
            $consultasAdicionales = json_decode(base64_decode($consultasAdicionales), true);
            foreach($consultasAdicionales as $modelo => $parametros) {
                list($columna, $valor) = explode('|', $parametros);
                if($modelo == 'grupos_trabajo' && !empty($valor)) {
                    $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id'])
                        ->where($columna, $valor)
                        ->where('estado', 'ACTIVO')
                        ->first();

                    if($grupoTrabajo) {
                        $usuarios = $usuarios->where(function($query) use ($user, $grupoTrabajo) {
                                $query->whereRaw('
                                    exists (
                                        select * from `' . $user->getBaseDatos->bdd_nombre . '`.`etl_grupos_trabajo_usuarios`
                                        where `etl_openmain`.`auth_usuarios`.`usu_id` = `' . $user->getBaseDatos->bdd_nombre . '`.`etl_grupos_trabajo_usuarios`.`usu_id`
                                        and `' . $user->getBaseDatos->bdd_nombre . '`.`etl_grupos_trabajo_usuarios`.`gtr_id` = ' . $grupoTrabajo->gtr_id . '
                                    )');
                            }
                        );
                    }
                } elseif($modelo == 'oferente' && !empty($valor)) {
                    $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
                        ->where($columna, $valor)
                        ->where('estado', 'ACTIVO')
                        ->first();

                    if($ofe) {
                        $usuarios = $usuarios->where(function($query) use ($ofe) {
                            if (!empty($ofe->bdd_id_rg)) {
                                $query->where('bdd_id_rg', $ofe->bdd_id_rg);
                            } else {
                                $query->whereNull('bdd_id_rg');
                            }
                        });
                    }
                }
            }
        }

        $usuarios->get()
            ->map(function ($item) use (&$data) {
                $data[] = [
                    'usu_id'                    => $item->usu_id,
                    'usu_email'                 => $item->usu_email,
                    'usu_identificacion'        => $item->usu_identificacion,
                    'usu_nombre'                => $item->usu_nombre,
                    'usu_identificacion_nombre' => $item->usu_identificacion . ' - ' . $item->usu_nombre
                ];
            });
            
        return response()->json([
            'data' => $data
        ], 200);
    }
}
