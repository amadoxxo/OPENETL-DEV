<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use App\Http\Models\User;

class AuthenticationController extends Controller {
    public function __construct() {
        // Rutas excluidas del middleware
        $this->middleware(
            'jwt.auth',
            ['except' => ['login']]
        );
    }

    /**
     * Función de validación de acceso de usuarios
     * 
     * @param $request Request - Parametros recibidos desde el formulario de login en Angular
     * @return response - json object or token
     */
    public function login (Request $request) {
        
        try {

            // Valida la existencia del email
            $user = User::where('usu_email', $request->email)->first();
            
            if($user) {
                // Valida el estado del usuario
                if($user->estado == 'ACTIVO') {
                    $token = auth()->attempt(["usu_email" => $request->email, "password" => $request->password]);
                    if (!$token) {
                        return response()->json([
                            'message' => 'Error de Autenticación',
                            'errors' => ['Credenciales de acceso no válidas']
                        ], 401);
                    }
                } else {
                    return response()->json([
                        'message' => 'Error de Autenticación',
                        'errors' => ['Su usuario se encuentra inactivo en el sistema']
                    ], 401);
                }
            } else {
                return response()->json([
                    'message' => 'Error de Autenticación',
                    'errors' => ['Credenciales de acceso no válidas']
                ], 401);
            }
        } catch (JWTException $e) {
            // si no se puede crear el token
            return response()->json(
                ['errors' => ['Error al generar el token de autenticación']],
                500
            );
        }
 
        // El token se ha creado de manera correcta y se devuelve el token creado
        return response()->json(
            compact('token')
        );
    }
}
