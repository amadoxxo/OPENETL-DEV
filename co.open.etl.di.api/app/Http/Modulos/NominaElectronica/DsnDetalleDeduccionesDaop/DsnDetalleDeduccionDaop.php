<?php

namespace App\Http\Modulos\NominaElectronica\DsnDetalleDeduccionesDaop;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Tenant\Models\NominaElectronica\DsnDetalleDeduccionesDaop\TenantDsnDetalleDeduccionDaop;
use App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaop;

class DsnDetalleDeduccionDaop extends TenantDsnDetalleDeduccionDaop {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'ddd_id',
        'cdn_id',
        'ddd_concepto',
        'ddd_descripcion',
        'ddd_porcentaje',
        'ddd_valor',
        'ddd_porcentaje_adicional',
        'ddd_valor_adicional',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getCabeceraNomina'
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
