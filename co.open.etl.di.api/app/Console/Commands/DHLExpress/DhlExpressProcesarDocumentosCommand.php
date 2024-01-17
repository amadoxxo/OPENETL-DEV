<?php
namespace App\Console\Commands\DHLExpress;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Models\AdoAgendamiento;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuento;
use App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccion;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Parser de archivos txt para la carga de documentos electronicos por parte de DHL Express
 * Class DhlExpressCommand
 * @package App\Console\Commands
 */
class DhlExpressProcesarDocumentosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhlexpress-procesar-documentos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa archivos TXT de DhlExpress para la emisión de documentos';

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
     * Clasificacion Documento electronico
     */
    protected $tipo = '';

    /**
     * @var int Total de registros a procesar
     */
    protected $total_procesar = 16;

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
     * @var array Array en donde se almacenan las claves de cada línea del archivo para poder determinar posteriormente si hay duplicados
     */
    private $arrClaves  = [];

    /**
     * @var array Array de llaves que si pueden estar duplicadas porque se utilizan en diferentes secciones del archivo
     */
    private $arrClavesPuedeDuplicar  = [
        'Prefijo',
        'Tipo Contribuyente',
        'Regimen Contable',
        'Codigo de tributo',
        'TipoImp'
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
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $arrCorreos = [
            'dhlexpress@openio.co',
            'dhlexpress01@openio.co'
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

            // Obtiene datos de conexión y ruta de entrada 'IN' para DHLExpress
            $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_conexion_ftp', 'ofe_prioridad_agendamiento'])
                ->where('ofe_identificacion', '860502609')
                ->with([
                    'getConfiguracionSoftwareProveedorTecnologico'
                ])
                ->first();

            // Array de rutas a consultar en el servicio FTP
            $arrPaths = array(
                $ofe->ofe_conexion_ftp['entrada_fc']
            );

            // Itera el array de rutas para copiar al disco del
            // servidor los archivos que encuentre en el momento
            foreach ($arrPaths as $path) {
                unset($archivos);
                // Obtiene lista de archivos
                $prefijo  = $this->baseDatosDhlExpress . $this->nitDhlExpress;
                $archivos = Storage::disk($this->discoTrabajo)->files($prefijo . $path);
                if (count($archivos) > 0) {
                    // Funcionalidad temporal para dejar registrado en un archivo de texto el listado de archivos que se leyeron en el proceso
                    $listaArchivos = '';
                    foreach ($archivos as $archivo) {
                        $listaArchivos .= $archivo . "\r\n";
                    }
                    Storage::put('proceso_dhlexpress_' . date('YmdHis') . '.txt', $listaArchivos);
                    // Fin funcionalidad temporal
                    
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

                    // Array de documentos procesados que se enviarán a DI
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

                        $extension = \File::extension($archivo);
                        $nombre = \File::name($archivo);

                        if (strtolower($extension) === 'txt' && Storage::disk($this->discoTrabajo)->exists($archivo) && Storage::disk($this->discoTrabajo)->size($archivo) > 0) {
                            try {
                                // Renombra el documento para que no sea procesado por otra instancia del comando
                                Storage::disk($this->discoTrabajo)->rename($archivo, $archivo . '.procesado');

                                // Inicia el procesamiento del archivo
                                $res = $this->_procesarArchivo($user, $archivo . '.procesado', $ofe, $nombre . '.' . $extension);
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

                                        $file = ($res['tipodoc'] === 'NC') ? $ofe->ofe_conexion_ftp['fallidos_nc'] : $ofe->ofe_conexion_ftp['fallidos_fc'];
                                        $file = $file . $nombre . '.' . $extension;
                                        $this->_moverArchivo($archivo . '.procesado', $prefijo . $file);
                                    }
                                } else {
                                    // Procesamiento correcto
                                    // Se agrega el documento al array de documentos a procesar por EDI y se mueve el documento al directorio de exitosos
                                    $payload['documentos'][$res['tipodoc']][] = $res['json'];
                                    $file = ($res['tipodoc'] === 'NC') ? $ofe->ofe_conexion_ftp['exitosos_nc'] : $ofe->ofe_conexion_ftp['exitosos_fc'];
                                    $file = $file . $nombre . '.' . $extension;
                                    $this->_moverArchivo($archivo . '.procesado', $prefijo . $file);
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

                                $file = ($this->tipo === 'NC') ? $ofe->ofe_conexion_ftp['fallidos_nc'] : $ofe->ofe_conexion_ftp['fallidos_fc'];
                                $file = $file . $nombre . '.' . $extension;
                                $this->_moverArchivo($archivo . '.procesado', $prefijo . $file);
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
     * Almacena los valores de la sección actual.
     */
    private function _guardarSeccion($nombreSeccion, &$datos) {
        if ($datos) {
            $this->data[trim(strtolower($nombreSeccion))] = $datos;
            $datos = [];
        }
    }

    /**
     * Almacena los ítems de la sección actual de la tabla.
     */
    private function _guardarItemTabla(&$datosItemTabla, $nombreItemTabla, &$detallesItems) {
        $datosItemTabla[trim(strtolower($nombreItemTabla))] = $detallesItems;
        $detallesItems = [];
    }

    /**
     * Almacena la sección actual de la tabla.
     */
    private function _guardarSeccionTabla($nombreItemTabla, &$datosSeccionTabla, $nombreSeccionTabla, &$datosItemTabla, &$detallesItems) {
        $this->_guardarItemTabla($datosItemTabla, $nombreItemTabla, $detallesItems);

        $datosSeccionTabla[trim(strtolower($nombreSeccionTabla))] = $datosItemTabla;
        $datosItemTabla = [];
    }

    /**
     * Formatea la línea actual de la tabla y obtiene sus campos.
     */
    private function _obtenerLineaTabla($linea) {
        // Reemplazo dos o más espacios consecutivos por una combinación especial para delimitar los campos de la línea.
        $lineaFormateada = preg_replace('/[\s][\s]+/', '_!_', $linea);

        $campos = explode('_!_', $lineaFormateada);

        if (!strlen($campos[count($campos) - 1])) {
            // Elimino la última posición de campos de la línea porque contiene un elemento vacío por el caracter de final de línea.
            unset($campos[count($campos) - 1]);
        }

        return $campos;
    }

    /**
     * Obtiene los títulos de la fila que se usa para las guías.
     */
    private function _obtenerTitulosGuia($linea) {
        // Reemplaza espacios por _ en el título FECHA DE GUIA para poder obtener correctamente todos los títulos
        // Esto es necesario porque hay títulos que están separados por un solo caracter
        $linea = str_replace('FECHA DE GUIA', 'FECHA_DE_GUIA', $linea);
        // Reemplazo espacios consecutivos por una combinación especial para delimitar los campos de la línea.
        $lineaFormateada = preg_replace('/[\s]+/', '_!_', $linea);

        $campos = explode('_!_', $lineaFormateada);

        if (!strlen($campos[count($campos) - 1])) {
            // Elimino la última posición de campos de la línea porque contiene un elemento vacío por el caracter de final de línea.
            unset($campos[count($campos) - 1]);
        }

        return $campos;
    }

    private function normalizeNum($num) {
        return str_replace(',', '', $num);
    }

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
     * Lee una factura o una nota de crédito de Dhl y llena el arreglo data.
     */
    private function _leerArchivo($nombre) {
        $storagePath = Storage::disk($this->discoTrabajo)->getDriver()->getAdapter()->getPathPrefix();
        $filename = $storagePath . $nombre;

        $handle = fopen($filename, 'r');

        // Contiene los datos de la sección actual.
        $seccionActual    = [];
        $nombreSeccion    = '';
        $lastitem         = '';
        $this->arrClaves  = [];

        while (($line = fgets($handle)) !== false) {
            $line = $this->fixEncoding($line);
            if (strlen(preg_replace("/[\r\n|\n|\r]+/", '', $line)) == 0) {
                // Omite líneas en blanco
                continue;
            }

            if (preg_match($this->inicioRegex, $line)) {
                // Es el inicio del archivo y se continúa con el procesamiento
                continue;
            }

            if (preg_match($this->seccionRegex, $line, $matches)) {
                // Es una definición de sección.
                if (isset($nombreSeccion)) {
                    // Guardo los datos de la sección previa, si existe, pues ya ha terminado de procesarse
                    $this->_guardarSeccion($nombreSeccion, $seccionActual);
                }
                $nombreSeccion = strtolower(trim($matches[1]));
                continue;
            }

            if (preg_match($this->claveValorRegex, $line, $matches) == 1) {
                $this->arrClaves[] = trim($matches[1]);

                // Es una línea de la forma clave: valor.
                if (strtolower($nombreSeccion) == 'impuestos') {
                    // Requiere tratamiento especial porque la línea contiene varios campos clave: valor.

                    // Creo una representación de la línea en la que múltiples espacios consecutivos son reemplazados
                    // por una combinación especial para poder distinguir cada bloque clave: valor.
                    $formattedLine = preg_replace('/:[\s][\s]+/', ': ', $line);
                    $formattedLine = preg_replace('/[\s][\s]+/', '_!_', $formattedLine);

                    // Obtengo un arreglo de campos clave: valor.
                    $campos = explode('_!_', $formattedLine);

                    // Contiene los detalles de este tipo de impuesto ya que no se pueden agrugar como los campos
                    // convencionales clave: valor porque existen múltiples definiciones iguales (eg. TipoImp: 01, TipoImp: 02...)
                    $lineaImpuesto = [];

                    foreach ($campos as $campo) {
                        if (!strlen($campo)) {
                            // Se ha llegado al final de la línea.
                            continue;
                        }

                        if (preg_match($this->claveValorRegex, $campo, $matches)) {
                            $clave = strtolower(trim($matches[1]));
                            $valor = trim($matches[2]);

                            // Almaceno los valores del campo en el arreglo para la línea actual del impuesto.
                            $lineaImpuesto[$clave] = $valor;
                        }
                    }
                    
                    if(!empty($lineaImpuesto))
                        array_push($seccionActual, $lineaImpuesto);
                    continue;
                }

                if (trim(strtolower($nombreSeccion)) == 'personalizados') {
                    // Requiere un tratamiento especial porque la línea puede contener : al lado derecho e izquierdo del : que define el campo clave: valor.
                    // Creo una representación de la línea en la que se han sustituido dos o más espacios consecutivos seguidos por : para delimitar clave de valor.
                    $formattedLine = preg_replace('/[\s][\s]+:/', '_!_', $line);

                    // Obtengo un arreglo clave: valor.
                    $campo = explode('_!_', $formattedLine);
                    $clave = strtolower(trim($campo[0]));
                    if ($clave === 'pid:' || $clave === 'awb:' || $clave === 'pid' || $clave === 'awb') {
                        if (array_key_exists(2, $campo))
                            $valor = trim($campo[2]);
                        else
                            $valor = trim($campo[1]);
                    }
                    else {
                        $valor = trim($campo[1]);
                    }

                    $seccionActual[$clave] = $valor;
                    continue;
                }

                if (trim(strtolower($nombreSeccion)) == 'datos fiscales receptor') {
                    // Requiere un tratamiento especial porque la línea puede contener : al lado derecho e izquierdo del : que define el campo clave: valor.
                    
                    // Creo una representación de la línea en la que se han sustituido dos o más espacios onsecutivos seguidos por : para delimitar clave de valor.
                    $formattedLine = preg_replace('/[\s][\s]+:/', '_!_', $line);
                    
                    // Obtengo un arreglo clave: valor.
                    $campo = explode('_!_', $formattedLine);
                    $clave = strtolower(trim($campo[0]));
                    if ($clave === 'direccion') {
                        if (array_key_exists(2, $campo))
                            $valor = trim($campo[2]);
                        else
                            $valor = trim($campo[1]);
                    } else {
                        $valor = trim($campo[1]);
                    }
                    
                    $seccionActual[$clave] = $valor;
                    continue;
                }
                
                $clave = strtolower(trim($matches[1]));
                $valor = trim($matches[2]);
                
                // Almaceno los valores actuales de la fila en el arreglo de la sección actual.
                $seccionActual[$clave] = $valor;
                
                continue;
            }
            
            if (substr($line, 0, strlen('NULL')) == 'NULL') {
                // Linea NULL.
                continue;
            }

            // Si llegamos a este punto, es una tabla.

            // Cabeceras de la tabla.
            $cabeceras = [];

            // Contiene el nombre de la sección de la tabla.
            $nombreSeccionTabla = null;

            // Contiene los datos de la sección actual.
            $datosSeccionTabla = [];

            // Contiene el nombre del ítem actual.
            $nombreItemTabla = null;

            // Contiene los datos para el ítem actual.
            $datosItemTabla = [];

            // Contiene los detalles del ítem actual.
            $detallesItems = [];

            // Se leen las líneas sucesivas hasta la definción de la próxima sección.
            while (!preg_match($this->seccionRegex, $line, $matches)) {
                if (trim(strtolower($nombreSeccion)) == 'datos fiscales receptor') {
                    if (!strlen($line)) {
                        // Se omiten las líneas en blanco.
                        $line = $this->fixEncoding(fgets($handle));
                        $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                        $line = trim($line);
                        continue;
                    }

                    $campos = $this->_obtenerLineaTabla($line);
                    if (trim(strtolower($campos[0])) == 'descripcionproducto') {
                        // Es la primera línea de la tabla. Se procede a almacenar los nombres de las cabeceras.
                        for ($i = 1; $i < count($campos); $i++) {
                            if (strlen($campos[$i])) {
                                array_push($cabeceras, trim($campos[$i]));
                            }
                        }

                        $nombreSeccionTabla = 'descripcionproducto';
                        $line = $this->fixEncoding(fgets($handle));
                        $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                        $line = trim($line);
                        continue;
                    }

                    if (trim(strtolower($campos[0])) == 'descripcioncargo') {
                        // Segunda sección de la tabla.
                        if (isset($nombreSeccionTabla)) {
                            // Procedo a guardar la sección previa.
                            $this->_guardarSeccionTabla($nombreItemTabla, $datosSeccionTabla, $nombreSeccionTabla, $datosItemTabla, $detallesItems);
                            unset($nombreItemTabla);
                        }
                        $nombreSeccionTabla = 'descripcioncargo';
                        $line = $this->fixEncoding(fgets($handle));
                        $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                        $line = trim($line);
                        continue;
                    }

                    if (strlen($campos[0])) {
                        if (isset($nombreItemTabla)) {
                            // Se ha encontrado un nuevo ítem en la tabla. Se procede a guardar el ítem actual.
                            $this->_guardarItemTabla($datosItemTabla, $nombreItemTabla, $detallesItems);
                        }
                        $nombreItemTabla = $campos[0];
                    }
                    
                    // Contiene el detalle para el ítem actual.
                    $detalleItem = [];
                    
                    for ($i = count($campos) - 1, $j = count($cabeceras) - 1; $i > 0; $i--, $j--) {
                        $detalleItem[trim(strtolower($cabeceras[$j]))] = str_replace(':', '', $campos[$i]);
                    }

                    if ($detalleItem) {
                        array_push($detallesItems, $detalleItem);
                    }
                }

                if (trim(strtolower($nombreSeccion)) == 'detalle guia') {
                    if (!strlen($line)) {
                        // Se omiten las líneas en blanco.
                        $line = $this->fixEncoding(fgets($handle));
                        $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                        $line = trim($line);
                        continue;
                    }

                    $campos = $this->_obtenerTitulosGuia($line);

                    if (trim(strtolower($campos[0])) == 'guia') {
                        // Es la primera línea de la tabla.
                        // Se procede a almacenar los nombres de las cabeceras.
                        for ($i = 0; $i < count($campos); $i++) {
                            if (strlen($campos[$i])) {
                                // Posición donde empieza la cabecera.
                                $inicioCabecera = strpos($line, $campos[$i]);

                                if ($i + 1 >= count($campos)) {
                                    // Si es la última cabecera, termina en el final de línea.
                                    $finCabecera = strlen($line) - 1;
                                } else {
                                    // Posición final de la cabecera.
                                    $finCabecera = strpos($line, $campos[$i + 1]) - 1;
                                }

                                $cabecera = [
                                    'start' => $inicioCabecera,
                                    'end' => $finCabecera,
                                    'name' => trim(strtolower($campos[$i])),
                                ];
                                array_push($cabeceras, $cabecera);
                            }
                        }
                        $line = $this->fixEncoding(fgets($handle));
                        $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                        continue;
                    }

                    // Contiene el detalle para el ítem actual.
                    $detalleItem = [];

                    $tokens = [
                        24, 48, 108, 134, 158, 178, 196, 219, 239, 264, 314, 379, 439, 479, 505, 524, 543, 567, 588, 613, 622, 637, 652, 667, 695, 723, 737, 750,
                        759, 779, 799, 811, 826, 841, 861, 881, 895, 915, 935, 950, 965, 985,
                        // Cargos
                        1005, 1035, 1063, 1085, 1098, 1113, 1133, 1153, 1165, 1185,
                        1205, 1235, 1263, 1285, 1298, 1313, 1333, 1353, 1365, 1385,
                        1405, 1435, 1463, 1485, 1498, 1513, 1533, 1553, 1565, 1585,
                        1605, 1635, 1663, 1685, 1698, 1713, 1733, 1753, 1765, 1785,
                        1805, 1835, 1863, 1885, 1898, 1913, 1933, 1953, 1965, 1985,
                        2005, 2035, 2063, 2085, 2098, 2113, 2133, 2153, 2165, 2185,
                        2205, 2235, 2263, 2285, 2298, 2313, 2333, 2353, 2365, 2385,
                        2405, 2435, 2463, 2485, 2498, 2513, 2533, 2553, 2565, 2585,
                        2605, 2635, 2663, 2685, 2698, 2713, 2733, 2753, 2765, 2785,
                        2805, 2835, 2863, 2885, 2898, 2913, 2933, 2953, 2965, 2985,
                        3005, 3035, 3063, 3085, 3098, 3113, 3133, 3153, 3165, 3185,
                        3205, 3235, 3263, 3285, 3298, 3313, 3333, 3353, 3365, 3385,
                        3405, 3435, 3463, 3485, 3498, 3513, 3533, 3553, 3565, 3585,
                        // Referencias Adicionales
                        3605, 3640, 3675, 3710
                    ];
                    
                    $init = 0;
                    $theguia = str_pad($line, 2531, ' ');
                    for ($i = 0; $i < count($cabeceras); $i++) {
                        $start = $init;
                        $end = $tokens[$i];
                        $campo = substr($theguia, $start, $end - $start);

                        if (!empty(trim($campo))) {
                            $campo = trim($campo);
                        } else {
                            $campo = "";
                        }

                        $detalleItem[trim(strtolower($cabeceras[$i]['name']))] = $campo;
                        $init = $tokens[$i];
                    }
                    
                    if ($detalleItem) {
                        array_push($seccionActual, $detalleItem);
                    }
                }

                if (trim(strtolower($nombreSeccion)) == 'referencia') {
                    if (!strlen($line)) {
                        // Se omiten las líneas en blanco.
                        $line = $this->fixEncoding(fgets($handle));
                        $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                        continue;
                    }

                    $campos = $this->_obtenerLineaTabla($line);

                    if (trim(strtolower($campos[0])) == 'tipo de documento') {
                        // Es la primera línea de la tabla.
                        // Se procede a almacenar los nombres
                        // de las cabeceras.
                        for ($i = 0; $i < count($campos); $i++) {
                            if (strlen($campos[$i])) {
                                // Posición donde empieza la cabecera.
                                $inicioCabecera = strpos($line, $campos[$i]);
                                if ($i + 1 >= count($campos)) {
                                    // Si es la última cabecera, termina
                                    // en el final de línea.
                                    $finCabecera = strlen($line) - 1;
                                } else {
                                    // Posición final de la cabecera.
                                    $finCabecera = strpos($line, $campos[$i + 1]) - 1;
                                }
                                $cabecera = [
                                    'start' => $inicioCabecera,
                                    'end' => $finCabecera,
                                    'name' => trim(strtolower($campos[$i])),
                                ];

                                array_push($cabeceras, $cabecera);
                            }
                        }
                        $line = $this->fixEncoding(fgets($handle));
                        $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                        continue;
                    }

                    // Contiene el detalle para el ítem actual.
                    $detalleItem = [];

                    for ($i = 0; $i < count($cabeceras); $i++) {
                        $start = intval($cabeceras[$i]['start']);
                        $end = intval($cabeceras[$i]['end']);
                        $campo = substr($line, $start, $end - $start + 1);
                        if ($campo) {
                            $campo = trim($campo);
                        } else {
                            $campo = "";
                        }

                        $detalleItem[$cabeceras[$i]['name']] = $campo;
                    }

                    if ($detalleItem) {
                        array_push($seccionActual, $detalleItem);
                    }
                }

                $line = $this->fixEncoding(fgets($handle));
                $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                $line = trim($line);
            }

            if (trim(strtolower($nombreSeccion)) == 'datos fiscales receptor') {
                if (isset($nombreSeccionTabla)) {
                    // Guardo la segunda sección de la tabla.
                    $this->_guardarSeccionTabla($nombreItemTabla, $datosSeccionTabla, $nombreSeccionTabla, $datosItemTabla, $detallesItems);
                    unset($nombreItemTabla);
                }

                $seccionActual['items'] = $datosSeccionTabla;
            }

            if (trim(strtolower($nombreSeccion)) == 'detalle guia') {
                if (!$seccionActual[count($seccionActual) - 1]['guia']) {
                    unset($seccionActual[count($seccionActual) - 1]);
                }
            }

            if (trim(strtolower($nombreSeccion)) == 'referencia') {
                if (!$seccionActual[count($seccionActual) - 1]['tipo de documento']) {
                    unset($seccionActual[count($seccionActual) - 1]);
                }
            }

            // Al terminar de procesar la tabla, se guardan los
            // datos de la sección actual.
            if (isset($nombreSeccion)) {
                $this->_guardarSeccion($nombreSeccion, $seccionActual);
            }

            // Se registra el nombre de la siguiente sección.
            $nombreSeccion = strtolower(trim($matches[1]));
        }
    }

    /**
     * Lee el archivo indicado, parsea sus datos y los
     * almacena en la base de datos.
     */
    private function _procesarArchivo($user, $rutaArchivo, $ofe, $archivoReal) {
        $this->_leerArchivo($rutaArchivo);

        $duplicados = [];
        foreach(array_count_values($this->arrClaves) as $valor => $cont)
            if($cont > 1 && !in_array($valor, $this->arrClavesPuedeDuplicar)) $duplicados[] = $valor;

        if(!empty($duplicados))
            throw new \Exception('El archivo tiene filas con las siguientes llaves duplicadas: ' . implode(', ', $duplicados));

        $tipodoc = null;
        if ($this->data['datos del encabezado']['tipodoc'] == '01') {
            $tipodoc   = 'FC';
            $tdeCodigo = '01';
        } elseif ($this->data['datos del encabezado']['tipodoc'] == '07') {
            $tipodoc   = 'NC';
            $tdeCodigo = '91';
        } elseif ($this->data['datos del encabezado']['tipodoc'] == '08') {
            $tipodoc   = 'ND';
            $tdeCodigo = '92';
        }

        $this->tipo = $tipodoc;

        // El siguiente bloque permite generar de manera automática consecutivos para los documentos
        /*if($ofe->getConfiguracionSoftwareProveedorTecnologico->sft_estado == 'En Pruebas') {
            $ultimoConsecutivo = EmisionDocumentos::select('cdo_consecutivo')
                ->where('cdo_tipo', $tipodoc)
                ->where('ofe_id', $ofe->ofe_id)
                ->orderBy('cdo_consecutivo', 'desc')
                ->first();
            if ($ultimoConsecutivo) {
                $cdo_consecutivo = intval($ultimoConsecutivo->cdo_consecutivo) + $this->consecutivo;
                $this->consecutivo++;
            } else {
                $cdo_consecutivo = 980000000;
            }
            
            if ($tipodoc == "FC") {
                $this->data['datos del encabezado']['prefijo'] = "PRUE";
            }
            $this->data['datos del encabezado']['numero'] = $cdo_consecutivo;
            $this->data['datos fiscales receptor']['email'] = "johana.arboleda@openits.co";
            $this->data['datos cae']['numeroresolucion'] = "9000000032421248";
            $this->data['datos del encabezado']['fechaemision'] = date('Y-m-d');
            $this->data['datos del encabezado']['fechavence'] = date('Y-m-d');
        } */

        $receptor = [
            'identificacion'                => (isset($this->data['datos fiscales receptor']['identificacion'])) ? $this->data['datos fiscales receptor']['identificacion'] : '',
            'tipo_contribuyente'            => (isset($this->data['datos fiscales receptor']['tipo contribuyente'])) ? $this->data['datos fiscales receptor']['tipo contribuyente'] : '',
            'regimen_contable'              => (isset($this->data['datos fiscales receptor']['regimen contable'])) ? $this->data['datos fiscales receptor']['regimen contable'] : '',
            'tipo_documento'                => (isset($this->data['datos fiscales receptor']['tipo documento'])) ? $this->data['datos fiscales receptor']['tipo documento'] : '',
            'tipo_operacion'                => (isset($this->data['datos fiscales receptor']['tipo de operacion'])) ? $this->data['datos fiscales receptor']['tipo de operacion'] : '',
            'nombre'                        => (isset($this->data['datos fiscales receptor']['nombre receptor'])) ? $this->data['datos fiscales receptor']['nombre receptor'] : '',
            'nombre_comercial'              => (isset($this->data['datos fiscales receptor']['nombre comercial'])) ? $this->data['datos fiscales receptor']['nombre comercial'] : '',
            'primer_nombre'                 => (isset($this->data['datos fiscales receptor']['1er nombre'])) ? $this->data['datos fiscales receptor']['1er nombre'] : '',
            'segundo_nombre'                => (isset($this->data['datos fiscales receptor']['2do nombre'])) ? $this->data['datos fiscales receptor']['2do nombre'] : '',
            'primer_apellido'               => (isset($this->data['datos fiscales receptor']['1er apellido'])) ? $this->data['datos fiscales receptor']['1er apellido'] : '',
            'segundo_apellido'              => (isset($this->data['datos fiscales receptor']['2do apellido'])) ? $this->data['datos fiscales receptor']['2do apellido'] : '',
            'pais'                          => (isset($this->data['datos fiscales receptor']['pais'])) ? $this->data['datos fiscales receptor']['pais'] : '',
            'departamento'                  => (isset($this->data['datos fiscales receptor']['provincia'])) ? $this->data['datos fiscales receptor']['provincia'] : '',
            'ciudad'                        => (isset($this->data['datos fiscales receptor']['ciudad'])) ? $this->data['datos fiscales receptor']['ciudad'] : '',
            'codigo'                        => (isset($this->data['datos fiscales receptor']['codigo'])) ? $this->data['datos fiscales receptor']['codigo'] : '',
            'codigo_postal_correspondencia' => (isset($this->data['datos fiscales receptor']['codigo correspondencia'])) ? $this->data['datos fiscales receptor']['codigo correspondencia'] : '',
            'direccion'                     => (isset($this->data['datos fiscales receptor']['direccion'])) ? $this->data['datos fiscales receptor']['direccion'] : '',
            'telefono'                      => (isset($this->data['datos fiscales receptor']['telefono'])) ? $this->data['datos fiscales receptor']['telefono'] : '',
            'email'                         => (isset($this->data['datos fiscales receptor']['email'])) ? mb_strtolower(trim($this->data['datos fiscales receptor']['email'],";"), 'UTF-8') : '',
            'responsabilidades_fiscales'    => (isset($this->data['datos fiscales receptor']['responsabilidades fiscales'])) ? $this->data['datos fiscales receptor']['responsabilidades fiscales'] : '',
            'codigos_tributo'               => (isset($this->data['datos fiscales receptor']['codigo de tributo'])) ? $this->data['datos fiscales receptor']['codigo de tributo'] : '',
            'enviar'                        => (isset($this->data['datos fiscales receptor']['enviar'])) ? $this->data['datos fiscales receptor']['enviar'] : '',
            'no_cuenta'                     => (isset($this->data['datos fiscales receptor']['no.cuenta'])) ? $this->data['datos fiscales receptor']['no.cuenta'] : '',
            'zonaops'                       => (isset($this->data['datos fiscales receptor']['zonaops'])) ? $this->data['datos fiscales receptor']['zonaops'] : '',
            'select'                        => (isset($this->data['datos fiscales receptor']['select'])) ? $this->data['datos fiscales receptor']['select'] : ''
        ];

        $pais = ParametrosPais::select(['pai_id', 'pai_codigo'])
            ->where('pai_descripcion', $this->data['datos fiscales receptor']['pais'])
            ->first();

        if(!$pais) {
            return [
                'tipodoc'           => $tipodoc,
                'rfa_prefijo'       => $this->data['datos del encabezado']['prefijo'],
                'cdo_consecutivo'   => (string)$this->data['datos del encabezado']['numero'],
                'statusCode'        => 404,
                'error_adquirente'  => true,
                'response'          => 'No se encontró el país del adquirente. País ['.$this->data['datos fiscales receptor']['pais'].']'
            ];
        }

        //Cambios con el anexo 1.8, la descripcion de la ciudad de bogota cambio
        if (substr_count($this->data['datos fiscales receptor']['provincia'], "BOGOTA") > 0)
            $this->data['datos fiscales receptor']['provincia'] = 'BOGOTÁ';

        $departamento = ParametrosDepartamento::select(['dep_id', 'dep_codigo'])
            ->where('dep_descripcion', $this->data['datos fiscales receptor']['provincia'])
            ->where('pai_id', $pais->pai_id)
            ->first();

        if(!$departamento) {
            return [
                'tipodoc'           => $tipodoc,
                'rfa_prefijo'       => $this->data['datos del encabezado']['prefijo'],
                'cdo_consecutivo'   => (string)$this->data['datos del encabezado']['numero'],
                'statusCode'        => 404,
                'error_adquirente'  => true,
                'response'          => 'No se encontró el departamento para el país del adquirente. País ['.$this->data['datos fiscales receptor']['pais'].'] - Departamento ['.$this->data['datos fiscales receptor']['provincia'].']'
            ];
        }

        //Cambios con el anexo 1.8, la descripcion de la ciudad de bogota cambio
        if (substr_count($this->data['datos fiscales receptor']['ciudad'], "BOGOTA") > 0)
            $this->data['datos fiscales receptor']['ciudad'] = 'BOGOTÁ, D.C.';

        $ciudad = ParametrosMunicipio::select(['mun_id', 'mun_codigo'])
            ->where('mun_descripcion', $this->data['datos fiscales receptor']['ciudad'])
            ->where('pai_id', $pais->pai_id)
            ->where('dep_id', $departamento->dep_id)
            ->first();

        if(!$ciudad) {
            return [
                'tipodoc'           => $tipodoc,
                'rfa_prefijo'       => $this->data['datos del encabezado']['prefijo'],
                'cdo_consecutivo'   => (string)$this->data['datos del encabezado']['numero'],
                'statusCode'        => 404,
                'error_adquirente'  => true,
                'response'          => 'No se encontró la ciudad para el país y departamento del adquirente. País ['.$this->data['datos fiscales receptor']['pais'].'] - Departamento ['.$this->data['datos fiscales receptor']['provincia'].'] - Ciudad ['.$this->data['datos fiscales receptor']['ciudad'].']'
            ];
        }

        // NIT OFE.
        $nitOfe = $this->data['datos fiscales emisor']['nit'];
        $nitOfe = str_replace('NIT', '', $nitOfe);
        $nitOfe = str_replace('.', '', $nitOfe);
        $nitOfe = preg_replace('/-[\S]*/', '', $nitOfe);
        $nitOfe = trim($nitOfe);

        $correoAdquirente = explode(";", trim(mb_strtolower($receptor['email'], 'UTF-8')));

        $adquirenteDI = [
            'adq_identificacion'                => $receptor['identificacion'],
            'adq_id_personalizado'              => array_key_exists('no.cuenta', $this->data['datos fiscales receptor']) ? $this->data['datos fiscales receptor']['no.cuenta'] : '',
            'ofe_identificacion'                => $nitOfe,
            'adq_razon_social'                  => ($receptor['tipo_contribuyente'] == 1) ? $receptor['nombre'] : '',
            'adq_nombre_comercial'              => ($receptor['tipo_contribuyente'] == 1) ? ($receptor['nombre_comercial'] ?? $receptor['nombre']) : '',
            'adq_primer_apellido'               => ($receptor['tipo_contribuyente'] != 1) ? $receptor['primer_apellido'] : '',
            'adq_segundo_apellido'              => ($receptor['tipo_contribuyente'] != 1) ? $receptor['segundo_apellido'] : '',
            'adq_primer_nombre'                 => ($receptor['tipo_contribuyente'] != 1) ? $receptor['primer_nombre'] : '',
            'adq_otros_nombres'                 => ($receptor['tipo_contribuyente'] != 1) ? $receptor['segundo_nombre'] : '',
            'tdo_codigo'                        => $receptor['tipo_documento'],
            'toj_codigo'                        => $receptor['tipo_contribuyente'],
            'pai_codigo'                        => $pais->pai_codigo,
            'dep_codigo'                        => $departamento->dep_codigo,
            'mun_codigo'                        => $ciudad->mun_codigo,
            'cpo_codigo'                        => $receptor['codigo_postal_correspondencia'],
            'adq_direccion'                     => $receptor['direccion'],
            'adq_telefono'                      => $receptor['telefono'],
            'pai_codigo_domicilio_fiscal'       => $pais->pai_codigo,
            'dep_codigo_domicilio_fiscal'       => $departamento->dep_codigo,
            'mun_codigo_domicilio_fiscal'       => $ciudad->mun_codigo,
            'cpo_codigo_domicilio_fiscal'       => $receptor['codigo_postal_correspondencia'],
            'adq_direccion_domicilio_fiscal'    => $receptor['direccion'],
            'adq_correo'                        => $correoAdquirente[0],
            'adq_matricula_mercantil'           => '',
            'adq_correos_notificacion'          => str_replace(';', ',', mb_strtolower(trim($receptor['email'],";"), 'UTF-8')),
            'rfi_codigo'                        => $receptor['regimen_contable'],
            'ref_codigo'                        => explode(';', $receptor['responsabilidades_fiscales']),
            'responsable_tributos'              => explode(';', $receptor['codigos_tributo'])
        ];

        $storagePath = Storage::disk($this->discoTrabajo)->getDriver()->getAdapter()->getPathPrefix();
        $documentoIntegracion = file_get_contents($storagePath . $rutaArchivo);

        // Detalle descuentos
        // Estas posiciones del array son muy especiales debido a que no llegan como una sección independiente en el TXT sino que
        // se encuentran dentro de la información del receptor y combina data del tipo clave:valor dentro de una subsección que
        // adicionalmente tiene encabezados de valores para COP y US
        if(array_key_exists('totalvlr_cop', $this->data['datos fiscales receptor']['items']['descripcioncargo']['descuento'][0]) && $this->data['datos fiscales receptor']['items']['descripcioncargo']['descuento'][0]['totalvlr_cop'] > 0) {
            $codigoDescuento = (isset($this->data['datos fiscales receptor']['codigo descuento'])) ? $this->data['datos fiscales receptor']['codigo descuento'] : null;
            if($codigoDescuento) {
                $descuento = ParametrosCodigoDescuento::select(['cde_id', 'cde_codigo', 'cde_descripcion'])
                    ->where('cde_codigo', $codigoDescuento)
                    ->first();
                if($descuento) {
                    $dataJson['cdo_detalle_descuentos'][] = [
                        'cde_codigo' => $codigoDescuento,
                        'razon' => $descuento->cde_descripcion,
                        'porcentaje' => (isset($this->data['datos fiscales receptor']['items']['descripcioncargo']['porcentaje total del descuento'][0]['totalvlr_cop'])) ? $this->data['datos fiscales receptor']['items']['descripcioncargo']['porcentaje total del descuento'][0]['totalvlr_cop'] : null,
                        'valor_moneda_nacional' => [
                            'base' => (isset($this->data['datos fiscales receptor']['items']['descripcioncargo']['base total del descuento'][0]['totalvlr_cop'])) ? $this->getDBNum($this->normalizeNum($this->data['datos fiscales receptor']['items']['descripcioncargo']['base total del descuento'][0]['totalvlr_cop'])) : null,
                            'valor' => $this->getDBNum($this->normalizeNum($this->data['datos fiscales receptor']['items']['descripcioncargo']['descuento'][0]['totalvlr_cop']))
                        ]
                    ];
                    $dataJson['cdo_descuentos'] = $this->getDBNum($this->normalizeNum($this->data['datos fiscales receptor']['items']['descripcioncargo']['descuento'][0]['totalvlr_cop']));
                }
            }
        }

        $valorSinImpuestos                 = 0;
        $valorSinImpuestosMonedaExtranjera = 0;
        $valorImpuestos                    = 0;

        $items             = [];
        $retenciones       = [];
        $tributos          = [];
        $dataJson['items'] = [];
        
        //Campos excluidos
        $campoExcluidos = [
            'guia', 'nombre_descuento', 'descuento_vlr_usd', 'descuento_vlr_cop', 
            'nombre_cargo1', 'extra_cargo1_vlr_usd', 'extra_cargo1_vlr_cop', 
            'nombre_cargo2', 'extra_cargo2_vlr_usd', 'extra_cargo2_vlr_cop', 
            'nombre_cargo3', 'extra_cargo3_vlr_usd', 'extra_cargo3_vlr_cop', 
            'nombre_cargo4', 'extra_cargo4_vlr_usd', 'extra_cargo4_vlr_cop', 
            'nombre_cargo5', 'extra_cargo5_vlr_usd', 'extra_cargo5_vlr_cop', 
            'nombre_cargo6', 'extra_cargo6_vlr_usd', 'extra_cargo6_vlr_cop', 
            'nombre_cargo7', 'extra_cargo7_vlr_usd', 'extra_cargo7_vlr_cop', 
            'nombre_cargo8', 'extra_cargo8_vlr_usd', 'extra_cargo8_vlr_cop', 
            'nombre_cargo9', 'extra_cargo9_vlr_usd', 'extra_cargo9_vlr_cop', 
            'nombre_cargo10', 'extra_cargo10_vlr_usd', 'extra_cargo10_vlr_cop', 
            'nombre_cargo11', 'extra_cargo11_vlr_usd', 'extra_cargo11_vlr_cop', 
            'nombre_cargo12', 'extra_cargo12_vlr_usd', 'extra_cargo12_vlr_cop', 
            'nombre_cargo13', 'extra_cargo13_vlr_usd', 'extra_cargo13_vlr_cop'
        ];

        //Informacion adicional a nivel de item
        foreach ($this->data['detalle guia'] as $key => $detalleGuia) {
            $guia = [
                'guia'                  => array_key_exists('guia', $detalleGuia) ? $this->fixEncoding($detalleGuia['guia']) : '',
                'fecha_guia'            => array_key_exists('fecha_de_guia', $detalleGuia) ? $this->fixEncoding($detalleGuia['fecha_de_guia']) : null,
                'remitente'             => array_key_exists('remitente', $detalleGuia) ? $this->fixEncoding($detalleGuia['remitente']) : '',
                'origen'                => array_key_exists('origen', $detalleGuia) ? $this->fixEncoding($detalleGuia['origen']) : '',
                'direccion_origen'      => array_key_exists('direccionorigen', $detalleGuia) ? $this->fixEncoding($detalleGuia['direccionorigen']) : '',
                'barrio_origen'         => array_key_exists('barrioorigen', $detalleGuia) ? $this->fixEncoding($detalleGuia['barrioorigen']) : '',
                'ciudad_origen'         => array_key_exists('ciudadorigen', $detalleGuia) ? $this->fixEncoding($detalleGuia['ciudadorigen']) : '',
                'pais_origen'           => array_key_exists('paisorigen', $detalleGuia) ? $this->fixEncoding($detalleGuia['paisorigen']) : '',
                'codigo_postal_origen'  => array_key_exists('codigopostalorigen', $detalleGuia) ? $this->fixEncoding($detalleGuia['codigopostalorigen']) : '',
                'telefono_origen'       => array_key_exists('telefonoorigen', $detalleGuia) ? $this->fixEncoding($detalleGuia['telefonoorigen']) : '',
                'destino'               => array_key_exists('destino', $detalleGuia) ? $this->fixEncoding($detalleGuia['destino']) : '',
                'contacto'              => array_key_exists('contacto', $detalleGuia) ? $this->fixEncoding($detalleGuia['contacto']) : '',
                'destinatario'          => array_key_exists('destinatario', $detalleGuia) ? $this->fixEncoding($detalleGuia['destinatario']) : '',
                'contacto2'             => array_key_exists('contacto2', $detalleGuia) ? $this->fixEncoding($detalleGuia['contacto2']) : '',
                'direccion_destino'     => array_key_exists('direcciondestino', $detalleGuia) ? $this->fixEncoding($detalleGuia['direcciondestino']) : '',
                'barrio_destino'        => array_key_exists('barriodestino', $detalleGuia) ?  $this->fixEncoding($detalleGuia['barriodestino']) : '',
                'ciudad_destino'        => array_key_exists('ciudaddestino', $detalleGuia) ? $this->fixEncoding($detalleGuia['ciudaddestino']) : '',
                'pais_destino'          => array_key_exists('paisdestino', $detalleGuia) ? $this->fixEncoding($detalleGuia['paisdestino']) : '',
                'codigo_postal_destino' => array_key_exists('codigopostaldestino', $detalleGuia) ?  $this->fixEncoding($detalleGuia['codigopostaldestino']) : '',
                'telefono_destino'      => array_key_exists('telefonodestino', $detalleGuia) ? $this->fixEncoding($detalleGuia['telefonodestino']) : '',
                'peso'                  => array_key_exists('peso', $detalleGuia) ? $this->fixEncoding($detalleGuia['peso']) : '',
                'piezas'                => array_key_exists('piezas', $detalleGuia) ? $this->fixEncoding($detalleGuia['piezas']) : '',
                'prd'                   => array_key_exists('prd', $detalleGuia) ? $this->fixEncoding($detalleGuia['prd']) : '',
                'zona'                  => array_key_exists('zona', $detalleGuia) ? $this->fixEncoding($detalleGuia['zona']) : '',
                'referencia'            => array_key_exists('referencia', $detalleGuia) ? $this->fixEncoding($detalleGuia['referencia']) : '',
                'total_vlr_usd'         => array_key_exists('totalvrlusd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['totalvrlusd'])) : '',
                'total_vlr_cop'         => array_key_exists('guia', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['totalvrlcop'])) : '',
                //Descuento
                'nombre_descuento'      => (array_key_exists('valdescuencop', $detalleGuia) && $this->getDBNum($this->normalizeNum($detalleGuia['valdescuencop'])) != '') ? 'DESCUENTO' : '',
                'descuento_vlr_usd'     => array_key_exists('valdescuenusd', $detalleGuia) ? ('-' . $this->getDBNum($this->normalizeNum($detalleGuia['valdescuenusd']))) : '',
                'descuento_vlr_cop'     => array_key_exists('valdescuencop', $detalleGuia) ? ('-' . $this->getDBNum($this->normalizeNum($detalleGuia['valdescuencop']))) : '',
                //Cargos
                'nombre_cargo1'         => array_key_exists('nombrecargo1', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo1']) : '',
                'extra_cargo1_vlr_usd'  => array_key_exists('extracargo1vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo1vlr_usd'])) : '',
                'extra_cargo1_vlr_cop'  => array_key_exists('extracargo1vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo1vlr_cop'])) : '',
                'nombre_cargo2'         => array_key_exists('nombrecargo2', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo2']) : '',
                'extra_cargo2_vlr_usd'  => array_key_exists('extracargo2vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo2vlr_usd'])) : '',
                'extra_cargo2_vlr_cop'  => array_key_exists('extracargo2vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo2vlr_cop'])) : '',
                'nombre_cargo3'         => array_key_exists('nombrecargo3', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo3']) : '',
                'extra_cargo3_vlr_usd'  => array_key_exists('extracargo3vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo3vlr_usd'])) : '',
                'extra_cargo3_vlr_cop'  => array_key_exists('extracargo3vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo3vlr_cop'])) : '',
                'nombre_cargo4'         => array_key_exists('nombrecargo4', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo4']) : '',
                'extra_cargo4_vlr_usd'  => array_key_exists('extracargo4vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo4vlr_usd'])) : '',
                'extra_cargo4_vlr_cop'  => array_key_exists('extracargo4vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo4vlr_cop'])) : '',
                'nombre_cargo5'         => array_key_exists('nombrecargo5', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo5']) : '',
                'extra_cargo5_vlr_usd'  => array_key_exists('extracargo5vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo5vlr_usd'])) : '',
                'extra_cargo5_vlr_cop'  => array_key_exists('extracargo5vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo5vlr_cop'])) : '',
                'nombre_cargo6'         => array_key_exists('nombrecargo6', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo6']) : '',
                'extra_cargo6_vlr_usd'  => array_key_exists('extracargo6vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo6vlr_usd'])) : '',
                'extra_cargo6_vlr_cop'  => array_key_exists('extracargo6vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo6vlr_cop'])) : '',
                'nombre_cargo7'         => array_key_exists('nombrecargo7', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo7']) : '',
                'extra_cargo7_vlr_usd'  => array_key_exists('extracargo7vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo7vlr_usd'])) : '',
                'extra_cargo7_vlr_cop'  => array_key_exists('extracargo7vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo7vlr_cop'])) : '',
                'nombre_cargo8'         => array_key_exists('nombrecargo8', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo8']) : '',
                'extra_cargo8_vlr_usd'  => array_key_exists('extracargo8vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo8vlr_usd'])) : '',
                'extra_cargo8_vlr_cop'  => array_key_exists('extracargo8vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo8vlr_cop'])) : '',
                'nombre_cargo9'         => array_key_exists('nombrecargo9', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo9']) : '',
                'extra_cargo9_vlr_usd'  => array_key_exists('extracargo9vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo9vlr_usd'])) : '',
                'extra_cargo9_vlr_cop'  => array_key_exists('extracargo9vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo9vlr_cop'])) : '',
                'nombre_cargo10'         => array_key_exists('nombrecargo10', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo10']) : '',
                'extra_cargo10_vlr_usd'  => array_key_exists('extracargo10vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo10vlr_usd'])) : '',
                'extra_cargo10_vlr_cop'  => array_key_exists('extracargo10vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo10vlr_cop'])) : '',
                'nombre_cargo11'         => array_key_exists('nombrecargo11', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo11']) : '',
                'extra_cargo11_vlr_usd'  => array_key_exists('extracargo11vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo11vlr_usd'])) : '',
                'extra_cargo11_vlr_cop'  => array_key_exists('extracargo11vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo11vlr_cop'])) : '',
                'nombre_cargo12'         => array_key_exists('nombrecargo12', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo12']) : '',
                'extra_cargo12_vlr_usd'  => array_key_exists('extracargo12vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo12vlr_usd'])) : '',
                'extra_cargo12_vlr_cop'  => array_key_exists('extracargo12vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo12vlr_cop'])) : '',
                'nombre_cargo13'         => array_key_exists('nombrecargo13', $detalleGuia) ? $this->fixEncoding($detalleGuia['nombrecargo13']) : '',
                'extra_cargo13_vlr_usd'  => array_key_exists('extracargo13vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo13vlr_usd'])) : '',
                'extra_cargo13_vlr_cop'  => array_key_exists('extracargo13vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo13vlr_cop'])) : '',
                //Datos Adicionales
                'referencia_adicional1' => array_key_exists('referencia_adicional1', $detalleGuia) ? $this->fixEncoding($detalleGuia['referencia_adicional1']) : '',
                'referencia_adicional2' => array_key_exists('referencia_adicional2', $detalleGuia) ? $this->fixEncoding($detalleGuia['referencia_adicional2']) : '',
                'referencia_adicional3' => array_key_exists('referencia_adicional3', $detalleGuia) ? $this->fixEncoding($detalleGuia['referencia_adicional3']) : '',
                'referencia_adicional4' => array_key_exists('referencia_adicional4', $detalleGuia) ? $this->fixEncoding($detalleGuia['referencia_adicional4']) : '',
                //Incluyendo las etiquetas que se deben excluir porque ya se estan enviando en otra seccion del xml-ubl
                'ddo_informacion_adicional_excluir_xml' => $campoExcluidos
            ];

            if(array_key_exists('valorretencion', $detalleGuia) && $this->getDBNum($this->normalizeNum($detalleGuia['valorretencion'])) > 0) {
                $retenciones[] = [
                    'ddo_secuencia'  => strval($key + 1),
                    'tri_codigo'     => array_key_exists('codreten', $detalleGuia) ? $detalleGuia['codreten'] : '',
                    'iid_valor'      => array_key_exists('valorretencion', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['valorretencion'])) : '',
                    'iid_porcentaje' => [
                        'iid_base'       => array_key_exists('basecop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['basecop'])) : '',
                        'iid_porcentaje' => array_key_exists('porcenreten', $detalleGuia) ? $detalleGuia['porcenreten'] : '',
                    ]
                ];
            }

            if(array_key_exists('valimpuestcop', $detalleGuia) && $this->getDBNum($this->normalizeNum($detalleGuia['valimpuestcop'])) > 0) {
                $valorImpuestos += array_key_exists('valimpuestcop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['valimpuestcop'])) : 0;
                $tributos[] = [
                    'ddo_secuencia'  => strval($key + 1),
                    'tri_codigo'     => '01',
                    'iid_valor'      => array_key_exists('valimpuestcop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['valimpuestcop'])) : '',
                    'iid_porcentaje' => [
                        'iid_porcentaje' => array_key_exists('porcentimp', $detalleGuia) ? $detalleGuia['porcentimp'] : '',
                        'iid_base'       => array_key_exists('baseimpcop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['baseimpcop'])) : ''
                    ]
                ];
            }

            $extracargo1NoFlete = false;
            $sumaExtracargos    = 0;
            $sumaExtracargosUSD = 0;
            for($i=1; $i<=13; $i++) {
                if(
                    (array_key_exists('extracargo'.$i.'vlr_cop', $detalleGuia) && $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_cop'])) > 0) ||
                    (array_key_exists('extracargo'.$i.'vlr_usd', $detalleGuia) && $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_usd'])) > 0)
                ) {
                    $sumaExtracargos    += $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_cop']));
                    $sumaExtracargosUSD += $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_usd']));

                    $valorSinImpuestos                 += array_key_exists('extracargo'.$i.'vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_cop'])) : '0.00';
                    $valorSinImpuestosMonedaExtranjera += array_key_exists('extracargo'.$i.'vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_usd'])) : '0.00';

                    // En el primer extracargo siempre envian el valor del item antes de cargos y descuentos
                    if($i == 1) {
                        $codigoProducto = array_key_exists('codprodu', $detalleGuia) ? $detalleGuia['codprodu'] : '';
                        switch ($codigoProducto) {
                            case '78102204':
                                $descripcionItem = "Servicios de entrega a nivel mundial de cartas o paquetes pequeños.";
                            break;
                            case '78102201':
                                $descripcionItem = "Servicios de entrega postal nacional.";
                            break;
                            default:
                                $descripcionItem = "Envios internacionales y/o nacionales.";
                            break;
                        }

                        $ddoTotal    = array_key_exists('extracargo'.$i.'vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_cop'])) : '0.00';
                        $ddoTotalUSD = array_key_exists('extracargo'.$i.'vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_usd'])) : '0.00';

                        $item = [
                            'ddo_codigo'                            => $codigoProducto,
                            'ddo_secuencia'                         => strval($key + 1),
                            'ddo_descripcion_uno'                   => $descripcionItem.' No. de Guia ' . $detalleGuia['guia'],
                            'ddo_descripcion_dos'                   => ($codigoProducto == '78102204') ? $this->fixEncoding("ENVÍOS INTERNACIONALES (Puerta a puerta)") : null,
                            'ddo_descripcion_tres'                  => array_key_exists('referencia', $detalleGuia) ? $this->fixEncoding("Referencia: ".$detalleGuia['referencia']) : null,
                            'ddo_cantidad'                          => "1.00",
                            'ddo_total'                             => $ddoTotal,
                            'ddo_total_moneda_extranjera'           => $ddoTotalUSD,
                            'ddo_tipo_item'                         => 'IP',
                            'ddo_valor_unitario'                    => $ddoTotal,
                            'ddo_valor_unitario_moneda_extranjera'  => $ddoTotalUSD,
                            'ddo_informacion_adicional'             => $guia,
                            'und_codigo'                            => (isset($this->data['datos fiscales receptor']['unidad de medida'])) ? $this->data['datos fiscales receptor']['unidad de medida'] : '',
                            'cpr_codigo'                            => (isset($this->data['datos fiscales receptor']['clasificacion producto'])) ? $this->data['datos fiscales receptor']['clasificacion producto'] : ''
                        ];

                        //Si el codigo de producto es 78102201 y el primer cargo incluye la palabra FLETE
                        //debe llevarse el primer cargo como item del documento y el resto como cargos del item
                        //si no cumple la condición, deben sumarse todos los cargos y crear un solo item con este valor
                        //no se crean cargos
                        if (!($codigoProducto == "78102204" || ($codigoProducto == "78102201" && stristr($detalleGuia['nombrecargo1'], 'FLETE')))) {
                            //El primer cargo no cumple la condicion
                            $extracargo1NoFlete = true;
                        }

                    } else {
                        $item['ddo_cargos'][] = [
                            'razon' => array_key_exists('nombrecargo'.$i.'', $detalleGuia) ? $detalleGuia['nombrecargo'.$i.''] : '',
                            'valor_moneda_nacional' => [
                                'valor' => array_key_exists('extracargo'.$i.'vlr_cop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_cop'])) : '',
                                'base'  => array_key_exists('bas'.$i.'carcop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['bas'.$i.'carcop'])) : ''
                            ],
                            'valor_moneda_extranjera' => [
                                'valor' => array_key_exists('extracargo'.$i.'vlr_usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['extracargo'.$i.'vlr_usd'])) : '',
                                'base'  => array_key_exists('bas'.$i.'usd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['bas'.$i.'usd'])) : ''
                            ],
                            'porcentaje' => array_key_exists('porcen'.$i.'cop', $detalleGuia) ? $detalleGuia['porcen'.$i.'cop'] : ''
                        ];
                    }
                }
            }

            if($extracargo1NoFlete) {
                $item['ddo_total']                            = $this->getDBNum($this->normalizeNum($sumaExtracargos));
                $item['ddo_total_moneda_extranjera']          = $this->getDBNum($this->normalizeNum($sumaExtracargosUSD));
                $item['ddo_valor_unitario']                   = $this->getDBNum($this->normalizeNum($sumaExtracargos));
                $item['ddo_valor_unitario_moneda_extranjera'] = $this->getDBNum($this->normalizeNum($sumaExtracargosUSD));
                $item['ddo_cargos']                           = [];
            }

            if(array_key_exists('valdescuencop', $detalleGuia) && $this->getDBNum($this->normalizeNum($detalleGuia['valdescuencop'])) > 0) {

                $valorSinImpuestos                 -= array_key_exists('valdescuencop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['valdescuencop'])) : 0;
                $valorSinImpuestosMonedaExtranjera -= array_key_exists('valdescuenusd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['valdescuenusd'])) : 0;

                $item['ddo_descuentos'][] = [
                    'porcentaje' => array_key_exists('porcentcop', $detalleGuia) ? $detalleGuia['porcentcop'] : '',
                    'razon'      => 'Descuento por negociación de tarifas.',
                    'valor_moneda_nacional' => [
                        'base'  => array_key_exists('basedescop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['basedescop'])) : '',
                        'valor' => array_key_exists('valdescuencop', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['valdescuencop'])) : '',
                    ],
                    'valor_moneda_extranjera' => [
                        'base'  => array_key_exists('baseusd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['baseusd'])) : '',
                        'valor' => array_key_exists('valdescuenusd', $detalleGuia) ? $this->getDBNum($this->normalizeNum($detalleGuia['valdescuenusd'])) : '',
                    ]
                ];
            }

            array_push($items, $item);
        }

        $dataJson = [
            'tde_codigo'                                => $tdeCodigo,
            'adq_identificacion'                        => $this->data['datos fiscales receptor']['identificacion'],
            'adq_id_personalizado'                      => $adquirenteDI['adq_id_personalizado'],
            'adquirente'                                => $adquirenteDI,
            'ofe_identificacion'                        => $nitOfe,
            'rfa_resolucion'                            => $this->data['datos cae']['numeroresolucion'],
            'rfa_prefijo'                               => $this->data['datos del encabezado']['prefijo'],
            'cdo_consecutivo'                           => (string)$this->data['datos del encabezado']['numero'],
            'cdo_fecha'                                 => $this->data['datos del encabezado']['fechaemision'],
            'cdo_hora'                                  => $this->data['datos del encabezado']['hora'],
            'cdo_fecha_vencimiento'                     => $this->data['datos del encabezado']['fechavence'],
            'cdo_fecha_procesar_documento'              => date('Y-m-d H:i:s'),
            'cdo_envio_dian_moneda_extranjera'          => 'NO',
            'mon_codigo'                                => 'COP',
            'cdo_valor_sin_impuestos'                   => $this->getDBNum($valorSinImpuestos),
            'cdo_valor_sin_impuestos_moneda_extranjera' => $this->getDBNum($valorSinImpuestosMonedaExtranjera),
            'cdo_impuestos'                             => $this->getDBNum($valorImpuestos),           
            'cdo_total'                                 => $this->getDBNum($valorSinImpuestos + $valorImpuestos),
            'cdo_observacion'                           => '',
            'cdo_trm'                                   => $this->normalizeNum($this->data['datos fiscales receptor']['tasacambio']),
            'cdo_documento_integracion'                 => base64_encode($documentoIntegracion),
            'top_codigo'                                => $this->data['datos fiscales receptor']['tipo de operacion'],
            'cdo_representacion_grafica_documento'      => '1',
            'cdo_representacion_grafica_acuse'          => '1',
            'cdo_informacion_adicional' => [
                'cdo_procesar_documento'                => 'SI',
                'emisor'                                => ['identificacion' => $nitOfe],
                'receptor'                              => $receptor,
                'documento_transporte'                  => array_key_exists('doctotransporte', $this->data['datos del encabezado']) ? $this->data['datos del encabezado']['doctotransporte'] : '',
                'linea1_dian'                           => array_key_exists('linea1dian', $this->data['datos del encabezado']) ? $this->data['datos del encabezado']['linea1dian'] : '',
                'linea3_dian'                           => array_key_exists('linea3dian', $this->data['datos del encabezado']) ? $this->data['datos del encabezado']['linea3dian'] : '',
                'linea4_dian'                           => array_key_exists('linea4dian', $this->data['datos del encabezado']) ? $this->data['datos del encabezado']['linea4dian'] : '',
                'numero_interno'                        => array_key_exists('numerointerno', $this->data['datos del encabezado']) ? $this->data['datos del encabezado']['numerointerno'] : '',
                'no_cuenta'                             => array_key_exists('no.cuenta', $this->data['datos fiscales receptor']) ? $this->data['datos fiscales receptor']['no.cuenta'] : '',
                'correos_receptor'                      => array_key_exists('email', $this->data['datos fiscales receptor']) ? trim($this->data['datos fiscales receptor']['email'],";") : '',
                'informacion_tributaria1'               => array_key_exists('informaciontributaria1', $this->data['personalizados']) ? $this->data['personalizados']['informaciontributaria1'] : '',
                'informacion_tributaria2'               => array_key_exists('informaciontributaria2', $this->data['personalizados']) ? $this->data['personalizados']['informaciontributaria2'] : '',
                'notas_interes'                         => array_key_exists('notasinteres', $this->data['personalizados']) ? $this->data['personalizados']['notasinteres'] : '',
                'imprimir_factura'                      => array_key_exists('imprimirfactura', $this->data['personalizados']) ? $this->data['personalizados']['imprimirfactura'] : '',
                'ip_impresora'                          => array_key_exists('ip impresora', $this->data['personalizados']) ? $this->data['personalizados']['ip impresora'] : '',
                'archivoprocesado'                      => $archivoReal,
                'cdo_informacion_adicional_excluir_xml' => ['emisor', 'receptor', 'correos_receptor', 'imprimir_factura', 'ip_impresora', 'archivoprocesado']
            ],
            'cdo_medios_pago' => [[
                'fpa_codigo'            => (isset($this->data['datos fiscales receptor']['forma de pago'])) ? $this->data['datos fiscales receptor']['forma de pago'] : '',
                'mpa_codigo'            => (isset($this->data['datos fiscales receptor']['medio de pago'])) ? $this->data['datos fiscales receptor']['medio de pago'] : ''
            ]],
            'cdo_redondeo'                      => (isset($this->data['datos fiscales receptor']['valor redondeo cop'])) ? $this->data['datos fiscales receptor']['valor redondeo cop'] : '',
            'cdo_redondeo_moneda_extranjera'    => (isset($this->data['datos fiscales receptor']['valor redondeo usd'])) ? $this->data['datos fiscales receptor']['valor redondeo usd'] : '',
            'cdo_retenciones'                   => (isset($this->data['datos fiscales receptor']['autoretenciones cop'])) ? $this->getDBNum($this->normalizeNum($this->data['datos fiscales receptor']['autoretenciones cop'])) : '',
            'cdo_retenciones_moneda_extranjera' => (isset($this->data['datos fiscales receptor']['autoretenciones usd'])) ? $this->getDBNum($this->normalizeNum($this->data['datos fiscales receptor']['autoretenciones usd'])) : ''
        ];

        if(isset($this->data['datos fiscales receptor']['forma de pago']) && $this->data['datos fiscales receptor']['forma de pago'] == '2') {
            $dataJson['cdo_medios_pago'][0]['men_fecha_vencimiento'] = $this->data['datos del encabezado']['fechavence'];
        }

        // Ítems.
        $dataJson['cdo_informacion_adicional']['items'] = [];
        foreach ($this->data['datos fiscales receptor']['items']['descripcionproducto'] as $key => $value) {
            $value = $value[0];
            $tipo = null;
            if (array_key_exists('descripcion', $value)) {
                $k = explode('-', $value['descripcion']);
                if (count($k) === 1) {
                    $tipo = 'DDP';
                    $value['descripcion_producto'] = 'Impuestos pagados en origen';
                }
                else {
                    $tipo = strtolower($k[1]);
                    if ($k[0] === '1')
                        $value['descripcion_producto'] = 'Envios Internacionales';
                    else
                        $value['descripcion_producto'] = 'Envios Nacionales';
                    $value['descripcion'] = $k[0] . '-' . ucfirst($k[1]);
                }
            } else {
                $k = explode('-', $key);
                $value['descripcion'] = ucwords($key);
                if (count($k) === 1) {
                    $tipo = 'DDP';
                    $value['descripcion_producto'] = 'Impuestos pagados en origen';
                }
                else {
                    $tipo = strtolower($k[1]);
                    if ($k[0] === '1')
                        $value['descripcion_producto'] = 'Envios Internacionales';
                    else
                        $value['descripcion_producto'] = 'Envios Nacionales';
                    $value['descripcion'] = $k[0] . '-' . ucfirst($k[1]);
                }
            }

            $totalCOP = 0;
            $totalUSD = 0;
            $cantidad = 0;

            if (array_key_exists('cantidad', $value))
                $cantidad = intval($this->normalizeNum($value['cantidad']));
            if ($cantidad > 0) {

                if (array_key_exists('totalvlr_cop', $value))
                    $totalCOP = $this->getDBNum($value['totalvlr_cop']);
                if (array_key_exists('totalvlr_usd', $value))
                    $totalUSD = $this->getDBNum($value['totalvlr_usd']);

                $detalleItem = [
                    'tipo'                    => $tipo,
                    'descripcion_producto'    => $value['descripcion_producto'],
                    'descripcion'             => $value['descripcion'],
                    'cantidad'                => $cantidad,
                    'valor_moneda_nacional'   => $totalCOP,
                    'valor_moneda_extranjera' => $totalUSD
                ];
                array_push($dataJson['cdo_informacion_adicional']['items'], $detalleItem);
            }
        }
        
        //Fecha vencimiento
        $dataJson['cdo_vencimiento'] = $this->data['datos del encabezado']['fechavence'];

        $dataJson['retenciones']              = $retenciones;
        $dataJson['tributos']                 = $tributos;
        $dataJson['cdo_comprimido']           = 'SI';
        $dataJson['cdo_documento_comprimido'] = base64_encode(gzdeflate(json_encode($items), 9));

        // Cargos
        $dataJson['cdo_informacion_adicional']['cargos'] = [];

        $cargos = [];
        if (array_key_exists('descripcioncargo', $this->data['datos fiscales receptor']['items']))
            $cargos = $this->data['datos fiscales receptor']['items']['descripcioncargo'];

        foreach ($cargos as $key => $value) {
            foreach ($value as $it) {
                if ($key == 'descuento') {
                    continue;
                }

                $totalCOP = 0;
                $totalUSD = 0;
                if (array_key_exists('totalvlr_cop', $it))
                    $totalCOP = $this->getDBNum($it['totalvlr_cop']);
                if (array_key_exists('totalvlr_usd', $it))
                    $totalUSD = $this->getDBNum($it['totalvlr_usd']);

                if ($totalCOP > 0 || $totalUSD > 0) {

                    $item = [
                        'descripcion' => $key,
                        'porcentaje'  => '100',
                        'valor_moneda_nacional' => [
                            'base'  => $totalCOP,
                            'valor' => $totalCOP,
                        ],
                        'valor_moneda_extranjera' => [
                            'base'  => $totalUSD,
                            'valor' => $totalUSD,
                        ],
                    ];

                    switch ($key) {
                        case 'recargo por combustible':
                            $tipo = 'recargo-combustible';
                            break;
                        case 'proteccion del envio':
                            $tipo = 'proteccion-envio';
                            break;
                        case 'embalaje':
                            $tipo = 'embalaje';
                            break;
                        case 'area remota':
                            $tipo = 'area-remota';
                            break;
                        case 'pieza sobredimensionada':
                            $tipo = 'pieza-sobredimensionada';
                            break;
                        case 'pieza con sobrepeso':
                            $tipo = 'pieza-sobrepeso';
                            break;
                        case 'otros':
                            $tipo = 'otros';
                            break;
                        default:
                            $tipo = '';
                    }

                    $cargo = [
                        'tipo'                    => $tipo,
                        'valor_moneda_extranjera' => $totalUSD,
                        'valor_moneda_nacional'   => $totalCOP,
                    ];

                    array_push($dataJson['cdo_informacion_adicional']['cargos'], $cargo);
                }
            }
        }

        // Descuentos.
        $descuentos = [];
        if (array_key_exists('descripcioncargo', $this->data['datos fiscales receptor']['items']))
            $descuentos = $this->data['datos fiscales receptor']['items']['descripcioncargo']['descuento'];
        $dataJson['cdo_informacion_adicional']['descuentos'] = [];

        foreach ($descuentos as $value) {
            $valorCOP = 0;
            $valorUSD = 0;
            if (array_key_exists('totalvlr_cop', $value))
                $valorCOP = $this->getDBNum($value['totalvlr_cop']);
            if (array_key_exists('totalvlr_usd', $value))
                $valorUSD = $this->getDBNum($value['totalvlr_usd']);

            if ($valorCOP !== 0 || $valorUSD !== 0) {
                $item = [
                    'descripcion' => 'descuento',
                    'porcentaje'  => '100',
                    'valor_moneda_nacional' => [
                        'base'  => $valorCOP,
                        'valor' => $valorCOP,
                    ],
                    'valor_moneda_extranjera' => [
                        'base'  => $valorUSD,
                        'valor' => $valorUSD,
                    ],
                ];

                $descuento = [
                    'tipo'                    => 'descuento',
                    'valor_moneda_extranjera' => strval($valorUSD),
                    'valor_moneda_nacional'   => strval($valorCOP),
                ];

                array_push($dataJson['cdo_informacion_adicional']['descuentos'], $descuento);
            }
        }

        $dataJson['cdo_informacion_adicional']['valor_total_usd'] = $this->getDBNum($this->data['totales']['totalvr.usd']);
        $dataJson['cdo_informacion_adicional']['valor_total_cop'] = $this->getDBNum($this->data['totales']['totalvr.cop']);
        $dataJson['cdo_informacion_adicional']['base_iva']        = $this->getDBNum($this->data['totales']['basedeiva']);
        $dataJson['cdo_informacion_adicional']['iva']             = $this->getDBNum($this->data['totales']['iva']);
        $dataJson['cdo_informacion_adicional']['valor_total']     = $this->getDBNum($this->data['totales']['valortotal']);

        $impuestosUSD = $this->normalizeNum($this->data['totales']['iva']);
        $trm = $this->normalizeNum($this->data['datos fiscales receptor']['tasacambio']);
        $this->data['datos fiscales receptor']['tasacambio'] = (float)$trm;
        $impuestosUSD = $trm > 0 ? (float)$impuestosUSD / (float)$trm : 0;
        $dataJson['cdo_impuestos_moneda_extranjera'] = $this->getDBNum((string)$impuestosUSD);

        // Total moneda extranjera.
        $totalUSD = $this->normalizeNum($this->data['totales']['valortotal']);
        $totalUSD = $trm > 0 ? (float) $totalUSD / (float) $trm : 0;
        $dataJson['cdo_total_moneda_extranjera'] = $this->getDBNum($totalUSD);

        // Específicos de facturas.
        if ($tipodoc == 'FC') {
            $dataJson['cdo_informacion_adicional']['linea2_dian'] = $this->data['datos del encabezado']['linea2dian'];
        }

        // Específicos de notas de créditos.
        if ($tipodoc == 'NC') {
            $referencia = $this->data['referencia'][0];

            // Si la nota credito es con referencia a factura se busca el cufe, 
            // de lo contrario no se envia la seccion
            if ($receptor['tipo_operacion'] != "22") {
                $tipoDocRef = null;
                if (array_key_exists('tipo de documento', $referencia) && $referencia['tipo de documento'] == '01') {
                    $tipoDocRef   = 'FC';
                } elseif (array_key_exists('tipo de documento', $referencia) && $referencia['tipo de documento'] == '07') {
                    $tipoDocRef   = 'NC';
                } elseif (array_key_exists('tipo de documento', $referencia) && $referencia['tipo de documento'] == '08') {
                    $tipoDocRef   = 'ND';
                }

                // Cufe factura electrónica.
                $factura = EtlCabeceraDocumentoDaop::select(['cdo_id','cdo_cufe'])
                    ->where([
                        ['rfa_prefijo', '=', array_key_exists('serie', $referencia) ? $referencia['serie'] : ''],
                        ['cdo_consecutivo', '=', array_key_exists('no doc referencia', $referencia) ? $referencia['no doc referencia'] : ''],
                    ])->first();

                if(!$factura)
                    $factura = EtlFatDocumentoDaop::select(['cdo_id','cdo_cufe'])
                        ->where([
                            ['rfa_prefijo', '=', array_key_exists('serie', $referencia) ? $referencia['serie'] : ''],
                            ['cdo_consecutivo', '=', array_key_exists('no doc referencia', $referencia) ? $referencia['no doc referencia'] : ''],
                        ])->first();

                if ($factura && $factura->cdo_cufe) {
                    $cufeFactura = $factura->cdo_cufe;
                } else if (array_key_exists('pid:', $this->data['personalizados'])){
                    $cufeFactura = $this->data['personalizados']['pid:'];
                } else if (array_key_exists('pid', $this->data['personalizados'])){
                    $cufeFactura = $this->data['personalizados']['pid'];
                } else {
                    $cufeFactura = "";
                }
                
                $dataJson['cdo_documento_referencia'][] = [
                    'clasificacion'    => $tipoDocRef,
                    'prefijo'          => array_key_exists('serie', $referencia) ? $referencia['serie'] : null,
                    'consecutivo'      => array_key_exists('no doc referencia', $referencia) ? $referencia['no doc referencia'] : null,
                    'cufe'             => $cufeFactura,
                    'fecha_emision'    => array_key_exists('fecha referencia', $referencia) ? $referencia['fecha referencia'] : null
                ];
            }
            
            // Conceptos de corrección
            $codigoConceptoCorreccion = array_key_exists('codigo referencia', $referencia) ? $referencia['codigo referencia'] : null;
            if($codigoConceptoCorreccion) {
                $conceptoCorreccion = ParametrosConceptoCorreccion::select(['cco_id', 'cco_codigo', 'cco_descripcion'])
                    ->where('cco_codigo', $codigoConceptoCorreccion)
                    ->where('cco_tipo', $tipodoc)
                    ->first();
                if($conceptoCorreccion) {
                    $dataJson['cdo_conceptos_correccion'][] = [
                        'cco_codigo'                 => $conceptoCorreccion->cco_codigo,
                        'cdo_observacion_correccion' => $conceptoCorreccion->cco_descripcion
                    ];
                }
            }

            $dataJson['cdo_informacion_adicional']['referencia'] = [
                'tipo_documento'    => array_key_exists('tipo de documento', $referencia) ? $referencia['tipo de documento'] : '',
                'serie'             => array_key_exists('serie', $referencia) ? $referencia['serie'] : '',
                'referencia'        => array_key_exists('no doc referencia', $referencia) ? $referencia['no doc referencia'] : '',
                'fecha'             => array_key_exists('fecha referencia', $referencia) ? $referencia['fecha referencia'] : '',
                'codigo'            => array_key_exists('codigo referencia', $referencia) ? $referencia['codigo referencia'] : '',
                'razon_referencia'  => array_key_exists('razon detallada referencia', $referencia) ? $referencia['razon detallada referencia'] : '',
            ];
        }

        return [
            'tipodoc'    => $tipodoc,
            'statusCode' => 200,
            'json'       => $dataJson
        ];
    }
}
