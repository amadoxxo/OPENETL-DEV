<?php
namespace App\Traits;

use GuzzleHttp\Client;
use openEtl\Tenant\Traits\TenantTrait;
use GuzzleHttp\Exception\RequestException;

trait OpenComexTrait {
    /**
     * Realiza una petición a openComex.
     * 
     * Tener en cuenta que el retorno del método puede ser procesado para obtener diferentes valores retornados, ejemplo:
     *   $status    = $peticion->getStatusCode();
     *   $razon     = $peticion->getReasonPhrase();
     *   $respuesta = json_decode((string)$peticion->getBody()->getContents(), true);
     *
     * @param string $metodoHttp Método HTTP para la petición
     * @param string $endpoint Endpoint en donde se realizará la petición
     * @param string $tipoParams Tipo de parametros a enviar (json, form_params, multipart)
     * @param array $parametros Array parametros a enviar
     * @return object Objeto resultado del procesamiento
     */
    public function peticionOpenComex($metodoHttp, $endpoint, $tipoParams = '', $parametros = []) {

        $arrParams = [
            'verify' => false,
            'headers' => [
               'Content-Type' => 'application/json'
            ],
        ];

        switch($tipoParams){
            case('json'):
                $arrParams['json'] = $parametros;
            break;
            case('form_params'):
                $arrParams['form_params'] = $parametros;
            break;
            case('multipart'):
                $arrParams['multipart'] = $parametros;
            break;
        }

        // Inicializa un cliente de Guzzle para permitir conexión con openComex
        $cliente = new Client();

        return  $cliente->request(
            $metodoHttp,
            $endpoint,
            $arrParams
        );
    }

    /**
     * Centraliza las peticiones que se realizan a OpenComex desde openETL.
     * 
     * Retorna la respuesta que se obtiene al hacer la petición hacia los endpoint de openComex.
     * En caso de que se genere un error, se retorna un array con el mensaje del error y el detalle del error.
     * 
     * @param string $endPoint Endpoint en donde se realizará la petición a openComex
     * @param string $metodoHttp Método HTTP para la petición
     * @param string $tipoParams Tipo de parámetros a enviar (json, form_params, multipart)
     * @param array $parametros Array parámetros a enviar
     * @param array $queryParams Array queryparams en caso de petición GET
     * @return object Objeto resultado del procesamiento
     */
    public function enviarPeticionOpenComex($endpoint, $metodoHttp, $tipoParams = null, $parametros = null, $queryParams = array()) {
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

        try {
            $peticionOpenComex = $this->peticionOpenComex(
                $metodoHttp, $endpoint . $queryParamsString, $tipoParams, $parametros
            );

            $status    = $peticionOpenComex->getStatusCode();
            $respuesta = json_decode((string)$peticionOpenComex->getBody()->getContents(), true);

            if (empty($respuesta) || (!empty($respuesta) && !array_key_exists('status', $respuesta))) {
              $respuesta['status'] = $status;
            }

            if($status != '200' && $status != '201') {
                return [
                    'message' => $respuesta['message'],
                    'errors'  => $respuesta['errors'],
                    'status'  => $status
                ];
            }
        } catch (RequestException $e) {
            $status       = $e->getCode();
            $respuesta    = $e->getResponse();
            $arrRespuesta = json_decode($respuesta->getBody()->getContents(), true);

            return [
                'message' => 'Error al realizar la petición',
                'errors'  => $arrRespuesta['errors'],
                'status'  => $status
            ];
        }

        return $respuesta;
    }
}