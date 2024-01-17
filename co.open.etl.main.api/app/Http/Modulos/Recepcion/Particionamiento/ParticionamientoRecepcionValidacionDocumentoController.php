<?php
namespace App\Http\Modulos\Recepcion\Particionamiento;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Modulos\Recepcion\Particionamiento\Requests\ListaValidacionDocumentosRequest;
use App\Http\Modulos\Recepcion\Particionamiento\Services\ParticionamientoRecepcionValidacionDocumentoService;

/**
 * Controlador de particionamiento en Recepción > Validación de Documentos.
 */
class ParticionamientoRecepcionValidacionDocumentoController extends Controller {
    /**
     * Instancia del servicio ParticionamientoRecepcionValidacionDocumentoService.
     *
     * @var ParticionamientoRecepcionValidacionDocumentoService
     */
    protected $particionamientoRecepcionValidacionDocumentoService;

    /**
     * Constructor de la clase.
     *
     * @param  ParticionamientoRecepcionValidacionDocumentoService $particionamientoRecepcionValidacionDocumentoService Instancia hacia el servicio
     * @return void
     */
    public function __construct(ParticionamientoRecepcionValidacionDocumentoService $particionamientoRecepcionValidacionDocumentoService) {
        $this->particionamientoRecepcionValidacionDocumentoService = $particionamientoRecepcionValidacionDocumentoService;

        $this->middleware(['jwt.auth','jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:RecepcionValidacionDocumentos,RecepcionValidacionDocumentosValidar,RecepcionValidacionDocumentosRechazar,RecepcionValidacionDocumentosPagar,RecepcionValidacionDocumentosAsignar,RecepcionValidacionDocumentosLiberar,RecepcionValidacionDescargarExcel'
        ])->only([
            'getListaValidacionDocumentos'
        ]);
    }

    /**
     * Retorna una lista paginada de los documentos de validación de acuerdo a los parámetros del request.
     *
     * @param ListaValidacionDocumentosRequest $request Parámetros de la petición
     * @return array|JsonResponse|BinaryFileResponse
     */
    public function getListaValidacionDocumentos(ListaValidacionDocumentosRequest $request) {
        set_time_limit(0);
        ini_set('memory_limit','2048M');

        if($request->filled('excel') && $request->excel == true) {
            try {
                return $this->particionamientoRecepcionValidacionDocumentoService->consultarValidacionDocumentos($request);
            } catch(\Exception $e) {
                if($request->filled('proceso_background') && $request->proceso_background == true)
                    return [
                        'errors' => [ $e->getMessage() ],
                        'traza'  => [ $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() ]
                    ];
                else
                    return response()->json(['message' => 'Error al general el Excel', 'errors' => [$e->getMessage()]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY, [
                        header('Access-Control-Expose-Headers: X-Error-Status, X-Error-Message'),
                        header('X-Error-Status: ' . JsonResponse::HTTP_UNPROCESSABLE_ENTITY),
                        header("X-Error-Message: {$e->getMessage()}")
                    ]);
            }
        }

        try {
            $documentos = $this->particionamientoRecepcionValidacionDocumentoService->consultarValidacionDocumentos($request);
            return response()->json($documentos, JsonResponse::HTTP_OK);
        } catch(\Exception $e) {
            return response()->json(['message' => 'Error al realizar la consulta', 'errors' => [$e->getMessage()]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Genera un reporte en Excel de acuerdo a los filtros diligenciados.
     * 
     * El reporte debe haber sido solicitado para su procesamiento en background
     *
     * @param  Request $request Parámetros de la petición
     * @param  string  $pjj_tipo Tipo de reporte a generar
     * @return array
     * @throws \Exception
     */
    public function procesarAgendamientoReporte(Request $request, string $pjj_tipo): array {
        try {
            $request->merge([
                'proceso_background' => true
            ]);

            switch ($pjj_tipo) {
                case 'RVALIDACION':
                    $arrExcel = $this->getListaValidacionDocumentos($request);
                    break;
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
}