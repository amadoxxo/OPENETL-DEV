<?php
namespace App\Console\Commands\Paisagro;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Models\AdoAgendamiento;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccion;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Parser de archivos txt para la carga de documentos electronicos por parte de Paisagro.
 * 
 * Class PaisagroProcesarDocumentosCommand
 * @package App\Console\Commands\Paisagro
 */
class PaisagroProcesarDocumentosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paisagro-procesar-documentos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa archivos TXT de Paisagro para la emisión de documentos';

    /**
     * Expresión regular para el inicio del archivo.
     */
    protected $inicioRegex = '/^[x]+Inicio/';

    /**
     * Expresión regular para definición de una sección.
     */
    protected $seccionRegex = '/^<\/?([\w\s]+)[*]*\/?>/';

    /**
     * Expresión regular para las líneas de la forma clave: valor.
     */
    protected $claveValorRegex = '/^[\s]*([\S\s]+?):[\s]*([\s\S]+)/';

    /**
     * Arreglo de valores del archivo.
     */
    protected $data = [];

    /**
     * Tipo de documento en procesamiento
     */
    protected $tipo = '';

    /**
     *
     */
    protected $totalRetencionesSugeridas = 0;

    /**
     * @var int Total de registros a procesar
     */
    protected $total_procesar = 15;

    /**
     * Disco de trabajo para el procesamiento de los archivos
     * @var string
     */
    private $discoTrabajo = 'ftpPaisagro';

    /**
     * @var string Nombre del directorio de la BD
     */
    protected $baseDatosPaisagro = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * @var string Nombre del directorio del NIT de Paisagro
     */
    protected $nitPaisagro = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * @var string Modo de lectura de los archivos, puede ser 'local' o 'remoto'
     */
    protected $modoLectura = 'remoto';

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
        ini_set('memory_limit', '1024M');

        $arrCorreos = [
            'paisagro@openio.co',
            'paisagro01@openio.co'
        ];

        // Se selecciona un correo de manera aleatoria, a éste correo quedará ligado todo el procesamiento del registro
        $correo = $arrCorreos[array_rand($arrCorreos)];

        // Obtiene el usuario relacionado con Paisagro
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

            // Obtiene datos de conexión y ruta de entrada 'IN' para Paisagro
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_conexion_ftp', 'ofe_prioridad_agendamiento'])
                ->where('ofe_identificacion', '830119738')
                ->first();

            // Array de rutas a consultar
            $arrPaths = [];
            if (isset($ofe->ofe_conexion_ftp['entrada_fc']) && $ofe->ofe_conexion_ftp['entrada_fc'] && $ofe->ofe_conexion_ftp['entrada_fc']) {
                $arrPaths = array(
                    $ofe->ofe_conexion_ftp['entrada_fc'],
                    $ofe->ofe_conexion_ftp['entrada_nc'],
                    $ofe->ofe_conexion_ftp['entrada_nd']
                );

                // Si el modo de lectura de archivos es remoto, se debe conectar al SFTP del cliente para bajar los archivos al disco local y continuar su procesamiento
                if ($this->modoLectura == 'remoto') {
                    $arrProcesadosLocal = [];
                    $this->configurarDiscoSftpPaisagro($ofe);
                    $this->obtenerArchivosRemotos($ofe, $arrPaths, $user);
                }
            }

            // Itera el array de rutas para procesar los archivos que se encuentren
            foreach ($arrPaths as $path) {
                unset($archivos);
                // Obtiene lista de archivos
                $prefijo  = $this->baseDatosPaisagro . $this->nitPaisagro;
                $archivos = Storage::disk($this->discoTrabajo)->files($prefijo . $path);
                if (count($archivos) > 0) {
                    // Funcionalidad temporal para dejar registrado en un archivo de texto el listado de archivos que se leyeron en el proceso
                    $listaArchivos = '';
                    foreach ($archivos as $archivo) {
                        $listaArchivos .= $archivo . "\r\n";
                    }
                    Storage::put('Paisagro/proceso_paisagro_' . date('YmdHis') . '.txt', $listaArchivos);
                    // Fin funcionalidad temporal
                    
                    //Para que el proceso no se bloque se deben traer los documentos que se encuentren sin la extension .procesado
                    $contador = 0;
                    $archivosSinProcesar = array();
                    foreach ($archivos as $archivo) {
                        $extension = File::extension($archivo);
                        if (strtolower($extension) === 'txt') {
                            $archivosSinProcesar[] = $archivo;
                            $contador++;
                        }
                        if ($contador == $this->total_procesar) {
                            break;
                        }
                    }
                    $archivos = $archivosSinProcesar;

                    // Array de documentos procesados que pasarán por EDI
                    $payload = [
                        'documentos' => [
                            'FC' => [],
                            'ND' => [],
                            'NC' => []
                        ],
                    ];

                    foreach ($archivos as $indiceArchivo => $archivo) {
                        // Array que almacena errores en el procesamiento de los documentos
                        $arrErrores = [];

                        // Array que almacena errores en el procesamiento de los documentos - Por excepciones
                        $arrExcepciones = [];

                        $extension = File::extension($archivo);
                        $nombre    = File::name($archivo);

                        if (strtolower($extension) === 'txt' && Storage::disk($this->discoTrabajo)->exists($archivo) && Storage::disk($this->discoTrabajo)->size($archivo) > 0) {
                            try {
                                // Renombra el documento para que no sea procesado por otra instancia del comando
                                Storage::disk($this->discoTrabajo)->rename($archivo, $archivo . '.procesado');

                                // Inicia el procesamiento del archivo
                                $res = $this->_procesarArchivo($archivo . '.procesado', $nombre . '.' . $extension);
                                if ($res['statusCode'] != 200) {
                                    if(array_key_exists('error_adquirente', $res) && $res['error_adquirente']) {
                                        $arrErrores = [];
                                        $arrErrores[] = [
                                            'documento'           => $res['tipodoc'] . $res['rfa_prefijo'] . $res['cdo_consecutivo'],
                                            'consecutivo'         => $res['cdo_consecutivo'],
                                            'prefijo'             => $res['rfa_prefijo'],
                                            'errors'              => [ $res['response'] ],
                                            'fecha_procesamiento' => date('Y-m-d'),
                                            'hora_procesamiento'  => date('H:i:s'),
                                            'archivo'             => $nombre.'.'.$extension,
                                            'traza'               => ''
                                        ];
                                        
                                        $this->registraProcesamientoJson([
                                            'pjj_tipo'                => (isset($this->tipo) && $this->tipo != '') ? $this->tipo : 'API',
                                            'pjj_json'                => sprintf('{"%s":[]}', $this->tipo),
                                            'pjj_procesado'           => 'SI',
                                            'pjj_errores'             => json_encode($arrErrores),
                                            'age_id'                  => 0,
                                            'age_estado_proceso_json' => 'FINALIZADO',
                                            'usuario_creacion'        => $user->usu_id,
                                            'estado'                  => 'ACTIVO'
                                        ]);

                                        $tmpIndiceRuta = 'entrada_' . strtolower($this->tipo);
                                        $ruta = $ofe->ofe_conexion_ftp[$tmpIndiceRuta] . '/bad/';
                                        $file = $ruta . $nombre . '.' . $extension;
                                        $this->_moverArchivo($archivo . '.procesado', $prefijo . $file);
                                        if($this->modoLectura == 'remoto') {
                                            $arrProcesadosLocal[] = [
                                                'archivo'      => $nombre . '.' . $extension,
                                                'ruta_origen'  => $ofe->ofe_conexion_ftp[$tmpIndiceRuta],
                                                'ruta_destino' => $ruta
                                            ];
                                        }
                                    }
                                } else {
                                    // Procesamiento correcto
                                    // Se agrega el documento al array de documentos a procesar por EDI y se mueve el documento al directorio de exitosos
                                    $payload['documentos'][$res['tipodoc']][] = $res['json'];
                                    $tmpIndiceRuta = 'entrada_' . strtolower($res['tipodoc']);
                                    $ruta = $ofe->ofe_conexion_ftp[$tmpIndiceRuta] . '/build/';
                                    $file = $ruta . $nombre . '.' . $extension;
                                    $this->_moverArchivo($archivo . '.procesado', $prefijo . $file);
                                    if($this->modoLectura == 'remoto') {
                                        $arrProcesadosLocal[] = [
                                            'archivo'      => $nombre . '.' . $extension,
                                            'ruta_origen'  => $ofe->ofe_conexion_ftp[$tmpIndiceRuta],
                                            'ruta_destino' => $ruta
                                        ];
                                    }
                                }
                            } catch (\Exception $e) {
                                $arrExcepciones = [];
                                $arrExcepciones[] = [
                                    'documento'           => $nombre.'.'.$extension,
                                    'consecutivo'         => '',
                                    'prefijo'             => '',
                                    'errors'              => [ $e->getMessage()],
                                    'fecha_procesamiento' => date('Y-m-d'),
                                    'hora_procesamiento'  => date('H:i:s'),
                                    'archivo'             => $nombre.'.'.$extension,
                                    'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                                ];

                                $this->registraProcesamientoJson([
                                    'pjj_tipo'                => (isset($this->tipo) && $this->tipo != '') ? $this->tipo : 'API',
                                    'pjj_json'                => sprintf('{"%s":[]}', $this->tipo),
                                    'pjj_procesado'           => 'SI',
                                    'pjj_errores'             => json_encode($arrExcepciones),
                                    'age_id'                  => 0,
                                    'age_estado_proceso_json' => 'FINALIZADO',
                                    'usuario_creacion'        => $user->usu_id,
                                    'estado'                  => 'ACTIVO'
                                ]);

                                $tmpIndiceRuta = 'entrada_' . strtolower($this->tipo);
                                $ruta = $ofe->ofe_conexion_ftp[$tmpIndiceRuta] . '/bad/';
                                $file = $ruta . $nombre . '.' . $extension;
                                $this->_moverArchivo($archivo . '.procesado', $prefijo . $file);
                                if($this->modoLectura == 'remoto') {
                                    $arrProcesadosLocal[] = [
                                        'archivo'      => $nombre . '.' . $extension,
                                        'ruta_origen'  => $ofe->ofe_conexion_ftp[$tmpIndiceRuta],
                                        'ruta_destino' => $ruta
                                    ];
                                }
                            }
                        } elseif (strtolower($extension) === 'procesado') {
                            $ultimaModificacion = Storage::disk($this->discoTrabajo)->lastModified($archivo);
                            $tiempoActual = strtotime('-300 seconds');

                            if ($ultimaModificacion < $tiempoActual) {
                                Storage::disk($this->discoTrabajo)->rename($archivo, $prefijo . $path . $nombre);
                            }
                        }
                    }

                    // Se procesó el bloque de documentos, ahora se crea el agendamieto para EDI y se registra el objeto Json
                    if(count($payload['documentos']['FC']) > 0 || count($payload['documentos']['NC']) > 0 || count($payload['documentos']['ND']) > 0) {
                        $totalDocumentos = count($payload['documentos']['FC']) + count($payload['documentos']['NC']) + count($payload['documentos']['ND']);
                        $agendamientoEdi = AdoAgendamiento::create([
                            'usu_id'                  => $user->usu_id,
                            'bdd_id'                  => $user->getBaseDatos->bdd_id,
                            'age_proceso'             => 'EDI',
                            'age_cantidad_documentos' => $totalDocumentos,
                            'age_prioridad'           => !empty($ofe->ofe_prioridad_agendamiento) ? $ofe->ofe_prioridad_agendamiento : null,
                            'usuario_creacion'        => $user->usu_id,
                            'estado'                  => 'ACTIVO'
                        ]);
                        
                        $this->registraProcesamientoJson([
                            'pjj_tipo'                => 'INTEGRACION',
                            'pjj_json'                => json_encode($payload),
                            'pjj_procesado'           => 'NO',
                            'pjj_errores'             => null,
                            'age_id'                  => $agendamientoEdi->age_id,
                            'age_estado_proceso_json' => null,
                            'usuario_creacion'        => $user->usu_id,
                            'estado'                  => 'ACTIVO'
                        ]);
                    }
                }
            }

            if($this->modoLectura == 'remoto' && !empty($arrProcesadosLocal)) {
                $this->moverArchivosRemotos($arrProcesadosLocal);
            }
        }
    }

    /**
     * Realiza el registro de un procesamiento json en el modelo correspondiente
     *
     * @param array $valores Array que contiene la información de los campos para la creación dle registro
     * @return void
     */
    private function registraProcesamientoJson($valores){
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
     * Mueve un archivo de una ubicación a otra
     *
     * @param string $origen Path relativo del archivo origen
     * @param string $destino Pat relativo del archivo destino
     * @return void
     */
    private function _moverArchivo($origen, $destino) {
      if (Storage::disk($this->discoTrabajo)->exists($destino)) {
          Storage::disk($this->discoTrabajo)->delete($destino);
      }
      if (Storage::disk($this->discoTrabajo)->exists($origen)) {
          Storage::disk($this->discoTrabajo)->move($origen, $destino);
      }
    }

    /**
     * Elimina el caracter coma de un string.
     * 
     * @param string $num String a normalizar
     * @return string $num String con el remplazo hecho 
     */
    private function normalizeNum($num) {
        return str_replace(',', '', $num);
    }

    /**
     * Da formato a un número.
     * 
     * @param float $value Valor flotante a formatear
     * @return float $value Valor flotante con formato
     */
    private function getDBNum($value) {
        if($value !== '') {
            $value = $this->normalizeNum($value);
            $value = number_format($value, 2, '.', '');
        }
        return $value;
    }

    /**
     * Intenta codificar a utf-8 las diferentes lineas que componen el archivo de entrada
     *
     * @param $line
     * @return bool|false|string|string[]|null
     */
    private function fixEncoding($line) {
        if (($codificacion = mb_detect_encoding($line)) !== false)
            return mb_convert_encoding($line, "UTF-8", $codificacion);
        return mb_convert_encoding($line, "ISO-8859-1");
    }

    /**
     * Convierte una línea en array separando los valores mediante pipe.
     * 
     * A cada linea se le aplica la codificación de caracteres y a cada item en el array resultante se le aplica la función trim
     * 
     * @param string $linea Línea en procesamiento
     */
    private function explotarLinea($linea) {
        $linea = $this->fixEncoding($linea);
        return array_map('trim', explode('|', $linea));
    }

    /**
     * Obtiene los datos paramétricos para Pais, Departamento y Municipio.
     * 
     * @param string $codPais Código del país
     * @param string $departamento Nombre del departamento
     * @param string $municipio Nombre dle municipio
     * @return array Conteniendo posiciones para el código del país, departmaneto y municipio
     */
    private function obtenerPaisDeptoMun($codPais, $departamento, $municipio) {
        $pais = ParametrosPais::select(['pai_id', 'pai_codigo'])
            ->where('pai_codigo', $codPais)
            ->first();

        if(!$pais) {
            return [
                'pai_codigo' => '',
                'dep_codigo' => '',
                'mun_codigo' => ''
            ];
        }

        $departamento = ParametrosDepartamento::select(['dep_id', 'dep_codigo'])
            ->where('dep_codigo', $departamento)
            ->where('pai_id', $pais->pai_id)
            ->first();

        if(!$departamento) {
            return [
                'pai_codigo' => $pais->pai_codigo,
                'dep_codigo' => '',
                'mun_codigo' => ''
            ];
        }

        $municipio = ParametrosMunicipio::select(['mun_id', 'mun_codigo'])
            ->where('mun_codigo', $municipio)
            ->where('pai_id', $pais->pai_id)
            ->where('dep_id', $departamento->dep_id)
            ->first();

        if(!$municipio) {
            return [
                'pai_codigo' => $pais->pai_codigo,
                'dep_codigo' => $departamento->dep_codigo,
                'mun_codigo' => ''
            ];
        }

        return [
            'pai_codigo' => $pais->pai_codigo,
            'dep_codigo' => $departamento->dep_codigo,
            'mun_codigo' => $municipio->mun_codigo
        ];
    }

    /**
     * Obtiene data de una línea cuyo valor del primer índice es IT
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosIT($arrLinea) {
        if(($arrLinea[12] == '01' || $arrLinea[12] == '02' || $arrLinea[12] == '03' || $arrLinea[12] == '04')) {
            $this->tipo = 'FC';
        } elseif($arrLinea[12] == '91') {
            $this->tipo = 'NC';
        } elseif($arrLinea[12] == '92') {
            $this->tipo = 'ND';
        } else {
            $this->tipo = 'FC';
        }

        $this->data['tde_codigo']                               = $arrLinea[12];
        $this->data['ofe_identificacion']                       = $arrLinea[2];
        $this->data['rfa_prefijo']                              = $arrLinea[13];
        $this->data['cdo_consecutivo']                          = $arrLinea[14];
        $this->data['cdo_medios_pago'][0]['fpa_codigo']         = (strtoupper($arrLinea[15]) == 'CONTADO') ? '1' : '2';
        list($this->data['cdo_fecha'], $this->data['cdo_hora']) = explode('T', $arrLinea[16]);
        
        $fechaHoraVence = explode('T', $arrLinea[17]);
        $this->data['cdo_vencimiento'] = $fechaHoraVence[0];
        if($this->data['cdo_vencimiento'] != '') {
            $this->data['cdo_medios_pago'][0]['men_fecha_vencimiento'] = $fechaHoraVence[0];
        }

        $this->data['cdo_representacion_grafica_documento'] = '1';
        $this->data['cdo_representacion_grafica_acuse']     = '1';
        $this->data['cdo_envio_dian_moneda_extranjera']     = 'NO';
    }

    /**
     * Obtiene data de una línea cuyo valor del primer índice es IC
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosIC($arrLinea) {
        $paisDeptoMun                     = $this->obtenerPaisDeptoMun($arrLinea[10], $arrLinea[14], $arrLinea[15]);
        if($paisDeptoMun['pai_codigo'] == '') {
            return [
                'tipodoc'           => $this->tipo,
                'rfa_prefijo'       => $this->data['rfa_prefijo'],
                'cdo_consecutivo'   => $this->data['cdo_consecutivo'],
                'statusCode'        => 404,
                'error_adquirente'  => true,
                'response'          => 'No se encontró el país del adquirente para el código ['.$arrLinea[10].']'
            ];
        } elseif($paisDeptoMun['dep_codigo'] == '') {
            return [
                'tipodoc'           => $this->tipo,
                'rfa_prefijo'       => $this->data['rfa_prefijo'],
                'cdo_consecutivo'   => $this->data['cdo_consecutivo'],
                'statusCode'        => 404,
                'error_adquirente'  => true,
                'response'          => 'No se encontró el departamento para el país del adquirente. País ['.$arrLinea[10].'] - Departamento ['.$arrLinea[14].']'
            ];
        } elseif($paisDeptoMun['mun_codigo'] == '') {
            return [
                'tipodoc'           => $this->tipo,
                'rfa_prefijo'       => $this->data['rfa_prefijo'],
                'cdo_consecutivo'   => $this->data['cdo_consecutivo'],
                'statusCode'        => 404,
                'error_adquirente'  => true,
                'response'          => 'No se encontró la ciudad para el país y departamento del adquirente. País ['.$arrLinea[10].'] - Departamento ['.$arrLinea[14].'] - Ciudad ['.$arrLinea[15].']'
            ];
        }

        $correosAdquirente = explode(',', $arrLinea[13]);

        $this->data['mon_codigo']         = $arrLinea[1];
        $this->data['adq_identificacion'] = $arrLinea[3];

        $this->data['adquirente']['ofe_identificacion']             = $this->data['ofe_identificacion'];
        $this->data['adquirente']['adq_identificacion']             = $arrLinea[3];
        $this->data['adquirente']['adq_razon_social']               = ($arrLinea[11] == '1') ? $arrLinea[4] : '';
        $this->data['adquirente']['adq_nombre_comercial']           = ($arrLinea[11] == '1') ? $arrLinea[5] : '';
        $this->data['adquirente']['adq_primer_apellido']            = ($arrLinea[11] == '2') ? $arrLinea[6] : '';
        $this->data['adquirente']['adq_segundo_apellido']          = ($arrLinea[11] == '2') ? $arrLinea[7] : '';
        $this->data['adquirente']['adq_primer_nombre']              = ($arrLinea[11] == '2') ? $arrLinea[8] : '';
        $this->data['adquirente']['adq_otros_nombres']              = ($arrLinea[11] == '2') ? $arrLinea[9] : '';
        $this->data['adquirente']['tdo_codigo']                     = $arrLinea[2];
        $this->data['adquirente']['toj_codigo']                     = $arrLinea[11];
        $this->data['adquirente']['pai_codigo']                     = $paisDeptoMun['pai_codigo'];
        $this->data['adquirente']['dep_codigo']                     = $paisDeptoMun['dep_codigo'];
        $this->data['adquirente']['mun_codigo']                     = $paisDeptoMun['mun_codigo'];
        $this->data['adquirente']['cpo_codigo']                     = $arrLinea[19];
        $this->data['adquirente']['adq_direccion']                  = $arrLinea[16];
        $this->data['adquirente']['adq_telefono']                   = $arrLinea[17];
        $this->data['adquirente']['pai_codigo_domicilio_fiscal']    = '';
        $this->data['adquirente']['dep_codigo_domicilio_fiscal']    = '';
        $this->data['adquirente']['mun_codigo_domicilio_fiscal']    = '';
        $this->data['adquirente']['cpo_codigo_domicilio_fiscal']    = '';
        $this->data['adquirente']['adq_direccion_domicilio_fiscal'] = '';
        $this->data['adquirente']['adq_correo']                     = $correosAdquirente[0];
        $this->data['adquirente']['adq_matricula_mercantil']        = '';
        $this->data['adquirente']['adq_correos_notificacion']       = $arrLinea[13];
        $this->data['adquirente']['rfi_codigo']                     = $arrLinea[12];
        $this->data['adquirente']['ref_codigo']                     = [$arrLinea[20]];
        $this->data['adquirente']['responsable_tributos']           = [$arrLinea[21]];

        $this->data['cdo_informacion_adicional']['vendedor']        = $arrLinea[18];
    }

    /**
     * Obtiene data de una línea cuyo valor del primer índice es T
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosT($arrLinea) {
        $this->data['cdo_valor_sin_impuestos'] = $this->getDBNum($this->normalizeNum($arrLinea[9]));
        $this->data['cdo_impuestos'] = $this->getDBNum($this->normalizeNum($arrLinea[10]));
        $this->data['cdo_total'] = $this->getDBNum($this->normalizeNum($arrLinea[11]));
    }

    /**
     * Obtiene data de una línea cuyo valor del primer índice es DE
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosDE($arrLinea) {
        $secuencia = (string)(count($this->data['items']) + 1);
        $cdo_total = floatval($this->getDBNum($this->normalizeNum($arrLinea[13]))) - floatval($this->getDBNum($this->normalizeNum($arrLinea[12])));
        $this->data['items'][] = [
            'ddo_secuencia'       => $secuencia,
            'cpr_codigo'          => '999',
            'ddo_codigo'          => $arrLinea[1],
            'ddo_tipo_item'       => 'IP',
            'ddo_descripcion_uno' => $arrLinea[3],
            'ddo_cantidad'        => $this->getDBNum($this->normalizeNum($arrLinea[5])),
            'ddo_valor_unitario'  => trim($arrLinea[9])  === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea[9])),
            'ddo_total'           => trim($arrLinea[13]) === "" ? "0.00" : $this->getDBNum($cdo_total),
            'und_codigo'          => $arrLinea[14],

            'ddo_informacion_adicional' => [
                'codigo_auxiliar' => $arrLinea[2],
                'lote'            => $arrLinea[4],
                'precio_publico'  => $this->getDBNum($this->normalizeNum($arrLinea[6])),
            ]
        ];

        // Muestra Comercial
        if(trim($arrLinea[13]) === "" || $arrLinea[13] == 0) {
            $this->data['items'][(count($this->data['items']) - 1)]['ddo_indicador_muestra'] = 'true';
            $this->data['items'][(count($this->data['items']) - 1)]['ddo_precio_referencia'] = [
                'pre_codigo'        => '01',
                'ddo_valor_muestra' => $this->getDBNum($this->normalizeNum($arrLinea[6]))
            ];
        }

        // Notas
        if($arrLinea[15] != '') {
            $this->data['items'][(count($this->data['items']) - 1)]['ddo_notas'] = $arrLinea[15];
        }

        // Impuestos
        if($arrLinea[12] != '' && $arrLinea[12] > 0) {
            $this->data['tributos'][] = [
                'ddo_secuencia' => $secuencia,
                'tri_codigo' => $arrLinea[10],
                'iid_valor' => $this->getDBNum($this->normalizeNum($arrLinea[12])),
                'iid_porcentaje' => [
                        'iid_base'       => $this->getDBNum($this->normalizeNum($arrLinea[13] - $arrLinea[12])),
                        'iid_porcentaje' => $this->getDBNum($this->normalizeNum($arrLinea[11]))
                ]
            ];
        }
    }

    /**
     * Obtiene el cufe de un documento en el sistema.
     *
     * @param string $tipoDocRef Tipo del documento referencia
     * @param string $prefijo Prefijo del documento referencia
     * @param string $consecutivo Consecutivo del documento referencia
     * @return string Cufe del documento
     */
    public function obtenerCufeDocumento($tipoDocRef, $prefijo, $consecutivo) {
        $cufe = EtlCabeceraDocumentoDaop::select(['cdo_id', 'cdo_cufe'])
            ->where('cdo_clasificacion', $tipoDocRef)
            ->where('rfa_prefijo', trim($prefijo))
            ->where('cdo_consecutivo', $consecutivo)
            ->first();

        if(!$cufe)
            $cufe = EtlFatDocumentoDaop::select(['cdo_id', 'cdo_cufe'])
                ->where('cdo_clasificacion', $tipoDocRef)
                ->where('rfa_prefijo', trim($prefijo))
                ->where('cdo_consecutivo', $consecutivo)
                ->first();

        if($cufe) {
            return $cufe->cdo_cufe;
        } else {
            throw new \Exception('No existe en el sistema el documento referencia ['.$tipoDocRef.' - ' . $prefijo . $consecutivo . ']');
        }
    }

    /**
     * Integra la data correspondiente al documento referencia es una NC o ND la que se esta procesando.
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function documentoReferencia($arrLinea) {
        $tipoDocRef = null;
        if(($arrLinea[2] == '01' || $arrLinea[2] == '02' || $arrLinea[2] == '03' || $arrLinea[2] == '04')) {
            $tipoDocRef = 'FC';
        } elseif($arrLinea[2] == '91') {
            $tipoDocRef = 'NC';
        } elseif($arrLinea[2] == '92') {
            $tipoDocRef = 'ND';
        }

        $this->data['cdo_documento_referencia'][] = [
            'clasificacion'    => $tipoDocRef,
            'prefijo'          => $arrLinea[3],
            'consecutivo'      => $arrLinea[4],
            'cufe'             => $this->obtenerCufeDocumento($tipoDocRef, $arrLinea[3], $arrLinea[4]),
            'fecha_emision'    => $arrLinea[5]
        ];

        // Conceptos de corrección
        $conceptoCorreccion = ParametrosConceptoCorreccion::select(['cco_id', 'cco_codigo', 'cco_descripcion'])
            ->where('cco_codigo', $arrLinea[6])
            ->where('cco_tipo', $this->tipo)
            ->first();

        if($conceptoCorreccion) {
            $concepto = $this->fixEncoding($conceptoCorreccion->cco_descripcion);
            $concepto = mb_convert_encoding($concepto, 'UTF-8', 'UTF-8');
            $this->data['cdo_conceptos_correccion'][] = [
                'cco_codigo'                 => $conceptoCorreccion->cco_codigo,
                'cdo_observacion_correccion' => $concepto
            ];
        } else {
            throw new \Exception('El concepto de corrección con código ['.$arrLinea[6].'] no existe en el sistema para el tipo de documento ['.$this->tipo.']');
        }
    }

    /**
     * Obtiene data de una línea cuyo valor del primer índice es IA
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosIA($arrLinea) {
        switch($arrLinea[1]) {
            case 'TipoOperacion':
                $this->data['top_codigo'] = $arrLinea[2];
                break;

            case 'RETE';
            case 'RICA';
            case 'RIVA':
                if($arrLinea[4] != '' && $arrLinea[4] > 0) { // VALIDAR LOS TRES VALORES PORQUE DEBEN VENIR
                    $this->data['cdo_detalle_retenciones_sugeridas'][] = [
                        'tipo'       => ($arrLinea[1] == 'RETE') ? 'RETEFUENTE' : (($arrLinea[1] == 'RIVA') ? 'RETEIVA' : 'RETEICA'),
                        'razon'      => ($arrLinea[1] == 'RETE') ? 'RETEFUENTE' : (($arrLinea[1] == 'RIVA') ? 'RETEIVA' : 'RETEICA'),
                        'porcentaje' => $this->getDBNum($this->normalizeNum($arrLinea[3])),
                        'valor_moneda_nacional' =>  [
                            'base'  => $this->getDBNum($this->normalizeNum($arrLinea[2])),
                            'valor' => $this->getDBNum($this->normalizeNum($arrLinea[4]))
                        ]
                    ];
                    $this->totalRetencionesSugeridas += floatval($this->getDBNum($this->normalizeNum($arrLinea[4])));
                }
                break;

            case 'Resol.Dian':
                $this->data['rfa_resolucion'] = $arrLinea[2];
                break;

            case 'Observacion':
                $this->data['cdo_observacion'] = [$arrLinea[2]];
                break;

            case 'FormaDePago':
                $medioPago = explode(':', $arrLinea[2]);
                $this->data['cdo_medios_pago'][0]['mpa_codigo'] = trim($medioPago[0]);
                break;

            case 'Matricula':
                $this->data['adquirente']['adq_matricula_mercantil'] = $arrLinea[2];
                break;

            case 'NCND':
                if($this->tipo == 'NC' || $this->tipo == 'ND') {
                    $this->documentoReferencia($arrLinea);
                }
                break;

            default:
                break;
        }
    }

    /**
     * Procesa el archivo indicado, parsea sus datos para poder almacenar el resultado del procesamiento en la base de datos.
     * 
     * @param string $rutaArchivo Ruta del archivo a procesar
     * @param string $archivoNombreOriginal Nombre original del archivo a procesar
     */
    private function _procesarArchivo($rutaArchivo, $archivoNombreOriginal) {
        $this->data                      = [];
        $this->tipo                      = '';
        $this->data['items']             = [];
        $this->data['tributos']          = [];
        $this->totalRetencionesSugeridas = 0;

        $storagePath = Storage::disk($this->discoTrabajo)->getDriver()->getAdapter()->getPathPrefix();
        $filename = $storagePath . $rutaArchivo;

        $handle = fopen($filename, 'r');
        while (($line = fgets($handle)) !== false) {
            $line = $this->fixEncoding($line);
            if (strlen(preg_replace("/[\r\n|\n|\r]+/", '', $line)) == 0) {
                // Omite líneas en blanco
                continue;
            }

            $arrLinea = $this->explotarLinea($line);
            // El valor en el primer índice determina como deberá ser procesada la línea
            switch($arrLinea[0]) {
                case 'IT':
                    $this->obtenerDatosIT($arrLinea);
                    break;

                case 'IC':
                    $datosIC = $this->obtenerDatosIC($arrLinea);
                    if(is_array($datosIC) && array_key_exists('error_adquirente', $datosIC)) return $datosIC;
                    break;

                case 'T':
                    $this->obtenerDatosT($arrLinea);
                    break;

                case 'DE':
                    $this->obtenerDatosDE($arrLinea);
                    break;

                case 'IA':
                    $this->obtenerDatosIA($arrLinea);
                    break;

                default:
                    break;
            }
        }

        $this->data['cdo_documento_integracion']                                          = base64_encode(file_get_contents($storagePath . $rutaArchivo));
        $this->data['cdo_informacion_adicional']['archivoprocesado']                      = $archivoNombreOriginal;
        $this->data['cdo_informacion_adicional']['cdo_procesar_documento']                = 'SI';
        $this->data['cdo_informacion_adicional']['cdo_informacion_adicional_excluir_xml'] = ['archivoprocesado'];
        if($this->totalRetencionesSugeridas > 0) { $this->data['cdo_retenciones_sugeridas'] = (string)$this->getDBNum($this->normalizeNum($this->totalRetencionesSugeridas)); }

        return [
            'tipodoc'    => $this->tipo,
            'statusCode' => 200,
            'json'       => $this->data
        ];
    }

    /**
     * Configura un disco en tiempo de ejecución para el SFTP de Paisagro
     *
     * @param App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente $ofe Modelo OFE
     * @return void
     */
    private function configurarDiscoSftpPaisagro(ConfiguracionObligadoFacturarElectronicamente $ofe) {
        config([
            'filesystems.disks.SftpPaisagro' => [
                'driver'   => 'sftp',
                'host'     => $ofe->ofe_conexion_ftp['host'],
                'username' => $ofe->ofe_conexion_ftp['username'],
                'password' => $ofe->ofe_conexion_ftp['password'],
            ]
        ]);
    }

    /**
     * Obtiene los archivos del SFTP de Paisagro para almacenarlos localmente
     *
     * @param App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente $ofe Modelo OFE
     * @param array $arrPaths Array con la información de las rutas que se debe recorrer
     * @param App\Http\Models\User $user Modelo Usuario
     * @return void
     */
    private function obtenerArchivosRemotos(ConfiguracionObligadoFacturarElectronicamente $ofe, $arrPaths, User $user) {
        // Ruta completa al disco de Paisagro
        $paisagroDiskPath = Storage::disk($this->discoTrabajo)->getAdapter()->getPathPrefix();

        // Itera el array de rutas para copiar al disco los archivos que encuentre en el momento
        foreach ($arrPaths as $path) {
            // Obtiene lista de archivos
            $archivos = Storage::disk('SftpPaisagro')->files($path);

            // Descarta los directorios de puntos
            $archivos = array_unique(array_values(array_diff($archivos, ['.', '..'])));
            if (!empty($archivos)) {
                //Para que el proceso no se bloque se deben traer los documentos que se encuentren sin la extension .procesado
                $contador = 0;
                $archivosSinProcesar = [];
                foreach ($archivos as $archivo) {
                    $extension = File::extension($archivo);
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
                    $nombre    = File::name($archivo);
                    $extension = File::extension($archivo);

                    // Array que almacena errores en el procesamiento de los documentos
                    $arrErrores = [];
                    if(strpos($path, 'invoice') !== false)
                        $cdo_tipo = 'FC';
                    elseif(strpos($path, 'credit') !== false)
                        $cdo_tipo = 'NC';
                    elseif(strpos($path, 'debit') !== false)
                        $cdo_tipo = 'ND';
                    else
                        $cdo_tipo = 'NO-DETERMINADO';

                    if (strtolower($extension) === 'txt') {
                        $prefijo  = $this->baseDatosPaisagro . $this->nitPaisagro;
                        // Borra el archivo si existe en el disco local
                        if (Storage::disk($this->discoTrabajo)->exists($prefijo . $path . '/' . $nombre . '.' . $extension)) {
                            Storage::disk($this->discoTrabajo)->delete($prefijo . $path . '/' . $nombre . '.' . $extension);
                        }
                        if (Storage::disk($this->discoTrabajo)->exists($prefijo . $path . '/' . $nombre . '.' . $extension . '.procesado')) {
                            Storage::disk($this->discoTrabajo)->delete($prefijo . $path . '/' . $nombre . '.' . $extension . '.procesado');
                        }

                        try {
                            // Renombra el documento en el SFTP para que no sea procesado por otra instancia del comando
                            Storage::disk('SftpPaisagro')->rename($archivo, $archivo . '.procesado');
                            try {
                                if (!Storage::disk($this->discoTrabajo)->exists($prefijo . $path . '/'))
                                    Storage::disk($this->discoTrabajo)->makeDirectory($prefijo . $path . '/');
                                    
                                // Obtiene el archivo
                                Storage::disk($this->discoTrabajo)->put($path . '/' . $nombre . '.' . $extension, Storage::disk('SftpPaisagro')->get($archivo . '.procesado'));
                            } catch (\Exception $e) {
                                $arrErrores[] = 'Error al copiar el archivo ' . $path . '/' . $nombre . '.' . $extension . ' (' . $e->getMessage() . ')';
                            }
                        } catch (\Exception $e) {
                            $arrErrores[] = 'Error al renombrar el archivo ' . $path . '/' . $nombre . '.' . $extension . ' (' . $e->getMessage() . ')';
                        }
                    }

                    if(!empty($arrErrores)) {
                        // Registra los errores en la lista de procesamiento de JSON
                        $this->registraProcesamientoJson([
                            'pjj_tipo'                => $cdo_tipo,
                            'pjj_json'                => '{}',
                            'pjj_procesado'           => 'SI',
                            'pjj_errores'             => json_encode($arrErrores),
                            'age_id'                  => 0,
                            'age_estado_proceso_json' => 'FINALIZADO',
                            'usuario_creacion'        => $user->usu_id,
                            'estado'                  => 'ACTIVO'
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Mueve los archivos remotos conforme a su procesamiento local
     *
     * @param array $arrProcesadosLocal Array con la información de los archivos procesados localmente
     * @return void
     */
    private function moverArchivosRemotos($arrProcesadosLocal) {
        foreach($arrProcesadosLocal as $procesado) {
            if (Storage::disk('SftpPaisagro')->exists($procesado['ruta_destino'] . $procesado['archivo'])) {
                Storage::disk('SftpPaisagro')->delete($procesado['ruta_destino'] . $procesado['archivo']);
            }
            if (Storage::disk('SftpPaisagro')->exists($procesado['ruta_origen'] . '/' . $procesado['archivo'] . '.procesado')) {
                Storage::disk('SftpPaisagro')->move($procesado['ruta_origen'] . '/' . $procesado['archivo'] . '.procesado', $procesado['ruta_destino'] . $procesado['archivo']);
            }
        }
    }
}
