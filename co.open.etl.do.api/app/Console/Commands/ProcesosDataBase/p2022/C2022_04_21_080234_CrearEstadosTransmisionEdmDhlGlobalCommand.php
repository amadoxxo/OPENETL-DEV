<?php
namespace App\Console\Commands\ProcesosDataBase\p2022;

use App\Http\Models\User;
use Illuminate\Console\Command;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\ProyectosEspeciales\DHLGlobal\InterfaceCargueFacturasEdm;

class C2022_04_21_080234_CrearEstadosTransmisionEdmDhlGlobalCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crear-estados-transmision-edm-dhl-global-2022-04-21';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea los estados TRANSMISION_EDM para DHL Global';

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

        TenantDatabase::setTenantConnection(
            'conexion01',
            $dataBase->bdd_host,
            $dataBase->bdd_nombre,
            $dataBase->bdd_usuario,
            $dataBase->bdd_password
        );

        $documentos = $interfaceCargueFacturasEdm->getDocumentosEdm();
        $documentos->map(function ($documento) use ($interfaceCargueFacturasEdm) {
            $fechaInicio         = date('Y-m-d H:i:s');
            $inicioProcesamiento = microtime(true);

            $interfaceCargueFacturasEdm->crearEstado(
                $documento->cdo_id,
                'TRANSMISION_EDM',
                'EXITOSO',
                'Documento previo a la implementación del proyecto EDM',
                null,
                null,
                null,
                $fechaInicio,
                date('Y-m-d H:i:s'),
                number_format((microtime(true) - $inicioProcesamiento), 3, '.', ''),
                'FINALIZADO',
                auth()->user()->usu_id,
                'ACTIVO'
            );
        });
    }   
}