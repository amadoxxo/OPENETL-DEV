<?php
namespace App\Repositories\Emision;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirente;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDocumentosAnexosDaop\EtlDocumentoAnexoDaop;
use App\Http\Modulos\Documentos\EtlDetalleDocumentosDaop\EtlDetalleDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlAnticiposDocumentosDaop\EtlAnticiposDocumentoDaop;
use App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop\EtlMediosPagoDocumentoDaop;
use App\Http\Modulos\Documentos\EtlImpuestosItemsDocumentosDaop\EtlImpuestosItemsDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCargosDescuentosDocumentosDaop\EtlCargosDescuentosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlDatosAdicionalesDocumentosDaop\EtlDatosAdicionalesDocumentoDaop;
use App\Http\Modulos\Documentos\EtlEventosNotificacionDocumentosDaop\EtlEventoNotificacionDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class EtlCabeceraDocumentoRepository {
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
    protected $tablaFat = 'etl_fat_documentos_daop';

    /**
     * Array de tablas que deben ser particionadas.
     *
     * @var array
     */
    public $arrTablasParticionar = [
        'etl_cabecera_documentos_',
        'etl_datos_adicionales_documentos_',
        'etl_detalle_documentos_',
        'etl_impuestos_items_documentos_',
        'etl_medios_pago_documentos_',
        'etl_anticipos_documentos_',
        'etl_cargos_descuentos_documentos_',
        'etl_estados_documentos_',
        'etl_documentos_anexos_',
        'etl_email_certification_sent_',
        'etl_eventos_notificacion_documentos_'
    ];
    
    /**
     * Array de nombres cortos que se pueden aplicar a relaciones o índices.
     * 
     * Esto es útil toda vez que el motor de base de datos restringe longitudes a 64 caracteres y al crear las nuevas tablas de particiones hay índices y FK que se revientan por la longitud
     *
     * @var array
     */
    protected $arrNombresCortos = [
        'etl_cabecera_documentos_'             => 'cdo_',
        'etl_datos_adicionales_documentos_'    => 'dad_',
        'etl_detalle_documentos_'              => 'ddo_',
        'etl_impuestos_items_documentos_'      => 'iid_',
        'etl_medios_pago_documentos_'          => 'men_',
        'etl_anticipos_documentos_'            => 'ant_',
        'etl_cargos_descuentos_documentos_'    => 'cdd_',
        'etl_estados_documentos_'              => 'est_',
        'etl_documentos_anexos_'               => 'dan_',
        'etl_email_certification_sent_'        => 'ecs_',
        'etl_eventos_notificacion_documentos_' => 'evt_'
    ];

    public function __construct() {}

    /**
     * Establece una conexión a una base de datos Tenant.
     *
     * @param AuthBaseDatos $baseDatos Instancia de la base de datos a la que se debe conectar
     * @return void
     */
    public function generarConexionTenant(AuthBaseDatos$baseDatos = null): void {
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
                '_etl_cabecera_documento_',
                '_etl_impuestos_items_documento_',
                'fk1_medios_pago_documentos_',
                '_' . $tablaParticionar
            ], 
            [
                $tablaParticionar . $periodo,
                '_etl_cabecera_documento_' . $periodo,
                '_' . $this->arrNombresCortos[$tablaParticionar] . $periodo . '_',
                'fk1_' . $this->arrNombresCortos[$tablaParticionar] . $periodo . '_',
                '_' . $this->arrNombresCortos[$tablaParticionar] . $periodo . '_'
            ],
            $crearTabla['Create Table']
        );
        $crearTabla = str_replace('daop', $periodo, $crearTabla);

        // Crea la nueva tabla de partición
        DB::connection($conexion)
            ->statement($crearTabla);
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
     * Filtra la resultados de la consulta conforme al parámetro de búsqueda rápida recibido en la petición.
     *
     * @param Builder $query Instancia del Query Builder en procesamiento
     * @param string $textoBuscar Texto a buscar
     * @param string $tablaDocs Nombre de la tabla de cabecera de documentos
     * @param string $tablaAdqs Nombre de la tabla de adquirentes
     * @param string $tablaMonedas Nombre de la tabla de monedas
     * @return Builder
     */
    private function busquedaRapida(Builder $query, string $textoBuscar, string $tablaDocs, string $tablaAdqs, string $tablaMonedas): Builder {
        return $query->where(function ($query) use ($textoBuscar, $tablaDocs, $tablaAdqs, $tablaMonedas) {
            $query->where($tablaDocs . '.cdo_lote', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_clasificacion', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.rfa_prefijo', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_consecutivo', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_fecha', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_total', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_origen', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.cdo_valor_a_pagar', 'like', '%' . $textoBuscar . '%')
                ->orWhere($tablaDocs . '.estado', $textoBuscar)
                ->orWhereRaw('exists (select * from ' . $tablaAdqs . ' where ' . $tablaDocs . '.`adq_id` = ' . $tablaAdqs . '.`adq_id` and (`adq_razon_social` like ? or `adq_nombre_comercial` like ? or `adq_primer_apellido` like ? or `adq_segundo_apellido` like ? or `adq_primer_nombre` like ? or `adq_otros_nombres` like ?))', ['%' . $textoBuscar . '%', '%' . $textoBuscar . '%', '%' . $textoBuscar . '%', '%' . $textoBuscar . '%', '%' . $textoBuscar . '%', '%' . $textoBuscar . '%'])
                ->orWhereRaw("exists (select * from " . $tablaAdqs . " where " . $tablaDocs . ".`adq_id` = " . $tablaAdqs . ".`adq_id` and REPLACE(CONCAT(COALESCE(`adq_primer_nombre`, ''), ' ', COALESCE(`adq_otros_nombres`, ''), ' ', COALESCE(`adq_primer_apellido`, ''), ' ', COALESCE(`adq_segundo_apellido`, '')), '  ', ' ') like ?)", ['%' . $textoBuscar . '%'])
                ->orWhereRaw('exists (select * from ' . $tablaMonedas . ' where ' . $tablaDocs . '.`mon_id` = ' . $tablaMonedas . '.`mon_id` and `mon_codigo` like ?)', [$textoBuscar]);
        });
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
    public function relacionEstadosDocumentos(Builder $query, string $tablaDocs, string $tablaEstados, array $colSelect, string $estEstado, string $estResultado, string $estEjecucion): Builder {
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
     * Si el documento tiene varios estados que cumplen con las condiciones solamente se retornará el estado con el id más alto (mas reciente)
     *
     * @param Builder $query Instancia del QueryBuilder al que se encadenará la consulta
     * @param string $tablaPrincipal Tabla principal donde nace la consulta
     * @param string $tablaSecundaria Tabla secundaria sobre la cual se hace el JOIN
     * @param string $estEstado Estado a consultar
     * @param string $estResultado Resultado del estado a consultar
     * @param string $estEjecucion Ejecución del estato a consultar
     * @return Builder
     */
    public function relacionUltimoEstadoDocumento(Builder $query, string $tablaPrincipal, string $tablaSecundaria, string $estEstado, string $estResultado, string $estEjecucion): Builder {
        return $query->on($tablaPrincipal . '.cdo_id', '=', $tablaSecundaria . '.cdo_id')
            ->whereRaw($tablaSecundaria . '.est_id IN (SELECT MAX(estado_max.est_id) FROM ' . $tablaPrincipal . ' AS estado_max WHERE estado_max.est_estado = "' . $estEstado . '" and estado_max.est_resultado = "' . $estResultado . '" and estado_max.est_ejecucion = "' . $estEjecucion . '" GROUP BY estado_max.cdo_id)');
    }

    /**
     * Permite consultar el último evento DIAN del documento conforme a los parámetros recibidos.
     * 
     * Este método solo evalúa los eventos DIAN (Aceptación Tácita, Aceptación y Rechazo) para identificar cuál es el último
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
    public function relacionUltimoEventoDianDocumento(Builder $query, string $tablaPrincipal, string $tablaSecundaria, string $nombreRelacion) {
        return  $query->on($tablaPrincipal . '.cdo_id', '=', $nombreRelacion.'.cdo_id')
            ->whereRaw($nombreRelacion.'.est_id IN (select MAX(estado_do_max.est_id) FROM ' . $tablaSecundaria . ' AS estado_do_max WHERE estado_do_max.est_estado IN("UBLACEPTACIONT", "ACEPTACIONT", "UBLACEPTACION", "ACEPTACION", "UBLRECHAZO", "RECHAZO") AND estado_do_max.est_ejecucion = "FINALIZADO" GROUP BY estado_do_max.cdo_id)');
    }

    /**
     * Devuelve el array de columnas y relaciones a retornar en una consulta.
     *
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return array
     */
    private function columnasSelect(\stdClass $tablasProceso): array {
        $colSelect = [
            $tablasProceso->tablaDocs    . '.cdo_id',
            $tablasProceso->tablaDocs    . '.cdo_origen',
            $tablasProceso->tablaDocs    . '.cdo_clasificacion',
            $tablasProceso->tablaDocs    . '.cdo_lote',
            $tablasProceso->tablaDocs    . '.ofe_id',
            $tablasProceso->tablaDocs    . '.adq_id',
            $tablasProceso->tablaDocs    . '.rfa_id',
            $tablasProceso->tablaDocs    . '.mon_id',
            $tablasProceso->tablaDocs    . '.mon_id_extranjera',
            $tablasProceso->tablaDocs    . '.rfa_prefijo',
            $tablasProceso->tablaDocs    . '.cdo_consecutivo',
            $tablasProceso->tablaDocs    . '.cdo_fecha',
            $tablasProceso->tablaDocs    . '.cdo_hora',
            $tablasProceso->tablaDocs    . '.cdo_vencimiento',
            $tablasProceso->tablaDocs    . '.cdo_documento_referencia',
            $tablasProceso->tablaDocs    . '.cdo_observacion',
            $tablasProceso->tablaDocs    . '.cdo_representacion_grafica_documento',
            $tablasProceso->tablaDocs    . '.cdo_valor_sin_impuestos',
            $tablasProceso->tablaDocs    . '.cdo_valor_sin_impuestos_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_impuestos',
            $tablasProceso->tablaDocs    . '.cdo_total',
            $tablasProceso->tablaDocs    . '.cdo_cargos',
            $tablasProceso->tablaDocs    . '.cdo_cargos_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_descuentos',
            $tablasProceso->tablaDocs    . '.cdo_descuentos_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_retenciones_sugeridas',
            $tablasProceso->tablaDocs    . '.cdo_retenciones_sugeridas_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_redondeo',
            $tablasProceso->tablaDocs    . '.cdo_redondeo_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_anticipo',
            $tablasProceso->tablaDocs    . '.cdo_anticipo_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_retenciones',
            $tablasProceso->tablaDocs    . '.cdo_retenciones_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_impuestos_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_valor_a_pagar',
            $tablasProceso->tablaDocs    . '.cdo_valor_a_pagar_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_cufe',
            $tablasProceso->tablaDocs    . '.cdo_signaturevalue',
            $tablasProceso->tablaDocs    . '.cdo_procesar_documento',
            $tablasProceso->tablaDocs    . '.cdo_fecha_procesar_documento',
            $tablasProceso->tablaDocs    . '.cdo_fecha_validacion_dian',
            $tablasProceso->tablaDocs    . '.cdo_fecha_acuse',
            $tablasProceso->tablaDocs    . '.cdo_estado',
            $tablasProceso->tablaDocs    . '.cdo_fecha_estado',
            $tablasProceso->tablaDocs    . '.cdo_fecha_inicio_consulta_eventos',
            $tablasProceso->tablaDocs    . '.cdo_fecha_recibo_bien',
            $tablasProceso->tablaDocs    . '.cdo_fecha_archivo_salida',
            $tablasProceso->tablaDocs    . '.usuario_creacion',
            $tablasProceso->tablaDocs    . '.fecha_creacion',
            $tablasProceso->tablaDocs    . '.fecha_modificacion',
            $tablasProceso->tablaDocs    . '.estado',
            $tablasProceso->tablaDocs    . '.fecha_actualizacion',

            $tablasProceso->tablaOfes    . '.ofe_id',
            $tablasProceso->tablaOfes    . '.ofe_identificacion',
            $tablasProceso->tablaOfes    . '.ofe_razon_social',
            $tablasProceso->tablaOfes    . '.ofe_nombre_comercial',
            $tablasProceso->tablaOfes    . '.ofe_primer_apellido',
            $tablasProceso->tablaOfes    . '.ofe_segundo_apellido',
            $tablasProceso->tablaOfes    . '.ofe_primer_nombre',
            $tablasProceso->tablaOfes    . '.ofe_otros_nombres',
            $tablasProceso->tablaOfes    . '.ofe_cadisoft_activo',
            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_nombre_completo'),

            $tablasProceso->tablaAdqs    . '.adq_id',
            $tablasProceso->tablaAdqs    . '.adq_identificacion',
            $tablasProceso->tablaAdqs    . '.adq_id_personalizado',
            $tablasProceso->tablaAdqs    . '.adq_razon_social',
            $tablasProceso->tablaAdqs    . '.adq_nombre_comercial',
            $tablasProceso->tablaAdqs    . '.adq_primer_apellido',
            $tablasProceso->tablaAdqs    . '.adq_segundo_apellido',
            $tablasProceso->tablaAdqs    . '.adq_primer_nombre',
            $tablasProceso->tablaAdqs    . '.adq_otros_nombres',
            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as adq_nombre_completo'),

            $tablasProceso->tablaMonedas . '.mon_id',
            $tablasProceso->tablaMonedas . '.mon_codigo',
            $tablasProceso->tablaMonedas . '.mon_descripcion',
            
            'moneda_extranjera.mon_id as mon_id_extranjera',
            'moneda_extranjera.mon_codigo as mon_codigo_extranjera',
            'moneda_extranjera.mon_descripcion as mon_descripcion_extranjera',

            $tablasProceso->tablaTiposOperacion . '.top_id',
            $tablasProceso->tablaTiposOperacion . '.top_codigo',
            $tablasProceso->tablaTiposOperacion . '.top_descripcion',

            $tablasProceso->tablaResoluciones . '.rfa_id as resolucion_rfa_id',
            $tablasProceso->tablaResoluciones . '.rfa_tipo as resolucion_rfa_tipo',
            $tablasProceso->tablaResoluciones . '.rfa_prefijo as resolucion_rfa_prefijo',
            $tablasProceso->tablaResoluciones . '.rfa_resolucion as resolucion_rfa_resolucion',

            'get_documento_aprobado.est_id as get_documento_aprobado_est_id',
            'get_documento_aprobado.est_estado as get_documento_aprobado_est_estado',
            'get_documento_aprobado.est_resultado as get_documento_aprobado_est_resultado',
            'get_documento_aprobado.est_ejecucion as get_documento_aprobado_est_ejecucion',
            'get_documento_aprobado.est_mensaje_resultado as get_documento_aprobado_est_mensaje_resultado',

            'get_documento_aprobado_notificacion.est_id as get_documento_aprobado_notificacion_est_id',
            'get_documento_aprobado_notificacion.est_estado as get_documento_aprobado_notificacion_est_estado',
            'get_documento_aprobado_notificacion.est_resultado as get_documento_aprobado_notificacion_est_resultado',
            'get_documento_aprobado_notificacion.est_ejecucion as get_documento_aprobado_notificacion_est_ejecucion',
            'get_documento_aprobado_notificacion.est_mensaje_resultado as get_documento_aprobado_notificacion_est_mensaje_resultado',

            'get_documento_rechazado.est_id as get_documento_rechazado_est_id',
            'get_documento_rechazado.est_estado as get_documento_rechazado_est_estado',
            'get_documento_rechazado.est_resultado as get_documento_rechazado_est_resultado',
            'get_documento_rechazado.est_ejecucion as get_documento_rechazado_est_ejecucion',
            'get_documento_rechazado.est_mensaje_resultado as get_documento_rechazado_est_mensaje_resultado',

            'get_notificacion_finalizado.est_id as get_notificacion_finalizado_est_id',
            'get_notificacion_finalizado.est_estado as get_notificacion_finalizado_est_estado',
            'get_notificacion_finalizado.est_resultado as get_notificacion_finalizado_est_resultado',
            'get_notificacion_finalizado.est_ejecucion as get_notificacion_finalizado_est_ejecucion',
            'get_notificacion_finalizado.est_mensaje_resultado as get_notificacion_finalizado_est_mensaje_resultado',
            'get_notificacion_finalizado.est_correos as get_notificacion_finalizado_est_correos',
            'get_notificacion_finalizado.est_fin_proceso as get_notificacion_finalizado_est_fin_proceso',
            'get_notificacion_finalizado.fecha_modificacion as get_notificacion_finalizado_fecha_modificacion',

            'get_do_en_proceso.est_id as get_do_en_proceso_est_id',
            'get_do_en_proceso.est_estado as get_do_en_proceso_est_estado',
            'get_do_en_proceso.est_resultado as get_do_en_proceso_est_resultado',
            'get_do_en_proceso.est_ejecucion as get_do_en_proceso_est_ejecucion',
            'get_do_en_proceso.est_mensaje_resultado as get_do_en_proceso_est_mensaje_resultado',

            'get_aceptado.est_id as get_aceptado_est_id',
            'get_aceptado.est_estado as get_aceptado_est_estado',
            'get_aceptado.est_resultado as get_aceptado_est_resultado',
            'get_aceptado.est_ejecucion as get_aceptado_est_ejecucion',
            'get_aceptado.est_mensaje_resultado as get_aceptado_est_mensaje_resultado',

            'get_aceptado_t.est_id as get_aceptado_t_est_id',
            'get_aceptado_t.est_estado as get_aceptado_t_est_estado',
            'get_aceptado_t.est_resultado as get_aceptado_t_est_resultado',
            'get_aceptado_t.est_ejecucion as get_aceptado_t_est_ejecucion',
            'get_aceptado_t.est_mensaje_resultado as get_aceptado_t_est_mensaje_resultado',

            'get_aceptado_t_fallido.est_id as get_aceptado_t_fallido_est_id',
            'get_aceptado_t_fallido.est_estado as get_aceptado_t_fallido_est_estado',
            'get_aceptado_t_fallido.est_resultado as get_aceptado_t_fallido_est_resultado',
            'get_aceptado_t_fallido.est_ejecucion as get_aceptado_t_fallido_est_ejecucion',
            'get_aceptado_t_fallido.est_mensaje_resultado as get_aceptado_t_fallido_est_mensaje_resultado',

            'get_rechazado.est_id as get_rechazado_est_id',
            'get_rechazado.est_estado as get_rechazado_est_estado',
            'get_rechazado.est_resultado as get_rechazado_est_resultado',
            'get_rechazado.est_ejecucion as get_rechazado_est_ejecucion',
            'get_rechazado.est_mensaje_resultado as get_rechazado_est_mensaje_resultado',
            'get_rechazado.est_motivo_rechazo as get_rechazado_est_motivo_rechazo',

            'get_ubl_finalizado.est_id as get_ubl_finalizado_est_id',
            'get_ubl_finalizado.est_estado as get_ubl_finalizado_est_estado',
            'get_ubl_finalizado.est_resultado as get_ubl_finalizado_est_resultado',
            'get_ubl_finalizado.est_ejecucion as get_ubl_finalizado_est_ejecucion',
            'get_ubl_finalizado.est_mensaje_resultado as get_ubl_finalizado_est_mensaje_resultado',

            'get_ubl_fallido.est_id as get_ubl_fallido_est_id',
            'get_ubl_fallido.est_estado as get_ubl_fallido_est_estado',
            'get_ubl_fallido.est_resultado as get_ubl_fallido_est_resultado',
            'get_ubl_fallido.est_ejecucion as get_ubl_fallido_est_ejecucion',
            'get_ubl_fallido.est_mensaje_resultado as get_ubl_fallido_est_mensaje_resultado',

            'get_ubl_en_proceso.est_id as get_ubl_en_proceso_est_id',
            'get_ubl_en_proceso.est_estado as get_ubl_en_proceso_est_estado',
            'get_ubl_en_proceso.est_resultado as get_ubl_en_proceso_est_resultado',
            'get_ubl_en_proceso.est_ejecucion as get_ubl_en_proceso_est_ejecucion',
            'get_ubl_en_proceso.est_mensaje_resultado as get_ubl_en_proceso_est_mensaje_resultado',

            'get_ubl_attached_document_fallido.est_id as get_ubl_attached_document_fallido_est_id',
            'get_ubl_attached_document_fallido.est_estado as get_ubl_attached_document_fallido_est_estado',
            'get_ubl_attached_document_fallido.est_resultado as get_ubl_attached_document_fallido_est_resultado',
            'get_ubl_attached_document_fallido.est_ejecucion as get_ubl_attached_document_fallido_est_ejecucion',
            'get_ubl_attached_document_fallido.est_mensaje_resultado as get_ubl_attached_document_fallido_est_mensaje_resultado',

            'get_ultimo_evento_dian.est_id as get_ultimo_evento_dian_est_id',
            'get_ultimo_evento_dian.est_estado as get_ultimo_evento_dian_est_estado',
            'get_ultimo_evento_dian.est_resultado as get_ultimo_evento_dian_est_resultado',
            'get_ultimo_evento_dian.est_ejecucion as get_ultimo_evento_dian_est_ejecucion',
            'get_ultimo_evento_dian.est_mensaje_resultado as get_ultimo_evento_dian_est_mensaje_resultado',

            'get_notificacion_tamano_superior.est_id as notificacion_tamano_superior',

            $tablasProceso->tablaDatosAdicionales . '.cdo_informacion_adicional',

            $tablasProceso->tablaEventosNot . '.evt_evento as notificacion_tipo_evento',

            DB::raw('count(' . $tablasProceso->tablaAnexos . '.cdo_id) as get_documentos_anexos_count')
        ];

        return $colSelect;
    }

    /**
     * Obtiene los documentos enviados de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Collection $consulta
     */
    public function getDocumentosEnviados(Request $request, \stdClass $tablasProceso): Collection {
        $consulta = DB::connection($this->connection)
            ->table($tablasProceso->tablaDocs)
            ->select($this->columnasSelect($tablasProceso))
            ->leftJoin($tablasProceso->tablaAdqs, $tablasProceso->tablaDocs . '.adq_id', '=', $tablasProceso->tablaAdqs . '.adq_id')
            ->leftJoin($tablasProceso->tablaMonedas, $tablasProceso->tablaDocs . '.mon_id', '=', $tablasProceso->tablaMonedas . '.mon_id')
            ->leftJoin($tablasProceso->tablaTiposOperacion, $tablasProceso->tablaDocs . '.top_id', '=', $tablasProceso->tablaTiposOperacion . '.top_id')
            ->leftJoin($tablasProceso->tablaMonedas . ' as moneda_extranjera', $tablasProceso->tablaDocs . '.mon_id_extranjera', '=', 'moneda_extranjera.mon_id')
            ->leftJoin($tablasProceso->tablaResoluciones, $tablasProceso->tablaDocs . '.rfa_id', '=', $tablasProceso->tablaResoluciones . '.rfa_id')
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
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_aprobado', ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where(function($query) {
                            $query->where('get_documento_aprobado.est_informacion_adicional', 'not like', '%conNotificacion%')
                                ->orWhere('get_documento_aprobado.est_informacion_adicional->conNotificacion', 'false');
                        });
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado_notificacion', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_aprobado_notificacion', ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where('get_documento_aprobado_notificacion.est_informacion_adicional->conNotificacion', 'true');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_rechazado', ['est_id'], 'DO', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_finalizado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_notificacion_finalizado', ['est_id'], 'NOTIFICACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_do_en_proceso', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_do_en_proceso', ['est_id'], 'DO', '', 'ENPROCESO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_aceptado', ['est_id'], 'ACEPTACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado_t', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_aceptado_t', ['est_id'], 'ACEPTACIONT', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado_t_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_aceptado_t_fallido', ['est_id'], 'ACEPTACIONT', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_rechazado', ['est_id'], 'RECHAZO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ubl_finalizado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_ubl_finalizado', ['est_id'], 'UBL', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ubl_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_ubl_fallido', ['est_id'], 'UBL', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ubl_en_proceso', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_ubl_en_proceso', ['est_id'], 'UBL', '', 'ENPROCESO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ubl_attached_document_fallido', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_ubl_attached_document_fallido', ['est_id'], 'UBLATTACHEDDOCUMENT', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_tamano_superior', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_notificacion_tamano_superior', ['est_id'], 'NOTIFICACION', 'EXITOSO', 'FINALIZADO')
                        ->where('get_notificacion_tamano_superior.est_informacion_adicional', 'like', '%zip adjunto al correo es superior a 2M%');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_ultimo_evento_dian', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, 'get_ultimo_evento_dian');
                });
            })
            ->leftJoin($tablasProceso->tablaEventosNot, function($query) use ($tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.cdo_id = ' . $tablasProceso->tablaEventosNot . '.cdo_id')
                    ->where('evt_evento', 'delivery');
            })
            ->where($tablasProceso->tablaDocs . '.ofe_id', $request->ofe_id)
            ->where($tablasProceso->tablaDocs . '.cdo_procesar_documento', 'SI')
            ->whereNotNull($tablasProceso->tablaDocs . '.cdo_fecha_procesar_documento')
            ->when($request->filled('tipo_reporte'), function($query) use ($request, $tablasProceso) {
                return $query ->whereBetween($tablasProceso->tablaDocs . '.fecha_creacion', [$request->fecha_creacion_desde . ' 00:00:00', $request->fecha_creacion_hasta . ' 23:59:59']);
            }, function($query) use ($request, $tablasProceso) {
                return $query ->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha_procesar_documento', [$request->cdo_fecha_envio_desde . ' 00:00:00', $request->cdo_fecha_envio_hasta . ' 23:59:59']);
            })
            ->when($request->filled('adq_id'), function($query) use ($request, $tablasProceso) {
                if (!is_array($request->adq_id)) {
                    $arrAdqIds = explode(",", $request->adq_id);
                } else {
                    $arrAdqIds = $request->adq_id;
                }

                if(!empty($arrAdqIds))
                    return $query->whereIn($tablasProceso->tablaDocs . '.adq_id', $arrAdqIds);
            })
            ->when($request->filled('buscar'), function($query) use ($request, $tablasProceso) {
                return $this->busquedaRapida($query, $request->buscar, $tablasProceso->tablaDocs, $tablasProceso->tablaAdqs, $tablasProceso->tablaMonedas);
            })
            ->when($request->filled('ofe_filtro') && $request->filled('ofe_filtro_buscar'), function($query) use ($request, $tablasProceso) {
                switch($request->ofe_filtro){
                    case 'cdo_representacion_grafica_documento':
                        return $query->where($tablasProceso->tablaDocs . '.cdo_representacion_grafica_documento', $request->ofe_filtro_buscar);
                        break;
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
            ->when($request->filled('cdo_clasificacion'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_clasificacion', $request->cdo_clasificacion);
            }, function($query) use ($request, $tablasProceso) {
                if($request->filled('proceso') && $request->proceso == 'emision') {
                    return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['FC','NC','ND']);
                } elseif($request->filled('proceso') && $request->proceso == 'documento_soporte') {
                    return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['DS','DS_NC']);
                }
            })
            ->when($request->filled('rfa_prefijo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('cdo_fecha_desde') && $request->filled('cdo_fecha_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha', [$request->cdo_fecha_desde, $request->cdo_fecha_hasta]);
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
                    $query->when(in_array('aceptacion_tacita', $request->estado_eventos_dian) && $request->filled('resultado_evento_dian') && $request->resultado_evento_dian == "exitoso", function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["ACEPTACIONT"], ["EXITOSO"]);
                        });
                    })
                    ->when(in_array('aceptacion_tacita', $request->estado_eventos_dian) && $request->filled('resultado_evento_dian') && $request->resultado_evento_dian == "fallido",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["ACEPTACIONT"], ["FALLIDO"]);
                        });
                    })
                    ->when(in_array('aceptacion_tacita', $request->estado_eventos_dian) && !$request->filled('resultado_evento_dian'),function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["UBLACEPTACIONT", "ACEPTACIONT"], ["EXITOSO", "FALLIDO"]);
                        });
                    })
                    ->when(in_array('aceptacion_documento', $request->estado_eventos_dian) && $request->filled('resultado_evento_dian') && $request->resultado_evento_dian == "exitoso",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["ACEPTACION"], ["EXITOSO"]);
                        });
                    })
                    ->when(in_array('aceptacion_documento', $request->estado_eventos_dian) && $request->filled('resultado_evento_dian') && $request->resultado_evento_dian == "fallido",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["ACEPTACION"], ["FALLIDO"]);
                        });
                    })
                    ->when(in_array('aceptacion_documento', $request->estado_eventos_dian) && !$request->filled('resultado_evento_dian'),function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["UBLACEPTACION", "ACEPTACION"], ["EXITOSO", "FALLIDO"]);
                        });
                    })
                    ->when(in_array('rechazo_documento', $request->estado_eventos_dian) && $request->filled('resultado_evento_dian') && $request->resultado_evento_dian == "exitoso",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["RECHAZO"], ["EXITOSO"]);
                        });
                    })
                    ->when(in_array('rechazo_documento', $request->estado_eventos_dian) && $request->filled('resultado_evento_dian') && $request->resultado_evento_dian == "fallido",function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["RECHAZO"], ["FALLIDO"]);
                        });
                    })
                    ->when(in_array('rechazo_documento', $request->estado_eventos_dian) && !$request->filled('resultado_evento_dian'),function($query) use ($tablasProceso) {
                        return $query->orWhere(function($query) use ($tablasProceso) {
                            $query = $this->consultaUltimoEventoDianDocumento($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ["UBLRECHAZO", "RECHAZO"], ["EXITOSO", "FALLIDO"]);
                        });
                    })
                    ->when(in_array('sin_estado', $request->estado_eventos_dian),function($query) use ($request, $tablasProceso) {
                        return $query->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACEPTACION', 'EXITOSO', 'FINALIZADO')
                                ->orWhere(function($query) use ($tablasProceso) { 
                                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACEPTACION', 'FALLIDO', 'FINALIZADO');
                                });
                        })->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'RECHAZO', 'EXITOSO', 'FINALIZADO')
                                ->orWhere(function($query) use ($tablasProceso) { 
                                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'RECHAZO', 'FALLIDO', 'FINALIZADO');
                                });
                        })->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACEPTACIONT', 'EXITOSO', 'FINALIZADO')
                                ->orWhere(function($query) use ($tablasProceso) { 
                                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACEPTACIONT', 'FALLIDO', 'FINALIZADO');
                                });
                        })->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'RECIBOBIEN', 'EXITOSO', 'FINALIZADO')
                                ->orWhere(function($query) use ($tablasProceso) { 
                                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'RECIBOBIEN', 'FALLIDO', 'FINALIZADO');
                                });
                        })->whereNotExists(function($query) use ($tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACUSERECIBO', 'EXITOSO', 'FINALIZADO')
                                ->orWhere(function($query) use ($tablasProceso) { 
                                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'ACUSERECIBO', 'FALLIDO', 'FINALIZADO');
                                });
                        });
                    });
                });
            })
            ->when($request->filled('estado_dian'), function($query) use ($request, $tablasProceso) {
                    return $query->when(($request->estado_dian == 'aprobado' || $request->estado_dian == 'aprobado_con_notificacion'), function($query) use ($request, $tablasProceso) {
                        return $query->whereExists(function($query) use ($request, $tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                                ->when($request->estado_dian == 'aprobado', function($query) {
                                    return $query->where(function($query) {
                                        $query->where('est_informacion_adicional', 'not like', '%conNotificacion%')
                                            ->orWhere('est_informacion_adicional->conNotificacion', 'false');
                                    });
                                }, function($query) {
                                    return $query->where('est_informacion_adicional->conNotificacion', 'true');
                                });
                        });
                    }, function($query) use ($request, $tablasProceso) {
                        return $query->when(($request->estado_dian == 'rechazado'), function($query) use ($tablasProceso) {
                            return $query->whereExists(function($query) use ($tablasProceso) {
                                $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'FALLIDO', 'FINALIZADO')
                                    ->whereNotExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO');
                                    });
                                });
                        }, function($query) use ($tablasProceso) {
                            return $query->where(function($query) use ($tablasProceso) {
                                return $query->where(function($query) use ($tablasProceso) {
                                    $query->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'EDI', '', 'ENPROCESO');
                                    })->orWhereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'UBL', '', 'ENPROCESO');
                                    })->orWhereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', '', 'ENPROCESO');
                                    })->orWhereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'UBLATTACHEDDOCUMENT', '', 'ENPROCESO');
                                    })->orWhereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'NOTIFICACION', '', 'ENPROCESO');
                                    });
                                })->orWhere(function($query) use ($tablasProceso) {
                                    $query->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'EDI', 'EXITOSO', 'FINALIZADO');
                                    })->whereNotExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'UBL', '', '');
                                    });
                                })->orWhere(function($query) use ($tablasProceso) {
                                    $query->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'EDI', 'EXITOSO', 'FINALIZADO');
                                    })->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'UBL', 'EXITOSO', 'FINALIZADO');
                                    })->whereNotExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', '', '');
                                    });
                                })->orWhere(function($query) use ($tablasProceso) {
                                    $query->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'EDI', 'EXITOSO', 'FINALIZADO');
                                    })->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'UBL', 'EXITOSO', 'FINALIZADO');
                                    })->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO');
                                    })->whereNotExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'UBLATTACHEDDOCUMENT', '', '');
                                    });
                                })->orWhere(function($query) use ($tablasProceso) {
                                    $query->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'EDI', 'EXITOSO', 'FINALIZADO');
                                    })->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'UBL', 'EXITOSO', 'FINALIZADO');
                                    })->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO');
                                    })->whereExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'UBLATTACHEDDOCUMENT', 'EXITOSO', 'FINALIZADO');
                                    })->whereNotExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'NOTIFICACION', '', '');
                                    });
                                });
                            });
                        });
                    });
                })
                ->groupBy($tablasProceso->tablaDocs . '.cdo_id')
                ->get();

        return $consulta;
    }

    /**
     * Retorna las columnas personalizadas de un OFE.
     *
     * @param Request $request
     * @return array
     */
    public function excelColumnasPersonalizadasOfe(Request $request): array {
        return ConfiguracionObligadoFacturarElectronicamente::select(['ofe_columnas_personalizadas'])
            ->find($request->ofe_id)
            ->toArray();
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
     * @param EtlCabeceraDocumentoDaop $documento Colección del documento a trasladar
     * @param string $particion Partición destino
     * @param string $comandoOrigen De acuerdo al comando desde donde se llama el método, se permite verificar que los registros de DAOP existan en las tablas particionadas para eliminar de DAOP o en caso contrario eliminar de las tablas particionadas
     * @return void
     */
    public function trasladarDocumentoHistorico(EtlCabeceraDocumentoDaop $documento, string $particion, string $comandoOrigen): void {
        $columnasFat = implode(',', Schema::connection($this->connection)->getColumnListing('etl_fat_documentos_daop'));

        $builder = DB::connection($this->connection);
        $builder->statement('SET FOREIGN_KEY_CHECKS=0');
        if($comandoOrigen == 'traslado-automatico' || $comandoOrigen == 'traslado-manual') {
            foreach($this->arrTablasParticionar as $tablaParticion) {
                if(!$this->existeTabla($this->connection, $tablaParticion . $particion))
                    $this->crearTablaParticionamiento($this->connection, $tablaParticion, $particion);

                $columnasSelect = implode(',', $this->diferenciasColumnasTabla($tablaParticion, $particion));

                if($tablaParticion == 'etl_cabecera_documentos_') {
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

        if($comandoOrigen == 'traslado-automatico' || $comandoOrigen == 'traslado-manual') {
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
     * Consulta Cantidad de Registros de las tablas particionadas.
     *
     * @param EtlCabeceraDocumentoDaop $documento Colección del documento a trasladar
     * @param string $particion Partición destino
     * @return array $totales Array que contiene el total de registros por cada tabla daop y por tabla particionada
     */
    public function ConsultarTotalRegistrosHistorico(EtlCabeceraDocumentoDaop $documento, string $particion) {
        $builder = DB::connection($this->connection);
        // Incializando total por tabla particionada
        foreach($this->arrTablasParticionar as $tablaParticion) {
          $totales[$tablaParticion . 'daop']       = 0;
          $totales[$tablaParticion . 'particion']  = 0;
          $totales[$tablaParticion . 'diferencia'] = 0;
        }
        // Cantidad de registros en DAOP y Particiones
        foreach($this->arrTablasParticionar as $tablaParticion) {
            $totalDaop      = $builder->select("SELECT COUNT(cdo_id) as totalRegistros FROM {$tablaParticion}daop WHERE cdo_id={$documento->cdo_id} GROUP BY cdo_id");
            $totalParticion = $builder->select("SELECT COUNT(cdo_id) as totalRegistros FROM {$tablaParticion}{$particion} WHERE cdo_id={$documento->cdo_id} GROUP BY cdo_id");

            $totalRegistrosDaop      = (!empty($totalDaop)) ? $totalDaop[0]->totalRegistros : 0;
            $totalRegistrosParticion = (!empty($totalParticion)) ? $totalParticion[0]->totalRegistros : 0;
            
            $totales[$tablaParticion . 'daop']       += $totalRegistrosDaop;
            $totales[$tablaParticion . 'particion']  += $totalRegistrosParticion;
            $totales[$tablaParticion . 'diferencia'] += $totalRegistrosDaop - $totalRegistrosParticion;
        }
        
        return $totales;
    }

    /**
     * Retorna el array de columnas y relaciones a retornar en una consulta.
     *
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @param string $tipoReporte Indica el tipo de reporte Documentos Procesados|Eventos Procesados
     * @return array
     */
    private function columnasSelectDocumentosProcesados(\stdClass $tablasProceso, string $tipoReporte = ''): array {
        $nombreTabla = ($tipoReporte == 'eventos_procesados') ? $tablasProceso->tablaDocs : 'cabecera_documentos';
        $colSelect = [
            $nombreTabla . '.ofe_id',
            $nombreTabla . '.adq_id',
            $nombreTabla . '.rfa_id',
            $nombreTabla . '.top_id',
            $nombreTabla . '.cdo_clasificacion',
            $nombreTabla . '.rfa_prefijo',
            $nombreTabla . '.cdo_consecutivo',
            $nombreTabla . '.cdo_fecha',
            $nombreTabla . '.cdo_hora',
            $nombreTabla . '.cdo_vencimiento',
            $nombreTabla . '.cdo_observacion',
            $nombreTabla . '.cdo_cufe',
            $nombreTabla . '.cdo_valor_sin_impuestos',
            $nombreTabla . '.cdo_impuestos',
            $nombreTabla . '.cdo_total',
            $nombreTabla . '.cdo_cargos',
            $nombreTabla . '.cdo_cargos_moneda_extranjera',
            $nombreTabla . '.cdo_descuentos',
            $nombreTabla . '.cdo_descuentos_moneda_extranjera',
            $nombreTabla . '.cdo_retenciones_sugeridas',
            $nombreTabla . '.cdo_retenciones_sugeridas_moneda_extranjera',
            $nombreTabla . '.cdo_redondeo',
            $nombreTabla . '.cdo_redondeo_moneda_extranjera',
            $nombreTabla . '.cdo_anticipo',
            $nombreTabla . '.cdo_anticipo_moneda_extranjera',
            $nombreTabla . '.cdo_retenciones',
            $nombreTabla . '.cdo_retenciones_moneda_extranjera',
            $nombreTabla . '.estado',
            $nombreTabla . '.cdo_estado',
            $nombreTabla . '.cdo_fecha_estado',
            $nombreTabla . '.cdo_fecha_acuse',
            $nombreTabla . '.cdo_fecha_recibo_bien',
            'usuarios.usu_nombre as usuario_creacion_nombre',

            $tablasProceso->tablaOfes . '.ofe_id',
            $tablasProceso->tablaOfes . '.ofe_identificacion',
            $tablasProceso->tablaOfes . '.ofe_razon_social',
            $tablasProceso->tablaOfes . '.ofe_nombre_comercial',
            $tablasProceso->tablaOfes . '.ofe_primer_apellido',
            $tablasProceso->tablaOfes . '.ofe_segundo_apellido',
            $tablasProceso->tablaOfes . '.ofe_primer_nombre',
            $tablasProceso->tablaOfes . '.ofe_otros_nombres',
            $tablasProceso->tablaOfes . '.ofe_cadisoft_activo',
            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_nombre_completo'),

            $tablasProceso->tablaAdqs . '.adq_id',
            $tablasProceso->tablaAdqs . '.adq_identificacion',
            $tablasProceso->tablaAdqs . '.adq_id_personalizado',
            $tablasProceso->tablaAdqs . '.adq_razon_social',
            $tablasProceso->tablaAdqs . '.adq_nombre_comercial',
            $tablasProceso->tablaAdqs . '.adq_primer_apellido',
            $tablasProceso->tablaAdqs . '.adq_segundo_apellido',
            $tablasProceso->tablaAdqs . '.adq_primer_nombre',
            $tablasProceso->tablaAdqs . '.adq_otros_nombres',
            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as adq_nombre_completo'),

            $tablasProceso->tablaResoluciones . '.rfa_id as resolucion_rfa_id',
            $tablasProceso->tablaResoluciones . '.rfa_tipo as resolucion_rfa_tipo',
            $tablasProceso->tablaResoluciones . '.rfa_prefijo as resolucion_rfa_prefijo',
            $tablasProceso->tablaResoluciones . '.rfa_resolucion as resolucion_rfa_resolucion',

            $tablasProceso->tablaTiposOperacion . '.top_id',
            $tablasProceso->tablaTiposOperacion . '.top_codigo',
            $tablasProceso->tablaTiposOperacion . '.top_descripcion',

            $tablasProceso->tablaMonedas . '.mon_id',
            $tablasProceso->tablaMonedas . '.mon_codigo',
            $tablasProceso->tablaMonedas . '.mon_descripcion',

            'get_notificacion_finalizado.est_id as get_notificacion_finalizado_est_id',
            'get_notificacion_finalizado.est_estado as get_notificacion_finalizado_est_estado',
            'get_notificacion_finalizado.est_resultado as get_notificacion_finalizado_est_resultado',
            'get_notificacion_finalizado.est_ejecucion as get_notificacion_finalizado_est_ejecucion',
            'get_notificacion_finalizado.est_mensaje_resultado as get_notificacion_finalizado_est_mensaje_resultado',
            'get_notificacion_finalizado.est_correos as get_notificacion_finalizado_est_correos',
            'get_notificacion_finalizado.est_fin_proceso as get_notificacion_finalizado_est_fin_proceso',
            'get_notificacion_finalizado.fecha_modificacion as get_notificacion_finalizado_fecha_modificacion',

            'get_documento_aprobado.est_id as get_documento_aprobado_est_id',
            'get_documento_aprobado.est_estado as get_documento_aprobado_est_estado',
            'get_documento_aprobado.est_resultado as get_documento_aprobado_est_resultado',
            'get_documento_aprobado.est_ejecucion as get_documento_aprobado_est_ejecucion',
            'get_documento_aprobado.est_mensaje_resultado as get_documento_aprobado_est_mensaje_resultado',

            'get_documento_aprobado_notificacion.est_id as get_documento_aprobado_notificacion_est_id',
            'get_documento_aprobado_notificacion.est_estado as get_documento_aprobado_notificacion_est_estado',
            'get_documento_aprobado_notificacion.est_resultado as get_documento_aprobado_notificacion_est_resultado',
            'get_documento_aprobado_notificacion.est_ejecucion as get_documento_aprobado_notificacion_est_ejecucion',
            'get_documento_aprobado_notificacion.est_mensaje_resultado as get_documento_aprobado_notificacion_est_mensaje_resultado',

            'get_documento_rechazado.est_id as get_documento_rechazado_est_id',
            'get_documento_rechazado.est_estado as get_documento_rechazado_est_estado',
            'get_documento_rechazado.est_resultado as get_documento_rechazado_est_resultado',
            'get_documento_rechazado.est_ejecucion as get_documento_rechazado_est_ejecucion',
            'get_documento_rechazado.est_mensaje_resultado as get_documento_rechazado_est_mensaje_resultado',

            'get_rechazado.est_id as get_rechazado_est_id',
            'get_rechazado.est_estado as get_rechazado_est_estado',
            'get_rechazado.est_resultado as get_rechazado_est_resultado',
            'get_rechazado.est_ejecucion as get_rechazado_est_ejecucion',
            'get_rechazado.est_mensaje_resultado as get_rechazado_est_mensaje_resultado',
            'get_rechazado.est_motivo_rechazo as get_rechazado_est_motivo_rechazo'
        ];

        if ($tipoReporte == 'eventos_procesados') 
            array_push($colSelect, 
                $nombreTabla . '.cdo_fecha_inicio_consulta_eventos',
                $nombreTabla . '.fecha_creacion',
                $nombreTabla . '.usuario_creacion'
            );
        else 
            array_push($colSelect, 
                $tablasProceso->tablaEstados . '.est_id',
                $tablasProceso->tablaEstados . '.cdo_id',
                $tablasProceso->tablaEstados . '.est_estado',
                $tablasProceso->tablaEstados . '.est_informacion_adicional',
                $tablasProceso->tablaEstados . '.fecha_creacion as est_fecha_creacion',
                $tablasProceso->tablaEstados . '.usuario_creacion',
            );

        return $colSelect;
    }

    /**
     * Obtiene una lista de estados y su correspondiente documento de cabecera, de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Collection $consulta
     */
    public function getListaDocumentosProcesados(Request $request, \stdClass $tablasProceso): Collection {
        $consultaDocumentosProcesados = DB::connection($this->connection)
            ->table($tablasProceso->tablaEstados)
            ->select($this->columnasSelectDocumentosProcesados($tablasProceso))
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_finalizado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaEstados, 'get_notificacion_finalizado', 'NOTIFICACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaEstados, 'get_documento_aprobado', 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where(function($query) {
                            $query->where('get_documento_aprobado.est_informacion_adicional', 'not like', '%conNotificacion%')
                                ->orWhere('get_documento_aprobado.est_informacion_adicional->conNotificacion', 'false');
                        });
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado_notificacion', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaEstados, 'get_documento_aprobado_notificacion', 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where('get_documento_aprobado_notificacion.est_informacion_adicional->conNotificacion', 'true');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaEstados, 'get_documento_rechazado', 'DO', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionUltimoEstadoDocumento($query, $tablasProceso->tablaEstados, 'get_rechazado', 'RECHAZO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaDocs . ' as cabecera_documentos', $tablasProceso->tablaEstados . '.cdo_id', '=', 'cabecera_documentos.cdo_id')
            ->leftJoin($tablasProceso->tablaUsuarios . ' as usuarios', 'usuarios.usu_id', '=', $tablasProceso->tablaEstados . '.usuario_creacion')
            ->leftJoin($tablasProceso->tablaAdqs, 'cabecera_documentos.adq_id', '=', $tablasProceso->tablaAdqs . '.adq_id')
            ->leftJoin($tablasProceso->tablaResoluciones, 'cabecera_documentos.rfa_id', '=', $tablasProceso->tablaResoluciones . '.rfa_id')
            ->leftJoin($tablasProceso->tablaTiposOperacion, 'cabecera_documentos.top_id', '=', $tablasProceso->tablaTiposOperacion . '.top_id')
            ->leftJoin($tablasProceso->tablaMonedas, 'cabecera_documentos.mon_id', '=', $tablasProceso->tablaMonedas . '.mon_id')
            ->leftJoin($tablasProceso->tablaOfes, 'cabecera_documentos.ofe_id', '=', $tablasProceso->tablaOfes . '.ofe_id')
            ->where('cabecera_documentos.ofe_id', $request->ofe_id)
            ->where($tablasProceso->tablaEstados . '.est_estado', 'EDI')
            ->where(function($query) use ($tablasProceso) {
                $query->where($tablasProceso->tablaEstados . '.est_informacion_adicional', 'NOT LIKE', '%"update": true%')
                    ->where($tablasProceso->tablaEstados . '.est_informacion_adicional', 'NOT LIKE', '%"update":true%');
            })
            ->whereBetween($tablasProceso->tablaEstados . '.fecha_creacion', [$request->fecha_creacion_desde . " 00:00:00", $request->fecha_creacion_hasta . " 23:59:59"])
            ->where($tablasProceso->tablaEstados . '.estado', 'ACTIVO')
            ->when($request->filled('cdo_clasificacion'), function($query) use ($request) {
                return $query->where('cabecera_documentos.cdo_clasificacion', $request->cdo_clasificacion);
            }, function($query) use ($request) {
                if($request->filled('proceso') && $request->proceso == 'emision') {
                    return $query->whereIn('cabecera_documentos.cdo_clasificacion', ['FC','NC','ND']);
                } elseif($request->filled('proceso') && $request->proceso == 'documento_soporte') {
                    return $query->whereIn('cabecera_documentos.cdo_clasificacion', ['DS','DS_NC']);
                }
            })
            ->when($request->filled('adq_id'), function($query) use ($request) {
                if (!is_array($request->adq_id)) {
                    $arrAdqIds = explode(",", $request->adq_id);
                } else {
                    $arrAdqIds = $request->adq_id;
                }

                if(!empty($arrAdqIds))
                    return $query->whereIn('cabecera_documentos.adq_id', $arrAdqIds);
            })
            ->when($request->filled('cdo_lote'), function($query) use ($request) {
                return $query->where('cabecera_documentos.cdo_lote', $request->cdo_lote);
            })
            ->when($request->filled('cdo_origen'), function($query) use ($request) {
                return $query->where('cabecera_documentos.cdo_origen', $request->cdo_origen);
            })
            ->when($request->filled('cdo_clasificacion'), function($query) use ($request) {
                return $query->where('cabecera_documentos.cdo_clasificacion', $request->cdo_clasificacion);
            }, function($query) use ($request) {
                if($request->filled('proceso') && $request->proceso == 'emision') {
                    if (!$request->filled('cdo_clasificacion')) {
                        return $query->whereIn('cabecera_documentos.cdo_clasificacion', ['FC','NC','ND']);
                    }
                } elseif($request->filled('proceso') && $request->proceso == 'documento_soporte') {
                    if (!$request->filled('cdo_clasificacion')) {
                        return $query->whereIn('cabecera_documentos.cdo_clasificacion', ['DS','DS_NC']);
                    }
                }
            })
            ->when($request->filled('ofe_filtro') && $request->filled('ofe_filtro_buscar'), function($query) use ($request, $tablasProceso) {
                switch($request->ofe_filtro){
                    case 'cdo_representacion_grafica_documento':
                        return $query->where($tablasProceso->tablaDocs . '.cdo_representacion_grafica_documento', $request->ofe_filtro_buscar);
                        break;
                    default:
                        return $query->where($tablasProceso->tablaDatosAdicionales . '.cdo_informacion_adicional->' . $request->ofe_filtro, $request->ofe_filtro_buscar);
                        break;
                }
            })
            ->when($request->filled('rfa_prefijo'), function($query) use ($request) {
                return $query->where('cabecera_documentos.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use ($request) {
                return $query->where('cabecera_documentos.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('estado'), function($query) use ($request) {
                return $query->where('cabecera_documentos.estado', $request->estado);
            })
            ->orderBy($tablasProceso->tablaEstados . '.cdo_id', 'desc')
            ->orderBy($tablasProceso->tablaEstados . '.est_id', 'desc')
            ->get();

        return $consultaDocumentosProcesados;
    }

    /**
     * Obtiene una lista los documento de cabecera que tiene eventos procesados, de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Collection $consulta
     */
    public function getListaEventosProcesados(Request $request, \stdClass $tablasProceso): Collection {
        $consultaEventosProcesados = DB::connection($this->connection)
            ->table($tablasProceso->tablaDocs)
            ->select($this->columnasSelectDocumentosProcesados($tablasProceso, 'eventos_procesados'))
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_finalizado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_notificacion_finalizado', ['est_id'], 'NOTIFICACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_aprobado', ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where(function($query) {
                            $query->where('get_documento_aprobado.est_informacion_adicional', 'not like', '%conNotificacion%')
                                ->orWhere('get_documento_aprobado.est_informacion_adicional->conNotificacion', 'false');
                        });
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado_notificacion', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_aprobado_notificacion', ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where('get_documento_aprobado_notificacion.est_informacion_adicional->conNotificacion', 'true');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_rechazado', ['est_id'], 'DO', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_rechazado', ['est_id'], 'RECHAZO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaAdqs, $tablasProceso->tablaDocs . '.adq_id', '=', $tablasProceso->tablaAdqs . '.adq_id')
            ->leftJoin($tablasProceso->tablaResoluciones, $tablasProceso->tablaDocs . '.rfa_id', '=', $tablasProceso->tablaResoluciones . '.rfa_id')
            ->leftJoin($tablasProceso->tablaTiposOperacion, $tablasProceso->tablaDocs . '.top_id', '=', $tablasProceso->tablaTiposOperacion . '.top_id')
            ->leftJoin($tablasProceso->tablaMonedas, $tablasProceso->tablaDocs . '.mon_id', '=', $tablasProceso->tablaMonedas . '.mon_id')
            ->leftJoin($tablasProceso->tablaUsuarios . ' as usuarios', $tablasProceso->tablaDocs . '.usuario_creacion', '=', 'usuarios.usu_id')
            ->leftJoin($tablasProceso->tablaOfes, function($query) use ($tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.ofe_id = ' . $tablasProceso->tablaOfes . '.ofe_id')
                    ->where(function($query) use ($tablasProceso) {
                        if(!empty(auth()->user()->bdd_id_rg))
                            $query->where($tablasProceso->tablaOfes . '.bdd_id_rg', auth()->user()->bdd_id_rg);
                        else
                            $query->whereNull($tablasProceso->tablaOfes . '.bdd_id_rg');
                    });
            })
            ->where($tablasProceso->tablaDocs . '.ofe_id', $request->ofe_id)
            ->whereNotNull($tablasProceso->tablaDocs . '.cdo_fecha_inicio_consulta_eventos')
            ->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha_inicio_consulta_eventos', [$request->fecha_creacion_desde . " 00:00:00", $request->fecha_creacion_hasta . " 23:59:59"])
            ->when($request->filled('adq_id'), function($query) use ($request) {
                if (!is_array($request->adq_id)) {
                    $arrAdqIds = explode(",", $request->adq_id);
                } else {
                    $arrAdqIds = $request->adq_id;
                }

                if(!empty($arrAdqIds))
                    return $query->whereIn($tablasProceso->tablaDocs . '.adq_id', $arrAdqIds);
            })
            ->when($request->filled('cdo_lote'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_lote', $request->cdo_lote);
            })
            ->when($request->filled('cdo_origen'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_origen', $request->cdo_origen);
            })
            ->when($request->filled('cdo_clasificacion'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_clasificacion', $request->cdo_clasificacion);
            }, function($query) use ($request, $tablasProceso) {
                return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['FC','NC','ND']);
            })
            ->when($request->filled('rfa_prefijo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('estado'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.estado', $request->estado);
            })
            ->orderBy($tablasProceso->tablaDocs . '.cdo_id', 'desc')
            ->get();

        return $consultaEventosProcesados;
    }

    /**
     * Retorna el array de columnas y relaciones a retornar en una consulta.
     *
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return array
     */
    private function columnasSelectEmailCertificationSent(\stdClass $tablasProceso): array {
        $colSelect = [
            $tablasProceso->tablaDocs . '.ofe_id',
            $tablasProceso->tablaDocs . '.adq_id',
            $tablasProceso->tablaDocs . '.rfa_id',
            $tablasProceso->tablaDocs . '.top_id',
            $tablasProceso->tablaDocs . '.tde_id',
            $tablasProceso->tablaDocs . '.cdo_id',
            $tablasProceso->tablaDocs . '.cdo_clasificacion',
            $tablasProceso->tablaDocs . '.rfa_prefijo',
            $tablasProceso->tablaDocs . '.cdo_consecutivo',
            $tablasProceso->tablaDocs . '.cdo_fecha',
            $tablasProceso->tablaDocs . '.cdo_hora',
            $tablasProceso->tablaDocs . '.cdo_fecha_validacion_dian',

            $tablasProceso->tablaOfes . '.ofe_id',
            $tablasProceso->tablaOfes . '.ofe_identificacion',
            $tablasProceso->tablaOfes . '.ofe_razon_social',
            $tablasProceso->tablaOfes . '.ofe_nombre_comercial',
            $tablasProceso->tablaOfes . '.ofe_primer_apellido',
            $tablasProceso->tablaOfes . '.ofe_segundo_apellido',
            $tablasProceso->tablaOfes . '.ofe_primer_nombre',
            $tablasProceso->tablaOfes . '.ofe_otros_nombres',
            $tablasProceso->tablaOfes . '.ofe_cadisoft_activo',
            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_nombre_completo'),

            $tablasProceso->tablaAdqs . '.adq_id',
            $tablasProceso->tablaAdqs . '.adq_identificacion',
            $tablasProceso->tablaAdqs . '.adq_id_personalizado',
            $tablasProceso->tablaAdqs . '.adq_razon_social',
            $tablasProceso->tablaAdqs . '.adq_nombre_comercial',
            $tablasProceso->tablaAdqs . '.adq_primer_apellido',
            $tablasProceso->tablaAdqs . '.adq_segundo_apellido',
            $tablasProceso->tablaAdqs . '.adq_primer_nombre',
            $tablasProceso->tablaAdqs . '.adq_otros_nombres',
            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as adq_nombre_completo'),

            $tablasProceso->tablaResoluciones . '.rfa_id as resolucion_rfa_id',
            $tablasProceso->tablaResoluciones . '.rfa_tipo as resolucion_rfa_tipo',
            $tablasProceso->tablaResoluciones . '.rfa_prefijo as resolucion_rfa_prefijo',
            $tablasProceso->tablaResoluciones . '.rfa_resolucion as resolucion_rfa_resolucion',

            $tablasProceso->tablaTiposOperacion . '.top_id',
            $tablasProceso->tablaTiposOperacion . '.top_codigo',
            $tablasProceso->tablaTiposOperacion . '.top_descripcion',

            $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_id',
            $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_codigo',
            $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_descripcion',

            DB::raw("CONCAT(
                '[',
                (select GROUP_CONCAT(
                    JSON_OBJECT(
                        'ecs_id', " .         $tablasProceso->tablaEmailCertificationSent . ".ecs_id,
                        'cdo_id', " .         $tablasProceso->tablaEmailCertificationSent . ".cdo_id,
                        'ecs_email_id', " .   $tablasProceso->tablaEmailCertificationSent . ".ecs_email_id,
                        'ecs_from', " .       $tablasProceso->tablaEmailCertificationSent . ".ecs_from,
                        'ecs_to', " .         $tablasProceso->tablaEmailCertificationSent . ".ecs_to,
                        'ecs_relay_name', " . $tablasProceso->tablaEmailCertificationSent . ".ecs_relay_name,
                        'ecs_relay_ip', " .   $tablasProceso->tablaEmailCertificationSent . ".ecs_relay_ip,
                        'ecs_relay_port', " . $tablasProceso->tablaEmailCertificationSent . ".ecs_relay_port,
                        'ecs_dsn', " .        $tablasProceso->tablaEmailCertificationSent . ".ecs_dsn,
                        'ecs_dsn_msg', " .    $tablasProceso->tablaEmailCertificationSent . ".ecs_dsn_msg,
                        'ecs_status_msg', " . $tablasProceso->tablaEmailCertificationSent . ".ecs_status_msg,
                        'ecs_mail_log', " .   $tablasProceso->tablaEmailCertificationSent . ".ecs_mail_log,
                        'ecs_status', " .     $tablasProceso->tablaEmailCertificationSent . ".ecs_status
                    )
                ) FROM " . $tablasProceso->tablaEmailCertificationSent . " WHERE " . $tablasProceso->tablaEmailCertificationSent . ".cdo_id = " . $tablasProceso->tablaDocs . ".cdo_id),
                ']'
            ) as get_email_certification_sent")
        ];

        return $colSelect;
    }

    /**
     * Obtiene una lista de notificación de documentos mediante SMTP de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Collection $consulta
     */
    public function getListaEmailCertificationSent(Request $request, \stdClass $tablasProceso): Collection {
        // La siguiente sentencia SQL es necesaria para permitir que la concatenación de resultados con MySql no trunque el resultado dado que su máximo de caracteres por defecto es de 1024
        // Esta modificación solamente es aplicable a la presente consulta/sesión y no es una modificación global
        DB::connection($this->connection)
            ->statement ("SET SESSION group_concat_max_len = 18446744073709551615;");
            
        $consultaEmailCertificationSent = DB::connection($this->connection)
            ->table($tablasProceso->tablaDocs)
            ->select($this->columnasSelectEmailCertificationSent($tablasProceso))
            ->leftJoin($tablasProceso->tablaAdqs, $tablasProceso->tablaDocs . '.adq_id', '=', $tablasProceso->tablaAdqs . '.adq_id')
            ->leftJoin($tablasProceso->tablaTiposOperacion, $tablasProceso->tablaDocs . '.top_id', '=', $tablasProceso->tablaTiposOperacion . '.top_id')
            ->leftJoin($tablasProceso->tablaTiposDocumentosElectronicos, $tablasProceso->tablaDocs . '.tde_id', '=', $tablasProceso->tablaTiposDocumentosElectronicos . '.tde_id')
            ->leftJoin($tablasProceso->tablaResoluciones, $tablasProceso->tablaDocs . '.rfa_id', '=', $tablasProceso->tablaResoluciones . '.rfa_id')
            ->leftJoin($tablasProceso->tablaEmailCertificationSent, $tablasProceso->tablaDocs . '.cdo_id', '=', $tablasProceso->tablaEmailCertificationSent . '.cdo_id')
            ->leftJoin($tablasProceso->tablaOfes, function($query) use ($tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.ofe_id = ' . $tablasProceso->tablaOfes . '.ofe_id')
                    ->where(function($query) use ($tablasProceso) {
                        if(!empty(auth()->user()->bdd_id_rg))
                            $query->where($tablasProceso->tablaOfes . '.bdd_id_rg', auth()->user()->bdd_id_rg);
                        else
                            $query->whereNull($tablasProceso->tablaOfes . '.bdd_id_rg');
                    });
            })
            ->when($request->filled('adq_id'), function($query) use ($request, $tablasProceso) {
                if (!is_array($request->adq_id)) {
                    $arrAdqIds = explode(",", $request->adq_id);
                } else {
                    $arrAdqIds = $request->adq_id;
                }

                if(!empty($arrAdqIds))
                    return $query->whereIn($tablasProceso->tablaDocs . '.adq_id', $arrAdqIds);
            })
            ->when($request->filled('cdo_lote'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_lote', $request->cdo_lote);
            })
            ->when($request->filled('ofe_filtro') && $request->filled('ofe_filtro_buscar'), function($query) use ($request, $tablasProceso) {
                switch($request->ofe_filtro){
                    case 'cdo_representacion_grafica_documento':
                        return $query->where($tablasProceso->tablaDocs . '.cdo_representacion_grafica_documento', $request->ofe_filtro_buscar);
                        break;
                    default:
                        return $query->where($tablasProceso->tablaDatosAdicionales . '.cdo_informacion_adicional->' . $request->ofe_filtro, $request->ofe_filtro_buscar);
                        break;
                }
            })
            ->when($request->filled('cdo_origen'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_origen', $request->cdo_origen);
            })
            ->when($request->filled('cdo_clasificacion'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_clasificacion', $request->cdo_clasificacion);
            }, function($query) use ($request, $tablasProceso) {
                if($request->filled('proceso') && $request->proceso == 'emision') {
                    return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['FC','NC','ND']);
                } elseif($request->filled('proceso') && $request->proceso == 'documento_soporte') {
                    return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['DS','DS_NC']);
                }
            })
            ->when($request->filled('rfa_prefijo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->where($tablasProceso->tablaDocs . '.ofe_id', $request->ofe_id)
            ->where($tablasProceso->tablaDocs . '.cdo_procesar_documento', 'SI')
            ->whereNotNull($tablasProceso->tablaDocs . '.cdo_fecha_procesar_documento')
            ->when($request->filled('cdo_fecha_desde') && $request->filled('cdo_fecha_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha', [$request->cdo_fecha_desde, $request->cdo_fecha_hasta]);
            })
            ->when($request->filled('cdo_fecha_envio_desde') && $request->filled('cdo_fecha_envio_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha_procesar_documento', [$request->cdo_fecha_envio_desde . ' 00:00:00', $request->cdo_fecha_envio_hasta . ' 23:59:59']);
            })
            ->whereExists(function($query) use ($tablasProceso) {
                $query->from($tablasProceso->tablaEmailCertificationSent)
                    ->select(['ecs_id'])
                    ->whereRaw($tablasProceso->tablaEmailCertificationSent . '.cdo_id = ' . $tablasProceso->tablaDocs . '.cdo_id');
            })
            ->groupBy($tablasProceso->tablaDocs . '.cdo_id')
            ->get();

        return $consultaEmailCertificationSent;
    }

    /**
     * Retorna el array de columnas y relaciones a retornar en una consulta de documentos enviados de DHL Express.
     *
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return array
     */
    private function columnasSelectEnviadosDhlExpress(\stdClass $tablasProceso): array {
        $colSelect = [
            $tablasProceso->tablaDocs    . '.cdo_id',
            $tablasProceso->tablaDocs    . '.cdo_origen',
            $tablasProceso->tablaDocs    . '.cdo_clasificacion',
            $tablasProceso->tablaDocs    . '.cdo_lote',
            $tablasProceso->tablaDocs    . '.ofe_id',
            $tablasProceso->tablaDocs    . '.adq_id',
            $tablasProceso->tablaDocs    . '.rfa_id',
            $tablasProceso->tablaDocs    . '.mon_id',
            $tablasProceso->tablaDocs    . '.mon_id_extranjera',
            $tablasProceso->tablaDocs    . '.rfa_prefijo',
            $tablasProceso->tablaDocs    . '.cdo_consecutivo',
            $tablasProceso->tablaDocs    . '.cdo_fecha',
            $tablasProceso->tablaDocs    . '.cdo_hora',
            $tablasProceso->tablaDocs    . '.cdo_vencimiento',
            $tablasProceso->tablaDocs    . '.cdo_documento_referencia',
            $tablasProceso->tablaDocs    . '.cdo_observacion',
            $tablasProceso->tablaDocs    . '.cdo_representacion_grafica_documento',
            $tablasProceso->tablaDocs    . '.cdo_valor_sin_impuestos',
            $tablasProceso->tablaDocs    . '.cdo_valor_sin_impuestos_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_impuestos',
            $tablasProceso->tablaDocs    . '.cdo_total',
            $tablasProceso->tablaDocs    . '.cdo_impuestos_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_valor_a_pagar',
            $tablasProceso->tablaDocs    . '.cdo_valor_a_pagar_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_cufe',
            $tablasProceso->tablaDocs    . '.cdo_signaturevalue',
            $tablasProceso->tablaDocs    . '.cdo_procesar_documento',
            $tablasProceso->tablaDocs    . '.cdo_fecha_procesar_documento',
            $tablasProceso->tablaDocs    . '.cdo_fecha_validacion_dian',
            $tablasProceso->tablaDocs    . '.cdo_fecha_acuse',
            $tablasProceso->tablaDocs    . '.cdo_estado',
            $tablasProceso->tablaDocs    . '.cdo_fecha_estado',
            $tablasProceso->tablaDocs    . '.usuario_creacion',
            $tablasProceso->tablaDocs    . '.fecha_creacion',
            $tablasProceso->tablaDocs    . '.fecha_modificacion',
            $tablasProceso->tablaDocs    . '.estado',
            $tablasProceso->tablaDocs    . '.fecha_actualizacion',

            $tablasProceso->tablaOfes    . '.ofe_id',
            $tablasProceso->tablaOfes    . '.ofe_identificacion',
            $tablasProceso->tablaOfes    . '.ofe_razon_social',
            $tablasProceso->tablaOfes    . '.ofe_nombre_comercial',
            $tablasProceso->tablaOfes    . '.ofe_primer_apellido',
            $tablasProceso->tablaOfes    . '.ofe_segundo_apellido',
            $tablasProceso->tablaOfes    . '.ofe_primer_nombre',
            $tablasProceso->tablaOfes    . '.ofe_otros_nombres',
            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_nombre_completo'),

            $tablasProceso->tablaAdqs    . '.adq_id',
            $tablasProceso->tablaAdqs    . '.adq_identificacion',
            $tablasProceso->tablaAdqs    . '.adq_id_personalizado',
            $tablasProceso->tablaAdqs    . '.adq_razon_social',
            $tablasProceso->tablaAdqs    . '.adq_nombre_comercial',
            $tablasProceso->tablaAdqs    . '.adq_primer_apellido',
            $tablasProceso->tablaAdqs    . '.adq_segundo_apellido',
            $tablasProceso->tablaAdqs    . '.adq_primer_nombre',
            $tablasProceso->tablaAdqs    . '.adq_otros_nombres',
            $tablasProceso->tablaAdqs    . '.adq_informacion_personalizada',
            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as adq_nombre_completo'),

            $tablasProceso->tablaMonedas . '.mon_id',
            $tablasProceso->tablaMonedas . '.mon_codigo',
            $tablasProceso->tablaMonedas . '.mon_descripcion',
            
            'moneda_extranjera.mon_id as mon_id_extranjera',
            'moneda_extranjera.mon_codigo as mon_codigo_extranjera',
            'moneda_extranjera.mon_descripcion as mon_descripcion_extranjera',

            $tablasProceso->tablaTiposOperacion . '.top_id',
            $tablasProceso->tablaTiposOperacion . '.top_codigo',
            $tablasProceso->tablaTiposOperacion . '.top_descripcion',

            $tablasProceso->tablaResoluciones . '.rfa_id as resolucion_rfa_id',
            $tablasProceso->tablaResoluciones . '.rfa_tipo as resolucion_rfa_tipo',
            $tablasProceso->tablaResoluciones . '.rfa_prefijo as resolucion_rfa_prefijo',
            $tablasProceso->tablaResoluciones . '.rfa_resolucion as resolucion_rfa_resolucion',

            'get_notificacion_finalizado.est_id as get_notificacion_finalizado_est_id',
            'get_notificacion_finalizado.est_estado as get_notificacion_finalizado_est_estado',
            'get_notificacion_finalizado.est_resultado as get_notificacion_finalizado_est_resultado',
            'get_notificacion_finalizado.est_ejecucion as get_notificacion_finalizado_est_ejecucion',
            'get_notificacion_finalizado.est_mensaje_resultado as get_notificacion_finalizado_est_mensaje_resultado',
            'get_notificacion_finalizado.est_correos as get_notificacion_finalizado_est_correos',
            'get_notificacion_finalizado.est_fin_proceso as get_notificacion_finalizado_est_fin_proceso',
            'get_notificacion_finalizado.fecha_modificacion as get_notificacion_finalizado_fecha_modificacion',

            'get_documento_aprobado.est_id as get_documento_aprobado_est_id',
            'get_documento_aprobado.est_estado as get_documento_aprobado_est_estado',
            'get_documento_aprobado.est_resultado as get_documento_aprobado_est_resultado',
            'get_documento_aprobado.est_ejecucion as get_documento_aprobado_est_ejecucion',
            'get_documento_aprobado.est_mensaje_resultado as get_documento_aprobado_est_mensaje_resultado',

            'get_documento_aprobado_notificacion.est_id as get_documento_aprobado_notificacion_est_id',
            'get_documento_aprobado_notificacion.est_estado as get_documento_aprobado_notificacion_est_estado',
            'get_documento_aprobado_notificacion.est_resultado as get_documento_aprobado_notificacion_est_resultado',
            'get_documento_aprobado_notificacion.est_ejecucion as get_documento_aprobado_notificacion_est_ejecucion',
            'get_documento_aprobado_notificacion.est_mensaje_resultado as get_documento_aprobado_notificacion_est_mensaje_resultado',

            'get_documento_rechazado.est_id as get_documento_rechazado_est_id',
            'get_documento_rechazado.est_estado as get_documento_rechazado_est_estado',
            'get_documento_rechazado.est_resultado as get_documento_rechazado_est_resultado',
            'get_documento_rechazado.est_ejecucion as get_documento_rechazado_est_ejecucion',
            'get_documento_rechazado.est_mensaje_resultado as get_documento_rechazado_est_mensaje_resultado',

            'get_rechazado.est_id as get_rechazado_est_id',
            'get_rechazado.est_estado as get_rechazado_est_estado',
            'get_rechazado.est_resultado as get_rechazado_est_resultado',
            'get_rechazado.est_ejecucion as get_rechazado_est_ejecucion',
            'get_rechazado.est_mensaje_resultado as get_rechazado_est_mensaje_resultado',
            'get_rechazado.est_motivo_rechazo as get_rechazado_est_motivo_rechazo',

            $tablasProceso->tablaDatosAdicionales . '.cdo_informacion_adicional',

            $tablasProceso->tablaEventosNot . '.evt_evento as notificacion_tipo_evento'
        ];

        return $colSelect;
    }

    /**
     * Obtiene los documentos enviados de DHL Express de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Collection $consulta
     */
    public function getDocumentosEnviadosDhlExpress(Request $request, \stdClass $tablasProceso): Collection {
        $consulta = DB::connection($this->connection)
            ->table($tablasProceso->tablaDocs)
            ->select($this->columnasSelectEnviadosDhlExpress($tablasProceso))
            ->leftJoin($tablasProceso->tablaAdqs, $tablasProceso->tablaDocs . '.adq_id', '=', $tablasProceso->tablaAdqs . '.adq_id')
            ->leftJoin($tablasProceso->tablaMonedas, $tablasProceso->tablaDocs . '.mon_id', '=', $tablasProceso->tablaMonedas . '.mon_id')
            ->leftJoin($tablasProceso->tablaTiposOperacion, $tablasProceso->tablaDocs . '.top_id', '=', $tablasProceso->tablaTiposOperacion . '.top_id')
            ->leftJoin($tablasProceso->tablaMonedas . ' as moneda_extranjera', $tablasProceso->tablaDocs . '.mon_id_extranjera', '=', 'moneda_extranjera.mon_id')
            ->leftJoin($tablasProceso->tablaResoluciones, $tablasProceso->tablaDocs . '.rfa_id', '=', $tablasProceso->tablaResoluciones . '.rfa_id')
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
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_aprobado', ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where(function($query) {
                            $query->where('get_documento_aprobado.est_informacion_adicional', 'not like', '%conNotificacion%')
                                ->orWhere('get_documento_aprobado.est_informacion_adicional->conNotificacion', 'false');
                        });
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado_notificacion', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_aprobado_notificacion', ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where('get_documento_aprobado_notificacion.est_informacion_adicional->conNotificacion', 'true');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_rechazado', ['est_id'], 'DO', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_finalizado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_notificacion_finalizado', ['est_id'], 'NOTIFICACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_do_en_proceso', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_do_en_proceso', ['est_id'], 'DO', '', 'ENPROCESO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_aceptado', ['est_id'], 'ACEPTACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado_t', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_aceptado_t', ['est_id'], 'ACEPTACIONT', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_rechazado', ['est_id'], 'RECHAZO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEventosNot, function($query) use ($tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.cdo_id = ' . $tablasProceso->tablaEventosNot . '.cdo_id')
                    ->where('evt_evento', 'delivery');
            })
            ->where($tablasProceso->tablaDocs . '.ofe_id', $request->ofe_id)
            ->where($tablasProceso->tablaDocs . '.cdo_procesar_documento', 'SI')
            ->whereNotNull($tablasProceso->tablaDocs . '.cdo_fecha_procesar_documento')
            ->when($request->filled('adq_id'), function($query) use ($request, $tablasProceso) {
                if (!is_array($request->adq_id)) {
                    $arrAdqIds = explode(",", $request->adq_id);
                } else {
                    $arrAdqIds = $request->adq_id;
                }

                if(!empty($arrAdqIds))
                    return $query->whereIn($tablasProceso->tablaDocs . '.adq_id', $arrAdqIds);
            })
            ->when($request->filled('buscar'), function($query) use ($request, $tablasProceso) {
                return $this->busquedaRapida($query, $request->buscar, $tablasProceso->tablaDocs, $tablasProceso->tablaAdqs, $tablasProceso->tablaMonedas);
            })
            ->when($request->filled('ofe_filtro') && $request->filled('ofe_filtro_buscar'), function($query) use ($request, $tablasProceso) {
                switch($request->ofe_filtro){
                    case 'cdo_representacion_grafica_documento':
                        return $query->where($tablasProceso->tablaDocs . '.cdo_representacion_grafica_documento', $request->ofe_filtro_buscar);
                        break;
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
            ->when($request->filled('cdo_clasificacion'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_clasificacion', $request->cdo_clasificacion);
            }, function($query) use ($request, $tablasProceso) {
                if($request->filled('proceso') && $request->proceso == 'emision') {
                    return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['FC','NC','ND']);
                } elseif($request->filled('proceso') && $request->proceso == 'documento_soporte') {
                    return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['DS','DS_NC']);
                }
            })
            ->when($request->filled('rfa_prefijo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('cdo_fecha_envio_desde') && $request->filled('cdo_fecha_envio_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha_procesar_documento', [$request->cdo_fecha_envio_desde . ' 00:00:00', $request->cdo_fecha_envio_hasta . ' 23:59:59']);
            })
            ->when($request->filled('cdo_fecha_desde') && $request->filled('cdo_fecha_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha', [$request->cdo_fecha_desde, $request->cdo_fecha_hasta]);
            })
            ->when($request->filled('estado'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.estado', $request->estado);
            })
            ->when($request->filled('estado_dian'), function($query) use ($request, $tablasProceso) {
                    return $query->when(($request->estado_dian == 'aprobado' || $request->estado_dian == 'aprobado_con_notificacion'), function($query) use ($request, $tablasProceso) {
                        return $query->whereExists(function($query) use ($request, $tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                                ->when($request->estado_dian == 'aprobado', function($query) {
                                    return $query->where(function($query) {
                                        $query->where('est_informacion_adicional', 'not like', '%conNotificacion%')
                                            ->orWhere('est_informacion_adicional->conNotificacion', 'false');
                                    });
                                }, function($query) {
                                    return $query->where('est_informacion_adicional->conNotificacion', 'true');
                                });
                        });
                    }, function($query) use ($request, $tablasProceso) {
                        return $query->when(($request->estado_dian == 'rechazado'), function($query) use ($tablasProceso) {
                            return $query->whereExists(function($query) use ($tablasProceso) {
                                $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'FALLIDO', 'FINALIZADO')
                                    ->whereNotExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO');
                                    });
                                });
                        });
                    });
                })
                ->get();

        return $consulta;
    }

    /**
     * Retorna el array de columnas y relaciones a retornar en una consulta de documentos enviados de DHL Express.
     *
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return array
     */
    private function columnasSelectDhlExpressFacturacionManualPickupCash(\stdClass $tablasProceso): array {
        $colSelect = [
            $tablasProceso->tablaDocs    . '.cdo_id',
            $tablasProceso->tablaDocs    . '.cdo_origen',
            $tablasProceso->tablaDocs    . '.cdo_clasificacion',
            $tablasProceso->tablaDocs    . '.cdo_lote',
            $tablasProceso->tablaDocs    . '.ofe_id',
            $tablasProceso->tablaDocs    . '.adq_id',
            $tablasProceso->tablaDocs    . '.rfa_id',
            $tablasProceso->tablaDocs    . '.mon_id',
            $tablasProceso->tablaDocs    . '.mon_id_extranjera',
            $tablasProceso->tablaDocs    . '.rfa_prefijo',
            $tablasProceso->tablaDocs    . '.cdo_consecutivo',
            $tablasProceso->tablaDocs    . '.cdo_fecha',
            $tablasProceso->tablaDocs    . '.cdo_hora',
            $tablasProceso->tablaDocs    . '.cdo_vencimiento',
            $tablasProceso->tablaDocs    . '.cdo_documento_referencia',
            $tablasProceso->tablaDocs    . '.cdo_observacion',
            $tablasProceso->tablaDocs    . '.cdo_representacion_grafica_documento',
            $tablasProceso->tablaDocs    . '.cdo_valor_sin_impuestos',
            $tablasProceso->tablaDocs    . '.cdo_valor_sin_impuestos_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_impuestos',
            $tablasProceso->tablaDocs    . '.cdo_total',
            $tablasProceso->tablaDocs    . '.cdo_impuestos_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_valor_a_pagar',
            $tablasProceso->tablaDocs    . '.cdo_valor_a_pagar_moneda_extranjera',
            $tablasProceso->tablaDocs    . '.cdo_trm',
            $tablasProceso->tablaDocs    . '.cdo_cufe',
            $tablasProceso->tablaDocs    . '.cdo_signaturevalue',
            $tablasProceso->tablaDocs    . '.cdo_procesar_documento',
            $tablasProceso->tablaDocs    . '.cdo_fecha_procesar_documento',
            $tablasProceso->tablaDocs    . '.cdo_fecha_validacion_dian',
            $tablasProceso->tablaDocs    . '.cdo_fecha_acuse',
            $tablasProceso->tablaDocs    . '.cdo_estado',
            $tablasProceso->tablaDocs    . '.cdo_fecha_estado',
            $tablasProceso->tablaDocs    . '.usuario_creacion',
            $tablasProceso->tablaDocs    . '.fecha_creacion',
            $tablasProceso->tablaDocs    . '.fecha_modificacion',
            $tablasProceso->tablaDocs    . '.estado',
            $tablasProceso->tablaDocs    . '.fecha_actualizacion',

            $tablasProceso->tablaOfes    . '.ofe_id',
            $tablasProceso->tablaOfes    . '.ofe_identificacion',
            $tablasProceso->tablaOfes    . '.ofe_razon_social',
            $tablasProceso->tablaOfes    . '.ofe_nombre_comercial',
            $tablasProceso->tablaOfes    . '.ofe_primer_apellido',
            $tablasProceso->tablaOfes    . '.ofe_segundo_apellido',
            $tablasProceso->tablaOfes    . '.ofe_primer_nombre',
            $tablasProceso->tablaOfes    . '.ofe_otros_nombres',
            DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_nombre_completo'),

            $tablasProceso->tablaAdqs    . '.adq_id',
            $tablasProceso->tablaAdqs    . '.adq_identificacion',
            $tablasProceso->tablaAdqs    . '.adq_id_personalizado',
            $tablasProceso->tablaAdqs    . '.adq_razon_social',
            $tablasProceso->tablaAdqs    . '.adq_nombre_comercial',
            $tablasProceso->tablaAdqs    . '.adq_primer_apellido',
            $tablasProceso->tablaAdqs    . '.adq_segundo_apellido',
            $tablasProceso->tablaAdqs    . '.adq_primer_nombre',
            $tablasProceso->tablaAdqs    . '.adq_otros_nombres',
            $tablasProceso->tablaAdqs    . '.adq_informacion_personalizada',
            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as adq_nombre_completo'),

            $tablasProceso->tablaMonedas . '.mon_id',
            $tablasProceso->tablaMonedas . '.mon_codigo',
            $tablasProceso->tablaMonedas . '.mon_descripcion',
            
            'moneda_extranjera.mon_id as mon_id_extranjera',
            'moneda_extranjera.mon_codigo as mon_codigo_extranjera',
            'moneda_extranjera.mon_descripcion as mon_descripcion_extranjera',

            $tablasProceso->tablaTiposOperacion . '.top_id',
            $tablasProceso->tablaTiposOperacion . '.top_codigo',
            $tablasProceso->tablaTiposOperacion . '.top_descripcion',

            $tablasProceso->tablaResoluciones . '.rfa_id as resolucion_rfa_id',
            $tablasProceso->tablaResoluciones . '.rfa_tipo as resolucion_rfa_tipo',
            $tablasProceso->tablaResoluciones . '.rfa_prefijo as resolucion_rfa_prefijo',
            $tablasProceso->tablaResoluciones . '.rfa_resolucion as resolucion_rfa_resolucion',

            'get_notificacion_finalizado.est_id as get_notificacion_finalizado_est_id',
            'get_notificacion_finalizado.est_estado as get_notificacion_finalizado_est_estado',
            'get_notificacion_finalizado.est_resultado as get_notificacion_finalizado_est_resultado',
            'get_notificacion_finalizado.est_ejecucion as get_notificacion_finalizado_est_ejecucion',
            'get_notificacion_finalizado.est_mensaje_resultado as get_notificacion_finalizado_est_mensaje_resultado',
            'get_notificacion_finalizado.est_correos as get_notificacion_finalizado_est_correos',
            'get_notificacion_finalizado.est_fin_proceso as get_notificacion_finalizado_est_fin_proceso',
            'get_notificacion_finalizado.fecha_modificacion as get_notificacion_finalizado_fecha_modificacion',

            'get_documento_aprobado.est_id as get_documento_aprobado_est_id',
            'get_documento_aprobado.est_estado as get_documento_aprobado_est_estado',
            'get_documento_aprobado.est_resultado as get_documento_aprobado_est_resultado',
            'get_documento_aprobado.est_ejecucion as get_documento_aprobado_est_ejecucion',
            'get_documento_aprobado.est_mensaje_resultado as get_documento_aprobado_est_mensaje_resultado',

            'get_documento_aprobado_notificacion.est_id as get_documento_aprobado_notificacion_est_id',
            'get_documento_aprobado_notificacion.est_estado as get_documento_aprobado_notificacion_est_estado',
            'get_documento_aprobado_notificacion.est_resultado as get_documento_aprobado_notificacion_est_resultado',
            'get_documento_aprobado_notificacion.est_ejecucion as get_documento_aprobado_notificacion_est_ejecucion',
            'get_documento_aprobado_notificacion.est_mensaje_resultado as get_documento_aprobado_notificacion_est_mensaje_resultado',

            'get_documento_rechazado.est_id as get_documento_rechazado_est_id',
            'get_documento_rechazado.est_estado as get_documento_rechazado_est_estado',
            'get_documento_rechazado.est_resultado as get_documento_rechazado_est_resultado',
            'get_documento_rechazado.est_ejecucion as get_documento_rechazado_est_ejecucion',
            'get_documento_rechazado.est_mensaje_resultado as get_documento_rechazado_est_mensaje_resultado',

            $tablasProceso->tablaDatosAdicionales . '.cdo_informacion_adicional',

            $tablasProceso->tablaDetalle . '.ddo_id as detalle_ddo_id',
            $tablasProceso->tablaDetalle . '.cdo_id as detalle_cdo_id',
            $tablasProceso->tablaDetalle . '.ddo_tipo_item',
            $tablasProceso->tablaDetalle . '.ddo_descripcion_uno',
            $tablasProceso->tablaDetalle . '.ddo_descripcion_dos',
            $tablasProceso->tablaDetalle . '.ddo_cantidad',
            $tablasProceso->tablaDetalle . '.ddo_valor_unitario',
            $tablasProceso->tablaDetalle . '.ddo_valor_unitario_moneda_extranjera',
            $tablasProceso->tablaDetalle . '.ddo_total',
            $tablasProceso->tablaDetalle . '.ddo_total_moneda_extranjera',
            $tablasProceso->tablaDetalle . '.ddo_informacion_adicional',

            $tablasProceso->tablaCargosDescuentos . '.cdd_id',
            $tablasProceso->tablaCargosDescuentos . '.cdo_id as cargo_descuento_cdo_id',
            $tablasProceso->tablaCargosDescuentos . '.ddo_id as cargo_descuento_ddo_id',
            $tablasProceso->tablaCargosDescuentos . '.cdd_aplica',
            $tablasProceso->tablaCargosDescuentos . '.cdd_tipo',
            $tablasProceso->tablaCargosDescuentos . '.cdd_razon',
            $tablasProceso->tablaCargosDescuentos . '.cdd_valor',

            $tablasProceso->tablaImpuestosItems . '.iid_id',
            $tablasProceso->tablaImpuestosItems . '.ddo_id as impuesto_ddo_id',
            $tablasProceso->tablaImpuestosItems . '.cdo_id as impuesto_cdo_id',
            $tablasProceso->tablaImpuestosItems . '.tri_id',
            $tablasProceso->tablaImpuestosItems . '.iid_tipo',
            $tablasProceso->tablaImpuestosItems . '.iid_porcentaje',
            $tablasProceso->tablaImpuestosItems . '.iid_base',
            $tablasProceso->tablaImpuestosItems . '.iid_base_moneda_extranjera',
            $tablasProceso->tablaImpuestosItems . '.iid_valor',
            $tablasProceso->tablaImpuestosItems . '.iid_valor_moneda_extranjera'
        ];

        return $colSelect;
    }

    /**
     * Obtiene los documentos de DHL Express Facturación Manual Pickup Cash de acuerdo a los parámetros recibidos.
     *
     * @param Request $request Petición
     * @param \stdClass $tablasProceso Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Collection $consulta
     */
    public function getDocumentosDhlExpressFacturacionManualPickupCash(Request $request, \stdClass $tablasProceso): Collection {
        $consulta = DB::connection($this->connection)
            ->table($tablasProceso->tablaDocs)
            ->select($this->columnasSelectDhlExpressFacturacionManualPickupCash($tablasProceso))
            ->leftJoin($tablasProceso->tablaAdqs, $tablasProceso->tablaDocs . '.adq_id', '=', $tablasProceso->tablaAdqs . '.adq_id')
            ->leftJoin($tablasProceso->tablaMonedas, $tablasProceso->tablaDocs . '.mon_id', '=', $tablasProceso->tablaMonedas . '.mon_id')
            ->leftJoin($tablasProceso->tablaTiposOperacion, $tablasProceso->tablaDocs . '.top_id', '=', $tablasProceso->tablaTiposOperacion . '.top_id')
            ->leftJoin($tablasProceso->tablaMonedas . ' as moneda_extranjera', $tablasProceso->tablaDocs . '.mon_id_extranjera', '=', 'moneda_extranjera.mon_id')
            ->leftJoin($tablasProceso->tablaResoluciones, $tablasProceso->tablaDocs . '.rfa_id', '=', $tablasProceso->tablaResoluciones . '.rfa_id')
            ->leftJoin($tablasProceso->tablaAnexos, $tablasProceso->tablaDocs . '.cdo_id', '=', $tablasProceso->tablaAnexos . '.cdo_id')
            ->leftJoin($tablasProceso->tablaDatosAdicionales, $tablasProceso->tablaDocs . '.cdo_id', '=', $tablasProceso->tablaDatosAdicionales . '.cdo_id')
            ->leftJoin($tablasProceso->tablaDetalle, $tablasProceso->tablaDocs . '.cdo_id', '=', $tablasProceso->tablaDetalle . '.cdo_id')
            ->leftJoin($tablasProceso->tablaCargosDescuentos, function($query) use ($tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.cdo_id = ' . $tablasProceso->tablaCargosDescuentos . '.cdo_id')
                    ->whereNull($tablasProceso->tablaCargosDescuentos . '.ddo_id')
                    ->where($tablasProceso->tablaCargosDescuentos . '.cdd_aplica', 'CABECERA')
                    ->where(function($query) use ($tablasProceso) {
                        $query->where($tablasProceso->tablaCargosDescuentos . '.cdd_tipo', 'CARGO')
                            ->orWhere($tablasProceso->tablaCargosDescuentos . '.cdd_tipo', 'DESCUENTO');
                    })
                    ->where($tablasProceso->tablaCargosDescuentos . '.estado', 'ACTIVO')
                    ->orderBy($tablasProceso->tablaCargosDescuentos . '.cdd_numero_linea', 'asc');
            })
            ->leftJoin($tablasProceso->tablaImpuestosItems, function($query) use ($tablasProceso) {
                $query->whereRaw($tablasProceso->tablaDocs . '.cdo_id = ' . $tablasProceso->tablaImpuestosItems . '.cdo_id')
                    ->whereRaw($tablasProceso->tablaDetalle . '.ddo_id = ' . $tablasProceso->tablaImpuestosItems . '.ddo_id')
                    ->where( $tablasProceso->tablaImpuestosItems . '.iid_tipo', 'TRIBUTO')
                    ->where( $tablasProceso->tablaImpuestosItems . '.estado', 'ACTIVO');
            })
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
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_aprobado', ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where(function($query) {
                            $query->where('get_documento_aprobado.est_informacion_adicional', 'not like', '%conNotificacion%')
                                ->orWhere('get_documento_aprobado.est_informacion_adicional->conNotificacion', 'false');
                        });
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_aprobado_notificacion', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_aprobado_notificacion', ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                        ->where('get_documento_aprobado_notificacion.est_informacion_adicional->conNotificacion', 'true');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_documento_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_documento_rechazado', ['est_id'], 'DO', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_notificacion_finalizado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_notificacion_finalizado', ['est_id'], 'NOTIFICACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_do_en_proceso', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_do_en_proceso', ['est_id'], 'DO', '', 'ENPROCESO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_aceptado', ['est_id'], 'ACEPTACION', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_aceptado_t', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_aceptado_t', ['est_id'], 'ACEPTACIONT', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin($tablasProceso->tablaEstados . ' as get_rechazado', function($query) use ($tablasProceso) {
                $query->where(function($query) use ($tablasProceso) {
                    $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, 'get_rechazado', ['est_id'], 'RECHAZO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->where($tablasProceso->tablaDocs . '.ofe_id', $request->ofe_id)
            ->where($tablasProceso->tablaDocs . '.cdo_procesar_documento', 'SI')
            ->whereNotNull($tablasProceso->tablaDocs . '.cdo_fecha_procesar_documento')
            ->when($request->filled('adq_id'), function($query) use ($request, $tablasProceso) {
                if (!is_array($request->adq_id)) {
                    $arrAdqIds = explode(",", $request->adq_id);
                } else {
                    $arrAdqIds = $request->adq_id;
                }

                if(!empty($arrAdqIds))
                    return $query->whereIn($tablasProceso->tablaDocs . '.adq_id', $arrAdqIds);
            })
            ->when($request->filled('buscar'), function($query) use ($request, $tablasProceso) {
                return $this->busquedaRapida($query, $request->buscar, $tablasProceso->tablaDocs, $tablasProceso->tablaAdqs, $tablasProceso->tablaMonedas);
            })
            ->when($request->filled('ofe_filtro') && $request->filled('ofe_filtro_buscar'), function($query) use ($request, $tablasProceso) {
                switch($request->ofe_filtro){
                    case 'cdo_representacion_grafica_documento':
                        return $query->where($tablasProceso->tablaDocs . '.cdo_representacion_grafica_documento', $request->ofe_filtro_buscar);
                        break;
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
            ->when($request->filled('cdo_clasificacion'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_clasificacion', $request->cdo_clasificacion);
            }, function($query) use ($request, $tablasProceso) {
                if($request->filled('proceso') && $request->proceso == 'emision') {
                    return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['FC','NC','ND']);
                } elseif($request->filled('proceso') && $request->proceso == 'documento_soporte') {
                    return $query->whereIn($tablasProceso->tablaDocs . '.cdo_clasificacion', ['DS','DS_NC']);
                }
            })
            ->when($request->filled('rfa_prefijo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('cdo_fecha_envio_desde') && $request->filled('cdo_fecha_envio_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha_procesar_documento', [$request->cdo_fecha_envio_desde . ' 00:00:00', $request->cdo_fecha_envio_hasta . ' 23:59:59']);
            })
            ->when($request->filled('cdo_fecha_desde') && $request->filled('cdo_fecha_hasta'), function($query) use ($request, $tablasProceso) {
                return $query->whereBetween($tablasProceso->tablaDocs . '.cdo_fecha', [$request->cdo_fecha_desde, $request->cdo_fecha_hasta]);
            })
            ->when($request->filled('estado'), function($query) use ($request, $tablasProceso) {
                return $query->where($tablasProceso->tablaDocs . '.estado', $request->estado);
            })
            ->when($request->filled('estado_dian'), function($query) use ($request, $tablasProceso) {
                    return $query->when(($request->estado_dian == 'aprobado' || $request->estado_dian == 'aprobado_con_notificacion'), function($query) use ($request, $tablasProceso) {
                        return $query->whereExists(function($query) use ($request, $tablasProceso) {
                            $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO')
                                ->when($request->estado_dian == 'aprobado', function($query) {
                                    return $query->where(function($query) {
                                        $query->where('est_informacion_adicional', 'not like', '%conNotificacion%')
                                            ->orWhere('est_informacion_adicional->conNotificacion', 'false');
                                    });
                                }, function($query) {
                                    return $query->where('est_informacion_adicional->conNotificacion', 'true');
                                });
                        });
                    }, function($query) use ($request, $tablasProceso) {
                        return $query->when(($request->estado_dian == 'rechazado'), function($query) use ($tablasProceso) {
                            return $query->whereExists(function($query) use ($tablasProceso) {
                                $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'FALLIDO', 'FINALIZADO')
                                    ->whereNotExists(function($query) use ($tablasProceso) {
                                        $query = $this->relacionEstadosDocumentos($query, $tablasProceso->tablaDocs, $tablasProceso->tablaEstados, ['est_id'], 'DO', 'EXITOSO', 'FINALIZADO');
                                    });
                                });
                        });
                    });
                })
                ->get();

        return $consulta;
    }

    /**
     * Verifica si un documento existe en la tabla FAT de emisión.
     *
     * @param int $ofe_id ID del OFE
     * @param int $adq_id ID del Adquirente
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @param int $tde_id ID del tipo de documento electrónico
     * @return null|EtlFatDocumentoDaop
     */
    public function consultarDocumentoFat(int $ofe_id, int $adq_id = 0, string $prefijo, string $consecutivo, int $tde_id = 0) {
        $documento = EtlFatDocumentoDaop::select([
                'cdo_id',
                'cdo_fecha_validacion_dian'
            ])
            ->where('ofe_id', $ofe_id)
            ->when($adq_id != 0, function($query) use ($adq_id) {
                return $query->where('adq_id', $adq_id);
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
     * Verifica si un documento existe en la tabla FAT de emisión mediante el cdo_id.
     *
     * @param int $cdo_id ID del documento
     * @return null|EtlFatDocumentoDaop
     */
    public function consultarDocumentoFatByCdoId(int $cdo_id) {
        $documento = EtlFatDocumentoDaop::select([
                'cdo_id',
                'ofe_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha_validacion_dian'
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
     * @param int $adq_id ID del Adquirente
     * @param string $prefijo Prefijo del documento
     * @param string $consecutivo Consecutivo del documento
     * @param int $tde_id ID del tipo de documento electrónico
     * @param array $relaciones Array con los nombres de las relaciones que deben ser consultadas y retornadas con el documento
     * @param string $procesoOrigen Indica el proceso que da origen al llamado del método, sirve para validar e incluir componentes de información
     * @return null|EtlCabeceraDocumentoDaop
     */
    public function consultarDocumentoHistorico(string $particion, int $ofe_id, int $adq_id = 0, string $prefijo, string $consecutivo, int $tde_id = 0, array $relaciones = [], string $procesoOrigen = '') {
        if(!$this->existeTabla($this->connection, 'etl_cabecera_documentos_' . $particion) || !$this->existeTabla($this->connection, 'etl_estados_documentos_' . $particion) || !$this->existeTabla($this->connection, 'etl_eventos_notificacion_documentos_' . $particion))
            return null;

        if($procesoOrigen == 'documentos-referencia') {
            $selectCabecera     = [];
            $selectOfe          = [];
            $relacionesCabecera = [
                'getConfiguracionResolucionesFacturacion',
                'getParametrosMoneda',
                'getParametrosMonedaExtranjera',
                'getTipoOperacion',
                'getTipoDocumentoElectronico'
            ];
        } else {
            $selectCabecera = [
                'cdo_id',
                'adq_id',
                'cdo_clasificacion',
                'rfa_id',
                'rfa_prefijo',
                'cdo_consecutivo',
                'cdo_fecha',
                'cdo_hora',
                'cdo_cufe',
                'cdo_qr',
                'cdo_signaturevalue',
                'cdo_fecha_validacion_dian',
                'cdo_nombre_archivos',
                'fecha_creacion',
                'estado'
            ];

            $relacionesCabecera = [
                'getConfiguracionResolucionesFacturacion'
            ];

            $selectOfe = [
                'ofe_id',
                'ofe_identificacion',
                DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
            ];
        }

        $tblCabecera = new EtlCabeceraDocumentoDaop();
        $tblCabecera->setTable('etl_cabecera_documentos_' . $particion);

        $documento = $tblCabecera->select((!empty($selectCabecera) ? $selectCabecera : '*'))
            ->with($relacionesCabecera)
            ->where('ofe_id', $ofe_id)
            ->when($adq_id != 0, function($query) use ($adq_id) {
                return $query->where('adq_id', $adq_id);
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

        $selectAdq = [
            'adq_id',
            'adq_identificacion',
            DB::raw('IF(adq_razon_social IS NULL OR adq_razon_social = "", CONCAT(COALESCE(adq_primer_nombre, ""), " ", COALESCE(adq_otros_nombres, ""), " ", COALESCE(adq_primer_apellido, ""), " ", COALESCE(adq_segundo_apellido, "")), adq_razon_social) as nombre_completo')
        ];

        if(in_array('getConfiguracionAdquirente.getParametroPais', $relaciones))
            $selectAdq[] = 'pai_id';

        if(in_array('getConfiguracionAdquirente.getParametroDepartamento', $relaciones))
            $selectAdq[] = 'dep_id';

        if(in_array('getConfiguracionAdquirente.getParametroMunicipio', $relaciones))
            $selectAdq[] = 'mun_id';

        $adquirente = ConfiguracionAdquirente::select($selectAdq)
            ->when(in_array('getConfiguracionAdquirente.getParametroPais', $relaciones), function ($query) {
                return $query->with('getParametroPais');
            })
            ->when(in_array('getConfiguracionAdquirente.getParametroDepartamento', $relaciones), function ($query) {
                return $query->with('getParametroDepartamento');
            })
            ->when(in_array('getConfiguracionAdquirente.getParametroMunicipio', $relaciones), function ($query) {
                return $query->with('getParametroMunicipio');
            })
            ->where('adq_id', $documento->adq_id)
            ->first();

        $documento->getConfiguracionObligadoFacturarElectronicamente = $oferente;
        $documento->getConfiguracionAdquirente                       = $adquirente;

        if(in_array('getDetalleDocumentosDaop', $relaciones)) {
            $tblDetalle = new EtlDetalleDocumentoDaop;
            $tblDetalle->setTable('etl_detalle_documentos_' . $particion);

            $getDetalleDocumentosDaop = $tblDetalle->where('cdo_id', $documento->cdo_id)
                ->when($procesoOrigen == 'documentos-referencia', function ($query) {
                    return $query->with([
                        'getCodigoUnidadMedida',
                        'getClasificacionProducto',
                        'getPrecioReferencia',
                        'getTipoDocumento'
                    ]);
                })
                ->get();

            $documento->getDetalleDocumentosDaop = $getDetalleDocumentosDaop;
        }

        if(in_array('getImpuestosItemsDocumentosDaop', $relaciones)) {
            $tblImpuestos = new EtlImpuestosItemsDocumentoDaop;
            $tblImpuestos->setTable('etl_impuestos_items_documentos_' . $particion);

            $getImpuestosItemsDocumentosDaop = $tblImpuestos->where('cdo_id', $documento->cdo_id)
                ->when($procesoOrigen == 'documentos-referencia', function ($query) {
                    return $query->with([
                        'getTributo'
                    ]);
                })
                ->get();

            $documento->getImpuestosItemsDocumentosDaop = $getImpuestosItemsDocumentosDaop;
        }

        if(in_array('getDadDocumentosDaop', $relaciones)) {
            $tblDadDocumentos = new EtlDatosAdicionalesDocumentoDaop;
            $tblDadDocumentos->setTable('etl_datos_adicionales_documentos_' . $particion);

            $getDadDocumentosDaop = $tblDadDocumentos->where('cdo_id', $documento->cdo_id)
                ->get();

            $documento->getDadDocumentosDaop = $getDadDocumentosDaop;
        }

        if(in_array('getEstadosDocumentosDaop', $relaciones)) {
            $tblEstados = new EtlEstadosDocumentoDaop;
            $tblEstados->setTable('etl_estados_documentos_' . $particion);

            $getEstadosDocumentosDaop = $tblEstados->select(['est_id','cdo_id','est_estado','est_resultado','est_mensaje_resultado','est_correos','est_object','est_informacion_adicional','est_ejecucion','est_inicio_proceso','fecha_creacion'])
                ->where('cdo_id', $documento->cdo_id)
                ->orderBy('est_id', 'desc')
                ->get();

            $documento->getEstadosDocumentosDaop = $getEstadosDocumentosDaop;
        }

        if(in_array('getNotificacionFinalizado', $relaciones)) {
            $tblEstados = new EtlEstadosDocumentoDaop;
            $tblEstados->setTable('etl_estados_documentos_' . $particion);

            $getNotificacionFinalizado = $tblEstados->select(['est_id', 'cdo_id', 'est_estado', 'est_resultado', 'est_mensaje_resultado', 'est_fin_proceso'])
                ->where('cdo_id', $documento->cdo_id)
                ->where('est_estado', 'NOTIFICACION')
                ->where('est_resultado', 'EXITOSO')
                ->where('est_ejecucion', 'FINALIZADO')
                ->orderBy('est_id', 'desc')
                ->first();

            $documento->getNotificacionFinalizado = $getNotificacionFinalizado;
        }

        if(in_array('getEventoNotificacionDocumentoDaop', $relaciones)) {
            $tblEventosNotificacion = new EtlEventoNotificacionDocumentoDaop;
            $tblEventosNotificacion->setTable('etl_eventos_notificacion_documentos_' . $particion);

            $getEventoNotificacionDocumentoDaop = $tblEventosNotificacion->select(['evt_id', 'cdo_id', 'evt_evento', 'evt_correos', 'evt_amazonses_id', 'evt_fecha_hora', 'evt_json'])
                ->where('cdo_id', $documento->cdo_id)
                ->orderBy('fecha_creacion', 'asc')
                ->get();

            $documento->getEventoNotificacionDocumentoDaop = $getEventoNotificacionDocumentoDaop;
        }

        if(in_array('getMediosPagoDocumentosDaop', $relaciones)) {
            $tblMediosPago = new EtlMediosPagoDocumentoDaop;
            $tblMediosPago->setTable('etl_medios_pago_documentos_' . $particion);

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

        if(in_array('getCargosDescuentosDocumentosDaop', $relaciones)) {
            $tblCargosDescuentos = new EtlCargosDescuentosDocumentoDaop;
            $tblCargosDescuentos->setTable('etl_cargos_descuentos_documentos_' . $particion);

            $getCargosDescuentosDocumentosDaop = $tblCargosDescuentos->select()
                ->where('cdo_id', $documento->cdo_id)
                ->when($procesoOrigen == 'documentos-referencia', function ($query) {
                    return $query->with([
                        'getFacturacionWebCargo',
                        'getFacturacionWebDescuento',
                        'getParametrosCodigoDescuento',
                        'getTributo'
                    ]);
                })
                ->orderBy('fecha_creacion', 'asc')
                ->get();

            $documento->getCargosDescuentosDocumentosDaop = $getCargosDescuentosDocumentosDaop;
        }
        
        if(in_array('getAnticiposDocumentosDaop', $relaciones)) {
            $tblAnticipos = new EtlAnticiposDocumentoDaop;
            $tblAnticipos->setTable('etl_anticipos_documentos_' . $particion);

            $getAnticiposDocumentosDaop = $tblAnticipos->select()
                ->where('cdo_id', $documento->cdo_id)
                ->orderBy('fecha_creacion', 'asc')
                ->get();

            $documento->getAnticiposDocumentosDaop = $getAnticiposDocumentosDaop;
        }
        
        return $documento;
    }

    /**
     * Obtiene un documento anexo relacionado con un documento electrónico que pertenece a la data histórica.
     *
     * @param EtlCabeceraDocumentoDaop $documento Documento en tabla FAT
     * @param string $particion Partición en donde se encuentra el documento
     * @param int $dan_id ID del documento anexo
     * @return null|\stdObject
     */
    public function obtenerDocumentoAnexoHistorico(EtlCabeceraDocumentoDaop $documento, string $particion, int $dan_id) {
        $tblAnexos = new EtlDocumentoAnexoDaop;
        $tblAnexos->setTable('etl_documentos_anexos_' . $particion);

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
        $tblAnexos = new EtlDocumentoAnexoDaop;
        $tblAnexos->setTable('etl_documentos_anexos_' . $particion);

        $tblAnexos->where('cdo_id', $cdo_id)
            ->where('dan_id', $dan_id)
            ->delete();
    }
}
