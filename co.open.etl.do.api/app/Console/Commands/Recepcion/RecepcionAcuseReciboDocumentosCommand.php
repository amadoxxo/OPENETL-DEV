<?php

namespace App\Console\Commands\Recepcion;

use Carbon\Carbon;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use openEtl\Tenant\Traits\TenantTrait;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class RecepcionAcuseReciboDocumentosCommand extends Command {
    use TenantTrait, DoTrait;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-acuse-recibo-documentos
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recepción - Procesa el acuse de recibo de documentos';

    /**
     * Indica si se debe notificar el evento al proveedor
     *
     * @var boolean
     */
    protected $notificarEvento = true;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * 
     * @return mixed
     */
    public function handle() {
        // Se consulta el agendamiento
        $agendamiento = AdoAgendamiento::where('age_id', $this->argument('age_id'))
            ->where('age_proceso', 'RACUSERECIBO')
            ->where('estado', 'ACTIVO')
            ->first();

        if($agendamiento) {
            // Obtiene el usuario relacionado con el agendamiento
            $user = User::find($agendamiento->usu_id);

            $bdUser = $user->getBaseDatos->bdd_nombre;
            if(!empty($user->bdd_id_rg)) {
                $bdUser = $user->getBaseDatosRg->bdd_nombre;
            }

            // Generación del token requerido para poder acceder a los modelos Tenant
            $token = auth()->login($user);

            $documentosAcusar = []; // Array que almacena los ID de los documentos a acusar recibo
            $estadosAcusar    = []; // Array que almacena los ID de los estados de los documentos a acusar recibo
            // Obtiene los estados de los documentos asociados con el agendamiento
            $documentosAgendamiento = RepEstadoDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                ->where('age_id', $this->argument('age_id'))
                ->where('estado', 'ACTIVO')
                ->whereNull('est_resultado')
                ->with([
                    'getRepCabeceraDocumentosDaop:cdo_id,ofe_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo,cdo_origen,cdo_cufe,cdo_nombre_archivos,fecha_creacion',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_archivo_certificado,ofe_password_certificado,ofe_recepcion_eventos_contratados_titulo_valor,bdd_id_rg',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->get()
                ->map( function ($estado) use (&$documentosAcusar, &$estadosAcusar, $bdUser) {
                    // Se debe verificar que le documento tenga estados GETSTATUS y UBLACUSERECIBO exitosos
                    $getStatus = RepEstadoDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'GETSTATUS')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    $ublAcuseRecibo = RepEstadoDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'UBLACUSERECIBO')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    if($getStatus && $ublAcuseRecibo) {
                        if(!empty($estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->bdd_id_rg)) {
                            $bddNombre = $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getBaseDatosRg->bdd_nombre;
                        } else {
                            $bddNombre = $bdUser;
                        }

                        $bddNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);

                        $informacionAdicional = !empty($ublAcuseRecibo->est_informacion_adicional) ? json_decode($ublAcuseRecibo->est_informacion_adicional, true) : [];
                        $xmlUbl = $this->obtenerArchivoDeDisco(
                            'recepcion',
                            $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $estado->getRepCabeceraDocumentosDaop,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );
                        $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

                        $documentosAcusar[$estado->cdo_id] = [
                            'ofe_id'                                         => $estado->getRepCabeceraDocumentosDaop->ofe_id,
                            'cdo_clasificacion'                              => $estado->getRepCabeceraDocumentosDaop->cdo_clasificacion,
                            'rfa_prefijo'                                    => $estado->getRepCabeceraDocumentosDaop->rfa_prefijo,
                            'cdo_consecutivo'                                => $estado->getRepCabeceraDocumentosDaop->cdo_consecutivo,
                            'cdo_origen'                                     => $estado->getRepCabeceraDocumentosDaop->cdo_origen,
                            'cdo_cufe'                                       => $estado->getRepCabeceraDocumentosDaop->cdo_cufe,
                            'ofe_identificacion'                             => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            'ofe_archivo_certificado'                        => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_archivo_certificado,
                            'ofe_password_certificado'                       => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_password_certificado,
                            'ofe_recepcion_eventos_contratados_titulo_valor' => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_eventos_contratados_titulo_valor,
                            'ambiente_destino'          => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id : null,
                            'test_set_id'               => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->sft_testsetid : null,
                            'ambiente_destino_ds'       => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->add_id : null,
                            'test_set_id_ds'            => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->sft_testsetid : null,
                            'xml_ubl'                                        => base64_encode($xmlUbl),
                            'nombre_archivos'                                => (!empty($estado->getRepCabeceraDocumentosDaop->cdo_nombre_archivos)) ? json_decode($estado->getRepCabeceraDocumentosDaop->cdo_nombre_archivos, true) : [],
                            'est_informacion_adicional'                      => $estado->est_informacion_adicional,
                            'bdd_nombre'                                     => $bddNombre
                        ];

                        $estadosAcusar[$estado->cdo_id] = [
                            'est_id'                     => $estado->est_id,
                            'inicio'                     => microtime(true)
                        ];
                    }
                });

            if (!empty($documentosAcusar)) {
                // Marca el agendamiento en procesando
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-RACUSERECIBO'
                ]);

                $classAcusar = new EventStatusUpdate('recepcion');
                $acuses      = $classAcusar->sendEventStatusUpdate($agendamiento, $user, $documentosAcusar, $estadosAcusar, 'acuse', 'validacionDianAcuseRecibo');
                $documentosAgendarNotificacionEventos = [];

                foreach($acuses as $cdo_id => $resultado) {
                    if($resultado['respuestaProcesada']['estado'] == 'EXITOSO') { 
                        $acuseReciboExitoso = RepEstadoDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'UBLADACUSERECIBO')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->first();

                        if(!$acuseReciboExitoso) {
                            $documentosAgendarNotificacionEventos[] = $cdo_id;
                        }

                        // Si el OFE tiene parametrizado el evento RECIBOBIEN automático, se agenda el estado UBLRECIBOBIEN automáticamente
                        $generacionAutomatica = false;
                        $generadoValidacion   = false;
                        if (is_array($documentosAcusar[$cdo_id]['ofe_recepcion_eventos_contratados_titulo_valor']) && !empty($documentosAcusar[$cdo_id]['ofe_recepcion_eventos_contratados_titulo_valor'])) {
                            foreach ($documentosAcusar[$cdo_id]['ofe_recepcion_eventos_contratados_titulo_valor'] as $evento) {
                                if ($evento['evento'] == 'RECIBOBIEN') {
                                    $generacionAutomatica = (array_key_exists('generacion_automatica', $evento) && $evento['generacion_automatica'] == "SI") ? true : false;
                                    break;
                                }
                            }
                        }

                        if(array_key_exists('origen', $resultado['respuestaProcesada']['estadoInformacionAdicional']) && $resultado['respuestaProcesada']['estadoInformacionAdicional']['origen'] == 'validacion'){
                            $generacionAutomatica = true;
                            $generadoValidacion   = true;
                        }

                        if ($generacionAutomatica && $documentosAcusar[$cdo_id]['cdo_clasificacion'] == 'FC' && $documentosAcusar[$cdo_id]['cdo_origen'] != 'NO-ELECTRONICO') {
                            $parametros['ofeId']              = $documentosAcusar[$cdo_id]['ofe_id'];
                            $parametros['cdoIds']             = $cdo_id;
                            // Solo se marca como automatico si el proceso no tiene origen validación
                            // Esto para que tome los datos del usuario que realiza el evento del usuario que realizo la validacion
                            // y no de la parametrizacion inicial
                            if (!$generadoValidacion)
                                $parametros['proceso_automatico'] = true;

                            if(array_key_exists('origen', $resultado['respuestaProcesada']['estadoInformacionAdicional']) && $resultado['respuestaProcesada']['estadoInformacionAdicional']['origen'] == 'validacion')
                                $parametros['origen'] = $resultado['respuestaProcesada']['estadoInformacionAdicional']['origen'];

                            self::peticionMicroservicio('MAIN', 'POST', '/api/recepcion/documentos/agendar-recibo-bien', $parametros, 'form_params');
                        }
                    } elseif(
                        $resultado['respuestaProcesada']['estado'] != 'EXITOSO' &&
                        (
                            (
                                array_key_exists('message', $resultado['respuestaProcesada']) &&
                                (stristr($resultado['respuestaProcesada']['message'], 'Ha ocurrido un error. Por favor inténtelo de nuevo') || stristr($resultado['respuestaProcesada']['message'], 'timeout'))
                            ) ||
                            (
                                array_key_exists('ErrorMessage', $resultado['respuestaProcesada']) &&
                                (stristr($resultado['respuestaProcesada']['ErrorMessage'], 'Ha ocurrido un error. Por favor inténtelo de nuevo') || stristr($resultado['respuestaProcesada']['ErrorMessage'], 'timeout'))
                            )
                        )
                    ) {
                        $acuseFallidos = RepEstadoDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'ACUSERECIBO')
                            ->where('est_resultado', 'FALLIDO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->count();

                        if($acuseFallidos < 3) {
                            // Verifica si el xmlRespuestaDian no está vacio y si es un XML válido
                            $esXml = true;
                            if(
                                $resultado['xmlRespuestaDian'] == '' || $resultado['xmlRespuestaDian'] == null || (
                                    array_key_exists('StatusCode', $resultado['respuestaProcesada']) && $resultado['respuestaProcesada']['StatusCode'] == '500'
                                )
                            ) {
                                $esXml = false;
                            } else {
                                $oXML        = new \SimpleXMLElement($resultado['xmlRespuestaDian']);
                                $vNameSpaces = $oXML->getNamespaces(true);
                                if(!is_array($vNameSpaces) || empty($vNameSpaces)) {
                                    $esXml = false;
                                }
                            }

                            if(!$esXml) {
                                $nuevoAgendamiento = $classAcusar->nuevoAgendamiento('RACUSERECIBO', $agendamiento, $user, 1);
                                $classAcusar->creaNuevoEstadoDocumento(
                                    $cdo_id,
                                    'UBLACUSERECIBO',
                                    'ACUSERECIBO',
                                    $nuevoAgendamiento->age_id,
                                    $user->usu_id,
                                    ['agendamiento' => 'ACUSERECIBOR']
                                );
                            }
                        }
                    }
                }
                
                if ($this->notificarEvento) {
                    $this->agendamientosNotificacionEventos($classAcusar, $user, $agendamiento, $documentosAgendarNotificacionEventos);
                }

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);

                $this->verificarSgteEstado($classAcusar, $user, $agendamiento);
            } else {
                // El agendamiento no encontró coincidencias en el modelo Tenant RepEstadoDocumentoDaop
                // Por lo que se valida el tiempo transcurrido desde su creación y si han pasado
                // más de 5 minutos se procede a finalizar el agendamiento.
                
                // A partir de la fecha y hora actual, se cálcula la fecha y hora restando 5 minutos
                $fecha = Carbon::now()->subSeconds(300);
                $fecha = $fecha->format('Y-m-d H:i:s');

                if($agendamiento->fecha_creacion->lt($fecha)){
                    $agendamiento->update([
                        'age_proceso' => 'FINALIZADO'
                    ]);
                }
            }
        }
    }

    /**
     * Procesa registros que deben ser agendados para UBLADACUSERECIBO.
     *
     * @param EventStatusUpdate $classAcusar Clase que procesa el envío de eventos a la DIAN
     * @param User $user Colección de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Colección con la información del agendamiento en proceso
     * @param array $documentosAgendarNotificacionEventos Array de documentos que se agendarán para UBLADACUSERECIBO
     * @return void
     */
    public function agendamientosNotificacionEventos(EventStatusUpdate $classAcusar, User $user, AdoAgendamiento $agendamiento, array $documentosAgendarNotificacionEventos) {
        if(!empty($documentosAgendarNotificacionEventos)) {
            $grupos = array_chunk($documentosAgendarNotificacionEventos, $user->getBaseDatos->bdd_cantidad_procesamiento_acuse);
            foreach ($grupos as $grupo) {
                $nuevoAgendamiento = $classAcusar->nuevoAgendamiento('RUBLADACUSERECIBO', $agendamiento, $user, count($grupo));
                foreach($grupo as $cdo_id) {
                    $classAcusar->creaNuevoEstadoDocumento(
                        $cdo_id,
                        'ACUSERECIBO',
                        'UBLADACUSERECIBO',
                        $nuevoAgendamiento->age_id,
                        $user->usu_id
                    );
                }
            }
        }
    }

    /**
     * Verifica que cada documento del agendamiento que haya sido procesado de manera correcta, cuente con el siguiente estado que le correspondía.
     *
     * @param EventStatusUpdate $classAcusar Clase principal del procesamiento del comando
     * @param User $user Instancia de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Instancia del agendamiento en proceso
     * @return void
     */
    private function verificarSgteEstado(EventStatusUpdate $classAcusar, User $user, AdoAgendamiento $agendamiento) {
        // Documentos asociados al agendamiento
        $docsAgendamiento = RepEstadoDocumentoDaop::select(['cdo_id'])
            ->where('age_id', $agendamiento->age_id)
            ->where('est_estado', 'ACUSERECIBO')
            ->get()
            ->pluck('cdo_id')
            ->values()
            ->toArray();

        $documentosAgendarNotificacionEventos = [];
        RepCabeceraDocumentoDaop::select(['cdo_id'])
            ->whereIn('cdo_id', $docsAgendamiento)
            ->with([
                'getEstadoAcuseRecibo:est_id,cdo_id,est_resultado',
                'getEstadoUblAdAcuseRecibo:est_id,cdo_id'
            ])
            ->get()
            ->map(function($documento) use (&$documentosAgendarNotificacionEventos) {
                // El estado ACUSERECIBO fue exitoso pero el documento no tiene estado UBLADACUSERECIBO por lo que se debe agendar
                if($documento->getEstadoAcuseRecibo->est_resultado == 'EXITOSO' && !$documento->getEstadoUblAdAcuseRecibo)
                    $documentosAgendarNotificacionEventos[] = $documento->cdo_id;
            });

        if(!empty($documentosAgendarNotificacionEventos))
            $this->agendamientosNotificacionEventos($classAcusar, $user, $agendamiento, $documentosAgendarNotificacionEventos);
    }
}
