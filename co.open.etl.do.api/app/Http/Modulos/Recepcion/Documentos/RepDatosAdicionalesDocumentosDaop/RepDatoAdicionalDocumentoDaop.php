<?php

namespace App\Http\Modulos\Recepcion\Documentos\RepDatosAdicionalesDocumentosDaop;

use openEtl\Tenant\Models\Recepcion\Documentos\RepDatosAdicionalesDocumentosDaop\TenantRepDatoAdicionalDocumentoDaop;

class RepDatoAdicionalDocumentoDaop extends TenantRepDatoAdicionalDocumentoDaop {
    protected $visible = [
        'dad_id',
        'cdo_id',
        'cdo_documento_adicional',
        'dad_periodo_fecha_inicio',
        'dad_periodo_hora_inicio',
        'dad_periodo_fecha_fin',
        'dad_periodo_hora_fin',
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
        'adq_id_entrega_bienes_responsable',
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
        'dad_codigo_moneda_alternativa',
        'dad_codigo_moneda_extranjera_alternativa',
        'dad_trm_alternativa',
        'dad_trm_fecha_alternativa',
        'cdo_informacion_adicional',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

    // Mutador que permite eliminar caracteres slash en el campo cdo_documento_adicional ya que la columna no es del tipo json
    public function setCdoDocumentoAdicionalAttribute($value) {
        if($value == '')
            $this->attributes['cdo_documento_adicional'] = null;
        else
            $this->attributes['cdo_documento_adicional'] = stripslashes($value);
    }
}
