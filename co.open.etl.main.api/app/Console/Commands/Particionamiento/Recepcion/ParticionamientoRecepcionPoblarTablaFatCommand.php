<?php
namespace App\Console\Commands\Particionamiento\Recepcion;

use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Recepcion\Particionamiento\Repositories\ParticionamientoRecepcionRepository;

class ParticionamientoRecepcionPoblarTablaFatCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'particionamiento:recepcion-poblar-tabla-fat {bdd_nombre : Nombre de la base de datos sobre la que se aplicará el particionamiento de data operativa en recepción} {cdo_id_inicial : Autoincremental Inicial del Documento en Cabecera} {cant_registros : Cantidad de registros a procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permite copiar manualmente la información de las tablas operativas (daop) a la tabla FAT de la base de datos indicada';

    /**
     * Instancia del repositorio ParticionamientoRecepcionRepository
     *
     * @var ParticionamientoRecepcionRepository
     */
    protected $particionamientoRecepcionRepository;

    /**
     * Cantidad de registros que se procesarán por bloques.
     *
     * @var integer
     */
    protected $cantidadRegistrosBloqueProcesar = 100;

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

        $baseDatos = AuthBaseDatos::select(['bdd_id', 'bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password'])
            ->where('bdd_nombre', $this->argument('bdd_nombre'))
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$baseDatos) {
            $this->error('La base de datos [' . $this->argument('bdd_nombre') . '] no existe o se encuentra inactiva');
            return;
        }

        // Usuario a autenticar para poder acceder a los modelos tenant
        $user = User::where('bdd_id', $baseDatos->bdd_id)
            ->where('usu_type', 'ADMINISTRADOR')
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$user) {
            $this->error('No se encontró el usuario [ADMINISTRADOR] de la base de datos [' . $this->argument('bdd_nombre') . '] o el usuario se encuentra inactivo');
            return;
        }

        auth()->login($user);

        dump(date('Y-m-d H:i:s'));
        $documentos = RepCabeceraDocumentoDaop::select(['cdo_id', 'fecha_modificacion', 'fecha_actualizacion'])
            ->with([
                'getEstadosDocumentosDaop:est_id,cdo_id,est_estado,est_resultado,est_informacion_adicional,est_ejecucion,est_fin_proceso,fecha_creacion,fecha_modificacion',
                'getMediosPagoDocumentosDaop:cdo_id,fpa_id'
            ])
            ->withCount('getDocumentosAnexos')
            ->where('cdo_id', '>=', $this->argument('cdo_id_inicial'))
            ->take($this->argument('cant_registros'))
            ->orderBy('cdo_id', 'asc')
            ->get();

        foreach($documentos->chunk($this->cantidadRegistrosBloqueProcesar) as $documentos) {
            foreach($documentos as $documento) {
                $this->particionamientoRecepcionRepository->generarConexionTenant($baseDatos);
                $this->particionamientoRecepcionRepository->copiarDocumentoTablaFat($documento);

                DB::disconnect($this->particionamientoRecepcionRepository::CONEXION);
            }
        }
        dump(date('Y-m-d H:i:s'));
    }
}
