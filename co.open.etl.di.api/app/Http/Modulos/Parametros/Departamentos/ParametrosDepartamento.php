<?php

namespace App\Http\Modulos\Parametros\Departamentos;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Paises\ParametrosPais;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use openEtl\Main\Models\Parametros\Departamentos\MainParametrosDepartamento;
use App\Http\Modulos\configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ParametrosDepartamento extends MainParametrosDepartamento {
    protected $visible = [
        'dep_id',
        'pai_id', 
        'dep_codigo',
        'dep_codigo_iso',
        'dep_descripcion',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getParametrosPais',
        'getUsuarioCreacion'
    ];

    // INICIO RELACION

    /**
     * Retorna el usuario creador.
     * 
     * @return BelongsTo
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion');
    }
    
     /**
      * Relación con el modelo Paramentro País.
      * 
      * @return BelongsTo
      */
    public function getParametrosPais() {
        return $this->belongsTo(ParametrosPais::class, 'pai_id');
    }

     /**
      * Relación con el modelo Paramentro Municipio.
      * 
      * @return HasMany
      */
    public function getParametrosMunicipio() {
        return $this->hasMany(ParametrosMunicipio::class, 'dep_id');
    }

     /**
      * Relación con modelo oferente.
      * 
      * @return HasMany
      */
    public function getConfiguracionOfe() {
        return $this->hasMany(ConfiguracionObligadoFacturarElectronicamente::class, 'dep_id');
    }
    // FIN RELACION
}