<?php

namespace App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop;

use JWTAuth;
use Validator;
use ZipArchive;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use App\Http\Models\User;
use App\Traits\MainTrait;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use GuzzleHttp\Exception\RequestException;
use App\Http\Modulos\Utils\ExcelExports\ExcelExport;
use App\Services\Emision\EtlCabeceraDocumentoService;
use openEtl\Tenant\Traits\TenantIdentificacionOfesTrait;
use App\Http\Modulos\Documentos\BaseDocumentosController;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Repositories\Emision\EtlCabeceraDocumentoRepository;
use App\Http\Modulos\Utils\ExcelExports\ExcelExportPersonalizado;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDocumentosAnexosDaop\EtlDocumentoAnexoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlConsecutivosDocumentos\EtlConsecutivoDocumento;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoDaop;

/**
 * Clase que permite instanciar el MainTrait.
 * 
 * Esto permite no afectar la funcionalidad actual del Trait dentro de la clase principal de cabecera de documentos y poder acceder a los métodos no estáticos del mismo
 */
class MainTraitClass {
    use MainTrait, TenantIdentificacionOfesTrait;

    /**
     * Método que permie realizar el llamado a la clase NO estática guardarArchivoEnDisco del MainTrait.
     *
     * @param string $ofeIdentificacion Identificación del OFE
     * @param EtlCabeceraDocumentoDaop|RepCabeceraDocumentoDaop $documento Registro de cabecera creado para el documento
     * @param string $proceso Indica si el proceso corresponde a emisión o recepción
     * @param string $prefijoArchivo Prefijo que se aplica al nombre del archivo que se creará
     * @param string $extensionArchivo Extensión del archivo que se creará
     * @param string $contenidoArchivo Contenido del archivo en base64
     * @param string $nombreArchivo Nombre con el que se debe almacenar el archivo
     * @return string Nombre del archivo almacenado en disco
     */
    public function fnGuardarArchivoEnDisco(string $ofeIdentificacion, $documento, string $proceso, string $prefijoArchivo, string $extensionArchivo, string $contenidoArchivo, $nombreArchivo = null) {
        return $this->guardarArchivoEnDisco($ofeIdentificacion, $documento, $proceso, $prefijoArchivo, $extensionArchivo, $contenidoArchivo, $nombreArchivo = null);
    }
}

/**
 * Clase principal del controlador.
 */
class EtlCabeceraDocumentoDaopController extends BaseDocumentosController {
    public const PROCESO_EMISION         = 'emision';

    public const ESTADO_EDI              = 'EDI';
    public const ESTADO_UBL              = 'UBL';
    public const ESTADO_DO               = 'DO';
    public const ESTADO_ATTACHEDDOCUMENT = 'UBLATTACHEDDOCUMENT';
    public const ESTADO_NOTIFICACION     = 'NOTIFICACION';
    public const ESTADO_ACEPTACION       = 'ACEPTACION';
    public const ESTADO_RECHAZO          = 'RECHAZO';

    public const TIPO_PDF  = 'pdf';
    public const TIPO_XML  = 'xml';
    public const TIPO_JSON = 'json';

    // Identificación de DHL Express
    public const NIT_DHLEXPRESS = '860502609';

    /**
     * Instancia del servicio de cabecera en emisión.
     * 
     * Clase encargada de la lógica de procesamiento de data
     *
     * @var EtlCabeceraDocumentoService
     */
    protected $emisionCabeceraService;

    /**
     * Instancia del servicio de cabecera en emisión.
     * 
     * Clase encargada de la lógica de procesamiento de data
     *
     * @var EtlCabeceraDocumentoRepository
     */
    protected $emisionCabeceraRepository;

    /**
     * Contiene las columnas que pueden ser listadas.
     *
     * @var array
     */
    public $columns = [
        'etl_cabecera_documentos_daop.cdo_id',
        'etl_cabecera_documentos_daop.cdo_origen',
        'etl_cabecera_documentos_daop.cdo_clasificacion',
        'etl_cabecera_documentos_daop.cdo_lote',
        'etl_cabecera_documentos_daop.ofe_id',
        'etl_cabecera_documentos_daop.adq_id',
        'etl_cabecera_documentos_daop.rfa_id',
        'etl_cabecera_documentos_daop.mon_id',
        'etl_cabecera_documentos_daop.rfa_prefijo',
        'etl_cabecera_documentos_daop.cdo_consecutivo',
        'etl_cabecera_documentos_daop.cdo_fecha',
        'etl_cabecera_documentos_daop.cdo_hora',
        'etl_cabecera_documentos_daop.cdo_vencimiento',
        'etl_cabecera_documentos_daop.cdo_observacion',
        'etl_cabecera_documentos_daop.cdo_representacion_grafica_documento',
        'etl_cabecera_documentos_daop.cdo_valor_sin_impuestos',
        'etl_cabecera_documentos_daop.cdo_valor_sin_impuestos_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_impuestos',
        'etl_cabecera_documentos_daop.cdo_impuestos_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_valor_a_pagar',
        'etl_cabecera_documentos_daop.cdo_valor_a_pagar_moneda_extranjera',
        'etl_cabecera_documentos_daop.cdo_cufe',
        'etl_cabecera_documentos_daop.cdo_signaturevalue',
        'etl_cabecera_documentos_daop.cdo_procesar_documento',
        'etl_cabecera_documentos_daop.cdo_fecha_procesar_documento',
        'etl_cabecera_documentos_daop.cdo_fecha_validacion_dian',
        'etl_cabecera_documentos_daop.cdo_fecha_acuse',
        'etl_cabecera_documentos_daop.cdo_estado',
        'etl_cabecera_documentos_daop.cdo_fecha_estado',
        'etl_cabecera_documentos_daop.usuario_creacion',
        'etl_cabecera_documentos_daop.fecha_creacion',
        'etl_cabecera_documentos_daop.fecha_modificacion',
        'etl_cabecera_documentos_daop.estado',
        'etl_cabecera_documentos_daop.fecha_actualizacion',
    ];

    /**
     * Constructor.
     */
    public function __construct(EtlCabeceraDocumentoRepository $emisionCabeceraRepository, EtlCabeceraDocumentoService $emisionCabeceraService) {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:EmisionDocumentosPorExcelDescargarFactura'
        ])->only([
            'generarInterfaceFacturas'
        ]);

        $this->middleware([
            'VerificaMetodosRol:EmisionDocumentosPorExcelDescargarNotas'
        ])->only([
            'generarInterfaceNotasCreditoDebito'
        ]);

        $this->middleware([
            'VerificaMetodosRol:DocumentosSoporteDocumentosPorExcelDescargar'
        ])->only([
            'generarInterfaceDocumentosSoporte'
        ]);

        $this->middleware([
            'VerificaMetodosRol:DocumentosSoporteNotasCreditoPorExcelDescargar'
        ])->only([
            'generarInterfaceNotaCreditoDocumentosSoporte'
        ]);

        $this->middleware(['VerificaMetodosRol:EmisionDocumentosSinEnvio,EmisionDocumentosSinEnvioEnvio,EmisionDocumentosSinEnvioDescargar,EmisionDocumentosSinEnvioDescargarJson,EmisionDocumentosSinEnvioDescargarExcel,EmisionDocumentosSinEnvioDescargarCertificado,DocumentosSoporteDocumentosSinEnvio,FacturacionWebCrearDocumentoSoporte,FacturacionWebEditarDocumentoSoporte,FacturacionWebVerDocumentoSoporte,DocumentosSoporteDocumentosSinEnvioDescargar,DocumentosSoporteDocumentosSinEnvioDescargarExcel'])->only([
            'getListaDocumentos',
        ]);

        $this->middleware(['VerificaMetodosRol:EmisionDocumentosSinEnvioEnvio'])->only([
            'enviarDocumentos'
        ]);

        $this->middleware(['VerificaMetodosRol:EmisionAceptacionTacita'])->only([
            'agendarEstadosAceptacionTacita'
        ]);

        $this->middleware(['VerificaMetodosRol:EmisionDocumentosEnviados,EmisionDocumentosEnviadosDescargar,EmisionDocumentosEnviadosEnviarCorreo,EmisionDocumentosEnviadosDescargarJson,EmisionDocumentosEnviadosDescargarExcel,EmisionDocumentosEnviadosDescargarCertificado,DocumentosSoporteDocumentosEnviados,DocumentosSoporteDocumentosEnviadosDescargar,DocumentosSoporteDocumentosEnviadosDescargarExcel,DocumentosSoporteDocumentosEnviadosEnviarGestionDocumentos'])->only([
            'getListaDocumentosEnviados',
        ]);

        $this->middleware(['VerificaMetodosRol:EmisionDocumentosAnexos,EmisionCargaDocumentosAnexos,EmisionEliminarDocumentosAnexos'])->only([
            'cargarDocumentosAnexos',
            'eliminarDocumentosAnexos',
        ]);

        $this->middleware(['VerificaMetodosRol:CambiarEstadoDocumentosEnviados,CambiarEstadoDocumentosSinEnvio'])->only([
            'cambiarEstadoDocumentos',
            'cambioEstadoDocumento'
        ]);

        $this->middleware(['VerificaMetodosRol:ModificarDocumento'])->only([
            'consultarDataDocumentoModificar',
            'modificarDocumentosPickupCash',
            'modificarDocumentos'
        ]);

        $this->middleware([
            'VerificaMetodosRol:FacturacionWebCrearFactura,FacturacionWebEditarFactura,FacturacionWebVerFacturaFacturacionWebCrearNotaCredito,FacturacionWebEditarNotaCredito,FacturacionWebVerNotaCredito,FacturacionWebCrearNotaDebito,FacturacionWebEditarNotaDebito,FacturacionWebVerNotaDebito,FacturacionWebCrearDocumentoSoporte,FacturacionWebEditarDocumentoSoporte,FacturacionWebVerDocumentoSoporte,FacturacionWebCrearNotaCreditoDS,FacturacionWebEditarNotaCreditoDS,FacturacionWebVerNotaCreditoDS'
        ])->only([
            'consultarDocumentoElectronicoReferencia',
            'obtenerInformacionDocumentoElectronico'
        ]);

        $this->middleware(['VerificaMetodosRol:EmisionReemplazarPdf'])->only([
            'reemplazarPdf'
        ]);

        $this->middleware([
            'VerificaMetodosRol:EmisionReporteBackground,DocumentosSoporteReporteBackground'
        ])->only([
            'agendarReporte',
            'procesarAgendamientoReporte',
            'listarReportesDescargar'
        ]);

        $this->emisionCabeceraRepository = $emisionCabeceraRepository;
        $this->emisionCabeceraService    = $emisionCabeceraService;
    }

    /**
     * Setea las columnas.
     *
     * @return void
     */
    private function setColumnas(){
        $this->columns = [
            'cdo_id',
            'cdo_lote',
            'rfa_pefijo',
            'cdo_consecutivo',
            'cdo_clasificacion',
            'cdo_fecha',
            'cdo_hora',
            'cdo_valor_a_pagar_moneda_extranjera',
            'cdo_valor_a_pagar',
            'cdo_origen',
            'cdo_cufe',
            'cdo_procesar_documento',
            'estado',
        ];
    }

    /**
     *  Retorna la lista de documentos sin envío según los filtros de búsqueda.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response|BinaryFileResponse|Collection|array
     */
    public function getListaDocumentos(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit','512M');

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

        if($request->has('adq_id') && $request->adq_id !== '' && !empty($request->adq_id)) {
            if (!is_array($request->adq_id)) {
                $arrAdqIds = explode(",", $request->adq_id);
            } else {
                $arrAdqIds = $request->adq_id;
            }

            $special_where_conditions[] = [
                'type'  => 'in',
                'field' => 'etl_cabecera_documentos_daop.adq_id',
                'value' => $arrAdqIds
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
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_razon_social,ofe_nombre_comercial,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres',
            'getConfiguracionAdquirente:adq_id,adq_identificacion,adq_id_personalizado,adq_razon_social,adq_nombre_comercial,adq_primer_apellido,adq_segundo_apellido,adq_primer_nombre,adq_otros_nombres',
            'getParametrosMoneda:mon_id,mon_codigo,mon_descripcion',
            'getParametrosMonedaExtranjera:mon_id,mon_codigo,mon_descripcion',
            'getConfiguracionResolucionesFacturacion:rfa_id,rfa_tipo,rfa_prefijo,rfa_resolucion'
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

        $proceso = 'emision';
        if ($request->filled('proceso') && $request->proceso == 'documento_soporte')
            $proceso = 'documento_soporte';

        if ($request->has('excel') && !empty($request->excel)) {
            array_push($this->columns,
                'etl_cabecera_documentos_daop.top_id',
                'etl_cabecera_documentos_daop.mon_id',
                'etl_cabecera_documentos_daop.cdo_documento_referencia',
                'etl_cabecera_documentos_daop.cdo_cargos',
                'etl_cabecera_documentos_daop.cdo_cargos_moneda_extranjera',
                'etl_cabecera_documentos_daop.cdo_descuentos',
                'etl_cabecera_documentos_daop.cdo_descuentos_moneda_extranjera',
                'etl_cabecera_documentos_daop.cdo_retenciones_sugeridas',
                'etl_cabecera_documentos_daop.cdo_retenciones_sugeridas_moneda_extranjera',
                'etl_cabecera_documentos_daop.cdo_redondeo',
                'etl_cabecera_documentos_daop.cdo_redondeo_moneda_extranjera',
                'etl_cabecera_documentos_daop.cdo_anticipo',
                'etl_cabecera_documentos_daop.cdo_anticipo_moneda_extranjera',
                'etl_cabecera_documentos_daop.cdo_retenciones',
                'etl_cabecera_documentos_daop.cdo_retenciones_moneda_extranjera'
            );

            array_push($arrRelaciones, 
                'getTipoOperacion:top_id,top_codigo,top_descripcion'
            );

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
                'PREFIJO DOCUMENTO REFERENCIA',
                'CONSECUTIVO DOCUMENTO REFERENCIA',
                'FECHA DOCUMENTO REFERENCIA',
                'CUFE DOCUMENTO REFERENCIA',
                'OBSERVACION',
                'MONEDA',
                'TOTAL ANTES DE IMPUESTOS',
                'IMPUESTOS',
                'TOTAL',
                'ESTADO'
            ];

            if ($proceso == 'emision') {
                $arrTitulos1 = [
                    'CARGOS',
                    'DESCUENTOS',
                    'REDONDEO'
                ];
                array_splice($titulos, 21, 0, $arrTitulos1);

                $arrTitulos2 = [
                    'ANTICIPO',
                    'RETENCIONES'
                ];
                array_splice($titulos, 25, 0, $arrTitulos2);
            }

            if (($request->filled('cdo_clasificacion') && ($request->cdo_clasificacion == 'DS' || $request->cdo_clasificacion == 'DS_NC')) || ($request->filled('proceso') && $request->proceso == 'documento_soporte')) {
                $titulos[0] = "NIT RECEPTOR";
                $titulos[1] = "RECEPTOR";
                $titulos[3] = "NIT VENDEDOR";
                $titulos[5] = "VENDEDOR";
            }

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
                    'do' => function($cdo_documento_referencia) {
                        if($cdo_documento_referencia != '') {
                            $documento_referencia = json_decode($cdo_documento_referencia);
                            $prefijo = '';
                            if(json_last_error() === JSON_ERROR_NONE && is_array($documento_referencia)) {
                                foreach($documento_referencia as $referencia) {
                                    $prefijo .= (isset($referencia->prefijo) && $referencia->prefijo != '') ? $referencia->prefijo . ', ' : '';
                                }
                                $prefijo = !empty($prefijo) ? substr($prefijo, 0, -2) : '';
                            }
                            return $prefijo;
                        }
                    },
                    'field' => 'cdo_documento_referencia',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($cdo_documento_referencia) {
                        if($cdo_documento_referencia != '') {
                            $documento_referencia = json_decode($cdo_documento_referencia);
                            $consecutivo = '';
                            if(json_last_error() === JSON_ERROR_NONE && is_array($documento_referencia)) {
                                foreach($documento_referencia as $referencia) {
                                    $consecutivo .= (isset($referencia->consecutivo) && $referencia->consecutivo != '') ? $referencia->consecutivo.', ' : '';
                                }
                                $consecutivo = !empty($consecutivo) ? substr($consecutivo, 0, -2) : '';
                            }
                            return $consecutivo;
                        }
                    },
                    'field' => 'cdo_documento_referencia',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($cdo_documento_referencia) {
                        if($cdo_documento_referencia != '') {
                            $documento_referencia = json_decode($cdo_documento_referencia);
                            $fecha_emision = '';
                            if(json_last_error() === JSON_ERROR_NONE && is_array($documento_referencia)) {
                                foreach($documento_referencia as $referencia) {
                                    $fecha_emision .= (isset($referencia->fecha_emision) && $referencia->fecha_emision != '') ? $referencia->fecha_emision.', ' : '';
                                }
                                $fecha_emision = !empty($fecha_emision) ? substr($fecha_emision, 0, -2) : '';
                            }
                            return $fecha_emision;
                        }
                    },
                    'field' => 'cdo_documento_referencia',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function($cdo_documento_referencia) {
                        if($cdo_documento_referencia != '') {
                            $documento_referencia = json_decode($cdo_documento_referencia);
                            $cufe = '';
                            if(json_last_error() === JSON_ERROR_NONE && is_array($documento_referencia)) {
                                foreach($documento_referencia as $referencia) {
                                    $cufe .= (isset($referencia->cufe) && $referencia->cufe != '') ? $referencia->cufe.', ' : '';
                                }
                                $cufe = !empty($cufe) ? substr($cufe, 0, -2) : '';
                            }
                            return $cufe;
                        }
                    },
                    'field' => 'cdo_documento_referencia',
                    'validation_type'=> 'callback'
                ],
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
                        if ($cdo_valor_sin_impuestos > 0 || $extra_data['cdo_valor_sin_impuestos_moneda_extranjera'] > 0)
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
                    'do' => function ($cdo_impuestos, $extra_data) {
                        if ($cdo_impuestos > 0 || $extra_data['cdo_impuestos_moneda_extranjera'] > 0) 
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_impuestos_moneda_extranjera'] : $cdo_impuestos;
                    },
                    'extra_data' => [
                        'cdo_impuestos_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_impuestos',
                    'validation_type'=> 'callback'
                ],
                [
                    'do' => function ($cdo_valor_a_pagar, $extra_data) {
                        if ($cdo_valor_a_pagar > 0 || $extra_data['cdo_valor_a_pagar_moneda_extranjera'] > 0) 
                            return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_valor_a_pagar_moneda_extranjera'] : $cdo_valor_a_pagar;
                    },
                    'extra_data' => [
                        'cdo_valor_a_pagar_moneda_extranjera',
                        'get_parametros_moneda.mon_codigo',
                    ],
                    'field' => 'cdo_valor_a_pagar',
                    'validation_type'=> 'callback'
                ],
                'estado'
            ];

            if ($proceso == 'emision') {
                $arrColumnas1 = [
                    [
                        'do' => function ($cdo_cargos, $extra_data) {
                            if ($cdo_cargos > 0 || $extra_data['cdo_cargos_moneda_extranjera'] > 0) {
                                return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_cargos_moneda_extranjera'] : $cdo_cargos;
                            }
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
                            $totalMonedaExtranjera = $extra_data['cdo_descuentos_moneda_extranjera'] + $extra_data['cdo_retenciones_sugeridas_moneda_extranjera'];
                            $cdo_descuentos = $extra_data['cdo_descuentos'] + $extra_data['cdo_retenciones_sugeridas'];

                            if ($cdo_descuentos > 0 || $totalMonedaExtranjera > 0) {
                                return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? number_format($totalMonedaExtranjera, 2, '.', '') : number_format($cdo_descuentos, 2, '.', '');
                            }
                        },
                        'extra_data' => [
                            'cdo_descuentos',
                            'cdo_descuentos_moneda_extranjera',
                            'cdo_retenciones_sugeridas',
                            'cdo_retenciones_sugeridas_moneda_extranjera',
                            'get_parametros_moneda.mon_codigo',
                        ],
                        'field' => 'cdo_descuentos',
                        'validation_type'=> 'callback'
                    ],
                    [
                        'do' => function ($cdo_redondeo, $extra_data) {
                            if ($cdo_redondeo != 0 || $extra_data['cdo_redondeo_moneda_extranjera'] != 0) {
                                return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_redondeo_moneda_extranjera'] : $cdo_redondeo;
                            }
                        },
                        'extra_data' => [
                            'cdo_redondeo_moneda_extranjera',
                            'get_parametros_moneda.mon_codigo',
                        ],
                        'field' => 'cdo_redondeo',
                        'validation_type'=> 'callback'
                    ]
                ];
                array_splice($columnas, 21, 0, $arrColumnas1);

                $arrColumnas2 = [
                    [
                        'do' => function ($cdo_anticipo, $extra_data) {
                            if ($cdo_anticipo > 0 || $extra_data['cdo_anticipo_moneda_extranjera'] > 0) {
                                return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_anticipo_moneda_extranjera'] : $cdo_anticipo;
                            }
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
                            if ($cdo_retenciones > 0 || $extra_data['cdo_retenciones_moneda_extranjera'] > 0) {
                                return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_retenciones_moneda_extranjera'] : $cdo_retenciones;
                            }
                        },
                        'extra_data' => [
                            'cdo_retenciones_moneda_extranjera',
                            'get_parametros_moneda.mon_codigo',
                        ],
                        'field' => 'cdo_retenciones',
                        'validation_type'=> 'callback'
                    ]
                ];
                array_splice($columnas, 25, 0, $arrColumnas2);
            }

            // Para los archivos de Excel se debe verificar si el OFE tiene configuradas columnas especiales en ofe_columnas_personalizadas
            // De ser el caso, se debe buscar dicha información en información adicional del documento para incluirlo en el Excel
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_columnas_personalizadas'])
                ->find($request->ofe_id);

            if(isset($ofe->ofe_columnas_personalizadas) && !empty($ofe->ofe_columnas_personalizadas) && array_key_exists('EXCEL', $ofe->ofe_columnas_personalizadas)) {
                foreach($ofe->ofe_columnas_personalizadas['EXCEL'] as $columnaExcel) {
                    $titulos[]  = $columnaExcel['titulo'];
                    $columnas[] = 'get_dad_documentos_daop.0.cdo_informacion_adicional.' . $columnaExcel['campo'];
                }

                $arrRelaciones[] = 'getDadDocumentosDaop:cdo_id,cdo_informacion_adicional';
            }

            $documentos = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, true, true, $where_has_conditions, null, $proceso, 'array');
            $procesoBackground = ($request->filled('pjj_tipo') && $request->pjj_tipo == 'ENOENVIADOS') ? true : false;

            return $this->printExcelDocumentos($documentos, $titulos, $columnas, 'documentos_sin_envio', $procesoBackground);
        }

        $data = $this->listDocuments($required_where_conditions, SEARCH_METHOD, $optional_where_conditions, $special_where_conditions, $arrRelaciones, false, false, $where_has_conditions, null, $proceso, 'array');

        $registros = $data['data']->map(function($registro) {
            $documento                 = is_array($registro) ? $registro : $registro->toArray();
            $documentoAnexo            = '';
            $documentoEstadoPickupCash = '';

            if (isset($registro['getDocumentosAnexos']) && count($registro['getDocumentosAnexos']) > 0) {
                $documentoAnexo = 'SI';
            }

            if (isset($registro['getPickupCashDocumento'])) {
                $documentoEstadoPickupCash = 'PICKUP_CASH_FINALIZADO';
            } else if (isset($registro['getPickupCashDocumentoExitoso'])) {
                $documentoEstadoPickupCash = 'PICKUP_CASH_PROCESO';
            }

            $documento['aplica_documento_anexo'] = $documentoAnexo;
            $documento['estado_pickup_cash']     = $documentoEstadoPickupCash;

            return $documento;
        });

        return response()->json([
            "total"     => $data['total'],
            "filtrados" => $data['filtrados'],
            "data"      => $registros
        ], 200);
    }

    /**
     * Retorna una lista de documentos enviados a la DIAN de acuerdo a los parámetros recibidos.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse|BinaryFileResponse|Collection
     */
    public function getListaDocumentosEnviados(Request $request) {
        return $this->emisionCabeceraService->getListaDocumentosEnviados($request);
    }

    /**
     * Retorna un Excel con el listado de documentos sin envío de acuerdo a los parámetros recibidos.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response|BinaryFileResponse
     * @throws \Exception
     */
    public function descargarListaDocumentosSinEnvio(Request $request) {
        return $this->getListaDocumentos($request);
    }

    /**
     * Realiza una búsqueda en la tabla FAT.
     * 
     * Tener en cuenta que en la tabla FAT solo se registra información de documentos en emisión que ha pasado al histórico de particionamiento
     *
     * @param string $campoBuscar Campo sobre el cual realizar la búsqueda
     * @param string $valorBuscar Valor a buscar
     * @return array
     */
    private function busquedaFat(string $campoBuscar, string $valorBuscar): array {
        $select = [\DB::raw('DISTINCT(cdo_lote) as cdo_lote')];
        $objDocumentos = EtlFatDocumentoDaop::select($select);

        if($campoBuscar == 'rfa_prefijo' && ($valorBuscar == '' || $valorBuscar == null || $valorBuscar == 'null')) {
            $objDocumentos->where(function($query) {
                $query->whereNull('rfa_prefijo')
                    ->orWhere('rfa_prefijo', '');
            });
        } elseif($campoBuscar == 'rfa_prefijo' && ($valorBuscar != '' && $valorBuscar != null && $valorBuscar != 'null')) {
            $objDocumentos->where('rfa_prefijo', $valorBuscar);
        } else {
            $objDocumentos->where($campoBuscar, 'like', '%' . $valorBuscar . '%');
        }

        $documentos = [];
        $objDocumentos->whereNotNull('cdo_lote')
            ->get()
            ->map(function ($item) use (&$documentos) {
                $select = [
                    \DB::raw('COUNT(*) as cantidad_documentos,
                    MIN(cdo_consecutivo) as consecutivo_inicial,
                    MAX(cdo_consecutivo) as consecutivo_final')
                ];

                $objDocumentos = EtlFatDocumentoDaop::select($select)
                    ->where('cdo_lote', $item->cdo_lote)
                    ->first();

                $documentos[] = [
                    'cdo_lote'            => $item->cdo_lote,
                    'cantidad_documentos' => $objDocumentos->cantidad_documentos,
                    'consecutivo_inicial' => $objDocumentos->consecutivo_inicial,
                    'consecutivo_final'   => $objDocumentos->consecutivo_final,
                ];
            });

        return $documentos;
    }

    /**
     * Realiza una búsqueda de documentos en emisión.
     *
     * @param Request $request Parámetros de la petición
     * @param string $campoBuscar Campo sobre el cual realizar la búsqueda
     * @param string $valorBuscar Valor a buscar
     * @param string $filtroColumnas Tipo de filtro
     * @return Illuminate\Http\JsonResponse
     */
    public function buscarDocumentos(Request $request, string $campoBuscar, string $valorBuscar, string $filtroColumnas): JsonResponse {
        $select = [\DB::raw('DISTINCT(cdo_lote) as cdo_lote')];
        $objDocumentos = EtlCabeceraDocumentoDaop::select($select);
        switch ($filtroColumnas) {
            case 'basico':
            case 'avanzado':
                if($campoBuscar != 'rfa_prefijo') $objDocumentos = $objDocumentos->where($campoBuscar, 'like', '%' . $valorBuscar . '%');
                break;

            default:
                if($campoBuscar != 'rfa_prefijo') $objDocumentos = $objDocumentos->where($campoBuscar, $valorBuscar);
                break;
        }

        if ($request->has('enviados')){
            if ($request->enviados == 'SI'){
                $objDocumentos->where('cdo_procesar_documento', 'SI')
                    ->whereNotNull('cdo_fecha_procesar_documento');
            } else {
                $objDocumentos->whereRaw("(cdo_procesar_documento IS NULL OR cdo_procesar_documento = 'NO')")
                    ->whereNull('cdo_fecha_procesar_documento');
            }
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
                    \DB::raw('COUNT(*) as cantidad_documentos,
                    MIN(cdo_consecutivo) as consecutivo_inicial,
                    MAX(cdo_consecutivo) as consecutivo_final')
                ];

                $objDocumentos = EtlCabeceraDocumentoDaop::select($select)
                    ->where('cdo_lote', $item->cdo_lote)
                    ->first();

                $documentos[] = [
                    'cdo_lote'            => $item->cdo_lote,
                    'cantidad_documentos' => $objDocumentos->cantidad_documentos,
                    'consecutivo_inicial' => $objDocumentos->consecutivo_inicial,
                    'consecutivo_final'   => $objDocumentos->consecutivo_final,
                ];
            });

        // Si la búsqueda se realiza sobre el campo cdo_lote para documentos enviados, se debe buscar la información sobre la tabla FAT si la BD asociada al usuario esta configurada para particionamiento
        if($request->filled('enviados') && $request->enviados == 'SI' && $campoBuscar == 'cdo_lote') {
            // if(auth()->user()->baseDatosTieneParticionamiento()) // TODO: Se comenta esta línea porque hay BD en donde se esta particionando pero estan marcadas con NO, igual la búsqueda se debe realizar
                $documentos = array_merge($documentos, $this->busquedaFat($campoBuscar, $valorBuscar));
        }

        return response()->json([
            'data' => $documentos
        ], 200);
    }

    /**
     * Permite encontrar documentos de acuerdo a los criterios de búsqueda.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function encontrarDocumento(Request $request) {
        $documentos = EtlCabeceraDocumentoDaop::select($this->columns)
            ->where('ofe_id', $request->ofe_id)
            ->where('cdo_consecutivo', 'like', '%' . $request->cdo_consecutivo . '%')
            ->where('cdo_fecha', '>=', $request->cdo_fecha_desde . ' 00:00:00')
            ->where('cdo_fecha', '<=', $request->cdo_fecha_hasta . ' 23:59:59')
            ->where('estado', 'ACTIVO');

        if($request->has('adq_id') && !empty($request->adq_id))
            $documentos = $documentos->whereIn('adq_id', explode(',', $request->adq_id));

        if($request->has('cdo_clasificacion') && !empty($request->cdo_clasificacion))
            $documentos = $documentos->where('cdo_clasificacion', $request->cdo_clasificacion);
            
        if($request->has('rfa_prefijo') && !empty($request->rfa_prefijo))
            $documentos = $documentos->where('rfa_prefijo', $request->rfa_prefijo);

        $documentos = $documentos->with([
                'getConfiguracionAdquirente' => function($query) {
                    $query->select([
                        'adq_id',
                        'adq_identificacion',
                        DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo')
                    ]);
                },
                'getDocumentosAnexos'
            ])
            ->get();

        return response()->json([
            'data' => $documentos
        ], 200);
    }

    /**
     * Permite cargar documentos anexos a un documento en el sistema.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function cargarDocumentosAnexos(Request $request) {
        $documento = EtlCabeceraDocumentoDaop::select($this->columns)
            ->with([
                'getConfiguracionAdquirente:adq_id,adq_identificacion',
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion'
            ])
            ->find($request->cdo_id);

        if ($documento) {
            if ($request->totalDocumentosAnexos > 0) {
                $erroresExisten = [];
                $erroresAnexos  = [];

                // Itera sobre los documentos anexos para validarlos
                for ($i = 0; $i < $request->totalDocumentosAnexos; $i++) {
                    $anexo = 'archivo' . ($i + 1);
                    $validarAnexo = $this->validarDocumentoAnexo($request->$anexo, $anexo);

                    if ($validarAnexo !== true) {
                        $erroresAnexos[] = $validarAnexo;
                    }
                }
                
                if (count($erroresAnexos) > 0) {
                    return response()->json([
                        'message' => 'Errores con los documentos anexos',
                        'errors' => $erroresAnexos
                    ], 422);
                }

                $user = auth()->user();
                $baseDatos = $user->getBaseDatos->bdd_nombre;

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
                MainTrait::crearDiscoDinamico('documentos_anexos_emision', config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_EMISION'));

                // Itera sobre los documentos anexos para grabarlos en la ruta y base de datos
                for ($i = 0; $i < $request->totalDocumentosAnexos; $i++) {
                    $anexo       = 'archivo' . ($i + 1);
                    $descripcion = 'descripcion' . ($i + 1);

                    $uuid = Uuid::uuid4();
                    $nuevoNombre = $uuid->toString();
                    $request->$anexo->storeAs(
                        $baseDatos . '/' . $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion  . '/' . $documento->getConfiguracionAdquirente->adq_identificacion . '/' . date('Y') .
                            '/' . date('m') . '/' . date('d') . '/' . date('H') . '/' . date('i'),
                        $nuevoNombre . "." . $request->$anexo->extension(),
                        'documentos_anexos_emision'
                    );

                    // Tamaño del archivo
                    $tamano = $request->$anexo->getSize();

                    // Nombre original del archivo
                    $nombreOriginal    = $request->$anexo->getClientOriginalName();
                    $nombreOriginal    = $this->sanear_string($nombreOriginal);
                    $extensionOriginal = explode('.', $nombreOriginal);

                    // Verifica que el documento anexo no exista en la BD para el mismo documento
                    $existe = EtlDocumentoAnexoDaop::where('cdo_id', $request->cdo_id)
                        ->where('dan_nombre', $nombreOriginal)
                        ->where('dan_tamano', $tamano)
                        ->first();

                    if (!$existe) {
                        $documentoAnexo['cdo_id']           = $request->cdo_id;
                        $documentoAnexo['dan_lote']         = $lote;
                        $documentoAnexo['dan_uuid']         = $nuevoNombre;
                        $documentoAnexo['dan_tamano']       = $tamano;
                        $documentoAnexo['dan_nombre']       = $extensionOriginal[0] . '.' . $request->$anexo->extension();
                        $documentoAnexo['dan_descripcion']  = $request->$descripcion;
                        $documentoAnexo['estado']           = 'ACTIVO';
                        $documentoAnexo['usuario_creacion'] = $user->usu_id;

                        $crearDocumentoAnexo = EtlDocumentoAnexoDaop::create($documentoAnexo);
                        if (!$crearDocumentoAnexo) {
                            $erroresAnexos[] = 'Se presentó un error 409 al intentar crear el registro del anexo ' . $nombreOriginal;
                        }
                    } else {
                        $erroresExisten[] = $nombreOriginal;
                    }
                }

                if (count($erroresAnexos) > 0) {
                    return response()->json([
                        'message' => 'Se presentaron errores al intentar crear los recursos correspondientes',
                        'errors' => $erroresAnexos
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
     * Descargar documentos anexos.
     *
     * @param  $ids IDs de los documentos anexos a descargar
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function descargarDocumentosAnexos($ids) {
        try {
            // Usuario autenticado
            $user = auth()->user();

            // Base de datos del usuario autenticado
            $baseDatos = $user->getBaseDatos->bdd_nombre;

            // Cuando la petición se hace para el proceso emisión, ids llega compuesto por el cdo_id + | + ids
            if(strstr($ids, '|'))
                list($cdo_id, $ids) = explode('|', $ids);

            // Genera un array con los Ids de los documentos anexos
            $arrIds = explode(',', $ids);

            // Ruta al disco de los documentos anexos en emisión
            MainTrait::setFilesystemsInfo();
            $disco = config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_EMISION');

            if (count($arrIds) == 1) {
                $documentoAnexo = EtlDocumentoAnexoDaop::where('dan_id', $arrIds[0])
                    ->with([
                        'getCabeceraDocumentosDaop:cdo_id,ofe_id,adq_id',
                        'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                        'getCabeceraDocumentosDaop.getConfiguracionAdquirente:ofe_id,adq_id,adq_identificacion'
                    ])
                    ->first();

                if (!$documentoAnexo) {
                    $documentoAnexo = $this->emisionCabeceraService->obtenerDocumentoAnexoHistorico($cdo_id, $arrIds[0]);
                }

                $extension = explode('.', $documentoAnexo->dan_nombre);
                $fh = \Carbon\Carbon::parse($documentoAnexo->fecha_creacion);
                $ruta = $disco . '/' . $baseDatos . '/' . $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                    $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_identificacion . '/' . $fh->year . '/' . $fh->format('m') . '/' . $fh->format('d') . '/' . 
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
                    $documentoAnexo = EtlDocumentoAnexoDaop::where('dan_id', $dan_id)
                        ->with([
                            'getCabeceraDocumentosDaop:cdo_id,ofe_id,adq_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo',
                            'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                            'getCabeceraDocumentosDaop.getConfiguracionAdquirente:ofe_id,adq_id,adq_identificacion'
                        ])
                        ->first();

                    if (!$documentoAnexo) {
                        $documentoAnexo = $this->emisionCabeceraService->obtenerDocumentoAnexoHistorico($cdo_id, $dan_id);
                    }

                    $extension = explode('.', $documentoAnexo->dan_nombre);
                    $fh   = \Carbon\Carbon::parse($documentoAnexo->fecha_creacion);
                    $ruta = $disco . '/' . $baseDatos . '/' . $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                        $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_identificacion . '/' . $fh->year . '/' . $fh->format('m') . '/' . $fh->format('d') . '/' . 
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
     * Eliminar documentos anexos.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
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
            MainTrait::crearDiscoDinamico('documentos_anexos_emision', config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_EMISION'));

            foreach($arrIds as $dan_id) {
                $documentoAnexo = EtlDocumentoAnexoDaop::select(['dan_id', 'cdo_id', 'dan_nombre', 'dan_uuid', 'fecha_creacion'])
                    ->where('dan_id', $dan_id)
                    ->with([
                        'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                        'getCabeceraDocumentosDaop.getConfiguracionAdquirente:ofe_id,adq_id,adq_identificacion'
                    ])
                    ->first();

                if (!$documentoAnexo) {
                    $historico = true;
                    $documentoAnexo = $this->emisionCabeceraService->obtenerDocumentoAnexoHistorico($request->cdo_id, $dan_id);
                }

                if($documentoAnexo) {
                    $fh          = \Carbon\Carbon::parse($documentoAnexo->fecha_creacion);
                    $extension   = explode('.', $documentoAnexo->dan_nombre);
                    $rutaArchivo = $baseDatos . '/' . $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                        $documentoAnexo->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_identificacion . '/' . $fh->year . '/' . $fh->format('m') . '/' . $fh->format('d') . '/' . 
                        $fh->format('H') . '/' . $fh->format('i') . '/' . $documentoAnexo->dan_uuid. '.' . $extension[count($extension) - 1];

                    // Elimina el documento anexo del disco
                    Storage::disk('documentos_anexos_emision')->delete($rutaArchivo);

                    // Elimina el documento anexo de la base de datos
                    if(!isset($historico))
                        $documentoAnexo->delete();
                    else
                        $this->emisionCabeceraService->eliminarDocumentoAnexoHistorico($request->cdo_id, $dan_id);
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
     * Retorna un string con el tipo de documento y un prefijo por defecto dependiendo del tipo de documento.
     *
     * @param  string $cdo_clasificacion
     * @param  string $rfa_prefijo
     * @return array
     */
    private function tipoDocumento($cdo_clasificacion, $rfa_prefijo) {
        $tipoDoc = '';
        $prefijo = '';
        switch ($cdo_clasificacion) {
            case 'FC':
                $tipoDoc = 'Factura';
                $prefijo = $rfa_prefijo;
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
     * Requiere el envio de documentos a la DIAN por numero de documento o lote para su validacion
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enviarDocumentos(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        $objUser = auth()->user();
        $arrErrores = [];
        if (!$request->has('lote') && !$request->has('documentos')) {
            return response()->json([
                'message' => 'Errores al Enviar los Documentos',
                'errors' => ['No se ha especificado ningún documento a enviar.']
            ], 400);
        } else {
            if ($request->has('lote') && !empty($request->lote)) {
                $documentos = EtlCabeceraDocumentoDaop::select($this->columns)
                    ->whereRaw("(cdo_procesar_documento IS NULL OR cdo_procesar_documento='NO')")
                    ->whereNull('cdo_fecha_procesar_documento')
                    ->where('cdo_lote', $request->lote)
                    ->with([
                        'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_prioridad_agendamiento',
                        'getPickupCashDocumento'
                    ])
                    ->get();
            } elseif ($request->has('documentos') && !empty($request->documentos)) {
                $documentos = EtlCabeceraDocumentoDaop::select($this->columns)
                    ->whereRaw("(cdo_procesar_documento IS NULL OR cdo_procesar_documento='NO')")
                    ->whereIn('cdo_id', explode(',',$request->documentos))
                    ->with([
                        'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_prioridad_agendamiento',
                        'getPickupCashDocumento'
                    ])
                    ->get();
            }

            if (!$documentos->isEmpty()) {
                $__documentos = [];
                // Variable que aplica para DHL Express proceso Pickup Cash
                $falloPickupCash = [];
                // Variable que aplica para la validacion de estado
                $falloEstado = [];
                // Variable que indica si se deben procesar el bloque de documentos
                $mensajesDocumentosProvisionales = [];
                // Variable que indica si se deben procesar el bloque de documentos
                $procesarDocumento = true;
                foreach ($documentos as $doc) {
                    //Varibale que indica que cumplio las dos condiciones
                    $cumpleCondiciones = true;
                    
                    // Aplica para DHL Express, documentos FC, cdo_representacion_grafica_documento igual a 9
                    // se debe validar que exista el estado PICKUP-CASH exitoso y finalizado
                    if(
                        $doc->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == self::NIT_DHLEXPRESS &&
                        $doc->cdo_clasificacion == 'FC' &&
                        $doc->cdo_representacion_grafica_documento == '9'
                    ) {
                        if(!($doc->getPickupCashDocumento != null && $doc->getPickupCashDocumento != '')) {
                            $cumpleCondiciones = false;
                            $procesarDocumento = false;
                            $falloPickupCash[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no tiene estado Pickup-Cash finalizado';
                        }
                    }

                    if ($doc->estado != 'ACTIVO' && $doc->estado != 'PROVISIONAL') {
                        $cumpleCondiciones = false;
                        $procesarDocumento = false;
                        $falloEstado[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' se encuentra en estado INACTIVO';
                    }

                    if ($doc->estado == 'PROVISIONAL') {
                        // Actualiza datos de fechas de documento, vencimiento y vencimiento del medio de pago
                        $documento = EtlCabeceraDocumentoDaop::find($doc->cdo_id)
                            ->update([
                                'cdo_fecha'       => date('Y-m-d'),
                                'cdo_hora'        => date('H:i:s'),
                                'cdo_vencimiento' => date('Y-m-d')
                            ]);

                        // El estado del coumento es PROVISIONAL por lo que se le debe asignar el consecutivo definitivo
                        $consecutivo = $this->getConsecutivoDocumento($doc->ofe_id, $doc->rfa_id, trim($doc->rfa_prefijo));

                        if($consecutivo === false) {
                            $cumpleCondiciones = false;
                            $procesarDocumento = false;
                            $falloEstado[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' El periodo [' . date('Ym') . '] no se encuentra aperturado';
                        } elseif(empty($consecutivo)){
                            $cumpleCondiciones = false;
                            $procesarDocumento = false;
                            $falloEstado[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' El estado del documento es PROVISIONAL y no se pudo generar el consecutivo definitivo para el documento';
                        } else {
                            $mensajesDocumentosProvisionales[] = 'El Documento Provisional ' . $doc->cdo_consecutivo .  ' se envió a la DIAN con el consecutivo ' . $consecutivo;

                            $doc->update([
                                'cdo_consecutivo' => $consecutivo,
                                'estado'          => 'ACTIVO'
                            ]);
                        }
                    }

                    if ($cumpleCondiciones == true) {
                        // Verifica que NO exista un estado DO Exitoso para el documento
                        $doExitoso = EtlEstadosDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $doc->cdo_id)
                            ->where('est_estado', 'DO')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->first();

                        if(!$doExitoso) {
                            $__documentos[] = $doc;
                        }      
                    }
                }

                if($procesarDocumento == true) {
                    $grupos = array_chunk($__documentos, $objUser->getBaseDatos->bdd_cantidad_procesamiento_ubl);
                    $fecha =  Carbon::now();
                    foreach ($grupos as $grupo) {
                        $agendamiento = AdoAgendamiento::create([
                            'usu_id'                  => $objUser->usu_id,
                            'age_proceso'             => 'UBL',
                            'bdd_id'                  => $objUser->getBaseDatos->bdd_id,
                            'age_cantidad_documentos' => count($grupo),
                            'age_prioridad'           => !empty($grupo[0]->getConfiguracionObligadoFacturarElectronicamente->ofe_prioridad_agendamiento) ? $grupo[0]->getConfiguracionObligadoFacturarElectronicamente->ofe_prioridad_agendamiento : null,
                            'usuario_creacion'        => $objUser->usu_id,
                            'estado'                  => 'ACTIVO',
                        ]);
                        foreach ($grupo as $documento) {
                            $documento->update([
                                'cdo_procesar_documento' => 'SI',
                                'cdo_fecha_procesar_documento' => $fecha
                            ]);

                            $estado                   = new EtlEstadosDocumentoDaop();
                            $estado->cdo_id           = $documento->cdo_id;
                            $estado->est_estado       = 'UBL';
                            $estado->age_id           = $agendamiento->age_id;
                            $estado->age_usu_id       = $objUser->usu_id;
                            $estado->usuario_creacion = $objUser->usu_id;
                            $estado->estado           = 'ACTIVO';
                            $estado->save();
                        }
                    }
                } else {
                    $arrErrores = array_merge($falloPickupCash,$falloEstado);
                }
            } else {
                return response()->json([
                    'message' => 'Errores al Enviar los Documentos',
                    'errors' => ['Los documentos seleccionados No Existen.']
                ], 400);
            }
        }

        if (count($arrErrores) === 0) {
            return response()->json([
                'success' => true,
                'mensajesDocumentosProvisionales' => $mensajesDocumentosProvisionales
            ], 200);
        } else {
            return response()->json([
                'message' => 'Errores al Enviar los Documentos',
                'errors' => $arrErrores
            ], 400);
        }
    }

    /**
     * Agenda estados de documentos para Aceptación Tacita.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function agendarEstadosAceptacionTacita(Request $request) {
        $cdoIds     = explode(',', $request->cdoIds);
        $objUser    = auth()->user();
        $documentos = [];
        if (!empty($cdoIds)) {
            $documentos = EtlCabeceraDocumentoDaop::select($this->columns)
                ->whereIn('cdo_id', $cdoIds)
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_prioridad_agendamiento'
                ])
                ->whereHas('getDoFinalizado')
                ->whereDoesntHave('getAceptadoT')
                ->whereDoesntHave('getUblAceptadoTEnProceso')
                ->whereDoesntHave('getAceptadoTEnProceso')
                ->whereDoesntHave('getAceptado')
                ->whereDoesntHave('getRechazado')
                ->get()
                ->toArray();
                
            if (count($cdoIds) == count($documentos)) {
                $grupos = array_chunk($documentos, $objUser->getBaseDatos->bdd_cantidad_procesamiento_ubl);
                foreach ($grupos as $grupo) {
                    $agendamiento = AdoAgendamiento::create([
                        'usu_id'                  => $objUser->usu_id,
                        'age_proceso'             => 'EUBLACEPTACIONT',
                        'bdd_id'                  => $objUser->getBaseDatos->bdd_id,
                        'age_cantidad_documentos' => count($grupo),
                        'age_prioridad'           => !empty($grupo[0]->getConfiguracionObligadoFacturarElectronicamente->ofe_prioridad_agendamiento) ? $grupo[0]->getConfiguracionObligadoFacturarElectronicamente->ofe_prioridad_agendamiento : null,
                        'usuario_creacion'        => $objUser->usu_id,
                        'estado'                  => 'ACTIVO',
                    ]);

                    foreach ($grupo as $documento) {
                        $estado                     = new EtlEstadosDocumentoDaop();
                        $estado['cdo_id']           = $documento['cdo_id'];
                        $estado['est_estado']       = 'UBLACEPTACIONT';
                        $estado['age_id']           = $agendamiento->age_id;
                        $estado['age_usu_id']       = $objUser->usu_id;
                        $estado['usuario_creacion'] = $objUser->usu_id;
                        $estado['estado']           = 'ACTIVO';
                        $estado->save();
                    }
                }

                return response()->json([
                    'message' => 'Estado agendado correctamente'
                ], 200);

            } else {
                return response()->json([
                    'message' => 'Error al procesar los documentos',
                    'errors' => ['Alguno o varios de los documentos seleccionados no han sido validados en la DIAN o han sido aceptados tácitamente, aceptados expresamente o rechazados']
                ], 400);
            }
        } else {
            return response()->json([
                'message' => 'No se encontraron documentos para procesar',
                'errors' => ['Los documentos a consultar no existen']
            ], 400);
        }
    }

    /**
     * Envia documentos a la DIAN.
     * 
     * Aplica solo para documentos previamente enviados y que cumplan con las validaciones del método,
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enviarDocumentosDian(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        $objUser = auth()->user();
        $arrErrores = [];
        if (!$request->has('documentos')) {
            return response()->json([
                'message' => 'Errores al Enviar los Documentos',
                'errors' => ['No se ha especificado ningún documento a enviar.']
            ], 400);
        } else {
            $documentos = EtlCabeceraDocumentoDaop::select($this->columns)
                ->whereIn('cdo_id', explode(',', $request->documentos))
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                    'getUblEnProceso',
                    'getDoEnProceso',
                    'getNotificacionEnProceso',
                ])
                ->withCount([
                    'getEstadosDocumentosDaop' => function($query) {
                        $query->where('est_estado', 'DO');
                    }
                ])
                ->get();

            if (!$documentos->isEmpty()) {
                $__documentos = [];
                foreach ($documentos as $doc) {
                    //Varibale que indica que cumplio las dos condiciones
                    $cumpleCondiciones = true;
                    if ($doc->estado != 'ACTIVO') {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' se encuentra en estado INACTIVO';
                    }

                    if ($doc->cdo_procesar_documento != 'SI' || $doc->getUblEnProceso != null || $doc->getDoEnProceso != null || $doc->getNotificacionEnProceso != null) {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no puede ser enviado a la DIAN, verifique que no se encuentre en proceso de generación del XML-UBL, WS DIAN o NOTIFICACIÓN';
                    }

                    // Si el documento tiene fecha de envío a la DIAN y NO tiene estados DO fallidos ni exitosos se debe generar error
                    if(!empty($doc->cdo_fecha_validacion_dian) && $doc->get_estados_documentos_daop_count == 0) {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no puede ser enviado a la DIAN, por favor comuníquese con Open Tecnología';
                    }

                    if ($cumpleCondiciones == true) {
                        // Verifica que NO exista un estado DO Exitoso para el documento
                        $doExitoso = EtlEstadosDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $doc->cdo_id)
                            ->where('est_estado', 'DO')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->first();

                        if(!$doExitoso) {
                            $__documentos[] = $doc;
                        }
                    }
                }

                if(empty($arrErrores)) {
                    $grupos = array_chunk($__documentos, $objUser->getBaseDatos->bdd_cantidad_procesamiento_ubl);
                    foreach ($grupos as $grupo) {
                        $agendamiento = AdoAgendamiento::create([
                            'usu_id'                  => $objUser->usu_id,
                            'age_proceso'             => 'UBL',
                            'bdd_id'                  => $objUser->getBaseDatos->bdd_id,
                            'age_cantidad_documentos' => count($grupo),
                            'age_prioridad'           => null,
                            'usuario_creacion'        => $objUser->usu_id,
                            'estado'                  => 'ACTIVO',
                        ]);
                        foreach ($grupo as $documento) {
                            $estado                   = new EtlEstadosDocumentoDaop();
                            $estado->cdo_id           = $documento->cdo_id;
                            $estado->est_estado       = 'UBL';
                            $estado->age_id           = $agendamiento->age_id;
                            $estado->age_usu_id       = $objUser->usu_id;
                            $estado->usuario_creacion = $objUser->usu_id;
                            $estado->estado           = 'ACTIVO';
                            $estado->save();
                        }
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Errores al Enviar los Documentos',
                    'errors' => ['Los documentos seleccionados no existen o no se encontraron en la data operativa.']
                ], 400);
            }
        }

        if (empty($arrErrores)) {
            return response()->json([
                'message' => 'Documentos enviados a la DIAN'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Errores al Enviar los Documentos',
                'errors' => $arrErrores
            ], 422);
        }
    }

    /**
     * Modifica documentos para que puedan ser editados para el proyecto Pickup Cash.
     * 
     * Aplica solo para documentos de DHL Express rechazados por la DIAN y con RG 9
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function modificarDocumentosPickupCash(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        $objUser = auth()->user();
        $arrErrores = [];
        if (!$request->has('documentos')) {
            return response()->json([
                'message' => 'Errores al Enviar los Documentos',
                'errors' => ['No se ha especificado ningún documento a enviar.']
            ], 400);
        } else {
            $documentos = EtlCabeceraDocumentoDaop::select($this->columns)
                ->whereIn('cdo_id', explode(',', $request->documentos))
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                    'getDocumentoRechazado',
                    'getUblFallido'
                ])
                ->get();

            if (!$documentos->isEmpty()) {
                $__documentos = [];
                foreach ($documentos as $doc) {
                    //Varibale que indica que cumplio las condiciones
                    $cumpleCondiciones = true;
                    if ($doc->estado != 'ACTIVO') {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' se encuentra en estado INACTIVO';
                        $cumpleCondiciones = false;
                    }

                    if ($doc->cdo_procesar_documento != 'SI' || (empty($doc->getDocumentoRechazado) && empty($doc->getUblFallido))) {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no puede ser modificado, el documento no ha sido rechazado por la DIAN o no tiene estado UBL fallido';
                        $cumpleCondiciones = false;
                    }

                    if ($doc->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion != self::NIT_DHLEXPRESS) {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no puede ser modificado, opción válida unicamente para documentos de DHL Express con RG 9';
                        $cumpleCondiciones = false;
                    }

                    if ($doc->cdo_representacion_grafica_documento != '9') {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no puede ser modificado, solamente aplica para RG 9 de DHL Express';
                        $cumpleCondiciones = false;
                    }

                    if ($cumpleCondiciones == true) {
                        $doc->update([
                            'cdo_cufe'                     => null,
                            'cdo_qr'                       => null,
                            'cdo_signaturevalue'           => null,
                            'cdo_procesar_documento'       => null,
                            'cdo_fecha_procesar_documento' => null,
                            'cdo_fecha_validacion_dian'    => null
                        ]);
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Errores al procesar los Documentos',
                    'errors' => ['Los documentos seleccionados no existen o no se encontraron en la data operativa.']
                ], 400);
            }
        }

        if (empty($arrErrores)) {
            return response()->json([
                'message' => 'Documentos modificados'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Errores al procesar los Documentos',
                'errors' => $arrErrores
            ], 422);
        }
    }

    /**
     * Modifica documentos para que puedan ser editados.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function modificarDocumentos(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        $objUser = auth()->user();
        $arrErrores = [];
        if (!$request->has('documentos')) {
            return response()->json([
                'message' => 'Errores al Enviar los Documentos',
                'errors' => ['No se ha especificado ningún documento a enviar.']
            ], 400);
        } else {
            $documentos = EtlCabeceraDocumentoDaop::select($this->columns)
                ->whereIn('cdo_id', explode(',', $request->documentos))
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                    'getDocumentoRechazado',
                    'getUblFallido'
                ])
                ->get();

            if (!$documentos->isEmpty()) {
                $__documentos = [];
                foreach ($documentos as $doc) {
                    //Varibale que indica que cumplio las condiciones
                    $cumpleCondiciones = true;
                    if ($doc->estado != 'ACTIVO') {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' se encuentra en estado INACTIVO';
                        $cumpleCondiciones = false;
                    }

                    if ($doc->cdo_procesar_documento != 'SI' || (empty($doc->getDocumentoRechazado) && empty($doc->getUblFallido))) {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no puede ser modificado, el documento no ha sido rechazado por la DIAN o no tiene estado UBL fallido';
                        $cumpleCondiciones = false;
                    }

                    if ($doc->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == self::NIT_DHLEXPRESS) {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no puede ser modificado, la edición de la RG 9 de DHL Express debe realizarla por la opción Modificar Documento Pickup Cash';
                        $cumpleCondiciones = false;
                    }

                    if ($doc->cdo_representacion_grafica_documento == '9') {
                        $arrErrores[] = $doc->cdo_clasificacion . ' ' . $doc->rfa_prefijo . $doc->cdo_consecutivo . ' no puede ser modificado, la edición de la RG 9 de DHL Express debe realizarla por la opción Modificar Documento Pickup Cash';
                        $cumpleCondiciones = false;
                    }

                    if ($cumpleCondiciones == true) {
                        $doc->update([
                            'cdo_cufe'                     => null,
                            'cdo_qr'                       => null,
                            'cdo_signaturevalue'           => null,
                            'cdo_procesar_documento'       => null,
                            'cdo_fecha_procesar_documento' => null,
                            'cdo_fecha_validacion_dian'    => null
                        ]);
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Errores al procesar los Documentos',
                    'errors' => ['Los documentos seleccionados no existen o no se encontraron en la data operativa.']
                ], 400);
            }
        }

        if (empty($arrErrores)) {
            return response()->json([
                'message' => 'Documentos modificados'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Errores al procesar los Documentos',
                'errors' => $arrErrores
            ], 422);
        }
    }

    /**
     * Toma los errores generados y los mezcla en un sólo arreglo para dar respuesta al usuario
     *
     * @param array $arrErrores
     * @param object $objValidator
     * @return void
     */
    public function adicionarErrorLocal($arrErrores, $objValidator) {
        foreach ($objValidator as $error) {
            array_push($arrErrores, $error);
        }

        return $arrErrores;
    }

    /**
     * Retorna una interfaz personalizada para aquellos ofes que tiene una interfaz custom de excel.
     * 
     * @param $nombreArchivo
     * @param ConfiguracionObligadoFacturarElectronicamente $oferente
     * @param string $clasificacion
     * @param string $interface
     * @return ExcelExportPersonalizado
     */
    private function generateCustomInterface($nombreArchivo, ConfiguracionObligadoFacturarElectronicamente $oferente, string $clasificacion, string $interface) {
        $todas       = $oferente->ofe_columnas_personalizadas[$clasificacion]['columnas'];
        $inicioItems = $oferente->ofe_columnas_personalizadas[$clasificacion]['inicio_items'];

        usort($todas, function ($a, $b){
            return $a['orden'] > $b['orden'];
        });

        $cabecera = [];
        $items = [];
        foreach ($todas as $pvt) {
            if ($pvt['orden'] < $inicioItems)
                $cabecera[] = $pvt;
            else
                $items[] = $pvt;
        }
        return new ExcelExportPersonalizado($nombreArchivo, $cabecera, $items, $interface);
    }

    /**
     * Verifica si el ofe aplica para Sector Salud, en vuyo caso agrega columnas especiales de dicho sector a los arrays de columnas de cabecera y detalle de la interface de FC, ND y NC
     * 
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Obligado a facturar electrónicamente
     * @param array $columnasCabecera Array de las columnas de cabecera
     * @param array $columnasDetalle Array de las columnas de detalle
     * @return void
     */
    private function agregaColumnasPrincipalesSectorSalud(ConfiguracionObligadoFacturarElectronicamente $ofe, &$columnasCabecera, &$columnasDetalle) {
        if(isset($ofe->ofe_campos_personalizados_factura_generica) && array_key_exists('aplica_sector_salud', $ofe->ofe_campos_personalizados_factura_generica) && $ofe->ofe_campos_personalizados_factura_generica['aplica_sector_salud'] == 'SI') {
            $columnasCabecera = array_merge($columnasCabecera, ['FUTURA OPERACION ACREDITACION', 'MODALIDADES CONTRATACION/PAGO']);
            $columnasDetalle  = array_merge($columnasDetalle, ['ID AUTORIZACION ERP/EPS']);
        }
    }

    /**
     * Genera una Interfaz de Facturas para guardar en Excel.
     *
     * @param $ofe_id
     * @return ExcelExport|ExcelExportPersonalizado
     */
    public function generarInterfaceFacturas($ofe_id) {
        $objUser = auth()->user();
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($ofe_id);

        header('Access-Control-Expose-Headers: Content-Disposition');
        date_default_timezone_set('America/Bogota');
        $nombreArchivo = 'FC_' . date('YmdHis');
        if (isset($ofe->ofe_excel_personalizado) && $ofe->ofe_excel_personalizado === 'SI') {
            return $this->generateCustomInterface($nombreArchivo, $ofe, 'FC', 'Fc');
        }

        $columnasCabecera = [];
        $columnasDetalle  = [];

        $this->agregaColumnasPrincipalesSectorSalud($ofe, $columnasCabecera, $columnasDetalle);

        // Valida si el campo ofe_columnas_personalizadas tiene información
        if (isset($ofe->ofe_columnas_personalizadas) && is_array($ofe->ofe_columnas_personalizadas) && !empty($ofe->ofe_columnas_personalizadas)) {
            if (array_key_exists('FC', $ofe->ofe_columnas_personalizadas) && ( array_key_exists('detalle', $ofe->ofe_columnas_personalizadas['NC-ND']) || array_key_exists('cabecera', $ofe->ofe_columnas_personalizadas['NC-ND']) )){
                $columnasCabecera = array_merge($columnasCabecera, $ofe->ofe_columnas_personalizadas['FC']['cabecera']);
                $columnasDetalle  = array_merge($columnasDetalle, $ofe->ofe_columnas_personalizadas['FC']['detalle']);
            }
        }

        $columnasDefault = MainTrait::$columnasDefault;

        $nIndiceAdqPersonalizado = 0;
        // Valida si el campo ofe_identificador_unico_adquirente contiene el valor de adq_id_personalizado
        if (isset($ofe->ofe_identificador_unico_adquirente) && is_array($ofe->ofe_identificador_unico_adquirente) && !empty($ofe->ofe_identificador_unico_adquirente)) {
            if(in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)){
                array_splice($columnasDefault, 4, 0, 'ID PERSONALIZADO');
                $nIndiceAdqPersonalizado = 1;
            }
        }

        if (!empty($columnasCabecera)) {
            $columnasDefault = array_merge($columnasDefault, $columnasCabecera);
        }

        $columnasDefault = array_merge($columnasDefault, MainTrait::$columnasItemDefault);
        if (!empty($columnasDetalle)) {
            $columnasDefault = array_merge($columnasDefault, $columnasDetalle);
        }

        $sectorSalud = false;
        if(isset($ofe->ofe_campos_personalizados_factura_generica) && array_key_exists('aplica_sector_salud', $ofe->ofe_campos_personalizados_factura_generica) && $ofe->ofe_campos_personalizados_factura_generica['aplica_sector_salud'] == 'SI')
            $sectorSalud = true;

        // Última columna por defecto en encabezado antes de columnas adicionales
        $letraColumna      = ($nIndiceAdqPersonalizado == 0) ? 'W' : 'X';
        $letraColAdicional = ($nIndiceAdqPersonalizado == 0) ? 'X' : 'Y';
        return new ExcelExport($nombreArchivo, $columnasDefault, [], 'Fc', $columnasCabecera, $columnasDetalle, $letraColumna, $letraColAdicional, $sectorSalud);
    }
    

    /**
     * Genera una Interfaz de Notas de Débitos o Créditos para guardar en Excel.
     *
     * @param $ofe_id
     * @return ExcelExport|ExcelExportPersonalizado
     */
    public function generarInterfaceNotasCreditoDebito($ofe_id) {
        $objUser = auth()->user();
        $baseDatos = $objUser->getBaseDatos->bdd_nombre;

        // Obtiene el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($ofe_id);

        header('Access-Control-Expose-Headers: Content-Disposition');
        date_default_timezone_set('America/Bogota');
        $nombreArchivo = 'NC_ND_' . date('YmdHis');
        if (isset($ofe->ofe_excel_personalizado) && $ofe->ofe_excel_personalizado === 'SI') {
            return $this->generateCustomInterface($nombreArchivo, $ofe, 'NC-ND', 'NC_ND_');
        }
        
        $columnasCabecera = [];
        $columnasDetalle = [];

        $this->agregaColumnasPrincipalesSectorSalud($ofe, $columnasCabecera, $columnasDetalle);

        // Valida si el campo ofe_columnas_personalizadas tiene información
        if (isset($ofe->ofe_columnas_personalizadas) && is_array($ofe->ofe_columnas_personalizadas) && !empty($ofe->ofe_columnas_personalizadas)) {
            if (array_key_exists('NC-ND', $ofe->ofe_columnas_personalizadas) && ( array_key_exists('detalle', $ofe->ofe_columnas_personalizadas['NC-ND']) || array_key_exists('cabecera', $ofe->ofe_columnas_personalizadas['NC-ND']) ) ){
                $columnasCabecera = array_merge($columnasCabecera, $ofe->ofe_columnas_personalizadas['NC-ND']['cabecera']);
                $columnasDetalle = array_merge($columnasDetalle, $ofe->ofe_columnas_personalizadas['NC-ND']['detalle']);
            }
        }

        $columnasDefault = MainTrait::$columnasDefault;
        unset($columnasDefault[5]); // Se elimina el índice de 'RESOLUCION FACTURACION'
        
        $nIndiceAdqPersonalizado = 0;
        // Valida si el campo ofe_identificador_unico_adquirente contiene el valor de adq_id_personalizado
        if (isset($ofe->ofe_identificador_unico_adquirente) && is_array($ofe->ofe_identificador_unico_adquirente) && !empty($ofe->ofe_identificador_unico_adquirente)) {
            if(in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)){
                array_splice($columnasDefault, 4, 0, 'ID PERSONALIZADO');
                $nIndiceAdqPersonalizado = 1;
            }
        }

        // Para las notas se agregan las columnas del documento referencia
        array_splice($columnasDefault, (10 + $nIndiceAdqPersonalizado), 0, [
            'PREFIJO FACTURA',
            'CONSECUTIVO FACTURA',
            'CUFE FACTURA',
            'FECHA FACTURA',
            'COD CONCEPTO CORRECCION',
            'OBSERVACION CORRECCION'
        ]);

        $indice = ($nIndiceAdqPersonalizado == 0) ? 29 : 30;
        $letraColumna = ($nIndiceAdqPersonalizado == 0) ? 'AB' : 'AC';
        $letraColAdicional = ($nIndiceAdqPersonalizado == 0) ? 'AC' : 'AD';

        if (count($columnasCabecera) > 0) {
            array_splice($columnasDefault, $indice, 0, $columnasCabecera);
        }
        $columnasDefault = array_merge($columnasDefault, MainTrait::$columnasItemDefault);

        if (count($columnasDetalle) > 0) {
            array_splice($columnasDefault, count($columnasDefault), 0, $columnasDetalle);
        }

        $sectorSalud = false;
        if(isset($ofe->ofe_campos_personalizados_factura_generica) && array_key_exists('aplica_sector_salud', $ofe->ofe_campos_personalizados_factura_generica) && $ofe->ofe_campos_personalizados_factura_generica['aplica_sector_salud'] == 'SI')
            $sectorSalud = true;

        return new ExcelExport($nombreArchivo, $columnasDefault, [], 'NcNd', $columnasCabecera, $columnasDetalle, $letraColumna, $letraColAdicional, $sectorSalud);

    }

    /**
     * Obtiene un documento y lo almacena en una lista para su posterior reutilizacion
     *
     * @param $cdo_id
     * @param $documentos
     * @return \Illuminate\Database\Eloquent\Collection|null
     */
    private function obtenerDocumento($cdo_id, &$documentos) {
        $documento = null;
        if (array_key_exists($cdo_id, $documentos))
            $documento = $documentos[$cdo_id];
        else {
            $documento = $this->getDocumento($cdo_id);
            $documentos[$cdo_id] = $documento;
        }
        return $documento;
    }

    /**
     * Retorna un array con los diferentes registros de estados solicitados.
     *
     * @param array $ids
     * @param string $estado
     * @param array $columnas
     * @return mixed
     */
    private function getEstadosDocumentos(array $ids, string $estado, array $columnas) {
        $documentos = [];
        EtlEstadosDocumentoDaop::select($columnas)
            ->where('est_estado', $estado)
            ->whereIn('cdo_id', $ids)
            ->get()
            ->map(function ($item) use (&$documentos) {
                if (!is_null($item) && isset($item->cdo_id))
                    $documentos[$item->cdo_id] = $item->toArray();
            });
        return $documentos;
    }

    /**
     * Retorna el modo en que se esta efectuando la descarga de documentos. Sin Envio y enviados
     *
     * @return string
     */
    private function getModo() {
        if (strpos($_SERVER['REQUEST_URI'], 'lista-documentos-enviados') !== false)
            return 'documento-enviado';
        return 'documento-sin-envio';
    }

    /**
     * Construye el nombre para un documento electronico en funcion de un registro de cabecera y un tipo de archivo
     *
     * @param $documento
     * @param $tipo
     * @param $sufijo
     * @return string
     */
    private function getNombreArchivo($documento, $tipo, $sufijo = '') {
        $tipoDoc = $this->tipoDocumento($documento->cdo_clasificacion, trim($documento->rfa_prefijo));
        if ($sufijo === '')
            return $documento->cdo_clasificacion . $tipoDoc['prefijo'] . $documento->cdo_consecutivo . ".{$tipo}";
        return $documento->cdo_clasificacion . $tipoDoc['prefijo'] . $documento->cdo_consecutivo . "-{$sufijo}.{$tipo}";
    }

    /**
     * Almacena un archivo XML especificado en el storage de descargas para poder servirlo al cliente.
     *
     * @param $documento
     * @param $estado
     * @param $nombreXml
     * @param string $temp_dir
     */
    private function prepareXML($documento, $estado, $nombreXml, $temp_dir = '') {
        MainTrait::setFilesystemsInfo();
        if(strtolower(substr(bin2hex(base64_decode($estado['est_xml'])), 0, 6)) === 'efbbbf')
            Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombreXml, substr(base64_decode($estado['est_xml']), 3));
        else
            Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir .$nombreXml, base64_decode($estado['est_xml']));
    }

    /**
     * Almacena un archivo PDF especificado en el storage de descargas para poder servirlo al cliente
     *
     * @param $documento
     * @param $estado
     * @param $nombrePdf
     * @param string $base64
     * @param string $temp_dir
     */
    private function preparePDF($documento, $estado, $nombrePdf, $base64 = '', $temp_dir = '') {
        MainTrait::setFilesystemsInfo();
        if ($base64 === '') {
            if (strtolower(substr(bin2hex(base64_decode($estado['est_archivo'])), 0, 6)) === 'efbbbf')
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombrePdf, substr(base64_decode($estado['est_archivo']), 3));
            else
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombrePdf, base64_decode($estado['est_archivo']));
        } else {
            if (strtolower(substr(bin2hex(base64_decode($base64)), 0, 6)) === 'efbbbf')
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombrePdf, substr(base64_decode($base64), 3));
            else
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombrePdf, base64_decode($base64));
        }
    }

    /**
     * Almacena un archivo JSON especificado en el storage de descargas para poder servirlo al cliente.
     *
     * @param $documento
     * @param $estado
     * @param $nombreJson
     * @param string $temp_dir
     */
    private function prepareJson($documento, $estado, $nombreJson, $temp_dir = '') {
        MainTrait::setFilesystemsInfo();
        if(strtolower(substr(bin2hex(base64_decode($estado['est_json'])), 0, 6)) === 'efbbbf')
            Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir . $nombreJson, substr(($estado['est_json']), 3));
        else
            Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($temp_dir .$nombreJson, $estado['est_json']);
    }

    /**
     * Verifica si un nombre de archivo existe en un array de nombres de archivos.
     * 
     * Este método se llama desde la descarga de archivos y permite renombrar con concecutivos los nombres de archivo repetidos
     * 
     * @param string $nombreArchivo Nombre del archivo
     * @param array Array de nombres de archivo
     * @return string $nombreArchivo Nombre del archivo que se puede utilizar
     */
    public function nombresArchivosDuplicados($nombreArchivo, &$arrNombres) {
        // Verifica si existe un archivo con el mismo nombre dentro del proceso, de ser así se renombra con un consecutivo al final
        $consecutivo = 1;
        $existe      = true;
        $nombre      = explode('.', $nombreArchivo);
        do {
            $nuevoNombre = $nombreArchivo;
            if(in_array($nuevoNombre, $arrNombres)) {
                $nombreArchivo = $nombre[0] . '-' . $consecutivo . '.' . $nombre[1];
                $consecutivo++;
            } else {
                $existe = false;
            }
        } while ($existe);

        $arrNombres[] = $nombreArchivo;

        return $nombreArchivo;
    }

    /**
     * Efectual el proceso de descarga
     *
     * @param string $nombreArchivo Nombre del archivo a descargar
     * @param boolean $portales Indica si la petición proviene desde portales
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function download($nombreArchivo, $portales = false) {
        if(!$portales) {
            $headers = [
                header('Access-Control-Expose-Headers: Content-Disposition')
            ];

            // Retorna el documento y lo elimina tan pronto es enviado en la respuesta
            return response()
                ->download(storage_path('etl/descargas/' . $nombreArchivo), $nombreArchivo, $headers)
                ->deleteFileAfterSend(true);
        } else {
            $base64 = base64_encode(File::get(storage_path('etl/descargas/' . $nombreArchivo)));
            File::delete(storage_path('etl/descargas/' . $nombreArchivo));
            
            return response()->json([
                'data' => [
                    'archivo'       => $base64,
                    'nombreArchivo' => $nombreArchivo
                ]
            ], 200);
        }
    }

    /**
     * Construye un mensaje de error en caso de que no se pueda descargar el archivo
     *
     * @param $messsage
     * @param array $errores
     * @return \Illuminate\Http\JsonResponse
     */
    private function generateErrorResponse($messsage, $errores = []) {
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
     * Permite descargar el PDF como archivo o en base64, del archivo guardado en disco o generado
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function descargarPdf(Request $request) {

        if(!isset($request->ofe_identificacion) || !isset($request->consecutivo) || !isset($request->resultado)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["Parametros no validos"]
            ], 404);
        }

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion','bdd_id_rg'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (is_null($ofe)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No existe el OFE {$request->ofe_identificacion}"]
            ], 404);
        }

        if ($request->prefijo === 'null' || $request->prefijo === null){
            $request->prefijo = '';
        }

        if (!isset($request->tipo_documento) || $request->tipo_documento != "") {
            $documento = EtlCabeceraDocumentoDaop::select(['cdo_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('cdo_consecutivo', $request->consecutivo)
                ->where('rfa_prefijo', trim($request->prefijo))
                ->first();
        } else {
            $documento = EtlCabeceraDocumentoDaop::select(['cdo_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('cdo_clasificacion', $request->tipo_documento)
                ->where('cdo_consecutivo', $request->consecutivo)
                ->where('rfa_prefijo', trim($request->prefijo))
                ->first();
        }

        if (is_null($documento)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["El Documento " . $request->tipo_documento . " ".(($request->prefijo === null) ? '' : $request->prefijo)."{$request->consecutivo} para el OFE {$request->ofe_identificacion} no existe"]
            ], 404);
        }

        $documento = $this->getDocumento($documento->cdo_id);
        $nombrePdf = $this->getNombreArchivo($documento, self::TIPO_PDF);
        $getPdf = $this->getPdfRepresentacionGraficaDocumento($documento->cdo_id);
        if (!$getPdf['error']) {
            $this->preparePDF($documento, null, $nombrePdf, $getPdf['pdf']);
            if ($request->resultado == "base64") {
                return response()->json([
                    'data' => [
                        'pdf' => base64_encode(file_get_contents(storage_path('etl/descargas/' . $nombrePdf)))
                    ]
                ]);
            } else {
                return $this->download($nombrePdf);
            }
        }
        return response()->json([
            'message' => 'Error en documento',
            'errors' => ["No fue posible obtener el PDF asociado al documento"]
        ], 404); 
    }

    /**
     * Permite obtener el PDF enviado a la DIAN en base64
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerPdfNotificacion(Request $request) {
        if(!isset($request->ofe_identificacion) || !isset($request->tipo_documento) || !isset($request->prefijo) || !isset($request->consecutivo)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["Parametros incompletos, verifique que envie ofe_identificacion, tipo_documento, prefijo y consecutivo"]
            ], 409);
        }

        // Verifica que exista el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion','bdd_id_rg'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (is_null($ofe)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["No existe el OFE {$request->ofe_identificacion}"]
            ], 404);
        }

        if ($request->prefijo === 'null' || $request->prefijo === null){
            $request->prefijo = '';
        }

        // Verifica que exista el documento
        $documento = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_nombre_archivos', 'fecha_creacion'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('cdo_clasificacion', $request->tipo_documento)
            ->where('rfa_prefijo', trim($request->prefijo))
            ->where('cdo_consecutivo', $request->consecutivo)
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion'
            ])
            ->first();

        if (is_null($documento)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["El Documento " . $request->tipo_documento . " ".(($request->prefijo === null) ? '' : $request->prefijo)."{$request->consecutivo} para el OFE {$request->ofe_identificacion} no existe"]
            ], 404);
        }

        // Obtiene el estado NOTIFICACION Exitoso
        $notificacion = EtlEstadosDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
            ->where('cdo_id', $documento->cdo_id)
            ->where('est_estado', self::ESTADO_NOTIFICACION)
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->orderBy('est_id', 'desc')
            ->first();

        if(!$notificacion) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["El Documento ".(($request->prefijo === null) ? '' : $request->prefijo)."{$request->consecutivo} no cuenta con PDF"]
            ], 404);
        }

        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->pdf) && $nombreArchivos->pdf != '') {
            $nombrePdf = $nombreArchivos->pdf;
        } else {
            $nombrePdf = $this->getNombreArchivo($documento, self::TIPO_PDF);
        }

        $informacionAdicional = !empty($notificacion->est_informacion_adicional) ? json_decode($notificacion->est_informacion_adicional, true) : [];
        $pdfNotificacion      = MainTrait::obtenerArchivoDeDisco(
            self::PROCESO_EMISION,
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento,
            array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
        );

        if (isset($request->resultado) && $request->resultado == "archivo") {
            $this->preparePDF($documento, null, $nombrePdf, base64_encode($pdfNotificacion));
            return $this->download($nombrePdf);
        } else {
            return response()->json([
                'data' => [
                    'pdf_notificacion' => base64_encode($pdfNotificacion)
                ]
            ], 200);
        }
    }

    /**
     * Permite obtener el XML-UBL en base64.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerXmlUbl(Request $request) {
        if(!isset($request->ofe_identificacion) || !isset($request->tipo_documento) || !isset($request->prefijo) || !isset($request->consecutivo)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["Parametros incompletos, verifique que envie ofe_identificacion, tipo_documento, prefijo y consecutivo"]
            ], 409);
        }

        // Verifica que exista el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion','bdd_id_rg'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (is_null($ofe)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["No existe el OFE {$request->ofe_identificacion}"]
            ], 404);
        }

        if ($request->prefijo === 'null' || $request->prefijo === null){
            $request->prefijo = '';
        }

        // Verifica que exista el documento
        $documento = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'fecha_creacion'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('cdo_clasificacion', $request->tipo_documento)
            ->where('rfa_prefijo', trim($request->prefijo))
            ->where('cdo_consecutivo', $request->consecutivo)
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion'
            ])
            ->first();

        if (is_null($documento)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["El Documento " . $request->tipo_documento . " ".(($request->prefijo === null) ? '' : $request->prefijo)."{$request->consecutivo} para el OFE {$request->ofe_identificacion} no existe"]
            ], 404);
        }

        // Obtiene el estado UBL Exitoso
        $ubl = EtlEstadosDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
            ->where('cdo_id', $documento->cdo_id)
            ->where('est_estado', self::ESTADO_UBL)
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->orderBy('est_id', 'desc')
            ->first();

        if(!$ubl) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["El Documento ".(($request->prefijo === null) ? '' : $request->prefijo)."{$request->consecutivo} no cuenta con XML-UBL"]
            ], 404);
        }

        $informacionAdicional = !empty($ubl->est_informacion_adicional) ? json_decode($ubl->est_informacion_adicional, true) : [];
        $xmlUbl               = MainTrait::obtenerArchivoDeDisco(
            self::PROCESO_EMISION,
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento,
            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
        );

        if(strtolower(substr(bin2hex($xmlUbl), 0, 6)) === 'efbbbf') {
            $xmlUbl = substr($xmlUbl, 3);
        }

        return response()->json([
            'data' => [
                'xml_ubl' => base64_encode($xmlUbl)
            ]
        ], 200);
    }

    /**
     * Permite obtener el AttachedDocument en base64.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerAttachedDocument(Request $request) {
        if(!isset($request->ofe_identificacion) || !isset($request->tipo_documento) || !isset($request->prefijo) || !isset($request->consecutivo)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["Parametros incompletos, verifique que envie ofe_identificacion, tipo_documento, prefijo y consecutivo"]
            ], 409);
        }

        // Verifica que exista el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion','bdd_id_rg'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (is_null($ofe)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["No existe el OFE {$request->ofe_identificacion}"]
            ], 404);
        }

        if ($request->prefijo === 'null' || $request->prefijo === null){
            $request->prefijo = '';
        }

        // Verifica que exista el documento
        $documento = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'fecha_creacion'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('cdo_clasificacion', $request->tipo_documento)
            ->where('rfa_prefijo', trim($request->prefijo))
            ->where('cdo_consecutivo', $request->consecutivo)
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion'
            ])
            ->first();

        if (is_null($documento)) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["El Documento " . $request->tipo_documento . " ".(($request->prefijo === null) ? '' : $request->prefijo)."{$request->consecutivo} para el OFE {$request->ofe_identificacion} no existe"]
            ], 404);
        }

        // Obtiene el estado NOTIFICACION Exitoso
        $estAttachedDocument = EtlEstadosDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
            ->where('cdo_id', $documento->cdo_id)
            ->where('est_estado', self::ESTADO_ATTACHEDDOCUMENT)
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->orderBy('est_id', 'desc')
            ->first();

        if(!$estAttachedDocument) {
            return response()->json([
                'message' => 'Error en petición',
                'errors' => ["El Documento ".(($request->prefijo === null) ? '' : $request->prefijo)."{$request->consecutivo} no ha sido notificado"]
            ], 404);
        }

        $informacionAdicional = !empty($estAttachedDocument->est_informacion_adicional) ? json_decode($estAttachedDocument->est_informacion_adicional, true) : [];
        $attachedDocument     = MainTrait::obtenerArchivoDeDisco(
            self::PROCESO_EMISION,
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento,
            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
        );

        if(strtolower(substr(bin2hex($attachedDocument), 0, 6)) === 'efbbbf') {
            $attachedDocument = substr($attachedDocument, 3);
        }

        return response()->json([
            'data' => [
                'attached_document' => base64_encode($attachedDocument)
            ]
        ], 200);
    }

    /**
     * Permite descargar los archivos relacionados con el procesamiento de uno o varios documentos electrónicos.
     *
     * @param Request $request
     * @return JsonResponse|Response|BinaryFileResponse
     * @throws \Exception
     */
    public function descargarDocumentos(Request $request) {
        // Array de tipos de documentos a descargar
        $arrTiposDocumentos = explode(',', $request->tipos_documentos);

        // Array de ids de documentos a descargar
        $arrCdoIds = explode(',', $request->cdo_ids);

        // Si solamente se recibe un tipo de documento y un solo cdo_id
        // Se genera la descarga solamente de ese archivo
        if (count($arrTiposDocumentos) == 1 && count($arrCdoIds) == 1) {
            $documento = $this->getDocumento($arrCdoIds[0]);
            $cdoId = $arrCdoIds[0];
            if ($documento) {
                // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo
                $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));

                if (strtolower($arrTiposDocumentos[0]) == 'xml-ubl') {
                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_ubl) && $nombreArchivos->xml_ubl != '') {
                        $nombreXml = $nombreArchivos->xml_ubl;
                    } else {
                        $nombreXml = $this->getNombreArchivo($documento, self::TIPO_XML);
                    }

                    // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                    $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdoId, self::ESTADO_UBL);
                    if (!empty($estado)) {
                        $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                        $xmlUbl                = MainTrait::obtenerArchivoDeDisco(
                            self::PROCESO_EMISION,
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );

                        if(!empty($xmlUbl)) {
                            $this->prepareXML($documento, ['est_xml' => base64_encode($xmlUbl)], $nombreXml);
                            return $this->download($nombreXml, ($request->has('portal_clientes') && $request->portal_clientes ? true : false));
                        }
                    }

                    return $this->generateErrorResponse('El XML-UBL no ha sido generado');
                } elseif (strtolower($arrTiposDocumentos[0]) == 'attacheddocument') {
                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->attached) && $nombreArchivos->attached != '') {
                        $nombreXml = $nombreArchivos->attached;
                    } else {
                        $nombreXml = $this->getNombreArchivo($documento, self::TIPO_XML, 'AttachedDocument');
                    }

                    // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                    $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdoId, self::ESTADO_ATTACHEDDOCUMENT);
                    $estadoEncontrado = true;
                    if (empty($estado)) {
                        $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdoId, self::ESTADO_NOTIFICACION);
                    }
                    if (empty($estado)) {
                        $estadoEncontrado = false;
                    }

                    if ($estadoEncontrado) {
                        $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                        $attachedDocument     = MainTrait::obtenerArchivoDeDisco(
                            self::PROCESO_EMISION,
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );

                        if(!empty($attachedDocument)) {
                            $this->prepareXML($documento, ['est_xml' => base64_encode($attachedDocument)], $nombreXml);
                            return $this->download($nombreXml, ($request->has('portal_clientes') && $request->portal_clientes ? true : false));
                        }
                    }

                    return $this->generateErrorResponse('El AttachedDocument no ha sido generado');
                } elseif ($arrTiposDocumentos[0] == 'pdf') {
                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->pdf) && $nombreArchivos->pdf != '') {
                        $nombrePdf = $nombreArchivos->pdf;
                    } else {
                        $nombrePdf = $this->getNombreArchivo($documento, self::TIPO_PDF);
                    }
                    $getPdf = null;
                    if ($request->has('documento_enviado') && $request->documento_enviado == 'SI') {
                        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                        $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdoId, self::ESTADO_NOTIFICACION);
                        if (!empty($estado)) {
                            $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                            $pdfRG                = MainTrait::obtenerArchivoDeDisco(
                                self::PROCESO_EMISION,
                                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                $documento,
                                array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                            );
                        } else {
                            return $this->generateErrorResponse('No fue posible obtener el PDF porque el documento no ha sido notificado');
                        }

                        if(isset($pdfRG) && !empty($pdfRG)) {
                            $this->preparePDF($documento, ['est_archivo' => base64_encode($pdfRG)], $nombrePdf);
                            return $this->download($nombrePdf);
                        }
                    } else { // documento sin envio
                        $getPdf = $this->getPdfRepresentacionGraficaDocumento($documento->cdo_id);
                        if (!$getPdf['error']) {
                            $this->preparePDF($documento, null, $nombrePdf, $getPdf['pdf']);
                            return $this->download($nombrePdf);
                        }
                        return $this->generateErrorResponse($getPdf['message'], [$getPdf['pdf']]);
                    }
                } elseif ($arrTiposDocumentos[0] == 'pdf-generar') {
                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->pdf) && $nombreArchivos->pdf != '') {
                        $nombrePdf = $nombreArchivos->pdf;
                    } else {
                        $nombrePdf = $this->getNombreArchivo($documento, self::TIPO_PDF);
                    }
                    $getPdf = $this->getPdfRepresentacionGraficaDocumento($documento->cdo_id);
                    if (!$getPdf['error']) {
                        $this->preparePDF($documento, null, $nombrePdf, $getPdf['pdf']);
                        return $this->download($nombrePdf);
                    }
                    return $this->generateErrorResponse($getPdf['message'], [$getPdf['pdf']]);
                } elseif (strtolower($arrTiposDocumentos[0]) == 'certificado') {
                    if(isset($documento->getDadDocumentosDaop[0]->cdo_informacion_adicional) && array_key_exists('certificado_mandato', $documento->getDadDocumentosDaop[0]->cdo_informacion_adicional) && $documento->getDadDocumentosDaop[0]->cdo_informacion_adicional['certificado_mandato'] != '') {
                        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                        $estado               = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdoId, self::ESTADO_EDI);
                        $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                        $certificadoMandato   = MainTrait::obtenerArchivoDeDisco(
                            self::PROCESO_EMISION,
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('certificado', $informacionAdicional) ? $informacionAdicional['certificado'] : null
                        );

                        if(!empty($certificadoMandato)) {
                            $nombrePdf = $informacionAdicional['certificado'];
                            $this->preparePDF($documento, null, $nombrePdf, base64_encode($certificadoMandato));
                            return $this->download($nombrePdf, false);
                        }
                    }
                    return $this->generateErrorResponse('El documento no tiene un certificado adjunto');
                } elseif (strtolower($arrTiposDocumentos[0]) == 'json') {
                    // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                    $estado               = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdoId, self::ESTADO_EDI);
                    $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                    $archivoJson   = MainTrait::obtenerArchivoDeDisco(
                        self::PROCESO_EMISION,
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                        $documento,
                        array_key_exists('est_json', $informacionAdicional) ? $informacionAdicional['est_json'] : null
                    );

                    if(!empty($archivoJson)) {
                        $nombreJson = $informacionAdicional['est_json'];
                        $this->prepareJson($documento, ['est_json' => $archivoJson], $nombreJson);
                        return $this->download($nombreJson, ($request->has('portal_clientes') && $request->portal_clientes ? true : false));
                    }
                    
                    return $this->generateErrorResponse('No fue posible obtener el Json.');
                } elseif (strtolower($arrTiposDocumentos[0]) == 'ar-dian') {
                    // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                    $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdoId, self::ESTADO_DO, ['EXITOSO', 'FALLIDO']);
                    if (!empty($estado)) {
                        $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                        $arDian = MainTrait::obtenerArchivoDeDisco(
                            self::PROCESO_EMISION,
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );

                        if(!empty($arDian)) {
                            $nombreArDian = $informacionAdicional['est_xml'];
                            $this->prepareXML($documento, ['est_xml' => base64_encode($arDian)], $nombreArDian);
                            return $this->download($nombreArDian, ($request->has('portal_clientes') && $request->portal_clientes ? true : false));
                        }
                    }

                    return $this->generateErrorResponse('El Application Response DIAN no ha sido generado');
                }
            }
            return $this->generateErrorResponse('El documento electr&oacute;nico seleccionado no existe o no ha sido firmado electr&oacute;nicamente');
        } elseif (count($arrTiposDocumentos) >= 1 || count($arrCdoIds) >= 1) {
            // Sin son varios tipos de documentos y/o varios cdo_id
            // Se genera un archivo comprimido con los documentos solicitados

            // Universal Unique ID utilizado para nombrar folder y zip
            $uuid = Uuid::uuid4();

            $losDocumentos   = [];
            $nombresArchivos = [];

            // Por cada tipo de documento se debe crear el archivo para cada documento electrónico
            if (in_array('xml-ubl', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos);
                    if ($documento) {
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->xml_ubl) && $nombreArchivos->xml_ubl != '') {
                            $nombreXml = $nombreArchivos->xml_ubl;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, self::TIPO_XML);
                        }

                        $nombreXml = $this->nombresArchivosDuplicados($nombreXml, $nombresArchivos);

                        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                        $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdo_id, self::ESTADO_UBL);
                        if (!empty($estado)) {
                            $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                            $xmlUbl                = MainTrait::obtenerArchivoDeDisco(
                                self::PROCESO_EMISION,
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

            // Por cada tipo de documento se debe crear el archivo para cada documento electrónico
            if (in_array('attacheddocument', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos);
                    if ($documento) {
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->attached) && $nombreArchivos->attached != '') {
                            $nombreXml = $nombreArchivos->attached;
                        } else {
                            $nombreXml = $this->getNombreArchivo($documento, self::TIPO_XML, 'Attacheddocument');
                        }

                        $nombreXml = $this->nombresArchivosDuplicados($nombreXml, $nombresArchivos);

                        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                        $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdo_id, self::ESTADO_ATTACHEDDOCUMENT);
                        $estadoEncontrado = true;
                        if (empty($estado)) {
                            $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdo_id, self::ESTADO_NOTIFICACION);
                        }
                        if (empty($estado)) {
                            $estadoEncontrado = false;
                        }
                        
                        if ($estadoEncontrado) {
                            $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                            $attachedDocument     = MainTrait::obtenerArchivoDeDisco(
                                self::PROCESO_EMISION,
                                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                $documento,
                                array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                            );

                            if(!empty($attachedDocument)) {
                                $this->prepareXML($documento, ['est_xml' => base64_encode($attachedDocument)], $nombreXml, $uuid->toString() . '/');
                            }
                        }
                    }
                }
            }

            if (in_array('pdf', $arrTiposDocumentos)) {
                $datapdf = [];
                $getPdf = null;
                if ($request->has('documento_enviado') && $request->documento_enviado == 'SI') {
                    foreach ($arrCdoIds as $cdo_id) {
                        $documento = $this->obtenerDocumento($cdo_id, $losDocumentos);
                        if ($documento) {
                            // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo
                            $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                            if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->pdf) && $nombreArchivos->pdf != '') {
                                $nombrePdf = $nombreArchivos->pdf;
                            } else {
                                $nombrePdf = $this->getNombreArchivo($documento, self::TIPO_PDF);
                            }

                            $nombrePdf = $this->nombresArchivosDuplicados($nombrePdf, $nombresArchivos);

                            // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                            $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdo_id, self::ESTADO_NOTIFICACION);
                            if (!empty($estado)) {
                                $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                                $pdfRG                = MainTrait::obtenerArchivoDeDisco(
                                    self::PROCESO_EMISION,
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                                );
                            }

                            if(isset($pdfRG) && !empty($pdfRG)) {
                                $this->preparePDF($documento, ['est_archivo' => base64_encode($pdfRG)], $nombrePdf, '', $uuid->toString() . '/');
                            }
                        }
                    }
                } else { // documento sin envio
                    foreach ($arrCdoIds as $cdo_id) {
                        $documento = $this->obtenerDocumento($cdo_id, $losDocumentos);
                        if ($documento) {
                            // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo
                            $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                            if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->pdf) && $nombreArchivos->pdf != '') {
                                $nombrePdf = $nombreArchivos->pdf;
                            } else {
                                $nombrePdf = $this->getNombreArchivo($documento, self::TIPO_PDF);
                            }

                            $nombrePdf = $this->nombresArchivosDuplicados($nombrePdf, $nombresArchivos);

                            $getPdf = $this->getPdfRepresentacionGraficaDocumento($documento->cdo_id);
                            if (!$getPdf['error']) {
                                $this->preparePDF($documento, null, $nombrePdf, $getPdf['pdf'], $uuid->toString() . '/');
                            }
                        }
                    }
                }
            }

            if (in_array('pdf-generar', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos);
                    if ($documento) {
                        // Para los nombres de los archivos se verifica si existen en la BD, sino existen se generan con tipo + prefijo + consecutivo
                        $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                        if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->pdf) && $nombreArchivos->pdf != '') {
                            $nombrePdf = $nombreArchivos->pdf;
                        } else {
                            $nombrePdf = $this->getNombreArchivo($documento, self::TIPO_PDF);
                        }

                        $nombrePdf = $this->nombresArchivosDuplicados($nombrePdf, $nombresArchivos);

                        $getPdf = $this->getPdfRepresentacionGraficaDocumento($documento->cdo_id);
                        if (!$getPdf['error']) {
                            $this->preparePDF($documento, null, $nombrePdf, $getPdf['pdf'], $uuid->toString() . '/');
                        }
                    }
                }
            }

            if (in_array('certificado', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos);
                    if ($documento && isset($documento->getDadDocumentosDaop[0]->cdo_informacion_adicional) && array_key_exists('certificado_mandato', $documento->getDadDocumentosDaop[0]->cdo_informacion_adicional) && $documento->getDadDocumentosDaop[0]->cdo_informacion_adicional['certificado_mandato'] != '') {
                        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                        $estado               = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdo_id, self::ESTADO_EDI);
                        $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                        $certificadoMandato   = MainTrait::obtenerArchivoDeDisco(
                            self::PROCESO_EMISION,
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('certificado', $informacionAdicional) ? $informacionAdicional['certificado'] : null
                        );

                        if(!empty($certificadoMandato)) {
                            $nombrePdf = $informacionAdicional['certificado'];
                            $nombrePdf = $this->nombresArchivosDuplicados($nombrePdf, $nombresArchivos);
                            $this->preparePDF($documento, null, $nombrePdf, base64_encode($certificadoMandato), $uuid->toString() . '/');
                        }
                    }
                }
            }

            if (in_array('json', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos);
                    if ($documento) {
                        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                        $estado               = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdo_id, self::ESTADO_EDI);
                        $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                        $archivoJson   = MainTrait::obtenerArchivoDeDisco(
                            self::PROCESO_EMISION,
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('est_json', $informacionAdicional) ? $informacionAdicional['est_json'] : null
                        );

                        if(!empty($archivoJson)) {
                            $nombreJson = $informacionAdicional['est_json'];
                            $this->prepareJson($documento, ['est_json' => $archivoJson], $nombreJson, $uuid->toString() . '/');
                        }
                    }
                }
            }

            // Por cada tipo de documento se debe crear el archivo para cada documento electrónico
            if (in_array('ar-dian', $arrTiposDocumentos)) {
                foreach ($arrCdoIds as $cdo_id) {
                    $documento = $this->obtenerDocumento($cdo_id, $losDocumentos);
                    if ($documento) {
                        // Estado del documento en donde debe existir en información adicional el nombre del archivo en disco
                        $estado = MainTrait::obtenerEstadoDocumento(self::PROCESO_EMISION, $cdo_id, self::ESTADO_DO, ['EXITOSO', 'FALLIDO']);
                        $informacionAdicional = !empty($estado->est_informacion_adicional) ? json_decode($estado->est_informacion_adicional, true) : [];
                        $archivoArDian = MainTrait::obtenerArchivoDeDisco(
                            self::PROCESO_EMISION,
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );

                        if(!empty($archivoArDian)) {
                            $nombreArDian = $informacionAdicional['est_xml'];
                            $this->prepareXML($documento, ['est_xml' => base64_encode($archivoArDian)], $nombreArDian, $uuid->toString() . '/');
                        }
                    }
                }
            }

            $nombreZip = $uuid->toString() . ".zip";
            try {
                $oZip = new ZipArchive();
                $oZip->open(storage_path('etl/descargas/' . $nombreZip), ZipArchive::OVERWRITE | ZipArchive::CREATE);
                $options = array('remove_all_path' => TRUE);
                $oZip->addGlob(storage_path('etl/descargas/' . $uuid->toString() . '/') . '*.{pdf,xml,json}', GLOB_BRACE, $options);
                $oZip->close();
                File::deleteDirectory(storage_path('etl/descargas/' . $uuid->toString()));
            } catch (\Exception $e) {
                // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
                // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
                $headers = [
                    header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                    header('X-Error-Status: 422'),
                    header('X-Error-Message: Ocurri&oacute; un problema al intentar descargar el archivo zip con los documentos')
                ];
                return response()->json([
                    'message' => 'Error en la Descarga',
                    'errors' => ['Ocurrio un problema al intentar generar el archivo zip con los documentos']
                ], 422, $headers);
            }
            
            if(!$request->has('portal_clientes') || ($request->has('portal_clientes') && !$request->portal_clientes)) {
                $headers = [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ];
                
                // Retorna el ZIP y lo elimina tan pronto es enviado en la respuesta
                return response()
                    ->download(storage_path('etl/descargas/' . $nombreZip), $nombreZip, $headers)
                    ->deleteFileAfterSend(true);
            } else {
                $base64 = base64_encode(File::get(storage_path('etl/descargas/' . $nombreZip)));
                File::delete(storage_path('etl/descargas/' . $nombreZip));
                
                return response()->json([
                    'data' => [
                        'archivo'       => $base64,
                        'nombreArchivo' => $nombreZip
                    ]
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Error en la Petición',
                'errors' => 'No se indicaron los tipos de documentos y/o documentos a descargar'
            ], 422);
        }
    }

    /**
     * Obtiene un documento juntos con las relaciones requeridas
     * para generar XML, PDF y/o Acuse de Recibo
     *
     * @param  integer $cdo_id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getDocumento($cdo_id) {
        $documento = EtlCabeceraDocumentoDaop::where('cdo_id', $cdo_id)
            ->with([
                'getDetalleDocumentosDaop',
                'getImpuestosItemsDocumentosDaop',
                'getDadDocumentosDaop:cdo_id,cdo_informacion_adicional',
                'getConfiguracionResolucionesFacturacion',
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,pai_id,dep_id,mun_id,rfi_id',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosPais',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosDepartamento',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosMunicipio',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosRegimenFiscal',
                'getConfiguracionAdquirente.getParametroPais',
                'getConfiguracionAdquirente.getParametroDepartamento',
                'getConfiguracionAdquirente.getParametroMunicipio'
            ])
            ->first();

        if(!$documento) {
            $documento = $this->emisionCabeceraService->consultarDocumentoByCdoId($cdo_id, [
                'getDetalleDocumentosDaop',
                'getImpuestosItemsDocumentosDaop',
                'getDadDocumentosDaop',
                'getConfiguracionResolucionesFacturacion',
                'getConfiguracionObligadoFacturarElectronicamente',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosPais',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosDepartamento',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosMunicipio',
                'getConfiguracionObligadoFacturarElectronicamente.getParametrosRegimenFiscal',
                'getConfiguracionAdquirente.getParametroPais',
                'getConfiguracionAdquirente.getParametroDepartamento',
                'getConfiguracionAdquirente.getParametroMunicipio'
            ]);
        }

        return $documento;
    }

    /**
     * Obtiene el pdf firmado electrónicamente de la representación gráfica de un documento.
     *
     * @param int $cdo_id ID del documento correspondiente
     * @return array Error/Pdf
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getPdfRepresentacionGraficaDocumento($cdo_id) {
        // Usuario autenticado
        $user = auth()->user();

        // Crea un token de usuario para enviarlo en la petición
        $token = auth()->login($user);
        
        // Cliente Guzzle
        $doApi = new Client([
            'base_uri' => config('variables_sistema.APP_URL'),
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json'
            ]
        ]);

        // Accede microservicio Firma para obtener la representación gráfica del documento
        try {
            $peticionDo = $doApi->request(
                'POST',
                config('variables_sistema.DO_API_URL') . '/api/pdf/pdf-representacion-grafica-documento',
                [
                    'form_params' => [
                        'cdo_id' => $cdo_id
                    ],
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token
                    ],
                    'http_errors' => false
                ]
            );

            $responsePeticionDo = json_decode((string)$peticionDo->getBody()->getContents());
            $status = $peticionDo->getStatusCode();

            if ($status == 500) {
                return [
                    'error' => true,
                    'message' => 'No fue posible generar la Representaci&oacute;n Gr&aacute;fica',
                    'pdf' => [
                        $responsePeticionDo->message . "  // line: " . $responsePeticionDo->debug->line
                    ]
                ];
            }

            if (isset($responsePeticionDo->errors) && $responsePeticionDo->errors != '') {
                return [
                    'error'   => true,
                    'pdf'     => $responsePeticionDo->errors,
                    'message' => $responsePeticionDo->errors
                ];
            } else {
                return [
                    'error' => false,
                    'pdf'   => $responsePeticionDo->data->pdf
                ];
            }
        } catch (RequestException $e) {
            $error = $e->getMessage();
            $error = explode("response:\n", $error);
            return [
                'error' => true,
                'pdf' => $error
            ];
        }
    }

    /**
     * Obtiene el pdf firmado electrónicamente del acuse de recibo del documento.
     *
     * @param int $cdo_id ID del documento
     * @return array Error/Pdf
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getPdfAcuseReciboDocumento($cdo_id) {
        // Usuario autenticado
        $user = auth()->user();

        // Crea un token de usuario para enviarlo en la petición
        $campos = [
            'id' => $user->usu_id,
            'email' => $user->usu_email,
            'nombre' => $user->usu_nombre,
            'identificacion' => $user->usu_identificacion,
        ];
        $token = JWTAuth::fromUser($user, $campos);

        // Cliente Guzzle
        $firmaApi = new Client([
            'base_uri' => env('FIRMA_API_URL'),
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json'
            ]
        ]);

        // Accede microservicio DO para obtener el acuse de recibo del documento
        try {
            $peticionFirma = $firmaApi->request(
                'POST',
                '/api/pdf-acuse-recibo-documento',
                [
                    'form_params' => [
                        'cdo_id' => $cdo_id
                    ],
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token
                    ]
                ]
            );
            $responsePeticionFirma = json_decode((string)$peticionFirma->getBody()->getContents());
            return [
                'error' => false,
                'pdf' => $responsePeticionFirma->data->acuse_recibo_documento
            ];
        } catch (RequestException $e) {
            $error = $e->getMessage();
            $error = explode("response:\n", $error);
            return [
                'error' => true,
                'pdf' => $error[0]
            ];
        }
    }

    /**
     * Realiza una consulta a mainIde para para obtener los AR de una lista de documentos
     * @param $params
     * @return array|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    private function getARs($params) {
        $token = $this->loginOpenIde();
        if ($token['autenticado']) {
            $file = storage_path('etl/descargas/' . Uuid::uuid4()->toString() . '.tmp');
            $ideApi = $this->clienteGuzzle();
            $descargarDocumentos = null;
            try {
                $descargarDocumentos = $ideApi->request(
                    'POST',
                    env('IDE_API_PREFIX') . 'get-ar-emision',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $token['token'],
                            'Content-Type' => 'application/json'
                        ],
                        'body' => $params,
                        'http_errors' => false,
                        'sink' => $file
                    ]
                );
                $status = $descargarDocumentos->getStatusCode();
                if ($status === 200) {
                    $cd = $descargarDocumentos->getHeader('Content-Disposition');
                    $filename = Uuid::uuid4()->toString() . '.xml';
                    if ($cd) {
                        $cd = $cd[0];
                        preg_match_all("/\w+\.\w+/", $cd, $filename);
                        $filename = $filename[0][0];
                    }
                    return ['file' => $file, 'filename' => $filename];
                }
            }
            catch (\Exception $e) {
                return null;
            }
        }
    }

    /**
     * Retorna una lista de errores de cargue de documentos.
     *
     * @param  Request  $request
     * @return Response
     */
    public function getListaErroresDocumentos(Request $request) {
        if($request->filled('pjjTipo') && $request->pjjTipo !== 'DN')
            $detectar_documentos = [$request->pjjTipo];
        elseif ($request->filled('pjjTipo') && $request->pjjTipo === 'DN')
            $detectar_documentos = ['NDI', 'API-DN'];
        else
            $detectar_documentos = ['API','EDI','INTEGRACION','NO-DETERMINADO','FC', 'ND-NC', 'NC', 'ND', 'CADISOFT'];

        if (!empty($request->excel)) {
            return $this->getListaErrores($detectar_documentos, true, 'errores_documentos', 'emision');
        }
        return $this->getListaErrores($detectar_documentos, false, '', 'emision');
    }

    /**
     * Descarga una lista de errores de cargue de documentos.
     *
     * @param  Request  $request
     * @return Response
     */
    public function descargarListaErroresDocumentos(Request $request) {
        return $this->getListaErroresDocumentos($request);
    }

    /**
     * Retorna una lista de errores de cargue de documentos soporte.
     *
     * @param  Request  $request
     * @return Response
     */
    public function getListaErroresDocumentosSoporte(Request $request) {
        $detectar_documentos = ['DS','DS_NC'];

        if (!empty($request->excel)) {
            return $this->getListaErrores($detectar_documentos, true, 'errores_documentos_soporte', 'emision');
        }
        return $this->getListaErrores($detectar_documentos, false, '', 'emision');
    }

    /**
     * Descarga una lista de errores de cargue de documentos soporte.
     *
     * @param  Request  $request
     * @return Response
     */
    public function descargarListaErroresDocumentosSoporte(Request $request) {
        return $this->getListaErroresDocumentosSoporte($request);
    }

    /** 
     * Retorna el estado de proceso de un documento en el sistema.
     *
     * @param  Request  $request
     * @return Response
     */
    public function archivoSalidaFtp(Request $request) {
        MainTrait::setFilesystemsInfo();
        
        // Usuario autenticado
        $user = auth()->user();

        // Obtiene el Oferente
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($request->ofe_id);

        // Obtiene la resolución de facturación
        $resolucion = ConfiguracionResolucionesFacturacion::find($request->rfa_id);

        // Verifica que el Ofe exista y este marcado para procesamiento FTP
        if ($ofe && $ofe->ofe_procesar_directorio_ftp == 'SI') {
            switch ($request->cdo_clasificacion) {
                case 'FC':
                    $tipoDocumento = 'factura';
                    $directorio = 'factura';
                    break;
                case 'NC':
                    $tipoDocumento = 'notaCredito';
                    $directorio = 'notacredito';
                    break;
                case 'ND':
                    $tipoDocumento = 'notaDebito';
                    $directorio = 'notadebito';
                    break;
            }

            // Se genera el archivo de salida correspondiente
            // conforme al formato de archivo definido para el Ofe
            if ($ofe->ofe_tipo_archivo_directorio_ftp == 'XML') {
                $oDomtree = new \DOMDocument('1.0', 'UTF-8');

                $oXmlRaiz = $oDomtree->createElement($tipoDocumento);
                $oDomtree->appendChild($oXmlRaiz);

                $numeroResolucion = $oDomtree->createElement("numeroResolucion", $resolucion->rfa_resolucion);
                $oXmlRaiz->appendChild($numeroResolucion);

                $prefijo = $oDomtree->createElement("prefijo", trim($request->rfa_prefijo));
                $oXmlRaiz->appendChild($prefijo);

                $consecutivo = $oDomtree->createElement("consecutivo", $request->cdo_consecutivo);
                $oXmlRaiz->appendChild($consecutivo);

                $fecha = $oDomtree->createElement("fecha", $request->cdo_fecha);
                $oXmlRaiz->appendChild($fecha);

                $hora = $oDomtree->createElement("hora", $request->cdo_hora);
                $oXmlRaiz->appendChild($hora);

                $cufe = $oDomtree->createElement("cufe", $request->cdo_cufe);
                $oXmlRaiz->appendChild($cufe);

                $signaturevalue = $oDomtree->createElement("signaturevalue", $request->cdo_signaturevalue);
                $oXmlRaiz->appendChild($signaturevalue);

                $qr = $oDomtree->createElement("qr", $request->cdo_qr);
                $oXmlRaiz->appendChild($qr);

                $xml = $oDomtree->saveXML();

                $ruta = $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/salida/' . $directorio . '/';
                $nombreArchivo = trim($request->rfa_prefijo) . $request->cdo_consecutivo . ".xml";
                Storage::disk('ftpOfes')->put($ruta . $nombreArchivo, $xml);
            } else { // Archivo de tipo Json
                $json = [
                    $tipoDocumento => [
                        'numeroResolucion'  => $resolucion->rfa_resolucion,
                        'prefijo'           => trim($request->rfa_prefijo),
                        'consecutivo'       => $request->cdo_consecutivo,
                        'fecha'             => $request->cdo_fecha,
                        'hora'              => $request->cdo_hora,
                        'cufe'              => $request->cdo_cufe,
                        'signaturevalue'    => $request->cdo_signaturevalue,
                        'qr'                => $request->cdo_qr
                    ]
                ];

                $ruta = $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/salida/' . $directorio . '/';
                $nombreArchivo = trim($request->rfa_prefijo) . $request->cdo_consecutivo . ".json";
                Storage::disk('ftpOfes')->put($ruta . $nombreArchivo, json_encode($json));
            }
        }
    }

    /**
     * Registra la gestión de documentos rechazados.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function registrarGestionDocumentosRechazados(Request $request) {
        if(!isset($request->cdo_ids) || !isset($request->texto_gestion)) {
            return response()->json([
                'message' => 'Falló la validación de la información recibida',
                'errors' => ['Faltan campos necesarios para registrar la gestión']
            ], 422);
        }

        $docs = explode(',', $request->cdo_ids);
        $erroresGestion = [];
        foreach ($docs as $doc) {
            // Verifica que exista el documento para el cual se guardara la gestion
            $documento = EtlCabeceraDocumentoDaop::select($this->columns)
            ->find($doc);
            $updated = true;
            if ($documento) {

                $nuevaGestion = [   'fecha'    =>  date('Y-m-d H:i:s'),
                                    'gestion'  =>  $request->texto_gestion,
                                    'usuario'  =>  $request->usu_id,
                                    'unombre'  =>  $request->usu_nombre
                                ];

                if ($documento->cdo_gestion != null && $documento->cdo_gestion != '') {
                    $gestionesArray = json_decode($documento->cdo_gestion);
                } else {
                    $gestionesArray = [];
                }
                array_push($gestionesArray, $nuevaGestion);
                $gestionesJson = json_encode($gestionesArray);
                $updated = $documento->update([
                    'cdo_gestion' => $gestionesJson
                ]);

            } else {
                array_push($erroresGestion, "Documento {$doc} no existe.");
                // $erroresGestion[] = 'Documento ' . $doc . ' no existe.';
            }

            if (!$updated) {
                array_push($erroresGestion, 'Fallo al actualizar el documento');
                // $erroresGestion[] = 'Falló al actualizar el documento';
            }
        }

        if (count($erroresGestion) > 0) {
            return response()->json([
                'message' => 'Errores al registrar la gestion de los documentos',
                'errors' => $erroresGestion
            ], 422);
        }

        return response()->json([
            'message' => 'Gestion registrada exitosamente'
        ], 201);
    }

    /**
     * Dado un número lo devuelve escrito en letras.
     *
     * @param integer $num - Número a convertir (máximo dos decimales) y el número no debe tener seprador de miles
     * @param boolean $fem - Forma femenina (true) o no (false).
     * @param boolean $dec - Con decimales (true) o no (false).
     * @return string - Devuelve el número escrito en letra.
     */
    private function num2letras($num, $fem = false, $dec = true) {
        $matuni[2] = "DOS";
        $matuni[3] = "TRES";
        $matuni[4] = "CUATRO";
        $matuni[5] = "CINCO";
        $matuni[6] = "SEIS";
        $matuni[7] = "SIETE";
        $matuni[8] = "OCHO";
        $matuni[9] = "NUEVE";
        $matuni[10] = "DIEZ";
        $matuni[11] = "ONCE";
        $matuni[12] = "DOCE";
        $matuni[13] = "TRECE";
        $matuni[14] = "CATORCE";
        $matuni[15] = "QUINCE";
        $matuni[16] = "DIECISEIS";
        $matuni[17] = "DIECISIETE";
        $matuni[18] = "DIECIOCHO";
        $matuni[19] = "DIECINUEVE";
        $matuni[20] = "VEINTE";
        $matunisub[2] = "DOS";
        $matunisub[3] = "TRES";
        $matunisub[4] = "CUATRO";
        $matunisub[5] = "QUIN";
        $matunisub[6] = "SEIS";
        $matunisub[7] = "SETE";
        $matunisub[8] = "OCHO";
        $matunisub[9] = "NOVE";

        $matdec[2] = "VEINT";
        $matdec[3] = "TREINTA";
        $matdec[4] = "CUARENTA";
        $matdec[5] = "CINCUENTA";
        $matdec[6] = "SESENTA";
        $matdec[7] = "SETENTA";
        $matdec[8] = "OCHENTA";
        $matdec[9] = "NOVENTA";
        $matsub[3] = 'MILL';
        $matsub[5] = 'BILL';
        $matsub[7] = 'MILL';
        $matsub[9] = 'TRILL';
        $matsub[11] = 'MILL';
        $matsub[13] = 'BILL';
        $matsub[15] = 'MILL';
        $matmil[4] = 'MILLONES';
        $matmil[6] = 'BILLONES';
        $matmil[7] = 'DE BILLONES';
        $matmil[8] = 'MILLONES DE BILLONES';
        $matmil[10] = 'TRILLONES';
        $matmil[11] = 'DE TRILLONES';
        $matmil[12] = 'MILLONES DE TRILLONES';
        $matmil[13] = 'DE TRILLONES';
        $matmil[14] = 'BILLONES DE TRILLONES';
        $matmil[15] = 'DE BILLONES DE TRILLONES';
        $matmil[16] = 'MILLONES DE BILLONES DE TRILLONES';

        //Zi hack
        $float = explode('.', $num);
        $num = $float[0];

        $num = trim((string)@$num);
        if ($num[0] == '-') {
            $neg = 'menos ';
            $num = substr($num, 1);
        } else {
            $neg = '';
        }
        while ($num[0] == '0') $num = substr($num, 1);
        if ($num[0] < '1' or $num[0] > 9) $num = '0' . $num;
        $zeros = true;
        $punt = false;
        $ent = '';
        $fra = '';
        for ($c = 0; $c < strlen($num); $c++) {
            $n = $num[$c];
            if (!(strpos(".,'''", $n) === false)) {
                if ($punt) break;
                else {
                    $punt = true;
                    continue;
                }
            } elseif (!(strpos('0123456789', $n) === false)) {
                if ($punt) {
                    if ($n != '0') $zeros = false;
                    $fra .= $n;
                } else
                    $ent .= $n;
            } else
                break;
        }
        $ent = '     ' . $ent;
        if ($dec and $fra and !$zeros) {
            $fin = ' coma';
            for ($n = 0; $n < strlen($fra); $n++) {
                if (($s = $fra[$n]) == '0')
                    $fin .= ' cero';
                elseif ($s == '1')
                    $fin .= $fem ? ' una' : ' un';
                else
                    $fin .= ' ' . $matuni[$s];
            }
        } else
            $fin = '';
        if ((int)$ent === 0) return 'Cero ' . $fin;
        $tex = '';
        $sub = 0;
        $mils = 0;
        $neutro = false;
        while (($num = substr($ent, -3)) != '   ') {
            $ent = substr($ent, 0, -3);
            if (++$sub < 3 and $fem) {
                $matuni[1] = 'una';
                $subcent = 'as';
            } else {
                $matuni[1] = $neutro ? 'un' : 'uno';
                $subcent = 'os';
            }
            $t = '';
            $n2 = substr($num, 1);
            if ($n2 == '00') {
                //
            } elseif ($n2 < 21)
                $t = ' ' . $matuni[(int)$n2];
            elseif ($n2 < 30) {
                $n3 = $num[2];
                if ($n3 != 0) $t = 'i' . $matuni[$n3];
                $n2 = $num[1];
                $t = ' ' . $matdec[$n2] . $t;
            } else {
                $n3 = $num[2];
                if ($n3 != 0) $t = ' y ' . $matuni[$n3];
                $n2 = $num[1];
                $t = ' ' . $matdec[$n2] . $t;
            }
            $n = $num[0];
            if ($n == 1) {
                $t = ' ciento' . $t;
            } elseif ($n == 5) {
                $t = ' ' . $matunisub[$n] . 'ient' . $subcent . $t;
            } elseif ($n != 0) {
                $t = ' ' . $matunisub[$n] . 'cient' . $subcent . $t;
            }
            if ($sub == 1) {
            } elseif (!isset($matsub[$sub])) {
                if ($num == 1) {
                    $t = ' mil';
                } elseif ($num > 1) {
                    $t .= ' mil';
                }
            } elseif ($num == 1) {
                $t .= ' ' . $matsub[$sub] . 'on';
            } elseif ($num > 1) {
                $t .= ' ' . $matsub[$sub] . 'ones';
            }
            if ($num == '000') $mils++;
            elseif ($mils != 0) {
                if (isset($matmil[$sub])) $t .= ' ' . $matmil[$sub];
                $mils = 0;
            }
            $neutro = true;
            $tex = $t . $tex;
        }
        $tex = $neg . substr($tex, 1) . $fin;
        // $end_num = strtoupper($tex).' CON '.$float[1].' CENTAVOS';
        $end_num = strtoupper($tex) . ' PESOS M/CTE';
        return $end_num;
    }

    /**
     * Cambia estado a un documento.
     * 
     * Este método hace uso del método cambiarEstadoDocumentos() que es el que se encarga del procesamiento frenta a modelos.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cambioEstadoDocumento(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        $reglas = [
            'ofe_identificacion' => 'string|required|max:20',
            'tipo_documento'     => 'string|required|max:2|in:FC,NC,ND',
            'prefijo'            => 'string|nullable|max:5',
            'consecutivo'        => 'string|required|max:20',
            'estado'             => 'string|required|in:ACTIVO,INACTIVO'
        ];

        $validator = Validator::make($request->all(), $reglas);

        if($validator->fails()) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select([
                'ofe_id',
                'bdd_id_rg'
            ])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors' => ['El OFE con identificación [' . $request->ofe_identificacion . '] no existe o se encuentra inactivo']
            ], 404);
        }

        $user = auth()->user();
        // Verifica que exista el documento
        $documento = EtlCabeceraDocumentoDaop::select(['cdo_id'])
            ->where('ofe_id', $ofe->ofe_id)
            ->where('cdo_consecutivo', $request->consecutivo)
            ->where('cdo_clasificacion', $request->tipo_documento);

        if($request->has('prefijo') && !empty($request->prefijo))
            $documento = $documento->where('rfa_prefijo', trim($request->prefijo));

        $documento = $documento->first();
        if(!$documento) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors' => ['El documento [' . $request->tipo_documento . '-' . $request->prefijo . $request->consecutivo . '] no existe para el OFE [' . $request->ofe_identificacion . ']']
            ], 404);
        }

        $token = auth()->login($user);

        $newRequest = new Request();
        $newRequest->headers->add(['Authorization' => 'Bearer ' . $token]);
        $newRequest->headers->add(['accept' => 'application/json']);
        $newRequest->headers->add(['content-type' => 'application/json']);
        $newRequest->headers->add(['cache-control' => 'no-cache']);
        $newRequest->merge([
            'cdoIds' => $documento->cdo_id,
            'estado' => $request->estado
        ]);

        return $this->cambiarEstadoDocumentos($newRequest);
    }

    /**
     * Cambia estado a los documentos.
     * 
     * El cambio de estado está sujeto a si el documento ha sido enviado o no a la DIAN y la validación de esto depende del parámetro tipoEnvio que se debe recibir dentro del request
     *
     * @param Request $request
     * @return Response
     */
    public function cambiarEstadoDocumentos(Request $request) {
        $acceso = MainTrait::validaAccesoUsuarioMA();
        if(!empty($acceso)) {
            return response()->json($acceso, 403);
        }

        if (!$request->has('cdoIds')) {
            return response()->json([
                'message' => 'Error',
                'errors' => ['Debe de especificar al menos un documento']
            ], 422);
        }

        $arrErrores = [];
        $documentos = [];
        $ids = explode(',', $request->json('cdoIds'));
        foreach ($ids as $id) {
            $documento = EtlCabeceraDocumentoDaop::select($this->columns)->where('cdo_id', $id)
                ->with([
                    'getDoFinalizado',
                    'getDocumentoRechazado',
                    'getNotificacionFinalizado',
                    'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion'
                ])
                ->doesntHave('getUblEnProceso')
                ->doesntHave('getDoEnProceso')
                ->doesntHave('getUblAttachedDocumentEnProceso')
                ->doesntHave('getNotificacionEnProceso')
                ->first();

            if (!$documento) {
                $msgError = "No existe el Documento con ID {$id} o tiene un estado en proceso";

                if(auth()->user()->baseDatosTieneParticionamiento()) {
                    $documento = $this->emisionCabeceraRepository->consultarDocumentoFatByCdoId($id);
                    
                    if(!is_null($documento))
                        $msgError = "El documento con ID {$id} no se encuentra en la data operativa";
                }

                array_push($arrErrores, [$msgError]);
            } else {
                array_push($documentos, $documento);
            }
        }

        $documentosCambioEstado = '';
        foreach ($documentos as $documento) {
            // Si tiene no tiene estado DO o este es FALLIDO y no ha sido notificado, se permite el cambio de estado del documento
            if (!$documento->getDoFinalizado && !$documento->getNotificacionFinalizado) {
                // Cuando el request se envia desde documentos enviados, se debe validar que si el nit es el de DHL Express,
                // es una factura y si la RG es 9, si cumple estas condiciones el sistema no permite el cambio de estado a INACTIVO
                if (
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == self::NIT_DHLEXPRESS && 
                    $documento->cdo_representacion_grafica_documento == '9' && 
                    $documento->cdo_clasificacion == 'FC' &&
                    $request->has('tipoEnvio') && $request->tipoEnvio == 'enviados'
                ) {
                    array_push($arrErrores, (($documento->rfa_prefijo != null) ? $documento->rfa_prefijo : "") . $documento->cdo_consecutivo . " Para los documentos con RG 9 de DHL Express utilice la opción 'Modificar Documento'.");
                } else {
                    // Si el documento no tiene ni DO ni NOTIFICACIÓN exitosos, pero si tienen estado rechazado por la DIAN en DO
                    // se debe verificar que el último estado DO en el resultado de falla no sea por timeout u otro error generado por la libreria SecureBlackbox
                    // Si es un documento que no tiene estados de DO, se debe dejar inactivar
                    if(
                        (
                            isset($documento->getDocumentoRechazado) &&
                            !stristr($documento->getDocumentoRechazado->est_mensaje_resultado, 'SecureBlackbox library exception') && 
                            !stristr($documento->getDocumentoRechazado->est_mensaje_resultado, 'Ha ocurrido un error. Por favor inténtelo de nuevo')
                        ) ||
                        !isset($documento->getDocumentoRechazado)
                    ) {
                        // Validación que aplica para EMSSANAR cuando es un documento soporte
                        if($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == TenantIdentificacionOfesTrait::$nitOfeEmssanar && $documento->cdo_clasificacion === 'DS') {
                            $docGestion = RepGestionDocumentoDaop::select('gdo_id')
                                ->where('gdo_id_emision', $documento->cdo_id)
                                ->where('gdo_estado_etapa1', '!=', 'SIN_GESTION')
                                ->first();
                            if($docGestion) {
                                array_push($arrErrores, "El documento [".(empty($documento->rfa_prefijo) ? "" : $documento->rfa_prefijo."-") . $documento->cdo_consecutivo . "] se encuentra en proceso en el Módulo Gestión de Documentos.");
                                continue;
                            }
                        }
                        $documentosCambioEstado .= (($documento->rfa_prefijo != null) ? $documento->rfa_prefijo : "") . $documento->cdo_consecutivo . ", ";

                        if(!$request->has('estado')) {
                            $nuevoEstado = ($documento->estado == 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
                        } else {
                            $nuevoEstado = $request->estado;
                        }

                        $documento->update([
                            'estado' => $nuevoEstado
                        ]);
                    } else {
                        array_push($arrErrores, ($documento->rfa_prefijo ?? "") . $documento->cdo_consecutivo . " no puede cambiar de estado, este se encuentra en proceso de validación o ya fue registrado en la DIAN.");
                    }
                }
            } else {
                if ($documento->getDoFinalizado)
                    array_push($arrErrores, ($documento->rfa_prefijo ?? "") . $documento->cdo_consecutivo . " no puede cambiar de estado, este ya fue transmitido y validado por la DIAN.");
                    
                if ($documento->getNotificacionFinalizado)
                    array_push($arrErrores, ($documento->rfa_prefijo ?? "") . $documento->cdo_consecutivo . " no puede cambiar de estado, este ya fue Notificado al Adquirente.");
            }
        }

        return response()->json([
            'message' => ($documentosCambioEstado != '') ? 'Documentos a los que se cambió su estado: ' . substr($documentosCambioEstado, 0, -2) : '',
            'errors'  => $arrErrores
        ], 200);
    }

    /**
     * Retorna el resultado en cada uno de los procesos por lo que el documento pasa
     *
     * @param  $id ID del Documento a consultar
     * @return \Illuminate\Http\Response
     */
    public function getResultadosProcesos($id) {

        // Obtiene el Documento
        $documento = EtlCabeceraDocumentoDaop::select([
            'cdo_resultado_xml',
            'cdo_mensaje_xml',
            'age_estado_proceso_xml',
            'cdo_resultado_xml_firmado',
            'cdo_mensaje_xml_firmado',
            'age_estado_proceso_firma',
            'cdo_resultado_ws',
            'cdo_fecha_envio_ws',
            'cdo_mensaje_ws',
            'cdo_resultado_ws_crt',
            'age_estado_proceso_ws',            
            'cdo_resultado_ws_crt_object'])
            ->find($id);

        $data = array();

        //Resultado proceso PARSER
        $data['age_estado_proceso_xml'] = $documento->age_estado_proceso_xml;
        if ($documento->cdo_resultado_xml == 'EXITOSO') {
            $data['cdo_mensaje_xml'] = 'Proceso Realizado con Exito.';
        } elseif($documento->age_estado_proceso_xml == 'ENPROCESO') {
            $data['cdo_mensaje_xml'] = '';
        } else {
            $data['cdo_mensaje_xml'] = $documento->cdo_mensaje_xml;
        }
        
        //Resultado proceso Firma
        $data['age_estado_proceso_firma'] = $documento->age_estado_proceso_firma;
        if ($documento->cdo_resultado_xml_firmado == 'EXITOSO') {
            $data['cdo_mensaje_xml_firmado'] = 'Proceso Realizado con Exito.';
        } elseif($documento->age_estado_proceso_xml == 'ENPROCESO') {
            $data['cdo_mensaje_xml_firmado'] = '';
        } else {
            $data['cdo_mensaje_xml_firmado'] = $documento->cdo_mensaje_xml_firmado;
        }

        //Resultado proceso DO
        $data['age_estado_proceso_ws'] = $documento->age_estado_proceso_ws;
        $data['cdo_fecha_envio_ws'] = $documento->cdo_fecha_envio_ws;
        if ($documento->cdo_resultado_ws == 'EXITOSO') {
            $data['cdo_mensaje_ws'] = $documento->cdo_mensaje_ws;
        } elseif($documento->age_estado_proceso_xml == 'ENPROCESO') {
            $data['cdo_mensaje_ws'] = '';
        } else {
            $data['cdo_mensaje_ws'] = $documento->cdo_mensaje_ws;
        }

        //Resultado CRT
        $resultadoCrt = array();
        $data['age_estado_proceso_ws'] = $documento->age_estado_proceso_ws;
        if($documento->age_estado_proceso_xml == 'ENPROCESO') {
            $indResultado = count($resultadoCrt);
            $resultadoCrt['message'] = 'Consulta de Resultado de Transaccion en Proceso.';
            $data['cdo_resultado_ws_crt_object'] = $resultadoCrt;
        } else {
            if (isset($documento->cdo_resultado_ws_crt_object->DocumentoRecibido) && is_array($documento->cdo_resultado_ws_crt_object->DocumentoRecibido)) {
                //Retorna Varios resultados
                //array con cada uno de los posibles estados
                $resultadoExitoso = array();
                $resultadoValidacion = array();
                $resultadoRecibida = array();
                $resultadoFallido = array();
                foreach ($documento->cdo_resultado_ws_crt_object->DocumentoRecibido as $resultado) {
                    // Exitoso
                    if ($resultado->DatosBasicosDocumento->EstadoDocumento == 7200002 && count($resultadoExitoso) == 0) {
                        if (isset($resultado->DatosBasicosDocumento)) {
                            $resultadoExitoso['Emisor'] = (isset($resultado->DatosBasicosDocumento->Emisor)) ? $resultado->DatosBasicosDocumento->Emisor : "";
                            $resultadoExitoso['FechaHoraEmision'] = (isset($resultado->DatosBasicosDocumento->FechaHoraEmision)) ? $resultado->DatosBasicosDocumento->FechaHoraEmision : "";
                            $resultadoExitoso['EstadoDocumento'] = (isset($resultado->DatosBasicosDocumento->EstadoDocumento)) ? $resultado->DatosBasicosDocumento->EstadoDocumento : "";
                            $resultadoExitoso['DescripcionEstado'] = (isset($resultado->DatosBasicosDocumento->DescripcionEstado)) ? $resultado->DatosBasicosDocumento->DescripcionEstado : "";
                            $resultadoExitoso['NumeroDocumento'] = (isset($resultado->DatosBasicosDocumento->NumeroDocumento)) ? $resultado->DatosBasicosDocumento->NumeroDocumento : "";
                            $resultadoExitoso['CUFE'] = (isset($resultado->DatosBasicosDocumento->CUFE)) ? $resultado->DatosBasicosDocumento->CUFE : "";
                            if (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) {
                                $resultadoExitoso['FechaTransaccion'] = (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) ? $resultado->DatosBasicosDocumento->FechaTransaccion : "";
                            }
                            if (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) {
                                $resultadoExitoso['DescripcionTransaccion'] = (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) ? $resultado->DatosBasicosDocumento->DescripcionTransaccion : "";
                            }
                        }
                        if (isset($resultado->VerificacionFuncional->VerificacionDocumento)) {
                            $resultadoExitoso['VerificacionFuncional'] = (isset($resultado->VerificacionFuncional->VerificacionDocumento)) ? $resultado->VerificacionFuncional->VerificacionDocumento : "";
                        }
                        // En proceso de validación
                    } elseif($resultado->DatosBasicosDocumento->EstadoDocumento == 7200003 && count($resultadoValidacion) == 0) {
                        if (isset($resultado->DatosBasicosDocumento)) {
                            $resultadoValidacion['Emisor'] = (isset($resultado->DatosBasicosDocumento->Emisor )) ? $resultado->DatosBasicosDocumento->Emisor : "";
                            $resultadoValidacion['FechaHoraEmision'] = (isset($resultado->DatosBasicosDocumento->FechaHoraEmision)) ? $resultado->DatosBasicosDocumento->FechaHoraEmision : "";
                            $resultadoValidacion['EstadoDocumento'] = (isset($resultado->DatosBasicosDocumento->EstadoDocumento)) ? $resultado->DatosBasicosDocumento->EstadoDocumento : "";
                            $resultadoValidacion['DescripcionEstado'] = (isset($resultado->DatosBasicosDocumento->DescripcionEstado)) ? $resultado->DatosBasicosDocumento->DescripcionEstado : "";
                            $resultadoValidacion['NumeroDocumento'] = (isset($resultado->DatosBasicosDocumento->NumeroDocumento)) ? $resultado->DatosBasicosDocumento->NumeroDocumento : "";
                            $resultadoValidacion['CUFE'] = (isset($resultado->DatosBasicosDocumento->CUFE)) ? $resultado->DatosBasicosDocumento->CUFE : "";
                            if (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) {
                                $resultadoValidacion['FechaTransaccion'] = (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) ? $resultado->DatosBasicosDocumento->FechaTransaccion : "";
                            }
                            if (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) {
                                $resultadoValidacion['DescripcionTransaccion'] = (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) ? $resultado->DatosBasicosDocumento->DescripcionTransaccion : "";
                            }
                        }
                        if (isset($resultado->VerificacionFuncional->VerificacionDocumento)) {
                            $resultadoValidacion['VerificacionFuncional'] = (isset($resultado->VerificacionFuncional->VerificacionDocumento)) ? $resultado->VerificacionFuncional->VerificacionDocumento : "";
                        }
                    // Documento recibido
                    } elseif($resultado->DatosBasicosDocumento->EstadoDocumento == 7200001 && count($resultadoRecibida) == 0) {
                        if (isset($resultado->DatosBasicosDocumento)) {
                            $resultadoRecibida['Emisor'] = (isset($resultado->DatosBasicosDocumento->Emisor)) ? $resultado->DatosBasicosDocumento->Emisor : "";
                            $resultadoRecibida['FechaHoraEmision'] = (isset($resultado->DatosBasicosDocumento->FechaHoraEmision)) ? $resultado->DatosBasicosDocumento->FechaHoraEmision : "";
                            $resultadoRecibida['EstadoDocumento'] = (isset($resultado->DatosBasicosDocumento->EstadoDocumento)) ? $resultado->DatosBasicosDocumento->EstadoDocumento : "";
                            $resultadoRecibida['DescripcionEstado'] = (isset($resultado->DatosBasicosDocumento->DescripcionEstado)) ? $resultado->DatosBasicosDocumento->DescripcionEstado : "";
                            $resultadoRecibida['NumeroDocumento'] = (isset($resultado->DatosBasicosDocumento->NumeroDocumento)) ? $resultado->DatosBasicosDocumento->NumeroDocumento : "";
                            $resultadoRecibida['CUFE'] = (isset($resultado->DatosBasicosDocumento->CUFE)) ? $resultado->DatosBasicosDocumento->CUFE : "";
                            if (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) {
                                $resultadoRecibida['FechaTransaccion'] = (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) ? $resultado->DatosBasicosDocumento->FechaTransaccion : "";
                            }
                            if (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) {
                                $resultadoRecibida['DescripcionTransaccion'] = (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) ? $resultado->DatosBasicosDocumento->DescripcionTransaccion : "";
                            }
                        }
                        if (isset($resultado->VerificacionFuncional->VerificacionDocumento)) {
                            $resultadoRecibida['VerificacionFuncional'] = (isset($resultado->VerificacionFuncional->VerificacionDocumento)) ? $resultado->VerificacionFuncional->VerificacionDocumento : "";
                        }
                    //Fallido
                    } elseif (count($resultadoFallido) == 0) {
                        if (isset($resultado->DatosBasicosDocumento)) {
                            $resultadoFallido['Emisor'] = (isset($resultado->DatosBasicosDocumento->Emisor)) ? $resultado->DatosBasicosDocumento->Emisor : "";
                            $resultadoFallido['FechaHoraEmision'] = (isset($resultado->DatosBasicosDocumento->FechaHoraEmision)) ? $resultado->DatosBasicosDocumento->FechaHoraEmision : "";
                            $resultadoFallido['EstadoDocumento'] = (isset($resultado->DatosBasicosDocumento->EstadoDocumento)) ? $resultado->DatosBasicosDocumento->EstadoDocumento : "";
                            $resultadoFallido['DescripcionEstado'] = (isset($resultado->DatosBasicosDocumento->DescripcionEstado)) ? $resultado->DatosBasicosDocumento->DescripcionEstado : "";
                            $resultadoFallido['NumeroDocumento'] = (isset($resultado->DatosBasicosDocumento->NumeroDocumento)) ? $resultado->DatosBasicosDocumento->NumeroDocumento : "";
                            $resultadoFallido['CUFE'] = (isset($resultado->DatosBasicosDocumento->CUFE)) ? $resultado->DatosBasicosDocumento->CUFE : "";
                            if (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) {
                                $resultadoFallido['FechaTransaccion'] = (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) ? $resultado->DatosBasicosDocumento->FechaTransaccion : "";
                            }
                            if (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) {
                                $resultadoFallido['DescripcionTransaccion'] = (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) ? $resultado->DatosBasicosDocumento->DescripcionTransaccion : "";
                            }
                        }
                        if (isset($resultado->VerificacionFuncional->VerificacionDocumento)) {
                            $resultadoFallido['VerificacionFuncional'] = (isset($resultado->VerificacionFuncional->VerificacionDocumento)) ? $resultado->VerificacionFuncional->VerificacionDocumento : "";
                        }
                    }
                }
                
                if (count($resultadoExitoso) > 0) {
                    $data['cdo_resultado_ws_crt_object'] = $resultadoExitoso;
                } elseif (count($resultadoValidacion) > 0) {
                    $data['cdo_resultado_ws_crt_object'] = $resultadoValidacion;
                } elseif (count($resultadoRecibida) > 0) {
                    $data['cdo_resultado_ws_crt_object'] = $resultadoRecibida;
                } elseif (count($resultadoFallido) > 0) {
                    $data['cdo_resultado_ws_crt_object'] = $resultadoFallido;
                } else {
                    $data['cdo_resultado_ws_crt_object'] = $resultadoCrt;
                }
            } elseif(isset($documento->cdo_resultado_ws_crt_object->DocumentoRecibido) && is_object($documento->cdo_resultado_ws_crt_object->DocumentoRecibido)) {
                //Retorna Un solo resultado
                $resultado = $documento->cdo_resultado_ws_crt_object->DocumentoRecibido;
                $indResultado = count($resultadoCrt);
                if (isset($resultado->DatosBasicosDocumento)) {
                    $resultadoCrt['Emisor'] = (isset($resultado->DatosBasicosDocumento->Emisor)) ? $resultado->DatosBasicosDocumento->Emisor : "";
                    $resultadoCrt['FechaHoraEmision'] = (isset($resultado->DatosBasicosDocumento->FechaHoraEmision)) ? $resultado->DatosBasicosDocumento->FechaHoraEmision : "";
                    $resultadoCrt['EstadoDocumento'] = (isset($resultado->DatosBasicosDocumento->EstadoDocumento)) ? $resultado->DatosBasicosDocumento->EstadoDocumento : "";
                    $resultadoCrt['DescripcionEstado'] = (isset($resultado->DatosBasicosDocumento->DescripcionEstado)) ? $resultado->DatosBasicosDocumento->DescripcionEstado : "";
                    $resultadoCrt['NumeroDocumento'] = (isset($resultado->DatosBasicosDocumento->NumeroDocumento)) ? $resultado->DatosBasicosDocumento->NumeroDocumento : "";
                    $resultadoCrt['CUFE'] = (isset($resultado->DatosBasicosDocumento->CUFE)) ? $resultado->DatosBasicosDocumento->CUFE : "";
                    if (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) {
                        $resultadoCrt['FechaTransaccion'] = (isset($resultado->DatosBasicosDocumento->FechaTransaccion)) ? $resultado->DatosBasicosDocumento->FechaTransaccion : "";
                    }
                    if (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) {
                        $resultadoCrt['DescripcionTransaccion'] = (isset($resultado->DatosBasicosDocumento->DescripcionTransaccion)) ? $resultado->DatosBasicosDocumento->DescripcionTransaccion : "";
                    }
                }
                if (isset($resultado->VerificacionFuncional->VerificacionDocumento)) {
                    $resultadoCrt['VerificacionFuncional'] = (isset($resultado->VerificacionFuncional->VerificacionDocumento)) ? $resultado->VerificacionFuncional->VerificacionDocumento : "";
                }
                $data['cdo_resultado_ws_crt_object'] = $resultadoCrt;
            } elseif (isset($documento->cdo_resultado_ws_crt_object->DescripcionTransaccion)) {
                //Vericando si llego la posicion CodigoTransaccion
                $indResultado = count($resultadoCrt);
                $resultadoCrt['FechaTransaccion'] = (isset($documento->cdo_resultado_ws_crt_object->FechaTransaccion)) ? $documento->cdo_resultado_ws_crt_object->FechaTransaccion : "";
                $resultadoCrt['DescripcionTransaccion'] = (isset($documento->cdo_resultado_ws_crt_object->DescripcionTransaccion)) ? $documento->cdo_resultado_ws_crt_object->DescripcionTransaccion : "";
                $data['cdo_resultado_ws_crt_object'] = $resultadoCrt;
            } elseif (isset($documento->cdo_resultado_ws_crt_object) && is_array($documento->cdo_resultado_ws_crt_object)) {
                foreach ($documento->cdo_resultado_ws_crt_object as $resultado) {
                    //Retorna la posicion error
                    $indResultado = count($resultadoCrt);
                    $resultadoCrt['message'] = $resultado;
                }
                $data['cdo_resultado_ws_crt_object'] = $resultadoCrt;
            } else {
                //Retorna un texto
                if ($documento->cdo_resultado_ws_crt_object != '') {
                    $indResultado = count($resultadoCrt);
                    $resultadoCrt['message'] = $documento->cdo_resultado_ws_crt_object;
                    $data['cdo_resultado_ws_crt_object'] = $resultadoCrt;
                }
            }
        }

        return response()->json([
            'data' =>  $data
        ], 200);
    }

    /**
     * Consulta data adicional de documentos, aplica a DHL Express - Proceso Pickup Cash
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarDataDocumentoModificar(Request $request) {
        // Verifica OFE sea DHL Express
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select([
                'ofe_id',
                'ofe_identificacion'
            ])
            ->where('ofe_id', $request->ofe_id)
            ->first();

        if($ofe->ofe_identificacion != self::NIT_DHLEXPRESS) {
            return response()->json([
                'message' => 'Consulta no Autorizada',
                'errors' => ['El OFE que realiza la consulta no esta autorizado a realizarla']
            ], 403);
        }

        $relaciones = [
            'getParametrosMoneda:mon_id,mon_codigo,mon_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta',
            'getParametrosMonedaExtranjera:mon_id,mon_codigo',
            'getMediosPagoDocumentosDaop:cdo_id,fpa_id,mpa_id,men_fecha_vencimiento,men_identificador_pago',
            'getMediosPagoDocumentosDaop.getMedioPago:mpa_id,mpa_codigo,mpa_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta',
            'getMediosPagoDocumentosDaop.getFormaPago:fpa_id,fpa_codigo,fpa_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta',
            'getDetalleDocumentosDaop:ddo_id,cdo_id,und_id,cpr_id,pre_id,ddo_tipo_item,ddo_codigo,ddo_descripcion_uno,ddo_descripcion_dos,ddo_descripcion_tres,ddo_notas,ddo_cantidad,ddo_cantidad_paquete,ddo_valor_unitario,ddo_total,ddo_valor_unitario_moneda_extranjera,ddo_total_moneda_extranjera,ddo_indicador_muestra,ddo_valor_muestra,ddo_valor_muestra_moneda_extranjera,ddo_nit_mandatario',
            'getDetalleDocumentosDaop.getCodigoUnidadMedida:und_id,und_codigo,und_descripcion',
            'getDetalleDocumentosDaop.getClasificacionProducto:cpr_id,cpr_codigo,cpr_nombre',
            'getDetalleDocumentosDaop.getPrecioReferencia:pre_id,pre_codigo,pre_descripcion',
            'getCargosDescuentosDocumentosDaop' => function($query) {
                $query->select(['cdd_id','cdo_id','ddo_id', 'cde_id','cdd_aplica','cdd_tipo','cdd_nombre','cdd_razon','cdd_porcentaje','cdd_base','cdd_valor','cdd_base_moneda_extranjera','cdd_valor_moneda_extranjera'])
                    ->where('cdd_aplica', 'CABECERA');
            },
            'getCargosDescuentosDocumentosDaop.getParametrosCodigoDescuento:cde_id,cde_codigo,cde_descripcion',
            'getImpuestosItemsDocumentosDaop:iid_id,tri_id,cdo_id,ddo_id,iid_porcentaje,iid_base,iid_valor,iid_base_moneda_extranjera,iid_valor_moneda_extranjera',
            'getImpuestosItemsDocumentosDaop.getTributo:tri_id,tri_codigo',
            'getDadDocumentosDaop' => function($query) {
                $query->selectRaw("dad_id, cdo_id, JSON_UNQUOTE(JSON_EXTRACT(cdo_informacion_adicional, '$.guia')) as guia, JSON_UNQUOTE(JSON_EXTRACT(cdo_informacion_adicional, '$.cuenta')) as cuenta");
            },
            'getEdiFinalizado:est_id,cdo_id',
            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
            'getConfiguracionAdquirente:adq_id,adq_id_personalizado,adq_identificacion,adq_razon_social',
            'getConfiguracionAutorizado:adq_id,adq_id_personalizado,adq_identificacion,adq_razon_social',
            'getUsuarioCreacion:usu_id,usu_identificacion,usu_email,usu_nombre',
            'getTipoDocumentoElectronico:tde_id,tde_codigo,tde_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta',
            'getTipoOperacion:top_id,top_codigo,top_descripcion,fecha_vigencia_desde,fecha_vigencia_hasta'
        ];

        switch($request->pickupcash) {
            case 'sin-pickupcash':
                break;
            case 'pickupcash-no-finalizado':
                $relaciones[] = 'getPickupCashDocumentoExitoso:est_id,cdo_id,est_informacion_adicional';
                break;
            case 'pickupcash-finalizado':
                $relaciones[] = 'getPickupCashDocumento:est_id,cdo_id,est_informacion_adicional';
                break;
        }

        // Consulta el documento con las relacionnes hacia la data requerida
        $documento = EtlCabeceraDocumentoDaop::
            where('cdo_id', $request->cdo_id)
            ->where('ofe_id', $request->ofe_id)
            ->whereHas('getEdiFinalizado')
            ->with($relaciones)
            ->first();

        if(!$documento) {
            return response()->json([
                'message' => 'Documento no Existe',
                'errors' => ['El documento que intenta consultar no existe o no ha sido procesado por EDI']
            ], 404);
        }

        return response()->json([
            'data' => $documento
        ], 200);
    }

    /**
     * Traduce el nombre de un estado a un valor aceptado.
     *
     * @param array $registro Array conteniendo información a procesar
     * @return string
     */
    private function homologarEstado(array $registro): string {
        if (isset($registro['estado'])) {
            switch ($registro['estado']) {
                case self::ESTADO_UBL:
                    return 'XML-UBL';
                case self::ESTADO_DO:
                    return 'WS DIAN';
                case self::ESTADO_ATTACHEDDOCUMENT:
                    return 'ATTACHEDDOCUMENT';
                case self::ESTADO_RECHAZO:
                    return 'RECLAMO (RECHAZO)';
                default:
                    return $registro['estado'];
            }
        }
    }

    /**
     * Consulta los datos del documento y el último estado exitoso, debe solicitar como parametros el nit del OFE, el prefijo y el consecutivo del documento
     *
     * @param Request $request
     * @param string $ofe_identificacion Identificación del OFE
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @return JsonResponse
     */
    public function consultarDocumento(Request $request, string $ofe_identificacion, string $prefijo, string $consecutivo): JsonResponse {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion','bdd_id_rg'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (is_null($ofe)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No existe el OFE {$ofe_identificacion}"]
            ], 404);
        }

        $documento = EtlCabeceraDocumentoDaop::select(
            [
                'cdo_id',
                'adq_id',
                'cdo_clasificacion',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_cufe',
                'cdo_qr',
                'cdo_signaturevalue',
                'cdo_fecha_validacion_dian',
                'estado'
            ])
            ->with(['getConfiguracionAdquirente:adq_id,adq_identificacion'])
            ->with(['getEstadosDocumentosDaop:est_id,cdo_id,est_estado,est_resultado,est_mensaje_resultado,est_correos,est_object,est_informacion_adicional,est_ejecucion,est_inicio_proceso,fecha_creacion'])
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
            if(auth()->user()->baseDatosTieneParticionamiento())
                $documento = $this->emisionCabeceraService->consultarDocumento($ofe->ofe_id, 0, $prefijo, $consecutivo, 0, ['getEstadosDocumentosDaop']);
            else
                $documento = null;

            if (is_null($documento))
                return response()->json([
                    'message' => 'Error en documento',
                    'errors' => ["El Documento ".(($prefijo === null) ? '' : $prefijo)."{$consecutivo} para el OFE {$ofe_identificacion} no existe"]
                ], 404);
        }

        $respuesta = $this->datosDocumento($documento, $ofe);

        return response()->json([
            'data' =>  $respuesta
        ], 200);
    }

    /**
     * Consulta los datos del documento y el último estado exitoso, debe solicitar como parametros el nit del OFE, el prefijo y el consecutivo del documento
     *
     * @param Request $request
     * @param string $ofe_identificacion Identificación del OFE
     * @param string $tipo Código del tipo de documento electrónico
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @return JsonResponse
     */
    public function consultarDocumentoElectronico(Request $request, string $ofe_identificacion, string $tipo, string $prefijo, string $consecutivo): JsonResponse {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion','bdd_id_rg'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (is_null($ofe)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No existe el OFE {$ofe_identificacion}"]
            ], 404);
        }

        $tipoDocumento = ParametrosTipoDocumentoElectronico::select(['tde_id','tde_codigo'])
            ->where('tde_codigo', $tipo)
            ->first();

        if (is_null($tipoDocumento)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No existe el Tipo de Documento Electronico {$tipo}"]
            ], 404);
        }

        $documento = EtlCabeceraDocumentoDaop::select(
            [
                'cdo_id',
                'adq_id',
                'cdo_clasificacion',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_cufe',
                'cdo_qr',
                'cdo_signaturevalue',
                'cdo_fecha_validacion_dian',
                'estado'
            ])
            ->with(['getConfiguracionAdquirente:adq_id,adq_identificacion'])
            ->with(['getEstadosDocumentosDaop:est_id,cdo_id,est_estado,est_resultado,est_mensaje_resultado,est_correos,est_object,est_informacion_adicional,est_ejecucion,est_inicio_proceso,fecha_creacion'])
            ->where('tde_id', $tipoDocumento->tde_id)
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
            if(auth()->user()->baseDatosTieneParticionamiento())
                $documento = $this->emisionCabeceraService->consultarDocumento($ofe->ofe_id, 0, $prefijo, $consecutivo, $tipoDocumento->tde_id, ['getEstadosDocumentosDaop']);
            else
                $documento = null;

            if (is_null($documento))
                return response()->json([
                    'message' => 'Error en documento',
                    'errors' => ["El Documento ".(($prefijo === null || $prefijo == '' || $prefijo == 'null') ? '' : $prefijo)."{$consecutivo} para el OFE {$ofe_identificacion} no existe"]
                ], 404);
        }

        $respuesta = $this->datosDocumento($documento, $ofe);

        return response()->json([
            'data' =>  $respuesta
        ], 200);
    }

    /**
     * Consulta los eventos de notificación asociados a un documento electrónico
     *
     * @param string $ofe_identificacion Identificación del OFE
     * @param string $tipo Código del tipo de documento electrónico
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @return JsonResponse
     */
    public function consultarEventosNotificacionDocumentoElectronico(string $ofe_identificacion, string $tipo, string $prefijo, string $consecutivo): JsonResponse {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (is_null($ofe)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No existe el OFE {$ofe_identificacion}"]
            ], 404);
        }

        $tipoDocumento = ParametrosTipoDocumentoElectronico::select(['tde_id', 'tde_codigo'])
            ->where('tde_codigo', $tipo)
            ->first();

        if (is_null($tipoDocumento)) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No existe el Tipo de Documento Electronico {$tipo}"]
            ], 404);
        }

        $documento = EtlCabeceraDocumentoDaop::select(
            [
                'cdo_id',
                'adq_id',
                'ofe_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_cufe',
                'cdo_qr',
                'cdo_signaturevalue',
                'cdo_fecha_validacion_dian',
                'estado'
            ])
            ->with([
                'getConfiguracionAdquirente' => function($query) {
                    $query->select([
                        'adq_id',
                        'adq_identificacion',
                        \DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo')
                    ]);
                },
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        \DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                    ]);
                },
                'getNotificacionFinalizado:est_id,cdo_id,est_estado,est_resultado,est_mensaje_resultado,est_fin_proceso',
                'getEventoNotificacionDocumentoDaop'  => function($query) {
                    $query->select(['evt_id', 'cdo_id', 'evt_evento', 'evt_correos', 'evt_amazonses_id', 'evt_fecha_hora', 'evt_json'])
                        ->orderBy('fecha_creacion', 'asc');
                }
            ])
            ->where('tde_id', $tipoDocumento->tde_id)
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
            if(auth()->user()->baseDatosTieneParticionamiento()) {
                $consultaHistorico = true;
                $documento = $this->emisionCabeceraService->consultarDocumento($ofe->ofe_id, 0, $prefijo, $consecutivo, $tipoDocumento->tde_id, ['getNotificacionFinalizado', 'getEventoNotificacionDocumentoDaop']);
            } else
                $documento = null;

            if (is_null($documento))
                return response()->json([
                    'message' => 'Error en documento',
                    'errors' => ["El Documento ".(($prefijo === null) ? '' : $prefijo)."{$consecutivo} para el OFE {$ofe_identificacion} no existe"]
                ], 404);
        }

        // Si se encontraron eventos de notificación para el documento, los agrupa mediante los correos notificados
        if($documento->has('getEventoNotificacionDocumentoDaop') && $documento->getEventoNotificacionDocumentoDaop->isNotEmpty()) {
            $eventos   = $documento->getEventoNotificacionDocumentoDaop->groupBy('evt_correos');
            $documento = $documento->toArray();
            $documento['get_evento_notificacion_documento_daop'] = $eventos;
            
            // Si se consultó el histórico se deben reorganizar los nombres de las relaciones obtenidas
            if(isset($consultaHistorico)) {
                $documento['get_configuracion_adquirente']                         = $documento['getConfiguracionAdquirente'];
                $documento['get_configuracion_obligado_facturar_electronicamente'] = $documento['getConfiguracionObligadoFacturarElectronicamente'];
                $documento['get_notificacion_finalizado']                          = $documento['getNotificacionFinalizado'];

                unset($documento['cdo_nombre_archivos']);
                unset($documento['get_configuracion_resoluciones_facturacion']);
                unset($documento['getConfiguracionAdquirente']);
                unset($documento['getConfiguracionObligadoFacturarElectronicamente']);
                unset($documento['getNotificacionFinalizado']);
                unset($documento['getEventoNotificacionDocumentoDaop']);
            }
        }

        return response()->json([
            'data' =>  $documento
        ], 200);
    }

    /**
     * Retorna los datos del documento y el último estado exitoso, debe solicitar como parametros el nit del OFE, el prefijo y el consecutivo del documento
     *
     * @param EtlCabeceraDocumentoDaop $documento Información dle documento electrónico
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Información del OFE relacionado con el documento
     * @return array
     */
    private function datosDocumento(EtlCabeceraDocumentoDaop $documento, ConfiguracionObligadoFacturarElectronicamente $ofe): array {
        $inicial   = 0;
        $ultimo    = array();
        $estadoDo  = array();
        $historico = array();

        foreach ($documento->getEstadosDocumentosDaop as $estado) {
            if ($inicial == 0)
                $ultimo = $estado instanceof \stdClass ? (array) $estado : $estado->toArray();

            $inicial++;

            if ($estado->est_estado == 'DO' && count($estadoDo) == 0)
                $estadoDo = $estado instanceof \stdClass ? (array) $estado : $estado->toArray();

            $historico[] = $estado instanceof \stdClass ? (array) $estado : $estado->toArray();
        }

        $historico = collect($historico)->sortBy('fecha_creacion')->toArray();
        
        //Procesando Ultimo Estado
        $ultimoEstado = array();
        $ultimoEstado['estado']            = (array_key_exists('est_estado', $ultimo) && !empty($ultimo['est_estado'])) ? $ultimo['est_estado'] : NULL;
        $ultimoEstado['resultado']         = (array_key_exists('est_resultado', $ultimo) && !empty($ultimo['est_resultado'])) ? $ultimo['est_resultado'] : NULL;
        $ultimoEstado['mensaje_resultado'] = (array_key_exists('est_mensaje_resultado', $ultimo) && !empty($ultimo['est_mensaje_resultado'])) ? $ultimo['est_mensaje_resultado'] : NULL;

        if (array_key_exists('est_estado', $ultimo) && $ultimo['est_estado'] == "NOTIFICACION")
            $ultimoEstado['correos_notificacion'] = $ultimo['est_correos'];

        $ultimoEstado['fecha']             = (array_key_exists('est_inicio_proceso', $ultimo) && !empty($ultimo['est_inicio_proceso'])) ? $ultimo['est_inicio_proceso'] : NULL;
        //homologando descripcion estados
        $ultimoEstado['estado'] = $this->homologarEstado($ultimoEstado);

        //Procesando historico Estados
        $historicoEstados = array();
        foreach ($historico as $estado) {
            $i = count($historicoEstados);
            $historicoEstados[$i]['estado']            = (array_key_exists('est_estado', $ultimo) && !empty($ultimo['est_estado'])) ? $estado['est_estado'] : NULL;
            $historicoEstados[$i]['resultado']         = (array_key_exists('est_resultado', $ultimo) && !empty($ultimo['est_resultado'])) ? $estado['est_resultado'] : NULL;
            $historicoEstados[$i]['mensaje_resultado'] = (array_key_exists('est_mensaje_resultado', $ultimo) && !empty($ultimo['est_mensaje_resultado'])) ? $estado['est_mensaje_resultado'] : NULL;

            if ($estado['est_estado'] == "NOTIFICACION")
                $historicoEstados[$i]['correos_notificacion'] = $estado['est_correos'];

            $historicoEstados[$i]['fecha']             = (array_key_exists('est_inicio_proceso', $ultimo) && !empty($ultimo['est_inicio_proceso'])) ? $estado['est_inicio_proceso'] : NULL;

            // Homologando descripcion estados
            $historicoEstados[$i]['estado'] = $this->homologarEstado($historicoEstados[$i]);
        }

        //Procesando Valiacion Previa
        $validacionPrevia = array();
        if (count($estadoDo) > 0) {
            $document_key              = '';
            $notificaciones            = '';
            $cdo_fecha_validacion_dian = '';

            $xml = base64_decode($estadoDo['est_informacion_adicional']);
            if (!is_null($estadoDo['est_informacion_adicional'])) {
                $json = json_decode($estadoDo['est_informacion_adicional']);
                if (!is_null($json) && isset($json->IssueDate) && isset($json->IssueTime)) {
                    $cdo_fecha_validacion_dian = strip_tags($json->IssueDate) . ' ' . strip_tags($json->IssueTime);
                }
            }

            if (!is_null($estadoDo['est_object'])) {
                if (isset($estadoDo['est_object']['XmlDocumentKey'])) {
                    $document_key = isset($estadoDo['est_object']['XmlDocumentKey']) ? $estadoDo['est_object']['XmlDocumentKey'] : '';
                }
                if (isset($estadoDo['est_object']['ErrorMessage'])) {
                    $notificaciones = isset($estadoDo['est_object']['ErrorMessage']) ? $estadoDo['est_object']['ErrorMessage'] : '';
                }
            }
        
            $validacionPrevia = [
                'estado'                    => $estadoDo['est_resultado'],
                'descripcion_estado'        => $estadoDo['est_mensaje_resultado'],
                'document_key'              => $document_key,
                'notificaciones'            => $notificaciones,
                'cdo_fecha_validacion_dian' => $cdo_fecha_validacion_dian
            ];
        }

        $respuesta = [
            'id'                        => $documento->cdo_id,
            'ofe_identificacion'        => $ofe->ofe_identificacion,
            'adquirente_identificacion' => $documento->getConfiguracionAdquirente->adq_identificacion,
            'prefijo'                   => trim($documento->rfa_prefijo),
            'consecutivo'               => $documento->cdo_consecutivo,
            'fecha_documento'           => $documento->cdo_fecha,
            'hora_documento'            => $documento->cdo_hora,
            'estado'                    => $documento->estado,
            'cufe'                      => $documento->cdo_cufe,
            'qr'                        => $documento->cdo_qr,
            'signaturevalue'            => $documento->cdo_signaturevalue,
            'ultimo_estado'             => $ultimoEstado,
            'historico_estados'         => $historicoEstados,
            'cdo_clasificacion'         => $documento->cdo_clasificacion
        ];

        if (count($validacionPrevia) > 0)
            $respuesta['resultado_validacion_previa'] = $validacionPrevia;

        return $respuesta;
    }

    /**
     * Permite generar consecutivos de documentos electrónicos.
     *
     * @param int $ofe_id ID del OFE
     * @param int $rfa_id ID de la resolución de facturación
     * @param string $rfa_prefijo Prefijo del documento
     * @return string $cdo_consecutivo Consecutivo generado
     */
    private function getConsecutivoDocumento($ofe_id, $rfa_id, $rfa_prefijo) {
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

    /**
     * Consulta el modelo Tenant de Consecutivo de Documento en busca de un consecutivo provisional para la resolución y prefijo del documento.
     *
     * @param string $ofe_id ID del OFE
     * @param string $rfa_id ID de la resolución de facturación
     * @param string $rfa_prefijo Prefijo de la resolución de facturación
     * @param string $tipoConsecutivo Tipo de consecutivo a consultas (PROVISIONAL/DEFINITIVO)
     * @return EtlConsecutivoDocumento Colección del consecutivo encontrado
     */
    private function consultarEtlConsecutivoDocumento($ofe_id, $rfa_id, $rfa_prefijo, $tipoConsecutivo) {
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
            $etlConsecutivoDocumento = EtlConsecutivoDocumento::where('ofe_id', $ofe_id)
                ->where('rfa_id', $rfa_id)
                ->where('rfa_prefijo', trim($rfa_prefijo))
                ->where('cdo_tipo_consecutivo', $tipoConsecutivo)
                ->where('cdo_periodo', Carbon::now()->subMonths(1)->format('Ym'))
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
            } else {
                return null;
            }
        }
    }

    /**
     * Arma la consulta para el documento de referencia dependiendo de si debe consultar el modelo de cabecera o el modelo FAT
     *
     * @param Request $request Parámetros de la petición
     * @param integer $ofe_id ID del OFE
     * @param string $tipoTabla Tipo de tabla sobre la cual generar la consulta (daop o fat)
     * @return mixed
     */
    private function armarConsultaDaopFatDocumentoReferencia(Request $request, int $ofe_id, string $tipoTabla) {
        $select = [
            'cdo_id',
            'tde_id',
            'ofe_id',
            'adq_id',
            'rfa_id',
            'rfa_prefijo',
            'cdo_consecutivo'
        ];

        if($tipoTabla == 'daop')
            $clase = EtlCabeceraDocumentoDaop::class;
        else
            $clase = EtlFatDocumentoDaop::class;

        $documento = $clase::select($select)
            ->when($tipoTabla == 'daop', function ($query) {
                return $query->with([
                        'getConfiguracionResolucionesFacturacion:rfa_id,rfa_resolucion,rfa_consecutivo_inicial,rfa_consecutivo_final,rfa_fecha_desde,rfa_fecha_hasta',
                        'getConfiguracionAdquirente:adq_id,adq_identificacion,adq_razon_social,adq_primer_apellido,adq_segundo_apellido,adq_primer_nombre,adq_otros_nombres'
                    ])
                    ->has('getDoFinalizado');
            }, function ($query) {
                return $query->with([
                        'getConfiguracionResolucionesFacturacion:rfa_id,rfa_resolucion,rfa_consecutivo_inicial,rfa_consecutivo_final,rfa_fecha_desde,rfa_fecha_hasta',
                        'getConfiguracionAdquirente:adq_id,adq_identificacion,adq_razon_social,adq_primer_apellido,adq_segundo_apellido,adq_primer_nombre,adq_otros_nombres'
                    ]);
            })
            ->where('ofe_id', $ofe_id)
            ->where('cdo_consecutivo', $request->cdo_consecutivo)
            ->where('cdo_fecha', $request->cdo_fecha)
            ->when($request->rfa_prefijo != '' && $request->rfa_prefijo != 'null' && $request->rfa_prefijo != null, function ($query) use ($request) {
                return $query->where('rfa_prefijo', trim($request->rfa_prefijo));
            })
            ->when($request->filled('aplica_para') && $request->aplica_para === 'DS', function ($query) {
                return $query->whereIn('cdo_clasificacion', ['DS','DS_NC']);
            }, function ($query) {
                return $query->whereNotIn('cdo_clasificacion', ['DS', 'DS_NC']);
            })
            ->where('cdo_cufe', '!=', '')
            ->where('estado', 'ACTIVO');

        return $documento;
    }

    /**
     * Consulta los datos de los documentos referencia filtrados, debe solicitar como parametros el nit del OFE, el consecutivo del documento y el prefijo.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarDocumentoElectronicoReferencia(Request $request) {
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_identificacion','bdd_id_rg'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (!$ofe) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No existe el OFE {$request->ofe_identificacion}"]
            ], 404);
        }

        $documentosDaop = $this->armarConsultaDaopFatDocumentoReferencia($request, $ofe->ofe_id, 'daop')
            ->get();

        $documentosFat  = $this->armarConsultaDaopFatDocumentoReferencia($request, $ofe->ofe_id, 'fat')
            ->get();

        $documentos = $documentosDaop->merge($documentosFat);

        if ($documentos->isEmpty()) {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["El Documento ".(($request->rfa_prefijo === null || $request->rfa_prefijo == '' || $request->rfa_prefijo == 'null') ? '' : $request->rfa_prefijo)."{$request->cdo_consecutivo} para el OFE {$request->ofe_identificacion} no existe"]
            ], 404);
        }

        return response()->json([
            'data' =>  $documentos
        ], 200);
    }

    /**
     * Consulta los datos del documento electrónico, debe solicitar como parametros el nit del OFE, el consecutivo del documento y el prefijo.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerInformacionDocumentoElectronico(Request $request) {
        $documento = EtlCabeceraDocumentoDaop::with(
            [
                'getConfiguracionAdquirente:adq_id,adq_identificacion,adq_razon_social,adq_primer_apellido,adq_segundo_apellido,adq_primer_nombre,adq_otros_nombres',
                'getEstadosDocumentosDaop:est_id,cdo_id,est_estado,est_resultado,est_mensaje_resultado,est_correos,est_object,est_informacion_adicional,est_ejecucion,est_inicio_proceso,fecha_creacion',
                'getConfiguracionResolucionesFacturacion',
                'getConfiguracionObligadoFacturarElectronicamente',
                'getTipoOperacion',
                'getTipoDocumentoElectronico',
                'getDadDocumentosDaop',
                'getMediosPagoDocumentosDaop:cdo_id,fpa_id,mpa_id,men_fecha_vencimiento,men_identificador_pago',
                'getMediosPagoDocumentosDaop.getMedioPago:mpa_id,mpa_codigo',
                'getMediosPagoDocumentosDaop.getFormaPago:fpa_id,fpa_codigo',
                'getCargosDescuentosDocumentosDaop.getFacturacionWebCargo',
                'getCargosDescuentosDocumentosDaop.getFacturacionWebDescuento',
                'getCargosDescuentosDocumentosDaop.getParametrosCodigoDescuento',
                'getCargosDescuentosDocumentosDaop.getTributo',
                'getAnticiposDocumentosDaop',
                'getDetalleDocumentosDaop.getCodigoUnidadMedida',
                'getDetalleDocumentosDaop.getClasificacionProducto',
                'getDetalleDocumentosDaop.getPrecioReferencia',
                'getDetalleDocumentosDaop.getTipoDocumento',
                'getImpuestosItemsDocumentosDaop.getTributo',
                'getParametrosMoneda',
                'getParametrosMonedaExtranjera'
            ]);

        // Condición para retornar el documento cuando es por editar o ver
        if ($request->editar_documento) {
            $documento = $documento->where('cdo_id', $request->cdo_id)
                ->where('ofe_id', $request->ofe_id);
        } else {
            $documento = $documento->where('tde_id', $request->tde_id)
                ->where('ofe_id', $request->ofe_id)
                ->where('cdo_consecutivo', $request->cdo_consecutivo);

            if($request->rfa_prefijo != '' && $request->rfa_prefijo != 'null' && $request->rfa_prefijo != null)
                $documento = $documento->where('rfa_prefijo', trim($request->rfa_prefijo));
            else
                $documento = $documento->where(function($query) {
                    $query->whereNull('rfa_prefijo')
                        ->orWhere('rfa_prefijo', '');
                });
        }

        $documento = $documento->first();

        if (!$documento) {
            if($request->filled('tipo_consulta') && $request->tipo_consulta == 'documentos-referencia')
                $documento = $this->emisionCabeceraService->consultarDocumento($request->ofe_id, 0, $request->rfa_prefijo, $request->cdo_consecutivo, $request->tde_id, [
                    'getConfiguracionAdquirente',
                    'getEstadosDocumentosDaop',
                    'getConfiguracionResolucionesFacturacion',
                    'getConfiguracionObligadoFacturarElectronicamente',
                    'getTipoOperacion',
                    'getTipoDocumentoElectronico',
                    'getDadDocumentosDaop',
                    'getMediosPagoDocumentosDaop',
                    'getMediosPagoDocumentosDaop.getMedioPago',
                    'getMediosPagoDocumentosDaop.getFormaPago',
                    'getCargosDescuentosDocumentosDaop',
                    'getCargosDescuentosDocumentosDaop.getFacturacionWebCargo',
                    'getCargosDescuentosDocumentosDaop.getFacturacionWebDescuento',
                    'getCargosDescuentosDocumentosDaop.getParametrosCodigoDescuento',
                    'getCargosDescuentosDocumentosDaop.getTributo',
                    'getAnticiposDocumentosDaop',
                    'getDetalleDocumentosDaop',
                    'getDetalleDocumentosDaop.getCodigoUnidadMedida',
                    'getDetalleDocumentosDaop.getClasificacionProducto',
                    'getDetalleDocumentosDaop.getPrecioReferencia',
                    'getDetalleDocumentosDaop.getTipoDocumento',
                    'getImpuestosItemsDocumentosDaop',
                    'getImpuestosItemsDocumentosDaop.getTributo',
                    'getParametrosMoneda',
                    'getParametrosMonedaExtranjera'
                ], 'documentos-referencia');

            if (!$documento)
                return response()->json([
                    'message' => 'Error en documento',
                    'errors' => ["El Documento ".(($request->rfa_prefijo === null || $request->rfa_prefijo == '' || $request->rfa_prefijo == 'null') ? '' : $request->rfa_prefijo)."{$request->cdo_consecutivo} no existe"]
                ], 404);
            else {
                $documentoTmp = [];
                foreach($documento->toArray() as $key => $value) {
                    if(substr($key, 0, 3) === 'get') {
                        $documentoTmp[$key] = $value;
                        $documentoTmp[Str::snake($key)] = $value; // Convierte las relaciones de camel case a snake case para emular el retorno de Eloquent
                    } else
                        $documentoTmp[$key] = $value;
                }

                $documento = json_decode(json_encode($documentoTmp));
            }
        }

        // Se decodifica la información del documento referencia
        if ($documento->cdo_documento_referencia != null) 
            $documento->cdo_documento_referencia = json_decode($documento->cdo_documento_referencia, true);

        // Se decodifica la información del concepto de corrección
        if ($documento->cdo_conceptos_correccion != null)
            $documento->cdo_conceptos_correccion = json_decode($documento->cdo_conceptos_correccion, true);

        // Se decodifica la información de las notas del ítem
        foreach ($documento->getDetalleDocumentosDaop as $key => $detalle) {
            $documento->getDetalleDocumentosDaop[$key]->ddo_notas = (array) json_decode($detalle->ddo_notas);
        }

        // Se decodifica la información de los campos personalizados del Ofe a nivel de cabecera e ítem
        $ofe = $documento->getConfiguracionObligadoFacturarElectronicamente;
        $ofes['valores_personalizados']         = '';
        $ofes['valores_personalizados_ds']      = '';
        $ofes['valores_personalizados_item']    = '';
        $ofes['valores_personalizados_item_ds'] = '';
        $arrKey = ['valores_personalizados','valores_personalizados_ds','valores_personalizados_item','valores_personalizados_item_ds'];

        if ($ofe->ofe_campos_personalizados_factura_generica != '' && $ofe->ofe_campos_personalizados_factura_generica != null) {
            foreach ($ofe->ofe_campos_personalizados_factura_generica as $key => $camposPersonalizados) {
                if (!empty($camposPersonalizados) && in_array($key, $arrKey)) {
                    foreach ($camposPersonalizados as $campo) {
                        $nameCampo = "";
                        $campo = (array) $campo;
                        foreach ($campo as $llave => $valorCampo) {
                            if ($llave == 'campo')
                                $nameCampo = $valorCampo;
                        }
                        $valorSanitizado = $this->sanear_string(str_replace(' ', '_', mb_strtolower($nameCampo, 'UTF-8')));

                        switch ($key) {
                            case 'valores_personalizados':
                                $arrCamposPersonalizados[0][] = $campo;
                                $arrCamposPersonalizados[1][] = $valorSanitizado;
                                break;
                            case 'valores_personalizados_item':
                                $arrCamposPersonalizadosItem[0][] = $campo;
                                $arrCamposPersonalizadosItem[1][] = $valorSanitizado;
                                break;
                            case 'valores_personalizados_ds':
                                $arrCamposPersonalizadosDS[0][] = $campo;
                                $arrCamposPersonalizadosDS[1][] = $valorSanitizado;
                                break;
                            case 'valores_personalizados_item_ds':
                                $arrCamposPersonalizadosItemDS[0][] = $campo;
                                $arrCamposPersonalizadosItemDS[1][] = $valorSanitizado;
                                break;
                            default:
                                break;
                        }
                    }
                    switch ($key) {
                        case 'valores_personalizados':
                            $ofes['valores_personalizados'] = $arrCamposPersonalizados;
                            break;
                        case 'valores_personalizados_item':
                            $ofes['valores_personalizados_item'] = $arrCamposPersonalizadosItem;
                            break;
                        case 'valores_personalizados_ds':
                            $ofes['valores_personalizados_ds'] = $arrCamposPersonalizadosDS;
                            break;
                        case 'valores_personalizados_item_ds':
                            $ofes['valores_personalizados_item_ds'] = $arrCamposPersonalizadosItemDS;
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        return response()->json([
            'data' =>  $documento
        ], 200);
    }

    /**
     * Obtiene los estados de un documento en específico.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerEstadosDocumento(Request $request) {
        $request->merge([
            'proceso' => 'emision'
        ]);

        return $this->estadosDocumento($request);
    }

    /**
     * Obtiene los documentos anexos de un documento en específico.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerDocumentosAnexos(Request $request) {
        $request->merge([
            'proceso' => 'emision'
        ]);

        return $this->documentosAnexos($request);
    }

    /**
     * Reemplaza el PDF asociado a un documento electrónico.
     *
     * @param Request $request
     * @return Response
     */
    public function reemplazarPdf(Request $request) {
        $rules = [
            'pdf'                => 'required|file',
            'ofe_identificacion' => 'required|string|max:20',
            'adq_identificacion' => 'required|string|max:20',
            'prefijo'            => 'nullable|string|max:5',
            'consecutivo'        => 'required|string|max:20'
        ];

        $validadorPdf = Validator::make($request->all(), $rules);
        if($validadorPdf->fails())
            return response()->json([
                'message' => 'Error al reemplazar el PDF',
                'errors'  => $validadorPdf->errors()->all()
            ], 400);

        if($request->file('pdf')->extension() !== 'pdf')
            return response()->json([
                'message' => 'Error al reemplazar el PDF',
                'errors'  => ['Solo se permite la cargar de archivos PDF']
            ], 409);

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'bdd_id_rg'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();

        if (!$ofe)
            return response()->json([
                'message' => 'Error al reemplazar el PDF',
                'errors' => ["No existe el OFE [{$request->ofe_identificacion}]"]
            ], 404);

        $adq = ConfiguracionAdquirente::select(['adq_id', 'adq_identificacion'])
            ->where('adq_identificacion', $request->adq_identificacion)
            ->first();

        if (!$adq)
            return response()->json([
                'message' => 'Error al reemplazar el PDF',
                'errors' => ["No existe el Adquirente [{$request->adq_identificacion}]"]
            ], 404);

        $documento = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'rfa_prefijo', 'cdo_consecutivo', 'fecha_creacion'])
            ->where('cdo_consecutivo', $request->consecutivo)
            ->where('ofe_id', $ofe->ofe_id)
            ->where('adq_id', $adq->adq_id)
            ->where('estado', 'ACTIVO');
                
        if($request->prefijo != '' && $request->prefijo != 'null' && $request->prefijo != null)
            $documento = $documento->where('rfa_prefijo', trim($request->prefijo));
        else
            $documento = $documento->where(function($query) {
                $query->whereNull('rfa_prefijo')
                    ->orWhere('rfa_prefijo', '');
            });

        $documento = $documento->first();

        if (!$documento) {
            // Si el documento no existe en DAOP se debe verificar si la BD del usuario está configurada para particionamiento y poder realizar una consulta a la tabla FAT e histórico
            if(auth()->user()->baseDatosTieneParticionamiento())
                $documento = $this->emisionCabeceraService->consultarDocumento($ofe->ofe_id, $adq->adq_id, $request->prefijo, $request->consecutivo, 0, []);
            else
                $documento = null;

            if (!$documento)
                return response()->json([
                    'message' => 'Error al reemplazar el PDF',
                    'errors' => ["No existe el Documento [{$request->prefijo}{$request->consecutivo}] o se encuentra inactivo"]
                ], 404);
        }

        // Guarda el PDF cargado en la ruta correspondiente en el disco
        $mainTraitClass = new MainTraitClass();
        $archivo = $mainTraitClass->fnGuardarArchivoEnDisco(
            $ofe->ofe_identificacion,
            $documento,
            'emision',
            'rg',
            'pdf',
            base64_encode(file_get_contents($request->file('pdf')->getRealPath()))
        );

        if(empty($archivo))
            return response()->json([
                'message' => 'Error al reemplazar el PDF',
                'errors' => ["Se presentó un error al reemplazar el PDF"]
            ], 409);

        // Genera el PDF para poder insertar fecha, firma, cufe y QR
        $pdfEditado = $this->getPdfRepresentacionGraficaDocumento($documento->cdo_id);

        if (!$pdfEditado['error']) {
            $archivo = $mainTraitClass->fnGuardarArchivoEnDisco(
                $ofe->ofe_identificacion,
                $documento,
                'emision',
                'rg',
                'pdf',
                $pdfEditado['pdf']
            );
        } else {
            return response()->json([
                'message' => 'Error en documento',
                'errors' => ["No fue posible obtener el PDF asociado al documento para insertar la información de la DIAN"]
            ], 409);
        }

        return response()->json([
            'message' => 'PDF reemplazado'
        ], 200);
    }

    /**
     * Genera una Interfaz de Documentos Soporte para guardar en Excel.
     *
     * @param int $ofe_id Id del Oferente
     * @return ExcelExport|ExcelExportPersonalizado
     */
    public function generarInterfaceDocumentosSoporte(int $ofe_id) {
        return $this->crearInterfaceDocumentosSoporte($ofe_id, 'DS');
    }

    /**
     * Genera una Interfaz de Notas Crédito Documento Soporte para guardar en Excel.
     *
     * @param int $ofe_id Id del Oferente
     * @return ExcelExport|ExcelExportPersonalizado
     */
    public function generarInterfaceNotaCreditoDocumentosSoporte(int $ofe_id) {
        return $this->crearInterfaceDocumentosSoporte($ofe_id, 'DS_NC');
    }

    /**
     * Permite crear una Interfaz para los Documentos Soporte y Notas Crédito Documento Soporte.
     *
     * @param int    $ofe_id Id del Oferente
     * @param string $tipoDocumento Indica el tipo de documento a generar
     * @return ExcelExport|ExcelExportPersonalizado
     */
    private function crearInterfaceDocumentosSoporte(int $ofe_id, string $tipoDocumento) {
        $objUser = auth()->user();
        $ofe = ConfiguracionObligadoFacturarElectronicamente::find($ofe_id);

        header('Access-Control-Expose-Headers: Content-Disposition');
        date_default_timezone_set('America/Bogota');
        $nombreArchivo =  ($tipoDocumento == 'DS') ? 'DS_' . date('YmdHis') : 'DS_NC_' . date('YmdHis');
        if (isset($ofe->ofe_excel_personalizado) && $ofe->ofe_excel_personalizado === 'SI') {
            $interface = ($tipoDocumento == 'DS') ? 'Ds' : 'DS_NC_';
            return $this->generateCustomInterface($nombreArchivo, $ofe, 'DS', $interface);
        }

        $columnasCabecera = [];
        $columnasDetalle  = [];

        // Valida si el campo ofe_columnas_personalizadas tiene información
        if (isset($ofe->ofe_columnas_personalizadas) && is_array($ofe->ofe_columnas_personalizadas) && !empty($ofe->ofe_columnas_personalizadas)) {
            if (array_key_exists('DS', $ofe->ofe_columnas_personalizadas) && (array_key_exists('detalle', $ofe->ofe_columnas_personalizadas['DS']) || array_key_exists('cabecera', $ofe->ofe_columnas_personalizadas['DS']))){
                $columnasCabecera = array_merge($columnasCabecera, $ofe->ofe_columnas_personalizadas['DS']['cabecera']);
                $columnasDetalle  = array_merge($columnasDetalle, $ofe->ofe_columnas_personalizadas['DS']['detalle']);
            }
        }

        $columnasDefault     = MainTrait::$columnasDefault;
        $columnasDefault     = array_replace($columnasDefault, array(2 => "NIT RECEPTOR", 3 => "NIT VENDEDOR"));
        $indiceNitAutorizado = array_search('NIT AUTORIZADO', $columnasDefault);
        unset($columnasDefault[$indiceNitAutorizado]);
        if ($tipoDocumento == 'DS_NC') {
            $indiceResolucionFacturacion = array_search('RESOLUCION FACTURACION', $columnasDefault);
            unset($columnasDefault[$indiceResolucionFacturacion]);
        }
        
        $nIndiceAdqPersonalizado = 0;
        // Valida si el campo ofe_identificador_unico_adquirente contiene el valor de adq_id_personalizado
        if (isset($ofe->ofe_identificador_unico_adquirente) && is_array($ofe->ofe_identificador_unico_adquirente) && !empty($ofe->ofe_identificador_unico_adquirente)) {
            if(in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)){
                array_splice($columnasDefault, 4, 0, 'ID PERSONALIZADO');
                $nIndiceAdqPersonalizado = 1;
            }
        }

        if ($tipoDocumento == 'DS_NC') {
            // Para las notas se agregan las columnas del documento referencia
            array_splice($columnasDefault, (9 + $nIndiceAdqPersonalizado), 0, [
                'PREFIJO FACTURA',
                'CONSECUTIVO FACTURA',
                'CUFE FACTURA',
                'FECHA FACTURA',
                'COD CONCEPTO CORRECCION',
                'OBSERVACION CORRECCION'
            ]);

            $interface         = 'DsNc';
            $indice            = ($nIndiceAdqPersonalizado == 0) ? 28 : 29;
            $letraColumna      = ($nIndiceAdqPersonalizado == 0) ? 'AA' : 'AB';
            $letraColAdicional = ($nIndiceAdqPersonalizado == 0) ? 'AB' : 'AC';

            if (count($columnasCabecera) > 0) {
                array_splice($columnasDefault, $indice, 0, $columnasCabecera);
            }
            $columnasDefault = array_merge($columnasDefault, MainTrait::$columnasItemDefaultDS);

            if (count($columnasDetalle) > 0) {
                array_splice($columnasDefault, count($columnasDefault), 0, $columnasDetalle);
            }
        } else {
            $interface         = 'Ds';
            $letraColumna      = ($nIndiceAdqPersonalizado == 0) ? 'V' : 'W';
            $letraColAdicional = ($nIndiceAdqPersonalizado == 0) ? 'W' : 'X';
            if (!empty($columnasCabecera)) {
                $columnasDefault = array_merge($columnasDefault, $columnasCabecera);
            }

            $columnasDefault = array_merge($columnasDefault, MainTrait::$columnasItemDefaultDS);
            if (!empty($columnasDetalle)) {
                $columnasDefault = array_merge($columnasDefault, $columnasDetalle);
            }
        }

        return new ExcelExport($nombreArchivo, $columnasDefault, [], $interface, $columnasCabecera, $columnasDetalle, $letraColumna, $letraColAdicional, false);
    }

    /**
     * Crea el agendamiento en background en el sistema para poder procesar y generar el archivo de Excel.
     *
     * @param  Request $request Parámetros de la petición
     * @return Response
     */
    function agendarReporte(Request $request) {
        $user = auth()->user();

        if($request->filled('tipo')) {
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
                'pjj_tipo'         => ($request->tipo === 'emision-enviados') ? 'EENVIADOS' : 'ENOENVIADOS',
                'pjj_json'         => json_encode(json_decode($request->json, TRUE)),
                'pjj_procesado'    => 'NO',
                'age_id'           => $agendamiento->age_id,
                'usuario_creacion' => $user->usu_id,
                'estado'           => 'ACTIVO'
            ]);
        }

        return response()->json([
            'message' => 'Reporte agendado para generarse en background'
        ], 201);
    }

    /**
     * Genera un reporte en Excel para de acuerdo a los filtros escogidos.
     *
     * @param  Request $request  Parámetros de la petición
     * @param  string  $pjj_tipo EENVIADOS|ENOENVIADOS Tipo de reporte a generar
     * @return array
     */
    public function procesarAgendamientoReporte(Request $request, string $pjj_tipo = 'EENVIADOS'): array {
        if($pjj_tipo === 'ENOENVIADOS') {
            $request->merge([
                'pjj_tipo' => $pjj_tipo
            ]);
            return $this->procesarAgendamientoReporteNoEnviados($request);
        } else {
            return $this->procesarAgendamientoReporteEnviados($request);
        }
    }

    /**
     * Procesa el reporte para los documentos enviados en emisión según los filtros.
     *
     * @param  Request $request Parámetros de la petición
     * @return array
     * @throws \Exception
     */
    private function procesarAgendamientoReporteEnviados(Request $request): array {
        try {
            $request->merge([
                'proceso_background' => true
            ]);
            $arrExcel =  $this->emisionCabeceraService->getListaDocumentosEnviados($request);

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
     * Procesa el reporte para los documentos sin envío en emisión según los filtros.
     *
     * @param  Request $request Parámetros de la petición
     * @return array
     * @throws \Exception
     */
    private function procesarAgendamientoReporteNoEnviados(Request $request): array {
        try {
            $this->request = $request;
            $arrExcel = $this->getListaDocumentos($request);

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
     * Retorna la lista de reportes que se han solicitado durante el día.
     * 
     * El listado de reportes se generará según la fecha del sistema para el usuario autenticado,
     * Si este usuario es un ADMINISTRADOR o MA se generarán todos los reportes.
     *
     * @param  Request $request Parámetros de la petición
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

        $consulta = EtlProcesamientoJson::select(['pjj_id', 'pjj_tipo', 'pjj_json', 'age_estado_proceso_json', 'fecha_modificacion', 'usuario_creacion'])
            ->where(function($query) {
                $query->where('pjj_tipo', 'EENVIADOS')
                ->orWhere('pjj_tipo', 'ENOENVIADOS');
            })
            ->when(!in_array($user->usu_type, ['ADMINISTRADOR', 'MA']), function ($query) use ($user) {
                return $query->where('usuario_creacion', $user->usu_id);
            })
            ->whereBetween('fecha_modificacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'])
            ->where('estado', 'ACTIVO')
            ->orderBy($columnaOrden, $ordenDireccion)
            ->get()
            ->filter(function($item) use ($request) {
                $tipoProceso = ($request->filled('proceso') && $request->proceso == 'documento_soporte') ? '"proceso":"documento_soporte"' : '"proceso":"emision"';
                return (strpos($item->pjj_json, $tipoProceso) !== false);
            })
            ->map(function($reporte) use ($request, &$reportes, &$totalReportes, &$filtradosReportes) {
                $totalReportes++;

                if (!$request->filled('buscar') || ($request->filled('buscar') && 
                    (strpos($reporte->fecha_modificacion, $request->buscar) !== false || strpos($reporte->pjj_json, $request->buscar) !== false))
                ) {
                    $filtradosReportes++;
                    $pjjJson           = json_decode($reporte->pjj_json);
                    $fechaModificacion = Carbon::createFromFormat('Y-m-d H:i:s', $reporte->fecha_modificacion)->format('Y-m-d H:i:s');
                    $tipoReporte       = 'Reportes Background';
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
