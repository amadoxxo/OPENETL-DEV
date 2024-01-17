<?php

namespace App\Http\Modulos\Sistema\RolesUsuario;

use App\Http\Models\User;
use App\Http\Modulos\Sistema\Roles\SistemaRol;
use openEtl\Main\Models\Sistema\RolesUsuario\MainSistemaRolesUsuario;

class SistemaRolesUsuario extends MainSistemaRolesUsuario {
    protected $visible = [
        'rus_id',   
        'usu_id',  
        'rol_id',
        'usuario_creacion', 
        'estado',
        'getUser',
        'getSistemaRol'
    ];

    /**
     * Relación con User
     */
    public function getUser() {
        return $this->belongsTo(User::class, 'usu_id');
    }

    /**
     * Relacion con Roles
     */
    public function getSistemaRol() {
        return $this->belongsTo(SistemaRol::class, 'rol_id');
    }

}
