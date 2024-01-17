<?php

namespace App\Http\Modulos\Configuracion\AutorizacionesEventosDian;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajo;
use openEtl\Tenant\Models\Configuracion\AutorizacionesEventosDian\TenantConfiguracionAutorizacionEventoDian;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ConfiguracionAutorizacionEventoDian extends TenantConfiguracionAutorizacionEventoDian {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'use_id',
        'ofe_id',
        'pro_id',
        'gtr_id',
        'usu_id',
        'tdo_id',
        'use_identificacion',
        'use_nombres',
        'use_apellidos',
        'use_cargo',
        'use_area',
        'use_acuse_recibo',
        'use_reclamo',
        'use_recibo_bien',
        'use_aceptacion_expresa',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getTipoDocumento',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getUsuarioAutorizacionEventoDian',
        'getConfiguracionProveedor',
        'getConfiguracionGrupoTrabajo'
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
            'usu_email',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }

    /**
     * Relación con el modelo usuario para el usuario del evento.
     *
     * @return BelongsTo
     */
    public function getUsuarioAutorizacionEventoDian() {
        return $this->belongsTo(User::class, 'usu_id');
    }

    /**
     * Relación con el modelo proveedor.
     *
     * @return BelongsTo
     */
    public function getConfiguracionProveedor() {
        return $this->belongsTo(ConfiguracionProveedor::class, 'pro_id');
    }

    /**
     * Relación con el modelo grupo de trabajo.
     *
     * @return BelongsTo
     */
    public function getConfiguracionGrupoTrabajo() {
        return $this->belongsTo(ConfiguracionGrupoTrabajo::class, 'gtr_id');
    }

    /**
     * Relación con Tipo Documento.
     *
     * @return BelongsTo
     */
    public function getTipoDocumento() {
        return $this->belongsTo(ParametrosTipoDocumento::class, 'tdo_id')->select([
            'tdo_id',
            'tdo_codigo',
            'tdo_descripcion'
        ]);
    }

    /**
     * Relación con el Ambiente Destino.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }
    // FIN RELACIONES

    // SCOPES
    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo.
     *
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        $query = $query->where( function ($query) use ($texto) {
            $query->where('etl_autorizaciones_eventos_dian.use_nombres', 'like', '%' . $texto . '%')
            ->orWhere('etl_autorizaciones_eventos_dian.use_apellidos', 'like', '%' . $texto . '%')
            ->orWhere('etl_autorizaciones_eventos_dian.use_identificacion', 'like', '%' . $texto . '%')
            ->orWhere('etl_autorizaciones_eventos_dian.estado', 'like', '%' . $texto . '%')
            ->orWhere(function($query) use ($texto){
                $query->with('getConfiguracionObligadoFacturarElectronicamente')
                ->wherehas('getConfiguracionObligadoFacturarElectronicamente', function ($query) use ($texto) {
                    $query->where('ofe_razon_social', 'like', '%' . $texto . '%')
                        ->orWhere('ofe_primer_apellido', 'like', '%' . $texto . '%')
                        ->orWhere('ofe_segundo_apellido', 'like', '%' . $texto . '%')
                        ->orWhere('ofe_primer_nombre', 'like', '%' . $texto . '%')
                        ->orWhere('ofe_otros_nombres', 'like', '%' . $texto . '%');
                });
            })
            ->orWhere(function($query) use ($texto){
                $query->with('getConfiguracionProveedor')
                ->wherehas('getConfiguracionProveedor', function ($query) use ($texto) {
                    $query->where('pro_razon_social', 'like', '%' . $texto . '%')
                        ->orWhere('pro_primer_apellido', 'like', '%' . $texto . '%')
                        ->orWhere('pro_segundo_apellido', 'like', '%' . $texto . '%')
                        ->orWhere('pro_primer_nombre', 'like', '%' . $texto . '%')
                        ->orWhere('pro_otros_nombres', 'like', '%' . $texto . '%');
                });
            })
            ->orWhere(function($query) use ($texto){
                $query->with('getConfiguracionGrupoTrabajo')
                ->wherehas('getConfiguracionGrupoTrabajo', function ($query) use ($texto) {
                    $query->where('gtr_codigo', 'like', '%' . $texto . '%')
                        ->orWhere('gtr_nombre', 'like', '%' . $texto . '%');
                });
            })
            ->orWhere(function($query) use ($texto){
                $query->whereRaw('
                    exists (
                        select * from `etl_openmain`.`auth_usuarios` where `etl_autorizaciones_eventos_dian`.`usu_id` = `etl_openmain`.`auth_usuarios`.`usu_id`
                        and (
                            `etl_openmain`.`auth_usuarios`.`usu_nombre` like "%' . $texto . '%"
                            or `etl_openmain`.`auth_usuarios`.`usu_email` like "%' . $texto . '%"
                            or `etl_openmain`.`auth_usuarios`.`estado` like "%' . $texto . '%"
                        )
                    )'
                );
            });
        });
        
        return $query;
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas.
     *
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'codigo', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'ofe':
                return $query->join('etl_obligados_facturar_electronicamente as ofes', 'ofes.ofe_id', '=', 'etl_autorizaciones_eventos_dian.ofe_id')
                    ->orderBy('ofes.ofe_razon_social', $ordenDireccion);
                break;
            case 'proveedor':
                return $query->leftJoin('rep_proveedores as proveedor', 'proveedor.pro_id', '=', 'etl_autorizaciones_eventos_dian.pro_id')
                    ->orderBy('proveedor.pro_razon_social', $ordenDireccion);
                break;
            case 'grupo_trabajo':
                return $query->leftJoin('etl_grupos_trabajo as grupo_trabajo', 'grupo_trabajo.gtr_id', '=', 'etl_autorizaciones_eventos_dian.gtr_id')
                    ->orderBy('grupo_trabajo.gtr_nombre', $ordenDireccion);
                break;
            case 'identificacion':
                return $query->orderBy('use_identificacion', $ordenDireccion);
                break;
            case 'nombres':
                return $query->orderBy('use_nombres', $ordenDireccion)
                    ->orderBy('use_apellidos', $ordenDireccion);
                break;
            case 'email':
                return $query->leftJoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'etl_autorizaciones_eventos_dian.usu_id')
                    ->orderBy('usuarios.usu_email', $ordenDireccion);
                break;
            case 'estado_usuario':
                return $query->leftJoin('etl_openmain.auth_usuarios as usuarios', 'usuarios.usu_id', '=', 'etl_autorizaciones_eventos_dian.usu_id')
                    ->orderBy('usuarios.estado', $ordenDireccion);
                break;
            case 'estado':
                return $query->orderBy('estado', $ordenDireccion);
                break;
            default:
                $orderBy = 'fecha_modificacion';
                break;
        }

        if(strtolower($ordenDireccion) !== 'asc' && strtolower($ordenDireccion) !== 'desc')
            $ordenDireccion = 'desc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }
    // END SCOPES
}