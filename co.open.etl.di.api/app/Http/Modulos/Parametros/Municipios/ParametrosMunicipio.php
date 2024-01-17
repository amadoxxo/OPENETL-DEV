<?php

namespace App\Http\Modulos\Parametros\Municipios;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use openEtl\Main\Models\Parametros\Municipios\MainParametrosMunicipio;

class ParametrosMunicipio extends MainParametrosMunicipio {
    protected $visible = [
        'mun_id',
        'pai_id',
        'dep_id',
        'mun_codigo',
        'mun_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getParametrosPais',
        'getParametrosDepartamento'
    ];

    /** 
     * Retorna el usuario creador.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }

    /**
     * Retorna un País dado su identificador.
     * 
     * @return BelongsTo
     */
    public function getParametrosPais() {
        return $this->belongsTo(ParametrosPais::class, 'pai_id');
    }

     /**
     * Retorna un departamento dado su identificador.
     * 
     * @return BelongsTo
     */
    public function getParametrosDepartamento() {
        return $this->belongsTo(ParametrosDepartamento::class, 'dep_id');
    }
}
