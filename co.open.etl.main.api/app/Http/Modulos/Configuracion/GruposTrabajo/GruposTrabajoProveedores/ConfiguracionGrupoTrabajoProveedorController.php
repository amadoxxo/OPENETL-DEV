<?php

namespace App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores;

use Validator;
use Illuminate\Http\Request;
use App\Traits\GruposTrabajoTrait;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantTrait;
use App\Http\Controllers\OpenTenantController;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuario;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use Illuminate\Http\JsonResponse;

class ConfiguracionGrupoTrabajoProveedorController extends OpenTenantController {
    use GruposTrabajoTrait;

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
    public $className = ConfiguracionGrupoTrabajoProveedor::class;

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
    public $nombreArchivo = 'proveedores_asociados';

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
     * Constructor.
     */
    public function __construct() {
        // Se obtiene el valor de la variable del sistema para identificar el nombre del grupo
        TenantTrait::GetVariablesSistemaTenant();
        $variableSistema    = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
        $arrVariableSistema = json_decode($variableSistema, true);

        $this->nombre                  = ucwords($arrVariableSistema['singular']);
        $this->nombrePlural            = ucwords($arrVariableSistema['plural']);
        $this->mensajeErrorCreacion422 = 'No Fue Posible Asociar el Proveedor a ' . $arrVariableSistema['singular'];
        $this->mensajeErroresCreacion  = 'Errores al Asociar el Proveedor a ' . $arrVariableSistema['singular'];
        $this->mensajeObjectNotFound   = 'El Id del Proveedor y ' . $arrVariableSistema['singular'] . ' [%s] no Existe';
        $this->mensajeObjectDisabled   = 'El Id del Proveedor y ' . $arrVariableSistema['singular'] . ' [%s] Esta Inactivo';

        // Se almacenan las columnas que se generan en la interfaz de Excel
        $this->columnasExcel = [
            'NIT OFE',
            'CODIGO ' . strtoupper($this->nombre),
            'IDENTIFICACION PROVEEDOR',
            'ESTADO'
        ];

        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarProveedor,ConfiguracionGrupoTrabajoAsociarProveedorNuevo,ConfiguracionGrupoTrabajoAsociarProveedorCambiarEstado,ConfiguracionGrupoTrabajoAsociarProveedorSubir,ConfiguracionGrupoTrabajoAsociarProveedorDescargarExcel,ConfiguracionGrupoTrabajoAsociarProveedorVerProveedoresAsociados,RecepcionAsociarDocumentoGrupoTrabajo'
        ])->except([
            'store',
            'cambiarEstado',
            'listarProveedoresAsociados',
            'generarInterfaceGruposTrabajoProveedores',
            'cargarGruposTrabajoProveedor',
            'getListaErroresGruposTrabajoProveedores',
            'descargarListaErroresGruposTrabajoProveedores'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarProveedorNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarProveedorCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarProveedorVerProveedoresAsociados'
        ])->only([
            'listarProveedoresAsociados'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAsociarProveedorSubir'
        ])->only([
            'generarInterfaceGruposTrabajoProveedores',
            'cargarGruposTrabajoProveedor',
            'getListaErroresGruposTrabajoProveedores',
            'descargarListaErroresGruposTrabajoProveedores'
        ]);
    }

    /**
     * Configura las reglas para poder asociar un proveedor a un grupo de trabajo.
     *
     * @return mixed
     */
    private function getRules() {
        $rules = array_merge($this->className::$rules);

        unset(
            $rules['pro_id'],
            $rules['gtr_id']
        );

        $rules['ofe_identificacion'] = 'required|string|max:20';
        $rules['pro_identificacion'] = 'required|string|max:20';
        $rules['gtr_codigo']         = 'required|array';

        return $rules;
    }

    /**
     * Devuelve una lista paginada de registros.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function getListaGruposTrabajoProveedores(Request $request) {
        $user = auth()->user();

        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'etl_grupos_trabajo_proveedores.gtp_id',
            'etl_grupos_trabajo_proveedores.gtr_id',
            'etl_grupos_trabajo_proveedores.pro_id',
            'etl_grupos_trabajo_proveedores.usuario_creacion',
            'etl_grupos_trabajo_proveedores.fecha_creacion',
            'etl_grupos_trabajo_proveedores.fecha_modificacion',
            'etl_grupos_trabajo_proveedores.estado',
            'etl_grupos_trabajo_proveedores.fecha_actualizacion'
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getProveedor:pro_id,pro_identificacion,pro_razon_social,pro_primer_apellido,pro_segundo_apellido,pro_primer_nombre,pro_otros_nombres,pro_correo',
            'getGrupoTrabajo.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres',
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
                'pro_id' => [
                    'multiple' => true,
                    'relation' => 'getProveedor',
                    'fields' => [
                        [
                            'label' => 'IDENTIFICACION PROVEEDOR',
                            'field' => 'pro_identificacion'
                        ],
                        [
                            'label' => 'RAZON SOCIAL',
                            'field' => 'pro_razon_social'
                        ]
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
                ],
                'estado' => 'Estado'
            ],
            'titulo' => $this->nombreArchivo
        ];

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Permite asociar un proveedor a un grupo de trabajo.
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request){
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
                        $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con código [{$value}] no existe para el OFE con identificación [{$$request->ofe_identificacion}] o se encuentra en estado INACTIVO."]);
                    } else {
                        $usuariosAsociados = ConfiguracionGrupoTrabajoUsuario::select('gtu_id')
                            ->where('gtr_id', $grupoTrabajo->gtr_id)
                            ->where('estado', 'ACTIVO')
                            ->first();

                        if (($grupoTrabajo->gtr_correos_notificacion != null && $grupoTrabajo->gtr_correos_notificacion != '') || $usuariosAsociados) {
                            $respuesta['gtr_id']     = $grupoTrabajo->gtr_id;
                            $respuesta['gtr_nombre'] = $grupoTrabajo->gtr_nombre;

                            $existe = $this->className::where('gtr_id', $grupoTrabajo->gtr_id)
                                ->where('pro_id', $respuesta['pro_id'])
                                ->first();

                            if (!$existe) {
                                $procesarDatos[$key] = $respuesta;
                            } else {
                                $this->errors = $this->adicionarError($this->errors, ["El Proveedor con identificación [{$request->pro_identificacion}] ya existe asociado a {$this->nombre} con código [{$value}]."]);
                            }
                        } else {
                            $this->errors = $this->adicionarError($this->errors, ["El/La {$this->nombre} con código [{$value}] no tiene correos de notificación parametrizados o no tiene usuarios asociados."]);
                        }
                    }
                }
            }

            if (empty($this->errors) && !empty($procesarDatos)) {
                foreach ($procesarDatos as $key => $value) {
                    $existe = $this->className::where('gtr_id', $value['gtr_id'])
                        ->where('pro_id', $value['pro_id'])
                        ->first();

                    if (!$existe) {
                        $data['gtr_id']           = $value['gtr_id'];
                        $data['pro_id']           = $value['pro_id'];
                        $data['estado']           = 'ACTIVO';
                        $data['usuario_creacion'] = auth()->user()->usu_id;
                        $obj = $this->className::create($data);

                        if($obj){
                            $value['grupo_trabajo'] = $this->nombre;

                            TenantTrait::GetVariablesSistemaTenant();
                            // Se envía el correo de notificación con la información del proveedor asociado al grupo de trabajo
                            if (config('variables_sistema_tenant.NOTIFICAR_ASIGNACION_GRUPO_TRABAJO') == 'SI' && $respuesta['ofe_recepcion_fnc_activo'] == 'SI' && $value['pro_correo'] != '' && $value['pro_correo'] != null)
                                $this->notificarProveedorAsociado($value);

                            // Se envía el correo de notificación a los usuarios asociados al grupo de trabajo y los correos de notificación parametrizados al grupo de trabajo
                            if (config('variables_sistema_tenant.NOTIFICAR_ASIGNACION_GRUPO_TRABAJO') == 'SI' && $respuesta['ofe_recepcion_fnc_activo'] == 'SI')
                                $this->notificarUsuariosAsociados($value);
                        }
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
     * Genera una interfaz para asociar los proveedores a los grupos de trabajo mediante un Excel.
     *
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceGruposTrabajoProveedores(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, $this->nombreArchivo);  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de grupos de trabajo proveedores.
     *
     * @param Request $request
     * @return
     * @throws \Exception
     */
    public function cargarGruposTrabajoProveedor(Request $request){
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
                    'message' => 'Errores al asociar los proveedores a un(a) ' . $this->nombre,
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
                        $columna['razon_social'],
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
                    'message' => 'Errores al asociar los proveedores a un(a) ' . $this->nombre,
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $codigoGrupo   = "codigo_" . $this->sanear_string(strtolower($this->nombre));
            $arrErrores    = [];
            $arrResultado  = [];
            $arrExisteOfe  = [];
            $arrExisteProveedor  = [];
            $arrExisteGrupoTrabajo = [];
            $arrGrupoTrabajoProveedorProceso = [];

            foreach ($data as $fila => $columnas) {
                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrGrupoTrabajoProveedor = [];
                $arrFaltantes = $this->checkFields($Acolumnas, [
                    'nit_ofe',
                    $codigoGrupo,
                    'identificacion_proveedor'
                ], $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {
                    $arrGrupoTrabajoProveedor['gtp_id'] = 0;

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
                        $arrGrupoTrabajoProveedor['ofe_id'] = $objExisteOfe->ofe_id;

                        $arrGrupoTrabajoProveedor['pro_id'] = null;
                        if (array_key_exists($columnas->identificacion_proveedor, $arrExisteProveedor)){
                            $objExisteProveedor = $arrExisteProveedor[$columnas->identificacion_proveedor];
                        } else {
                            $objExisteProveedor = ConfiguracionProveedor::where('ofe_id', $objExisteOfe->ofe_id)
                                ->where('pro_identificacion', $columnas->identificacion_proveedor)
                                ->where('estado', 'ACTIVO')
                                ->first();

                            if ($objExisteProveedor) {
                                $arrExisteProveedor[$columnas->identificacion_proveedor] = $objExisteProveedor;
                            }
                        }

                        if (empty($objExisteProveedor)) {
                            $arrErrores = $this->adicionarError($arrErrores, ["El proveedor con identificación {$columnas->identificacion_proveedor} no existe para el OFE {$columnas->nit_ofe} o se encuentra INACTIVO."], $fila);
                        }

                        $arrGrupoTrabajoProveedor['gtr_id'] = null;
                        if (array_key_exists($columnas->$codigoGrupo, $arrExisteGrupoTrabajo)){
                            $objExisteGrupoTrabajo = $arrExisteGrupoTrabajo[$columnas->$codigoGrupo];
                        } else {
                            $objExisteGrupoTrabajo = ConfiguracionGrupoTrabajo::where('gtr_codigo', $columnas->$codigoGrupo)
                                ->where('ofe_id', $objExisteOfe->ofe_id)
                                ->where('estado', 'ACTIVO')
                                ->first();

                            if ($objExisteGrupoTrabajo) {
                                $arrExisteGrupoTrabajo[$columnas->$codigoGrupo] = $objExisteGrupoTrabajo;
                            }
                        }

                        if (empty($objExisteGrupoTrabajo)) {
                            $arrErrores = $this->adicionarError($arrErrores, ["{$this->nombre} con código {$columnas->$codigoGrupo} no existe para el OFE {$columnas->nit_ofe} o se encuentra INACTIVO."], $fila);
                        }

                        if (!empty($objExisteProveedor) && !empty($objExisteGrupoTrabajo)) {
                            // Si el grupo tiene correos de notificación parmétrizados o usuarios asociados, se permite realizar la asociación del proveedor
                            $usuariosAsociados = ConfiguracionGrupoTrabajoUsuario::select('gtu_id')
                                ->where('gtr_id', $objExisteGrupoTrabajo->gtr_id)
                                ->where('estado', 'ACTIVO')
                                ->first();

                            if (($objExisteGrupoTrabajo->gtr_correos_notificacion != null && $objExisteGrupoTrabajo->gtr_correos_notificacion != '') || $usuariosAsociados) {
                                $arrGrupoTrabajoProveedor['pro_id'] = $objExisteProveedor->pro_id;
                                $arrGrupoTrabajoProveedor['gtr_id'] = $objExisteGrupoTrabajo->gtr_id;

                                $objExisteGrupoTrabajoProveedor = $this->className::where('gtr_id', $objExisteGrupoTrabajo->gtr_id)
                                    ->where('pro_id', $objExisteProveedor->pro_id)
                                    ->first();

                                if (!empty($objExisteGrupoTrabajoProveedor)){
                                    $arrGrupoTrabajoProveedor['gtp_id'] = $objExisteGrupoTrabajoProveedor->gtp_id;
                                }
                            } else {
                                $arrErrores = $this->adicionarError($arrErrores, ["El/La {$this->nombre} con código {$columnas->$codigoGrupo} no tiene correos de notificación parametrizados o no tiene usuarios asociados."]);
                            }
                        }
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ["El OFE con identificación {$columnas->nit_ofe} no existe o se encuentra INACTIVO."], $fila);
                    }

                    $llaveGrupoTrabajo = $columnas->nit_ofe . '|' . $columnas->$codigoGrupo . '|' . $columnas->identificacion_proveedor;
                    if (array_key_exists($llaveGrupoTrabajo, $arrGrupoTrabajoProveedorProceso)) {
                        $arrErrores = $this->adicionarError($arrErrores, ["El proveedor con identificación {$columnas->identificacion_proveedor} asociado a {$this->nombre} con código {$columnas->$codigoGrupo} ya existe en otras filas."], $fila);
                    } else {
                        $arrGrupoTrabajoProveedorProceso[$llaveGrupoTrabajo] = true;
                    }

                    $arrGrupoTrabajoProveedor['estado'] = (isset($columnas->estado) && $columnas->estado != '') ? $this->sanitizarStrings($columnas->estado) : 'ACTIVO';
                    if(count($arrErrores) == 0){
                        $reglas = $this->className::$rules;
                        $objValidator = Validator::make($arrGrupoTrabajoProveedor, $reglas);

                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrGrupoTrabajoProveedor;
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
                    'pjj_tipo'                => ProcesarCargaParametricaCommand::$TYPE_ASOCIARPROVEEDOR,
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
                    'message' => 'Errores al asociar los proveedores a un(a) ' . $this->nombre,
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            } else {
                $bloque_grupo_trabajo_proveedor = [];
                foreach ($arrResultado as $grupo_trabajo_proveedor) {
                    $data = [
                        "gtp_id" => $grupo_trabajo_proveedor['gtp_id'],
                        "gtr_id" => $grupo_trabajo_proveedor['gtr_id'],
                        "pro_id" => $grupo_trabajo_proveedor['pro_id'],
                        "ofe_id" => $grupo_trabajo_proveedor['ofe_id'],
                        "estado" => $grupo_trabajo_proveedor['estado']
                    ];

                    array_push($bloque_grupo_trabajo_proveedor, $data);
                }

                if (!empty($bloque_grupo_trabajo_proveedor)) {
                    $bloques = array_chunk($bloque_grupo_trabajo_proveedor, 100);
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
                                'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_ASOCIARPROVEEDOR,
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
                'message' => 'Errores al asociar los proveedores a un(a) ' . $this->nombre,
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Permite realizar las validaciones correspondientes sobre la data enviada en la petición de asociar proveedores y cambiar estado.
     *
     * @param Array  $data Información que llega en el request 
     * @param Boolean  $cambiarEstado Identifica si llega por la acción de cambiar estado
     * @return Array Id del grupo de trabajo, proveedor y ofe 
     */
    private function validarDatos(Array $data, $cambiarEstado) {
        $returnData = [];
        // Obtiene la información del usuario autenticado
        $this->user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select([
                'ofe_id',
                'ofe_identificacion',
                'ofe_correo',
                'ofe_recepcion_fnc_activo',
                DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo'),
                'bdd_id_rg'
            ])
            ->where('ofe_identificacion', $data['ofe_identificacion'])
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if ($ofe) {
            // Valida que el proveedor exista para el Ofe seleccionado
            $proveedor = ConfiguracionProveedor::select([
                    'pro_id',
                    'pro_identificacion',
                    'pro_correo',
                    DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                ])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_identificacion', $data['pro_identificacion'])
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$proveedor) {
                $this->errors = $this->adicionarError($this->errors, ["El Proveedor con identificación [{$data['pro_identificacion']}] no existe para el OFE con identificación [{$data['ofe_identificacion']}] o se encuentra en estado INACTIVO."]);
            }

            if ($cambiarEstado) {
                // Valida que el grupo de trabajo exista para el Ofe seleccionado
                $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_id', 'gtr_nombre'])
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('gtr_codigo', $data['gtr_codigo'])
                    ->where('estado', 'ACTIVO')
                    ->first();
            
                if (!$grupoTrabajo) {
                    $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con código [{$data['gtr_codigo']}] no existe para el OFE con identificación [{$data['ofe_identificacion']}] o se encuentra en estado INACTIVO."]);
                }
            }
        } else {
            $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación [{$data['ofe_identificacion']}] no existe o se encuentra en estado INACTIVO."]);
        }

        if (empty($this->errors)) {
            $returnData['ofe_id']                   = $ofe->ofe_id;
            $returnData['pro_id']                   = $proveedor->pro_id;
            $returnData['ofe_identificacion']       = $ofe->ofe_identificacion;
            $returnData['ofe_recepcion_fnc_activo'] = $ofe->ofe_recepcion_fnc_activo;
            $returnData['ofe_correo']               = $ofe->ofe_correo;
            $returnData['ofe_razon_social']         = $ofe->nombre_completo;
            $returnData['pro_identificacion']       = $proveedor->pro_identificacion;
            $returnData['pro_correo']               = $proveedor->pro_correo;
            $returnData['pro_razon_social']         = $proveedor->nombre_completo;
            if ($cambiarEstado)
                $returnData['gtr_id']         = $grupoTrabajo->gtr_id;
        }

        return $returnData;
    }

    /**
     * Cambia el estado de los proveedores asociados a los grupos de trabajo.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function cambiarEstado(Request $request) {
        $this->errors = [];

        foreach ($request->all() as $registro) {
            if(!is_array($registro)) continue;

            // Se realiza la validación de los datos
            $respuesta = $this->validarDatos($registro, true);

            if (!empty($respuesta)) {
                $modelo = $this->className::where('gtr_id', $respuesta['gtr_id'])
                    ->where('pro_id', $respuesta['pro_id'])
                    ->first();

                if ($modelo) {
                    $strEstado = ($modelo->estado == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                    $modelo->update(['estado' => $strEstado]);

                    if(!$modelo)
                        $this->errors = $this->adicionarError($this->errors, ["No fue posible actualizar el proveedor con identificación [{$registro['pro_identificacion']}] asociado a {$this->nombre} con código [{$registro['gtr_codigo']}]."]);
                } else {
                    $this->errors = $this->adicionarError($this->errors, ["El Proveedor con identificación [{$registro['pro_identificacion']}] asociado a {$this->nombre} con código [{$registro['gtr_codigo']}] no existe."]);
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
     * Obtiene la lista de errores de procesamiento de cargas masivas de grupos de trabajo proveedores.
     * 
     * @param Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getListaErroresGruposTrabajoProveedores(Request $request) {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_ASOCIARPROVEEDOR);
    }

    /*
     * Retorna una lista en Excel de errores de cargue de grupos de trabajo proveedores.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function descargarListaErroresGruposTrabajoProveedores(Request $request) {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_ASOCIARPROVEEDOR, true, 'carga_asociar_proveedores_log_errores');
    }

    /**
     * Retorna la lista de los proveedores asociados a un grupo de trabajo específico.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function listarProveedoresAsociados(Request $request) {
        // Valida que el grupo de trabajo exista para el Ofe en proceso
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
            ->where('ofe_identificacion', $request->nitOfe)
            ->validarAsociacionBaseDatos()
            ->first();

        if (!$ofe) {
            return response()->json([
                'message' => "Error al listar los proveedores asociados",
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
                'message' => "Error al listar los proveedores asociados",
                'errors'  => ["{$this->nombre} con código [{$request->codigoGrupo}] no existe para el OFE con identificación [{$request->nitOfe}]"]
            ], 422);
        }

        $totalProveedoresAsociados = $this->className::where('gtr_id', $grupoTrabajo->gtr_id)->count();
        $length = isset($request->length) ? $request->length : 10;
        $start = isset($request->start) ? $request->start : 0;

        $buscador = $this->className::select([
                'etl_grupos_trabajo_proveedores.gtp_id',
                'etl_grupos_trabajo_proveedores.gtr_id',
                'etl_grupos_trabajo_proveedores.pro_id'
            ])
            ->with('getProveedor')
            ->where('gtr_id', $grupoTrabajo->gtr_id);

        if (isset($request->buscar) && !empty(trim($request->buscar))) {
            $buscador->where(function($query) use ($request){
                $query->with('getProveedor')
                ->wherehas('getProveedor', function ($query) use ($request) {
                    $query->where('pro_identificacion', 'like', '%'.$request->buscar.'%')
                    ->orWhere('pro_razon_social', 'like', '%'.$request->buscar.'%')
                    ->orWhere('pro_primer_apellido', 'like', '%'.$request->buscar.'%')
                    ->orWhere('pro_segundo_apellido', 'like', '%'.$request->buscar.'%')
                    ->orWhere('pro_primer_nombre', 'like', '%'.$request->buscar.'%')
                    ->orWhere('pro_otros_nombres', 'like', '%'.$request->buscar.'%')
                    ->orWhere('pro_correo', 'like', '%'.$request->buscar.'%')
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
            'total'     => $totalProveedoresAsociados
        ], 200);
    }

    /**
     * Lista los grupos de trabajo de un proveedor para poder ser utilizados en un combo select.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listarGruposTrabajoProveedor(Request $request): JsonResponse {
        $gtrProveedor = $this->className::select(['gtr_id'])
            ->where('pro_id', $request->pro_id)
            ->where('estado', 'ACTIVO')
            ->with([
                'getGrupoTrabajo' => function ($query) {
                    $query->select(['gtr_id', 'gtr_codigo', 'gtr_nombre'])
                        ->where('estado', 'ACTIVO');
                }
            ])
            ->get()
            ->pluck('getGrupoTrabajo')
            ->filter()
            ->values();

        return response()->json([
            'data' =>  [
                'grupos_trabajo_proveedor' => $gtrProveedor
            ]
        ], 200);
    }
}
