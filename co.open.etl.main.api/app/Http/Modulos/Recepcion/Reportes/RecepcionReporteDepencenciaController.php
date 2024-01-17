<?php
namespace App\Http\Modulos\Recepcion\Reportes; 

use Carbon\Carbon;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use App\Http\Modulos\Documentos\BaseDocumentosController;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;

class RecepcionReporteDepencenciaController extends BaseDocumentosController {
    /**
     * Nombre del tipo de procesamiento del reporte de dependencias.
     *
     * @var string
     */    
    protected $pjjTipo = 'RDEPENDENCIAS';

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware([
            'jwt.auth', 
            'jwt.refresh'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionReporteDependencias'
        ])->only([
            'agendarReporte',
            'listarReportesDescargar',
            'descargarReporte'
        ]);
    }

    /**
     * Crea el agendamiento en el sistema para poder procesar y generar el archivo de Excel en background.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function agendarReporte(Request $request): JsonResponse {
        $user = auth()->user();
        $validador = Validator::make($request->all(), [
            'ofe_id'                 => 'required|numeric',
            'cdo_fecha_desde'        => 'required|date_format:Y-m-d',
            'cdo_fecha_hasta'        => 'required|date_format:Y-m-d',
            'pro_id'                 => 'nullable|string',
            'cdo_origen'             => 'nullable|string',
            'cdo_clasificacion'      => 'nullable|string',
            'rfa_prefijo'            => 'nullable|string',
            'cdo_consecutivo'        => 'nullable|string',
            'campo_validacion'       => 'nullable|string',
            'valor_campo_validacion' => 'nullable|string',
            'estado_validacion'      => 'nullable|string',
            'filtro_grupos_trabajo'  => 'nullable|string',
        ], [
            'ofe_id.required'               => 'El autoincremental del OFE es requerido.',
            'ofe_id.numeric'                => 'El autoincremental del OFE debe ser númerico.',
            'cdo_fecha_desde.required'      => 'La fecha desde es requerida.',
            'cdo_fecha_desde.date_format'   => 'La fecha desde debe ser una fecha válida con el formato Y-m-d.',
            'cdo_fecha_hasta.required'      => 'La fecha hasta es requerida.',
            'cdo_fecha_hasta.date_format'   => 'La fecha hasta debe ser una fecha válida con el formato Y-m-d.',
            'pro_id.string'                 => 'Los proveedores deben estar separados por coma y deben ser una cadena de texto.',
            'cdo_origen.string'             => 'El origen debe ser una cadena de texto.',
            'cdo_clasificacion.string'      => 'La clasificacion debe ser una cadena de texto.',
            'rfa_prefijo.string'            => 'El prefijo debe ser una cadena de texto.',
            'cdo_consecutivo.string'        => 'El consecutivo debe ser una cadena de texto.',
            'campo_validacion.string'       => 'El campo de validación debe ser una cadena de texto.',
            'valor_campo_validacion.string' => 'El valor del campo de validación debe ser una cadena de texto.',
            'estado_validacion.string'      => 'El valor del estado de validación ddebe ser una cadena de texto.',
            'filtro_grupos_trabajo.string'  => 'El valor del filtro de los grupos de trabajo debe ser una cadena de texto.',
        ]);

        if ($validador->fails())
            return response()->json([
                'message' => 'Errores en la petición',
                'errors'  => $validador->errors()->all()
            ], Response::HTTP_BAD_REQUEST);

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'estado'])
            ->where('ofe_id', $request->ofe_id)
            ->where('ofe_recepcion_fnc_activo', 'SI')
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Errores en la petición',
                'errors'  => ['El OFE seleccionado no existe o no está habilitado para generar el reporte de dependencias.']
            ], Response::HTTP_BAD_REQUEST);
        elseif($ofe->estado === 'INACTIVO')
            return response()->json([
                'message' => 'Errores en la petición',
                'errors'  => ['El OFE seleccionado se encuentra INACTIVO.']
            ], Response::HTTP_BAD_REQUEST);

        $agendamiento = AdoAgendamiento::create([
            'usu_id'                  => $user->usu_id,
            'age_proceso'             => 'REPORTE',
            'bdd_id'                  => $user->getBaseDatos->bdd_id,
            'age_cantidad_documentos' => 1,
            'age_prioridad'           => null,
            'usuario_creacion'        => $user->usu_id,
            'estado'                  => 'ACTIVO',
        ]);

        if($request->filled('estado_validacion'))
            $request->merge([
                'estado_validacion' => explode(",", $request->estado_validacion)
            ]);

        if($request->filled('pro_id'))
            $request->merge([
                'pro_id' => explode(",", $request->pro_id)
            ]);

        EtlProcesamientoJson::create([
            'pjj_tipo'         => $this->pjjTipo,
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
     * Retorna la lista de reportes de dependencias generados en background.
     * 
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function listarReportesDescargar(Request $request): JsonResponse {
        $user           = auth()->user();
        $reportes       = [];
        $start          = $request->filled('start')          ? $request->start          : 0;
        $length         = $request->filled('length')         ? $request->length         : 10;
        $ordenDireccion = $request->filled('ordenDireccion') ? $request->ordenDireccion : 'DESC';
        $columnaOrden   = $request->filled('columnaOrden')   ? $request->columnaOrden   : 'fecha_modificacion';

        $consulta = EtlProcesamientoJson::select(['pjj_id', 'pjj_tipo', 'pjj_json', 'age_estado_proceso_json', 'fecha_modificacion', 'usuario_creacion'])
            ->where('pjj_tipo', $this->pjjTipo)
            ->where('estado', 'ACTIVO')
            ->when(!in_array($user->usu_type, ['ADMINISTRADOR', 'MA']), function ($query) use ($user) {
                return $query->whereBetween('fecha_modificacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'])
                    ->where('usuario_creacion', $user->usu_id);
            })
            ->when($request->filled('buscar'), function ($query) use ($request) {
                return $this->buscarReportes($query, $request->buscar);
            });

        $totalReportes = $consulta->count();

        $consulta->orderBy($columnaOrden, $ordenDireccion)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function($reporte) use (&$reportes) {
                $pjjJson           = json_decode($reporte->pjj_json);
                $fechaModificacion = Carbon::createFromFormat('Y-m-d H:i:s', $reporte->fecha_modificacion)->format('Y-m-d H:i:s');
                $usuario           = User::select(['usu_id', 'usu_identificacion', 'usu_nombre'])->where('usu_id', $reporte->usuario_creacion)->first();
                if($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->archivo_reporte)) {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => $pjjJson->archivo_reporte,
                        'fecha'           => $fechaModificacion,
                        'errores'         => '',
                        'tipo_reporte'    => "Reporte Dependencias",
                        'usuario'         => $usuario,
                        'existe_archivo'  => file_exists(storage_path('etl/descargas/' . $pjjJson->archivo_reporte))
                    ];
                } elseif($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->errors)) {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => '',
                        'fecha'           => $fechaModificacion,
                        'errores'         => $pjjJson->errors,
                        'estado'          => 'FINALIZADO',
                        'tipo_reporte'    => "Reporte Dependencias",
                        'usuario'         => $usuario,
                        'existe_archivo'  => false
                    ];
                } elseif($reporte->age_estado_proceso_json != 'FINALIZADO') {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => '',
                        'fecha'           => $fechaModificacion,
                        'errores'         => '',
                        'estado'          => 'EN PROCESO',
                        'tipo_reporte'    => "Reporte Dependencias",
                        'usuario'         => $usuario,
                        'existe_archivo'  => false
                    ];
                }
            });

        return response()->json([
            'total'     => $totalReportes,
            'filtrados' => count($reportes),
            'data'      => $reportes
        ], 200);
    }

    /**
     * Descarga un reporte generado por el usuario autenticado.
     *
     * @param  Request $request Parámetros de la petición
     * @return BinaryFileResponse|JsonResponse
     */
    public function descargarReporte(Request $request) {
        $user = auth()->user();

        $proceso = EtlProcesamientoJson::find($request->pjj_id);

        if($proceso && (in_array($user->usu_type, ['ADMINISTRADOR', 'MA']) || $proceso->usuario_creacion == $user->usu_id)) {
            $pjjJson = json_decode($proceso->pjj_json);
            if(!empty($pjjJson) && isset($pjjJson->archivo_reporte) && file_exists(storage_path('etl/descargas/' . $pjjJson->archivo_reporte))) {
                $headers = [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ];
                return response()
                    ->download(storage_path('etl/descargas/'. $pjjJson->archivo_reporte, $pjjJson->archivo_reporte, $headers));
            } else {
                return $this->responseFileError(['El archivo no existe en el sistema.']);
            }
        } elseif($proceso && $proceso->usuario_creacion != $user->usu_id) {
            return $this->responseFileError(['Usted no tiene permisos para descargar el archivo solicitado']);
        } else {
            return $this->responseFileError(['No se encontró el registro asociado a la consulta.']);
        }
    }

    /**
     * Retorna la respuesta cuando la petición espera un archivo de respuesta.
     *
     * @param  array $arrErrores Errores
     * @return JsonResponse
     */
    private function responseFileError(array $arrErrores): JsonResponse {
        if (!empty($arrErrores)) {
            // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar
            // headers en la respuesta del error para poder mostrar explícitamente al usuario el error que ha ocurrido
            $headers = [
                header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                header('X-Error-Status: 422'),
                header("X-Error-Message: " . (!empty($arrErrores) ? implode(' // ' , $arrErrores) : 'Error de Validacion de Datos'))
            ];

            return response()->json([
                'message' => 'Error en la descarga',
                'errors'  => $arrErrores
            ], 422, $headers);
        }
    }

    /**
     * Filtra los reportes cuando en el request llega el parámetro buscar.
     *
     * @param  Builder $query Instancia del Eloquent Builder
     * @param  string  $texto Valor a buscar
     * @return Builder
     */
    private function buscarReportes(Builder $query, string $texto): Builder {
        return $query->where(function($query) use ($texto) {
            $query->where('fecha_modificacion', 'like', '%' . $texto . '%')
                ->orWhere('pjj_json', 'like', '%' . $texto . '%');
        });
    }

    /**
     * Genera un reporte en Excel para de acuerdo a los filtros escogidos.
     *
     * @param  Request $request Parámetros de la petición
     * @param  string $pjj_tipo Tipo de reporte a generar
     * @return array
     * @throws \Exception
     */
    public function procesarAgendamientoReporte(Request $request, string $pjj_tipo): array {
        try {
            $request->merge([
                'pjj_tipo'           => $pjj_tipo,
                'proceso_background' => true
            ]);

            $this->request = $request;

            $arrExcel = $this->generarReporteDependencias($request);

            // Renombra el archivo y lo mueve al disco de descargas ya que se crea sobre el disco local
            File::move($arrExcel['ruta'], storage_path('etl/descargas/' . $arrExcel['nombre']));
            File::delete($arrExcel['ruta']);

            return [
                'errors'  => [],
                'archivo' => $arrExcel['nombre']
            ];
        } catch (\Exception $e) {
            return [
                'errors' => [ $e->getMessage() ],
                'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
            ];
        }
    }

    /**
     * Genera el excel del reporte de dependencias.
     *
     * @param  Request $request Parámetros de la petición
     * @return array
     */
    private function generarReporteDependencias(Request $request): array {
        $documentos = $this->documentosReporteDependencia($request);

        if(empty($documentos))
            throw new \Exception("No se encontraron documentos de acuerdo a los filtros seleccionados.");

        $titulos = [
            'NIT RECEPTOR',
            'RECEPTOR',
            'NIT EMISOR',
            'EMISOR',
            'TIPO DOCUMENTO',
            'PREFIJO',
            'CONSECUTIVO',
            'FECHA DOCUMENTO',
            'EVENTO DIAN',
            'CUFE',
            'MONEDA',
            'TOTAL ANTES DE IMPUESTOS',
            'IMPUESTOS',
            'TOTAL',
            'OBSERVACIÓN',
            'ORIGEN',
            'ESTADO VALIDACION',
            'FONDOS',
            'OC SAP',
            'POSICIÓN',
            'HOJA DE ENTRADA',
            'OBSERVACIÓN VALIDACIÓN',
            'No APROBACIÓN',
            'FECHA VALIDACIÓN',
            'OBSERVACIÓN',
            'CORREOS NOTIFICADOS',
            'CODIGO_DEPENDENCIA',
            'DES_DEPENDENCIA',
            'DEPENDENCIA',
            'RESPONSABLE_DEPENDENCIA',
            'HISTORICO_ASIGNACION_DEPENDENCIAS'
        ];

        date_default_timezone_set('America/Bogota');
        $nombreArchivo = 'reporte_dependencias_' . date('YmdHis');
        $archivoExcel  = $this->toExcel($titulos, $documentos, $nombreArchivo);

        return [
            'ruta'   => $archivoExcel,
            'nombre' => $nombreArchivo . '.xlsx'
        ];
    }

    /**
     * Obtiene los documentos y data relacional necesaria para generar el reporte de dependencias.
     *
     * @param  Request $request Parámetros de la petición
     * @return array $filasDocumentos Array conteniendo la información de los documentos a incluir en el reporte
     */
    private function documentosReporteDependencia(Request $request): array {
        $filasDocumentos      = [];

        RepCabeceraDocumentoDaop::select(['rep_cabecera_documentos_daop.cdo_id', 'rep_cabecera_documentos_daop.cdo_origen', 'rep_cabecera_documentos_daop.cdo_clasificacion', 'rep_cabecera_documentos_daop.ofe_id', 'rep_cabecera_documentos_daop.pro_id', 'rep_cabecera_documentos_daop.gtr_id', 'rep_cabecera_documentos_daop.tde_id', 'rep_cabecera_documentos_daop.rfa_prefijo', 'rep_cabecera_documentos_daop.cdo_consecutivo', 'rep_cabecera_documentos_daop.cdo_fecha', 'rep_cabecera_documentos_daop.cdo_cufe', 'rep_cabecera_documentos_daop.mon_id', 'rep_cabecera_documentos_daop.cdo_valor_sin_impuestos', 'rep_cabecera_documentos_daop.cdo_impuestos', 'rep_cabecera_documentos_daop.cdo_valor_a_pagar', 'rep_cabecera_documentos_daop.cdo_observacion', 'rep_cabecera_documentos_daop.cdo_estado'])
            ->where('ofe_id', $request->ofe_id)
            ->when($request->filled('pro_id'), function($query) use($request) {
                $query->whereIn('rep_cabecera_documentos_daop.pro_id', $request->pro_id);
            })
            ->when($request->filled('cdo_origen'), function($query) use($request) {
                $query->where('rep_cabecera_documentos_daop.cdo_origen', $request->cdo_origen);
            })
            ->when($request->filled('cdo_clasificacion'), function($query) use($request) {
                $query->where('rep_cabecera_documentos_daop.cdo_clasificacion', $request->cdo_clasificacion);
            })
            ->when($request->filled('rfa_prefijo'), function($query) use($request) {
                $query->where('rep_cabecera_documentos_daop.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use($request) {
                $query->where('rep_cabecera_documentos_daop.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('estado_validacion'), function($query) use ($request) {
                $query->whereHas('getValidacionUltimo', function($query) use ($request) {
                    $query->select(['est_id', 'rep_estados_documentos_daop.cdo_id', 'est_estado', 'est_resultado'])
                        ->whereIn('est_resultado', $request->estado_validacion);
                });
            })
            ->with([
                'getTipoDocumentoElectronico:tde_id,tde_descripcion',
                'getParametrosMoneda:mon_id,mon_codigo',
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                    ]);
                },
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                        'pro_id',
                        'pro_identificacion',
                        DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                    ]);
                },
                'getGrupoTrabajo:gtr_id,gtr_codigo,gtr_nombre',
                'getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo' => function ($query) {
                    $query->select(['gtr_id', 'ofe_id'])
                        ->where('estado', 'ACTIVO');
                },
                'getConfiguracionProveedor.getProveedorGruposTrabajo' => function ($query) {
                    $query->select(['gtp_id' ,'gtr_id', 'pro_id'])
                        ->where('estado', 'ACTIVO');
                },
                'getConfiguracionProveedor.getProveedorGruposTrabajo.getGrupoTrabajo' => function ($query) {
                    $query->select(['gtr_id', 'gtr_codigo', 'gtr_nombre'])
                        ->where('estado', 'ACTIVO');
                },
                'getUltimoEstadoValidacion:est_id,rep_estados_documentos_daop.cdo_id,est_estado,est_resultado',
                'getEstadoValidacionValidado:est_id,rep_estados_documentos_daop.cdo_id,est_estado,est_resultado,est_informacion_adicional',
                'getEstadoValidacionEnProcesoPendiente:est_id,rep_estados_documentos_daop.cdo_id,est_estado,est_resultado,est_informacion_adicional',
                'getUltimoEstadoValidacionEnProcesoPendiente:est_id,rep_estados_documentos_daop.cdo_id,est_estado,est_resultado,est_informacion_adicional',
                'getEstadosAsignarDependencia:est_id,cdo_id,est_informacion_adicional',
                'getMaximoEventoDian' => function($query) {
                    $query->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO');
                }
            ])
            ->whereBetween('cdo_fecha', [$request->cdo_fecha_desde, $request->cdo_fecha_hasta])
            ->get()
            ->map(function($documento) use ($request, &$filasDocumentos) {
                $this->procesarInfoDocumento($documento, $request, $filasDocumentos);
            });

        return $filasDocumentos;
    }

    /**
     * Procesa la información de una instancia de documento para extraer la data que pasará finalmente al reporte.
     *
     * @param RepCabeceraDocumentoDaop $documento Instancia del documento
     * @param Request $request Parámetros de la petición
     * @param array $filasDocumentos Array de data que pasará al Excel
     * @return void
     */
    private function procesarInfoDocumento(RepCabeceraDocumentoDaop $documento, Request $request, array &$filasDocumentos): void {
        $continuar = true;

        if($request->filled('campo_validacion') && $request->filled('valor_campo_validacion')) {
            if(isset($documento->getUltimoEstadoValidacionEnProcesoPendiente->est_informacion_adicional)) {
                $infoAdicionalUltimoValidacionPendiente = json_decode($documento->getUltimoEstadoValidacionEnProcesoPendiente->est_informacion_adicional, true);

                if(json_last_error() === JSON_ERROR_NONE && is_array($infoAdicionalUltimoValidacionPendiente) && array_key_exists('campos_adicionales', $infoAdicionalUltimoValidacionPendiente) && !empty($infoAdicionalUltimoValidacionPendiente['campos_adicionales'])) {
                    $datoFondosUltimoValidacionPendiente = collect($infoAdicionalUltimoValidacionPendiente['campos_adicionales'])
                        ->firstWhere('campo', $request->campo_validacion);

                    if(
                        empty($datoFondosUltimoValidacionPendiente) ||
                        (!empty($datoFondosUltimoValidacionPendiente) && array_key_exists('valor', $datoFondosUltimoValidacionPendiente) && $datoFondosUltimoValidacionPendiente['valor'] != $request->valor_campo_validacion)
                    )
                        $continuar = false;
                } else {
                    $continuar = false;
                }
            } else {
                $continuar = false;
            }
        }

        if($continuar) {
            $observacion = '';
            if(!empty($documento->cdo_observacion)) {
                $observacion = json_decode($documento->cdo_observacion);
                if(json_last_error() === JSON_ERROR_NONE && is_array($observacion)) {
                    $observacion = str_replace(array("\n","\t"), array(" "," "), substr(implode(' | ', $observacion), 0, 32767));
                } else {
                    $observacion = str_replace(array("\n","\t"), array(" | "," "), substr($documento->cdo_observacion, 0, 32767));
                }
            }

            $fondos        = '';
            $ocSap         = '';
            $posicion      = '';
            $hojaEntrada   = '';
            $obsValidacion = '';
            if(isset($documento->getEstadoValidacionEnProcesoPendiente->est_informacion_adicional)) {
                $infoAdicionalValidacionPendiente = json_decode($documento->getEstadoValidacionEnProcesoPendiente->est_informacion_adicional, true);
                if(json_last_error() === JSON_ERROR_NONE && is_array($infoAdicionalValidacionPendiente) && array_key_exists('campos_adicionales', $infoAdicionalValidacionPendiente) && !empty($infoAdicionalValidacionPendiente['campos_adicionales'])) {
                    $datoFondos = collect($infoAdicionalValidacionPendiente['campos_adicionales'])
                        ->firstWhere('campo', 'fondos');

                    if(!empty($datoFondos) && array_key_exists('valor', $datoFondos))
                        $fondos = $datoFondos['valor'];
                    
                    $datoOcSap = collect($infoAdicionalValidacionPendiente['campos_adicionales'])
                        ->firstWhere('campo', 'oc_sap');

                    if(!empty($datoOcSap) && array_key_exists('valor', $datoOcSap))
                        $ocSap = $datoOcSap['valor'];
                    
                    $datoPosicion = collect($infoAdicionalValidacionPendiente['campos_adicionales'])
                        ->firstWhere('campo', 'posicion');

                    if(!empty($datoPosicion) && array_key_exists('valor', $datoPosicion))
                        $posicion = $datoPosicion['valor'];
                    
                    $datoHojaEntrada = collect($infoAdicionalValidacionPendiente['campos_adicionales'])
                        ->firstWhere('campo', 'hoja_de_entrada');

                    if(!empty($datoHojaEntrada) && array_key_exists('valor', $datoHojaEntrada))
                        $hojaEntrada = $datoHojaEntrada['valor'];
                    
                    $datoObsValidacion = collect($infoAdicionalValidacionPendiente['campos_adicionales'])
                        ->firstWhere('campo', 'observacion_validacion');

                    if(!empty($datoObsValidacion) && array_key_exists('valor', $datoObsValidacion))
                        $obsValidacion = $datoObsValidacion['valor'];
                }
            }

            $noAprobacion       = '';
            $fechaValidado      = '';
            $obsValidado        = '';
            $correosNotificados = '';
            if(isset($documento->getEstadoValidacionValidado->est_informacion_adicional)) {
                $infoAdicionalValidado = json_decode($documento->getEstadoValidacionValidado->est_informacion_adicional, true);
                if(json_last_error() === JSON_ERROR_NONE && is_array($infoAdicionalValidado) && array_key_exists('campos_adicionales', $infoAdicionalValidado) && !empty($infoAdicionalValidado['campos_adicionales'])) {
                    $datoValidado = collect($infoAdicionalValidado['campos_adicionales'])
                        ->firstWhere('campo', 'no_aprobacion');

                    if(!empty($datoValidado) && array_key_exists('valor', $datoValidado))
                        $noAprobacion = $datoValidado['valor'];

                    $datoFechaValidacion = collect($infoAdicionalValidado['campos_adicionales'])
                        ->firstWhere('campo', 'fecha_validacion');

                    if(!empty($datoFechaValidacion) && array_key_exists('valor', $datoFechaValidacion))
                        $fechaValidado = $datoFechaValidacion['valor'];

                    $datoObsValidado = collect($infoAdicionalValidado['campos_adicionales'])
                        ->firstWhere('campo', 'observacion');

                    if(!empty($datoObsValidado) && array_key_exists('valor', $datoObsValidado))
                        $obsValidado = $datoObsValidado['valor'];
                }

                if(json_last_error() === JSON_ERROR_NONE && is_array($infoAdicionalValidado) && array_key_exists('correos_notificados', $infoAdicionalValidado) && !empty($infoAdicionalValidado['correos_notificados']))
                    $correosNotificados = implode(',', $infoAdicionalValidado['correos_notificados']);
            }

            $grupoTrabajo = $this->definirDataDependencia($request, $documento);

            if(!empty($grupoTrabajo)) {
                //Tipo Documento teniendo en cuenta los documentos no electronicos
                $tipoDocumento = '';
                if ($documento->cdo_origen == 'NO-ELECTRONICO') {
                    if ($documento->cdo_clasificacion == 'FC')
                        $tipoDocumento = 'Factura de Venta';
                    elseif ($documento->cdo_clasificacion == 'NC')
                        $tipoDocumento = 'Nota Crédito';
                    elseif ($documento->cdo_clasificacion == 'ND')
                        $tipoDocumento = 'Nota Débito';
                    else
                        $tipoDocumento = '';
                } else {
                    $tipoDocumento = isset($documento->getTipoDocumentoElectronico->tde_descripcion) ? $documento->getTipoDocumentoElectronico->tde_descripcion : '';
                }

                // Información histórico de asignación a dependencias
                $historicoAsignacionesDependencias = [];
                if($documento->getEstadosAsignarDependencia) {
                    foreach($documento->getEstadosAsignarDependencia as $asignacion) {
                        if(!empty($asignacion->est_informacion_adicional)) {
                            $infoAsignacion = json_decode($asignacion->est_informacion_adicional);
                            $historicoAsignacionesDependencias[] = $infoAsignacion->usuario . ',' . $infoAsignacion->fecha_asignacion . ',' . $infoAsignacion->hora_asignacion . ',' . $infoAsignacion->grupo_trabajo_asignado . ',' . $infoAsignacion->observacion;
                        }
                    }
                }

                $filasDocumentos[] = [
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                    $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo,
                    $documento->getConfiguracionProveedor->pro_identificacion,
                    $documento->getConfiguracionProveedor->nombre_completo,
                    $tipoDocumento,
                    $documento->rfa_prefijo,
                    $documento->cdo_consecutivo,
                    $documento->cdo_fecha,
                    $documento->getMaximoEventoDian ? $documento->getMaximoEventoDian->est_estado : '',
                    $documento->cdo_cufe,
                    $documento->getParametrosMoneda->mon_codigo,
                    $documento->cdo_valor_sin_impuestos,
                    $documento->cdo_impuestos,
                    $documento->cdo_valor_a_pagar,
                    $observacion,
                    $documento->cdo_origen,
                    isset($documento->getUltimoEstadoValidacion) ? $documento->getUltimoEstadoValidacion->est_resultado : '',
                    $fondos,
                    $ocSap,
                    $posicion,
                    $hojaEntrada,
                    $obsValidacion,
                    $noAprobacion,
                    $fechaValidado,
                    $obsValidado,
                    $correosNotificados,
                    $grupoTrabajo['codigo_dependencia'],
                    $grupoTrabajo['descripcion_dependencia'],
                    $grupoTrabajo['dependencia'],
                    $grupoTrabajo['responsable_dependencia'],
                    implode('|', $historicoAsignacionesDependencias)
                ];
            }
        }
    }

    /**
     * Define la información para los campos de dependencia en el reporte de dependencias. 
     *
     * @param  Request $request Parámetros de la petición
     * @param  RepCabeceraDocumentoDaop $documento Documento electrónico a procesar
     * @return array
     */
    private function definirDataDependencia(Request $request, RepCabeceraDocumentoDaop $documento): array {
        $arrValores = [
            'codigo_dependencia'      => '',
            'descripcion_dependencia' => '',
            'dependencia'             => '',
            'responsable_dependencia' => ''
        ];

        if($documento->getGrupoTrabajo) {
            $arrValores['codigo_dependencia']      = $documento->getGrupoTrabajo->gtr_codigo;
            $arrValores['descripcion_dependencia'] = $documento->getGrupoTrabajo->gtr_nombre;
            $arrValores['dependencia']             = 'UNICA';
            $arrValores['responsable_dependencia'] = $documento->getGrupoTrabajo->gtr_codigo;
        } else {
            $gruposTrabajoProveedor = collect($documento->getConfiguracionProveedor->getProveedorGruposTrabajo)
                ->where('getGrupoTrabajo', '!=', null)
                ->values();

            if($gruposTrabajoProveedor->count() == 1) {
                $arrValores['codigo_dependencia']      = $gruposTrabajoProveedor[0]->getGrupoTrabajo->gtr_codigo;
                $arrValores['descripcion_dependencia'] = $gruposTrabajoProveedor[0]->getGrupoTrabajo->gtr_nombre;
                $arrValores['dependencia']             = 'UNICA';
                $arrValores['responsable_dependencia'] = $gruposTrabajoProveedor[0]->getGrupoTrabajo->gtr_codigo;
            } else {
                $gtrCodigos = [];
                foreach($gruposTrabajoProveedor as $grupoTrabajo) {
                    if($grupoTrabajo->getGrupoTrabajo)
                        $gtrCodigos[] = trim($grupoTrabajo->getGrupoTrabajo->gtr_codigo);
                }

                $arrValores['codigo_dependencia'] = implode(',', $gtrCodigos);
                $arrValores['dependencia']        = 'COMPARTIDA';
            }
        }

        if($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo == 'unico' && $arrValores['dependencia'] == 'UNICA')
            return $arrValores;
        elseif($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo == 'compartido' && $arrValores['dependencia'] == 'COMPARTIDA')
            return $arrValores;
        elseif(!$request->filled('filtro_grupos_trabajo'))
            return $arrValores;
        else
            return [];
    }
}
