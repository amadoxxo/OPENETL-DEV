<?php

namespace App\Console\Commands\Emision;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;

class EmisionAceptacionTacitaDocumentosCommand extends Command {
    use DoTrait, FechaVigenciaValidations;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emision-aceptacion-tacita-documentos
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emisión - Procesa la aceptación tácita de documentos para el envío del evento a la DIAN';

    /**
     * @var array cdo_clasificacion de los documentos que aplican.
     */
    protected $arrCdoClasificacion = ['FC','NC','ND'];

    /**
     * Indica si se debe notificar el evento de aceptación tácita.
     *
     * @var boolean
     */
    protected $notificarAceptacionTacita = false;

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
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        // Se consulta el agendamiento
        $agendamiento = AdoAgendamiento::where('age_id', $this->argument('age_id'))
            ->where('age_proceso', 'EACEPTACIONT')
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

            $estadosAceptacionTacita    = []; // Array que almacena los ID de los estados de los documentos a procesar
            $documentosAceptacionTacita = []; // Array que almacena los ID de los documentos que se deben procesar

            // Obtiene los documentos relacionados con el agendamiento en proceso
            EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_motivo_rechazo'])
                ->where('age_id', $this->argument('age_id'))
                ->where('est_estado', 'ACEPTACIONT')
                ->whereNull('est_resultado')
                ->whereNull('est_ejecucion')
                ->whereHas('getCabeceraDocumentosDaop', function ($query) {
                    $query->whereIn('cdo_clasificacion', $this->arrCdoClasificacion);
                })
                ->with([
                    'getCabeceraDocumentosDaop:cdo_id,ofe_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_nombre_archivos,fecha_creacion',
                    'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_archivo_certificado,ofe_password_certificado,bdd_id_rg',
                    'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid',
                    'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->doesntHave('getCabeceraDocumentosDaop.getAceptado')
                ->doesntHave('getCabeceraDocumentosDaop.getAceptadoT')
                ->doesntHave('getCabeceraDocumentosDaop.getRechazado')
                ->get()
                ->map( function ($estado) use (&$documentosAceptacionTacita, &$estadosAceptacionTacita, $bdUser) {
                    // Se debe verificar que el documento tenga estados DO y UBLACEPTACIONT exitosos
                    $getDo = EtlEstadosDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'DO')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    $ublAceptacionT = EtlEstadosDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'UBLACEPTACIONT')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    if($getDo && $ublAceptacionT) {
                        if(!empty($estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->bdd_id_rg)) {
                            $bddNombre = $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getBaseDatosRg->bdd_nombre;
                        } else {
                            $bddNombre = $bdUser;
                        }

                        $bddNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);

                        $informacionAdicional = !empty($ublAceptacionT->est_informacion_adicional) ? json_decode($ublAceptacionT->est_informacion_adicional, true) : [];
                        $xmlUbl = $this->obtenerArchivoDeDisco(
                            'emision',
                            $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $estado->getCabeceraDocumentosDaop,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );
                        $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

                        $documentosAceptacionTacita[$estado->cdo_id] = [
                            'ofe_id'                    => $estado->getCabeceraDocumentosDaop->ofe_id,
                            'cdo_clasificacion'         => $estado->getCabeceraDocumentosDaop->cdo_clasificacion,
                            'rfa_prefijo'               => $estado->getCabeceraDocumentosDaop->rfa_prefijo,
                            'cdo_consecutivo'           => $estado->getCabeceraDocumentosDaop->cdo_consecutivo,
                            'cdo_cufe'                  => $estado->getCabeceraDocumentosDaop->cdo_cufe,
                            'ofe_identificacion'        => $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            'ofe_archivo_certificado'   => $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_archivo_certificado,
                            'ofe_password_certificado'  => $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_password_certificado,
                            'ambiente_destino'          => $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                                $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id : null,
                            'test_set_id'               => $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                                $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->sft_testsetid : null,
                            'ambiente_destino_ds'       => $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                                $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->add_id : null,
                            'test_set_id_ds'            => $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                                $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->sft_testsetid : null,
                            'xml_ubl'                   => base64_encode($xmlUbl),
                            'nombre_archivos'           => (!empty($estado->getCabeceraDocumentosDaop->cdo_nombre_archivos)) ? json_decode($estado->getCabeceraDocumentosDaop->cdo_nombre_archivos, true) : [],
                            'est_informacion_adicional' => $estado->est_informacion_adicional,
                            'bdd_nombre'                => $bddNombre
                        ];

                        $estadosAceptacionTacita[$estado->cdo_id] = [
                            'est_id' => $estado->est_id,
                            'inicio' => microtime(true)
                        ];
                    }
                });

            if (!empty($documentosAceptacionTacita)) {
                // Marca el agendamiento en procesando
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-EACEPTACIONT'
                ]);

                $classAceptarT  = new EventStatusUpdate('emision');
                $aceptacionesT  = $classAceptarT->sendEventStatusUpdate($agendamiento, $user, $documentosAceptacionTacita, $estadosAceptacionTacita, 'aceptaciont', 'validacionDianAceptacionT');

                $documentosAgendarNotificacionEventos = [];
                foreach($aceptacionesT as $cdo_id => $resultado) {
                    if($resultado['respuestaProcesada']['estado'] == 'EXITOSO') {
                        $NotAceptacionTExitoso = EtlEstadosDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'UBLADACEPTACIONT')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->first();

                        if(!$NotAceptacionTExitoso) {
                            $documentosAgendarNotificacionEventos[] = $cdo_id;
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
                        $aceptacionTFallidos = EtlEstadosDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'ACEPTACIONT')
                            ->where('est_resultado', 'FALLIDO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->where('est_mensaje_resultado', 'not like', '%timeout%')
                            ->count();

                        if($aceptacionTFallidos < 3) {
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
                                $nuevoAgendamiento = $classAceptarT->nuevoAgendamiento('EACEPTACIONT', $agendamiento, $user, 1);
                                $classAceptarT->creaNuevoEstadoDocumento(
                                    $cdo_id,
                                    'UBLACEPTACIONT',
                                    'ACEPTACIONT',
                                    $nuevoAgendamiento->age_id,
                                    $user->usu_id,
                                    ['agendamiento' => 'ACEPTACIONTR']
                                );
                            }
                        }
                    }
                }

                if($this->notificarAceptacionTacita)
                    $this->agendamientosNotificacionEventos($classAceptarT, $user, $agendamiento, $documentosAgendarNotificacionEventos);

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);

                if($this->notificarAceptacionTacita)
                    $this->verificarSgteEstado($classAceptarT, $user, $agendamiento);
            } else {
                // El agendamiento no encontró coincidencias en el modelo Tenant EtlEstadosDocumentoDaop
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
     * Procesa registros que deben ser agendados para NOTACEPTACIONT.
     *
     * @param EventStatusUpdate $classAceptarT Clase que procesa el envío de eventos a la DIAN
     * @param User $user Colección de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Colección con la información del agendamiento en proceso
     * @param array $documentosAgendarNotificacionEventos Array de documentos que se agendarán para NOTACEPTACION
     * @return void
     */
    public function agendamientosNotificacionEventos(EventStatusUpdate $classAceptarT, User $user, AdoAgendamiento $agendamiento, array $documentosAgendarNotificacionEventos) {
        if(!empty($documentosAgendarNotificacionEventos)) {
            $grupos = array_chunk($documentosAgendarNotificacionEventos, $user->getBaseDatos->bdd_cantidad_procesamiento_aceptacion);
            foreach ($grupos as $grupo) {
                $nuevoAgendamiento = $classAceptarT->nuevoAgendamiento('EUBLADACEPTACIONT', $agendamiento, $user, count($grupo));
                foreach($grupo as $cdo_id) {
                    $classAceptarT->creaNuevoEstadoDocumento(
                        $cdo_id,
                        'ACEPTACIONT',
                        'UBLADACEPTACIONT',
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
     * @param EventStatusUpdate $classAceptarT Clase principal del procesamiento del comando
     * @param User $user Instancia de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Instancia del agendamiento en proceso
     * @return void
     */
    private function verificarSgteEstado(EventStatusUpdate $classAceptarT, User $user, AdoAgendamiento $agendamiento) {
        // Documentos asociados al agendamiento
        $docsAgendamiento = EtlEstadosDocumentoDaop::select(['cdo_id'])
            ->where('age_id', $agendamiento->age_id)
            ->where('est_estado', 'ACEPTACIONT')
            ->get()
            ->pluck('cdo_id')
            ->values()
            ->toArray();

        $documentosAgendarNotificacionEventos = [];
        EtlCabeceraDocumentoDaop::select(['cdo_id'])
            ->whereIn('cdo_id', $docsAgendamiento)
            ->with([
                'getEstadoAceptacionT:est_id,cdo_id,est_resultado',
                'getEstadoUblAdAceptacionT:est_id,cdo_id'
            ])
            ->get()
            ->map(function($documento) use (&$documentosAgendarNotificacionEventos) {
                // El estado ACEPTACIONT fue exitoso pero el documento no tiene estado UBLADACEPTACIONT por lo que se debe agendar
                if($documento->getEstadoAceptacionT->est_resultado == 'EXITOSO' && !$documento->getEstadoUblAdAceptacionT)
                    $documentosAgendarNotificacionEventos[] = $documento->cdo_id;
            });

        if(!empty($documentosAgendarNotificacionEventos))
            $this->agendamientosNotificacionEventos($classAceptarT, $user, $agendamiento, $documentosAgendarNotificacionEventos);
    }
}
