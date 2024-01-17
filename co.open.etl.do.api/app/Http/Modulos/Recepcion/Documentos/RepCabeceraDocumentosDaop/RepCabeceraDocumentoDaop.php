<?php

namespace App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop;

use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Recepcion\Documentos\RepMediosPagoDocumentosDaop\RepMediosPagoDocumentoDaop;
use openEtl\Tenant\Models\Recepcion\Documentos\RepCabeceraDocumentosDaop\TenantRepCabeceraDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepDatosAdicionalesDocumentosDaop\RepDatoAdicionalDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RepCabeceraDocumentoDaop extends TenantRepCabeceraDocumentoDaop {
    protected $visible = [
        'cdo_id',
        'cdo_origen',
        'cdo_clasificacion',
        'gtr_id',
        'tde_id',
        'top_id',
        'cdo_lote',
        'ofe_id',
        'pro_id',
        'rfa_resolucion',
        'rfa_prefijo',
        'cdo_consecutivo',
        'cdo_fecha',
        'cdo_hora',
        'cdo_vencimiento',
        'cdo_observacion',
        'cdo_documento_referencia',
        'cdo_conceptos_correccion',
        'mon_id',
        'mon_id_extranjera',
        'cdo_trm',
        'cdo_trm_fecha',
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
        'cdo_algoritmo_cufe',
        'cdo_qr',
        'cdo_signaturevalue',
        'cdo_fecha_validacion_dian',
        'cdo_fecha_acuse',
        'cdo_estado',
        'cdo_fecha_estado',
        'cdo_nombre_archivos',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getRdi',
        'getParametrosMoneda',
        'getTipoDocumentoElectronico',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionProveedor',
        'getRepDadDocumentosDaop',
        'getMediosPagoDocumentosDaop',
        'getRepEstadosDocumentoDaop',
        'getGetStatus',
        'getAceptado',
        'getAceptadoT',
        'getRechazado',
        'getReciboBien',
        'getTransmisionErpExitoso',
        'getTransmisionErpFallido',
        'getOpencomexCxp',
        'getOpencomexCxpExitoso',
        'getOpencomexCxpFallido',
        'getEstadoAcuseRecibo',
        'getEstadoUblAdAcuseRecibo',
        'getEstadoReciboBien',
        'getEstadoUblAdReciboBien',
        'getEstadoAceptacion',
        'getEstadoUblAdAceptacion',
        'getEstadoAceptacionT',
        'getEstadoUblAdAceptacionT',
        'getEstadoRechazo',
        'getEstadoUblAdRechazo',
        'getEstadoValidacionEnProcesoPendiente',
        'getUltimoEstadoValidacion',
        'getEstadosDocumento'
    ];

    // RELACIONES
    /**
     * Obtiene el estado RDI más reciente del documento
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getRdi() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RDI')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene la moneda asociada al documento.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getParametrosMoneda() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id');
    }

    /**
     * Retorna el tipo de documento Electronico que posee el documento
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getTipoDocumentoElectronico() {
        return $this->belongsTo(ParametrosTipoDocumentoElectronico::class, 'tde_id');
    }

    /**
     * Retorna el Oferente al que pertnece el documento.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con ConfiguracionProveedor - Proveedor
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getConfiguracionProveedor() {
        return $this->belongsTo(ConfiguracionProveedor::class, 'pro_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getRepDadDocumentosDaop() {
        return $this->hasMany(RepDatoAdicionalDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Obtiene la lista de medios de pagos asociados al documento.
     *
     * @return HasMany
     */
    public function getMediosPagoDocumentosDaop() {
        return $this->hasMany(RepMediosPagoDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Obtiene la lista de medios de estados asociados al documento.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getRepEstadosDocumentoDaop() {
        return $this->hasMany(RepEstadoDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getGetStatus() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'GETSTATUS')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getAceptado() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado tácitamente
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getAceptadoT() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado rechazado
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getRechazado() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado recibo bien
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getReciboBien() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECIBOBIEN')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado TRANSMISIONERP EXITOSO.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getTransmisionErpExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'TRANSMISIONERP')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado TRANSMISIONERP EXCLUIDO.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getTransmisionErpExcluido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'TRANSMISIONERP')
            ->where('est_resultado', 'EXCLUIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Relación de documentos con estado TRANSMISIONERP FALLIDO.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getTransmisionErpFallido() {
        return $this->HasMany(RepEstadoDocumentoDaop::class, 'cdo_id');
    }
    
    /**
     * Obtiene el registro más reciente para el estado OPENCOMEXCXP.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getOpencomexCxp() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'OPENCOMEXCXP')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado OPENCOMEXCXP EXITOSO.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getOpencomexCxpExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'OPENCOMEXCXP')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado OPENCOMEXCXP FALLIDO.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getOpencomexCxpFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'OPENCOMEXCXP')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }


    /**
     * Obtiene el estado ACUSERECIBO mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoAcuseRecibo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACUSERECIBO')
            ->latest();
    }

    /**
     * Obtiene el estado UBLADACUSERECIBO mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoUblAdAcuseRecibo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACUSERECIBO')
            ->latest();
    }

    /**
     * Obtiene el estado RECIBOBIEN mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoReciboBien() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECIBOBIEN')
            ->latest();
    }

    /**
     * Obtiene el estado UBLADRECIBOBIEN mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoUblAdReciboBien() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADRECIBOBIEN')
            ->latest();
    }

    /**
     * Obtiene el estado ACEPTACION mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoAceptacion() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->latest();
    }

    /**
     * Obtiene el estado UBLADACEPTACION mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoUblAdAceptacion() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACEPTACION')
            ->latest();
    }

    /**
     * Obtiene el estado ACEPTACIONT mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoAceptacionT() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->latest();
    }

    /**
     * Obtiene el estado UBLADACEPTACIONT mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoUblAdAceptacionT() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACEPTACIONT')
            ->latest();
    }

    /**
     * Obtiene el estado RECHAZO mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoRechazo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->latest();
    }

    /**
     * Obtiene el estado UBLADRECHAZO mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoUblAdRechazo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADRECHAZO')
            ->latest();
    }

    /**
     * Obtiene el estado VALIDACION más reicente con resultado PENDIENTE o EN PROCESO.
     *
     * @return HasOne
     */
    public function getEstadoValidacionEnProcesoPendiente() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'VALIDACION')
            ->where(function($query) {
                $query->where('est_resultado', 'PENDIENTE')
                    ->orWhere('est_resultado', 'ENPROCESO');
            })
            ->latest('est_id');
    }

    /**
     * Obtiene el último estado VALIDACION de un documento sin importar el resultado del estado.
     *
     * @return HasOne
     */
    public function getUltimoEstadoValidacion() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'VALIDACION')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest('est_id');
    }

    /**
     * Obtiene todos los estados de un documento electrónico.
     *
     * @return HasMany
     */
    public function getEstadosDocumento() {
        return $this->hasMany(RepEstadoDocumentoDaop::class, 'cdo_id');
    }
    // FIN RELACIONES

    // Mutador que permite establecer el valor de la columna rfa_prefijo a null cuando su valor es vacio
    public function setRfaPrefijoAttribute($value) {
        if($value == '')
            $this->attributes['rfa_prefijo'] = '';
        else
            $this->attributes['rfa_prefijo'] = $value;
    }

    // Mutador que permite establecer el valor de la columna mon_id_extranjera a null cuando su valor es vacio
    public function setMonIdExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['mon_id_extranjera'] = null;
        else
            $this->attributes['mon_id_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_valor_sin_impuestos_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoValorSinImpuestosMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_valor_sin_impuestos_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_valor_sin_impuestos_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_impuestos_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoImpuestosMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_impuestos_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_impuestos_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_retenciones_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoRetencionesMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_retenciones_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_retenciones_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_total_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoTotalMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_total_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_total_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_cargos_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoCargosMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_cargos_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_cargos_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_descuentos_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoDescuentosMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_descuentos_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_descuentos_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_retenciones_sugeridas_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoRetencionesSugeridasMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_retenciones_sugeridas_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_retenciones_sugeridas_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_anticipo_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoAnticipoMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_anticipo_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_anticipo_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_redondeo_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoRedondeoMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_redondeo_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_redondeo_moneda_extranjera'] = $value;
    }

    // Mutador que permite establecer el valor de la columna cdo_valor_a_pagar_moneda_extranjera a cero cuando su valor es vacio
    public function setCdoValorAPagarMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_valor_a_pagar_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_valor_a_pagar_moneda_extranjera'] = $value;
    }
}
