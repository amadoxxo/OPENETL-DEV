<?php
namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Services;

use Illuminate\Http\Request;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Helpers\HelperRepGestionDocumento;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Repositories\RepAutorizacionEtapaRepository;

class RepAutorizacionEtapaService {

   /**
     * Instancia de la clase RepAutorizacionEtapaRepository.
     *
     * @var RepAutorizacionEtapaRepository
     */
    public RepAutorizacionEtapaRepository $repAutorizacionEtapaRepository;

    public function __construct(RepAutorizacionEtapaRepository $repAutorizacionEtapaRepository) {
        $this->repAutorizacionEtapaRepository = $repAutorizacionEtapaRepository;
    }

    /**
     * Retorna la información del documento que sera autorizado.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function consultarGestionDocumento(Request $request): array {
        $arrErrores = [];
        $documento  = $this->repAutorizacionEtapaRepository->consultarGestionDocumento($request);

        if ($documento) {
            $arrEtapaActual = HelperRepGestionDocumento::etapaActualDocumento($documento);
            $documento->nombre_etapa_actual = $arrEtapaActual['nombre_etapa_actual'];
            $documento->numero_etapa_actual = $arrEtapaActual['numero_etapa_actual'];
        } else 
            $arrErrores[] = "El documento seleccionado no existe";

        return [
            'data'    => $documento,
            'errores' => $arrErrores
        ];
    }

    /**
     *  Realiza el proceso de autorizar etapa, lo que permite devolver un documento a la etapa anterior.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function autorizarEtapa(Request $request): array {
        $respuesta = $this->repAutorizacionEtapaRepository->autorizarEtapa($request);

        return [
            'errores' => $respuesta
        ];        
    }
}
