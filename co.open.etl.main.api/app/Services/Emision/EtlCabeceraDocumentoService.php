<?php
namespace App\Services\Emision;

use Validator;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use openEtl\Tenant\Traits\TenantDatabase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response as ResponseHttp;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Modulos\Documentos\BaseDocumentosController;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Repositories\Emision\EtlCabeceraDocumentoRepository;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;
use openEtl\Tenant\Traits\Particionamiento\TenantParticionamientoTrait;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEmailCertificationSentDaop\EtlEmailCertificationSentDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlCabeceraDocumentoService extends BaseDocumentosController {
    use TenantDatabase, TenantParticionamientoTrait;

    /**
     * Instancia del repositorio de cabecera en emisión.
     * 
     * Clase encargada de la lógica de consultas frente a la base de datos
     *
     * @var EtlCabeceraDocumentoRepository
     */
    protected $emisionCabeceraRepository;

    /**
     * Nombre de la conexión Tenant por defecto a la base de datos.
     *
     * @var string
     */
    protected $connection = 'conexion01';

    /**
     * Títulos para la fila de encabezado del Excel.
     *
     * @var array
     */
    protected $encabezadoExcel = [
        'NIT EMISOR',
        'EMISOR',
        'RESOLUCION FACTURACION',
        'NIT ADQUIRENTE',
        'ID PERSONALIZADO',
        'ADQUIRENTE',
        'TIPO DOCUMENTO',
        'TIPO OPERACION',
        'PREFIJO',
        'CONSECUTIVO',
        'FECHA DOCUMENTO',
        'HORA DOCUMENTO',
        'FECHA VENCIMIENTO',
        'CUFE',
        'PREFIJO DOCUMENTO REFERENCIA',
        'CONSECUTIVO DOCUMENTO REFERENCIA',
        'FECHA DOCUMENTO REFERENCIA',
        'CUFE DOCUMENTO REFERENCIA',
        'OBSERVACION',
        'MONEDA',
        'TOTAL ANTES DE IMPUESTOS',
        'IMPUESTOS',
        'CARGOS',
        'DESCUENTOS',
        'REDONDEO',
        'TOTAL',
        'ANTICIPOS',
        'RETENCIONES',
        'CORREOS NOTIFICACIÓN',
        'FECHA NOTIFICACIÓN',
        'ESTADO',
        'ESTADO DIAN',
        'RESULTADO DIAN',
        'FECHA INICIO CONSULTA EVENTOS',
        'FECHA ACUSE RECIBO',
        'FECHA RECIBO DE BIEN Y/O PRESTACION DEL SERVICIO',
        'ESTADO DOCUMENTO',
        'FECHA ESTADO',
        'MOTIVO RECHAZO',
        'FECHA ARCHIVO DE SALIDA'
    ];

    public function __construct(EtlCabeceraDocumentoRepository $emisionCabeceraRepository) {
        $this->emisionCabeceraRepository = $emisionCabeceraRepository;
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
            'ofe_id'                => 'required|numeric',
            'cdo_fecha_envio_desde' => 'required|date_format:Y-m-d',
            'cdo_fecha_envio_hasta' => 'required|date_format:Y-m-d',
            'proceso'               => 'required|string',
            'columnaOrden'          => 'required|string',
            'ordenDireccion'        => 'required|string',
            'start'                 => 'required|numeric',
            'length'                => 'required|numeric',
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
     * Arma las propiedades de las relaciones de un documento.
     *
     * @param \stdClass $documento Colección del documento en procesamiento
     * @return void
     */
    private function armarPropiedadesRelaciones(\stdClass &$documento): void {
        $documento->get_configuracion_obligado_facturar_electronicamente = !empty($documento->ofe_id) ? [
            'ofe_id'               => $documento->ofe_id,
            'ofe_identificacion'   => $documento->ofe_identificacion,
            'ofe_razon_social'     => $documento->ofe_razon_social,
            'ofe_nombre_comercial' => $documento->ofe_nombre_comercial,
            'ofe_primer_apellido'  => $documento->ofe_primer_apellido,
            'ofe_segundo_apellido' => $documento->ofe_segundo_apellido,
            'ofe_primer_nombre'    => $documento->ofe_primer_nombre,
            'ofe_otros_nombres'    => $documento->ofe_otros_nombres,
            'nombre_completo'      => str_replace('  ', ' ', trim($documento->ofe_nombre_completo)),
            'ofe_cadisoft_activo'  => isset($documento->ofe_cadisoft_activo) ? $documento->ofe_cadisoft_activo : NULL
        ] : NULL;

        $documento->get_configuracion_adquirente = !empty($documento->adq_id) ? [
            'adq_id'                        => $documento->adq_id,
            'adq_identificacion'            => $documento->adq_identificacion,
            'adq_id_personalizado'          => $documento->adq_id_personalizado,
            'adq_razon_social'              => $documento->adq_razon_social,
            'adq_nombre_comercial'          => $documento->adq_nombre_comercial,
            'adq_primer_apellido'           => $documento->adq_primer_apellido,
            'adq_segundo_apellido'          => $documento->adq_segundo_apellido,
            'adq_primer_nombre'             => $documento->adq_primer_nombre,
            'adq_otros_nombres'             => $documento->adq_otros_nombres,
            'nombre_completo'               => str_replace('  ', ' ', trim($documento->adq_nombre_completo)),
            'adq_informacion_personalizada' => isset($documento->adq_informacion_personalizada) ? $documento->adq_informacion_personalizada : NULL
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

        $documento->get_tipo_operacion = !empty($documento->top_id) ? [
            'top_id'          => $documento->top_id,
            'top_codigo'      => $documento->top_codigo,
            'top_descripcion' => $documento->top_descripcion
        ] : NULL;

        $documento->get_tipo_documento_electronico = !empty($documento->tde_id) ? [
            'tde_id'          => $documento->tde_id,
            'tde_codigo'      => $documento->tde_codigo,
            'tde_descripcion' => $documento->tde_descripcion
        ] : NULL;

        $documento->get_configuracion_resoluciones_facturacion = !empty($documento->rfa_id) ? [
            'rfa_id'         => $documento->resolucion_rfa_id,
            'rfa_tipo'       => $documento->resolucion_rfa_tipo,
            'rfa_prefijo'    => $documento->resolucion_rfa_prefijo,
            'rfa_resolucion' => $documento->resolucion_rfa_resolucion
        ] : NULL;

        $documento->get_documento_aprobado = !empty($documento->get_documento_aprobado_est_id) ? [
            'est_id'                => $documento->get_documento_aprobado_est_id,
            'est_estado'            => $documento->get_documento_aprobado_est_estado,
            'est_resultado'         => $documento->get_documento_aprobado_est_resultado,
            'est_ejecucion'         => $documento->get_documento_aprobado_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_documento_aprobado_est_mensaje_resultado
        ] : NULL;

        $documento->get_documento_aprobado_notificacion = !empty($documento->get_documento_aprobado_notificacion_est_id) ? [
            'est_id'                => $documento->get_documento_aprobado_notificacion_est_id,
            'est_estado'            => $documento->get_documento_aprobado_notificacion_est_estado,
            'est_resultado'         => $documento->get_documento_aprobado_notificacion_est_resultado,
            'est_ejecucion'         => $documento->get_documento_aprobado_notificacion_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_documento_aprobado_notificacion_est_mensaje_resultado
        ] : NULL;

        $documento->get_documento_rechazado = !empty($documento->get_documento_rechazado_est_id) ? [
            'est_id'                => $documento->get_documento_rechazado_est_id,
            'est_estado'            => $documento->get_documento_rechazado_est_estado,
            'est_resultado'         => $documento->get_documento_rechazado_est_resultado,
            'est_ejecucion'         => $documento->get_documento_rechazado_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_documento_rechazado_est_mensaje_resultado
        ] : NULL;

        $documento->get_notificacion_finalizado = !empty($documento->get_notificacion_finalizado_est_id) ? [
            'est_id'                => $documento->get_notificacion_finalizado_est_id,
            'est_estado'            => $documento->get_notificacion_finalizado_est_estado,
            'est_resultado'         => $documento->get_notificacion_finalizado_est_resultado,
            'est_ejecucion'         => $documento->get_notificacion_finalizado_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_notificacion_finalizado_est_mensaje_resultado,
            'est_correos'           => $documento->get_notificacion_finalizado_est_correos,
            'est_fin_proceso'       => $documento->get_notificacion_finalizado_est_fin_proceso,
            'fecha_modificacion'    => $documento->get_notificacion_finalizado_fecha_modificacion
        ] : NULL;

        $documento->get_do_en_proceso = !empty($documento->get_do_en_proceso_est_id) ? [
            'est_id'                => $documento->get_do_en_proceso_est_id,
            'est_estado'            => $documento->get_do_en_proceso_est_estado,
            'est_resultado'         => $documento->get_do_en_proceso_est_resultado,
            'est_ejecucion'         => $documento->get_do_en_proceso_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_do_en_proceso_est_mensaje_resultado
        ] : NULL;

        $documento->get_aceptado = !empty($documento->get_aceptado_est_id) ? [
            'est_id'                => $documento->get_aceptado_est_id,
            'est_estado'            => $documento->get_aceptado_est_estado,
            'est_resultado'         => $documento->get_aceptado_est_resultado,
            'est_ejecucion'         => $documento->get_aceptado_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_aceptado_est_mensaje_resultado
        ] : NULL;

        $documento->get_aceptado_t = !empty($documento->get_aceptado_t_est_id) ? [
            'est_id'                => $documento->get_aceptado_t_est_id,
            'est_estado'            => $documento->get_aceptado_t_est_estado,
            'est_resultado'         => $documento->get_aceptado_t_est_resultado,
            'est_ejecucion'         => $documento->get_aceptado_t_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_aceptado_t_est_mensaje_resultado
        ] : NULL;

        $documento->get_aceptado_t_fallido = !empty($documento->get_aceptado_t_fallido_est_id) ? [
            'est_id'                => $documento->get_aceptado_t_fallido_est_id,
            'est_estado'            => $documento->get_aceptado_t_fallido_est_estado,
            'est_resultado'         => $documento->get_aceptado_t_fallido_est_resultado,
            'est_ejecucion'         => $documento->get_aceptado_t_fallido_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_aceptado_t_fallido_est_mensaje_resultado
        ] : NULL;
            
        $documento->get_rechazado = !empty($documento->get_rechazado_est_id) ? [
            'est_id'                => $documento->get_rechazado_est_id,
            'est_estado'            => $documento->get_rechazado_est_estado,
            'est_resultado'         => $documento->get_rechazado_est_resultado,
            'est_ejecucion'         => $documento->get_rechazado_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_rechazado_est_mensaje_resultado,
            'est_motivo_rechazo'    => !empty($documento->get_rechazado_est_motivo_rechazo) ? json_decode($documento->get_rechazado_est_motivo_rechazo, true) : NULL
        ] : NULL;

        $documento->get_ubl_finalizado = !empty($documento->get_ubl_finalizado_est_id) ? [
            'est_id'                => $documento->get_ubl_finalizado_est_id,
            'est_estado'            => $documento->get_ubl_finalizado_est_estado,
            'est_resultado'         => $documento->get_ubl_finalizado_est_resultado,
            'est_ejecucion'         => $documento->get_ubl_finalizado_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_ubl_finalizado_est_mensaje_resultado
        ] : NULL;

        $documento->get_ubl_fallido = !empty($documento->get_ubl_fallido_est_id) ? [
            'est_id'                => $documento->get_ubl_fallido_est_id,
            'est_estado'            => $documento->get_ubl_fallido_est_estado,
            'est_resultado'         => $documento->get_ubl_fallido_est_resultado,
            'est_ejecucion'         => $documento->get_ubl_fallido_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_ubl_fallido_est_mensaje_resultado
        ] : NULL;

        $documento->get_ubl_en_proceso = !empty($documento->get_ubl_en_proceso_est_id) ? [
            'est_id'                => $documento->get_ubl_en_proceso_est_id,
            'est_estado'            => $documento->get_ubl_en_proceso_est_estado,
            'est_resultado'         => $documento->get_ubl_en_proceso_est_resultado,
            'est_ejecucion'         => $documento->get_ubl_en_proceso_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_ubl_en_proceso_est_mensaje_resultado
        ] : NULL;

        $documento->get_ubl_attached_document_fallido = !empty($documento->get_ubl_attached_document_fallido_est_id) ? [
            'est_id'                => $documento->get_ubl_attached_document_fallido_est_id,
            'est_estado'            => $documento->get_ubl_attached_document_fallido_est_estado,
            'est_resultado'         => $documento->get_ubl_attached_document_fallido_est_resultado,
            'est_ejecucion'         => $documento->get_ubl_attached_document_fallido_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_ubl_attached_document_fallido_est_mensaje_resultado
        ] : NULL;
        
        $documento->get_ultimo_evento_dian = !empty($documento->get_ultimo_evento_dian_est_id) ? [
            'est_id'                => $documento->get_ultimo_evento_dian_est_id,
            'est_estado'            => $documento->get_ultimo_evento_dian_est_estado,
            'est_resultado'         => $documento->get_ultimo_evento_dian_est_resultado,
            'est_ejecucion'         => $documento->get_ultimo_evento_dian_est_ejecucion,
            'est_mensaje_resultado' => $documento->get_ultimo_evento_dian_est_mensaje_resultado
        ] : NULL;
        
        $documento->get_dad_documentos_daop['cdo_informacion_adicional'] = !empty($documento->cdo_informacion_adicional) ? json_decode($documento->cdo_informacion_adicional, true) : [];

        $documento->get_email_certification_sent = !empty($documento->get_email_certification_sent) ? json_decode($documento->get_email_certification_sent, true) : NULL;

        $documento->get_detalle_documentos       = !empty($documento->get_detalle_documentos) ? json_decode($documento->get_detalle_documentos, true) : NULL;

        $documento->get_estados_documentos       = !empty($documento->get_estados_documentos) ? json_decode($documento->get_estados_documentos, true) : NULL;

        $documento->get_cargos_descuentos        = !empty($documento->get_cargos_descuentos) ? json_decode($documento->get_cargos_descuentos, true) : NULL;

        $documento->estado_documento = '';
        if ($documento->get_documento_aprobado)
            $documento->estado_documento = 'APROBADO';
        elseif ($documento->get_documento_aprobado_notificacion)
            $documento->estado_documento = 'APROBADO_NOTIFICACION';
        elseif ($documento->get_documento_rechazado && !$documento->get_documento_aprobado && !$documento->get_documento_aprobado_notificacion)
            $documento->estado_documento = 'RECHAZADO';

        $documento->estado_notificacion = '';
        if ($documento->get_notificacion_finalizado)
            $documento->estado_notificacion = 'NOTIFICACION_EXITOSO';

        $documento->estado_dian = '';
        if ($documento->get_ultimo_evento_dian && ($documento->get_ultimo_evento_dian['est_estado'] == 'ACEPTACION' || 
            $documento->get_ultimo_evento_dian['est_estado'] == 'UBLACEPTACION') && $documento->get_ultimo_evento_dian['est_resultado'] == 'EXITOSO'
        )
            $documento->estado_dian = 'ACEPTACION';
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
        
        $documento->estado_do = '';
        if ($documento->get_documento_aprobado || $documento->get_documento_aprobado_notificacion)
            $documento->estado_do = 'DO_EXITOSO';
        elseif ($documento->get_do_en_proceso)
            $documento->estado_do = 'DO_PROCESO';
        elseif ($documento->get_documento_rechazado && !$documento->get_documento_aprobado && !$documento->get_documento_aprobado_notificacion)
            $documento->estado_do = 'DO_FALLIDO';

        $documento->estado_ubl = '';
        if ($documento->get_ubl_finalizado)
            $documento->estado_ubl = 'UBL_EXITOSO';
        elseif ($documento->get_ubl_fallido && !$documento->get_ubl_finalizado)
            $documento->estado_ubl = 'UBL_FALLIDO';
        elseif ($documento->get_ubl_en_proceso)
            $documento->estado_ubl = 'UBL_PROCESO';

        $documento->estado_attacheddocument = '';
        if ($documento->get_ubl_attached_document_fallido)
            $documento->estado_attacheddocument = 'ATTACHEDDOCUMENT_FALLIDO';
        
        $documento->aplica_documento_anexo = '';
        if (isset($documento->get_documentos_anexos_count) && $documento->get_documentos_anexos_count > 0)
            $documento->aplica_documento_anexo = 'SI';

        $documento->notificacion_tamano_superior = false;
        if ($documento->notificacion_tamano_superior)
            $documento->notificacion_tamano_superior = true;

        $documento->notificacion_tipo_evento = false;
        if ($documento->notificacion_tipo_evento)
            $documento->notificacion_tipo_evento = true;
    }

    /**
     * Elimina propiedades de la colección de un documento.
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
            $documento->ofe_cadisoft_activo,
            $documento->mon_codigo,
            $documento->mon_descripcion,
            $documento->top_codigo,
            $documento->top_descripcion,
            $documento->tde_codigo,
            $documento->tde_descripcion,
            $documento->adq_identificacion,
            $documento->adq_id_personalizado,
            $documento->adq_razon_social,
            $documento->adq_nombre_comercial,
            $documento->adq_primer_apellido,
            $documento->adq_segundo_apellido,
            $documento->adq_primer_nombre,
            $documento->adq_otros_nombres,
            $documento->adq_nombre_completo,
            $documento->mon_codigo,
            $documento->mon_descripcion,
            $documento->mon_id_extranjera,
            $documento->mon_codigo_extranjera,
            $documento->mon_descripcion_extranjera,
            $documento->resolucion_rfa_id,
            $documento->resolucion_rfa_tipo,
            $documento->resolucion_rfa_prefijo,
            $documento->resolucion_rfa_resolucion,
            $documento->get_documento_aprobado_est_id,
            $documento->get_documento_aprobado_est_estado,
            $documento->get_documento_aprobado_est_resultado,
            $documento->get_documento_aprobado_est_ejecucion,
            $documento->get_documento_aprobado_est_mensaje_resultado,
            $documento->get_documento_aprobado_notificacion_est_id,
            $documento->get_documento_aprobado_notificacion_est_estado,
            $documento->get_documento_aprobado_notificacion_est_resultado,
            $documento->get_documento_aprobado_notificacion_est_ejecucion,
            $documento->get_documento_aprobado_notificacion_est_mensaje_resultado,
            $documento->get_documento_rechazado_est_id,
            $documento->get_documento_rechazado_est_estado,
            $documento->get_documento_rechazado_est_resultado,
            $documento->get_documento_rechazado_est_ejecucion,
            $documento->get_documento_rechazado_est_mensaje_resultado,
            $documento->get_notificacion_finalizado_est_id,
            $documento->get_notificacion_finalizado_est_estado,
            $documento->get_notificacion_finalizado_est_resultado,
            $documento->get_notificacion_finalizado_est_ejecucion,
            $documento->get_notificacion_finalizado_est_correos,
            $documento->get_notificacion_finalizado_est_fin_proceso,
            $documento->get_notificacion_finalizado_fecha_modificacion,
            $documento->get_notificacion_finalizado_est_mensaje_resultado,
            $documento->get_do_en_proceso_est_id,
            $documento->get_do_en_proceso_est_estado,
            $documento->get_do_en_proceso_est_resultado,
            $documento->get_do_en_proceso_est_ejecucion,
            $documento->get_do_en_proceso_est_mensaje_resultado,
            $documento->get_aceptado_est_id,
            $documento->get_aceptado_est_estado,
            $documento->get_aceptado_est_resultado,
            $documento->get_aceptado_est_ejecucion,
            $documento->get_aceptado_est_mensaje_resultado,
            $documento->get_aceptado_t_est_id,
            $documento->get_aceptado_t_est_estado,
            $documento->get_aceptado_t_est_resultado,
            $documento->get_aceptado_t_est_ejecucion,
            $documento->get_aceptado_t_est_mensaje_resultado,
            $documento->get_aceptado_t_fallido_est_id,
            $documento->get_aceptado_t_fallido_est_estado,
            $documento->get_aceptado_t_fallido_est_resultado,
            $documento->get_aceptado_t_fallido_est_ejecucion,
            $documento->get_aceptado_t_fallido_est_mensaje_resultado,
            $documento->get_rechazado_est_id,
            $documento->get_rechazado_est_estado,
            $documento->get_rechazado_est_resultado,
            $documento->get_rechazado_est_ejecucion,
            $documento->get_rechazado_est_mensaje_resultado,
            $documento->get_rechazado_est_motivo_rechazo,
            $documento->get_ubl_finalizado_est_id,
            $documento->get_ubl_finalizado_est_estado,
            $documento->get_ubl_finalizado_est_resultado,
            $documento->get_ubl_finalizado_est_ejecucion,
            $documento->get_ubl_finalizado_est_mensaje_resultado,
            $documento->get_ubl_fallido_est_id,
            $documento->get_ubl_fallido_est_estado,
            $documento->get_ubl_fallido_est_resultado,
            $documento->get_ubl_fallido_est_ejecucion,
            $documento->get_ubl_fallido_est_mensaje_resultado,
            $documento->get_ubl_en_proceso_est_id,
            $documento->get_ubl_en_proceso_est_estado,
            $documento->get_ubl_en_proceso_est_resultado,
            $documento->get_ubl_en_proceso_est_ejecucion,
            $documento->get_ubl_en_proceso_est_mensaje_resultado,
            $documento->get_ubl_attached_document_fallido_est_id,
            $documento->get_ubl_attached_document_fallido_est_estado,
            $documento->get_ubl_attached_document_fallido_est_resultado,
            $documento->get_ubl_attached_document_fallido_est_ejecucion,
            $documento->get_ubl_attached_document_fallido_est_mensaje_resultado,
            $documento->get_ultimo_evento_dian_est_id,
            $documento->get_ultimo_evento_dian_est_estado,
            $documento->get_ultimo_evento_dian_est_resultado,
            $documento->get_ultimo_evento_dian_est_ejecucion,
            $documento->get_ultimo_evento_dian_est_mensaje_resultado
        );
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
            case 'receptor':
                $orderBy = function($documento, $key) {
                    if(!empty($documento->get_configuracion_adquirente['adq_razon_social']))
                        return $documento->get_configuracion_adquirente['adq_razon_social'];
                    else
                        return str_replace('  ', ' ', trim($documento->get_configuracion_adquirente['adq_primer_nombre']) . ' ' .
                            trim($documento->get_configuracion_adquirente['adq_otros_nombres']) . ' ' .
                            trim($documento->get_configuracion_adquirente['adq_primer_apellido']) . ' ' .
                            trim($documento->get_configuracion_adquirente['adq_segundo_apellido']));
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
            'get_configuracion_resoluciones_facturacion.rfa_resolucion',
            'get_configuracion_adquirente.adq_identificacion',
            'get_configuracion_adquirente.adq_id_personalizado',
            'get_configuracion_adquirente.nombre_completo',
            'cdo_clasificacion',
            'get_tipo_operacion.top_descripcion',
            'rfa_prefijo',
            'cdo_consecutivo',
            'cdo_fecha',
            'cdo_hora',
            'cdo_vencimiento',
            'cdo_cufe',
            [
                'do' => function($cdo_documento_referencia) {
                    if($cdo_documento_referencia != '') {
                        $documento_referencia = json_decode($cdo_documento_referencia);
                        $prefijo = '';
                        if(json_last_error() === JSON_ERROR_NONE && is_array($documento_referencia)) {
                            foreach($documento_referencia as $referencia) {
                                $prefijo .= (isset($referencia->prefijo) && $referencia->prefijo != '') ? $referencia->prefijo . ', ' : '';
                            }
                            $prefijo = strlen($prefijo) > 2 ? substr($prefijo, 0, -2) : '';
                        }
                        return $prefijo;
                    }
                },
                'field' => 'cdo_documento_referencia',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function($cdo_documento_referencia) {
                    if($cdo_documento_referencia != '') {
                        $documento_referencia = json_decode($cdo_documento_referencia);
                        $consecutivo = '';
                        if(json_last_error() === JSON_ERROR_NONE && is_array($documento_referencia)) {
                            foreach($documento_referencia as $referencia) {
                                $consecutivo .= $referencia->consecutivo.', ';
                            }
                            $consecutivo = substr($consecutivo, 0, -2);
                        }
                        return $consecutivo;
                    }
                },
                'field' => 'cdo_documento_referencia',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function($cdo_documento_referencia) {
                    if($cdo_documento_referencia != '') {
                        $documento_referencia = json_decode($cdo_documento_referencia);
                        $fecha_emision = '';
                        if(json_last_error() === JSON_ERROR_NONE && is_array($documento_referencia)) {
                            foreach($documento_referencia as $referencia) {
                                $fecha_emision .= $referencia->fecha_emision.', ';
                            }
                            $fecha_emision = substr($fecha_emision, 0, -2);
                        }
                        return $fecha_emision;
                    }
                },
                'field' => 'cdo_documento_referencia',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function($cdo_documento_referencia) {
                    if($cdo_documento_referencia != '') {
                        $documento_referencia = json_decode($cdo_documento_referencia);
                        $cufe = '';
                        if(json_last_error() === JSON_ERROR_NONE && is_array($documento_referencia)) {
                            foreach($documento_referencia as $referencia) {
                                $cufe .= $referencia->cufe.', ';
                            }
                            $cufe = substr($cufe, 0, -2);
                        }
                        return $cufe;
                    }
                },
                'field' => 'cdo_documento_referencia',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function($cdo_observacion) {
                    if($cdo_observacion != '') {
                        $observacion = json_decode($cdo_observacion);
                        if(json_last_error() === JSON_ERROR_NONE && is_array($observacion)) {
                            return implode(' | ', $observacion);
                        } else {
                            return $cdo_observacion;
                        }
                    }
                },
                'field' => 'cdo_observacion',
                'validation_type'=> 'callback'
            ],
            'get_parametros_moneda.mon_codigo',
            [
                'do' => function ($cdo_valor_sin_impuestos, $extra_data) {
                    return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_valor_sin_impuestos_moneda_extranjera'] : $cdo_valor_sin_impuestos;
                },
                'extra_data' => [
                    'cdo_valor_sin_impuestos_moneda_extranjera',
                    'get_parametros_moneda.mon_codigo',
                ],
                'field' => 'cdo_valor_sin_impuestos',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function ($cdo_impuestos, $extra_data) {
                    return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_impuestos_moneda_extranjera'] : $cdo_impuestos;
                },
                'extra_data' => [
                    'cdo_impuestos_moneda_extranjera',
                    'get_parametros_moneda.mon_codigo',
                ],
                'field' => 'cdo_impuestos',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function ($cdo_cargos, $extra_data) {
                    if($cdo_cargos > 0 || $extra_data['cdo_cargos_moneda_extranjera'] > 0)
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_cargos_moneda_extranjera'] : $cdo_cargos;
                    else
                        return '';
                },
                'extra_data' => [
                    'cdo_cargos_moneda_extranjera',
                    'get_parametros_moneda.mon_codigo',
                ],
                'field' => 'cdo_cargos',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function ($cdo_descuentos, $extra_data) {
                    if($extra_data['get_parametros_moneda.mon_codigo'] != 'COP')
                        $descuentos = number_format($extra_data['cdo_descuentos_moneda_extranjera'] + $extra_data['cdo_retenciones_sugeridas_moneda_extranjera'], 2, '.', '');
                    else
                        $descuentos = number_format($cdo_descuentos + $extra_data['cdo_retenciones_sugeridas'], 2, '.', '');

                    return $descuentos > 0 ? $descuentos : '';
                },
                'extra_data' => [
                    'cdo_retenciones_sugeridas',
                    'cdo_retenciones_sugeridas_moneda_extranjera',
                    'cdo_descuentos_moneda_extranjera',
                    'get_parametros_moneda.mon_codigo',
                ],
                'field' => 'cdo_descuentos',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function ($cdo_redondeo, $extra_data) {
                    if($cdo_redondeo != 0 || $extra_data['cdo_redondeo_moneda_extranjera'] != 0)
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_redondeo_moneda_extranjera'] : $cdo_redondeo;
                    else
                        return '';
                },
                'extra_data' => [
                    'cdo_redondeo_moneda_extranjera',
                    'get_parametros_moneda.mon_codigo',
                ],
                'field' => 'cdo_redondeo',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function ($cdo_valor_sin_impuestos, $extra_data) {
                    return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_valor_a_pagar_moneda_extranjera'] : $cdo_valor_sin_impuestos;
                },
                'extra_data' => [
                    'cdo_valor_a_pagar_moneda_extranjera',
                    'get_parametros_moneda.mon_codigo',
                ],
                'field' => 'cdo_valor_a_pagar',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function ($cdo_anticipo, $extra_data) {
                    if($cdo_anticipo > 0 || $extra_data['cdo_anticipo_moneda_extranjera'] > 0)
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_anticipo_moneda_extranjera'] : $cdo_anticipo;
                    else
                        return '';
                },
                'extra_data' => [
                    'cdo_anticipo_moneda_extranjera',
                    'get_parametros_moneda.mon_codigo',
                ],
                'field' => 'cdo_anticipo',
                'validation_type'=> 'callback'
            ],
            [
                'do' => function ($cdo_retenciones, $extra_data) {
                    if($cdo_retenciones > 0 || $extra_data['cdo_retenciones_moneda_extranjera'] > 0)
                        return ($extra_data['get_parametros_moneda.mon_codigo'] != 'COP') ? $extra_data['cdo_retenciones_moneda_extranjera'] : $cdo_retenciones;
                    else
                        return '';
                },
                'extra_data' => [
                    'cdo_retenciones_moneda_extranjera',
                    'get_parametros_moneda.mon_codigo',
                ],
                'field' => 'cdo_retenciones',
                'validation_type'=> 'callback'
            ],
            'get_notificacion_finalizado.est_correos',
            'get_notificacion_finalizado.est_fin_proceso',
            'estado',
            'estado_dian',
            'resultado_dian',
            'cdo_fecha_inicio_consulta_eventos',
            'cdo_fecha_acuse',
            'cdo_fecha_recibo_bien',
            'cdo_estado',
            'cdo_fecha_estado',
            'get_rechazado.est_motivo_rechazo.motivo_rechazo',
            'cdo_fecha_archivo_salida'
        ];
    }

    /**
     * Obtiene una lista de documentos enviados de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @return array|Response|Collection Resultados de la consulta
     */
    public function obtenerListaDocumentosEnviados(Request $request) {
        $documentos = new Collection();

        $tablasProceso = new \stdClass();
        $tablasProceso->tablaOfes           = $this->getNombreTabla(ConfiguracionObligadoFacturarElectronicamente::class);
        $tablasProceso->tablaAdqs           = $this->getNombreTabla(ConfiguracionAdquirente::class);
        $tablasProceso->tablaMonedas        = 'etl_openmain.' . $this->getNombreTabla(ParametrosMoneda::class);
        $tablasProceso->tablaUsuarios       = 'etl_openmain.' . $this->getNombreTabla(User::class);
        $tablasProceso->tablaResoluciones   = $this->getNombreTabla(ConfiguracionResolucionesFacturacion::class);
        $tablasProceso->tablaTiposOperacion = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoOperacion::class);
        $tablasProceso->idsFormasPago       = $this->emisionCabeceraRepository->formasPagoId($request);

        if($request->filled('tipo_reporte'))
            $periodos = $this->generarPeriodosParticionamiento($request->fecha_creacion_desde, $request->fecha_creacion_hasta);
        else
            $periodos = $this->generarPeriodosParticionamiento($request->cdo_fecha_envio_desde, $request->cdo_fecha_envio_hasta);

        foreach($periodos as $periodo) {
            $tablasProceso->tablaDocs             = 'etl_cabecera_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEstados          = 'etl_estados_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaAnexos           = 'etl_documentos_anexos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEventosNot       = 'etl_eventos_notificacion_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            
            $tablasProceso->tablaDatosAdicionales = 'etl_datos_adicionales_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            
            $tablasProceso->tablaMediosPagoDocs   = 'etl_medios_pago_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo); 

            if(!$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDocs) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEstados) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaAnexos) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEventosNot) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDatosAdicionales) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDatosAdicionales)  || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaMediosPagoDocs))
                continue;

            $consultaDocumentos = $this->emisionCabeceraRepository->getDocumentosEnviados($request, $tablasProceso);

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
            $ofe                     = $this->emisionCabeceraRepository->excelColumnasPersonalizadasOfe($request);
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

            return $this->printExcelDocumentos($documentos, $this->encabezadoExcel, $columnasDocumentosExcel, 'documentos_enviados', ($request->filled('proceso_background') ? $request->proceso_background : false));
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
     * Retorna una lista de documentos enviados a la DIAN de acuerdo a los parámetros recibidos.
     *
     * @param Request $request
     * @return JsonResponse|BinaryFileResponse|Collection
     */
    public function getListaDocumentosEnviados(Request $request) {
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
            return $this->obtenerListaDocumentosEnviados($request);
        } else
            return response()->json(
                $this->obtenerListaDocumentosEnviados($request),
                ResponseHttp::HTTP_OK
            );
    }

    /**
     * Obtiene una lista de documentos procesados de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @return Collection Resultados de la consulta
     */
    public function obtenerListaDocumentosProcesados(Request $request): Collection {
        $documentosProcesados = new Collection();

        $tablasProceso = new \stdClass();
        $tablasProceso->tablaOfes           = $this->getNombreTabla(ConfiguracionObligadoFacturarElectronicamente::class);
        $tablasProceso->tablaAdqs           = $this->getNombreTabla(ConfiguracionAdquirente::class);
        $tablasProceso->tablaMonedas        = 'etl_openmain.' . $this->getNombreTabla(ParametrosMoneda::class);
        $tablasProceso->tablaUsuarios       = 'etl_openmain.' . $this->getNombreTabla(User::class);
        $tablasProceso->tablaResoluciones   = $this->getNombreTabla(ConfiguracionResolucionesFacturacion::class);
        $tablasProceso->tablaTiposOperacion = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoOperacion::class);

        if($request->filled('tipo_reporte'))
            $periodos = $this->generarPeriodosParticionamiento($request->fecha_creacion_desde, $request->fecha_creacion_hasta);
        else
            $periodos = $this->generarPeriodosParticionamiento($request->cdo_fecha_envio_desde, $request->cdo_fecha_envio_hasta);

        foreach($periodos as $periodo) {
            $tablasProceso->tablaDocs    = 'etl_cabecera_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEstados = 'etl_estados_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);          

            if(!$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDocs) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEstados))
                continue;

            if ($request->filled('tipo_reporte') && $request->tipo_reporte == 'eventos_procesados')
                $listaDocumentos = $this->emisionCabeceraRepository->getListaEventosProcesados($request, $tablasProceso);
            else
                $listaDocumentos = $this->emisionCabeceraRepository->getListaDocumentosProcesados($request, $tablasProceso);
                
            $documentosProcesados = $documentosProcesados->concat(
                    $listaDocumentos->map(function($documento) {
                        $this->formatoColumnasNumericas($documento);
                        $this->armarPropiedadesRelaciones($documento);
                        $this->destruirPropiedadesDocumento($documento);

                        return $documento;
                    })
            );
        }

        return $documentosProcesados;
    }

    /**
     * Obtiene una lista de notificación de documentos mediante SMTP de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @return Collection Resultados de la consulta
     */
    public function obtenerListaEmailCertificationSent(Request $request): Collection {
        $documentosEmailCertificationSent = new Collection();

        $tablasProceso = new \stdClass();
        $tablasProceso->tablaOfes                        = $this->getNombreTabla(ConfiguracionObligadoFacturarElectronicamente::class);
        $tablasProceso->tablaAdqs                        = $this->getNombreTabla(ConfiguracionAdquirente::class);
        $tablasProceso->tablaResoluciones                = $this->getNombreTabla(ConfiguracionResolucionesFacturacion::class);
        $tablasProceso->tablaEmailCertificationSent      = $this->getNombreTabla(EtlEmailCertificationSentDaop::class);
        $tablasProceso->tablaTiposOperacion              = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoOperacion::class);
        $tablasProceso->tablaTiposDocumentosElectronicos = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoDocumentoElectronico::class);

        $periodos = $this->generarPeriodosParticionamiento($request->cdo_fecha_envio_desde, $request->cdo_fecha_envio_hasta);

        foreach($periodos as $periodo) {
            $tablasProceso->tablaDocs                   = 'etl_cabecera_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEstados                = 'etl_estados_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);          
            $tablasProceso->tablaEmailCertificationSent = 'etl_email_certification_sent_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);          

            if(!$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDocs) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEstados) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEmailCertificationSent))
                continue;

            $listaDocumentos = $this->emisionCabeceraRepository->getListaEmailCertificationSent($request, $tablasProceso);

            $documentosEmailCertificationSent = $documentosEmailCertificationSent->concat(
                    $listaDocumentos->map(function($documento) {
                        $this->formatoColumnasNumericas($documento);
                        $this->armarPropiedadesRelaciones($documento);
                        $this->destruirPropiedadesDocumento($documento);

                        return $documento;
                    })
            );
        }

        return $documentosEmailCertificationSent;
    }

    /**
     * Obtiene una lista de documentos enviados de DHL Express de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @return Collection Resultados de la consulta
     */
    public function obtenerListaDocumentosEnviadosDhlExpress(Request $request) {
        $documentosEnviadosDhlExpress = new Collection();

        $tablasProceso = new \stdClass();
        $tablasProceso->tablaOfes           = $this->getNombreTabla(ConfiguracionObligadoFacturarElectronicamente::class);
        $tablasProceso->tablaAdqs           = $this->getNombreTabla(ConfiguracionAdquirente::class);
        $tablasProceso->tablaMonedas        = 'etl_openmain.' . $this->getNombreTabla(ParametrosMoneda::class);
        $tablasProceso->tablaUsuarios       = 'etl_openmain.' . $this->getNombreTabla(User::class);
        $tablasProceso->tablaResoluciones   = $this->getNombreTabla(ConfiguracionResolucionesFacturacion::class);
        $tablasProceso->tablaTiposOperacion = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoOperacion::class);

        $periodos = $this->generarPeriodosParticionamiento($request->cdo_fecha_envio_desde, $request->cdo_fecha_envio_hasta);

        foreach($periodos as $periodo) {
            $tablasProceso->tablaDocs             = 'etl_cabecera_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEstados          = 'etl_estados_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaAnexos           = 'etl_documentos_anexos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEventosNot       = 'etl_eventos_notificacion_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            
            $tablasProceso->tablaDatosAdicionales = 'etl_datos_adicionales_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            

            if(!$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDocs) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEstados) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaAnexos) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEventosNot) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDatosAdicionales))
                continue;

            $consultaDocumentos = $this->emisionCabeceraRepository->getDocumentosEnviadosDhlExpress($request, $tablasProceso);

            $documentosEnviadosDhlExpress = $documentosEnviadosDhlExpress->concat(
                    $consultaDocumentos->map(function($documento) {
                        $this->formatoColumnasNumericas($documento);
                        $this->armarPropiedadesRelaciones($documento);
                        $this->destruirPropiedadesDocumento($documento);

                        return $documento;
                    })
            );
        }
        
        return $documentosEnviadosDhlExpress;
    }

    /**
     * Obtiene una lista de documentos de DHL Express Facturación Manual Pickup Cash de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @return Collection Resultados de la consulta
     */
    public function obtenerListaDocumentosDhlExpressFacturacionManualPickupCash(Request $request) {
        $documentosFacturacionManualPickupCash = new Collection();

        $tablasProceso = new \stdClass();
        $tablasProceso->tablaOfes           = $this->getNombreTabla(ConfiguracionObligadoFacturarElectronicamente::class);
        $tablasProceso->tablaAdqs           = $this->getNombreTabla(ConfiguracionAdquirente::class);
        $tablasProceso->tablaMonedas        = 'etl_openmain.' . $this->getNombreTabla(ParametrosMoneda::class);
        $tablasProceso->tablaUsuarios       = 'etl_openmain.' . $this->getNombreTabla(User::class);
        $tablasProceso->tablaResoluciones   = $this->getNombreTabla(ConfiguracionResolucionesFacturacion::class);
        $tablasProceso->tablaTiposOperacion = 'etl_openmain.' . $this->getNombreTabla(ParametrosTipoOperacion::class);
        $tablasProceso->tablaTributos       = 'etl_openmain.' . $this->getNombreTabla(ParametrosTributo::class);

        $periodos = $this->generarPeriodosParticionamiento($request->cdo_fecha_envio_desde, $request->cdo_fecha_envio_hasta);

        foreach($periodos as $periodo) {
            $tablasProceso->tablaDocs             = 'etl_cabecera_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEstados          = 'etl_estados_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaAnexos           = 'etl_documentos_anexos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);
            $tablasProceso->tablaEventosNot       = 'etl_eventos_notificacion_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            
            $tablasProceso->tablaDatosAdicionales = 'etl_datos_adicionales_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            
            $tablasProceso->tablaDetalle          = 'etl_detalle_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            
            $tablasProceso->tablaCargosDescuentos = 'etl_cargos_descuentos_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            
            $tablasProceso->tablaImpuestosItems   = 'etl_impuestos_items_documentos_' . (empty($periodo) || $periodo == date('Ym') ? 'daop' : $periodo);            

            if(!$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDocs) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEstados) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaAnexos) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaEventosNot) || !$this->emisionCabeceraRepository->existeTabla($this->connection, $tablasProceso->tablaDatosAdicionales))
                continue;

            $consultaDocumentos = $this->emisionCabeceraRepository->getDocumentosDhlExpressFacturacionManualPickupCash($request, $tablasProceso);

            $documentosFacturacionManualPickupCash = $documentosFacturacionManualPickupCash->concat(
                    $consultaDocumentos->map(function($documento) {
                        $this->formatoColumnasNumericas($documento);
                        $this->armarPropiedadesRelaciones($documento);
                        $this->destruirPropiedadesDocumento($documento);

                        return $documento;
                    })
            );
        }
        
        return $documentosFacturacionManualPickupCash;
    }

    /**
     * Lógica para la consulta de un documento de emisión en la data histórica.
     * 
     * Primero se debe verificar en la tabla FAT si el documento existe para poder definir la partición en la que se encuentra.
     *
     * @param int $ofe_id ID del OFE
     * @param int $adq_id ID del Adquirente
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @param int $tde_id ID del tipo de documento electrónico
     * @param array $relaciones Array con los nombres de las relaciones que deben ser consultadas y retornadas con el documento
     * @param string $procesoOrigen Indica el proceso que da origen al llamado del método, sirve para validar e incluir componentes de información
     * @return null|EtlCabeceraDocumentoDaop
     */
    public function consultarDocumento(int $ofe_id, int $adq_id = 0, string $prefijo, string $consecutivo, int $tde_id = 0, array $relaciones = [], string $procesoOrigen = '') {
        $docFat = $this->emisionCabeceraRepository->consultarDocumentoFat($ofe_id, $adq_id, $prefijo, $consecutivo, $tde_id);

        if(is_null($docFat))
            return null;

        $particion = Carbon::parse($docFat->cdo_fecha_validacion_dian)->format('Ym');
        return $this->emisionCabeceraRepository->consultarDocumentoHistorico($particion, $ofe_id, $adq_id, $prefijo, $consecutivo, $tde_id, $relaciones, $procesoOrigen);
    }

    /**
     * Lógica para la consulta de un documento de emisión en la data histórica mediante el cdo_id.
     * 
     * Primero se debe verificar en la tabla FAT si el documento existe para poder definir la partición en la que se encuentra.
     *
     * @param int $cdo_id ID del documento
     * @param array $relaciones Array con los nombres de las relaciones que deben ser consultadas y retornadas con el documento
     * @return null|EtlCabeceraDocumentoDaop
     */
    public function consultarDocumentoByCdoId(int $cdo_id, array $relaciones = []) {
        $docFat = $this->emisionCabeceraRepository->consultarDocumentoFatByCdoId($cdo_id);

        if(is_null($docFat))
            return null;

        $particion = Carbon::parse($docFat->cdo_fecha_validacion_dian)->format('Ym');
        return $this->emisionCabeceraRepository->consultarDocumentoHistorico($particion, $docFat->ofe_id, 0, $docFat->rfa_prefijo, $docFat->cdo_consecutivo, 0, $relaciones);
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

        $particion = Carbon::parse($documento->cdo_fecha_validacion_dian)->format('Ym');
        $getDocumentoAnexo = $this->emisionCabeceraRepository->obtenerDocumentoAnexoHistorico($documento, $particion, $dan_id);

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
                'getConfiguracionAdquirente' => [
                    'adq_identificacion' => $documento->getConfiguracionAdquirente->adq_identificacion
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

        $particion = Carbon::parse($documento->cdo_fecha_validacion_dian)->format('Ym');
        $this->emisionCabeceraRepository->eliminarDocumentoAnexoHistorico($particion, $cdo_id, $dan_id);
    }
}
