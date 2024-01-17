<?php
namespace App\Http\Modulos\Recepcion\Particionamiento\Services;

use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use openEtl\Tenant\Traits\Particionamiento\TenantParticionamientoTrait;
use App\Http\Modulos\Recepcion\Particionamiento\Exports\DocumentosRecibidosExcelExport;
use App\Http\Modulos\Recepcion\Particionamiento\Repositories\ParticionamientoRecepcionRepository;

class ParticionamientoRecepcionService {
    use TenantParticionamientoTrait;

    /**
     * Instancia de la clase ParticionamientoRecepcionRepository.
     *
     * @var ParticionamientoRecepcionRepository
     */
    public ParticionamientoRecepcionRepository $particionamientoRecepcionRepository;

    /**
     * Cantidad máxima de registros que se incluyen en un excel.
     *
     * @var integer
     */
    private int $cantidadRegistrosPorExcel = 20000;

    public function __construct(ParticionamientoRecepcionRepository $particionamientoRecepcionRepository) {
        $this->particionamientoRecepcionRepository = $particionamientoRecepcionRepository;
    }

    /**
     * Cuando el proceso es en background se genera un ZIP que contiene los archivos de Excel generados en el proceso
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
     * Genera el Excel de documentos recibidos.
     *
     * @param Collection $documentos Colección de documentos recibidos
     * @param string $cdoFechaDesde Fecha inicial de la consulta
     * @param string $cdoFechaHasta Fecha final de la consulta
     * @param string $ordenamiento Indica el tipo de ordenamiento para el resultado
     * @param bool $retonarArray Indica si se debe retornar el excel en un array (Aplica cuando el proceso es en background)
     * @return array|BinaryFileResponse
     */
    private function generarExcelDocumentosRecibidos(Collection $documentos, string $cdoFechaDesde, string $cdofechaHasta, string $ordenamiento, bool $retonarArray = false) {
        date_default_timezone_set('America/Bogota');

        $erroresParticiones = [];
        $particiones        = $this->generarPeriodosParticionamiento($cdoFechaDesde, $cdofechaHasta, 'recepcion', $ordenamiento);

        foreach($particiones as $particion) {
            $existeTablaParticionCabecera = $this->particionamientoRecepcionRepository->existeTabla($this->particionamientoRecepcionRepository::CONEXION, 'rep_cabecera_documentos_' . $particion);
            $existeTablaParticionEstados  = $this->particionamientoRecepcionRepository->existeTabla($this->particionamientoRecepcionRepository::CONEXION, 'rep_estados_documentos_' . $particion);

            if(!$existeTablaParticionCabecera)
                $erroresParticiones[] = 'rep_cabecera_documentos_' . $particion;

            if(!$existeTablaParticionEstados)
                $erroresParticiones[] = 'rep_estados_documentos_' . $particion;
        }

        if(!empty($erroresParticiones))
            throw new \Exception('No existen las siguientes tablas particionadas: ' . implode(', ', $erroresParticiones));

        $contArchivos  = 1;
        $fechaHoraGen  = date('YmdHis');
        $uuid          = Uuid::uuid4()->toString();
        File::makeDirectory(storage_path('etl/descargas/' . $uuid), 0755, true, true);

        foreach($particiones as $particion) {
            $consultaDocumentos = $this->particionamientoRecepcionRepository->joinsExcelDocumentosRecibidos($documentos['query']->clone(), $particion);
            $consultaDocumentos->chunk($this->cantidadRegistrosPorExcel, function ($documentosPorArchivo) use ($documentos, $particion, &$contArchivos, $fechaHoraGen, $uuid) {
                $nombreExcel = 'documentos_recibidos_particion_' . $particion . '_' . $fechaHoraGen . '_' . str_pad($contArchivos, 5, '0', STR_PAD_LEFT) . '.xlsx';
                Excel::store(new DocumentosRecibidosExcelExport($documentos['ofe'], $documentosPorArchivo), $uuid . '/' . $nombreExcel, 'etl', \Maatwebsite\Excel\Excel::XLSX,
                    [
                        'lazy' => true,
                        'chunck_size' => 2000,
                    ]
                );
        
                $contArchivos++;
                flush();
            });
        }

        $nombreZip = 'documentos_recibidos_' . $uuid . '.zip';
        $this->crearZipExcel($uuid, $nombreZip);

        if(!$retonarArray) { // Ingresa cuando la solicitud de descarga NO es en background
            return response()
                ->download(storage_path('etl/descargas/' . $nombreZip), $nombreZip, [
                    header('Access-Control-Expose-Headers: Content-Disposition')
                ])
                ->deleteFileAfterSend(true);
        } else { // Ingresa cuando la solicitud de descarga es en background
            return [
                'nombre' => $nombreZip
            ];
        }
    }

    /**
     * Retorna una lista paginada de documentos recibidos de acuerdo a los parámetros del request.
     *
     * @param Request $request Parámetros de la petición
     * @return array|Collection
     */
    public function consultarDocumentosRecibidos(Request $request) {
        $consulta = $this->particionamientoRecepcionRepository->consultarDocumentosRecibidos($request);

        if($request->filled('excel') && $request->excel == true)
            return $this->generarExcelDocumentosRecibidos(
                $consulta,
                $request->cdo_fecha_desde,
                $request->cdo_fecha_hasta,
                $request->ordenDireccion,
                ($request->filled('proceso_background') && $request->proceso_background == true ? true : false)
            );

        $idAnterior  = $consulta['documentos']->first() ? $consulta['documentos']->first()->cdo_id : null;
        $idSiguiente = $consulta['documentos']->last() ? $consulta['documentos']->last()->cdo_id : null;

        return [
            'pag_anterior' => $idAnterior ? ($this->particionamientoRecepcionRepository->mostrarLinkAnteriorSiguiente(
                    clone $consulta['query'],
                    $idAnterior,
                    $request->cdo_fecha_desde,
                    $request->cdo_fecha_hasta,
                    $request->columnaOrden,
                    $request->ordenDireccion,
                    'anterior'
                ) ? base64_encode(json_encode(['cdoId' => $idAnterior, 'apuntarSiguientes' => false])) : null) : null,
            'pag_siguiente' => $idSiguiente ? ($this->particionamientoRecepcionRepository->mostrarLinkAnteriorSiguiente(
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

    /**
     * Retorna una lista de coincidencias para el autocomplete del campo Lote del tracking de documentos recibidos.
     *
     * @param string $lote Cadena que debe ser buscada para coincidencias en la tabla
     * @return array
     */
    public function autocompleteLote(string $lote): array {
        $listaLotes = $this->particionamientoRecepcionRepository->autocompleteLote($lote);

        return [
            'data' => $listaLotes
        ];
    }

    public function descargarDocumentos(Request $request): array {
        $arrCdoIds           = explode(',', $request->cdo_ids);
        $arrTiposDocumentos  = explode(',', $request->tipos_documentos);
        $fraseErrorDescargas = 'No se encontraron documentos para descargar';

        if (count($arrTiposDocumentos) == 1 && count($arrCdoIds) == 1) {

        } elseif (count($arrTiposDocumentos) >= 1 || count($arrCdoIds) >= 1) {

        } else {
            return [
                'message' => 'Error en la Petición',
                'errors'  => ['No se indicaron los tipos de documentos y/o documentos a descargar'],
                'codigo'  => JsonResponse::HTTP_BAD_REQUEST
            ];
        }
    }
}