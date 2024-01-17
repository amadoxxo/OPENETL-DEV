<?php

namespace App\Http\Modulos\Parametros\Paises;

use Illuminate\Database\Eloquent\Relations\HasMany;
use openEtl\Main\Models\Parametros\Paises\MainParametrosPais;
use App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamento;
use App\Http\Modulos\configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ParametrosPais extends MainParametrosPais {
    protected $visible = [
        'pai_id', 
        'pai_codigo',
        'pai_codigo_dos',
        'pai_codigo_numerico', 
        'pai_descripcion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getParametrosDepartamento',
        'getConfiguracionOfe'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo Departamento.
     * 
     * @@return HasMany
     */
    public function getParametrosDepartamento() {
        return $this->hasMany(ParametrosDepartamento::class, 'pai_id');
    }

    /**
     * Relación con el modelo Obligado a facturar obligatoriamente.
     * 
     * @return HasMany
     */
    public function getConfiguracionOfe() {
        return $this->hasMany(ConfiguracionObligadoFacturarElectronicamente::class, 'pai_id');
    }
    // FIN RELACIONES
}