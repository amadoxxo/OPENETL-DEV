<?php
namespace App\Http\Modulos\Emision\Reportes;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Services\Emision\EtlCabeceraDocumentoService;
use App\Http\Modulos\Documentos\BaseDocumentosController;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\ProyectosEspeciales\DHLExpress\PickupCash\PryArchivoPickupCash;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class DhlExpressController extends BaseDocumentosController {
    /**
     * Instancia del servicio de cabecera en emisión.
     * 
     * Clase encargada de la lógica de procesamiento de data
     *
     * @var EtlCabeceraDocumentoService
     */
    protected $emisionCabeceraService;

    /**
     * Constructor
     */
    public function __construct(EtlCabeceraDocumentoService $emisionCabeceraService) {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ReportePersonalizadoDhlExpress'
        ])->only([
            'dhlExpress',
            'dhlExpressSinEnvio',
            'dhlExpressEnviados',
            'dhlExpressFacturacionManualPickupCash',
            'dhlExpressListarReportesDescargas',
            'dhlExpressDescargarReporte',
            'dhlExpressArchivoEntradaPickupCash'
        ]);

        $this->emisionCabeceraService = $emisionCabeceraService;
    }

    public $columns = [
        'etl_cabecera_documentos_daop.cdo_id',
        'etl_cabecera_documentos_daop.cdo_origen',
        'etl_cabecera_documentos_daop.cdo_clasificacion',
        'etl_cabecera_documentos_daop.tde_id',
        'etl_cabecera_documentos_daop.top_id',
        'etl_cabecera_documentos_daop.cdo_lote',
        'etl_cabecera_documentos_daop.ofe_id',
        'etl_cabecera_documentos_daop.adq_id',
        'etl_cabecera_documentos_daop.adq_id_autorizado',
        'etl_cabecera_documentos_daop.rfa_id',
        'etl_cabecera_documentos_daop.rfa_prefijo',
        'etl_cabecera_documentos_daop.cdo_consecutivo',
        'etl_cabecera_documentos_daop.cdo_fecha',
        'etl_cabecera_documentos_daop.cdo_hora',
        'etl_cabecera_documentos_daop.cdo_vencimiento',
        'etl_cabecera_documentos_daop.cdo_observacion',
        'etl_cabecera_documentos_daop.cdo_representacion_grafica_documento',
        'etl_cabecera_documentos_daop.cdo_representacion_grafica_acuse',
        'etl_cabecera_documentos_daop.cdo_documento_referencia',
        'etl_cabecera_documentos_daop.cdo_conceptos_correccion',
        'etl_cabecera_documentos_daop.mon_id',
        'etl_cabecera_documentos_daop.mon_id_extranjera',
        'etl_cabecera_documentos_daop.cdo_trm',
        'etl_cabecera_documentos_daop.cdo_envio_dian_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_valor_sin_impuestos',
        'etl_cabecera_documentos_daop.cdo_valor_sin_impuestos_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_impuestos',
        'etl_cabecera_documentos_daop.cdo_impuestos_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_retenciones',
        'etl_cabecera_documentos_daop.cdo_retenciones_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_total',
        'etl_cabecera_documentos_daop.cdo_total_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_cargos',
        'etl_cabecera_documentos_daop.cdo_cargos_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_descuentos',
        'etl_cabecera_documentos_daop.cdo_descuentos_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_retenciones_sugeridas',
        'etl_cabecera_documentos_daop.cdo_retenciones_sugeridas_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_anticipo',
        'etl_cabecera_documentos_daop.cdo_anticipo_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_redondeo',
        'etl_cabecera_documentos_daop.cdo_redondeo_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_valor_a_pagar',
        'etl_cabecera_documentos_daop.cdo_valor_a_pagar_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_cufe',
        'etl_cabecera_documentos_daop.cdo_qr',
        'etl_cabecera_documentos_daop.cdo_signaturevalue',
        'etl_cabecera_documentos_daop.cdo_procesar_documento',
        'etl_cabecera_documentos_daop.cdo_fecha_procesar_documento',
        'etl_cabecera_documentos_daop.usuario_creacion',
        'etl_cabecera_documentos_daop.fecha_creacion',
        'etl_cabecera_documentos_daop.fecha_modificacion',
        'etl_cabecera_documentos_daop.estado',
        'etl_cabecera_documentos_daop.fecha_actualizacion',
    ];

    /**
     * Crea el agendamiento en el sistema para poder procesar y generar el archivo de Excel en background
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    function dhlExpress(Request $request) {
        $user    = auth()->user();

        $agendamiento = AdoAgendamiento::create([
            'usu_id'                  => $user->usu_id,
            'age_proceso'             => 'REPORTE',
            'bdd_id'                  => $user->getBaseDatos->bdd_id,
            'age_cantidad_documentos' => 1,
            'age_prioridad'           => null,
            'usuario_creacion'        => $user->usu_id,
            'estado'                  => 'ACTIVO',
        ]);

        EtlProcesamientoJson::create([
            'pjj_tipo'         => 'REPORTEDHLEXPRESS',
            'pjj_json'         => json_encode($request->all()),
            'pjj_procesado'    => 'NO',
            'age_id'           => $agendamiento->age_id,
            'usuario_creacion' => $user->usu_id,
            'estado'           => 'ACTIVO'
        ]);

        return response()->json([
            'message' => 'Reporte agendado para generarse en background'
        ], 201);
    }

    /**
     * Genera un reporte en Excel para DHL Express de Documentos Sin Envío de acuerdo a los filtros escogidos.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function dhlExpressSinEnvio(Request $request) {
        try {
            set_time_limit(0);
            ini_set('memory_limit','2048M');

            $user = auth()->user();
            
            $required_where_conditions = [];
            define('SEARCH_METHOD', 'busquedaGeneral');

            $special_where_conditions = [
                [
                    'type' => 'Null',
                    'field' => 'cdo_fecha_procesar_documento',
                ],
                [
                    'type' => 'whereraw',
                    'query' => '(cdo_procesar_documento IS NULL OR cdo_procesar_documento = "NO")'
                ]
            ];

            if($request->has('adq_id') && $request->adq_id !== '' && !empty($request->adq_id )) {
                $special_where_conditions[] = [
                    'type'  => 'in',
                    'field' => 'etl_cabecera_documentos_daop.adq_id',
                    'value' => explode(',', $request->adq_id)
                ];
            }

            if ($request->has('ofe_filtro') && !empty($request->get('ofe_filtro')) && $request->has('ofe_filtro_buscar') && !empty($request->get('ofe_filtro_buscar'))) {
                //Filtros a nivel de cabecera
                switch($request->get('ofe_filtro')){
                    case 'cdo_representacion_grafica_documento':
                        $required_where_conditions['etl_cabecera_documentos_daop.'.$request->get('ofe_filtro')] = $request->get('ofe_filtro_buscar');
                    break;
                    default:
                        $array_filtros = [
                            'type' => 'join',
                            'table' => 'etl_datos_adicionales_documentos_daop',
                            'on_conditions'  => [
                                [
                                    'from' => 'etl_cabecera_documentos_daop.cdo_id',
                                    'operator' => '=',
                                    'to' => 'etl_datos_adicionales_documentos_daop.cdo_id',
                                ]
                            ],
                        ];
            
                        $array_filtros['where_conditions'][] = [
                            'from' => DB::raw("JSON_EXTRACT(etl_datos_adicionales_documentos_daop.cdo_informacion_adicional , '$.{$request->get("ofe_filtro")}')"),
                            'operator' => '=',
                            'to' => $request->get('ofe_filtro_buscar'),
                        ];

                        array_push($special_where_conditions, $array_filtros);
                    break;
                }
            }

            if (!empty($request->cdo_fecha_desde) && !empty($request->cdo_fecha_hasta)){
                array_push($special_where_conditions, [
                    'type' => 'Between',
                    'field' => 'etl_cabecera_documentos_daop.cdo_fecha',
                    'value' => [$request->cdo_fecha_desde . " 00:00:00", $request->cdo_fecha_hasta . " 23:59:59"]
                ]);
            }

            $required_where_conditions['etl_cabecera_documentos_daop.ofe_id'] = $request->get('ofe_id');

            if (isset($request->cdo_origen) && $request->cdo_origen !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.cdo_origen'] = $request->cdo_origen;
            }

            if (isset($request->cdo_consecutivo) && $request->cdo_consecutivo !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.cdo_consecutivo'] = $request->cdo_consecutivo;
            }

            if (isset($request->rfa_prefijo) && $request->rfa_prefijo !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.rfa_prefijo'] = $request->rfa_prefijo;
            }

            if (isset($request->cdo_clasificacion) && $request->cdo_clasificacion !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.cdo_clasificacion'] = $request->cdo_clasificacion;
            }

            if (isset($request->cdo_lote) && $request->cdo_lote !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.cdo_lote'] = $request->cdo_lote;
            }

            if (isset($request->estado) && $request->estado !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.estado'] = $request->estado;
            }

            $optional_where_conditions = [];

            $arrRelaciones = [
                'getConfiguracionObligadoFacturarElectronicamente',
                'getConfiguracionAdquirente',
                'getDocumentosAnexos',
                'getParametrosMoneda',
                'getParametrosMonedaExtranjera',
                'getConfiguracionResolucionesFacturacion',
                'getEstadosDocumentosDaop:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,fecha_creacion',
                'getTipoOperacion:top_id,top_codigo,top_descripcion',
                'getPickupCashDocumentoExitoso:est_id,cdo_id,est_informacion_adicional'
            ];

            $where_has_conditions = [];
            if(!empty($user->bdd_id_rg)) {
                $where_has_conditions[] = [
                    'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                    'function' => function($query) use ($user) {
                        $query->where('bdd_id_rg', $user->bdd_id_rg);
                    }
                ];
            } else {
                $where_has_conditions[] = [
                    'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                    'function' => function($query) {
                        $query->whereNull('bdd_id_rg');
                    }
                ];
            }

            $titulos = [
                'NIT EMISOR',
                'EMISOR',
                'RESOLUCION FACTURACION',
                'NIT ADQUIRENTE',
                'No. DE CUENTA',
                'ADQUIRENTE',
                'TIPO DOCUMENTO',
                'TIPO OPERACION',
                'PREFIJO',
                'CONSECUTIVO',
                'FECHA FACTURA',
                'HORA FACTURA',
                'FECHA VENCIMIENTO',
                'OBSERVACION',
                'MONEDA',
                'TOTAL ANTES DE IMPUESTOS',
                'IMPUESTOS',
                'TOTAL',
                'IMPORTE TOTAL',
                'DIFERENCIA IMPORTE',
                'ESTADO'
            ];

            $columnas = [
                'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion',
                'get_configuracion_obligado_facturar_electronicamente.ofe_razon_social',
                'get_configuracion_resoluciones_facturacion.rfa_resolucion',
                'get_configuracion_adquirente.adq_identificacion',
                'get_configuracion_adquirente.adq_id_personalizado',
                'get_configuracion_adquirente.adq_razon_social',
                'cdo_clasificacion',
                'get_tipo_operacion.top_descripcion',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_vencimiento',
                [
                    'do' => function($cdo_observacion) {
                        if($cdo_observacion != '') {
                            $observacion = json_decode($cdo_observacion);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($observacion)) {
                                return implode(' | ', $observacion);
                            } else {
                                return $cdo_observacion;
                            }
                        }
                    },
                    'field' => 'cdo_observacion',
                    'validation_type'=> 'callback'
                ],
                'get_parametros_moneda.mon_codigo',
                [
                    'do' => function ($cdo_valor_sin_impuestos, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_valor_sin_impuestos_moneda_extranjera'] : $cdo_valor_sin_impuestos;
                    },
                    'extra_data' => [
                        'cdo_valor_sin_impuestos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_valor_sin_impuestos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_valor_sin_impuestos, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_impuestos_moneda_extranjera'] : $cdo_valor_sin_impuestos;
                    },
                    'extra_data' => [
                        'cdo_impuestos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_impuestos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_valor_sin_impuestos, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_valor_a_pagar_moneda_extranjera'] : $cdo_valor_sin_impuestos;
                    },
                    'extra_data' => [
                        'cdo_valor_a_pagar_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_valor_a_pagar',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($est_informacion_adicional) {
                        if($est_informacion_adicional != NULL && $est_informacion_adicional != '')
                            $informacionAdicional = json_decode($est_informacion_adicional);
                        else
                            $informacionAdicional = json_decode(json_encode([]));

                        return isset($informacionAdicional->importe_total) ? $informacionAdicional->importe_total : '';
                    },
                    'field' => 'get_pickup_cash_documento_exitoso.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_valor_a_pagar, $extra_data) {
                        if($extra_data['get_pickup_cash_documento_exitoso.est_informacion_adicional'] != NULL && $extra_data['get_pickup_cash_documento_exitoso.est_informacion_adicional'] != '')
                            $informacionAdicional = json_decode($extra_data['get_pickup_cash_documento_exitoso.est_informacion_adicional']);
                        else
                            $informacionAdicional = json_decode(json_encode([]));

                        if(isset($informacionAdicional->importe_total)) {
                            if($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') {
                                $diferencia = number_format((floatval($informacionAdicional->importe_total) - floatval($extra_data['cdo_valor_a_pagar_moneda_extranjera'])), 2, '.', '');
                            } else {
                                $diferencia = number_format((floatval($informacionAdicional->importe_total) - floatval($cdo_valor_a_pagar)), 2, '.', '');
                            }
                        } else
                            $diferencia = '';

                        return $diferencia;
                    },
                    'extra_data' => [
                        'get_pickup_cash_documento_exitoso.est_informacion_adicional',
                        'get_parametros_moneda.mon_codigo',
                        'cdo_valor_a_pagar_moneda_extranjera'
                    ],
                    'field' => 'cdo_valor_a_pagar',
                    'validation_type'=> 'callback'
                ],
                'estado'
            ];

            // Para los archivos de Excel se debe verificar si el OFE tiene configuradas columnas especiales en ofe_columnas_personalizadas
            // De ser el caso, se debe buscar dicha información en información adicional del documento para incluirlo en el Excel
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_columnas_personalizadas'])
                ->find($request->ofe_id);

            if(array_key_exists('EXCEL', $ofe->ofe_columnas_personalizadas)) {
                foreach($ofe->ofe_columnas_personalizadas['EXCEL'] as $columnaExcel) {
                    $titulos[]  = $columnaExcel['titulo'];
                    $columnas[] = 'get_dad_documentos_daop.0.cdo_informacion_adicional.' . $columnaExcel['campo'];
                }

                $arrRelaciones[] = 'getDadDocumentosDaop:cdo_id,cdo_informacion_adicional';
            }

            $columnasPersonalizadasAdq = $this->ofeColumnasPersonalizadasAdquirentes($request->get('ofe_id'));
            if(!empty($columnasPersonalizadasAdq)) {
                foreach($columnasPersonalizadasAdq as $columnaExcel) {
                    $titulos[]  = $columnaExcel;
                    $columnas[] = [
                        'do' => function ($adqInfoPersonalizada, $extra_data) {
                            if($adqInfoPersonalizada != NULL && $adqInfoPersonalizada != '')
                                $adqInfoPersonalizada = json_decode($adqInfoPersonalizada);
                            else
                                $adqInfoPersonalizada = json_decode(json_encode([]));

                            if(!empty($extra_data) && is_array($extra_data)) { 
                                $indice = key($extra_data);
                                return isset($adqInfoPersonalizada->$indice) ? $adqInfoPersonalizada->$indice : '';
                            } else {
                                return '';
                            }
                        },
                        'extra_data' => [
                            $columnaExcel
                        ],
                        'field' => 'get_configuracion_adquirente.adq_informacion_personalizada',
                        'validation_type'=> 'callback'
                    ];
                }
            }

            $documentos = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, true, true, $where_has_conditions, null, 'emision', 'array');
            $reporte    = $this->printExcelDocumentos($documentos, $titulos, $columnas, 'documentos_sin_envio', true);

            // Renombra el archivo y lo mueve al disco de descargas porque el método toExcel crea el archivo sobre el disco local
            File::move($reporte['ruta'], storage_path('etl/descargas/' . $reporte['nombre']));
            File::delete($reporte['ruta']);
            return [
                'errors'  => [],
                'archivo' => $reporte['nombre']
            ];
        } catch (\Exception $e) {
            return [
                'errors' => [ $e->getMessage() ],
                'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
            ];
        }
    }

    /**
     * Genera un reporte en Excel para DHL Express de Documentos Enviados de acuerdo a los filtros escogidos.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function dhlExpressEnviados(Request $request) {
        try {
            set_time_limit(0);
            ini_set('memory_limit','2048M');

            $titulos = [
                'NIT EMISOR',
                'EMISOR',
                'RESOLUCION FACTURACION',
                'NIT ADQUIRENTE',
                'No. DE CUENTA',
                'ADQUIRENTE',
                'TIPO DOCUMENTO',
                'TIPO OPERACION',
                'PREFIJO',
                'CONSECUTIVO',
                'FECHA FACTURA',
                'HORA FACTURA',
                'FECHA VENCIMIENTO',
                'OBSERVACION',
                'CUFE',
                'MONEDA',
                'TOTAL ANTES DE IMPUESTOS',
                'IMPUESTOS',
                'TOTAL',
                'CORREOS NOTIFICACIÓN',
                'FECHA NOTIFICACIÓN',
                'ESTADO',
                'ESTADO DIAN',
                'RESULTADO DIAN',
                'FECHA ACUSE RECIBO',
                'ESTADO DOCUMENTO',
                'FECHA ESTADO',
                'MOTIVO RECHAZO'
            ];

            $columnas = [
                'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion',
                'get_configuracion_obligado_facturar_electronicamente.nombre_completo',
                'get_configuracion_resoluciones_facturacion.rfa_resolucion',
                'get_configuracion_adquirente.adq_identificacion',
                'get_configuracion_adquirente.adq_id_personalizado',
                'get_configuracion_adquirente.nombre_completo',
                'cdo_clasificacion',
                'get_tipo_operacion.top_descripcion',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_vencimiento',
                [
                    'do' => function($cdo_observacion) {
                        if($cdo_observacion != '') {
                            $observacion = json_decode($cdo_observacion);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($observacion)) {
                                return implode(' | ', $observacion);
                            } else {
                                return $cdo_observacion;
                            }
                        }
                    },
                    'field' => 'cdo_observacion',
                    'validation_type'=> 'callback'
                ],
                'cdo_cufe',
                'get_parametros_moneda.mon_codigo',
                [
                    'do' => function ($cdo_valor_sin_impuestos, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_valor_sin_impuestos_moneda_extranjera'] : $cdo_valor_sin_impuestos;
                    },
                    'extra_data' => [
                        'cdo_valor_sin_impuestos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_valor_sin_impuestos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_valor_sin_impuestos, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_impuestos_moneda_extranjera'] : $cdo_valor_sin_impuestos;
                    },
                    'extra_data' => [
                        'cdo_impuestos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_impuestos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_valor_sin_impuestos, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_valor_a_pagar_moneda_extranjera'] : $cdo_valor_sin_impuestos;
                    },
                    'extra_data' => [
                        'cdo_valor_a_pagar_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_valor_a_pagar',
                    'validation_type'=> 'callback'
                ],
                'get_notificacion_finalizado.est_correos',
                'get_notificacion_finalizado.est_fin_proceso',
                'estado',
                'estado_dian',
                'resultado_dian',
                'cdo_fecha_acuse',
                'cdo_estado',
                'cdo_fecha_estado',
                'get_rechazado.est_motivo_rechazo.motivo_rechazo'
            ];

            // Para los archivos de Excel se debe verificar si el OFE tiene configuradas columnas especiales en ofe_columnas_personalizadas
            // De ser el caso, se debe buscar dicha información en información adicional del documento para incluirlo en el Excel
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_columnas_personalizadas'])
                ->find($request->ofe_id);

            if(isset($ofe->ofe_columnas_personalizadas) && array_key_exists('EXCEL', $ofe->ofe_columnas_personalizadas)) {
                foreach($ofe->ofe_columnas_personalizadas['EXCEL'] as $columnaExcel) {
                    $titulos[]  = $columnaExcel['titulo'];
                    $columnas[] = 'get_dad_documentos_daop.cdo_informacion_adicional.' . $columnaExcel['campo'];
                }
            }

            $columnasPersonalizadasAdq = $this->ofeColumnasPersonalizadasAdquirentes($request->ofe_id);
            if(!empty($columnasPersonalizadasAdq)) {
                foreach($columnasPersonalizadasAdq as $columnaExcel) {
                    $titulos[]  = $columnaExcel;
                    $columnas[] = [
                        'do' => function ($adqInfoPersonalizada, $extra_data) {
                            if($adqInfoPersonalizada != NULL && $adqInfoPersonalizada != '')
                                $adqInfoPersonalizada = json_decode($adqInfoPersonalizada);
                            else
                                $adqInfoPersonalizada = json_decode(json_encode([]));

                            if(!empty($extra_data) && is_array($extra_data)) { 
                                $indice = key($extra_data);
                                return isset($adqInfoPersonalizada->$indice) ? $adqInfoPersonalizada->$indice : '';
                            } else {
                                return '';
                            }
                        },
                        'extra_data' => [
                            $columnaExcel
                        ],
                        'field' => 'get_configuracion_adquirente.adq_informacion_personalizada',
                        'validation_type'=> 'callback'
                    ];
                }
            }

            $documentos = $this->emisionCabeceraService
                ->obtenerListaDocumentosEnviadosDhlExpress($request);

            $reporte = $this->printExcelDocumentos($documentos, $titulos, $columnas, 'documentos_enviados', true);

            // Renombra el archivo y lo mueve al disco de descargas porque el método toExcel crea el archivo sobre el disco local
            File::move($reporte['ruta'], storage_path('etl/descargas/' . $reporte['nombre']));
            File::delete($reporte['ruta']);

            return [
                'errors'  => [],
                'archivo' => $reporte['nombre']
            ];
        } catch (\Exception $e) {
            return [
                'errors' => [ $e->getMessage() ],
                'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
            ];
        }
    }

    /**
     * Genera un reporte en Excel para DHL Express de Facturación Manual y Pickup Cash de acuerdo a los filtros escogidos.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function dhlExpressFacturacionManualPickupCash(Request $request) {
        try {
            set_time_limit(0);
            ini_set('memory_limit','2048M');

            $titulos = [
                'PREFIJO',
                'CONSECUTIVO',
                'FECHA-HORA',
                'NUMERO DOCUMENTO REFERENCIA',
                'FECHA DOCUMENTO REFERENCIA',
                'CUENTA',
                'NIT ADQUIRENTE',
                'No. DE CUENTA',
                'ADQUIRENTE',
                'GUIA',
                'TRM',
                'MONEDA',
                'CARGO 1',
                'VALOR CARGO 1',
                'CARGO 2',
                'VALOR CARGO 2',
                'CARGO 3',
                'VALOR CARGO 3',
                'CARGO 4',
                'VALOR CARGO 4',
                'CARGO 5',
                'VALOR CARGO 5',
                'CONCEPTO 1',
                'VALOR CONCEPTO 1',
                'CONCEPTO 2',
                'VALOR CONCEPTO 2',
                'CONCEPTO 3',
                'VALOR CONCEPTO 3',
                'CONCEPTO 4',
                'VALOR CONCEPTO 4',
                'CONCEPTO 5',
                'VALOR CONCEPTO 5',
                'DESCRIPCION DESCUENTO',
                'VALOR DESCUENTO',
                'DESCRIPCION 1',
                'DESCRIPCION 2',
                'CANTIDAD',
                'VALOR UNITARIO',
                'TOTAL',
                'BASE IVA',
                '% IVA',
                'VALOR IVA'
            ];

            $columnas = [
                'rfa_prefijo',
                [
                    'do' => function ($cdo_clasificacion, $extra_data) {
                        return $extra_data['rfa_prefijo'] . $extra_data['cdo_consecutivo'];
                    },
                    'extra_data' => [
                        'cdo_consecutivo',
                        'rfa_prefijo'
                    ],
                    'field' => 'cdo_clasificacion',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_fecha, $extra_data) {
                        return $cdo_fecha . ' ' . $extra_data['cdo_hora'];
                    },
                    'extra_data' => [
                        'cdo_hora'
                    ],
                    'field' => 'cdo_fecha',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_documento_referencia) {
                        if($cdo_documento_referencia != '') {
                            $documentoReferencia = json_decode($cdo_documento_referencia);
                            if(is_array($documentoReferencia))
                                $documentoReferencia = $documentoReferencia[0];

                            return ((isset($documentoReferencia->prefijo) && $documentoReferencia->prefijo != '') ? $documentoReferencia->prefijo : '') . $documentoReferencia->consecutivo;
                        } else {
                            return '';
                        }
                    },
                    'field' => 'cdo_documento_referencia',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_documento_referencia) {
                        if($cdo_documento_referencia != '') {
                            $documentoReferencia = json_decode($cdo_documento_referencia);
                            if(is_array($documentoReferencia))
                                $documentoReferencia = $documentoReferencia[0];

                            return (isset($documentoReferencia->fecha_emision) && $documentoReferencia->fecha_emision != '') ? $documentoReferencia->fecha_emision : '';
                        } else {
                            return '';
                        }
                    },
                    'field' => 'cdo_documento_referencia',
                    'validation_type'=> 'callback'
                ],
                'get_dad_documentos_daop.cdo_informacion_adicional.cuenta',
                'get_configuracion_adquirente.adq_identificacion',
                'get_configuracion_adquirente.adq_id_personalizado',
                'get_configuracion_adquirente.nombre_completo',
                'get_dad_documentos_daop.cdo_informacion_adicional.guia',
                [
                    'do' => function ($cdo_trm) {
                        if($cdo_trm > 0)
                            return number_format($cdo_trm, 2, '.', '');
                        else
                            return '';
                    },
                    'field' => 'cdo_trm',
                    'validation_type'=> 'callback'
                ],
                'get_parametros_moneda.mon_codigo',
                'get_detalle_documentos.0.ddo_informacion_adicional.cargo_1',
                'get_detalle_documentos.0.ddo_informacion_adicional.valor_cargo_1',
                'get_detalle_documentos.0.ddo_informacion_adicional.cargo_2',
                'get_detalle_documentos.0.ddo_informacion_adicional.valor_cargo_2',
                'get_detalle_documentos.0.ddo_informacion_adicional.cargo_3',
                'get_detalle_documentos.0.ddo_informacion_adicional.valor_cargo_3',
                'get_detalle_documentos.0.ddo_informacion_adicional.cargo_4',
                'get_detalle_documentos.0.ddo_informacion_adicional.valor_cargo_4',
                'get_detalle_documentos.0.ddo_informacion_adicional.cargo_5',
                'get_detalle_documentos.0.ddo_informacion_adicional.valor_cargo_5',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                ''
            ];

            $columnasPersonalizadasAdq = $this->ofeColumnasPersonalizadasAdquirentes($request->get('ofe_id'));
            if(!empty($columnasPersonalizadasAdq)) {
                foreach($columnasPersonalizadasAdq as $columnaExcel) {
                    $titulos[]  = $columnaExcel;
                    $columnas[] = [
                        'do' => function ($adqInfoPersonalizada, $extra_data) {
                            if($adqInfoPersonalizada != NULL && $adqInfoPersonalizada != '')
                                $adqInfoPersonalizada = json_decode($adqInfoPersonalizada);
                            else
                                $adqInfoPersonalizada = json_decode(json_encode([]));

                            if(!empty($extra_data) && is_array($extra_data)) { 
                                $indice = key($extra_data);
                                return isset($adqInfoPersonalizada->$indice) ? $adqInfoPersonalizada->$indice : '';
                            } else {
                                return '';
                            }
                        },
                        'extra_data' => [
                            $columnaExcel
                        ],
                        'field' => 'get_configuracion_adquirente.adq_informacion_personalizada',
                        'validation_type'=> 'callback'
                    ];
                }
            }

            $consultaDocumentos = $this->emisionCabeceraService
                ->obtenerListaDocumentosDhlExpressFacturacionManualPickupCash($request);

            $documentos = [];
            foreach($consultaDocumentos->chunk(1000) as $chunkDocumentos) {
                $chunkDocumentos->each(function($doc) use (&$documentos) {
                    if(!array_key_exists($doc->cdo_id, $documentos)) {
                        $documentos[$doc->cdo_id] = (array) $doc;
                        $documentos[$doc->cdo_id]['get_detalle_documentos'] = [];
                        $documentos[$doc->cdo_id]['get_cargos_descuentos']  = [];
                    }

                    if(!array_key_exists($doc->detalle_ddo_id, $documentos[$doc->cdo_id]['get_detalle_documentos']))
                        $documentos[$doc->cdo_id]['get_detalle_documentos'][$doc->detalle_ddo_id] = [
                            'ddo_id'                               => $doc->detalle_ddo_id,
                            'cdo_id'                               => $doc->detalle_cdo_id,
                            'ddo_tipo_item'                        => $doc->ddo_tipo_item,
                            'ddo_descripcion_uno'                  => $doc->ddo_descripcion_uno,
                            'ddo_descripcion_dos'                  => $doc->ddo_descripcion_dos,
                            'ddo_cantidad'                         => $doc->ddo_cantidad,
                            'ddo_valor_unitario'                   => $doc->ddo_valor_unitario,
                            'ddo_valor_unitario_moneda_extranjera' => $doc->ddo_valor_unitario_moneda_extranjera,
                            'ddo_total'                            => $doc->ddo_total,
                            'ddo_total_moneda_extranjera'          => $doc->ddo_total_moneda_extranjera,
                            'ddo_informacion_adicional'            => !empty($doc->ddo_informacion_adicional) ? json_decode($doc->ddo_informacion_adicional, true) : null,
                            'get_impuestos_items_documentos'       => []
                        ];

                    if(!empty($doc->iid_id) && !array_key_exists($doc->iid_id, $documentos[$doc->cdo_id]['get_detalle_documentos'][$doc->detalle_ddo_id]['get_impuestos_items_documentos']))
                        $documentos[$doc->cdo_id]['get_detalle_documentos'][$doc->detalle_ddo_id]['get_impuestos_items_documentos'][$doc->iid_id] =[
                            'iid_id'                      => $doc->iid_id,
                            'ddo_id'                      => $doc->impuesto_ddo_id,
                            'cdo_id'                      => $doc->impuesto_cdo_id,
                            'tri_id'                      => $doc->tri_id,
                            'iid_tipo'                    => $doc->iid_tipo,
                            'iid_porcentaje'              => $doc->iid_porcentaje,
                            'iid_base'                    => $doc->iid_base,
                            'iid_base_moneda_extranjera'  => $doc->iid_base_moneda_extranjera,
                            'iid_valor'                   => $doc->iid_valor,
                            'iid_valor_moneda_extranjera' => $doc->iid_valor_moneda_extranjera,
                        ];

                    if(!array_key_exists($doc->cdd_id, $documentos[$doc->cdo_id]['get_cargos_descuentos']))
                        $documentos[$doc->cdo_id]['get_cargos_descuentos'][$doc->cdd_id] = [
                            'cdd_id'     => $doc->cdd_id,
                            'ddo_id'     => $doc->cargo_descuento_ddo_id,
                            'cdo_id'     => $doc->cargo_descuento_cdo_id,
                            'cdd_aplica' => $doc->cdd_aplica,
                            'cdd_tipo'   => $doc->cdd_tipo,
                            'cdd_razon'  => $doc->cdd_razon,
                            'cdd_valor'  => $doc->cdd_valor
                        ];
                });
            }

            // Resetea todos los índices de las relaciones de detalle de documentos e impuestos de items
            foreach($documentos as $cdo_id => $documento) {
                if(is_array($documento['get_detalle_documentos']))
                    $documentos[$cdo_id]['get_detalle_documentos'] = array_values($documento['get_detalle_documentos']);

                foreach($documentos[$cdo_id]['get_detalle_documentos'] as $index => $itemDocumento) {
                    if(is_array($itemDocumento['get_impuestos_items_documentos']))
                        $documentos[$cdo_id]['get_detalle_documentos'][$index]['get_impuestos_items_documentos'] = array_values($itemDocumento['get_impuestos_items_documentos']);
                }
            }

            // Array que pasa al Excel
            $documentos = collect($documentos);
            $arrDocumentos = $documentos->map(function ($document) use ($columnas) {
                $arr_doc = [];
                $doc = Arr::dot(!is_array($document) ? $document->toArray() : $document);
                
                //Añadimos columnas de cabecera al array
                foreach ($columnas as $column) {
                    if (is_array($column)){
                        switch ($column['validation_type']) {
                            case 'callback':
                                $value = $this->runCallback($doc, $column);
                                break;
                            default:
                                break;
                        }
                    } else {
                        $value = $doc[$column] ?? '';
                    }
                    array_push($arr_doc, $value);
                }
                return $arr_doc;
            });

            $arrDocumentos = $arrDocumentos->toArray();

            // Hasta este punto se han armado las columnas conforme a información de cabecera
            // Ahora por cada elemento del array de documentos, toca crear un nuevo elemento por cada item del documento y agregar la información del mismo
            $filasItems   = [];
            foreach($documentos as $documento) {
                $filaDocumento = $arrDocumentos[$documento['cdo_id']];
                foreach($documento['get_detalle_documentos'] as $item) {
                    $totalCargos     = 0;
                    $totalDescuentos = 0;
                    if(!empty($documento['get_cargos_descuentos'])) {
                        // Índices 11 a 20 corresponden a los cargos
                        $cargos = array_filter($documento['get_cargos_descuentos'], function($cargo) {
                            return $cargo['cdd_tipo'] == 'CARGO';
                        });

                        $indice = 12;
                        foreach($cargos as $cargo) {
                            $filaDocumento[$indice]   = isset($cargo['cdd_razon']) && !empty($cargo['cdd_razon']) ? $cargo['cdd_razon'] : '';
                            $filaDocumento[$indice+1] = isset($cargo['cdd_valor']) && !empty($cargo['cdd_valor']) ? number_format($cargo['cdd_valor'], 2, '.', '') : '';
                            if(isset($cargo['cdd_valor']) && !empty($cargo['cdd_valor']))
                                $totalCargos += $cargo['cdd_valor'];
                            $indice = $indice +2;
                        }

                        // Índices 31 a 32 corresponden a datos del descuento
                        $descuentos = array_filter($documento['get_cargos_descuentos'], function($descuento) {
                            return $descuento['cdd_tipo'] == 'DESCUENTO';
                        });

                        foreach($descuentos as $descuento) {
                            $filaDocumento[32] = isset($descuento['cdd_razon']) && !empty($descuento['cdd_razon']) ? $descuento['cdd_razon'] : '';
                            $filaDocumento[33] = isset($descuento['cdd_valor']) && !empty($descuento['cdd_valor']) ? number_format($descuento['cdd_valor'], 2, '.', '') : '';
                            if(isset($descuento['cdd_valor']) && !empty($descuento['cdd_valor']))
                                $totalDescuentos += $descuento['cdd_valor'];
                        }
                    }

                    if(!empty($item['ddo_informacion_adicional'])) {
                        // Índices 22 a 31 corresponden a los conceptos
                        $filaDocumento[22] = isset($item['ddo_informacion_adicional']['concepto_1']) && !empty($item['ddo_informacion_adicional']['concepto_1']) ? $item['ddo_informacion_adicional']['concepto_1'] : '';
                        $filaDocumento[23] = isset($item['ddo_informacion_adicional']['concepto_1_valor']) && !empty($item['ddo_informacion_adicional']['concepto_1_valor']) ? number_format($item['ddo_informacion_adicional']['concepto_1_valor'], 2, '.', '') : '';
                        $filaDocumento[24] = isset($item['ddo_informacion_adicional']['concepto_2']) && !empty($item['ddo_informacion_adicional']['concepto_2']) ? $item['ddo_informacion_adicional']['concepto_2'] : '';
                        $filaDocumento[25] = isset($item['ddo_informacion_adicional']['concepto_2_valor']) && !empty($item['ddo_informacion_adicional']['concepto_2_valor']) ? number_format($item['ddo_informacion_adicional']['concepto_2_valor'], 2, '.', '') : '';
                        $filaDocumento[26] = isset($item['ddo_informacion_adicional']['concepto_3']) && !empty($item['ddo_informacion_adicional']['concepto_3']) ? $item['ddo_informacion_adicional']['concepto_3'] : '';
                        $filaDocumento[27] = isset($item['ddo_informacion_adicional']['concepto_3_valor']) && !empty($item['ddo_informacion_adicional']['concepto_3_valor']) ? number_format($item['ddo_informacion_adicional']['concepto_3_valor'], 2, '.', '') : '';
                        $filaDocumento[28] = isset($item['ddo_informacion_adicional']['concepto_4']) && !empty($item['ddo_informacion_adicional']['concepto_4']) ? $item['ddo_informacion_adicional']['concepto_4'] : '';
                        $filaDocumento[29] = isset($item['ddo_informacion_adicional']['concepto_4_valor']) && !empty($item['ddo_informacion_adicional']['concepto_4_valor']) ? number_format($item['ddo_informacion_adicional']['concepto_4_valor'], 2, '.', '') : '';
                        $filaDocumento[30] = isset($item['ddo_informacion_adicional']['concepto_5']) && !empty($item['ddo_informacion_adicional']['concepto_5']) ? $item['ddo_informacion_adicional']['concepto_5'] : '';
                        $filaDocumento[31] = isset($item['ddo_informacion_adicional']['concepto_5_valor']) && !empty($item['ddo_informacion_adicional']['concepto_5_valor']) ? number_format($item['ddo_informacion_adicional']['concepto_5_valor'], 2, '.', '') : '';
                    }

                    // Índices 34 a 37 corresponden a datos del detalle
                    $filaDocumento[34] = $item['ddo_descripcion_uno'];
                    $filaDocumento[35] = $item['ddo_descripcion_dos'];
                    $filaDocumento[36] = number_format($item['ddo_cantidad'], 2, '.', '');
                    $filaDocumento[37] = number_format($item['ddo_valor_unitario'], 2, '.', '');

                    // La columna del total (índice 37) se calcula asi:
                    // valor del item (valor unitario*cantidad) + cargos + iva - descuentos
                    // los cargos son los que corresponden a las columnas cargo 1 hasta cargo 5, y el descuento es el de cabecera del documento
                    $iva   = isset($item['get_impuestos_items_documentos'][0]['iid_valor']) && !empty($item['get_impuestos_items_documentos'][0]['iid_valor']) ? $item['get_impuestos_items_documentos'][0]['iid_valor'] : 0;
                    $total = ($item['ddo_total'] + $totalCargos + $iva) - $totalDescuentos;
                    $filaDocumento[38] = number_format($total, 2, '.', '');
                    
                    // Índices 38 a 40 corresponden a datos del IVA
                    $filaDocumento[39] = isset($item['get_impuestos_items_documentos'][0]['iid_base']) && !empty($item['get_impuestos_items_documentos'][0]['iid_base']) ? number_format($item['get_impuestos_items_documentos'][0]['iid_base'], 2, '.', '') : '';
                    $filaDocumento[40] = isset($item['get_impuestos_items_documentos'][0]['iid_porcentaje']) && !empty($item['get_impuestos_items_documentos'][0]['iid_porcentaje']) ? number_format($item['get_impuestos_items_documentos'][0]['iid_porcentaje'], 2, '.', '') : '';
                    $filaDocumento[41] = isset($item['get_impuestos_items_documentos'][0]['iid_valor']) && !empty($item['get_impuestos_items_documentos'][0]['iid_valor']) ? number_format($item['get_impuestos_items_documentos'][0]['iid_valor'], 2, '.', '') : '';

                    $filasItems[] = $filaDocumento;
                }
            }
            $arrDocumentos = $filasItems;

            date_default_timezone_set('America/Bogota');
            $nombreArchivo = 'facturacion_manual_pickup_cash_' . date('YmdHis');
            $archivoExcel = $this->toExcel($titulos, $arrDocumentos, $nombreArchivo);

            // Renombra el archivo y lo mueve al disco de descargas porque el método toExcel crea el archivo sobre el disco local
            File::move($archivoExcel, storage_path('etl/descargas/' . $nombreArchivo . '.xlsx'));
            File::delete($archivoExcel);
            return [
                'errors'  => [],
                'archivo' => $nombreArchivo . '.xlsx'
            ];
        } catch (\Exception $e) {
            return [
                'errors' => [ $e->getMessage() ],
                'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
            ];
        }

        /* try {
            set_time_limit(0);
            ini_set('memory_limit','2048M');

            $user = auth()->user();

            $required_where_conditions = [];
            define('SEARCH_METHOD', 'BusquedaEnviados');

            $optional_where_conditions = [
                'etl_cabecera_documentos_daop.adq_id',
                'etl_cabecera_documentos_daop.cdo_origen',
                'etl_cabecera_documentos_daop.cdo_clasificacion',
                'etl_cabecera_documentos_daop.cdo_lote',
                'etl_cabecera_documentos_daop.rfa_prefijo',
                'etl_cabecera_documentos_daop.cdo_consecutivo'
            ];
            
            if (isset($request->cdo_consecutivo) && $request->cdo_consecutivo !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.cdo_consecutivo'] = $request->cdo_consecutivo;
            }

            if (isset($request->rfa_prefijo) && $request->rfa_prefijo !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.rfa_prefijo'] = $request->rfa_prefijo;
            }

            if (isset($request->cdo_clasificacion) && $request->cdo_clasificacion !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.cdo_clasificacion'] = $request->cdo_clasificacion;
            }

            if (isset($request->cdo_lote) && $request->cdo_lote !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.cdo_lote'] = $request->cdo_lote;
            }

            if (isset($request->cdo_origen) && $request->cdo_origen !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.cdo_origen'] = $request->cdo_origen;
            }

            if (isset($request->estado) && $request->estado !== '') {
                $required_where_conditions['etl_cabecera_documentos_daop.estado'] = $request->estado;
            }

            $special_where_conditions = [
                [
                    'type' => 'NotNull',
                    'field' => 'etl_cabecera_documentos_daop.cdo_fecha_procesar_documento',
                ],

            ];

            if($request->has('adq_id') && $request->adq_id !== '' && !empty($request->adq_id )) {
                $special_where_conditions[] = [
                    'type'  => 'in',
                    'field' => 'etl_cabecera_documentos_daop.adq_id',
                    'value' => explode(',', $request->adq_id)
                ];
            }

            if ($request->has('ofe_id')) {
                array_push($special_where_conditions,
                    [
                        'type' => 'join',
                        'table' => 'etl_obligados_facturar_electronicamente',
                        'on_conditions'  => [
                            [
                                'from' => 'etl_cabecera_documentos_daop.ofe_id',
                                'operator' => '=',
                                'to' => 'etl_obligados_facturar_electronicamente.ofe_id',
                            ]
                        ]
                    ]
                );
            }

            if ($request->has('ofe_filtro') && !empty($request->get('ofe_filtro')) && $request->has('ofe_filtro_buscar') && !empty($request->get('ofe_filtro_buscar'))) {
                //Filtros a nivel de cabecera
                switch($request->get('ofe_filtro')){
                    case 'cdo_representacion_grafica_documento':
                        $required_where_conditions['etl_cabecera_documentos_daop.'.$request->get('ofe_filtro')] = $request->get('ofe_filtro_buscar');
                    break;
                    default:
                        $array_filtros = [
                            'type' => 'join',
                            'table' => 'etl_datos_adicionales_documentos_daop',
                            'on_conditions'  => [
                                [
                                    'from' => 'etl_cabecera_documentos_daop.cdo_id',
                                    'operator' => '=',
                                    'to' => 'etl_datos_adicionales_documentos_daop.cdo_id',
                                ]
                            ],
                        ];
            
                        $array_filtros['where_conditions'][] = [
                            'from' => DB::raw("JSON_EXTRACT(etl_datos_adicionales_documentos_daop.cdo_informacion_adicional , '$.{$request->get("ofe_filtro")}')"),
                            'operator' => '=',
                            'to' => $request->get('ofe_filtro_buscar'),
                        ];
            
                        array_push($special_where_conditions, $array_filtros);
                    break;
                }
            }

            if (!empty($request->cdo_fecha_envio_desde) && !empty($request->cdo_fecha_envio_hasta)){
                array_push($special_where_conditions, [
                    'type' => 'Between',
                    'field' => 'etl_cabecera_documentos_daop.cdo_fecha_procesar_documento',
                    'value' => [$request->cdo_fecha_envio_desde . " 00:00:00", $request->cdo_fecha_envio_hasta . " 23:59:59"]
                ]);
            }

            $required_where_conditions['etl_cabecera_documentos_daop.cdo_procesar_documento'] = 'SI';
            $required_where_conditions['etl_cabecera_documentos_daop.ofe_id'] = $request->get('ofe_id');

            $arrRelaciones = [
                'getConfiguracionObligadoFacturarElectronicamente',
                'getConfiguracionAdquirente',
                // 'getDocumentosAnexos',
                'getParametrosMoneda',
                'getParametrosMonedaExtranjera',
                'getTipoOperacion:top_id,top_codigo,top_descripcion',
                'getConfiguracionResolucionesFacturacion',
                // 'getEstadosDocumentosDaop:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,fecha_creacion',
                'getNotificacionFinalizado',
                'getDocumentoAprobado:est_id,cdo_id,est_resultado,est_mensaje_resultado',
                'getDocumentoAprobadoNotificacion:est_id,cdo_id,est_resultado,est_mensaje_resultado',
                'getDocumentoRechazado:est_id,cdo_id,est_resultado,est_mensaje_resultado',
                'getDadDocumentosDaop:dad_id,cdo_id,cdo_informacion_adicional',
                'getDetalleDocumentosDaop:ddo_id,cdo_id,ddo_descripcion_uno,ddo_descripcion_dos,ddo_cantidad,ddo_valor_unitario,ddo_valor_unitario_moneda_extranjera,ddo_total,ddo_total_moneda_extranjera,ddo_informacion_adicional',
                'getCargosDescuentosDocumentosDaop' => function($query) {
                    $query->select(['cdd_id', 'cdo_id', 'ddo_id', 'cdd_aplica', 'cdd_tipo', 'cdd_razon', 'cdd_valor'])
                        ->whereNull('ddo_id')
                        ->where('cdd_aplica', 'CABECERA')
                        ->where(function($query) {
                            $query->where('cdd_tipo', 'CARGO')
                                ->orWhere('cdd_tipo', 'DESCUENTO');
                        })
                        ->where('estado', 'ACTIVO')
                        ->orderBy('cdd_numero_linea', 'asc');
                },
                'getDetalleDocumentosDaop.getImpuestosItemsDocumentosDaop' => function ($query) {
                    $query->select(['iid_id', 'cdo_id', 'ddo_id', 'tri_id', 'iid_tipo', 'iid_porcentaje', 'iid_base', 'iid_base_moneda_extranjera', 'iid_valor', 'iid_valor_moneda_extranjera'])
                        ->where('iid_tipo', 'TRIBUTO')
                        ->where('estado', 'ACTIVO')
                        ->with([
                            'getTributo' => function($query) {
                                $query->where('tri_codigo', '01')
                                    ->where('estado', 'ACTIVO');
                            }
                        ]);
                }
            ];

            $where_has_conditions = [];
            $where_doesnthave_conditions = '';

            if ($request->has('estado_dian')) {
                switch($request->estado_dian) {
                    case 'aprobado':
                        $where_has_conditions[] = [
                            'relation' => 'getDocumentoAprobado'
                        ];
                        break;
                    case 'aprobado_con_notificacion':
                        $where_has_conditions[] = [
                            'relation' => 'getDocumentoAprobadoNotificacion'
                        ];
                        break;
                    case 'rechazado':
                        $where_has_conditions[] = [
                            'relation' => 'getDocumentoRechazado'
                        ];
                        $where_doesnthave_conditions = 'getDoFinalizado';
                        break;
                }
            }

            if(!empty($user->bdd_id_rg)) {
                $where_has_conditions[] = [
                    'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                    'function' => function($query) use ($user) {
                        $query->where('bdd_id_rg', $user->bdd_id_rg);
                    }
                ];
            } else{
                $where_has_conditions[] = [
                    'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                    'function' => function($query) {
                        $query->whereNull('bdd_id_rg');
                    }
                ];
            }

            $titulos = [
                'PREFIJO',
                'CONSECUTIVO',
                'FECHA-HORA',
                'NUMERO DOCUMENTO REFERENCIA',
                'FECHA DOCUMENTO REFERENCIA',
                'CUENTA',
                'NIT ADQUIRENTE',
                'No. DE CUENTA',
                'ADQUIRENTE',
                'GUIA',
                'TRM',
                'MONEDA',
                'CARGO 1',
                'VALOR CARGO 1',
                'CARGO 2',
                'VALOR CARGO 2',
                'CARGO 3',
                'VALOR CARGO 3',
                'CARGO 4',
                'VALOR CARGO 4',
                'CARGO 5',
                'VALOR CARGO 5',
                'CONCEPTO 1',
                'VALOR CONCEPTO 1',
                'CONCEPTO 2',
                'VALOR CONCEPTO 2',
                'CONCEPTO 3',
                'VALOR CONCEPTO 3',
                'CONCEPTO 4',
                'VALOR CONCEPTO 4',
                'CONCEPTO 5',
                'VALOR CONCEPTO 5',
                'DESCRIPCION DESCUENTO',
                'VALOR DESCUENTO',
                'DESCRIPCION 1',
                'DESCRIPCION 2',
                'CANTIDAD',
                'VALOR UNITARIO',
                'TOTAL',
                'BASE IVA',
                '% IVA',
                'VALOR IVA'
            ];

            $columnas = [
                'rfa_prefijo',
                [
                    'do' => function ($cdo_clasificacion, $extra_data) {
                        return $extra_data['rfa_prefijo'] . $extra_data['cdo_consecutivo'];
                    },
                    'extra_data' => [
                        'cdo_consecutivo',
                        'rfa_prefijo'
                    ],
                    'field' => 'cdo_clasificacion',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_fecha, $extra_data) {
                        return $cdo_fecha . ' ' . $extra_data['cdo_hora'];
                    },
                    'extra_data' => [
                        'cdo_hora'
                    ],
                    'field' => 'cdo_fecha',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_documento_referencia) {
                        if($cdo_documento_referencia != '') {
                            $documentoReferencia = json_decode($cdo_documento_referencia);
                            if(is_array($documentoReferencia))
                                $documentoReferencia = $documentoReferencia[0];

                            return ((isset($documentoReferencia->prefijo) && $documentoReferencia->prefijo != '') ? $documentoReferencia->prefijo : '') . $documentoReferencia->consecutivo;
                        } else {
                            return '';
                        }
                    },
                    'field' => 'cdo_documento_referencia',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_documento_referencia) {
                        if($cdo_documento_referencia != '') {
                            $documentoReferencia = json_decode($cdo_documento_referencia);
                            if(is_array($documentoReferencia))
                                $documentoReferencia = $documentoReferencia[0];

                            return (isset($documentoReferencia->fecha_emision) && $documentoReferencia->fecha_emision != '') ? $documentoReferencia->fecha_emision : '';
                        } else {
                            return '';
                        }
                    },
                    'field' => 'cdo_documento_referencia',
                    'validation_type'=> 'callback'
                ],
                'get_dad_documentos_daop.0.cdo_informacion_adicional.cuenta',
                'get_configuracion_adquirente.adq_identificacion',
                'get_configuracion_adquirente.adq_id_personalizado',
                'get_configuracion_adquirente.adq_razon_social',
                'get_dad_documentos_daop.0.cdo_informacion_adicional.guia',
                [
                    'do' => function ($cdo_trm) {
                        if($cdo_trm > 0)
                            return number_format($cdo_trm, 2, '.', '');
                        else
                            return '';
                    },
                    'field' => 'cdo_trm',
                    'validation_type'=> 'callback'
                ],
                'get_parametros_moneda.mon_codigo',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.cargo_1',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.valor_cargo_1',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.cargo_2',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.valor_cargo_2',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.cargo_3',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.valor_cargo_3',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.cargo_4',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.valor_cargo_4',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.cargo_5',
                'get_detalle_documentos_daop.0.cdo_informacion_adicional.valor_cargo_5',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                ''
            ];

            $columnasPersonalizadasAdq = $this->ofeColumnasPersonalizadasAdquirentes($request->get('ofe_id'));
            if(!empty($columnasPersonalizadasAdq)) {
                foreach($columnasPersonalizadasAdq as $columnaExcel) {
                    $titulos[]  = $columnaExcel;
                    $columnas[] = [
                        'do' => function ($adqInfoPersonalizada, $extra_data) {
                            if($adqInfoPersonalizada != NULL && $adqInfoPersonalizada != '')
                                $adqInfoPersonalizada = json_decode($adqInfoPersonalizada);
                            else
                                $adqInfoPersonalizada = json_decode(json_encode([]));

                            if(!empty($extra_data) && is_array($extra_data)) { 
                                $indice = key($extra_data);
                                return isset($adqInfoPersonalizada->$indice) ? $adqInfoPersonalizada->$indice : '';
                            } else {
                                return '';
                            }
                        },
                        'extra_data' => [
                            $columnaExcel
                        ],
                        'field' => 'get_configuracion_adquirente.adq_informacion_personalizada',
                        'validation_type'=> 'callback'
                    ];
                }
            }

            $documentos = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, true, true, $where_has_conditions, $where_doesnthave_conditions, 'emision', 'array');

            // Array que pasa al Excel
            $arrDocumentos = $documentos->map(function ($document) use ($columnas) {
                $arr_doc = [];
                $doc = Arr::dot($document->toArray());

                //Añadimos columnas de cabecera al array
                foreach ($columnas as $column) {
                    if (is_array($column)){
                        switch ($column['validation_type']) {
                            case 'callback':
                                $value = $this->runCallback($doc, $column);
                                break;
                            default:
                                break;
                        }
                    } else {
                        $value = $doc[$column] ?? '';
                    }
                    array_push($arr_doc, $value);
                }
                return $arr_doc;
            });

            $arrDocumentos = $arrDocumentos->toArray();

            // Hasta este punto se han armado las columnas conforme a información de cabecera
            // Ahora por cada elemento del array de documentos, toca crear un nuevo elemento por cada item del documento y agregar la información del mismo
            $filasItems   = [];
            $indiceDocumento = 0;
            foreach($documentos as $documento) {
                $filaDocumento = $arrDocumentos[$indiceDocumento];
                foreach($documento->getDetalleDocumentosDaop as $item) {
                    $totalCargos     = 0;
                    $totalDescuentos = 0;
                    if(!empty($documento->getCargosDescuentosDocumentosDaop)) {
                        // Índices 11 a 20 corresponden a los cargos
                        $cargos = $documento->getCargosDescuentosDocumentosDaop
                            ->filter(function($cargo) {
                                return $cargo->cdd_tipo == 'CARGO';
                            });

                        $indice = 11;
                        foreach($cargos as $cargo) {
                            $filaDocumento[$indice]   = isset($cargo->cdd_razon) && !empty($cargo->cdd_razon) ? $cargo->cdd_razon : '';
                            $filaDocumento[$indice+1] = isset($cargo->cdd_valor) && !empty($cargo->cdd_valor) ? number_format($cargo->cdd_valor, 2, '.', '') : '';
                            if(isset($cargo->cdd_valor) && !empty($cargo->cdd_valor))
                                $totalCargos += $cargo->cdd_valor;
                            $indice = $indice +2;
                        }

                        // Índices 31 a 32 corresponden a datos del descuento
                        $descuentos = $documento->getCargosDescuentosDocumentosDaop
                            ->filter(function($descuento) {
                                return $descuento->cdd_tipo == 'DESCUENTO';
                            });

                        foreach($descuentos as $descuento) {
                            $filaDocumento[31] = isset($descuento->cdd_razon) && !empty($descuento->cdd_razon) ? $descuento->cdd_razon : '';
                            $filaDocumento[32] = isset($descuento->cdd_valor) && !empty($descuento->cdd_valor) ? number_format($descuento->cdd_valor, 2, '.', '') : '';
                            if(isset($descuento->cdd_valor) && !empty($descuento->cdd_valor))
                                $totalDescuentos += $descuento->cdd_valor;
                        }
                    }
                    
                    if(!empty($item->ddo_informacion_adicional)) {
                        $cargosConceptos = json_decode($item->ddo_informacion_adicional);

                        // Índices 21 a 30 corresponden a los conceptos
                        $filaDocumento[21] = isset($cargosConceptos->concepto_1) && !empty($cargosConceptos->concepto_1) ? $cargosConceptos->concepto_1 : '';
                        $filaDocumento[22] = isset($cargosConceptos->concepto_1_valor) && !empty($cargosConceptos->concepto_1_valor) ? number_format($cargosConceptos->concepto_1_valor, 2, '.', '') : '';
                        $filaDocumento[23] = isset($cargosConceptos->concepto_2) && !empty($cargosConceptos->concepto_2) ? $cargosConceptos->concepto_2 : '';
                        $filaDocumento[24] = isset($cargosConceptos->concepto_2_valor) && !empty($cargosConceptos->concepto_2_valor) ? number_format($cargosConceptos->concepto_2_valor, 2, '.', '') : '';
                        $filaDocumento[25] = isset($cargosConceptos->concepto_3) && !empty($cargosConceptos->concepto_3) ? $cargosConceptos->concepto_3 : '';
                        $filaDocumento[26] = isset($cargosConceptos->concepto_3_valor) && !empty($cargosConceptos->concepto_3_valor) ? number_format($cargosConceptos->concepto_3_valor, 2, '.', '') : '';
                        $filaDocumento[27] = isset($cargosConceptos->concepto_4) && !empty($cargosConceptos->concepto_4) ? $cargosConceptos->concepto_4 : '';
                        $filaDocumento[28] = isset($cargosConceptos->concepto_4_valor) && !empty($cargosConceptos->concepto_4_valor) ? number_format($cargosConceptos->concepto_4_valor, 2, '.', '') : '';
                        $filaDocumento[29] = isset($cargosConceptos->concepto_5) && !empty($cargosConceptos->concepto_5) ? $cargosConceptos->concepto_5 : '';
                        $filaDocumento[30] = isset($cargosConceptos->concepto_5_valor) && !empty($cargosConceptos->concepto_5_valor) ? number_format($cargosConceptos->concepto_5_valor, 2, '.', '') : '';
                    }

                    // Índices 33 a 37 corresponden a datos del detalle
                    $filaDocumento[33] = $item->ddo_descripcion_uno;
                    $filaDocumento[34] = $item->ddo_descripcion_dos;
                    $filaDocumento[35] = number_format($item->ddo_cantidad, 2, '.', '');
                    $filaDocumento[36] = number_format($item->ddo_valor_unitario, 2, '.', '');

                    // La columna del total (índice 37) se calcula asi:
                    // valor del item (valor unitario*cantidad) + cargos + iva - descuentos
                    // los cargos son los que corresponden a las columnas cargo 1 hasta cargo 5, y el descuento es el de cabecera del documento
                    $iva   = isset($item->getImpuestosItemsDocumentosDaop->iid_valor) && !empty($item->getImpuestosItemsDocumentosDaop->iid_valor) ? $item->getImpuestosItemsDocumentosDaop->iid_valor : 0;
                    $total = ($item->ddo_total + $totalCargos + $iva) - $totalDescuentos;
                    $filaDocumento[37] = number_format($total, 2, '.', '');
                    
                    // Índices 38 a 40 corresponden a datos del IVA
                    $filaDocumento[38] = isset($item->getImpuestosItemsDocumentosDaop->iid_base) && !empty($item->getImpuestosItemsDocumentosDaop->iid_base) ? number_format($item->getImpuestosItemsDocumentosDaop->iid_base, 2, '.', '') : '';
                    $filaDocumento[39] = isset($item->getImpuestosItemsDocumentosDaop->iid_porcentaje) && !empty($item->getImpuestosItemsDocumentosDaop->iid_porcentaje) ? number_format($item->getImpuestosItemsDocumentosDaop->iid_porcentaje, 2, '.', '') : '';
                    $filaDocumento[40] = isset($item->getImpuestosItemsDocumentosDaop->iid_valor) && !empty($item->getImpuestosItemsDocumentosDaop->iid_valor) ? number_format($item->getImpuestosItemsDocumentosDaop->iid_valor, 2, '.', '') : '';

                    $filasItems[] = $filaDocumento;
                }
                $indiceDocumento++;
            }
            $arrDocumentos = $filasItems;

            date_default_timezone_set('America/Bogota');
            $nombreArchivo = 'facturacion_manual_pickup_cash_' . date('YmdHis');
            $archivoExcel = $this->toExcel($titulos, $arrDocumentos, $nombreArchivo);

            $reporte = [
                'ruta'   => $archivoExcel,
                'nombre' => $nombreArchivo . '.xlsx'
            ];

            // Renombra el archivo y lo mueve al disco de descargas porque el método toExcel crea el archivo sobre el disco local
            File::move($archivoExcel, storage_path('etl/descargas/' . $nombreArchivo . '.xlsx'));
            File::delete($archivoExcel);
            return [
                'errors'  => [],
                'archivo' => $nombreArchivo . '.xlsx'
            ];
        } catch (\Exception $e) {
            return [
                'errors' => [ $e->getMessage() ],
                'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
            ];
        } */
    }

    /**
     * Retorna la lista de reportes que se han solicitado durante el día.
     * 
     * El listado de reportes solamente se retorna para el usuario autenticado
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function dhlExpressListarReportesDescargas(Request $request): JsonResponse {
        $user = auth()->user();
        $reportes = [];
        $start          = $request->filled('start')          ? $request->start          : 0;
        $length         = $request->filled('length')         ? $request->length         : 10;
        $ordenDireccion = $request->filled('ordenDireccion') ? $request->ordenDireccion : 'DESC';
        $columnaOrden   = $request->filled('columnaOrden')   ? $request->columnaOrden   : 'fecha_modificacion';

        $consulta = EtlProcesamientoJson::select(['pjj_id', 'pjj_json', 'age_estado_proceso_json', 'fecha_modificacion'])
            ->where('pjj_tipo', 'REPORTEDHLEXPRESS')
            ->whereBetween('fecha_modificacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'])
            ->where('usuario_creacion', $user->usu_id)
            ->where('estado', 'ACTIVO')
            ->when($request->filled('buscar'), function ($query) use ($request) {
                return $query->where(function($query) use ($request) {
                    $query->where('fecha_modificacion', 'like', '%' . $request->buscar . '%')
                        ->orWhere('pjj_json', 'like', '%' . $request->buscar . '%');

                    if(strpos(strtolower($request->buscar), 'proceso') !== false)
                        $query->orWhere('age_estado_proceso_json', 'like', '%proceso%')
                            ->orWhereNull('age_estado_proceso_json');
                });
            });

        $totalReportes = $consulta->count();
        $length = $length != -1 ? $length : $totalReportes;

        $consulta->orderBy($columnaOrden, $ordenDireccion)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function($reporte) use (&$reportes) {
                $pjjJson = json_decode($reporte->pjj_json);
                $fechaModificacion = Carbon::createFromFormat('Y-m-d H:i:s', $reporte->fecha_modificacion)->format('Y-m-d H:i:s');

                if($pjjJson->tipo_reporte == 'sin_envio')
                    $tipoReporte = 'Documentos Sin Envío';
                if($pjjJson->tipo_reporte == 'facturacion_manual_pickup_cash')
                    $tipoReporte = 'Facturación Manual y Pickup Cash';
                if($pjjJson->tipo_reporte == 'enviados')
                    $tipoReporte = 'Documentos Enviados';
                if($pjjJson->tipo_reporte == 'archivo_entrada_pickup_cash')
                    $tipoReporte = 'Archivo Entrada Pickup Cash';

                if($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->archivo_reporte)) {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => $pjjJson->archivo_reporte,
                        'fecha'           => $fechaModificacion,
                        'errores'         => '',
                        'tipo_reporte'    => $tipoReporte
                    ];
                } elseif($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->errors)) {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => '',
                        'fecha'           => $fechaModificacion,
                        'errores'         => $pjjJson->errors,
                        'estado'          => 'FINALIZADO',
                        'tipo_reporte'    => $tipoReporte
                    ];
                } elseif($reporte->age_estado_proceso_json != 'FINALIZADO') {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => '',
                        'fecha'           => $fechaModificacion,
                        'errores'         => '',
                        'estado'          => 'EN PROCESO',
                        'tipo_reporte'    => $tipoReporte
                    ];
                }
            });

        return response()->json([
            'total'     => $totalReportes,
            'filtrados' => $totalReportes,
            'data'      => $reportes
        ], 200);
    }

    /**
     * Descarga un reporte generado por el usuario autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dhlExpressDescargarReporte(Request $request) {
        $user = auth()->user();

        // Verifica que el pjj_id del request pertenezca al usuario autenticado
        $pjj = EtlProcesamientoJson::find($request->pjj_id);

        if($pjj && $pjj->usuario_creacion == $user->usu_id) {
            if($pjj->pjj_json != '') {
                $pjjJson = json_decode($pjj->pjj_json);
                $archivo = $pjjJson->archivo_reporte;

                $headers = [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ];

                if(is_file(storage_path('etl/descargas/' . $archivo)))
                    return response()
                        ->download(storage_path('etl/descargas/' . $archivo), $archivo, $headers);
                else
                    // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
                    // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
                    $headersError = [
                        header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                        header('X-Error-Status: 422'),
                        header('X-Error-Message: Archivo no encontrado')
                    ];
                    return response()->json([
                        'message' => 'Error en la descarga',
                        'errors' => ['Archivo no encontrado']
                    ], 409, $headersError);
            }
        } elseif($pjj && $pjj->usuario_creacion != $user->usu_id) {
            // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
            // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
            $headersError = [
                header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                header('X-Error-Status: 422'),
                header('X-Error-Message: Usted no tiene permisos para descargar el archivo solicitado')
            ];
            return response()->json([
                'message' => 'Error en la descarga',
                'errors' => ['Usted no tiene permisos para descargar el archivo solicitado']
            ], 409, $headersError);
        } else {
            // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
            // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
            $headersError = [
                header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                header('X-Error-Status: 422'),
                header('X-Error-Message: No se encontr&oacute; el registro asociado a la consulta')
            ];
            return response()->json([
                'message' => 'Error en la descarga',
                'errors' => ['No se encontró el registro asociado a la consulta']
            ], 404, $headersError);
        }
    }

    /**
     * Columnas personalizadas de adquirentes definidas por cada OFE en la base de datos.
     * 
     * @param int $ofeId ID del OFE para el cual se programó la generación del reporte
     * @return array $columnasPersonalizadas Array de columnas personalizadas
     */
    private function ofeColumnasPersonalizadasAdquirentes($ofeId) {
        $columnasPersonalizadas     = [];
        $ofesColumnasPersonalizadas = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_informacion_personalizada_adquirente'])
            ->where('ofe_id', $ofeId)
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function($columna) use (&$columnasPersonalizadas) {
                if(!empty($columna->ofe_informacion_personalizada_adquirente))
                    $columnasPersonalizadas = array_merge($columna->ofe_informacion_personalizada_adquirente, $columnasPersonalizadas);
            });

        return array_values(array_unique($columnasPersonalizadas));
    }

    /**
     * Genera un reporte en Excel para DHL Express de archivos de entrada Pickup Cash de acuerdo a los filtros escogidos.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function dhlExpressArchivoEntradaPickupCash(Request $request) {
        try {
            set_time_limit(0);
            ini_set('memory_limit','2048M');

            $user = auth()->user();

            $titulos = [
                'FECHA CARGUE ARCHIVO',
                'USUARIO',
                'NOMBRE ARCHIVO',
                'FECHA DE LA GUIA',
                'NUMERO DE LA GUIA',
                'CODIGO DE ESTACION',
                'OFICINA DE VENTA',
                'ORGANIZACION DE VENTAS',
                'NUMERO DE NOTA',
                'IMPORTE',
                'CUENTA'
            ];

            $filas = [];
            PryArchivoPickupCash::select([
                    'apc_id',
                    'apc_nombre_archivo_original',
                    'apc_nombre_archivo_carpeta',
                    'usuario_creacion',
                    'fecha_creacion',
                ])
                ->where('estado', 'ACTIVO')
                ->whereBetween('fecha_creacion', [$request->apc_cargue_desde . ' 00:00:00', $request->apc_cargue_hasta . ' 23:59:59'])
                ->with([
                    'getUsuarioCreacion:usu_id,usu_nombre',
                    'getDetalleArchivoPickupCash' => function($query) use ($request) {
                        if(
                            $request->filled('campo_buscar') &&
                            $request->filled('valor_buscar')
                        ) {
                            if($request->campo_buscar == 'cuenta')
                                $query->where('dpc_cuenta', $request->valor_buscar);
                            elseif($request->campo_buscar == 'guia')
                                $query->where('dpc_numero_guia', $request->valor_buscar);
                        }
                    }
                ])
                ->get()
                ->map(function($cargue) use (&$filas) {
                    foreach($cargue->getDetalleArchivoPickupCash as $detalle) {
                        $filas[] = [
                            $cargue->fecha_creacion->format('Y-m-d H:i:s'),
                            $cargue->getUsuarioCreacion->usu_nombre,
                            $cargue->apc_nombre_archivo_original,
                            $detalle->dpc_fecha_guia,
                            $detalle->dpc_numero_guia,
                            $detalle->dpc_codigo_estacion,
                            $detalle->dpc_oficina_venta,
                            $detalle->dpc_organizacion_ventas,
                            $detalle->dpc_numero_nota,
                            $detalle->dpc_importe,
                            $detalle->dpc_cuenta
                        ];
                    }
                });

            date_default_timezone_set('America/Bogota');
            $nombreArchivo = 'archivos_entrada_pickup_cash_' . date('YmdHis');
            $archivoExcel = $this->toExcel($titulos, $filas, $nombreArchivo);

            // Renombra el archivo y lo mueve al disco de descargas porque el método toExcel crea el archivo sobre otro disco
            File::move($archivoExcel, storage_path('etl/descargas/' . $nombreArchivo . '.xlsx'));
            File::delete($archivoExcel);

            return [
                'errors'  => [],
                'archivo' => $nombreArchivo . '.xlsx'
            ];
        } catch (\Exception $e) {
            return [
                'errors' => [ $e->getMessage() ],
                'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
            ];
        }
    }
}
