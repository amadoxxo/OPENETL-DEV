<?php

namespace App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Documentos\EtlDocumentosAnexosDaop\EtlDocumentoAnexoDaop;
use openEtl\Main\Models\Parametros\TiposOperacion\MainParametrosTipoOperacion;
use App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop\EtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop\EtlAnticiposDocumentoDaop;
use App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop\EtlMediosPagoDocumentoDaop;
use openEtl\Tenant\Models\Documentos\EtlCabeceraDocumentosDaop\TenantEtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop\EtlImpuestosItemsDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacion;
use App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop\EtlCargosDescuentosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlCabeceraDocumentoDaop extends TenantEtlCabeceraDocumentoDaop {
    protected $visible = [
        'cdo_id',
        'ofe_id',
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
        'getTipoDocumentoElectronico',
        'getTipoOperacion',
        'getParametrosMoneda',
        'getParametrosMonedaExtranjera',
        'getEstadosDocumento',
        'getAdoAgendamiento',
        'getAgendamientoUsuario',
        'getDocumentosAnexos',
        'getEdiDocumento',
        'getUblDocumento',
        'getDoDocumento',
        'getEstadoDo',
        'getEstadoAd',
        'getPickupCashDocumento',
        'getNotificacionDocumento',
        'getEstadoNotificacion',
        'getAceptado',
        'getUblAceptadoT',
        'getAceptadoT',
        'getRechazado',
        'getEstadoContingencia',
        'getEstadoAceptacionT',
        'getEstadoUblAdAceptacionT',
        'getEstadoTransmisionEdm',
        'getEstadosTransmisionEdmFallido'
    ];

    // INICIO RELACIONES
    /**
     * Retorna los items asociados al documento.
     *
     * @return HasMany
     */
    public function getDetalleDocumentosDaop() {
        return $this->hasMany(EtlDetalleDocumentoDaop::class, 'cdo_id')
            ->with(['getImpuestosItemsDocumentosDaop', 'getCargoDescuentosItemsDocumentosDaop']);
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
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id')
            ->with([
                'getParametrosRegimenFiscal',
                'getCodigoPostal',
                'getParametroDomicilioFiscalPais',
                'getParametroDomicilioFiscalDepartamento',
                'getParametroDomicilioFiscalMunicipio',
                'getCodigoPostalDomicilioFiscal']);
    }

    /**
     * Relación con ConfiguracionAdquirente - Adquirente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionAdquirente() {
        return $this->belongsTo(ConfiguracionAdquirente::class, 'adq_id')
            ->with([
                'getCodigoPostal',
                'getParametroDomicilioFiscalPais',
                'getParametroDomicilioFiscalDepartamento',
                'getParametroDomicilioFiscalMunicipio',
                'getCodigoPostalDomicilioFiscal'
            ]);
    }

    /**
     *  Relación con ConfiguracionAdquirente - Autorizado.
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
     * Retorna el tipo de documento Electrónico que posee el documento.
     *
     * @return BelongsTo
     */
    public function getTipoDocumentoElectronico() {
        return $this->belongsTo(ParametrosTipoDocumentoElectronico::class, 'tde_id');
    }

    /**
     * Retorna el tipo de operación que posee el documento.
     *
     * @return BelongsTo
     */
    public function getTipoOperacion() {
        return $this->belongsTo(MainParametrosTipoOperacion::class, 'top_id');
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
     * Obtiene la moneda asociada al documento.
     *
     * @return BelongsTo
     */
    public function getParametrosMoneda() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id');
    }

    /**
     * Obtiene la moneda extranjera asociada al documento.
     *
     * @return BelongsTo
     */
    public function getParametrosMonedaExtranjera() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id_extranjera');
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
     * Retorna la lista de datos adicionales del documento.
     * 
     * @return BelongsTo
     */
    public function getDadDocumentosDaop() {
        return $this->hasOne(EtlDatosAdicionalesDocumentoDaop::class, 'cdo_id');
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
     * Obtiene la lista de estados asociados al documento.
     *
     * @return HasMany
     */
    public function getEstadosDocumento() {
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
     * Obtiene el registro de estado DO procesado de manera exitosa.
     *
     * @return HasOne
     */
    public function getDoDocumento() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado DO más reciente sin importar si fue exitoso o fallido.
     *
     * @return HasOne
     */
    public function getEstadoDo() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'DO')
            ->latest();
    }

    /**
     * Obtiene el registro de estado UBLATTACHEDDOCUMENT más reciente sin importar si fue exitoso o fallido.
     *
     * @return HasOne
     */
    public function getEstadoAd() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLATTACHEDDOCUMENT')
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
     * Obtiene el registro de estado NOTIFICACION mas reciente, sin importar si fue exitoso o fallido.
     *
     * @return HasOne
     */
    public function getEstadoNotificacion() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTIFICACION')
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
            ->select(['est_id', 'cdo_id', 'est_informacion_adicional'])
            ->where('est_estado', 'PICKUP-CASH')
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
     * Obtiene el registro que permite saber que el documento tiene estado UBL aceptado tácitamente.
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
     * Obtiene el registro que permite saber que el documento tiene estado CONTINGENCIA sin importar si fue exitoso o fallido.
     *
     * @return HasOne
     */
    public function getEstadoContingencia() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'CONTINGENCIA')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado ACEPTACIONT sin importar si fue exitoso o fallido.
     *
     * @return HasOne
     */
    public function getEstadoAceptacionT() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado UBLADACEPTACIONT sin importar si fue exitoso o fallido.
     *
     * @return HasOne
     */
    public function getEstadoUblAdAceptacionT() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACEPTACIONT')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado TRANSMISION_EDM Exitoso.
     *
     * @return HasOne
     */
    public function getEstadoTransmisionEdm() {
        return $this->hasOne(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'TRANSMISION_EDM')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene los registros de estados TRANSMISION_EDM Fallidos.
     *
     * @return HasMany
     */
    public function getEstadosTransmisionEdmFallido() {
        return $this->hasMany(EtlEstadosDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'TRANSMISION_EDM')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO');
    }
    // FIN RELACIONES
}
    