<?php

namespace App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Console\Commands\ProcesarCargaParametricaCommand;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContrato;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;
use App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajador;
use App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajador;
use App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajador;

class ConfiguracionTrabajadorController extends OpenTenantController{
    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Trabajador';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Trabajadores';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ConfiguracionTrabajador::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Trabajador';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Trabajador';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Trabajador';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Trabajador';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Trabajador [%s] no Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id del Trabajador [%s] Esta Inactivo';

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
    public $nombreDatoCambiarEstado = 'trabajadores';

    /**
     * Nombre del campo que contiene la identificación del registro.
     * 
     * @var String
     */
    public $nombreCampoIdentificacion = 'tra_identificacion';

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
        'NIT EMPLEADOR',
        'CODIGO TIPO DOCUMENTO',    
        'DESCRIPCION TIPO DOCUMENTO',
        'IDENTIFICACION',
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
        'CODIGO TRABAJADOR',
        'FECHA INGRESO',
        'FECHA RETIRO',
        'SUELDO',
        'CODIGO TIPO CONTRATO',
        'DESCRIPCION TIPO CONTRATO',
        'CODIGO TIPO TRABAJADOR',
        'DESCRIPCION TIPO TRABAJADOR',
        'CODIGO SUBTIPO TRABAJADOR',
        'DESCRIPCION SUBTIPO TRABAJADOR',
        'TABAJADOR DE ALTO RIESGO PENSION (SI/NO)',
        'SALARIO INTEGRAL (SI/NO)',
        'NOMBRE ENTIDAD BANCARIA',
        'TIPO DE CUENTA',
        'NUMERO DE CUENTA'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnTrabajador,ConfiguracionDnTrabajadorNuevo,ConfiguracionDnTrabajadorEditar,ConfiguracionDnTrabajadorVer,ConfiguracionDnTrabajadorCambiarEstado,ConfiguracionDnTrabajadorSubir,ConfiguracionDnTrabajadorDescargarExcel'
        ])->except([
            'show',
            'store',
            'update',
            'cambiarEstado',
            'updateCompuesto',
            'showCompuesto',
            'busqueda',
            'searchTrabajadores',
            'adminFromDI',
            'generarInterfaceTrabajador',
            'getListaErroresTrabajador',
            'descargarListaErroresTrabajador',
            'cargarTrabajadores'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnTrabajadorNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnTrabajadorEditar'
        ])->only([
            'update',
            'updateCompuesto'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnTrabajadorVer,ConfiguracionDnTrabajadorEditar'
        ])->only([
            'show',
            'showCompuesto'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnTrabajadorVer'
        ])->only([
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnTrabajadorCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionDnTrabajadorSubir'
        ])->only([
            'generarInterfaceTrabajador',
            'getListaErroresTrabajador',
            'descargarListaErroresTrabajador',
            'cargarTrabajadores'
        ]);
    }

    /**
     * Muestra una lista de trabajadores.
     *
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function getListaTrabajadores(Request $request) {
        $user = auth()->user();

        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];

        $columnas = [
            'tra_id',
            'dsn_trabajadores.emp_id',
            'dsn_trabajadores.tdo_id',
            'tra_identificacion',
            'tra_primer_apellido',
            'tra_segundo_apellido',
            'tra_primer_nombre',
            'tra_otros_nombres',
            \DB::raw('CONCAT(COALESCE(tra_primer_nombre, ""), " ", COALESCE(tra_otros_nombres, ""), " ", COALESCE(tra_primer_apellido, ""), " ", COALESCE(tra_segundo_apellido, "")) as nombre_completo'),
            'dsn_trabajadores.pai_id',
            'dsn_trabajadores.dep_id',
            'dsn_trabajadores.mun_id',
            'tra_direccion',
            'tra_telefono',
            'tra_codigo',
            'tra_fecha_ingreso',
            'tra_fecha_retiro',
            'tra_sueldo',
            'ntc_id',
            'ntt_id',
            'nst_id',
            'tra_alto_riesgo',
            'tra_salario_integral',
            'tra_entidad_bancaria',
            'tra_tipo_cuenta',
            'tra_numero_cuenta',
            'dsn_trabajadores.usuario_creacion',
            'dsn_trabajadores.estado',
            'dsn_trabajadores.fecha_modificacion',
            'dsn_trabajadores.fecha_creacion'
        ];

        $relaciones = [
            'getUsuarioCreacion',
            'getEmpleador',
            'getTipoDocumento',
            'getParametrosPais',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getParametrosTipoContrato',
            'getParametrosTipoTrabajador',
            'getParametrosSubtipoTrabajador'
        ];

        $whereHasConditions = [];
        if(!empty($user->bdd_id_rg)) {
            $whereHasConditions[] = [
                'relation' => 'getEmpleador',
                'function' => function($query) use ($user) {
                    $query->where('bdd_id_rg', $user->bdd_id_rg);
                }
            ];
        } else {
            $whereHasConditions[] = [
                'relation' => 'getEmpleador',
                'function' => function($query) {
                    $query->whereNull('bdd_id_rg');
                }
            ];
        }

        $exportacion = [
            'columnas' => [
                'emp_id' => [
                    'label' => 'NIT EMPLEADOR',
                    'relation' => ['name' => 'getEmpleador', 'field' => 'emp_identificacion']
                ],
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
                'tra_identificacion' => 'IDENTIFICACION',
                'tra_primer_apellido' => 'PRIMER APELLIDO',
                'tra_segundo_apellido' => 'SEGUNDO APELLIDO',
                'tra_primer_nombre' => 'PRIMER NOMBRE',
                'tra_otros_nombres' => 'OTROS NOMBRES',
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
                'tra_direccion' => 'DIRECCION',
                'tra_telefono' => 'TELEFONO',
                'tra_codigo' => 'CODIGO TRABAJADOR',
                'tra_fecha_ingreso' => 'FECHA INGRESO',
                'tra_fecha_retiro' => 'FECHA RETIRO',
                'tra_sueldo' => 'SUELDO',
                [
                    'multiple' => true,
                    'relation' => 'getParametrosTipoContrato',
                    'fields' => [
                        [
                            'label' => 'CODIGO TIPO CONTRATO',
                            'field' => 'ntc_codigo'
                        ],
                        [
                            'label' => 'DESCRIPCION TIPO CONTRATO',
                            'field' => 'ntc_descripcion'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getParametrosTipoTrabajador',
                    'fields' => [
                        [
                            'label' => 'CODIGO TIPO TRABAJADOR',
                            'field' => 'ntt_codigo'
                        ],
                        [
                            'label' => 'DESCRIPCION TIPO TRABAJADOR',
                            'field' => 'ntt_descripcion'
                        ]
                    ]
                ],
                [
                    'multiple' => true,
                    'relation' => 'getParametrosSubtipoTrabajador',
                    'fields' => [
                        [
                            'label' => 'CODIGO SUBTIPO TRABAJADOR',
                            'field' => 'nst_codigo'
                        ],
                        [
                            'label' => 'DESCRIPCION SUBTIPO TRABAJADOR',
                            'field' => 'nst_descripcion'
                        ]
                    ]
                ],
                'tra_alto_riesgo' => 'TABAJADOR DE ALTO RIESGO PENSION (SI/NO)',
                'tra_salario_integral' => 'SALARIO INTEGRAL (SI/NO)',
                'tra_entidad_bancaria' => 'NOMBRE ENTIDAD BANCARIA',
                'tra_tipo_cuenta' => 'TIPO DE CUENTA',
                'tra_numero_cuenta' => 'NUMERO DE CUENTA',
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
            'titulo' => 'trabajadores'
        ];

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Configura las reglas para poder actualizar o crear los datos de los trabajadores.
     *
     * @return mixed
     */
    private function getRules() {
        $rules = array_merge($this->className::$rules);
        unset($rules['emp_id']);
        unset($rules['tdo_id']);
        unset($rules['pai_id']);
        unset($rules['dep_id']);
        unset($rules['mun_id']);
        unset($rules['ntc_id']);
        unset($rules['ntt_id']);
        unset($rules['nst_id']);

        $rules['emp_identificacion'] = 'required|string|max:20';
        $rules['tdo_codigo'] = 'required|string|max:2';
        $rules['pai_codigo'] = 'nullable|string|max:10';
        $rules['dep_codigo'] = 'nullable|string|max:10';
        $rules['mun_codigo'] = 'nullable|string|max:10';
        $rules['ntc_codigo'] = 'required|string|max:10';
        $rules['nst_codigo'] = 'required|string|max:10';

        return $rules;
    }

    /**
     * Almacena un trabajador recién creado en el almacenamiento.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $this->errors = [];
        // Usuario autenticado
        $this->user = auth()->user();
        $validador = Validator::make($request->all(), $this->getRules());

        if (!$validador->fails()) {
            $empleador = ConfiguracionEmpleador::select('emp_id')
                ->where('emp_identificacion', $request->emp_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();

            // Válida si ya existe creado un trabajador con la misma identificación y para el mismo empleador
            $existe = $this->className::select('tra_id')
                ->where('emp_id', $empleador->emp_id ?? '')
                ->where('tra_identificacion', $request->tra_identificacion)
                ->first();

            if ($existe) {
                return response()->json([
                    'message' => $this->mensajeErroresCreacion,
                    'errors' => ["El Trabajador con identificación [{$request->tra_identificacion}], para el Empleador con identificación [{$request->emp_identificacion}] ya existe."]
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
                'tra_id'  => $objCreate->tra_id
            ], 200);
        } else {
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors' => $validador->errors()->all()
            ], 400);
        }
    }

    /**
     * Muestra el Trabajador especificado.
     *
     * @param  int  $id Identificador del registro procesado
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getTipoDocumento',
            'getEmpleador',
            'getParametrosPais',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getParametrosTipoContrato',
            'getParametrosTipoTrabajador',
            'getParametrosSubtipoTrabajador'
        ];

        return $this->procesadorShow($id, $arrRelaciones, true);
    }

    /**
     * Muestra el trabajador especificado por la identificación del empleador al que está asociado y la identificación del trabajador.
     *
     * @param string $emp_identificacion Identificación del Empleador
     * @param string $tra_identificacion Identificación del Trabajador
     * @return \Illuminate\Http\Response
     */
    public function showCompuesto(string $emp_identificacion, string $tra_identificacion) {
        $arrRelaciones = [
            'getUsuarioCreacion',
            'getEmpleador',
            'getTipoDocumento',
            'getParametrosPais',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getParametrosTipoContrato',
            'getParametrosTipoTrabajador',
            'getParametrosSubtipoTrabajador'
        ];

        $objEmpleador = ConfiguracionEmpleador::select(['emp_id'])
            ->where('emp_identificacion', $emp_identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$objEmpleador) {
            return response()->json([
                'message' =>sprintf("El Empleador [%s] no existe o se encuentra en estado INACTIVO.", $emp_identificacion),
                'errors' => []
            ], Response::HTTP_NOT_FOUND);
        }

        $objetoModel = $this->className::where('tra_identificacion', $tra_identificacion)
            ->where('emp_id', $objEmpleador->emp_id);

        if (!empty($arrRelaciones))
            $objetoModel = $objetoModel->with($arrRelaciones);

        $objetoModel = $objetoModel->first();


        if ($objetoModel){
            $arrTrabajador = $objetoModel->toArray();

            return response()->json([
                'data' => $arrTrabajador
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'message' =>sprintf("El Trabajador [%s], para el Empleador [%s] no existe o se encuentra en estado INACTIVO.", $tra_identificacion, $emp_identificacion),
                'errors' => []
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => sprintf("El Trabajador [%s] no existe", $tra_identificacion),
            'errors' => []
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Actualiza el trabajador especificado en el almacenamiento.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $emp_id Id del empleador
     * @param  string  $tra_identificacion Identificación del trabajador
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $emp_id, string $tra_identificacion){
        $this->errors = [];
        // Usuario autenticado
        $this->user = auth()->user();
        $validador = Validator::make($request->all(), $this->getRules());
        if (!$validador->fails()) {
            $trabajador = $this->className::where('emp_id', $emp_id)
                ->where('tra_identificacion', $tra_identificacion)
                ->first();

            // Válida si existe el empleador y se encuentra en estado activo
            $empleador = ConfiguracionEmpleador::select('emp_id')
                ->where('emp_identificacion', $request->emp_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$empleador) {
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors' => ["El Empleador con identificación [{$request->emp_identificacion}] no existe."]
                ], 404);
            }

            // Válida si ya existe un trabajador con la misma identificación y para el mismo empleador
            $existe = $this->className::select('tra_id')
                ->where('emp_id', $empleador->emp_id)
                ->where('tra_identificacion', $request->tra_identificacion)
                ->where('tra_id', '!=', $trabajador->tra_id)
                ->first();

            if ($existe) {
                return response()->json([
                    'message' => $this->mensajeErroresModificacion,
                    'errors' => ["El Trabajador con identificación [{$request->tra_identificacion}], para el Empleador con identificación [{$request->emp_identificacion}] ya existe."]
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
                $objUpdate = $trabajador->update($data);
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
     * Permite editar un trabajador en función de un empleador.
     *
     * @param Request $request
     * @param string $emp_identificacion Identificación del empleador
     * @param string $tra_identificacion Identificación del trabajador
     * @return JsonResponse
     */
    public function updateCompuesto(Request $request, string $emp_identificacion, string $tra_identificacion) {
        $this->user = auth()->user();

        $objEmpleador = ConfiguracionEmpleador::select(['emp_id', 'bdd_id_rg'])
            ->where('emp_identificacion', $emp_identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$objEmpleador) {
            return response()->json([
                'message' => 'Error al modificar el Trabajador',
                'errors' => [sprintf("El Empleador con identificación [%s] no existe o se encuentra INACTIVO.", $emp_identificacion)]
            ], Response::HTTP_NOT_FOUND);
        }

        if(!empty($this->user->bdd_id_rg) && $this->user->bdd_id_rg != $objEmpleador->bdd_id_rg) {
            return response()->json([
                'message' => 'Error al modificar el Trabajador',
                'errors' => [sprintf("Empleador con identificación [%s] no válido.", $emp_identificacion)]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $objTrabajador = $this->className::select('tra_id', 'emp_id', 'tra_identificacion')
            ->where('emp_id', $objEmpleador->emp_id)
            ->where('tra_identificacion', $tra_identificacion)
            ->first();

        if (!empty($objTrabajador)) {
            return $this->update($request, $objEmpleador->emp_id, $tra_identificacion);
        } else {
            return response()->json([
                'message' => 'Error al modificar el Trabajador',
                'errors' => ['El Trabajador con identificación [' . $tra_identificacion . '], para el Empleador con identificación [' . $emp_identificacion . '] no existe']
            ], 404);
        }
    }

    /**
     * Metodo para validar los datos por la opción de Nuevo y Editar.
     * 
     * @param  Request  $request
     * @return array  Errores de validación
     * 
     */
    public function validarDatos(Request $request) {
        $this->user = auth()->user();

        $data = [];
        // Válida si existe creado el empleador
        $empleador = ConfiguracionEmpleador::select('emp_id','bdd_id_rg')
            ->where('emp_identificacion', $request->emp_identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$empleador) {
            $this->errors = $this->adicionarErrorLocal($this->errors, ["El Empleador con identificación [{$request->emp_identificacion}] no Existe o se Encuentra INACTIVO."]);
        }

        if(!empty($this->user->bdd_id_rg) && $this->user->bdd_id_rg != $empleador->bdd_id_rg) {
            $this->errors = $this->adicionarErrorLocal($this->errors, ["Empleador con identificación [{$request->emp_identificacion}] no válido."]);
        }

        if ($request->tdo_codigo == '31') {
            $this->errors = $this->adicionarErrorLocal($this->errors, ["No se puede Seleccionar el Tipo Documento con Código [31]."]);
        } else {
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
                $this->errors = $this->adicionarErrorLocal($this->errors, ["El Tipo Documento con Código [{$request->tdo_codigo}], ya no Está Vigente, no aplica para Documento Nómina Electrónica, se Encuentra INACTIVO o no Existe."]);
            }
        }

        // Válida que el país exista y este vigente 
        if ($request->pai_codigo != '') {
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
                $this->errors = $this->adicionarErrorLocal($this->errors, ["El País con Código [{$request->pai_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe."]);
            }
        }

        // Válida que el departamento exista y este vigente
        if ($request->dep_codigo != '') {
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
                $this->errors = $this->adicionarErrorLocal($this->errors, ["El Departamento con Código [{$request->dep_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe para el País [{$request->pai_codigo}]."]);
            }
        }

        // Válida que el municipio exista y este vigente 
        if ($request->mun_codigo != '') {
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
                $this->errors = $this->adicionarErrorLocal($this->errors, ["El Municipio con Código [{$request->mun_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe para el Departamento [{$request->dep_codigo}]."]);
            }
        }

        // Válida que el tipo de contrato exista y este vigente 
        $tipoContrato = ParametrosTipoContrato::select(['ntc_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('ntc_codigo', $request->ntc_codigo)
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('ntc_codigo')
            ->map( function($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    return $vigente['registro'];
                }
            })->first();

        if(!$tipoContrato) {
            $this->errors = $this->adicionarErrorLocal($this->errors, ["El Tipo Contrato con Código [{$request->ntc_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe."]);
        }

        // Válida que el tipo de trabajador exista y este vigente 
        $tipoTrabajador = ParametrosTipoTrabajador::select(['ntt_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('ntt_codigo', $request->ntt_codigo)
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('ntt_codigo')
            ->map( function($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    return $vigente['registro'];
                }
            })->first();

        if(!$tipoTrabajador) {
            $this->errors = $this->adicionarErrorLocal($this->errors, ["El Tipo Trabajador con Código [{$request->ntt_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe."]);
        }

        // Válida que el subtipo de trabajador exista y este vigente 
        $subtipoTrabajador = ParametrosSubtipoTrabajador::select(['nst_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('nst_codigo', $request->nst_codigo)
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('nst_codigo')
            ->map( function($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    return $vigente['registro'];
                }
            })->first();

        if(!$subtipoTrabajador) {
            $this->errors = $this->adicionarErrorLocal($this->errors, ["El Subtipo Trabajador con Código [{$request->nst_codigo}], ya no Está Vigente, se Encuentra INACTIVO o no Existe."]);
        }

        if(empty($this->errors)) {
            $request->merge([
                'emp_id' => $empleador->emp_id,
                'tdo_id' => $tipoDocumento->tdo_id,
                'pai_id' => $pais->pai_id ?? null,
                'dep_id' => $departamento->dep_id ?? null,
                'mun_id' => $municipio->mun_id ?? null,
                'ntc_id' => $tipoContrato->ntc_id,
                'ntt_id' => $tipoTrabajador->ntt_id,
                'nst_id' => $subtipoTrabajador->nst_id
            ]);

            // Se arma el array con los datos a procesar al momento de crear y actualizar
            $data = [
                'emp_id'                   => $request->emp_id,
                'tdo_id'                   => $request->tdo_id,
                'tra_identificacion'       => $request->tra_identificacion,
                'tra_primer_apellido'      => $request->tra_primer_apellido,
                'tra_segundo_apellido'     => $request->tra_segundo_apellido,
                'tra_primer_nombre'        => $request->tra_primer_nombre,
                'tra_otros_nombres'        => $request->tra_otros_nombres,
                'pai_id'                   => $request->pai_id,
                'dep_id'                   => $request->dep_id,
                'mun_id'                   => $request->mun_id,
                'tra_direccion'            => $request->tra_direccion,
                'tra_telefono'             => $request->tra_telefono,
                'tra_codigo'               => $request->tra_codigo,
                'tra_fecha_ingreso'        => $request->tra_fecha_ingreso,
                'tra_fecha_retiro'         => $request->tra_fecha_retiro,
                'tra_sueldo'               => $request->tra_sueldo,
                'ntc_id'                   => $request->ntc_id,
                'ntt_id'                   => $request->ntt_id,
                'nst_id'                   => $request->nst_id,
                'tra_alto_riesgo'          => $request->tra_alto_riesgo,
                'tra_salario_integral'     => $request->tra_salario_integral,
                'tra_entidad_bancaria'     => $request->tra_entidad_bancaria,
                'tra_tipo_cuenta'          => $request->tra_tipo_cuenta,
                'tra_numero_cuenta'        => $request->tra_numero_cuenta
            ];
        }

        return $data;
    }

    /**
     * Cambia el estado de los trabajadores en el almacenamiento.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function cambiarEstado(Request $request) {
        return $this->procesadorCambiarEstadoCompuesto(
            ConfiguracionEmpleador::class,
            'emp_id',
            'emp_identificacion',
            'tra_identificacion',
            'tra_identificacion',
            $request->all(),
            'El Empleador [%s] no existe o se encuentra INACTIVO'
        );
    }

    /**
     * Genera una Interfaz de trabajadores para guardar en Excel.
     *
     * @param Request $request
     * @return App\Http\Modulos\Utils\ExcelExports\ExcelExport
     */
    public function generarInterfaceTrabajador(Request $request) {
        return $this->generarInterfaceToTenant($this->columnasExcel, 'trabajadores');  
    }

    /**
     * Toma una interfaz en Excel y la almacena en la tabla de trabajadores.
     *
     * @param Request $request
     * @return
     * @throws \Exception
     */
    public function cargarTrabajadores(Request $request){
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
                    'message' => 'Errores al guardar los Trabajadores',
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
                    'message' => 'Errores al guardar los Trabajadores',
                    'errors'  => ['El archivo subido no tiene datos.']
                ], 400);
            }

            $arrErrores = [];
            $arrResultado = [];
            $arrExistePais = [];
            $arrExisteMunicipio = [];
            $arrExisteEmpleador = [];
            $arrExisteTrabajador = [];
            $arrTrabajadorProceso = [];
            $arrExisteDepartamento = [];

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

            $arrExisteTipoContrato = [];
            ParametrosTipoContrato::where('estado', 'ACTIVO')->get()
                ->groupBy('ntc_codigo')
                ->map(function ($item) use (&$arrExisteTipoContrato) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrExisteTipoContrato[$vigente['registro']->ntc_codigo] = $vigente['registro'];
                    }
                });

            $arrExisteTipoTrabajador = [];
            ParametrosTipoTrabajador::where('estado', 'ACTIVO')->get()
                ->groupBy('ntt_codigo')
                ->map(function ($item) use (&$arrExisteTipoTrabajador) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrExisteTipoTrabajador[$vigente['registro']->ntt_codigo] = $vigente['registro'];
                    }
                });


            $arrExisteSubtipoTrabajador = [];
            ParametrosSubtipoTrabajador::where('estado', 'ACTIVO')->get()
                ->groupBy('nst_codigo')
                ->map(function ($item) use (&$arrExisteSubtipoTrabajador) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrExisteSubtipoTrabajador[$vigente['registro']->nst_codigo] = $vigente['registro'];
                    }
                });

            foreach ($data as $fila => $columnas) {
                $columnas['fecha_ingreso'] = \Carbon\Carbon::parse($columnas['fecha_ingreso'])->format('Y-m-d');
                $columnas['fecha_retiro'] = \Carbon\Carbon::parse($columnas['fecha_retiro'])->format('Y-m-d');

                $Acolumnas = $columnas;
                $columnas = (object) $columnas;

                $arrTrabajador = [];
                $arrFaltantes = $this->checkFields($Acolumnas, [
                    'codigo_tipo_documento',
                    'identificacion',
                    'primer_apellido',
                    'primer_nombre',
                    'sueldo',
                    'codigo_tipo_contrato',
                    'codigo_tipo_trabajador',
                    'codigo_subtipo_trabajador',
                    'fecha_ingreso',
                    'salario_integral_si_no'
                ], $fila);

                if(!empty($arrFaltantes)){
                    $vacio = $this->revisarArregloVacio($Acolumnas);
                    if($vacio){
                        $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes, $fila);
                    } else {
                        unset($data[$fila]);
                    }
                } else {
                    //Trabajador
                    $arrTrabajador['tra_id'] = 0;
                    $arrTrabajador['tra_identificacion'] = (string)$columnas->identificacion;

                    if (!preg_match("/^[1-9]/", $arrTrabajador['tra_identificacion']) && 
                        ($columnas->codigo_tipo_documento == '13')
                    ) {
                        $arrErrores = $this->adicionarError($arrErrores, ['El formato del campo Identificación del Empleador es inválido.'], $fila);
                    }

                    if ($columnas->codigo_tipo_documento == '31') {
                        $arrErrores = $this->adicionarError($arrErrores, ['No se puede Seleccionar el Tipo Documento con Código [31].'], $fila);
                    }

                    if (array_key_exists($columnas->codigo_tipo_documento, $arrExisteTipoDocumento)) {
                        $objExisteTipoDocumento = $arrExisteTipoDocumento[$columnas->codigo_tipo_documento];
                        $arrTrabajador['tdo_id'] = $objExisteTipoDocumento->tdo_id;
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ['El Código del Tipo de Documento [' . $columnas->codigo_tipo_documento . '], ya no está vigente, no aplica para Documento Nómina Electrónica, se encuentra INACTIVO o no existe.'], $fila);
                    }

                    $arrTrabajador['emp_id'] = null;
                    if (array_key_exists($columnas->nit_empleador, $arrExisteEmpleador)){
                        $objExisteEmpleador = $arrExisteEmpleador[$columnas->nit_empleador];
                    } else {
                        $objExisteEmpleador = ConfiguracionEmpleador::where('estado', 'ACTIVO')
                            ->where('emp_identificacion', $columnas->nit_empleador)
                            ->first();
                        $arrExisteEmpleador[$columnas->nit_empleador] = $objExisteEmpleador;
                    }

                    // Si el usuario autenticado tiene base de datos asignada
                    if(!empty($objUser->bdd_id_rg) && $objExisteEmpleador && $objUser->bdd_id_rg != $objExisteEmpleador->bdd_id_rg) {
                        $arrErrores = $this->adicionarError($arrErrores, ['Empleador ' . $columnas->nit_empleador . ' no válido.'], $fila);
                    }

                    if (!empty($objExisteEmpleador)) {
                        $arrTrabajador['emp_id'] = $objExisteEmpleador->emp_id;

                        if (array_key_exists($columnas->identificacion, $arrExisteTrabajador)){
                            $objExisteTrabajador = $arrExisteTrabajador[$columnas->identificacion];
                            $arrTrabajador['tra_id'] = $objExisteTrabajador->tra_id;
                        } else {
                            $objExisteTrabajador = $this->className::where('tra_identificacion', $columnas->identificacion)
                                ->where('emp_id', $objExisteEmpleador->emp_id)
                                ->first();

                            if ($objExisteTrabajador) {
                                $arrExisteTrabajador[$columnas->identificacion] = $objExisteTrabajador;
                                $arrTrabajador['tra_id'] = $objExisteTrabajador->tra_id;
                            }
                        }

                        $llaveTrabajador = $columnas->nit_empleador . '|' . $columnas->identificacion;

                        if (array_key_exists($llaveTrabajador, $arrTrabajadorProceso)) {
                            $arrErrores = $this->adicionarError($arrErrores, ['EL Nit del Trabajador '. $columnas->identificacion . ' ya existe en otras filas.'], $fila);
                        } else {
                            $arrTrabajadorProceso[$llaveTrabajador] = true;
                        }
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ['El Nit del Empleador ['. $columnas->nit_empleador .'] no existe o se encuentra INACTIVO.'], $fila);
                    }

                    //DOMICILIO
                    $arrTrabajador['pai_id'] = null;
                    $arrTrabajador['dep_id'] = null;
                    $arrTrabajador['mun_id'] = null;

                    // Válida que exista y este vigente el país cargado
                    if  (isset($columnas->codigo_pais) && $columnas->codigo_pais != '') {
                        if (array_key_exists($columnas->codigo_pais, $arrExistePais)) {
                            $objExistePais = $arrExistePais[$columnas->codigo_pais];
                            $arrTrabajador['pai_id'] = $objExistePais->pai_id;
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
                                $arrTrabajador['pai_id'] = $arrExistePais[$columnas->codigo_pais]->pai_id;
                            }
                        }
                    }

                    // Válida que exista y este vigente el departamento cargado
                    if (isset($columnas->codigo_departamento) && $columnas->codigo_departamento != '') {
                        if (array_key_exists($columnas->codigo_departamento, $arrExisteDepartamento)) {
                            $objExisteDepartamento = $arrExisteDepartamento[$columnas->codigo_departamento];
                            $arrTrabajador['dep_id'] = $objExisteDepartamento->dep_id;
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
                                    $arrTrabajador['dep_id'] = $arrExisteDepartamento[$columnas->codigo_departamento]->dep_id;
                                }
                            }
                        }
                    }

                    // Válida que exista y este vigente el municipio cargado
                    if (isset($columnas->codigo_ciudad_municipio) && $columnas->codigo_ciudad_municipio != '') {
                        if (array_key_exists($columnas->codigo_ciudad_municipio, $arrExisteMunicipio)) {
                            $objExisteMunicipio = $arrExisteMunicipio[$columnas->codigo_ciudad_municipio];
                            $arrTrabajador['mun_id'] = $objExisteMunicipio->mun_id;
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
                                    $arrTrabajador['mun_id'] = $arrExisteMunicipio[$columnas->codigo_ciudad_municipio]->mun_id;
                                }
                            }
                        }
                    }

                    if (array_key_exists($columnas->codigo_tipo_contrato, $arrExisteTipoContrato)) {
                        $objExisteTipoContrato = $arrExisteTipoContrato[$columnas->codigo_tipo_contrato];
                        $arrTrabajador['ntc_id'] = $objExisteTipoContrato->ntc_id;
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ['El Código del Tipo de Contrato [' . $columnas->codigo_tipo_contrato . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                    }

                    if (array_key_exists($columnas->codigo_tipo_trabajador, $arrExisteTipoTrabajador)) {
                        $objExisteTipoTrabajador = $arrExisteTipoTrabajador[$columnas->codigo_tipo_trabajador];
                        $arrTrabajador['ntt_id'] = $objExisteTipoTrabajador->ntt_id;
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ['El Código del Tipo de Trabajador [' . $columnas->codigo_tipo_trabajador . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                    }

                    if (array_key_exists($columnas->codigo_subtipo_trabajador, $arrExisteSubtipoTrabajador)) {
                        $objExisteSubtipoTrabajador = $arrExisteSubtipoTrabajador[$columnas->codigo_subtipo_trabajador];
                        $arrTrabajador['nst_id'] = $objExisteSubtipoTrabajador->nst_id;
                    } else {
                        $arrErrores = $this->adicionarError($arrErrores, ['El Código del Subtipo de Trabajador [' . $columnas->codigo_subtipo_trabajador . '], ya no está vigente, se encuentra INACTIVO o no existe.'], $fila);
                    }

                    if (property_exists($columnas, 'tabajador_de_alto_riesgo_pension_si_no') && isset($columnas->tabajador_de_alto_riesgo_pension_si_no) && !empty($columnas->tabajador_de_alto_riesgo_pension_si_no)) {
                        if ($columnas->tabajador_de_alto_riesgo_pension_si_no !== 'SI' && $columnas->tabajador_de_alto_riesgo_pension_si_no !== 'NO' && $columnas->tabajador_de_alto_riesgo_pension_si_no !== "") {
                            $arrErrores = $this->adicionarError($arrErrores, ['El campo Trabajador de Alto Riesgo debe contener SI o NO como valor.'], $fila);
                        }
                    }

                    if (property_exists($columnas, 'salario_integral_si_no') && isset($columnas->salario_integral_si_no)) {
                        if ($columnas->salario_integral_si_no !== 'SI' && $columnas->salario_integral_si_no !== 'NO') {
                            $arrErrores = $this->adicionarError($arrErrores, ['El campo Salario Integral debe contener SI o NO como valor.'], $fila);
                        }
                    }

                    if (property_exists($columnas, 'tipo_de_cuenta') && isset($columnas->tipo_de_cuenta) && !empty($columnas->tipo_de_cuenta)) {
                        if ($columnas->tipo_de_cuenta !== 'AHORRO' && $columnas->tipo_de_cuenta !== 'CORRIENTE') {
                            $arrErrores = $this->adicionarError($arrErrores, ['El campo Tipo de Cuenta debe contener AHORRO o CORRIENTE como valor.'], $fila);
                        }
                    }

                    $arrTrabajador['tra_primer_apellido']  = $this->sanitizarStrings($columnas->primer_apellido);
                    $arrTrabajador['tra_segundo_apellido'] = $this->sanitizarStrings($columnas->segundo_apellido);
                    $arrTrabajador['tra_primer_nombre']    = $this->sanitizarStrings($columnas->primer_nombre);
                    $arrTrabajador['tra_otros_nombres']    = $this->sanitizarStrings($columnas->otros_nombres);
                    $arrTrabajador['tra_direccion']        = $this->sanitizarStrings($columnas->direccion);
                    $arrTrabajador['tra_telefono']         = $this->sanitizarStrings($columnas->telefono);
                    $arrTrabajador['tra_codigo']           = $this->sanitizarStrings($columnas->codigo_trabajador);
                    $arrTrabajador['tra_fecha_ingreso']    = $this->sanitizarStrings($columnas->fecha_ingreso);
                    $arrTrabajador['tra_fecha_retiro']     = $this->sanitizarStrings($columnas->fecha_retiro);
                    $arrTrabajador['tra_sueldo']           = $this->sanitizarStrings($columnas->sueldo);
                    $arrTrabajador['tra_alto_riesgo']      = $this->sanitizarStrings($columnas->tabajador_de_alto_riesgo_pension_si_no);
                    $arrTrabajador['tra_salario_integral'] = $this->sanitizarStrings($columnas->salario_integral_si_no);
                    $arrTrabajador['tra_entidad_bancaria'] = $this->sanitizarStrings($columnas->nombre_entidad_bancaria);
                    $arrTrabajador['tra_tipo_cuenta']      = $this->sanitizarStrings($columnas->tipo_de_cuenta);
                    $arrTrabajador['tra_numero_cuenta']    = $this->sanitizarStrings($columnas->numero_de_cuenta);

                    if(count($arrErrores) == 0){
                        $objValidator = Validator::make($arrTrabajador, $this->className::$rules);
                        if(count($objValidator->errors()->all()) > 0) {
                            $arrErrores = $this->adicionarError($arrErrores, $objValidator->errors()->all(), $fila);
                        } else {
                            $arrResultado[] = $arrTrabajador;
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
                    'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_TRA,
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
                    'message' => 'Errores al guardar los Trabajadores',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            } else {
                $bloque_trabajador = [];
                foreach ($arrResultado as $trabajador) {
                    $data = [
                        'tra_id'                => $trabajador['tra_id'],
                        'emp_id'                => $trabajador['emp_id'],
                        'tdo_id'                => $trabajador['tdo_id'],
                        'tra_identificacion'    => $trabajador['tra_identificacion'],
                        'tra_primer_apellido'   => !empty($trabajador['tra_primer_apellido']) ? $this->sanitizarStrings($trabajador['tra_primer_apellido']): null,
                        'tra_segundo_apellido'  => !empty($trabajador['tra_segundo_apellido']) ? $this->sanitizarStrings($trabajador['tra_segundo_apellido']): null,
                        'tra_primer_nombre'     => !empty($trabajador['tra_primer_nombre']) ? $this->sanitizarStrings($trabajador['tra_primer_nombre']): null,
                        'tra_otros_nombres'     => !empty($trabajador['tra_otros_nombres']) ? $this->sanitizarStrings($trabajador['tra_otros_nombres']): null,
                        'pai_id'                => $trabajador['pai_id'],
                        'dep_id'                => $trabajador['dep_id'],
                        'mun_id'                => $trabajador['mun_id'],
                        'tra_direccion'         => !empty($trabajador['tra_direccion']) ? $this->sanitizarStrings($trabajador['tra_direccion']): null,
                        'tra_telefono'          => !empty($trabajador['tra_telefono']) ? $this->sanitizarStrings($trabajador['tra_telefono']): null,
                        'tra_codigo'            => !empty($trabajador['tra_codigo']) ? $this->sanitizarStrings($trabajador['tra_codigo']): null,
                        'tra_fecha_ingreso'     => !empty($trabajador['tra_fecha_ingreso']) ? $trabajador['tra_fecha_ingreso']: null,
                        'tra_fecha_retiro'      => !empty($trabajador['tra_fecha_retiro']) ? $trabajador['tra_fecha_retiro']: null,
                        'tra_sueldo'            => !empty($trabajador['tra_sueldo']) ? $trabajador['tra_sueldo']: null,
                        'ntc_id'                => $trabajador['ntc_id'],
                        'ntt_id'                => $trabajador['ntt_id'],
                        'nst_id'                => $trabajador['nst_id'],
                        'tra_alto_riesgo'       => !empty($trabajador['tra_alto_riesgo']) ? $this->sanitizarStrings($trabajador['tra_alto_riesgo']): null,
                        'tra_salario_integral'  => !empty($trabajador['tra_salario_integral']) ? $this->sanitizarStrings($trabajador['tra_salario_integral']): null,
                        'tra_entidad_bancaria'  => !empty($trabajador['tra_entidad_bancaria']) ? $this->sanitizarStrings($trabajador['tra_entidad_bancaria']): null,
                        'tra_tipo_cuenta'       => !empty($trabajador['tra_tipo_cuenta']) ? $this->sanitizarStrings($trabajador['tra_tipo_cuenta']): null,
                        'tra_numero_cuenta'     => !empty($trabajador['tra_numero_cuenta']) ? $this->sanitizarStrings($trabajador['tra_numero_cuenta']): null
                    ];

                    array_push($bloque_trabajador, $data);
                }

                if (!empty($bloque_trabajador)) {
                    $bloques = array_chunk($bloque_trabajador, 100);
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
                                'pjj_tipo' => ProcesarCargaParametricaCommand::$TYPE_TRA,
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
                'message' => 'Errores al guardar los Trabajadores',
                'errors'  => ['No se ha subido ningún archivo.']
            ], 400);
        }
    }

    /**
     * Obtiene la lista de errores de procesamiento de cargas masivas de trabajadores.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListaErroresTrabajador(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_TRA);
    }

    /**
     * Retorna una lista en Excel de errores de cargue de trabajadores.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function descargarListaErroresTrabajador(){
        return $this->getListaErrores(ProcesarCargaParametricaCommand::$TYPE_TRA, true, 'carga_trabajador_log_errores');
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
     * Efectua un proceso de busqueda en la parametrica de trabajadores.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function busqueda(Request $request) {
        $columnas = [
            'tra_id',
            'emp_id',
            'tdo_id',
            'tra_identificacion',
            'tra_primer_apellido',
            'tra_segundo_apellido',
            'tra_primer_nombre',
            'tra_otros_nombres',
            \DB::raw('CONCAT(COALESCE(tra_primer_nombre, ""), " ", COALESCE(tra_otros_nombres, ""), " ", COALESCE(tra_primer_apellido, ""), " ", COALESCE(tra_segundo_apellido, "")) as nombre_completo'),
            'pai_id',
            'dep_id',
            'mun_id',
            'tra_direccion',
            'tra_telefono',
            'tra_codigo',
            'tra_fecha_ingreso',
            'tra_fecha_retiro',
            'tra_sueldo',
            'ntc_id',
            'ntt_id',
            'nst_id',
            'tra_alto_riesgo',
            'tra_salario_integral',
            'tra_entidad_bancaria',
            'tra_tipo_cuenta',
            'tra_numero_cuenta',
            'estado'
        ];

        $incluir = [
            'getUsuarioCreacion',
            'getEmpleador',
            'getTipoDocumento',
            'getParametrosPais',
            'getParametrosDepartamento',
            'getParametrosMunicipio',
            'getParametrosTipoContrato',
            'getParametrosTipoTrabajador',
            'getParametrosSubtipoTrabajador'
        ];

        $empleador = ConfiguracionEmpleador::where('estado', 'ACTIVO')
            ->where('emp_identificacion', $request->valorEmpleador)
            ->first();
        if (!is_null($empleador)) {
            $precondiciones = [
                ['emp_id', '=', $empleador->emp_id]
            ];
            return $this->procesadorBusqueda($request, $columnas, $incluir, $precondiciones);
        } else {
            return response()->json([
                'data' => [],
            ], Response::HTTP_OK);
        }
    }

    /**
     * Obtiene la información de los trabajadores dado un empleador y el término a buscar.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function searchTrabajadores(Request $request) {
        $trabajadores = $this->className::where('estado', 'ACTIVO');
        $trabajadores->select(
            [
                'tra_id',
                'tra_identificacion',
                'tra_primer_apellido',
                'tra_segundo_apellido',
                'tra_primer_nombre',
                'tra_otros_nombres',
                \DB::raw('CONCAT(COALESCE(tra_primer_nombre, ""), " ", COALESCE(tra_otros_nombres, ""), " ", COALESCE(tra_primer_apellido, ""), " ", COALESCE(tra_segundo_apellido, "")) as nombre_completo')
            ]);

        if ($request->has('emp_id') && !empty($request->emp_id)) {
            $trabajadores->where('emp_id', $request->emp_id);
        }

        $trabajadores->where(function ($query) use ($request) {
            $query->where('tra_primer_apellido', 'like', '%' . $request->buscar . '%')
                ->orWhere('tra_segundo_apellido', 'like', '%' . $request->buscar . '%')
                ->orWhere('tra_primer_nombre', 'like', '%' . $request->buscar . '%')
                ->orWhere('tra_otros_nombres', 'like', '%' . $request->buscar . '%')
                ->orWhere('tra_identificacion', 'like', '%' . $request->buscar . '%')
                ->orWhereRaw("REPLACE(CONCAT(COALESCE(tra_primer_nombre, ''), ' ', COALESCE(tra_otros_nombres, ''), ' ', COALESCE(tra_primer_apellido, ''), ' ', COALESCE(tra_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $request->buscar . '%']);
        });

        return response()->json([
            'data' => $trabajadores->get()
        ], 200);
    }

    /**
     * Recibe una petición desde el microservicio DI para creación/actualización de trabajadores.
     * 
     * En el request debe llegar un objeto llamado trabajador con los datos del empleado a procesar
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminFromDI(Request $request) {
        $errorsAll = [];
        if($request->has('trabajador') && is_array($request->trabajador)) {
            $errors = [];

            if(!array_key_exists('emp_identificacion', $request->trabajador) || (array_key_exists('emp_identificacion', $request->trabajador) && empty($request->trabajador['emp_identificacion'])))
                $errors[] = 'Trabajador [' . $request->trabajador['tra_identificacion'] . ']: No se ha proporcionado el Empleador para procesar el trabajador';

            if(!array_key_exists('tra_identificacion', $request->trabajador) || (array_key_exists('tra_identificacion', $request->trabajador) && empty($request->trabajador['tra_identificacion'])))
                $errors[] = 'Trabajador [' . $request->trabajador['tra_identificacion'] . ']: No se ha proporcionado el número de identificación del trabajador';

            if (empty($errors)) {
                $empleador = ConfiguracionEmpleador::select(['emp_id', 'emp_identificacion'])
                    ->where('emp_identificacion', $request->trabajador['emp_identificacion'])
                    ->where('estado', 'ACTIVO')
                    ->first();

                if (!$empleador) {
                    $errors[] = 'Trabajador [' . $request->trabajador['tra_identificacion'] . ']: ' . "EL empleador {$request->trabajador['emp_identificacion']} no existe o esta inactivo.";
                } else {
                    $trabajador = ConfiguracionTrabajador::select(['tra_id', 'tra_identificacion'])
                        ->where('emp_id', $empleador->emp_id)
                        ->where('tra_identificacion', $request->trabajador['tra_identificacion'])
                        ->where('estado', 'ACTIVO')
                        ->first();

                    $newRequest = new Request($request->trabajador);
                    if (!$trabajador) {
                        $procesamiento = $this->store($newRequest);
                    } else {
                        $procesamiento = $this->updateCompuesto($newRequest, $empleador->emp_identificacion, $trabajador->tra_identificacion);
                    }

                    $resultado = json_decode($procesamiento->getContent(), true);
                    if(array_key_exists('errors', $resultado) && !empty($resultado['errors'])) {
                        foreach($resultado['errors'] as $rptaError) {
                            $errors[] = 'Trabajador [' . $request->trabajador['tra_identificacion'] . ']: ' . $rptaError;
                        }
                    }
                }
            }

            if (!empty($errors)) {
                $errorsAll = array_merge($errorsAll, $errors);
            }
        } else {
            $errorsAll[] = 'Se espera un array para poder procesar el trabajador';
        }

        if(empty($errorsAll)) {
            return response()->json([
                'message' => 'Información de trabajador procesada de manera correcta'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Errores al procesar el trabajador',
                'errors' => $errorsAll
            ], 400);
        }
    }
}
