<?php
namespace App\Http\Modulos\FacturacionWeb\Parametros\Productos;

use openEtl\Tenant\Models\Documentos\FacturacionWeb\EtlFacturacionWebProductos\TenantEtlFacturacionWebProducto;

class EtlFacturacionWebProducto extends TenantEtlFacturacionWebProducto {
    protected $visible = [
        'dmp_id',
        'ofe_id',
        'cpr_id',
        'dmp_codigo',
        'dmp_descripcion_uno',
        'dmp_descripcion_dos',
        'dmp_descripcion_dos_editable',
        'dmp_descripcion_tres',
        'dmp_descripcion_tres_editable',
        'dmp_nota',
        'dmp_nota_editable',
        'dmp_tipo_item',
        'und_id',
        'dmp_cantidad_paquete',
        'dmp_valor',
        'dmp_tributos',
        'dmp_autoretenciones',
        'dmp_retenciones_sugeridas',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getClasificacionProducto',
        'getUnidad',
        'getPartidaArancelaria',
        'getColombiaCompraEficiente'
    ];
}
