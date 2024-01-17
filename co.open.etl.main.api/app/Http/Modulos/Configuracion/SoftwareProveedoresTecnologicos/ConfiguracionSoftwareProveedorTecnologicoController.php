<?php

namespace App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos;

use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\OpenTenantController;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;

class ConfiguracionSoftwareProveedorTecnologicoController extends OpenTenantController {
    /**
     * Método correspondiente al ambiente en habilitación.
     * 
     * @var String
     */
    public $ambienteHabilitacion = 'SendTestSetAsync';

    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Software Proveedor Tecnológico';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Software Proveedores Tecnológico';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ConfiguracionSoftwareProveedorTecnologico::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Software Proveedor Tecnológico';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Software Proveedor Tecnológico';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Software Proveedor Tecnológico';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Software Proveedor Tecnológico';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Software Proveedor Tecnológico [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id de la Software Proveedor Tecnológico [%s] Esta Inactivo';

    public $nombreDatoCambiarEstado = 'sft-identificadores';

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
    public $columnasExcel = [
        'CODIGO INTERNO',
        'IDENTIFICADOR',
        'PIN',
        'NOMBRE',
        'FECHA REGISTRO',
        'CODIGO AMBIENTE DESTINO',
        'APLICA PARA',
        'NIT PROVEEDOR TECNOLOGICO',
        'RAZON SOCIAL PROVEEDOR TECNOLOGICO'
    ];

    /**
    * Constructor
    */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:SoftwareProveedorTecnologico,SoftwareProveedorTecnologicoNuevo,SoftwareProveedorTecnologicoEditar,SoftwareProveedorTecnologicoVer,SoftwareProveedorTecnologicoCambiarEstado,SoftwareProveedorTecnologicoSubir'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'buscarSoftware',
            'buscarSptNgSelect',
            'busqueda',
            'busquedaDn',
            'busquedaDs',
            'generarInterfaceSpt',
            'cargarSpt',
            'getListaErroresSpt',
            'descargarListaErroresSpt'
        ]);

        $this->middleware([
            'VerificaMetodosRol:SoftwareProveedorTecnologicoNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:SoftwareProveedorTecnologicoEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:SoftwareProveedorTecnologicoVer'
        ])->only([
            'busqueda',
            'busquedaDn',
            'busquedaDs'
        ]);

        $this->middleware([
            'VerificaMetodosRol:SoftwareProveedorTecnologicoVer,SoftwareProveedorTecnologicoEditar'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:SoftwareProveedorTecnologicoCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:SoftwareProveedorTecnologicoSubir'
        ])->only([
            'generarInterfaceSpt',
            'cargarSpt',
            'getListaErroresSpt',
            'descargarListaErroresSpt'
        ]);
    }

    /**
     * Configura las reglas para poder actualizar o crear los datos de los adqs en funcion de los codigos proporcionados
     *
     * @return mixed
     */
    private function getRules() {
        $rules = array_merge($this->className::$rules);
        return $rules;
    }
    
    /**
     * Almacena un software recién creado en el almacenamiento
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request){
        $data = $request->all();
        $validador = Validator::make($request->all(), $this->getRules());
        if (!$validador->fails()) {
            $existe = $this->className::where('sft_identificador', $request->sft_identificador)
                ->where('sft_nombre', $request->sft_nombre)
                ->first();
            if ($existe !== null) {
                return response()->json([
                    'message' => 'Error al crear el Software Proveedor Tecnológico',
                    'errors' => ["El Software Proveedor Tecnológico con Identificador {$request->sft_identificador} y Nombre {$request->sft_nombre} ya existe."]
                ], 409);
            }

            $ambienteDestino = ParametrosAmbienteDestinoDocumento::select(['add_id', 'add_metodo'])
                ->where('estado', 'ACTIVO')
                ->where('add_id', $request->add_id)
                ->first();

            if ($ambienteDestino === null) {
                return response()->json([
                    'message' => 'Error al crear el Software Proveedor Tecnológico',
                    'errors' => ["El Ambiente Destino Documento seleccionado no existe."]
                ], 404);
            }

            if($ambienteDestino->add_metodo == $this->ambienteHabilitacion && (!isset($request->sft_testsetid) || $request->sft_testsetid == '')) {
                return response()->json([
                    'message' => 'Error al crear el Software Proveedor Tecnológico',
                    'errors' => ["El Ambiente Destino Documento corresponde con Habilitación pero no se recibió el TestSetId."]
                ], 409);
            } elseif($ambienteDestino->add_metodo != $this->ambienteHabilitacion) {
                $data['sft_testsetid'] = null;
            }

            $arrOpcionesAplica = ['DE', 'DS', 'DN'];
            $arrAplicaPara = explode(',', $request->sft_aplica_para);
            $arrErrorAplicaPara = [];
            foreach ($arrAplicaPara as $value) {
                if(!in_array($value, $arrOpcionesAplica))
                    array_push($arrErrorAplicaPara, "El valor [{$value}] del aplica para no es valido");
            }
            if(!empty($arrErrorAplicaPara)) 
                return response()->json([
                    'message' => $this->mensajeErroresCreacion,
                    'errors' => $arrErrorAplicaPara
                ], 422);

            $user                     = auth()->user();
            $data['add_id']           = $ambienteDestino->add_id;
            $data['bdd_id_rg']        = !empty($user->bdd_id_rg) ? $user->bdd_id_rg : null;
            $data['estado']           = 'ACTIVO';
            $data['usuario_creacion'] = auth()->user()->usu_id;
            $obj = $this->className::create($data);

            if($obj){
                return response()->json([
                    'success' => true,
                    'sft_id'  => $obj->sft_id
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Error al crear el Software Proveedor Tecnológico',
                    'errors' => []
                ], 422);
            }
        }

        return response()->json([
            'message' => 'Error al crear el Software Proveedor Tecnológico',
            'errors' => $validador->errors()->all()
        ], 400);
    }
    
    /**
     * Muestra el software especificado.
     *
     * @param   $id
     * @return Response
     */
    public function show($id){
        return $this->procesadorShow($id, [
            'getUsuarioCreacion',
            'getAmbienteDestino:add_id,add_codigo,add_descripcion,add_metodo'
        ]);
    }
    
    /**
     * Actualiza el software especificado en el almacenamiento.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id){
        $data = $request->all();
        $validador = Validator::make($request->all(), $this->getRules());
        if (!$validador->fails()) {
            $objetoSpt = $this->className::find($id);
            if ($objetoSpt === null) {
                return response()->json([
                    'message' => 'Error al modificar el Software Proveedor Tecnológico',
                    'errors' => ["El Software Proveedor Tecnológico {$request->sft_identificador} no existe."]
                ], 409);
            }

            if ($id != $request->sft_id) {
                $existe = $this->className::where('sft_identificador', $request->sft_identificador)
                    ->where('sft_nombre', $request->sft_nombre)
                    ->first();
                if ($existe !== null) {
                    return response()->json([
                        'message' => 'Error al modificar el Software Proveedor Tecnológico',
                        'errors' => ["El Software Proveedor Tecnológico con Identificador {$request->sft_identificador} y Nombre {$request->sft_nombre} ya existe."]
                    ], 409);
                }
            }

            $ambienteDestino = ParametrosAmbienteDestinoDocumento::select(['add_id', 'add_metodo'])
                ->where('estado', 'ACTIVO')
                ->where('add_id', $request->add_id)
                ->first();

            if($ambienteDestino === null) {
                return response()->json([
                    'message' => 'Error al modificar el Software Proveedor Tecnológico',
                    'errors' => ["El Ambiente Destino Documento seleccionado no existe."]
                ], 404);
            }

            if($ambienteDestino->add_metodo == $this->ambienteHabilitacion && (!isset($request->sft_testsetid) || $request->sft_testsetid == '')) {
                return response()->json([
                    'message' => 'Error al modificar el Software Proveedor Tecnológico',
                    'errors' => ["El Ambiente Destino Documento corresponde con Habilitación pero no se recibió el TestSetId."]
                ], 409);
            } elseif($ambienteDestino->add_metodo != $this->ambienteHabilitacion) {
                $data['sft_testsetid'] = null;
            }

            if(!$request->filled('sft_aplica_para') || $request->sft_aplica_para === ''){
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors' => ["El campo aplica para es obligatorio"]
                ], 422);
            }

            $arrOpcionesAplica = ['DE', 'DS', 'DN'];
            $arrAplicaPara = explode(',', $request->sft_aplica_para);
            $arrErrorAplicaPara = [];
            foreach ($arrAplicaPara as $value) {
                if(!in_array($value, $arrOpcionesAplica))
                    array_push($arrErrorAplicaPara, "El valor [{$value}] del aplica para no es valido");
            }
            if(!empty($arrErrorAplicaPara)) 
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors' => $arrErrorAplicaPara
                ], 422);

            $data['add_id'] = $ambienteDestino->add_id;
            $obj = $objetoSpt->update($data);

            if($obj){
                return response()->json([
                    'success' => true
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Error al modificar el Software Proveedor Tecnológico',
                    'errors' => []
                ], 422);
            }
        }

        return response()->json([
            'message' => 'Error al modificar el Software Proveedor Tecnológico',
            'errors' => $validador->errors()->all()
        ], 400);
    }

    /**
     * Cambia el estado de los Software seleccionados
     *
     * @param Request $request
     * @return Response
     */
    public function cambiarEstado(Request $request){
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Permite realizar la buúsqueda de uno o mas registros por una columna en específico.
     *
     * @param string $campoBuscar Columna a realizar la búsqueda
     * @param string $valorBuscar Valor a buscar
     * @param string $filtroColumnas Tipo de búsqueda basico|avanzado|exacto
     * @return Response
     */
    public function buscarSoftware(string $campoBuscar, string $valorBuscar, string $filtroColumnas){
        switch($filtroColumnas){
            case 'basico':
                $objResponse = ConfiguracionSoftwareProveedorTecnologico::where($campoBuscar, 'like', '%' . $valorBuscar . '%')
                    ->where('estado','ACTIVO')
                    ->select(['sft_id', 'sft_identificador', 'sft_nombre', 'estado'])
                    ->get();
            break;
            case 'avanzado':
                $objResponse = ConfiguracionSoftwareProveedorTecnologico::where($campoBuscar, 'like', '%' . $valorBuscar . '%')
                    ->where('estado','ACTIVO')
                    ->select(['sft_id', 'sft_identificador', 'sft_nombre', 'estado'])
                    ->get();
            break;
            case 'exacto':
                $objResponse = ConfiguracionSoftwareProveedorTecnologico::where($campoBuscar, $valorBuscar)
                    ->where('estado','ACTIVO')
                    ->select(['sft_id', 'sft_identificador', 'sft_nombre'])
                    ->get();
            break;
        }

        $temp = [];
        foreach ($objResponse as $item) {
            $data = $item->toArray();
            $data['sft_identificador_nombre'] = $data['sft_identificador'] . ' - ' . $data['sft_nombre'];
            $temp[] = $data;
        }
        $objResponse = $temp;

        return response()->json([
            'data' => $objResponse
        ], 200);
    }

    /**
     * Devuelve una lista paginada de registros que aplican para Documento Soporte Nómina Electrónica.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function getListaSptDn(Request $request) {
        $request->merge([
            'aplica_para' => 'DN'
        ]);
        return $this->getListaSpt($request);
    }

    /**
     * Devuelve una lista paginada de registros que aplican para Documento Soporte.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function getListaSptDs(Request $request) {
        $request->merge([
            'aplica_para' => 'DS'
        ]);
        return $this->getListaSpt($request);
    }

    /**
     * Devuelve una lista paginada de registros.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function getListaSpt(Request $request) {
        $user = auth()->user();

        $filtros = [];

        if(!empty($user->bdd_id_rg))
            $filtros = [
                'AND' => [
                    ['bdd_id_rg', '=', $user->bdd_id_rg]
                ]
            ];
        else
            $filtros = [
                'AND' => [
                    ['bdd_id_rg', '=', null]
                ]
            ];

        $aplicaPara = ($request->filled('aplica_para')) ? $request->aplica_para : 'DE';
        $arrAplicaPara = explode(',', $aplicaPara);
        if(count($arrAplicaPara) > 1) {
            $orAplicaPara = [];
            foreach ($arrAplicaPara as $value)
                $orAplicaPara[] = ['sft_aplica_para', 'LIKE', '%'. $value .'%'];
    
            $filtros['OR'] = $orAplicaPara;
        } else { 
            array_push($filtros['AND'], ['sft_aplica_para', 'like', '%'. $aplicaPara .'%']);
        }

        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'sft_id',
            'sft_identificador',
            'sft_pin',
            'sft_nombre',
            'sft_fecha_registro',
            'add_id',
            'sft_aplica_para',
            'sft_nit_proveedor_tecnologico',
            'sft_razon_social_proveedor_tecnologico',
            'usuario_creacion',
            'fecha_creacion',
            'fecha_modificacion',
            'estado',
            'fecha_actualizacion'
        ];
        $relaciones = [
            'getUsuarioCreacion',
            'getAmbienteDestino'
        ];

        $exportacion = [
            'columnas' => [
                'sft_id' => 'CODIGO INTERNO',
                'sft_identificador' => 'IDENTIFICADOR',
                'sft_pin' => 'PIN',
                'sft_nombre'  => 'NOMBRE',
                'sft_fecha_registro' => 'FECHA REGISTRO',
                'add_id' => [
                    'label' => 'CODIGO AMBIENTE DESTINO',
                    'relation' => ['name' => 'getAmbienteDestino', 'field' => 'add_codigo']
                ],
                'sft_aplica_para' => 'APLICA PARA',
                'sft_nit_proveedor_tecnologico' => 'NIT PROVEEDOR TECNOLOGICO',
                'sft_razon_social_proveedor_tecnologico' => 'RAZON SOCIAL PROVEEDOR TECNOLOGICO',
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
            'titulo' => 'software_proveedor_tecnologico'
        ];

        if(!$request->filled('tracking') || $request->tracking === 'false'){
            $campoAplicaPara = array_search('sft_aplica_para', $columnas);
            unset($columnas[$campoAplicaPara], $exportacion['columnas']['sft_aplica_para']);
            $columnas = array_values($columnas);
        }

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, [], 'configuracion');
    }

    /**
     * Busca los proveedores tecnológicos en base a un criterio predictivo.
     *
     * @param string $valorBuscar Valor a buscar
     * @param string $aplicaPara Tipo de documento electrónico para el cual aplica
     * @return Response
     */
    public function buscarSptNgSelect(string $valorBuscar, string $aplicaPara){
        $user    = auth()->user();
        $comodin = "'%$valorBuscar%'";
        $sqlRaw  = "sft_identificador LIKE $comodin OR sft_nombre LIKE $comodin OR sft_pin LIKE $comodin OR sft_nit_proveedor_tecnologico LIKE $comodin";

        $proveedoresTecnologicos = ConfiguracionSoftwareProveedorTecnologico::select(['sft_id', 'sft_identificador', 'sft_nombre'])
            ->where('estado', 'ACTIVO')
            ->where(function($query) use ($sqlRaw) {
                $query->whereRaw($sqlRaw);
            });

        if ($aplicaPara === 'DE' || $aplicaPara === 'DS' || $aplicaPara === 'DN') {
            $proveedoresTecnologicos = $proveedoresTecnologicos->where('sft_aplica_para', 'LIKE', '%'.$aplicaPara.'%');
        } else {
            $proveedoresTecnologicos = $proveedoresTecnologicos->where(function($query) use ($sqlRaw) {
                $query->where('sft_aplica_para', 'LIKE', '%DE%')
                    ->orWhere('sft_aplica_para', 'LIKE', '%DS%');
            });
        }

        if(!empty($user->bdd_id_rg)) {
            $proveedoresTecnologicos = $proveedoresTecnologicos->where('bdd_id_rg', $user->bdd_id_rg);
        } else {
            $proveedoresTecnologicos = $proveedoresTecnologicos->whereNull('bdd_id_rg');
        }

        $proveedoresTecnologicos = $proveedoresTecnologicos->get();

        $temp = [];
        foreach ($proveedoresTecnologicos as $item) {
            $data = $item->toArray();
            $data['sft_identificador_nombre'] = $data['sft_identificador'] . ' - ' . $data['sft_nombre'];
            $temp[] = $data;
        }
        $proveedoresTecnologicos = $temp;

        return response()->json([
            'data' => $proveedoresTecnologicos
        ], 200);
    }

    /**
     * Genera una Interfaz de Software Proveedor Tecnológico para guardar en Excel.
     *
     * @return \App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceSpt(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'software_proveedor_tecnologico');  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de Software Proveedor Tecnológico.
     *
     * @param Request $request
     * @return
     * @throws \Exception
     */
    public function cargarSpt(Request $request){
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
                    'message' => 'Errores al guardar los Software Proveedor Tecnológico',
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
                    'message' => 'Errores al guardar los Software Proveedor Tecnologico',
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores    = [];
            $arrResultado  = [];
            $arrExisteAdd  = [];

            ParametrosAmbienteDestinoDocumento::where('estado', 'ACTIVO')->select('add_id', 'add_codigo')->get()->map(function ($add) use (&$arrExisteAdd) {
                $arrExisteAdd[$add->add_codigo] = $add;
            });

            foreach ($data as $fila => $columnas) {
                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrSoftwareProveedorTecnologico = [];
                $arrFaltantes = $this->checkFields($Acolumnas, [
                    'identificador',
                    'pin',
                    'nombre',
                    'fecha_registro',
                    'codigo_ambiente_destino',
                    'aplica_para',
                    'nit_proveedor_tecnologico',
                    'razon_social_proveedor_tecnologico'
                ], $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {
                    $arrSoftwareProveedorTecnologico['sft_id'] = 0;

                    if(isset($columnas->codigo_interno) && !empty($columnas->codigo_interno)) {
                        $objExisteSpt = $this->className::select('sft_id')->where('sft_id', $columnas->codigo_interno)
                            ->first();
                        if (!empty($objExisteSpt)){
                            $arrSoftwareProveedorTecnologico['sft_id'] = $objExisteSpt->sft_id;
                        }
                    } else {
                        $objExisteSpt = $this->className::select('sft_id')->where('sft_identificador', $columnas->identificador)
                            ->first();
                        if (!empty($objExisteSpt)){
                            $arrSoftwareProveedorTecnologico['sft_id'] = $objExisteSpt->sft_id;
                        }
                    }

                    $arrSoftwareProveedorTecnologico['sft_identificador']                      = $this->sanitizarStrings($columnas->identificador);
                    $arrSoftwareProveedorTecnologico['sft_pin']                                = $this->sanitizarStrings($columnas->pin);
                    $arrSoftwareProveedorTecnologico['sft_nombre']                             = $this->sanitizarStrings($columnas->nombre);
                    $arrSoftwareProveedorTecnologico['sft_fecha_registro']                     = $this->sanitizarStrings($columnas->fecha_registro);
                    $arrSoftwareProveedorTecnologico['sft_aplica_para']                        = $this->sanitizarStrings($columnas->aplica_para);
                    $arrSoftwareProveedorTecnologico['sft_nit_proveedor_tecnologico']          = $this->sanitizarStrings($columnas->nit_proveedor_tecnologico);
                    $arrSoftwareProveedorTecnologico['sft_razon_social_proveedor_tecnologico'] = $this->sanitizarStrings($columnas->razon_social_proveedor_tecnologico);

                    $arrSoftwareProveedorTecnologico['add_id'] = 0;
                    if (property_exists($columnas, 'codigo_ambiente_destino') && !empty($columnas->codigo_ambiente_destino)){
                        if(array_key_exists($columnas->codigo_ambiente_destino, $arrExisteAdd)) {
                            $objExisteSoftwareProveedorTecnologico = $arrExisteAdd[$columnas->codigo_ambiente_destino];
                            $arrSoftwareProveedorTecnologico['add_id'] = $objExisteSoftwareProveedorTecnologico->add_id;
                        } else {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Código del Ambiente Destino ['.$columnas->codigo_ambiente_destino.'] no existe'], $fila);
                        }
                    }


                    if(count($arrErrores) == 0){
                        $objValidator = Validator::make($arrSoftwareProveedorTecnologico, $this->className::$rules);

                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrSoftwareProveedorTecnologico;
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
                    'pjj_tipo'                => ProcesarCargaParametricaCommand::$TYPE_SPT,
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
                    'message' => 'Errores al guardar los Software Proveedor Tecnologico',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            } else {
                $bloque_software_proveedor_tecnologico = [];
                foreach ($arrResultado as $software_proveedor_tecnologico) {
                    $data = [
                        "sft_id"                                 => $software_proveedor_tecnologico['sft_id'],
                        "sft_identificador"                      => $this->sanitizarStrings($software_proveedor_tecnologico['sft_identificador']),
                        "sft_pin"                                => $this->sanitizarStrings($software_proveedor_tecnologico['sft_pin']),
                        "sft_nombre"                             => $this->sanitizarStrings($software_proveedor_tecnologico['sft_nombre']),
                        "sft_fecha_registro"                     => $this->sanitizarStrings($software_proveedor_tecnologico['sft_fecha_registro']),
                        "sft_aplica_para"                        => $this->sanitizarStrings($software_proveedor_tecnologico['sft_aplica_para']),
                        "sft_nit_proveedor_tecnologico"          => $this->sanitizarStrings($software_proveedor_tecnologico['sft_nit_proveedor_tecnologico']),
                        "sft_razon_social_proveedor_tecnologico" => $this->sanitizarStrings($software_proveedor_tecnologico['sft_razon_social_proveedor_tecnologico']),
                        "add_id"                                 => $software_proveedor_tecnologico['add_id']
                    ];

                    array_push($bloque_software_proveedor_tecnologico, $data);
                }

                if (!empty($bloque_software_proveedor_tecnologico)) {
                    $bloques = array_chunk($bloque_software_proveedor_tecnologico, 100);
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
                                'pjj_tipo'         => ProcesarCargaParametricaCommand::$TYPE_SPT,
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
                'message' => 'Errores al guardar los Software Proveedor Tecnologico',
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de Software Proveedor Tecnológico.
     * 
     * @return Response
     */
    public function getListaErroresSpt() {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_SPT);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de Spt.
     *
     * @return Response
     */
    public function descargarListaErroresSpt() {
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_SPT, true, 'carga_software_proveedor_facturacion_log_errores');
    }

    /**
     * Efectúa un proceso de búsqueda en la paramétrica para los registros que aplican para Documento Soporte Nómina Electrónica.
     *
     * @param Request $request
     * @return Response
     */
    public function busquedaDn(Request $request) {
        $request->merge([
            'aplica_para' => 'DN'
        ]);
        return $this->busqueda($request);
    }

    /**
     * Efectúa un proceso de búsqueda en la paramétrica para los registros que aplican para Documento Soporte.
     *
     * @param Request $request
     * @return Response
     */
    public function busquedaDs(Request $request) {
        $request->merge([
            'aplica_para' => 'DS'
        ]);
        return $this->busqueda($request);
    }

    /**
     * Efectúa un proceso de búsqueda en la paramétrica para los registros que aplican para Documento Soporte.
     *
     * @param Request $request
     * @return Response
     */
    public function busqueda(Request $request) {
        $columnas = [
            'sft_id',
            'sft_identificador',
            'sft_pin',
            'sft_nombre',
            'sft_fecha_registro',
            'add_id',
            'sft_aplica_para',
            'sft_nit_proveedor_tecnologico',
            'sft_razon_social_proveedor_tecnologico',
            'estado'
        ];
        $incluir = ['getAmbienteDestino'];

        $aplicaPara = ($request->filled('aplica_para')) ? $request->aplica_para : 'DE';
        $precondiciones = [
            ['sft_aplica_para', 'like', '%'.$aplicaPara.'%']
        ];

        return $this->procesadorBusqueda($request, $columnas, $incluir, $precondiciones);
    }
}
