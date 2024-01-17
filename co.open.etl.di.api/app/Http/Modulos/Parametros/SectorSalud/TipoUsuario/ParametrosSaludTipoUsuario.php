<?php

namespace App\Http\Modulos\Parametros\SectorSalud\TipoUsuario;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\SectorSalud\TipoUsuario\MainParametrosSaludTipoUsuario;

class ParametrosSaludTipoUsuario extends MainParametrosSaludTipoUsuario {
    protected $visible = [
        'stu_id',
        'stu_codigo',
        'stu_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion'
    ];

    // INICIO RELACIÓN
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
    // FIN RELACION
}