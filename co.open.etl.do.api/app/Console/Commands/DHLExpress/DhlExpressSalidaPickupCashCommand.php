<?php
namespace App\Console\Commands\DHLExpress;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Parser de archivos txt para la carga de documentos electronicos por parte de DHL Express
 * Class DhlExpressCommand
 * @package App\Console\Commands
 */
class DhlExpressSalidaPickupCashCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhlexpress-salida-pickup-cash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera los archivos TXT de salida de DhlExpress para el proceso Pickup Cash';

    /**
     * @var int Total de registros a procesar
     */
    protected $total_procesar = 32;

    /**
     * Disco de trabajo para el procesamiento de los archivos.
     * 
     * @var string
     */
    private $discoTrabajo = 'ftpDhlExpress';

    /**
     * Nombre del directorio de la BD.
     * 
     * @var string
     */
    protected $baseDatosDhlExpress = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * Nombre del directorio del NIT de Express.
     * 
     * @var string
     */
    protected $nitDhlExpress = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * cdo_clasificacion de los documentos que aplican.
     * 
     * @var array
     */
    protected $arrCdoClasificacion = ['FC','NC','ND'];

    /**
     * Array para homolación de productos.
     *
     * @var array
     */
    protected $arrHomologacionCargos = [
        'ZS53' => 'II',
        'ZS44' => 'IB',
        'ZS23' => 'FF',
        'ZS85' => 'WO',
        'ZS24' => 'CR'
    ];

    /**
     * Array para homolación de productos.
     *
     * @var array
     */
    protected $arrHomologacionProductos = [
        '78102201' => 'N',
        '78102204' => 'P',
        '99900010' => 'NP'
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
     * @return mixed
     */
    public function handle() {
        $arrCorreos = [
            'dhlexpress-cra@openio.co',
            'dhlexpress-cra01@openio.co'
        ];

        // Se selecciona un correo de manera aleatoria, a éste correo quedará ligado todo el procesamiento del registro
        $correo = $arrCorreos[array_rand($arrCorreos)];

        // Obtiene el usuario relacionado con DhlExpress
        $user = User::where('usu_email', $correo)
            ->where('estado', 'ACTIVO')
            ->with([
                'getBaseDatos:bdd_id,bdd_nombre,bdd_host,bdd_usuario,bdd_password'
            ])
            ->first();

        if($user) {
            // Generación del token conforme al usuario
            $token = auth()->login($user);
            DoTrait::setFilesystemsInfo();

            // Obtiene datos de conexión y ruta de salida 'Pickup' para DHLExpress
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_conexion_ftp'])
                ->where('ofe_identificacion', '860502609')
                ->first();

            $contenidoArchivo    = '';

            // Obtiene los documentos para los cuales se deben generar los archivos de salida y que cumplan las siguientes condiciones
            //  - cdo_procesar_documento debe ser SI
            //  - cdo_representacion_grafica_documento debe ser 9
            //  - cdo_fecha_archivo_salida debe ser null
            //  - Debe tener un estado PICKUP-CASH EXITOSO y FINALIZADO
            //  - El cdo_clasificacion del documento sea FC, NC o ND

            // Ticket ETL-1201. Se solicito por parte del cliente enviar los ultimos 7 dias, sin tener en cuenta el dia actual
            date_default_timezone_set('America/Bogota');
            // Canitdad de dias que se deben restar
            $dias = 7;
            // Fecha actual del sistema
            $actual = date("Y-m-d");
            // La fecha final corresponde al dia actual menos un dia
            $fin = date("Y-m-d", strtotime($actual."- 1 days"));
            // Para el inicio del proyecto en produccion se debe tranmitir todo el mes de marzo
            // Para los demas dias la fecha inicial es la fecha actual menos 7 dias
            $inicio = date("Y-m-d", strtotime($actual."- $dias days"));

            $documentos = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'cdo_fecha', 'adq_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha_archivo_salida'])
                ->whereIn('cdo_clasificacion', $this->arrCdoClasificacion)
                ->where('cdo_representacion_grafica_documento', '9')
                ->whereNull('cdo_fecha_archivo_salida')
                ->whereBetween('cdo_fecha_validacion_dian', [$inicio . ' 00:00:00', $fin . ' 23:59:59'])
                ->with([
                    'getConfiguracionAdquirente' => function($query) {
                        $query->select(
                            'adq_id',
                            'adq_identificacion',
                            'toj_id',
                            'cpo_id',
                            'pai_id',
                            'mun_id',
                            'dep_id',
                            'adq_direccion',
                            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo')
                        );
                    },
                    'getPickupCashDocumento',
                    'getDetalleDocumentosDaop:ddo_id,cdo_id,ddo_total,ddo_codigo',
                    'getCargosDescuentosDocumentosDaop:cdd_id,cdo_id,cdd_aplica,cdd_tipo,cdd_razon,cdd_valor,dmc_id,cdd_nombre',
                    'getCargosDescuentosDocumentosDaop.getDocumentoManualCargo:dmc_id,dmc_codigo'
                ])
                ->has('getPickupCashDocumento')
                ->has('getDoDocumento')
                ->get();

            $saltoLinea = "\r\n";
            $saltoColumna = "\t";
            foreach($documentos as $documento) {
                // Inicio de procesamiento
                $fechaInicio = date('Y-m-d H:i:s');
                $timestampInicio = microtime(true);

                // Información almacenada de la gúia
                $infoGuia = json_decode($documento->getPickupCashDocumento->est_informacion_adicional);

                // Totaliza los descuentos del documento, si es mayor a cero se deberá restar al valor del primer item
                $totalDescuentos = 0;
                $descuentos = $documento->getCargosDescuentosDocumentosDaop
                    ->where('cdd_tipo', 'DESCUENTO')
                    ->values();

                foreach($descuentos as $descuento) {
                    $totalDescuentos += $descuento->cdd_valor;
                }

                // Fecha del documento
                $fechaDocumento = Carbon::parse($documento->cdo_fecha);

                // Items del documento
                $detalleDoc         = '';
                $homologaPrimerItem = '';
                foreach($documento->getDetalleDocumentosDaop as $index => $item) {
                    if($index == 0 && $totalDescuentos > 0)
                        $totalItem          = $item->ddo_total - $totalDescuentos;
                    else
                        $totalItem = $item->ddo_total;

                    $detalleDoc .= 'F9';
                    $detalleDoc .= 'CO';
                    $detalleDoc .= $this->completarCaracteres(3, 'der', (isset($infoGuia->area_servicio) ? $infoGuia->area_servicio : ''));
                    $detalleDoc .= $this->completarCaracteres(9, 'der', (isset($infoGuia->cuenta_cliente) ? $infoGuia->cuenta_cliente : ''));
                    $detalleDoc .= $this->completarCaracteres(12, 'izq', number_format($totalItem, 2, '', ''), '0');
                    $detalleDoc .= $this->completarCaracteres(2, 'der', $fechaDocumento->format('d'));
                    $detalleDoc .= $this->completarCaracteres(2, 'der', $fechaDocumento->format('m'));
                    $detalleDoc .= $this->completarCaracteres(2, 'der', $fechaDocumento->format('y'));
                    $detalleDoc .= $this->completarCaracteres(16, 'der', $documento->rfa_prefijo . $documento->cdo_consecutivo);
                    $detalleDoc .= $this->completarCaracteres(10, 'der', (isset($infoGuia->guia) ? $infoGuia->guia : ''));
                    $detalleDoc .= $this->completarCaracteres(1, 'der', (array_key_exists($item->ddo_codigo, $this->arrHomologacionProductos) ? $this->arrHomologacionProductos[$item->ddo_codigo] : ''));
                    $detalleDoc .= $this->completarCaracteres(3, 'der', '000');
                    $detalleDoc .= $this->completarCaracteres(3, 'der', '000');
                    $detalleDoc .= $this->completarCaracteres(2, 'der', ' ');
                    $detalleDoc .= $this->completarCaracteres(2, 'der', ' ');
                    $detalleDoc .= $this->completarCaracteres(50, 'der', $documento->getConfiguracionAdquirente->nombre_completo, ' ', true);
                    $detalleDoc .= $this->completarCaracteres(12, 'der', $documento->getConfiguracionAdquirente->adq_identificacion);
                    $detalleDoc .= $this->completarCaracteres(11, 'der', (isset($infoGuia->numero_nota) ? $infoGuia->numero_nota : ''));

                    if($index == 0) {
                        $detalleDoc .= $this->completarCaracteres(5, 'der', '1');
                        $detalleDoc .= $this->completarCaracteres(5, 'der', '1');
                        $detalleDoc .= $this->completarCaracteres(10, 'der', '0.00');
                        $detalleDoc .= $this->completarCaracteres(5, 'der', 'COP');
                        $detalleDoc .= $this->completarCaracteres(11, 'der', '1');

                        $homologaPrimerItem = array_key_exists($item->ddo_codigo, $this->arrHomologacionProductos) ? $this->arrHomologacionProductos[$item->ddo_codigo] : '';
                    } else {
                        $detalleDoc .= $this->completarCaracteres(5, 'der', ' ');
                        $detalleDoc .= $this->completarCaracteres(5, 'der', ' ');
                        $detalleDoc .= $this->completarCaracteres(10, 'der', ' ');
                        $detalleDoc .= $this->completarCaracteres(5, 'der', ' ');
                        $detalleDoc .= $this->completarCaracteres(11, 'der', ' ');
                    }

                    $detalleDoc .= $this->completarCaracteres(13, 'der', ' ');
                    $detalleDoc .= $this->completarCaracteres(5, 'der', ' ');
                    $detalleDoc .= $this->completarCaracteres(2, 'der', 'A0');
                    $detalleDoc .= $this->completarCaracteres(20, 'der', ' ');
                    $detalleDoc .= $saltoLinea;
                }

                // Cargos del documento
                $cargosDoc    = '';
                $cargos = $documento->getCargosDescuentosDocumentosDaop
                    ->where('cdd_tipo', 'CARGO')
                    ->values();

                foreach($cargos as $cargo) {
                    $codigoCargo = (!empty($cargo->getDocumentoManualCargo) && isset($cargo->getDocumentoManualCargo->dmc_codigo)) ? $cargo->getDocumentoManualCargo->dmc_codigo : $cargo->cdd_nombre;
                    $cargosDoc .= $this->completarCaracteres(2, 'der', (array_key_exists($codigoCargo, $this->arrHomologacionCargos) ? $this->arrHomologacionCargos[$codigoCargo] : ''));
                    $cargosDoc .= 'CO';
                    $cargosDoc .= $this->completarCaracteres(3, 'der', (isset($infoGuia->area_servicio) ? $infoGuia->area_servicio : ''));
                    $cargosDoc .= $this->completarCaracteres(9, 'der', (isset($infoGuia->cuenta_cliente) ? $infoGuia->cuenta_cliente : ''));
                    $cargosDoc .= $this->completarCaracteres(12, 'izq', number_format($cargo->cdd_valor, 2, '', ''), '0');
                    $cargosDoc .= $this->completarCaracteres(2, 'der', $fechaDocumento->format('d'));
                    $cargosDoc .= $this->completarCaracteres(2, 'der', $fechaDocumento->format('m'));
                    $cargosDoc .= $this->completarCaracteres(2, 'der', $fechaDocumento->format('y'));
                    $cargosDoc .= $this->completarCaracteres(16, 'der', $documento->rfa_prefijo . $documento->cdo_consecutivo);
                    $cargosDoc .= $this->completarCaracteres(10, 'der', (isset($infoGuia->guia) ? $infoGuia->guia : ''));
                    $cargosDoc .= $this->completarCaracteres(1, 'der', $homologaPrimerItem);
                    $cargosDoc .= $this->completarCaracteres(3, 'der', '000');
                    $cargosDoc .= $this->completarCaracteres(3, 'der', '000');
                    $cargosDoc .= $this->completarCaracteres(2, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(2, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(50, 'der', $documento->getConfiguracionAdquirente->nombre_completo, ' ', true);
                    $cargosDoc .= $this->completarCaracteres(12, 'der', $documento->getConfiguracionAdquirente->adq_identificacion);
                    $cargosDoc .= $this->completarCaracteres(11, 'der', (isset($infoGuia->numero_nota) ? $infoGuia->numero_nota : ''));
                    $cargosDoc .= $this->completarCaracteres(5, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(5, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(10, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(5, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(11, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(13, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(5, 'der', ' ');
                    $cargosDoc .= $this->completarCaracteres(2, 'der', 'A0');
                    $cargosDoc .= $this->completarCaracteres(20, 'der', ' ');
                    $cargosDoc .= $saltoLinea;
                }

                $contenidoArchivo .= $detalleDoc . $cargosDoc;

                // Actualiza el documento
                $documento->update([
                    'cdo_fecha_archivo_salida' => date('Y-m-d H:i:s')
                ]);

                // Crea el estado correspondiente
                $this->crearEstado(
                    $documento->cdo_id,
                    'PICKUP-CASH-FTP',
                    'EXITOSO',
                    null,
                    null,
                    null,
                    $fechaInicio,
                    date('Y-m-d H:i:s'),
                    number_format((microtime(true) - $timestampInicio), 3, '.', ''),
                    'FINALIZADO',
                    $user->usu_id,
                    'ACTIVO'
                );
            }

            if($contenidoArchivo != '') {
                // Se crea el archivo y se escribe el contenido del mismo
                // $nombreArchivoSalida = 'gbi_CO_exp_' . date('YmdHis') . '.txt';
                // $nombreArchivoSalida = 'CO10_WML_OC_02_' . date('Ymd') . '_' . date('His') . '.TXT';
                $nombreArchivoSalida = 'CO10_WML_OC_02_' . date('Ymd') . '_' . date('His') . '.txt.';
                
                $path = $this->baseDatosDhlExpress . $this->nitDhlExpress . $ofe->ofe_conexion_ftp['salida_pickup_cash'];
                TenantTrait::GetVariablesSistemaTenant();
                DoTrait::setFilesystemsInfo();
                Storage::disk($this->discoTrabajo)->put($path . $nombreArchivoSalida, $contenidoArchivo);
                chown(config('variables_sistema_tenant.RUTA_DHL_EXPRESS_860502609') . '/' . $path . $nombreArchivoSalida, config('variables_sistema.USUARIO_SO'));
                chmod(config('variables_sistema_tenant.RUTA_DHL_EXPRESS_860502609') . '/' . $path . $nombreArchivoSalida, 0775);
            }
        }
    }

    /**
     * Crea un estado para el documento.
     *
     * @param int $cdo_id ID del documento
     * @param string $estadoDescripcion Descripción del estado
     * @param string $resultado Resultado del estado
     * @param null|string $informacionAdicional Objeto conteniendo información relacionada con el objeto
     * @param null|int $age_id ID agendamiento
     * @param null|int $age_usu_id ID del usuario relacionadoc con el agendamiento
     * @param string $inicio Fecha y hora de inicio de procesamiento
     * @param string $fin Fecha y hora final de procesamiento
     * @param float $tiempo Tiempo de procesamiento
     * @param string $ejecucion Estado final de procesamiento
     * @param int $usuario ID del usuario relacionado con el procesamiento
     * @param string $estadoRegistro Estado del registro
     * @return void
     */
    private function crearEstado(int $cdo_id, string $estadoDescripcion, string $resultado, $informacionAdicional, $age_id, $age_usu_id, string $inicio, string $fin, float $tiempo, string $ejecucion, int $usuario, string $estadoRegistro): void {
        EtlEstadosDocumentoDaop::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estadoDescripcion,
            'est_resultado'             => $resultado,
            'est_informacion_adicional' => $informacionAdicional,
            'age_id'                    => $age_id,
            'age_usu_id'                => $age_usu_id,
            'est_inicio_proceso'        => $inicio,
            'est_fin_proceso'           => $fin,
            'est_tiempo_procesamiento'  => $tiempo,
            'est_ejecucion'             => $ejecucion,
            'usuario_creacion'          => $usuario,
            'estado'                    => $estadoRegistro,
        ]);
    }

    /**
     * Completa una cadena hasta determinada longitud con caracteres en blanco a la izquierda o a la derecha.
     *
     * @param int $cantidad Cantidad totales de caracteres en la cadena resultante
     * @param string $ladoCompletar Indica si se debe completar la cadena por la izquierda o la derecha
     * @param string $cadena Cadena a procesar
     * @param string $caracterCompletar Caracter con el cual se debe completar el string
     * @param boolean $recortar Indica si la cadena debe ser recortada hast la cantidad de caracteres
     * @return string $cadena Cadena procesada
     */
    private function completarCaracteres(int $cantidad, string $ladoCompletar, string $cadena, string $caracterCompletar = ' ', bool $recortar = false): string {
        if($ladoCompletar == 'der') {
            $cadena = str_pad($cadena, $cantidad, $caracterCompletar, STR_PAD_RIGHT);
        } else {
            $cadena = str_pad($cadena, $cantidad, $caracterCompletar, STR_PAD_LEFT);
        }
        if($recortar)
            return substr($cadena, 0, $cantidad);
        else
            return $cadena;
    }
}
