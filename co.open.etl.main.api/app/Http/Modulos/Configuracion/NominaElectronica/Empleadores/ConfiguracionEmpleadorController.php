<?php

namespace App\Http\Modulos\Configuracion\NominaElectronica\Empleadores;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;
use App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologico;

class ConfiguracionEmpleadorController extends OpenTenantController{
    use PackageMainTrait;

    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Empleador';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Empleadores';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ConfiguracionEmpleador::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Empleador';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Empleador';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Empleador';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Empleador';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id de el Empleador [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id de el Empleador [%s] Esta Inactivo';

    /**
     * Propiedad contiene los datos del usuario autenticado.
     *
     * @var Object
     */
    protected $user;

    /**
     * Propiedad para almacenar los errores.
     *
     * @var Array
     */
    protected $errors = [];

    /**
     * Nombre del campo que contiene los valores de identificación de los registros a modificar.
     * 
     * @var String
     */
    public $nombreDatoCambiarEstado = 'empleadores';

    /**
     * Nombre del campo que contiene la identificación del registro.
     * 
     * @var String
     */
    public $nombreCampoIdentificacion = 'emp_identificacion';

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
        'CODIGO TIPO DOCUMENTO',
        'DESCRIPCION TIPO DOCUMENTO',
        'IDENTIFICACION',
        'RAZON SOCIAL',
        'PRIMER APELLIDO',
        'SEGUNDO APELLIDO',
        'PRIMER NOMBRE',
        'OTROS NOMBRES',
        'CODIGO PAIS',
        'DESCRIPCION PAIS',
        'CODIGO DEPARTAMENTO',
        'DESCRIPCION DEPARTAMENTO',
        'CODIGO CIUDAD/MUNICIPIO',
        'DESCRIPCION CIUDAD/MUNICIPIO',
        'DIRECCION',
        'TELEFONO',
        'SITIO WEB',
        'CORREO',
        'TWITTER',
        'FACEBOOK',
        'SOFTWARE PROVEEDOR TECNOLOGICO'
    ];

    /**
    * Constructor
    */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnEmpleador,ConfiguracionDnEmpleadorNuevo,ConfiguracionDnEmpleadorEditar,ConfiguracionDnEmpleadorVer,ConfiguracionDnEmpleadorCambiarEstado,ConfiguracionDnEmpleadorSubir,ConfiguracionDnEmpleadorDescargarExcel'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'generarInterfaceEmpleador', 
            'cargarEmpleadores',
            'getListaErroresEmpleador',
            'descargarListaErroresEmpleador',
            'buscarEmpleadores'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnEmpleadorNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnEmpleadorEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnEmpleadorVer,ConfiguracionDnEmpleadorEditar'
        ])->only([
            'show'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnEmpleadorVer'
        ])->only([
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnEmpleadorCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnEmpleadorSubir'
        ])->only([
            'generarInterfaceEmpleador', 
            'cargarEmpleadores',
            'getListaErroresEmpleador',
            'descargarListaErroresEmpleador'
        ]);
    }

    /**
     * Muestra una lista de empleadores.
     *
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function getListaEmpleadores(Request $request) {
        $user = auth()->user();

        $filtros = [];
        if(empty($user->bdd_id_rg)) {
            $filtros = [
                'AND' => [
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

        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'emp_id',
            'tdo_id',
            'emp_identificacion',
            'emp_razon_social',
            'emp_primer_apellido',
            'emp_segundo_apellido',
            'emp_primer_nombre',
            'emp_otros_nombres',
            \DB::raw('IF(emp_razon_social IS NULL OR emp_razon_social = "", CONCAT(COALESCE(emp_primer_nombre, ""), " ", COALESCE(emp_otros_nombres, ""), " ", COALESCE(emp_primer_apellido, ""), " ", COALESCE(emp_segundo_apellido, "")), emp_razon_social) as nombre_completo'),
            'pai_id',
            'dep_id',
            'mun_id',
            'emp_direccion',
            'emp_telefono',
            'emp_web',
            'emp_correo',
            'emp_twitter',
            'emp_facebook',
            'sft_id',
            'emp_prioridad_agendamiento',
            'bdd_id_rg',
            'usuario_creacion',
            'estado',
            'fecha_modificacion',
            'fecha_creacion'
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getTipoDocumento',
            'getParametrosPais',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getProveedorTecnologico'
        ];

        $whereHasConditions = [];

        $exportacion = [
            'columnas' => [
                [
                    'multiple' => true,
                    'relation' => 'getTipoDocumento',
                    'fields' => [
                        [
                            'label' => 'CODIGO TIPO DOCUMENTO',
                            'field' => 'tdo_codigo'
                        ],
                        [
                            'label' => 'DESCRIPCION TIPO DOCUMENTO',
                            'field' => 'tdo_descripcion'
                        ]
                    ]
                ],
                'emp_identificacion' => 'IDENTIFICACION',
                'emp_razon_social' => 'RAZON SOCIAL',
                'emp_primer_apellido' => 'PRIMER APELLIDO',
                'emp_segundo_apellido' => 'SEGUNDO APELLIDO',
                'emp_primer_nombre' => 'PRIMER NOMBRE',
                'emp_otros_nombres' => 'OTROS NOMBRES',
                [
                    'multiple' => true,
                    'relation' => 'getParametrosPais',
                    'fields' => [
                        [
                            'label' => 'CODIGO PAIS',
                            'field' => 'pai_codigo'
                        ],
                        [
                            'label' => 'DESCRIPCION PAIS',
                            'field' => 'pai_descripcion'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getParametrosDepartamento',
                    'fields' => [
                        [
                            'label' => 'CODIGO DEPARTAMENTO',
                            'field' => 'dep_codigo'
                        ],
                        [
                            'label' => 'DESCRIPCION DEPARTAMENTO',
                            'field' => 'dep_descripcion'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getParametrosMunicipio',
                    'fields' => [
                        [
                            'label' => 'CODIGO CIUDAD/MUNICIPIO',
                            'field' => 'mun_codigo'
                        ],
                        [
                            'label' => 'DESCRIPCION CIUDAD/MUNICIPIO',
                            'field' => 'mun_descripcion'
                        ]
                    ]
                ],
                'emp_direccion' => 'DIRECCION',
                'emp_telefono' => 'TELEFONO',
                'emp_web' => 'SITIO WEB',
                'emp_correo' => 'CORREO',
                'emp_twitter' => 'TWITTER',
                'emp_facebook' => 'FACEBOOK',
                'emp_prioridad_agendamiento' => 'PRIORIDAD AGENDAMIENTO',
                'sft_id' => [
                    'label' => 'SOFTWARE PROVEEDOR TECNOLOGICO',
                    'relation' => ['name' => 'getProveedorTecnologico', 'field' => 'sft_identificador']
                ],
                'estado' =>  [
                    'label' => 'ESTADO',
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
            'titulo' => 'empleadores'
        ];

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Configura las reglas para poder actualizar o crear los datos de los empleadores.
     *
     * @return mixed
     */
    private function getRules() {
        $rules = array_merge($this->className::$rules);
        unset($rules['tdo_id']);
        unset($rules['pai_id']);
        unset($rules['dep_id']);
        unset($rules['mun_id']);
        unset($rules['sft_id']);

        $rules['tdo_codigo']                 = 'required|string|max:2';
        $rules['pai_codigo']                 = 'required|string|max:10';
        $rules['dep_codigo']                 = 'required|string|max:10';
        $rules['mun_codigo']                 = 'required|string|max:10';
        $rules['emp_correo']                 = 'nullable|email|string|max:255';
        $rules['sft_identificador']          = 'required|string|max:255';
        $rules['emp_prioridad_agendamiento'] = 'nullable|string|regex:/^[0-9]{1,2}$/';

        return $rules;
    }
    
    /**
     * Almacena un empleador recién creado en el almacenamiento.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $this->errors = [];
        // Usuario autenticado
        $this->user = auth()->user();
        $validatorEmail = $this->validationEmailRule($request->emp_correo);
        if (!empty($validatorEmail['errors']))
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors'  => $validatorEmail['errors']
            ], 404);

        $validador = Validator::make($request->all(), $this->getRules());

        if (!$validador->fails()) {
            // Válida si ya existe creado un empleador con la misma identificación
            $existe = $this->className::select('emp_id')->where('emp_identificacion', $request->emp_identificacion)
                ->first();

            if ($existe) {
                return response()->json([
                    'message' => $this->mensajeErroresCreacion,
                    'errors' => ["El Empleador con identificación [{$request->emp_identificacion}] ya existe."]
                ], 409);
            }

            // Se validan los datos paramétricos
            $data = $this->validarDatos($request);

            if (!empty($this->errors)) {
                return response()->json([
                    'message' => $this->mensajeErroresCreacion,
                    'errors'  => $this->errors
                ], 409);
            }

            $data['bdd_id_rg']        = $this->user->bdd_id_rg;
            $data['usuario_creacion'] = $this->user->usu_id;
            $data['estado']           = 'ACTIVO';

            try {
                $objCreate = $this->className::create($data);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => $this->mensajeErroresCreacion,
                    'errors' => []
                ], 422);
            }

            return response()->json([
                'success' => true,
                'emp_id'  => $objCreate->emp_id
            ], 200);
        } else {
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors' => $validador->errors()->all()
            ], 400);
        }
    }
    
    /**
     * Muestra el empleador especificado.
     *
     * @param  int  $id Identificador del registro procesado
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getTipoDocumento',
            'getParametrosPais',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getProveedorTecnologico'
        ];

        return $this->procesadorShow($id, $arrRelaciones, true);
    }

    /**
     * Actualiza el empleador especificado en el almacenamiento.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id $id Identificador del registro procesado
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id){
        $this->errors = [];
        // Usuario autenticado
        $this->user = auth()->user();
        $validatorEmail = $this->validationEmailRule($request->emp_correo);
        if (!empty($validatorEmail['errors']))
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors'  => $validatorEmail['errors']
            ], 404);

        $validador = Validator::make($request->all(), $this->getRules());
        if (!$validador->fails()) {
            // Válida si existe creado el empleador
            $empleador = $this->className::where('emp_identificacion', $id)
                ->first();

            if (!$empleador) {
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors' => ["El Empleador con identificación [{$id}] no existe."]
                ], 409);
            }

            // Válida si ya existe creado un empleador con la misma identificación
            $existe = $this->className::select('emp_id')
                ->where('emp_identificacion', $request->emp_identificacion)
                ->where('emp_id', '!=', $empleador->emp_id)
                ->first();

            if ($existe) {
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors' => ["El Empleador con identificación [{$request->emp_identificacion}] ya existe."]
                ], 409);
            }

            // Se validan los datos paramétricos
            $data = $this->validarDatos($request);

            if (!empty($this->errors)) {
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors'  => $this->errors
                ], 409);
            }

            $data['bdd_id_rg'] = $this->user->bdd_id_rg;

            try {
                $objUpdate = $empleador->update($data);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors' => []
                ], 422);
            }

            return response()->json([
                'success' => true
            ], 200);
        } else {
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors' => $validador->errors()->all()
            ], 400);
        }
    }

    /**
     * Metodo para validar los datos por la opción de Nuevo y Editar.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return array  Errores de validación
     */
    public function validarDatos(Request $request) {
        $data = [];

        // Válida que el tipo de documento exista y este vigente 
        $tipoDocumento = ParametrosTipoDocumento::select(['tdo_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('tdo_codigo', $request->tdo_codigo)
            ->where('tdo_aplica_para', 'LIKE', '%DN%')
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('tdo_codigo')
            ->map( function($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    return $vigente['registro'];
                }
            })->first();

        if(!$tipoDocumento) {
            $this->errors = $this->adicionarError($this->errors, ["El Tipo Documento con Código [{$request->tdo_codigo}], ya no Está Vigente, no aplica para Documento Nomina Electrónica, se Encuentra INACTIVO o no Existe."]);
        }

        // Válida que el país exista y este vigente 
        $pais = ParametrosPais::select(['pai_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('pai_codigo', $request->pai_codigo)
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('pai_codigo')
            ->map( function($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    return $vigente['registro'];
                }
            })->first();

        if(!$pais) {
            $this->errors = $this->adicionarError($this->errors, ["El País con Código [{$request->pai_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe."]);
        }

        // Válida que el departamento exista y este vigente 
        $departamento = ParametrosDepartamento::select(['dep_id', 'pai_id', 'dep_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('dep_codigo', $request->dep_codigo)
            ->where('pai_id', $pais->pai_id ?? '')
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy(function($item, $key) {
                return $item->pai_id . $item->dep_codigo;
            })
            ->map( function($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    return $vigente['registro'];
                }
            })->first();

        if(!$departamento) {
            $this->errors = $this->adicionarError($this->errors, ["El Departamento con Código [{$request->dep_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe para el País [{$request->pai_codigo}]."]);
        }

        // Válida que el municipio exista y este vigente 
        $municipio = ParametrosMunicipio::select(['mun_id', 'pai_id', 'dep_id', 'mun_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('mun_codigo', $request->mun_codigo)
            ->where('pai_id', $pais->pai_id ?? '')
            ->where('dep_id', $departamento->dep_id ?? '')
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy(function($item, $key) {
                return $item->pai_id . $item->dep_id . $item->mun_codigo;
            })
            ->map( function($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    return $vigente['registro'];
                }
            })->first();

        if(!$municipio) {
            $this->errors = $this->adicionarError($this->errors, ["El Municipio con Código [{$request->mun_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe para el Departamento [{$request->dep_codigo}]."]);
        }

        // Software Proveedor Tecnológico
        $softwareProveedorTecnologico = ConfiguracionSoftwareProveedorTecnologico::select(['sft_id'])
            ->where('sft_aplica_para', 'LIKE', '%DN%')
            ->where('estado', 'ACTIVO');
        
        if(empty(auth()->user()->bdd_id_rg))
            $softwareProveedorTecnologico = $softwareProveedorTecnologico->whereNull('bdd_id_rg');
        else
            $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('bdd_id_rg',auth()->user()->bdd_id_rg);

        if($request->has('sft_id') && !empty($request->sft_id))
            $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('sft_id', $request->sft_id);
        else
            $softwareProveedorTecnologico = $softwareProveedorTecnologico->where('sft_identificador', $request->sft_identificador);

        $softwareProveedorTecnologico = $softwareProveedorTecnologico->first();

        if(!$softwareProveedorTecnologico) {
            $this->errors = $this->adicionarError($this->errors, ["El Software de Proveedor Tecnológico [{$request->sft_identificador}], se Encuentra INACTIVO o no Existe."]);
        }

        if(empty($this->errors)) {
            $request->merge([
                'tdo_id' => $tipoDocumento->tdo_id,
                'pai_id' => $pais->pai_id,
                'dep_id' => $departamento->dep_id,
                'mun_id' => $municipio->mun_id,
                'sft_id' => $softwareProveedorTecnologico->sft_id
            ]);

            // Se arma el array con los datos a procesar al momento de crear y actualizar
            $data = [
                'tdo_id'                     => $request->tdo_id,
                'emp_identificacion'         => $request->emp_identificacion,
                'emp_razon_social'           => $request->emp_razon_social,
                'emp_primer_apellido'        => $request->emp_primer_apellido,
                'emp_segundo_apellido'       => $request->emp_segundo_apellido,
                'emp_primer_nombre'          => $request->emp_primer_nombre,
                'emp_otros_nombres'          => $request->emp_otros_nombres,
                'pai_id'                     => $request->pai_id,
                'dep_id'                     => $request->dep_id,
                'mun_id'                     => $request->mun_id,
                'emp_direccion'              => $request->emp_direccion,
                'emp_telefono'               => $request->emp_telefono,
                'emp_web'                    => $request->emp_web,
                'emp_correo'                 => $request->emp_correo,
                'emp_twitter'                => $request->emp_twitter,
                'emp_facebook'               => $request->emp_facebook,
                'sft_id'                     => $request->sft_id,
                'emp_prioridad_agendamiento' => $request->filled('emp_prioridad_agendamiento') ? $request->emp_prioridad_agendamiento : null
            ];
        }

        return $data;
    }

    /**
     * Cambia el estado de los empleadores en el almacenamiento.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function cambiarEstado(Request $request) {
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Genera una Interfaz de empleadores para guardar en Excel.
     *
     * @param Request $request
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceEmpleador(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'empleadores');  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de empleadores.
     *
     * @param Request $request
     * @return
     * @throws \Exception
     */
    public function cargarEmpleadores(Request $request){
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
                    'message' => 'Errores al guardar los Empleadores',
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
                        $columna['prioridad_agendamiento'],
                        $columna['estado'],
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
                    'message' => 'Errores al guardar los Empleadores',
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores = [];
            $arrResultado = [];
            $arrExisteEmpleador = [];
            $arrExistePais = [];
            $arrExisteDepartamento = [];
            $arrExisteMunicipio = [];
            $arrExisteSft = [];

            $arrExisteTipoDocumento = [];
            ParametrosTipoDocumento::where('tdo_aplica_para', 'LIKE', '%DN%')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('tdo_codigo')
                ->map(function ($doc) use (&$arrExisteTipoDocumento) {
                    $vigente = $this->validarVigenciaRegistroParametrica($doc);
                    if ($vigente['vigente']) {
                        $arrExisteTipoDocumento[$vigente['registro']->tdo_codigo] = $vigente['registro'];
                    }
                });

            ConfiguracionSoftwareProveedorTecnologico::where('sft_aplica_para', 'LIKE', '%DN%')
                ->where('estado', 'ACTIVO')
                ->get()
                ->map(function ($sft) use (&$arrExisteSft) {
                    $arrExisteSft[$sft->sft_identificador] = $sft;
                });

            foreach ($data as $fila => $columnas) {

                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrEmpleador = [];
                $arrFaltantes = $this->checkFields($Acolumnas, [
                    'codigo_tipo_documento',
                    'identificacion',
                    'codigo_pais',
                    'codigo_departamento',
                    'codigo_ciudad_municipio',
                    'direccion',
                    'software_proveedor_tecnologico'
                ], $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {

                    //Empleador
                    $arrEmpleador['emp_id'] = 0;
                    $arrEmpleador['emp_identificacion'] = (string)$columnas->identificacion;

                    if (!preg_match("/^[1-9]/", $arrEmpleador['emp_identificacion']) && 
                        ($columnas->codigo_tipo_documento == '13' || $columnas->codigo_tipo_documento == '31')
                    ) {
                        $arrErrores = $this->adicionarError($arrErrores, ['El formato del campo Identificación del Empleador es inválido.'], $fila);
                    }

                    if (array_key_exists($columnas->identificacion, $arrExisteEmpleador)){
                        $objExisteEmpleador = $arrExisteEmpleador[$columnas->identificacion];
                        $arrEmpleador['emp_id'] = $objExisteEmpleador->emp_id;
                    } else {
                        $objExisteEmpleador = $this->className::where('emp_identificacion', $columnas->identificacion)
                            ->first();

                        if ($objExisteEmpleador){
                            $arrExisteEmpleador[$columnas->identificacion] = $objExisteEmpleador;
                            $arrEmpleador['emp_id'] = $objExisteEmpleador->emp_id;
                        }
                    }

                    if (array_key_exists($columnas->codigo_tipo_documento, $arrExisteTipoDocumento)) {
                        $objExisteTipoDocumento = $arrExisteTipoDocumento[$columnas->codigo_tipo_documento];
                        $arrEmpleador['tdo_id'] = $objExisteTipoDocumento->tdo_id;
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ['El Código del Tipo de Documento [' . $columnas->codigo_tipo_documento . '], ya no está vigente, no aplica para Documento Nómina Electrónica, se encuentra INACTIVO o no existe.'], $fila);
                    }

                    // Si el tipo de documento es 13 se obliga a digitar el primer nombre y primer apellido
                    if ($columnas->codigo_tipo_documento == '13') {
                        if (empty($columnas->primer_nombre) || empty($columnas->primer_apellido)) {
                            $arrErrores = $this->adicionarError($arrErrores, ['El Primer Nombre y Primer Apellido son Obligatorios.'], $fila);
                        }

                    // Si el tipo de documento es 31 se obliga a digitar la razón salical
                    } elseif ($columnas->codigo_tipo_documento == '31') {
                        if (empty($columnas->razon_social)) {
                            $arrErrores = $this->adicionarError($arrErrores, ['La Razón Social es Obligatoria.'], $fila);
                        }
                    }

                    //DOMICILIO
                    $arrEmpleador['pai_id'] = null;
                    $arrEmpleador['dep_id'] = null;
                    $arrEmpleador['mun_id'] = null;

                    // Válida que exista y este vigente el país cargado
                    if (array_key_exists($columnas->codigo_pais, $arrExistePais)) {
                        $objExistePais = $arrExistePais[$columnas->codigo_pais];
                        $arrEmpleador['pai_id'] = $objExistePais->pai_id;
                    } else {
                        $objExistePais = ParametrosPais::where('pai_codigo', $columnas->codigo_pais)
                            ->where('estado', 'ACTIVO')
                            ->get()
                            ->groupBy('pai_codigo')
                            ->map(function ($doc) use (&$arrExistePais, $columnas) {
                                $vigente = $this->validarVigenciaRegistroParametrica($doc);
                                if ($vigente['vigente']) {
                                    $arrExistePais[$columnas->codigo_pais] = $vigente['registro'];
                                }
                            });

                        if (empty($arrExistePais)) {
                            $arrErrores = $this->adicionarError($arrErrores, ['El País con código [' . $columnas->codigo_pais . '] ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                        } else {
                            $arrEmpleador['pai_id'] = $arrExistePais[$columnas->codigo_pais]->pai_id;
                        }
                    }

                    // Válida que exista y este vigente el departamento cargado
                    if (array_key_exists($columnas->codigo_departamento, $arrExisteDepartamento)) {
                        $objExisteDepartamento = $arrExisteDepartamento[$columnas->codigo_departamento];
                        $arrEmpleador['dep_id'] = $objExisteDepartamento->dep_id;
                    } else {
                        if (!empty($arrExistePais)) {
                            $objExisteDepartamento = ParametrosDepartamento::where('dep_codigo', $columnas->codigo_departamento)
                                ->where('pai_id', $arrExistePais[$columnas->codigo_pais]->pai_id)
                                ->where('estado', 'ACTIVO')
                                ->get()
                                ->groupBy(function($item, $key) {
                                    return $item->pai_id . $item->dep_codigo;
                                })
                                ->map(function ($doc) use (&$arrExisteDepartamento, $columnas) {
                                    $vigente = $this->validarVigenciaRegistroParametrica($doc);
                                    if ($vigente['vigente']) {
                                        $arrExisteDepartamento[$columnas->codigo_departamento] = $vigente['registro'];
                                    }
                                });

                            if (empty($arrExisteDepartamento)) {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Departamento con código [' . $columnas->codigo_departamento . '] ya no está vigente, se encuentra INACTIVO o no existe para el País [' . $columnas->codigo_pais . '].'], $fila);
                            } else {
                                $arrEmpleador['dep_id'] = $arrExisteDepartamento[$columnas->codigo_departamento]->dep_id;
                            }
                        }
                    }

                    // Válida que exista y este vigente el municipio cargado
                    if (array_key_exists($columnas->codigo_ciudad_municipio, $arrExisteMunicipio)) {
                        $objExisteMunicipio = $arrExisteMunicipio[$columnas->codigo_ciudad_municipio];
                        $arrEmpleador['mun_id'] = $objExisteMunicipio->mun_id;
                    } else {
                        if (!empty($arrExisteDepartamento)) {
                            $objExisteMunicipio = ParametrosMunicipio::where('mun_codigo', $columnas->codigo_ciudad_municipio)
                            ->where('pai_id',  $arrExistePais[$columnas->codigo_pais]->pai_id)
                            ->where('dep_id',  $arrExisteDepartamento[$columnas->codigo_departamento]->dep_id)
                            ->where('estado', 'ACTIVO')
                            ->get()
                            ->groupBy(function($item, $key) {
                                return $item->pai_id . $item->dep_id . $item->mun_codigo;
                            })
                            ->map(function ($doc) use (&$arrExisteMunicipio, $columnas) {
                                $vigente = $this->validarVigenciaRegistroParametrica($doc);
                                if ($vigente['vigente']) {
                                    $arrExisteMunicipio[$columnas->codigo_ciudad_municipio] = $vigente['registro'];
                                }
                            });

                            if (empty($arrExisteMunicipio)) {
                                $arrErrores = $this->adicionarError($arrErrores, ['El Municipio con código [' . $columnas->codigo_ciudad_municipio . '] ya no está vigente, se encuentra INACTIVO o no existe para el Departamento [' . $columnas->codigo_departamento . '].'], $fila);
                            } else {
                                $arrEmpleador['mun_id'] = $arrExisteMunicipio[$columnas->codigo_ciudad_municipio]->mun_id;
                            }
                        }
                    }

                    $arrEmpleador['sft_id'] = '';
                    if (array_key_exists($columnas->software_proveedor_tecnologico, $arrExisteSft)) {
                        $objExisteSft = $arrExisteSft[$columnas->software_proveedor_tecnologico];
                        $arrEmpleador['sft_id'] = $objExisteSft->sft_id;
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ['La Identificación del Software Proveedor Tecnologico [' . $columnas->software_proveedor_tecnologico . '] no existe o no aplica para [DN].'], $fila);
                    }

                    $arrEmpleador['emp_razon_social']           = $this->sanitizarStrings($columnas->razon_social);
                    $arrEmpleador['emp_primer_apellido']        = $this->sanitizarStrings($columnas->primer_apellido);
                    $arrEmpleador['emp_segundo_apellido']       = $this->sanitizarStrings($columnas->segundo_apellido);
                    $arrEmpleador['emp_primer_nombre']          = $this->sanitizarStrings($columnas->primer_nombre);
                    $arrEmpleador['emp_otros_nombres']          = $this->sanitizarStrings($columnas->otros_nombres);
                    $arrEmpleador['emp_direccion']              = $this->sanitizarStrings($columnas->direccion);
                    $arrEmpleador['emp_telefono']               = $this->sanitizarStrings($columnas->telefono);
                    $arrEmpleador['emp_web']                    = $this->sanitizarStrings($columnas->sitio_web);
                    $arrEmpleador['emp_correo']                 = $this->sanitizarStrings($columnas->correo);
                    $arrEmpleador['emp_twitter']                = $this->sanitizarStrings($columnas->twitter);
                    $arrEmpleador['emp_facebook']               = $this->sanitizarStrings($columnas->facebook);

                    if(count($arrErrores) == 0){
                        $rules = array_merge($this->className::$rules);
                        $rules['emp_correo'] = 'nullable|email|string|max:255';
                        $objValidator = Validator::make($arrEmpleador, $rules);
                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrEmpleador;
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
                    'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_EMP,
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
                    'message' => 'Errores al guardar los Empleadores',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            } else {
                $bloque_empleador = [];
                foreach ($arrResultado as $empleador) {
                    $data = [
                        'emp_id'                     => $empleador['emp_id'],
                        'tdo_id'                     => $empleador['tdo_id'],
                        'emp_identificacion'         => $empleador['emp_identificacion'],
                        'emp_razon_social'           => !empty($empleador['emp_razon_social']) ? $this->sanitizarStrings($empleador['emp_razon_social']) : null,
                        'emp_primer_apellido'        => !empty($empleador['emp_primer_apellido']) ? $this->sanitizarStrings($empleador['emp_primer_apellido']): null,
                        'emp_segundo_apellido'       => !empty($empleador['emp_segundo_apellido']) ? $this->sanitizarStrings($empleador['emp_segundo_apellido']): null,
                        'emp_primer_nombre'          => !empty($empleador['emp_primer_nombre']) ? $this->sanitizarStrings($empleador['emp_primer_nombre']): null,
                        'emp_otros_nombres'          => !empty($empleador['emp_otros_nombres']) ? $this->sanitizarStrings($empleador['emp_otros_nombres']): null,
                        'pai_id'                     => $empleador['pai_id'],
                        'dep_id'                     => $empleador['dep_id'],
                        'mun_id'                     => $empleador['mun_id'],
                        'emp_direccion'              => !empty($empleador['emp_direccion']) ? $this->sanitizarStrings($empleador['emp_direccion']): null,
                        'emp_telefono'               => !empty($empleador['emp_telefono']) ? $this->sanitizarStrings($empleador['emp_telefono']): null,
                        'emp_web'                    => !empty($empleador['emp_web']) ? $this->sanitizarStrings($empleador['emp_web']): null,
                        'emp_correo'                 => !empty($empleador['emp_correo']) ? $this->sanitizarStrings($empleador['emp_correo']): null,
                        'emp_twitter'                => !empty($empleador['emp_twitter']) ? $this->sanitizarStrings($empleador['emp_twitter']): null,
                        'emp_facebook'               => !empty($empleador['emp_facebook']) ? $this->sanitizarStrings($empleador['emp_facebook']): null,
                        'sft_id'                     => !empty($empleador['sft_id']) ? $this->sanitizarStrings($empleador['sft_id']): null
                    ];

                    array_push($bloque_empleador, $data);
                }

                if (!empty($bloque_empleador)) {
                    $bloques = array_chunk($bloque_empleador, 100);
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
                                'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_EMP,
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
                'message' => 'Errores al guardar los Empleadores',
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de empleadores.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListaErroresEmpleador(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_EMP);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de empleadores.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function descargarListaErroresEmpleador(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_EMP, true, 'carga_empleador_log_errores');
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
     * Efectua un proceso de busqueda en la parametrica de empleadores.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function busqueda(Request $request) {
        $columnas = [
            'emp_id',
            'tdo_id',
            'emp_identificacion',
            'emp_razon_social',
            'emp_primer_apellido',
            'emp_segundo_apellido',
            'emp_primer_nombre',
            'emp_otros_nombres',
            \DB::raw('IF(emp_razon_social IS NULL OR emp_razon_social = "", CONCAT(COALESCE(emp_primer_nombre, ""), " ", COALESCE(emp_otros_nombres, ""), " ", COALESCE(emp_primer_apellido, ""), " ", COALESCE(emp_segundo_apellido, "")), emp_razon_social) as nombre_completo'),
            'pai_id',
            'dep_id',
            'mun_id',
            'emp_direccion',
            'emp_telefono',
            'emp_web',
            'emp_correo',
            'emp_twitter',
            'emp_facebook',
            'sft_id',
            'emp_prioridad_agendamiento',
            'bdd_id_rg',
            'estado'
        ];

        $incluir = [
            'getUsuarioCreacion',
            'getTipoDocumento',
            'getParametrosPais',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getProveedorTecnologico'
        ];

        return $this->procesadorBusqueda($request, $columnas, $incluir);
    }

    /**
     * Busca los empleadores en base a un criterio particular.
     *
     * @param [type] $valorBuscar Texto de búsqueda
     * @return Response
     */
    public function buscarEmpleadores($valorBuscar)
    {
        $comodin = "'%$valorBuscar%'";
        // $sqlRaw = "(emp_razon_social LIKE $comodin OR CONCAT(emp_primer_nombre, ' ', emp_otros_nombres, ' ', emp_primer_apellido, ' ', emp_segundo_apellido) LIKE $comodin)";
        $empleadores = $this->className::where('estado', 'ACTIVO')
            ->where('emp_razon_social', 'LIKE', '%'.$valorBuscar.'%')
            ->orWhere('emp_primer_nombre', 'LIKE', '%'.$valorBuscar.'%')
            ->orWhere('emp_otros_nombres', 'LIKE', '%'.$valorBuscar.'%')
            ->orWhere('emp_primer_apellido', 'LIKE', '%'.$valorBuscar.'%')
            ->orWhere('emp_segundo_apellido', 'LIKE', '%'.$valorBuscar.'%')
            ->select(['emp_id', 'emp_identificacion',
                \DB::raw('IF(emp_razon_social IS NULL OR emp_razon_social = "", CONCAT(COALESCE(emp_primer_nombre, ""), " ", COALESCE(emp_otros_nombres, ""), " ", COALESCE(emp_primer_apellido, ""), " ", COALESCE(emp_segundo_apellido, "")), emp_razon_social) as nombre_completo')])
            ->get();

        return response()->json([
            'data' => $empleadores
        ], 200);
    }
}
