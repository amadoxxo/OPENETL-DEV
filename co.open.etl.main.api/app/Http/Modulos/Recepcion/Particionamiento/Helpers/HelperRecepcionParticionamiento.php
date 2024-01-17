<?php
namespace App\Http\Modulos\Recepcion\Particionamiento\Helpers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\JoinClause;

class HelperRecepcionParticionamiento {
    /**
     * Nombre de la conexión Tenant por defecto a la base de datos.
     */
    public const CONEXION = 'conexion01';

    /**
     * Agrega columnas de una relación con la tabla de cabecera, al array select de la consulta de documentos.
     *
     * @param string $prefijoRelacion Prefijo de la relación de cabecera de la cual se incluirán las columnas en el select
     * @param array $columnasCabecera Array de las columnas de cabecera que se deben incluir en el select final
     * @return array
     */
    public static function agregarColumnasCabeceraSelect(string $prefijoRelacion, array $columnasCabecera): array {
        $arrColSelect = [];
        foreach($columnasCabecera as $columna) {
            $arrColSelect[] = $prefijoRelacion . '.' . $columna . ' as ' . $prefijoRelacion . '_' . $columna;
        }

        return $arrColSelect;
    }

    /**
     * Agrega columnas de una relación con la tabla tenant, al array select de la consulta de documentos.
     *
     * @param string $prefijoTabla Prefijo de la relación de la tabla de la cual se incluirán las columnas en el select
     * @param array $columnasSelect Array de las columnas de la tabla que se deben incluir en el select final
     * @param array $columnasSelectRaw Array de las columnas de la tabla que se deben incluir en el select como un raw
     * @return array
     */
    public static function agregarColumnasOtrasTablasTenantSelect(string $prefijoTabla, array $columnasSelect, array $columnasSelectRaw = []): array {
        $arrColSelect = [];
        foreach($columnasSelect as $columna)
            $arrColSelect[] = $prefijoTabla . '.' . $columna . ' as ' . $prefijoTabla . '_' . $columna;

        foreach($columnasSelectRaw as $columnaRaw)
            $arrColSelect[] = $columnaRaw;

        return $arrColSelect;
    }

    /**
     * Agrega columnas de una relación con estados, al array select de la consulta de documentos.
     *
     * @param string $prefijoRelacion Prefijo de la relación de estado de la cual se incluirán las columnas en el select
     * @return array
     */
    public static function agregarColumnasEstadosSelect(string $prefijoRelacion): array {
        return [
            $prefijoRelacion . ".est_id as " . $prefijoRelacion . "_est_id",
            $prefijoRelacion . ".cdo_id as " . $prefijoRelacion . "_cdo_id",
            $prefijoRelacion . ".est_correos as " . $prefijoRelacion . "_est_correos",
            $prefijoRelacion . ".est_informacion_adicional as " . $prefijoRelacion . "_est_informacion_adicional",
            $prefijoRelacion . ".est_estado as " . $prefijoRelacion . "_est_estado",
            $prefijoRelacion . ".est_resultado as " . $prefijoRelacion . "_est_resultado",
            $prefijoRelacion . ".est_mensaje_resultado as " . $prefijoRelacion . "_est_mensaje_resultado",
            $prefijoRelacion . ".est_object as " . $prefijoRelacion . "_est_object",
            $prefijoRelacion . ".est_ejecucion as " . $prefijoRelacion . "_est_ejecucion",
            $prefijoRelacion . ".est_motivo_rechazo as " . $prefijoRelacion . "_est_motivo_rechazo",
            $prefijoRelacion . ".est_inicio_proceso as " . $prefijoRelacion . "_est_inicio_proceso",
            $prefijoRelacion . ".fecha_creacion as " . $prefijoRelacion . "_fecha_creacion"
        ];
    }

    /**
     * Obtiene el estado más reciente del documento conforme a los parámetros recibidos.
     * 
     * Si el documento tiene varios estados que cumplen con las condiciones solamente se retornará el estado con el id más alto (mas reciente)
     *
     * @param JoinClause $query Instancia del QueryBuilder al que se encadenará la consulta
     * @param string $tablaPrincipal Tabla principal donde nace la consulta
     * @param string $tablaSecundaria Tabla secundaria sobre la cual se hace el JOIN
     * @param string $tablaEstados Tabla de estados de los documentos
     * @param string $estEstado Estado a consultar, puede ser vacio
     * @param string $estResultado Resultado del estado a consultar, puede ser vacio
     * @param string $estEjecucion Ejecución del estado a consultar
     * @return JoinClause
     */
    public static function relacionEstadoUltimo(JoinClause $query, string $tablaPrincipal, string $tablaSecundaria, string $tablaEstados, string $estEstado = '', string $estResultado = '', string $estEjecucion = ''): JoinClause {
        $stringWhereRaw = $tablaSecundaria . '.est_id IN (SELECT MAX(estado_max.est_id) FROM ' . $tablaEstados . ' AS estado_max WHERE ';

        if(!empty($estEjecucion))
            $stringWhereRaw .= ' estado_max.est_ejecucion = "' . $estEjecucion . '"';

        if(!empty($estEstado))
            $stringWhereRaw .= (!empty($estEjecucion) ? ' and' : '') . ' estado_max.est_estado = "' . $estEstado . '" ';

        if(!empty($estResultado))
            $stringWhereRaw .= (!empty($estEjecucion) || !empty($estEstado) ? ' and' : '') . ' estado_max.est_resultado = "' . $estResultado . '" ';

        $stringWhereRaw .= ' GROUP BY estado_max.cdo_id)';

        return $query->on($tablaPrincipal . '.cdo_id', '=', $tablaSecundaria . '.cdo_id')
            ->whereRaw($stringWhereRaw);
    }

    /**
     * Arma Joins hacia otras tablas en la base de datos.
     *
     * @param JoinClause $query Sentencia SQL en procesamiento
     * @param string $tablaPrincipal Tabla principal u origen del join
     * @param string $tablaSecundaria Tabla secundaria o destino del join
     * @param string $colRelacional Nombre de la columna que relaciona las dos tablas, de preferencia se debe usar las llaves foraneas
     * @param array $colSelect Array de colummas que deben ser seleccionadas
     * @return JoinClause
     */
    public static function relacionOtrasTablas(JoinClause $query, string $tablaPrincipal, string $tablaSecundaria, string $colRelacional, array $colSelect): JoinClause {
        return $query->from($tablaSecundaria)
            ->select($colSelect)
            ->whereRaw($tablaPrincipal . '.' . $colRelacional .' = ' . $tablaSecundaria . '.' . $colRelacional);
    }

    /**
     * Verifica si existen las tablas de particionamiento.
     * 
     * @param ParticionamientoRecepcionRepository|ParticionamientoRecepcionValidacionDocumentoRepository $conexion Nombre de la conexión actual a la base de datos tenant
     * @param array $particiones Arreglo que contiene los sufijos de partición a las tablas
     * @param array $erroresParticiones Arreglo para almacenar los errores generados
     * 
     * @return void
     */
    public static function verificaExisteTablasParticion($particionRepository, array $particiones, array &$erroresParticiones): void {
        foreach($particiones as $particion) {
            $existeTablaParticionCabecera = $particionRepository->existeTabla($particionRepository::CONEXION, 'rep_cabecera_documentos_' . $particion);
            $existeTablaParticionEstados  = $particionRepository->existeTabla($particionRepository::CONEXION, 'rep_estados_documentos_' . $particion);

            if(!$existeTablaParticionCabecera)
                $erroresParticiones[] = 'rep_cabecera_documentos_' . $particion;

            if(!$existeTablaParticionEstados)
                $erroresParticiones[] = 'rep_estados_documentos_' . $particion;
        }
    }
}
