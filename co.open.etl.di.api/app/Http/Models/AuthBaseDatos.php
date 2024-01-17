<?php

namespace App\Http\Models;

use openEtl\Main\Models\MainAuthBaseDatos;

class AuthBaseDatos extends MainAuthBaseDatos {
    protected $visible = [
        'bdd_id', 
        'bdd_nombre',
        'bdd_alias',
        'bdd_host',
        'bdd_usuario',
        'bdd_password',
        'bdd_aplica_particionamiento_emision',
        'bdd_inicio_particionamiento_emision',
        'bdd_dias_data_operativa_emision',
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
        'estado'
    ];
}
