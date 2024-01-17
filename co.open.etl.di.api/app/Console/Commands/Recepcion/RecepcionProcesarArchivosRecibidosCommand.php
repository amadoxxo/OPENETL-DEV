<?php
namespace App\Console\Commands\Recepcion;

use App\Http\Models\User;
use Illuminate\Console\Command;
use App\Http\Models\AdoAgendamiento;
use App\Http\Modulos\Recepcion\RecepcionProcesamientoRdi;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;

class RecepcionProcesarArchivosRecibidosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-procesar-archivos-recibidos {age_id : ID del agendamiento RDI a procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los archivos recibidos basado en los agendamientos RDI de recepción';

    /**
     * Instancia de la clase RecepcionProcesamientoRdi que permite realizar el procesamiento de los documentos agendados.
     *
     * @var RecepcionProcesamientoRdi
     */
    protected $procesamientoRdi;

    /**
     * Create a new command instance.
     *
     * @param RecepcionProcesamientoRdi $procesamientoRdi Instancia de la clase mediante inyección de dependencias
     * @return void
     */
    public function __construct(RecepcionProcesamientoRdi $procesamientoRdi) {
        parent::__construct();

        $this->procesamientoRdi = $procesamientoRdi;
    }

    /**
     * Execute the console command.
     */
    public function handle() {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        // Se consulta el agendamiento
        $agendamiento = AdoAgendamiento::where('age_id', $this->argument('age_id'))
            ->where('age_proceso', 'RDI')
            ->where('estado', 'ACTIVO')
            ->first();

        if ($agendamiento) {
            $user = User::find($agendamiento->usu_id);
            auth()->login($user);

            $procesamientoJson = EtlProcesamientoJson::where('age_id', $this->argument('age_id'))
                ->where('pjj_tipo', 'RDI')
                ->whereNull('age_estado_proceso_json')
                ->first();

            if ($procesamientoJson) {
                $agendamiento->update(['age_proceso' => 'PROCESANDO-RDI']);
                $procesamientoJson->update([
                    'pjj_procesado'             => 'NO',
                    'age_estado_proceso_json'   => 'ENPROCESO'
                ]);

                $procesoRdi = $this->procesamientoRdi->procesarDocumentos($procesamientoJson->age_id, $procesamientoJson->pjj_json);

                $procesamientoJson->update([
                    'pjj_procesado'           => 'SI',
                    'age_estado_proceso_json' => 'FINALIZADO',
                    'pjj_errores'             => array_key_exists('errors', $procesoRdi) && !empty($procesoRdi['errors']) ? json_encode($procesoRdi['errors']) : null
                ]);

                // Actualiza el estado del agendamiento
                $agendamiento->update(['age_proceso' => 'FINALIZADO']);
            } else {
                // El agendamiento no encontró coincidencias en el modelo Tenant EtlProcesamientoJson
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
}
