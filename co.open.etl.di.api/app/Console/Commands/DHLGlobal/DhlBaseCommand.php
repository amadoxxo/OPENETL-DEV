<?php
namespace App\Console\Commands\DHLGlobal;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Log\Logger;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Models\AdoAgendamiento;
use Illuminate\Support\Facades\Storage;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\DataInputWorker\DataBuilder;
use App\Http\Modulos\DataInputWorker\DHL\ParserXmlDHL;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\DataInputWorker\Json\JsonEtlBuilder;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Comando base para la implemantacion de cargas masivas en DHL Agencia, Goblal, Zona Franca
 *
 * Class DhlBaseCommand
 * @package App\Console\Commands\DHL
 */
class DhlBaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-core-commmand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando base para el procesamiento de archivos XML desde el servicio FTP en DHL Agencia, Goblal, Zona Franca y Deposito - No se ejecuta directamente';

    /**
     * User id
     * @var string
     */
    protected $userId = 1;

    /**
     * Nit del OFE a implementar.
     *
     * @var string
     */
    protected $nitOFE = '';

    /**
     * Email del usuario que va a insertar el documento(s).
     *
     * @var string
     */
    protected $emailUsuario = '';

    /**
     * Nombre de la carpeta de empresa donde se almancenaran los archivos de modo temporal.
     *
     * @var string
     */
    protected $nombreEmpresaStorage = '';

    /**
     * Permite cambiar el modo de ejecución del comando.
     *
     * @var bool
     */
    protected $useFTP = true;

    /**
     * Cantidad de elementos a procesar.
     *
     * @var
     */
    public $total_procesar = 10;

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
        echo "Comando base para la implemantacion de cargas masivas en DHL Agencia, Goblal, Zona Franca - No se ejecuta directamente\n";
    }

    /**
     * Incluye el proceso de carga en lotes de documentos electronicos mediante XML
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe
     * @param User $user
     */
    public function procesador(ConfiguracionObligadoFacturarElectronicamente $ofe, User $user) {
        if($user) {
            $baseDatos = \App\Http\Models\AuthBaseDatos::find($user->bdd_id);
            // Valida el estado del usuario y BD activa
            if ($user->estado == 'ACTIVO' && $baseDatos) {
                // Conexión Tenant
                $dhlFtp = null;
                if ($this->useFTP) {
                    // Establece la conexión al FTP de DHLAgencia
                    $dhlFtp = ftp_connect($ofe->ofe_conexion_ftp['host']);

                    // Inicia sesión
                    $loginFtp = ftp_login($dhlFtp, $ofe->ofe_conexion_ftp['username'], $ofe->ofe_conexion_ftp['password']);

                    // Método pasivo
                    ftp_pasv($dhlFtp, true);
                }

                // Array de rutas a consultar en el servicio FTP
                $arrPaths = array(
                    ConstantsDataInput::FC => $ofe->ofe_conexion_ftp['entrada_fc'],
                    ConstantsDataInput::NC => $ofe->ofe_conexion_ftp['entrada_nc'],
                    ConstantsDataInput::ND => $ofe->ofe_conexion_ftp['entrada_nd']
                );

                // Inhabilita los errores libxml y permite capturarlos para su manipulación
                libxml_use_internal_errors(true);

                // Crea el directorio en disco para dhlagencia
                // Si el directorio existe no hay problema, no retorna error
                DiTrait::setFilesystemsInfo();
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->makeDirectory($this->nombreEmpresaStorage);

                $subJsons = [];

                $jsonEtlBuilder = new JsonEtlBuilder('');
                $N = 0;
                $paraProcesar = [];

                // Array que permite almacenar el listado de archivos descargados
                $archivosDescargados = [];

                // Itera el array de rutas para copiar al disco del
                // servidor los archivos que encuentre en el momento
                foreach ($arrPaths as $tipo_documento => $path) {
                    $rutaFTP = $path;

                    $jsonEtlBuilder->setDocType($tipo_documento);

                    // Obtiene lista de archivos
                    if ($this->useFTP)
                        $archivos = ftp_nlist($dhlFtp, $path);
                    else
                        $archivos = scandir($path);
                    // Descarta los directorios de puntos y directorios bad y build
                    $archivos = array_unique(array_values(array_diff($archivos, ['.', '..', 'bad', 'build'])));
                    // Log::info(__LINE__.': Directorio: '.$path);
                    // Log::info(__LINE__.': archivos directorio FTP');
                    // Log::info($archivos);
                    if (!empty($archivos)) {
                        // Toma una cantidad determinada de archivos para procesarlos
                        // $archivos = array_slice($archivos, 0, $this->total_procesar);
                        //Para que el proceso no se bloque se deben traer los documentos que se encuentren sin la extension .procesado
                        $contador = 0;
                        $archivosSinProcesar = array();
                        foreach ($archivos as $archivo) {
                            $extension = \File::extension($archivo);
                            if (strtolower($extension) === 'xml') {
                                $archivosSinProcesar[] = $archivo;
                                $contador++;
                            }
                            if ($contador == $this->total_procesar) {
                                break;
                            }
                        }
                        $archivos = $archivosSinProcesar;

                        foreach ($archivos as $archivo) {
                            $extension = \File::extension($archivo);
                            $nombre = \File::name($archivo);

                            // Array que almacena errores en el procesamiento de los documentos
                            $arrErrores = [];
                            $cdo_tipo = '';

                            if (strtolower($extension) === 'xml') {
                                // Log::info(__LINE__.':Entro ' .  $nombre . '.' . $extension);
                                // Borrando el archivo
                                if (Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->exists("/{$this->nombreEmpresaStorage}/" . $nombre . '.' . $extension)) {
                                    Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->delete("/{$this->nombreEmpresaStorage}/" . $nombre . '.' . $extension);
                                }
                                if (Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->exists("/{$this->nombreEmpresaStorage}/" . $nombre . '.' . $extension . '.procesado')) {
                                    Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->delete("/{$this->nombreEmpresaStorage}/" . $nombre . '.' . $extension . '.procesado');
                                }

                                try {
                                    // Renombra el documento en el FTP para que no sea procesado por otra instancia del comando
                                    if ($this->useFTP) {
                                        if (ftp_rename($dhlFtp, $path . '/' . $nombre . '.' . $extension, $path . '/' . $nombre . '.' . $extension . '.procesado')) {
                                            // Log::info(__LINE__.': Renombro en el FTP el Archivo '.$path .'/' . $nombre.'.'.$extension. ' a '.$path . '/' . $nombre . '.' . $extension . '.procesado');
                                            try {
                                                if (!Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->exists("/{$this->nombreEmpresaStorage}/"))
                                                    Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->makeDirectory("/{$this->nombreEmpresaStorage}");

                                                $ruta = storage_path("etl/descargas/{$this->nombreEmpresaStorage}");
                                                $__archivo = storage_path("etl/descargas/{$this->nombreEmpresaStorage}/" . $nombre . '.' . $extension);
                                                @unlink($__archivo);
                                                if (ftp_get($dhlFtp, $__archivo, $path . '/' . $nombre . '.' . $extension . '.procesado', FTP_BINARY)) {
                                                    // Log::info(__LINE__.': Copio a Local Archivo '.$path .'/' . $nombre.'.'.$extension);
                                                    $archivosDescargados[$nombre] = [
                                                        'rutaftp' => $ruta,
                                                        'archivo' => $__archivo,
                                                        'nombre' => $nombre . '.' . $extension,
                                                        'error' => ''
                                                    ];
                                                }
                                                chmod($__archivo, 0755);
                                            } catch (\Exception $e) {
                                                // Log::info(__LINE__.': '.$e);
                                                $arrErrores[] = 'Error al copiar el archivo ' . $path . '/' . $nombre . '.' . $extension . ' (' . $e->getMessage() . ')';
                                            }
                                        }
                                    }
                                    else {
                                        $archivosDescargados[$nombre] = [
                                            'rutaftp' => $path,
                                            'archivo' => $archivo,
                                            'nombre' => $nombre . '.' . $extension,
                                            'error' => ''
                                        ];
                                    }
                                    $documento = $archivosDescargados[$nombre];
                                    $parser = new ParserXmlDHL($this->useFTP ? $documento['archivo'] : $documento['rutaftp'] . '/' . $documento['nombre']);
                                    $parser->run($this->nitOFE);
                                    if (!$parser->hasError()) {
                                        $jsonEtlBuilder->addDocument($parser->getJsonDocumentBUilder());
                                        $N++;
                                        // Movemos el archivo a build porque se pudo procesar
                                        if ($this->useFTP) {
                                            $paraProcesar[] = [
                                                'ruta' => $rutaFTP,
                                                'archivo' => $documento['nombre'],
                                                'tipo' => $tipo_documento
                                            ];
                                        }
                                    }
                                    else {
                                        $fallas = array_unique(array_values(array_merge(array_values($arrErrores) , array_values($parser->getErrors()))));
                                        if($parser->numDocumento === '') {
                                            $arrErrores['ERROR'] = $fallas;
                                        } else {
                                            $arrErrores[$parser->numDocumento] = $fallas;
                                            $arrErrores['archivo'] = $documento['nombre'];
                                        }

                                        // Movemos el archivo a fail porque no se pudo procesar
                                        if ($this->useFTP) {
                                            try {
                                                ftp_rename($dhlFtp, $rutaFTP . '/' . $documento['nombre'] . '.procesado', $rutaFTP . '/bad/' . $documento['nombre']);
                                            } catch (\Exception $e) {
                                                // Log::info(__LINE__.': '.$e);
                                                $arrErrores[] = 'Error al copiar en la carpeta /bad/ el archivo' . $rutaFTP . '/' . $documento['nombre'] . ' (' . $e->getMessage() . ')';
                                            }
                                        }
                                    }
                                    $cdo_tipo = $parser->cdo_clasificacion;
                                    if ($cdo_tipo === '')
                                        $cdo_tipo = "NO-DETERMINADO";
                                    @unlink($this->useFTP ? $documento['archivo'] : $documento['nombre']);
                                } catch (\Exception $e) {
                                    // Log::info(__LINE__.': '.$e);
                                    $arrErrores[] = 'Error al renombrar el archivo ' . $path . '/' . $nombre . '.' . $extension . ' (' . $e->getMessage() . ')';
                                }
                            }

                            if(!empty($arrErrores)) {
                                // Registrando los errors en la lista de procesamiento de JSON para notificar al usuario mediante la interfaz WEB
                                EtlProcesamientoJson::create([
                                    'pjj_tipo'                  => $cdo_tipo,
                                    'pjj_json'                  => '{}',
                                    'pjj_procesado'             => 'SI',
                                    'pjj_errores'               => json_encode($arrErrores),
                                    'age_id'                    => 0,
                                    'age_estado_proceso_json'   => 'FINALIZADO',
                                    'usuario_creacion'          => $user->usu_id,
                                    'estado'                    => 'ACTIVO'
                                ]);
                            }

                            /*if ($extension == 'procesado') {
                                if ($this->useFTP) {
                                    $ultimaModificacion = ftp_mdtm($dhlFtp, $path . '/' . $nombre . '.' . $extension);
                                    $tiempoActual = strtotime('-300 seconds');
                                }
                                // logger(__LINE__.': ' . $path .'/' . $nombre.'.'.$extension . '~' . $ultimaModificacion);
                                // logger(__LINE__.': ' . $tiempoActual);
                                if ($ultimaModificacion < $tiempoActual) {
                                    try {
                                        // Renombra el documento en el FTP para que sea nuevamente procesado
                                        if ($this->useFTP)
                                            ftp_rename($dhlFtp, $path . '/' . $nombre . '.' . $extension, $path . '/' . $nombre);
                                    } catch (\Exception $e) {
                                        // logger(__LINE__.': Error al renombrar el archivo ' . $path .'/' . $nombre.'.'.$extension . ' (' . $e->getMessage() . ')');
                                        $procesar = false;
                                        $arrErrores[] = 'Error al renombrar el archivo ' . $path . '/' . $nombre . '.' . $extension . ' (' . $e->getMessage() . ')';
                                    }
                                }
                            }*/
                        }

                    }
                }
                // Se solicita el registro de los diferentes archivos
                if ($N > 0) {
                    try {
                        $cdo_origen = $user->usu_type == 'INTEGRACION' ? 'INTEGRACION' : 'API';
                        $json = json_decode($jsonEtlBuilder->build());
                        // Log::info(array($json));
                        $builder = new DataBuilder($user->usu_id, $json, $cdo_origen);
                        $procesado = $builder->run();
                        foreach ($procesado['documentos_procesados'] as $indice => $exitoso) {
                            if (array_key_exists($indice, $paraProcesar)) {
                                $data = $paraProcesar[$indice];
                                if ($this->useFTP) {
                                    ftp_rename($dhlFtp, $data['ruta'] . '/' . $data['archivo'] . '.procesado', $data['ruta'] . '/build/' . $data['archivo']);
                                }
                            }
                        }

                        foreach ($procesado['documentos_fallidos'] as $indice => $fallido) {
                            if (array_key_exists($indice, $paraProcesar)) {
                                $data = $paraProcesar[$indice];
                                if ($this->useFTP)
                                    ftp_rename($dhlFtp, $data['ruta'] . '/' . $data['archivo'] . '.procesado', $data['ruta'] . '/bad/' . $data['archivo']);
                            }
                            if (isset($data))
                                $fallido['errors']['archivo'] = $data['archivo'];

                            EtlProcesamientoJson::create([
                                'pjj_tipo'                  => 'EDI',
                                'pjj_json'                  => '{}',
                                'pjj_procesado'             => 'SI',
                                'pjj_errores'               => json_encode([$fallido['documento'] => array_values($fallido['errors'])]),
                                'age_id'                    => 0,
                                'age_estado_proceso_json'   => 'FINALIZADO',
                                'usuario_creacion'          => $user->usu_id,
                                'estado'                    => 'ACTIVO'
                            ]);
                        }
                    } catch (\Exception $e) {
                        $error = $e->getMessage();
                    }
                }

                // Cierra la conexión al FTP
                if ($this->useFTP)
                    ftp_close($dhlFtp);
            }
        }
    }

}