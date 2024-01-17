<?php
namespace App\Console\Commands\ProcesosDataBase\p2023;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;

class C2023_12_14_100923_ActualizarRegistrosParametricaEtlTiposOperacionCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-parametrica-etl-tipos-operacion-2023-12-14';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los registros iniciales de la paramétrica tipos operación';

    /**
     * Create a new Command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console Command.
     * 
     * @return mixed
     */
    public function handle() {

        ParametrosTipoOperacion::where('top_codigo', '13')->update(['fecha_vigencia_hasta' => '2024-02-01']);

        $inserts = [
            [
                'top_codigo'           => '14',
                'top_descripcion'      => 'Notarios',
                'top_aplica_para'      => 'FC',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'top_codigo'           => '15',
                'top_descripcion'      => 'Compra Divisas',
                'top_aplica_para'      => 'FC',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'top_codigo'           => '16',
                'top_descripcion'      => 'Venta Divisas',
                'top_aplica_para'      => 'FC',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosTipoOperacion::where('top_codigo', $insert['top_codigo'])
                ->whereNull('fecha_vigencia_desde')
                ->whereNull('fecha_vigencia_hasta')
                ->first();

            if(!$existe) {
                ParametrosTipoOperacion::create($insert);
                $this->info('El registro fue creado: ' . $insert['top_codigo'] . ' - ' . $insert['top_descripcion']);
            } else {
                $this->error('El registro ya existe: ' . $insert['top_codigo'] . ' - ' . $insert['top_descripcion']);
            }
        }
    }
}
