<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Collection;
use App\Http\Modulos\Sistema\Roles\SistemaRol;
use App\Http\Modulos\Sistema\Permisos\SistemaPermiso;
use App\Http\Modulos\Sistema\Recursos\SistemaRecurso;

/**
 * Trait que incluye métodos reutilizables en los comandos de Laravel
 */
trait comandosTrait {
    /**
     * Devuelve una colección con las instancias de los roles correspondientes.
     *
     * @param array $nombreRoles Array con los nombres de los roles a filtrar
     * @return Collection
     */
    public function consultarRoles(array $nombreRoles): Collection {
        return SistemaRol::select('rol_id')
            ->whereIn('rol_codigo', $nombreRoles)
            ->get();
    }

    /**
     * Crea un recurso en el sistema.
     *
     * @param  array $insert Array con la información del recurso a crear
     * @return SistemaRecurso
     */
    public function crearRecurso(array $insert): SistemaRecurso{
        return SistemaRecurso::select(['rec_id'])
            ->where('rec_modulo', $insert['rec_modulo'])
            ->where('rec_controlador', $insert['rec_controlador'])
            ->where('rec_accion', $insert['rec_accion'])
            ->firstOr(function() use ($insert) {
                return SistemaRecurso::create($insert);
            });
    }

    /**
     * Asigna un recurso a un rol en el sistema.
     *
     * @param  int $rol_id ID del rol al cual se le asignará el recurso
     * @param  int $rec_id ID del recurso que será asignado
     * @return void
     */
    public function crearPermiso(int $rol_id, int $rec_id): void{
        SistemaPermiso::firstOrCreate(
            [
                'rol_id' => $rol_id,
                'rec_id' => $rec_id
            ],
            [
                'usuario_creacion'   => '1',
                'estado'             => 'ACTIVO'
            ]
        );
    }
}