<?php


namespace App\Console\Commands\DHLGlobal;


use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class DhlBaseSalidaArchivosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl-core-salida-archivos-commmand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando base para el procesamiento de envio de archivos al servidor FTP en DHL Agencia, Goblal, Zona Franca y Deposito - No se ejecuta directamente';

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
     * Cantidad de elementos a procesar.
     *
     * @var
     */
    public $total_procesar = 10;

    /**
     * Execute the console command.
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Exception
     */
    public function handle(Request $request)
    {
        echo "Comando base para carga de archivos en el servidor FTP de DHL Agencia, Goblal, Zona Franca - No se ejecuta directamente\n";
    }

    /**
     * Construye el nombre del archivo de la representacion grafica
     * @param EtlCabeceraDocumentoDaop $documento
     * @return string
     */
    private function getDocName(EtlCabeceraDocumentoDaop $documento)
    {
        $nombre = $documento->cdo_clasificacion;
        if (!empty($documento->rfa_prefijo))
            $nombre = $nombre . $documento->rfa_prefijo;
        $nombre = $nombre . $documento->cdo_consecutivo;
        return $nombre;
    }

    public function process() {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        // Obtiene el usuario relacionado con DHLGlobal
        $user = User::where('usu_email', $this->emailUsuario)
            ->first();

        if (!is_null($user)) {
            auth()->login($user);

            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id','ofe_conexion_ftp'])
                ->where('ofe_identificacion', $this->nitOFE)
                ->first();

            if (is_null($ofe))
                return;

            $documentos = EtlCabeceraDocumentoDaop::select(['cdo_cufe', 'cdo_id', 'ofe_id', 'cdo_clasificacion', 'rfa_prefijo', 'cdo_consecutivo'])
                ->where('ofe_id', $ofe->ofe_id)
                ->whereIn('cdo_clasificacion', ['FC','NC'])
                ->whereNotNull('cdo_fecha_validacion_dian')
                ->whereNull('cdo_fecha_archivo_salida')
                ->whereRaw("cdo_id IN (SELECT cdo_id FROM etl_estados_documentos_daop WHERE est_estado = 'NOTIFICACION' AND est_resultado='EXITOSO')")
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente',
                    'getDadDocumentosDaop:dad_id,cdo_id,cdo_informacion_adicional'
                ])
                ->take($this->total_procesar)
                ->get();

            DoTrait::setFilesystemsInfo();
            $storagePath = Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();

            // Establece la conexión al FTP de DHLAgencia
            $dhlFtp = ftp_connect($ofe->ofe_conexion_ftp['host']);
            if ($dhlFtp) {
                // Configura tiemout de la conexión en 180 segundos
                ftp_set_option($dhlFtp, FTP_TIMEOUT_SEC, 180);
                // Inicia sesión
                $loginFtp = ftp_login($dhlFtp, $ofe->ofe_conexion_ftp['username'], $ofe->ofe_conexion_ftp['password']);
                // Método pasivo
                ftp_pasv($dhlFtp, true);

                foreach ($documentos as $documento) {
                    $compania = '';
                    switch ($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion) {
                        case '860030380':
                            $compania = 'CO';
                            break;
                        case '830002397':
                            $compania = 'SI';
                            break;
                        case '860038063':
                            $compania = 'DB';
                        break;
                        case '830025224':
                            $compania = 'ZF';
                        break;
                    }
                    $tipoDocumento = '';
                    switch ($documento->cdo_clasificacion) {
                        case "FC":
                            $informacionAdicional = $documento->getDadDocumentosDaop->cdo_informacion_adicional;
                            // $stringDocumento      = $documento->rfa_prefijo . $documento->cdo_consecutivo;
                            $stringDocumento      = (array_key_exists('aerol_naviera', $informacionAdicional) && $informacionAdicional['aerol_naviera'] != '' && strpos($informacionAdicional['aerol_naviera'], ' ') === false) ? $informacionAdicional['aerol_naviera'] : '';
                            $tipoDocumento        = 'IN';
                            break;
                        case "NC":
                            $tipoDocumento   = 'CR';
                            $stringDocumento = $documento->rfa_prefijo . $documento->cdo_consecutivo;
                            break;
                        case "ND":
                            $tipoDocumento   = '';
                            $stringDocumento = $documento->rfa_prefijo . $documento->cdo_consecutivo;
                            /** NO DEFINIDO A LA FECHA */
                            break;
                    }

                    $contenido = $compania . ';' .
                        $tipoDocumento . ';' .
                        $stringDocumento . ';' .
                        $documento->cdo_cufe;
                    
                    /**
                     * El nombre del documento de tener la siguiente estructura
                     * Global     : CO_ + prefijo + consecutivo // Si es FC debe ser CO_ + cdo_informacion_adicional[aerol_naviera]
                     * Aduana     : SI_ + prefijo + consecutivo // Si es FC debe ser CO_ + cdo_informacion_adicional[aerol_naviera]
                     * Deposito   : DB_ + prefijo + consecutivo // Si es FC debe ser CO_ + cdo_informacion_adicional[aerol_naviera]
                     * Zona Franca: ZF_ + prefijo + consecutivo // Si es FC debe ser CO_ + cdo_informacion_adicional[aerol_naviera]
                     */
                    // $nombrebase = $this->getDocName($documento);
                    if(isset($stringDocumento) && $stringDocumento != '') {
                        $nombrebase = $compania."_".$stringDocumento;
                        Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))->put($this->nombreEmpresaStorage . '/' . $nombrebase . '.txt', $contenido);
                        $remoto = $ofe->ofe_conexion_ftp['salida_' . strtolower($documento->cdo_clasificacion)] . '/';
                        ftp_put($dhlFtp, $remoto . $nombrebase . '.txt', $storagePath . $this->nombreEmpresaStorage . '/' . $nombrebase . '.txt', FTP_TEXT);
                        @unlink($storagePath . $this->nombreEmpresaStorage . '/' . $nombrebase . '.txt');
                    }
                    $documento->cdo_fecha_archivo_salida = date('Y-m-d H:i:s');
                    $documento->update();
                }
                ftp_close($dhlFtp);
            }
        }
    }

}