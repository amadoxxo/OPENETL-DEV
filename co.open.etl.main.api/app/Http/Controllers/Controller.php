<?php

namespace App\Http\Controllers;

use JWTAuth;
use DateTime;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use App\Http\Models\User;
use App\Traits\MainTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Box\Spout\Common\Entity\Style\Color;
use openEtl\Main\Traits\PackageMainTrait;
use openEtl\Tenant\Traits\TenantDatabase;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use App\Http\Modulos\sistema\festivos\SistemaFestivo;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Modulos\sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\sistema\EtlProcesamientoJson\EtlProcesamientoJson;

class Controller extends BaseController {
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, PackageMainTrait;

    /**
     * Almacena los errores basados en la ausencia de campos en los requerimientos
     * @var array
     */
    public $errorsPetition = [];

    /**
     * Almacena los días festivos.
     *
     * @var array
     */
    private $festivos = [];

    public function __construct () {

    }

    /**
     * Ejecuta un chequeo en el objeto $request basado en una lista de items para  verificar si esan ausentes
     * @param Request $request
     * @param array $fields
     */
    protected function analizarPostRequest(Request $request, array $fields)
    {
        if ($this->errorsPetition == null)
            $this->errorsPetition = [];
        foreach ($fields as $field)
            if (!$request->has($field))
                array_push($this->errorsPetition, "'$field' no puede ser nulo.");
    }

    /**
     * Ejecuta un chequeo en el objeto $request basado en una lista de items para  verificar si esan ausentes
     * @param Request $request
     * @param array $fields
     */
    protected function analizarPostHeader(Request $request, array $fields)
    {
        if ($this->errorsPetition == null)
            $this->errorsPetition = [];
        foreach ($fields as $field)
            if (!$request->header($field))
                array_push($this->errorsPetition, "'$field' no puede ser nulo.");
    }

    /**
     * Ejecuta un chequeo en el objeto $request basado en una lista de items para  verificar si esan ausentes en el cuerpo
     * json de la solicitud
     * @param Request $request
     * @param array $fields
     */
    protected function analizarJsonRequest(Request $request, array $fields)
    {
        if ($this->errorsPetition == null)
            $this->errorsPetition = [];
        foreach ($fields as $field)
            if (is_null($request->json($field))) {
                array_push($this->errorsPetition, "'$field' no puede ser nulo.");
            }
    }

    /**
     * Ejecuta un chequeo en el objeto $request basado en una lista de items para  verificar si esan ausentes en el cuerpo
     * json de la solicitud
     * @param Request $request
     * @param array $fields
     */
    protected function analizarJsonRequestAngular(Request $request, array $fields)
    {
        if ($this->errorsPetition == null)
            $this->errorsPetition = [];
        foreach ($fields as $field)
            if (is_null($request->json($field['id']))) {
                array_push($this->errorsPetition, $field['label'] . " no puede ser nulo.");
            }
    }

    /**
     * Determina si ha registrado errores en la peticion
     * @param Request $request
     * @param array $fields
     * @return bool
     */
    protected function postHasError(Request $request, array $fields)
    {
        $this->analizarPostRequest($request, $fields);
        return count($this->errorsPetition) > 0;
    }

    /**
     * Determina si ha registrado errores en la peticion
     * @param Request $request
     * @param array $fields
     * @return bool
     */
    protected function postHasHeaderError(Request $request, array $fields)
    {
        $this->analizarPostHeader($request, $fields);
        return count($this->errorsPetition) > 0;
    }

    /**
     * Determina si ha registrado errores en la peticion
     * @param Request $request
     * @param array $fields
     * @return bool
     */
    protected function postHasJsonError(Request $request, array $fields)
    {
        $this->analizarJsonRequest($request, $fields);
        return count($this->errorsPetition) > 0;
    }

    /**
     * Determina si ha registrado errores en la peticion
     * @param Request $request
     * @param array $fields
     * @return bool
     */
    protected function postHasJsonErrorAngular(Request $request, array $fields)
    {
        $this->analizarJsonRequestAngular($request, $fields);
        return count($this->errorsPetition) > 0;
    }

    /**
     * Retorna los campos faltantes en el requerimiento http que se ha recibido
     * @return Response
     */
    protected function getErrorResponseFieldsMissing()
    {
        return $this->getErrorResponse($this->errorsPetition);
    }

    /**
     * @param array $errors
     * @return Response
     */
    protected function getErrorResponse(array $errors)
    {
        return response()->json(
            [
                'message' => 'Error en la petición.',
                'errors'  => $errors,
            ], 422
        );
    }

    /**
     * Retorna una respuesta de error generica
     * @param $msg
     * @param int $code
     * @return Response
     */
    protected function getErrorResponseByCode($msg, int $code = 500)
    {
        return response()->json(
            [
                'message' => 'Error en la petición.',
                'errors'  => is_array($msg) ? $msg : [$msg],
            ], $code
        );
    }

    /**
     * Elimina los caracteres especiales que una cadena pueda contener y puede que la trunque a una longitud maxima
     * @param string $cad
     * @param int $maxLenght Máxima longitud de la cadena a retornar
     * @return mixed|string
     */
    public function sanitizarStrings($cad, $maxLenght = -1) {
        if ($cad === null)
            return '';

        // Variables para reemplazar caracteres especiales
        $cBuscar = array(chr(13),chr(10),chr(27),chr(9));
        foreach ($cBuscar as $comodin)
            $cad = str_replace($comodin, ' ', $cad);
        if ($maxLenght != -1)
            $cad = substr($cad, 0, $maxLenght);
        return $cad;
    }

    /**
     * Procesa un string para solo retornar el email que esta pueda contener
     * @param string $text
     * @return string
     */
    function soloEmail($text) {
        if ($text === null)
            return '';
        preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $text, $matches);
        if (count($matches) && array_key_exists(0, $matches) && array_key_exists(0, $matches[0]))
            return $matches[0][0];
        return '';
    }

    /**
     * Procesa un string para solo retornar los emails que esta pueda contener
     * @param string $text
     * @return string
     */
    public function soloEmails($text) {
        if ($text === null)
            return '';
        preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $text, $matches);
        if (count($matches) && array_key_exists(0, $matches) && count($matches[0]) > 0)
            return implode(',', $matches[0]);
        return '';
    }

    /**
     * remueve los espacios en blanco o caracteres especiales que una cadena puede contener
     * @param string $text
     * @return string
     */
    public function removerEspacios($text) {
        if ($text === null)
            return '';
        $text = $this->sanitizarStrings($text);
        return str_replace(' ', '', $text);
    }

    /**
     * Valida si una direccion de email es valida
     * @param $email
     * @return bool
     */
    public function validarEmail($email) {
        $regex = '/^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,3})$/';
        if (preg_match($regex, $email))
            return true;
        return false;
    }

    /**
     * Reemplaza todos los acentos por sus equivalentes sin ellos
     *
     * @param $string string la cadena a sanear
     * @return string
     */
    function sanear_string($string) {
        $string = trim($string);
        $string = str_replace(['á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'], ['a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'], $string);
        $string = str_replace(['é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'], ['e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'], $string);
        $string = str_replace(['í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'], ['i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'], $string);
        $string = str_replace(['ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'], ['o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'], $string);
        $string = str_replace(['ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'], ['u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'], $string);
        $string = str_replace(['ñ', 'Ñ', 'ç', 'Ç'], ['n', 'N', 'c', 'C'], $string);
        $string = str_replace(['%', '(', ')', '/', '-', '+', ' '], ['porcentaje', '', '', '_', '_', '_', '_'], $string);
        return $string;
    }

    /**
     * Obtiene la data de un archivo de Excel.
     *
     * @param Request $request Parámetros de la petición
     * @param string $archivo Archivo cargado
     * @param bool $storage Indica si se obtiene del storage
     * @param string $filepath Ruta del archivo
     * @param bool $generarCsv Indica si se procesa el archivo como CSV
     * @return array|false|null
     * @throws \Exception
     */
    public function parserExcel(Request $request, string $archivo = 'archivo', bool $storage = true, string $filepath = '', bool $generarCsv = false) {
        $filename = $this->sanear_string($request->file('archivo')->getClientOriginalName());
        MainTrait::setFilesystemsInfo();
        $uploaded = Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))
            ->put($filename, file_get_contents($request->file('archivo')->getPathName()));

        if ($uploaded) {
            $storagePath = Storage::disk('etl')->getDriver()->getAdapter()->getPathPrefix();
            $path = $storagePath . $filename;
        };

        // $pathExcel = '';
        // if ($filepath == '') {
        //     $excel = Uuid::uuid4()->toString() . '.' . $request->file($archivo)->getClientOriginalExtension();
        //     $pathExcel = $storagePath . $excel;

        //     // Guarda el archivo en disco
        //     if ($storage)
        //         $request->file($archivo)->storeAs('', $excel, 'local');
        // }
        // else
        //     $pathExcel = $filepath;

        $registros = [];
        if ($generarCsv) {
            // Construyendo el csv
            $salida     = [];
            $archivoCsv = $storagePath . Uuid::uuid4()->toString() . '.csv';
            exec("ssconvert $path $archivoCsv", $salida);

            if (($handle = fopen($archivoCsv, "r")) !== false) {
                while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                    $registros[] = $data;
                }
                fclose($handle);
            }
        } else {
            $reader = ReaderEntityFactory::createXLSXReader(); // for XLSX files
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $registros[] = $row->toArray();
                }
            }
            $reader->close();
        }

        $header = $registros[0];
        $keys = [];
        $data = [];
        foreach ($header as $k) {
            $keys[] = strtolower($this->sanear_string(str_replace(' ', '_', $k)));
        }

        $N = count($keys);

        for ($i = 1; $i < count($registros); $i++) {
            $row = $registros[$i];
            $newrow = [];
            for ($j = 0; $j < $N; $j++)
                if (array_key_exists($j, $row))
                    $newrow[$keys[$j]] = $row[$j];
            $data[] = $newrow;
        }

        // if ($filepath === '')
        //     unlink($request->file($archivo)->getRealPath());
        // unlink($tempfile);

        //Eliminar Archivo excel
        unlink($path);

        return $data;
    }

    /**
    * Llamada al Trail de TenantDatabase para conectarnos a cualquer base de datos
    * @param $user User que esta logeado
    */
    public function renovarConexion($user) {
        DB::disconnect('conexion01');
        TenantDatabase::setTenantConnection('conexion01',
        $user->getBaseDatos->bdd_host, $user->getBaseDatos->bdd_nombre,
        $user->getBaseDatos->bdd_usuario, $user->getBaseDatos->bdd_password);
        DB::reconnect('conexion01');
    }
    /**
     * Genera un archivo de excel.
     *
     * @param array $header Titulos de las columnas del Excel
     * @param array $input Items a ser insertados como filas en el Excel
     * @param string $nombre Nombre de la hoja y del archivo de Excel
     * @return string $tempfilexlsx Ruta del archivo temporal de Excel generado
     */
    public function toExcel($header, $input, $nombre = 'Hoja 1') {
        set_time_limit(0);
        ini_set('memory_limit','2048M');

        MainTrait::setFilesystemsInfo();
        $cadena = str_random(12);
        $storagePath  = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();
        $tempfilexlsx = $storagePath . $cadena . '.xlsx';

        $defaultStyle = (new StyleBuilder())
            ->setFontName('Calibri')
            ->setFontSize(12)
            ->build();

        $headerStyle = (new StyleBuilder())
            ->setFontName('Calibri')
            ->setFontBold()
            ->setFontSize(14)
            ->setBackgroundColor(Color::rgb(201, 218, 248))
            ->build();

        $writer  = WriterEntityFactory::createXLSXWriter();
        $filas[] = WriterEntityFactory::createRowFromArray($header, $headerStyle);

        $writer->openToFile($tempfilexlsx);

        if (!empty($input)) {
            foreach ($input as $fila) {
                $filas[] = WriterEntityFactory::createRowFromArray($fila, $defaultStyle);
            }
        }

        $writer->addRows($filas);

        if (strlen($nombre) > 30)
            $nombre = substr($nombre, 0, 30);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($nombre);

        $writer->close();

        return $tempfilexlsx;
    }

    /**
     * Registra un nuevo agendamiento
     * @param string $proceso         - Proceso en base al cual se ejecutara el agendamiento
     * @param int $cantidadDocumentos - Cantidad de documentos que se agendaran
     * @param $user
     * @return AdoAgendamiento
     */
    public function crearAgendamiento(string $proceso, int $cantidadDocumentos = 1, $user = null)  {
        $usuario_auth = ($user === null) ? JWTAuth::parseToken()->authenticate() : $user;
        $nuevoAgendamiento                          = new AdoAgendamiento();
        $nuevoAgendamiento->age_proceso             = $proceso;
        $nuevoAgendamiento->age_cantidad            = $cantidadDocumentos;
        $nuevoAgendamiento->usu_id                  = $usuario_auth->usu_id;
        $nuevoAgendamiento->usuario_creacion        = $usuario_auth->usu_id;
        $nuevoAgendamiento->estado                  = 'ACTIVO';
        $nuevoAgendamiento->save();
        return $nuevoAgendamiento;
    }

    /**
     * Registra un nuevo proc
     * @param string $pjj_tipo
     * @param array $data
     * @param AdoAgendamiento $agendamiento
     * @param string $age_proceso
     * @param int $total
     * @param $user
     * @return EtlProcesamientoJson
     */
    public function crearProcesamientoJson(string $pjj_tipo, array  $data, AdoAgendamiento $agendamiento = null,
                                           string $age_proceso = '', int $total = 1, $user = null) {
        $usuario_auth = ($user === null) ? JWTAuth::parseToken()->authenticate() : $user;

        // Si el agendamiento no se ha creado, se construye
        if ($agendamiento === null)
            $agendamiento = $this->crearAgendamiento($age_proceso, $total);

        $procesamientoJson = new EtlProcesamientoJson();
        $procesamientoJson->pjj_tipo = $pjj_tipo;
        $procesamientoJson->pjj_json = json_encode($data);
        $procesamientoJson->pjj_procesado = 'NO';
        $procesamientoJson->pjj_errores = null;
        $procesamientoJson->age_id = $agendamiento->age_id;
        $procesamientoJson->age_estado_proceso_json = null;
        $procesamientoJson->estado = 'ACTIVO';
        $procesamientoJson->usuario_creacion = $usuario_auth->usu_id;
        $procesamientoJson->save();
        return $procesamientoJson;
    }

    /**
     * Obtiene al ruta para almacenar un nuevo archivo en el sistema
     * @param $disk
     * @param $date
     * @param $__uuid
     * @return array
     * @throws \Exception
     */
    public function getDataForStorage($disk, $date = null, $__uuid = null) {
        // Ruta de almacenamiento por defecto storage/app (Laravel filesystem local por defecto).
        $path = '';
        if ($date === null)
            $path = (new Carbon('now'))->format("Y/m/d/H/i/");
        else{
            $fh = Carbon::parse($date);
            $path = $fh->year . '/' . $fh->format('m') . '/' . $fh->format('d') . '/' . $fh->format('H') . '/' . $fh->format('i') . '/';
        }
        $filename = $__uuid === null ? Uuid::uuid4()->toString() : $__uuid;
        $fullname = $path . $filename;
        return ['path' => $path,
            'filename' => $filename,
            'fullname' => $fullname
        ];
    }

    /**
     * Verifica que los campos existentes en un arreglo coincidan con los solicitados.
     *
     * @param array $arreglo
     * @param array $camposRequeridos
     * @param int $linea
     * @return array
     */
    public function checkFields($arreglo, $camposRequeridos, $linea = -1){
        $arrFaltantes = [];
        foreach ($camposRequeridos as $campo) {
            if (array_key_exists($campo, $arreglo)){
                if(trim($arreglo[$campo]) === ''){
                    if ($linea === -1)
                        $error = 'La Columna ['.$campo.'] es requerida para continuar. ' . ($linea != -1 ? (' (Fila ' . ($linea + 2) .  ')'): '');
                    else
                        $error = 'La Columna ['.$campo.'] es requerida para continuar. ' . ($linea != -1 ? (' (Fila ' . ($linea + 2) .  ')'): '');
                    array_push($arrFaltantes, $error);
                }
            } else {
                $error = 'La Columna ['.$campo.'] es requerida para continuar. ' . ($linea != -1 ? (' (Fila ' . ($linea + 2) .  ')'): '');
                array_push($arrFaltantes, $error);
            }
        }

        return $arrFaltantes;
    }

    /**
     * Toma los errores generados y los mezcla en un sólo arreglo para dar respuesta al usuario
     *
     * @param array $arrErrores
     * @param array $objValidator
     * @param int $fila
     * @return array
     */
    public function adicionarError($arrErrores, $objValidator, $fila = -1){
        foreach($objValidator as $error){
            array_push($arrErrores, $error . ($fila != -1 ? (' (Fila ' . ($fila + 2) .  ')'): ''));
        }

        return $arrErrores;
    }

    /**
     * Corrobora si los valores de un arreglo están vacíos
     *
     * @param array $arrValor
     * @return boolean
     */
    public function revisarArregloVacio($arrValor){
        $boolVerificador = false;
        foreach($arrValor as $strLlave => $strValor){
            if(strlen(trim($strValor)) > 0){
                $boolVerificador = true;
            }
        }

        return $boolVerificador;
    }

    /**
     * Consulta los días festivos en la tabla de festivos y los almacena en la variable festivos
     *
     * @return void
     * @throws \Exception
     */
    public function obtenerFestivos()
    {
        // Se obtienen los días festivos almacenados en el sistema
        $festivos = SistemaFestivo::where('estado', 'ACTIVO')
            ->get()
            ->pluck('fes_fecha');

        if ($festivos) {
            foreach ($festivos as $festivo) {
                array_push($this->festivos, new DateTime($festivo));
            }
        }
    }

    /**
     * Comprueba si la fecha dada corresponde a un día hábil de trabajo
     *
     * @param DateTime $fecha
     * @return bool
     */
    function esDiaHabil(DateTime $fecha)
    {
        // Comprueba si el día es Sábado o Domingo
        if ($fecha->format('N') > 5) {
            return false;
        }
        // Comprueba si el día es festivo
        foreach ($this->festivos as $festivo) {
            if ($festivo->format('Y-m-d') === $fecha->format('Y-m-d')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retorna el valor de una posicion en un array si esta existe, de lo contrario retorna vacio
     * @param array $array
     * @param string $key
     * @return mixed|string
     */
    function getValueFromArray(array $array, string $key) {
        return array_key_exists($key, $array) ? $array[$key] : '';
    }

    /**
     * Obtiene un token para efectuar una peticion a un microservicio.
     *
     * @return string
     */
    public function getToken() {
        $user = auth()->user();
        if ($user)
            return auth()->tokenById($user->usu_id);
        return '';
    }

    /**
     * Construye un cliente de Guzzle para consumir los microservicios
     *
     * @param string $URI
     * @return Client
     */
    private function getCliente(string $URI) {
        return new Client([
            'base_uri' => $URI,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Retorna un PDF
     *
     * @param $documento
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function peticionFirma($documento) {
        try {
            // Accede microservicio Firma para obtener la representación gráfica del documento
            $peticionFirma = $this->getCliente(env('FIRMA_API_URL'))->request(
                'POST',
                '/api/pdf-representacion-grafica-documento',
                [
                    'form_params' => [
                        'cdo_id' => $documento->cdo_id
                    ],
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getToken()
                    ]
                ]
            );
            return[
                /*
                 * En respuesta se accede al documento  respuesta->data->representacion_grafica_documento
                 */
                'respuesta' => json_decode((string)$peticionFirma->getBody()->getContents()),
                'error' => false
            ];
        }
        catch (\Exception $e) {
            $response = $e->getResponse();
            return[
                'respuesta' => $response->getBody()->getContents(),
                'error' => true
            ];
        }
    }

    /**
     * Convierte un numero a la nomenclatura de las columnas de excel
     *
     * @param $num
     * @return string
     */
    public function getNameFromNumber($num) {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }
}
