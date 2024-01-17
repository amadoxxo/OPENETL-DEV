<?php

namespace App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop;

use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepDocumentosAnexosDaop\RepDocumentoAnexoDaop;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
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
        'cdo_fecha_recibo_bien',
        'cdo_fecha_acuse',
        'cdo_estado',
        'cdo_fecha_estado',
        'cdo_nombre_archivos',
        'cdo_usuario_responsable',
        'cdo_usuario_responsable_recibidos',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioResponsable',
        'getUsuarioResponsableRecibidos',
        'getDadDocumentosDaop',
        'getMediosPagoDocumentosDaop',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionProveedor',
        'getParametrosMoneda',
        'getParametrosMonedaExtranjera',
        'getTipoOperacion',
        'getTipoDocumentoElectronico',
        'getEstadosDocumentosDaop',
        'getUltimoEstadoDocumento',
        'getEstadosAsignarDependencia',
        'getEstadosValidacion',
        'getEstadoValidacionValidado',
        'getUltimoEstadoValidacion',
        'getEstadosValidacionEnProcesoPendiente',
        'getEstadoValidacionEnProcesoPendiente',
        'getUltimoEstadoValidacionEnProcesoPendiente',
        'getUltimoEstadoExitoso',
        'getEstadoRdiExitoso',
        'getEstadoRdiEnProceso',
        'getDocumentoAprobado',
        'getDocumentoAprobadoNotificacion',
        'getDocumentoRechazado',
        'getUblAcuseRecibo',
        'getUblAcuseReciboEnProceso',
        'getUblAcuseReciboUltimo',
        'getAcuseReciboEnProceso',
        'getAcuseRecibo',
        'getAcuseReciboUltimo',
        'getAcuseReciboExitosoFallido',
        'getUblReciboBienUltimo',
        'getReciboBien',
        'getUblReciboBien',
        'getUblReciboBienEnProceso',
        'getReciboBienEnProceso',
        'getUblAceptacion',
        'getAceptado',
        'getAceptadoExitosoFallido',
        'getAceptadoEnProceso',
        'getUblAceptado',
        'getUblAceptadoEnProceso',
        'getAceptadoT',
        'getUblAceptadoT',
        'getUblAceptadoTFallido',
        'getAceptadoTExitosoFallido',
        'getRechazado',
        'getRechazadoEnProceso',
        'getUblRechazado',
        'getUblRechazoEnProceso',
        'getRechazadoExitosoFallido',
        'getDocumentosAnexos',
        'getTransmisionErp',
        'getTransmisionErpExitoso',
        'getTransmisionErpFallido',
        'getTransmisionErpExcluido',
        'getStatusEnProceso',
        'getEstadoStatus',
        'getEstadoGetStatusExitoso',
        'getEstadoRdiInconsistencia',
        'getAceptadoFallido',
        'getAceptadoTFallido',
        'getAceptadoTEnProceso',
        'getRechazadoFallido',
        'getNotificacionAcuseRecibo',
        'getNotificacionReciboBien',
        'getNotificacionAceptacion',
        'getNotificacionRechazo',
        'getNotificacionAcuseReciboFallido',
        'getNotificacionReciboBienFallido',
        'getNotificacionAceptacionFallido',
        'getNotificacionRechazoFallido',
        'getNotificacionAcuseReciboEnProceso',
        'getNotificacionReciboBienEnProceso',
        'getNotificacionAceptacionEnProceso',
        'getNotificacionRechazoEnProceso',
        'getOpencomexCxp',
        'getOpencomexCxpExitoso',
        'getOpencomexCxpFallido',
        'getUblAdAcuseReciboExitoso',
        'getUblAdAcuseReciboUltimo',
        'getNotificacionAcuseReciboUltimo',
        'getReciboBienUltimo',
        'getUblAdReciboBienExitoso',
        'getUblAdReciboBienUltimo',
        'getNotificacionReciboBienUltimo',
        'getAceptacionUltimo',
        'getUblAdAceptacionExitoso',
        'getUblAdAceptacionUltimo',
        'getNotificacionAceptacionUltimo',
        'getUblRechazo',
        'getUblRechazoFallido',
        'getRechazo',
        'getRechazoUltimo',
        'getUblAdRechazoExitoso',
        'getUblAdRechazoUltimo',
        'getNotificacionRechazoUltimo',
        'getGrupoTrabajo',
        'getReciboBienNull',
        'getReciboBienExitosoFallido',
        'getValidacionUltimo',
        'getMaximoEstadoDocumento',
        'getMaximoEventoDian',
        'getEstadoDianUltimo'
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
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     */
    public function getUsuarioResponsable() {
        return $this->belongsTo(User::class, 'cdo_usuario_responsable');
    }

    /**
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     */
    public function getUsuarioResponsableRecibidos() {
        return $this->belongsTo(User::class, 'cdo_usuario_responsable_recibidos');
    }

    /**
     * Relación con RepDatoAdicionalDocumentoDaop.
     * 
     * @return HasOne
     */
    public function getDadDocumentosDaop() {
        return $this->hasOne(RepDatoAdicionalDocumentoDaop::class, 'cdo_id');
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
     * Retorna los estados asociados al documento.
     *
     * @return HasMany
     */
    public function getEstadosDocumentosDaop() {
        return $this->hasMany(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->orderBy('est_id', 'asc');
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
     * Relación con Proveedores
     *
     * @return BelongsTo
     */
    public function getConfiguracionProveedor() {
        return $this->belongsTo(ConfiguracionProveedor::class, 'pro_id');
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
     * Obtiene la lista de Tipo Operación.
     *
     * @return BelongsTo
     */
    public function getTipoOperacion() {
        return $this->belongsTo(ParametrosTipoOperacion::class, 'top_id');
    }

    /**
     * Obtiene la lista de Tipos de Documentos Electrónicos.
     *
     * @return BelongsTo
     */
    public function getTipoDocumentoElectronico() {
        return $this->belongsTo(ParametrosTipoDocumentoElectronico::class, 'tde_id');
    }

    /**
     * Obtiene el último estado FINALIZADO de un documento.
     *
     * @return HasOne
     */
    public function getUltimoEstadoDocumento() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest('est_id');
    }

    /**
     * Obtiene los estados ASIGNAR_DEPEDENCIA que tiene la información histórica de asignaciones.
     *
     * @return HasMany
     */
    public function getEstadosAsignarDependencia() {
        return $this->hasMany(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ASIGNAR_DEPENDENCIA')
            ->where('est_resultado', 'EXITOSO');
    }
    
    /**
     * Obtiene todos los estados VALIDACION que pueda tener un documento.
     *
     * @return HasMany
     */
    public function getEstadosValidacion() {
        return $this->hasMany(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'VALIDACION')
            ->where(function($query) {
                $query->where('est_resultado', 'PENDIENTE')
                    ->orWhere('est_resultado', 'ENPROCESO')
                    ->orWhere('est_resultado', 'VALIDADO')
                    ->orWhere('est_resultado', 'PAGADO')
                    ->orWhere('est_resultado', 'RECHAZADO')
                    ->orWhere('est_resultado', 'RECHDIAN');
            });
    }

    /**
     * Obtiene el último estado VALIDACION de un documento.
     *
     * @return HasOne
     */
    public function getEstadoValidacionValidado() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'VALIDACION')
            ->where('est_resultado', 'VALIDADO')
            ->latest('est_id');
    }

    /**
     * Obtiene todos los estados VALIDACION del documento con resultado VALIDADO.
     *
     * @return HasMany
     */
    public function getEstadosValidacionValidado() {
        return $this->hasMany(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'VALIDACION')
            ->where('est_resultado', 'VALIDADO');
    }


    /**
     * Obtiene el último estado VALIDACION de un documento.
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
     * Obtiene todos los estados VALIDACION del documento con resultado PENDIENTE y EN PROCESO.
     *
     * @return HasMany
     */
    public function getEstadosValidacionEnProcesoPendiente() {
        return $this->hasMany(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'VALIDACION')
            ->where(function($query) {
                $query->where('est_resultado', 'PENDIENTE')
                    ->orWhere('est_resultado', 'ENPROCESO');
            });
    }

    /**
     * Obtiene el estado VALIDACION más reciente con resultado PENDIENTE o EN PROCESO.
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
     * Obtiene el último estado VALIDACIOON ENPROCESO o PENDIENTE de un documento.
     *
     * @return HasOne
     */
    public function getUltimoEstadoValidacionEnProcesoPendiente() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id', 'cdo_id')
            ->where('est_estado', 'VALIDACION')
            ->where(function($query) {
                $query->where('est_resultado', 'PENDIENTE')
                    ->orWhere('est_resultado', 'ENPROCESO');
            })
            ->ofMany('est_id', 'max');
    }

    /**
     * Obtiene el último estado EXITOSO de un documento.
     *
     * @return HasOne
     */
    public function getUltimoEstadoExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento fue aprobado por la DIAN.
     *
     * @return HasOne
     */
    public function getEstadoRdiExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RDI')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado RDI se encuentra en proceso.
     *
     * @return HasOne
     */
    public function getEstadoRdiEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RDI')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento fue aprobado por la DIAN.
     *
     * @return HasOne
     */
    public function getEstadoRdiInconsistencia() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RDI')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_informacion_adicional', 'like', '%inconsistencia%')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento fue aprobado por la DIAN.
     *
     * @return HasOne
     */
    public function getDocumentoAprobado() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'GETSTATUS')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->where(function($query) {
                $query->where('est_informacion_adicional', 'not like', '%conNotificacion%')
                    ->orWhere('est_informacion_adicional->conNotificacion', 'false');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento fue aprobado con notificaciones por la DIAN.
     *
     * @return HasOne
     */
    public function getDocumentoAprobadoNotificacion() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'GETSTATUS')
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
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'GETSTATUS')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento fue rechazado por la DIAN.
     *
     * @return HasOne
     */
    public function getStatusEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'GETSTATUS')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el último estado de UBL acuse de recibo.
     *
     * @return HasOne
     */
    public function getUblAcuseReciboUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACUSERECIBO')
            ->latest();
    }

    /**
     * Obtiene el último estado DIAN.
     *
     * @return HasOne
     */
    public function getEstadoStatus() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'GETSTATUS')
            ->latest();
    }

    /**
     * Obtiene el último estado DIAN.
     *
     * @return HasOne
     */
    public function getEstadoGetStatusExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'GETSTATUS')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAcuseRecibo.
     *
     * @return HasOne
     */
    public function getUblAcuseRecibo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACUSERECIBO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado del documento esta en proceso.
     *
     * @return HasOne
     */
    public function getUblAcuseReciboEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACUSERECIBO')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado del documento esta en proceso.
     *
     * @return HasOne
     */
    public function getAcuseReciboEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACUSERECIBO')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->where('est_ejecucion', null)
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
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
     * Obtiene el registro que permite saber si el estado ACUSERECIBO del documento es exitoso o fallido en su est_resultado.
     *
     * @return HasOne
     */
    public function getAcuseReciboExitosoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACUSERECIBO')
            ->where(function($query) {
                $query->where('est_resultado', 'EXITOSO')
                    ->orWhere('est_resultado', 'FALLIDO');
            })
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado de acuse de recibo.
     *
     * @return HasOne
     */
    public function getAcuseReciboUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACUSERECIBO')
            ->latest();
    }

    /**
     * Obtiene el último estado de UBL recibo del bien.
     *
     * @return HasOne
     */
    public function getUblReciboBienUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECIBOBIEN')
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
     * Obtiene el registro que permite saber que el documento tiene un estado UblReciboBien.
     *
     * @return HasOne
     */
    public function getUblReciboBien() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECIBOBIEN')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado del documento esta en proceso.
     *
     * @return HasOne
     */
    public function getUblReciboBienEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECIBOBIEN')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->where('est_ejecucion', null)
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado del documento esta en proceso.
     *
     * @return HasOne
     */
    public function getReciboBienEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECIBOBIEN')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado RECIBOBIEN del documento es null en su est_resultado.
     *
     * @return HasOne
     */
    public function getReciboBienNull() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECIBOBIEN')
            ->whereNull('est_resultado')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado RECIBOBIEN del documento es exitoso o fallido en su est_resultado.
     *
     * @return HasOne
     */
    public function getReciboBienExitosoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECIBOBIEN')
            ->where(function($query) {
                $query->where('est_resultado', 'EXITOSO')
                    ->orWhere('est_resultado', 'FALLIDO');
            })
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene un estado UblAcuseRecibo.
     *
     * @return HasOne
     */
    public function getUblAceptacion() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
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
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado ACEPTACION exitoso o fallido en su est_resultado.
     *
     * @return HasOne
     */
    public function getAceptadoExitosoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->where(function($query) {
                $query->where('est_resultado', 'EXITOSO')
                    ->orWhere('est_resultado', 'FALLIDO');
            })
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado fallido.
     *
     * @return HasOne
     */
    public function getAceptadoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->where('est_resultado', 'FALLIDO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado UblAceptacion.
     *
     * @return HasOne
     */
    public function getUblAceptado() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACEPTACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado UblAceptacion Exitoso.
     *
     * @return HasOne
     */
    public function getUblAceptadoT() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACEPTACIONT')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado UblAceptacionT Fallido.
     *
     * @return HasOne
     */
    public function getUblAceptadoTFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACEPTACIONT')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado del documento esta en proceso.
     *
     * @return HasOne
     */
    public function getUblAceptadoEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLACEPTACION')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado del documento esta en proceso.
     *
     * @return HasOne
     */
    public function getAceptadoEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado tacitamente.
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
     * Obtiene el registro que permite saber si el estado ACEPTACIONT del documento es exitoso o fallido en su est_resultado.
     *
     * @return HasOne
     */
    public function getAceptadoTExitosoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->where(function($query) {
                $query->where('est_resultado', 'EXITOSO')
                    ->orWhere('est_resultado', 'FALLIDO');
            })
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado tacitamente fallido.
     *
     * @return HasOne
     */
    public function getAceptadoTFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->where('est_resultado', 'FALLIDO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado tacitamente en proceso.
     *
     * @return HasOne
     */
    public function getAceptadoTEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
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
     * Obtiene el registro que permite saber si el estado RECHAZO del documento es exitoso o fallido en su est_resultado.
     *
     * @return HasOne
     */
    public function getRechazadoExitosoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->where(function($query) {
                $query->where('est_resultado', 'EXITOSO')
                    ->orWhere('est_resultado', 'FALLIDO');
            })
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado rechazado fallido.
     *
     * @return HasOne
     */
    public function getRechazadoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->where('est_resultado', 'FALLIDO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado Ubl Rechazo.
     *
     * @return HasOne
     */
    public function getUblRechazado() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado del documento esta en proceso.
     *
     * @return HasOne
     */
    public function getUblRechazoEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECHAZO')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el estado del documento esta en proceso.
     *
     * @return HasOne
     */
    public function getRechazadoEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene los documentos anexos de un documento recibido.
     *
     * @return HasMany
     */
    public function getDocumentosAnexos() {
        return $this->hasMany(RepDocumentoAnexoDaop::class, 'cdo_id');
    }

    /**
     * Obtiene el registro más reciente para el estado TRANSMISIONERP independiente de si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getTransmisionErp() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'TRANSMISIONERP')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado TRANSMISIONERP EXITOSO.
     *
     * @return HasOne
     */
    public function getTransmisionErpExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'TRANSMISIONERP')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado TRANSMISIONERP FALLIDO.
     *
     * @return HasOne
     */
    public function getTransmisionErpFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'TRANSMISIONERP')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado TRANSMISIONERP EXCLUIDO.
     *
     * @return HasOne
     */
    public function getTransmisionErpExcluido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'TRANSMISIONERP')
            ->where('est_resultado', 'EXCLUIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación acuse recibo.
     *
     * @return HasOne
     */
    public function getNotificacionAcuseRecibo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTACUSERECIBO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación recibo bien.
     *
     * @return HasOne
     */
    public function getNotificacionReciboBien() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTRECIBOBIEN')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación aceptación.
     *
     * @return HasOne
     */
    public function getNotificacionAceptacion() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTACEPTACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación rechazo.
     *
     * @return HasOne
     */
    public function getNotificacionRechazo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTRECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación acuse recibo.
     *
     * @return HasOne
     */
    public function getNotificacionAcuseReciboEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTACUSERECIBO')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación recibo bien.
     *
     * @return HasOne
     */
    public function getNotificacionReciboBienEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTRECIBOBIEN')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación aceptación.
     *
     * @return HasOne
     */
    public function getNotificacionAceptacionEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTACEPTACION')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación rechazo.
     *
     * @return HasOne
     */
    public function getNotificacionRechazoEnProceso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTRECHAZO')
            ->whereNull('est_resultado')
            ->where(function($query) {
                $query->whereNull('est_ejecucion')
                    ->orWhere('est_ejecucion', 'ENPROCESO');
            })
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación acuse recibo fallido.
     *
     * @return HasOne
     */
    public function getNotificacionAcuseReciboFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTACUSERECIBO')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación recibo bien fallido.
     *
     * @return HasOne
     */
    public function getNotificacionReciboBienFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTRECIBOBIEN')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación aceptación fallido.
     *
     * @return HasOne
     */
    public function getNotificacionAceptacionFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTACEPTACION')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado de notificación rechazo fallido.
     *
     * @return HasOne
     */
    public function getNotificacionRechazoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTRECHAZO')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro más reciente para el estado OPENCOMEXCXP.
     *
     * @return HasOne
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
     * @return HasOne
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
     * @return HasOne
     */
    public function getOpencomexCxpFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'OPENCOMEXCXP')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el documento tiene ubl attached document acuse recibo exitoso.
     *
     * @return HasOne
     */
    public function getUblAdAcuseReciboExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACUSERECIBO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado de ubl attached document acuse recibo.
     *
     * @return HasOne
     */
    public function getUblAdAcuseReciboUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACUSERECIBO')
            ->latest();
    }

    /**
     * Obtiene el último estado de notificación acuse recibo.
     *
     * @return HasOne
     */
    public function getNotificacionAcuseReciboUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTACUSERECIBO')
            ->latest();
    }

    /**
     * Obtiene el último estado de recibo de bien.
     *
     * @return HasOne
     */
    public function getReciboBienUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECIBOBIEN')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el documento tiene ubl attached document recibo bien exitoso.
     *
     * @return HasOne
     */
    public function getUblAdReciboBienExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADRECIBOBIEN')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado de ubl attached document recibo bien.
     *
     * @return HasOne
     */
    public function getUblAdReciboBienUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADRECIBOBIEN')
            ->latest();
    }

    /**
     * Obtiene el último estado de notificación recibo bien.
     *
     * @return HasOne
     */
    public function getNotificacionReciboBienUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTRECIBOBIEN')
            ->latest();
    }

    /**
     * Obtiene el último estado de aceptación.
     *
     * @return HasOne
     */
    public function getAceptacionUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el documento tiene ubl attached document aceptación exitoso.
     *
     * @return HasOne
     */
    public function getUblAdAceptacionExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACEPTACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado de ubl attached document aceptación.
     *
     * @return HasOne
     */
    public function getUblAdAceptacionUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACEPTACION')
            ->latest();
    }

    /**
     * Obtiene el último estado de notificación aceptación.
     *
     * @return HasOne
     */
    public function getNotificacionAceptacionUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTACEPTACION')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el documento tiene un estado de ubl rechazo.
     *
     * @return HasOne
     */
    public function getUblRechazo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el documento tiene un estado de ubl rechazo.
     *
     * @return HasOne
     */
    public function getUblRechazoFallido() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLRECHAZO')
            ->where('est_resultado', 'FALLIDO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el documento tiene estado rechazo.
     *
     * @return HasOne
     */
    public function getRechazo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado de rechazo.
     *
     * @return HasOne
     */
    public function getRechazoUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber si el documento tiene ubl attached document rechazo exitoso.
     *
     * @return HasOne
     */
    public function getUblAdRechazoExitoso() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADRECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el último estado de ubl attached document rechazo.
     *
     * @return HasOne
     */
    public function getUblAdRechazoUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADRECHAZO')
            ->latest();
    }

    /**
     * Obtiene el último estado de notificación rechazo.
     *
     * @return HasOne
     */
    public function getNotificacionRechazoUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'NOTRECHAZO')
            ->latest();
    }

    /**
     * Retorna el Grupo de Trabajo al que pertenece el documento, siempre que el grupo este activo.
     *
     * @return BelongsTo
     */
    public function getGrupoTrabajo() {
        return $this->belongsTo(ConfiguracionGrupoTrabajo::class, 'gtr_id');
    }

    /**
     * Retorna el último estado Finalizado del documento.
     *
     * @return HasOne
     */
    public function getMaximoEstadoDocumento() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->ofMany([
                "est_id" => "max"
            ],  function ($query) {
                $query->whereIn('est_estado', [
                        'UBLACEPTACIONT', 
                        'ACEPTACIONT', 
                        'UBLACEPTACION', 
                        'ACEPTACION', 
                        'UBLRECHAZO', 
                        'RECHAZO'
                    ])->where('est_ejecucion', 'FINALIZADO');
            });
    }

    /**
     * Retorna el último evento DIAN Finalizado del documento.
     *
     * @return HasOne
     */
    public function getMaximoEventoDian() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->ofMany([
                "est_id" => "max"
            ],  function ($query) {
                $query->whereIn('est_estado', [
                        'ACUSERECIBO', 
                        'RECIBOBIEN', 
                        'ACEPTACION', 
                        'ACEPTACIONT',
                        'RECHAZO'
                    ])->where('est_ejecucion', 'FINALIZADO');
            });
    }

    /**
     * Retorna el último estado de VALIDACION del documento.
     *
     * @return HasOne
     */
    public function getValidacionUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->ofMany([
                "est_id" => "max"
            ],  function ($query) {
                $query->where('est_estado', 'VALIDACION');
            });
    }

    /**
     * Retorna el último estado GETSTATUS del documento.
     *
     * @return HasOne
     */
    public function getEstadoDianUltimo() {
        return $this->hasOne(RepEstadoDocumentoDaop::class, 'cdo_id')
            ->ofMany([
                "est_id" => "max"
            ],  function ($query) {
                $query->where('est_estado', 'GETSTATUS');
            });
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
            ->orWhereHas('getConfiguracionProveedor', function ($query2) use ($texto) {
                $query2->where('pro_razon_social', 'like', '%'.$texto.'%')
                    ->orWhere('pro_nombre_comercial', 'like', '%'.$texto.'%')
                    ->orWhere('pro_primer_apellido', 'like', '%'.$texto.'%')
                    ->orWhere('pro_segundo_apellido', 'like', '%'.$texto.'%')
                    ->orWhere('pro_primer_nombre', 'like', '%'.$texto.'%')
                    ->orWhere('pro_otros_nombres', 'like', '%'.$texto.'%')
                    ->orWhereRaw("REPLACE(CONCAT(COALESCE(pro_primer_nombre, ''), ' ', COALESCE(pro_otros_nombres, ''), ' ', COALESCE(pro_primer_apellido, ''), ' ', COALESCE(pro_segundo_apellido, '')), '  ', ' ') like ?", ['%' . $texto . '%']);
            })
            ->orWhere('cdo_fecha', 'like', '%'.$texto.'%')
            ->orWhere('cdo_hora', 'like', '%'.$texto.'%')
            ->orWhere('cdo_total', 'like', '%'.$texto.'%')
            ->orWhere('cdo_origen', 'like', '%'.$texto.'%')
            ->orWhere('cdo_valor_a_pagar', 'like', '%'.$texto.'%')
            ->orWhere('rep_cabecera_documentos_daop.estado', $texto)
            ->orWhereRaw('exists (select * from `etl_openmain`.`etl_monedas` where `rep_cabecera_documentos_daop`.`mon_id` = `etl_openmain`.`etl_monedas`.`mon_id` and `mon_codigo` like ?)', [$texto])
            ->orWhereRaw('exists (select * from `etl_openmain`.`auth_usuarios` where `rep_cabecera_documentos_daop`.`cdo_usuario_responsable` = `etl_openmain`.`auth_usuarios`.`usu_id` and `usu_nombre` like ?)', ['%'.$texto.'%']);
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
            case 'proveedor':
                return $query->join('rep_proveedores as proveedor', 'proveedor.pro_id', '=', 'rep_cabecera_documentos_daop.pro_id')
                    ->orderBy(DB::Raw('IF(proveedor.pro_razon_social IS NULL OR proveedor.pro_razon_social = "", CONCAT(COALESCE(proveedor.pro_primer_nombre, ""), " ", COALESCE(proveedor.pro_primer_apellido, "")), proveedor.pro_razon_social)'), $ordenDireccion);
                break;
            case 'fecha':
                $orderBy = 'cdo_fecha';
                break;
            case 'moneda':
                return $query->join('etl_openmain.etl_monedas as monedas', 'monedas.mon_id', '=', 'rep_cabecera_documentos_daop.mon_id')
                    ->orderBy('monedas.mon_codigo', $ordenDireccion);
                break;
            case 'valor':
                $orderBy = 'cdo_valor_a_pagar';
                break;
            case 'estado':
                $orderBy = 'estado';
                break;
            case 'origen':
                $orderBy = 'cdo_origen';
                break;
            case 'usuario_responsable':
                return $query->leftjoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'rep_cabecera_documentos_daop.cdo_usuario_responsable')
                    ->orderBy('usuarios.usu_nombre', $ordenDireccion);
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
     * Local Scope que permite incluir en una búsqueda el campo rfa_prefijo
     * dependiendo de si es recibido con un valor o no
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

    /**
     * Permite filtrar documentos asignados a un único grupo de trabajo, varios grupos de trabajo o la combinación de los dos anteriores.
     * 
     * Aplica solamente para los OFES que tengan configurados grupos de trabajo
     *
     * @param Builder $query Consulta en ejecuión
     * @param Request $request Parámetros de la petición en curso
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE con la relación a los grupos de trabajo
     * @param array $gruposTrabajoUsuario Array con los IDs de los grupos de trabajo a los que el usuario autenticado se encuentra asignado
     * @return Builder
     */
    public function scopeFiltroGruposTrabajo(Builder $query, Request $request, ConfiguracionObligadoFacturarElectronicamente $ofe, array $gruposTrabajoUsuario): Builder {
        return $query->when($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo == 'unico' && $ofe->getGruposTrabajo->count() > 0, function($query) use ($gruposTrabajoUsuario) {
            $query->has('getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo', '>', 0)
                ->where(function($query) use ($gruposTrabajoUsuario) {
                    $query->whereHas('getGrupoTrabajo', function($query) use ($gruposTrabajoUsuario) {
                        $query->where('estado', 'ACTIVO')
                            ->whereIn('gtr_id', $gruposTrabajoUsuario);
                    })
                    ->orWhereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                        $query->where('estado', 'ACTIVO')
                            ->when(empty($gruposTrabajoUsuario), function ($query) {
                                $query->whereNull('gtr_id');
                            });
                    }, '=', 1);
                });
        })
        ->when($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo == 'compartido' && $ofe->getGruposTrabajo->count() > 0, function($query) use ($gruposTrabajoUsuario) {
            $query->has('getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo', '>', 0)
                ->where(function($query) use ($gruposTrabajoUsuario) {
                    $query->where(function($query) use ($gruposTrabajoUsuario) {
                        $query->doesntHave('getGrupoTrabajo')
                            ->orWhereHas('getGrupoTrabajo', function($query) use ($gruposTrabajoUsuario) {
                                $query->where('estado', 'INACTIVO')
                                    ->whereIn('gtr_id', $gruposTrabajoUsuario);
                            });
                    })
                    ->whereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                        $query->where('estado', 'ACTIVO')
                            ->when(empty($gruposTrabajoUsuario), function ($query) {
                                $query->whereNull('gtr_id');
                            });
                    }, '>', 1);
                });
        })
        ->when(!$request->filled('filtro_grupos_trabajo') && $ofe->getGruposTrabajo->count() > 0, function($query) use ($gruposTrabajoUsuario) {
            $query->has('getConfiguracionObligadoFacturarElectronicamente.getGruposTrabajo', '>', 0)
                ->where(function($query) use ($gruposTrabajoUsuario) {
                    $query->where(function($query) use ($gruposTrabajoUsuario) {
                        $query->whereHas('getGrupoTrabajo', function($query) use ($gruposTrabajoUsuario) {
                            $query->where('estado', 'ACTIVO')
                                ->whereIn('gtr_id', $gruposTrabajoUsuario);
                        })
                        ->orWhereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                            $query->where('estado', 'ACTIVO')
                                ->when(empty($gruposTrabajoUsuario), function ($query) {
                                    $query->whereNull('gtr_id');
                                });
                        }, '=', 1);
                    })
                    ->orWhere(function($query) use ($gruposTrabajoUsuario) {
                        $query->where(function($query) use ($gruposTrabajoUsuario) {
                            $query->doesntHave('getGrupoTrabajo')
                                ->orWhereHas('getGrupoTrabajo', function($query) use ($gruposTrabajoUsuario) {
                                    $query->where('estado', 'INACTIVO')
                                        ->whereIn('gtr_id', $gruposTrabajoUsuario);
                                });
                        })
                        ->whereHas('getConfiguracionProveedor.getProveedorGruposTrabajo', function($query) use ($gruposTrabajoUsuario) {
                            $query->where('estado', 'ACTIVO')
                                ->when(empty($gruposTrabajoUsuario), function ($query) {
                                    $query->whereNull('gtr_id');
                                });
                        }, '>', 1);
                    });
                });
        });
    }
}
    