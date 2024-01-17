<?php

namespace App\Console\Commands\Recepcion;

use Carbon\Carbon;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class RecepcionRechazoDocumentosCommand extends Command {
    use DoTrait;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-rechazo-documentos
                            {age_id : ID de agendamiento}';

    /**
     * Indica si se debe notificar el evento al proveedor
     *
     * @var boolean
     */
    protected $notificarEvento = true;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recepción - Procesa el rechazo de documentos';

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
            ->where('age_proceso', 'RRECHAZO')
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

            $documentosRechazar = []; // Array que almacena los ID de los documentos que se rechazarán
            $estadosRechazar    = []; // Array que almacena los ID de los estados de los documentos que se rechazarán
            // Obtiene los estados de los documentos asociados con el agendamiento
            $documentosAgendamiento = RepEstadoDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                ->where('age_id', $this->argument('age_id'))
                ->where('estado', 'ACTIVO')
                ->whereNull('est_resultado')
                ->with([
                    'getRepCabeceraDocumentosDaop:cdo_id,ofe_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_nombre_archivos,fecha_creacion',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_archivo_certificado,ofe_password_certificado,bdd_id_rg,ofe_recepcion_fnc_activo',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->doesntHave('getRepCabeceraDocumentosDaop.getAceptado')
                ->doesntHave('getRepCabeceraDocumentosDaop.getAceptadoT')
                ->doesntHave('getRepCabeceraDocumentosDaop.getRechazado')
                ->get()
                ->map( function ($estado) use (&$documentosRechazar, &$estadosRechazar, $bdUser) {
                    // Se debe verificar que el documento tenga estados GETSTATUS y UBLRechazo exitosos
                    $getStatus = RepEstadoDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'GETSTATUS')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    $ublRechazo = RepEstadoDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'UBLRECHAZO')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    if($getStatus && $ublRechazo) {
                        if(!empty($estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->bdd_id_rg)) {
                            $bddNombre = $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getBaseDatosRg->bdd_nombre;
                        } else {
                            $bddNombre = $bdUser;
                        }

                        $bddNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);

                        $informacionAdicional = !empty($ublRechazo->est_informacion_adicional) ? json_decode($ublRechazo->est_informacion_adicional, true) : [];
                        $xmlUbl = $this->obtenerArchivoDeDisco(
                            'recepcion',
                            $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $estado->getRepCabeceraDocumentosDaop,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );
                        $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

                        $documentosRechazar[$estado->cdo_id] = [
                            'ofe_id'                    => $estado->getRepCabeceraDocumentosDaop->ofe_id,
                            'cdo_clasificacion'         => $estado->getRepCabeceraDocumentosDaop->cdo_clasificacion,
                            'rfa_prefijo'               => $estado->getRepCabeceraDocumentosDaop->rfa_prefijo,
                            'cdo_consecutivo'           => $estado->getRepCabeceraDocumentosDaop->cdo_consecutivo,
                            'cdo_cufe'                  => $estado->getRepCabeceraDocumentosDaop->cdo_cufe,
                            'ofe_identificacion'        => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            'ofe_archivo_certificado'   => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_archivo_certificado,
                            'ofe_password_certificado'  => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_password_certificado,
                            'ofe_recepcion_fnc_activo'  => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_fnc_activo,
                            'ambiente_destino'          => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id : null,
                            'test_set_id'               => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->sft_testsetid : null,
                            'ambiente_destino_ds'       => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->add_id : null,
                            'test_set_id_ds'            => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->sft_testsetid : null,
                            'xml_ubl'                   => base64_encode($xmlUbl),
                            'nombre_archivos'           => (!empty($estado->getRepCabeceraDocumentosDaop->cdo_nombre_archivos)) ? json_decode($estado->getRepCabeceraDocumentosDaop->cdo_nombre_archivos, true) : [],
                            'est_informacion_adicional' => $estado->est_informacion_adicional,
                            'bdd_nombre'                => $bddNombre
                        ];

                        $estadosRechazar[$estado->cdo_id] = [
                            'est_id'                     => $estado->est_id,
                            'inicio'                     => microtime(true)
                        ];
                    }
                });

            if (!empty($documentosRechazar)) {
                // Marca el agendamiento en procesando
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-RRECHAZO'
                ]);

                $classRechazar  = new EventStatusUpdate('recepcion');
                $rechazos       = $classRechazar->sendEventStatusUpdate($agendamiento, $user, $documentosRechazar, $estadosRechazar, 'rechazo', 'validacionDianReclamo');
                $documentosAgendarNotificacionEventos = [];

                foreach($rechazos as $cdo_id => $resultado) {
                    if($resultado['respuestaProcesada']['estado'] == 'EXITOSO') {
                        $rechazoExitoso = RepEstadoDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'RUBLADRECHAZO')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->first();

                        if(!$rechazoExitoso) {
                            $documentosAgendarNotificacionEventos[] = $cdo_id;
                        }

                        // Como el rechazo fue exitoso, se debe verificar para el documento si el estado más reciente de VALIDACION es PENDIENTE para proceder a eliminarlo,
                        // siempre que el OFE tenga la columnas ofe_recepcion_fnc_activo en SI
                        if($documentosRechazar[$cdo_id]['ofe_recepcion_fnc_activo'] == 'SI')
                            $this->eliminarEstadoValidacionPendiente($cdo_id);
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
                        $rechazoFallidos = RepEstadoDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'RECHAZO')
                            ->where('est_resultado', 'FALLIDO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->count();

                        if($rechazoFallidos < 3) {
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
                                $nuevoAgendamiento = $classRechazar->nuevoAgendamiento('RRECHAZO', $agendamiento, $user, 1);
                                $classRechazar->creaNuevoEstadoDocumento(
                                    $cdo_id,
                                    'UBLRECHAZO',
                                    'RECHAZO',
                                    $nuevoAgendamiento->age_id,
                                    $user->usu_id,
                                    ['agendamiento' => 'RECHAZOR']
                                );
                            }
                        }
                    }
                }
                
                if ($this->notificarEvento) {
                    $this->agendamientosNotificacionEventos($classRechazar, $user, $agendamiento, $documentosAgendarNotificacionEventos);
                }

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);

                $this->verificarSgteEstado($classRechazar, $user, $agendamiento);
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
     * Procesa registros que deben ser agendados para RUBLADRECHAZO.
     *
     * @param EventStatusUpdate $classRechazar Clase que procesa el envío de eventos a la DIAN
     * @param User $user Colección de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Colección con la información del agendamiento en proceso
     * @param array $documentosAgendarNotificacionEventos Array de documentos que se agendarán para RUBLADRECHAZO
     * @return void
     */
    public function agendamientosNotificacionEventos(EventStatusUpdate $classRechazar, $user, $agendamiento, $documentosAgendarNotificacionEventos) {
        if(!empty($documentosAgendarNotificacionEventos)) {
            $grupos = array_chunk($documentosAgendarNotificacionEventos, $user->getBaseDatos->bdd_cantidad_procesamiento_rechazo);
            foreach ($grupos as $grupo) {
                $nuevoAgendamiento = $classRechazar->nuevoAgendamiento('RUBLADRECHAZO', $agendamiento, $user, count($grupo));
                foreach($grupo as $cdo_id) {
                    $classRechazar->creaNuevoEstadoDocumento(
                        $cdo_id,
                        'RECHAZO',
                        'UBLADRECHAZO',
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
     * @param EventStatusUpdate $classRechazar Clase principal del procesamiento del comando
     * @param User $user Instancia de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Instancia del agendamiento en proceso
     * @return void
     */
    private function verificarSgteEstado(EventStatusUpdate $classRechazar, User $user, AdoAgendamiento $agendamiento) {
        // Documentos asociados al agendamiento
        $docsAgendamiento = RepEstadoDocumentoDaop::select(['cdo_id'])
            ->where('age_id', $agendamiento->age_id)
            ->where('est_estado', 'RECHAZO')
            ->get()
            ->pluck('cdo_id')
            ->values()
            ->toArray();

        $documentosAgendarNotificacionEventos = [];
        RepCabeceraDocumentoDaop::select(['cdo_id'])
            ->whereIn('cdo_id', $docsAgendamiento)
            ->with([
                'getEstadoRechazo:est_id,cdo_id,est_resultado',
                'getEstadoUblAdRechazo:est_id,cdo_id'
            ])
            ->get()
            ->map(function($documento) use (&$documentosAgendarNotificacionEventos) {
                // El estado RECIBOBIEN fue exitoso pero el documento no tiene estado UBLADRECIBOBIEN por lo que se debe agendar
                if($documento->getEstadoRechazo->est_resultado == 'EXITOSO' && !$documento->getEstadoUblAdRechazo)
                    $documentosAgendarNotificacionEventos[] = $documento->cdo_id;
            });

        if(!empty($documentosAgendarNotificacionEventos))
            $this->agendamientosNotificacionEventos($classRechazar, $user, $agendamiento, $documentosAgendarNotificacionEventos);
    }

    /**
     * Para los documentos cuyo rechazo fue exitoso, se debe verificar si el estado más reciente de VALIDACION es PENDIENTE para proceder a eliminarlo
     *
     * @param int $cdo_id ID del documento
     * @return void
     */
    private function eliminarEstadoValidacionPendiente(int $cdo_id): void {
        $validacionPendiente = RepCabeceraDocumentoDaop::select(['cdo_id'])
            ->where('cdo_id', $cdo_id)
            ->with([
                'getUltimoEstadoValidacion:est_id,cdo_id,est_estado,est_resultado'
            ])
            ->first();

        if($validacionPendiente && !empty($validacionPendiente->getUltimoEstadoValidacion) && isset($validacionPendiente->getUltimoEstadoValidacion->est_resultado) && $validacionPendiente->getUltimoEstadoValidacion->est_resultado == 'PENDIENTE')
            $validacionPendiente->getUltimoEstadoValidacion->delete();
    }
}
