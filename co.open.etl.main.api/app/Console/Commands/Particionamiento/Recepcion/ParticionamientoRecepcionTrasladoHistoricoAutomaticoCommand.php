<?php
namespace App\Console\Commands\Particionamiento\Recepcion;

use App\Http\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Recepcion\Particionamiento\Repositories\ParticionamientoRecepcionRepository;

class ParticionamientoRecepcionTrasladoHistoricoAutomaticoCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'particionamiento:recepcion-traslado-historico-automatico';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pasa automáticamente y a diario la información de las tablas operativas (daop) de recepción a las tablas de particionamiento';

    /**
     * Instancia del repositorio ParticionamientoRecepcionRepository
     *
     * @var ParticionamientoRecepcionRepository
     */
    protected $particionamientoRecepcionRepository;

    /**
     * Cantidad de registros que se procesarán por bloques de consultas a la base de datos.
     *
     * @var integer
     */
    protected $cantidadRegistrosBloqueConsulta = 100;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ParticionamientoRecepcionRepository $particionamientoRecepcionRepository) {
        parent::__construct();

        $this->particionamientoRecepcionRepository = $particionamientoRecepcionRepository;
    }

    /**
     * Execute the console command.
     * 
     * @return void
     */
    public function handle() {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        $basesDatos = AuthBaseDatos::select(['bdd_id', 'bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password', 'bdd_aplica_particionamiento_recepcion', 'bdd_inicio_particionamiento_recepcion', 'bdd_dias_data_operativa_recepcion'])
            ->where('bdd_aplica_particionamiento_recepcion', 'SI')
            ->where('estado', 'ACTIVO')
            ->get();

        foreach($basesDatos as $baseDatos) {
            if(empty($baseDatos->bdd_dias_data_operativa_recepcion))
                continue;
                
            // Usuario a autenticar para poder acceder a los modelos tenant
            $user = User::where('bdd_id', $baseDatos->bdd_id)
                ->where('usu_type', 'ADMINISTRADOR')
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$user)
                continue;

            auth()->login($user);

            $fechaLimiteParticionamiento   = Carbon::now()->subDays($baseDatos->bdd_dias_data_operativa_recepcion)->format('Y-m-d H:i:s');

            $this->particionamientoRecepcionRepository->generarConexionTenant($baseDatos);
            RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_fecha'])
                ->whereNotNull('cdo_fecha_validacion_dian')
                ->where('cdo_fecha_validacion_dian', '<=', $fechaLimiteParticionamiento)
                ->with([
                    'getEstadosDocumentosDaop:est_id,cdo_id,est_estado,est_resultado,est_informacion_adicional,est_ejecucion,est_fin_proceso,fecha_creacion,fecha_modificacion',
                    'getMediosPagoDocumentosDaop:men_id,fpa_id'
                ])
                ->withCount('getDocumentosAnexos')
                ->orderBy('cdo_id', 'asc')
                ->chunkById($this->cantidadRegistrosBloqueConsulta, function($documentos) use ($baseDatos) {
                    foreach($documentos as $documento) {
                        $particion = Carbon::parse($documento->cdo_fecha)->format('Ym');

                        $this->particionamientoRecepcionRepository->generarConexionTenant($baseDatos);
                        $this->particionamientoRecepcionRepository->trasladarDocumentoHistorico($documento, $particion);
                        DB::disconnect($this->particionamientoRecepcionRepository::CONEXION);
                    }
                }, 'cdo_id');

            // Purga la conexión a la BD para asegurar el cambio de la conexión a la siguiente BD del foreach
            DB::purge($this->particionamientoRecepcionRepository::CONEXION);
        }
    }
}
