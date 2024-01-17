<?php
namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\CursorPaginator;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Helpers\HelperRepGestionDocumento;
use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Repositories\RepGestionDocumentoRepository;

class RepGestionDocumentoService {

    /**
     * Instancia de la clase RepGestionDocumentoRepository.
     *
     * @var RepGestionDocumentoRepository
     */
    public RepGestionDocumentoRepository $repGestionDocumentoRepository;

    /**
     * Constructor de la clase.
     * 
     * @param RepGestionDocumentoRepository $repGestionDocumentoRepository Instancia del repositorio
     */
    public function __construct(RepGestionDocumentoRepository $repGestionDocumentoRepository) {
        $this->repGestionDocumentoRepository = $repGestionDocumentoRepository;
    }

    /**
     * Retorna una lista paginada con la gestión de documentos por etapas de acuerdo a los parámetros del request.
     *
     * @param Request $request Parámetros de la petición
     * @return array|Collection
     */
    public function consultarEtapasGestionDocumentos(Request $request) {
        $consulta   = $this->repGestionDocumentoRepository->consultarEtapasGestionDocumentos($request);
        $documentos = $this->datosAdicionalesGestionDocumentos($request->etapa, $consulta);

        return [
            'pag_anterior'  => !empty($consulta->previousCursor()) ? base64_encode(json_encode($consulta->previousCursor()->toArray())) : null,
            'pag_siguiente' => !empty($consulta->nextCursor()) ? base64_encode(json_encode($consulta->nextCursor()->toArray())) : null,
            'data'          => $documentos->items(),
        ];
    }

    /**
     * Permite identificar el estado en gestión de los documentos dependiendo de la etapa seleccionada.
     *
     * @param string $etapa Número de la etapa
     * @param CursorPaginator $documento Información del documento
     * @return CursorPaginator
     */
    private function datosAdicionalesGestionDocumentos(string $etapa, CursorPaginator $documentos): CursorPaginator {
        $documentos->map(function ($documento) use ($etapa) {
            // Obtiene la razón social del emisor dependiendo si es un adquirente o proveedor
            if ($documento->gdo_modulo == 'EMISION')
                $documento->emisor_razon_social = $documento->getConfiguracionAdquirente->nombre_completo;
            else
                $documento->emisor_razon_social = $documento->getConfiguracionProveedor->nombre_completo;

            // Obtiene el estado actual de la etapa
            $estadoGestion = HelperRepGestionDocumento::obtieneEstadoActualEtapa($documento, $etapa);
            $documento->estado_gestion = $estadoGestion;
        });

        return $documentos;
    }

    /**
     * Retorna una lista de coincidencias para el autocomplete del campo Emisor del tracking de gestión de documentos.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function searchAdquirentes(Request $request): array {
        $listaEmisores = $this->repGestionDocumentoRepository->searchAdquirentes($request);

        return [
            'data' => $listaEmisores
        ];
    }

    /**
     * Realiza las validaciones necesarias del documento en gestión dependiendo de la etapa seleccionada.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function validaDocumentoGestionarEtapas(Request $request): array {
        $arrErrores = [];
        $arrGdoIds  = explode(',', $request->gdo_ids);

        foreach ($arrGdoIds as $gdoId) {
            $documento = $this->repGestionDocumentoRepository->consultarGestionDocumento($gdoId);

            // Se realizan las validaciones
            if ($documento) {
                if ($documento->estado != "ACTIVO")
                    $arrErrores[] = "El documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] se encuentra en estado INACTIVO";

                $error = false;
                switch ($request->etapa) {
                    case 1:
                        if (!($documento->gdo_estado_etapa1 == "SIN_GESTION" || $documento->gdo_estado_etapa2 == "REVISION_NO_CONFORME"))
                            $error = true;
                        break;
                    case 2:
                        if (!($documento->gdo_estado_etapa2 == "SIN_GESTION" || $documento->gdo_estado_etapa3 == "APROBACION_NO_CONFORME"))
                            $error = true;
                        break;
                    case 3:
                        if (!($documento->gdo_estado_etapa3 == "SIN_GESTION" || $documento->gdo_estado_etapa4 == "NO_APROBADA_POR_CONTABILIDAD"))
                            $error = true;
                        break;
                    case 4:
                        if (!($documento->gdo_estado_etapa4 == "SIN_GESTION" || $documento->gdo_estado_etapa5 == "NO_APROBADA_POR_IMPUESTOS"))
                            $error = true;
                        break;
                    case 5:
                        if (!($documento->gdo_estado_etapa5 == "SIN_GESTION" || $documento->gdo_estado_etapa6 == "NO_APROBADA_PARA_PAGO"))
                            $error = true;
                        break;
                    case 6:
                        if (!($documento->gdo_estado_etapa6 == "SIN_GESTION"))
                            $arrErrores[] = "El documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe tener estado SIN GESTIÓN";
                        break;
                    default:
                    break;
                }

                if ($error)
                    $arrErrores[] = "El documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe tener estado SIN GESTIÓN o RECHAZADO";
            } else
                $arrErrores[] = "El documento con Id [{$gdoId}] no existe";
        }

        return [
            'errores' => $arrErrores
        ];
    }

    /**
     * Actualiza la información del documento sobre la acción de gestionar.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function actualizarGestionDocumentos(Request $request): array {
        $respuesta = $this->repGestionDocumentoRepository->actualizarGestionDocumentos($request);

        return [
            'errores' => $respuesta
        ];
    }

    /**
     * Realiza las validaciones necesarias para actualizar el documento en gestión.
     * 
     * Este método se utiliza para validar la asignación de Centro de Costo, Centro de Operación y la acción de siguiente etapa
     *
     * @param Request $request Parámetros de la petición
     * @param string  $origen Indica el origen de la petición
     * @return array
     */
    public function validacionesGeneralesGestionDocumentos(Request $request, string $origen = ''): array {
        $arrErrores = [];
        $arrGdoIds  = explode(',', $request->gdo_ids);

        foreach ($arrGdoIds as $gdoId) {
            $documento = $this->repGestionDocumentoRepository->consultarGestionDocumento($gdoId);
            if ($documento) {
                switch ($request->etapa) {
                    case 1:
                        if (!($documento->gdo_estado_etapa1 == "CONFORME" && $documento->gdo_estado_etapa2 != "SIN_GESTION" && $documento->gdo_estado_etapa2 != "REVISION_CONFORME"))
                            $arrErrores[] = "El estado del documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe ser CONFORME";

                        if ($origen == "siguiente-etapa" && $documento->cop_id == '')
                            $arrErrores[] = "Para el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe asignar Centro de Operaciones";
                        break;
                    case 2:
                        if (!($documento->gdo_estado_etapa2 == "REVISION_CONFORME" && $documento->gdo_estado_etapa3 != "SIN_GESTION" && $documento->gdo_estado_etapa3 != "APROBACION_CONFORME"))
                            $arrErrores[] = "El estado del documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe ser REVISION CONFORME";

                        if ($origen == "siguiente-etapa" && $documento->cco_id == '')
                            $arrErrores[] = "Para el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe asignar Centro de Costos";
                        break;
                    case 3:
                        if (!($documento->gdo_estado_etapa3 == "APROBACION_CONFORME" && $documento->gdo_estado_etapa4 != "SIN_GESTION" && $documento->gdo_estado_etapa4 != "APROBADA_POR_CONTABILIDAD"))
                            $arrErrores[] = "El estado del documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe ser APROBACION CONFORME";
                        break;
                    case 4:
                        if (!($documento->gdo_estado_etapa4 == "APROBADA_POR_CONTABILIDAD" && $documento->gdo_estado_etapa5 != "SIN_GESTION" && $documento->gdo_estado_etapa5 != "APROBADA_POR_IMPUESTOS"))
                            $arrErrores[] = "El estado del documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe ser APROBADA POR CONTABILIDAD";

                        if ($origen == "siguiente-etapa" && !isset($documento->gdo_informacion_etapa4))
                            $arrErrores[] = "Para el documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe digitar los datos contabilizado";
                        break;
                    case 5:
                        if (!($documento->gdo_estado_etapa5 == "APROBADA_POR_IMPUESTOS" && $documento->gdo_estado_etapa6 != "SIN_GESTION" && $documento->gdo_estado_etapa6 != "APROBADA_Y_PAGADA"))
                            $arrErrores[] = "El estado del documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe ser APROBADA POR IMPUESTOS";
                        break;
                    case 6:
                        if (!($documento->gdo_estado_etapa6 == "APROBADA_Y_PAGADA" && $documento->gdo_estado_etapa7 != "SIN_GESTION"))
                            $arrErrores[] = "El estado del documento [{$documento->rfa_prefijo}-{$documento->gdo_consecutivo}] debe ser APROBADA Y PAGADA o el documento ya se encuentra en la siguiente etapa";
                        break;
                    default:
                        break;
                }
            } else
                $arrErrores[] = "El documento con Id [{$gdoId}] no existe";
        }

        return [
            'errores' => $arrErrores
        ];
    }

    /**
     * Asigna el centro de operaciones sobre la gestión del documento en la etapa 1.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function asignarCentroOperaciones(Request $request): array {
        $respuesta = $this->repGestionDocumentoRepository->asignarCentroOperaciones($request);

        return [
            'errores' => $respuesta
        ];
    }

    /**
     * Asigna el centro de costos sobre la gestión del documento en la etapa 1.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function asignarCentroCostos(Request $request): array {
        $respuesta = $this->repGestionDocumentoRepository->asignarCentroCostos($request);

        return [
            'errores' => $respuesta
        ];
    }

    /**
     * Actualiza los estados de la gestión del documento para pasar a la siguiente etapa.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function actualizarEstadoSiguienteEtapa(Request $request): array {
        $respuesta = $this->repGestionDocumentoRepository->actualizarEstadoSiguienteEtapa($request);

        return [
            'errores' => $respuesta
        ];
    }

    /**
     * Actualiza los datos contabilizado sobre la gestión del documento en la etapa 4.
     *
     * @param Request $request Parámetros de la petición
     * @return array
     */
    public function actualizarDatosContabilizado(Request $request): array {
        $respuesta = $this->repGestionDocumentoRepository->actualizarDatosContabilizado($request);

        return [
            'errores' => $respuesta
        ];
    }

    /**
     * Obtiene la información sobre el detalle de las etapas.
     *
     * @param int $gdoId Id del documento en gestión
     * @return array
     */
    public function consultarDetalleEtapas(int $gdoId): array {
        $arrInformacionDocumento = [];
        $arrInformacionEtapas    = [];

        $documento = $this->repGestionDocumentoRepository->consultarDetalleGestionDocumento($gdoId);
        if ($documento) {
            // Obtiene la razón social del emisor dependiendo si es un adquirente o proveedor
            $razonSocial = "";
            if ($documento->gdo_modulo == 'EMISION')
                $razonSocial = $documento->getConfiguracionAdquirente->nombre_completo;
            else
                $razonSocial = $documento->getConfiguracionProveedor->nombre_completo;

            $arrInformacionDocumento = [
                'documento'         => $documento->rfa_prefijo . '-' . $documento->gdo_consecutivo,
                'emisor'            => $documento->gdo_identificacion . ' - ' . $razonSocial,
                'fecha_emision'     => $documento->gdo_fecha,
                'fecha_vencimiento' => $documento->gdo_vencimiento,
                'historico'         => json_decode($documento->gdo_historico_etapas, true)
            ];

            for ($i=1; $i <= 6 ; $i++) { 
                $campoEstado = 'gdo_estado_etapa'.$i;

                if ($documento->{$campoEstado} != '') {
                    $estadoGestion  = HelperRepGestionDocumento::obtieneEstadoActualEtapa($documento, $i);
                    $relacionCausal = 'getConfiguracionCausalDevolucionEtapa'.$i;
                    $observacion    = 'gdo_observacion_etapa'.$i;
    
                    $arrInformacionEtapas = [
                        'etapa'             => $i,
                        'nombre_etapa'      => HelperRepGestionDocumento::$arrNombreEtapas[$i],
                        'estado_gestion'    => str_replace('_', ' ', $estadoGestion),
                        'causal_devolucion' => $documento->{$relacionCausal} ? $documento->{$relacionCausal}->cde_descripcion : '',
                        'observacion'       => $documento->{$observacion}
                    ];

                    if ($i == 1)
                        $arrInformacionEtapas = array_merge($arrInformacionEtapas, [
                            'centro_operacion' => $documento->getConfiguracionCentroOperacion ? $documento->getConfiguracionCentroOperacion->cop_descripcion : '',
                        ]);

                    if ($i == 2)
                        $arrInformacionEtapas = array_merge($arrInformacionEtapas, [
                            'centro_costo' => $documento->getConfiguracionCentroCosto ? $documento->getConfiguracionCentroCosto->cco_codigo . ' - ' . $documento->getConfiguracionCentroCosto->cco_descripcion : '',
                        ]);

                    if ($i == 3) {
                        $arrInformacionAdicional = $documento->gdo_informacion_etapa3 != '' ? json_decode($documento->gdo_informacion_etapa3, true) : [];
                        $arrInformacionEtapas = array_merge($arrInformacionEtapas, $arrInformacionAdicional);
                    }

                    if ($i == 4) {
                        $arrInformacionAdicional = $documento->gdo_informacion_etapa4 != '' ? json_decode($documento->gdo_informacion_etapa4, true) : [];
                        $arrInformacionEtapas = array_merge($arrInformacionEtapas, $arrInformacionAdicional);
                    }

                    $arrInformacionDocumento['etapas'][] = $arrInformacionEtapas;
                } 
            }
        }

        return [
            'data' => $arrInformacionDocumento
        ];
    }
}
