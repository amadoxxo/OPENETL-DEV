<?php

namespace App\Http\Modulos\Configuracion\ResolucionesFacturacion;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\OpenTenantController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Documentos\EtlConsecutivosDocumentos\EtlConsecutivoDocumento;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionResolucionesFacturacionController extends OpenTenantController{
    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Resolución de Facturación';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Resoluciones de Facturación';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var Model
     */
    public $className = ConfiguracionResolucionesFacturacion::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear la Resolución de Facturación';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear la Resolución de Facturación';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar la Resolución de Facturación';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar la Resolución de Facturación';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id de la Resolución de Facturación [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id de la Resolución de Facturación [%s] Esta Inactivo';

    /**
     * Propiedad Contiene las datos del usuario autenticado.
     *
     * @var Object
     */
    protected $user;

    /**
     * Nombre del campo que contiene los valores que permiten buscar los registros a modificar.
     * 
     * @var String
     */
    public $nombreDatoCambiarEstado = 'resoluciones';

    /**
     * Nombre del campo que contiene el número identificador del registro.
     * 
     * @var String
     */
    public $nombreCampoIdentificacion = 'rfa_resolucion';

    /**
     * Almacena las columnas que se generan en la interfaz de Excel.
     * 
     * @var Array
     */
    public $columnasExcel = [
        'NIT OFE',
        'TIPO',
        'RESOLUCION',
        'PREFIJO',
        'CLAVE TECNICA',
        'FECHA DESDE',
        'FECHA HASTA',
        'CONSECUTIVO INICIAL',
        'CONSECUTIVO FINAL',
        'APLICA CONTROL DE CONSECUTIVOS',
        'APLICA CONSECUTIVO PROVISIONAL',
        'DIAS AVISO',
        'CONSECUTIVOS AVISO'
    ];

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
            'VerificaMetodosRol:ConfiguracionResolucionesFacturacion,ConfiguracionResolucionesFacturacionNuevo,ConfiguracionResolucionesFacturacionEditar,ConfiguracionResolucionesFacturacionVer,ConfiguracionResolucionesFacturacionCambiarEstado,ConfiguracionResolucionesFacturacionSubir'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'busqueda',
            'generarInterfaceResolucionFacturacion', 
            'cargarResolucionFacturacion',
            'getListaErroresResolucionFacturacion',
            'descargarListaErroresResolucionFacturacion',
            'resolucionesVencidas'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionResolucionesFacturacionNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionResolucionesFacturacionEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionResolucionesFacturacionVer,ConfiguracionResolucionesFacturacionEditar'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionResolucionesFacturacionVer'
        ])->only([
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionResolucionesFacturacionCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionResolucionesFacturacionSubir'
        ])->only([
            'generarInterfaceResolucionFacturacion', 
            'cargarResolucionFacturacion',
            'getListaErroresResolucionFacturacion',
            'descargarListaErroresResolucionFacturacion'
        ]);
    }

    /**
     * Muestra una lista de resoluciones de facturación.
     *
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function getListaResolucionesFacturacion(Request $request){
        $user = auth()->user();

        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'rfa_id',
            'ofe_id',
            'rfa_resolucion',
            'rfa_prefijo',
            'rfa_clave_tecnica',
            'rfa_tipo',
            'rfa_fecha_desde',
            'rfa_fecha_hasta',
            'rfa_consecutivo_inicial',
            'rfa_consecutivo_final',
            'cdo_control_consecutivos',
            'cdo_consecutivo_provisional',
            'rfa_dias_aviso',
            'rfa_consecutivos_aviso',
            'fecha_creacion',
            'fecha_modificacion',
            'usuario_creacion',
            'estado',
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres',
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
                'ofe_id' =>  [
                    'label' => 'NIT OFE',
                    'relation' => ['name' => 'getConfiguracionObligadoFacturarElectronicamente', 'field' => 'ofe_identificacion']
                ],
                'rfa_tipo'                    => 'TIPO',
                'rfa_resolucion'              => 'RESOLUCION',
                'rfa_prefijo'                 => 'PREFIJO',
                'rfa_clave_tecnica'           => 'CLAVE TECNICA',
                'rfa_fecha_desde'             => 'FECHA DESDE',
                'rfa_fecha_hasta'             => 'FECHA HASTA',
                'rfa_consecutivo_inicial'     => 'CONSECUTIVO INICIAL',
                'rfa_consecutivo_final'       => 'CONSECUTIVO FINAL',
                'cdo_control_consecutivos'    => 'APLICA CONTROL DE CONSECUTIVOS',
                'cdo_consecutivo_provisional' => 'APLICA CONSECUTIVO PROVISIONAL',
                'rfa_dias_aviso'              => 'DIAS AVISO',
                'rfa_consecutivos_aviso'      => 'CONSECUTIVOS AVISO',
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
            'titulo' => 'resolucion_facturacion'
        ];

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Configura las reglas para poder actualizar o crear los datos de las resoluciones de facturación.
     *
     * @param string $rfaTipo Yipo de resolución
     * @return mixed
     */
    private function getRules(string $rfaTipo) {
        $rules = array_merge($this->className::$rules);
        unset($rules['ofe_id']);
        $rules['ofe_identificacion'] = 'required|string|max:20';

        // Se modifica la regla del campo rfa_prefijo debido a modificaciones relacionadas con el Anexo Técnico 1.8
        $rules['rfa_prefijo'] = 'nullable|string|max:4';

        if($rfaTipo !== 'DOCUMENTO_SOPORTE')
            $rules['rfa_clave_tecnica'] = 'required|string';
        else
            $rules['rfa_clave_tecnica'] = 'nullable|string';

        return $rules;
    }
    
    /**
     * Almacena una resolución recién creada en el almacenamiento.
     *
     * @param  Request $request
     * @return Response
     */
    public function store(Request $request){
        $arrErrores = [];
        // Usuario autenticado
        $this->user = auth()->user();
        $data      = $request->all();
        $validador = Validator::make($request->all(), $this->getRules($request->rfa_tipo));

        if (!$validador->fails()) {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if ($ofe === null) {
                return response()->json([
                    'message' => 'Error al crear la Resolución de Facturación',
                    'errors' => ["El OFE {$request->ofe_identificacion} no existe o se encuentra INACTIVO."]
                ], 404);
            }

            if ($request->filled('rfa_dias_aviso') && !preg_match('/^\d+$/', $request->rfa_dias_aviso))
                $this->adicionarErrorArray($arrErrores, ["Los días de aviso debe contener un número entero."]);

            if ($request->filled('rfa_consecutivos_aviso') && !preg_match('/^\d+$/', $request->rfa_consecutivos_aviso))
                $this->adicionarErrorArray($arrErrores, ["Los consecutivos aviso debe contener un número entero."]);

            $existe = $this->className::where('rfa_resolucion', $request->rfa_resolucion)
                ->where('ofe_id', $ofe->ofe_id);

            if (isset($request->rfa_prefijo) && !is_null($request->rfa_prefijo))
                $existe->where('rfa_prefijo', $request->rfa_prefijo);
            else
                $existe->whereNull('rfa_prefijo');

            $existe = $existe->first();

            if ($existe !== null) {
                $resolucion = ($request->rfa_prefijo ?? '') . $request->rfa_resolucion;
                $this->adicionarErrorArray($arrErrores, ["La resolución de facturación {$resolucion} ya existe."]);
            }

            if (!empty($arrErrores))
                return response()->json([
                    'message' => 'Error al modificar la resolución de facturación',
                    'errors' => $arrErrores
                ], 422);

            unset($data['ofe_identificacion']);

            if (!$request->filled('rfa_clave_tecnica') || ($request->filled('rfa_clave_tecnica') && $request->rfa_tipo == 'DOCUMENTO_SOPORTE'))
                $data['rfa_clave_tecnica'] = null;

            $data['ofe_id']           = $ofe->ofe_id;
            $data['estado']           = 'ACTIVO';
            $data['usuario_creacion'] = auth()->user()->usu_id;
            $obj                      = $this->className::create($data);

            if($obj){
                if($request->has('cdo_control_consecutivos') && $request->cdo_control_consecutivos == 'SI')
                    $this->crearConsecutivo($ofe->ofe_id, $obj->rfa_id, $obj->rfa_prefijo, $obj->rfa_consecutivo_inicial, 'DEFINITIVO');

                if($request->has('cdo_consecutivo_provisional') && $request->cdo_consecutivo_provisional == 'SI')
                    $this->crearConsecutivo($ofe->ofe_id, $obj->rfa_id, $obj->rfa_prefijo, '1', 'PROVISIONAL');

                return response()->json([
                    'success' => true,
                    'sft_id'  => $obj->sft_id
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Error al crear la Resolución de Facturación',
                    'errors' => []
                ], 422);
            }
        }
        return response()->json([
            'message' => 'Error al crear la Resolución de Facturación',
            'errors' => $validador->errors()->all()
        ], 400);
    }
    
    /**
     * Muestra la resolución especificada.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id){
        $objectoModel = $this->getResolucion($id, ['getUsuarioCreacion', 'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres']);
        if ($objectoModel){
            return response()->json([
                'data' => $objectoModel
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => is_null($this->nombreCampoIdentificacion) ? "No Se Encontró {$this->nombre} Con ID [{$id}]" : "No Se Encontró {$this->nombre} Con identificación [{$id}]",
            'errors' => []
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Retorna una resolución en funcioón de su prefijo y número.
     *
     * @param $id
     * @param array $relaciones
     * @return mixed
     */
    private function getResolucion($id, array $relaciones = []) {
        try {
            $arrData = explode(':', $id);
            // Valida la cantidad de elementos, ya que cuando es mayor a 3, se envía el tipo de resolución
            if(count($arrData) === 4)
                list($ofe_identificacion, $rfaTipo, $prefijo, $resolucion) = $arrData;
            else
                list($ofe_identificacion, $rfaTipo, $resolucion) = $arrData;
            //Buscando id OFE
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
                ->where('ofe_identificacion', $ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->first();

            $ofe_id = '';
            if ($ofe)  {
                $ofe_id = $ofe->ofe_id;
            }
        } catch (\Exception $e) {
            $ofe_id = $ofe_id ?? '';
            $rfaTipo = $rfaTipo ?? '';
            $prefijo = $prefijo ?? '';
            $resolucion = $resolucion ?? '';
        }

        $objetoRfa = $this->className::where('ofe_id', $ofe_id)
            ->where('rfa_resolucion', $resolucion);

        if(count($arrData) === 4) {
            $objetoRfa = $objetoRfa->where('rfa_prefijo', $prefijo);

            // Se valida el tipo ya que existen resoluciones sin definir el tipo y esta es NULL
            if($arrData[1] === 'null' || $arrData === null)
                $objetoRfa = $objetoRfa->whereNull('rfa_tipo');
            else
                $objetoRfa = $objetoRfa->where('rfa_tipo', $rfaTipo);
        }
        if (!empty($relaciones))
            $objetoRfa->with($relaciones);

        return $objetoRfa->first();
    }
    
    /**
     * Actualiza la resolución especificada en el almacenamiento.
     *
     * @param  Request $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id){
        $arrErrores = [];
        // Usuario autenticado
        $this->user = auth()->user();
        $data = $request->all();
        $validador = Validator::make($request->all(), $this->getRules($request->rfa_tipo));
        if (!$validador->fails()) {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();
            if ($ofe === null) {
                return response()->json([
                    'message' => 'Error al modificar la Resolución de Facturación',
                    'errors' => ["El OFE {$request->ofe_identificacion} no existe no existe o se encuentra INACTIVO."]
                ], 404);
            }

            if ($request->filled('rfa_dias_aviso') && !preg_match('/^\d+$/', $request->rfa_dias_aviso))
                $this->adicionarErrorArray($arrErrores, ["Los días de aviso debe contener un número entero."]);

            if ($request->filled('rfa_consecutivos_aviso') && !preg_match('/^\d+$/', $request->rfa_consecutivos_aviso))
                $this->adicionarErrorArray($arrErrores, ["Los consecutivos aviso debe contener un número entero."]);

            $objetoRfa = $this->getResolucion($id);
            if ($objetoRfa === null)
                $this->adicionarErrorArray($arrErrores, ["La resolución de facturación {$id} no existe."]);

            $buscar = explode(':', $id);

            if ((count($buscar) === 1 && ($buscar[0] !== $request->rfa_resolucion || !empty($request->rfa_prefijo)) ) ||
                (count($buscar) === 2 && ($buscar[0] !== $request->rfa_prefijo || $buscar[1] !== $request->rfa_resolucion ))) {
                $existe = $this->className::where('rfa_resolucion', $request->rfa_resolucion)
                    ->where('ofe_id', $ofe->ofe_id);

                if (empty($request->rfa_prefijo))
                    $existe->whereNull('rfa_prefijo');
                else
                    $existe = $existe->where('rfa_prefijo',$request->rfa_prefijo);
                $existe = $existe->first();

                if ($existe !== null) {
                    $resolucion = ($request->rfa_prefijo ?? '') . $request->rfa_resolucion;
                    $this->adicionarErrorArray($arrErrores, ["La resolución de facturación {$resolucion} ya existe."]);
                }
            }

            if (!empty($arrErrores))
                return response()->json([
                    'message' => 'Error al modificar la resolución de facturación',
                    'errors' => $arrErrores
                ], 422);

            unset($data['add_codigo']);
            $data['ofe_id'] = $ofe->ofe_id;

            if (!$request->filled('rfa_clave_tecnica') || ($request->filled('rfa_clave_tecnica') && $request->rfa_tipo == 'DOCUMENTO_SOPORTE'))
                $data['rfa_clave_tecnica'] = null;

            $obj = $objetoRfa->update($data);

            if($obj){
                return response()->json([
                    'success' => true
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Error al modificar la Resolución de Facturación',
                    'errors' => []
                ], 422);
            }
        }
        return response()->json([
            'message' => 'Error al modificar la Resolución de Facturación',
            'errors' => $validador->errors()->all()
        ], 400);
    }

    /**
     * Cambia el estado de las resoluciones especificadas al almacenamiento
     *
     * @param Request $request
     * @return Response
     */
    public function cambiarEstado(Request $request) {
        if (!isset($request->{$this->nombreDatoCambiarEstado}))
            return response()->json([
                'message' => "No se ha proporcionado el campo {$this->nombreDatoCambiarEstado} que contiene los identificadores a modificar",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        $arrObjetos = explode(',', $request->{$this->nombreDatoCambiarEstado});
        $arrErrores = [];

        foreach($arrObjetos as $identificadorObj) {
            $objeto = $this->getResolucion($identificadorObj);
            if($objeto){
                $strEstado = ($objeto->estado == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                $objeto->update(['estado' => $strEstado]);

                if(!$objeto)
                    $this->adicionarErrorArray($arrErrores, ["Errores Al Actualizar {$this->nombre} [{$identificadorObj}]"]);
            } else
                $this->adicionarErrorArray($arrErrores, ["El Id {$this->nombre} [{$identificadorObj}] No Existe."]);
        }

        if(empty($arrErrores)) {
            return response()->json([
                'success' => true
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'message' => "Error Al Cambiar El Estado De {$this->nombrePlural} Seleccionados",
                'errors' => $arrErrores
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Genera una Interfaz de Resolución de facturacion para guardar en Excel.
     *
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceResolucionFacturacion(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'resolucion_facturacion');  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de Resoluciones de Facturación
     *
     * @param Request $request
     * @return
     * @throws \Exception
     */
    public function cargarResolucionFacturacion(Request $request){
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
                    'message' => 'Errores al guardar las Resoluciones de Facturación',
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
                    'message' => 'Errores al guardar las Resoluciones de Facturación',
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores = [];
            $arrResultado = [];
            $arrExisteResolucionFacturacion = [];
            $arrExisteOfe = [];

            foreach ($data as $fila => $columnas) {

                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrResolucionFacturacion = [];
                $camposRequeridos = [
                    'nit_ofe',
                    'tipo',
                    'resolucion',
                    'fecha_desde',
                    'fecha_hasta',
                    'consecutivo_inicial',
                    'consecutivo_final',
                ];

                if($Acolumnas['tipo'] == 'AUTORIZACION' || $Acolumnas['tipo'] == 'HABILITACION')
                    $camposRequeridos[] = 'clave_tecnica';
                
                if (isset($Acolumnas['fecha_desde']) && isset($Acolumnas['fecha_hasta'])) {
                    $Acolumnas['fecha_desde'] = is_string($Acolumnas['fecha_desde']) ? $Acolumnas['fecha_desde'] : $Acolumnas['fecha_desde']->format('Y-m-d H:i:s');
                    $Acolumnas['fecha_hasta'] = is_string($Acolumnas['fecha_hasta']) ? $Acolumnas['fecha_hasta'] : $Acolumnas['fecha_hasta']->format('Y-m-d H:i:s');
                }

                $arrFaltantes = $this->checkFields($Acolumnas, $camposRequeridos, $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {
                    $arrResolucionFacturacion['rfa_id'] = 0;
                    if (array_key_exists($columnas->tipo.'-'.$columnas->resolucion.'-'.$columnas->prefijo, $arrExisteResolucionFacturacion)){
                        $objExisteResolucionFacturacion = $arrExisteResolucionFacturacion[$columnas->tipo.'-'.$columnas->resolucion.'-'.$columnas->prefijo];
                        $arrResolucionFacturacion['rfa_id'] = $objExisteResolucionFacturacion->rfa_id;
                    } else {
                        $objExisteResolucionFacturacion = $this->className::where('rfa_resolucion', $columnas->resolucion)
                            ->where('rfa_tipo', $columnas->tipo)
                            ->where('rfa_prefijo', $columnas->prefijo)
                            ->first();

                        if ($objExisteResolucionFacturacion){
                            $arrExisteResolucionFacturacion[$columnas->tipo.'-'.$columnas->resolucion.'-'.$columnas->prefijo] = $objExisteResolucionFacturacion;
                            $arrResolucionFacturacion['rfa_id'] = $objExisteResolucionFacturacion->rfa_id;
                        }
                    }

                    //nit_ofe
                    $arrResolucionFacturacion['ofe_id'] = 0;
                    $arrResolucionFacturacion['nit_ofe'] = $columnas->nit_ofe;
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
                        $arrErrores = $this->adicionarError($arrErrores, ["El Nit del Ofe [{$columnas->nit_ofe}] No Existe."], $fila);
                    }

                    if (!empty($objExisteOfe)){
                        $arrResolucionFacturacion['ofe_id'] = $objExisteOfe->ofe_id;
                        if(!is_numeric($columnas->consecutivo_inicial)){
                            $arrErrores = $this->adicionarError($arrErrores, ['El Consecutivo Inicial debe contener sólo números.'], $fila);
                        }

                        if(!is_numeric($columnas->consecutivo_final)){
                            $arrErrores = $this->adicionarError($arrErrores, ['El Consecutivo Final debe contener sólo números.'], $fila);
                        }

                        if(intval($columnas->consecutivo_inicial) > intval($columnas->consecutivo_final)){
                            $arrErrores = $this->adicionarError($arrErrores, ['El Consecutivo Final debe ser Mayor al Consecutivo Inicial.'], $fila);
                        }

                        if (empty($columnas->clave_tecnica) || (!empty($columnas->clave_tecnica) && $columnas->tipo == 'DOCUMENTO_SOPORTE'))
                            $arrResolucionFacturacion['rfa_clave_tecnica'] = null;
                        else
                            $arrResolucionFacturacion['rfa_clave_tecnica'] = $this->sanitizarStrings($columnas->clave_tecnica);


                        if (isset($columnas->aplica_control_de_consecutivos) && !empty($columnas->aplica_control_de_consecutivos)) {
                            if ($columnas->aplica_control_de_consecutivos !== 'SI' && $columnas->aplica_control_de_consecutivos !== 'NO')
                                $arrErrores = $this->adicionarError($arrErrores, ['El campo Aplica Control de Consecutivos debe contener SI o NO como valor.'], $fila);
                        }

                        if (isset($columnas->aplica_consecutivo_provisional) && !empty($columnas->aplica_consecutivo_provisional)) {
                            if ($columnas->aplica_consecutivo_provisional !== 'SI' && $columnas->aplica_consecutivo_provisional !== 'NO')
                                $arrErrores = $this->adicionarError($arrErrores, ['El campo Aplica Consecutivo Provisional debe contener SI o NO como valor.'], $fila);
                        
                            if ($columnas->aplica_consecutivo_provisional == 'SI' && 
                                (
                                    !isset($columnas->aplica_control_de_consecutivos) ||
                                    isset($columnas->aplica_control_de_consecutivos) && $columnas->aplica_control_de_consecutivos !== 'SI'
                                )
                            )
                                $arrErrores = $this->adicionarError($arrErrores, ['El campo Aplica Consecutivo Provisional no puede ser SI porque el campo Aplica Control de Consecutivos es diferente de SI.'], $fila);
                        }

                        if (isset($columnas->dias_aviso) && !empty($columnas->dias_aviso) && !preg_match('/^\d+$/', $columnas->dias_aviso)) {
                            $arrErrores = $this->adicionarError($arrErrores, ['Los Dias de Aviso debe contener un número entero.'], $fila);
                        }

                        if (isset($columnas->consecutivos_aviso) && !empty($columnas->consecutivos_aviso) && !preg_match('/^\d+$/', $columnas->consecutivos_aviso)) {
                            $arrErrores = $this->adicionarError($arrErrores, ['Los Consecutivos de Aviso debe contener un número entero.'], $fila);
                        }

                        $arrResolucionFacturacion['rfa_tipo']                    = $this->sanitizarStrings($columnas->tipo);
                        $arrResolucionFacturacion['rfa_resolucion']              = $this->sanitizarStrings($columnas->resolucion);
                        $arrResolucionFacturacion['rfa_prefijo']                 = $this->sanitizarStrings($columnas->prefijo);
                        $arrResolucionFacturacion['rfa_fecha_desde']             = $this->sanitizarStrings(is_string($columnas->fecha_desde) ? $columnas->fecha_desde : $columnas->fecha_desde->format('Y-m-d H:i:s'));
                        $arrResolucionFacturacion['rfa_fecha_hasta']             = $this->sanitizarStrings(is_string($columnas->fecha_hasta) ? $columnas->fecha_hasta : $columnas->fecha_hasta->format('Y-m-d H:i:s'));
                        $arrResolucionFacturacion['rfa_consecutivo_inicial']     = $this->sanitizarStrings($columnas->consecutivo_inicial);
                        $arrResolucionFacturacion['rfa_consecutivo_final']       = $this->sanitizarStrings($columnas->consecutivo_final);
                        $arrResolucionFacturacion['cdo_control_consecutivos']    = isset($columnas->aplica_control_de_consecutivos) ? $this->sanitizarStrings($columnas->aplica_control_de_consecutivos) : null;
                        $arrResolucionFacturacion['cdo_consecutivo_provisional'] = isset($columnas->aplica_consecutivo_provisional) ? $this->sanitizarStrings($columnas->aplica_consecutivo_provisional) : null;
                        $arrResolucionFacturacion['rfa_dias_aviso']              = isset($columnas->dias_aviso) ? $this->sanitizarStrings($columnas->dias_aviso) : null;
                        $arrResolucionFacturacion['rfa_consecutivos_aviso']      = isset($columnas->consecutivos_aviso) ? $this->sanitizarStrings($columnas->consecutivos_aviso) : null;
                    }

                    if(count($arrErrores) == 0){

                        $objValidator = Validator::make($arrResolucionFacturacion, $this->className::$rules);

                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrResolucionFacturacion;
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
                    'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_RFA,
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
                    'message' => 'Errores al guardar las Resoluciones de Facturación',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            } else {
                $bloque_resolucion_facturacion = [];
                foreach ($arrResultado as $resolucion_facturacion) {
                    $data = [
                        'rfa_id'                      => $resolucion_facturacion['rfa_id'],
                        'ofe_id'                      => $resolucion_facturacion['ofe_id'],
                        'rfa_tipo'                    => $this->sanitizarStrings($resolucion_facturacion['rfa_tipo']),
                        'rfa_resolucion'              => $this->sanitizarStrings($resolucion_facturacion['rfa_resolucion']),
                        'rfa_prefijo'                 => $this->sanitizarStrings($resolucion_facturacion['rfa_prefijo']),
                        'rfa_clave_tecnica'           => $this->sanitizarStrings($resolucion_facturacion['rfa_clave_tecnica']),
                        'rfa_fecha_desde'             => $this->sanitizarStrings($resolucion_facturacion['rfa_fecha_desde']),
                        'rfa_fecha_hasta'             => $this->sanitizarStrings($resolucion_facturacion['rfa_fecha_hasta']),
                        'rfa_consecutivo_inicial'     => $this->sanitizarStrings($resolucion_facturacion['rfa_consecutivo_inicial']),
                        'rfa_consecutivo_final'       => $this->sanitizarStrings($resolucion_facturacion['rfa_consecutivo_final']),
                        'cdo_control_consecutivos'    => $this->sanitizarStrings($resolucion_facturacion['cdo_control_consecutivos']),
                        'cdo_consecutivo_provisional' => $this->sanitizarStrings($resolucion_facturacion['cdo_consecutivo_provisional']),
                        'rfa_dias_aviso'              => $this->sanitizarStrings($resolucion_facturacion['rfa_dias_aviso']),
                        'rfa_consecutivos_aviso'      => $this->sanitizarStrings($resolucion_facturacion['rfa_consecutivos_aviso'])
                    ];

                    array_push($bloque_resolucion_facturacion, $data);
                }

                if (!empty($bloque_resolucion_facturacion)) {
                    $bloques = array_chunk($bloque_resolucion_facturacion, 100);
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
                                'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_RFA,
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
                'message' => 'Errores al guardar las Resoluciones de Facturación',
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de resolucion facturacion
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListaErroresResolucionFacturacion(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_RFA);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de Resolucion Facturacion.
     *
     * @param  Request $request
     * @return Response
     */
    public function descargarListaErroresResolucionFacturacion(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_RFA, true, 'carga_resolucion_facturacion_log_errores');
    }

    /**
     * Toma los errores generados y los mezcla en un sólo arreglo para dar respuesta al usuario
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
     * Efectua un proceso de busqueda en la parametrica
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function busqueda(Request $request) {
        $columnas = [
            'rfa_id',
            'ofe_id',
            'rfa_resolucion',
            'rfa_prefijo',
            'rfa_clave_tecnica',
            'rfa_fecha_desde',
            'rfa_fecha_hasta',
            'rfa_consecutivo_inicial',
            'rfa_consecutivo_final',
            'rfa_dias_aviso',
            'rfa_consecutivos_aviso',
            'estado'
        ];
        $incluir = ['getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres'];
        $precondicion = [];
        if (strtolower($request->valorPrefijo) !== 'null') {
            $precondicion = [
                ['rfa_prefijo', '=', $request->valorPrefijo]
            ];
        }
        return $this->procesadorBusqueda($request, $columnas, $incluir, $precondicion);
    }

    /**
     * Crea un registro en la tabal de consecutivos de documentos, de acuerso al tipo de consecutivo
     *
     * @param integer $ofe_id ID del OFE
     * @param integer $rfa_id ID de la resolución de facturación
     * @param string $rfa_prefijo Prefijo de la resolución de facturación
     * @param string $consecutivoInicial Consecutivo inicial
     * @param string $tipoConsecutivo Tipo de Consecutivo
     * @return void
     */
    private function crearConsecutivo($ofe_id, $rfa_id, $rfa_prefijo, $consecutivoInicial, $tipoConsecutivo) {
        EtlConsecutivoDocumento::create([
            'ofe_id'               => $ofe_id,
            'rfa_id'               => $rfa_id,
            'cdo_tipo_consecutivo' => $tipoConsecutivo,
            'cdo_periodo'          => date('Ym'),
            'rfa_prefijo'          => $rfa_prefijo != '' ? $rfa_prefijo : null,
            'cdo_consecutivo'      => $consecutivoInicial,
            'usuario_creacion'     => auth()->user()->usu_id,
            'estado'               => 'ACTIVO'
        ]);
    }

    /**
     * Permite obtener la lista de todas las resoluciones que estan proximas a vencer.
     *
     * @return JsonResponse
     */
    public function resolucionesVencidas() {
        $user = auth()->user();
        $arrDiasVencidos = [];
        $arrConsecutivoVencidos = [];

        $this->className::with([
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        \DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_razon_social')
                    ]);
                }
            ])
            ->where('rfa_fecha_hasta', '>=', date('Y-m-d'))
            ->where(function($query) {
                $query->whereNotNull('rfa_dias_aviso')
                ->orWhereNotNull('rfa_consecutivos_aviso');
            })
            ->when(!empty($user->bdd_id_rg), function ($query) use ($user) {
                return $query->whereHas('getConfiguracionObligadoFacturarElectronicamente', function ($queryOfe) use ($user) {
                    $queryOfe->where('bdd_id_rg', $user->bdd_id_rg);
                });
            }, function ($query) use ($user) {
                return $query->whereHas('getConfiguracionObligadoFacturarElectronicamente', function ($queryOfe) {
                    $queryOfe->whereNull('bdd_id_rg');
                });
            })
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function($resolucion) use (&$arrDiasVencidos, &$arrConsecutivoVencidos) {
                // Se calculan los días faltantes entre la fecha hasta de resolución y la fecha actual del sistema
                $date1    = date_create(date('Y-m-d'));
                $date2    = date_create($resolucion->rfa_fecha_hasta);
                $diffDias = (date_diff($date1, $date2)->format('%R%a') + 0);

                if ($diffDias <= $resolucion->rfa_dias_aviso)
                    $arrDiasVencidos[]['message'] = "La resolución ".$resolucion->rfa_resolucion.", con prefijo ".$resolucion->rfa_prefijo.", para el OFE ".$resolucion->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social." se encuentra próxima a vencer. Vence el día ".$resolucion->rfa_fecha_hasta.".";

                $documentoCabecera = EtlCabeceraDocumentoDaop::select(['cdo_consecutivo'])
                    ->where('rfa_id', $resolucion->rfa_id)
                    ->orderBy(DB::Raw('abs(cdo_consecutivo)'), 'desc')
                    ->first();

                // Si no se encuentra consecutivo para la resolución en la data operativa se consulta en la tabla FAT
                $cdoConsecutivo = '';
                if ($documentoCabecera)
                    $cdoConsecutivo = $documentoCabecera->cdo_consecutivo;
                else {
                    $documentoFat = EtlFatDocumentoDaop::select(['cdo_consecutivo'])
                        ->where('rfa_id', $resolucion->rfa_id)
                        ->orderBy(DB::Raw('abs(cdo_consecutivo)'), 'desc')
                        ->first();

                    if ($documentoFat)
                        $cdoConsecutivo = $documentoFat->cdo_consecutivo;
                }

                if ($cdoConsecutivo != '') {
                    $diferenciaConsecutivo = $resolucion->rfa_consecutivo_final - $cdoConsecutivo;

                    if ($diferenciaConsecutivo <= $resolucion->rfa_consecutivos_aviso)
                        $arrConsecutivoVencidos[]['message'] = "Para la resolución ".$resolucion->rfa_resolucion.", con prefijo ".$resolucion->rfa_prefijo.", del OFE ".$resolucion->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social.", la cantidad de consecutivos autorizados para su expedición esta próxima a finalizar. Cantidad de consecutivos disponibles ". (($diferenciaConsecutivo < 0) ? 0 : $diferenciaConsecutivo).".";
                }
            });
            
        return response()->json([
            'vencimientos' => [
                'diasVencidos'         => $arrDiasVencidos,
                'consecutivosVencidos' => $arrConsecutivoVencidos
            ]
        ], 200);
    }

    /**
     * Descarga un Excel con la información de resoluciones consultada en la DIAN y cruzada con la información existente en openETL.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse|BinaryFileResponse
     */
    public function descargarExcelConsultaDian(Request $request) {
        if(!$request->filled('data')) {
            $headers = [
                header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                header('X-Error-Status: 422'),
                header('X-Error-Message: Error al descargar el Excel de resoluciones')
            ];

            return response()->json([
                'message' => 'Error en la Descarga',
                'errors' => ['Error al descargar el Excel de resoluciones']
            ], 422, $headers);
        }

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

        if (!$ofe) {
            $headers = [
                header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                header('X-Error-Status: 404'),
                header('X-Error-Message: El OFE [' . $request->ofe_identificacion . '] no existe o se encuentra INACTIVO.')
            ];

            return response()->json([
                'message' => 'Error al crear la Resolución de Facturación',
                'errors' => ['El OFE [' . $request->ofe_identificacion . '] no existe o se encuentra INACTIVO.']
            ], 404);
        }

        $arrFilas         = [];
        $arrColumnas      = [
            'NIT OFE',
            'TIPO',
            'RESOLUCION',
            'PREFIJO',
            'CLAVE TECNICA',
            'FECHA DESDE',
            'FECHA HASTA',
            'CONSECUTIVO INICIAL',
            'CONSECUTIVO FINAL',
            'APLICA CONTROL DE CONSECUTIVOS',
            'APLICA CONSECUTIVO PROVISIONAL',
            'DIAS AVISO',
            'CONSECUTIVOS AVISO'
        ];

        $dataResoluciones = json_decode(base64_decode($request->data), true);
        foreach($dataResoluciones as $resolucion) {
            $dataResolucion = ConfiguracionResolucionesFacturacion::select([
                    'rfa_id',
                    'ofe_id',
                    'rfa_tipo',
                    'rfa_prefijo',
                    'rfa_resolucion',
                    'rfa_clave_tecnica',
                    'rfa_fecha_desde',
                    'rfa_fecha_hasta',
                    'rfa_consecutivo_inicial',
                    'rfa_consecutivo_final',
                    'cdo_control_consecutivos',
                    'cdo_consecutivo_provisional',
                    'rfa_dias_aviso',
                    'rfa_consecutivos_aviso',
                    'estado'
                ])
                ->where('ofe_id', $ofe->ofe_id)
                ->where(function($query) {
                    $query->whereNull('rfa_tipo')
                        ->orWhereIn('rfa_tipo', ['AUTORIZACION', 'HABILITACION']);
                })
                ->where('rfa_prefijo', $resolucion['prefijo'])
                ->where('rfa_resolucion', $resolucion['resolucion'])
                ->first();

            if($dataResolucion) {
                $arrFilas[] = [
                    $ofe->ofe_identificacion,
                    $dataResolucion->rfa_tipo,
                    $dataResolucion->rfa_resolucion,
                    $dataResolucion->rfa_prefijo,
                    !empty($resolucion['clave_tecnica']) ? $resolucion['clave_tecnica'] : $dataResolucion->rfa_clave_tecnica,
                    !empty($resolucion['fecha_desde']) ? $resolucion['fecha_desde'] : $dataResolucion->rfa_fecha_desde,
                    !empty($resolucion['fecha_hasta']) ? $resolucion['fecha_hasta'] : $dataResolucion->rfa_fecha_hasta,
                    !empty($resolucion['consecutivo_inicial']) ? $resolucion['consecutivo_inicial'] : $dataResolucion->rfa_consecutivo_inicial,
                    !empty($resolucion['consecutivo_final']) ? $resolucion['consecutivo_final'] : $dataResolucion->rfa_consecutivo_final,
                    $dataResolucion->cdo_control_consecutivos,
                    $dataResolucion->cdo_consecutivo_provisional,
                    $dataResolucion->rfa_dias_aviso,
                    $dataResolucion->rfa_consecutivos_aviso
                ];
            } else {
                $arrFilas[] = [
                    $ofe->ofe_identificacion,
                    '',
                    $resolucion['resolucion'],
                    $resolucion['prefijo'],
                    $resolucion['clave_tecnica'],
                    $resolucion['fecha_desde'],
                    $resolucion['fecha_hasta'],
                    $resolucion['consecutivo_inicial'],
                    $resolucion['consecutivo_final'],
                    '',
                    '',
                    '',
                    ''
                ];
            }
        }

        $nombreArchivo = 'resolucion_facturacion_' . date('YmdHis');
        $archivoExcel  = $this->toExcel($arrColumnas, $arrFilas, $nombreArchivo);

        $headers = [
            header('Access-Control-Expose-Headers: Content-Disposition')
        ];

        return response()
            ->download($archivoExcel, $nombreArchivo . '.xlsx', $headers)
            ->deleteFileAfterSend(true);
    }
}
