<?php
namespace App\Http\Modulos\DataInputWorker;

use DateTime;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use App\Http\Models\User;
use Illuminate\Support\Facades\Log;
use App\Http\Models\AdoAgendamiento;
use openEtl\Tenant\Traits\TenantTrait;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Valida los datos proporcionados por medio de un JSON y procede a construir los diferentes registros a ser insertados.
 *
 * Class DataBuilder
 * @package App\Http\Modulos\DataInputWorker
 */
class DataBuilder
{
    // Identificación de DHL Express
    public const NIT_DHLEXPRESS = '860502609';

    // Origen Manual para documentos
    public const ORIGEN_MANUAL  = 'MANUAL';
    
    // Origen Manual para documentos
    public const ORIGEN_INTEGRACION  = 'INTEGRACION';
    
    /**
     * Json que contiene la data de entrada.
     *
     * @var mixed
     */
    private $data;

    /**
     * Usuario creacion.
     *
     * @var int
     */
    private $usu_id;

    /**
     * Almacena el tipo de documentos que contiene el array.
     *
     * @var string
     */
    private $docType = '';

    /**
     * Define el tipo de origen de la carga del documento.
     *
     * @var string
     */
    private $origen;

    /**
     * Grupo de documentos a ser registrados
     * @var null|array
     */
    private $sets = null;

    /**
     * Lista de documentos que pueden ser registrados
     *
     * @var array
     */
    private $successful = [];

    /**
     * Lista de documentos que no pueden ser registrados
     *
     * @var array
     */
    private $failure = [];

    /**
     * Lista con errores encontrados.
     *
     * @var array
     */
    private $errors = [];

    /**
     * Lista documentos procesados
     *
     * @var array
     */
    private $procesados = [];

    /**
     * Almacena el número de lote.
     *
     * @var string
     */
    private $lote;

    /**
     * Codigo de moneda extranejera configurada en la data.
     *
     * @var string
     */
    public static $monCodigoExtranjera;

    /**
     * Motor de validaciones para el lote de documentos
     *
     * @var DataInputValidator
     */
    private $engineValidator;

    /**
     * Ofes con procesamiento de documentos prioritarios
     *
     * @array
     */
    private $ofesPrioridad = [
        '860502609',
        //'83010000'
    ];

    /**
     * DataBuilder constructor.
     *
     * @param int $usu_id
     * @param mixed $data Datos a validar y registrar
     * @param string $origen Indica la procedencia de la data (cargas por json o excel por los momentos)
     * @throws \Exception
     */
    public function __construct($usu_id, $data, $origen) {
        $this->usu_id = $usu_id;
        $this->data   = $data;
        $this->origen = $origen;
        $this->buildLote();
    }

    /**
     * Efectua el procesamiento del paquete.
     *
     * @return array Documentos que no han podido ser procesados
     */
    public function run() {
        $this->checkData();
        $this->save();
        $response = [
            "message" => "Bloque de informacion procesado bajo el lote " . $this->lote,
            "lote" => $this->lote,
            "documentos_procesados" => $this->procesados,
            "documentos_fallidos" => $this->failure
        ];

        // Guarda en la tabla de procesamiento Json el log de errores cuando el origen de carga es diferente de MANUAL
        if(!empty($this->failure) && $this->origen != self::ORIGEN_MANUAL && $this->origen != self::ORIGEN_INTEGRACION) {
            foreach ($this->failure as $errores) {
                EtlProcesamientoJson::create([
                    'pjj_tipo'                => 'API',
                    'pjj_procesado'           => 'SI',
                    'pjj_json'                => '',
                    'pjj_errores'             => json_encode([$errores]),
                    'age_id'                  => 0,
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'usuario_creacion'        => $this->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);
            }
        }

        return $response;
    }

    /**
     * UUID utilizado en el campo cdo_lote.
     *
     * @throws \Exception
     */
    private function buildLote() {
        $uuid = Uuid::uuid4();
        $dateObj = DateTime::createFromFormat('U.u', microtime(TRUE));
        $msg = $dateObj->format('u');
        $msg /= 1000;
        $dateObj->setTimeZone(new DateTimeZone('America/Bogota'));
        $dateTime = $dateObj->format('YmdHis').intval($msg);
        $this->lote = $dateTime . '_' . $uuid->toString();
    }

    /**
     * Efectua el procesos de verificacion y contruccion de data global.
     * 
     * @return void
     */
    private function checkData() {
        // Verificando que exista la clave de documentos
        if (!isset($this->data->{ConstantsDataInput::ROOT})) {
            $this->errors[] = ConstantsDataInput::NOT_DOCUMENT_ROOT;
            return;
        }
        $root = $this->data->{ConstantsDataInput::ROOT};

        // Trayendo la identificacion del consumidor final
        $adquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'));

        // Verificando que exista la clave adecuada de tipo de documento y que este sea efectivamente un array
        if (!isset($root->NC) && !isset($root->ND) && !isset($root->FC) && !isset($root->DS)  && !isset($root->{'DS_NC'})) {
            $this->errors[] = ConstantsDataInput::NOT_TYPE_ALLOWED;
            return;
        }

        $grupos = [
            ConstantsDataInput::FC,
            ConstantsDataInput::NC,
            ConstantsDataInput::ND,
            ConstantsDataInput::DS,
            ConstantsDataInput::DS_NC
        ];

        foreach ($grupos as $grupo) {
            if (isset($root->{$grupo})) {
                $this->docType = $grupo;
                $this->sets = $root->{$grupo};
            } else {
                // No existe una clave de este tipo en el json, por lo cual debemos intentar con la proxima
                continue;
            }

            // Si el campo con el arreglo de documentos no es un array
            if (!is_array($this->sets)) {
                $this->errors[] = sprintf(ConstantsDataInput::DOCS_ARE_NOT_ARRAY, $this->docType);
                /*
                 * La clave se esta usando para un objeto pero no para un array, debemos reportar la falla y pasar al
                 * proximo objeto
                 */
                continue;
            }

            /**
             * Filtrando la data para evitar procesar documentos con los mismos prefijos y consecutivos
             */
            $mapa = [];
            foreach ($this->sets as $indice => $item) {
                // Si existen elementos sin estas claves, el motor de validación los descartara
                if (isset($item->rfa_prefijo) && isset($item->cdo_consecutivo)) {
                    $key = $item->rfa_prefijo . $item->cdo_consecutivo;
                    if (array_key_exists($key, $mapa))
                        $mapa[$key][] = $indice;
                    else
                        $mapa[$key] = [$indice];
                }
            }

            $evitar = [];
            foreach ($mapa as $subgrupo){
                // Si existe mas de un elemento con un mismo codigo de identificación
                if (count($subgrupo) > 1)
                    $evitar = array_merge($evitar, $subgrupo);
            }

            $this->engineValidator = new DataInputValidator($this->docType);

            // Efectuamos los procesos de validacion para cada documento
            foreach ($this->sets as $key => $item) {
                // Si el OFE es DHL Express, el tipo de documento es FC y la RG es 9
                // se omite la verificación de documentos duplicados dentro del mismo bloque
                $expressPickupCash = $this->docType === ConstantsDataInput::FC && $item->ofe_identificacion == self::NIT_DHLEXPRESS && $item->cdo_representacion_grafica_documento == '9';

                if($expressPickupCash) {
                    $this->checkDocument($item, $key);
                } else {
                    if (!in_array($key, $evitar))
                        $this->checkDocument($item, $key);
                    else {
                        $doc         = $this->docType . '-' . $item->rfa_prefijo . $item->cdo_consecutivo;
                        $consecutivo = $item->cdo_consecutivo;
                        $prefijo     = $item->rfa_prefijo;

                        $this->failure[] = [
                            'documento'           => $doc,
                            'consecutivo'         => $consecutivo,
                            'prefijo'             => $prefijo,
                            'errors'              => ['Existen otros documentos en este bloque de información con el mismo prefijo y consecutivo'],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s')
                        ];
                    }
                }
            }
        }
    }

    /**
     * Retorna una propiedad contenida en un objeto.
     *
     * @param mixed $object Objeto donde puede que resida la propiedad
     * @param string $key Nombre de la propiedad
     * @return null|mixed
     */
    private function getValue($object, string $key) {
        return isset($object->{$key}) ? $object->{$key} : null;
    }

    /**
     * Efectua un proceso de validacion un documento y reasocia la data en cada una de las estructuras necesarias para
     * ser escritas en la base de datos.
     *
     * @param mixed $document Contiene la informacion de una (NC-ND-FC-DS) a ser registrada
     * @param int $indice Se usa en aquellos casos donde no se puede construir el número de documento y se debe notificar que conjunto de datos dentro del json estan fallando
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function checkDocument($document, $indice) {
        $theDoc             = $document;
        $est_inicio_proceso = date('Y-m-d H:i:s');

        // Si el adquirente es consumidor final, debe eliminarse el adq_id_personalizado
        // El sistema no permite crear el consumidor final con id personalizado
        if (isset($document->adq_id_personalizado) && !empty($document->adq_id_personalizado)) {
            $document->adq_id_personalizado = !$this->isConsumidorFinal($document->adq_identificacion) ? $document->adq_id_personalizado : null;
        }

        if ((isset($document->adq_identificacion_multiples) && !empty($document->adq_identificacion_multiples))) {
            foreach ($document->adq_identificacion_multiples as $indice => $adqMultiple) {
                if (isset($adqMultiple->adq_id_personalizado) && !empty($adqMultiple->adq_id_personalizado)) {
                    $document->adq_identificacion_multiples[$indice]->adq_id_personalizado = !$this->isConsumidorFinal($adqMultiple->adq_identificacion) ? $adqMultiple->adq_id_personalizado : null;
                }
            }
        }

        // Si llega un objeto, se convierte en un array con un solo objeto
        if ((isset($document->adquirente) && !empty($document->adquirente)) || (isset($document->vendedor) && !empty($document->vendedor))) {
            if (isset($document->vendedor))
                $document->adquirente = $document->vendedor;

            if (is_object($document->adquirente)) {
                $document->adquirente = [
                    $document->adquirente
                ];
            }

            foreach($document->adquirente as $indice => $adquirente) {
                if (isset($adquirente->adq_id_personalizado) && !empty($adquirente->adq_id_personalizado)) {
                    $document->adquirente[$indice]->adq_id_personalizado = !$this->isConsumidorFinal($adquirente->adq_identificacion) ? $adquirente->adq_id_personalizado : null;
                }
            }              
        }

        // Arrays de Objetos que componen un documento
        $cabecera     = [];
        $dad          = [];
        $this->errors = [];

        // Desarrollo especial para DHL EXPRESS
        // Para los documentos de Express con Representación Gráfica 4 y 6
        // se debe verificar si el adquirente existe en el servidor ETL principal del OFE
        //  - Si existe se debe utilizar la información del registro del servidor ETL principal
        //    para poder crear/modificar el adquirente en el servidor en donde se esta haciendo el proceso
        //  - Si NO existe el adquirente en el servidor ETL principal se debe retornar el error
        //  - Adicionalmente el proceso se debe realizar si el servidor de ETL sobre el cual se esta realizando
        //    el procesamiento es diferente del servidor ETL principal
        if(
            $document->ofe_identificacion === self::NIT_DHLEXPRESS &&
            isset($document->cdo_representacion_grafica_documento) &&
            (
                $document->cdo_representacion_grafica_documento == '4' ||
                $document->cdo_representacion_grafica_documento == '6'
            )
        ) {
            //Buscando la base de datos del usuario autenticado
            $user = User::select(['usu_id','bdd_id'])
                ->where('usu_id', $this->usu_id)
                ->where('estado', 'ACTIVO')
                ->with(['getBaseDatos'])
                ->first();

            if ($user) {
                //si la base de datos es diferente debe consultarse el adquirente en la base de datos principal
                TenantTrait::GetVariablesSistemaTenant();
                if ($user->getBaseDatos->bdd_nombre != config('variables_sistema_tenant.DB_PRINCIPAL_ID')) {
                    $adq_id_personalizado   = isset($document->adq_id_personalizado) && !empty($document->adq_id_personalizado) ? $document->adq_id_personalizado : null;
                    $adquirenteEtlPrincipal = $this->consultarAdquirenteEtlPrincipal($document->ofe_identificacion, $document->adq_identificacion, $adq_id_personalizado);

                    if (isset($adquirenteEtlPrincipal) && $adquirenteEtlPrincipal['errors']) {
                        $decode = json_decode($adquirenteEtlPrincipal['respuesta'], true);

                        $this->errors[] = $decode['message'];
                        $this->errors   = array_merge($this->errors, $decode['errors']);
                    } else {
                        $document->adquirente = [$adquirenteEtlPrincipal['respuesta']->data];
                    } 
                }
            }
        }

        $user = auth()->user();

        // Consulta el OFE para verificar la configuracion de la composición de la llave de adquirente y verificar si cuenta con campos personalizados de cabezera y/o items
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_identificador_unico_adquirente', 'ofe_campos_personalizados_factura_generica'])
            ->where('ofe_identificacion', $document->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$ofe)
            $this->errors[] = 'El Oferente '.$document->ofe_identificacion.' no existe o se encuentra en estado INACTIVO';

        $adq_identificacion = isset($document->adq_identificacion) && !empty($document->adq_identificacion) ? $document->adq_identificacion : '';
        if (isset($document->adquirente) && !empty($document->adquirente)) {
            $listaAdquirentesMultiples = [];
            if((isset($document->adq_identificacion_multiples) && !empty($document->adq_identificacion_multiples))) {
                foreach($document->adq_identificacion_multiples as $adqMultiple) {
                    $listaAdquirentesMultiples[] = $adqMultiple->adq_identificacion;
                }
            }

            if (isset($document->vendedor)) {
                $tipoMensaje       = "vendedor";
                $tipoMensajePlural = "vendedores";
            } else {
                $tipoMensaje       = "adquirente";
                $tipoMensajePlural = "adquirentes";
            }

            // Validaciones de data previo al envío de la solicitud a Main
            $adquirentesFinal = [];
            foreach($document->adquirente as $adquirente) {
                if(
                    (empty($ofe->ofe_identificador_unico_adquirente) || (!empty($ofe->ofe_identificador_unico_adquirente) && !in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente))) &&
                    isset($adquirente->adq_id_personalizado)
                ) {
                    unset($adquirente->adq_id_personalizado);
                    if(isset($document->adq_id_personalizado))
                        unset($document->adq_id_personalizado);
                } elseif(
                    !empty($ofe->ofe_identificador_unico_adquirente) &&
                    in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente) &&
                    !isset($adquirente->adq_id_personalizado)
                ) {
                    $adquirente->adq_id_personalizado = null;
                    if(!isset($document->adq_id_personalizado))
                        $document->adq_id_personalizado = null;
                }

                if (!isset($adquirente->adq_identificacion))
                    $this->errors[] = 'El registro de '.$tipoMensaje.' no contiene el campo adq_identificacion';

                if (
                    (
                        !isset($document->adq_identificacion_multiples) ||
                        (
                            isset($document->adq_identificacion_multiples) &&
                            empty($document->adq_identificacion_multiples)
                        )
                    ) &&
                    !isset($document->adq_identificacion)
                )
                    $this->errors[] = 'El documento no contiene el campo adq_identificacion';

                if (
                    empty($this->errors) &&
                    (
                        empty($ofe->ofe_identificador_unico_adquirente) || 
                        (
                            !empty($ofe->ofe_identificador_unico_adquirente) &&
                            !in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)
                        )
                    ) &&
                    (
                        !isset($document->adq_identificacion_multiples) ||
                        (
                            isset($document->adq_identificacion_multiples) &&
                            empty($document->adq_identificacion_multiples)
                        )
                    ) &&
                    $adquirente->adq_identificacion !== $document->adq_identificacion
                ) {
                    $this->errors[] = 'El documento de identificación del '.$tipoMensaje.' y el documento no coinciden';
                } elseif (
                    (
                        !empty($ofe->ofe_identificador_unico_adquirente) &&
                        in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)
                    ) &&
                    (
                        !isset($document->adq_identificacion_multiples) ||
                        (
                            isset($document->adq_identificacion_multiples) &&
                            empty($document->adq_identificacion_multiples)
                        )
                    )
                ) {
                    if(
                        (
                            (
                                !isset($document->adq_id_personalizado) ||
                                (
                                    isset($document->adq_id_personalizado) &&
                                    empty($document->adq_id_personalizado)
                                )
                            ) &&
                            isset($adquirente->adq_id_personalizado) &&
                            !empty($adquirente->adq_id_personalizado)
                        ) ||
                        (
                            isset($document->adq_id_personalizado) && 
                            !empty($document->adq_id_personalizado) && 
                            (
                                !isset($adquirente->adq_id_personalizado) ||
                                (
                                    isset($adquirente->adq_id_personalizado) && 
                                    empty($adquirente->adq_id_personalizado)
                                )
                            )
                        ) ||
                        (
                            isset($document->adq_id_personalizado) && 
                            !empty($document->adq_id_personalizado) && 
                            isset($adquirente->adq_id_personalizado) &&
                            !empty($adquirente->adq_id_personalizado) &&
                            $adquirente->adq_id_personalizado !== $document->adq_id_personalizado
                        )
                    )
                        $this->errors[] = 'El campo adq_id_personalizado debe existir a nivel de documento y '.$tipoMensaje.' con el mismo valor';
                } elseif((isset($document->adq_identificacion_multiples) && !empty($document->adq_identificacion_multiples))) {
                    // Para cada adquirente en el objeto se debe verificar que exista en los adquirentes múltiples
                    if(!in_array($adquirente->adq_identificacion, $listaAdquirentesMultiples)) {
                        $this->errors[] = 'EL '.ucfirst($tipoMensaje).' [' . $adquirente->adq_identificacion . '] no existe en los '.$tipoMensajePlural.' multiples relacionados para el documento.';
                    }
                }

                $arrAdq = (array) $adquirente;
                if($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC)
                    $arrAdq['adq_tipo_vendedor_ds'] = 'SI';

                $adquirentesFinal[] = $arrAdq;
            }

            if(!empty($adquirentesFinal))
                $document->adquirente = json_decode(json_encode($adquirentesFinal));

            if (empty($this->errors)) {
                if($adq_identificacion == '')
                    $adq_identificacion = $document->adquirente[0]->adq_identificacion;
                $respuesta = $this->peticionMain($document->adquirente);
            }
            
            if (isset($respuesta) && array_key_exists('error', $respuesta) && $respuesta['error']) {
                $decode = json_decode($respuesta['respuesta'], true);
                $this->errors[] = $decode['message'];
                $this->errors = array_merge($this->errors, $decode['errors']);
            }
        }

        if (!empty($this->errors)) {
            if (isset($document->cdo_consecutivo) && isset($document->rfa_prefijo)) {
                $doc         = $this->docType . '-' . $document->rfa_prefijo . $document->cdo_consecutivo;
                $consecutivo = $document->cdo_consecutivo;
                $prefijo     = $document->rfa_prefijo;
            } else
                $doc = "documento[".($indice+1)."]";

            $this->failure[] = [
                'documento'           => isset($doc) ? $doc : '',
                'consecutivo'         => isset($consecutivo) ? $consecutivo : '',
                'prefijo'             => isset($prefijo) ? $prefijo : '',
                'errors'              => array_merge($this->errors),
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento'  => date('H:i:s')
            ];
            return;
        }

        $cdo_informacion_adicional = isset($document->cdo_informacion_adicional) ? $document->cdo_informacion_adicional : null;

        if(!empty($cdo_informacion_adicional)) {
            $validacion   = $this->engineValidator->validate($this->toArray($cdo_informacion_adicional), DataInputValidator::VALIDATE_COLUMNAS_PERSONALIZADAS, $ofe->toArray());

            if(array_key_exists('errores', $validacion))
                $this->errors = array_merge($this->errors, $validacion['errores']);

            if(array_key_exists('informacion_adicional', $validacion))
                $document->cdo_informacion_adicional = $cdo_informacion_adicional = (object) $validacion['informacion_adicional'];
        }

        // Soporte para compresion de items
        if (isset($document->cdo_comprimido) && $document->cdo_comprimido === 'SI' && isset($document->cdo_documento_comprimido))
            $items = json_decode(gzinflate(base64_decode($document->cdo_documento_comprimido)));
        else
            $items = isset($document->items) ? $document->items : [];

        if (!is_array($items) || count($items) === 0)
            $this->errors[] = 'El documento debe tener al menos un item';

        // Construyendo la cabecera
        $encontroOrigen = 0;
        foreach (KeyMap::$clavesCDO as $key) {
            $cabecera[$key] = $this->getValue($document, $key);
            if ($key == 'cdo_origen' && $this->getValue($document, $key) != '')
                $encontroOrigen++;
        }

        if ($encontroOrigen == 0)
            $cabecera['cdo_origen'] = $this->origen;

        // Construyendo DAD
        foreach (KeyMap::$clavesDAD as $key)
            $dad[$key] = $this->getValue($document, $key);

        // Verifica si $dad es un objeto, en cuyo caso debe convertirse en array de objetos
        if(!is_null($dad['dad_terminos_entrega']) && !empty($dad['dad_terminos_entrega']) && is_object($dad['dad_terminos_entrega'])) {
            $arrTerminosEntrega = [];
            array_push($arrTerminosEntrega, $dad['dad_terminos_entrega']);
            $dad['dad_terminos_entrega'] = $arrTerminosEntrega;
        }

        if(
            array_key_exists('dad_terminos_entrega', $dad) && 
            (
                (is_array($dad['dad_terminos_entrega']) && count($dad['dad_terminos_entrega']) > 0) ||
                (!is_array($dad['dad_terminos_entrega']) && !empty($dad['dad_terminos_entrega']))
            )
        ) {
            // Agrega los números de línea
            $modificadoTerminosEntrega = [];
            foreach($dad['dad_terminos_entrega'] as $index => $terminoEntrega){
                $terminoEntrega->dad_numero_linea = $index+1;
                array_push($modificadoTerminosEntrega, $terminoEntrega);
            }
            $dad['dad_terminos_entrega'] = $modificadoTerminosEntrega;
        }

        // Si en la etiqueta nombre se envia NUMERO_AUTORIZACIÓN o NUMERO_AUTORIZACI\u00d3N, se debe reemplazar este valor por NUMERO_AUTORIZACION
        if(
            array_key_exists('cdo_interoperabilidad', $dad) &&
            (
                (is_array($dad['cdo_interoperabilidad']) && count($dad['cdo_interoperabilidad']) > 0) ||
                (!is_array($dad['cdo_interoperabilidad']) && !empty($dad['cdo_interoperabilidad']))
            )
        ) {
            $indice = 0;
            $informacionAdicional = [];
            if (isset($dad['cdo_interoperabilidad'][0]->interoperabilidad->collection[0]->informacion_adicional) && !is_null($dad['cdo_interoperabilidad'][0]->interoperabilidad->collection[0]->informacion_adicional)) {
                foreach ($dad['cdo_interoperabilidad'][0]->interoperabilidad->collection[0]->informacion_adicional as $infoAdicional) {
                    if(isset($infoAdicional->nombre) && $infoAdicional->nombre != '') {
                        $arrReemplazar = array('NUMERO_AUTORIZACIÓN', 'NUMERO_AUTORIZACI\u00d3N');
                        $informacionAdicional[$indice]['nombre'] = str_replace($arrReemplazar, 'NUMERO_AUTORIZACION', $infoAdicional->nombre);
                    }

                    $informacionAdicional[$indice]['valor'] = $infoAdicional->valor;

                    if(isset($infoAdicional->atributo_name) && $infoAdicional->atributo_name != '')
                        $informacionAdicional[$indice]['atributo_name'] = $infoAdicional->atributo_name;

                    if(isset($infoAdicional->atributo_id) && $infoAdicional->atributo_id != '')
                        $informacionAdicional[$indice]['atributo_id'] = $infoAdicional->atributo_id;

                    $indice++;
                }
                $dad['cdo_interoperabilidad'][0]->interoperabilidad->collection[0]->informacion_adicional = $informacionAdicional;
            }
        }

        // Si se trata de Express, cargue manual, FC, RG = 9 y en información adicional viene la llave currier con valor SI
        // se debe agregar el indice cdo_procesar_documento = 'SI' en cdo_informacion_adicional
        /*
        // Se comentan estas validaciones conforme al ticket PSE-1040
        if (
            array_key_exists('ofe_identificacion', $cabecera) &&
            $cabecera['ofe_identificacion'] === self::NIT_DHLEXPRESS &&
            $this->docType == ConstantsDataInput::FC &&
            $cabecera['cdo_origen'] === self::ORIGEN_MANUAL &&
            $cabecera['cdo_representacion_grafica_documento'] == '9' &&
            isset($cdo_informacion_adicional->currier) &&
            $cdo_informacion_adicional->currier == 'SI'
        ) {
            $cdo_informacion_adicional->cdo_procesar_documento = 'SI';
        } */

        // Sirve para reiniciar los errores en caso de multiples documentos
        $registrado = false;

        $errores = $this->engineValidator->validate($cabecera, DataInputValidator::VALIDATE_HEADER_DOCUMENTO, ['cdo_informacion_adicional' => $cdo_informacion_adicional], '', $registrado);
        $this->errors = array_merge($this->errors, $errores);

        // Si es true es creación de lo contrario edición
        $modo = is_null($cdo_informacion_adicional) || !isset($cdo_informacion_adicional->update) || strtoupper($cdo_informacion_adicional->update) !== 'SI';

        // Solo se permite opciones validas de creacion, es un proceso de creación y no existe el documento o es un proceso de edición y si existe el documento
        // fuera de esto se dispara el error
        if ((!$registrado && $modo) || ($registrado && !$modo)){
            $arrOthers = ['tde_codigo' => $document->tde_codigo, 'top_codigo' => $document->top_codigo, 'adq_identificacion' => $adq_identificacion];
            if(isset($document->adq_id_personalizado) && !empty($document->adq_id_personalizado))
                $arrOthers['adq_id_personalizado'] = $document->adq_id_personalizado;
            
            $errores = $this->engineValidator->validate($dad, DataInputValidator::VALIDATE_DAD_DOCUMENTO, $arrOthers);
            $this->errors = array_merge($this->errors, $errores);

            // Preguntar si esta clave siempre vendra
            $pvt = null;
            if (isset($document->{keyMap::$claveCdoMedioPago})) {
                $errores = $this->engineValidator->validate($this->toArray($document->{keyMap::$claveCdoMedioPago}), DataInputValidator::VALIDATE_MEDIOS_PAGO_DOCUMENTO,
                    ['cdo_vencimiento' => array_key_exists('cdo_vencimiento', $cabecera) ? $cabecera['cdo_vencimiento'] : null], '', $pvt, array_key_exists('ofe_identificacion', $cabecera) ? $cabecera['ofe_identificacion'] : null);
                $this->errors = array_merge($this->errors, $errores);
            } elseif ($this->docType === ConstantsDataInput::FC || $this->docType === ConstantsDataInput::DS)
                $this->errors[] = "Falta la clave cdo_medios_pago";

            // Preguntar si esta clave siempre vendra
            if (isset($document->{keyMap::$claveCdoDetalleAnticipos})) {
                $errores = $this->engineValidator->validate($this->toArray($document->{keyMap::$claveCdoDetalleAnticipos}), DataInputValidator::VALIDATE_DETALLES_ANTICIPOS_DOCUMENTO);
                $this->errors = array_merge($this->errors, $errores);
            }

            // Preguntar si esta clave siempre vendra
            if (isset($document->{keyMap::$claveCdoDetalleCargos})) {
                $other = ['ofe_identificacion' => $document->ofe_identificacion];
                if (isset($document->cdo_sistema) && $document->cdo_sistema === DataInputValidator::OPENETL_WEB)
                    $other = array_merge($other, ['cdo_sistema' => DataInputValidator::OPENETL_WEB]);

                $errores = $this->engineValidator->validate($this->toArray($document->{keyMap::$claveCdoDetalleCargos}), DataInputValidator::VALIDATE_CARGOS_DOCUMENTO, $other);
                $this->errors = array_merge($this->errors, $errores);
            }

            // Preguntar si esta clave siempre vendra
            if (isset($document->{keyMap::$claveCdoDetalleDescuentos})) {
                $other = ['ofe_identificacion' => $document->ofe_identificacion];
                if (isset($document->cdo_sistema) && $document->cdo_sistema === DataInputValidator::OPENETL_WEB)
                    $other = array_merge($other, ['cdo_sistema' => DataInputValidator::OPENETL_WEB]);

                $errores = $this->engineValidator->validate($this->toArray($document->{keyMap::$claveCdoDetalleDescuentos}), DataInputValidator::VALIDATE_DESCUENTOS_DOCUMENTO, $other);
                $this->errors = array_merge($this->errors, $errores);
            }

            // Preguntar si esta clave siempre vendra
            if (isset($document->{keyMap::$claveCdoRetencionesSugeridas})) {
                $other = [];
                if (isset($document->cdo_sistema) && $document->cdo_sistema === DataInputValidator::OPENETL_WEB)
                    $other = ['cdo_sistema' => DataInputValidator::OPENETL_WEB];

                if(!empty($document->{keyMap::$claveCdoRetencionesSugeridas})) {
                    $retencionesSugeridas = [];
                    foreach($document->{keyMap::$claveCdoRetencionesSugeridas} as $retencionSugerida) {
                        if($retencionSugerida->tipo == 'RETERENTA')
                            $retencionSugerida->tipo = 'RETEFUENTE';

                        $retencionesSugeridas[] = $retencionSugerida;
                    }

                    if(!empty($retencionesSugeridas))
                        $document->{keyMap::$claveCdoRetencionesSugeridas} = $retencionesSugeridas;
                }

                $errores = $this->engineValidator->validate($this->toArray($document->{keyMap::$claveCdoRetencionesSugeridas}), DataInputValidator::VALIDATE_DETALLES_RETENCIONES_SUGERIDAS_DOCUMENTO, $other);
                $this->errors = array_merge($this->errors, $errores);
            }

            //Items
            foreach ($items as $key => $item) {
                switch ($cabecera['top_codigo']) {
                    case '11':
                        // Si el tipo de operacion es 11 (mandato) y en el json no han enviado ddo_identificador 
                        // o el origen es MANUAL
                        // si el tipo de item es IPT o PCC, se debe enviar en el campo
                        // ddo_identificador el valor 1, de lo contrario 
                        // se envia 0 si tipo de item es IP o el origen es MANUAL
                        if (!isset($items[$key]->ddo_identificador) || (isset($items[$key]->ddo_identificador) && $items[$key]->ddo_identificador == '')) {
                            if ($items[$key]->ddo_tipo_item == 'PCC' || $items[$key]->ddo_tipo_item == 'IPT') {
                                $items[$key]->ddo_identificador = '1';
                            } elseif ($items[$key]->ddo_tipo_item == 'IP' || $this->origen == self::ORIGEN_MANUAL) {
                                // Cuando se carga por el excel y no se especifica el tipo de item se envia por defecto 0
                                // Pero si se carga desde una integracion o API solo se asigna 0 si el tipo de Item es IP
                                $items[$key]->ddo_identificador = '0';
                            }
                        }
                        break;
                    case '12':
                        if ($this->origen == self::ORIGEN_MANUAL) {
                            // Si el tipo de operacion es 12 (transporte) y el origen es MANUAL
                            // se debe enviar en el campo ddo_identificador el valor enviado en el campo ddo_tipo_item
                            if ($cabecera['top_codigo'] == '12') {
                                $items[$key]->ddo_identificador = $items[$key]->ddo_tipo_item;
                            }
                        }
                        break;
                    default:
                        // No hace nada
                        break;
                }
            }

            $other = ['top_codigo' => (array_key_exists('top_codigo', $cabecera) ? $cabecera['top_codigo'] : null), 'ofe' => $ofe];
            if (isset($document->cdo_sistema) && $document->cdo_sistema === DataInputValidator::OPENETL_WEB)
                $other = array_merge($other, ['cdo_sistema' => DataInputValidator::OPENETL_WEB]);
            $errores = $this->engineValidator->validate($items, DataInputValidator::VALIDATE_ITEMS_DOCUMENTO, $other);
            $this->errors = array_merge($this->errors, $errores);

            // Si los items falla no se esta validando los impuestos
            if (count($errores) === 0) {
                if (isset($document->{keyMap::$claveTributos})) {
                    $errores = $this->engineValidator->validate($this->toArray($document->{keyMap::$claveTributos}),
                        DataInputValidator::VALIDATE_TRIBUTOS_DOCUMENTO, ['cdo_envio_dian_moneda_extranjera' => array_key_exists('cdo_envio_dian_moneda_extranjera', $cabecera) ? $cabecera['cdo_envio_dian_moneda_extranjera'] : null], keyMap::$claveTributos);
                    $this->errors = array_merge($this->errors, $errores);
                }
                if (isset($document->{keyMap::$claveRetenciones})) {
                    $errores = $this->engineValidator->validate($this->toArray($document->{keyMap::$claveRetenciones}),
                        DataInputValidator::VALIDATE_TRIBUTOS_DOCUMENTO, ['cdo_envio_dian_moneda_extranjera' => array_key_exists('cdo_envio_dian_moneda_extranjera', $cabecera) ? $cabecera['cdo_envio_dian_moneda_extranjera'] : null], keyMap::$claveRetenciones);
                    $this->errors = array_merge($this->errors, $errores);
                }
            }

            $response = $this->engineValidator->validateValores($document);
            $this->errors = array_merge($this->errors, $response);
        }

        // Si no hay errores y el documento no esta vacio
        if (empty($this->errors))
            $this->successful[] = [
                'documento' => $this->prepareData($document, $cabecera, $dad),
                'json' => $theDoc,
                'est_inicio_proceso' => $est_inicio_proceso,
                'est_fin_proceso' => date('Y-m-d H:i:s')
            ];
        else {
            $consecutivo = '';
            $prefijo = '';
            if (array_key_exists('cdo_consecutivo', $cabecera) && array_key_exists('rfa_prefijo', $cabecera)) {
                $doc = $this->docType . '-' . $cabecera['rfa_prefijo'] . $cabecera['cdo_consecutivo'];
                $consecutivo = $cabecera['cdo_consecutivo'];
                $prefijo = $cabecera['rfa_prefijo'];
            }
            else
                $doc = "documento[".($indice+1)."]";

            $this->failure[] = [
                'documento' => $doc,
                'consecutivo' => $consecutivo,
                'prefijo' => $prefijo,
                'errors' => array_merge($this->errors),
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento' => date('H:i:s')
            ];
        }
    }

    /**
     * Si la entrada es un objeto esta es tranformada en un array.
     *
     * @param $obj
     * @return array
     */
    private function toArray($obj) {
        if (is_object($obj))
            return (array) $obj;
        return $obj;
    }

    /**
     * Prepara la data para ser despachada al gestor de escritura.
     *
     * @param mixed $document
     * @param mixed $cabecera
     * @param mixed $dad
     * @return array
     */
    public function prepareData($document, $cabecera, $dad) {
        $object = [];
        if (isset($document->{keyMap::$claveCdoMedioPago}))
            $object['medios_pago'] = $this->engineValidator->prepareMediosPagos($document->{keyMap::$claveCdoMedioPago});

        $object['cdo_cabecera'] = $this->engineValidator->prepareCabecera($cabecera, $this->docType, $this->lote, (array_key_exists('medios_pago', $object) ? $object['medios_pago'] : []));
        $object['dad'] = $this->engineValidator->prepareDad($dad, $cabecera['cdo_informacion_adicional']);

        if (isset($document->{keyMap::$claveCdoDetalleAnticipos}))
            $object['anticipos'] = $this->engineValidator->prepareAnticipos($document->{keyMap::$claveCdoDetalleAnticipos});

        $contador           = 1;
        $cdo_sistema        = null;
        $ofe_identificacion = null;
        if (isset($document->cdo_sistema) && $document->cdo_sistema === DataInputValidator::OPENETL_WEB) {
            $cdo_sistema        = DataInputValidator::OPENETL_WEB;
            $ofe_identificacion = $document->ofe_identificacion;
        }

        if (isset($document->{keyMap::$claveCdoDetalleCargos}))
            $object['cdo_detalle_cargos'] = $this->engineValidator->prepareDetalleCargos($document->{keyMap::$claveCdoDetalleCargos}, $contador, 'cabecera', $ofe_identificacion, $cdo_sistema);

        if (isset($document->{keyMap::$claveCdoDetalleDescuentos}))
            $object['cdo_detalle_descuentos'] = $this->engineValidator->prepareDetalleDescuentos($document->{keyMap::$claveCdoDetalleDescuentos}, $contador, $ofe_identificacion, $cdo_sistema);

        if (isset($document->{keyMap::$claveCdoRetencionesSugeridas}))
            $object['cdo_detalle_retenciones_sugeridas'] = $this->engineValidator->prepareRetencionesSugeridas($document->{keyMap::$claveCdoRetencionesSugeridas}, $contador, $cdo_sistema);

        if (isset($document->cdo_comprimido) && $document->cdo_comprimido === 'SI' && isset($document->cdo_documento_comprimido)) {
            $items           = json_decode(gzinflate(base64_decode($document->cdo_documento_comprimido)));
            $object['items'] = $this->engineValidator->prepareItems($items, $contador, $ofe_identificacion, $cdo_sistema);
        } else {
            $object['items'] = $this->engineValidator->prepareItems($document->items, $contador, $ofe_identificacion, $cdo_sistema);
        }

        if (isset($document->{keyMap::$claveTributos}))
            $object['tributos'] = $this->engineValidator->prepareImpuestos($document->{keyMap::$claveTributos});

        if (isset($document->{keyMap::$claveRetenciones}))
            $object['retenciones'] = $this->engineValidator->prepareImpuestos($document->{keyMap::$claveRetenciones});

        return $object;
    }

    /**
     * Crea un nuevo agendamiento para proceso PARSER.
     *
     * @param  integer $usu_id Id del usuario relacionado con el agendamiento
     * @param  integer $bdd_id Id de la base de datos
     * @param integer $total Numero de documentos que gestioara el agendamiento
     * @param integer|null $prioridadAgendamiento Indica si es un agendamiento con prioridad
     * @return Illuminate\Database\Eloquent\Collection
     */
    private function crearNuevoAgendamientoUbl($usu_id, $bdd_id, $total, $prioridadAgendamiento = null) {
        return AdoAgendamiento::create([
            'usu_id'                    => $usu_id,
            'bdd_id'                    => $bdd_id,
            'age_proceso'               => 'UBL',
            'age_cantidad_documentos'   => $total,
            'usuario_creacion'          => $usu_id,
            'estado'                    => 'ACTIVO',
            'age_prioridad'             => $prioridadAgendamiento ?? null
        ]);
    }

    /**
     * Almacena la informacion de los documentos electronicos en la db.
     * 
     * @return void
     */
    public function save() {
        $escritos         = [];
        $this->procesados = [];
        $writer           = new WriterInput();

        // Solo se escriben aquellos que han pasado todas las verificaciones
        foreach ($this->successful as $data) {
            $document = $data['documento'];
            $json     = $data['json'];
            $inicio   = $data['est_inicio_proceso'];
            $fin      = $data['est_fin_proceso' ];
            $registro = $writer->store($document, $this->usu_id, $this->procesados, $json, $inicio, $fin, $this->failure);

            if ($registro != null) {
                $doc = $registro['cabecera'];
                $dad = $registro['datos-adicionales'];

                if (!is_null($dad) && isset($dad->cdo_informacion_adicional)) {
                    $cdo_informacion_adicional = $dad->cdo_informacion_adicional;
                    if (
                        (
                            isset($cdo_informacion_adicional['cdo_procesar_documento']) &&
                            $cdo_informacion_adicional['cdo_procesar_documento'] === 'SI'
                        ) || (
                            isset($cdo_informacion_adicional['proceso_automatico']) &&
                            $cdo_informacion_adicional['proceso_automatico'] === 'SI'
                        )
                    )
                        $escritos[] = $doc;
                }
            }
        }

        // Solo los escritos que fueron procesados automaticamente
        if (!empty($escritos)) {
            $user                 = auth()->user();
            $basededatos          = $user->getBaseDatos;
            $cantidadRegistrosUbl = intval($basededatos->bdd_cantidad_procesamiento_ubl);
            $subGrupos            = array_chunk($escritos, $cantidadRegistrosUbl);
            $prioritarios         = [];
            $ofesPrioridadAge     = [];

            ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_prioridad_agendamiento'])
                ->where('estado', 'ACTIVO')
                ->get()
                ->map(function ($item) use (&$prioritarios, &$ofesPrioridadAge) {
                    $ofesPrioridadAge[$item->ofe_id] = $item->ofe_prioridad_agendamiento;

                    if(in_array($item->ofe_identificacion, $this->ofesPrioridad))
                        $prioritarios[] = $item->ofe_id;
                });

            foreach ($subGrupos as $grupo) {
                $prioridad = null;
                foreach ($grupo as $item) {
                    if ($item->cdo_clasificacion === ConstantsDataInput::FC && in_array($item->ofe_id, $prioritarios) && $item->rfa_prefijo == 'CEU' && empty($ofesPrioridadAge[$item->ofe_id])) {
                        $prioridad = 1;
                        break;
                    } elseif (!empty($ofesPrioridadAge[$item->ofe_id])) {
                        $prioridad = $ofesPrioridadAge[$item->ofe_id];
                        break;
                    } 
                }

                $agendamiento = $this->crearNuevoAgendamientoUbl($user->usu_id, $basededatos->bdd_id, count($grupo), $prioridad);
                foreach ($grupo as $item) {
                    // Verifica que NO exista un estado DO Exitoso para el documento
                    $doExitoso = EtlEstadosDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $item->cdo_id)
                        ->where('est_estado', 'DO')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->first();

                    if(!$doExitoso) {
                        $estado                   = new EtlEstadosDocumentoDaop();
                        $estado->cdo_id           = $item->cdo_id;
                        $estado->est_estado       = 'UBL';
                        $estado->age_id           = $agendamiento->age_id;
                        $estado->age_usu_id       = $user->usu_id;
                        $estado->usuario_creacion = $user->usu_id;
                        $estado->estado           = 'ACTIVO';
                        $estado->save();
                    }
                }
            }
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
     * Construye un cliente de Guzzle para consumir los microservicios.
     *
     * @param string $URI
     * @return Client
     */
    private function getCliente(string $URI) {
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
     * Accede microservicio Main para procesar la información del adquirente.
     *
     * @param array $parametros Parametros del adquirente que son enviados en la petición
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function peticionMain($parametros) {
        try {
            // Dependiendo el tipo de documento electronico se consume el endponit para creacion
            // de adquirentes o vendedores
            $endpoint = '/api/configuracion/adquirentes/di-gestion';
            if (isset($parametros[0]->adq_tipo_vendedor_ds) && $parametros[0]->adq_tipo_vendedor_ds == 'SI')
                $endpoint = '/api/configuracion/vendedores-ds/di-gestion';

            $peticionMain = $this->getCliente(config('variables_sistema.APP_URL'))->request(
                'POST',
                config('variables_sistema.MAIN_API_URL') . $endpoint,
                [
                    'json' => ['adquirente' => $parametros],
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getToken()
                    ]
                ]
            );
            return[
                'respuesta' => json_decode((string)$peticionMain->getBody()->getContents()),
                'error' => false
            ];
        } catch (\Exception $e) {
            $response = $e->getResponse();
            return[
                'respuesta' => $response->getBody()->getContents(),
                'error' => true
            ];
        }
    }

    /**
     * Consulta un Adquirente de un OFE en el servidor ETL Principal.
     * 
     * Aplica para OFEs que tienen varios servidores ETL como el caso de DHL Express (CAIA)
     *
     * @param string $ofe_identificacion Identificacion del OFE
     * @param string $adq_identificacion Identificacion del Adquirente
     * @param string $adq_id_personalizado ID Personalizado del adquirente
     * @return array Array que contiene el resultado de la consulta y la data del adquirente si existe
     */
    public function consultarAdquirenteEtlPrincipal($ofe_identificacion, $adq_identificacion, $adq_id_personalizado) {
        try {
            // Usuario autenticado
            $user = auth()->user();

            // Accede al microservicio Main del servidor ETL principal para consultar el Adquirente
            // Dentro de los parámetros se debe enviar el email del usuario autenticado toda vez
            // que por tratarse de servidores diferentes, los IDs de los usuarios pueden NO corresponder
            // Y la consulta retornaría error de autenticación, adicionalmente se incluye un hash único por temas de seguridad
            TenantTrait::GetVariablesSistemaTenant();
            $peticionMain = $this->getCliente(config('variables_sistema_tenant.SERVER_PRINCIPAL_MAIN_API_URL'))->request(
                'POST',
                config('variables_sistema_tenant.SERVER_PRINCIPAL_MAIN_API_PUERTO') . '/api/configuracion/adquirentes/di-gestion-obtener-adquirente',
                [
                    'form_params' => [
                        'ofe_identificacion'   => $ofe_identificacion,
                        'adq_identificacion'   => $adq_identificacion,
                        'adq_id_personalizado' => $adq_id_personalizado,
                        'etl_server_principal' => 'Principal',
                        'usuario_autenticado'  => config('variables_sistema_tenant.DB_PRINCIPAL_EMAIL_AUTENTICACION'),
                        'hash'                 => $this->calcularHash($ofe_identificacion . $adq_identificacion . $adq_id_personalizado . 'Principal' . config('variables_sistema_tenant.DB_PRINCIPAL_EMAIL_AUTENTICACION'))
                    ]
                ]
            );

            return[
                'respuesta' => json_decode((string)$peticionMain->getBody()->getContents()),
                'errors' => false
            ];
        } catch (\Exception $e) {
            $response = $e->getResponse();
            return[
                'respuesta' => $response->getBody()->getContents(),
                'errors' => true
            ];
        }
    }

    /**
     * Calcula un HASH seguro para una cadena basado en algoritmo de encripción sha256.
     *
     * @param string $cadena Cadena sobre la cual se efectua el cálculo
     * @return string Hash calculado
     */
    public function calcularHash($cadena) {
        return hash_hmac(
            'sha256',
            $cadena,
            env('HASH_KEY_PETICIONES')
        );
    }

    /**
     * Verifica si la identificacion del adquirente corresponde a un consumidor final.
     *
     * @param string $adq_identificacion
     * @return bool Indica si el nit enviado es un nit de consumidor final
     */
    private function isConsumidorFinal($adq_identificacion) {
        $adquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'));
        $checkConsumidorFinal = false;
        foreach($adquirenteConsumidorFinal as $consumidorFinal) {
            if ($consumidorFinal->adq_identificacion == $adq_identificacion && !$checkConsumidorFinal) {
                $checkConsumidorFinal = true;
            }
        }
        return $checkConsumidorFinal;
    }
}
