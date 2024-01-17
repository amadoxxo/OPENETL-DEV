<?php

namespace App\Console\Commands;

use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;


class EnvioDocumentosContingenciaCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'envio-documentos-contingencia';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia a la DIAN documentos que se han notificado en contingencia';

    /**
     * @var array cdo_clasificacion de los documentos que aplican.
     */
    protected $arrCdoClasificacion = ['FC'];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * 
     * @return mixed
     */
    public function handle() {
        // Se deben recorrer todas las BD para ubicar los documentos FC que deben ser procesados en contingencia
        $basesDatos = AuthBaseDatos::select(['bdd_id', 'bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password', 'bdd_cantidad_procesamiento_ubl'])
            ->where('estado', 'ACTIVO')
            ->get();

        foreach($basesDatos as $baseDatos) {
            // Ubica un usuario relacionado con la BD para poder generar un token
            $user = User::where('bdd_id', $baseDatos->bdd_id)
                ->where('usu_email', 'like', '%admin@%')
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$user) {
                $user = User::where('bdd_id_rg', $baseDatos->bdd_id)
                    ->where('usu_email', 'like', '%admin@%')
                    ->where('estado', 'ACTIVO')
                    ->first();
            }

            if(!$user) 
                continue;

            $token = auth()->login($user);

            // Se establece la conexión con la base de datos
            TenantDatabase::setTenantConnection(
                'conexion01',
                $baseDatos->bdd_host,
                $baseDatos->bdd_nombre,
                $baseDatos->bdd_usuario,
                $baseDatos->bdd_password
            );

            $this->line('');
            $this->info('Iniciando proceso para la base de datos ' . trim($baseDatos->bdd_nombre));

            $dbId = $baseDatos->bdd_id;

            // Obtiene los estados marcados como CONTINGENCIA y que no esten procesados
            $estado = EtlEstadosDocumentoDaop::select(['est_id', 'usuario_creacion'])
                ->where('est_estado', 'CONTINGENCIA')
                ->whereNull('est_resultado')
                ->whereNull('est_ejecucion')
                ->whereHas('getCabeceraDocumentosDaop', function ($query) {
                    $query->whereIn('cdo_clasificacion', $this->arrCdoClasificacion);
                })
                ->get()
                ->map(function($estado) use ($dbId) {
                    // crea un agendamiento para DO
                    $agendamiento = AdoAgendamiento::create([
                        'usu_id'                  => $estado->usuario_creacion,
                        'bdd_id'                  => $dbId,
                        'age_proceso'             => 'DO',
                        'age_cantidad_documentos' => 1,
                        'age_prioridad'           => null,
                        'usuario_creacion'        => $estado->usuario_creacion,
                        'estado'                  => 'ACTIVO'
                    ]);

                    EtlEstadosDocumentoDaop::select(['est_id'])
                        ->where('est_id', $estado->est_id)
                        ->update([
                            'est_estado' => 'DO',
                            'age_id'     => $agendamiento->age_id,
                            'age_usu_id' => $estado->usuario_creacion
                        ]);
                });

            $this->info('Proceso finalizado para base de datos ' . trim($baseDatos->bdd_nombre));
            $this->line('<------------------------------------------------->');
            $this->line('');

            DB::purge('conexion01');
        }
    }
}
