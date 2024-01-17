<?php

namespace App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop\EtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop\EtlAnticiposDocumentoDaop;
use App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop\EtlMediosPagoDocumentoDaop;
use openEtl\Tenant\Models\Documentos\EtlCabeceraDocumentosDaop\TenantEtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop\EtlImpuestosItemsDocumentoDaop;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop\EtlCargosDescuentosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;
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
        'cdo_consecutivo',
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
        'getDetalleDocumentosDaop',
        'getImpuestosItemsDocumentosDaop',
        'getAnticiposDocumentosDaop',
        'getCargosDescuentosDocumentosDaop',
        'getDadDocumentosDaop',
        'getMediosPagoDocumentosDaop',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionAdquirente',
        'getConfiguracionAutorizado',
        'getConfiguracionResolucionesFacturacion',
        'getAgendamientoUsuario',
        'cantidad_documentos',
        'consecutivo_inicial',
        'consecutivo_final',
        'getEstadosDocumentoDaop',
        'getEdiDocumento',
        'getUblDocumento',
        'getDoFinalizado',
        'getEstadoDo',
        'getNotificacionDocumento',
        'getEnviadoFtpDocumento',
        'getPickupCashDocumento'
    ];

    // RELACIONES

    /**
     * Retorna los items asociados al documento.
     *
     * @return HasMany
     */
    public function getDetalleDocumentosDaop() {
        return $this->hasMany(EtlDetalleDocumentoDaop::class, 'cdo_id');
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
     * Retorna el Oferente al que pertenece el documento.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con Configuración Adquirente - Adquirente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionAdquirente() {
        return $this->belongsTo(ConfiguracionAdquirente::class, 'adq_id');
    }

    /**
     *  Relación con Configuración Adquirente - Autorizado.
     *
     * @return BelongsTo
     */
    public function getConfiguracionAutorizado() {
        return$this->belongsTo(ConfiguracionAdquirente::class, 'adq_id_autorizado');
    }

    /**
     * Retorna la resolución de facturación bajo la cual se rige el documento.
     *
     * @return BelongsTo
     */
    public function getConfiguracionResolucionesFacturacion() {
        return $this->belongsTo(ConfiguracionResolucionesFacturacion::class, 'rfa_id');
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
     * Obtiene la lista de datos adicionales al documento.
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
     * Obtiene la lista de medios de estados asociados al documento.
     *
     * @return HasMany
     */
    public function getEstadosDocumentoDaop() {
        return $this->hasMany(EtlEstadosDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Obtiene el registro de estado EDI procesado de manera exitosa.
     *
     * @return HasOne
     */
    public function getEdiDocumento() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'EDI')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado UBL procesado de manera exitosa.
     *
     * @return HasOne
     */
    public function getUblDocumento() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBL')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
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
     * Obtiene el estado DO más reciente de un documento sin importar si es fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoDo() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado NOTIFICACION procesado de manera exitosa.
     *
     * @return HasOne
     */
    public function getNotificacionDocumento() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTIFICACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }
    
    /**
     * Obtiene el registro de estado ENVIADO-FTP procesado de manera exitosa.
     *
     * @return HasOne
     */
    public function getEnviadoFtpDocumento() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ENVIADO-FTP')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
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
    // FIN RELACIONES

    /**
     * Mutador que permite establecer el valor de la columna rfa_prefijo a vacio.
     *
     * @param string $value
     * @return void
     */
    public function setRfaPrefijoAttribute($value) {
        if($value == '' || $value == null)
            $this->attributes['rfa_prefijo'] = '';
        else
            $this->attributes['rfa_prefijo'] = $value;
    }
}
    