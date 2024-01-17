<?php
namespace App\Console\Commands\DHLGlobal;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\ProyectosEspeciales\DHLGlobal\InterfaceCargueFacturasEdm;

class DhlGlobalInterfaceCargueFacturasEdmCommand extends Command {
    use DoTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhlglobal-interface-cargue-facturas-edm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Coloca en el SFTP de DHL Global los PDF y XML requeridos para el cargue de facturas EDM';

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
        $interfaceCargueFacturasEdm = new InterfaceCargueFacturasEdm();

        $dataBase = AuthBaseDatos::where('bdd_nombre', $interfaceCargueFacturasEdm::BASE_DATOS)->first();

        if(!$dataBase) {
            $this->error("No existe la base de datos [" . $interfaceCargueFacturasEdm::BASE_DATOS . "]");
            exit;
        }

        // Ubica un usuario relacionado con la BD para poder autenticarlo y acceder a los modelos Tenant
        $user = User::where('bdd_id', $dataBase->bdd_id)
            ->where('estado', 'ACTIVO')
            ->where('usu_type', 'ADMINISTRADOR')
            ->first();

        if(!$user) {
            $this->error("No se encontró un usuario asociado con la base de datos [" . $interfaceCargueFacturasEdm::BASE_DATOS . "]");
            exit;
        }

        auth()->login($user);

        DB::disconnect('conexion01');
        $this->reiniciarConexion($dataBase);

        $documentos = $interfaceCargueFacturasEdm->getDocumentosEdm();
        $documentos->map(function ($documento) use ($dataBase, $interfaceCargueFacturasEdm) {
            $this->reiniciarConexion($dataBase);

            $interfaceCargueFacturasEdm->procesar($documento);
        });
    }
}