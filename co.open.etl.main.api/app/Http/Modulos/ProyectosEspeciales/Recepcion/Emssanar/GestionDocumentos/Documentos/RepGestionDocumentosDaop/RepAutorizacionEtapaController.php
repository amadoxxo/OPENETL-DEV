<?php
namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Requests\AutorizacionEtapaRequest;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Services\RepAutorizacionEtapaService;

/**
 * Controlador de particionamiento en Recepción
 */
class RepAutorizacionEtapaController extends Controller {
    /**
      * Instancia del servicio RepAutorizacionEtapaService.
     *
     * @var RepAutorizacionEtapaService
     */
    protected $repAutorizacionEtapaService;

    public function __construct(
        RepAutorizacionEtapaService $repAutorizacionEtapaService
    ) {
        $this->repAutorizacionEtapaService = $repAutorizacionEtapaService;

        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:RecepcionAutorizacionEtapas'
        ])->only([
            'consultarGestionDocumento',
            'autorizacionEtapas'
        ]);
    }

    /**
     * Retorna la información del documento que sera autorizado.
     *
     * @param AutorizacionEtapaRequest $request Parámetros de la petición
     * @return JsonResponse
     */
    public function consultarGestionDocumento(AutorizacionEtapaRequest $request): JsonResponse {
        $documento = $this->repAutorizacionEtapaService->consultarGestionDocumento($request);

        if (count($documento['errores']) > 0)
            return response()->json([
                'message' => 'Error al consultar el documento',
                'errors'  => $documento['errores']
            ], JsonResponse::HTTP_NOT_FOUND);
        
        return response()->json(['data' => $documento['data']], JsonResponse::HTTP_OK);
    }

    /**
     * Realiza el proceso de autorizar etapa, lo que permite devolver un documento a la etapa anterior.
     * 
     * @param AutorizacionEtapaRequest $request Parámetros de la petición
     * @return JsonResponse
     */
    public function autorizacionEtapas(AutorizacionEtapaRequest $request): JsonResponse {
        $respuesta = $this->repAutorizacionEtapaService->autorizarEtapa($request);

        if (count($respuesta['errores']) > 0)
            return response()->json([
                'message' => 'Errores al autorizar el documento',
                'errors'  => $respuesta['errores']
            ], JsonResponse::HTTP_BAD_REQUEST);
        else
            return response()->json([
                'success' => true
            ], JsonResponse::HTTP_OK);
    }
}