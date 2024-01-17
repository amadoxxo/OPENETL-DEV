<?php
namespace App\Console\Commands\Particionamiento\Recepcion;

use App\Http\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Recepcion\Particionamiento\Repositories\ParticionamientoRecepcionRepository;

class ParticionamientoRecepcionTrasladoHistoricoManualCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'particionamiento:recepcion-traslado-historico-manual {bdd_nombre : Nombre de la base de datos sobre la que se aplicará el particionamiento de data operativa en recepción} {cdo_id_inicial : Autoincremental Inicial del Documento en Cabecera} {cant_registros : Cantidad de registros a procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permite pasar manualmente la información de las tablas operativas (daop) de recepción a las tablas de particionamiento';

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

        $baseDatos = AuthBaseDatos::select(['bdd_id', 'bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password', 'bdd_inicio_particionamiento_recepcion', 'bdd_dias_data_operativa_recepcion'])
            ->where('bdd_nombre', $this->argument('bdd_nombre'))
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$baseDatos) {
            $this->error('La base de datos [' . $this->argument('bdd_nombre') . '] no existe o se encuentra inactiva');
            return;
        }

        if(empty($baseDatos->bdd_inicio_particionamiento_recepcion) || empty($baseDatos->bdd_dias_data_operativa_recepcion)) {
            $this->error('La base de datos [' . $this->argument('bdd_nombre') . '] no tiene configurados todos los parámetros de particionamiento (bdd_inicio_particionamiento_recepcion o bdd_dias_data_operativa_recepcion)');
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

        $fechaLimiteParticionamiento = Carbon::now()->subDays($baseDatos->bdd_dias_data_operativa_recepcion)->format('Y-m-d H:i:s');

        // Obtiene la posición del registro dentro de la tabla de cabecera para definir el punto de inicio de la consulta
        $start = RepCabeceraDocumentoDaop::select('cdo_id')
            ->where('cdo_id', '<=', $this->argument('cdo_id_inicial'))
            ->whereNotNull('cdo_fecha_validacion_dian')
            ->where('cdo_fecha_validacion_dian', '<=', $fechaLimiteParticionamiento)
            ->count();

        $start = ($start - 1) < 0 ? 0 : ($start - 1);

        RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_fecha'])
            ->whereNotNull('cdo_fecha_validacion_dian')
            ->where('cdo_fecha_validacion_dian', '<=', $fechaLimiteParticionamiento)
            ->with([
                'getEstadosDocumentosDaop:est_id,cdo_id,est_estado,est_resultado,est_informacion_adicional,est_ejecucion,est_fin_proceso,fecha_creacion,fecha_modificacion',
                'getMediosPagoDocumentosDaop:cdo_id,fpa_id'
            ])
            ->withCount('getDocumentosAnexos')
            ->skip($start)
            ->take($this->argument('cant_registros'))
            ->orderBy('cdo_id', 'asc')
            ->chunkById($this->cantidadRegistrosBloqueConsulta, function($documentos) use ($baseDatos) {
                foreach($documentos as $documento) {
                    $particion = Carbon::parse($documento->cdo_fecha)->format('Ym');

                    $this->particionamientoRecepcionRepository->generarConexionTenant($baseDatos);
                    $this->particionamientoRecepcionRepository->trasladarDocumentoHistorico($documento, $particion);

                    DB::disconnect($this->particionamientoRecepcionRepository::CONEXION);
                }
            }, 'cdo_id');
    }
}
