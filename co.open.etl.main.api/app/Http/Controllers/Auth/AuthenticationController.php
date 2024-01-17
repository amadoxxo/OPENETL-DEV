<?php

namespace App\Http\Controllers\Auth;

use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Sistema\VariablesSistema\VariableSistema;

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
     * @return \Illuminate\Http\Response TOKEN 
     */
    public function login (Request $request) {
        // Valida la existencia del email
        $user = User::where('usu_email', $request->email)->first();
        if($user) {
            $baseDatos = AuthBaseDatos::find($user->bdd_id);
            if (!isset($baseDatos->estado)) {
                return response()->json([
                    'message' => 'Error de Autenticación',
                    'errors' => ['Su usuario no se encuentra activo en el sistema']
                ], 401);
            }

            // Valida el estado del usuario
            if($user->estado == 'ACTIVO' && $user->usu_type != 'INTEGRACION') {
                // Se debe verificar si los ofes de la BD del usuario estan configurados para emisión, recepción, openEcm y nómina electrónica
                $servicios = $this->verificarOfes($user);

                $gruposTrabajo           = $this->obtenerVariableSistema($user, 'NOMBRE_GRUPOS_TRABAJO');
                $notificaGrupoTrabajo    = $this->obtenerVariableSistema($user, 'NOTIFICAR_ASIGNACION_GRUPO_TRABAJO');
                $sagrilaftMensaje        = $this->obtenerVariableSistema($user, 'SAGRILAFT_MENSAJE');
                $sagrilaftActivarMensaje = $this->obtenerVariableSistema($user, 'SAGRILAFT_ACTIVAR_MENSAJE');
                $oferentes               = $this->usuarioAuthOfes($user);

                // Versión del sistema
                $version = VariableSistema::select('vsi_valor')
                    ->where('vsi_nombre', 'VERSION')
                    ->first();

                // si los datos de login no son correctos
                if (!$token = auth()->claims([
                    'ofe_emision'              => $servicios['emision'],
                    'ofe_recepcion'            => $servicios['recepcion'],
                    'ofe_recepcion_fnc'        => $servicios['recepcion_fnc'],
                    'ofe_documento_soporte'    => $servicios['documento_soporte'],
                    'ecm'                      => $servicios['ecm'],
                    'nomina'                   => $servicios['nomina'],
                    'version_sistema'          => $version->vsi_valor,
                    'grupos_trabajo'           => $gruposTrabajo,
                    'notificar_grupos_trabajo' => $notificaGrupoTrabajo,
                    'sagrilaft_mensaje'        => $sagrilaftMensaje,
                    'sagrilaft_activar_mensaje'=> $sagrilaftActivarMensaje,
                    'oferentes'                => $oferentes
                ])->attempt(["usu_email" => $request->email, "password" => $request->password])) {
                    return response()->json([
                        'message' => 'Error de Autenticación',
                        'errors' => ['Credenciales de acceso no válidas']
                    ], 401);
                }

                $acl = base64_encode(json_encode([
                    'roles' => $user->usuarioRoles()->filter(function($rol){
                        return $rol->estado === 'ACTIVO';
                    })->map(function ($rol) {
                        return collect($rol->toArray())
                            ->only(['rol_codigo']);
                    })
                        ->values()
                        ->pluck('rol_codigo'),
                    'permisos' => $user->usuarioPermisos()->map(function ($rol) {
                        return collect($rol->toArray())
                            ->only(['rec_alias']);
                    })->flatten()
                        ->unique()
                        ->values()
                ]));

            } else {
                return response()->json([
                    'message' => 'Error de Autenticación',
                    'errors' => ['Su usuario se encuentra inactivo en el sistema o no cuenta con los suficientes permisos de acceso']
                ], 401);
            }
        }
        else {
            return response()->json([
                'message' => 'Error de Autenticación',
                'errors' => ['Credenciales de acceso no válidas']
            ], 401);
        }

        // El token se ha creado de manera correcta, se retorna junto con la información de acl
        return response()->json(
            compact('token', 'acl')
        );
    }

    /**
     * Verifica servicios habilitados para el OFE en openETL
     *
     * @param User $user Usuario que se intenta autenticar
     * @return array
     */
    private function verificarOfes($user) {
        $servicios = [
            'emision'           => 'NO',
            'recepcion'         => 'NO',
            'documento_soporte' => 'NO',
            'ecm'               => 'NO',
            'nomina'            => 'NO',
            'recepcion_fnc'     => 'NO'
        ];

        // En este punto el usuario aún no se ha autenticado, 
        // por lo que la consulta se debe realizar mediante el QueryBuilder
        // Datos de coneción a la BD del usuario
        $bd = AuthBaseDatos::find($user->bdd_id);
        
        // Establece una conexión dinámica a la BD tenant
        Config::set('database.connections.' . $bd->bdd_nombre, array(
            'driver'    => 'mysql',
            'host'      => $bd->bdd_host,
            'database'  => $bd->bdd_nombre,
            'username'  => $bd->bdd_usuario,
            'password'  => $bd->bdd_password,
            'charset'   => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix'    => ''
        ));

        $ofes = DB::connection($bd->bdd_nombre)
            ->table('etl_obligados_facturar_electronicamente')
            ->select(['ofe_id', 'ofe_emision', 'ofe_recepcion', 'ofe_documento_soporte', 'ofe_recepcion_fnc_activo'])
            ->when(!empty($user->bdd_id_rg), function ($query) use ($user) {
                return $query->where('bdd_id_rg', $user->bdd_id_rg);
            }, function ($query) {
                return $query->whereNull('bdd_id_rg');
            })
            ->get();

        foreach ($ofes as $ofe) {
            if($servicios['emision'] == 'NO' && $ofe->ofe_emision == 'SI')
                $servicios['emision'] = 'SI';

            if($servicios['recepcion'] == 'NO' && $ofe->ofe_recepcion == 'SI')
                $servicios['recepcion'] = 'SI';

            if($servicios['documento_soporte'] == 'NO' && $ofe->ofe_documento_soporte == 'SI')
                $servicios['documento_soporte'] = 'SI';

            if($servicios['emision'] == 'SI' && $servicios['recepcion'] == 'SI' && $servicios['documento_soporte'] == 'SI') {
                $servicios['emision']           = 'SI';
                $servicios['recepcion']         = 'SI';
                $servicios['documento_soporte'] = 'SI';
            }

            if($servicios['recepcion_fnc'] == 'NO' && $ofe->ofe_recepcion_fnc_activo == 'SI')
                $servicios['recepcion_fnc'] = 'SI';
        }

        //Modulo openECM
        $openECM = DB::connection($bd->bdd_nombre)->table('sys_variables_sistema')->select(['vsi_valor'])
            ->where('vsi_nombre', 'INTEGRACION_ECM')
            ->first();
        
        $servicios['ecm'] = 'NO';
        if ($openECM) {
            $servicios['ecm'] = $openECM->vsi_valor;
        }

        //Modulo nómina electrónica
        $empleador = DB::connection($bd->bdd_nombre)->table('dsn_empleadores')->select(['emp_id'])
            ->where('bdd_id_rg', $user->bdd_id_rg)
            ->first();

        if ($empleador) {
            $servicios['nomina'] = 'SI';
        }

        return $servicios;
    }

    /**
     * Obtiene la variable del sistema tenant Grupos de Trabajo
     *
     * @param User $user Usuario que se intenta autenticar
     * @param string $vsi_nombre Nombre de la variable del sistema
     * @return array|string
     */
    private function obtenerVariableSistema(User $user, string $vsi_nombre) {
        // En este punto el usuario aún no se ha autenticado, 
        // por lo que la consulta se debe realizar mediante el QueryBuilder
        // Datos de coneción a la BD del usuario
        $bd = AuthBaseDatos::find($user->bdd_id);

        // Establece una conexión dinámica a la BD tenant
        Config::set('database.connections.' . $bd->bdd_nombre, array(
            'driver'    => 'mysql',
            'host'      => $bd->bdd_host,
            'database'  => $bd->bdd_nombre,
            'username'  => $bd->bdd_usuario,
            'password'  => $bd->bdd_password,
            'charset'   => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix'    => ''
        ));

        $obtenerVariableSistema = DB::connection($bd->bdd_nombre)
            ->table('sys_variables_sistema')
            ->select('vsi_valor')
            ->where('vsi_nombre', $vsi_nombre)
            ->first();

        if ($obtenerVariableSistema && $obtenerVariableSistema->vsi_valor != '' && $vsi_nombre == 'NOMBRE_GRUPOS_TRABAJO')
            return json_decode($obtenerVariableSistema->vsi_valor, true);
        else if ($obtenerVariableSistema && $obtenerVariableSistema->vsi_valor != '' && $vsi_nombre == 'NOTIFICAR_ASIGNACION_GRUPO_TRABAJO')
            return $obtenerVariableSistema->vsi_valor;
        else if ($obtenerVariableSistema && $obtenerVariableSistema->vsi_valor != '' && $vsi_nombre == 'SAGRILAFT_MENSAJE')
            return $obtenerVariableSistema->vsi_valor;
        else if ($obtenerVariableSistema && $obtenerVariableSistema->vsi_valor != '' && $vsi_nombre == 'SAGRILAFT_ACTIVAR_MENSAJE')
            return $obtenerVariableSistema->vsi_valor;
        else
            return [];
    }

    /**
     * Obtiene la identificacion de los ofes activos de la base de datos del usuario autenticado.
     *
     * @param User $user Usuario que se intenta autenticar
     * @return array
     */
    private function usuarioAuthOfes($user) {
        // En este punto el usuario aún no se ha autenticado, 
        // por lo que la consulta se debe realizar mediante el QueryBuilder
        // Datos de conexión a la BD del usuario
        $bd = AuthBaseDatos::find($user->bdd_id);
        $ofes = DB::connection($bd->bdd_nombre)
            ->table('etl_obligados_facturar_electronicamente')
            ->select(['ofe_identificacion'])
            ->where(function($query) {
                $query->where('ofe_emision', 'SI')
                    ->orWhere('ofe_recepcion', 'SI')
                    ->orWhere('ofe_documento_soporte', 'SI');
            })
            ->where('estado', 'ACTIVO');

        if(!empty($user->bdd_id_rg)) {
            $ofes->where('bdd_id_rg', $user->bdd_id_rg);
        } else {
            $ofes->whereNull('bdd_id_rg');
        }

        $ofes = $ofes->get()
            ->pluck('ofe_identificacion')
            ->values()
            ->toArray();

        return $ofes;
    }
}
