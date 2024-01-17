<?php

namespace App\Console\Commands\ProcesosDataBase\p2023;

use App\Traits\comandosTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class C2023_03_27_081201_CreaRecursosRecepcionReporteDependenciasCommand extends Command {
    use comandosTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crea-recursos-recepcion-reporte-dependencias-fnc-2023-03-27';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea los recursos de recepción para el módulo de reporte de dependencias de FNC';

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
        $inserts = [
            [ 
                'rec_alias'              => 'RecepcionReporteDependencias',
                'rec_modulo'             => 'Recepcion', 
                'rec_controlador'        => 'RecepcionReporteDepencenciaController', 
                'rec_accion'             => 'listar', 
                'rec_modulo_descripcion' => 'Reportes',
                'rec_descripcion'        => 'Listar Reportes Dependencias',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ]
        ];

        // Obtiene los roles 'superadmin' y 'usuarioma' del sistema para poder asignarle los nuevos permisos
        $roles = $this->consultarRoles(['superadmin', 'usuarioma']);

        // Itera sobre los recursos para poder crearlos
        foreach ($inserts as $recurso) {
            DB::beginTransaction();
            try {
                $this->info("Insertando permiso para el recurso: [{$recurso['rec_alias']}-{$recurso['rec_modulo']}-{$recurso['rec_descripcion']}].");

                $recursoCreado = $this->crearRecurso($recurso);

                foreach ($roles as $rol)
                    $this->crearPermiso($rol->rol_id, $recursoCreado->rec_id);

                DB::commit();

                $this->info("[{$recurso['rec_alias']}]: Recurso creado con los permisos correctamente.");
            } catch (\Exception $e){
                DB::rollBack();

                $this->error("[{$recurso['rec_alias']}]: error al intentar crear el recurso [" . $e->getFile() . " - Línea " . $e->getLine() . ": " . $e->getMessage() . "].");
            }
        }
    }
}
