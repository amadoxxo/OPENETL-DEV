<?php

namespace App\Console\Commands\Recepcion\FNC;

use App\Traits\DiTrait;
use App\Http\Models\User;
use App\Traits\RecepcionTrait;
use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use openEtl\Tenant\Traits\TenantDatabase;
use ZBateson\MailMimeParser\Message as MimeMessage;
use App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManual;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RecepcionConsultaCorreosFncCommand extends Command {
    use DiTrait, RecepcionTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-consulta-correos-fnc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consulta de correos de OFEs del grupo de FNC en el proceso Recepción';

    /**
     * Cantidad de mensajes de correo que pueden ser leídos en cada ejecución del comando.
     *
     * @var integer
     */
    protected $cantidadMensajesPorProceso = 100;

    /**
     * Permisos para archivos
     *
     * @var string
     */
    protected $permisosArchivos = 0755;

    /**
     * Permisos para carpetas
     *
     * @var string
     */
    protected $permisosCarpetas = 0755;

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
                ->where('ofe_recepcion_fnc_activo', 'SI');
            if (!empty($user->bdd_id_rg))
                $ofes = $ofes->where('bdd_id_rg', $user->bdd_id_rg);
            else
                $ofes = $ofes->whereNull('bdd_id_rg');
            $ofes = $ofes->where('estado', 'ACTIVO')
                ->get();

            if($ofes->isEmpty()) {
                $this->error('No se encontraron OFEs configurados para el proceso de Recepción - Consulta Correos en la base de datos [' . $user->getBaseDatos->bdd_nombre . ']');
                continue;
            }

            foreach($ofes as $ofe) {
                if (empty($ofe->ofe_recepcion_fnc_configuracion)) {
                    $this->error('El OFE [' . $ofe->ofe_identificacion . '] de la base de datos [' . $user->getBaseDatos->bdd_nombre . '] no tiene configurada la información para el proceso de Recepción - Consulta Correos');
                    continue;
                }

                $ruta = json_decode($ofe->ofe_recepcion_fnc_configuracion);

                dump($ofe->ofe_identificacion);
                dump($ruta->username);

                // Verificando ruta principal donde se cargan los anexos, debe existir ya que no es creada por el comando
                if (!File::exists($ruta->directorio)) {
                    $this->error('No existe en el disco la ruta base del directorio para procesamiento de correos de la base de datos [' . $user->getBaseDatos->bdd_nombre . '] y OFE [' . $ofe->ofe_identificacion . ']');
                    continue;
                }

                try {
                    $this->crearDirectorios($ofe, $ruta, $this->permisosCarpetas, true, $user);

                    DiTrait::crearDiscoDinamico($user->getBaseDatos->bdd_nombre, $ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion);

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
                    $paths = [
                        'INBOX',
                        'INBOX.Spam'
                    ];

                    foreach ($paths as $path) {
                        $folder = $client->getFolderByPath($path);

                        $messages = $folder->query()
                            ->all()
                            ->unseen() // Lee solamente los mensajes no leídos
                            ->limit($this->cantidadMensajesPorProceso)
                            ->get();

                        foreach ($messages as $message) {
                            if ($message->hasAttachments()) {
                                // Identifica el origen del correo de acuerdo a la conposición del subject del correo para poder definir la carpeta en la que se almacenará
                                $headers = imap_rfc822_parse_headers($message->getHeader()->raw);
                                $headers = MimeMessage::from($message->getHeader()->raw, true);
                                $subject = trim(str_replace(['Rm:', 'RV:', 'Fwd:', 'Re:'], ['', '', '', ''], $headers->getHeaderValue('Subject')));

                                // Extrayendo información del cuerpo del correo
                                $cuerpoCorreo   = ($message->hasTextBody() && !empty($message->getTextBody())) ? $message->getTextBody() : (($message->hasHtmlBody()) ? strip_tags($message->getHtmlBody()) : '');
                                $correoCompleto = ($message->hasHtmlBody() || $message->hasTextBody()) ? $message->getHeader()->raw . $message->getRawBody() : '';

                                $partesSubject = explode(';', $subject);
                                if (count($partesSubject) >= 5) {
                                    $carpetaDestino = $ruta->entrada;

                                    $tmpArray = [];
                                    foreach($partesSubject as $part)
                                        $tmpArray[] = trim($part);

                                    $subject = implode(';', $tmpArray);
                                } else {
                                    $carpetaDestino = $ruta->fallidos;
                                    $insertEmail = [
                                        'ofe_identificacion'    => $ofe->ofe_identificacion,
                                        'epm_subject'           => $subject,
                                        'epm_id_carpeta'        => $message->getMessageId()->toString(),
                                        'epm_cuerpo_correo'     => (!empty($cuerpoCorreo)) ? $cuerpoCorreo : 'Correo sin contenido.',
                                        'epm_fecha_correo'      => $message->getDate()->toString(),
                                        'epm_procesado'         => 'NO',
                                        'epm_procesado_usuario' => NULL,
                                        'epm_procesado_fecha'   => NULL,
                                        'usuario_creacion'      => '1',
                                        'estado'                => 'ACTIVO'
                                    ];
                                    EtlEmailProcesamientoManual::create($insertEmail);
                                }

                                $rutaCorreo = $ruta->directorio . '/' . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $carpetaDestino . $message->getMessageId()->toString() . '/';
                                if(!File::exists($rutaCorreo)) {
                                    File::makeDirectory($rutaCorreo, 0755, true);
                                    $this->asignarPermisos($rutaCorreo, config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), $this->permisosCarpetas, $this->permisosArchivos);
                                }
                                $this->asignarPermisos($rutaCorreo, config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), $this->permisosCarpetas, $this->permisosArchivos);

                                Storage::disk($user->getBaseDatos->bdd_nombre)->put($carpetaDestino . '/' . $message->getMessageId()->toString() . '/fecha.txt', $message->getDate()->toString());
                                $this->asignarPermisos($rutaCorreo . 'fecha.txt', config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), $this->permisosCarpetas, $this->permisosArchivos);

                                Storage::disk($user->getBaseDatos->bdd_nombre)->put($carpetaDestino . '/' . $message->getMessageId()->toString() . '/subject.txt', $subject);
                                $this->asignarPermisos($rutaCorreo . 'subject.txt', config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), $this->permisosCarpetas, $this->permisosArchivos);

                                // Almacena el cuerpo completo del mensaje
                                Storage::disk($user->getBaseDatos->bdd_nombre)->put($carpetaDestino . '/' . $message->getMessageId()->toString() . '/correo_completo.txt', $correoCompleto);
                                $this->asignarPermisos($rutaCorreo . 'correo_completo.txt', config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), $this->permisosCarpetas, $this->permisosArchivos);

                                // Almacena el cuerpo (texto) del mensaje
                                Storage::disk($user->getBaseDatos->bdd_nombre)->put($carpetaDestino . '/' . $message->getMessageId()->toString() . '/cuerpo_correo.txt', $cuerpoCorreo);
                                $this->asignarPermisos($rutaCorreo . 'cuerpo_correo.txt', config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), $this->permisosCarpetas, $this->permisosArchivos);

                                $message->getAttachments()->each(function($attachment) use ($user, $carpetaDestino, $message, $rutaCorreo) {
                                    // Estensiones de archivos son almacenadas en minúsculas para permitir posteriores lecturas con métodos glob de PHP
                                    $nombre_attachment = explode('.', $attachment->name);
                                    $nombre_attachment = str_replace(
                                        '.' . $nombre_attachment[count($nombre_attachment) - 1],
                                        '.' . strtolower($nombre_attachment[count($nombre_attachment) - 1]),
                                        $attachment->name
                                    );

                                    Storage::disk($user->getBaseDatos->bdd_nombre)->put(
                                        $carpetaDestino . '/' . $message->getMessageId()->toString() . '/' . $nombre_attachment,
                                        $attachment->content
                                    );
                                    $this->asignarPermisos($rutaCorreo . $nombre_attachment, config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), $this->permisosCarpetas, $this->permisosArchivos);
                                });
                            }

                            $message->setFlag('Seen'); // Marca el mensaje como leído para que no sea procesado posteriormente
                        }
                    }

                    $client->disconnect();
                } catch (\Exception $e) {
                    $arrExcepciones = [];
                    $arrExcepciones[] = [
                        'documento'           => '',
                        'consecutivo'         => '',
                        'prefijo'             => '',
                        'errors'              => ['Base de Datos [' . $user->getBaseDatos->bdd_nombre . '] - OFE [' . $ofe->ofe_identificacion . '] - Errors:' . $e->getMessage()],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'archivo'             => '',
                        'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                    ];
    
                    $this->registrarErroresProcesamiento([
                        'pjj_tipo'                => 'REPCONSULTACORREOS',
                        'pjj_json'                => json_encode([]),
                        'pjj_procesado'           => 'SI',
                        'pjj_errores'             => json_encode($arrExcepciones),
                        'age_id'                  => 0,
                        'age_estado_proceso_json' => 'FINALIZADO',
                        'usuario_creacion'        => $user->usu_id,
                        'estado'                  => 'ACTIVO',
                    ]);
                }
            }

            DB::purge('conexion01');
        }
    }

    /**
     * Verifica si existen las carpetas de la estructura inicial de carpetas, si no existen se crean.
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE para el cual se esta ejecutando el proceso
     * @param \stdClass $ruta Objeto que contiene la configuración de carpetas
     * @param string    $permisos Permisos para las carpetas
     * @param boolean   $recursivo Recursividad sobre la creación de carpetas
     * @param object    $user Usuario autenticado en el sistema
     * @return void
     */
    public function crearDirectorios($ofe, \stdClass $ruta, string $permisos, bool $recursivo = true, $user) {
        $creado        = false; // Indica si un directorio fue creado

        if(!File::exists($ruta->directorio . $user->getBaseDatos->bdd_nombre))  {
            File::makeDirectory($ruta->directorio . $user->getBaseDatos->bdd_nombre , $permisos, $recursivo);
            $creado = true;
        }

        if (!File::exists($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion)) {
            File::makeDirectory($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion, $permisos, $recursivo);
            $creado = true;
        }
        
        if(!File::exists($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $ruta->entrada)) {
            File::makeDirectory($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $ruta->entrada, $permisos, $recursivo);
            $creado = true;
        }

        if(!File::exists($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $ruta->exitosos)) {
            File::makeDirectory($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $ruta->exitosos, $permisos, $recursivo);
            $creado = true;
        }

        if(!File::exists($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $ruta->fallidos)) {
            File::makeDirectory($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion . '/' . $ruta->fallidos, $permisos, $recursivo);
            $creado = true;
        }

        if ($creado) {
            $this->asignarPermisos($ruta->directorio . $user->getBaseDatos->bdd_nombre . '/', config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), 0755, 0755);
        }
    }

    /**
     * Asigna permiso a los directorios y archivos de un directorio.
     *
     * @param string $ruta      ruta del directorio
     * @param string $usuarioSo Usuario sistema operativo asignar al directorio
     * @param string $grupoSO   Grupo sistema operativo asignar al directorio
     * @param string $permisos  permiso asignar al directorio
     * @return void
     */
    public function asignarPermisos(string $ruta, string $usuarioSo, string $grupoSO, string $permisosCarpeta, string $permisosArchivos){
        // abrir un directorio y listarlo recursivo
        if (is_dir($ruta)) {
            chown($ruta, $usuarioSo);
            chgrp($ruta, $grupoSO); 
            chmod($ruta, $permisosCarpeta);
            if ($dh = opendir($ruta)) {
                while (($file = readdir($dh)) !== false) {
                    chown($ruta . $file, $usuarioSo);
                    chgrp($ruta . $file, $grupoSO); 
                    chmod($ruta . $file, $permisosCarpeta);
                    //mostraría tanto archivos como directorios
                    if (is_dir($ruta . $file) && $file!="." && $file!="..") {
                        //solo si el archivo es un directorio, distinto que "." y ".."
                        // dump("Directorio: $ruta$file");
                        $this->asignarPermisos($ruta . $file . '/', $usuarioSo, $grupoSO, $permisosCarpeta, $permisosArchivos);
                    }
                }
                closedir($dh);
            }
        } else {
            // Si es un archivo se asignan los permisos
            if (is_file($ruta)) {
                chown($ruta, $usuarioSo);
                chgrp($ruta, $grupoSO); 
                chmod($ruta, $permisosArchivos);
            }
        } 
    }
}
