<?php

namespace App\Console\Commands\DHLGlobal\DocumentosSoporte;

use App\Http\Models\User;
use App\Console\Commands\DHLGlobal\DocumentosSoporte\DhlBaseDsCommand;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Comando para la carga masiva de DHL Deposito
 *
 * Class DhlDeposito
 * @package App\Console\Commands\DHL
 */
class DhlDepositoDs extends DhlBaseDsCommand {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-deposito-procesar-documentos-soporte';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa archivos XML de Documentos Soporte desde el servicio FTP de DHL Depósito para creación de documentos';

    /**
     * Nit del DHL Depósito.
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
     */
    public function handle() {
        // Obtiene el usuario relacionado con DHL Depósito
        $user = User::where('usu_email', $this->emailUsuario)
            ->first();

        if(!$user) {
            $this->line('Usuario [' . $this->emailUsuario . '] no se encontró en openETL');
            die();
        }

        auth()->login($user);
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_conexion_ftp'])
            ->where('ofe_identificacion', $this->nitOFE)
            ->first();
        
        if(!$ofe) {
            $this->line('OFE [' . $this->nitOFE . '] no se encontró en openETL');
            die();
        }

        $this->procesador($ofe, $user);
    }
}