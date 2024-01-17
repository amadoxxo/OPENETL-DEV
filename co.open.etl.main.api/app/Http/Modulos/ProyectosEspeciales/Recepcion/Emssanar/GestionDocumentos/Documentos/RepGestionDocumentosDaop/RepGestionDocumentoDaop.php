<?php

namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;
use openEtl\Tenant\Models\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentosDaop\TenantRepGestionDocumentoDaop;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto\CentroCosto;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacion;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion\CausalDevolucion;


class RepGestionDocumentoDaop extends TenantRepGestionDocumentoDaop {
    /**
     * Campos visible del modelo.
     * 
     * @var array
     */
    protected $visible = [
        'gdo_id',
        'gdo_id',
        'gdo_modulo',
        'gdo_clasificacion',
        'gdo_id_recepcion',
        'gdo_id_emision',
        'tde_id',
        'top_id',
        'ofe_id',
        'adq_id',
        'pro_id',
        'gdo_identificacion',
        'rfa_resolucion',
        'rfa_prefijo',
        'gdo_consecutivo',
        'gdo_fecha',
        'gdo_hora',
        'gdo_vencimiento',
        'gdo_observacion',
        'mon_id',
        'mon_id_extranjera',
        'gdo_trm',
        'gdo_trm_fecha',
        'gdo_valor_sin_impuestos',
        'gdo_valor_sin_impuestos_moneda_extranjera',
        'gdo_impuestos',
        'gdo_impuestos_moneda_extranjera',
        'gdo_retenciones',
        'gdo_retenciones_moneda_extranjera',
        'gdo_total',
        'gdo_total_moneda_extranjera',
        'gdo_cargos',
        'gdo_cargos_moneda_extranjera',
        'gdo_descuentos',
        'gdo_descuentos_moneda_extranjera',
        'gdo_retenciones_sugeridas',
        'gdo_retenciones_sugeridas_moneda_extranjera',
        'gdo_anticipo',
        'gdo_anticipo_moneda_extranjera',
        'gdo_redondeo',
        'gdo_redondeo_moneda_extranjera',
        'gdo_valor_a_pagar',
        'gdo_valor_a_pagar_moneda_extranjera',
        'gdo_cufe',
        'gdo_fecha_validacion_dian',
        'gdo_estado_etapa1',
        'cop_id',
        'cde_id_etapa1',
        'gdo_observacion_etapa1',
        'gdo_estado_etapa2',
        'cco_id',
        'cde_id_etapa2',
        'gdo_observacion_etapa2',
        'gdo_estado_etapa3',
        'cde_id_etapa3',
        'gdo_observacion_etapa3',
        'gdo_estado_etapa4',
        'gdo_informacion_etapa4',
        'cde_id_etapa4',
        'gdo_observacion_etapa4',
        'gdo_estado_etapa5',
        'cde_id_etapa5',
        'gdo_observacion_etapa5',
        'gdo_estado_etapa6',
        'cde_id_etapa6',
        'gdo_observacion_etapa6',
        'gdo_estado_etapa7',
        'gdo_historico_etapas',
        'usuario_creacion',
        'fecha_creacion',
        'fecha_modificacion',
        'estado',
        'estado_gestion',
        'emisor_razon_social',
        'nombre_etapa_actual',
        'numero_etapa_actual',
        'getConfiguracionObligadoFacturarElectronicamente',
        'getConfiguracionProveedor',
        'getConfiguracionAdquirente',
        'getParametrosMoneda',
        'getParametrosMonedaExtranjera',
        'getConfiguracionCausalDevolucionEtapa1',
        'getConfiguracionCausalDevolucionEtapa2',
        'getConfiguracionCausalDevolucionEtapa3',
        'getConfiguracionCausalDevolucionEtapa4',
        'getConfiguracionCausalDevolucionEtapa5',
        'getConfiguracionCausalDevolucionEtapa6',
        'getConfiguracionCentroOperacion',
        'getConfiguracionCentroCosto'
    ];

    // INICIO RELACIONES
    /**
     * Retorna el Oferente al que pertenece el documento.
     *
     * @return BelongsTo
     */
    public function getConfiguracionObligadoFacturarElectronicamente() {
        return $this->belongsTo(ConfiguracionObligadoFacturarElectronicamente::class, 'ofe_id');
    }

    /**
     * Relación con los proveedores.
     *
     * @return BelongsTo
     */
    public function getConfiguracionProveedor() {
        return $this->belongsTo(ConfiguracionProveedor::class, 'pro_id');
    }

    /**
     * Relación con el Adquirente.
     *
     * @return BelongsTo
     */
    public function getConfiguracionAdquirente() {
        return $this->belongsTo(ConfiguracionAdquirente::class, 'adq_id');
    }

    /**
     * Relación con la paramétrica de moneda.
     *
     * @return BelongsTo
     */
    public function getParametrosMoneda() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id');
    }

    /**
     * Relación con la paramétrica de moneda extranjera.
     *
     * @return BelongsTo
     */
    public function getParametrosMonedaExtranjera() {
        return $this->belongsTo(ParametrosMoneda::class, 'mon_id_extranjera');
    }

    /**
     * Relación con la paramétrica de causal de devolución etapa 1.
     *
     * @return BelongsTo
     */
    public function getConfiguracionCausalDevolucionEtapa1() {
        return $this->belongsTo(CausalDevolucion::class, 'cde_id_etapa1');
    }

    /**
     * Relación con la paramétrica de causal de devolución etapa 2.
     *
     * @return BelongsTo
     */
    public function getConfiguracionCausalDevolucionEtapa2() {
        return $this->belongsTo(CausalDevolucion::class, 'cde_id_etapa2');
    }

    /**
     * Relación con la paramétrica de causal de devolución etapa 3.
     *
     * @return BelongsTo
     */
    public function getConfiguracionCausalDevolucionEtapa3() {
        return $this->belongsTo(CausalDevolucion::class, 'cde_id_etapa3');
    }

    /**
     * Relación con la paramétrica de causal de devolución etapa 4.
     *
     * @return BelongsTo
     */
    public function getConfiguracionCausalDevolucionEtapa4() {
        return $this->belongsTo(CausalDevolucion::class, 'cde_id_etapa4');
    }

    /**
     * Relación con la paramétrica de causal de devolución etapa 5.
     *
     * @return BelongsTo
     */
    public function getConfiguracionCausalDevolucionEtapa5() {
        return $this->belongsTo(CausalDevolucion::class, 'cde_id_etapa5');
    }

    /**
     * Relación con la paramétrica de causal de devolución etapa 6.
     *
     * @return BelongsTo
     */
    public function getConfiguracionCausalDevolucionEtapa6() {
        return $this->belongsTo(CausalDevolucion::class, 'cde_id_etapa6');
    }

    /**
     * Relación con la paramétrica de centro de operación.
     *
     * @return BelongsTo
     */
    public function getConfiguracionCentroOperacion() {
        return $this->belongsTo(CentroOperacion::class, 'cop_id');
    }

    /**
     * Relación con la paramétrica de centro de costo.
     *
     * @return BelongsTo
     */
    public function getConfiguracionCentroCosto() {
        return $this->belongsTo(CentroCosto::class, 'cco_id');
    }
    // FIN RELACIONES
}
