<?php

namespace App\Console\Commands\ProcesosDataBase\p2023;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Modulos\Sistema\Roles\SistemaRol;

class C2023_02_02_104239_CreaRecursosPryDatosParametricosValidacionFondosCommand extends Command {
    /**
     * Nombre del comando de la consola.
     *
     * @var string
     */
    protected $signature = 'crea-recursos-pry-datos-parametricos-validacion-fondos-2023-02-02';

    /**
     * Descripción del comando de la consola.
     *
     * @var string
     */
    protected $description = 'Crea recursos para el proyecto especial de FNC Datos Paramétricos Validación - Clasificación fondos';

    /**
     * Crea una nueva instancia del comando.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Ejecución del comando en la consola.
     * 
     * @return mixed
     */
    public function handle() {
        $inserts = [
            [ 
                'rec_id'                    => null, 
                'rec_alias'                 => 'ConfiguracionRecepcionFondos',
                'rec_modulo'                => 'Configuracion', 
                'rec_controlador'           => 'PryDatosParametricosValidacion', 
                'rec_accion'                => 'listar', 
                'rec_modulo_descripcion'    => 'Recepcion',
                'rec_descripcion'           => 'Lista de Datos Paramétricos Validación - Clasificación fondos',
                'usuario_creacion'          => '1',
                'fecha_creacion'            => Carbon::now(),
                'fecha_modificacion'        => Carbon::now(),
                'estado'                    => 'ACTIVO'
            ],
            [ 
                'rec_id'                    => null, 
                'rec_alias'                 => 'ConfiguracionRecepcionFondosNuevo',
                'rec_modulo'                => 'Configuracion', 
                'rec_controlador'           => 'PryDatosParametricosValidacion', 
                'rec_accion'                => 'store', 
                'rec_modulo_descripcion'    => 'Recepcion',
                'rec_descripcion'           => 'Nuevo Dato Paramétrico Validación - Clasificación fondos',
                'usuario_creacion'          => '1',
                'fecha_creacion'            => Carbon::now(),
                'fecha_modificacion'        => Carbon::now(),
                'estado'                    => 'ACTIVO'
            ],
            [ 
                'rec_id'                    => null, 
                'rec_alias'                 => 'ConfiguracionRecepcionFondosEditar',
                'rec_modulo'                => 'Configuracion', 
                'rec_controlador'           => 'PryDatosParametricosValidacion', 
                'rec_accion'                => 'update', 
                'rec_modulo_descripcion'    => 'Recepcion',
                'rec_descripcion'           => 'Editar Dato Paramétrico Validación - Clasificación fondos',
                'usuario_creacion'          => '1',
                'fecha_creacion'            => Carbon::now(),
                'fecha_modificacion'        => Carbon::now(),
                'estado'                    => 'ACTIVO'
            ],
            [ 
                'rec_id'                    => null, 
                'rec_alias'                 => 'ConfiguracionRecepcionFondosVer',
                'rec_modulo'                => 'Configuracion', 
                'rec_controlador'           => 'PryDatosParametricosValidacion', 
                'rec_accion'                => 'show', 
                'rec_modulo_descripcion'    => 'Recepcion',
                'rec_descripcion'           => 'Ver Dato Paramétrico Validación - Clasificación fondos',
                'usuario_creacion'          => '1',
                'fecha_creacion'            => Carbon::now(),
                'fecha_modificacion'        => Carbon::now(),
                'estado'                    => 'ACTIVO'
            ],
            [ 
                'rec_id'                    => null, 
                'rec_alias'                 => 'ConfiguracionRecepcionFondosCambiarEstado',
                'rec_modulo'                => 'Configuracion', 
                'rec_controlador'           => 'PryDatosParametricosValidacion', 
                'rec_accion'                => 'cambiarEstado', 
                'rec_modulo_descripcion'    => 'Recepcion',
                'rec_descripcion'           => 'Cambiar estado de Datos Paramétricos Validación - Clasificación fondos',
                'usuario_creacion'          => '1',
                'fecha_creacion'            => Carbon::now(),
                'fecha_modificacion'        => Carbon::now(),
                'estado'                    => 'ACTIVO'
            ],
        ];
        
        // Obtiene el rol 'superadmin' y 'usuarioma' del sistema para poder asignarle los nuevos permisos
        $adminRol = SistemaRol::select('rol_id')->whereIn('rol_codigo', ['superadmin', 'usuarioma'])->get();

        foreach ($inserts as $key => $insert) {
            try {
                $this->info("[{$insert['rec_alias']}]: Insertando el recurso del modulo [{$insert['rec_modulo']}] para la accion [{$insert['rec_accion']}]");
                $rec_id = DB::table('sys_recursos')->insertGetId($insert);

                $this->info("[{$insert['rec_alias']}]: Insertando permiso...");

                foreach ($adminRol as $rol) {
                    $insert_permiso = $this->insertPermiso($rec_id, $rol->rol_id);
                }
                $this->alertPermiso($insert_permiso, $insert);
            } catch (\Exception $e){
                $recurso = DB::table('sys_recursos')->select('rec_id')
                    ->where('rec_modulo', $insert['rec_modulo'])
                    ->where('rec_controlador', $insert['rec_controlador'])
                    ->where('rec_accion', $insert['rec_accion'])
                    ->first();

                if (!empty($recurso)) {
                    foreach ($adminRol as $rol) {
                        $insertPermiso = $this->insertPermiso($recurso->rec_id, $rol->rol_id);
                    }
                    $this->alertPermiso($insertPermiso, $insert);
                }
            }
        }
    }

    /**
     * Mensaje en el comando para indicar el resultado del permiso.
     *
     * @param  bool  $insertPermiso Indica si se ha insertado el permiso
     * @param  array $inserting Data del recurso a insertar
     * @return void
     */
    private function alertPermiso(bool $insertPermiso, array $inserting): void {
        if ($insertPermiso){
            $this->info("[{$inserting['rec_alias']}]: Se crearon los permisos del recurso correctamente.");
        } else {
            $this->error("[{$inserting['rec_alias']}]: Ya existen los permisos del recurso en el sistema.");
        }
    }

    /**
     * Inserta el permiso en la tabla sys_permisos.
     *
     * @param  string $rec_id Id del recurso a insertar
     * @param  string $rol_id Id del rol a insertar
     * @return bool
     */
    private function insertPermiso(string $rec_id, string $rol_id): bool{
        try {
            return DB::table('sys_permisos')->insert([
                'rol_id'                => $rol_id,
                'rec_id'                => $rec_id,
                'usuario_creacion'      => '1',
                'fecha_creacion'        => \Carbon\Carbon::now(),
                'fecha_modificacion'     => \Carbon\Carbon::now(),
                'estado'                => 'ACTIVO'
            ]);
        }
        catch (\Exception $e){
            return false;
        }
    }
    
}
