<?php
namespace App\Console\Commands\Osram;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Models\AdoAgendamiento;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuento;
use App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccion;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Parser de archivos txt para la carga de documentos electronicos por parte de Osram.
 * 
 * Class OsramProcesarDocumentosCommand
 * @package App\Console\Commands\Osram
 */
class OsramProcesarDocumentosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osram-procesar-documentos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa archivos TXT de Osram para la emisión de documentos';

    /**
     * Arreglo de valores del archivo.
     */
    protected $data = [];

    /**
     * Tipo de documento en procesamiento
     */
    protected $tipo = '';

    /**
     * Arreglo de valores con los impuestos procesados en detalle.
     */
    protected $impuestos = [];

    /**
     *
     */
    protected $totalRetencionesSugeridas = 0;

    /**
     * @var int Total de registros a procesar
     */
    protected $total_procesar = 16;

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
     * @var string Modo de lectura de los archivos, puede ser 'local' o 'remoto'
     */
    protected $modoLectura = 'local';

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
            'osram@openio.co',
            'osram01@openio.co'
        ];

        // Se selecciona un correo de manera aleatoria, a éste correo quedará ligado todo el procesamiento del registro
        $correo = $arrCorreos[array_rand($arrCorreos)];

        // Obtiene el usuario relacionado con Osram
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

            // Obtiene datos de conexión y ruta de entrada 'IN' para Osram
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_conexion_ftp', 'ofe_prioridad_agendamiento'])
                ->where('ofe_identificacion', '900058192')
                ->first();

            // Array de rutas a consultar
            $arrPaths = array(
                $ofe->ofe_conexion_ftp['entrada_fc']
                // $ofe->ofe_conexion_ftp['entrada_nc'],
                // $ofe->ofe_conexion_ftp['entrada_nd']
            );

            // Si el modo de lectura de archivos es remoto, se debe conectar al SFTP del cliente para bajar los archivos al disco local y continuar su procesamiento
            if($this->modoLectura == 'remoto') {
                $arrProcesadosLocal = [];
                $this->configurarDiscoSftpOsram($ofe);
                $this->obtenerArchivosRemotos($ofe, $arrPaths, $user);
            }

            // Itera el array de rutas para procesar los archivos que se encuentren
            foreach ($arrPaths as $path) {
                unset($archivos);
                // Obtiene lista de archivos
                $prefijo  = $this->baseDatosOsram . $this->nitOsram;
                $archivos = Storage::disk($this->discoTrabajo)->files($prefijo . $path);

                if (count($archivos) > 0) {
                    // Funcionalidad temporal para dejar registrado en un archivo de texto el listado de archivos que se leyeron en el proceso
                    $listaArchivos = '';
                    foreach ($archivos as $archivo) {
                        $listaArchivos .= $archivo . "\r\n";
                    }
                    Storage::put('Osram/proceso_osram_' . date('YmdHis') . '.txt', $listaArchivos);
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

                                        $tmpIndiceRuta = 'fallidos_' . strtolower($this->tipo);
                                        $ruta = $ofe->ofe_conexion_ftp[$tmpIndiceRuta];
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
                                    $tmpIndiceRuta = 'exitosos_' . strtolower($res['tipodoc']);
                                    $ruta = $ofe->ofe_conexion_ftp[$tmpIndiceRuta];
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

                                $tmpIndiceRuta = 'fallidos_' . strtolower($this->tipo);
                                $ruta = $ofe->ofe_conexion_ftp[$tmpIndiceRuta];
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
     * @param array $valores Array que contiene la informacion de los campos para la creación dle registro
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
    private function getDBNum($value, $decimales = 2) {
        if($value !== '') {
            $value = $this->normalizeNum($value);
            $value = number_format($value, $decimales, '.', '');
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
     * Convierte una línea en array separando los valores mediante un separador, por defecto es punto y coma.
     * 
     * A cada linea se le aplica la codificación de caracteres y a cada item en el array resultante se le aplica la función trim
     * 
     * @param string $linea Línea en procesamiento
     */
    private function explotarLinea($linea,$separador=':') {
        $linea    = $this->fixEncoding($linea);
        $arrLinea = array_map('trim', explode($separador, $linea));
        //En la posicion 0 se envia la etiqueta, el resto de las posiciones son la informacion
        $datosLinea[0] = (string) $arrLinea[0];
        $datosLinea[1] = '';
        for ($i=1; $i<count($arrLinea); $i++) {
            $datosLinea[1] .= $separador.$arrLinea[$i];
        }
        $datosLinea[1] = (string) trim($datosLinea[1], $separador);
        return $datosLinea;
    }

    /**
     * Obtiene la informacion de cabecera del documento
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosCabecera($arrLinea) {

        switch ($arrLinea[0]) {
            case '@Encabezado@Ambiente':
                //Ambiente, no aplica, porque este valor depende de la paremetrizacion del software
                //1 Produccion, 2 Pruebas
                break;
            case '@Encabezado@TipoServicio':
                //Tipo de operacion
                $this->data['top_codigo'] = $arrLinea[1];
                break;
            case '@Encabezado@Tipo':
                //Tipo de documento electronico
                $this->data['tde_codigo'] = $arrLinea[1];
                //Clasificacion documento
                if(($arrLinea[1] == '01' || $arrLinea[1] == '02' || $arrLinea[1] == '03' || $arrLinea[1] == '04')) {
                    $this->tipo = 'FC';
                } elseif($arrLinea[1] == '91') {
                    $this->tipo = 'NC';
                } elseif($arrLinea[1] == '92') {
                    $this->tipo = 'ND';
                } else {
                    $this->tipo = 'FC';
                }
                //Datos fijos del documento
                $this->data['cdo_representacion_grafica_documento'] = '1';
                $this->data['cdo_representacion_grafica_acuse']     = '1';
                $this->data['cdo_envio_dian_moneda_extranjera']     = 'NO';
                break;
            case '@Encabezado@Serie':
                //Prefijo
                $this->data['rfa_prefijo'] = $arrLinea[1];
                break;
            case '@Encabezado@Numero':
                //Consecutivo
                $this->data['cdo_consecutivo'] = $arrLinea[1];
                break;
            case '@Encabezado@NroInterno':
                //NroInterno - Informacion adicional cabecera
                $this->data['cdo_informacion_adicional']['NroInterno'] = $arrLinea[1];
                break;
            case '@Encabezado@FechaEmis':
                //Fecha y hora documento, el formato llega 2021-02-03T18:10:08Z
                list($this->data['cdo_fecha'], $this->data['cdo_hora']) = explode('T', substr($arrLinea[1], 0, -1));
                break;
            case '@Encabezado@FechaVenc':
                //Fecha vencimiento del documento, el formato de esta fecha es DD-MM-YYYY, se debe convertir a YYYY-MM-DD
                $fecha = explode('-',$arrLinea[1]);
                $this->data['cdo_vencimiento'] = $fecha[2].'-'.$fecha[1].'-'.$fecha[0];
                break;
            case '@Encabezado@MedioPago':
                //Forma de pago
                $this->data['cdo_medios_pago'][0]['fpa_codigo'] = $arrLinea[1];

                if($this->data['cdo_medios_pago'][0]['fpa_codigo'] == 2 || (isset($this->data['cdo_vencimiento']) && $this->data['cdo_vencimiento'] != ''))  {
                    $this->data['cdo_medios_pago'][0]['men_fecha_vencimiento'] = $this->data['cdo_vencimiento'];
                }
                break;
            case '@Encabezado@TipoNegociacion':
                //Medio de pago
                $this->data['cdo_medios_pago'][0]['mpa_codigo'] = $arrLinea[1];
                break;
            case '@Encabezado@IncotermDs':
                //Lugar de entrega
                $this->data['cdo_informacion_adicional']['IncotermDs'] = $arrLinea[1];
                break;
            default:
                //Inidices que no se procesan
                //@Encabezado@Establecimiento
                //@Encabezado@PtoEmis
                //@Encabezado@IDPago
                //@Encabezado@PeriodoDesde
                //@Encabezado@PeriodoHasta
                //@Encabezado@TermPagoCdg
                //@Encabezado@Plazo
                //@Encabezado@CodIncoterms
                break;
        }
    }

    /**
     * Obtiene la informacion de la resulucion de facturacion
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosResolucion($arrLinea) {

        switch ($arrLinea[0]) {
            case '@CAE@NumeroResolucion':
                //Numero de Resolucion
                if ($this->tipo == 'FC') {
                    $this->data['rfa_resolucion'] = $arrLinea[1];
                }
                break;
            default:
                //Inidices que no se procesan
                //@CAE@Tipo
                //@CAE@Prefijo
                //@CAE@NumeroInicial
                //@CAE@NumeroFinal
                //@CAE@FechaResolucion
                //@CAE@ClaveTC
                //@CAE@lazo
                break;
        }
    }

    /**
     * Obtiene la informacion del OFE
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosOfe($arrLinea) {

        switch ($arrLinea[0]) {
            case '@Emisor@IDEmisor':
                //Numero de Resolucion
                $this->data['ofe_identificacion'] = $arrLinea[1];
                break;
            default:
                //Inidices que no se procesan
                //@Emisor@TipoContribuyente
                //@Emisor@RegimenContable
                //@Emisor@CdgSucursal
                //@Emisor@NombreEmisor
                //@Emisor@CodigoEmisor@TpoCdgIntEmisor1
                //@Emisor@@CodigoEmisor@CdgIntEmisor1
                //@Emisor@DomFiscal@Calle
                //@Emisor@DomFiscal@Departamento
                //@EmisorDomFiscal@@Ciudad
                //@Emisor@DomFiscal@Pais
                //@Emisor@DomFiscal@CodigoPostal
                //@Emisor@LugarExped@Calle
                //@Emisor@@Departamento
                //@EmisorDomFiscal@LugarExped@Ciudad
                //@Emisor@LugarExped@Pais
                //@Emisor@LugarExped@CodigoPostal
                //@Emisor@ContactoEmisor@Nombre
                //@Emisor@ContactoEmisor@Descripcion
                //@Emisor@ContactoEmisor@eMail
                //@Emisor@ContactoEmisor@Telefono
                //@Emisor@ContactoEmisor@@Fax
                break;
        }
    }

    /**
     * Obtiene los datos del adquirente
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosAdquirente($arrLinea) {
        switch ($arrLinea[0]) {
            case '@Receptor@TipoContribuyente':
                //Tipo de organizacion Juridica
                $this->data['adquirente']['toj_codigo'] = $arrLinea[1];
                break;
            case '@Receptor@RegimenContable':
                //Regimen fiscal
                $this->data['adquirente']['rfi_codigo'] = $arrLinea[1];
                break;
            case '@Receptor@CdgSucursal':
                //Numero de matricula mercantil
                $this->data['adquirente']['adq_matricula_mercantil'] = $arrLinea[1];
                break;
            case '@Receptor@TpoDocRecep':
                //Tipo de documento
                $this->data['adquirente']['tdo_codigo'] = $arrLinea[1];
                break;
            case '@Receptor@NroDocRecep':
                //Nit del Adq
                $this->data['adq_identificacion'] = $arrLinea[1];
                //En la seccion de adquirente se debe enviar la identificacion del OFE
                $this->data['adquirente']['ofe_identificacion'] = $this->data['ofe_identificacion'];
                $this->data['adquirente']['adq_identificacion'] = $arrLinea[1];
                break;
            case '@Receptor@NombreReceptor':
                //Razon social del Adq o apellidos y nombre del adquiirente cuando es persona natural 
                //(para personas naturales seprar por espacio los apellidos y nombres)
                //1: Juridica, 2: Natural

                if ($this->data['adquirente']['toj_codigo'] == '2') {
                  //Extrayendo apellidos y nombres
                  $primerApellido  = '';
                  $segundoApellido = '';
                  $primerNombre    = '';
                  $otrosNombres    = '';
                  
                  $arrNombre = $this->explotarLinea($arrLinea[1],' ');
                  if (count($arrNombre) == 2) {
                    //Solo tiene un apellido y un nombre
                    $primerApellido = $arrNombre[0];
                    $primerNombre   = $arrNombre[1];
                  } else {
                      for ($i=0; $i<count($arrNombre); $i++) {
                          switch ($i) {
                              case 0:
                                  $primerApellido = $arrNombre[$i];
                                  break;
                              case 1:
                                  $segundoApellido = $arrNombre[$i];
                                  break;
                              case 3:
                                  $primerNombre = $arrNombre[$i];
                                  break;
                              case 3:
                                    $otrosNombres .= $arrNombre[$i].' ';
                                    break;
                              default:
                                # code...
                                break;
                          }
                      }
                  }
                }

                $this->data['adquirente']['adq_razon_social']     = ($this->data['adquirente']['toj_codigo'] == '1') ? $arrLinea[1]        : '';
                $this->data['adquirente']['adq_nombre_comercial'] = ($this->data['adquirente']['toj_codigo'] == '1') ? $arrLinea[1]        : '';
                $this->data['adquirente']['adq_primer_apellido']  = ($this->data['adquirente']['toj_codigo'] == '2') ? $primerApellido     : '';
                $this->data['adquirente']['adq_segundo_apellido'] = ($this->data['adquirente']['toj_codigo'] == '2') ? $segundoApellido    : '';
                $this->data['adquirente']['adq_primer_nombre']    = ($this->data['adquirente']['toj_codigo'] == '2') ? $primerNombre       : '';
                $this->data['adquirente']['adq_otros_nombres']    = ($this->data['adquirente']['toj_codigo'] == '2') ? trim($otrosNombres) : '';
                break;
            case '@Receptor@CodigoReceptor@CdgIntReceptor1':
                  //Responsabilidad Fiscal
                  $this->data['adquirente']['ref_codigo'] = [$arrLinea[1]];
                  break;
            case '@Receptor@DomFiscal@Calle':
                //Direccion Fiscal
                $this->data['adquirente']['adq_direccion_domicilio_fiscal'] = $arrLinea[1];
                break;
            case '@Receptor@DomFiscal@Departamento':
                //Departmentro domicilio fiscal
                $this->data['adquirente']['dep_codigo_domicilio_fiscal'] = $arrLinea[1];
                break;
            case '@Receptor@DomFiscal@Ciudad':
                //ciudad domicilio fiscal, viene en este formato Dpto + ciudad (ejm.05001)
                $this->data['adquirente']['mun_codigo_domicilio_fiscal'] = (string) substr($arrLinea[1], 2, strlen($arrLinea[1]));
                break;
            case '@Receptor@DomFiscal@Pais':
                //Pais domcicilio fiscal
                $this->data['adquirente']['pai_codigo_domicilio_fiscal'] = $arrLinea[1];
                //Si el pais es diferente de colombia no se envia ni departamento, ni municipio
                if ($arrLinea[1] != 'CO') {
                    $this->data['adquirente']['dep_codigo_domicilio_fiscal'] = '';
                    $this->data['adquirente']['mun_codigo_domicilio_fiscal'] = '';
                }
                break;
            case '@Receptor@DomFiscal@CodigoPostal':
                //Codigo postal domicilio fiscal
                $this->data['adquirente']['cpo_codigo_domicilio_fiscal'] = $arrLinea[1];
                break;
            case '@Receptor@LugarExped@Calle':
                //Direccion Correspondencia
                $this->data['adquirente']['adq_direccion'] = $arrLinea[1];
                break;
            case '@Receptor@LugarExped@Departamento':
                //Departmentro Correspondencia
                $this->data['adquirente']['dep_codigo'] = $arrLinea[1];
                break;
            case '@Receptor@LugarExped@Ciudad':
                //ciudad domicilio Correspondencia, viene en este formato Dpto + ciudad (ejm.05001)
                $this->data['adquirente']['mun_codigo'] = (string) substr($arrLinea[1], 2, strlen($arrLinea[1]));
                break;
            case '@Receptor@LugarExped@Pais':
                //Pais domcicilio Correspondencia
                $this->data['adquirente']['pai_codigo'] = $arrLinea[1];
                //Si el pais es diferente de colombia no se envia ni departamento, ni municipio
                if ($arrLinea[1] != 'CO') {
                    $this->data['adquirente']['dep_codigo'] = '';
                    $this->data['adquirente']['mun_codigo'] = '';
                }
                break;
            case '@Receptor@LugarExped@CodigoPostal':
                //Codigo postal domicilio Correspondencia
                $this->data['adquirente']['cpo_codigo'] = $arrLinea[1];
                break;
            case '@Receptor@CodigoReceptor@TpoCdgIntReceptor':
                //Codigo postal domicilio Correspondencia
                $this->data['adquirente']['responsable_tributos'] = explode(",",$arrLinea[1]);
                break;
            default:
                //Inidices que no se procesan
                //@Receptor@PrimerNombre
                //@Receptor@ContactoReceptor@Nombre
                //@Receptor@ContactoReceptor@Descripcion
                //@Receptor@ContactoReceptor@eMail (se traer de informacion personalizada)
                //@Receptor@ContactoReceptor@Telefono (se traer de informacion personalizada)
                //@Receptor@ContactoReceptor@Fax
                //En los datos no viene responsabilidad tributos. $this->data['adquirente']['responsable_tributos'] = [$arrLinea[1]];
                break;
        }
    }

    /**
     * Obtiene la informacion de los Totales
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosTotales($arrLinea) {

        switch ($arrLinea[0]) {
            case '@Totales@Moneda':
                //Numero de Resolucion
                $this->data['mon_codigo'] = $arrLinea[1];
                break;
            case '@Totales@SubTotal':
                //Valor total de la factura sin impuestos
                $this->data['cdo_valor_sin_impuestos'] = $this->getDBNum($this->normalizeNum($arrLinea[1]));
                break;
            case '@Totales@MntBase':
                //sumatoria del total de los items gravados
                $this->data['cdo_informacion_adicional']['MntBase'] = $arrLinea[1];
                break;
            case '@Totales@MntExe':
                //sumatoria de los itema no gravados
                $this->data['cdo_informacion_adicional']['MntExe'] = $arrLinea[1];
                break;
            case '@Totales@MntImp':
                //Valor del impuesto
                $this->data['cdo_impuestos'] = $this->getDBNum($this->normalizeNum($arrLinea[1]));
                break;
            case '@Totales@VlrPagar':
                //valor total: (MntBase+MntExe+MntImp) (items gravados + items no grabados + iva)
                $this->data['cdo_total'] = $this->getDBNum($this->normalizeNum($arrLinea[1]));
                break;
            case 'VlrPalabras':
                //Valor en letras
                $this->data['cdo_informacion_adicional']['valor_letras'] = $arrLinea[1];
                break;
            case '@Totales@FctConv':
                //Si la moneda es diferente de COP se debe enviar la tasa de cambio
                if ($arrLinea[1] != "") {
                    $this->data['cdo_trm']       = $this->getDBNum($this->normalizeNum($arrLinea[1]),3);
                    $this->data['cdo_trm_fecha'] = date('Y-m-d');
                }
                break;
            default:
                //Inidices que no se procesan
                //@Totales@MntDcto
                //@Totales@PctDcto
                //@Totales@MntRcgo
                //@Totales@PctRcgo
                break;
        }
    }

    /**
     * Obtiene la informacion de informacion personalizada
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosPersonalizados($arrLinea) {

        switch ($arrLinea[0]) {
            case '@DIVISION':
                $clave = trim(str_replace('@','',$arrLinea[0]));
                $this->data['cdo_informacion_adicional'][$clave] = $arrLinea[1];

                // Dependiendo si en DIVISON llega el valor ES o EN se debe asignar el valor para la RG
                if($arrLinea[1] == 'EN')
                    $this->data['cdo_representacion_grafica_documento'] = '2';
                else
                    $this->data['cdo_representacion_grafica_documento'] = '1';
                break;
            case '@email':
                //se realiza explode por ; por si vienen varios correos, se asigna el primero al correo del adquirente, y todos a los correos de notificacion
                $arrLinea[1] = trim($arrLinea[1], ';');
                $correos = explode(';',$arrLinea[1]);

                //Mail
                $this->data['adquirente']['adq_correo'] = $correos[0];
                $this->data['adquirente']['adq_correos_notificacion'] = str_replace(';',',',$arrLinea[1]);
                break;
            case '@Telefone':
                //Mail
                $this->data['adquirente']['adq_telefono'] = $arrLinea[1];
                break;
            default:
                //Informacion personalizada
                $clave = trim(str_replace('@','',$arrLinea[0]));
                $this->data['cdo_informacion_adicional'][$clave] = $arrLinea[1];

                //Si la factura es de exportacion debe incluirse la seccion de termino de entrega
                //y en la observacion del documento enviar el termino de negociacion (campos MAXENTREGA)
                //concatenado con el lugar de entrega que viene en el campo @Encabezado@IncotermDs
                if ($this->data['tde_codigo'] == '02' && $arrLinea[0] == "@MAXENTREGA") {
                    $this->data['dad_terminos_entrega']['cen_codigo'] = $arrLinea[1];

                    $this->data['cdo_observacion'][1] = trim($arrLinea[1]." ".$this->data['cdo_informacion_adicional']['IncotermDs']);
                }

                //Observaciones de la factura se deben enviar en el xml, 
                //cuando es factura de exportacion se debe enviar la observacion como la posicion 0 del array 
                //y luego se debe enviar en la posicion 1 el termino de negociacion y lugar de entrega)
                if ($arrLinea[0] == "@F_Interno") {
                    $this->data['cdo_observacion'][0] = $arrLinea[1];
                }
                break;
        }
    }

    /**
     * Convierte la línea de items en array separando los valores por poisiciones.
     * 
     * A cada linea se le aplica la codificación de caracteres y a cada item en el array resultante se le aplica la función trim
     * 
     * @param string $linea Línea en procesamiento
     */
    private function explotarLineaxPosicion($linea, $seccion) {
        $linea = $this->fixEncoding($linea);

        //Estas poisiciones incluyen la etiqueta del campo
        switch ($seccion) {
            case 'impuestos':
            case 'retencion':
                $posiciones = [
                    [1, 29],
                    [30, 14],
                    [44, 39],
                    [83, 39]
                    ];
                break;
            case 'referencia':
                $posiciones = [
                    [1, 32],
                    [33, 31],
                    [64, 31],
                    [95, 40],
                    [135, 52],
                    [187, 31],
                    [218, 41],
                    [259, 120]
                    ];
                break;
            default:
                //items [posicion - tamaño]
                $posiciones = [
                    [1, 14],
                    [15, 14],
                    [29, 32],
                    [61, 16],
                    [77, 30],
                    [107, 49],
                    [156, 30],
                    [186, 15],
                    [201, 33],
                    [234, 47],
                    [281, 12],
                    [293, 18],
                    [311, 35],
                    [346, 70],
                    [416, 58],
                    [474, 19],
                    [493, 25],
                    [518, 26],
                    [544, 32],
                    [576, 33],
                    [609, 17],
                    [626, 35],
                    [661, 41],
                    [702, 39],
                    [741, 40],
                    [781, 24],
                    [805, 28],
                    [833, 33],
                    [866, 161],
                    [1027, 19],
                    [1046, 19],
                    [1065, 7],
                    [1072, 12]
                    ];
                break;
        }

        //Etiqueta de los campos, la canitdad de etiquetas deben ser igual a la cantidad de posiciones del array $posiciones
        //la etiqueta y el valor se separan por punto y coma, o espacio
        switch ($seccion) {
            case 'impuestos':
                $etiquetaCampos = [
                    '@Impuestos@TipoImp:',
                    'TasaImp:',
                    'MontoBaseImp:',
                    'MontoImp:'
                    ];
                break;
            case 'retencion':
                $etiquetaCampos = [
                    'TipoRet:',
                    'TasaImp:',
                    'MontoBaseImp:',
                    'MontoImp:'
                    ];
                break;
            case 'referencia':
                $etiquetaCampos = [
                    '@Referencias@NroLinea:',
                    '@Referencias@TpoDocRe',
                    '@Referencias@SerieRef:',
                    '@Referencias@NumeroRef:',
                    '@Referencias@FechRef:',
                    '@Referencias@CodRef:',
                    '@Referencias@RazonRef:',
                    '@Referencias@ECB01'
                    ];
                break;
            default:
                $etiquetaCampos = [
                    'lin:',
                    'TipoCod1:',
                    'VlrCodigo1:',
                    'TipoCod2:',
                    'VlrCodigo2:',
                    'DscItem:',
                    'QtyItem:',
                    'UnMdItem:',
                    'PrcBrutoItem:',
                    'PrcNetoItem:',
                    'TipoImp:',
                    'TasaImp:',
                    'MontoBaseImp',
                    'MontoImp:',
                    'TipoImp 2:',
                    'TasaImp 2:',
                    'MontoBaseImp 2',
                    'MontoImp 2:',
                    'TipoImp 3:',
                    'TasaImp 3:',
                    'MontoBaseImp 3',
                    'MontoImp 3:',
                    'MontoBrutoItem:',
                    'MontoNetoItem:',
                    'MontoTotalItem:',
                    '@CustDetalle@NroLin:',
                    'Codigo:',
                    'PLU Cliente:',
                    'DESCRIPCION',
                    'EAN:',
                    'AL ECCN:',
                    'UM:',
                    'ORIGEN:',
                    ];
                break;
        }

        $arrLinea = [];
        for ($i=0; $i<count($posiciones); $i++) {
            //si el texto no contiene separador de dos puntos, se explota por espacio
            $campoCompleto = substr($linea, $posiciones[$i][0]-1, $posiciones[$i][1]);
            $campoExtraido = substr($campoCompleto, strlen($etiquetaCampos[$i]), strlen($campoCompleto));
            //en el nombre de la etiqueta se eliminan los punto y coma (:) y los espacios ( )
            $arrLinea[str_replace(array(':',' '),array('','_'),$etiquetaCampos[$i])] = trim(trim($campoExtraido),":");
        }

        return $arrLinea;
    }

    /**
     * Obtiene data de un item
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosItem($arrLinea) {
        $secuencia = (string)(count($this->data['items']) + 1);
        $this->data['items'][] = [
            'ddo_secuencia'       => $secuencia,
            'cpr_codigo'          => '999',
            'ddo_codigo'          => $arrLinea['VlrCodigo1'],
            'ddo_tipo_item'       => 'IP',
            'ddo_descripcion_uno' => $arrLinea['DscItem'],
            'ddo_cantidad'        => trim($arrLinea['QtyItem'])        === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['QtyItem'])),
            'ddo_valor_unitario'  => trim($arrLinea['PrcBrutoItem'])   === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['PrcBrutoItem'])),
            'ddo_total'           => trim($arrLinea['MontoBrutoItem']) === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['MontoBrutoItem'])),
            'und_codigo'          => $arrLinea['UnMdItem'],

            'ddo_informacion_adicional' => [
                'EAN'     => $arrLinea['EAN'],
                'AL_ECCN' => $arrLinea['AL_ECCN'],
                'UM'      => $arrLinea['UM'],
                'ORIGEN'  => $arrLinea['ORIGEN']
            ]
        ];

        // Impuestos
        if($arrLinea['TipoImp'] != '') {
            //incluyendo el tipo de impuesto en array de control de impuestos de detalle
            if(!in_array($arrLinea['TipoImp'],$this->impuestos)) {
                $this->impuestos[] = $arrLinea['TipoImp'];
            }
          
            $this->data['tributos'][] = [
                'ddo_secuencia' => $secuencia,
                'tri_codigo'    => $arrLinea['TipoImp'],
                'iid_valor'     => trim($arrLinea['MontoImp']) === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['MontoImp'])),
                'iid_porcentaje' => [
                        'iid_base'       => trim($arrLinea['MontoBaseImp']) === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['MontoBaseImp'])),
                        'iid_porcentaje' => trim($arrLinea['TasaImp'])      === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['TasaImp']),3)
                ]
            ];
        }
    }

    /**
     * Obtiene data de un impuesto, solo se procesan los impuestos que no se hayan incluido en los items
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosImpuestos($arrLinea) {
        if ($arrLinea['@Impuestos@TipoImp'] != '' && !in_array($arrLinea['@Impuestos@TipoImp'],$this->impuestos)) {
            $this->data['tributos'][] = [
                'ddo_secuencia' => '',
                'tri_codigo'    => $arrLinea['@Impuestos@TipoImp'],
                'iid_valor'     => trim($arrLinea['MontoImp']) === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['MontoImp'])),
                'iid_porcentaje' => [
                        'iid_base'       => trim($arrLinea['MontoBaseImp']) === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['MontoBaseImp'])),
                        'iid_porcentaje' => trim($arrLinea['TasaImp'])      === "" ? "0.00" : $this->getDBNum($this->normalizeNum($arrLinea['TasaImp']),3)
                ]
            ];
        }
    }

    /**
     * Obtiene data de las retenciones sugeridas cuyo valor del primer índice es IA
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosRetenciones($arrLinea) {
        //En los valores de las retenciones se elimina el signo menos al final, cuando es nota credito
        if($arrLinea['TipoRet'] != '') {
            // Clasificando la retencion
            switch ($arrLinea['TipoRet']) {
                case '06':
                    $tipo  = 'RETEFUENTE';
                    break;
                case '05':
                    $tipo  = 'RETEIVA';
                    break;
                default:
                    $tipo = 'RETEICA';
                    break;
            }

            //Trayendo descripcion de la retencion
            // Conceptos de corrección
            $tributo = ParametrosTributo::select(['tri_codigo','tri_nombre'])
                ->where('tri_codigo', $arrLinea['TipoRet'])
                ->first();

            //Si el porcentaje tiene mas de tres decimales debe enviarse 
            //como porcentaje el 100% y base e iva el valor de la retencion sugerida
            $porcentajeRetencion = $this->getDBNum($this->normalizeNum(trim($arrLinea['TasaImp'],'-')),3) + 0;
            $decimales           = explode('.',$porcentajeRetencion);
            $cantidadDecimales   = (isset($decimales[1])) ? strlen($decimales[1]) : 0;

            if ($cantidadDecimales > 2) {
                $porcentaje = 100;
                $base       = $this->getDBNum($this->normalizeNum(trim($arrLinea['MontoImp'],'-')));
                $valor      = $this->getDBNum($this->normalizeNum(trim($arrLinea['MontoImp'],'-')));
            } else {
                $porcentaje = $porcentajeRetencion;
                $base       = $this->getDBNum($this->normalizeNum(trim($arrLinea['MontoBaseImp'],'-')));
                $valor      = $this->getDBNum($this->normalizeNum(trim($arrLinea['MontoImp'],'-')));
            }

            $this->data['cdo_detalle_retenciones_sugeridas'][] = [
                'tipo'       => $tipo,
                'razon'      => 'RETENCION SUGERIDA ' . mb_strtoupper((isset($tributo->tri_nombre) ? $tributo->tri_nombre : $tipo)) . ' ' . round($porcentajeRetencion,3) . '%',
                'porcentaje' => trim($arrLinea['TasaImp']) === "" ? "0.00" : number_format($porcentaje,2,'.',''),
                'valor_moneda_nacional' =>  [
                    'base'  => trim($arrLinea['MontoBaseImp']) === "" ? "0.00" : $base,
                    'valor' => trim($arrLinea['MontoImp'])     === "" ? "0.00" : $valor
                ]
            ];
            $this->totalRetencionesSugeridas += $valor;
        }
    }

    /**
     * Integra la data correspondiente al documento referencia es una NC o ND la que se esta procesando.
     * 
     * @param array $arrLinea Array de la línea en procesamiento
     * @return void
     */
    private function obtenerDatosDocumentoReferencia($arrLinea) {
        $tipoDocRef = null;
        if(($arrLinea['@Referencias@TpoDocRe'] == '01' || $arrLinea['@Referencias@TpoDocRe'] == '02' || $arrLinea['@Referencias@TpoDocRe'] == '03' || $arrLinea['@Referencias@TpoDocRe'] == '04')) {
            $tipoDocRef = 'FC';
        } elseif($arrLinea['@Referencias@TpoDocRe'] == '91') {
            $tipoDocRef = 'NC';
        } elseif($arrLinea['@Referencias@TpoDocRe'] == '92') {
            $tipoDocRef = 'ND';
        }

        //Fecha y hora documento referencia, el formato llega 2021-02-03T18:10:08Z
        $fecha = explode('T', substr($arrLinea['@Referencias@FechRef'], 0, -1));

        $this->data['cdo_documento_referencia'][] = [
            'clasificacion'    => $tipoDocRef,
            'prefijo'          => $arrLinea['@Referencias@SerieRef'],
            'consecutivo'      => $arrLinea['@Referencias@NumeroRef'],
            'cufe'             => $arrLinea['@Referencias@ECB01'],
            'fecha_emision'    => $fecha[0]
        ];

        // Conceptos de corrección
        $conceptoCorreccion = ParametrosConceptoCorreccion::select(['cco_id', 'cco_codigo', 'cco_descripcion'])
            ->where('cco_codigo', $arrLinea['@Referencias@CodRef'])
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
            throw new \Exception('El concepto de corrección con código ['.$arrLinea['@Referencias@CodRef'].'] no existe en el sistema para el tipo de documento ['.$this->tipo.']');
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
        $seccion                         = '';

        $storagePath = Storage::disk($this->discoTrabajo)->getDriver()->getAdapter()->getPathPrefix();
        $filename = $storagePath . $rutaArchivo;

        $handle = fopen($filename, 'r');
        while (($line = fgets($handle)) !== false) {
            $line = $this->fixEncoding($line);
            if (strlen(preg_replace("/[\r\n|\n|\r]+/", '', $line)) == 0) {
                // Omite líneas en blanco
                continue;
            }

            if (substr_count($line, "==ENCABEZADO==") > 0) {
                //Inicia seccion de encabezado
                $seccion = 'encabezado';
                continue;
            }

            if (substr_count($line, "==CAE==") > 0) {
                //Inicia seccion de resolucion de facturacion
                $seccion = 'resolucion';
                continue;
            }

            if (substr_count($line, "==EMISOR==") > 0) {
                //Inicia seccion de datos del OFE
                $seccion = 'ofe';
                continue;
            }

            if (substr_count($line, "==RECEPTOR==") > 0) {
                //Inicia seccion de datos del ADQUIRENTE
                $seccion = 'adquirente';
                continue;
            }

            if (substr($line, 0, 4) == "lin:") {
                //Inicia seccion de items
                $seccion = 'items';
            }

            if (substr_count($line, "XXXFINDETA") > 0) {
                //Inicia seccion de totales
                $seccion = 'totales';
                continue;
            }

            if (substr_count($line, "==IMPUESTOS==") > 0) {
                //Inicia seccion de datos del impuestos
                $seccion = 'impuestos';
                continue;
            }

            if (substr_count($line, "==RETENCION==") > 0) {
                //Inicia seccion de datos del retenciones sugeridas
                $seccion = 'retencion';
                continue;
            }

            if (substr_count($line, "==DESCUENTOS Y RECARGO GLOBALES==") > 0) {
                //Inicia seccion de datos del descuentos y cargos
                $seccion = 'descuentos-cargos';
                continue;
            }

            if (substr_count($line, "==PERSONALIZADOS==") > 0) {
                //Inicia seccion de datos del datos personalizadas
                $seccion = 'personalizados';
                continue;
            }

            if (substr_count($line, "==DESPACHO==") > 0) {
                //Inicia seccion de datos del despacho
                $seccion = 'despacho';
                continue;
            }

            if (substr_count($line, "==REFERENCIAS==") > 0) {
                //Inicia seccion de datos del referencia documento nota credito
                $seccion = 'referencia';
                continue;
            }

            //las notas debito no tienen la division REFERECIAS, por lo que se busca por la etiqueta @Referencias@NroLinea:
            if (substr($line, 0, 22) == "@Referencias@NroLinea:") {
                //Inicia seccion de referencia documento notas debito
                $seccion = 'referencia';
            }

            if (substr_count($line, "==FINDOC==") > 0) {
                //Inicia seccion de datos del ADQUIRENTE
                $seccion = 'fin';
                continue;
            }

            //Extrayendo Datos de la linea
            switch ($seccion) {
                case 'items':
                case 'impuestos':
                case 'retencion':
                case 'referencia':
                    $arrLinea = $this->explotarLineaxPosicion($line, $seccion);
                    break;
                case 'descuentos-cargos':
                    //No se manejan descuentos, ni cargos
                    break;
                default:
                    $arrLinea = $this->explotarLinea($line);
                    break;
            }
            
            //Procesando cada una de las secciones
            switch ($seccion) {
                case 'encabezado':
                    $this->obtenerDatosCabecera($arrLinea);
                    break;
                case 'resolucion':
                    $this->obtenerDatosResolucion($arrLinea);
                    break;
                case 'ofe':
                    $this->obtenerDatosOfe($arrLinea);
                    break;
                case 'adquirente':
                    $this->obtenerDatosAdquirente($arrLinea);
                    break;
                case 'items':
                    $this->obtenerDatosItem($arrLinea);
                    break;
                case 'totales':
                    $this->obtenerDatosTotales($arrLinea);
                    break;
                case 'impuestos':
                    $this->obtenerDatosImpuestos($arrLinea);
                    break;
                case 'retencion':
                    $this->obtenerDatosRetenciones($arrLinea);
                    break;
                case 'descuentos-cargos':
                    //No se manejan descuentos, ni cargos
                    break;
                case 'personalizados':
                    $this->obtenerDatosPersonalizados($arrLinea);
                    break;
                case 'despacho':
                    //No se usa, sin embargo se envia en campos personalizados
                    $this->obtenerDatosPersonalizados($arrLinea);
                    break;
                case 'referencia':
                    $this->obtenerDatosDocumentoReferencia($arrLinea);
                    break;
                default:
                    //No hace nada
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
     * Configura un disco en tiempo de ejecución para el SFTP de Osram
     *
     * @param App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente $ofe Modelo OFE
     * @return void
     */
    private function configurarDiscoSftpOsram(ConfiguracionObligadoFacturarElectronicamente $ofe) {
        config([
            'filesystems.disks.SftpOsram' => [
                'driver'   => 'sftp',
                'host'     => $ofe->ofe_conexion_ftp['host'],
                'username' => $ofe->ofe_conexion_ftp['username'],
                'password' => $ofe->ofe_conexion_ftp['password'],
            ]
        ]);
    }

    /**
     * Obtiene los archivos del SFTP de Osram para almacenarlos localmente
     *
     * @param App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente $ofe Modelo OFE
     * @param array $arrPaths Array con la informacion de las rutas que se debe recorrer
     * @param App\Http\Models\User $user Modelo Usuario
     * @return void
     */
    private function obtenerArchivosRemotos(ConfiguracionObligadoFacturarElectronicamente $ofe, $arrPaths, User $user) {
        // Ruta completa al disco de Osram
        $OsramDiskPath = Storage::disk($this->discoTrabajo)->getAdapter()->getPathPrefix();

        // Itera el array de rutas para copiar al disco los archivos que encuentre en el momento
        foreach ($arrPaths as $path) {
            // Obtiene lista de archivos
            $archivos = Storage::disk('SftpOsram')->files($path);

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
                        $prefijo  = $this->baseDatosOsram . $this->nitOsram;
                        // Borra el archivo si existe en el disco local
                        if (Storage::disk($this->discoTrabajo)->exists($prefijo . $path . '/' . $nombre . '.' . $extension)) {
                            Storage::disk($this->discoTrabajo)->delete($prefijo . $path . '/' . $nombre . '.' . $extension);
                        }
                        if (Storage::disk($this->discoTrabajo)->exists($prefijo . $path . '/' . $nombre . '.' . $extension . '.procesado')) {
                            Storage::disk($this->discoTrabajo)->delete($prefijo . $path . '/' . $nombre . '.' . $extension . '.procesado');
                        }

                        try {
                            // Renombra el documento en el SFTP para que no sea procesado por otra instancia del comando
                            Storage::disk('SftpOsram')->rename($archivo, $archivo . '.procesado');
                            try {
                                if (!Storage::disk($this->discoTrabajo)->exists($prefijo . $path . '/'))
                                    Storage::disk($this->discoTrabajo)->makeDirectory($prefijo . $path . '/');
                                    
                                // Obtiene el archivo
                                Storage::disk($this->discoTrabajo)->put($path . '/' . $nombre . '.' . $extension, Storage::disk('SftpOsram')->get($archivo . '.procesado'));
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
     * @param array $arrProcesadosLocal Array con la informacion de los archivos procesados localmente
     * @return void
     */
    private function moverArchivosRemotos($arrProcesadosLocal) {
        foreach($arrProcesadosLocal as $procesado) {
            if (Storage::disk('SftpOsram')->exists($procesado['ruta_destino'] . $procesado['archivo'])) {
                Storage::disk('SftpOsram')->delete($procesado['ruta_destino'] . $procesado['archivo']);
            }
            if (Storage::disk('SftpOsram')->exists($procesado['ruta_origen'] . '/' . $procesado['archivo'] . '.procesado')) {
                Storage::disk('SftpOsram')->move($procesado['ruta_origen'] . '/' . $procesado['archivo'] . '.procesado', $procesado['ruta_destino'] . $procesado['archivo']);
            }
        }
    }
}
