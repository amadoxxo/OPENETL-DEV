<?php
namespace App\Http\Controllers;

use App\Traits\MainTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Commons\CommonsController;
use App\Http\Modulos\Utils\ExcelExports\ExcelExport;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmision;

/**
 * Controlador Base para los procesos de Tenant en OpenETL
 *
 * Class OpenTenantController
 * @package App\Http\Controllers
 */
class OpenTenantController extends OpenBaseController
{
    /**
     * @var string Nombre del campo que contiene los valores de identificacion de los registros a modificar.
     */
    public $nombreDatoCambiarEstado;


    public function __construct() {
        parent::__construct();
    }

    /**
     * Muestra un registro en particular.
     *
     * @param mixed $id
     * @param array $relations
     * @return Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function procesadorShow($id, array $relations, bool $returnData=false){
        $objetoModel = null;
        if (is_null($this->nombreCampoIdentificacion)) {
            if (!empty($relations))
                $objetoModel = $this->className::with($relations)->find($id);
            else
                $objetoModel = $this->className::find($id);
        } else {
            if (!empty($relations))
                $objetoModel = $this->className::with($relations)->where($this->nombreCampoIdentificacion, $id)->first();
            else
                $objetoModel = $this->className::where($this->nombreCampoIdentificacion, $id)->first();
        }
        if ($objetoModel){
            $objetoModel = $objetoModel->toArray();
            // Si el modelo a recuperar pertenece a un Ofe y este no escogió representación gráfica personalizada se obtiene la imagen del logo para devolverla en Base64
            if (strpos($this->className, 'ConfiguracionObligadoFacturarElectronicamente') !== false && ($objetoModel['ofe_tiene_representacion_grafica_personalizada'] === 'NO' || $objetoModel['ofe_cadisoft_activo'] == 'SI')) {
                MainTrait::setFilesystemsInfo();
                $disk = config('variables_sistema.ETL_LOGOS_STORAGE');
                $bdd = (!empty(auth()->user()->bdd_id_rg)) ? auth()->user()->getBaseDatosRg->bdd_nombre : auth()->user()->getBaseDatos->bdd_nombre;
                $bdd = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdd);
                $file = $bdd . '/' . $objetoModel['ofe_identificacion'] . '/assets/' . 'logo' . $objetoModel['ofe_identificacion'] . '.png';
                if (Storage::disk($disk)->exists($file)) {
                    $contenido = Storage::disk($disk)->get($file);
                    $f = finfo_open();
                    $mime_type = finfo_buffer($f, $contenido, FILEINFO_MIME_TYPE);
                    $data_uri = "data:". $mime_type . ";base64," . base64_encode($contenido);
                    if($objetoModel['ofe_cadisoft_activo'] == 'SI') {
                        $objetoModel['logoCadisoft'] = $data_uri;
                    }
                    $objetoModel['logo'] = $data_uri;
                }
            }
            if (strpos($this->className, 'ConfiguracionObligadoFacturarElectronicamente') !== false && $objetoModel['ofe_tiene_representacion_grafica_personalizada_ds'] === 'NO') {
                MainTrait::setFilesystemsInfo();
                $disk = config('variables_sistema.ETL_LOGOS_STORAGE');
                $bdd = (!empty(auth()->user()->bdd_id_rg)) ? auth()->user()->getBaseDatosRg->bdd_nombre : auth()->user()->getBaseDatos->bdd_nombre;
                $bdd = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdd);
                $fileDs = $bdd . '/' . $objetoModel['ofe_identificacion'] . '/assets/' . 'logo' . $objetoModel['ofe_identificacion'] . '_ds.png';
                if (Storage::disk($disk)->exists($fileDs)) {
                    $contenidoDs = Storage::disk($disk)->get($fileDs);
                    $fDs = finfo_open();
                    $mime_type_ds = finfo_buffer($fDs, $contenidoDs, FILEINFO_MIME_TYPE);
                    $data_uri_ds = "data:". $mime_type_ds . ";base64," . base64_encode($contenidoDs);
                    $objetoModel['logo_ds'] = $data_uri_ds;
                }
            }
            if(strpos($this->className, 'ConfiguracionObligadoFacturarElectronicamente') !== false && !empty($objetoModel['ofe_datos_documentos_manuales'])) {
                $commonsController = new CommonsController();

                $datosFacturacionWeb = [];
                $tmpDatosFacturacionWeb = json_decode($objetoModel['ofe_datos_documentos_manuales'], true);
                foreach ($tmpDatosFacturacionWeb as $index => $datoManual) {
                    if(array_key_exists('tipo', $datoManual) && $datoManual['tipo'] == 'PARAMETRO') {
                        if($datoManual['tabla'] == 'etl_resoluciones_facturacion') {
                            $resolucion = ConfiguracionResolucionesFacturacion::select(['rfa_prefijo', 'rfa_resolucion'])
                                ->where('rfa_id', $datoManual['valor'])
                                ->first();

                            $datoManual['valor_descripcion'] = $resolucion ? $resolucion->rfa_prefijo . ' - ' . $resolucion->rfa_resolucion : '';
                        } elseif ($datoManual['tabla'] == 'etl_forma_generacion_transmision') {
                            $resolucion = ParametrosFormaGeneracionTransmision::select(['fgt_codigo', 'fgt_descripcion'])
                                ->where('fgt_id', $datoManual['valor'])
                                ->first();

                            $datoManual['valor_descripcion'] = $resolucion ? $resolucion->fgt_codigo . ' - ' . $resolucion->fgt_descripcion : '';
                        } else {
                            $top_aplica_para = '';
                            if($datoManual['tabla'] == 'etl_tipos_operacion') {
                                if(strstr($datoManual['descripcion'], 'Crédito'))
                                    $top_aplica_para = 'NC';
                                elseif(strstr($datoManual['descripcion'], 'Débito'))
                                    $top_aplica_para = 'ND';
                                elseif(strstr($datoManual['descripcion'], 'Factura'))
                                    $top_aplica_para = 'FC';
                            }


                            $newRequest = new Request();
                            $newRequest->merge([
                                'tabla'  => $datoManual['tabla'],
                                'campo'  => $datoManual['campo'],
                                'valor'  => $datoManual['valor'],
                                'ofe_id' => $datoManual['tabla'] == 'etl_resoluciones_facturacion' ? $ofe->ofe_id : '',
                                'top_aplica_para' => $datoManual['tabla'] == 'etl_tipos_operacion' ? $top_aplica_para :''
                            ]);

                            $consulta = $commonsController->searchParametricas($newRequest);
                            $status   = $consulta->getStatusCode();
                            $consulta = json_decode((string)$consulta->getContent(), true);

                            if($status == 200) {
                                if($datoManual['tabla'] == 'etl_clasificacion_productos' && array_key_exists('0', $consulta['data'])) {
                                    $datoManual['valor_descripcion'] = $consulta['data'][0]['cpr_nombre'];
                                } elseif($datoManual['tabla'] == 'etl_resoluciones_facturacion' && array_key_exists('0', $consulta['data'])) {
                                    $datoManual['valor_descripcion'] = (!empty($consulta['data'][0]['rfa_prefijo']) ? $consulta['data'][0]['rfa_prefijo'] . ' - ' : '') . $consulta['data'][0]['rfa_resolucion'];
                                } elseif($datoManual['tabla'] == 'etl_forma_generacion_transmision' && array_key_exists('0', $consulta['data'])) {
                                    $datoManual['valor_descripcion'] = (!empty($consulta['data'][0]['fgt_codigo']) ? $consulta['data'][0]['fgt_codigo'] . ' - ' : '') . $consulta['data'][0]['fgt_descripcion'];
                                } elseif($datoManual['tabla'] != 'etl_resoluciones_facturacion' && $datoManual['tabla'] != 'etl_clasificacion_productos' && $datoManual['tabla'] != 'etl_forma_generacion_transmision' && array_key_exists('0', $consulta['data'])) {
                                    $datoManual['valor_descripcion'] = $consulta['data'][0]['descripcion'];
                                } else {
                                    $datoManual['valor_descripcion'] = '';
                                }
                            } else {
                                $datoManual['valor_descripcion'] = '';
                            }
                        }
                    }

                    $datosFacturacionWeb[] = $datoManual;
                }

                if(!empty($datosFacturacionWeb))
                    $objetoModel['ofe_datos_documentos_manuales'] = $datosFacturacionWeb;
            }

            if(strstr($this->className, 'ConfiguracionObligadoFacturarElectronicamente')) {
                $responsabilidades_fiscales = [];
                if (!empty($objetoModel['ref_id'])) {
                    $responsabilidades_fiscales = $this->listarResponsabilidadesFiscales($objetoModel['ref_id']);
                }
                $objetoModel['responsabilidades_fiscales'] = $responsabilidades_fiscales;
            }

            if ($returnData && !empty($objetoModel)) {
                return $objetoModel;
            }

            return response()->json([
                'data' => $objetoModel
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => is_null($this->nombreCampoIdentificacion) ? "No Se Encontró {$this->nombre} Con ID [{$id}]" : "No Se Encontró {$this->nombre} Con identificación [{$id}]",
            'errors' => []
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Muestra solo un registro.
     *
     * @param string $masterModel
     * @param mixed $master
     * @param mixed $slave
     * @param array $relations
     * @param array $additionalContions
     * @param string $errorMasterNotFound
     * @param string $errorSlaveNotFound
     * @return Response
     */
    public function procesadorShowCompuesto(string $masterModel, $master, $slave, array $relations, array $additionalContions,
                                            string $errorMasterNotFound, string $errorSlaveNotFound){

        $objMaster = $masterModel::select([$master['id_key']])
            ->where($master['property'],  $master['value'])
            ->where('estado', 'ACTIVO')
            ->first();

        if ($objMaster === null) {
            return response()->json([
                'message' =>sprintf($errorMasterNotFound, $master['value']),
                'errors' => []
            ], Response::HTTP_NOT_FOUND);
        }

        if (!empty($relations))
            $objetoModel = $this->className::with($relations)
                ->where($master['id_key'],  $objMaster->{$master['id_key']})
                ->where($slave['property'],  $slave['value'])
                ->where($additionalContions)
                ->first();
        else
            $objetoModel = $this->className::where($master['id_key'],  $objMaster->{$master['id_key']})
                ->where($slave['property'],  $slave['value'])
                ->where($additionalContions)
                ->first();

        if ($objetoModel){
            return response()->json([
                'data' => $objetoModel
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => sprintf($errorSlaveNotFound, $slave['value']),
            'errors' => []
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Cambia el estado de una lista de objetos.
     *
     * @param string $masterModel
     * @param string $masterKey
     * @param string $master
     * @param string $slaveKey
     * @param string $collection
     * @param array $arrObjetos
     * @param string $errorMasterNotFound
     * @return JsonResponse
     */
    public function procesadorCambiarEstadoCompuesto(string $masterModel, string $masterKey, string $master, string $slaveKey, string  $collection, array $arrObjetos, string $errorMasterNotFound){
        $arrErrores = [];
        foreach ($arrObjetos as $objeto) {
            if(empty($objeto))
                continue;
            if (is_array($objeto))
                $objeto = (object)$objeto;

            $objMaster = $masterModel::where($master, $objeto->{$master})
                ->where('estado', 'ACTIVO');

            if($masterKey == 'ofe_id') {
                $objMaster = $objMaster->select([$masterKey, 'ofe_identificador_unico_adquirente'])
                    ->when(!empty(auth()->user()->bdd_id_rg), function ($query) {
                        return $query->where('bdd_id_rg', auth()->user()->bdd_id_rg);
                    }, function ($query) {
                        return $query->whereNull('bdd_id_rg');
                    });
            } else
                $objMaster = $objMaster->select([$masterKey]);

            $objMaster = $objMaster->first();

            if ($objMaster === null)
                $arrErrores[] = sprintf($errorMasterNotFound, $objeto->$master);
            else {
                $items = explode(',', $objeto->{$collection});
                foreach($items as $idObj){
                    // No se debe permitir cambiar el estado de un adquirente que exista en la variable del sistema ADQUIRENTE_CONSUMIDOR_FINAL
                    $cambiarEstado = true; 
                    if($slaveKey == 'adq_identificacion') {
                        $infoAdquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'), true);
                        foreach($infoAdquirenteConsumidorFinal as $adqConsumidorFinal) {
                            if($adqConsumidorFinal['adq_identificacion'] == $idObj) {
                                $cambiarEstado = false;
                                $this->adicionarErrorArray($arrErrores, ["El {$this->nombre} [{$idObj}] es un Adquirente Consumidor Final y no se puede cambiar su estado"]);
                            }
                        }
                    }

                    if($cambiarEstado) {
                        $modelo = $this->className::where($masterKey, $objMaster->{$masterKey})
                            ->where($slaveKey, $idObj);

                        // Para los adquirentes se debe verificar si el OFE tiene configurada la llave adq_id_personalizado en la columna ofe_identificador_unico_adquirente
                        // de modo tal que si dicha configuración existe debe ser tenida en cuenta para ubicar el registro que cambiará de estado
                        if($slaveKey == 'adq_identificacion') {
                            if(
                                !empty($objMaster->ofe_identificador_unico_adquirente) &&
                                in_array('adq_id_personalizado', $objMaster->ofe_identificador_unico_adquirente)
                            ) {
                                if(isset($objeto->adq_id_personalizado) && !empty($objeto->adq_id_personalizado))
                                    $modelo = $modelo->where('adq_id_personalizado', $objeto->adq_id_personalizado);
                                else
                                    $modelo = $modelo->whereNull('adq_id_personalizado');
                            }
                        }
                            
                        $modelo = $modelo->first();

                        if($modelo){
                            $strEstado = ($modelo->estado == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                            $modelo->update(['estado' => $strEstado]);

                            if(!$modelo)
                                $this->adicionarErrorArray($arrErrores, ["Errores Al Actualizar {$this->nombre} [{$idObj}]"]);
                        } else
                            $this->adicionarErrorArray($arrErrores, ["El {$this->nombre} [{$idObj}] No Existe."]);
                    }
                }
            }
        }

        if(empty($arrErrores)) {
            return response()->json([
                'success' => true
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'message' => "Error Al Cambiar El Estado De {$this->nombrePlural} Seleccionados",
                'errors' => $arrErrores
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Cambia el estado de una lista de objetos seleccionados
     *
     * @param Request $request   Parametros de los métodos que lo invocan
     * @param array $extraErrors Errores extra que se pueden obtener en el proceso y que pueden llegar
     * @return JsonResponse
     */
    public function procesadorCambiarEstado(Request $request, array $extraErrors = []): JsonResponse {
        if (!isset($request->{$this->nombreDatoCambiarEstado}) || is_null($this->nombreDatoCambiarEstado))
            return response()->json([
                'message' => "No se ha proporcionado el campo {$this->nombreDatoCambiarEstado} que contiene los identificadores a modificar",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        $arrObjetos  = explode(',', $request->{$this->nombreDatoCambiarEstado});
        $arrErrores = $extraErrors;

        foreach($arrObjetos as $identificadorObj){
            if (strpos($this->className, 'ConfiguracionSoftwareProveedorTecnologico') !== false) {
                $objeto = $this->className::find($identificadorObj);
            } else {
                $objeto = $this->className::where($this->nombreCampoIdentificacion, $identificadorObj)->first();
            }
            if($objeto){
                $strEstado = ($objeto->estado == 'ACTIVO' ? 'INACTIVO' : 'ACTIVO');
                $objeto->update(['estado' => $strEstado]);

                if(!$objeto)
                    $this->adicionarErrorArray($arrErrores, ["Errores Al Actualizar {$this->nombre} [{$identificadorObj}]"]);
            } else
                $this->adicionarErrorArray($arrErrores, ["El Id {$this->nombre} [{$identificadorObj}] No Existe."]);
        }

        if(empty($arrErrores)) {
            return response()->json([
                'success' => true
            ], Response::HTTP_OK);
        } else {
            return response()->json([
                'message' => "Error Al Cambiar El Estado De {$this->nombrePlural} Seleccionados",
                'errors' => $arrErrores
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Almacena los objetos en las tablas de una Tenant dado un conjuto de reglas para un modelo Maestro - Esclavo (Pueden
     * ser de multiples, y puede tratarse de objetos o colecciones de objetos); como en el caso del simple storage
     * tambien debe de tomarse en consideracion los objetos de gestión para master keys y foreign keys heradados de
     * OpenBaseController
     *
     * @param Request $request
     * @param array $rules Describe la manera en que sera procesada la dada
     *       [
     *            "master": [
     *                  "className": "Nombre del Modelo Maestro",
     *                  "fields": ["campo1", "campo2", ... , "campoN"]
     *            ],
     *            "slaves": [
     *                  "item1": [
     *                          "className": "Nombre del Modelo Esclavo",
     *                          "fields": ["campo1", "campo2", ... , "campoN"],
     *                          "type": "object|array",
     *                          "foreign_keys": [Funciona similar que en procesadorSimpleStore]
     *                   ] , ...
     *             ]
     *       ]
     * @param string $obj_id Nombre del campo clave del modelo maestro
     */
    public function saveToTenant(Request $request, array $rules, string $obj_id) {
        $master = $rules['master'];
        $slaves = $rules['slaves'];
    }

    /**
     * @param Request $request
     * @param int $id
     * @param array $rules
     * @param string $obj_id
     */
    public function updateToTenant(Request $request, int $id, array $rules, string $obj_id) {

    }

    public function cargaMasivaToTenant() {

    }

    /**
     * Genera una Interfaz para Carga masiva dada una configuración de columnas
     *
     * @param array $columnas Títulos de las columnas de Excel
     * @param string $titulo Título con el que se construira el archivo
     * @return ExcelExport
     */
    public function generarInterfaceToTenant(array $columnas, string $titulo) {
        header('Access-Control-Expose-Headers: Content-Disposition');
        date_default_timezone_set('America/Bogota');
        $nombreArchivo = $titulo . '_' . date('YmdHis');
        $__columnas = [];
        foreach ($columnas as $col)
            $__columnas[] = mb_strtoupper($col);
        return new ExcelExport($nombreArchivo, $__columnas);
    }

    /**
     * Mecanismo de busqueda en base a un criterio predictivo, aplica para ofes, prov y adqs
     *
     * @param string $className Nombre del modelo sobre el que se efectura la busqueda
     * @param string $prefix Prefijo que se aplicara a los campos del modelo
     * @param string $valorBuscar Criterio de busqueda
     * @param int $ofe_id ID de oferente, opcional
     * @return Response
     */
    public function buscarNgSelect(string $className, string $prefix, string $valorBuscar, $ofe_id = -1){
        $comodin = "'%$valorBuscar%'";
        $sqlRaw = "({$prefix}_razon_social LIKE $comodin OR {$prefix}_nombre_comercial LIKE $comodin OR CONCAT({$prefix}_primer_nombre, ' ', {$prefix}_otros_nombres, ' ', {$prefix}_primer_apellido, ' ', {$prefix}_segundo_apellido) LIKE $comodin)";
        $buscador = $className::where('estado', 'ACTIVO')
            ->whereRaw($sqlRaw)
            ->select(["{$prefix}_id", "{$prefix}_identificacion",
                DB::raw("IF({$prefix}_razon_social IS NULL OR {$prefix}_razon_social = '', CONCAT({$prefix}_primer_nombre, ' ', {$prefix}_otros_nombres, ' ', {$prefix}_primer_apellido, ' ', {$prefix}_segundo_apellido), {$prefix}_razon_social) as {$prefix}_razon_social")]);

        // Si se trata de un request por oferentes
        if ($ofe_id !== -1)
            $buscador->where('ofe_id', $ofe_id);
        $buscador->take(10);

        return response()->json([
            'data' => $buscador->get()
        ], 200);
    }

    /**
     * Lista los parámetros buscados para armar un select
     *
     * @param string $className
     * @param string $prefix
     * @param int $ofe_id
     * @return JsonResponse
     */
    public function getListaSimpleSelect(string $className, string $prefix, $ofe_id = -1){
        $buscador = $className::where('estado', 'ACTIVO')
            ->select(["{$prefix}_id", "{$prefix}_identificacion", DB::raw("IF({$prefix}_razon_social IS NULL OR {$prefix}_razon_social = '', CONCAT({$prefix}_primer_nombre, ' ', {$prefix}_otros_nombres, ' ', {$prefix}_primer_apellido, ' ', {$prefix}_segundo_apellido), {$prefix}_razon_social) as {$prefix}_razon_social")]);
        // Si se trata de un request por oferentes
        if ($ofe_id !== -1)
            $buscador->where('ofe_id', $ofe_id);
        return response()->json([
            'data' => $buscador->get()
        ], 200);
    }

    /**
     * Obtiene la lista de errores de procesamientos en el sistema.
     *
     * @param array   $processType Indica tipos de proceso a buscar
     * @param boolean $printExcel inidica si se genera o no el excel
     * @param string  $filename nombre del archivo excel
     * @param string  $origen indica el modulo desde donde se invoca
     * @return JsonResponse
     * @throws \Exception
     */
    public function getListaErrores($processType, bool $printExcel = false, $filename = '', $origen = '') {
        // Usuario autenticado
        $user = auth()->user();
        $request = request();

        if (!is_array($processType)){
            $processType = explode(',', $processType);
        }

        $tipoDocumento = '';
        if (in_array('DS', $processType) || in_array('DS_NC', $processType)) {
            $processType   = ['EDI'];
            $tipoDocumento = 'DS';
        }

        //Si es el usuario ADMINISTRADOR o MA, o si el llamado 
        if($user->usu_type == 'ADMINISTRADOR' || $user->usu_type == 'MA' || $origen == 'emision' || $origen == 'recepcion') {
            $cargues = EtlProcesamientoJson::select('pjj_id', 'pjj_tipo', 'pjj_errores', 'usuario_creacion', 'fecha_creacion')
                ->where('fecha_creacion', '>=', $request->get('fechaCargue') . ' 00:00:00')
                ->where('fecha_creacion', '<=', $request->get('fechaCargue') . ' 23:59:59')
                ->whereIn('pjj_tipo', $processType)
                ->whereNotNull('pjj_errores')
                ->UsuariosBaseDatos();
        } else { 
            // Se obtiene la colección conforme a los parámetros en el request
            $cargues = EtlProcesamientoJson::select('pjj_id', 'pjj_tipo', 'pjj_errores', 'usuario_creacion', 'fecha_creacion')
                ->when(in_array('XML-ADQUIRENTES', $processType) || in_array('ADQ-OPENCOMEX', $processType), function ($cargues) use ($processType, $request) {
                    return $cargues->where('fecha_creacion', '>=', $request->get('fechaCargue') . ' 00:00:00')
                        ->where('fecha_creacion', '<=', $request->get('fechaCargue') . ' 23:59:59')
                        ->whereIn('pjj_tipo', $processType)
                        ->whereNotNull('pjj_errores')
                        ->UsuariosBaseDatos();
                }, function($cargues) use ($processType, $request, $user) {
                    return $cargues->where('usuario_creacion', $user->usu_id)
                        ->whereBetween('fecha_creacion', [$request->fechaCargue . ' 00:00:00', $request->fechaCargue . ' 23:59:59'])
                        ->whereIn('pjj_tipo', $processType)
                        ->whereNotNull('pjj_errores');
                });
        }

        if($request->has('buscar') && !empty($request->buscar)) {
            $cargues->where(function($query) use ($request) {
                $query->where('fecha_creacion', 'like', '%' . $request->buscar . '%')
                    ->orWhere('pjj_tipo', 'like', '%' . $request->buscar . '%')
                    ->orWhere('pjj_errores', 'like', '%' . $request->buscar . '%')
                    ->orWhereRaw('exists (select * from `etl_openmain`.`auth_usuarios` where `etl_procesamiento_json`.`usuario_creacion` = `etl_openmain`.`auth_usuarios`.`usu_id` and `etl_openmain`.`auth_usuarios`.`usu_nombre` like "%' . $request->buscar . '%")');
            });
        }

        // Se obtienen solo los errores para Documento soporte
        if ($tipoDocumento == 'DS') {
            $cargues->where(function($query) {
                $query->where('pjj_errores', 'like', '%{"documento":"DS%')
                    ->orWhere('pjj_errores', 'like', '%{"documento":"DS_NC%');
            });
        } else {
            $cargues->where('pjj_errores', 'not like', '%{"documento":"DS%');
        }

        if($request->has('portalProveedores') && $request->portalProveedores == true) {
            $cargues->where('pjj_json', 'like', '%"ofe_identificacion":"' . $request->ofe_identificacion . '"%')
                ->where('pjj_json', 'like', '%"pro_identificacion":"' . $request->pro_identificacion . '"%')
                ->where('pjj_json', 'like', '%"usu_identificacion":"' . $request->usu_identificacion . '"%');
        }

        $cargues->with([
            'getUsuarioCreacion:usu_id,usu_nombre'
        ]);

        $cargues = $cargues->orderBy('fecha_creacion', 'desc')->get();
        if ($cargues) {
            // La colección resultante debe ser reorganizada en otra colección
            // dado que por cada registro en la colección original, pueden
            // existir múltiples documentos con múltiples errores
            $totalErrores = 0;
            $newArrCargues = [];
            $erroresCargues = [];
            // Itera sobre la colección resultando para crear la nueva
            $cargues->map(function ($cargue) use (&$newArrCargues, &$totalErrores, $request, $printExcel) {
                $errores = json_decode($cargue->pjj_errores);

                if (!empty($errores)){
                    foreach ($errores as $documento => $erroresDocumento) {
                        $thedoc = isset($erroresDocumento->documento) ? $erroresDocumento->documento : $documento;
                        $erroresDocumento = (array)$erroresDocumento;
                        foreach ($erroresDocumento as $key => $value) {
                            if (array_key_exists($key, $erroresDocumento)) {
                                if (is_object($erroresDocumento[$key])) {
                                    $erroresDocumento[$key] = implode('<br>', (array)(json_encode($erroresDocumento[$key])));
                                } else {
                                    $erroresDocumento[$key] = $erroresDocumento[$key];
                                }
                            }
                        }
                        
                        if(!array_key_exists('errors', $erroresDocumento))
                            $erroresDocumento['errors'] = $erroresDocumento;

                        if (!empty($request->get('buscar'))) {
                            $__errores = $erroresDocumento;
                            $cadenaErrores = implode(' - ', $__errores['errors']);
                            unset($__errores['errors']);
                            $cadenaErrores = $cadenaErrores . ' - ' . implode(' - ', $__errores);
                            if (
                                stripos($cadenaErrores, $request->buscar) !== false ||
                                stripos($documento, $request->buscar) !== false ||
                                $documento == $request->buscar ||
                                stripos($cargue->fecha_creacion, $request->buscar) !== false
                            ) {
                                $newArrCargues[$totalErrores]['pjj_id']                  = $cargue->pjj_id;
                                $newArrCargues[$totalErrores]['pjj_tipo']                = $cargue->pjj_tipo;
                                $newArrCargues[$totalErrores]['usuario_creacion']        = $cargue->usuario_creacion;
                                $newArrCargues[$totalErrores]['usuario_creacion_nombre'] = $cargue->getUsuarioCreacion->usu_nombre;
                                $newArrCargues[$totalErrores]['fecha_creacion']          = $cargue->fecha_creacion->format('Y-m-d H:i:s');
                                $newArrCargues[$totalErrores]['documento']               = $thedoc;
                                $newArrCargues[$totalErrores]['errores']                 = implode('<br>', $erroresDocumento['errors']);
                                $newArrCargues[$totalErrores]['adquirente']              = array_key_exists('adquirente', $erroresDocumento) ? $erroresDocumento['adquirente'] : '';
                            }
                        } else {
                            $newArrCargues[$totalErrores]['pjj_id']                  = $cargue->pjj_id;
                            $newArrCargues[$totalErrores]['pjj_tipo']                = $cargue->pjj_tipo;
                            $newArrCargues[$totalErrores]['usuario_creacion']        = $cargue->usuario_creacion;
                            $newArrCargues[$totalErrores]['usuario_creacion_nombre'] = $cargue->getUsuarioCreacion->usu_nombre;
                            $newArrCargues[$totalErrores]['fecha_creacion']          = $cargue->fecha_creacion->format('Y-m-d H:i:s');
                            $newArrCargues[$totalErrores]['documento']               = $thedoc;
                            $newArrCargues[$totalErrores]['adquirente']              = array_key_exists('adquirente', $erroresDocumento) ? $erroresDocumento['adquirente'] : '';

                            if (!empty($erroresDocumento['errors']) && is_array($erroresDocumento['errors'])) {
                                $newArrCargues[$totalErrores]['fecha_creacion'] = array_key_exists('fecha_procesamiento', $erroresDocumento) && array_key_exists('hora_procesamiento', $erroresDocumento) ? $erroresDocumento['fecha_procesamiento'] . ' ' . $erroresDocumento['hora_procesamiento'] : $cargue->fecha_creacion->format('Y-m-d H:i:s');
                                $newArrCargues[$totalErrores]['errores'] = implode($printExcel ? "\n": '<br>', $erroresDocumento['errors']);
                            } else {
                                $newArrCargues[$totalErrores]['errores'] = implode('<br>', $erroresDocumento);
                            }
                        }
                        $totalErrores++;
                    }
                }
            });
            $length = isset($request->length) && $request->length > 0 ? $request->length : count($newArrCargues);
            $erroresCargues = array_slice($newArrCargues, $request->start, $length);
        } else {
            $erroresCargues = [];
        }

        if ($printExcel){
            $newArrCargues = collect($erroresCargues);
            $objDocumentos = $newArrCargues->values();
            // Si existen resultados se genera el archivo de excel
            if ($objDocumentos->count() > 0) {
                // Array que pasa al Excel
                $arrDocumentos = [];

                // Itera sobre la colección de documentos para armar
                // el array que se integrará en el archivo de Excel
                $objDocumentos->each(function ($documento) use (&$arrDocumentos, $request) {
                    if($request->has('tipoLog') && $request->tipoLog == 'RECEPCION') {
                        $arrDocumentos[] = [
                            $documento['fecha_creacion'],
                            $documento['usuario_creacion_nombre'],
                            $documento['documento'],
                            $documento['errores']
                        ];
                    } elseif($request->has('tipoLog') && ($request->tipoLog == 'ADQ' || $request->tipoLog == 'XML-ADQUIRENTES' || $request->tipoLog == 'ADQ-OPENCOMEX')) {
                        $arrDocumentos[] = [
                            $documento['fecha_creacion'],
                            array_key_exists('documento', $documento) ? $documento['documento'] : '',
                            array_key_exists('adquirente', $documento) ? $documento['adquirente'] : '',
                            $documento['errores']
                        ];
                    } elseif($request->has('tipoLog') && ($request->tipoLog == 'DMCARGOS' || $request->tipoLog == 'DMDESCUENTOS' || $request->tipoLog == 'AED')) {
                        $arrDocumentos[] = [
                            $documento['fecha_creacion'],
                            $documento['errores']
                        ];
                    } else {
                        $arrDocumentos[] = [
                            $documento['fecha_creacion'],
                            $documento['documento'],
                            $documento['errores']
                        ];
                    }
                });

                if($request->has('tipoLog') && $request->tipoLog == 'RECEPCION') {
                    $titulos = [
                        'FECHA CARGUE',
                        'USUARIO',
                        'DOCUMENTO',
                        'ERRORES'
                    ];
                } elseif($request->has('tipoLog') && ($request->tipoLog == 'ADQ' || $request->tipoLog == 'XML-ADQUIRENTES' || $request->tipoLog == 'ADQ-OPENCOMEX')) {
                    $titulos = [
                        'FECHA CARGUE',
                        'DOCUMENTO',
                        'IDENTIFICACION',
                        'ERRORES'
                    ];
                } elseif($request->has('tipoLog') && ($request->tipoLog == 'DMCARGOS' || $request->tipoLog == 'DMDESCUENTOS' || $request->tipoLog == 'AED')) {
                    $titulos = [
                        'FECHA CARGUE',
                        'ERRORES'
                    ];
                } else {
                    $titulos = [
                        'FECHA CARGUE',
                        'DOCUMENTO',
                        'ERRORES'
                    ];
                }

                $nombreArchivo = $filename.'_'.$request->fechaCargue;
                $archivoExcel = $this->toExcel($titulos, $arrDocumentos, $nombreArchivo);

                if($request->has('portalProveedores') && $request->portalProveedores == true) {
                    $base64 = base64_encode(File::get($archivoExcel));
                    File::delete($archivoExcel);
                    
                    return response()->json([
                        'data' => [
                            'archivo'       => $base64,
                            'nombreArchivo' => $nombreArchivo . '.xlsx'
                        ]
                    ], 200);
                } else {
                    $headers = [
                        header('Access-Control-Expose-Headers: Content-Disposition')
                    ];
            
                    return response()
                        ->download($archivoExcel, $nombreArchivo . '.xlsx', $headers)
                        ->deleteFileAfterSend(true);
                }
            } else {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Sin resultados en la consulta']
                ], 400);
            }
        }

        return response()->json([
            "total" => $totalErrores,
            "filtrados" => count($newArrCargues),
            "data" => $erroresCargues
        ], 200);
    }

}
