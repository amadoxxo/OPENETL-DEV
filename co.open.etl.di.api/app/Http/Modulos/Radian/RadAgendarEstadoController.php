<?php

namespace App\Http\Modulos\Radian;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Models\AdoAgendamiento;
use App\Http\Controllers\Controller;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\Radian\RadianActores\RadianActor;
use App\Http\Modulos\Parametros\Radian\Roles\ParametrosRadianRol;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;

class RadAgendarEstadoController extends Controller {
    use FechaVigenciaValidations;

    /**
     * Constructor de la clase.
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);

        $this->middleware(['VerificaMetodosRol:RadianRegistroDocumentos'])->only([
            'agendarRadEdi'
        ]);
    }

    /**
     * Recibe un Documento Json en el request para registrar un agendamiento para procesamiento del Json.
     *
     * @param  Request      $request Parametros de la petición
     * @return JsonResponse
     */
    public function agendarRadEdi(Request $request): JsonResponse {
        $arrErrors = [];
        // Autenticación recibiendo un token en los encabezados del request
        $user = auth()->user();
        if (empty($request->data)) {
            return response()->json([
                'message' => 'Error en la petición',
                'errors'  => ['La petición esta mal formada, debe expecificarse un tipo y objeto JSON']
            ], 422);
        }

        foreach ($request->data as $value) {
            $acts = RadianActor::select(['act_id', 'act_identificacion', 'act_roles', 'estado'])
                    ->where('act_identificacion', $value['act_identificacion'])
                    ->where('bdd_id_rg', $user->bdd_id_rg)
                    ->where('estado', 'ACTIVO')
                    ->first();

            $rolActor = json_decode($acts->act_roles);

            if ($acts->act_roles !== null && $value['rol_id'] !== null && in_array($value['rol_id'], $rolActor) == false) {
                $arrErrors[] = 'El ROL [' . $value['rol_id'] . '] no pertenece al ACTOR con identificación [' . $value['act_identificacion'] . '].';
            }

            if ($acts->act_roles == null && $value['rol_id'] !== null) {
                $arrErrors[] = 'El ROL [' . $value['rol_id'] . '] no pertenece al ACTOR con identificación [' . $value['act_identificacion'] . '].';
            }

            if (!$acts) {
                $arrErrors[] = 'Actor con Identificación [' . $value['act_identificacion'] . '] no está ACTIVA o no pertenece a la misma BD del usuario autenticado';
            }

            if ($value['rol_id'] !== null) {
                $roles = ParametrosRadianRol::select(['rol_id', 'rol_descripcion'])
                    ->where('rol_id', $value['rol_id'])
                    ->get();
    
                if ($roles->isEmpty()) {
                    $arrErrors[] = 'El ROL [' . $value['rol_id']. '] no existe.'; 
                } else {
                    $roles->groupBy('rol_descripcion')
                        ->map(function ($rol) use (&$arrErrors){
                            $vigente = $this->validarVigenciaRegistroParametrica($rol);
                            if (!$vigente['vigente']) {
                                $arrErrors[] = 'El ROL [' . $vigente['registro']->rol_descripcion. '] no está vigente.';  
                            }
                        });
                }
            }

            if (empty($value['cufe'])) {
                $arrErrors[] = 'El CUFE no puede ser vacío.';
            }
        }

        if (empty($arrErrors)) {
            try {
                $bloques = array_chunk($request->data, $user->getBaseDatos->bdd_cantidad_procesamiento_edi);
                foreach($bloques as $bloque) {
                    // Crea el agendamiento en el sistema
                    $agendamiento = AdoAgendamiento::create([
                        'usu_id'                    => $user->usu_id,
                        'bdd_id'                    => $user->bdd_id,
                        'age_proceso'               => 'RADEDI',
                        'age_cantidad_documentos'   => count($bloque),
                        'age_prioridad'             => null,
                        'usuario_creacion'          => $user->usu_id,
                        'estado'                    => 'ACTIVO'
                    ]);
    
                    // Graba la información del Json en la tabla de programacion de procesamientos de Json
                    EtlProcesamientoJson::create([
                        'pjj_tipo'                  => 'RADEDI',
                        'pjj_json'                  => json_encode($bloque),
                        'pjj_procesado'             => 'NO',
                        'age_id'                    => $agendamiento->age_id,
                        'age_estado_proceso_json'   => null,
                        'usuario_creacion'          => $user->usu_id,
                        'fecha_creacion'            => date('Y-m-d H:i:s'),
                        'estado'                    => 'ACTIVO',
                    ]);
                }
                $cantAge = count($request->data);
                return response()->json([
                    'message' => "Resumen Procesamiento Documentos. Se ha agendado $cantAge documento para su procesamiento en background."
                ], 201);
            } catch (Exception $e) {
                $error = $e->getMessage();
                return response()->json([
                    'message' => 'Error al registrar el Json',
                    'errors'  => [$error]
                ], 400);
            }   
        } else {
            return response()->json([
                'message' => 'Error al registrar el Json',
                'errors'  => $arrErrors
            ], 400);
        }
    }
}
