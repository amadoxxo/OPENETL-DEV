<?php

namespace App\Http\Models;

use openEtl\Main\Models\MainUser;
use App\Http\Models\AuthBaseDatos;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'getBaseDatosRg',
        'getRolesUsuario'
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
     * Relación con el modelo Base Datos y representación gráfica.
     *
     * @return BelongsTo
     */
    public function getBaseDatosRg() {
        return $this->belongsTo(AuthBaseDatos::class, 'bdd_id_rg', 'bdd_id');
    }

    /**
     * Relación con el modelo de roles de usuario.
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
     * Verifica si un usuario tiene el rol superadmin.
     *
     * @return boolean
     */
    public function esSuperadmin() {
        $superadmin = $this->getRolesUsuario()
            ->has('getSistemaRol')
            ->with('getSistemaRol')
            ->get()
            ->pluck('getSistemaRol')
            ->filter(function ($rol) {
                return $rol->rol_codigo === 'superadmin' && $rol->estado === 'ACTIVO';
            })
            ->values()
            ->count();

        return $superadmin > 0 ? true : false;
    }

    /**
     * Verifica si un usuario tiene el rol usuarioma (Usuario Mesa de Ayuda).
     *
     * @return boolean
     */
    public function esUsuarioMA() {
        $UsuarioMA = $this->getRolesUsuario()
            ->has('getSistemaRol')
            ->with('getSistemaRol')
            ->get()
            ->pluck('getSistemaRol')
            ->filter(function ($rol) {
                return $rol->rol_codigo === 'usuarioma' && $rol->estado === 'ACTIVO';
            })
            ->values()
            ->count();

        return $UsuarioMA > 0 ? true : false;
    }

    /**
     * Retorna todos los permisos del usuario autenticado.
     */
    public function usuarioPermisos() {
        return $this->getRolesUsuario()
            ->has('getSistemaRol.getRolPermisos.getSistemaRecurso')
            ->with('getSistemaRol.getRolPermisos.getSistemaRecurso')
            ->where('estado','ACTIVO')
            ->get()            
            ->pluck('getSistemaRol')->filter(function($rol){
                return $rol->estado === 'ACTIVO';
            })            
            ->pluck('getRolPermisos')
            ->collapse()
            ->filter(function($rolPermiso){
                return $rolPermiso->estado === 'ACTIVO';
            })
            ->pluck('getSistemaRecurso')->filter(function($recurso){
                return $recurso->estado === 'ACTIVO';
            });
    }
}
