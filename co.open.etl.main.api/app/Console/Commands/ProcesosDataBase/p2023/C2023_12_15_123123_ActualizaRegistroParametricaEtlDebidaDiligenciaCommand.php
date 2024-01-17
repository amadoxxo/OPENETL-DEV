<?php
namespace App\Console\Commands\ProcesosDataBase\p2023;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\SectorCambiario\DebidaDiligencia\ParametrosDebidaDiligencia;

class C2023_12_15_123123_ActualizaRegistroParametricaEtlDebidaDiligenciaCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-parametrica-etl-debida-diligencia-2023-12-14';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los registros iniciales de la paramétrica debida diligencia';

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

        $inserts = [
            [
                'ddi_codigo'              => '01',
                'ddi_descripcion'         => 'Debida Diligencia del Cliente - DDC General',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ],
            [
                'ddi_codigo'              => '02',
                'ddi_descripcion'         => 'Debida Diligencia del Cliente - DDC Reforzada',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ],
            [
                'ddi_codigo'              => '03',
                'ddi_descripcion'         => 'Debida diligencia intensificada por razón de la cuantía de las operaciones - DDC intensificada.',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ],
            [
                'ddi_codigo'              => '04',
                'ddi_descripcion'         => 'Debida Diligencia del Cliente - DDC simplificada',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ]
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosDebidaDiligencia::where('ddi_codigo', $insert['ddi_codigo'])
                ->whereNull('fecha_vigencia_desde')
                ->whereNull('fecha_vigencia_hasta')
                ->first();

            if(!$existe) {
                ParametrosDebidaDiligencia::create($insert);
                $this->info('El registro fue creado: ' . $insert['ddi_codigo'] . ' - ' . $insert['ddi_descripcion']);
            } else {
                $this->error('El registro ya existe: ' . $insert['ddi_codigo'] . ' - ' . $insert['ddi_descripcion']);
            }
        }
    }
}
