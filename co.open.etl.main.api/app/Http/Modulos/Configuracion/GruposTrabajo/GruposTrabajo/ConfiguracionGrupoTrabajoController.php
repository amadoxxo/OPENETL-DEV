<?php

namespace App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use openEtl\Tenant\Traits\TenantTrait;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Controllers\OpenTenantController;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionGrupoTrabajoController extends OpenTenantController {
    use PackageMainTrait;

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
    public $className = ConfiguracionGrupoTrabajo::class;

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
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $nombreDatoCambiarEstado = 'grupos_trabajo';

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
     * Nombre del archivo Excel.
     * 
     * @var String
     */
    public $nombreArchivo = '';

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
        $user = auth()->user();
        $variableSistemaNotificarAsignacion = '';
        if ($user) {
            // Se obtiene el valor de la variable del sistema para identificar el nombre del grupo
            TenantTrait::GetVariablesSistemaTenant();
            $variableSistema    = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
            $variableSistemaNotificarAsignacion  = config('variables_sistema_tenant.NOTIFICAR_ASIGNACION_GRUPO_TRABAJO');
            $arrVariableSistema = json_decode($variableSistema, true);

            $this->nombre                      = ucwords($arrVariableSistema['singular']);
            $this->nombrePlural                = ucwords($arrVariableSistema['plural']);
            $this->nombreArchivo               = $this->sanear_string(strtolower($arrVariableSistema['plural']));
            $this->mensajeErrorCreacion422     = 'No Fue Posible Crear ' . $arrVariableSistema['singular'];
            $this->mensajeErroresCreacion      = 'Errores al Crear ' . $arrVariableSistema['singular'];
            $this->mensajeErrorModificacion422 = 'Errores al modificar ' . $arrVariableSistema['singular'];
            $this->mensajeErroresModificacion  = 'Errores al modificar ' . $arrVariableSistema['singular'];
            $this->mensajeObjectNotFound       = 'El Id de ' . $arrVariableSistema['singular'] . ' [%s] no Existe';
            $this->mensajeObjectDisabled       = 'El Id de ' . $arrVariableSistema['singular'] . ' [%s] Esta Inactivo';
        }

        // Se almacenan las columnas que se generan en la interfaz de Excel
        $this->columnasExcel = [
            'NIT OFE',
            'CODIGO',
            'NOMBRE',
            strtoupper($this->nombre) . ' POR DEFECTO',
            'CORREOS NOTIFICACION'
        ];

        if ($variableSistemaNotificarAsignacion == 'NO') {
            unset($this->columnasExcel['4']);
        }

        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAdministracion,ConfiguracionGrupoTrabajoAdministracionNuevo,ConfiguracionGrupoTrabajoAdministracionEditar,ConfiguracionGrupoTrabajoAdministracionCambiarEstado,ConfiguracionGrupoTrabajoAdministracionSubir,ConfiguracionGrupoTrabajoAdministracionVer,ConfiguracionGrupoTrabajoAdministracionDescargarExcel'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'busqueda',
            'showCompuesto',
            'updateCompuesto',
            'generarInterfaceGruposTrabajo',
            'cargarGruposTrabajo',
            'getListaErroresGruposTrabajo',
            'descargarListaErroresGruposTrabajo',
            'searchGruposTrabajo'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAdministracionNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAdministracionEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAdministracionVer,ConfiguracionGrupoTrabajoAdministracionEditar'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAdministracionVer'
        ])->only([
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAdministracionCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionGrupoTrabajoAdministracionSubir'
        ])->only([
            'generarInterfaceGruposTrabajo',
            'cargarGruposTrabajo',
            'getListaErroresGruposTrabajo',
            'descargarListaErroresGruposTrabajo'
        ]);
    }

    /**
     * Configura las reglas para poder actualizar o crear la información de los grupos de trabajo.
     *
     * @return mixed
     */
    private function getRules() {
        $rules = array_merge($this->className::$rules);

        unset(
            $rules['ofe_id']
        );

        $rules['ofe_identificacion'] = 'required|string|max:20';

        return $rules;
    }

    /**
     * Devuelve una lista paginada de registros.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function getListaGruposTrabajo(Request $request) {
        $user = auth()->user();

        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'etl_grupos_trabajo.gtr_id',
            'etl_grupos_trabajo.ofe_id',
            'etl_grupos_trabajo.gtr_codigo',
            'etl_grupos_trabajo.gtr_nombre',
            'etl_grupos_trabajo.gtr_servicio',
            'etl_grupos_trabajo.gtr_por_defecto',
            'etl_grupos_trabajo.gtr_correos_notificacion',
            'etl_grupos_trabajo.usuario_creacion',
            'etl_grupos_trabajo.fecha_creacion',
            'etl_grupos_trabajo.fecha_modificacion',
            'etl_grupos_trabajo.estado',
            'etl_grupos_trabajo.fecha_actualizacion'
        ];

        $relaciones = [
            'getUsuarioCreacion',
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
                'function' => function($query){
                    $query->whereNull('bdd_id_rg');
                }
            ];
        }

        $exportacion = [
            'columnas' => [
                'ofe_id' => [
                    'label' => 'NIT OFE',
                    'relation' => ['name' => 'getConfiguracionObligadoFacturarElectronicamente', 'field' => 'ofe_identificacion']
                ],
                'gtr_codigo' => 'CODIGO',
                'gtr_nombre' => 'NOMBRE',
                'gtr_por_defecto' => strtoupper($this->nombre) . ' POR DEFECTO',
                'gtr_correos_notificacion' => 'CORREOS NOTIFICACION',
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
     * Genera una interfaz de grupos de trabajo para guardar en Excel.
     *
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceGruposTrabajo(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, $this->nombreArchivo);  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de grupos de trabajo.
     *
     * @param Request $request
     * @return
     * @throws \Exception
     */
    public function cargarGruposTrabajo(Request $request){
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
                    'message' => 'Errores al guardar ' . $this->nombrePlural,
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
                        $columna['usuario_creacion'],
                        $columna['fecha_creacion'],
                        $columna['fecha_modificacion'],
                        $columna['estado']
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
                    'message' => 'Errores al guardar ' . $this->nombrePlural,
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $grupoPorDefecto =  $this->sanear_string(strtolower($this->nombre)) . '_por_defecto';
            $arrErrores    = [];
            $arrResultado  = [];
            $arrExisteOfe  = [];
            $arrGrupoTrabajoProceso = [];
            $arrExisteGrupoTrabajo = [];
            $arrExisteGrupoPorDefecto = [];

            foreach ($data as $fila => $columnas) {
                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrGrupoTrabajo = [];
                $arrFaltantes = $this->checkFields($Acolumnas, [
                    'nit_ofe',
                    'codigo',
                    'nombre'
                ], $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {
                    $arrGrupoTrabajo['gtr_id'] = 0;

                    $arrGrupoTrabajo['ofe_id'] = null;
                    if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)){
                        $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                    } else {
                        $objExisteOfe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
                            ->where('ofe_identificacion', $columnas->nit_ofe)
                            ->validarAsociacionBaseDatos()
                            ->where('estado', 'ACTIVO')
                            ->first();
                        $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                    }

                    if (!empty($objExisteOfe)) {
                        $arrGrupoTrabajo['ofe_id'] = $objExisteOfe->ofe_id;
                        
                        if (array_key_exists($columnas->nit_ofe.'-'.$columnas->codigo, $arrExisteGrupoTrabajo)) {
                            $objExisteGrupoTrabajo = $arrExisteGrupoTrabajo[$columnas->nit_ofe.'-'.$columnas->codigo];
                            $arrGrupoTrabajo['gtr_id'] = $objExisteGrupoTrabajo->gtr_id;
                        } else {
                            $objExisteGrupoTrabajo = $this->className::where('ofe_id', $objExisteOfe->ofe_id)
                                ->where('gtr_codigo', $columnas->codigo)
                                ->first();

                            if ($objExisteGrupoTrabajo){
                                $arrExisteGrupoTrabajo[$columnas->nit_ofe.'-'.$columnas->codigo] = $objExisteGrupoTrabajo;
                                $arrGrupoTrabajo['gtr_id'] = $objExisteGrupoTrabajo->gtr_id;
                            }
                        }

                        if (property_exists($columnas, $grupoPorDefecto) && isset($columnas->$grupoPorDefecto) && !empty($columnas->$grupoPorDefecto)) {
                            if ($columnas->$grupoPorDefecto !== 'SI' && $columnas->$grupoPorDefecto !== 'NO' && $columnas->$grupoPorDefecto !== "") {
                                $arrErrores = $this->adicionarError($arrErrores, ["El campo " . strtolower($this->nombre) . " por defecto debe contener SI o NO como valor."], $fila);
                            }

                            if (array_key_exists($columnas->nit_ofe.'-'.$columnas->$grupoPorDefecto, $arrExisteGrupoPorDefecto) && $columnas->$grupoPorDefecto == 'SI'){
                                $arrErrores = $this->adicionarError($arrErrores, ["El OFE con identificación {$columnas->nit_ofe} ya tiene seleccionado un(a) " . strtolower($this->nombre) . " por defecto en otras filas"], $fila);
                            } else {
                                $arrExisteGrupoPorDefecto[$columnas->nit_ofe.'-'.$columnas->$grupoPorDefecto] = $columnas->nit_ofe.'-'.$columnas->$grupoPorDefecto;
                            }

                            if ($columnas->$grupoPorDefecto == 'SI') {
                                $existeGrupoPorDefecto = $this->className::select('gtr_codigo')
                                    ->where('gtr_por_defecto', 'SI')
                                    ->where('ofe_id', $objExisteOfe->ofe_id);
                                    if ($objExisteGrupoTrabajo)
                                        $existeGrupoPorDefecto->where('gtr_id', '!=', $objExisteGrupoTrabajo->gtr_id);

                                $existeGrupoPorDefecto = $existeGrupoPorDefecto->first();
                                if ($existeGrupoPorDefecto) {
                                    $arrErrores = $this->adicionarError($arrErrores, ["El OFE con identificación {$columnas->nit_ofe} ya tiene resgitrado un(a) " . strtolower($this->nombre) . " por defecto"], $fila);
                                }
                            }
                        }
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ["El OFE con identificación {$columnas->nit_ofe} no existe o se encuentra INACTIVO."], $fila);
                    }

                    $llaveGrupoTrabajo = $columnas->nit_ofe . '|' . $columnas->codigo;
                    if (array_key_exists($llaveGrupoTrabajo, $arrGrupoTrabajoProceso)) {
                        $arrErrores = $this->adicionarError($arrErrores, ["{$this->nombre} con código {$columnas->codigo} para el OFE {$columnas->nit_ofe} ya existe en otras filas."], $fila);
                    } else {
                        $arrGrupoTrabajoProceso[$llaveGrupoTrabajo] = true;
                    }

                    $arrGrupoTrabajo['gtr_codigo']               = $this->sanitizarStrings($columnas->codigo);
                    $arrGrupoTrabajo['gtr_nombre']               = $this->sanitizarStrings($columnas->nombre);
                    $arrGrupoTrabajo['gtr_por_defecto']          = $this->sanitizarStrings($columnas->$grupoPorDefecto);
                    $arrGrupoTrabajo['gtr_correos_notificacion'] = isset($columnas->correos_notificacion) && $this->soloEmails($columnas->correos_notificacion) != '' ? $this->soloEmails($columnas->correos_notificacion) : null;

                    if(count($arrErrores) == 0){
                        $objValidator = Validator::make($arrGrupoTrabajo, $this->className::$rules);
                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrGrupoTrabajo;
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
                    'pjj_tipo'                => ProcesarCargaParametricaCommand::$TYPE_GRUPOTRABAJO,
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
                    'message' => 'Errores al guardar ' . $this->nombrePlural,
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            } else {
                TenantTrait::GetVariablesSistemaTenant();
                $variableSistemaCorreos = config('variables_sistema_tenant.NOTIFICAR_ASIGNACION_GRUPO_TRABAJO');
                $bloque_grupo_trabajo = [];

                foreach ($arrResultado as $grupo_trabajo) {
                    $strCorreos = ($this->soloEmails($grupo_trabajo['gtr_correos_notificacion']) != '' && $variableSistemaCorreos == 'SI') ? $this->soloEmails($grupo_trabajo['gtr_correos_notificacion']) : null;

                    $data = [
                        "gtr_id"                   => $grupo_trabajo['gtr_id'],
                        "ofe_id"                   => $grupo_trabajo['ofe_id'],
                        "gtr_codigo"               => $this->sanitizarStrings($grupo_trabajo['gtr_codigo']),
                        "gtr_nombre"               => $this->sanitizarStrings($grupo_trabajo['gtr_nombre']),
                        "gtr_servicio"             => "RECEPCION",
                        "gtr_por_defecto"          => $this->sanitizarStrings($grupo_trabajo['gtr_por_defecto']),
                        "gtr_correos_notificacion" => $strCorreos
                    ];

                    array_push($bloque_grupo_trabajo, $data);
                }

                if (!empty($bloque_grupo_trabajo)) {
                    $bloques = array_chunk($bloque_grupo_trabajo, 100);
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
                                'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_GRUPOTRABAJO,
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
                'message' => 'Errores al guardar ' . $this->nombrePlural,
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Obtiene el registro del grupo de trabajo especificado.
     *
     * @param   $id Identificador del grupo de trabajo
     * @return Response
     */
    public function show($id){
        return $this->procesadorShow($id, [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente'
        ]);
    }

    /**
     * Obtiene un grupo de trabajo especificado por la identificación del Ofe al que está asociado y el código del grupo.
     *
     * @param int    $ofe_identificacion Identificación del Ofe
     * @param string $codigo_grupo Código del grupo de trabajo
     * @return Response
     */
    public function showCompuesto($ofe_identificacion, $codigo_grupo) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente'
        ];

        $objOfe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$objOfe) {
            return response()->json([
                'message' => sprintf("El OFE con identificación [%s] no existe o se encuentra en estado INACTIVO.", $ofe_identificacion),
                'errors'  => []
            ], Response::HTTP_NOT_FOUND);
        }

        $objetoModel = $this->className::where('ofe_id', $objOfe->ofe_id)
            ->where('gtr_codigo', $codigo_grupo);

        if (!empty($arrRelaciones))
            $objetoModel = $objetoModel->with($arrRelaciones);

        $objetoModel = $objetoModel->first();

        if($objetoModel){
            $arrTrabajador = $objetoModel->toArray();

            return response()->json([
                'data' => $arrTrabajador
            ], Response::HTTP_OK);
        } 

        return response()->json([
            'message' => sprintf("{$this->nombre} con código [%s], para el OFE con identificación [%s] no existe o se encuentra en estado INACTIVO.", $codigo_grupo, $ofe_identificacion),
            'errors'  => []
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Permite crear un grupo de trabajo.
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request){
        $this->errors = [];
        // Obtiene la información del usuario autenticado
        $this->user = auth()->user();

        $data = $request->all();
        $validador = Validator::make($data, $this->getRules());

        $validatorEmail = $this->validationEmailRule($request->gtr_correos_notificacion);
        if (!empty($validatorEmail['errors']))
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors'  => $validatorEmail['errors']
            ], 404);

        if (!$validador->fails()) {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$ofe) {
                return response()->json([
                    'message' => $this->mensajeErroresCreacion,
                    'errors'  => ["El OFE con identificación {$request->ofe_identificacion} no existe o se encuentra en estado INACTIVO."]
                ], 404);
            }

            $existe = $this->className::where('ofe_id', $ofe->ofe_id)
                ->where('gtr_codigo', $request->gtr_codigo)
                ->first();

            if ($existe) {
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con Código [{$request->gtr_codigo}] para el OFE con identificación [{$request->ofe_identificacion}] ya existe."]);
            }

            if ($request->gtr_por_defecto == 'SI') {
                $existeGrupoPorDefecto = $this->className::select('gtr_codigo')
                    ->where('ofe_id', $ofe->ofe_id)
                    ->where('gtr_por_defecto', 'SI')
                    ->first();

                if ($existeGrupoPorDefecto) {
                    $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación [{$request->ofe_identificacion}] solamente puede tener un(a) " . strtolower($this->nombre) . " por defecto"]);
                }
            }

            if (empty($this->errors)) {
                $data['ofe_id']                   = $ofe->ofe_id;
                $data['gtr_servicio']             = "RECEPCION";
                $data['estado']                   = 'ACTIVO';
                $data['usuario_creacion']         = $this->user->usu_id;
                $obj = $this->className::create($data);

                if($obj){
                    return response()->json([
                        'success' => true,
                        'gtr_id'  => $obj->gtr_id
                    ], 200);
                }
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
     * Actualiza un grupo de trabajo en específico.
     *
     * @param  Request  $request
     * @param  int    $ofe_id Id del Ofe que llega en el request
     * @param  string $codigo_grupo Código del grupo de trabajo
     * @return Response
     */
    public function update(Request $request, $ofe_id, $codigo_grupo){
        $data = $request->all();
        $validador = Validator::make($data, $this->getRules());
        if (!$validador->fails()) {
            $objetoGrupoTrabajo = $this->className::where('ofe_id', $ofe_id)
                ->where('gtr_codigo', $codigo_grupo)
                ->first();

            // Valida que no exista un grupo de trabajo con el mismo código y para el mismo OFE
            $existe = $this->className::where('ofe_id', $request->ofe_id)
                ->where('gtr_codigo', $request->gtr_codigo)
                ->where('gtr_id', '!=', $objetoGrupoTrabajo->gtr_id)
                ->first();

            if ($existe) {
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con código [{$request->gtr_codigo}] para el OFE con identificación [{$request->ofe_identificacion}] ya existe"]);
            }

            // Si se envía grupo por defecto en SI, se valida que el OFE no tenga asiganado un grupo por defecto en SI
            if ($request->gtr_por_defecto == 'SI') {
                $existeGrupoPorDefecto = $this->className::select('gtr_codigo')
                    ->where('ofe_id', $request->ofe_id)
                    ->where('gtr_por_defecto', 'SI')
                    ->where('gtr_id', '!=', $objetoGrupoTrabajo->gtr_id)
                    ->first();

                if ($existeGrupoPorDefecto) {
                    $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación [{$request->ofe_identificacion}] solamente puede tener un(a) " . strtolower($this->nombre) . " por defecto"]);
                }
            }

            if (empty($this->errors)) {
                $data['ofe_id']       = $request->ofe_id;
                $data['gtr_servicio'] = "RECEPCION";
                $obj = $objetoGrupoTrabajo->update($data);

                if($obj){
                    return response()->json([
                        'success' => true
                    ], 200);
                }
            }

            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors'  => $this->errors
            ], 422);
        }

        return response()->json([
            'message' => $this->mensajeErroresModificacion,
            'errors'  => $validador->errors()->all()
        ], 400);
    }

    /**
     * Permite editar un grupo de trabajo en función de un Ofe.
     *
     * @param Request $request
     * @param int    $ofe_identificacion Identificación del Ofe
     * @param string $codigo_grupo Código del grupo de trabajo
     * @return JsonResponse
     */
    public function updateCompuesto(Request $request, $ofe_identificacion, $codigo_grupo) {
        $this->errors = [];
        $this->user = auth()->user();

        $validatorEmail = $this->validationEmailRule($request->gtr_correos_notificacion);
        if (!empty($validatorEmail['errors']))
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors'  => $validatorEmail['errors']
            ], 404);

        $objOfe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$objOfe) {
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors'  => ["El OFE con identificación [{$ofe_identificacion}] no existe o se encuentra INACTIVO."]
            ], 404);
        }

        $objGrupoTrabajo = $this->className::select('gtr_id', 'ofe_id', 'gtr_codigo')
            ->where('ofe_id', $objOfe->ofe_id)
            ->where('gtr_codigo', $codigo_grupo)
            ->first();

        if (!empty($objGrupoTrabajo)) {
            // Si el OFE cambia, se valida que el grupo no exista asociado a un proveedor
            if ($ofe_identificacion != $request->ofe_identificacion) {
                $existeGrupoProveedor = ConfiguracionGrupoTrabajoProveedor::select('gtp_id')
                    ->where('gtr_id', $objGrupoTrabajo->gtr_id)
                    ->first();

                if (!empty($existeGrupoProveedor)) {
                    $this->errors = $this->adicionarError($this->errors, ["No es posible modificar el OFE a {$this->nombre} con código [{$codigo_grupo}], este ya existe asociado a un proveedor."]);
                }
            }

            // Valida que el Ofe enviado en el request exista
            $objRequestOfe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$objRequestOfe) {
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors'  => ["El OFE con identificación [{$request->ofe_identificacion}] no existe o se encuentra INACTIVO."]
                ], 404);
            }

            $request->merge([
                'ofe_id' => $objRequestOfe->ofe_id
            ]);
            return $this->update($request, $objOfe->ofe_id, $codigo_grupo);
        } else {
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors'  => ["{$this->nombre} con código [{$codigo_grupo}], para el OFE con identificación [{$ofe_identificacion}] no existe"]
            ], 404);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de grupos de trabajo.
     * 
     * @param Illuminate\Http\Request $request
     * @return Response
     */
    public function getListaErroresGruposTrabajo(Request $request) {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_GRUPOTRABAJO);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de grupos de trabajo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Response
     */
    public function descargarListaErroresGruposTrabajo(Request $request) {
        $nombreExcel = 'carga_' . $this->nombreArchivo . '_log_errores';
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_GRUPOTRABAJO, true, $nombreExcel);
    }

    /**
     * Efectua un proceso de búsqueda sobre grupos de trabajo.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function busqueda(Request $request) {
        $columnas = [
            'gtr_id',
            'ofe_id',
            'gtr_codigo',
            'gtr_nombre',
            'gtr_servicio',
            'gtr_por_defecto'
        ];

        $incluir = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente'
        ];

        $oferente = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $request->valorOfe)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if ($oferente) {
            return $this->procesadorBusqueda($request, $columnas, $incluir, []);
        } else {
            return response()->json([
                'data' => [],
            ], Response::HTTP_OK);
        }
    }

    /**
     * Cambia el estado de los grupos de trabajo.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function cambiarEstado(Request $request) {
        return $this->procesadorCambiarEstadoCompuesto(
            ConfiguracionObligadoFacturarElectronicamente::class,
            'ofe_id',
            'ofe_identificacion',
            'gtr_codigo',
            'gtr_codigo',
            $request->all(),
            'El OFE [%s] no existe o se encuentra INACTIVO'
        );
    }

    /**
     * Obtiene los grupos de trabajo dado un ofe_id y el término a buscar.
     *
     * @param \Illuminate\Http\Request $request
     * @return Response
     */
    public function searchGruposTrabajo(Request $request) {
        $grupos = $this->className::where('estado', 'ACTIVO');
        $grupos->select(
            [
                'gtr_id',
                'gtr_codigo',
                'gtr_nombre'
            ]);

        if ($request->has('ofe_id') && !empty($request->ofe_id)) {
            $grupos->where('ofe_id', $request->ofe_id);
        }

        $grupos->where(function ($query) use ($request) {
            $query->where('gtr_codigo', 'like', '%' . $request->buscar . '%')
                ->orWhere('gtr_nombre', 'like', '%' . $request->buscar . '%');
        });

        return response()->json([
            'data' => $grupos->get()
        ], 200);
    }
}
