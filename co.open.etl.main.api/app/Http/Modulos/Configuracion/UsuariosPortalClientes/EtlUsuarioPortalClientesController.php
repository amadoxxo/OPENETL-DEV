<?php
namespace App\Http\Modulos\Configuracion\UsuariosPortalClientes;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantTrait;
use App\Http\Controllers\OpenTenantController;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Configuracion\UsuariosPortalClientes\EtlUsuarioPortalClientes;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlUsuarioPortalClientesController extends OpenTenantController {
    /**
     * Modelo relacionado a la paramétrica.
     *
     * @var Model
     */
    public $className = EtlUsuarioPortalClientes::class;

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
        'OFE',
        'NIT ADQUIRENTE',
        'ADQUIRENTE',
        'IDENTIFICACION',
        'NOMBRE',
        'CORREO',
        'ESTADO'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionAdquirentes,ConfiguracionAdquirentesNuevo,ConfiguracionAdquirentesEditar,ConfiguracionAdquirentesSubir'
        ])->only([
            'actualizarUsuariosPortales',
            'actualizarEstadoUsuarioPortales',
            'descargarListaUsuariosPortales',
            'generarInterfaceUsuariosPortalClientes',
            'cargarUsuariosPortalClientes',
            'administrarUsuariosPortales'
        ]);
    }

    /**
     * Obtiene las colecciones correspondientes a OFE y Adquirente.
     *
     * @param string $ofe_identificacion Identificación del OFE
     * @param string $adq_identificacion Identificación del Adquirente
     * @param string $adq_id_personalizado ID personalizado del Adquirente
     * @return array Conteniendo las colecciones de OFE y Adquirente
     */
    private function consultarOfeAdquirente($ofe_identificacion, $adq_identificacion, $adq_id_personalizado = null) {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_identificador_unico_adquirente'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        $adquirente = null; 
        if($ofe) {
            $adquirente = ConfiguracionAdquirente::select(['adq_id', 'ofe_id', 'adq_identificacion'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('adq_identificacion', $adq_identificacion)
                ->where('estado', 'ACTIVO');

            // Verifica la configuración de la llave de adquirentes que tiene el OFE
            if(
                !empty($ofe->ofe_identificador_unico_adquirente) &&
                in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)
            ) {
                if (!empty($adq_id_personalizado))
                    $adquirente = $adquirente->where('adq_id_personalizado', $adq_id_personalizado);
                else
                    $adquirente = $adquirente->whereNull('adq_id_personalizado');
            }
                
            $adquirente = $adquirente->first();
        }

        return [
            'ofe'        => $ofe,
            'adquirente' => $adquirente
        ];
    }

    /**
     * Verifica que si existen datos repetidos en la petición.
     * 
     * La verificación se hace sobre las identificaciones y correos de los usuaros.
     * 
     * @param array $usuariosPortalClientes Array de usuarios del portal clientes que se recibe en la petición
     * @return array Conteniendo información de los usuarios repetidos luego de las verificaciones
     */
    private function verificarDatosRepetidos($usuariosPortalClientes) {
        $correos           = [];
        $identificaciones  = [];
        $usuariosRepetidos = [];

        foreach($usuariosPortalClientes as $usuario) {
            $errores = [];
            if(!in_array($usuario['identificacion'], $identificaciones))
                $identificaciones[] = $usuario['identificacion'];
            else
                $errores[] = 'Identificación repetida';

            if(!in_array($usuario['correo'], $correos))
                $correos[] = $usuario['correo'];
            else
                $errores[] = 'Correo repetido';

            if(!empty($errores)) {
                $usuario['errors'] = 'Error el procesar el usuario: [' . implode(' // ' , $errores) . ']';
                $usuariosRepetidos[] = $usuario;
            }

        }

        return [
            'usuariosRepetidos' => $usuariosRepetidos
        ];
    }

    /**
     * Administra los usuarios de portal clientes.
     * 
     * Permite la creación o la actualización de estados de los usuarios de portal clientes.
     *
     * @param Request $request
     * @return Response
     */
    public function administrarUsuariosPortales(Request $request) {
        if(
            !$request->has('usuarios_portal_clientes') || 
            ($request->has('usuarios_portal_clientes') && empty($request->usuarios_portal_clientes)) ||
            ($request->has('usuarios_portal_clientes') && !is_array($request->usuarios_portal_clientes))
        ) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['La petición está mal formadada, no se envió el parámetro usuarios_portal_clientes o no es un array de objetos']
            ], 422);
        }
            
        $user      =  auth()->user();
        $resultado = [
            'message'             => '',
            'usuarios_procesados' => [],
            'usuarios_fallidos'   => []
        ];

        // Verifica que dentro de los datos de la petición no se envién identificaciones y/o correos repetidos
        $datosRepetidos = $this->verificarDatosRepetidos($request->usuarios_portal_clientes);
        if(!empty($datosRepetidos['usuariosRepetidos'])) {
            $resultado['message']           = 'Se encontraron ' . count($datosRepetidos['usuariosRepetidos']) . ' Usuario(s) con datos repetidos, no se procesó ningún registro en la petición';
            $resultado['usuarios_fallidos'] = $datosRepetidos['usuariosRepetidos'];

            return response()->json($resultado, 200);
        }

        // Se reordena el array de usuarios para colocal en primer lugar los que se deben inactivar
        $usuariosPortalClientes = collect($request->usuarios_portal_clientes)->sortBy('estado')->reverse()->toArray();

        foreach($usuariosPortalClientes as $usuario) {
            try {
                $arrErrores = [];

                $ofeAdquirente = $this->consultarOfeAdquirente(
                    $usuario['ofe_identificacion'],
                    $usuario['adq_identificacion'],
                    (array_key_exists('adq_id_personalizado', $usuario) && !empty($usuario['adq_id_personalizado']) ? $usuario['adq_id_personalizado'] : null)
                );

                $ofe        = $ofeAdquirente['ofe'];
                $adquirente = $ofeAdquirente['adquirente'];

                if(!$ofe)
                    $arrErrores[] = 'El OFE [' . $usuario['ofe_identificacion'] . '] del usuario no existe o se encuentra inactivo';
                    
                if(!$adquirente)
                    $arrErrores[] = 'El Adquirente [' . $usuario['adq_identificacion'] . (array_key_exists('adq_id_personalizado', $usuario) && !empty($usuario['adq_id_personalizado']) ? ' - ' . $usuario['adq_id_personalizado'] : '') . '] para el OFE [' . $usuario['ofe_identificacion'] . '] del usuario no existe o se encuentra inactivo';

                if($ofe && $adquirente) {
                    $token = auth()->login($user);

                    $newRequest = new Request();
                    $newRequest->headers->add(['Authorization' => 'Bearer ' . $token]);
                    $newRequest->headers->add(['accept' => 'application/json']);
                    $newRequest->headers->add(['content-type' => 'application/json']);
                    $newRequest->headers->add(['cache-control' => 'no-cache']);

                    $newRequest->merge([
                        'ofe_id' => $adquirente->ofe_id,
                        'adq_id' => $adquirente->adq_id
                    ]);

                    // Se verifica si el usuario existe o no como usuario de portal clientes para definir si se debe crear o actualizar su estado
                    $existe = EtlUsuarioPortalClientes::select(['upc_id'])
                        ->where('ofe_id', $ofe->ofe_id)
                        ->where('adq_id', $adquirente->adq_id)
                        ->where(function($query) use ($usuario) {
                            $query->where('upc_identificacion', $usuario['identificacion'])
                                ->orWhere('upc_correo', $usuario['correo']);
                        })
                        ->first();

                    if(!$existe) {
                        $newRequest->merge([
                            'usuariosNuevos' => [
                                [
                                    'identificacion' => $usuario['identificacion'],
                                    'nombre'         => $usuario['nombre'],
                                    'email'          => $usuario['correo'],
                                    'estado'         => $usuario['estado']
                                ]
                            ]
                        ]);
                        $procesamiento = $this->actualizarUsuariosPortales($newRequest);
                    } else {
                        $newRequest->merge([
                            'upc_id'         => $existe->upc_id,
                            'identificacion' => $usuario['identificacion'],
                            'nombre'         => $usuario['nombre'],
                            'email'          => $usuario['correo'],
                            'estado'         => $usuario['estado']
                        ]);

                        $opcion1 = false;
                        $opcion2 = false;
                        //Verifica si existe el usuario con la misma identidicacion y mismo correo
                        $existeUsuario = EtlUsuarioPortalClientes::select(['upc_id', 'estado'])
                            ->where('ofe_id', $ofe->ofe_id)
                            ->where('adq_id', $adquirente->adq_id)
                            ->where('upc_identificacion', $usuario['identificacion'])
                            ->where('upc_correo', $usuario['correo'])
                            ->first();

                        if($existeUsuario) {
                            if($existeUsuario->estado == $usuario['estado']) {
                                $opcion1 = true;
                                $arrCampos = [
                                    'upc_nombre' => $usuario['nombre']
                                ];
                                $procesamiento = $this->actualizarCamposUsuarioPortales($newRequest, $arrCampos, '');
                            }elseif($existeUsuario->estado != $usuario['estado']) {
                                $opcion2 = true;
                                $arrCampos = [
                                    'upc_nombre' => $usuario['nombre'],
                                    'estado'     => $usuario['estado']
                                ];
                                $procesamiento = $this->actualizarCamposUsuarioPortales($newRequest, $arrCampos, $usuario['estado']);
                            }
                        }
                    
                        if(!$opcion1 && !$opcion2) {
                            $opcion1 = false;
                            $opcion2 = false;
                            // Verifica si existe el usuario en el sistema con la misma identificación
                            $usuarioID = EtlUsuarioPortalClientes::select(['upc_id', 'estado'])
                                ->where('ofe_id', $ofe->ofe_id)
                                ->where('adq_id', $adquirente->adq_id)
                                ->where('upc_identificacion', $usuario['identificacion'])
                                ->first();

                            if($usuarioID) {
                                if($usuarioID->estado == $usuario['estado']) {
                                    $opcion1 = true;
                                    $arrCampos = [
                                        'upc_nombre' => $usuario['nombre'],
                                        'upc_correo' => $usuario['correo']
                                    ];
                                    $procesamiento = $this->actualizarCamposUsuarioPortales($newRequest, $arrCampos, '');
                                }elseif($usuarioID->estado != $usuario['estado']) {
                                    $opcion2 = true;
                                    $arrCampos = [
                                        'upc_nombre' => $usuario['nombre'],
                                        'upc_correo' => $usuario['correo'],
                                        'estado'     => $usuario['estado']
                                    ];
                                    $procesamiento = $this->actualizarCamposUsuarioPortales($newRequest, $arrCampos, $usuario['estado']);
                                }
                            }
                        }

                        if(!$opcion1 && !$opcion2) {
                            // Verifica si existe el usuario en el sistema con el mismo correo
                            $usuarioCorreo = EtlUsuarioPortalClientes::select(['upc_id', 'estado'])
                                ->where('ofe_id', $ofe->ofe_id)
                                ->where('adq_id', $adquirente->adq_id)
                                ->where('upc_correo', $usuario['correo'])
                                ->first();

                            if($usuarioCorreo) {
                                if($usuarioCorreo->estado == $usuario['estado']) {
                                    $opcion1 = true;
                                    $arrCampos = [
                                        'upc_nombre'         => $usuario['nombre'],
                                        'upc_identificacion' => $usuario['identificacion']
                                    ];
                                    $procesamiento = $this->actualizarCamposUsuarioPortales($newRequest, $arrCampos, '');
                                }elseif($usuarioCorreo->estado != $usuario['estado']) {
                                    $opcion1 = true;
                                    $arrCampos = [
                                        'upc_nombre'         => $usuario['nombre'],
                                        'upc_identificacion' => $usuario['identificacion'],
                                        'estado'             => $usuario['estado']
                                    ];
                                    $procesamiento = $this->actualizarCamposUsuarioPortales($newRequest, $arrCampos, $usuario['estado']);
                                }
                            }
                        }
                    }

                    $status        = $procesamiento->getStatusCode();
                    $procesamiento = json_decode((string)$procesamiento->getContent());

                    if($status != 200) {
                        $arrErrores[] = 'Error al procesar el usuario: [' . implode(' // ' , $procesamiento->errors) . ']';
                    }
                }

                if(!empty($arrErrores)) {
                    $usuario['errors'] = $arrErrores;
                    $resultado['usuarios_fallidos'][] = $usuario;
                } else {
                    $resultado['usuarios_procesados'][] = $usuario;
                }
            } catch (\Exception $e) {
                $usuario['errors'] = 'Usuarios Portal Clientes [' . $e->getLine() . ']: ' . $e->getMessage();
                $resultado['usuarios_fallidos'][] = $usuario;
            }
        }

        $resultado['message'] = 'Se procesaron: ' . count($resultado['usuarios_procesados']) . ' Usuario(s) Exitoso(s). ' . count($resultado['usuarios_fallidos']) . ' Usuario(s) con Error';
        return response()->json($resultado, 200);
    }

    /**
     * Actualiza los usuarios de un adquirente con acceso a portales
     *
     * @param Request $request
     * @return Response
     */
    public function actualizarUsuariosPortales(Request $request) {
        $user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_identificador_unico_adquirente'])
            ->where('ofe_id', $request->ofe_id)
            ->where('estado', 'ACTIVO')
            ->first();

        $adquirente = ConfiguracionAdquirente::select(['adq_id', 'ofe_id', 'adq_identificacion'])
            ->where('ofe_id', $request->ofe_id)
            ->where('adq_id', $request->adq_id)
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El Oferente indicado no existe en el sistema o se encuentra inactivo']
            ], 409);
        }
    
        if(!$adquirente) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El Adquirente indicado no existe en el sistema, se encuentra inactivo o no está relacionado con el OFE indicado']
            ], 409);
        }

        $existen = [];
        foreach($request->usuariosNuevos as $usuario) {
            //Verifica si existe el usuario con la misma identidicacion y mismo correo
            $existeUsuario = EtlUsuarioPortalClientes::select(['upc_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('adq_id', $adquirente->adq_id)
                ->where('upc_identificacion', $usuario['identificacion'])
                ->where('upc_correo', $usuario['email'])
                ->where('estado', 'ACTIVO')
                ->first();

            if($existeUsuario) {
                $existen[] = $usuario['identificacion'] . ' - ' . $usuario['email'];
            } else {
                // Verifica si existe el usuario en el sistema con la misma identificación
                $usuarioID = EtlUsuarioPortalClientes::select(['upc_id'])
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('adq_id', $adquirente->adq_id)
                    ->where('upc_identificacion', $usuario['identificacion'])
                    ->where('estado', 'ACTIVO')
                    ->first();

                // Verifica si existe el usuario en el sistema con el mismo correo
                $usuarioCorreo = EtlUsuarioPortalClientes::select(['upc_id'])
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('adq_id', $adquirente->adq_id)
                    ->where('upc_correo', $usuario['email'])
                    ->where('estado', 'ACTIVO')
                    ->first();

                if($usuarioID || $usuarioCorreo)
                    $existen[] = $usuario['identificacion'] . ' - ' . $usuario['email'];
            }
        }

        if(!empty($existen)) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['Solicitud no procesada. Los siguientes usuarios ya existen en el sistema con la misma identificación y/o correo: ' . implode(', ', $existen)]
            ], 409);
        }

        // Cantidad de usuarios de portal clientes admitidos
        TenantTrait::GetVariablesSistemaTenant();
        $cantidadUsuariosPortalClientes = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_CLIENTES');

        // Verifica la cantidad de usuarios de portal clientes en estado ACTIVO para el adquirente del Ofe
        $totalUsuariosPortalActivos = EtlUsuarioPortalClientes::select(['upc_id'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('adq_id', $adquirente->adq_id)
            ->where('estado', 'ACTIVO')
            ->count();

        if($totalUsuariosPortalActivos >= $cantidadUsuariosPortalClientes) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['La cantidad máxima de usuarios activos del portal clientes es [' . $cantidadUsuariosPortalClientes . ']. Si desea crear nuevos usuarios, debe inactivar usuarios existentes']
            ], 409);
        }

        $totalFinalUsuariosActivos = $totalUsuariosPortalActivos + count($request->usuariosNuevos);
        if($totalFinalUsuariosActivos > $cantidadUsuariosPortalClientes) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['La cantidad máxima de usuarios activos del portal clientes es [' . $cantidadUsuariosPortalClientes . ']. Actualmente cuenta con [' . $totalUsuariosPortalActivos . '] usuario(s) activo(s) y esta intentado crear [' . count($request->usuariosNuevos) . '] con lo que supera el máximo permitido. Verifique su información']
            ], 409);
        }

        $contador = 0;
        foreach($request->usuariosNuevos as $usuario) {
            if($contador <= $cantidadUsuariosPortalClientes) {
                // crea el usuario de portales
                EtlUsuarioPortalClientes::create([
                    'ofe_id'             => $ofe->ofe_id,
                    'ofe_identificacion' => $ofe->ofe_identificacion,
                    'adq_id'             => $adquirente->adq_id,
                    'adq_identificacion' => $adquirente->adq_identificacion,
                    'upc_identificacion' => $usuario['identificacion'],
                    'upc_nombre'         => $usuario['nombre'],
                    'upc_correo'         => $usuario['email'],
                    'usuario_creacion'   => $user->usu_id,
                    'estado'             => array_key_exists('estado', $usuario) && !empty($usuario['estado']) ? $usuario['estado'] : 'ACTIVO'
                ]);

                $contador ++;
            } else {
                return response()->json([
                    'message' => 'Error Usuarios Portales',
                    'errors'  => ['La cantidad máxima de usuarios activos del portal clientes es [' . $cantidadUsuariosPortalClientes . ']. Si desea activar nuevos usuarios, debe inactivar usuarios existentes']
                ], 409);
            }
        }

        return response()->json([
            'message' => 'Usuarios de Portales Actualizados'
        ], 200);
    }

    /**
     * Actualiza los campos de un usuario de un adquirente con acceso a portales.
     *
     * @param Request $request
     * @return Response
     */
    public function actualizarCamposUsuarioPortales(Request $request, $camposActualizar, $estado) {
        $usuarioPortales = EtlUsuarioPortalClientes::where('ofe_id', $request->ofe_id)
            ->where('adq_id', $request->adq_id)
            ->where('upc_id', $request->upc_id)
            ->first();

        if(!$usuarioPortales) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El usuario de portales indicado no existe en el sistema']
            ], 409);
        }

        // Si el estado enviado es inactivo o vacío solo se actualizan los campos enviados
        if($estado == 'INACTIVO' || $estado == '') {
            $usuarioPortales->update($camposActualizar);
        // Si el estado enviado es activo se debe activar teniendo en cuenta la cantidad de usuarios activos que se pueden tener
        }elseif($estado == 'ACTIVO') {

            $existen = [];
            //Verifica si existe el usuario con la misma identidicacion y mismo correo
            $existeUsuario = EtlUsuarioPortalClientes::select(['upc_id'])
                ->where('ofe_id', $request->ofe_id)
                ->where('adq_id', $request->adq_id)
                ->where('upc_identificacion', $request->identificacion)
                ->where('upc_correo', $request->email)
                ->where('estado', 'ACTIVO')
                ->first();

            if($existeUsuario) {
                $existen[] = $request->identificacion . ' - ' . $request->email;
            } else {
                // Verifica si existe el usuario en el sistema con la misma identificación
                $usuarioID = EtlUsuarioPortalClientes::select(['upc_id'])
                    ->where('ofe_id', $request->ofe_id)
                    ->where('adq_id', $request->adq_id)
                    ->where('upc_identificacion', $request->identificacion)
                    ->where('estado', 'ACTIVO')
                    ->first();

                // Verifica si existe el usuario en el sistema con el mismo correo
                $usuarioCorreo = EtlUsuarioPortalClientes::select(['upc_id'])
                    ->where('ofe_id', $request->ofe_id)
                    ->where('adq_id', $request->adq_id)
                    ->where('upc_correo', $request->email)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if($usuarioID || $usuarioCorreo)
                    $existen[] = $request->identificacion . ' - ' . $request->email;
            }

            if(!empty($existen)) {
                return response()->json([
                    'message' => 'Error Usuarios Portales',
                    'errors'  => ['Solicitud no procesada. Los siguientes usuarios ya existen en el sistema con la misma identificación y/o correo: ' . implode(', ', $existen)]
                ], 409);
            }

            // Cantidad de usuarios de portal clientes admitidos
            TenantTrait::GetVariablesSistemaTenant();
            $cantidadUsuariosPortalClientes = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_CLIENTES');

            // Verifica la cantidad de usuarios de portal clientes en estado ACTIVO para el adquirente del Ofe
            $totalUsuariosPortalActivos = EtlUsuarioPortalClientes::select(['upc_id'])
                ->where('ofe_id', $request->ofe_id)
                ->where('adq_id', $request->adq_id)
                ->where('estado', 'ACTIVO')
                ->count();

            if($totalUsuariosPortalActivos >= $cantidadUsuariosPortalClientes) {
                return response()->json([
                    'message' => 'Error Usuarios Portales',
                    'errors'  => ['La cantidad máxima de usuarios activos del portal clientes es [' . $cantidadUsuariosPortalClientes . ']. Si desea crear nuevos usuarios, debe inactivar usuarios existentes']
                ], 409);
            }

            $usuarioPortales->update($camposActualizar);
        }

        return response()->json([
            'message' => 'Estado de usuario de portales actualizado'
        ]);
    }

    /**
     * Actualiza el estado de un usuario de un adquirente con acceso a portales
     *
     * @param Request $request
     * @return Response
     */
    public function actualizarEstadoUsuarioPortales(Request $request) {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
            ->where('ofe_id', $request->ofe_id)
            ->where('estado', 'ACTIVO')
            ->first();

        $adquirente = ConfiguracionAdquirente::select(['adq_id', 'ofe_id', 'adq_identificacion'])
            ->where('ofe_id', $request->ofe_id)
            ->where('adq_id', $request->adq_id)
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El Oferente indicado no existe en el sistema o se encuentra inactivo']
            ], 409);
        }

        if(!$adquirente) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El Adquirente indicado no existe en el sistema o se encuentra inactivo']
            ], 409);
        }

        $usuarioPortales = EtlUsuarioPortalClientes::where('ofe_id', $request->ofe_id)
            ->where('adq_id', $request->adq_id)
            ->where('upc_id', $request->upc_id)
            ->first();

        if(!$usuarioPortales) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El usuario de portales indicado no existe en el sistema']
            ], 409);
        }

        // Si llega el estado en el request (estado que se debe actualizar para el usuario) se debe invertir el estado del usuario para que codigo posterior se encargue de la lógica
        // Esto es porque desde el frontend los request no llegan con el estado, sino que de acuerdo al estado actual del usuario el sistema lo invierte
        if($request->has('estado') && !empty($request->estado)) {
            if($request->estado == 'ACTIVO')
                $usuarioPortales->update([
                    'estado' => 'INACTIVO'
                ]);
            else
                $usuarioPortales->update([
                    'estado' => 'ACTIVO'
                ]);
        }

        // Usuario esta activo, se debe inactivar
        if($usuarioPortales->estado == 'ACTIVO')
            $usuarioPortales->update([
                'estado' => 'INACTIVO'
            ]);
        // Usuario esta inactivo, se debe activar teniendo en cuenta la cantidad de usuarios activos que se pueden tener
        elseif($usuarioPortales->estado == 'INACTIVO') {
            $existe = [];
            //Verifica si existe el usuario con la misma identidicación y mismo correo
            $existeUsuario = EtlUsuarioPortalClientes::select(['upc_id'])
                ->where('ofe_id', $request->ofe_id)
                ->where('adq_id', $request->adq_id)
                ->where('upc_identificacion', $usuarioPortales->upc_identificacion)
                ->where('upc_correo', $usuarioPortales->upc_correo)
                ->where('estado', 'ACTIVO')
                ->first();

            if($existeUsuario) {
                $existen[] = $usuarioPortales->upc_identificacion . ' - ' . $usuarioPortales->upc_correo;
            } else {
                // Verifica si existe el usuario en el sistema con la misma identificación
                $usuarioID = EtlUsuarioPortalClientes::select(['upc_id'])
                    ->where('ofe_id', $request->ofe_id)
                    ->where('adq_id', $request->adq_id)
                    ->where('upc_identificacion', $usuarioPortales->upc_identificacion)
                    ->where('estado', 'ACTIVO')
                    ->first();

                // Verifica si existe el usuario en el sistema con el mismo correo
                $usuarioCorreo = EtlUsuarioPortalClientes::select(['upc_id'])
                    ->where('ofe_id', $request->ofe_id)
                    ->where('adq_id', $request->adq_id)
                    ->where('upc_correo', $usuarioPortales->upc_correo)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if($usuarioID || $usuarioCorreo)
                    $existen[] = $usuarioPortales->upc_identificacion . ' - ' . $usuarioPortales->upc_correo;
            }

            if(!empty($existen)) {
                return response()->json([
                    'message' => 'Error Usuarios Portales',
                    'errors'  => ['Solicitud no procesada. El usuario que intenta ACTIVAR ya existen en el sistema con la misma identificación y/o correo: ' . implode(', ', $existen)]
                ], 409);
            }

            // Cantidad de usuarios de portal clientes admitidos
            TenantTrait::GetVariablesSistemaTenant();
            $cantidadUsuariosPortalClientes = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_CLIENTES');

            // Verifica la cantidad de usuarios de portal clientes en estado ACTIVO para el adquirente del Ofe
            $totalUsuariosPortalActivos = EtlUsuarioPortalClientes::select(['upc_id'])
                ->where('ofe_id', $request->ofe_id)
                ->where('adq_id', $request->adq_id)
                ->where('estado', 'ACTIVO')
                ->count();

            if($totalUsuariosPortalActivos >= $cantidadUsuariosPortalClientes) {
                return response()->json([
                    'message' => 'Error Usuarios Portales',
                    'errors'  => ['La cantidad máxima de usuarios activos del portal clientes es [' . $cantidadUsuariosPortalClientes . ']. Si desea crear nuevos usuarios, debe inactivar usuarios existentes']
                ], 409);
            }

            $usuarioPortales->update([
                'estado' => 'ACTIVO'
            ]);
        }

        return response()->json([
            'message' => 'Estado de usuario de portales actualizado'
        ]);
    }

    /**
     * Ejecuta la descarga de un archivo
     *
     * @param $nombreArchivo
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function download($nombreArchivo, $titulo) {
        $headers = [
            header('Access-Control-Expose-Headers: Content-Disposition')
        ];

        header('Access-Control-Expose-Headers: Content-Disposition');
        date_default_timezone_set('America/Bogota');
        $nombreArchivo = $titulo . '_' . date('YmdHis');
        $__columnas = [];
        foreach ($columnas as $col)
            $__columnas[] = mb_strtoupper($col);
        return new ExcelExport($nombreArchivo, $__columnas);

        // Retorna el documento y lo elimina tan pronto es enviado en la respuesta
        return response()
            ->download(storage_path('etl/descargas/' . $nombreArchivo), $nombreArchivo, $headers)
            ->deleteFileAfterSend(true);
    }

    /**
     * Actualiza el estado de un usuario de un adquirente con acceso a portales
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function descargarListaUsuariosPortales(Request $request) {
        $columnas = [
            'ofe_id',
            'ofe_identificacion',
            'adq_id',
            'adq_identificacion',
            'upc_identificacion',
            'upc_nombre',
            'upc_correo',
            'estado',
        ];
        $relaciones = [
            'getConfiguracionAdquirente' => function($query) {
                $query->select([
                    'adq_id',
                    'adq_identificacion',
                    DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo')
                ]);
            },
            'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                $query->select([
                    'ofe_id',
                    'ofe_identificacion',
                    DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                ]);
            }
        ];

        $exportacion = [
            'columnas' => [
                'ofe_identificacion' => 'NIT OFE',
                'ofe_razon_social' => [
                    'label' => 'OFE',
                    'relation' => ['name' => 'getConfiguracionObligadoFacturarElectronicamente', 'field' => 'nombre_completo']
                ],
                'adq_identificacion' => 'NIT ADQUIRENTE',
                'adq_razon_social' => [
                    'label' => 'ADQUIRENTE',
                    'relation' => ['name' => 'getConfiguracionAdquirente', 'field' => 'nombre_completo']
                ],
                'upc_identificacion' => 'IDENTIFICACION',
                'upc_nombre' => 'NOMBRE',
                'upc_correo' => 'CORREO',
                'estado' => 'ESTADO'
            ],
            'titulo' => 'USUARIOSPORTALClientes'
        ];

        if ($request->has('excel') && ($request->excel || $request->excel === 'true'))
            return $this->procesadorTracking($request, [], $columnas, $relaciones, $exportacion, false, [], 'configuracion');
        else {
            $data = $this->procesadorTracking($request, [], $columnas, $relaciones, $exportacion, true, [], 'configuracion');
            
            // Cantidad de usuarios de portal clientes admitidos
            TenantTrait::GetVariablesSistemaTenant();
            $data['cantidad_usuarios_portal_clientes'] = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_CLIENTES');
            return response()->json($data, Response::HTTP_OK);
        }
    }

    /**
     * Genera una Interfaz de Usuarios Portal Clientes para guardar en Excel.
     *
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceUsuariosPortalClientes(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'usuariosportalclientes');
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de Usuarios Portal Clientes.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function cargarUsuariosPortalClientes(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->user = auth()->user();

        $titulo = 'usuariosportalclientes';

        if ($request->hasFile('archivo')) {
            $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
            if (
                (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
                !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
            )
                return response()->json([
                    'message' => 'Errores al guardar los ' . ucwords($titulo),
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
                    'message' => 'Errores al guardar los ' . ucwords($titulo),
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores  = [];
            $arrResultado = [];
            $arrExisteOfe = [];
            $arrExistePro = [];

            foreach ($data as $fila => $columnas) {

                $Acolumnas = $columnas;
                $columnas = (object)$columnas;

                $nCantidadVacias = 0;
                foreach ($Acolumnas as $key => $value) {
                    $Acolumnas[$key] = trim($value);
                    if ($Acolumnas[$key] == "") {
                        $nCantidadVacias++;
                    }
                }

                if ($nCantidadVacias == count($Acolumnas)) {
                    unset($data[$fila]);
                } else {
                    $arrUsuarioPortal = [];

                    $campos = [
                        'nit_ofe',
                        'nit_adquirente',
                        'identificacion',
                        'nombre',
                        'correo',
                        'estado'
                    ];

                    $arrFaltantes = $this->checkFields($Acolumnas, $campos, $fila);

                    if (!empty($arrFaltantes)) {
                        $vacio = $this->revisarArregloVacio($columnas);

                        if ($vacio) {
                            $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                        } else {
                            unset($data[$fila]);
                        }
                    } else {
                        //nit_ofe
                        if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)) {
                            $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                        } else {
                            $objExisteOfe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
                                ->where('ofe_identificacion', $columnas->nit_ofe)
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                        }

                        if (!$objExisteOfe) {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Id del OFE [' . $columnas->nit_ofe . '] no existe.'], $fila);
                        }

                        if (array_key_exists($columnas->nit_adquirente, $arrExistePro)) {
                            $objExistePro = $arrExistePro[$columnas->nit_adquirente];
                        } else {
                            $objExistePro = ConfiguracionAdquirente::select(['adq_id'])
                                ->where('adq_identificacion', $columnas->nit_adquirente)
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $arrExistePro[$columnas->nit_adquirente] = $objExistePro;
                        }

                        if (!$objExistePro) {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Id del ADQUIRENTE [' . $columnas->nit_adquirente . '] no existe.'], $fila);
                        }

                        $arrUsuarioPortal['ofe_id']             = isset($objExisteOfe->ofe_id) ? $objExisteOfe->ofe_id : null;
                        $arrUsuarioPortal['ofe_identificacion'] = $this->sanitizarStrings($columnas->nit_ofe);
                        $arrUsuarioPortal['adq_id']             = isset($objExistePro->adq_id) ? $objExistePro->adq_id : null;
                        $arrUsuarioPortal['adq_identificacion'] = $this->sanitizarStrings($columnas->nit_adquirente);
                        $arrUsuarioPortal['upc_identificacion'] = $this->sanitizarStrings($columnas->identificacion);
                        $arrUsuarioPortal['upc_nombre']         = $this->sanitizarStrings($columnas->nombre);
                        $arrUsuarioPortal['upc_correo']         = $this->sanitizarStrings($columnas->correo);
                        $arrUsuarioPortal['estado']             = $this->sanitizarStrings($columnas->estado);

                        if (empty($arrErrores)) {
                            $objValidator = Validator::make($arrUsuarioPortal, $this->className::$rules);

                            if (!empty($objValidator->errors()->all())) {
                                $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                            } else {
                                $arrResultado[] = $arrUsuarioPortal;
                            }
                        }
                    }
                }
                if ($fila % 500 === 0) {
                    $this->renovarConexion($this->user);
                }
            }

            if (!empty($arrErrores)) {
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $this->user->usu_id,
                    'age_proceso'             => 'FINALIZADO',
                    'age_cantidad_documentos' => 0,
                    'age_prioridad'           => null,
                    'usuario_creacion'        => $this->user->usu_id,
                    'fecha_creacion'          => date('Y-m-d H:i:s'),
                    'estado'                  => 'ACTIVO',
                ]);

                EtlProcesamientoJson::create([
                    'pjj_tipo'                => ProcesarCargaParametricaCommand::$TYPE_UPC,
                    'pjj_json'                => json_encode([]),
                    'pjj_procesado'           => 'SI',
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'pjj_errores'             => json_encode($arrErrores),
                    'age_id'                  => $agendamiento->age_id,
                    'usuario_creacion'        => $this->user->usu_id,
                    'fecha_creacion'          => date('Y-m-d H:i:s'),
                    'estado'                  => 'ACTIVO'
                ]);

                return response()->json([
                    'message' => 'Errores al guardar los ' . ucwords($titulo),
                    'errors' => ['Verifique el Log de Errores'],
                ], 400);

            } else {
                if (count($arrResultado) > 0) {
                    $bloques = array_chunk($arrResultado, 100);

                    foreach ($bloques as $bloque) {
                        $agendamiento = AdoAgendamiento::create([
                            'usu_id'                  => $this->user->usu_id,
                            'age_proceso'             => ProcesarCargaParametricaCommand::$NOMBRE_COMANDO,
                            'age_cantidad_documentos' => count($bloque),
                            'age_prioridad'           => null,
                            'usuario_creacion'        => $this->user->usu_id,
                            'fecha_creacion'          => date('Y-m-d H:i:s'),
                            'estado'                  => 'ACTIVO',
                        ]);

                        if ($agendamiento) {
                            EtlProcesamientoJson::create([
                                'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_UPC,
                                'pjj_json'         => json_encode($bloque),
                                'pjj_procesado'    => 'NO',
                                'age_id'           => $agendamiento->age_id,
                                'usuario_creacion' => $this->user->usu_id,
                                'fecha_creacion'   => date('Y-m-d H:i:s'),
                                'estado'           => 'ACTIVO'
                            ]);
                        }
                    }
                }

                if (!empty($arrErrores)) {
                    return response()->json([
                        'message' => 'Errores al guardar los ' . ucwords($titulo),
                        'errors' => $arrErrores
                    ], 422);
                } else {
                    return response()->json([
                        'success' => true
                    ], 200);
                }
            }
        } else {
            return response()->json([
                'message' => 'Errores al guardar los ' . ucwords($titulo),
                'errors' => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }
}
