<?php

namespace App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual;

use App\Http\Models\User;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Models\Recepcion\RPA\EtlEmailsProcesamientoManual\TenantEtlEmailProcesamientoManual;

class EtlEmailProcesamientoManual extends TenantEtlEmailProcesamientoManual {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'epm_id',
        'ofe_identificacion',
        'epm_subject',
        'epm_id_carpeta',
        'epm_fecha_correo',
        'epm_cuerpo_correo',
        'epm_procesado',
        'epm_procesado_usuario',
        'epm_procesado_fecha',
        'epm_observaciones',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'getUsuarioCreacion'
    ];

    /**
     * Relación con el modelo usuario.
     * @var Illuminate\Database\Eloquent\Model
     */
    public function getUsuarioCreacion() {
        return $this->belongsTo(User::class, 'usuario_creacion')->select([
            'usu_id',
            'usu_nombre',
            'usu_identificacion',
        ]);
    }

    /**
     * Local Scope que permite realizar una búsqueda general sobre determinados campos del modelo
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $texto cadena de texto a buscar
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBusquedaGeneral($query, $texto) {
        return $query->orWhere( function ($query) use ($texto) {
            $query->where('ofe_identificacion', 'like', '%'.$texto.'%')
                ->orWhere('epm_subject', 'like', '%'.$texto.'%')
                ->orWhere('epm_id_carpeta', 'like', '%'.$texto.'%')
                ->orWhere('epm_fecha_correo', 'like', '%'.$texto.'%')
                ->orWhere('estado', $texto);
        });
    }

    /**
     * Local Scope que permite ordenar querys por diferentes columnas.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param $columnaOrden string columna sobre la cual se debe ordenar
     * @param $ordenDireccion string indica la dirección de ordenamiento
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByColumn($query, $columnaOrden = 'modificado', $ordenDireccion = 'desc'){
        switch($columnaOrden){
            case 'ofe_identificacion':
                $orderBy = DB::Raw('abs(ofe_identificacion)');
                break;
            case 'subject':
                $orderBy = 'epm_subject';
                break;
            case 'procesado':
                $orderBy = 'epm_procesado';
                break;
            case 'fecha_correo':
                $orderBy = 'epm_fecha_correo';
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
}
