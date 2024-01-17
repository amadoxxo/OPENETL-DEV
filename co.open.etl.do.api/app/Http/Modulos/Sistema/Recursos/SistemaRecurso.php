<?php

namespace App\Http\Modulos\Sistema\Recursos;

use openEtl\Main\Models\Sistema\Recursos\MainSistemaRecurso;

class SistemaRecurso extends MainSistemaRecurso {
    protected $visible = [
        'rec_id', 
        'rec_alias',  
        'rec_modulo',  
        'rec_controlador',  
        'rec_accion',
        'rec_modulo_descripcion',
        'rec_descripcion',
        'usuario_creacion', 
        'estado',
        'getRecursoPermisos'
    ];
}