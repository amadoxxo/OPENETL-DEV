<?php
namespace App\Console\Commands;

use Carbon\Carbon;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Emision\Reportes\DhlExpressController;
use App\Http\Modulos\Emision\Reportes\EmailCertificationController;
use App\Http\Modulos\Emision\Reportes\EventosNotificacionController;
use App\Http\Modulos\Emision\Reportes\DocumentosProcesadosController;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Reportes\RecepcionReporteDepencenciaController;
use App\Http\Modulos\Recepcion\Reportes\RecepcionDocumentosProcesadosController;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController;
use App\Http\Modulos\Recepcion\Reportes\RecepcionReporteGestionDocumentosController;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController;

class GenerarReporteCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generar-reporte {age_id : ID de Agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera un reporte en background';

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
     * @return void
     */
    public function handle() {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        // Se consulta el agendamiento
        $agendamiento = AdoAgendamiento::where('age_id', $this->argument('age_id'))
            ->where('age_proceso', 'REPORTE')
            ->first();

        if ($agendamiento) {
            // Obtiene el usuario relacionado con el agendamiento
            $user  = User::find($agendamiento->usu_id);
            $token = auth()->login($user);

            // Procesamiento Json que contiene el request
            $procesoJson = EtlProcesamientoJson::where('age_id', $agendamiento->age_id)
                ->where('pjj_procesado', 'NO')
                ->whereNull('age_estado_proceso_json')
                ->first();

            if($procesoJson) {
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-REPORTE'
                ]);

                $procesoJson->update([
                    'age_estado_proceso_json' => 'ENPROCESO'
                ]);

                // Inicializa los headers del Request
                $request = new Request();
                $request->headers->add(['Authorization' => 'Bearer ' . $token]);
                $request->headers->add(['accept' => 'application/json']);
                $request->headers->add(['x-requested-with' => 'XMLHttpRequest']);
                $request->headers->add(['content-type' => 'application/json']);
                $request->headers->add(['cache-control' => 'no-cache']);

                // Convierte la columna pjj_son en un objeto de la clase Request
                $parametros = json_decode($procesoJson->pjj_json, true);
                foreach($parametros as $parametro => $valor) {
                    $request->merge([
                        $parametro => $valor
                    ]);
                }

                switch ($procesoJson->pjj_tipo) {
                    case 'REPORTEDHLEXPRESS':
                        $classDhlExpress = App::make(DhlExpressController::class);
                        if($parametros['tipo_reporte'] == 'sin_envio')
                            $reporte = App::call([$classDhlExpress, 'dhlExpressSinEnvio'], [
                                'request' => $request
                            ]);
                        elseif($parametros['tipo_reporte'] == 'enviados')
                            $reporte = App::call([$classDhlExpress, 'dhlExpressEnviados'], [
                                'request' => $request
                            ]);
                        elseif($parametros['tipo_reporte'] == 'facturacion_manual_pickup_cash')
                            $reporte = App::call([$classDhlExpress, 'dhlExpressFacturacionManualPickupCash'], [
                                'request' => $request
                            ]);
                        elseif($parametros['tipo_reporte'] == 'archivo_entrada_pickup_cash')
                            $reporte = App::call([$classDhlExpress, 'dhlExpressArchivoEntradaPickupCash'], [
                                'request' => $request
                            ]);
                        break;

                    case 'REPORTEDOCPROCESADOS':
                        $classReporteDocumentosProcesados = App::make(DocumentosProcesadosController::class);
                        if($parametros['tipo_reporte'] == 'eventos_procesados')
                            $reporte = App::call([$classReporteDocumentosProcesados, 'procesarAgendamientoReporteEventos'], [
                                'request' => $request
                            ]);
                        else {
                            if ($parametros['proceso'] == 'recepcion') {
                                $classReporteDocumentosProcesados = App::make(RecepcionDocumentosProcesadosController::class);
                                $reporte = App::call([$classReporteDocumentosProcesados, 'procesarAgendamientoReporte'], [
                                    'request' => $request
                                ]);
                            } else 
                                $reporte = App::call([$classReporteDocumentosProcesados, 'procesarAgendamientoReporte'], [
                                    'request' => $request
                                ]);
                        }
                        break;

                    case 'REPORTEEVENTOSNOTIF':
                        $classReporteEventosNotificacion = App::make(EventosNotificacionController::class);
                        $reporte = App::call([$classReporteEventosNotificacion, 'procesarAgendamientoReporte'], [
                            'request' => $request
                        ]);
                        break;

                    case 'REPORTEEMAILCERTIFI':
                        $classReporteEmailCertification = App::make(EmailCertificationController::class);
                        $reporte = App::call([$classReporteEmailCertification, 'procesarAgendamientoReporte'], [
                            'request' => $request
                        ]);
                        break;

                    case 'EENVIADOS':
                    case 'ENOENVIADOS':
                        $classEmisionReportesBackground = App::make(EtlCabeceraDocumentoDaopController::class);
                        $reporte = App::call([$classEmisionReportesBackground, 'procesarAgendamientoReporte'], [
                            'request' => $request,
                            'pjj_tipo' => $procesoJson->pjj_tipo
                        ]);
                        break;

                    case 'NENVIADOS':
                    case 'NNOENVIADOS':
                        $classNominaReportesBackground = App::make(DsnCabeceraDocumentoNominaDaopController::class);
                        $reporte = App::call([$classNominaReportesBackground, 'procesarAgendamientoReporte'], [
                            'request' => $request,
                            'pjj_tipo' => $procesoJson->pjj_tipo
                        ]);
                        break;

                    case 'RRECIBIDOS':
                    case 'RVALIDACION':
                    case 'RLOGVALIDACION':
                        $classRecepcion = App::make(RepCabeceraDocumentoDaopController::class);
                        $reporte = App::call([$classRecepcion, 'procesarAgendamientoReporte'], [
                            'request'  => $request,
                            'pjj_tipo' => $procesoJson->pjj_tipo
                        ]);
                        break;

                    case 'RDEPENDENCIAS':
                        $classRecepcion = App::make(RecepcionReporteDepencenciaController::class);
                        $reporte = App::call([$classRecepcion, 'procesarAgendamientoReporte'], [
                            'request'  => $request,
                            'pjj_tipo' => $procesoJson->pjj_tipo
                        ]);
                        break;

                    case 'REPORTEGESTIONDOC':
                        $classRecepcion = App::make(RecepcionReporteGestionDocumentosController::class);
                        $reporte = App::call([$classRecepcion, 'procesarAgendamientoReporte'], [
                            'request'  => $request
                        ]);
                        break;

                    case 'RDOCRAD':
                        $classRadian = App::make(RadianCabeceraDocumentoDaopController::class);
                        $reporte = App::call([$classRadian, 'procesarAgendamientoReporte'], [
                            'request' => $request
                        ]);
                        break;

                    case 'REPADQ':
                        $classRecepcion = App::make(ConfiguracionAdquirenteController::class);
                        $reporte = App::call([$classRecepcion, 'procesarAgendamientoReporte'], [
                            'request' => $request
                        ]);
                        break;

                    default:
                        $reporte['errors'] = ['No se pudo determinar el tipo de reporte a generar.'];
                        $reporte['traza'] = [''];
                        break;
                }

                if(empty($reporte['errors'])) {
                    // Se agrega el nombre del archivo al json de los parámetros con los cuales se creó el reporte y se actualiza el registro
                    $parametros['archivo_reporte'] = $reporte['archivo'];
                    $procesoJson->update([
                        'pjj_json'                => json_encode($parametros),
                        'pjj_errores'             => null,
                        'pjj_procesado'           => 'SI',
                        'age_estado_proceso_json' => 'FINALIZADO'
                    ]);
                } else {
                    // Se agrega el error a los parámetros con los cuales se creó el reporte y se actualiza el registro
                    $parametros['errors'] = implode(' || ', $reporte['errors']);
                    $procesoJson->update([
                        'pjj_json'                => json_encode($parametros),
                        'pjj_errores'             => json_encode($reporte['traza']),
                        'pjj_procesado'           => 'SI',
                        'age_estado_proceso_json' => 'FINALIZADO'
                    ]);
                }

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);
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
