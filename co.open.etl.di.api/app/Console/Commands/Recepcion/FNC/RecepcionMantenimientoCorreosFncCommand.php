<?php
namespace App\Console\Commands\Recepcion\FNC;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use App\Http\Models\AuthBaseDatos;
use Illuminate\Support\Facades\DB;
use Webklex\PHPIMAP\ClientManager;
use Illuminate\Support\Facades\File;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManual;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RecepcionMantenimientoCorreosFncCommand extends Command {
    use DiTrait;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-mantenimiento-correos-fnc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina correos, archivos y registros creados durante el proceso de recepción de correos de empresas del grupo de FNC';

    /**
     * Cantidad de días en los que se conserva la información de los archivos en disco.
     *
     * @var integer
     */
    protected $diasConservar = 14;

    /**
     * Cantidad de horas en los que se conserva los correos leidos.
     *
     * @var integer
     */
    protected $horasConservarCorreos = 1;

    /**
     * Array de IDs de bases de datos de empresas del grupo de FNC a las que aplica el proceso.
     *
     * @var array
     */
    protected $arrBddId = [
        2, 6, 183, 184, 185
    ];

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
     * @return void
     */
    public function handle() {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        // Obtiene el usuario de recepción relacionado con el proceso de correos
        $users = User::where('usu_email', 'LIKE', 'recepcion.%')
            ->where('usu_email', 'LIKE', '%@open.io')
            ->where(function($query) {
                $query->whereIn('bdd_id', $this->arrBddId)
                    ->orWhereIn('bdd_id_rg', $this->arrBddId);
            })
            ->where('estado', 'ACTIVO')
            ->with([
                'getBaseDatos:bdd_id,bdd_nombre,bdd_host,bdd_usuario,bdd_password',
                'getBaseDatosRg:bdd_id,bdd_nombre,bdd_host,bdd_usuario,bdd_password'
            ])
            ->get();

        foreach($users as $user) {
            // Usuario de autenticacion
            $userAuth = clone $user;

            // Autenticacion
            auth()->login($userAuth);

            // Se establece una conexión dinámica a la BD
            DB::purge('conexion01');
            TenantDatabase::setTenantConnection(
                'conexion01',
                $user->getBaseDatos->bdd_host,
                $user->getBaseDatos->bdd_nombre,
                $user->getBaseDatos->bdd_usuario,
                $user->getBaseDatos->bdd_password
            );

            // Base de datos usuario
            // Si la base de datos bdd_id_rg no es vacia, se asigna a la relacion de la base de principal 
            // la relacion de la base de datos de rg, esto porque en los procesos internos se hace uso de esa relacion
            if (!empty($user->bdd_id_rg))
                $user->getBaseDatos = $user->getBaseDatosRg;

            //Validando que el usuario de recepcion corresponda al usuario de la base de datos
            if ($user->usu_email != 'recepcion.' . (str_replace('etl_', '', $user->getBaseDatos->bdd_nombre)) . '@open.io') {
                $this->error('El usuario '. $user->usu_email . ' no correponde al usuario de recepcion [' . $user->getBaseDatos->bdd_nombre . '].');
                continue;
            }

            $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_recepcion_fnc_configuracion'])
                ->where('ofe_recepcion_fnc_activo', 'SI')
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->get();

            if($ofes->isEmpty()) {
                $this->error('No se encontraron OFEs configurados para el proceso de Recepción - Mantenimiento de Correos en la base de datos [' . $user->getBaseDatos->bdd_nombre . ']');
                continue;
            }

            foreach($ofes as $ofe) {
                if (empty($ofe->ofe_recepcion_fnc_configuracion)) {
                    $this->error('El OFE [' . $ofe->ofe_identificacion . '] de la base de datos [' . $user->getBaseDatos->bdd_nombre . '] no tiene configurada la información para el proceso de Recepción - Mantenimiento de Correos');
                    continue;
                }

                $ruta = json_decode($ofe->ofe_recepcion_fnc_configuracion);

                dump($ofe->ofe_identificacion);
                dump($ruta->username);

                // Verificando ruta principal donde se cargan los anexos, debe existir ya que no es creada por el comando
                if (!File::exists($ruta->directorio)) {
                    $this->error('No existe en el disco la ruta base del directorio para procesamiento de correos de la base de datos [' . $user->getBaseDatos->bdd_nombre . '] y OFE [' . $ofe->ofe_identificacion . ']');
                    die();
                }

                $fechaLimite = Carbon::now()->subDays($this->diasConservar);

                // Elimina registros de procesamiento manual
                EtlEmailProcesamientoManual::select(['epm_id'])
                    ->where('ofe_identificacion', $ofe->ofe_identificacion)
                    ->where('epm_fecha_correo', '<=', $fechaLimite)
                    ->delete();

                // Si el OFE tiene configurado los datos del correo y rutas
                if (!empty($ruta)) {
                    if (File::exists($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion)) {
                        // Elimina directorios y archivos
                        $dirIterator = new \RecursiveTreeIterator(new \RecursiveDirectoryIterator($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion, \RecursiveDirectoryIterator::SKIP_DOTS));
                        foreach($dirIterator as $path) {
                            if(strstr($path, 'fecha.txt')) {
                                $arrPath     = explode($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/', $path);
                                $fechaCorreo = File::get($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $arrPath[count($arrPath) - 1]);
                                if(strtotime($fechaCorreo) <= $fechaLimite->timestamp) {
                                    $rutaDirEliminar = str_replace('/fecha.txt', '', $ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $arrPath[count($arrPath) - 1]);
                                    File::deleteDirectory($rutaDirEliminar);
                                }
                            }
                        }
                    }

                    try {
                        // Elimina correos
                        // Crea una instancia temporal de conexión a una cuenta de correo Imap
                        $clientManager = new ClientManager();
                        $client        = $clientManager->make([
                            'host'           => $ruta->host,
                            'port'           => $ruta->port,
                            'protocol'       => $ruta->protocol,
                            'encryption'     => $ruta->encryption,
                            'validate_cert'  => $ruta->validate_cert,
                            'username'       => $ruta->username,
                            'password'       => $ruta->password,
                            'authentication' => $ruta->authentication
                        ]);
                        $client->connect();

                        $fechaLimiteCorreos = Carbon::now()->subHours($this->horasConservarCorreos);

                        $paths = [
                            'INBOX',
                            'INBOX.Spam'
                        ];

                        foreach ($paths as $path) {
                            $folder = $client->getFolderByPath($path);

                            $messages = $folder->query()
                                ->all()
                                ->seen() // Lee solamente los mensajes leídos
                                ->setFetchOrderAsc()
                                ->limit(50)
                                ->get();

                            foreach ($messages as $message) {
                              $fechaCorreo = Carbon::parse($message->getDate()->toString())->subHours(5);
                              dump($fechaCorreo."~".$fechaLimiteCorreos->format('Y-m-d H:i:s'));
                                if(strtotime($fechaCorreo) <= $fechaLimiteCorreos->timestamp)
                                    $message->delete($expunge = true);
                            }
                        }
                        $client->disconnect();
                    } catch (\Exception $e) {
                        dump($e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString());
                    }
                }
            }
            DB::purge('conexion01');
        }
    }
}
