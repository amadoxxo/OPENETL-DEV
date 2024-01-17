<?php
namespace App\Console\Commands\DHLGlobal\DocumentosSoporte;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use App\Http\Models\AuthBaseDatos;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\DataInputWorker\Json\JsonEtlBuilder;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\DataInputWorker\DHL\ParserXmlDsDhlGlobal;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Comando base para la implemantacion de cargas masivas de Documentos Soporte en DHL Agencia, Goblal, Zona Franca
 *
 * Class DhlBaseCommand
 * @package App\Console\Commands\DHL
 */
class DhlBaseDsCommand extends Command {
    use DiTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-ds-core-commmand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando base para el procesamiento de archivos XML de Documentos Soporte desde el servicio FTP en DHL Agencia, Goblal, Zona Franca y Deposito - No se ejecuta directamente';

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
     */
    public function handle()
    {
        $this->line('Comando base para el procesamiento de archivos XML de Documentos Soporte desde el servicio FTP en DHL Agencia, Goblal, Zona Franca y Deposito - No se ejecuta directamente');
    }

    /**
     * Incluye el proceso de carga en lotes de documentos electronicos mediante XML
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe
     * @param User $user
     */
    public function procesador(ConfiguracionObligadoFacturarElectronicamente $ofe, User $user) {
        if($user) {
            $baseDatos = AuthBaseDatos::find($user->bdd_id);
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
                    ConstantsDataInput::DS => $ofe->ofe_conexion_ftp['entrada_ds']
                );

                // Inhabilita los errores libxml y permite capturarlos para su manipulación
                libxml_use_internal_errors(true);

                // Crea el directorio en disco para el OFE en procesamiento
                // Si el directorio existe no hay problema, no retorna error
                DiTrait::setFilesystemsInfo();
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->makeDirectory($this->nombreEmpresaStorage);

                $N              = 0;
                $paraProcesar   = [];
                $jsonEtlBuilder = new JsonEtlBuilder('');

                // Array que permite almacenar el listado de archivos descargados
                $archivosDescargados = [];

                // Array final con resultados de procesamiento
                $procesados = [
                    'documentos_procesados' => [],
                    'documentos_fallidos'   => []
                ];

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
                    $archivos = array_unique(array_values(array_diff($archivos, [$path . '/.', $path . '/..', $path . '/bad', $path . '/build'])));
                    // Log::info(__LINE__.': Directorio: '.$path);
                    // Log::info(__LINE__.': archivos directorio FTP');
                    // Log::info($archivos);
                    if (!empty($archivos)) {
                        // Toma una cantidad determinada de archivos para procesarlos
                        // Para que el proceso no se bloque se deben traer los documentos que se encuentren sin la extension .procesado
                        $contador = 0;
                        $archivosSinProcesar = [];
                        foreach ($archivos as $archivo) {
                            $extension = File::extension($archivo);
                            if (strtolower($extension) === 'xml') {
                                $archivosSinProcesar[] = $archivo;
                                $contador++;
                            }
                            if ($contador == $this->total_procesar) {
                                break;
                            }
                        }
                        $archivos   = $archivosSinProcesar;

                        foreach ($archivos as $archivo) {
                            $nombre    = File::name($archivo);
                            $extension = File::extension($archivo);

                            // Array que almacena errores en el procesamiento de los documentos
                            $arrErrores = [];
                            $cdo_tipo   = '';

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

                                                $ruta      = storage_path("etl/descargas/{$this->nombreEmpresaStorage}");
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
                                    } else {
                                        $archivosDescargados[$nombre] = [
                                            'rutaftp' => $path,
                                            'archivo' => $archivo,
                                            'nombre'  => $nombre . '.' . $extension,
                                            'error'   => ''
                                        ];
                                    }

                                    $documento = $archivosDescargados[$nombre];
                                    $parser    = new ParserXmlDsDhlGlobal();
                                    $docJson   = $parser->parserXml(($this->useFTP ? $documento['archivo'] : $documento['rutaftp'] . '/' . $documento['nombre']), $ofe->ofe_id);
                                    $cdo_tipo  = array_key_exists('clasificacion', $docJson) ? $docJson['clasificacion'] : '';
                                    if ($cdo_tipo === '')
                                        $cdo_tipo = "DS-NO-DETERMINADO";
                                    
                                    if(array_key_exists('errors', $docJson) && !empty($docJson['errors'])) {
                                        $nomDocumento = (array_key_exists('cdo_consecutivo', $docJson['json']) ? $docJson['json']['cdo_consecutivo'] : '') . (array_key_exists('rfa_prefino', $docJson['json']) ? $docJson['json']['rfa_prefino'] : '');

                                        if(empty($nomDocumento))
                                            $nomDocumento = $nombre . '.' . $extension;

                                        $errors[] = [
                                            'documento'           => $cdo_tipo . '-' . $nomDocumento,
                                            'consecutivo'         => array_key_exists('cdo_consecutivo', $docJson['json']) ? $docJson['json']['cdo_consecutivo'] : '',
                                            'prefijo'             => array_key_exists('rfa_prefino', $docJson['json']) ? $docJson['json']['rfa_prefino'] : '',
                                            'errors'              => $docJson['errors'],
                                            'fecha_procesamiento' => date('Y-m-d'),
                                            'hora_procesamiento'  => date('H:i:s')
                                        ];

                                        // Registrando los errors en la lista de procesamiento de JSON para notificar al usuario mediante la interfaz WEB
                                        EtlProcesamientoJson::create([
                                            'pjj_tipo'                  => 'EDI',
                                            'pjj_json'                  => '{}',
                                            'pjj_procesado'             => 'SI',
                                            'pjj_errores'               => json_encode($errors),
                                            'age_id'                    => 0,
                                            'age_estado_proceso_json'   => 'FINALIZADO',
                                            'usuario_creacion'          => $user->usu_id,
                                            'estado'                    => 'ACTIVO'
                                        ]);

                                        if ($this->useFTP) {
                                            try {
                                                ftp_rename($dhlFtp, $rutaFTP . '/' . $documento['nombre'] . '.procesado', $rutaFTP . '/bad/' . $documento['nombre']);
                                            } catch (\Exception $e) {
                                                // Log::info(__LINE__.': '.$e);
                                                $arrErrores[] = 'Error al copiar en la carpeta /bad/ el archivo' . $rutaFTP . '/' . $documento['nombre'] . ' (' . $e->getMessage() . ')';
                                            }
                                        }
                                    } else { // Sin errores, se procesa el documento a través del método de creación de documentos
                                        $documentoProcesar = json_decode(json_encode([
                                            'documentos' => [
                                                $docJson['clasificacion'] => [$docJson['json']]
                                            ]
                                        ]));

                                        $request  = new Request();
                                        $request->merge(['retornar_array' => true]);
                                        $json     = json_decode(json_encode(['documentos' => $documentoProcesar->documentos]));
                                        $registro = $this->registrarDocumentosEmision($request, $json, $docJson['json']['cdo_origen']);

                                        if(!empty($registro['documentos_procesados']))
                                            foreach ($registro['documentos_procesados'] as $exitoso)
                                                ftp_rename($dhlFtp, $rutaFTP . '/' . $documento['nombre'] . '.procesado', $rutaFTP . '/build/' . $documento['nombre']);
                
                                        if(!empty($registro['documentos_fallidos']))
                                            foreach ($registro['documentos_fallidos'] as $fallido) {
                                                ftp_rename($dhlFtp, $rutaFTP . '/' . $documento['nombre'] . '.procesado', $rutaFTP . '/bad/' . $documento['nombre']);

                                                $fallido['errors']['archivo'] = $documento['nombre'];
                                                EtlProcesamientoJson::create([
                                                    'pjj_tipo'                 => 'EDI',
                                                    'pjj_json'                 => '{}',
                                                    'pjj_procesado'            => 'SI',
                                                    'pjj_errores'              => json_encode([$fallido]),
                                                    'age_id'                   => 0,
                                                    'age_estado_proceso_json'  => 'FINALIZADO',
                                                    'usuario_creacion'         => $user->usu_id,
                                                    'estado'                   => 'ACTIVO'
                                                ]);
                                            }
                                    }

                                    @unlink($this->useFTP ? $documento['archivo'] : $documento['nombre']);
                                } catch (\Exception $e) {
                                    // Log::info(__LINE__.': '.$e);
                                    $arrErrores[] = 'Error al procesar el archivo ' . $path . '/' . $nombre . '.' . $extension . ' (' . $e->getMessage() . ')';

                                    if(is_file(storage_path("etl/descargas/{$this->nombreEmpresaStorage}/" . $nombre . '.' . $extension)))
                                        @unlink(storage_path("etl/descargas/{$this->nombreEmpresaStorage}/" . $nombre . '.' . $extension));
                                }
                            }

                            if(!empty($arrErrores)) {
                                $nomDocumento = $nombre . '.' . $extension;

                                $errors[] = [
                                    'documento'           => $nomDocumento,
                                    'consecutivo'         => '',
                                    'prefijo'             => '',
                                    'errors'              => $arrErrores,
                                    'fecha_procesamiento' => date('Y-m-d'),
                                    'hora_procesamiento'  => date('H:i:s')
                                ];

                                // Registrando los errors en la lista de procesamiento de JSON para notificar al usuario mediante la interfaz WEB
                                EtlProcesamientoJson::create([
                                    'pjj_tipo'                  => 'EDI',
                                    'pjj_json'                  => '{}',
                                    'pjj_procesado'             => 'SI',
                                    'pjj_errores'               => json_encode($errors),
                                    'age_id'                    => 0,
                                    'age_estado_proceso_json'   => 'FINALIZADO',
                                    'usuario_creacion'          => $user->usu_id,
                                    'estado'                    => 'ACTIVO'
                                ]);
                            }
                        }
                    }
                }

                // Cierra la conexión al FTP
                if ($this->useFTP)
                    ftp_close($dhlFtp);
            }
        }
    }
}
