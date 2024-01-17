<?php

namespace App\Http\Modulos\Configuracion\UsuariosPortalClientes;

use App\Http\Models\User;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use openEtl\Tenant\Models\Configuracion\UsuariosPortalClientes\TenantEtlUsuarioPortalClientes;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlUsuarioPortalClientes extends TenantEtlUsuarioPortalClientes {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'upc_id',
        'ofe_id',
        'ofe_identificacion',
        'adq_id',
        'adq_identificacion',
        'upc_identificacion',
        'upc_nombre',
        'upc_correo',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionAdquirente'
    ];

    // INICIO RELACIONES
    /**
     * Relación con el modelo usuario.
     *
     * @return BelongsTo
     * @var Model
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }

    /**
     * Relación con el modelo Adquirente.
     *
     * @return BelongsTo
     * @var Model
     */
    public function getConfiguracionAdquirente() {
        return $this->belongsTo(ConfiguracionAdquirente::class, 'adq_id');
    }

    /**
     * Relación con el modelo Obligado a Facturar Electronicamente.
     *
     * @return BelongsTo
     * @var Model
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    // SCOPES
    /**
     * Local Scope que permite ordenar querys por diferentes columnas
     * 
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'adq_identificacion', $ordenDireccion = 'asc'){
        switch($columnaOrden){
            case 'adq_identificacion':
                $orderBy = 'adq_identificacion';
                break;
            case 'ofe_identificacion':
                $orderBy = 'ofe_identificacion';
                break;
            case 'creado':
                $orderBy = 'fecha_creacion';
                break;
            case 'modificado':
                $orderBy = 'fecha_modificacion';
                break;
            case 'estado':
                $orderBy = 'estado';
                break;
            default:
                $orderBy = 'fecha_modificacion';
                break;
        }

        if( strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'asc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }
}
