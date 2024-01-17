<?php

namespace App\Http\Modulos\Sistema\Roles;

use App\Http\Modulos\Sistema\Permisos\SistemaPermiso;
use openEtl\Main\Models\Sistema\Roles\MainSistemaRol;
use App\Http\Modulos\Sistema\RolesUsuario\SistemaRolesUsuario;

class SistemaRol extends MainSistemaRol {
    protected $visible = [
        'rol_id', 
        'rol_codigo',  
        'rol_descripcion',
        'estado',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'getRolesUsuario',
        'getRolPermisos'
    ];

    /**
     * Relación con SistemaRolesUsuario.
     */
    public function getRolesUsuario(){
        return $this->hasMany(SistemaRolesUsuario::class, 'rol_id');
    }

    /**
     * Relación con SistemaPermiso.
     */
    public function getRolPermisos(){
        return $this->hasMany(SistemaPermiso::class, 'rol_id');
    }
}
