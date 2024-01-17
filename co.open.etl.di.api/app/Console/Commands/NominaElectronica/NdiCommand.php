<?php

namespace App\Console\Commands\NominaElectronica;

use Carbon\Carbon;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Models\AdoAgendamiento;
use App\Http\Modulos\NominaElectronica\DataBuilder;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;

class NdiCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'procesar-documentos-json-nomina-electronica {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los documentos Json creados mediante cargue de Excel en Nómina Electrónica';

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
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function handle(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        // Se consulta el agendamiento
        $agendamiento = AdoAgendamiento::find($this->argument('age_id'));
        if ($agendamiento) {
            // Obtiene el usuario relacionado con el agendamiento
            $user  = User::find($agendamiento->usu_id);
            $token = auth()->login($user);

            // Se obtiene el registro de documentos json que deben ser procesados de acuerdo al Job
            $programacion = EtlProcesamientoJson::where('age_id', $this->argument('age_id'))
                ->whereNull('age_estado_proceso_json')
                ->first();

            if ($programacion) {
                $agendamiento->update(['age_proceso' => 'PROCESANDO-NDI']);
                $programacion->update([
                    'pjj_procesado'           => 'NO',
                    'age_estado_proceso_json' => 'ENPROCESO'
                ]);
                $json       = json_decode($programacion->pjj_json);
                $cdo_origen = $user->usu_type == 'INTEGRACION' ? 'INTEGRACION' : 'MANUAL';
                $builder    = new DataBuilder($user->usu_id, $json, $cdo_origen);
                $procesado  = $builder->procesar();

                $agendamiento->update(['age_proceso' => 'FINALIZADO']);

                if(count($procesado['resultado']) > 0) {
                    $programacion->update([
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => array_key_exists('documentos_fallidos', $procesado['resultado']) && count($procesado['resultado']['documentos_fallidos']) > 0 ?
                                                    (string)json_encode($procesado['resultado']['documentos_fallidos']) : null,
                        'age_estado_proceso_json' => 'FINALIZADO'
                    ]);
                } else {
                    $programacion->update([
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
