<?php
namespace App\Http\Modulos\Emision\Reportes;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use App\Services\Emision\EtlCabeceraDocumentoService;
use App\Http\Modulos\Documentos\BaseDocumentosController;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;

class DocumentosProcesadosController extends BaseDocumentosController {
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

        $this->emisionCabeceraService = $emisionCabeceraService;

        $this->middleware([
            'VerificaMetodosRol:EmisionReporteDocumentosProcesados,DocumentosSoporteReporteDocumentosProcesados'
        ])->only([
            'agendarReporte',
            'procesarAgendamientoReporte',
            'listarReportesDescargar',
            'descargarReporte',
            'procesarAgendamientoReporteEventos'
        ]);
    }

    /**
     * Crea el agendamiento en el sistema para poder procesar y generar el archivo de Excel en background
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    function agendarReporte(Request $request) {
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
     * Genera un reporte en Excel para de acuerdo a los filtros escogidos.
     *
     * @param Request $request Petición
     * @return Response
     * @throws \Exception
     */
    public function procesarAgendamientoReporte(Request $request) {
        try {
            set_time_limit(0);
            ini_set('memory_limit','2048M');

            $titulos = [
                'NIT EMISOR',
                'EMISOR',
                'RESOLUCION FACTURACION',
                'NIT ADQUIRENTE',
                'ID PERSONALIZADO',
                'ADQUIRENTE',
                'TIPO DOCUMENTO',
                'TIPO OPERACION',
                'PREFIJO',
                'CONSECUTIVO',
                'FECHA DOCUMENTO',
                'HORA DOCUMENTO',
                'FECHA VENCIMIENTO',
                'OBSERVACIÓN',
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
                'CORREOS NOTIFICACIÓN',
                'FECHA NOTIFICACIÓN',
                'ESTADO',
                'ESTADO DIAN',
                'RESULTADO DIAN',
                'FECHA ACUSE RECIBO',
                'FECHA RECIBO DE BIEN Y/O PRESTACION DEL SERVICIO',
                'ESTADO DOCUMENTO',
                'FECHA ESTADO',
                'MOTIVO RECHAZO',
                'FECHA DE CREACIÓN',
                'ID USUARIO',
                'USUARIO',
                'ESTADO DOCUMENTO'
            ];

            if (($request->filled('cdo_clasificacion') && ($request->cdo_clasificacion == 'DS' || $request->cdo_clasificacion == 'DS_NC')) || ($request->filled('proceso') && $request->proceso == 'documento_soporte')) {
                $titulos[0] = "NIT RECEPTOR";
                $titulos[1] = "RECEPTOR";
                $titulos[3] = "NIT VENDEDOR";
                $titulos[5] = "VENDEDOR";
            }

            $columnas = [
                'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion',
                'get_configuracion_obligado_facturar_electronicamente.nombre_completo',
                'get_configuracion_resoluciones_facturacion.rfa_resolucion',
                'get_configuracion_adquirente.adq_identificacion',
                'get_configuracion_adquirente.adq_id_personalizado',
                'get_configuracion_adquirente.nombre_completo',
                'cdo_clasificacion',
                'get_tipo_operacion.top_codigo',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_vencimiento',
                [
                    'do' => function($cdo_observacion) {
                        if($cdo_observacion != '') {
                            $observacion = json_decode($cdo_observacion, true);
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
                '',
                'get_parametros_moneda.mon_codigo',
                'cdo_valor_sin_impuestos',
                'cdo_impuestos',
                [
                    'do' => function ($cdo_cargos, $extra_data) {
                        if($cdo_cargos > 0 || $extra_data['cdo_cargos_moneda_extranjera'] > 0)
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_cargos_moneda_extranjera'] : $cdo_cargos;
                        else
                            return '';
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
                        if($extra_data['get_parametros_moneda.mon_codigo'] != 'COP')
                            $descuentos = number_format($extra_data['cdo_descuentos_moneda_extranjera'] + $extra_data['cdo_retenciones_sugeridas_moneda_extranjera'], 2, '.', '');
                        else
                            $descuentos = number_format($cdo_descuentos + $extra_data['cdo_retenciones_sugeridas'], 2, '.', '');
    
                        return $descuentos > 0 ? $descuentos : '';
                    },
                    'extra_data' => [
                        'cdo_retenciones_sugeridas',
                        'cdo_retenciones_sugeridas_moneda_extranjera',
                        'cdo_descuentos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_descuentos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_redondeo, $extra_data) {
                        if($cdo_redondeo != 0 || $extra_data['cdo_redondeo_moneda_extranjera'] != 0)
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_redondeo_moneda_extranjera'] : $cdo_redondeo;
                        else
                            return '';
                    },
                    'extra_data' => [
                        'cdo_redondeo_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_redondeo',
                    'validation_type'=> 'callback'
                ],
                'cdo_total',
                [
                    'do' => function ($cdo_anticipo, $extra_data) {
                        if($cdo_anticipo > 0 || $extra_data['cdo_anticipo_moneda_extranjera'] > 0)
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_anticipo_moneda_extranjera'] : $cdo_anticipo;
                        else
                            return '';
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
                        if($cdo_retenciones > 0 || $extra_data['cdo_retenciones_moneda_extranjera'] > 0)
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_retenciones_moneda_extranjera'] : $cdo_retenciones;
                        else
                            return '';
                    },
                    'extra_data' => [
                        'cdo_retenciones_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_retenciones',
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
                '',
                '',
                'estado',
            ];

            $request->merge([
                'tipo_reporte' => 'procesados'
            ]);

            $documentos = $this->emisionCabeceraService
                ->obtenerListaDocumentosProcesados($request);

            // Array que pasa al Excel
            $arrDocumentos = $documentos->map(function ($document) use ($columnas) {
                $arr_doc = [];
                if($document instanceof \stdClass)
                    $doc = Arr::dot((array) $document);
                else
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

            // Hasta este punto se han armado las columnas conforme a información de cabecera
            // Ahora por cada elemento del array de documentos, toca crear un nuevo elemento por cada estado EDI de cada documento
            $filasItems = [];
            $countEdi   = 1;
            $estCdoId   = '';
            foreach($documentos as $indiceDocumento => $documento) {
                $filaDocumento = $arrDocumentos[$indiceDocumento];
                // Verifica cuantos estados EDI tiene el documento, esto es porque las columnas 24 a 33 solamente se escriben en el estado EDI más reciente
                $estadosEdi = $documentos->filter(function($doc, $key) use ($documento) {
                    return $doc->cdo_id == $documento->cdo_id;
                });

                if($estadosEdi->count() == 1) {
                    $this->complementarValoresColumnas($documento, $filaDocumento);
                    $filaDocumento[14] = $documento->cdo_cufe;
                    $filaDocumento[34] = Carbon::parse($documento->est_fecha_creacion)->format('Y-m-d H:i:s');
                    $filaDocumento[35] = $documento->usuario_creacion;
                    $filaDocumento[36] = $documento->usuario_creacion_nombre;
                    $filasItems[]      = $filaDocumento;
                } else {
                    $documentoEstadoEdi = (is_array($estadosEdi) && array_key_exists(0, $estadosEdi) ? $estadosEdi[0] : $estadosEdi->first());

                    // Lógica para pintar la información de las columnas 24 a 33 en el último estado EDI
                    if ($estCdoId != $documentoEstadoEdi->cdo_id) {
                        $estCdoId = $documentoEstadoEdi->cdo_id;
                        $countEdi = 1;
                    }

                    if ($estadosEdi->count() == $countEdi)
                        $this->complementarValoresColumnas($documentoEstadoEdi, $filaDocumento);
                    
                    $filaDocumento[14] = $documento->cdo_cufe;
                    $filaDocumento[34] = Carbon::parse($documento->est_fecha_creacion)->format('Y-m-d H:i:s');
                    $filaDocumento[35] = $documento->usuario_creacion;
                    $filaDocumento[36] = $documento->usuario_creacion_nombre;
                    $filasItems[]      = $filaDocumento;

                    $countEdi++;
                }
            }
            $arrDocumentos = $filasItems;

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
     * @param Collection $documento Colección con la información del documento
     * @param array $filaDocumento Fila del documento que se pasará al Excel
     * @return void
     */
    private function complementarValoresColumnas($documento, &$filaDocumento) {
        // El documento fue enviado a la DIAN?
        if(
            $documento->get_documento_aprobado ||
            $documento->get_documento_aprobado_notificacion ||
            $documento->get_documento_rechazado
        ) {
            $filaDocumento[24] = $documento->get_notificacion_finalizado ? $documento->get_notificacion_finalizado['est_correos'] : '';
            $filaDocumento[25] = $documento->get_notificacion_finalizado ? Carbon::parse($documento->get_notificacion_finalizado['fecha_modificacion'])->format('Y-m-d H:i:s') : '';
            $filaDocumento[26] = $documento->estado;
            $filaDocumento[27] = $this->definirEstadoDian(($documento instanceof \stdClass) ? Arr::dot((array) $documento) : Arr::dot($documento->toArray()), 'estado_dian');
            $filaDocumento[28] = $this->definirEstadoDian(($documento instanceof \stdClass) ? Arr::dot((array) $documento) : Arr::dot($documento->toArray()), 'resultado_dian');
            $filaDocumento[29] = $documento->cdo_fecha_acuse;
            $filaDocumento[30] = $documento->cdo_fecha_recibo_bien;
            $filaDocumento[31] = $documento->cdo_estado;
            $filaDocumento[32] = $documento->cdo_fecha_estado;
            $filaDocumento[33] = $this->obtenerMotivoRechazo(($documento instanceof \stdClass) ? Arr::dot((array) $documento) : Arr::dot($documento->toArray()), 'motivo_rechazo');
        }
    }

    /**
     * Filtra los reportes cuando en el request llega el parámetro buscar.
     *
     * @param Request $request Petición
     * @param Builder $query Instancia del Eloquent Builder
     * @return Builder
     */
    private function buscarReportes(Request $request, Builder $query) {
        return $query->where(function($query) use ($request) {
            $query->where('fecha_modificacion', 'like', '%' . $request->buscar . '%')
                ->orWhere('pjj_json', 'like', '%' . $request->buscar . '%');

                if(strpos(strtolower($request->buscar), 'proceso') !== false)
                    $query->orWhere('age_estado_proceso_json', 'like', '%proceso%')
                        ->orWhereNull('age_estado_proceso_json');
        });
    }

    /**
     * Retorna la lista de reportes que se han solicitado durante el día.
     * 
     * El listado de reportes se generará según la fecha del sistema para el usuario autenticado,
     * Si este usuario es un ADMINISTRADOR o MA se listarán todos los reportes.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function listarReportesDescargar(Request $request): JsonResponse {
        $user     = auth()->user();
        $reportes = [];
        $start          = $request->filled('start')          ? $request->start          : 0;
        $length         = $request->filled('length')         ? $request->length         : 10;
        $ordenDireccion = $request->filled('ordenDireccion') ? $request->ordenDireccion : 'DESC';
        $columnaOrden   = $request->filled('columnaOrden')   ? $request->columnaOrden   : 'fecha_modificacion';

        $consulta = EtlProcesamientoJson::select(['pjj_id', 'pjj_tipo', 'pjj_json', 'age_estado_proceso_json', 'fecha_modificacion'])
            ->where('pjj_tipo', 'REPORTEDOCPROCESADOS')
            ->whereBetween('fecha_modificacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'])
            ->where('estado', 'ACTIVO')
            ->when(!in_array($user->usu_type, ['ADMINISTRADOR', 'MA']), function ($query) use ($user) {
                return $query->where('usuario_creacion', $user->usu_id);
            })
            ->when($request->filled('buscar'), function ($query) use ($request) {
                return $this->buscarReportes($request, $query);
            })
            ->when($request->filled('proceso') && $request->proceso == 'emision', function ($query) {
                return $query->whereRaw('JSON_CONTAINS(pjj_json, \'{"proceso":"emision"}\')');
            }, function ($queryElse) {
                return $queryElse->where(function($queryWhere) {
                    $queryWhere->whereRaw('JSON_CONTAINS(pjj_json, \'{"proceso":"documento_soporte"}\')')
                        ->orWhere('pjj_json', 'not like', '%proceso%');
                });
            })
            ->when($request->filled('tipoReporte') && $request->tipoReporte == 'eventos_procesados', function ($query) {
                return $query->whereRaw('JSON_CONTAINS(pjj_json, \'{"tipo_reporte":"eventos_procesados"}\')');
            }, function ($queryElse) {
                return $queryElse->whereRaw('JSON_CONTAINS(pjj_json, \'{"tipo_reporte":"documentos_procesados"}\')');
            });

        $totalRegistros = $consulta->count();
        $length        = $length != -1 ? $length : $totalRegistros;

        $consulta->orderBy($columnaOrden, $ordenDireccion)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function($reporte) use (&$reportes) {
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
            });

        return response()->json([
            'total'     => $totalRegistros,
            'filtrados' => count($reportes),
            'data'      => $reportes
        ], 200);
    }

    /**
     * Descarga un reporte generado por el usuario autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

    /**
     * Genera un reporte en Excel para los eventos procesados.
     *
     * @param Request $request Petición
     * @return Response
     * @throws \Exception
     */
    public function procesarAgendamientoReporteEventos(Request $request) {
        try {
            set_time_limit(0);
            ini_set('memory_limit','2048M');

            $titulos = [
                'NIT EMISOR',
                'EMISOR',
                'RESOLUCION FACTURACION',
                'NIT ADQUIRENTE',
                'ID PERSONALIZADO',
                'ADQUIRENTE',
                'TIPO DOCUMENTO',
                'TIPO OPERACION',
                'PREFIJO',
                'CONSECUTIVO',
                'FECHA DOCUMENTO',
                'HORA DOCUMENTO',
                'FECHA VENCIMIENTO',
                'OBSERVACIÓN',
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
                'CORREOS NOTIFICACIÓN',
                'FECHA NOTIFICACIÓN',
                'ESTADO',
                'ESTADO DIAN',
                'RESULTADO DIAN',
                'FECHA INICIO CONSULTA EVENTOS',
                'FECHA ACUSE RECIBO',
                'FECHA RECIBO DE BIEN Y/O PRESTACION DEL SERVICIO',
                'ESTADO DOCUMENTO',
                'FECHA ESTADO',
                'MOTIVO RECHAZO',
                'FECHA DE CREACIÓN',
                'ID USUARIO',
                'USUARIO',
                'ESTADO DOCUMENTO'
            ];

            $columnas = [
                'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion',
                'get_configuracion_obligado_facturar_electronicamente.nombre_completo',
                'get_configuracion_resoluciones_facturacion.rfa_resolucion',
                'get_configuracion_adquirente.adq_identificacion',
                'get_configuracion_adquirente.adq_id_personalizado',
                'get_configuracion_adquirente.nombre_completo',
                'cdo_clasificacion',
                'get_tipo_operacion.top_codigo',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_vencimiento',
                [
                    'do' => function($cdo_observacion) {
                        if($cdo_observacion != '') {
                            $observacion = json_decode($cdo_observacion, true);
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
                'cdo_valor_sin_impuestos',
                'cdo_impuestos',
                [
                    'do' => function ($cdo_cargos, $extra_data) {
                        if($cdo_cargos > 0 || $extra_data['cdo_cargos_moneda_extranjera'] > 0)
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_cargos_moneda_extranjera'] : $cdo_cargos;
                        else
                            return '';
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
                        if($extra_data['get_parametros_moneda.mon_codigo'] != 'COP')
                            $descuentos = number_format($extra_data['cdo_descuentos_moneda_extranjera'] + $extra_data['cdo_retenciones_sugeridas_moneda_extranjera'], 2, '.', '');
                        else
                            $descuentos = number_format($cdo_descuentos + $extra_data['cdo_retenciones_sugeridas'], 2, '.', '');
    
                        return $descuentos > 0 ? $descuentos : '';
                    },
                    'extra_data' => [
                        'cdo_retenciones_sugeridas',
                        'cdo_retenciones_sugeridas_moneda_extranjera',
                        'cdo_descuentos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_descuentos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_redondeo, $extra_data) {
                        if($cdo_redondeo != 0 || $extra_data['cdo_redondeo_moneda_extranjera'] != 0)
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_redondeo_moneda_extranjera'] : $cdo_redondeo;
                        else
                            return '';
                    },
                    'extra_data' => [
                        'cdo_redondeo_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_redondeo',
                    'validation_type'=> 'callback'
                ],
                'cdo_total',
                [
                    'do' => function ($cdo_anticipo, $extra_data) {
                        if($cdo_anticipo > 0 || $extra_data['cdo_anticipo_moneda_extranjera'] > 0)
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_anticipo_moneda_extranjera'] : $cdo_anticipo;
                        else
                            return '';
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
                        if($cdo_retenciones > 0 || $extra_data['cdo_retenciones_moneda_extranjera'] > 0)
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_retenciones_moneda_extranjera'] : $cdo_retenciones;
                        else
                            return '';
                    },
                    'extra_data' => [
                        'cdo_retenciones_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_retenciones',
                    'validation_type'=> 'callback'
                ],
                '',
                '',
                '',
                '',
                '',
                'cdo_fecha_inicio_consulta_eventos',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'estado',
            ];

            $request->merge([
                'tipo_reporte' => 'eventos_procesados'
            ]);

            $documentos = $this->emisionCabeceraService
                ->obtenerListaDocumentosProcesados($request);

            // Array que pasa al Excel
            $arrDocumentos = $documentos->map(function ($document) use ($columnas) {
                $arr_doc = [];
                if($document instanceof \stdClass)
                    $doc = Arr::dot((array) $document);
                else
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

            // Hasta este punto se han armado las columnas conforme a información de cabecera
            // Ahora por cada elemento del array de documentos se pinta la información que corresponde a los estados
            $filasItems = [];
            foreach($documentos as $indiceDocumento => $documento) {
                $filaDocumento = $arrDocumentos[$indiceDocumento];

                // El documento fue enviado a la DIAN?
                if(
                    $documento->get_documento_aprobado ||
                    $documento->get_documento_aprobado_notificacion ||
                    $documento->get_documento_rechazado
                ) {
                    $filaDocumento[24] = $documento->get_notificacion_finalizado ? $documento->get_notificacion_finalizado['est_correos'] : '';
                    $filaDocumento[25] = $documento->get_notificacion_finalizado ? Carbon::parse($documento->get_notificacion_finalizado['fecha_modificacion'])->format('Y-m-d H:i:s') : '';
                    $filaDocumento[26] = $documento->estado;
                    $filaDocumento[27] = $this->definirEstadoDian(($documento instanceof \stdClass) ? Arr::dot((array) $documento) : Arr::dot($documento->toArray()), 'estado_dian');
                    $filaDocumento[28] = $this->definirEstadoDian(($documento instanceof \stdClass) ? Arr::dot((array) $documento) : Arr::dot($documento->toArray()), 'resultado_dian');
                    $filaDocumento[30] = $documento->cdo_fecha_acuse;
                    $filaDocumento[31] = $documento->cdo_fecha_recibo_bien;
                    $filaDocumento[32] = $documento->cdo_estado;
                    $filaDocumento[33] = $documento->cdo_fecha_estado;
                    $filaDocumento[34] = $this->obtenerMotivoRechazo(($documento instanceof \stdClass) ? Arr::dot((array) $documento) : Arr::dot($documento->toArray()), 'motivo_rechazo');
                }

                $filaDocumento[35] = Carbon::parse($documento->fecha_creacion)->format('Y-m-d H:i:s');
                $filaDocumento[36] = $documento->usuario_creacion;
                $filaDocumento[37] = $documento->usuario_creacion_nombre;
                $filasItems[] = $filaDocumento;
            }
            $arrDocumentos = $filasItems;

            date_default_timezone_set('America/Bogota');
            $nombreArchivo = 'eventos_procesados_' . date('YmdHis');
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
}
