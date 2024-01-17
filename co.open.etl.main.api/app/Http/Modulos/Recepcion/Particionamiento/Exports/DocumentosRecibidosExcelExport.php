<?php
namespace App\Http\Modulos\Recepcion\Particionamiento\Exports;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use openEtl\Tenant\Traits\TenantTrait;
use Maatwebsite\Excel\Concerns\WithTitle;
use openEtl\Main\Traits\PackageMainTrait;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class DocumentosRecibidosExcelExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithMapping, ShouldAutoSize, WithColumnFormatting {
    use PackageMainTrait;

    /**
     * Instancia del OFE.
     *
     * @var ConfiguracionObligadoFacturarElectronicamente
     */
    protected ConfiguracionObligadoFacturarElectronicamente $ofe;

    /**
     * Instancia para contener los documentos que se deb incluir en el Excel.
     *
     * @var Collection
     */
    protected Collection $documentos;

    /**
     * Titulos de columnas del Excel
     *
     * @var array
     */
    protected array $encabezados = [
        'NIT RECEPTOR',
        'RECEPTOR',
        'RESOLUCION FACTURACION',
        'NIT EMISOR',
        'EMISOR',
        'CÓDIGO TIPO DOCUMENTO',
        'TIPO DOCUMENTO',
        'CÓDIGO TIPO OPERACION',
        'TIPO OPERACION',
        'PREFIJO',
        'CONSECUTIVO',
        'FECHA DOCUMENTO',
        'HORA DOCUMENTO',
        'FECHA VENCIMIENTO',
        'OBSERVACION',
        'CUFE',
        'MONEDA',
        'TOTAL ANTES DE IMPUESTOS',
        'IMPUESTOS',
        'CARGOS',
        'DESCUENTOS',
        'REDONDEO',
        'TOTAL',
        'ANTICIPOS',
        'RETENCIONES',
        'FECHA CARGUE',
        'INCONSISTENCIAS XML-UBL',
        'FECHA VALIDACION DIAN',
        'ESTADO DIAN',
        'RESULTADO DIAN',
        'FECHA ACUSE DE RECIBO',
        'FECHA RECIBO DE BIEN Y/O PRESTACION SERVICIO',
        'ESTADO DOCUMENTO',
        'FECHA ESTADO',
        'MOTIVO RECHAZO',
        'TRANSMISION ERP',
        'RESULTADO TRANSMISION ERP',
        'OBSERVACIONES TRANSMISION ERP'
    ];

    /**
     * Constructor de la clase que permite generar el Excel de los documentos recibidos.
     *
     * @param Collection $documentos Documentos recibidos
     */
    public function __construct(
        ConfiguracionObligadoFacturarElectronicamente $ofe,
        Collection $documentos
    ) {
        $this->ofe = $ofe;
        $this->documentos = $documentos;
    }

    /**
     * Título de las columnas
     * 
     * * @return array
     */
    public function headings(): array {
        TenantTrait::GetVariablesSistemaTenant();
        
        if($this->ofe->getGruposTrabajo->count() > 0) {
            $gruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
            $gruposTrabajo = !empty($gruposTrabajo) ? json_decode($gruposTrabajo) : new \stdClass();
            
            $this->encabezados[] = (isset($gruposTrabajo->singular) && !empty($gruposTrabajo->singular) ? strtoupper($gruposTrabajo->singular) : 'GRUPO DE TRABAJO');
            $this->encabezados[] = 'RESPONSABLE';
        }
        
        if($this->ofe->ofe_recepcion_fnc_activo == 'SI') {
            $this->encabezados[] = 'ESTADO VALIDACION';

            // Se obtienen las columnas adicionales configuradas en el evento recibo bien
            if(!empty($this->ofe->ofe_recepcion_fnc_configuracion)) {
                $ofeRecepcionFncConfiguracion = json_decode($this->ofe->ofe_recepcion_fnc_configuracion);
                if(isset($ofeRecepcionFncConfiguracion->evento_recibo_bien) && !empty($ofeRecepcionFncConfiguracion->evento_recibo_bien)) {
                    foreach ($ofeRecepcionFncConfiguracion->evento_recibo_bien as $value)
                        array_push($this->encabezados, strtoupper(str_replace('_', ' ', $this->sanear_string($value->campo))));
                }
            }

        }

        return $this->encabezados;
    }

    /**
     * Formato para las columnas.
     *
     * @return array
     */
    public function columnFormats(): array {
        return [
            'R' => NumberFormat::FORMAT_NUMBER_00,
            'S' => NumberFormat::FORMAT_NUMBER_00,
            'T' => NumberFormat::FORMAT_NUMBER_00,
            'U' => NumberFormat::FORMAT_NUMBER_00,
            'V' => NumberFormat::FORMAT_NUMBER_00,
            'W' => NumberFormat::FORMAT_NUMBER_00,
            'X' => NumberFormat::FORMAT_NUMBER_00,
            'Y' => NumberFormat::FORMAT_NUMBER_00
        ];
    }

    /**
     * Estilos para las columnas.
     * 
     * @param Worksheet $sheet 
     * @return array
     */
    public function styles(Worksheet $sheet): array {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 14
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => 'C9DAF8',
                    ]
                ],
            ]
        ];
    }

    /**
     * Retorna el string correspondiente a la observación de un documento.
     *
     * @param null|mixed $cdo_observacion Valor de la columna de observación del documento
     * @return string
     */
    private function cdoObservacion($cdo_observacion): string {
        if(!empty($cdo_observacion)) {
            $observacion = json_decode($cdo_observacion);
            if(json_last_error() === JSON_ERROR_NONE && is_array($observacion)) {
                return str_replace(["\n","\t"], [" "," "], substr(implode(' | ', $observacion), 0, 32767));
            } else {
                return str_replace(["\n","\t"], [" | "," "], substr($cdo_observacion, 0, 32767));
            }
        }

        return '';
    }

    /**
     * Calcula los valores para las columnas de los OFEs de FNC.
     * 
     * Este proceso solamente aplica a los OFEs con la columna ofe_recepcion_fnc_activo en SI
     *
     * @param $documento Objeto con la información del documento que se procesa para una fila del Excel
     * @param array $valoresColumnas Array con los valores de las columnas generales del Excel
     * @return void
     */
    private function calcularValoresColumnasFnc($documento, array &$valoresColumnas): void {
        if($this->ofe->getGruposTrabajo->count() > 0) {
            $grupoTrabajo = '';
            if(!empty($documento->gtr_id)) {
                $grupoTrabajo = $documento->getGrupoTrabajo->gtr_codigo . ' - ' . $documento->getGrupoTrabajo->gtr_nombre;
            } else {
                if(
                    isset($documento->getConfiguracionProveedor->getProveedorGruposTrabajo) &&
                    !empty($documento->getConfiguracionProveedor->getProveedorGruposTrabajo) && 
                    $documento->getConfiguracionProveedor->getProveedorGruposTrabajo->count() == 1
                ) {
                    $grupoTrabajo = $documento->getConfiguracionProveedor->getProveedorGruposTrabajo[0]->getGrupoTrabajo->gtr_codigo . ' - ' . $documento->getConfiguracionProveedor->getProveedorGruposTrabajo[0]->getGrupoTrabajo->gtr_nombre;
                }
            }
            
            $valoresColumnas[] = $grupoTrabajo;
            $valoresColumnas[] = $documento->getUsuarioResponsable ? $documento->getUsuarioResponsable->usu_nombre : '';
        }

        if($this->ofe->ofe_recepcion_fnc_activo == 'SI') {
            $valoresColumnas[] = !empty($documento->cdo_validacion) ? $documento->cdo_validacion : '';

            // Se obtienen las columnas adicionales configuradas en el evento recibo bien
            if(!empty($this->ofe->ofe_recepcion_fnc_configuracion)) {
                $ofeRecepcionFncConfiguracion = json_decode($this->ofe->ofe_recepcion_fnc_configuracion);
                if(isset($ofeRecepcionFncConfiguracion->evento_recibo_bien) && !empty($ofeRecepcionFncConfiguracion->evento_recibo_bien)) {
                    $valoresColumnas[] = $documento->cdo_validacion_valor_campos_adicionales_fondos;
                    $valoresColumnas[] = $documento->cdo_validacion_valor_campos_adicionales_oc_sap;
                    $valoresColumnas[] = $documento->cdo_validacion_valor_campos_adicionales_posicion;
                    $valoresColumnas[] = $documento->cdo_validacion_valor_campos_adicionales_hoja_de_entrada;
                    $valoresColumnas[] = $documento->cdo_validacion_valor_campos_adicionales_observacion_validacion;
                }
            }
        }
    }

    /**
     * Mapea los datos a retornar en cada fila del archivo.
     *
     * @param $documento Objeto con la información del documento que se procesa para una fila del Excel
     * @return array
     */
    public function map($documento): array {
        $inconsistenciasXmlUbl = '';
        $informacionAdicionalRdi = !empty($documento->get_rdi_exitoso_est_informacion_adicional) ? json_decode($documento->get_rdi_exitoso_est_informacion_adicional, true) : [];
        if(json_last_error() === JSON_ERROR_NONE && is_array($informacionAdicionalRdi) && array_key_exists('inconsistencia', $informacionAdicionalRdi) && !empty($informacionAdicionalRdi['inconsistencia']))
            $inconsistenciasXmlUbl = implode(' || ', $informacionAdicionalRdi['inconsistencia']);

        $motivoRechazo         = '';
        $estMotivoRechazo = !empty($documento->get_evento_dian_rechazo_est_motivo_rechazo) ? json_decode($documento->get_evento_dian_rechazo_est_motivo_rechazo, true) : [];
        if(json_last_error() === JSON_ERROR_NONE && is_array($estMotivoRechazo) && array_key_exists('motivo_rechazo', $estMotivoRechazo) && !empty($estMotivoRechazo['motivo_rechazo']))
            $motivoRechazo = $estMotivoRechazo['motivo_rechazo'];

        switch($documento->cdo_estado_dian) {
            case 'APROBADO':
                $estadoDian = Str::title($documento->cdo_estado_dian);
                $mensajeResultadoDian = $documento->get_estado_dian_aprobado_est_mensaje_resultado;
                break;
            case 'RECHAZADO':
                $estadoDian = Str::title($documento->cdo_estado_dian);
                $mensajeResultadoDian = $documento->get_estado_dian_rechazado_est_mensaje_resultado;
                break;
            case 'CONNOTIFICACION':
                $estadoDian = 'Aprobado con notificación';
                $mensajeResultadoDian = $documento->get_estado_dian_aprobado_con_notificacion_est_mensaje_resultado;
                break;
            case 'ENPROCESO':
                $estadoDian = 'En proceso';
                $mensajeResultadoDian = '';
                break;
            default:
                $estadoDian = '';
                $mensajeResultadoDian = '';
                break;
        }
        
        $valoresColumnas = [
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo,
            $documento->get_cabecera_documentos_rfa_resolucion,
            $documento->getConfiguracionProveedor->pro_identificacion,
            $documento->getConfiguracionProveedor->nombre_completo,
            $documento->get_tipo_documento_electronico_tde_codigo,
            $documento->get_tipo_documento_electronico_tde_descripcion,
            $documento->get_tipo_operacion_top_codigo,
            $documento->get_tipo_operacion_top_descripcion,
            $documento->rfa_prefijo,
            $documento->cdo_consecutivo,
            $documento->cdo_fecha,
            $documento->get_cabecera_documentos_cdo_hora,
            $documento->get_cabecera_documentos_cdo_vencimiento,
            $this->cdoObservacion($documento->get_cabecera_documentos_cdo_observacion),
            $documento->cdo_cufe,
            $documento->get_moneda_mon_codigo,
            $documento->get_cabecera_documentos_cdo_valor_sin_impuestos,
            $documento->get_cabecera_documentos_cdo_impuestos,
            $documento->get_cabecera_documentos_cdo_cargos,
            $documento->get_cabecera_documentos_cdo_descuentos,
            $documento->get_cabecera_documentos_cdo_redondeo,
            $documento->get_cabecera_documentos_cdo_valor_a_pagar,
            $documento->get_cabecera_documentos_cdo_anticipo,
            $documento->get_cabecera_documentos_cdo_retenciones,
            $documento->fecha_creacion,
            $inconsistenciasXmlUbl,
            $documento->cdo_fecha_validacion_dian,
            $estadoDian,
            $mensajeResultadoDian,
            $documento->cdo_acuse_recibo,
            $documento->cdo_recibo_bien,
            $documento->cdo_estado_eventos_dian,
            $documento->cdo_estado_eventos_dian_fecha,
            $motivoRechazo,
            $documento->get_transmision_erp_est_inicio_proceso,
            $documento->get_transmision_erp_est_resultado,
            $documento->get_transmision_erp_est_mensaje_resultado
        ];

        if($this->ofe->ofe_recepcion_fnc_activo == 'SI')
            $this->calcularValoresColumnasFnc($documento, $valoresColumnas);

        return $valoresColumnas;
    }

    /**
     * Título de la Hoja
     * 
     * @return string
     */
    public function title(): string {
        return 'documentos_recibidos_' . date('YmdHis');
    }

    /**
     * Hace uso de la colección de resultados para armar el Excel.
     *
     * @return Collection
     */
    public function collection() {
        return $this->documentos;
    }
}