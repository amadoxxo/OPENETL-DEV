<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Http\Models\User;
use Illuminate\Console\Command;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\NotificarDocumentos\NotificarDocumentos;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;

class NotificarDocumentosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notificar-documentos
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notificación de documentos electrónicos';

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

            $documentosNotificar = []; // Array que almacena los ID de los documentos a notificar
            $estadosNotificar    = []; // Array que almacena los ID de los estados de los documentos a notificar
            // Obtiene los documentos relacionados con el agendamiento en proceso
            $documentosAgendamiento = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                ->where('age_id', $this->argument('age_id'))
                ->where('est_estado', 'NOTIFICACION')
                ->whereNull('est_resultado')
                ->whereNull('est_ejecucion')
                ->with([
                    'getCabeceraDocumentosDaop:cdo_id,cdo_contingencia',
                ])
                ->get()
                ->map( function ($estado) use (&$documentosNotificar, &$estadosNotificar) {
                    $documentosNotificar[]             = $estado->cdo_id;
                    $estadosNotificar[$estado->cdo_id] = [
                        'est_id'                     => $estado->est_id,
                        'inicio'                     => microtime(true),
                        'estadoInformacionAdicional' => ($estado->est_informacion_adicional != '') ? json_decode($estado->est_informacion_adicional, true) : []
                    ];
                });

            if (!empty($documentosNotificar)) {
                // Marca el agendamiento en procesando
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-NOTIFICACION'
                ]);

                $classNotificar = new NotificarDocumentos();
                $notificar      = $classNotificar->NotificarDocumentos($documentosNotificar, $user, false, $estadosNotificar);

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);

                // Dentro del proceso de notificación, cuando un documento es notificado en contingencia, se debe crear el estado contingencia
                // por lo que se deben verificar todos los documentos procesados en el agendamiento y validar para los documentos notificados en contingencia
                // si se les creó el siguiente estado, que como se menciona, debe ser CONTINGENCIA
                $this->verificarSgteEstado($classNotificar, $user, $agendamiento);
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
     * Verifica que cada documento del agendamiento que haya sido procesado de manera correcta, cuente con el siguiente estado que le correspondía.
     * 
     * Esto aplica para los documentos notificados en contingencia, para los cuales se debió crear el estado CONTINGENCIA
     *
     * @param NotificarDocumentos $clasePrincipal Clase principal de procesamiento del comando
     * @param User $user Instancia de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Instancia del agendamiento en proceso
     * @return void
     */
    private function verificarSgteEstado(NotificarDocumentos $clasePrincipal, User $user, AdoAgendamiento $agendamiento) {
        // Documentos asociados al agendamiento
        $docsAgendamiento = EtlEstadosDocumentoDaop::select(['cdo_id'])
            ->where('age_id', $agendamiento->age_id)
            ->where('est_estado', 'NOTIFICACION')
            ->get()
            ->pluck('cdo_id')
            ->values()
            ->toArray();

        $documentosAgendarContingencia = [];
        EtlCabeceraDocumentoDaop::select(['cdo_id', 'cdo_contingencia'])
            ->whereIn('cdo_id', $docsAgendamiento)
            ->with([
                'getEstadoNotificacion:est_id,cdo_id,est_resultado',
                'getEstadoContingencia:est_id,cdo_id',
                'getDoDocumento:est_id,cdo_id'
            ])
            ->get()
            ->map(function($documento) use (&$documentosAgendarContingencia) {
                // El estado NOTIFICACION fue exitoso pero el documento fue notificado en contingencia y no tiene estado CONTINGENCIA, entonces se debe agendar
                if(
                    $documento->getEstadoNotificacion->est_resultado == 'EXITOSO' &&
                    $documento->cdo_contingencia == 'SI' &&
                    !$documento->getDoDocumento &&
                    !$documento->getEstadoContingencia
                ) {
                    $documentosAgendarContingencia[] = $documento->cdo_id;
                }
            });

        if(!empty($documentosAgendarContingencia))
            $this->crearEstadosContingencia($clasePrincipal, $user, $documentosAgendarContingencia);
    }

    /**
     * Crea los estados de contingencia para los documentos en el array recibido por parámetro.
     *
     * @param NotificarDocumentos $clasePrincipal Clase principal de procesamiento del comando
     * @param User $user Instancia de usuario relacionado con el agendamiento
     * @param array $documentosAgendarContingencia Array que contiene los cdo_id de los documentos a agendar
     * @return void
     */
    private function crearEstadosContingencia(NotificarDocumentos $clasePrincipal, User $user, array $documentosAgendarContingencia) {
        foreach($documentosAgendarContingencia as $cdo_id) {
            $clasePrincipal->creaNuevoEstadoDocumento(
                $cdo_id,
                'CONTINGENCIA',
                null,
                $user->usu_id,
                ['contingencia' => true]
            );
        }
    }
}
