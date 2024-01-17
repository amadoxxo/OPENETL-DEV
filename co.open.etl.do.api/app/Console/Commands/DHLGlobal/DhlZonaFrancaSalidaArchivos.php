<?php


namespace App\Console\Commands\DHLGlobal;


use Illuminate\Http\Request;

class DhlZonaFrancaSalidaArchivos extends DhlBaseSalidaArchivosCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-zona-franca-salida-archivos';

    /**
     * Nit del DHL Zona Franca.
     *
     * @var string
     */
    protected $nitOFE = '830025224';

    /**
     * Email del usuario que va a insertar el documento(s).
     *
     * @var string
     */
    protected $emailUsuario = 'dhlzonafranca@openio.co';

    /**
     * Nombre de la carpeta de empresa donde se almancenaran los archivos de modo temporal.
     *
     * @var string
     */
    protected $nombreEmpresaStorage = 'dhlzonafranca';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando base para el procesamiento de envio de archivos al servidor FTP en DHL Zona Franca y Deposito';

    /**
     * Execute the console command.
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Exception
     */
    public function handle(Request $request) {
        $this->process();
    }
}