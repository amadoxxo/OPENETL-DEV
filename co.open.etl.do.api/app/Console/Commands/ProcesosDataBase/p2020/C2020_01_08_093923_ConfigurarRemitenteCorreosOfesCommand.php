<?php

namespace App\Console\Commands\ProcesosDataBase\p2020;

use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class C2020_01_08_093923_ConfigurarRemitenteCorreosOfesCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'configurar-remitente-correos-ofes-2020-01-08 {email : Email Remitente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configura el remitente de correos para los OFEs en el sistema';

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
        $emailRemitente = [
            'from_email' => $this->argument('email')
        ];

        $bases_de_datos = $this->ask('Ingrese la lista de bases de datos en donde configurará el remitente de correos para los OFEs existentes');
        $this->line('');

        $this->info('A continuación deberá ingresar la información del host, usuario y clave de MySql del servidor...');
        $this->line('');
        $host = $this->ask('Ingrese el host de conexión');
        $usu_usuario_base_datos = $this->ask('Ingrese el usuario de conexión');
        $usu_password_base_datos = $this->ask('Ingrese el password de conexión');

        $basesDatos = explode(',', $bases_de_datos);
        $this->line('Total de bases de datos listadas: ' . count($basesDatos));

        foreach ($basesDatos as $baseDatos) {
            if(trim($baseDatos) != '') {
                $this->line('Creando conexión dinámica en Laravel a la base de datos ' . trim($baseDatos));
                Config::set('database.connections.' . trim($baseDatos), array(
                    'driver'    => 'mysql',
                    'host'      => $host,
                    'database'  => trim($baseDatos),
                    'username'  => $usu_usuario_base_datos,
                    'password'  => $usu_password_base_datos,
                    'charset'   => 'utf8',
                    'collation' => 'utf8_general_ci',
                    'prefix'    => ''
                ));
            }
        }

        foreach ($basesDatos as $baseDatos) {
            if(trim($baseDatos) != '') {
                $this->line('');
                $this->info('Iniciando proceso para la base de datos ' . trim($baseDatos));

                // Obtiene los OFEs existentes para actualizarlos
                $ofes = DB::connection(trim($baseDatos))->table('etl_obligados_facturar_electronicamente')->select(['ofe_id', 'ofe_conexion_smtp'])
                        ->whereNull('ofe_conexion_smtp')
                        ->get()
                        ->map(function($ofe) use ($baseDatos, $emailRemitente) {
                            DB::connection(trim($baseDatos))->table('etl_obligados_facturar_electronicamente')
                                ->where('ofe_id', $ofe->ofe_id)
                                ->update([
                                    'ofe_conexion_smtp' => json_encode($emailRemitente)
                                ]);
                        });

                $this->info('Proceso finalizado para base de datos ' . trim($baseDatos));
                $this->line('<------------------------------------------------->');
                $this->line('');
                DB::disconnect(trim($baseDatos));
            }
        }
    }
}
