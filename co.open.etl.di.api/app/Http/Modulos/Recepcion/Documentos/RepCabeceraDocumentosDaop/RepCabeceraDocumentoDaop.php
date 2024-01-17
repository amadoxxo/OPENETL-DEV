<?php

namespace App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionProveedor',
        'getRepDadDocumentosDaop',
        'getRepEstadosDocumentoDaop',
        'getMediosPagoDocumentosDaop',
        'getRdi',
        'getGetStatus',
        'getAcuseRecibo',
        'getReciboBien',
        'getAceptado',
        'getAceptadoT',
        'getRechazado',
        'getTipoDocumentoElectronico'
    ];

    // RELACIONES
    /**
     * Retorna el Oferente al que pertenece el documento.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con Configuración Proveedor.
     *
     * @return BelongsTo
     */
    public function getConfiguracionProveedor() {
        return $this->belongsTo(ConfiguracionProveedor::class, 'pro_id');
    }

    /**
     * Obtiene la lista de datos adicionales del documento.
     *
     * @return HasMany
     */
    public function getRepDadDocumentosDaop() {
        return $this->hasMany(RepDatoAdicionalDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Obtiene la lista de medios de estados asociados al documento.
     *
     * @return HasMany
     */
    public function getRepEstadosDocumentoDaop() {
        return $this->hasMany(RepEstadoDocumentoDaop::class, 'cdo_id');
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
     * Obtiene el estado RDI más reciente del documento.
     *
     * @return HasOne
     */
    public function getRdi() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RDI')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento existe en la DIAN.
     *
     * @return HasOne
     */
    public function getGetStatus() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'GETSTATUS')
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
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACUSERECIBO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene recibo de bien.
     *
     * @return HasOne
     */
    public function getReciboBien() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECIBOBIEN')
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
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
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
    public function getAceptadoT() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
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
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el tipo de Documento Electrónico.
     *
     * @return BelongsTo
     */
    public function getTipoDocumentoElectronico() {
        return $this->belongsTo(ParametrosTipoDocumentoElectronico::class, 'tde_id');
    }
    // FIN RELACIONES

    /**
     * Mutador que permite establecer el valor de la columna rfa_prefijo a null cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setRfaPrefijoAttribute($value) {
        if($value == '')
            $this->attributes['rfa_prefijo'] = '';
        else
            $this->attributes['rfa_prefijo'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna mon_id_extranjera a null cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setMonIdExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['mon_id_extranjera'] = null;
        else
            $this->attributes['mon_id_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_valor_sin_impuestos_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoValorSinImpuestosMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_valor_sin_impuestos_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_valor_sin_impuestos_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_impuestos_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoImpuestosMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_impuestos_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_impuestos_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_retenciones_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoRetencionesMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_retenciones_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_retenciones_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_total_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoTotalMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_total_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_total_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_cargos_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoCargosMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_cargos_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_cargos_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_descuentos_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoDescuentosMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_descuentos_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_descuentos_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_retenciones_sugeridas_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoRetencionesSugeridasMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_retenciones_sugeridas_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_retenciones_sugeridas_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_anticipo_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoAnticipoMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_anticipo_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_anticipo_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_redondeo_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoRedondeoMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_redondeo_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_redondeo_moneda_extranjera'] = $value;
    }

    /**
     * Mutador que permite establecer el valor de la columna cdo_valor_a_pagar_moneda_extranjera a cero cuando su valor es vacio.
     *
     * @param string|int|float $value
     * @return void
     */
    public function setCdoValorAPagarMonedaExtranjeraAttribute($value) {
        if($value == '')
            $this->attributes['cdo_valor_a_pagar_moneda_extranjera'] = 0.00;
        else
            $this->attributes['cdo_valor_a_pagar_moneda_extranjera'] = $value;
    }
}
