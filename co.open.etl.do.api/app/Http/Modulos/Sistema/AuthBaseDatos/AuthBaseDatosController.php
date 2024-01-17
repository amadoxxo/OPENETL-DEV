<?php

namespace App\Http\Modulos\Sistema\AuthBaseDatos;

use JWTAuth;

use App\Http\Controllers\Controller;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;

class AuthBaseDatosController extends Controller {
    
    /**
     * Middlewares que serán utilizados en el controlador
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Obtiene la lista de Base datos disponibles
     * 
     */
    public function getListaBaseDatos() {
        $user = auth()->user();

        return response()->json([
            'data' => AuthBaseDatos::where('bdd_id', $user->bdd_id)->get()
        ], 200);
    }
}
