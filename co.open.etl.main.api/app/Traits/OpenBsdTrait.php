<?php
namespace App\Traits;

use GuzzleHttp\Client;
use openEtl\Tenant\Traits\TenantTrait;

trait OpenBsdTrait {

    /**
     * Autenticación frente a openBSD para obtener un Token.
     *
     * @return Array  Conteniendo el Token o un Error
     */
    public function loginBSD() {
        // Se obtiene el valor de las variables del sistema para la conexión con openBSD
        $urlApi      = config('variables_sistema.BSD_API');
        $usuarioApi  = config('variables_sistema.BSD_USUARIO_API');
        $passwordApi = config('variables_sistema.BSD_PASSWORD_API');

        if (!empty($urlApi) && !empty($usuarioApi) && !empty($passwordApi)) {
            // Cliente Guzzle
            $bsdApi = new Client([
                'base_uri' => $urlApi,
                'headers' => [
                    'Content-Type'      => 'application/x-www-form-urlencoded',
                    'X-Requested-With'  => 'XMLHttpRequest',
                    'Accept'            => 'application/json'
                ]
            ]);

            try {
                $login = $bsdApi->request(
                    'POST',
                    '/api/login',
                    [
                        'form_params' => [
                            'email'     => $usuarioApi,
                            'password'  => $passwordApi
                        ]
                    ]
                );

                $responseLogin = json_decode((string)$login->getBody()->getContents());

                return [
                    'errors'  => '',
                    'token'   => $responseLogin->token,
                ];

            } catch (\Exception $e) {
                $error = $e->getMessage();
                return [
                    'errors' => $e->getMessage(),
                    'token'  => '',
                ];
            }
        } else {
            return [
                'errors' => 'Datos API BSD no parametrizados.',
                'token'  => '',
            ];
        }
    }

    /**
     * Construye un cliente de Guzzle para consumir los end-point de openBSD.
     *
     * @param string $URI
     * @return Client
     */
    private function getCliente(string $URI, $contentType) {
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
     * Realiza una petición a openBSD.
     * 
     * Tener en cuenta que el retorno del método puede ser procesado para obtener diferentes valores retornados, ejemplo:
     * $status    = $peticion->getStatusCode();
     * $razon     = $peticion->getReasonPhrase();
     * $newToken  = $peticion->getHeader('Authorization');
     * $newToken  = str_replace('Bearer ', '', $newToken[0]);
     * $respuesta = json_decode((string)$peticion->getBody()->getContents(), true);
     *
     * @param string $uri Base URI de conexión con openBSD
     * @param string $metodoHttp Método HTTP para la petición
     * @param string $endpoint Endpoint en donde se realizará la petición
     * @param string $token Token de autenticación en openBSD
     * @param string $tipoParams Tipo de parametros a enviar (json, form_params, multipart)
     * @param array $parametros Array parametros a enviar
     * @return object Objeto resultado del procesamiento
     */
    private function peticionBSD($uri, $metodoHttp, $endpoint, $token, $tipoParams = null, $parametros = null) {

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
     * Centraliza las peticiones que se realizan a OpenBSD, desde los end-point de openETL.
     * 
     * Se realiza el login hacia openBSD para poder obtener el token y enviarselo en la petición hacia los end-point de openBSD.
     * Retorna la respuesta que se obtiene al hacer la petición hacia los endpoint de openBSD.
     * En caso de que se genere un error, se retorna un array con el mensaje del error y el detalle del error.
     * 
     * @param string $uri Base URI de conexión con openBSD
     * @param string $endPoint Endpoint en donde se realizará la petición a openBSD
     * @param string $metodoHttp Método HTTP para la petición
     * @param string $tipoParams Tipo de parámetros a enviar (json, form_params, multipart)
     * @param array $parametros Array parámetros a enviar
     * @param array $queryParams Array queryparams en caso de petición GET
     * @return object Objeto resultado del procesamiento
     */
    public function enviarPeticionBSD($uri, $endpoint, $token, $metodoHttp, $tipoParams = null, $parametros = null, $queryParams = array()) {
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

        $peticionBSD = $this->peticionBSD(
            $uri, $metodoHttp, $endpoint . $queryParamsString, $token, $tipoParams, $parametros
        );

        $status    = $peticionBSD->getStatusCode();
        $respuesta = json_decode((string)$peticionBSD->getBody()->getContents(), true);

        if($status != '200' && $status != '201') {
            return [
                'message' => $respuesta['message'],
                'errors'  => isset($respuesta['errors']) ? $respuesta['errors'] : '',
                'status'  => $status
            ];
        }

        // Se retorna el token refresh

        $newToken = $peticionBSD->getHeader('Authorization');
        $newToken = str_replace('Bearer ', '', $newToken[0]);

        $respuesta['status'] = $status;
        $respuesta['token']  = $newToken;

        return $respuesta;
    }
}