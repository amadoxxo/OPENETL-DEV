<?php
namespace App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop;

use ZipArchive;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use App\Http\Models\User;
use App\Traits\MainTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\GruposTrabajoTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use openEtl\Tenant\Traits\TenantSmtp;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response as ResponseHttp;
use openEtl\Tenant\Traits\TenantRecepcionTrait;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\Utils\ExcelExports\ExcelExport;
use App\Services\Recepcion\RepCabeceraDocumentoService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Modulos\Documentos\BaseDocumentosController;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Recepcion\Documentos\DocumentosManuales;
use App\Repositories\Recepcion\RepCabeceraDocumentoRepository;
use openEtl\Tenant\Servicios\Recepcion\TenantRecepcionService;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use openEtl\Tenant\Helpers\Recepcion\TenantXmlUblExtractorHelper;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\ConceptosRechazo\ParametrosConceptoRechazo;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepDocumentosAnexosDaop\RepDocumentoAnexoDaop;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\FNC\Validacion\PryDatosParametricosValidacionService;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RepCabeceraDocumentoDaopController extends BaseDocumentosController {
    use FechaVigenciaValidations, GruposTrabajoTrait, TenantRecepcionTrait;

    /**
     * Instancia del servicio de cabecera en recepción.
     * 
     * Clase encargada de la lógica de procesamiento de data
     *
     * @var RepCabeceraDocumentoService
     */
    protected $recepcionCabeceraService;

    /**
     * Instancia del servicio de cabecera en recepción.
     * 
     * Clase encargada de la lógica de persistencia de data
     *
     * @var RepCabeceraDocumentoRepository
     */
    protected $recepcionCabeceraRepository;

    /**
     * Instancia del servicio para Datos Paramétricos Validacion de FNC.
     *
     * @var PryDatosParametricosValidacionService
     */
    protected $datosParametricosValidacionService;

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
        'rep_cabecera_documentos_daop.cdo_nombre_archivos',
        'rep_cabecera_documentos_daop.cdo_usuario_responsable',
        'rep_cabecera_documentos_daop.cdo_usuario_responsable_recibidos',
        'rep_cabecera_documentos_daop.usuario_creacion',
        'rep_cabecera_documentos_daop.estado',
        'rep_cabecera_documentos_daop.fecha_creacion'
    ];

    /**
     * Identificación del OFE para el cual se realizan transmisiones a openComex.
     *
     * @var string
     */
    protected $ofeTransmisionOpenComex = '830076778';

    /**
     * Extensiones permitidas para el cargue de registros.
     * 
     * @var array
     */
    public $arrExtensionesPermitidas = ['xlsx', 'xls'];

    /**
     * Almacena las columnas que se generan en la interfaz de Excel para los eventos DIAN.
     * 
     * @var array
     */
    public $columnasExcelEventos = [
        "NIT OFE",
        "NIT PROVEEDOR",
        "TIPO OPERACION",
        "PREFIJO",
        "CONSECUTIVO",
        "CUFE",
        "FECHA",
        "CODIGO EVENTO",
        "CONCEPTO RECLAMO",
        "OBSERVACION"
    ];

    /**
     * Constructor.
     */
    public function __construct(
        RepCabeceraDocumentoRepository $recepcionCabeceraRepository,
        RepCabeceraDocumentoService $recepcionCabeceraService,
        PryDatosParametricosValidacionService $datosParametricosValidacionService
    ) {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:RecepcionDocumentosManuales,RecepcionDocumentosRecibidos,RecepcionDocumentosRecibidosDescargar,RecepcionDocumentoNoElectronicoNuevo,RecepcionDocumentoNoElectronicoEditar,RecepcionDocumentoNoElectronicoVer'
        ])->only([
            'buscarDocumentos',
            'getListaDocumentosRecibidos',
            'descargarDocumentos',
            'agendarConsultaEstadoDian',
            'documentosManuales',
            'getListaErroresDocumentos',
            'descargarListaErroresDocumentos',
            'descargarDocumentosAnexos',
            'consultaDocumentos',
            'consultarDocumentoNoElectronico'
        ]);
        
        $this->middleware([
            'VerificaMetodosRol:RecepcionDocumentosRecibidosDescargar'
        ])->only([
            'buscarDocumentos'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionDocumentoNoElectronicoNuevo,RecepcionDocumentoNoElectronicoEditar,RecepcionDocumentoNoElectronicoVer'
        ])->only([
            'consultarDocumentoNoElectronico'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionDocumentosRecibidos'
        ])->only([
            'asignarGrupoTrabajo'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionAsociarDocumentoGrupoTrabajo'
        ])->only([
            'asignarGrupoTrabajoDocumentos'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionValidacionDocumentosValidar,RecepcionValidacionDocumentosRechazar,RecepcionValidacionDocumentosPagar,RecepcionEnviarValidacion,RecepcionDatosValidacion,RecepcionDocumentoValidado,RecepcionValidacionDocumentosAsignar,RecepcionValidacionDocumentosLiberar'
        ])->only([
            'crearEstadoValidacionAccion'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionReporteBackground,RecepcionReportesLogValidacionDocumentos'
        ])->only([
            'agendarReporte',
            'procesarAgendamientoReporte',
            'listarReportesDescargar'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionValidacionDocumentos,RecepcionValidacionDocumentosValidar,RecepcionValidacionDocumentosRechazar,RecepcionValidacionDocumentosPagar,RecepcionValidacionDocumentosAsignar,RecepcionValidacionDocumentosLiberar,RecepcionValidacionDescargarExcel'
        ])->only([
            'getListaValidacionDocumentos'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionConsultaAceptacionTacitaDocumentos'
        ])->only([
            'agendarAceptacionTacita'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionReportesLogValidacionDocumentos'
        ])->only([
            'agendarLogValidacionDocumentos',
            'generarLogValidacionDocumentos'
        ]);

        $this->recepcionCabeceraRepository        = $recepcionCabeceraRepository;
        $this->recepcionCabeceraService           = $recepcionCabeceraService;
        $this->datosParametricosValidacionService = $datosParametricosValidacionService;
    }

    /**
     * Realiza una búsqueda de documentos en recepción.
     *
     * @param  Request $request Parámetros de la petición 
     * @param  string  $campoBuscar Campo sobre el cual realizar la búsqueda
     * @param  string  $valorBuscar Valor a buscar
     * @param  string  $filtroColumnas Busqueda exacta o por similitud
     * @return JsonResponse
     * @deprecated Método obsoleto por desarrollo de la versión 2 de particonamiento, por favor usar el método autocompleteLote() de la clase ParticionamientoRecepcionController
     */
    public function buscarDocumentos(Request $request, string $campoBuscar, string $valorBuscar, string $filtroColumnas): JsonResponse {
        $select = [DB::raw('DISTINCT(cdo_lote) as cdo_lote')];
        $objDocumentos = RepCabeceraDocumentoDaop::select($select);
        switch ($filtroColumnas) {
            case 'basico':
            case 'avanzado':
                if($campoBuscar != 'rfa_prefijo') $objDocumentos = $objDocumentos->where($campoBuscar, 'like', '%' . $valorBuscar . '%');
                break;

            default:
                if($campoBuscar != 'rfa_prefijo') $objDocumentos = $objDocumentos->where($campoBuscar, $valorBuscar);
                break;
        }

        if($campoBuscar == 'rfa_prefijo' && ($valorBuscar == '' || $valorBuscar == null || $valorBuscar == 'null')) {
            $objDocumentos->where(function($query) {
                $query->whereNull('rfa_prefijo')
                    ->orWhere('rfa_prefijo', '');
            });
        } elseif($campoBuscar == 'rfa_prefijo' && ($valorBuscar != '' && $valorBuscar != null && $valorBuscar != 'null')) {
            $objDocumentos->where('rfa_prefijo', $valorBuscar);
        }

        $documentos = [];
        $objDocumentos->whereNotNull('cdo_lote')
            ->get()
            ->map(function ($item) use (&$documentos) {
                $select = [
                    DB::raw('COUNT(*) as cantidad_documentos,
                    MIN(cdo_consecutivo) as consecutivo_inicial,
                    MAX(cdo_consecutivo) as consecutivo_final')
                ];
                $objDocumentos = RepCabeceraDocumentoDaop::select($select)
                    ->where('cdo_lote', $item->cdo_lote)
                    ->first();
                    
                $documentos[] = [
                    'cdo_lote'            => $item->cdo_lote,
                    'cantidad_documentos' => $objDocumentos->cantidad_documentos,
                    'consecutivo_inicial' => $objDocumentos->consecutivo_inicial,
                    'consecutivo_final'   => $objDocumentos->consecutivo_final,
                ];
            });

        // Si la búsqueda se realiza sobre el campo cdo_lote para documentos recibidos, se debe buscar la información sobre la tabla FAT si la BD asociada al usuario esta configurada para particionamiento
        if($campoBuscar == 'cdo_lote') {
            // if(auth()->user()->baseDatosTieneParticionamientoRecepcion()) // TODO: Se comenta esta línea porque hay BD en donde se esta particionando pero estan marcadas con NO, igual la búsqueda se debe realizar
                $documentos = array_merge($documentos, $this->recepcionCabeceraRepository->busquedaFatByCampoValor($campoBuscar, $valorBuscar));
        }

        return response()->json([
            'data' => $documentos
        ], 200);
    }

    /**
     * Consulta los datos del documento y el último estado exitoso, debe solicitar como parametros el nit del OFE, el prefijo y el consecutivo del documento
     *
     * @param Request $request Parámetros de la petición
     * @param string $ofe_identificacion Indentificación del oferente
     * @param string $prefijo 
     * @param string $consecutivo
     * @return JsonResponse
     */
    public function consultarDocumento(Request $request, string $ofe_identificacion, string $prefijo, string $consecutivo) {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (is_null($ofe)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No existe el OFE {$ofe_identificacion}"]
            ], 404);
        }

        $documento = RepCabeceraDocumentoDaop::select(
            [
                'cdo_id',
                'pro_id',
                'cdo_clasificacion',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_cufe',
                'cdo_qr',
                'cdo_signaturevalue',
                'cdo_fecha_validacion_dian',
                'cdo_fecha_acuse',
                'cdo_estado',
                'cdo_fecha_estado',
                'estado'
            ])
            ->with(['getConfiguracionProveedor:pro_id,pro_identificacion'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('cdo_consecutivo', $consecutivo);

        if($prefijo != '' && $prefijo != 'null' && $prefijo != null)
            $documento = $documento->where('rfa_prefijo', trim($prefijo));
        else
            $documento = $documento->where(function($query) {
                $query->whereNull('rfa_prefijo')
                    ->orWhere('rfa_prefijo', '');
            });
            
        $documento = $documento->first();

        if (is_null($documento)) {
            // Si el documento no existe en DAOP se debe verificar si la BD del usuario está configurada para particionamiento y poder realizar una consulta a la tabla FAT e histórico
            if(auth()->user()->baseDatosTieneParticionamientoRecepcion())
                $documento = $this->recepcionCabeceraService->consultarDocumento($ofe->ofe_id, 0, $prefijo, $consecutivo, 0, []);
            else
                $documento = null;

            if (is_null($documento))
                return response()->json([
                    'message' => 'Error en documento',
                    'errors' => ["El Documento ".(($prefijo === null) ? '' : $prefijo)."{$consecutivo} para el OFE {$ofe_identificacion} no existe"]
                ], 404);
            else {
                // Modifica el objeto del documento obtenido desde la partición para igualarlo con el retorno esperado para la consulta
                $proveedor = [
                    'pro_id' => $documento->getConfiguracionProveedor->pro_id,
                    'pdo_identificacion' => $documento->getConfiguracionProveedor->pro_identificacion
                ]; 
                
                unset(
                    $documento->rfa_resolucion,
                    $documento->cdo_nombre_archivos,
                    $documento->fecha_creacion,
                    $documento->getConfiguracionObligadoFacturarElectronicamente,
                    $documento->getConfiguracionProveedor,
                    $documento->getParametrosMoneda,
                    $documento->getParametrosMonedaExtranjera,
                    $documento->getTipoOperacion,
                    $documento->getTipoDocumentoElectronico
                );

                $documento = $documento->toArray();
                $documento['get_configuracion_proveedor'] = $proveedor;
            }
        }

        return response()->json([
            'data' =>  $documento
        ], 200);
    }

    /**
     * Retorna los datos del documento organizados, el último estado exitoso y el histórico de estados
     *
     * @param  object $documento Colección con información del documento
     * @return array
     */
    private function datosDocumento($documento) {
        foreach ($documento->getEstadosDocumentosDaop as $estado) {
            $xmlUbl  = '';
            $archivo = '';
            if(!empty($estado->est_informacion_adicional)) {
                $informacionAdicionalEstado = json_decode($estado->est_informacion_adicional, true);

                $xmlUbl = base64_encode(MainTrait::obtenerArchivoDeDisco(
                    'recepcion',
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                    $documento,
                    array_key_exists('est_xml', $informacionAdicionalEstado) ? $informacionAdicionalEstado['est_xml'] : ''
                ));
                $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

                $archivo = base64_encode(MainTrait::obtenerArchivoDeDisco(
                    'recepcion',
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                    $documento,
                    array_key_exists('est_archivo', $informacionAdicionalEstado) ? $informacionAdicionalEstado['est_archivo'] : ''
                ));
                $archivo = $this->eliminarCaracteresBOM($archivo);
            }
        
            $historicoEstados[] = [
                'estado'            => $estado->est_estado,
                'resultado'         => $estado->est_resultado,
                'mensaje_resultado' => $estado->est_mensaje_resultado,
                'archivo'           => $archivo,
                'xml'               => $xmlUbl,
                'fecha'             => $estado->est_inicio_proceso
            ];
        }

        $xmlUbl  = '';
        $archivo = '';
        if(!empty($documento->getUltimoEstadoDocumento->est_informacion_adicional)) {
            $informacionAdicionalEstado = json_decode($documento->getUltimoEstadoDocumento->est_informacion_adicional, true);

            $xmlUbl = base64_encode(MainTrait::obtenerArchivoDeDisco(
                'recepcion',
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                $documento,
                array_key_exists('est_xml', $informacionAdicionalEstado) ? $informacionAdicionalEstado['est_xml'] : ''
            ));
            $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

            $archivo = base64_encode(MainTrait::obtenerArchivoDeDisco(
                'recepcion',
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                $documento,
                array_key_exists('est_archivo', $informacionAdicionalEstado) ? $informacionAdicionalEstado['est_archivo'] : ''
            ));
            $archivo = $this->eliminarCaracteresBOM($archivo);
        }

        $respuesta = [
            'id'                 => $documento->cdo_id,
            'ofe_identificacion' => $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            'pro_identificacion' => $documento->getConfiguracionProveedor->pro_identificacion,
            'cdo_clasificacion'  => $documento->cdo_clasificacion,
            'resolucion'         => $documento->rfa_resolucion,
            'prefijo'            => trim($documento->rfa_prefijo),
            'consecutivo'        => $documento->cdo_consecutivo,
            'fecha_documento'    => $documento->cdo_fecha,
            'hora_documento'     => $documento->cdo_hora,
            'estado'             => $documento->estado,
            'cufe'               => $documento->cdo_cufe,
            'qr'                 => $documento->cdo_qr,
            'signaturevalue'     => $documento->cdo_signaturevalue,
            'ultimo_estado'      => !empty($documento->getUltimoEstadoDocumento) ? [
                'estado'            => $documento->getUltimoEstadoDocumento->est_estado,
                'resultado'         => $documento->getUltimoEstadoDocumento->est_resultado,
                'mensaje_resultado' => $documento->getUltimoEstadoDocumento->est_mensaje_resultado,
                'archivo'           => $archivo,
                'xml'               => $xmlUbl,
                'fecha'             => $documento->getUltimoEstadoDocumento->est_inicio_proceso
            ] : '',
            'historico_estados'  => $historicoEstados,
        ];

        return $respuesta;
    }

    /**
     * Consulta información de un documento específico mediante método POST, la consulta incluye el último estado exitoso y el histórico de estados del documento.
     * 
     * Este método es diferente de consultarDocumento en cuanto a que dicho método es llamado de un endpoint GET y recibe query-params
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function consultaDocumentos(Request $request) {
        if(isset($request->cufe) && !empty($request->cufe)) {
            $rulesRequest = [
                'cufe'        => 'required|string|max:255',
                'fecha'       => 'required|date_format:Y-m-d'
            ];
        } else {
            $rulesRequest = [
                'ofe'         => 'required|string',
                'proveedor'   => 'required|string',
                'tipo'        => 'required|string|max:2',
                'prefijo'     => 'nullable|string|max:5',
                'consecutivo' => 'required|string|max:20',
                'fecha'       => 'required|date_format:Y-m-d'
            ];
        }

        $validador = Validator::make($request->all(), $rulesRequest);
        if ($validador->fails()) {
            return response()->json([
                'message' => 'Errores en la petición',
                'errors'  => $validador->errors()->all()
            ], 400);
        }

        // Consultado por documento
        if(!(isset($request->cufe) && !empty($request->cufe))) {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion'])
                ->where('ofe_identificacion', $request->ofe)
                ->validarAsociacionBaseDatos()
                ->first();

            if (!$ofe) {
                return response()->json([
                    'message' => 'Error en la petición',
                    'errors' => ["No existe el OFE [{$request->ofe}]"]
                ], 404);
            }

            $proveedor = ConfiguracionProveedor::select(['pro_id','pro_identificacion'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_identificacion', $request->proveedor)
                ->first();

            if (!$proveedor) {
                return response()->json([
                    'message' => 'Error en la petición',
                    'errors' => ["No existe el Proveedor [{$request->proveedor}] para el OFE [{$request->ofe}]"]
                ], 404);
            }

            $tdeID = null;
            ParametrosTipoDocumentoElectronico::select(['tde_id', 'tde_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('tde_codigo', $request->tipo)
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('tde_codigo')
                ->map(function($registro) use (&$tdeID) {
                    $valida = $this->validarVigenciaRegistroParametrica($registro);
                    if($valida['vigente'])
                        $tdeID = $valida['registro']->tde_id;
                });

            if (!$tdeID) {
                return response()->json([
                    'message' => 'Error en la petición',
                    'errors' => ["No existe el tipo de documento electrónico [{$request->tipo}] o no se encuentra vigente"]
                ], 404);
            }
        }

        $documentos = RepCabeceraDocumentoDaop::select(
            [
                'cdo_id',
                'ofe_id',
                'pro_id',
                'cdo_clasificacion',
                'rfa_resolucion',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_cufe',
                'cdo_qr',
                'cdo_signaturevalue',
                'cdo_fecha_validacion_dian',
                'cdo_fecha_acuse',
                'cdo_estado',
                'cdo_fecha_estado',
                'estado',
                'fecha_creacion'
            ])
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                'getConfiguracionProveedor:pro_id,pro_identificacion',
                'getUltimoEstadoDocumento:est_id,cdo_id,est_estado,est_resultado,est_mensaje_resultado,est_correos,est_inicio_proceso,est_informacion_adicional,fecha_creacion',
                'getEstadosDocumentosDaop:est_id,cdo_id,est_estado,est_resultado,est_mensaje_resultado,est_correos,est_inicio_proceso,est_informacion_adicional,fecha_creacion'
            ]);

        // Busca por cufe
        if($request->filled('cufe')) {
            $documentos = $documentos->where('cdo_cufe', $request->cufe);
        } else {
            // Busca por documento
            $documentos = $documentos->where('tde_id', $tdeID)
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_id', $proveedor->pro_id);

            if($request->prefijo != '' && $request->prefijo != 'null' && $request->prefijo != null)
                $documentos = $documentos->where('rfa_prefijo', trim($request->prefijo))
                    ->where('cdo_consecutivo', $request->consecutivo);
            else
                $documentos = $documentos->where(function($query) {
                    $query->whereNull('rfa_prefijo')
                        ->orWhere('rfa_prefijo', '');
                })
                ->where('cdo_consecutivo', $request->consecutivo);
        }

        $documentos = $documentos->where('cdo_fecha', $request->fecha)
            ->get();

        $respuesta = [];
        if ($documentos->isEmpty()) {
            // Realiza la consulta sobre la data particionada
            if(!$request->filled('cufe'))
                $documento = $this->recepcionCabeceraService->consultaDocumentos($request, $ofe, $proveedor, $tdeID);
            else
                $documento = $this->recepcionCabeceraService->consultaDocumentos($request, null, null, 0);

            if (!$documento) {
                if($request->filled('cufe')) {
                    return response()->json([
                        'message' => 'Error en documento',
                        'errors' => ["El CUFE [{$request->cufe}] No Existe"]
                    ], 404);
                } else {
                    return response()->json([
                        'message' => 'Error en documento',
                        'errors' => ["No se encontraron documentos [".(($request->prefijo === null) ? '' : $request->prefijo)."{$request->consecutivo}] para el OFE [{$request->ofe}]"]
                    ], 404);
                }
            }

            $respuesta[] = $this->datosDocumento($documento);
        } else {
            $documentos->map(function($documento) use (&$respuesta) {
                $respuesta[] = $this->datosDocumento($documento);
            });
        }

        return response()->json([
            'data' =>  $respuesta
        ], 200);
    }

    /**
     * Permite filtrar los documentos electrónicos teniendo en cuenta la configuración de grupos de trabajo a nivel de usuario autenticado y proveedores.
     * 
     * Si el usuario autenticado esta configurado en algún grupo de trabajo, solamente se deben listar documentos electrónicos de los proveedores asociados con ese mismo grupo o grupos de trabajo
     * Si el usuario autenticado no esta configurado en ningún grupo de trabajo, se verifica ssi el usuario está relacionado directamente con algún proveedor para mostrar solamente documentos de esos proveedores
     * Si no se da ninguna de las anteriores condiciones, el usuario autenticado debe poder ver todos los documentos electrónicos de todos los proveedores
     *
     * @param Builder $query Consulta que está en procesamiento
     * @param int $ofeId ID del OFE para el cual se está haciendo la consulta
     * @param bool $usuarioGestor Indica cuando se debe tener en cuenta que se trate de un usuario gestor
     * @param bool $usuarioValidador Indica cuando se debe tener en cuenta que se trate de un usuario validador
     * @return Builder
     */
    public function verificaRelacionUsuarioProveedor($query, int $ofeId, bool $usuarioGestor = false, bool $usuarioValidador = false) {
        $user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select('ofe_recepcion_fnc_activo')
            ->where('ofe_id', $ofeId)
            ->first();

        $gruposTrabajoUsuario = $this->getGruposTrabajoUsuarioAutenticado($ofe, $usuarioGestor, $usuarioValidador);

        if(!empty($gruposTrabajoUsuario)) {
            $query->whereHas('getProveedorGruposTrabajo', function($gtrProveedor) use ($gruposTrabajoUsuario) {
                $gtrProveedor->whereIn('gtr_id', $gruposTrabajoUsuario)
                    ->where('estado', 'ACTIVO');
            });
        } else {
            // Verifica si el usuario autenticado esta asociado con uno o varios proveedores para mostrar solo los documentos de ellos, de lo contrario mostrar los documentos de todos los proveedores en la BD
            $consultaProveedoresUsuario = ConfiguracionProveedor::select(['pro_id'])
                ->where('ofe_id', $ofeId)
                ->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%')
                ->where('estado', 'ACTIVO')
                ->get();
                
            if($consultaProveedoresUsuario->count() > 0)
                $query->where('ofe_id', $ofeId)
                    ->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%');
        }

        return $query;
    }

    /**
     * Retorna una lista de documentos recibidos según la búsqueda.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response|BinaryFileResponse|Collection
     * @throws \Exception
     * @deprecated Método obsoleto por desarrollo de la versión 2 de particionamiento, por favor usar el método getListaDocumentosRecibidos() de la clase ParticionamientoRecepcionController
     */
    public function getListaDocumentosRecibidos(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit','2048M');

        $user = auth()->user();

        $required_where_conditions = [];
        define('SEARCH_METHOD', 'busquedaGeneral');

        $optional_where_conditions = [
            'rep_cabecera_documentos_daop.pro_id',
            'rep_cabecera_documentos_daop.cdo_origen',
            'rep_cabecera_documentos_daop.cdo_clasificacion',
            'rep_cabecera_documentos_daop.cdo_lote',
            'rep_cabecera_documentos_daop.rfa_prefijo',
            'rep_cabecera_documentos_daop.cdo_consecutivo',
        ];

        $required_where_conditions['rep_cabecera_documentos_daop.ofe_id'] = $request->ofe_id;

        if (isset($request->cdo_consecutivo) && $request->cdo_consecutivo !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_consecutivo'] = $request->cdo_consecutivo;
        }

        if (isset($request->rfa_prefijo) && $request->rfa_prefijo !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.rfa_prefijo'] = $request->rfa_prefijo;
        }

        if (isset($request->cdo_clasificacion) && $request->cdo_clasificacion !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_clasificacion'] = $request->cdo_clasificacion;
        }

        if (isset($request->cdo_lote) && $request->cdo_lote !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_lote'] = $request->cdo_lote;
        }

        if (isset($request->cdo_origen) && $request->cdo_origen !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_origen'] = $request->cdo_origen;
        }

        if (isset($request->cdo_usuario_responsable_recibidos) && $request->cdo_usuario_responsable_recibidos !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_usuario_responsable_recibidos'] = $request->cdo_usuario_responsable_recibidos;
        }

        if (isset($request->estado) && $request->estado !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.estado'] = $request->estado;
        }

        $special_where_conditions = [];

        if($request->has('pro_id') && $request->pro_id !== '' && !empty($request->pro_id)) {
            $special_where_conditions[] = [
                'type'  => 'in',
                'field' => 'rep_cabecera_documentos_daop.pro_id',
                'value' => $request->pro_id
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

        if ($request->has('ofe_filtro') && !empty($request->ofe_filtro) && $request->has('ofe_filtro_buscar') && !empty($request->ofe_filtro_buscar)) {
            //Filtros a nivel de cabecera
            switch($request->ofe_filtro){
                default:
                    $array_filtros = [
                        'type' => 'join',
                        'table' => 'rep_datos_adicionales_documentos_daop',
                        'on_conditions'  => [
                            [
                                'from' => 'rep_cabecera_documentos_daop.cdo_id',
                                'operator' => '=',
                                'to' => 'rep_datos_adicionales_documentos_daop.cdo_id',
                            ]
                        ],
                    ];
        
                    $array_filtros['where_conditions'][] = [
                        'from' => DB::raw("JSON_EXTRACT(rep_datos_adicionales_documentos_daop.cdo_informacion_adicional , '$.{$request->ofe_filtro}')"),
                        'operator' => '=',
                        'to' => $request->ofe_filtro_buscar,
                    ];
        
                    array_push($special_where_conditions, $array_filtros);
                break;
            }
        }

        if (!empty($request->cdo_fecha_desde) && !empty($request->cdo_fecha_hasta)){
            array_push($special_where_conditions, [
                'type'   => 'IfBetween',
                'field1' => 'rep_cabecera_documentos_daop.cdo_fecha',
                'field2' => 'rep_cabecera_documentos_daop.cdo_fecha',
                'field3' => 'rep_cabecera_documentos_daop.cdo_hora',
                'value'  => [$request->cdo_fecha_desde . " 00:00:00", $request->cdo_fecha_hasta . " 23:59:59"]
            ]);
        }

        if (!empty($request->cdo_fecha_validacion_dian_desde) && !empty($request->cdo_fecha_validacion_dian_hasta)){
            array_push($special_where_conditions, [
                'type'   => 'IfBetween',
                'field1' => 'rep_cabecera_documentos_daop.cdo_fecha_validacion_dian',
                'field2' => 'rep_cabecera_documentos_daop.cdo_fecha_validacion_dian',
                'field3' => 'rep_cabecera_documentos_daop.cdo_hora',
                'value'  => [$request->cdo_fecha_validacion_dian_desde . " 00:00:00", $request->cdo_fecha_validacion_dian_hasta . " 23:59:59"]
            ]);
        }

        TenantTrait::GetVariablesSistemaTenant();

        // Verifica si el usuario autenticado esta asociado con uno o varios proveedores para mostrar solo los documentos de ellos, de lo contrario mostrar los documentos de todos los proveedores en la BD
        $arrRelaciones = [
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,ofe_recepcion_fnc_activo',
            'getConfiguracionProveedor' => function($query) use ($request) {
                $query = $this->recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $request->ofe_id, true, false, false);

                if(filled($request->transmision_opencomex)) {
                    $nitsIntegracionOpenComex = explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'));
                    $query->whereIn('pro_identificacion', $nitsIntegracionOpenComex);
                }
            },
            'getConfiguracionProveedor.getProveedorGruposTrabajo' => function($query) {
                $query->select(['pro_id', 'gtr_id'])
                    ->where('estado', 'ACTIVO')
                    ->with([
                        'getGrupoTrabajo' => function($query) {
                            $query->select('gtr_id', 'gtr_codigo', 'gtr_nombre')
                                ->where('estado', 'ACTIVO');
                        }
                    ]);
            },
            'getParametrosMoneda:mon_id,mon_codigo,mon_descripcion',
            'getTipoDocumentoElectronico:tde_id,tde_codigo,tde_descripcion',
            'getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo:gtr_id,ofe_id',
            'getGrupoTrabajo:gtr_id,gtr_codigo,gtr_nombre',
            'getDocumentoAprobado:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getDocumentoAprobadoNotificacion:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getStatusEnProceso:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getDocumentoRechazado:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getEstadoRdiInconsistencia:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getAceptado:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getAceptadoT:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getRechazado:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getAceptadoFallido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getAceptadoTFallido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getRechazadoFallido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getTransmisionErpExitoso:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getTransmisionErpExcluido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getTransmisionErpFallido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getNotificacionAcuseRecibo:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getNotificacionReciboBien:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getNotificacionAceptacion:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getNotificacionRechazo:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getNotificacionAcuseReciboFallido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getNotificacionReciboBienFallido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getNotificacionAceptacionFallido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getNotificacionRechazoFallido:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getUltimoEstadoDocumento:est_id,cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getValidacionUltimo',
            'getEstadoValidacionValidado',
            'getEstadoValidacionEnProcesoPendiente',
            'getUltimoEstadoValidacionEnProcesoPendiente',
            'getDocumentosAnexos:dan_id,cdo_id,dan_nombre,dan_descripcion,dan_tamano',
            'getUsuarioResponsableRecibidos:usu_id,usu_identificacion,usu_nombre',
        ];

        $where_has_conditions   = [];
        if ($request->has('estado_dian') && !empty($request->estado_dian)) {
            foreach($request->estado_dian as $estadoDian) {
                switch($estadoDian) {
                    case 'aprobado':
                        $where_has_conditions[] = [
                            'relation' => 'getEstadoDianUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'EXITOSO')
                                    ->where(function($query) {
                                        $query->where('est_informacion_adicional', 'not like', '%conNotificacion%')
                                            ->orWhere('est_informacion_adicional->conNotificacion', 'false');
                                    });
                            }
                        ];
                        break;
                    case 'aprobado_con_notificacion':
                        $where_has_conditions[] = [
                            'relation' => 'getEstadoDianUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'EXITOSO')
                                    ->where('est_informacion_adicional->conNotificacion', 'true');
                            }
                        ];
                        break;
                    case 'rechazado':
                        $where_has_conditions[] = [
                            'relation' => 'getEstadoDianUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'FALLIDO')
                                    ->where('est_informacion_adicional->conNotificacion', 'true');
                            }
                        ];
                        break;
                    case 'en_proceso':
                        $where_has_conditions[] = [
                            'relation' => 'getEstadoDianUltimo',
                            'function' => function($query) {
                                $query->where('est_ejecucion', 'ENPROCESO');
                            }
                        ];
                        break;
                }
            }
        }

        $where_doesnthave_conditions = '';
        if ($request->has('estado_acuse_recibo') && !empty($request->estado_acuse_recibo)) {
            if($request->estado_acuse_recibo == 'SI')
                $where_has_conditions[] = ['relation' => 'getAcuseRecibo'];
            else 
                $where_doesnthave_conditions .= 'getAcuseRecibo';
        }

        if ($request->has('estado_recibo_bien') && !empty($request->estado_recibo_bien)) {
            if($request->estado_recibo_bien == 'SI')
                $where_has_conditions[] = ['relation' => 'getReciboBien'];
            else {
                $where_doesnthave_conditions .= (empty($where_doesnthave_conditions)) ? 'getReciboBien' : ',getReciboBien';
            }
        }

        if ($request->filled('forma_pago')) {
            $arrIdsFormaPago = [];
            ParametrosFormaPago::select('fpa_id')
                ->where('fpa_codigo', $request->forma_pago)
                ->where('fpa_aplica_para', 'LIKE', '%' . 'DE' . '%')
                ->where('estado', 'ACTIVO')
                ->get()
                ->map(function($item) use (&$arrIdsFormaPago){
                    $arrIdsFormaPago[] = $item['fpa_id'];
                });
            
            $where_has_conditions[] = [
                'relation' => 'getMediosPagoDocumentosDaop',
                'function' => function($query) use ($arrIdsFormaPago){
                    $query->whereIn('fpa_id', $arrIdsFormaPago);
                }
            ];
        }

        if ($request->has('estado_eventos_dian') && !empty($request->estado_eventos_dian)) {
            foreach($request->estado_eventos_dian as $estadoDocumento) {
                switch($estadoDocumento) {
                    case 'sin_estado':
                        $where_doesnthave_conditions .= (empty($where_doesnthave_conditions)) ? 'getAceptadoExitosoFallido,getAcuseReciboExitosoFallido,getReciboBienExitosoFallido,getAceptadoTExitosoFallido,getRechazadoExitosoFallido' : ',getAceptadoExitosoFallido,getAcuseReciboExitosoFallido,getReciboBienExitosoFallido,getAceptadoTExitosoFallido,getRechazadoExitosoFallido';
                        break;
                    case 'aceptado_tacitamente':
                        if (!$request->filled('resEventosDian')) {
                            $where_has_conditions[] = [
                                'relation' => 'getMaximoEstadoDocumento',
                                'function' => function($query) {
                                    $query->where('est_estado', 'ACEPTACIONT')
                                        ->orWhere('est_estado', 'UBLACEPTACIONT');
                                }
                            ];
                        } 
                        break;
                    case 'aceptacion_expresa':
                        if (!$request->filled('resEventosDian')) {
                            $where_has_conditions[] = [
                                'relation' => 'getMaximoEstadoDocumento',
                                'function' => function($query) {
                                    $query->where('est_estado', 'ACEPTACION')
                                        ->orWhere('est_estado', 'UBLACEPTACION');
                                }
                            ];
                        } 
                        break;
                    case 'reclamo_rechazo':
                        if (!$request->filled('resEventosDian')) {
                            $where_has_conditions[] = [
                                'relation' => 'getMaximoEstadoDocumento',
                                'function' => function($query) {
                                    $query->where('est_estado', 'RECHAZO')
                                        ->orWhere('est_estado', 'UBLRECHAZO');
                                }
                            ];
                        }
                        break;
                }
            }
        }

        if ($request->has('estado_validacion') && !empty($request->estado_validacion)) {
            foreach($request->estado_validacion as $estadoValidacion) {
                switch($estadoValidacion) {
                    case 'sin_gestion':
                        if(!in_array('sin_gestion_rechazado', $request->estado_validacion))
                            $where_doesnthave_conditions .= (empty($where_doesnthave_conditions)) ? 'getEstadosValidacion' : ',getEstadosValidacion';
                        break;
                    case 'sin_gestion_rechazado':
                        $rawQuery = "(
                            EXISTS
                            (
                                SELECT est_id
                                FROM `rep_estados_documentos_daop`
                                INNER JOIN
                                    (SELECT max(`rep_estados_documentos_daop`.`est_id`) AS `est_id_aggregate`,
                                            `rep_estados_documentos_daop`.`cdo_id`
                                    FROM `rep_estados_documentos_daop`
                                    WHERE `est_estado` = 'VALIDACION'
                                    GROUP BY `rep_estados_documentos_daop`.`cdo_id`) AS `getValidacionUltimo` ON `getValidacionUltimo`.`est_id_aggregate` = `rep_estados_documentos_daop`.`est_id`
                                AND `getValidacionUltimo`.`cdo_id` = `rep_estados_documentos_daop`.`cdo_id`
                                WHERE `rep_cabecera_documentos_daop`.`cdo_id` = `rep_estados_documentos_daop`.`cdo_id`
                                    AND `est_resultado` = 'RECHDIAN'
                            ) OR NOT EXISTS (
                                SELECT est_id
                                FROM `rep_estados_documentos_daop`
                                WHERE `rep_cabecera_documentos_daop`.`cdo_id` = `rep_estados_documentos_daop`.`cdo_id`
                                    AND `est_estado` = 'VALIDACION'
                                    AND (`est_resultado` = 'PENDIENTE'
                                        OR `est_resultado` = 'ENPROCESO'
                                        OR `est_resultado` = 'VALIDADO'
                                        OR `est_resultado` = 'PAGADO'
                                        OR `est_resultado` = 'RECHAZADO'
                                        OR `est_resultado` = 'RECHDIAN')
                            )
                        )";
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'query'    => $rawQuery
                        ];
                        break;
                    case 'pendiente':
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'PENDIENTE');
                            }
                        ];
                        break;
                    case 'validado':
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'VALIDADO');
                            }
                        ];
                        break;
                    case 'rechazado':
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'RECHAZADO');
                            }
                        ];
                        break;
                    case 'pagado':
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'PAGADO');
                            }
                        ];
                        break;
                }
            }
        }

        if ($request->has('transmision_erp') && !empty($request->transmision_erp)) {
            foreach($request->transmision_erp as $transmisionErp) {
                switch($transmisionErp) {
                    case 'sin_estado':
                        $where_doesnthave_conditions .= (empty($where_doesnthave_conditions)) ? 'getTransmisionErp' : ',getTransmisionErp';
                        break;
                    case 'exitoso':
                        $where_has_conditions[] = [
                            'relation' => 'getTransmisionErp',
                            'function' => function($query) {
                                $query->where('est_resultado', 'EXITOSO')
                                    ->latest()
                                    ->limit(1);
                            }
                        ];
                        break;
                    case 'fallido':
                        $where_has_conditions[] = [
                            'relation' => 'getTransmisionErp',
                            'function' => function($query) {
                                $query->where('est_resultado', 'FALLIDO')
                                    ->latest()
                                    ->limit(1);
                            }
                        ];
                        break;
                }
            }
        }

        if ($request->has('transmision_opencomex') && !empty($request->transmision_opencomex)) {
            switch($request->transmision_opencomex) {
                case 'sin_estado':
                    $where_doesnthave_conditions .= (empty($where_doesnthave_conditions)) ? 'getOpencomexCxp' : ',getOpencomexCxp';
                    break;
                case 'exitoso':
                    $where_has_conditions[] = [
                        'relation' => 'getOpencomexCxp',
                        'function' => function($query) {
                            $query->where('est_resultado', 'EXITOSO')
                                ->latest()
                                ->limit(1);
                        }
                    ];
                    break;
                case 'fallido':
                    $where_has_conditions[] = [
                        'relation' => 'getOpencomexCxp',
                        'function' => function($query) {
                            $query->where('est_resultado', 'FALLIDO')
                                ->latest()
                                ->limit(1);
                        }
                    ];
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
        } else {
            $where_has_conditions[] = [
                'relation' => 'getConfiguracionObligadoFacturarElectronicamente',
                'function' => function($query) {
                    $query->whereNull('bdd_id_rg');
                }
            ];
        }

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo', 'ofe_recepcion_fnc_configuracion'])
            ->where('ofe_id', $request->ofe_id)
            ->with(['getGruposTrabajo:gtr_id,ofe_id'])
            ->first();

        if ($ofe->ofe_recepcion_fnc_activo == 'SI' && $request->filled('campo_validacion') && $request->filled('valor_campo_validacion')) {
            $where_has_conditions[] = [
                'relation' => 'getUltimoEstadoValidacionEnProcesoPendiente',
                'function' => function($query) use ($request) {
                    $query->whereJsonContains('est_informacion_adicional->campos_adicionales', ['campo' => $request->campo_validacion, 'valor' => $request->valor_campo_validacion]);
                }
            ];
        }

        //PRINT EXCEL CONDITION.
        if ($request->has('excel') && !empty($request->excel)){
            array_push($arrRelaciones, 
                'getTipoOperacion:top_id,top_codigo,top_descripcion',
                'getEstadoRdiExitoso:est_id,cdo_id,est_informacion_adicional,est_estado,est_resultado',
                'getTransmisionErp:est_id,cdo_id,est_resultado,est_mensaje_resultado,est_inicio_proceso',
                'getDocumentoAprobado:est_id,cdo_id,est_resultado,est_mensaje_resultado',
                'getDocumentoAprobadoNotificacion:est_id,cdo_id,est_resultado,est_mensaje_resultado',
                'getDocumentoRechazado:est_id,cdo_id,est_resultado,est_mensaje_resultado,est_motivo_rechazo',
                'getRechazado:est_id,cdo_id,est_resultado,est_mensaje_resultado,est_motivo_rechazo',
                'getUltimoEstadoValidacionEnProcesoPendiente',
                'getReciboBienUltimo:est_id,cdo_id,est_informacion_adicional'
            );

            if($ofe->ofe_recepcion_fnc_activo == 'SI')
                array_push($arrRelaciones, 
                    'getUltimoEstadoValidacion:est_id,cdo_id,est_estado,est_resultado'
                );

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
                'FECHA CARGUE',
                'INCONSISTENCIAS XML-UBL',
                'FECHA VALIDACION DIAN',
                'ESTADO DIAN',
                'RESULTADO DIAN',
                'FECHA ACUSE DE RECIBO',
                'FECHA RECIBO DE BIEN Y/O PRESTACION SERVICIO',
                'ESTADO DOCUMENTO',
                'FECHA ESTADO',
                'MOTIVO RECHAZO',
                'TRANSMISION ERP',
                'RESULTADO TRANSMISION ERP',
                'OBSERVACIONES TRANSMISION ERP'
            ];

            if($ofe->getGruposTrabajo->count() > 0) {
                $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
                $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();
                
                $titulos[] = (isset($gruposTrabajo->singular) && !empty($gruposTrabajo->singular) ? strtoupper($gruposTrabajo->singular) : 'GRUPO DE TRABAJO');
                $titulos[] = 'RESPONSABLE';
            }
            
            if($ofe->ofe_recepcion_fnc_activo == 'SI')
                $titulos[] = 'ESTADO VALIDACION';

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
                'cdo_valor_sin_impuestos',
                'cdo_impuestos',
                'cdo_cargos',
                'cdo_descuentos',
                'cdo_redondeo',
                'cdo_valor_a_pagar',
                'cdo_anticipo',
                'cdo_retenciones',
                'fecha_creacion',
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
                'cdo_fecha_validacion_dian',
                'estado_dian',
                'resultado_dian',
                'cdo_fecha_acuse',
                'cdo_fecha_recibo_bien',
                'cdo_estado',
                'cdo_fecha_estado',
                'motivo_rechazo',
                'get_transmision_erp.est_inicio_proceso',
                'get_transmision_erp.est_resultado',
                'get_transmision_erp.est_mensaje_resultado',
            ];

            if($ofe->getGruposTrabajo->count() > 0) {
                $columnas[] = 'grupo_trabajo';
                $columnas[] = 'get_usuario_responsable_recibidos.usu_nombre';
            }

            if ($ofe->ofe_recepcion_fnc_activo == 'SI') {
                $columnas[] = 'get_ultimo_estado_validacion.est_resultado';

                // Se obtienen las columnas adicionales configuradas en el evento recibo bien
                if(!empty($ofe->ofe_recepcion_fnc_configuracion)) {
                    $ofeRecepcionFncConfiguracion = json_decode($ofe->ofe_recepcion_fnc_configuracion);
                    if(isset($ofeRecepcionFncConfiguracion->evento_recibo_bien) && !empty($ofeRecepcionFncConfiguracion->evento_recibo_bien)) {

                        foreach ($ofeRecepcionFncConfiguracion->evento_recibo_bien as $value) {
                            array_push($titulos, strtoupper(str_replace('_', ' ', $this->sanear_string($value->campo))));
                        }

                        $columnas[] = 'estado_validacion';
                    }
                }
            }

            $documentos = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, true, true, $where_has_conditions, $where_doesnthave_conditions, 'recepcion');
            $procesoBackground = ($request->filled('pjj_tipo') && $request->pjj_tipo == 'RRECIBIDOS') ? true : false;

            return $this->printExcelDocumentos($documentos, $titulos, $columnas, 'documentos_recibidos', $procesoBackground);
        }

        $data = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, false, false, $where_has_conditions, $where_doesnthave_conditions, 'recepcion', 'array');

        $registros = $data['data']->map(function($registro) {
            $documento = $registro->toArray();

            $estadoDocumento           = '';
            $estadoDian                = '';
            $estadoTransmisionErp      = '';
            $estadoNotificacion        = '';
            $estadoNotificacionFallido = '';
            $estadoValidacion          = '';
            $documentoAnexo            = '';

            if ($registro->getDocumentoAprobado) {
                $estadoDocumento = 'APROBADO';
            } elseif ($registro->getDocumentoAprobadoNotificacion) {
                $estadoDocumento = 'APROBADO_NOTIFICACION';
            } elseif ($registro->getStatusEnProceso) {
                $estadoDocumento = 'GETSTATUS_ENPROCESO';
            } elseif ($registro->getDocumentoRechazado) {
                $estadoDocumento = 'RECHAZADO';
            } elseif ($registro->getEstadoRdiInconsistencia) {
                $estadoDocumento = 'RDI_INCONSISTENCIA';
            }

            if ($registro->getMaximoEstadoDocumento && ($registro->getMaximoEstadoDocumento->est_estado == 'ACEPTACION' || 
                $registro->getMaximoEstadoDocumento->est_estado == 'UBLACEPTACION') && $registro->getMaximoEstadoDocumento->est_resultado == 'EXITOSO'
            ) {
                $estadoDian = 'ACEPTACION';
            } else if ($registro->getMaximoEstadoDocumento && ($registro->getMaximoEstadoDocumento->est_estado == 'ACEPTACION' || 
                $registro->getMaximoEstadoDocumento->est_estado == 'UBLACEPTACION') && $registro->getMaximoEstadoDocumento->est_resultado == 'FALLIDO'
            ) {
                $estadoDian = 'ACEPTACION_FALLIDO';
            } else if ($registro->getMaximoEstadoDocumento && ($registro->getMaximoEstadoDocumento->est_estado == 'ACEPTACIONT' || 
                $registro->getMaximoEstadoDocumento->est_estado == 'UBLACEPTACIONT') && $registro->getMaximoEstadoDocumento->est_resultado == 'EXITOSO'
            ) {
                $estadoDian = 'ACEPTACIONT';
            } else if ($registro->getMaximoEstadoDocumento && ($registro->getMaximoEstadoDocumento->est_estado == 'ACEPTACIONT' || 
                $registro->getMaximoEstadoDocumento->est_estado == 'UBLACEPTACIONT') && $registro->getMaximoEstadoDocumento->est_resultado == 'FALLIDO'
            ) {
                $estadoDian = 'ACEPTACIONT_FALLIDO';
            } else if ($registro->getMaximoEstadoDocumento && ($registro->getMaximoEstadoDocumento->est_estado == 'RECHAZO' || 
                $registro->getMaximoEstadoDocumento->est_estado == 'UBLRECHAZO') && $registro->getMaximoEstadoDocumento->est_resultado == 'EXITOSO'
            ) {
                $estadoDian = 'RECHAZO';
            } else if ($registro->getMaximoEstadoDocumento && ($registro->getMaximoEstadoDocumento->est_estado == 'RECHAZO' || 
                $registro->getMaximoEstadoDocumento->est_estado == 'UBLRECHAZO') && $registro->getMaximoEstadoDocumento->est_resultado == 'FALLIDO'
            ) {
                $estadoDian = 'RECHAZO_FALLIDO';
            }

            if ($registro->getTransmisionErpExitoso) {
                $estadoTransmisionErp = 'TRANSMISIONERP_EXITOSO';
            } elseif ($registro->getTransmisionErpExcluido) {
                $estadoTransmisionErp = 'TRANSMISIONERP_EXCLUIDO';
            } elseif ($registro->getTransmisionErpFallido) {
                $estadoTransmisionErp = 'TRANSMISIONERP_FALLIDO';
            }

            if ($registro->getNotificacionAcuseRecibo) {
                $estadoNotificacion = 'NOTACUSERECIBO';
            } elseif ($registro->getNotificacionReciboBien) {
                $estadoNotificacion = 'NOTRECIBOBIEN';
            } elseif ($registro->getNotificacionAceptacion) {
                $estadoNotificacion = 'NOTACEPTACION';
            } elseif ($registro->getNotificacionRechazo) {
                $estadoNotificacion = 'NOTRECHAZO';
            }

            if ($registro->getNotificacionAcuseReciboFallido && !$registro->getNotificacionAcuseRecibo) {
                $estadoNotificacionFallido = 'NOTACUSERECIBO_FALLIDO';
            } elseif ($registro->getNotificacionReciboBienFallido && !$registro->getNotificacionReciboBien) {
                $estadoNotificacionFallido = 'NOTRECIBOBIEN_FALLIDO';
            } elseif ($registro->getNotificacionAceptacionFallido && !$registro->getNotificacionAceptacion) {
                $estadoNotificacionFallido = 'NOTACEPTACION_FALLIDO';
            } elseif ($registro->getNotificacionRechazoFallido && !$registro->getNotificacionRechazo) {
                $estadoNotificacionFallido = 'NOTRECHAZO_FALLIDO';
            }

            if ($registro->getDocumentosAnexos->count() > 0) {
                $documentoAnexo = 'SI';
            }

            if ($registro->getValidacionUltimo)
                $estadoValidacion = $registro->getValidacionUltimo->est_resultado;

            $documento['estado_documento']             = $estadoDocumento;
            $documento['estado_dian']                  = $estadoDian;
            $documento['estado_transmisionerp']        = $estadoTransmisionErp;
            $documento['estado_notificacion']          = $estadoNotificacion;
            $documento['estado_notificacion_fallido']  = $estadoNotificacionFallido;
            $documento['estado_validacion']            = $estadoValidacion;
            $documento['aplica_documento_anexo']       = $documentoAnexo;
            $documento['estado_transmision_opencomex'] = '';

            if(array_key_exists('cdo_hora', $documento))
                $documento['cdo_hora'] = isset($documento['cdo_hora']) && $documento['cdo_hora'] != "null" ? $documento['cdo_hora'] : '';

            if($this->ofeTransmisionOpenComex == $registro->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion && !empty($registro->getConfiguracionProveedor)) {                
                $nitsIntegracionOpenComex = explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'));

                if(in_array($registro->getConfiguracionProveedor->pro_identificacion, $nitsIntegracionOpenComex) && isset($registro->getOpencomexCxpExitoso) && !empty($registro->getOpencomexCxpExitoso)) {
                    $documento['estado_transmision_opencomex'] = 'TRANSMISIONOPENCOMEX_EXITOSO';
                } elseif(in_array($registro->getConfiguracionProveedor->pro_identificacion, $nitsIntegracionOpenComex) && isset($registro->getOpencomexCxpFallido) && !empty($registro->getOpencomexCxpFallido)) {
                    $documento['estado_transmision_opencomex'] = 'TRANSMISIONOPENCOMEX_FALLIDO';
                } elseif(in_array($registro->getConfiguracionProveedor->pro_identificacion, $nitsIntegracionOpenComex) && empty($registro->getOpencomexCxpExitoso) && empty($registro->getOpencomexCxpFallido)) {
                    $documento['estado_transmision_opencomex'] = 'TRANSMISIONOPENCOMEX_SINESTADO';
                }
            }

            if(!empty($registro->getConfiguracionProveedor))
                return $documento;
        });

        return response()->json([
            "total"     => $data['total'],
            "filtrados" => $data['filtrados'],
            "data"      => $registros
        ], 200);
    }


    /**
     * Retorna una lista con los documentos de validación.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response|BinaryFileResponse|Collection
     * @throws \Exception
     */
    public function getListaValidacionDocumentos(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit','2048M');

        $user = auth()->user();

        $required_where_conditions = [];
        define('SEARCH_METHOD', 'busquedaGeneral');

        $optional_where_conditions = [
            'rep_cabecera_documentos_daop.pro_id',
            'rep_cabecera_documentos_daop.cdo_origen',
            'rep_cabecera_documentos_daop.cdo_clasificacion',
            'rep_cabecera_documentos_daop.rfa_prefijo',
            'rep_cabecera_documentos_daop.cdo_consecutivo',
        ];

        $required_where_conditions['rep_cabecera_documentos_daop.ofe_id'] = $request->ofe_id;

        if (isset($request->cdo_consecutivo) && $request->cdo_consecutivo !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_consecutivo'] = $request->cdo_consecutivo;
        }

        if (isset($request->rfa_prefijo) && $request->rfa_prefijo !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.rfa_prefijo'] = $request->rfa_prefijo;
        }

        if (isset($request->cdo_clasificacion) && $request->cdo_clasificacion !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_clasificacion'] = $request->cdo_clasificacion;
        }

        if (isset($request->cdo_origen) && $request->cdo_origen !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_origen'] = $request->cdo_origen;
        }

        if (isset($request->cdo_usuario_responsable) && $request->cdo_usuario_responsable !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.cdo_usuario_responsable'] = $request->cdo_usuario_responsable;
        }

        if (isset($request->estado) && $request->estado !== '') {
            $required_where_conditions['rep_cabecera_documentos_daop.estado'] = $request->estado;
        }

        $special_where_conditions = [];

        if($request->has('pro_id') && $request->pro_id !== '' && !empty($request->pro_id)) {
            $special_where_conditions[] = [
                'type'  => 'in',
                'field' => 'rep_cabecera_documentos_daop.pro_id',
                'value' => $request->pro_id
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

        if (!empty($request->cdo_fecha_desde) && !empty($request->cdo_fecha_hasta)){
            array_push($special_where_conditions, [
                'type'   => 'IfBetween',
                'field1' => 'rep_cabecera_documentos_daop.cdo_fecha',
                'field2' => 'rep_cabecera_documentos_daop.cdo_fecha',
                'field3' => 'rep_cabecera_documentos_daop.cdo_hora',
                'value'  => [$request->cdo_fecha_desde . " 00:00:00", $request->cdo_fecha_hasta . " 23:59:59"]
            ]);
        }

        if (!empty($request->cdo_fecha_validacion_dian_desde) && !empty($request->cdo_fecha_validacion_dian_hasta)){
            array_push($special_where_conditions, [
                'type'   => 'IfBetween',
                'field1' => 'rep_cabecera_documentos_daop.cdo_fecha_validacion_dian',
                'field2' => 'rep_cabecera_documentos_daop.cdo_fecha_validacion_dian',
                'field3' => 'rep_cabecera_documentos_daop.cdo_hora',
                'value'  => [$request->cdo_fecha_validacion_dian_desde . " 00:00:00", $request->cdo_fecha_validacion_dian_hasta . " 23:59:59"]
            ]);
        }

        // Verifica si el usuario autenticado esta asociado con uno o varios proveedores para mostrar solo los documentos de ellos, de lo contrario mostrar los documentos de todos los proveedores en la BD
        $arrRelaciones = [
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_nombre_comercial,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres',
            'getConfiguracionProveedor' => function($query) use ($request) {
                $query = $this->recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $request->ofe_id, false, true);
            },
            'getConfiguracionProveedor.getProveedorGruposTrabajo' => function($query) {
                $query->select(['pro_id', 'gtr_id'])
                    ->where('estado', 'ACTIVO')
                    ->with([
                        'getGrupoTrabajo' => function($query) {
                            $query->select('gtr_id', 'gtr_codigo', 'gtr_nombre')
                                ->where('estado', 'ACTIVO');
                        }
                    ]);
            },
            'getParametrosMoneda:mon_id,mon_codigo,mon_descripcion',
            'getTipoDocumentoElectronico:tde_id,tde_codigo,tde_descripcion',
            'getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo:gtr_id,ofe_id',
            'getValidacionUltimo:est_id,rep_estados_documentos_daop.cdo_id,est_correos,est_informacion_adicional,est_estado,est_resultado,est_mensaje_resultado,est_object,est_ejecucion,est_motivo_rechazo,est_inicio_proceso,fecha_creacion',
            'getEstadoValidacionEnProcesoPendiente',
            'getEstadoValidacionValidado',
            'getUltimoEstadoValidacionEnProcesoPendiente',
            'getDocumentosAnexos:dan_id,cdo_id,dan_nombre',
            'getUsuarioResponsable:usu_id,usu_identificacion,usu_nombre',
            'getGrupoTrabajo:gtr_id,gtr_codigo,gtr_nombre',
        ];

        $where_has_conditions = [];
        if ($request->has('estado_validacion') && !empty($request->estado_validacion)) {
            foreach($request->estado_validacion as $estadoValidacion) {
                switch($estadoValidacion) {
                    case 'pendiente':
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'PENDIENTE');
                            }
                        ];
                        break;
                    case 'validado':
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'VALIDADO');
                            }
                        ];
                        break;
                    case 'rechazado':
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'RECHAZADO');
                            }
                        ];
                        break;
                    case 'pagado':
                        $where_has_conditions[] = [
                            'relation' => 'getValidacionUltimo',
                            'function' => function($query) {
                                $query->where('est_resultado', 'PAGADO');
                            }
                        ];
                        break;
                }
            }
        } else {
            $where_has_conditions[] = [
                'relation' => 'getValidacionUltimo',
                'function' => function($query) {
                    $query->where('est_resultado', 'PENDIENTE')
                        ->orWhere('est_resultado', 'VALIDADO')
                        ->orWhere('est_resultado', 'RECHAZADO')
                        ->orWhere('est_resultado', 'PAGADO');
                }
            ];
        }

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

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo', 'ofe_recepcion_fnc_configuracion'])
            ->where('ofe_id', $request->ofe_id)
            ->with(['getGruposTrabajo:gtr_id,ofe_id'])
            ->first();

        if ($ofe->ofe_recepcion_fnc_activo == 'SI' && $request->filled('campo_validacion') && $request->filled('valor_campo_validacion')) {
            $where_has_conditions[] = [
                'relation' => 'getUltimoEstadoValidacionEnProcesoPendiente',
                'function' => function($query) use ($request) {
                    $query->whereJsonContains('est_informacion_adicional->campos_adicionales', ['campo' => $request->campo_validacion, 'valor' => $request->valor_campo_validacion]);
                }
            ];
        }

        if ($request->has('excel') && !empty($request->excel)){
            $titulos = [
                'NIT RECEPTOR',
                'RECEPTOR',
                'NIT EMISOR',
                'EMISOR',
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
                'ORIGEN',
                'FECHA CARGUE',
                'RESPONSABLE',
                'ESTADO VALIDACION',
                'FONDO',
                'USUARIO FONDO',
                'FECHA FONDO',
                'OC SAP',
                'USUARIO OC SAP',
                'FECHA OC SAP',
                'POSICIÓN',
                'USUARIO POSICIÓN',
                'FECHA POSICIÓN',
                'HOJA DE ENTRADA',
                'USUARIO HOJA DE ENTRADA',
                'FECHA HOJA DE ENTRADA',
                'OBSERVACIÓN VALIDACIÓN',
                'USUARIO OBSERVACIÓN VALIDACIÓN',
                'FECHA OBSERVACIÓN VALIDACIÓN',
                'No APROBACIÓN',
                'FECHA VALIDACIÓN',
                'OBSERVACIÓN'
            ];

            if($ofe->getGruposTrabajo->count() > 0) {
                $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
                $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();
                
                $titulos[] = (isset($gruposTrabajo->singular) && !empty($gruposTrabajo->singular) ? strtoupper($gruposTrabajo->singular) : 'GRUPO DE TRABAJO');
            }

            $columnas = [
                'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion',
                'get_configuracion_obligado_facturar_electronicamente.ofe_razon_social',
                'get_configuracion_proveedor.pro_identificacion',
                'get_configuracion_proveedor.pro_razon_social',
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
                'cdo_valor_sin_impuestos',
                'cdo_impuestos',
                'cdo_cargos',
                'cdo_descuentos',
                'cdo_redondeo',
                'cdo_valor_a_pagar',
                'cdo_anticipo',
                'cdo_retenciones',
                'cdo_origen',
                'fecha_creacion',
                'get_usuario_responsable.usu_identificacion',
                [
                    'do' => function($get_validacion_ultimo_est_resultado) {
                        if($get_validacion_ultimo_est_resultado != 'ENPROCESO') {
                            return $get_validacion_ultimo_est_resultado;
                        } else {
                            return '';
                        }
                    },
                    'field' => 'get_validacion_ultimo.est_resultado',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'fondos');

                                if(!empty($datoSap) && array_key_exists('valor', $datoSap))
                                    return $datoSap['valor'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'fondos');

                                if(!empty($datoSap) && array_key_exists('nombre_usuario', $datoSap))
                                    return $datoSap['nombre_usuario'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'fondos');

                                if(!empty($datoSap) && array_key_exists('fecha', $datoSap))
                                    return $datoSap['fecha'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'oc_sap');

                                if(!empty($datoSap) && array_key_exists('valor', $datoSap))
                                    return $datoSap['valor'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'oc_sap');

                                if(!empty($datoSap) && array_key_exists('nombre_usuario', $datoSap))
                                    return $datoSap['nombre_usuario'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'oc_sap');

                                if(!empty($datoSap) && array_key_exists('fecha', $datoSap))
                                    return $datoSap['fecha'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'posicion');

                                if(!empty($datoSap) && array_key_exists('valor', $datoSap))
                                    return $datoSap['valor'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'posicion');

                                if(!empty($datoSap) && array_key_exists('nombre_usuario', $datoSap))
                                    return $datoSap['nombre_usuario'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'posicion');

                                if(!empty($datoSap) && array_key_exists('fecha', $datoSap))
                                    return $datoSap['fecha'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'hoja_de_entrada');

                                if(!empty($datoSap) && array_key_exists('valor', $datoSap))
                                    return $datoSap['valor'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'hoja_de_entrada');

                                if(!empty($datoSap) && array_key_exists('nombre_usuario', $datoSap))
                                    return $datoSap['nombre_usuario'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'hoja_de_entrada');

                                if(!empty($datoSap) && array_key_exists('fecha', $datoSap))
                                    return $datoSap['fecha'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'observacion_validacion');

                                if(!empty($datoSap) && array_key_exists('valor', $datoSap))
                                    return $datoSap['valor'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'observacion_validacion');

                                if(!empty($datoSap) && array_key_exists('nombre_usuario', $datoSap))
                                    return $datoSap['nombre_usuario'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'observacion_validacion');

                                if(!empty($datoSap) && array_key_exists('fecha', $datoSap))
                                    return $datoSap['fecha'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_en_proceso_pendiente.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'no_aprobacion');

                                if(!empty($datoSap) && array_key_exists('valor', $datoSap))
                                    return $datoSap['valor'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_validado.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'fecha_validacion');

                                if(!empty($datoSap) && array_key_exists('valor', $datoSap))
                                    return $datoSap['valor'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_validado.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($est_informacion_adicional) {
                        if(!empty($est_informacion_adicional)) {
                            $informacionAdicional = json_decode($est_informacion_adicional, true);
                            if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('campos_adicionales', $informacionAdicional) && !empty($informacionAdicional['campos_adicionales'])) {
                                $datoSap =  collect($informacionAdicional['campos_adicionales'])
                                    ->firstWhere('campo', 'observacion');

                                if(!empty($datoSap) && array_key_exists('valor', $datoSap))
                                    return $datoSap['valor'];
                                else
                                    return '';
                            } else {
                                return '';
                            }
                        }
                    },
                    'field' => 'get_estado_validacion_validado.est_informacion_adicional',
                    'validation_type'=> 'callback'
                ]
            ];

            if($ofe->getGruposTrabajo->count() > 0)
                $columnas[] = 'grupo_trabajo';

            $documentos = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, true, true, $where_has_conditions, '', 'recepcion');
            $procesoBackground = ($request->filled('pjj_tipo') && $request->pjj_tipo == 'RVALIDACION') ? true : false;

            return $this->printExcelDocumentos($documentos, $titulos, $columnas, 'validacion_documentos', $procesoBackground);
        }

        $data = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, false, false, $where_has_conditions, '', 'recepcion', 'array');
    
        $documentos = [];
        $data['data']->map(function($registro) use (&$documentos) {
            $documento = $registro->toArray();
            $documento['estado_validacion']      = '';
            $documento['aplica_documento_anexo'] = '';

            $usuarioResponsable = User::select(['usu_id', 'usu_nombre'])
                ->where('usu_id', $registro->cdo_usuario_responsable)
                ->first();

            $documento['cdo_usuario_responsable'] = '';
            if ($usuarioResponsable)
                $documento['cdo_usuario_responsable'] = $usuarioResponsable->usu_nombre;

            if ($registro->getValidacionUltimo)
                $documento['estado_validacion'] = $registro->getValidacionUltimo->est_resultado;

            if (count($registro->getDocumentosAnexos) > 0)
                $documento['aplica_documento_anexo'] = 'SI';

            if(!empty($registro->getConfiguracionProveedor))
                $documentos[] = $documento;
        });

        return response()->json([
            "total"     => $data['total'],
            "filtrados" => $data['filtrados'],
            "data"      => $documentos
        ], 200);
    }

    /**
     * Ejecuta la descarga de un archivo.
     *
     * @param  string $nombrearchivo Nombre del archivo a descargar
     * @return BinaryFileResponse
     */
    private function download(string $nombrearchivo) {
        $headers = [
            header('Access-Control-Expose-Headers: Content-Disposition')
        ];

        // Retorna el documento y lo elimina tan pronto es enviado en la respuesta
        return response()
            ->download(storage_path('etl/descargas/' . $nombrearchivo), $nombrearchivo, $headers)
            ->deleteFileAfterSend(true);
    }

    /**
     * Construye un mensaje de error en caso de que no se pueda descargar un archivo.
     *
     * @param  string $messsage Mensaje de error de respuesta
     * @param  array $errores Contiene los errores generados en el proceso
     * @return JsonResponse
     */
    private function generateErrorResponse(string $messsage, array $errores = []) {
        // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar
        // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
        $headers = [
            header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
            header('X-Error-Status: 404'),
            header("X-Error-Message: {$messsage}")
        ];
        return response()->json([
            'message' => 'Error en la Petición',
            'errors' => !empty($errores) ? $errores : [$messsage]
        ], 404, $headers);
    }

    /**
     * Obtiene un documento juntos con las relaciones requeridas.
     *
     * @param  int $cdo_id ID del documento electrónico
     * @param  Request $request Petición que da origen al llamado de éste método
     * @return Collection|null
     */
    private function getDocumento(int $cdo_id, Request $request) {
        $documento = RepCabeceraDocumentoDaop::where('cdo_id', $cdo_id)
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,pai_id,dep_id,mun_id,rfi_id',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosPais',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosDepartamento',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosMunicipio',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosRegimenFiscal',
                'getConfiguracionObligadoFacturarElectronicamente.getResolucionesFacturacion',
                'getConfiguracionProveedor' => function($query) use ($request) {
                    $query = $this->recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $request->ofe_id);
                },
                'getConfiguracionProveedor.getParametroPais',
                'getConfiguracionProveedor.getParametroDepartamento',
                'getConfiguracionProveedor.getParametroMunicipio'
            ])
            ->whereHas('getConfiguracionProveedor', function($query) use ($request) {
                $query = $this->recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $request->ofe_id);
            })
            ->first();

        if(!$documento) {
            $documento = $this->recepcionCabeceraService->consultarDocumentoByCdoId($cdo_id, [
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,pai_id,dep_id,mun_id,rfi_id',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosPais',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosDepartamento',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosMunicipio',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosRegimenFiscal',
                'getConfiguracionObligadoFacturarElectronicamente.getResolucionesFacturacion',
                'getConfiguracionProveedor',
                'getConfiguracionProveedor.getParametroPais',
                'getConfiguracionProveedor.getParametroDepartamento',
                'getConfiguracionProveedor.getParametroMunicipio'
            ]);
        }

        return $documento;
    }

    /**
     * Obtiene un documento y lo almacena en una lista para su posterior reutilizacion.
     *
     * @param  int $cdo_id
     * @param  array $documentos Array de documentos electrónicos, permite no realizar consultas innecesarias a la base de datos
     * @param  Request $request Petición que da origen al llamado de éste método
     * @return Collection|null
     */
    private function obtenerDocumento(int $cdo_id, array &$documentos, Request $request) {
        $documento = null;
        if (array_key_exists($cdo_id, $documentos))
            $documento = $documentos[$cdo_id];
        else {
            $documento = $this->getDocumento($cdo_id, $request);
            $documentos[$cdo_id] = $documento;
        }
        return $documento;
    }

    /**
     * Retorna un string con el tipo de documento y un prefijo por defecto dependiendo del tipo de documento.
     *
     * @param  string $cdo_clasificacion Clasificación del documento
     * @param  string $rfa_prefijo Prefijo del documento
     * @return array
     */
    private function tipoDocumento(string $cdo_clasificacion, string $rfa_prefijo) {
        $tipoDoc = '';
        $prefijo = '';
        switch ($cdo_clasificacion) {
            case 'FC':
                $tipoDoc = 'Factura';
                $prefijo = ($rfa_prefijo == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
            case 'NC':
                $tipoDoc = 'Nota Crédito';
                $prefijo = ($rfa_prefijo == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
            case 'ND':
                $tipoDoc = 'Nota Dédito';
                $prefijo = ($rfa_prefijo == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
        }

        return [
            'tipoDoc' => $tipoDoc,
            'prefijo' => $prefijo
        ];
    }

    /**
     * Construye el nombre para un documento electronico en funcion de un registro de cabecera y un tipo de archivo
     *
     * @param $documento
     * @param $tipo
     * @param $sufijo
     * @param $nitProveedor
     * @return string
     */
    private function getNombreArchivo($documento, $tipo, $sufijo = '') {
        $tipoDoc = $this->tipoDocumento($documento->cdo_clasificacion, $documento->rfa_prefijo);

        $nitProveedor = ConfiguracionProveedor::select('pro_identificacion')
            ->where('pro_id', $documento->pro_id)
            ->first();
        
        if ($sufijo === '')
            return $documento->cdo_clasificacion . $tipoDoc['prefijo'] . $documento->cdo_consecutivo . $nitProveedor->pro_identificacion . ".{$tipo}";
        return $documento->cdo_clasificacion . $tipoDoc['prefijo'] . $documento->cdo_consecutivo . $nitProveedor->pro_identificacion . "-{$sufijo}.{$tipo}";
    }

    /**
     * Obtiene el nombre de archivo para las descargas de XML o PDF.
     * 
     * Permite construir el nombre del archivo a descargar teniendo en cuenta que el XML y PDF de un documento 
     * deben descargar con el mismo nombre.
     *
     * @param object $documento Información del documento a descargar
     * @param string $extension Extensión del documento a descargar
     * @return string
     */
    private function getNombreArchivoPdfXml($documento, string $extension): string {
        $objNombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
        $nombreArchivo     = "";

        // Codigo para establecer el nombre del archivo PDF igual al del XML
        // Esto porque cuando el PDF es generado por ETL esta quedando con un nombre diferente
        if ($documento->cdo_nombre_archivos != '' && 
            isset($objNombreArchivos->xml_ubl) && $objNombreArchivos->xml_ubl != '' &&
            isset($objNombreArchivos->pdf) && $objNombreArchivos->pdf != ''
        ) {
            $arrNombreXml = explode('.', $objNombreArchivos->xml_ubl);
            $arrNombrePdf = explode('.', $objNombreArchivos->pdf);

            // Si el nombre del PDF es diferente al del XML se debe descargar el PDF con el nombre del XML
            if (substr($objNombreArchivos->xml_ubl,0,-(strlen(end($arrNombreXml))+1)) != substr($objNombreArchivos->pdf,0,-(strlen(end($arrNombrePdf))+1)))
                $nombreArchivo = substr($objNombreArchivos->xml_ubl,0,-(strlen(end($arrNombreXml))+1)) . "." . $extension;
            else
                $nombreArchivo = ($extension == 'xml') ? $objNombreArchivos->xml_ubl : $objNombreArchivos->pdf;

        } else if ($documento->cdo_nombre_archivos != '' &&
            isset($objNombreArchivos->xml_ubl)  && $objNombreArchivos->xml_ubl != '' &&
            (!isset($objNombreArchivos->pdf) || $objNombreArchivos->pdf == '')
        ) {
            // Valida si existe el nombre del XML y no existe el nombre del PDF
            $arrNombreXml  = explode('.', $objNombreArchivos->xml_ubl);
            $nombreArchivo = substr($objNombreArchivos->xml_ubl,0,-(strlen(end($arrNombreXml))+1)) . "." . $extension;

        }  else if ($documento->cdo_nombre_archivos != '' &&
            isset($objNombreArchivos->pdf)  && $objNombreArchivos->pdf != '' &&
            (!isset($objNombreArchivos->xml_ubl) || $objNombreArchivos->xml_ubl == '')
        ) {
            // Valida si existe el nombre del PDF y no existe el nombre del XML
            $arrNombrePdf  = explode('.', $objNombreArchivos->pdf);
            $nombreArchivo = substr($objNombreArchivos->pdf,0,-(strlen(end($arrNombrePdf))+1)) . "." . $extension;

        }  else
            $nombreArchivo = $this->getNombreArchivo($documento, $extension, '');
        
        return $nombreArchivo;
    }

    /**
     * Retorna un array con los diferentes registros de estados solicitados.
     *
     * @param  array  $ids Autoincrementales de los documentos
     * @param  string $estado Estado del documento
     * @param  array  $columnas Columnas a seleccionar en la consulta
     * @return mixed
     */
    private function getEstadosDocumentos(array $ids, string $estado, array $columnas) {
        $documentos = [];
        RepEstadoDocumentoDaop::select($columnas)
            ->whereIn('cdo_id', $ids)
            ->where('est_estado', $estado)
            ->where('est_resultado', 'EXITOSO')
            ->get()
            ->map(function ($item) use (&$documentos) {
                if (!is_null($item) && isset($item->cdo_id))
                    $documentos[$item->cdo_id] = $item->toArray();
            });
        return $documentos;
    }

    /**
     * Almacena un archivo PDF especificado en el storage de descargas para poder servirlo al cliente.
     *
     * @param $documento
     * @param $estado
     * @param $nombrePdf
     * @param string $base64
     * @param string $temp_dir
     * @return void
     */
    private function preparePDF($documento, $estado, $nombrePdf, $base64 = '', $temp_dir = '') {
        MainTrait::setFilesystemsInfo();
        if ($base64 === '') {
            if (substr(bin2hex(base64_decode($estado['est_archivo'])), 0, 6) === 'efbbbf')
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombrePdf, substr(base64_decode($estado['est_archivo']), 3));
            else
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombrePdf, base64_decode($estado['est_archivo']));
        } else {
            if (substr(bin2hex(base64_decode($base64)), 0, 6) === 'efbbbf')
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombrePdf, substr(base64_decode($base64), 3));
            else
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombrePdf, base64_decode($base64));
        }
    }

    /**
     * Almacena un archivo XML especificado en el storage de descargas para poder servirlo al cliente.
     *
     * @param $documento
     * @param $estado
     * @param $nombreXml
     * @param string $temp_dir
     * @return void
     */
    private function prepareXML($documento, $estado, $nombreXml, $temp_dir = '') {
        MainTrait::setFilesystemsInfo();
        if(substr(bin2hex(base64_decode($estado['est_xml'])), 0, 6) === 'efbbbf')
            Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombreXml, substr(base64_decode($estado['est_xml']), 3));
        else
            Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir .$nombreXml, base64_decode($estado['est_xml']));
    }

    /**
     * Elimina caracteres BOM de los XML o PDF si los tiene.
     *
     * @param string $contenidoArchivo Contenido del archivo XML|PDF
     * @param string $contenidoArchivo
     * @return string
     */
    private function eliminarCaracteresBOM($contenidoArchivo) {
        if(strtolower(substr(bin2hex($contenidoArchivo), 0, 6)) === 'efbbbf')
            return substr($contenidoArchivo, 3);
        else
            return $contenidoArchivo;
    }

    /**
     * Permite descargar documentos XML, PDF, Acuse de Recibo y Application Response de uno o varios documentos electrónicos.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse|Response|BinaryFileResponse
     * @throws \Exception
     */
    public function descargarDocumentos(Request $request) {
        $fraseErrorDescargas = 'No se encontraron documentos para descargar';

        // Array de tipos de documentos a descargar
        $arrTiposDocumentos = explode(',', $request->tipos_documentos);

        // Array de ids de documentos a descargar
        $arrCdoIds = explode(',', $request->cdo_ids);

        // Si solamente se recibe un tipo de documento y un solo cdo_id
        // Se genera la descarga solamente de ese archivo
        if (count($arrTiposDocumentos) == 1 && count($arrCdoIds) == 1) {
            $documento     = $this->getDocumento($arrCdoIds[0], $request);
            $cdoId         = $arrCdoIds[0];
            $uuid          = Uuid::uuid4();
            $nombreZip     = '';
            $losDocumentos = [];

            if ($documento) {
                if ($documento->cdo_origen != 'NO-ELECTRONICO') {
                    // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo + pro_identificacion
                    $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));

                    if (strtolower($arrTiposDocumentos[0]) == 'xml-ubl') {
                        $nombreXml = $this->getNombreArchivoPdfXml($documento, 'xml');

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, 'RDI');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlUbl               = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlUbl)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlUbl)], $nombreXml);
                                    return $this->download($nombreXml);
                                }
                            }
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        } else {
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        }
                    } elseif ($arrTiposDocumentos[0] == 'pdf') {
                        $nombrePdf = $this->getNombreArchivoPdfXml($documento, 'pdf');

                        if($nombrePdf) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, 'RDI');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $pdfRG                = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                                );
                                // Validación del contenido del archivo PDF
                                if((new TenantRecepcionService)->validatePdfContent($pdfRG)) {
                                    // Extracción de datos a partir del xml
                                    $extractorXml = new TenantXmlUblExtractorHelper();
                                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_ubl) && $nombreArchivos->xml_ubl != '')
                                        $nombreXml = $nombreArchivos->xml_ubl;
                                    else
                                        $nombreXml = $this->getNombreArchivo($documento, 'xml', '');

                                    $xmlUbl = MainTrait::obtenerArchivoDeDisco(
                                        'recepcion',
                                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                        $documento,
                                        array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                    );
                                    if(empty($xmlUbl)) {
                                        return $this->generateErrorResponse($fraseErrorDescargas);
                                    }
                                    $documentoXml = base64_encode($xmlUbl);
                                    $jsonXML      = base64_decode($extractorXml($documentoXml));
                                    // Petición al microservicio DO para la generación de la representación gráfica de recepción usando el json de respuesta del extractor
                                    $peticionDO = TenantTrait::peticionMicroservicio("DO", "POST", "/api/pdf/recepcion/generar-representacion-grafica", $jsonXML, "pdf");
                                    if($peticionDO['error'] === false) {
                                        $pdfRG = base64_decode($peticionDO["respuesta"]);
                                    } else {
                                        return $this->generateErrorResponse($fraseErrorDescargas);
                                    }
                                }
                                if(!empty($pdfRG)) {
                                    $this->preparePDF($documento, ['est_archivo' => base64_encode($pdfRG)], $nombrePdf);
                                    return $this->download($nombrePdf);
                                }
                            }
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        } else {
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        }
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ar_estado_dian') {
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->attached) && $nombreArchivos->attached != '') {
                            $nombreXml = $nombreArchivos->attached;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationResponseDian');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, 'GETSTATUS');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlArDian            = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlArDian)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlArDian)], $nombreXml);
                                    return $this->download($nombreXml);
                                }
                            }
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        } else {
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        }
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ar_acuse_recibo') {
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_acuse) && $nombreArchivos->xml_acuse != '') {
                            $nombreXml = $nombreArchivos->xml_acuse;
                        } elseif($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->acuse) && $nombreArchivos->acuse != '') {
                            $nombreXml = $nombreArchivos->acuse;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationResponseAcuseRecibo');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, 'UBLACUSERECIBO');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlAcuseRecibo       = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlAcuseRecibo)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlAcuseRecibo)], $nombreXml);
                                    return $this->download($nombreXml);
                                }
                            }
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        }

                        return $this->generateErrorResponse($fraseErrorDescargas);
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ar_recibo_bien') {
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_recibo) && $nombreArchivos->xml_recibo != '') {
                            $nombreXml = $nombreArchivos->xml_recibo;
                        } elseif($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->estado_documento) && $nombreArchivos->estado_documento != '') {
                            $nombreXml = $nombreArchivos->estado_documento;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationresponseReciboBien');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, 'UBLRECIBOBIEN');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlReciboBien        = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlReciboBien)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlReciboBien)], $nombreXml);
                                    return $this->download($nombreXml);
                                }
                            }
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        } else {
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        }
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ar_aceptacion_expresa') {
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_aceptacion) && $nombreArchivos->xml_aceptacion != '') {
                            $nombreXml = $nombreArchivos->xml_aceptacion;
                        } elseif($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->estado_documento) && $nombreArchivos->estado_documento != '') {
                            $nombreXml = $nombreArchivos->estado_documento;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationResponseAceptacionExpresa');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, 'UBLACEPTACION');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlAceptacion        = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlAceptacion)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlAceptacion)], $nombreXml);
                                    return $this->download($nombreXml);
                                }
                            }
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        } else {
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        }
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ar_reclamo_rechazo') {
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_rechazo) && $nombreArchivos->xml_rechazo != '') {
                            $nombreXml = $nombreArchivos->xml_rechazo;
                        } elseif($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->estado_documento) && $nombreArchivos->estado_documento != '') {
                            $nombreXml = $nombreArchivos->estado_documento;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationResponseReclamo');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, 'UBLRECHAZO');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlRechazo        = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlRechazo)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlRechazo)], $nombreXml);
                                    return $this->download($nombreXml);
                                }
                            }
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        } else {
                            return $this->generateErrorResponse($fraseErrorDescargas);
                        }
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ad_acuse_recibo') {
                        return $this->descargarAttachedDocument($arrCdoIds, $cdoId, $documento, $nombreArchivos, 'acuse', 'AttachedDocumentAcuseRecibo', 'NOTACUSERECIBO', $fraseErrorDescargas);
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ad_recibo_bien') {
                        return $this->descargarAttachedDocument($arrCdoIds, $cdoId, $documento, $nombreArchivos, 'recibo', 'AttachedDocumentReciboBien', 'NOTRECIBOBIEN', $fraseErrorDescargas);
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ad_aceptacion_expresa') {
                        return $this->descargarAttachedDocument($arrCdoIds, $cdoId, $documento, $nombreArchivos, 'aceptacion', 'AttachedDocumentAceptacionExpresa', 'NOTACEPTACION', $fraseErrorDescargas);
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'ad_reclamo_rechazo') {
                        return $this->descargarAttachedDocument($arrCdoIds, $cdoId, $documento, $nombreArchivos, 'rechazo', 'AttachedDocumentReclamo', 'NOTRECHAZO', $fraseErrorDescargas);
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'pdf_acuse_recibo') {
                        return $this->descargarRgEventosDian($cdoId, $documento, $nombreArchivos, 'acuse', 'rgAcuseRecibo', 'NOTACUSERECIBO');
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'pdf_recibo_bien') {
                        return $this->descargarRgEventosDian($cdoId, $documento, $nombreArchivos, 'recibo', 'rgReciboBien', 'NOTRECIBOBIEN');
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'pdf_aceptacion_expresa') {
                        return $this->descargarRgEventosDian($cdoId, $documento, $nombreArchivos, 'aceptacion', 'rgAceptacion', 'NOTACEPTACION');
                    } elseif (strtolower($arrTiposDocumentos[0]) == 'pdf_reclamo_rechazo') {
                        return $this->descargarRgEventosDian($cdoId, $documento, $nombreArchivos, 'rechazo', 'rgReclamo', 'NOTRECHAZO');
                    }
                } else {
                    return $this->generateErrorResponse('No se permite la descarga para documentos no electr&oacute;nicos');
                }
            }

            return $this->generateErrorResponse('El documento electr&oacute;nico seleccionado no existe o no ha sido firmado electr&oacute;nicamente');
        } elseif (count($arrTiposDocumentos) >= 1 || count($arrCdoIds) >= 1) {
            // Sin son varios tipos de documentos y/o varios cdo_id
            // Se genera un archivo comprimido con los documentos solicitados

            // Universal Unique ID utilizado para nombrar folder y zip
            $uuid = Uuid::uuid4();
            $losDocumentos = [];
            $encontroDocumento = false;

            // Por cada tipo de documento se debe crear el archivo para cada documento electrónico
            if (in_array('xml-ubl', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo + pro_identificacion
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $nombreXml = $this->getNombreArchivoPdfXml($documento, 'xml');

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdo_id, 'RDI');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlUbl               = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlUbl)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlUbl)], $nombreXml, $uuid->toString() . '/');
                                }
                            }
                        }
                    }
                }
            }

            if (in_array('pdf', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo + pro_identificacion
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $nombrePdf = $this->getNombreArchivoPdfXml($documento, 'pdf');

                        if($nombrePdf) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdo_id, 'RDI');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $pdfRG                = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                                );
                                // Validación del contenido del archivo PDF
                                if((new TenantRecepcionService)->validatePdfContent($pdfRG)) {
                                    // Extracción de datos a partir del xml
                                    $extractorXml = new TenantXmlUblExtractorHelper();
                                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_ubl) && $nombreArchivos->xml_ubl != '')
                                        $nombreXml = $nombreArchivos->xml_ubl;
                                    else
                                        $nombreXml = $this->getNombreArchivo($documento, 'xml', '');

                                    $xmlUbl = MainTrait::obtenerArchivoDeDisco(
                                        'recepcion',
                                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                        $documento,
                                        array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                    );
                                    if(!empty($xmlUbl)) {
                                        $documentoXml = base64_encode($xmlUbl);
                                        $jsonXML      = base64_decode($extractorXml($documentoXml));
                                        // Petición al microservicio DO para la generación de la representación gráfica de recepción usando el json de respuesta del extractor
                                        $peticionDO = TenantTrait::peticionMicroservicio("DO", "POST", "/api/pdf/recepcion/generar-representacion-grafica", $jsonXML, "pdf");
                                        if($peticionDO['error'] === false) {
                                            $pdfRG = base64_decode($peticionDO["respuesta"]);
                                        } else {
                                            $pdfRG = '';
                                        }
                                    }
                                }
                                if(!empty($pdfRG)) {
                                    $this->preparePDF($documento, ['est_archivo' => base64_encode($pdfRG)], $nombrePdf, '', $uuid->toString() . '/');
                                }
                            }
                        }
                    }
                }
            }

            if (in_array('ar_estado_dian', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo + pro_identificacion
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->attached) && $nombreArchivos->attached != '') {
                            $nombreXml = $nombreArchivos->attached;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationResponseDian');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdo_id, 'GETSTATUS');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlArDian            = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlArDian)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlArDian)], $nombreXml, $uuid->toString() . '/');
                                }
                            }
                        }
                    }
                }
            }

            if (in_array('ar_acuse_recibo', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo + pro_identificacion
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_acuse) && $nombreArchivos->xml_acuse != '') {
                            $nombreXml = $nombreArchivos->xml_acuse;
                        } elseif($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->acuse) && $nombreArchivos->acuse != '') {
                            $nombreXml = $nombreArchivos->acuse;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationResponseAcuseRecibo');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdo_id, 'UBLACUSERECIBO');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlAcuseRecibo        = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlAcuseRecibo)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlAcuseRecibo)], $nombreXml, $uuid->toString() . '/');
                                }
                            }
                        }
                    }
                }
            }

            if (in_array('ar_recibo_bien', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo + pro_identificacion
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_recibo) && $nombreArchivos->xml_recibo != '') {
                            $nombreXml = $nombreArchivos->xml_recibo;
                        } elseif($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->estado_documento) && $nombreArchivos->estado_documento != '') {
                            $nombreXml = $nombreArchivos->estado_documento;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationresponseReciboBien');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdo_id, 'UBLRECIBOBIEN');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlReciboBien        = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlReciboBien)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlReciboBien)], $nombreXml, $uuid->toString() . '/');
                                }
                            }
                        }
                    }
                }
            }

            if (in_array('ar_aceptacion_expresa', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo + pro_identificacion
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_aceptacion) && $nombreArchivos->xml_aceptacion != '') {
                            $nombreXml = $nombreArchivos->xml_aceptacion;
                        } elseif($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->estado_documento) && $nombreArchivos->estado_documento != '') {
                            $nombreXml = $nombreArchivos->estado_documento;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationResponseAceptacionExpresa');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdo_id, 'UBLACEPTACION');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlAceptacion        = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlAceptacion)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlAceptacion)], $nombreXml, $uuid->toString() . '/');
                                }
                            }
                        }
                    }
                }
            }

            if (in_array('ar_reclamo_rechazo', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo + pro_identificacion
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_rechazo) && $nombreArchivos->xml_rechazo != '') {
                            $nombreXml = $nombreArchivos->xml_rechazo;
                        } elseif($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->estado_documento) && $nombreArchivos->estado_documento != '') {
                            $nombreXml = $nombreArchivos->estado_documento;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, 'xml', 'ApplicationResponseReclamo');
                        }

                        if($nombreXml) {
                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdo_id, 'UBLRECHAZO');
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $xmlRechazo        = MainTrait::obtenerArchivoDeDisco(
                                    'recepcion',
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                                );

                                if(!empty($xmlRechazo)) {
                                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlRechazo)], $nombreXml, $uuid->toString() . '/');
                                }
                            }
                        }
                    }
                }
            }

            if (in_array('ad_acuse_recibo', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $this->descargarAttachedDocument($arrCdoIds, $cdo_id, $documento, $nombreArchivos, 'acuse', 'AttachedDocumentAcuseRecibo', 'NOTACUSERECIBO', $fraseErrorDescargas, $uuid);
                    }
                }
            }

            if (in_array('ad_recibo_bien', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $this->descargarAttachedDocument($arrCdoIds, $cdo_id, $documento, $nombreArchivos, 'recibo', 'AttachedDocumentReciboBien', 'NOTRECIBOBIEN', $fraseErrorDescargas, $uuid);
                    }
                }
            }

            if (in_array('ad_aceptacion_expresa', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $this->descargarAttachedDocument($arrCdoIds, $cdo_id, $documento, $nombreArchivos, 'aceptacion', 'AttachedDocumentAceptacionExpresa', 'NOTACEPTACION', $fraseErrorDescargas, $uuid);
                    }
                }
            }

            if (in_array('ad_reclamo_rechazo', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $this->descargarAttachedDocument($arrCdoIds, $cdo_id, $documento, $nombreArchivos, 'rechazo', 'AttachedDocumentReclamo', 'NOTRECHAZO', $fraseErrorDescargas, $uuid);
                    }
                }
            }

            if (in_array('pdf_acuse_recibo', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $this->descargarRgEventosDian($cdo_id, $documento, $nombreArchivos, 'acuse', 'rgAcuseRecibo', 'NOTACUSERECIBO', $uuid);
                    }
                }
            }

            if (in_array('pdf_recibo_bien', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $this->descargarRgEventosDian($cdo_id, $documento, $nombreArchivos, 'recibo', 'rgReciboBien', 'NOTRECIBOBIEN', $uuid);
                    }
                }
            }

            if (in_array('pdf_aceptacion_expresa', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $this->descargarRgEventosDian($cdo_id, $documento, $nombreArchivos, 'aceptacion', 'rgAceptacion', 'NOTACEPTACION', $uuid);
                    }
                }
            }

            if (in_array('pdf_reclamo_rechazo', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos, $request);
                    if ($documento && $documento->cdo_origen != 'NO-ELECTRONICO') {
                        $encontroDocumento = true;
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        $this->descargarRgEventosDian($cdo_id, $documento, $nombreArchivos, 'rechazo', 'rgReclamo', 'NOTRECHAZO', $uuid);
                    }
                }
            }

            if (!$encontroDocumento) {
                $headers = [
                    header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                    header('X-Error-Status: 422'),
                    header('X-Error-Message: No se permite la descarga para documentos no electr&oacute;nicos')
                ];

                return response()->json([
                    'message' => 'Error en la Descarga',
                    'errors' => ['Ocurrio un problema al intentar generar el archivo zip con los documentos']
                ], 422, $headers);
            }
            
            $nombreZip = $uuid->toString() . ".zip";
            return $this->crearZip($uuid, $nombreZip);
        } else {
            return response()->json([
                'message' => 'Error en la Petición',
                'errors' => 'No se indicaron los tipos de documentos y/o documentos a descargar'
            ], 422);
        }
    }

    /**
     * Permite descargar documentos Application Response de los eventos de un documento.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse|Response
     * @throws \Exception
     */
    public function descargarXmlEstado(Request $request) {
        // Trayendo datos del documento
        $documento = $this->getDocumento($request->cdo_id, $request);

        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
        $estado = MainTrait::obtenerEstadoDocumento('recepcion', $request->cdo_id, $request->estado);
        if ($documento && $estado) {
            $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
            $xmlAcuseRecibo       = MainTrait::obtenerArchivoDeDisco(
                'recepcion',
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                $documento,
                array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
            );

            if($xmlAcuseRecibo) {
                return response()->json([
                    'data' => [
                        'est_xml' => base64_encode($xmlAcuseRecibo)
                    ]
                ]);
            }
        }

        return $this->generateErrorResponse('No se encontraron documentos para descargar');
        
    }

    /**
     * Descarga los archivos de un AttachedDocument de un documento electrónico.
     * 
     * Los AttachedDocument son un archivo zip que contiene un archivo PDF y un archivo XML
     * 
     * @param array $arrCdoIds Array de ID de documentos electrónicos en la petición
     * @param int $cdoId ID del documento para el cual se debe descargar el AttachedDocumento
     * @param RepCabeceraDocumentoDaop $documento Colección con información dle documento electrónico
     * @param object $nombreArchivos Objeto conteniendo los nombres de archivos que se han guardado para el documento electrónico
     * @param string $campoNombreAD Nombre del campo o propiedad dentro de $nombreArchivo que debería contener el nombre de los archivos PDF, XML (Attached) y ZIP para el documento electrónico
     * @param string $nombreParaAD Nombre que es utilizado como sufijo para el nombre del AttachedDocument
     * @param string $nombreDelEstadoAD Nombre del estado sobre el cual se debe realizar la busqueda
     * @param string $fraseErrorDescargas Frase que es retornada en caso de presentarse un error en la descarga
     * @param UUID $uuidPadre UUID que es utilizado en la descarga de múltiples archivos, es null cuando se descarga un solo archivo
     * @return JsonResponse|Response|BinaryFileResponse
     */
    private function descargarAttachedDocument($arrCdoIds, $cdoId, $documento, $nombreArchivos, $campoNombreAD, $nombreParaAD, $nombreDelEstadoAD, $fraseErrorDescargas, $uuidPadre = null) {
        $propiedadNombrePdf      = 'pdf_' . $campoNombreAD;
        $propiedadNombreAttached = 'attached_' . $campoNombreAD;
        $propiedadNombreZip      = 'zip_' . $campoNombreAD;
        
        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->$propiedadNombrePdf) && $nombreArchivos->$propiedadNombrePdf != '') {
            $nombrePdf = $nombreArchivos->$propiedadNombrePdf;
        } else {
            $nombrePdf = $this->getNombreArchivo($documento, 'pdf', $nombreParaAD);
        }

        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->$propiedadNombreAttached) && $nombreArchivos->$propiedadNombreAttached != '') {
            $nombreAttached = $nombreArchivos->$propiedadNombreAttached;
        } else {
            $nombreAttached = $this->getNombreArchivo($documento, 'xml', $nombreParaAD);
        }

        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->$propiedadNombreZip) && $nombreArchivos->$propiedadNombreZip != '') {
            $nombreZip = $nombreArchivos->$propiedadNombreZip;
        } else {
            $nombreZip = $this->getNombreArchivo($documento, 'zip', $nombreParaAD);
        }

        if($nombrePdf && $nombreAttached && $nombreZip) {
            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
            $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, $nombreDelEstadoAD);
            if (!empty($estado)) {
                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                $xmlAD        = MainTrait::obtenerArchivoDeDisco(
                    'recepcion',
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                    $documento,
                    array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                );
                
                $rgAD        = MainTrait::obtenerArchivoDeDisco(
                    'recepcion',
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                    $documento,
                    array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                );

                $uuid         = Uuid::uuid4();
                $carpetaPadre = $uuidPadre !== null ? $uuidPadre->toString() . '/' : '';
                if(!empty($xmlAD) && !empty($rgAD)) {
                    $this->preparePDF($documento, ['est_archivo' => base64_encode($rgAD)], $nombrePdf, '', $carpetaPadre. $uuid->toString() . '/');
                    $this->prepareXML($documento, ['est_xml' => base64_encode($xmlAD)], $nombreAttached, $carpetaPadre . $uuid->toString() . '/');

                    return $this->crearZip($uuid, $nombreZip, $uuidPadre);
                }
            }
            return $this->generateErrorResponse($fraseErrorDescargas);
        } else {
            return $this->generateErrorResponse($fraseErrorDescargas);
        }
    }

    /**
     * Descarga los archivos PDF de los eventos DIAN de un documento electrónico.
     * 
     * @param int $cdoId ID del documento para el cual se debe descargar la RG del evento
     * @param Collection|RepCabeceraDocumentoDaop $documento Colección con información del documento electrónico
     * @param object $nombreArchivos Objeto conteniendo los nombres de archivos que se han guardado para el documento electrónico
     * @param string $campoNombreRG Nombre del campo o propiedad dentro de $nombreArchivo que debería contener el nombre de los archivos PDF para el documento electrónico
     * @param string $nombreParaRG Nombre que es utilizado como sufijo para el nombre de la RG del evento
     * @param string $nombreDelEstadoNot Nombre del estado sobre el cual se debe realizar la busqueda
     * @param UUID $uuidPadre UUID que es utilizado en la descarga de múltiples archivos, es null cuando se descarga un solo archivo
     * @return JsonResponse|Response|BinaryFileResponse
     */
    private function descargarRgEventosDian(int $cdoId, $documento, object $nombreArchivos, string $campoNombreRG, string $nombreParaRG, string $nombreDelEstadoNot, $uuidPadre = null) {
        $propiedadNombrePdf = 'pdf_' . $campoNombreRG;

        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->$propiedadNombrePdf) && $nombreArchivos->$propiedadNombrePdf != '') {
            $nombrePdf = $nombreArchivos->$propiedadNombrePdf;
        } else {
            $nombrePdf = $this->getNombreArchivo($documento, 'pdf', $nombreParaRG);
        }

        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
        $estado = MainTrait::obtenerEstadoDocumento('recepcion', $cdoId, $nombreDelEstadoNot);
        if (!empty($estado)) {
            $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : []; 
            $rgEvento             = MainTrait::obtenerArchivoDeDisco(
                'recepcion',
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                $documento,
                array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
            );
        }

        if(isset($rgEvento) && !empty($rgEvento)) {
            $carpetaPadre = $uuidPadre !== null ? $uuidPadre->toString() . '/' : '';
            $this->preparePDF($documento, ['est_archivo' => base64_encode($rgEvento)], $nombrePdf, '', $carpetaPadre);

            if ($uuidPadre == null)
                return $this->download($nombrePdf);
        }

        return $this->generateErrorResponse('No fue posible obtener el PDF para el evento porque no ha sido notificado.');
    }

    /**
     * Crea archivos zip para los diferentes eventos.
     * 
     * @param UUID $uuid Universal unique id Usado cuando son varios eventos los seleccionados
     * @param string $nombreZip Usado cuando es solo un evento el seleccionado
     * @param UUID $uuidPadre UUID que es utilizado en la descarga de múltiples archivos, es null cuando se descarga un solo archivo
     * @return JsonResponse|BinaryFileResponse|void
     */
    private function crearZip($uuid, $nombreZip, $uuidPadre = null){
        try {
            $carpetaPadre = $uuidPadre !== null ? $uuidPadre->toString() . '/' : '';
            $oZip = new ZipArchive();
            $oZip->open(storage_path('etl/descargas/' . $carpetaPadre . $nombreZip), ZipArchive::OVERWRITE | ZipArchive::CREATE);
            $options = array('remove_all_path' => true);
            $oZip->addGlob(storage_path('etl/descargas/' . $carpetaPadre . $uuid->toString() . '/') . '*.{pdf,xml,zip}', GLOB_BRACE, $options);
            $oZip->close();
            File::deleteDirectory(storage_path('etl/descargas/' . $carpetaPadre . $uuid->toString()));

            if($uuidPadre === null)
                return $this->download($nombreZip);
        } catch (\Exception $e) {
            // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
            // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
            $headers = [
                header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                header('X-Error-Status: 422'),
                header('X-Error-Message: Ocurri&oacute; un problema al intentar descargar el archivo')
            ];

            return response()->json([
                'message' => 'Error en la Descarga',
                'errors' => ['Ocurrio un problema al intentar generar el archivo zip con los documentos']
            ], 422, $headers);
        }
    }

    /**
     * Crea agendamientos de eventos DIAN para documentos.
     * 
     * @param array $arrAgendar Array con los IDs de los documentos a agendar
     * @param array $arrDescartar Array cuyos índices son los IDS de los documentos que se deben descartar
     * @param User $user usuario autenticado
     * @param string $proceso Proceso que debe ser agendado
     * @param object $motivoRechazo Json conteniendo el motivo de rechazo
     * @param array $estInformacionAdicional Array con información adicional para la creación del estado agendado
     * @return void
     */
    private function crearAgendamientos($arrAgendar, $arrDescartar, User $user, $proceso, $motivoRechazo = null, $estInformacionAdicional = null) {
        if(!empty($arrAgendar)) {
            // Define la columna que se deben tener en cuenta para poder dividir en bloques los procesamientos
            switch($proceso) {
                case 'RGETSTATUS':
                case 'RUBLACUSERECIBO':
                    $campoCantidad = 'bdd_cantidad_procesamiento_acuse';
                    break;
                case 'RUBLRECIBOBIEN':
                    $campoCantidad = 'bdd_cantidad_procesamiento_recibo';
                    break;
                case 'RUBLACEPTACION':
                    $campoCantidad = 'bdd_cantidad_procesamiento_aceptacion';
                    break;
                case 'RUBLRECHAZO':
                    $campoCantidad = 'bdd_cantidad_procesamiento_rechazo';
                    break;
                case 'RACEPTACIONT':
                    $campoCantidad = 'bdd_cantidad_procesamiento_getstatus';
                    break;
            }

            // Divide los ids a agendar de acuerdo a la cantidad de procesamiento definida para la base de datos
            foreach(array_chunk($arrAgendar, $user->getBaseDatos->$campoCantidad) as $grupo) {
                // Agendamiento
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $user->usu_id,
                    'bdd_id'                  => $user->bdd_id,
                    'age_proceso'             => $proceso,
                    'age_cantidad_documentos' => count($grupo),
                    'age_prioridad'           => null,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);

                // Crea el estado correspondiente para cada documento en el grupo
                foreach($grupo as $cdo_id) {
                    if(!array_key_exists($cdo_id, $arrDescartar)){
                        RepEstadoDocumentoDaop::create([
                            'cdo_id'                    => $cdo_id,
                            'est_estado'                => substr($proceso, 1),
                            'est_motivo_rechazo'        => $motivoRechazo,
                            'age_id'                    => $agendamiento->age_id,
                            'age_usu_id'                => $user->usu_id,
                            'est_informacion_adicional' => !empty($estInformacionAdicional) ? json_encode($estInformacionAdicional) : null,
                            'usuario_creacion'          => $user->usu_id,
                            'estado'                    => 'ACTIVO'
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Agenda uno o varios documentos para consultar su estado en la DIAN.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function agendarConsultaEstadoDian(Request $request) {
        $user           = auth()->user();
        $cdoIds         = explode(',', $request->cdoIds);
        $arrAgendar     = [];
        $arrDescartar   = [];
        $arrNoPermisos  = [];
        $arrNoExiste    = [];
        $countDocumentoNoElectronico = 0;

        // Verifica que el OFE tenga configurado el evento correspondiente en la columna ofe_recepcion_eventos_contratados_titulo_valor
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('ofe_id', $request->ofeId)
            ->where('estado', 'ACTIVO')
            ->where('ofe_recepcion', 'SI')
            ->whereJsonContains('ofe_recepcion_eventos_contratados_titulo_valor',['evento'=>'GETSTATUS'])
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['El evento GETSTATUS no está autorizado para el OFE']
            ], 403);

        if(!empty($cdoIds)) {
            // Verifica para cada documento en el grupo si tiene estado GETSTATUS éxitoso
            foreach ($cdoIds as $cdo_id) {
                $documento = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_origen', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo'])
                    ->where('cdo_id', $cdo_id)
                    ->with([
                        'getConfiguracionProveedor' => function($query) use ($request) {
                            $query = $this->recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $request->ofeId);
                        },
                        'getDocumentoAprobado:est_id,cdo_id',
                        'getDocumentoAprobadoNotificacion:est_id,cdo_id'
                    ])
                    ->whereHas('getConfiguracionProveedor', function($query) use ($request) {
                        $query = $this->recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $request->ofeId);
                    })
                    ->first();

                if ($documento) {
                    if ($documento->cdo_origen == 'NO-ELECTRONICO') {
                        $countDocumentoNoElectronico++;
                    } else {
                        $estado = RepEstadoDocumentoDaop::select(['est_id', 'cdo_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'GETSTATUS')
                            ->where('est_resultado', 'EXITOSO')
                            ->with(['getCabeceraDocumentosDaop:cdo_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo'])
                            ->first();
    
                        if($estado) {
                            $arrDescartar[$cdo_id] = $estado->getCabeceraDocumentosDaop->cdo_clasificacion . $estado->getCabeceraDocumentosDaop->rfa_prefijo . $estado->getCabeceraDocumentosDaop->cdo_consecutivo;
                        } else {
                            $arrAgendar[] = $cdo_id;
                        }
                    }
                } else {
                    $arrNoExiste[$cdo_id] = $cdo_id;
                }
            }

            if ($countDocumentoNoElectronico == count($cdoIds)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Solicitud no procesada, los documentos procesados corresponden a Documentos No Electrónicos']
                ], 422);
            }

            if (!empty($arrNoExiste)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados con IDs: [' . implode(', ', $arrNoExiste) . '] no existen.']
                ], 422);
            }

            $this->crearAgendamientos($arrAgendar, $arrDescartar, $user, 'RGETSTATUS');
        }

        if(empty($arrAgendar)) {
            return response()->json([
                'message' => 'Solicitud procesada pero NO se agendó ningún documento, esto puede ser por tratarse documentos NO Electrónicos, no tener acceso a los documentos, tener consulta exitosa a la DIAN o no existir en la data operativa'
            ], 200); 
        } else {
            $noAgendados = '';
            if(!empty($arrDescartar)) {
                $noAgendados = 'Los siguientes documentos no se agendaron por tener una consulta a la DIAN éxitosa: ' . implode(', ', $arrDescartar);
            }

            $noPermisos = '';
            if(!empty($arrNoPermisos)) {
                $noPermisos = 'Los siguientes IDs de documentos no se pudieron consultar posiblemente por no tener acceso a ellos o no existir en la data operativa: ' . implode(', ', $arrNoPermisos);
            }

            return response()->json([
                'message' => 'Solicitud procesada, documentos agendados' . (($noAgendados != '') ? '. ' . $noAgendados : '') . (($noPermisos != '') ? '. ' . $noPermisos : '')
            ], 201); 
        }
    }

    /**
     * Agenda uno o varios documentos para realizar el acuse de recibo.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function agendarAcuseRecibo(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }
        
        $user               = auth()->user();
        $cdoIds             = array_unique(explode(',', $request->cdoIds));
        $arrDescartar       = [];
        $arrMotivosDescarte = [];
        $arrAgendar         = [];
        $arrNoExiste        = [];
        $estInformacionAdicional     = null;
        $countDocumentoNoElectronico = 0;

        // Verifica que el OFE tenga configurado el evento correspondiente en la columna ofe_recepcion_eventos_contratados_titulo_valor
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('ofe_id', $request->ofeId)
            ->where('estado', 'ACTIVO')
            ->where('ofe_recepcion', 'SI')
            ->whereJsonContains('ofe_recepcion_eventos_contratados_titulo_valor',['evento'=>'ACUSERECIBO'])
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['El evento ACUSERECIBO no está autorizado para el OFE']
            ], 403);

        if(!empty($cdoIds)) {
            // Verifica para cada documento en el grupo que NO tenga estado UBLACUSERECIBO éxitoso y que SI tenga estado GETSTATUS éxitoso
            foreach ($cdoIds as $cdo_id) {
                $documento = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'pro_id'])
                    ->where('cdo_id', $cdo_id)
                    ->where('cdo_origen', '!=', 'NO-ELECTRONICO')
                    ->where(function($query) {
                        $query->whereHas('getDocumentoAprobado')
                            ->orWhereHas('getDocumentoAprobadoNotificacion');
                    })
                    ->doesntHave('getAcuseRecibo')
                    ->doesntHave('getUblAcuseReciboEnProceso')
                    ->first();

                if(!$documento) {
                    $docu = RepCabeceraDocumentoDaop::select(['cdo_clasificacion', 'cdo_origen', 'rfa_prefijo', 'cdo_consecutivo'])
                        ->find($cdo_id);

                    if ($docu) {
                        if ($docu && $docu->cdo_origen == 'NO-ELECTRONICO') {
                            $countDocumentoNoElectronico++;
                            $arrMotivosDescarte[]  = 'El documento procesado corresponde a Documento No Electrónicos';
                        } elseif ($docu->cdo_clasificacion == 'DS' || $docu->cdo_clasificacion == 'DS_NC') {
                            $countDocumentoNoElectronico++;
                            $arrMotivosDescarte[]  = 'El documento procesado, corresponde a Documento Soporte';
                        } else {
                            $arrMotivosDescarte[]  = 'El documento no cuenta con estado GETSTATUS exitoso o ya tiene Acuse de Recibo.';
                        }
                        $arrDescartar[$cdo_id] = $docu->cdo_clasificacion . $docu->rfa_prefijo . $docu->cdo_consecutivo;
                    } else {
                        $arrNoExiste[$cdo_id] = $cdo_id;
                    }
                } else {
                    if($documento->cdo_clasificacion != 'FC') {
                        $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                        $arrMotivosDescarte[]  = 'El documento no es una una Factura de Venta.';
                    } else {
                        if ($request->filled('proceso_automatico') && $request->proceso_automatico) {
                            $arrAgendar[] = $cdo_id;
                        } else {
                            // Verifica que el usuario autenticado, el ofe y el proveedor frente a la autorización de eventos DIAN
                            $permisoAcceso = TenantTrait::usuarioAutorizacionesEventosDian($request->ofeId, $documento->pro_id, $user->usu_id, 'use_acuse_recibo');
                            if(empty($permisoAcceso)) {
                                $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                                $arrMotivosDescarte[]  = 'El usuario no tiene autorización para ejecutar este evento';
                            } else {
                                $arrAgendar[] = $cdo_id;
                            }
                        }
                    }
                }
            }

            if ($countDocumentoNoElectronico == count($cdoIds)) {
                return response()->json([
                    'message' => 'Error',
                    'errors'  => ['Los documentos procesados corresponden a Documentos No Electrónicos']
                ], 422);
            }

            if (!empty($arrNoExiste)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados con IDs: [' . implode(', ', $arrNoExiste) . '] no existen en la data operativa.']
                ], 422);
            }

            $dataAcuse = [
                'observacion' => $request->observacion
            ];

            if ($request->filled('proceso_automatico') && $request->proceso_automatico)
                $estInformacionAdicional['automatico'] = true;

            $this->crearAgendamientos($arrAgendar, $arrDescartar, $user, 'RUBLACUSERECIBO', $dataAcuse, $estInformacionAdicional);
        }

        $noAgendados = '';
        if(!empty($arrDescartar)) {
            $noAgendados = 'Documentos no agendados: [' . implode(', ', $arrDescartar) . ']. Motivos: [' . implode(', ', array_unique($arrMotivosDescarte)) . ']';
        }
        if(empty($arrAgendar)) {
            return response()->json([
                'message' => 'Error',
                'errors'  => ['No se agendó ningún documento. ' . $noAgendados]
            ], 422); 
        } else {
            if(!empty($noAgendados))
                return response()->json([
                    'message' => 'Documentos agendados, algunos documento no fueron agendados. ' . (($noAgendados != '') ? '. ' . $noAgendados : '')
                ], 201);
            else 
                return response()->json([
                    'message' => 'Documentos agendados con exito.'
                ], 201);
        }
    }

    /**
     * Permite definir la expresión regular a utilizar en campos de tipo texto para el proyecto especial de Validación de FNC.
     *
     * @param \stdClass $configCampo Objeto que contiene la configuración del campo
     * @return string Expresión regular
     */
    private function definirExpresionParaTexto(\stdClass $configCampo): string {
        if(isset($configCampo->numerico) && isset($configCampo->permite_espacios)) {
            if($configCampo->numerico == 'SI' && $configCampo->permite_espacios == 'SI')
                return "regex:/^[0-9 ]+$/";
            else if($configCampo->numerico == 'SI' && $configCampo->permite_espacios == 'NO')
                return "regex:/^[0-9]+$/";
            else
                return "regex:/[[:alnum:]]$/";
        } else {
            return "regex:/[[:alnum:]]$/";
        }
    }

    /**
     * Valida la información recibida en el request para el evento de FNC.
     *
     * @param Request $request Parámetros recibidos
     * @param array $configEventoFnc Información de la configuración del evento
     * @return array Array conteniendo los errores de la validación o los parámetros que pasan a información adicional
     */
    private function validarOfeRecepcionFncEvento(Request $request, array $configEventoFnc): array {
        $camposFnc                = [];
        $reglasRecepcionFncEvento = [];
        $erroresValidacion        = [];
        $parametrosRequest        = $request->filled('camposFnc') ? json_decode($request->camposFnc, true) : [];

        foreach($configEventoFnc as $config) {
            $reglas      = [];
            $campo       = str_replace(' ', '_', $config->campo);
            $campo       = str_replace('.', '', $config->campo);
            $nombreCampo = TenantTrait::sanitizarCadena($campo);

            switch($config->tipo) {
                case 'texto':
                case 'textarea':
                    $reglas[$nombreCampo][] = 'string';
                    $reglas[$nombreCampo][] = $this->definirExpresionParaTexto($config);
                    break;

                case 'por_defecto':
                    $reglas[$nombreCampo][] = 'string';
                    break;

                case 'numerico':
                    $reglas[$nombreCampo][] = 'numeric';
                    
                    if(strstr($config->longitud, '.') !== false) {
                        $composicion = explode('.', $config->longitud);
                        $reglas[$nombreCampo][] = 'regex:/^[0-9]{1,' . $composicion[0] . '}(\.[0-9]{1,' . $composicion[1] . '})$/';
                    }

                    break;

                case 'multiple':
                    $reglas[$nombreCampo][] = 'string';
                    $reglas[$nombreCampo][] = 'in:' . implode(',', $config->opciones);
                    break;

                case 'parametrico':
                    $opciones = [];
                    if($config->tabla == 'pry_datos_parametricos_validacion') {
                        $opciones = $this->datosParametricosValidacionService->listarDatosParametricosValidacion($config->campo, $config->clasificacion, true);

                        if(empty($opciones))
                            $erroresValidacion = array_merge($erroresValidacion, [
                                'No se encontraron Datos Paramétricos de Validación en estado [ACTIVO] para la clasificación [' . $config->clasificacion . '] del campo [' . $config->campo . ']'
                            ]);
                    }

                    $reglas[$nombreCampo][] = 'string';
                    if(!empty($opciones))
                        $reglas[$nombreCampo][] = 'in:' . implode(',', $opciones);
                        
                    break;

                case 'date':
                    $reglas[$nombreCampo][] = 'date';
                    $reglas[$nombreCampo][] = 'date_format:Y-m-d';
                    break;
            }

            if(isset($config->obligatorio) && $config->obligatorio == 'SI' && (!$request->filled('accion') || ($request->filled('accion') && $request->accion != 'enproceso')))
                $reglas[$nombreCampo][] = 'required';
            else
                $reglas[$nombreCampo][] = 'nullable';

            if(isset($config->longitud) && !empty($config->longitud))
                $reglas[$nombreCampo][] = 'max:' . $config->longitud;

            if(isset($config->exacta) && $config->exacta == 'SI')
                $reglas[$nombreCampo][] = 'min:' . (isset($config->longitud) && !empty($config->longitud) ? $config->longitud : 0);

            if(!empty($reglas))
                $reglasRecepcionFncEvento[$nombreCampo] = implode('|', $reglas[$nombreCampo]);
            else
                $reglasRecepcionFncEvento[$nombreCampo] = 'nullable';

            $parametroRecibido = '';
            foreach($parametrosRequest as $parametro) {
                if(empty($parametroRecibido) && array_key_exists($nombreCampo, $parametro)) {
                    $parametroRecibido = $parametro;

                    $validador = Validator::make($parametro, [$nombreCampo => $reglasRecepcionFncEvento[$nombreCampo]]);
                    if ($validador->fails())
                        $erroresValidacion = array_merge($erroresValidacion, $validador->errors()->all());
                }
            }

            $camposFnc[] = [
                'campo'       => $nombreCampo,
                'descripcion' => $config->campo,
                'valor'       => is_array($parametroRecibido) && array_key_exists($nombreCampo, $parametroRecibido) ? $parametroRecibido[$nombreCampo] : null
            ];
        }

        if (!empty($erroresValidacion)) {
            return [
                'errors'  => $erroresValidacion
            ];
        } else {
            return [
                'campos_fnc' => $camposFnc
            ];
        }
    }

    /**
     * Agenda uno o varios documentos para realizar el acuse de recibo.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function agendarReciboBien(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }
        
        $user               = auth()->user();
        $cdoIds             = array_unique(explode(',', $request->cdoIds));
        $arrDescartar       = [];
        $arrMotivosDescarte = [];
        $arrAgendar         = [];
        $arrNoExiste        = [];
        $estInformacionAdicional     = null;
        $countDocumentoNoElectronico = 0;

        // Verifica que el OFE tenga configurado el evento correspondiente en la columna ofe_recepcion_eventos_contratados_titulo_valor
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo', 'ofe_recepcion_fnc_configuracion'])
            ->where('ofe_id', $request->ofeId)
            ->where('estado', 'ACTIVO')
            ->where('ofe_recepcion', 'SI')
            ->whereJsonContains('ofe_recepcion_eventos_contratados_titulo_valor', ['evento' => 'RECIBOBIEN'])
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['El evento RECIBOBIEN no está autorizado para el OFE']
            ], 403);

        if($ofe->ofe_recepcion_fnc_activo == 'SI') {
            TenantTrait::GetVariablesSistemaTenant();
            $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
            $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();

            if(!empty($ofe->ofe_recepcion_fnc_configuracion)) {
                $ofeRecepcionFncConfiguracion = json_decode($ofe->ofe_recepcion_fnc_configuracion);
                
                if(isset($ofeRecepcionFncConfiguracion->evento_recibo_bien) && !empty($ofeRecepcionFncConfiguracion->evento_recibo_bien)) {
                    $validarOfeRecepcionFncEvento = $this->validarOfeRecepcionFncEvento($request, $ofeRecepcionFncConfiguracion->evento_recibo_bien);

                    if(array_key_exists('errors', $validarOfeRecepcionFncEvento) && !empty($validarOfeRecepcionFncEvento['errors']))
                        return response()->json([
                            'message' => 'Error al Procesar la Acción',
                            'errors'  => $validarOfeRecepcionFncEvento['errors']
                        ], 409);
                    else
                        $estInformacionAdicional['campos_adicionales'] = $validarOfeRecepcionFncEvento['campos_fnc'];
                }
            }
        }

        if(!empty($cdoIds)) {
            // Verifica para cada documento en el grupo que NO tenga estado UBLRECIBOBIEN éxitoso y que SI tenga estado GETSTATUS éxitoso
            $arrGtrIds = [];
            foreach ($cdoIds as $cdo_id) {
                $documento = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'pro_id', 'gtr_id'])
                    ->where('cdo_id', $cdo_id)
                    ->where('cdo_origen', '!=', 'NO-ELECTRONICO')
                    ->where(function($query) {
                        $query->whereHas('getDocumentoAprobado')
                            ->orWhereHas('getDocumentoAprobadoNotificacion');
                    })
                    ->doesntHave('getReciboBien')
                    ->doesntHave('getUblReciboBienEnProceso')
                    ->when($ofe->ofe_recepcion_fnc_activo == 'SI', function($query) {
                        return $query->with([
                            'getConfiguracionProveedor:pro_id',
                            'getConfiguracionProveedor.getProveedorGruposTrabajo' => function($query) {
                                $query->select(['pro_id', 'gtr_id'])
                                    ->where('estado', 'ACTIVO')
                                    ->with([
                                        'getGrupoTrabajo:gtr_id,estado'
                                    ]);
                            }
                        ]);
                    })
                    ->first();

                if(!$documento) {
                    $docu = RepCabeceraDocumentoDaop::select(['cdo_clasificacion', 'cdo_origen', 'rfa_prefijo', 'cdo_consecutivo'])
                        ->find($cdo_id);

                    if ($docu) {
                        if ($docu && $docu->cdo_origen == 'NO-ELECTRONICO') {
                            $countDocumentoNoElectronico++;
                            $arrMotivosDescarte[] = 'El documento procesado corresponde a Documentos No Electrónicos';
                        } elseif ($docu->cdo_clasificacion == 'DS' || $docu->cdo_clasificacion == 'DS_NC') {
                            $arrMotivosDescarte[]  = 'El documento procesado, corresponde a Documento Soporte';
                        } else {
                            $arrMotivosDescarte[]  = 'Documento no cuenta con estado GETSTATUS exitoso o ya cuenta con Recibo del Bien y/o Servicio';
                        }
                        $arrDescartar[$cdo_id] = $docu->cdo_clasificacion . $docu->rfa_prefijo . $docu->cdo_consecutivo;
                        $arrMotivosDescarte[]  = 'Documento no es una una Factura de Venta';
                    } else {
                        $arrNoExiste[$cdo_id] = $cdo_id;
                    }
                } else {
                    // Si ofe_recepcion_fnc_activo es SI todos los documentos deben tener un único grupo de trabajo asignada o que el proveedor este relacionado solamente con un único grupo de trabajo
                    // Y que el grupo de trabajo sea el mismo para todos los documentos (ya sea por grupo asignado al documento o pertenencia del proveedor)
                    if($ofe->ofe_recepcion_fnc_activo == 'SI') {
                        if(!empty($documento->gtr_id))
                            $arrGtrIds[] = $documento->gtr_id;
                        else {
                            $gruposTrabajoProveedor = $documento->getConfiguracionProveedor->getProveedorGruposTrabajo->filter(function($grupoTrabajo) {
                                return $grupoTrabajo->getGrupoTrabajo->estado == 'ACTIVO';
                            })->pluck('gtr_id')->values()->toArray();

                            if(count($gruposTrabajoProveedor) >= 1) {
                                $gruposProveedor = !empty($gruposTrabajoProveedor) ? $gruposTrabajoProveedor : [];
                                $arrGtrIds = array_merge($arrGtrIds, $gruposProveedor);
                            }
                        }
                    }

                    if($documento->cdo_clasificacion != 'FC') {
                        $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                    } else {
                        if ($request->filled('proceso_automatico') && $request->proceso_automatico) {
                            $arrAgendar[] = $cdo_id;
                        } else {
                            // Verifica que el usuario autenticado, el ofe y el proveedor frente a la autorización de eventos DIAN
                            $permisoAcceso = TenantTrait::usuarioAutorizacionesEventosDian($request->ofeId, $documento->pro_id, $user->usu_id, 'use_recibo_bien');
                            if(empty($permisoAcceso)) {
                                $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                                $arrMotivosDescarte[]  = 'El usuario no tiene autorización para ejecutar este evento';
                            } else {
                                $arrAgendar[] = $cdo_id;
                            }
                        }
                    }
                }
            }

            if($ofe->ofe_recepcion_fnc_activo == 'SI') {
                $arrGtrIds = array_unique($arrGtrIds);

                if(count($arrGtrIds) > 1)
                    return response()->json([
                        'message' => 'Error',
                        'errors' => ['Los documentos procesados y/o sus proveedores pertenecen a diferentes ' . (isset($gruposTrabajo->plural) && !empty($gruposTrabajo->plural) ? $gruposTrabajo->plural : ' Grupos de Trabajo')]
                    ], 422);
            }

            if ($countDocumentoNoElectronico == count($cdoIds)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados corresponden a Documentos No Electrónicos']
                ], 422);
            }

            if (!empty($arrNoExiste)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados con IDs: [' . implode(', ', $arrNoExiste) . '] no existen en la data operativa.']
                ], 422);
            }

            $dataReciboBien = [
                'observacion' => $request->observacion
            ];

            if ($request->filled('origen'))
                $estInformacionAdicional['origen'] = $request->origen;

            if ($request->filled('proceso_automatico') && $request->proceso_automatico)
                $estInformacionAdicional['automatico'] = true;

            $this->crearAgendamientos($arrAgendar, $arrDescartar, $user, 'RUBLRECIBOBIEN', $dataReciboBien, $estInformacionAdicional);
        }

        $noAgendados = '';
        if(!empty($arrDescartar)) {
            $noAgendados = 'Documentos no agendados: [' . implode(', ', $arrDescartar) . ']. Motivos: [' . implode(', ', array_unique($arrMotivosDescarte)) . ']';
        }

        if(empty($arrAgendar)) {
            return response()->json([
                'message' => 'Error',
                'errors'  => ['No se agendó ningún documento. ' . $noAgendados]
            ], 422);
        } else {
            if(!empty($noAgendados))
                return response()->json([
                    'message' => 'Documentos agendados, algunos documento no fueron agendados. ' . (($noAgendados != '') ? '. ' . $noAgendados : '')
                ], 201);
            else 
                return response()->json([
                    'message' => 'Documentos agendados con exito.'
                ], 201);
        }
    }

    /**
     * Agenda uno o varios documentos para realizar la aceptación expresa.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function agendarAceptacionExpresa(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }
        
        $user               = auth()->user();
        $cdoIds             = array_unique(explode(',', $request->cdoIds));
        $arrDescartar       = [];
        $arrMotivosDescarte = [];
        $arrAgendar         = [];
        $arrNoExiste        = [];
        $countDocumentoNoElectronico = 0;

        // Verifica que el OFE tenga configurado el evento correspondiente en la columna ofe_recepcion_eventos_contratados_titulo_valor
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('ofe_id', $request->ofeId)
            ->where('estado', 'ACTIVO')
            ->where('ofe_recepcion', 'SI')
            ->whereJsonContains('ofe_recepcion_eventos_contratados_titulo_valor',['evento'=>'ACEPTACION'])
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['El evento ACEPTACION no está autorizado para el OFE']
            ], 403);

        if(!empty($cdoIds)) {
            // Verifica para cada documento en el grupo que NO no tenga un estado ACEPTACION, ACEPTACIONT, RECHAZO, UBLACEPTACION o ejecución en ENPROCESO y que SI tenga estado GETSTATUS éxitoso
            foreach ($cdoIds as $cdo_id) {
                $documento = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'pro_id'])
                    ->where('cdo_id', $cdo_id)
                    ->where('cdo_origen', '!=', 'NO-ELECTRONICO')
                    ->where(function($query) {
                        $query->whereHas('getDocumentoAprobado')
                            ->orWhereHas('getDocumentoAprobadoNotificacion');
                    })
                    ->where(function($query) {
                        $query->doesntHave('getAceptado')
                            ->doesntHave('getAceptadoT')
                            ->doesntHave('getRechazado')
                            ->doesntHave('getUblRechazoEnProceso')
                            ->doesntHave('getUblAceptadoEnProceso');
                    })
                    ->first();

                if(!$documento) {
                    $docu = RepCabeceraDocumentoDaop::select(['cdo_clasificacion', 'cdo_origen', 'rfa_prefijo', 'cdo_consecutivo'])
                        ->find($cdo_id);

                    if ($docu) {
                        if ($docu && $docu->cdo_origen == 'NO-ELECTRONICO') {
                            $countDocumentoNoElectronico++;
                            $arrMotivosDescarte[] = 'El documento procesado corresponde a Documentos No Electrónicos';
                        } elseif ($docu->cdo_clasificacion == 'DS' || $docu->cdo_clasificacion == 'DS_NC') {
                            $arrMotivosDescarte[]  = 'El documento procesado, corresponde a Documento Soporte';
                        } else {
                            $arrMotivosDescarte[]  = 'Documento no cuenta con estado GETSTATUS exitoso o ya cuenta con estados DIAN Posteriores';
                        }
                        $arrDescartar[$cdo_id] = $docu->cdo_clasificacion . $docu->rfa_prefijo . $docu->cdo_consecutivo;
                    } else {
                        $arrNoExiste[$cdo_id] = $cdo_id;
                    }
                } else {
                    if($documento->cdo_clasificacion != 'FC') {
                        $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                        $arrMotivosDescarte[]  = 'Documento no es una una Factura de Venta';
                    } else {
                        // Verifica que el usuario autenticado, el ofe y el proveedor frente a la autorización de eventos DIAN
                        $permisoAcceso = TenantTrait::usuarioAutorizacionesEventosDian($request->ofeId, $documento->pro_id, $user->usu_id, 'use_aceptacion_expresa');
                        if(empty($permisoAcceso)) {
                            $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                            $arrMotivosDescarte[]  = 'El usuario no tiene autorización para ejecutar este evento';
                        } else {
                            $arrAgendar[] = $cdo_id;
                        }
                    }
                }
            }

            if ($countDocumentoNoElectronico == count($cdoIds)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados corresponden a Documentos No Electrónicos']
                ], 422);
            }

            if (!empty($arrNoExiste)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados con IDs: [' . implode(', ', $arrNoExiste) . '] no existen en la data operativa.']
                ], 422);
            }

            $dataAceptacion = [
                'observacion' => $request->observacion
            ];

            $this->crearAgendamientos($arrAgendar, $arrDescartar, $user, 'RUBLACEPTACION', $dataAceptacion);
        }

        $noAgendados = '';
        if(!empty($arrDescartar)) {
            $noAgendados = 'Documentos no agendados: [' . implode(', ', $arrDescartar) . ']. Motivos: [' . implode(', ', array_unique($arrMotivosDescarte)) . ']';
        }
        if(empty($arrAgendar)) {
            return response()->json([
                'message' => 'Error',
                'errors'  => ['No se agendó ningún documento. ' . $noAgendados]
            ], 422);
        } else {
            if(!empty($noAgendados))
                return response()->json([
                    'message' => 'Documentos agendados, algunos documento no fueron agendados. ' . (($noAgendados != '') ? '. ' . $noAgendados : '')
                ], 201);
            else 
                return response()->json([
                    'message' => 'Documentos agendados con exito.'
                ], 201);
        }
    }

    /**
     * Agenda uno o varios documentos para realizar su rechazo.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function agendarRechazo(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        $user               = auth()->user();
        $cdoIds             = array_unique(explode(',', $request->cdoIds));
        $arrDescartar       = [];
        $arrMotivosDescarte = [];
        $arrAgendar         = [];
        $arrNoExiste        = [];
        $countDocumentoNoElectronico = 0;

        if(!$request->has('conceptoRechazo') || ($request->has('conceptoRechazo') && empty($request->conceptoRechazo))) {
            return response()->json([
                'message' => 'Error en Rechazo de Documentos',
                'errors'  => ['No se envió el concepto de rechazo']
            ], 422);
        }

        // Verifica que el OFE tenga configurado el evento correspondiente en la columna ofe_recepcion_eventos_contratados_titulo_valor
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('ofe_id', $request->ofeId)
            ->where('estado', 'ACTIVO')
            ->where('ofe_recepcion', 'SI')
            ->whereJsonContains('ofe_recepcion_eventos_contratados_titulo_valor',['evento'=>'RECHAZO'])
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['El evento RECLAMO o RECHAZO no está autorizado para el OFE']
            ], 403);

        if(!empty($cdoIds)) {
            // Verifica para cada documento en el grupo que NO no tenga un estado ACEPTACION, ACEPTACIONT, RECHAZO, UBLRECHAZO o ejecución en ENPROCESO y que SI tenga estado GETSTATUS éxitoso
            foreach ($cdoIds as $cdo_id) {
                $documento = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'pro_id'])
                    ->where('cdo_id', $cdo_id)
                    ->where('cdo_origen', '!=', 'NO-ELECTRONICO')
                    ->where(function($query) {
                        $query->whereHas('getDocumentoAprobado')
                            ->orWhereHas('getDocumentoAprobadoNotificacion');
                    })
                    ->where(function($query) {
                        $query->doesntHave('getAceptado')
                            ->doesntHave('getAceptadoT')
                            ->doesntHave('getRechazado')
                            ->doesntHave('getUblRechazoEnProceso')
                            ->doesntHave('getUblAceptadoEnProceso');
                    })
                    ->first();

                if(!$documento) {
                    $docu = RepCabeceraDocumentoDaop::select(['cdo_clasificacion', 'cdo_origen', 'rfa_prefijo', 'cdo_consecutivo'])
                        ->find($cdo_id);

                    if ($docu) {
                        if ($docu && $docu->cdo_origen == 'NO-ELECTRONICO') {
                            $countDocumentoNoElectronico++;
                            $arrMotivosDescarte[] = 'El documento procesado corresponde a Documentos No Electrónicos';
                        } elseif ($docu->cdo_clasificacion == 'DS' || $docu->cdo_clasificacion == 'DS_NC') {
                            $arrMotivosDescarte[]  = 'El documento procesado, corresponde a Documento Soporte';
                        } else {
                            $arrMotivosDescarte[]  = 'Documento no cuenta con estado GETSTATUS exitoso o ya cuenta con estados DIAN Posteriores';
                        }
                        $arrDescartar[$cdo_id] = $docu->cdo_clasificacion . $docu->rfa_prefijo . $docu->cdo_consecutivo;
                    } else {
                        $arrNoExiste[$cdo_id] = $cdo_id;
                    }
                } else {
                    if($documento->cdo_clasificacion != 'FC') {
                        $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                        $arrMotivosDescarte[]  = 'Documento no es una una Factura de Venta';
                    } else {
                        // Verifica que el usuario autenticado, el ofe y el proveedor frente a la autorización de eventos DIAN
                        $permisoAcceso = TenantTrait::usuarioAutorizacionesEventosDian($request->ofeId, $documento->pro_id, $user->usu_id, 'use_reclamo');
                        if(empty($permisoAcceso)) {
                            $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                            $arrMotivosDescarte[]  = 'El usuario no tiene autorización para ejecutar este evento';
                        } else {
                            $arrAgendar[] = $cdo_id;
                        }
                    }
                }
            }

            if ($countDocumentoNoElectronico == count($cdoIds)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados corresponden a Documentos No Electrónicos']
                ], 422);
            }

            if (!empty($arrNoExiste)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados con IDs: [' . implode(', ', $arrNoExiste) . '] no existen en la data operativa.']
                ], 422);
            }

            //Trae la descripción del concepto rechazo vigente
            $arrConsulta = [];
            $consulta = ParametrosConceptoRechazo::select(['cre_id','cre_codigo','cre_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('cre_codigo', $request->conceptoRechazo)
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('cre_codigo')
                ->map(function ($item) use (&$arrConsulta) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente']){
                        $arrConsulta[] = $vigente['registro'];
                    }
                });

            $strDescripcionRechazo = $arrConsulta[0]->cre_descripcion;

            $dataRechazo = [
                'concepto_rechazo'    => $request->conceptoRechazo,
                'descripcion_rechazo' => $strDescripcionRechazo,
                'motivo_rechazo'      => $request->motivoRechazo
            ];

            $this->crearAgendamientos($arrAgendar, $arrDescartar, $user, 'RUBLRECHAZO', $dataRechazo);
        }

        $noAgendados = '';
        if(!empty($arrDescartar)) {
            $noAgendados = 'Documentos no agendados: [' . implode(', ', $arrDescartar) . ']. Motivos: [' . implode(', ', array_unique($arrMotivosDescarte)) . ']';
        }
        if(empty($arrAgendar)) {
            return response()->json([
                'message' => 'Error',
                'errors'  => ['No se agendó ningún documento. ' . $noAgendados]
            ], 422);
        } else {
            if(!empty($noAgendados))
                return response()->json([
                    'message' => 'Documentos agendados, algunos documento no fueron agendados. ' . (($noAgendados != '') ? '. ' . $noAgendados : '')
                ], 201);
            else 
                return response()->json([
                    'message' => 'Documentos agendados con exito.'
                ], 201);
        }
    }

    /**
     * Agenda uno o varios documentos para realizar la aceptación tacita.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function agendarAceptacionTacita(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        $user               = auth()->user();
        $cdoIds             = array_unique(explode(',', $request->cdoIds));
        $arrDescartar       = [];
        $arrMotivosDescarte = [];
        $arrAgendar         = [];
        $arrNoExiste        = [];
        $countDocumentoNoElectronico = 0;

        // Verifica que el OFE tenga configurado el evento correspondiente en la columna ofe_recepcion_eventos_contratados_titulo_valor
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('ofe_id', $request->ofeId)
            ->where('estado', 'ACTIVO')
            ->where('ofe_recepcion', 'SI')
            ->whereJsonContains('ofe_recepcion_eventos_contratados_titulo_valor',['evento'=>'ACEPTACIONT'])
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['El evento ACEPTACIONT no está autorizado para el OFE']
            ], 403);

        if(!empty($cdoIds)) {
            // Verifica para cada documento en el grupo que NO no tenga un estado ACEPTACION, ACEPTACIONT, RECHAZO, UBLACEPTACIONT o ejecución en ENPROCESO y que SI tenga estado GETSTATUS éxitoso
            foreach ($cdoIds as $cdo_id) {
                $documento = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'pro_id'])
                    ->where('cdo_id', $cdo_id)
                    ->where('cdo_origen', '!=', 'NO-ELECTRONICO')
                    ->where(function($query) {
                        $query->whereHas('getDocumentoAprobado')
                            ->orWhereHas('getDocumentoAprobadoNotificacion');
                    })
                    ->where(function($query) {
                        $query->doesntHave('getAceptado')
                            ->doesntHave('getAceptadoT')
                            ->doesntHave('getRechazado')
                            ->doesntHave('getAceptadoTEnProceso');
                    })
                    ->first();

                if(!$documento) {
                    $docu = RepCabeceraDocumentoDaop::select(['cdo_clasificacion', 'cdo_origen', 'rfa_prefijo', 'cdo_consecutivo'])
                        ->find($cdo_id);

                    if ($docu) {
                        if ($docu && $docu->cdo_origen == 'NO-ELECTRONICO') {
                            $countDocumentoNoElectronico++;
                            $arrMotivosDescarte[] = 'El documento procesado corresponde a Documentos No Electrónicos';
                        } elseif ($docu->cdo_clasificacion == 'DS' || $docu->cdo_clasificacion == 'DS_NC') {
                            $arrMotivosDescarte[]  = 'El documento procesado, corresponde a Documento Soporte';
                        } else {
                            $arrMotivosDescarte[] = 'Documento no cuenta con estado GETSTATUS exitoso o ya cuenta con estados DIAN Posteriores';
                        }
                        $arrDescartar[$cdo_id] = $docu->cdo_clasificacion . $docu->rfa_prefijo . $docu->cdo_consecutivo;
                    } else {
                        $arrNoExiste[$cdo_id] = $cdo_id;
                    }
                } else {
                    if($documento->cdo_clasificacion != 'FC') {
                        $arrDescartar[$cdo_id] = $documento->cdo_clasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo;
                        $arrMotivosDescarte[]  = 'Documento no es una una Factura de Venta';
                    } else
                        $arrAgendar[] = $cdo_id;
                }
            }

            if ($countDocumentoNoElectronico == count($cdoIds)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados corresponden a Documentos No Electrónicos']
                ], 422);
            }

            if (!empty($arrNoExiste)) {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados con IDs: [' . implode(', ', $arrNoExiste) . '] no existen en la data operativa.']
                ], 422);
            }

            $this->crearAgendamientos($arrAgendar, $arrDescartar, $user, 'RACEPTACIONT');
        }

        $noAgendados = '';
        if(!empty($arrDescartar)) {
            $noAgendados = 'Documentos no agendados: [' . implode(', ', $arrDescartar) . ']. Motivos: [' . implode(', ', array_unique($arrMotivosDescarte)) . ']';
        }

        if(empty($arrAgendar)) {
            return response()->json([
                'message' => 'Error',
                'errors'  => ['No se agendó ningún documento. ' . $noAgendados]
            ], 422);
        } else {
            if(!empty($noAgendados))
                return response()->json([
                    'message' => 'Documentos agendados, algunos documentos no fueron agendados. ' . (($noAgendados != '') ? '. ' . $noAgendados : '')
                ], 201);
            else 
                return response()->json([
                    'message' => 'Documentos agendados con exito.'
                ], 201);
        }
    }

    /**
     * Procesamiento de documentos manuales enviados en el request en un FILE.
     * 
     * Los archivos se reciben por parejas (XML y PDF) para su procesamiento
     *
     * @param  Request Parámetros de la petición
     * @return JsonResponse
     */
    public function documentosManuales(Request $request) {
        $documentosManuales = new DocumentosManuales();
        return $documentosManuales->documentosManuales($request);
    }

    /**
     * Procesamiento de documentos manuales enviados en el request en un JSON en base64.
     * 
     * Los archivos se reciben por parejas (XML y PDF) para su procesamiento
     *
     * @param  Request Parámetros de la petición
     * @return JsonResponse
     */
    public function documentosManualesJson(Request $request) {
        $request->merge([
            'json' => true
        ]);
        $documentosManuales = new DocumentosManuales();
        return $documentosManuales->documentosManuales($request);
    }

    /**
     * Retorna una lista de errores de cargue de documentos.
     *
     * @param  Request  $request Parámetros de la petición
     * @return JsonResponse
     */
    public function getListaErroresDocumentos(Request $request) {
        if(isset($request->pjjTipo) && $request->pjjTipo != '') {
            $detectar_documentos = [$request->pjjTipo];
        } else {
            $detectar_documentos = ['RPA','RDI','RDIMANUAL'];
        }
        if (!empty($request->excel)) {
            return $this->getListaErrores($detectar_documentos, true, 'errores_documentos');
        }
        return $this->getListaErrores($detectar_documentos, false, '');
    }

    /**
     * Retorna una lista de errores de cargue de documentos anexos.
     *
     * @param  Request  $request Parámetros de la petición
     * @return JsonResponse
     */
    public function getListaErroresDocumentosAnexos(Request $request) {
        $detectar_documentos = ['RECEPCIONANEXOS'];
        if (!empty($request->excel)) {
            return $this->getListaErrores($detectar_documentos, true, 'errores_anexos_documentos');
        }
        return $this->getListaErrores($detectar_documentos, false, '');
    }

    /**
     * Descarga una lista de errores de cargue de documentos anexos.
     *
     * @param  Request  $request Parámetros de la petición
     * @return JsonResponse
     */
    public function descargarListaErroresDocumentosAnexos(Request $request) {
        return $this->getListaErroresDocumentosAnexos($request);
    }

    /**
     * Descarga una lista de errores de cargue de documentos.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function descargarListaErroresDocumentos(Request $request) {
        return $this->getListaErroresDocumentos($request);
    }

    /**
     * Descargar documentos anexos.
     *
     * @param  string $ids IDs de los documentos anexos a descargar
     * @return JsonResponse
     * @throws \Exception
     */
    public function descargarDocumentosAnexos(string $ids) {
        try {
            // Usuario autenticado
            $user = auth()->user();

            // Base de datos del usuario autenticado
            $baseDatos = $user->getBaseDatos->bdd_nombre;

            // Cuando la petición se hace para el proceso recepción, ids llega compuesto por el cdo_id + | + ids
            if(strstr($ids, '|'))
                list($cdo_id, $ids) = explode('|', $ids);

            // Genera un array con los Ids de los documentos anexos
            $arrIds = explode(',', $ids);

            // Ruta al disco de los documentos anexos en recepcion
            MainTrait::setFilesystemsInfo();
            $disco = config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION');

            if (count($arrIds) == 1) {
                $documentoAnexo = RepDocumentoAnexoDaop::where('dan_id', $arrIds[0])
                    ->with([
                        'getCabeceraDocumentosDaop:cdo_id,ofe_id,pro_id',
                        'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                        'getCabeceraDocumentosDaop.getConfiguracionProveedor:pro_id,ofe_id,pro_identificacion'
                    ])
                    ->first();

                if (!$documentoAnexo)
                    $documentoAnexo = $this->recepcionCabeceraService->obtenerDocumentoAnexoHistorico($cdo_id, $arrIds[0]);

                $extension = explode('.', $documentoAnexo->dan_nombre);
                $fh        = \Carbon\Carbon::parse($documentoAnexo->fecha_creacion);
                $ruta      = $disco . '/' . $baseDatos . '/' . $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                    $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionProveedor->pro_identificacion . '/' . $fh->year . '/' . $fh->format('m') . '/' . $fh->format('d') . '/' . 
                    $fh->format('H') . '/' . $fh->format('i') . '/' . $documentoAnexo->dan_uuid. '.' . $extension[count($extension) - 1];

                $headers = [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ];

                return response()->download($ruta, $documentoAnexo->dan_nombre, $headers);
            } elseif (count($arrIds) > 1) {
                // Crea el UUID para el nombre del archivo .zip que se descargará
                $nombreZip = Uuid::uuid4()->toString() . ".zip";

                // Inicia la creación del archivo .zip
                $oZip = new ZipArchive();
                $oZip->open(storage_path('etl/descargas/' . $nombreZip), ZipArchive::OVERWRITE | ZipArchive::CREATE);
                foreach ($arrIds as $dan_id) {
                    $documentoAnexo = RepDocumentoAnexoDaop::where('dan_id', $dan_id)
                        ->with([
                            'getCabeceraDocumentosDaop:cdo_id,ofe_id,pro_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo',
                            'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                            'getCabeceraDocumentosDaop.getConfiguracionProveedor:pro_id,ofe_id,pro_identificacion'
                        ])
                        ->first();

                    if (!$documentoAnexo)
                        $documentoAnexo = $this->recepcionCabeceraService->obtenerDocumentoAnexoHistorico($cdo_id, $dan_id);

                    $extension = explode('.', $documentoAnexo->dan_nombre);
                    $fh        = \Carbon\Carbon::parse($documentoAnexo->fecha_creacion);
                    $ruta      = $disco . '/' . $baseDatos . '/' . $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                        $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionProveedor->pro_identificacion . '/' . $fh->year . '/' . $fh->format('m') . '/' . $fh->format('d') . '/' . 
                        $fh->format('H') . '/' . $fh->format('i') . '/' . $documentoAnexo->dan_uuid. '.' . $extension[count($extension) - 1];
                    $oZip->addFile($ruta, $documentoAnexo->dan_nombre);
                }
                $oZip->close();

                $headers = [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ];

                $tipoDoc           = $this->tipoDocumento($documentoAnexo->getCabeceraDocumentosDaop->cdo_clasificacion, $documentoAnexo->getCabeceraDocumentosDaop->rfa_prefijo);
                $nombreDescargaZip = $documentoAnexo->getCabeceraDocumentosDaop->cdo_clasificacion . $tipoDoc['prefijo'] . $documentoAnexo->getCabeceraDocumentosDaop->cdo_consecutivo . '.zip';

                return response()
                    ->download(storage_path('etl/descargas/' . $nombreZip), $nombreDescargaZip, $headers)
                    ->deleteFileAfterSend(true);
            } else {
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['No se enviaron los IDs de los documentos anexos a descargar']
                ], 422);
            }
        } catch (\Exception $e) {
            return $this->generateErrorResponse('Error al descargar el documento anexo: ' . $e->getMessage());
        }
    }

    /**
     * Cambia estado a documentos.
     * 
     * El cambio de estado está sujeto a si el documento existe en al DIAN pero está rechazado (GETSTATUS FALLIDO)
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function cambiarEstadoDocumentos(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        if (!$request->has('cdoIds') || ($request->has('cdoIds') && empty($request->cdoIds))) {
            return response()->json([
                'message' => 'Error',
                'errors' => ['Debe de especificar al menos un documento']
            ], 422);
        }

        $arrErrores = [];
        $documentos = [];
        $ids = explode(',', $request->cdoIds);
        foreach ($ids as $id) {
            // Para no modificar el endpoint se hace necesario consultar el documento para obtener el ID del OFE
            $docOfeId = RepCabeceraDocumentoDaop::select(['ofe_id'])
                ->where('cdo_id', $id)
                ->first();

            if(!$docOfeId) {
                array_push($arrErrores, ["El documento con ID {$id} no existe la data operativa y no fue posible ubicar el OFE"]);
                continue;
            }

            $documento = RepCabeceraDocumentoDaop::select($this->columns)
                ->where('cdo_id', $id)
                ->with([
                    'getDocumentoRechazado',
                    'getDocumentoAprobado',
                    'getDocumentoAprobadoNotificacion',
                    'getConfiguracionProveedor' => function($query) use ($docOfeId) {
                        $query = $this->recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $docOfeId->ofe_id);
                    }
                ])
                ->whereHas('getConfiguracionProveedor', function($query) use ($docOfeId) {
                    $query = $this->recepcionCabeceraRepository->verificaRelacionUsuarioProveedor($query, $docOfeId->ofe_id);
                })
                ->doesntHave('getUblAcuseReciboEnProceso')
                ->doesntHave('getAcuseReciboEnProceso')
                ->doesntHave('getUblReciboBienEnProceso')
                ->doesntHave('getReciboBienEnProceso')
                ->doesntHave('getAceptadoEnProceso')
                ->doesntHave('getUblAceptadoEnProceso')
                ->doesntHave('getRechazadoEnProceso')
                ->doesntHave('getUblRechazoEnProceso')
                ->doesntHave('getStatusEnProceso')
                ->doesntHave('getNotificacionAcuseReciboEnProceso')
                ->doesntHave('getNotificacionReciboBienEnProceso')
                ->doesntHave('getNotificacionAceptacionEnProceso')
                ->doesntHave('getNotificacionRechazoEnProceso')
                ->first();

            if (!$documento) {
                array_push($arrErrores, ["No existe en la data operativa el Documento con ID {$id} o tiene un estado en proceso"]);
            } else {
                array_push($documentos, $documento);
            }
        }

        $documentosCambioEstado = '';
        foreach ($documentos as $documento) {
            if ((!$documento->getDocumentoRechazado && !$documento->getDocumentoAprobado && !$documento->getDocumentoAprobadoNotificacion) || $documento->cdo_origen == "NO-ELECTRONICO") {
                $documentosCambioEstado .= (($documento->rfa_prefijo != '') ? $documento->rfa_prefijo : "") . $documento->cdo_consecutivo . ", ";
                $nuevoEstado = ($documento->estado == 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
                $documento->update([
                    'estado' => $nuevoEstado
                ]);
            } else {
                array_push($arrErrores, (($documento->rfa_prefijo != '') ? $documento->rfa_prefijo : "") . $documento->cdo_consecutivo . " no puede cambiar de estado, verifique que NO haya sido aceptado por la DIAN.");
            }
        }

        return response()->json([
            'message' => ($documentosCambioEstado != '') ? 'Documentos a los que se cambió su estado: ' . substr($documentosCambioEstado, 0, -2) : '',
            'errors'  => $arrErrores
        ], 200);
    }

    /**
     * Permite el agendamiento de notificación de eventos a la DIAN.
     *
     * @param  Request $request Parámetros de la petición
     * @param  bool $registroExcel Indica si viene del proceso de registro masivo de eventos
     * @return JsonResponse
     */
    public function registrarEvento(Request $request, bool $registroExcel = false) {
        if (!$request->has('documentos') || !is_array($request->documentos) || empty($request->documentos)) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['La petición esta mal formada, no existe la propiedad [documentos] o no es del tipo array']
            ], 422);
        }
        
        if (!$request->has('evento') || empty($request->evento)) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['La petición esta mal formada, no existe la propiedad [evento] o esta vacia']
            ], 422);
        }

        try {
            switch(strtolower($request->evento)) {
                case "acuse":
                    $metodo = 'agendarAcuseRecibo';
                    break;
                case "recibobien":
                    $metodo = 'agendarReciboBien';
                    break;
                case "aceptacion":
                    $metodo = 'agendarAceptacionExpresa';
                    break;
                case "reclamo":
                    $metodo = 'agendarRechazo';
                    break;
                default:
                    return response()->json([
                        'message' => 'Error al procesar la petición',
                        'errors'  => ['El evento debe corresponder a ACUSE, RECIBOBIEN, ACEPTACION o RECLAMO']
                    ], 400);
            }

            $fallidos = [];
            $exitosos = [];
            foreach($request->documentos as $documento) {
                // Control error por documento
                $errorDocumento = 0;

                // Texto Mensaje de error
                if(array_key_exists('cdo_cufe', $documento) && !empty($documento['cdo_cufe'])) {
                    $mensaje = 'con CUFE [' . $documento['cdo_cufe'] . ']';
                } else {
                    $mensaje = '[' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'] . ']';
                }

                // Si se envio cufe solo se valida que exista un documento con ese cdo_cufe
                // Si no envia cufe se validan los datos del documento
                if(!(array_key_exists('cdo_cufe', $documento) && !empty($documento['cdo_cufe']))) {
                    $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
                        ->where('ofe_identificacion', $documento['ofe_identificacion'])
                        ->validarAsociacionBaseDatos()
                        ->where('estado', 'ACTIVO')
                        ->first();

                    if(!$ofe) {
                        $errorDocumento = 1;
                        $fallidos[] = 'Para el documento ' . $mensaje . ' No existe el OFE [' . $documento['ofe_identificacion'] . ']';
                    }

                    $pro = ConfiguracionProveedor::select(['pro_id'])
                        ->where('ofe_id', $ofe->ofe_id)
                        ->where('pro_identificacion', $documento['pro_identificacion'])
                        ->where('estado', 'ACTIVO')
                        ->first();

                    if(!$pro) {
                        $errorDocumento = 1;
                        $fallidos[] = 'Para el documento ' . $mensaje . ' No existe el Proveedor [' . $documento['pro_identificacion'] . ']';
                    }

                    $tdeID = null;
                    ParametrosTipoDocumentoElectronico::select(['tde_id', 'tde_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('tde_codigo', $documento['tde_codigo'])
                        ->where('estado', 'ACTIVO')
                        ->get()
                        ->groupBy('tde_codigo')
                        ->map(function($registro) use (&$tdeID) {
                            $valida = $this->validarVigenciaRegistroParametrica($registro);
                            if($valida['vigente'])
                                $tdeID = $valida['registro']->tde_id;
                        });
            
                    if(!$tdeID) { 
                        $errorDocumento = 1;
                        $fallidos[] = 'Para el documento ' . $mensaje . ' No existe el tipo de documento electrónico [' . $documento['tde_codigo'] . ']';
                    }
                }

                // Validando la fecha
                if(!array_key_exists('cdo_fecha', $documento) || (array_key_exists('cdo_fecha', $documento) && empty($documento['cdo_fecha']))) {
                    $errorDocumento = 1;
                    $fallidos[] = 'Para el documento ' . $mensaje . ' no se envió el parámetro [cdo_fecha]';
                }

                // Validando el codigo de reclamo, solo para el evento RECLAMO
                if(array_key_exists('cre_codigo', $documento) && !empty($documento['cre_codigo']) && strtolower($request->evento) == 'reclamo') {
                    $conceptoRechazo = null;
                    ParametrosConceptoRechazo::select(['cre_id', 'cre_codigo', 'cre_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('cre_codigo', $documento['cre_codigo'])
                        ->where('estado', 'ACTIVO')
                        ->get()
                        ->groupBy('cre_codigo')
                        ->map(function($registro) use (&$conceptoRechazo) {
                            $valida = $this->validarVigenciaRegistroParametrica($registro);
                            if($valida['vigente'])
                                $conceptoRechazo = $valida['registro'];
                        });

                    if(!$conceptoRechazo) {
                        $errorDocumento = 1;
                        $fallidos[] = 'Para el documento ' . $mensaje . ' No existe el código de rechazo [' . $documento['cre_codigo'] . '] para el OFE, proveedor y tipo de documento electrónico indicados';
                    }
                } elseif(
                    (
                        !array_key_exists('cre_codigo', $documento) &&
                        strtolower($request->evento) == 'reclamo'
                    ) ||
                    (
                        array_key_exists('cre_codigo', $documento) &&
                        empty($documento['cre_codigo']) &&
                        strtolower($request->evento) == 'reclamo'
                    )
                ) {
                    $errorDocumento = 1;
                    $fallidos[] = 'Para el [RECLAMO] del documento ' . $mensaje . ' No se envío la propiedad [cre_codigo]';
                }

                if ($errorDocumento == 1) {
                    // Se genero error en el documento
                    continue;
                }

                // Buscar por CUFE
                if(array_key_exists('cdo_cufe', $documento) && !empty($documento['cdo_cufe'])) {
                    $doc = RepCabeceraDocumentoDaop::select(['cdo_id','ofe_id','rfa_prefijo','cdo_consecutivo','cdo_cufe'])
                        ->where('cdo_cufe', $documento['cdo_cufe'])
                        ->where('cdo_fecha', $documento['cdo_fecha'])
                        ->first();

                    if(!$doc) {
                        $fallidos[] = 'No existe en la data operativa el documento electrónico ' . $mensaje . '.';
                        continue;
                    }

                    // Trayendo la informacion del OFE
                    $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
                        ->where('ofe_id', $doc['ofe_id'])
                        ->validarAsociacionBaseDatos()
                        ->where('estado', 'ACTIVO')
                        ->first();

                    if(!$ofe) {
                        $errorDocumento = 1;
                        $fallidos[] = 'El documento ' . $mensaje . ' no existe en la data operativa, no existe el OFE o el OFE se encuentra INACTIVO';
                    }
                } else {
                    // Buscar por Documento
                    $doc = RepCabeceraDocumentoDaop::select(['cdo_id','rfa_prefijo','cdo_consecutivo','cdo_cufe'])
                        ->where('ofe_id', $ofe->ofe_id)
                        ->where('pro_id', $pro->pro_id)
                        ->where('tde_id', $tdeID);
                    if($documento['rfa_prefijo'] != '' && $documento['rfa_prefijo'] != 'null' && $documento['rfa_prefijo'] != null)
                        $doc = $doc->where('rfa_prefijo', trim($documento['rfa_prefijo']));
                    else
                        $doc = $doc->where(function($query) {
                            $query->whereNull('rfa_prefijo')
                                ->orWhere('rfa_prefijo', '');
                        });
                    $doc = $doc->where('cdo_consecutivo', $documento['cdo_consecutivo'])
                        ->where('cdo_fecha', $documento['cdo_fecha'])
                        ->first();
                }

                if(!$doc) {
                    $fallidos[] = 'No existe en la data operativa el documento electrónico ' . $mensaje . '.';
                    continue;
                }

                $newRequest = new Request();
                $newRequest->merge([
                    'cdoIds' => $doc->cdo_id,
                    'ofeId'  => $ofe->ofe_id
                ]);

                if(array_key_exists('cre_codigo', $documento) && !empty($documento['cre_codigo']) && strtolower($request->evento) == 'reclamo') {
                    $newRequest->merge([
                        'descripcionRechazo' => $conceptoRechazo->cre_descripcion,
                        'conceptoRechazo'    => $documento['cre_codigo']
                    ]);
                }

                if(array_key_exists('cdo_observacion', $documento) && !empty($documento['cdo_observacion']) && strtolower($request->evento) == 'reclamo') {
                    $newRequest->merge([
                        'motivoRechazo' => $documento['cdo_observacion']
                    ]);
                } elseif(array_key_exists('cdo_observacion', $documento) && !empty($documento['cdo_observacion']) && strtolower($request->evento) != 'reclamo') {
                    $newRequest->merge([
                        'observacion' => $documento['cdo_observacion']
                    ]);
                }

                $proceso   = $this->$metodo($newRequest);
                $status    = $proceso->getStatusCode();
                $respuesta = json_decode((string)$proceso->getContent());

                if(isset($respuesta->errors) && !empty($respuesta->errors)) {
                    $fallidos[] = !$registroExcel ? 'Documento ' . $mensaje . ': ' . implode(' - ' ,$respuesta->errors) : implode(' - ' ,$respuesta->errors);
                    continue;
                } else {
                    $exitosos[] = 'Documento ' . $mensaje . ': ' . $respuesta->message;
                }
            }

            return response()->json([
                'message'   => 'Solicitud Procesada',
                'exitosos' => $exitosos,
                'fallidos' => $fallidos
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al procesar la petición',
                'errors'  => [$e->getMessage()]
            ], 400);
        }
    }

    /**
     * Obtiene los estados de un documento en específico.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function obtenerEstadosDocumento(Request $request) {
        $request->merge([
            'proceso' => 'recepcion'
        ]);

        return $this->estadosDocumento($request);
    }

    /**
     * Obtiene los documentos anexos de un documento en específico.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function obtenerDocumentosAnexos(Request $request) {
        $request->merge([
            'proceso' => 'recepcion'
        ]);

        return $this->documentosAnexos($request);
    }

    /**
     * Permite cargar documentos anexos a un documento en el sistema.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function cargarDocumentosAnexos(Request $request) {
        $documento = RepCabeceraDocumentoDaop::select($this->columns)
            ->with([
                'getConfiguracionProveedor:pro_id,pro_identificacion',
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion'
            ])
            ->find($request->cdo_id);

        if ($documento) {
            if ($request->totalDocumentosAnexos > 0) {
                $erroresExisten  = [];
                $erroresAnexos = [];

                // Itera sobre los documentos anexos para validarlos
                for ($i = 0; $i < $request->totalDocumentosAnexos; $i++) {
                    $anexo = 'archivo' . ($i + 1);
                    $validarAnexo = $this->validarDocumentoAnexo($request->$anexo, $anexo);

                    if ($validarAnexo !== true) {
                        $erroresAnexos[] = $validarAnexo;
                    }
                }
                
                $user = auth()->user();
                $baseDatos = $user->getBaseDatos->bdd_nombre;

                if (count($erroresAnexos) > 0) {
                    EtlProcesamientoJson::create([
                        'pjj_tipo'                => 'RECEPCIONANEXOS',
                        'pjj_json'                => json_encode([]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => json_encode($erroresAnexos),
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => $user->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);

                    return response()->json([
                        'message' => 'Errores con los documentos anexos',
                        'errors' => ['Verifique el log de errores']
                    ], 422);
                }

                // Obtiene fecha y hora del sistema para utlizarlos en el UUID del lote
                $dateObj = \DateTime::createFromFormat('U.u', microtime(TRUE));
                $msg = $dateObj->format('u');
                $msg /= 1000;
                $dateObj->setTimeZone(new \DateTimeZone('America/Bogota'));
                $dateTime = $dateObj->format('YmdHis') . intval($msg);

                // Crea el UUID para el lote de cargue de los documentos anexos
                $uuidLote = Uuid::uuid4();
                $lote = $dateTime . '_' . $uuidLote->toString();

                // Disco en donde se almacenarán los documentos anexos
                MainTrait::crearDiscoDinamico('documentos_anexos_recepcion', config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION'));

                // Itera sobre los documentos anexos para guardarlos en la ruta y base de datos
                for ($i = 0; $i < $request->totalDocumentosAnexos; $i++) {
                    $anexo       = 'archivo' . ($i + 1);
                    $descripcion = 'descripcion' . ($i + 1);

                    $uuid        = Uuid::uuid4();
                    $nuevoNombre = $uuid->toString();
                    $directorio  = $baseDatos . '/' . $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion  . '/' . $documento->getConfiguracionProveedor->pro_identificacion . '/' . date('Y') .
                        '/' . date('m') . '/' . date('d') . '/' . date('H') . '/' . date('i');
                    $request->$anexo->storeAs(
                        $directorio,
                        $nuevoNombre . "." . $request->$anexo->extension(),
                        'documentos_anexos_recepcion'
                    );

                    chmod(config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $directorio . '/' . $nuevoNombre . "." . $request->$anexo->extension(), 0755);

                    // Tamaño del archivo
                    $tamano = $request->$anexo->getSize();

                    // Nombre original del archivo
                    $nombreOriginal    = $request->$anexo->getClientOriginalName();
                    $nombreOriginal    = $this->sanear_string($nombreOriginal);
                    $extensionOriginal = explode('.', $nombreOriginal);

                    // Verifica que el documento anexo no existe en la BD para el mismo documento
                    $existe = RepDocumentoAnexoDaop::where('cdo_id', $request->cdo_id)
                        ->where('dan_nombre', $nombreOriginal)
                        ->where('dan_tamano', $tamano)
                        ->first();

                    if (!$existe) {
                        $documentoAnexo['cdo_id']                      = $request->cdo_id;
                        $documentoAnexo['dan_lote']                    = $lote;
                        $documentoAnexo['dan_uuid']                    = $nuevoNombre;
                        $documentoAnexo['dan_tamano']                  = $tamano;
                        $documentoAnexo['dan_nombre']                  = $extensionOriginal[0] . '.' . $request->$anexo->extension();
                        $documentoAnexo['dan_descripcion']             = $request->$descripcion;
                        $documentoAnexo['dan_envio_openecm']           = null; // TODO: Pendiente implementación por parte de openECM
                        $documentoAnexo['dan_respuesta_envio_openecm'] = null; // TODO: Pendiente implementación por parte de openECM
                        $documentoAnexo['estado']                      = 'ACTIVO';
                        $documentoAnexo['usuario_creacion']            = $user->usu_id;

                        $crearDocumentoAnexo = RepDocumentoAnexoDaop::create($documentoAnexo);
                        if (!$crearDocumentoAnexo) {
                            $erroresAnexos[] = 'Se presentó un error 409 al intentar crear el registro del documento anexo ' . $nombreOriginal;
                        }
                    } else {
                        $erroresExisten[] = $nombreOriginal;
                    }
                }

                if (count($erroresAnexos) > 0) {
                    EtlProcesamientoJson::create([
                        'pjj_tipo'                => 'RECEPCIONANEXOS',
                        'pjj_json'                => json_encode([]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => json_encode($erroresAnexos),
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => $user->usu_id,
                        'estado'                  => 'ACTIVO'
                    ]);

                    return response()->json([
                        'message' => 'Se presentaron errores al intentar crear los anexos',
                        'errors' => ['Verifique el log de errores']
                    ], 409);
                } else {
                    if (count($erroresExisten) > 0) {
                        $existen = '<br><br>Los siguientes anexos no se cargaron dado que ya existen en el sistema: ' . implode(', ', $erroresExisten);
                    } else {
                        $existen = '';
                    }
                    return response()->json([
                        'message' => 'Documentos anexos procesados con el lote ' . $lote . $existen
                    ], 201);
                }
            }
        } else {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ['No existe el documento para el cual se intentan cargar anexos']
            ], 404);
        }
    }

    /**
     * Valida documentos anexos.
     *
     * @param  $documentoAnexo Archivo enviado con la petición
     * @param  $campoDocumentoAnexo Nombre del campo del documento anexo
     * @return String||Boolean mensaje de error en caso de fallo || true
     */
    private function validarDocumentoAnexo($documentoAnexo, $campoDocumentoAnexo) {
        $rules = [];
        $rules[$campoDocumentoAnexo] = 'file|mimes:tiff,jpg,jpeg,png,gif,bmp,pdf,doc,docx,xls,xlsx,zip,rar|max:1000';

        $valFile[$campoDocumentoAnexo] = $documentoAnexo;

        $validarAnexo = Validator::make($valFile, $rules);

        if ($validarAnexo->fails()) {
            return $validarAnexo->messages()->all();
        } else {
            return true;
        }
    }

    /**
     * Permite encontrar documentos de acuerdo a los criterios de búsqueda.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response
     */
    public function encontrarDocumento(Request $request) {
        $documentos = RepCabeceraDocumentoDaop::select($this->columns)
            ->where('ofe_id', $request->ofe_id)
            ->where('cdo_consecutivo', 'like', '%' . $request->cdo_consecutivo . '%')
            ->where('cdo_fecha', '>=', $request->cdo_fecha_desde . ' 00:00:00')
            ->where('cdo_fecha', '<=', $request->cdo_fecha_hasta . ' 23:59:59')
            ->where('estado', 'ACTIVO');

        if($request->has('pro_id') && !empty($request->pro_id))
            $documentos = $documentos->whereIn('pro_id', explode(',', $request->pro_id));

        if($request->has('cdo_clasificacion') && !empty($request->cdo_clasificacion))
            $documentos = $documentos->where('cdo_clasificacion', $request->cdo_clasificacion);
            
        if($request->has('rfa_prefijo') && !empty($request->rfa_prefijo))
            $documentos = $documentos->where('rfa_prefijo', $request->rfa_prefijo);

        $documentos = $documentos->with([
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                        'pro_id',
                        'pro_identificacion',
                        DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                    ]);
                },
                'getDocumentosAnexos:dan_id,cdo_id,dan_nombre,dan_descripcion,dan_tamano'
            ])
            ->get();

        return response()->json([
            'data' => $documentos
        ], 200);
    }

    /**
     * Permite eliminar documentos anexos.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response
     * @throws \Exception
     */
    public function eliminarDocumentosAnexos(Request $request) {
        try {
            // Usuario autenticado
            $user = auth()->user();

            // Base de datos del usuario autenticado
            $baseDatos = $user->getBaseDatos->bdd_nombre;

            // Genera un array con los Ids de los documentos anexos
            $arrIds = explode(',', $request->ids);

            // Disco en donde se almacenarán los documentos anexos
            MainTrait::crearDiscoDinamico('documentos_anexos_recepcion', config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION'));

            foreach($arrIds as $dan_id) {
                $documentoAnexo = RepDocumentoAnexoDaop::select(['dan_id', 'cdo_id', 'dan_nombre', 'dan_uuid', 'fecha_creacion'])
                    ->where('dan_id', $dan_id)
                    ->with([
                        'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                        'getCabeceraDocumentosDaop.getConfiguracionProveedor:pro_id,ofe_id,pro_identificacion'
                    ])
                    ->first();

                if (!$documentoAnexo) {
                    $historico = true;
                    $documentoAnexo = $this->recepcionCabeceraService->obtenerDocumentoAnexoHistorico($request->cdo_id, $dan_id);
                }

                if($documentoAnexo) {
                    $fh          = \Carbon\Carbon::parse($documentoAnexo->fecha_creacion);
                    $extension   = explode('.', $documentoAnexo->dan_nombre);
                    $rutaArchivo = $baseDatos . '/' . $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                        $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionProveedor->pro_identificacion . '/' . $fh->year . '/' . $fh->format('m') . '/' . $fh->format('d') . '/' . 
                        $fh->format('H') . '/' . $fh->format('i') . '/' . $documentoAnexo->dan_uuid. '.' . $extension[count($extension) - 1];

                    // Elimina el documento anexo del disco
                    Storage::disk('documentos_anexos_recepcion')->delete($rutaArchivo);

                    // Elimina el documento anexo de la base de datos
                    if(!isset($historico))
                        $documentoAnexo->delete();
                    else
                        $this->recepcionCabeceraService->eliminarDocumentoAnexoHistorico($request->cdo_id, $dan_id);
                }
            }

            return response()->json([
                'message' => 'Documentos anexos eliminados'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error en proceso',
                'errors' => ['Se generó un error al intentar eliminar los documentos anexos: ' . $e->getMessage()]
            ], 404);
        }
    }

    /**
     * Consulta los datos de un documento no electrónico, debe solicitar como parametros el nit del OFE, el consecutivo del documento, el prefijo y la clasificación.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function consultarDocumentoNoElectronico(Request $request) {
        if (!isset($request->editar_documento)) {
            // Verifica que el OFE exista con estado ACTIVO
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg'])
                ->where('ofe_identificacion', $request->ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$ofe)
                return response()->json([
                    'message' => 'Error al consultar el documento',
                    'errors'  => ['El OFE con identificación ['.$request->ofe_identificacion.'] no existe o se encuentra INACTIVO']
                ], 404);
        }

        $documento = RepCabeceraDocumentoDaop::with([
            'getParametrosMoneda',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_recepcion_fnc_activo',
            'getConfiguracionProveedor:pro_id,pro_identificacion,pro_razon_social,pro_primer_apellido,pro_segundo_apellido,pro_primer_nombre,pro_otros_nombres',
            'getEstadosValidacion:est_id,cdo_id,est_estado,est_resultado'
        ]);
        // Condición para retornar el documento cuando es por editar o ver
        if ($request->editar_documento) {
            $documento = $documento->where('cdo_id', $request->cdo_id)
                ->where('ofe_id', $request->ofe_id);
        } else {
            $documento = $documento->where('cdo_clasificacion', $request->cdo_clasificacion)
                ->where('ofe_id', $ofe->ofe_id)
                ->where('cdo_consecutivo', $request->cdo_consecutivo);

            if($request->rfa_prefijo != '' && $request->rfa_prefijo != 'null' && $request->rfa_prefijo != null)
                $documento = $documento->where('rfa_prefijo', trim($request->rfa_prefijo));
            else
                $documento = $documento->where(function($query) {
                    $query->whereNull('rfa_prefijo')
                        ->orWhere('rfa_prefijo', '');
                });
        }

        $documento = $documento->where('cdo_origen', 'NO-ELECTRONICO')
            ->first();

        if (is_null($documento)) {
            return response()->json([
                'message' => 'Error al consultar el documento',
                'errors' => ["El Documento ".(($request->rfa_prefijo === null || $request->rfa_prefijo == '' || $request->rfa_prefijo == 'null') ? '' : $request->rfa_prefijo)."{$request->cdo_consecutivo} no existe o no es un documento no electrónico"]
            ], 404);
        }

        return response()->json([
            'data' =>  $documento
        ], 200);
    }

    /**
     * Lista los documentos recibidos según la fecha desde y fecha hasta que se filtran por la columna fecha_creacion.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function listarDocumentosRecibidos(Request $request): JsonResponse {
        if(!$request->filled('fecha_desde') || !$request->filled('fecha_hasta'))
            return response()->json([
                'message' => 'Error al consultar el documento',
                'errors' => ["La petición está mal formada, debe especificar la fecha desde y la fecha hasta, por favor verifique"]
            ], 422);
        else {
            $fechaDesde = date_create($request->fecha_desde);
            $fechaHasta = date_create($request->fecha_hasta);

            if ($fechaDesde > $fechaHasta)
                return response()->json([
                    'message' => 'Error al consultar el documento',
                    'errors' => ["La petición está mal formada, la fecha desde no puede ser mayor a la fecha hasta, por favor verifique"]
                ], 422);
        }

        $documentos = $this->recepcionCabeceraService->listarDocumentosRecibidos($request);

        return response()->json($documentos, 200);
    }

    /**
     * Asigna un grupo de trabajo a uno o varios documentos.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function asignarGrupoTrabajoDocumentos(Request $request): JsonResponse {
        if(!$request->filled('cdo_ids') || !$request->filled('gtr_id') || !$request->filled('pro_id') || !$request->filled('nombre_grupos_trabajo'))
            return response()->json([
                'message' => 'Error al procesar la petición',
                'errors'  => ["La petición está mal formada, faltan parámetros"]
            ], 422);

        $grupoProveedor = ConfiguracionGrupoTrabajoProveedor::select(['gtp_id', 'gtr_id'])
            ->where('gtr_id', $request->gtr_id)
            ->where('pro_id', $request->pro_id)
            ->where('estado', 'ACTIVO')
            ->with([
                'getGrupoTrabajo:gtr_id,gtr_codigo,gtr_nombre'
            ])
            ->first();

        if(!$grupoProveedor)
            return response()->json([
                'message' => 'Error al procesar la petición',
                'errors'  => ["El (la) " . $request->nombre_grupos_trabajo . " no existe o se encuentra inactivo(a)"]
            ], 400);

        $grupoTrabajo = ConfiguracionGrupoTrabajo::select(['gtr_codigo', 'gtr_nombre'])
            ->find($request->gtr_id);

        $errores = [];
        $cdoIds  = array_unique(explode(',', $request->cdo_ids));
        foreach($cdoIds as $cdo_id) {
            $documento = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_origen', 'ofe_id', 'pro_id', 'tde_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha'])
                ->where('cdo_id', $cdo_id)
                ->where('estado', 'ACTIVO')
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente' => function ($query) {
                        $query->select([
                            'ofe_id',
                            'ofe_identificacion',
                            'ofe_correo',
                            'ofe_recepcion_fnc_activo',
                            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_razon_social')
                        ]);
                    },
                    'getConfiguracionProveedor' => function ($query) {
                        $query->select([
                            'pro_id',
                            'pro_identificacion',
                            'pro_correo',
                            DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as pro_razon_social')
                        ]);
                    },
                    'getTipoDocumentoElectronico:tde_id,tde_codigo,tde_descripcion'
                ])
                ->first();

            if(!$documento) {
                $errores[] = 'Documento con ID [' . $cdo_id . '] no existe en la data operativa o se encuentra inactivo';
                continue;
            }

            $documento->update([
                'gtr_id' => $request->gtr_id
            ]);

            RepEstadoDocumentoDaop::create([
                'cdo_id'                    => $cdo_id,
                'est_estado'                => 'ASIGNAR_DEPENDENCIA',
                'est_resultado'             => 'EXITOSO',
                'est_ejecucion'             => 'FINALIZADO',
                'est_informacion_adicional' => json_encode([
                    'usuario'                => auth()->user()->usu_nombre,
                    'nombre_grupos_trabajo'  => $request->nombre_grupos_trabajo,
                    'grupo_trabajo_asignado' => $grupoTrabajo->gtr_codigo . ' - ' .$grupoTrabajo->gtr_nombre,
                    'fecha_asignacion'       => date('Y-m-d'),
                    'hora_asignacion'        => date('H:i:s'),
                    'observacion'            => $request->observacion
                ]),
                'usuario_creacion'          => auth()->user()->usu_id,
                'estado'                    => 'ACTIVO'
            ]);

            // Se envía un correo a los usuarios del grupo indicando que el documento fue asignado a ese grupo
            if($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_fnc_activo == 'SI') {
                $this->notificarDocumentoAsociadoGrupoTrabajo($documento, $request->gtr_id);
            }
        }

        if(!empty($errores) && count($errores) == count($cdoIds))
            return response()->json([
                'message' => 'Error al procesar la petición',
                'errors'  => $errores
            ], 422);
        elseif(!empty($errores) && count($errores) < count($cdoIds))
            return response()->json([
                'message' => 'Petición procesada, algunos documentos generaron error',
                'errors'  => $errores
            ], 400);
        else
            return response()->json([
                'message' => 'Petición procesada, ' . $request->nombre_grupos_trabajo . ' asignada a los documentos'
            ], 200);
    }

    /**
     * Crea el agendamiento en el sistema para poder procesar y generar el archivo de Excel en background.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response
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

        $tipo = 'RRECIBIDOS';
        if($request->filled('tipo')){
            switch($request->tipo) {
                case 'recepcion-validacion':
                    $tipo = 'RVALIDACION';
                    break;
                case 'recepcion-log-validacion':
                    $tipo = 'RLOGVALIDACION';
                    break;
                default:
                    $tipo = 'RRECIBIDOS';
                    break;
            }
        }

        EtlProcesamientoJson::create([
            'pjj_tipo'         => $tipo,
            'pjj_json'         => json_encode(json_decode($request->json, TRUE)),
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
     * @param  Request  $request Parámetros de la petición
     * @param  string  $pjj_tipo Tipo de reporte a generar
     * @return array
     * @throws \Exception
     * @deprecated Método obsoleto por desarrollo de la versión 2 de particonamiento, por favor usar el método procesarAgendamientoReporte() de la clase ParticionamientoRecepcionController
     */
    public function procesarAgendamientoReporte(Request $request, string $pjj_tipo): array {
        try {
            $request->merge([
                'pjj_tipo'           => $pjj_tipo,
                'proceso_background' => true
            ]);

            $this->request = $request;

            switch ($pjj_tipo) {
                case 'RRECIBIDOS':
                    // $arrExcel =  $this->recepcionCabeceraService->getListaDocumentosRecibidos($request); // TODO: SE COMENTA ESTE CÓDIGO QUE HACE USO DE PARTICIONAMIENTO PORQUE REQUIERE OPTIMIZACIÓN Y SE RESTAURA LA VERSIÓN ANTERIOR DEL MÉTODO
                    $arrExcel =  $this->getListaDocumentosRecibidos($request);
                    break;
                case 'RVALIDACION':
                    $arrExcel = $this->getListaValidacionDocumentos($request);
                    break;
                case 'RLOGVALIDACION':
                    $arrExcel = $this->generarLogValidacionDocumentos($request);
                    break;
            }

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
     * Filtra los reportes cuando en el request llega el parámetro buscar.
     *
     * @param  Request $request Parámetros de la petición
     * @param  Builder $query Instancia del Eloquent Builder
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
     * Si este usuario es un ADMINISTRADOR o MA se generarán todos los reportes.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function listarReportesDescargar(Request $request): JsonResponse {
        $user = auth()->user();
        $reportes = [];
        $start          = $request->filled('start')          ? $request->start          : 0;
        $length         = $request->filled('length')         ? $request->length         : 10;
        $ordenDireccion = $request->filled('ordenDireccion') ? $request->ordenDireccion : 'DESC';
        $columnaOrden   = $request->filled('columnaOrden')   ? $request->columnaOrden   : 'fecha_modificacion';

        $consulta = EtlProcesamientoJson::select(['pjj_id', 'pjj_tipo', 'pjj_json', 'age_estado_proceso_json', 'fecha_modificacion', 'usuario_creacion'])
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha_modificacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'])
            ->when($request->filled('tipo'), function($query) use ($request) {
                return $query->where('pjj_tipo', $request->tipo);
            }, function($query) {
                return $query->whereIn('pjj_tipo', ['RRECIBIDOS', 'RVALIDACION']);
            })
            ->when(!in_array($user->usu_type, ['ADMINISTRADOR', 'MA']), function ($query) use ($user) {
                return $query->where('usuario_creacion', $user->usu_id);
            })
            ->when($request->filled('buscar'), function ($query) use ($request) {
                return $this->buscarReportes($request, $query);
            });

        $totalRegistros = $consulta->count();
        $length         = $length != -1 ? $length : $totalRegistros;

        $consulta->orderBy($columnaOrden, $ordenDireccion)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function($reporte) use (&$reportes) {
                switch($reporte->pjj_tipo) {
                    case 'RRECIBIDOS':
                        $tipoReporte = 'DOCUMENTOS RECIBIDOS';
                        break;
                    case 'RVALIDACION':
                        $tipoReporte = 'VALIDACION DOCUMENTOS';
                        break;
                    case 'RLOGVALIDACION':
                        $tipoReporte = 'LOG VALIDACIÓN DOCUMENTOS';
                        break;
                    default:
                        $tipoReporte = 'Reportes Background';
                        break;
                }

                $pjjJson           = json_decode($reporte->pjj_json);
                $fechaModificacion = Carbon::createFromFormat('Y-m-d H:i:s', $reporte->fecha_modificacion)->format('Y-m-d H:i:s');
                $usuario           = User::select(['usu_id', 'usu_identificacion', 'usu_nombre'])->where('usu_id', $reporte->usuario_creacion)->first();
                if($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->archivo_reporte)) {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => $pjjJson->archivo_reporte,
                        'fecha'           => $fechaModificacion,
                        'errores'         => '',
                        'estado'          => 'FINALIZADO',
                        'tipo_reporte'    => $tipoReporte,
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
                        'tipo_reporte'    => $tipoReporte,
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
                        'tipo_reporte'    => $tipoReporte,
                        'usuario'         => $usuario,
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
     * @param  Request $request Parámetros de la petición
     * @return Response
     */
    public function descargarReporte(Request $request) {
        $user = auth()->user();

        // Verifica que el pjj_id del request pertenezca al usuario autenticado
        $pjj = EtlProcesamientoJson::find($request->pjj_id);

        if($pjj && $pjj->usuario_creacion == $user->usu_id) {
            if($pjj->pjj_json != '') {
                $pjjJson = json_decode($pjj->pjj_json);
                $archivo = $pjjJson->archivo_reporte;


                if(is_file(storage_path('etl/descargas/' . $archivo)))
                    return $this->download($archivo);
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
     * Crea el estado de validación VALIDADO, RECHAZADO, PAGADO, ASIGNAR ó LIBERAR según la acción a realizar para uno o varios documentos.
     *
     * @param Request $request Parametros de la petición
     * @return JsonResponse
     */
    public function crearEstadoValidacionAccion(Request $request): JsonResponse {
        if(!$request->filled('ofeId') || !$request->filled('cdoIds') || !$request->has('camposFnc') || !$request->filled('accion'))
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['Faltan parámetros en la petición']
            ], 409);

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo', 'ofe_recepcion_fnc_configuracion'])
            ->where('ofe_id', $request->ofeId)
            ->where('estado', 'ACTIVO')
            ->where('ofe_recepcion', 'SI')
            ->where('ofe_recepcion_fnc_activo', 'SI')
            ->validarAsociacionBaseDatos()
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['Verifique la configuración del OFE para el proceso Recepción']
            ], 400);

        $estEstado = 'VALIDACION';
        switch ($request->accion) {
            case 'validar':
            case 'datos_aprobacion':
                $propiedad    = "validacion_aprobacion";
                $msjResultado = 'validados';
                $estResultado = 'VALIDADO';
                break;
            case 'rechazar':
                $propiedad    = "validacion_rechazo";
                $msjResultado = 'rechazados';
                $estResultado = 'RECHAZADO';
                break;
            case 'pagar':
                $propiedad    = "validacion_pagado";
                $msjResultado = 'pagados';
                $estResultado = 'PAGADO';
                break;
            case 'asignar':
                $estEstado    = "ASIGNAR_VALIDACION";
                $msjResultado = 'asignados';
                $estResultado = 'EXITOSO';
                break;
            case 'liberar':
                $estEstado    = "LIBERAR_VALIDACION";
                $msjResultado = 'liberados';
                $estResultado = 'EXITOSO';
                break;
            case 'enproceso':
                $msjResultado = 'procesados';
                $propiedad    = "evento_recibo_bien";
                $estEstado    = "VALIDACION";
                if($request->filled('origen') && $request->origen == 'validacion_documentos')
                    $estResultado = 'PENDIENTE';
                else
                    $estResultado = 'ENPROCESO';
                break;
            case 'pendiente':
                $msjResultado = 'enviados a validación';
                $propiedad    = "evento_recibo_bien";
                $estEstado    = "VALIDACION";
                $estResultado = 'PENDIENTE';
                break;
            case 'documento_validado':
                $propiedad    = "validacion_aprobacion";
                $msjResultado = 'validados';
                $estEstado    = "VALIDACION";
                $estResultado = 'VALIDADO';
                break;
        }

        $estInformacionAdicional = null;
        if($ofe->ofe_recepcion_fnc_activo == 'SI' && $request->accion != 'asignar' && $request->accion != 'liberar') {
            TenantTrait::GetVariablesSistemaTenant();
            $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
            $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();

            if(!empty($ofe->ofe_recepcion_fnc_configuracion)) {
                $ofeRecepcionFncConfiguracion = json_decode($ofe->ofe_recepcion_fnc_configuracion);
                if(isset($ofeRecepcionFncConfiguracion->{$propiedad}) && !empty($ofeRecepcionFncConfiguracion->{$propiedad})) {
                    $validarOfeRecepcionFncEvento = $this->validarOfeRecepcionFncEvento($request, $ofeRecepcionFncConfiguracion->{$propiedad});
                    if(array_key_exists('errors', $validarOfeRecepcionFncEvento) && !empty($validarOfeRecepcionFncEvento['errors']))
                        return response()->json([
                            'message' => 'Error al Procesar la Acción',
                            'errors'  => $validarOfeRecepcionFncEvento['errors']
                        ], 409);
                    else
                        $estInformacionAdicional['campos_adicionales'] = $validarOfeRecepcionFncEvento['campos_fnc'];
                }
            }
        }

        if(empty($estInformacionAdicional) && $request->accion != 'asignar' && $request->accion != 'liberar')
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => ['Verifique la información requerida para la validación, falta información']
            ], 409);

        $arrErrores             = [];
        $arrAgendarAcuse        = [];
        $arrAgendarRecibo       = [];
        $arrDocumentosNotificar = [];
        $arrDocumentosValidados = [];
        $arrGtrIds              = [];
        $documentoValidado      = '';
        $cdoIds                 = explode(',', $request->cdoIds);
        foreach ($cdoIds as $cdo_id) {
            $documento = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'ofe_id', 'pro_id', 'gtr_id', 'cdo_usuario_responsable'])
                ->where('cdo_id', $cdo_id)
                ->with([
                    'getEstadoValidacionEnProcesoPendiente',
                    'getEstadoValidacionEnProcesoPendiente.getUsuarioCreacion',
                    'getAcuseRecibo:est_id,cdo_id',
                    'getUblAcuseReciboEnProceso:est_id,cdo_id',
                    'getReciboBien:est_id,cdo_id',
                    'getUblReciboBienEnProceso:est_id,cdo_id',
                    'getConfiguracionProveedor' => function($query) {
                        $query->select([
                            'pro_id',
                            'pro_identificacion',
                            DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                        ]);
                    },
                    'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                        $query->select([
                            'ofe_id',
                            'ofe_identificacion',
                            'ofe_conexion_smtp',
                            'ofe_correo',
                            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                        ]);
                    }
                ])
                ->first();

            if ($documento) {
                $usuarioResponsable = User::select(['usu_id', 'usu_nombre'])
                    ->where('usu_id', $documento->cdo_usuario_responsable)
                    ->first();

                if ($request->accion == "asignar") {
                    if (!empty($documento->cdo_usuario_responsable)) {
                        if ($documento->cdo_usuario_responsable == auth()->user()->usu_id) {
                            $arrErrores[] = 'El Documento ['.$documento->rfa_prefijo . $documento->cdo_consecutivo .'] ya se encuentra asignado';
                            continue;
                        } else {
                            $arrErrores[] = 'El Documento ['.$documento->rfa_prefijo . $documento->cdo_consecutivo .'] se encuentra asignado para validación al usuario ['.$usuarioResponsable->usu_nombre.']';
                            continue;
                        }
                    } else 
                        $documento->update([
                            'cdo_usuario_responsable' => auth()->user()->usu_id
                        ]);
                } else if ($request->accion == "validar" || $request->accion == "rechazar" || $request->accion == "pagar") {
                    if (!empty($documento->cdo_usuario_responsable)) {
                        if ($documento->cdo_usuario_responsable !== auth()->user()->usu_id) {
                            $arrErrores[] = 'El Documento ['.$documento->rfa_prefijo . $documento->cdo_consecutivo .'] se encuentra asignado para validación al usuario ['.$usuarioResponsable->usu_nombre.']';
                            continue;
                        }
                    }

                    if ($request->accion == "validar" && !$documento->getEstadoValidacionEnProcesoPendiente) {
                        $arrErrores[] = 'El Documento ['.$documento->rfa_prefijo . $documento->cdo_consecutivo .'] No tiene como último estado VALIDACIÓN PENDIENTE';
                        continue;
                    }

                    $relacionUsuario = $documento->getEstadoValidacionEnProcesoPendiente->getUsuarioCreacion;
                    // Para la acción validar se verifica si el documento tiene estado ACUSERECIBO EXITOSO, de lo contrario se debe agendar si no se encuentra en proceso
                    // Adicionalmente, tener en cuenta que el evento ACUSERECIBO solo se procesa para FC
                    if ($request->accion == "validar" && $documento->cdo_clasificacion == 'FC' && empty($documento->getAcuseRecibo) && empty($documento->getUblAcuseReciboEnProceso)) {
                        $permisoAcuse = TenantTrait::usuarioAutorizacionesEventosDian($ofe->ofe_id, $documento->pro_id, $relacionUsuario->usu_id, 'use_acuse_recibo');

                        $permisoRecibo = TenantTrait::usuarioAutorizacionesEventosDian($ofe->ofe_id, $documento->pro_id, $relacionUsuario->usu_id, 'use_recibo_bien');

                        if(!empty($permisoAcuse) && !empty($permisoRecibo)) {
                            $arrAgendarAcuse[$relacionUsuario->usu_id]['cdo_id'][] = $cdo_id;
                            $arrAgendarAcuse[$relacionUsuario->usu_id]['usuario']  = $relacionUsuario;
                        } else {
                            $arrErrores[] = 'El usuario con identificación [' . $relacionUsuario->usu_identificacion . '] y nombre [' . $relacionUsuario->usu_nombre . '] no tiene autorización para generar el evento ACUSE DE RECIBO y/o RECIBO DEL BIEN para el documento ['.$documento->rfa_prefijo . $documento->cdo_consecutivo .']';
                            continue;
                        }
                    }
                    // Para la acción validar se verifica si el documento tiene estado RECIBOBIEN EXITOSO, de lo contrario se debe agendar si no se encuentra en proceso
                    // Adicionalmente, tener en cuenta que el evento RECIBOBIEN solo se procesa para FC
                    elseif ($request->accion == "validar" && $documento->cdo_clasificacion == 'FC' && empty($documento->getReciboBien) && empty($documento->getUblReciboBienEnProceso)) {
                        $permisoRecibo = TenantTrait::usuarioAutorizacionesEventosDian($ofe->ofe_id, $documento->pro_id, $relacionUsuario->usu_id, 'use_recibo_bien');

                        if(!empty($permisoRecibo)) {
                            $arrAgendarRecibo[$relacionUsuario->usu_id]['cdo_id'][] = $cdo_id;
                            $arrAgendarRecibo[$relacionUsuario->usu_id]['usuario']  = $relacionUsuario;
                        } else {
                            $arrErrores[] = 'El usuario con identificación [' . $relacionUsuario->usu_identificacion . '] y nombre [' . $relacionUsuario->usu_nombre . '] no tiene autorización para generar el evento RECIBO DEL BIEN para el documento ['.$documento->rfa_prefijo . $documento->cdo_consecutivo .']';
                            continue;
                        }
                    }
                } else if ($request->accion == "liberar") {
                    $documento->update([
                        'cdo_usuario_responsable' => null
                    ]);
                } else if ($request->accion == "pendiente") {
                    // Valida que los documentos deben tener un único grupo de trabajo asignado o que el proveedor este relacionado solamente con un único grupo de trabajo
                    // Y que el grupo de trabajo sea el mismo para todos los documentos (ya sea por grupo asignado al documento o pertenencia del proveedor)
                    if(!empty($documento->gtr_id))
                        $arrGtrIds[] = $documento->gtr_id;
                    else {
                        $gruposTrabajoProveedor = $documento->getConfiguracionProveedor->getProveedorGruposTrabajo->filter(function($grupoTrabajo) {
                            return $grupoTrabajo->getGrupoTrabajo->estado == 'ACTIVO';
                        })->pluck('gtr_id')->values()->toArray();

                        if(count($gruposTrabajoProveedor) >= 1) {
                            $gruposProveedor = !empty($gruposTrabajoProveedor) ? $gruposTrabajoProveedor : [];
                            $arrGtrIds = array_merge($arrGtrIds, $gruposProveedor);
                        }
                    }
                }
            } else {
                $arrErrores[] = 'El documento con ID ['.$cdo_id.'] no existe';
                continue;
            }

            if ($request->accion == 'enproceso' || $request->accion == 'pendiente') {
                // Valida que el usuario gestor tenga autorización para realizar el evento de ACUSE DE RECIBO y/o RECIBO DEL BIEN
                if ($request->accion == 'pendiente') {
                    $permisoAcuse = TenantTrait::usuarioAutorizacionesEventosDian($ofe->ofe_id, $documento->pro_id, auth()->user()->usu_id, 'use_acuse_recibo');

                    $permisoRecibo = TenantTrait::usuarioAutorizacionesEventosDian($ofe->ofe_id, $documento->pro_id, auth()->user()->usu_id, 'use_recibo_bien');

                    if(empty($permisoAcuse) || empty($permisoRecibo)) {
                        $arrErrores[] = 'El usuario [' . auth()->user()->usu_nombre . '] no tiene autorización para generar el evento ACUSE DE RECIBO y/o RECIBO DEL BIEN para el documento ['.$documento->rfa_prefijo . $documento->cdo_consecutivo .']';
                        continue;
                    }
                }

                // Antes de crear el estado se debe revisar el estado VALIDACION PENDIENTE o ENPROCESO más reciente para comparar
                // la información recibida en la petición contra lo pre-existente en el estado para modificar o no cada campo adicional
                if($documento->getEstadoValidacionEnProcesoPendiente && $documento->getEstadoValidacionEnProcesoPendiente->est_informacion_adicional) {
                    $informacionAdicionalEstado = !empty($documento->getEstadoValidacionEnProcesoPendiente->est_informacion_adicional) ? 
                        json_decode($documento->getEstadoValidacionEnProcesoPendiente->est_informacion_adicional, true) :
                        ['campos_adicionales' => []];

                    $estInformacionAdicional['campos_adicionales'] = $this->compararDatosEstadoPrevioValidacion(
                        $informacionAdicionalEstado['campos_adicionales'],
                        $estInformacionAdicional['campos_adicionales']
                    );
                } else {
                    // Para la creación del estado ENPROCESO la información de los campos adicionales debe llevar datos del usuario autenticado
                    $estInformacionAdicional['campos_adicionales'] = array_map(function($campoAdicional) {
                        return $this->agregarDatosUsuarioFechaCamposAdicionales($campoAdicional);
                    }, $estInformacionAdicional['campos_adicionales']);
                }
            }

            if ($request->accion == 'validar' || $request->accion == 'rechazar' || $request->accion == 'datos_aprobacion')
                $estInformacionAdicional['correos_notificados'] = $request->filled('correos_notificar') ? explode(',', $request->correos_notificar) : [];
            elseif ($request->accion == 'documento_validado')
                $estInformacionAdicional = json_decode($request->camposFnc);

            $arrDocumentosValidados[] = [
                'documento'                 => $documento,
                'est_informacion_adicional' => json_encode($estInformacionAdicional)
            ];

            if (($request->accion == 'validar' || $request->accion == 'rechazar' || $request->accion == 'datos_aprobacion') && $request->filled('correos_notificar'))
                $arrDocumentosNotificar[] = [
                    'documento'            => $documento,
                    'informacionAdicional' => $estInformacionAdicional
                ];
        }

        if($request->accion == 'pendiente') {
            $arrGtrIds = array_unique($arrGtrIds);

            if(count($arrGtrIds) > 1)
                return response()->json([
                    'message' => 'Error',
                    'errors' => ['Los documentos procesados y/o sus proveedores pertenecen a diferentes ' . (isset($gruposTrabajo->plural) && !empty($gruposTrabajo->plural) ? $gruposTrabajo->plural : ' Grupos de Trabajo')]
                ], 422);
        }

        foreach ($arrDocumentosValidados as $arrDocumentoValidado) {
            RepEstadoDocumentoDaop::create([
                'cdo_id'                    => $arrDocumentoValidado['documento']->cdo_id,
                'est_estado'                => $estEstado,
                'est_resultado'             => $estResultado,
                'est_ejecucion'             => 'FINALIZADO',
                'est_informacion_adicional' => $arrDocumentoValidado['est_informacion_adicional'],
                'usuario_creacion'          => auth()->user()->usu_id,
                'estado'                    => 'ACTIVO'
            ]);

            if($request->accion == 'pendiente')
                $arrDocumentoValidado['documento']->update([
                    'cdo_usuario_responsable_recibidos' => auth()->user()->usu_id
                ]);

            $documentoValidado .= (($arrDocumentoValidado['documento']->rfa_prefijo != '') ? $arrDocumentoValidado['documento']->rfa_prefijo : "") . $arrDocumentoValidado['documento']->cdo_consecutivo . ", ";
        }

        if(!empty($arrAgendarAcuse)) {
            foreach ($arrAgendarAcuse as $key => $agendarAcuse) {
                $this->crearAgendamientos($agendarAcuse['cdo_id'], [], $agendarAcuse['usuario'], 'RUBLACUSERECIBO', null, ['origen' => 'validacion']);
            }
        }

        if(!empty($arrAgendarRecibo)) {
            foreach ($arrAgendarRecibo as $key => $agendarRecibo) {
                $this->crearAgendamientos($agendarRecibo['cdo_id'], [], $agendarRecibo['usuario'], 'RUBLRECIBOBIEN', null, ['origen' => 'validacion']);
            }
        }

        if(!empty($arrDocumentosNotificar))
            foreach($arrDocumentosNotificar as $documentoNotificar)
                $this->enviarCorreosNotificacionValidacion($documentoNotificar['documento'], $documentoNotificar['informacionAdicional'], $request->accion);

        if (empty($arrErrores))
            return response()->json([
                'message' => "Documentos $msjResultado correctamente."
            ], 201);

        if ($documentoValidado != '')
            return response()->json([
                'message' => 'Documentos '.$msjResultado.' correctamente: ' . substr($documentoValidado, 0, -2),
                'errors'  => $arrErrores
            ], 201);
        else 
            return response()->json([
                'message' => 'Error al Procesar la Acción',
                'errors'  => $arrErrores
            ], 422);
    }

    /**
     * Genera una interfaz para registrar los eventos DIAN.
     *
     * @return ExcelExport
     */
    public function generarInterfaceRegistroEventos(): ExcelExport {
        return $this->generarInterfaceToTenant($this->columnasExcelEventos, 'registro_eventos_dian');
    }

    /**
     * Toma una interfaz en excel y procesa la infomración para agendar los eventos DIAN.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function cargarRegistroEventos(Request $request): JsonResponse {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $this->user = auth()->user();

        if (!$request->hasFile('archivo')) {
            return response()->json([
                'message' => 'Error al registrar los eventos DIAN',
                'errors' => ['No se ha subido ningún archivo.']
            ], 400);
        }

        $archivo = explode('.', $request->file('archivo')->getClientOriginalName());
        if (
            (!empty($request->file('archivo')->extension()) && !in_array($request->file('archivo')->extension(), $this->arrExtensionesPermitidas)) ||
            !in_array($archivo[count($archivo) - 1], $this->arrExtensionesPermitidas)
        )
            return response()->json([
                'message' => 'Error al registrar los eventos DIAN',
                'errors'  => ['Solo se permite la carga de archivos EXCEL.']
            ], 409);

        $data = $this->parserExcel($request, 'archivo', false, '', true);

        if (!empty($data)) {
            // Se obtinen las columnas de la interfaz sanitizadas
            $tempColumnas = [];
            foreach ($this->columnasExcelEventos as $k) {
                $tempColumnas[] = strtolower($this->sanear_string(str_replace(' ', '_', $k)));
            }

            // Se obtienen las columnas del excel cargado
            $columnas = [];
            foreach ($data as $fila => $columna) {
                $columnas = (array)$columna;
                break;
            }

            // Valida que las columnas del excel cargado correspondan con las columnas de la interfaz
            $diferenciasFaltan = array_diff($tempColumnas, array_keys($columnas));
            $diferenciasSobran = array_diff(array_keys($columnas), $tempColumnas);
            if (!empty($diferenciasFaltan) || !empty($diferenciasSobran)) {
                $errores = [];
                if(!empty($diferenciasFaltan))
                    $errores[] = 'Faltan las columnas: ' . strtoupper(str_replace('_', ' ', implode(', ', $diferenciasFaltan)));

                if(!empty($diferenciasSobran))
                    $errores[] = 'Sobran las columnas: ' . strtoupper(str_replace('_', ' ', implode(', ', $diferenciasSobran)));

                return response()->json([
                    'message' => 'La estructura del archivo no corresponde con la interfaz solicitada',
                    'errors'  => $errores
                ], 400);
            }
        } else {
            return response()->json([
                'message' => 'Error al registrar los eventos DIAN',
                'errors'  => ['El archivo subido no tiene datos.']
            ], 400);
        }

        $arrErrores         = [];
        $arrExisteOfe       = [];
        $arrExisteProveedor = [];
        $arrEventos         = [
            'acuse'       => [],
            'recibo_bien' => [],
            'aceptacion'  => [],
            'reclamo'     => []
        ];
        foreach ($data as $fila => $columnas) {

            $Acolumnas = $columnas;
            $columnas = (object) $columnas;

            $arrInfoEvento = [];
            $arrFaltantes  = $this->checkFields($Acolumnas, [
                'codigo_evento'
            ], $fila);

            if(!empty($arrFaltantes)){
                $vacio = $this->revisarArregloVacio($Acolumnas);
                if($vacio){
                    $arrErrores = $this->adicionarError($arrErrores, $arrFaltantes);
                } else {
                    unset($data[$fila]);
                }
            } else {
                if (isset($columnas->nit_ofe) && !empty($columnas->nit_ofe) && isset($columnas->nit_proveedor) && !empty($columnas->nit_proveedor) &&
                    isset($columnas->tipo_operacion) && !empty($columnas->tipo_operacion) && isset($columnas->prefijo) && !empty($columnas->prefijo) &&
                    isset($columnas->consecutivo) && !empty($columnas->consecutivo) && isset($columnas->fecha) && !empty($columnas->fecha)
                ) {
                    if (array_key_exists($columnas->nit_ofe, $arrExisteOfe)) {
                        $objExisteOfe = $arrExisteOfe[$columnas->nit_ofe];
                    } else {
                        $objExisteOfe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
                            ->where('ofe_identificacion', $columnas->nit_ofe)
                            ->validarAsociacionBaseDatos()
                            ->where('estado', 'ACTIVO')
                            ->first();
                        $arrExisteOfe[$columnas->nit_ofe] = $objExisteOfe;
                    }

                    if ($objExisteOfe) {
                        if (array_key_exists($columnas->nit_proveedor, $arrExisteProveedor)) {
                            $objExisteProveedor = $arrExisteProveedor[$columnas->nit_proveedor];
                        } else {
                            $objExisteProveedor = ConfiguracionProveedor::select(['pro_id', 'pro_identificacion'])
                                ->where('ofe_id', $objExisteOfe->ofe_id)
                                ->where('pro_identificacion', $columnas->nit_proveedor)
                                ->where('estado', 'ACTIVO')
                                ->first();
                            $arrExisteProveedor[$columnas->nit_proveedor] = $objExisteProveedor;
                        }

                        if (!$objExisteProveedor)
                            $arrErrores = $this->adicionarError($arrErrores, ['El Proveedor con identificación [' . $columnas->nit_proveedor . '] para el OFE con NIT [' . $columnas->nit_ofe . '] no existe o se encuentra INACTIVO.'], $fila);
                    } else
                        $arrErrores = $this->adicionarError($arrErrores, ['El OFE con NIT [' . $columnas->nit_ofe . '] no existe o se encuentra INACTIVO.'], $fila);


                    $arrInfoEvento['ofe_identificacion'] = $columnas->nit_ofe;
                    $arrInfoEvento['pro_identificacion'] = $columnas->nit_proveedor;
                    $arrInfoEvento['tde_codigo']         = $columnas->tipo_operacion;
                    $arrInfoEvento['rfa_prefijo']        = $columnas->prefijo;
                    $arrInfoEvento['cdo_consecutivo']    = $columnas->consecutivo;
                    $arrInfoEvento['cdo_fecha']          = $columnas->fecha;
                    $arrInfoEvento['cdo_observacion']    = $columnas->observacion;
                    $arrInfoEvento['cre_codigo']         = $columnas->concepto_reclamo;
                } elseif (isset($columnas->cufe) && !empty($columnas->cufe) && isset($columnas->fecha) && !empty($columnas->fecha)) {
                    $arrInfoEvento['cdo_cufe']        = $columnas->cufe;
                    $arrInfoEvento['cdo_fecha']       = $columnas->fecha;
                    $arrInfoEvento['cdo_observacion'] = $columnas->observacion;
                    $arrInfoEvento['cre_codigo']      = $columnas->concepto_reclamo;
                } else {
                    $arrErrores = $this->adicionarError($arrErrores, ["Debe ingresar las columnas NIT OFE, NIT PROVEEDOR, TIPO OPERACION, PREFIJO, CONSECUTIVO y FECHA o las columnas CUFE y FECHA, para registrar el evento."], $fila);
                }

                if(empty($arrErrores) && isset($columnas->codigo_evento) && !empty($columnas->codigo_evento)){
                    switch ($columnas->codigo_evento) {
                        case '30':
                            $arrEventos['acuse']['documentos'][]       = $arrInfoEvento;
                            break;
                        case '32':
                            $arrEventos['recibo_bien']['documentos'][] = $arrInfoEvento;
                            break;
                        case '33':
                            $arrEventos['aceptacion']['documentos'][]  = $arrInfoEvento;
                            break;
                        case '31':
                            $arrEventos['reclamo']['documentos'][]     = $arrInfoEvento;
                            break;
                        default:
                            $arrErrores = $this->adicionarError($arrErrores, ["El código de evento [".$columnas->codigo_evento."] no es válido."], $fila);
                            break;
                    }
                }
            }

            if ($fila % 500 === 0) {
                $this->renovarConexion($this->user);
            }
        }

        if(!empty($arrErrores)){
            $this->crearLogErrores($arrErrores);

            return response()->json([
                'message' => 'Error al registrar los eventos DIAN',
                'errors'  => ['Verifique el Log de Errores'],
            ], 400);
        } else {
            $arrResultados = [];

            // Realiza el agendamiento por cada array de eventos DIAN
            $this->enviarRegistroEventos($arrEventos['acuse'], $arrResultados, 'ACUSE');
            $this->enviarRegistroEventos($arrEventos['recibo_bien'], $arrResultados, 'RECIBOBIEN');
            $this->enviarRegistroEventos($arrEventos['aceptacion'], $arrResultados, 'ACEPTACION');
            $this->enviarRegistroEventos($arrEventos['reclamo'], $arrResultados, 'RECLAMO');

            if (!empty($arrResultados)) {
                $this->crearLogErrores($arrResultados);

                return response()->json([
                    'message' => 'Error al agendar los eventos DIAN',
                    'errors'  => ['Verifique el Log de Errores'],
                ], 400);
            }

            return response()->json([
                'message' => "Se realizó el agendamiento de eventos DIAN correctamente."
            ], 201);
        }
    }

    /**
     * Crea el registro para el log de errores del registro de eventos DIAN.
     *
     * @param array $arrEventos Información de los documentos por cada evento
     * @param array $arrResultados Array para almacenar el resultado de la petición
     * @param string $evento Nombre del evento a procesar
     * @return void
     */
    private function enviarRegistroEventos(array $arrEventos, array &$arrResultados, string $evento): void {
        if (!empty($arrEventos)) {
            $request = new Request();
            $arrEventos['evento'] = $evento;

            $request->merge($arrEventos);

            $respuesta = $this->registrarEvento($request, true);
            $respuesta = json_decode($respuesta->getContent(), true);

            if (array_key_exists('fallidos', $respuesta) && !empty($respuesta['fallidos']))
                $arrResultados = array_merge($arrResultados, $respuesta['fallidos']);
        }
    }

    /**
     * Crea el registro para el log de errores del registro de eventos DIAN.
     *
     * @param array $errores Errores generados en el registro de eventos
     * @return void
     */
    private function crearLogErrores(array $errores): void {
        $this->user = auth()->user();

        $agendamiento = AdoAgendamiento::create([
            'usu_id'                  => $this->user->usu_id,
            'age_proceso'             => 'FINALIZADO',
            'age_cantidad_documentos' => 0,
            'age_prioridad'           => null,
            'usuario_creacion'        => $this->user->usu_id,
            'fecha_creacion'          => date('Y-m-d H:i:s'),
            'estado'                  => 'ACTIVO',
        ]);

        EtlProcesamientoJson::create([
            'pjj_tipo'                => 'REGEVENTOS',
            'pjj_json'                => json_encode([]),
            'pjj_procesado'           => 'SI',
            'age_estado_proceso_json' => 'FINALIZADO',
            'pjj_errores'             => json_encode($errores),
            'age_id'                  => $agendamiento->age_id,
            'usuario_creacion'        => $this->user->usu_id,
            'fecha_creacion'          => date('Y-m-d H:i:s'),
            'estado'                  => 'ACTIVO'
        ]);
    }

    /**
     * Envía los correos de notificación de validación del documento a los correos seleccionados en el proceso.
     *
     * @param RepCabeceraDocumentoDaop $documento Documento electrónico que está siendo validado
     * @param array $estInformacionAdicional Información adicional del evento de validación
     * @param string $accion Acción realizada (validar - rechazar)
     * @return void
     */
    private function enviarCorreosNotificacionValidacion(RepCabeceraDocumentoDaop $documento, array $estInformacionAdicional, string $accion) {
        if($accion == 'validar' || $accion == 'datos_apobacion') {
            $noAprobacion = collect($estInformacionAdicional['campos_adicionales'])
                ->firstWhere('campo', 'no_aprobacion');

            $fechaValidacion = collect($estInformacionAdicional['campos_adicionales'])
                ->firstWhere('campo', 'fecha_validacion');
        } elseif($accion == 'rechazar') {
            $noRechazo = collect($estInformacionAdicional['campos_adicionales'])
                ->firstWhere('campo', 'no_rechazo');
        }

        $observacion = collect($estInformacionAdicional['campos_adicionales'])
            ->firstWhere('campo', 'observacion');

        $dataEmail = [
            "accion"             => $accion,
            "asunto"             => $documento->rfa_prefijo . $documento->cdo_consecutivo,
            "ofe_identificacion" => $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            "ofe_razon_social"   => $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo,
            "pro_identificacion" => $documento->getConfiguracionProveedor->pro_identificacion,
            "pro_razon_social"   => $documento->getConfiguracionProveedor->nombre_completo,
            "no_aprobacion"      => isset($noAprobacion) ? $noAprobacion['valor'] : '',
            "fecha_validacion"   => isset($fechaValidacion) ? $fechaValidacion['valor'] : '',
            "no_rechazo"         => isset($noRechazo) ? $noRechazo['valor'] : '',
            "observacion"        => isset($observacion) ? $observacion['valor'] : '',
            "app_url"            => config('variables_sistema.APP_URL_WEB'),
            "remite"             => config('variables_sistema.EMPRESA'),
            "direccion"          => config('variables_sistema.DIRECCION'),
            "ciudad"             => config('variables_sistema.CIUDAD'),
            "telefono"           => config('variables_sistema.TELEFONO'),
            "web"                => config('variables_sistema.WEB'),
            "email"              => config('variables_sistema.EMAIL'),
            "facebook"           => config('variables_sistema.FACEBOOK'),
            "twitter"            => config('variables_sistema.TWITTER')
        ];

        $this->logoNotificacionAsociacion($dataEmail, $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion);

        if (
            !empty($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp) &&
            (
                array_key_exists('driver', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp) || 
                array_key_exists('from_email', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)
            )
        ) {
            // Establece el email del remitente del correo
            if(array_key_exists('from_email', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)) {
                $emailRemitente = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp['from_email'];
            } else {
                $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;
            }
            
            // Verifica si existe conexión especial a un servidor SMTP del OFE
            if (array_key_exists('driver', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)) {
                TenantSmtp::setSmtpConnection($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp);
            } else {
                MainTrait::setMailInfo();
            }
        } else {
            // Establece el email del remitente del correo
            $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;
            MainTrait::setMailInfo();
        }

        $destinatarios = array_unique($estInformacionAdicional['correos_notificados']);
        foreach ($destinatarios as $correo) {
            Mail::send(
                'emails.validacionDocumento',
                $dataEmail,
                function ($message) use ($documento, $correo, $emailRemitente){
                    $message->from(
                        $emailRemitente,
                        $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo . ' (' . $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . ')'
                    );
                    $message->sender($emailRemitente, $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo);
                    $message->subject($documento->rfa_prefijo . $documento->cdo_consecutivo);
                    $message->to($correo);
                }
            );
        }
    }

    /**
     * Retorna el listado de usuarios que pertenecen a la misma base de datos del usuario autenticado.
     * 
     * La lista es utilizada en el proceso de validación de documentos, cuando se va a validar un documento.
     *
     * @return JsonResponse
     */
    public function getListaUsuariosNotificarValidacion(): JsonResponse {
        $user     = auth()->user();
        $usuarios = User::select([
                'usu_id',
                'usu_email',
                'usu_identificacion',
                DB::raw('CONCAT(usu_identificacion, " - ", usu_nombre) as usuario')
            ])
            ->when(!empty($user->bdd_id_rg), function($query) use ($user) {
                $query->where('bdd_id_rg', $user->bdd_id_rg);
            }, function($query) use ($user) {
                $query->where('bdd_id', $user->bdd_id);
            })
            ->whereIn('usu_type', ['ADMINISTRADOR', 'OPERATIVO'])
            ->where('usu_email', 'not like', '%portal%')
            ->where('estado', 'ACTIVO')
            ->get();

        if($usuarios->isEmpty())
            return response()->json([
                'message' => 'Consulta de Usuarios',
                'errors'  => ['No se encontraron usuarios']
            ], ResponseHttp::HTTP_NOT_FOUND);

        return response()->json([
            'data' => [
                'usuarios_notificar_validacion' => $usuarios
            ]
        ], ResponseHttp::HTTP_OK);
    }

    /**
     * Agenda la generación del reporte Log de Validación de Documentos.
     *
     * @param  Request$request Parámetros de la petición
     * @return JsonResponse
     */
    public function agendarLogValidacionDocumentos(Request $request): JsonResponse {
        $validador = Validator::make($request->all(), [
            'ofe_id'                 => 'required|numeric',
            'tipo'                   => 'required|string|in:recepcion-log-validacion',
            'cdo_fecha_desde'        => 'required|date_format:Y-m-d',
            'cdo_fecha_hasta'        => 'required|date_format:Y-m-d',
            'pro_id'                 => 'nullable|numeric',
            'cdo_origen'             => 'nullable|string',
            'cdo_clasificacion'      => 'nullable|string',
            'rfa_prefijo'            => 'nullable|string',
            'cdo_consecutivo'        => 'nullable|string',
            'campo_validacion'       => 'nullable|string',
            'valor_campo_validacion' => 'nullable|string'
        ]);

        if ($validador->fails())
            return response()->json([
                'message' => 'Errores en la petición',
                'errors'  => $validador->errors()->all()
            ], ResponseHttp::HTTP_BAD_REQUEST);

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
            ->where('ofe_id', $request->ofe_id)
            ->where('ofe_recepcion_fnc_activo', 'SI')
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Errores en la petición',
                'errors'  => ['El OFE seleccionado no existe, no se encuentra activo o no está habilitado para el proceso de Validación de Documentos']
            ], ResponseHttp::HTTP_BAD_REQUEST);

        $request->merge([
            'json' => json_encode($request->all())
        ]);

        return $this->agendarReporte($request);
    }

    /**
     * Genera el reporte Log Validación de Documentos
     *
     * @param Request $request
     * @return array
     */
    public function generarLogValidacionDocumentos(Request $request): array {
        $titulos = [
            'NIT RECEPTOR',
            'RECEPTOR',
            'NIT EMISOR',
            'EMISOR',
            'PREFIJO',
            'CONSECUTIVO',
            'FECHA DOCUMENTO',
            'CAMPO',
            'VALOR',
            'ID USUARIO',
            'USUARIO',
            'CORREO',
            'FECHA Y HORA'
        ];

        $filas = [];
        RepCabeceraDocumentoDaop::select([
                'cdo_id',
                'ofe_id',
                'pro_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'fecha_creacion'
            ])
            ->where('ofe_id', $request->ofe_id)
            ->whereBetween('cdo_fecha', [$request->cdo_fecha_desde . ' 00:00:00', $request->cdo_fecha_hasta . ' 23:59:59'])
            ->when($request->filled('pro_id'), function($query) use ($request) {
                $query->whereIn('pro_id', explode(',', $request->pro_id));
            })
            ->when($request->filled('cdo_origen'), function($query) use ($request) {
                $query->where('cdo_origen', $request->cdo_origen);
            })
            ->when($request->filled('cdo_clasificacion'), function($query) use ($request) {
                $query->where('cdo_clasificacion', $request->cdo_clasificacion);
            })
            ->when($request->filled('rfa_prefijo'), function($query) use ($request) {
                $query->where('rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use ($request) {
                $query->where('cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->with([
                'getEstadosValidacionEnProcesoPendiente' => function($query) {
                    $query->select(['est_id', 'cdo_id', 'est_estado', 'est_resultado', 'est_informacion_adicional', 'usuario_creacion', 'fecha_creacion'])
                        ->with([
                            'getUsuarioCreacion:usu_id,usu_identificacion,usu_nombre,usu_email'
                        ]);
                },
                'getEstadosValidacionValidado' => function($query) {
                    $query->select(['est_id', 'cdo_id', 'est_estado', 'est_resultado', 'est_informacion_adicional', 'usuario_creacion', 'fecha_creacion'])
                        ->with([
                            'getUsuarioCreacion:usu_id,usu_identificacion,usu_nombre,usu_email'
                        ]);
                },
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                        'pro_id',
                        'pro_identificacion',
                        DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                    ]);
                },
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        'ofe_conexion_smtp',
                        'ofe_correo',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                    ]);
                }
            ])
            ->where(function($query) use ($request) {
                $query->whereHas('getEstadosValidacionEnProcesoPendiente', function($query) use ($request) {
                    $query->select(['est_id', 'cdo_id', 'est_estado', 'est_resultado', 'est_informacion_adicional'])
                        ->when($request->filled('campo_validacion') && $request->filled('valor_campo_validacion'), function($query) use ($request) {
                            $query->whereJsonContains('est_informacion_adicional->campos_adicionales', ['campo' => $request->campo_validacion, 'valor' => $request->valor_campo_validacion]);
                        });
                })->orWhereHas('getEstadosValidacionValidado', function($query) use ($request) {
                    $query->select(['est_id', 'cdo_id', 'est_estado', 'est_resultado', 'est_informacion_adicional']);
                });
            })
            ->orderBy('fecha_creacion', 'asc')
            ->get()
            ->sortBy('getEstadosValidacionEnProcesoPendiente.fecha_creacion')
            ->sortBy('getEstadosValidacionValidado.fecha_creacion')
            ->map(function($documento) use (&$filas) {
                foreach($documento->getEstadosValidacionEnProcesoPendiente as $estadoDocumento) {
                    if(empty($estadoDocumento->est_informacion_adicional))
                        continue;
                        
                    $estInformacionAdicional = json_decode($estadoDocumento->est_informacion_adicional, true);

                    if(array_key_exists('campos_adicionales', $estInformacionAdicional) && !empty($estInformacionAdicional['campos_adicionales'])) {
                        foreach($estInformacionAdicional['campos_adicionales'] as $campoAdicional) {
                            if(!array_key_exists('modificado', $campoAdicional) || (array_key_exists('modificado', $campoAdicional) && $campoAdicional['modificado'] != 'NO'))
                                $filas[] = [
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo,
                                    $documento->getConfiguracionProveedor->pro_identificacion,
                                    $documento->getConfiguracionProveedor->nombre_completo,
                                    $documento->rfa_prefijo,
                                    $documento->cdo_consecutivo,
                                    $documento->cdo_fecha,
                                    $campoAdicional['campo'],
                                    $campoAdicional['valor'],
                                    array_key_exists('id_usuario', $campoAdicional) ? $campoAdicional['id_usuario'] : $estadoDocumento->getUsuarioCreacion->usu_identificacion,
                                    array_key_exists('nombre_usuario', $campoAdicional) ? $campoAdicional['nombre_usuario'] : $estadoDocumento->getUsuarioCreacion->usu_nombre,
                                    array_key_exists('correo_usuario', $campoAdicional) ? $campoAdicional['correo_usuario'] : $estadoDocumento->getUsuarioCreacion->usu_email,
                                    array_key_exists('fecha', $campoAdicional) ? $campoAdicional['fecha'] : $estadoDocumento->fecha_creacion->format('Y-m-d H:i:s')
                                ];
                        }
                    }
                }

                foreach($documento->getEstadosValidacionValidado as $estadoDocumento) {
                    if(empty($estadoDocumento->est_informacion_adicional))
                        continue;
                        
                    $estInformacionAdicional = json_decode($estadoDocumento->est_informacion_adicional, true);

                    if(array_key_exists('campos_adicionales', $estInformacionAdicional) && !empty($estInformacionAdicional['campos_adicionales'])) {
                        foreach($estInformacionAdicional['campos_adicionales'] as $campoAdicional) {
                            $filas[] = [
                                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo,
                                $documento->getConfiguracionProveedor->pro_identificacion,
                                $documento->getConfiguracionProveedor->nombre_completo,
                                $documento->rfa_prefijo,
                                $documento->cdo_consecutivo,
                                $documento->cdo_fecha,
                                $campoAdicional['campo'],
                                $campoAdicional['valor'],
                                $estadoDocumento->getUsuarioCreacion->usu_identificacion,
                                $estadoDocumento->getUsuarioCreacion->usu_nombre,
                                $estadoDocumento->getUsuarioCreacion->usu_email,
                                $estadoDocumento->fecha_creacion->format('Y-m-d H:i:s')
                            ];
                        }
                    }
                }
            });

        date_default_timezone_set('America/Bogota');
        $nombreArchivo = 'log_validacion_documentos_' . date('YmdHis');
        $archivoExcel  = $this->toExcel($titulos, $filas, $nombreArchivo);

        return [
            'ruta'   => $archivoExcel,
            'nombre' => $nombreArchivo . '.xlsx'
        ];
    }
}
