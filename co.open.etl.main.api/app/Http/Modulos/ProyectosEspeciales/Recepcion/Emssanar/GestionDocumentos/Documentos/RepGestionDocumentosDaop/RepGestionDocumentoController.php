<?php
namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Services\RepGestionDocumentoService;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Requests\ListaEtapasGestionDocumentosRequest;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Requests\TrackingGestionDocumentosAccionesEnBloqueRequest;

/**
 * Controlador de particionamiento en Recepción
 */
class RepGestionDocumentoController extends Controller {
    /**
     * Instancia del servicio RepGestionDocumentoService.
     *
     * @var RepGestionDocumentoService
     */
    protected $repGestionDocumentosService;

    public function __construct(
        RepGestionDocumentoService $repGestionDocumentosService
    ) {
        $this->repGestionDocumentosService = $repGestionDocumentosService;

        $this->middleware(['jwt.auth', 'jwt.refresh']);

        // Recursos asignados de los métodos
        $recursos = [
            'RecepcionGestionDocumentosEtapa1',
            'RecepcionGestionDocumentosEtapa1DescargarExcel',
            'RecepcionGestionDocumentosEtapa1GestionarFeDs',
            'RecepcionGestionDocumentosEtapa1CentroOperaciones',
            'RecepcionGestionDocumentosEtapa1SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa2',
            'RecepcionGestionDocumentosEtapa2DescargarExcel',
            'RecepcionGestionDocumentosEtapa2GestionarFeDs',
            'RecepcionGestionDocumentosEtapa2CentroCosto',
            'RecepcionGestionDocumentosEtapa2SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa3',
            'RecepcionGestionDocumentosEtapa3DescargarExcel',
            'RecepcionGestionDocumentosEtapa3GestionarFeDs',
            'RecepcionGestionDocumentosEtapa3SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa4',
            'RecepcionGestionDocumentosEtapa4DescargarExcel',
            'RecepcionGestionDocumentosEtapa4GestionarFeDs',
            'RecepcionGestionDocumentosEtapa4DatosContabilizado',
            'RecepcionGestionDocumentosEtapa4SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa5',
            'RecepcionGestionDocumentosEtapa5DescargarExcel',
            'RecepcionGestionDocumentosEtapa5GestionarFeDs',
            'RecepcionGestionDocumentosEtapa5SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa6',
            'RecepcionGestionDocumentosEtapa6DescargarExcel',
            'RecepcionGestionDocumentosEtapa6GestionarFeDs',
            'RecepcionGestionDocumentosEtapa6SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa7',
            'RecepcionGestionDocumentosEtapa7DescargarExcel',
        ];
        $this->middleware([
            'VerificaMetodosRol:'.implode(",", $recursos)
        ])->only([
            'getListaEtapasGestionDocumentos',
            'searchEmisores'
        ]);

        // Recursos asignados del método
        $recursos = [
            'RecepcionGestionDocumentosEtapa1GestionarFeDs',
            'RecepcionGestionDocumentosEtapa2GestionarFeDs',
            'RecepcionGestionDocumentosEtapa3GestionarFeDs',
            'RecepcionGestionDocumentosEtapa4GestionarFeDs',
            'RecepcionGestionDocumentosEtapa5GestionarFeDs',
            'RecepcionGestionDocumentosEtapa6GestionarFeDs',
        ];
        $this->middleware([
            'VerificaMetodosRol:'.implode(",", $recursos)
        ])->only([
            'gestionarEtapas'
        ]);

        // Recursos asignados del método
        $recursos = [
            'RecepcionGestionDocumentosEtapa1SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa2SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa3SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa4SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa5SiguienteEtapa',
            'RecepcionGestionDocumentosEtapa6SiguienteEtapa',
        ];
        $this->middleware([
            'VerificaMetodosRol:'.implode(",", $recursos)
        ])->only([
            'siguienteEtapa'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionGestionDocumentosEtapa1CentroOperaciones'
        ])->only([
            'asignarCentroOperacion'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionGestionDocumentosEtapa2CentroCosto'
        ])->only([
            'asignarCentroCosto'
        ]);

        $this->middleware([
            'VerificaMetodosRol:RecepcionGestionDocumentosEtapa4DatosContabilizado'
        ])->only([
            'datosContabilizado'
        ]);
    }

    /**
     * Retorna una lista paginada con la gestión de documentos por etapas de acuerdo a los parámetros del request.
     *
     * @param ListaEtapasGestionDocumentosRequest $request Parámetros de la petición
     * @return array|JsonResponse|BinaryFileResponse
     */
    public function getListaEtapasGestionDocumentos(ListaEtapasGestionDocumentosRequest $request) {
        set_time_limit(0);
        ini_set('memory_limit','2048M');

        try {
            $documentos = $this->repGestionDocumentosService->consultarEtapasGestionDocumentos($request);
            return response()->json($documentos, JsonResponse::HTTP_OK);
        } catch(\Exception $e) {
            return response()->json(['message' => 'Error al realizar la consulta', 'errors' => [$e->getMessage()]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Retorna una lista de coincidencias para el autocomplete del campo Emisor del tracking de gestión de documentos.
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function searchEmisores(Request $request): JsonResponse {
        if(!$request->filled('ofe_id'))
            return response()->json([
                'message' => 'Consulta de Emisores',
                'errors'  => [
                    'El Id del OFE es requerido'
                ]
            ], JsonResponse::HTTP_BAD_REQUEST);

        $listaEmisores = $this->repGestionDocumentosService->searchAdquirentes($request);

        return response()->json($listaEmisores, JsonResponse::HTTP_OK);
    }

    /**
     * Permite realizar el proceso de gestión de los documentos FE|DS para una etapa específica.
     *
     * @param TrackingGestionDocumentosAccionesEnBloqueRequest $request Parámetros de la petición
     * @return JsonResponse
     */
    public function gestionarEtapas(TrackingGestionDocumentosAccionesEnBloqueRequest $request): JsonResponse {
        $arrErrores = [];

        // Realiza las validaciones sobre el documento
        $respuesta  = $this->repGestionDocumentosService->validaDocumentoGestionarEtapas($request);
        $arrErrores = array_merge($arrErrores, $respuesta['errores']);

        // Si llega el parámetro validar se retornan los errores antes de abrir la modal para el frontEnd
        if ($request->filled('validar') && $request->validar) {
            if (count($arrErrores) > 0)
                return response()->json([
                    'message' => 'Errores al gestionar la etapa',
                    'errors'  => $arrErrores
                ], JsonResponse::HTTP_BAD_REQUEST);
            else
                return response()->json([
                    'success' => true
                ], JsonResponse::HTTP_OK);
        }

        if (count($arrErrores) == 0) {
            // Actualiza la información de gestionar para los documentos solicitados
            $respuesta  = $this->repGestionDocumentosService->actualizarGestionDocumentos($request);
            $arrErrores = array_merge($arrErrores, $respuesta['errores']);
        }

        if (count($arrErrores) > 0)
            return response()->json([
                'message' => 'Errores al gestionar la etapa',
                'errors'  => $arrErrores
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        return response()->json([
            'success' => true
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Asigna el centro de costo sobre la gestión del documento en la etapa 2.
     *
     * @param TrackingGestionDocumentosAccionesEnBloqueRequest $request Parámetros de la petición
     * @return JsonResponse
     */
    public function asignarCentroCosto(TrackingGestionDocumentosAccionesEnBloqueRequest $request): JsonResponse {
        $arrErrores = [];
        $request->merge([
            'etapa' => 2
        ]);

        // Realiza las validaciones sobre el documento
        $respuesta  = $this->repGestionDocumentosService->validacionesGeneralesGestionDocumentos($request);
        $arrErrores = array_merge($arrErrores, $respuesta['errores']);

        // Si llega el parámetro validar se retornan los errores antes de abrir la modal para el frontEnd
        if ($request->filled('validar') && $request->validar) {
            if (count($arrErrores) > 0)
                return response()->json([
                    'message' => 'Errores al asignar centro de costo',
                    'errors'  => $arrErrores
                ], JsonResponse::HTTP_BAD_REQUEST);
            else
                return response()->json([
                    'success' => true
                ], JsonResponse::HTTP_OK);
        }

        if (count($arrErrores) == 0) {
            $respuesta  = $this->repGestionDocumentosService->asignarCentroCostos($request);
            $arrErrores = array_merge($arrErrores, $respuesta['errores']);
        }

        if (count($arrErrores) > 0)
            return response()->json([
                'message' => 'Errores al gestionar la etapa',
                'errors'  => $arrErrores
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        return response()->json([
            'success' => true
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Asigna el centro de operaciones sobre la gestión del documento en la etapa 1.
     *
     * @param TrackingGestionDocumentosAccionesEnBloqueRequest $request Parámetros de la petición
     * @return JsonResponse
     */
    public function asignarCentroOperaciones(TrackingGestionDocumentosAccionesEnBloqueRequest $request): JsonResponse {
        $arrErrores = [];
        $request->merge([
            'etapa' => 1
        ]);

        // Realiza las validaciones sobre el documento
        $respuesta  = $this->repGestionDocumentosService->validacionesGeneralesGestionDocumentos($request);
        $arrErrores = array_merge($arrErrores, $respuesta['errores']);

        // Si llega el parámetro validar se retornan los errores antes de abrir la modal para el frontEnd
        if ($request->filled('validar') && $request->validar) {
            if (count($arrErrores) > 0)
                return response()->json([
                    'message' => 'Errores al asignar centro de operación',
                    'errors'  => $arrErrores
                ], JsonResponse::HTTP_BAD_REQUEST);
            else
                return response()->json([
                    'success' => true
                ], JsonResponse::HTTP_OK);
        }

        if (count($arrErrores) == 0) {
            $respuesta  = $this->repGestionDocumentosService->asignarCentroOperaciones($request);
            $arrErrores = array_merge($arrErrores, $respuesta['errores']);
        }

        if (count($arrErrores) > 0)
            return response()->json([
                'message' => 'Errores al gestionar la etapa',
                'errors'  => $arrErrores
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        return response()->json([
            'success' => true
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Actualiza los estados sobre la gestión del documento para pasar a la siguiente etapa.
     *
     * @param TrackingGestionDocumentosAccionesEnBloqueRequest $request Parámetros de la petición
     * @return JsonResponse
     */
    public function siguienteEtapa(TrackingGestionDocumentosAccionesEnBloqueRequest $request): JsonResponse {
        $arrErrores = [];

        // Realiza las validaciones sobre el documento
        $respuesta  = $this->repGestionDocumentosService->validacionesGeneralesGestionDocumentos($request, 'siguiente-etapa');
        $arrErrores = array_merge($arrErrores, $respuesta['errores']);

        // Si llega el parámetro validar se retornan los errores antes de abrir la modal para el frontEnd
        if ($request->filled('validar') && $request->validar) {
            if (count($arrErrores) > 0)
                return response()->json([
                    'message' => 'Errores al actualizar la gestión del documento',
                    'errors'  => $arrErrores
                ], JsonResponse::HTTP_BAD_REQUEST);
            else
                return response()->json([
                    'success' => true
                ], JsonResponse::HTTP_OK);
        }

        // Realiza la validación sobre el centro de operación seleccionado
        if (count($arrErrores) == 0) {
            $respuesta  = $this->repGestionDocumentosService->actualizarEstadoSiguienteEtapa($request);
            $arrErrores = array_merge($arrErrores, $respuesta['errores']);
        }

        if (count($arrErrores) > 0)
            return response()->json([
                'message' => 'Errores al actualizar la gestión del documento',
                'errors'  => $arrErrores
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        return response()->json([
            'success' => true
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Obtiene la información detallada de cada etapa del documento en gestión.
     *
     * @param int $gdoId Id del documento en gestión
     * @return JsonResponse
     */
    public function verDetalleEtapas(int $gdoId): JsonResponse {
        $documentos = $this->repGestionDocumentosService->consultarDetalleEtapas($gdoId);
        return response()->json($documentos, JsonResponse::HTTP_OK);
    }

    /**
     * Procesa los documentos para asignar los datos contabilizado en la etapa 4.
     * 
     * @param TrackingGestionDocumentosAccionesEnBloqueRequest $request Parámetros de la petición
     * 
     * @return JsonResponse
     */
    public function datosContabilizado(TrackingGestionDocumentosAccionesEnBloqueRequest $request): JsonResponse {
        $arrErrores = [];
        $request->merge([
            'etapa' => 4
        ]);

        // Realiza las validaciones sobre los documentos
        $respuesta  = $this->repGestionDocumentosService->validacionesGeneralesGestionDocumentos($request);
        $arrErrores = array_merge($arrErrores, $respuesta['errores']);

        // Si llega el parámetro validar se retornan los errores antes de abrir la modal para el frontEnd
        if ($request->filled('validar') && $request->validar) {
            if (count($arrErrores) > 0)
                return response()->json([
                    'message' => 'Errores al actualizar la gestión del documento',
                    'errors'  => $arrErrores
                ], JsonResponse::HTTP_BAD_REQUEST);
            else
                return response()->json([
                    'success' => true
                ], JsonResponse::HTTP_OK);
        }

        // Realiza la actualización de los datos contabilizado
        if (count($arrErrores) == 0) {
            $respuesta  = $this->repGestionDocumentosService->actualizarDatosContabilizado($request);
            $arrErrores = array_merge($arrErrores, $respuesta['errores']);        
        }

        if (count($arrErrores) > 0)
            return response()->json([
                'message' => 'Errores al actualizar la gestión del documento',
                'errors'  => $arrErrores
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        return response()->json([
            'success' => true
        ], JsonResponse::HTTP_OK);
    }
}