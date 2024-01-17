<?php
namespace App\Console\Commands\Recepcion;

use Carbon\Carbon;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;

class RecepcionAceptacionTacitaDocumentosCommand extends Command {
    use DoTrait;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-aceptacion-tacita-documentos
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recepción - Procesa el agendamiento de aceptación tácita de documentos';

    /**
     * Instancia de la clase AdoAgendamiento para consulta del agendamiento
     *
     * @var AdoAgendamiento
     */
    protected $agendamiento;

    /**
     * Instancia de la clase EventStatusUpdate para consulta de eventos en la DIAN
     *
     * @var EventStatusUpdate
     */
    protected $eventStatusUpdate;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();

        $this->eventStatusUpdate       = new EventStatusUpdate('recepcion');
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
        $this->agendamiento = AdoAgendamiento::where('age_id', $this->argument('age_id'))
            ->where('age_proceso', 'RACEPTACIONT')
            ->where('estado', 'ACTIVO')
            ->first();

        if($this->agendamiento) {
            // Obtiene el usuario relacionado con el agendamiento
            $user = User::find($this->agendamiento->usu_id);

            // Generación del token requerido para poder acceder a los modelos Tenant
            auth()->login($user);

            $this->agendamiento->update([
                'age_proceso' => 'PROCESANDO-RACEPTACIONT'
            ]);

            // Obtiene los estados de los documentos asociados con el agendamiento
            RepEstadoDocumentoDaop::select(['est_id', 'cdo_id'])
                ->where('age_id', $this->argument('age_id'))
                ->where('estado', 'ACTIVO')
                ->whereNull('est_resultado')
                ->with([
                    'getRepCabeceraDocumentosDaop:cdo_id,ofe_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_nombre_archivos,fecha_creacion',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,ofe_archivo_certificado,ofe_password_certificado,bdd_id_rg'
                ])
                ->get()
                ->map(function ($estado) {
                    $inicioProceso = microtime(true);

                    try {
                        dump("cdo_id: " . $estado->getRepCabeceraDocumentosDaop->cdo_id);

                        $estado->update([
                            'est_ejecucion' => 'ENPROCESO',
                        ]);

                        $this->consultarEventosDian($inicioProceso, $estado, ['034'], true, '034');
                    } catch (\Exception $e) {
                        $arrExcepciones   = [];
                        $arrExcepciones[] = [
                            'documento'           => $estado->getRepCabeceraDocumentosDaop->cdo_clasificacion,
                            'consecutivo'         => $estado->getRepCabeceraDocumentosDaop->cdo_consecutivo,
                            'prefijo'             => $estado->getRepCabeceraDocumentosDaop->rfa_prefijo,
                            'errors'              => [$e->getMessage()],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s'),
                            'archivo'             => '',
                            'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                        ];

                        $estado->update([
                            'est_resultado'             => 'FALLIDO',
                            'est_mensaje_resultado'     => $e->getMessage(),
                            'est_inicio_proceso'        => Carbon::createFromTimestamp($inicioProceso)->toDateTimeString(),
                            'est_fin_proceso'           => date('Y-m-d H:i:s'),
                            'est_tiempo_procesamiento'  => number_format((microtime(true) - $inicioProceso), 3, '.', ''),
                            'est_informacion_adicional' => json_encode($arrExcepciones),
                            'est_ejecucion'             => 'FINALIZADO',
                        ]);
                    }
                });

            $this->agendamiento->update([
                'age_proceso' => 'FINALIZADO'
            ]);
        }
    }

    /**
     * Consulta en la DIAN los eventos que pueda tener registrados un documento, y de acuerdo a la configuración del OFE se pueden registrar o no como estados.
     *
     * @param string|float $inicioProceso Timestamp del inicio de procesamiento
     * @param Collection|RepEstadoDocumentoDaop $estado Instancia del estado relacionado con el procesamiento
     * @param array $codigosEventos Array con los códigos de los eventos a consultar en la DIAN (030 - Acuse de Recibo | DIAN 031 - Reclamo (Rechazo) | 032 - Recibo del bien, 033 - Aceptación expresa y 034 - Aceptación Tácita)
     * @param boolean $retornarAR Indica si se debe retornar el ApplicationResponse
     * @param string $codigoEventoArRetornar Código del evento DIAN para el cual se retornará el ApplicationResponse
     * @return void
     */
    public function consultarEventosDian($inicioProceso, $estado, array $codigosEventos, bool $retornarAR, string $codigoEventoArRetornar): void {
        $request = new Request();
        $request->merge([
            'cdo_id'                    => $estado->getRepCabeceraDocumentosDaop->cdo_id,
            'codigos_eventos_dian'      => implode(',', $codigosEventos),
            'retornar_ar'               => $retornarAR,
            'codigo_evento_ar_retornar' => $codigoEventoArRetornar,
            'array_response'            => true
        ]);
        
        $eventosDian = $this->eventStatusUpdate->consultarEventosDian($request);

        dump($estado->getRepCabeceraDocumentosDaop->cdo_id . ': Inicia Verificacion Evento 034');

        // Verifica si el documento tiene Aceptación tácita (034) en la DIAN
        if(
            (
                !array_key_exists('errors', $eventosDian) ||
                (array_key_exists('errors', $eventosDian) && empty($eventosDian['errors']))
            ) && array_key_exists('eventos_dian', $eventosDian) &&
            array_key_exists('034', $eventosDian['eventos_dian']) &&
            array_key_exists('existeEvento', $eventosDian['eventos_dian']['034']) && $eventosDian['eventos_dian']['034']['existeEvento'] == true
        ) {
            dump($estado->getRepCabeceraDocumentosDaop->cdo_id . ': Ya Existe Evento 034');
            $nombreArchivoDisco = $this->guardarArchivoEnDisco(
                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                $estado->getRepCabeceraDocumentosDaop,
                'recepcion',
                'validacionDianAceptacionT',
                'xml',
                $eventosDian['eventos_dian']['034']['arEvento']
            );

            $estado->update([
                'est_resultado'             => 'EXITOSO',
                'est_mensaje_resultado'     => 'La Application response ' . $eventosDian['eventos_dian']['034']['referencia'] . ', ha sido autorizada. // CUDE Evento: ' . $eventosDian['eventos_dian']['034']['uuidEvento'],
                'est_informacion_adicional' => json_encode([
                    'codigo_evento'         => $eventosDian['eventos_dian']['034']['codigoEvento'],
                    'descripcion_evento'    => $eventosDian['eventos_dian']['034']['descripcion'],
                    'id_evento'             => $eventosDian['eventos_dian']['034']['referencia'],
                    'cude_evento'           => $eventosDian['eventos_dian']['034']['uuidEvento'],
                    'fecha_evento'          => $eventosDian['eventos_dian']['034']['effectiveDate'],
                    'hora_evento'           => str_replace('-', ':', str_replace('-05:00', '', $eventosDian['eventos_dian']['034']['effectiveTime'])),
                    'est_xml'               => $nombreArchivoDisco
                ]),
                'est_inicio_proceso'        => Carbon::createFromTimestamp($inicioProceso)->toDateTimeString(),
                'est_fin_proceso'           => date('Y-m-d H:i:s'),
                'est_tiempo_procesamiento'  => number_format((microtime(true) - $inicioProceso), 3, '.', ''),
                'est_ejecucion'             => 'FINALIZADO',
            ]);

            $estado->getRepCabeceraDocumentosDaop->update([
                'cdo_estado'       => 'ACEPTACIONT',
                'cdo_fecha_estado' => $eventosDian['eventos_dian']['034']['effectiveDate'] . ' ' . str_replace('-', ':', str_replace('-05:00', '', $eventosDian['eventos_dian']['034']['effectiveTime']))
            ]);
        } else {
            dump($estado->getRepCabeceraDocumentosDaop->cdo_id . stristr(implode(' ', $eventosDian['errors']), 'timeout') !== false ? ' Error: ' . implode(' ', $eventosDian['errors']) : ': No Existe Evento 034');

            $estado->update([
                'est_resultado'             => 'FALLIDO',
                'est_mensaje_resultado'     => stristr(implode(' ', $eventosDian['errors']), 'timeout') !== false ? $eventosDian['errors'] : 'El evento con código [034] no existe en la DIAN para el documento',
                'est_inicio_proceso'        => Carbon::createFromTimestamp($inicioProceso)->toDateTimeString(),
                'est_fin_proceso'           => date('Y-m-d H:i:s'),
                'est_tiempo_procesamiento'  => number_format((microtime(true) - $inicioProceso), 3, '.', ''),
                'est_ejecucion'             => 'FINALIZADO',
            ]);
        }
    }
}
