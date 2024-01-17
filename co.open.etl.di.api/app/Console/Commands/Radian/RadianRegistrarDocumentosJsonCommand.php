<?php

/**
* Comando que permite procesar documentos Json para ser creados en Radian
*/

namespace App\Console\Commands\Radian;

use Carbon\Carbon;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use App\Http\Models\AdoAgendamiento;
use App\Http\Modulos\Radian\RadianRegistrarDocumento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;

class RadianRegistrarDocumentosJsonCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'radian-registrar-documentos-json {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los documentos Json de Radian';

    /**
     * Instancia de la clase RadianRegistrarDocumento que permite realizar el procesamiento de los documentos agendados
     *
     * @var RadianRegistrarDocumento
     */
    protected $radianRegistrarDocumento;

    /**
     * Create a new command instance.
     *
     * @param RadianRegistrarDocumento $radianRegistrarDocumento Instancia de la clase mediante inyección de dependencias 
     * @return void
     */
    public function __construct(RadianRegistrarDocumento $radianRegistrarDocumento) {
        parent::__construct();

        $this->radianRegistrarDocumento = $radianRegistrarDocumento;
    }

    /**
     * Execute the console command.
     *
     * @param Request $request
     * @return void
     */
    public function handle(Request $request): void {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        // Se consulta el agendamiento
        $agendamiento = AdoAgendamiento::find($this->argument('age_id'));
        if ($agendamiento) {
            // Obtiene el usuario relacionado con el agendamiento
            $user = User::find($agendamiento->usu_id);
            auth()->login($user);

            $procesamiento = EtlProcesamientoJson::where('age_id', $this->argument('age_id'))
                ->whereNull('age_estado_proceso_json')
                ->first();

            if ($procesamiento) {
                $agendamiento->update(['age_proceso' => 'PROCESANDO-RADEDI']);
                $procesamiento->update([
                    'pjj_procesado'             => 'NO',
                    'age_estado_proceso_json'   => 'ENPROCESO'
                ]);

                $json = $request->merge([
                    'origen' => 'MANUAL',
                    'documentos' => $procesamiento->pjj_json
                ]);

                $procesado = $this->radianRegistrarDocumento->run($json);

                // Actualiza el estado del agendamiento
                $agendamiento->update(['age_proceso' => 'FINALIZADO']);

                // Actualiza el modelo del proceso json
                if(!empty($procesado)) {
                    $procesamiento->update([
                        'pjj_procesado'             => 'SI',
                        'pjj_errores'               => array_key_exists('documentos_fallidos', $procesado) && !empty($procesado['documentos_fallidos']) ?
                            (string)json_encode($procesado['documentos_fallidos']) : null,
                        'age_estado_proceso_json'   => 'FINALIZADO'
                    ]);
                } else {
                    $procesamiento->update([
                        'pjj_procesado'             => 'SI',
                        'age_estado_proceso_json'   => 'FINALIZADO'
                    ]);
                }
                
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
