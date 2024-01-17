<?php
namespace App\Console\Commands\ProcesosDataBase\p2023;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccion;

class C2023_12_14_160932_ActualizarRegistrosParametricaEtlConceptosCorrecionCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-parametrica-etl-conceptos-correcion-2023-12-14';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los registros iniciales de la paramétrica conceptos correción';

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

        ParametrosConceptoCorreccion::where('cco_tipo', 'NC')
            ->where('cco_codigo', '5')
            ->whereNull('fecha_vigencia_hasta')
            ->update(['fecha_vigencia_hasta' => '2024-02-01']);

        $inserts = [
            [
                'cco_tipo'                => 'NC',
                'cco_codigo'              => '5',
                'cco_descripcion'         => 'Descuento comercial por pronto pago',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ],
            [
                'cco_tipo'                => 'NC',
                'cco_codigo'              => '6',
                'cco_descripcion'         => 'Descuento comercial por volumen de ventas',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ]
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosConceptoCorreccion::where('cco_codigo', $insert['cco_codigo'])
                ->where('cco_tipo', $insert['cco_tipo'])
                ->whereNull('fecha_vigencia_desde')
                ->whereNull('fecha_vigencia_hasta')
                ->first();

            if(!$existe) {
                ParametrosConceptoCorreccion::create($insert);
                $this->info('El registro fue creado: ' . $insert['cco_codigo'] . ' - ' . $insert['cco_descripcion']);
            } else {
                $this->error('El registro ya existe: ' . $insert['cco_codigo'] . ' - ' . $insert['cco_descripcion']);
            }
        }
    }
}
