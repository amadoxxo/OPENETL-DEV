<?php

namespace App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Modulos\Radian\RadianRegistrarDocumento;

class RadianCabeceraDocumentoDaopController extends Controller {

    /**
     * Instancia de la clase RadianRegistrarDocumento que permite realizar el procesamiento de los documentos agendados
     *
     * @var RadianRegistrarDocumento
     */
    protected $radianRegistrarDocumento;

    /**
     * Constructor de la clase.
     */
    public function __construct(RadianRegistrarDocumento $radianRegistrarDocumento) {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->radianRegistrarDocumento = $radianRegistrarDocumento;
    }

    /**
     * Permite registrar documentos para el módulo de Radian por medio de un JSON.
     *
     * @param request $request Objeto Json que llega mediante el endpoint 
     * @return JsonResponse
     */
    public function registrarDocumentos(Request $request): JsonResponse {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        if (!$request->filled('documentos')) {
            return response()->json([
                'message' => 'Error al registrar el Json',
                'errors'  => ['No se encontraron documentos para procesar en la petición']
            ], 400);
        }

        // Crea el registro en las tablas de Radian
        $procesado = $this->radianRegistrarDocumento->run($request);
        
        return response()->json($procesado, 200);
    }
}
