<?php

namespace App\Http\Modulos\Sistema\Agendamiento;

use App\Http\Modulos\Documentos\ETLCabeceraDocumentosDaop\ETLCabeceraDocumentosDaop;
use App\Http\Models\User;
use openEtl\Main\Models\MainAdoAgendamiento;

class AdoAgendamiento extends MainAdoAgendamiento{
    protected $visible = [
        'age_id', 
        'usu_id',
        'bdd_id',
        'age_proceso',
        'age_cantidad_documentos',
        'age_prioridad',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getEmisionDocumentos',
        'getUser'
    ];

    // Relaciones
    public function getEmisionDocumentos() {
        return $this->hasMany(ETLCabeceraDocumentosDaop::class, 'age_id');
    }

    public function getUser() {
        return $this->belongsTo(User::class, 'usu_id');
    }

    /**
     * Mutador que permite establecer la prioridad del agendamiento teniendo en cuenta el proceso y el valor de la variable del sistema ETL_PRIORIDAD_AGENDAMIENTOS para la base de datos del usuario autenticado.
     *
     * @param mixed $value Valor que llega para la columna age_prioridad
     * @return void
     */
    public function setAgePrioridadAttribute($value) {
      if (($this->attributes['age_proceso'] == 'EDI' || $this->attributes['age_proceso'] == 'UBL') && $value == null) {
            $baseDatos   = auth()->user()->getBaseDatos->bdd_id;
            $baseDatosRg = auth()->user()->getBaseDatos->bdd_id_rg;

            if(!empty($baseDatosRg))
                $baseDatos = $baseDatosRg;

            $agePrioridades = json_decode(config('variables_sistema.ETL_PRIORIDAD_AGENDAMIENTOS'), true);
            $prioDefault    = array_search('default', $agePrioridades);

            $prioridad = null;
            foreach($agePrioridades as $index => $agePrio) {
                if($index != $prioDefault && in_array($baseDatos, $agePrio))
                    $prioridad = $index;
            };

            $this->attributes['age_prioridad'] = !is_null($prioridad) ? $prioridad : $prioDefault;
        } else {
            $this->attributes['age_prioridad'] = $value;
        }
    }
}
