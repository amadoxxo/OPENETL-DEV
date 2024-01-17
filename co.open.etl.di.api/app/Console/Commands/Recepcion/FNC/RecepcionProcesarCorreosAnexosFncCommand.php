<?php
namespace App\Console\Commands\Recepcion\FNC;

use Ramsey\Uuid\Uuid;
use App\Traits\DiTrait;
use App\Http\Models\User;
use App\Traits\RecepcionTrait;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Recepcion\RecepcionException;
use App\Http\Modulos\Recepcion\ParserXml\ParserXml;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RecepcionProcesarCorreosAnexosFncCommand extends Command {
    use RecepcionTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-procesar-correos-anexos-fnc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los correos de de OFEs del grupo de FNC recibidos con anexos de documentos electrónicos en el proceso de recepción';

    /**
     * Cantidad de mensajes de correo que pueden ser procesados en cada ejecución del comando.
     *
     * @var integer
     */
    protected $cantidadMensajesPorProceso = 100;

    /**
     * Disco de trabajo creado en tiempo de ejecución desde donde se leen los anexos de los correos.
     *
     * @var string
     */
    protected $discoCorreos = '';

    /**
     * Disco de trabajo creado en tiempo de ejecución en donde se almacenan finalmente los anexos.
     *
     * @var string
     */
    protected $discoAnexos = '';

    /**
     * Path al directorio principal de procesamiento.
     *
     * @var string
     */
    protected $pathDirPrincipal = '';

    /**
     * Objeto json conteniendo las rutas de disco a utilizar en el comando.
     *
     * @var stdClass
     */
    protected $rutas = null;

    /**
     * Array de errores que son generados en el procesamiento de cada carpeta, para almacenar un solo registro con todos los errores de una sola carpeta.
     *
     * @var array
     */
    protected $arrErroresPorDirectorio = [];

    /**
     * Array de archivos que NO deben ser tenidos en cuenta para guardarse como anexos.
     *
     * @var array
     */
    protected $arrArchivosDescartar = [];

    /**
     * Array de archivos extraidos de un zip que deben ser eliminados despues de ser procesados.
     *
     * @var array
     */
    protected $arrArchivosZipEliminar = [];

    /**
     * Lote de procesamiento que será asignado a los anexos procesados por el comando
     *
     * @var string
     */
    protected $lote = '';

    /**
     * Indica si todos los archivos dentro de un sip lograron ser descomprimidos de manera exitosa
     *
     * @var bool
     */
    protected $zipDescomprimidoTotal = true;

    /**
     * Variable que permitirá instanciar la clase que permite procesar y obtener información de los archivos XML en recepción
     *
     * @var ParserXml
     */
    protected $parserXml;

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
                $this->error('No se encontraron OFEs configurados para el proceso de Recepción - Procesar Correos en la base de datos [' . $user->getBaseDatos->bdd_nombre . ']');
                continue;
            }

            foreach($ofes as $ofe) {
                if (empty($ofe->ofe_recepcion_fnc_configuracion)) {
                    $this->error('El OFE [' . $ofe->ofe_identificacion . '] de la base de datos [' . $user->getBaseDatos->bdd_nombre . '] no tiene configurada la información para el proceso de Recepción - Procesar Correos');
                    continue;
                }

                $this->rutas = json_decode($ofe->ofe_recepcion_fnc_configuracion);

                dump($ofe->ofe_identificacion);
                dump($this->rutas->username);

                // Verificando ruta principal donde se cargan los anexos, debe existir ya que no es creada por el comando
                if (!File::exists($this->rutas->directorio)) {
                    $this->error('No existe en el disco la ruta base del directorio para procesamiento de correos de la base de datos [' . $user->getBaseDatos->bdd_nombre . '] y OFE [' . $ofe->ofe_identificacion . ']');
                    continue;
                }

                // Obtiene fecha y hora del sistema para utlizarlos en el UUID del lote
                $dateObj = \DateTime::createFromFormat('U.u', microtime(TRUE));
                $msg = $dateObj->format('u');
                $msg /= 1000;
                $dateObj->setTimeZone(new \DateTimeZone('America/Bogota'));
                $dateTime = $dateObj->format('YmdHis') . intval($msg);

                $uuidLote               = Uuid::uuid4();
                $this->lote             = $dateTime . '_' . $uuidLote->toString();
                $this->discoAnexos      = 'documentos_anexos_recepcion';
                $this->discoCorreos     = $user->getBaseDatos->bdd_nombre;
                $this->pathDirPrincipal = $this->rutas->directorio . $user->getBaseDatos->bdd_nombre . '/' . $ofe->ofe_identificacion;
                DiTrait::crearDiscoDinamico($this->discoCorreos, $this->pathDirPrincipal);

                
                // Lee todos los directorios en la entrada de correos del proceso
                $directoriosCorreosEntrada = Storage::disk($this->discoCorreos)
                    ->directories($this->rutas->entrada);

                $contador = 0;
                $this->parserXml = new ParserXml($this->lote);
                // foreach($directoriosCorreosEntrada as $keyDir => $directorio) {
                for($keyDir = 0; $keyDir < count($directoriosCorreosEntrada); $keyDir++) {
                    try {
                        $directorio = $directoriosCorreosEntrada[$keyDir];
                        dump('==============');
                        dump( $keyDir . '~' . $directorio);
                        if(strstr($directorio, 'eliminar') === false && File::exists($this->pathDirPrincipal . '/' . $directorio)) {
                            if(strstr($directorio, 'procesado') !== false) {
                                $key             = array_search($directorio, $directoriosCorreosEntrada);
                                $fechaReferencia = Carbon::now()->subMinutes(10);
                                $fechaDirectorio = Carbon::createFromTimestamp(filemtime($this->pathDirPrincipal . '/' . $directorio));

                                if($fechaDirectorio->lte($fechaReferencia)){
                                    rename(
                                        $this->pathDirPrincipal . '/' . $directorio,
                                        $this->pathDirPrincipal . '/' . str_replace('.procesado', '', $directorio)
                                    );

                                    unset($directoriosCorreosEntrada[$key]);
                                }
                            } else {
                                $eliminar = false;
                                if(($key = array_search($directorio . '.procesado', $directoriosCorreosEntrada)) !== false) {
                                    rename(
                                        $this->pathDirPrincipal . '/' . $directorio . '.procesado',
                                        $this->pathDirPrincipal . '/' . $directorio . '.eliminar'
                                    );
                                    
                                    // Eliminando del array el directorio .procesado
                                    $directoriosCorreosEntrada[$key] = $directorio . '.eliminar';
                                    $eliminar = true;
                                }

                                // Si existia un directorio con el mismo nombre y la extension .procesado
                                // Se elimina la extensión .eliminar, y no se renombra la carpeta a .procesado
                                // porque el sistema esta demorando en tomar el cambio del nombre y no procesa el archivo
                                // se deja sin .procesado para que se ejecute en la siguiente frecuencia
                                if ($eliminar) {
                                    File::deleteDirectory($this->pathDirPrincipal . '/' . $directorio . '.eliminar');
                                } else {
                                    rename(
                                        $this->pathDirPrincipal . '/' . $directorio,
                                        $this->pathDirPrincipal . '/' . $directorio . '.procesado'
                                    );

                                    //Se renombra por si se genera una excepcion tenga el nombre del directorio despues de renombrado
                                    $directorio = $directorio . '.procesado';
                                    $contador++;

                                    $this->arrArchivosZipEliminar  = [];
                                    $this->zipDescomprimidoTotal   = true;
                                    $this->arrErroresPorDirectorio = [];
                                    $this->arrArchivosDescartar    = [
                                        'correo_completo.txt',
                                        'cuerpo_correo.txt',
                                        'fecha.txt',
                                        'subject.txt'
                                    ];

                                    $this->arrErroresPorDirectorio = $this->procesarDirectorio(
                                        $directorio,
                                        $this->pathDirPrincipal,
                                        $this->parserXml,
                                        $this->rutas,
                                        $ofe,
                                        $this->arrArchivosDescartar,
                                        $this->zipDescomprimidoTotal,
                                        $this->arrArchivosZipEliminar,
                                        $this->arrErroresPorDirectorio,
                                        $this->discoAnexos,
                                        $this->lote,
                                        $user
                                    );
                                    
                                    if(!empty($this->arrErroresPorDirectorio)) {
                                        $this->registrarErroresProcesamiento([
                                            'pjj_tipo'                => 'RECEPCIONANEXOS',
                                            'pjj_json'                => json_encode([]),
                                            'pjj_procesado'           => 'SI',
                                            'pjj_errores'             => json_encode($this->arrErroresPorDirectorio),
                                            'age_id'                  => 0,
                                            'age_estado_proceso_json' => 'FINALIZADO',
                                            'usuario_creacion'        => $user->usu_id,
                                            'estado'                  => 'ACTIVO',
                                        ]);
                                    }
                                  
                                    if ($contador == $this->cantidadMensajesPorProceso)
                                        break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        dump($e->getMessage());
                        $documentoElectronico = '';
                        if ($e instanceof RecepcionException) {
                            $documentoElectronico = $e->getDocumento();
                        }
        
                        $arrExcepciones = [];
                        $arrExcepciones[] = [
                            'documento'           => (!is_array($documentoElectronico) && !empty($documentoElectronico)) ? $documentoElectronico : '',
                            'consecutivo'         => (is_array($documentoElectronico) && array_key_exists('cdo_consecutivo', $documentoElectronico)) ? $documentoElectronico['cdo_consecutivo'] : '',
                            'prefijo'             => (is_array($documentoElectronico) && array_key_exists('cdo_prefijo', $documentoElectronico)) ? $documentoElectronico['cdo_prefijo'] : '',
                            'errors'              => [$e->getMessage()],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s'),
                            'archivo'             => '',
                            'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                        ];
        
                        $this->registrarErroresProcesamiento([
                            'pjj_tipo'                => 'RECEPCIONANEXOS',
                            'pjj_json'                => json_encode([]),
                            'pjj_procesado'           => 'SI',
                            'pjj_errores'             => json_encode($arrExcepciones),
                            'age_id'                  => 0,
                            'age_estado_proceso_json' => 'FINALIZADO',
                            'usuario_creacion'        => $user->usu_id,
                            'estado'                  => 'ACTIVO',
                        ]);
                        
                        $this->moverDirectorio($directorio, 'fallidos', $this->pathDirPrincipal, $this->rutas, $ofe, $arrExcepciones);
                    }
                }
            }

            DB::purge('conexion01');
        }
    }
}
