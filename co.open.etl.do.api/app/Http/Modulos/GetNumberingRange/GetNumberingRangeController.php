<?php
namespace App\Http\Modulos\GetNumberingRange;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Modulos\Recepcion\GetStatus;
use Illuminate\Http\Response as HttpResponseCodes;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\TransmitirDocumentosDian\FirmarEnviarSoap;
use App\Http\Modulos\TransmitirDocumentosDian\TransmitirDocumentosDian;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class GetNumberingRangeController extends Controller {
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware([
            'VerificaMetodosRol:ConfiguracionResolucionesFacturacionConsultar'
        ])->except([
            'consultarResolucionFacturacionDian'
        ]);
    }

    /**
     * Consulta en la DIAN las resoluciones de facturación de un OFE.
     *
     * @param Request $request Petición
     * @return JsonResponse
     */
    public function consultarResolucionFacturacionDian(Request $request): JsonResponse {
        if(!$request->filled('ofe_identificacion'))
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['No existe la identificación del OFE en la petición']
            ], HttpResponseCodes::HTTP_BAD_REQUEST);

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'sft_id', 'bdd_id_rg', 'ofe_archivo_certificado', 'ofe_password_certificado'])
            ->where('ofe_identificacion', $request->ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->where('estado', 'ACTIVO')
            ->with([
                'getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_identificador,sft_nit_proveedor_tecnologico'
            ])
            ->first();

        if(!$ofe)
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['El OFE con identificación [' . $request->ofe_identificacion . '] no existe o se encuentra inactivo']
            ], HttpResponseCodes::HTTP_BAD_REQUEST);

        try {
            $classGetStatus   = new GetStatus();
            $classMetodosBase = new MetodosBase();
            $classFirmarSoap  = new FirmarEnviarSoap();
            $classTransmitir  = new TransmitirDocumentosDian();

            // Array de objetos de paramétricas
            $parametricas = [
                'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_metodo', 'add_descripcion', 'add_url', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                    ->where('estado', 'ACTIVO')
                    ->get()->toArray()
            ];

            $urlAmbienteDestino = $classMetodosBase->obtieneDatoParametrico(
                $parametricas['ambienteDestino'],
                'add_id',
                $ofe->getConfiguracionSoftwareProveedorTecnologico->add_id,
                'add_url'
            );

            if(empty($ofe->ofe_archivo_certificado) || empty($ofe->ofe_password_certificado) || empty($ofe->getConfiguracionSoftwareProveedorTecnologico) || empty($ofe->getConfiguracionSoftwareProveedorTecnologico->sft_nit_proveedor_tecnologico) || empty($ofe->getConfiguracionSoftwareProveedorTecnologico->sft_identificador))
                return response()->json([
                    'message' => 'Error al consultar las resoluciones',
                    'errors'  => ['Verifique la configuración del OFE a nivel de certificado de firma y/o Software del Proveedor Tecnológico']
                ], HttpResponseCodes::HTTP_BAD_REQUEST);

            $obtenerResoluciones = $classGetStatus->requestDianWs(
                $classTransmitir,
                $classFirmarSoap,
                'GetNumberingRange',
                $urlAmbienteDestino,
                $ofe->ofe_identificacion . ',' . $ofe->getConfiguracionSoftwareProveedorTecnologico->sft_nit_proveedor_tecnologico . ',' . $ofe->getConfiguracionSoftwareProveedorTecnologico->sft_identificador,
                $classGetStatus->getNombreBaseDatos($ofe),
                $ofe->ofe_archivo_certificado,
                $ofe->ofe_password_certificado
            );

            if(array_key_exists('error', $obtenerResoluciones) && !empty($obtenerResoluciones['error']))
                return response()->json([
                    'message' => 'Error al consultar las resoluciones',
                    'errors'  => [$obtenerResoluciones['error']]
                ], HttpResponseCodes::HTTP_BAD_REQUEST);
            else {
                $infoResoluciones = $this->extraerResoluciones($obtenerResoluciones['rptaDian']);

                return response()->json(
                    $infoResoluciones['data']
                , $infoResoluciones['code']);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al consultar las resoluciones',
                'errors'  => [$th->getMessage()]
            ], HttpResponseCodes::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Extrae la información de resoluciones de una consulta a la DIAN.
     *
     * @param string $rptaDian Respuesta de la DIAN a la consulta de resoluciones
     * @return array
     */
    private function extraerResoluciones(string $rptaDian): array {
        libxml_use_internal_errors(true);

        $oXML         = new \SimpleXMLElement($rptaDian);
        $vNameSpaces  = $oXML->getNamespaces(true);
        $nodoResponse = 'GetNumberingRangeResponse';
        $nodoResult   = 'GetNumberingRangeResult';

        $oBody = $oXML->children($vNameSpaces['s'])
            ->Body
            ->children($vNameSpaces[''])
            ->$nodoResponse
            ->children($vNameSpaces[''])
            ->$nodoResult
            ->children($vNameSpaces['b']);

        if((string) $oBody->OperationCode != '100') {
            return [
                'code'    => HttpResponseCodes::HTTP_BAD_REQUEST,
                'data'    => [
                    'message' => 'Error al consultar las resoluciones',
                    'errors'  => [(string) $oBody->OperationDescription]
                ]
            ];
        } else {
            $responseList        = (array) $oBody->ResponseList->children($vNameSpaces['c']);
            $numberRangeResponse = is_array($responseList['NumberRangeResponse']) ? $responseList['NumberRangeResponse'] : [$responseList['NumberRangeResponse']];
            $dataResoluciones    = [];

            foreach($numberRangeResponse as $resolucion)
                $dataResoluciones[] = [
                    'prefijo'    => (string) $resolucion->Prefix,
                    'resolucion' => (string) $resolucion->ResolutionNumber,
                    'consecutivo_inicial' => (string) $resolucion->FromNumber,
                    'consecutivo_final' => (string) $resolucion->ToNumber,
                    'fecha_desde' => (string) $resolucion->ValidDateFrom,
                    'fecha_hasta' => (string) $resolucion->ValidDateTo,
                    'clave_tecnica' => (string) $resolucion->TechnicalKey
                ];

            return [
                'code' => HttpResponseCodes::HTTP_OK,
                'data' => ['data' => base64_encode(json_encode($dataResoluciones))]
            ];
        }

        throw new \Exception('No fue posible extraer la información de las resoluciones de facturación desde la respuesta a la consulta en la DIAN');
    }
}
