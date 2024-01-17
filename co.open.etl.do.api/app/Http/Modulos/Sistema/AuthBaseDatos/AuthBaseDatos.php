<?php

namespace App\Http\Modulos\Sistema\AuthBaseDatos;

use App\Http\Models\User;
use openEtl\Main\Models\MainAuthBaseDatos;

class AuthBaseDatos extends MainAuthBaseDatos {   
    protected $visible = [
        'bdd_id', 
        'bdd_nombre',
        'bdd_alias',
        'bdd_host',
        'bdd_usuario',
        'bdd_password',
        'bdd_cantidad_procesamiento_edi',
        'bdd_cantidad_procesamiento_ubl',
        'bdd_cantidad_procesamiento_do',
        'bdd_cantidad_procesamiento_notificacion',
        'bdd_cantidad_procesamiento_rdi',
        'bdd_cantidad_procesamiento_getstatus',
        'bdd_cantidad_procesamiento_acuse',
        'bdd_cantidad_procesamiento_aceptacion',
        'bdd_cantidad_procesamiento_rechazo',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarios'
    ];

    // INICIO RELACION
    public function getUsuarios() {
        return $this->hasMany(User::class, 'bdd_id');
    }
    // FIN RELACION

}
