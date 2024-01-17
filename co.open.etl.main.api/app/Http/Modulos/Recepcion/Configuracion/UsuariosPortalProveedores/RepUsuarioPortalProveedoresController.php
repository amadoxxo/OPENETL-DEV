<?php
namespace App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantTrait;
use App\Http\Controllers\OpenTenantController;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores\RepUsuarioPortalProveedores;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RepUsuarioPortalProveedoresController extends OpenTenantController {
    /**
     * Modelo relacionado a la paramétrica.
     *
     * @var Model
     */
    public $className = RepUsuarioPortalProveedores::class;

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
        'NIT PROVEEDOR',
        'PROVEEDOR',
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
            'VerificaMetodosRol:ConfiguracionProveedores,ConfiguracionProveedoresNuevo,ConfiguracionProveedoresEditar,ConfiguracionProveedoresSubir'
        ])->only([
            'actualizarUsuariosPortales',
            'actualizarEstadoUsuarioPortales',
            'descargarListaUsuariosPortales',
            'generarInterfaceUsuariosPortalProveedores',
            'cargarUsuariosPortalProveedores',
            'administrarUsuariosPortales'
        ]);
    }

    /**
     * Obtiene las colecciones correspondientes a OFE y Proveedor.
     *
     * @param string $ofe_identificacion Identificación del OFE
     * @param string $pro_identificacion Identificación del Proveedor
     * @return array Conteniendo las colecciones de OFE y Proveedor
     */
    private function consultarOfeProveedor($ofe_identificacion, $pro_identificacion) {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        $proveedor = null; 
        if($ofe) {
            $proveedor = ConfiguracionProveedor::select(['pro_id', 'ofe_id', 'pro_identificacion'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_identificacion', $pro_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();
        }

        return [
            'ofe'       => $ofe,
            'proveedor' => $proveedor
        ];
    }

    /**
     * Verifica que si existen datos repetidos en la petición.
     * 
     * La verificación se hace sobre las identificaciones y correos de los usuaros.
     * 
     * @param array $usuariosPortalProveedores Array de usuarios del portal proveedores que se recibe en la petición
     * @return array Conteniendo información de los usuarios repetidos luego de las verificaciones
     */
    private function verificarDatosRepetidos($usuariosPortalProveedores) {
        $correos           = [];
        $identificaciones  = [];
        $usuariosRepetidos = [];

        foreach($usuariosPortalProveedores as $usuario) {
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
     * Administra los usuarios de portal proveedores.
     * 
     * Permite la creación o la actualización de estados de los usuarios de portal proveedores.
     *
     * @param Request $request
     * @return Response
     */
    public function administrarUsuariosPortales(Request $request) {
        if(
            !$request->has('usuarios_portal_proveedores') || 
            ($request->has('usuarios_portal_proveedores') && empty($request->usuarios_portal_proveedores)) ||
            ($request->has('usuarios_portal_proveedores') && !is_array($request->usuarios_portal_proveedores))
        ) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['La petición está mal formadada, no se envió el parámetro usuarios_portal_proveedores o no es un array de objetos']
            ], 422);
        }
            
        $user      =  auth()->user();
        $resultado = [
            'message'             => '',
            'usuarios_procesados' => [],
            'usuarios_fallidos'   => []
        ];

        // Verifica que dentro de los datos de la petición no se envién identificaciones y/o correos repetidos
        $datosRepetidos = $this->verificarDatosRepetidos($request->usuarios_portal_proveedores);
        if(!empty($datosRepetidos['usuariosRepetidos'])) {
            $resultado['message']           = 'Se encontraron ' . count($datosRepetidos['usuariosRepetidos']) . ' Usuario(s) con datos repetidos, no se procesó ningún registro en la petición';
            $resultado['usuarios_fallidos'] = $datosRepetidos['usuariosRepetidos'];

            return response()->json($resultado, 200);
        }

        // Se reordena el array de usuarios para colocal en primer lugar los que se deben inactivar
        $usuariosPortalProveedores = collect($request->usuarios_portal_proveedores)->sortBy('estado')->reverse()->toArray();

        foreach($usuariosPortalProveedores as $usuario) {
            try {
                $arrErrores = [];

                $ofeProveedor = $this->consultarOfeProveedor(
                    $usuario['ofe_identificacion'],
                    $usuario['pro_identificacion']
                );

                $ofe       = $ofeProveedor['ofe'];
                $proveedor = $ofeProveedor['proveedor'];

                if(!$ofe)
                    $arrErrores[] = 'El OFE [' . $usuario['ofe_identificacion'] . '] del usuario no existe o se encuentra inactivo';
                    
                if(!$proveedor)
                    $arrErrores[] = 'El Proveedor [' . $usuario['pro_identificacion'] . '] para el OFE [' . $usuario['ofe_identificacion'] . '] del usuario no existe o se encuentra inactivo';

                if($ofe && $proveedor) {
                    $token = auth()->login($user);

                    $newRequest = new Request();
                    $newRequest->headers->add(['Authorization' => 'Bearer ' . $token]);
                    $newRequest->headers->add(['accept' => 'application/json']);
                    $newRequest->headers->add(['content-type' => 'application/json']);
                    $newRequest->headers->add(['cache-control' => 'no-cache']);

                    $newRequest->merge([
                        'ofe_id' => $proveedor->ofe_id,
                        'pro_id' => $proveedor->pro_id
                    ]);

                    // Se verifica si el usuario existe o no como usuario de portal proveedores para definir si se debe crear o actualizar su estado
                    $existe = RepUsuarioPortalProveedores::select(['upp_id'])
                        ->where('ofe_id', $ofe->ofe_id)
                        ->where('pro_id', $proveedor->pro_id)
                        ->where(function($query) use ($usuario) {
                            $query->where('upp_identificacion', $usuario['identificacion'])
                                ->orWhere('upp_correo', $usuario['correo']);
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
                            'upp_id' => $existe->upp_id,
                            'estado' => $usuario['estado']
                        ]);
                        
                        $procesamiento = $this->actualizarEstadoUsuarioPortales($newRequest);
                    }

                    $status        = $procesamiento->getStatusCode();
                    $procesamiento = json_decode((string)$procesamiento->getContent());

                    if($status != 200) {
                        $arrErrores[] = 'Error el procesar el usuario: [' . implode(' // ' , $procesamiento->errors) . ']';
                    }
                }

                if(!empty($arrErrores)) {
                    $usuario['errors'] = $arrErrores;
                    $resultado['usuarios_fallidos'][] = $usuario;
                } else {
                    $resultado['usuarios_procesados'][] = $usuario;
                }
            } catch (\Exception $e) {
                $usuario['errors'] = 'Usuarios Portal Proveedores [' . $e->getLine() . ']: ' . $e->getMessage();
                $resultado['usuarios_fallidos'][] = $usuario;
            }
        }

        $resultado['message'] = 'Se procesaron: ' . count($resultado['usuarios_procesados']) . ' Usuario(s) Exitoso(s). ' . count($resultado['usuarios_fallidos']) . ' Usuario(s) con Error';
        return response()->json($resultado, 200);
    }

    /**
     * Actualiza los usuarios de un proveedor con acceso a portales
     *
     * @param Request $request
     * @return Response
     */
    public function actualizarUsuariosPortales(Request $request) {
        $user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
            ->where('ofe_id', $request->ofe_id)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        $proveedor = ConfiguracionProveedor::select(['pro_id', 'ofe_id', 'pro_identificacion'])
            ->where('pro_id', $request->pro_id)
            ->where('ofe_id', $request->ofe_id)
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El Oferente indicado no existe en el sistema o se encuentra inactivo']
            ], 409);
        }

        if(!$proveedor) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El Adquirente indicado no existe en el sistema, se encuentra inactivo o no está relacionado con el OFE indicado']
            ], 409);
        }

        $existen = [];
        foreach($request->usuariosNuevos as $usuario) {
            // Verifica si existe el usuario en el sistema con la misma identificación
            $usuarioID = RepUsuarioPortalProveedores::select(['upp_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_id', $proveedor->pro_id)
                ->where('upp_identificacion', $usuario['identificacion'])
                ->first();

            // Verifica si existe el usuario en el sistema con el mismo correo
            $usuarioCorreo = RepUsuarioPortalProveedores::select(['upp_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_id', $proveedor->pro_id)
                ->where('upp_correo', $usuario['email'])
                ->first();

            if($usuarioID || $usuarioCorreo)
                $existen[] = $usuario['identificacion'] . ' - ' . $usuario['email'];
        }

        if(!empty($existen)) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['Solicitud no procesada. Los siguientes usuarios ya existen en el sistema con la misma identificación y/o correo: ' . implode(', ', $existen)]
            ], 409);
        }

        // Cantidad de usuarios de portal proveedores admitidos
        TenantTrait::GetVariablesSistemaTenant();
        $cantidadUsuariosPortalProveedores = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_PROVEEDORES');

        // Verifica la cantidad de usuarios de portal proveedores en estado ACTIVO para el proveedor del Ofe
        $totalUsuariosPortalActivos = RepUsuarioPortalProveedores::select(['upp_id'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('pro_id', $proveedor->pro_id)
            ->where('estado', 'ACTIVO')
            ->count();

        if($totalUsuariosPortalActivos >= $cantidadUsuariosPortalProveedores) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['La cantidad máxima de usuarios activos del portal proveedores es [' . $cantidadUsuariosPortalProveedores . ']. Si desea crear nuevos usuarios, debe inactivar usuarios existentes']
            ], 409);
        }

        $totalFinalUsuariosActivos = $totalUsuariosPortalActivos + count($request->usuariosNuevos);
        if($totalFinalUsuariosActivos > $cantidadUsuariosPortalProveedores) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['La cantidad máxima de usuarios activos del portal proveedores es [' . $cantidadUsuariosPortalProveedores . ']. Actualmente cuenta con [' . $totalUsuariosPortalActivos . '] usuario(s) activo(s) y esta intentado crear [' . count($request->usuariosNuevos) . '] con lo que supera el máximo permitido. Verifique su información']
            ], 409);
        }

        $contador = 0;
        foreach($request->usuariosNuevos as $usuario) {
            if($contador <= $cantidadUsuariosPortalProveedores) {
                // crea el usuario de portales
                RepUsuarioPortalProveedores::create([
                    'ofe_id'             => $ofe->ofe_id,
                    'ofe_identificacion' => $ofe->ofe_identificacion,
                    'pro_id'             => $proveedor->pro_id,
                    'pro_identificacion' => $proveedor->pro_identificacion,
                    'upp_identificacion' => $usuario['identificacion'],
                    'upp_nombre'         => $usuario['nombre'],
                    'upp_correo'         => $usuario['email'],
                    'usuario_creacion'   => $user->usu_id,
                    'estado'             => array_key_exists('estado', $usuario) && !empty($usuario['estado']) ? $usuario['estado'] : 'ACTIVO'
                ]);

                $contador ++;
            } else {
                return response()->json([
                    'message' => 'Error Usuarios Portales',
                    'errors'  => ['La cantidad máxima de usuarios activos del portal proveedores es [' . $cantidadUsuariosPortalProveedores . ']. Si desea activar nuevos usuarios, debe inactivar usuarios existentes']
                ], 409);
            }
        }

        return response()->json([
            'message' => 'Usuarios de Portales Actualizados'
        ], 200);
    }

    /**
     * Actualiza el estado de un usuario de un proveedor con acceso a portales
     *
     * @param Request $request
     * @return Response
     */
    public function actualizarEstadoUsuarioPortales(Request $request) {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
            ->where('ofe_id', $request->ofe_id)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        $proveedor = ConfiguracionProveedor::select(['pro_id', 'ofe_id', 'pro_identificacion'])
            ->where('pro_id', $request->pro_id)
            ->where('ofe_id', $request->ofe_id)
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El Oferente indicado no existe en el sistema o se encuentra inactivo']
            ], 409);
        }

        if(!$proveedor) {
            return response()->json([
                'message' => 'Error Usuarios Portales',
                'errors'  => ['El Proveedor indicado no existe en el sistema, se encuentra inactivo o no esta relacionado con el OFE indicado']
            ], 409);
        }

        $usuarioPortales = RepUsuarioPortalProveedores::where('ofe_id', $request->ofe_id)
            ->where('pro_id', $request->pro_id)
            ->where('upp_id', $request->upp_id)
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
            // Cantidad de usuarios de portal proveedores admitidos
            TenantTrait::GetVariablesSistemaTenant();
            $cantidadUsuariosPortalProveedores = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_PROVEEDORES');

            // Verifica la cantidad de usuarios de portal proveedores en estado ACTIVO para el proveedor del Ofe
            $totalUsuariosPortalActivos = RepUsuarioPortalProveedores::select(['upp_id'])
                ->where('ofe_id', $request->ofe_id)
                ->where('pro_id', $request->pro_id)
                ->where('estado', 'ACTIVO')
                ->count();

            if($totalUsuariosPortalActivos >= $cantidadUsuariosPortalProveedores) {
                return response()->json([
                    'message' => 'Error Usuarios Portales',
                    'errors'  => ['La cantidad máxima de usuarios activos del portal proveedores es [' . $cantidadUsuariosPortalProveedores . ']. Si desea crear nuevos usuarios, debe inactivar usuarios existentes']
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
     * Actualiza el estado de un usuario de un proveedor con acceso a portales
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function descargarListaUsuariosPortales(Request $request) {
        $columnas = [
            'ofe_id',
            'ofe_identificacion',
            'pro_id',
            'pro_identificacion',
            'upp_identificacion',
            'upp_nombre',
            'upp_correo',
            'estado',
        ];
        $relaciones = [
            'getConfiguracionProveedor' => function($query) {
                $query->select([
                    'pro_id',
                    'pro_identificacion',
                    DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
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
                'pro_identificacion' => 'NIT PROVEEDOR',
                'pro_razon_social' => [
                    'label' => 'PROVEEDOR',
                    'relation' => ['name' => 'getConfiguracionProveedor', 'field' => 'nombre_completo']
                ],
                'upp_identificacion' => 'IDENTIFICACION',
                'upp_nombre' => 'NOMBRE',
                'upp_correo' => 'CORREO',
                'estado' => 'ESTADO'
            ],
            'titulo' => 'USUARIOSPORTALPROVEEDORES'
        ];

        if ($request->has('excel') && ($request->excel || $request->excel === 'true'))
            return $this->procesadorTracking($request, [], $columnas, $relaciones, $exportacion, false);
        else {
            $data = $this->procesadorTracking($request, [], $columnas, $relaciones, $exportacion, true);
            
            // Cantidad de usuarios de portal proveedores admitidos
            TenantTrait::GetVariablesSistemaTenant();
            $data['cantidad_usuarios_portal_proveedores'] = config('variables_sistema_tenant.CANTIDAD_USUARIOS_PORTAL_PROVEEDORES');
            return response()->json($data, Response::HTTP_OK);
        }
    }

    /**
     * Genera una Interfaz de Usuarios Portal Proveedores para guardar en Excel.
     *
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceUsuariosPortalProveedores(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'usuariosportalproveedores');
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de Usuarios Portal Proveedores.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function cargarUsuariosPortalProveedores(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->user = auth()->user();

        $titulo = 'usuariosportalproveedores';

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
                        'nit_proveedor',
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
                                ->validarAsociacionBaseDatos()
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                        }

                        if (!$objExisteOfe) {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Id del OFE [' . $columnas->nit_ofe . '] no existe.'], $fila);
                        } else {
                            if (array_key_exists($objExisteOfe->ofe_id."~".$columnas->nit_proveedor, $arrExistePro)) {
                                $objExistePro = $arrExistePro[$objExisteOfe->ofe_id."~".$columnas->nit_proveedor];
                            } else {
                                $objExistePro = ConfiguracionProveedor::select(['pro_id'])
                                    ->where('ofe_id', $objExisteOfe->ofe_id)
                                    ->where('pro_identificacion', $columnas->nit_proveedor)
                                    ->where('estado', 'ACTIVO')
                                    ->first();
                                $arrExistePro[$objExisteOfe->ofe_id."~".$columnas->nit_proveedor] = $objExistePro;
                            }

                            if (!$objExistePro) {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Id del PROVEEDOR [' . $columnas->nit_proveedor . '] no existe.'], $fila);
                            }

                            $arrUsuarioPortal['ofe_id']             = isset($objExisteOfe->ofe_id) ? $objExisteOfe->ofe_id : null;
                            $arrUsuarioPortal['ofe_identificacion'] = $this->sanitizarStrings($columnas->nit_ofe);
                            $arrUsuarioPortal['pro_id']             = isset($objExistePro->pro_id) ? $objExistePro->pro_id : null;
                            $arrUsuarioPortal['pro_identificacion'] = $this->sanitizarStrings($columnas->nit_proveedor);
                            $arrUsuarioPortal['upp_identificacion'] = $this->sanitizarStrings($columnas->identificacion);
                            $arrUsuarioPortal['upp_nombre']         = $this->sanitizarStrings($columnas->nombre);
                            $arrUsuarioPortal['upp_correo']         = $this->sanitizarStrings($columnas->correo);
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
                    'pjj_tipo'                => ProcesarCargaParametricaCommand::$TYPE_UPP,
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
                                'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_UPP,
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
