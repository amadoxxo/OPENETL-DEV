<?php

namespace App\Http\Modulos\Parametros\SectorCambiario\DebidaDiligencia;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\OpenBaseController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Modulos\Parametros\SectorCambiario\DebidaDiligencia\ParametrosDebidaDiligencia;

class ParametrosDebidaDiligenciaController extends OpenBaseController {
    /**
     * Nombre del modelo en singular.
     * 
     * @var string
     */
    public $nombre = 'Debida Diligencia';

    /**
     * Nombre del modelo en plural.
     * 
     * @var string
     */
    public $nombrePlural = 'Debida Diligencia';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var ParametrosDebidaDiligencia
     */
    public $className = ParametrosDebidaDiligencia::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var string
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear la Debida Diligencia';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var string
     */
    public $mensajeErroresCreacion = 'Errores al Crear la Debida Diligencia';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var string
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar la Debida Diligencia';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var string
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar la Debida Diligencia';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var string
     */
    public $mensajeObjectNotFound = 'El Id de la Debida Diligencia [%s] no existe';

    /**
     * Propiedad para almacenar los errores.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var string
     */
    public $mensajeObjectDisabled = 'El Id de la Debida Diligencia [%s] esta inactivo';

    /**
     * Campo de control para filtrado de informacion al cambiar el estado.
     * 
     * @var string
     */
    public $nombreDatoCambiarEstado = 'ddi_ids';

    /**
     * Extensiones permitidas para el cargue de registros.
     * 
     * @var array
     */
    public $arrExtensionesPermitidas = ['xlsx', 'xls'];

    /**
     * Nombre del archivo Excel.
     * 
     * @var string
     */
    public $nombreArchivo = 'debida_diligencia';

    /**
     * Campo de control para filtrado de información.
     *
     * @var string
     */
    public $nombreCampoIdentificacion = 'ddi_id';

    /**
     * Autoincremental del registro que se está modificando.
     * 
     * @var string
     */
    public $idDdi = '';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->middleware(['jwt.auth','jwt.refresh']);

        // Listar Sector Cambiario Debida Diligencia
        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioDebidaDiligencia,ParametricaSectorCambiarioDebidaDiligenciaVer,ParametricaSectorCambiarioDebidaDiligenciaNuevo,ParametricaSectorCambiarioDebidaDiligenciaEditar,ParametricaSectorCambiarioDebidaDiligenciaCambiarEstado'
        ])->only([
            'getListaParametrosDebidaDiligencia'
        ]);

        // Ver Sector Cambiario Debida Diligencia
        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioDebidaDiligenciaVer,ParametricaSectorCambiarioDebidaDiligenciaEditar'
        ])->only([
            'show'
        ]);

        // Crear Configuraricion Debida Diligencia
        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioDebidaDiligenciaNuevo'
        ])->only([
            'store'
        ]);

        // Editar Sector Cambiario Debida Diligencia
        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioDebidaDiligenciaEditar'
        ])->only([
            'update'
        ]);

        // Cambiar Estado Sector Cambiario Debida Diligencia
        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioDebidaDiligenciaCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);
    }

    /**
     * Devuelve una lista paginada de registros o el archivo excel con los registros de la paramétrica.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response|JsonResponse|BinaryFileResponse
     * @throws \Exception
     */
    public function getListaParametrosDebidaDiligencia(Request $request) {
        $columnas = [
            'etl_debida_diligencia.ddi_id',
            'etl_debida_diligencia.ddi_codigo',
            'etl_debida_diligencia.ddi_descripcion',
            'etl_debida_diligencia.fecha_vigencia_desde',
            'etl_debida_diligencia.fecha_vigencia_hasta',
            'etl_debida_diligencia.usuario_creacion',
            'etl_debida_diligencia.fecha_creacion',
            'etl_debida_diligencia.fecha_modificacion',
            'etl_debida_diligencia.estado'
        ];
        $relaciones = ['getUsuarioCreacion'];
        $exportacion = [
            'columnas' => [
                'ddi_codigo' => 'Código',
                'ddi_descripcion' => 'Descripcion',
                'fecha_vigencia_desde' => 'Fecha Vigencia Desde',
                'fecha_vigencia_hasta' => 'Fecha Vigencia Hasta',
                'usuario_creacion' => [
                    'label' => 'Usuarios Creacion',
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
            'titulo' => 'debida-diligencia'
        ];
        $exportacion['titulo'] = $this->nombreArchivo;

        return $this->procesadorTracking($request, [], $columnas, $relaciones, $exportacion, false, [], 'parametrica');
    }

    /**
     * Obtiene el registro de una sola Debida Diligencia.
     *
     * @param $id Identificador de la Debida Diligencia.
     * @return Response|JsonResponse
     */
    public function show(string $id) {
        return $this->procesadorShow($id, ['getUsuarioCreacion']);
    }

    /**
     * Crea una nueva Debida Diligencia.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response|JsonResponse
     */
    public function store(Request $request) {
        $chequear = [
            [
                'field'   => 'ddi_codigo',
                'message' => 'El Código [%s] de la Debida Diligencia que Intenta Insertar ya existe con la misma fecha de vigencia',
                'type'    => 'multiunique'
            ],
            [
                'field'   => 'ddi_descripcion',
                'message' => 'La Descripción [%s] de la Debida Diligencia que Intenta Insertar ya existe con la misma fecha de vigencia',
                'type'    => 'multiunique'
            ]
        ];
        $columnas = ['ddi_codigo', 'ddi_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'];
        return $this->procesadorSimpleStore($request, $columnas, 'ddi_id', $chequear);
    }

    /**
     * Actualiza el código de la Debida Diligencia especificado en el almacenamiento.
     *
     * @param Request $request Parámetros de la solicitud
     * @param int $ddi_id Id de la Debida Diligencia.
     * @return Response|JsonResponse
     */
    public function update(Request $request, int $ddi_id) {
        $chequear = [
            [
                'field' => 'ddi_codigo',
                'message' => 'El Código [%s] de la Debida Diligencia que Intenta Modificar ya existe con la misma fecha de vigencia',
                'type' => 'multiunique'
            ],
            [
                'field' => 'ddi_descripcion',
                'message' => 'La Descripción [%s] de la Debida Diligencia que Intenta Modificar ya existe con la misma fecha de vigencia',
                'type' => 'multiunique'
            ]
        ];
        $columnas = ['ddi_codigo', 'ddi_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', 'estado'];
        return $this->procesadorSimpleUpdate($request, $ddi_id, $columnas, 'ddi_id', $chequear, true);
    }

    /**
     * Cambia el estado de uno o más registros seleccionados de la Debida Diligencia.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response|JsonResponse
     */
    public function cambiarEstado(Request $request) {
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Efectua un proceso de búsqueda en la paramétrica.
     * 
     * @param Request $request
     * @return Response|JsonResponse
     */
    public function busqueda(Request $request) {
        $columnas = [
            'ddi_id',
            'ddi_codigo',
            'ddi_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta',
            'estado'
        ];
        return $this->procesadorBusqueda($request, $columnas);
    }

    /**
     * Obtiene el registro seleccionado a partir de su código y fechas de vigencia.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response|JsonResponse
     */
    public function consultaRegistroParametrica(Request $request) {
        return $this->procesadorConsultaRegistroParametrica($request, ['getUsuarioCreacion']);
    }
}
