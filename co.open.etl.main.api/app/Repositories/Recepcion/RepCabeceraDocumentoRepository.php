<?php
namespace App\Repositories\Recepcion;

use Illuminate\Http\Request;
use App\Traits\GruposTrabajoTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use openEtl\Tenant\Traits\TenantTrait;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepFatDocumentosDaop\RepFatDocumentoDaop;
use App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacita;
use App\Http\Modulos\Recepcion\Documentos\RepDocumentosAnexosDaop\RepDocumentoAnexoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepMediosPagoDocumentosDaop\RepMediosPagoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepDatosAdicionalesDocumentosDaop\RepDatoAdicionalDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RepCabeceraDocumentoRepository {
    use GruposTrabajoTrait;

    /**
     * Nombre de la conexión Tenant por defecto a la base de datos.
     *
     * @var string
     */
    protected $connection = 'conexion01';

    /**
     * Nombre de la tabla en donde se almacena la información básica histórica de un registro que es movido a particionamiento.
     *
     * @var string
     */
    protected $tablaFat = 'rep_fat_documentos_daop';

    /**
     * Array de tablas que deben ser particionadas.
     *
     * @var array
     */
    public $arrTablasParticionar = [
        'rep_cabecera_documentos_',
        'rep_datos_adicionales_documentos_',
        'rep_documentos_anexos_',
        'rep_estados_documentos_',
        'rep_medios_pago_documentos_'
    ];

    /**
     * Segundos que corresponden al tiempo estandar para aceptación tácita
     *
     * @var int
     */
    public $estandarAceptacionTacita;
    
    /**
     * Array de nombres cortos que se pueden aplicar a relaciones o índices.
     * 
     * Esto es útil toda vez que el motor de base de datos restringe longitudes a 64 caracteres y al crear las nuevas tablas de particiones hay índices y FK que se revientan por la longitud
     *
     * @var array
     */
    protected $arrNombresCortos = [
        'rep_cabecera_documentos_'          => 'cdo_',
        'rep_datos_adicionales_documentos_' => 'dad_',
        'rep_documentos_anexos_'            => 'dan_',
        'rep_estados_documentos_'           => 'est_',
        'rep_medios_pago_documentos_'       => 'men_'
    ];

    public function __construct() {
        $this->estandarAceptacionTacita = SistemaTiempoAceptacionTacita::where('tat_default', 'SI')
            ->where('estado', 'ACTIVO')
            ->first();
    }

    /**
     * Establece una conexión a una base de datos Tenant.
     *
     * @param AuthBaseDatos $baseDatos Instancia de la base de datos a la que se debe conectar
     * @return void
     */
    public function generarConexionTenant(AuthBaseDatos $baseDatos = null): void {
        if(empty($baseDatos))
            $baseDatos = $this->getDataBase();

        DB::disconnect($this->connection);

        // Se establece la conexión con la base de datos
        TenantDatabase::setTenantConnection(
            $this->connection,
            $baseDatos->bdd_host,
            $baseDatos->bdd_nombre,
            env('DB_USERNAME'),
            env('DB_PASSWORD')
        );
    }

    /**
     * Retorna la información de conexión a una base de datos.
     *
     * @return AuthBaseDatos
     */
    public function getDataBase(): AuthBaseDatos {
        return AuthBaseDatos::select(['bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password'])
            ->where('bdd_id', !empty(auth()->user()->bdd_id_rg) ? auth()->user()->bdd_id_rg : auth()->user()->bdd_id)
            ->where('estado', 'ACTIVO')
            ->first();
    }

    /**
     * Verifica la existencia de una tabla de acuerdo a la conexión establecida.
     *
     * @param string $conexion Nombre de la conexión actual a la base de datos
     * @param string $tabla Nombre de la tabla a verificar
     * @return boolean
     */
    public function existeTabla(string $conexion, string $tabla): bool {
        return Schema::connection($conexion)->hasTable($tabla);
    }

    /**
     * Crea una tabla de particionamiento en la base datos relacionada con la conexión.
     *
     * @param string $conexion Nombre de la conexión a utilizar
     * @param string $tablaParticionar Nombre de la tabla sin el sufijo
     * @param string $periodo Periodo para el cual se creará la tabla de particionamiento, actua como sufijo al nombre de la tabla (YYYYMM)
     * @return void
     */
    public function crearTablaParticionamiento(string $conexion, string $tablaParticionar, string $periodo): void {
        // Genera la sentencia SQL que permite crear una tabla particionada a partir de la estructura de la tabla de data opertiva (incluyendo índices y llaves foráneas)
        $crearTabla = DB::connection($conexion)
            ->select(DB::raw('SHOW CREATE TABLE ' . $tablaParticionar . 'daop'));
        $crearTabla = (array) $crearTabla[0];
        $crearTabla = str_replace(
            [
                $tablaParticionar . 'daop',
                '_rep_cabecera_documento_',
                'fk1_rep_medios_pago_documentos_'
            ], 
            [
                $tablaParticionar . $periodo,
                '_rep_cabecera_documento_' . $periodo,
                'fk1_rep_' . $this->arrNombresCortos[$tablaParticionar] . $periodo . '_'
            ],
            $crearTabla['Create Table']
        );
        $crearTabla = str_replace('daop', $periodo, $crearTabla);

        // Crea la nueva tabla de partición
        DB::connection($conexion)
            ->statement($crearTabla);
    }

    /**
     * Retorna un array de columnas coincidentes entre una tabla de data operativa y una tabla particionada.
     *
     * @param string $tablaParticion Nombre (sin sufijo) de la tabla particionada
     * @param string $particion Nombre (sufijo) para la partición
     * @return array Array de las columnas coincidentes
     */
    public function diferenciasColumnasTabla(string $tablaParticion, string $particion): array {
        $columnasDaop = Schema::connection($this->connection)->getColumnListing($tablaParticion . 'daop');
        $columnasHist = Schema::connection($this->connection)->getColumnListing($tablaParticion . $particion);

        // Las columnas diferentes deben ser descartadas del select a la tabla particionada
        $columnasDiferentes = array_diff($columnasDaop, $columnasHist);
        if(!empty($columnasDiferentes))
            foreach($columnasDiferentes as $descartar)
                if(in_array($descartar, $columnasDaop)) {
                    $indice = array_search($descartar, $columnasDaop);
                    unset($columnasDaop[$indice]);
                }

        return $columnasDaop;
    }

    /**
     * Traslada un documento desde la data operativa a las tablas particionadas.
     *
     * @param RepCabeceraDocumentoDaop $documento Colección del documento a trasladar
     * @param string $particion Partición destino
     * @param string $comandoOrigen De acuerdo al comando desde donde se llama el método, se permite verificar que los registros de DAOP existan en las tablas particionadas para eliminar de DAOP o en caso contrario eliminar de las tablas particionadas
     * @return void
     */
    public function trasladarDocumentoHistorico(RepCabeceraDocumentoDaop $documento, string $particion, string $comandoOrigen): void {
        $columnasFat = implode(',', Schema::connection($this->connection)->getColumnListing('rep_fat_documentos_daop'));

        $builder = DB::connection($this->connection);
        $builder->statement('SET FOREIGN_KEY_CHECKS=0');
        if($comandoOrigen == 'traslado-automatico' || $comandoOrigen == 'traslado-manual') {
            foreach($this->arrTablasParticionar as $tablaParticion) {
                if(!$this->existeTabla($this->connection, $tablaParticion . $particion))
                    $this->crearTablaParticionamiento($this->connection, $tablaParticion, $particion);

                $columnasSelect = implode(',', $this->diferenciasColumnasTabla($tablaParticion, $particion));

                if($tablaParticion == 'rep_cabecera_documentos_') {
                    // Consulta si el autoincremental ya existe
                    $existe = $builder->select("SELECT cdo_id FROM {$this->tablaFat} WHERE cdo_id={$documento->cdo_id}");

                    if(!empty($existe))
                        $builder->delete("DELETE FROM {$this->tablaFat} WHERE cdo_id={$documento->cdo_id}");

                    $builder->insert("INSERT INTO {$this->tablaFat} (SELECT {$columnasFat} FROM {$tablaParticion}daop WHERE cdo_id={$documento->cdo_id})");
                }

                // Consulta si el autoincremental ya existe
                $existe = $builder->select("SELECT cdo_id FROM {$tablaParticion}{$particion} WHERE cdo_id={$documento->cdo_id}");

                if(!empty($existe))
                    $builder->delete("DELETE FROM {$tablaParticion}{$particion} WHERE cdo_id={$documento->cdo_id}");

                $builder->insert("INSERT INTO {$tablaParticion}{$particion} (SELECT {$columnasSelect} FROM {$tablaParticion}daop WHERE cdo_id={$documento->cdo_id})");
            }
        }

        if($comandoOrigen == 'traslado-automatico' || $comandoOrigen == 'verificacion-traslado-manual') {
            // Se debe verificar que la cantidad de registros copiados en cada tabla en la partición coincidan con la cantidad de registros de cada tabla daop
            $deshacer = false;
            foreach($this->arrTablasParticionar as $tablaParticion) {
                $totalDaop      = $builder->select("SELECT COUNT(cdo_id) as totalRegistros FROM {$tablaParticion}daop WHERE cdo_id={$documento->cdo_id} GROUP BY cdo_id");
                $totalParticion = $builder->select("SELECT COUNT(cdo_id) as totalRegistros FROM {$tablaParticion}{$particion} WHERE cdo_id={$documento->cdo_id} GROUP BY cdo_id");

                if(
                    (empty($totalDaop) && !empty($totalParticion)) ||
                    (!empty($totalDaop) && empty($totalParticion)) ||
                    (!empty($totalDaop) && !empty($totalParticion) && $totalDaop[0]->totalRegistros != $totalParticion[0]->totalRegistros)
                )
                    $deshacer = true;
            }

            // Si deshacer es true, en alguna tabla no se copiaron los registros de manera completa, se deben eliminar todos los registros creados en las tablas de la particion
            if($deshacer) {
                foreach($this->arrTablasParticionar as $tablaParticion) {
                    $builder->delete("DELETE FROM {$tablaParticion}{$particion} WHERE cdo_id={$documento->cdo_id}");
                }
            // Si deshacer es false, se copiaron todos los registros de manera completa en todas las tablas, se deben eliminar todos los registros de las tablas daop
            } else {
                foreach($this->arrTablasParticionar as $tablaParticion) {
                    $builder->delete("DELETE FROM {$tablaParticion}daop WHERE cdo_id={$documento->cdo_id}");
                }
            }
        }

        $builder->statement('SET FOREIGN_KEY_CHECKS=0');
    }

    /**
     * Retorna el OFE recibido en el request de una petición.
     * 
     * La respuesta incluye la relación con los grupos de trabajo del OFE, si los tiene configurados
     *
     * @param int $ofe_id ID del OFE
     * @return ConfiguracionObligadoFacturarElectronicamente
     */
    public function getRequestOfe(int $ofe_id): ConfiguracionObligadoFacturarElectronicamente {
        return ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo'])
            ->where('ofe_id', $ofe_id)
            ->with([
                'getGruposTrabajo' => function($query) {
                    $query->select(['gtr_id', 'ofe_id'])
                        ->where('estado', 'ACTIVO');
                }
            ])
            ->first();
    }

    /**
     * Retorna los IDs de las formas de pago conforme al código recibido en el request.
     *
     * @param Request $request Parametros de la petición
     * @return array
     */
    public function formasPagoId(Request $request): array {
        $arrIdsFormaPago = [];
        ParametrosFormaPago::select('fpa_id')
            ->where('fpa_codigo', $request->forma_pago)
            ->where('fpa_aplica_para', 'LIKE', '%' . 'DE' . '%')
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function($item) use (&$arrIdsFormaPago){
                $arrIdsFormaPago[] = $item['fpa_id'];
            });

        return $arrIdsFormaPago;
    }

    /**
     * Retorna las columnas personalizadas de un OFE.
     *
     * @param Request $request
     * @return array
     */
    public function excelColumnasPersonalizadasOfe(Request $request): array {
        return ConfiguracionObligadoFacturarElectronicamente::select(['ofe_columnas_personalizadas', 'ofe_recepcion_fnc_activo', 'ofe_recepcion_fnc_configuracion'])
            ->find($request->ofe_id)
            ->toArray();
    }

    /**
     * Permite filtrar los documentos electrónicos teniendo en cuenta la configuración de grupos de trabajo a nivel de usuario autenticado y proveedores.
     * 
     * Si el usuario autenticado esta configurado en algún grupo de trabajo, solamente se deben listar documentos electrónicos de los proveedores asociados con ese mismo grupo o grupos de trabajo
     * Si el usuario autenticado no esta configurado en ningún grupo de trabajo, se verifica si el usuario está relacionado directamente con algún proveedor para mostrar solamente documentos de esos proveedores
     * Si no se da ninguna de las anteriores condiciones, el usuario autenticado debe poder ver todos los documentos electrónicos de todos los proveedores
     *
     * @param Builder|EloquentBuilder $query Consulta que está en procesamiento
     * @param int $ofeId ID del OFE para el cual se está haciendo la consulta
     * @param bool $usuarioGestor Indica cuando se debe tener en cuenta que se trate de un usuario gestor
     * @param bool $usuarioValidador Indica cuando se debe tener en cuenta que se trate de un usuario validador
     * @param bool $queryBuilder Indica que el método es llamado desde una construcción con QueryBuilder y no con Eloquent
     * @param \StdClass $tablasProcesos Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Builder|EloquentBuilder Retorna una instancia del Query Builder cuando $queryBuilder es true, de lo contrario retorna una instancia de Eloquent Builder
     * @deprecated Esta lógica fue movida a la clase app/Http/Modulos/Recepcion/Particionamiento/Repositories/ParticionamientoRecepcionRepository
     */
    public function verificaRelacionUsuarioProveedor($query, int $ofeId, bool $usuarioGestor = false, bool $usuarioValidador = false, bool $queryBuilder = false, \stdClass $tablasProceso = null) {
        $user = auth()->user();

        $ofe = ConfiguracionObligadoFacturarElectronicamente::select('ofe_recepcion_fnc_activo')
            ->where('ofe_id', $ofeId)
            ->first();

        $gruposTrabajoUsuario = $this->getGruposTrabajoUsuarioAutenticado($ofe, $usuarioGestor, $usuarioValidador);

        if(!empty($gruposTrabajoUsuario)) {
            if($queryBuilder)
                $query->whereRaw('EXISTS 
                    (
                        SELECT * FROM ' . $tablasProceso->tablaGruposTrabajoProveedor . '
                        WHERE ' . $tablasProceso->tablaProveedores . '.pro_id = ' . $tablasProceso->tablaGruposTrabajoProveedor . '.pro_id
                        AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                        AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.estado = "ACTIVO"
                    )');
            else
                $query->whereHas('getProveedorGruposTrabajo', function($gtrProveedor) use ($gruposTrabajoUsuario) {
                    $gtrProveedor->whereIn('gtr_id', $gruposTrabajoUsuario)
                        ->where('estado', 'ACTIVO');
                });
        } else {
            // Verifica si el usuario autenticado esta asociado con uno o varios proveedores para mostrar solo los documentos de ellos, de lo contrario mostrar los documentos de todos los proveedores en la BD
            $consultaProveedoresUsuario = ConfiguracionProveedor::select(['pro_id'])
                ->where('ofe_id', $ofeId)
                ->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%')
                ->where('estado', 'ACTIVO')
                ->get();
                
            if($consultaProveedoresUsuario->count() > 0)
                $query->when(!$tablasProceso, function($query) use ($ofeId) {
                        return $query->where('ofe_id', $ofeId);
                    }, function($query) use ($tablasProceso, $ofeId) {
                        return $query->where($tablasProceso->tablaDocs . '.ofe_id', $ofeId);
                    })
                    ->where('pro_usuarios_recepcion', 'like', '%"' . $user->usu_identificacion . '"%');
        }

        return $query;
    }

    /**
     * Arma la sentencia del QueryBuilder que permite consultar los registro de un documento con un mismo estado.
     * 
     * Esta consulta puede devolver varios registros.
     *
     * @param Builder $query Instancia del QueryBuilder al que se encadenará la consulta
     * @param string $tablaDocs Nombre de la tabla de documentos (partición)
     * @param string $tablaEstados Nombre de la tabla de estados de documentos (partición)
     * @param array $colSelect Array de columnas a seleccionar
     * @param string $estEstado Estado a consultar
     * @param string $estResultado Resultado del estado a consultar
     * @param string $estEjecucion Ejecución del estado a consultar
     * @return Builder
     */
    private function relacionEstadosDocumentos(Builder $query, string $tablaDocs, string $tablaEstados, array $colSelect, string $estEstado, string $estResultado, string $estEjecucion): Builder {
        if(!empty($estEjecucion))
            return $query->from($tablaEstados)
                ->select($colSelect)
                ->whereRaw($tablaDocs . '.cdo_id = ' . $tablaEstados . '.cdo_id')
                ->where($tablaEstados . '.est_estado', $estEstado)
                ->when(!empty($estResultado), function($query) use ($tablaEstados, $estResultado) {
                    return $query->where($tablaEstados . '.est_resultado', $estResultado);
                }, function($query) use ($tablaEstados) {
                    return $query->whereNull($tablaEstados . '.est_resultado');
                })
                ->when($estEjecucion != 'ENPROCESO', function($query) use ($tablaEstados, $estEjecucion) {
                    return $query->where($tablaEstados . '.est_ejecucion', $estEjecucion);
                }, function($query) use ($tablaEstados, $estEjecucion) {
                    return $query->where(function($query) use ($tablaEstados, $estEjecucion) {
                        $query->whereNull($tablaEstados . '.est_ejecucion')
                            ->orWhere($tablaEstados . '.est_ejecucion', $estEjecucion);
                    });
                });
        else
            return $query->from($tablaEstados)
                ->select($colSelect)
                ->whereRaw($tablaDocs . '.cdo_id = ' . $tablaEstados . '.cdo_id')
                ->where($tablaEstados . '.est_estado', $estEstado);
    }

    /**
     * Obtiene el último estado del documento conforme a los parámetros recibidos.
     * 
     * Si el documento tiene varios estados que cumplen con las condiciones solamente se retornará el estado con el id más alto (mas reciente) que se encuentre FINALIZADO
     *
     * @param Builder $query Instancia del QueryBuilder al que se encadenará la consulta
     * @param string $tablaPrincipal Tabla principal donde nace la consulta
     * @param string $tablaSecundaria Tabla secundaria sobre la cual se hace el JOIN
     * @param string $tablaEstados Tabla de estados de los documentos
     * @param string $estEstado Estado a consultar, puede ser vacio
     * @param string $estResultado Resultado del estado a consultar, puede ser vacio
     * @param string $estEjecucion Ejecución del estato a consultar
     * @return Builder
     */
    private function relacionUltimoEstadoDocumento(Builder $query, string $tablaPrincipal, string $tablaSecundaria, string $tablaEstados, string $estEstado = '', string $estResultado = '', string $estEjecucion = ''): Builder {
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
     * Permite consultar la existencia de un estado del documento conforme a los parámetros recibidos,
     * la consulta no tiene en cuenta previos resultados del estado, sino que obtiene el más reciente.
     *
     * @param Builder $query Instancia del QueryBuilder al que se encadenará la consulta
     * @param string $tablaPrincipal Tabla principal donde nace la consulta
     * @param string $tablaSecundaria Tabla secundaria sobre la cual se hace el JOIN
     * @param string  $estEstado Estado a consultar
     * @param string  $estResultado Resultado del estado a consultar
     * @param string  $estEjecucion Estado de la ejecución del proceso
     * @return Builder
     */
    public function consultaUltimoEstadoDocumento(Builder $query, string $tablaPrincipal, string $tablaSecundaria, string $estEstado, string $estResultado = '', string $estEjecucion): Builder {
        $stringWhereRaw = '(select ' . $tablaSecundaria . '.est_id FROM ' . $tablaSecundaria . ' 
            WHERE ' . $tablaPrincipal . '.cdo_id = ' . $tablaSecundaria . '.cdo_id';

        if(!empty($estEjecucion))
            $stringWhereRaw .= ' AND ' . $tablaSecundaria . '.est_ejecucion = "' . $estEjecucion . '"';

        if(!empty($estResultado))
            $stringWhereRaw .= ' AND ' . $tablaSecundaria . '.est_resultado = "' . $estResultado . '" ';
            
        $stringWhereRaw .= ' and ' . $tablaSecundaria . '.est_id IN 
            (SELECT MAX(estado_max.est_id) FROM ' . $tablaSecundaria . ' AS estado_max';

        if(!empty($estEstado))
            $stringWhereRaw .= ' WHERE estado_max.est_estado = "' . $estEstado . '"';

        $stringWhereRaw .= ' GROUP BY estado_max.cdo_id))';

        return $query->whereRaw($stringWhereRaw);
    }

    /**
     * Permite consultar el último evento DIAN del documento conforme a los parámetros recibidos.
     * 
     * Este método solo evalúa los eventos DIAN (Aceptación Tácita, Aceptación y Rechazo)
     * para identificar cuál es el último
     *
     * @param Builder $query Instancia del QueryBuilder al que se encadenará la consulta
     * @param string $tablaPrincipal Tabla principal donde nace la consulta
     * @param string $tablaSecundaria Tabla secundaria sobre la cual se hace el JOIN
     * @param array  $estEstado Estados a consultar
     * @param array  $estResultado Resultados del estado a consultar
     * @return Builder
     */
    public function consultaUltimoEventoDianDocumento(Builder $query, string $tablaPrincipal, string $tablaSecundaria, array $estEstado, array $estResultado): Builder {
        $arrEstado    = "\"".implode("\",\"", $estEstado)."\"";
        $arrResultado = "\"".implode("\",\"", $estResultado)."\"";

        return $query->whereRaw('EXISTS 
            (
                SELECT ' . $tablaSecundaria . ' . est_id FROM ' . $tablaSecundaria . ' INNER JOIN (
                    SELECT MAX(' . $tablaSecundaria . ' . est_id) AS est_id_aggregate, ' . $tablaSecundaria . ' . cdo_id 
                    FROM ' . $tablaSecundaria . ' 
                    WHERE est_estado IN ("UBLACEPTACIONT", "ACEPTACIONT", "UBLACEPTACION", "ACEPTACION", "UBLRECHAZO", "RECHAZO") 
                    AND est_ejecucion = "FINALIZADO" 
                    GROUP BY ' . $tablaSecundaria . ' . cdo_id
                ) AS getMaximoEstadoDocumento ON getMaximoEstadoDocumento.est_id_aggregate = ' . $tablaSecundaria . ' . est_id 
                AND getMaximoEstadoDocumento.cdo_id = ' . $tablaSecundaria . ' . cdo_id 
                WHERE ' . $tablaPrincipal . ' . cdo_id = ' . $tablaSecundaria . ' . cdo_id 
                AND est_estado IN(' . $arrEstado . ') 
                AND est_resultado IN(' . $arrResultado . ')
            )');
    }

    /**
     * Permite consultar el último evento VALIDACION del documento conforme a los parámetros recibidos.
     * 
     * Este método solo evalúa los eventos VALIDACION para identificar cuál es el último
     *
     * @param Builder $query Instancia del QueryBuilder al que se encadenará la consulta
     * @param string $tablaPrincipal Tabla principal donde nace la consulta
     * @param string $tablaSecundaria Tabla secundaria sobre la cual se hace el JOIN
     * @param array  $estEstado Estados a consultar
     * @param array  $estResultado Resultados del estado a consultar
     * @return Builder
     */
    public function consultaUltimoEventoValidacionDocumento(Builder $query, string $tablaPrincipal, string $tablaSecundaria, array $estEstado, array $estResultado): Builder {
        $arrEstado    = "\"".implode("\",\"", $estEstado)."\"";
        $arrResultado = "\"".implode("\",\"", $estResultado)."\"";

        return $query->whereRaw('EXISTS 
            (
                SELECT ' . $tablaSecundaria . ' . est_id FROM ' . $tablaSecundaria . ' INNER JOIN (
                    SELECT MAX(' . $tablaSecundaria . ' . est_id) AS est_id_aggregate, ' . $tablaSecundaria . ' . cdo_id 
                    FROM ' . $tablaSecundaria . ' 
                    WHERE est_estado = "VALIDACION"
                    AND est_ejecucion = "FINALIZADO" 
                    GROUP BY ' . $tablaSecundaria . ' . cdo_id
                ) AS getMaximoEstadoDocumento ON getMaximoEstadoDocumento.est_id_aggregate = ' . $tablaSecundaria . ' . est_id 
                AND getMaximoEstadoDocumento.cdo_id = ' . $tablaSecundaria . ' . cdo_id 
                WHERE ' . $tablaPrincipal . ' . cdo_id = ' . $tablaSecundaria . ' . cdo_id 
                AND est_estado IN(' . $arrEstado . ') 
                AND est_resultado IN(' . $arrResultado . ')
            )');
    }

    /**
     * Obtiene la relación el último evento DIAN del documento.
     *  
     * Este método solo evalúa los eventos DIAN (Aceptación Tacita, Aceptación y Rechazo) para identificar cuál es el último
     *
     * @param Builder $query Instancia del QueryBuilder al que se encadenará la consulta
     * @param string $tablaPrincipal Tabla principal donde nace la consulta
     * @param string $tablaSecundaria Tabla secundaria sobre la cual se hace el JOIN
     * @param string $nombreRelacion Nombre de la relación
     * @return Builder
     */
    public function relacionUltimoEventoDianDocumento(Builder $query, string $tablaPrincipal, string $tablaSecundaria, string $nombreRelacion): Builder {
        return  $query->on($tablaPrincipal . '.cdo_id', '=', $nombreRelacion.'.cdo_id')
            ->whereRaw($nombreRelacion.'.est_id IN (select MAX(ultimo_evemto_dian_documento.est_id) FROM ' . $tablaSecundaria . ' AS ultimo_evemto_dian_documento WHERE ultimo_evemto_dian_documento.est_estado IN("UBLACEPTACIONT", "ACEPTACIONT", "UBLACEPTACION", "ACEPTACION", "UBLRECHAZO", "RECHAZO") AND ultimo_evemto_dian_documento.est_ejecucion = "FINALIZADO" GROUP BY ultimo_evemto_dian_documento.cdo_id)');
    }

    /**
     * Agrega columnas de una relación con estados, al array select de la consulta de documentos.
     *
     * @param string $prefijoRelacion Prefijo de la relación de estado de la cual se incluirán las columnas en el select
     * @return array
     */
    private function agregarColumnasRelacionesSelect(string $prefijoRelacion): array {
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
     * Devuelve el array de columnas y relaciones a retornar en una consulta.
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return array
     */
    private function columnasSelect(Request $request, \stdClass $tablasProceso): array {
        $colSelect = [
            $tablasProceso->tablaDocs . '.cdo_id',
            $tablasProceso->tablaDocs . '.cdo_origen',
            $tablasProceso->tablaDocs . '.cdo_clasificacion',
            $tablasProceso->tablaDocs . '.gtr_id',
            $tablasProceso->tablaDocs . '.tde_id',
            $tablasProceso->tablaDocs . '.top_id',
            $tablasProceso->tablaDocs . '.cdo_lote',
            $tablasProceso->tablaDocs . '.ofe_id',
            $tablasProceso->tablaDocs . '.pro_id',
            $tablasProceso->tablaDocs . '.mon_id',
            $tablasProceso->tablaDocs . '.rfa_resolucion',
            $tablasProceso->tablaDocs . '.rfa_prefijo',
            $tablasProceso->tablaDocs . '.cdo_consecutivo',
            $tablasProceso->tablaDocs . '.cdo_fecha',
            $tablasProceso->tablaDocs . '.cdo_hora',
            $tablasProceso->tablaDocs . '.cdo_vencimiento',
            $tablasProceso->tablaDocs . '.cdo_observacion',
            $tablasProceso->tablaDocs . '.cdo_valor_sin_impuestos',
            $tablasProceso->tablaDocs . '.cdo_valor_sin_impuestos_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_impuestos',
            $tablasProceso->tablaDocs . '.cdo_impuestos_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_total',
            $tablasProceso->tablaDocs . '.cdo_total_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_cargos',
            $tablasProceso->tablaDocs . '.cdo_cargos_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_descuentos',
            $tablasProceso->tablaDocs . '.cdo_descuentos_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_redondeo',
            $tablasProceso->tablaDocs . '.cdo_redondeo_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_anticipo',
            $tablasProceso->tablaDocs . '.cdo_anticipo_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_retenciones',
            $tablasProceso->tablaDocs . '.cdo_retenciones_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_retenciones_sugeridas',
            $tablasProceso->tablaDocs . '.cdo_retenciones_sugeridas_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_valor_a_pagar',
            $tablasProceso->tablaDocs . '.cdo_valor_a_pagar_moneda_extranjera',
            $tablasProceso->tablaDocs . '.cdo_cufe',
            $tablasProceso->tablaDocs . '.cdo_fecha_validacion_dian',
            $tablasProceso->tablaDocs . '.cdo_fecha_recibo_bien',
            $tablasProceso->tablaDocs . '.cdo_fecha_acuse',
            $tablasProceso->tablaDocs . '.cdo_estado',
            $tablasProceso->tablaDocs . '.cdo_fecha_estado',
            $tablasProceso->tablaDocs . '.cdo_nombre_archivos',
            $tablasProceso->tablaDocs . '.cdo_usuario_responsable',
            $tablasProceso->tablaDocs . '.usuario_creacion',
            $tablasProceso->tablaDocs . '.estado',

            $tablasProceso->tablaOfes . '.ofe_id',
            $tablasProceso->tablaOfes . '.ofe_identificacion',
            $tablasProceso->tablaOfes . '.ofe_razon_social',
            $tablasProceso->tablaOfes . '.ofe_nombre_comercial',
            $tablasProceso->tablaOfes . '.ofe_primer_apellido',
            $tablasProceso->tablaOfes . '.ofe_segundo_apellido',
            $tablasProceso->tablaOfes . '.ofe_primer_nombre',
            $tablasProceso->tablaOfes . '.ofe_otros_nombres',
            $tablasProceso->tablaOfes . '.ofe_recepcion_fnc_activo',
            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_nombre_completo'),

            $tablasProceso->tablaProveedores . '.pro_id',
            $tablasProceso->tablaProveedores . '.pro_identificacion',
            $tablasProceso->tablaProveedores . '.pro_id_personalizado',
            $tablasProceso->tablaProveedores . '.pro_razon_social',
            $tablasProceso->tablaProveedores . '.pro_nombre_comercial',
            $tablasProceso->tablaProveedores . '.pro_primer_apellido',
            $tablasProceso->tablaProveedores . '.pro_segundo_apellido',
            $tablasProceso->tablaProveedores . '.pro_primer_nombre',
            $tablasProceso->tablaProveedores . '.pro_otros_nombres',
            DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as pro_nombre_completo'),

            $tablasProceso->tablaGruposTrabajo . '.gtr_id',
            $tablasProceso->tablaGruposTrabajo . '.gtr_codigo',
            $tablasProceso->tablaGruposTrabajo . '.gtr_nombre',
            $tablasProceso->tablaGruposTrabajo . '.estado as cdo_gtr_estado',

            $tablasProceso->tablaMonedas . '.mon_id',
            $tablasProceso->tablaMonedas . '.mon_codigo',
            $tablasProceso->tablaMonedas . '.mon_descripcion',
            
            'moneda_extranjera.mon_id as mon_id_extranjera',
            'moneda_extranjera.mon_codigo as mon_codigo_extranjera',
            'moneda_extranjera.mon_descripcion as mon_descripcion_extranjera',

            $tablasProceso->tablaDatosAdicionales . '.cdo_informacion_adicional',

            DB::raw('count(' . $tablasProceso->tablaAnexos . '.cdo_id) as get_documentos_anexos_count'),

            DB::raw("CONCAT(
                '[',
                    (select GROUP_CONCAT(
                        JSON_OBJECT(
                            'gtr_id', " .  $tablasProceso->tablaGruposTrabajo . ".gtr_id,
                            'ofe_id', " .  $tablasProceso->tablaGruposTrabajo . ".ofe_id
                        )
                    ) FROM " . $tablasProceso->tablaGruposTrabajo . " WHERE " . $tablasProceso->tablaGruposTrabajo . ".ofe_id = " . $request->ofe_id . " AND " . $tablasProceso->tablaGruposTrabajo . ".estado = 'ACTIVO'),
                ']'
            ) as get_grupos_trabajo_ofe"),

            $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_id',
            $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_codigo',
            $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_descripcion',

            $tablasProceso->tablaTiposOperacion . '.top_id',
            $tablasProceso->tablaTiposOperacion . '.top_codigo',
            $tablasProceso->tablaTiposOperacion . '.top_descripcion',
        ];

        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_documento_aprobado'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_documento_aprobado_notificacion'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_documento_rechazado'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_status_en_proceso'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_estado_rdi_exitoso'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_estado_rdi_inconsistencia'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_aceptado'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_aceptado_t'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_rechazado'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_aceptado_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_aceptado_t_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_rechazado_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_transmision_erp'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_transmision_erp_exitoso'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_transmision_erp_excluido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_transmision_erp_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_opencomex_cxp_exitoso'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_opencomex_cxp_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_notificacion_acuse_recibo'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_notificacion_recibo_bien'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_notificacion_aceptacion'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_notificacion_rechazo'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_notificacion_acuse_recibo_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_notificacion_recibo_bien_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_notificacion_aceptacion_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_notificacion_rechazo_fallido'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_ultimo_estado_documento'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_ultimo_estado_validacion'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_ultimo_estado_validacion_en_proceso_pendiente'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_validacion_ultimo'));
        $colSelect = array_merge($colSelect, $this->agregarColumnasRelacionesSelect('get_ultimo_evento_dian'));

        return $colSelect;
    }

    /**
     * Filtra la resultados de la consulta conforme al parámetro de búsqueda rápida recibido en la petición.
     *
     * @param Builder $query Instancia del Query Builder en procesamiento
     * @param string $textoBuscar Texto a buscar
     * @param string $tablaDocs Nombre de la tabla de cabecera de documentos
     * @param string $tablaProveedores Nombre de la tabla de proveedores
     * @param string $tablaMonedas Nombre de la tabla de monedas
     * @return Builder
     */
    private function busquedaRapida(Builder $query, string $textoBuscar, string $tablaDocs, string $tablaProveedores, string $tablaMonedas): Builder {
        return $query->where(function ($query) use ($textoBuscar, $tablaDocs, $tablaProveedores, $tablaMonedas) {
            $query->where($tablaDocs . '.cdo_lote', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_clasificacion', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.rfa_prefijo', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_consecutivo', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_fecha', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_hora', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_total', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_origen', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_valor_a_pagar', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.estado', $textoBuscar)
                ->orWhereRaw('exists (select * from ' . $tablaProveedores . ' where ' . $tablaDocs . '.`pro_id` = ' . $tablaProveedores . '.`pro_id` and (`pro_razon_social` like ? or `pro_nombre_comercial` like ? or `pro_primer_apellido` like ? or `pro_segundo_apellido` like ? or `pro_primer_nombre` like ? or `pro_otros_nombres` like ?))', ['%' . $textoBuscar . '%', '%' . $textoBuscar . '%', '%' . $textoBuscar . '%', '%' . $textoBuscar . '%', '%' . $textoBuscar . '%', '%' . $textoBuscar . '%'])
                ->orWhereRaw("exists (select * from " . $tablaProveedores . " where " . $tablaDocs . ".`pro_id` = " . $tablaProveedores . ".`pro_id` and REPLACE(CONCAT(COALESCE(`pro_primer_nombre`, ''), ' ', COALESCE(`pro_otros_nombres`, ''), ' ', COALESCE(`pro_primer_apellido`, ''), ' ', COALESCE(`pro_segundo_apellido`, '')), '  ', ' ') like ?)", ['%' . $textoBuscar . '%'])
                ->orWhereRaw('exists (select * from ' . $tablaMonedas . ' where ' . $tablaDocs . '.`mon_id` = ' . $tablaMonedas . '.`mon_id` and `mon_codigo` like ?)', [$textoBuscar]);
        });
    }

    /**
     * Obtiene los documentos recibidos de acuerdo a los parámetros en el request.
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Collection $consulta
     */
    public function getDocumentosRecibidos(Request $request, \stdClass $tablasProceso): Collection {
        TenantTrait::GetVariablesSistemaTenant();
        
        $consulta = DB::connection($this->connection)
            ->table($tablasProceso->tablaDocs)
            ->select($this->columnasSelect($request, $tablasProceso))
            ->rightJoin($tablasProceso->tablaProveedores, function($query) use ($request, $tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.pro_id = ' . $tablasProceso->tablaProveedores . '.pro_id')
                    ->when($request->filled('transmision_opencomex'), function($query) {
                        $nitsIntegracionOpenComex = explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'));
                        $query->whereIn('pro_identificacion', $nitsIntegracionOpenComex);
                    });

                return $this->verificaRelacionUsuarioProveedor($query, $request->ofe_id, true, false, true, $tablasProceso);
            })
            ->leftJoin($tablasProceso->tablaGruposTrabajo, $tablasProceso->tablaDocs . '.gtr_id', '=', $tablasProceso->tablaGruposTrabajo . '.gtr_id')
            ->leftJoin($tablasProceso->tablaMonedas, $tablasProceso->tablaDocs . '.mon_id', '=', $tablasProceso->tablaMonedas . '.mon_id')
            ->leftJoin($tablasProceso->tablaMonedas . ' as moneda_extranjera', $tablasProceso->tablaDocs . '.mon_id_extranjera', '=', 'moneda_extranjera.mon_id')
            ->leftJoin($tablasProceso->tablaTiposDocumentosElectronicos, $tablasProceso->tablaDocs . '.tde_id', '=', $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_id')
            ->leftJoin($tablasProceso->tablaAnexos, $tablasProceso->tablaDocs . '.cdo_id', '=', $tablasProceso->tablaAnexos . '.cdo_id')
            ->leftJoin($tablasProceso->tablaDatosAdicionales, $tablasProceso->tablaDocs . '.cdo_id', '=', $tablasProceso->tablaDatosAdicionales . '.cdo_id')
            ->leftJoin($tablasProceso->tablaOfes, function($query) use ($tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.ofe_id = ' . $tablasProceso->tablaOfes . '.ofe_id')
                    ->where(function($query) use ($tablasProceso) {
                        if(!empty(auth()->user()->bdd_id_rg))
                            $query->where($tablasProceso->tablaOfes . '.bdd_id_rg', auth()->user()->bdd_id_rg);
                        else
                            $query->whereNull($tablasProceso->tablaOfes . '.bdd_id_rg');
                    });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_documento_aprobado', $tablasProceso->tablaEstados, 'GETSTATUS', 'EXITOSO', 'FINALIZADO')
                        ->where(function($query) {
                            $query->where('get_documento_aprobado.est_informacion_adicional', 'not like', '%conNotificacion%')
                                ->orWhere('get_documento_aprobado.est_informacion_adicional->conNotificacion', 'false');
                        });
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado_notificacion', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_documento_aprobado_notificacion', $tablasProceso->tablaEstados, 'GETSTATUS', 'EXITOSO', 'FINALIZADO')
                        ->where('get_documento_aprobado_notificacion.est_informacion_adicional->conNotificacion', 'true');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_documento_rechazado', $tablasProceso->tablaEstados, 'GETSTATUS', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_status_en_proceso', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_status_en_proceso', ['est_id'], 'GETSTATUS', '', 'ENPROCESO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_estado_rdi_inconsistencia', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_estado_rdi_inconsistencia', $tablasProceso->tablaEstados, 'RDI', 'EXITOSO', 'FINALIZADO')
                        ->where('get_estado_rdi_inconsistencia.est_informacion_adicional', 'like', '%inconsistencia%');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_finalizado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_finalizado', $tablasProceso->tablaEstados, 'NOTIFICACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_aceptado', $tablasProceso->tablaEstados, 'ACEPTACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado_t', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_aceptado_t', $tablasProceso->tablaEstados, 'ACEPTACIONT', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_rechazado', $tablasProceso->tablaEstados, 'RECHAZO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_aceptado_fallido', $tablasProceso->tablaEstados, 'ACEPTACION', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado_t_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_aceptado_t_fallido', $tablasProceso->tablaEstados, 'ACEPTACIONT', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_rechazado_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_rechazado_fallido', $tablasProceso->tablaEstados, 'RECHAZO', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_transmision_erp_exitoso', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_transmision_erp_exitoso', $tablasProceso->tablaEstados, 'TRANSMISIONERP', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_transmision_erp_excluido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_transmision_erp_excluido', $tablasProceso->tablaEstados, 'TRANSMISIONERP', 'EXCLUIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_transmision_erp_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_transmision_erp_fallido', $tablasProceso->tablaEstados, 'TRANSMISIONERP', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_opencomex_cxp_exitoso', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_opencomex_cxp_exitoso', $tablasProceso->tablaEstados, 'OPENCOMEXCXP', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_opencomex_cxp_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_opencomex_cxp_fallido', $tablasProceso->tablaEstados, 'OPENCOMEXCXP', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_acuse_recibo', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_acuse_recibo', $tablasProceso->tablaEstados, 'NOTACUSERECIBO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_recibo_bien', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_recibo_bien', $tablasProceso->tablaEstados, 'NOTRECIBOBIEN', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_aceptacion', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_aceptacion', $tablasProceso->tablaEstados, 'NOTACEPTACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_rechazo', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_rechazo', $tablasProceso->tablaEstados, 'NOTRECHAZO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_acuse_recibo_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_acuse_recibo_fallido', $tablasProceso->tablaEstados, 'NOTACUSERECIBO', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_recibo_bien_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_recibo_bien_fallido', $tablasProceso->tablaEstados, 'NOTRECIBOBIEN', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_aceptacion_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_aceptacion_fallido', $tablasProceso->tablaEstados, 'NOTACEPTACION', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_rechazo_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_notificacion_rechazo_fallido', $tablasProceso->tablaEstados, 'NOTRECHAZO', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ultimo_estado_documento', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_ultimo_estado_documento', $tablasProceso->tablaEstados, '', '', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ultimo_estado_validacion', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_ultimo_estado_validacion', $tablasProceso->tablaEstados, 'VALIDACION', '', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ultimo_estado_validacion_en_proceso_pendiente', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_ultimo_estado_validacion_en_proceso_pendiente', $tablasProceso->tablaEstados, 'VALIDACION', 'PENDIENTE', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_validacion_ultimo', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_validacion_ultimo', $tablasProceso->tablaEstados, 'VALIDACION', '', '');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ultimo_evento_dian', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, 'get_ultimo_evento_dian');
                });
            })
            // Relaciones necesarias para el Excel
            ->leftJoin($tablasProceso->tablaTiposOperacion, $tablasProceso->tablaDocs . '.top_id', '=', $tablasProceso->tablaTiposOperacion . '.top_id')
            ->leftJoin($tablasProceso->tablaEstados . ' as get_estado_rdi_exitoso', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_estado_rdi_exitoso', $tablasProceso->tablaEstados, 'RDI', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_transmision_erp', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_transmision_erp', $tablasProceso->tablaEstados, 'TRANSMISIONERP', '', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_recibo_bien_ultimo', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, 'get_recibo_bien_ultimo', $tablasProceso->tablaEstados, 'RECIBOBIEN', '', '');
                });
            })
            // Fin relaciones necesarias para el Excel
            ->where($tablasProceso->tablaDocs . '.ofe_id', $request->ofe_id)
            ->when($request->filled('pro_id'), function($query) use ($request, $tablasProceso) {
                if (!is_array($request->pro_id)) {
                    $arrProveedoresIds = explode(",", $request->pro_id);
                } else {
                    $arrProveedoresIds = $request->pro_id;
                }

                if(!empty($arrProveedoresIds))
                    return $query->whereIn($tablasProceso->tablaDocs . '.pro_id', $arrProveedoresIds);
            })
            ->when($request->filled('buscar'), function($query) use ($request, $tablasProceso) {
                return $this->busquedaRapida($query, $request->buscar, $tablasProceso->tablaDocs, $tablasProceso->tablaProveedores, $tablasProceso->tablaMonedas);
            })
            ->when($request->filled('ofe_filtro') && $request->filled('ofe_filtro_buscar'), function($query) use ($request, $tablasProceso) {
                switch($request->ofe_filtro){
                    default:
                        return $query->where($tablasProceso->tablaDatosAdicionales . '.cdo_informacion_adicional->' . $request->ofe_filtro, $request->ofe_filtro_buscar);
                        break;
                }
            })
            ->when($request->filled('cdo_lote'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_lote', $request->cdo_lote);
            })
            ->when($request->filled('cdo_origen'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_origen', $request->cdo_origen);
            })
            ->when((
                $request->filled('cdo_clasificacion') &&
                ($request->cdo_clasificacion == 'DS' || $request->cdo_clasificacion == 'DS_NC') &&
                ($request->filled('estado_acuse_recibo') || $request->filled('estado_recibo_bien') || $request->filled('estado'))
            ), function($query) use ($tablasProceso) {
                return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['FC','NC','ND']);
            })
            ->when((
                $request->filled('cdo_clasificacion') &&
                ($request->cdo_clasificacion !== 'DS' && $request->cdo_clasificacion !== 'DS_NC') &&
                (
                    ($request->filled('estado_acuse_recibo') || $request->filled('estado_recibo_bien') || $request->filled('estado')) ||
                    (!$request->filled('estado_acuse_recibo') && !$request->filled('estado_recibo_bien') && !$request->filled('estado'))
                )
            ), function($query) use ($tablasProceso, $request) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_clasificacion', $request->cdo_clasificacion);
            })
            ->when((
                !$request->filled('cdo_clasificacion') &&
                ($request->filled('estado_acuse_recibo') || $request->filled('estado_recibo_bien') || $request->filled('estado'))
            ), function($query) use ($tablasProceso) {
                return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['FC','NC','ND']);
            })
            ->when((
                $request->filled('cdo_clasificacion') && ($request->cdo_clasificacion == 'DS' || $request->cdo_clasificacion == 'DS_NC') && (!$request->filled('estado_acuse_recibo') && !$request->filled('estado_recibo_bien') && !$request->filled('estado'))
            ), function($query) use ($tablasProceso) {
                return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['DS','DS_NC']);
            })
            ->when(($request->filled('rfa_prefijo')), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('cdo_fecha_desde') && $request->filled('cdo_fecha_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha', [$request->cdo_fecha_desde, $request->cdo_fecha_hasta]);
            })
            ->when($request->filled('cdo_fecha_validacion_dian_desde') && $request->filled('cdo_fecha_validacion_dian_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha_validacion_dian', [$request->cdo_fecha_validacion_dian_desde . ' 00:00:00', $request->cdo_fecha_validacion_dian_hasta . ' 23:59:59']);
            })
            ->when($request->filled('estado'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.estado', $request->estado);
            })
            ->when($request->filled('forma_pago'), function($query) use ($tablasProceso) {
                return $query->where(function($query) use ($tablasProceso) {
                    $query->whereExists(function($query) use ($tablasProceso) {
                        return $query->from($tablasProceso->tablaMediosPagoDocs)
                            ->whereRaw($tablasProceso->tablaDocs . '.cdo_id = ' . $tablasProceso->tablaMediosPagoDocs . '.cdo_id')
                            ->whereIn($tablasProceso->tablaMediosPagoDocs . '.fpa_id', $tablasProceso->idsFormasPago);
                    });
                });
            })
            ->when($request->filled('estado_dian'), function($query) use ($request, $tablasProceso) {
                return $query->where(function($query) use ($request, $tablasProceso) {
                    return $query->when(is_array($request->estado_dian) && in_array('en_proceso', $request->estado_dian), function($query) use ($tablasProceso) {
                        $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, 'GETSTATUS', '', 'ENPROCESO');
                        });
                    })
                    ->when(is_array($request->estado_dian) && in_array('aprobado', $request->estado_dian), function($query) use ($tablasProceso) {
                        return $query->orWhereExists(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, 'GETSTATUS', 'EXITOSO', 'FINALIZADO')
                                ->where($tablasProceso->tablaEstados . '.est_informacion_adicional', 'not like', '%conNotificacion%')
                                ->orWhere($tablasProceso->tablaEstados . '.est_informacion_adicional->conNotificacion', 'false');
                        });
                    })
                    ->when(is_array($request->estado_dian) && in_array('aprobado_con_notificacion', $request->estado_dian), function($query) use ($tablasProceso) {
                        return $query->orWhereExists(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, 'GETSTATUS', 'EXITOSO', 'FINALIZADO')
                                ->where($tablasProceso->tablaEstados . '.est_informacion_adicional->conNotificacion', 'true');
                        });
                    })
                    ->when(is_array($request->estado_dian) && in_array('rechazado', $request->estado_dian), function($query) use ($tablasProceso) {
                        return $query->orWhereExists(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEstadoDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, 'GETSTATUS', 'FALLIDO', 'FINALIZADO');
                        });
                    });
                });
            })
            ->when($request->filled('estado_acuse_recibo'), function($query) use ($request, $tablasProceso) {
                $query->when($request->estado_acuse_recibo == "SI", function ($query) use ($tablasProceso) {
                    return $query->where(function($query) use ($tablasProceso) {
                        $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACUSERECIBO', 'EXITOSO', 'FINALIZADO');
                        });
                    });
                }, function ($query) use ($tablasProceso) {
                    return $query->where(function($query) use ($tablasProceso) {
                        $query->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACUSERECIBO', 'EXITOSO', 'FINALIZADO');
                        });
                    });
                });
            })
            ->when($request->filled('estado_recibo_bien'), function($query) use ($request, $tablasProceso) {
                $query->when($request->estado_recibo_bien == "SI", function ($query) use ($tablasProceso) {
                    return $query->where(function($query) use ($tablasProceso) {
                        $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'RECIBOBIEN', 'EXITOSO', 'FINALIZADO');
                        });
                    });
                }, function ($query) use ($tablasProceso) {
                    return $query->where(function($query) use ($tablasProceso) {
                        $query->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'RECIBOBIEN', 'EXITOSO', 'FINALIZADO');
                        });
                    });
                });
            })
            ->when($request->filled('estado_eventos_dian'), function($query) use ($request, $tablasProceso) {
                return $query->where(function($query) use ($request, $tablasProceso) {
                    $query->when(in_array('aceptado_tacitamente', $request->estado_eventos_dian) && $request->filled('resEventosDian') && $request->resEventosDian == "exitoso", function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["ACEPTACIONT"], ["EXITOSO"]);
                        });
                    })
                    ->when(in_array('aceptado_tacitamente', $request->estado_eventos_dian) && $request->filled('resEventosDian') && $request->resEventosDian == "fallido",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["ACEPTACIONT"], ["FALLIDO"]);
                        });
                    })
                    ->when(in_array('aceptado_tacitamente', $request->estado_eventos_dian) && !$request->filled('resEventosDian'),function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["UBLACEPTACIONT", "ACEPTACIONT"], ["EXITOSO", "FALLIDO"]);
                        });
                    })
                    ->when(in_array('aceptacion_expresa', $request->estado_eventos_dian) && $request->filled('resEventosDian') && $request->resEventosDian == "exitoso",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["ACEPTACION"], ["EXITOSO"]);
                        });
                    })
                    ->when(in_array('aceptacion_expresa', $request->estado_eventos_dian) && $request->filled('resEventosDian') && $request->resEventosDian == "fallido",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["ACEPTACION"], ["FALLIDO"]);
                        });
                    })
                    ->when(in_array('aceptacion_expresa', $request->estado_eventos_dian) && !$request->filled('resEventosDian'),function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["UBLACEPTACION", "ACEPTACION"], ["EXITOSO", "FALLIDO"]);
                        });
                    })
                    ->when(in_array('reclamo_rechazo', $request->estado_eventos_dian) && $request->filled('resEventosDian') && $request->resEventosDian == "exitoso",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["RECHAZO"], ["EXITOSO"]);
                        });
                    })
                    ->when(in_array('reclamo_rechazo', $request->estado_eventos_dian) && $request->filled('resEventosDian') && $request->resEventosDian == "fallido",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["RECHAZO"], ["FALLIDO"]);
                        });
                    })
                    ->when(in_array('reclamo_rechazo', $request->estado_eventos_dian) && !$request->filled('resEventosDian'),function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["UBLRECHAZO", "RECHAZO"], ["EXITOSO", "FALLIDO"]);
                        });
                    })
                    ->when(in_array('sin_estado', $request->estado_eventos_dian),function($query) use ($tablasProceso) {
                        return $query->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACEPTACION', 'EXITOSO', '');
                        })->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'RECHAZO', 'EXITOSO', '');
                        })->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACEPTACIONT', 'EXITOSO', '');
                        })->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'RECIBOBIEN', 'EXITOSO', '');
                        })->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACUSERECIBO', 'EXITOSO', '');
                        });
                    });
                });
            })
            ->when($request->filled('estado_validacion'), function($query) use ($request, $tablasProceso) {
                return $query->where(function($query) use ($request, $tablasProceso) {
                    $query->when(in_array('pendiente', $request->estado_validacion), function($query) use ($tablasProceso) {
                        return $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoValidacionDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["VALIDACION"], ["PENDIENTE"]);
                        });
                    })->when(in_array('validado', $request->estado_validacion), function($query) use ($tablasProceso) {
                        return $query->orWhereExists(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoValidacionDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["VALIDACION"], ["VALIDADO"]);
                        });
                    })->when(in_array('rechazado', $request->estado_validacion), function($query) use ($tablasProceso) {
                        return $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoValidacionDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["VALIDACION"], ["RECHAZADO"]);
                        });
                    })->when(in_array('pagado', $request->estado_validacion), function($query) use ($tablasProceso) {
                        return $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoValidacionDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["VALIDACION"], ["PAGADO"]);
                        });
                    });
                });
            })
            ->when($request->filled('transmision_erp'), function($query) use ($request, $tablasProceso) {
                return $query->where(function($query) use ($request, $tablasProceso) {
                    $query->when(in_array('exitoso', $request->transmision_erp), function($query) use ($tablasProceso) {
                        return $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'TRANSMISIONERP', 'EXITOSO', 'FINALIZADO');
                        });
                    })
                    ->when(in_array('fallido', $request->transmision_erp), function($query) use ($tablasProceso) {
                        return $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'TRANSMISIONERP', 'FALLIDO', 'FINALIZADO');
                        });
                    })
                    ->when(in_array('sin_estado', $request->transmision_erp), function($query) use ($tablasProceso) {
                        return $query->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'TRANSMISIONERP', 'EXITOSO', 'FINALIZADO');
                        })
                        ->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'TRANSMISIONERP', 'FALLIDO', 'FINALIZADO');
                        });
                    });
                });
            })
            ->when($request->filled('transmision_opencomex'), function($query) use ($request, $tablasProceso) {
                return $query->where(function($query) use ($request, $tablasProceso) {
                    $query->when($request->transmision_opencomex == 'exitoso', function($query) use ($tablasProceso) {
                        return $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'OPENCOMEXCXP', 'EXITOSO', 'FINALIZADO');
                        });
                    })
                    ->when($request->transmision_opencomex == 'fallido', function($query) use ($tablasProceso) {
                        return $query->whereExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'OPENCOMEXCXP', 'FALLIDO', 'FINALIZADO');
                        });
                    })
                    ->when($request->transmision_opencomex == 'sin_estado', function($query) use ($tablasProceso) {
                        return $query->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'OPENCOMEXCXP', 'EXITOSO', 'FINALIZADO');
                        })
                        ->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'OPENCOMEXCXP', 'FALLIDO', 'FINALIZADO');
                        });
                    });
                });
            })
            ->where(function($query) use ($request, $tablasProceso) {
                $gruposTrabajoUsuario = $this->getGruposTrabajoUsuarioAutenticado($tablasProceso->requestOfe, true, false);
                $gruposTrabajoUsuario = (empty($gruposTrabajoUsuario)) ? [0] : $gruposTrabajoUsuario; 
                return $query->when($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo == 'unico' && $tablasProceso->requestOfe->getGruposTrabajo->count() > 0, function ($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                    return $query->where(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                        $query->whereExists(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                                $query->whereRaw('EXISTS 
                                    (
                                        SELECT * FROM ' . $tablasProceso->tablaGruposTrabajo . '
                                        WHERE ' . $tablasProceso->tablaDocs . '.gtr_id = ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id
                                        AND gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                                        AND estado = "ACTIVO"
                                    )');
                            })->orWhereExists(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                                $query->whereRaw('EXISTS 
                                    (
                                        SELECT * FROM ' . $tablasProceso->tablaProveedores . '
                                        WHERE ' . $tablasProceso->tablaDocs . '.pro_id = ' . $tablasProceso->tablaProveedores . '.pro_id
                                        AND (
                                            SELECT COUNT(*) FROM ' . $tablasProceso->tablaGruposTrabajoProveedor . ' where ' . $tablasProceso->tablaProveedores . '.pro_id = ' . $tablasProceso->tablaGruposTrabajoProveedor . '.pro_id
                                            AND gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                                            AND `estado` = "ACTIVO"
                                        ) = 1
                                    )'
                                );
                            });
                    });
                })
                ->when($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo == 'compartido' && $tablasProceso->requestOfe->getGruposTrabajo->count() > 0, function ($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                    return $query->where(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                        $query->whereExists(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                            $query->whereRaw('NOT EXISTS 
                                (
                                    SELECT * FROM ' . $tablasProceso->tablaGruposTrabajo . '
                                    WHERE ' . $tablasProceso->tablaDocs . '.gtr_id = ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id
                                ) OR EXISTS (
                                    SELECT * FROM ' . $tablasProceso->tablaGruposTrabajo . '
                                    WHERE ' . $tablasProceso->tablaDocs . '.gtr_id = ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id
                                    AND ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                                    AND ' . $tablasProceso->tablaGruposTrabajo . '.estado = "INACTIVO"
                                )');
                            })
                            ->whereExists(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                                $query->whereRaw('EXISTS 
                                    (
                                        SELECT * FROM ' . $tablasProceso->tablaProveedores . '
                                        WHERE ' . $tablasProceso->tablaDocs . '.pro_id = ' . $tablasProceso->tablaProveedores . '.pro_id
                                        AND (
                                            SELECT COUNT(*) FROM ' . $tablasProceso->tablaGruposTrabajoProveedor . ' where ' . $tablasProceso->tablaProveedores . '.pro_id = ' . $tablasProceso->tablaGruposTrabajoProveedor . '.pro_id
                                            AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                                            AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.estado = "ACTIVO"
                                        ) > 1
                                    )'
                                );
                            });
                    });
                })
                ->when(
                    (
                        !$request->filled('filtro_grupos_trabajo') || 
                        ($request->filled('filtro_grupos_trabajo') && $request->filtro_grupos_trabajo != 'unico' && $request->filtro_grupos_trabajo != 'compartido')
                    ) && 
                    $tablasProceso->requestOfe->getGruposTrabajo->count() > 0, function ($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                    return $query->where(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                        $query->whereExists(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                            $query->whereRaw('EXISTS 
                                (
                                    SELECT * FROM ' . $tablasProceso->tablaGruposTrabajo . '
                                    WHERE ' . $tablasProceso->tablaDocs . '.gtr_id = ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id
                                    AND ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                                    AND ' . $tablasProceso->tablaGruposTrabajo . '.estado = "ACTIVO"
                                )');
                            })->orWhereExists(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                                $query->whereRaw('EXISTS 
                                    (
                                        SELECT * FROM ' . $tablasProceso->tablaProveedores . '
                                        WHERE ' . $tablasProceso->tablaDocs . '.pro_id = ' . $tablasProceso->tablaProveedores . '.pro_id
                                        AND (
                                            SELECT COUNT(*) FROM ' . $tablasProceso->tablaGruposTrabajoProveedor . ' where ' . $tablasProceso->tablaProveedores . '.pro_id = ' . $tablasProceso->tablaGruposTrabajoProveedor . '.pro_id
                                            AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                                            AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.estado = "ACTIVO"
                                        ) = 1
                                    )'
                                );
                            });
                    })->orWhere(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                        $query->whereExists(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                            $query->whereRaw('NOT EXISTS 
                                (
                                    SELECT * FROM ' . $tablasProceso->tablaGruposTrabajo . '
                                    WHERE ' . $tablasProceso->tablaDocs . '.gtr_id = ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id
                                ) OR EXISTS (
                                    SELECT * FROM ' . $tablasProceso->tablaGruposTrabajo . '
                                    WHERE ' . $tablasProceso->tablaDocs . '.gtr_id = ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id
                                    AND ' . $tablasProceso->tablaGruposTrabajo . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                                    AND ' . $tablasProceso->tablaGruposTrabajo . '.estado = "INACTIVO"
                                )');
                            })
                            ->whereExists(function($query) use ($tablasProceso, $gruposTrabajoUsuario) {
                                $query->whereRaw('EXISTS 
                                    (
                                        SELECT * FROM ' . $tablasProceso->tablaProveedores . '
                                        WHERE ' . $tablasProceso->tablaDocs . '.pro_id = ' . $tablasProceso->tablaProveedores . '.pro_id
                                        AND (
                                            SELECT COUNT(*) FROM ' . $tablasProceso->tablaGruposTrabajoProveedor . ' where ' . $tablasProceso->tablaProveedores . '.pro_id = ' . $tablasProceso->tablaGruposTrabajoProveedor . '.pro_id
                                            AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                                            AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.estado = "ACTIVO"
                                        ) > 1
                                    )'
                                );
                            });
                    });
                });
            })
            ->groupBy($tablasProceso->tablaDocs . '.cdo_id')
            ->get();

        return $consulta;
    }

    /**
     * Realiza una búsqueda en la tabla FAT de recepción mediante un campo y valor.
     * 
     * Tener en cuenta que en la tabla FAT solo se registra información de documentos en recepción que ha pasado al histórico de particionamiento
     *
     * @param string $campoBuscar Campo sobre el cual realizar la búsqueda
     * @param string $valorBuscar Valor a buscar
     * @return array
     */
    public function busquedaFatByCampoValor(string $campoBuscar, string $valorBuscar): array {
        $select = [DB::raw('DISTINCT(cdo_lote) as cdo_lote')];
        $objDocumentos = RepFatDocumentoDaop::select($select);

        if($campoBuscar == 'rfa_prefijo' && ($valorBuscar == '' || $valorBuscar == null || $valorBuscar == 'null')) {
            $objDocumentos->where(function($query) {
                $query->whereNull('rfa_prefijo')
                    ->orWhere('rfa_prefijo', '');
            });
        } elseif($campoBuscar == 'rfa_prefijo' && ($valorBuscar != '' && $valorBuscar != null && $valorBuscar != 'null')) {
            $objDocumentos->where('rfa_prefijo', $valorBuscar);
        } else {
            $objDocumentos->where($campoBuscar, 'like', '%' . $valorBuscar . '%');
        }

        $documentos = [];
        $objDocumentos->whereNotNull('cdo_lote')
            ->get()
            ->map(function ($item) use (&$documentos) {
                $select = [
                    DB::raw('COUNT(*) as cantidad_documentos,
                    MIN(cdo_consecutivo) as consecutivo_inicial,
                    MAX(cdo_consecutivo) as consecutivo_final')
                ];

                $objDocumentos = RepFatDocumentoDaop::select($select)
                    ->where('cdo_lote', $item->cdo_lote)
                    ->first();

                $documentos[] = [
                    'cdo_lote'            => $item->cdo_lote,
                    'cantidad_documentos' => $objDocumentos->cantidad_documentos,
                    'consecutivo_inicial' => $objDocumentos->consecutivo_inicial,
                    'consecutivo_final'   => $objDocumentos->consecutivo_final,
                ];
            });

        return $documentos;
    }

    /**
     * Verifica si un documento existe en la tabla FAT de recepción, realizando la consulta mediante el CUFE y la fecha del documento.
     *
     * @param string $cdo_cufe CUFE dle documento
     * @param string $cdo_fecha Fecha del documento
     * @return null|RepFatDocumentoDaop
     */
    public function consultarDocumentoFatByCufe(string $cdo_cufe, string $cdo_fecha) {
        $documento = RepFatDocumentoDaop::select([
                'cdo_id',
                'ofe_id',
                'pro_id',
                'tde_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha'
            ])
            ->where('cdo_cufe', $cdo_cufe)
            ->where('cdo_fecha', $cdo_fecha)
            ->first();

        if(!$documento)
            return null;

        return $documento;
    }

    /**
     * Verifica si un documento existe en la tabla FAT de recepción.
     *
     * @param int $ofe_id ID del OFE
     * @param int $pro_id ID del Proveedor
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @param int $tde_id ID del tipo de documento electrónico
     * @return null|RepFatDocumentoDaop
     */
    public function consultarDocumentoFat(int $ofe_id, int $pro_id = 0, string $prefijo, string $consecutivo, int $tde_id = 0) {
        $documento = RepFatDocumentoDaop::select([
                'cdo_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha'
            ])
            ->where('ofe_id', $ofe_id)
            ->when($pro_id != 0, function($query) use ($pro_id) {
                return $query->where('pro_id', $pro_id);
            })
            ->where('cdo_consecutivo', $consecutivo)
            ->when($tde_id != 0, function($query) use ($tde_id) {
                return $query->where('tde_id', $tde_id);
            });

        if($prefijo != '' && $prefijo != 'null' && $prefijo != null)
            $documento = $documento->where('rfa_prefijo', trim($prefijo));
        else
            $documento = $documento->where(function($query) {
                $query->whereNull('rfa_prefijo')
                    ->orWhere('rfa_prefijo', '');
            });
            
        $documento = $documento->first();

        if(!$documento)
            return null;

        return $documento;
    }

    /**
     * Verifica si un documento existe en la tabla FAT de recepción mediante el cdo_id.
     *
     * @param int $cdo_id ID del documento
     * @return null|RepFatDocumentoDaop
     */
    public function consultarDocumentoFatByCdoId(int $cdo_id) {
        $documento = RepFatDocumentoDaop::select([
                'cdo_id',
                'ofe_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha'
            ])
            ->where('cdo_id', $cdo_id)
            ->first();

        if(!$documento)
            return null;

        return $documento;
    }

    /**
     * Ejecuta la consulta de los datos de un documento y el último estado exitoso en la data histórica teniendo en cuenta la partición en la que se encuentra el documento.
     *
     * @param string $particion Partición en la que se encuentra el documento
     * @param int $ofe_id ID del OFE
     * @param int $pro_id ID del Proveedor
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @param int $tde_id ID del tipo de documento electrónico
     * @param array $relaciones Array con los nombres de las relaciones que deben ser consultadas y retornadas con el documento
     * @return null|RepCabeceraDocumentoDaop
     */
    public function consultarDocumentoHistorico(string $particion, int $ofe_id, int $pro_id = 0, string $prefijo, string $consecutivo, int $tde_id = 0, array $relaciones = []) {
        if(!$this->existeTabla($this->connection, 'rep_cabecera_documentos_' . $particion) || !$this->existeTabla($this->connection, 'rep_estados_documentos_' . $particion))
            return null;

        $selectCabecera = [
            'cdo_id',
            'pro_id',
            'cdo_clasificacion',
            'rfa_prefijo',
            'rfa_resolucion',
            'cdo_consecutivo',
            'cdo_fecha',
            'cdo_hora',
            'cdo_cufe',
            'cdo_qr',
            'cdo_signaturevalue',
            'cdo_fecha_validacion_dian',
            'cdo_nombre_archivos',
            'cdo_fecha_acuse',
            'cdo_estado',
            'cdo_fecha_estado',
            'fecha_creacion',
            'estado'
        ];

        if(in_array('getConfiguracionProveedor', $relaciones)) {
            $index = array_search('getConfiguracionProveedor', $relaciones);
            unset($relaciones[$index]);

            $relaciones['getConfiguracionProveedor'] = function($query) use ($ofe_id) {
                $query = $this->verificaRelacionUsuarioProveedor($query, $ofe_id);
            };
        }

        $relacionesCabecera = array_merge($relaciones, [
            'getParametrosMoneda',
            'getParametrosMonedaExtranjera',
            'getTipoOperacion',
            'getTipoDocumentoElectronico'
        ]);

        $selectOfe = [
            'ofe_id',
            'ofe_identificacion',
            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
        ];

        $tblCabecera = new RepCabeceraDocumentoDaop();
        $tblCabecera->setTable('rep_cabecera_documentos_' . $particion);

        $documento = $tblCabecera->select((!empty($selectCabecera) ? $selectCabecera : '*'))
            ->with($relacionesCabecera)
            ->whereHas('getConfiguracionProveedor', function($query) use ($ofe_id) {
                $query = $this->verificaRelacionUsuarioProveedor($query, $ofe_id);
            })
            ->where('ofe_id', $ofe_id)
            ->when($pro_id != 0, function($query) use ($pro_id) {
                return $query->where('pro_id', $pro_id);
            })
            ->where('cdo_consecutivo', $consecutivo)
            ->when($tde_id != 0, function($query) use ($tde_id) {
                return $query->where('tde_id', $tde_id);
            });

        if($prefijo != '' && $prefijo != 'null' && $prefijo != null)
            $documento = $documento->where('rfa_prefijo', trim($prefijo));
        else
            $documento = $documento->where(function($query) {
                $query->whereNull('rfa_prefijo')
                    ->orWhere('rfa_prefijo', '');
            });
            
        $documento = $documento->first();

        if(!$documento)
            return null;

        if(in_array('getConfiguracionObligadoFacturarElectronicamente.getParametrosPais', $relaciones))
            $selectOfe[] = 'pai_id';

        if(in_array('getConfiguracionObligadoFacturarElectronicamente.getParametrosDepartamento', $relaciones))
            $selectOfe[] = 'dep_id';

        if(in_array('getConfiguracionObligadoFacturarElectronicamente.getParametrosMunicipio', $relaciones))
            $selectOfe[] = 'mun_id';

        if(in_array('getConfiguracionObligadoFacturarElectronicamente.getParametrosRegimenFiscal', $relaciones))
            $selectOfe[] = 'rfi_id';

        $oferente = ConfiguracionObligadoFacturarElectronicamente::select((!empty($selectOfe) ? $selectOfe : '*'))
            ->where('ofe_id', $ofe_id)
            ->when(in_array('getConfiguracionObligadoFacturarElectronicamente.getParametrosPais', $relaciones), function ($query) {
                return $query->with('getParametrosPais');
            })
            ->when(in_array('getConfiguracionObligadoFacturarElectronicamente.getParametrosDepartamento', $relaciones), function ($query) {
                return $query->with('getParametrosDepartamento');
            })
            ->when(in_array('getConfiguracionObligadoFacturarElectronicamente.getParametrosMunicipio', $relaciones), function ($query) {
                return $query->with('getParametrosMunicipio');
            })
            ->when(in_array('getConfiguracionObligadoFacturarElectronicamente.getParametrosRegimenFiscal', $relaciones), function ($query) {
                return $query->with('getParametrosRegimenFiscal');
            })
            ->first();

        $selectPro = [
            'pro_id',
            'pro_identificacion',
            DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
        ];

        if(in_array('getConfiguracionProveedor.getParametroPais', $relaciones))
            $selectPro[] = 'pai_id';

        if(in_array('getConfiguracionProveedor.getParametroDepartamento', $relaciones))
            $selectPro[] = 'dep_id';

        if(in_array('getConfiguracionProveedor.getParametroMunicipio', $relaciones))
            $selectPro[] = 'mun_id';

        $proveedor = ConfiguracionProveedor::select($selectPro)
            ->when(in_array('getConfiguracionProveedor.getParametroPais', $relaciones), function ($query) {
                return $query->with('getParametroPais');
            })
            ->when(in_array('getConfiguracionProveedor.getParametroDepartamento', $relaciones), function ($query) {
                return $query->with('getParametroDepartamento');
            })
            ->when(in_array('getConfiguracionProveedor.getParametroMunicipio', $relaciones), function ($query) {
                return $query->with('getParametroMunicipio');
            })
            ->where('pro_id', $documento->pro_id)
            ->first();

        if(!$proveedor)
            return null;

        $documento->getConfiguracionObligadoFacturarElectronicamente = $oferente;
        $documento->getConfiguracionProveedor                        = $proveedor;

        if(in_array('getDadDocumentosDaop', $relaciones)) {
            $tblDadDocumentos = new RepDatoAdicionalDocumentoDaop;
            $tblDadDocumentos->setTable('rep_datos_adicionales_documentos_' . $particion);

            $getDadDocumentosDaop = $tblDadDocumentos->where('cdo_id', $documento->cdo_id)
                ->get();

            $documento->getDadDocumentosDaop = $getDadDocumentosDaop;
        }

        if(in_array('getEstadosDocumentosDaop', $relaciones)) {
            $tblEstados = new RepEstadoDocumentoDaop;
            $tblEstados->setTable('rep_estados_documentos_' . $particion);

            $getEstadosDocumentosDaop = $tblEstados->select(['est_id','cdo_id','est_estado','est_resultado','est_mensaje_resultado','est_correos','est_object','est_informacion_adicional','est_ejecucion','est_inicio_proceso','fecha_creacion'])
                ->where('cdo_id', $documento->cdo_id)
                ->orderBy('est_id', 'desc')
                ->get();

            $documento->getEstadosDocumentosDaop = $getEstadosDocumentosDaop;
        }

        if(in_array('getUltimoEstadoDocumento', $relaciones)) {
            $tblEstados = new RepEstadoDocumentoDaop;
            $tblEstados->setTable('rep_estados_documentos_' . $particion);

            $getUltimoEstadoDocumento = $tblEstados->select(['est_id','cdo_id','est_estado','est_resultado','est_mensaje_resultado','est_correos','est_object','est_informacion_adicional','est_ejecucion','est_inicio_proceso','fecha_creacion'])
                ->where('cdo_id', $documento->cdo_id)
                ->where('est_ejecucion', 'FINALIZADO')
                ->orderBy('est_id', 'desc')
                ->first();

            $documento->getUltimoEstadoDocumento = $getUltimoEstadoDocumento;
        }

        if(in_array('getMediosPagoDocumentosDaop', $relaciones)) {
            $tblMediosPago = new RepMediosPagoDocumentoDaop;
            $tblMediosPago->setTable('rep_medios_pago_documentos_' . $particion);

            $getMediosPagoDocumentosDaop = $tblMediosPago->select(['men_id', 'cdo_id', 'fpa_id', 'mpa_id', 'men_fecha_vencimiento', 'men_identificador_pago'])
                ->where('cdo_id', $documento->cdo_id)
                ->when($procesoOrigen == 'documentos-referencia', function ($query) {
                    return $query->with([
                        'getMedioPago:mpa_id,mpa_codigo',
                        'getFormaPago:fpa_id,fpa_codigo'
                    ]);
                })
                ->orderBy('fecha_creacion', 'asc')
                ->get();

            $documento->getMediosPagoDocumentosDaop = $getMediosPagoDocumentosDaop;
        }
        
        return $documento;
    }

    /**
     * Obtiene un documento anexo relacionado con un documento electrónico que pertenece a la data histórica.
     *
     * @param RepCabeceraDocumentoDaop $documento Documento en tabla FAT
     * @param string $particion Partición en donde se encuentra el documento
     * @param int $dan_id ID del documento anexo
     * @return null|\stdObject
     */
    public function obtenerDocumentoAnexoHistorico(RepCabeceraDocumentoDaop $documento, string $particion, int $dan_id) {
        $tblAnexos = new RepDocumentoAnexoDaop;
        $tblAnexos->setTable('rep_documentos_anexos_' . $particion);

        return $tblAnexos->where('cdo_id', $documento->cdo_id)
            ->where('dan_id', $dan_id)
            ->first();
    }

    /**
     * Elimina un documento anexo relacionado con un documento electrónico que pertenece a la data histórica.
     *
     * @param string $particion Partición en donde se encuentra el documento
     * @param int $cdo_id ID del documento electrónico
     * @param int $dan_id ID del documento anexo
     * @return void
     */
    public function eliminarDocumentoAnexoHistorico(string $particion, int $cdo_id, int $dan_id): void {
        $tblAnexos = new RepDocumentoAnexoDaop;
        $tblAnexos->setTable('rep_documentos_anexos_' . $particion);

        $tblAnexos->where('cdo_id', $cdo_id)
            ->where('dan_id', $dan_id)
            ->delete();
    }

    /**
     * Obtiene una lista de documentos recibidos de acuerdo a los parámetros en el request.
     * 
     * Proceso de consulta relacionado con el endpoint listar-documentos de recepción
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Collection $consulta
     */
    public function listarDocumentosRecibidos(Request $request, \stdClass $tablasProceso): Collection {
        TenantTrait::GetVariablesSistemaTenant();
        
        // Válida si la fecha desde y fecha hasta del request tienen la hora para consultar la data sobre la fecha y hora enviada
        $arrFechaDesde = explode(' ', $request->fecha_desde);
        $arrFechaHasta = explode(' ', $request->fecha_hasta);
        $fechaDesde    = (array_key_exists(1, $arrFechaDesde) && strtotime($arrFechaDesde[1]) !== false) ? $request->fecha_desde : $request->fecha_desde . ' 00:00:00';
        $fechaHasta    = (array_key_exists(1, $arrFechaHasta) && strtotime($arrFechaHasta[1]) !== false) ? $request->fecha_hasta : $request->fecha_hasta . ' 23:59:59';

        $consulta = DB::connection($this->connection)
            ->table($tablasProceso->tablaDocs)
            ->select([
                $tablasProceso->tablaDocs . '.cdo_id',
                $tablasProceso->tablaDocs . '.tde_id',
                $tablasProceso->tablaDocs . '.ofe_id',
                $tablasProceso->tablaDocs . '.pro_id',
                $tablasProceso->tablaDocs . '.rfa_prefijo',
                $tablasProceso->tablaDocs . '.cdo_consecutivo',
                $tablasProceso->tablaDocs . '.cdo_fecha',
                $tablasProceso->tablaDocs . '.cdo_hora',
                $tablasProceso->tablaDocs . '.cdo_cufe',
    
                $tablasProceso->tablaOfes . '.ofe_id',
                $tablasProceso->tablaOfes . '.ofe_identificacion',
    
                $tablasProceso->tablaProveedores . '.pro_id',
                $tablasProceso->tablaProveedores . '.pro_identificacion',

                $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_id',
                $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_codigo',
            ])
            ->rightJoin($tablasProceso->tablaOfes, function($query) use ($tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.ofe_id = ' . $tablasProceso->tablaOfes . '.ofe_id')
                    ->where(function($query) use ($tablasProceso) {
                        if(!empty(auth()->user()->bdd_id_rg))
                            $query->where($tablasProceso->tablaOfes . '.bdd_id_rg', auth()->user()->bdd_id_rg);
                        else
                            $query->whereNull($tablasProceso->tablaOfes . '.bdd_id_rg');
                    });
            })
            ->rightJoin($tablasProceso->tablaProveedores, $tablasProceso->tablaDocs . '.pro_id', '=', $tablasProceso->tablaProveedores . '.pro_id')
            ->leftJoin($tablasProceso->tablaTiposDocumentosElectronicos, $tablasProceso->tablaDocs . '.tde_id', '=', $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_id')
            ->where($tablasProceso->tablaDocs . '.estado', 'ACTIVO')
            ->whereBetween($tablasProceso->tablaDocs . '.fecha_creacion', [$fechaDesde, $fechaHasta])
            ->when($request->filled('ofe'), function ($query) use ($request, $tablasProceso) {
                return $query->whereRaw("exists (select * from " . $tablasProceso->tablaOfes . " where " . $tablasProceso->tablaDocs . ".`ofe_id` = " . $tablasProceso->tablaOfes . ".`ofe_id` and " . $tablasProceso->tablaOfes .".ofe_identificacion = '" . $request->ofe . "')");
            })
            ->when($request->filled('proveedor'), function ($query) use ($request, $tablasProceso) {
                return $query->whereRaw("exists (select * from " . $tablasProceso->tablaProveedores . " where " . $tablasProceso->tablaDocs . ".`pro_id` = " . $tablasProceso->tablaProveedores . ".`pro_id` and " . $tablasProceso->tablaProveedores .".pro_identificacion = '" . $request->proveedor . "')");
            });
            // dump($consulta->toSql());
            // dump($consulta->getBindings());
            $consulta = $consulta->get();

        return $consulta;
    }
}
