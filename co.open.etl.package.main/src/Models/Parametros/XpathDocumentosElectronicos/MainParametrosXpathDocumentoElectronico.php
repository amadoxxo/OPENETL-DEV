<?php

namespace openEtl\Main\Models\Parametros\XpathDocumentosElectronicos;

use openEtl\Main\Models\MainModel;

/**
 * @property int $xde_id
 * @property string $xde_aplica_para
 * @property string $xde_descripcion
 * @property string $xde_xpath
 * @property int $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class MainParametrosXpathDocumentoElectronico extends MainModel {
    /**
     * Tabla asociada con el modelo.
     * 
     * @var string
     */
    protected $table = 'etl_xpath_documentos_electronicos';

    /**
     * Llave primaria del modelo.
     * 
     * @var string
     */
    protected $primaryKey = 'xde_id';

    /**
     * Reglas de creación en el modelo.
     * 
     * @var array
     */
    public static $rules = [
        'xde_aplica_para' => 'required|string|max:20',
        'xde_descripcion' => 'required|string|max:255',
        'xde_xpath'       => 'required|string|unique:etl_xpath_documentos_electronicos,xde_xpath'
    ];

    /**
     * Reglas de actualización en el modelo.
     * 
     * @var array
     */
    public static $rulesUpdate = [
        'xde_aplica_para' => 'nullable|string|max:20',
        'xde_descripcion' => 'nullable|string|max:255',
        'xde_xpath'       => 'nullable|string|unique:etl_xpath_documentos_electronicos,xde_xpath',
        'estado'          => 'nullable|in:ACTIVO,INACTIVO'
    ];

    /**
     * Atributos asignables en masa.
     * 
     * @var array
     */
    protected $fillable = [
        'xde_id',
        'xde_aplica_para',
        'xde_descripcion',
        'xde_xpath',
        'usuario_creacion',
        'estado'
    ];

    /**
     * Atributos visibles del modelo.
     * 
     * @var array
     */
    protected $visible = [
        'xde_id',
        'xde_aplica_para',
        'xde_descripcion',
        'xde_xpath',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];
}
