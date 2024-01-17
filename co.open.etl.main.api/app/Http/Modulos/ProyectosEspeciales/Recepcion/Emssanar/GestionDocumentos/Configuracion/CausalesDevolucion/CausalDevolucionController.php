<?php

namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion\CausalDevolucion;

class CausalDevolucionController extends OpenTenantController {
    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Causal Devolucion';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Causales Devolucion';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var CausalDevolucion
     */
    public $className = CausalDevolucion::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Causal Devolucion';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Causal Devolucion';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Causal Devolucion';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Causal Devolucion';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Causal Devolucion [%s] no existe';

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
    public $mensajeObjectDisabled = 'El Id del Causal Devolucion [%s] esta inactivo';

    /**
     * Campo de control para filtrado de informacion al cambiar el estado.
     * 
     * @var String
     */
    public $nombreDatoCambiarEstado = 'cde_ids';

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
    public $nombreArchivo = 'causal_devolucion';

    /**
     * Campo de control para filtrado de información.
     *
     * @var String
     */
    public $nombreCampoIdentificacion = 'cde_id';

    /**
     * Autoincremental del registro que se está modificando.
     * 
     * @var String
     */
    public $idCde = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth','jwt.refresh']);

        // Listar Configuracion Causal Devolucion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCausalesDevolucion,ConfiguracionCausalesDevolucionVer,ConfiguracionCausalesDevolucionNuevo,ConfiguracionCausalesDevolucionEditar,ConfiguracionCausalesDevolucionCambiarEstado'
        ])->only([
            'getListaCausalDevolucion'
        ]);

        // Ver Configuracion Causal Devolucion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCausalesDevolucionVer,ConfiguracionCausalesDevolucionEditar'
        ])->only([
            'show'
        ]);

        // Crear Configuraricion Causal Devolucion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCausalesDevolucionNuevo'
        ])->only([
            'store'
        ]);

        // Editar Configuracion Causal Devolucion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCausalesDevolucionEditar'
        ])->only([
            'update'
        ]);

        // Cambiar Estado Configuracion Causal Devolucion
        $this->middleware([
            'VerificaMetodosRol:ConfiguracionCausalesDevolucionCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);
    }

    /**
     * Configura las reglas para poder actualizar o crear la información de los Causales Devolucion de los documentos electrónicos.
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
    public function getListaCausalDevolucion(Request $request):JsonResponse {
        $user = auth()->user();

        $condiciones = [];

        $columnas = [
            'pry_causales_devolucion.cde_id',
            'pry_causales_devolucion.cde_descripcion',
            'pry_causales_devolucion.usuario_creacion',
            'pry_causales_devolucion.fecha_creacion',
            'pry_causales_devolucion.fecha_modificacion',
            'pry_causales_devolucion.estado'
        ];
        $relaciones = [];
        $whereHasConditions = [];
        $exportacion = [];
        $exportacion['titulo'] = $this->nombreArchivo;

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Obtiene el registro del Causales Devolucion del documento electrónico estándar especificado.
     *
     * @param $id Identificador del Causales Devolucion del documento electrónico estándar
     * @return JsonResponse
     */
    public function show(string $id):JsonResponse {
        $relaciones = [
            'getUsuarioCreacion',
        ];

        return $this->procesadorShow($id, $relaciones);
    }

    /**
     * Permite crear un Causal Devolucion del documento electrónico estándar o personalizado.
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
            $existe = $this->className::where('cde_id', $request->cde_id)->first();

            if ($existe) { 
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} [{$request->cde_id}] ya existe."]);
            } else {
                $data['estado']           = 'ACTIVO';
                $data['usuario_creacion'] = $this->user->usu_id;
                $obj = $this->className::create($data);

                if($obj) {
                    return response()->json([
                        'success' => true,
                        'gtr_id'  => $obj->cde_id
                    ], JsonResponse::HTTP_CREATED);
                } else {
                    $this->errors = $this->adicionarError($this->errors, ["No fue posible crear el {$this->nombre} con el código [{$request->cde_id}]"]);
                }
            }
        }

        return response()->json([
            'message' => $this->mensajeErroresCreacion,
            'errors'  => $this->errors
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Permite actualizar un Causal Devolucion del documento electrónico estándar o personalizado.
     *
     * @param Request  $request Parámetros de la solicitud
     * @param int $cde_id Id del Causal Devolucion que va ser actualizado
     * @return JsonResponse
     */
    public function update(Request $request, int $cde_id):JsonResponse {
        $data = $request->all();
        $this->errors = [];
        $this->idCde = $cde_id;

        $validador = Validator::make($data, $this->getRules('update'));

        $this->errors = $validador->errors()->all();

        $objConsulta = $this->className::where('cde_id', $cde_id)->first();
        
        if (!$objConsulta)
            $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} con el ID [{$this->idCde}] no existe"]);

        if (empty($this->errors)) {

            $obj = $objConsulta->update($data);

            if($obj) {
                return response()->json([
                    'success' => true
                ], JsonResponse::HTTP_OK);
            }
            else {
                $this->errors = $this->adicionarError($this->errors, ["No fue posible actualizar el {$this->nombre} con el código [{$request->cde_id}]"]);
            }
        }

        return response()->json([
            'message' => $this->mensajeErroresModificacion,
            'errors'  => $this->errors
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Cambia el estado de uno o más registros seleccionados del Causales Devolucion documento electrónico estándar.
     *
     * @param Request $request Parámetros de la solicitud
     * @return JsonResponse
     */
    public function cambiarEstado(Request $request):JsonResponse {
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Obtiene el id y descripción de los registros en estado ACTIVO.
     * 
     * @return JsonResponse
     */
    public function searchCausalesDevolucion(): JsonResponse {
        $consulta = CausalDevolucion::select([
            'cde_id',
            'cde_descripcion'
            ])
            ->where('estado', 'ACTIVO')
            ->get();

        return response()->json([
            'data' => $consulta
        ], JsonResponse::HTTP_OK);
    }
}
