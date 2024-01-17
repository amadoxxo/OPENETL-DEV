<?php

namespace App\Http\Modulos\DataInputWorker;

use Validator;
use Carbon\Carbon;
use App\Traits\DiTrait;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\DataInputWorker\helpers;
use App\Http\Modulos\DataInputWorker\Utils\VP;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\DataInputWorker\ConstantsDataInput;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop\EtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Gestor primario en los procesos de validación de data a insertar de documentos electronicos.
 *
 * Class DataInputValidator
 * @package App\Http\Modulos\DataInputWorker
 */
class DataInputValidator {
    use DiTrait, PackageMainTrait;

    /**
     * Instancia de la clase helpers del DataInputWorker
     *
     * @var helpers
     */
    protected $helpers;

    // Identificación de DHL Express
    public const NIT_DHLEXPRESS = '860502609';

    // Origen Manual para documentos
    public const ORIGEN_MANUAL  = 'MANUAL';

    /*
     * Secciones del JSON
     */
    public const VALIDATE_HEADER_DOCUMENTO                         = 'validar-cabecera-documento';
    public const VALIDATE_DAD_DOCUMENTO                            = 'validar-datos-adicionales-documento';
    public const VALIDATE_MEDIOS_PAGO_DOCUMENTO                    = 'validar-medios-pago-documento';
    public const VALIDATE_DETALLES_ANTICIPOS_DOCUMENTO             = 'validar-detalles-anticipo-documento';
    public const VALIDATE_DETALLES_RETENCIONES_SUGERIDAS_DOCUMENTO = 'validar-retenciones-sugeridas-documento';
    public const VALIDATE_DESCUENTOS_DOCUMENTO                     = 'validar-descuentos-documento';
    public const VALIDATE_CARGOS_DOCUMENTO                         = 'validar-cargos-documento';
    public const VALIDATE_ITEMS_DOCUMENTO                          = 'validar-items-documento';
    public const VALIDATE_TRIBUTOS_DOCUMENTO                       = 'validar-tributos-documento';
    public const VALIDATE_COLUMNAS_PERSONALIZADAS                  = 'validar-columnas-personalizadas';

    /*
     * Tipos de adquirentes
     */
    public const TIPO_ADQUIRENTE                 = 'adquirente-documento';
    public const TIPO_AUTORIZADO                 = 'autorizado-documento';
    public const TIPO_RESPONSABLE_ENTREGA        = 'responsable de entrega';
    public const TIPO_VENDEDOR_DOCUMENTO_SOPORTE = 'vendedor documento soporte';

    /**
     * Constante que permite relacionar los tipos de operación del sector salud
     */
    public const SECTOR_SALUD = 'SECTOR_SALUD';

    /**
     * Constante que permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     */
    public const OPENETL_WEB = 'OPENETL-WEB';

    /**
     * Tipo de documento.
     * 
     * @var string
     */
    private $docType;

    /**
     * Descripción Tipo de documento
     * 
     * @var string
     */
    private $descriptionDocType;

    /**
     * HashMap que almacena las monedas consultas para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $monedas = [];

    /**
     * HashMap que almacena los oferentes consultados para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $oferentes = [];

    /**
     * HashMap que almacena los adquirentes consultadas para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $adquirentes = [];

    /**
     * HashMap que almacena los paises consultados para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $paises = [];

    /**
     * HashMap que almacena los departamentos consultados para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $departamentos = [];

    /**
     * HashMap que almacena los municipios consultados para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $municipios = [];

    /**
     * HashMap que almacena los tipos de documentos electronicos.
     *
     * @var array
     */
    private $tipoDocumentosElectronico = [];

    /**
     * HashMap que almacena los tipos de documentos.
     *
     * @var array
     */
    private $tipoDocumentos = [];

    /**
     * HashMap que almacena los tipos de operaciones.
     *
     * @var array
     */
    private $tipoOperaciones = [];

    /**
     * HashMap que almacena las resoluciones consultadas para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $resoluciones = [];

    /**
     * HashMap que almacena las condiciones de entrega consultadas para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $condicionesEntrega = [];

    /**
     * HashMap que almacena los medios de pago consultados para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $mediosPago = [];

    /**
     * HashMap que almacena las formas de pago consultadas para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $formasPago = [];

    /**
     * HashMap que almacena los descuentos para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $codigosDescuentos = [];

    /**
     * HashMap que almacena los conceptos de correción para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $conceptosCorreccion = [];

    /**
     * HashMap que almacena los tributos para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $tributos = [];

    /**
     * HashMap que almacena los códigos postales para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $codigosPostales = [];

    /**
     * HashMap que almacena la clasificacion de productos para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $clasificacionProductos = [];

    /**
     * HashMap que almacena las unidades para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $unidades = [];

    /**
     * HashMap que almacena los precios de referencia para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $preciosReferencia = [];

    /**
     * HashMap que almacena las formas de generación y transmisión para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $formaGeneracionTransmision = [];

    /**
     * HashMap que almacena los mandatos para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $mandatos = [];

    /**
     * HashMap que almacena los registros de transporte registro para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $transporteRegistro = [];

    /**
     * HashMap que almacena los registros de transporte remesa para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $transporteRemesa = [];

    /**
     * HashMap que almacena los registros de documentos manuales cargos para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $facturacionWebCargos = [];

    /**
     * HashMap que almacena los registros de documentos manuales descuentos para evitar hacer búsquedas duplicadas.
     *
     * @var array
     */
    private $facturacionWebDescuentos = [];

    /**
     * Contiene los ddo_secuencia documento.
     *
     * @var array
     */
    private $ddo_secuencia = [];

    /**
     * Contiene el total a pagar del documento en monenda nacional.
     * @var float
     */
    private $cdo_valor_a_pagar = 0.0;

    /**
     * Contiene el total a pagar del documento en moneda extranjera.
     *
     * @var float
     */
    private $cdo_valor_a_pagar_moneda_extranjera = 0.0;

    /**
     * HashMaps con las reglas de validacion para cada sección y sub-secciones del JSON
     */
    private $rulesValidatorCabecera           = [];
    private $rulesCdoConceptosCorreccion      = [];
    private $rulesCdoDocumentoAdicional       = [];
    private $rulesCdoPeriodoFacturacion       = [];
    private $rulesResolucionFacturacion       = [];
    private $rulesDadReferencia               = [];
    private $rulesDadCondicionesEntrega       = [];
    private $rulesDadEntregaBienesDespacho    = [];
    private $rulesDadTerminosEntrega          = [];
    private $rulesDadMonedaAlternativa        = [];
    private $rulesDadInteroperabilidad        = [];
    private $rulesDadDocumentoReferenciaLinea = [];
    private $rulesDadReferenciaAdquirente     = [];
    private $rulesCdoDocumentoReferencia      = [];
    private $rulesDetalleDescuentos           = [];
    private $rulesValores                     = [];
    private $rulesMediosPago                  = [];
    private $rulesMediosPagoIds               = [];
    private $rulesDetalleAnticipos            = [];
    private $rulesCargos                      = [];
    private $rulesDescuentos                  = [];
    private $rulesRetencionesSugeridas        = [];
    private $rulesTributos                    = [];
    private $rulesItems                       = [];
    private $rulesDdoPrecioReferencia         = [];
    private $rulesDdoFechaCompras             = [];
    private $rulesDdoIdentificacionComprador  = [];
    private $rulesDatosTecnicos               = [];
    private $rulesImpuestoPorcentaje          = [];
    private $rulesImpuestoUnidad              = [];

    private $retencionesSugeridasPermitidas = ['reteiva', 'retefuente', 'reteica'];

    /**
     * DataInputValidator constructor.
     * @param string $docType Tipo de documento a validar
     */
    public function __construct(string $docType) {
        if (!defined('PHP_FLOAT_EPSILON')) {
            define('PHP_FLOAT_EPSILON', 2.000000000);
        }

        $this->helpers = new helpers;

        $this->docType = $docType;
        if ($this->docType === ConstantsDataInput::FC || $this->docType === ConstantsDataInput::NC || $this->docType === ConstantsDataInput::ND || $this->docType === ConstantsDataInput::NC_ND)
            $this->descriptionDocType = 'documento electrónico';
        elseif ($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC)
            $this->descriptionDocType = 'documento soporte';

        $this->init();
    }

    private function init() {
        // Reglas para evaluar la vigencia de una resolucion de facturación
        $this->rulesResolucionFacturacion = [
            'rfa_fecha_desde' => 'required|date_format:Y-m-d|before_or_equal:' . date('Y-m-d'),
            'rfa_fecha_hasta' => 'required|date_format:Y-m-d|after_or_equal:' . date('Y-m-d')
        ];

        $parametricas = $this->parametricas([
            'tipoDocumentosElectronico',
            'tipoDocumentos',
            'tipoOperaciones',
            'condicionesEntrega',
            'mediosPago',
            'formasPago',
            'codigosDescuentos',
            'conceptosCorreccion',
            'tributos',
            'codigosPostales',
            'clasificacionProductos',
            'preciosReferencia',
            'formaGeneracionTransmision',
            'unidades',
            'monedas',
            'mandatos',
            'transporteRegistro',
            'transporteRemesa',
            'facturacionWebCargos',
            'facturacionWebDescuentos'
        ], $this->docType);

        $this->tipoDocumentosElectronico    = $parametricas['tipoDocumentosElectronico'];
        $this->tipoDocumentos               = $parametricas['tipoDocumentos'];
        $this->tipoOperaciones              = $parametricas['tipoOperaciones'];
        $this->condicionesEntrega           = $parametricas['condicionesEntrega'];
        $this->mediosPago                   = $parametricas['mediosPago'];
        $this->formasPago                   = $parametricas['formasPago'];
        $this->codigosDescuentos            = $parametricas['codigosDescuentos'];
        $this->conceptosCorreccion          = $parametricas['conceptosCorreccion'];
        $this->tributos                     = $parametricas['tributos'];
        $this->codigosPostales              = $parametricas['codigosPostales'];
        $this->clasificacionProductos       = $parametricas['clasificacionProductos'];
        $this->preciosReferencia            = $parametricas['preciosReferencia']; 
        $this->formaGeneracionTransmision   = $parametricas['formaGeneracionTransmision'];
        $this->unidades                     = $parametricas['unidades'];
        $this->monedas                      = $parametricas['monedas'];
        $this->mandatos                     = $parametricas['mandatos'];
        $this->transporteRegistro           = $parametricas['transporteRegistro'];
        $this->transporteRemesa             = $parametricas['transporteRemesa'];
        $this->facturacionWebCargos         = $parametricas['facturacionWebCargos'];
        $this->facturacionWebDescuentos     = $parametricas['facturacionWebDescuentos'];

        // Reglas generales - Aunque estos campos pueden ser null porque simplemente puede que la seccion no venga
        // Si la seccion existe, deben ser obligatorios
        $this->rulesDetalleDescuentos['tipo']                    = 'required|in:CARGO,DESCUENTO';
        $this->rulesDetalleDescuentos['descripcion']             = 'nullable|string';
        $this->rulesDetalleDescuentos['porcentaje']              = 'required|numeric|regex:/^[0-9]{1,2}(\.[0-9]{1,2})?$/|min:0.00|max:100.00';
        $this->rulesDetalleDescuentos['valor_moneda_nacional']   = '';
        $this->rulesDetalleDescuentos['valor_moneda_extranjera'] = '';

        $this->rulesValores['base']  = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValores['valor'] = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';

        // Reglas por secciones
        $this->buildRulesValidatorHeader();
        $this->buildRulesValidatorDad();
        $this->buildRulesMediosPago();
        $this->buildRulesDetalleAnticipos();
        $this->buildRulesCargos();
        $this->buildRulesDescuentos();
        $this->buildRulesRetencionesSugeridas();
        $this->buildRulesItems();
        $this->buildRulesTributos();
    }

    /**
     * Construye las reglas de validacion para verificar los datos correspondientes a Cabecera.
     */
    private function buildRulesValidatorHeader() {
        // Cálculo de fecha de hace tres meses
        $fecha = date('Y-m-d', strtotime('-90 days'));
        $fecha = explode('-', $fecha);

        $keysToDelete = ['cdo_origen', 'cdo_clasificacion', 'ofe_id', 'adq_id', 'adq_id_autorizado', 'rfa_id', 'tde_id',
            'top_id', 'cdo_lote', 'mon_id', 'mon_id_extranjera', 'cdo_fecha_vencimiento', 'cdo_valor_a_pagar',
            'cdo_valor_a_pagar_moneda_extranjera', 'cdo_conceptos_correccion', 'cdo_documento_referencia' ,
            'cdo_valor_sin_impuestos_moneda_extranjera', 'cdo_impuestos_moneda_extranjera', 'cdo_retenciones_moneda_extranjera',
            'cdo_total_moneda_extranjera', 'cdo_cargos_moneda_extranjera', 'cdo_descuentos_moneda_extranjera', 'cdo_retenciones_sugeridas',
            'cdo_retenciones_sugeridas_moneda_extranjera', 'cdo_anticipo_moneda_extranjera', 'cdo_redondeo_moneda_extranjera',
            'cdo_retenciones', 'cdo_cargos', 'cdo_descuentos', 'cdo_anticipo', 'cdo_redondeo'];
        $this->rulesValidatorCabecera = array_merge(EtlCabeceraDocumentoDaop::$rules);

        foreach ($keysToDelete as $key)
            unset($this->rulesValidatorCabecera[$key]);

        // Campos a interpretar en el JSON
        $this->rulesValidatorCabecera['rfa_prefijo']                      = 'nullable|string|max:5|regex:/^[a-zA-Z\d]+$/';
        $this->rulesValidatorCabecera['cdo_consecutivo']                  = 'required|string|max:20|regex:/^[a-zA-Z\d]+$/';
        $this->rulesValidatorCabecera['tde_codigo']                       = 'required|in:01,02,03,04,91,92';
        $this->rulesValidatorCabecera['top_codigo']                       = 'required|string|max:20';
        $this->rulesValidatorCabecera['ofe_identificacion']               = 'required|string|max:20';
        $this->rulesValidatorCabecera['ofe_identificacion_multiples']     = 'nullable|array';
        $this->rulesValidatorCabecera['adq_identificacion']               = 'nullable|string|max:20';
        $this->rulesValidatorCabecera['adq_id_personalizado']             = 'nullable|string|max:100';
        $this->rulesValidatorCabecera['adq_identificacion_multiples']     = 'nullable|array';
        $this->rulesValidatorCabecera['adq_identificacion_autorizado']    = 'nullable|string|max:20';
        $this->rulesValidatorCabecera['cdo_fecha']                        = 'required|date_format:Y-m-d|before:' . Carbon::now() . '|after:' . Carbon::createFromDate($fecha[0], $fecha[1], $fecha[2]);
        $this->rulesValidatorCabecera['cdo_hora']                         = 'required|date_format:H:i:s';
        $this->rulesValidatorCabecera['cdo_vencimiento']                  = 'nullable|date_format:Y-m-d|after_or_equal:cdo_fecha';
        $this->rulesValidatorCabecera['cdo_trm_fecha']                    = 'nullable|date_format:Y-m-d';
        $this->rulesValidatorCabecera['cdo_envio_dian_moneda_extranjera'] = 'nullable|in:SI,NO';
        $this->rulesValidatorCabecera['cdo_total']                        = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_total_moneda_extranjera']      = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_observacion']                  = '';
        $this->rulesValidatorCabecera['mon_codigo']                       = 'required|string|max:10';

        $this->rulesValidatorCabecera['cdo_valor_sin_impuestos_moneda_extranjera']   = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_impuestos_moneda_extranjera']             = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_retenciones_moneda_extranjera']           = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_total_moneda_extranjera']                 = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_cargos_moneda_extranjera']                = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_descuentos_moneda_extranjera']            = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_retenciones_sugeridas']                   = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_retenciones_sugeridas_moneda_extranjera'] = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_redondeo_moneda_extranjera']              = 'nullable|numeric|regex:/^(\-)?[0-9]{1,13}(\.[0-9]{1,6})?$/';

        $this->rulesValidatorCabecera['cdo_retenciones']        = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_cargos']             = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_descuentos']         = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['cdo_redondeo']           = 'nullable|numeric|regex:/^(\-)?[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesValidatorCabecera['atributo_top_codigo_id'] = 'nullable|string';

        // Para los documentos soporte no aplican los valores del anticipo
        if ($this->docType !== ConstantsDataInput::DS && $this->docType !== ConstantsDataInput::DS_NC) {
            $this->rulesValidatorCabecera['cdo_anticipo']                   = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
            $this->rulesValidatorCabecera['cdo_anticipo_moneda_extranjera'] = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        }

        // Solo para notas credito documento soporte, notas crédito documento electrónico y notas debito documento electrónico
        if ($this->docType === ConstantsDataInput::NC || $this->docType === ConstantsDataInput::ND || $this->docType === ConstantsDataInput::NC_ND || $this->docType === ConstantsDataInput::DS_NC) {
            $this->rulesValidatorCabecera['cdo_conceptos_correccion'] = '';
            $this->rulesValidatorCabecera['rfa_resolucion'] = '';
        } else
            $this->rulesValidatorCabecera['rfa_resolucion'] = 'required|string|max:20';

        if ($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC) {
            $this->rulesValidatorCabecera['tde_codigo'] = 'required|in:05,95';
            $this->rulesValidatorCabecera['top_codigo'] = 'required|string|max:20|in:10';
        }
        // Campos concepto correccion
        $this->rulesCdoConceptosCorreccion['cco_codigo']                              = 'required|string|max:10';
        $this->rulesCdoConceptosCorreccion['cdo_observacion_correccion']              = 'required';
        // Campos documento referencia
        $this->rulesCdoDocumentoReferencia['clasificacion']                           = 'required|in:FC,NC,ND';
        $this->rulesCdoDocumentoReferencia['prefijo']                                 = 'nullable|string|max:5';
        $this->rulesCdoDocumentoReferencia['consecutivo']                             = 'required|string|max:20';
        $this->rulesCdoDocumentoReferencia['cufe']                                    = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['fecha_emision']                           = 'required|date_format:Y-m-d';
        // Campos aplicables a sector Salud
        $this->rulesCdoDocumentoReferencia['atributo_consecutivo_id']                 = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['atributo_consecutivo_name']               = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['atributo_consecutivo_agency_id']          = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['atributo_consecutivo_version_id']         = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['uuid']                                    = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['atributo_uuid_name']                      = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['codigo_tipo_documento']                   = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['atributo_codigo_tipo_documento_list_uri'] = 'nullable|string';
        $this->rulesCdoDocumentoReferencia['tipo_documento']                          = 'nullable|string';
    }

    /**
     * Construye las reglas de validacion para evaluar los datos correspondientes a DAD.
     */
    private function buildRulesValidatorDad() {
        $this->rulesCdoDocumentoAdicional['rod_codigo']                              = 'required|string|max:10';
        $this->rulesCdoDocumentoAdicional['prefijo']                                 = 'nullable|string|max:5';
        $this->rulesCdoDocumentoAdicional['consecutivo']                             = 'required|string|max:20';
        $this->rulesCdoDocumentoAdicional['cufe']                                    = 'nullable|string';
        $this->rulesCdoDocumentoAdicional['fecha_emision']                           = 'nullable|date_format:Y-m-d';
        // Campos aplicables a sector Salud
        $this->rulesCdoDocumentoAdicional['atributo_rod_codigo_list_uri']            = 'nullable|string';
        $this->rulesCdoDocumentoAdicional['atributo_consecutivo_id']                 = 'nullable|string';
        $this->rulesCdoDocumentoAdicional['atributo_consecutivo_name']               = 'nullable|string';
        $this->rulesCdoDocumentoAdicional['atributo_consecutivo_agency_id']          = 'nullable|string';
        $this->rulesCdoDocumentoAdicional['atributo_consecutivo_version_id']         = 'nullable|string';
        $this->rulesCdoDocumentoAdicional['uuid']                                    = 'nullable|string';
        $this->rulesCdoDocumentoAdicional['atributo_uuid_name']                      = 'nullable|string';
        $this->rulesCdoDocumentoAdicional['tipo_documento']                          = 'nullable|string';

        $this->rulesCdoPeriodoFacturacion['dad_periodo_fecha_inicio'] = 'required|date_format:Y-m-d';
        $this->rulesCdoPeriodoFacturacion['dad_periodo_hora_inicio']  = 'nullable|date_format:H:i:s';
        $this->rulesCdoPeriodoFacturacion['dad_periodo_fecha_fin']    = 'required|date_format:Y-m-d';
        $this->rulesCdoPeriodoFacturacion['dad_periodo_hora_fin']     = 'nullable|date_format:H:i:s';

        $this->rulesDadReferencia['referencia']               = 'nullable|string';
        $this->rulesDadReferencia['fecha_emision_referencia'] = 'nullable|date_format:Y-m-d';

        $this->rulesDadCondicionesEntrega['dad_entrega_bienes_fecha']         = 'required|date_format:Y-m-d';
        $this->rulesDadCondicionesEntrega['dad_entrega_bienes_hora']          = 'nullable|date_format:H:i:s';
        $this->rulesDadCondicionesEntrega['pai_codigo_entrega_bienes']        = 'required|string|max:10';
        $this->rulesDadCondicionesEntrega['dep_codigo_entrega_bienes']        = 'required|string|max:10';
        $this->rulesDadCondicionesEntrega['mun_codigo_entrega_bienes']        = 'required|string|max:10';
        $this->rulesDadCondicionesEntrega['dad_entrega_bienes_codigo_postal'] = 'nullable|string|max:10';
        $this->rulesDadCondicionesEntrega['dad_entrega_bienes_direccion']     = 'nullable|string';

        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_identificacion_transportador'] = 'nullable|string|max:20';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_identificacion_transporte']    = 'nullable|string|max:100';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_tipo_transporte']              = 'nullable|string|max:100';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_fecha_solicitada']             = 'nullable|date_format:Y-m-d';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_hora_solicitada']              = 'nullable|date_format:H:i:s';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_fecha_estimada']               = 'nullable|date_format:Y-m-d';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_hora_estimada']                = 'nullable|date_format:H:i:s';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_fecha_real']                   = 'nullable|date_format:Y-m-d';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_hora_real']                    = 'nullable|date_format:H:i:s';
        $this->rulesDadEntregaBienesDespacho['pai_codigo_entrega_bienes_despacho']                       = 'required|string|max:10';
        $this->rulesDadEntregaBienesDespacho['dep_codigo_entrega_bienes_despacho']                       = 'required|string|max:10';
        $this->rulesDadEntregaBienesDespacho['mun_codigo_entrega_bienes_despacho']                       = 'required|string|max:10';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_codigo_postal']                = 'nullable|string|max:10';
        $this->rulesDadEntregaBienesDespacho['dad_entrega_bienes_despacho_direccion']                    = 'nullable|string';

        $this->rulesDadTerminosEntrega['dad_terminos_entrega_condiciones_pago'] = '';
        $this->rulesDadTerminosEntrega['cen_codigo']                            = 'required|string|max:3';
        $this->rulesDadTerminosEntrega['pai_codigo_terminos_entrega']           = 'nullable|string|max:10';
        $this->rulesDadTerminosEntrega['dep_codigo_terminos_entrega']           = 'nullable|string|max:10';
        $this->rulesDadTerminosEntrega['mun_codigo_terminos_entrega']           = 'nullable|string|max:10';
        $this->rulesDadTerminosEntrega['dad_terminos_entrega_codigo_postal']    = 'nullable|string|max:10';
        $this->rulesDadTerminosEntrega['dad_terminos_entrega_direccion']        = 'nullable|string';
        $this->rulesDadTerminosEntrega['dad_detalle_descuentos']                = '';

        $this->rulesDadMonedaAlternativa['dad_codigo_moneda_alternativa']            = 'required|string|max:3';
        $this->rulesDadMonedaAlternativa['dad_codigo_moneda_extranjera_alternativa'] = 'required|string|max:3';
        $this->rulesDadMonedaAlternativa['dad_trm_alternativa']                      = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesDadMonedaAlternativa['dad_trm_fecha_alternativa']                = 'required|date_format:Y-m-d';

        $this->rulesDadInteroperabilidad['informacion_general'] = 'nullable|array';
        $this->rulesDadInteroperabilidad['interoperabilidad']   = 'required';

        $this->rulesInteroprabilidadCamposAdicionales['nombre'] = 'required|string';
        $this->rulesInteroprabilidadCamposAdicionales['valor']  = 'required|string';

        $this->rulesDadInteroperabilidadInteroperabilidad['grupo']      = 'nullable|string';
        $this->rulesDadInteroperabilidadInteroperabilidad['collection'] = 'required|array';

        $this->rulesDadInteroperabilidadCollection['nombre'] = 'nullable|string';
        $this->rulesDadInteroperabilidadCollection['informacion_adicional'] = 'required|array';
        
        $this->rulesInteroprabilidadInformacionAdicional['nombre']        = 'required|string';
        $this->rulesInteroprabilidadInformacionAdicional['valor']         = 'required|string';
        $this->rulesInteroprabilidadInformacionAdicional['atributo_name'] = 'nullable|string';
        $this->rulesInteroprabilidadInformacionAdicional['atributo_id']   = 'nullable|string';
        
        $this->rulesInteroprabilidadPT['url']            = 'nullable|string';
        $this->rulesInteroprabilidadPT['ws']             = 'nullable|string';
        $this->rulesInteroprabilidadPT['argumentos_ws']  = 'nullable|array';
        $this->rulesInteroprabilidadPT['argumentos_url'] = 'nullable|array';

        $this->rulesInteroprabilidadPTArgumentosWs['nombre'] = 'required|string';
        $this->rulesInteroprabilidadPTArgumentosWs['valor']  = 'required|string';

        $this->rulesDadDocumentoReferenciaLinea['prefijo']                         = 'nullable|string|max:5';
        $this->rulesDadDocumentoReferenciaLinea['consecutivo']                     = 'required|string|max:20';
        $this->rulesDadDocumentoReferenciaLinea['atributo_consecutivo_id']         = 'nullable|string';
        $this->rulesDadDocumentoReferenciaLinea['atributo_consecutivo_name']       = 'nullable|string';
        $this->rulesDadDocumentoReferenciaLinea['atributo_consecutivo_agency_id']  = 'nullable|string';
        $this->rulesDadDocumentoReferenciaLinea['atributo_consecutivo_version_id'] = 'nullable|string';
        $this->rulesDadDocumentoReferenciaLinea['valor']                           = 'nullable|string';
        $this->rulesDadDocumentoReferenciaLinea['atributo_valor_moneda']           = 'nullable|string';
        $this->rulesDadDocumentoReferenciaLinea['atributo_valor_concepto']         = 'nullable|string';

        $this->rulesDadReferenciaAdquirente['id']                                 = 'required|string';
        $this->rulesDadReferenciaAdquirente['atributo_id_name']                   = 'nullable|string';
        $this->rulesDadReferenciaAdquirente['nombre']                             = 'nullable|string';
        $this->rulesDadReferenciaAdquirente['postal_address_codigo_pais']         = 'nullable|string';
        $this->rulesDadReferenciaAdquirente['postal_address_descripcion_pais']    = 'nullable|string';
        $this->rulesDadReferenciaAdquirente['residence_address_id']               = 'nullable|string';
        $this->rulesDadReferenciaAdquirente['residence_address_atributo_id_name'] = 'nullable|string';
        $this->rulesDadReferenciaAdquirente['residence_address_nombre_ciudad']    = 'nullable|string';
        $this->rulesDadReferenciaAdquirente['residence_address_direccion']        = 'nullable|array';
        $this->rulesDadReferenciaAdquirente['codigo_pais']                        = 'nullable|string';
        $this->rulesDadReferenciaAdquirente['descripcion_pais']                   = 'nullable|string';
    }

    /**
     * Construye las reglas de validacion para verificar los medios de pago.
     */
    private function buildRulesMediosPago() {
        $this->rulesMediosPago['fpa_codigo']               = 'required|string|max:10';
        $this->rulesMediosPago['mpa_codigo']               = 'required|string|max:10';
        $this->rulesMediosPago['men_fecha_vencimiento']    = 'nullable|date_format:Y-m-d';
        $this->rulesMediosPago['men_identificador_pago']   = '';
        $this->rulesMediosPago['atributo_fpa_codigo_id']   = 'nullable|string';
        $this->rulesMediosPago['atributo_fpa_codigo_name'] = 'nullable|string';
        // Este campo forma parte del campo men_identificador_pago, si el campo padre existe, el id debe ser requerido
        $this->rulesMediosPagoIds['id'] = 'required|string|max:200';
    }

    /**
     * Construye las reglas de validacion para verificar los anticipos.
     */
    private function buildRulesDetalleAnticipos() {
        $this->rulesDetalleAnticipos['ant_identificacion']          = 'required|string|max:150';
        $this->rulesDetalleAnticipos['ant_valor']                   = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesDetalleAnticipos['ant_valor_moneda_extranjera'] = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesDetalleAnticipos['ant_fecha_recibido']          = 'required|date_format:Y-m-d|before_or_equal:ant_fecha_realizado';
        $this->rulesDetalleAnticipos['ant_fecha_realizado']         = 'nullable|date_format:Y-m-d';
        $this->rulesDetalleAnticipos['ant_hora_realizado']          = 'nullable|date_format:H:i:s';
        $this->rulesDetalleAnticipos['ant_instrucciones']           = 'nullable|string';
    }

    /**
     * Construye las reglas de validacion para verificar los descuentos.
     */
    private function buildRulesDescuentos() {
        $this->rulesDescuentos['cde_codigo']              = 'nullable|string|max:10';
        $this->rulesDescuentos['razon']                   = 'nullable|string';
        $this->rulesDescuentos['nombre']                  = 'nullable|string|max:255';
        $this->rulesDescuentos['porcentaje']              = 'required|numeric|regex:/^[0-9]{1,3}(\.[0-9]{1,2})?$/|min:0.00|max:100.00';
        $this->rulesDescuentos['valor_moneda_nacional']   = '';
        $this->rulesDescuentos['valor_moneda_extranjera'] = '';
    }

    /**
     * Construye las reglas de validacion para verificar los cargos.
     */
    private function buildRulesCargos() {
        $this->rulesCargos['razon']                   = 'nullable|string';
        $this->rulesCargos['nombre']                  = 'nullable|string|max:255';
        $this->rulesCargos['porcentaje']              = 'required|numeric|regex:/^[0-9]{1,3}(\.[0-9]{1,2})?$/|min:0.00|max:100.00';
        $this->rulesCargos['valor_moneda_nacional']   = '';
        $this->rulesCargos['valor_moneda_extranjera'] = '';
    }

    /**
     * Construye las reglas de validacion para retenciones sugeridas.
     */
    private function buildRulesRetencionesSugeridas() {
        $this->rulesRetencionesSugeridas['tipo']                    = 'required|string|in:RETEIVA,RETEFUENTE,RETEICA';
        $this->rulesRetencionesSugeridas['razon']                   = 'nullable|string';
        $this->rulesRetencionesSugeridas['porcentaje']              = 'required|numeric|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/|min:0.00|max:100.00';
        $this->rulesRetencionesSugeridas['valor_moneda_nacional']   = '';
        $this->rulesRetencionesSugeridas['valor_moneda_extranjera'] = '';
    }

    /**
     * Construye las reglas de validacion para items.
     */
    private function buildRulesItems() {
        // Cálculo de fecha de hace tres meses
        $fecha = date('Y-m-d', strtotime('-90 days'));
        $fecha = explode('-', $fecha);

        $keysToDelete = ['pai_id', 'cdo_id', 'und_id', 'pre_id', 'cpr_id', 'ddo_tipo_item', 'ddo_datos_tecnicos', 'pre_codigo',
            'ddo_propiedades_adicionales', 'ddo_informacion_adicional', 'ddo_valor_muestra', 'ddo_valor_muestra_moneda_extranjera',
            'tdo_id_mandatario', 'ddo_marca', 'ddo_modelo'];
        $this->rulesItems = array_merge(EtlDetalleDocumentoDaop::$rules);

        foreach ($keysToDelete as $key)
            unset($this->rulesItems[$key]);

        $this->rulesItems['pai_codigo']                           = 'nullable|string|max:10';
        $this->rulesItems['und_codigo']                           = 'nullable|string|max:10';
        $this->rulesItems['cpr_codigo']                           = 'required|string|max:10';
        $this->rulesItems['ddo_tipo_item']                        = 'nullable';
        $this->rulesItems['ddo_cargos']                           = '';
        $this->rulesItems['ddo_datos_tecnicos']                   = '';
        $this->rulesItems['ddo_propiedades_adicionales']          = '';
        $this->rulesItems['ddo_informacion_adicional']            = '';
        $this->rulesItems['tributos']                             = '';
        $this->rulesItems['retenciones']                          = '';
        $this->rulesItems['ddo_notas']                            = '';
        $this->rulesItems['ddo_precio_referencia']                = '';
        $this->rulesItems['ddo_cantidad']                         = 'required|numeric|min:0|regex:/^[0-9]{1,8}(\.[0-9]{1,2})?$/';
        $this->rulesItems['ddo_tdo_codigo_mandatario']            = 'nullable|string|max:2';
        $this->rulesItems['ddo_valor_unitario_moneda_extranjera'] = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesItems['ddo_total_moneda_extranjera']          = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesItems['ddo_valor_muestra_moneda_extranjera']  = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesItems['ddo_identificador']                    = 'nullable|string|max:5';
        $this->rulesItems['ddo_marca']                            = '';
        $this->rulesItems['ddo_modelo']                           = '';
        $this->rulesItems['ddo_identificacion_comprador']         = 'nullable|array';
        $this->rulesItems['ddo_fecha_compra']                     = '';

        $this->rulesDdoPrecioReferencia['pre_codigo']                          = 'required|string|max:10';
        $this->rulesDdoPrecioReferencia['ddo_valor_muestra']                   = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesDdoPrecioReferencia['ddo_valor_muestra_moneda_extranjera'] = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';

        $this->rulesDdoFechaCompras['fecha_compra'] = 'required|date_format:Y-m-d|before:' . Carbon::now() . '|after:' . Carbon::createFromDate($fecha[0], $fecha[1], $fecha[2]);
        $this->rulesDdoFechaCompras['codigo']       = 'required|string|max:2';
        
        $this->rulesDdoIdentificacionComprador['id']                        = 'nullable|string';
        $this->rulesDdoIdentificacionComprador['atributo_consecutivo_id']   = 'nullable|string';
        $this->rulesDdoIdentificacionComprador['atributo_consecutivo_name'] = 'nullable|string';

        $this->rulesDatosTecnicos['descripcion'] = 'required|string|max:500';
    }

    /**
     * Construye las reglas de validacion para items.
     */
    private function buildRulesTributos() {
        $this->rulesTributos['ddo_secuencia']                           = 'nullable|string|max:5';
        $this->rulesTributos['tri_codigo']                              = 'required|string|max:10';
        $this->rulesTributos['iid_valor']                               = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesTributos['iid_valor_moneda_extranjera']             = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesTributos['iid_motivo_exencion']                     = 'nullable|string';
        $this->rulesTributos['iid_porcentaje']                          = '';
        $this->rulesTributos['iid_unidad']                              = '';

        $this->rulesImpuestoPorcentaje['iid_base']                   = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesImpuestoPorcentaje['iid_base_moneda_extranjera'] = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesImpuestoPorcentaje['iid_porcentaje']             = 'required|numeric|regex:/^[0-9]{1,3}(\.[0-9]{1,3})?$/|min:0.00|max:100.00';

        $this->rulesImpuestoUnidad['iid_cantidad']                             = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/';
        $this->rulesImpuestoUnidad['und_codigo']                               = 'required|string|max:10';
        $this->rulesImpuestoUnidad['iid_base_unidad_medida']                   = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,2})?$/';
        $this->rulesImpuestoUnidad['iid_base_unidad_medida_moneda_extranjera'] = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,2})?$/';
        $this->rulesImpuestoUnidad['iid_valor_unitario']                       = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,2})?$/';
        $this->rulesImpuestoUnidad['iid_valor_unitario_moneda_extranjera']     = 'nullable|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,2})?$/';
    }

    /**
     * Efectua el proceso de validación sobre un conjunto de datos
     *
     * @param array $array Datos a evaluar
     * @param string $type Tipo de verificación
     * @param array $other Otro array para comparar dependecias
     * @param string $seccion Seccion que se esta procesando
     * @param boolean $registrado Variable de control para evitar verificar procesar documentos que ya existen en el sistema
     * @param null $nit_ofe
     * @return array|boolean Si no hay errores retorna un array vacio, si el tipo de validación solicitado no existe retorna
     * false
     */
    public function validate(array $array, string $type, array $other = [], string $seccion = '', &$registrado = false, $nit_ofe = null) {
        switch ($type) {
            case self::VALIDATE_HEADER_DOCUMENTO:
                return $this->checkCabecera($array, $registrado, $other);
            case self::VALIDATE_DAD_DOCUMENTO:
                return $this->checkDad($array, $other['tde_codigo'], $other['top_codigo'], $other['adq_identificacion'], (array_key_exists('adq_id_personalizado', $other) && !empty($other['adq_id_personalizado']) ? $other['adq_id_personalizado'] : null));
            case self::VALIDATE_MEDIOS_PAGO_DOCUMENTO:
                return $this->checkMediosPagos($array, $nit_ofe, (array_key_exists('cdo_vencimiento', $other) && !empty($other['cdo_vencimiento']) ? $other['cdo_vencimiento'] : null));
            case self::VALIDATE_DETALLES_ANTICIPOS_DOCUMENTO:
                return $this->checkDetallesAnticipos($array);
            case self::VALIDATE_CARGOS_DOCUMENTO:
                return $this->checkCargos($array, (array_key_exists('ofe_identificacion', $other) && !empty($other['ofe_identificacion']) ? $other['ofe_identificacion'] : null), (array_key_exists('cdo_sistema', $other) && !empty($other['cdo_sistema']) ? $other['cdo_sistema'] : null));
            case self::VALIDATE_DESCUENTOS_DOCUMENTO:
                return $this->checkDescuentos($array, (array_key_exists('ofe_identificacion', $other) && !empty($other['ofe_identificacion']) ? $other['ofe_identificacion'] : null), (array_key_exists('cdo_sistema', $other) && !empty($other['cdo_sistema']) ? $other['cdo_sistema'] : null));
            case self::VALIDATE_DETALLES_RETENCIONES_SUGERIDAS_DOCUMENTO:
                return $this->checkRetencionesSugeridas($array, (array_key_exists('cdo_sistema', $other) && !empty($other['cdo_sistema']) ? $other['cdo_sistema'] : null));
            case self::VALIDATE_ITEMS_DOCUMENTO:
                return $this->checkItems($array, $other['top_codigo'], $other['ofe'], (array_key_exists('cdo_sistema', $other) && !empty($other['cdo_sistema']) ? $other['cdo_sistema'] : null));
            case self::VALIDATE_TRIBUTOS_DOCUMENTO:
                return $this->checkTributos($array, $other, $seccion);
            case self::VALIDATE_COLUMNAS_PERSONALIZADAS:
                return $this->checkColumnasPersonalizadas($array, $other);
        }
        return false;
    }

    /**
     * Verifica si un adquirente existe o no, teniendo en cuenta si es requerido
     *
     * @param string $field_name
     * @param string $adq_identificacion
     * @param string $tipoAdq
     * @param $oferente
     * @param bool $required
     * @param string $adq_id_personalizado
     * @return array
     */
    private function checkAdquirente($field_name, $adq_identificacion, string $tipoAdq, $oferente, $required = true, $adq_id_personalizado = null) {
        $adq_identificacion = trim($adq_identificacion);
        $fieldTipo = 'adq_tipo_adquirente';

        if ($tipoAdq === self::TIPO_AUTORIZADO) $fieldTipo = 'adq_tipo_autorizado';
        elseif ($tipoAdq === self::TIPO_RESPONSABLE_ENTREGA) $fieldTipo = 'adq_tipo_responsable_entrega';
        elseif ($tipoAdq === self::TIPO_VENDEDOR_DOCUMENTO_SOPORTE) $fieldTipo = 'adq_tipo_vendedor_ds';
        $adq = [];

        $indiceAdqIdPersonalizado = '';
        $textoAdqIdPersonalizado  = !empty($adq_id_personalizado) ? ' con el adq_id_personalizado [' . $adq_id_personalizado . ']' : '';
        if (!array_key_exists($adq_identificacion, $this->adquirentes)) {
            if ($required) {
                if (empty($adq_identificacion))
                    return $this->getErrorMsg("El $tipoAdq $adq_identificacion en el campo $field_name no existe.");

                $adquirente = ConfiguracionAdquirente::select(['adq_id', 'adq_identificacion', 'adq_id_personalizado', 'adq_tipo_adquirente', 'adq_tipo_autorizado', 'adq_tipo_responsable_entrega', 'adq_tipo_vendedor_ds'])
                    ->where('estado', 'ACTIVO')
                    ->where('ofe_id', $oferente->ofe_id)
                    ->where('adq_identificacion', $adq_identificacion);

                if(!empty($adq_id_personalizado)) {
                    $indiceAdqIdPersonalizado = '~' . $adq_id_personalizado;
                    $adquirente = $adquirente->where('adq_id_personalizado', $adq_id_personalizado);
                }

                $adquirente = $adquirente->first();

                if (!$adquirente)
                    return $this->getErrorMsg("El $tipoAdq [$adq_identificacion]".$textoAdqIdPersonalizado." en el campo $field_name no existe.");
                    
                $adq = $this->adquirentes[$adq_identificacion . $indiceAdqIdPersonalizado] = $adquirente->toArray();
            } else {
                if (!empty($adq_identificacion)) {
                    $adquirente = ConfiguracionAdquirente::select(['adq_id', 'adq_identificacion', 'adq_id_personalizado', 'adq_tipo_adquirente', 'adq_tipo_autorizado', 'adq_tipo_responsable_entrega', 'adq_tipo_vendedor_ds'])
                        ->where('estado', 'ACTIVO')
                        ->where('ofe_id', $oferente->ofe_id)
                        ->where('adq_identificacion', $adq_identificacion);

                    if(!empty($adq_id_personalizado)) {
                        $indiceAdqIdPersonalizado = '~' . $adq_id_personalizado;
                        $adquirente = $adquirente->where('adq_id_personalizado', $adq_id_personalizado);
                    }

                    $adquirente = $adquirente->first();

                    if (!$adquirente)
                        return $this->getErrorMsg("El $tipoAdq [$adq_identificacion]".$textoAdqIdPersonalizado." en el campo $field_name no existe.");

                    $adq = $this->adquirentes[$adq_identificacion . $indiceAdqIdPersonalizado] = $adquirente->toArray();
                }
            }
        } else
            $adq = $this->adquirentes[$adq_identificacion . $indiceAdqIdPersonalizado];

        if (count($adq)) {
            if (!array_key_exists($fieldTipo, $adq) || $adq[$fieldTipo] !== 'SI')
                return $this->getErrorMsg("El $tipoAdq [$adq_identificacion]".$textoAdqIdPersonalizado." no está marcado para este tipo de Adquirente.");
        }

        return ['error' => false];
    }
    
    /**
     * Realiza validaciones sobre la información recibida para ofes múltiples
     *
     * @param array $ofeMultiples Array conteniendo la información de los ofes múltiples
     * @return array
     */
    private function checkOfeMultiples($ofeMultiples) {
        $msgErrors               = [];
        $listaOfes               = [];
        $repetidos               = [];
        $participacionNoValida   = [];
        $participacionNoNumerica = [];
        $totalParticipacion      = 0;
        foreach($ofeMultiples as $ofe) {
            if(!in_array($ofe->ofe_identificacion, $listaOfes)){
                $listaOfes[] = $ofe->ofe_identificacion;
            } else {
                $repetidos[] = $ofe->ofe_identificacion;
            }

            if (is_numeric($ofe->ofe_participacion)) {
                if($ofe->ofe_participacion <= 0 || $ofe->ofe_participacion > 100) {
                    $participacionNoValida[] = $ofe->ofe_identificacion;
            }

                $totalParticipacion += $ofe->ofe_participacion;
            } else {
                $participacionNoNumerica[] = $ofe->ofe_identificacion;
            }
        }

        if(!empty($repetidos)) {
            $msgErrors [] = "Los OFE(s) [" . implode(', ', $repetidos) . "] estan repetidos en el campo ofe_identificacion_multiples";
        }

        if(!empty($participacionNoValida)) {
            $msgErrors [] = "El porcentaje de participacion de los OFE(s) [" . implode(', ', $participacionNoValida) . "] debe ser mayor a cero y menor o igual a cien";
        }

        if(!empty($participacionNoNumerica)) {
            $msgErrors [] = "El porcentaje de participacion de los OFE(s) [" . implode(', ', $participacionNoNumerica) . "] debe ser numerico";
        }

        if($totalParticipacion != 100) {
            $msgErrors [] = "La sumatoria de los porcentajes de participacion de los OFE(s) multiples es [" . $totalParticipacion . "%] y debe ser igual a 100";
        }

        if (!empty($msgErrors)) {
            return ['error' => true, 'message' => $msgErrors];
        }

        return ['error' => false];
    }

    /**
     * Realiza validaciones sobre la información recibida para adquirentes múltiples
     *
     * @param array $adqMultiples Array conteniendo la información de los adquirentes múltiples
     * @return array
     */
    private function checkAdquirenteMultiples($adqMultiples) {
        if($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC)
            $tipoAdqPlural = 'vendedores documento soporte';
        else
            $tipoAdqPlural = 'adquirentes';

        // Verifica que no existan adquirentes duplicados
        $msgErrors               = [];
        $listaAdqs               = [];
        $repetidos               = [];
        $participacionNoValida   = [];
        $participacionNoNumerica = [];
        $totalParticipacion      = 0;
        foreach($adqMultiples as $adq) {
            if(!in_array($adq->adq_identificacion, $listaAdqs)){
                $listaAdqs[] = $adq->adq_identificacion;
            } else {
                $repetidos[] = $adq->adq_identificacion;
            }

            if (is_numeric($adq->adq_participacion)) {
                if($adq->adq_participacion <= 0 || $adq->adq_participacion > 100) {
                    $participacionNoValida[] = $adq->adq_identificacion;
            }
                $totalParticipacion += $adq->adq_participacion;
            } else {
                $participacionNoNumerica[] = $adq->adq_identificacion;
            }
        }

        if(!empty($repetidos)) {
            $msgErrors [] = "Los " . $tipoAdqPlural . " [" . implode(', ', $repetidos) . "] estan repetidos en el campo adq_identificacion_multiples";
        }

        if(!empty($participacionNoValida)) {
            $msgErrors [] = "El porcentaje de participacion de los " . $tipoAdqPlural . " [" . implode(', ', $participacionNoValida) . "] debe ser mayor a cero y menor o igual a cien";
        }

        if(!empty($participacionNoNumerica)) {
            $msgErrors [] = "El porcentaje de participacion de los " . $tipoAdqPlural . " [" . implode(', ', $participacionNoNumerica) . "] debe ser numerico";
        }

        if($totalParticipacion != 100) {
            $msgErrors [] = "La sumatoria de los porcentajes de participacion de los " . $tipoAdqPlural . " multiples es [" . $totalParticipacion . "%] y debe ser igual a 100";
        }

        if (!empty($msgErrors)) {
            return ['error' => true, 'message' => $msgErrors];
        }

        return ['error' => false];
    }

    /**
     * Verifica si un oferente existe.
     *
     * @param string $ofe_identificacion
     * @return array
     */
    private function checkOferente($ofe_identificacion) {
        //Trayendo la base de datos del usuario autenticado
        $user = auth()->user();

        $ofe_identificacion = trim($ofe_identificacion);
        if (!array_key_exists($ofe_identificacion, $this->oferentes)) {
            $oferente = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
                ->where('ofe_identificacion', $ofe_identificacion)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO');

            // Valida si el tipo de documento es DS para filtrar por los ofes que tienen contratado este servicio
            if($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC)
                $oferente = $oferente->where('ofe_documento_soporte', 'SI');

            $oferente = $oferente->first();

            if(($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC) && !$oferente)
                return $this->getErrorMsg("El Oferente $ofe_identificacion no tiene contratado el servicio de documento soporte.");

            if (!$oferente)
                return $this->getErrorMsg("El Oferente $ofe_identificacion no existe.");

            $this->oferentes[$ofe_identificacion] = $oferente;
        }

        return ['error' => false];
    }

    /**
     * Verifica si un mandato existe.
     *
     * @param string $man_codigo Código de mandato
     * @return array
     */
    private function checkMandato(string $man_codigo) {
        $man_codigo = trim($man_codigo);
        if (!array_key_exists($man_codigo, $this->mandatos))
            return $this->getErrorMsg("El código de mandato $man_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica si un registro de transporte registro existe.
     *
     * @param string $tre_codigo Código de transporte registro
     * @return array
     */
    private function checkTransporteRegistro(string $tre_codigo) {
        $tre_codigo = trim($tre_codigo);
        if (!array_key_exists($tre_codigo, $this->transporteRegistro))
            return $this->getErrorMsg("El código de registro de transporte $tre_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica si un registro de transporte remesa existe.
     *
     * @param string $trm_codigo Código de transporte remesa
     * @return array
     */
    private function checkTransporteRemesa(string $trm_codigo) {
        $trm_codigo = trim($trm_codigo);
        if (!array_key_exists($trm_codigo, $this->transporteRemesa))
            return $this->getErrorMsg("El código de registro de transporte remesa $trm_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica la composición de propiedades adicionales de un item.
     * 
     * @param array $errores Array de errores
     * @param int $ddo_secuencia Secuencia del item
     * @param string $top_codigo Tipo de operación del documento electrónico
     * @param object $ddoPropiedadAdicional Propiedad adicional de un item
     * @param int $indicePropiedadAdicional Índice de la propiedad adicional
     * @return void
     */
    private function checkComposicionPropiedadesAdicionales(&$errores, $ddo_secuencia, $top_codigo, $ddoPropiedadAdicional, $indicePropiedadAdicional) {
        if(!isset($ddoPropiedadAdicional->nombre) || (isset($ddoPropiedadAdicional->nombre) && empty($ddoPropiedadAdicional->nombre))) {
            $errores[] = 'Para el Item de secuencia [' . $ddo_secuencia . '], el campo ddo_propiedades_adicionales [' . $indicePropiedadAdicional . '] debe incluir la propiedad [nombre] teniendo en cuenta que el tipo de operación es [' . $top_codigo . ']';
        } else {
            $transporteRemesa = $this->checkTransporteRemesa($ddoPropiedadAdicional->nombre);
            if($transporteRemesa['error'])
                $transporteRemesa['message'] = 'Error en Item de secuencia [' . $ddo_secuencia . '], ddo_propiedades_adicionales [' . $indicePropiedadAdicional . '], tipo de operación [' . $top_codigo . ']: ' . $transporteRemesa['message'];

            $this->analyzeResponse($errores, $transporteRemesa);
        }

        if(!isset($ddoPropiedadAdicional->cantidad) || (isset($ddoPropiedadAdicional->cantidad) && empty($ddoPropiedadAdicional->cantidad))) {
            $errores[] = 'Para el Item de secuencia [' . $ddo_secuencia . '], el campo ddo_propiedades_adicionales [' . $indicePropiedadAdicional . '] debe incluir la propiedad [cantidad] teniendo en cuenta que el tipo de operación es [' . $top_codigo . ']';
        }

        if(
            (isset($ddoPropiedadAdicional->cantidad) && !empty($ddoPropiedadAdicional->cantidad)) &&
            (
                !isset($ddoPropiedadAdicional->und_codigo) || (isset($ddoPropiedadAdicional->und_codigo) && empty($ddoPropiedadAdicional->und_codigo))
            )
        ) {
            $errores[] = 'Para el Item de secuencia [' . $ddo_secuencia . '], el campo ddo_propiedades_adicionales [' . $indicePropiedadAdicional . '] incluye la propiedad [cantidad] pero no incluye la propiedad [und_codigo] teniendo en cuenta que el tipo de operación es [' . $top_codigo . ']';
        }
    }

    /**
     * Verifica si una condicion de entrega existe.
     *
     * @param string $cen_codigo Código de condicion de entrega
     * @return array
     */
    private function checkCondicionesEntrega(string $cen_codigo) {
        $cen_codigo = trim($cen_codigo);
        if (!array_key_exists($cen_codigo, $this->condicionesEntrega))
            return $this->getErrorMsg("La condición de entrega $cen_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica si existe un tipo de documento electronico.
     *
     * @param string $tde_codigo Código de Tipo de documento electronico
     * @return array
     */
    private function checkTipoDocumentoElectronico(string $tde_codigo) {
        $tde_codigo = trim($tde_codigo);
        if (!array_key_exists($tde_codigo, $this->tipoDocumentosElectronico))
            return $this->getErrorMsg("El tipo de documento electrónico $tde_codigo no existe, no esta vigente o no aplica para {$this->descriptionDocType}.");
        return ['error' => false];
    }

    /**
     * Verifica si existe una clasificación de producto.
     *
     * @param string $cpr_codigo Código de clasificacion de producto
     * @return array
     */
    private function checkClasificacionProductos(string $cpr_codigo) {
        $cpr_codigo = trim($cpr_codigo);
        if (!array_key_exists($cpr_codigo, $this->clasificacionProductos))
            return $this->getErrorMsg("La clasificación de producto $cpr_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica si existe un precio de referencia.
     *
     * @param string $pre_codigo Código de precio de referencia
     * @return array
     */
    private function checkPrecioReferencia(string $pre_codigo) {
        $pre_codigo = trim($pre_codigo);
        if (!array_key_exists($pre_codigo, $this->preciosReferencia))
            return $this->getErrorMsg("El precio de referencia $pre_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica si existe una forma de generación y transmisión.
     *
     * @param string $codigo Código de forma de generación y transmisión
     * @return array
     */
    private function checkFormaGeneracionTransmision(string $codigo) {
        $codigo = trim($codigo);
        if (!array_key_exists($codigo, $this->formaGeneracionTransmision))
            return $this->getErrorMsg("La forma de generación y transmisión $codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica si existe una unidad.
     *
     * @param string $und_codigo Código de precio de referencia
     * @return array
     */
    private function checkUnidad(string $und_codigo) {
        $und_codigo = trim($und_codigo);
        if (!array_key_exists($und_codigo, $this->unidades))
            return $this->getErrorMsg("La unidad $und_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica si existe un tipo de documento.
     *
     * @param string tdo_codigo Código Tipo Documento
     * @return array
     */
    private function checkTipoDocumento(string $tdo_codigo) {
        $tdo_codigo = trim($tdo_codigo);
        if (!array_key_exists($tdo_codigo, $this->tipoDocumentos))
            return $this->getErrorMsg("El tipo de documento $tdo_codigo no existe, no esta vigente o no aplica para {$this->descriptionDocType}.");
        return ['error' => false];
    }

    /**
     * Verifica si existe un tributo.
     *
     * @param string $tri_codigo Código de Tipo de documento electronico
     * @return array
     */
    private function checkTributo(string $tri_codigo) {
        $tri_codigo = trim($tri_codigo);
        if (!array_key_exists($tri_codigo, $this->tributos))
            return $this->getErrorMsg("El tributo $tri_codigo no existe, no esta vigente o no aplica para {$this->descriptionDocType}.");
        return ['error' => false];
    }

    /**
     * Verifica si existe un tributo.
     *
     * @param string $tri_codigo Código de Tipo de documento electronico
     * @param string $tri_tipo_requerido Tipo del tributo, ej: TRIBUTO / RETENCION / TRIBUTO-UNIDAD / RETENCION-UNIDAD
     * @return array
     */
    private function checkTributoYTipo(string $tri_codigo, string $tri_tipo_requerido) {
        $tri_codigo = trim($tri_codigo);
        if (!array_key_exists($tri_codigo, $this->tributos))
            return $this->getErrorMsg("El código tributo $tri_codigo no existe, no esta vigente o no aplica para {$this->descriptionDocType}.");
        elseif (array_key_exists($tri_codigo, $this->tributos) && $this->tributos[$tri_codigo]->tri_tipo != $tri_tipo_requerido)
            return $this->getErrorMsg("El código tributo $tri_codigo no es del tipo $tri_tipo_requerido.");
        return ['error' => false];
    }

    /**
     * Verifica si existe un código de descuento existe.
     *
     * @param string $cde_codigo Código de Descuento
     * @return array
     */
    private function checkCodigoDescuento(string $cde_codigo) {
        $cde_codigo = trim($cde_codigo);
        if (!array_key_exists($cde_codigo, $this->codigosDescuentos))
            return $this->getErrorMsg("El código de descuento $cde_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Verifica si un tipo de operacion existe.
     *
     * @param string $top_codigo Código de tipo de operación
     * @return array
     */
    private function checkTipoOperacion(string $top_codigo) {
        $top_codigo  = trim($top_codigo);
        $aplica_para = ($this->docType === ConstantsDataInput::DS_NC) ? ConstantsDataInput::DS : $this->docType;
        if (!array_key_exists($top_codigo . '~' . $aplica_para, $this->tipoOperaciones))
            return $this->getErrorMsg("El tipo de operacion $top_codigo no existe, no esta vigente o no aplica para {$this->descriptionDocType}.");
        return ['error' => false];
    }

    /**
     * Verifica si una forma de pago existe.
     *
     * @param string $fpa_codigo Código de forma de pago.
     * @return array
     */
    private function checkFormaPago(string $fpa_codigo) {
        $fpa_codigo = trim($fpa_codigo);
        if (!array_key_exists($fpa_codigo, $this->formasPago))
            return $this->getErrorMsg("La forma de pago $fpa_codigo no existe, no esta vigente o no aplica para {$this->descriptionDocType}.");
        return ['error' => false];
    }

    /**
     * Verifica si un medio de pago existe.
     *
     * @param string $mpa_codigo Código de medio de pago.
     * @return array
     */
    private function checkMedio(string $mpa_codigo) {
        $mpa_codigo = trim($mpa_codigo);
        if (!array_key_exists($mpa_codigo, $this->mediosPago))
            return $this->getErrorMsg("El medio de pago $mpa_codigo no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Evalua si se ha proporcionado una moneda valida o no.
     *
     * @param string $mon_codigo
     * @param string $field
     * @return array
     */
    private function checkMoneda(string $mon_codigo, string $field) {
        $mon_codigo = trim($mon_codigo);
        if (!array_key_exists($mon_codigo, $this->monedas))
            return $this->getErrorMsg("La moneda $mon_codigo en el campo $field no existe o no esta vigente.");
        return ['error' => false];
    }

    /**
     * Evalua si se ha proporcionado una resolucion valida o no.
     *
     * @param string $ofe_identificacion Identificacion del oferente
     * @param string $rfa_resolucion Número de Resolución
     * @param string $rfa_prefijo Prefijo de resolución
     * @return array
     */
    private function checkResolucion(string $ofe_identificacion, string $rfa_resolucion, $rfa_prefijo = null) {
        if (!array_key_exists($ofe_identificacion, $this->oferentes))
            return $this->getErrorMsg("El oferente [$ofe_identificacion] no existe.");

        $oferente       = $this->oferentes[$ofe_identificacion];
        $rfa_resolucion = trim($rfa_resolucion);
        if (!array_key_exists($rfa_resolucion, $this->resoluciones)) {
            $resolucion = ConfiguracionResolucionesFacturacion::where('ofe_id', $oferente->ofe_id);
            
            if ($this->docType === ConstantsDataInput::DS)
                $resolucion->where('rfa_tipo', 'DOCUMENTO_SOPORTE');
            
            if ($rfa_prefijo !== null && $rfa_prefijo !== '')
                $resolucion->where('rfa_prefijo', $rfa_prefijo);
            else
                $resolucion->whereNull('rfa_prefijo');

            $resolucion->where('rfa_resolucion', $rfa_resolucion);
            $resolucion->where('estado', 'ACTIVO');
            $resolucion = $resolucion->first();

            if ($resolucion === null)
                return $this->getErrorMsg("La resolución de facturación [$rfa_resolucion] con prefijo [{$rfa_prefijo}] no existe o no se encuentra asociada al OFE [$ofe_identificacion].");

            if($this->docType === ConstantsDataInput::DS && $resolucion->rfa_tipo != 'DOCUMENTO_SOPORTE')
                return $this->getErrorMsg("La resolución de facturación [$rfa_resolucion] con prefijo [{$rfa_prefijo}] debe ser del tipo DOCUMENTO_SOPORTE.");
            elseif($this->docType !== ConstantsDataInput::DS && $resolucion->rfa_tipo != 'AUTORIZACION' && $resolucion->rfa_tipo != 'HABILITACION' && $resolucion->rfa_tipo != 'CONTINGENCIA' && $resolucion->rfa_tipo != null)
                return $this->getErrorMsg("La resolución de facturación [$rfa_resolucion] con prefijo [{$rfa_prefijo}] debe ser del tipo AUTORIZACION o HABILITACION o CONTINGENCIA.");

            $indiceResolucion = $ofe_identificacion."~".$rfa_resolucion."~".(($rfa_prefijo !== null) ? $rfa_prefijo : '');
            $this->resoluciones[$indiceResolucion] = $resolucion->toArray();
        }
        return ['error' => false];
    }

    /**
     * Evalua si los datos del documento encajan dentro de los parametros configurados para la resolución.
     *
     * @param string $rfa_resolucion Número de resolución
     * @param string $cdo_fecha Fecha del documento
     * @param string|null $cdo_consecutivo
     * @param string $ofe_identificacion Identificación del Ofe
     * @param string $cdo_origen Origen del Documento
     * @param string $cdoRepresentacionGraficaDocumento Representación gráfica del documento
     * @return array
     */
    private function evaluateDataResolucion(string $rfa_resolucion, string $cdo_fecha, $cdo_consecutivo, string $ofe_identificacion, string $cdo_origen, string $rfa_prefijo = '', $cdoRepresentacionGraficaDocumento = '') {
        $indiceResolucion = $ofe_identificacion."~".$rfa_resolucion."~".(($rfa_prefijo !== null) ? $rfa_prefijo : '');        
        if (!array_key_exists($indiceResolucion, $this->resoluciones))
            return $this->getErrorMsg("La resolución de facturación [$rfa_resolucion] no existe.");
        $resolucion = $this->resoluciones[$indiceResolucion];

        $validarFechasResolucion = Validator::make([
            'rfa_fecha_desde' => $resolucion['rfa_fecha_desde'],
            'rfa_fecha_hasta' => $resolucion['rfa_fecha_hasta']
        ], $this->rulesResolucionFacturacion, $this->mensajeNumerosPositivos);

        if (count($validarFechasResolucion->errors()->all()) > 0)
            return $this->getErrorMsg('La resolución de facturación se encuentra fuera de un rango válido de fechas, revise la vigencia de la misma.');

        // Valida que la fecha del documento se encuentre en el rango de fechas de la resolución de facturación
        if (!Carbon::parse($cdo_fecha)->between(Carbon::parse($resolucion['rfa_fecha_desde']), Carbon::parse($resolucion['rfa_fecha_hasta'])))
            return $this->getErrorMsg('La fecha del documento no se encuentra dentro de las fechas de vigencia de la resolución de facturación');

        // Valor booleano para identificar que se trata de una FC de DHL Express de origen manual (cargue por excel),
        // si es verdadero NO se debe validar el consecutivo ya que el mismo se genera en el momento de crear el registro en el sistema
        $express = $this->docType == ConstantsDataInput::FC && $ofe_identificacion === self::NIT_DHLEXPRESS && $cdo_origen == self::ORIGEN_MANUAL;

        // Igualmente se deben exceptuar la verificación de existencia del documento cuando se trata de facturación manual:
        //      Si el origen es manual, se trata de una FC o DS, que NO sea DHL Express o que siendo DHL Express
        //      la RG sea 9 y que la resolución tenga activado el control de consecutivos
        $indiceResolucion    = $ofe_identificacion."~".$rfa_resolucion."~".(($rfa_prefijo !== null) ? $rfa_prefijo : '');
        $controlConsecutivos = array_key_exists($indiceResolucion, $this->resoluciones) ? $this->resoluciones[$indiceResolucion]['cdo_control_consecutivos'] : null;

        $facturaManual = $cdo_origen == self::ORIGEN_MANUAL &&
            $controlConsecutivos == 'SI' &&
            (
                $this->docType === ConstantsDataInput::FC || $this->docType === ConstantsDataInput::DS
            ) &&
            (
                $ofe_identificacion != self::NIT_DHLEXPRESS ||
                (
                    $ofe_identificacion == self::NIT_DHLEXPRESS &&
                    $cdoRepresentacionGraficaDocumento == '9'
                )
            );

        if(!$express && !$facturaManual) {
            if (!is_null($cdo_consecutivo)) {
                if (!($cdo_consecutivo >= $resolucion['rfa_consecutivo_inicial'] && $cdo_consecutivo <= $resolucion['rfa_consecutivo_final']))
                    return $this->getErrorMsg('Consecutivo del documento por fuera del rango de numeración autorizado');
            }
        }

        return ['error' => false];
    }

    /**
     * Evalua si se ha proporcionado un pais valido o no.
     *
     * @param string $pai_codigo
     * @return int|null
     */
    private function checkPais(string $pai_codigo) {
        $pais = null;
        if (!array_key_exists($pai_codigo, $this->paises)) {
            $objeto = ParametrosPais::select(['pai_id'])
                ->where('estado', 'ACTIVO')
                ->where('pai_codigo', $pai_codigo)
                ->get()
                ->groupBy('pai_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->first();

            if ($objeto)
                $pais = $this->paises[$pai_codigo] = $objeto->pai_id;
        } else
            $pais = $this->paises[$pai_codigo];
        return $pais;
    }

    /**
     * Evalua si se ha proporcionado un departamento valido o no.
     *
     * @param int|null $pai_id Id del país
     * @param string $dep_codigo Código de Departamento
     * @return int|null
     */
    private function checkDepartamento($pai_id, string $dep_codigo) {
        if ($pai_id === null)
            return null;
        $departamento = null;
        $key = sprintf("%d_%s", $pai_id, $dep_codigo);
        if (!array_key_exists($key, $this->departamentos)) {
            $objeto = ParametrosDepartamento::select(['dep_id', 'pai_id', 'dep_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('estado', 'ACTIVO')
                ->where('pai_id', $pai_id)
                ->where('dep_codigo', $dep_codigo)
                ->get()
                ->groupBy(function($item, $key) {
                    return $item->pai_id . '~' . $item->dep_codigo;
                })
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->first();

            if ($objeto)
                $departamento = $this->departamentos[$key] = $objeto->dep_id;
        } else
            $departamento = $this->departamentos[$key];
        return $departamento;
    }

    /**
     * Evalua si se ha proporcionado un municipio valido o no.
     *
     * @param int|null $pai_id Id de país
     * @param int|null $dep_id Id de departamento, puede ser -1, indicanddo que el municipio no tiene un departamento asociado
     * @param string $mun_codigo Código de Minicipio
     * @return int|null
     */
    private function checkMunicipio($pai_id, $dep_id, string $mun_codigo) {
        if ($pai_id === null)
            return null;
        $mun_codigo = trim($mun_codigo);
        $municipio = null;
        $key = sprintf("%d_%d_%s", $pai_id, $dep_id, $mun_codigo);
        if (!array_key_exists($key, $this->municipios)) {
            $objeto = ParametrosMunicipio::select(['mun_id', 'mun_codigo', 'pai_id', 'dep_id', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                ->where('estado', 'ACTIVO')
                ->where('pai_id', $pai_id)
                ->where('mun_codigo', $mun_codigo);

            if ($dep_id !== null)
                $objeto->where('dep_id', $dep_id);

            $objeto = $objeto->get()
                ->groupBy(function($item, $key) {
                    return $item->pai_id . '~' . $item->dep_id . '~' . $item->mun_codigo;
                })
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->first();

            if ($objeto)
                $municipio = $this->municipios[$key] = $objeto->mun_id;
        } else
            $municipio = $this->municipios[$key];
        return $municipio;
    }

    /**
     * Retorna un objeto con el error
     *
     * @param string $msg Mensaje de error
     * @return array
     */
    private function getErrorMsg(string $msg) {
        return ['error' => true, 'message' => $msg];
    }

    /**
     * Construye un mensaje para regla de validación faltantes
     *
     * @param string $key Nombre de la regla
     * @param string $groupName Nombre del grupo
     * @return string
     */
    private function getMissingRuleMessageError(string $key, string $groupName) {
        return "No se proporciono la regla $key en el grupo $groupName";
    }

    /**
     * Construye un mensaje para regla de validación faltantes
     *
     * @param string $key Nombre de la regla
     * @param string $groupName Nombre del grupo
     * @return string
     */
    private function getBadRuleMessageError(string $key, string $groupName) {
        return "La regla $key en el grupo $groupName esta mal definida";
    }

    /**
     * Construye un mensaje de error para un campo faltante en un grupo de datos
     *
     * @param string $key Nombre del campo
     * @param string $groupName Nombre del grupo
     * @return string
     */
    private function getMissingFieldDataGroupMessageError(string $key, string $groupName) {
        return "Falta el campo $key para $groupName";
    }

    /**
     * Construye un mensaje de error para un campo vacio en un grupo de datos
     *
     * @param string $key Nombre del campo
     * @param string $groupName Nombre del grupo
     * @return string
     */
    private function getEmptyFieldDataGroupMessageError(string $key, string $groupName) {
        return "El campo $key para $groupName esta vacío";
    }

    /**
     * Determina si una regla esta construida adecuadamente
     * @param array $rules
     * @param string $key
     * @param string $groupName
     * @return array|bool
     */
    private function errorsInRulesGroup(array $rules, string $key, string $groupName) {
        if (!array_key_exists($key, $rules))
            return $this->getErrorMsg($this->getMissingRuleMessageError($key, $groupName));
        if (!array_key_exists('name', $rules[$key]) || !array_key_exists('required', $rules[$key]))
            return $this->getErrorMsg($this->getBadRuleMessageError($key, $groupName));
        return false;
    }

    /**
     * @param $objeto
     * @param array $grupo Contiene un conjuto de elementos de tipo pais-departamento-municipio a ser validado
     * [
     *    'pai_key' => ['name' => 'nombre del campo de pais', 'required' => 'true or false'],
     *    'dep_key' => ['name' => 'nombre del campo de departamento', 'required' => 'true or false'],
     *    'mun_key' => ['name' => 'nombre del campo de municipio', 'required' => 'true or false']
     * ]
     * @param string $groupName
     * @return array
     */
    private function checkPaisDepartamentoMunicipio($objeto, array $grupo, string $groupName) {
        /*
         * Bloque de verificación de existencia reglas
         */
        if (($resultado = $this->errorsInRulesGroup($grupo, 'pai_key', $groupName)) !== false)
            return $resultado;
        if (($resultado = $this->errorsInRulesGroup($grupo, 'mun_key', $groupName)) !== false)
            return $resultado;
        if (($resultado = $this->errorsInRulesGroup($grupo, 'dep_key', $groupName)) !== false)
            return $resultado;

        $pais = null;
        $departamento = null;
        /*
         * Bloque de verificación de existencia de datos
         */
        // Pais
        $key = $grupo['pai_key']['name'];
        if (!array_key_exists($key, $objeto) && $grupo['pai_key']['required'])
            return $this->getErrorMsg($this->getMissingFieldDataGroupMessageError($key, $groupName ));

        if (array_key_exists($key, $objeto) ) {
            $valuePais = trim($objeto[$key]);
            if ($grupo['pai_key']['required']) {
                if (empty($valuePais))
                    return $this->getErrorMsg($this->getEmptyFieldDataGroupMessageError($key, $groupName));
                $pais = $this->checkPais($valuePais);
                if ($pais === null)
                    return $this->getErrorMsg("El código de país [$valuePais] en $groupName no esta registrado");
            } elseif (!empty($valuePais)) {
                $pais = $this->checkPais($valuePais);
                if ($pais === null)
                    return $this->getErrorMsg("El código de país [$valuePais] en $groupName no esta registrado");
            }

            // Departamento
            $key = $grupo['dep_key']['name'];
            if (!array_key_exists($key, $objeto) && $grupo['dep_key']['required'])
                return $this->getErrorMsg($this->getMissingFieldDataGroupMessageError($key, $groupName));
            if (array_key_exists($key, $objeto)) {
                $valueDepartamento = trim($objeto[$key]);
                if ($grupo['dep_key']['required']) {
                    if (empty($valueDepartamento))
                        return $this->getErrorMsg($this->getEmptyFieldDataGroupMessageError($key, $groupName));
                    $departamento = $this->checkDepartamento($pais, $valueDepartamento);
                    if ($departamento === null)
                        return $this->getErrorMsg("El código de departamento [$valueDepartamento] en $groupName no esta registrado");
                } elseif (!empty($valueDepartamento)) {
                    $departamento = $this->checkDepartamento($pais, $valueDepartamento);
                    if ($departamento === null)
                        return $this->getErrorMsg("El código de departamento [$valueDepartamento] en $groupName no esta registrado");
                }
            }

            // Municipio
            $key = $grupo['mun_key']['name'];
            if (!array_key_exists($key, $objeto) && $grupo['mun_key']['required'])
                return $this->getErrorMsg($this->getMissingFieldDataGroupMessageError($key, $groupName));
            if (array_key_exists($key, $objeto) ) {
                $valueMunicipio = trim($objeto[$key]);
                if ($grupo['mun_key']['required']) {
                    if (empty($valueMunicipio))
                        return $this->getErrorMsg($this->getEmptyFieldDataGroupMessageError($key, $groupName));
                    $municipio = $this->checkMunicipio($pais, $departamento, $valueMunicipio);
                    if ($municipio === null)
                        return $this->getErrorMsg("El código de municipio [$valueMunicipio] en $groupName no esta registrado");
                } elseif (!empty($valueMunicipio)) {
                    $municipio = $this->checkMunicipio($pais, $departamento, $valueMunicipio);
                    if ($municipio === null)
                        return $this->getErrorMsg("El código de municipio [$valueMunicipio] en $groupName no esta registrado");
                }
            }
        }

        return ['error' => false];
    }

    /**
     * Efectua la validacion sobre sobre objetos en arrays
     *
     * @param mixed $object objeto que sera evaluado
     * @param array $keys LLaves de los objetos a ser procesadas
     * @param string $fieldName Nombre del campo que contiene el objeto
     * @param array $rules Reglas de validacion para cada objeto que contiene el array de objetos
     * @param bool $nullable
     * @param array $opcionales
     * @return array
     */
    private function validateArrayObjects($object, array $keys, string $fieldName, array $rules = [], $nullable = true, $opcionales = []) {
        if ($object === '' || is_null($object)) {
            if ($nullable)
                return ['error' => false];
            return $this->getErrorMsg("El campo $fieldName debe contener un array con objetos validos.");
        }
        if (is_array($object)) {
            foreach ($object as $indice => $item) {
                $item = (array)$item;
                $properties = array_keys($item);
                // Union de las diferencias
                $sobra = array_diff($properties, $keys);
                $falta = array_diff($keys, $properties);
                $falta = array_diff($falta, $opcionales);

                if (!empty($sobra) || !empty($falta)) {
                    $msg = "El campo $fieldName esta mal formado.";
                    if (!empty($falta)) {
                        if (count($falta) === 1)
                            $msg = $msg . " Falta el campo: " . implode(',', $falta) . '.';
                        else
                            $msg = $msg . " Faltan los campos: " . implode(',', $falta) . '.';
                    }
                    if (!empty($sobra)) {
                        if (count($falta) === 1)
                            $msg = $msg . " Sobra el campo: " . implode(',', $sobra) . '.';
                        else
                            $msg = $msg . " Sobran los campos: " . implode(',', $sobra) . '.';
                    }
                    return $this->getErrorMsg($msg);
                }

                if (count($rules)) {
                    // Si se trata del objeto cdo_documento referencia, el cufe es obligatorio para el primer objeto del array, para las demás objetos no es obligatorio
                    if($fieldName == 'cdo_documento_referencia' && $indice > 0)
                        $rules['cufe'] = 'nullable|string';

                    // Si todos los campos de la sección documento referencia llegan en null no se valida que sean obligatorios
                    if ($fieldName == 'cdo_documento_referencia') {
                        if ($item['consecutivo'] == null && $item['fecha_emision'] == null && $item['prefijo'] == null && $item['clasificacion'] == null && $item['cufe'] == null) {
                            $rules['clasificacion'] = 'nullable|in:FC,NC,ND';
                            $rules['prefijo']       = 'nullable|string|max:5';
                            $rules['consecutivo']   = 'nullable|string|max:20';
                            $rules['cufe']          = 'nullable|string';
                            $rules['fecha_emision'] = 'nullable|date_format:Y-m-d';
                        }
                    }

                    $validar = Validator::make(is_object($item) ? (array)$item : $item, $rules, $this->mensajeNumerosPositivos);
                    if (count($validar->errors()->all()) > 0) {
                        $implode = implode(', ', $validar->errors()->all());
                        return $this->getErrorMsg("El campo $fieldName tiene los siguientes errores: [$implode]");
                    }
                }
            }
        } else
            return $this->getErrorMsg("El campo $fieldName debe ser un arreglo");
        return ['error' => false];
    }

    /**
     * Efectua la validacion sobre un objeto
     *
     * @param $object objeto que sera evaluado
     * @param array $keys LLaves de los objetos a ser procesadas
     * @param string $fieldName Nombre del campo que contiene el objeto
     * @param array $rules Reglas de validacion para cada objeto que contiene el array de objetos
     * @param bool $nullable
     * @param array $opcionales
     * @param bool $puedeSerVacio
     * @return array
     */
    private function validateObject($object, array $keys, string $fieldName, array $rules = [], $nullable = true, $opcionales = [], $puedeSerVacio = false) {
        if ($object === '' || is_null($object)) {
            if ($nullable)
                return ['error' => false];
            return $this->getErrorMsg("El campo $fieldName debe contener un objeto valido.");
        }
        if (is_object($object)) {
            $item = (array)$object;
            $properties = array_keys($item);
            $sobra = array_diff($properties, $keys);
            $falta = array_diff($keys, $properties);
            $falta = array_diff($falta, $opcionales);

            if (!empty($sobra) || !empty($falta)) {
                $msg = "El campo $fieldName esta mal formado.";
                if (!empty($falta)) {
                    if (count($falta) === 1)
                        $msg = $msg . " Falta el campo: " . implode(',', $falta) . '.';
                    else
                        $msg = $msg . " Faltan los campos: " . implode(',', $falta) . '.';
                }
                if (!empty($sobra)) {
                    if (count($falta) === 1)
                        $msg = $msg . " Sobra el campo: " . implode(',', $sobra) . '.';
                    else
                        $msg = $msg . " Sobran los campos: " . implode(',', $sobra) . '.';
                }
                return $this->getErrorMsg($msg);
            }
            if (!empty($rules)) {
                $validar = Validator::make(is_object($item) ? (array)$item : $item, $rules, $this->mensajeNumerosPositivos);
                if (count($validar->errors()->all()) > 0) {
                    $implode = implode(', ', $validar->errors()->all());
                    return $this->getErrorMsg("El campo $fieldName tiene los siguientes errores: [$implode]");
                }
            }
        } else {
            if ((!$puedeSerVacio || !empty($object)) && !(is_array($object) && empty($object)))
                return $this->getErrorMsg("El campo $fieldName debe ser un objeto");
            elseif (is_array($object) && empty($object) && !$puedeSerVacio)
            return $this->getErrorMsg("El campo $fieldName esta vacio");
        }
        return ['error' => false];
    }

    /**
     * Determina si ha ocurrido un error y lo encola.
     *
     * @param $errors
     * @param $seccion
     * @param $response
     */
    private function analyzeResponse(&$errors, $response, $seccion = '') {
        if ($response['error']) {
            $errors[] = $seccion === '' ? $response['message'] : $response['message'] . ", $seccion";
        }
    }

    /**
     *  Efecuta los procesos de validacion sobre los datos de cabecera proporcionados en json.
     *
     * @param array $array Datos de cabecera a ser validados
     * @param mixed $registrado Indica si un documento ya esta registrado o no
     * @param array $other Arreglo con datos adicionales para procesamiento
     * @return array
     */
    private function checkCabecera(array $array, &$registrado, array $other) {
        $errores = [];
        $cdo_origen = isset($array['cdo_origen']) ? $array['cdo_origen'] : '';

        // Condicionales para la TRM
        if(
            $array['mon_codigo'] != 'COP' ||
            (
                array_key_exists('cdo_envio_dian_moneda_extranjera', $array) && $array['cdo_envio_dian_moneda_extranjera'] == 'SI' &&
                array_key_exists('mon_codigo_extranjera', $array) && $array['mon_codigo_extranjera'] != 'COP'
            )
        ) {
            $this->rulesValidatorCabecera['cdo_trm'] = [
                'required',
                'numeric',
                'regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/',
                function ($attribute, $value, $fail) {
                    if ($value <= 0) {
                        $fail('La TRM debe ser mayor a cero');
                    }
                }
            ];

            $this->rulesValidatorCabecera['cdo_trm_fecha'] = [
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) {
                    if ($value == '')
                        $fail('La fecha de la TRM es obligatoria');
                }
            ];

            $this->mensajeNumerosPositivos['cdo_trm.regex']             = 'La TRM debe ser un dato numérico mayor a cero (puede tener decimales con punto como separador)';
            $this->mensajeNumerosPositivos['cdo_trm.numeric']           = 'La TRM debe ser un dato numérico mayor a cero';
            $this->mensajeNumerosPositivos['cdo_trm.required']          = 'La TRM es obligatoria';
            $this->mensajeNumerosPositivos['cdo_trm_fecha.required']    = 'La fecha de la TRM es obligatoria';
            $this->mensajeNumerosPositivos['cdo_trm_fecha.date_format'] = 'La fecha de la TRM no cumple con el formato requerido [YYYY-MM-DD]';
        }

        if($array['mon_codigo'] != 'COP' && array_key_exists('mon_codigo_extranjera', $array) && $array['mon_codigo_extranjera'] != 'COP' && $array['mon_codigo_extranjera'] != null) {
            $errores[] = 'El código de moneda extranjera debe ser COP teniendo en cuenta que el código de la moneda principal del documento es diferente de COP';
        }

        if (array_key_exists('ofe_identificacion', $array)) {
            $response = $this->checkOferente($array['ofe_identificacion']);
            $this->analyzeResponse($errores, $response);
        } else
            $response = ['error' => true];

        if (!$response['error'] && ($this->docType === ConstantsDataInput::FC || $this->docType === ConstantsDataInput::DS)) {
            $ofe_identificacion = isset($array['ofe_identificacion']) ? $array['ofe_identificacion'] : '';
            $rfa_resolucion     = isset($array['rfa_resolucion']) ? $array['rfa_resolucion'] : '';
            $rfa_prefijo        = isset($array['rfa_prefijo']) ? $array['rfa_prefijo'] : '';
            $cdo_fecha          = isset($array['cdo_fecha']) ? $array['cdo_fecha'] : '';
            $cdo_consecutivo    = isset($array['cdo_consecutivo']) ? $array['cdo_consecutivo'] : '';

            $response = $this->checkResolucion($ofe_identificacion, $rfa_resolucion, $rfa_prefijo);
            $this->analyzeResponse($errores, $response);
            if (!$response['error']) {
                $response = $this->evaluateDataResolucion($rfa_resolucion, $cdo_fecha, $cdo_consecutivo, $ofe_identificacion, $cdo_origen, $rfa_prefijo, $array['cdo_representacion_grafica_documento']);
                $this->analyzeResponse($errores, $response);
            }
        }

        if (!empty($errores))
            return $errores;

        //Inicializando variable de control para facturación manual de express
        $facturaManual = false;
        if ($this->docType === ConstantsDataInput::FC || $this->docType === ConstantsDataInput::DS) {
            // Se debe exceptuar la verificación de existencia del documento cuando:
            // Si el origen es manual, se trata de una FC o DS y la resolución tenga activado el control de consecutivos
            // Y se verifica si el cdo_consecutivo no existe o existe y llega vacio para asignar un valor por defecto y pasar validaciones 
            $indiceResolucion    = $ofe_identificacion."~".$rfa_resolucion."~".(($rfa_prefijo !== null) ? $rfa_prefijo : '');
            $controlConsecutivos = array_key_exists($indiceResolucion, $this->resoluciones) ? $this->resoluciones[$indiceResolucion]['cdo_control_consecutivos'] : null;
            $facturaManual       = $cdo_origen == self::ORIGEN_MANUAL && $controlConsecutivos == 'SI';

            if($facturaManual && (!array_key_exists('cdo_consecutivo', $array) || (array_key_exists('cdo_consecutivo', $array) && empty($array['cdo_consecutivo']))))
                $array['cdo_consecutivo'] = '1';
        }

        // Si el tipo de documento es 03 se debe validar solo el formato de la fecha y que no sea mayor a la fecha actual
        if ($array['tde_codigo'] == '03')
            $this->rulesValidatorCabecera['cdo_fecha'] = 'required|date_format:Y-m-d|before:' . Carbon::now();

        // Validación General de formatos
        $validarFormatosCabecera = Validator::make($array, $this->rulesValidatorCabecera, $this->mensajeNumerosPositivos);
        if (!empty($validarFormatosCabecera->errors()->all()))
            return $validarFormatosCabecera->errors()->all();

        $response = $this->checkTipoDocumentoElectronico($array['tde_codigo']);
        $this->analyzeResponse($errores, $response);

        //Trayendo la base de datos del usuario autenticado
        $user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
            ->where('ofe_identificacion', $array['ofe_identificacion'])
            ->validarAsociacionBaseDatos()
            ->first();

        if (!is_null($ofe)) {
            $tde_id = array_key_exists($array['tde_codigo'], $this->tipoDocumentosElectronico) ? $this->tipoDocumentosElectronico[$array['tde_codigo']]->tde_id : null;

            $cdo_informacion_adicional = $other['cdo_informacion_adicional'];

            // Verifica si existe el documento en la data operativa
            $documento    = $this->helpers->consultarDocumento('daop', $tde_id, $ofe->ofe_id, $array);

            // Verifica si existe el documento en la data histórica
            $docHistorico = $this->helpers->consultarDocumento('historico', $tde_id, $ofe->ofe_id, $array);

            // Se evalua el caso en que se esta creando un documento
            if (is_null($cdo_informacion_adicional) || !isset($cdo_informacion_adicional->update) || strtoupper($cdo_informacion_adicional->update) !== 'SI') {
                // Si el OFE es DHL Express, el tipo de documento es FC y el cargue es MANUAL
                // se omite la verificación, en la creación de documentos, de si el documento existe
                $express = $this->docType === ConstantsDataInput::FC && $ofe_identificacion == self::NIT_DHLEXPRESS && $cdo_origen == self::ORIGEN_MANUAL;

                // Del mismo modo, si el documento ya existe pero su estado en cabecera es INACTIVO
                // se omite la verificación, en la creación de documentos, de si el documento existe
                if(!$express && !$facturaManual) {
                    if ((!is_null($documento) && $documento->estado == 'ACTIVO') || !is_null($docHistorico)) {
                        $registrado = true;
                        return [
                            "El documento electrónico {$array['rfa_prefijo']}{$array['cdo_consecutivo']} ya esta registrado~" . (isset($documento->cdo_id) ? $documento->cdo_id : $docHistorico->cdo_id) . "|" . (isset($documento->cdo_lote) ? $documento->cdo_lote : $docHistorico->cdo_lote),
                        ];
                    }
                }
            } else { // Se quiere editar un documento
                if (is_null($documento) && is_null($docHistorico)) {
                    $registrado = false;
                    return ["El documento electrónico {$array['rfa_prefijo']}{$array['cdo_consecutivo']} no esta registrado. No es posible actualizar el documento."];
                } elseif(!is_null($documento)) {
                    // Independiente del estado del documento (ACTIVO/INACTIVO) y sabiendo que en cdo_informacion_adicional viene la posición update en SI,
                    // se verifica que el documento NO haya sido transmitido a la DIAN o notificado al adquirente para poder editarlo/modificarlo
                    $doEstado = EtlEstadosDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $documento->cdo_id)
                        ->where('est_estado', 'DO')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->first();
                    if (!is_null($doEstado)) {
                        $registrado = false;
                        return ["El documento electrónico {$array['rfa_prefijo']}{$array['cdo_consecutivo']} ya se encuentra aprobado por la DIAN. No es posible actualizar el documento."];
                    }

                    $notificacionEstado = EtlEstadosDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $documento->cdo_id)
                        ->where('est_estado', 'NOTIFICACION')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->first();
                    if (!is_null($notificacionEstado)) {
                        $registrado = false;
                        return ["El documento electrónico {$array['rfa_prefijo']}{$array['cdo_consecutivo']} ya fue notificado. No es posible actualizar el documento."];
                    }

                    $registrado = true;
                }
            }

            // Se verifica si en información adicional llega el grupo de datos cdo_direccion_domicilio_correspondencia para realizar las validaciones correspondiente
            if(isset($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia) && !empty($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia)) {
                $continuar = true;
                if(!is_object($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia)) {
                    $continuar = false;
                    $errores[] = 'El documento incluye el grupo de datos [cdo_direccion_domicilio_correspondencia] pero no es un objeto';
                }

                if(
                    $continuar &&
                    (
                        (
                            !isset($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->pai_codigo) ||
                            (isset($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->pai_codigo) && empty($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->pai_codigo))
                        ) ||
                        (
                            !isset($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->adq_direccion) ||
                            (isset($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->adq_direccion) && empty($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->adq_direccion))
                        )
                    )
                ) {
                    $continuar = false;
                    $errores[] = 'El documento incluye el grupo de datos [cdo_direccion_domicilio_correspondencia], el país y la dirección son datos obligatorios esta dirección';
                }

                if(
                    $continuar
                ) {
                    $keys = [
                        'pai_key' => ['name' => 'pai_codigo', 'required' => true],
                        'dep_key' => ['name' => 'dep_codigo', 'required' => false],
                        'mun_key' => ['name' => 'mun_codigo', 'required' => false]
                    ];
                    $response = $this->checkPaisDepartamentoMunicipio(
                        (array)$cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia,
                        $keys,
                        'cdo_direccion_domicilio_correspondencia'
                    );
                    $this->analyzeResponse($errores, $response);
                    if (isset($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->cpo_codigo) && !empty($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->cpo_codigo)) {
                        if (!array_key_exists($cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->cpo_codigo, $this->codigosPostales))
                            $errores[] = "No existe el código postal {$cdo_informacion_adicional->cdo_direccion_domicilio_correspondencia->cpo_codigo} de cdo_direccion_domicilio_correspondencia";
                    }
                }
            }

            // Se verifica si en información adicional llega el grupo de datos cdo_informacion_adicional_excluir_xml para realizar las validaciones correspondiente
            if(isset($cdo_informacion_adicional->cdo_informacion_adicional_excluir_xml) && !empty($cdo_informacion_adicional->cdo_informacion_adicional_excluir_xml)) {
                if(!is_array($cdo_informacion_adicional->cdo_informacion_adicional_excluir_xml)) {
                    $continuar = false;
                    $errores[] = 'El documento incluye el grupo de datos [cdo_informacion_adicional_excluir_xml] pero no es un array';
                } else {
                    $adicionalXml = (array)$cdo_informacion_adicional->cdo_informacion_adicional_excluir_xml;
                    
                    // Verifica que no existan elementos duplicados
                    if(count($adicionalXml) != count(array_unique($adicionalXml))) {
                        $errores[] = 'Se encontraron elementos duplicados en el grupo de datos [cdo_informacion_adicional_excluir_xml]';
                    }

                    // Verifica que en el grupo de datos no se incluyan los elementos cdo_procesar_documento o cdo_direccion_domicilio_correspondencia
                    if(
                        in_array('cdo_informacion_adicional_excluir_xml', $adicionalXml) ||
                        in_array('cdo_procesar_documento', $adicionalXml) ||
                        in_array('cdo_direccion_domicilio_correspondencia', $adicionalXml)
                    ) {
                        $errores[] = 'No puede incluir en el grupo de datos [cdo_informacion_adicional_excluir_xml] los valores cdo_procesar_documento, cdo_direccion_domicilio_correspondencia y/o cdo_informacion_adicional_excluir_xml';
                    }

                    // Verifica que los elementos del grupo cdo_informacion_adicional_excluir_xml existan y tengan valores en cdo_informacion_adicional
                    $informacionAdicional = (array) $cdo_informacion_adicional;
                    $keyInformacionAdicional = array_keys($informacionAdicional);
                    foreach($adicionalXml as $datoAdicional) {
                        if(!in_array($datoAdicional, $keyInformacionAdicional)) {
                            $errores[] = 'El campo [' . $datoAdicional . '] no existe dentro del grupo de datos [cdo_informacion_adicional]';
                        }
                    }
                }
            }
        }

        $response = $this->checkTipoOperacion($array['top_codigo']);
        $this->analyzeResponse($errores, $response);

        if(array_key_exists('ofe_identificacion_multiples', $array) && !empty($array['ofe_identificacion_multiples'])) {
            $existeOfePrincipal = array_search($array['ofe_identificacion'], array_column($array['ofe_identificacion_multiples'], 'ofe_identificacion'));
            if($existeOfePrincipal === false) {
                $errores[] = 'El OFE [' . $array['ofe_identificacion'] . '] no existe dentro del campo [ofe_identificacion_multiples]';
            }

            $responseMultiplesOfes = $this->checkOfeMultiples($array['ofe_identificacion_multiples']);
            if (!empty($responseMultiplesOfes['message'])) {
                foreach ($responseMultiplesOfes['message'] as $msgError) {
                    $responseMultiplesOfesErrores = $this->getErrorMsg($msgError);
                    $this->analyzeResponse($errores, $responseMultiplesOfesErrores);
                }
            }

            foreach($array['ofe_identificacion_multiples'] as $ofeMultiple) {
                $response = $this->checkOferente($ofeMultiple->ofe_identificacion);
                $this->analyzeResponse($errores, $response);
            }
        }

        if(
            array_key_exists('adq_identificacion', $array) && !empty($array['adq_identificacion']) && array_key_exists('adq_identificacion_multiples', $array) && !empty($array['adq_identificacion_multiples'])
        ) {
            $errores[] = "El documento no puede tener valores para los campos adq_identificacion y adq_identificacion_multiples";
        } elseif(
            array_key_exists('adq_identificacion', $array) && empty($array['adq_identificacion']) && array_key_exists('adq_identificacion_multiples', $array) && empty($array['adq_identificacion_multiples'])
        ) {
            if($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC)
                $tipoAdqPlural = 'vendedores documento soporte';
            else
                $tipoAdqPlural = 'adquirentes';

            $errores[] = "El documento no refiere el / los $tipoAdqPlural";
        } elseif(
            (array_key_exists('adq_identificacion', $array) && empty($array['adq_identificacion']) && array_key_exists('adq_identificacion_multiples', $array) && !empty($array['adq_identificacion_multiples'])) ||
            (!array_key_exists('adq_identificacion', $array) && array_key_exists('adq_identificacion_multiples', $array) && !empty($array['adq_identificacion_multiples']))
        ) {
            $response = $this->checkAdquirenteMultiples($array['adq_identificacion_multiples']);
            //Los mensajes de error de checkAdquirenteMultiples se retornan en un array
            if (!empty($response['message'])) {
                foreach ($response['message'] as $msgError) {
                    $responseMultiples = $this->getErrorMsg($msgError);
                    $this->analyzeResponse($errores, $responseMultiples);
                }
            }
            foreach($array['adq_identificacion_multiples'] as $adqMultiple) {
                $adq_id_personalizado = isset($adqMultiple->adq_id_personalizado) && !empty($adqMultiple->adq_id_personalizado) ? $adqMultiple->adq_id_personalizado : null;
                $response = $this->checkAdquirente('adq_identificacion', $adqMultiple->adq_identificacion, (($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC) ? self::TIPO_VENDEDOR_DOCUMENTO_SOPORTE : self::TIPO_ADQUIRENTE), $ofe, true, $adq_id_personalizado);
                $this->analyzeResponse($errores, $response);
            }
        } elseif(
            (array_key_exists('adq_identificacion', $array) && !empty($array['adq_identificacion']) && array_key_exists('adq_identificacion_multiples', $array) && empty($array['adq_identificacion_multiples'])) ||
            (array_key_exists('adq_identificacion', $array) && !empty($array['adq_identificacion']) && !array_key_exists('adq_identificacion_multiples', $array))
        ) {
            $adq_id_personalizado = (array_key_exists('adq_id_personalizado', $array) && !empty($array['adq_id_personalizado'])) ? $array['adq_id_personalizado'] : null;
            $response = $this->checkAdquirente('adq_identificacion', $array['adq_identificacion'], (($this->docType === ConstantsDataInput::DS || $this->docType === ConstantsDataInput::DS_NC) ? self::TIPO_VENDEDOR_DOCUMENTO_SOPORTE : self::TIPO_ADQUIRENTE), $ofe, true, $adq_id_personalizado);
            $this->analyzeResponse($errores, $response);
        }

        $response = $this->checkAdquirente('adq_identificacion_autorizado', $array['adq_identificacion_autorizado'], self::TIPO_AUTORIZADO, $ofe, false, null);
        $this->analyzeResponse($errores, $response);

        if (
            array_key_exists('adq_identificacion', $array) && !empty($array['adq_identificacion']) &&
            array_key_exists('adq_identificacion_autorizado', $array) && !empty($array['adq_identificacion_autorizado']) &&
            $array['adq_identificacion'] === $array['adq_identificacion_autorizado']
        )
            $this->analyzeResponse($errores, $this->getErrorMsg('El adquirente y la persona autorizada son el mismo.'));

        // Chequeando la moneda local
        $response = $this->checkMoneda($array['mon_codigo'], 'mon_codigo');
        $this->analyzeResponse($errores, $response);

        // Si se ha proporcionado la moneda extranjera
        if (!empty($array['mon_codigo_extranjera'])) {
            $response = $this->checkMoneda($array['mon_codigo_extranjera'], 'mon_codigo_extranjera');
            $this->analyzeResponse($errores, $response);

            if (!$response['error'] && $array['mon_codigo'] === $array['mon_codigo_extranjera'])
                $errores[] = "Los campos mon_codigo y mon_codigo_extranjera deben contener valores diferentes";
        }

        if (!empty($array['cdo_observacion'])) {
            if (is_array($array['cdo_observacion'])) {
                foreach ($array['cdo_observacion'] as $key => $item)
                    if (!is_string($item) && !empty($item))
                        $errores[] = "El campo cdo_observacion[{$key}] no es una cadena";
            }
            elseif (!is_null($array['cdo_observacion'])) {
                if (!is_string($array['cdo_observacion']) && !empty($item))
                    $errores[] = "El campo cdo_observacion no es una cadena o un array";
                else
                    $array['cdo_observacion'] = [$array['cdo_observacion']];
            } else
                $errores[] = 'Falta el campo cdo_observacion.';
        }

        // Solo para notas credito y notas debito
        if (array_key_exists('cdo_conceptos_correccion', $array) && !is_null($array['cdo_conceptos_correccion'])) {
            $response = $this->validateArrayObjects($array['cdo_conceptos_correccion'],
                ['cco_codigo', 'cdo_observacion_correccion'], 'cdo_conceptos_correccion',
                $this->rulesCdoConceptosCorreccion, true);
            $this->analyzeResponse($errores, $response);
            if (!$response['error']) {
                foreach ($array['cdo_conceptos_correccion'] as $item) {
                    if (!array_key_exists($item->cco_codigo, $this->conceptosCorreccion))
                        $errores[] = "El concepto de corrección {$item->cco_codigo} no existe";
                }
            }
        } else {
            if ($this->docType === ConstantsDataInput::NC || $this->docType === ConstantsDataInput::ND || $this->docType === ConstantsDataInput::NC_ND || $this->docType === ConstantsDataInput::DS_NC)
                $errores[] = "La clave cdo_conceptos_correccion es obligatoria para NC,ND,DS_NC";
        }

        // cdo_documento_referencia
        if (array_key_exists('cdo_documento_referencia', $array) && !is_null($array['cdo_documento_referencia'])) {
            if (
                ($this->docType === ConstantsDataInput::NC && $array['top_codigo'] != '22') ||
                ($this->docType === ConstantsDataInput::ND && $array['top_codigo'] != '32') ||
                ($this->docType === ConstantsDataInput::NC_ND && ($array['top_codigo'] != '22' || $array['top_codigo'] != '32'))
            ) {
                // Nota de credito con referencia a documento electronico el cufe es obligatorio
                $opcionales = ['prefijo'];
                //modificando la regla del campo cufe
                $this->rulesCdoDocumentoReferencia['cufe'] = 'required|string';
            } else {
                // Nota Credito o Debito sin referencia a documento electronico el cufe no es obligatorio
                $opcionales = ['prefijo', 'cufe'];
                //modificando la regla del campo cufe
                $this->rulesCdoDocumentoReferencia['cufe'] = 'nullable|string';
            }

            // Si es una factura y el tipo de operacion es diferente al sector salud, 
            // solo se permite en el documento de referencia el tipo de clasificacion ND, NC
            // para el sector salud se permite enviar FC
            $sectoresTipoOperacion = array_key_exists($array['top_codigo'] . '~' . $this->docType, $this->tipoOperaciones) ? explode(',', $this->tipoOperaciones[$array['top_codigo'] . '~' . $this->docType]->top_sector) : [];
            if ($this->docType === ConstantsDataInput::FC && !in_array(self::SECTOR_SALUD, $sectoresTipoOperacion)) {
                $this->rulesCdoDocumentoReferencia['clasificacion'] = 'required|in:NC,ND';
            } else if($this->docType === ConstantsDataInput::DS_NC) {
                $this->rulesCdoDocumentoReferencia['clasificacion'] = 'required|in:DS,DS_NC';
            } else {
                $this->rulesCdoDocumentoReferencia['clasificacion'] = 'required|in:FC,NC,ND';
            }

            $opcionales = array_merge($opcionales, ['atributo_consecutivo_id', 'atributo_consecutivo_name', 'atributo_consecutivo_agency_id', 'atributo_consecutivo_version_id', 'uuid', 'atributo_uuid_name', 'codigo_tipo_documento', 'atributo_codigo_tipo_documento_list_uri', 'tipo_documento']);
            $response = $this->validateArrayObjects($array['cdo_documento_referencia'],
                KeyMap::$clavesCdoDocumentoReferencia, 'cdo_documento_referencia',
                $this->rulesCdoDocumentoReferencia, true, $opcionales);
            $this->analyzeResponse($errores, $response);
        } else {
            if (
                ($this->docType === ConstantsDataInput::NC && $array['top_codigo'] != '22') ||
                ($this->docType === ConstantsDataInput::ND && $array['top_codigo'] != '32') ||
                $this->docType === ConstantsDataInput::DS_NC ||
                ($this->docType === ConstantsDataInput::NC_ND &&
                    ($array['top_codigo'] != '22' && $array['top_codigo'] != '32')
                )
            )
                $errores[] = "La sección cdo_documento_referencia es obligatoria para {$this->docType}";
        }
        return $errores;
    }

    /**
     *  Efecuta los procesos de validacion sobre los datos de cabecera dad en json
     *
     * @param array $array Datos de cabecera a ser validados
     * @param string $tde_codigo Código del tipo de documento electrónico
     * @param string $top_codigo Código del tipo de operación
     * @param string $adq_identificacion Identificacion del adquirente
     * @param string $adq_id_personalizado ID personalizado del adquirente
     * @return array
     */
    private function checkDad(array $array, $tde_codigo, $top_codigo, $adq_identificacion, $adq_id_personalizado = null) {
        $errores = [];
        // dad_orden_referencia
        if (array_key_exists('dad_orden_referencia', $array) && !is_null($array['dad_orden_referencia'])) {
            $opcionales = ['fecha_emision_referencia', 'referencia'];
            $response = $this->validateObject($array['dad_orden_referencia'],
                ['referencia', 'fecha_emision_referencia'], 'dad_orden_referencia', $this->rulesDadReferencia, true, $opcionales);
            $this->analyzeResponse($errores, $response);
        }

        // dad_despacho_referencia
        if (array_key_exists('dad_despacho_referencia', $array) && !is_null($array['dad_despacho_referencia'])) {
            $opcionales = ['fecha_emision_referencia', 'referencia'];
            $response = $this->validateObject($array['dad_despacho_referencia'],
                ['referencia', 'fecha_emision_referencia'], 'dad_despacho_referencia', $this->rulesDadReferencia, true, $opcionales);
            $this->analyzeResponse($errores, $response);
        }

        // dad_recepcion_referencia
        if (array_key_exists('dad_recepcion_referencia', $array) && !is_null($array['dad_recepcion_referencia'])) {
            $opcionales = ['fecha_emision_referencia', 'referencia'];
            $response = $this->validateObject($array['dad_recepcion_referencia'],
                ['referencia', 'fecha_emision_referencia'], 'dad_recepcion_referencia', $this->rulesDadReferencia, true, $opcionales);
            $this->analyzeResponse($errores, $response);
        }

        // dad_condiciones_entrega
        if (array_key_exists('dad_condiciones_entrega', $array) && !is_null($array['dad_condiciones_entrega'])) {
            // Verifica si el adquirente es o no responsable de IVA para poder establecer los campos opcionales de condiciones de entrega
            $consAdquirente = ConfiguracionAdquirente::select(['adq_id', 'rfi_id'])
                ->where('adq_identificacion', $adq_identificacion)
                ->where('estado', 'ACTIVO');

            if(!empty($adq_id_personalizado))
                $consAdquirente = $consAdquirente->where('adq_id_personalizado', $adq_id_personalizado);

            $consAdquirente = $consAdquirente->with([
                    'getParametroRegimenFiscal:rfi_id,rfi_codigo'
                ])
                ->first();

            if($consAdquirente && isset($consAdquirente->getParametroRegimenFiscal->rfi_codigo) && $consAdquirente->getParametroRegimenFiscal->rfi_codigo == '48') {
                $opcionales = ['dad_entrega_bienes_hora', 'dad_entrega_bienes_codigo_postal'];
            } else {
                if(
                    (isset($array['dad_condiciones_entrega']->pai_codigo_entrega_bienes) && !empty($array['dad_condiciones_entrega']->pai_codigo_entrega_bienes)) ||
                    (isset($array['dad_condiciones_entrega']->dep_codigo_entrega_bienes) && !empty($array['dad_condiciones_entrega']->dep_codigo_entrega_bienes)) ||
                    (isset($array['dad_condiciones_entrega']->mun_codigo_entrega_bienes) && !empty($array['dad_condiciones_entrega']->mun_codigo_entrega_bienes)) ||
                    (isset($array['dad_condiciones_entrega']->dad_entrega_bienes_direccion) && !empty($array['dad_condiciones_entrega']->dad_entrega_bienes_direccion))
                ) {
                    if (isset($array['dad_condiciones_entrega']->pai_codigo_entrega_bienes) && $array['dad_condiciones_entrega']->pai_codigo_entrega_bienes != "CO") {
                        //Si el pais NO es Ccolombia no obliga departamento
                        $opcionales = ['dad_entrega_bienes_hora', 'dep_codigo_entrega_bienes', 'dad_entrega_bienes_codigo_postal'];
                    } else {
                        //Si el pais es colombia obliga todos los datos de direccion
                        $opcionales = ['dad_entrega_bienes_hora', 'dad_entrega_bienes_codigo_postal'];
                    }
                } else {
                    //No envio ningun dato de direccion, por lo cual no son obligatorios
                    $opcionales = ['dad_entrega_bienes_hora', 'pai_codigo_entrega_bienes', 'dep_codigo_entrega_bienes', 'mun_codigo_entrega_bienes', 'dad_entrega_bienes_direccion', 'dad_entrega_bienes_codigo_postal'];
                }
            }
            $response = $this->validateObject($array['dad_condiciones_entrega'],
                [
                    'dad_entrega_bienes_fecha',
                    'dad_entrega_bienes_hora',
                    'pai_codigo_entrega_bienes',
                    'dep_codigo_entrega_bienes',
                    'mun_codigo_entrega_bienes',
                    'dad_entrega_bienes_codigo_postal',
                    'dad_entrega_bienes_direccion'
                ], 'dad_condiciones_entrega', $this->rulesDadCondicionesEntrega, true, $opcionales);

            $this->analyzeResponse($errores, $response);
            if (!$response['error']) {
                $keys = [
                    'pai_key' => ['name' => 'pai_codigo_entrega_bienes', 'required' => false],
                    'dep_key' => ['name' => 'dep_codigo_entrega_bienes', 'required' => false],
                    'mun_key' => ['name' => 'mun_codigo_entrega_bienes', 'required' => false],
                ];
                $response = $this->checkPaisDepartamentoMunicipio((array)$array['dad_condiciones_entrega'], $keys,
                    'dad_condiciones_entrega');
                $this->analyzeResponse($errores, $response);
                if (isset($array['dad_condiciones_entrega']->dad_entrega_bienes_codigo_postal)) {
                    if (!array_key_exists($array['dad_condiciones_entrega']->dad_entrega_bienes_codigo_postal, $this->codigosPostales)) {
                        //Si no esta, se verifica si el pais es colombia y el codigo postal enviado es el departamento y municipio concatenados
                        if (!($array['dad_condiciones_entrega']->pai_codigo_entrega_bienes == "CO" && $array['dad_condiciones_entrega']->dad_entrega_bienes_codigo_postal == $array['dad_condiciones_entrega']->dep_codigo_entrega_bienes . $array['dad_condiciones_entrega']->mun_codigo_entrega_bienes)) {
                            $errores[] = "El código postal {$array['dad_condiciones_entrega']->dad_entrega_bienes_codigo_postal} en las condiciones de entrega no existe";
                        }
                    }
                }
            }
        }

        // dad_entrega_bienes_despacho
        if (array_key_exists('dad_entrega_bienes_despacho', $array) && !is_null($array['dad_entrega_bienes_despacho'])) {
            $opcionales = [
                'dad_entrega_bienes_despacho_identificacion_transportador',
                'dad_entrega_bienes_despacho_identificacion_transporte',
                'dad_entrega_bienes_despacho_tipo_transporte',
                'dad_entrega_bienes_despacho_fecha_solicitada',
                'dad_entrega_bienes_despacho_hora_solicitada',
                'dad_entrega_bienes_despacho_fecha_estimada',
                'dad_entrega_bienes_despacho_hora_estimada',
                'dad_entrega_bienes_despacho_fecha_real',
                'dad_entrega_bienes_despacho_hora_real',
                'dad_entrega_bienes_despacho_codigo_postal',
                'dad_entrega_bienes_despacho_direccion'
            ];
            $response = $this->validateObject($array['dad_entrega_bienes_despacho'],
                [
                    'dad_entrega_bienes_despacho_identificacion_transportador',
                    'dad_entrega_bienes_despacho_identificacion_transporte',
                    'dad_entrega_bienes_despacho_tipo_transporte',
                    'dad_entrega_bienes_despacho_fecha_solicitada',
                    'dad_entrega_bienes_despacho_hora_solicitada',
                    'dad_entrega_bienes_despacho_fecha_estimada',
                    'dad_entrega_bienes_despacho_hora_estimada',
                    'dad_entrega_bienes_despacho_fecha_real',
                    'dad_entrega_bienes_despacho_hora_real',
                    'pai_codigo_entrega_bienes_despacho',
                    'dep_codigo_entrega_bienes_despacho',
                    'mun_codigo_entrega_bienes_despacho',
                    'dad_entrega_bienes_despacho_codigo_postal',
                    'dad_entrega_bienes_despacho_direccion'
                ], 'dad_entrega_bienes_despacho', $this->rulesDadEntregaBienesDespacho, true, $opcionales);
            $this->analyzeResponse($errores, $response);
            if (!$response['error']) {
                $keys = [
                    'pai_key' => ['name' => 'pai_codigo_entrega_bienes_despacho', 'required' => false],
                    'dep_key' => ['name' => 'dep_codigo_entrega_bienes_despacho', 'required' => false],
                    'mun_key' => ['name' => 'mun_codigo_entrega_bienes_despacho', 'required' => false],
                ];
                $response = $this->checkPaisDepartamentoMunicipio((array)$array['dad_entrega_bienes_despacho'], $keys,
                    'dad_entrega_bienes_despacho');
                $this->analyzeResponse($errores, $response);
                if (isset($array['dad_entrega_bienes_despacho']->dad_entrega_bienes_despacho_codigo_postal)) {
                    if (!array_key_exists($array['dad_entrega_bienes_despacho']->dad_entrega_bienes_despacho_codigo_postal, $this->codigosPostales))
                        $errores[] = "El código postal {$array['dad_entrega_bienes_despacho']->dad_entrega_bienes_despacho_codigo_postal} en entrega bienes despacho no existe";
                }
            }
        }

        // dad_terminos_entrega
        if (array_key_exists('dad_terminos_entrega', $array) && !is_null($array['dad_terminos_entrega'])) {
            if(($tde_codigo == '91' || $tde_codigo == '92') && count($array['dad_terminos_entrega']) > 1) {
                $errores[] = "El tipo de documento electrónico [{$tde_codigo}] solo puede contener un objeto en el campo dad_terminos_entrega y el documento contiene [" . count($array['dad_terminos_entrega']) . "]";
            }

            $opcionales = [
                'dad_terminos_entrega_condiciones_pago',
                'pai_codigo_terminos_entrega',
                'dep_codigo_terminos_entrega',
                'mun_codigo_terminos_entrega',
                'dad_terminos_entrega_codigo_postal',
                'dad_terminos_entrega_direccion',
                'dad_detalle_descuentos',
            ];
            $response = $this->validateArrayObjects($array['dad_terminos_entrega'],
                [
                    'dad_terminos_entrega_condiciones_pago',
                    'cen_codigo',
                    'pai_codigo_terminos_entrega',
                    'dep_codigo_terminos_entrega',
                    'mun_codigo_terminos_entrega',
                    'dad_terminos_entrega_codigo_postal',
                    'dad_terminos_entrega_direccion',
                    'dad_detalle_descuentos',
                    'dad_numero_linea'
                ], 'dad_terminos_entrega', $this->rulesDadTerminosEntrega, true, $opcionales);

            $this->analyzeResponse($errores, $response);
            if (!$response['error']) {
                foreach($array['dad_terminos_entrega'] as $dadTerminosEntrega) {
                    $keys = [
                        'pai_key' => ['name' => 'pai_codigo_terminos_entrega', 'required' => false],
                        'dep_key' => ['name' => 'dep_codigo_terminos_entrega', 'required' => false],
                        'mun_key' => ['name' => 'mun_codigo_terminos_entrega', 'required' => false],
                    ];
                    $response = $this->checkPaisDepartamentoMunicipio((array)$dadTerminosEntrega, $keys, 'dad_terminos_entrega');
                    $this->analyzeResponse($errores, $response);

                    if (isset($dadTerminosEntrega->dad_terminos_entrega_codigo_postal)) {
                        if (!array_key_exists($dadTerminosEntrega->dad_terminos_entrega_codigo_postal, $this->codigosPostales))
                            $errores[] = "El código postal {$dadTerminosEntrega->dad_terminos_entrega_codigo_postal} en terminos de entrega no existe";
                    }

                    // cen_codigo
                    $response = $this->checkCondicionesEntrega($dadTerminosEntrega->cen_codigo);
                    $this->analyzeResponse($errores, $response);

                    if (isset($dadTerminosEntrega->dad_detalle_descuentos) && !is_null($dadTerminosEntrega->dad_detalle_descuentos)) {
                        // dad_detalle_descuentos
                        $opcionales = ['valor_moneda_extranjera'];
                        $response = $this->validateObject($dadTerminosEntrega->dad_detalle_descuentos,
                            ['tipo', 'descripcion', 'porcentaje', 'valor_moneda_nacional', 'valor_moneda_extranjera'],
                            'dad_terminos_entrega[dad_detalle_descuentos]', $this->rulesDetalleDescuentos, true, $opcionales);
                        $this->analyzeResponse($errores, $response);

                        if (!$response['error']) {
                            if (isset($dadTerminosEntrega->dad_detalle_descuentos->valor_moneda_nacional) && !is_null($dadTerminosEntrega->dad_detalle_descuentos->valor_moneda_nacional)) {
                                $response = $this->validateObject($dadTerminosEntrega->dad_detalle_descuentos->valor_moneda_nacional,
                                    ['base', 'valor'], 'dad_terminos_entrega[dad_detalle_descuentos][valor_moneda_nacional]',
                                    $this->rulesValores, true);
                                $this->analyzeResponse($errores, $response);
                            } else
                                $errores[] = sprintf('En el campo dad_terminos_entrega[dad_detalle_descuentos] falta la clave valor_moneda_nacional');

                            if (isset($dadTerminosEntrega->dad_detalle_descuentos->valor_moneda_extranjera) && !is_null($dadTerminosEntrega->dad_detalle_descuentos->valor_moneda_extranjera)) {
                                $response = $this->validateObject($dadTerminosEntrega->dad_detalle_descuentos->valor_moneda_extranjera,
                                    ['base', 'valor'], 'dad_terminos_entrega[dad_detalle_descuentos][valor_moneda_extranjera]',
                                    $this->rulesValores, true);
                                $this->analyzeResponse($errores, $response);
                            }

                            // Si el formato del objeto es el adecuado se analiza el calculo de los porcentajes
                            if (!$response['error']) {
                                $response = $this->checkPorcentajesCargosRetencionesDescuentos($dadTerminosEntrega->dad_detalle_descuentos,
                                    'dad_terminos_entrega[dad_detalle_descuentos]', $dadTerminosEntrega->dad_detalle_descuentos->tipo);
                                $errores = array_merge($errores, $response);
                            }
                        }
                    }
                }
            }
        }

        // dad_moneda_alternativa
        if (array_key_exists('dad_moneda_alternativa', $array) && !is_null($array['dad_moneda_alternativa'])) {
            $opcionales = [];

            if (isset($array['dad_moneda_alternativa']->dad_trm_alternativa) && !is_null($array['dad_moneda_alternativa']->dad_trm_alternativa)) {
                $this->rulesDadMonedaAlternativa['dad_trm_alternativa'] = [
                    'numeric',
                    'regex:/^[0-9]{1,13}(\.[0-9]{1,6})?$/',
                    function ($attribute, $value, $fail) {
                        if ($value <= 0) {
                            $fail('La TRM de la moneda alternativa debe ser mayor a cero');
                        }
                    }
                ];
            }

            $response = $this->validateObject($array['dad_moneda_alternativa'],
                ['dad_codigo_moneda_alternativa', 'dad_codigo_moneda_extranjera_alternativa', 'dad_trm_alternativa', 'dad_trm_fecha_alternativa'],
                'dad_moneda_alternativa',
                $this->rulesDadMonedaAlternativa, true, $opcionales);

            $this->analyzeResponse($errores, $response);

            if (isset($array['dad_moneda_alternativa']->dad_codigo_moneda_alternativa) && !is_null($array['dad_moneda_alternativa']->dad_codigo_moneda_alternativa)) {
                $response = $this->checkMoneda($array['dad_moneda_alternativa']->dad_codigo_moneda_alternativa, '[dad_moneda_alternativa]dad_codigo_moneda_alternativa');
                $this->analyzeResponse($errores, $response);
            }

            if (isset($array['dad_moneda_alternativa']->dad_codigo_moneda_extranjera_alternativa) && !is_null($array['dad_moneda_alternativa']->dad_codigo_moneda_extranjera_alternativa)) {
                $response = $this->checkMoneda($array['dad_moneda_alternativa']->dad_codigo_moneda_extranjera_alternativa, '[dad_moneda_alternativa]dad_codigo_moneda_extranjera_alternativa');
                $this->analyzeResponse($errores, $response);
            }
        }

        // cdo_interoperabilidad
        if (array_key_exists('cdo_interoperabilidad', $array) && !is_null($array['cdo_interoperabilidad'])) {
            $opcionales = ['informacion_general'];
            $response = $this->validateArrayObjects($array['cdo_interoperabilidad'],
                ['informacion_general', 'interoperabilidad'], 'cdo_interoperabilidad',
                $this->rulesDadInteroperabilidad, true, $opcionales);
            
            $this->analyzeResponse($errores, $response);

            if (isset($array['cdo_interoperabilidad']->informacion_general) && !is_null($array['cdo_interoperabilidad']->informacion_general)) {
                $response = $this->validateArrayObjects($array['cdo_interoperabilidad']->informacion_general,
                    ['nombre', 'valor'], 'cdo_interoperabilidad->informacion_general',
                    $this->rulesInteroprabilidadCamposAdicionales, true, []);
                $this->analyzeResponse($errores, $response);
            }

            if (isset($array['cdo_interoperabilidad']->interoperabilidad) && !is_null($array['cdo_interoperabilidad']->interoperabilidad)) {
                $opcionales = ['grupo','interoperabilidad_pt'];
                $response = $this->validateObject($array['cdo_interoperabilidad']->interoperabilidad,
                    ['grupo', 'collection','interoperabilidad_pt'], 'cdo_interoperabilidad->interoperabilidad',
                    $this->rulesDadInteroperabilidadInteroperabilidad, true, $opcionales);
                $this->analyzeResponse($errores, $response);  
            }

            if (isset($array['cdo_interoperabilidad']->interoperabilidad->collection) && !is_null($array['cdo_interoperabilidad']->interoperabilidad->collection)) {
                $opcionales = ['nombre'];
                $response = $this->validateArrayObjects($array['cdo_interoperabilidad']->interoperabilidad->collection,
                    ['nombre', 'informacion_adicional'], 'cdo_interoperabilidad->interoperabilidad->collection',
                    $this->rulesDadInteroperabilidadCollection, true, $opcionales);
                $this->analyzeResponse($errores, $response);
            }

            if (isset($array['cdo_interoperabilidad']->interoperabilidad->collection->informacion_adicional) && !is_null($array['cdo_interoperabilidad']->interoperabilidad->collection->informacion_adicional)) {
                $opcionales = ['atributo_name', 'atributo_id'];
                $response = $this->validateArrayObjects($array['cdo_interoperabilidad']->interoperabilidad->collection->informacion_adicional,
                    ['nombre', 'valor', 'atributo_name', 'atributo_id'], 'cdo_interoperabilidad->interoperabilidad->collection->informacion_adicional',
                    $this->rulesInteroprabilidadInformacionAdicional, true, $opcionales);
                $this->analyzeResponse($errores, $response);
            }

            if (isset($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt) && !is_null($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt)) {
                $opcionales = ['argumentos_ws', 'argumentos_url'];
                $response = $this->validateObject($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt,
                    ['url', 'ws', 'argumentos_ws', 'argumentos_url'], 'cdo_interoperabilidad->interoperabilidad->interoperabilidad_pt',
                    $this->rulesInteroprabilidadPT, true, $opcionales);
                $this->analyzeResponse($errores, $response);

                if (isset($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt->argumentos_url) && !is_null($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt->argumentos_url)) {
                    $response = $this->validateArrayObjects($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt->argumentos_url,
                        ['nombre', 'valor'], 'cdo_interoperabilidad->interoperabilidad->interoperabilidad_pt->argumentos_url',
                        $this->rulesInteroprabilidadPTArgumentosWs, true, []);
                    $this->analyzeResponse($errores, $response);
                }

                if (isset($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt->argumentos_ws) && !is_null($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt->argumentos_ws)) {
                    $response = $this->validateArrayObjects($array['cdo_interoperabilidad']->interoperabilidad->interoperabilidad_pt->argumentos_ws,
                        ['nombre', 'valor'], 'cdo_interoperabilidad->interoperabilidad->interoperabilidad_pt->argumentos_ws',
                        $this->rulesInteroprabilidadPTArgumentosWs, true, []);
                    $this->analyzeResponse($errores, $response);
                }
            }
        }

        // cdo_documento_adicional
        if (array_key_exists('cdo_documento_adicional', $array) && !is_null($array['cdo_documento_adicional'])) {
            $opcionales = ['seccion', 'prefijo', 'cufe', 'atributo_consecutivo_id', 'atributo_consecutivo_name', 'atributo_consecutivo_agency_id', 'atributo_consecutivo_version_id', 'uuid', 'atributo_uuid_name', 'atributo_rod_codigo_list_uri', 'tipo_documento'];
            $response = $this->validateArrayObjects($array['cdo_documento_adicional'],
                KeyMap::$clavesCdoDocumentoAdicional, 'cdo_documento_adicional',
                $this->rulesCdoDocumentoAdicional, true, $opcionales);
            $this->analyzeResponse($errores, $response);

            // Verifica que dentro del array de objetos no venga más de un objeto que aplique a la sección 'referencia'
            $countDocumentoAdicionalReferencia = 0;
            foreach($array['cdo_documento_adicional'] as $documentoAdicional) {
                if(isset($documentoAdicional->seccion) && $documentoAdicional->seccion == 'referencia')
                    $countDocumentoAdicionalReferencia++;
            }
            if($countDocumentoAdicionalReferencia > 1)
                $errores[] = 'cdo_documento_adicional no puede incluir más de un elemento para la sección [referencia]';
        }

        // cdo_periodo_facturacion
        if (array_key_exists('cdo_periodo_facturacion', $array)) {
            if (!is_null($array['cdo_periodo_facturacion']) && !empty($array['cdo_periodo_facturacion'])){
                $opcionales = ['dad_periodo_hora_inicio', 'dad_periodo_hora_fin'];
                $response = $this->validateObject($array['cdo_periodo_facturacion'],
                    ['dad_periodo_fecha_inicio', 'dad_periodo_hora_inicio', 'dad_periodo_fecha_fin', 'dad_periodo_hora_fin'],
                    'cdo_periodo_facturacion', $this->rulesCdoPeriodoFacturacion, false, $opcionales, true);
                $this->analyzeResponse($errores, $response);
            }
            //else
            //    $errores[] = "El campo cdo_periodo_facturacion esta mal formado: Faltan los campos: dad_periodo_fecha_inicio, dad_periodo_fecha_fin";
        }

        // cdo_documento_referencia_linea
        if (array_key_exists('cdo_documento_referencia_linea', $array) && !is_null($array['cdo_documento_referencia_linea'])) {
            $opcionales = ['prefijo', 'atributo_consecutivo_id', 'atributo_consecutivo_name', 'atributo_consecutivo_agency_id', 'atributo_consecutivo_version_id', 'valor', 'atributo_valor_moneda', 'atributo_valor_concepto'];
            $response = $this->validateArrayObjects($array['cdo_documento_referencia_linea'],
                KeyMap::$clavesCdoDocumentoReferenciaLinea,
                'cdo_documento_referencia_linea',
                $this->rulesDadDocumentoReferenciaLinea, true, $opcionales);

            $this->analyzeResponse($errores, $response);
        }

        // cdo_referencia_adquirente
        if (array_key_exists('cdo_referencia_adquirente', $array) && !is_null($array['cdo_referencia_adquirente'])) {
            // Para el Sector Salud solo se permite el envío de un solo objeto en cdo_referencia_adquirente
            $sectoresTipoOperacion = array_key_exists($top_codigo . '~' . $this->docType, $this->tipoOperaciones) ? explode(',', $this->tipoOperaciones[$top_codigo . '~' . $this->docType]->top_sector) : [];
            if(in_array(self::SECTOR_SALUD, $sectoresTipoOperacion) && count($array['cdo_referencia_adquirente']) > 1) {
                $errores[] = "Para el SECTOR SALUD la propiedad cdo_referencia_adquirente solo puede contener un objeto";
            }

            $opcionales = ['atributo_id_name', 'nombre', 'postal_address_codigo_pais', 'postal_address_descripcion_pais', 'residence_address_id', 'residence_address_atributo_id_name', 'residence_address_nombre_ciudad', 'residence_address_direccion', 'codigo_pais', 'descripcion_pais'];

            // Para el Sector Salud, en la información del cdo_referencia_adquirente se pueden enviar las colummas tdo_codigo, nombres_usuario_beneficiario y apellidos_usuario_beneficiario
            if(in_array(self::SECTOR_SALUD, $sectoresTipoOperacion)) {
                $adicionalesSectorSalud = ['tdo_codigo', 'nombres_usuario_beneficiario', 'apellidos_usuario_beneficiario'];
                $opcionales = array_merge($opcionales, $adicionalesSectorSalud);

                KeyMap::$cdoReferenciaAdquirente = array_merge(KeyMap::$cdoReferenciaAdquirente, $adicionalesSectorSalud);

                $this->rulesDadReferenciaAdquirente['top_codigo']                   = 'nullable|string';
                $this->rulesDadReferenciaAdquirente['nombres_usuario_beneficiario'] = 'nullable|string';
                $this->rulesDadReferenciaAdquirente['apellidos_usuario_beneficiario'] = 'nullable|string';
            }

            $response = $this->validateArrayObjects(
                $array['cdo_referencia_adquirente'],
                KeyMap::$cdoReferenciaAdquirente,
                'cdo_referencia_adquirente',
                $this->rulesDadReferenciaAdquirente,
                true,
                $opcionales
            );

            $this->analyzeResponse($errores, $response);
        }

        return $errores;
    }

    /**
     * Efecuta los procesos de validacion sobre los datos de medios de pago en el json
     *
     * @param array $array
     * @param null $nit_ofe
     * @return array
     */
    private function checkMediosPagos(array $array, $nit_ofe = null, $cdoVencimiento = null) {
        $errores = [];
        $opcionales = ['men_fecha_vencimiento', 'men_identificador_pago', 'atributo_fpa_codigo_id', 'atributo_fpa_codigo_name'];
        $rulesMedio = $this->rulesMediosPago;

        if (!empty($array)) {
            // reglas de validacion cuando la forma de cobro fpa_codigo es credito (2) 
            // el medio de pago solo es obligatorio si es contado (1)
            // la fecha de vencimiento es obligatoria si es credito (2)
            if (isset($array[0]->fpa_codigo) && $array[0]->fpa_codigo !== '1' && $array[0]->fpa_codigo !== 1) {
                $rulesMedio['mpa_codigo']             = 'nullable|string|max:10';
                $rulesMedio['men_fecha_vencimiento']  = 'required|date_format:Y-m-d';
                $opcionales                           = ['mpa_codigo', 'men_identificador_pago', 'atributo_fpa_codigo_id', 'atributo_fpa_codigo_name'];
            }
        }

        // cdo_medios_pago
        $response = $this->validateArrayObjects($array, KeyMap::$clavesMediosPago, KeyMap::$claveCdoMedioPago, $rulesMedio, true, $opcionales);
        $this->analyzeResponse($errores, $response);
        if (!$response['error']) {
            $menFechaVencimiento = null;
            foreach ($array as $item) {
                if (!empty($item->fpa_codigo)) {
                    $response = $this->checkFormaPago($item->fpa_codigo);
                    $this->analyzeResponse($errores, $response);
                }

                if (!empty($item->mpa_codigo)) {
                    $response = $this->checkMedio($item->mpa_codigo);
                    $this->analyzeResponse($errores, $response);
                }

                if (isset($item->men_identificador_pago) && !is_null($item->men_identificador_pago)) {
                    $response = $this->validateArrayObjects($item->men_identificador_pago, ['id'],
                        KeyMap::$claveCdoMedioPago . '[men_identificador_pago]', $this->rulesMediosPagoIds, false);
                    $this->analyzeResponse($errores, $response);
                }

                if(isset($item->men_fecha_vencimiento) && ! empty($item->men_fecha_vencimiento) && $menFechaVencimiento === null) {
                    $menFechaVencimiento = $item->men_fecha_vencimiento;
                }
            }

            if($cdoVencimiento !== null && $menFechaVencimiento !== null && $cdoVencimiento != $menFechaVencimiento)
                $this->analyzeResponse($errores, ['error' => true, 'message' => 'La fecha de vencimiento del documento y la fecha de vencimiento del medio de pago deben ser iguales']);

            if($cdoVencimiento !== null && $cdoVencimiento !== '' && ($menFechaVencimiento === null || $menFechaVencimiento === ''))
                $this->analyzeResponse($errores, ['error' => true, 'message' => 'Si reporta fecha de vencimiento, debe enviar la fecha de vencimiento de la sección medio de pago']);
        }
        return $errores;
    }

    /**
     * Efectua la comprobacion de porcentajes en cargos, retenciones y descuentos
     * @param $objeto
     * @param $field
     * @param $tipo
     * @param $indice
     * @param bool $retencion
     * @return array
     */
    private function checkPorcentajesCargosRetencionesDescuentos($objeto, $field, $tipo, $indice = null, $retencion = false) {
        $union = $retencion ? 'de la' : 'del';

        $errores = [];
        $porcentaje = $objeto->porcentaje;
        if (isset($objeto->valor_moneda_nacional) && isset($objeto->valor_moneda_nacional->base) && isset($objeto->valor_moneda_nacional->valor)) {
            $calculo = $objeto->valor_moneda_nacional->base * $porcentaje / 100.00;
            $calculo = VP::redondeo($calculo, 2);
            if (!$this->compararFlotantes($calculo , floatval($objeto->valor_moneda_nacional->valor))) {
                if (!is_null($indice))
                    $errores[] = "El cálculo {$union} {$tipo}[".($indice+1)."] en el campo $field [".floatval($objeto->valor_moneda_nacional->valor)."] no corresponde a la base por el porcentaje [".$calculo."].";
                else
                    $errores[] = "El cálculo {$union} $tipo en el campo $field [".floatval($objeto->valor_moneda_nacional->valor)."] no corresponde a la base por el porcentaje [".$calculo."].";
            }
        }
        if (isset($objeto->valor_moneda_extranjera) && isset($objeto->valor_moneda_extranjera->base) && isset($objeto->valor_moneda_extranjera->valor) && !empty($objeto->valor_moneda_extranjera->base)) {
            $calculo = $objeto->valor_moneda_extranjera->base * $porcentaje / 100.00;
            $calculo = VP::redondeo($calculo, 2);
            if (!$this->compararFlotantes($calculo , floatval($objeto->valor_moneda_extranjera->valor))) {
                if (!is_null($indice))
                    $errores[] = "El cálculo {$union} {$tipo}[".($indice+1)."] en el campo $field [".floatval($objeto->valor_moneda_extranjera->valor)."] no corresponde a la base por el porcentaje, para moneda extranjera [".$calculo."].";
                else
                    $errores[] = "El cálculo {$union} $tipo en el campo $field [".floatval($objeto->valor_moneda_extranjera->valor)."] no corresponde a la base por el porcentaje, para moneda extranjera [".$calculo."].";
            }
        }

        return $errores;
    }

    /**
     * Efectua la validacion sobre los campos de tributos y renteciones
     *
     * @param mixed $objeto
     * @param string $field
     * @param int $key
     * @return array
     */
    private function checkPorcentajesImpuestos($objeto, $field, $key) {
        $impuesto = '';
        if ($objeto->tri_codigo === '01')
            $impuesto = 'IVA';
        elseif($objeto->tri_codigo === '04')
            $impuesto = 'IMPUESTO AL CONSUMO';
        elseif ($objeto->tri_codigo === '03')
            $impuesto = 'ICA';

        $errores = [];

        //Validando que el calculo de $objeto->iid_porcentaje * $objeto->iid_porcentaje->iid_base sea igual a 
        //$objeto->iid_valor, se permite diferencia de +-2
        if (isset($objeto->iid_porcentaje) && isset($objeto->iid_porcentaje->iid_base) && 
        isset($objeto->iid_porcentaje->iid_porcentaje) && isset($objeto->iid_porcentaje->iid_base) && 
        trim($objeto->iid_porcentaje->iid_base) != '' && trim($objeto->iid_porcentaje->iid_porcentaje) != '') {
        $calculo = $objeto->iid_porcentaje->iid_base * $objeto->iid_porcentaje->iid_porcentaje / 100.00;
        $calculo = VP::redondeo($calculo, 2);
        if (!$this->compararFlotantes($calculo, floatval($objeto->iid_valor), $objeto->tri_codigo))
            $errores[] = "El cálculo de $field {$impuesto} no corresponde a la base por el porcentaje";
        }

        //Validando que se envien todos campos requeridos para el calculo de las retenciones en moneda extranjera
        //Solo se valida si se envia alguno de los campos requeridos
        if (isset($objeto->iid_porcentaje->iid_base_moneda_extranjera) || isset($objeto->iid_valor_moneda_extranjera)) {
            if(isset($objeto->iid_porcentaje->iid_base_moneda_extranjera) && !isset($objeto->iid_valor_moneda_extranjera)) {
                $errores[] = "Campo $field {$impuesto} mal formado. Falta el campo: iid_valor_moneda_extranjera.";
            } elseif(!isset($objeto->iid_porcentaje->iid_base_moneda_extranjera) && isset($objeto->iid_valor_moneda_extranjera)) {
                $errores[] = "Campo $field {$impuesto} mal formado. Falta el campo: iid_base_moneda_extranjera.";
            }else {
                //Validando que el calculo de $objeto->iid_porcentaje * $objeto->iid_porcentaje->iid_base sea igual a 
                //$objeto->iid_valor_moneda_extranjera, se permite diferencia de +-2
                if (isset($objeto->iid_porcentaje) && isset($objeto->iid_porcentaje->iid_base_moneda_extranjera) && 
                    isset($objeto->iid_porcentaje->iid_porcentaje) && isset($objeto->iid_porcentaje->iid_base_moneda_extranjera) && !empty($objeto->iid_porcentaje->iid_base_moneda_extranjera) &&
                    trim($objeto->iid_porcentaje->iid_base_moneda_extranjera) != '' && trim($objeto->iid_porcentaje->iid_porcentaje) != '') {
                    $calculo = $objeto->iid_porcentaje->iid_base_moneda_extranjera * $objeto->iid_porcentaje->iid_porcentaje / 100.00;
                    $calculo = VP::redondeo($calculo, 2);
                    if (!$this->compararFlotantes($calculo, floatval($objeto->iid_valor_moneda_extranjera), $objeto->tri_codigo))
                        $errores[] = "El cálculo de $field {$impuesto} no corresponde a la base por el porcentaje, para moneda extranjera";
                }
            }
        }
        return $errores;
    }

    /**
     * Efecuta los procesos de validacion sobre los datos de anticipos en el json
     *
     * @param array $array
     * @return array
     */
    private function checkDetallesAnticipos(array $array) {
        $opcionales = ['ant_fecha_realizado', 'ant_hora_realizado', 'ant_instrucciones', 'ant_valor_moneda_extranjera'];
        $errores = [];
        // cdo_detalle_anticipos
        $response = $this->validateArrayObjects($array, ['ant_identificacion', 'ant_valor', 'ant_valor_moneda_extranjera',
            'ant_fecha_recibido', 'ant_fecha_realizado', 'ant_hora_realizado', 'ant_instrucciones'],
            KeyMap::$claveCdoDetalleAnticipos, $this->rulesDetalleAnticipos, true, $opcionales);
        $this->analyzeResponse($errores, $response);
        return $errores;
    }

    /**
     * Efecuta los procesos de validacion sobre los datos de cargos en el json.
     *
     * @param array $array Array con la información de cargos
     * @param string $ofe_identificacion Identificacion del OFE
     * @param string $cdo_sistema Permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     * @return array
     */
    private function checkCargos(array $array, string $ofe_identificacion = null, string $cdo_sistema = null) {
        $errores = [];
        // cdo_detalle_cargos
        $keys       = ['razon', 'nombre', 'porcentaje', 'valor_moneda_nacional', 'valor_moneda_extranjera'];
        $opcionales = ['nombre', 'valor_moneda_extranjera'];

        if($cdo_sistema == DataInputValidator::OPENETL_WEB) $keys[]       = 'dmc_codigo';
        if($cdo_sistema != DataInputValidator::OPENETL_WEB) $opcionales[] = 'dmc_codigo';

        $response = $this->validateArrayObjects($array, $keys, KeyMap::$claveCdoDetalleCargos, $this->rulesCargos, true, $opcionales);
        $this->analyzeResponse($errores, $response);
        if (!$response['error']) {
            foreach ($array as $key => $item) {
                if($cdo_sistema == DataInputValidator::OPENETL_WEB && isset($item->dmc_codigo) && !empty($item->dmc_codigo)) {
                    if (
                        !array_key_exists($ofe_identificacion, $this->facturacionWebCargos) ||
                        (
                            array_key_exists($ofe_identificacion, $this->facturacionWebCargos) &&
                            !array_key_exists(trim($item->dmc_codigo), $this->facturacionWebCargos[$ofe_identificacion])
                        )
                    )
                        $errores[] = "Para el cargo[$key], el dmc_codigo [" . trim($item->dmc_codigo) . "] no existe para el OFE [$ofe_identificacion] o no aplica para {$this->descriptionDocType}.";
                }

                if (isset($item->valor_moneda_nacional)) {
                    $response = $this->validateObject($item->valor_moneda_nacional,
                        ['base', 'valor'], KeyMap::$claveCdoDetalleCargos . '[valor_moneda_nacional]',
                        $this->rulesValores, true);
                    $this->analyzeResponse($errores, $response);
                } else
                    $errores[] = sprintf('Para el cargo[%d] no se ha incluido la clave valor_moneda_nacional', $key);

                if (isset($item->valor_moneda_extranjera)) {
                    $response = $this->validateObject($item->valor_moneda_extranjera,
                        ['base', 'valor'], KeyMap::$claveCdoDetalleCargos . '[valor_moneda_extranjera]',
                        $this->rulesValores, true);
                    $this->analyzeResponse($errores, $response);
                }

                // Si no hay erroes se evalua el calculo de porcentajes proporcionados
                if (!$response['error']) {
                    $response = $this->checkPorcentajesCargosRetencionesDescuentos($item, KeyMap::$claveCdoDetalleCargos, 'cargo', $key);
                    $errores = array_merge($errores, $response);
                }
            }
        }
        return $errores;
    }

    /**
     * Efecuta los procesos de validacion sobre los datos de descuentos en el json.
     *
     * @param array $array Array con la información de descuentos
     * @param string $ofe_identificacion Identificacion del OFE
     * @param string $cdo_sistema Permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     * @return array
     */
    private function checkDescuentos(array $array, string $ofe_identificacion = null, string $cdo_sistema = null) {
        $errores = [];
        // cdo_detalle_descuentos
        $keys       = ['cde_codigo', 'razon', 'nombre', 'porcentaje', 'valor_moneda_nacional', 'valor_moneda_extranjera'];
        $opcionales = ['nombre', 'valor_moneda_extranjera'];

        if($cdo_sistema == DataInputValidator::OPENETL_WEB) $keys[]       = 'dmd_codigo';
        if($cdo_sistema != DataInputValidator::OPENETL_WEB) $opcionales[] = 'dmd_codigo';

        $response = $this->validateArrayObjects($array, $keys, KeyMap::$claveCdoDetalleDescuentos, $this->rulesDescuentos, true, $opcionales);
        $this->analyzeResponse($errores, $response);
        if (!$response['error']) {
            foreach ($array as $key => $item) {
                if($cdo_sistema == DataInputValidator::OPENETL_WEB && isset($item->dmd_codigo) && !empty($item->dmd_codigo)) {
                    if (
                        !array_key_exists($ofe_identificacion, $this->facturacionWebDescuentos) ||
                        (
                            array_key_exists($ofe_identificacion, $this->facturacionWebDescuentos) &&
                            !array_key_exists(trim($item->dmd_codigo), $this->facturacionWebDescuentos[$ofe_identificacion])
                        )
                    )
                    $errores[] = "Para el descuento[$key], el dmd_codigo [" . trim($item->dmd_codigo) . "] no existe para el OFE [$ofe_identificacion] o no aplica para {$this->descriptionDocType}.";
                }

                $response = $this->checkCodigoDescuento($item->cde_codigo);
                $this->analyzeResponse($errores, $response);

                // La moneda nacional es obligatoria
                if (isset($item->valor_moneda_nacional)) {
                    $response = $this->validateObject($item->valor_moneda_nacional,
                        ['base', 'valor'], KeyMap::$claveCdoDetalleDescuentos . '[valor_moneda_nacional]',
                        $this->rulesValores, true);
                    $this->analyzeResponse($errores, $response);
                } else
                    $errores[] = sprintf('Para el descuento[%d] no se ha incluido la clave valor_moneda_nacional', $key);

                // La moneda extranjera es opcional
                if (isset($item->valor_moneda_extranjera)) {
                    $response = $this->validateObject($item->valor_moneda_extranjera,
                        ['base', 'valor'], KeyMap::$claveCdoDetalleDescuentos . '[valor_moneda_extranjera]',
                        $this->rulesValores, true);
                    $this->analyzeResponse($errores, $response);
                }

                // Si no hay erroes se evalua el calculo de porcentajes proporcionados
                if (!$response['error']) {
                    $response = $this->checkPorcentajesCargosRetencionesDescuentos($item, KeyMap::$claveCdoDetalleDescuentos, 'descuento', $key);
                    $errores = array_merge($errores, $response);
                }
            }
        }
        return $errores;
    }

    /**
     * Efecuta los procesos de validacion sobre las retenciones sugeridas en el json.
     *
     * @param array $array Array con la información de las retenciones sugeridas
     * @param string $cdo_sistema Permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     * @return array
     */
    private function checkRetencionesSugeridas(array $array, string $cdo_sistema = null) {
        $errores = [];
        // cdo_detalle_retenciones_sugeridas
        $keys       = ['tipo', 'razon', 'porcentaje', 'valor_moneda_nacional', 'valor_moneda_extranjera'];
        $opcionales = ['valor_moneda_extranjera'];

        if($cdo_sistema == DataInputValidator::OPENETL_WEB) $keys[]       = 'codigo_tributo';
        if($cdo_sistema != DataInputValidator::OPENETL_WEB) $opcionales[] = 'codigo_tributo';

        $response = $this->validateArrayObjects($array, $keys, KeyMap::$claveCdoRetencionesSugeridas, $this->rulesRetencionesSugeridas, true, $opcionales);
        $this->analyzeResponse($errores, $response);
        if (!$response['error']) {
            foreach ($array as $key => $item) {
                if($cdo_sistema == DataInputValidator::OPENETL_WEB) {
                    $response = $this->checkTributoYTipo($item->codigo_tributo, 'RETENCION');
                    if ($response['error']) 
                        $errores[] = "Para la retencion sugerida[$key], " . $response['message'];
                }

                if (!in_array(strtolower($item->tipo), $this->retencionesSugeridasPermitidas))
                    $errores[] = "El valor {$item->tipo} no es un valor permitido para las retenciones sugeridas";

                if (isset($item->valor_moneda_nacional)) {
                    $response = $this->validateObject($item->valor_moneda_nacional,
                        ['base', 'valor'], KeyMap::$claveCdoRetencionesSugeridas . '[valor_moneda_nacional]',
                        $this->rulesValores, true);
                    $this->analyzeResponse($errores, $response);
                } else
                    $errores[] = sprintf('Para la retención[%d] no se ha incluido la clave valor_moneda_nacional', $key);

                if (isset($item->valor_moneda_extranjera)) {
                    $response = $this->validateObject($item->valor_moneda_extranjera,
                        ['base', 'valor'], KeyMap::$claveCdoRetencionesSugeridas . '[valor_moneda_extranjera]',
                        $this->rulesValores, true);
                    $this->analyzeResponse($errores, $response);
                }

                if (!$response['error']) {
                    $response = $this->checkPorcentajesCargosRetencionesDescuentos($item, KeyMap::$claveCdoRetencionesSugeridas, 'retencion-sugerida', $key, true);
                    $errores = array_merge($errores, $response);
                }
            }
        }
        return $errores;
    }

    /**
     * Efecuta los procesos de validacion sobre los items en el json.
     *
     * @param array $array Array conteniendo los items del documento electrónico
     * @param string $top_codigo Tipo de operación del documento electrónico
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE
     * @param string $cdo_sistema Permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     * @return  $errores Array de errores si se encuentra alguno
     */
    private function checkItems(array $array, string $top_codigo = null, ConfiguracionObligadoFacturarElectronicamente $ofe = null, string $cdo_sistema = null) {
        $errores             = [];
        $this->ddo_secuencia = [];
        $ofe_identificacion  = $ofe->ofe_identificacion;

        // items
        $opcionales = [
            'ddo_descripcion_dos', 'ddo_descripcion_tres', 'ddo_notas', 'ddo_cantidad_paquete', 'ddo_marca',
            'ddo_modelo', 'ddo_codigo_vendedor', 'ddo_codigo_vendedor_subespecificacion', 'ddo_codigo_fabricante',
            'ddo_codigo_fabricante_subespecificacion', 'ddo_nombre_fabricante', 'pai_codigo', 'ddo_propiedades_adicionales',
            'ddo_nit_mandatario', 'ddo_cargos', 'ddo_descuentos', 'ddo_informacion_adicional', 'ddo_precio_referencia', 'ddo_datos_tecnicos',
            'ddo_valor_unitario_moneda_extranjera', 'ddo_total_moneda_extranjera', 'ddo_indicador_muestra', 'ddo_tdo_codigo_mandatario', 'ddo_identificador', 'ddo_identificacion_comprador', 'ddo_detalle_retenciones_sugeridas'
        ];

        $arrKey = array_values(KeyMap::$clavesItem);
        // Para los documentos soporte se elimina la posicion 34 => ddo_precio_referencia, esta llave no se debe enviar 
        if ($this->docType == ConstantsDataInput::DS || $this->docType == ConstantsDataInput::DS_NC) {
            unset($arrKey[34]);
            $arrKey = array_merge($arrKey, ['ddo_fecha_compra']);
        }

        // Construimos un array temporal para dejar solo los campos permitidos
        $response = $this->validateArrayObjects($array, $arrKey, KeyMap::$claveItems, $this->rulesItems, false, $opcionales);
        $this->analyzeResponse($errores, $response);

        if (!$response['error']) {
            foreach ($array as $K => $item) {
                $K++;
                if (array_key_exists($item->ddo_secuencia, $this->ddo_secuencia))
                    $errores[] = sprintf("El item[{$K}] tiene asignado un ddo_secuencia {{$item->ddo_secuencia}} que ya ha sido asignado previamente a otro item en este documento");
                else
                    $this->ddo_secuencia[$item->ddo_secuencia] = true;

                $ddo_secuencia = $item->ddo_secuencia;

                if ($top_codigo !== null && $top_codigo == '11' && isset($item->ddo_identificador)) {
                    $mandato = $this->checkMandato($item->ddo_identificador);
                    $this->analyzeResponse($errores, $mandato);
                } elseif ($top_codigo !== null && $top_codigo == '12' && isset($item->ddo_identificador)) {
                    $transporteRegistro = $this->checkTransporteRegistro($item->ddo_identificador);
                    $this->analyzeResponse($errores, $transporteRegistro);
                }

                if (isset($item->pai_codigo) && !empty($item->pai_codigo)) {
                    $pai_id = $this->checkPais($item->pai_codigo);
                    if ($pai_id === null)
                        $this->analyzeResponse($errores, $this->getErrorMsg("El código de país [{$item->pai_codigo}] no existe"));
                }

                if (isset($item->und_codigo) && !empty($item->und_codigo)) {
                    $response = $this->checkUnidad($item->und_codigo);
                    $this->analyzeResponse($errores, $response);
                }
                if (isset($item->cpr_codigo)) {
                    $response = $this->checkClasificacionProductos($item->cpr_codigo);
                    $this->analyzeResponse($errores, $response);
                    if (isset($item->ddo_tdo_codigo_mandatario) || isset($item->ddo_nit_mandatario)) {
                        if (!empty($item->ddo_nit_mandatario) && !empty($item->ddo_tdo_codigo_mandatario)) {
                            $response = $this->checkTipoDocumento($item->ddo_tdo_codigo_mandatario);
                            $this->analyzeResponse($errores, $response);
                        } else {
                            if (!empty($item->ddo_nit_mandatario))
                                $errores[] = "No se indico Nit Mandatario para el item de secuencia {$ddo_secuencia} pero si un código de tipo de documento";
                            if (!empty($item->ddo_tdo_codigo_mandatario))
                                $errores[] = "Se indico un código de tipo de documento para el item de secuencia {$ddo_secuencia} pero no el Nit Mandatario";
                        }
                    }
                }

                if (isset($item->ddo_notas)) {
                    if (!empty($item->ddo_notas)) {
                        if (is_string($item->ddo_notas))
                            $item->ddo_notas = [$item->ddo_notas];

                        $notas = is_array($item->ddo_notas) ? $item->ddo_notas : (array)($item->ddo_notas);
                        foreach ($notas as $key => $nota)
                            if (!is_string($nota) && !empty($nota))
                                $errores[] = "El campo ddo_notas[{$key}] no es una cadena o un array para el item de secuencia {$ddo_secuencia}";
                    }
                }

                // Validaciones para los documentos soporte
                if ($this->docType == ConstantsDataInput::DS || $this->docType == ConstantsDataInput::DS_NC) {
                    // Para los documentos soporte el campo ddo_total debe ser mayor a cero
                    if (isset($item->ddo_total) && $item->ddo_total == 0.00)
                        $errores[] = "El campo items tiene los siguientes errores: [El campo ddo_total debe ser mayor a cero]";

                    // Para los documentos soporte se valida la sección de ddo_fecha_compra
                    $response = $this->validateObject($item->ddo_fecha_compra,
                        ['fecha_compra', 'codigo'], "items [secuencia: {$ddo_secuencia}] ddo_fecha_compra",
                        $this->rulesDdoFechaCompras, true, $opcionales);
                    
                    $this->analyzeResponse($errores, $response);
                    if (!$response['error'] && isset($item->ddo_fecha_compra->codigo) && !empty($item->ddo_fecha_compra->codigo)) {
                        $response = $this->checkFormaGeneracionTransmision($item->ddo_fecha_compra->codigo);
                        $this->analyzeResponse($errores, $response);
                    }
                } else {
                    // Si es una muestra y es diferente de documentos soporte
                    if (isset($item->ddo_indicador_muestra) && strtolower($item->ddo_indicador_muestra) === 'true') {
                        $opcionales = ['ddo_valor_muestra_moneda_extranjera'];
                        $response = $this->validateObject($item->ddo_precio_referencia,
                            ['pre_codigo', 'ddo_valor_muestra', 'ddo_valor_muestra_moneda_extranjera'], "items[secuencia: {$ddo_secuencia}]ddo_precio_referencia",
                            $this->rulesDdoPrecioReferencia, true, $opcionales);
                        $this->analyzeResponse($errores, $response);
                        if (!$response['error'] && isset($item->ddo_precio_referencia->pre_codigo) && !empty($item->ddo_precio_referencia->pre_codigo)) {
                            $response = $this->checkPrecioReferencia($item->ddo_precio_referencia->pre_codigo);
                            $this->analyzeResponse($errores, $response);
                        }
                    } else {
                        if (isset($item->ddo_precio_referencia)) {
                            if ((isset($item->ddo_precio_referencia->pre_codigo) && !empty($item->ddo_precio_referencia->pre_codigo)) ||
                                (isset($item->ddo_precio_referencia->ddo_valor_muestra) && !empty($item->ddo_precio_referencia->ddo_valor_muestra)) ||
                                (isset($item->ddo_precio_referencia->ddo_valor_muestra_moneda_extranjera) && !empty($item->ddo_precio_referencia->ddo_valor_muestra_moneda_extranjera)))
                                $errores[] = "Si indica pre_codigo, ddo_valor_muestra o ddo_valor_muestra_moneda_extranjera, debe indicar que el items[secuencia: {$ddo_secuencia}] es una muestra comercial";
                        }
                    }
                }

                if (isset($item->ddo_cargos)) {
                    // Validando los cargos del Item
                    $keys       = ['razon', 'nombre', 'porcentaje', 'valor_moneda_nacional', 'valor_moneda_extranjera'];
                    $opcionales = ['nombre', 'valor_moneda_extranjera'];

                    if($cdo_sistema == DataInputValidator::OPENETL_WEB) $keys[]       = 'dmc_codigo';
                    if($cdo_sistema != DataInputValidator::OPENETL_WEB) $opcionales[] = 'dmc_codigo';

                    $response = $this->validateArrayObjects($item->ddo_cargos, $keys, "item[{$K}][ddo_cargos]", $this->rulesCargos, true, $opcionales);
                    $this->analyzeResponse($errores, $response);
                    if (!$response['error']) {
                        foreach ($item->ddo_cargos as $key => $cargo) {
                            if($cdo_sistema == DataInputValidator::OPENETL_WEB && isset($cargo->dmc_codigo) && !empty($cargo->dmc_codigo)) {
                                if (
                                    !array_key_exists($ofe_identificacion, $this->facturacionWebCargos) ||
                                    (
                                        array_key_exists($ofe_identificacion, $this->facturacionWebCargos) &&
                                        !array_key_exists(trim($cargo->dmc_codigo), $this->facturacionWebCargos[$ofe_identificacion])
                                    )
                                )
                                    $errores = array_merge($errores, ["Para el item[$K] cargo[$key], el dmc_codigo [" . trim($cargo->dmc_codigo) . "] no existe para el OFE [$ofe_identificacion]."]);
                            }

                            if (isset($cargo->valor_moneda_nacional)) {
                                $response = $this->validateObject($cargo->valor_moneda_nacional,
                                    ['base', 'valor'], "items[secuencia: {$ddo_secuencia}][cargos][valor_moneda_nacional]",
                                    $this->rulesValores, true);
                                $this->analyzeResponse($errores, $response);
                            } else
                                $errores[] = sprintf("Para el Item de secuencia {$ddo_secuencia}, el campo valor_moneda_nacional del cargo[%d] es obligatorio", $K, $key);

                            if (isset($cargo->valor_moneda_extranjera)) {
                                $response = $this->validateObject($cargo->valor_moneda_extranjera,
                                    ['base', 'valor'], "items[secuencia: {$ddo_secuencia}][cargos][valor_moneda_extranjera]",
                                    $this->rulesValores, true);
                                $this->analyzeResponse($errores, $response);
                            }

                            if (!$response['error']) {
                                $response = $this->checkPorcentajesCargosRetencionesDescuentos($cargo, "item[secuencia: {$ddo_secuencia}]ddo_cargos", 'cargo', $key);
                                $errores = array_merge($errores, $response);
                            }
                        }
                    }
                }

                if (isset($item->ddo_descuentos)) {
                    // Validando los descuentos del Item - Se uso la regla de cargos, porque en este caso no hay codigo de descuento
                    $keys       = ['razon', 'nombre', 'porcentaje', 'valor_moneda_nacional', 'valor_moneda_extranjera'];
                    $opcionales = ['nombre', 'valor_moneda_extranjera'];

                    if($cdo_sistema == DataInputValidator::OPENETL_WEB) $keys[]       = 'dmd_codigo';
                    if($cdo_sistema != DataInputValidator::OPENETL_WEB) $opcionales[] = 'dmd_codigo';

                    $response = $this->validateArrayObjects($item->ddo_descuentos, $keys, "item[{$K}][ddo_descuentos]", $this->rulesCargos, true, $opcionales);
                    $this->analyzeResponse($errores, $response);
                    if (!$response['error']) {
                        foreach ($item->ddo_descuentos as $key => $descuento) {
                            // $response = $this->checkCodigoDescuento($descuento->cde_codigo);
                            // $this->analyzeResponse($errores, $response);

                            if($cdo_sistema == DataInputValidator::OPENETL_WEB && isset($descuento->dmd_codigo) && !empty($descuento->dmd_codigo)) {
                                if (
                                    !array_key_exists($ofe_identificacion, $this->facturacionWebDescuentos) ||
                                    (
                                        array_key_exists($ofe_identificacion, $this->facturacionWebDescuentos) &&
                                        !array_key_exists(trim($descuento->dmd_codigo), $this->facturacionWebDescuentos[$ofe_identificacion])
                                    )
                                )
                                    $errores = array_merge($errores, ["Para el item[$K] descuento[$key], el dmd_codigo [" . trim($descuento->dmd_codigo) . "] no existe para el OFE [$ofe_identificacion]."]);
                            }

                            if (isset($descuento->valor_moneda_nacional)) {
                                $response = $this->validateObject($descuento->valor_moneda_nacional,
                                    ['base', 'valor'], "items[secuencia: {$ddo_secuencia}][descuento][valor_moneda_nacional]",
                                    $this->rulesValores, true);
                                $this->analyzeResponse($errores, $response);
                            } else
                                $errores[] = sprintf("Para el Item de secuencia {$ddo_secuencia}, el campo valor_moneda_nacional del descuento[%d] es obligatorio", $K, $key);

                            if (isset($descuento->valor_moneda_extranjera)) {
                                $response = $this->validateObject($descuento->valor_moneda_extranjera,
                                    ['base', 'valor'], "items[secuencia: {$ddo_secuencia}][descuento][valor_moneda_extranjera]",
                                    $this->rulesValores, true);
                                $this->analyzeResponse($errores, $response);
                            }

                            if (!$response['error']) {
                                $response = $this->checkPorcentajesCargosRetencionesDescuentos($descuento, "item[secuencia: {$ddo_secuencia}]ddo_descuentos", 'descuento', $key);
                                $errores = array_merge($errores, $response);
                            }
                        }
                    }
                }

                // Se evalua la estructura de los datos tecnicos si vienen
                if (isset($item->ddo_datos_tecnicos)) {
                    $response = $this->validateArrayObjects($item->ddo_datos_tecnicos, ['descripcion'],
                        KeyMap::$claveCdoDetalleDescuentos, $this->rulesDatosTecnicos, true);
                    $this->analyzeResponse($errores, $response);
                }

                // Analisis de información para marca en caso de llegar
                if(isset($item->ddo_marca) && !empty($item->ddo_marca)) {
                    if(!is_string($item->ddo_marca)) {
                        $marcas = is_array($item->ddo_marca) ? $item->ddo_marca : (array)($item->ddo_marca);
                        if(count($marcas) > 3) {
                            $errores[] = 'La cantidad máxima de marcas por item es tres (3) y el Item de secuencia [' . $ddo_secuencia . '] tiene ' . count($marcas) . ' marcas';
                        } else {
                            $item->ddo_marca = [];
                            foreach($marcas as $marca) {
                                if(strlen($marca) > 100) {
                                    $errores[] = 'Para el Item de secuencia [' . $ddo_secuencia . '], en el campo ddo_marca hay marcas que contienen más de 100 caracteres';
                                } else {
                                    $item->ddo_marca[] = $marca;
                                }
                            }
                        }
                    } else {
                        if(strlen($item->ddo_marca) > 100) {
                            $errores[] = 'Para el Item de secuencia [' . $ddo_secuencia . '], el campo ddo_marca contiene más de 100 caracteres';
                        } else {
                            $item->ddo_marca = [$item->ddo_marca];
                        }
                    }
                }

                // Analisis de información para modelo en caso de llegar
                if(isset($item->ddo_modelo) && !empty($item->ddo_modelo)) {
                    if(!is_string($item->ddo_modelo)) {
                        $modelos = is_array($item->ddo_modelo) ? $item->ddo_modelo : (array)($item->ddo_modelo);
                        if(count($modelos) > 3) {
                            $errores[] = 'La cantidad máxima de modelos por item es tres (3) y el Item de secuencia [' . $ddo_secuencia . '] tiene ' . count($modelos) . ' modelos';
                        } else {
                            $item->ddo_modelo = [];
                            foreach($modelos as $modelo) {
                                if(strlen($modelo) > 100) {
                                    $errores[] = 'Para el Item de secuencia [' . $ddo_secuencia . '], en el campo ddo_modelo hay modelos que contienen más de 100 caracteres';
                                } else {
                                    $item->ddo_modelo[] = $modelo;
                                }
                            }
                        }
                    } else {
                        if(strlen($item->ddo_modelo) > 100) {
                            $errores[] = 'Para el Item de secuencia [' . $ddo_secuencia . '], el campo ddo_modelo contiene más de 100 caracteres';
                        } else {
                            $item->ddo_modelo = [$item->ddo_modelo];
                        }
                    }
                }

                // Análisis de información apara propiedades adicionales en caso de llegar
                if(isset($item->ddo_propiedades_adicionales) && !empty($item->ddo_propiedades_adicionales)) {
                    if($top_codigo == '12') {
                        if(is_object($item->ddo_propiedades_adicionales)) {
                            // Si en propiedades adicionales se envia el campo nombre, 
                            // se valida que esten completos los datos de transporte
                            if (isset($item->ddo_propiedades_adicionales->nombre) && !empty($item->ddo_propiedades_adicionales->nombre)) {
                                $this->checkComposicionPropiedadesAdicionales($errores, $ddo_secuencia, $top_codigo, $item->ddo_propiedades_adicionales, '1');
                            }
                        } elseif(is_array($item->ddo_propiedades_adicionales)) {
                            foreach($item->ddo_propiedades_adicionales as $index => $propiedadAdicional) {
                                // Si en propiedades adicionales se envia el campo nombre, 
                                // se valida que esten completos los datos de transporte
                                if (isset($propiedadAdicional->nombre) && !empty($propiedadAdicional->nombre)) {
                                    $this->checkComposicionPropiedadesAdicionales($errores, $ddo_secuencia, $top_codigo, $propiedadAdicional, ($index + 1));
                                }
                            }
                        }
                    }
                }

                // Se evalua la estructura de la información de comprador si viene
                if (isset($item->ddo_identificacion_comprador)) {
                    $response = $this->validateArrayObjects($item->ddo_identificacion_comprador, ['id', 'atributo_consecutivo_id', 'atributo_consecutivo_name'],
                        'items->ddo_identificacion_comprador', $this->rulesDdoIdentificacionComprador, true, ['id', 'atributo_consecutivo_id', 'atributo_consecutivo_name']);
                    $this->analyzeResponse($errores, $response);
                }

                // Vallida si en información adicional del item llegan columnas personalizadas para validarlas
                if(isset($item->ddo_informacion_adicional) && !empty($item->ddo_informacion_adicional)) {
                    $validacion = $this->checkColumnasPersonalizadas(
                        (is_object($item->ddo_informacion_adicional) ? (array) $item->ddo_informacion_adicional : $item->ddo_informacion_adicional),
                        $ofe->toArray(),
                        true,
                        $item->ddo_secuencia
                    );

                    if(array_key_exists('errores', $validacion))
                        $errores = array_merge($errores, $validacion['errores']);

                    if(array_key_exists('informacion_adicional', $validacion))
                        $item->ddo_informacion_adicional = $validacion['informacion_adicional'];
                }

                // Se verifica si en información adicional llega el grupo de datos ddo_informacion_adicional_excluir_xml para realizar las validaciones correspondiente
                if(
                    isset($item->ddo_informacion_adicional) &&
                    isset($item->ddo_informacion_adicional->ddo_informacion_adicional_excluir_xml) && 
                    !empty($item->ddo_informacion_adicional->ddo_informacion_adicional_excluir_xml)
                ) {
                    if(!is_array($item->ddo_informacion_adicional->ddo_informacion_adicional_excluir_xml)) {
                        $continuar = false;
                        $errores[] = 'El documento incluye el grupo de datos [ddo_informacion_adicional_excluir_xml] pero no es un array';
                    } else {
                        $adicionalXml = (array)$item->ddo_informacion_adicional->ddo_informacion_adicional_excluir_xml;
                        
                        // Verifica que no existan elementos duplicados
                        if(count($adicionalXml) != count(array_unique($adicionalXml))) {
                            $errores[] = 'Se encontraron elementos duplicados en el grupo de datos [ddo_informacion_adicional_excluir_xml]';
                        }

                        // Verifica que en el grupo de datos no se incluyan los elementos cdo_procesar_documento o cdo_direccion_domicilio_correspondencia
                        if(
                            in_array('ddo_informacion_adicional_excluir_xml', $adicionalXml)
                        ) {
                            $errores[] = 'No puede incluir en el grupo de datos [ddo_informacion_adicional_excluir_xml] el valor ddo_informacion_adicional_excluir_xml';
                        }

                        // Verifica que los elementos del grupo cdo_informacion_adicional_excluir_xml existan y tengan valores en cdo_informacion_adicional
                        $informacionAdicional = (array) $item->ddo_informacion_adicional;
                        $keyInformacionAdicional = array_keys($informacionAdicional);
                        foreach($adicionalXml as $datoAdicional) {
                            if(!in_array($datoAdicional, $keyInformacionAdicional)) {
                                $errores[] = 'El campo [' . $datoAdicional . '] no existe dentro del grupo de datos [ddo_informacion_adicional_excluir_xml]';
                            }
                        }
                    }
                }
            }
        }

        return $errores;
    }

    /**
     * Efecuta los procesos de validacion sobre los tributos en el json.
     *
     * @param array $array Array con los tributos/retenciones a verificar
     * @param array $other Array conteniendo información necesaria para ñas validaciones
     * @param string $field Indica si se trata de tributos o retenciones
     * @return array
     */
    private function checkTributos(array $array, array $other, string $field) {
        $asignadosCabecera = [];
        $asignadosItems = [];

        if ($field === 'tributos') {
            $field      = 'impuestos';
            $campos     = KeyMap::$clavesImpuestos;
            $campos     = ['ddo_secuencia', 'tri_codigo', 'iid_valor', 'iid_valor_moneda_extranjera', 'iid_motivo_exencion', 'iid_porcentaje', 'iid_unidad', 'iid_nombre_figura_tributaria'];
            $opcionales = ['iid_porcentaje', 'iid_unidad', 'iid_motivo_exencion', 'iid_valor_moneda_extranjera', 'iid_nombre_figura_tributaria'];
        } else {
            $campos     = KeyMap::$clavesAutoretenciones;
            $campos     = ['ddo_secuencia', 'tri_codigo', 'iid_valor', 'iid_valor_moneda_extranjera', 'iid_motivo_exencion', 'iid_porcentaje', 'iid_nombre_figura_tributaria'];
            $opcionales = ['iid_porcentaje', 'iid_motivo_exencion', 'iid_valor_moneda_extranjera', 'iid_nombre_figura_tributaria'];
        }

        $errores  = [];
        $response = $this->validateArrayObjects($array, $campos, $field, $this->rulesTributos, true, $opcionales);
        $this->analyzeResponse($errores, $response);
        if (!$response['error']) {
            foreach ($array as $key => $item) {
                $response = $this->checkTributo($item->tri_codigo);
                $this->analyzeResponse($errores, $response);
                if (!$response['error']) {
                    if($item->tri_codigo == 'ZZ' && property_exists($item, 'iid_porcentaje') && !empty($item->iid_porcentaje))
                        $this->tributos[trim($item->tri_codigo)]['tri_tipo']  = 'TRIBUTO';
                    elseif($item->tri_codigo == 'ZZ' && property_exists($item, 'iid_unidad') && !empty($item->iid_unidad))
                        $this->tributos[trim($item->tri_codigo)]['tri_tipo']  = 'TRIBUTO-UNIDAD';
                    
                    if($this->tributos[trim($item->tri_codigo)]['tri_tipo'] == 'TRIBUTO' && (!isset($item->iid_porcentaje) || (isset($item->iid_porcentaje) && empty($item->iid_porcentaje)))) {
                        $errores[] = "El {$field}[$key] de código {$item->tri_codigo} es del tipo TRIBUTO y no se incluyó la sección iid_porcentaje";
                    } elseif($this->tributos[trim($item->tri_codigo)]['tri_tipo'] == 'TRIBUTO-UNIDAD' && (!isset($item->iid_unidad) || (isset($item->iid_unidad) && empty($item->iid_unidad)))) {
                        $errores[] = "El {$field}[$key] de código {$item->tri_codigo} es del tipo TRIBUTO-UNIDAD y no se incluyó la sección iid_unidad";
                    }

                    if(isset($item->iid_porcentaje) && !empty($item->iid_porcentaje) && isset($item->iid_unidad) && !empty($item->iid_unidad)) {
                        $errores[] = "El {$field}[$key] de código {$item->tri_codigo} y tipo {$this->tributos[trim($item->tri_codigo)]['tri_tipo']} contiene las secciones iid_procentaje y iid_unidad, solamente debe enviar una de esas dos secciones de acuerdo al tipo";
                    }

                    //if (!array_key_exists($item->ddo_secuencia, $this->ddo_secuencia))
                    //    $this->analyzeResponse($errores, $this->getErrorMsg("En campo {$field}[$key] tiene el valor ddo_secuencia {$item->ddo_secuencia} que no esta asociado a un item"))
                    if (!isset($item->ddo_secuencia) || empty($item->ddo_secuencia)) {
                        if (array_key_exists($item->tri_codigo, $asignadosCabecera))
                            $errores[] = "El {$field}[$key] de código {$item->tri_codigo} ya ha sido asignado a la cabecera";
                        $asignadosCabecera[$item->tri_codigo] = true;
                    } else {
                        if (!array_key_exists($item->tri_codigo, $asignadosCabecera)) {
                            if (!array_key_exists($item->ddo_secuencia, $asignadosItems))
                                $asignadosItems[$item->ddo_secuencia] = [];
                            $impuestoItem = $asignadosItems[$item->ddo_secuencia];
                            if (array_key_exists($item->tri_codigo, $impuestoItem) && $item->tri_codigo != 'ZZ')
                                $errores[] = "El {$field}[$key] de código {$item->tri_codigo} ya ha sido asignado al item ddo_secuencia {$item->ddo_secuencia}";
                            $impuestoItem[$item->tri_codigo] = true;
                            $asignadosItems[$item->ddo_secuencia] = $impuestoItem;
                        }
                        else
                            $errores[] = "El {$field}[$key] de código {$item->tri_codigo} ya ha sido asignado a la cabecera, no puede ser asignado a un item";
                    }

                    // Puede que no venga
                    if (isset($item->iid_porcentaje)) {
                        $response = $this->validateObject($item->iid_porcentaje, ['iid_base', 'iid_base_moneda_extranjera', 'iid_porcentaje'],
                            "{$field}[iid_porcentaje]", $this->rulesImpuestoPorcentaje, true, ['iid_base_moneda_extranjera']);
                        $this->analyzeResponse($errores, $response);
                        if (!$response['error']) {
                            $response = $this->checkPorcentajesImpuestos($item, $field, $key);
                            $errores = array_merge($errores, $response);
                        }
                    }

                    // Puede que no venga
                    if (isset($item->iid_unidad) && $field === 'impuestos') {
                        if(array_key_exists('cdo_envio_dian_moneda_extranjera', $other) && $other['cdo_envio_dian_moneda_extranjera'] == 'SI') {
                            $this->rulesImpuestoUnidad['iid_base_unidad_medida_moneda_extranjera'] = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,2})?$/';
                            $this->rulesImpuestoUnidad['iid_valor_unitario_moneda_extranjera']     = 'required|numeric|regex:/^[0-9]{1,13}(\.[0-9]{1,2})?$/';
                        }

                        $response = $this->validateObject($item->iid_unidad, ['iid_cantidad', 'und_codigo', 'iid_base_unidad_medida', 'iid_base_unidad_medida_moneda_extranjera', 'iid_valor_unitario', 'iid_valor_unitario_moneda_extranjera'],
                            "{$field}[iid_unidad]", $this->rulesImpuestoUnidad, true, ['iid_cantidad', 'iid_base_unidad_medida_moneda_extranjera', 'iid_valor_unitario_moneda_extranjera']);
                        $this->analyzeResponse($errores, $response);
                        if (isset($item->iid_unidad->und_codigo) && !empty($item->iid_unidad->und_codigo)) {
                            $response = $this->checkUnidad($item->iid_unidad->und_codigo);
                            $this->analyzeResponse($errores, $response);
                        }

                        $totalItemUnidad = (isset($item->iid_unidad->iid_cantidad) && isset($item->iid_unidad->iid_valor_unitario)) ? $item->iid_unidad->iid_cantidad * $item->iid_unidad->iid_valor_unitario : 0;
                        $totalItemUnidad = floatval(VP::redondeo($totalItemUnidad));
                        if (!$this->compararFlotantes($totalItemUnidad, floatval($item->iid_valor))) {
                            $errores[] = "En {$field}[$key] la cantidad [iid_cantidad] por el valor unitario [iid_valor_unitario] no es igual al valor [iid_valor]";
                        }

                        if(array_key_exists('cdo_envio_dian_moneda_extranjera', $other) && $other['cdo_envio_dian_moneda_extranjera'] == 'SI') {
                            $totalItemUnidadMonedaExtranjera = $item->iid_unidad->iid_cantidad * $item->iid_unidad->iid_valor_unitario_moneda_extranjera;
                            $totalItemUnidadMonedaExtranjera = floatval(VP::redondeo($totalItemUnidadMonedaExtranjera));
                            if (!$this->compararFlotantes($totalItemUnidadMonedaExtranjera, floatval($item->iid_valor_moneda_extranjera))) {
                                $errores[] = "En {$field}[$key] la cantidad [iid_cantidad] por el valor unitario de moneda extranjera [iid_valor_unitario_moneda_extranjera] no es igual al valor en moneda extranjera [iid_valor_moneda_extranjera]";
                            }
                        }
                    }

                    // Si el código de tributo es ZZ se debe enviar el campo iid_nombre_figura_tributaria
                    if (
                        $item->tri_codigo == 'ZZ' && 
                        (
                            !isset($item->iid_nombre_figura_tributaria) ||
                            (isset($item->iid_nombre_figura_tributaria) && empty($item->iid_nombre_figura_tributaria))
                        )
                    ) {
                        $errores[] = "El {$field}[$key] tiene código de tributo [{$item->tri_codigo}] y no existe el campo [iid_nombre_figura_tributaria] o esta vacio";
                    }
                }
            }
        }
        return $errores;
    }

    /**
     * Verifica si el OFE tiene configuradas columnas personalizadas en cabecera y/o items para poder validar los valores cuando llega en información adicional.
     *
     * @param array $informacionAdicional Array con información adicional de cabecera o de item
     * @param array $ofe Array con información del OFE
     * @param boolean $campoItem Indica si la validación se realizará sobre un campo personalizado a nivel de item
     * @param integer $itemSecuencia Secuencia del item
     * @return array Array conteniendo los índices errores e informacion_adicional
     */
    private function checkColumnasPersonalizadas(array $informacionAdicional, array $ofe, bool $campoItem = false, int $itemSecuencia = 0) {
        $errores = [];

        if ($this->docType == ConstantsDataInput::DS || $this->docType == ConstantsDataInput::DS_NC)
            $indiceColumnasPersonalizadas = $campoItem ? 'valores_personalizados_item_ds' : 'valores_personalizados_ds';
        else 
            $indiceColumnasPersonalizadas = $campoItem ? 'valores_personalizados_item' : 'valores_personalizados';

        $complementoMsg = ' en información adicional de cabecera';
        if($campoItem)
            $complementoMsg = ' en información adicional del ítem ['. $itemSecuencia .']';

        // Si el OFE tiene configurados valores_personalizados se debe verificar si en información adicional llega alguno de esos campos para validarlo
        if(
            array_key_exists('ofe_campos_personalizados_factura_generica', $ofe) &&
            !empty($ofe['ofe_campos_personalizados_factura_generica']) &&
            array_key_exists($indiceColumnasPersonalizadas, $ofe['ofe_campos_personalizados_factura_generica']) &&
            !empty($ofe['ofe_campos_personalizados_factura_generica'][$indiceColumnasPersonalizadas])
        ) {
            $camposPersonalizados = collect(array_map(function($valorPersonalizado) {
                foreach ($valorPersonalizado as $key => $value) {
                    if($key == 'campo') {
                        $valorPersonalizado[$key] = trim(strtolower($this->sanear_string(str_replace(' ', '_', $value))));
                    } else
                        $valorPersonalizado[$key] = $value;

                }
                
                return $valorPersonalizado;
            }, $ofe['ofe_campos_personalizados_factura_generica'][$indiceColumnasPersonalizadas]));

            foreach($informacionAdicional as $key => $valor) {
                // Si la llave corresponde a un campo configurado como campo personalizado, se debe realizar la validación conforme a la configuración para dicho campo personalizado
                $campoConfig = $camposPersonalizados->where('campo', $key)->values();
                if(!$campoConfig->isEmpty()) {
                    if (array_key_exists('obligatorio', $campoConfig[0]) && $campoConfig[0]['obligatorio'] == 'SI' && $valor == '')
                        $errores[] = 'El campo [' . $campoConfig[0]['campo'] . ']' . $complementoMsg . ' es obligatorio';

                    if (!empty($valor)) {
                        $validaCampo = $this->validarCampoPersonalizado($campoConfig[0], $valor, $campoItem, $itemSecuencia);

                        if($validaCampo['error'])
                            $errores[] = $validaCampo['message'];
                        else
                            $informacionAdicional[$key] = !empty($validaCampo['valor_por_defecto']) && empty($valor) ? $validaCampo['valor_por_defecto'] : $valor;
                    }
                }
            }
        }

        return [
            'errores'               => $errores,
            'informacion_adicional' => $informacionAdicional
        ];
    }

    /**
     * Configura los IDs de paises, deparamento y municipios
     * @param $pai
     * @param $dep
     * @param $mun
     * @param $paiDest
     * @param $depDest
     * @param $munDest
     * @param $target
     */
    private function resolvePaiDepMun($pai, $dep, $mun, $paiDest, $depDest, $munDest, &$target) {
        if (!empty($pai) && array_key_exists($pai, $this->paises)) {
            $target[$paiDest] = $this->paises[$pai];
            $key = sprintf("%d_%s", $target[$paiDest], $dep);
            $target[$depDest] = array_key_exists($key, $this->departamentos) ? $this->departamentos[$key] : null;
            $key = sprintf("%d_%d_%s", $target[$paiDest], $target[$depDest], $mun);
            $target[$munDest] = array_key_exists($key, $this->municipios) ? $this->municipios[$key] : null;
        } else {
            $target[$paiDest] = null;
            $target[$depDest] = null;
            $target[$munDest] = null;
        }
    }

    /**
     * Valida los valores contenidos en el documento
     *
     * @param $document
     * @return array
     */
    public function validateValores($document) {
        $errores = [];
        // Si es diferente de vacio, todas las validaciones fueran exitosas y por ende este es un codigo de moeneda valido
        $tieneMonedaExtranejera = (isset($document->cdo_valor_sin_impuestos_moneda_extranjera) && !empty($document->cdo_valor_sin_impuestos_moneda_extranjera)) ||
            (isset($document->cdo_impuestos_moneda_extranjera) && !empty($document->cdo_impuestos_moneda_extranjera)) ||
            (isset($document->cdo_cargos_moneda_extranjera) && !empty($document->cdo_cargos_moneda_extranjera)) ||
            (isset($document->cdo_descuentos_moneda_extranjera) && !empty($document->cdo_descuentos_moneda_extranjera)) ||
            (isset($document->cdo_retenciones_sugeridas_moneda_extranjera) && !empty($document->cdo_retenciones_sugeridas_moneda_extranjera)) ||
            (isset($document->cdo_anticipo_moneda_extranjera) && !empty($document->cdo_anticipo_moneda_extranjera)) ||
            (isset($document->cdo_redondeo_moneda_extranjera) && !empty($document->cdo_redondeo_moneda_extranjera));

        if (isset($document->{keyMap::$claveCdoDetalleCargos})) {
            $valor = isset($document->cdo_cargos) ? floatval($document->cdo_cargos) : 0.0;
            $valorMonedaExtranjera = isset($document->cdo_cargos_moneda_extranjera) ? floatval($document->cdo_cargos_moneda_extranjera) : 0.0;
            $response = $this->validarCargosRetencionesSugeridasDescuentos($document->{keyMap::$claveCdoDetalleCargos}, $tieneMonedaExtranejera, $valor, $valorMonedaExtranjera, keyMap::$claveCdoDetalleCargos);
            $errores = array_merge($errores, $response);
        } else {
            if (isset($document->cdo_cargos) && floatval($document->cdo_cargos > 0.0))
                $errores[] = "El valor de cdo_cargos es mayor a 0, a pesar que el documento no posee la llave cdo_detalle_cargos";
            if (isset($document->cdo_cargos_moneda_extranjera) && floatval($document->cdo_cargos_moneda_extranjera > 0.0))
                $errores[] = "El valor de cdo_cargos_moneda_extranjera es mayor a 0, a pesar que el documento no posee la llave cdo_detalle_cargos";
        }


        if (isset($document->{keyMap::$claveCdoDetalleDescuentos})) {
            $valor = isset($document->cdo_descuentos) ? floatval($document->cdo_descuentos) : 0.0;
            $valorMonedaExtranjera = isset($document->cdo_descuentos_moneda_extranjera) ? floatval($document->cdo_descuentos_moneda_extranjera) : 0.0;
            $response = $this->validarCargosRetencionesSugeridasDescuentos($document->{keyMap::$claveCdoDetalleDescuentos}, $tieneMonedaExtranejera, $valor, $valorMonedaExtranjera, keyMap::$claveCdoDetalleDescuentos);
            $errores = array_merge($errores, $response);
        } else {
            if (isset($document->cdo_descuentos) && floatval($document->cdo_descuentos > 0.0))
                $errores[] = "El valor de cdo_descuentos es mayor a 0, a pesar que el documento no posee la llave cdo_detalle_descuentos";
            if (isset($document->cdo_descuentos_moneda_extranjera) && floatval($document->cdo_descuentos_moneda_extranjera > 0.0))
                $errores[] = "El valor de cdo_descuentos_moneda_extranjera es mayor a 0, a pesar que el documento no posee la llave cdo_detalle_descuentos";
        }

        if (isset($document->{keyMap::$claveCdoRetencionesSugeridas})) {
            $valor = isset($document->cdo_retenciones_sugeridas) ? floatval($document->cdo_retenciones_sugeridas) : 0.0;
            $valorMonedaExtranjera = isset($document->cdo_retenciones_sugeridas_moneda_extranjera) ? floatval($document->cdo_retenciones_sugeridas_moneda_extranjera) : 0.0;
            $response = $this->validarCargosRetencionesSugeridasDescuentos($document->{keyMap::$claveCdoRetencionesSugeridas}, $tieneMonedaExtranejera, $valor, $valorMonedaExtranjera, keyMap::$claveCdoRetencionesSugeridas, $document->items);
            $errores = array_merge($errores, $response);
        } else {
            if (isset($document->cdo_retenciones_sugeridas) && floatval($document->cdo_retenciones_sugeridas > 0.0))
                $errores[] = "El valor de cdo_retenciones_sugeridas es mayor a 0, a pesar que el documento no posee la llave cdo_detalle_retenciones_sugeridas";
            if (isset($document->cdo_retenciones_sugeridas_moneda_extranjera) && floatval($document->cdo_retenciones_sugeridas_moneda_extranjera > 0.0))
                $errores[] = "El valor de cdo_retenciones_sugeridas_moneda_extranjera es mayor a 0, a pesar que el documento no posee la llave cdo_detalle_retenciones_sugeridas";
        }

        // Para los documentos soporte no se debe enviar la sección de cdo_detalle_anticipos y los campos de anticipos
        if (($this->docType == ConstantsDataInput::DS || $this->docType == ConstantsDataInput::DS_NC)) {
            if (isset($document->{keyMap::$claveCdoDetalleAnticipos}))
                $errores[] = "Sobra la llave: " . keyMap::$claveCdoDetalleAnticipos;

            if (isset($document->cdo_anticipo))
                $errores[] = "Sobra el campo: cdo_anticipo";

            if (isset($document->cdo_anticipo_moneda_extranjera))
                $errores[] = "Sobra el campo: cdo_anticipo_moneda_extranjera";
        } else {
            if (isset($document->{keyMap::$claveCdoDetalleAnticipos})) {
                $response = $this->validarAnticipos($document, $document->{keyMap::$claveCdoDetalleAnticipos}, $tieneMonedaExtranejera);
                $errores = array_merge($errores, $response);
            } else {
                if (isset($document->cdo_anticipo) && floatval($document->cdo_anticipo > 0.0))
                    $errores[] = "El valor de cdo_anticipo es mayor a 0, a pesar que el documento no posee la llave cdo_detalle_anticipos";
                if (isset($document->cdo_anticipo_moneda_extranjera) && floatval($document->cdo_anticipo_moneda_extranjera > 0.0))
                    $errores[] = "El valor de cdo_anticipo_moneda_extranjera es mayor a 0, a pesar que el documento no posee la llave cdo_detalle_anticipos";
            }
        }

        if (isset($document->{keyMap::$claveTributos})) {
            $valor = isset($document->cdo_impuestos) ? floatval($document->cdo_impuestos) : 0.0;
            $valorMonedaExtranjera = isset($document->cdo_impuestos_moneda_extranjera) ? floatval($document->cdo_impuestos_moneda_extranjera) : 0.0;
            $response = $this->validarTributos($document->{keyMap::$claveTributos}, $tieneMonedaExtranejera, $valor, $valorMonedaExtranjera, keyMap::$claveTributos);
            $errores = array_merge($errores, $response);
        } else {
            if (isset($document->cdo_impuestos) && floatval($document->cdo_impuestos > 0.0))
                $errores[] = "El valor de cdo_impuestos es mayor a 0, a pesar que el documento no posee la llave tributos";
            if (isset($document->cdo_impuestos_moneda_extranjera) && floatval($document->cdo_impuestos_moneda_extranjera > 0.0))
                $errores[] = "El valor de cdo_impuestos_moneda_extranjera es mayor a 0, a pesar que el documento no posee la llave tributos";
        }

        if (isset($document->{keyMap::$claveRetenciones})) {
            $valor = isset($document->cdo_retenciones) ? floatval($document->cdo_retenciones) : 0.0;
            $valorMonedaExtranjera = isset($document->cdo_retenciones_moneda_extranjera) ? floatval($document->cdo_retenciones_moneda_extranjera) : 0.0;
            $response = $this->validarTributos($document->{keyMap::$claveRetenciones}, $tieneMonedaExtranejera, $valor, $valorMonedaExtranjera, keyMap::$claveRetenciones);
            $errores = array_merge($errores, $response);
        } else {
            if (isset($document->cdo_retenciones) && floatval($document->cdo_retenciones > 0.0))
                $errores[] = "El valor de cdo_retenciones es mayor a 0, a pesar que el documento no posee la llave retenciones";
            if (isset($document->cdo_retenciones_moneda_extranjera) && floatval($document->cdo_retenciones_moneda_extranjera > 0.0))
                $errores[] = "El valor de cdo_retenciones_moneda_extranjera es mayor a 0, a pesar que el documento no posee la llave retenciones";
        }

        $response = $this->validarTotales($document, $tieneMonedaExtranejera);
        $errores = array_merge($errores, $response);

        // Si cuadra todos los totales
        // Impuesos suman
        if (empty($errores)) {
            /* if (isset($document->cdo_total) && isset($document->cdo_cargos) && isset($document->cdo_descuentos) && isset($document->cdo_retenciones) &&
                isset($document->cdo_anticipo) && isset($document->cdo_retenciones_sugeridas)
            ) {
                $maxDecimales = VP::maxDecimales([$document->cdo_valor_sin_impuestos, $document->cdo_impuestos, $document->cdo_cargos, $document->cdo_descuentos, $document->cdo_retenciones_sugeridas, ($document->cdo_redondeo)]);
                $this->cdo_valor_a_pagar = round($document->cdo_valor_sin_impuestos + $document->cdo_impuestos + $document->cdo_cargos - $document->cdo_descuentos - $document->cdo_retenciones_sugeridas + ($document->cdo_redondeo), $maxDecimales);
                $this->cdo_valor_a_pagar = ($this->cdo_valor_a_pagar > 0) ? $this->cdo_valor_a_pagar : 0;
            }

            $this->cdo_valor_a_pagar_moneda_extranjera = 0.00;
            if ($tieneMonedaExtranejera) {
                if (isset($document->cdo_total_moneda_extranjera) && isset($document->cdo_cargos_moneda_extranjera) && isset($document->cdo_descuentos_moneda_extranjera) &&
                    isset($document->cdo_retenciones_moneda_extranjera) && isset($document->cdo_anticipo_moneda_extranjera) && isset($document->cdo_retenciones_sugeridas_moneda_extranjera)
                ) {
                    $maxDecimales = VP::maxDecimales([$document->cdo_valor_sin_impuestos_moneda_extranjera, $document->cdo_impuestos_moneda_extranjera, $document->cdo_cargos_moneda_extranjera, $document->cdo_descuentos_moneda_extranjera, $document->cdo_retenciones_sugeridas_moneda_extranjera, ($document->cdo_redondeo_moneda_extranjera)]);
                    $this->cdo_valor_a_pagar_moneda_extranjera = round($document->cdo_valor_sin_impuestos_moneda_extranjera + $document->cdo_impuestos_moneda_extranjera + $document->cdo_cargos_moneda_extranjera - $document->cdo_descuentos_moneda_extranjera - $document->cdo_retenciones_sugeridas_moneda_extranjera + ($document->cdo_redondeo_moneda_extranjera), $maxDecimales);
                    $this->cdo_valor_a_pagar_moneda_extranjera = ($this->cdo_valor_a_pagar_moneda_extranjera > 0) ? $this->cdo_valor_a_pagar_moneda_extranjera : 0;
                }
            } */

            $valorSinImpuestos    = isset($document->cdo_valor_sin_impuestos) ? floatval($document->cdo_valor_sin_impuestos) : 0.0;
            $impuestos            = isset($document->cdo_impuestos) ? floatval($document->cdo_impuestos) : 0.0;
            $cargos               = isset($document->cdo_cargos) ? floatval($document->cdo_cargos): 0.0;
            $descuentos           = isset($document->cdo_descuentos) ? floatval($document->cdo_descuentos) : 0.0;
            $retencionesSugeridas = isset($document->cdo_retenciones_sugeridas) ? floatval($document->cdo_retenciones_sugeridas) : 0.0;
            $anticipos            = isset($document->cdo_anticipo) ? floatval($document->cdo_anticipo) : 0.0;
            $redondeo             = isset($document->cdo_redondeo) ? floatval($document->cdo_redondeo) : 0.0;

            // Para los tipos de operación del sector salud (top_sector conteniendo SECTOR_SALUD) se debe tener en cuenta el anticipo y se debe restar
            $sectoresTipoOperacion = array_key_exists($document->top_codigo . '~' . $this->docType, $this->tipoOperaciones) ? explode(',', $this->tipoOperaciones[$document->top_codigo . '~' . $this->docType]->top_sector) : [];
            if(in_array(self::SECTOR_SALUD, $sectoresTipoOperacion) && ($document->top_codigo == 'SS-CUFE' || $document->top_codigo == 'SS-CUDE' || $document->top_codigo == 'SS-POS' || $document->top_codigo == 'SS-SNum')) {
                $maxDecimales = VP::maxDecimales([$valorSinImpuestos, $impuestos, $cargos, $anticipos, $descuentos, $retencionesSugeridas, $redondeo]);
                $this->cdo_valor_a_pagar = VP::redondeo(round($valorSinImpuestos + $impuestos + $cargos - $anticipos - $descuentos - $retencionesSugeridas + ($redondeo), $maxDecimales), $maxDecimales);

                if($this->cdo_valor_a_pagar < 0) {
                    $errores[] = "Valor a pagar negativo. Verifique valores de anticipos, descuentos y/o retenciones sugeridas";
                }
            } else {
                $maxDecimales = VP::maxDecimales([$valorSinImpuestos, $impuestos, $cargos, $descuentos, $retencionesSugeridas, $redondeo]);
                $this->cdo_valor_a_pagar = VP::redondeo(round($valorSinImpuestos + $impuestos + $cargos - $descuentos - $retencionesSugeridas + ($redondeo), $maxDecimales), $maxDecimales);

                if($this->cdo_valor_a_pagar < 0) {
                    $errores[] = "Valor a pagar negativo. Verifique valores de descuentos y/o retenciones sugeridas";
                }

                if($anticipos > $this->cdo_valor_a_pagar) {
                    $errores[] = "El valor total del anticipo no puede ser mayor al valor total a pagar";
                }
            }

            if ($tieneMonedaExtranejera) {
                $valorSinImpuestos    = isset($document->cdo_valor_sin_impuestos_moneda_extranjera) ? floatval($document->cdo_valor_sin_impuestos_moneda_extranjera) : 0.0;
                $impuestos            = isset($document->cdo_impuestos_moneda_extranjera) ? floatval($document->cdo_impuestos_moneda_extranjera) : 0.0;
                $cargos               = isset($document->cdo_cargos_moneda_extranjera) ? floatval($document->cdo_cargos_moneda_extranjera): 0.0;
                $descuentos           = isset($document->cdo_descuentos_moneda_extranjera) ? floatval($document->cdo_descuentos_moneda_extranjera) : 0.0;
                $retencionesSugeridas = isset($document->cdo_retenciones_sugeridas_moneda_extranjera) ? floatval($document->cdo_retenciones_sugeridas_moneda_extranjera) : 0.0;
                $anticipos            = isset($document->cdo_anticipo_moneda_extranjera) ? floatval($document->cdo_anticipo_moneda_extranjera) : 0.0;
                $redondeo             = isset($document->cdo_redondeo_moneda_extranjera) ? floatval($document->cdo_redondeo_moneda_extranjera) : 0.0;

                // Para los tipos de operación del sector salud (top_sector conteniendo SECTOR_SALUD) se debe tener en cuenta el anticipo y se debe restar
                if(in_array(self::SECTOR_SALUD, $sectoresTipoOperacion) && ($document->top_codigo == 'SS-CUFE' || $document->top_codigo == 'SS-CUDE' || $document->top_codigo == 'SS-POS' || $document->top_codigo == 'SS-SNum')) {
                    $maxDecimales = VP::maxDecimales([$valorSinImpuestos, $impuestos, $cargos, $anticipos, $descuentos, $retencionesSugeridas, $redondeo]);
                    $this->cdo_valor_a_pagar_moneda_extranjera = VP::redondeo(round($valorSinImpuestos + $impuestos + $cargos - $anticipos - $descuentos - $retencionesSugeridas + ($redondeo), $maxDecimales), $maxDecimales);

                    if($this->cdo_valor_a_pagar_moneda_extranjera < 0) {
                        $errores[] = "Valor a pagar en moneda extranjera negativo. Verifique valores de anticipos, descuentos y/o retenciones sugeridas";
                    }
                } else {
                    $maxDecimales = VP::maxDecimales([$valorSinImpuestos, $impuestos, $cargos, $descuentos, $retencionesSugeridas, $redondeo]);
                    $this->cdo_valor_a_pagar_moneda_extranjera = VP::redondeo(round($valorSinImpuestos + $impuestos + $cargos - $descuentos - $retencionesSugeridas + ($redondeo), $maxDecimales), $maxDecimales);

                    if($this->cdo_valor_a_pagar_moneda_extranjera < 0) {
                        $errores[] = "Valor a pagar en moneda extranjera negativo. Verifique valores de descuentos y/o retenciones sugeridas";
                    }

                    if($anticipos > $this->cdo_valor_a_pagar_moneda_extranjera) {
                        $errores[] = "El valor total del anticipo en moneda extranjera no puede ser mayor al valor total a pagar en moneda extranjera";
                    }
                }
            }
        }
        return $errores;
    }

    /**
     * Verifica que la sumatoria de los cargos, retenciones sugerdidas y descuentos se corresponda con los proporcionados en el total.
     *
     * @param mixed $itemsEvaluar Datos que deben ser procesados
     * @param boolean $tieneMonedaExtranejera Indicador para moneda extranjera
     * @param float $valor Valor en moneda nacional
     * @param float $valor_moneda_extranjera Valor en moneda extranjera
     * @param boolean $tieneMonedaExtranejera
     * @param string $tipos Tipo de los datos a analizar
     * @param mixed $itemsDocumento Items del documento, no obligatorio pero necesario cuando se requieren calculos sobre datos contenidos en ellos
     * @return array
     */
    private function validarCargosRetencionesSugeridasDescuentos($itemsEvaluar, $tieneMonedaExtranejera, $valor, $valor_moneda_extranjera, $tipos, $itemsDocumento = null) {
        $errores = [];
        $total   = 0.0;
        $totalMonedaExtranjera = 0.0;

        foreach ($itemsEvaluar as $itemEvaluar) {
            if (isset($itemEvaluar->valor_moneda_nacional) && isset($itemEvaluar->valor_moneda_nacional->valor) && is_numeric($itemEvaluar->valor_moneda_nacional->valor))
                $total += floatval($itemEvaluar->valor_moneda_nacional->valor);
                
            if ($tieneMonedaExtranejera && isset($itemEvaluar->valor_moneda_extranjera) && is_numeric($itemEvaluar->valor_moneda_extranjera->valor))
                $totalMonedaExtranjera += floatval($itemEvaluar->valor_moneda_extranjera->valor);
        }

        // Si la verificación se realiza sobre las retenciones sugeridas, se deven recorrer los items y verificar si tienen retenciones sugeridas para sumarlos
        if($tipos == KeyMap::$claveCdoRetencionesSugeridas && $itemsDocumento != null) {
            foreach($itemsDocumento as $itemDocumento) {
                if(isset($itemDocumento->ddo_detalle_retenciones_sugeridas) && !empty($itemDocumento->ddo_detalle_retenciones_sugeridas)) {
                    foreach($itemDocumento->ddo_detalle_retenciones_sugeridas as $retencionSugerida) {
                        if (isset($retencionSugerida->valor_moneda_nacional) && isset($retencionSugerida->valor_moneda_nacional->valor) && is_numeric($retencionSugerida->valor_moneda_nacional->valor))
                            $total += floatval($retencionSugerida->valor_moneda_nacional->valor);
                            
                        if ($tieneMonedaExtranejera && isset($retencionSugerida->valor_moneda_extranjera) && is_numeric($retencionSugerida->valor_moneda_extranjera->valor))
                            $totalMonedaExtranjera += floatval($retencionSugerida->valor_moneda_extranjera->valor);
                    }
                }
            }
        }

        $total = floatval(VP::redondeo($total));

        if (!$this->compararFlotantes($total, floatval($valor)))
            $errores[] = "La sumatoria del detalle de $tipos y el total de $tipos del documento no coinciden";

        if ($tieneMonedaExtranejera) {
            $totalMonedaExtranjera = floatval(VP::redondeo($totalMonedaExtranjera));
            if (!$this->compararFlotantes($totalMonedaExtranjera, floatval($valor_moneda_extranjera)))
                $errores[] = "La sumatoria del detalle de $tipos y el total de $tipos del documento, para moneda extranjera, no coinciden";
        }

        return $errores;
    }

    /**
     * Verifica que la sumatoria de los anticipos se corresponda con los proporcionados en el total.
     *
     * @param mixed $documento
     * @param mixed $anticipos
     * @param boolean $tieneMonedaExtranejera
     * @return array
     */
    private function validarAnticipos($documento, $anticipos, $tieneMonedaExtranejera) {
        $errores = [];
        $totalAnticipos = 0.00;
        $totalAnticiposMonedaExtranjera = 0.00;
        foreach ($anticipos as $anticipo) {
            if (isset($anticipo->ant_valor))
                $totalAnticipos += floatval($anticipo->ant_valor);

            if ($tieneMonedaExtranejera && isset($anticipo->ant_valor_moneda_extranjera))
                $totalAnticiposMonedaExtranjera += floatval($anticipo->ant_valor_moneda_extranjera);
        }

        $totalAnticipos = floatval(VP::redondeo($totalAnticipos));
        if (isset($documento->cdo_anticipo) && !$this->compararFlotantes($totalAnticipos, floatval($documento->cdo_anticipo)))
            $errores[] = 'La sumatoria del detalle de anticipos y el total de anticipos del documento no coinciden';

        if ($tieneMonedaExtranejera) {
            $totalAnticiposMonedaExtranjera = floatval(VP::redondeo($totalAnticiposMonedaExtranjera));
            if (isset($documento->cdo_anticipo_moneda_extranjera) && !$this->compararFlotantes($totalAnticiposMonedaExtranjera, floatval($documento->cdo_anticipo_moneda_extranjera)))
                $errores[] = 'La sumatoria del detalle de anticipos y el total de anticipos del documento, para moneda extranjera, no coinciden';
        }

        return $errores;
    }

    /**
     * Verifica que la sumatoria de los tributos o retenciones se corresponda con los proporcionados en el total.
     * 
     * @param array $items
     * @param boolean $tieneMonedaExtranejera
     * @param float $valor
     * @param float $valor_moneda_extranjera
     * @param string $field
     * @return array
     */
    private function validarTributos($items, $tieneMonedaExtranejera, $valor, $valor_moneda_extranjera, $field) {
        $errores = [];
        $total = 0.00;
        $totalMonedaExtranjera = 0.00;
        foreach ($items as $item) {
            if (isset($item->iid_valor))
                $total += floatval($item->iid_valor);

            if ($tieneMonedaExtranejera && isset($item->iid_valor_moneda_extranjera))
                $totalMonedaExtranjera += floatval($item->iid_valor_moneda_extranjera);
        }
        $total = floatval(VP::redondeo($total));
        if (!$this->compararFlotantes($total, floatval($valor)))
            $errores[] = "La sumatoria de $field y el total de $field del documento no coinciden";

        if ($tieneMonedaExtranejera) {
            $totalMonedaExtranjera = floatval(VP::redondeo($totalMonedaExtranjera));
            if (!$this->compararFlotantes($totalMonedaExtranjera, floatval($valor_moneda_extranjera)))
                $errores[] = "La sumatoria de $field y el total de $field del documento, para moneda extranjera, no coinciden";
        }

        return $errores;
    }

    /**
     * Compara si dos cantidades flotantes son iguales o no.
     * 
     * @param float $a Valor calculado
     * @param float $b Valor contra el comparar
     * @param $codigoTributo Codigo del tributo, aplica cuando se calculan tributos y permite que la diferencia para el IVA (01) sea +-5
     * @return bool
     */
    private function compararFlotantes($a, $b, $codigoTributo = null) {
        if (is_string($a))
            $a = (float)$a;
        if (is_string($b))
            $b = (float)$b;
        //dump("$a  -  $b  =  " . (abs(($a-$b)) < PHP_FLOAT_EPSILON) );
        // return abs(($a-$b)) < PHP_FLOAT_EPSILON;

        if($codigoTributo != '01')
            return abs(($a-$b)) < 2.000000000;
        else
            return abs(($a-$b)) < 5.000000000;
    }

    /**
     * Verifica que la totalización de los items y el total del documento corresponden
     *
     * @param mixed $document
     * @param boolean $tieneMonedaExtranejera
     * @return array
     */
    private function validarTotales($document, $tieneMonedaExtranejera) {
        // El documento no tiene items, no se puede determinar otro tipo de errores
        if (!isset($document->items) || !is_array($document->items))
            return [];

        $errores = [];
        $total   = 0.00;
        $totalMonedaExtranjera = 0.00;
        foreach ($document->items as $key => $item) {

            $total += floatval($item->ddo_total);
            $totalMonedaExtranjera += (isset($item->ddo_total_moneda_extranjera)) ? floatval($item->ddo_total_moneda_extranjera) : 0;

            if (isset($item->ddo_cargos)) {
                foreach ($item->ddo_cargos as $keyC => $cargos) {
                    $total += floatval($cargos->valor_moneda_nacional->valor);
                    $totalMonedaExtranjera += (isset($cargos->valor_moneda_extranjera->valor)) ? floatval($cargos->valor_moneda_extranjera->valor) : 0;
                }
            }

            if (isset($item->ddo_descuentos)) {
                foreach ($item->ddo_descuentos as $keyD => $descuentos) {
                    $total -= floatval($descuentos->valor_moneda_nacional->valor);
                    $totalMonedaExtranjera -= (isset($descuentos->valor_moneda_extranjera->valor)) ? floatval($descuentos->valor_moneda_extranjera->valor) : 0;
                }
            }
        }

        /*
        Se quita validación por la FNC el excel que se carga o la logica no esta teniendo en cuenta
        los valores de cargos y descuentos a nivel de item, se debe ajustar la logica de la federación para habilitar
        esta validacion 
        //Totales
        $valorSinImpuestos = (isset($document->cdo_valor_sin_impuestos)) ? $document->cdo_valor_sin_impuestos : 0;
        if ($total !== floatval($valorSinImpuestos))
            $errores[] = "El valor sin impuestos del documento no corresponde con la sumatoria del valor total del item, mas cargos y descuentos a nivel de item";

        if (isset($document->cdo_envio_dian_moneda_extranjera) && $document->cdo_envio_dian_moneda_extranjera == 'SI'){
            $valorSinImpuestosMonedaExtranjera = (isset($document->cdo_valor_sin_impuestos_moneda_extranjera)) ? $document->cdo_valor_sin_impuestos_moneda_extranjera : 0;
            if ($totalMonedaExtranjera !== floatval($valorSinImpuestosMonedaExtranjera))
                $errores[] = "El valor sin impuestos en moneda extranjera del documento no corresponde con la sumatoria del valor total del item, mas cargos y descuentos en moneda extrajera a nivel de item [$totalMonedaExtranjera !== ".floatval($valorSinImpuestosMonedaExtranjera)."]";
        }
        */        
        return $errores;
    }

    /**
     * Transforma la data para preparar la data de cabecera para el proceso de creación/actualización del documento
     *
     * @param array $cabecera Array de cabecera del documento
     * @param string $docType Tipo de documento electrónico
     * @param string $lote Lote de procesamiento para el documento electrónico
     * @param array $mediosPago Medios de pago asociados al documento electrónico
     * @return array
     */
    public function prepareCabecera(array $cabecera, $docType, $lote, $mediosPago) {
        if(!empty($mediosPago)) {
            $asignaFV = false;
            foreach($mediosPago as $medioPago) {
                if(
                    array_key_exists('men_fecha_vencimiento', $medioPago) && !empty($medioPago['men_fecha_vencimiento']) && !$asignaFV &&
                    (
                        (array_key_exists('cdo_vencimiento', $cabecera) && empty($cabecera['cdo_vencimiento'])) ||
                        (!array_key_exists('cdo_vencimiento', $cabecera))
                    )
                ) {
                    $cabecera['cdo_vencimiento'] = $medioPago['men_fecha_vencimiento'];
                    $asignaFV = true;
                }
            }
        }

        $cabecera['cdo_clasificacion'] = $docType;
        $cabecera['cdo_lote'] = $lote;
        $cabecera['mon_id'] = array_key_exists('mon_codigo', $cabecera) && !is_null($cabecera['mon_codigo']) ? $this->monedas[$cabecera['mon_codigo']]->mon_id : null;
        $cabecera['mon_id_extranjera'] = array_key_exists('mon_codigo_extranjera', $cabecera) && !empty($cabecera['mon_codigo_extranjera']) ? $this->monedas[$cabecera['mon_codigo_extranjera']]->mon_id : null;

        if (is_object($cabecera['cdo_informacion_adicional']) && ((isset($cabecera['cdo_informacion_adicional']->proceso_automatico) && $cabecera['cdo_informacion_adicional']->proceso_automatico === 'SI') ||
                (isset($cabecera['cdo_informacion_adicional']->cdo_procesar_documento) && $cabecera['cdo_informacion_adicional']->cdo_procesar_documento === 'SI'))) {
            $cabecera['cdo_procesar_documento'] = 'SI';
            $cabecera['cdo_fecha_procesar_documento'] = date('Y-m-d H:i:s');
        } else {
            $cabecera['cdo_procesar_documento'] = 'NO';
            $cabecera['cdo_fecha_procesar_documento'] = NULL;
        }
        $aplica_para = ($this->docType === ConstantsDataInput::DS_NC) ? ConstantsDataInput::DS : $this->docType;
        $cabecera['tde_id'] = array_key_exists($cabecera['tde_codigo'], $this->tipoDocumentosElectronico) ? $this->tipoDocumentosElectronico[$cabecera['tde_codigo']]->tde_id : null;
        $cabecera['top_id'] = array_key_exists($cabecera['top_codigo'] . '~' .  $aplica_para, $this->tipoOperaciones) ? $this->tipoOperaciones[$cabecera['top_codigo'] . '~' .  $aplica_para]->top_id : null;
        $cabecera['rfa_prefijo'] = $cabecera['rfa_prefijo'] ?? '';

        // Solo las facturas y los documentos soporte estan atadas a una resolución
        if ($this->docType === ConstantsDataInput::FC || $this->docType === ConstantsDataInput::DS) {
            $indiceResolucion = $cabecera['ofe_identificacion']."~".$cabecera['rfa_resolucion']."~".$cabecera['rfa_prefijo'];
            $cabecera['rfa_id']                      = array_key_exists($indiceResolucion, $this->resoluciones) ? $this->resoluciones[$indiceResolucion]['rfa_id'] : null;
            $cabecera['cdo_control_consecutivos']    = array_key_exists($indiceResolucion, $this->resoluciones) ? $this->resoluciones[$indiceResolucion]['cdo_control_consecutivos'] : null;
            $cabecera['cdo_consecutivo_provisional'] = array_key_exists($indiceResolucion, $this->resoluciones) ? $this->resoluciones[$indiceResolucion]['cdo_consecutivo_provisional'] : null;
        }

        $cabecera['ofe_id'] = array_key_exists($cabecera['ofe_identificacion'], $this->oferentes) ? $this->oferentes[$cabecera['ofe_identificacion']]->ofe_id : null;
        if(array_key_exists('ofe_identificacion_multiples', $cabecera) && !empty($cabecera['ofe_identificacion_multiples']))
            $cabecera['ofe_id_multiples'] = json_encode($cabecera['ofe_identificacion_multiples']);

        if(
            (array_key_exists('adq_identificacion', $cabecera) && empty($cabecera['adq_identificacion']) && array_key_exists('adq_identificacion_multiples', $cabecera) && !empty($cabecera['adq_identificacion_multiples'])) ||
            (!array_key_exists('adq_identificacion', $cabecera) && array_key_exists('adq_identificacion_multiples', $cabecera) && !empty($cabecera['adq_identificacion_multiples']))
        ) {
            $adqIdentificacion  = $cabecera['adq_identificacion_multiples'][0]->adq_identificacion;
            $adqIdPersonalizado = isset($cabecera['adq_identificacion_multiples'][0]->adq_id_personalizado) && !empty($cabecera['adq_identificacion_multiples'][0]->adq_id_personalizado) ? '~' . $cabecera['adq_identificacion_multiples'][0]->adq_id_personalizado : '';
            $cabecera['adq_id'] = array_key_exists($adqIdentificacion . $adqIdPersonalizado, $this->adquirentes) ? $this->adquirentes[$adqIdentificacion . $adqIdPersonalizado]['adq_id'] : null;
            $cabecera['adq_id_multiples'] = json_encode($cabecera['adq_identificacion_multiples']);
        } elseif(
            (array_key_exists('adq_identificacion', $cabecera) && !empty($cabecera['adq_identificacion']) && array_key_exists('adq_identificacion_multiples', $cabecera) && empty($cabecera['adq_identificacion_multiples'])) ||
            (array_key_exists('adq_identificacion', $cabecera) && !empty($cabecera['adq_identificacion']) && !array_key_exists('adq_identificacion_multiples', $cabecera))
        ) {
            $adqIdPersonalizado = array_key_exists('adq_id_personalizado', $cabecera) && !empty($cabecera['adq_id_personalizado']) ? '~' . $cabecera['adq_id_personalizado'] : '';
            $cabecera['adq_id'] = array_key_exists($cabecera['adq_identificacion'] . $adqIdPersonalizado, $this->adquirentes) ? $this->adquirentes[$cabecera['adq_identificacion'] . $adqIdPersonalizado]['adq_id'] : null;
        }
        
        $cabecera['adq_id_autorizado'] = array_key_exists($cabecera['adq_identificacion_autorizado'], $this->adquirentes) ? $this->adquirentes[$cabecera['adq_identificacion_autorizado']]['adq_id'] : null;

        $cabecera['cdo_observacion'] = isset($cabecera['cdo_observacion']) && !empty($cabecera['cdo_observacion']) ? json_encode($cabecera['cdo_observacion']) : null;
        // En notas credito-debito esto siempre vendra, aun asi se deja el caso optativo para las facturas
        if (!is_null($cabecera['cdo_conceptos_correccion']) && !empty($cabecera['cdo_conceptos_correccion']))
            $cabecera['cdo_conceptos_correccion'] = json_encode($cabecera['cdo_conceptos_correccion']);

        // En notas credito-debito esto siempre vendra, aun asi se deja el caso optativo para las facturas
        if (!is_null($cabecera['cdo_documento_referencia']) && !empty($cabecera['cdo_documento_referencia']))
            $cabecera['cdo_documento_referencia'] = json_encode($cabecera['cdo_documento_referencia']);

        $cabecera['cdo_valor_a_pagar'] = $this->cdo_valor_a_pagar;
        $cabecera['cdo_valor_a_pagar_moneda_extranjera'] = $this->cdo_valor_a_pagar_moneda_extranjera;
        $cabecera['cdo_atributos'] = array_key_exists('atributo_top_codigo_id', $cabecera) && !empty($cabecera['atributo_top_codigo_id']) ? json_encode(['atributo_top_codigo_id' => $cabecera['atributo_top_codigo_id']]) : null;

        unset($cabecera['tde_codigo']);
        unset($cabecera['top_codigo']);
        unset($cabecera['adq_identificacion']);
        unset($cabecera['adq_identificacion_autorizado']);
        unset($cabecera['atributo_top_codigo_id']);

        return $cabecera;
    }

    /**
     * Construye los datos para la tabla dad.
     *
     * @param array $dad
     * @param $cdo_informacion_adicional
     * @return array
     */
    public function prepareDad(array $dad, $cdo_informacion_adicional) {
        $datos = [];

        $datos['cdo_informacion_adicional']      = is_object($cdo_informacion_adicional) ? (array) $cdo_informacion_adicional : null;
        $datos['cdo_documento_adicional']        = array_key_exists('cdo_documento_adicional', $dad) ? $dad['cdo_documento_adicional'] : null;
        $datos['dad_orden_referencia']           = array_key_exists('dad_orden_referencia', $dad) ? $dad['dad_orden_referencia'] : null;
        $datos['dad_despacho_referencia']        = array_key_exists('dad_despacho_referencia', $dad) ? $dad['dad_despacho_referencia'] : null;
        $datos['dad_recepcion_referencia']       = array_key_exists('dad_recepcion_referencia', $dad) ? $dad['dad_recepcion_referencia'] : null;
        $datos['dad_interoperabilidad']          = array_key_exists('cdo_interoperabilidad', $dad) ? $dad['cdo_interoperabilidad'] : null;
        $datos['dad_documento_referencia_linea'] = array_key_exists('cdo_documento_referencia_linea', $dad) ? $dad['cdo_documento_referencia_linea'] : null;
        $datos['dad_referencia_adquirente']      = array_key_exists('cdo_referencia_adquirente', $dad) ? $dad['cdo_referencia_adquirente'] : null;

        // dad_condiciones_entrega
        if (!is_null($dad['dad_condiciones_entrega']) && !is_array($dad['dad_condiciones_entrega'])) {
            $dad_ce = $dad['dad_condiciones_entrega'];
            $datos['dad_entrega_bienes_fecha'] = isset($dad_ce->dad_entrega_bienes_fecha) ? $dad_ce->dad_entrega_bienes_fecha : null ;
            $datos['dad_entrega_bienes_hora'] = isset($dad_ce->dad_entrega_bienes_hora) ? $dad_ce->dad_entrega_bienes_hora : null ;
            if (isset($dad_ce->dad_entrega_bienes_codigo_postal))
                $datos['cpo_id_entrega_bienes'] = array_key_exists($dad_ce->dad_entrega_bienes_codigo_postal, $this->codigosPostales) ? $this->codigosPostales[$dad_ce->dad_entrega_bienes_codigo_postal]->cpo_id : null;
            $datos['dad_direccion_entrega_bienes'] = isset($dad_ce->dad_entrega_bienes_direccion) ? $dad_ce->dad_entrega_bienes_direccion : null;
            $this->resolvePaiDepMun(
                isset($dad_ce->pai_codigo_entrega_bienes) ? $dad_ce->pai_codigo_entrega_bienes : null,
                isset($dad_ce->dep_codigo_entrega_bienes) ? $dad_ce->dep_codigo_entrega_bienes : null,
                isset($dad_ce->mun_codigo_entrega_bienes) ? $dad_ce->mun_codigo_entrega_bienes : null,
                'pai_id_entrega_bienes', 'dep_id_entrega_bienes', 'mun_id_entrega_bienes', $datos);
        }

        // dad_entrega_bienes_despacho
        if (!is_null($dad['dad_entrega_bienes_despacho']) && !is_array($dad['dad_entrega_bienes_despacho'])) {
            $dad_bd = $dad['dad_entrega_bienes_despacho'];
            $datos['dad_entrega_bienes_despacho_identificacion_transportador'] = isset($dad_bd->dad_entrega_bienes_despacho_identificacion_transportador) ? $dad_bd->dad_entrega_bienes_despacho_identificacion_transportador : null;
            $datos['dad_entrega_bienes_despacho_identificacion_transporte'] = isset($dad_bd->dad_entrega_bienes_despacho_identificacion_transporte) ? $dad_bd->dad_entrega_bienes_despacho_identificacion_transporte : null;
            $datos['dad_entrega_bienes_despacho_tipo_transporte'] = isset($dad_bd->dad_entrega_bienes_despacho_tipo_transporte) ? $dad_bd->dad_entrega_bienes_despacho_tipo_transporte : null;
            $datos['dad_entrega_bienes_despacho_fecha_solicitada'] = isset($dad_bd->dad_entrega_bienes_despacho_fecha_solicitada) ? $dad_bd->dad_entrega_bienes_despacho_fecha_solicitada : null;
            $datos['dad_entrega_bienes_despacho_hora_solicitada'] = isset($dad_bd->dad_entrega_bienes_despacho_hora_solicitada) ? $dad_bd->dad_entrega_bienes_despacho_hora_solicitada : null;
            $datos['dad_entrega_bienes_despacho_fecha_estimada'] = isset($dad_bd->dad_entrega_bienes_despacho_fecha_estimada) ? $dad_bd->dad_entrega_bienes_despacho_fecha_estimada : null;
            $datos['dad_entrega_bienes_despacho_hora_estimada'] = isset($dad_bd->dad_entrega_bienes_despacho_hora_estimada) ? $dad_bd->dad_entrega_bienes_despacho_hora_estimada : null;
            $datos['dad_entrega_bienes_despacho_fecha_real'] = isset($dad_bd->dad_entrega_bienes_despacho_fecha_real) ? $dad_bd->dad_entrega_bienes_despacho_fecha_real : null;
            $datos['dad_entrega_bienes_despacho_hora_real'] = isset($dad_bd->dad_entrega_bienes_despacho_hora_real) ? $dad_bd->dad_entrega_bienes_despacho_hora_real : null;
            if (isset($dad_bd->dad_entrega_bienes_despacho_codigo_postal))
                $datos['cpo_id_entrega_bienes_despacho'] = array_key_exists($dad_bd->dad_entrega_bienes_despacho_codigo_postal, $this->codigosPostales) ? $this->codigosPostales[$dad_bd->dad_entrega_bienes_despacho_codigo_postal]->cpo_id : null;
            $datos['dad_direccion_entrega_bienes_despacho'] = isset($dad_bd->dad_entrega_bienes_despacho_direccion) ? $dad_bd->dad_entrega_bienes_despacho_direccion : null;
            $this->resolvePaiDepMun(
                isset($dad_bd->pai_codigo_entrega_bienes_despacho) ? $dad_bd->pai_codigo_entrega_bienes_despacho : null,
                isset( $dad_bd->dep_codigo_entrega_bienes_despacho) ? $dad_bd->dep_codigo_entrega_bienes_despacho : null,
                isset($dad_bd->mun_codigo_entrega_bienes_despacho) ? $dad_bd->mun_codigo_entrega_bienes_despacho : null,
                'pai_id_entrega_bienes_despacho', 'dep_id_entrega_bienes_despacho', 'mun_id_entrega_bienes_despacho', $datos);
        }

        // dad_terminos_entrega
        if (!is_null($dad['dad_terminos_entrega']) && is_array($dad['dad_terminos_entrega'])) {
            $datos['dad_terminos_entrega_condiciones_pago'] = null;
            $datos['cen_id']                                = null;
            $datos['cpo_id_terminos_entrega']               = null;
            $datos['pai_id_terminos_entrega']               = null;
            $datos['dep_id_terminos_entrega']               = null;
            $datos['mun_id_terminos_entrega']               = null;
            $datos['cpo_id_terminos_entrega']               = null;
            $datos['dad_direccion_terminos_entrega']        = null;
            $datos['dad_detalle_descuentos']                = [];
            $dad_te = $dad['dad_terminos_entrega'];
            foreach($dad_te as $index => $terminosEntrega) {
                if(isset($terminosEntrega->dad_detalle_descuentos)) {
                    $terminosEntrega->dad_detalle_descuentos->cdd_numero_linea = $index+1;
                    $datos['dad_detalle_descuentos'][] = $terminosEntrega->dad_detalle_descuentos;
                }
            }

            $datos['dad_terminos_entrega'] = $dad_te;
        }

        // dad_moneda_alternativa
        if (!is_null($dad['dad_moneda_alternativa']) && !is_array($dad['dad_moneda_alternativa'])) {
            $dad_ma = $dad['dad_moneda_alternativa'];
            $datos['dad_codigo_moneda_alternativa'] = isset($dad_ma->dad_codigo_moneda_alternativa) ? $dad_ma->dad_codigo_moneda_alternativa : null;
            $datos['dad_codigo_moneda_extranjera_alternativa'] = isset($dad_ma->dad_codigo_moneda_extranjera_alternativa) ? $dad_ma->dad_codigo_moneda_extranjera_alternativa : null;
            $datos['dad_trm_alternativa'] = isset($dad_ma->dad_trm_alternativa) ? $dad_ma->dad_trm_alternativa : null;
            $datos['dad_trm_fecha_alternativa'] = isset($dad_ma->dad_trm_fecha_alternativa) ? $dad_ma->dad_trm_fecha_alternativa : null;
        }

        // cdo_periodo_facturacion
        if (!is_null($dad['cdo_periodo_facturacion']) && !is_array($dad['cdo_periodo_facturacion'])) {
            $dad_pf = $dad['cdo_periodo_facturacion'];
            $datos['dad_periodo_fecha_inicio'] = isset($dad_pf->dad_periodo_fecha_inicio) ? $dad_pf->dad_periodo_fecha_inicio : null;
            $datos['dad_periodo_hora_inicio'] = isset($dad_pf->dad_periodo_hora_inicio) ? $dad_pf->dad_periodo_hora_inicio : null;
            $datos['dad_periodo_fecha_fin'] = isset($dad_pf->dad_periodo_fecha_fin) ? $dad_pf->dad_periodo_fecha_fin : null;
            $datos['dad_periodo_hora_fin'] = isset($dad_pf->dad_periodo_hora_fin) ? $dad_pf->dad_periodo_hora_fin : null;
        }

        return $datos;
    }

    /**
     * Construye los datos para la tabla de medios de pagos
     *
     * @param array $array
     * @return array
     */
    public function prepareMediosPagos(array $array) {
        $datos = [];
        foreach ($array as $item) {
            $__item = [];
            $__informacionAdicional = [];
            if (isset($item->fpa_codigo))
                $__item['fpa_id'] = array_key_exists($item->fpa_codigo, $this->formasPago) ? $this->formasPago[$item->fpa_codigo]->fpa_id : null;
            if (isset($item->mpa_codigo))
                $__item['mpa_id'] = array_key_exists($item->mpa_codigo, $this->mediosPago) ? $this->mediosPago[$item->mpa_codigo]->mpa_id : null;
            if (isset($item->men_fecha_vencimiento))
                $__item['men_fecha_vencimiento'] = $item->men_fecha_vencimiento;
            if (isset($item->men_identificador_pago))
                $__item['men_identificador_pago'] = !empty($item->men_identificador_pago) ? json_encode($item->men_identificador_pago) : null;
            if (isset($item->atributo_fpa_codigo_id) && !empty($item->atributo_fpa_codigo_id))
                $__informacionAdicional['atributo_fpa_codigo_id'] = $item->atributo_fpa_codigo_id;
            if (isset($item->atributo_fpa_codigo_name) && !empty($item->atributo_fpa_codigo_name))
                $__informacionAdicional['atributo_fpa_codigo_name'] = $item->atributo_fpa_codigo_name;

            if(!empty($__informacionAdicional))
                $__item['mpa_informacion_adicional'] = json_encode($__informacionAdicional);

            $datos[] = $__item;
        }
        return $datos;
    }

    /**
     * Construye los datos para la tabla de anticipos
     *
     * @param array $array
     * @return array
     */
    public function prepareAnticipos(array $array) {
        $datos = [];
        foreach ($array as $item) {
            $__item = [];
            $__item['ant_identificacion'] = isset($item->ant_identificacion) ? $item->ant_identificacion : '';
            $__item['ant_valor'] = isset($item->ant_valor) ? $item->ant_valor : 0.00;
            $__item['ant_valor_moneda_extranjera'] = isset($item->ant_valor_moneda_extranjera) ? $item->ant_valor_moneda_extranjera : 0.00;
            $__item['ant_fecha_recibido'] = isset($item->ant_fecha_recibido) ? $item->ant_fecha_recibido : null;
            $__item['ant_fecha_realizado'] = isset($item->ant_fecha_realizado) ? $item->ant_fecha_realizado : null;
            $__item['ant_hora_realizado'] = isset($item->ant_hora_realizado) ? $item->ant_hora_realizado : null;
            $__item['ant_instrucciones'] = isset($item->ant_instrucciones) ? $item->ant_instrucciones : null;
            $datos[] = $__item;
        }
        return $datos;
    }

    /**
     * Construye los datos para la tabla de cargos-descuentos
     *
     * @param array $array Datos
     * @param int $counter Contador
     * @param string $origen Origen de la data
     * @param string $ofe_identificacion Identificacion del OFE
     * @param string $cdo_sistema Permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     * @return array
     */
    public function prepareDetalleCargos(array $array, int &$counter, string $origen = '', string $ofe_identificacion = null, string $cdo_sistema = null) {
        $datos = [];
        foreach ($array as $item) {
            $__item = [];

            if($cdo_sistema == DataInputValidator::OPENETL_WEB)
                $__item['dmc_id'] = isset($item->dmc_codigo) && array_key_exists($item->dmc_codigo, $this->facturacionWebCargos[$ofe_identificacion]) ? $this->facturacionWebCargos[$ofe_identificacion][$item->dmc_codigo]->dmc_id : null;

            $__item['cdd_numero_linea'] = $counter;
            $__item['cdd_razon'] = isset($item->razon) ? $item->razon : null;
            $__item['cdd_nombre'] = isset($item->nombre) ? $item->nombre : null;
            $__item['cdd_porcentaje'] = isset($item->porcentaje) ? $item->porcentaje : 0.00;
            $__item['cdd_valor'] = isset($item->valor_moneda_nacional) && isset($item->valor_moneda_nacional->valor) ? $item->valor_moneda_nacional->valor : 0.00;
            $__item['cdd_base'] = isset($item->valor_moneda_nacional) && isset($item->valor_moneda_nacional->base) ? $item->valor_moneda_nacional->base : 0.00;
            $__item['cdd_valor_moneda_extranjera'] = isset($item->valor_moneda_extranjera) && isset($item->valor_moneda_extranjera->valor) ? $item->valor_moneda_extranjera->valor : 0.00;
            $__item['cdd_base_moneda_extranjera'] = isset($item->valor_moneda_extranjera) && isset($item->valor_moneda_extranjera->base) ? $item->valor_moneda_extranjera->base : 0.00;
            if ($origen == 'cabecera')
                $__item['cde_id'] = $this->codigosDescuentos['01']->cde_id;
            $datos[] = $__item;
            $counter++;
        }
        return $datos;
    }

    /**
     * Construye los datos para la tabla de cargos-descuentos
     *
     * @param array $array Datos
     * @param int $counter Contador
     * @param string $ofe_identificacion Identificacion del OFE
     * @param string $cdo_sistema Permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     * @return array
     */
    public function prepareDetalleDescuentos(array $array, int &$counter, string $ofe_identificacion = null, string $cdo_sistema = null) {
        $datos = [];
        foreach ($array as $item) {
            $__item = [];
            if (isset($item->cde_codigo))
                $__item['cde_id'] = array_key_exists($item->cde_codigo, $this->codigosDescuentos) ? $this->codigosDescuentos[$item->cde_codigo]->cde_id : null;

            if($cdo_sistema == DataInputValidator::OPENETL_WEB)
                $__item['dmd_id'] = isset($item->dmd_codigo) && array_key_exists($item->dmd_codigo, $this->facturacionWebDescuentos[$ofe_identificacion]) ? $this->facturacionWebDescuentos[$ofe_identificacion][$item->dmd_codigo]->dmd_id : null;

            $__item['cdd_numero_linea'] = $counter;
            $__item['cdd_razon'] = isset($item->razon) ? $item->razon : null;
            $__item['cdd_nombre'] = isset($item->nombre) ? $item->nombre : null;
            $__item['cdd_porcentaje'] = isset($item->porcentaje) ? $item->porcentaje : 0.00;
            $__item['cdd_valor'] = isset($item->valor_moneda_nacional) && isset($item->valor_moneda_nacional->valor) ? $item->valor_moneda_nacional->valor : 0.00;
            $__item['cdd_base'] = isset($item->valor_moneda_nacional) && isset($item->valor_moneda_nacional->base) ? $item->valor_moneda_nacional->base : 0.00;
            $__item['cdd_valor_moneda_extranjera'] = isset($item->valor_moneda_extranjera) && isset($item->valor_moneda_extranjera->valor) ? $item->valor_moneda_extranjera->valor : 0.00;
            $__item['cdd_base_moneda_extranjera'] = isset($item->valor_moneda_extranjera) && isset($item->valor_moneda_extranjera->base) ? $item->valor_moneda_extranjera->base : 0.00;
            $datos[] = $__item;
            $counter++;
        }
        return $datos;
    }

    /**
     * Construye los datos para la tabla de retenciones sugeridas
     *
     * @param array $array
     * @param int $counter
     * @param string $cdo_sistema Permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     * @return array
     */
    public function prepareRetencionesSugeridas(array $array, int &$counter, string $cdo_sistema = null) {
        $datos = [];
        foreach ($array as $item) {
            $__item = [];

            if($cdo_sistema == DataInputValidator::OPENETL_WEB)
                $__item['tri_id'] = $this->tributos[$item->codigo_tributo]->tri_id;

            $__item['cdd_numero_linea'] = $counter;
            $__item['cdd_tipo'] = isset($item->tipo) ? $item->tipo : null;
            $__item['cdd_razon'] = isset($item->razon) ? $item->razon : null;
            $__item['cdd_porcentaje'] = isset($item->porcentaje) ? $item->porcentaje : 0.00;
            $__item['cdd_valor'] = isset($item->valor_moneda_nacional) && isset($item->valor_moneda_nacional->valor) ? $item->valor_moneda_nacional->valor : 0.00;
            $__item['cdd_base'] = isset($item->valor_moneda_nacional) && isset($item->valor_moneda_nacional->base) ? $item->valor_moneda_nacional->base : 0.00;
            $__item['cdd_valor_moneda_extranjera'] = isset($item->valor_moneda_extranjera) && isset($item->valor_moneda_extranjera->valor) ? $item->valor_moneda_extranjera->valor : 0.00;
            $__item['cdd_base_moneda_extranjera'] =isset($item->valor_moneda_extranjera) && isset($item->valor_moneda_extranjera->base) ?  $item->valor_moneda_extranjera->base : 0.00;
            $__item['cde_id'] = $this->codigosDescuentos['01']->cde_id;
            $datos[] = $__item;
            $counter++;
        }
        return $datos;
    }

    /**
     * Construye los datos para la tabla de detalle de documento
     *
     * @param array $array Array de Items del documento
     * @param int $contadorRetencionesSugeridasCabecera Contador que se debe utilizar para las retenciones sugeridas a nivel de item, si existen
     * @param string $ofe_identificacion Identificacion del OFE
     * @param string $cdo_sistema Permite establecer si un documento fue enviado desde el Frontend de openETL para su procesamiento
     * @return array
     */
    public function prepareItems(array $array, int $contadorRetencionesSugeridasCabecera, string $ofe_identificacion = null, string $cdo_sistema = null) {
        $datos = [];
        foreach ($array as $item) {
            $__item = (array)$item;
            $__item['ddo_datos_tecnicos']          = isset($item->ddo_datos_tecnicos) && !empty($item->ddo_datos_tecnicos) ? json_encode($item->ddo_datos_tecnicos) : null;
            $__item['ddo_marca']                   = isset($item->ddo_marca) && !empty($item->ddo_marca) ? json_encode($item->ddo_marca) : null;
            $__item['ddo_modelo']                  = isset($item->ddo_modelo) && !empty($item->ddo_modelo) ? json_encode($item->ddo_modelo) : null;
            $__item['ddo_propiedades_adicionales'] = isset($item->ddo_propiedades_adicionales) && !empty($item->ddo_propiedades_adicionales) ? json_encode($item->ddo_propiedades_adicionales) : null;
            $__item['ddo_informacion_adicional']   = isset($item->ddo_informacion_adicional) && !empty($item->ddo_informacion_adicional) ? json_encode($item->ddo_informacion_adicional) : null;
            $__item['ddo_notas']                   = isset($item->ddo_notas) && !empty($item->ddo_notas) ? json_encode($item->ddo_notas) : [];

            $__item['ddo_valor_unitario']                   = isset($item->ddo_valor_unitario) && !empty($item->ddo_valor_unitario) ? $item->ddo_valor_unitario : 0.00;
            $__item['ddo_valor_unitario_moneda_extranjera'] = isset($item->ddo_valor_unitario_moneda_extranjera) && !empty($item->ddo_valor_unitario_moneda_extranjera) ? $item->ddo_valor_unitario_moneda_extranjera : 0.00;
            $__item['ddo_total']                            = isset($item->ddo_total) && !empty($item->ddo_total) ? $item->ddo_total : 0.00;
            $__item['ddo_total_moneda_extranjera']          = isset($item->ddo_total_moneda_extranjera) && !empty($item->ddo_total_moneda_extranjera) ? $item->ddo_total_moneda_extranjera : 0.00;

            $contador = 1;

            $__item['ddo_detalle_retenciones_sugeridas'] = isset($item->ddo_detalle_retenciones_sugeridas) ? $this->prepareRetencionesSugeridas((is_object($item->ddo_detalle_retenciones_sugeridas) ? (array)($item->ddo_detalle_retenciones_sugeridas) : $item->ddo_detalle_retenciones_sugeridas), $contadorRetencionesSugeridasCabecera, $cdo_sistema) : null;
            $__item['ddo_cargos']                        = isset($item->ddo_cargos) ? $this->prepareDetalleCargos((is_object($item->ddo_cargos) ? (array)($item->ddo_cargos) : $item->ddo_cargos), $contador, '', $ofe_identificacion, $cdo_sistema) : null;
            $__item['ddo_descuentos']                    = isset($item->ddo_descuentos) ? $this->prepareDetalleDescuentos((is_object($item->ddo_descuentos) ? (array)($item->ddo_descuentos) : $item->ddo_descuentos), $contador, $ofe_identificacion, $cdo_sistema) : null;

            $__item['cpr_id']            = isset($item->cpr_codigo) && array_key_exists($item->cpr_codigo, $this->clasificacionProductos) ? $this->clasificacionProductos[$item->cpr_codigo]->cpr_id : null;
            $__item['und_id']            = isset($item->und_codigo) && array_key_exists($item->und_codigo, $this->unidades) ? $this->unidades[$item->und_codigo]->und_id : null;
            $__item['tdo_id_mandatario'] = isset($item->ddo_tdo_codigo_mandatario) && array_key_exists($item->ddo_tdo_codigo_mandatario, $this->tipoDocumentos) ? $this->tipoDocumentos[$item->ddo_tdo_codigo_mandatario]->tdo_id : null;
            $__item['pai_id']            = isset($item->pai_codigo) && array_key_exists($item->pai_codigo, $this->paises) ? $this->paises[$item->pai_codigo] : null;

            $__item['ddo_indicador_muestra'] = isset($item->ddo_indicador_muestra) ? $item->ddo_indicador_muestra : null;
            
            if (isset($item->ddo_precio_referencia) && !is_null($item->ddo_precio_referencia)) {
                $__item['pre_id']                              = isset($item->ddo_precio_referencia->pre_codigo) && array_key_exists($item->ddo_precio_referencia->pre_codigo, $this->preciosReferencia) ?
                $this->preciosReferencia[$item->ddo_precio_referencia->pre_codigo]->pre_id: null;
                $__item['ddo_valor_muestra']                   = isset($item->ddo_precio_referencia->ddo_valor_muestra) ? $item->ddo_precio_referencia->ddo_valor_muestra : 0.00;
                $__item['ddo_valor_muestra_moneda_extranjera'] = isset($item->ddo_precio_referencia->ddo_valor_muestra_moneda_extranjera) ? $item->ddo_precio_referencia->ddo_valor_muestra_moneda_extranjera : 0.00;
            }

            $__item['ddo_identificacion_comprador'] = isset($item->ddo_identificacion_comprador) ? json_encode($item->ddo_identificacion_comprador) : null;
            $__item['ddo_fecha_compra'] = isset($item->ddo_fecha_compra) ? json_encode($item->ddo_fecha_compra) : null;

            unset($__item['cpr_codigo']);
            unset($__item['pre_codigo']);
            unset($__item['und_codigo']);
            unset($__item['pai_codigo']);

            $datos[] = $__item;
        }

        return $datos;
    }

    /**
     * Construye los datos para la tabla de impuestos
     *
     * @param array $array
     * @return array
     */
    public function prepareImpuestos(array $array) {
        $datos = [];
        foreach ($array as $item) {
            $__item = [];
            $__item['ddo_secuencia']                           = $item->ddo_secuencia;
            $__item['iid_valor']                               = $item->iid_valor;
            $__item['iid_valor_moneda_extranjera']             = isset($item->iid_valor_moneda_extranjera) ? $item->iid_valor_moneda_extranjera : 0.0;
            $__item['iid_motivo_exencion']                     = isset($item->iid_motivo_exencion) ? $item->iid_motivo_exencion : null;
            $__item['iid_nombre_figura_tributaria']            = isset($item->iid_nombre_figura_tributaria) && !empty($item->iid_nombre_figura_tributaria) ? $item->iid_nombre_figura_tributaria : null;

            $__item['tri_id']   = $this->tributos[$item->tri_codigo]->tri_id;

            if($item->tri_codigo != 'ZZ')
                $__item['iid_tipo'] = $this->tributos[$item->tri_codigo]->tri_tipo;
            else
                if($item->tri_codigo == 'ZZ' && property_exists($item, 'iid_porcentaje') && !empty($item->iid_porcentaje))
                    $__item['iid_tipo'] = 'TRIBUTO';
                elseif($item->tri_codigo == 'ZZ' && property_exists($item, 'iid_unidad') && !empty($item->iid_unidad))
                    $__item['iid_tipo'] = 'TRIBUTO-UNIDAD';
                else
                    $__item['iid_tipo'] = $this->tributos[$item->tri_codigo]->tri_tipo;

            if (isset($item->iid_porcentaje) && !is_null($item->iid_porcentaje)) {
                $__item['iid_base']                   = $item->iid_porcentaje->iid_base;
                $__item['iid_base_moneda_extranjera'] = isset($item->iid_porcentaje->iid_base_moneda_extranjera) ? $item->iid_porcentaje->iid_base_moneda_extranjera : 0.0;
                $__item['iid_porcentaje']             = $item->iid_porcentaje->iid_porcentaje;

                $__item['iid_redondeo_agregado']                   = $this->calcularRedondeoAgregado($__item['iid_valor'], $__item['iid_base'], $__item['iid_porcentaje']);
                $__item['iid_redondeo_agregado_moneda_extranjera'] = $this->calcularRedondeoAgregado($__item['iid_valor_moneda_extranjera'], $__item['iid_base_moneda_extranjera'], $__item['iid_porcentaje']);
            }

            if (isset($item->iid_unidad) && !is_null($item->iid_unidad)) {
                $__item['und_id']                                   = isset($item->iid_unidad->und_codigo) && array_key_exists($item->iid_unidad->und_codigo, $this->unidades) ? $this->unidades[$item->iid_unidad->und_codigo]->und_id : null;
                $__item['iid_cantidad']                             = isset($item->iid_unidad->iid_cantidad) ? $item->iid_unidad->iid_cantidad : 0.0;
                $__item['iid_base_unidad_medida']                   = $item->iid_unidad->iid_base_unidad_medida;
                $__item['iid_base_unidad_medida_moneda_extranjera'] = isset($item->iid_unidad->iid_base_unidad_medida_moneda_extranjera) ? $item->iid_unidad->iid_base_unidad_medida_moneda_extranjera : 0.0;
                $__item['iid_valor_unitario']                       = ($item->iid_unidad->iid_valor_unitario != null) ? $item->iid_unidad->iid_valor_unitario : 0.0;
                $__item['iid_valor_unitario_moneda_extranjera']     = isset($item->iid_unidad->iid_valor_unitario_moneda_extranjera) ? $item->iid_unidad->iid_valor_unitario_moneda_extranjera : 0.0;
            } else {
                $__item['und_id']                                   = null;
                $__item['iid_unidad']                               = 0.0;
                $__item['iid_base_unidad_medida']                   = 0.0;
                $__item['iid_base_unidad_medida_moneda_extranjera'] = 0.0;
                $__item['iid_valor_unitario']                       = 0.0;
                $__item['iid_valor_unitario_moneda_extranjera']     = 0.0;
            }
            $datos[] = $__item;
        }
        return $datos;
    }
}
