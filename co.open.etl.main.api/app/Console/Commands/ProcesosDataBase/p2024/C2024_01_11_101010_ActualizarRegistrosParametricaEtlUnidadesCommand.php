<?php
namespace App\Console\Commands\ProcesosDataBase\p2024;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\Unidades\ParametrosUnidad;

class C2024_01_11_101010_ActualizarRegistrosParametricaEtlUnidadesCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-parametrica-etl-unidades-2024-01-11';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los registros iniciales de la paramétrica unidades';

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

        ParametrosUnidad::where('und_codigo', 'MGM')
            ->first()
            ->whereNull('fecha_vigencia_hasta')
            ->update(['fecha_vigencia_hasta' => '2024-02-01']);

        $inserts = [
            [
                'und_codigo'              => 'MG',
                'und_descripcion'         => 'miligramo',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ],
            [
                'und_codigo'              => 'MHZ',
                'und_descripcion'         => 'megahercio',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ]
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosUnidad::where('und_codigo', $insert['und_codigo'])
                ->whereNull('fecha_vigencia_desde')
                ->whereNull('fecha_vigencia_hasta')
                ->first();

            if(!$existe) {
                ParametrosUnidad::create($insert);
                $this->info('El registro fue creado: ' . $insert['und_codigo'] . ' - ' . $insert['und_descripcion']);
            } else {
                $this->error('El registro ya existe: ' . $insert['und_codigo'] . ' - ' . $insert['und_descripcion']);
            }
        }
    }
}
