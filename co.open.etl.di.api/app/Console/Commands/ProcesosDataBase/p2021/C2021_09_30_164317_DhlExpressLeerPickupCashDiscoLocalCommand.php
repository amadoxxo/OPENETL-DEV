<?php
namespace App\Console\Commands\ProcesosDataBase\p2021;

use App\Console\Commands\DHLExpress\DhlExpressEntradaPickupCashCommand;
use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class C2021_09_30_164317_DhlExpressLeerPickupCashDiscoLocalCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhlexpress-leer-pickup-cash-disco-local-2021-09-30';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Para el proceso Pickup-Cash de DHL Express, lee los archivos existentes en el directorio local y guarda su contenido en la tabla pry_guias_pickup_cash';

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
        $arrCorreos = [
            'dhlexpress-cra@openio.co',
            'dhlexpress-cra01@openio.co'
        ];

        $correo = $arrCorreos[array_rand($arrCorreos)];
        $user = User::where('usu_email', $correo)
            ->where('estado', 'ACTIVO')
            ->first();

        if($user) {
            $token           = auth()->login($user);
            foreach(['_new', ''] as $sufijoDisco) {
                $archivos        = File::files(storage_path() . '/etl/PickupCash' . $sufijoDisco);
                $pickupCashCommandClass = new DhlExpressEntradaPickupCashCommand;
                if(!empty($archivos)) {
                    foreach($archivos as $archivo) {
                        $extension = File::extension($archivo);
                        if($extension != 'txt')
                            continue;

                        $fileArray = file($archivo->getPathname());
                        for($i = 0; $i < count($fileArray); $i++) {
                            $line   = $fileArray[$i];
                            $linea1 = $pickupCashCommandClass->fixEncoding($line);

                            if(!empty($sufijoDisco)) {
                                $linea2 = '';
                            } else {
                                $i++;
                                // Cada guía corresponde a dos renglones seguidos en los archivos del formato antiguo, entonces
                                // leemos el siguiente renglon para tener los dos líneas correspondientes a la guía segunda línea
                                $linea2  = $pickupCashCommandClass->fixEncoding($fileArray[$i]);
                            }

                            $valores = $pickupCashCommandClass->extraerValores($linea1, $linea2, $sufijoDisco, ($i + 1));
                            $pickupCashCommandClass->guardarGuiaBaseDatos(empty($sufijoDisco) ? 'SFTP' : 'WEB', $valores);
                        }
                        
                        // Renombra el archivo para poder identificar posteriormente los archivos que fueron procesados
                        File::move($archivo->getPathname(), $archivo->getPathname() . '.procesado_basedatos');
                    }
                }
            }
        }
    }
}
