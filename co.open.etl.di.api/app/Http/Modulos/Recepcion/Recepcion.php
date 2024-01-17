<?php
/**
 * Calse principal del proceso de recepción.
 * 
 * Agrupa métodos que pueden ser llamados desde la clase de procesamiento
 */
namespace App\Http\Modulos\Recepcion;

use Validator;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use App\Http\Models\AdoAgendamiento;
use openEtl\Tenant\Traits\TenantTrait;
use App\Http\Modulos\Recepcion\RecepcionException;
use App\Http\Modulos\Sistema\VariablesSistema\VariableSistemaTenant;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepFatDocumentosDaop\RepFatDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepMediosPagoDocumentosDaop\RepMediosPagoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepDatosAdicionalesDocumentosDaop\RepDatoAdicionalDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class Recepcion {
    use DiTrait;

    /**
     * Array para almacenamiento de errores.
     *
     * @var string
     */
    public $errors = [];

    /**
     * Namespaces del documento.
     *
     * @var array
     */
    public $namespaces = [];
    
    /**
     * Namespace del elemento root del documento.
     *
     * @var string
     */
    public $rootNS = '';

    /**
     * Tipo de documento XML - dato original.
     *
     * @var string
     */
    public $tipoDocumentoOriginal = null;

    /**
     * Código del tipo de documento electrónico.
     *
     * @var string|null
     */
    public $codigoTipoDocumento = null;

    /**
     * Tipo de documento XML, puede o no incluir el namespace del elemento root cuando exista.
     *
     * @var string|null
     */
    public $tipoDocumento = null;

    /**
     * Paramétricas utilizadas en los procesos.
     *
     * @var array
     */
    public $parametricas = [];

    /**
     * NIT de AGENCIA DE ADUANAS DHL EXPRESS COLOMBIA LTDA.
     *
     * @var string
     */
    protected $nitAgenciaAduanasDhl = '830076778';

    /**
     * Endpoint en DO para transmisión de documentos de recepción a openCOMEX.
     *
     * @var string
     */
    protected $endpointCboDhlExpress = 'recepcion/documentos/cbo-dhl-transmitir-opencomex';

    public function __construct() {
        $this->parametricas = $this->parametricas([
            'tipoDocumentoElectronico',
            'tipoOperacion',
            'moneda',
            'mediosPago',
            'formasPago'
        ], '');
    }

    /**
     * Reconstruye las reglas de validación de creación de registros del modelo RepCabeceraDocumentoDaop
     *
     * @param array $reglas Reglas de validación
     * @return array $reglas Reglas de validación modificadas
     */
    public function reconstruyeReglas($reglas) {
        $reglas['cdo_fecha'] = 'nullable|date_format:Y-m-d|before:' . Carbon::now();

        return $reglas;
    }

    /**
     * Reconstruye las reglas de validación de actualización de registros del modelo RepCabeceraDocumentoDaop
     *
     * @param array $reglas Reglas de validación
     * @return array $reglas Reglas de validación modificadas
     */
    public function reconstruyeReglasUpdate($reglas) {
        $reglas['cdo_fecha'] = 'nullable|date_format:Y-m-d|before:' . Carbon::now();

        return $reglas;
    }

    /**
     * Formatea un valor numérico.
     *
     * @param mixed $value
     * @return string
     */
    public function numberFormat($value) {
        if (empty($value))
            return "0.00";
        if (is_integer($value))
            $value = (string) $value;
        if (is_float($value))
            $value = (string) $value;

        if (preg_match("/^(\-)?((\d{1,3})(\,\d{3})*)(\.\d{1,6})?$/",  $value)) {
            $value = str_replace(',', '', $value);
            return number_format($value, 2, '.', '');
        } elseif (preg_match("/^(\-)?((\d{1,3})(\.\d{3})*)(\,\d{1,6})?$/",  $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
            return number_format($value, 2, '.', '');
        } elseif (preg_match("/^(\-)?(\d+)(\.\d{1,6})?$/",  $value)) {
            return number_format($value, 2, '.', '');
        } elseif(preg_match("/^(\-)?(\d+)(\,\d{1,6})?$/",  $value)) {
            $value = str_replace(',', '.', $value);
            return number_format($value, 2, '.', '');
        } else {
            return number_format($value, 2, '.', '');
        }
        
        return $value;
    }

    /**
     * UUID utilizado en el campo cdo_lote.
     * 
     */
    public static function buildLote() {
        $uuid    = Uuid::uuid4();
        $dateObj = \DateTime::createFromFormat('U.u', microtime(TRUE));
        $msg     = $dateObj->format('u');
        $msg    /= 1000;
        $dateObj->setTimeZone(new \DateTimeZone('America/Bogota'));
        $dateTime = $dateObj->format('YmdHis').intval($msg);
        $lote = $dateTime . '_' . $uuid->toString();

        return $lote;
    }

    /**
     * Obtiene el datos paramétrico requerido del array de paramétricas.
     * 
     * @param array $arrParametrica Array de la parametrica desde el cual se va a obtener el dato
     * @param string $campoParametrica Nombre del campo dentro del array de paramétricas
     * @param string $campoDcumento Valor del campo asociado al documento con el cual se va a comparar
     * @param string $campoRetornar Nombre del campo del array de paramétricas cuyo valor será devuelto
     * @return string $parametrica Valor de la paramétrica solicitada
     */
    public function obtieneDatoParametrico($arrParametrica, $campoParametrica, $campoDocumento, $campoRetornar) {
        $parametrica = Arr::where($arrParametrica, function ($value, $key) use ($campoParametrica, $campoDocumento) {
            return (isset($value[$campoParametrica]) && $value[$campoParametrica] == $campoDocumento);
        });
        $parametrica = Arr::pluck($parametrica, $campoRetornar);
        if(array_key_exists(0, $parametrica)) {
            return $parametrica[0];
        } else {
            return null;
        }
    }

    /**
     * Obtiene un token para efectuar una peticion a un microservicio.
     *
     * @return string
     */
    public function getToken() {
        $user = auth()->user();
        if ($user)
            return auth()->tokenById($user->usu_id);
        return '';
    }

    /**
     * Construye un cliente de Guzzle para consumir los microservicios
     *
     * @param string $URI
     * @return Client
     */
    public function getCliente(string $URI) {
        return new Client([
            'base_uri' => $URI,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Realiza una petición al microservicio MAIN.
     * 
     * @param string $metodoHttp Método HTTP a usar en la petición a MAIN
     * @param string $endpoint Endpoint del MAIN a consumir
     * @param array $parametros Array de parámetros
     * @return array
     * @throws \Exception
     */
    public function peticionMain(string $metodoHttp, string $endpoint, array $parametros) {
        try {
            // Accede microservicio Main para procesar la informacion del adquirente
            $peticionMain = $this->getCliente(config('variables_sistema.APP_URL'))
                ->request(
                    $metodoHttp,
                    config('variables_sistema.MAIN_API_URL') . '/api/' . $endpoint,
                    [
                        'form_params' => $parametros,
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->getToken()
                        ]
                    ]
                );

            return[
                'respuesta' => json_decode((string)$peticionMain->getBody()->getContents()),
                'error'     => false
            ];
        } catch (\Exception $e) {
            $response = $e->getResponse();
            return[
                'respuesta' => $response->getBody()->getContents(),
                'error'     => true
            ];
        }
    }

    /**
     * Realiza una petición al microservicio DO.
     * 
     * @param string $metodoHttp Método HTTP a usar en la petición a DO
     * @param string $endpoint Endpoint del DO a consumir
     * @param array $parametros Array de parámetros
     * @return array
     * @throws \Exception
     */
    public function peticionDo(string $metodoHttp, string $endpoint, array $parametros) {
        $responsePdf = false;
        try {
            if($endpoint == 'pdf/recepcion/generar-representacion-grafica') {
                $responsePdf = true;
                $parametros  = $parametros[0];
                $cabecera    = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getToken(),
                        'Content-Type' => 'application/json'
                    ],
                    'body' => $parametros,
                    'query' => [
                        'return-type' => 'pdf'
                    ]
                ];
            } else {
                $cabecera    = [
                    'form_params' => $parametros,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getToken()
                    ]
                ];
            }
            // Accede al microservicio DO para procesar la petición
            $peticionDo = $this->getCliente(config('variables_sistema.APP_URL'))
                ->request(
                    $metodoHttp,
                    config('variables_sistema.DO_API_URL') . '/api/' . $endpoint,
                    $cabecera
                );

            return[
                'respuesta' => ($responsePdf) ? base64_encode($peticionDo->getBody()->getContents()) : json_decode((string)$peticionDo->getBody()->getContents()),
                'error'     => false
            ];
        } catch (\Exception $e) {
            if(!$responsePdf)
                $response = $e->getResponse();

            return[
                'respuesta' => ($responsePdf) ? $e->getMessage() : $response->getBody()->getContents(),
                'error'     => true
            ];
        }
    }

    /**
     * Define la clasificación de un documento.
     * 
     * @return string
     */
    public function cdoClasificacion(): string {
        $cdo_clasificacion = "";
        if ($this->tipoDocumentoOriginal == "Invoice" && $this->codigoTipoDocumento == "05")
            $cdo_clasificacion = "DS";
        else if ($this->tipoDocumentoOriginal == "Invoice")
            $cdo_clasificacion = "FC";
        else if ($this->tipoDocumentoOriginal == "CreditNote" && $this->codigoTipoDocumento == "95")
            $cdo_clasificacion = "DS_NC";
        else if ($this->tipoDocumentoOriginal == "CreditNote")
            $cdo_clasificacion = "NC";
        else if ($this->tipoDocumentoOriginal == "DebitNote")
            $cdo_clasificacion = "ND";

        return $cdo_clasificacion;
    }

    /**
     * Verifica la existencia de un OFE en el sistema y que se encuentre ACTIVO
     *
     * @param string $ofe_identificacion Identificación del OFE
     * @return boolean
     */
    public function existeOfe($ofe_identificacion) {
        // Verifica que el Adquirente del documento exista como OFE y que se encuentre activo
        $existeOfe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if($existeOfe)
            return $existeOfe->ofe_id;
        else
            return '';
    }

    /**
     * Verifica la existencia de un Proveedor en el sistema y que se encuentre ACTIVO
     *
     * @param int $ofe_id ID del OFE
     * @param string $pro_identificacion Identificación del Proveedor
     * @return boolean
     */
    public function existeProveedor($ofe_id, $pro_identificacion) {
        $existeProveedor = ConfiguracionProveedor::select(['pro_id'])
            ->where('ofe_id', $ofe_id)
            ->where('pro_identificacion', $pro_identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        if($existeProveedor)
            return $existeProveedor->pro_id;
        else
            return '';
    }

    /**
     * Carga las varibales del sistema tenant que indican si se puede crear/actualizar un proveedor
     *
     * @return array Array que contiene índices booleanos para cada tipo de operación
     */
    public function variablesTenantCrearActualizarProveedor() {
        $variableCrear = VariableSistemaTenant::select(['vsi_valor'])
            ->where('vsi_nombre', 'RECEPCION_CREACION_PROVEEDOR')
            ->where('estado', 'ACTIVO')
            ->first();

        $variableActualizar = VariableSistemaTenant::select(['vsi_valor'])
            ->where('vsi_nombre', 'RECEPCION_ACTUALIZACION_PROVEEDOR')
            ->where('estado', 'ACTIVO')
            ->first();

        return [
            'crearProveedor'      => ($variableCrear && $variableCrear->vsi_valor == 'SI') ? true : false,
            'actualizarProveedor' => ($variableActualizar && $variableActualizar->vsi_valor == 'SI') ? true : false
        ];
    }

    /**
     * Procesa la creación de un proveedor a través del microservicio MAIN
     *
     * @param string $metodoHttp Método HTTP a usar en la petición a MAIN
     * @param string $endpoint Endpoint para la petición a MAIN
     * @param array $proveedor Array conteniendo los datos del proveedor
     * @param string $nombreArchivo Nombre del archivo en procesamiento
     * @return void
     */
    public function procesarProveedor($metodoHttp, $endpoint, $proveedor, $nombreArchivo) {
        $proceso = $this->peticionMain($metodoHttp, $endpoint, $proveedor);

        if($proceso['error']) {
            $respuesta = json_decode($proceso['respuesta']);

            if(!isset($respuesta->pro_id)) {
                throw new RecepcionException(
                    'Error al procesar el Proveedor (Método ' . $metodoHttp . '): ' . $respuesta->message . (isset($respuesta->errors) ? ' - ' . implode(' // ' , $respuesta->errors) : ''),
                    422,
                    null,
                    $nombreArchivo
                );
            } else {
                $this->errors[] = 'Error al procesar el Proveedor (Método ' . $metodoHttp . '): ' . $respuesta->message . (isset($respuesta->errors) ? ' - ' . implode(' // ' , $respuesta->errors) : '');
                if($metodoHttp == 'POST')
                    return $respuesta->pro_id;
            }
        } else {
            if($metodoHttp == 'POST')
                return $proceso['respuesta']->pro_id;
        }
    }

    /**
     * Permite consultar un documento en la data operativa y en el histórico.
     *
     * @param string $tipoTabla Tipo de tabla a aconsultar (daop|historico)
     * @param int $tdeId ID del tipo de documento electrónico
     * @param int $ofeId ID del OFE
     * @param int $proId ID del Proveedor
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @param string $cufe CUFE del documento
     * @return mixed
     */
    public function consultarDocumento(string $tipoTabla, int $tdeId, int $ofeId, int $proId, string $prefijo, string $consecutivo, string $cufe) {
        switch($tipoTabla) {
            case 'historico':
                $modeloCabecera = RepFatDocumentoDaop::class;
                $arrSelect      = ['cdo_id', 'estado', 'fecha_creacion'];
                break;
            default:
            case 'daop':
                $modeloCabecera = RepCabeceraDocumentoDaop::class;
                $arrSelect      = ['cdo_id', 'cdo_origen', 'estado', 'fecha_creacion'];
                break;
        }

        return $modeloCabecera::select($arrSelect)
            ->where('tde_id', $tdeId)
            ->where('ofe_id', $ofeId)
            ->where('pro_id', $proId)
            ->where('cdo_consecutivo', $consecutivo)
            ->where(function($query) use ($prefijo) {
                if($prefijo == '')
                    $query->where('rfa_prefijo', '');
                else
                    $query->where('rfa_prefijo', $prefijo);
            })
            ->where('cdo_cufe', $cufe)
            ->first();
    }

    /**
     * Procesa un documento en recepción
     *
     * @param array $documento Array conteniendo la información del documento a procesar
     * @param string $origen Origen del procesamiento del documento
     * @return RepCabeceraDocumentoDaop $documentoRecepcion Modelo del documento procesado
     */
    public function procesarDocumentoRecepcion($documento, $origen) {
        // Usuario Autenticado
        $user = auth()->user();
        $actualizarOrigen = false;
        $documentoRecepcionHistorico = null;

        // Establece el estado del documento
        $documento['estado'] = 'ACTIVO';

        // Verifica si existe el documento en la data operativa
        $documentoRecepcion = $this->consultarDocumento('daop', $documento['tde_id'], $documento['ofe_id'], $documento['pro_id'], $documento['rfa_prefijo'], $documento['cdo_consecutivo'], $documento['cdo_cufe']);

        // Si no existe en la data operativa, verifica si existe el documento en la data histórica
        if(!$documentoRecepcion)
            $documentoRecepcionHistorico = $this->consultarDocumento('historico', $documento['tde_id'], $documento['ofe_id'], $documento['pro_id'], $documento['rfa_prefijo'], $documento['cdo_consecutivo'], $documento['cdo_cufe']);

        if ($documentoRecepcion && $origen == 'RPA' && $documentoRecepcion->cdo_origen == 'CORREO')
            $actualizarOrigen = true;

        if($documentoRecepcionHistorico) {
            throw new RecepcionException(
                'Errores al procesar el documento: El documento ya existe en la data histórica del sistema',
                409,
                null,
                [
                    'cdo_prefijo'     => $documento['rfa_prefijo'],
                    'cdo_consecutivo' => $documento['cdo_consecutivo']
                ]
            );
        } elseif($documentoRecepcion && $documentoRecepcion->estado == 'INACTIVO') {
            // Valida el array del documento frente a las reglas de actualización del modelo
            $reglasUpdate = $this->reconstruyeReglasUpdate(RepCabeceraDocumentoDaop::$rulesUpdate);
            $validador = Validator::make($documento, $reglasUpdate, $this->mensajeNumerosPositivosRecepcion);
            if($validador->fails()){
                $this->errors[] = 'Errores al validar la información del documento: [' . implode(' // ', $validador->errors()->all()) . ']';
            }

            // Se actualiza el documento
            $documentoRecepcion->update($documento);

            // Medios de pago
            if(!empty($documento['cdo_medios_pago'])) {
                RepMediosPagoDocumentoDaop::where('cdo_id', $documentoRecepcion->cdo_id)
                    ->delete();

                foreach ($documento['cdo_medios_pago'] as $item) {
                    $item['cdo_id']           = $documentoRecepcion->cdo_id;
                    $item['usuario_creacion'] = $user->usu_id;
                    $item['estado']           = 'ACTIVO';
                    $mediosPagosEscritor      = new RepMediosPagoDocumentoDaop($item);
                    $mediosPagosEscritor->save();
                }
            }

            // Si el documento tiene documentos adicionales, se crean/actualizan en el modelo correspondiente
            if(!empty($documento['datos_adicionales_documento']['cdo_documento_adicional']) || !empty($documento['datos_adicionales_documento']['dad_orden_referencia'])) {
                $datosAdicionales = RepDatoAdicionalDocumentoDaop::select(['dad_id'])
                    ->where('cdo_id', $documentoRecepcion->cdo_id)
                    ->first();

                if(!$datosAdicionales) {
                    RepDatoAdicionalDocumentoDaop::create([
                        'cdo_id'                  => $documentoRecepcion->cdo_id,
                        'cdo_documento_adicional' => !empty($documento['datos_adicionales_documento']['cdo_documento_adicional']) ? json_encode($documento['datos_adicionales_documento']['cdo_documento_adicional']) : null,
                        'dad_orden_referencia'    => !empty($documento['datos_adicionales_documento']['dad_orden_referencia']) ? $documento['datos_adicionales_documento']['dad_orden_referencia'] : null,
                        'usuario_creacion'        => $user->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);
                } else {
                    $datosAdicionales->update([
                        'cdo_documento_adicional' => !empty($documento['datos_adicionales_documento']['cdo_documento_adicional']) ? json_encode($documento['datos_adicionales_documento']['cdo_documento_adicional']) : null,
                        'dad_orden_referencia'    => !empty($documento['datos_adicionales_documento']['dad_orden_referencia']) ? $documento['datos_adicionales_documento']['dad_orden_referencia'] : null,
                        'estado'                  => 'ACTIVO'
                    ]);
                }
            }

            if ($actualizarOrigen) {
                $this->actualizarOrigenDocumentoRPA($documentoRecepcion);
            }
        } elseif($documentoRecepcion && $documentoRecepcion->estado == 'ACTIVO') {
            if ($actualizarOrigen) {
                $this->actualizarOrigenDocumentoRPA($documentoRecepcion);
            }

            throw new RecepcionException(
                'Errores al procesar el documento: El documento ya existe en el sistema, para modificarlo su estado debe ser INACTIVO',
                409,
                null,
                [
                    'cdo_prefijo'     => $documento['rfa_prefijo'],
                    'cdo_consecutivo' => $documento['cdo_consecutivo']
                ]
            );
        } else {
            $documento['usuario_creacion'] = $user->usu_id;

            $reglas = $this->reconstruyeReglas(RepCabeceraDocumentoDaop::$rules);
            if($documento['cdo_clasificacion'] == 'NC' || $documento['cdo_clasificacion'] == 'ND') {
                $reglas['cdo_vencimiento'] = 'nullable|date_format:Y-m-d';
            }

            // Valida el array del documento frente a las reglas de creación del modelo
            $validador = Validator::make($documento, $reglas, $this->mensajeNumerosPositivosRecepcion);
            if($validador->fails()){
                $this->errors[] = 'Errores al validar la información del documento: [' . implode(' // ', $validador->errors()->all()) . ']';
            }

            // Se obtiene la identificación del Ofe para validar si es de FNC
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_identificacion', 'ofe_recepcion_fnc_activo'])
                ->where('ofe_id', $documento['ofe_id'])
                ->first();

            if ($ofe && $ofe->ofe_recepcion_fnc_activo == 'SI' && $origen == 'MANUAL' && ($user->usu_type == 'ADMINISTRADOR' || $user->usu_type == 'MA')) {
                $documento['cdo_origen'] = 'CORREO';
            } elseif($user->esSuperadmin() || $user->esUsuarioMA()) {
                // Para los usuarios superadmin y usuarioma el origen debe ser RPA
                $documento['cdo_origen'] = 'RPA';
            }

            // Se crea el documento
            $documentoRecepcion = RepCabeceraDocumentoDaop::create($documento);

            TenantTrait::GetVariablesSistemaTenant();
            $opencomexCboNitsIntegracion = array_map('trim', explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION')));

            // Verificaciones del proceso CBO de DHL Express para transmitir la información del documento a openCOMEX
            if(
                in_array($documentoRecepcion->getConfiguracionProveedor->pro_identificacion, $opencomexCboNitsIntegracion) &&
                $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == $this->nitAgenciaAduanasDhl
            )
                $this->procesoEspecialDhlExpressCbo($documentoRecepcion->cdo_id);

            // Medios de pago
            if(!empty($documento['cdo_medios_pago'])) {
                foreach ($documento['cdo_medios_pago'] as $item) {
                    $item['cdo_id']           = $documentoRecepcion->cdo_id;
                    $item['usuario_creacion'] = $user->usu_id;
                    $item['estado']           = 'ACTIVO';
                    $mediosPagosEscritor      = new RepMediosPagoDocumentoDaop($item);
                    $mediosPagosEscritor->save();
                }
            }

            // Si el documento tiene documentos adicionales, se crean en el modelo correspondiente
            if(!empty($documento['datos_adicionales_documento']['cdo_documento_adicional']) || !empty($documento['datos_adicionales_documento']['dad_orden_referencia'])) {
                RepDatoAdicionalDocumentoDaop::create([
                    'cdo_id'                  => $documentoRecepcion->cdo_id,
                    'cdo_documento_adicional' => !empty($documento['datos_adicionales_documento']['cdo_documento_adicional']) ? json_encode($documento['datos_adicionales_documento']['cdo_documento_adicional']) : null,
                    'dad_orden_referencia'    => !empty($documento['datos_adicionales_documento']['dad_orden_referencia']) ? $documento['datos_adicionales_documento']['dad_orden_referencia'] : null,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);
            }
        }

        return $documentoRecepcion;
    }

    /**
     * Crea un nuevoestado para un documento electrónico en recepción.
     *
     * @param int $cdo_id ID del documento para el cual se crea el estado
     * @param string $estado Nombre del estado a crear
     * @param string $resultado Resultado del estado
     * @param json $jsonDocumento Json dle documento procesado
     * @param string $archivo Archivo en base64, ej, Pdf en base 64
     * @param string $xml XML en base64
     * @param dateTime $inicioProceso Fecha y hora de inicio del procesamiento
     * @param dateTime $finProceso Fecha y hora del final del procesamiento
     * @param timestamp $tiempoProcesamiento Tiempo de procesamiento
     * @param int $ageId del agendamiento relacionado con el estado
     * @param int $ageUsuId del usuario que crea el nuevo estado
     * @param array $estadoInformacionAdicional Información adicional del estado
     * @param string $ejecucion Estado de ejecucion del proceso
     * @return void
     */
    public static function creaNuevoEstadoDocumentoRecepcion($cdo_id, $estado, $resultado, $inicioProceso = null, $finProceso = null, $tiempoProcesamiento = null, $ageId = null, $ageUsuId = null, $estadoInformacionAdicional = null, $ejecucion = null) {
        $user = auth()->user();

        RepEstadoDocumentoDaop::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estado,
            'est_resultado'             => $resultado,
            'est_inicio_proceso'        => $inicioProceso,
            'est_fin_proceso'           => $finProceso,
            'est_tiempo_procesamiento'  => $tiempoProcesamiento,
            'age_id'                    => $ageId,
            'age_usu_id'                => $ageUsuId,
            'est_informacion_adicional' => ($estadoInformacionAdicional != null && !empty($estadoInformacionAdicional)) ? json_encode($estadoInformacionAdicional) : null,
            'usuario_creacion'          => $user->usu_id,
            'est_ejecucion'             => $ejecucion,
            'estado'                    => 'ACTIVO'
        ]);
    }

    /**
     * Crea un nuevo agendamiento en el sistema.
     *
     * @param int $bddId ID de la base de datos
     * @param string $ageProceso Nombre del proceso a ejecutarse en el agendamiento
     * @param int $ageCantidadDocumentos Cantidad de documentos a procesar
     * @param int $agePrioridad Número de prioridad del proceso
     * @return int ID del nuevo agendamiento
     */
    public static function creaNuevoAgendamiento($bddId = null, $ageProceso, $ageCantidadDocumentos, $agePrioridad = null) {
        $user = auth()->user();

        $agendamiento = AdoAgendamiento::create([
            'usu_id'                  => $user->usu_id,
            'bdd_id'                  => $bddId,
            'age_proceso'             => $ageProceso,
            'age_cantidad_documentos' => $ageCantidadDocumentos,
            'age_prioridad'           => $agePrioridad,
            'usuario_creacion'        => $user->usu_id,
            'estado'                  => 'ACTIVO'
        ]);

        return $agendamiento->age_id;
    }

    /**
     * Crea un agendamiento RDI para los documentos a procesar.
     *
     * @param User $usu_id Instancia del usuario autenticado por el proceso
     * @param array $arrAgendamiento Array de datos para el agendamiento
     * @param int $cantidadDocumentos Cantidad de documentos a agendar
     * @return bool
     */
    public static function crearAgendamientoRdi(User $user, array $arrAgendamiento, int $cantidadDocumentos): bool {
        try {
            $agendamiento = self::creaNuevoAgendamiento(auth()->user()->bdd_id, 'RDI', $cantidadDocumentos, null);
            
            EtlProcesamientoJson::create([
                'pjj_tipo'                => 'RDI',
                'pjj_json'                => json_encode($arrAgendamiento),
                'pjj_procesado'           => 'NO',
                'age_id'                  => $agendamiento,
                'age_estado_proceso_json' => null,
                'usuario_creacion'        => $user->usu_id,
                'estado'                  => 'ACTIVO'
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Actualiza el origen del documento cuando el proceso es por RPA y el cdo_origen es CORREO.
     * 
     * Adicionalmente en el estado RDI existente del documento, en la columna est_informacion_adicional agrega la propiedad origen con valor RPA 
     *
     * @param object $documentoRecepcion Información del documento en proceso
     * @return void
     */
    private function actualizarOrigenDocumentoRPA($documentoRecepcion) {
        // Se actualiza el documento
        $documentoRecepcion->update([
            'cdo_origen' => 'RPA'
        ]);

        $estadoDocumento = RepEstadoDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
            ->where('cdo_id', $documentoRecepcion->cdo_id)
            ->where('est_estado', 'RDI')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->get()
            ->last();

        if ($estadoDocumento) {
            $informacionAdicional = !empty($estadoDocumento->est_informacion_adicional) ? json_decode($estadoDocumento->est_informacion_adicional, true) : [];
            $informacionAdicional['origen'] = 'RPA';

            $estadoDocumento->update([
                'est_informacion_adicional' => json_encode($informacionAdicional)
            ]);
        }
    }

    /**
     * Procesa un documento de recepción de DHL Express para ser enviado a openCOMEX.
     *
     * @param int $cdo_id ID del documento a procesar
     * @return void
     */
    private function procesoEspecialDhlExpressCbo($cdo_id) {
        $proceso = $this->peticionDo('POST', $this->endpointCboDhlExpress, ['cdo_id' => $cdo_id]);

        if($proceso['error']) {
            $respuesta = json_decode($proceso['respuesta']);
            $this->errors[] = 'Error intentar transmitir a openCOMEX: ' . $respuesta->message . (isset($respuesta->errors) ? ' - ' . implode(' // ' , $respuesta->errors) : '');
        }
    }
}