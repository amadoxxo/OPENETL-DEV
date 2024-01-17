<?php

namespace App\Jobs;

use JWTAuth;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Queue\InteractsWithQueue;
use openEtl\Tenant\Traits\TenantDatabase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Modulos\Documentos\EtlDocumentosDaopController;
use App\Http\Modulos\Documentos\EtlProcesamientoJson\EtlProcesamientoJson;

class ProcesarDocumentosJson implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function handle(Request $request) {
        // Obtiene el usuario relacionado con la programación del Job
        $user = User::find($this->data['usu_id']);

        TenantDatabase::setTenantConnection(
            'conexion01',
            $user->getBaseDatos->bdd_host,
            $user->getBaseDatos->bdd_nombre,
            $user->getBaseDatos->bdd_usuario,
            $user->getBaseDatos->bdd_password
        );

        // Se obtiene el registro de documentos json que deben ser procesados de acuerdo al Job
        $programacion = EtlProcesamientoJson::where('pjj_procesado', 'NO')
            ->where('pjj_id', $this->data['pjj_id'])
            ->where('usuario_creacion', $this->data['usu_id'])
            ->first();

        // Generación del token requerido por el método principal del parser
        $campos = [
            'id' => $user->usu_id,
            'email' => $user->usu_email,
            'nombre' => $user->usu_nombre,
            'identificacion' => $user->usu_identificacion,
        ];
        $token = JWTAuth::fromUser($user, $campos);

        // Inicializa los headers del Request
        $request->headers->add(['Authorization' => 'Bearer ' . $token]);
        $request->headers->add(['accept' => 'application/json']);
        $request->headers->add(['x-requested-with' => 'XMLHttpRequest']);
        $request->headers->add(['content-type' => 'application/json']);
        $request->headers->add(['cache-control' => 'no-cache']);
        $request->request->add(['documentos' => json_decode($programacion->pjj_json, true)]);
        $request->json()->add(['documentos' => json_decode($programacion->pjj_json, true)]);

        if($programacion->pjj_tipo == 'FC' || $programacion->pjj_tipo == 'ND-NC') {
            // Controlador EtlDocumentosDaopController
            $EtlDocumentosDaopController = App::make(EtlDocumentosDaopController::class);
            $procesar = App::call([$EtlDocumentosDaopController, 'registrarDocumentos'], [
                'request' => $request
            ]);

            if(array_key_exists('errors', $procesar->original)) {
                $programacion->update([
                    'pjj_procesado' => 'SI',
                    'pjj_errores'   => (string)json_encode($procesar->original['errors'])
                ]);
            } else {
                $programacion->update([
                    'pjj_procesado' => 'SI'
                ]);
            }
        }
    }
}
