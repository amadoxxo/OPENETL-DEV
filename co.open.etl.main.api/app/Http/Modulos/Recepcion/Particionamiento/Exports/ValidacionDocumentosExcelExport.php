<?php
namespace App\Http\Modulos\Recepcion\Particionamiento\Exports;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use openEtl\Tenant\Traits\TenantTrait;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithTitle;
use openEtl\Main\Traits\PackageMainTrait;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Clase para generar el excel del tracking de Validación de Documentos.
 */
class ValidacionDocumentosExcelExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithMapping, ShouldAutoSize, WithEvents, WithColumnFormatting {
    use PackageMainTrait;

    /**
     * Instancia del OFE.
     *
     * @var ConfiguracionObligadoFacturarElectronicamente
     */
    protected $ofe;

    /**
     * Instancia para contener los documentos que se deben incluir en el Excel.
     *
     * @var Collection
     */
    protected $documentos;

    /**
     * Titulos de columnas del Excel
     *
     * @var array
     */
    protected $encabezados = [
        'NIT RECEPTOR',
        'RECEPTOR',
        'NIT EMISOR',
        'EMISOR',
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
        'ORIGEN',
        'FECHA CARGUE',
        'RESPONSABLE',
        'ESTADO VALIDACION',
        'FONDO',
        'USUARIO FONDO',
        'FECHA FONDO',
        'OC SAP',
        'USUARIO OC SAP',
        'FECHA OC SAP',
        'POSICION',
        'USUARIO POSICION',
        'FECHA POSICION',
        'HOJA DE ENTRADA',
        'USUARIO HOJA DE ENTRADA',
        'FECHA HOJA DE ENTRADA',
        'OBSERVACION VALIDACION',
        'USUARIO OBSERVACION VALIDACION',
        'FECHA OBSERVACION VALIDACION',
        'No APROBACION',
        'FECHA VALIDACION',
        'OBSERVACION'
    ];

    /**
     * Constructor de la clase que permite generar el excel de validación de documentos.
     * 
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Oferente consultado
     * @param Collection $documentos Documentos consultados
     */
    public function __construct(
        ConfiguracionObligadoFacturarElectronicamente $ofe,
        Collection $documentos
    ) {
        $this->ofe = $ofe;
        $this->documentos = $documentos;
    }

    /**
     * Título de las columnas.
     * 
     * * @return array
     */
    public function headings(): array {
        TenantTrait::GetVariablesSistemaTenant();
        if($this->ofe->getGruposTrabajo->count() > 0) {
            $vsiGruposTrabajo = config('variables_sistema_tenant.NOMBRE_GRUPOS_TRABAJO');
            $gruposTrabajo = !empty($vsiGruposTrabajo) ? json_decode($vsiGruposTrabajo) : new \stdClass();
            
            $this->encabezados[] = (isset($gruposTrabajo->singular) && !empty($gruposTrabajo->singular) ? strtoupper($gruposTrabajo->singular) : 'GRUPO DE TRABAJO');
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
            'M:T' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    /**
     * Registra los eventos del excel.
     *
     * @return array
     */
    public function registerEvents(): array {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Alinear todo el excel a la izquierda por defecto
                $event->sheet->getDelegate()->getStyle('A:AQ')
                    ->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                // Alinear los rangos de las columnas a la derecha
                $event->sheet->getDelegate()->getStyle('M:T')
                    ->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            }
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
     * Mapea los datos a retornar en cada fila del archivo.
     *
     * @param $documento Objeto con la información del documento que se procesa para una fila del Excel
     * @return array
     */
    public function map($documento): array {
        $valoresColumnas = [
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo,
            $documento->getConfiguracionProveedor->pro_identificacion,
            $documento->getConfiguracionProveedor->nombre_completo,
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
            $documento->cdo_origen,
            $documento->fecha_creacion,
            !empty($documento->getUsuarioResponsable) ? $documento->getUsuarioResponsable->usu_identificacion : '',
            $documento->cdo_validacion
        ];
        $this->obtenerValoresValidacion($documento, $valoresColumnas);
        $this->obtenerValoresEstadoValidacionValidado($documento, $valoresColumnas);

        if($this->ofe->ofe_recepcion_fnc_activo == 'SI')
            $this->calcularValoresColumnasFnc($documento, $valoresColumnas);

        return $valoresColumnas;
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
        }
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
     * Obtiene los valores de los campos de validación donde trae valor, nombre_usuario y fecha.
     *
     * @param  mixed $documento Objeto con la información del documento que se procesa para una fila del excel
     * @param  array $valoresColumnas Array con los valores de las columnas generales del Excel
     * @return void
     */
    private function obtenerValoresValidacion($documento, array &$valoresColumnas): void {
        $columnas = [
            'fondos',
            'oc_sap',
            'posicion',
            'hoja_de_entrada',
            'observacion_validacion'
        ];
        if(!empty($documento->cdo_validacion_valor)) {
            $arr = json_decode($documento->cdo_validacion_valor, true);
            $collect = collect($arr['campos_adicionales']);
        } else {
            $collect = collect([]);
        }
        foreach($columnas as $indice) {
            $consulta = $collect->firstWhere('campo', $indice);

            if(!empty($consulta) && array_key_exists('valor', $consulta)) {
                $valoresColumnas[] = $consulta['valor'];
                $valoresColumnas[] = $consulta['nombre_usuario'];
                $valoresColumnas[] = $consulta['fecha'];
            } else {
                $valoresColumnas[] = '';
                $valoresColumnas[] = '';
                $valoresColumnas[] = '';
            }
        }
    }

    /**
     * Obtiene los valores de los campos del estado validación validado donde trae el valor.
     *
     * @param  mixed $documento Objeto con la información del documento que se procesa para una fila del excel
     * @param  array $valoresColumnas Array con los valores de las columnas generales del Excel
     * @return void
     */
    private function obtenerValoresEstadoValidacionValidado($documento, array &$valoresColumnas): void {
        $columnas = [
            'no_aprobacion',
            'fecha_validacion',
            'observacion'
        ];

        if(!empty($documento->get_estado_validacion_validado_est_informacion_adicional)) {
            $arr = json_decode($documento->get_estado_validacion_validado_est_informacion_adicional, true);
            $collect =  collect($arr['campos_adicionales']);
            foreach($columnas as $indice) {
                $consulta = $collect->firstWhere('campo', $indice);

                if(!empty($consulta) && array_key_exists('valor', $consulta))
                    $valoresColumnas[] = $consulta['valor'];
                else
                    $valoresColumnas[] = '';
            }
        } else {
            $valoresColumnas[] = '';
            $valoresColumnas[] = '';
            $valoresColumnas[] = '';
        }
    }

    /**
     * Título de la Hoja.
     * 
     * @return string
     */
    public function title(): string {
        return 'validacion_documentos_' . date('YmdHis');
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