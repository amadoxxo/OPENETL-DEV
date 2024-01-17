<?php

namespace App\Http\Modulos\NominaElectronica\DsnEstadosDocumentosDaop;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\NominaElectronica\DsnEstadosDocumentosDaop\TenantDsnEstadoDocumentoDaop;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaop;

class DsnEstadoDocumentoDaop extends TenantDsnEstadoDocumentoDaop {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'est_id',
        'cdn_id',
        'est_estado',
        'est_resultado',
        'est_mensaje_resultado',
        'est_object',
        'est_correos',
        'est_informacion_adicional',
        'age_id',
        'age_usu_id',
        'est_inicio_proceso',
        'est_fin_proceso',
        'est_tiempo_procesamiento',
        'est_ejecucion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

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
     * Relación con el Documento de Cabecera Nómina.
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
}
