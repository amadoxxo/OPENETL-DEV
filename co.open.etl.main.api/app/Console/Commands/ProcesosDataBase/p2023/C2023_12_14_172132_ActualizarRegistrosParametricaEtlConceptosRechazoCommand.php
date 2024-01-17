<?php
namespace App\Console\Commands\ProcesosDataBase\p2023;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\ConceptosRechazo\ParametrosConceptoRechazo;

class C2023_12_14_172132_ActualizarRegistrosParametricaEtlConceptosRechazoCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-parametrica-etl-conceptos-rechazo-2023-12-14';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los registros iniciales de la paramétrica conceptos rechazo';

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

        ParametrosConceptoRechazo::whereIn('cre_codigo', ['02', '03'])
            ->whereNull('fecha_vigencia_hasta')
            ->update(['fecha_vigencia_hasta' => '2024-02-01']);

        $inserts = [
            [
                'cre_codigo'              => '02',
                'cre_descripcion'         => 'Mercancía no entregada',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ],
            [
                'cre_codigo'              => '03',
                'cre_descripcion'         => 'Mercancía entregada parcialmente',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ]
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosConceptoRechazo::where('cre_codigo', $insert['cre_codigo'])
                ->whereNull('fecha_vigencia_desde')
                ->whereNull('fecha_vigencia_hasta')
                ->first();

            if(!$existe) {
                ParametrosConceptoRechazo ::create($insert);
                $this->info('El registro fue creado: ' . $insert['cre_codigo'] . ' - ' . $insert['cre_descripcion']);
            } else {
                $this->error('El registro ya existe: ' . $insert['cre_codigo'] . ' - ' . $insert['cre_descripcion']);
            }
        }
    }
}
