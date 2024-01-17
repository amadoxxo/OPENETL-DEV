<?php
namespace App\Console\Commands\ProcesosDataBase\p2023;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;

class C2023_12_14_153043_ActualizarRegistrosParametricaEtlTributosCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-parametrica-etl-tributos-2023-12-14';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los registros iniciales de la paramétrica tributos';

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

        ParametrosTributo::where('tri_codigo', '01')->update(['tri_descripcion' => 'Impuesto sobre la Ventas']);
        ParametrosTributo::where('tri_codigo', '23')->update(['tri_descripcion' => 'Impuesto Nacional del Carbono']);

        $inserts = [
            [
                'tri_codigo'              => '32',
                'tri_nombre'              => 'ICL',
                'tri_descripcion'         => 'Impuesto al Consumo de Licores',
                'tri_tipo'                => 'TRIBUTO-UNIDAD',
                'tri_aplica_tributo'      => 'SI',
                'tri_aplica_para_tributo' => 'DE',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ],
            [
                'tri_codigo'              => '36',
                'tri_nombre'              => 'ADV',
                'tri_descripcion'         => 'AD VALOREM',
                'tri_tipo'                => 'TRIBUTO',
                'tri_aplica_tributo'      => 'SI',
                'tri_aplica_para_tributo' => 'DE',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ]
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosTributo::where('tri_codigo', $insert['tri_codigo'])
                ->whereNull('fecha_vigencia_desde')
                ->whereNull('fecha_vigencia_hasta')
                ->first();

            if(!$existe) {
                ParametrosTributo::create($insert);
                $this->info('El registro fue creado: ' . $insert['tri_codigo'] . ' - ' . $insert['tri_descripcion']);
            } else {
                $this->error('El registro ya existe: ' . $insert['tri_codigo'] . ' - ' . $insert['tri_descripcion']);
            }
        }
    }
}
