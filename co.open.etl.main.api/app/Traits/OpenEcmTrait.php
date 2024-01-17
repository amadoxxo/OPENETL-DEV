<?php
namespace App\Traits;

use GuzzleHttp\Client;
use openEtl\Tenant\Traits\TenantTrait;

use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

trait OpenEcmTrait {

    /**
     * Autenticación frente a openECM para obtener un Token.
     *
     * @param string $ofe_identificacion Identificación del Oferente seleccionado
     * @return Array  Conteniendo el Token o un Error
     */
    public function loginECM($ofe_identificacion) {

        /**
         * Retorna un array:
         * 
         * 'url_api'    => 'http://devops.open-eb.io:49154/81/api'
         * 'bdd_id_ecm' => '9'
         */
        $conexion = $this->obtieneConexionOfe($ofe_identificacion);

        $endPointEcm = $this->endPointEcm('endpoint_login_roles');

        // Cliente Guzzle
        $ecmApi = new Client([
            'base_uri' => $conexion['url_api'],
            'headers' => [
                'Content-Type'      => 'application/x-www-form-urlencoded',
                'X-Requested-With'  => 'XMLHttpRequest',
                'Accept'            => 'application/json'
            ]
        ]);

        try {
            $login = $ecmApi->request(
                'GET',
                $conexion['url_api'] . $endPointEcm . '/'. $conexion['bdd_id_ecm']
            );

            $responseHeader = $login->getHeaderLine('Authorization');
            $token = explode(' ', $responseHeader);

            $responseLogin = json_decode((string)$login->getBody()->getContents());

            return [
                'errors'  => '',
                'token'   => $token[1],
                'url_api' => $conexion['url_api']
            ];

        } catch (\Exception $e) {
            $error = $e->getMessage();
            return [
                'errors' => $e->getMessage(),
                'token'  => '',
                'url_api' => ''
            ];
        }
    }

    /**
     * Construye un cliente de Guzzle para consumir los endpoint de openECM.
     *
     * @param string $URI
     * @return Client
     */
    public function getCliente(string $URI, $contentType) {
        return new Client([
            'base_uri' => $URI,
            'headers' => [
                'Content-Type'     => $contentType,
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept'           => 'application/json'
            ]
        ]);
    }

    /**
     * Realiza una petición a openECM.
     * 
     * Tener en cuenta que el retorno del método puede ser procesado para obtener diferentes valores retornados, ejemplo:
     * $status    = $peticion->getStatusCode();
     * $razon     = $peticion->getReasonPhrase();
     * $newToken  = $peticion->getHeader('Authorization');
     * $newToken  = str_replace('Bearer ', '', $newToken[0]);
     * $respuesta = json_decode((string)$peticion->getBody()->getContents(), true);
     *
     * @param string $uri Base URI de conexión con openECM
     * @param string $metodoHttp Método HTTP para la petición
     * @param string $endpoint Endpoint en donde se realizará la petición
     * @param string $token Token de autenticación en openECM
     * @param string $tipoParams Tipo de parametros a enviar (json, form_params, multipart)
     * @param array $parametros Array parametros a enviar
     * @return object Objeto resultado del procesamiento
     */
    public function peticionECM($uri, $metodoHttp, $endpoint, $token, $tipoParams = null, $parametros = null) {

        $arrParams = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'http_errors' => false
        ];

        $contentType = '';

        switch($tipoParams){
            case('json'):
                $arrParams['json'] = $parametros;
            break;
            case('form_params'):
                $arrParams['form_params'] = $parametros;
            break;
            case('multipart'):
                $arrParams['multipart'] = $parametros;
                $contentType = 'multipart/form-data';
            break;
        }

        return $this->getCliente($uri, $contentType)
            ->request(
                $metodoHttp,
                $endpoint,
                $arrParams
            );
    }

    /**
     * Centraliza las peticiones que se realizan a OpenECM, desde los end-point de openETL.
     * 
     * Se realizá el login hacia openECM para poder obtener el token y enviarselo en la petición hacia los end-point de openECM.
     * Retorna la respuesta que se obtiene al hacer la petición hacia los endpoint de openECM.
     * En caso de que se genere un error, se retorna un array con el mensaje del error y el detalle del error.
     * 
     * @param string $uri Base URI de conexión con openECM
     * @param string $endPoint Endpoint en donde se realizará la petición a openECM
     * @param string $metodoHttp Método HTTP para la petición
     * @param string $tipoParams Tipo de parámetros a enviar (json, form_params, multipart)
     * @param array $parametros Array parámetros a enviar
     * @param array $queryParams Array queryparams en caso de petición GET
     * @return object Objeto resultado del procesamiento
     */
    public function enviarPeticionECM($uri, $endpoint, $token, $metodoHttp, $tipoParams = null, $parametros = null, $queryParams = array()){        

        // Valido los $queryParams
        $queryParamsString = '';
        if(!empty($queryParams)){
            $tieneLlaves = false;
            // Valido si el arreglo tiene llaves distintas a números
            foreach($queryParams as $key => $value){
                if(!is_numeric($key)){
                    // El array tienen los nombres de los campos
                    $tieneLlaves = true;
                break;
                }
            }
            // Si tiene llaves, concateno los parametros con & y =
            if($tieneLlaves){
                $first = true;
                $queryParamsString = '?';
                foreach($queryParams as $key => $value){
                    if($first){
                        $first = false;
                        $queryParamsString .= $key . '=' . $value;
                    }else{
                        $queryParamsString .= '&' . $key . '=' . $value;
                    }
                }
            // Si no tiene llaves, cancatenos los parametros con /
            }else{
                foreach($queryParams as $value){
                    $queryParamsString .= '/' . $value;
                }
            }
        }

        $peticionECM = $this->peticionECM(
            $uri, $metodoHttp, $endpoint . $queryParamsString, $token, $tipoParams, $parametros
        );

        $status    = $peticionECM->getStatusCode();
        $respuesta = json_decode((string)$peticionECM->getBody()->getContents(), true);

        if($status != '200' && $status != '201') {
            return [
                'message' => $respuesta['message'],
                'errors'  => $respuesta['errors'],
                'status'  => $status
            ];
        }

        $respuesta['status']  = $status;

        return $respuesta;
    }

    /**
     * Consulta la información de conexioón a openECM teniendo en cuenta la identificación del OFE.
     * 
     * @param string $ofe_identificacion Identificación del Oferente
     * @return array Array resultado del procesamiento
     */
    public function obtieneConexionOfe($ofe_identificacion) {
        $dataConexion = [];

        //Se obtiene la información del Oferente para la conexión con openECM
        $ofe = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_integracion_ecm_conexion'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();
        
        if ($ofe) {
           $ecmConexion = json_decode($ofe->ofe_integracion_ecm_conexion, true);
           $dataConexion['url_api']    = $ecmConexion['url_api'];
           $dataConexion['bdd_id_ecm'] = $ecmConexion['bdd_id_ecm'];
        }

        if (!empty($dataConexion)) {
            return $dataConexion;
        }

        return [
            'message' => 'Error de parámetros de conexión',
            'errors'  => ['No fue posible obtener los parámetros de conexión para el OFE [ ' . $ofe_id . ']']
        ];        
    }

    /**
     * Obtiene la ruta del endPoint de openECM a consumir.
     * 
     * @param string $nombreEndPoint Nombre del endPoint
     * @return string String resultado del procesamiento
     */
    public function endPointEcm($nombreEndPoint) {
        TenantTrait::GetVariablesSistemaTenant();
        $integracionEcmAuth = config('variables_sistema_tenant.INTEGRACION_ECM_AUTH');
        $endPoints = json_decode($integracionEcmAuth, true);
        
        return $endPoints[$nombreEndPoint];
    }
}