<?php

namespace App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use openEtl\Tenant\Models\Recepcion\Configuracion\UsuariosPortalProveedores\TenantRepUsuarioPortalProveedores;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RepUsuarioPortalProveedores extends TenantRepUsuarioPortalProveedores {
    protected $visible = [
        'upp_id',
        'ofe_id',
        'ofe_identificacion',
        'pro_id',
        'pro_identificacion',
        'upp_identificacion',
        'upp_nombre',
        'upp_correo',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionProveedor'
    ];

    // INICIO RELACIONES
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
     * Relación con el modelo Proveedor.
     *
     * @return BelongsTo
     */
    public function getConfiguracionProveedor() {
        return $this->belongsTo(ConfiguracionProveedor::class, 'pro_id');
    }

    /**
     * Relación con el modelo Obligado a Facturar Electrónicamente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }
    // FIN RELACIONES

    // SCOPES
    /**
     * Local Scope que permite ordenar querys por diferentes columnas
     * 
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'pro_identificacion', $ordenDireccion = 'asc'){
        switch($columnaOrden){
            case 'pro_identificacion':
                $orderBy = 'pro_identificacion';
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
