<?php

namespace openEtl\Tenant\Models\Documentos\FacturacionWeb\EtlFacturacionWebProductos;

use openEtl\Tenant\Models\TenantMainModel;

/**
 * @property int $dmp_id
 * @property int $ofe_id
 * @property int $cpr_id
 * @property string $dmp_codigo
 * @property string $dmp_descripcion_uno
 * @property string $dmp_descripcion_uno_editable
 * @property string $dmp_descripcion_dos
 * @property string $dmp_descripcion_dos_editable
 * @property string $dmp_descripcion_tres
 * @property string $dmp_descripcion_tres_editable
 * @property string $dmp_nota
 * @property string $dmp_nota_editable
 * @property string $dmp_tipo_item
 * @property int $und_id
 * @property float $dmp_cantidad_paquete
 * @property json $dmp_valor
 * @property json $dmp_tributos
 * @property json $dmp_autoretenciones
 * @property json $dmp_retenciones_sugeridas
 * @property string $usuario_creacion
 * @property string $fecha_creacion
 * @property string $fecha_modificacion
 * @property string $estado
 * @property string $fecha_actualizacion
 */
class TenantEtlFacturacionWebProducto extends TenantMainModel {
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'etl_facturacion_web_productos';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'dmp_id';

    /**
     * @var array
     */
    public static $rules = [
        'ofe_id'                        => 'required|numeric',
        'cpr_id'                        => 'required|numeric',
        'dmp_codigo'                    => 'required|string|max:255',
        'dmp_descripcion_uno'           => 'nullable|string',
        'dmp_descripcion_uno_editable'  => 'nullable|string|max:2|in:SI,NO',
        'dmp_descripcion_dos'           => 'nullable|string',
        'dmp_descripcion_dos_editable'  => 'nullable|string|max:2|in:SI,NO',
        'dmp_descripcion_tres'          => 'nullable|string',
        'dmp_descripcion_tres_editable' => 'nullable|string|max:2|in:SI,NO',
        'dmp_nota'                      => 'nullable|string',
        'dmp_nota_editable'             => 'nullable|string|max:2|in:SI,NO',
        'dmp_tipo_item'                 => 'required|string|max:3|in:IP,IPT,PCC',
        'und_id'                        => 'required|numeric',
        'dmp_cantidad_paquete'          => 'nullable|numeric|regex:/^[0-9]{1,8}(\.[0-9]{1,2})?$/',
        'dmp_valor'                     => 'nullable|json',
        'dmp_tributos'                  => 'nullable|json',
        'dmp_autoretenciones'           => 'nullable|json',
        'dmp_retenciones_sugeridas'     => 'nullable|json'
    ];

    /**
     * @var array
     */
    public static $rulesUpdate = [
        'ofe_id'                        => 'required|numeric',
        'cpr_id'                        => 'required|numeric',
        'dmp_codigo'                    => 'required|string|max:255',
        'dmp_descripcion_uno'           => 'nullable|string',
        'dmp_descripcion_uno_editable'  => 'nullable|string|max:2|in:SI,NO',
        'dmp_descripcion_dos'           => 'nullable|string',
        'dmp_descripcion_dos_editable'  => 'nullable|string|max:2|in:SI,NO',
        'dmp_descripcion_tres'          => 'nullable|string',
        'dmp_descripcion_tres_editable' => 'nullable|string|max:2|in:SI,NO',
        'dmp_nota'                      => 'nullable|string',
        'dmp_nota_editable'             => 'nullable|string|max:2|in:SI,NO',
        'dmp_tipo_item'                 => 'required|string|max:3|in:IP,IPT,PCC',
        'und_id'                        => 'required|numeric',
        'dmp_cantidad_paquete'          => 'nullable|numeric|regex:/^[0-9]{1,8}(\.[0-9]{1,2})?$/',
        'dmp_valor'                     => 'nullable|json',
        'dmp_tributos'                  => 'nullable|json',
        'dmp_autoretenciones'           => 'nullable|json',
        'dmp_retenciones_sugeridas'     => 'nullable|json',
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'ofe_id',
        'cpr_id',
        'dmp_codigo',
        'dmp_descripcion_uno',
        'dmp_descripcion_uno_editable',
        'dmp_descripcion_dos',
        'dmp_descripcion_dos_editable',
        'dmp_descripcion_tres',
        'dmp_descripcion_tres_editable',
        'dmp_nota',
        'dmp_nota_editable',
        'dmp_tipo_item',
        'und_id',
        'dmp_cantidad_paquete',
        'dmp_valor',
        'dmp_tributos',
        'dmp_autoretenciones',
        'dmp_retenciones_sugeridas',
        'usuario_creacion',
        'estado'
    ];

    /**
     * @var array
     */
    protected $visible = [
        'dmp_id',
        'ofe_id',
        'cpr_id',
        'dmp_codigo',
        'dmp_descripcion_uno',
        'dmp_descripcion_uno_editable',
        'dmp_descripcion_dos',
        'dmp_descripcion_dos_editable',
        'dmp_descripcion_tres',
        'dmp_descripcion_tres_editable',
        'dmp_nota',
        'dmp_nota_editable',
        'dmp_tipo_item',
        'und_id',
        'dmp_cantidad_paquete',
        'dmp_valor',
        'dmp_tributos',
        'dmp_autoretenciones',
        'dmp_retenciones_sugeridas',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion'
    ];

    protected $casts = [
        'dmp_valor'                 => 'array',
        'dmp_tributos'              => 'array',
        'dmp_autoretenciones'       => 'array',
        'dmp_retenciones_sugeridas' => 'array'
    ];
}
