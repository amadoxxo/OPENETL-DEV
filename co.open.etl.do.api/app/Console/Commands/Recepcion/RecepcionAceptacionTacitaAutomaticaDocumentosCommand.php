<?php
namespace App\Console\Commands\Recepcion;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\Sistema\Festivos\SistemaFestivo;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use openEtl\Tenant\Servicios\TenantAceptacionTacitaService;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RecepcionAceptacionTacitaAutomaticaDocumentosCommand extends Command {
    use DoTrait, FechaVigenciaValidations;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-aceptacion-tacita-automatica-documentos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica los documentos en recepción que deben ser agendados para aceptación tácita';

    /**
     * Instancia de la clase EventStatusUpdate para consulta de eventos en la DIAN
     *
     * @var EventStatusUpdate
     */

    /**
     * Instancia de la clase TenantAceptacionTacitaService
     *
     * @var TenantAceptacionTacitaService
     */
    protected $aceptacionTacitaService;

    /**
     * Cantidad de reistros que se procesarán por bloques de consultas a la base de datos.
     *
     * @var integer
     */
    protected $cantidadRegistrosProcesar = 100;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();

        $this->aceptacionTacitaService = new TenantAceptacionTacitaService('recepcion');
    }

    /**
     * Execute the console command.
     * 
     * @return mixed
     */
    public function handle() {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        // Se consultan las bases de datos disponibles
        $basesDatos = AuthBaseDatos::where('estado', 'ACTIVO')
            ->get();

        $fechaCreacionMaxima = $this->evaluarDiasHabilesTranscurridos(Carbon::now('America/Bogota')->subDays(10), Carbon::now('America/Bogota')->subDay(), 3);

        $formasPago = ParametrosFormaPago::select('fpa_id', 'fpa_codigo', 'fpa_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
            ->where('fpa_codigo', '2')
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('fpa_codigo')
            ->map(function ($item) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if($vigente['vigente'])
                    return $vigente['registro'];
            })
            ->pluck('fpa_id')->values()->toArray();

        // Se realiza el proceso para cada base de datos
        foreach($basesDatos as $baseDatos) {
            try {
                // Define el correo del usuario de recepcion
                $bdSinPrefijo = str_replace('etl_', '', $baseDatos->bdd_nombre);
                $emailUsuario = 'recepcion.' . $bdSinPrefijo . '@open.io';

                // Ubica el usuario del proceso de recepcio RPA para autenticarlo y poder acceder a los modelos tenant
                $user = User::where('usu_email', $emailUsuario)
                    ->where('bdd_id', $baseDatos->bdd_id)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if(!$user) {
                    $user = User::where('usu_email', $emailUsuario)
                        ->where('bdd_id_rg', $baseDatos->bdd_id)
                        ->where('estado', 'ACTIVO')
                        ->first();
                }

                if(!$user)
                    continue;

                // Generación del token conforme al usuario
                auth()->login($user);

                // Se establece una conexión dinámica a la BD
                $this->reiniciarConexion($baseDatos);

                // Consultado los OFE de la base de datos que tienen activo el evento de aceptacion tacita en recepción
                $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_eventos_contratados_titulo_valor'])
                    ->where('ofe_recepcion', 'SI')
                    ->whereJsonContains('ofe_recepcion_eventos_contratados_titulo_valor', ['evento' => 'ACEPTACIONT'])
                    ->validarAsociacionBaseDatos()
                    ->where('estado', 'ACTIVO')
                    ->get();

                foreach ($ofes as $ofe) {
                    dump('=========================');
                    dump($baseDatos->bdd_nombre);

                    $ofeFechaInicioEventosDian = null;
                    array_map(function($evento) use (&$ofeFechaInicioEventosDian) {
                        if($evento['evento'] == 'ACEPTACIONT' && array_key_exists('fecha_inicio', $evento) && !empty($evento['fecha_inicio']))
                            $ofeFechaInicioEventosDian = $evento['fecha_inicio'] . ' 00:00:00';
                    }, $ofe->ofe_recepcion_eventos_contratados_titulo_valor);

                    if(!$ofeFechaInicioEventosDian)
                        continue;

                    $documentosValidarAceptacionTacita = [];
                    $documentosAgendarAceptacionTacita = [];
                    // Obtiene los documentos que en cabecera NO tengan asignado el estado, que tengan estado GETSTATUS éxitoso y que no tengan estados ACEPTACION, ACEPTACIONT o RECHAZO
                    RepCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'pro_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha_recibo_bien', 'cdo_fecha_acuse', 'cdo_fecha_validacion_dian', 'fecha_creacion'])
                        ->where('ofe_id', $ofe->ofe_id)
                        ->where('cdo_clasificacion', 'FC')
                        ->whereBetween('fecha_creacion', [$ofeFechaInicioEventosDian, $fechaCreacionMaxima . ' 23:59:59'])
                        ->where('estado', 'ACTIVO')
                        ->whereNull('cdo_estado')
                        ->whereNotNull('cdo_fecha_recibo_bien')
                        ->with([
                            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,tat_id,ofe_recepcion,ofe_recepcion_eventos_contratados_titulo_valor,estado',
                            'getConfiguracionProveedor:pro_id,pro_identificacion,tat_id',
                            'getRepDadDocumentosDaop:dad_id,cdo_id,dad_entrega_bienes_fecha,dad_entrega_bienes_hora'
                        ])
                        ->doesntHave('getAceptado')
                        ->doesntHave('getAceptadoT')
                        ->doesntHave('getRechazado')
                        ->whereDoesntHave('getEstadosDocumento', function($query) {
                            $query->where('est_estado', 'ACEPTACIONT')
                                ->where('est_resultado', 'FALLIDO')
                                ->where('est_mensaje_resultado', 'not like', '%timeout%')
                                ->whereBetween('fecha_creacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']);
                        })
                        ->whereDoesntHave('getEstadosDocumento', function($query) { // Estado pendiente por ejecución
                            $query->where('est_estado', 'ACEPTACIONT')
                                ->whereNull('est_resultado')
                                ->whereNull('est_ejecucion')
                                ->whereBetween('fecha_creacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59']);
                        })
                        ->whereHas('getMediosPagoDocumentosDaop', function ($query) use ($formasPago) {
                            $query->whereIn('fpa_id', $formasPago);
                        })
                        ->has('getGetStatus')
                        ->whereHas('getEstadoAcuseRecibo', function($query) {
                            $query->where('est_resultado', 'EXITOSO')
                                ->where('est_ejecucion', 'FINALIZADO');
                        })
                        ->whereHas('getEstadoReciboBien', function($query) {
                            $query->where('est_resultado', 'EXITOSO')
                                ->where('est_ejecucion', 'FINALIZADO');
                        })
                        ->orderBy('cdo_id', 'desc')
                        ->chunk($this->cantidadRegistrosProcesar, function($documentos) use ($baseDatos, &$documentosValidarAceptacionTacita) {
                            // Por cada bloque de resultados se reconecta la base de datos
                            $this->reiniciarConexion($baseDatos);

                            foreach($documentos as $documento)
                                $documentosValidarAceptacionTacita[] = $documento;
                        });

                    if(!empty($documentosValidarAceptacionTacita))
                        $documentosAgendarAceptacionTacita = $this->procesarAceptacionTacita($documentosValidarAceptacionTacita);

                    dump('Documentos para agendar:');
                    dump($documentosAgendarAceptacionTacita);

                    if(!empty($documentosAgendarAceptacionTacita))
                        $this->crearAgendamientosAceptacionTacita($documentosAgendarAceptacionTacita);
                }

                // Finaliza la conexión a la actual base de datos para poder pasar a la siguiente
                DB::disconnect('conexion01');
            } catch (\Exception $e) {
                dump($e->getTraceAsString());
                continue;
            }  
        }
    }

    /**
     * Obtiene los días festivos ebtre la fecha actual y los últimos 20 días.
     *
     * @return array
     */
    private function getFestivos() {
        return SistemaFestivo::where('estado', 'ACTIVO')
            ->whereBetween('fes_fecha', [Carbon::now('America/Bogota')->subDays(20)->format('Y-m-d'), Carbon::now('America/Bogota')->format('Y-m-d')])
            ->get()
            ->pluck('fes_fecha')
            ->toArray();
    }

    /**
     * Retona una fecha límite de acuerdo a la cantidad de días hábiles requeridos y entre un rango de fechas.
     *
     * @param Carbon $fechaInicio Fecha inicial
     * @param Carbon $fechaFinal Fecha final
     * @param integer $cantidadDias Cantidad de días hábiles a taner en cuenta
     * @return string|null Fecha límite en formato Y-m-d
     */
    public function evaluarDiasHabilesTranscurridos(Carbon $fechaInicio, Carbon $fechaFinal, int $cantidadDias) {
        $festivos = $this->getFestivos();
        
        $contDiasHabiles = 0;
        for($fecha = $fechaFinal->copy(); $fecha->lte($fechaFinal) && $fecha->gte($fechaInicio); $fecha->subDay()) {
            if(!$fecha->isSaturday() && !$fecha->isSunday() && !in_array($fecha->format('Y-m-d'), $festivos) && $contDiasHabiles < $cantidadDias)
                $contDiasHabiles++;

            if($contDiasHabiles == $cantidadDias)
                return $fecha->format('Y-m-d');
        }

        return null;
    }

    /**
     * Verifica los documentos en el array, para definir si se deben o no aceptar tácitamente y agendarlos.
     *
     * @param array $documentos Array de objetos de documentos
     * @return array Array con la información de los documentos que deben ser agendados
     */
    private function procesarAceptacionTacita(array $documentos): array {
        $this->aceptacionTacitaService->setDocumentos($documentos);
        return $this->aceptacionTacitaService->run();
    }

    /**
     * Realiza los agendamientos para RACEPTACIONT.
     *
     * @param array $documentosAgendar Array de documentos que deben ser agendados
     * @return void
     */
    private function crearAgendamientosAceptacionTacita(array $documentosAgendar): void {
        if(!empty(auth()->user()->bdd_id_rg)) {
            $bddId             = auth()->user()->bdd_id_rg;
            $cantidadRegistros = auth()->user()->getBaseDatosRg->bdd_cantidad_procesamiento_getstatus;
        } else {
            $bddId             = auth()->user()->bdd_id;
            $cantidadRegistros = auth()->user()->getBaseDatos->bdd_cantidad_procesamiento_getstatus;
        }

        $grupos = array_chunk($documentosAgendar, $cantidadRegistros);
        foreach ($grupos as $grupo) {
            $nuevoAgendamiento = DoTrait::crearNuevoAgendamiento('R' . $this->aceptacionTacitaService::ACEPTADO_TACITAMENTE, auth()->user()->usu_id, $bddId, count($grupo), null);
            foreach($grupo as $cdo_id) {
                $this->aceptacionTacitaService->creaNuevoEstadoDocumento(
                    $cdo_id,
                    $this->aceptacionTacitaService::ACEPTADO_TACITAMENTE,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $nuevoAgendamiento->age_id,
                    auth()->user()->usu_id,
                    null,
                    null
                );
            }
        }
    }
}
