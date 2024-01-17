<?php
namespace App\Console\Commands\ProcesosDataBase\p2023;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuento;

class C2023_12_14_165652_ActualizarRegistrosParametricaEtlCodigosDescuentosCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-parametrica-etl-codigos-descuentos-2023-12-14';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los registros iniciales de la paramétrica códigos descuentos';

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

        ParametrosCodigoDescuento::where('cde_codigo', '03')
            ->whereNull('fecha_vigencia_hasta')
            ->update(['fecha_vigencia_hasta' => '2024-02-01']);

        $inserts = [
            [
                'cde_codigo'              => '02',
                'cde_descripcion'         => 'Recargo no condicionado',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ],
            [
                'cde_codigo'              => '03',
                'cde_descripcion'         => 'Recargo condicionado',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ]
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosCodigoDescuento::where('cde_codigo', $insert['cde_codigo'])
                ->whereNull('fecha_vigencia_desde')
                ->whereNull('fecha_vigencia_hasta')
                ->first();

            if(!$existe) {
                ParametrosCodigoDescuento::create($insert);
                $this->info('El registro fue creado: ' . $insert['cde_codigo'] . ' - ' . $insert['cde_descripcion']);
            } else {
                $this->error('El registro ya existe: ' . $insert['cde_codigo'] . ' - ' . $insert['cde_descripcion']);
            }
        }
    }
}
