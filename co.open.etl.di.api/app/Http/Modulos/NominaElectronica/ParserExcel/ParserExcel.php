<?php
namespace App\Http\Modulos\NominaElectronica\ParserExcel;

use Validator;
use Ramsey\Uuid\Uuid;
use App\Traits\DiTrait;
use App\Http\Models\AdoAgendamiento;
use Illuminate\Support\Facades\Storage;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\NominaElectronica\ConstantsDataInput;
use App\Http\Modulos\NominaElectronica\Json\JsonNominaBuilder;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;

class ParserExcel {
    use DiTrait, PackageMainTrait;

    /**
     * Almacena información de las paramétricas requeridas en el proceso de Nómina Electrónica.
     *
     * @var array
     */
    private $parametricas = [];

    /**
     * Reglas de validación para Nomina / Novedad / Ajuste
     *
     * @var array
     */
    private $rulesNominaNovedadAjuste = [];

    /**
     * Reglas de validación para Eliminar
     *
     * @var array
     */
    private $rulesEliminar = [];

    /**
     * Errores de estructura para DevengadosDeducciones
     *
     * @var array
     */
    private $erroresEstructuraDevengadosDeducciones = [];

    /**
     * Array para contener la información de Devengados
     *
     * @var array
     */
    private $arrDevengados = [];

    /**
     * Array para contener la información de Deducciones
     *
     * @var array
     */
    private $arrDeducciones = [];

    /**
     * Lista de documentos que presentaron fallas en el procesamiento del excel - Validaciones.
     *
     * @var array
     */
    private $documentosConFallas = [];

    /**
     * Lista de documentos que en el procesamiento del excel pasaron las validaciones y pueden ser agendados para el proceso principal NDI.
     *
     * @var array
     */
    private $documentosParaAgendamiento = [];

    public function __construct() {
        set_time_limit(0);
        ini_set('memory_limit', ConstantsDataInput::MAX_MEMORY);

        $this->buildRules();
    }

    private function buildRules() {
        $this->rulesNominaNovedadAjuste = [
            'tipo_documento'              => 'required|string|max:5',
            'aplica_novedad'              => 'required_if:tipo_documento,102|string|max:2|in:SI,NO',
            'tipo_nota'                   => 'required_if:tipo_documento,103|string|max:5',
            'nit_empleador'               => 'required|string|max:20',
            'nit_trabajador'              => 'required|string|max:20',
            'prefijo'                     => 'nullable|string|max:10',
            'consecutivo'                 => 'required|numeric|min:1',
            'periodo_nomina'              => 'required|string|max:10',
            'fecha_emision'               => 'required|date_format:Y-m-d',
            'hora_emision'                => 'required|date_format:H:i:s',
            'fecha_inicio_liquidacion'    => 'nullable|date_format:Y-m-d',
            'fecha_fin_liquidacion'       => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio_liquidacion',
            'tiempo_laborado'             => 'nullable|numeric|min:1',
            'cod_pais_generacion'         => 'required|string|max:10',
            'cod_departamento_generacion' => 'required|string|max:10',
            'cod_municipio_generacion'    => 'required|string|max:10',
            'cod_forma_de_pago'           => 'required|string|max:10',
            'cod_medio_de_pago'           => 'required|string|max:10',
            'fechas_pago'                 => 'required|string',
            'notas'                       => 'required|string',
            'moneda'                      => 'required|string|max:10',
            'trm'                         => 'required_unless:moneda,COP|numeric|regex:/^[0-9]{1,13}(\.[0-9]{0,6})?$/',
            'prefijo_predecesor'          => 'nullable|string|max:10',
            'consecutivo_predecesor'      => 'nullable|numeric|min:1',
            'fecha_emision_predecesor'    => 'nullable|date|date_format:Y-m-d',
            'cune_predecesor'             => 'nullable|string'
        ];

        $this->rulesEliminar = [
            'tipo_documento'              => 'required|string|max:5',
            'tipo_nota'                   => 'required_if:tipo_documento,103|string|max:5',
            'nit_empleador'               => 'required|string|max:20',
            'nit_trabajador'              => 'required|string|max:20',
            'prefijo'                     => 'nullable|string|max:10',
            'consecutivo'                 => 'required|numeric|min:1',
            'fecha_emision'               => 'required|date_format:Y-m-d',
            'hora_emision'                => 'required|date_format:H:i:s',
            'cod_pais_generacion'         => 'required|string|max:10',
            'cod_departamento_generacion' => 'required|string|max:10',
            'cod_municipio_generacion'    => 'required|string|max:10',
            'notas'                       => 'required|string',
            'prefijo_predecesor'          => 'required|string|max:10',
            'consecutivo_predecesor'      => 'required|numeric|min:1',
            'fecha_emision_predecesor'    => 'required|date|date_format:Y-m-d',
            'cune_predecesor'             => 'required|string'
        ];
    }

    /**
     * Procesa un archivo de Excel generando y registrando el objeto Json que será procesado mediante NDI.
     *
     * @param string $archivo Ruta al archivo de Excel
     * @param string $tipo Tipo de cargue de archivo de Excel (NOMINA/NOVEDAD/AJUSTE - ELIMINAR)
     * @param int $emp_id ID del empleador asociado al proceso de cargue
     * @param string $nombreRealArchivo Nombre real del archivo de Excel en procesamiento
     * @return array Array con información de procesamiento y errores
     */
    public function procesarArchivo(string $archivo, string $tipo, int $emp_id, string $nombreRealArchivo = '') {
        $this->archivoExcel = $nombreRealArchivo;
        $this->tipo         = $tipo;

        $this->parserExcel($archivo, $emp_id); 
        $response = $this->validarColumnas();
        $errores  = [];
        $message  = '';

        if (!$response['error']) {
            $message = $this->procesador($emp_id);
            foreach($this->documentosConFallas as $doc)
                if(is_array($doc))
                    foreach($doc as $error)
                        $errores[] = $error;
                else
                    $errores[] = $doc;
        } else {
            $errores = $response['errores'];
            $message = $response['message'];
        }

        return [
            'error'   => !empty($errores) ? true : false,
            'errores' => $errores,
            'message' => $message
        ];
    }

    /**
     * Extrae la data de un archivo de Excel.
     *
     * @param string $archivo Archivo de Excel cargado
     * @param int $emp_id ID del empleador seleccionado para el proceso de cargue
     * @return void
     */
    public function parserExcel($archivo, $emp_id) {
        self::setFilesystemsInfo();
        $storagePath = Storage::disk(config('variables_sistema.ETL_LOCAL_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
        $archivoCsv  = $storagePath . Uuid::uuid4()->toString() . '.csv';
        
        // Construyendo el csv
        $salida = [];
        exec("ssconvert $archivo $archivoCsv", $salida);

        $registros = [];
        if (($handle = fopen($archivoCsv, "r")) !== false) {
            while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                $registros[] = $data;
            }
            fclose($handle);
        }
        $header     = $registros[0];
        $this->keys = [];
        $this->data = [];
        
        if($this->tipo == ConstantsDataInput::EXCEL_NOMINA)
            $this->validarExtraerDevengadosDeducciones($registros);

        // Sanitiza y convierte a snake_case todos los encabezados
        foreach($header as $i => $k) {
            $key                    = trim(strtolower($this->sanear_string(str_replace(' ', '_', $k))));
            $this->keys[]           = $key;
            $this->nombresReales[] = $k;
        }

        // Recorre la información de cabecera para estandarizar las columnas relacionadas con datos del tipo fecha y dejar todos los registros en el array 'data'
        $N = count($this->keys);
        for ($i = 1; $i < count($registros); $i++) {
            $row = $registros[$i];
            $newrow = [];
            for ($j = 0; $j < $N; $j++) {
                $value = $row[$j];
                if (in_array($this->keys[$j], ConstantsDataInput::FIX_FECHAS_NOMINA) && !empty($value)) {
                    if($this->keys[$j] != 'fechas_pago')
                        $value = str_replace('/', '-', $value);
                    else {
                        $fechas = [];
                        foreach(explode(',', $value) as $fecha)
                            $fechas[] = str_replace('/', '-', trim($fecha));

                        $value = implode(',', $fechas);
                    }
                }
                $newrow[$this->keys[$j]] = $value;
            }
            $this->data[] = $newrow;
        }
        unlink($archivo);
        unlink($archivoCsv);

        if($this->tipo == ConstantsDataInput::EXCEL_NOMINA)
            $this->validarExistenciaInfoDevengadosDeducciones();
    }

    /**
     * Valida la composición de las secciones de devengados y deducciones y adicionalmente extrae hacia arrays las secciones correspondientes.
     *
     * @param array $registros Array que contiene toda la información contenida en el Excel cargado
     * @return void
     */
    private function validarExtraerDevengadosDeducciones(&$registros) {
        // Columnas de las secciones de Devengados/Deducciones que llegan en el Excel
        $columnasDevengadosDeducciones    = [];

        // El array de registros es separado entre las secciones que corresponden al documento como tal y las secciones que corresponden a sector salud
        $filaDevengados     = array_search('DEVENGADOS', array_column($registros, 0));
        $columnasDevengados = [];
        if($filaDevengados)
            $columnasDevengados = array_filter($registros[$filaDevengados + 1], function($value) { return !is_null($value) && $value !== ''; });

        $columnasDevengadosDeducciones[$registros[$filaDevengados][0]] = $columnasDevengados;
        
        $filaDeducciones     = array_search('DEDUCCIONES', array_column($registros, 0));
        $columnasDeducciones = [];
        if($filaDeducciones)
            $columnasDeducciones = array_filter($registros[$filaDeducciones + 1], function($value) { return !is_null($value) && $value !== ''; });

        $columnasDevengadosDeducciones[$registros[$filaDeducciones][0]] = $columnasDeducciones;
        
        // Compara la estructura de columnas de las secciones de Devengados/Deducciones frente al estandar de columnas existente
        $estandarColumnasDevengadosDeducciones = ConstantsDataInput::SECCIONES_COLUMNAS_DEVENGADOS_DEDUCCIONES;
        foreach ($estandarColumnasDevengadosDeducciones as $seccion => $columnas) {
            if(!array_key_exists($seccion, $columnasDevengadosDeducciones))
                $this->erroresEstructuraDevengadosDeducciones[] = "Falta el título de la sección [" . $seccion . "] o no fue posible identificar la sección";
            else {
                if(array_diff($estandarColumnasDevengadosDeducciones[$seccion], $columnasDevengadosDeducciones[$seccion]) || array_diff($columnasDevengadosDeducciones[$seccion], $estandarColumnasDevengadosDeducciones[$seccion]))
                    $this->erroresEstructuraDevengadosDeducciones[] = "La estructura de columnas de la sección [" . $seccion . "] no es la correcta";
            }
        }

        $arrDeducciones = [];
        $arrDevengados  = [];
        if(empty($this->erroresEstructuraDevengadosDeducciones)) {
            $arrDeducciones = array_splice($registros, $filaDeducciones); // Extrae al array solamente lo correspondiente a Deducciones
            $arrDevengados  = array_splice($registros, $filaDevengados); // Extrae al array solamente lo correspondiente a Devengados
            array_splice($registros, $filaDeducciones); // Del array $registros resultante de las operaciones anteriores, se excluye la fila correspondiente al título de sección Devengados

            // Elimina filas vacias de los arrays resultantes y reorganiza la información en llaves tipo => valor
            $arrDeducciones = $this->reorganizaRegistrosDevengadosDeducciones(array_filter($arrDeducciones, function($value) { return !is_null($value[0]) && $value[0] !==''; }));
            $arrDevengados  = $this->reorganizaRegistrosDevengadosDeducciones(array_filter($arrDevengados, function($value) { return !is_null($value[0]) && $value[0] !==''; }));
            $registros            = array_filter($registros, function($value) { return !is_null($value[0]) && $value[0] !==''; });
        }

        // Agrupa por documento la información de devengados
        foreach($arrDevengados as $devengado) {
            $keyDevengado = $devengado['prefijo'] . $devengado['consecutivo'];
            $this->arrDevengados[$keyDevengado][] = $devengado;
        }

        // Verifica que los valores correspondientes a las columnas de Concepto en Devengados, se encuentren dentro de los valores permitidos y que los conceptos que deben ser enviados una sola vez no sean enviados varias veces
        foreach($this->arrDevengados as $key => $devengados) {
            $conceptosDevengadosNoRepetir[$key] = ConstantsDataInput::CONTADOR_CONCEPTOS_DEVENGADOS_NO_REPETIR;
            foreach($devengados as $devengado) {
                if(!in_array($devengado['concepto'], ConstantsDataInput::CONCEPTOS_VALIDOS_DEVENGADOS))
                    $this->erroresEstructuraDevengadosDeducciones[] = "Documento [" . $key . "], el concepto devengado [" . $devengado['concepto'] . "] no es válido";

                if(array_key_exists($devengado['concepto'], $conceptosDevengadosNoRepetir[$key]))
                    $conceptosDevengadosNoRepetir[$key][$devengado['concepto']]++;
            }

            $devengadosRepetidos[$key] = [];
            array_filter(
                $conceptosDevengadosNoRepetir[$key],
                function($value, $indice) use (&$devengadosRepetidos, $key) {
                    if($value > 1) $devengadosRepetidos[$key][] = $indice;
                    return ($value > 1); }
                    , ARRAY_FILTER_USE_BOTH
            );
            if(!empty($devengadosRepetidos[$key]))
                $this->erroresEstructuraDevengadosDeducciones[] = "Documento [" . $key . "], conceptos de devengados que solo se pueden incluir una vez y se incluyeron varias veces: " . implode(', ', $devengadosRepetidos[$key]);
        }

        // Agrupa por documento la información de deducciones
        foreach($arrDeducciones as $deduccion) {
            $keyDeduccion = $deduccion['prefijo'] . $deduccion['consecutivo'];
            $this->arrDeducciones[$keyDeduccion][] = $deduccion;
        }

        // Verifica que los valores correspondientes a las columnas de Concepto en Deducciones, se encuentren dentro de los valores permitidos y que los conceptos que deben ser enviados una sola vez no sean enviados varias veces
        foreach($this->arrDeducciones as $key => $deducciones) {
            $conceptosDeduccionesNoRepetir[$key] = ConstantsDataInput::CONTADOR_CONCEPTOS_DEDUCCIONES_NO_REPETIR;
            foreach($deducciones as $deduccion) {
                if(!in_array($deduccion['concepto'], ConstantsDataInput::CONCEPTOS_VALIDOS_DEDUCCIONES))
                    $this->erroresEstructuraDevengadosDeducciones[] = "Documento [" . $key . "], el concepto deducción [" . $deduccion['concepto'] . "] no es válido";

                if(array_key_exists($deduccion['concepto'], $conceptosDeduccionesNoRepetir[$key]))
                    $conceptosDeduccionesNoRepetir[$key][$deduccion['concepto']]++;
            }

            $deduccionesRepetidas[$key] = [];
            array_filter(
                $conceptosDeduccionesNoRepetir[$key],
                function($value, $indice) use (&$deduccionesRepetidas, $key) {
                    if($value > 1) $deduccionesRepetidas[$key][] = $indice;
                    return ($value > 1); }
                    , ARRAY_FILTER_USE_BOTH
            );
            if(!empty($deduccionesRepetidas[$key]))
                $this->erroresEstructuraDevengadosDeducciones[] = "Documento [" . $key . "], conceptos de deducción que solo se pueden incluir una vez y se incluyeron varias veces: " . implode(', ', $deduccionesRepetidas[$key]);
        }
    }

    /**
     * Reorganiza los registros de las secciones de Devengados/Deducciones en un array del tipo [clave => valor].
     *
     * @param array $arrDevengadosDeducciones Array de Devengados/Deducciones que será reorganizado
     * @return array $filas Array de Devengados/Deducciones reorganizado
     */
    private function reorganizaRegistrosDevengadosDeducciones($arrDevengadosDeducciones) {
        // Reorganiza los arrays de Devengados/Deducciones para que las columnas pasen a ser índices en el array
        $columnas = array_filter($arrDevengadosDeducciones[1], function($value) { return !is_null($value) && $value !==''; });
        $keys     = [];
        foreach ($columnas as $columna) {
            $keys[] = trim(strtolower($this->sanear_string(str_replace(' ', '_', $columna))));
        }

        $filas    = [];
        for($i = 2; $i < count($arrDevengadosDeducciones); $i++) {
            $row = $arrDevengadosDeducciones[$i];
            $newrow = [];
            for($j = 0; $j < count($keys); $j++) {
                $value = $row[$j];
                $newrow[$keys[$j]] = $value;
            }
            $filas[] = $newrow;
        }

        return $filas;
    }

    /**
     * Valida que todas las filas de Devengados y Deducciones coincidan con al menos un documento de nómina electrónica en cabecera.
     *
     * @return void
     */
    private function validarExistenciaInfoDevengadosDeducciones() {
        if(!empty($this->arrDevengados)) {
            foreach($this->arrDevengados as $devengadosDocumento) {
                foreach($devengadosDocumento as $devengado)
                    $this->filtrarDataExcelDeduccionesDevengados($devengado, 'El registro de devengados');
            }
        }

        if(!empty($this->arrDeducciones)) {
            foreach($this->arrDeducciones as $deduccionesDocumento) {
                foreach($deduccionesDocumento as $deduccion)
                    $this->filtrarDataExcelDeduccionesDevengados($deduccion, 'El registro de deducción');
            }
        }
    }

    /**
     * Para Devengados y Deducciones, filtra la data frente a la información de documentos de nómina electrónica reportada en el Excel para validar que todas los registros de deducciones y devengados coincidan con al menos un documento de nómina electrónica.
     *
     * @param array $arrayBuscar
     * @param string $fraseError
     * @return void
     */
    private function filtrarDataExcelDeduccionesDevengados($arrayBuscar, $fraseError) {
        $existe = array_filter($this->data, function($registro) use ($arrayBuscar) {
            return $registro['nit_empleador'] == $arrayBuscar['nit_empleador'] &&
                $registro['nit_trabajador'] == $arrayBuscar['nit_trabajador'] &&
                $registro['prefijo']        == $arrayBuscar['prefijo'] &&
                $registro['consecutivo']    == $arrayBuscar['consecutivo'];
        });

        if(empty($existe)) {
                $this->erroresEstructuraDevengadosDeducciones[] = $fraseError . ' [NIT EMPLEADOR: ' . $arrayBuscar['nit_empleador'] . '], [NIT TRABAJADOR: ' . $arrayBuscar['nit_trabajador'] . '], [Prefijo: ' . $arrayBuscar['prefijo'] . '], [Consecutivo: ' . $arrayBuscar['consecutivo'] . '], [Concepto: ' . $arrayBuscar['concepto'] . '] no coincide con ningún documento de nómina electrónica del archivo';
        }
    }

    /**
     * Verifica la estructura de las columnas del excel
     *
     * @return array
     */
    private function validarColumnas() {
        if ($this->tipo !== ConstantsDataInput::EXCEL_NOMINA && $this->tipo !== ConstantsDataInput::EXCEL_ELIMINAR)
            return [
                'error'   => true,
                'message' => 'Error de procesamiento',
                'errores' => ['El tipo de documento no es soportado']
            ];

        $errores = [];
        if(!empty($this->erroresEstructuraDevengadosDeducciones))
            $errores = array_merge($errores, $this->erroresEstructuraDevengadosDeducciones);

        $verificarCabecera = ($this->tipo === ConstantsDataInput::EXCEL_NOMINA) ? ConstantsDataInput::COLUMNAS_DEFAULT_NOMINA_NOVEDAD_AJUSTE : ConstantsDataInput::COLUMNAS_DEFAULT_ELIMINAR;
        $diferenciasFaltan = array_diff($verificarCabecera, $this->nombresReales);
        $diferenciasSobran = array_diff($this->nombresReales, $verificarCabecera);
        if (!empty($diferenciasFaltan) || !empty($diferenciasSobran)) {
            if(!empty($diferenciasFaltan))
                $errores[] = 'Faltan las columnas: ' . implode(', ', $diferenciasFaltan);

            if(!empty($diferenciasSobran))
                $errores[] = 'Sobran las columnas: ' . implode(', ', $diferenciasSobran);
        }

        if(!empty($errores)) {
            return [
                'error' => true,
                'message' => 'La estructura del archivo no corresponde con la interfaz solicitada',
                'errores' => $errores
            ];
        } else {
            return ['error' => false];
        }
    }

    /**
     * Procesa la data del Excel para convertirla en objetos json.
     *
     * @return string
     */
    private function procesador() {
        $documentos = [];
        $prioridad  = null;
        foreach($this->data as $k => $documento) {
            // Agrupando la data por documentos
            if (array_key_exists('prefijo', $documento) && array_key_exists('consecutivo', $documento)) {
                $key = $documento['prefijo'] . $documento['consecutivo'];
                if (array_key_exists($key, $documentos))
                    $documentos[$key][] = ['data' => $documento, 'linea' => $k + 2];
                else
                    $documentos[$key] = [['data' => $documento, 'linea' => $k + 2]];
            }

            // Verifica si el empleador tiene configurada prioridad para los agendamientos
            if (array_key_exists('nit_empleador', $documento) && !empty($documento['nit_empleador'])) {
                $empleador = ConfiguracionEmpleador::select(['emp_id', 'emp_prioridad_agendamiento'])
                    ->where('emp_identificacion', $documento['nit_empleador'])
                    ->where('estado', 'ACTIVO')
                    ->first();

                if($empleador && !empty($empleador->emp_prioridad_agendamiento) && empty($prioridad))
                    $prioridad = $empleador->emp_prioridad_agendamiento;
            }
        }

        // Verificando cada documento
        foreach($documentos as $key => $doc) {
            $response = $this->validarDocumento($doc);
            if (!empty($response['errores']))
                $this->documentosConFallas[$key] = $response['errores'];
            else {
                $this->documentosParaAgendamiento[$key] = $doc[0];
            }
        }

        if (!empty($this->documentosParaAgendamiento)) {
            $user = auth()->user();

            $jsonBuilder = new JsonNominaBuilder($this->tipo);
            // Divide los documentos por bloques de acuerdo al máximo permitido en la BD para EDI
            $grupos = array_chunk($this->documentosParaAgendamiento, $user->getBaseDatos->bdd_cantidad_procesamiento_edi);
            foreach($grupos as $grupo) {
                foreach($grupo as $doc) {
                    if($this->tipo == ConstantsDataInput::EXCEL_NOMINA) {
                        $key                = $doc['data']['prefijo'] . $doc['data']['consecutivo'];
                        if(isset($this->arrDevengados[$key]))
                            $doc['devengados']  = $this->arrDevengados[$key];
                        if(isset($this->arrDeducciones[$key]))
                        $doc['deducciones'] = $this->arrDeducciones[$key];
                    }

                    $jsonBuilder->addDocument($doc);
                }
                $jsonNomina = $jsonBuilder->build();

                // Crea el agendamiento en el sistema
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $user->usu_id,
                    'bdd_id'                  => $user->bdd_id,
                    'age_proceso'             => 'NDI',
                    'age_cantidad_documentos' => count($grupo),
                    'age_prioridad'           => $prioridad,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);

                // Graba la información del Json en la tabla de programacion de procesamientos de Json
                EtlProcesamientoJson::create([
                    'pjj_tipo'                => 'NDI',
                    'pjj_json'                => $jsonNomina,
                    'pjj_procesado'           => 'NO',
                    'age_id'                  => $agendamiento->age_id,
                    'age_estado_proceso_json' => null,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO',
                ]);

                $jsonBuilder->emptyDocuments();
            }

            $N = count($this->documentosParaAgendamiento);
            if ($N === 1)
                $message = "Se ha agendado 1 documento para su procesamiento en background";
            else
                $message = "Se han agendado $N documentos para su procesamiento en background";
        } else
            $message = "No se han agendado documentos";

        return $message;
    }

    /**
     * Realiza las validaciones de data sobre el documento de nómina electrónica.
     * 
     * @param $documento
     * @return array
     */
    private function validarDocumento($documento) {
        $errores  = [];
        $fila     = $documento[0]['linea'];
        $reglasValidacion = ($this->tipo === ConstantsDataInput::EXCEL_NOMINA) ? $this->rulesNominaNovedadAjuste : $this->rulesEliminar;

        $validador = Validator::make($documento[0]['data'], $reglasValidacion);
        if (!empty($validador->errors()->all()))
            foreach($validador->errors()->all() as $reg) {
                $errores[] = "Fila [{$fila}] $reg";
            }

        if ($documento[0]['data']['tipo_documento'] !== '102' && $documento[0]['data']['tipo_documento'] !== '103')
            $errores[] = "EL tipo de documento no corresponde para algun tipo de documento de nómina electrónica en la fila [{$fila}]";

        return [
            'errores' => $errores
        ];
    }
}