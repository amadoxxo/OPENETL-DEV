<?php

namespace App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual;

use App\Http\Models\User;
use openEtl\Tenant\Models\Recepcion\RPA\EtlEmailsProcesamientoManual\TenantEtlEmailProcesamientoManual;

class EtlEmailProcesamientoManual extends TenantEtlEmailProcesamientoManual {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'epm_id',
        'ofe_identificacion',
        'epm_subject',
        'epm_id_carpeta',
        'epm_fecha_correo',
        'epm_cuerpo_correo',
        'epm_procesado',
        'epm_procesado_usuario',
        'epm_procesado_fecha',
        'epm_observaciones',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    /**
     * Relación con el modelo usuario.
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }
}
