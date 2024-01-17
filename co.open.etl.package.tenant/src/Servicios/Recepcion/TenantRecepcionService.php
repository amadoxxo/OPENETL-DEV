<?php

namespace openEtl\Tenant\Servicios\Recepcion;

use GuzzleHttp\Client;

/**
 * TenantRecepcionService.
 */
class TenantRecepcionService {
    /**
     * Valida contenido de un archivo PDF con una expresión regular para verificar si el contenido
     * es válido y el archivo se puede abrir.
     *
     * @param  string $fileContent Contenido del archivo PDF
     * @return boolean
     */
    public function validatePdfContent(string $fileContent) {
        $utfContenido = mb_convert_encoding($fileContent, 'UTF-8', 'UTF-8');

        return !preg_match("/^%PDF-/", $utfContenido) ? true : false;
    }

    /**
     * Realiza una petición al microservicio DO para la generación de la representación gráfica de Recepción.
     * 
     * @param array $parametros Array de parámetros
     * @return array
     * @throws \Exception
     */
    public function generarRgRecepcion(array $parametros) {
        try {
            $cabecera    = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getToken(),
                    'Content-Type' => 'application/json'
                ],
                'body' => $parametros[0],
                'query' => [
                    'return-type' => 'pdf'
                ]
            ];
            // Accede al microservicio DO para procesar la petición
            $peticionDo = $this->getCliente(config('variables_sistema.APP_URL'))
                ->request(
                    'POST',
                    config('variables_sistema.DO_API_URL') . '/api/pdf/recepcion/generar-representacion-grafica',
                    $cabecera
                );

            return[
                'respuesta' => base64_encode($peticionDo->getBody()->getContents()),
                'error'     => false
            ];
        } catch (\Exception $e) {
            return[
                'respuesta' => $e->getMessage(),
                'error'     => true
            ];
        }
    }

    /**
     * Obtiene un token para efectuar una peticion a un microservicio.
     *
     * @return string
     */
    public function getToken() {
        $user = auth()->user();
        if ($user)
            return auth()->tokenById($user->usu_id);
        return '';
    }

    /**
     * Construye un cliente de Guzzle para consumir los microservicios.
     *
     * @param string $URI
     * @return Client
     */
    public function getCliente(string $URI) {
        return new Client([
            'base_uri' => $URI,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json'
            ]
        ]);
    }
}
