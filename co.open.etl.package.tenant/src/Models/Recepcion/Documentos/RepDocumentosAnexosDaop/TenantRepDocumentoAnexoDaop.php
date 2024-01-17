<?php

namespace openEtl\Tenant\Models\Recepcion\Documentos\RepDocumentosAnexosDaop;

use openEtl\Tenant\Models\TenantMainModel;

/**
 * @property int $dan_id
 * @property int $cdo_id
 * @property string $dan_lote
 * @property string $dan_uuid
 * @property string $dan_tamano
 * @property string $dan_nombre
 * @property string $dan_descripcion
 * @property string $dan_envio_openecm
 * @property string $dan_respuesta_envio_openecm
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class TenantRepDocumentoAnexoDaop extends TenantMainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'rep_documentos_anexos_daop';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'dan_id';

    /**
     * @var array
     */
    public static $rules = [
        'cdo_id'                       => 'required|numeric',
        'dan_lote'                     => 'required|string|max:60',
        'dan_uuid'                     => 'required|string|max:255',
        'dan_tamano'                   => 'required|string|max:10',
        'dan_nombre'                   => 'required|string|max:255',
        'dan_descripcion'              => 'required|string|max:255',
        'dan_envio_openecm'            => 'nullable|string|max:2',
        'dan_respuesta_envio_openecm'  => 'nullable|string'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'cdo_id'                       => 'required|numeric',
        'dan_lote'                     => 'required|string|max:60',
        'dan_uuid'                     => 'required|string|max:255',
        'dan_tamano'                   => 'required|string|max:10',
        'dan_nombre'                   => 'required|string|max:255',
        'dan_descripcion'              => 'required|string|max:255',
        'dan_envio_openecm'            => 'nullable|string|max:2',
        'dan_respuesta_envio_openecm'  => 'nullable|string',
        'estado'                       => 'required|in:ACTIVO,INACTIVO'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'cdo_id',
        'dan_lote',
        'dan_uuid',
        'dan_tamano',
        'dan_nombre',
        'dan_descripcion',
        'dan_envio_openecm',
        'dan_respuesta_envio_openecm',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'dan_id',
        'cdo_id',
        'dan_lote',
        'dan_uuid',
        'dan_tamano',
        'dan_nombre',
        'dan_descripcion',
        'dan_envio_openecm',
        'dan_respuesta_envio_openecm',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
