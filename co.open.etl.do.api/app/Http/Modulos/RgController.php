<?php
namespace App\Http\Modulos;

use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use App\Http\Traits\DocumentosTrait;
use App\Http\Traits\NumToLetrasEngine;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Unidades\ParametrosUnidad;
use App\Http\Modulos\RepresentacionesGraficas\Core\RgBase;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPago;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuento;
use App\Http\Modulos\Parametros\PreciosReferencia\ParametrosPrecioReferencia;
use App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProducto;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Controlador para gestionar la generación de representacines graficas.
 *
 * Class RgController
 * @package App\Http\Modulos
 */
class RgController extends Controller {
    use FechaVigenciaValidations;

    /**
     * Reglas para las facturas.
     *
     * @var array
     */
    public $rulesFacturas;

    /**
     * Reglas para las notas crédito o notas débito.
     *
     * @var array
     */
    public $rulesNdNc;

    public function __construct()
    {
        $this->middleware(['jwt.auth', 'jwt.refresh'])
            ->except([
                'notificacionDocumento'
            ]);

        // Reglas de validación para facturas
        $this->rulesFacturas = [
            'ofe_identificacion' => 'required|numeric',
            'rfa_prefijo' => 'nullable|string|max:5',
            'cdo_consecutivo' => 'required|string|max:20'
        ];

        // Reglas de validación para notas crédito
        $this->rulesNdNc = [
            'ofe_identificacion' => 'required|numeric',
            'cdo_consecutivo' => 'required|string|max:20'
        ];
    }

    /**
     * Retorna el PDF de un documento electrónico.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function getPdfRepresentacionGraficaDocumento(Request $request) {
        $documento = $this->getPdfDoc($request->cdo_id);
        if(array_key_exists('clase', $documento) && $documento['clase'] === false) {
            return response()->json([
                'message' => 'Error en la Petición',
                'errors'  => $documento['error']
            ], 404);
        } elseif (array_key_exists('error', $documento) && !$documento['error']) {
            return response()->json([
                'data' => [
                    'pdf' => base64_encode($documento['pdf'])
                ]
            ]);
        } else {
            return response()->json([
                'message' => 'Error en la Petición',
                'errors'  => 'El documento electr&oacute;nico seleccionado no ex&iacute;ste'
            ], 404);
        }
    }

    /**
     * Construye un PDF dado su identificador y un oferente.
     *
     * @param string $cdo_id ID del documento electrónico
     * @return JsonResponse|mixed|null
     */
    public function getPdfDoc(string $cdo_id) {
        $user = auth()->user();
        $representacionGrafica = RgBase::resolve($cdo_id, $user, $this->obtenerParametricas());
        if (array_key_exists('clase', $representacionGrafica) && $representacionGrafica['clase'] === null) {
            return [
                'clase' => null
            ];
        } elseif (array_key_exists('clase', $representacionGrafica) && $representacionGrafica['clase'] === false) {
            return [
                'clase' => false,
                'error' => $representacionGrafica['error']
            ];
        }

        return $representacionGrafica->getPdf();
    }

    /**
     * Retorna un array con las paramétricas requeridas en el proceso que permite ver la RG de un documento electrónico que está siendo creado/editado manualmente.
     *
     * @return array Array de paramétricas
     */
    private function obtenerParametricas() {
        return [
            'mediosPago' => ParametrosMediosPago::select('mpa_id', 'mpa_codigo', 'mpa_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('mpa_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'formasPago' => ParametrosFormaPago::select('fpa_id', 'fpa_codigo', 'fpa_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('fpa_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'tributos' => ParametrosTributo::select('tri_id', 'tri_codigo', 'tri_nombre', 'tri_tipo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('tri_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'unidades' => ParametrosUnidad::select('und_id', 'und_codigo', 'und_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('und_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'tiposOperacion' => ParametrosTipoOperacion::select('top_id', 'top_codigo', 'top_descripcion', 'top_aplica_para', 'top_sector', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->whereIn('top_aplica_para', ['FC', 'NC', 'ND', 'DS'])
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('top_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'tiposDocumentoElectronico' => ParametrosTipoDocumentoElectronico::select('tde_id', 'tde_codigo', 'tde_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->where('tde_aplica_para', 'LIKE', '%DE%')
                ->get()
                ->groupBy('tde_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'tiposDocumento' => ParametrosTipoDocumento::select('tdo_id', 'tdo_codigo', 'tdo_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('tdo_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'tiposOrganizacionJuridica' => ParametrosTipoOrganizacionJuridica::select('toj_id', 'toj_codigo', 'toj_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('toj_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'regimenFiscal' => ParametrosRegimenFiscal::select('rfi_id', 'rfi_codigo', 'rfi_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('rfi_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'clasificacionProductos' => ParametrosClasificacionProducto::select('cpr_id', 'cpr_codigo', 'cpr_nombre', 'cpr_identificador', 'cpr_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('cpr_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'preciosReferencia' => ParametrosPrecioReferencia::select('pre_id', 'pre_codigo', 'pre_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('pre_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'paises' => ParametrosPais::select('pai_id', 'pai_codigo', 'pai_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('pai_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),

            'codigosDescuento' => ParametrosCodigoDescuento::select('cde_id', 'cde_codigo', 'cde_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->get()
                ->groupBy('cde_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray()
        ];
    }

    /**
     * Retorna un objeto que emula una colección del modelo EtlCargosDescuentosDocumentoDaop.
     *
     * @param int    $numeroLinea Número de línea correspondiente al cargo / descuento /retencion sugerida
     * @param object $valores Objeto conteniendo los valores a procesar
     * @param string $aplica Indicador de aplicabilidad a CABECERA o DETALLE del documento
     * @param string $tipo Cadena que indica si corresponde a cargo / descuento /retencion sugerida
     * @param string $indicador Indicador de cargo
     * @param string $nombre Nombre del cargo /descuento
     * @param int    $cdId ID del código de descuento
     * @param int    $ddoId ID del item
     * @return string Representación JSON del array generado
     */
    private function crearObjetoCargosDescuentosRetenciones(&$numeroLinea, $valores, $aplica, $tipo, $indicador, $nombre, $cdId, $ddoId = null) {
        $objeto = json_decode(json_encode([
            'cdd_numero_linea'            => $numeroLinea,
            'cdd_aplica'                  => $aplica,
            'ddo_id'                      => $ddoId,
            'cdd_tipo'                    => $tipo == 'RETERENTA' ? 'RETEFUENTE' : $tipo,
            'cdd_indicador'               => $indicador,
            'cdd_nombre'                  => $nombre,
            'cdd_razon'                   => $valores->razon,
            'cdd_porcentaje'              => $valores->porcentaje,
            'cdd_valor'                   => isset($valores->valor_moneda_nacional) && isset($valores->valor_moneda_nacional->valor) ? $valores->valor_moneda_nacional->valor : null,
            'cdd_valor_moneda_extranjera' => isset($valores->valor_moneda_extranjera) && isset($valores->valor_moneda_extranjera->valor) ? $valores->valor_moneda_extranjera->valor : null,
            'cdd_base'                    => isset($valores->valor_moneda_nacional) && isset($valores->valor_moneda_nacional->base) ? $valores->valor_moneda_nacional->base : null,
            'cdd_base_moneda_extranjera'  => isset($valores->valor_moneda_extranjera) && isset($valores->valor_moneda_extranjera->base) ? $valores->valor_moneda_extranjera->base : null,
            'cde_codigo'                  => isset($valores->cde_codigo) ? $valores->cde_codigo : null,
            'cde_id'                      => $cdId
        ]));

        $numeroLinea++;

        return $objeto;
    }

    /**
     * Retorna el PDF de un documento electrónico que esta siendo creado/editado en facturacion web.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function facturacionWebVerRg(Request $request) {
        $user = auth()->user();

        if (!$request->has('documentos')) {
            return $this->generateErrorResponse('La petici&oacute;n esta mal formada, debe especificarse un tipo y objeto JSON');
        }

        $json = json_decode(json_encode($request->documentos));

        if (!isset($json->NC) && !isset($json->ND) && !isset($json->FC) && !isset($json->DS) && !isset($json->DS_NC)) {
            return $this->generateErrorResponse('La petici&oacute;n esta mal formada, no existe la propiedad asociada a FC, NC, ND, DS o DS_NC');
        } elseif(isset($json->FC) && !empty($json->FC)) {
            $cdoClasificacion = 'FC';
            $documento = $json->FC[0];
        } elseif(isset($json->ND) && !empty($json->ND)) {
            $cdoClasificacion = 'ND';
            $documento = $json->ND[0];
        } elseif(isset($json->NC) && !empty($json->NC)) {
            $cdoClasificacion = 'NC';
            $documento = $json->NC[0];
        } elseif(isset($json->DS) && !empty($json->DS)) {
            $cdoClasificacion = 'DS';
            $documento = $json->DS[0];
        } elseif(isset($json->DS_NC) && !empty($json->DS_NC)) {
            $cdoClasificacion = 'DS_NC';
            $documento = $json->DS_NC[0];
        }

        $tipoDoc = DocumentosTrait::tipoDocumento($cdoClasificacion, trim($documento->rfa_prefijo));
        $idRepresentacionGrafica = $documento->cdo_representacion_grafica_documento;

        $oferente = ConfiguracionObligadoFacturarElectronicamente::where('ofe_identificacion', $documento->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->with([
                'getParametroTipoDocumento:tdo_id,tdo_codigo,tdo_descripcion',
                'getParametrosRegimenFiscal:rfi_id,rfi_codigo,rfi_descripcion',
                'getParametrosDepartamento:dep_id,dep_codigo,dep_descripcion',
                'getParametrosMunicipio:mun_id,mun_codigo,mun_descripcion',
                'getParametrosPais:pai_id,pai_codigo,pai_descripcion',
                'getCodigoPostal:cpo_id,cpo_codigo',
                'getParametroDomicilioFiscalDepartamento:dep_id,dep_codigo,dep_descripcion',
                'getParametroDomicilioFiscalMunicipio:mun_id,mun_codigo,mun_descripcion',
                'getParametroDomicilioFiscalPais:pai_id,pai_codigo,pai_descripcion',
                'getCodigoPostalDomicilioFiscal:cpo_id,cpo_codigo',
                'getBaseDatosRg:bdd_id,bdd_nombre',
                'getConfiguracionSoftwareProveedorTecnologico'
            ])
            ->first();

        if(!$oferente)
            return $this->generateErrorResponse('El OFE con identificaci&oacute;n [' . $documento->ofe_identificacion . '] no existe o se encuentra inactivo');

        if(isset($oferente->getParametroTipoDocumento) && $oferente->getParametroTipoDocumento->tdo_codigo == '31')
            $ofe_dv = TenantTrait::calcularDV($oferente->ofe_identificacion);
        else
            $ofe_dv = '';

        if (isset($oferente->ofe_representacion_grafica) && !empty($oferente->ofe_representacion_grafica)) {
            $ofeRepresentacionGrafica    = json_decode($oferente->ofe_representacion_grafica);
            $camposRepresentacionGrafica = (isset($ofeRepresentacionGrafica->$cdoClasificacion->$idRepresentacionGrafica)) ? $ofeRepresentacionGrafica->$cdoClasificacion->$idRepresentacionGrafica : '';
        } else
            $camposRepresentacionGrafica = '';

        if(!empty($oferente->bdd_id_rg))
            $baseDatos = $oferente->getBaseDatosRg->bdd_nombre;
        else
            $baseDatos = $user->getBaseDatos->bdd_nombre;
        
        $adquirente = ConfiguracionAdquirente::where('adq_identificacion', $documento->adq_identificacion)
            ->where('estado', 'ACTIVO')
            ->with([
                'getParametroDepartamento:dep_id,dep_codigo,dep_descripcion',
                'getParametroMunicipio:mun_id,mun_codigo,mun_descripcion',
                'getParametroPais:pai_id,pai_codigo,pai_descripcion',
                'getCodigoPostal:cpo_id,cpo_codigo',
                'getParametroDomicilioFiscalDepartamento:dep_id,dep_codigo,dep_descripcion',
                'getParametroDomicilioFiscalMunicipio:mun_id,mun_codigo,mun_descripcion',
                'getParametroDomicilioFiscalPais:pai_id,pai_codigo,pai_descripcion',
                'getCodigoPostalDomicilioFiscal:cpo_id,cpo_codigo',
                'getParametroTipoDocumento:tdo_id,tdo_codigo,tdo_descripcion'
            ])
            ->first();

        if(!$adquirente)
            return $this->generateErrorResponse('El Adquirente con identificacion [' . $documento->adq_identificacion . '] no existe o se encuentra inactivo');

        if(isset($adquirente->getParametroTipoDocumento) && $adquirente->getParametroTipoDocumento->tdo_codigo == '31')
            $adq_dv = TenantTrait::calcularDV($adquirente->adq_identificacion);
        else
            $adq_dv = '';

        if (isset($documento->rfa_resolucion)) {
            $resolucion = ConfiguracionResolucionesFacturacion::where('rfa_resolucion', $documento->rfa_resolucion)
                ->where('estado', 'ACTIVO');

            if(isset($documento->rfa_prefijo) && !empty($documento->rfa_prefijo))
                $resolucion = $resolucion->where('rfa_prefijo', $documento->rfa_prefijo);
            else
                $resolucion = $resolucion->where(function($query) {
                    $query->whereNull('rfa_prefijo')
                        ->orWhere('rfa_prefijo', '');
                });

            $resolucion = $resolucion->first();

            if(!$resolucion)
                return $this->generateErrorResponse('La resoluci&oacute;n [' . $documento->rfa_resolucion . '] con prefijo [' . $documento->rfa_prefijo . '] no existe o se encuentra inactiva');
        }

        // Forma de pago - cálculo de días entre la fecha del documento y la fecha de vencimiento
        $fini      = Carbon::parse($documento->cdo_fecha);
        $ffin      = Carbon::parse($documento->cdo_vencimiento);
        $dias_pago = $ffin->diffInDays($fini);

        // Verifica si el Adquirente es un consumidor final, porque en ese caso hay datos de paramétricos que no se pueden obtener
        $consumidorFinal = false;
        $adquirenteConsumidorFinal = json_decode(config('variables_sistema.ADQUIRENTE_CONSUMIDOR_FINAL'));
        foreach($adquirenteConsumidorFinal as $consumidor) {
            $arrAdquirenteConsumidorFinal[$consumidor->adq_identificacion] = $consumidor;
        }

        if(array_key_exists($adquirente->adq_identificacion, $arrAdquirenteConsumidorFinal)) {
            $consumidorFinal = true;
        }

        $parametricas = $this->obtenerParametricas();

        // Clase que contiene el método que permite realizar búsquedas en el array de paramétricas
        $metodosBase = new MetodosBase();

        $contadorCargosDescuentosRetenciones = 1;
        $detalleCargosDescuentosRetenciones = [];
        if(isset($documento->cdo_detalle_cargos) && !empty($documento->cdo_detalle_cargos)) {
            foreach ($documento->cdo_detalle_cargos as $cargo) {
                $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $cargo, 'CABECERA', 'CARGO', 'true', (isset($cargo->nombre) ? $cargo->nombre : null), null);
            }
        }

        if(isset($documento->cdo_detalle_descuentos) && !empty($documento->cdo_detalle_descuentos)) {
            foreach ($documento->cdo_detalle_descuentos as $descuento) {
                $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $descuento, 'CABECERA', 'DESCUENTO', 'false', (isset($descuento->nombre) ? $descuento->nombre : null), $metodosBase->obtieneDatoParametrico($parametricas['codigosDescuento'], 'cde_codigo', $descuento->cde_codigo, 'cde_id'));
            }
        }

        if(isset($documento->cdo_detalle_retenciones_sugeridas) && !empty($documento->cdo_detalle_retenciones_sugeridas)) {
            foreach ($documento->cdo_detalle_retenciones_sugeridas as $retencionSugerida) {
                $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $retencionSugerida, 'CABECERA', $retencionSugerida->tipo, 'false', null, null);
            }
        }

        // Ajusta la propiedad items del documento para agregar elementos faltantes
        $items = [];
        foreach($documento->items as $item) {
            if(isset($item->und_codigo) && !empty($item->und_codigo))
                $item->und_id = $metodosBase->obtieneDatoParametrico($parametricas['unidades'], 'und_codigo', $item->und_codigo, 'und_id');

            if(isset($item->cpr_codigo) && !empty($item->cpr_codigo))
                $item->cpr_id = $metodosBase->obtieneDatoParametrico($parametricas['clasificacionProductos'], 'cpr_codigo', $item->cpr_codigo, 'cpr_id');

            if(isset($item->pre_codigo) && !empty($item->pre_codigo))
                $item->pre_id = $metodosBase->obtieneDatoParametrico($parametricas['preciosReferencia'], 'pre_codigo', $item->pre_codigo, 'pre_id');

            if(isset($item->pai_codigo) && !empty($item->pai_codigo))
                $item->pai_id = $metodosBase->obtieneDatoParametrico($parametricas['paises'], 'pai_codigo', $item->pai_codigo, 'pai_id');

            if(isset($item->ddo_informacion_adicional) && empty($item->ddo_informacion_adicional))
                $item->ddo_informacion_adicional = "";
            elseif(isset($item->ddo_informacion_adicional) && !empty($item->ddo_informacion_adicional))
                $item->ddo_informacion_adicional = json_encode((array) $item->ddo_informacion_adicional);

            if(isset($item->ddo_notas) && empty($item->ddo_notas))
                $item->ddo_notas = "";

            if(isset($item->ddo_datos_tecnicos) && empty($item->ddo_datos_tecnicos))
                $item->ddo_datos_tecnicos = "";

            $items[] = (array)$item;

            // Cargos, descuentos y retenciones a nivel de items
            if(isset($item->ddo_cargos) && !empty($item->ddo_cargos)) {
                foreach ($item->ddo_cargos as $cargo) {
                    $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $cargo, 'DETALLE', 'CARGO', 'true', (isset($cargo->nombre) ? $cargo->nombre : null), null, (isset($item->ddo_id) ? $item->ddo_id : null));
                }
            }

            if(isset($item->ddo_descuentos) && !empty($item->ddo_descuentos)) {
                foreach ($item->ddo_descuentos as $descuento) {
                    $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $descuento, 'DETALLE', 'DESCUENTO', 'false', (isset($descuento->nombre) ? $descuento->nombre : null), null, (isset($item->ddo_id) ? $item->ddo_id : null));
                }
            }

            if(isset($item->ddo_detalle_retenciones_sugeridas) && !empty($item->ddo_detalle_retenciones_sugeridas)) {
                foreach ($item->ddo_detalle_retenciones_sugeridas as $retencion) {
                    $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $retencion, 'DETALLE', $retencion->tipo, 'false', null, null);
                }
            }
        }
        
        $items = collect($items)
            ->sortBy(function ($item, $indice) {
                return abs($item['ddo_secuencia']);
            });
        $items = $items->toArray();

        $datosAdicionales = null;
        if(isset($documento->dad_terminos_entrega) && !empty($documento->dad_terminos_entrega)) {
            $datosAdicionales = json_decode('{"dad_terminos_entrega":""}');
            $datosAdicionales->dad_terminos_entrega = [0 => json_decode(json_encode($documento->dad_terminos_entrega[0]), true)];
        }

        $mediosPagosDocumento = [];
        foreach($documento->cdo_medios_pago as $medioPago) {
            $descMedioPago = $metodosBase->obtieneDatoParametrico($parametricas['mediosPago'], 'mpa_codigo', $medioPago->mpa_codigo, 'mpa_descripcion');

            $descFormaPago = $metodosBase->obtieneDatoParametrico($parametricas['formasPago'], 'fpa_codigo', $medioPago->fpa_codigo, 'fpa_descripcion');

            $mediosPagosDocumento[] = [
                'medio' => $descMedioPago != '' ? [
                    'mpa_codigo'      => $medioPago->mpa_codigo,
                    'mpa_descripcion' => $descMedioPago
                ] : [],
                'forma' => $descFormaPago != '' ? [
                    'fpa_codigo'      => $medioPago->fpa_codigo,
                    'fpa_descripcion' => $descFormaPago
                ] : [],
                'identificador' => isset($medioPago->men_identificador_pago) ? $medioPago->men_identificador_pago : []
            ];
        }

        $impuestosItems     = [];
        $impuestosDocumento = [];
        $porcentajeIva      = 0;
        $codigoIva          = $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_nombre', 'IVA', 'tri_codigo');
        foreach($documento->tributos as $tributo) {
            $triId   = $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_codigo', $tributo->tri_codigo, 'tri_id');
            $triTipo = $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_codigo', $tributo->tri_codigo, 'tri_tipo');

            $undId = null;
            if($tributo->iid_unidad && $tributo->iid_unidad->und_codigo) {
                $undId = $metodosBase->obtieneDatoParametrico($parametricas['unidades'], 'und_codigo', $tributo->iid_unidad->und_codigo, 'und_id');
            }

            $impuesto = [
                'tri_id'                                  => $triId,
                'iid_tipo'                                => $triTipo,
                'iid_nombre_figura_tributaria'            => $tributo->iid_nombre_figura_tributaria ? $tributo->iid_nombre_figura_tributaria : null,
                'iid_base'                                => $tributo->iid_porcentaje && $tributo->iid_porcentaje->iid_base ? $tributo->iid_porcentaje->iid_base : null,
                'iid_base_moneda_extranjera'              => $tributo->iid_porcentaje && $tributo->iid_porcentaje->iid_base_moneda_extranjera ? $tributo->iid_porcentaje->iid_base_moneda_extranjera : null,
                'iid_porcentaje'                          => $tributo->iid_porcentaje && $tributo->iid_porcentaje->iid_porcentaje ? $tributo->iid_porcentaje->iid_porcentaje : null,
                'iid_cantidad'                            => $tributo->iid_unidad && $tributo->iid_unidad->iid_cantidad ? $tributo->iid_unidad->iid_cantidad : null,
                'und_id'                                  => (string) $undId,
                'iid_valor_unitario'                      => $tributo->iid_unidad && $tributo->iid_unidad->iid_valor_unitario ? $tributo->iid_unidad->iid_valor_unitario : null,
                'iid_valor_unitario_moneda_extranjera'    => $tributo->iid_unidad && $tributo->iid_unidad->iid_valor_unitario_moneda_extranjera ? $tributo->iid_unidad->iid_valor_unitario_moneda_extranjera : null,
                'iid_valor'                               => $tributo->iid_valor ? $tributo->iid_valor : null,
                'iid_valor_moneda_extranjera'             => $tributo->iid_valor_moneda_extranjera ? $tributo->iid_valor_moneda_extranjera : null,
                'iid_motivo_exencion'                     => $tributo->iid_motivo_exencion ? $tributo->iid_motivo_exencion : null,
            ];

            $impuestosItems[]     = $impuesto;
            $impuestosDocumento[] = json_decode(json_encode($impuesto));

            if ($tributo->tri_codigo == $codigoIva && $tributo->iid_porcentaje->iid_porcentaje > 0 && $porcentajeIva == 0) {
                $porcentajeIva = number_format($tributo->iid_porcentaje->iid_porcentaje, 2, '.', '');
            }
        }

        $signo = 0.0;
        $total = $documento->cdo_total;
        $totalMonedaExtranjera  = $documento->cdo_total_moneda_extranjera;
        $valorLetras            = NumToLetrasEngine::num2letras(number_format($total, 2, '.', ''), false, true, $documento->mon_codigo);

        $valorPagar = $this->calcularValorAPagar($documento);

        if(array_key_exists('errores', $valorPagar) && !empty($valorPagar['errores'])) {
            return $this->generateErrorResponse('', $valorPagar['errores']);
        }

        $objImpuestosRegistrados = [];
        ParametrosTributo::select(['tri_id', 'tri_codigo'])->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($item) use (&$objImpuestosRegistrados) {
                $objImpuestosRegistrados[$item->tri_id] = $item->tri_codigo;
            });

        $docConceptosCorrecion = [];
        if (isset($documento->cdo_conceptos_correccion) && $documento->cdo_conceptos_correccion !== null && $documento->cdo_conceptos_correccion !== '') {
            $docConceptosCorrecion = RgBase::convertToObject($documento->cdo_conceptos_correccion );
        }

        $anticiposDocumento = null;
        if(isset($documento->cdo_detalle_anticipos) && !empty($documento->cdo_detalle_anticipos))
            $anticiposDocumento = collect($documento->cdo_detalle_anticipos);

        $esSectorSalud = false;
        if(!empty($parametricas) && is_array($parametricas) && array_key_exists('tiposOperacion', $parametricas)) {
            $sectoresTipoOperacion = explode(',', $metodosBase->obtieneDatoParametrico($parametricas['tiposOperacion'], 'top_codigo', $documento->top_codigo, 'top_sector'));
            if(in_array('SECTOR_SALUD', $sectoresTipoOperacion) && ($documento->top_codigo == 'SS-CUFE' || $documento->top_codigo == 'SS-CUDE' || $documento->top_codigo == 'SS-POS' || $documento->top_codigo == 'SS-SNum'))
                $esSectorSalud = true;
        }

        $datos = [
            'cdo_id'                                  => null,
            'cdo_tipo'                                => $cdoClasificacion,
            'cdo_origen'                              => 'MANUAL',
            'cdo_tipo_nombre'                         => $tipoDoc['clasificacionDoc'],
            'rfa_prefijo'                             => $tipoDoc['prefijo'],
            'cdo_consecutivo'                         => $documento->cdo_consecutivo,
            'cdo_moneda'                              => $documento->mon_codigo,
            'cdo_moneda_extranjera'                   => isset($documento->mon_codigo_extranjera) && !empty($documento->mon_codigo_extranjera) ? $documento->mon_codigo_extranjera : null,
            'cdo_trm'                                 => isset($documento->cdo_trm) && !empty($documento->cdo_trm) ? $documento->cdo_trm : null,
            'cdo_trm_fecha'                           => isset($documento->cdo_trm_fecha) && !empty($documento->cdo_trm_fecha) ? $documento->cdo_trm_fecha : null,
            'cdo_documento_referencia'                => isset($documento->cdo_documento_referencia) && !empty($documento->cdo_documento_referencia) ? $documento->cdo_documento_referencia : [],
            'cdo_documento_adicional'                 => isset($documento->cdo_documento_adicional) && !empty($documento->cdo_documento_adicional) ? $documento->cdo_documento_adicional : null,
            'cdo_informacion_adicional'               => isset($documento->cdo_informacion_adicional) && !empty($documento->cdo_informacion_adicional) ? $documento->cdo_informacion_adicional : null,
            'dad_orden_referencia'                    => isset($documento->dad_orden_referencia) && !empty($documento->dad_orden_referencia) ? $documento->dad_orden_referencia : null,
            'dad_terminos_entrega'                    => isset($documento->dad_terminos_entrega) && !empty($documento->dad_terminos_entrega) ? RgBase::convertToObject($documento->dad_terminos_entrega) : null,
            'datos_adicionales'                       => $datosAdicionales,
            'cdo_anticipo'                            => isset($documento->cdo_anticipo) && !empty($documento->cdo_anticipo) ? number_format($documento->cdo_anticipo, 2, ',', '.') : '',
            'cdo_anticipo_moneda_extranjera'          => isset($documento->cdo_anticipo_moneda_extranjera) && !empty($documento->cdo_anticipo_moneda_extranjera) ? number_format($documento->cdo_anticipo_moneda_extranjera, 2, ',', '.') : '',
            'cdo_descuentos'                          => isset($documento->cdo_descuentos) && !empty($documento->cdo_descuentos) ? $documento->cdo_descuentos : '',
            'cdo_descuentos_moneda_extranjera'        => isset($documento->cdo_descuentos_moneda_extranjera) && !empty($documento->cdo_descuentos_moneda_extranjera) ? $documento->cdo_descuentos_moneda_extranjera : '',
            'ofe_representacion_grafica'              => $camposRepresentacionGrafica,
            'fecha_hora_documento'                    => (isset($documento->cdo_fecha) && !empty($documento->cdo_fecha) ? $documento->cdo_fecha : '') . ' ' . (isset($documento->cdo_hora) && !empty($documento->cdo_hora) ? $documento->cdo_hora : ''),
            'cdo_fecha'                               => isset($documento->cdo_fecha) && !empty($documento->cdo_fecha) ? $documento->cdo_fecha : '',
            'cdo_hora'                                => isset($documento->cdo_hora) && !empty($documento->cdo_hora) ? $documento->cdo_hora : '',
            'fecha_vencimiento'                       => isset($documento->cdo_vencimiento) && !empty($documento->cdo_vencimiento) ? $documento->cdo_vencimiento : '',
            'fecha_creacion'                          => date('Y-m-d H:i:s'),
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
            'adq_informacion_personalizada'           => !empty($adquirente->adq_informacion_personalizada) ? RgBase::convertToObject($adquirente->adq_informacion_personalizada) : '',
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
            'ofe_resolucion'                          => (isset($documento->rfa_resolucion) && $resolucion) ? $resolucion->rfa_resolucion : '',
            'ofe_resolucion_fecha'                    => (isset($documento->rfa_resolucion) && $resolucion) ? $resolucion->rfa_fecha_desde : '',
            'ofe_resolucion_fecha_hasta'              => (isset($documento->rfa_resolucion) && $resolucion) ? $resolucion->rfa_fecha_hasta : '',
            'ofe_resolucion_prefijo'                  => (isset($documento->rfa_resolucion) && $resolucion) ? $resolucion->rfa_prefijo : '',
            'ofe_resolucion_desde'                    => (isset($documento->rfa_resolucion) && $resolucion) ? $resolucion->rfa_consecutivo_inicial : '',
            'ofe_resolucion_hasta'                    => (isset($documento->rfa_resolucion) && $resolucion) ? $resolucion->rfa_consecutivo_final : '',
            'ofe_resolucion_vigencia'                 => (isset($documento->rfa_resolucion) && $resolucion) ? Carbon::parse($resolucion->rfa_fecha_hasta)->diffInMonths($resolucion->rfa_fecha_desde) : null,
            'ofe_campos_personalizados_factura'       => $oferente->ofe_campos_personalizados_factura_generica,
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
            'detalle_cargos_descuentos'               => $detalleCargosDescuentosRetenciones,
            'retenciones_sugeridas'                   => number_format($documento->cdo_retenciones_sugeridas, 2, ',', '.'),
            'retenciones_sugeridas_moneda_extranjera' => number_format($documento->cdo_retenciones_sugeridas_moneda_extranjera, 2, ',', '.'),
            'anticipo'                                => isset($documento->cdo_anticipo) && !empty($documento->cdo_anticipo) ? number_format($documento->cdo_anticipo, 2, ',', '.') : '',
            'anticipo_moneda_extranjera'              => isset($documento->cdo_anticipo_moneda_extranjera) && !empty($documento->cdo_anticipo_moneda_extranjera) ? number_format($documento->cdo_anticipo_moneda_extranjera, 2, ',', '.') : '',
            'signo'                                   => $signo,
            'total_con_descuentos'                    => number_format($total, 2, ',', '.'),
            'total_moneda_extranjera_con_descuentos'  => number_format($totalMonedaExtranjera, 2, ',', '.'),
            'valor_a_pagar'                           => number_format((isset($documento->cdo_anticipo) && !$esSectorSalud && $documento->cdo_anticipo > 0) ? ($valorPagar['cdo_valor_a_pagar'] - $documento->cdo_anticipo) : $valorPagar['cdo_valor_a_pagar'], 2, ',', '.'),
            'valor_a_pagar_moneda_extranjera'         => number_format((isset($documento->cdo_anticipo) && !$esSectorSalud && $documento->cdo_anticipo_moneda_extranjera > 0) ? ($valorPagar['cdo_valor_a_pagar_moneda_extranjera'] - $documento->cdo_anticipo_moneda_extranjera) : $valorPagar['cdo_valor_a_pagar_moneda_extranjera'], 2, ',', '.'),
            'cdo_fecha_validacion_dian'               => null,
            'observacion'                             => isset($documento->cdo_observacion) && !empty($documento->cdo_observacion) ? json_encode($documento->cdo_observacion) : json_encode([]),
            'valor_letras'                            => $valorLetras,
            'signaturevalue'                          => null,
            'cufe'                                    => null,
            'qr'                                      => null,
            'impuestos_registrados'                   => $objImpuestosRegistrados,
            'usuario_creacion'                        => mb_strtoupper($user->usu_nombre),
            'cdo_conceptos_correccion'                => $docConceptosCorrecion,
            'nit_pt'                                  => config('variables_sistema.NIT_PT'),
            'razon_social_pt'                         => config('variables_sistema.RAZON_SOCIAL_PT'),
            'software_pt'                             => (isset($oferente->getConfiguracionSoftwareProveedorTecnologico) && !empty($oferente->getConfiguracionSoftwareProveedorTecnologico)) ? $oferente->getConfiguracionSoftwareProveedorTecnologico : '',
            'tipo_documento_electronico'              => ParametrosTipoDocumentoElectronico::select('tde_id', 'tde_codigo', 'tde_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('estado', 'ACTIVO')
                ->where('tde_codigo', $documento->tde_codigo)
                ->first(),
            'anticipos_documento'                     => $anticiposDocumento,
            'impuestos_documento'                     => $impuestosDocumento
        ];

        RgBase::setearLogo($datos, $oferente);

        $assets = '';
        $baseDatos = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos);
        if ($datos['cdo_tipo'] == 'DS' || $datos['cdo_tipo'] == 'DS_NC') {
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
            if ($oferente->ofe_tiene_representacion_grafica_personalizada === 'SI') {
                $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\' . $baseDatos . '\\rg' . $oferente->ofe_identificacion . '\\Rg' . $oferente->ofe_identificacion . '_' . $idRepresentacionGrafica;
                if(!class_exists($clase)) {
                    $clase = false;
                }
    
                DoTrait::setFilesystemsInfo();
                $assets = Storage::disk(config('variables_sistema.ETL_PUBLIC_STORAGE'))->getDriver()->getAdapter()->getPathPrefix() . 'ecm/assets-ofes/' . $baseDatos . '/' . $oferente->ofe_identificacion . '/';
            } else {
                // Proyecto especial CADISOFT, si el campo cdo_integracion es enviado en informacion adicional de cabecera
                // el cliente esta usando su propia RG, 
                // si no es enviado usa el estandar de CADISOFT
                if ($oferente->ofe_cadisoft_activo === 'SI' && !isset($documento->cdo_informacion_adicional->cdo_integracion))
                    $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\etl_cadisoft\\rgCadisoft\\RgCadisoftBase_1';
                else
                    $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\etl_generica\\rgGenerica\\RgGENERICA' . '_' . $idRepresentacionGrafica;
            }
        }

        $datos = [
            'assets' => $assets,
            'clase'  => $clase,
            'datos'  => $datos,
            'ofe_identificacion'      => $oferente->ofe_identificacion,
            'idRepresentacionGrafica' => $idRepresentacionGrafica
        ];

        if($datos['clase'] === false)
            return $this->generateErrorResponse('No se logr&oacute; establecer la clase asociada a la representaci&oacute;n gr&aacute;fica del documento electr&oacute;nico');

        if(!empty($oferente->bdd_id_rg))
            $baseDatos = $oferente->getBaseDatosRg->bdd_nombre;
        else
            $baseDatos = $user->getBaseDatos->bdd_nombre;

        $baseDatos = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos);
        $representacionGrafica = new $datos['clase']($datos['ofe_identificacion'], $baseDatos, $datos['idRepresentacionGrafica'], $datos['datos'], $datos['assets']);
        if ($representacionGrafica === null || $representacionGrafica === false)
            return $this->generateErrorResponse('No fue posible generar la representaci&oacute;n gr&aacute;fica del documento electr&oacute;nico');

        $rg = $representacionGrafica->getPdf();
        if(is_array($rg) && !$rg['error']) {
            $nombreRg = $cdoClasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo . '.pdf';
            Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))
                ->put($nombreRg, $rg['pdf']);
            
            $headers = [
                header('Access-Control-Expose-Headers: Content-Disposition')
            ];

            return response()
                ->download(storage_path('etl/descargas/' . $nombreRg), $nombreRg, $headers)
                ->deleteFileAfterSend(true);
        } else {
            return $this->generateErrorResponse('No fue posible generar la Representaci&oacute;n Gr&aacute;fica');
        }
    }

    /**
     * Dado un array de valores flotantes, define cual es la mayor cantidad de decimales entre ellos.
     *
     * @param array $arrFlotantes Array de flotantes
     * @return int $maxDecimales Número máximo de decimales encontrados
     */
    private static function maxDecimales(array $arrFlotantes) {
        $maxDecimales = 2;

        foreach ($arrFlotantes as $flotante) {
            $componentes = explode('.', $flotante);

            if(count($componentes) === 2 && strlen($componentes[1]) > $maxDecimales)
                $maxDecimales = strlen($componentes[1]);
        }

        return $maxDecimales;
    }

    /**
     * Calcula el redondeo de un flotante de acuerdo a estandar establecido por la DIAN en los anexos técnicos (NTC 3711).
     * 
     * @param float $in Número flotante a redondear
     * @param int $decimals Cantidad de decimales, por defecto 2
     * @return string Número flotante redondeado
     */
    public static function redondeo(float $in, int $decimals = 2) {
        $number = $in;
        if (!is_string($number))
            $number = (string) $number;
        $componentes = explode('.', $number);

        // Si hay una parte decimal
        if (count($componentes) === 2) {
            $decimalPart = $componentes[1];

            if (strlen($decimalPart) > $decimals) {
                $digit = (int) $decimalPart[$decimals];

                if ($digit > 5)
                    return round($in, $decimals);

                if ($digit === 5 && strlen($decimalPart) >= $decimals + 2) {
                    $lastdigit = (int) $decimalPart[$decimals + 1];
                    if ($lastdigit % 2)
                        return round($in, $decimals);
                }
            }

            return $componentes[0] . '.' . substr($decimalPart, 0, $decimals);
        }

        return $in;
    }

    /**
     * Calcula el valor a pagar en moneda local y moneda extranjera.
     *
     * @param \stdClass $document Documento que se está procesando
     * @return array Array conteniendo errores (si se generan) y los valores a pagar
     */
    private function calcularValorAPagar(\stdClass $document) {
        $errores              = [];
        $valorSinImpuestos    = isset($document->cdo_valor_sin_impuestos) ? floatval($document->cdo_valor_sin_impuestos) : 0.0;
        $impuestos            = isset($document->cdo_impuestos) ? floatval($document->cdo_impuestos) : 0.0;
        $cargos               = isset($document->cdo_cargos) ? floatval($document->cdo_cargos): 0.0;
        $descuentos           = isset($document->cdo_descuentos) ? floatval($document->cdo_descuentos) : 0.0;
        $retencionesSugeridas = isset($document->cdo_retenciones_sugeridas) ? floatval($document->cdo_retenciones_sugeridas) : 0.0;
        $anticipos            = isset($document->cdo_anticipo) ? floatval($document->cdo_anticipo) : 0.0;
        $redondeo             = isset($document->cdo_redondeo) ? floatval($document->cdo_redondeo) : 0.0;

        $cdo_valor_a_pagar = 0;
        $cdo_valor_a_pagar_moneda_extranjera = 0;

        $maxDecimales = $this->maxDecimales([$valorSinImpuestos, $impuestos, $cargos, $descuentos, $retencionesSugeridas, $redondeo]);
        $cdo_valor_a_pagar = $this->redondeo(round($valorSinImpuestos + $impuestos + $cargos - $descuentos - $retencionesSugeridas + ($redondeo), $maxDecimales), $maxDecimales);

        if($cdo_valor_a_pagar < 0) {
            $errores[] = "Valor a pagar negativo. Verifique valores de descuentos y/o retenciones sugeridas";
        }

        if($cdo_valor_a_pagar < $anticipos) {
            $errores[] = "El valor total del anticipo no puede ser mayor al valor total a pagar";
        }

        if (isset($document->mon_codigo_extranjera) && !empty($document->mon_codigo_extranjera)) {
            $valorSinImpuestos    = isset($document->cdo_valor_sin_impuestos_moneda_extranjera) ? floatval($document->cdo_valor_sin_impuestos_moneda_extranjera) : 0.0;
            $impuestos            = isset($document->cdo_impuestos_moneda_extranjera) ? floatval($document->cdo_impuestos_moneda_extranjera) : 0.0;
            $cargos               = isset($document->cdo_cargos_moneda_extranjera) ? floatval($document->cdo_cargos_moneda_extranjera): 0.0;
            $descuentos           = isset($document->cdo_descuentos_moneda_extranjera) ? floatval($document->cdo_descuentos_moneda_extranjera) : 0.0;
            $retencionesSugeridas = isset($document->cdo_retenciones_sugeridas_moneda_extranjera) ? floatval($document->cdo_retenciones_sugeridas_moneda_extranjera) : 0.0;
            $anticipos            = isset($document->cdo_anticipo_moneda_extranjera) ? floatval($document->cdo_anticipo_moneda_extranjera) : 0.0;
            $redondeo             = isset($document->cdo_redondeo_moneda_extranjera) ? floatval($document->cdo_redondeo_moneda_extranjera) : 0.0;

            $maxDecimales = $this->maxDecimales([$valorSinImpuestos, $impuestos, $cargos, $descuentos, $retencionesSugeridas, $redondeo]);
            $cdo_valor_a_pagar_moneda_extranjera = $this->redondeo(round($valorSinImpuestos + $impuestos + $cargos - $descuentos - $retencionesSugeridas + ($redondeo), $maxDecimales), $maxDecimales);

            if($cdo_valor_a_pagar_moneda_extranjera < 0) {
                $errores[] = "Valor a pagar en moneda extranjera negativo. Verifique valores de descuentos y/o retenciones sugeridas";
            }

            if($cdo_valor_a_pagar_moneda_extranjera < $anticipos) {
                $errores[] = "El valor total del anticipo en moneda extranjera no puede ser mayor al valor total a pagar en moneda extranjera";
            }
        }

        return [
            'errores' => $errores,
            'cdo_valor_a_pagar' => $cdo_valor_a_pagar,
            'cdo_valor_a_pagar_moneda_extranjera' => $cdo_valor_a_pagar_moneda_extranjera
        ];
    }

    /**
     * Construye un mensaje de error en caso de que no se pueda descargar el archivo.
     *
     * @param string $messsage Mensaje del error generado
     * @param array $errores Array de errores
     * @return JsonResponse
     */
    private function generateErrorResponse(string $messsage, array $errores = []) {
        // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar
        // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
        $headers = [
            header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
            header('X-Error-Status: 404'),
            header("X-Error-Message: {$messsage}")
        ];
        return response()->json([
            'message' => 'Error en la Petición',
            'errors' => !empty($errores) ? $errores : [$messsage]
        ], 422, $headers);
    }

    /**
     * Retorna el PDF genérico similar al formado de la DIAN para recepción, en base a un documento json.
     *
     * @param Request $request Parámetros de la petición
     * @return array|Response
     */
    public function recepcionGenerarRg(Request $request) {
        $user = auth()->user();

        // Verifica si el request proviene de un proceso interno en el mismo microservicio (llamado a este método desde otra clase en el microservicio)
        if($request->filled('proceso_interno') && $request->filled('json') && $request->proceso_interno === true) {
            $json = json_decode($request->json);
            $json = $json->documentos;
        } else {
            if (!$request->has('documentos')) {
                return $this->generateErrorResponse('La petici&oacute;n esta mal formada, debe especificarse un tipo y objeto JSON');
            }

            $json = json_decode(json_encode($request->documentos));
        }

        if (!isset($json->NC) && !isset($json->ND) && !isset($json->FC) && !isset($json->DS) && !isset($json->DS_NC)) {
            return $this->generateErrorResponse('La petici&oacute;n esta mal formada, no existe la propiedad asociada a FC, NC, ND, DS o DS_NC');
        } elseif(isset($json->FC) && !empty($json->FC)) {
            $cdoClasificacion = 'FC';
            $documento = $json->FC[0];
        } elseif(isset($json->ND) && !empty($json->ND)) {
            $cdoClasificacion = 'ND';
            $documento = $json->ND[0];
        } elseif(isset($json->NC) && !empty($json->NC)) {
            $cdoClasificacion = 'NC';
            $documento = $json->NC[0];
        } elseif(isset($json->DS) && !empty($json->DS)) {
            $cdoClasificacion = 'DS';
            $documento = $json->DS[0];
        } elseif(isset($json->DS_NC) && !empty($json->DS_NC)) {
            $cdoClasificacion = 'DS_NC';
            $documento = $json->DS_NC[0];
        }

        $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\etl_generica\\rgRepGenerica\\RgGENERICA_1';
        if(!class_exists($clase)) {
            return $this->generateErrorResponse('No se logr&oacute; establecer la clase asociada a la representaci&oacute;n gr&aacute;fica del documento electr&oacute;nico');
        }

        // Clase que contiene el método que permite realizar búsquedas en el array de paramétricas
        $metodosBase = new MetodosBase();
        $parametricas = $this->obtenerParametricas();

        // Tipo de documento electrónico
        $documento->tde_descripcion = '';
        if(isset($documento->tde_codigo) && !empty($documento->tde_codigo))
            $documento->tde_descripcion = $metodosBase->obtieneDatoParametrico($parametricas['tiposDocumentoElectronico'], 'tde_codigo', $documento->tde_codigo, 'tde_descripcion');
        // Tipo de operación
        $documento->top_descripcion = '';
        if(isset($documento->top_codigo) && !empty($documento->top_codigo))
            $documento->top_descripcion = $metodosBase->obtieneDatoParametrico($parametricas['tiposOperacion'], 'top_codigo', $documento->top_codigo, 'top_descripcion');

        // Información proveedor
        $proveedor       = null;
        $dataProveedor   = [];
        $proveedorNombre = '';
        $paramProveedor  = isset($documento->proveedor) && !empty($documento->proveedor);
        if($paramProveedor && isset($documento->pro_identificacion) && !empty($documento->pro_identificacion)) {
            foreach ($documento->proveedor as $registro) {
                if($registro->pro_identificacion === $documento->pro_identificacion) {
                    $proveedor = $registro;
                    break;
                }
            }
        }
        if($paramProveedor) {
            // Proveedor - Nombre
            if (isset($proveedor->pro_razon_social) && !empty($proveedor->pro_razon_social))
                $proveedorNombre = $proveedor->pro_razon_social;
            else
                $proveedorNombre = $proveedor->pro_primer_nombre . ' ' . $proveedor->pro_otros_nombres . ' ' . $proveedor->pro_primer_apellido . ' ' . $proveedor->pro_segundo_apellido;
            // Proveedor - Tipo de documento
            if(isset($proveedor->tdo_codigo) && !empty($proveedor->tdo_codigo)) {
                $dataProveedor['tdo_codigo']      = $proveedor->tdo_codigo;
                $dataProveedor['tdo_descripcion'] = $metodosBase->obtieneDatoParametrico($parametricas['tiposDocumento'], 'tdo_codigo', $proveedor->tdo_codigo, 'tdo_descripcion');
            } else {
                $dataProveedor['tdo_codigo']      = '';
                $dataProveedor['tdo_descripcion'] = '';
            }
            // Proveedor - Tipo de organización jurídica
            if(isset($proveedor->toj_codigo) && !empty($proveedor->toj_codigo)) {
                $dataProveedor['toj_codigo']      = $proveedor->toj_codigo;
                $dataProveedor['toj_descripcion'] = $metodosBase->obtieneDatoParametrico($parametricas['tiposOrganizacionJuridica'], 'toj_codigo', $proveedor->toj_codigo, 'toj_descripcion');
            } else {
                $dataProveedor['toj_codigo']      = '';
                $dataProveedor['toj_descripcion'] = '';
            }
            // Proveedor - Regimen fiscal
            if(isset($proveedor->rfi_codigo) && !empty($proveedor->rfi_codigo)) {
                $dataProveedor['rfi_codigo']      = $proveedor->rfi_codigo;
                $dataProveedor['rfi_descripcion'] = (strtolower($proveedor->rfi_codigo) !== 'no aplica') ? $metodosBase->obtieneDatoParametrico($parametricas['regimenFiscal'], 'rfi_codigo', $proveedor->rfi_codigo, 'rfi_descripcion') : $proveedor->rfi_codigo;
            } else {
                $dataProveedor['rfi_codigo']      = '';
                $dataProveedor['rfi_descripcion'] = '';
            }
            // Proveedor - Información
            $dataProveedor['pro_identificacion']               = isset($proveedor->pro_identificacion) && !empty($proveedor->pro_identificacion) ? $proveedor->pro_identificacion : '';
            $dataProveedor['pro_correo']                       = isset($proveedor->pro_correo) && !empty($proveedor->pro_correo) ? $proveedor->pro_correo : '';
            $dataProveedor['pro_direccion']                    = isset($proveedor->pro_direccion) && !empty($proveedor->pro_direccion) ? $proveedor->pro_direccion : '';
            $dataProveedor['pro_telefono']                     = isset($proveedor->pro_telefono) && !empty($proveedor->pro_telefono) ? $proveedor->pro_telefono : '';
            $dataProveedor['ref_codigo']                       = isset($proveedor->ref_codigo) && !empty($proveedor->ref_codigo) ? $proveedor->ref_codigo : [];

            // Proveedor - Información de origen
            $dataProveedor['pai_codigo']                       = isset($proveedor->pai_codigo) && !empty($proveedor->pai_codigo) ? $proveedor->pai_codigo : '';
            $dataProveedor['pai_descripcion']                  = isset($proveedor->pai_descripcion) && !empty($proveedor->pai_descripcion) ? $proveedor->pai_descripcion : '';
            $dataProveedor['dep_codigo']                       = isset($proveedor->dep_codigo) && !empty($proveedor->dep_codigo) ? $proveedor->dep_codigo : '';
            $dataProveedor['dep_descripcion']                  = isset($proveedor->dep_descripcion) && !empty($proveedor->dep_descripcion) ? $proveedor->dep_descripcion : '';
            $dataProveedor['mun_codigo']                       = isset($proveedor->mun_codigo) && !empty($proveedor->mun_codigo) ? $proveedor->mun_codigo : '';
            $dataProveedor['mun_descripcion']                  = isset($proveedor->mun_descripcion) && !empty($proveedor->mun_descripcion) ? $proveedor->mun_descripcion : '';
            $dataProveedor['cpo_codigo']                       = isset($proveedor->cpo_codigo) && !empty($proveedor->cpo_codigo) ? $proveedor->cpo_codigo : '';
            $dataProveedor['pro_matricula_mercantil']          = isset($proveedor->pro_matricula_mercantil) && !empty($proveedor->pro_matricula_mercantil) ? $proveedor->pro_matricula_mercantil : '';
            $dataProveedor['pai_codigo_domicilio_fiscal']      = isset($proveedor->pai_codigo_domicilio_fiscal) && !empty($proveedor->pai_codigo_domicilio_fiscal) ? $proveedor->pai_codigo_domicilio_fiscal : '';
            $dataProveedor['pai_descripcion_domicilio_fiscal'] = isset($proveedor->pai_descripcion_domicilio_fiscal) && !empty($proveedor->pai_descripcion_domicilio_fiscal) ? $proveedor->pai_descripcion_domicilio_fiscal : '';
            $dataProveedor['dep_codigo_domicilio_fiscal']      = isset($proveedor->dep_codigo_domicilio_fiscal) && !empty($proveedor->dep_codigo_domicilio_fiscal) ? $proveedor->dep_codigo_domicilio_fiscal : '';
            $dataProveedor['dep_descripcion_domicilio_fiscal'] = isset($proveedor->dep_descripcion_domicilio_fiscal) && !empty($proveedor->dep_descripcion_domicilio_fiscal) ? $proveedor->dep_descripcion_domicilio_fiscal : '';
            $dataProveedor['mun_codigo_domicilio_fiscal']      = isset($proveedor->mun_codigo_domicilio_fiscal) && !empty($proveedor->mun_codigo_domicilio_fiscal) ? $proveedor->mun_codigo_domicilio_fiscal : '';
            $dataProveedor['mun_descripcion_domicilio_fiscal'] = isset($proveedor->mun_descripcion_domicilio_fiscal) && !empty($proveedor->mun_descripcion_domicilio_fiscal) ? $proveedor->mun_descripcion_domicilio_fiscal : '';
            $dataProveedor['cpo_codigo_domicilio_fiscal']      = isset($proveedor->cpo_codigo_domicilio_fiscal) && !empty($proveedor->cpo_codigo_domicilio_fiscal) ? $proveedor->cpo_codigo_domicilio_fiscal : '';
            $dataProveedor['pro_direccion_domicilio_fiscal']   = isset($proveedor->pro_direccion_domicilio_fiscal) && !empty($proveedor->pro_direccion_domicilio_fiscal) ? $proveedor->pro_direccion_domicilio_fiscal : '';
        }

        // Información oferente
        $oferente       = null;
        $dataOferente   = [];
        $oferenteNombre = '';
        $paramOferente  = isset($documento->oferente) && !empty($documento->oferente);
        if($paramOferente && isset($documento->ofe_identificacion) && !empty($documento->ofe_identificacion)) {
            foreach ($documento->oferente as $registro) {
                if($registro->ofe_identificacion === $documento->ofe_identificacion) {
                    $oferente = $registro;
                    break;
                }
            }
        }
        if($paramOferente) {
            // Oferente - Nombre
            if (isset($oferente->ofe_razon_social) && !empty($oferente->ofe_razon_social))
                $oferenteNombre = $oferente->ofe_razon_social;
            else
                $oferenteNombre = $oferente->ofe_primer_nombre . ' ' . $oferente->ofe_otros_nombres . ' ' . $oferente->ofe_primer_apellido . ' ' . $oferente->ofe_segundo_apellido;
            // Oferente - Tipo de documento
            if(isset($oferente->tdo_codigo) && !empty($oferente->tdo_codigo)) {
                $dataOferente['tdo_codigo']      = $oferente->tdo_codigo;
                $dataOferente['tdo_descripcion'] = $metodosBase->obtieneDatoParametrico($parametricas['tiposDocumento'], 'tdo_codigo', $oferente->tdo_codigo, 'tdo_descripcion');
            } else {
                $dataOferente['tdo_codigo']      = '';
                $dataOferente['tdo_descripcion'] = '';
            }
            // Oferente - Tipo de organización jurídica
            if(isset($oferente->toj_codigo) && !empty($oferente->toj_codigo)) {
                $dataOferente['toj_codigo']      = $oferente->toj_codigo;
                $dataOferente['toj_descripcion'] = $metodosBase->obtieneDatoParametrico($parametricas['tiposOrganizacionJuridica'], 'toj_codigo', $oferente->toj_codigo, 'toj_descripcion');
            } else {
                $dataOferente['toj_codigo']      = '';
                $dataOferente['toj_descripcion'] = '';
            }
            // Oferente - Regimen fiscal
            if(isset($oferente->rfi_codigo) && !empty($oferente->rfi_codigo)) {
                $dataOferente['rfi_codigo']      = $oferente->rfi_codigo;
                $dataOferente['rfi_descripcion'] = (strtolower($oferente->rfi_codigo) !== 'no aplica') ? $metodosBase->obtieneDatoParametrico($parametricas['regimenFiscal'], 'rfi_codigo', $oferente->rfi_codigo, 'rfi_descripcion') : $oferente->rfi_codigo;
            } else {
                $dataOferente['rfi_codigo']      = '';
                $dataOferente['rfi_descripcion'] = '';
            }
            // Oferente - Información
            $dataOferente['ofe_identificacion']               = isset($oferente->ofe_identificacion) && !empty($oferente->ofe_identificacion) ? $oferente->ofe_identificacion : '';
            $dataOferente['ofe_correo']                       = isset($oferente->ofe_correo) && !empty($oferente->ofe_correo) ? $oferente->ofe_correo : '';
            $dataOferente['ofe_direccion']                    = isset($oferente->ofe_direccion) && !empty($oferente->ofe_direccion) ? $oferente->ofe_direccion : '';
            $dataOferente['ofe_telefono']                     = isset($oferente->ofe_telefono) && !empty($oferente->ofe_telefono) ? $oferente->ofe_telefono : '';
            $dataOferente['ref_codigo']                       = isset($oferente->ref_codigo) && !empty($oferente->ref_codigo) ? $oferente->ref_codigo : [];
            $dataOferente['ofe_actividad_economica']          = isset($oferente->ofe_actividad_economica) && !empty($oferente->ofe_actividad_economica) ? $oferente->ofe_actividad_economica : '';
            $dataOferente['ofe_matricula_mercantil']          = isset($oferente->ofe_matricula_mercantil) && !empty($oferente->ofe_matricula_mercantil) ? $oferente->ofe_matricula_mercantil : '';

            // Oferente - Información de origen
            $dataOferente['pai_codigo']                       = isset($oferente->pai_codigo) && !empty($oferente->pai_codigo) ? $oferente->pai_codigo : '';
            $dataOferente['pai_descripcion']                  = isset($oferente->pai_descripcion) && !empty($oferente->pai_descripcion) ? $oferente->pai_descripcion : '';
            $dataOferente['dep_codigo']                       = isset($oferente->dep_codigo) && !empty($oferente->dep_codigo) ? $oferente->dep_codigo : '';
            $dataOferente['dep_descripcion']                  = isset($oferente->dep_descripcion) && !empty($oferente->dep_descripcion) ? $oferente->dep_descripcion : '';
            $dataOferente['mun_codigo']                       = isset($oferente->mun_codigo) && !empty($oferente->mun_codigo) ? $oferente->mun_codigo : '';
            $dataOferente['mun_descripcion']                  = isset($oferente->mun_descripcion) && !empty($oferente->mun_descripcion) ? $oferente->mun_descripcion : '';
            $dataOferente['cpo_codigo']                       = isset($oferente->cpo_codigo) && !empty($oferente->cpo_codigo) ? $oferente->cpo_codigo : '';
            $dataOferente['pai_codigo_domicilio_fiscal']      = isset($oferente->pai_codigo_domicilio_fiscal) && !empty($oferente->pai_codigo_domicilio_fiscal) ? $oferente->pai_codigo_domicilio_fiscal : '';
            $dataOferente['pai_descripcion_domicilio_fiscal'] = isset($oferente->pai_descripcion_domicilio_fiscal) && !empty($oferente->pai_descripcion_domicilio_fiscal) ? $oferente->pai_descripcion_domicilio_fiscal : '';
            $dataOferente['dep_codigo_domicilio_fiscal']      = isset($oferente->dep_codigo_domicilio_fiscal) && !empty($oferente->dep_codigo_domicilio_fiscal) ? $oferente->dep_codigo_domicilio_fiscal : '';
            $dataOferente['dep_descripcion_domicilio_fiscal'] = isset($oferente->dep_descripcion_domicilio_fiscal) && !empty($oferente->dep_descripcion_domicilio_fiscal) ? $oferente->dep_descripcion_domicilio_fiscal : '';
            $dataOferente['mun_codigo_domicilio_fiscal']      = isset($oferente->mun_codigo_domicilio_fiscal) && !empty($oferente->mun_codigo_domicilio_fiscal) ? $oferente->mun_codigo_domicilio_fiscal : '';
            $dataOferente['mun_descripcion_domicilio_fiscal'] = isset($oferente->mun_descripcion_domicilio_fiscal) && !empty($oferente->mun_descripcion_domicilio_fiscal) ? $oferente->mun_descripcion_domicilio_fiscal : '';
            $dataOferente['cpo_codigo_domicilio_fiscal']      = isset($oferente->cpo_codigo_domicilio_fiscal) && !empty($oferente->cpo_codigo_domicilio_fiscal) ? $oferente->cpo_codigo_domicilio_fiscal : '';
            $dataOferente['ofe_direccion_domicilio_fiscal']   = isset($oferente->ofe_direccion_domicilio_fiscal) && !empty($oferente->ofe_direccion_domicilio_fiscal) ? $oferente->ofe_direccion_domicilio_fiscal : '';
        }

        // Información de Pagos
        $mediosPagosDocumento = [];
        if (isset($documento->cdo_medios_pago) && !empty($documento->cdo_medios_pago)) {
            foreach($documento->cdo_medios_pago as $medioFormaPago) {
                $descMedioPago = $metodosBase->obtieneDatoParametrico($parametricas['mediosPago'], 'mpa_codigo', $medioFormaPago->mpa_codigo, 'mpa_descripcion');
                $descFormaPago = $metodosBase->obtieneDatoParametrico($parametricas['formasPago'], 'fpa_codigo', $medioFormaPago->fpa_codigo, 'fpa_descripcion');

                $mediosPagosDocumento[] = [
                    'medio' => $descMedioPago != '' ? [
                        'mpa_codigo'      => $medioFormaPago->mpa_codigo,
                        'mpa_descripcion' => $descMedioPago
                    ] : [],
                    'forma' => $descFormaPago != '' ? [
                        'fpa_codigo'      => $medioFormaPago->fpa_codigo,
                        'fpa_descripcion' => $descFormaPago
                    ] : [],
                    'identificador' => isset($medioFormaPago->men_identificador_pago) ? $medioFormaPago->men_identificador_pago : []
                ];
            }
        }

        // Información detalle cargo, descuentos y retenciones sugeridas
        $contadorCargosDescuentosRetenciones = 1;
        $detalleCargosDescuentosRetenciones = [];
        if(isset($documento->cdo_detalle_cargos) && !empty($documento->cdo_detalle_cargos)) {
            foreach ($documento->cdo_detalle_cargos as $cargo) {
                $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $cargo, 'CABECERA', 'CARGO', 'true', (isset($cargo->nombre) ? $cargo->nombre : null), null);
            }
        }
        if(isset($documento->cdo_detalle_descuentos) && !empty($documento->cdo_detalle_descuentos)) {
            foreach ($documento->cdo_detalle_descuentos as $descuento) {
                $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $descuento, 'CABECERA', 'DESCUENTO', 'false', (isset($descuento->nombre) ? $descuento->nombre : null), $metodosBase->obtieneDatoParametrico($parametricas['codigosDescuento'], 'cde_codigo', $descuento->cde_codigo, 'cde_id'));
            }
        }
        if(isset($documento->cdo_detalle_retenciones_sugeridas) && !empty($documento->cdo_detalle_retenciones_sugeridas)) {
            foreach ($documento->cdo_detalle_retenciones_sugeridas as $retencionSugerida) {
                $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $retencionSugerida, 'CABECERA', $retencionSugerida->tipo, 'false', null, null);
            }
        }

        $anticiposDocumento = [];
        if(isset($documento->cdo_detalle_anticipos) && !empty($documento->cdo_detalle_anticipos))
            $anticiposDocumento = collect($documento->cdo_detalle_anticipos);

        // Información de Items
        $items = [];
        if (isset($documento->items) && !empty($documento->items)) {
            foreach($documento->items as $item) {
                if(isset($item->und_codigo) && !empty($item->und_codigo))
                    $item->und_id = $metodosBase->obtieneDatoParametrico($parametricas['unidades'], 'und_codigo', $item->und_codigo, 'und_id');
                // Cargos a nivel de ítem
                if(isset($item->ddo_cargos) && !empty($item->ddo_cargos)) {
                    foreach ($item->ddo_cargos as $cargo) {
                        $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $cargo, 'DETALLE', 'CARGO', 'true', (isset($cargo->nombre) ? $cargo->nombre : null), null, (isset($item->ddo_id) ? $item->ddo_id : null));
                    }
                }
                // Descuentos a nivel de ítem
                if(isset($item->ddo_descuentos) && !empty($item->ddo_descuentos)) {
                    foreach ($item->ddo_descuentos as $descuento) {
                        $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $descuento, 'DETALLE', 'DESCUENTO', 'false', (isset($descuento->nombre) ? $descuento->nombre : null), null, (isset($item->ddo_id) ? $item->ddo_id : null));
                    }
                }

                // Retenciones a nivel de ítem
                if(isset($item->ddo_detalle_retenciones_sugeridas) && !empty($item->ddo_detalle_retenciones_sugeridas)) {
                    foreach ($item->ddo_detalle_retenciones_sugeridas as $retencion) {
                        $detalleCargosDescuentosRetenciones[] = $this->crearObjetoCargosDescuentosRetenciones($contadorCargosDescuentosRetenciones, $retencion, 'DETALLE', $retencion->tipo, 'false', null, null);
                    }
                }

                $items[] = (array)$item;
            }
        }

        // Información de tributos
        $impuestosItems     = [];
        $impuestosDocumento = [];
        $porcentajeIva      = 0;
        $codigoIva          = $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_nombre', 'IVA', 'tri_codigo');
        if (isset($documento->tributos) && !empty($documento->tributos)) {
            foreach($documento->tributos as $tributo) {
                $triId   = $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_codigo', $tributo->tri_codigo, 'tri_id');
                $triTipo = $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_codigo', $tributo->tri_codigo, 'tri_tipo');

                $impuesto = [
                    'tri_id'                                  => $triId,
                    'iid_tipo'                                => $triTipo,
                    'iid_tipo'                                => $triTipo,
                    'ddo_secuencia'                           => $tributo->ddo_secuencia,
                    'tri_codigo'                              => $tributo->tri_codigo,
                    'iid_nombre_figura_tributaria'            => $tributo->iid_nombre_figura_tributaria ? $tributo->iid_nombre_figura_tributaria : null,
                    'iid_base'                                => isset($tributo->iid_porcentaje) && isset($tributo->iid_porcentaje->iid_base) ? $tributo->iid_porcentaje->iid_base : null,
                    'iid_base_moneda_extranjera'              => isset($tributo->iid_porcentaje) && isset($tributo->iid_porcentaje->iid_base_moneda_extranjera) ? $tributo->iid_porcentaje->iid_base_moneda_extranjera : null,
                    'iid_porcentaje'                          => isset($tributo->iid_porcentaje) && isset($tributo->iid_porcentaje->iid_porcentaje) ? $tributo->iid_porcentaje->iid_porcentaje : null,
                    'iid_cantidad'                            => isset($tributo->iid_unidad) && isset($tributo->iid_unidad->iid_cantidad) ? $tributo->iid_unidad->iid_cantidad : null,
                    'iid_valor_unitario'                      => isset($tributo->iid_unidad) && isset($tributo->iid_unidad->iid_valor_unitario) ? $tributo->iid_unidad->iid_valor_unitario : null,
                    'iid_valor_unitario_moneda_extranjera'    => isset($tributo->iid_unidad) && isset($tributo->iid_unidad->iid_valor_unitario_moneda_extranjera) ? $tributo->iid_unidad->iid_valor_unitario_moneda_extranjera : null,
                    'iid_valor'                               => $tributo->iid_valor ? $tributo->iid_valor : null,
                    'iid_valor_moneda_extranjera'             => $tributo->iid_valor_moneda_extranjera ? $tributo->iid_valor_moneda_extranjera : null,
                    'iid_motivo_exencion'                     => $tributo->iid_motivo_exencion ? $tributo->iid_motivo_exencion : null,
                ];
                $impuestosItems[]     = $impuesto;
                $impuestosDocumento[] = json_decode(json_encode($impuesto));

                if ($tributo->tri_codigo == $codigoIva && isset($tributo->iid_porcentaje)) {
                    if($tributo->iid_porcentaje->iid_porcentaje > 0 && $porcentajeIva == 0)
                        $porcentajeIva = number_format($tributo->iid_porcentaje->iid_porcentaje, 2, '.', '');
                }
            }
        }

        // Información complementaria extensión
        $arrComplementariaExtension = [];
        if(isset($documento->cdo_interoperabilidad) && !empty($documento->cdo_interoperabilidad)) {
            foreach ($documento->cdo_interoperabilidad as $registro) {
                if(isset($registro->interoperabilidad) && !empty($registro->interoperabilidad) && isset($registro->interoperabilidad->collection) && !empty($registro->interoperabilidad->collection)) {
                    foreach ($registro->interoperabilidad->collection as $collect) {
                        if(isset($collect->informacion_adicional) && !empty($collect->informacion_adicional)) {
                            foreach ($collect->informacion_adicional as $informacion) {
                                $arrComplementariaExtension[] = $informacion;
                            }
                        }
                    }
                }
            }
        }

        // Referencias del documento
        $arrReferenciasDocumento = [];
        if(isset($documento->cdo_documento_referencia) && !empty($documento->cdo_documento_referencia)) {
            foreach ($documento->cdo_documento_referencia as $referencia) {
                $arrReferenciasDocumento[] = [
                    'tipo_documento' => isset($referencia->codigo_tipo_documento) ? $referencia->codigo_tipo_documento : '',
                    'consecutivo'    => isset($referencia->consecutivo) ? $referencia->consecutivo : '',
                    'fecha_emision'  => isset($referencia->fecha_emision) ? $referencia->fecha_emision : ''
                ];
            }
        }

        // Documento adicional
        if(isset($documento->cdo_documento_adicional) && !empty($documento->cdo_documento_adicional)) {
            foreach ($documento->cdo_documento_adicional as $referencia) {
                $arrReferenciasDocumento[] = [
                    'tipo_documento' => isset($referencia->tipo_documento) ? $referencia->tipo_documento : '',
                    'consecutivo'    => isset($referencia->consecutivo)    ? $referencia->consecutivo    : '',
                    'fecha_emision'  => isset($referencia->fecha_emision)  ? $referencia->fecha_emision  : ''
                ];
            }
        }

        // Información que será usada en la representación gráfica
        $datos = [
            'cdo_tipo'                                => $cdoClasificacion,
            'tde_codigo'                              => isset($documento->tde_codigo) && !empty($documento->tde_codigo) ? $documento->tde_codigo : '',
            'tde_descripcion'                         => isset($documento->tde_codigo) && !empty($documento->tde_codigo) ? $documento->tde_descripcion : '',
            'top_codigo'                              => isset($documento->top_codigo) && !empty($documento->top_codigo) ? $documento->top_codigo : '',
            'top_descripcion'                         => isset($documento->top_codigo) && !empty($documento->top_codigo) ? $documento->top_descripcion : '',
            'pro_identificacion'                      => isset($documento->pro_identificacion) && !empty($documento->pro_identificacion) ? $documento->pro_identificacion : '',
            'pro_razon_social'                        => $proveedorNombre,
            'pro_nombre_comercial'                    => ($paramProveedor && isset($proveedor->pro_nombre_comercial) && !empty($proveedor->pro_nombre_comercial)) ? $proveedor->pro_nombre_comercial : $proveedorNombre,
            'proveedor'                               => $dataProveedor,
            'ofe_identificacion'                      => isset($documento->ofe_identificacion) && !empty($documento->ofe_identificacion) ? $documento->ofe_identificacion : '',
            'ofe_razon_social'                        => $oferenteNombre,
            'ofe_nombre_comercial'                    => ($paramOferente && isset($oferente->ofe_nombre_comercial) && !empty($oferente->ofe_nombre_comercial)) ? $oferente->ofe_nombre_comercial : $oferenteNombre,
            'oferente'                                => $dataOferente,
            'rfa_resolucion'                          => isset($documento->rfa_resolucion) && !empty($documento->rfa_resolucion) ? $documento->rfa_resolucion : '', 
            'rfa_prefijo'                             => isset($documento->rfa_prefijo) && !empty($documento->rfa_prefijo) ? $documento->rfa_prefijo : '', 
            'rfa_vigencia_desde'                      => isset($documento->rfa_vigencia_desde) && !empty($documento->rfa_vigencia_desde) ? $documento->rfa_vigencia_desde : '', 
            'rfa_vigencia_hasta'                      => isset($documento->rfa_vigencia_hasta) && !empty($documento->rfa_vigencia_hasta) ? $documento->rfa_vigencia_hasta : '', 
            'rfa_rango_desde'                         => isset($documento->rfa_rango_desde) && !empty($documento->rfa_rango_desde) ? $documento->rfa_rango_desde : '', 
            'rfa_rango_hasta'                         => isset($documento->rfa_rango_hasta) && !empty($documento->rfa_rango_hasta) ? $documento->rfa_rango_hasta : '', 
            'cdo_consecutivo'                         => isset($documento->cdo_consecutivo) && !empty($documento->cdo_consecutivo) ? $documento->cdo_consecutivo : '',
            'cdo_fecha'                               => isset($documento->cdo_fecha) && !empty($documento->cdo_fecha) ? $documento->cdo_fecha : '',
            'cdo_hora'                                => isset($documento->cdo_hora) && !empty($documento->cdo_hora) ? $documento->cdo_hora : '',
            'fecha_vencimiento'                       => isset($documento->cdo_vencimiento) && !empty($documento->cdo_vencimiento) ? $documento->cdo_vencimiento : '',
            'observacion'                             => isset($documento->cdo_observacion) && !empty($documento->cdo_observacion) ? json_encode($documento->cdo_observacion) : json_encode([]),
            'cufe'                                    => isset($documento->cdo_cufe) && !empty($documento->cdo_cufe) ? $documento->cdo_cufe : '',
            'qr'                                      => isset($documento->cdo_qr) && !empty($documento->cdo_qr) ? $documento->cdo_qr : '',
            'signaturevalue'                          => isset($documento->cdo_signaturevalue) && !empty($documento->cdo_signaturevalue) ? $documento->cdo_signaturevalue : '',
            'cdo_moneda'                              => isset($documento->mon_codigo) && !empty($documento->mon_codigo) ? $documento->mon_codigo : '',
            'cdo_moneda_extranjera'                   => isset($documento->mon_codigo_extranjera) && !empty($documento->mon_codigo_extranjera) ? $documento->mon_codigo_extranjera : '',
            'cdo_trm'                                 => isset($documento->cdo_trm) && !empty($documento->cdo_trm) ? $documento->cdo_trm : '',
            'cdo_trm_fecha'                           => isset($documento->cdo_trm_fecha) && !empty($documento->cdo_trm_fecha) ? $documento->cdo_trm_fecha : '',
            'cdo_conceptos_correccion'                => isset($documento->cdo_conceptos_correccion) && !empty($documento->cdo_conceptos_correccion) ? $documento->cdo_conceptos_correccion : [],
            'cdo_documento_referencia'                => isset($documento->cdo_documento_referencia) && !empty($documento->cdo_documento_referencia) ? $documento->cdo_documento_referencia : [],
            'subtotal'                                => isset($documento->cdo_valor_sin_impuestos) && !empty($documento->cdo_valor_sin_impuestos) ? $documento->cdo_valor_sin_impuestos : '',
            'iva'                                     => isset($documento->cdo_impuestos) && !empty($documento->cdo_impuestos) ? $documento->cdo_impuestos : '',
            'total'                                   => isset($documento->cdo_total) && !empty($documento->cdo_total) ? $documento->cdo_total : '',
            'cargos'                                  => isset($documento->cdo_cargos) && !empty($documento->cdo_cargos) ? $documento->cdo_cargos : '',
            'descuentos'                              => isset($documento->cdo_descuentos) && !empty($documento->cdo_descuentos) ? $documento->cdo_descuentos : '',
            'anticipos'                               => isset($documento->cdo_anticipo) && !empty($documento->cdo_anticipo) ? $documento->cdo_anticipo : '',
            'retenciones'                             => isset($documento->retenciones) && !empty($documento->retenciones) ? $documento->retenciones : [],
            'total_retenciones'                       => isset($documento->cdo_retenciones) && !empty($documento->cdo_retenciones) ? $documento->cdo_retenciones : '',
            'valor_a_pagar'                           => isset($documento->cdo_valor_a_pagar) && !empty($documento->cdo_valor_a_pagar) ? $documento->cdo_valor_a_pagar : '',
            'detalle_cargos_descuentos'               => $detalleCargosDescuentosRetenciones,
            'items'                                   => $items,
            'impuestos_items'                         => $impuestosItems,
            'tri_id_iva'                              => $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_codigo', '01', 'tri_id'),
            'tri_id_inc'                              => $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_codigo', '04', 'tri_id'),
            'tri_id_bolsa'                            => $metodosBase->obtieneDatoParametrico($parametricas['tributos'], 'tri_codigo', '22', 'tri_id'),
            'medios_pagos_documento'                  => $mediosPagosDocumento,
            'impuestos_documento'                     => $impuestosDocumento,
            'anticipos_documento'                     => $anticiposDocumento,
            'complementaria_extension'                => $arrComplementariaExtension,
            'referencias_documento'                   => $arrReferenciasDocumento,
            'porcentaje_iva'                          => $porcentajeIva,
            'orden_compra'                            => isset($documento->dad_orden_referencia->referencia) && !empty($documento->dad_orden_referencia->referencia) ? $documento->dad_orden_referencia->referencia : '',
            'fecha_compra'                            => isset($documento->dad_orden_referencia->fecha_emision_referencia) && !empty($documento->dad_orden_referencia->fecha_emision_referencia) ? $documento->dad_orden_referencia->fecha_emision_referencia : '',
            'fecha_creacion'                          => date('Y-m-d H:i:s'),
            'nit_pt'                                  => config('variables_sistema.NIT_PT'),
            'razon_social_pt'                         => config('variables_sistema.RAZON_SOCIAL_PT')
        ];

        DoTrait::setFilesystemsInfo();

        $datos = [
            'clase' => $clase,
            'datos' => $datos
        ];

        $representacionGrafica = new $datos['clase']($datos['datos']);
        if ($representacionGrafica === null || $representacionGrafica === false)
            return $this->generateErrorResponse('No fue posible generar la representaci&oacute;n gr&aacute;fica del documento electr&oacute;nico');

        $rg = $representacionGrafica->getPdf();
        if(is_array($rg) && !$rg['error']) {
            if($request->filled('proceso_interno') && $request->filled('json') && $request->proceso_interno === true) {
                return [
                    'pdf' => $rg['pdf']
                ];
            } else {
                $nombreRg = $cdoClasificacion . $documento->rfa_prefijo . $documento->cdo_consecutivo . '.pdf';
                Storage::disk(config('variables_sistema.ETL_DESCARGAS_STORAGE'))
                    ->put($nombreRg, $rg['pdf']);
    
                $headers = [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ];
    
                return response()
                    ->download(storage_path('etl/descargas/' . $nombreRg), $nombreRg, $headers)
                    ->deleteFileAfterSend(true);
            }
        } else {
            if($request->filled('proceso_interno') && $request->filled('json') && $request->proceso_interno === true)
                return [
                    'errors' => $rg['error']
                ];
            else
                return $this->generateErrorResponse('No fue posible generar la Representaci&oacute;n Gr&aacute;fica');
        }
    }

    /**
     * Permite generar el PDF de la representación gráfica de los eventos DIAN de recepción.
     *
     * @param string $ofeIdentificacion Identificación del Oferente
     * @param string $baseDatos Base de datos
     * @param string $applicationResponse Application Response del Evento
     * @param array  $motivoRechazo Información del motivo de rechazo
     * @return array Array con la representación gráfica o los errores 
     */
    public function generarRgEventoDian(string $ofeIdentificacion, string $baseDatos, string $applicationResponse, string $estadoEvento, array $motivoRechazo) {
        $oferente = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_recepcion_correo_estandar'])
            ->where('ofe_identificacion', $ofeIdentificacion)
            ->first();

        // Clase que permite generar la RG de la notificación del evento
        if ($oferente->ofe_recepcion_correo_estandar === 'SI')
            $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Eventos\\etl_generica\\rgGenerica\\RgGENERICA';
        else 
            $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Eventos\\' . $baseDatos . '\\rg' . $ofeIdentificacion . '\\Rg' . $ofeIdentificacion;

        if(!class_exists($clase))
            return [
                'respuesta' => 'La clase del OFE para la notificación del evento no existe [' . $clase . ']',
                'error'     => true
            ];

        $applicationResponse = $applicationResponse;
        $applicationResponse = str_replace('xmlns=', 'ns=', $applicationResponse);
        $applicationResponse = new \SimpleXMLElement($applicationResponse, LIBXML_NOERROR);
        $metodosBase         = new MetodosBase();

        switch($estadoEvento) {
            case 'NOTACUSERECIBO':
                $evento       = 'acuse';
                $tituloEvento = 'Acuse de recibo de la Factura Electrónica de Venta';
                break;
            case 'NOTRECIBOBIEN':
                $evento       = 'recibo';
                $tituloEvento = 'Recibo del bien o prestación del servicio';
                break;
            case 'NOTACEPTACION':
                $evento       = 'aceptacion';
                $tituloEvento = 'Aceptación expresa de la Factura Electrónica de Venta';
                break;
            case 'NOTRECHAZO':
                $evento       = 'rechazo';
                $tituloEvento = 'Reclamo de la Factura Electrónica de Venta';
                break;
        }

        // Información que será usada en la representación gráfica
        $datos = [
            'cude'                    => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cbc:UUID'),
            'numero_evento'           => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cbc:ID'),
            'fecha_generacion'        => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cbc:IssueDate'),
            'hora_generacion'         => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cbc:IssueTime'),
            'emisor_razon_social'     => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cac:SenderParty/cac:PartyTaxScheme/cbc:RegistrationName'),
            'emisor_identificacion'   => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cac:SenderParty/cac:PartyTaxScheme/cbc:CompanyID'),
            'receptor_razon_social'   => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cac:ReceiverParty/cac:PartyTaxScheme/cbc:RegistrationName'),
            'receptor_identificacion' => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cac:ReceiverParty/cac:PartyTaxScheme/cbc:CompanyID'),
            'numero_factura'          => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cac:DocumentResponse/cac:DocumentReference/cbc:ID'),
            'cufe_factura'            => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/cac:DocumentResponse/cac:DocumentReference/cbc:UUID'),
            'codigo_qr'               => (string) $metodosBase->getValueByXpath($applicationResponse, '//ApplicationResponse/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/sts:DianExtensions/sts:QRCode'),
            'tipo_evento'             => $evento,
            'titulo_evento'           => $tituloEvento,
            'est_motivo_rechazo'      => !empty($motivoRechazo) ? $motivoRechazo : []
        ];

        DoTrait::setFilesystemsInfo();

        $datos = [
            'clase'              => $clase,
            'ofe_identificacion' => $ofeIdentificacion,
            'baseDeDatos'        => $baseDatos,
            'datos'              => $datos
        ];

        $representacionGrafica = new $datos['clase']($datos['ofe_identificacion'], $datos['baseDeDatos'], $datos['datos']);
        if ($representacionGrafica === null || $representacionGrafica === false)
            return $this->generateErrorResponse('No fue posible generar la representaci&oacute;n gr&aacute;fica del documento electr&oacute;nico');

        $respuestaRg = $representacionGrafica->getPdf();
        if(is_array($respuestaRg) && !$respuestaRg['error']) {
            return [
                'respuesta' => $respuestaRg['pdf'],
                'error'     => false
            ];
        } else {
            return [
                'respuesta' => 'No fue posible generar la Representación Gráfica del evento',
                'error'     => true
            ];
        }
    }
}