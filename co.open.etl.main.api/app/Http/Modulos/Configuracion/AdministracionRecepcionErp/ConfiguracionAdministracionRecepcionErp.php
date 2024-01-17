<?php

namespace App\Http\Modulos\Configuracion\AdministracionRecepcionErp;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronico;
use App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoTenant;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use openEtl\Tenant\Models\Configuracion\Recepcion\RepAdministracionTransmisionErp\TenantRepAdministracionTransmisionErp;

class ConfiguracionAdministracionRecepcionErp extends TenantRepAdministracionTransmisionErp {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'ate_id',
        'ofe_id',
        'ate_erp',
        'ate_grupo',
        'ate_descripcion',
        'ate_aplica_para',
        'ate_deben_aplica',
        'xde_id_main',
        'xde_id_tenant',
        'ate_condicion',
        'ate_valor',
        'ate_accion',
        'ate_accion_titulo',
        'xde_accion_id_main',
        'xde_accion_id_tenant',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getParametrosXpathDocumentoElectronico',
        'getParametrosXpathDocumentoElectronicoTenant',
        'getConfiguracionObligadoFacturarElectronicamente'
    ];

    // INICIO RELACIONES
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

    /**
     * Relación con el modelo Obligado a Facturar Electrónicamente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id')->select([
            'ofe_id',
            'sft_id',
            'tdo_id',
            'toj_id',
            'ofe_identificacion',
            'ofe_razon_social',
            'ofe_nombre_comercial',
            'ofe_primer_apellido',
            'ofe_segundo_apellido',
            'ofe_primer_nombre',
            'ofe_otros_nombres',
        ]);
    }

    /**
     * Relación con el modelo Xpath Documento Electrónico de Main.
     *
     * @return BelongsTo 
     */
    public function getParametrosXpathDocumentoElectronico() {
        return $this->belongsTo(ParametrosXpathDocumentoElectronico::class, 'xde_id_main', 'xde_id')->select([
            'xde_id',
            'xde_aplica_para',
            'xde_descripcion',
            'xde_xpath'
        ]);
    }

    /**
     * Relación con el modelo Xpath Documento Electrónico de Tenant.
     *
     * @return BelongsTo
     */
    public function getParametrosXpathDocumentoElectronicoTenant() {
        return $this->belongsTo(ParametrosXpathDocumentoElectronicoTenant::class, 'xde_id_tenant', 'xde_id')->select([
            'xde_id',
            'ofe_id',
            'xde_aplica_para',
            'xde_descripcion',
            'xde_xpath'
        ]);
    }
    // FIN RELACIONES

    // SCOPES
    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     * 
     * @param Builder $query
     * @param string $texto cadena de texto a buscar
     * @return Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        $query = $query->where( function ($query) use ($texto) {
            $query->where('ate_erp', 'like', '%'.$texto.'%')
            ->orWhere('ate_descripcion', 'like', '%'.$texto.'%')
            ->orWhere('fecha_creacion', 'like', '%'.$texto.'%')
            ->orWhere('fecha_modificacion', 'like', '%'.$texto.'%')
            ->orWhere('ofe_id', 'like', '%'.$texto.'%')
            ->orWhere('estado', $texto)
            ->orWhere(function($query) use ($texto){
                $query->with('getConfiguracionObligadoFacturarElectronicamente')
                ->wherehas('getConfiguracionObligadoFacturarElectronicamente', function ($query) use ($texto) {
                    $query->where('ofe_razon_social', 'like', '%'.$texto.'%');
                });
            });
        });

        return $query;
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas
     * 
     * @param Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'receptor', $ordenDireccion = 'asc'){

        switch($columnaOrden) {
            case 'receptor':
                $orderBy = 'ofe_id';
                break;
            case 'erp':
                $orderBy = 'ate_erp';
                break;
            case 'regla':
                $orderBy = 'ate_descripcion';
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
            $ordenDireccion = 'desc';

        return $query->orderBy($orderBy, $ordenDireccion);
    }
    // END SCOPES
}
