<?php

namespace App\Http\Modulos\Sistema\Permisos;

use App\Http\Modulos\Sistema\Recursos\SistemaRecurso;
use openEtl\Main\Models\Sistema\Permisos\MainSistemaPermiso;

class SistemaPermiso extends MainSistemaPermiso {
    protected $visible = [
        'per_id', 
        'rol_id',  
        'rec_id',  
        'usuario_creacion', 
        'estado',
        'getSistemaRol',
        'getSistemaRecurso',
    ];

    /**
     * Relacion con Recursos
     */
    public function getSistemaRecurso() {
        return $this->belongsTo(SistemaRecurso::class, 'rec_id');
    }

}