<?php
namespace App\Services\Recepcion;

use Validator;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response as ResponseHttp;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Modulos\Documentos\BaseDocumentosController;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Repositories\Recepcion\RepCabeceraDocumentoRepository;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;
use openEtl\Tenant\Traits\Particionamiento\TenantParticionamientoTrait;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RepCabeceraDocumentoService extends BaseDocumentosController {
    use TenantParticionamientoTrait;

    /**
     * Instancia del repositorio de cabecera en recepción.
     * 
     * Clase encargada de la lógica de consultas frente a la base de datos
     *
     * @var RepCabeceraDocumentoRepository
     */
    protected $recepcionCabeceraRepository;

    /**
     * Nombre de la conexión Tenant por defecto a la base de datos.
     *
     * @var string
     */
    protected $connection = 'conexion01';

    /**
     * Identificación del OFE para el cual se realizan transmisiones a openComex.
     *
     * @var string
     */
    protected $ofeTransmisionOpenComex = '830076778';

    /**
     * Títulos para la fila de encabezado del Excel.
     *
     * @var array
     */
    protected $encabezadoExcel = [
        'NIT RECEPTOR',
        'RECEPTOR',
        'RESOLUCION FACTURACION',
        'NIT EMISOR',
        'EMISOR',
        'CÓDIGO TIPO DOCUMENTO',
        'TIPO DOCUMENTO',
        'CÓDIGO TIPO OPERACION',
        'TIPO OPERACION',
        'PREFIJO',
        'CONSECUTIVO',
        'FECHA DOCUMENTO',
        'HORA DOCUMENTO',
        'FECHA VENCIMIENTO',
        'OBSERVACION',
        'CUFE',
        'MONEDA',
        'TOTAL ANTES DE IMPUESTOS',
        'IMPUESTOS',
        'CARGOS',
        'DESCUENTOS',
        'REDONDEO',
        'TOTAL',
        'ANTICIPOS',
        'RETENCIONES',
        'INCONSISTENCIAS XML-UBL',
        'FECHA VALIDACION DIAN',
        'ESTADO DIAN',
        'RESULTADO DIAN',
        'FECHA ACUSE DE RECIBO',
        'FECHA RECIBO DE BIEN Y/O PRESTACION SERVICIO',
        'ESTADO DOCUMENTO',
        'FECHA ESTADO',
        'MOTIVO RECHAZO',
        'TRANSMISION ERP',
        'RESULTADO TRANSMISION ERP',
        'OBSERVACIONES TRANSMISION ERP'
    ];

    public function __construct(RepCabeceraDocumentoRepository $recepcionCabeceraRepository) {
        $this->recepcionCabeceraRepository = $recepcionCabeceraRepository;
    }

    /**
     * Deserializa un string.
     *
     * @param string $value Valor a procesar
     * @return mixed
     */
    public function unserializeObject($value) {
        $value = preg_replace_callback('!s:(\d+):"(.*?)";!', function($match) {      
            return ($match[1] == strlen($match[2])) ? $match[0] : 's:' . strlen($match[2]) . ':"' . $match[2] . '";';
        }, $value);
        
        if (unserialize(trim($value))){
            return unserialize(trim($value));
        } else {
            return trim($value);
        } 
    }

    /**
     * Retorna el nombre de la tabla asociada a un modelo.
     *
     * @return string
     */
    public function getNombreTabla(string $class): string {
        return app($class)->getTable();
    }

    /**
     * Valida los parámetros recibidos para la consulta de documentos en cabecera.
     *
     * @param Request $request
     * @return Validator
     */
    public function validarConsultaCabecera(Request $request) {
        return Validator::make($request->all(), [
            'ofe_id'          => 'required|numeric',
            'cdo_fecha_desde' => 'required|date_format:Y-m-d',
            'cdo_fecha_hasta' => 'required|date_format:Y-m-d',
            'columnaOrden'    => 'required|string',
            'ordenDireccion'  => 'required|string',
            'start'           => 'required|numeric',
            'length'          => 'required|numeric'
        ]);
    }

    /**
     * Da formato a dos decimales a las columnas con valores númericos.
     *
     * @param \stdClass $documento Colección del documento en procesamiento
     * @return void
     */
    private function formatoColumnasNumericas(\stdClass &$documento): void {
        if(isset($documento->cdo_valor_sin_impuestos))
            $documento->cdo_valor_sin_impuestos                     = number_format($documento->cdo_valor_sin_impuestos, 2, '.', '');

        if(isset($documento->cdo_valor_sin_impuestos_moneda_extranjera))
            $documento->cdo_valor_sin_impuestos_moneda_extranjera   = number_format($documento->cdo_valor_sin_impuestos_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_impuestos))
            $documento->cdo_impuestos                               = number_format($documento->cdo_impuestos, 2, '.', '');

        if(isset($documento->cdo_impuestos_moneda_extranjera))
            $documento->cdo_impuestos_moneda_extranjera             = number_format($documento->cdo_impuestos_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_total))
            $documento->cdo_total                                   = number_format($documento->cdo_total, 2, '.', '');

        if(isset($documento->cdo_total_moneda_extranjera))
            $documento->cdo_total_moneda_extranjera                 = number_format($documento->cdo_total_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_cargos))
            $documento->cdo_cargos                                  = number_format($documento->cdo_cargos, 2, '.', '');

        if(isset($documento->cdo_cargos_moneda_extranjera))
            $documento->cdo_cargos_moneda_extranjera                = number_format($documento->cdo_cargos_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_descuentos))
            $documento->cdo_descuentos                              = number_format($documento->cdo_descuentos, 2, '.', '');

        if(isset($documento->cdo_descuentos_moneda_extranjera))
            $documento->cdo_descuentos_moneda_extranjera            = number_format($documento->cdo_descuentos_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_retenciones_sugeridas))
            $documento->cdo_retenciones_sugeridas                   = number_format($documento->cdo_retenciones_sugeridas, 2, '.', '');

        if(isset($documento->cdo_retenciones_sugeridas_moneda_extranjera))
            $documento->cdo_retenciones_sugeridas_moneda_extranjera = number_format($documento->cdo_retenciones_sugeridas_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_redondeo))
            $documento->cdo_redondeo                                = number_format($documento->cdo_redondeo, 2, '.', '');

        if(isset($documento->cdo_redondeo_moneda_extranjera))
            $documento->cdo_redondeo_moneda_extranjera              = number_format($documento->cdo_redondeo_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_anticipo))
            $documento->cdo_anticipo                                = number_format($documento->cdo_anticipo, 2, '.', '');

        if(isset($documento->cdo_anticipo_moneda_extranjera))
            $documento->cdo_anticipo_moneda_extranjera              = number_format($documento->cdo_anticipo_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_retenciones))
            $documento->cdo_retenciones                             = number_format($documento->cdo_retenciones, 2, '.', '');

        if(isset($documento->cdo_retenciones_moneda_extranjera))
            $documento->cdo_retenciones_moneda_extranjera           = number_format($documento->cdo_retenciones_moneda_extranjera, 2, '.', '');

        if(isset($documento->cdo_valor_a_pagar))
            $documento->cdo_valor_a_pagar                           = number_format($documento->cdo_valor_a_pagar, 2, '.', '');

        if(isset($documento->cdo_valor_a_pagar_moneda_extranjera))
            $documento->cdo_valor_a_pagar_moneda_extranjera         = number_format($documento->cdo_valor_a_pagar_moneda_extranjera, 2, '.', '');
    }

    /**
     * Arma el array correspondiente a una relación del documento con un estado.
     *
     * @param \stdClass $documento Colección del documento en procesamiento
     * @param string $prefijoRelacion Prefijo de la relación para la cual se armará el array
     * @return array Array con la información de la relación
     */
    public function armaArrayRelacionEstado(\stdClass $documento, string $prefijoRelacion): array {
        return [
            'est_id'                    => $documento->{$prefijoRelacion . "_est_id"},
            'cdo_id'                    => $documento->{$prefijoRelacion . "_cdo_id"},
            'est_correos'               => $documento->{$prefijoRelacion . "_est_correos"},
            'est_informacion_adicional' => !empty($documento->{$prefijoRelacion . "_est_informacion_adicional"}) ? $documento->{$prefijoRelacion . "_est_informacion_adicional"} : NULL,
            'est_estado'                => $documento->{$prefijoRelacion . "_est_estado"},
            'est_resultado'             => $documento->{$prefijoRelacion . "_est_resultado"},
            'est_mensaje_resultado'     => $documento->{$prefijoRelacion . "_est_mensaje_resultado"},
            'est_object'                => !empty($documento->{$prefijoRelacion . "_est_object"}) ? $this->unserializeObject($documento->{$prefijoRelacion . "_est_object"}) : '',
            'est_ejecucion'             => $documento->{$prefijoRelacion . "_est_ejecucion"},
            'est_motivo_rechazo'        => !empty($documento->{$prefijoRelacion . "_est_motivo_rechazo"}) ? json_decode($documento->{$prefijoRelacion . "_est_motivo_rechazo"}, true) : NULL,
            'est_inicio_proceso'        => $documento->{$prefijoRelacion . "_est_inicio_proceso"},
            'fecha_creacion'            => $documento->{$prefijoRelacion . "_fecha_creacion"}
        ];
    }

    /**
     * Destruye propiedades del documento que fueron utilizadas para armar el array de la relación correspondiente.
     * 
     * Esto evita que el objeto final del documento sea demasiado extenso y que tenga propiedades que no son utilizadas
     *
     * @param \stdClass $documento Colección del documento en procesamiento
     * @param string $prefijoRelacion Prefijo de la relación para la cual se eliminarán propiedades
     * @return void
     */
    public function destruyePropiedadesRelaciones(\stdClass &$documento, string $prefijoRelacion): void {
        unset(
            $documento->{$prefijoRelacion . "_est_id"},
            $documento->{$prefijoRelacion . "_cdo_id"},
            $documento->{$prefijoRelacion . "_est_correos"},
            $documento->{$prefijoRelacion . "_est_informacion_adicional"},
            $documento->{$prefijoRelacion . "_est_estado"},
            $documento->{$prefijoRelacion . "_est_resultado"},
            $documento->{$prefijoRelacion . "_est_mensaje_resultado"},
            $documento->{$prefijoRelacion . "_est_object"},
            $documento->{$prefijoRelacion . "_est_ejecucion"},
            $documento->{$prefijoRelacion . "_est_motivo_rechazo"},
            $documento->{$prefijoRelacion . "_est_inicio_proceso"},
            $documento->{$prefijoRelacion . "_fecha_creacion"}
        );
    }

    /**
     * Arma las propiedades de las relaciones de un documento.
     *
     * @param \stdClass $documento Colección del documento en procesamiento
     * @return void
     */
    private function armarPropiedadesRelaciones(\stdClass &$documento): void {
        $documento->get_configuracion_obligado_facturar_electronicamente = !empty($documento->ofe_id) ? [
            'ofe_id'                   => $documento->ofe_id,
            'ofe_identificacion'       => $documento->ofe_identificacion,
            'ofe_razon_social'         => $documento->ofe_razon_social,
            'ofe_nombre_comercial'     => $documento->ofe_nombre_comercial,
            'ofe_primer_apellido'      => $documento->ofe_primer_apellido,
            'ofe_segundo_apellido'     => $documento->ofe_segundo_apellido,
            'ofe_primer_nombre'        => $documento->ofe_primer_nombre,
            'ofe_otros_nombres'        => $documento->ofe_otros_nombres,
            'nombre_completo'          => str_replace('  ', ' ', trim($documento->ofe_nombre_completo)),
            'ofe_recepcion_fnc_activo' => isset($documento->ofe_recepcion_fnc_activo) ? $documento->ofe_recepcion_fnc_activo : NULL,
            'get_grupos_trabajo'       => isset($documento->get_grupos_trabajo_ofe) && !empty($documento->get_grupos_trabajo_ofe) ? json_decode($documento->get_grupos_trabajo_ofe, true) : []
        ] : NULL;

        $documento->get_configuracion_proveedor = !empty($documento->pro_id) ? [
            'pro_id'                        => $documento->pro_id,
            'pro_identificacion'            => $documento->pro_identificacion,
            'pro_id_personalizado'          => $documento->pro_id_personalizado,
            'pro_razon_social'              => $documento->pro_razon_social,
            'pro_nombre_comercial'          => $documento->pro_nombre_comercial,
            'pro_primer_apellido'           => $documento->pro_primer_apellido,
            'pro_segundo_apellido'          => $documento->pro_segundo_apellido,
            'pro_primer_nombre'             => $documento->pro_primer_nombre,
            'pro_otros_nombres'             => $documento->pro_otros_nombres,
            'nombre_completo'               => str_replace('  ', ' ', trim($documento->pro_nombre_completo))
        ] : NULL;

        $documento->get_grupo_trabajo = !empty($documento->gtr_id) ? [
            'gtr_id'     => $documento->gtr_id,
            'gtr_codigo' => $documento->gtr_codigo,
            'gtr_nombre' => $documento->gtr_nombre,
            'estado'     => $documento->cdo_gtr_estado
        ] : NULL;

        $documento->get_parametros_moneda = !empty($documento->mon_id) ? [
            'mon_id'          => $documento->mon_id,
            'mon_codigo'      => $documento->mon_codigo,
            'mon_descripcion' => $documento->mon_descripcion
        ] : NULL;

        $documento->get_parametros_moneda_extranjera = !empty($documento->mon_id_extranjera) ? [
            'mon_id'          => $documento->mon_id_extranjera,
            'mon_codigo'      => $documento->mon_codigo_extranjera,
            'mon_descripcion' => $documento->mon_descripcion_extranjera
        ] : NULL;

        $documento->get_tipo_documento_electronico = !empty($documento->tde_id) ? [
            'tde_id'          => $documento->tde_id,
            'tde_codigo'      => $documento->tde_codigo,
            'tde_descripcion' => $documento->tde_descripcion
        ] : NULL;

        $documento->get_tipo_operacion = !empty($documento->top_id) ? [
            'top_id'          => $documento->top_id,
            'top_codigo'      => $documento->top_codigo,
            'top_descripcion' => $documento->top_descripcion
        ] : NULL;

        $documento->get_documento_aprobado                            = !empty($documento->get_documento_aprobado_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_documento_aprobado') : NULL;
        $documento->get_documento_aprobado_notificacion               = !empty($documento->get_documento_aprobado_notificacion_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_documento_aprobado_notificacion') : NULL;
        $documento->get_documento_rechazado                           = !empty($documento->get_documento_rechazado_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_documento_rechazado') : NULL;
        $documento->get_status_en_proceso                             = !empty($documento->get_status_en_proceso_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_status_en_proceso') : NULL;
        $documento->get_estado_rdi_exitoso                            = !empty($documento->get_estado_rdi_exitoso_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_estado_rdi_exitoso') : NULL;
        $documento->get_estado_rdi_inconsistencia                     = !empty($documento->get_estado_rdi_inconsistencia_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_estado_rdi_inconsistencia') : NULL;
        $documento->get_aceptado                                      = !empty($documento->get_aceptado_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_aceptado') : NULL;
        $documento->get_aceptado_t                                    = !empty($documento->get_aceptado_t_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_aceptado_t') : NULL;
        $documento->get_rechazado                                     = !empty($documento->get_rechazado_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_rechazado') : NULL;
        $documento->get_aceptado_fallido                              = !empty($documento->get_aceptado_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_aceptado_fallido') : NULL;
        $documento->get_aceptado_t_fallido                            = !empty($documento->get_aceptado_t_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_aceptado_t_fallido') : NULL;
        $documento->get_rechazado_fallido                             = !empty($documento->get_rechazado_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_rechazado_fallido') : NULL;
        $documento->get_transmision_erp                               = !empty($documento->get_transmision_erp_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_transmision_erp') : NULL;
        $documento->get_transmision_erp_exitoso                       = !empty($documento->get_transmision_erp_exitoso_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_transmision_erp_exitoso') : NULL;
        $documento->get_transmision_erp_excluido                      = !empty($documento->get_transmision_erp_excluido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_transmision_erp_excluido') : NULL;
        $documento->get_transmision_erp_fallido                       = !empty($documento->get_transmision_erp_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_transmision_erp_fallido') : NULL;
        $documento->get_opencomex_cxp_exitoso                         = !empty($documento->get_opencomex_cxp_exitoso_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_opencomex_cxp_exitoso') : NULL;
        $documento->get_opencomex_cxp_fallido                         = !empty($documento->get_opencomex_cxp_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_opencomex_cxp_fallido') : NULL;
        $documento->get_notificacion_acuse_recibo                     = !empty($documento->get_notificacion_acuse_recibo_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_notificacion_acuse_recibo') : NULL;
        $documento->get_notificacion_recibo_bien                      = !empty($documento->get_notificacion_recibo_bien_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_notificacion_recibo_bien') : NULL;
        $documento->get_notificacion_aceptacion                       = !empty($documento->get_notificacion_aceptacion_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_notificacion_aceptacion') : NULL;
        $documento->get_notificacion_rechazo                          = !empty($documento->get_notificacion_rechazo_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_notificacion_rechazo') : NULL;
        $documento->get_notificacion_acuse_recibo_fallido             = !empty($documento->get_notificacion_acuse_recibo_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_notificacion_acuse_recibo_fallido') : NULL;
        $documento->get_notificacion_recibo_bien_fallido              = !empty($documento->get_notificacion_recibo_bien_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_notificacion_recibo_bien_fallido') : NULL;
        $documento->get_notificacion_aceptacion_fallido               = !empty($documento->get_notificacion_aceptacion_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_notificacion_aceptacion_fallido') : NULL;
        $documento->get_notificacion_rechazo_fallido                  = !empty($documento->get_notificacion_rechazo_fallido_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_notificacion_rechazo_fallido') : NULL;
        $documento->get_ultimo_estado_documento                       = !empty($documento->get_ultimo_estado_documento_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_ultimo_estado_documento') : NULL;
        $documento->get_ultimo_estado_validacion                      = !empty($documento->get_ultimo_estado_validacion_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_ultimo_estado_validacion') : NULL;
        $documento->get_ultimo_estado_validacion_en_proceso_pendiente = !empty($documento->get_ultimo_estado_validacion_en_proceso_pendiente_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_ultimo_estado_validacion_en_proceso_pendiente') : NULL;
        $documento->get_validacion_ultimo                             = !empty($documento->get_validacion_ultimo_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_validacion_ultimo') : NULL;
        $documento->get_ultimo_evento_dian                            = !empty($documento->get_ultimo_evento_dian_est_id) ? $this->armaArrayRelacionEstado($documento, 'get_ultimo_evento_dian') : NULL;

        $documento->get_dad_documentos_daop['cdo_informacion_adicional'] = !empty($documento->cdo_informacion_adicional) ? json_decode($documento->cdo_informacion_adicional, true) : [];

        $documento->estado_documento = '';
        if ($documento->get_documento_aprobado) {
            $documento->estado_documento = 'APROBADO';
        } elseif ($documento->get_documento_aprobado_notificacion) {
            $documento->estado_documento = 'APROBADO_NOTIFICACION';
        } elseif ($documento->get_status_en_proceso) {
            $documento->estado_documento = 'GETSTATUS_ENPROCESO';
        } elseif ($documento->get_documento_rechazado) {
            $documento->estado_documento = 'RECHAZADO';
        } elseif ($documento->get_estado_rdi_inconsistencia) {
            $documento->estado_documento = 'RDI_INCONSISTENCIA';
        }

        $documento->estado_dian = '';
        if ($documento->get_ultimo_evento_dian && ($documento->get_ultimo_evento_dian['est_estado'] == 'ACEPTACION' || 
            $documento->get_ultimo_evento_dian['est_estado'] == 'UBLACEPTACION') && $documento->get_ultimo_evento_dian['est_resultado'] == 'EXITOSO'
        )
            $documento->estado_dian = 'ACEPTACION';
        if ($documento->get_ultimo_evento_dian && ($documento->get_ultimo_evento_dian['est_estado'] == 'ACEPTACION' || 
            $documento->get_ultimo_evento_dian['est_estado'] == 'UBLACEPTACION') && $documento->get_ultimo_evento_dian['est_resultado'] == 'FALLIDO'
        )
            $documento->estado_dian = 'ACEPTACION_FALLIDO';
        else if ($documento->get_ultimo_evento_dian && ($documento->get_ultimo_evento_dian['est_estado'] == 'ACEPTACIONT' || 
            $documento->get_ultimo_evento_dian['est_estado'] == 'UBLACEPTACIONT') && $documento->get_ultimo_evento_dian['est_resultado'] == 'EXITOSO'
        )
            $documento->estado_dian = 'ACEPTACIONT';
        else if ($documento->get_ultimo_evento_dian && ($documento->get_ultimo_evento_dian['est_estado'] == 'ACEPTACIONT' || 
            $documento->get_ultimo_evento_dian['est_estado'] == 'UBLACEPTACIONT') && $documento->get_ultimo_evento_dian['est_resultado'] == 'FALLIDO'
        )
            $documento->estado_dian = 'ACEPTACIONT_FALLIDO';
        else if ($documento->get_ultimo_evento_dian && ($documento->get_ultimo_evento_dian['est_estado'] == 'RECHAZO' || 
            $documento->get_ultimo_evento_dian['est_estado'] == 'UBLRECHAZO') && $documento->get_ultimo_evento_dian['est_resultado'] == 'EXITOSO'
        )
            $documento->estado_dian = 'RECHAZO';
        else if ($documento->get_ultimo_evento_dian && ($documento->get_ultimo_evento_dian['est_estado'] == 'RECHAZO' || 
            $documento->get_ultimo_evento_dian['est_estado'] == 'UBLRECHAZO') && $documento->get_ultimo_evento_dian['est_resultado'] == 'FALLIDO'
        )
            $documento->estado_dian = 'RECHAZO_FALLIDO';

        $documento->estado_transmisionerp = '';
        if ($documento->get_transmision_erp_exitoso) {
            $documento->estado_transmisionerp = 'TRANSMISIONERP_EXITOSO';
        } elseif ($documento->get_transmision_erp_excluido) {
            $documento->estado_transmisionerp = 'TRANSMISIONERP_EXCLUIDO';
        } elseif ($documento->get_transmision_erp_fallido) {
            $documento->estado_transmisionerp = 'TRANSMISIONERP_FALLIDO';
        }

        $documento->estado_notificacion = '';
        if ($documento->get_notificacion_acuse_recibo) {
            $documento->estado_notificacion = 'NOTACUSERECIBO';
        } elseif ($documento->get_notificacion_recibo_bien) {
            $documento->estado_notificacion = 'NOTRECIBOBIEN';
        } elseif ($documento->get_notificacion_aceptacion) {
            $documento->estado_notificacion = 'NOTACEPTACION';
        } elseif ($documento->get_notificacion_rechazo) {
            $documento->estado_notificacion = 'NOTRECHAZO';
        }

        $documento->estado_notificacion_fallido = '';
        if ($documento->get_notificacion_acuse_recibo_fallido && !$documento->get_notificacion_acuse_recibo) {
            $documento->estado_notificacion_fallido = 'NOTACUSERECIBO_FALLIDO';
        } elseif ($documento->get_notificacion_recibo_bien_fallido && !$documento->get_notificacion_recibo_bien) {
            $documento->estado_notificacion_fallido = 'NOTRECIBOBIEN_FALLIDO';
        } elseif ($documento->get_notificacion_aceptacion_fallido && !$documento->get_notificacion_aceptacion) {
            $documento->estado_notificacion_fallido = 'NOTACEPTACION_FALLIDO';
        } elseif ($documento->get_notificacion_rechazo_fallido && !$documento->get_notificacion_rechazo) {
            $documento->estado_notificacion_fallido = 'NOTRECHAZO_FALLIDO';
        }

        $documento->aplica_documento_anexo = '';
        if (isset($documento->get_documentos_anexos_count) && $documento->get_documentos_anexos_count > 0)
            $documento->aplica_documento_anexo = 'SI';

        TenantTrait::GetVariablesSistemaTenant();
        $documento->estado_transmision_opencomex = '';
        if($this->ofeTransmisionOpenComex == $documento->get_configuracion_obligado_facturar_electronicamente['ofe_identificacion'] && !empty($documento->get_configuracion_proveedor)) {                
            $nitsIntegracionOpenComex = explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'));

            if(in_array($documento->get_configuracion_proveedor['pro_identificacion'], $nitsIntegracionOpenComex) && isset($documento->get_opencomex_cxp_exitoso) && !empty($documento->get_opencomex_cxp_exitoso)) {
                $documento->estado_transmision_opencomex = 'TRANSMISIONOPENCOMEX_EXITOSO';
            } elseif(in_array($documento->get_configuracion_proveedor['pro_identificacion'], $nitsIntegracionOpenComex) && isset($documento->get_opencomex_cxp_fallido) && !empty($documento->get_opencomex_cxp_fallido)) {
                $documento->estado_transmision_opencomex = 'TRANSMISIONOPENCOMEX_FALLIDO';
            } elseif(in_array($documento->get_configuracion_proveedor['pro_identificacion'], $nitsIntegracionOpenComex) && empty($documento->get_opencomex_cxp_exitoso) && empty($documento->get_opencomex_cxp_fallido)) {
                $documento->estado_transmision_opencomex = 'TRANSMISIONOPENCOMEX_SINESTADO';
            }
        }
    }

    /**
     * Elimina propiedades de la colección de un documento.
     * 
     * En las relaciones del documento con los estados, destruye las propiedades que corresponden a cada una de las columnas y que quedan por fuera del array de la relación
     *
     * @param \stdClass $documento Colección del documento en procesamiento
     * @return void
     */
    private function destruirPropiedadesDocumento(\stdClass &$documento): void {
        unset(
            $documento->cdo_informacion_adicional,
            $documento->ofe_identificacion,
            $documento->ofe_razon_social,
            $documento->ofe_nombre_comercial,
            $documento->ofe_primer_apellido,
            $documento->ofe_segundo_apellido,
            $documento->ofe_primer_nombre,
            $documento->ofe_otros_nombres,
            $documento->ofe_nombre_completo,
            $documento->ofe_recepcion_fnc_activo,
            $documento->mon_codigo,
            $documento->mon_descripcion,
            $documento->pro_identificacion,
            $documento->pro_id_personalizado,
            $documento->pro_razon_social,
            $documento->pro_nombre_comercial,
            $documento->pro_primer_apellido,
            $documento->pro_segundo_apellido,
            $documento->pro_primer_nombre,
            $documento->pro_otros_nombres,
            $documento->pro_nombre_completo,
            $documento->mon_codigo,
            $documento->mon_descripcion,
            $documento->mon_id_extranjera,
            $documento->mon_codigo_extranjera,
            $documento->mon_descripcion_extranjera,
            $documento->get_grupos_trabajo_ofe,
            $documento->tde_codigo,
            $documento->tde_descripcion,
            $documento->top_codigo,
            $documento->top_descripcion,
            $documento->gtr_codigo,
            $documento->gtr_nombre,
            $documento->cdo_gtr_estado,
        );

        $this->destruyePropiedadesRelaciones($documento, 'get_documento_aprobado');
        $this->destruyePropiedadesRelaciones($documento, 'get_documento_aprobado_notificacion');
        $this->destruyePropiedadesRelaciones($documento, 'get_documento_rechazado');
        $this->destruyePropiedadesRelaciones($documento, 'get_status_en_proceso');
        $this->destruyePropiedadesRelaciones($documento, 'get_estado_rdi_exitoso');
        $this->destruyePropiedadesRelaciones($documento, 'get_estado_rdi_inconsistencia');
        $this->destruyePropiedadesRelaciones($documento, 'get_aceptado');
        $this->destruyePropiedadesRelaciones($documento, 'get_aceptado_t');
        $this->destruyePropiedadesRelaciones($documento, 'get_rechazado');
        $this->destruyePropiedadesRelaciones($documento, 'get_aceptado_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_aceptado_t_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_rechazado_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_transmision_erp');
        $this->destruyePropiedadesRelaciones($documento, 'get_transmision_erp_exitoso');
        $this->destruyePropiedadesRelaciones($documento, 'get_transmision_erp_excluido');
        $this->destruyePropiedadesRelaciones($documento, 'get_transmision_erp_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_opencomex_cxp_exitoso');
        $this->destruyePropiedadesRelaciones($documento, 'get_opencomex_cxp_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_notificacion_acuse_recibo');
        $this->destruyePropiedadesRelaciones($documento, 'get_notificacion_recibo_bien');
        $this->destruyePropiedadesRelaciones($documento, 'get_notificacion_aceptacion');
        $this->destruyePropiedadesRelaciones($documento, 'get_notificacion_rechazo');
        $this->destruyePropiedadesRelaciones($documento, 'get_notificacion_acuse_recibo_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_notificacion_recibo_bien_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_notificacion_aceptacion_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_notificacion_rechazo_fallido');
        $this->destruyePropiedadesRelaciones($documento, 'get_ultimo_estado_documento');
        $this->destruyePropiedadesRelaciones($documento, 'get_ultimo_estado_validacion');
        $this->destruyePropiedadesRelaciones($documento, 'get_ultimo_estado_validacion_en_proceso_pendiente');
        $this->destruyePropiedadesRelaciones($documento, 'get_validacion_ultimo');
        $this->destruyePropiedadesRelaciones($documento, 'get_ultimo_evento_dian');
    }

    /**
     * Ordenamiento y paginación de los resultados obtenidos.
     *
     * @param Collection $documentos Colección de resultados de documentos
     * @param Request $request Petición recibida
     * @param int $totalDocumentos Total de documentos en la colección sin paginar
     * @return Collection
     */
    private function ordenarPaginar(Collection $documentos, Request $request, int $totalDocumentos): Collection {
        $sortFlag = SORT_REGULAR;
        switch($request->columnaOrden){
            case 'lote':
                $orderBy = 'cdo_lote';
                break;
            case 'clasificacion':
                $orderBy = 'cdo_clasificacion';
                break;
            case 'documento':
                $orderBy = [
                    ['rfa_prefijo', strtolower($request->ordenDireccion)],
                    ['cdo_consecutivo', strtolower($request->ordenDireccion)]
                ];
                $sortFlag = SORT_NUMERIC;
                break;
            case 'valor':
                $orderBy  = 'cdo_valor_a_pagar';
                $sortFlag = SORT_NUMERIC;
                break;
            case 'fecha':
            case 'modificado':
                $orderBy = 'cdo_id';
                break;
            case 'proveedor':
                $orderBy = function($documento, $key) {
                    if(!empty($documento->get_configuracion_proveedor['pro_razon_social']))
                        return $documento->get_configuracion_proveedor['pro_razon_social'];
                    else
                        return str_replace('  ', ' ', trim($documento->get_configuracion_proveedor['pro_primer_nombre']) . ' ' .
                            trim($documento->get_configuracion_proveedor['pro_otros_nombres']) . ' ' .
                            trim($documento->get_configuracion_proveedor['pro_primer_apellido']) . ' ' .
                            trim($documento->get_configuracion_proveedor['pro_segundo_apellido']));
                };
                $sortFlag = SORT_REGULAR;
                break;
            case 'origen':
                $orderBy = 'cdo_origen';
                break;
            case 'moneda':
                $orderBy = function ($documento, $key) {
                    return $documento->get_parametros_moneda['mon_codigo'];
                };
                break;
            case 'estado':
                $orderBy = 'estado';
                break;
            default:
                $orderBy = 'cdo_id';
                break;
        }

        if($request->filled('ordenDireccion') && $request->ordenDireccion == 'ASC')
            $documentos = $documentos->sortBy($orderBy, $sortFlag);
        elseif($request->filled('ordenDireccion') && $request->ordenDireccion == 'DESC')
            $documentos = $documentos->sortByDesc($orderBy, $sortFlag);
        else
            $documentos = $documentos->sortBy('cdo_fecha', $sortFlag);

        $length = ($request->length !== -1) ? $request->length : $totalDocumentos;
        $documentos = $documentos->skip($request->start)
            ->take($length)
            ->values();

        return $documentos;
    }

    /**
     * Retorna el array de columnas que se utilizara por cada documento para escribir la fila correspondiente en el Excel.
     *
     * @return array
     */
    private function columnasDocumentosExcel(): array {
        return [
            'get_configuracion_obligado_facturar_electronicamente.ofe_identificacion',
            'get_configuracion_obligado_facturar_electronicamente.nombre_completo',
            'rfa_resolucion',
            'get_configuracion_proveedor.pro_identificacion',
            'get_configuracion_proveedor.nombre_completo',
            'get_tipo_documento_electronico.tde_codigo',
            'get_tipo_documento_electronico.tde_descripcion',
            'get_tipo_operacion.top_codigo',
            'get_tipo_operacion.top_descripcion',
            'rfa_prefijo',
            'cdo_consecutivo',
            'cdo_fecha',
            'cdo_hora',
            'cdo_vencimiento',
            [
                'do' => function($cdo_observacion) {
                    if($cdo_observacion != '') {
                        $observacion = json_decode($cdo_observacion);
                        if(json_last_error() === JSON_ERROR_NONE && is_array($observacion)) {
                            return str_replace(array("\n","\t"),array(" "," "),substr(implode(' | ', $observacion), 0, 32767));
                        } else {
                            return str_replace(array("\n","\t"),array(" | "," "),substr($cdo_observacion, 0, 32767));
                        }
                    }
                },
                'field' => 'cdo_observacion',
                'validation_type'=> 'callback'
            ],
            'cdo_cufe',
            'get_parametros_moneda.mon_codigo',
            'cdo_valor_sin_impuestos',
            'cdo_impuestos',
            'cdo_cargos',
            'cdo_descuentos',
            'cdo_redondeo',
            'cdo_valor_a_pagar',
            'cdo_anticipo',
            'cdo_retenciones',
            [
                'do' => function($est_informacion_adicional) {
                    if(!empty($est_informacion_adicional)) {
                        $informacionAdicional = json_decode($est_informacion_adicional, true);
                        if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicional) && array_key_exists('inconsistencia', $informacionAdicional) && !empty($informacionAdicional['inconsistencia'])) {
                            return implode(' || ', $informacionAdicional['inconsistencia']);
                        } else {
                            return '';
                        }
                    }
                },
                'field' => 'get_estado_rdi_exitoso.est_informacion_adicional',
                'validation_type'=> 'callback'
            ],
            'cdo_fecha_validacion_dian',
            'estado_dian',
            'resultado_dian',
            'cdo_fecha_acuse',
            'cdo_fecha_recibo_bien',
            'cdo_estado',
            'cdo_fecha_estado',
            'motivo_rechazo',
            'get_transmision_erp.est_inicio_proceso',
            'get_transmision_erp.est_resultado',
            'get_transmision_erp.est_mensaje_resultado',
        ];
    }

    /**
     * Retorna una lista de documentos recibidos de acuerdo a los parámetros recibidos.
     *
     * @param Request $request
     * @return JsonResponse|BinaryFileResponse|Collection
     */
    public function getListaDocumentosRecibidos(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit','2048M');

        if(!$request->filled('tipo_reporte')) {
            $validator = $this->validarConsultaCabecera($request);

            if($validator->fails()) {
                return response()->json([
                    'message' => 'Error en la petición, faltan parámetros',
                    'errors'  => $validator->errors()->all()
                ], ResponseHttp::HTTP_BAD_REQUEST);
            }
        }

        if(($request->filled('excel') && $request->excel) || $request->filled('tipo_reporte')) {
            return $this->obtenerListaDocumentosRecibidos($request);
        } else
            return response()->json(
                $this->obtenerListaDocumentosRecibidos($request),
                ResponseHttp::HTTP_OK
            );
    }

    /**
     * Obtiene una lista de documentos recibidos de acuerdo a los parámetros en el request.
     * 
     * Proceso de consulta relacionado con el tracking de documentos recibidos
     *
     * @param Request $request Petición
     * @return array|Response|Collection Resultados de la consulta
     */
    public function obtenerListaDocumentosRecibidos(Request $request) {
        $totalDocumentos = 0;
        $documentos      = new Collection();

        if(
            $request->filled('cdo_clasificacion') &&
            ($request->cdo_clasificacion == 'DS' || $request->cdo_clasificacion == 'DS_NC') &&
            ($request->filled('estado_acuse_recibo') || $request->filled('estado_recibo_bien') || $request->filled('estado'))
        ) {
            return [
                "total"     => $totalDocumentos,
                "filtrados" => $totalDocumentos,
                "data"      => $documentos
            ];
        }

        $tablasProceso = new \stdClass();
        $tablasProceso->tablaOfes                        = $this->getNombreTabla(ConfiguracionObligadoFacturarElectronicamente::class);
        $tablasProceso->tablaProveedores                 = $this->getNombreTabla(ConfiguracionProveedor::class);
        $tablasProceso->tablaMonedas                     = 'etl_openmain.' . $this->getNombreTabla(ParametrosMoneda::class);
        $tablasProceso->tablaUsuarios                    = 'etl_openmain.' . $this->getNombreTabla(User::class);
        $tablasProceso->tablaGruposTrabajo               = $this->getNombreTabla(ConfiguracionGrupoTrabajo::class);
        $tablasProceso->tablaGruposTrabajoProveedor      = $this->getNombreTabla(ConfiguracionGrupoTrabajoProveedor::class);
        $tablasProceso->tablaTiposOperacion              = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoOperacion::class);
        $tablasProceso->tablaTiposDocumentosElectronicos = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoDocumentoElectronico::class);
        $tablasProceso->idsFormasPago                    = $this->recepcionCabeceraRepository->formasPagoId($request);
        $tablasProceso->requestOfe                       = $this->recepcionCabeceraRepository->getRequestOfe($request->ofe_id);

        $periodos = $this->generarPeriodosParticionamiento($request->cdo_fecha_desde, $request->cdo_fecha_hasta);

        foreach($periodos as $periodo) {
            $tablasProceso->tablaDocs             = 'rep_cabecera_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEstados          = 'rep_estados_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaAnexos           = 'rep_documentos_anexos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaMediosPagoDocs   = 'rep_medios_pago_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaDatosAdicionales = 'rep_datos_adicionales_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);

            if(!$this->recepcionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDocs) || !$this->recepcionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEstados) || !$this->recepcionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaAnexos) || !$this->recepcionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaMediosPagoDocs) || !$this->recepcionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDatosAdicionales))
                continue;

            $consultaDocumentos = $this->recepcionCabeceraRepository->getDocumentosRecibidos($request, $tablasProceso);

            $documentos = $documentos->concat(
                    $consultaDocumentos->map(function($documento) {
                        $this->formatoColumnasNumericas($documento);
                        $this->armarPropiedadesRelaciones($documento);
                        $this->destruirPropiedadesDocumento($documento);

                        return $documento;
                    })
            );
        }

        if (($request->filled('cdo_clasificacion') && ($request->cdo_clasificacion == 'DS' || $request->cdo_clasificacion == 'DS_NC')) || ($request->filled('proceso') && $request->proceso == 'documento_soporte')) {
            $this->encabezadoExcel[0] = "NIT RECEPTOR";
            $this->encabezadoExcel[1] = "RECEPTOR";
            $this->encabezadoExcel[3] = "NIT VENDEDOR";
            $this->encabezadoExcel[5] = "VENDEDOR";
        }

        if($request->filled('tipo_reporte')) {
            return $documentos;
        } elseif($request->filled('excel') && $request->excel) {
            $ofe                     = $this->recepcionCabeceraRepository->excelColumnasPersonalizadasOfe($request);
            $columnasDocumentosExcel = $this->columnasDocumentosExcel();

            if(
                isset($ofe['ofe_columnas_personalizadas']) &&
                !empty($ofe['ofe_columnas_personalizadas']) &&
                array_key_exists('ofe_columnas_personalizadas', $ofe) && 
                array_key_exists('EXCEL', $ofe['ofe_columnas_personalizadas'])
            ) {
                foreach($ofe['ofe_columnas_personalizadas']['EXCEL'] as $columnaExcel) {
                    $this->encabezadoExcel[]   = $columnaExcel['titulo'];
                    $columnasDocumentosExcel[] = 'get_dad_documentos_daop.cdo_informacion_adicional.' . $columnaExcel['campo'];
                }
            }

            if(array_key_exists('ofe_recepcion_fnc_activo', $ofe) && $ofe['ofe_recepcion_fnc_activo'] == 'SI') {
                $this->encabezadoExcel[]   = 'ESTADO VALIDACION';
                $columnasDocumentosExcel[] = 'get_ultimo_estado_validacion.est_resultado';

                // Se obtienen las columnas adicionales configuradas en el evento recibo bien
                if(array_key_exists('ofe_recepcion_fnc_configuracion', $ofe) && !empty($ofe['ofe_recepcion_fnc_configuracion'])) {
                    $ofeRecepcionFncConfiguracion = json_decode($ofe['ofe_recepcion_fnc_configuracion']);
                    if(isset($ofeRecepcionFncConfiguracion->evento_recibo_bien) && !empty($ofeRecepcionFncConfiguracion->evento_recibo_bien)) {

                        foreach ($ofeRecepcionFncConfiguracion->evento_recibo_bien as $value) {
                            array_push($this->encabezadoExcel, strtoupper(str_replace('_', ' ', $this->sanear_string($value->campo))));
                        }

                        $columnasDocumentosExcel[] = 'estado_validacion';
                    }
                }
            }

            return $this->printExcelDocumentos($documentos, $this->encabezadoExcel, $columnasDocumentosExcel, 'documentos_recibidos', ($request->filled('proceso_background') ? $request->proceso_background : false));
        }

        $totalDocumentos = $documentos->count();
        $documentos      = $this->ordenarPaginar($documentos, $request, $totalDocumentos);

        return [
            "total"     => $totalDocumentos,
            "filtrados" => $totalDocumentos,
            "data"      => $documentos
        ];
    }

    /**
     * Lógica para la consulta de un documento de recepción en la data histórica.
     * 
     * Primero se debe verificar en la tabla FAT si el documento existe para poder definir la partición en la que se encuentra.
     *
     * @param int $ofe_id ID del OFE
     * @param int $pro_id ID del Proveedor
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @param int $tde_id ID del tipo de documento electrónico
     * @param array $relaciones Array con los nombres de las relaciones que deben ser consultadas y retornadas con el documento
     * @param string $procesoOrigen Indica el proceso que da origen al llamado del método, sirve para validar e incluir componentes de información
     * @return null|RepCabeceraDocumentoDaop
     */
    public function consultarDocumento(int $ofe_id, int $pro_id = 0, string $prefijo, string $consecutivo, int $tde_id = 0, array $relaciones = [], string $procesoOrigen = '') {
        $docFat = $this->recepcionCabeceraRepository->consultarDocumentoFat($ofe_id, $pro_id, $prefijo, $consecutivo, $tde_id);

        if(is_null($docFat))
            return null;

        $particion = Carbon::parse($docFat->cdo_fecha)->format('Ym');
        return $this->recepcionCabeceraRepository->consultarDocumentoHistorico($particion, $ofe_id, $pro_id, $prefijo, $consecutivo, $tde_id, $relaciones, $procesoOrigen);
    }

    /**
     * Lógica para la consulta de un documento de emisión en la data histórica mediante el cdo_id.
     * 
     * Primero se debe verificar en la tabla FAT si el documento existe para poder definir la partición en la que se encuentra.
     *
     * @param int $cdo_id ID del documento
     * @param array $relaciones Array con los nombres de las relaciones que deben ser consultadas y retornadas con el documento
     * @return null|RepCabeceraDocumentoDaop
     */
    public function consultarDocumentoByCdoId(int $cdo_id, array $relaciones = []) {
        $docFat = $this->recepcionCabeceraRepository->consultarDocumentoFatByCdoId($cdo_id);

        if(is_null($docFat))
            return null;

        $particion = Carbon::parse($docFat->cdo_fecha)->format('Ym');
        return $this->recepcionCabeceraRepository->consultarDocumentoHistorico($particion, $docFat->ofe_id, 0, $docFat->rfa_prefijo, $docFat->cdo_consecutivo, 0, $relaciones);
    }

    /**
     * Obtiene un documento anexo relacionado con un documento electrónico que pertenece a la data histórica.
     *
     * @param int $cdo_id ID del documento electrónico
     * @param int $dan_id ID del documento anexo
     * @return null|\stdObject
     */
    public function obtenerDocumentoAnexoHistorico(int $cdo_id, int $dan_id) {
        $documento = $this->consultarDocumentoByCdoId($cdo_id);

        if(is_null($documento))
            return null;

        $particion = Carbon::parse($documento->cdo_fecha)->format('Ym');
        $getDocumentoAnexo = $this->recepcionCabeceraRepository->obtenerDocumentoAnexoHistorico($documento, $particion, $dan_id);

        if(is_null($getDocumentoAnexo))
            return null;

        return json_decode(json_encode([
            'dan_uuid'        => $getDocumentoAnexo->dan_uuid,
            'dan_nombre'      => $getDocumentoAnexo->dan_nombre,
            'fecha_creacion'  => $getDocumentoAnexo->fecha_creacion->format('Y-m-d H:i:s'),
            'getCabeceraDocumentosDaop' => [
                'cdo_clasificacion' => $documento->cdo_clasificacion,
                'rfa_prefijo'       => $documento->rfa_prefijo,
                'cdo_consecutivo'   => $documento->cdo_consecutivo,
                'getConfiguracionObligadoFacturarElectronicamente' => [
                    'ofe_identificacion' => $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion
                ],
                'getConfiguracionProveedor' => [
                    'pro_identificacion' => $documento->getConfiguracionProveedor->pro_identificacion
                ]
            ]
        ]));
    }

    /**
     * Elimina un documento anexo relacionado con un documento electrónico que pertenece a la data histórica.
     *
     * @param int $cdo_id ID del documento electrónico
     * @param int $dan_id ID del documento anexo
     * @return null|void
     */
    public function eliminarDocumentoAnexoHistorico(int $cdo_id, int $dan_id) {
        $documento = $this->consultarDocumentoByCdoId($cdo_id);

        if(is_null($documento))
            return null;

        $particion = Carbon::parse($documento->cdo_fecha)->format('Ym');
        $this->recepcionCabeceraRepository->eliminarDocumentoAnexoHistorico($particion, $cdo_id, $dan_id);
    }

    /**
     * Consulta información de un documento específico mediante método POST, la consulta incluye el último estado exitoso y el histórico de estados del documento.
     * 
     * Este método es diferente de consultarDocumento en cuanto a que dicho método recibe diferentes parámetros, los cuales llegan mediante query-params
     *
     * @param Request $request Parámetros de la petición
     * @param null|ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE relacionado con la consulta
     * @param null|ConfiguracionProveedor $proveedor Instancia del Proveedor relacionado con la consulta
     * @param int $tde_id ID del tipo de documento electrónico
     * @return null|RepCabeceraDocumentoDaop
     */
    public function consultaDocumentos(Request $request, ConfiguracionObligadoFacturarElectronicamente $ofe = null, ConfiguracionProveedor $proveedor = null, int $tde_id = 0) {
        $relaciones = [
            'getConfiguracionObligadoFacturarElectronicamente',
            'getConfiguracionProveedor',
            'getUltimoEstadoDocumento',
            'getEstadosDocumentosDaop'
        ];

        if($request->filled('cufe')) {
            $docFat = $this->recepcionCabeceraRepository->consultarDocumentoFatByCufe($request->cufe, $request->fecha);

            if(is_null($docFat))
                return null;

            $particion = Carbon::parse($docFat->cdo_fecha)->format('Ym');
            return $this->recepcionCabeceraRepository->consultarDocumentoHistorico($particion, $docFat->ofe_id, $docFat->pro_id, $docFat->rfa_prefijo, $docFat->cdo_consecutivo, $docFat->tde_id, $relaciones);
        } else {
            $docFat = $this->recepcionCabeceraRepository->consultarDocumentoFat($ofe->ofe_id, $proveedor->pro_id, $request->prefijo, $request->consecutivo, $tde_id);

            if(is_null($docFat))
                return null;

            $particion = Carbon::parse($docFat->cdo_fecha)->format('Ym');
            return $this->recepcionCabeceraRepository->consultarDocumentoHistorico($particion, $ofe->ofe_id, $proveedor->pro_id, $docFat->rfa_prefijo, $docFat->cdo_consecutivo, $tde_id, $relaciones);
        }
    }

    /**
     * Obtiene una lista de documentos recibidos de acuerdo a los parámetros en el request.
     * 
     * Proceso de consulta relacionado con el endpoint listar-documentos de recepción
     *
     * @param Request $request Petición
     * @return array Resultados de la consulta
     */
    public function listarDocumentosRecibidos(Request $request): array {
        $documentos = [];

        $tablasProceso = new \stdClass();
        $tablasProceso->tablaOfes                        = $this->getNombreTabla(ConfiguracionObligadoFacturarElectronicamente::class);
        $tablasProceso->tablaProveedores                 = $this->getNombreTabla(ConfiguracionProveedor::class);
        $tablasProceso->tablaGruposTrabajoProveedor      = $this->getNombreTabla(ConfiguracionGrupoTrabajoProveedor::class);
        $tablasProceso->tablaTiposDocumentosElectronicos = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoDocumentoElectronico::class);

        $periodos = $this->generarPeriodosParticionamiento($request->fecha_desde, $request->fecha_hasta);

        foreach($periodos as $periodo) {
            $tablasProceso->tablaDocs = 'rep_cabecera_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);

            if(!$this->recepcionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDocs))
                continue;

            $consultaDocumentos = $this->recepcionCabeceraRepository->listarDocumentosRecibidos($request, $tablasProceso);

            $consultaDocumentos->map(function($documento) use (&$documentos) {
                $documentos[] = [
                    'ofe'         => $documento->ofe_identificacion,
                    'proveedor'   => $documento->pro_identificacion,
                    'tipo'        => $documento->tde_codigo ?? '',
                    'prefijo'     => $documento->rfa_prefijo,
                    'consecutivo' => $documento->cdo_consecutivo,
                    'cufe'        => $documento->cdo_cufe,
                    'fecha'       => $documento->cdo_fecha,
                    'hora'        => $documento->cdo_hora
                ];
            });
        }

        return [
            "data"  => $documentos,
            "total" => count($documentos)
        ];
    }
}
