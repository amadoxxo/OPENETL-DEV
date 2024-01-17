<?php

namespace App\Http\Modulos\Parametros\ConceptosRechazo;

use openEtl\Main\Models\Parametros\ConceptosRechazo\MainParametrosConceptoRechazo;

class ParametrosConceptoRechazo extends MainParametrosConceptoRechazo {
    protected $visible = [
        'cre_id',
        'cre_codigo',
        'cre_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}