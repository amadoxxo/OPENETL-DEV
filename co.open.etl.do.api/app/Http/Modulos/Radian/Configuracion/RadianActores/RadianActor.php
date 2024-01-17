<?php

namespace App\Http\Modulos\Radian\Configuracion\RadianActores;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use openEtl\Tenant\Models\Radian\RadianActores\TenantRadianActor;
use App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologico;

class RadianActor extends TenantRadianActor {
    protected $visible = [
        'act_id',
        'tdo_id',
        'toj_id',
        'act_identificacion',
        'act_razon_social',
        'act_nombre_comercial',
        'act_primer_apellido',
        'act_segundo_apellido',
        'act_primer_nombre',
        'act_otros_nombres',
        'pai_id',
        'dep_id',
        'mun_id',
        'cpo_id',
        'act_direccion',
        'act_telefono',
        'act_correo',
        'sft_id',
        'act_archivo_certificado',
        'act_password_certificado',
        'act_vencimiento_certificado',
        'act_ticket_vencimiento',
        'tat_id',
        'act_correos_notificacion',
        'act_notificacion_un_solo_correo',
        'act_roles',
        'bdd_id_rg',
        'act_prioridad_agendamiento',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getConfiguracionSoftwareProveedorTecnologico',
        'getUsuarioCreacion',
        'getTipoOrganizacionJuridica',
        'getParametrosDepartamento',
        'getParametrosMunicipio',
        'getParametrosPais',
        'getTiempoAceptacionTacita',
        'getCodigoPostal',
        'getParametroTipoDocumento',
        'getBaseDatosRg'
    ];

    /**
     * Obtiene la razon social del actor.
     *
     * @param  string $value
     * @return string
     */
    public function getActRazonSocialAttribute($value): string {
        if(empty($value)){
            return str_replace('  ', ' ', trim($this->act_primer_nombre.' '.$this->act_otros_nombres.' '.$this->act_primer_apellido.' '.$this->act_segundo_apellido));
        } else {
            return $value;
        }
    }

    // INICIO RELACIONES
    /**
     * Relación con el modelo Software Proveeedor Tecnológico.
     *
     * @return BelongsTo
     */
    public function getConfiguracionSoftwareProveedorTecnologico() {
        return $this->belongsTo(ConfiguracionSoftwareProveedorTecnologico::class, 'sft_id')->select([
            'sft_id',
            'sft_identificador',
            'sft_pin',
            'sft_nombre',
        ]);
    }

    /**
     * Relación con el modelo usuario.
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

    /**
     * Relación con el modelo base de datos.
     *
     * @return BelongsTo
     */
    public function getBaseDatosRg() {
        return $this->belongsTo(AuthBaseDatos::class, 'bdd_id_rg', 'bdd_id');
    }
    // FIN RELACIONES

    // SCOPES
    /**
     * Local Scope que permite filtrar los Actores asociados a la base de datos del usuario autenticado.
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeValidarAsociacionBaseDatos($query): Builder {
        return $query->when(!empty(auth()->user()->bdd_id_rg), function ($query) {
                return $query->where('bdd_id_rg', auth()->user()->bdd_id_rg);
            }, function ($query) {
                return $query->whereNull('bdd_id_rg');
            });
    }
    // END SCOPES
}