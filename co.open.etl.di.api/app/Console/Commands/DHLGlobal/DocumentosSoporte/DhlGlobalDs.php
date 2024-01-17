<?php

namespace App\Console\Commands\DHLGlobal\DocumentosSoporte;

use App\Http\Models\User;
use App\Console\Commands\DHLGlobal\DocumentosSoporte\DhlBaseDsCommand;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Comando para la carga masiva de DHL Global
 *
 * Class DhlGlobal
 * @package App\Console\Commands\DHL
 */
class DhlGlobalDs extends DhlBaseDsCommand {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-global-procesar-documentos-soporte';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa archivos XML de Documentos Soporte desde el servicio FTP de DHL Global para creación de documentos';

    /**
     * Nit del DHL Global.
     *
     * @var string
     */
    protected $nitOFE = '860030380';

    /**
     * Email del usuario que va a insertar el documento(s).
     *
     * @var string
     */
    protected $emailUsuario = 'dhlglobal@openio.co';

    /**
     * Nombre de la carpeta de empresa donde se almancenaran los archivos de modo temporal.
     *
     * @var string
     */
    protected $nombreEmpresaStorage = 'dhlglobal';

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
        // Obtiene el usuario relacionado con DHLGlobal
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