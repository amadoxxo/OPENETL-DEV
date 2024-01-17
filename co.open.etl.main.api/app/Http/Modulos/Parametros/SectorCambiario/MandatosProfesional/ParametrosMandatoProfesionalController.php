<?php

namespace App\Http\Modulos\Parametros\SectorCambiario\MandatosProfesional;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\OpenBaseController;

class ParametrosMandatoProfesionalController extends OpenBaseController {
    
    /**
     * Nombre del modelo en singular.
     * 
     * @var String
     */
    public $nombre = 'Mandato Profesional de Cambios';

    /**
     * Nombre del modelo en plural.
     * 
     * @var String
     */
    public $nombrePlural = 'Mandatos Profesional de Cambios';

    /**
     * Modelo relacionado a la paramétrica.
     * 
     * @var Illuminate\Database\Eloquent\Model
     */
    public $className = ParametrosMandatoProfesional::class;

    /**
     * Mensaje de error para status code 422 al crear.
     * 
     * @var String
     */
    public $mensajeErrorCreacion422 = 'No Fue Posible Crear el Mandato Profesional de Cambios';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresCreacion = 'Errores al Crear el Mandato Profesional de Cambios';

    /**
     * Mensaje de error para status code 422 al actualizar.
     * 
     * @var String
     */
    public $mensajeErrorModificacion422 = 'Errores al Actualizar el Mandato Profesional de Cambios';

    /**
     * Mensaje de errores al momento de crear.
     * 
     * @var String
     */
    public $mensajeErroresModificacion = 'Errores al Actualizar el Mandato Profesional de Cambios';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectNotFound = 'El Id del Mandato Profesional de Cambios [%s] No Existe';

    /**
     * Mensaje de error cuando el objeto no existe.
     * 
     * @var String
     */
    public $mensajeObjectDisabled = 'El Id del Mandato Profesional de Cambios [%s] Esta Inactivo';

    /**
     * Campo de control para filtrado de información.
     *
     * @var string
     */
    public $nombreCampoIdentificacion = 'cmp_codigo';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->middleware(['jwt.auth', 'jwt.refresh']);


        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioMandatoProfesional,ParametricaSectorCambiarioMandatoProfesionalNuevo,ParametricaSectorCambiarioMandatoProfesionalEditar,ParametricaSectorCambiarioMandatoProfesionalVer,ParametricaSectorCambiarioMandatoProfesionalCambiarEstado'
        ])->except([
            'show',
            'store',
            'update',
            'busqueda',
            'cambiarEstado',
            'consultaRegistroParametrica'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioMandatoProfesionalNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioMandatoProfesionalEditar'
        ])->only([
            'update'
        ]);
        
        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioMandatoProfesionalVer'
        ])->only([
            'show',
            'busqueda'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioMandatoProfesionalEditar,ParametricaSectorCambiarioMandatoProfesionalVer'
        ])->only([
            'consultaRegistroParametrica'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ParametricaSectorCambiarioMandatoProfesionalCambiarEstado'
        ])->only([
            'cambiarEstado'
        ]);
    }

    /**
     * Muestra solo un registro.
     * 
     * @param  int  $id
     * @return Response
     */
    public function show($id){
        return $this->procesadorShow($id, ['getUsuarioCreacion']);
    }

    /**
     * Almacena un código nuevo.
     * 
     * @param Request $request
     * @return Response
     */
    public function store(Request $request){
        $chequear = [
            [
                'field' => 'cmp_codigo',
                'message' => 'El Código [%s] del Mandato Profesional de Cambios que Intenta Insertar ya existe con la misma fecha de vigencia',
                'type' => 'multiunique'
            ],
            [
                'field' => 'cmp_descripcion',
                'message' => 'La Descripción [%s] del Mandato Profesional de Cambios que Intenta Insertar ya existe con la misma fecha de vigencia',
                'type' => 'multiunique'
            ]
        ];
        $columnas = ['cmp_codigo', 'cmp_significado', 'cmp_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'];
        return $this->procesadorSimpleStore($request, $columnas, 'cmp_id', $chequear);
    }

    /**
     * Actualiza el código de Cambios especificado en el almacenamiento.
     *
     * @param Request $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id) {
        $chequear = [
            [
                'field' => 'cmp_codigo',
                'message' => 'El Código [%s] del Mandato Profesional de Cambios que Intenta Modificar ya existe con la misma fecha de vigencia',
                'type' => 'multiunique'
            ],
            [
                'field' => 'cmp_significado',
                'message' => 'El Significado [%s] del Mandato Profesional de Cambios que Intenta Modificar ya existe con la misma fecha de vigencia',
                'type' => 'multiunique'
            ],
            [
                'field' => 'cmp_descripcion',
                'message' => 'La Descripción [%s] del Mandato Profesional de Cambios que Intenta Modificar ya existe con la misma fecha de vigencia',
                'type' => 'multiunique'
            ]
        ];
        $columnas = ['cmp_codigo', 'cmp_significado', 'cmp_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', 'estado'];
        return $this->procesadorSimpleUpdate($request, $id, $columnas, 'cmp_id', $chequear, true);
    }
    
    /**
     * Cambia el estado de una lista de registros seleccionados.
     *
     * @param Request $request
     * @return Response
     */
    public function cambiarEstado(Request $request){
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Devuelve una lista paginada de registros.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function getListaMandatosProfesional(Request $request) {
        $filtros = [];
        $condiciones = [
            'filters' => $filtros,
        ];
        $columnas = [
            'cmp_id',
            'cmp_codigo',
            'cmp_significado',
            'cmp_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta',
            'usuario_creacion',
            'fecha_creacion',
            'fecha_modificacion',
            'estado'
        ];
        $relaciones = ['getUsuarioCreacion'];
        $exportacion = [
            'columnas' => [
                'cmp_codigo' => 'Código',
                'cmp_significado' => 'Significado',
                'cmp_descripcion' => 'Descripcion',
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
            'titulo' => 'mandato-profesional-cambios'
        ];
        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, [], 'parametrica');
    }

    /**
     * Efectua un proceso de búsqueda en la paramétrica.
     *
     * @param Request $request
     * @return Response
     */
    public function busqueda(Request $request) {
        $columnas = [
            'cmp_id',
            'cmp_codigo',
            'cmp_significado',
            'cmp_descripcion',
            'fecha_vigencia_desde',
            'fecha_vigencia_hasta',
            'estado'
        ];
        return $this->procesadorBusqueda($request, $columnas);
    }

    /**
     * Obtiene el registro seleccionado a partir de su código y fechas de vigencia.
     *
     * @param Request $request
     * @return Response
     */
    public function consultaRegistroParametrica(Request $request) {
        return $this->procesadorConsultaRegistroParametrica($request, ['getUsuarioCreacion']);
    }
}
