<?php

namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacion;

class CentroOperacionController extends OpenTenantController {
    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Centro Operacion';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Centros Operacion';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var CentroOperacion
     */
    public $className = CentroOperacion::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Centro Operacion';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Centro Operacion';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Centro Operacion';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Centro Operacion';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Centro Operacion [%s] no existe';

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
    public $mensajeObjectDisabled = 'El Id del Centro Operacion [%s] esta inactivo';

    /**
     * Campo de control para filtrado de informacion al cambiar el estado.
     * 
     * @var String
     */
    public $nombreDatoCambiarEstado = 'cop_ids';

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
    public $nombreArchivo = 'centro_operacion';

    /**
     * Campo de control para filtrado de información.
     *
     * @var String
     */
    public $nombreCampoIdentificacion = 'cop_id';

    /**
     * Autoincremental del registro que se está modificando.
     * 
     * @var String
     */
    public $idCop = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth','jwt.refresh']);

        // Listar Configuracion Centro Operacion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosOperacion,ConfiguracionCentrosOperacionVer,ConfiguracionCentrosOperacionNuevo,ConfiguracionCentrosOperacionEditar,ConfiguracionCentrosOperacionCambiarEstado'
        ])->only([
            'getListaCentroOperacion'
        ]);

        // Ver Configuracion Centro Operacion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosOperacionVer,ConfiguracionCentrosOperacionEditar'
        ])->only([
            'show'
        ]);

        // Crear Configuraricion Centro Operacion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosOperacionNuevo'
        ])->only([
            'store'
        ]);

        // Editar Configuracion Centro Operacion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosOperacionEditar'
        ])->only([
            'update'
        ]);

        // Cambiar Estado Configuracion Centro Operacion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCentrosOperacionCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);
    }

    /**
     * Configura las reglas para poder actualizar o crear la información de los Centros Operacion.
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
    public function getListaCentroOperacion(Request $request): JsonResponse {
        $condiciones = [];

        $columnas = [
            'pry_centros_operaciones.cop_id',
            'pry_centros_operaciones.cop_descripcion',
            'pry_centros_operaciones.usuario_creacion',
            'pry_centros_operaciones.fecha_creacion',
            'pry_centros_operaciones.fecha_modificacion',
            'pry_centros_operaciones.estado'
        ];
        $relaciones = [];
        $whereHasConditions = [];
        $exportacion = [];
        $exportacion['titulo'] = $this->nombreArchivo;

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Obtiene el registro del Centros Operación.
     *
     * @param string $id Identificador del Centro Operación 
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse {
        $relaciones = [
            'getUsuarioCreacion',
        ];

        return $this->procesadorShow($id, $relaciones);
    }

    /**
     * Permite crear un Centro Operación.
     *
     * @param Request $request Parámetros de la solicitud
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse {
        $this->user   = auth()->user();
        $this->errors = [];
        $data = $request->all();

        $validador = Validator::make($data, $this->getRules('store'));

        $this->errors = $validador->errors()->all();

        if (!$validador->fails()) {
            $existe = $this->className::where('cop_id', $request->cop_id)->first();

            if ($existe) { 
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} [{$request->cop_id}] ya existe."]);
            } else {
                $data['estado']           = 'ACTIVO';
                $data['usuario_creacion'] = $this->user->usu_id;
                $obj = $this->className::create($data);

                if($obj) {
                    return response()->json([
                        'success' => true,
                        'gtr_id'  => $obj->cop_id
                    ], JsonResponse::HTTP_CREATED);
                } else {
                    $this->errors = $this->adicionarError($this->errors, ["No fue posible crear el {$this->nombre} con el código [{$request->cop_id}]"]);
                }
            }
        }

        return response()->json([
            'message' => $this->mensajeErroresCreacion,
            'errors'  => $this->errors
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Permite actualizar un Centro Operación.
     *
     * @param Request  $request Parámetros de la solicitud
     * @param int $cop_id Id del Centro Operación que va ser actualizado
     * @return JsonResponse
     */
    public function update(Request $request, int $cop_id): JsonResponse {
        $data = $request->all();
        $this->errors = [];
        $this->idCop = $cop_id;

        $validador = Validator::make($data, $this->getRules('update'));

        $this->errors = $validador->errors()->all();

        $objConsulta = $this->className::where('cop_id', $cop_id)->first();
        
        if (!$objConsulta)
            $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con el ID [{$this->idCop}] no existe"]);

        if (empty($this->errors)) {

            $obj = $objConsulta->update($data);

            if($obj) {
                return response()->json([
                    'success' => true
                ], JsonResponse::HTTP_OK);
            }
            else {
                $this->errors = $this->adicionarError($this->errors, ["No fue posible actualizar el {$this->nombre} con el código [{$request->cop_id}]"]);
            }
        }

        return response()->json([
            'message' => $this->mensajeErroresModificacion,
            'errors'  => $this->errors
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Cambia el estado de uno o más registros seleccionados del Centro Operación.
     *
     * @param Request $request Parámetros de la solicitud
     * @return JsonResponse
     */
    public function cambiarEstado(Request $request): JsonResponse {
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Obtiene el id y descripción de los registros en estado ACTIVO.
     * 
     * @return JsonResponse
     */
    public function searchCentrosOperacion(): JsonResponse {
        $consulta = CentroOperacion::select(['cop_id','cop_descripcion'])
            ->where('estado', 'ACTIVO')
            ->get();

        return response()->json([
            'data' => $consulta
        ], JsonResponse::HTTP_OK);
    }
}
