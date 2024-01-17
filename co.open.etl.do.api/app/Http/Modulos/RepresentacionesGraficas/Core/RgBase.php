<?php
namespace App\Http\Modulos\RepresentacionesGraficas\Core;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Support\Carbon;
use App\Http\Traits\DocumentosTrait;
use App\Http\Traits\NumToLetrasEngine;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use openEtl\Main\Traits\PackageMainTrait;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Parametros\Unidades\ParametrosUnidad;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Engines\Documentos\BuscadorDocumentosEngine;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Parametros\CondicionesEntrega\ParametrosCondicionEntrega;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop\EtlAnticiposDocumentoDaop;
use App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop\EtlMediosPagoDocumentoDaop;
use App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProducto;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop\EtlImpuestosItemsDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop\EtlCargosDescuentosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Clase base para la generación de representaciones gráficas en openETL
 *
 * Class RgBase
 * @package App\Http\Modulos\RepresentacionesGraficas\Core
 */
class RgBase {
    use PackageMainTrait, FechaVigenciaValidations;

    // Codigo de impuestos reconocidos
    public const IMPUESTO_IVA     = '01';
    public const IMPUESTO_ICA     = '03';
    public const IMPUESTO_CONSUMO = '04';

    public const MODO_CONSULTA_CABECERA = 'cabecera';
    public const MODO_CONSULTA_ITEM     = 'item';

    public const MODO_PORCENTAJE_IGNORAR  = 'ignore';
    public const MODO_PORCENTAJE_DETALLAR = 'detallar';

    // Retenciones sugeridas
    public const RETEIVA    = 'RETEIVA';
    public const RETEICA    = 'RETEICA';
    public const RETEFUENTE = 'RETEFUENTE';

    /**
     * Contiene los datos adicionales del documento.
     * @var EtlDatosAdicionalesDocumentoDaop
     */
    private $datosAdicionales;

    /**
     * Identificación del oferente
     *
     * @var
     */
    private $ofe_identificacion;

    /**
     * Data del documento
     *
     * @var
     */
    private $datos;

    /**
     * Directorio de assets
     *
     * @var
     */
    private $assets;

    /**
     * Base de datos actual
     *
     * @var
     */
    private $baseDeDatos;

    /**
     * Objeto gestor de para elaborar el pdf, de este modo evitamos multiples instancias del mismo
     *
     * @var
     */
    private $pdfManager;

    /**
     * Controlador de FPDF
     *
     * @var PDFBase
     */
    public $fpdf;

    /**
     * Almacena la clasificacion de los productos
     * @var array
     */
    public $clasificacionProductos = [];

    /**
     * Almacena las Unidades
     * @var array
     */
    public $unidades = [];

    /**
     * Condiciones de Entrega
     * @var array
     */
    public $condicionesEntrega = [];


    /**
     * Indice de representacion grafica
     * @var
     */
    private $idRepresentacionGrafica;

    /**
     * Contiene la parametrica de IVA
     *
     * @var ParametrosTributo
     */
    private $objetoImpuestoIva;

    /**
     * RgBase constructor.
     *
     * RgBase constructor.
     * @param string $ofe_identificacion
     * @param $baseDeDatos
     * @param $idRepresentacionGrafica
     * @param $datos
     * @param $assets
     */
    public function __construct(string $ofe_identificacion, $baseDeDatos, $idRepresentacionGrafica, $datos, $assets) {
        $this->ofe_identificacion = $ofe_identificacion;
        $this->baseDeDatos = $baseDeDatos;
        $this->idRepresentacionGrafica = $idRepresentacionGrafica;
        $this->datos = $datos;
        $this->assets = $assets;
    }

    /**
     * Permite obtner la representación grafica de un documento dado su cdo_id
     *
     * @param $cdo_id
     * @return EtlCabeceraDocumentoDaop|null
     */
    public static function obtenerDocumento($cdo_id) {
        $buscador   = new BuscadorDocumentosEngine();
        $relaciones = [
            'getDetalleDocumentosDaop',
            'getImpuestosItemsDocumentosDaop',
            'getAnticiposDocumentosDaop',
            'getCargosDescuentosDocumentosDaop',
            'getDadDocumentosDaop',
            'getMediosPagoDocumentosDaop',
            'getMediosPagoDocumentosDaop.getFormaPago',
            'getMediosPagoDocumentosDaop.getMedioPago',
            'getConfiguracionObligadoFacturarElectronicamente',
            'getConfiguracionAdquirente',
            'getConfiguracionAutorizado',
            'getConfiguracionResolucionesFacturacion',
            'getTipoDocumentoElectronico',
            'getTipoOperacion',
            'getParametrosMoneda',
            'getParametrosMonedaExtranjera',
            'getEstadosDocumento'
        ];
        return $buscador->getDocumento($cdo_id, $relaciones);
    }

    /**
     * Transforma un json codificado o un array a un objeto
     *
     * @param $in
     * @return mixed|object|null
     */
    public static function convertToObject($in) {
        if (is_string($in))
            return json_decode($in);
        elseif (is_array($in))
            return (object)$in;
        return null;
    }

    /**
     * Devuelve la representación gráfica de un documento
     *
     * @param EtlCabeceraDocumentoDaop $documento
     * @param array $parametricas Paramétricas del sistema
     * @return mixed
     */
    private static function init(EtlCabeceraDocumentoDaop $documento, array $parametricas = null) {
        $assets = '';
        $oferente = $documento->getConfiguracionObligadoFacturarElectronicamente;
        $adquirente = $documento->getConfiguracionAdquirente;
        $resolucion = $documento->getConfiguracionResolucionesFacturacion;

        $tipoDoc = DocumentosTrait::tipoDocumento($documento->cdo_clasificacion, trim($documento->rfa_prefijo));
        // DV Adquirente
        if(isset($adquirente->getParametroTipoDocumento) && $adquirente->getParametroTipoDocumento->tdo_codigo == '31')
            $adq_dv = TenantTrait::calcularDV($adquirente->adq_identificacion);
        else
            $adq_dv = '';

        // DV Oferente
        $ofe_dv = TenantTrait::calcularDV($oferente->ofe_identificacion);

        // Forma de pago - cálculo de días entre la fecha del documento y la fecha de vencimiento
        $fini = Carbon::parse($documento->cdo_fecha);
        $ffin = Carbon::parse($documento->cdo_vencimiento);
        $dias_pago = $ffin->diffInDays($fini);

        // Obtiene el ID para la representación gráfica e info adicional para la vista
        $cdoClasificacion = $documento->cdo_clasificacion;
        $idRepresentacionGrafica = $documento->cdo_representacion_grafica_documento;

        // Se obtiene la BD relacionada con el documento
        $user = User::find($documento->usuario_creacion);
        if(!empty($oferente->bdd_id_rg))
            $baseDatos = $oferente->getBaseDatosRg->bdd_nombre;
        else
            $baseDatos = $user->getBaseDatos->bdd_nombre;

        $baseDatos = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos);

        if (isset($oferente->ofe_representacion_grafica) && !empty($oferente->ofe_representacion_grafica)) {
            $ofeRepresentacionGrafica = json_decode($oferente->ofe_representacion_grafica);
            $camposRepresentacionGrafica = (isset($ofeRepresentacionGrafica->$cdoClasificacion->$idRepresentacionGrafica)) ? $ofeRepresentacionGrafica->$cdoClasificacion->$idRepresentacionGrafica : '';
        } else
            $camposRepresentacionGrafica = '';

        $docFat = EtlFatDocumentoDaop::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha_validacion_dian'])
            ->where('cdo_id', $documento->cdo_id)
            ->first();

        if($docFat)
            $particion = Carbon::parse($docFat->cdo_fecha_validacion_dian)->format('Ym');

        $datosAdicionales = $documento->getDadDocumentosDaop;
        if(empty($datosAdicionales) && isset($particion)) {
            // Campos de datos adicionales de cabecera
            $selectDatosAdicionales = [
                'dad_id',
                'cdo_id',
                'adq_id_entrega_bienes_responsable',
                'cdo_documento_adicional',
                'dad_periodo_fecha_inicio',
                'dad_periodo_hora_inicio',
                'dad_periodo_fecha_fin',
                'dad_periodo_hora_fin',
                'dad_orden_referencia',
                'dad_despacho_referencia',
                'dad_recepcion_referencia',
                'dad_entrega_bienes_fecha',
                'dad_entrega_bienes_hora',
                'pai_id_entrega_bienes',
                'dep_id_entrega_bienes',
                'mun_id_entrega_bienes',
                'cpo_id_entrega_bienes',
                'dad_direccion_entrega_bienes',
                'dad_entrega_bienes_despacho_identificacion_transporte',
                'dad_entrega_bienes_despacho_tipo_transporte',
                'dad_entrega_bienes_despacho_fecha_solicitada',
                'dad_entrega_bienes_despacho_hora_solicitada',
                'dad_entrega_bienes_despacho_fecha_estimada',
                'dad_entrega_bienes_despacho_hora_estimada',
                'dad_entrega_bienes_despacho_fecha_real',
                'dad_entrega_bienes_despacho_hora_real',
                'pai_id_entrega_bienes_despacho',
                'dep_id_entrega_bienes_despacho',
                'mun_id_entrega_bienes_despacho',
                'cpo_id_entrega_bienes_despacho',
                'dad_direccion_entrega_bienes_despacho',
                'dad_terminos_entrega',
                'dad_terminos_entrega_condiciones_pago',
                'cen_id',
                'pai_id_terminos_entrega',
                'dep_id_terminos_entrega',
                'mun_id_terminos_entrega',
                'cpo_id_terminos_entrega',
                'dad_direccion_terminos_entrega',
                'cdd_id_terminos_entrega',
                'dad_codigo_moneda_alternativa',
                'dad_codigo_moneda_extranjera_alternativa',
                'dad_trm_alternativa',
                'dad_trm_fecha_alternativa',
                'dad_interoperabilidad',
                'cdo_informacion_adicional'
            ];
            $tblDatosAdicionales = new EtlDatosAdicionalesDocumentoDaop();
            $tblDatosAdicionales->setTable('etl_datos_adicionales_documentos_' . $particion);
            $datosAdicionales = $tblDatosAdicionales->select($selectDatosAdicionales)
                ->where('cdo_id', $documento->cdo_id)
                ->first();
        }

        $dadTerminosEntrega        = [];
        $docInformacionAdicional   = '';
        $documentosAdicionales     = [];
        $documentosOrdenReferencia = [];
        $dadInteroperabilidad      = [];

        if (!is_null($datosAdicionales)) {
            if (isset($datosAdicionales->cdo_informacion_adicional) && !empty($datosAdicionales->cdo_informacion_adicional))
                $docInformacionAdicional = self::convertToObject($datosAdicionales->cdo_informacion_adicional);
            if (isset($datosAdicionales->cdo_documento_adicional) && !empty($datosAdicionales->cdo_documento_adicional))
                $documentosAdicionales = self::convertToObject($datosAdicionales->cdo_documento_adicional);
            if (isset($datosAdicionales->dad_orden_referencia) && !empty($datosAdicionales->dad_orden_referencia))
                $documentosOrdenReferencia = self::convertToObject($datosAdicionales->dad_orden_referencia);
            if (isset($datosAdicionales->dad_terminos_entrega) && !empty($datosAdicionales->dad_terminos_entrega))
                $dadTerminosEntrega = self::convertToObject($datosAdicionales->dad_terminos_entrega);
            if (isset($datosAdicionales->dad_interoperabilidad) && !empty($datosAdicionales->dad_interoperabilidad))
                $dadInteroperabilidad = self::convertToObject($datosAdicionales->dad_interoperabilidad);
        }

        $mediosPagosDocumento = [];
        if(!empty($documento->getMediosPagoDocumentosDaop)) {
            foreach ($documento->getMediosPagoDocumentosDaop as $registro) {
                $mediosPagosDocumento[] = [
                    'medio'         => !is_null($registro->getMedioPago) ? $registro->getMedioPago->toArray() : [],
                    'forma'         => !is_null($registro->getFormaPago) ? $registro->getFormaPago->toArray() : [],
                    'identificador' => json_decode($registro->men_identificador_pago, true)
                ];
            }
        }

        if(empty($mediosPagosDocumento) && isset($particion)) {
            $tblMediosPago = new EtlMediosPagoDocumentoDaop();
            $tblMediosPago->setTable('etl_medios_pago_documentos_' . $particion);

            $tblMediosPago->select(['fpa_id', 'mpa_id', 'men_identificador_pago'])
                ->where('cdo_id', $documento->cdo_id)
                ->with(['getMedioPago', 'getFormaPago'])
                ->get()
                ->map(function ($item) use (&$mediosPagosDocumento) {
                    $mediosPagosDocumento[] = [
                        'medio' => !is_null($item->getMedioPago) ? $item->getMedioPago->toArray() : [],
                        'forma' => !is_null($item->getFormaPago) ? $item->getFormaPago->toArray() : [],
                        'identificador' => json_decode($item->men_identificador_pago, true)
                    ];
                });
        }

        //Conceptos correcion
        $docConceptosCorrecion = [];
        if ($documento->cdo_conceptos_correccion !== null && $documento->cdo_conceptos_correccion !== '') {
            $docConceptosCorrecion = self::convertToObject($documento->cdo_conceptos_correccion );
        }

        // Items de la factura ordenados por valor absoluto en ddo_secuencia
        $items = $documento->getDetalleDocumentosDaop;
        $items = $items->sortBy(function ($item, $indice) {
            return abs($item->ddo_secuencia);
        });
        $items = $items->toArray();

        // Impuestos por Item de la factura
        $impuestosItems = $documento->getImpuestosItemsDocumentosDaop->toArray();
        $impuestoIva = ParametrosTributo::where('tri_codigo', self::IMPUESTO_IVA)->first();

        // Obtiene el porcentaje que corresponde al IVA en el documento
        $porcentajeIva = 0;
        foreach ($impuestosItems as $impuesto) {
            if ($impuesto['tri_id'] === $impuestoIva->tri_id && $impuesto['iid_porcentaje'] > 0) {
                $porcentajeIva = number_format($impuesto['iid_porcentaje'], 2, '.', '');
                break;
            }
        }

        $total = 0.0;
        $totalMonedaExtranjera = 0.0;
        $signo = 0.0;
        $total = $documento->cdo_total;
        $totalMonedaExtranjera = $documento->cdo_total_moneda_extranjera;

        $monedaLocal             = ParametrosMoneda::where('mon_id', $documento->mon_id)->first();
        $monedaExtranjera        = ParametrosMoneda::where('mon_id', $documento->mon_id_extranjera)->first();
        $valorLetras             = NumToLetrasEngine::num2letras(number_format($total, 2, '.', ''), false, true, $monedaLocal->mon_codigo);
        $objImpuestosRegistrados = [];
        ParametrosTributo::select(['tri_id', 'tri_codigo'])->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) use (&$objImpuestosRegistrados) {
                $objImpuestosRegistrados[$item->tri_id] = $item->tri_codigo;
            });

        // Verifica si el Adquirente es un consumidor final, porque en ese caso hay datos de paramétricos que no se pueden obtener
        $consumidorFinal = false;
        $adquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'));
        foreach($adquirenteConsumidorFinal as $consumidor) {
            $arrAdquirenteConsumidorFinal[$consumidor->adq_identificacion] = $consumidor;
        }

        if(array_key_exists($adquirente->adq_identificacion, $arrAdquirenteConsumidorFinal)) {
            $consumidorFinal = true;
        }

        $anticiposDocumento = $documento->getAnticiposDocumentosDaop;
        if(empty($anticiposDocumento) && isset($particion)) {
            $tblAnticipos = new EtlAnticiposDocumentoDaop();
            $tblAnticipos->setTable('etl_anticipos_documentos_' . $particion);

            $anticiposDocumento = $tblAnticipos->where('cdo_id', $documento->cdo_id)
                ->get();
        }

        $impuestosDocumento = [];
        if(!empty($documento->getImpuestosItemsDocumentosDaop)) {
            $documento->getImpuestosItemsDocumentosDaop->each(function ($impuesto) use (&$impuestosDocumento) {
                if($impuesto->iid_tipo == 'TRIBUTO') {
                    $arrImpuesto = [
                        "tri_id"                      => $impuesto->tri_id,
                        "iid_valor"                   => $impuesto->iid_valor,
                        "iid_valor_moneda_extranjera" => $impuesto->iid_valor_moneda_extranjera
                    ];
                    $impuestosDocumento[] = (object) $arrImpuesto;
                }
            });
        }
        if(empty($impuestosDocumento) && isset($particion)) {
            $tblImpuestos = new EtlImpuestosItemsDocumentoDaop();
            $tblImpuestos->setTable('etl_impuestos_items_documentos_' . $particion);

            $impuestosDocumento = $tblImpuestos->select(['tri_id', 'iid_valor', 'iid_valor_moneda_extranjera'])
                ->where('cdo_id', $documento->cdo_id)
                ->where('iid_tipo', 'TRIBUTO')
                ->get();
        }

        $esSectorSalud = false;
        if(!empty($parametricas) && is_array($parametricas) && array_key_exists('tiposOperacion', $parametricas)) {
            $metodosBase = new MetodosBase();
            $sectoresTipoOperacion = explode(',', $metodosBase->obtieneDatoParametrico($parametricas['tiposOperacion'], 'top_codigo', $documento->getTipoOperacion->top_codigo, 'top_sector'));
            if(in_array('SECTOR_SALUD', $sectoresTipoOperacion) && ($documento->getTipoOperacion->top_codigo == 'SS-CUFE' || $documento->getTipoOperacion->top_codigo == 'SS-CUDE' || $documento->getTipoOperacion->top_codigo == 'SS-POS' || $documento->getTipoOperacion->top_codigo == 'SS-SNum'))
                $esSectorSalud = true;
        }

        // Se obtienen las responsabilidades fiscales del Oferente
        $arrResponsabilidadesFiscales = [];
        $arrCodigos = explode(';', $oferente->ref_id);
        $responsabilidadesFiscales = ParametrosResponsabilidadFiscal::select([
                'ref_id',
                'ref_codigo',
                'ref_descripcion'
            ])
            ->whereIn('ref_codigo', $arrCodigos)
            ->get()
            ->groupBy('ref_codigo')
            ->map(function($item) use (&$arrResponsabilidadesFiscales) {
                $vigente = FechaVigenciaValidations::validarVigenciaRegistroParametrica($item, false);
                if ($vigente['vigente']) {
                    $arrResponsabilidadesFiscales[] = $vigente['registro']->ref_descripcion;
                }
            });

        // Crea la representación gráfica del documento en PDF
        $datos = [
            'cdo_id'                                  => $documento->cdo_id,
            'cdo_tipo'                                => $documento->cdo_clasificacion,
            'cdo_origen'                              => $documento->cdo_origen,
            'cdo_tipo_nombre'                         => $tipoDoc['clasificacionDoc'],
            'rfa_prefijo'                             => $tipoDoc['prefijo'],
            'cdo_consecutivo'                         => $documento->cdo_consecutivo,
            'cdo_moneda'                              => $monedaLocal->mon_codigo,
            'cdo_moneda_extranjera'                   => !is_null($monedaExtranjera) ? $monedaExtranjera->mon_codigo : null,
            'cdo_trm'                                 => $documento->cdo_trm,
            'cdo_trm_fecha'                           => $documento->cdo_trm_fecha,
            'cdo_documento_referencia'                => isset($documento->cdo_documento_referencia) ? json_decode($documento->cdo_documento_referencia) : [],
            'cdo_documento_adicional'                 => $documentosAdicionales,
            'cdo_informacion_adicional'               => $docInformacionAdicional,
            'dad_orden_referencia'                    => $documentosOrdenReferencia,
            'dad_terminos_entrega'                    => $dadTerminosEntrega,
            'dad_interoperabilidad'                   => $dadInteroperabilidad,
            'datos_adicionales'                       => $datosAdicionales,
            'cdo_anticipo'                            => !empty($documento->cdo_anticipo) ? number_format($documento->cdo_anticipo, 2, ',', '.') : '',
            'cdo_anticipo_moneda_extranjera'          => !empty($documento->cdo_anticipo_moneda_extranjera) ? number_format($documento->cdo_anticipo_moneda_extranjera, 2, ',', '.') : '',
            'cdo_descuentos'                          => $documento->cdo_descuentos,
            'cdo_descuentos_moneda_extranjera'        => !empty($documento->cdo_descuentos_moneda_extranjera) ? $documento->cdo_descuentos_moneda_extranjera : '',
            'ofe_representacion_grafica'              => $camposRepresentacionGrafica,
            'fecha_hora_documento'                    => $documento->cdo_fecha . ' ' . $documento->cdo_hora,
            'cdo_fecha'                               => $documento->cdo_fecha,
            'cdo_hora'                                => $documento->cdo_hora,
            'fecha_vencimiento'                       => $documento->cdo_vencimiento,
            'fecha_creacion'                          => $documento->fecha_creacion->format('Y-m-d H:i:s'),
            'dias_pago'                               => $dias_pago,
            'adquirente'                              => $adquirente->adq_razon_social !== null ? $adquirente->adq_razon_social : str_replace('  ', ' ', trim($adquirente->adq_primer_nombre . ' ' . $adquirente->adq_otros_nombres . ' ' . $adquirente->adq_primer_apellido . ' ' . $adquirente->adq_segundo_apellido)),
            'adq_nit'                                 => $adquirente->adq_identificacion . '-' . $adq_dv,
            'adq_nit_sin_digito'                      => $adquirente->adq_identificacion,
            'adq_tel'                                 => !$consumidorFinal ? $adquirente->adq_telefono : '',
            'adq_dir'                                 => !$consumidorFinal ? $adquirente->adq_direccion : '',
            'adq_dep'                                 => !$consumidorFinal ? (isset($adquirente->getParametroDepartamento) ? $adquirente->getParametroDepartamento->dep_descripcion : '') : '',
            'adq_mun'                                 => !$consumidorFinal ? (isset($adquirente->getParametroMunicipio) ? $adquirente->getParametroMunicipio->mun_descripcion : '') : '',
            'adq_pais'                                => !$consumidorFinal ? (isset($adquirente->getParametroPais) ? $adquirente->getParametroPais->pai_descripcion : '') : '',
            'adq_codigo_postal'                       => !$consumidorFinal ? (isset($adquirente->getCodigoPostal) ? $adquirente->getCodigoPostal->cpo_codigo : '') : '',
            'adq_correo'                              => !$consumidorFinal ? $adquirente->adq_correo : '',
            'adq_correos_notificacion'                => !$consumidorFinal ? $adquirente->adq_correos_notificacion : '',
            'adq_informacion_personalizada'           => !empty($adquirente->adq_informacion_personalizada) ? self::convertToObject($adquirente->adq_informacion_personalizada) : '',
            'adq_dir_domicilio_fiscal'                => !$consumidorFinal ? $adquirente->adq_direccion_domicilio_fiscal : '',            
            'adq_dep_domicio_fiscal'                  => !$consumidorFinal ? (isset($adquirente->getParametroDomicilioFiscalDepartamento) ? $adquirente->getParametroDomicilioFiscalDepartamento->dep_descripcion : '') : '',
            'adq_mun_domicio_fiscal'                  => !$consumidorFinal ? (isset($adquirente->getParametroDomicilioFiscalMunicipio) ? $adquirente->getParametroDomicilioFiscalMunicipio->mun_descripcion : '') : '',
            'adq_pais_domicio_fiscal'                 => !$consumidorFinal ? (isset($adquirente->getParametroDomicilioFiscalPais) ? $adquirente->getParametroDomicilioFiscalPais->pai_descripcion : '') : '',
            'adq_codigo_postal_domicio_fiscal'        => !$consumidorFinal ? (isset($adquirente->getCodigoPostalDomicilioFiscal) ? $adquirente->getCodigoPostalDomicilioFiscal->cpo_codigo : '') : '',
            'adq_matricula_mercantil'                 => $adquirente->adq_matricula_mercantil,
            'tdo_codigo'                              => isset($adquirente->getParametroTipoDocumento) ? $adquirente->getParametroTipoDocumento->tdo_codigo : '',
            'tdo_descripcion'                         => isset($adquirente->getParametroTipoDocumento->tdo_descripcion) ? $adquirente->getParametroTipoDocumento->tdo_descripcion : '',
            'numero_documento'                        => $tipoDoc['prefijo'] . $documento->cdo_consecutivo,
            'oferente'                                => $oferente->ofe_razon_social !== null ? $oferente->ofe_razon_social : str_replace('  ', ' ', trim($oferente->ofe_primer_nombre . ' ' . $oferente->ofe_otros_nombres . ' ' . $oferente->ofe_primer_apellido . ' ' . $oferente->ofe_segundo_apellido)),
            'ofe_identificacion'                      => $oferente->ofe_identificacion,
            'ofe_dv'                                  => $ofe_dv,
            'ofe_nit'                                 => $oferente->ofe_identificacion . '-' . $ofe_dv,
            'ofe_regimen'                             => isset($oferente->getParametrosRegimenFiscal) ? $oferente->getParametrosRegimenFiscal->rfi_descripcion : '',
            'ofe_dir'                                 => $oferente->ofe_direccion,
            'ofe_tel'                                 => $oferente->ofe_telefono,
            'ofe_mun'                                 => isset($oferente->getParametrosMunicipio) ? $oferente->getParametrosMunicipio->mun_descripcion : '',
            'ofe_pais'                                => isset($oferente->getParametrosPais) ? $oferente->getParametrosPais->pai_descripcion : '',
            'ofe_codigo_postal'                       => ($oferente->getCodigoPostal) ? $oferente->getCodigoPostal->cpo_codigo : '',
            'ofe_dep_domicio_fiscal'                  => isset($oferente->getParametroDomicilioFiscalDepartamento) ? $oferente->getParametroDomicilioFiscalDepartamento->dep_descripcion : '',
            'ofe_mun_domicio_fiscal'                  => isset($oferente->getParametroDomicilioFiscalMunicipio) ? $oferente->getParametroDomicilioFiscalMunicipio->mun_descripcion : '',
            'ofe_pais_domicio_fiscal'                 => isset($oferente->getParametroDomicilioFiscalPais) ? $oferente->getParametroDomicilioFiscalPais->pai_descripcion : '',
            'ofe_codigo_postal_domicio_fiscal'        => isset($oferente->getCodigoPostalDomicilioFiscal) ? $oferente->getCodigoPostalDomicilioFiscal->cpo_codigo : '',
            'ofe_matricula_mercantil'                 => $oferente->ofe_matricula_mercantil,
            'ofe_web'                                 => $oferente->ofe_web,
            'ofe_correo'                              => $oferente->ofe_correo,
            'ofe_twitter'                             => $oferente->ofe_twitter,
            'ofe_resolucion'                          => $resolucion ? $resolucion->rfa_resolucion : '',
            'ofe_resolucion_fecha'                    => $resolucion ? $resolucion->rfa_fecha_desde : '',
            'ofe_resolucion_fecha_hasta'              => $resolucion ? $resolucion->rfa_fecha_hasta : '',
            'ofe_resolucion_prefijo'                  => $resolucion ? $resolucion->rfa_prefijo : '',
            'ofe_resolucion_desde'                    => $resolucion ? $resolucion->rfa_consecutivo_inicial : '',
            'ofe_resolucion_hasta'                    => $resolucion ? $resolucion->rfa_consecutivo_final : '',
            'ofe_resolucion_vigencia'                 => $resolucion ? Carbon::parse($resolucion->rfa_fecha_hasta)->diffInMonths($resolucion->rfa_fecha_desde) : null,
            'ofe_resolucion_tipo'                     => $resolucion ? $resolucion->rfa_tipo : '',
            'ofe_campos_personalizados_factura'       => $oferente->ofe_campos_personalizados_factura_generica,
            'ofe_responsabilidad_fiscal'              => !empty($arrResponsabilidadesFiscales) ? implode(', ', $arrResponsabilidadesFiscales) : '',
            'ofe_tipo_organizacion'                   => isset($oferente->getTipoOrganizacionJuridica) ? $oferente->getTipoOrganizacionJuridica->toj_descripcion : '',
            'items'                                   => $items,
            'medios_pagos_documento'                  => $mediosPagosDocumento,
            'impuestos_items'                         => $impuestosItems,
            'porcentaje_iva'                          => $porcentajeIva,
            'subtotal'                                => number_format($documento->cdo_valor_sin_impuestos, 2, ',', '.'),
            'subtotal_moneda_extranjera'              => !empty($documento->cdo_valor_sin_impuestos_moneda_extranjera) ? number_format($documento->cdo_valor_sin_impuestos_moneda_extranjera, 2, ',', '.') : '',
            'iva'                                     => number_format($documento->cdo_impuestos, 2, ',', '.'),
            'iva_sin_formato'                         => $documento->cdo_impuestos,
            'iva_moneda_extranjera'                   => !empty($documento->cdo_impuestos_moneda_extranjera) ? number_format($documento->cdo_impuestos_moneda_extranjera, 2, ',', '.') : '',
            'total'                                   => number_format($documento->cdo_total, 2, ',', '.'),
            'total_moneda_extranjera'                 => number_format($documento->cdo_total_moneda_extranjera, 2, ',', '.'),
            'cargos'                                  => number_format($documento->cdo_cargos, 2, ',', '.'),
            'cargos_moneda_extranjera'                => number_format($documento->cdo_cargos_moneda_extranjera, 2, ',', '.'),
            'descuentos'                              => number_format($documento->cdo_descuentos, 2, ',', '.'),
            'descuentos_moneda_extranjera'            => number_format($documento->cdo_descuentos_moneda_extranjera, 2, ',', '.'),
            'detalle_cargos_descuentos'               => $documento->getCargosDescuentosDocumentosDaop,
            'retenciones_sugeridas'                   => number_format($documento->cdo_retenciones_sugeridas, 2, ',', '.'),
            'retenciones_sugeridas_moneda_extranjera' => number_format($documento->cdo_retenciones_sugeridas_moneda_extranjera, 2, ',', '.'),
            'cdo_retenciones'                         => number_format($documento->cdo_retenciones, 2, ',', '.'),
            'cdo_retenciones_moneda_extranjera'       => number_format($documento->cdo_retenciones_moneda_extranjera, 2, ',', '.'),
            'anticipo'                                => number_format($documento->cdo_anticipo, 2, ',', '.'),
            'anticipo_moneda_extranjera'              => number_format($documento->cdo_anticipo_moneda_extranjera, 2, ',', '.'),
            'signo'                                   => $signo,
            'total_con_descuentos'                    => number_format($total, 2, ',', '.'),
            'total_moneda_extranjera_con_descuentos'  => number_format($totalMonedaExtranjera, 2, ',', '.'),
            'valor_a_pagar'                           => number_format((!$esSectorSalud && $documento->cdo_anticipo > 0) ? ($documento->cdo_valor_a_pagar - $documento->cdo_anticipo) : $documento->cdo_valor_a_pagar, 2, ',', '.'),
            'valor_a_pagar_moneda_extranjera'         => number_format((!$esSectorSalud && $documento->cdo_anticipo_moneda_extranjera > 0) ? ($documento->cdo_valor_a_pagar_moneda_extranjera - $documento->cdo_anticipo_moneda_extranjera) : $documento->cdo_valor_a_pagar_moneda_extranjera, 2, ',', '.'),
            'cdo_fecha_validacion_dian'               => $documento->cdo_fecha_validacion_dian,
            'observacion'                             => $documento->cdo_observacion,
            'valor_letras'                            => $valorLetras,
            'signaturevalue'                          => $documento->cdo_signaturevalue,
            'cufe'                                    => $documento->cdo_cufe,
            'qr'                                      => $documento->cdo_qr,
            'impuestos_registrados'                   => $objImpuestosRegistrados,
            'usuario_creacion'                        => mb_strtoupper($user->usu_nombre),
            'cdo_conceptos_correccion'                => $docConceptosCorrecion,
            'nit_pt'                                  => config('variables_sistema.NIT_PT'),
            'razon_social_pt'                         => config('variables_sistema.RAZON_SOCIAL_PT'),
            'software_pt'                             => (isset($oferente->getConfiguracionSoftwareProveedorTecnologico) && !empty($oferente->getConfiguracionSoftwareProveedorTecnologico)) ? $oferente->getConfiguracionSoftwareProveedorTecnologico : '',
            'tipo_documento_electronico'              => $documento->getTipoDocumentoElectronico,
            'anticipos_documento'                     => $anticiposDocumento,
            'impuestos_documento'                     => $impuestosDocumento
        ];

        if ($documento->cdo_clasificacion == 'DS' || $documento->cdo_clasificacion == 'DS_NC') {
            if ($oferente->ofe_tiene_representacion_grafica_personalizada_ds === 'SI') {
                $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\' . $baseDatos . '\\rgDs' . $oferente->ofe_identificacion . '\\Rg' . $oferente->ofe_identificacion . '_' . $idRepresentacionGrafica;
                if(!class_exists($clase)) {
                    $clase = false;
                }

                DoTrait::setFilesystemsInfo();
                $assets = Storage::disk(config('variables_sistema.ETL_PUBLIC_STORAGE'))->getDriver()->getAdapter()->getPathPrefix() . 'ecm/assets-ofes/' . $baseDatos . '/' . $oferente->ofe_identificacion . '_ds/';
            } else {
                $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\etl_generica\\rgDsGenerica\\RgGENERICA' . '_' . $idRepresentacionGrafica;
            }
        } else {
            if ($oferente->ofe_cadisoft_activo === 'SI' && !isset($docInformacionAdicional->cdo_integracion)) {
                $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\etl_cadisoft\\rgCadisoft\\RgCadisoftBase_1';
            } else {
                if ($oferente->ofe_tiene_representacion_grafica_personalizada === 'SI') {
                    $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\' . $baseDatos . '\\rg' . $oferente->ofe_identificacion . '\\Rg' . $oferente->ofe_identificacion . '_' . $idRepresentacionGrafica;
                    if(!class_exists($clase)) {
                        $clase = false;
                    }
    
                    DoTrait::setFilesystemsInfo();
                    $assets = Storage::disk(config('variables_sistema.ETL_PUBLIC_STORAGE'))->getDriver()->getAdapter()->getPathPrefix() . 'ecm/assets-ofes/' . $baseDatos . '/' . $oferente->ofe_identificacion . '/';
                } else {
                    $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\etl_generica\\rgGenerica\\RgGENERICA' . '_' . $idRepresentacionGrafica;
                }
            }
        }
        self::setearLogo($datos, $oferente);

        return [
            'assets'                  => $assets, 
            'datos'                   => $datos, 
            'clase'                   => $clase, 
            'idRepresentacionGrafica' => $idRepresentacionGrafica, 
            'ofe_identificacion'      => $oferente->ofe_identificacion
        ];
    }

    /**
     * Determina el logo que sera incluido en la RG.
     *
     * @param array $datos Información para crear la RG
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe
     */
    public static function setearLogo(array &$datos, ConfiguracionObligadoFacturarElectronicamente $ofe) {
        if ($datos['cdo_tipo'] == 'DS' || $datos['cdo_tipo'] == 'DS_NC') {
            if ($ofe->ofe_tiene_representacion_grafica_personalizada_ds === 'SI') {
                // Gracias a esto podremos saber se trata de una RG Personalizada
                $datos['logo'] = '';
            } else {
                DoTrait::setFilesystemsInfo();
                $directorio = Storage::disk(config('variables_sistema.ETL_LOGOS_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
    
                $user = auth()->user();
                if(!empty($ofe->bdd_id_rg))
                    $bdd = $ofe->getBaseDatosRg->bdd_nombre;
                else
                    $bdd = $user->getBaseDatos->bdd_nombre;
    
                $bdd = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdd);
                $datos['logo'] = $directorio . $bdd . '/' . $ofe->ofe_identificacion . '/assets/' . 'logo' . $ofe->ofe_identificacion . '_ds.png';
            }
        } else {
            if ($ofe->ofe_tiene_representacion_grafica_personalizada === 'SI') {
                // Gracias a esto podremos saber se trata de una RG Personalizada
                $datos['logo'] = '';
            } else {
                DoTrait::setFilesystemsInfo();
                $directorio = Storage::disk(config('variables_sistema.ETL_LOGOS_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
    
                $user = auth()->user();
                if(!empty($ofe->bdd_id_rg))
                    $bdd = $ofe->getBaseDatosRg->bdd_nombre;
                else
                    $bdd = $user->getBaseDatos->bdd_nombre;
    
                $bdd = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdd);
                $datos['logo'] = $directorio . $bdd . '/' . $ofe->ofe_identificacion . '/assets/' . 'logo' . $ofe->ofe_identificacion . '.png';
            }
        }
    }

    /**
     * Retorna las URL's fullimages
     *
     * @param string $img Nombre del assets que se ubica en la carpeta de "assets" del oferente
     * @return string
     */
    public function getFullImage(string $img) {
        return $this->assets . $img;
    }

    /**
     * Determina la representacion gráfica que debe generarse
     *
     * @param $cdo_id ID del documento electrónico
     * @param $user Usuario autenticado
     * @param array $parametricas Paramétricas del sistema
     * @return null|array|ArrayObject
     */
    public static function resolve($cdo_id, $user, array $parametricas = null) {
        $documento = self::obtenerDocumento($cdo_id);
        if (!is_null($documento)) {
            $datos = self::init($documento, $parametricas);
            if ($datos != null) {
                if($datos['clase'] === false)
                    return [
                        'clase' => false,
                        'error' => 'No Existe la Representaci&oacute;n Gr&aacute;fica ' . $documento->cdo_representacion_grafica_documento . ' para el NIT ' . $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion
                    ];

                if(!empty($documento->getConfiguracionObligadoFacturarElectronicamente->bdd_id_rg))
                    $baseDatos = $documento->getConfiguracionObligadoFacturarElectronicamente->getBaseDatosRg->bdd_nombre;
                else
                    $baseDatos = $user->getBaseDatos->bdd_nombre;

                $baseDatos = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos);
                $clase = $datos['clase'];
                return new $clase($datos['ofe_identificacion'], $baseDatos, $datos['idRepresentacionGrafica'], $datos['datos'], $datos['assets']);
            }
        }
        return [
            'clase' => null
        ];
    }

    /**
     * @return mixed
     */
    public function getDatos() {
        return $this->datos;
    }

    /**
     * Construye el pdf - este se sobreescribe por las clases particulares.
     * 
     * @return mixed
     */
    public function getPdf() {}

    /**
     * Obtiene un objeto gestor para poder elaborar un pdf.
     *
     * @return mixed
     */
    public function pdfManager() {
        if ($this->pdfManager === null) {
            //Trayendo los datos del OFE
            $oferente = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_identificacion', 'ofe_tiene_representacion_grafica_personalizada', 'ofe_tiene_representacion_grafica_personalizada_ds', 'ofe_cadisoft_activo'])
                ->where('ofe_identificacion', $this->ofe_identificacion)
                ->first();
            
            // Proyecto especial CADISOFT, si el campo cdo_integracion es enviado en informacion adicional de cabecera
            // el cliente esta usando su propia RG, 
            // si no es enviado usa el estandar de CADISOFT
            $aplicaCadisoft = ($oferente->ofe_cadisoft_activo === 'SI' && !isset($this->datos['cdo_informacion_adicional']->cdo_integracion)) ? 'SI' : 'NO';

            $this->pdfManager = PDFBase::buildPdfManager($oferente, $this->baseDeDatos, $this->idRepresentacionGrafica, $this->datos['cdo_tipo'], $aplicaCadisoft);
        }
        return $this->pdfManager;
    }

    /**
     * Ajusta un string númerico cuyo formato es ###.###,## al formato #####.##
     *
     * @param $str
     * @return float
     */
    function parserNumberController($str) {
        if (is_float($str))
            return $str;

        $temp = str_replace('.', '', $str);
        $temp = str_replace(',', '.', $temp);
        return floatval($temp);
    }

    /**
     * Formatea un número con decimalas
     *
     * @param $in
     * @return string
     */
    function formatWithDecimals($in) {
        return number_format($this->parserNumberController($in), 2, ',', '.');
    }

    /**
     * Imprime una valor dado una X,Y en la página actual del PDF.
     * El Array de Opciones Puede tener las siguientes definiciones
     * $opciones => [
     *      'font' => [
     *          'family' => 'Familia de la fuente',
     *          'style' => 'Estilo de la fuente',
     *          'size' => 'Tamaño de la fuente'
     *      ],
     *      'format_number' => 'true|false',
     *      'border' => 0,
     *      'ln' => 0
     * ]
     *
     * @param $posx
     * @param $posy
     * @param $value
     * @param int $width
     * @param int $heigth
     * @param string $align
     * @param array $opciones
     */
    public function imprimirValor($posx, $posy, $value, $width = 60, $heigth = 3, $align = 'L', $opciones = []) {
        $this->fpdf->setXY($posx, $posy);
        /*
         * La fuente se fija, siempre y cuando se propocione la opcion font, si esta se envia, pero no contiene las claves validas
         * se asignan valores por defecto
         */
        if (array_key_exists('font', $opciones))
            $this->fpdf->SetFont($this->getValueInArray('family', $opciones['font']) ?? 'Arial',
                $this->getValueInArray('style', $opciones['font']) ?? '',
                $this->getValueInArray('size', $opciones['font']) ?? 7);

        if (array_key_exists('format_number', $opciones) && $opciones['format_number'])
            $this->fpdf->Cell($width, $heigth, number_format($value, 2, '.', ','), $this->getValueInArray('border', $opciones) ?? 0,
                $this->getValueInArray('ln', $opciones) ?? 0, $align);
        else
            $this->fpdf->Cell($width, $heigth, $value, $this->getValueInArray('border', $opciones) ?? 0,
                $this->getValueInArray('ln', $opciones) ?? 0, $align);
    }

    /**
     * Retorna el valor contenido en la posición de un array, si esta existe, de lo contrario retorna null
     * @param string $key
     * @param array $array
     * @return mixed|null
     */
    public function getValueInArray(string $key, array $array) {
        return array_key_exists($key, $array) ? $array[$key] : null;
    }


    /**
     * Obtiene el código de clasificacion de un producto dado su identificador en la tabla de clafisificacion de productos
     *
     * @param $cpr_id
     * @return string
     */
    public function getCodigoClasificacionProducto($cpr_id) {
        $clasificacion = null;
        if (!array_key_exists($cpr_id, $this->clasificacionProductos)) {
            $clasificacion = ParametrosClasificacionProducto::select(['cpr_id', 'cpr_codigo', 'cpr_nombre', 'cpr_identificador', 'cpr_descripcion'])
                ->where('cpr_id', $cpr_id)
                ->first();
            if ($clasificacion != null)
                $this->clasificacionProductos[$clasificacion->cpr_id] = $clasificacion;
        } else
            $clasificacion = $this->clasificacionProductos[$cpr_id];
        if (!is_null($clasificacion))
            return $clasificacion->cpr_codigo;
        return '';
    }

    /**
     * Totaliza los impuestos por tipo, este metodo puede tomar o no en cuenta el porcentaje aplicado si este se indica
     *
     * @param int $cdo_id Id de cabecera
     * @param string $tipo Código del impuesto
     * @param bool $porcentaje Valor del porcentaje
     * @param int|null $ddo_id Id de detalle
     * @return array
     */
    public function getTotalImpuestoPorTipo(int $cdo_id, string $tipo, bool $porcentaje = false, $ddo_id = null) {
        $columnas = [
            'iid_id',
            'cdo_id',
            'ddo_id',
            'iid_base',
            'iid_base_moneda_extranjera',
            'iid_porcentaje',
            'iid_valor',
            'iid_valor_moneda_extranjera'
        ];

        $objTributo = null;
        ParametrosTributo::select(['tri_id', 'tri_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('tri_codigo', $tipo)
            ->get()
            ->groupBy('tri_codigo')
            ->map(function($item) use (&$objTributo) {
                $vigente = FechaVigenciaValidations::validarVigenciaRegistroParametrica($item, false);
                if ($vigente['vigente']) {
                    $objTributo = $vigente['registro'];
                }
            });

        $basevalorLocal      = 0.0;
        $valorLocal          = 0.0;
        $basevalorExtranjera = 0.0;
        $valorExtranjera     = 0.0;
        $iidPorcentaje       = 0.0;
        if (!empty($objTributo)) {
            $impuestos = EtlImpuestosItemsDocumentoDaop::select($columnas)
                ->where('tri_id', $objTributo->tri_id)
                ->where('cdo_id', $cdo_id)
                ->when($ddo_id == null, function ($query) {
                    return $query->whereNull('ddo_id');
                }, function ($query) use ($ddo_id) {
                    return $query->where('ddo_id', $ddo_id);
                });

            if ($porcentaje !== false && $porcentaje !== '')
                $impuestos->where('iid_porcentaje', $porcentaje);

            $impuestos = $impuestos->get();

            foreach ($impuestos as $impuesto) {
                $basevalorLocal      += $impuesto->iid_base;
                $valorLocal          += $impuesto->iid_valor;
                $basevalorExtranjera += $impuesto->iid_base_moneda_extranjera;
                $valorExtranjera     += $impuesto->iid_valor_moneda_extranjera;

                if (floatval($impuesto->iid_porcentaje) > $iidPorcentaje)
                    $iidPorcentaje = floatval($impuesto->iid_porcentaje);
            }
        }

        return [
            'baseLocal'      => $basevalorLocal,
            'local'          => $valorLocal,
            'baseExtranjera' => $basevalorExtranjera,
            'extranjera'     => $valorExtranjera,
            'porcentaje'     => $iidPorcentaje
        ];
    }

    /**
     * Totaliza los impuestos por tipo, este metodo puede tomar o no en cuenta el porcentaje aplicado si este se indica
     *
     * @param $cdo_id
     * @param string $cdd_tipo
     * @param string $nombre
     * @param bool $porcentaje
     * @return array
     */
    public function getTotalCargoDescuentoRetenciones($cdo_id, $ddo_id, string $cdd_tipo, $porcentaje = false) {

        $columnas = [
            'cdd_id',
            'cdo_id',
            'ddo_id',
            'cdd_aplica',
            'cdd_tipo',
            'cdd_indicador',
            'cdd_razon',
            'cdd_porcentaje',
            'cdd_valor',
            'cdd_valor_moneda_extranjera',
            'cdd_base',
            'cdd_base_moneda_extranjera',
            'cde_id',
        ];

        $cargoDescuentoRetenciones = EtlCargosDescuentosDocumentoDaop::select($columnas)
            ->where('cdd_tipo', $cdd_tipo)
            ->where('cdo_id', $cdo_id);
        
        if ($porcentaje !== false && $ddo_id !== '' && $ddo_id !== null)
            $cargoDescuentoRetenciones->where('ddo_id', $ddo_id);
        else
            $cargoDescuentoRetenciones->whereNull('ddo_id');

        if ($porcentaje !== false && $porcentaje !== '' && $porcentaje !== null)
            $cargoDescuentoRetenciones->where('cdd_porcentaje', $porcentaje);

        $cargoDescuentoRetenciones = $cargoDescuentoRetenciones->get();

        $baseValorLocal      = 0.0;
        $valorLocal          = 0.0;
        $baseValorExtranjera = 0.0;
        $valorExtranjera     = 0.0;
        foreach ($cargoDescuentoRetenciones as $cargoDescuento) {
            $baseValorLocal      += $cargoDescuento->cdd_base;
            $valorLocal          += $cargoDescuento->cdd_valor;
            $baseValorExtranjera += $cargoDescuento->cdd_base_moneda_extranjera;
            $valorExtranjera     += $cargoDescuento->cdd_valor_moneda_extranjera;
        }

        return ['baseLocal'      => $valorLocal,
                'local'          => $valorLocal,
                'BaseExtranjera' => $valorLocal,
                'extranjera'     => $valorExtranjera];
    }

    /**
     * Retorna la informción del documento de refernecia, por lo general una fatura para una nota
     *
     * @param $cdo_documento_referencia
     * @param string $clasificacion
     * @return array
     */
    public function getDocumentoReferencia($cdo_documento_referencia, $clasificacion = 'FC') {

        $factura = "";
        $fecha = "";
        $cufe = "";
        if (is_array($cdo_documento_referencia)) {
            $N = count($cdo_documento_referencia);
            $i = 0;
            $sw = false;
            $pvt = null;
            while ($i < $N && !$sw) {
                $pvt = (array)$cdo_documento_referencia[$i];
                if (is_array($pvt) && array_key_exists('clasificacion', $pvt) && $pvt['clasificacion'] === $clasificacion) {
                    $sw = true;
                }
                if (!$sw)
                    $i++;
            }
            if ($sw) {
                $prefijo = isset($pvt['prefijo']) ? $pvt['prefijo'] : '';
                $factura = $prefijo . (isset($pvt['consecutivo']) ? $pvt['consecutivo'] : '');
                $fecha = isset($pvt['fecha_emision']) ? $pvt['fecha_emision'] : '';
                $cufe = isset($pvt['cufe']) ? $pvt['cufe'] : '';
            }
        }
        
        return [$factura, $fecha, $cufe];
    }

    /**
     * Retorna el tipo de unidad "Codigo o descripcion"
     *
     * @param $und_id
     * @param string $field
     * @return string
     */
    public function getUnidad($und_id, $field = 'und_codigo') {
      if (!is_null($und_id)) {
        $field = strtolower($field);
        $allowed = ['und_codigo', 'und_descripcion'];
        if (!in_array($field, $allowed))
            $field = 'und_codigo';
        $unidad = null;
        if (!array_key_exists($und_id, $this->unidades)) {
            $unidad = ParametrosUnidad::select(['und_id', 'und_codigo', 'und_descripcion'])
                ->where('und_id', $und_id)
                ->first()
                ->toArray();
            $this->unidades[$und_id] = $unidad;
        } else
            $unidad = $this->unidades[$und_id];
        if (!is_null($unidad))
            return $unidad[$field];
        return '';
      } else {
        return '';
      }        
    }

    /**
     * Retorna las condiciones de entrega (Terminos de negociacion)
     *
     * @param $datosAdicionales
     * @param string $field
     * @return string
     */
    public function getCondicionesEntrega($datosAdicionales, $field = 'cen_codigo') {
        $field = strtolower($field);
        $allowed = ['cen_codigo', 'cen_descripcion'];
        if (!in_array($field, $allowed))
            $field = 'cen_codigo';
        $condicion = "";
        if (
            !is_null($datosAdicionales) && 
            isset($datosAdicionales->dad_terminos_entrega) &&
            !empty($datosAdicionales->dad_terminos_entrega) &&
            array_key_exists('cen_codigo', $datosAdicionales->dad_terminos_entrega[0]) &&
            $datosAdicionales->dad_terminos_entrega[0]['cen_codigo'] != ''
        ) {
            $con = null;
            if (!array_key_exists($datosAdicionales->dad_terminos_entrega[0]['cen_codigo'], $this->condicionesEntrega)) {
                $con = ParametrosCondicionEntrega::select(['cen_id', 'cen_codigo', 'cen_descripcion'])
                    ->where('cen_codigo', $datosAdicionales->dad_terminos_entrega[0]['cen_codigo'])
                    ->first()
                    ->toArray();
                $this->condicionesEntrega[$datosAdicionales->dad_terminos_entrega[0]['cen_codigo']] = $con;
            } else
                $con = $this->condicionesEntrega[$datosAdicionales->dad_terminos_entrega[0]['cen_codigo']];
            if (!is_null($con))
                $condicion = $con[$field];
        }
        return $condicion;
    }

    /**
     * @param $mediosPago
     * @param int $pos
     * @return array
     */
    public function getDataMetodosPago($mediosPago, $pos = 0) {
        if (is_array($mediosPago) && isset($mediosPago[$pos])) {
            $data = [
                'medio' => isset($mediosPago[$pos]['medio']['mpa_descripcion']) ? $mediosPago[$pos]['medio']['mpa_descripcion'] : '' ,
                'forma' => isset($mediosPago[$pos]['forma']['fpa_descripcion']) ? $mediosPago[$pos]['forma']['fpa_descripcion'] : '',
                'identificador_pago' => isset($mediosPago[$pos]['identificador']) ? $mediosPago[$pos]['identificador'] :  []
            ];
        }
        else
            $data = ['medio' => '', 'forma' => '', 'identificador_pago' => []];

        return $data;
    }

    /**
     * Retorna el impuesto IVA de un item, si existe
     *
     * @param $item
     * @return mixed|null
     */
    public function getIvaItem($item) {
        if (is_null($this->objetoImpuestoIva))
            $this->objetoImpuestoIva = ParametrosTributo::where('tri_codigo', self::IMPUESTO_IVA)->first();
        if (!is_null($item) && isset($item['get_impuestos_items_documentos_daop'])) {
            foreach ($item['get_impuestos_items_documentos_daop'] as $impuesto) {
                if ($impuesto['tri_id'] === $this->objetoImpuestoIva->tri_id) {
                    return $impuesto;
                }
            }
        }
        return null;
    }

    static function agregar(&$retencion, $item) {
        $valorNacional = array_key_exists('valor', $retencion) ? $retencion['valor'] : 0.00;
        $valorExtranjera = array_key_exists('valor_extranjera', $retencion) ? $retencion['valor_extranjera'] : 0.00;
        $baseNacional = array_key_exists('base', $retencion) ? $retencion['base'] : 0.00;
        $baseExtranjera = array_key_exists('base_extranjera', $retencion) ? $retencion['base_extranjera'] : 0.00;
        $valorNacional += $item->cdd_valor;
        $valorExtranjera += $item->cdd_valor_moneda_extranjera;
        $baseNacional += $item->cdd_base;
        $baseExtranjera += $item->cdd_base_moneda_extranjera;
        $retencion['valor'] = $valorNacional;
        $retencion['valor_extranjera'] = $valorExtranjera;
        $retencion['base'] = $baseNacional;
        $retencion['base_extranjera'] = $baseExtranjera;
    }

    public function getCargoDescuentosRetencionesTipo($id, $modo = self::MODO_CONSULTA_CABECERA, $porcentaje = false) {
        $allowed = [self::MODO_CONSULTA_CABECERA, self::MODO_CONSULTA_ITEM];
        $modo = strtolower($modo);
        if (!in_array($modo, $allowed))
            $modo = self::MODO_CONSULTA_CABECERA;

        $field_id = $modo === self::MODO_CONSULTA_CABECERA ? 'cdo_id' : 'ddo_id';
        $aplica = $modo === self::MODO_CONSULTA_CABECERA ? 'CABECERA' : 'DETALLE';

        $buscador = EtlCargosDescuentosDocumentoDaop::select([
            'cdd_id',
            'cdo_id',
            'ddo_id',
            'cdd_aplica',
            'cdd_tipo',
            'cdd_indicador',
            'cdd_razon',
            'cdd_porcentaje',
            'cdd_valor',
            'cdd_valor_moneda_extranjera',
            'cdd_base',
            'cdd_base_moneda_extranjera'
        ])
            ->where($field_id, $id)
            ->where('cdd_aplica', $aplica);
        if ($porcentaje !== self::MODO_PORCENTAJE_DETALLAR && $porcentaje !== self::MODO_PORCENTAJE_IGNORAR && is_numeric($porcentaje))
            $buscador->where('cdd_porcentaje', $porcentaje);

        $resultado = [];
        $buscador->get()
            ->map(function ($item) use (&$resultado, $porcentaje) {
                $retencion = [];
                if (array_key_exists($item->cdd_tipo, $resultado))
                    $retencion = $resultado[$item->cdd_tipo];

                // Se totaliza todo por tipo
                if ($porcentaje === self::MODO_PORCENTAJE_IGNORAR) {
                    self::agregar($retencion, $item);
                }
                // Se totaliza por cada sub-grupo de porcentaje
                elseif ($porcentaje === self::MODO_PORCENTAJE_DETALLAR) {
                    $retPorcentaje = array_key_exists("$item->cdd_porcentaje", $retencion) ? $retencion["$item->cdd_porcentaje"] : [];
                    self::agregar($retPorcentaje, $item);
                    $retencion["$item->cdd_porcentaje"] = $retPorcentaje;
                }
                // Se totaliza solo un porcentaje en particular
                else {
                    $retPorcentaje = array_key_exists("$porcentaje", $retencion) ? $retencion["$porcentaje"] : [];
                    self::agregar($retPorcentaje, $item);
                    $retencion["$porcentaje"] = $retPorcentaje;
                }
                $resultado[$item->cdd_tipo] = $retencion;
            });

        return $resultado;
    }
}