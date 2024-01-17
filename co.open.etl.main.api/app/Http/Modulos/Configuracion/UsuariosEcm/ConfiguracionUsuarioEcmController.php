<?php

namespace App\Http\Modulos\Configuracion\UsuariosEcm;

use Validator;
use Carbon\Carbon;
use App\Http\Models\User;
use App\Traits\OpenEcmTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\OpenTenantController;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcm;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionUsuarioEcmController extends OpenTenantController {
    use OpenEcmTrait;

    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Usuario openECM';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Usuarios openECM';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ConfiguracionUsuarioEcm::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Usuario de openECM';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Usuario de openECM';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Usuario de openECM';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Usuario de openECM';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id de el Usuario de openECM [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id de el Usuario de openECM [%s] Esta Inactivo';

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
    public $columnasExcel = [
        'NIT OFE',
        'RAZON SOCIAL',
        'IDENTIFICACIÓN',
        'NOMBRE',
        'EMAIL',
        'ROL OPENECM',
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionUsuarioEcm,ConfiguracionUsuarioEcmNuevo,ConfiguracionUsuarioEcmEditar,ConfiguracionUsuarioEcmVer,ConfiguracionUsuarioEcmCambiarEstado,ConfiguracionUsuarioEcmSubir,ConfiguracionUsuarioEcmDescargarExcel'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'obtenerRolesEcm',
            'consultaOfes',
            'cargarUsuarioEcm',
            'generarInterfaceUsuarioEcm',
            'getListaErroresUsuarioEcm',
            'descargarListaErroresUsuarioEcm',
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionUsuarioEcmNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionUsuarioEcmEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionUsuarioEcmVer,ConfiguracionUsuarioEcmEditar'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionUsuarioEcmVer'
        ])->only([
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionUsuarioEcmCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionUsuarioEcmSubir'
        ])->only([
            'cargarUsuarioEcm',
            'generarInterfaceUsuarioEcm',
            'getListaErroresUsuarioEcm',
            'descargarListaErroresUsuarioEcm'
        ]);
    }

    /**
     * Obtiene los Roles de openECM.
     *
     * @param $ofe_identificacion Identificación del Ofe seleccionado
     * @return Response
     */
    public function obtenerRolesEcm($ofe_identificacion) {
        $login = $this->loginECM($ofe_identificacion);
        
        $parametros = [];
        $endPointEcm = $this->endPointEcm('endpoint_roles');

        $parametros['buscarId']          = '';
        $parametros['buscarDescripcion'] = '';
        $parametros['buscarExacto']      = 'NO';

        $respuesta = $this->enviarPeticionECM($login['url_api'], $endPointEcm, $login['token'], 'POST', 'form_params', $parametros);

        if (!empty($respuesta['errors'])) {
            $status = (array_key_exists('status', $respuesta)) ? $respuesta['status'] : 422;

            return response()->json([
                'message' => $respuesta['message'],
                'errors' => [
                    $respuesta['errors']
                ]
            ], $status);
        }

        return response()->json([
            'data' => $respuesta['data']
        ], 200);
    }

    /**
     * Obtiene los Ofes de acuerdo al usuario seleccionado.
     *
     * @param $usu_identificacion Identificación del usuario seleccionado
     * @return Response
     */
    public function consultaOfes($usu_identificacion) {
        
        $user = User::where('usu_identificacion', $usu_identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_integracion_ecm',
            DB::raw('TRIM(IF(LENGTH(ofe_razon_social) = 0 OR IFNULL(ofe_razon_social, TRUE), CONCAT(IF(IFNULL(ofe_razon_social, "") = "", "", ofe_razon_social)," ",IF(IFNULL(ofe_otros_nombres, "") = "", "", ofe_otros_nombres)," ", IF(IFNULL(ofe_primer_nombre, "") = "", "", ofe_primer_nombre), " ", IF(IFNULL(ofe_primer_apellido, "") = "", "", ofe_primer_apellido), " ", IF(IFNULL(ofe_segundo_apellido, "") = "", "", ofe_segundo_apellido)), ofe_razon_social)) as ofe_razon_social')
        ])
            ->where('ofe_integracion_ecm', 'SI')
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->orderBy('ofe_razon_social', 'asc')
            ->get();

        $temp = [];
        foreach ($ofes as $ofe) {
            $data = $ofe->toArray();
            $data['ofe_identificacion_ofe_razon_social'] = $data['ofe_identificacion'] . ' - ' . $data['ofe_razon_social'];
            $temp[] = $data;
        }
        $ofes = $temp;

        return response()->json([
            'data' => $ofes
        ], 200);
    }

    /**
     * Muestra el usuario openECM especificado.
     *
     * @param  int  $id Identificador del registro procesado
     * @return Response
     */
    public function show($id) {
        $objectModel = [];
        // Se obtiene la información del usuario
        $user = User::select(['usu_id', 'usu_identificacion', 'usu_email', 'usu_nombre'])
            ->where('usu_identificacion', $id)
            ->first();

        if ($user) {
            $objectUser['usu_identificacion'] = $user['usu_identificacion'];
            $objectUser['usu_id']     = $user['usu_id'];
            $objectUser['usu_email']  = $user['usu_email'];
            $objectUser['usu_nombre'] = $user['usu_nombre'];

            $usuariosECM = $this->className::where('usu_id', $user['usu_id'])
                ->get();
            
            $arrInformacionECM = [];
            $count = 1;
            foreach($usuariosECM as $usuarioECM) {
                // Se obtiene la información del Ofe
                $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
                    ->where('ofe_id', $usuarioECM['ofe_id'])
                    ->first();

                $data['ofe_id'] = $ofe->ofe_identificacion;
                $data['ros_id'] = $usuarioECM['use_rol'];

                // Se obtiene la informacion de los roles a partir del oferente
                $parametros = [];
                $login = $this->loginECM($ofe->ofe_identificacion);
                $endPointEcm = $this->endPointEcm('endpoint_roles');

                $parametros['buscarId']          = '';
                $parametros['buscarDescripcion'] = '';
                $parametros['buscarExacto']      = 'NO';

                $respuesta = $this->enviarPeticionECM($login['url_api'], $endPointEcm, $login['token'], 'POST', 'form_params', $parametros);
                
                if (!empty($respuesta['errors'])) {
                    $status = (array_key_exists('status', $respuesta)) ? $respuesta['status'] : 422;

                    return response()->json([
                        'message' => $respuesta['message'],
                        'errors' => [
                            $respuesta['errors']
                        ]
                    ], $status);
                } else {
                    $data['roles'] = $respuesta['data'];
                }

                $arrInformacionECM[] = $data;

                if ($count == 1) {
                    $objectModel['fecha_creacion']      = Carbon::parse($usuarioECM['fecha_creacion']);
                    $objectModel['fecha_modificacion']  = Carbon::parse($usuarioECM['fecha_modificacion']);
                    $objectModel['estado']              = $usuarioECM['estado'];
                    $objectModel['fecha_actualizacion'] = $usuarioECM['fecha_actualizacion'];
                }
                $count++;
            }
            $objectModel['informacion_ecm'] = $arrInformacionECM;
            $objectModel['usuario'] = $objectUser;
        }

        if ($objectModel){
            return response()->json([
                'data' => $objectModel
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => "Se produjo un error al procesar la información",
            'errors' => ["No Se Encontró el Usuario openECM con Identificacion [{$id}]."]
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Almacena un usuario openECM.
     * 
     * @param Request $request
     * @return Response
     */
    public function store(Request $request) {
        $arrErrores  = [];
        $dataRequest = [];
        $data = [];

        $this->user = auth()->user();

        // Válida que el usuario procesado exista y que pertenezca a la misma base de datos del usuario autenticado
        $usuario = User::select(['usu_id', 'usu_identificacion', 'bdd_id', 'bdd_id_rg'])
            ->where('estado', 'ACTIVO')
            ->where('usu_identificacion', $request->usu_identificacion)
            ->first();
        if ($usuario === null) {
            $arrErrores[] = "El Usuario con identificación [{$request->usu_identificacion}] no existe o se encuentra INACTIVO.";
        }

        if((!empty($this->user->bdd_id) && $this->user->bdd_id != $usuario->bdd_id) &&
            (!empty($this->user->bdd_id_rg) && $this->user->bdd_id_rg != $usuario->bdd_id_rg)) 
        {
            $arrErrores[] = "El Usuario con identificación [{$request->usu_identificacion}] no válido.";
        }

        // Válida qu no exista creado un registro para el usuario 
        $existe = $this->className::where('usu_id', $usuario->usu_id)
            ->first();

        if ($existe) {
            $arrErrores[] = "El Usuario de openECM con identificación [{$request->usu_identificacion}] ya existe.";
        }

        $informacionEcm = $request->informacion_ecm;;
        // Válida que existan datos de información ECM (Ofe y Rol Ecm)
        if (!empty($informacionEcm)) {
            // Realiza las validaciones de los datos cargados a nivel de información ECM
            $respuesta = $this->validarDatos($informacionEcm, $usuario);

            $arrErrorValidar = $respuesta['arrErrores'];
            $dataRequest     = $respuesta['dataRequest'];
            $dataOfes        = $respuesta['dataOfes'];
        } else {
            return response()->json([
                'message' => 'Error al crear el Usuario ECM',
                'errors' => ["Datos Incompletos, no se envió el Oferente y el Rol de ECM"]
            ], 404);
        }

        $arrErrores = array_merge($arrErrores, $arrErrorValidar);

        if (empty($arrErrores)) {
            $arrErroresGuardar = [];

            // Se agrupan los ofes por la url api y se válida que tengan asignado el mismo Rol y la misma BD de openECM
            $respuesta = $this->agruparValidarOfes($dataOfes, $usuario, $informacionEcm, "ACTIVO");
            $arrAgrupaApiUrl   = $respuesta['arrAgrupaApiUrl'];
            $arrAgrupaOfe      = $respuesta['arrAgrupaOfe'];
            $arrErroresGuardar = $respuesta['arrErroresAgrupa'];

            if (empty($arrErroresGuardar)) {
                // Se crean los registros en openETL uno por cada OFE
                for ($i=0; $i < count($dataRequest); $i++) {
                    $data['usu_id']  = $usuario->usu_id;
                    $data['ofe_id']  = $dataRequest[$i]['ofe_id'];
                    $data['use_rol'] = $dataRequest[$i]['use_rol'];
                    $data['estado']  = 'ACTIVO';
                    $data['usuario_creacion'] = auth()->user()->usu_id;
                    $obj = $this->className::create($data);
        
                    if(!$obj){
                        $arrErroresGuardar[] = "No fue posible crear el Usuario de openECM con identificación [{$request->usu_identificacion}]";
                    } else {
                        $dataOfes[]  = $dataRequest[$i]['ofe_identificacion'];
                    }
                }
            
                for ($i=0; $i < count($arrAgrupaApiUrl); $i++) { 
                    $arrOfesAgrupados = $arrAgrupaOfe[$arrAgrupaApiUrl[$i]];

                    foreach ($arrOfesAgrupados as $key => $value) {

                        $parametros  = [];
                        $login       = $this->loginECM($arrOfesAgrupados[$key]['ofe_identificacion']);
                        $endPointEcm = $this->endPointEcm('endpoint_usuarios');

                        // Se consulta la información del usuario para guardarla en openECM
                        $user = User::select(['usu_id', 'usu_identificacion', 'usu_email', 'usu_nombre'])
                            ->where('estado', 'ACTIVO')
                            ->where('usu_identificacion', $usuario->usu_identificacion)
                            ->first();

                        if ($user === null) {
                            $arrErrores[] = "El Usuario con identificación [{$usuario->usu_identificacion}] no existe o se encuentra INACTIVO.";
                        } else {
                            // Se construye el request para enviar a openECM
                            $parametros['usu_email']          = $user['usu_email'];
                            $parametros['usu_identificacion'] = $user['usu_identificacion'];
                            $parametros['usu_nombre']         = $user['usu_nombre'];
                            $parametros['rosIds']             = $arrOfesAgrupados[$key]['use_rol'];
                            $parametros['activar_usuario']    = "SI";

                            $respuesta = $this->enviarPeticionECM($login['url_api'], $endPointEcm, $login['token'], 'POST', 'form_params', $parametros);

                            if ($respuesta['status'] != '200' && $respuesta['status'] != '201') {
                                // Si se presentan errores se eliminan los registros previamente cargados a la tabla de etl_usuarios_ecm
                                return response()->json([
                                    'message' => 'Error al crear el Usuario en la plataforma openECM',
                                    'errors' => $respuesta['errors']
                                ], $respuesta['status']);
                            }
                        }
                        break;
                    }
                }
            }

            if (empty($arrErroresGuardar)) {
                return response()->json([
                    'success' => true
                ], 200);
            } else {
                // Si se presentan errores se eliminan los registros previamente cargados a la tabla de etl_usuarios_ecm
                return response()->json([
                    'message' => 'Error al crear el Usuario de openECM',
                    'errors' => $arrErroresGuardar
                ], 422);
            }
        } else {
            return response()->json([
                'message' => 'Error al crear el Usuario de openECM',
                'errors' => $arrErrores
            ], 422);
        }
    }

    /**
     * Actualiza el usuario openECM especificado en el almacenamiento.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id $id Identificador del registro procesado
     * @return Response
     */
    public function update(Request $request, $id){
        $arrErrores  = [];
        $dataRequest = [];
        $data = [];

        // Válida que el usuario procesado exista y que pertenezca a la misma base de datos del usuario autenticado
        $usuario = User::select(['usu_id', 'usu_identificacion', 'bdd_id', 'bdd_id_rg'])
            ->where('usu_identificacion', $id)
            ->first();
        if ($usuario === null) {
            $arrErrores[] = "El Usuario con identificación [{$id}] no existe.";
        }

        $informacionEcm = $request->informacion_ecm;

        // Válida que existan datos de información ECM (Ofe y Rol Ecm)
        if (!empty($informacionEcm)) {
            // Realiza las validaciones de los datos cargados a nivel de información ECM
            $respuesta = $this->validarDatos($informacionEcm, $usuario);

            $arrErrorValidar = $respuesta['arrErrores'];
            $dataRequest     = $respuesta['dataRequest'];
            $dataOfes        = $respuesta['dataOfes'];

        } else {
            return response()->json([
                'message' => 'Error al crear el Usuario ECM',
                'errors' => ["Datos Incompletos, no se envió el Oferente y el Rol de ECM"]
            ], 404);
        }

        $arrErrores = array_merge($arrErrores, $arrErrorValidar);

        if (empty($arrErrores)) {
            $arrErroresGuardar = [];

            // Se agrupan los ofes por la url api y se válida que tengan asignado el mismo Rol y la misma BD de openECM
            $respuesta = $this->agruparValidarOfes($dataOfes, $usuario, $informacionEcm, $request->estado);
            $arrAgrupaApiUrl   = $respuesta['arrAgrupaApiUrl'];
            $arrAgrupaOfe      = $respuesta['arrAgrupaOfe'];
            $arrErroresGuardar = $respuesta['arrErroresAgrupa'];

            if (empty($arrErroresGuardar)) {
                $objUsuariosEcm = $this->className::where('usu_id', $usuario->usu_id)
                    ->get()
                    ->toArray();

                foreach ($objUsuariosEcm as $userEcm) {
                    $existeRegistro = false;
                    for ($i=0; $i < count($dataRequest); $i++) {
                        if ($dataRequest[$i]['ofe_id'] == $userEcm['ofe_id']) {
                            $existeRegistro = true;
                        }
                    }

                    if (!$existeRegistro) {
                        $delete = $this->className::where('usu_id', $usuario->usu_id)
                            ->where('ofe_id', $userEcm['ofe_id'])
                            ->where('use_rol', $userEcm['use_rol'])
                            ->delete();
                    }
                }

                // Se crean o actualizan los registros en openETL uno por cada OFE
                for ($i=0; $i < count($dataRequest); $i++) {
                    // Validá si ya existe el registro 
                    $usuarioEcmExiste = $this->className::select(['use_id', 'ofe_id', 'use_rol'])
                        ->where('ofe_id', $dataRequest[$i]['ofe_id'])
                        ->where('usu_id', $usuario->usu_id)
                        ->first();

                    if ($usuarioEcmExiste) {
                        $data['use_rol'] = $dataRequest[$i]['use_rol'];
                        $obj = $usuarioEcmExiste->update($data);
                    } else {
                        $data['usu_id']  = $usuario->usu_id;
                        $data['ofe_id']  = $dataRequest[$i]['ofe_id'];
                        $data['use_rol'] = $dataRequest[$i]['use_rol'];
                        $data['estado']  = 'ACTIVO';
                        $data['usuario_creacion'] = auth()->user()->usu_id;
                        $obj = $this->className::create($data);
                    }

                    if(!$obj){
                        $arrErroresGuardar[] = "No fue posible crear/actualizar el Usuario de openECM con identificación [{$request->usu_identificacion}]";
                    } else {
                        $dataOfes[] = $dataRequest[$i]['ofe_identificacion'];
                    }
                }

                for ($i=0; $i < count($arrAgrupaApiUrl); $i++) { 
                    $arrOfesAgrupados = $arrAgrupaOfe[$arrAgrupaApiUrl[$i]];

                    foreach ($arrOfesAgrupados as $key => $value) {
                        $parametros  = [];
                        $login       = $this->loginECM($arrOfesAgrupados[$key]['ofe_identificacion']);
                        $endPointEcm = $this->endPointEcm('endpoint_usuarios');

                        // Se consulta la información del usuario para guardarla en openECM
                        $user = User::select(['usu_id', 'usu_identificacion', 'usu_email', 'usu_nombre'])
                            ->where('usu_identificacion', $usuario->usu_identificacion)
                            ->first();

                        if ($user === null) {
                            $arrErrores[] = "El Usuario {$usuario->usu_identificacion} no existe o se encuentra INACTIVO.";
                        } else {
                            // Se construye el request para enviar a openECM
                            $parametros['usu_email']          = $user['usu_email'];
                            $parametros['usu_identificacion'] = $user['usu_identificacion'];
                            $parametros['usu_nombre']         = $user['usu_nombre'];
                            $parametros['rosIds']             = $arrOfesAgrupados[$key]['use_rol'];
                            $parametros['activar_usuario']    = ($arrOfesAgrupados[$key]['estado'] == 'ACTIVO') ? 'SI' : 'NO';

                            $respuesta = $this->enviarPeticionECM($login['url_api'], $endPointEcm, $login['token'], 'POST', 'form_params', $parametros);

                            if ($respuesta['status'] != '200' && $respuesta['status'] != '201') {
                                return response()->json([
                                    'message' => 'Error al crear el Usuario en la plataforma openECM',
                                    'errors' => $respuesta['errors']
                                ], $respuesta['status']);
                            }
                        }
                        break;
                    }
                }
            }

            if (empty($arrErroresGuardar)) {
                return response()->json([
                    'success' => true
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Error al crear el Usuario de openECM',
                    'errors' => $arrErroresGuardar
                ], 422);
            }
        } else {
            return response()->json([
                'message' => 'Error al crear el Usuario de openECM',
                'errors' => $arrErrores
            ], 422);
        }
    }

    /**
     * Realiza las validaciones de los datos cargados a nivel de información ECM.
     *
     * @param  array  $informacionEcm Contiene la información ECM
     * @param  array  $usuario Contiene la información del usuario procesado
     * @return array  Retorna los errores y la información de los ofes procesados
     */
    public function validarDatos($informacionEcm, $usuario) {
        $ofeRepetido = [];
        $dataOfes    = [];
        $arrErrores  = [];
        $dataRequest = [];

        $intCantidadRegistros = 0;
        for ($i=0; $i < count($informacionEcm); $i++) { 
            $intCantidadRegistros++;

            // Válida que el ofe procesado exista y que pertenezca a la misma base de datos del usuario autenticado
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
                ->where('ofe_identificacion', $informacionEcm[$i]['ofe_identificacion'])
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if ($ofe === null) {
                $arrErrores[] = "El OFE con identificación [{$informacionEcm[$i]['ofe_identificacion']}] no existe o se encuentra INACTIVO.";
            }

            if (in_array($informacionEcm[$i]['ofe_identificacion'], $ofeRepetido)) {
                $arrErrores[] = "El OFE en la grilla [{$intCantidadRegistros}], ya existe en las filas anteriores.";
            }
            $ofeRepetido[] = $informacionEcm[$i]['ofe_identificacion'];

            if (!is_numeric($informacionEcm[$i]['use_rol'])) {
                $arrErrores[] = "El Rol de ECM en la grilla [{$intCantidadRegistros}] debe ser numérico.";
            }

            if(empty($arrErrores)) {
                $dataRequest[$i]['ofe_id']  = $ofe['ofe_id'];
                $dataRequest[$i]['ofe_identificacion'] = $ofe['ofe_identificacion'];
                $dataRequest[$i]['use_rol'] = $informacionEcm[$i]['use_rol'];
                $dataOfes[] = $ofe['ofe_identificacion'];
            }
        }

        $data['arrErrores']  = $arrErrores;
        $data['dataRequest'] = $dataRequest;
        $data['dataOfes']    = $dataOfes;

        return $data;
    }

    /**
     * Agrupa los Ofes procesados por la url api de conexión a openECM.
     * 
     * Válida que los ofes tengan el mismo rol y pertenezcan a la misma base de datos.
     *
     * @param  array  $dataOfes Contiene la información de los ofes procesados
     * @param  array  $usuario Contiene la información del usuario procesado
     * @param  array  $arrInfomracionEcm Contiene la información de los ofes y el rol
     * @param  string $estado Contiene el estado del registro
     * @return array  Retorna los errores y los ofes agrupados
     */
    public function agruparValidarOfes($dataOfes, $usuario, $arrInfomracionEcm, $estado = "") {

        // Se agrupan los OFEs por la url api de conexión a openECM
        $arrOfes = [];
        $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_integracion_ecm_conexion'])
            ->where('estado', 'ACTIVO')
            ->whereIn('ofe_identificacion', $dataOfes)
            ->get();

        $arrAgrupaApiUrl = [];
        $key = 0;
        foreach($ofes as $ofe) {
            $ecmConexion = json_decode($ofe->ofe_integracion_ecm_conexion, true);
            if (!in_array($ecmConexion['url_api'], $arrAgrupaApiUrl)) {
                $arrAgrupaApiUrl[] = $ecmConexion['url_api'];
            }

            // Si llega la información de la opción cargar Excel, se recorre el array enviado para obtener el rol
            foreach ($arrInfomracionEcm as $dataEcm) {
                if ($ofe['ofe_identificacion'] == $dataEcm['ofe_identificacion']) {
                    $usuarioECM['use_rol'] = $dataEcm['use_rol'];
                    $usuarioECM['estado']  = "";
                }
            }

            $arrOfes["{$ecmConexion['url_api']}"][$key]['ofe_identificacion'] = $ofe['ofe_identificacion'];
            $arrOfes["{$ecmConexion['url_api']}"][$key]['use_rol']            = $usuarioECM['use_rol'];
            $arrOfes["{$ecmConexion['url_api']}"][$key]['url_api']            = $ecmConexion['url_api'];
            $arrOfes["{$ecmConexion['url_api']}"][$key]['estado']             = $estado;
            $arrOfes["{$ecmConexion['url_api']}"][$key]['bdd_id_ecm']         = $ecmConexion['bdd_id_ecm'];
            $key++;
        }

        $arrErroresAgrupa = [];
        $arrAgrupaOfe = [];
        for ($i=0; $i < count($arrAgrupaApiUrl); $i++) { 
            $ofesAgrupados = $arrOfes[$arrAgrupaApiUrl[$i]];

            $arrCompararRol = [];
            $arrCompararBD  = [];
            $strMsjError = '';
            $existeRol = false;
            $existeBD  = false;

            $j = 0;
            foreach ($ofesAgrupados as $key => $value) {
                if (!in_array($ofesAgrupados[$key]['use_rol'], $arrCompararRol) && $j > 0) {
                    $existeRol = true;
                }

                if (!in_array($ofesAgrupados[$key]['bdd_id_ecm'], $arrCompararBD)  && $j > 0) {
                    $existeBD = true;
                }

                $arrCompararRol[] = $ofesAgrupados[$key]['use_rol'];
                $arrCompararBD[]  = $ofesAgrupados[$key]['bdd_id_ecm'];

                $strMsjError .= $ofesAgrupados[$key]['ofe_identificacion'].", ";
                
                $j++;
            }

            $arrAgrupaOfe[] = $ofesAgrupados;
            if ($existeRol) {
                $arrErroresAgrupa[] = "Los OFEs con Identificación [".rtrim($strMsjError,', ')."] deben tener el mismo ROL.";
            }

            if ($existeBD) {
                $arrErroresAgrupa[] = "Los OFEs con Identificación [".rtrim($strMsjError,', ')."] deben tener la misma Base de Datos de ECM asignada.";
            }
        }

        $data['arrAgrupaApiUrl']  = $arrAgrupaApiUrl;
        $data['arrAgrupaOfe']     = $arrOfes;
        $data['arrErroresAgrupa'] = $arrErroresAgrupa;

        return $data;
    }

    /**
     * Muestra una lista de usuarios openECM.
     *
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function getListaUsuariosEcm(Request $request) {
        $user = auth()->user();

        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'etl_usuarios_ecm.use_id',
            'etl_usuarios_ecm.ofe_id',
            'etl_usuarios_ecm.usu_id',
            'etl_usuarios_ecm.use_rol',
            'etl_usuarios_ecm.usuario_creacion',
            'etl_usuarios_ecm.estado',
            'etl_usuarios_ecm.fecha_modificacion',
            'etl_usuarios_ecm.fecha_creacion'
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social',
            'getUsuarioEcm'
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
                [
                    'multiple' => true,
                    'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                    'fields' => [
                        [
                            'label' => 'NIT OFE',
                            'field' => 'ofe_identificacion'
                        ],
                        [
                            'label' => 'RAZON SOCIAL',
                            'field' => 'ofe_razon_social'
                        ]
                    ]
                ],
                [
                    'multiple' => false,
                    'relation' => 'getUsuarioEcm',
                    'fields' => [
                        [
                            'label' => 'IDENTIFICACION',
                            'field' => 'usu_identificacion'
                        ],
                    ]
                ],
                [
                    'multiple' => false,
                    'relation' => 'getUsuarioEcm',
                    'fields' => [
                        [
                            'label' => 'NOMBRE',
                            'field' => 'usu_nombre'
                        ],
                    ]
                ],
                [
                    'multiple' => false,
                    'relation' => 'getUsuarioEcm',
                    'fields' => [
                        [
                            'label' => 'EMAIL',
                            'field' => 'usu_email'
                        ],
                    ]
                ],
                'use_rol' => 'ROL OPENECM',
                'estado' =>  [
                    'label' => 'ESTADO OPENECM',
                ],
                [
                    'multiple' => false,
                    'relation' => 'getUsuarioEcm',
                    'fields' => [
                        [
                            'label' => 'ESTADO USUARIO',
                            'field' => 'estado'
                        ],
                    ]
                ],
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
                ]
            ],
            'titulo' => 'usuario_ecm'
        ];

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion_usuarios_ecm');
    }

    /**
     * Genera una Interfaz de usuarios openECM para guardar en Excel.
     *
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceUsuarioEcm() {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'usuario_ecm');  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de usuarios openECM.
     *
     * @param Request $request
     * @return
     * @throws \Exception
     */
    public function cargarUsuarioEcm(Request $request){
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
                    'message' => 'Errores al guardar los Usuarios openECM',
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
                        $columna['estado_openecm'],
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
                    'message' => 'Errores al guardar los Usuarios openECM',
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores = [];
            $arrResultado = [];
            $arrExisteOfe = [];
            $arrExisteUsuario = [];
            $arrExisteUsuarioOpenEcm = [];
            $arrOfeRepetidos = [];
            $arrRolesEcm = [];
            $dataUsuarios = [];

            foreach ($data as $fila => $columnas) {

                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrExisteUsuarioOpenEcm = [];
                $arrFaltantes = $this->checkFields($Acolumnas, [
                    'nit_ofe',
                    'identificacion',
                    'email',
                    'rol_openecm',
                ], $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {
                    //nit_ofe
                    $arrUsuarioOpenEcm['ofe_id'] = 0;
                    $arrUsuarioOpenEcm['nit_ofe'] = $columnas->nit_ofe;
                    if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)){
                        $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                    } else {

                        $objExisteOfe = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $columnas->nit_ofe)
                            ->validarAsociacionBaseDatos()
                            ->where('estado', 'ACTIVO')
                            ->first();

                        $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;

                        // Se obtiene la informacion de los roles a partir del oferente
                        $parametros = [];
                        $login = $this->loginECM($columnas->nit_ofe);
                        $endPointEcm = $this->endPointEcm('endpoint_roles');

                        $parametros['buscarId']          = '';
                        $parametros['buscarDescripcion'] = '';
                        $parametros['buscarExacto']      = 'NO';

                        $respuesta = $this->enviarPeticionECM($login['url_api'], $endPointEcm, $login['token'], 'POST', 'form_params', $parametros);
                        
                        if (!empty($respuesta['errors'])) {
                            $arrErrores = $this->adicionarError($arrErrores, $respuesta['errors'], $fila);
                        } else {
                            foreach($respuesta['data'] as $rol){
                                $arrRolesEcm[$columnas->nit_ofe][] = $rol['ros_id'];
                            }
                        }
                    }

                    // Válida si ya existe un registro repetido para el usuario y el ofe
                    if (in_array($columnas->identificacion.'-'.$columnas->nit_ofe, $arrOfeRepetidos)) {
                        $arrErrores = $this->adicionarError($arrErrores, ["El Usuario [{$columnas->identificacion}] ya tiene asignado el Ofe [{$columnas->nit_ofe}]."], $fila);
                    }
                    $arrOfeRepetidos[] = $columnas->identificacion.'-'.$columnas->nit_ofe;

                    if (empty($objExisteOfe)){
                        $arrErrores = $this->adicionarError($arrErrores, ["El Nit del Ofe [{$columnas->nit_ofe}] no existe o se encuentra INACTIVO."], $fila);
                    }

                    //usu_identificacion
                    $arrUsuarioOpenEcm['usu_id'] = 0;
                    $arrUsuarioOpenEcm['identificacion'] = $columnas->identificacion;
                    if (array_key_exists($columnas->identificacion, $arrExisteUsuario)){
                        $objExisteUsuario = $arrExisteUsuario[$columnas->identificacion];
                    } else {
                        $objExisteUsuario = User::where('estado', 'ACTIVO')->where('usu_identificacion', $columnas->identificacion)
                            ->first();
                        $arrExisteUsuario[$columnas->identificacion] = $objExisteUsuario;
                    }

                    if (empty($objExisteUsuario)) {
                        $arrErrores = $this->adicionarError($arrErrores, ["El Usuario [{$columnas->identificacion}] no existe o se encuentra INACTIVO."], $fila);
                    }

                    // Si el usuario autenticado tiene base de datos asignada
                    if(!empty($objUser->bdd_id_rg) && $objUser->bdd_id_rg != $objExisteUsuario->bdd_id_rg &&
                        !empty($objUser->bdd_id) && $objUser->bdd_id != $objExisteUsuario->bdd_id )
                    {
                        $arrErrores = $this->adicionarError($arrErrores, ['El Usuario ' . $columnas->identificacion . ' no válido.'], $fila);
                    }

                    $arrUsuarioOpenEcm['use_id'] = 0;
                    if (array_key_exists($columnas->nit_ofe.'-'.$columnas->identificacion, $arrExisteUsuarioOpenEcm)){
                        $objExisteUsuarioOpenEcm = $arrExisteUsuarioOpenEcm[$columnas->nit_ofe.'-'.$columnas->identificacion];
                        $arrUsuarioOpenEcm['use_id'] = $objExisteUsuarioOpenEcm->use_id;
                    } else {
                        if (!empty($objExisteUsuario) && !empty($objExisteOfe)) {
                            $objExisteUsuarioOpenEcm = $this->className::where('ofe_id', $objExisteOfe->ofe_id)->where('usu_id', $objExisteUsuario->usu_id)
                            ->first();

                            if ($objExisteUsuarioOpenEcm){
                                $arrExisteUsuarioOpenEcm[$columnas->nit_ofe.'-'.$columnas->identificacion] = $objExisteUsuarioOpenEcm;
                                $arrUsuarioOpenEcm['use_id'] = $objExisteUsuarioOpenEcm->use_id;
                            }
                        }
                    }

                    if (property_exists($columnas, 'rol_openecm') && isset($columnas->rol_openecm)) {
                        if (!in_array($columnas->rol_openecm, $arrRolesEcm[$objExisteOfe->ofe_identificacion])) {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Rol con ID ' . $columnas->rol_openecm . ' no existe en openECM.'], $fila);
                        }
                    }

                    if (!empty($objExisteUsuario) && !empty($objExisteOfe)) {
                        $existeUse = $this->className::select(['use_id'])
                            ->where('ofe_id', $objExisteOfe->ofe_id)
                            ->where('usu_id', $objExisteUsuario->usu_id)
                            ->first();
                        if (!$existeUse) {
                            $arrUsuarioOpenEcm['ofe_id'] = $objExisteOfe->ofe_id;
                            $arrUsuarioOpenEcm['usu_id'] = $objExisteUsuario->usu_id;
                        }
                    }

                    $arrUsuarioOpenEcm['use_rol']  = $this->sanitizarStrings($columnas->rol_openecm);

                    if(count($arrErrores) == 0){

                        $dataEcm['use_rol'] = $columnas->rol_openecm;
                        $dataEcm['ofe_identificacion'] = $columnas->nit_ofe;
                        $dataUsuarios[$columnas->identificacion][] = $dataEcm;

                        $objValidator = Validator::make($arrUsuarioOpenEcm, $this->className::$rulesUpdate);
                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrUsuarioOpenEcm;
                        }
                    }
                }
                if ($fila % 500 === 0)
                    $this->renovarConexion($objUser);
            }

            if(empty($arrErrores)){
                // $dataUsuarios = [];
                $bloque_usuario_ecm = [];
                foreach ($arrResultado as $usuario_ecm) {
                    $data = [
                        'use_id' => $usuario_ecm['use_id'],
                        'use_rol' => !empty($usuario_ecm['use_rol']) ? $this->sanitizarStrings($usuario_ecm['use_rol']): null,
                    ];

                    if (isset($usuario_ecm['ofe_id']) && $usuario_ecm['ofe_id'] !== 0 && isset($usuario_ecm['usu_id']) && $usuario_ecm['usu_id'] !== 0) {
                        $data['ofe_id'] = $usuario_ecm['ofe_id'];
                        $data['usu_id'] = $usuario_ecm['usu_id'];
                    }

                    // $dataUsuarios[$usuario_ecm['identificacion']] = $usuario_ecm['identificacion'];
                    array_push($bloque_usuario_ecm, $data);
                }

                if (!empty($bloque_usuario_ecm)) {
                    $bloques = array_chunk($bloque_usuario_ecm, 100);
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
                                'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_USUECM,
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

                // Cargar usuario a la plataforma de openECM
                foreach ($dataUsuarios as $id => $arrInfomracionEcm) {

                    $usuario = User::select(['usu_id', 'usu_identificacion', 'usu_email', 'usu_nombre', 'estado'])
                        ->where('usu_identificacion', $id)
                        ->first();

                    // Realiza las validaciones de los datos cargados a nivel de información ECM
                    $respuesta = $this->validarDatos($arrInfomracionEcm, $usuario);

                    $arrErrorValidar = $respuesta['arrErrores'];
                    $dataRequest     = $respuesta['dataRequest'];
                    $dataOfes        = $respuesta['dataOfes'];

                    $arrErrores = array_merge($arrErrores, $arrErrorValidar);

                    if (empty($arrErrores)) {
                        $arrErroresGuardar = [];

                        if (empty($arrErroresGuardar)) {
                            // Se agrupan los ofes por la url api y se válida que tengan asignado el mismo Rol y la misma BD de openECM
                            $respuesta = $this->agruparValidarOfes($dataOfes, $usuario, $arrInfomracionEcm);
                            $arrAgrupaApiUrl  = $respuesta['arrAgrupaApiUrl'];
                            $arrAgrupaOfe     = $respuesta['arrAgrupaOfe'];
                            $arrErroresAgrupa = $respuesta['arrErroresAgrupa'];
            
                            if (empty($arrErroresAgrupa)) {
                                for ($i=0; $i < count($arrAgrupaApiUrl); $i++) { 
                                    $arrOfesAgrupados = $arrAgrupaOfe[$arrAgrupaApiUrl[$i]];
            
                                    foreach ($arrOfesAgrupados as $key => $value) {
                                        $parametros  = [];
                                        $login       = $this->loginECM($arrOfesAgrupados[$key]['ofe_identificacion']);
                                        $endPointEcm = $this->endPointEcm('endpoint_usuarios');
            
                                        // Se consulta la información del usuario para guardarla en openECM
                                        $user = User::select(['usu_id', 'usu_identificacion', 'usu_email', 'usu_nombre'])
                                            ->where('usu_identificacion', $usuario->usu_identificacion)
                                            ->first();
            
                                        if ($user === null) {
                                            $arrErrores[] = "El Usuario {$usuario->usu_identificacion} no existe o se encuentra INACTIVO.";
                                        } else {
                                            // Se construye el request para enviar a openECM
                                            $parametros['usu_email']          = $user['usu_email'];
                                            $parametros['usu_identificacion'] = $user['usu_identificacion'];
                                            $parametros['usu_nombre']         = $user['usu_nombre'];
                                            $parametros['rosIds']             = $arrOfesAgrupados[$key]['use_rol'];
                                            $parametros['activar_usuario']    = 'SI';

                                            $respuesta = $this->enviarPeticionECM($login['url_api'], $endPointEcm, $login['token'], 'POST', 'form_params', $parametros);

                                            if ($respuesta['status'] != '200' && $respuesta['status'] != '201') {
                                                return response()->json([
                                                    'message' => 'Error al crear el Usuario en la plataforma openECM',
                                                    'errors' => $respuesta['errors']
                                                ], $respuesta['status']);
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                        $arrErrores = array_merge($arrErroresGuardar, $arrErroresAgrupa);
                    }
                }
                // Fin Cargar usuario a la plataforma de openECM

                return response()->json([
                    'success' => true
                ], 200);
            }

            // Si se presentan errores se genera el agendamiento
            if (!empty($arrErrores)) {
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
                    'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_USUECM,
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
                    'message' => 'Errores al guardar los Usuarios de openECM',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            }
        } else {
            return response()->json([
                'message' => 'Errores al guardar los Usuarios de openECM',
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de usuarios openECM.
     * 
     * @return Response
     */
    public function getListaErroresUsuarioEcm(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_USUECM);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de usuarios openECM.
     *
     * @return Response
     */
    public function descargarListaErroresUsuarioEcm(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_USUECM, true, 'carga_usuario_ecm_log_errores');
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
     * Efectua un proceso de busqueda en la parametrica usuarios openECM.
     *
     * @param Request $request
     * @return Response
     */
    public function busqueda(Request $request) {
        $columnas = [
            'use_id',
            'ofe_id',
            'usu_id',
            'use_rol',
            'estado'
        ];
        $incluir = [
            'getUsuarioCreacion',
            'getUsuarioEcm',
            'getConfiguracionObligadoFacturarElectronicamente'
        ];

        return $this->procesadorBusqueda($request, $columnas, $incluir);
    }

    /**
     * Cambia el estado de los usuarios openECM en el almacenamiento.
     *
     * @param Request $request
     * @return Response
     */
    public function cambiarEstado(Request $request) {
        $arrErrores = [];
        $usuariosId = explode(',', $request->usuarios);

        if (!empty($usuariosId)) {
            foreach ($usuariosId as $usuario) {

                // Válida que el usuario procesado exista
                $user = User::select(['usu_id', 'usu_identificacion', 'usu_email', 'usu_nombre'])
                    ->where('usu_identificacion', trim($usuario))
                    ->first();

                if ($user === null) {
                    $arrErrores[] = "El Usuario {$usuario} no existe.";
                } else {

                    $dataOfes = [];
                    $usuariosECM = $this->className::select(['use_id', 'ofe_id', 'estado'])
                        ->where('usu_id', $user->usu_id)
                        ->get()
                        ->map(function($dataUser) use (&$dataOfes) {
                            $data['estado'] = ($dataUser->estado == 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
                            $dataUser->update($data);
                            $dataOfes[] = $dataUser->ofe_id;
                        });

                    $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_integracion_ecm_conexion'])
                        ->where('estado', 'ACTIVO')
                        ->whereIn('ofe_id', $dataOfes)
                        ->get();

                    $arrAgrupaApiUrl = [];
                    $key = 0;
                    foreach($ofes as $ofe) {
                        $ecmConexion = json_decode($ofe->ofe_integracion_ecm_conexion, true);
                        if (!in_array($ecmConexion['url_api'], $arrAgrupaApiUrl)) {
                            $arrAgrupaApiUrl[] = $ecmConexion['url_api'];
                        }

                        $usuarioECM = $this->className::select(['ofe_id', 'use_rol', 'estado'])
                            ->where('ofe_id', $ofe['ofe_id'])
                            ->where('usu_id', $user->usu_id)
                            ->first();

                        $arrOfes["{$ecmConexion['url_api']}"][$key]['ofe_identificacion'] = $ofe['ofe_identificacion'];
                        $arrOfes["{$ecmConexion['url_api']}"][$key]['use_rol']            = $usuarioECM['use_rol'];
                        $arrOfes["{$ecmConexion['url_api']}"][$key]['url_api']            = $ecmConexion['url_api'];
                        $arrOfes["{$ecmConexion['url_api']}"][$key]['estado']             = $usuarioECM['estado'];
                        $arrOfes["{$ecmConexion['url_api']}"][$key]['bdd_id_ecm']         = $ecmConexion['bdd_id_ecm'];
                        $key++;
                    }

                    $arrAgrupaOfe = [];
                    for ($i=0; $i < count($arrAgrupaApiUrl); $i++) { 
                        $arrAgrupaOfe = $arrOfes[$arrAgrupaApiUrl[$i]];
                    }

                    for ($i=0; $i < count($arrAgrupaApiUrl); $i++) { 
                        foreach ($arrAgrupaOfe as $key => $value) {

                            $parametros  = [];
                            $login       = $this->loginECM($arrAgrupaOfe[$key]['ofe_identificacion']);
                            $endPointEcm = $this->endPointEcm('endpoint_usuarios');

                            // Se construye el request para enviar a openECM
                            $parametros['usu_email']          = $user->usu_email;
                            $parametros['usu_identificacion'] = $user->usu_identificacion;
                            $parametros['usu_nombre']         = $user->usu_nombre;
                            $parametros['rosIds']             = $arrAgrupaOfe[$key]['use_rol'];
                            $parametros['activar_usuario']    = ($arrAgrupaOfe[$key]['estado'] == 'ACTIVO') ? 'SI' : 'NO';

                            $respuesta = $this->enviarPeticionECM($login['url_api'], $endPointEcm, $login['token'], 'POST', 'form_params', $parametros);

                            if ($respuesta['status'] != '200' && $respuesta['status'] != '201') {
                                return response()->json([
                                    'message' => 'Error al cambiar el estado del Usuario en la plataforma openECM',
                                    'errors' => $respuesta['errors']
                                ], $respuesta['status']);
                            }

                            break;
                        }
                    }
                }
            }

            if(!empty($arrErrores)){
                return response()->json([
                    'message' => 'Error al cambiar el estado de los Usuarios de openECM',
                    'errors' => $arrErrores
                ], 422);
            } else {
                return response()->json([
                    'message' => 'Se cambio el estado exitosamente.',
                ], 201);
            }
        }
    }
}