<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Http\Models\User;
use Illuminate\Console\Command;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\NotificarEventosDian\NotificarEventosDianController;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class NotificarEventosDianCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notificar-eventos-dian
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notificación de eventos DIAN';

    /**
     * @var array cdo_clasificacion de los documentos que aplican.
     */
    protected $arrCdoClasificacion = ['FC','NC','ND'];

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

            $documentosNotificarEvento = []; // Array que almacena los ID de los documentos a notificar
            $estadosNotificarEvento    = []; // Array que almacena los ID de los estados de los documentos a notificar

            switch($agendamiento->age_proceso) {
                case 'RNOTACUSERECIBO':
                case 'RNOTRECIBOBIEN':
                case 'RNOTACEPTACION':
                case 'RNOTRECHAZO':
                    $classCabecera = RepCabeceraDocumentoDaop::class;
                    $classEstado   = RepEstadoDocumentoDaop::class;
                    $proceso       = 'recepcion';
                    break;
                case 'ENOTACEPTACIONT':
                    $classCabecera = EtlCabeceraDocumentoDaop::class;
                    $classEstado   = EtlEstadosDocumentoDaop::class;
                    $proceso       = 'emision';
                    break;
            }

            $documentosAgendamiento = $classEstado::select(['est_id', 'cdo_id', 'est_estado', 'est_informacion_adicional'])
                ->where('age_id', $this->argument('age_id'))
                ->whereNull('est_resultado')
                ->whereNull('est_ejecucion');

            if($proceso === 'emision') {
                $documentosAgendamiento = $documentosAgendamiento->whereHas('getCabeceraDocumentosDaop', function ($query) {
                    $query->whereIn('cdo_clasificacion', $this->arrCdoClasificacion);
                });
            }
            $documentosAgendamiento = $documentosAgendamiento->get()
                ->map( function ($estado) use (&$documentosNotificarEvento, &$estadosNotificarEvento) {
                    $documentosNotificarEvento[]             = $estado->cdo_id;
                    $estadosNotificarEvento[$estado->cdo_id] = [
                        'est_id'                     => $estado->est_id,
                        'est_estado'                 => $estado->est_estado,
                        'inicio'                     => microtime(true),
                        'estadoInformacionAdicional' => ($estado->est_informacion_adicional != '') ? json_decode($estado->est_informacion_adicional, true) : []
                    ];
                });

            if (!empty($documentosNotificarEvento)) {
                // Marca el agendamiento en procesando
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-' . $agendamiento->age_proceso
                ]);

                $classNotificarEventos = new NotificarEventosDianController();
                $notificarEventos      = $classNotificarEventos->notificarEventosDian($classCabecera, $classEstado, $proceso, $documentosNotificarEvento, $estadosNotificarEvento, $user, false);

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);
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
}
