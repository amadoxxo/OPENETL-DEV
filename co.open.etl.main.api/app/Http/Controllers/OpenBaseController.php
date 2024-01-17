<?php
namespace App\Http\Controllers;

use Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronico;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoTenant;


/**
 * Controlador con las funcionalidades basicas de los trackings de OpenTenologia
 * Contiene las funcionalidades basicas a modo general de Cambiar Estados, Listar, exportar a Excel
 * Para todos los modelos contenidos la API de openPCE
 *
 * Class OpenBaseController
 * @package App\Http\Controllers
 */
class OpenBaseController extends Controller {

    use FechaVigenciaValidations;

    public const TYPE_CARBON        = 'carbon';
    public const TYPE_ARRAY         = 'array';
    public const TYPE_ARRAY_OBJECTS = 'array_objects';

    /**
     * Setea el numero de maximo de registros que se puede retornar en una busqueda de integracion
     * @var int
     */
    public $limitBusquedas = 10;

    /**
     * Nombre del modelo en singular.
     *
     * @var String
     */
    public $nombre;

    /**
     * Nombre del modelo en plural.
     *
     * @var String
     */
    public $nombrePlural;

    /**
     * Modelo relacionado a la parametrica.
     *
     * @var String
     */
    public $className;

    /**
     * Mensaje de error para status code 422 al crear.
     *
     * @var String
     */
    public $mensajeErrorCreacion422;

    /**
     * Mensaje de errores al momento de crear.
     *
     * @var String
     */
    public $mensajeErroresCreacion;

    /**
     * Mensaje de error para status code 422 al actualizar.
     *
     * @var String
     */
    public $mensajeErrorModificacion422;

    /**
     * Mensaje de errores al momento de crear.
     *
     * @var String
     */
    public $mensajeErroresModificacion;

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var String
     */
    public $mensajeObjectNotFound;

    /**
     * Mensaje de error cuando el objeto no existe.
     *
     * @var string
     */
    public $mensajeObjectDisabled;

    /**
     * Claves referenciales del padre.
     *
     * @var array
     */
    public $master_keys = [];

    /**
     * Contiene la relaciones de depencia con otros modelos.
     *
     * @var array
     */
    public $foreign_keys = [];

    /**
     * Reglas para actualizar o modificar un registro
     *
     * @var array
     */
    private $rules = null;

    /**
     * OpenBaseController constructor.
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * @var string Nombre del campo de identificacion que existe en el modelo.
     */
    public $nombreCampoIdentificacion = null;

    /**
     * Permite reemplazar las reglas de validacion para crear o actualizar un modelo
     *
     * @param array $rules
     */
    public function setRules($rules): void {
        $this->rules = $rules;
    }

    /**
     * Se determina si se ha proporcionado datos de uno o varios modelos maestros para efectuar comprobaciones de duplicidad
     * sobre campos que deberia poseer valores unicos dentro de cada subgrupo {$parent_key, $child_field}.
     *
     * @param Request $request
     * @param array $dependencias
     * @param array $erroresDependencias
     * @param $malformedData
     */
    private function appendMasterKeys(Request $request, array &$dependencias, array &$erroresDependencias, &$malformedData) {
        if ($this->master_keys && count($this->master_keys)) {
            foreach ($this->master_keys as $master_key) {
                if (array_key_exists('model', $master_key) && array_key_exists('id', $master_key) && array_key_exists('error_msg', $master_key)) {
                    if (array_key_exists('optional', $master_key) && $master_key['optional']) {
                        // Es una clave que puede ser opcional
                        if (isset($request->{$master_key['id']}) || $request->{$master_key['id']} === null)
                            continue;
                    }

                    $objeto = $master_key['model']::find($request->{$master_key['id']});
                    if ($objeto)
                        $dependencias[] = [$master_key['id'], '=', $request->{$master_key['id']}];
                    else
                        $erroresDependencias[] = sprintf($master_key['error_msg'], $request->{$master_key['id']});
                }
                else
                    $malformedData = $this->getErrorResponseByCode('No se han proporcionado de forma correcta los datos del modelo padre ["model" => "XXXX", "id"=>"Nombre del ID", "error_msg"="El valor de id[%d] no esta registrado en el sistema"]', Response::HTTP_CONFLICT);
            }
        }
    }

    /**
     * Se verifica si se ha proporcionado datos de uno o varios modelos externos para efectuar comprobaciones de existencia.
     *
     * de los mismos en cada una de las tablas correspondientes
     * @param Request $request
     * @param array $erroresExistencia
     * @param $malformedData
     */
    private function checkForeignKeys(Request $request, array &$erroresExistencia, &$malformedData) {
        if ($this->foreign_keys && count($this->foreign_keys)) {
            foreach ($this->foreign_keys as $foreign_key) {
                if (array_key_exists('model', $foreign_key) && array_key_exists('id', $foreign_key) && array_key_exists('error_msg', $foreign_key)) {
                    if (array_key_exists('optional', $foreign_key) && $foreign_key['optional']) {
                        // Es un foreign key opcional
                        if (isset($request->{$foreign_key['id']}) || $request->{$foreign_key['id']} === null)
                            continue;
                    }

                    $objeto = $foreign_key['model']::where($foreign_key['id'], $request->{$foreign_key['id']})->count();
                    if ($objeto === 0)
                        $erroresExistencia[] = sprintf($foreign_key['error_msg'], $request->{$foreign_key['id']});
                }
                else
                    $malformedData = $this->getErrorResponseByCode('No se han proporcionado de forma correcta los datos del model ["model" => "XXXX", "id"=>"Nombre del ID", "error_msg"="El valor de id[%d] no esta registrado en el sistema"]', Response::HTTP_CONFLICT);
            }
        }
    }

    /**
     * Almacena un registro de forma simple (Una sola tabla)
     *
     * @param Request $request
     * @param array $fields
     * @param string $obj_id
     * @param $comparadores
     * @return Response
     */
    public function procesadorSimpleStore(Request $request, array $fields, string $obj_id, $comparadores){
        $arrErrores = [];
        $objUser = auth()->user();

        $objValidator = Validator::make($request->all(), $this->rules ?? $this->className::$rules );
        $this->adicionarErrorArray($arrErrores, $objValidator->errors()->all());

        if (empty($objValidator->errors()->all())) {
            if ($request->fecha_vigencia_desde != null && $request->fecha_vigencia_desde != '' && 
                $request->fecha_vigencia_hasta != null && $request->fecha_vigencia_hasta != '') 
            {   
                if (Carbon::parse($request->fecha_vigencia_hasta)->lt(Carbon::parse($request->fecha_vigencia_desde))) {
                    $this->adicionarErrorArray($arrErrores, ['El campo fecha vigencia hasta debe ser una fecha posterior a fecha vigencia desde.']);
                } 
            }
        }

        if(count($arrErrores) == 0) {

            $dependencias = [];
            $erroresDependencias = [];
            $malformedData = null;
            $this->appendMasterKeys($request, $dependencias, $erroresDependencias, $malformedData);

            if ($malformedData)
                return $malformedData;
            // Si hay identificadores de objetos señalados como padres que no estan registrados en la base de datos
            if (count($erroresDependencias) > 0)
                return $this->getErrorResponseByCode($erroresDependencias, Response::HTTP_CONFLICT);

            // Acciones de verificacion
            foreach ($comparadores as $item) {
                if (!empty($item['type'])){
                    $objExiste = $this->customComparation($item);
                } else {
                    $objExiste = $this->className::where($item['field'], $request->{$item['field']});
                }

                /*
                * Si se configuro dependencias de para el objeto, por ejemplo en el caso de los Departamentos, estos
                * deben de estar asociados a un pai_id valido, estas condiciones pueden ser simples o multiples, y en todos
                * los comparadores que existan deben ser aplicados, porque es necesario evaluar la validez del comparador
                * para el subgrupo donde se esta intentado asociar los datos
                */
                if ($dependencias)
                    $objExiste = $objExiste->where($dependencias);

                $objExiste = $objExiste->first();
                if ($objExiste) {
                    if (!empty($item['type'])){
                        $error_messages = $this->customComparationMessage($item);
                    } else {
                        $error_messages = sprintf($item['message'], $objExiste->{$item['field']});
                    }

                    return response()->json([
                        'message' => $this->mensajeErroresCreacion,
                        'errors' => [$error_messages]
                    ], Response::HTTP_CONFLICT);
                }
            }

            // Verificando los foreign keys
            $erroresExistencia = [];
            $malformedData = null;
            $this->checkForeignKeys($request, $erroresExistencia, $malformedData);
            if ($malformedData)
                return $malformedData;
            // Si hay foreigns que no estan registrados en la base de datos
            if (count($erroresExistencia) > 0)
                return $this->getErrorResponseByCode($erroresExistencia, Response::HTTP_CONFLICT);

            $input = [];
            foreach ($fields as $field)
                $input[$field] = $request->{$field};
            $input['usuario_creacion'] = $objUser->usu_id;
            $input['estado'] = 'ACTIVO';

            $obj = $this->className::create($input);
            if($obj){
                return response()->json(['success' => true, $obj_id  => $obj->{$obj_id}], Response::HTTP_CREATED);
            } else {
                return response()->json([
                    'message' => $this->mensajeErrorCreacion422,
                    'errors' => []
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            return response()->json([
                'message' => $this->mensajeErroresCreacion,
                'errors' => $arrErrores
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Validador de comparaciones por tipos
     *
     * se encarga de responder la consulta adecuada para poder validar si el registro ya existe.
     * 
     * @param Array $item Item de comparacion actualmente validandose
     * @return Illuminate\Database\Eloquent\Builder
     */
    protected function customComparation($item) {
        $request = request();

        switch ($item['type']) {
            case 'multiunique':
                $fields = explode(',', $item['field']);
                $unique_value = '';
                foreach ($fields as $key => $field) {
                    try {
                        $unique_value .= $request->{$field};
                    }
                    catch (\Exception $e){
                        $unique_value .= $field;
                    }
                }
                $where = "CONCAT(".$item['field'].") = '{$unique_value}'";
                $objExiste = $this->className::whereRaw($where)
                    ->where('fecha_vigencia_desde', '=', $request->fecha_vigencia_desde)
                    ->where('fecha_vigencia_hasta', '=', $request->fecha_vigencia_hasta);
            break;
            default:
                $objExiste = $this->className::where($item['field'], $request->{$item['field']});
            break;
        }
        
        return $objExiste;
    }

    /**
     * Da un mensaje correcto para el tipo de validacion realizada
     *
     * modifica el mensaje predeterminado para poder responder correctamente.
     * 
     * @param Array $item Item de comparacion actualmente validandose
     * @return String
     */
    protected function customComparationMessage($item){
        $request = request();
        $error_messages = '';
        switch ($item['type']) {
            case 'multiunique':
                $fields = explode(',', $item['field']);
                $unique_value = '';
                foreach ($fields as $key => $field) {
                    try {
                        $unique_value .= $request->{$field}.',';
                    }
                    catch (\Exception $e){
                        $unique_value .= $field.',';
                    }
                }
                $error_messages = vsprintf($item['message'], explode(',', $unique_value));
            break;
            default:
                # code
            break;
        }
        return $error_messages;
    }

    /**
     * Actualiza un registro de forma simple (Una sola tabla).
     *
     * @param Request $request
     * @param mixed $valor Valor del campo que se define en la variable nombreCampoIdentificacion del controlador
     * @param array $fields Campos de la tabla de la paramétrica
     * @param string $obj_id Autoincremental de la paramétrica
     * @param $comparadores Campos de validación de la paramétrica.
     * @return Response
     */
    public function procesadorSimpleUpdate(Request $request, $valor, array $fields, string $obj_id, $comparadores, $allowDisabled = false){
        $arrErrores = [];
        if(is_array($valor)) {
            $id = $valor[0];
            $datoFiltroAdicional = $valor[1];
        } else
            $id = $valor;

        $objValidator = Validator::make($request->all(), $this->rules ?? $this->className::$rulesUpdate);
        $this->adicionarErrorArray($arrErrores, $objValidator->errors()->all());

        if (empty($objValidator->errors()->all())) {
            if ($request->fecha_vigencia_desde != null && $request->fecha_vigencia_desde != '' && 
                $request->fecha_vigencia_hasta != null && $request->fecha_vigencia_hasta != '') 
            {
                if (Carbon::parse($request->fecha_vigencia_hasta)->lt(Carbon::parse($request->fecha_vigencia_desde))) {
                    $this->adicionarErrorArray($arrErrores, ['El campo fecha vigencia hasta debe ser una fecha posterior a fecha vigencia desde.']);
                }
            }
        }

        if (is_null($this->nombreCampoIdentificacion))
            $obj = $this->className::find($id);
        else {
            $obj = $this->className::where($this->nombreCampoIdentificacion, $id);
            if(strstr($this->className, 'ParametrosConceptoCorreccion'))
                $obj = $obj->where('cco_tipo', $datoFiltroAdicional);
            elseif (strstr($this->className, 'ParametrosTipoOperacion'))
                $obj = $obj->where('top_aplica_para', $datoFiltroAdicional)
                    ->whereIn('top_aplica_para', ['FC', 'NC', 'ND', 'DS']);

            $obj = $obj->where('fecha_vigencia_desde', '=', $request->fecha_vigencia_desde_anterior)
                ->where('fecha_vigencia_hasta', '=', $request->fecha_vigencia_hasta_anterior)
                ->first();
        }

        if($obj) {
            if(strstr($this->className, 'User') && $obj->usu_type == 'INTEGRACION')
                $this->adicionarErrorArray($arrErrores, ['No se permite actualizar usuarios del tipo [INTEGRACION].']);

            if($obj->estado == 'INACTIVO' && !$allowDisabled)
                $this->adicionarErrorArray($arrErrores, ['No Se Permiten Actualizar Registros En Estado INACTIVO.']);

            // Válida que el registro a actualizar no exista con el mismo código y fechas de vigencia
            if (!is_null($this->nombreCampoIdentificacion)) {
                $objExisteVigente = $this->className::where($this->nombreCampoIdentificacion, $id);
                if(strstr($this->className, 'ParametrosConceptoCorreccion'))
                    $objExisteVigente = $objExisteVigente->where('cco_tipo', $datoFiltroAdicional);
                elseif (strstr($this->className, 'ParametrosTipoOperacion'))
                    $objExisteVigente = $objExisteVigente->where('top_aplica_para', $datoFiltroAdicional)
                        ->whereIn('top_aplica_para', ['FC', 'NC', 'ND', 'DS'])
                        ->where('top_aplica_para', '=', $request->top_aplica_para);

                $objExisteVigente = $objExisteVigente->where('fecha_vigencia_desde', '=', $request->fecha_vigencia_desde)
                    ->where('fecha_vigencia_hasta', '=', $request->fecha_vigencia_hasta)
                    ->where($obj_id, '!=', $obj->{$obj_id})
                    ->first();

                if ($objExisteVigente) {
                    $this->adicionarErrorArray($arrErrores, ['El Código ['.$id.'] que Intenta Actualizar ya existe con la misma fecha de vigencia']);
                }
            }

            if(empty($arrErrores)){
                $dependencias = [];
                $erroresDependencias = [];
                $malformedData = null;
                $this->appendMasterKeys($request, $dependencias, $erroresDependencias, $malformedData);

                if ($malformedData)
                    return $malformedData;
                // Si hay identificadores de objetos señalados como padres que no estan registrados en la base de datos
                if (count($erroresDependencias) > 0)
                    return $this->getErrorResponseByCode($erroresDependencias, Response::HTTP_CONFLICT);

                // Acciones de verificacion
                foreach ($comparadores as $item) {
                    // Vamos a verificar que solo exista uno con el codigo primario
                    if (is_null($this->nombreCampoIdentificacion)) {
                        if (!empty($item['type'])) {
                            $objExiste = $this->customComparation($item);
                            $objExiste->where($obj_id, '!=', $obj->{$obj_id});
                        } else {
                            $objExiste = $this->className::where($obj_id, '!=', $obj->{$obj_id})
                                ->where($item['field'], $request->{$item['field']});
                        }
                    } else {
                        $old = $this->className::where($this->nombreCampoIdentificacion, $id);
                        if(strstr($this->className, 'ParametrosConceptoCorreccion'))
                            $old = $old->where('cco_tipo', $datoFiltroAdicional);
                        elseif (strstr($this->className, 'ParametrosTipoOperacion'))
                            $old = $old->where('top_aplica_para', $datoFiltroAdicional)
                                ->whereIn('top_aplica_para', ['FC', 'NC', 'ND', 'DS']);

                        $old = $old->where('fecha_vigencia_desde', '=', $request->fecha_vigencia_desde_anterior)
                            ->where('fecha_vigencia_hasta', '=', $request->fecha_vigencia_hasta_anterior)
                            ->first();

                        // Se va a comparar el campo nos esta sirviendo de enlace
                        if ($this->nombreCampoIdentificacion === $item['field']) {
                            // Se evalua el caso en el que se modifica el valor, y se observa si va a tomar un valor
                            // asignado previamente a otro elemento
                            if ($id !== $request->{$this->nombreCampoIdentificacion}) {
                                $objExiste = $this->className::where($this->nombreCampoIdentificacion, '=', $request->{$this->nombreCampoIdentificacion});
                            }
                        } else {
                            if (!empty($item['type'])) {
                                $objExiste = $this->customComparation($item);
                                $objExiste->where($obj_id, '!=', $old->{$obj_id});
                            } else {
                                $objExiste = $this->className::where($obj_id, '!=', $old->{$obj_id});
                                $objExiste->where($item['field'], $request->{$item['field']});
                            }
                        }
                    }

                    /* Si se configuro dependencias de para el objeto, por ejemplo en el caso de los Departamentos, estos
                     * deben de estar asociados a un pai_id valido, estas condiciones pueden ser simples o multiples, y en todos
                     * los comparadores que existan deben ser aplicados, porque es necesario evaluar la validez del comparador
                     * para el subgrupo donde se esta intentado asociar los datos
                     */
                    if ($dependencias) {
                        if (isset($objExiste) && !is_null($objExiste))
                            $objExiste = $objExiste->where($dependencias);
                        else
                            $objExiste = $this->className::where($dependencias);
                    }
                    if (isset($objExiste)) {
                        $objExiste = $objExiste->first();
                        if ($objExiste) {
                            if (!empty($item['type'])) {
                                $error_messages = $this->customComparationMessage($item);
                            } else {
                                $error_messages = sprintf($item['message'], $objExiste->{$item['field']});
                            }

                            return response()->json([
                                'message' => $this->mensajeErroresModificacion,
                                'errors' => [$error_messages]
                            ], Response::HTTP_CONFLICT);
                        }
                    }
                    unset($objExiste);
                }

                // Verificando los foreign keys
                $erroresExistencia = [];
                $malformedData = null;
                $this->checkForeignKeys($request, $erroresExistencia, $malformedData);
                if ($malformedData)
                    return $malformedData;
                // Si hay foreigns que no estan registrados en la base de datos
                if (count($erroresExistencia) > 0)
                    return $this->getErrorResponseByCode($erroresExistencia, Response::HTTP_CONFLICT);

                $data = $request->all();
                $input = [];
                foreach ($fields as $field){
                    if ( array_key_exists($field, $data) ) {
                        $input[$field] = $request->{$field};
                    }
                }
                $obj->update($input);

                if($obj){
                    return response()->json([
                        'success' => true,
                        $obj_id  => $obj->{$obj_id}
                    ], Response::HTTP_OK);
                } else {
                    return response()->json([
                        'message' => $this->mensajeErrorModificacion422,
                        'errors' => []
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            } else {
                return response()->json([
                    'message' => $this->mensajeErrorModificacion422,
                    'errors' => $arrErrores
                ], Response::HTTP_BAD_REQUEST);
            }

        } elseif($obj && $obj->estado == 'INACTIVO') {
            return response()->json([
                'message' => $this->mensajeErrorModificacion422,
                'errors' => [sprintf($this->mensajeObjectDisabled, $id)]
            ], Response::HTTP_CONFLICT);
        } else {
            return response()->json([
                'message' => $this->mensajeErroresModificacion,
                'errors' => [sprintf($this->mensajeObjectNotFound, $id)]
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Toma los errores generados y los mezcla en un sólo arreglo para dar respuesta al usuario
     *
     * @param $arrErrores
     * @param $objValidator
     * @return void
     */
    public function adicionarErrorArray(&$arrErrores, $objValidator){
        foreach($objValidator as $error)
            array_push($arrErrores, $error);
    }

    /**
    * Muestra solo un registro.
    *
    *
    * @param mixed $id
    * @param array $relations
    * @return Response
    */
    public function procesadorShow($id, array $relations){
        if (is_null($this->nombreCampoIdentificacion)) {
            if (!empty($relations))
                $objectoModel = $this->className::with($relations)->find($id);
            else
                $objectoModel = $this->className::find($id);
        }
        else {
            if (!empty($relations))
                $objectoModel = $this->className::with($relations)->where($this->nombreCampoIdentificacion, $id)->first();
            else
                $objectoModel = $this->className::where($this->nombreCampoIdentificacion, $id)->first();
        }


        if ($objectoModel){        
            return response()->json([
                'data' => $objectoModel
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => "No Se Encontró {$this->nombre} Con Id [{$id}]",
            'errors' => []
        ], Response::HTTP_NOT_FOUND);
    }


    /**
     * Cambia el estado de una lista registros seleccionados
     *
     * @param Request $request
     * @param array $extraErrors
     * @return Response
     */
    public function procesadorCambiarEstado(Request $request, array $extraErrors = []){
        //Se obtiene todo el request
        $arrObjetos = $request->all();
        $arrErrores = $extraErrors;

        foreach($arrObjetos as $arrCampos){
            if(is_array($arrCampos)) {
                $codigo = '';
                $objeto = $this->className::select();
                foreach($arrCampos as $key => $value){
                    $objeto->where($key, $value);

                    if(strstr($key, '_codigo'))
                        $codigo = $value;
                }
                $objeto = $objeto->first();

                if($objeto){
                    $strEstado = ($objeto->estado == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                    $objeto->update(['estado' => $strEstado]);

                    if(!$objeto)
                        $this->adicionarErrorArray($arrErrores, ["Errores Al Actualizar el parámetro {$this->nombre} con código [{$codigo}]"]);
                } else
                    $this->adicionarErrorArray($arrErrores, ["El parámetro {$this->nombre} con código [{$codigo}] no existe."]);
            }
        }

        if(empty($arrErrores)) {
            return response()->json([
                'success' => true
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'message' => "Error Al Cambiar El Estado De {$this->nombrePlural} Seleccionados",
                'errors'  => $arrErrores
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Muestra solo un registro.
     *
     *
     * @param mixed $id
     * @param array $relations
     * @return Response
     */
    public function procesadorConsultaRegistroParametrica($request, array $relations){
        if (!empty($relations))
            $objectoModel = $this->className::with($relations)
                ->where($this->nombreCampoIdentificacion, $request->{$this->nombreCampoIdentificacion})
                ->where('fecha_vigencia_desde', $request->fecha_vigencia_desde)
                ->where('fecha_vigencia_hasta', $request->fecha_vigencia_hasta)
                ->first();
        else
            $objectoModel = $this->className::where($this->nombreCampoIdentificacion, $request->{$this->nombreCampoIdentificacion})
                ->where('fecha_vigencia_desde', $request->fecha_vigencia_desde)
                ->where('fecha_vigencia_hasta', $request->fecha_vigencia_hasta)
                ->first();

        if ($objectoModel){        
            return response()->json([
                'data' => $objectoModel
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => "No Se Encontró {$this->nombre} Con Código [{$request->{$this->nombreCampoIdentificacion}}]",
            'errors' => []
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Tracking generico, el filtrado de la condición se da en función del array $conditions
     * $conditions = [
     *      "restrict"  = [],
     *      "filters"   = [],
     *      "queryRaws" =[]
     * ]
     *
     * @param Request $request
     * @param array $conditions Set de condiciones
     * @param array $columns Columnas retornar
     * @param array $relations Relaciones a Incluier en la busqueda
     * @param array $opcionesExportacion Títulos de las columnas
     * @param bool $returnData Indica si se debe retornar la data pura o una respuesta json
     * @param array $whereHas Array de Relaciones sobre la cual se debe validar existencia de información
     * @param string $origen String que indica el origen de donde se hace uso el metodo
     * @param bool  $proceso_background Indica si se trata de un proceso en background o no para retornar la descarga del archivo o solo el nombre del archivo
     * @return array|Response
     * @throws \Exception
     */
    public function procesadorTracking(Request $request, array $conditions, array $columns, array $relations = [], array $opcionesExportacion = [], bool $returnData = false, array $whereHas = [], string $origen = 'parametrica', bool $proceso_background = false) {

        $objUser = auth()->user();
        $restrict = [];
        $filters = [];
        $queryRaws = [];
        $tracking = true;

        if (array_key_exists('restrict', $conditions))
            $restrict = $conditions['restrict'];
        if (array_key_exists('filters', $conditions))
            $filters = $conditions['filters'];
        if (array_key_exists('queryRaws', $conditions))
            $queryRaws = $conditions['queryRaws'];

        $buscador = $this->className::select($columns);

        if(!($request->filled('tracking')) || $request->tracking === 'false')
            $tracking = false;

        /*
         * Aplicable en aquellos casos en que los registros pertenece a otro (Maestro-Esquema)
         * $restrict = [
         *      ['campo_1', 'condicion', 'value_1'],
         *      ........
         *      ['campo_n', 'condicion', 'value_n']
         * ]
         * Todos se unen con AND
         */
        if (!empty($restrict))
            $buscador->where($restrict);

        /*
         * Incluyendo las relaciones del modelo, debemos asegurarnos que estas tambien esten incluidas en array $visible
         * del modelo, y que estamos incluendo el campo de enlace (ID), en las columnas de nuestro Select
         */
        if (!empty($relations))
            $buscador->with($relations);

        /**
         * Incluye las validaciones sobre relaciones en donde obligatoriamente debe existir data para poder retornar el registro correspondiente
         */
        if(!empty($whereHas)) {
            $buscador->where(function($query) use ($whereHas) {
                for($i = 0; $i < count($whereHas); $i++) {
                    $query->whereHas($whereHas[$i]['relation'], $whereHas[$i]['function']);
                }
            });
        }

        /**
         * Agregar el criterio de busqueda 'buscar', al buscador haciendo uso de busquedaGeneral (scopeBusquedaGeneral
         * en el modelo), debemos asegurarnos que este scope este implementado sino obtendremos un error 500 por metodo
         * no definido
         */
        if (isset($request->buscar) && !empty(trim($request->buscar)))
            $buscador->busquedaGeneral(trim($request->buscar));

        if (!empty($filters)) {
            /*
             * Condiciones particulares a tomar en consideracion en el fitrado, lo comun es que se busque que todos los
             * filtros sean incluyentes, por lo que en la mayoria de las veces vamos a colocar nuestros arreglos de criterios
             * en la key 'AND', de todos modos, se esta chequeando que tanto AND como OR existan, a nivel interno cada arreglo
             * sigue el formato de Laravel, el filtro del campo 'buscar' no entra dentro de estas condiciones
             * $filters => [
             *      'AND' => [
             *              ['campo_1', 'condicion', 'value_1'],
             *              ........
             *              ['campo_n', 'condicion', 'value_n']
             *       ],
             *       'OR' => [
             *              ['campo_1', 'condicion', 'value_1'],
             *              ........
             *              ['campo_n', 'condicion', 'value_n']
             *        ]
             * ]
             * Todos se unen con AND
             */
            if (array_key_exists('AND', $filters))
                $buscador->where($filters['AND']);
            if (array_key_exists('OR', $filters)) {
                $buscador->where(function ($query) use ($filters){
                    foreach ($filters['OR'] as $filter)
                        $query->orWhere($filter[0], $filter[1], $filter[2]);
                    return $query;
                });
            }
        }

        // Total Disponible sin los filtros
        $total = $buscador->count();

        if (!empty($queryRaws)) {
            foreach ($queryRaws as $subQuery)
                $buscador->whereRaw($subQuery);
        }

        $exportarExcel = false;
        if (isset($request->excel) && ($request->excel || $request->excel === 'true'))
            $exportarExcel = true;

        /*
         * El modelo debe implementar el scope orderBy
         * Nota: El valor de $start debe ser menor a $filtrados, si lo iguala o supera se retorna una lista vacia
         * de registros
         */
        $columna   = isset($request->columnaOrden) ? $request->columnaOrden : '';
        $direccion = isset($request->ordenDireccion) ? $request->ordenDireccion : '';
        $buscador->orderByColumn($columna, $direccion);

        $length = isset($request->length) ? $request->length : 10;
        $start  = isset($request->start) ? $request->start : 0;

        if ($exportarExcel) {
            // Si se trata de una consulta al modelo de adquirentes se deben omitir para el Excel los registros que existan en ADQUIRENTE_CONSUMIDOR_FINAL
            if(strstr($this->className, 'ConfiguracionAdquirente')) {
                $arrAdquirenteConsumidorFinal = [];
                $adquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'));
                foreach($adquirenteConsumidorFinal as $consumidorFinal) {
                    $arrAdquirenteConsumidorFinal[] = $consumidorFinal->adq_identificacion;
                }
                $buscador->whereNotIn('adq_identificacion', $arrAdquirenteConsumidorFinal);
                if ($request->has('tipo') && $request->tipo == "adquirente")
                    $buscador->skip($start)->take($length);
            } else if (strstr($this->className, 'VariableSistemaTenant')) {
                $bdd_id = ($objUser->bdd_id_rg && $objUser->bdd_id_rg != '') ? $objUser->bdd_id_rg : $objUser->bdd_id;
                $buscador = $buscador->where(function ($query) use ($bdd_id) {
                    $query->whereJsonContains('vsi_bases_datos', $bdd_id)
                        ->orWhereNull('vsi_bases_datos');
                });
            }

            $data = $buscador->get();

            if(strstr($this->className, 'ConfiguracionAdministracionRecepcionErp')) {
                $arrData = [];
                $data = $data->map(function ($item) use (&$arrData) {
                    $arrXpath['xde_descripcion'] = ''; $arrXpathAccion['xde_descripcion'] = ''; 

                    if($item->xde_id_main !== null) {
                        $arrXpath = ParametrosXpathDocumentoElectronico::select(['xde_descripcion'])
                            ->where('xde_id', $item->xde_id_main)
                            ->where('estado', 'ACTIVO')
                            ->first();
                    } elseif($item->xde_id_tenant !== null) {
                        $arrXpath = ParametrosXpathDocumentoElectronicoTenant::select(['xde_descripcion'])
                            ->where('xde_id', $item->xde_id_tenant)
                            ->where('ofe_id', $item->ofe_id)
                            ->where('estado', 'ACTIVO')
                            ->first();
                    }

                    if($item->xde_accion_id_main !== null) {
                        $arrXpathAccion = ParametrosXpathDocumentoElectronico::select(['xde_descripcion'])
                            ->where('xde_id', $item->xde_accion_id_main)
                            ->where('estado', 'ACTIVO')
                            ->first();
                    } elseif($item->xde_accion_id_tenant !== null) {
                        $arrXpathAccion = ParametrosXpathDocumentoElectronicoTenant::select(['xde_descripcion'])
                            ->where('xde_id', $item->xde_accion_id_tenant)
                            ->where('ofe_id', $item->ofe_id)
                            ->where('estado', 'ACTIVO')
                            ->first();
                    }

                    $item->xde_condicion_descripcion = $arrXpath['xde_descripcion'];
                    $item->xde_accion_descripcion    = $arrXpathAccion['xde_descripcion'];

                    $arrData[] = $item;
                });
                $data = collect($arrData);
            }

            if(strstr($this->className, 'EtlFacturacionWebCargo')) {
                $arrData = [];
                $data = $data->map(function ($item) use (&$arrData) {
                    if($item->dmc_aplica_para === '' || $item->dmc_aplica_para === null)
                        $item->dmc_aplica_para = 'DE';
    
                    $arrData[] = $item;
                });
                $data = collect($arrData);
            }

            if(strstr($this->className, 'EtlFacturacionWebDescuento')) {
                $arrData = [];
                $data = $data->map(function ($item) use (&$arrData) {
                    if($item->dmd_aplica_para === '' || $item->dmd_aplica_para === null)
                        $item->dmd_aplica_para = 'DE';
    
                    $arrData[] = $item;
                });
                $data = collect($arrData);
            }

            if(strstr($this->className, 'EtlFacturacionWebProducto')) {
                $arrData = [];
                $data = $data->map(function ($item) use (&$arrData) {
                    if($item->dmp_aplica_para === '' || $item->dmp_aplica_para === null)
                        $item->dmp_aplica_para = 'DE';
    
                    $arrData[] = $item;
                });
                $data = collect($arrData);
            }

            if ($returnData){
                return $data;
            }
            return $this->printExcel($data, $opcionesExportacion, $proceso_background);
        } else {

            if(!$tracking && $origen == "parametrica"){
                $arrRegistros = [];
                $consulta = $buscador->get();

                if(in_array('mun_id', $columns)){
                    // Agrupa para el caso especial Municipios
                    $consulta = $consulta->groupBy(function($item){
                        return $item->pai_id . '~' . $item->dep_id . '~' . $item->mun_codigo;
                    });
                } elseif(in_array('dep_id', $columns)){
                    // Agrupa para el caso especial Departamentos
                    $consulta = $consulta->groupBy(function($item){
                        return $item->pai_id . '~' . $item->dep_codigo;
                    });
                } elseif(in_array('cco_id', $columns)){
                    // Agrupa para el caso especial Conceptos Correción
                    $consulta = $consulta->groupBy(function($item){
                        return $item->cco_tipo . '~' . $item->cco_codigo;
                    });
                } else {
                    $consulta = $consulta->groupBy($columns[1]);
                }

                $consulta = $consulta->map(function ($item) use (&$arrRegistros) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item, false);
                    if($vigente['vigente']){
                        $arrRegistros[] = $vigente['registro'];
                    }
                });
                $filtrados    = count($arrRegistros);
                $total        = count($arrRegistros);

                if($length == -1 || $length == '-1')
                    $length = $total;

                $arrRegistros = array_slice($arrRegistros, $start, $length);
                $registros    = collect($arrRegistros);
            } else {
                if ($origen == 'configuracion_usuarios_ecm') {
                    $arrRegistros = [];
                    $consulta     = $buscador->groupBy('use_id');
                    $arrRegistros = $consulta->get()->toArray();

                    $filtrados    = count($arrRegistros);
                    $total        = count($arrRegistros);

                    if($length == -1 || $length == '-1')
                        $length = $total;

                    $arrRegistros = array_slice($arrRegistros, $start, $length);
                    $registros    = collect($arrRegistros);
                } else if ($origen == 'rep_administracion_transmision_erp') {
                    $arrRegistros = [];
                    $consulta     = $buscador->groupBy('ate_grupo');
                    $arrRegistros = $consulta->get()->toArray();

                    $filtrados    = count($arrRegistros);
                    $total        = count($arrRegistros);

                    if($length == -1 || $length == '-1')
                        $length = $total;

                    $arrRegistros = array_slice($arrRegistros, $start, $length);
                    $registros    = collect($arrRegistros);
                } else if (strstr($this->className, 'VariableSistemaTenant')) {
                    $bdd_id = ($objUser->bdd_id_rg && $objUser->bdd_id_rg != '') ? $objUser->bdd_id_rg : $objUser->bdd_id;
                    $buscador = $buscador->where(function ($query) use ($bdd_id) {
                        $query->whereJsonContains('vsi_bases_datos', $bdd_id)
                            ->orWhereNull('vsi_bases_datos');
                    });

                    $filtrados    = $buscador->count();
                    $total        = $buscador->count();

                    if ($length !== -1 && $length !== '-1')
                        $buscador->skip($start)->take($length);

                    $registros = $buscador->get();
                } else if (strstr($this->className, 'EtlEmailProcesamientoManual')) {
                    $buscador = $buscador->whereBetween('epm_fecha_correo', [$request->fecha_desde.' 00:00:00', $request->fecha_hasta.' 23:59:59']);
                    $arrProveedores = is_array($request->pro_id) ? $request->pro_id : json_decode($request->pro_id);
                    if(!$request->filled('pro_id') || empty($arrProveedores)) {
                        $filtrados = $buscador->count();
    
                        if ($length !== -1 && $length !== '-1')
                            $buscador->skip($start)->take($length);
    
                        $registros = $buscador->get();
                    } else {
                        $proveedores = ConfiguracionProveedor::select(['pro_identificacion'])
                            ->whereIn('pro_id', $arrProveedores)
                            ->where('estado', 'ACTIVO')
                            ->get();
                        $arrProveedores = [];
                        foreach ($proveedores as $proveedor)
                            $arrProveedores[] = $proveedor->pro_identificacion;

                        $registros = [];
                        $buscador = $buscador->get()->map(function ($email) use ($arrProveedores, &$registros) {
                            $arrSubject = explode(';', $email->epm_subject);

                            if(count($arrSubject) >= 5) {
                                $proId = ($arrSubject[0] === 'Soporte') ? $arrSubject[1] : $arrSubject[0];
                                if(in_array($proId, $arrProveedores)) {
                                    $registros[] = $email;
                                }
                            }
                        });
                        $filtrados = count($registros);
                        $total     = count($registros);

                        if($length == -1 || $length == '-1')
                            $length = $total;

                        $arrRegistros = array_slice($registros, $start, $length);
                        $registros    = collect($arrRegistros);
                    }
                } else if (strstr($this->className, 'SistemaRol')) {
                    $arrRoles = $request->filled('arrRoles') && !empty($request->arrRoles) ? $request->arrRoles : []; 
                    $buscador = $buscador->when($request->filled('asignados'), function ($query) use ($request, $arrRoles) {
                        return $query->soloAsignados($request->asignados, $arrRoles);
                    });

                    $filtrados = $buscador->count();
                    if ($length !== -1 && $length !== '-1')
                        $buscador->skip($start)->take($length);

                    $registros = $buscador->where('rol_codigo', '!=', 'superadmin')
                        ->get();

                } else {
                    $filtrados = $buscador->count();
                    if ($length !== -1 && $length !== '-1')
                        $buscador->skip($start)->take($length);

                    $registros = $buscador->get();
                }
            }

            if(strstr($this->className, 'ConfiguracionAdquirente') || strstr($this->className, 'ConfiguracionObligadoFacturarElectronicamente') || strstr($this->className, 'ConfiguracionProveedor')) {
                $registros = $registros->transform(function($registro) {
                    if(isset($registro->getTributos) && !empty($registro->getTributos)) {
                        $tributo = $registro->getTributos->transform(function($tributo) {
                            if(isset($tributo->getDetalleTributo) && !empty($tributo->getDetalleTributo) && $tributo->getDetalleTributo->tri_codigo == 'ZZ') {
                                $tributo->getDetalleTributo->tri_nombre = ' No Aplica';
                            }

                            return $tributo;
                        });
                    }

                    return $registro;
                });
            }

            if(strstr($this->className, 'ConfiguracionObligadoFacturarElectronicamente') || strstr($this->className, 'ConfiguracionAdquirente') || strstr($this->className, 'ConfiguracionProveedor')) {
                $newRegistros = $registros->toArray();
                $registros = [];
                foreach($newRegistros as $registro) {
                    $registro['get_responsabilidad_fiscal'] = $this->listarResponsabilidadesFiscales($registro['ref_id']);
                    $registros[] = $registro;
                }
            }

            $data_response = [
                'total'     => $total,
                'filtrados' => $filtrados,
                'data'      => $registros
            ];

            if (!$returnData)
                return response()->json($data_response, Response::HTTP_OK);
            else
                return $data_response;
        }
    }

    /**
     * Obtiene las responsabilidades fiscales dado un string de códigos separados por puntos y comas (;).
     * 
     * @param string $refId Codigos de responsabilidades fiscales separados por comas
     * @param array $responsabilidadesFiscales Array con la información de las responsabilidades fiscales
     */
    public function listarResponsabilidadesFiscales($refId) {
        $responsabilidades_fiscales = [];
        if (!empty($refId)) {
            $codigos_responsabilidades_fiscales = explode(';', $refId);
            if (!empty($codigos_responsabilidades_fiscales)) {
                $arrResponsabilidadesFiscales = [];
                $responsabilidadesFiscales = ParametrosResponsabilidadFiscal::select([
                        'ref_id',
                        'ref_codigo',
                        'ref_descripcion',
                        'fecha_vigencia_desde',
                        'fecha_vigencia_hasta',
                        DB::raw('CONCAT(ref_codigo, " - ", ref_descripcion) as ref_codigo_descripion'),
                        'estado'
                    ])
                    ->whereIn('ref_codigo', $codigos_responsabilidades_fiscales)
                    ->get()
                    ->groupBy('ref_codigo')
                    ->map( function($item) use (&$arrResponsabilidadesFiscales) {
                        $vigente = $this->validarVigenciaRegistroParametrica($item, false);
                        if ($vigente['vigente']) {
                            $arrResponsabilidadesFiscales[] = $vigente['registro'];
                        }
                    });
            }
        }

        return !empty($arrResponsabilidadesFiscales) ? $arrResponsabilidadesFiscales : null;
    }

    /**
     * @param array $data Datos que se va a imprimir
     * @param array $opcionesExportacion Títulos de las columnas
     * @param bool  $proceso_background Indica si se trata de un proceso en background o no para retornar la descarga del archivo o solo el nombre del archivo
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    protected function printExcel($data, array $opcionesExportacion, bool $proceso_background = false){
        /*
         * $opcionesExportacion = [
         *      'titulo' => 'nombre en plural del modelo'
         *      'titulosColumnas' => ['titulo1', 'titulo2', ...., 'titulo2']
         * ]
         */
        $columns = array_key_exists('columnas', $opcionesExportacion) ? $opcionesExportacion['columnas'] : [];
        $nombre = array_key_exists('titulo', $opcionesExportacion) ? $opcionesExportacion['titulo'] : '';

        $titulos = [];
        $data = $this->prepareDataToExcel($data, $columns, $titulos);
        
        date_default_timezone_set('America/Bogota');
        $nombreArchivo = mb_strtolower($nombre) . '_' . date('YmdHis');
        $archivoExcel = $this->toExcel($titulos, $data, $nombreArchivo);

        if(!$proceso_background) {
            $headers = [
                header('Access-Control-Expose-Headers: Content-Disposition')
            ];

            return response()
                ->download($archivoExcel, $nombreArchivo . '.xlsx', $headers)
                ->deleteFileAfterSend(true);
        } else {
            return [
                'ruta'   => $archivoExcel,
                'nombre' => $nombreArchivo . '.xlsx'
            ];
        }
    }

    /**
     * Configura la data a exportar con el formato requerido para ser presetado en archivo de Excel
     *   [
     *       'cde_codigo' => 'Código',
     *       'cde_descripcion' => 'Descripcion',
     *       'usuario_creacion' => [
     *           'label' => 'Usuarios Creacion',
     *           'relation' => [
     *               'name' => 'get_user',
     *               'field' => 'usu_nombre'
     *            ]
     *       ],
     *       'fecha_creacion' => 'Fecha Creación',
     *       'fecha_modificacion' => 'Fecha Modificación',
     *       'estado' => 'Estado'
     *   ]
     *
     * @param $registros
     * @param array $columnas
     * @param array $titulos
     * @return array
     */
    private function prepareDataToExcel($registros, array $columnas, array &$titulos) {
        $rows = [];
        $keys = array_keys($columnas);
        foreach ($keys as $col) {
            if (is_array($columnas[$col]) && array_key_exists('multiple', $columnas[$col])) {
                if (is_array($columnas[$col]['fields'])) {
                    foreach ($columnas[$col]['fields'] as $field)
                        $titulos[] = mb_strtoupper($field['label']);
                }
            } elseif(is_array($columnas[$col]) && array_key_exists('columnas_personalizadas', $columnas[$col]) && $columnas[$col]['columnas_personalizadas']) {
                foreach($columnas[$col]['fields'] as $columnaPersonalizada)
                    $titulos[] = mb_strtoupper($columnaPersonalizada);
            } else
                $titulos[] = mb_strtoupper(is_array($columnas[$col]) ?  $columnas[$col]['label'] : $columnas[$col]);
        }

        foreach ($registros as $item) {
            $item = (object) $item;
            $newrow = [];
            foreach ($keys as $col) {
                if (is_array($columnas[$col])) {
                    if (array_key_exists('multiple', $columnas[$col])) {
                        if (is_array($columnas[$col]['fields'])) {
                            foreach ($columnas[$col]['fields'] as $field) {
                                if (isset($item->{$columnas[$col]['relation']})) {
                                    $newrow[] = isset($item->{$columnas[$col]['relation']}) && !is_null($item->{$columnas[$col]['relation']}) ? $item->{$columnas[$col]['relation']}->{$field['field']} : '';
                                } else {
                                    $newrow[] = '';
                                }
                            }
                        }
                    } elseif (array_key_exists('callback', $columnas[$col])){
                        $extraDatos = [];
                        if (is_array($columnas[$col]['fields'])) {
                            foreach ($columnas[$col]['fields'] as $field) {
                                if (isset($item->{$columnas[$col]['relation']})) {
                                    if ($item->{$columnas[$col]['relation']}->count() == 1) {
                                        $extraDatos[] = isset($item->{$columnas[$col]['relation']}) && !is_null($item->{$columnas[$col]['relation']}) ? $item->{$columnas[$col]['relation']}->first()->{$field['field']} : '';
                                    } else if ($item->{$columnas[$col]['relation']}->count() > 1) {
                                        $datos = isset($item->{$columnas[$col]['relation']}) && !is_null($item->{$columnas[$col]['relation']}) ? $item->{$columnas[$col]['relation']} : [];

                                        if (!empty($datos)) {
                                            foreach ($datos as $data) {
                                                $extraDatos[] = $data->{$field['field']} ?? '';
                                            }
                                        }
                                    }
                                } else {
                                    $extraDatos = $item->{$field['field']} ?? '';
                                }
                            }

                            if (is_callable($columnas[$col]['function'])){
                                $newrow[] = (string) $columnas[$col]['function']($extraDatos);
                            }
                        }
                    } elseif(array_key_exists('columnas_personalizadas', $columnas[$col]) && $columnas[$col]['columnas_personalizadas']) {
                        if (is_array($columnas[$col]['fields'])) {
                            foreach($columnas[$col]['fields'] as $columnaPersonalizada) {
                                if(isset($item->adq_informacion_personalizada) && !empty($item->adq_informacion_personalizada)) {
                                    $informacionPersonalizada = json_decode($item->adq_informacion_personalizada, true);
                                    $newrow[] = array_key_exists($columnaPersonalizada, $informacionPersonalizada) ? $informacionPersonalizada[$columnaPersonalizada] : '';
                                } else {
                                    $newrow[] = '';
                                }
                            }
                        }
                    } elseif (array_key_exists('inner_relation', $columnas[$col])) {
                        $value = '';
                        if (array_key_exists('origin_relation', $columnas[$col]) &&
                            array_key_exists('destiny_relation', $columnas[$col]) &&
                            $item->{$columnas[$col]['origin_relation']['name']} &&
                            $item->{$columnas[$col]['origin_relation']['name']}->{$columnas[$col]['destiny_relation']['name']}
                        ) {
                            $value = $item->{$columnas[$col]['origin_relation']['name']}
                                ->{$columnas[$col]['destiny_relation']['name']}
                                ->{$columnas[$col]['destiny_relation']['field']};
                        }
                        
                        $newrow[] = $value;
                    } else {
                        $value = null;
                        // Es una relacion
                        if (array_key_exists('relation', $columnas[$col])) {
                            if ($item->{$columnas[$col]['relation']['name']})
                                $value = $item->{$columnas[$col]['relation']['name']}->{$columnas[$col]['relation']['field']};
                        }
                        else
                            $value = $item->{$col};
                        // Se requiere un formateo previo
                        if (array_key_exists('type', $columnas[$col]))
                            $newrow[] = $this->getValueFrom($value, $columnas[$col]['type']);
                        else
                            $newrow[] = $value ?? '';
                    }
                }
                else
                    $newrow[] = $item->{$col} ?? '';
            }
            $rows[] = $newrow;
        }
        return $rows;
    }

    /**
     * encontrar registros por busqueda predictiva
     *
     * @param Request $Request contiene todos los datos de la petición
     * @param string $valueToSearch valor a buscar para realizar el filtrado.
     * @param array $SearchByColumns columnas en la cual se realizara la busqueda
     * @param array $selectColumns columnas que se seleccionan para la respuesta
     * @param array $relations relaciones que puede tener el modelo
     * @param int $limit limite de resultado a responder
     * @param bool $allowDisabled permite obtener datos que se escuentran inactivos
     * @return JsonResponse
     */
    protected function simplepredictive(Request $Request, string $valueToSearch, array $SearchByColumns, array $selectColumns, array $relations = [], int $limit = 10, bool $allowDisabled = false) {
        $objectoModel = $this->className::select($selectColumns)->with($relations);

        if ($allowDisabled){
            $objectoModel->where('estado', 'ACTIVO');
        }

        foreach ($SearchByColumns as $key => $column) {
            if ($key == 0) {
                $objectoModel->where($column, 'like', "%{$valueToSearch}%");
            }
            else {
                $objectoModel->orwhere($column, 'like', "%{$valueToSearch}%");
            }
        }

        return response()->json([
            'data' => DB::select( $objectoModel->toSql() . " LIMIT 0,{$limit}" , $objectoModel->getBindings())
        ], Response::HTTP_OK);
    }

    /**
     * Formatea un objeto para poder ser escrito en el excel.
     *
     * @param $value
     * @param string $format
     * @return string
     */
    private function getValueFrom($value, string $format) {
        $str = '';
        if ($value) {
            switch ($format) {
                case self::TYPE_CARBON:
                    $str = $value->toDateString();
                    break;
                case self::TYPE_ARRAY:
                    if(!empty($value) && is_array($value))
                        $str = implode('|', $value);
                    else
                        $str = '';
                    break;
                case self::TYPE_ARRAY_OBJECTS:
                    // Estas líneas aplican para poder escribir la información de usuarios autorizados para gestión de documentos recibidos del modelo proveedores
                    if(strstr($this->className, 'ConfiguracionProveedor')) {
                        $str = '';
                        if(!empty($value) && is_array($value)) {
                            foreach($value as $usuario) {
                                if(is_object($usuario) && isset($usuario->usu_identificacion) && !empty($usuario->usu_identificacion))
                                    $str .= $usuario->usu_identificacion . ',';
                                else
                                    $str .= $usuario . ',';
                            }
                            $str = substr($str, 0, strlen($str) - 1);
                        } else
                            $str = '';
                    }
                    
                    break;
            }
        }
        return $str;
    }

    /**
     * Agrega una condicion a la lista de condiciones.
     *
     * @param Request $request
     * @param array $array
     * @param string $key
     * @param string $condicion
     */
    public function appendCondition(Request $request, array &$array, string $key, string $condicion) {
        if ($request->has($key))
            $array[] = [$key, $condicion, $request->{$key}];
    }

    /**
     * Efectua un proceso de busqueda basica, detalla o avanazada sobre una parametrica en particuular
     *
     * @param Request $request
     * @param array $select Campos a seleccionar en la consulta
     * @param array $relaciones Relaciones del modelo
     * @param array $condiciones Condiciones para incluir en la consulta
     * @param array $dominioBlanco Limitar los registros de búsqueda
     * @param array $filters Filtros de búsqueda, se envían los índices AND o OR
     * @return JsonResponse
     */
    public function procesadorBusqueda(Request $request, array $select = [], array $relaciones = [], array $condiciones = [], array $dominioBlanco = [], array $filters = []) {
            $campoBuscar = isset($request->campoBuscar) ? $request->campoBuscar : '';
            $valorBuscar = isset($request->valorBuscar) ? $request->valorBuscar : '';
            $filtroColumnas = isset($request->filtroColumnas) ? $request->filtroColumnas : '';
            switch ($filtroColumnas) {
                case 'basico':
                    $resultado = $this->className::where($campoBuscar, 'like', '%' . $valorBuscar . '%')
                        ->where('estado', 'ACTIVO')
                        ->select($select);
                    break;

                case 'avanzado':
                    break;

                case 'exacto':
                    $resultado = $this->className::where($campoBuscar, $valorBuscar)
                        ->where('estado', 'ACTIVO')
                        ->select($select);
                    break;
            }

            if (!empty($filters)) {
                /*
                 * Condiciones particulares a tomar en consideracion en el fitrado, lo comun es que se busque que todos los
                 * filtros sean incluyentes, por lo que en la mayoria de las veces vamos a colocar nuestros arreglos de criterios
                 * en la key 'AND', de todos modos, se esta chequeando que tanto AND como OR existan, a nivel interno cada arreglo
                 * sigue el formato de Laravel, el filtro del campo 'buscar' no entra dentro de estas condiciones
                 * $filters => [
                 *      'AND' => [
                 *              ['campo_1', 'condicion', 'value_1'],
                 *              ........
                 *              ['campo_n', 'condicion', 'value_n']
                 *       ],
                 *       'OR' => [
                 *              ['campo_1', 'condicion', 'value_1'],
                 *              ........
                 *              ['campo_n', 'condicion', 'value_n']
                 *        ]
                 * ]
                 * Todos se unen con AND
                 */
                if (array_key_exists('AND', $filters))
                    $resultado->where($filters['AND']);
                if (array_key_exists('OR', $filters)) {
                    $resultado->where(function ($query) use ($filters){
                        foreach ($filters['OR'] as $filter)
                            $query->orWhere($filter[0], $filter[1], $filter[2]);
                        return $query;
                    });
                }
            }

            if (isset($resultado)) {
                if (!empty($relaciones))
                    $resultado->with($relaciones);

                if (!empty($condiciones) && array_key_exists(1, $condiciones[0]) && $condiciones[0][1] == 'IN') {
                    $resultado->whereIn($condiciones[0][0], $condiciones[0][2]);
                } elseif (!empty($condiciones) && array_key_exists(1, $condiciones[0]) && $condiciones[0][1] != 'IN') {
                    $resultado->where($condiciones);
                }

                if(!in_array($campoBuscar, $dominioBlanco))
                    $resultado->take($this->limitBusquedas);
                $resultado = $resultado->get();

                if(strstr($this->className, 'ConfiguracionAdquirente') || strstr($this->className, 'ConfiguracionObligadoFacturarElectronicamente') || strstr($this->className, 'ConfiguracionProveedor')) {
                    $resultado = $resultado->transform(function($registro) {
                        if(isset($registro->getTributos) && !empty($registro->getTributos)) {
                            $tributo = $registro->getTributos->transform(function($tributo) {
                                if(isset($tributo->getDetalleTributo) && !empty($tributo->getDetalleTributo) && $tributo->getDetalleTributo->tri_codigo == 'ZZ') {
                                    $tributo->getDetalleTributo->tri_nombre = ' No Aplica';
                                }
    
                                return $tributo;
                            });
                        }
    
                        return $registro;
                    });
                }

                if(
                    strstr($this->className, 'ConfiguracionObligadoFacturarElectronicamente') ||
                    strstr($this->className, 'ConfiguracionAdquirente') ||
                    strstr($this->className, 'ConfiguracionProveedor')
                ) {
                    $newResultado = $resultado->toArray();
                    $resultado = [];
                    foreach($newResultado as $registro) {
                        $registro['get_responsabilidad_fiscal'] = $this->listarResponsabilidadesFiscales($registro['ref_id']);
                        $resultado[] = $registro;
                    }
                } else {
                    if(
                        !strstr($this->className, 'ConfiguracionSoftwareProveedorTecnologico') &&
                        !strstr($this->className, 'EtlConsecutivoDocumentoDaop')
                    ) {
                        $resultadoRegistros = [];
                        if(strstr($this->className, 'ParametrosDepartamento'))
                            $resultado = $resultado->groupBy(function($item) {
                                return $item->pai_id . '~' . $item->dep_codigo;
                            });
                        elseif(strstr($this->className, 'ParametrosMunicipio'))
                            $resultado = $resultado->groupBy(function($item) {
                                return $item->pai_id . '~' . $item->dep_id . '~' . $item->mun_codigo;
                            });
                        elseif(strstr($this->className, 'ParametrosConceptoCorreccion'))
                            $resultado = $resultado->groupBy(function($item) {
                                return $item->cco_tipo . '~' . $item->cco_codigo;
                            });
                        elseif(strstr($this->className, 'ParametrosTipoOperacion'))
                            $resultado = $resultado->groupBy(function($item) {
                                return $item->top_aplica_para . '~' . $item->top_codigo;
                            });
                        else
                            $resultado = $resultado->groupBy($this->nombreCampoIdentificacion);

                        $resultado->map(function ($item) use (&$resultadoRegistros) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if ($vigente['vigente']) {
                                $resultadoRegistros[] = $vigente['registro'];
                            }
                        });
                        $resultado = $resultadoRegistros;
                    }
                }
            } else
                $resultado = [];

            return response()->json([
                'data' => $resultado,
            ], Response::HTTP_OK);
    }
}
