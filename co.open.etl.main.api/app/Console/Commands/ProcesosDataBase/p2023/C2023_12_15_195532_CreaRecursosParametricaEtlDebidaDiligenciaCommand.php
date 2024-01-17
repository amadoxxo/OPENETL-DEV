<?php

namespace App\Console\Commands\ProcesosDataBase\p2023;

use App\Traits\comandosTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class C2023_12_15_195532_CreaRecursosParametricaEtlDebidaDiligenciaCommand extends Command {
    use comandosTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crea-recursos-parametrica-etl-debida-diligencia-documentos-2023-12-15';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea los recursos de la parametrica debida diligencia';

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
            // Recursos para Parametros DIAN > Sector Control Cambiario > Debida Diligencia
            [ 
                'rec_alias'              => 'ParametricaSectorCambiarioDebidaDiligencia',
                'rec_modulo'             => 'SectorCambiario', 
                'rec_controlador'        => 'ParametrosDebidaDiligenciaController', 
                'rec_accion'             => 'lista', 
                'rec_modulo_descripcion' => 'Sector Cambiario',
                'rec_descripcion'        => 'Sector Cambiario Debida Diligencia',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ParametricaSectorCambiarioDebidaDiligenciaVer',
                'rec_modulo'             => 'SectorCambiario', 
                'rec_controlador'        => 'ParametrosDebidaDiligenciaController', 
                'rec_accion'             => 'ver', 
                'rec_modulo_descripcion' => 'Sector Cambiario',
                'rec_descripcion'        => 'Ver Debida Diligencia',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ParametricaSectorCambiarioDebidaDiligenciaNuevo',
                'rec_modulo'             => 'SectorCambiario', 
                'rec_controlador'        => 'ParametrosDebidaDiligenciaController', 
                'rec_accion'             => 'nuevo', 
                'rec_modulo_descripcion' => 'Sector Cambiario',
                'rec_descripcion'        => 'Nuevo Debida Diligencia',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ParametricaSectorCambiarioDebidaDiligenciaEditar',
                'rec_modulo'             => 'SectorCambiario', 
                'rec_controlador'        => 'ParametrosDebidaDiligenciaController', 
                'rec_accion'             => 'editar', 
                'rec_modulo_descripcion' => 'Sector Cambiario',
                'rec_descripcion'        => 'Editar Debida Diligencia',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
            [ 
                'rec_alias'              => 'ParametricaSectorCambiarioDebidaDiligenciaCambiarEstado',
                'rec_modulo'             => 'SectorCambiario', 
                'rec_controlador'        => 'ParametrosDebidaDiligenciaController', 
                'rec_accion'             => 'cambiarEstado', 
                'rec_modulo_descripcion' => 'Sector Cambiario',
                'rec_descripcion'        => 'Cambiar Estado Debida Diligencia',
                'usuario_creacion'       => '1',
                'estado'                 => 'ACTIVO'
            ],
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
