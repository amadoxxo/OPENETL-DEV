<?php

namespace App\Console\Commands\DHLGlobal;

use App\Http\Models\User;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use Exception;
use Illuminate\Http\Request;

/**
 * Comando para la carga masiva de DHL Agencia
 *
 * Class DhlAgencia
 * @package App\Console\Commands\DHL
 */
class DhlAgencia extends DhlBaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-agencia-procesar-documentos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa archivos XML desde el servicio FTP de DHL Agencia para creación de documentos';

    /**
     * Nit del DHL Agencia.
     *
     * @var string
     */
    protected $nitOFE = '830002397';

    /**
     * Email del usuario que va a insertar el documento(s).
     *
     * @var string
     */
    protected $emailUsuario = 'dhlagencia@openio.co';

    /**
     * Nombre de la carpeta de empresa donde se almancenaran los archivos de modo temporal.
     *
     * @var string
     */
    protected $nombreEmpresaStorage = 'dhlagencia';

    /**
     * Create a new command instance.
     *
     * @return void,
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Exception
     */
    public function handle(Request $request)
    {
        // Obtiene el usuario relacionado con DHLGlobal
        $user = User::where('usu_email', $this->emailUsuario)
            ->first();
        auth()->login($user);
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_conexion_ftp'])
            ->where('ofe_identificacion', $this->nitOFE)
            ->first();
        $this->procesador($ofe, $user);
    }
}