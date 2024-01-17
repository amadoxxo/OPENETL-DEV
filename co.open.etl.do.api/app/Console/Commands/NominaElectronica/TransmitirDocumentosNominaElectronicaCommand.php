<?php

namespace App\Console\Commands\NominaElectronica;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\NominaElectronica\TransmitirDocumentosNominaElectronicaDian;
use App\Http\Modulos\NominaElectronica\DsnEstadosDocumentosDaop\DsnEstadoDocumentoDaop;

class TransmitirDocumentosNominaElectronicaCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transmitir-documentos-nomina-electronica
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transmisión de documentos de nómina electrónica a la DIAN';

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
        try {
            // Se consulta el agendamiento
            $agendamiento = AdoAgendamiento::find($this->argument('age_id'));

            if($agendamiento) {
                // Obtiene el usuario relacionado con el agendamiento
                $user = User::find($agendamiento->usu_id);
                    
                // Generación del token requerido para poder acceder a los modelos Tenant
                $token = auth()->login($user);

                $estadosDocumentosNomina    = [];
                $documentosNominaConsultar  = [];
                $documentosNominaTransmitir = [];
                // Obtiene los documentos relacionados con el agendamiento en proceso
                DsnEstadoDocumentoDaop::select(['est_id', 'cdn_id', 'est_informacion_adicional'])
                    ->where('age_id', $this->argument('age_id'))
                    ->where('est_estado', 'DO')
                    ->whereNull('est_resultado')
                    ->whereNull('est_ejecucion')
                    ->get()
                    ->map( function ($estado) use (&$documentosNominaTransmitir, &$documentosNominaConsultar, &$estadosDocumentosNomina) {
                        $estInformacionAdicional = (isset($estado->est_informacion_adicional) && $estado->est_informacion_adicional != '') ? json_decode($estado->est_informacion_adicional, true) : [];
                        if(array_key_exists('metodo', $estInformacionAdicional) && $estInformacionAdicional['metodo'] == 'GetStatus')
                            $documentosNominaConsultar[]  = $estado->cdn_id;
                        else
                            $documentosNominaTransmitir[] = $estado->cdn_id;

                        $estadosDocumentosNomina[$estado->cdn_id] = [
                            'est_id'                     => $estado->est_id,
                            'inicio'                     => microtime(true),
                            'estadoInformacionAdicional' => $estado->est_informacion_adicional
                        ];
                    });

                // Marca el agendamiento en procesando
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-DONOMINA'
                ]);

                if (!empty($documentosNominaTransmitir)) {
                    $classTransmitir = new TransmitirDocumentosNominaElectronicaDian();
                    $transmitir      = $classTransmitir->transmitirDocumentosNominaElectronicaDian($documentosNominaTransmitir, $estadosDocumentosNomina, $user);

                    $this->finalizarProcesamiento('transmitir', $classTransmitir, $transmitir, $estadosDocumentosNomina, $user, $agendamiento);
                }

                if (!empty($documentosNominaConsultar)) {
                    $classConsultar = new TransmitirDocumentosNominaElectronicaDian();
                    $consultar      = $classConsultar->ConsultarDocumentosNominaElectronicaDian($documentosNominaConsultar, $estadosDocumentosNomina, $user);

                    $this->finalizarProcesamiento('consultar', $classConsultar, $consultar, $estadosDocumentosNomina, $user, $agendamiento);
                }

                if(empty($documentosNominaConsultar) && empty($documentosNominaTransmitir)) {
                    $agendamiento->update([
                        'age_proceso' => 'FINALIZADO'
                    ]);
                }
            }
        } catch (\Exception $e) {
            // config(['logging.channels.emision_log_do.path' => storage_path('log_do/emision_log_do'.date('Ymd').'.log')]);
            // Log::channel('emision_log_do')->info('==================Inicio Ejecucion Comando======================');
        }
    }

    /**
     * Finaliza el procesamiento de un bloque de documentos de un agendamiento.
     *
     * @param string $tipoProcesamiento Indica si se trata de una transmisión o consulta de documentos
     * @param TransmitirDocumentosNominaElectronicaDian $clasePrincipal Clase principal encargada del procesamiento de la transmisión a al DIAN
     * @param array $consultar Array que contiene el resultado de procesamiento de los documentos
     * @param array $estadosDocumentosNomina Array que contiene la información de los estados de los documentos procesados
     * @param User $user Modelo del usuario autenticado
     * @param AdoAgendamiento $agendamiento Modelo del agendamiento en proceamiento
     * @return void
     */
    private function finalizarProcesamiento($tipoProcesamiento, $clasePrincipal, $consultar, $estadosDocumentosNomina, $user, $agendamiento) {
        // Actualiza la información de los registro procesados exitosos y fallidos
        foreach($consultar as $cdn_id => $resultado) {
            if($tipoProcesamiento == 'transmitir') {
                // Si se presentó un error en la trasmisión y la respuesta no es un error de la DIAN
                // Se verifica si el documento tiene menos de tres estados DO fallidos y de ser el caso
                // Realiza un nuevo agendamiento con el proceso DOR y marca el estado con agendamiento DOR

                // Verifica la cantidad de estados DO fallidos del documento solo si el resultado fue fallido
                if($resultado['respuestaProcesada']['estado'] !== 'EXITOSO') {
                    $doFallidos = DsnEstadoDocumentoDaop::select(['est_id'])
                        ->where('cdn_id', $cdn_id)
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
                            $clasePrincipal->creaNuevoEstadoDocumentoNomina(
                                $cdn_id,
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

        $agendamiento->update([
            'age_proceso' => 'FINALIZADO'
        ]);
    }
}
