<?php

namespace App\Http\Models;

use openEtl\Main\Models\MainUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Sistema\RolesUsuario\SistemaRolesUsuario;

class User extends MainUser {
    protected $visible = [
        'usu_id',
        'usu_nombre',
        'usu_email',
        'usu_identificacion',
        'usu_direccion',
        'usu_telefono',
        'usu_movil',
        'usu_type',
        'usu_relaciones',
        'bdd_id',
        'bdd_id_rg',
        'estado',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'getBaseDatos',
        'getBaseDatosRg'
    ];

    /**
     * Relación con el modelo Base Datos.
     *
     * @return BelongsTo
     */
    public function getBaseDatos() {
        return $this->belongsTo(AuthBaseDatos::class, 'bdd_id');
    }

    /**
     * Relación con el modelo Base Datos de la representación gráfica.
     *
     * @return BelongsTo
     */
    public function getBaseDatosRg() {
        return $this->belongsTo(AuthBaseDatos::class, 'bdd_id_rg', 'bdd_id');
    }

    /**
     * Relación con el modelo roles usuarios.
     *
     * @return HasMany
     */
    public function getRolesUsuario() {
        return $this->hasMany(SistemaRolesUsuario::class, 'usu_id')->select([
            'usu_id',
            'rol_id'
        ]);
    }

    /**
     * Retorna todos los permisos del usuario autenticado
     */
    public function usuarioPermisos() {
        return $this->getRolesUsuario()
            ->has('getSistemaRol.getRolPermisos.getSistemaRecurso')
            ->with('getSistemaRol.getRolPermisos.getSistemaRecurso')
            ->where('estado', 'ACTIVO')
            ->get()
            ->pluck('getSistemaRol')->filter(function($rol) {
                return $rol->estado === 'ACTIVO';
            })
            ->pluck('getRolPermisos')
            ->collapse()
            ->filter(function($rolPermiso) {
                return $rolPermiso->estado === 'ACTIVO';
            })
            ->pluck('getSistemaRecurso')->filter(function($recurso) {
                return $recurso->estado === 'ACTIVO';
            });
    }

    /**
     * Permite identificar si la BD asociada al usuario esta configurada o no para particionamiento.
     * 
     * @return bool
     */
    public function baseDatosTieneParticionamiento(): bool {
        $particionamiento = AuthBaseDatos::select('bdd_aplica_particionamiento_emision')
            ->whereNotNull('bdd_inicio_particionamiento_emision')
            ->when(!empty(auth()->user()->bdd_id_rg), function ($query) {
                return $query->where('bdd_id', auth()->user()->bdd_id_rg);
            }, function ($query) {
                return $query->where('bdd_id', auth()->user()->bdd_id);
            })
            ->first();

        if(!is_null($particionamiento) && $particionamiento->bdd_aplica_particionamiento_emision == 'SI')
            return true;
        else
            return false;
    }
}
