<?php
namespace App\Http\Modulos\DataInputWorker\DhlAeroExpreso;

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use App\Traits\DiTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Models\AdoAgendamiento;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;

/**
 * Clase Gestora para el procesamiento de Cargas por medio de excel.
 *
 * Class ParserExcel
 * @package App\Http\Modulos\DataInputWorker
 */
class ParserExcelDhlAeroExpreso {
    /**
     * Constantes usadas en la clase
     */
    private const NIT_AEROEXPRESO               = '900749828';
    private const RG_DOCUMENTO                  = '2';
    private const RG_ACUSE                      = '2';
    private const FPA_CODIGO                    = '2';
    private const MON_CODIGO                    = 'COP';
    private const CCO_CODIGO_NC                 = '5';
    private const CDO_OBSERVACION_CORRECCION_NC = 'Otros';
    private const CCO_CODIGO_ND                 = '4';
    private const CDO_OBSERVACION_CORRECCION_ND = 'Descuento total aplicado';
    private const TIPO_ITEM                     = 'IP';
    private const CPR_CODIGO                    = '999';
    private const DDO_CODIGO                    = 'T01';
    private const DDO_CANTIDAD                  = '1';
    private const UND_CODIGO                    = 'A9';
    private const DESCRIPCION_UNO               = 'FLETE INTERNACIONAL DE CARGA';
    private const RAZON_CARGOS                  = 'OTROS CARGOS';
    private const PORCENTAJE_CARGOS             = '100.00';
    
    /**
     * Prefijo de facturación para Aeroexpreso
     *
     * @var string
     */
    protected $prefijoFacturacion;

    /**
     * Código de la moneda extranjera.
     * 
     * @var string
     */
    protected $monCodigoMonedaExtranjera = null;

    /**
     * Tasa representav del mercado para los valores del documento.
     *
     * @var integer
     */
    protected $cdoTRM = 0;

    /**
     * Propiedades protegidas para el cálculo de valores a nivel de cabecera.
     *
     */
    protected $cdoValorSinImpuestos                 = 0;
    protected $cdoValorSinImpuestosMonedaExtranjera = 0;
    protected $cdoImpuestos                         = 0;
    protected $cdoImpuestosMonedaExtranjera         = 0;
    protected $cdoTotal                             = 0;
    protected $cdoTotalMonedaExtranjera             = 0;
    protected $cdoCargos                            = 0;
    protected $cdoCargosMonedaExtranjera            = 0;

    public function __construct() {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        TenantTrait::GetVariablesSistemaTenant();
        $this->prefijoFacturacion = config('variables_sistema_tenant.PREFIJO_AEROEXPRESO');
    }

    /**
     * Obtiene un valor solo con dos decimales y sin ningún tipo de redondeo.
     *
     * @param float $value Valor a procesar
     * @return float
     */
    private function solo2decimales($value) {
        return floor($value*100)/100;
    }

    /**
     * Formatea un valor numérico y lo devuelve con valor positivo si se recibe negativo.
     *
     * @param mixed $value
     * @return string
     */
    private function numberFormat($value) {
        if (empty($value))
            return "0.00";
        if (is_integer($value))
            $value = (string) $value;
        if (is_float($value))
            $value = (string) $value;

        if (preg_match("/^(\-)?((\d{1,3})(\,\d{3})*)(\.\d{1,6})?$/",  $value)) {
            $value = str_replace(',', '', $value);
            return number_format($this->solo2decimales(abs($value)), 2, '.', '');
        } elseif (preg_match("/^(\-)?((\d{1,3})(\.\d{3})*)(\,\d{1,6})?$/",  $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
            return number_format($this->solo2decimales(abs($value)), 2, '.', '');
        } elseif (preg_match("/^(\-)?(\d+)(\.\d{1,6})?$/",  $value)) {
            return number_format($this->solo2decimales(abs($value)), 2, '.', '');
        } elseif(preg_match("/^(\-)?(\d+)(\,\d{1,6})?$/",  $value)) {
            $value = str_replace(',', '.', $value);
            return number_format($this->solo2decimales(abs($value)), 2, '.', '');
        }
        return $value;
    }

    /**
     * Convierte un valor de moneda extranjera a moneda nacional haciendo uso de la tasa recibida como parámetro.
     *
     * @param float $valor
     * @param float $tasa
     * @return float
     */
    private function convertirValorMoneda($valor, $tasa) {
        return $this->numberFormat(floatval($valor) * $tasa);
    }

    /**
     * Realiza el registro de un procesamiento json en el modelo correspondiente
     *
     * @param array $valores Array que contiene la información de los campos para la creación dle registro
     * @return void
     */
    public function registraProcesamientoJson($valores){
        EtlProcesamientoJson::create([
            'pjj_tipo'                => $valores['pjj_tipo'],
            'pjj_json'                => $valores['pjj_json'],
            'pjj_procesado'           => $valores['pjj_procesado'],
            'pjj_errores'             => $valores['pjj_errores'],
            'age_id'                  => $valores['age_id'],
            'age_estado_proceso_json' => $valores['age_estado_proceso_json'],
            'usuario_creacion'        => $valores['usuario_creacion'],
            'estado'                  => $valores['estado']
        ]);
    } 

    /**
     * Procesa la carga manual de documentos electrónicos mediante archivos Excel para DHL Aero Expreso.
     *
     * @param Request $request
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Colección con la información del OFE
     * @return \Illuminate\Http\Response
     */
    public function procesarExcel(Request $request, $ofe) {
        if(empty($this->prefijoFacturacion))
            return [
                'error'   => true,
                'errores' => ['No existe la variable del sistema Tenant PREFIJO_AEROEXPRESO'],
                'message' => ['Error en variable del sistema Tenant']
            ];

        DiTrait::setFilesystemsInfo();

        $archivo     = $request->file('archivo')->path();
        $storagePath = Storage::disk(config('variables_sistema.ETL_LOCAL_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
        $tempfilecsv = $storagePath . Uuid::uuid4()->toString() . '.csv';

        // Construyendo el csv
        $salida = [];
        exec("ssconvert $archivo $tempfilecsv", $salida);

        $filas = [];
        if (($handle = fopen($tempfilecsv, "r")) !== false) {
            while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                $filas[] = $data;
            }
            fclose($handle);
        }

        unlink($archivo);
        unlink($tempfilecsv);

        if(empty($filas)) {
            return response()->json([
                'message' => 'Error al procesar el Excel',
                'errors'  => ['El archivo de excel está vacio']
            ], 400);
        }

        $keys = $filas[0];
        unset($filas[0]);
        
        $documentosElectronicos = [];
        foreach ($filas as $fila) {
            if(strtolower($fila[0]) !== 'total' && strtolower($fila[0]) !== 'account') {
                $documentosElectronicos[$fila[1]][] = array_combine(array_map('trim', $keys), array_map('trim', $fila));
            }
        }

        if($request->tipo_documento_electronico == 'ND' || $request->tipo_documento_electronico == 'NC') {
            $documentoReferencia = $request->documento_referencia;
        } else {
            $documentoReferencia = null;
        }

        // El array de registros fue agrupado mediante el número de documento porque en un archivo de Excel
        // pueden venir varios documentos electrónicos, por lo que se debe iterar dicho array y separarlo
        // de acuerdo a la cantidad de registros DI que se pueden procesar para la BD (bdd_cantidad_procesamiento_edi)
        $erroresGlobales      = [];
        $grupos               = array_chunk($documentosElectronicos, auth()->user()->getBaseDatos->bdd_cantidad_procesamiento_edi);
        $arrDocumentosAgendar =  [];
        foreach($grupos as $documentos) {
            $errores = [];
            $documento['documentos'][strtoupper($request->tipo_documento_electronico)] = [];
            
            foreach($documentos as $registros) {
                $this->cdoTRM                               = 0;
                $this->cdoValorSinImpuestos                 = 0;
                $this->cdoImpuestos                         = 0;
                $this->cdoTotal                             = 0;
                $this->cdoCargos                            = 0;
                $this->cdoValorSinImpuestosMonedaExtranjera = 0;
                $this->cdoImpuestosMonedaExtranjera         = 0;
                $this->cdoTotalMonedaExtranjera             = 0;
                $this->cdoCargosMonedaExtranjera            = 0;

                $cabecera = $this->procesarDatosCabecera($ofe, $registros, $request->tipo_documento_electronico, $documentoReferencia, $errores);
                $items    = $this->procesarItems($ofe, $registros, $request->tipo_documento_electronico, $errores);

                if(empty($errores)) {
                    // Asigna valores a nivel de cabecera, calculados en el procesamiento de items
                    $cabecera['cdo_valor_sin_impuestos'] = (string)$this->numberFormat($this->cdoValorSinImpuestos);
                    $cabecera['cdo_impuestos']           = (string)$this->numberFormat($this->cdoImpuestos);
                    $cabecera['cdo_total']               = (string)$this->numberFormat($this->cdoTotal);
                    $cabecera['cdo_cargos']              = (string)$this->numberFormat($this->cdoCargos);

                    if(!empty($this->monCodigoMonedaExtranjera) ){
                        $cabecera['cdo_valor_sin_impuestos_moneda_extranjera'] = (string)$this->numberFormat($this->cdoValorSinImpuestosMonedaExtranjera);
                        $cabecera['cdo_impuestos_moneda_extranjera']           = (string)$this->numberFormat($this->cdoImpuestosMonedaExtranjera);
                        $cabecera['cdo_total_moneda_extranjera']               = (string)$this->numberFormat($this->cdoTotalMonedaExtranjera);
                        $cabecera['cdo_cargos_moneda_extranjera']              = (string)$this->numberFormat($this->cdoCargosMonedaExtranjera);
                    }

                    // Integra los arrays que hacen parte del documento
                    $documento['documentos'][strtoupper($request->tipo_documento_electronico)][] = array_merge($cabecera, $items);
                } else {
                    $erroresGlobales = array_merge($erroresGlobales, $errores);
                }
            }

            if(empty($errores)) {
                $arrDocumentosAgendar[] = [
                    'total'     => count($documentos),
                    'documento' => $documento
                ];
            }
        }

        if(empty($erroresGlobales)) {
            $user = auth()->user();
            foreach ($arrDocumentosAgendar as $documentosAgendar) {
                $agendamientoEdi = AdoAgendamiento::create([
                    'usu_id'                  => $user->usu_id,
                    'bdd_id'                  => $user->getBaseDatos->bdd_id,
                    'age_proceso'             => 'EDI',
                    'age_cantidad_documentos' => $documentosAgendar['total'],
                    'age_prioridad'           => !empty($ofe->ofe_prioridad_agendamiento) ? $ofe->ofe_prioridad_agendamiento : null,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);
    
                $this->registraProcesamientoJson([
                    'pjj_tipo'                => 'EDI',
                    'pjj_json'                => json_encode($documentosAgendar['documento']),
                    'pjj_procesado'           => 'NO',
                    'pjj_errores'             => null,
                    'age_id'                  => $agendamientoEdi->age_id,
                    'age_estado_proceso_json' => null,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);
            }
            
            $message = 'Se han agendado ' . count($documentosElectronicos) . ' documentos para su procesamiento en background';
        } else {
            $message = 'Se presentaron errores en el procesamiento del archivo';
        }

        return [
            'error'   => count($erroresGlobales) > 0,
            'errores' => $erroresGlobales,
            'message' => $message
        ];
    }

    /**
     * Crea la cabecera del documento basado en el primer registro o fila del archivo de Excel.
     * 
     * Se debe tener en cuenta que el OFE solamente carga un documento electrónico por archivo y por esto se toma la información de la primera fila de datos para obtener los datos de cabecera.
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Colección con la información del OFE
     * @param array $registros Array de filas del Excel
     * @param string $tipoDocumentoElectronico Tipo de Documento Electrónico
     * @param array $documentoReferencia Array conteniendo la información de los documentos referencia, aplica a NC y ND
     * @param array $errores Array de retorno de errores
     * @return array $cabecera  Array con los datos necesarios de cabecera
     */
    private function procesarDatosCabecera($ofe, $registros, $tipoDocumentoElectronico, $documentoReferencia = null, &$errores) {
        if($tipoDocumentoElectronico == 'FC') {
            $tde_codigo = '01';
            $top_codigo = '10';
        } elseif($tipoDocumentoElectronico == 'NC') {
            $tde_codigo = '91';
            $top_codigo = '20';
        } elseif($tipoDocumentoElectronico == 'ND') {
            $tde_codigo = '92';
            $top_codigo = '30';
        }

        // Adquirente
        $adquirente = ConfiguracionAdquirente::select(['adq_id', 'adq_identificacion'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('adq_id_personalizado', $registros[0]['ACCOUNT'])
            ->where('estado', 'ACTIVO')
            ->first();

        $cabecera = [];
        if(!$adquirente) {
            $errores[] = 'No se encontró o se encuentra inactivo el adquirente con ACCOUNT [' . $registros[0]['ACCOUNT'] . '] documento [' . $registros[0]['INV/BATCH NR'] . ']';
        } else {
            $rfa_resolucion = null;
            $rfa_prefijo    = '';
            if($tipoDocumentoElectronico == 'FC') {
                // Resolución
                $resolucion = ConfiguracionResolucionesFacturacion::select('rfa_id', 'rfa_resolucion')
                    ->where('rfa_prefijo', $this->prefijoFacturacion)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if(!$resolucion) {
                    $errores[] = 'No se encontró o se encuentra inactiva la resolución de facturación con PREFIJO [' . $this->prefijoFacturacion . '] documento [' . $registros[0]['INV/BATCH NR'] . ']';
                } else {
                    $rfa_resolucion = $resolucion->rfa_resolucion;
                    $rfa_prefijo    = $this->prefijoFacturacion;
                }
            }

            $fecha = date('Y-m-d');
            $hora  = date('H:i:s');
            $vence = Carbon::parse($fecha)->addDays(30)->format('Y-m-d');

            $cabecera = [
                'tde_codigo'                                  => $tde_codigo,
                'top_codigo'                                  => $top_codigo,
                'ofe_identificacion'                          => self::NIT_AEROEXPRESO,
                'adq_identificacion'                          => $adquirente->adq_identificacion,
                'adq_identificacion_autorizado'               => null,
                'rfa_resolucion'                              => $rfa_resolucion,
                'rfa_prefijo'                                 => $rfa_prefijo,
                'cdo_consecutivo'                             => $registros[0]['INV/BATCH NR'],
                'cdo_fecha'                                   => $fecha,
                'cdo_hora'                                    => $hora,
                'cdo_vencimiento'                             => $vence,
                'cdo_observacion'                             => null,
                'cdo_representacion_grafica_documento'        => self::RG_DOCUMENTO,
                'cdo_representacion_grafica_acuse'            => self::RG_ACUSE,
                'mon_codigo'                                  => self::MON_CODIGO
            ];

            if(array_key_exists('CURR IND', $registros[0]) && !empty($registros[0]['CURR IND']) && $registros[0]['CURR IND'] != 'COP') {
                $this->monCodigoMonedaExtranjera              = $registros[0]['CURR IND'];
                $cabecera['mon_codigo_extranjera']            = $this->monCodigoMonedaExtranjera;
                $cabecera['cdo_envio_dian_moneda_extranjera'] = 'SI';
            } else {
                $cabecera['cdo_envio_dian_moneda_extranjera'] = 'NO';
            }
            
            if(array_key_exists('EXCH RATE DIAN', $registros[0]) && !empty($registros[0]['EXCH RATE DIAN'])) {
                $this->cdoTRM              = $this->numberFormat($registros[0]['EXCH RATE DIAN']);
                $cabecera['cdo_trm']       = $this->cdoTRM;
                $cabecera['cdo_trm_fecha'] = $fecha;
            } elseif(
                !array_key_exists('EXCH RATE DIAN', $registros[0]) ||
                (
                    array_key_exists('EXCH RATE DIAN', $registros[0]) &&
                    empty($registros[0]['EXCH RATE DIAN'])
                ) &&
                !empty($this->monCodigoMonedaExtranjera)
            ) {
                $errores[] = 'NO se envió o está vacia la información correspondiente a la TRM documento [' . $registros[0]['INV/BATCH NR'] . ']';
            }

            $cabecera['cdo_informacion_adicional'] = [
                'CUENTA' => $registros[0]['ACCOUNT'],
                'cdo_procesar_documento' => 'SI'
            ];

            $cabecera['cdo_medios_pago'][] = [
                'fpa_codigo'            => self::FPA_CODIGO,
                'men_fecha_vencimiento' => $vence
            ];

            // Documento referencia para NC y ND
            if($tipoDocumentoElectronico == 'NC' || $tipoDocumentoElectronico == 'ND') {
                if(!empty($documentoReferencia)) {
                    $documentoReferencia = json_decode($documentoReferencia, true);

                    if(array_key_exists('tiene_documento_referencia', $documentoReferencia) && $documentoReferencia['tiene_documento_referencia'] == 'SI') {
                        // Verifica que el documento referencia existe
                        $existe = EtlCabeceraDocumentoDaop::select(['cdo_id'])
                            ->where('cdo_clasificacion', $documentoReferencia['clasificacion'])
                            ->where('cdo_consecutivo', $documentoReferencia['consecutivo']);
                        
                        if(empty($documentoReferencia['prefijo']))
                            $existe = $existe->whereRaw("(rfa_prefijo IS NULL OR rfa_prefijo = '')");
                        else 
                            $existe = $existe->where('rfa_prefijo', $documentoReferencia['prefijo']);
                            
                        $existe = $existe->first();

                        if(!$existe) {
                            $errores[] = 'No se encontró el documento referencia [' . $documentoReferencia['clasificacion'] . '-' . $documentoReferencia['prefijo'] . $documentoReferencia['consecutivo'] . ']  documento [' . $registros[0]['INV/BATCH NR'] . ']';
                        } else {
                            $cabecera['cdo_documento_referencia'][] = [
                                'clasificacion' => array_key_exists('clasificacion', $documentoReferencia) && !empty($documentoReferencia['clasificacion']) ? $documentoReferencia['clasificacion'] : '',
                                'prefijo'       => array_key_exists('prefijo', $documentoReferencia) && !empty($documentoReferencia['prefijo']) ? $documentoReferencia['prefijo'] : '',
                                'consecutivo'   => array_key_exists('consecutivo', $documentoReferencia) && !empty($documentoReferencia['consecutivo']) ? $documentoReferencia['consecutivo'] : '',
                                'cufe'          => array_key_exists('cufe', $documentoReferencia) && !empty($documentoReferencia['cufe']) ? $documentoReferencia['cufe'] : '',
                                'fecha_emision' => array_key_exists('fecha_emision', $documentoReferencia) && !empty($documentoReferencia['fecha_emision']) ? $documentoReferencia['fecha_emision'] : ''
                            ];
                        }
                    } else {
                        if($tipoDocumentoElectronico == 'NC') {
                            $cabecera['top_codigo'] = '22';
                        } elseif($tipoDocumentoElectronico == 'ND') {
                            $cabecera['top_codigo'] = '32';
                        }
                    }
                }

                $cabecera['cdo_conceptos_correccion'][] = [
                    'cco_codigo'                 => $tipoDocumentoElectronico == 'NC' ? self::CCO_CODIGO_NC : self::CCO_CODIGO_ND,
                    'cdo_observacion_correccion' => $tipoDocumentoElectronico == 'NC' ? self::CDO_OBSERVACION_CORRECCION_NC : self::CDO_OBSERVACION_CORRECCION_ND
                ];
            }
        }

        return $cabecera;
    }

    /**
     * Crea la información de los items del documento cargados el archivo de Excel.
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Colección con la información del OFE
     * @param array $registros Array de filas del Excel
     * @param string $tipoDocumentoElectronico Tipo de Documento Electrónico
     * @param array $errores Array de retorno de errores
     * @return array $items  Array con los datos necesarios de items
     */
    private function procesarItems($ofe, $registros, $tipoDocumentoElectronico, &$errores) {
        $items['items'] = [];

        $contItems = 1;
        foreach($registros as $registro) {
            $item = [
                'ddo_secuencia'       => (string)$contItems,
                'ddo_tipo_item'       => self::TIPO_ITEM,
                'cpr_codigo'          => self::CPR_CODIGO,
                'ddo_codigo'          => self::DDO_CODIGO,
                'ddo_descripcion_uno' => self::DESCRIPCION_UNO,
                'ddo_cantidad'        => self::DDO_CANTIDAD,
                'und_codigo'          => self::UND_CODIGO,
                'ddo_informacion_adicional' => [
                    'fecha_hawb'  => date('Y-m-d', strtotime($registro['AWB DATE'])),
                    'hawb'        => $registro['AIRWAYBILL'],
                    'org_dst'     => $registro['CARR FROM-TO'],
                    'tasa_cambio' => $registro['EXCH RATE']
                ]
            ];

            $ddoCargo = [
                'razon'      => self::RAZON_CARGOS,
                'porcentaje' => self::PORCENTAJE_CARGOS
            ];

            if(!empty($this->monCodigoMonedaExtranjera)) {
                $item['ddo_valor_unitario']                   = $this->convertirValorMoneda($registro['WT/RATE CHARGES'], $registro['EXCH RATE']);
                $item['ddo_total']                            = $this->convertirValorMoneda($registro['WT/RATE CHARGES'], $registro['EXCH RATE']);
                $item['ddo_valor_unitario_moneda_extranjera'] = $this->numberFormat($registro['WT/RATE CHARGES']);
                $item['ddo_total_moneda_extranjera']          = $this->numberFormat($registro['WT/RATE CHARGES']);

                $ddoCargo['valor_moneda_nacional'] = [
                    'base'  => floatval($registro['D/C OTHER CHARGES']) > 0 ? $this->convertirValorMoneda($registro['D/C OTHER CHARGES'], $this->cdoTRM) : '0.00',
                    'valor' => floatval($registro['D/C OTHER CHARGES']) > 0 ? $this->convertirValorMoneda($registro['D/C OTHER CHARGES'], $this->cdoTRM) : '0.00'
                ];

                $this->cdoValorSinImpuestos += ($item['ddo_total'] + $ddoCargo['valor_moneda_nacional']['valor']);
                $this->cdoTotal             += ($item['ddo_total'] + $ddoCargo['valor_moneda_nacional']['valor']);

                $ddoCargo['valor_moneda_extranjera'] = [
                    'base'  => $this->numberFormat($registro['D/C OTHER CHARGES']),
                    'valor' => $this->numberFormat($registro['D/C OTHER CHARGES'])
                ];

                $this->cdoValorSinImpuestosMonedaExtranjera += ($item['ddo_total_moneda_extranjera'] + $ddoCargo['valor_moneda_extranjera']['valor']);
                $this->cdoTotalMonedaExtranjera             += ($item['ddo_total_moneda_extranjera'] + $ddoCargo['valor_moneda_extranjera']['valor']);
            } else {
                $item['ddo_valor_unitario'] = $this->numberFormat($registro['WT/RATE CHARGES']);
                $item['ddo_total']          = $this->numberFormat($registro['WT/RATE CHARGES']);

                $ddoCargo['valor_moneda_nacional'] = [
                    'base'  => $this->numberFormat($registro['D/C OTHER CHARGES']),
                    'valor' => $this->numberFormat($registro['D/C OTHER CHARGES'])
                ];

                $this->cdoValorSinImpuestos += ($item['ddo_total'] + $ddoCargo['valor_moneda_nacional']['valor']);
                $this->cdoTotal             += ($item['ddo_total'] + $ddoCargo['valor_moneda_nacional']['valor']);
            }

            $item['ddo_cargos'][] = $ddoCargo;
            $items['items'][] = $item;

            $contItems++;
        }

        return $items;
    }
}
