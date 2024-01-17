<?php

namespace App\Http\Modulos\Parametros\XpathDocumentosElectronicos;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\OpenTenantController;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronico;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoTenant;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ParametrosXpathDocumentoElectronicoController extends OpenTenantController {
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
     * @var Model
     */
    public $className;

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
    public $nombreDatoCambiarEstado = 'xpath';

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
     * Campo de control para filtrado de información.
     *
     * @var string
     */
    public $nombreCampoIdentificacion = 'xde_id';

    /**
     * Autoincremental del registro que se está modificando.
     * 
     * @var String
     */
    public $idXpath = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionXPathDEEstandar,ConfiguracionXPathDEEstandarNuevo,ConfiguracionXPathDEEstandarEditar,ConfiguracionXPathDEEstandarVer,ConfiguracionXPathDEEstandarCambiarEstado,ConfiguracionXPathDEEstandarDescargar'
        ])->only([
            'getListaConfiguracionEstandar'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionXPathDEPersonalizado,ConfiguracionXPathDEPersonalizadoNuevo,ConfiguracionXPathDEPersonalizadoEditar,ConfiguracionXPathDEPersonalizadoVer,ConfiguracionXPathDEPersonalizadoCambiarEstado,ConfiguracionXPathDEPersonalizadoDescargar'
        ])->only([
            'getListaConfiguracionPersonalizada'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionXPathDEEstandarNuevo,ConfiguracionXPathDEPersonalizadoNuevo'
        ])->only([
            'store'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionXPathDEEstandarEditar,ConfiguracionXPathDEPersonalizadoEditar'
        ])->only([
            'update'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionXPathDEEstandarVer,ConfiguracionXPathDEEstandarEditar'
        ])->only([
            'showConfiguracionEstandar'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionXPathDEPersonalizadoVer,ConfiguracionXPathDEPersonalizadoEditar'
        ])->only([
            'showConfiguracionPersonalizada'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionXPathDEEstandarCambiarEstado'
        ])->only([
            'cambiarEstadoConfiguracionEstandar'
        ]);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionXPathDEPersonalizadoCambiarEstado'
        ])->only([
            'cambiarEstadoConfiguracionPersonalizada'
        ]);
    }

    /**
     * Configura las reglas para poder actualizar o crear la información de los XPath de los documentos electrónicos.
     *
     * @param  string $tipoConfiguracion Indica si el modelo es estándar o personalizado
     * @param  string $accion Acción a ejecutar si es creación o actualización
     * @return array
     */
    private function getRules(string $tipoConfiguracion, string $accion) {
        if($accion === 'store') {
            $rules = array_merge($this->className::$rules);
        } else {
            $rules = array_merge($this->className::$rulesUpdate);
            // Se modifica la regla del campo xde_xpath, para que omita en la validación el Id del registro que se está actualizando
            $rules['xde_xpath'] = $rules['xde_xpath']. ",{$this->idXpath},xde_id";
        }
        if($tipoConfiguracion === 'personalizada') {
            unset($rules['ofe_id']);
            $rules['ofe_identificacion'] = 'required|string|max:20';
        }

        return $rules;
    }

    /**
     * Devuelve una lista paginada de registros.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response
     * @throws \Exception
     */
    private function getListaConfiguracion(Request $request) {
        $user = auth()->user();

        $condiciones = [
            'filters' => [],
        ];
        $columnas = [
            'etl_xpath_documentos_electronicos.xde_id',
            'etl_xpath_documentos_electronicos.xde_xpath',
            'etl_xpath_documentos_electronicos.xde_aplica_para',
            'etl_xpath_documentos_electronicos.xde_descripcion',
            'etl_xpath_documentos_electronicos.usuario_creacion',
            'etl_xpath_documentos_electronicos.fecha_creacion',
            'etl_xpath_documentos_electronicos.fecha_modificacion',
            'etl_xpath_documentos_electronicos.estado'
        ];
        $relaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,estado'
        ];
        $whereHasConditions = [];
        $exportacion = [
            'columnas' => [
                'ofe_id' => [
                    'label' => 'NIT OFE/RECEPTOR',
                    'relation' => ['name' => 'getConfiguracionObligadoFacturarElectronicamente', 'field' => 'ofe_identificacion']
                ],
                'xde_aplica_para' => 'TIPO DOCUMENTO',
                'xde_descripcion' => 'DESCRIPCION',
                'xde_xpath' => 'XPATH',
                'fecha_creacion' =>  [
                    'label' => 'CREADO',
                    'type' => self::TYPE_CARBON
                ],
                'fecha_modificacion' =>  [
                    'label' => 'MODIFICADO',
                    'type' => self::TYPE_CARBON
                ],
                'estado' => 'Estado'
            ],
            'titulo' => ''
        ];
        if($request->filled('xpath_configuracion') && $request->xpath_configuracion === 'estandar') {
            $tipoConfiguracion = 'estandar';
            $this->defineVariablesGlobales($tipoConfiguracion);
            unset($relaciones[1]);
            unset($exportacion['columnas']['ofe_id']);
            $exportacion['titulo'] = $this->nombreArchivo;
        } else {
            $tipoConfiguracion = 'personalizada';
            $this->defineVariablesGlobales($tipoConfiguracion);
            array_splice($columnas, 1, 0, 'etl_xpath_documentos_electronicos.ofe_id');
            $exportacion['titulo'] = $this->nombreArchivo;
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
        }

        return $this->procesadorTracking($request, $condiciones, $columnas, $relaciones, $exportacion, false, $whereHasConditions, 'configuracion');
    }

    /**
     * Devuelve una lista paginada de registros para los XPath de los documentos electrónicos estandar.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response
     */
    public function getListaConfiguracionEstandar(Request $request) {
        $request->merge([
            'xpath_configuracion' => 'estandar'
        ]);
        return $this->getListaConfiguracion($request);
    }

    /**
     * Devuelve una lista paginada de registros para los XPath de los documentos electrónicos personalizados.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response
     */
    public function getListaConfiguracionPersonalizada(Request $request) {
        $request->merge([
            'xpath_configuracion' => 'personalizada'
        ]);
        return $this->getListaConfiguracion($request);
    }

    /**
     * Obtiene el registro del XPath del documento electrónico estándar especificado.
     *
     * @param $id Identificador del XPath del documento electrónico estándar
     * @return Response
     */
    public function showConfiguracionEstandar($id){
        $relaciones = [
            'getUsuarioCreacion',
        ];
        $this->defineVariablesGlobales('estandar');

        return $this->procesadorShow($id, $relaciones);
    }

    /**
     * Obtiene el registro del XPath del documento electrónico personalizado especificado.
     *
     * @param $id Identificador del XPath del documento electrónico personalizado
     * @return Response
     */
    public function showConfiguracionPersonalizada($id){
        $relaciones = [
            'getUsuarioCreacion',
            'getConfiguracionObligadoFacturarElectronicamente'
        ];
        $this->defineVariablesGlobales('personalizada');

        return $this->procesadorShow($id, $relaciones);
    }

    /**
     * Permite crear un XPath del documento electrónico estándar o personalizado.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response
     */
    public function store(Request $request){
        $this->user   = auth()->user();
        $this->errors = [];
        $msjAplicaOfe = '';
        $data = $request->all();

        if(!$request->filled('ofe_identificacion')) {
            $tipoConfiguracion = 'estandar';
            $this->defineVariablesGlobales($tipoConfiguracion);
            $validador = Validator::make($data, $this->getRules($tipoConfiguracion, 'store'));
        } else {
            $tipoConfiguracion = 'personalizada';
            $this->defineVariablesGlobales($tipoConfiguracion);
            $validador = Validator::make($data, $this->getRules($tipoConfiguracion, 'store'));
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->where(function ($query) {
                    $query->where('ofe_emision', 'SI')->orWhere('ofe_recepcion', 'SI');
                })
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$ofe)
                $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación {$request->ofe_identificacion} no existe o se encuentra en estado INACTIVO."]);

            if (!empty($this->user->bdd_id_rg) && $this->user->bdd_id_rg != $ofe->bdd_id_rg)
                $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación [{$request->ofe_identificacion}] no es válido."]);
        }

        if (!empty($this->errors))
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors'  => $this->errors
            ], 422);

        if (!$validador->fails()) {
            $existe = $this->className::where('xde_xpath', $request->xde_xpath);

            if($tipoConfiguracion === 'personalizada') {
                $existe         = $existe->where('ofe_id', $ofe->ofe_id);
                $msjAplicaOfe   = " para el OFE con identificación [{$request->ofe_identificacion}]";
                $data['ofe_id'] = $ofe->ofe_id;
            }

            $existe = $existe->first();

            if ($existe)
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} [{$request->xde_xpath}]{$msjAplicaOfe} ya existe."]);

            if (empty($this->errors)) {
                $data['estado']           = 'ACTIVO';
                $data['usuario_creacion'] = $this->user->usu_id;
                $obj = $this->className::create($data);

                if($obj)
                    return response()->json([
                        'success' => true,
                        'gtr_id'  => $obj->xde_id
                    ], 200);
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
     * Permite actualizar un XPath del documento electrónico estándar o personalizado.
     *
     * @param Request  $request Parámetros de la solicitud
     * @param int $xde_id Id del XPath que va ser actualizado
     * @return Response
     */
    public function update(Request $request, $xde_id){
        $data = $request->all();
        $msjAplicaOfe = '';
        $this->errors = [];
        $this->idXpath = $xde_id;

        if(!isset($request->ofe_identificacion)) {
            $tipoConfiguracion = 'estandar';
            $this->defineVariablesGlobales($tipoConfiguracion);
            $validador = Validator::make($data, $this->getRules($tipoConfiguracion, 'update'));
        } else {
            $tipoConfiguracion = 'personalizada';
            $this->defineVariablesGlobales($tipoConfiguracion);
            $validador = Validator::make($data, $this->getRules($tipoConfiguracion, 'update'));

            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->where(function ($query) {
                    $query->where('ofe_emision', 'SI')->orWhere('ofe_recepcion', 'SI');
                })
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$ofe)
                $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación {$request->ofe_identificacion} no existe o se encuentra en estado INACTIVO."]);

            if (!empty($this->user->bdd_id_rg) && $this->user->bdd_id_rg != $ofe->bdd_id_rg)
                $this->errors = $this->adicionarError($this->errors, ["El OFE con identificación [{$request->ofe_identificacion}] no es válido."]);
        }

        if (!empty($this->errors))
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors'  => $this->errors
            ], 422);

        if (!$validador->fails()) {
            $objConsulta = $this->className::where('xde_id', $xde_id)
                ->first();
            if (!$objConsulta)
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} [{$request->xde_xpath}]{$msjAplicaOfe} y aplica para [{$request->xde_aplica_para}] no existe"]);

            $existe = $this->className::where('xde_id', '!=', $xde_id)
                ->where('xde_xpath', $request->xde_xpath)
                ->where('xde_aplica_para', $request->xde_aplica_para);

            if($tipoConfiguracion === 'personalizada') {
                $existe         = $existe->where('ofe_id', $ofe->ofe_id);
                $msjAplicaOfe   = " para el OFE con identificación [{$request->ofe_identificacion}]";
                $data['ofe_id'] = $ofe->ofe_id;
            }

            $existe = $existe->first();

            if ($existe && $objConsulta) {
                $this->errors = $this->adicionarError($this->errors, ["{$this->nombre} [{$request->xde_xpath}]{$msjAplicaOfe} ya existe"]);
            }

            if (empty($this->errors)) {
                $obj = $objConsulta->update($data);

                if($obj)
                    return response()->json([
                        'success' => true
                    ], 200);
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
     * Cambia el estado de uno o más registros seleccionados del XPath documento electrónico estándar.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response
     */
    public function cambiarEstadoConfiguracionEstandar(Request $request){
        $this->defineVariablesGlobales('estandar');
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Cambia el estado de uno o más registros seleccionados del XPath documento electrónico personalizado.
     *
     * @param Request $request Parámetros de la solicitud
     * @return Response
     */
    public function cambiarEstadoConfiguracionPersonalizada(Request $request) {
        $this->defineVariablesGlobales('personalizada');
        return $this->procesadorCambiarEstado($request);
    }

    /**
     * Define las variables globales de la clase.
     *
     * @param string $tipoConfiguracion estandar|personalizado
     * @return void
     */
    private function defineVariablesGlobales($tipoConfiguracion) {
        if($tipoConfiguracion === 'estandar') {
            $this->nombre = "XPath Documento Electrónico Estándar";
            $this->nombrePlural = "XPath Documentos Electrónicos Estándar";
            $this->className = ParametrosXpathDocumentoElectronico::class;
            $this->nombreArchivo = "xpath_documento_estandar";
        } else {
            $this->nombre = "XPath Documento Electrónico Personalizado";
            $this->nombrePlural = "XPath Documentos Electrónicos Personalizados";
            $this->className = ParametrosXpathDocumentoElectronicoTenant::class;
            $this->nombreArchivo = "xpath_documento_personalizado";
        }

        $this->mensajeErrorCreacion422 = "No Fue Posible Crear el {$this->nombre}";
        $this->mensajeErroresCreacion = "Errores al Crear el {$this->nombre}";
        $this->mensajeErrorModificacion422 = "Errores al Actualizar el {$this->nombre}";
        $this->mensajeErroresModificacion = "Errores al Actualizar el {$this->nombre}";
        $this->mensajeObjectNotFound = "El Id del {$this->nombre} [%s] no existe";
        $this->mensajeObjectDisabled = "El Id del {$this->nombre} [%s] esta inactivo";
    }
}
