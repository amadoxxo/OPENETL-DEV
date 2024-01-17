<?php
namespace App\Http\Modulos\Recepcion\Reportes; 

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use App\Http\Modulos\Documentos\BaseDocumentosController;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Repositories\Recepcion\RepCabeceraDocumentoRepository;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class RecepcionDocumentosProcesadosController extends BaseDocumentosController {
    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:RecepcionReporteDocumentosProcesados'
        ])->only([
            'agendarReporte',
            'procesarAgendamientoReporte',
            'listarReportesDescargar',
            'descargarReporte'
        ]);
    }

    /**
     * Contiene las columnas que pueden ser listadas.
     *
     * @var array
     */
    public $columns = [
        'rep_cabecera_documentos_daop.cdo_id',
        'rep_cabecera_documentos_daop.cdo_origen',
        'rep_cabecera_documentos_daop.cdo_clasificacion',
        'rep_cabecera_documentos_daop.gtr_id',
        'rep_cabecera_documentos_daop.tde_id',
        'rep_cabecera_documentos_daop.top_id',
        'rep_cabecera_documentos_daop.cdo_lote',
        'rep_cabecera_documentos_daop.ofe_id',
        'rep_cabecera_documentos_daop.pro_id',
        'rep_cabecera_documentos_daop.mon_id',
        'rep_cabecera_documentos_daop.rfa_resolucion',
        'rep_cabecera_documentos_daop.rfa_prefijo',
        'rep_cabecera_documentos_daop.cdo_consecutivo',
        'rep_cabecera_documentos_daop.cdo_fecha',
        'rep_cabecera_documentos_daop.cdo_hora',
        'rep_cabecera_documentos_daop.cdo_vencimiento',
        'rep_cabecera_documentos_daop.cdo_observacion',
        'rep_cabecera_documentos_daop.cdo_valor_sin_impuestos',
        'rep_cabecera_documentos_daop.cdo_valor_sin_impuestos_moneda_extranjera',
        'rep_cabecera_documentos_daop.cdo_impuestos',
        'rep_cabecera_documentos_daop.cdo_impuestos_moneda_extranjera',
        'rep_cabecera_documentos_daop.cdo_valor_a_pagar',
        'rep_cabecera_documentos_daop.cdo_valor_a_pagar_moneda_extranjera',
        'rep_cabecera_documentos_daop.cdo_cargos',
        'rep_cabecera_documentos_daop.cdo_cargos_moneda_extranjera',
        'rep_cabecera_documentos_daop.cdo_descuentos',
        'rep_cabecera_documentos_daop.cdo_descuentos_moneda_extranjera',
        'rep_cabecera_documentos_daop.cdo_redondeo',
        'rep_cabecera_documentos_daop.cdo_redondeo_moneda_extranjera',
        'rep_cabecera_documentos_daop.cdo_anticipo',
        'rep_cabecera_documentos_daop.cdo_anticipo_moneda_extranjera',
        'rep_cabecera_documentos_daop.cdo_retenciones',
        'rep_cabecera_documentos_daop.cdo_retenciones_moneda_extranjera',
        'rep_cabecera_documentos_daop.cdo_cufe',
        'rep_cabecera_documentos_daop.cdo_fecha_validacion_dian',
        'rep_cabecera_documentos_daop.cdo_fecha_recibo_bien',
        'rep_cabecera_documentos_daop.cdo_fecha_acuse',
        'rep_cabecera_documentos_daop.cdo_estado',
        'rep_cabecera_documentos_daop.cdo_fecha_estado',
        'rep_cabecera_documentos_daop.usuario_creacion',
        'rep_cabecera_documentos_daop.estado'
    ];

    /**
     * Crea el agendamiento en el sistema para poder procesar y generar el archivo de Excel en background.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    function agendarReporte(Request $request): JsonResponse {
        $user = auth()->user();

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
            'pjj_tipo'         => 'REPORTEDOCPROCESADOS',
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
     * Genera un reporte en Excel de acuerdo a los filtros seleccionados.
     *
     * @param Request $request Parámetros de la petición
     * @return Array
     * @throws \Exception
     */
    public function procesarAgendamientoReporte(Request $request): Array {
        try {
            set_time_limit(0);
            ini_set('memory_limit','2048M');

            $user = auth()->user();

            $required_where_conditions = [];
            define('SEARCH_METHOD', 'BusquedaEnviados');

            $optional_where_conditions = [
                'rep_cabecera_documentos_daop.pro_id',
                'rep_cabecera_documentos_daop.cdo_origen',
                'rep_cabecera_documentos_daop.cdo_clasificacion',
                'rep_cabecera_documentos_daop.cdo_lote',
                'rep_cabecera_documentos_daop.rfa_prefijo',
                'rep_cabecera_documentos_daop.cdo_consecutivo'
            ];

            if ($request->filled('cdo_consecutivo')) {
                $required_where_conditions['rep_cabecera_documentos_daop.cdo_consecutivo'] = $request->cdo_consecutivo;
            }

            if ($request->filled('rfa_prefijo')) {
                $required_where_conditions['rep_cabecera_documentos_daop.rfa_prefijo'] = $request->rfa_prefijo;
            }

            if ($request->filled('cdo_clasificacion')) {
                $required_where_conditions['rep_cabecera_documentos_daop.cdo_clasificacion'] = $request->cdo_clasificacion;
            }

            if ($request->filled('cdo_lote')) {
                $required_where_conditions['rep_cabecera_documentos_daop.cdo_lote'] = $request->cdo_lote;
            }

            if ($request->filled('cdo_origen')) {
                $required_where_conditions['rep_cabecera_documentos_daop.cdo_origen'] = $request->cdo_origen;
            }

            if ($request->filled('estado') && in_array($request->estado, ['ACTIVO', 'INACTIVO'])) {
                $required_where_conditions['rep_cabecera_documentos_daop.estado'] = $request->estado;
            }

            $special_where_conditions = [];

            if($request->filled('pro_id')) {
                $special_where_conditions[] = [
                    'type'  => 'in',
                    'field' => 'rep_cabecera_documentos_daop.pro_id',
                    'value' => explode(',', $request->pro_id)
                ];
            }

            if ($request->has('ofe_id')) {
                array_push($special_where_conditions,
                    [
                        'type' => 'join',
                        'table' => 'etl_obligados_facturar_electronicamente',
                        'on_conditions'  => [
                            [
                                'from' => 'rep_cabecera_documentos_daop.ofe_id',
                                'operator' => '=',
                                'to' => 'etl_obligados_facturar_electronicamente.ofe_id',
                            ]
                        ]
                    ]
                );
            }

            array_push($special_where_conditions, [
                'type' => 'Between',
                'field' => 'rep_cabecera_documentos_daop.fecha_creacion',
                'value' => [$request->fecha_creacion_desde . " 00:00:00", $request->fecha_creacion_hasta . " 23:59:59"]
            ]);

            $required_where_conditions['rep_cabecera_documentos_daop.ofe_id'] = $request->ofe_id;

            $arrRelaciones = [
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,ofe_recepcion_fnc_activo',
                'getConfiguracionProveedor' => function($query) use ($request) {
                    $recepcionCabeceraRepository = new RepCabeceraDocumentoRepository();
                    $query = $recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $request->ofe_id, true);

                    if(filled($request->transmision_opencomex)) {
                        $nitsIntegracionOpenComex = explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'));
                        $query->whereIn('pro_identificacion', $nitsIntegracionOpenComex);
                    }
                },
                'getParametrosMoneda:mon_id,mon_codigo,mon_descripcion',
                'getParametrosMonedaExtranjera:mon_id,mon_codigo,mon_descripcion',
                'getTipoDocumentoElectronico:tde_id,tde_codigo,tde_descripcion',
                'getTipoOperacion:top_id,top_codigo,top_descripcion',
                'getEstadosDocumentosDaop' => function($query) {
                    $query->select(['est_id', 'cdo_id', 'est_correos', 'est_informacion_adicional', 'est_estado', 'est_resultado', 'est_mensaje_resultado', 'est_object', 'est_ejecucion', 'fecha_creacion', 'usuario_creacion', 'estado'])
                        ->where('est_estado', 'RDI')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'asc');
                },
                'getEstadosDocumentosDaop.getUsuarioCreacion:usu_id,usu_nombre',
                'getEstadoRdiExitoso:est_id,cdo_id,est_informacion_adicional,est_estado,est_resultado',
                'getDocumentoAprobado:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
                'getDocumentoAprobadoNotificacion:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
                'getDocumentoRechazado:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
                'getRechazado:est_id,cdo_id,est_resultado,est_mensaje_resultado,est_motivo_rechazo'
            ];

            $where_has_conditions   = [];
            $where_has_conditions[] = [
                'relation' => 'getEstadosDocumentosDaop'
            ];
            $where_doesnthave_conditions = '';

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
                    'function' => function($query) use ($user) {
                        $query->whereNull('bdd_id_rg');
                    }
                ];
            }

            $titulos = [
                'NIT RECEPTOR',
                'RECEPTOR',
                'RESOLUCION FACTURACION',
                'NIT EMISOR',
                'EMISOR',
                'CÓDIGO TIPO DOCUMENTO',
                'TIPO DOCUMENTO',
                'CÓDIGO TIPO OPERACION',
                'TIPO OPERACION',
                'PREFIJO',
                'CONSECUTIVO',
                'FECHA DOCUMENTO',
                'HORA DOCUMENTO',
                'FECHA VENCIMIENTO',
                'OBSERVACION',
                'CUFE',
                'MONEDA',
                'TOTAL ANTES DE IMPUESTOS',
                'IMPUESTOS',
                'CARGOS',
                'DESCUENTOS',
                'REDONDEO',
                'TOTAL',
                'ANTICIPOS',
                'RETENCIONES',
                'INCONSISTENCIAS XML-UBL',
                'FECHA VALIDACION DIAN',
                'ESTADO DIAN',
                'RESULTADO DIAN',
                'FECHA ACUSE DE RECIBO',
                'FECHA RECIBO DE BIEN Y/O PRESTACION SERVICIO',
                'ESTADO DOCUMENTO',
                'FECHA ESTADO',
                'MOTIVO RECHAZO',
                'FECHA DE CREACION',
                'ID USUARIO',
                'USUARIO',
                'ESTADO'
            ];

            $columnas = [
                'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion',
                'get_configuracion_obligado_facturar_electronicamente.ofe_razon_social',
                'rfa_resolucion',
                'get_configuracion_proveedor.pro_identificacion',
                'get_configuracion_proveedor.pro_razon_social',
                'get_tipo_documento_electronico.tde_codigo',
                'get_tipo_documento_electronico.tde_descripcion',
                'get_tipo_operacion.top_codigo',
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
                                return str_replace(array("\n","\t"),array(" "," "),substr(implode(' | ', $observacion), 0, 32767));
                            } else {
                                return str_replace(array("\n","\t"),array(" | "," "),substr($cdo_observacion, 0, 32767));
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
                    'do' => function ($cdo_cargos, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_cargos_moneda_extranjera'] : $cdo_cargos;
                    },
                    'extra_data' => [
                        'cdo_cargos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_cargos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_descuentos, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_descuentos_moneda_extranjera'] : $cdo_descuentos;
                    },
                    'extra_data' => [
                        'cdo_descuentos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_descuentos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_redondeo, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_redondeo_moneda_extranjera'] : $cdo_redondeo;
                    },
                    'extra_data' => [
                        'cdo_redondeo_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_redondeo',
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
                    'do' => function ($cdo_anticipo, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_anticipo_moneda_extranjera'] : $cdo_anticipo;
                    },
                    'extra_data' => [
                        'cdo_anticipo_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_anticipo',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_retenciones, $extra_data) {
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_retenciones_moneda_extranjera'] : $cdo_retenciones;
                    },
                    'extra_data' => [
                        'cdo_retenciones_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_retenciones',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('inconsistencia', $informacionAdicional) && !empty($informacionAdicional['inconsistencia'])) {
                                return implode(' || ', $informacionAdicional['inconsistencia']);
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_rdi_exitoso.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
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

            $documentos = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, true, true, $where_has_conditions, $where_doesnthave_conditions, 'recepcion_documentos_procesados', 'array');

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
            // Ahora por cada elemento del array de documentos, toca crear un nuevo elemento por cada estado RDI de cada documento
            $filasItems   = [];
            $indiceDocumento = 0;
            foreach($documentos as $documento) {
                // Descarta documentos sin estados RDI
                if(empty($documento->getEstadosDocumentosDaop)) {
                    $indiceDocumento++;
                    continue;
                }
                
                $filaDocumento = $arrDocumentos[$indiceDocumento];
                // Verifica cuantos estados RDI tiene el documento, esto es porque las columnas 18 a 26 solamente se escriben en el estado RDI más reciente
                $totalEstadosRdi = count($documento->getEstadosDocumentosDaop);
                if($totalEstadosRdi == 1) {
                    $this->complementarValoresColumnas($documento, $filaDocumento);
                    $filaDocumento[34] = $documento->getEstadosDocumentosDaop[0]->fecha_creacion->format('Y-m-d H:i:s');
                    $filaDocumento[35] = $documento->getEstadosDocumentosDaop[0]->usuario_creacion;
                    $filaDocumento[36] = $documento->getEstadosDocumentosDaop[0]->getUsuarioCreacion->usu_nombre;
                    $filaDocumento[37] = $documento->getEstadosDocumentosDaop[0]->estado;
                    $filasItems[]      = $filaDocumento;
                } else {
                    $counterRdi = 1;
                    foreach($documento->getEstadosDocumentosDaop->sortBy('est_id') as $estadoRdi) {
                        if($counterRdi == $totalEstadosRdi) {
                            $this->complementarValoresColumnas($documento, $filaDocumento);
                        }
                        $filaDocumento[34] = $estadoRdi->fecha_creacion->format('Y-m-d H:i:s');
                        $filaDocumento[35] = $estadoRdi->usuario_creacion;
                        $filaDocumento[36] = $estadoRdi->getUsuarioCreacion->usu_nombre;
                        $filaDocumento[37] = $estadoRdi->estado;
                        $filasItems[]      = $filaDocumento;
                        $counterRdi++;
                    }
                }

                $indiceDocumento++;
            }
            $arrDocumentos = $filasItems;

            if(empty($arrDocumentos)) 
                throw new \Exception("No se encontraron registros.");

            date_default_timezone_set('America/Bogota');
            $nombreArchivo = 'documentos_procesados_' . date('YmdHis');
            $archivoExcel  = $this->toExcel($titulos, $arrDocumentos, $nombreArchivo);

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
    }

    /**
     * Complementa las columnas de un registro con los valores correspondientes del documento.
     *
     * @param RepCabeceraDocumentoDaop $documento Colección con la información del documento
     * @param array $filaDocumento Fila del documento que se pasará al Excel
     * @return mixed
     */
    private function complementarValoresColumnas(RepCabeceraDocumentoDaop $documento, array &$filaDocumento) {
        if(
            $documento->getDocumentoAprobado ||
            $documento->getDocumentoAprobadoNotificacion ||
            $documento->getDocumentoRechazado
        ) {
            $filaDocumento[26] = $documento->cdo_fecha_validacion_dian;
            $filaDocumento[27] = $this->definirEstadoDian(Arr::dot($documento->toArray()), 'estado_dian');
            $filaDocumento[28] = $this->definirEstadoDian(Arr::dot($documento->toArray()), 'resultado_dian');
            $filaDocumento[29] = $documento->cdo_fecha_acuse;
            $filaDocumento[30] = $documento->cdo_fecha_recibo_bien;
            $filaDocumento[31] = $documento->cdo_estado;
            $filaDocumento[32] = $documento->cdo_fecha_estado;
            $filaDocumento[33] = $this->obtenerMotivoRechazo(Arr::dot($documento->toArray()), 'motivo_rechazo');
        }
    }

    /**
     * Retorna la lista de reportes que se han solicitado durante el día.
     * 
     * El listado de reportes solamente se retorna para el usuario autenticado
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function listarReportesDescargar(Request $request): JsonResponse {
        $user              = auth()->user();
        $reportes          = [];
        $start             = $request->filled('start')          ? $request->start          : 0;
        $length            = $request->filled('length')         ? $request->length         : 10;
        $ordenDireccion    = $request->filled('ordenDireccion') ? $request->ordenDireccion : 'DESC';
        $columnaOrden      = $request->filled('columnaOrden')   ? $request->columnaOrden   : 'fecha_modificacion';
        $totalReportes     = 0;
        $filtradosReportes = 0;

        $consulta = EtlProcesamientoJson::select(['pjj_id', 'pjj_tipo', 'pjj_json', 'age_estado_proceso_json', 'fecha_modificacion'])
            ->where('pjj_tipo', 'REPORTEDOCPROCESADOS')
            ->whereBetween('fecha_modificacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'])
            ->when(!in_array($user->usu_type, ['ADMINISTRADOR', 'MA']), function ($query) use ($user) {
                return $query->where('usuario_creacion', $user->usu_id);
            })
            ->where('estado', 'ACTIVO')
            ->orderBy($columnaOrden, $ordenDireccion)
            ->get()
            ->filter(function($item) {
                return (strpos($item->pjj_json, '"proceso":"recepcion"') !== false);
            })
            ->map(function($reporte) use ($request, &$reportes, &$totalReportes, &$filtradosReportes) {
                $totalReportes++;

                if (!$request->filled('buscar') || ($request->filled('buscar') && 
                    (strpos($reporte->fecha_modificacion, $request->buscar) !== false || strpos($reporte->pjj_json, $request->buscar) !== false))
                ) {
                    $filtradosReportes++;
                    $pjjJson           = json_decode($reporte->pjj_json);
                    $fechaModificacion = Carbon::createFromFormat('Y-m-d H:i:s', $reporte->fecha_modificacion)->format('Y-m-d H:i:s');
                    $tipoReporte       = 'Documentos Procesados';
                    if($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->archivo_reporte)) {
                        $reportes[] = [
                            'pjj_id'          => $reporte->pjj_id,
                            'archivo_reporte' => $pjjJson->archivo_reporte,
                            'fecha'           => $fechaModificacion,
                            'errores'         => '',
                            'estado'          => 'FINALIZADO',
                            'tipo_reporte'    => $tipoReporte,
                            'existe_archivo'  => file_exists(storage_path('etl/descargas/' . $pjjJson->archivo_reporte))
                        ];
                    } elseif($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->errors)) {
                        $reportes[] = [
                            'pjj_id'          => $reporte->pjj_id,
                            'archivo_reporte' => '',
                            'fecha'           => $fechaModificacion,
                            'errores'         => $pjjJson->errors,
                            'estado'          => 'FINALIZADO',
                            'tipo_reporte'    => $tipoReporte,
                            'existe_archivo'  => false
                        ];
                    } elseif($reporte->age_estado_proceso_json != 'FINALIZADO') {
                        $reportes[] = [
                            'pjj_id'          => $reporte->pjj_id,
                            'archivo_reporte' => '',
                            'fecha'           => $fechaModificacion,
                            'errores'         => '',
                            'estado'          => 'EN PROCESO',
                            'tipo_reporte'    => $tipoReporte,
                            'existe_archivo'  => false
                        ];
                    }
                }
            });

        $length   = $length != -1 ? $length : $totalReportes;
        $reportes = array_slice($reportes, $start, $length);

        return response()->json([
            'total'     => $totalReportes,
            'filtrados' => $filtradosReportes,
            'data'      => $reportes
        ], 200);
    }

    /**
     * Descarga un reporte generado por el usuario autenticado.
     *
     * @param Request $request Parámetros de la petición
     * @return Response|JsonResponse
     */
    public function descargarReporte(Request $request) {
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
}
