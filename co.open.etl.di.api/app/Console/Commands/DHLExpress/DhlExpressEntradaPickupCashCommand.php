<?php
namespace App\Console\Commands\DHLExpress;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Console\Command;
use App\Http\Models\AdoAgendamiento;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\DataInputWorker\WriterInput;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\PickupCash\PryGuiaPickupCash;
use App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\PickupCash\PryArchivoPickupCash;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;
use App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\PickupCash\PryDetalleArchivoPickupCash;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Parser de archivos txt para la carga de documentos electronicos por parte de DHL Express
 * Class DhlExpressCommandg
 * @package App\Console\Commands
 */
class DhlExpressEntradaPickupCashCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhlexpress-entrada-pickup-cash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa archivos TXT de entrada de DhlExpress para el proceso Pickup Cash';

    /**
     * @var int Total de registros a procesar
     */
    protected $total_procesar = 32;

    /**
     * Disco de trabajo para el procesamiento de los archivos
     * @var string
     */
    private $discoTrabajo = 'ftpDhlExpress';

    /**
     * @var string Nombre del directorio de la BD
     */
    protected $baseDatosDhlExpress = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * @var string Nombre del directorio del NIT de Express
     */
    protected $nitDhlExpress = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * @var ConfiguracionObligadoFacturarElectronicamente Instancia del modelo de OFE de DHL Express con NIT 860502609
     */
    protected $ofeAgendamientos = null;

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
            DiTrait::setFilesystemsInfo();

            // Obtiene datos de conexión y ruta de entrada 'Pickup' para DHLExpress
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_conexion_ftp', 'ofe_prioridad_agendamiento'])
                ->where('ofe_identificacion', '860502609')
                ->first();

            $this->ofeAgendamientos = $ofe;

            // El proceso sobre los archivos del SFTP debe ejecutar sobre dos modelos de archivos diferentes, el modelo incial se estará procesando sobre el sufijo de ruta ''
            // y el modelo nuevo se estará procesando sobre el sufijo de ruta '_new'. Se debe iniciar por el procesamiento en el sufijo de ruta '_new'
            foreach(['_new', ''] as $sufijoRuta) {
                // Procesa los archivos del disco del SFTP del OFE
                $path = $this->baseDatosDhlExpress . $this->nitDhlExpress . $ofe->ofe_conexion_ftp['entrada_pickup_cash' . $sufijoRuta];
                $this->procesarRuta($path, $ofe, $user, true, $sufijoRuta);
            }
        }
    }

    /**
     * Procesa archivos ubicados en disco local o en el SFTP del OFE.
     *
     * @param string $ruta Ruta dentro del disco en donde se ubican los archivos
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Coleccion con información del OFE
     * @param User $user Collecion con la información del usuario relacionado con el procesamiento
     * @param bool $guardar Indica si la información del archivo debe guardarse o no en la base de datos
     * @param string $sufijoRuta Sufijo que se concatenará a las rutas de los archivos para poder procesar los diferentes modelos de archivos
     * @return void
     */
    private function procesarRuta(string $ruta, ConfiguracionObligadoFacturarElectronicamente $ofe, User $user, bool $guardar, string $sufijoRuta) {
        // Obtiene lista de archivos
        $archivos = Storage::disk($this->discoTrabajo)->files($ruta);

        if (count($archivos) > 0) {
            // Toma una cantidad determinada de archivos para procesarlos
            // $archivos = array_slice($archivos, 0, $this->total_procesar);

            //Para que el proceso no se bloque se deben traer los documentos que se encuentren sin la extension .procesado
            $contador = 0;
            $archivosSinProcesar = array();
            foreach ($archivos as $archivo) {
                $extension = \File::extension($archivo);
                if (strtolower($extension) === 'txt') {
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
                $nombre    = \File::name($archivo);

                try {
                    if (strtolower($extension) === 'txt' && Storage::disk($this->discoTrabajo)->exists($archivo) && Storage::disk($this->discoTrabajo)->size($archivo) > 0) {
                        // Renombra el documento para que no sea procesado por otra instancia del comando
                        Storage::disk($this->discoTrabajo)->rename($archivo, $archivo . '.procesado');

                        // Inicia el procesamiento del archivo
                        $proceso = $this->procesarArchivo($user, $archivo . '.procesado', $ofe, $nombre . '.' . $extension, $guardar, $sufijoRuta);
                        $this->procesarResultado($ofe, $user, $proceso, $nombre . '.' . $extension, $archivo, $sufijoRuta);
                    }
                } catch (\Exception $e) {
                    $arrExcepciones = [];
                    $arrExcepciones[] = [
                        'documento'           => $nombre.'.'.$extension,
                        'consecutivo'         => '',
                        'prefijo'             => '',
                        'errors'              => [ $e->getMessage() ],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'archivo'             => $nombre.'.'.$extension,
                        'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                    ];

                    $this->registrarError(
                        json_encode([]),
                        json_encode($arrExcepciones),
                        $user->usu_id
                    );

                    $archivoMover = $this->baseDatosDhlExpress . $this->nitDhlExpress . $ofe->ofe_conexion_ftp['fallidos_pickup_cash' . $sufijoRuta] . $nombre . '.' . $extension;
                    $this->moverArchivo($archivo . '.procesado', $archivoMover);
                }
            }
        }
    }

    /**
     * Mueve un archivo de una ubicación a otra.
     *
     * @param string $origen Path relativo del archivo origen
     * @param string $destino Pat relativo del archivo destino
     * @return void
     */
    private function moverArchivo($origen, $destino) {
        if (Storage::disk($this->discoTrabajo)->exists($destino)) {
            Storage::disk($this->discoTrabajo)->delete($destino);
        }
        if (Storage::disk($this->discoTrabajo)->exists($origen)) {
            Storage::disk($this->discoTrabajo)->move($origen, $destino);
        }
    }

    /**
     * Intenta codificar a utf-8 las diferentes lineas que componen el archivo de entrada.
     *
     * @param $line
     * @return bool|false|string|string[]|null
     */
    public function fixEncoding($line) {
        if (($codificacion = mb_detect_encoding($line)) !== false)
            return mb_convert_encoding($line, "UTF-8", $codificacion);
        return mb_convert_encoding($line, "ISO-8859-1");
    }

    /**
     * Extrae los valores requeridos de cada linea.
     *
     * @param string $linea Información de una linea archivo
     * @param string $linea2 Información de la línea 2 de la guia, aplica para los archivos del modelo 1 en donde la información de la guía esta en dos líneas
     * @param string $sufijoRuta Sufijo que se concatenará a las rutas de los archivos para poder procesar los diferentes modelos de archivos
     * @param int $indexFila Número que indica la posición de la línea en el archivo en procesamiento
     * @return array Array conteniendo los valores requeridos
     */
    public function extraerValores(string $linea, string $linea2 = '', string $sufijoRuta, int $indexFila){
        if(!empty($sufijoRuta)) {
            try {
                $arrayValores = explode('|', $linea);
                return [
                    'fecha_generacion_awb' => trim($arrayValores[6]),
                    'guia'                 => trim($arrayValores[0]),
                    'area_servicio'        => trim($arrayValores[1]),
                    'oficina_venta'        => trim($arrayValores[2]),
                    'numero_nota'          => trim($arrayValores[11]),
                    'importe_total'        => trim($arrayValores[18]),
                    'cuenta_cliente'       => trim($arrayValores[9]),
                    'route_code'           => trim($arrayValores[3])
                ];
            } catch (\Exception $e) {
                throw new \Exception('No fue posible extraer los valores de la línea [' . $indexFila . '] del archivo');
            }
        } else {
            return [
                'fecha_factura'        => trim(substr($linea, 34, 8)),
                'fecha_generacion_awb' => trim(substr($linea, 42, 8)),
                'cuenta_cliente'       => trim(substr($linea, 50, 10)),
                'paquete_documento'    => trim(substr($linea, 90, 5)),
                'guia'                 => trim(substr($linea, 95, 20)),
                'codigo_estacion'      => trim(substr($linea, 330, 10)),
                'oficina_venta'        => trim(substr($linea, 340, 4)),
                'organizacion_ventas'  => trim(substr($linea, 377, 5)),
                'numero_externo'       => trim(substr($linea, 398, 16)),
                'numero_nota'          => trim(substr($linea, 414, 18)),
                'estacion_origen'      => trim(substr($linea, 473, 4)),
                'estacion_destino'     => trim(substr($linea, 477, 4)),
                'texto_final'          => trim(substr($linea, 497, 50)),
                'importe_total'        => trim(substr($linea2, 72, 13))
            ];
        }
    }

    /**
     * Registra errores de procesamiento en el modelo EtlProcesamientoJson.
     *
     * @param json $json Objeto conteniendo la información de la guía procesada, puede ser null en caso de errores del código
     * @param json $errores Objeto conteniendo los errores correspondientes
     * @param int $usu_id ID del usuario relacionado con el procesamiento
     * @return void
     */
    private function registrarError($json, $errores, $usu_id) {
        EtlProcesamientoJson::create([
            'pjj_tipo'                => 'PICKUP-CASH',
            'pjj_procesado'           => 'SI',
            'pjj_json'                => $json,
            'pjj_errores'             => $errores,
            'age_id'                  => 0,
            'age_estado_proceso_json' => 'FINALIZADO',
            'usuario_creacion'        => $usu_id,
            'estado'                  => 'ACTIVO'
        ]);
    }

    /**
     * Procesa un archivo TXT de entrada.
     *
     * @param User $user Collecion con la información del usuario relacionado con el procesamiento
     * @param string $rutaArchivo Ruta del archivo a procesar
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Coleccion con información del OFE
     * @param string $archivoReal Nombre original del archivo
     * @param bool $guardar Indica si la información del archivo debe guardarse o no en la base de datos
     * @param string $sufijoRuta Sufijo que se concatenará a las rutas de los archivos para poder procesar los diferentes modelos de archivos
     * @return array $guiaErrores Array con la ifnormación de errores y guías procesadas
     */
    private function procesarArchivo(User $user, string $rutaArchivo, ConfiguracionObligadoFacturarElectronicamente $ofe, string $archivoReal, bool $guardar, string $sufijoRuta) {
        $agendarUbl   = [];
        $guiasErrores = [];
        $storagePath  = Storage::disk($this->discoTrabajo)->getDriver()->getAdapter()->getPathPrefix();
        $filename     = $storagePath . $rutaArchivo;
        $totalGuias   = 0;

        $handle = fopen($filename, 'r');

        $archivoPickupCash = null;
        if($guardar && !empty($sufijoRuta)) {
            $archivoPickupCash = PryArchivoPickupCash::select(['apc_id', 'usuario_creacion', 'fecha_creacion'])
                ->where('apc_nombre_archivo_carpeta', $archivoReal)
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$archivoPickupCash)
                $archivoPickupCash = PryArchivoPickupCash::create([
                    'apc_nombre_archivo_original' => $archivoReal,
                    'apc_nombre_archivo_carpeta'  => $archivoReal,
                    'usuario_creacion'            => $user->usu_id,
                    'estado'                      => 'ACTIVO'
                ]);
        }

        $fileArray = file($filename);
        for($i = 0; $i < count($fileArray); $i++) {
            $line                     = $fileArray[$i];
            $fechaInicioProcesamiento = date('Y-m-d H:i:s');
            $timestampInicio          = microtime(true);

            // Omite líneas en blanco
            if(!empty($sufijoRuta) && strlen(preg_replace("/[\r\n|\n|\r|\\x00]+/", '', $line)) == 0) {
                continue;
            } elseif(empty($sufijoRuta) && strlen(preg_replace("/[\r\n|\n|\r]+/", '', $line)) == 0) {
                continue;
            }

            $totalGuias++;
            if(!empty($sufijoRuta)) {
                $linea         = $this->fixEncoding($line);
                $valores       = $this->extraerValores($linea, '', $sufijoRuta, ($i + 1));
            } else {
                $i++;
                $linea1 = $this->fixEncoding($line);

                // Como cada guía corresponde a dos renglones seguidos, entonces leemos el siguiente renglon
                // para tener los dos líneas correspondientes a la guía
                // Segunda línea
                $linea2        = $this->fixEncoding($fileArray[$i]);
                // $linea2        = $this->fixEncoding(fgets($handle));
                $valores       = $this->extraerValores($linea1, $linea2, $sufijoRuta, ($i + 1));
            }

            if($guardar && $archivoPickupCash && !empty($sufijoRuta))
                PryDetalleArchivoPickupCash::create([
                    'apc_id'                  => $archivoPickupCash->apc_id,
                    'dpc_fecha_guia'          => $valores['fecha_generacion_awb'],
                    'dpc_numero_guia'         => $valores['guia'],
                    'dpc_codigo_estacion'     => array_key_exists('codigo_estacion', $valores) && !empty($valores['codigo_estacion']) ? $valores['codigo_estacion'] : null,
                    'dpc_oficina_venta'       => $valores['oficina_venta'],
                    'dpc_organizacion_ventas' => array_key_exists('organizacion_ventas', $valores) && !empty($valores['organizacion_ventas']) ? $valores['organizacion_ventas'] : null,
                    'dpc_numero_nota'         => $valores['numero_nota'],
                    'dpc_importe'             => $valores['importe_total'],
                    'dpc_cuenta'              => $valores['cuenta_cliente'],
                    'dpc_route_code'          => $valores['route_code'],
                    'usuario_creacion'        => $archivoPickupCash->usuario_creacion,
                    'fecha_creacion'          => $archivoPickupCash->fecha_creacion,
                    'estado'                  => 'ACTIVO',
                ]);

            $this->verificarGuia($fechaInicioProcesamiento, $timestampInicio, $valores, $sufijoRuta, $agendarUbl, $guiasErrores);
        }

        return [
            'agendarUbl'   => $agendarUbl,
            'guiasErrores' => $guiasErrores,
            'totalGuias'   => $totalGuias
        ];
    }

    /**
     * Verifica una guía frente a la data existente en openETL.
     *
     * @param string $fechaInicioProcesamiento Fecha y hora del inicio de procesamiento de la guía
     * @param string|float $timestampInicio Timpestamp del inicio de procesamiento de la guía
     * @param array $valores Array de valores de la guía
     * @param string $sufijoRuta Sufijo de ruta cuando se procesar archivos desde el SFTP del cliente
     * @param array $agendarUbl Array que permite guardar los documentos de guías que se agendarán para el proceso UBL
     * @param array $guiasErrores Array que registra los errores de procesamiento de las guías
     * @return void
     */
    private function verificarGuia($fechaInicioProcesamiento, $timestampInicio, $valores, $sufijoRuta = '', &$agendarUbl = [], &$guiasErrores = []) {
        $existeGuia = EtlDatosAdicionalesDocumentoDaop::select(['dad_id', 'cdo_id', 'cdo_informacion_adicional'])
            ->where('cdo_informacion_adicional->guia', $valores['guia'])
            ->whereHas('getCabeceraDocumentosDaop',function($query) {
                $query->select(['cdo_id'])
                    ->where('cdo_representacion_grafica_documento', '9')
                    ->where(function($query) {
                        $query->where('estado', 'ACTIVO')
                            ->orWhere('estado', 'PROVISIONAL');
                    });
            })
            ->with([
                'getCabeceraDocumentosDaop:cdo_id,ofe_id,adq_id,cdo_clasificacion,rfa_id,rfa_prefijo,cdo_consecutivo,cdo_procesar_documento,cdo_fecha_procesar_documento,cdo_valor_a_pagar,estado',
                'getCabeceraDocumentosDaop.getDetalleDocumentosDaop:ddo_id,cdo_id',
                'getCabeceraDocumentosDaop.getPickupCashDocumento:est_id,cdo_id'
            ])
            ->get();

        $continuar = true;
        // La guía no esta asociada con ningún documento entonces se agrega a los fallidos
        if(!isset($existeGuia[0]->getCabeceraDocumentosDaop)){
            $guiasErrores[$valores['guia']] = [
                'tipodoc'           => '',
                'rfa_prefijo'       => '',
                'cdo_consecutivo'   => '',
                'razon'             => 'No existe',
                'valores'           => json_encode($valores)
            ];
            $continuar = false;
            $this->guardarGuiaBaseDatos(empty($sufijoRuta) ? 'SFTP' : 'WEB', $valores);
        }
        // Si la guia tiene un estado PICKUP-CASH finalizado NO se continua el proceso en adelante ni se agrega a fallidos
        elseif($existeGuia[0]->getCabeceraDocumentosDaop->cdo_procesar_documento == 'SI' || $existeGuia[0]->getCabeceraDocumentosDaop->getPickupCashDocumento != null) {
            $continuar = false;
        }
        // La guía esta asociada con un documento diferente de FC
        elseif($existeGuia[0]->getCabeceraDocumentosDaop->cdo_clasificacion != 'FC') {
            $guiasErrores[$valores['guia']] = [
                'tipodoc'           => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_clasificacion,
                'rfa_prefijo'       => $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo,
                'cdo_consecutivo'   => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo,
                'razon'             => 'Documento [' . $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo . $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo . '] diferente de factura de venta',
                'valores'           => json_encode($valores)
            ];
            $continuar = false;
            $this->guardarGuiaBaseDatos(empty($sufijoRuta) ? 'SFTP' : 'WEB', $valores);
        }
        // La guía debe existir en un solo documento entonces se agrega a los fallidos
        elseif(count($existeGuia) > 1) {
            $guiasErrores[$valores['guia']] = [
                'tipodoc'           => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_clasificacion,
                'rfa_prefijo'       => $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo,
                'cdo_consecutivo'   => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo,
                'razon'             => 'Guia existe para varios documentos',
                'valores'           => json_encode($valores)
            ];
            $continuar = false;
            $this->guardarGuiaBaseDatos(empty($sufijoRuta) ? 'SFTP' : 'WEB', $valores);
        }
        // El documento tiene más de un item entonces se agrega a los fallidos
        elseif(count($existeGuia[0]->getCabeceraDocumentosDaop->getDetalleDocumentosDaop) > 1) {
            $guiasErrores[$valores['guia']] = [
                'tipodoc'           => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_clasificacion,
                'rfa_prefijo'       => $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo,
                'cdo_consecutivo'   => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo,
                'razon'             => 'Documento [' . $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo . $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo . '] tiene más de un item',
                'valores'           => json_encode($valores)
            ];
            $continuar = false;
            $this->guardarGuiaBaseDatos(empty($sufijoRuta) ? 'SFTP' : 'WEB', $valores);
        }

        if($continuar) {
            $writer = new WriterInput();
            $writer->actualizarDocumentoPickupCash($existeGuia[0], $valores, 'comando');

            // Compara el importe total contra el campo cdo_valor_a_pagar, si son iguales
            // se marca el registro para envío a la DIAN, se crea el estado UBL y verifica
            // si existe estado PICKUP-CASH NO finalizado para actualizarlo y finalizarlo
            if(($valores['importe_total']+0) == ($existeGuia[0]->getCabeceraDocumentosDaop->cdo_valor_a_pagar+0)) {
                $crearAgendamientoDo = true;
                
                //Se incluye logica para generar consecutivo difinitivo, cuando el estado del documento es provisional
                if ($existeGuia[0]->getCabeceraDocumentosDaop->estado == 'PROVISIONAL') {
                    $consecutivo = $writer->getConsecutivoDocumento($existeGuia[0]->getCabeceraDocumentosDaop->ofe_id, $existeGuia[0]->getCabeceraDocumentosDaop->rfa_id, trim($existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo), false, 'SI', 'NO');

                    if($consecutivo === false) {
                        $crearAgendamientoDo = false;
                        $guiasErrores[$valores['guia']] = [
                            'tipodoc'           => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_clasificacion,
                            'rfa_prefijo'       => $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo,
                            'cdo_consecutivo'   => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo,
                            'razon'             => 'Documento [' . $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo . $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo . '], el estado del documento es PROVISIONAL y no se pudo generar el consecutivo DEFINITIVO, el periodo [' . date('Ym') . '] no se encuentra aperturado.',
                            'valores'           => json_encode($valores)
                        ];

                        $this->guardarGuiaBaseDatos(empty($sufijoRuta) ? 'SFTP' : 'WEB', $valores);
                    } else {
                        $actualizarConsecutivo = EtlCabeceraDocumentoDaop::where('cdo_id', $existeGuia[0]->cdo_id)
                            ->update([
                                'cdo_consecutivo' => $consecutivo,
                                'estado'          => 'ACTIVO'
                            ]);
                        
                        if (!$actualizarConsecutivo) {
                            $crearAgendamientoDo = false;
                            $guiasErrores[$valores['guia']] = [
                                'tipodoc'           => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_clasificacion,
                                'rfa_prefijo'       => $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo,
                                'cdo_consecutivo'   => $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo,
                                'razon'             => 'Documento [' . $existeGuia[0]->getCabeceraDocumentosDaop->rfa_prefijo . $existeGuia[0]->getCabeceraDocumentosDaop->cdo_consecutivo . '], error al actualizar el consecutivo DEFINITIVO.',
                                'valores'           => json_encode($valores)
                            ];

                            $this->guardarGuiaBaseDatos(empty($sufijoRuta) ? 'SFTP' : 'WEB', $valores);
                        }
                    }
                }

                if ($crearAgendamientoDo == true) {
                    // Verifica que NO exista un estado DO Exitoso para el documento
                    $doExitoso = EtlEstadosDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $existeGuia[0]->cdo_id)
                        ->where('est_estado', 'DO')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->first();

                    if(!$doExitoso) {
                        $agendarUbl[] = $existeGuia[0]->cdo_id;
                    }
                    $writer->creaActualizaEstadoPickupCash($existeGuia[0]->cdo_id, $valores, $fechaInicioProcesamiento, date('Y-m-d H:i:s'), number_format((microtime(true) - $timestampInicio), 3, '.', ''), 'FINALIZADO', auth()->user()->usu_id);
                } else {
                    //Si se genero error al actualizar a estado DEFINITIVO se actualiza estado pickucash pero no finalizado
                    $writer->creaActualizaEstadoPickupCash($existeGuia[0]->cdo_id, $valores, $fechaInicioProcesamiento, date('Y-m-d H:i:s'), number_format((microtime(true) - $timestampInicio), 3, '.', ''), null, auth()->user()->usu_id);
                }
            } else {
                $writer->creaActualizaEstadoPickupCash($existeGuia[0]->cdo_id, $valores, $fechaInicioProcesamiento, date('Y-m-d H:i:s'), number_format((microtime(true) - $timestampInicio), 3, '.', ''), null, auth()->user()->usu_id);
            }

            // Se borra del histórico la guía que cruzo y las guías que se encuentren repetidas
            $writer->eliminarGuiasPickupCash($valores['guia']);
        }
    }

    /**
     * Procesa el resultado de un un archivo/registro de Pickup Cash para generar los agendamientos y estados que se requieran, igualmente mover de ubicación los archivos cuando el origen del proceso es 'archivo'.
     *
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE
     * @param User $user Instancia del usuario
     * @param array $proceso Array de resultado del procesamiento de achivos o base de datos
     * @param string $nombreArchivo Nombre del archivo procesado
     * @param string $sufijoRuta Sufijo de la ruta procesada
     * @return void
     */
    private function procesarResultado(ConfiguracionObligadoFacturarElectronicamente $ofe = null, User $user, array $proceso, string $nombreArchivo = '', string $rutaArchivo = '', $sufijoRuta = '') {
        // Procesa documentos que deben agendarse para UBL
        if(!empty($proceso['agendarUbl'])) {
            $agendamiento = AdoAgendamiento::create([
                'usu_id'                    => $user->usu_id,
                'bdd_id'                    => $user->getBaseDatos->bdd_id, 
                'age_proceso'               => 'UBL',
                'age_cantidad_documentos'   => count($proceso['agendarUbl']),
                'age_prioridad'             => $this->ofeAgendamientos && !empty($this->ofeAgendamientos->ofe_prioridad_agendamiento) ? $this->ofeAgendamientos->ofe_prioridad_agendamiento : null,
                'usuario_creacion'          => $user->usu_id,
                'estado'                    => 'ACTIVO'
            ]);

            // Marca los documentos con el agendamiento
            foreach($proceso['agendarUbl'] as $cdo_id) {
                EtlCabeceraDocumentoDaop::find($cdo_id)
                    ->update([
                        'cdo_procesar_documento'       => 'SI',
                        'cdo_fecha_procesar_documento' => date('Y-m-d H:i:s')
                    ]);

                $writer = new WriterInput();
                $writer->crearEstado(
                    $cdo_id,
                    'UBL',
                    null,
                    null,
                    $agendamiento->age_id,
                    $user->usu_id,
                    null,
                    null,
                    null,
                    null,
                    $user->usu_id,
                    'ACTIVO'
                );
            }
        }

        // Procesamiento para registrar los errores en la tabla etl_procesamiento_json
        if(!empty($proceso['guiasErrores'])) {
            $totalNoExiste    = 0;
            foreach($proceso['guiasErrores'] as $guia => $error) {
                // Se registra en el log de errores las guías con error diferente a 'No existe'
                if($error['razon'] != 'No existe') {
                    $arrErrores = [];
                    $arrErrores[] = [
                        'documento'           => $error['tipodoc'] . $error['rfa_prefijo'] . $error['cdo_consecutivo'],
                        'consecutivo'         => $error['cdo_consecutivo'],
                        'prefijo'             => $error['rfa_prefijo'],
                        'errors'              => [ 'Guía: ' . $guia . ' - ' . $error['razon'] ],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'archivo'             => $nombreArchivo,
                        'traza'               => ''
                    ];

                    $this->registrarError(
                        $error['valores'],
                        json_encode($arrErrores),
                        $user->usu_id
                    );
                } else {
                    $totalNoExiste++;
                }
            }

            // Si el total de guías procesadas es igual al total de guías con error 'No existe'
            // se deja registro en el log indicando que ninguna guía del archivo existe en el sistema
            if($proceso['totalGuias'] == $totalNoExiste) {
                $arrNoExisten = [];
                $arrNoExisten[] = [
                    'documento'           => $nombreArchivo,
                    'consecutivo'         => '',
                    'prefijo'             => '',
                    'errors'              => [ 'Ninguna de las guías contenidas en el archivo existen en el sistema' ],
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s'),
                    'archivo'             => $nombreArchivo,
                    'traza'               => ''
                ];

                $this->registrarError(
                    json_encode([]),
                    json_encode($arrNoExisten),
                    $user->usu_id
                );
            }

            // El archivo generó errores, entonces se mueve a la carpeta de fallidos
            $archivoMover = $this->baseDatosDhlExpress . $this->nitDhlExpress . $ofe->ofe_conexion_ftp['fallidos_pickup_cash' . $sufijoRuta] . $nombreArchivo;
        } elseif(empty($proceso['guiasErrores'])) {
            // Archivo sin errores, se mueve a la carpeta de exitosos
            $archivoMover = $this->baseDatosDhlExpress . $this->nitDhlExpress . $ofe->ofe_conexion_ftp['exitosos_pickup_cash' . $sufijoRuta] . $nombreArchivo;
        }

        if(isset($archivoMover))
            $this->moverArchivo($rutaArchivo . '.procesado', $archivoMover);
    }

    /**
     * Registra en la base de datos las guías que generaron error y que deberán ser procesadas posteriormente.
     * 
     * Se exceptuan del proceso las guías con error 'No Existe'.
     * Este proceso se implementó en lugar de guardar archivos en el disco local del proyecto
     *
     * @param string $interfaz Interfaz mediante la cual se cargó el archivo (SFTP o WEB)
     * @param array $valores Valores extraidos del archivo para la guía
     * @return void
     */
    public function guardarGuiaBaseDatos($interfaz, $valores) {
        $registroGuia = [
            'gpc_interfaz'             => $interfaz,
            'gpc_fecha_factura'        => array_key_exists('fecha_factura', $valores) && !empty($valores['fecha_factura']) ? $valores['fecha_factura'] : null,
            'gpc_fecha_generacion_awb' => $valores['fecha_generacion_awb'],
            'gpc_cuenta_cliente'       => array_key_exists('cuenta_cliente', $valores) && !empty($valores['cuenta_cliente']) ? $valores['cuenta_cliente'] : null,
            'gpc_paquete_documento'    => array_key_exists('paquete_documento', $valores) && !empty($valores['paquete_documento']) ? $valores['paquete_documento'] : null,
            'gpc_guia'                 => $valores['guia'],
            'gpc_area_servicio'        => array_key_exists('area_servicio', $valores) && !empty($valores['area_servicio']) ? $valores['area_servicio'] : null,
            'gpc_codigo_estacion'      => array_key_exists('codigo_estacion', $valores) && !empty($valores['codigo_estacion']) ? $valores['codigo_estacion'] : null,
            'gpc_oficina_venta'        => array_key_exists('oficina_venta', $valores) && !empty($valores['oficina_venta']) ? $valores['oficina_venta'] : null,
            'gpc_organizacion_ventas'  => array_key_exists('organizacion_ventas', $valores) && !empty($valores['organizacion_ventas']) ? $valores['organizacion_ventas'] : null,
            'gpc_numero_externo'       => array_key_exists('numero_externo', $valores) && !empty($valores['numero_externo']) ? $valores['numero_externo'] : null,
            'gpc_numero_nota'          => array_key_exists('numero_nota', $valores) && !empty($valores['numero_nota']) ? $valores['numero_nota'] : null,
            'gpc_estacion_origen'      => array_key_exists('estacion_origen', $valores) && !empty($valores['estacion_origen']) ? $valores['estacion_origen'] : null,
            'gpc_estacion_destino'     => array_key_exists('estacion_final', $valores) && !empty($valores['estacion_final']) ? $valores['estacion_final'] : null,
            'gpc_texto_final'          => array_key_exists('texto_final', $valores) && !empty($valores['texto_final']) ? $valores['texto_final'] : null,
            'gpc_importe_total'        => $valores['importe_total'],
            'gpc_route_code'           => array_key_exists('route_code', $valores) && !empty($valores['route_code']) ? $valores['route_code'] : null,
            'usuario_creacion'         => auth()->user()->usu_id,
            'estado'                   => 'ACTIVO'
        ];

        PryGuiaPickupCash::create($registroGuia);
    }
}
