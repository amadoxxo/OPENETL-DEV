<?php

namespace App\Http\Modulos\DataInputWorker\Excel;

use Validator;
use Ramsey\Uuid\Uuid;
use App\Traits\DiTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Models\AdoAgendamiento;
use Illuminate\Support\Facades\Storage;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\DataInputWorker\helpers;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\DataInputWorker\Json\JsonEtlBuilder;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\DataInputWorker\Json\JsonDocumentBuilder;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use openEtl\Tenant\Traits\Particionamiento\TenantParticionamientoTrait;

/**
 * Clase Gestora para el procesamiento de Cargas por medio de excel.
 *
 * Class ParserExcel
 * @package App\Http\Modulos\DataInputWorker
 */
class ParserExcel {
    use DiTrait, PackageMainTrait, TenantParticionamientoTrait;

    /**
     * Instancia de la clase helpers del DataInputWorker
     *
     * @var helpers
     */
    protected $helpers;

    /**
     * Nombre de la conexión Tenant por defecto a la base de datos.
     *
     * @var string
     */
    protected $connection = 'conexion01';

    /**
     * Constante para el control maximo de memoria
     */
    private const MAX_MEMORY = '512M';

    // Identificación de DHL Express
    private const NIT_DHLEXPRESS = '860502609';

    /**
     * Columnas sanitizadas para la interpretacion de cada registro.
     *
     * @var array
     */
    private $keys = [];

    /**
     * Nombre de las columnas reales del excel.
     *
     * @var array
     */
    private $nombresReales = [];

    /**
     * Data del documento de excel.
     *
     * @var array
     */
    private $data = [];

    /**
     * Tipo de documentos que seran cargados.
     *
     * @var string
     */
    private $tipo;

    /**
     * Indica el indice de inicio de items.
     *
     * @var int
     */
    private $inicioItems = -1;

    /**
     * Indica el indice de finalización de la columna de items requeridos
     *
     * @var int
     */
    private $finItemsRequeridos = -1;

    /**
     *  Contiene las columnas adicionales de cabecera que se han incluido en el excel.
     *
     * @var array
     */
    private $columnasOptativasCabecera = [];

    /**
     * Contiene las columnas que forman parte de la información adicional de cabecera.
     *
     * @var array
     */
    private $informacionAdicionalCabecera = [];

    /**
     *  Contiene las columnas adicionales de cabecera que se han incluido en el excel.
     *
     * @var array
     */
    private $columnasOptativasItems = [];

    /**
     * Contiene las columnas que forman parte de la información adicional items.
     *
     * @var array
     */
    private $informacionAdicionalItems = [];

    /**
     * Lista de elementos que presentaron fallas en la carga de excel - Validaciones.
     *
     * @var array
     */
    private $documentosConFallas = [];

    /**
     * Lista de elementos que en la carga de excel presentaron datos consistentes. No implica que cumplan con los filtros
     * de validación para ser registrados, solo que seran agendados para su validación total de integridad y posible
     * registro en una tenant de openEtl.
     *
     * @var array
     */
    private $documentosParaAgendamiento = [];

    /**
     * Lista de posibles impuestos identificados en el documento de excel
     *
     * @var array
     */
    private $listaImpuestos = [];

    /**
     * Lista de posibles cargos identificados en cabecera del documento de excel
     *
     * @var array
     */
    private $listaCargosCabecera = [];

    /**
     * Lista de posibles cargos identificados items del documento de excel
     *
     * @var array
     */
    private $listaCargosItems = [];

    /**
     * Lista de posibles cargos identificados en cabecera del documento de excel
     *
     * @var array
     */
    private $listaDescuentosCabecera = [];

    /**
     * Lista de posibles cargos identificados items del documento de excel
     *
     * @var array
     */
    private $listaDescuentosItems = [];

    /**
     * Lista de posibles retenciones identificadas en el documento de excel
     *
     * @var array
     */
    private $listaRetenciones = [];

    /**
     * Lista de impuestos registrados y activos en etl_openmain
     *
     * @var array
     */
    private $impuestosRegistrados = [];

    /**
     * Lista de tipo de documentos registrados y activos en etl_openmain
     *
     * @var array
     */
    private $tipoDocumentos = [];

    /**
     * Listado de retenciones existentes
     *
     * @var array
     */
    private $listaRetencionesExistentes = ['reteiva', 'reteica', 'retefuente'];

    /**
     * Lista de impuestos existentes
     *
     * @var array
     */
    private $listaImpuestosExistentes = ['iva', 'ica', 'impuesto_consumo'];

    /**
     * Contiene las reglas de validación basicas.
     *
     * @var
     */
    private $rules;

    /**
     * Nombre del archivo de excel cargado por el usuario.
     *
     * @var
     */
    private $archivoExcel;

    /**
     * Indica si el OFE está marcado para Sector Salud
     *
     * @var boolean
     */
    private $ofeSectorSalud = false;

    /**
     * Errores de estructura para el Sector Salud
     *
     * @var array
     */
    private $erroresEstructuraSectorSalud = [];

    /**
     * Sector Salud - Array para contener la información de Extensión Sector Salud
     *
     * @var array
     */
    private $arrExtensionSectorSalud = [];

    /**
     * Sector Salud - Array para contener la información de Referencia Adquirenre
     *
     * @var array
     */
    private $arrReferenciaAdquirenteSectorSalud = [];

    /**
     * Sector Salud - Array para contener la información de Documento Referencia Línea
     *
     * @var array
     */
    private $arrDocumentoReferenciaLineaSectorSalud = [];

    /**
     * Sector Salud - Array para contener la información de Documento Adicional
     *
     * @var array
     */
    private $arrDocumentoAdicionalSectorSalud = [];

    /**
     * Sector Salud - Array para contener la información de Documento Referencia
     *
     * @var array
     */
    private $arrDocumentoReferenciaSectorSalud = [];

    /**
     * ParserExcel constructor.
     */
    public function __construct() {
        set_time_limit(0);
        ini_set('memory_limit', self::MAX_MEMORY);

        $this->helpers = new helpers;

        ParametrosTributo::where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) {
                $key = strtolower($this->sanear_string($item->tri_nombre));
                $key = str_replace(' ', '_', $key);
                $this->impuestosRegistrados[$key] = $item->tri_codigo;
            });

        ParametrosTipoDocumentoElectronico::where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) {
                $key = strtolower($this->sanear_string($item->tri_nombre));
                $key = str_replace(' ', '_', $key);
                $this->tipoDocumentos[$key] = $item->tde_descripcion;
            });

        $this->buildRules();
    }

    /**
     * Construye las reglas ee valdación basicas para el excel.
     *
     */
    private function buildRules() {
        $this->rules = [
            'fecha'                                    => 'required|date_format:Y-m-d|before:tomorrow',
            'fecha_vencimiento'                        => 'date_format:Y-m-d',
            'fecha_factura'                            => 'nullable|date_format:Y-m-d',
            'medios_pago_fecha_vencimiento'            => 'date_format:Y-m-d',
            'fecha_trm'                                => 'date_format:Y-m-d',
            'fecha_inicio_periodo'                     => 'date_format:Y-m-d',
            'fecha_fin_periodo'                        => 'date_format:Y-m-d',
            'fecha_emision_orden_referencia'           => 'date_format:Y-m-d',
            'fecha_emision_despacho_referencia'        => 'date_format:Y-m-d',
            'fecha_emision_recpcion_referecia'         => 'date_format:Y-m-d',
            'entrega_bienes_fecha_salida'              => 'date_format:Y-m-d',
            'entrega_bienes_despacho_fecha_solicitada' => 'date_format:Y-m-d',
            'entrega_bienes_despacho_fecha_estimada'   => 'date_format:Y-m-d',
            'entrega_bienes_despacho_fecha_real'       => 'date_format:Y-m-d',
            'anticipo_fecha_recibio'                   => 'date_format:Y-m-d',
            'hora'                                     => 'required|date_format:H:i:s',
            'hora_inicio_periodo'                      => 'date_format:H:i:s',
            'hora_fin_periodo'                         => 'date_format:H:i:s',
            'entrega_bienes_hora_salida'               => 'date_format:H:i:s',
            'entrega_bienes_despacho_hora_solicitada'  => 'date_format:H:i:s',
            'entrega_bienes_despacho_hora_estimada'    => 'date_format:H:i:s',
            'entrega_bienes_despacho_hora_real'        => 'date_format:H:i:s',
            'cdo_cargo_porcentaje'                     => 'numeric',
            'trm'                                      => 'nullable|numeric',
            'cdo_cargo_base'                           => 'numeric',
            'cdo_cargo_valor'                          => 'numeric',
            'cdo_cargo_base_moneda_extranjera'         => 'nullable|numeric',
            'cdo_cargo_valor_moneda_extranjera'        => 'nullable|numeric',
            'cdo_descuento_porcentaje'                 => 'numeric|numeric',
            'cdo_descuento_base'                       => 'numeric|numeric',
            'cdo_descuento_valor'                      => 'numeric',
            'cdo_descuento_base_moneda_extranjera'     => 'nullable|numeric',
            'cdo_descuento_valor_moneda_extranjera'    => 'nullable|numeric',
            'cantidad'                                 => 'required|numeric',
            'cantidad_paquete'                         => 'nullable|numeric',
            'valor_unitario'                           => 'required|numeric',
            'total'                                    => 'required|numeric',
            'valor_unitario_moneda_extranjera'         => 'nullable|numeric',
            'total_moneda_extranjera'                  => 'nullable|numeric',
            'valor_muestra'                            => 'nullable|numeric',
            'valor_muestra_moneda_extranjera'          => 'nullable|numeric',
            'ddo_cargo_porcentaje'                     => 'numeric',
            'ddo_cargo_base'                           => 'numeric',
            'ddo_cargo_valor'                          => 'numeric',
            'ddo_cargo_base_moneda_extranjera'         => 'nullable|numeric',
            'ddo_cargo_valor_moneda_extranjera'        => 'nullable|numeric',
            'ddo_descuento_porcentaje'                 => 'numeric|numeric',
            'ddo_descuento_base'                       => 'numeric',
            'ddo_descuento_valor'                      => 'numeric',
            'ddo_descuento_base_moneda_extranjera'     => 'nullable|numeric',
            'ddo_descuento_valor_moneda_extranjera'    => 'nullable|numeric',
        ];
    }

    /**
     * Genera un archivo CSV.
     *
     * @param string $archivo
     * @return string|false|null
     * @throws \Exception
     */
    public function toCSV($archivo) {
        self::setFilesystemsInfo();
        $storagePath = Storage::disk(config('variables_sistema.ETL_LOCAL_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
        $tempfilecsv = $storagePath . Uuid::uuid4()->toString() . '.csv';
        $excel = Uuid::uuid4()->toString() . '.' . $ext = pathinfo($archivo, PATHINFO_EXTENSION);
        $pathExcel = $storagePath . $excel;

        // Guarda el archivo en disco
        Storage::disk(config('variables_sistema.ETL_LOCAL_STORAGE'))->put($excel, file_get_contents($archivo));
        // Construyendo el csv
        $salida = [];
        exec("ssconvert $pathExcel $tempfilecsv", $salida);
        unlink($archivo);
        return $tempfilecsv;
    }

    /**
     * Si el OFE está marcado para Sector Salud, este método se encarga de validar la composición de las secciones que aplican a Sector Salud y adicionalmente extrae hacia arrays las secciones correspondientes.
     *
     * @param array $registros Array que contiene toda la información contenida en el Excel cargado
     * @return void
     */
    private function validarExtraerSeccionesSectorSalud(&$registros) {
        // Columnas de Sector Salud que llegan en el Excel
        $columnasExcelSectorSalud    = [];

        // Columnas de Sector Salud que hacen parte del estandar
        $estandarColumnasSectorSalud = json_decode(config('variables_sistema.SECCIONES_COLUMNAS_SECTOR_SALUD_EXCEL'), true);

        // El array de registros es separado entre las secciones que corresponden al documento como tal y las secciones que corresponden a sector salud
        $filaDocumentoReferencia = array_search('DOCUMENTO REFERENCIA', array_column($registros, 0));
        $columnasDocumentoReferencia = [];
        if($filaDocumentoReferencia)
            $columnasDocumentoReferencia = array_filter($registros[$filaDocumentoReferencia + 1], function($value) { return !is_null($value) && $value !== ''; });

        $columnasExcelSectorSalud[$registros[$filaDocumentoReferencia][0]] = $columnasDocumentoReferencia;
        
        $filaDocumentoAdicional = array_search('DOCUMENTO ADICIONAL', array_column($registros, 0));
        $columnasDocumentoAdicional = [];
        if($filaDocumentoAdicional)
            $columnasDocumentoAdicional = array_filter($registros[$filaDocumentoAdicional + 1], function($value) { return !is_null($value) && $value !== ''; });

        $columnasExcelSectorSalud[$registros[$filaDocumentoAdicional][0]] = $columnasDocumentoAdicional;
        
        $filaDocumentoReferenciaLinea = array_search('DOCUMENTO REFERENCIA LINEA', array_column($registros, 0));
        $columnasDocumentoReferenciaLinea = [];
        if($filaDocumentoReferenciaLinea)
            $columnasDocumentoReferenciaLinea = array_filter($registros[$filaDocumentoReferenciaLinea + 1], function($value) { return !is_null($value) && $value !== ''; });

        $columnasExcelSectorSalud[$registros[$filaDocumentoReferenciaLinea][0]] = $columnasDocumentoReferenciaLinea;
        
        $filaReferenciaAdquirente = array_search('REFERENCIA ADQUIRENTE', array_column($registros, 0));
        $columnasReferenciaAdquirente = [];
        if($filaReferenciaAdquirente)
            $columnasReferenciaAdquirente = array_filter($registros[$filaReferenciaAdquirente + 1], function($value) { return !is_null($value) && $value !== ''; });

        $columnasExcelSectorSalud[$registros[$filaReferenciaAdquirente][0]] = $columnasReferenciaAdquirente;
        
        $filaExtensionSectorSalud = array_search('EXTENSION SECTOR SALUD', array_column($registros, 0));
        $columnasExtensionSectorSalud = [];
        if($filaExtensionSectorSalud)
            $columnasExtensionSectorSalud = array_filter($registros[$filaExtensionSectorSalud + 1], function($value) { return !is_null($value) && $value !== ''; });

        $columnasExcelSectorSalud[$registros[$filaExtensionSectorSalud][0]] = $columnasExtensionSectorSalud;

        // Compara la estructura de columnas de Sector Salud frente al estandar de columnas existente
        foreach ($estandarColumnasSectorSalud as $seccion => $columnas) {
            if(!array_key_exists($seccion, $columnasExcelSectorSalud))
                $this->erroresEstructuraSectorSalud[] = "Sector Salud: Falta el título de la sección [" . $seccion . "] o no fue posible identificar la sección";
            else {
                if($this->tipo == ConstantsDataInput::NC || $this->tipo == ConstantsDataInput::ND || $this->tipo == ConstantsDataInput::NC_ND) { // Para el Excel de NC - ND la columna estandar RESOLUCION FACTURACION CABECERA no se usa por lo que se descarta del estandar de la sección
                    $keyResolucion = array_search('RESOLUCION FACTURACION CABECERA', $estandarColumnasSectorSalud[$seccion], TRUE);
                    unset($estandarColumnasSectorSalud[$seccion][$keyResolucion]);
                    $estandarColumnasSectorSalud[$seccion] = array_values($estandarColumnasSectorSalud[$seccion]);
                }

                if(array_diff($estandarColumnasSectorSalud[$seccion], $columnasExcelSectorSalud[$seccion]) || array_diff($columnasExcelSectorSalud[$seccion], $estandarColumnasSectorSalud[$seccion]))
                    $this->erroresEstructuraSectorSalud[] = "Sector Salud: La estructura de columnas de la sección [" . $seccion . "] no es la correcta";
            }
        }

        if(empty($this->erroresEstructuraSectorSalud)) {
            $this->arrExtensionSectorSalud                = array_splice($registros, $filaExtensionSectorSalud); // Extrae al array solamente lo correspondiente a Extension Sector Salud
            $this->arrReferenciaAdquirenteSectorSalud     = array_splice($registros, $filaReferenciaAdquirente); // Extrae al array solamente lo correspondiente a Referencia Adquirente
            $this->arrDocumentoReferenciaLineaSectorSalud = array_splice($registros, $filaDocumentoReferenciaLinea); // Extrae al array solamente lo correspondiente a Documento Referencia Linea
            $this->arrDocumentoAdicionalSectorSalud       = array_splice($registros, $filaDocumentoAdicional); // Extrae al array solamente lo correspondiente a Documento Adicional
            $this->arrDocumentoReferenciaSectorSalud      = array_splice($registros, $filaDocumentoReferencia); // Extrae al array solamente lo correspondiente a Documento Referencia
            array_splice($registros, $filaDocumentoReferencia); // Del array $registros resultante de las operaciones anteriores, se excluye la fila correspondiente al título de sección Documento Referencia

            // Elimina filas vacias de los arrays resultantes
            $this->arrExtensionSectorSalud                = $this->reorganizaRegistrosSectorSalud(array_filter($this->arrExtensionSectorSalud, function($value) { return !is_null($value[0]) && $value[0] !==''; }));
            $this->arrReferenciaAdquirenteSectorSalud     = $this->reorganizaRegistrosSectorSalud(array_filter($this->arrReferenciaAdquirenteSectorSalud, function($value) { return !is_null($value[0]) && $value[0] !==''; }));
            $this->arrDocumentoReferenciaLineaSectorSalud = $this->reorganizaRegistrosSectorSalud(array_filter($this->arrDocumentoReferenciaLineaSectorSalud, function($value) { return !is_null($value[0]) && $value[0] !==''; }));
            $this->arrDocumentoAdicionalSectorSalud       = $this->reorganizaRegistrosSectorSalud(array_filter($this->arrDocumentoAdicionalSectorSalud, function($value) { return !is_null($value[0]) && $value[0] !==''; }));
            $this->arrDocumentoReferenciaSectorSalud      = $this->reorganizaRegistrosSectorSalud(array_filter($this->arrDocumentoReferenciaSectorSalud, function($value) { return !is_null($value[0]) && $value[0] !==''; }));
            $registros                                    = array_filter($registros, function($value) { return !is_null($value[0]) && $value[0] !==''; });
        }
    }

    /**
     * Para el Sector Salud, filtra la data del sector frente a la información de documentos electrónicos reportados en el Excel para validar que todas las referencias del sector coincidan con al menos un documento electrónico.
     *
     * @param array $arrayBuscar
     * @param string $fraseError
     * @return void
     */
    private function filtrarDataExcelSectorSalud($arrayBuscar, $fraseError) {
        $existe = array_filter($this->data, function($registro) use ($arrayBuscar) {
            if($this->tipo == ConstantsDataInput::NC || $this->tipo == ConstantsDataInput::ND || $this->tipo == ConstantsDataInput::NC_ND)
                return $registro['tipo_documento'] == $arrayBuscar['tipo_documento_cabecera'] &&
                    $registro['tipo_operacion'] == $arrayBuscar['tipo_operacion_cabecera'] &&
                    $registro['prefijo'] == $arrayBuscar['prefijo_cabecera'] &&
                    $registro['consecutivo'] == $arrayBuscar['consecutivo_cabecera'];
            else
                return $registro['tipo_documento'] == $arrayBuscar['tipo_documento_cabecera'] &&
                    $registro['tipo_operacion'] == $arrayBuscar['tipo_operacion_cabecera'] &&
                    $registro['resolucion_facturacion'] == $arrayBuscar['resolucion_facturacion_cabecera'] &&
                    $registro['prefijo'] == $arrayBuscar['prefijo_cabecera'] &&
                    $registro['consecutivo'] == $arrayBuscar['consecutivo_cabecera'];
        });

        if(empty($existe)) {
            if($this->tipo == ConstantsDataInput::NC || $this->tipo == ConstantsDataInput::ND || $this->tipo == ConstantsDataInput::NC_ND)
                $this->erroresEstructuraSectorSalud[] = $fraseError . ' de sector salud [Tipo Documento Cabecera: ' . $arrayBuscar['tipo_documento_cabecera'] . '], [Tipo operacion Cabecera: ' . $arrayBuscar['tipo_operacion_cabecera'] . '], [Prefijo Cabecera: ' . $arrayBuscar['prefijo_cabecera'] . '], [Consecutivo Cabecera: ' . $arrayBuscar['consecutivo_cabecera'] . '] no coincide con ningún documento electrónico del archivo';
            else
                $this->erroresEstructuraSectorSalud[] = $fraseError . ' de sector salud [Tipo Documento Cabecera: ' . $arrayBuscar['tipo_documento_cabecera'] . '], [Tipo operacion Cabecera: ' . $arrayBuscar['tipo_operacion_cabecera'] . '], [Resolucion Facturacion Cabecera: ' . $arrayBuscar['resolucion_facturacion_cabecera'] . '], [Prefijo Cabecera: ' . $arrayBuscar['prefijo_cabecera'] . '], [Consecutivo Cabecera: ' . $arrayBuscar['consecutivo_cabecera'] . '] no coincide con ningún documento electrónico del archivo';
        }
    }

    /**
     * Valida que todas las referencias del Sector Salud coincidan con al menos un documento electrónico.
     *
     * @return void
     */
    private function validarExistenciaInfoSectorSalud() {
        if(!empty($this->arrDocumentoReferenciaSectorSalud)) {
            foreach($this->arrDocumentoReferenciaSectorSalud as $registroSectorSalud) {
                $this->filtrarDataExcelSectorSalud($registroSectorSalud, 'El documento referencia');
            }
        }

        if(!empty($this->arrDocumentoAdicionalSectorSalud)) {
            foreach($this->arrDocumentoAdicionalSectorSalud as $registroSectorSalud) {
                $this->filtrarDataExcelSectorSalud($registroSectorSalud, 'El documento adicional');
            }
        }

        if(!empty($this->arrDocumentoReferenciaLineaSectorSalud)) {
            foreach($this->arrDocumentoReferenciaLineaSectorSalud as $registroSectorSalud) {
                $this->filtrarDataExcelSectorSalud($registroSectorSalud, 'El documento referencia línea');
            }
        }

        if(!empty($this->arrReferenciaAdquirenteSectorSalud)) {
            foreach($this->arrReferenciaAdquirenteSectorSalud as $registroSectorSalud) {
                $this->filtrarDataExcelSectorSalud($registroSectorSalud, 'la referencia adquirente');
            }
        }

        if(!empty($this->arrExtensionSectorSalud)) {
            $actosAdministrativos = [];
            foreach($this->arrExtensionSectorSalud as $registroSectorSalud) {
                $this->filtrarDataExcelSectorSalud($registroSectorSalud, 'La extensión');

                if($this->tipo == ConstantsDataInput::NC || $this->tipo == ConstantsDataInput::ND || $this->tipo == ConstantsDataInput::NC_ND)
                    $indice = $registroSectorSalud['tipo_documento_cabecera'] .  $registroSectorSalud['tipo_operacion_cabecera'] . $registroSectorSalud['prefijo_cabecera'] . $registroSectorSalud['consecutivo_cabecera'];
                else
                    $indice = $registroSectorSalud['tipo_documento_cabecera'] .  $registroSectorSalud['tipo_operacion_cabecera'] . $registroSectorSalud['resolucion_facturacion_cabecera'] . $registroSectorSalud['prefijo_cabecera'] . $registroSectorSalud['consecutivo_cabecera'];

                if(!array_key_exists($indice, $actosAdministrativos))
                    $actosAdministrativos[$indice] = [];

                if(!in_array($registroSectorSalud['acto_administrativo'], $actosAdministrativos[$indice]))
                    $actosAdministrativos[$indice][] = $registroSectorSalud['acto_administrativo'];
            }

            $multiplesActosAdministrativos = false;
            foreach($actosAdministrativos as $acto) {
                if(count($acto) > 1)
                    $multiplesActosAdministrativos = true;
            }

            if($multiplesActosAdministrativos)
                $this->erroresEstructuraSectorSalud[] = 'El archivo de Excel incluye, para un mismo documento electrónico, diferentes actos administrativos en la Extensión Sector Salud';
        }
    }

    /**
     * Reorganiza los registros de las secciones de Sector Salud en un array del tipo [clave => valor].
     *
     * @param array $arrSectorSalud Array de Sector Salud que será reorganizado
     * @return array $filas Array se Sector Salud reorganizado
     */
    private function reorganizaRegistrosSectorSalud($arrSectorSalud) {
        // Reorganiza los arrays de Sector Salud para que las columnas pasen a ser índices en el array
        $columnas = array_filter($arrSectorSalud[1], function($value) { return !is_null($value) && $value !==''; });
        $keys     = [];
        foreach ($columnas as $columna) {
            $keys[] = trim(strtolower($this->sanear_string(str_replace(' ', '_', $columna))));
        }

        $filas    = [];
        for($i = 2; $i < count($arrSectorSalud); $i++) {
            $row = $arrSectorSalud[$i];
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
     * Efectua el proceso de registro de un archivo de excel para su posterior carga en DI por medio de procesamiento de documentos en segundo plano.
     *
     * @param string $archivo
     * @param string $tipo
     * @param int $ofe_id
     * @param string $nombreRealArchivo
     * @return array
     * @throws \Exception
     */
    public function run(string $archivo, string $tipo, int $ofe_id, string $nombreRealArchivo = '') {
        $this->archivoExcel = $nombreRealArchivo;
        $this->tipo = $tipo;
        $this->parserExcel($archivo, $ofe_id);
        $response = $this->validarColumnas($ofe_id);
        $errores = [];
        $message = '';
        // Si no hay error
        if (!$response['error']) {
            $message = $this->procesador($ofe_id);
            foreach($this->documentosConFallas as $doc)
                foreach($doc['errores'] as $err)
                    $errores[] = $err;
        } else {
            $errores = array_merge($response['errores']);
        }
        $resultado = [
            'error' => count($errores) > 0,
            'errores' => $errores,
            'message' => $message
        ];
        return $resultado;
    }

    /**
     * Obtiene la data de un archivo de Excel.
     *
     * @param string $archivo Archivo de Excel cargado
     * @param int $ofe_id ID del OFE seleccionado para el proceso de cargue
     * @throws \Exception
     */
    public function parserExcel($archivo, $ofe_id) {
        self::setFilesystemsInfo();
        $storagePath = Storage::disk(config('variables_sistema.ETL_LOCAL_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
        $tempfilecsv = $storagePath . Uuid::uuid4()->toString() . '.csv';
        // Construyendo el csv
        $salida = [];
        exec("ssconvert $archivo $tempfilecsv", $salida);

        $registros = [];
        if (($handle = fopen($tempfilecsv, "r")) !== false) {
            while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                $registros[] = $data;
            }
            fclose($handle);
        }
        $header = $registros[0];
        $this->keys = [];
        $this->data = [];
        $mode = 'cdo';
        
        // Verificar si el OFE esta mrcado para Sector Salud y poder efectuar diversas operaciones
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'bdd_id_rg', 'ofe_campos_personalizados_factura_generica'])
            ->find($ofe_id);

        if(!empty($ofe->ofe_campos_personalizados_factura_generica) && 
            array_key_exists('aplica_sector_salud', $ofe->ofe_campos_personalizados_factura_generica) && 
            $ofe->ofe_campos_personalizados_factura_generica['aplica_sector_salud'] == 'SI'
        ) {
            $this->ofeSectorSalud = true;
            $this->validarExtraerSeccionesSectorSalud($registros);
        }

        $keysToReplace = [
            'cargo_descripcion',
            'cargo_porcentaje',
            'cargo_base',
            'cargo_valor',
            'cargo_base_moneda_extranjera',
            'cargo_valor_moneda_extranjera',
            'descuento_codigo',
            'descuento_descripcion',
            'descuento_porcentaje',
            'descuento_base',
            'descuento_valor',
            'descuento_base_moneda_extranjera',
            'descuento_valor_moneda_extranjera'
        ];

        foreach($header as $i => $k) {
            $key = trim(strtolower($this->sanear_string(str_replace(' ', '_', $k))));
            $this->nombresReales[$key] = $k;

            if ($key === 'tipo_item') {
                $mode = 'ddo';
                $this->inicioItems = $i;
            }

            if ($this->tipo == 'DS' || $this->tipo == 'DS_NC') {
                if ($key === 'motivo_exencion_iva') {
                    $this->finItemsRequeridos = $i;
                }
            } else {
                if ($key === 'valor_ica_moneda_extranjera') {
                    $this->finItemsRequeridos = $i;
                }
            }

            if (in_array($key, $keysToReplace) !== false)
                $key = $mode . '_' . $key;
            $this->keys[] = $key;
        }

        $N = count($this->keys);
        for ($i = 1; $i < count($registros); $i++) {
            $row = $registros[$i];
            $newrow = [];
            for ($j = 0; $j < $N; $j++) {
                $value = $row[$j];
                if (in_array($this->keys[$j], ColumnsExcel::$fixFecha) && !empty($value))
                    $value = str_replace('/', '-', $value);
                $newrow[$this->keys[$j]] = $value;
            }
            $this->data[] = $newrow;
        }
        unlink($archivo);
        unlink($tempfilecsv);

        // Se verifica si cada registro de cada sección salud esta relacionado con al menos un documento electrónico
        if($this->ofeSectorSalud)
            $this->validarExistenciaInfoSectorSalud();
    }

    /**
     * Agrega una retención a la lista de retenciones siempre y cuando esta no se halla registrado ya.
     *
     * @param string $retencion
     */
    private function agregarRetencion(string $retencion) {
        $retencion = $this->sanear_string($retencion);
        if (!in_array($retencion, $this->listaRetenciones))
            $this->listaRetenciones[] = $retencion;
    }

    /**
     * Agrega un impuesto a la lista de impuestos siempre y cuando este no se halla registrado ya.
     *
     * @param string $impuesto
     */
    private function agregarImpuesto(string $impuesto) {
        $impuesto = $this->sanear_string($impuesto);
        if (!in_array($impuesto, $this->listaImpuestos))
            $this->listaImpuestos[] = $impuesto;
    }

    /**
     * Agrega un cargo a la lista de cargos en cabecera o items siempre y cuando este no se halla registrado ya.
     *
     * @param string $cargo
     * @param bool $items
     */
    private function agregarCargo(string $cargo, bool $items = false) {
        $cargo = $this->sanear_string($cargo);
        if (!$items) {
            if (!in_array($cargo, $this->listaCargosCabecera))
                $this->listaCargosCabecera[] = $cargo;
        }
        else {
            if (!in_array($cargo, $this->listaCargosItems))
                $this->listaCargosItems[] = $cargo;
        }
    }

    /**
     * Agrega un cargo a la lista de descuentos en cabecera o items siempre y cuando este no se halla registrado ya.
     *
     * @param string $descuento
     * @param bool $items
     */
    private function agregarDescuento(string $descuento, bool $items = false) {
        $descuento = $this->sanear_string($descuento);
        if (!$items) {
            if (!in_array($descuento, $this->listaDescuentosCabecera))
                $this->listaDescuentosCabecera[] = $descuento;
        }
        else {
            if (!in_array($descuento, $this->listaDescuentosItems))
                $this->listaDescuentosItems[] = $descuento;
        }
    }

    /**
     * Determina cuales son los impuestos y retenciones que el usuario ha definido en los campos optativos de items.
     *
     * @return array
     */
    private function determinarImpuestosRetenciones() {
        return array_filter($this->informacionAdicionalItems, function ($item) {
            $impuestos = [
                ['pattern' => '/^porcentaje_%s$/', 'remove' => ['porcentaje_'], 'replace' => ['']],
                ['pattern' => '/^impuesto_unidad_%s_unidad$/', 'remove' => ['impuesto_unidad_', '_unidad'], 'replace' => ['', '']],
                ['pattern' => '/^base_%s_moneda_extranjera$/', 'remove' => ['base_', '_moneda_extranjera'], 'replace' => ['', '']],
                ['pattern' => '/^base_%s$/', 'remove' => ['base_'], 'replace' => ['']],
                ['pattern' => '/^valor_%s_moneda_extranjera$/', 'remove' => ['valor_', '_moneda_extranjera'], 'replace' => ['', '']],
                ['pattern' => '/^valor_%s$/', 'remove' => ['valor_'], 'replace' => ['']],
                ['pattern' => '/^impuesto_unidad_%s_base_moneda_extranjera$/', 'remove' => ['impuesto_unidad_', '_base_moneda_extranjera'], 'replace' => ['', '']],
                ['pattern' => '/^impuesto_unidad_%s_base$/', 'remove' => ['impuesto_unidad_', '_base'], 'replace' => ['', '']],
                ['pattern' => '/^impuesto_unidad_%s_valor_unitario_moneda_extranjera$/', 'remove' => ['impuesto_unidad_', '_valor_unitario_moneda_extranjera'], 'replace' => ['', '']],
                ['pattern' => '/^impuesto_unidad_%s_valor_unitario$/', 'remove' => ['impuesto_unidad_', '_valor_unitario'], 'replace' => ['', '']],
            ];

            $retenciones = [
                ['pattern' => '/^porcentaje_%s$/', 'remove' => ['porcentaje_'], 'replace' => ['']],
                ['pattern' => '/^retencion_unidad_%s_unidad$/', 'remove' => ['retencion_unidad_', '_unidad'], 'replace' => ['', '']],
                ['pattern' => '/^base_%s_moneda_extranjera$/', 'remove' => ['base_', '_moneda_extranjera'], 'replace' => ['', '']],
                ['pattern' => '/^base_%s$/', 'remove' => ['base_'], 'replace' => ['']],
                ['pattern' => '/^valor_%s_moneda_extranjera$/', 'remove' => ['valor_', '_moneda_extranjera'], 'replace' => ['', '']],
                ['pattern' => '/^valor_%s$/', 'remove' => ['valor_'], 'replace' => ['']],
                ['pattern' => '/^retencion_unidad_%s_base_moneda_extranjera$/', 'remove' => ['retencion_unidad_', '_base_moneda_extranjera'], 'replace' => ['', '']],
                ['pattern' => '/^retencion_unidad_%s_base$/', 'remove' => ['retencion_unidad_', '_base'], 'replace' => ['', '']],
                ['pattern' => '/^retencion_unidad_%s_valor_unitario_moneda_extranjera$/', 'remove' => ['retencion_unidad_', '_valor_unitario_moneda_extranjera'], 'replace' => ['', '']],
                ['pattern' => '/^retencion_unidad_%s_valor_unitario$/', 'remove' => ['retencion_unidad_', '_valor_unitario'], 'replace' => ['', '']],
            ];

            foreach($impuestos as $k => $impuesto) {
                foreach($this->listaImpuestosExistentes as $impuestosExistente) {
                    $patron = sprintf($impuesto['pattern'], $impuestosExistente);
                    if (preg_match($patron, $item)) {
                        $this->agregarImpuesto($impuestosExistente);
                        break;
                    }
                }
            }

            foreach($retenciones as $k => $retencion) {
                foreach($this->listaRetencionesExistentes as $retencionesExistente) {
                    $patron = sprintf($retencion['pattern'], $retencionesExistente);
                    if (preg_match($patron, $item)) {
                        $this->agregarRetencion($retencionesExistente);
                        return true;
                    }
                }
            }

            return false;
        });
    }

    /**
     * Validad que la columnas de documentos adicionales este formada correctamente.
     *
     * @param $registro
     * @param int $fila
     * @return array
     */
    private function validarDocumentosAdicionales($registro, int $fila) {
        if (array_key_exists('documento_adicional', $registro) && !empty($registro['documento_adicional'])) {
            if (is_string($registro['documento_adicional'])) {
                $datos = explode('|', $registro['documento_adicional']);
                foreach($datos as $dat) {
                    if (trim($dat) != ''){
                        if (!preg_match('/[0-9a-zA-Z]{1,10}~[0-9a-zA-Z]{1,5}~[0-9a-zA-Z]{1,20}~[0-9a-zA-Z]{0,1000}~[0-9]{4}-[0-9]{2}-[0-9]{2}/', $dat))
                            return ['error' => true, 'errores' => ["El campo cdo_documento_adicional no tiene el formato adecuado en la fila [{$fila}]"]];
                    }
                }
            }
            else {
                return ['error' => true, errores => ["El campo cdo_documento_adicional no tiene el formato adecuado en la fila [{$fila}]"]];
            }
        }
        return ['error' => false];
    }

    /**
     * Evalua la estructura de los cargos para la carga por excel,
     *
     * @param array $registro
     * @param array $colecion
     * @param string $seccion
     * @return array
     */
    public function evaluarObjetosCargos(array $registro, array $colecion, string $seccion = 'cabecera') {
        $errores = [];
        $formatos = [
            "cargo_%s",
            "cargo_%s_porcentaje",
            "cargo_%s_base",
            "cargo_%s_valor",
            "cargo_%s_base_moneda_extranjera",
            "cargo_%s_valor_moneda_extranjera"
        ];

        foreach($colecion as $grupo) {
            foreach($formatos as $k => $col) {
                $key = sprintf($col, $grupo);
                if (!in_array($key, $registro)) {
                    $columna = strtoupper(str_replace('_', ' ', $key));
                    $errores[] = "Para el cargo {$grupo} no existe una columan en el archivo de excel llamada {$columna} en la sección de correspondiente de {$seccion}";
                }
                else {
                    // se trata de los valores númericos
                    if ($k > 0)
                        $this->rules[$key] = 'numeric';
                }
            }
        }
        return $errores;
    }

    /**
     * Evalua la estructura de los descuentos para la carga por excel.
     *
     * @param array $registro
     * @param array $colecion
     * @param string $seccion
     * @return array
     */
    public function evaluarObjetosDescuentos(array $registro, array $colecion, string $seccion = 'cabecera') {
        $errores = [];
        $formatos = [
            "descuento_%s_codigo",
            "descuento_%s",
            "descuento_%s_porcentaje",
            "descuento_%s_base",
            "descuento_%s_valor",
            "descuento_%s_base_moneda_extranjera",
            "descuento_%s_valor_moneda_extranjera"
        ];

        foreach($colecion as $grupo) {
            foreach($formatos as $k => $col) {
                $key = sprintf($col, $grupo);
                if (!in_array($key, $registro)) {
                    $columna = strtoupper(str_replace('_', ' ', $key));
                    $errores[] = "Para el descuento {$grupo} no existe una columan en el archivo de excel llamada {$columna} en la sección de correspondiente de {$seccion}";
                }
                else {
                    // se trata de los valores númericos
                    if ($k > 1)
                        $this->rules[$key] = 'numeric';
                }
            }
        }
        return $errores;
    }

    /**
     * Evalua si el excel contiene una seccion de impuestos completa o no.
     *
     * @param array $camposOpcionalesItems
     * @param array $colecion
     * @param string $modo
     * @return array
     */
    private function evaluarObjetosTributo(array $camposOpcionalesItems, array $colecion, $modo = 'impuesto') {
        $errores = [];
        $formatos = [
            "base_%s",
            "porcentaje_%s",
            "valor_%s",
            "base_%s_moneda_extranjera",
            "valor_%s_moneda_extranjera",
            "{$modo}_unidad_%s_unidad",
            "{$modo}_unidad_%s_base",
            "{$modo}_unidad_%s_valor_unitario",
            "{$modo}_unidad_%s_valor",
            "{$modo}_unidad_%s_base_moneda_extranjera",
            "{$modo}_unidad_%s_valor_unitario_moneda_extranjera",
            "{$modo}_unidad_%s_valor_moneda_extranjera",
        ];

        $articulo = $modo === 'impuesto' ? 'el' : 'la';

        foreach($colecion as $grupo) {
            foreach($formatos as $col) {
                $key = sprintf($col, $grupo);
                if (!in_array($key, $camposOpcionalesItems)) {
                    $columna = strtoupper(str_replace('_', ' ', $key));
                    $errores[] = "Para {$articulo} {$modo} {$grupo} no existe una columan en el archivo de excel llamada {$columna} en la sección de correspondiente de items";
                }
                else
                    $this->rules[$key] = 'numeric';
            }
        }

        return $errores;
    }

    /**
     * Verifica la estructura de las columnas del excel.
     *
     * @param $ofe_id
     * @return array
     */
    private function validarColumnas($ofe_id) {
        if ($this->inicioItems === -1)
            return ['error' => true, 'errores' => ['Es imposible determinar en que columna inicia la sección de items, falta la columna TIPO ITEM']];

        if ($this->finItemsRequeridos === -1)
            return ['error' => true, 'errores' => ['Es imposible determinar en que columna finaliza la sección de items, falta la columna VALOR ICA MONEDA EXTRANJERA']];

        if(!empty($this->erroresEstructuraSectorSalud))
            return ['error' => true, 'errores' => $this->erroresEstructuraSectorSalud];

        $faltantesObligatoriosCabecera = [];
        $faltantesOptativasCabecera = [];
        $inconsistenciasAdicionalCabecera = [];
        $faltantesObligatoriosItems = [];
        $faltantesOptativasItems = [];
        $inconsistenciasAdicionalitems = [];

        // Obtiene el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_columnas_personalizadas', 'ofe_identificacion', 'ofe_identificador_unico_adquirente'])
            ->where('ofe_id', $ofe_id)
            ->first();

        if ($this->tipo === ConstantsDataInput::FC || $this->tipo === ConstantsDataInput::DS) {
            $tipo = $this->tipo;
        } elseif ($this->tipo === ConstantsDataInput::DS_NC) {
            $tipo = 'DS';
        } else {
            $tipo = 'NC-ND';
        }
        $columnasCabeceraAdicionales = [];
        $columnasDetalleAdicionales = [];

        $corregir = [
            'descuento_codigo',
            'descuento_descripcion',
            'descuento_porcentaje',
            'descuento_base',
            'descuento_valor',
            'descuento_base_moneda_extranjera',
            'descuento_valor_moneda_extranjera',
            'cargo_descripcion',
            'cargo_porcentaje',
            'cargo_base',
            'cargo_valor',
            'cargo_base_moneda_extranjera',
            'cargo_valor_moneda_extranjera',
        ];

        $tempCabecera = [];
        $tempItems = [];

        // Valida si el campo ofe_columnas_personalizadas tiene información
        if (isset($ofe->ofe_columnas_personalizadas) && !is_null($ofe->ofe_columnas_personalizadas) && is_array($ofe->ofe_columnas_personalizadas)) {
            if (array_key_exists($tipo, $ofe->ofe_columnas_personalizadas)) {
                if (is_array($ofe->ofe_columnas_personalizadas[$tipo]['cabecera'])) {
                    foreach($ofe->ofe_columnas_personalizadas[$tipo]['cabecera'] as $item) {
                        $columna = strtolower($this->sanear_string($item));
                        $columnasCabeceraAdicionales[] = $columna;
                        if (in_array($columna, $corregir))
                            $columna = 'cdo_' . $columna;
                        $tempCabecera[] = $columna;
                    }
                }
                if (is_array($ofe->ofe_columnas_personalizadas[$tipo]['detalle'])) {
                    foreach($ofe->ofe_columnas_personalizadas[$tipo]['detalle'] as $item) {
                        $columna = strtolower($this->sanear_string($item));
                        $columnasDetalleAdicionales[] = $columna;
                        if (in_array($columna, $corregir))
                            $columna = 'ddo_' . $columna;
                        $tempItems[] = $columna;
                    }
                }
            }
        }

        // Verificando la estructura de las columnas obligatorias en cabecera
        if ($this->tipo !== ConstantsDataInput::FC && $this->tipo !== ConstantsDataInput::NC_ND && $this->tipo !== ConstantsDataInput::DS && $this->tipo !== ConstantsDataInput::DS_NC)
            return ['error' => true, 'errores' => ['El tipo de documento no es soportado']];

        if ($this->tipo === ConstantsDataInput::FC) {
            $verificarCabecera = ColumnsExcel::$columnasObligatoriasCabeceraFC;
        } elseif ($this->tipo === ConstantsDataInput::DS) {
            $verificarCabecera = array_replace(ColumnsExcel::$columnasObligatoriasCabeceraFC, array(2 => "nit_receptor", 3 => "nit_vendedor"));
            $indice = array_search('nit_autorizado', $verificarCabecera);
            unset($verificarCabecera[$indice]);
            $verificarCabecera = array_values($verificarCabecera);
        } elseif ($this->tipo === ConstantsDataInput::DS_NC) {
            $verificarCabecera = array_replace(ColumnsExcel::$columnasObligatoriasCabeceraNCND, array(2 => "nit_receptor", 3 => "nit_vendedor"));
            $indice = array_search('nit_autorizado', $verificarCabecera);
            unset($verificarCabecera[$indice]);
            $verificarCabecera = array_values($verificarCabecera);
        } else {
            $verificarCabecera = ColumnsExcel::$columnasObligatoriasCabeceraNCND;
        }

        if (isset($ofe->ofe_identificador_unico_adquirente) && !is_null($ofe->ofe_identificador_unico_adquirente) && is_array($ofe->ofe_identificador_unico_adquirente)) {
            // Si existe lo agrega a la cabecera de validacion
            if(in_array('adq_id_personalizado', $ofe->ofe_identificador_unico_adquirente)){
                array_splice($verificarCabecera, 4, 0, 'id_personalizado');
            }
        }

        // Valida que si el campo no existe en las columnas obligatorias pero se envía en las cabeceras, lo agregué en el arreglo tempCabecera
        if(!in_array('id_personalizado', $verificarCabecera) && in_array('id_personalizado', $this->keys)){
            $tempCabecera[] = $this->keys[4];
            $this->inicioItems = $this->inicioItems - 1;
        }

        // Valida que si el campo existe en las columnas obligatorias pero no se envíe, lo agregué al arreglo arrCabeceraFaltante
        if(in_array('id_personalizado', $verificarCabecera) && !in_array('id_personalizado', $this->keys)){
            $arrCabeceraFaltante[] = strtoupper(str_replace('_', ' ',$verificarCabecera[4]));
            unset($verificarCabecera[4]);
            $verificarCabecera = array_values($verificarCabecera);
        }

        $i = 0;
        while ($i < count($verificarCabecera)) {
            if ($verificarCabecera[$i] !== $this->keys[$i])
                $arrCabeceraFaltante[] = strtoupper(str_replace('_', ' ',$verificarCabecera[$i]));
            $i++;
        }

        if(!empty($arrCabeceraFaltante))
            $faltantesObligatoriosCabecera[] = "Falta la Columna de cabecera: " . implode(', ', $arrCabeceraFaltante);
        else{
            if($this->tipo === ConstantsDataInput::FC || $this->tipo === ConstantsDataInput::DS)
                ColumnsExcel::$columnasObligatoriasCabeceraFC = $verificarCabecera;
            else
                ColumnsExcel::$columnasObligatoriasCabeceraNCND = $verificarCabecera;
        }

        // Determinando los campos adicionales y la infomacion opcional que se gestiona en el apartado de cabecera del documento
        $otrasColumnasCabecera = [];
        for ($k = $i; $k < $this->inicioItems; $k++)
            $otrasColumnasCabecera[] = $this->keys[$k];

        if($this->ofeSectorSalud) // Columnas que deben existir en cabecera cuando el OFE aplica para Sector Salud
            $tempCabecera = array_merge($tempCabecera, ['futura_operacion_acreditacion', 'modalidades_contratacion_pago']);

        $diferencias = array_merge(
            array_diff($otrasColumnasCabecera, $tempCabecera),
            array_diff($tempCabecera, $otrasColumnasCabecera)
        );
        if (!empty($diferencias)) {
            return [
                'error' => true,
                'errores' => ['La estructura del archivo no se corresponde con la interfaz solicitada: sobran las columnas ' . implode(', ', $diferencias)]
            ];
        }

        $this->columnasOptativasCabecera = [];
        // Se itera cada valor esperado en las columnas opcionales
        foreach(MapaInformacionOpcionalExcel::$cabecera as $regex) {
            $porEliminar = [];
            // Para las columanas registradas se evalua una a una si cumple con el patron, de ser positivo es una columna opcional
            foreach($otrasColumnasCabecera as $key => $col) {
                if (preg_match('/^' . $regex . '$/', $col)) {
                    $porEliminar[] = $key;
                    $this->columnasOptativasCabecera[] = $col;
                }
            }

            // Se eliminan los items ya registrados para evitar sobreprocesamiento
            foreach($porEliminar as $key)
                unset($otrasColumnasCabecera[$key]);
        }

        // Lo que queda es la informcion Adicional
        $this->informacionAdicionalCabecera = array_values($otrasColumnasCabecera);

        $erroresDad = $this->validarGruposDad($this->columnasOptativasCabecera);
        if (count($erroresDad)) {
            return ['error' => true, 'errores' => $erroresDad];
        }

        /*
        * Verificando la estructura de columnas obligatorias en items.
        */
        $soloItems = [];
        for ($i = $this->inicioItems; $i < count($this->keys); $i++)
            $soloItems[] = $this->keys[$i];


        if ($this->tipo === ConstantsDataInput::DS || $this->tipo === ConstantsDataInput::DS_NC)
            $columnasObligatoriasItems = ColumnsExcel::$columnasObligatoriasItemsDS;
        else
            $columnasObligatoriasItems = ColumnsExcel::$columnasObligatoriasItems;

        if($this->ofeSectorSalud) // Columnas que deben existir en items cuando el OFE aplica para Sector Salud
            $columnasObligatoriasItems = array_merge($columnasObligatoriasItems, ['id_autorizacion_erp_eps']);

        $i = $this->inicioItems;
        foreach($columnasObligatoriasItems as $item) {
            if (!in_array($item, $soloItems))
                $faltantesObligatoriosItems[] = "Falta la Columna de Items: " . strtoupper(str_replace('_', ' ', $item));
            $i++;
        }

        $otrasColumnasItems = [];
        for ($k = $this->finItemsRequeridos + 1; $k < count($this->keys); $k++)
            $otrasColumnasItems[] = $this->keys[$k];

        $this->columnasOptativasItems = [];

        // Se itera cada valor esperado en las columnas opcionales
        foreach(MapaInformacionOpcionalExcel::$items as $regex) {
            $porEliminar = [];
            // Para las columanas registradas se evalua una a una si cumple con el patron, de ser positivo es una columna opcional
            foreach($otrasColumnasItems as $key => $col) {
                if (preg_match('/^' . $regex . '$/', $col)) {
                    $porEliminar[] = $key;
                    $this->columnasOptativasItems[] = $col;
                }
            }

            // Se eliminan los items ya registrados para evitar sobreprocesamiento
            foreach($porEliminar as $key)
                unset($otrasColumnasItems[$key]);
        }

        // Lo que queda es la informcion Adicional
        $this->informacionAdicionalItems = array_values($otrasColumnasItems);

        /* Las columnas de impuestos y rentenciones son de nombres variables por los que debemos evaluar en funcion
         * de expresiones regulares cuales de estas columnas correspondes a columnas opcionales de items y no a la
         * la información adicional de los items
         */
        $mutables = $this->determinarImpuestosRetenciones();
        $errores = [];
        $this->checkImpuestosRetenciones($errores);

        $faltasImpuestos =  $this->evaluarObjetosTributo($this->columnasOptativasItems, $this->listaImpuestos);
        $faltasReteneciones =  $this->evaluarObjetosTributo($this->columnasOptativasItems, $this->listaRetenciones, 'retencion');

        $errores = array_merge($faltantesObligatoriosCabecera, $faltantesOptativasCabecera, $inconsistenciasAdicionalCabecera,
            $faltantesObligatoriosItems, $faltantesOptativasItems, $inconsistenciasAdicionalitems, $faltasImpuestos, $faltasReteneciones, $errores);

        if (!empty($errores)) {
            return [
                'error' => true,
                'errores' => $errores
            ];
        }

        return ['error' => false];
    }

    /**
     * Determina si los impuestos que se han definido en el excel existen y estan activos en etl_openmain.
     *
     * @param array $errores
     */
    private function checkImpuestosRetenciones(array &$errores) {
        foreach($this->listaImpuestos as $impuesto) {
            if (array_key_exists(strtolower($impuesto), $this->impuestosRegistrados) == 0) {
                $errores[] = "El impuesto $impuesto no esta registrado";
            }
        }

        foreach($this->listaRetenciones as $retencion) {
            if (array_key_exists(strtolower($retencion), $this->impuestosRegistrados) == 0) {
                $errores[] = "Para la retención $retencion no esta registrada";
            }
        }
    }

    /**
     * Registra errores de guías de DHL Express cuando estan repetidas en el archivo de excel o cuando existen en el sistema.
     * 
     * @param string $archivo Nombre del archivo de Excel
     * @param string $erroresGuias Cadena que indica los errores presentados
     * @param collection $user Usuario autenticado
     */
    private function registrarErroresGuias($archivo, $erroresGuias, $user) {
        $errores[] = [
            'documento'           => $archivo,
            'consecutivo'         => '',
            'prefijo'             => '',
            'errors'              => [
                $erroresGuias
            ],
            'fecha_procesamiento' => date('Y-m-d'),
            'hora_procesamiento'  => date('H:i:s'),
            'archivo'             => $archivo,
            'traza'               => ''
        ];

        EtlProcesamientoJson::create([
            'pjj_tipo'                => 'EDI',
            'pjj_procesado'           => 'SI',
            'pjj_json'                => json_encode([]),
            'pjj_errores'             => json_encode($errores),
            'age_id'                  => 0,
            'age_estado_proceso_json' => 'FINALIZADO',
            'usuario_creacion'        => $user->usu_id,
            'estado'                  => 'ACTIVO'
        ]);
    }

    /**
     * Ejecuta el registro de la data.
     *
     * @param int $ofe_id ID del OFE que inició el procesamiento
     * @return string
     */
    private function procesador($ofe_id) {
        $documentos = [];

        // Obtiene el OFE
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_identificacion', 'ofe_prioridad_agendamiento'])
            ->where('ofe_id', $ofe_id)
            ->first();

        // Si el OFE es DHL Express se verifica si el archivo corresponde al proceso Pickup Cash
        // en donde todos los documentos deben ser FC, tener como representación gráfica la 9 y tener guia
        if($ofe->ofe_identificacion == self::NIT_DHLEXPRESS) {
            $user           = auth()->user();
            $listaGuias     = [];
            $guiasRepetidas = [];
            foreach($this->data as $k => $item) {
                if (
                    array_key_exists('guia', $item) && $item['guia'] != ''  && 
                    array_key_exists('representacion_grafica_documento', $item) && $item['representacion_grafica_documento'] == '9' &&
                    $this->tipo == ConstantsDataInput::FC
                ) {
                    if(!in_array($item['guia'], $listaGuias)) {
                        $listaGuias[] = $item['guia'];
                    } else {
                        $guiasRepetidas[] = $item['guia'];
                    }
                    
                    // En el proceso Pickup Cash los documentos llegan con el consecutivo vacio
                    // entonces se les debe asignar algún valor para que pase la validación,
                    // Posteriormente y antes de crear el documento en el sistema se les asigna
                    // el consecutivo definitivo
                    if (array_key_exists('consecutivo', $item) && $item['consecutivo'] == '')
                        $item['consecutivo'] = $k + 1;

                    // Agrupando la data por Guía
                    if (array_key_exists($item['guia'], $documentos))
                        $documentos[$item['guia']][] = ['data' => $item, 'linea' => $k + 2];
                    else
                        $documentos[$item['guia']] = [['data' => $item, 'linea' => $k + 2]];
                } else {
                    // Agrupando la data por documentos
                    if (array_key_exists('prefijo', $item) && array_key_exists('consecutivo', $item)) {
                        $key = $item['prefijo'] . $item['consecutivo'];
                        if (array_key_exists($key, $documentos))
                            $documentos[$key][] = ['data' => $item, 'linea' => $k + 2];
                        else
                            $documentos[$key] = [['data' => $item, 'linea' => $k + 2]];
                    }
                }
            }

            if(!empty($guiasRepetidas)) {
                $this->registrarErroresGuias($this->archivoExcel, 'El archivo no se procesó. Las siguientes guías estan repetidas: ' . implode(', ', $guiasRepetidas), $user);
                $message = "El archivo no se procesó.<br>Las siguientes guías estan repetidas:<br><br>" . implode(', ', $guiasRepetidas);
                return $message;
            } else {
                $periodos         = [];
                $erroresExisten   = [];
                $particionamiento = $user->getBaseDatos->bdd_aplica_particionamiento_emision;
                if($particionamiento == 'SI')
                    $periodos = $this->generarPeriodosParticionamiento(
                        Carbon::now()->subMonths(6)->format('Y-m-d'),
                        Carbon::now()->subMonth()->format('Y-m-d')
                    );

                $fecha = Carbon::now()->subMonths(6)->format('Y-m-d H:i:s');
                foreach($listaGuias as $guia) {
                    // Si no hay guías repetidas se debe verificar que cada guía en el archivo de excel NO exista para otro documento
                    // en el sistema, y si existe se debe verificar que el otro documento haya sido cargado hace más de seis meses
                    EtlDatosAdicionalesDocumentoDaop::select(['dad_id', 'cdo_id'])
                        ->where(function($query) use ($guia) {
                            $query->where('cdo_informacion_adicional->guia', $guia)
                                ->orWhere('cdo_informacion_adicional', 'like', '%"guia": "' . $guia . '"%')
                                ->orWhere('cdo_informacion_adicional', 'like', '%"guia":"' . $guia . '"%');
                        })
                        ->with([
                            'getCabeceraDocumentosDaop:cdo_id,rfa_prefijo,cdo_consecutivo,fecha_creacion,estado'
                        ])
                        ->get()
                        ->map(function ($documentoGuia) use ($guia, $fecha, &$erroresExisten) {
                            if(
                                $documentoGuia->getCabeceraDocumentosDaop->fecha_creacion->gt($fecha) &&
                                (
                                    $documentoGuia->getCabeceraDocumentosDaop->estado == 'ACTIVO' ||
                                    $documentoGuia->getCabeceraDocumentosDaop->estado == 'PROVISIONAL'
                                )
                            ) {
                                $erroresExisten[] = 'Guía [' . $guia . '] existe en el sistema para el documento [' . $documentoGuia->getCabeceraDocumentosDaop->rfa_prefijo . $documentoGuia->getCabeceraDocumentosDaop->cdo_consecutivo .'], el estado del documento es [ACTIVO], fecha de creación [' . $documentoGuia->getCabeceraDocumentosDaop->fecha_creacion . ']';
                            }
                        });

                    // Se debe realizar la misma busqueda en el histórico de los últimos 6 meses (6 últimas particiones), siempre que la base de datos tenga habilitado el particionamiento
                    if($particionamiento == 'SI' && !empty($periodos)) {
                        foreach($periodos as $periodo) {
                            $tablaDocs             = 'etl_cabecera_documentos_' . $periodo;          
                            $tablaDatosAdicionales = 'etl_datos_adicionales_documentos_' . $periodo;   
                            
                            if(!$this->helpers->existeTabla($this->connection, $tablaDocs) || !$this->helpers->existeTabla($this->connection, $tablaDatosAdicionales))
                                continue;
                            
                            DB::connection($this->connection)
                                ->table($tablaDatosAdicionales)
                                ->select([
                                    $tablaDatosAdicionales . '.dad_id',
                                    $tablaDatosAdicionales . '.cdo_id',

                                    $tablaDocs . '.rfa_prefijo as getCabeceraDocumentosDaop_rfa_prefijo',
                                    $tablaDocs . '.cdo_consecutivo as getCabeceraDocumentosDaop_cdo_consecutivo',
                                    $tablaDocs . '.fecha_creacion as getCabeceraDocumentosDaop_fecha_creacion',
                                    $tablaDocs . '.estado as getCabeceraDocumentosDaop_estado'
                                ])
                                ->leftJoin($tablaDocs, $tablaDatosAdicionales . '.cdo_id', '=', $tablaDocs . '.cdo_id')
                                ->where(function($query) use ($guia) {
                                    $query->where('cdo_informacion_adicional->guia', $guia)
                                        ->orWhere('cdo_informacion_adicional', 'like', '%"guia": "' . $guia . '"%')
                                        ->orWhere('cdo_informacion_adicional', 'like', '%"guia":"' . $guia . '"%');
                                })
                                ->get()
                                ->map(function ($documentoGuia) use ($guia, $fecha, &$erroresExisten) {
                                    if(
                                        Carbon::parse($documentoGuia->getCabeceraDocumentosDaop_fecha_creacion)->gt($fecha) &&
                                        (
                                            $documentoGuia->getCabeceraDocumentosDaop_estado == 'ACTIVO' ||
                                            $documentoGuia->getCabeceraDocumentosDaop_estado == 'PROVISIONAL'
                                        )
                                    ) {
                                        $erroresExisten[] = 'Guía [' . $guia . '] existe en el sistema para el documento [' . $documentoGuia->getCabeceraDocumentosDaop_rfa_prefijo . $documentoGuia->getCabeceraDocumentosDaop_cdo_consecutivo .'], el estado del documento es [ACTIVO], fecha de creación [' . $documentoGuia->getCabeceraDocumentosDaop_fecha_creacion . ']';
                                    }
                                });
                        }
                    }
                }

                if(!empty($erroresExisten)) {
                    $this->registrarErroresGuias($this->archivoExcel, 'Error: ' . implode(' // ', $erroresExisten), $user);
                    $message = "El archivo no se procesó. Se encontraron guías que existen en el sistema, verifique el log de errores";
                    return $message;
                }
            }
        } else {
            foreach($this->data as $k => $item) {
                // Agrupando la data por documentos
                if (array_key_exists('prefijo', $item) && array_key_exists('consecutivo', $item)) {
                    $key = $item['prefijo'] . $item['consecutivo'];
                    if (array_key_exists($key, $documentos))
                        $documentos[$key][] = ['data' => $item, 'linea' => $k + 2];
                    else
                        $documentos[$key] = [['data' => $item, 'linea' => $k + 2]];
                }
            }
        }

        // Verificando cada documento
        foreach($documentos as $key => $doc) {
            $response = $this->validarDocumento($doc);
            if ($response['error'])
                $this->documentosConFallas[$key] = $response;
            else {
                if (array_key_exists('tipo_documento', $response['cabecera']['obligatorias']) &&
                    in_array($response['cabecera']['obligatorias']['tipo_documento'], ConstantsDataInput::codigosValidos ))
                    $this->documentosParaAgendamiento[$key] = $response;
                else {
                    $this->documentosConFallas[$key] = [
                        'error' => true,
                        'errores' => ['No se puede determinar el tipo de ' . $response['cabecera']['obligatorias']['prefijo'] . $response['cabecera']['obligatorias']['consecutivo'] . 'El tipo de operación no es valido.']
                    ];
                }
            }
        }

        if (count($this->documentosParaAgendamiento) > 0) {
            $user = auth()->user();

            // Divide los documentos por bloques de acuerdo al máximo permitido en la BD para EDI
            $grupos = array_chunk($this->documentosParaAgendamiento, $user->getBaseDatos->bdd_cantidad_procesamiento_edi);
            foreach($grupos as $grupo) {
                $jsonBuilder = new JsonEtlBuilder($this->tipo);
                foreach($grupo as $doc) {
                    if($this->ofeSectorSalud)
                        $this->agregarInfoSectorSalud($doc);

                    $jsonDoc = new JsonDocumentBuilder($doc['cabecera'], $doc['items'], $this->listaImpuestos, $this->listaRetenciones, $this->impuestosRegistrados, null, 'manual', (array_key_exists('sector_salud', $doc) ? $doc['sector_salud'] : []));
                    $jsonDoc->setColumnasOptativasCabecera($this->columnasOptativasCabecera)
                        ->setColumnasInformacionAdicionalCabecera($this->informacionAdicionalCabecera)
                        ->setColumnasInformacionAdicionalItems($this->informacionAdicionalItems)
                        ->setColumnasOptativasItems($this->columnasOptativasItems);
                    $jsonBuilder->addDocument($jsonDoc);
                }
                $json = $jsonBuilder->build();

                // Crea el agendamiento en el sistema
                $agendamiento = AdoAgendamiento::create([
                    'usu_id'                  => $user->usu_id,
                    'bdd_id'                  => $user->bdd_id,
                    'age_proceso'             => ($ofe->ofe_identificacion == self::NIT_DHLEXPRESS) ? 'EDIEXPRESS' : 'EDI',
                    'age_cantidad_documentos' => count($grupo),
                    'age_prioridad'           => !empty($ofe->ofe_prioridad_agendamiento) ? $ofe->ofe_prioridad_agendamiento : null,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO'
                ]);

                // Graba la información del Json en la tabla de programacion de procesamientos de Json
                $programacion = EtlProcesamientoJson::create([
                    'pjj_tipo'                => 'EDI',
                    'pjj_json'                => $json,
                    'pjj_procesado'           => 'NO',
                    'age_id'                  => $agendamiento->age_id,
                    'age_estado_proceso_json' => null,
                    'usuario_creacion'        => $user->usu_id,
                    'estado'                  => 'ACTIVO',
                ]);
            }

            $N = count($this->documentosParaAgendamiento);
            if ($N === 1)
                $message = "Se ha agendado 1 documento para su procesamiento en background";
            else
                $message = "Se ha agendado $N documentos para su procesamiento en background";
        } else
            $message = "No se han agendado documentos";

        return $message;
    }

    /**
     * Construye los cargos extendidos y ajusta el array de $opcionales en caso de ser necesario.
     *
     * @param array $opcionales Cargos opcionales
     * @param array $patrones   Patrones de validación para los cargos
     * @param array $extendidos Cargos extendidos
     */
    private function getCargosExtendidos(array &$opcionales, array $patrones, array &$extendidos) {
        $search = ['cargo_', '_descripcion', '_porcentaje', '_base_moneda_extranjera', '_valor_moneda_extranjera', '_base', '_valor'];
        $replace = ['', '', '', '', '', '', ''];

        foreach($opcionales as $key => $item) {
            foreach($patrones as $regex) {
                if (preg_match('/^' . $regex . '$/', $key)) {
                    $descuento = str_replace($search, $replace, $key);
                    if (!array_key_exists($descuento, $extendidos))
                        $extendidos[$descuento] = [];
                    $temp = $extendidos[$descuento];
                    $temp[$key] = $item;
                    $extendidos[$descuento] = $temp;
                    unset($opcionales[$key]);
                }
            }
        }
    }

    /**
     * Construye los descuentos extendidos y ajusta el array de $opcionales en caso de ser necesario.
     *
     * @param array $opcionales Descuentos opcionales
     * @param array $patrones   Patrones de validación para los descuentos
     * @param array $extendidos Descuentos extendidos
     */
    private function getDescuentosExtendidos(array &$opcionales, array $patrones, array &$extendidos) {
        $search = ['descuento_', '_codigo', '_descripcion', '_porcentaje', '_base_moneda_extranjera', '_valor_moneda_extranjera', '_base', '_valor'];
        $replace = ['', '', '', '', '', '', '', ''];

        foreach($opcionales as $key => $item) {
            foreach($patrones as $regex) {
                if (preg_match('/^' . $regex . '$/', $key)) {
                    $cargo = str_replace($search, $replace, $key);
                    if (!array_key_exists($cargo, $extendidos))
                        $extendidos[$cargo] = [];
                    $temp = $extendidos[$cargo];
                    $temp[$key] = $item;
                    $extendidos[$cargo] = $temp;
                    unset($opcionales[$key]);
                }
            }
        }
    }

    /**
     * Evalua que cada fila del excel correspondiente a un documento contenga información consistente entre si.
     * 
     * @param array $documento Información del documento
     * @return array
     */
    private function validarDocumento(array $documento) {
        $errores = [];
        $cabecera = [];

        /*
         * Inicializando el registro de cabecera para verificar la consistencia del mismo con cada una de las demas filas
         * (Si las hay)
         */
        if ($this->tipo === ConstantsDataInput::FC) {
            $columnasCabecera = ColumnsExcel::$columnasObligatoriasCabeceraFC;
        } elseif ($this->tipo === ConstantsDataInput::DS) {
            $columnasCabecera = array_replace(ColumnsExcel::$columnasObligatoriasCabeceraFC, array(2 => "nit_receptor", 3 => "nit_vendedor"));
        } elseif ($this->tipo === ConstantsDataInput::DS_NC) {
            $columnasCabecera = array_replace(ColumnsExcel::$columnasObligatoriasCabeceraNCND, array(2 => "nit_receptor", 3 => "nit_vendedor"));
        } else {
            $columnasCabecera = ColumnsExcel::$columnasObligatoriasCabeceraNCND;
        }

        $__registro = $documento[0]['data'];
        $fila = $documento[0]['linea'];
        foreach($columnasCabecera as $col)
            $cabecera[$col] = trim($__registro[$col]);

        $validador = Validator::make($__registro, $this->rules);
        $fallas = $validador->errors()->all();
        if (count($fallas) > 0) {
            foreach($fallas as $reg) {
                $errores[] = "$reg en la fila [{$fila}]";
            }
        }

        $respuesta = $this->validarDocumentosAdicionales($__registro, $fila);
        if ($respuesta['error'])
            $errores = array_merge($errores, $respuesta['errores']);

        // Si hay mas de un item validamos que los datos de cabecera sean iguales en todos
        if (count($documento) > 0) {
            for ($i = 1; $i < count($documento); $i++) {
                $__registro = $documento[$i]['data'];
                $fila = $documento[$i]['linea'];
                foreach($columnasCabecera as $col) {
                    if ($__registro[$col] !== $cabecera[$col])
                        $errores[] = "El registro $i presentan inconsistencias en columna de cabecera {$this->nombresReales[$col]} de la fila $fila";
                }

                $validador = Validator::make($__registro, $this->rules);
                $fallas = $validador->errors()->all();
                if (count($fallas) > 0) {
                    foreach($fallas as $reg) {
                        $errores[] = "$reg en la fila [{$fila}]";
                    }
                }

                $respuesta = $this->validarDocumentosAdicionales($__registro, $fila);
                if ($respuesta['error'])
                    $errores = array_merge($errores, $respuesta['errores']);
            }
        }

        if ($this->tipo === ConstantsDataInput::FC) {
            if ($cabecera['tipo_documento'] !== '01' && $cabecera['tipo_documento'] !== '02' && $cabecera['tipo_documento'] !== '03' && $cabecera['tipo_documento'] !== '04') {
                $fila = $documento[0]['linea'];
                $errores[] = "EL tipo de documento no se corresponde para algun tipo de factura en la fila [{$fila}]";
            }
        } elseif ($this->tipo === ConstantsDataInput::DS) {
            if ($cabecera['tipo_documento'] !== '05') {
                $fila = $documento[0]['linea'];
                $errores[] = "EL tipo de documento no se corresponde para algun tipo de documento soporte en la fila [{$fila}]";
            }
        } elseif ($this->tipo === ConstantsDataInput::DS_NC) {
            if ($cabecera['tipo_documento'] !== '95') {
                $fila = $documento[0]['linea'];
                $errores[] = "EL tipo de documento no se corresponde para algun tipo de nota crédito documento soporte en la fila [{$fila}]";
            }
        } else {
            if ($cabecera['tipo_documento'] !== '91' && $cabecera['tipo_documento'] !== '92') {
                $fila = $documento[0]['linea'];
                $errores[] = "EL tipo de documento no se corresponde para notas credito o notas debito en la fila [{$fila}]";
            }
        }

        $respuesta = [
            'error'   => count($errores) > 0,
            'errores' => $errores
        ];

        if (count($errores) === 0) {
            $optativosCabecera   = [];
            $adicionalesCabecera = [];
            foreach($this->columnasOptativasCabecera as $col) {
                if (array_key_exists($col, $__registro))
                    $optativosCabecera[$col] = $__registro[$col];
            }

            foreach($this->informacionAdicionalCabecera as $col) {
                if (array_key_exists($col, $__registro))
                    $adicionalesCabecera[$col] = $__registro[$col];
            }

            if (array_key_exists('redondeo' ,$optativosCabecera))
                $cabecera['cdo_redondeo'] = $optativosCabecera['redondeo'];

            if (array_key_exists('redondeo_moneda_extranjera' ,$optativosCabecera))
                $cabecera['cdo_redondeo_moneda_extranjera'] = $optativosCabecera['redondeo_moneda_extranjera'];

            $cargosExtendidos = [];
            $patrones = [
                'cargo_([a-zA-Z0-9_]{1,255})?',
                'cargo_[a-zA-Z0-9_]{1,255}_porcentaje',
                'cargo_[a-zA-Z0-9_]{1,255}_base',
                'cargo_[a-zA-Z0-9_]{1,255}_valor',
                'cargo_[a-zA-Z0-9_]{1,255}_base_moneda_extranjera',
                'cargo_[a-zA-Z0-9_]{1,255}_valor_moneda_extranjera'
            ];
            $this->getCargosExtendidos($optativosCabecera, $patrones, $cargosExtendidos);

            $descuentosExtendidos = [];
            $patrones = [
                'descuento_[a-zA-Z0-9_]{1,255}_codigo',
                'descuento_([a-zA-Z0-9_]{1,255})?',
                'descuento_[a-zA-Z0-9_]{1,255}_porcentaje',
                'descuento_[a-zA-Z0-9_]{1,255}_base',
                'descuento_[a-zA-Z0-9_]{1,255}_valor',
                'descuento_[a-zA-Z0-9_]{1,255}_base_moneda_extranjera',
                'descuento_[a-zA-Z0-9_]{1,255}_valor_moneda_extranjera'
            ];
            $this->getDescuentosExtendidos($optativosCabecera, $patrones, $descuentosExtendidos);

            $respuesta['cabecera'] = [
                'obligatorias'          => $cabecera,
                'opcionales'            => $optativosCabecera,
                'cargos_extendido'      => $cargosExtendidos,
                'descuentos_extendido'  => $descuentosExtendidos,
                'informacion_adicional' => $adicionalesCabecera
            ];

            if ($this->tipo === ConstantsDataInput::DS || $this->tipo === ConstantsDataInput::DS_NC)
                $columnasObligatoriasItems = ColumnsExcel::$columnasObligatoriasItemsDS;
            else
                $columnasObligatoriasItems = ColumnsExcel::$columnasObligatoriasItems;

            $items = [];
            foreach($documento as $item) {
                $__registro = $item['data'];
                $opcionalesItems = [];
                $adicionalesItems = [];
                $datos = [];
                foreach($columnasObligatoriasItems as $col)
                    $datos[$col] = $__registro[$col];

                foreach($this->columnasOptativasItems as $col)
                    $opcionalesItems[$col] = $__registro[$col];

                foreach($this->informacionAdicionalItems as $col)
                    $adicionalesItems[$col] = $__registro[$col];

                $cargosExtendidos = [];
                $patrones = [
                    'cargo_([a-zA-Z0-9_]{1,255})?',
                    'cargo_[a-zA-Z0-9_]{1,255}_porcentaje',
                    'cargo_[a-zA-Z0-9_]{1,255}_base',
                    'cargo_[a-zA-Z0-9_]{1,255}_valor',
                    'cargo_[a-zA-Z0-9_]{1,255}_base_moneda_extranjera',
                    'cargo_[a-zA-Z0-9_]{1,255}_valor_moneda_extranjera'
                ];
                $this->getCargosExtendidos($opcionalesItems, $patrones, $cargosExtendidos);

                $descuentosExtendidos = [];
                $patrones = [
                    'descuento_[a-zA-Z0-9_]{1,255}_codigo',
                    'descuento_([a-zA-Z0-9_]{1,255})?',
                    'descuento_[a-zA-Z0-9_]{1,255}_porcentaje',
                    'descuento_[a-zA-Z0-9_]{1,255}_base',
                    'descuento_[a-zA-Z0-9_]{1,255}_valor',
                    'descuento_[a-zA-Z0-9_]{1,255}_base_moneda_extranjera',
                    'descuento_[a-zA-Z0-9_]{1,255}_valor_moneda_extranjera'
                ];
                $this->getDescuentosExtendidos($opcionalesItems, $patrones, $descuentosExtendidos);

                $items[] = [
                    'obligatorias'          => $datos,
                    'opcionales'            => $opcionalesItems,
                    'cargos_extendido'      => $cargosExtendidos,
                    'descuentos_extendido'  => $descuentosExtendidos,
                    'informacion_adicional' => $adicionalesItems,
                    'excel_general'         => true
                ];
            }

            $respuesta['items'] = $items;
        }
        
        return $respuesta;
    }

    /**
     * Evalua la existencia de un grupo de datos dentro de DAD
     *
     * @param array $conjunto
     * @param array $grupo
     * @param array $optativas
     * @return array
     */
    private function evaluarGrupo(array $conjunto, array $grupo, array $optativas = []) {
        $contador = 0;
        $noexisten = [];
        foreach($grupo as $item) {
            if (in_array($item, $conjunto))
                $contador++;
            else
                $noexisten[] = $item;
        }
        if (!empty($noexisten))
            $noexisten = array_diff($optativas, $noexisten);

        // Si hay algo en el grupo pero no son todos los elementos
        if ($contador > 0 && $contador < count($grupo))
            return [
                'error' => true,
                'faltantes' => $noexisten
            ];
        return ['error' => false];
    }

    /**
     * Registra los campos faltantes para indicar un error en el formato del archivo.
     *
     * @param $errores
     * @param $nombreSeccion
     * @param $respuesta
     */
    private function registrarFallasFaltantes(&$errores, $nombreSeccion, $respuesta) {
        if ($respuesta['error']) {
            if (count($respuesta['faltantes']) > 0) {
                $nombres = [];
                foreach($respuesta['faltantes'] as $item)
                    $nombres[] = strtoupper(str_replace('_', ' ', $item));
                $campos = implode(',', $nombres);
                $errores[] = "En la sección $nombreSeccion faltan las columnas: $campos";
            }
        }
    }

    /**
     * @param $opcionales
     * @return array
     */
    private function validarGruposDad(array $opcionales) {
        $errores = [];

        // cdo_periodo_facturacion
        $grupo = [
            'fecha_inicio_periodo',
            'hora_inicio_periodo',
            'fecha_fin_periodo',
            'hora_fin_periodo'
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Periodo de Facturación', $response);

        // dad_orden_referencia
        $grupo = [
            'orden_referencia',
            'fecha_emision_orden_referencia'
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Orden Referencia', $response);

        // dad_despacho_referencia
        $grupo = [
            'despacho_referencia',
            'fecha_emision_despacho_referencia'
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Despacho Referencia', $response);

        // dad_recepcion_referencia
        $grupo = [
            'recepcion_referencia',
            'fecha_emision_recpcion_referecia'
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Recepción Referencia', $response);

        // dad_condiciones_entrega
        $grupo = [
            'entrega_bienes_fecha_salida',
            'entrega_bienes_hora_salida',
            'entrega_bienes_cod_pais',
            'entrega_bienes_cod_departamento',
            'entrega_bienes_cod_ciudad_municipio',
            'entrega_bienes_cod_postal',
            'entrega_bienes_direccion',
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Condiciones Entrega', $response);

        // dad_entrega_bienes_despacho
        $grupo = [
            'entrega_bienes_despacho_identificacion_transportador',
            'entrega_bienes_despacho_identificacion_transporte',
            'entrega_bienes_despacho_tipo_transporte',
            'entrega_bienes_despacho_fecha_solicitada',
            'entrega_bienes_despacho_hora_solicitada',
            'entrega_bienes_despacho_fecha_estimada',
            'entrega_bienes_despacho_hora_estimada',
            'entrega_bienes_despacho_fecha_real',
            'entrega_bienes_despacho_hora_real',
            'entrega_bienes_despacho_cod_pais',
            'entrega_bienes_despacho_cod_departamento',
            'entrega_bienes_despacho_cod_ciudad_municipio',
            'entrega_bienes_despacho_cod_postal',
            'entrega_bienes_despacho_direccion'
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Entrega Bienes Despacho', $response);

        // dad_terminos_entrega
        $grupo = [
            'terminos_entrega_condiciones_pago',
            'terminos_entrega_cod_condicion_entrega_incoterms',
            'terminos_entrega_cod_pais',
            'terminos_entrega_cod_departamento',
            'terminos_entrega_cod_ciudad_municipio',
            'terminos_entrega_cod_postal_cod_postal',
            'terminos_entrega_direccion_terminos'
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Terminos de Entrega', $response);

        // cdo_detalle_anticipos
        $grupo = [
            'anticipo_identificado_pago',
            'anticipo_valor',
            'anticipo_valor_moneda_extranjera',
            'anticipo_fecha_recibio',
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Detalles de Anticipo', $response);

        // cdo_cargos
        $grupo = [
            'cdo_cargo_descripcion',
            'cdo_cargo_porcentaje',
            'cdo_cargo_base',
            'cdo_cargo_valor',
            'cdo_cargo_base_moneda_extranjera',
            'cdo_cargo_valor_moneda_extranjera',
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Detalles de Cargo', $response);

        // cdo_descuentos
        $grupo = [
            'cdo_descuento_codigo',
            'cdo_descuento_descripcion',
            'cdo_descuento_porcentaje',
            'cdo_descuento_base',
            'cdo_descuento_valor',
            'cdo_descuento_base_moneda_extranjera',
            'cdo_descuento_valor_moneda_extranjera',
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Detalles de Descuento', $response);

        return $errores;
    }

    /**
     * Valida que los campos opcionales de items esten bien formados
     *
     * @param array $opcionales
     * @return array
     */
    private function validarCamposOptativosItems(array $opcionales) {
        $errores = [];

        $grupo = [
            'marca',
            'modelo',
            'cod_vendedor',
            'cod_vendedor_subespecificacion',
            'cod_fabricante',
            'cod_fabricante_subespecificacion',
            'nombre_fabricante',
            'nombre_clasificacion_producto',
            'cod_pais_de_origen',
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Datos de producto', $response);

        // cdo_cargos
        $grupo = [
            'ddo_cargo_descripcion',
            'ddo_cargo_porcentaje',
            'ddo_cargo_base',
            'ddo_cargo_valor',
            'ddo_cargo_base_moneda_extranjera',
            'ddo_cargo_valor_moneda_extranjera',
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Detalles de Cargo', $response);

        // ddo_descuentos
        $grupo = [
            'ddo_descuento_codigo',
            'ddo_descuento_descripcion',
            'ddo_descuento_porcentaje',
            'ddo_descuento_base',
            'ddo_descuento_valor',
            'ddo_descuento_base_moneda_extranjera',
            'ddo_descuento_valor_moneda_extranjera',
        ];
        $response = $this->evaluarGrupo($opcionales, $grupo);
        $this->registrarFallasFaltantes($errores, 'Detalles de Descuento', $response);

        return $errores;
    }

    /**
     * Filtra los registros de Sector Salud que corresponden al documento que esta siendo procesado.
     *
     * @param array $doc Documento en procesamiento
     * @param array $arrFiltrar Array de información de Sector Salud que debe ser filtrado
     * @return array Array con información filtrada
     */
    private function filtrarRegistrosSectorSaludDocumento($doc, $arrFiltrar) {
        return array_filter($arrFiltrar, function($registro) use ($doc) {
            if($this->tipo == ConstantsDataInput::NC || $this->tipo == ConstantsDataInput::ND || $this->tipo == ConstantsDataInput::NC_ND)
                return $registro['tipo_documento_cabecera'] == $doc['cabecera']['obligatorias']['tipo_documento'] &&
                    $registro['tipo_operacion_cabecera'] == $doc['cabecera']['obligatorias']['tipo_operacion'] &&
                    $registro['prefijo_cabecera'] == $doc['cabecera']['obligatorias']['prefijo'] &&
                    $registro['consecutivo_cabecera'] == $doc['cabecera']['obligatorias']['consecutivo'];
            else
                return $registro['tipo_documento_cabecera'] == $doc['cabecera']['obligatorias']['tipo_documento'] &&
                    $registro['tipo_operacion_cabecera'] == $doc['cabecera']['obligatorias']['tipo_operacion'] &&
                    $registro['resolucion_facturacion_cabecera'] == $doc['cabecera']['obligatorias']['resolucion_facturacion'] &&
                    $registro['prefijo_cabecera'] == $doc['cabecera']['obligatorias']['prefijo'] &&
                    $registro['consecutivo_cabecera'] == $doc['cabecera']['obligatorias']['consecutivo'];
        });
    }

    /**
     * Agrega la información de Sector Salud al documento que esta siendo procesado.
     *
     * @param array $doc Documento en procesamiento
     * @return void
     */
    private function agregarInfoSectorSalud(&$doc) {
        $doc['sector_salud'] = [];

        $filasDoc = $this->filtrarRegistrosSectorSaludDocumento($doc, $this->arrExtensionSectorSalud);
        $doc['sector_salud']['extension_sector_salud'] = $filasDoc;
        
        $filasDoc = $this->filtrarRegistrosSectorSaludDocumento($doc, $this->arrReferenciaAdquirenteSectorSalud);
        $doc['sector_salud']['referencia_adquirente'] = $filasDoc;

        $filasDoc = $this->filtrarRegistrosSectorSaludDocumento($doc, $this->arrDocumentoReferenciaLineaSectorSalud);
        $doc['sector_salud']['documento_referencia_linea'] = $filasDoc;

        $filasDoc = $this->filtrarRegistrosSectorSaludDocumento($doc, $this->arrDocumentoAdicionalSectorSalud);
        $doc['sector_salud']['documento_adicional'] = $filasDoc;

        $filasDoc = $this->filtrarRegistrosSectorSaludDocumento($doc, $this->arrDocumentoReferenciaSectorSalud);
        $doc['sector_salud']['documento_referencia'] = $filasDoc;
    }
}