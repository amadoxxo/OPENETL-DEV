<?php

namespace App\Http\Controllers\Auth;

use Mail;
use App\Http\Models\User;
use App\Traits\MainTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash; // Facade = Patron de diseño de software

class PasswordController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
     */
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        //$this->middleware('guest');
        $this->middleware('guest', ['only' => ['email']]);
        $this->middleware(['jwt.auth', 'jwt.refresh'], ['only' => ['change']]);
    }

    /**
     * Send an email with a new passwod to the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function email(Request $request) {
        // Valida el formato del email recibido
        $this->validate(
            $request, [
                'email' => 'required|email|max:255'
            ]
        );
        
        // Email existe asignado a un usuario?
        $user = User::where(
            'usu_email',
            $request->email
        )->first();

        if (!$user) {
            return response()->json(
                ['errors' => ['El email no existe en el sistema']],
                401
            );
        }
        
        // Genera la nueva clave de acceso
        $password=str_random(10);
        
        // Guarda la nueva clave
        $cambiaClave = $user->fill([
            'usu_password' => Hash::make($password)
        ])->save();
        
        // Respuesta
        if ($request->wantsJson()) {
            if ($cambiaClave) {
                // Envía el email
                $data=[
                    "usu_email" => $request->email,
                    "user" => $user,
                    "clave" => $password,
                    "remite" => config('variables_sistema.EMPRESA'),
                    "direccion" => config('variables_sistema.DIRECCION'),
                    "ciudad" => config('variables_sistema.CIUDAD'),
                    "telefono" => config('variables_sistema.TELEFONO'),
                    "app_url" => config('variables_sistema.APP_URL_WEB'),
                    "web" => config('variables_sistema.WEB'),
                    "email" => config('variables_sistema.EMAIL'),
                    "facebook" => config('variables_sistema.FACEBOOK'),
                    "twitter" => config('variables_sistema.TWITTER'),
                    "reminder" => 'Usted ha recibido este mensaje porque realizó el proceso de reestablecimiento de contraseña',
                ];
                MainTrait::setMailInfo();
                Mail::send(
                    'emails.forgotPassword',
                    $data,
                    function ($message) use ($user){
                        $message->from(config('variables_sistema.EMAIL'), env('APP_NAME'));
                        $message->sender(config('variables_sistema.EMAIL'), env('APP_NAME'));
                        $message->subject('Clave de acceso a openETL');
                        $message->to($user->usu_email, $user->usu_nombre);
                    }
                );
                return response()->json(
                    ['success' => 'clave_cambiada'],
                    200
                );
            } else {
                return response()->json(
                    ['error' => 'clave_no_cambiada'],
                    500
                );
            }
        } else {
          return response()->json(
              ['error' => 'encabezado_de_peticion_no_validos'],
              400
            );
        }
    }

    /**
     * Change the given user's password.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function change(Request $request) {

        $user = auth()->user();
               
        // Checks that the given plain-text string 'claveActual' corresponds to the given hashed password
        // Valida que el string 'claveActual' corresponde con el hash del password
        if (Hash::check($request->claveActual, $user->usu_password)) {
            // Cambia la clave por la nueva
            $user->fill([
                'usu_password' => Hash::make($request->claveNueva)
            ])->save();
            
            return response()->json(
                ['success' => 'clave_cambiada'],
                200
            );
        } else {
            return response()->json(
                ['error' => 'credenciales_no_validas'],
                401
            );
        }
    }
}
