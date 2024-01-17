<?php
/**
 * Comando que permite procesar documentos recibidos en recepción, proceso RPA
 */
namespace App\Console\Commands\Recepcion;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use App\Http\Models\AuthBaseDatos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Recepcion\Recepcion;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Recepcion\RecepcionException;
use App\Http\Modulos\Recepcion\ParserXml\ParserXml;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use App\Http\Modulos\Sistema\VariablesSistema\VariableSistema;
use openEtl\Tenant\Servicios\Recepcion\TenantRecepcionService;
use openEtl\Tenant\Helpers\Recepcion\TenantXmlUblExtractorHelper;
use App\Http\Modulos\Sistema\VariablesSistema\VariableSistemaTenant;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use App\Http\Modulos\Recepcion\Documentos\RepProcesarDocumentosDaop\RepProcesarDocumentoDaop;

class RecepcionProcesarArchivosRpaCommand extends Command {
    use TenantTrait, DiTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-procesar-archivos-rpa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los archivos del RPA de Recepción';

    /**
     * Cantidad máxima de archivos a procesar en cada ejecución.
     *
     * @var string
     */
    private $total_procesar = 10;

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

        // Se obtiene la variable del sistema main ID_SERVIDOR
        $idServidor = VariableSistema::select(['vsi_valor'])
            ->where('vsi_nombre', 'ID_SERVIDOR')
            ->where('estado', 'ACTIVO')
            ->first();

        // Homologación al nombre del servidor
        // Armando el usuario del sistema operativo 
        // para el directorio del RPA
        switch ($idServidor->vsi_valor) {
            case 0: 
                $servidor  = 'etlqa';
                // Si es QA se obtiene el usuario del sistema operativo
                $variableUsuarioSO = VariableSistema::select(['vsi_valor'])
                    ->where('vsi_nombre', 'USUARIO_SO')
                    ->where('estado', 'ACTIVO')
                    ->first();
                $usuarioSO = $variableUsuarioSO->vsi_valor;
                break;
            case 2: 
                $servidor  = 'etl22'; 
                $usuarioSO = 'api22etl22io';
                break;
            case 3: 
                $servidor  = 'etl33'; 
                $usuarioSO = 'api33etl33io';
                break;
            case 4: 
                $servidor  = 'etl44';
                $usuarioSO = 'api44etl44io';
                break;
            default: 
                $servidor  = 'etl' . $idServidor->vsi_valor;  
                $usuarioSO = 'api' . $idServidor->vsi_valor . 'etl' . $idServidor->vsi_valor . 'io';
                break;
        }

        $varibaleGrupoSO = VariableSistema::select(['vsi_valor'])
            ->where('vsi_nombre', 'GRUPO_SO')
            ->where('estado', 'ACTIVO')
            ->first();

        $grupoSO = $varibaleGrupoSO->vsi_valor;

        // Cambiando permisos de un directorio mal creado
        // $this->asignarPermisos('/var/www/vhosts/apiqa.etlqa.open-eb.io/rpa/etl/recepcion/', $usuarioSO, $grupoSO, 0755);
        // $this->asignarPermisos('/var/www/vhosts/soa.etl5.open-eb.io/rpa/etl/recepcion/1/', $usuarioSO, $grupoSO, 0755);
        // $this->asignarPermisos('/var/www/vhosts/soa.etl5.open-eb.io/rpa/etl/recepcion/146/', $usuarioSO, $grupoSO, 0755);
        // die();

        // Ubica los usuarios del proceso de recepción RPA para autenticarlos y poder acceder a los modelos tenant
        // Por cada base de datos hay un usuario, sin importar cuantos OFE existan en esa base de datos
        // Se recorren todos los OFE y se traen por cada OFE tantos archivos como indique 
        // la variable $total_procesar
        $usuarios = User::where('usu_type', 'INTEGRACION')
            ->where('usu_email', 'like', 'recepcion.%')
            ->where('usu_email', 'like', '%@open.io')
            ->where('estado', 'ACTIVO')
            ->get();

        // Se realiza el proceso para cada base de datos
        foreach($usuarios as $user) {
            // Base de datos
            $db = (!empty($user->bdd_id_rg)) ? $user->bdd_id_rg : $user->bdd_id;

            // Se consultan las bases de datos
            $baseDatos = AuthBaseDatos::where('bdd_id', $user->bdd_id)
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$baseDatos)
                continue;

            // Autentica el usuario
            auth()->login($user);

            dump('==========================');
            dump($user->usu_email);

            // Se establece una conexión dinámica a la BD
            $this->reconectarDB($baseDatos);

            // Buscando los OFE que corresponden a la base de datos del usuario autenticado y si tienen el módulo de recepción activo
            $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
                ->where('ofe_recepcion', 'SI')
                ->where('estado', 'ACTIVO')
                ->validarAsociacionBaseDatos()
                ->get();

            if (!$ofes->isEmpty()) {
                DB::purge('conexion01');
                TenantTrait::GetVariablesSistemaTenant();
                $configRuta = config('variables_sistema_tenant.RECEPCION_RUTA_ARCHIVOS');
                $ruta       = json_decode($configRuta);

                // Verificando ruta principal donde se cargan los archivos por base de datos y ofe
                // Esta ruta principal debe existir, no la crea el comando
                if (!$configRuta || !File::exists($ruta->directorio)) {
                    $this->reconectarDB($baseDatos);
                    continue;
                }

                // Define el nombre del disco
                $nombreDisco    = 'sftp_' . $baseDatos->bdd_nombre;
                // Ruta del directorio
                $rutaDirectorio = $ruta->directorio . $db;
                // Crea una instancia temporal del Storage hacia la ruta, lo que permitirá utilizar los métodos del Storage
                DiTrait::crearDiscoDinamico($nombreDisco, $rutaDirectorio . '/');
                foreach ($ofes as $ofe) {
                    $this->reconectarDB($baseDatos);

                    // Limpiando array de errores
                    $arrErrores = [];

                    // Se concatena la identificación del ofe a las rutas de la variable del sistema RECEPCION_RUTA_ARCHIVOS
                    $rutaEntrada  = $ofe->ofe_identificacion . '/' . $ruta->entrada;
                    $rutaExitosos = $ofe->ofe_identificacion . '/' . $ruta->exitosos;
                    $rutaFallidos = $ofe->ofe_identificacion . '/' . $ruta->fallidos;

                    // Variable que indica que se creo algun directorio
                    $creado = false;

                    // Creando el directorio de entrada si no existe
                    if(!File::exists($rutaDirectorio . '/' . $rutaEntrada))  {
                        File::makeDirectory($rutaDirectorio . '/' . $rutaEntrada, 0755, true);
                        $creado = true;
                    }
                    // Creando el directorio de exitosos si no existe
                    if (!File::exists($rutaDirectorio . '/' . $rutaExitosos)) {
                        File::makeDirectory($rutaDirectorio . '/' . $rutaExitosos, 0755, true);
                        $creado = true;
                    }
                    // Creando el directorio de fallidos si no existe
                    if(!File::exists($rutaDirectorio . '/' . $rutaFallidos)) {
                        File::makeDirectory($rutaDirectorio . '/' . $rutaFallidos, 0755, true);
                        $creado = true;
                    }
                    // Asigna los permisos según la configuración del usuarioSO y grupoSO
                    if ($creado == true) {
                        $this->asignarPermisos($rutaDirectorio . '/', $usuarioSO, $grupoSO, 0755);
                    }

                    $archivos = Storage::disk($nombreDisco)->files($rutaEntrada);
                    if (empty($archivos))
                        continue;

                    // Para que el proceso no se bloque se deben traer los documentos que se encuentren sin la extensión .procesado
                    $contador = 0;
                    $archivosSinProcesar   = [];
                    $archivosExcelProcesar = [];
                    foreach ($archivos as $archivo) {
                        $extension = File::extension($archivo);
                        $nombreZip = File::name($archivo);

                        if (strtolower($extension) === 'xlsx') {
                            $archivosExcelProcesar[] = $archivo;
                            continue;
                        }

                        // El nombre del archivo esta conformado por nombre_servidor~id_db~14_digitos_cufe~timestamp.zip
                        $infoNombreArchivo = array_map('trim', explode('~', $nombreZip));

                        // Solo se procesan los archivos .xlsx, .zip y .procesado
                        if (strtolower($extension) === 'xlsx' || strtolower($extension) === 'zip' || strtolower($extension) === 'procesado') {
                            // Se valida si el archivo corresponde al servidor y base de datos del usuario autenticado
                            if ($infoNombreArchivo[0] == $servidor && $infoNombreArchivo[1] == $db) {
                                if (strtolower($extension) === 'zip') {
                                    $archivosSinProcesar[] = $archivo;
                                    $contador++;
                                } elseif (strtolower($extension) === 'procesado') {
                                    $ultimaModificacion = Storage::disk($nombreDisco)->lastModified($archivo);
                                    $tiempoActual       = strtotime('-300 seconds'); // 5 minutos

                                    if ($ultimaModificacion < $tiempoActual) {
                                        // Renombra el archivo zip para quitarle la extensión .procesado
                                        Storage::disk($nombreDisco)->rename($archivo, $rutaEntrada . str_replace('.procesado', '', $nombreZip));

                                        // Elimina el directorio temporal
                                        if(stristr($nombreZip, '.zip'))
                                            Storage::disk($nombreDisco)->deleteDirectory($rutaEntrada . str_replace('.zip.procesado', '', $nombreZip));

                                        $archivosSinProcesar[] = $archivo;
                                        $contador++;
                                    }
                                }
                            } else {
                                dump('Documento no procesado: ' . $archivo);

                                // Limpiando array de errores
                                $arrErrores   = [];
                                $arrErrores[] = [
                                    'documento'           => $nombreZip,
                                    'consecutivo'         => '',
                                    'prefijo'             => '',
                                    'errors'              => ['El Archivo Zip no corresponde al servidor y base de datos del OFE [' . $ofe->ofe_identificacion . ']'],
                                    'fecha_procesamiento' => date('Y-m-d'),
                                    'hora_procesamiento'  => date('H:i:s'),
                                    'archivo'             => $nombreZip.'.'.$extension,
                                    'traza'               => ''
                                ];

                                // Renombra el documento para quitarle la extensión .procesado y moverlo al directorio de fallidos
                                $this->moverArchivo($nombreDisco, $archivo, $rutaFallidos . $nombreZip . '.' . $extension);

                                // Elimina el directorio temporal
                                Storage::disk($nombreDisco)->deleteDirectory($rutaEntrada . $nombreZip);

                                // Registra el error en la BD
                                $this->registraProcesamientoJson([
                                    'pjj_tipo'                => 'RPA',
                                    'pjj_json'                => json_encode([]),
                                    'pjj_procesado'           => 'SI',
                                    'pjj_errores'             => json_encode($arrErrores),
                                    'age_id'                  => 0,
                                    'age_estado_proceso_json' => 'FINALIZADO',
                                    'usuario_creacion'        => $user->usu_id,
                                    'estado'                  => 'ACTIVO'
                                ]);
                            }

                            if ($contador == config('variables_sistema_tenant.RECEPCION_CANTIDAD_DOCUMENTOS_RPA')) {
                                break;
                            }
                        }
                    }

                    $arrInfoPrincipal = [
                        'ofe_identificacion' => $ofe->ofe_identificacion,
                        'pro_identificacion' => '',
                        'usu_identificacion' => $user->usu_identificacion,
                        'lote_procesamiento' => '',
                        'origen'             => 'RPA'
                    ];
                    
                    $contDocumentos        = 1;
                    $totalProcesados       = 1;
                    $archivosTamanoGrande  = 0;
                    $arrDocumentosProcesar = [];

                    // Permite almacenar los archivos procesados exitosos o fallidos dependiendo si el agendamiento fue o no creado para poder moverlos posteriormente
                    $arrArchivosMover      = [
                        'exitosos' => [],
                        'fallidos' => []
                    ];

                    // La diferencia de este array respecto del anterior, es que este array se reinicializa vacio con cada bloque de documentos agendados conforme a la columna bdd_cantidad_procesamiento_rdi
                    // y no tiene en cuenta si el agendamiento fue creado o no, porque en el punto en donde se agregan los documentos aún no se tiene ese dato
                    $arrTmpArchivosMover   = [];
                    foreach ($archivosSinProcesar as $archivo) {
                        $this->reconectarDB($baseDatos);

                        $extension         = File::extension($archivo);
                        $nombreZip         = File::name($archivo);
                        $fechaArchivo      = Carbon::createFromTimestamp(Storage::disk($nombreDisco)->lastModified($archivo));
                        $tamanoArchivoZip  = Storage::disk($nombreDisco)->size($archivo);
                        $infoNombreArchivo = array_map('trim', explode('~', $nombreZip));

                        if (strtolower($extension) === 'zip' && Storage::disk($nombreDisco)->exists($archivo) && $tamanoArchivoZip > 0 && $fechaArchivo->lte(Carbon::now()->subMinutes(1))) {
                            try {
                                dump('Documento: ' . $archivo);
                                // Renombra el documento para que no sea procesado por otra instancia del comando
                                Storage::disk($nombreDisco)->rename($archivo, $archivo . '.procesado');

                                // Descomprime el zip a un directorio temporal con el mismo nombre del zip, este directorio será eliminado una vez el archivo sea procesado
                                $dirTemp = $rutaDirectorio . '/' . $rutaEntrada . $nombreZip;
                                $this->unzip($dirTemp, $rutaDirectorio . '/' . $archivo . '.procesado');

                                // Verifica que existan archivos en la ruta de descompresión
                                $archivosDocumento = Storage::disk($nombreDisco)->files($rutaEntrada . $nombreZip);
                                if(empty($archivosDocumento)) {
                                    //Limpiando array de errores
                                    $arrErrores   = [];
                                    $arrErrores[] = [
                                        'documento'           => $infoNombreArchivo[2],
                                        'consecutivo'         => '',
                                        'prefijo'             => '',
                                        'errors'              => ['No se encontraron archivos al descomprimir el fichero'],
                                        'fecha_procesamiento' => date('Y-m-d'),
                                        'hora_procesamiento'  => date('H:i:s'),
                                        'archivo'             => $nombreZip.'.'.$extension,
                                        'traza'               => ''
                                    ];

                                    // Renombra el documento para quitarle la extensión .procesado y moverlo al directorio de fallidos
                                    $this->llenarArrayMoverArchivos($arrArchivosMover, 'fallidos', $archivo . '.procesado', $nombreZip . '.' . $extension, $rutaFallidos);

                                    // Elimina el directorio temporal
                                    Storage::disk($nombreDisco)->deleteDirectory($rutaEntrada . $nombreZip);

                                    // Registra el error en la BD
                                    $this->registraProcesamientoJson([
                                        'pjj_tipo'                => 'RPA',
                                        'pjj_json'                => json_encode([]),
                                        'pjj_procesado'           => 'SI',
                                        'pjj_errores'             => json_encode($arrErrores),
                                        'age_id'                  => 0,
                                        'age_estado_proceso_json' => 'FINALIZADO',
                                        'usuario_creacion'        => $user->usu_id,
                                        'estado'                  => 'ACTIVO'
                                    ]);
                                    continue;
                                }

                                // Lee el contenido del archivo xml para iniciar su procesamiento
                                $archivoPdf = null;
                                $archivoXml = null;
                                $nombreXml  = null;
                                foreach($archivosDocumento as $archivoPdfXml) {
                                    if(File::extension($archivoPdfXml) == 'xml' && $archivoXml == null) {
                                        $archivoXml = $archivoPdfXml;
                                        $nombreXml  = str_replace('xml_', '', File::name($archivoPdfXml));
                                        $tamanoXml  = Storage::disk($nombreDisco)->size($archivoPdfXml);
                                    } elseif(File::extension($archivoPdfXml) == 'pdf' && $archivoPdf == null) {
                                        $archivoPdf = $archivoPdfXml;
                                    }
                                }

                                if($archivoPdf == null || $archivoXml == null) 
                                    throw new \Exception('El zip no contenía el archivo xml y/o el archivo pdf');

                                if($tamanoArchivoZip > 3145728 || $tamanoXml > 3145728) {
                                    $arrArchivoGrande = $arrInfoPrincipal;
                                    $arrArchivoGrande['lote_procesamiento'] = Recepcion::buildLote();
                                    $arrArchivoGrande['documentos']         = [];
                                    $arrArchivoGrande['documentos'][]       = [
                                        'nombre' => $nombreXml,
                                        'xml'    => base64_encode(Storage::disk($nombreDisco)->get($archivoXml)),
                                        'pdf'    => base64_encode(Storage::disk($nombreDisco)->get($archivoPdf)),
                                    ];

                                    $agendamientoCreado = Recepcion::crearAgendamientoRdi($user, $arrArchivoGrande, 1);

                                    if($agendamientoCreado)
                                        $this->llenarArrayMoverArchivos($arrArchivosMover, 'exitosos', $archivo . '.procesado', $nombreZip . '.' . $extension, $rutaExitosos);
                                    else
                                        $this->llenarArrayMoverArchivos($arrArchivosMover, 'fallidos', $archivo . '.procesado', $nombreZip . '.' . $extension, $rutaFallidos);

                                    $archivosTamanoGrande++;
                                } else {
                                    $arrDocumentosProcesar['documentos'][] = [
                                        'nombre' => $nombreXml,
                                        'xml'    => base64_encode(Storage::disk($nombreDisco)->get($archivoXml)),
                                        'pdf'    => base64_encode(Storage::disk($nombreDisco)->get($archivoPdf)),
                                    ];

                                    if($contDocumentos < $baseDatos->bdd_cantidad_procesamiento_rdi && $totalProcesados < (count($archivosSinProcesar) - $archivosTamanoGrande)) {
                                        $arrTmpArchivosMover[] = [
                                            'origen'  => $archivo . '.procesado',
                                            'destino' => $nombreZip . '.' . $extension
                                        ];

                                        $contDocumentos++;
                                    } else {
                                        $arrFinal = array_merge($arrInfoPrincipal, $arrDocumentosProcesar);
                                        $arrFinal['lote_procesamiento'] = Recepcion::buildLote();

                                        $agendamientoCreado = Recepcion::crearAgendamientoRdi($user, $arrFinal, $contDocumentos);

                                        foreach($arrTmpArchivosMover as $archivoMover) {
                                            if($agendamientoCreado)
                                                $this->llenarArrayMoverArchivos($arrArchivosMover, 'exitosos', $archivoMover['origen'], $archivoMover['destino'], $rutaExitosos);
                                            else
                                                $this->llenarArrayMoverArchivos($arrArchivosMover, 'fallidos', $archivoMover['origen'], $archivoMover['destino'], $rutaFallidos);
                                        }

                                        // El archivo con el cual se llega a este punto de procesamiento no fue incluido en $arrTmpArchivosMover
                                        if($agendamientoCreado)
                                            $this->llenarArrayMoverArchivos($arrArchivosMover, 'exitosos', $archivo . '.procesado', $nombreZip . '.' . $extension, $rutaExitosos);
                                        else
                                            $this->llenarArrayMoverArchivos($arrArchivosMover, 'fallidos', $archivo . '.procesado', $nombreZip . '.' . $extension, $rutaFallidos);

                                        $contDocumentos        = 1;
                                        $arrDocumentosProcesar = [];
                                        $arrTmpArchivosMover   = [];
                                    }

                                    $totalProcesados++;
                                }

                                // Elimina el directorio temporal
                                Storage::disk($nombreDisco)->deleteDirectory($rutaEntrada . $nombreZip);
                            } catch (\Exception $e) {
                                if ($e instanceof RecepcionException)
                                    $documentoElectronico = $e->getDocumento();
                                else
                                    $documentoElectronico = [];

                                $arrExcepciones = [];
                                $arrExcepciones[] = [
                                    'documento'           => $nombreZip.'.'.$extension,
                                    'consecutivo'         => (is_array($documentoElectronico) && array_key_exists('cdo_consecutivo', $documentoElectronico)) ? $documentoElectronico['cdo_consecutivo'] : '',
                                    'prefijo'             => (is_array($documentoElectronico) && array_key_exists('cdo_prefijo', $documentoElectronico)) ? $documentoElectronico['cdo_prefijo'] : '',
                                    'errors'              => [$e->getMessage()],
                                    'fecha_procesamiento' => date('Y-m-d'),
                                    'hora_procesamiento'  => date('H:i:s'),
                                    'archivo'             => $nombreZip.'.'.$extension,
                                    'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                                ];

                                $this->registraProcesamientoJson([
                                    'pjj_tipo'                => 'RDI',
                                    'pjj_json'                => json_encode([]),
                                    'pjj_procesado'           => 'SI',
                                    'pjj_errores'             => json_encode($arrExcepciones),
                                    'age_id'                  => 0,
                                    'age_estado_proceso_json' => 'FINALIZADO',
                                    'usuario_creacion'        => $user->usu_id,
                                    'estado'                  => 'ACTIVO'
                                ]);

                                $arrArchivosMover['fallidos'][] = [
                                    'origen'  => $archivo . '.procesado',
                                    'destino' => $rutaFallidos . $nombreZip . '.' . $extension
                                ];

                                // Elimina el directorio temporal
                                Storage::disk($nombreDisco)->deleteDirectory($rutaEntrada . $nombreZip);
                            }
                        }
                    }

                    foreach($arrArchivosMover['exitosos'] as $archivoMover)
                        $this->moverArchivo($nombreDisco, $archivoMover['origen'], $archivoMover['destino']);

                    foreach($arrArchivosMover['fallidos'] as $archivoMover)
                        $this->moverArchivo($nombreDisco, $archivoMover['origen'], $archivoMover['destino']);

                    foreach ($archivosExcelProcesar as $archivo) {
                        $this->reconectarDB($baseDatos);

                        $extension      = File::extension($archivo);
                        $nombreExcel    = File::name($archivo);
                        $fechaArchivo   = Carbon::createFromTimestamp(Storage::disk($nombreDisco)->lastModified($archivo));
                        $tamanoArchivo  = Storage::disk($nombreDisco)->size($archivo);

                        if (strtolower($extension) === 'xlsx' && Storage::disk($nombreDisco)->exists($archivo) && $tamanoArchivo > 0 && $fechaArchivo->lte(Carbon::now()->subMinutes(1))) {
                            try {
                                dump('Documento: ' . $archivo);
                                // Renombra el documento para que no sea procesado por otra instancia del comando
                                Storage::disk($nombreDisco)->rename($archivo, $archivo . '.procesado');
                                $storagePath = Storage::disk($nombreDisco)->getDriver()->getAdapter()->getPathPrefix();

                                $reader = ReaderEntityFactory::createXLSXReader(); // for XLSX files
                                $reader->open($storagePath . $archivo . '.procesado');
                                $registros = [];
                                foreach ($reader->getSheetIterator() as $sheet) {
                                    foreach ($sheet->getRowIterator() as $row) {
                                        $registros[] = $row->toArray();
                                    }
                                }
                                $reader->close();

                                if (!empty($registros)) {
                                    foreach($registros as $registro) {
                                        if($registro[1] == 'NO') {
                                            $this->crearProcesarDocumentoDaop($user->usu_id, $ofe->ofe_id, $registro[0]);
                                        }
                                    }
                                }

                                // Renombra el documento para quitarle la extensión .procesado y moverlo al directorio de fallidos
                                $this->moverArchivo($nombreDisco, $archivo . '.procesado', $rutaExitosos . '/' . $nombreExcel . '.' . $extension);
                            } catch (\Exception $e) {
                                $arrExcepciones = [];
                                $arrExcepciones[] = [
                                    'documento'           => $nombreExcel.'.'.$extension,
                                    'consecutivo'         => '',
                                    'prefijo'             => '',
                                    'errors'              => [$e->getMessage()],
                                    'fecha_procesamiento' => date('Y-m-d'),
                                    'hora_procesamiento'  => date('H:i:s'),
                                    'archivo'             => $nombreExcel.'.'.$extension,
                                    'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                                ];

                                $this->registraProcesamientoJson([
                                    'pjj_tipo'                => 'RDI',
                                    'pjj_json'                => json_encode([]),
                                    'pjj_procesado'           => 'SI',
                                    'pjj_errores'             => json_encode($arrExcepciones),
                                    'age_id'                  => 0,
                                    'age_estado_proceso_json' => 'FINALIZADO',
                                    'usuario_creacion'        => $user->usu_id,
                                    'estado'                  => 'ACTIVO'
                                ]);

                                // Renombra el documento para quitarle la extensión .procesado y moverlo al directorio de fallidos
                                $this->moverArchivo($nombreDisco, $archivo . '.procesado', $rutaFallidos . '/' . $nombreExcel . '.' . $extension);
                            }
                        }
                    }
                }
            }
            DB::purge('conexion01');
        }
    }

    /**
     * Asigna permiso a los directorios y archivos de un directorio.
     *
     * @param string $ruta      Ruta del directorio
     * @param string $usuarioSo Usuario sistema operativo asignar al directorio
     * @param string $grupoSO   Grupo sistema operativo asignar al directorio
     * @param string $permiso   Permiso asignar al directorio
     * @return void
     */
    private function asignarPermisos(string $ruta, string $usuarioSo, string $grupoSO, string $permiso){
        // Abrir un directorio y listarlo recursivo
        if (is_dir($ruta)) {
            chown($ruta, $usuarioSo);
            chgrp($ruta, $grupoSO);
            chmod($ruta, $permiso);
            if ($dh = opendir($ruta)) {
                while (($file = readdir($dh)) !== false) {
                    chown($ruta . $file, $usuarioSo);
                    chgrp($ruta . $file, $grupoSO);
                    chmod($ruta . $file, $permiso);
                    // Mostraría tanto archivos como directorios
                    if (is_dir($ruta . $file) && $file!="." && $file!="..") {
                        // Solo si el archivo es un directorio, distinto que "." y ".."
                        $this->asignarPermisos($ruta . $file . '/', $usuarioSo, $grupoSO, $permiso);
                    }
                }
                closedir($dh);
            }
        } else {
            // Si es un archivo se asignan los permisos
            if (is_file($ruta)) {
                chown($ruta, $usuarioSo);
                chgrp($ruta, $grupoSO); 
                chmod($ruta, $permiso);
            }
        } 
    }

    /**
     * Realiza el registro de un procesamiento json en el modelo correspondiente.
     *
     * @param array $valores Array que contiene la información de los campos para la creación del registro
     * @return void
     */
    private function registraProcesamientoJson(array $valores){
        if (!empty($valores['pjj_errores']))
            dump($valores['pjj_errores']);

        EtlProcesamientoJson::create([
            'pjj_tipo'                => $valores['pjj_tipo'],
            'pjj_json'                => $valores['pjj_json'],
            'pjj_procesado'           => $valores['pjj_procesado'],
            'pjj_errores'             => $valores['pjj_errores'],
            'age_id'                  => $valores['age_id'],
            'age_estado_proceso_json' => $valores['age_estado_proceso_json'],
            'usuario_creacion'        => $valores['usuario_creacion'],
            'estado'                  => $valores['estado']
        ]);
    } 

    /**
     * Descomprime un archivo zip.
     *
     * @param string $dir Ruta del directorio temporal de decompresión
     * @param string $rutaZip Ruta del archivo zip a descomprimir
     * @return void
     */
    private function unzip(string $dir, string $rutaZip) {
        $zip = new \ZipArchive;
        $res = $zip->open($rutaZip);
        $zip->extractTo($dir);
        $zip->close();
    }

    /**
     * Mueve un archivo de una ubicación a otra.
     *
     * @param string $disco Nombre del disco
     * @param string $origen Path relativo del archivo origen
     * @param string $destino Path relativo del archivo destino
     * @return void
     */
    private function moverArchivo(string $disco, string $origen, string $destino) {
        if (Storage::disk($disco)->exists($destino)) {
            Storage::disk($disco)->delete($destino);
        }
        if (Storage::disk($disco)->exists($origen)) {
            Storage::disk($disco)->move($origen, $destino);
        }
    }

    /**
     * Crea un registro en el modelo RepProcesarDocumentoDaop.
     *
     * @param integer $usu_id ID del usuario autenticado
     * @param integer $ofe_id ID del OFE en procesamiento
     * @param string $registro Array conteniendo la información del cufe a registrar ara posterior procesamiento
     * @return void
     */
    private function crearProcesarDocumentoDaop(int $usu_id, int $ofe_id, string $cufe): void {
        RepProcesarDocumentoDaop::create([
            'ofe_id'           => $ofe_id,
            'cdo_cufe'         => $cufe,
            'usuario_creacion' => $usu_id,
            'estado'           => 'ACTIVO'
        ]);
    }

    /**
     * Agrega nuevos elementos al array que permite mover los archivos, teniendo en cuenta los parámetros recibidos.
     *
     * @param array $arrArchivosMover Array de archivos a mover
     * @param string $indiceArray Índice del array en donde debe agregarse la información (exitosos o fallidos)
     * @param string $origen Origen o nombre original del archivo
     * @param string $destino Destino o nombre con el que quedará el archivo
     * @param string $rutaDestino Ruta de exitoso o fallidos a la cual se moverá un archivo
     * @return void
     */
    private function llenarArrayMoverArchivos(array &$arrArchivosMover, string $indiceArray, string $origen, string $destino, string $rutaDestino): void {
        $arrArchivosMover[$indiceArray][] = [
            'origen'  => $origen,
            'destino' => $rutaDestino . $destino
        ];
    }
}
