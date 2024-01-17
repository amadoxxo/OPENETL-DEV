<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\TransmitirDocumentosDian\TransmitirDocumentosDian;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class TransmitirDocumentosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transmitir-documentos
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transmisión de documentos electrónicos a la DIAN';

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
        $agendamiento = AdoAgendamiento::find($this->argument('age_id'));

        if($agendamiento) {
            // Obtiene el usuario relacionado con el agendamiento
            $user = User::find($agendamiento->usu_id);
                
            // Generación del token requerido para poder acceder a los modelos Tenant
            $token = auth()->login($user);

            $documentosTransmitir = []; // Array que almacena los ID de los documentos a transmitir
            $documentosConsultar  = []; // Array que almacena los ID de los documentos para los cuales se consultará su estado en la DIAN
            $estadosDocumentos    = []; // Array que almacena los ID de los estados de los documentos a transmitir
            // Obtiene los documentos relacionados con el agendamiento en proceso
            $documentosAgendamiento = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                ->where('age_id', $this->argument('age_id'))
                ->where('est_estado', 'DO')
                ->whereNull('est_resultado')
                ->whereNull('est_ejecucion')
                ->with([
                    'getCabeceraDocumentosDaop:cdo_id,ofe_id,adq_id',
                    'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_reenvio_notificacion_contingencia',
                    'getCabeceraDocumentosDaop.getConfiguracionAdquirente:adq_id,adq_reenvio_notificacion_contingencia'
                ])
                ->get()
                ->map( function ($estado) use (&$documentosTransmitir, &$documentosConsultar, &$estadosDocumentos) {
                    $estInformacionAdicional = (isset($estado->est_informacion_adicional) && $estado->est_informacion_adicional != '') ? json_decode($estado->est_informacion_adicional, true) : [];
                    if(array_key_exists('metodo', $estInformacionAdicional) && $estInformacionAdicional['metodo'] == 'GetStatus')
                        $documentosConsultar[]  = $estado->cdo_id;
                    else
                        $documentosTransmitir[] = $estado->cdo_id;

                    $estadosDocumentos[$estado->cdo_id] = [
                        'est_id'                     => $estado->est_id,
                        'inicio'                     => microtime(true),
                        'estadoInformacionAdicional' => $estado->est_informacion_adicional,
                        'ofe_contingencia'           => $estado->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_reenvio_notificacion_contingencia,
                        'adq_contingencia'           => $estado->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_reenvio_notificacion_contingencia,
                    ];
                });

            // Marca el agendamiento en procesando
            $agendamiento->update([
                'age_proceso' => 'PROCESANDO-DO'
            ]);

            if (!empty($documentosTransmitir)) {
                $classTransmitir = new TransmitirDocumentosDian();
                $transmitir      = $classTransmitir->TransmitirDocumentosDian($documentosTransmitir, $estadosDocumentos, $user);

                $this->finalizarProcesamiento('transmitir', $classTransmitir, $transmitir, $estadosDocumentos, $user, $agendamiento);
            }
            
            if (!empty($documentosConsultar)) {
                $classConsultar = new TransmitirDocumentosDian();
                $consultar      = $classConsultar->ConsultarDocumentosDian($documentosConsultar, $estadosDocumentos, $user);

                $this->finalizarProcesamiento('consultar', $classConsultar, $consultar, $estadosDocumentos, $user, $agendamiento);
            }

            if(empty($documentosConsultar) && empty($documentosTransmitir)) {
                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);
            }
        }
    }

    /**
     * Finaliza el procesamiento de un bloque de documentos de un agendamiento.
     *
     * @param string $tipoProcesamiento Indica si se trata de una transmisión o consulta de documentos
     * @param TransmitirDocumentosDian $clasePrincipal Clase principal de procesamiento del comando
     * @param array $consultar Array que contiene el resultado de procesamiento de los documentos
     * @param array $estadosDocumentos Array que contiene la información de los estados de los documentos procesados
     * @param User $user Modelo del usuario autenticado
     * @param AdoAgendamiento $agendamiento Modelo del agendamiento en proceamiento
     * @return void
     */
    private function finalizarProcesamiento(string $tipoProcesamiento, TransmitirDocumentosDian $clasePrincipal, array $consultar, array $estadosDocumentos, User $user, AdoAgendamiento $agendamiento) {
        $documentosAgendarAD                = [];
        $documentosNotificadosContingencia = [];

        // Actualiza la información de los registro procesados exitosos y fallidos
        foreach($consultar as $cdo_id => $resultado) {
            if($resultado['respuestaProcesada']['estado'] === 'EXITOSO') {
                // Si en el estado de DO en información adicional EXISTE el campo "contingencia"
                // y el valor de este es true, se debe validar si a nivel de ofe o de adq existe
                // la marca para reenvio de notificación en contingencia
                $estadoInformacionAdicional = ($estadosDocumentos[$cdo_id]['estadoInformacionAdicional'] != '') ? json_decode($estadosDocumentos[$cdo_id]['estadoInformacionAdicional'], true) : [];
                if(
                    array_key_exists('contingencia', $estadoInformacionAdicional) &&
                    $estadoInformacionAdicional['contingencia'] == true
                ) {
                    if($estadosDocumentos[$cdo_id]['ofe_contingencia'] == 'SI' || $estadosDocumentos[$cdo_id]['adq_contingencia'] == 'SI') {
                        $documentosNotificadosContingencia[] = $cdo_id;
                    }
                } else {
                    // Si es un Documento Soporte no se agenda para el estado UBLATTACHEDDOCUMENT
                    $documentoDS = EtlCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion'])
                        ->where('cdo_id', $cdo_id)
                        ->whereIn('cdo_clasificacion', ['DS','DS_NC'])
                        ->first();

                    if (!$documentoDS) {
                        // Si el documento fue exitoso y NO ha pasado por el proceso de UBLATTACHEDDOCUMENT con estado EXITOSO, se agenda el documento para dicho estado
                        $documentoConAD = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_estado', 'est_resultado'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'UBLATTACHEDDOCUMENT')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->first();

                        if(!$documentoConAD) $documentosAgendarAD[] = $cdo_id;
                    }
                }
            }

            if($tipoProcesamiento == 'transmitir') {
                // Si se presentó un error en la trasmisión y la respuesta no es un error de la DIAN
                // Se verifica si el documento tiene menos de tres estados DO fallidos y de ser el caso
                // Realiza un nuevo agendamiento con el proceso DOR y marca el estado con agendamiento DOR

                // Verifica la cantidad de estados DO fallidos del documento solo si el resultado fue fallido
                if($resultado['respuestaProcesada']['estado'] !== 'EXITOSO') {
                    $doFallidos = EtlEstadosDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', 'DO')
                        ->where('est_resultado', 'FALLIDO')
                        ->count();

                    if($doFallidos < 3) {
                        // Verifica si el xmlRespuestaDian no está vacio y si NO es un XML
                        $esXml = true;
                        if($resultado['xmlRespuestaDian'] == '' || $resultado['xmlRespuestaDian'] == null || $resultado['respuestaProcesada']['StatusCode'] == '500') {
                            $esXml = false;
                        } else {
                            $oXML        = new \SimpleXMLElement($resultado['xmlRespuestaDian']);
                            $vNameSpaces = $oXML->getNamespaces(true);
                            if(!is_array($vNameSpaces) || empty($vNameSpaces)) {
                                $esXml = false;
                            }
                        }
                        // Si no es un XML válido se agenda DOR
                        if(!$esXml) {
                            $agendamientoDOR = DoTrait::crearNuevoAgendamiento('DO', $user->usu_id, $user->getBaseDatos->bdd_id, 1, $agendamiento->age_prioridad);
                            $clasePrincipal->creaNuevoEstadoDocumento(
                                $cdo_id,
                                'DO',
                                $agendamientoDOR->age_id,
                                $user->usu_id,
                                ['agendamiento' => 'DOR']
                            );
                        }
                    }
                }
            }
        }

        if(!empty($documentosAgendarAD)) {
            $grupos = array_chunk($documentosAgendarAD, $user->getBaseDatos->bdd_cantidad_procesamiento_ubl);
            foreach ($grupos as $grupo) {
                $nuevoAgendamiento = DoTrait::crearNuevoAgendamiento('EUBLATTACHEDDOCUMENT', $user->usu_id, $user->getBaseDatos->bdd_id, count($grupo), $agendamiento->age_prioridad);
                foreach($grupo as $cdo_id) {
                    $clasePrincipal->creaNuevoEstadoDocumento(
                        $cdo_id,
                        'UBLATTACHEDDOCUMENT',
                        $nuevoAgendamiento->age_id,
                        $user->usu_id,
                        null
                    );
                }
            }
        }

        if(!empty($documentosNotificadosContingencia)) {
            $grupos = array_chunk($documentosNotificadosContingencia, $user->getBaseDatos->bdd_cantidad_procesamiento_ubl);
            foreach ($grupos as $grupo) {
                $nuevoAgendamiento = DoTrait::crearNuevoAgendamiento('EUBLATTACHEDDOCUMENT', $user->usu_id, $user->getBaseDatos->bdd_id, count($grupo), $agendamiento->age_prioridad);
                foreach($grupo as $cdo_id) {
                    $clasePrincipal->creaNuevoEstadoDocumento(
                        $cdo_id,
                        'UBLATTACHEDDOCUMENT',
                        $nuevoAgendamiento->age_id,
                        $user->usu_id,
                        ['contingencia' => false]
                    );
                }
            }
        }

        $agendamiento->update([
            'age_proceso' => 'FINALIZADO'
        ]);

        // Si el procesamiento es 'transmitir' se debe verificar que todos los documentos relacionados con el agendamiento, hayan sido agendados para el siguiente estado
        if($tipoProcesamiento == 'transmitir')
            $this->verificarSgteEstado($clasePrincipal, $user, $agendamiento);
    }

    /**
     * Verifica que cada documento del agendamiento que haya sido procesado de manera correcta, cuente con el siguiente estado que le correspondía.
     *
     * @param TransmitirDocumentosDian $clasePrincipal Clase principal de procesamiento del comando
     * @param User $user Instancia de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Instancia del agendamiento en proceso
     * @return void
     */
    private function verificarSgteEstado(TransmitirDocumentosDian $clasePrincipal, User $user, AdoAgendamiento $agendamiento) {
        // Documentos asociados al agendamiento
        $docsAgendamiento = EtlEstadosDocumentoDaop::select(['cdo_id'])
            ->where('age_id', $agendamiento->age_id)
            ->where('est_estado', 'DO')
            ->get()
            ->pluck('cdo_id')
            ->values()
            ->toArray();

        $documentosAgendarAD = [];
        EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'adq_id', 'cdo_clasificacion'])
            ->whereIn('cdo_id', $docsAgendamiento)
            ->with([
                'getEstadoDo:est_id,cdo_id,est_resultado,est_informacion_adicional',
                'getEstadoAd:est_id,cdo_id',
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_reenvio_notificacion_contingencia',
                'getConfiguracionAdquirente:adq_id,adq_reenvio_notificacion_contingencia'
            ])
            ->get()
            ->map(function($documento) use (&$documentosAgendarAD) {
                // El estado DO fue exitoso pero el documento no tiene estado UBLATTACHEDDOCUMENT, entonces se debe agendar al siguiente estado
                // solo se agenda para los documentos diferentes a Documento Soporte
                if($documento->getEstadoDo->est_resultado == 'EXITOSO' && !$documento->getEstadoAd && $documento->cdo_clasificacion != 'DS' && $documento->cdo_clasificacion != 'DS_NC') {
                    $doInformacionAdicional = $documento->getEstadoDo->est_informacion_adicional != '' ? json_decode($documento->getEstadoDo->est_informacion_adicional, true) : [];
                    if(
                        array_key_exists('contingencia', $doInformacionAdicional) &&
                        $doInformacionAdicional['contingencia'] == true &&
                        ($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_reenvio_notificacion_contingencia == 'SI' || $documento->getConfiguracionAdquirente->adq_reenvio_notificacion_contingencia == 'SI')
                    )
                        $contingencia = ['contingencia' => false];
                    else
                        $contingencia = null;

                    $documentosAgendarAD[$documento->cdo_id] = $contingencia;
                }
            });

        if(!empty($documentosAgendarAD))
            $this->agendamientosAD($clasePrincipal, $user, $agendamiento, $documentosAgendarAD);
    }

    /**
     * Crea los agendamientos correspondientes para EUBLATTACHEDDOCUMENT.
     *
     * @param TransmitirDocumentosDian $clasePrincipal Clase principal de procesamiento del comando
     * @param User $user Modelo del usuario autenticado
     * @param AdoAgendamiento $agendamiento Modelo del agendamiento en proceamiento
     * @param array $documentosAgendar Array con los ids de los documentos a agendar
     * @return void
     */
    private function agendamientosAD(TransmitirDocumentosDian $clasePrincipal, User $user, AdoAgendamiento $agendamiento, array $documentosAgendar) {
        $grupos = array_chunk($documentosAgendar, $user->getBaseDatos->bdd_cantidad_procesamiento_ubl);
        foreach ($grupos as $grupo) {
            $nuevoAgendamiento = DoTrait::crearNuevoAgendamiento('EUBLATTACHEDDOCUMENT', $user->usu_id, $user->getBaseDatos->bdd_id, count($grupo), $agendamiento->age_prioridad);
            foreach($grupo as $cdo_id => $contingencia) {
                $clasePrincipal->creaNuevoEstadoDocumento(
                    $cdo_id,
                    'UBLATTACHEDDOCUMENT',
                    $nuevoAgendamiento->age_id,
                    $user->usu_id,
                    $contingencia
                );
            }
        }
    }
}
