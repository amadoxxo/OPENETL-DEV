<?php
namespace App\Console\Commands\ProcesosDataBase\p2021;

use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;

class C2021_06_15_090028_ActualizarRegistrosEtlTiposOperacionCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-etl-tipos-operacion-2021-06-15';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza registros de la tabla etl_tipo_operacion para marcar los que corresponden al sector salud';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * 
     * @return mixed
     */
    public function handle() {
        // Actualiza el json de la variable ADQUIRENTE_CONSUMIDOR_FINAL
        $update = ParametrosTipoOperacion::
            where(function($query) {
                $query->where('top_codigo', 'SS-CUDE')
                    ->orWhere('top_codigo', 'SS-CUFE')
                    ->orWhere('top_codigo', 'SS-POS')
                    ->orWhere('top_codigo', 'SS-Recaudo')
                    ->orWhere('top_codigo', 'SS-Reporte')
                    ->orWhere('top_codigo', 'SS-SinAporte')
                    ->orWhere('top_codigo', 'SS-SNum');
            })
            ->update([
                'top_sector' => 'SECTOR_SALUD'
            ]);

        if ($update) {
            $this->info('Registros actualizados con éxito.!');
        } else {
            $this->error('No se pudo actualizar el registro.');
        }
    }
}
