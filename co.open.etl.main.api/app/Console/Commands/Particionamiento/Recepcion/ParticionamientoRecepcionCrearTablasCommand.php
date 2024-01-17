<?php
namespace App\Console\Commands\Particionamiento\Recepcion;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use openEtl\Tenant\Traits\Particionamiento\TenantParticionamientoTrait;
use App\Http\Modulos\Recepcion\Particionamiento\Repositories\ParticionamientoRecepcionRepository;

class ParticionamientoRecepcionCrearTablasCommand extends Command {
    use TenantParticionamientoTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'particionamiento:recepcion-crear-tablas {bdd_nombre : Nombre de la base de datos sobre la que se aplicará el particionamiento de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea las tablas Tenant de particionamiento en recepción para la bases de datos indicada. Este comando no migra ningún tipo de información';

    /**
     * Instancia del repositorio ParticionamientoRecepcionRepository
     *
     * @var ParticionamientoRecepcionRepository
     */
    protected $particionamientoRecepcionRepository;

    /**
     * Cantidad de periodos que se pueden procesar de manera consecutiva.
     *
     * @var integer
     */
    protected $cantidadPeriodosProcesar = 5;

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

        $baseDatos = AuthBaseDatos::select(['bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password', 'bdd_inicio_particionamiento_recepcion'])
            ->where('bdd_nombre', $this->argument('bdd_nombre'))
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$baseDatos) {
            $this->error('La base de datos [' . $this->argument('bdd_nombre') . '] no existe o se encuentra inactiva');
            return;
        }

        if(empty($baseDatos->bdd_inicio_particionamiento_recepcion)) {
            $this->error('La base de datos [' . $this->argument('bdd_nombre') . '] no tiene un dato asociado en la columna [bdd_inicio_particionamiento_recepcion]');
            return;
        }

        $this->particionamientoRecepcionRepository->generarConexionTenant($baseDatos);

        $totalPeriodos = $this->generarPeriodosParticionamiento(
            Carbon::parse($baseDatos->bdd_inicio_particionamiento_recepcion . '01')->format('Y-m-d'),
            Carbon::now()->subMonth()->format('Y-m-d')
        );

        foreach(array_chunk($totalPeriodos, $this->cantidadPeriodosProcesar) as $periodos) {
            foreach($periodos as $periodo) {
                $this->particionamientoRecepcionRepository->generarConexionTenant($baseDatos);

                $builder = DB::connection($this->particionamientoRecepcionRepository::CONEXION);
                $builder->statement('SET FOREIGN_KEY_CHECKS=0');
                foreach($this->particionamientoRecepcionRepository->arrTablasParticionar as $tablaParticionar) {
                    if(!$this->particionamientoRecepcionRepository->existeTabla($this->particionamientoRecepcionRepository::CONEXION, $tablaParticionar . $periodo)) {
                        $this->info('Creando Tabla: ' . $tablaParticionar . $periodo);
                        $this->particionamientoRecepcionRepository->crearTablaParticionamiento($this->particionamientoRecepcionRepository::CONEXION, $tablaParticionar, $periodo);
                    } else {
                        $this->error('Existe Tabla: ' . $tablaParticionar . $periodo);
                    }
                }
                $builder->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
}
