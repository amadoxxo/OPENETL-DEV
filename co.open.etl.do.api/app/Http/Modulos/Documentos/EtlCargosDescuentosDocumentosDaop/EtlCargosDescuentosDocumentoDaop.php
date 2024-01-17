<?php

namespace App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop;

use App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargo;
use openEtl\Tenant\Models\Documentos\EtlCargosDescuentosDocumentosDaop\TenantEtlCargoDescuentoDocumentoDaop;

class EtlCargosDescuentosDocumentoDaop extends TenantEtlCargoDescuentoDocumentoDaop {
    /**
     * @var array
     */
    protected $visible = [
        'cdo_id',
        'ddo_id',
        'cdd_numero_linea',
        'cdd_aplica',
        'cdd_tipo',
        'cdd_indicador',
        'cdd_nombre',
        'cdd_razon',
        'cdd_porcentaje',
        'cdd_valor',
        'cdd_valor_moneda_extranjera',
        'cdd_base',
        'cdd_base_moneda_extranjera',
        'cde_id',
        'tri_id',
        'dmc_id',
        'dmd_id',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getDocumentoManualCargo'
    ];


    /**
     * Relación con el modelo EtlFacturacionWebCargo.
     *
     * @return BelongsTo
     */
    public function getDocumentoManualCargo() {
        return $this->belongsTo(EtlFacturacionWebCargo::class, 'dmc_id');
    }
}