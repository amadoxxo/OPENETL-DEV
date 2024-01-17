<?php

namespace App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use App\Http\Modulos\Recepcion\Recepcion;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

/**
 * Gestiona las tareas relacionadas con la carga de documentos no electrónicos.
 *
 * Class RepCabeceraDocumentoDaopController
 * @package App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop
 */
class RepCabeceraDocumentoDaopController extends Controller {

    /**
     * Grupo de documentos a ser registrados.
     * 
     * @var null|array
     */
    private $sets = null;

    /**
     * Almacena el tipo de documentos que contiene el array.
     *
     * @var string
     */
    private $docType = '';

    /**
     * Array para almacenamiento de errores.
     *
     * @var array
     */
    public $errors = [];

    /**
     * Lista de documentos que no pueden ser registrados.
     *
     * @var array
     */
    private $failure = [];

    /**
     * Lista documentos procesados.
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
     * Instancia de la clase Recepcion.
     *
     * @var Class App\Http\Modulos\Recepcion\Recepcion
     */
    private $recepcion;

    /**
     * Constructor.
     * 
     * RepCabeceraDocumentoDaopController constructor.
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware(['VerificaMetodosRol:RecepcionDocumentoNoElectronicoNuevo,RecepcionDocumentoNoElectronicoEditar'])->only([
            'registrarDocumentoNoElectronico'
        ]);

        $this->recepcion = new Recepcion;
    }

    /**
     * Recibe un documento Json en el request y registra los documentos no electónicos que este contiene.
     *
     * @param Request $request Objeto con la información de la petición
     * @return Response Respuesta de la petición
     */
    public function registrarDocumentoNoElectronico(Request $request) {
        $this->errors = [];

        if (!$request->has('documentos')) {
            return response()->json([
                'message' => 'Error al procesar el documento no electrónico',
                'errors'  => ['La petición esta mal formada, debe especificarse un tipo y objeto JSON']
            ], 422);
        }

        // Se obtiene el número del lote
        $this->lote = Recepcion::buildLote();

        $json = json_decode(json_encode(['documentos' => $request->documentos]));
        $root = $json->{ConstantsDataInput::ROOT};
        $this->validarDataGlobal($root);

        $response = [
            "message"               => "Bloque de información procesado bajo el lote " . $this->lote,
            "lote"                  => $this->lote,
            "documentos_procesados" => $this->procesados,
            "documentos_fallidos"   => $this->failure
        ];

        return $response;
    }

    /**
     * Efectúa el proceso de verificación y construcción de la data global.
     * 
     * @param mixed $data Contiene la información de todos los documentos enviados
     */
    private function validarDataGlobal($data) {
        // Verificando que exista la clave adecuada de tipo de documento y que sea efectivamente un array
        if (!isset($data->NC) && !isset($data->ND) && !isset($data->FC)) {
            return response()->json([
                'message' => 'Error al procesar el documento no electrónico',
                'errors'  => ['No existe una clave de documento FC, NC o ND']
            ], 422);
        }

        $grupos = [
            ConstantsDataInput::FC,
            ConstantsDataInput::NC,
            ConstantsDataInput::ND
        ];

        foreach ($grupos as $grupo) {
            if (isset($data->{$grupo})) {
                $this->sets    = $data->{$grupo};
                $this->docType = $grupo;
            } else {
                // No existe una clave de este tipo en el json, por lo cual debemos intentar con la próxima
                continue;
            }

            // Si el campo con el arreglo de documentos no es un array
            if (!is_array($this->sets)) {
                $this->errors[] = sprintf(ConstantsDataInput::DOCS_ARE_NOT_ARRAY, $this->docType);
                // La clave se esta usando para un objeto pero no para un array, debemos reportar la falla y pasar al próximo objeto
                continue;
            }

            // Filtrando la data para evitar procesar documentos con los mismos prefijos y consecutivos
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
                // Si existe más de un elemento con un mismo código de identificación (prefijo y consecutivo)
                if (count($subgrupo) > 1)
                    $evitar = array_merge($evitar, $subgrupo);
            }

            // Efectuamos los procesos de validación para cada documento
            foreach ($this->sets as $key => $item) {
                if (!in_array($key, $evitar)) {
                    $this->validarDatosDocumento($item, $key);
                } else {
                    $doc = $this->docType . '-' . $item->rfa_prefijo . $item->cdo_consecutivo;
                    $consecutivo = $item->cdo_consecutivo;
                    $prefijo = $item->rfa_prefijo;
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

    /**
     * Efectúa un proceso de validación de los datos del documento y construye la información que será guardada.
     *
     * @param mixed $document Contiene la información de una (NC-ND-FC) a ser registrados
     * @param int $indice Obtiene el número del documento en proceso
     */
    private function validarDatosDocumento($document, $indice) {
        $documento = [];
        $user = auth()->user();

        // Verifica si el Ofe existe en estado ACTIVO
        $ofeId = $this->recepcion->existeOfe($document->ofe_identificacion);
        if($ofeId == '') {
            $this->errors[] = 'El OFE con identificación [' . $document->ofe_identificacion . '] no existe o su estado es INACTIVO.';
        }

        // Verifica si el Proveedor existe en estado ACTIVO
        $proveedorId = $this->recepcion->existeProveedor($ofeId, $document->pro_identificacion);
        if($proveedorId == '') {
            $this->errors[] = 'El Proveedor con identificación [' . $document->pro_identificacion . '] no existe o su estado es INACTIVO.';
        }

        // Verifica si la moneda existe en estado ACTIVO y esta vigente
        $monId = $this->recepcion->obtieneDatoParametrico($this->recepcion->parametricas['moneda'], 'mon_codigo', $document->mon_codigo, 'mon_id');
        if($monId == null) {
            $this->errors[] = 'La moneda con código [' . $document->mon_codigo . '] no existe, su estado es INACTIVO o no esta vigente.';
        }

        // Se construye la información para guardar el documento
        $documento = [
            'ofe_id'                  => $ofeId,
            'pro_id'                  => $proveedorId,
            'cdo_origen'              => 'NO-ELECTRONICO',
            'cdo_clasificacion'       => $this->docType,
            'rfa_prefijo'             => $document->rfa_prefijo,
            'cdo_consecutivo'         => $document->cdo_consecutivo,
            'cdo_lote'                => $this->lote,
            'cdo_fecha'               => $document->cdo_fecha,
            'cdo_hora'                => $document->cdo_hora,
            'mon_id'                  => $monId,
            'cdo_trm'                 => $document->cdo_trm,
            'cdo_trm_fecha'           => $document->cdo_trm_fecha,
            'cdo_vencimiento'         => $document->cdo_vencimiento,
            'cdo_observacion'         => !empty($document->cdo_observacion) ? json_encode($document->cdo_observacion) : null,
            'cdo_valor_sin_impuestos' => $document->cdo_valor_sin_impuestos,
            'cdo_impuestos'           => $document->cdo_impuestos,
            'cdo_total'               => $document->cdo_total,
            'cdo_valor_a_pagar'       => $document->cdo_valor_a_pagar,
            'update'                  => (isset($document->update) && $document->update != '') ? $document->update : '',
            'usuario_creacion'        => $user->usu_id,
            'estado'                  => 'ACTIVO'
        ];

        // Valida el array del documento frente a las reglas de creación del modelo
        $reglas = RepCabeceraDocumentoDaop::$rules;
        $reglas['cdo_fecha']       = 'required|date_format:Y-m-d|before:' . Carbon::now();
        $reglas['cdo_vencimiento'] = 'nullable|date_format:Y-m-d';

        $validador = Validator::make($documento, $reglas);
        if($validador->fails()){
            $this->errors = array_merge($this->errors, $validador->errors()->all());
        }

        if (empty($this->errors)) {
            $this->save($documento);
        } else {
            $prefijo = '';
            $consecutivo = '';
            if (array_key_exists('cdo_consecutivo', $documento) && array_key_exists('rfa_prefijo', $documento)) {
                $doc         = $this->docType . '-' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'];
                $prefijo     = $documento['rfa_prefijo'];
                $consecutivo = $documento['cdo_consecutivo'];
            } else {
                $doc = "documento[".($indice+1)."]";
            }

            $this->failure[] = [
                'documento'           => $doc,
                'consecutivo'         => $consecutivo,
                'prefijo'             => $prefijo,
                'errors'              => array_merge($this->errors),
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento'  => date('H:i:s')
            ];
        }
    }

    /**
     * Permite crear un documento no electrónico en la base de datos.
     * 
     * @param array $informacionDocumento Contiene la información del documento a guardar
     */
    public function save($informacionDocumento) {
        // Verifica si el documento existe para identificar si se debe crear o actualizar
        $documentoRecepcion = RepCabeceraDocumentoDaop::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'estado'])
            ->where('ofe_id', $informacionDocumento['ofe_id'])
            ->where('pro_id', $informacionDocumento['pro_id'])
            ->where('cdo_clasificacion', $informacionDocumento['cdo_clasificacion'])
            ->where('cdo_consecutivo', $informacionDocumento['cdo_consecutivo'])
            ->where(function($query) use ($informacionDocumento) {
                if($informacionDocumento['rfa_prefijo'] == '')
                    $query->where('rfa_prefijo', '');
                else
                    $query->where('rfa_prefijo', $informacionDocumento['rfa_prefijo']);
            })
            ->first();

        // Valida que el documento no exista registrado con un estado ACTIVO
        if ($documentoRecepcion && $documentoRecepcion->estado == 'ACTIVO' && $informacionDocumento['update'] != 'SI') {
            $this->failure[] = [
                'documento'           => $this->docType . '-' . $informacionDocumento['rfa_prefijo'] . $informacionDocumento['cdo_consecutivo'],
                'consecutivo'         => $informacionDocumento['cdo_consecutivo'],
                'prefijo'             => $informacionDocumento['rfa_prefijo'],
                'errors'              => ["El documento no electrónico {$informacionDocumento['rfa_prefijo']}{$informacionDocumento['cdo_consecutivo']} ya esta registrado"],
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento'  => date('H:i:s')
            ];
        } elseif ($documentoRecepcion) {
            $documentoRecepcion->update($informacionDocumento);
        } else {
            $documentoRecepcion = RepCabeceraDocumentoDaop::create($informacionDocumento);
        }

        $this->procesados[] = [
            'cdo_id'              => $documentoRecepcion->cdo_id,
            'rfa_prefijo'         => $documentoRecepcion->rfa_prefijo,
            'cdo_consecutivo'     => $documentoRecepcion->cdo_consecutivo,
            'tipo'                => $this->docType,
            'fecha_procesamiento' => date('Y-m-d'),
            'hora_procesamiento'  => date('H:i:s')
        ];
    }
}
