<?php

namespace App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActor;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;
use openEtl\Tenant\Models\Radian\RadianCabeceraDocumentosDaop\TenantRadianCabeceraDocumentoDaop;

class RadianCabeceraDocumentoDaop extends TenantRadianCabeceraDocumentoDaop {
    protected $visible = [
        'cdo_id',
        'cdo_origen',
        'tde_id',
        'top_id',
        'cdo_lote',
        'act_id',
        'rol_id',
        'ofe_identificacion',
        'ofe_nombre',
        'ofe_informacion_adicional',
        'adq_identificacion',
        'adq_nombre',
        'adq_informacion_adicional',
        'rfa_resolucion',
        'rfa_prefijo',
        'cdo_consecutivo',
        'cdo_fecha',
        'cdo_hora',
        'cdo_vencimiento',
        'mon_id',
        'mon_id_extranjera',
        'cdo_trm',
        'cdo_trm_fecha',
        'cdo_valor_sin_impuestos',
        'cdo_impuestos',
        'cdo_retenciones',
        'cdo_total',
        'cdo_cargos',
        'cdo_descuentos',
        'cdo_anticipo',
        'cdo_redondeo',
        'cdo_valor_a_pagar',
        'cdo_cufe',
        'cdo_algoritmo_cufe',
        'cdo_qr',
        'cdo_signaturevalue',
        'cdo_fecha_validacion_dian',
        'cdo_fecha_acuse',
        'cdo_fecha_recibo_bien',
        'cdo_estado',
        'cdo_fecha_estado',
        'cdo_nombre_archivos',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getRadActores',
        'getEstadoAcuseRecibo',
        'getEstadoUblAdAcuseRecibo',
        'getAceptado',
        'getAceptadoT',
        'getRechazado',
    ];

    //INICIO RELACIONES
    /**
     * Relación con el modelo de Radián Actores.
     * 
     * @return BelongsTo
     */
    public function getRadActores() {
        return $this->belongsTo(RadianActor::class, 'act_id');
    }

    /**
     * Obtiene el estado ACUSERECIBO mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoAcuseRecibo() {
        return $this->hasOne(RadianEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACUSERECIBO')
            ->latest();
    }

    /**
     * Obtiene el estado UBLADACUSERECIBO mas reciente sin importar si fue fallido o exitoso.
     *
     * @return HasOne
     */
    public function getEstadoUblAdAcuseRecibo() {
        return $this->hasOne(RadianEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'UBLADACUSERECIBO')
            ->latest(); 
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado
     *
     * @return HasOne
     */
    public function getAceptado() {
        return $this->hasOne(RadianEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACION')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado aceptado tácitamente
     *
     * @return HasOne
     */
    public function getAceptadoT() {
        return $this->hasOne(RadianEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'ACEPTACIONT')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }

    /**
     * Obtiene el registro que permite saber que el documento tiene estado rechazado
     *
     * @return HasOne
     */
    public function getRechazado() {
        return $this->hasOne(RadianEstadoDocumentoDaop::class, 'cdo_id')
            ->where('est_estado', 'RECHAZO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }
    //FIN RELACIONES
}
