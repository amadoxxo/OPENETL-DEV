<?php
namespace App\Http\Modulos\Recepcion\Particionamiento;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Modulos\Recepcion\Particionamiento\Requests\DescargarDocumentosRequest;
use App\Http\Modulos\Recepcion\Particionamiento\Requests\ListaDocumentosRecibidosRequest;
use App\Http\Modulos\Recepcion\Particionamiento\Services\ParticionamientoRecepcionService;

/**
 * Controlador de particionamiento en Recepción
 */
class ParticionamientoRecepcionController extends Controller {
    /**
     * Instancia del servicio ParticionamientoRecepcionService
     *
     * @var ParticionamientoRecepcionService
     */
    protected $particionamientoRecepcionService;

    public function __construct(
        ParticionamientoRecepcionService $particionamientoRecepcionService
    ) {
        $this->particionamientoRecepcionService = $particionamientoRecepcionService;

        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:RecepcionDocumentosManuales,RecepcionDocumentosRecibidos,RecepcionDocumentosRecibidosDescargar,RecepcionDocumentoNoElectronicoNuevo,RecepcionDocumentoNoElectronicoEditar,RecepcionDocumentoNoElectronicoVer'
        ])->only([
            'autocompleteLote',
            'getListaDocumentosRecibidos',
            // 'descargarDocumentos',
            // 'agendarConsultaEstadoDian',
            // 'documentosManuales',
            // 'getListaErroresDocumentos',
            // 'descargarListaErroresDocumentos',
            // 'descargarDocumentosAnexos',
            // 'consultaDocumentos',
            // 'consultarDocumentoNoElectronico'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionDocumentosRecibidosDescargar'
        ])->only([
            'autocompleteLote'
        ]);

        // TODO: AGREGAR LOS MIDDLEWARE DE PERMISOS NECESARIOS
    }

    /**
     * Retorna una lista paginada de documentos recibidos de acuerdo a los parámetros del request.
     *
     * @param ListaDocumentosRecibidosRequest $request Parámetros de la petición
     * @return array|JsonResponse|BinaryFileResponse
     */
    public function getListaDocumentosRecibidos(ListaDocumentosRecibidosRequest $request) {
        set_time_limit(0);
        ini_set('memory_limit','2048M');

        if($request->filled('excel') && $request->excel == true) {
            try {
                return $this->particionamientoRecepcionService->consultarDocumentosRecibidos($request);
            } catch(\Exception $e) {
                if($request->filled('proceso_background') && $request->proceso_background == true)
                    return [
                        'errors' => [ $e->getMessage() ],
                        'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
                    ];
                else
                    return response()->json(['message' => 'Error al general el Excel', 'errors' => [$e->getMessage()]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $documentos = $this->particionamientoRecepcionService->consultarDocumentosRecibidos($request);
            return response()->json($documentos, JsonResponse::HTTP_OK);
        } catch(\Exception $e) {
            return response()->json(['message' => 'Error al realizar la consulta', 'errors' => [$e->getMessage()]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Genera un reporte en Excel de acuerdo a los filtros escogidos.
     * 
     * El reporte debe haber sido solicitado para su procesamiento en background
     *
     * @param  Request $request Parámetros de la petición
     * @param  string $pjj_tipo Tipo de reporte a generar
     * @return array
     * @throws \Exception
     */
    public function procesarAgendamientoReporte(Request $request, string $pjj_tipo): array {
        try {
            $request->merge([
                'proceso_background' => true
            ]);

            switch ($pjj_tipo) {
                case 'RRECIBIDOS':
                    $arrExcel =  $this->getListaDocumentosRecibidos(ListaDocumentosRecibidosRequest::createFrom($request));
                    break;
                /* case 'RVALIDACION':
                    $arrExcel = $this->getListaValidacionDocumentos($request);
                    break; */
            }

            if(array_key_exists('nombre', $arrExcel))
                return [
                    'errors'  => [],
                    'archivo' => $arrExcel['nombre']
                ];
            else
                return [
                    'errors' => $arrExcel['errors'],
                    'traza'  => $arrExcel['traza']
                ];
        } catch (\Exception $e) {
            return [
                'errors' => [ $e->getMessage() ],
                'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
            ];
        }
    }

    /**
     * Retorna una lista de coincidencias para el autocomplete del campo Lote del tracking de documentos recibidos.
     *
     * @param string $lote Cadena que debe ser buscada para coincidencias en la tabla
     * @return JsonResponse
     */
    public function autocompleteLote(string $lote): JsonResponse {
        if(empty($lote))
            return response()->json([
                'message' => 'Consulta de lotes de procesamiento',
                'errors'  => [
                    'El parámetro de búsqueda es vacio'
                ]
            ], JsonResponse::HTTP_BAD_REQUEST);

        $listaLotes = $this->particionamientoRecepcionService->autocompleteLote($lote);

        return response()->json($listaLotes, JsonResponse::HTTP_OK);
    }

    /**
     * Permite la descarga de documentos desde los tracking en recepción.
     *
     * @param DescargarDocumentosRequest $request Parámetros de la petición
     * @return void
     */
    public function descargarDocumentos(DescargarDocumentosRequest $request) {
        $descargasProcesadas = $this->particionamientoRecepcionService->descargarDocumentos($request);

        if(array_key_exists('errors', $descargasProcesadas) && !empty($descargasProcesadas['errors'])) {
            return response()->json([
                'message' => $descargasProcesadas['message'],
                'errors'  => $descargasProcesadas['errors'],
            ], $descargasProcesadas['codigo']);
        }

        
    }
}