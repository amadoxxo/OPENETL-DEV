<?php

namespace openEtl\Tenant\Models\Recepcion\RPA\EtlEmailsProcesamientoManual;

use openEtl\Tenant\Models\TenantMainModel;

/**
 * @property int epm_id
 * @property string ofe_identificacion
 * @property text epm_subject
 * @property string epm_id_carpeta
 * @property datetime epm_fecha_correo
 * @property text epm_cuerpo_correo
 * @property string epm_procesado
 * @property integer epm_procesado_usuario
 * @property datetime epm_procesado_fecha
 * @property int usuario_creacion
 * @property datetime fecha_creacion
 * @property datetime fecha_modificacion
 * @property string estado
 * @property datetime fecha_actualizacion
 */
class TenantEtlEmailProcesamientoManual extends TenantMainModel {
    /**
     * Tabla relacionada con el modelo.
     *
     * @var string
     */
    protected $table = 'etl_emails_procesamiento_manual';

    /**
     * Llave primaria de la tabla.
     *
     * @var string
     */
    protected $primaryKey = 'epm_id';

    /**
     * Reglas de validación para el recurso crear.
     *
     * @var array
     */
    public static $rules = [
        'ofe_identificacion'        => 'required|string|max:10',
        'epm_subject'               => 'required|string',
        'epm_id_carpeta'            => 'required|string|max:255',
        'epm_fecha_correo'          => 'required|date_format:Y-m-d H:i:s',
        'epm_cuerpo_correo'         => 'required|string',
        'epm_procesado'             => 'nullable|string|max:2',
        'epm_procesado_usuario'     => 'nullable|numeric',
        'epm_procesado_fecha'       => 'nullable|date_format:Y-m-d H:i:s',
    ];

    /**
     * Reglas de validación para el recurso actualizar.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'ofe_identificacion'        => 'nullable|string|max:10',
        'epm_subject'               => 'nullable|string',
        'epm_id_carpeta'            => 'nullable|string|max:255',
        'epm_fecha_correo'          => 'nullable|date_format:Y-m-d H:i:s',
        'epm_cuerpo_correo'         => 'nullable|string',
        'epm_procesado'             => 'nullable|string|max:2',
        'epm_procesado_usuario'     => 'nullable|numeric',
        'epm_procesado_fecha'       => 'nullable|date_format:Y-m-d H:i:s',
        'estado'                    => 'nullable|string|in:ACTIVO,INACTIVO'
    ];

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'ofe_identificacion',
        'epm_subject',
        'epm_id_carpeta',
        'epm_fecha_correo',
        'epm_cuerpo_correo',
        'epm_procesado',
        'epm_procesado_usuario',
        'epm_procesado_fecha',
        'usuario_creacion',
        'estado'
    ];

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
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
