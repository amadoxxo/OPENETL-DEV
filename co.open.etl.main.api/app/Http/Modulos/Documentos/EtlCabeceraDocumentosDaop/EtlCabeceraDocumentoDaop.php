<?php

namespace App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop;

use App\Http\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;
use App\Http\Modulos\Documentos\EtlDocumentosAnexosDaop\EtlDocumentoAnexoDaop;
use App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop\EtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop\EtlAnticiposDocumentoDaop;
use App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop\EtlMediosPagoDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEmailCertificationSentDaop\EtlEmailCertificationSentDaop;
use openEtl\Tenant\Models\Documentos\EtlCabeceraDocumentosDaop\TenantEtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop\EtlImpuestosItemsDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop\EtlCargosDescuentosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEventosNotificacionDocumentosDaop\EtlEventoNotificacionDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlCabeceraDocumentoDaop extends TenantEtlCabeceraDocumentoDaop {
    protected $visible = [
        'cdo_id',
        'ofe_id',
        'ofe_id_multiples',
        'adq_id',
        'adq_id_multiples',
        'adq_id_autorizado',
        'rfa_id',
        'cdo_origen',
        'cdo_clasificacion',
        'tde_id',
        'top_id',
        'cdo_lote',
        'rfa_prefijo',
        'consecutivo_inicial',
        'consecutivo_final',
        'cdo_consecutivo',
        'cantidad_documentos',
        'cdo_fecha',
        'cdo_hora',
        'cdo_vencimiento',
        'cdo_observacion',
        'cdo_representacion_grafica_documento',
        'cdo_representacion_grafica_acuse',
        'cdo_documento_referencia',
        'cdo_conceptos_correccion',
        'mon_id',
        'mon_id_extranjera',
        'cdo_trm',
        'cdo_trm_fecha',
        'cdo_envio_dian_moneda_extranjera',
        'cdo_valor_sin_impuestos',
        'cdo_valor_sin_impuestos_moneda_extranjera',
        'cdo_impuestos',
        'cdo_impuestos_moneda_extranjera',
        'cdo_retenciones',
        'cdo_retenciones_moneda_extranjera',
        'cdo_total',
        'cdo_total_moneda_extranjera',
        'cdo_cargos',
        'cdo_cargos_moneda_extranjera',
        'cdo_descuentos',
        'cdo_descuentos_moneda_extranjera',
        'cdo_retenciones_sugeridas',
        'cdo_retenciones_sugeridas_moneda_extranjera',
        'cdo_anticipo',
        'cdo_anticipo_moneda_extranjera',
        'cdo_redondeo',
        'cdo_redondeo_moneda_extranjera',
        'cdo_valor_a_pagar',
        'cdo_valor_a_pagar_moneda_extranjera',
        'cdo_cufe',
        'cdo_qr',
        'cdo_signaturevalue',
        'cdo_procesar_documento',
        'cdo_fecha_procesar_documento',
        'cdo_fecha_validacion_dian',
        'cdo_fecha_inicio_consulta_eventos',
        'cdo_fecha_recibo_bien',
        'cdo_fecha_acuse',
        'cdo_estado',
        'cdo_fecha_estado',
        'cdo_fecha_archivo_salida',
        'cdo_contingencia',
        'cdo_nombre_archivos',
        'cdo_atributos',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getDetalleDocumentosDaop',
        'getEstadosDocumentosDaop',
        'getImpuestosItemsDocumentosDaop',
        'getAnticiposDocumentosDaop',
        'getCargosDescuentosDocumentosDaop',
        'getDadDocumentosDaop',
        'getMediosPagoDocumentosDaop',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionAdquirente',
        'getConfiguracionAutorizado',
        'getConfiguracionResolucionesFacturacion',
        'getAdoAgendamiento',
        'getAgendamientoUsuario',
        'getDocumentosAnexos',
        'getTipoDocumentoElectronico',
        'getTipoOperacion',
        'getParametrosMoneda',
        'getParametrosMonedaExtranjera',
        'getEdiFinalizado',
        'getEdiEnProceso',
        'getUblEnProceso',
        'getUblFinalizado',
        'getUblFallido',
        'getEstadoUbl',
        'getDoEnProceso',
        'getDoFinalizado',
        'getEstadoDo',
        'getUblAttachedDocumentEnProceso',
        'getUblAttachedDocumentFinalizado',
        'getUblAttachedDocumentFallido',
        'getEstadoUblattacheddocument',
        'getNotificacionEnProceso',
        'getNotificacionFinalizado',
        'getNotificacionTamanoSuperior',
        'getEstadoNotificacion',
        'getPickupCashDocumentoExitoso',
        'getPickupCashDocumento',
        'getEstadoPickupCash',
        'getDocumentoAprobado',
        'getDocumentoAprobadoNotificacion',
        'getDocumentoRechazado',
        'getUblAcuseReciboProcesando',
        'getUblAcuseRecibo',
        'getAcuseRecibo',
        'getUblAceptacionProcesando',
        'getUblAceptacion',
        'getAceptado',
        'getAceptadoT',
        'getUblAceptadoT',
        'getAceptadoTEnProceso',
        'getUblAceptadoTEnProceso',
        'getUblRechazoProcesando',
        'getUblRechazo',
        'getRechazado',
        'getEventoNotificacionDocumentoDaop',
        'getEmailCertificationSentDaop'
    ];

    public function getConsecutivoInicial($value){
        return $value ?? 0;
    }

    public function getConsecutivoFinal($value){
        return $value ?? 0;
    }

    public function getCantidadDocumentos($value){
        return $value ?? 0;
    }


    // INICIO RELACIONES
    /**
     * Retorna los items asociados al documento.
     *
     * @return HasMany
     */
    public function getDetalleDocumentosDaop() {
        return $this->hasMany(EtlDetalleDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Retorna los estados asociados al documento.
     *
     * @return HasMany
     */
    public function getEstadosDocumentosDaop() {
        return $this->hasMany(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->orderBy('est_id', 'desc');
    }

    /**
     * Retorna los impuestos asociados al documento.
     *
     * @return HasMany
     */
    public function getImpuestosItemsDocumentosDaop() {
        return $this->hasMany(EtlImpuestosItemsDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Retorna el Oferente al que pertnece el documento.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con Adquirente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionAdquirente() {
        return $this->belongsTo(ConfiguracionAdquirente::class, 'adq_id');
    }

    /**
     * Relación con Moneda.
     *
     * @return BelongsTo
     */
    public function getParametrosMoneda() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id');
    }

    /**
     * Relación con Moneda Extranjera.
     *
     * @return BelongsTo
     */
    public function getParametrosMonedaExtranjera() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id_extranjera');
    }

    /**
     *  Relación con Adquirente (Autorizado).
     *
     * @return BelongsTo
     */
    public function getConfiguracionAutorizado() {
        return$this->belongsTo(ConfiguracionAdquirente::class, 'adq_id_autorizado');
    }

    /**
     * Retorna la resolución de facturacion bajo la cual se rige el documento.
     *
     * @return BelongsTo
     */
    public function getConfiguracionResolucionesFacturacion() {
        return $this->belongsTo(ConfiguracionResolucionesFacturacion::class, 'rfa_id');
    }

    /**
     * Retorna el agendamiento bajo el cual se proceso el documento.
     *
     * @return BelongsTo
     */
    public function getAdoAgendamiento(){
        return $this->belongsTo(AdoAgendamiento::class, 'age_id');
    }

    /**
     * Retorna el usuario que construyo el agendamiento.
     *
     * @return BelongsTo
     */
    public function getAgendamientoUsuario(){
        return $this->belongsTo(User::class, 'age_usu_id');
    }

    /**
     * Retorna la lista de documentos anexos.
     *
     * @return HasMany
     */
    public function getDocumentosAnexos(){
        return $this->hasMany(EtlDocumentoAnexoDaop::class, 'cdo_id');
    }

    /**
     * Retorna la lista de anticipos asociados a un documento.
     *
     * @return HasMany
     */
    public function getAnticiposDocumentosDaop() {
        return $this->hasMany(EtlAnticiposDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Retorna la lista de cargos, descuentos y retenciones sugeridas asociados a un documento.
     *
     * @return HasMany
     */
    public function getCargosDescuentosDocumentosDaop() {
        return $this->hasMany(EtlCargosDescuentosDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Retorna l data adiconal del documento.
     * 
     * @return HasMany
     */
    public function getDadDocumentosDaop() {
        return $this->hasMany(EtlDatosAdicionalesDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Obtiene la lista de medios de pagos asociados al documento.
     *
     * @return HasMany
     */
    public function getMediosPagoDocumentosDaop() {
        return $this->hasMany(EtlMediosPagoDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Obtiene la lista de Tipos de Documentos Electronicos.
     *
     * @return BelongsTo
     */
    public function getTipoDocumentoElectronico() {
        return $this->belongsTo(ParametrosTipoDocumentoElectronico::class, 'tde_id');
    }

    /**
     * Obtiene la lista de Tipo Operacion.
     *
     * @return BelongsTo
     */
    public function getTipoOperacion() {
        return $this->belongsTo(ParametrosTipoOperacion::class, 'top_id');
    }

    /**
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }

    /**
     * Obtiene el registro de estado EDI procesado de manera exitosa y finalizado.
     * 
     * Esta relación especial se requiere para el proceso de modificación de documentos en donde una de las condiciones es que el estado no este finalizado
     *
     * @return HasOne
     */
    public function getEdiFinalizado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'EDI')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado EDI que se encuentra en proceso.
     *
     * @return HasOne
     */
    public function getEdiEnProceso() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'EDI')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro de estado UBL que se encuentra en proceso.
     *
     * @return HasOne
     */
    public function getUblEnProceso() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBL')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro de estado UBL que se encuentra finalizado.
     *
     * @return HasOne
     */
    public function getUblFinalizado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBL')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado UBL que se encuentra fallido.
     *
     * @return HasOne
     */
    public function getUblFallido() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBL')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado UBL del documento.
     *
     * @return HasOne
     */
    public function getEstadoUbl() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBL')
            ->latest();
    }

    /**
     * Obtiene el registro de estado UBLATTACHEDDOCUMENT que se encuentra en proceso o exitoso.
     *
     * @return HasOne
     */
    public function getUblAttachedDocumentEnProceso() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLATTACHEDDOCUMENT')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro de estado UBLATTACHEDDOCUMENT procesado de manera exitosa y finalizado.
     *
     * @return HasOne
     */
    public function getUblAttachedDocumentFinalizado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLATTACHEDDOCUMENT')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado UBLATTACHEDDOCUMENT que se encuentra fallido.
     *
     * @return HasOne
     */
    public function getUblAttachedDocumentFallido() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLATTACHEDDOCUMENT')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado UBLATTACHEDDOCUMENT del documento.
     *
     * @return HasOne
     */
    public function getEstadoUblattacheddocument() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLATTACHEDDOCUMENT')
            ->latest();
    }

    /**
     * Obtiene el registro de estado DO que se encuentra en proceso o exitoso.
     *
     * @return HasOne
     */
    public function getDoEnProceso() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro de estado DO procesado de manera exitosa y finalizado.
     *
     * @return HasOne
     */
    public function getDoFinalizado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado DO del documento.
     *
     * @return HasOne
     */
    public function getEstadoDo() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado NOTIFICACION que se encuentra en proceso o exitoso.
     *
     * @return HasOne
     */
    public function getNotificacionEnProceso() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTIFICACION')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro de estado NOTIFICACION procesado de manera exitosa y finalizado.
     *
     * @return HasOne
     */
    public function getNotificacionFinalizado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTIFICACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro de tamaño superior del archivo notificación.
     *
     * @return HasOne
     */
    public function getNotificacionTamanoSuperior() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTIFICACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->where(function($query) {
                $query->where('est_informacion_adicional', 'like', '%zip adjunto al correo es superior a 2M%');
            })
            ->latest();
    }

    /**
     * Obtiene el último estado NOTIFICACION del documento.
     *
     * @return HasOne
     */
    public function getEstadoNotificacion() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTIFICACION')
            ->latest();
    }

    /**
     * Obtiene el registro de notificación con el tipo de evento delivery.
     *
     * @return HasOne
     */
    public function getNotificacionTipoEvento() {
        return $this->hasOne(EtlEventoNotificacionDocumentoDaop::class, 'cdo_id')
            ->where('evt_evento', 'delivery')
            ->latest();
    }

    /**
     * Obtiene el registro de estado PICKUP-CASH procesado de manera exitosa pero NO finalizado.
     * 
     * Esta relación especial se requiere para el proceso de modificación de documentos en donde una de las condiciones es que el estado no este finalizado
     *
     * @return HasOne
     */
    public function getPickupCashDocumentoExitoso() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'PICKUP-CASH')
            ->where('est_resultado', 'EXITOSO')
            ->whereNull('est_ejecucion')
            ->latest();
    }

    /**
     * Obtiene el registro de estado PICKUP-CASH procesado de manera exitosa.
     *
     * @return HasOne
     */
    public function getPickupCashDocumento() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'PICKUP-CASH')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado PICKUP-CASH.
     *
     * @return HasOne
     */
    public function getEstadoPickupCash() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'PICKUP-CASH')
            ->where('est_resultado', 'EXITOSO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento fue aprobado por la DIAN sin notificación.
     *
     * @return HasOne
     */
    public function getDocumentoAprobado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->where(function($query) {
                $query->where('est_informacion_adicional', 'not like', '%conNotificacion%')
                    ->orWhere('est_informacion_adicional->conNotificacion', 'false');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento fue aprobado por la DIAN con Notificación.
     *
     * @return HasOne
     */
    public function getDocumentoAprobadoNotificacion() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->where('est_informacion_adicional->conNotificacion', 'true')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento fue rechazado por la DIAN.
     *
     * @return HasOne
     */
    public function getDocumentoRechazado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAcuseRecibo que esta en proceso.
     *
     * @return HasOne
     */
    public function getUblAcuseReciboProcesando() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACUSERECIBO')
            ->whereNull('est_resultado')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAcuseRecibo.
     *
     * @return HasOne
     */
    public function getUblAcuseRecibo() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACUSERECIBO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene acuse de recibo.
     *
     * @return HasOne
     */
    public function getAcuseRecibo() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACUSERECIBO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAceptacion que esta en proceso.
     *
     * @return HasOne
     */
    public function getUblAceptacionProcesando() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACEPTACION')
            ->whereNull('est_resultado')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAceptacion.
     *
     * @return HasOne
     */
    public function getUblAceptacion() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACEPTACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado.
     *
     * @return HasOne
     */
    public function getAceptado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado tácitamente.
     *
     * @return HasOne
     */
    public function getUblAceptadoT() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACEPTACIONT')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado tácitamente.
     *
     * @return HasOne
     */
    public function getAceptadoT() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado AceptacionT que esta en proceso.
     *
     * @return HasOne
     */
    public function getAceptadoTEnProceso() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->where('est_ejecucion', 'ENPROCESO')
                    ->orWhereNull('est_ejecucion');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAceptadoT que esta en proceso.
     *
     * @return HasOne
     */
    public function getUblAceptadoTEnProceso() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACEPTACIONT')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->where('est_ejecucion', 'ENPROCESO')
                    ->orWhereNull('est_ejecucion');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAceptacion que esta en proceso.
     *
     * @return HasOne
     */
    public function getUblRechazoProcesando() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECHAZO')
            ->whereNull('est_resultado')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAceptacion.
     *
     * @return HasOne
     */
    public function getUblRechazo() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado rechazado.
     *
     * @return HasOne
     */
    public function getRechazado() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Relación con EtlEventoNotificacionDocumentoDaop.
     *
     * @return HasMany
     */
    public function getEventoNotificacionDocumentoDaop() {
        return $this->hasMany(EtlEventoNotificacionDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Relación con EtlEmailCertificationSentDaop.
     *
     * @return HasMany
     */
    public function getEmailCertificationSentDaop() {
        return $this->hasMany(EtlEmailCertificationSentDaop::class, 'cdo_id');
    }
    // FIN RELACIONES

    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo.
     *
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->where('cdo_lote', 'like', '%'.$texto.'%')
            ->orWhere('cdo_clasificacion', 'like', '%'.$texto.'%')
            ->orWhere('rfa_prefijo', 'like', '%'.$texto.'%')
            ->orWhere('cdo_consecutivo', 'like', '%'.$texto.'%')
            ->orWhereHas('getConfiguracionAdquirente', function ($query2) use ($texto) {
                $query2->where('adq_razon_social', 'like', '%'.$texto.'%')
                    ->orWhere('adq_nombre_comercial', 'like', '%'.$texto.'%')
                    ->orWhere('adq_primer_apellido', 'like', '%'.$texto.'%')
                    ->orWhere('adq_segundo_apellido', 'like', '%'.$texto.'%')
                    ->orWhere('adq_primer_nombre', 'like', '%'.$texto.'%')
                    ->orWhere('adq_otros_nombres', 'like', '%'.$texto.'%')
                    ->orWhereRaw("REPLACE(CONCAT(COALESCE(adq_primer_nombre, ''), ' ', COALESCE(adq_otros_nombres, ''), ' ', COALESCE(adq_primer_apellido, ''), ' ', COALESCE(adq_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $texto . '%']);
            })
            ->orWhere('cdo_fecha', 'like', '%'.$texto.'%')
            ->orWhere('cdo_total', 'like', '%'.$texto.'%')
            ->orWhere('cdo_origen', 'like', '%'.$texto.'%')
            ->orWhere('cdo_valor_a_pagar', 'like', '%'.$texto.'%')
            ->orWhere('etl_cabecera_documentos_daop.estado', $texto)
            ->orWhereRaw('exists (select * from `etl_openmain`.`etl_monedas` where `etl_cabecera_documentos_daop`.`mon_id` = `etl_openmain`.`etl_monedas`.`mon_id` and `mon_codigo` like ?)', [$texto]);
    }

    /**
     * Local Scope que permite realizar una búsqueda sobre determinados campos del modelo, aplica sólo para los documentos rechazados.
     *
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaRechazados($query, $texto) {
        return $query->where( function ($query) use ($texto) {
            $query->where('cdo_lote', 'like', '%'.$texto.'%')
                ->orWhere('cdo_clasificacion', 'like', '%'.$texto.'%')
                ->orWhere('rfa_prefijo', 'like', '%'.$texto.'%')
                ->orWhere('cdo_consecutivo', 'like', '%'.$texto.'%')
                ->orWhereHas('getConfiguracionAdquirente', function ($query2) use ($texto) {
                    $query2->where('adq_razon_social', 'like', '%'.$texto.'%')
                        ->orWhere('adq_nombre_comercial', 'like', '%'.$texto.'%')
                        ->orWhere('adq_primer_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('adq_segundo_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('adq_primer_nombre', 'like', '%'.$texto.'%')
                        ->orWhere('adq_otros_nombres', 'like', '%'.$texto.'%')
                        ->orWhereRaw("REPLACE(CONCAT(COALESCE(adq_primer_nombre, ''), ' ', COALESCE(adq_otros_nombres, ''), ' ', COALESCE(adq_primer_apellido, ''), ' ', COALESCE(adq_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $texto . '%']);
                })
                ->orWhere('cdo_fecha', 'like', '%'.$texto.'%')
                ->orWhere('cdo_total', 'like', '%'.$texto.'%')
                ->orWhere('cdo_origen', 'like', '%'.$texto.'%')
                ->orWhere('cdo_valor_a_pagar', 'like', '%'.$texto.'%')
                ->orWhere('etl_cabecera_documentos_daop.estado', $texto)
                ->orWhereRaw('exists (select * from `etl_openmain`.`etl_monedas` where `etl_cabecera_documentos_daop`.`mon_id` = `etl_openmain`.`etl_monedas`.`mon_id` and `mon_codigo` like ?)', [$texto]);
        });
    }

    /**
     * Local Scope que permite realizar una búsqueda sobre determinados campos del modelo, aplica sólo para los documentos enviados
     *
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaEnviados($query, $texto) {
        return $query->where( function ($query) use ($texto) {
            $query->where('cdo_lote', 'like', '%'.$texto.'%')
                ->orWhere('cdo_clasificacion', 'like', '%'.$texto.'%')
                ->orWhere('rfa_prefijo', 'like', '%'.$texto.'%')
                ->orWhere('cdo_consecutivo', 'like', '%'.$texto.'%')
                ->orWhereHas('getConfiguracionAdquirente', function ($query2) use ($texto) {
                    $query2->where('adq_razon_social', 'like', '%'.$texto.'%')
                        ->orWhere('adq_nombre_comercial', 'like', '%'.$texto.'%')
                        ->orWhere('adq_primer_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('adq_segundo_apellido', 'like', '%'.$texto.'%')
                        ->orWhere('adq_primer_nombre', 'like', '%'.$texto.'%')
                        ->orWhere('adq_otros_nombres', 'like', '%'.$texto.'%')
                        ->orWhereRaw("REPLACE(CONCAT(COALESCE(adq_primer_nombre, ''), ' ', COALESCE(adq_otros_nombres, ''), ' ', COALESCE(adq_primer_apellido, ''), ' ', COALESCE(adq_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $texto . '%']);
                })
                ->orWhere('cdo_fecha', 'like', '%'.$texto.'%')
                ->orWhere('cdo_total', 'like', '%'.$texto.'%')
                ->orWhere('cdo_origen', 'like', '%'.$texto.'%')
                ->orWhere('cdo_valor_a_pagar', 'like', '%'.$texto.'%')
                ->orWhere('etl_cabecera_documentos_daop.estado', $texto)
                ->orWhereRaw('exists (select * from `etl_openmain`.`etl_monedas` where `etl_cabecera_documentos_daop`.`mon_id` = `etl_openmain`.`etl_monedas`.`mon_id` and `mon_codigo` like ?)', [$texto]);
        });
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas.
     *
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'fecha', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'lote':
                $orderBy = 'cdo_lote';
                break;
            case 'clasificacion':
                $orderBy = 'cdo_clasificacion';
                break;
            case 'documento':
                return $query->orderBy('rfa_prefijo', $ordenDireccion)
                    ->orderBy(DB::Raw('abs(cdo_consecutivo)'), $ordenDireccion);
                break;
            case 'valor':
                $orderBy = 'cdo_total';
                break;
            case 'fecha':
            case 'modificado':
                $orderBy = 'cdo_fecha';
                break;
            case 'receptor':
                return $query->join('etl_adquirentes as adquirente', 'adquirente.adq_id', '=', 'etl_cabecera_documentos_daop.adq_id')
                    ->orderBy(DB::Raw('IF(adquirente.adq_razon_social IS NULL OR adquirente.adq_razon_social = "", CONCAT(COALESCE(adquirente.adq_primer_nombre, ""), " ", COALESCE(adquirente.adq_primer_apellido, "")), adquirente.adq_razon_social)'), $ordenDireccion);
                break;
            case 'origen':
                $orderBy = 'cdo_origen';
                break;
            case 'moneda':
                return $query->join('etl_openmain.etl_monedas as monedas', 'monedas.mon_id', '=', 'etl_cabecera_documentos_daop.mon_id')
                    ->orderBy('monedas.mon_codigo', $ordenDireccion);
                break;
            case 'enviado':
                $orderBy = 'age_usu_id';
                break;
            case 'recibo':
                $orderBy = 'cdo_acuse_recibo';
                break;
            case 'estado':
                $orderBy = 'estado';
                break;
            default:
                $orderBy = 'cdo_fecha';
                break;
        }

        if( strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'asc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }

    /**
     * Local Scope que permite incluir en una búsqueda el campo rfa_prefijo dependiendo de si es recibido con un valor o no.
     *
     * @param Builder $query
     * @param string $prefijo
     * @return Builder
     */
    public function scopeIncluyePrefijo($query, $prefijo) {
        if($prefijo != '') {
            return $query->where('rfa_prefijo', $prefijo);
        }

        return $query;
    }
}
    