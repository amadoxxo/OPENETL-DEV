<?php

namespace App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use openEtl\Tenant\Models\Documentos\EtlDetalleDocumentosDaop\TenantEtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop\EtlImpuestosItemsDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop\EtlCargosDescuentosDocumentoDaop;

class EtlDetalleDocumentoDaop extends TenantEtlDetalleDocumentoDaop {
    protected $visible = [
        'ddo_id',
        'cdo_id',
        'ddo_tipo_item',
        'ddo_secuencia',
        'ddo_descripcion_uno',
        'ddo_descripcion_dos',
        'ddo_descripcion_tres',
        'ddo_notas',
        'ddo_cantidad',
        'ddo_cantidad_paquete',
        'und_id',
        'ddo_valor_unitario',
        'ddo_valor_unitario_moneda_extranjera',
        'ddo_total',
        'ddo_total_moneda_extranjera',
        'ddo_indicador_muestra',
        'pre_id',
        'ddo_valor_muestra',
        'ddo_valor_muestra_moneda_extranjera',
        'ddo_datos_tecnicos',
        'ddo_marca',
        'ddo_modelo',
        'ddo_codigo_vendedor',
        'ddo_codigo_vendedor_subespecificacion',
        'ddo_codigo_fabricante',
        'ddo_codigo_fabricante_subespecificacion',
        'ddo_nombre_fabricante',
        'ddo_codigo',
        'cpr_id',
        'ddo_nombre_clasificacion_producto',
        'pai_id',
        'ddo_propiedades_adicionales',
        'ddo_nit_mandatario',
        'ddo_informacion_adicional',
        'ddo_identificacion_comprador',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getCabeceraDocumentosDaop',
        'getImpuestosItemsDocumentosDaop',
        'getCargoDescuentosItemsDocumentosDaop'
    ];

    // INICIO RELACIONES
    /**
     * Relación con EtlCabeceraDocumentosDaop.
     * 
     * @return BelongsTo
     */
    public function getCabeceraDocumentosDaop() {
        return $this->belongsTo(EtlCabeceraDocumentoDaop::class, 'cdo_id');
    }

    /**
     * Relación con EtlImpuestosItemsDocumentosDaop.
     * 
     * @return HasMany
     */
    public function getImpuestosItemsDocumentosDaop() {
        return $this->hasMany(EtlImpuestosItemsDocumentoDaop::class, 'ddo_id');
    }

    /**
     * Relación con EtlImpuestosItemsDocumentosDaop.
     * 
     * @return HasMany
     */
    public function getCargoDescuentosItemsDocumentosDaop() {
        return $this->hasMany(EtlCargosDescuentosDocumentoDaop::class, 'ddo_id');
    }
    // FIN RELACIONES  
}
