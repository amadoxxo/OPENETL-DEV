<?php

namespace App\Http\Modulos\DataInputWorker;

use App\Traits\DiTrait;
use Illuminate\Support\Carbon;
use App\Http\Models\AdoAgendamiento;
use App\Http\Modulos\DataInputWorker\helpers;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop\EtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlConsecutivosDocumentos\EtlConsecutivoDocumento;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop\EtlAnticiposDocumentoDaop;
use App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop\EtlMediosPagoDocumentoDaop;
use App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\PickupCash\PryGuiaPickupCash;
use App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop\EtlImpuestosItemsDocumentoDaop;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop\EtlCargosDescuentosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Clase encargada de escribir los registros correspondientes de los documentos electronicos en las diferentes tablas
 * de la tenant correspondiente.
 *
 * Class WriterInput
 * @package App\Http\Modulos\DataInputWorker
 */
class WriterInput {
    use DiTrait;

    /**
     * Instancia de la clase helpers del DataInputWorker
     *
     * @var helpers
     */
    protected $helpers;
    
    // Nombre del proceso en Emisión
    public const PROCESO_EMISION = 'emision';

    // Identificación de DHL Express
    public const NIT_DHLEXPRESS = '860502609';

    // Origen Manual para documentos
    public const ORIGEN_MANUAL  = 'MANUAL';

    public function __construct() {
        $this->helpers = new helpers;
    }

    /**
     * Setee en 0 las columnas numericas que estan en NULL
     *
     * @param array $data
     * @param $keys
     */
    private function setZeros(array &$data, $keys) {
        foreach ($keys as $pvt)
            if (empty($data[$pvt]))
                $data[$pvt] = 0.0;
    }

    private function actualizarEstadoPickupCash($cdo_id) {
        $estadoPickupCash = EtlEstadosDocumentoDaop::select(['est_id', 'est_ejecucion'])
            ->where('cdo_id', $cdo_id)
            ->where('est_estado', 'PICKUP-CASH')
            ->where('est_resultado', 'EXITOSO')
            ->whereNull('est_ejecucion')
            ->first();

        if($estadoPickupCash) {
            $estadoPickupCash->update([
                'est_ejecucion' => 'FINALIZADO'
            ]);
        }
    }

    /**
     * Controla la escritura de un documento electronico en una tenant.
     *
     * @param array $document Array con información del documento electrónico
     * @param int $usu_id ID del usuario autenticado
     * @param array $procesados Array de documentos procesados
     * @param \stdClass $json Objeto json del documento en procesamiento
     * @param string $inicio Fecha y hora de inicio de procesamiento
     * @param string $fin Fecha y hora de finalización de procesamiento
     * @param array $errors Array de errores
     * @return array|null Array conteniendo información sobre el resultado del procesamiento
     */
    public function store(array $document, int $usu_id, array &$procesados, \stdClass $json, string $inicio, string $fin, array &$errors) {
        $header = $document['cdo_cabecera'];
        $header['rfa_prefijo'] = $header['rfa_prefijo'] ?? '';

        try {
            $cdo_informacion_adicional = array_key_exists('dad', $document) && array_key_exists('cdo_informacion_adicional', $document['dad']) ? $document['dad']['cdo_informacion_adicional'] : [] ;

            // true es creación, false es edicion
            $creacion = empty($cdo_informacion_adicional) || !array_key_exists('update', $cdo_informacion_adicional) || strtoupper($cdo_informacion_adicional['update']) !== 'SI';

            if($header['cdo_origen'] === self::ORIGEN_MANUAL) {
                $prefijo = array_key_exists('rfa_prefijo', $header) ? $header['rfa_prefijo'] : '';

                if(
                    (
                        array_key_exists('cdo_control_consecutivos', $header) &&
                        array_key_exists('ofe_identificacion', $header) &&
                        $header['cdo_control_consecutivos'] == 'SI' &&
                        (
                            $header['cdo_clasificacion'] == ConstantsDataInput::FC ||
                            $header['cdo_clasificacion'] == ConstantsDataInput::DS
                        )
                    ) &&
                    (
                        !array_key_exists('cdo_informacion_adicional', $header) ||
                        (
                            array_key_exists('cdo_informacion_adicional', $header) &&
                            (
                                !isset($header['cdo_informacion_adicional']->update) ||
                                (isset($header['cdo_informacion_adicional']->update) && $header['cdo_informacion_adicional']->update !== 'SI')
                            )
                        )
                    )
                ) {
                    $consecutivo = $this->getConsecutivoDocumento($header['ofe_id'], $header['rfa_id'], $prefijo, false, $header['cdo_control_consecutivos'], $header['cdo_consecutivo_provisional']);

                    if($consecutivo === false) {
                        throw new \Exception('No se encuentró un periodo aperturado de acuerdo al inicio de vigencia de la resolución de facturación');
                    } else {
                        $header['cdo_consecutivo'] = $consecutivo;
                    }

                    if($header['cdo_consecutivo_provisional'] == 'SI')
                        $header['estado'] = 'PROVISIONAL';
                    elseif($header['cdo_consecutivo_provisional'] != 'SI')
                        $header['estado'] = 'ACTIVO';
                }

                // Si el OFE es DHL Express y en informacion adicional se envia la bandera actualizar en SI 
                // y es una Factura con RG diferente de 9
                // el documento debe actualizarse y no crearse
                if (
                    array_key_exists('ofe_identificacion', $header) &&
                    $header['ofe_identificacion'] === self::NIT_DHLEXPRESS &&
                    $header['cdo_clasificacion'] == ConstantsDataInput::FC &&
                    $header['cdo_representacion_grafica_documento'] != '9' &&
                    (
                        array_key_exists('actualizar', $cdo_informacion_adicional) &&
                        trim($cdo_informacion_adicional['actualizar']) == 'SI'
                    ) &&
                    $creacion 
                ) {
                    $creacion = false;
                }
            }

            // $creación verifica si en información adicional NO se recibe la llave update
            // Sin embargo se debe consultar si el documento existe, ya que si existe en la data operativa pero su estado es INACTIVO se debe permitir su edición
            if($creacion) {
                // Verifica si existe el documento en la data operativa
                $documento    = $this->helpers->consultarDocumento('daop', $header['tde_id'], $header['ofe_id'], $header);
                $docHistorico = $this->helpers->consultarDocumento('historico', $header['tde_id'], $header['ofe_id'], $header);

                if($documento && $documento->estado == 'INACTIVO') {
                    $creacion = false;
                } elseif (
                    ($documento && $documento->estado != 'INACTIVO') ||
                    (!$documento && $docHistorico)
                ) {
                    throw new \Exception(' El documento [' . $header['cdo_clasificacion'] . ' ' . $header['rfa_prefijo'] . $header['cdo_consecutivo'] . '] del OFE [' . $header['ofe_identificacion'] . '] ya existe');
                }
            }

            // Escribiendo la cabecera
            $this->setZeros($header, ["cdo_valor_sin_impuestos",
                "cdo_valor_sin_impuestos_moneda_extranjera",
                "cdo_impuestos",
                "cdo_impuestos_moneda_extranjera",
                "cdo_retenciones",
                "cdo_retenciones_moneda_extranjera",
                "cdo_total",
                "cdo_total_moneda_extranjera",
                "cdo_cargos",
                "cdo_cargos_moneda_extranjera",
                "cdo_descuentos",
                "cdo_descuentos_moneda_extranjera",
                "cdo_retenciones_sugeridas",
                "cdo_retenciones_sugeridas_moneda_extranjera",
                "cdo_anticipo",
                "cdo_anticipo_moneda_extranjera",
                "cdo_redondeo",
                "cdo_redondeo_moneda_extranjera"
            ]);

            $expressCruzarGuiaPickupCash = false;
            if($creacion) {
                if(array_key_exists('cdo_trm_fecha', $header) && $header['cdo_trm_fecha'] == '') {
                    $header['cdo_trm_fecha'] = null;
                }
                $header['usuario_creacion'] = $usu_id;
                $header['estado'] = array_key_exists('estado', $header) && !empty($header['estado']) ? $header['estado'] :  'ACTIVO';
                $cabecera = new EtlCabeceraDocumentoDaop($header);
                $cabecera->save();

                if(empty($json->cdo_consecutivo)) // Aplica cuando se tiene control de consecutivos porque la propiedad llega vacia
                    $json->cdo_consecutivo = $cabecera->cdo_consecutivo; 

                $archivosMandatoRg = $this->guardarDocumentosCertificadoMandatoPdfbase64($header['ofe_identificacion'], $cabecera, $document, self::PROCESO_EMISION);
            
                // Si el OFE es DHL Express y se trata de una FC con RG 9, se debe verificar si la guía asociada al documento existe en la tabla de histórico
                if (
                    array_key_exists('ofe_identificacion', $header) &&
                    $header['ofe_identificacion'] === self::NIT_DHLEXPRESS &&
                    $header['cdo_clasificacion'] == ConstantsDataInput::FC &&
                    $header['cdo_representacion_grafica_documento'] == '9'
                ) {
                    $guia = '';
                    if(
                        array_key_exists('cdo_informacion_adicional', $document['dad']) &&
                        array_key_exists('guia', $document['dad']['cdo_informacion_adicional']) &&
                        $document['dad']['cdo_informacion_adicional']['guia'] != ''
                    ) {
                        $guia = $document['dad']['cdo_informacion_adicional']['guia'];
                    }

                    // Consulta la guía mas reciente del histórico
                    $existeGuia = PryGuiaPickupCash::where('gpc_guia', $guia)
                        ->where('estado', 'ACTIVO')
                        ->orderBy('gpc_id', 'desc')
                        ->first();

                    if ($existeGuia)
                        $expressCruzarGuiaPickupCash = true;
                }
            } else {
                $cabecera =  EtlCabeceraDocumentoDaop::select(['cdo_id', 'estado', 'fecha_creacion', 'rfa_prefijo', 'cdo_consecutivo'])
                    ->where('tde_id', $header['tde_id'])
                    ->where('ofe_id', $header['ofe_id'])
                    ->where(function($query) use ($header) {
                        if (!is_null($header['rfa_prefijo']) && $header['rfa_prefijo'] !== '')
                            $query->where('rfa_prefijo', $header['rfa_prefijo']);
                        else 
                            $query->whereRaw("(rfa_prefijo IS NULL OR rfa_prefijo = '')");
                    })
                    ->where('cdo_consecutivo', $header['cdo_consecutivo'])
                    ->with([
                        'getDoFinalizado',
                        'getNotificacionDocumento',
                        'getEstadoDo'
                    ])
                    ->first();

                if (!$cabecera) {
                    throw new \Exception("No se puede actualizar el documento porque no existe");
                }

                // Si el documento ya tiene proceso DO o Notificación finalizados, no se debe poder modificar
                if($cabecera->getDoFinalizado || $cabecera->getNotificacionDocumento) {
                    throw new \Exception("No se puede actualizar el documento porque ya fue transmitido a la DIAN y/o fue Notificado");
                }

                // Se verifica si el documento tiene un estado DO que sea el más reciente, que sea fallido y que tenga errores de SecreBlackbox o de reintento
                if($cabecera->getEstadoDo && $cabecera->getEstadoDo->est_resultado == 'FALLIDO') {
                    if(stristr($cabecera->getEstadoDo->est_mensaje_resultado, 'SecureBlackbox library exception') || stristr($cabecera->getEstadoDo->est_mensaje_resultado, 'vuelva a intentarlo'))
                        throw new \Exception("No se puede actualizar el documento porque ya fue transmitido a la DIAN");
                }

                // Estado anterior en cabecera del documento, permite definir si al crear el estado EDI se debe guardar data en informacion_adicional del estado
                $estadoAnteriorCabecera = $cabecera->estado;

                // En la actualización de información del documento, el estado debe quedar en ACTIVO
                if($estadoAnteriorCabecera != 'PROVISIONAL')
                    $header['estado'] = 'ACTIVO';

                // 
                if(array_key_exists('cdo_trm_fecha', $header) && $header['cdo_trm_fecha'] == '') {
                    $header['cdo_trm_fecha'] = null;
                }

                // Actualiza cabecera y limpia dependencias
                $cabecera->update($header);
                $archivosMandatoRg = $this->guardarDocumentosCertificadoMandatoPdfbase64($header['ofe_identificacion'], $cabecera, $document, self::PROCESO_EMISION);
                $this->limpiarDependencias($cabecera);
                
                // Si el OFE es DHL Express y se trata de una FC con RG 9, se debe verificar si tiene un estado PICKUP-CASH sin finalizar para finalizarlo
                $expressPickupCash = false;
                if (
                    array_key_exists('ofe_identificacion', $header) &&
                    $header['ofe_identificacion'] === self::NIT_DHLEXPRESS &&
                    $header['cdo_clasificacion'] == ConstantsDataInput::FC &&
                    $header['cdo_representacion_grafica_documento'] == '9'
                ) {
                    $expressPickupCash = true;
                    $this->actualizarEstadoPickupCash($cabecera->cdo_id);
                }
            }

            //Cargo Descuento Dad
            $cargoDescuentoDad = null;
            if (array_key_exists('dad_detalle_descuentos', $document['dad']) && !is_null($document['dad']['dad_detalle_descuentos']) && is_array($document['dad']['dad_detalle_descuentos'])) {
                // Hay que ajustar el objeto para que pueda ser escrito en la base de datos
                foreach($document['dad']['dad_detalle_descuentos'] as $dadDetalleDescuento) {
                    $objeto = $dadDetalleDescuento;
                    if ((isset($objeto->valor_moneda_nacional) && isset($objeto->valor_moneda_nacional->valor) && floatval($objeto->valor_moneda_nacional->valor) > 0.0)
                        || (isset($objeto->valor_moneda_extranjera) && floatval( $objeto->valor_moneda_extranjera->valor) > 0.0)) {
                        $item = [];
                        $item['cdd_numero_linea'] = $objeto->cdd_numero_linea;
                        $item['cdd_aplica'] = 'INCOTERMS';
                        $item['cdd_tipo'] = $objeto->tipo;
                        $item['cdd_indicador'] = ($objeto->tipo === 'CARGO') ? 'true' : 'false';
                        $item['cdd_razon'] = $objeto->descripcion;
                        $item['cdd_porcentaje'] = $objeto->porcentaje;
                        if (isset($objeto->valor_moneda_nacional)) {
                            $item['cdd_valor'] = $objeto->valor_moneda_nacional->valor;
                            $item['cdd_base'] = $objeto->valor_moneda_nacional->base;
                        }
                        if (isset($objeto->valor_moneda_extranjera)) {
                            $item['cdd_valor_moneda_extranjera'] = $objeto->valor_moneda_extranjera->valor;
                            $item['cdd_base_moneda_extranjera'] = $objeto->valor_moneda_extranjera->base;
                        } else {
                            $item['cdd_valor_moneda_extranjera'] = 0.00;
                            $item['cdd_base_moneda_extranjera'] = 0.00;
                        }
                        $item['cdo_id'] = $cabecera->cdo_id;
                        $item['usuario_creacion'] = $usu_id;
                        $item['estado'] = 'ACTIVO';
                        $cargoDescuentoDad = new EtlCargosDescuentosDocumentoDaop($item);
                        $cargoDescuentoDad->save();
                    }
                    unset($document['dad']['cdo_detalle_descuentos']);
                }
            }

            // Escribiendo DAD
            $dad = $document['dad'];
            $dad['cdo_id'] = $cabecera->cdo_id;
            $dad['usuario_creacion'] = $usu_id;
            $dad['estado'] = 'ACTIVO';

            $datosAdicionales = new EtlDatosAdicionalesDocumentoDaop($dad);
            $datosAdicionales->save();

            // Cargos Cabecera
            if (array_key_exists('cdo_detalle_cargos', $document) && !is_null($document['cdo_detalle_cargos'])) {
                $detallesCargos = $document['cdo_detalle_cargos'];
                foreach ($detallesCargos as $item) {
                    if ((array_key_exists('cdd_valor', $item) && floatval($item['cdd_valor']) > 0.0) ||
                        (array_key_exists('cdd_valor_moneda_extranjera', $item) && floatval($item['cdd_valor_moneda_extranjera']) > 0.0)) {
                        $item['cdd_aplica'] = 'CABECERA';
                        $item['cdd_tipo'] = 'CARGO';
                        $item['cdd_indicador'] = 'true';
                        $item['cdo_id'] = $cabecera->cdo_id;
                        $item['usuario_creacion'] = $usu_id;
                        $item['estado'] = 'ACTIVO';
                        $detallesCargosEscritor = new EtlCargosDescuentosDocumentoDaop($item);
                        $detallesCargosEscritor->save();
                    }
                }
            }

            // Descuentos Cabecera
            if (array_key_exists('cdo_detalle_descuentos', $document) && !is_null($document['cdo_detalle_descuentos'])) {
                $detallesDescuentos = $document['cdo_detalle_descuentos'];
                foreach ($detallesDescuentos as $item) {
                    if ((array_key_exists('cdd_valor', $item) && floatval($item['cdd_valor']) > 0.0) ||
                        (array_key_exists('cdd_valor_moneda_extranjera', $item) && floatval($item['cdd_valor_moneda_extranjera']) > 0.0)) {
                        $item['cdd_aplica'] = 'CABECERA';
                        $item['cdd_tipo'] = 'DESCUENTO';
                        $item['cdd_indicador'] = 'false';
                        $item['cdo_id'] = $cabecera->cdo_id;
                        $item['usuario_creacion'] = $usu_id;
                        $item['estado'] = 'ACTIVO';
                        $detallesDescuentosEscritor = new EtlCargosDescuentosDocumentoDaop($item);
                        $detallesDescuentosEscritor->save();
                    }
                }
            }

            // Retenciones Sugeridas cabecera
            if (array_key_exists('cdo_detalle_retenciones_sugeridas', $document) && !is_null($document['cdo_detalle_retenciones_sugeridas'])) {
                $detallesRetencionesSugeridas = $document['cdo_detalle_retenciones_sugeridas'];
                foreach ($detallesRetencionesSugeridas as $item) {
                    if ((array_key_exists('cdd_valor', $item) && floatval($item['cdd_valor']) > 0.0) ||
                        (array_key_exists('cdd_valor_moneda_extranjera', $item) && floatval($item['cdd_valor_moneda_extranjera']) > 0.0)) {
                        $item['cdd_aplica'] = 'CABECERA';
                        $item['cdd_indicador'] = 'false';
                        $item['cdo_id'] = $cabecera->cdo_id;
                        $item['usuario_creacion'] = $usu_id;
                        $item['estado'] = 'ACTIVO';
                        $detallesRetencionesSugeridasEscritor = new EtlCargosDescuentosDocumentoDaop($item);
                        $detallesRetencionesSugeridasEscritor->save();
                    }
                }
            }

            // medios_pago
            if (array_key_exists('medios_pago', $document) && !is_null($document['medios_pago'])) {
                $mediosPagos = $document['medios_pago'];
                foreach ($mediosPagos as $item) {
                    $item['cdo_id'] = $cabecera->cdo_id;
                    $item['usuario_creacion'] = $usu_id;
                    $item['estado'] = 'ACTIVO';
                    $mediosPagosEscritor = new EtlMediosPagoDocumentoDaop($item);
                    $mediosPagosEscritor->save();
                }
            }

            // anticipos
            if (array_key_exists('anticipos', $document) && !is_null($document['anticipos'])) {
                $anticipos = $document['anticipos'];
                foreach ($anticipos as $item) {
                    if ((array_key_exists('ant_valor', $item) && floatval($item['ant_valor']) > 0.0) ||
                        (array_key_exists('ant_valor_moneda_extranjera', $item) && floatval($item['ant_valor_moneda_extranjera']) > 0.0)) {
                        $item['cdo_id'] = $cabecera->cdo_id;
                        $item['usuario_creacion'] = $usu_id;
                        $item['estado'] = 'ACTIVO';
                        $anticiposEscritor = new EtlAnticiposDocumentoDaop($item);
                        $anticiposEscritor->save();
                    }
                }
            }

            $this->escribirItemsImpuestos($document, $cabecera, $usu_id);
            $procesados[] = [
                "cdo_id" => $cabecera->cdo_id,
                "rfa_prefijo" => $cabecera->rfa_prefijo,
                "cdo_consecutivo" => $cabecera->cdo_consecutivo,
                "tipo" => $cabecera->cdo_clasificacion,
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento' => date('H:i:s')
            ];

            $cdo_documento_integracion = null;
            if (isset($json->cdo_documento_integracion)) {
                $cdo_documento_integracion = $json->cdo_documento_integracion;
                unset($json->cdo_documento_integracion);
            }

            $nombreArchivos     = [];
            try {
                $archivoJson = $this->guardarArchivoEnDisco($header['ofe_identificacion'], $cabecera, self::PROCESO_EMISION, 'json', 'json', base64_encode(json_encode($json)));
            } catch(\Exception $e) {
                // No guarda el archivo JSON en el disco, entonces la Exception se guarda en el campo est_informacion_adicional
                // de la tabla etl_estados_documentos_daop en el estado EDI del documento
                $nombreArchivos = [
                    'errors'              => ['Se produjo un error al guardar el archivo JSON en el disco'],
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s'),
                    'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                ];
            }

            if($cdo_documento_integracion) {
                try {
                    $archivoProcesado = $this->guardarArchivoEnDisco($header['ofe_identificacion'], $cabecera, self::PROCESO_EMISION, null, null, $cdo_documento_integracion, $document['dad']['cdo_informacion_adicional']['archivoprocesado']);
                } catch(\Exception $e) {
                    // No guarda el archivo de integración en el disco, entonces la Exception se guarda en el campo est_informacion_adicional
                    // de la tabla etl_estados_documentos_daop en el estado EDI del documento
                    $nombreArchivos = [
                        'errors'              => ['Se produjo un error al guardar el archivo de integración en disco'],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                    ];
                }
            }

            $estado                           = new EtlEstadosDocumentoDaop();
            $estado->cdo_id                   = $cabecera->cdo_id;
            $estado->est_estado               = 'EDI';
            $estado->est_resultado            = 'EXITOSO';
            $estado->est_ejecucion            = 'FINALIZADO';
            $estado->est_inicio_proceso       = $inicio;
            $estado->est_fin_proceso          = $fin;
            $estado->est_tiempo_procesamiento = strtotime($fin)  - strtotime($inicio);
            $estado->usuario_creacion         = $usu_id;
            $estado->estado                   = 'ACTIVO';

            if(isset($archivosMandatoRg) && !empty($archivosMandatoRg)) {
                if(array_key_exists('certificado', $archivosMandatoRg) && !empty($archivosMandatoRg['certificado']))
                    $nombreArchivos['certificado'] = $archivosMandatoRg['certificado'];

                if(array_key_exists('est_archivo', $archivosMandatoRg) && !empty($archivosMandatoRg['est_archivo']))
                    $nombreArchivos['est_archivo'] = $archivosMandatoRg['est_archivo'];
            }

            if(!empty($archivoJson))
                $nombreArchivos['est_json'] = $archivoJson;

            if(isset($archivoProcesado) && !empty($archivoProcesado))
                $nombreArchivos['archivoprocesado'] = $archivoProcesado;

            if (
                (!$creacion && isset($estadoAnteriorCabecera) && $estadoAnteriorCabecera == 'ACTIVO') ||
                (!$creacion && isset($expressPickupCash) && $expressPickupCash)
            ) {
                $estado->est_informacion_adicional = json_encode(array_merge(['update' => true], $nombreArchivos));
            } else {
                $estado->est_informacion_adicional = json_encode($nombreArchivos);
            }

            $estado->save();

            // Si el OFE es DHL Express y se trata de una FC con RG 9 y existe la guía en el histórico, se realiza el proceso de cruzar la guía con el documento en proceso
            if ($expressCruzarGuiaPickupCash && $existeGuia) {
                $existeGuia = $existeGuia->toArray();

                $datosAdicionalesGuias = EtlDatosAdicionalesDocumentoDaop::select(['dad_id'])
                    ->where('cdo_informacion_adicional->guia', $existeGuia['gpc_guia'])
                    ->whereHas('getCabeceraDocumentosDaop',function($query) {
                        $query->select(['cdo_id'])
                            ->where('cdo_representacion_grafica_documento', '9')
                            ->where(function($query) {
                                $query->where('estado', 'ACTIVO')
                                    ->orWhere('estado', 'PROVISIONAL');
                            });
                    })
                    ->get();

                // Si la guía existe en mas de un documento o el documento tiene mas de un ítem, no se cruza la guía con el documento
                if(!(count($datosAdicionalesGuias) > 1 || count($document['items']) > 1)) {
                    $this->actualizarDocumentoPickupCash($datosAdicionales, $existeGuia);

                    // Se crea el array con la información de la guía para ser almacenada en información adicional del estado
                    if ($existeGuia['gpc_interfaz'] == 'WEB') {
                        $valoresGuia = [
                            'fecha_generacion_awb' => $existeGuia['gpc_fecha_generacion_awb'],
                            'guia'                 => $existeGuia['gpc_guia'],
                            'oficina_venta'        => $existeGuia['gpc_oficina_venta'],
                            'numero_nota'          => $existeGuia['gpc_numero_nota'],
                            'importe_total'        => $existeGuia['gpc_importe_total'],
                            'cuenta_cliente'       => $existeGuia['gpc_cuenta_cliente'],
                            'route_code'           => $existeGuia['gpc_route_code']
                        ];
                    } else {
                        $valoresGuia = [
                            'fecha_factura'        => $existeGuia['gpc_fecha_factura'],
                            'fecha_generacion_awb' => $existeGuia['gpc_fecha_generacion_awb'],
                            'cuenta_cliente'       => $existeGuia['gpc_cuenta_cliente'],
                            'paquete_documento'    => $existeGuia['gpc_paquete_documento'],
                            'guia'                 => $existeGuia['gpc_guia'],
                            'codigo_estacion'      => $existeGuia['gpc_codigo_estacion'],
                            'oficina_venta'        => $existeGuia['gpc_oficina_venta'],
                            'organizacion_ventas'  => $existeGuia['gpc_organizacion_ventas'],
                            'numero_externo'       => $existeGuia['gpc_numero_externo'],
                            'numero_nota'          => $existeGuia['gpc_numero_nota'],
                            'estacion_origen'      => $existeGuia['gpc_estacion_origen'],
                            'estacion_destino'     => $existeGuia['gpc_estacion_destino'],
                            'texto_final'          => $existeGuia['gpc_texto_final'],
                            'importe_total'        => $existeGuia['gpc_importe_total']
                        ];
                    }
                   
                    // Compara el importe total contra el campo cdo_valor_a_pagar, si son iguales
                    // se marca el registro para envío a la DIAN, se crea el estado UBL y verifica
                    // si existe estado PICKUP-CASH NO finalizado para actualizarlo y finalizarlo
                    if(($existeGuia['gpc_importe_total']+0) == ($cabecera->cdo_valor_a_pagar+0)) {
                        $crearEstadoPickupCashFinalizado = true;
                        //Se incluye lógica para generar consecutivo difinitivo, cuando el estado del documento es provisional
                        if ($cabecera->estado == 'PROVISIONAL') {
                            $consecutivo = $this->getConsecutivoDocumento($cabecera->ofe_id, $cabecera->rfa_id, trim($cabecera->rfa_prefijo), false, 'SI', 'NO');

                            if($consecutivo === false)
                                $crearEstadoPickupCashFinalizado = false;
                            else {
                                $actualizarConsecutivo = EtlCabeceraDocumentoDaop::select('cdo_id')->where('cdo_id', $cabecera->cdo_id)
                                    ->update([
                                        'cdo_consecutivo' => $consecutivo,
                                        'estado'          => 'ACTIVO'
                                    ]);

                                if (!$actualizarConsecutivo)
                                    $crearEstadoPickupCashFinalizado = false;
                            }
                        }

                        if ($crearEstadoPickupCashFinalizado == true) {
                            $this->creaActualizaEstadoPickupCash($cabecera->cdo_id, $valoresGuia, $inicio, date('Y-m-d H:i:s'), strtotime(date('Y-m-d H:i:s')) - strtotime($inicio), 'FINALIZADO', auth()->user()->usu_id);

                            // Verifica que NO exista un estado DO Exitoso para el documento
                            $doExitoso = EtlEstadosDocumentoDaop::select(['est_id'])
                                ->where('cdo_id', $cabecera->cdo_id)
                                ->where('est_estado', 'DO')
                                ->where('est_resultado', 'EXITOSO')
                                ->where('est_ejecucion', 'FINALIZADO')
                                ->first();

                            if(!$doExitoso) {
                                $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_prioridad_agendamiento'])
                                    ->where('ofe_id', $cabecera->ofe_id)
                                    ->first();

                                $agendamiento = AdoAgendamiento::create([
                                    'usu_id'                    => auth()->user()->usu_id,
                                    'bdd_id'                    => auth()->user()->getBaseDatos->bdd_id, 
                                    'age_proceso'               => 'UBL',
                                    'age_cantidad_documentos'   => 1,
                                    'age_prioridad'             => $ofe && !empty($ofe->ofe_prioridad_agendamiento) ? $ofe->ofe_prioridad_agendamiento : null,
                                    'usuario_creacion'          => auth()->user()->usu_id,
                                    'estado'                    => 'ACTIVO'
                                ]);

                                $this->crearEstado(
                                    $cabecera->cdo_id,
                                    'UBL',
                                    null,
                                    null,
                                    $agendamiento->age_id,
                                    auth()->user()->usu_id,
                                    null,
                                    null,
                                    null,
                                    null,
                                    auth()->user()->usu_id,
                                    'ACTIVO'
                                );

                                // Actualiza en cabecera los campos de documento procesado
                                $cabecera->update([
                                    'cdo_procesar_documento'       => 'SI',
                                    'cdo_fecha_procesar_documento' => date('Y-m-d H:i:s')
                                ]);
                            }
                        } else 
                            //Si se genero error al actualizar a estado DEFINITIVO se actualiza estado pickucash pero no finalizado
                            $this->creaActualizaEstadoPickupCash($cabecera->cdo_id, $valoresGuia, $inicio, date('Y-m-d H:i:s'), strtotime(date('Y-m-d H:i:s')) - strtotime($inicio), null, auth()->user()->usu_id);
                    } else {
                        $this->creaActualizaEstadoPickupCash($cabecera->cdo_id, $valoresGuia, $inicio, date('Y-m-d H:i:s'), strtotime(date('Y-m-d H:i:s')) - strtotime($inicio), null, auth()->user()->usu_id);
                    }

                    // Se borra del histórico la guía que cruzo y las guías que se encuentren repetidas
                    $this->eliminarGuiasPickupCash($existeGuia['gpc_guia']);
                }
            }

            return ['cabecera' => $cabecera, 'datos-adicionales' => $datosAdicionales];
        } catch (\Exception $e) {
            if(
                isset($document) &&
                isset($document['dad']) &&
                isset($document['dad']['cdo_informacion_adicional']) &&
                array_key_exists('dad', $document) &&
                array_key_exists('cdo_informacion_adicional', $document['dad']) &&
                array_key_exists('guia', $document['dad']['cdo_informacion_adicional']) &&
                $document['dad']['cdo_informacion_adicional']['guia'] != ''
            ) {
                $guia = $document['dad']['cdo_informacion_adicional']['guia'];
            }
            $falla = [
                'documento'           => $header['cdo_clasificacion'],
                'consecutivo'         => $header['cdo_consecutivo'],
                'prefijo'             => $header['rfa_prefijo'],
                'errors'              => (isset($guia)) ? ['Se generó un error al crear el documento. Guía [' . $guia . '], Error: ' . $e->getMessage()] : [$e->getMessage()],
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento'  => date('H:i:s'),
                'traza'               => [$e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()]
            ];
            $errors[] = $falla;
            return null;
        }
    }

    /**
     * LLeva a cabo el registro de items, impuestos, autorenciones, cargos y descuentos asociados.
     *
     * @param array $document Información del documento
     * @param EtlCabeceraDocumentoDaop $cabecera Colección del registro de cabecera
     * @param int $usu_id ID del usuario
     */
    private function escribirItemsImpuestos(array $document, $cabecera, int $usu_id) {
        // items
        $items = $document['items'];
        $ids = [];
        foreach ($items as $item) {
            $ddo_cargos = $item['ddo_cargos'];
            $ddo_descuentos = $item['ddo_descuentos'];
            unset($item['ddo_cargos']);
            unset($item['ddo_descuentos']);

            // Escribiendo el item
            $item['cdo_id'] = $cabecera->cdo_id;
            $item['usuario_creacion'] = $usu_id;
            $item['estado'] = 'ACTIVO';
            if (isset($item['ddo_notas']) && empty($item['ddo_notas']))
                $item['ddo_notas'] = "[]";
            $itemEscritor = new EtlDetalleDocumentoDaop($item);
            $itemEscritor->save();

            $ids[$itemEscritor->ddo_secuencia] = $itemEscritor->ddo_id;

            // Retenciones sugeridas Item - Se procesan en primer lugar dado que el campo cdd_numero_linea es un consecutivo derivado de los cargos/descuentos de cabecera
            if (array_key_exists('ddo_detalle_retenciones_sugeridas', $item) && is_array($item['ddo_detalle_retenciones_sugeridas'])) {
                foreach ($item['ddo_detalle_retenciones_sugeridas'] as $retencionSugerida) {
                    if ((array_key_exists('cdd_valor', $retencionSugerida) && floatval($retencionSugerida['cdd_valor']) > 0.0) ||
                        (array_key_exists('cdd_valor_moneda_extranjera', $retencionSugerida) && floatval($retencionSugerida['cdd_valor_moneda_extranjera']) > 0.0)) {
                        $retencionSugerida['cdd_aplica']       = 'DETALLE';
                        $retencionSugerida['cdd_indicador']    = 'false';
                        $retencionSugerida['cdo_id']           = $cabecera->cdo_id;
                        $retencionSugerida['ddo_id']           = $itemEscritor->ddo_id;
                        $retencionSugerida['usuario_creacion'] = $usu_id;
                        $retencionSugerida['estado']           = 'ACTIVO';
                        $detallesDescuentosEscritor            = new EtlCargosDescuentosDocumentoDaop($retencionSugerida);
                        $detallesDescuentosEscritor->save();
                    }
                }
            }

            // Cargos Item
            if (is_array($ddo_cargos)) {
                foreach ($ddo_cargos as $cargo) {
                    if ((array_key_exists('cdd_valor', $cargo) && floatval($cargo['cdd_valor']) > 0.0) ||
                        (array_key_exists('cdd_valor_moneda_extranjera', $cargo) && floatval($cargo['cdd_valor_moneda_extranjera']) > 0.0)) {
                        $cargo['cdd_aplica']       = 'DETALLE';
                        $cargo['cdd_tipo']         = 'CARGO';
                        $cargo['cdd_indicador']    = 'true';
                        $cargo['cdo_id']           = $cabecera->cdo_id;
                        $cargo['ddo_id']           = $itemEscritor->ddo_id;
                        $cargo['usuario_creacion'] = $usu_id;
                        $cargo['estado']           = 'ACTIVO';
                        $detallesCargosEscritor = new EtlCargosDescuentosDocumentoDaop($cargo);
                        $detallesCargosEscritor->save();
                    }
                }
            }

            // Descuentos Item
            if (is_array($ddo_descuentos)) {
                foreach ($ddo_descuentos as $descuento) {
                    if ((array_key_exists('cdd_valor', $descuento) && floatval($descuento['cdd_valor']) > 0.0) ||
                        (array_key_exists('cdd_valor_moneda_extranjera', $descuento) && floatval($descuento['cdd_valor_moneda_extranjera']) > 0.0)) {
                        $descuento['cdd_aplica']       = 'DETALLE';
                        $descuento['cdd_tipo']         = 'DESCUENTO';
                        $descuento['cdd_indicador']    = 'false';
                        $descuento['cdo_id']           = $cabecera->cdo_id;
                        $descuento['ddo_id']           = $itemEscritor->ddo_id;
                        $descuento['usuario_creacion'] = $usu_id;
                        $descuento['estado']           = 'ACTIVO';
                        $detallesDescuentosEscritor = new EtlCargosDescuentosDocumentoDaop($descuento);
                        $detallesDescuentosEscritor->save();
                    }
                }
            }
        }

        if (array_key_exists('tributos', $document) && !is_null($document['tributos'])) {
            $tributos = $document['tributos'];
            foreach ($tributos as $impuesto ) {
                //impuesto por porcentaje
                if ((array_key_exists('iid_base', $impuesto) && floatval($impuesto['iid_base']) > 0.0) ||
                    (array_key_exists('iid_base_moneda_extranjera', $impuesto) && floatval($impuesto['iid_base_moneda_extranjera']) > 0.0) ||
                    (array_key_exists('iid_base_unidad_medida', $impuesto) && floatval($impuesto['iid_valor_unitario']) > 0.0) ||
                    (array_key_exists('iid_base_unidad_medida_moneda_extranjera', $impuesto) && floatval($impuesto['iid_valor_unitario_moneda_extranjera']) > 0.0)) {
                    $impuesto['cdo_id'] = $cabecera->cdo_id;
                    $impuesto['ddo_id'] = array_key_exists($impuesto['ddo_secuencia'], $ids) ? $ids[$impuesto['ddo_secuencia']] : null;
                    $impuesto['usuario_creacion'] = $usu_id;
                    $impuesto['estado'] = 'ACTIVO';
                    unset($impuesto['ddo_secuencia']);
                    $impuestoEscritor = new EtlImpuestosItemsDocumentoDaop($impuesto);
                    $impuestoEscritor->save();
                }
                //impuesto por unidad
            }
        }

        if (array_key_exists('retenciones', $document) && !is_null($document['retenciones'])) {
            $retenciones = $document['retenciones'];
            foreach ($retenciones as $retencion ) {
                //impuesto por porcentaje
                if ((array_key_exists('iid_base', $retencion) && floatval($retencion['iid_base']) > 0.0) ||
                    (array_key_exists('iid_base_moneda_extranjera', $retencion) && floatval($retencion['iid_base_moneda_extranjera']) > 0.0)) {
                    $retencion['cdo_id'] = $cabecera->cdo_id;
                    $retencion['ddo_id'] = array_key_exists($retencion['ddo_secuencia'], $ids) ? $ids[$retencion['ddo_secuencia']] : null;
                    $retencion['usuario_creacion'] = $usu_id;
                    $retencion['estado'] = 'ACTIVO';
                    unset($retencion['ddo_secuencia']);
                    $impuestoEscritor = new EtlImpuestosItemsDocumentoDaop($retencion);
                    $impuestoEscritor->save();
                }
                //impuesto por unidad
            }
        }
    }

    /**
     * Limpia los datos asociados en cabecera para llevar a acabo los procesos de edición.
     *
     * @param EtlCabeceraDocumentoDaop $cabecera
     */
    private function limpiarDependencias(EtlCabeceraDocumentoDaop $cabecera) {
        EtlMediosPagoDocumentoDaop::where('cdo_id', $cabecera->cdo_id)->delete();
        EtlDatosAdicionalesDocumentoDaop::where('cdo_id', $cabecera->cdo_id)->delete();
        EtlAnticiposDocumentoDaop::where('cdo_id', $cabecera->cdo_id)->delete();
        EtlCargosDescuentosDocumentoDaop::where('cdo_id', $cabecera->cdo_id)->delete();
        EtlImpuestosItemsDocumentoDaop::where('cdo_id', $cabecera->cdo_id)->delete();
        EtlDetalleDocumentoDaop::where('cdo_id', $cabecera->cdo_id)->delete();
    }

    /**
     * Permite generar consecutivos de documentos electrónicos.
     *
     * @param int $ofe_id ID del OFE
     * @param int $rfa_id ID de la resolución de facturación
     * @param string|null $rfa_prefijo Prefijo del documento
     * @param bool $dhlExpress Indica se trata de DHL Express en el proceso especial de generación de consecutivos
     * @param string|null $resolucionControlConsecutivos Indica si para la resolucion del documento aplica o no el control de consecutivos
     * @param string|null $resolucionConsecutivoProvisional Indica si para la resolucion del documento aplica o no el consecutivo provisional
     * @return string|bool $cdo_consecutivo Consecutivo generado
     */
    public function getConsecutivoDocumento(int $ofe_id, int $rfa_id, $rfa_prefijo, bool $dhlExpress, $resolucionControlConsecutivos = 'NO', $resolucionConsecutivoProvisional = 'NO') {
        if($dhlExpress) {
            $ultimoConsecutivo = EtlCabeceraDocumentoDaop::select([\DB::raw('MAX(ABS(cdo_consecutivo)) AS cdo_consecutivo')])
                ->where('cdo_clasificacion', 'FC')
                ->where('ofe_id', $ofe_id)
                ->where('rfa_id', $rfa_id)
                ->where('rfa_prefijo', trim($rfa_prefijo))
                ->where('estado', 'ACTIVO')
                ->first();

            if(!is_null($ultimoConsecutivo->cdo_consecutivo)) {
                $cdo_consecutivo = (string)(intval($ultimoConsecutivo->cdo_consecutivo) + 1);

                return $cdo_consecutivo;
            } else {
                // No se encontró consecutivo, debe consultar la resolución y traer el consecutivo inicial
                $resolucionFacturacion = ConfiguracionResolucionesFacturacion::where('rfa_id', $rfa_id)
                    ->where('estado', 'ACTIVO')
                    ->first();
                $cdo_consecutivo = $resolucionFacturacion->rfa_consecutivo_inicial;

                return $cdo_consecutivo;
            }
        } else {
            if($resolucionControlConsecutivos == 'SI') {
                if($resolucionConsecutivoProvisional == 'SI') {
                    $etlConsecutivoDocumento = $this->consultarEtlConsecutivoDocumento($ofe_id, $rfa_id, trim($rfa_prefijo), 'PROVISIONAL');

                    if($etlConsecutivoDocumento) {
                        $cdo_consecutivo = 'P' . $etlConsecutivoDocumento->cdo_consecutivo;
            
                        $etlConsecutivoDocumento->update([
                            'cdo_consecutivo' => (string)(intval($etlConsecutivoDocumento->cdo_consecutivo) + 1)
                        ]);
            
                        return $cdo_consecutivo;
                    } else {
                        return false;
                    }
                } else {
                    $etlConsecutivoDocumento = $this->consultarEtlConsecutivoDocumento($ofe_id, $rfa_id, trim($rfa_prefijo), 'DEFINITIVO');

                    if($etlConsecutivoDocumento) {
                        $cdo_consecutivo = $etlConsecutivoDocumento->cdo_consecutivo;
            
                        $etlConsecutivoDocumento->update([
                            'cdo_consecutivo' => (string)(intval($etlConsecutivoDocumento->cdo_consecutivo) + 1)
                        ]);
            
                        return $cdo_consecutivo;
                    } else {
                        return false;
                    }
                }
            }
        }
    }

    /**
     * Consulta el modelo Tenant de Consecutivo de Documento en busca de un consecutivo provisional para la resolución y prefijo del documento.
     *
     * @param int $ofe_id ID del OFE
     * @param int $rfa_id ID de la resolución de facturación
     * @param string $rfa_prefijo Prefijo de la resolución de facturación
     * @param string $tipoConsecutivo Tipo de consecutivo a consultas (PROVISIONAL/DEFINITIVO)
     * @return null|EtlConsecutivoDocumento Colección del consecutivo encontrado
     */
    private function consultarEtlConsecutivoDocumento(int $ofe_id, int $rfa_id, string $rfa_prefijo, string $tipoConsecutivo) {
        $etlConsecutivoDocumento = EtlConsecutivoDocumento::where('ofe_id', $ofe_id)
            ->where('rfa_id', $rfa_id)
            ->where('rfa_prefijo', trim($rfa_prefijo))
            ->where('cdo_tipo_consecutivo', $tipoConsecutivo)
            ->where('cdo_periodo', date('Ym'))
            ->where('estado', 'ACTIVO')
            ->lockForUpdate()
            ->first();

        if($etlConsecutivoDocumento) {
            return $etlConsecutivoDocumento;
        } else {
            $resolucionFacturacion = ConfiguracionResolucionesFacturacion::select(['rfa_fecha_desde'])
                ->where('rfa_id', $rfa_id)
                ->where('estado', 'ACTIVO')
                ->first();

            // Calcula la diferencia en meses desde el mes actual hasta el mes de inicio de vigencia de la resolución
            $mesActual  = Carbon::parse(date('Y-m-d'))->floorMonth();
            $mesInicial = Carbon::parse($resolucionFacturacion->rfa_fecha_desde)->floorMonth();
            $meses      = $mesInicial->diffInMonths($mesActual);

            for($contMes = 1; $contMes <= $meses; $contMes++) {
                $etlConsecutivoDocumento = EtlConsecutivoDocumento::where('ofe_id', $ofe_id)
                    ->where('rfa_id', $rfa_id)
                    ->where('rfa_prefijo', trim($rfa_prefijo))
                    ->where('cdo_tipo_consecutivo', $tipoConsecutivo)
                    ->where('cdo_periodo', Carbon::now()->subMonths($contMes)->format('Ym'))
                    ->where('estado', 'ACTIVO')
                    ->lockForUpdate()
                    ->first();

                if($etlConsecutivoDocumento) {
                    $nuevoConsecutivoDocumento = EtlConsecutivoDocumento::create([
                        'ofe_id'               => $ofe_id,
                        'rfa_id'               => $rfa_id,
                        'cdo_tipo_consecutivo' => $tipoConsecutivo,
                        'cdo_periodo'          => date('Ym'),
                        'rfa_prefijo'          => trim($rfa_prefijo),
                        'cdo_consecutivo'      => $etlConsecutivoDocumento->cdo_consecutivo,
                        'usuario_creacion'     => auth()->user()->usu_id,
                        'estado'               => 'ACTIVO'
                    ]);

                    return $nuevoConsecutivoDocumento;
                }
            }

            return null;
        }
    }

    /**
     * Crea o actualiza el estado PICKUP-CASH para el documento.
     *
     * @param int $cdo_id ID del documento
     * @param array $valores Array con valores a almacenar como objeto Json
     * @param datetime $fechaIncio Fecha y hora de inicio de procesamiento
     * @param datetime $fechaFin Fecha y hora final de procesamiento
     * @param float $tiempo Tiempo total de procesamiento
     * @param string $ejecucion Esatdo final de procesamiento, puede ser null
     * @param int $usu_id ID del usuario relacionado con el procesamiento
     * @return void
     */
    public function creaActualizaEstadoPickupCash(int $cdo_id, array $valores, $fechaInicio, $fechaFin, float $tiempo, $ejecucion, int $usu_id) {
        // Verifica si existe un estado PICKUP-CASH No Finalizado para actualizarlo, de lo contrario se crea el estado
        $pickupCash = $this->consultaEstadoPickupCash($cdo_id);

        if($pickupCash) {
            $pickupCash->update([
                'est_resultado'             => 'EXITOSO',
                'est_informacion_adicional' => json_encode($valores),
                'est_inicio_proceso'        => $fechaInicio,
                'est_fin_proceso'           => $fechaFin,
                'est_tiempo_procesamiento'  => $tiempo,
                'est_ejecucion'             => $ejecucion
            ]);
        } else {
            $this->crearEstado(
                $cdo_id,
                'PICKUP-CASH',
                'EXITOSO',
                json_encode($valores),
                null,
                null,
                $fechaInicio,
                $fechaFin,
                $tiempo,
                $ejecucion,
                $usu_id,
                'ACTIVO'
            );
        }
    }

    /**
     * Consulta en los estados del documento para validar si existe un estado PICKUP-CASH.
     *
     * @param int $cdo_id ID del documento
     * @return EtlEstadosDocumentoDaop Colleción con la ifnromación dle estado o null si no existe
     */
    private function consultaEstadoPickupCash(int $cdo_id) {
        return EtlEstadosDocumentoDaop::where('cdo_id', $cdo_id)
            ->where('est_estado', 'PICKUP-CASH')
            ->whereNull('est_ejecucion')
            ->orderBy('est_id', 'desc')
            ->first();
    }

    /**
     * Crea un estado para el documento.
     *
     * @param int $cdo_id ID del documento
     * @param string $estadoDescripcion Descripción del estado
     * @param string $resultado Resultado del estado
     * @param string $informacionAdicional Objeto conteniendo información relacionada con el objeto
     * @param int $age_id ID agendamiento
     * @param int $age_usu_id ID del usuario relacionadoc con el agendamiento
     * @param string $inicio Fecha y hora de inicio de procesamiento
     * @param string $fin Fecha y hora final de procesamiento
     * @param float $tiempo Tiempo de procesamiento
     * @param string $ejecucion Estado final de procesamiento
     * @param int $usuario ID del usuario relacionado con el procesamiento
     * @param string $estadoRegistro Estado del registro
     * @return void
     */
    public function crearEstado(int $cdo_id, string $estadoDescripcion, $resultado, $informacionAdicional, $age_id, $age_usu_id, $inicio, $fin, $tiempo, $ejecucion, int $usuario, string $estadoRegistro) {
        EtlEstadosDocumentoDaop::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estadoDescripcion,
            'est_resultado'             => $resultado,
            'est_informacion_adicional' => $informacionAdicional,
            'age_id'                    => $age_id,
            'age_usu_id'                => $age_usu_id,
            'est_inicio_proceso'        => $inicio,
            'est_fin_proceso'           => $fin,
            'est_tiempo_procesamiento'  => $tiempo,
            'est_ejecucion'             => $ejecucion,
            'usuario_creacion'          => $usuario,
            'estado'                    => $estadoRegistro,
        ]);
    }

    /**
     * Permite actualizar la información adicional y la fecha de vencimiento del medio de pago para el documento en proceso.
     *
     * @param EtlDatosAdicionalesDocumentoDaop $datosAdicionalesDocumento
     * @param array  $valoresGuia Información de la guía
     * @param string $origen Indica el origen del proceso
     * @return void
     */
    public function actualizarDocumentoPickupCash($datosAdicionalesDocumento, array $valoresGuia, string $origen = '') {
        $prefijo = ($origen == '') ? 'gpc_' : '';
        
        // Agrega la cuenta a la información adicional del documento
        if(!empty($datosAdicionalesDocumento->cdo_informacion_adicional)) {
            $informacionAdicional = $datosAdicionalesDocumento->cdo_informacion_adicional;
            $informacionAdicional['cuenta'] = $valoresGuia[$prefijo.'cuenta_cliente'];
        } else {
            $informacionAdicional['cuenta'] = $valoresGuia[$prefijo.'cuenta_cliente'];
        }

        $datosAdicionalesDocumento->update([
            'cdo_informacion_adicional' => $informacionAdicional
        ]);
    
        if(strlen($valoresGuia[$prefijo.'fecha_generacion_awb']) > 8) {
            if(\DateTime::createFromFormat('m/d/Y H:i:s A', $valoresGuia[$prefijo.'fecha_generacion_awb']) !== false)
                $menFechaVencimiento = \DateTime::createFromFormat('m/d/Y H:i:s A', $valoresGuia[$prefijo.'fecha_generacion_awb'])->format('Y-m-d');
            else
                $menFechaVencimiento = \DateTime::createFromFormat('m/d/Y H:i:s', $valoresGuia[$prefijo.'fecha_generacion_awb'])->format('Y-m-d');    
        } else
            $menFechaVencimiento = substr($valoresGuia[$prefijo.'fecha_factura'], 0, 4) . '-' . substr($valoresGuia[$prefijo.'fecha_factura'], 4, 2) . '-' . substr($valoresGuia[$prefijo.'fecha_factura'], 6, 2);

        EtlMediosPagoDocumentoDaop::where('cdo_id', $datosAdicionalesDocumento->cdo_id)
            ->update([
                'men_fecha_vencimiento' => $menFechaVencimiento
            ]);
    }

    /**
     * Elimina la guía de la tabla de histórico y las demás guías que se encuentren repetidas.
     *
     * @param string $guia Número de guía a eliminar
     * @return void
     */
    public function eliminarGuiasPickupCash(string $guia) {
        PryGuiaPickupCash::select('gpc_id')
            ->where('gpc_guia', $guia)
            ->get()
            ->map(function ($registro) {
                $registro->delete();
            });
    }
}
