<?php
namespace App\Http\Modulos\NominaElectronica\Json;

use App\Traits\DiTrait;
use App\Http\Modulos\NominaElectronica\ConstantsDataInput;

class JsonNominaBuilder {
    use DiTrait;
    /**
     * Array de documentos a procesar por NDI.
     * 
     * @var array
     */
    private $jsonDocumentos = [];

    /**
     * Tipos de documentos de nómina electrónica a procesar.
     * 
     * @var string
     */
    private $tipo = '';

    /**
     * Origen de los documentos de nómina electrónica a procesar.
     * 
     * @var string
     */
    private $origen = '';

    public function __construct($tipo) {
        $this->tipo   = $tipo;
    }

    /**
     * Agrega un nuevo documento al array de documentos a ser registrados para su procesamiento.
     * 
     * @param $documento
     */
    public function addDocument($documento) {
        $this->jsonDocumentos[] = $documento;
    }

    /**
     * Limpia la variable jsonDocumentos, para agregar el siguiente grupo de documentos.
     * 
     * @return void
     */
    public function emptyDocuments() {
        $this->jsonDocumentos = [];
    }

    /**
     * Retorna un objeto json que puede proccesar DataBullder
     *
     * @return false|string
     */
    public function build() {
        $documentos = [];
        foreach ($this->jsonDocumentos as $documento) {
            if($this->tipo != ConstantsDataInput::EXCEL_ELIMINAR)
                $documentos[] = $this->buildDocumento($documento['data'], (isset($documento['devengados'])) ? $documento['devengados'] : [], (isset($documento['deducciones'])) ? $documento['deducciones'] : []);
            else
                $documentos[] = $this->buildDocumento($documento['data']);
        }

        $objetos['DN'] = $documentos;
        return json_encode(['documentos' => $objetos]);
    }

    /**
     * Construye el array del documento conforme a la estructura esperada por NDI.
     *
     * @param array $documento Documento en procesamiento
     * @param array $devengados Array de devengados del documento
     * @param array $deducciones Array de deducciones del documento
     * @return array
     */
    private function buildDocumento(array $documento, array $devengados = [], array $deducciones = []) {
        if($this->tipo != ConstantsDataInput::EXCEL_ELIMINAR) {
            $ddv_detalle_devengados  = $this->buildDevengados($devengados);
            $ddv_detalle_deducciones = $this->buildDeducciones($deducciones);
        }

        $cdn_documento_predecesor = '';
        if(
            (
                $documento['tipo_documento'] == 103 ||
                (
                    $documento['tipo_documento'] == 102 &&
                    $documento['aplica_novedad'] == 'SI'
                )
            ) ||
            $this->tipo == ConstantsDataInput::EXCEL_ELIMINAR
        ) {
            $cdn_documento_predecesor = [
                'cdn_prefijo'       => $documento['prefijo_predecesor'],
                'cdn_consecutivo'   => $documento['consecutivo_predecesor'],
                'cdn_fecha_emision' => $documento['fecha_emision_predecesor'],
                'cdn_cune'          => $documento['cune_predecesor']
            ];
        }

        if($this->tipo == ConstantsDataInput::EXCEL_NOMINA) {
            $arrDocumento = [
                'tde_codigo'                   => $documento['tipo_documento'],
                'ntn_codigo'                   => $documento['tipo_nota'],
                'cdn_aplica_novedad'           => $documento['aplica_novedad'] == 'SI' ? 'SI' : 'NO',
                'emp_identificacion'           => $documento['nit_empleador'],
                'tra_identificacion'           => $documento['nit_trabajador'],
                'cdn_prefijo'                  => $documento['prefijo'],
                'cdn_consecutivo'              => $documento['consecutivo'],
                'npe_codigo'                   => $documento['periodo_nomina'],
                'cdn_fecha_emision'            => $documento['fecha_emision'] . ' ' . $documento['hora_emision'],
                'cdn_fecha_inicio_liquidacion' => $documento['fecha_inicio_liquidacion'],
                'cdn_fecha_fin_liquidacion'    => $documento['fecha_fin_liquidacion'],
                'cdn_tiempo_laborado'          => $documento['tiempo_laborado'],
                'pai_codigo'                   => $documento['cod_pais_generacion'],
                'dep_codigo'                   => $documento['cod_departamento_generacion'],
                'mun_codigo'                   => $documento['cod_municipio_generacion'],
                'cdn_notas'                    => !empty($documento['notas']) ? explode('|', $documento['notas']) : '',
                'cdn_medios_pago'              => [
                    'fpa_codigo' => $documento['cod_forma_de_pago'],
                    'mpa_codigo' => $documento['cod_medio_de_pago']
                ],
                'cdn_fechas_pago'           => !empty($documento['fechas_pago']) ? explode(',', $documento['fechas_pago']) : '',
                'mon_codigo'                => $documento['moneda'],
                'cdn_trm'                   => !empty($documento['trm']) ? $documento['trm'] : '',
                'cdn_documento_predecesor'  => $cdn_documento_predecesor,
                'cdn_redondeo'              => 0,
                'cdn_devengados'            => $this->formatearValor($ddv_detalle_devengados['total'], true),
                'cdn_deducciones'           => $this->formatearValor($ddv_detalle_deducciones['total'], true),
                'cdn_total_comprobante'     => $this->formatearValor($ddv_detalle_devengados['total'] - $ddv_detalle_deducciones['total'], true),
                'cdn_informacion_adicional' => [],
                'ddv_detalle_devengados'    => $ddv_detalle_devengados['data'],
                'ddd_detalle_deducciones'   => $ddv_detalle_deducciones['data']
            ];

            if($arrDocumento['tde_codigo'] == '103')
                unset($arrDocumento['cdn_aplica_novedad']);
        } else {
            $arrDocumento = [
                'tde_codigo'                => $documento['tipo_documento'],
                'ntn_codigo'                => $documento['tipo_nota'],
                'emp_identificacion'        => $documento['nit_empleador'],
                'tra_identificacion'        => $documento['nit_trabajador'],
                'cdn_prefijo'               => $documento['prefijo'],
                'cdn_consecutivo'           => $documento['consecutivo'],
                'cdn_fecha_emision'         => $documento['fecha_emision'] . ' ' . $documento['hora_emision'],
                'pai_codigo'                => $documento['cod_pais_generacion'],
                'dep_codigo'                => $documento['cod_departamento_generacion'],
                'mun_codigo'                => $documento['cod_municipio_generacion'],
                'cdn_notas'                 => !empty($documento['notas']) ? explode('|', $documento['notas']) : '',
                'cdn_documento_predecesor'  => $cdn_documento_predecesor,
                'cdn_informacion_adicional' => []
            ];
        }

        return $arrDocumento;
    }

    /**
     * Arma la información de Devengados y calcula el total de los mismos.
     *
     * @param array $devengados Array de devengados obtenidos del Excel
     * @return array
     */
    private function buildDevengados($devengados) {
        $total         = 0;
        $arrDevengados = [];
        foreach ($devengados as $devengado) {
            switch ($devengado['concepto']) {
                case 'Basico':
                    $arrDevengados['basico'] = [
                        'dias_trabajados'  => $this->formatearValor($devengado['cantidad'], false, true),
                        'sueldo_trabajado' => $this->formatearValor($devengado['valor'], true)
                    ];
                    $total += $devengado['valor'] != '' ? $devengado['valor'] : '';
                    break;
                case 'Transporte':
                    $arrDevengados['transporte'][] = [
                        'auxilio_transporte'  => $this->formatearValor($devengado['valor'], true),
                        'viatico_salarial'    => $this->formatearValor($devengado['valor_salarial'], true),
                        'viatico_no_salarial' => $this->formatearValor($devengado['valor_no_salarial'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0) + ($devengado['valor_salarial'] != '' ? $devengado['valor_salarial'] : 0) + ($devengado['valor_no_salarial'] != '' ? $devengado['valor_no_salarial'] : 0);
                    break;
                case 'HorasExtrasRecargos':
                    $arrDevengados['horas_extras_recargos'][] = [
                        'tipo'         => $devengado['tipo'],
                        'fecha_inicio' => str_replace('/', '-', trim($devengado['fecha_inicio'])),
                        'hora_inicio'  => date('H:i:s', strtotime($devengado['hora_inicio'])),
                        'fecha_fin'    => str_replace('/', '-', trim($devengado['fecha_fin'])),
                        'hora_fin'     => date('H:i:s', strtotime($devengado['hora_fin'])),
                        'cantidad'     => $this->formatearValor($devengado['cantidad'], false, true),
                        'porcentaje'   => $this->formatearValor($devengado['porcentaje'], true),
                        'pago'         => $this->formatearValor($devengado['valor'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'Vacaciones':
                    if($devengado['tipo'] == 'VacacionesComunes') {
                        $arrDevengados['vacaciones']['vacaciones_comunes'][] = [
                            'fecha_inicio' => str_replace('/', '-', trim($devengado['fecha_inicio'])),
                            'fecha_fin'    => str_replace('/', '-', trim($devengado['fecha_fin'])),
                            'cantidad'     => $this->formatearValor($devengado['cantidad'], false, true),
                            'pago'         => $this->formatearValor($devengado['valor'], true)
                        ];
                        $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    } elseif($devengado['tipo'] == 'VacacionesCompensadas') {
                        $arrDevengados['vacaciones']['vacaciones_compensadas'][] = [
                            'cantidad' => $this->formatearValor($devengado['cantidad'], false, true),
                            'pago'     => $this->formatearValor($devengado['valor'], true)
                        ];
                        $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    }
                    break;
                case 'Primas':
                    $arrDevengados['primas'] = [
                        'cantidad'         => $this->formatearValor($devengado['cantidad'], false, true),
                        'pago'             => $this->formatearValor($devengado['valor'], true),
                        'pago_no_salarial' => $this->formatearValor($devengado['valor_no_salarial'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    $total += ($devengado['valor_no_salarial'] != '' ? $devengado['valor_no_salarial'] : 0);
                    break;
                case 'Cesantias':
                    $arrDevengados['cesantias'] = [
                        'porcentaje'     => $this->formatearValor($devengado['porcentaje'], true),
                        'pago'           => $this->formatearValor($devengado['valor'], true),
                        'pago_intereses' => $this->formatearValor($devengado['pago_adicional'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    $total += ($devengado['pago_adicional'] != '' ? $devengado['pago_adicional'] : 0);
                    break;
                case 'Incapacidades':
                    $arrDevengados['incapacidades'][] = [
                        'tipo'         => $devengado['tipo'],
                        'fecha_inicio' => str_replace('/', '-', trim($devengado['fecha_inicio'])),
                        'fecha_fin'    => str_replace('/', '-', trim($devengado['fecha_fin'])),
                        'cantidad'     => $this->formatearValor($devengado['cantidad'], false, true),
                        'pago'         => $this->formatearValor($devengado['valor'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'Licencias':
                    $arrDevengados['licencias'][] = [
                        'tipo'         => $devengado['tipo'],
                        'fecha_inicio' => str_replace('/', '-', trim($devengado['fecha_inicio'])),
                        'fecha_fin'    => str_replace('/', '-', trim($devengado['fecha_fin'])),
                        'cantidad'     => $this->formatearValor($devengado['cantidad'], false, true),
                        'pago'         => $this->formatearValor($devengado['valor'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'Bonificaciones':
                    $arrDevengados['bonificaciones'][] = [
                        'bonificacion_salarial'    => $this->formatearValor($devengado['valor_salarial'], true),
                        'bonificacion_no_salarial' => $this->formatearValor($devengado['valor_no_salarial'], true)
                    ];
                    $total += ($devengado['valor_salarial'] != '' ? $devengado['valor_salarial'] : 0);
                    $total += ($devengado['valor_no_salarial'] != '' ? $devengado['valor_no_salarial'] : 0);
                    break;
                case 'Auxilios':
                    $arrDevengados['auxilios'][] = [
                        'auxilio_salarial'    => $this->formatearValor($devengado['valor_salarial'], true),
                        'auxilio_no_salarial' => $this->formatearValor($devengado['valor_no_salarial'], true)
                    ];
                    $total += ($devengado['valor_salarial'] != '' ? $devengado['valor_salarial'] : 0);
                    $total += ($devengado['valor_no_salarial'] != '' ? $devengado['valor_no_salarial'] : 0);
                    break;
                case 'HuelgasLegales':
                    $arrDevengados['huelgas_legales'][] = [
                        'fecha_inicio' => str_replace('/', '-', trim($devengado['fecha_inicio'])),
                        'fecha_fin'    => str_replace('/', '-', trim($devengado['fecha_fin'])),
                        'cantidad'     => $this->formatearValor($devengado['cantidad'], false, true)
                    ];
                    break;
                case 'OtrosConceptos':
                    $arrDevengados['otros_conceptos'][] = [
                        'descripcion_concepto' => $devengado['descripcion'],
                        'concepto_salarial'     => $this->formatearValor($devengado['valor_salarial'], true),
                        'concepto_no_salarial'  => $this->formatearValor($devengado['valor_no_salarial'], true)
                    ];
                    $total += ($devengado['valor_salarial'] != '' ? $devengado['valor_salarial'] : 0);
                    $total += ($devengado['valor_no_salarial'] != '' ? $devengado['valor_no_salarial'] : 0);
                    break;
                case 'Compensaciones':
                    $arrDevengados['compensaciones'][] = [
                        'compensacion_ordinaria'      => $this->formatearValor($devengado['valor_ordinario'], true),
                        'compensacion_extraordinaria' => $this->formatearValor($devengado['valor_extraordinario'], true)
                    ];
                    $total += ($devengado['valor_ordinario'] != '' ? $devengado['valor_ordinario'] : 0);
                    $total += ($devengado['valor_extraordinario'] != '' ? $devengado['valor_extraordinario'] : 0);
                    break;
                case 'BonoEPCTVs':
                    $arrDevengados['bono_epctvs'][] = [
                        'pago_salarial'                 => $this->formatearValor($devengado['valor_salarial'], true),
                        'pago_no_salarial'              => $this->formatearValor($devengado['valor_no_salarial'], true),
                        'pago_alimentacion_salarial'    => $this->formatearValor($devengado['valor_salarial_adicional'], true),
                        'pago_alimentacion_no_salarial' => $this->formatearValor($devengado['valor_no_salarial_adicional'], true)
                    ];
                    $total += ($devengado['valor_salarial'] != '' ? $devengado['valor_salarial'] : 0);
                    $total += ($devengado['valor_no_salarial'] != '' ? $devengado['valor_no_salarial'] : 0);
                    $total += ($devengado['valor_salarial_adicional'] != '' ? $devengado['valor_salarial_adicional'] : 0);
                    $total += ($devengado['valor_no_salarial_adicional'] != '' ? $devengado['valor_no_salarial_adicional'] : 0);
                    break;
                case 'Comisiones':
                    $arrDevengados['comisiones'][] = [
                        'comision' => $this->formatearValor($devengado['valor'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'PagosTerceros':
                    $arrDevengados['pagos_terceros'][] = [
                        'pago_tercero' => $this->formatearValor($devengado['valor'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'Anticipos':
                    $arrDevengados['anticipos'][] = [
                        'anticipo' => $this->formatearValor($devengado['valor'], true)
                    ];
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'Dotacion':
                    $arrDevengados['dotacion'] = $this->formatearValor($devengado['valor'], true);
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'ApoyoSost':
                    $arrDevengados['apoyo_sost'] = $this->formatearValor($devengado['valor'], true);
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'Teletrabajo':
                    $arrDevengados['teletrabajo'] = $this->formatearValor($devengado['valor'], true);
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'BonifRetiro':
                    $arrDevengados['bonificaciones_retiro'] = $this->formatearValor($devengado['valor'], true);
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'Indemnizacion':
                    $arrDevengados['indemnizacion'] = $this->formatearValor($devengado['valor'], true);
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
                case 'Reintegro':
                    $arrDevengados['reintegro'] = $this->formatearValor($devengado['valor'], true);
                    $total += ($devengado['valor'] != '' ? $devengado['valor'] : 0);
                    break;
            }
        }

        return [
            'total' => $total,
            'data'  => $arrDevengados,
        ];
    }

    /**
     * Arma la información de Deducciones y calcula el total de los mismos.
     *
     * @param array $devengados Array de deducciones obtenidos del Excel
     * @return array
     */
    private function buildDeducciones($deducciones) {
        $total         = 0;
        $arrDeducciones = [];
        foreach ($deducciones as $deduccion) {
            switch ($deduccion['concepto']) {
                case 'Salud':
                    $arrDeducciones['salud'] = [
                        'porcentaje' => $this->formatearValor($deduccion['porcentaje'], true),
                        'deduccion'  => $this->formatearValor($deduccion['valor'], true)
                    ];
                    $total += $deduccion['valor'] != '' ? $deduccion['valor'] : '';
                    break;
                case 'FondoPension':
                    $arrDeducciones['fondo_pension'] = [
                        'porcentaje' => $this->formatearValor($deduccion['porcentaje'], true),
                        'deduccion'  => $this->formatearValor($deduccion['valor'], true)
                    ];
                    $total += $deduccion['valor'] != '' ? $deduccion['valor'] : '';
                    break;
                case 'FondoSP':
                    $arrDeducciones['fondo_sp'] = [
                        'porcentaje'     => $this->formatearValor($deduccion['porcentaje'], true),
                        'deduccion_sp'   => $this->formatearValor($deduccion['valor'], true),
                        'porcentaje_sub' => $this->formatearValor($deduccion['porcentaje_adicional'], true),
                        'deduccion_sub'  => $this->formatearValor($deduccion['valor_adicional'], true),
                    ];
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    $total += ($deduccion['valor_adicional'] != '' ? $deduccion['valor_adicional'] : 0);
                    break;
                case 'Sindicatos':
                    $arrDeducciones['sindicatos'][] = [
                        'porcentaje' => $this->formatearValor($deduccion['porcentaje'], true),
                        'deduccion'  => $this->formatearValor($deduccion['valor'], true)
                    ];
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'Sanciones':
                    $arrDeducciones['sanciones'][] = [
                        'sancion_publica' => $this->formatearValor($deduccion['valor'], true),
                        'sancion_privada' => $this->formatearValor($deduccion['valor_adicional'], true)
                    ];
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    $total += ($deduccion['valor_adicional'] != '' ? $deduccion['valor_adicional'] : 0);
                    break;
                case 'Libranzas':
                    $arrDeducciones['libranzas'][] = [
                        'descripcion' => $deduccion['descripcion'],
                        'deduccion'   => $this->formatearValor($deduccion['valor'], true)
                    ];
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'PagosTerceros':
                    $arrDeducciones['pagos_terceros'][] = [
                        'pago_tercero' => $this->formatearValor($deduccion['valor'], true)
                    ];
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'Anticipos':
                    $arrDeducciones['anticipos'][] = [
                        'anticipo' => $this->formatearValor($deduccion['valor'], true)
                    ];
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'OtrasDeducciones':
                    $arrDeducciones['otras_deducciones'][] = [
                        'otra_deduccion' => $this->formatearValor($deduccion['valor'], true)
                    ];
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'PensionVoluntaria':
                    $arrDeducciones['pension_voluntaria'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'RetencionFuente':
                    $arrDeducciones['retencion_fuente'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'AFC':
                    $arrDeducciones['afc'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'Cooperativa':
                    $arrDeducciones['cooperativa'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'EmbargoFiscal':
                    $arrDeducciones['embargo_fiscal'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'PlanComplementarios':
                    $arrDeducciones['plan_complementarios'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'Educacion':
                    $arrDeducciones['educacion'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'Reintegro':
                    $arrDeducciones['reintegro'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
                case 'Deuda':
                    $arrDeducciones['deuda'] = $this->formatearValor($deduccion['valor'], true);
                    $total += ($deduccion['valor'] != '' ? $deduccion['valor'] : 0);
                    break;
            }
        }

        return [
            'total' => $total,
            'data'  => $arrDeducciones,
        ];
    }
}
