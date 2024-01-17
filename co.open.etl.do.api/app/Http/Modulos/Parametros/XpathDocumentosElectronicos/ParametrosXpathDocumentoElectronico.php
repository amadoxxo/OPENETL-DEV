<?php

namespace App\Http\Modulos\Parametros\XpathDocumentosElectronicos; 

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use openEtl\Main\Models\Parametros\XpathDocumentosElectronicos\MainParametrosXpathDocumentoElectronico;

class ParametrosXpathDocumentoElectronico extends MainParametrosXpathDocumentoElectronico {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'xde_id',
        'xde_aplica_para',
        'xde_descripcion',
        'xde_xpath',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

    // INICIO RELACION
    /**
     * Relación con el usuario de creación.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }
    // FIN RELACION
}
