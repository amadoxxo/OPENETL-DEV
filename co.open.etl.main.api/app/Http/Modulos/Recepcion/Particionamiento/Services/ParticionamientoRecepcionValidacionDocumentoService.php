<?php
namespace App\Http\Modulos\Recepcion\Particionamiento\Services;

use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use openEtl\Tenant\Traits\Particionamiento\TenantParticionamientoTrait;
use App\Http\Modulos\Recepcion\Particionamiento\Helpers\HelperRecepcionParticionamiento;
use App\Http\Modulos\Recepcion\Particionamiento\Exports\ValidacionDocumentosExcelExport;
use App\Http\Modulos\Recepcion\Particionamiento\Repositories\ParticionamientoRecepcionValidacionDocumentoRepository;

/**
 * Servicio de Recepción > Validación de Documentos.
 */
class ParticionamientoRecepcionValidacionDocumentoService {
    use TenantParticionamientoTrait;

    /**
     * Instancia de la clase ParticionamientoRecepcionValidacionDocumentoRepository.
     *
     * @var ParticionamientoRecepcionValidacionDocumentoRepository
     */
    public $particionamientoRecepcionValidacionDocumentoRepository;

    /**
     * Cantidad máxima de registros que se incluyen en un excel.
     *
     * @var integer
     */
    private $cantidadRegistrosPorExcel = 20000;

    /**
     * Constructor de la clase.
     *
     * @param  ParticionamientoRecepcionValidacionDocumentoRepository $particionamientoRecepcionValidacionDocumentoRepository Instancia hacia el repositorio
     * @return void
     */
    public function __construct(ParticionamientoRecepcionValidacionDocumentoRepository $particionamientoRecepcionValidacionDocumentoRepository) {
        $this->particionamientoRecepcionValidacionDocumentoRepository = $particionamientoRecepcionValidacionDocumentoRepository;
    }

    /**
     * Cuando el proceso es en background se genera un ZIP que contiene los archivos de Excel generados en el proceso.
     *
     * @param array $uuid Nombre de la carpeta que contiene los archivos de Excel
     * @param string $nombreZip Nombre del ZIP
     * @return void
     */
    private function crearZipExcel(string $uuid, string $nombreZip): void {
        $zip = new \ZipArchive();
        $zip->open(storage_path('etl/descargas/' . $nombreZip), \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $options = array('remove_all_path' => TRUE);
        $zip->addGlob(storage_path('etl/descargas/' . $uuid . '/') . '*.{xlsx}', GLOB_BRACE, $options);
        $zip->close();
        File::deleteDirectory(storage_path('etl/descargas/' . $uuid));
    }

    /**
     * Genera el excel del tracking de validación de documentos.
     *
     * @param Collection $documentos Colección de documentos
     * @param string $cdoFechaDesde Fecha inicial de la consulta
     * @param string $cdoFechaHasta Fecha final de la consulta
     * @param string $ordenamiento Indica el tipo de ordenamiento para el resultado
     * @param bool $retonarArray Indica si se debe retornar el excel en un array (Aplica cuando el proceso es en background)
     * @return array|BinaryFileResponse
     */
    private function generarExcelValidacionDocumentos(Collection $documentos, string $cdoFechaDesde, string $cdofechaHasta, string $ordenamiento, bool $retonarArray = false) {
        date_default_timezone_set('America/Bogota');

        $erroresParticiones = [];
        $particiones        = $this->generarPeriodosParticionamiento($cdoFechaDesde, $cdofechaHasta, 'recepcion', $ordenamiento);
        HelperRecepcionParticionamiento::verificaExisteTablasParticion($this->particionamientoRecepcionValidacionDocumentoRepository, $particiones, $erroresParticiones);

        if(!empty($erroresParticiones))
            throw new \Exception('No existen las siguientes tablas particionadas: ' . implode(', ', $erroresParticiones));

        $contArchivos  = 1;
        $fechaHoraGen  = date('YmdHis');
        $uuid          = Uuid::uuid4()->toString();
        File::makeDirectory(storage_path('etl/descargas/' . $uuid), 0755, true, true);

        foreach($particiones as $particion) {
            $consultaDocumentos = $this->particionamientoRecepcionValidacionDocumentoRepository->joinsExcelValidacionDocumentos($documentos['query']->clone(), $particion);
            $consultaDocumentos->chunk($this->cantidadRegistrosPorExcel, function ($documentosPorArchivo) use ($documentos, $particion, &$contArchivos, $fechaHoraGen, $uuid) {
                $nombreExcel = 'validacion_documentos_particion_' . $particion . '_' . $fechaHoraGen . '_' . str_pad($contArchivos, 5, '0', STR_PAD_LEFT) . '.xlsx';
                Excel::store(new ValidacionDocumentosExcelExport($documentos['ofe'], $documentosPorArchivo), $uuid . '/' . $nombreExcel, 'etl', \Maatwebsite\Excel\Excel::XLSX,
                    [
                        'lazy' => true,
                        'chunck_size' => 2000,
                    ]
                );

                $contArchivos++;
            });
        }
        $nombreZip = 'validacion_documentos_' . $uuid . '.zip';
        $this->crearZipExcel($uuid, $nombreZip);

        if(!$retonarArray) { // Ingresa cuando la solicitud de descarga NO es en background
            return response()
                ->download(storage_path('etl/descargas/' . $nombreZip), $nombreZip, [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ])->deleteFileAfterSend(true);
        } else { // Ingresa cuando la solicitud de descarga es en background
            return [
                'nombre' => $nombreZip
            ];
        }
    }

    /**
     * Retorna una lista paginada de los documentos de validación de acuerdo a los parámetros del request.
     *
     * @param Request $request Parámetros de la petición
     * @return array|BinaryFileResponse
     */
    public function consultarValidacionDocumentos(Request $request) {
        $consulta = $this->particionamientoRecepcionValidacionDocumentoRepository->consultarValidacionDocumentos($request);

        if($request->filled('excel') && $request->excel == true)
            return $this->generarExcelValidacionDocumentos(
                $consulta,
                $request->cdo_fecha_desde,
                $request->cdo_fecha_hasta,
                $request->ordenDireccion,
                ($request->filled('proceso_background') && $request->proceso_background == true ? true : false)
            );

        $idAnterior  = $consulta['documentos']->first() ? $consulta['documentos']->first()->cdo_id : null;
        $idSiguiente = $consulta['documentos']->last() ? $consulta['documentos']->last()->cdo_id : null;

        return [
            'pag_anterior' => $idAnterior ? ($this->particionamientoRecepcionValidacionDocumentoRepository->mostrarLinkAnteriorSiguiente(
                    clone $consulta['query'],
                    $idAnterior,
                    $request->cdo_fecha_desde,
                    $request->cdo_fecha_hasta,
                    $request->columnaOrden,
                    $request->ordenDireccion,
                    'anterior'
                ) ? base64_encode(json_encode(['cdoId' => $idAnterior, 'apuntarSiguientes' => false])) : null) : null,
            'pag_siguiente' => $idSiguiente ? ($this->particionamientoRecepcionValidacionDocumentoRepository->mostrarLinkAnteriorSiguiente(
                    clone $consulta['query'],
                    $idSiguiente,
                    $request->cdo_fecha_desde,
                    $request->cdo_fecha_hasta,
                    $request->columnaOrden,
                    $request->ordenDireccion,
                    'siguiente'
                ) ? base64_encode(json_encode(['cdoId' => $idSiguiente, 'apuntarSiguientes' => true])) : null) : null,
            'data' => $consulta['documentos'],
        ];
    }
}