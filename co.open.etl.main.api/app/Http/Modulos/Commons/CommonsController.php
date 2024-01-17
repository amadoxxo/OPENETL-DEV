<?php

namespace App\Http\Modulos\Commons;

use Carbon\Carbon;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use openEtl\Tenant\Traits\TenantTrait;
use openEtl\Main\Traits\FechaVigenciaValidations;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPago;
use App\Http\Modulos\Parametros\Radian\Roles\ParametrosRadianRol;
use App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActor;
use App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscal;
use App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcm;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuento;
use App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargo;
use App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccion;
use App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedor;
use App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacita;
use App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuento;
use App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContrato;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;
use App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajador;
use App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscal;
use App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridica;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmision;
use App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajador;
use App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologico;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto\CentroCosto;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacion;

class CommonsController extends Controller {
    use FechaVigenciaValidations;

    /**
     * Constructor
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Devuelve el digito de verificaciòn dada la identificación.
     * 
     * @param Request $request
     * @return Response
     */
    public function getDigitoVerificacion(Request $request) {
        if ($request->has('identificacion')){
            $digitoVerificacion = TenantTrait::calcularDV($request->identificacion);
            return response()->json(["data" => $digitoVerificacion], 200);
        }

        return response()->json([
            'message' => 'Error al Obtener el Digito de Verificación',
            'errors' => ['No se Envio el Parametro Identificacion']
        ], 400);
    }

    /**
     * Retorna data que es utilizada en multiples partes en el cliente frontend de openETL.
     * 
     * @param Request $request
     * @return Response
     */
    public function getInitDataForBuild(Request $request) {
        $authUser = auth()->user();

        //Parametricas Comunes
        $spts = ConfiguracionSoftwareProveedorTecnologico::select(['sft_id', 'sft_identificador', 'sft_nombre']);
        if (isset($request->aplicaPara) && $request->aplicaPara == 'DN') {
            $spts = $spts->where('sft_aplica_para', 'LIKE', '%DN%');
        } else {
            // Si no se envia aplica para o este es diferente de DN 
            // se deben filtrar los registros donde el aplica para sea DE o NULL
            $spts = $spts->where(function($query) {
                $query->where('sft_aplica_para', 'LIKE', '%DE%')
                    ->orWhereNull('sft_aplica_para');
            });
        }
        $spts = $spts->where('estado', 'ACTIVO')
            ->orderBy('sft_nombre', 'asc')
            ->get();
        $temp = [];
        foreach ($spts as $spt) {
            $data = $spt->toArray();
            $data['sft_identificador_nombre'] = $data['sft_identificador'] . ' - ' . $data['sft_nombre'];
            $temp[] = $data;
        }
        $spts = $temp;

        $arrTipoDocumentos = [];
        $tipoDocumentos = ParametrosTipoDocumento::select(['tdo_id', 'tdo_codigo', 'tdo_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta',  DB::raw('CONCAT(tdo_codigo, " - ", tdo_descripcion) as tdo_codigo_descripion')]);
        if (isset($request->aplicaPara) && $request->aplicaPara != '')
            $tipoDocumentos = $tipoDocumentos->where('tdo_aplica_para', 'LIKE', '%' . $request->aplicaPara . '%');

        $tipoDocumentos = $tipoDocumentos->where('estado', 'ACTIVO')
            ->orderBy('tdo_descripcion', 'asc')
            ->get()
            ->groupBy('tdo_codigo')
            ->map(function ($item) use (&$arrTipoDocumentos) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrTipoDocumentos[] = $vigente['registro'];
                }
            });

        $arrFormaPago = [];
        $formasPago = ParametrosFormaPago::select(['fpa_id', 'fpa_codigo', 'fpa_descripcion', 'fpa_aplica_para', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('fpa_aplica_para', 'LIKE', '%' . $request->aplicaPara . '%')
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('fpa_codigo')
            ->map(function ($item) use (&$arrFormaPago) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrFormaPago[] = $vigente['registro'];
                }
            });
        
        if (isset($request->aplicaPara) && $request->aplicaPara == 'DN') {
            //Parametricas Documento Soporte Nomina Electronica
            $empleadores = ConfiguracionEmpleador::select(
                'emp_id',
                'emp_identificacion',
                'emp_razon_social',
                'emp_primer_apellido',
                'emp_segundo_apellido',
                'emp_primer_nombre',
                'emp_otros_nombres',
                \DB::raw('IF(emp_razon_social IS NULL OR emp_razon_social = "", CONCAT(COALESCE(emp_primer_nombre, ""), " ", COALESCE(emp_otros_nombres, ""), " ", COALESCE(emp_primer_apellido, ""), " ", COALESCE(emp_segundo_apellido, "")), emp_razon_social) as nombre_completo')
            )
                ->where('estado', 'ACTIVO');
            
            if(!empty($authUser->bdd_id_rg)) {
                $empleadores->where('bdd_id_rg', $authUser->bdd_id_rg);
            } else {
                $empleadores->whereNull('bdd_id_rg');
            }

            $empleadores = $empleadores->orderBy('emp_razon_social', 'asc')
                ->get();

            $arrTipoContrato = [];
            $tipoContrato = ParametrosTipoContrato::select(['ntc_id', 'ntc_codigo', 'ntc_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', DB::raw('CONCAT(ntc_codigo, " - ", ntc_descripcion) as ntc_codigo_descripion')])
                ->where('estado', 'ACTIVO')
                ->orderBy('ntc_descripcion', 'asc')
                ->get()
                ->groupBy('ntc_codigo')
                ->map(function ($item) use (&$arrTipoContrato) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrTipoContrato[] = $vigente['registro'];
                    }
                });

            $arrTipoTrabajador = [];
            $tipoTrabajador = ParametrosTipoTrabajador::select(['ntt_id', 'ntt_codigo', 'ntt_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', DB::raw('CONCAT(ntt_codigo, " - ", ntt_descripcion) as ntt_codigo_descripion')])
                ->where('estado', 'ACTIVO')
                ->orderBy('ntt_descripcion', 'asc')
                ->get()
                ->groupBy('ntt_codigo')
                ->map(function ($item) use (&$arrTipoTrabajador) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrTipoTrabajador[] = $vigente['registro'];
                    }
                });


            $arrSuntipoTrabajador = [];
            $tipoTrabajador = ParametrosSubtipoTrabajador::select(['nst_id', 'nst_codigo', 'nst_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', DB::raw('CONCAT(nst_codigo, " - ", nst_descripcion) as nst_codigo_descripion')])
                ->where('estado', 'ACTIVO')
                ->orderBy('nst_descripcion', 'asc')
                ->get()
                ->groupBy('nst_codigo')
                ->map(function ($item) use (&$arrSuntipoTrabajador) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrSuntipoTrabajador[] = $vigente['registro'];
                    }
                });

            $respuesta = [
                'spts'                       => $spts,
                'empleadores'                => $empleadores,
                'tipo_documentos'            => $arrTipoDocumentos,
                'tipo_contratos'             => $arrTipoContrato,
                'tipo_trabajador'            => $arrTipoTrabajador,
                'subtipo_trabajador'         => $arrSuntipoTrabajador
            ];
        } else {
            //Parametricas Documentos Electonicos
            $ofes = ConfiguracionObligadoFacturarElectronicamente::select([
                'ofe_id', 
                'ofe_identificacion', 
                'ofe_emision', 
                'ofe_recepcion', 
                'ofe_documento_soporte', 
                'ofe_emision_eventos_contratados_titulo_valor',
                'ofe_recepcion_eventos_contratados_titulo_valor',
                'ofe_identificador_unico_adquirente', 
                'ofe_filtros', 
                'ofe_informacion_personalizada_adquirente', 
                'ofe_integracion_ecm', 
                'ofe_integracion_ecm_conexion',
                'ofe_recepcion_fnc_activo',
                'ofe_recepcion_fnc_configuracion',
                DB::raw('TRIM(IF(LENGTH(ofe_razon_social) = 0 OR IFNULL(ofe_razon_social, TRUE), CONCAT(IF(IFNULL(ofe_razon_social, "") = "", "", ofe_razon_social)," ",IF(IFNULL(ofe_otros_nombres, "") = "", "", ofe_otros_nombres)," ", IF(IFNULL(ofe_primer_nombre, "") = "", "", ofe_primer_nombre), " ", IF(IFNULL(ofe_primer_apellido, "") = "", "", ofe_primer_apellido), " ", IF(IFNULL(ofe_segundo_apellido, "") = "", "", ofe_segundo_apellido)), ofe_razon_social)) as ofe_razon_social'), 'ofe_recepcion_transmision_erp'
            ])
                ->with([
                    'getGruposTrabajo:ofe_id,gtr_id,gtr_codigo,gtr_nombre'
                ])
                ->where(function($query) {
                    $query->where('ofe_emision', 'SI')
                        ->orWhere('ofe_recepcion', 'SI')
                        ->orWhere('ofe_documento_soporte', 'SI');
                })
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->orderBy('ofe_razon_social', 'asc')
                ->get();

            TenantTrait::GetVariablesSistemaTenant();
            $integracionEcm        = config('variables_sistema_tenant.INTEGRACION_ECM');
            $integracionEcmAuth    = config('variables_sistema_tenant.INTEGRACION_ECM_AUTH');
            $modulosIntegracionEcm = config('variables_sistema.MODULOS_INTEGRACION_ECM');
                
            $temp = [];
            foreach ($ofes as $ofe) {
                $data = $ofe->toArray();
                $data['ofe_identificacion_ofe_razon_social'] = $data['ofe_identificacion'] . ' - ' . $data['ofe_razon_social'];
                $ecm = ConfiguracionUsuarioEcm::where('usu_id', $authUser->usu_id)
                    ->where('estado', 'ACTIVO')
                    ->where('ofe_id', $data['ofe_id'])
                    ->first();

                $existeUserEcm = ($ecm) ? "SI": "NO";
                $data['variable_modulos_integracion_ecm'] = $modulosIntegracionEcm;
                $data['integracion_variable_ecm'] = $integracionEcm;
                $data['integracion_variable_ecm_auth'] = $integracionEcmAuth;
                $data['integracion_usuario_ecm'] = $existeUserEcm;

                if(!empty($ofe->ofe_recepcion_fnc_configuracion)) {
                    $ofeRecepcionFncConfiguracion = json_decode($ofe->ofe_recepcion_fnc_configuracion);
                    $data['ofe_recepcion_fnc_configuracion'] = [
                        'evento_recibo_bien' => isset($ofeRecepcionFncConfiguracion->evento_recibo_bien) ? $ofeRecepcionFncConfiguracion->evento_recibo_bien : null,
                        'validacion_aprobacion' => isset($ofeRecepcionFncConfiguracion->validacion_aprobacion) ? $ofeRecepcionFncConfiguracion->validacion_aprobacion : null,
                        'validacion_rechazo' => isset($ofeRecepcionFncConfiguracion->validacion_rechazo) ? $ofeRecepcionFncConfiguracion->validacion_rechazo : null,
                        'validacion_pagado' => isset($ofeRecepcionFncConfiguracion->validacion_pagado) ? $ofeRecepcionFncConfiguracion->validacion_pagado : null
                    ];
                }

                $temp[] = $data;
            }
            $ofes = $temp;

            $arrTipoOrganizaciones = [];
            $tipoOrganizaciones = ParametrosTipoOrganizacionJuridica::select(['toj_id', 'toj_codigo', 'toj_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', DB::raw('CONCAT(toj_codigo, " - ", toj_descripcion) as toj_codigo_descripion')])
                ->where('estado', 'ACTIVO')
                ->orderBy('toj_descripcion', 'asc')
                ->get()
                ->groupBy('toj_codigo')
                ->map(function ($item) use (&$arrTipoOrganizaciones) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrTipoOrganizaciones[] = $vigente['registro'];
                    }
                });

            $arrTipoRegimen = [];
            $tipoRegimen = ParametrosRegimenFiscal::select(['rfi_id', 'rfi_codigo', 'rfi_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', DB::raw('CONCAT(rfi_codigo, " - ", rfi_descripcion) as rfi_codigo_descripion')])
                ->where('estado', 'ACTIVO')
                ->orderBy('rfi_descripcion', 'asc')
                ->get()
                ->groupBy('rfi_codigo')
                ->map(function ($item) use (&$arrTipoRegimen) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrTipoRegimen[] = $vigente['registro'];
                    }
                });

            $arrProcedenciaVendedor = [];
            $procedenciaVendedor = ParametrosProcedenciaVendedor::select(['ipv_id', 'ipv_codigo', 'ipv_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', DB::raw('CONCAT(ipv_codigo, " - ", ipv_descripcion) as ipv_codigo_descripcion')])
                ->where('estado', 'ACTIVO')
                ->orderBy('ipv_descripcion', 'asc')
                ->get()
                ->groupBy('ipv_codigo')
                ->map(function ($item) use (&$arrProcedenciaVendedor) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrProcedenciaVendedor[] = $vigente['registro'];
                    }
                });

            $arrResFiscal = [];
            $resFiscal = ParametrosResponsabilidadFiscal::select(['ref_id', 'ref_codigo', 'ref_descripcion', 'estado', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', DB::raw('CONCAT(ref_codigo, " - ", ref_descripcion) as ref_codigo_descripion')]);
                if (isset($request->aplicaPara) && $request->aplicaPara == 'DS') {
                    $resFiscal = $resFiscal->where('ref_aplica_para', 'like', '%' . $request->aplicaPara . '%');
                } elseif (isset($request->aplicaPara) && $request->aplicaPara == 'DE') {
                    $resFiscal = $resFiscal->where('ref_aplica_para', 'like', '%' . $request->aplicaPara . '%');
                }
                $resFiscal = $resFiscal->where('estado', 'ACTIVO')
                ->orderBy('ref_descripcion', 'asc')
                ->get()
                ->groupBy('ref_codigo')
                ->map(function ($item) use (&$arrResFiscal) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrResFiscal[] = $vigente['registro'];
                    }
                });

            $arrTributos = [];
            $tributos = ParametrosTributo::select(['tri_id', 'tri_codigo', 'tri_nombre', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', DB::raw('IF(tri_codigo = "ZZ", "No aplica", tri_descripcion) as tri_descripcion'), 'tri_aplica_persona', DB::raw('CONCAT(tri_codigo, " - ", IF(tri_codigo = "ZZ", "No aplica", tri_descripcion)) as tri_codigo_descripion')]);
                if (isset($request->aplicaPara) && $request->aplicaPara == 'DS') {
                    $tributos = $tributos->where('tri_aplica_para_personas', 'like', '%' . $request->aplicaPara . '%');
                } elseif (isset($request->aplicaPara) && $request->aplicaPara == 'DE') {
                    $tributos = $tributos->where('tri_aplica_para_personas', 'like', '%' . $request->aplicaPara . '%');
                }
                $tributos = $tributos->where('estado', 'ACTIVO')
                ->orderBy('tri_nombre', 'asc')
                ->get()
                ->groupBy('tri_codigo')
                ->map(function ($item) use (&$arrTributos) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrTributos[] = $vigente['registro'];
                    }
                });

            $arrRolesRadian = [];
            $rolesRadian = ParametrosRadianRol::select(['rol_id', 'rol_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta']);
                $rolesRadian = $rolesRadian->where('estado', 'ACTIVO')
                ->orderBy('rol_descripcion', 'asc')
                ->get()
                ->groupBy('rol_descripcion')
                ->map(function ($item) use (&$arrRolesRadian) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if ($vigente['vigente']) {
                        $arrRolesRadian[] = $vigente['registro'];
                    }
                });

            $dataTemporalActor = [];
            $arrActoresRadian = RadianActor::select([
                    'act_id', 
                    'act_identificacion', 
                    'act_roles',
                    DB::raw('TRIM(IF(LENGTH(act_razon_social) = 0 OR IFNULL(act_razon_social, TRUE), CONCAT(IF(IFNULL(act_razon_social, "") = "", "", act_razon_social)," ",IF(IFNULL(act_otros_nombres, "") = "", "", act_otros_nombres)," ", IF(IFNULL(act_primer_nombre, "") = "", "", act_primer_nombre), " ", IF(IFNULL(act_primer_apellido, "") = "", "", act_primer_apellido), " ", IF(IFNULL(act_segundo_apellido, "") = "", "", act_segundo_apellido)), act_razon_social)) as act_razon_social')
                ])
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->get();

            foreach ($arrActoresRadian as $actor) {
                $data = $actor->toArray();
                $data['act_identificacion_act_razon_social'] = $data['act_identificacion'] . ' - ' . $data['act_razon_social'];
                $dataTemporalActor[] = $data;
            }
            
            $arrActoresRadian = $dataTemporalActor;

            $arrCentrosOperacion = CentroOperacion::select(['cop_id','cop_descripcion'])
                ->where('estado', 'ACTIVO')
                ->get();

            $arrCentrosCosto = CentroCosto::select(['cco_id', 'cco_codigo', 'cco_descripcion'])
                ->where('estado', 'ACTIVO')
                ->get();

            $respuesta = [
                'ofes'                       => $ofes,
                'spts'                       => $spts,
                'tipo_documentos'            => $arrTipoDocumentos,
                'tipo_organizaciones'        => $arrTipoOrganizaciones,
                'tipo_regimen'               => $arrTipoRegimen,
                'procedencia_vendedor'       => $arrProcedenciaVendedor,
                'responsabilidades_fiscales' => $arrResFiscal,
                'tributos'                   => $arrTributos,
                'roles_radian'               => $arrRolesRadian,
                'formas_pago'                => $arrFormaPago,
                'actores_radian'             => $arrActoresRadian,
                'centros_operacion'          => $arrCentrosOperacion,
                'centros_costo'              => $arrCentrosCosto,
            ];
        }

        $tat = isset($request->tat) ? $request->tat : false;
            $tipoAceptacionTacita = [];
            if ($tat) {
                $tipoAceptacionTacita = SistemaTiempoAceptacionTacita::select(['tat_id', 'tat_codigo', 'tat_descripcion'])
                    ->where('estado', 'ACTIVO')
                    ->orderBy('tat_descripcion', 'asc');
                $tipoAceptacionTacita = DB::select( $tipoAceptacionTacita->toSql() , $tipoAceptacionTacita->getBindings());
            }

        if ($tat)
            $respuesta['tiempo_aceptacion_tacita'] = $tipoAceptacionTacita;

        return response()->json(["data" => $respuesta], 200);
    }

    /**
     * Realiza búsquedas sobre las paramétricas de openETL.
     *
     * @param Request $request
     * @return Response
     */
    public function searchParametricas(Request $request) {
        if(
            !$request->has('tabla') || empty($request->tabla) ||
            !$request->has('campo') || empty($request->campo) ||
            !$request->has('valor') || ($request->has('valor') && $request->valor == '') ||
            (
                $request->has('tabla') &&
                $request->tabla == 'etl_resoluciones_facturacion' &&
                (
                    !$request->has('ofe_id') ||
                    ($request->has('ofe_id') && empty($request->ofe_id))
                )
            )
        ) {
            return response()->json([
                'message' => 'Error de procesamiento',
                'errors'  => ['La petición no cuenta con los parámetros requeridos para su procesamiento']
            ], 400);
        }

        $arrResultados    = [];
        $campoDescripcion = '';

        if(strstr($request->campo, '_codigo'))
            $campoDescripcion = str_replace('_codigo', '_descripcion', $request->campo);

        if($request->tabla == 'etl_resoluciones_facturacion') {
            $consultaParametrica = ConfiguracionResolucionesFacturacion::
                where('ofe_id', $request->ofe_id)
                ->where(function($query) use ($request) {
                    $query->where('rfa_resolucion', 'like', '%' . $request->valor . '%')
                        ->orWhere('rfa_prefijo', 'like', '%' . $request->valor . '%');
                });
        } elseif($request->tabla == 'etl_tipos_operacion') {
            if(!$request->has('top_aplica_para') || ($request->has('top_aplica_para') && empty($request->top_aplica_para)))
                $tipoOperacionAplicaPara = 'FC';
            else
                $tipoOperacionAplicaPara = $request->top_aplica_para;

            $consultaParametrica = DB::table($request->tabla)
                ->where('top_aplica_para', $tipoOperacionAplicaPara)
                ->where(function ($query) use ($request, $campoDescripcion) {
                    $query->where($request->campo, 'like', '%' . $request->valor . '%')
                        ->orWhere($campoDescripcion, 'like', '%' . $request->valor . '%');
                });
        } elseif($request->tabla == 'etl_tributos') {
            $consultaParametrica = DB::table($request->tabla);

            if($request->has('tri_tipo') && !empty($request->tri_tipo)) {
                if ($request->tri_tipo != "RETENCION") {
                    $consultaParametrica = $consultaParametrica->where(function ($query) use ($request) {
                        $query->where('tri_tipo', $request->tri_tipo)
                            ->orWhere('tri_codigo', 'ZZ');
                    });
                } else {
                    $arrCodigosReteSugerida = ['05', '06', '07'];
                    $consultaParametrica = $consultaParametrica->where('tri_tipo', $request->tri_tipo);
                    if($request->filled('retencion_sugerida') && $request->retencion_sugerida == 'SI')
                        $consultaParametrica = $consultaParametrica->whereIn('tri_codigo', $arrCodigosReteSugerida);
                }
            }

            $consultaParametrica = $consultaParametrica->where(function ($query) use ($request, $campoDescripcion) {
                    $query->where($request->campo, 'like', '%' . $request->valor . '%')
                        ->orWhere('tri_nombre', 'like', '%' . $request->valor . '%')
                        ->orWhere($campoDescripcion, 'like', '%' . $request->valor . '%');
                });
        } elseif($request->tabla == 'etl_forma_generacion_transmision') {
            $consultaParametrica = DB::table($request->tabla)
                ->where(function($query) use ($request) {
                    $query->where('fgt_codigo', 'like', '%' . $request->valor . '%')
                        ->orWhere('fgt_descripcion', 'like', '%' . $request->valor . '%');
                });
        } else {
            $consultaParametrica = DB::table($request->tabla)
                ->where($request->campo, 'like', '%' . $request->valor . '%');
        }

        if(!empty($campoDescripcion) && $request->tabla != 'etl_tipos_operacion' && $request->tabla != 'etl_resoluciones_facturacion' && $request->tabla != 'etl_tributos')
            $consultaParametrica = $consultaParametrica->orWhere($campoDescripcion, 'like', '%' . $request->valor . '%');

        if($request->tabla == 'etl_clasificacion_productos')
            $consultaParametrica = $consultaParametrica->orWhere('cpr_nombre', 'like', '%' . $request->valor . '%');

        // Validación del aplica para seleccionado en la parametrización de productos en la sección tributos, autorretenciones y retenciones sugeridas
        if($request->filled('aplica_para') && $request->tabla === 'etl_tributos') {
            $arrAplicaPara = explode(',', $request->aplica_para);
            $consultaParametrica = $consultaParametrica->where(function($query) use ($arrAplicaPara) {
                $i = 0;
                foreach ($arrAplicaPara as $aplica) {
                    if($i === 0)
                        $query = $query->where('tri_aplica_para_tributo', 'LIKE', '%'.$aplica.'%');
                    else
                        $query = $query->orWhere('tri_aplica_para_tributo', 'LIKE', '%'.$aplica.'%');

                    $i++;
                }
            });
            if($request->aplica_para === 'DS')
                $consultaParametrica = $consultaParametrica->where('tri_codigo', '!=', '07');
        }

        $consultaParametrica = $consultaParametrica->where('estado', 'ACTIVO')
            ->get()
            ->groupBy($request->campo)
            ->map(function($registro) use (&$arrResultados, $request, $campoDescripcion) {
                $vigente = $this->validarVigenciaRegistroParametrica($registro);

                if($request->tabla == 'etl_clasificacion_productos') {
                    if ($vigente['vigente']) {
                        $arrResultados[] = [
                            'cpr_codigo' => $vigente['registro']->cpr_codigo,
                            'cpr_nombre' => $vigente['registro']->cpr_nombre
                        ];
                    }
                } elseif($request->tabla == 'etl_resoluciones_facturacion') {
                    $arrResultados[] = [
                        'rfa_id'         => $registro[0]->rfa_id,
                        'rfa_prefijo'    => $registro[0]->rfa_prefijo,
                        'rfa_resolucion' => $registro[0]->rfa_resolucion
                    ];
                } elseif($request->tabla == 'etl_tributos') {
                    if ($vigente['vigente']) {
                        $arrResultados[] = [
                            'tri_codigo'         => $vigente['registro']->tri_codigo,
                            'tri_nombre'         => $vigente['registro']->tri_nombre,
                            'tri_tipo'           => $vigente['registro']->tri_tipo,
                            'tri_aplica_tributo' => $vigente['registro']->tri_aplica_tributo
                        ];
                    }
                } elseif($request->tabla == 'etl_colombia_compra_eficiente') {
                    if ($vigente['vigente']) {
                        $arrResultados[] = [
                            'codigo' => $vigente['registro']->{$request->campo},
                            'descripcion' => $vigente['registro']->$campoDescripcion
                        ];
                    } 
                } elseif($request->tabla == 'etl_forma_generacion_transmision') {
                    if ($vigente['vigente']) {
                        $arrResultados[] = [
                            'codigo'      => $vigente['registro']->fgt_codigo,
                            'descripcion' => $vigente['registro']->fgt_descripcion
                        ];
                    }
                } else {
                    if ($vigente['vigente']) {
                        $arrResultados[] = [
                            'codigo' => $vigente['registro']->{$request->campo},
                            'descripcion' => $vigente['registro']->$campoDescripcion
                        ];
                    }
                }
            });

        return response()->json([
            'data' => $arrResultados
        ], 200);
    }

    /**
     * Realiza búsquedas exactas sobre las paramétricas de openETL.
     *
     * @param Request $request
     * @param boolean $ultimoVigente Consulta el ultimo registro vigente
     *
     * @return Response
     */
    public function searchParametricasExacto(Request $request, $ultimoVigente) {
        if(
            !$request->has('tabla') || empty($request->tabla) ||
            !$request->has('campo') || empty($request->campo) ||
            !$request->has('valor') || ($request->has('valor') && $request->valor == '') ||
            (
                $request->has('tabla') &&
                $request->tabla == 'etl_resoluciones_facturacion' &&
                (
                    !$request->has('ofe_id') ||
                    ($request->has('ofe_id') && empty($request->ofe_id))
                )
            )
        ) {
            return response()->json([
                'message' => 'Error de procesamiento',
                'errors'  => ['La petición no cuenta con los parámetros requeridos para su procesamiento']
            ], 400);
        }

        $arrResultados    = [];

        if($request->tabla == 'etl_resoluciones_facturacion')
            $consultaParametrica = ConfiguracionResolucionesFacturacion::
                where('ofe_id', $request->ofe_id)
                ->where(function($query) use ($request) {
                    $query->where($request->campo, '=', $request->valor);
                });
        elseif($request->tabla == 'etl_tipos_operacion') {
            if(!$request->has('top_aplica_para') || ($request->has('top_aplica_para') && empty($request->top_aplica_para)))
                $tipoOperacionAplicaPara = 'FC';
            else
                $tipoOperacionAplicaPara = $request->top_aplica_para;

            $consultaParametrica = DB::table($request->tabla)
                ->where('top_aplica_para', $tipoOperacionAplicaPara)
                ->where($request->campo, '=', $request->valor);
        } elseif($request->tabla == 'etl_tributos') {
            $consultaParametrica = DB::table($request->tabla);

            if($request->has('tri_tipo') && !empty($request->tri_tipo)) {
                if ($request->tri_tipo != "RETENCION") {
                    $consultaParametrica = $consultaParametrica->where(function($query) {
                        $query->where('tri_tipo', $request->tri_tipo)
                            ->orWhere('tri_codigo', 'ZZ');
                    });
                } else {
                    $consultaParametrica = $consultaParametrica->where('tri_tipo', $request->tri_tipo);
                }
            }
            if($request->filled('aplica_para')) {
                $aplicaPara = explode(",", $request->aplica_para);
                $consultaParametrica = $consultaParametrica->where(function ($query) use ($aplicaPara) {
                    $i = 0;
                    foreach ($aplicaPara as $aplica) {
                        if($i === 0)
                            $query->where('tri_aplica_para_tributo', 'LIKE', '%'.$aplica.'%');
                        else
                            $query->orWhere('tri_aplica_para_tributo', 'LIKE', '%'.$aplica.'%');

                        $i++;
                    }
                });
            }

            $consultaParametrica = $consultaParametrica->where($request->campo, '=', $request->valor);
        } else
            $consultaParametrica = DB::table($request->tabla)
                ->where($request->campo, '=', $request->valor);

        if(!$request->has('editar') || ($request->has('editar') && $request->editar !== true))
            $consultaParametrica = $consultaParametrica->where('estado', 'ACTIVO');


        if($request->tabla == 'etl_resoluciones_facturacion') {
            $consultaParametrica = $consultaParametrica->first();
        } else {
            $aplicaUltimoVigente = $ultimoVigente ? false : true;
            $consultaParametrica = $consultaParametrica->get()
                ->groupBy($request->campo)
                ->map(function ($item) use(&$aplicaUltimoVigente) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item, $aplicaUltimoVigente);
                    if ($vigente['vigente']) {
                        return $vigente['registro'];
                    }
                })->first();
        }

        return response()->json([
            'data' => $consultaParametrica
        ], 200);
    }

    /**
     * Retorna data paramétrica para la creación manual de documentos electrónicos.
     * 
     * @param Request $request
     * @return Response
     */
    public function getParametrosDocumentosElectronicos(Request $request) {
        $aplicaPara = '';
        if($request->filled('aplica_para') && $request->aplica_para !== '')
            $aplicaPara = $request->aplica_para;

        $arrMoneda = [];
        $moneda = ParametrosMoneda::select(['mon_id', 'mon_codigo', 'mon_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(mon_codigo)'), 'asc')
            ->get()
            ->groupBy('mon_codigo')
            ->map(function ($item) use (&$arrMoneda) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrMoneda[] = $vigente['registro'];
                }
            });

        $arrTipoOperacion = [];
        $tipoOperacion = ParametrosTipoOperacion::select(['top_id', 'top_codigo', 'top_descripcion', 'top_aplica_para', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where(function ($query) {
                $query->whereNull('top_sector')
                ->orWhere('top_sector', 'NOT LIKE', '%SECTOR_SALUD%');
            })
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(top_codigo)'), 'asc');

        if($aplicaPara === 'DE')
            $tipoOperacion = $tipoOperacion->whereIn('top_aplica_para', ['FC', 'NC', 'ND']);
        elseif ($aplicaPara === 'DS')
            $tipoOperacion = $tipoOperacion->whereIn('top_aplica_para', ['DS']);
        else
            $tipoOperacion = $tipoOperacion->whereIn('top_aplica_para', ['FC', 'NC', 'ND', 'DS']);

        $tipoOperacion = $tipoOperacion->get()
            ->groupBy(function($item, $key) {
                return $item->top_codigo . '~' . $item->top_aplica_para;
            })
            ->map(function ($item) use (&$arrTipoOperacion) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrTipoOperacion[] = $vigente['registro'];
                }
            });

        $arrTipoDocumentos = [];
        $tipoDocumentos = ParametrosTipoDocumento::select(['tdo_id', 'tdo_codigo', 'tdo_descripcion', 'tdo_aplica_para', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(tdo_codigo)'), 'asc');

        if($aplicaPara !== '')
            $tipoDocumentos = $tipoDocumentos->where('tdo_aplica_para', 'LIKE', '%' . $aplicaPara . '%');

        $tipoDocumentos = $tipoDocumentos->get()
            ->groupBy('tdo_codigo')
            ->map(function ($item) use (&$arrTipoDocumentos) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrTipoDocumentos[] = $vigente['registro'];
                }
            });

        $arrTipoOrganizaciones = [];
        $tipoDocumentoElectronico = ParametrosTipoDocumentoElectronico::select(['tde_id', 'tde_codigo', 'tde_descripcion', 'tde_aplica_para', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(tde_codigo)'), 'asc');

        if($aplicaPara !== '')
            $tipoDocumentoElectronico = $tipoDocumentoElectronico->where('tde_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

        $tipoDocumentoElectronico = $tipoDocumentoElectronico->get()
            ->groupBy('tde_codigo')
            ->map(function ($item) use (&$arrTipoOrganizaciones) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrTipoOrganizaciones[] = $vigente['registro'];
                }
            });

        $arrFormaPago = [];
        $formasPago = ParametrosFormaPago::select(['fpa_id', 'fpa_codigo', 'fpa_descripcion', 'fpa_aplica_para', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(fpa_codigo)'), 'asc');

        if($aplicaPara !== '')
            $formasPago = $formasPago->where('fpa_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

        $formasPago = $formasPago->get()
            ->groupBy(function($item, $key) {
                return $item->fpa_codigo . '~' . $item->fpa_aplica_para;
            })
            ->map(function ($item) use (&$arrFormaPago) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrFormaPago[] = $vigente['registro'];
                }
            });

        $arrMediosPago = [];
        $mediosPago = ParametrosMediosPago::select(['mpa_id', 'mpa_codigo', 'mpa_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(mpa_codigo)'), 'asc')
            ->get()
            ->groupBy('mpa_codigo')
            ->map(function ($item) use (&$arrMediosPago) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrMediosPago[] = $vigente['registro'];
                }
            });

        $arrCodigosDescuento = [];
        $codigosDescuento = ParametrosCodigoDescuento::select(['cde_id', 'cde_codigo', 'cde_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(cde_codigo)'), 'asc')
            ->get()
            ->groupBy('cde_codigo')
            ->map(function ($item) use (&$arrCodigosDescuento) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrCodigosDescuento[] = $vigente['registro'];
                }
            });

        $cargos = EtlFacturacionWebCargo::select(['dmc_id', 'dmc_aplica_para', 'dmc_codigo', 'dmc_descripcion', 'dmc_porcentaje'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(dmc_codigo)'), 'asc');

        if($aplicaPara !== '')
            $cargos = $cargos->where('dmc_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

        $cargos = $cargos->get();

        $manualDescuento = EtlFacturacionWebDescuento::select(['dmd_id', 'dmd_aplica_para', 'dmd_codigo', 'dmd_descripcion', 'dmd_porcentaje','cde_id'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(dmd_codigo)'), 'asc')
            ->with('getCodigoDescuento:cde_id,cde_codigo,cde_descripcion');

        if($aplicaPara !== '')
            $manualDescuento = $manualDescuento->where('dmd_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

        $manualDescuento = $manualDescuento->get();

        $arrTributos = [];
        $tributos = ParametrosTributo::select(['tri_id', 'tri_codigo', 'tri_nombre', 'tri_descripcion', 'tri_tipo', 'tri_aplica_persona', 'tri_aplica_tributo', 'tri_aplica_para_tributo','fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('tri_tipo', 'RETENCION')
            ->where('estado', 'ACTIVO')
            ->orderBy('tri_nombre', 'asc');

        if($aplicaPara !== '')
            $tributos = $tributos->where('tri_aplica_para_tributo', 'LIKE', '%'.$aplicaPara.'%');

        $tributos = $tributos->get()
            ->groupBy('tri_codigo')
            ->map(function ($item) use (&$arrTributos) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrTributos[] = $vigente['registro'];
                }
            });

        $arrConceptosCorrecion = [
            'NC' => [],
            'ND' => []
        ];
        $conceptosCorreccion = ParametrosConceptoCorreccion::select(['cco_id', 'cco_tipo', 'cco_codigo', 'cco_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(cco_codigo)'), 'asc')
            ->get()
            ->groupBy(function($item, $key) {
                return $item->cco_tipo . '~' . $item->cco_codigo;
            })
            ->map(function ($item) use (&$arrConceptosCorrecion) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    if ($vigente['registro']->cco_tipo == "NC") {
                        $arrConceptosCorrecion['NC'][] = $vigente['registro'];
                    } elseif ($vigente['registro']->cco_tipo == "ND") {
                        $arrConceptosCorrecion['ND'][] = $vigente['registro'];
                    } elseif ($vigente['registro']->cco_tipo == "DS") {
                        $arrConceptosCorrecion['DS_NC'][] = $vigente['registro'];
                    }
                }
            });

        $arrGeneracionTransmision = [];
        $generacionTransmision = ParametrosFormaGeneracionTransmision::select(['fgt_id','fgt_codigo','fgt_descripcion','fecha_vigencia_desde','fecha_vigencia_hasta'])
            ->where('estado', 'ACTIVO')
            ->orderBy(DB::Raw('abs(fgt_codigo)'), 'asc')
            ->get()
            ->groupBy('fgt_codigo')
            ->map(function ($item) use (&$arrGeneracionTransmision) {
                $vigente = $this->validarVigenciaRegistroParametrica($item);
                if ($vigente['vigente']) {
                    $arrGeneracionTransmision[] = $vigente['registro'];
                }
            });

        return response()->json([
            'data' => [
                'fechaSistema'             => date('Y-m-d'),
                'horaSistema'              => date('H:i:s'),
                'moneda'                   => $arrMoneda,
                'tipoOperacion'            => $arrTipoOperacion,
                'tipoDocumentos'           => $arrTipoDocumentos,
                'tipoDocumentoElectronico' => $arrTipoOrganizaciones,
                'formasPago'               => $arrFormaPago,
                'mediosPago'               => $arrMediosPago,
                'conceptosCorreccion'      => $arrConceptosCorrecion,
                'codigosDescuento'         => $arrCodigosDescuento,
                'tributos'                 => $arrTributos,
                'cargos'                   => $cargos,
                'manualDescuento'          => $manualDescuento,
                'generacionTransmision'    => $arrGeneracionTransmision
            ]
        ], 200);
    }

    /**
     * Retorna los oferentes según los servicios habilitados.
     * 
     * @param Request $request Parámetros de la petición
     * @return Response
     */
    public function getOferentes(Request $request) {
        TenantTrait::GetVariablesSistemaTenant();
        $arrVariableSistema = config('variables_sistema_tenant.RECEPCION_LISTA_ERP');

        $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_emision', 'ofe_recepcion', 'ofe_identificador_unico_adquirente', 'ofe_filtros', 'ofe_informacion_personalizada_adquirente', 'ofe_integracion_ecm', 'ofe_integracion_ecm_conexion', 'ofe_recepcion_fnc_activo',
            DB::raw('TRIM(IF(LENGTH(ofe_razon_social) = 0 OR IFNULL(ofe_razon_social, TRUE), CONCAT(IF(IFNULL(ofe_razon_social, "") = "", "", ofe_razon_social)," ",IF(IFNULL(ofe_otros_nombres, "") = "", "", ofe_otros_nombres)," ", IF(IFNULL(ofe_primer_nombre, "") = "", "", ofe_primer_nombre), " ", IF(IFNULL(ofe_primer_apellido, "") = "", "", ofe_primer_apellido), " ", IF(IFNULL(ofe_segundo_apellido, "") = "", "", ofe_segundo_apellido)), ofe_razon_social)) as ofe_razon_social'), 'ofe_recepcion_transmision_erp'
        ])
        ->where(function ($query) use ($request) {
            if($request->filled('recepcion') && $request->recepcion === 'true' && $request->filled('emision') && $request->emision === 'true') {
                $query->where('ofe_emision', 'SI')->orWhere('ofe_recepcion', 'SI');
            } else if($request->filled('recepcion') && $request->recepcion === 'true') {
                $query->where('ofe_recepcion', 'SI');
            } else if($request->filled('emision') && $request->emision === 'true') {
                $query->where('ofe_emision', 'SI');
            }
        })
        ->validarAsociacionBaseDatos()
        ->where('estado', 'ACTIVO')
        ->orderBy('ofe_razon_social', 'asc')
        ->get();

        $temp = [];
        foreach ($ofes as $ofe) {
            $data = $ofe->toArray();
            $data['ofe_identificacion_ofe_razon_social'] = $data['ofe_identificacion'] . ' - ' . $data['ofe_razon_social'];
            $temp[] = $data;
        }
        $arrData = [
            'ofes' => $temp,
            'erp'  => $arrVariableSistema
        ];
        return response()->json(["data" => $arrData], 200);
    }

    /**
     * Retorna la lista de reportes que se han solicitado durante el día en el módulo de configuración.
     * 
     * El listado de reportes se generará según la fecha del sistema para el usuario autenticado,
     * Si este usuario es un ADMINISTRADOR o MA se listarán todos los reportes.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function listarReportesDescargar(Request $request): JsonResponse {
        $user     = auth()->user();
        $reportes = [];
        $start          = $request->filled('start')          ? $request->start          : 0;
        $length         = $request->filled('length')         ? $request->length         : 10;
        $ordenDireccion = $request->filled('ordenDireccion') ? $request->ordenDireccion : 'DESC';
        $columnaOrden   = $request->filled('columnaOrden')   ? $request->columnaOrden   : 'fecha_modificacion';

        $consulta = EtlProcesamientoJson::select(['pjj_id', 'pjj_tipo', 'pjj_json', 'age_estado_proceso_json', 'fecha_modificacion', 'usuario_creacion'])
            ->where('pjj_tipo', 'REPADQ')
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha_modificacion', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'])
            ->when(!in_array($user->usu_type, ['ADMINISTRADOR', 'MA']), function ($query) use ($user) {
                return $query->where('usuario_creacion', $user->usu_id);
            })
            ->when($request->filled('buscar'), function ($query) use ($request) {
                return $query->where(function($queryWhere) use ($request) {
                    $queryWhere->where('fecha_modificacion', 'like', '%' . $request->buscar . '%')
                        ->orWhere('pjj_json', 'like', '%' . $request->buscar . '%');
                });
            });

        $totalReportes = $consulta->count();
        $length = $length != -1 ? $length : $totalReportes;

        $consulta->orderBy($columnaOrden, $ordenDireccion)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function($reporte) use (&$reportes) {
                $pjjJson           = json_decode($reporte->pjj_json);
                $fechaModificacion = Carbon::createFromFormat('Y-m-d H:i:s', $reporte->fecha_modificacion)->format('Y-m-d H:i:s');
                $tipoReporte       = 'Reportes Background';
                $usuario           = User::select(['usu_id', 'usu_identificacion', 'usu_nombre'])->where('usu_id', $reporte->usuario_creacion)->first();
                if($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->archivo_reporte)) {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => $pjjJson->archivo_reporte,
                        'fecha'           => $fechaModificacion,
                        'errores'         => '',
                        'estado'          => 'FINALIZADO',
                        'tipo_reporte'    => $tipoReporte,
                        'usuario'         => $usuario,
                        'existe_archivo'  => file_exists(storage_path('etl/descargas/' . $pjjJson->archivo_reporte))
                    ];
                } elseif($reporte->age_estado_proceso_json == 'FINALIZADO' && isset($pjjJson->errors)) {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => '',
                        'fecha'           => $fechaModificacion,
                        'errores'         => $pjjJson->errors,
                        'estado'          => 'FINALIZADO',
                        'tipo_reporte'    => $tipoReporte,
                        'usuario'         => $usuario,
                        'existe_archivo'  => false
                    ];
                } elseif($reporte->age_estado_proceso_json != 'FINALIZADO') {
                    $reportes[] = [
                        'pjj_id'          => $reporte->pjj_id,
                        'archivo_reporte' => '',
                        'fecha'           => $fechaModificacion,
                        'errores'         => '',
                        'estado'          => 'EN PROCESO',
                        'tipo_reporte'    => $tipoReporte,
                        'usuario'         => $usuario,
                        'existe_archivo'  => false
                    ];
                }
            });

        return response()->json([
            'total'     => $totalReportes,
            'filtrados' => count($reportes),
            'data'      => $reportes
        ], 200);
    }

    /**
     * Descarga un reporte generado por el usuario autenticado en el módulo de Configuración.
     *
     * @param  Request $request Parámetros de la petición
     * @return JsonResponse|BinaryFileResponse
     */
    public function descargarReporte(Request $request) {
        $user = auth()->user();

        // Verifica que el pjj_id del request pertenezca al usuario autenticado
        $pjj = EtlProcesamientoJson::find($request->pjj_id);

        if($pjj && $pjj->usuario_creacion == $user->usu_id) {
            if($pjj->pjj_json != '') {
                $pjjJson = json_decode($pjj->pjj_json);
                $archivo = $pjjJson->archivo_reporte;

                $headers = [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ];

                if(is_file(storage_path('etl/descargas/' . $archivo)))
                    return response()
                        ->download(storage_path('etl/descargas/' . $archivo), $archivo, $headers);
                else
                    // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
                    // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
                    $headersError = [
                        header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                        header('X-Error-Status: 422'),
                        header('X-Error-Message: Archivo no encontrado')
                    ];
                    return response()->json([
                        'message' => 'Error en la descarga',
                        'errors' => ['Archivo no encontrado']
                    ], 409, $headersError);
            }
        } elseif($pjj && $pjj->usuario_creacion != $user->usu_id) {
            // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
            // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
            $headersError = [
                header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                header('X-Error-Status: 422'),
                header('X-Error-Message: Usted no tiene permisos para descargar el archivo solicitado')
            ];
            return response()->json([
                'message' => 'Error en la descarga',
                'errors' => ['Usted no tiene permisos para descargar el archivo solicitado']
            ], 409, $headersError);
        } else {
            // Al obtenerse la respuesta en el frontend en un Observable de tipo Blob se deben agregar 
            // headers en la respuesta del error para poder mostrar explicitamente al usuario el error que ha ocurrido
            $headersError = [
                header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                header('X-Error-Status: 422'),
                header('X-Error-Message: No se encontr&oacute; el registro asociado a la consulta')
            ];
            return response()->json([
                'message' => 'Error en la descarga',
                'errors' => ['No se encontró el registro asociado a la consulta']
            ], 404, $headersError);
        }
    }
}