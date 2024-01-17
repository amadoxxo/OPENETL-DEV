<?php
namespace App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop;

use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Traits\RecepcionTrait;
use App\Http\Controllers\Controller;
use openEtl\Tenant\Traits\TenantTrait;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;
use App\Http\Modulos\NotificarEventosDian\NotificarEventosDianController;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepFatDocumentosDaop\RepFatDocumentoDaop;
use App\Console\Commands\DHLExpress\DhlExpressIntegracionOpencomexCausacionCxpCommand;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RepCabeceraDocumentoDaopController extends Controller {
    use FechaVigenciaValidations, DoTrait, RecepcionTrait;

    /**
     * Límite de intenatos de transmisión.
     *
     * @var integer
     */
    protected $limiteIntentos = 5;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:RecepcionTransmitirErp'
        ])->only([
            'transmitirErp'
        ]);
    }

    /**
     * Transmite la información de documentos existentes en el sistema a un ERP.
     * 
     * La acción depende de si el OFE tiene activada la opción ofe_recepcion_transmision_erp.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transmitirErp(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        
        $user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_recepcion', 'ofe_recepcion_transmision_erp', 'ofe_recepcion_conexion_erp'])
            ->where('ofe_id', $request->ofeId)
            ->where('ofe_recepcion', 'SI')
            ->where('ofe_recepcion_transmision_erp', 'SI')
            ->whereNotNull('ofe_recepcion_conexion_erp')
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error en Transmisión a ERP',
                'errors'  => 'El OFE indicado no existe, se encuentra inactivo o no tiene configurada la opción de Transmisión a ERP'
            ], 400);

        $bdUser = $user->getBaseDatos->bdd_nombre;
        if(!empty($user->bdd_id_rg)) {
            $bdUser = $user->getBaseDatosRg->bdd_nombre;
        }

        $bdUser = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdUser);

        // Clase personalizada de OFE para la transmisión al ERP
        $clase = 'App\\Http\\Modulos\\Recepcion\\TransmisionErp\\' . $bdUser . '\\erp' . $ofe->ofe_identificacion . '\\' . 'RecepcionTransmisionErp' . $ofe->ofe_identificacion;

        if(!class_exists($clase))
            return response()->json([
                'message' => 'Error en Transmisión a ERP',
                'errors'  => 'No existe la clase del OFE que permite la Transmisión a ERP'
            ], 409);

        new $clase(
            $this->limiteIntentos,
            $ofe,
            $request->cdoIds
        );

        return response()->json([
            'message' => 'Proceso realizado, por favor consulte el detalle de la transmisión de los documentos seleccionados'
        ], 200);
    }

    /**
     * Verifica si un documento existe en la tabla FAT de recepción.
     *
     * @param array $parametroDocumento Array del parámetro documento recibido en el request
     * @param int $tde_id ID del tipo de documento electrónico
     * @param int $ofe_id ID del OFE
     * @param int $cdo_id ID del Proveedor
     * @return null|RepFatDocumentoDaop
     */
    private function consultarDocumentoFat(array $parametroDocumento, int $tde_id, int $ofe_id, int $pro_id) {
        $docFat = RepFatDocumentoDaop::select([
                'cdo_id',
                'ofe_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha'
            ])
            ->where('tde_id', $tde_id)
            ->where('ofe_id', $ofe_id)
            ->where('pro_id', $pro_id);

        if($parametroDocumento['rfa_prefijo'] != '' && $parametroDocumento['rfa_prefijo'] != 'null' && $parametroDocumento['rfa_prefijo'] != null)
            $docFat = $docFat->where('rfa_prefijo', trim($parametroDocumento['rfa_prefijo']));
        else
            $docFat = $docFat->where(function($query) {
                $query->whereNull('rfa_prefijo')
                    ->orWhere('rfa_prefijo', '');
            });

        $docFat = $docFat->where('cdo_consecutivo', $parametroDocumento['cdo_consecutivo']);

        if(array_key_exists('cdo_cufe', $parametroDocumento))
            $docFat = $docFat->where('cdo_cufe', $parametroDocumento['cdo_cufe']);

        $docFat = $docFat->first();

        if(!$docFat)
            return null;

        return $docFat;
    }

    /**
     * Verifica si un documento existe en la tabla FAT de recepción mediante el cdo_id.
     *
     * @param int $cdo_id ID del documento
     * @return null|RepFatDocumentoDaop
     */
    public function consultarDocumentoFatByCdoId(int $cdo_id) {
        $documento = RepFatDocumentoDaop::select([
                'cdo_id',
                'ofe_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha'
            ])
            ->where('cdo_id', $cdo_id)
            ->first();

        if(!$documento)
            return null;

        return $documento;
    }

    /**
     * Consulta un estado de un documento electrónico para poder notificarlo.
     *
     * @param RepEstadoDocumentoDaop $classEstado Clase de estados, puede ser daop o partición
     * @param integer $cdo_id ID del documento
     * @param integer $ofe_id ID del OFE
     * @param string $evento Nombre del evento a notificar
     * @param string $particion Sufijo de la partición en donde se encuentra el documento, llega vacio cuando el documento está en la data operativa
     * @return null|RepEstadoDocumentoDaop
     */
    private function getEventoEstado(RepEstadoDocumentoDaop $classEstado, int $cdo_id, int $ofe_id, string $evento, string $particion = '') {
        if(empty($particion)) {
            return $classEstado::select(['est_id', 'cdo_id', 'est_estado', 'est_motivo_rechazo', 'est_informacion_adicional'])
                ->where('cdo_id', $cdo_id)
                ->where('est_estado', $evento)
                ->where('est_resultado', 'EXITOSO')
                ->where('est_ejecucion', 'FINALIZADO')
                ->where('estado', 'ACTIVO')
                ->with([
                    'getRepCabeceraDocumentosDaop:cdo_id,ofe_id,pro_id,rfa_prefijo,cdo_consecutivo,cdo_fecha,cdo_hora,cdo_fecha_estado,cdo_fecha_acuse,cdo_fecha_recibo_bien,fecha_creacion',
                    'getRepCabeceraDocumentosDaop.getConfiguracionProveedor:pro_id,pro_identificacion,pro_correo,pro_correos_notificacion',
                    'getRepCabeceraDocumentosDaop.getConfiguracionProveedor' => function($query) use ($ofe_id) {
                        $query = $this->verificaRelacionUsuarioProveedor($query, $ofe_id)
                            ->select([
                                'pro_id',
                                'pro_identificacion',
                                'pro_correo',
                                'pro_correos_notificacion'
                            ]);
                    },
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,mun_id,ofe_identificacion,ofe_correo,ofe_direccion,ofe_telefono',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getParametroMunicipio:mun_id,mun_codigo,mun_descripcion',
                ])
                ->whereHas('getRepCabeceraDocumentosDaop.getConfiguracionProveedor', function($query) use ($ofe_id) {
                    $query = $this->verificaRelacionUsuarioProveedor($query, $ofe_id);
                })
                ->first();
        } else {
            $consultaEstado = $classEstado->setTable('rep_estados_documentos_' . $particion)
                ->select(['est_id', 'cdo_id', 'est_estado', 'est_motivo_rechazo', 'est_informacion_adicional'])
                ->where('cdo_id', $cdo_id)
                ->where('est_estado', $evento)
                ->where('est_resultado', 'EXITOSO')
                ->where('est_ejecucion', 'FINALIZADO')
                ->where('estado', 'ACTIVO')
                ->first();

            if($consultaEstado) {
                $tablaCabecera = new RepCabeceraDocumentoDaop();
                $tablaCabecera->setTable('rep_cabecera_documentos_' . $particion);

                $getRepCabeceraDocumentosDaop = $tablaCabecera->select(['cdo_id', 'ofe_id', 'pro_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha', 'cdo_hora', 'cdo_fecha_estado', 'cdo_fecha_acuse', 'cdo_fecha_recibo_bien', 'fecha_creacion'])
                    ->with([
                        'getConfiguracionProveedor:pro_id,pro_identificacion,pro_correo,pro_correos_notificacion',
                        'getConfiguracionProveedor' => function($query) use ($ofe_id) {
                            $query = $this->verificaRelacionUsuarioProveedor($query, $ofe_id)
                                ->select([
                                    'pro_id',
                                    'pro_identificacion',
                                    'pro_correo',
                                    'pro_correos_notificacion'
                                ]);
                        },
                        'getConfiguracionObligadoFacturarElectronicamente:ofe_id,mun_id,ofe_identificacion,ofe_correo,ofe_direccion,ofe_telefono',
                        'getConfiguracionObligadoFacturarElectronicamente.getParametroMunicipio:mun_id,mun_codigo,mun_descripcion',
                    ])
                    ->whereHas('getConfiguracionProveedor', function($query) use ($ofe_id) {
                        $query = $this->verificaRelacionUsuarioProveedor($query, $ofe_id);
                    })
                    ->first();

                $consultaEstado->getRepCabeceraDocumentosDaop = $getRepCabeceraDocumentosDaop;
                $consultaEstado->getRepCabeceraDocumentosDaop->getConfiguracionProveedor = $getRepCabeceraDocumentosDaop->getConfiguracionProveedor;
                $consultaEstado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente = $getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente;

                return $consultaEstado;
            } else {
                return null;
            }
        }
    }


    /**
     * Realiza el reenvío de la notificación de un evento.
     *
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function reenvioNotificacionEvento(Request $request) {
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

        switch(strtolower($request->evento)) {
            case "acuse":
                $evento = 'NOTACUSERECIBO';
                break;
            case "recibobien":
                $evento = 'NOTRECIBOBIEN';
                break;
            case "aceptacion":
                $evento = 'NOTACEPTACION';
                break;
            case "reclamo":
                $evento = 'NOTRECHAZO';
                break;
            default:
                return response()->json([
                    'message' => 'Error al procesar la petición',
                    'errors'  => ['El evento debe corresponder a ACUSE, RECIBOBIEN, ACEPTACION o RECLAMO']
                ], 400);
        }
        
        $classReenvio              = new NotificarEventosDianController();
        $estadosNotificarEvento    = [];
        $documentosProcesados      = [];
        $documentosNoProcesados    = [];
        $documentosAgendarAD       = [];
        $documentosNotificarEvento = [];

        foreach($request->documentos as $documento) {
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
                ->where('ofe_identificacion', $documento['ofe_identificacion'])
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$ofe) {
                $documentosNoProcesados[] = 'Para el documento [' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'] . '] No existe el OFE [' . $documento['ofe_identificacion'] . ']';
                continue;
            }

            $pro = ConfiguracionProveedor::select(['pro_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_identificacion', $documento['pro_identificacion'])
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$pro) {
                $documentosNoProcesados[] = 'Para el documento [' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'] . '] No existe el Proveedor [' . $documento['pro_identificacion'] . ']';
                continue;
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
                $documentosNoProcesados[] = 'Para el documento [' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'] . '] No existe el tipo de documento electrónico [' . $documento['tde_codigo'] . '], o el documento es NO-ELECTRONICO y No se permite el reenvío de notificación.';
                continue;
            }

            $particion     = '';
            $classCabecera = new RepCabeceraDocumentoDaop();
            $doc = $classCabecera::select(['cdo_id', 'cdo_origen'])
                ->where('tde_id', $tdeID)
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_id', $pro->pro_id);

            if($documento['rfa_prefijo'] != '' && $documento['rfa_prefijo'] != 'null' && $documento['rfa_prefijo'] != null)
                $doc = $doc->where('rfa_prefijo', trim($documento['rfa_prefijo']));
            else
                $doc = $doc->where(function($query) {
                    $query->whereNull('rfa_prefijo')
                        ->orWhere('rfa_prefijo', '');
                });

            $doc = $doc->where('cdo_consecutivo', $documento['cdo_consecutivo']);

            if(array_key_exists('cdo_cufe', $documento))
                $doc = $doc->where('cdo_cufe', $documento['cdo_cufe']);

            $doc = $doc->first();

            if(!$doc) {
                $docFat = $this->consultarDocumentoFat($documento, $tdeID, $ofe->ofe_id, $pro->pro_id);

                if(!$docFat) {
                    $documentosNoProcesados[] = 'No existe el documento electrónico [' . $documento['rfa_prefijo'].$documento['cdo_consecutivo'] . '] para el OFE, proveedor y tipo de documento electrónico indicados';
                    continue;
                }

                $particion     = Carbon::parse($docFat->cdo_fecha)->format('Ym');
                $classCabecera = new RepCabeceraDocumentoDaop();
                $doc = $classCabecera->setTable('rep_cabecera_documentos_' . $particion)
                    ->newQuery()
                    ->select(['cdo_id', 'cdo_origen'])
                    ->where('cdo_id', $docFat->cdo_id)
                    ->first();

                if(!$doc) {
                    $documentosNoProcesados[] = 'No existe el documento electrónico [' . $documento['rfa_prefijo'].$documento['cdo_consecutivo'] . '] para el OFE, proveedor y tipo de documento electrónico indicados';
                    continue;
                }
            }

            if($doc && $doc->cdo_origen == 'NO-ELECTRONICO') {
                $documentosNoProcesados[] = 'Para el documento [' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'] . '] No se permite el reenvío de notificación por ser un documento no electrónico';
                continue;
            }

            if(isset($doc) && !isset($docFat)) { // Documento existe en data operativa, se debe agendar para generar el AttachedDocument del evento para notificación (UBLADXXXXX)
                $classEstado = new RepEstadoDocumentoDaop();
                $estado      = $this->getEventoEstado($classEstado, $doc->cdo_id, $ofe->ofe_id, str_replace('NOT', '', $evento), $particion);

                if(!$estado) {
                    $this->verificaDocumentoExiste($classCabecera, $doc->cdo_id, $documento['rfa_prefijo'], $documento['cdo_consecutivo'], $documentosNoProcesados);
                } else {
                    $documentosProcesados[] = '[' . $documento['rfa_prefijo'] . $documento['cdo_consecutivo'] . '] agendado para reenvío de notificación del evento';
                    $documentosAgendarAD[]  = $doc->cdo_id;
                }
            } elseif(isset($doc) && isset($docFat)) { // Documento existe en data histórica, se permite el reenvío de la notificación a partir del AttachedDocument existente para el evento y sin agendamientos nuevos
                $classEstado = new RepEstadoDocumentoDaop();
                $estado      = $this->getEventoEstado($classEstado, $doc->cdo_id, $ofe->ofe_id, $evento, $particion);

                if(!$estado) {
                    $this->verificaDocumentoExiste($classCabecera, $doc->cdo_id, $documento['rfa_prefijo'], $documento['cdo_consecutivo'], $documentosNoProcesados);
                } else {
                    $informacionAdicionalAr = ($estado->est_informacion_adicional != '') ? json_decode((string) $estado->est_informacion_adicional, true) : [];
                    $attachedDocument       = $this->obtenerArchivoDeDisco(
                        'recepcion',
                        $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                        $estado->getRepCabeceraDocumentosDaop,
                        array_key_exists('est_archivo', $informacionAdicionalAr) ? $informacionAdicionalAr['est_archivo'] : null
                    );
                    $attachedDocument = $this->eliminarCaracteresBOM($attachedDocument);
    
                    $documentosNotificarEvento[]     = $doc->cdo_id;
                    $estadosNotificarEvento[$doc->cdo_id] = [
                        'est_id'                     => $estado->est_id,
                        'est_estado'                 => $estado->est_estado,
                        'inicio'                     => null,
                        'estadoInformacionAdicional' => $informacionAdicionalAr,
                        'attached_document_pdf'      => $attachedDocument,
                        'particion'                  => isset($particion) && !empty($particion) ? $particion : ''
                    ];
                }
            }
        }

        if(!empty($documentosNotificarEvento)) {
            $reenvioNotificacion = $classReenvio->notificarEventosDian($classCabecera, $classEstado, 'recepcion', $documentosNotificarEvento, $estadosNotificarEvento, auth()->user(), true);

            foreach($reenvioNotificacion as $reenvio) {
                $documentosProcesados[] = '[' . $reenvio['consecutivo'] . '] ' . $reenvio['mensajeResultado'];
            }
        }

        if(!empty($documentosAgendarAD))
            $this->agendamientosNotificacionEventos(str_replace('NOT', '', $evento), $documentosAgendarAD);

        return response()->json([
            'message'   => 'Reenvio de notificaciones realizados',
            'resultado' => array_merge($documentosProcesados, $documentosNoProcesados)
        ], 200);
    }

    /**
     * Verifica si un documento existe en recepción.
     *
     * @param RepCabeceraDocumentoDaop $classCabecera Instancia de la clase de cabecera en recepción
     * @param integer $cdo_id ID del documento
     * @param string $rfa_prefijo Prefijo dle documento
     * @param string $cdo_consecutivo Consecutivo del documento
     * @param array $documentosNoProcesados Array de documentos no procesados
     * @return void
     */
    private function verificaDocumentoExiste(RepCabeceraDocumentoDaop $classCabecera, int $cdo_id, string $rfa_prefijo, string $cdo_consecutivo, array &$documentosNoProcesados): void {
        $documentoExiste = $classCabecera
            ->newQuery()
            ->select(['cdo_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo'])
            ->where('cdo_id', $cdo_id)
            ->first();

        if(!$documentoExiste) {
            $documentosNoProcesados[] = '[' . $rfa_prefijo . $cdo_consecutivo . '] no existe en openETL';
        } else {
            $documentosNoProcesados[] = '[' . $rfa_prefijo . $cdo_consecutivo . '] no cuenta con el evento solicitado o no tiene los permisos para ejecutar la acción';
        }
    }

    /**
     * Procesa documentos que deben ser agendados para notificación de evento.
     *
     * @param string $evento Nombre del evento para el cual se realizarán los agendamientos
     * @param array $documentosAgendarAD Array conteniendo los documentos a agendar
     * @return void
     */
    private function agendamientosNotificacionEventos(string $evento, array $documentosAgendarAD): void {
        if(!empty($documentosAgendarAD)) {
            $classEventStatusUpdate = new EventStatusUpdate('recepcion');
            $grupos                 = array_chunk($documentosAgendarAD, auth()->user()->getBaseDatos->bdd_cantidad_procesamiento_notificacion);

            foreach ($grupos as $grupo) {
                $nuevoAgendamiento = DoTrait::crearNuevoAgendamiento('RUBLAD' . $evento, auth()->user()->usu_id, auth()->user()->getBaseDatos->bdd_id, count($grupo), null);

                foreach($grupo as $cdo_id) {
                    $classEventStatusUpdate->creaNuevoEstadoDocumento(
                        $cdo_id,
                        '',
                        'UBLAD' . $evento,
                        $nuevoAgendamiento->age_id,
                        auth()->user()->usu_id
                    );
                }
            }
        }
    }

    /**
     * Transmite documentos electrónicos de recepción de DHL Express a openCOMEX.
     *
     * @param Request $request
     * @return Response
     */
    public function cboDhltransmitirOpenComex(Request $request) {
        if(!filled($request->cdo_id))
            return response()->json([
                'message' => 'Petición mal formada',
                'errors'  => ['Falta el parámetro cdo_id o esta vacio']
            ], 409);

        $openComexRptas = [];
        $cdoIds         = explode(',', $request->cdo_id);
        $openComexCxp   = new DhlExpressIntegracionOpencomexCausacionCxpCommand();
        foreach($cdoIds as $cdo_id) {
            $documento = RepCabeceraDocumentoDaop::where('cdo_id', $cdo_id)
                ->with([
                    'getOpencomexCxpExitoso:cdo_id,est_estado,est_resultado'
                ])
                ->first();
            
            if($documento) {
                if(!$documento->getOpencomexCxpExitoso) {
                    TenantTrait::GetVariablesSistemaTenant();
                    $variablesOpencomex = [
                        'hashKey'             => config('variables_sistema_tenant.OPENCOMEX_CBO_HASH_KEY'),
                        'endpointOpencomex'   => config('variables_sistema_tenant.OPENCOMEX_CBO_API'),
                        'proIdentificaciones' => array_map('trim', explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'))),
                        'origen'              => 'controlador'
                    ];

                    $resultadoIntegracionOpenComex = $openComexCxp->openComexIntegracionCausacionCxp($documento, $variablesOpencomex);
                    $openComexRptas[] = $documento->rfa_prefijo . $documento->cdo_consecutivo . ': ' . $resultadoIntegracionOpenComex;
                } else {
                    return response()->json([
                        'message' => 'Error en la Petición',
                        'errors'  => 'El documento electrónico seleccionado tiene estado Transmisión a openComex Exitoso.'
                    ], 404);
                }
            } else {
                return response()->json([
                    'message' => 'Error en la Petición',
                    'errors'  => 'El documento electrónico seleccionado no existe en la data operativa.'
                ], 404);
            }
        }

        return response()->json([
            'message'   => 'Transmisión openComex',
            'resultado' => $openComexRptas
        ], 200);
    }
}
