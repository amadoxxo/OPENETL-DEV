<?php

namespace App\Http\Modulos\Parametros\ProcedenciaVendedor;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\ProcedenciaVendedor\MainParametrosProcedenciaVendedor;

class ParametrosProcedenciaVendedor extends MainParametrosProcedenciaVendedor {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'ipv_id',
        'ipv_codigo',
        'ipv_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion'
    ];

    /**
     * Relación con Usuarios.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion(){
        return $this->belongsTo(User::class,'usu_id');
    }
}
