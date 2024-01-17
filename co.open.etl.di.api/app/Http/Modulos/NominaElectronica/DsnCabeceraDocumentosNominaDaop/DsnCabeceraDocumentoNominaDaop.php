<?php

namespace App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop;

use App\Http\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\NominaElectronica\DsnEstadosDocumentosDaop\DsnEstadoDocumentoDaop;
use App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleador;
use App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajador;
use openEtl\Tenant\Models\NominaElectronica\DsnCabeceraDocumentosNominaDaop\TenantDsnCabeceraDocumentoNominaDaop;

class DsnCabeceraDocumentoNominaDaop extends TenantDsnCabeceraDocumentoNominaDaop {
    /**
     * Los atributos que deberían estar visibles.
     * 
     * @var array
     */
    protected $visible = [
        'cdn_id',
        'cdn_origen',
        'cdn_clasificacion',
        'cdn_lote',
        'emp_id',
        'tra_id',
        'tde_id',
        'cdn_aplica_novedad',
        'ntn_id',
        'cdn_prefijo',
        'cdn_consecutivo',
        'npe_id',
        'cdn_fecha_emision',
        'cdn_fecha_inicio_liquidacion',
        'cdn_fecha_fin_liquidacion',
        'cdn_tiempo_laborado',
        'pai_id',
        'dep_id',
        'mun_id',
        'fpa_id',
        'mpa_id',
        'cdn_fechas_pago',
        'cdn_notas',
        'mon_id',
        'cdn_trm',
        'cdn_redondeo',
        'cdn_devengados',
        'cdn_deducciones',
        'cdn_total_comprobante',
        'cdn_cune',
        'cdn_qr',
        'cdn_signaturevalue',
        'cdn_prefijo_predecesor',
        'cdn_consecutivo_predecesor',
        'cdn_fecha_emision_predecesor',
        'cdn_cune_predecesor',
        'cdn_procesar_documento',
        'cdn_fecha_procesar_documento',
        'cdn_fecha_validacion_dian',
        'cdn_nombre_archivos',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'fecha_actualizacion',
        'getUsuarioCreacion',
        'getEmpleador',
        'getTrabajador',
        'getDoFinalizado'
    ];

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
     * Relación con el empleador.
     * 
     * @return BelongsTo
     */
    public function getEmpleador() {
        return $this->belongsTo(ConfiguracionEmpleador::class, 'emp_id')->select([
            'emp_id',
            'emp_identificacion',
            'emp_razon_social'
        ]);
    }

    /**
     * Relación con el trabajador.
     * 
     * @return BelongsTo
     */
    public function getTrabajador() {
        return $this->belongsTo(ConfiguracionTrabajador::class, 'tra_id')->select([
            'tra_id',
            'tra_identificacion',
            'tra_codigo'
        ]);
    }

    /**
     * Obtiene el registro de estado DO procesado de manera exitosa y finalizado.
     *
     * @return HasOne
     */
    public function getDoFinalizado() {
        return $this->hasOne(DsnEstadoDocumentoDaop::class, 'cdn_id')
            ->where('est_estado', 'DO')
            ->where('est_resultado', 'EXITOSO')
            ->where('est_ejecucion', 'FINALIZADO')
            ->latest();
    }
}
