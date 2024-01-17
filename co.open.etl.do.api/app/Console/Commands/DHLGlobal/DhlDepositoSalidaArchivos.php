<?php


namespace App\Console\Commands\DHLGlobal;


use Illuminate\Http\Request;

class DhlDepositoSalidaArchivos extends DhlBaseSalidaArchivosCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-deposito-salida-archivos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando base para el procesamiento de envio de archivos al servidor FTP en DHL Deposito';

    /**
     * Nit del DHL Deposito.
     *
     * @var string
     */
    protected $nitOFE = '860038063';

    /**
     * Email del usuario que va a insertar el documento(s).
     *
     * @var string
     */
    protected $emailUsuario = 'dhldeposito@openio.co';

    /**
     * Nombre de la carpeta de empresa donde se almancenaran los archivos de modo temporal.
     *
     * @var string
     */
    protected $nombreEmpresaStorage = 'dhldeposito';

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