<?php

namespace App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop;


use openEtl\Tenant\Models\Documentos\EtlDatosAdicionalesDocumentosDaop\TenantEtlDatoAdicionalDocumentoDaop;

/**
 * Clase gestora de datos adicionales de documentos electronicos.
 *
 * Class EtlDatosAdicionalesDocumentosDaop
 * @package App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop
 */
class EtlDatosAdicionalesDocumentoDaop extends TenantEtlDatoAdicionalDocumentoDaop {
    /**
     * @var array
     */
    protected $visible = [
        'dad_id',
        'cdo_id',
        'adq_id_entrega_bienes_responsable',
        'cdo_documento_adicional',
        'dad_periodo_fecha_inicio',
        'dad_periodo_hora_inicio',
        'dad_periodo_fecha_fin',
        'dad_periodo_hora_fin',
        'dad_documento_referencia_linea',
        'dad_referencia_adquirente',
        'dad_orden_referencia',
        'dad_despacho_referencia',
        'dad_recepcion_referencia',
        'dad_entrega_bienes_fecha',
        'dad_entrega_bienes_hora',
        'pai_id_entrega_bienes',
        'dep_id_entrega_bienes',
        'mun_id_entrega_bienes',
        'cpo_id_entrega_bienes',
        'dad_direccion_entrega_bienes',
        'dad_entrega_bienes_despacho_identificacion_transportador',
        'dad_entrega_bienes_despacho_identificacion_transporte',
        'dad_entrega_bienes_despacho_tipo_transporte',
        'dad_entrega_bienes_despacho_fecha_solicitada',
        'dad_entrega_bienes_despacho_hora_solicitada',
        'dad_entrega_bienes_despacho_fecha_estimada',
        'dad_entrega_bienes_despacho_hora_estimada',
        'dad_entrega_bienes_despacho_fecha_real',
        'dad_entrega_bienes_despacho_hora_real',
        'pai_id_entrega_bienes_despacho',
        'dep_id_entrega_bienes_despacho',
        'mun_id_entrega_bienes_despacho',
        'cpo_id_entrega_bienes_despacho',
        'dad_direccion_entrega_bienes_despacho',
        'dad_terminos_entrega',
        'dad_terminos_entrega_condiciones_pago',
        'cen_id',
        'pai_id_terminos_entrega',
        'dep_id_terminos_entrega',
        'mun_id_terminos_entrega',
        'cpo_id_terminos_entrega',
        'dad_direccion_terminos_entrega',
        'cdd_id_terminos_entrega',
        'dad_codigo_moneda_alternativa',
        'dad_codigo_moneda_extranjera_alternativa',
        'dad_trm_alternativa',
        'dad_trm_fecha_alternativa',
        'dad_interoperabilidad',
        'cdo_informacion_adicional',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}