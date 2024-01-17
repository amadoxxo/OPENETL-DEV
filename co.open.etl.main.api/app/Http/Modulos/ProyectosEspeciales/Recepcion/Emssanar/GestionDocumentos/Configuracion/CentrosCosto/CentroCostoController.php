<?php

namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto\CentroCosto;

class CentroCostoController extends OpenTenantController {
    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Centro Costo';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Centros Costo';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var CentroCosto
     */
    public $className = CentroCosto::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Centro Costo';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Centro Costo';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Centro Costo';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Centro Costo';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Centro Costo [%s] no existe';

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
    public $mensajeObjectDisabled = 'El Id del Centro Costo [%s] esta inactivo';

    /**
     * Campo de control para filtrado de informacion al cambiar el estado.
     * 
     * @var String
     */
    public $nombreDatoCambiarEstado = 'cco_codigos';

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
    public $nombreArchivo = 'centro_costo';

    /**
     * Campo de control para filtrado de información.
     *
     * @var String
     */
    public $nombreCampoIdentificacion = 'cco_codigo';

    /**
     * Autoincremental del registro que se está modificando.
     * 
     * @var String
     */
    public $idCco = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth','jwt.refresh']);

        // Listar Configuracion Centros Costo
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosCosto,ConfiguracionCentrosCostoVer,ConfiguracionCentrosCostoNuevo,ConfiguracionCentrosCostoEditar,ConfiguracionCentrosCostoCambiarEstado'
        ])->only([
            'getListaCentrosCosto'
        ]);

        // Ver Configuracion Centros Costo
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosCostoVer,ConfiguracionCentrosCostoEditar'
        ])->only([
            'show'
        ]);

        // Crear Configuraricion Centros Costo
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosCostoNuevo'
        ])->only([
            'store'
        ]);

        // Editar Configuracion Centros Costo
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosCostoEditar'
        ])->only([
            'update'
        ]);

        // Cambiar Estado Configuracion Centros Costo
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosCostoCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);
    }

    /**
     * Configura las reglas para poder actualizar o crear la información de los Centros Costo de los documentos electrónicos.
     *
     * @param  string $accion Acción a ejecutar si es creación o actualización
     * @return array
     */
    private function getRules(string $accion): array {
        if($accion === 'store') {
            $rules = array_merge($this->className::$rules);
        } else {
            $rules = array_merge($this->className::$rulesUpdate);
        }

        return $rules;
    }

    /**
     * Devuelve una lista paginada de registros.
     *
     * @param Request $request Parámetros de la solicitud
     * @return JsonResponse
     * @throws \Exception
     */
    public function getListaCentrosCosto(Request $request):JsonResponse {
        $user = auth()->user();

        $condiciones = [];

        $columnas = [
            'pry_centros_costo.cco_id',
            'pry_centros_costo.cco_codigo',
            'pry_centros_costo.cco_descripcion',
            'pry_centros_costo.usuario_creacion',
            'pry_centros_costo.fecha_creacion',
            'pry_centros_costo.fecha_modificacion',
            'pry_centros_costo.estado'
        ];
        $relaciones = [];
        $whereHasConditions = [];
        $exportacion = [];
        $exportacion['titulo'] = $this->nombreArchivo;

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Obtiene el registro del Centros Costo del documento electrónico estándar especificado.
     *
     * @param $id Identificador del Centros Costo del documento electrónico estándar
     * @return JsonResponse
     */
    public function show(string $codigo):JsonResponse {
        $relaciones = [
            'getUsuarioCreacion',
        ];

        return $this->procesadorShow($codigo, $relaciones);
    }

    /**
     * Permite crear un Centro Costo del documento electrónico estándar o personalizado.
     *
     * @param Request $request Parámetros de la solicitud
     * @return JsonResponse
     */
    public function store(Request $request):JsonResponse {
        $this->user   = auth()->user();
        $this->errors = [];
        $data = $request->all();

        $validador = Validator::make($data, $this->getRules('store'));

        $this->errors = $validador->errors()->all();

        if (!$validador->fails()) {
            $existe = $this->className::where('cco_codigo', $request->cco_codigo)->first();

            if ($existe) { 
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} [{$request->cco_codigo}] ya existe."]);
            } else {
                $data['estado']           = 'ACTIVO';
                $data['usuario_creacion'] = $this->user->usu_id;
                $obj = $this->className::create($data);

                if($obj) {
                    return response()->json([
                        'success' => true,
                        'gtr_id'  => $obj->cco_id
                    ], JsonResponse::HTTP_CREATED);
                } else {
                    $this->errors = $this->adicionarError($this->errors, ["No fue posible crear el {$this->nombre} con el código [{$request->cco_id}]"]);
                }
            }
        }

        return response()->json([
            'message' => $this->mensajeErroresCreacion,
            'errors'  => $this->errors
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Permite actualizar un Centro Costo del documento electrónico estándar o personalizado.
     *
     * @param Request  $request Parámetros de la solicitud
     * @param int $cco_id Id del Centros Costo que va ser actualizado
     * @return JsonResponse
     */
    public function update(Request $request, int $cco_id):JsonResponse {
        $data = $request->all();
        $this->errors = [];
        $this->idCco = $cco_id;

        $validador = Validator::make($data, $this->getRules('update'));

        $this->errors = $validador->errors()->all();

        $objConsulta = $this->className::where('cco_id', $cco_id)->first();
        
        if (!$objConsulta)
            $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con el ID [{$this->idCco}] no existe"]);

        if (empty($this->errors)) {
            $existe = $this->className::where('cco_id', '!=', $cco_id)
                ->where('cco_codigo', $request->cco_codigo)
                ->first();

            if ($existe) {
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} [{$request->cco_codigo}] ya existe"]);
            } else {
                $obj = $objConsulta->update($data);

                if($obj) {
                    return response()->json([
                        'success' => true
                    ], JsonResponse::HTTP_OK);
                }
                else {
                    $this->errors = $this->adicionarError($this->errors, ["No fue posible actualizar el {$this->nombre} con el código [{$request->cco_id}]"]);
                }
            }
        }

        return response()->json([
            'message' => $this->mensajeErroresModificacion,
            'errors'  => $this->errors
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Cambia el estado de uno o más registros seleccionados del Centros Costo documento electrónico estándar.
     *
     * @param Request $request Parámetros de la solicitud
     * @return JsonResponse
     */
    public function cambiarEstado(Request $request):JsonResponse {
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Obtiene el id, codigo y descripción de los registros en estado ACTIVO.
     * 
     * @return JsonResponse
     */
    public function searchCentrosCosto(): JsonResponse {
        $consulta = CentroCosto::select([
            'cco_id',
            'cco_codigo',
            'cco_descripcion'
            ])
            ->where('estado', 'ACTIVO')
            ->get();

        return response()->json([
            'data' => $consulta
        ], JsonResponse::HTTP_OK);
    }
}
