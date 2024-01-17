<?php
namespace App\Console\Commands\Osram;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Comando para exportar la data a ser leida por los servicios de carga de documentos de Osram (XML)
 * Class OsramPutDocumentsCommand
 * @package App\Console\Commands
 */
class OsramSalidaArchivosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osram-salida-archivos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia el archivo de salida XML de los documentos notificados para que Osram los pueda subir a su ERP.';

    /**
     * @var int Total de registros a procesar
     */
    protected $total_procesar = 10;

    /**
     * Disco de trabajo para el procesamiento de los archivos
     * @var string
     */
    private $discoTrabajo = 'ftpOsram';

    /**
     * @var string Nombre del directorio de la BD
     */
    protected $baseDatosOsram = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * @var string Nombre del directorio del NIT de Osram
     */
    protected $nitOsram = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * @var array cdo_clasificacion de los documentos que aplican.
     */
    protected $arrCdoClasificacion = ['FC','NC','ND'];

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
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $usuarios = [
            'osram@openio.co',
            'osram01@openio.co'
        ];
        
        foreach($usuarios as $usuario) { 
            // Obtiene el usuario relacionado con Osram
            $user = User::where('usu_email', $usuario)
                ->where('estado', 'ACTIVO')
                ->with(['getBaseDatos'])
                ->first();

            if($user) {
                // Establecemos conexión a la BD
                TenantDatabase::setTenantConnection(
                    'conexion01', // Nombre de la conexión
                    $user->getBaseDatos->bdd_host, // Host de la base de datos
                    $user->getBaseDatos->bdd_nombre, // Base de datos
                    $user->getBaseDatos->bdd_usuario, // Usuario de la conexión
                    $user->getBaseDatos->bdd_password // Clave de conexión
                );

                if($user) {
                    // Generación del token conforme al usuario
                    $token = auth()->login($user);
                    DoTrait::setFilesystemsInfo();

                    // Obtiene la información de configuracion del SFTP par DHL Osram
                    $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_conexion_ftp'])
                        ->where('ofe_identificacion', '900058192')
                        ->first();

                    // Obtiene los documentos de DHL Osram que cumplan con las siguientes condiciones
                    //  - Origen del documento debe ser INTEGRACION
                    //  - Los archivos de salida no hayan sido generados
                    //  - Tengan estado DO procesado de manera existosa
                    //  - Tengan estado NOTIFICACION procesado de manera existosa
                    //  - La clasificación del documento sea FC, NC o ND
                    $documentos = EtlCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion', 'ofe_id', 'adq_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha', 'cdo_fecha_archivo_salida','tde_id','cdo_fecha_validacion_dian','cdo_cufe'])
                        ->whereIn('cdo_clasificacion', $this->arrCdoClasificacion)
                        ->where('ofe_id', $ofe->ofe_id)
                        ->whereNull('cdo_fecha_archivo_salida')
                        ->whereHas('getDoDocumento')
                        ->whereHas('getNotificacionDocumento')
                        ->with([
                            'getConfiguracionAdquirente:adq_id,adq_identificacion'
                        ])
                        ->take($this->total_procesar)
                        ->get();
                    
                    foreach ($documentos as $documento) {
                        $this->enviarCarpetaSalida(
                            $documento,
                            $ofe->ofe_conexion_ftp,
                            $user->usu_id,
                            date('Y-m-d H:i:s'),
                            microtime(true)
                        );
                    }
                }
                DB::purge('conexion01');
            }
        }
    }

    /**
     * Envia el Archivo de salida XML en la carpeta de salida de SFTP de Osram
     *
     * @param Illuminate\Database\Eloquent\Collection $documento Colección de información del documento
     * @param array $ofeConexionFtp Configuracion del árbol de directorios del SFTP de Osram
     * @param int $usu_id ID del usuario de Osram
     * @param datetime $fechaInicioProcesamiento Fecha y hora del inicio de procesamiento
     * @param timestamp $inicioProcesamiento Timestamp del inicio de procesamiento
     * @return void
     */
    private function enviarCarpetaSalida($documento, $ofeConexionFtp, $usu_id, $fechaInicioProcesamiento, $inicioProcesamiento) {

        try {
            // Trayendo tipo de documento
            $tdeCodigo = ParametrosTipoDocumentoElectronico::select(['tde_id', 'tde_codigo'])
                ->where('tde_id', $documento->tde_id)
                ->first();

            //Armando XML Salida
            $datosSalida = '';
            $datosSalida .= '<Result>';
              $datosSalida .= '<SignatureMessage type="MSGSAS" tstamp="'.date('Y-m-d').'">';
                $datosSalida .= '<Document Id="'.$documento->cdo_cufe.'">';                                                  //CUFE/CUDE DOCUEMNTO
                  $datosSalida .= '<Sender></Sender>';
                  $datosSalida .= '<Receiver>'.$documento->getConfiguracionAdquirente->adq_identificacion.'</Receiver>';     //Nit Adquirente
                  $datosSalida .= '<Type>'.$tdeCodigo->tde_codigo.'</Type>';                                                 //Tipo de Documento
                  $datosSalida .= '<SerieNumber>'.$documento->rfa_prefijo . $documento->cdo_consecutivo.'</SerieNumber>';    //prefijo + consecutivo de docuemnto
                  $datosSalida .= '<Serie>'.$documento->rfa_prefijo.'</Serie>';                                              //Prefijo del documento
                  $datosSalida .= '<Number>'.$documento->cdo_consecutivo.'</Number>';                                        //cocecutivo del documneto
                  $datosSalida .= '<Date>'.str_replace(' ','T',$documento->cdo_fecha_validacion_dian).'</Date>';             //Fecha validación DIAN
                  $datosSalida .= '<TransactionId>'.$documento->cdo_cufe.'</TransactionId>';                                 //número de CUFE/CUDE
                $datosSalida .= '</Document>';
                $datosSalida .= '<StoreInfo>';
                  $datosSalida .= '<TimeStamp>'.str_replace(' ','T',$documento->cdo_fecha_validacion_dian).'</TimeStamp>';   //Fecha validación DIAN
                  $datosSalida .= '<URL />';
                $datosSalida .= '</StoreInfo>';
                $datosSalida .= '<Authorization>';
                  $datosSalida .= '<Status>';
                    $datosSalida .= '<Code>2</Code>';                                                                        //Enviar "2"
                    $datosSalida .= '<TrackId>'.$documento->rfa_prefijo . $documento->cdo_consecutivo.'</TrackId>';          //prefijo + consecutivo de docuemnto
                    $datosSalida .= '<Description>Aceptado SAT</Description>';                                               //Enviar "Aceptado SAT"
                    $datosSalida .= '<TimeStamp>'.str_replace(' ','T',$documento->cdo_fecha_validacion_dian).'</TimeStamp>'; //Fecha validación DIAN
                    $datosSalida .= '<Comments>'.$documento->cdo_cufe.'</Comments>';                                         //número de CUFE/CUDE
                  $datosSalida .= '</Status>';
                $datosSalida .= '</Authorization>';
                $datosSalida .= '<Status>';
                  $datosSalida .= '<Code>2</Code>';                                                                          //Enviar "2"
                  $datosSalida .= '<Description>Aceptado SAT</Description>';                                                 //Enviar "Aceptado SAT"
                  $datosSalida .= '<TimeStamp>'.str_replace(' ','T',$documento->cdo_fecha_validacion_dian).'</TimeStamp>';   //Fecha validación DIAN
                  $datosSalida .= '<Comments>'.$documento->cdo_cufe.'</Comments>';                                           //número de CUFE/CUDE
                $datosSalida .= '</Status>';
              $datosSalida .= '</SignatureMessage>';
            $datosSalida .= '</Result>';

            //Transmitiendo Archivo a carpeta del SFTP
            if ($documento->cdo_clasificacion === 'NC')
                $remoteBase = $ofeConexionFtp['salida_nc'];
            elseif ($documento->cdo_clasificacion === 'ND')
                $remoteBase = $ofeConexionFtp['salida_nd'];
            else
                $remoteBase = $ofeConexionFtp['salida_fc'];

            TenantTrait::GetVariablesSistemaTenant();
            $storagePath  = Storage::disk($this->discoTrabajo)->getDriver()->getAdapter()->getPathPrefix();
            $prefijoRuta  = $this->baseDatosOsram . $this->nitOsram . $remoteBase;

            $nombreXML = $documento->rfa_prefijo . $documento->cdo_consecutivo . '_' .$documento->cdo_fecha;
            //Creando archivo
            Storage::disk($this->discoTrabajo)->put($prefijoRuta . $nombreXML . '.xml', $datosSalida);
            chown($storagePath . $prefijoRuta . $nombreXML . '.xml', config('variables_sistema.USUARIO_SO'));
            chmod($storagePath . $prefijoRuta . $nombreXML . '.xml', 0755);

            $estadoInformacionAdicional['archivo_xml'] = $nombreXML;

            EtlEstadosDocumentoDaop::create([
                'cdo_id'                   => $documento->cdo_id,
                'est_estado'               => 'ENVIADO-FTP',
                'est_resultado'            => 'EXITOSO',
                'est_informacion_adicional'=> empty($estadoInformacionAdicional) ? null : json_encode($estadoInformacionAdicional),
                'est_inicio_proceso'       => $fechaInicioProcesamiento,
                'est_fin_proceso'          => date('Y-m-d H:i:s'),
                'est_tiempo_procesamiento' => number_format((microtime(true) - $inicioProcesamiento), 3, '.', ''),
                'est_ejecucion'            => 'FINALIZADO',
                'usuario_creacion'         => $usu_id,
                'estado'                   => 'ACTIVO',
            ]);

            $documento->update([
                'cdo_fecha_archivo_salida' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            dump($e->getMessage());
            EtlEstadosDocumentoDaop::create([
                'cdo_id'                    => $documento->cdo_id,
                'est_estado'                => 'ENVIADO-FTP',
                'est_resultado'             => 'FALLIDO',
                'est_mensaje_resultado'     => 'Error al procesar el documento',
                'est_inicio_proceso'        => $fechaInicioProcesamiento,
                'est_fin_proceso'           => date('Y-m-d H:i:s'),
                'est_tiempo_procesamiento'  => number_format((microtime(true) - $inicioProcesamiento), 3, '.', ''),
                'est_ejecucion'             => 'FINALIZADO',
                'est_informacion_adicional' => json_encode(['error' => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()]),
                'usuario_creacion'          => $usu_id,
                'estado'                    => 'ACTIVO',
            ]);

            $documento->update([
                'cdo_fecha_archivo_salida' => date('Y-m-d H:i:s')
            ]);
        }
    }
}