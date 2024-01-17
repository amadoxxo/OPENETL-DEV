<?php
namespace App\Http\Modulos\Sistema\Festivos;

use openEtl\Main\Models\Sistema\Festivos\MainSistemaFestivo;

class SistemaFestivo extends MainSistemaFestivo {
    protected $visible = [
        'fes_id', 
        'fes_fecha',
        'fes_descripcion',
        'usuario_creacion', 
        'fecha_creacion',
        'fecha_modificacion',
        'estado'
    ];
}
