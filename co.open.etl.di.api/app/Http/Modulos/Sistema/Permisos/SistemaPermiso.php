<?php

namespace App\Http\Modulos\Sistema\Permisos;

use App\Http\Modulos\Sistema\Roles\SistemaRol;
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
     * Relación con Roles.
     */
    public function getSistemaRol() {
        return $this->belongsTo(SistemaRol::class, 'rol_id');
    }

    /**
     * Relación con Recursos.
     */
    public function getSistemaRecurso() {
        return $this->belongsTo(SistemaRecurso::class, 'rec_id');
    }

}