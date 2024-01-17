<?php
namespace App\Http\Modulos\Radian;

use Validator;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Http\Response as ResponseHttp;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\TransmitirDocumentosDian\FirmarEnviarSoap;
use App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActor;
use App\Http\Modulos\TransmitirDocumentosDian\TransmitirDocumentosDian;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;

class GetStatus extends Controller {
    use TenantTrait, FechaVigenciaValidations, DoTrait;
    
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Realiza una consulta única a los web services de la DIAN.
     *
     * @param TransmitirDocumentosDian $classTransmitir Clase que contiene métodos relacionados con la transmisión/consulta de información en la DIAN
     * @param FirmarEnviarSoap $firmarSoap Clase que permite firmar electrónicamente un SOAP y transmitirlo a la DIAN
     * @param string $metodoCsonulta Metodo de la DIAN a través del cual se realiza la consulta
     * @param string $urlAmbienteDestino Ambiente de destino
     * @param string $cufe Cufe del documento electrónico
     * @param string $bddNombre Nombre de la base de datos del ACTOR
     * @param string $actArchivoCertificado Path al certificado firmante del ACTOR
     * @param string $actPasswordCertificado Password del certificado firmante del ACTOR
     * @return array Array conteniendo información sobre errores generados en el proceso y la respuesta de la DIAN
     */
    public function requestDianWS(TransmitirDocumentosDian $classTransmitir, FirmarEnviarSoap $firmarSoap, string $metodoConsulta, string $urlAmbienteDestino, string $cufe, string $bddNombre, string $actArchivoCertificado, string $actPasswordCertificado) {
        $soapConsultar = $classTransmitir->inicializarSoapDian($metodoConsulta, $urlAmbienteDestino, $cufe);
        return $firmarSoap->firmarSoapXML(
            $soapConsultar,
            config('variables_sistema.PATH_CERTIFICADOS') . '/' . $bddNombre . '/' . $actArchivoCertificado,
            $actPasswordCertificado,
            $urlAmbienteDestino
        );
    }

    /**
     * Obtiene el nombre de la base de datos del proceso teniendo en cuenta el usuario autenticado y la configuración del ACTOR
     *
     * @param RadianActor $actor Instancia del ACTOR
     * @return string Nombre de la base de datos relacionada con el proceso
     */
    private function getNombreBaseDatos(RadianActor $actor): string {
        $user   = auth()->user();
        $bdUser = $user->getBaseDatos->bdd_nombre;
        if(!empty($user->bdd_id_rg))
            $bdUser = $user->getBaseDatosRg->bdd_nombre;

        if(!empty($actor->bdd_id_rg))
            $bddNombre = $actor->getBaseDatosRg->bdd_nombre;
        else
            $bddNombre = $bdUser;

        return str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);
    }

    /**
     * Procesa una petición originada en el microservicio DI - proceso RADIAN para consultar un documento en la DIAN mediante el CUFE
     *
     * @param Request $request Parámetros de la petición
     * @return JsonResponse
     */
    public function procesarPeticionProcesoRadian(Request $request): JsonResponse {
        try {
            $validacion = Validator::make($request->all(), [
                    'act_id'               => 'required|numeric',
                    'cufe'                 => 'required|string',
                    'metodo_consulta_dian' => 'required|string'
                ]);

            if($validacion->fails())
                return response()->json([
                    'message' => 'Errores de validación en los parámetros de la petición',
                    'errors'  => $validacion->errors()->all()
                ], ResponseHttp::HTTP_BAD_REQUEST);

            $actor = RadianActor::select(['act_id', 'bdd_id_rg', 'sft_id', 'act_archivo_certificado', 'act_password_certificado'])
                ->with([
                    'getBaseDatosRg:bdd_id,bdd_nombre',
                    'getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id'
                ])
                ->where('act_id', $request->act_id)
                ->validarAsociacionBaseDatos()
                ->where('estado', 'ACTIVO')
                ->first();
                
            if(!$actor)
                return response()->json([
                    'message' => 'Errores de validación en los parámetros de la petición',
                    'errors'  => ['El actor con id [' . $request->act_id . '] no existe, o se encuentra inactivo']
                ], ResponseHttp::HTTP_BAD_REQUEST);

            $classMetodosBase = new MetodosBase();
            $classTransmitir  = new TransmitirDocumentosDian();
            $classFirmarSoap  = new FirmarEnviarSoap();

            // Array de objetos de paramétricas
            $parametricas = [
                'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_metodo', 'add_descripcion', 'add_url', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                    ->where('estado', 'ACTIVO')
                    ->get()->toArray()
            ];

            $urlAmbienteDestino = $classMetodosBase->obtieneDatoParametrico(
                $parametricas['ambienteDestino'],
                'add_id',
                $actor->getConfiguracionSoftwareProveedorTecnologico->add_id,
                'add_url'
            );

            $obtenerXmlDocumento = $this->requestDianWS(
                $classTransmitir,
                $classFirmarSoap,
                $request->metodo_consulta_dian,
                $urlAmbienteDestino,
                $request->cufe,
                $this->getNombreBaseDatos($actor),
                $actor->act_archivo_certificado,
                $actor->act_password_certificado
            );

            $xmlString = $this->obtenerXmlBytesBase64($obtenerXmlDocumento['rptaDian'], $request->metodo_consulta_dian);

            if (!$xmlString['error'] && !empty($xmlString['string']))
                return response()->json([
                    'data' => [
                        'xml' => $xmlString['string']
                    ]
                ], ResponseHttp::HTTP_OK);
            elseif($xmlString['error'] && !empty($xmlString['string']))
                return response()->json([
                    'message' => 'Error al procesar la petición',
                    'errors'  => [(string) $xmlString['string']]
                ], ResponseHttp::HTTP_NOT_FOUND);
            else
                return response()->json([
                    'message' => 'Error al procesar la petición',
                    'errors'  => ['No fue posible consultar el documento en la DIAN']
                ], ResponseHttp::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al procesar la petición',
                'errors'  => ['DO - GetStatus (Línea ' . $e->getLine() . '): ' . $e->getMessage()]
            ], ResponseHttp::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}