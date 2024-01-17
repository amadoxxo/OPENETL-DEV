<?php

namespace App\Console\Commands\DHLGlobal\DocumentosSoporte;

use App\Http\Models\User;
use App\Console\Commands\DHLGlobal\DocumentosSoporte\DhlBaseDsCommand;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Comando para la carga masiva de DHL  Agencia
 *
 * Class DhlAgencia
 * @package App\Console\Commands\DHL
 */
class DhlAgenciaDs extends DhlBaseDsCommand {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-agencia-procesar-documentos-soporte';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa archivos XML de Documentos Soporte desde el servicio FTP de DHL Agencia para creación de documentos';

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
     */
    public function handle() {
        // Obtiene el usuario relacionado con DHL Agencia
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