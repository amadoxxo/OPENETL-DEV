<?php

namespace App\Http\Modulos\NominaElectronica\DsnDetalleDevengadosDaop;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\NominaElectronica\DsnDetalleDevengadosDaop\TenantDsnDetalleDevengadoDaop;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaop;

class DsnDetalleDevengadoDaop extends TenantDsnDetalleDevengadoDaop {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'ddv_id',
        'cdn_id',
        'ddv_concepto',
        'ddv_descripcion',
        'ddv_tipo',
        'ddv_fecha_inicio',
        'ddv_hora_inicio',
        'ddv_fecha_fin',
        'ddv_hora_fin',
        'ddv_cantidad',
        'ddv_porcentaje',
        'ddv_valor',
        'ddv_pago_adicional',
        'ddv_valor_salarial',
        'ddv_valor_no_salarial',
        'ddv_valor_salarial_adicional',
        'ddv_valor_no_salarial_adicional',
        'ddv_valor_ordinario',
        'ddv_valor_extraordinario',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getCabeceraNomina'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo usuario.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }

    /**
     * Relación con el Documento de Cabecera Nomina.
     * 
     * @return BelongsTo
     */
    public function getCabeceraNomina() {
        return $this->belongsTo(DsnCabeceraDocumentoNominaDaop::class, 'cdn_id')->select([
            'cdn_id',
            'cdn_prefijo',
            'cdn_consecutivo'
        ]);
    }
    // FIN RELACIONES
}
