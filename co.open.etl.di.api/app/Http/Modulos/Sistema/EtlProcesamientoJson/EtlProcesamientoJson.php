<?php
namespace App\Http\Modulos\Sistema\EtlProcesamientoJson;

use openEtl\Tenant\Models\Documentos\EtlProcesamientoJson\TenantEtlProcesamientoJson;

class EtlProcesamientoJson extends TenantEtlProcesamientoJson {
    protected $visible = [
        'pjj_id',
        'pjj_tipo',
        'pjj_json',
        'pjj_procesado',
        'pjj_errores',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

    // Accesors
    // Transforma la columna pjj_tipo
    public function getPjjTipoAttribute ($value) {
        switch($value) {
            case 'FC':
                return 'FACTURAS';
                break;
            case 'ND-NC':
                return 'NOTAS DEBITO/CREDITO';
                break;
            default:
                return strtoupper($value);
                break;
        }
    }
}
