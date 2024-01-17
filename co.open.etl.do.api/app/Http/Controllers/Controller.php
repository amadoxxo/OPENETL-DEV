<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController {
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

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
     * Construye un cliente de Guzzle para consumir los microservicios
     *
     * @param string $URI
     * @return Client
     */
    private function getCliente(string $URI) {
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
