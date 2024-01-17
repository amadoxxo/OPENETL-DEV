<?php

namespace App\Http\Modulos\Recepcion\Documentos\RepMediosPagoDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPago;
use openEtl\Tenant\Models\Recepcion\Documentos\RepMediosPagoDocumentosDaop\TenantRepMediosPagoDocumentoDaop;

/**
 * Clase gestora de medios de pago de documentos electronicos en Recepción
 *
 * Class RepMediosPagoDocumentosDaop
 * @package App\Http\Modulos\Recepcion\Documentos\RepMediosPagoDocumentosDaop
 */
class RepMediosPagoDocumentoDaop extends TenantRepMediosPagoDocumentoDaop {
    protected $visible= [
        'men_id',
        'cdo_id',
        'fpa_id',
        'mpa_id',
        'men_fecha_vencimiento',
        'men_identificador_pago',
        'mpa_informacion_adicional',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getMedioPago',
        'getFormaPago',
        'getMediosPagoDocumentosDaop'
    ];

    // INICIO RELACIONES
    /**
     * Relación con la paramétrica de Medios de Pago.
     *
     * @return BelongsTo
     */
    public function getMedioPago() {
        return $this->belongsTo(ParametrosMediosPago::class, 'mpa_id');
    }

    /**
     * Relación con la paramétrica de Formas de Pago.
     *
     * @return BelongsTo
     */
    public function getFormaPago() {
        return $this->belongsTo(ParametrosFormaPago::class, 'fpa_id');
    }
    // FIN RELACIONES
}
