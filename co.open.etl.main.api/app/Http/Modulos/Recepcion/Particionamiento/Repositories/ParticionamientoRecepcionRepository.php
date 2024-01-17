<?php
namespace App\Http\Modulos\Recepcion\Particionamiento\Repositories;

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
use Facades\openEtl\Tenant\Servicios\Recepcion\TenantRecepcionService;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepFatDocumentosDaop\RepFatDocumentoDaop;
use App\Http\Modulos\Recepcion\Particionamiento\Helpers\HelperRecepcionParticionamiento;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Clase encargada del procesamiento lógico frente al motor de base de datos.
 */
class ParticionamientoRecepcionRepository {
    use GruposTrabajoTrait;
    /**
     * Nombre de la conexión Tenant por defecto a la base de datos.
     */
    public const CONEXION = 'conexion01';

    /**
     * Nombre de la tasbla FAT.
     */
    public const TABLAFAT = 'rep_fat_documentos_daop';

    /**
     * Array de tablas que deben ser particionadas sin el sufijo daop.
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
     * Array de nombres cortos que se pueden aplicar a relaciones o índices.
     * 
     * Esto es útil toda vez que el motor de base de datos restringe longitudes a 64 caracteres y al crear las nuevas tablas de particiones
     * hay índices y FK que se revientan por la longitud de caracteres, tambien permite normalizar los nombres de índices y FK al interior
     * del schema de base de datos para evitar errores por nombres duplicados
     *
     * @var array
     */
    protected $arrNombresCortos = [
        '_rep_cabecera_documentos_'          => '_rep_cabecera_',
        '_rep_datos_adicionales_documentos_' => '_rep_dad_',
        '_rep_documentos_anexos_'            => '_rep_dan_',
        '_rep_estados_documentos_'           => '_rep_est_',
        '_rep_medios_pago_documentos_'       => '_rep_men_'
    ];

    public function __construct() {
        TenantTrait::GetVariablesSistemaTenant();
    }

    /**
     * Retorna la información de conexión a la base de datos relacionada con el usuario autenticado.
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
     * Establece una conexión a una base de datos Tenant.
     *
     * @param AuthBaseDatos $baseDatos Instancia de la base de datos a la que se debe conectar
     * @return void
     */
    public function generarConexionTenant(?AuthBaseDatos $baseDatos = null): void {
        if(empty($baseDatos))
            $baseDatos = $this->getDataBase();

        DB::disconnect(self::CONEXION);

        // Se establece la conexión con la base de datos
        TenantDatabase::setTenantConnection(
            self::CONEXION,
            $baseDatos->bdd_host,
            $baseDatos->bdd_nombre,
            env('DB_USERNAME'),
            env('DB_PASSWORD')
        );
    }

    /**
     * Verifica la existencia de una tabla de acuerdo a la conexión establecida.
     *
     * @param string $conexion Nombre de la conexión actual a la base de datos tenant
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
        // Genera la sentencia SQL que permite crear una tabla particionada a partir de la estructura de la tabla daop (incluyendo índices y llaves foráneas)
        $crearTabla = DB::connection($conexion)
            ->select(DB::raw('SHOW CREATE TABLE ' . $tablaParticionar . 'daop'));

        $crearTabla = (array) $crearTabla[0];
        $crearTabla = str_replace(
            [
                $tablaParticionar . 'daop',
                'rep_cabecera_documentos_daop',
                'fk1_rep_medios_pago_documentos_'
            ], 
            [
                $tablaParticionar . $periodo,
                'rep_cabecera_documentos_' . $periodo,
                'fk1' . $this->arrNombresCortos['_rep_medios_pago_documentos_'] . $periodo . '_'
            ],
            $crearTabla['Create Table']
        );

        foreach($this->arrNombresCortos as $nombreLargo => $nombreCorto) {
            $crearTabla = str_replace([$nombreLargo, $periodo . '_id'], [$nombreCorto, $periodo . '_cdo_id'], $crearTabla);
        }

        // Crea la nueva tabla de partición
        DB::connection($conexion)
            ->statement($crearTabla);
    }
    
    /**
     * Retorna un array de columnas coincidentes entre una tabla de data operativa y una tabla particionada.
     *
     * @param string $tablaParticion Nombre (sin sufijo) de la tabla particionada
     * @param string $particion Nombre (sufijo) para la partición
     * @param bool $tablaFat Indica si la lógica se aplica a la tabla FAT o no
     * @return array Array de las columnas coincidentes
     */
    public function diferenciasColumnasTabla(string $tablaParticion, string $particion, bool $tablaFat = false): array {
        if($tablaFat) {
            $columnasDaop = Schema::connection(self::CONEXION)->getColumnListing($tablaParticion . 'daop');
            $columnasHist = Schema::connection(self::CONEXION)->getColumnListing($particion);
        } else {
            $columnasDaop = Schema::connection(self::CONEXION)->getColumnListing($tablaParticion . 'daop');
            $columnasHist = Schema::connection(self::CONEXION)->getColumnListing($tablaParticion . $particion);
        }

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
     * Actualiza la información de las columnas de la tabla FAT que dependen de información de tablas relacionadas con cabecera.
     *
     * @param RepCabeceraDocumentoDaop $documento Instancia del documento en procesamiento
     * @return void
     */
    public function actualizarDocumentoFat(RepCabeceraDocumentoDaop $documento): void {
        TenantRecepcionService::recepcionActualizarRegistroFat($documento->cdo_id, [
            'fpa_id'                => isset($documento->getMediosPagoDocumentosDaop[0]->fpa_id) ? $documento->getMediosPagoDocumentosDaop[0]->fpa_id : null,
            'cdo_documentos_anexos' => $documento->get_documentos_anexos_count > 0 ? 1 : 0
        ]);

        $estadoRdi = $documento->getEstadosDocumentosDaop->where('est_estado', 'RDI')->sortByDesc('est_id')->values()->first();
        if($estadoRdi)
            TenantRecepcionService::procesarEstadoRdi($estadoRdi);

        $getStatus = $documento->getEstadosDocumentosDaop->where('est_estado', 'GETSTATUS')->sortByDesc('est_id')->values()->first();
        if($getStatus)
            TenantRecepcionService::procesarEstadoGetStatus($getStatus);

        $ublAcuseRecibo = $documento->getEstadosDocumentosDaop->where('est_estado', 'UBLACUSERECIBO')->sortByDesc('est_id')->values()->first();
        if($ublAcuseRecibo)
            TenantRecepcionService::procesarEstadoAcuseRecibo($ublAcuseRecibo);

        $acuseRecibo = $documento->getEstadosDocumentosDaop->where('est_estado', 'ACUSERECIBO')->sortByDesc('est_id')->values()->first();
        if($acuseRecibo)
            TenantRecepcionService::procesarEstadoAcuseRecibo($acuseRecibo);

        $ublReciboBien = $documento->getEstadosDocumentosDaop->where('est_estado', 'UBLRECIBOBIEN')->sortByDesc('est_id')->values()->first();
        if($ublReciboBien)
            TenantRecepcionService::procesarEstadoReciboBien($ublReciboBien);

        $reciboBien = $documento->getEstadosDocumentosDaop->where('est_estado', 'RECIBOBIEN')->sortByDesc('est_id')->values()->first();
        if($reciboBien)
            TenantRecepcionService::procesarEstadoReciboBien($reciboBien);

        $ublAceptacion = $documento->getEstadosDocumentosDaop->where('est_estado', 'UBLACEPTACION')->sortByDesc('est_id')->values()->first();
        $aceptacion    = $documento->getEstadosDocumentosDaop->where('est_estado', 'ACEPTACION')->sortByDesc('est_id')->values()->first();
        $aceptacionT   = $documento->getEstadosDocumentosDaop->where('est_estado', 'ACEPTACIONT')->sortByDesc('est_id')->values()->first();
        $rechazo       = $documento->getEstadosDocumentosDaop->where('est_estado', 'RECHAZO')->sortByDesc('est_id')->values()->first();

        if($aceptacion)
            TenantRecepcionService::procesarEstadoEventosDian($aceptacion);
        elseif($ublAceptacion)
            TenantRecepcionService::procesarEstadoEventosDian($ublAceptacion);

        if(
            (!$aceptacion || (
                $aceptacion &&
                $aceptacion->est_resultado == 'FALLIDO'
            )) &&
            $aceptacionT
        )
            TenantRecepcionService::procesarEstadoEventosDian($aceptacionT);

        if(
            (!$aceptacion || (
                $aceptacion &&
                $aceptacion->est_resultado == 'FALLIDO'
            )) &&
            $rechazo
        )
            TenantRecepcionService::procesarEstadoEventosDian($rechazo);

        $notAcuse = $documento->getEstadosDocumentosDaop->where('est_estado', 'NOTACUSERECIBO')->sortByDesc('est_id')->values()->first();
        if($notAcuse)
                TenantRecepcionService::procesarEstadoNotificacionEventoDian($notAcuse);

        $notRecibo = $documento->getEstadosDocumentosDaop->where('est_estado', 'NOTRECIBOBIEN')->sortByDesc('est_id')->values()->first();
        if($notRecibo)
                TenantRecepcionService::procesarEstadoNotificacionEventoDian($notRecibo);

        $notAceptacion  = $documento->getEstadosDocumentosDaop->where('est_estado', 'NOTACEPTACION')->sortByDesc('est_id')->values()->first();
        $notAceptacionT = $documento->getEstadosDocumentosDaop->where('est_estado', 'NOTACEPTACIONT')->sortByDesc('est_id')->values()->first();
        $notRechazo     = $documento->getEstadosDocumentosDaop->where('est_estado', 'NOTRECHAZO')->sortByDesc('est_id')->values()->first();

        if($notAceptacion)
            TenantRecepcionService::procesarEstadoNotificacionEventoDian($notAceptacion);
        elseif($notAceptacionT)
            TenantRecepcionService::procesarEstadoNotificacionEventoDian($notAceptacionT);
        elseif($notRechazo)
            TenantRecepcionService::procesarEstadoNotificacionEventoDian($notRechazo);

        $transmisionErp = $documento->getEstadosDocumentosDaop->where('est_estado', 'TRANSMISIONERP')->sortByDesc('est_id')->values()->first();
        if($transmisionErp)
            TenantRecepcionService::procesarEstadoTransmisionErp($transmisionErp);

        $transmisionOpenComex = $documento->getEstadosDocumentosDaop->where('est_estado', 'OPENCOMEXCXP')->sortByDesc('est_id')->values()->first();
        if($transmisionOpenComex)
            TenantRecepcionService::procesarEstadoTransmisionOpenComex($transmisionOpenComex);

        $validacion = $documento->getEstadosDocumentosDaop->where('est_estado', 'VALIDACION')->sortByDesc('est_id')->values()->first();
        if($validacion)
            TenantRecepcionService::procesarEstadoValidacion($validacion);
    }

    /**
     * Copia un documento a la tabla FAT.
     * 
     * Este método no mueve data a tablas de particionamiento, simplemente se encarga de poblar la tabla FAT de una base de datos
     *
     * @param RepCabeceraDocumentoDaop $documento Instancia del documento a copiar
     * @return void
     */
    public function copiarDocumentoTablaFat(RepCabeceraDocumentoDaop $documento): void {
        $columnasFat = implode(',', $this->diferenciasColumnasTabla('rep_cabecera_documentos_', self::TABLAFAT, true));

        $builder = DB::connection(self::CONEXION);
        $builder->statement('SET FOREIGN_KEY_CHECKS=0');

        // Verifica si el autoincremental del documento existe en la tabla FAT
        $existe = $builder->select("SELECT cdo_id FROM " . self::TABLAFAT . " WHERE cdo_id={$documento->cdo_id}");

        if(empty($existe))
            $builder->insert("INSERT INTO " . self::TABLAFAT . " ({$columnasFat}) SELECT {$columnasFat} FROM rep_cabecera_documentos_daop WHERE cdo_id={$documento->cdo_id}");

        // Este proceso permite actualizar todas las columnas de un documento en la Tabla FAT con la información del documento en las tablas daop
        $this->actualizarDocumentoFat($documento);

        // Reestablece los valor originales de las columnas fecha_modificacion y fecha_actualizacion que fueron cambiadas en el proceso
        $builder->update("UPDATE " . self::TABLAFAT . " SET fecha_modificacion='{$documento->fecha_modificacion}', fecha_actualizacion='{$documento->fecha_actualizacion}' WHERE cdo_id={$documento->cdo_id}");

        $builder->statement('SET FOREIGN_KEY_CHECKS=0');
    }

    /**
     * Traslada un documento de la data operativa a las tablas particionadas.
     * 
     * Al crear el registro en la tabla FAT, el proceso debe verificar información de estados del documentos para actualizar las columnas correspondientes
     *
     * @param RepCabeceraDocumentoDaop $documento Instancia del documento a trasladar
     * @param string $particion Partición destino
     * @return void
     */
    public function trasladarDocumentoHistorico(RepCabeceraDocumentoDaop $documento, string $particion): void {
        $columnasFat = implode(',', $this->diferenciasColumnasTabla('rep_cabecera_documentos_', self::TABLAFAT, true));

        $builder = DB::connection(self::CONEXION);
        $builder->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach($this->arrTablasParticionar as $tablaParticion) {
            if(!$this->existeTabla(self::CONEXION, $tablaParticion . $particion))
                $this->crearTablaParticionamiento(self::CONEXION, $tablaParticion, $particion);

            $columnasSelect = implode(',', $this->diferenciasColumnasTabla($tablaParticion, $particion));

            if($tablaParticion == 'rep_cabecera_documentos_') {
                // Verifica si el autoincremental del documento ya existe en la tabla FAT
                $existe = $builder->select("SELECT cdo_id FROM " . self::TABLAFAT . " WHERE cdo_id={$documento->cdo_id}");

                if(empty($existe))
                    $builder->insert("INSERT INTO " . self::TABLAFAT . " ({$columnasFat}) SELECT {$columnasFat} FROM {$tablaParticion}daop WHERE cdo_id={$documento->cdo_id}");

                // Este proceso permite actualizar todas las columnas de un documento en la Tabla FAT con la información del documento en las tablas daop
                $this->actualizarDocumentoFat($documento);
            }

            // Verifica si el autoincremental del documento ya existe en la tabla particionada
            $existe = $builder->select("SELECT cdo_id FROM {$tablaParticion}{$particion} WHERE cdo_id={$documento->cdo_id}");

            if(!empty($existe))
                $builder->delete("DELETE FROM {$tablaParticion}{$particion} WHERE cdo_id={$documento->cdo_id}");

            $builder->insert("INSERT INTO {$tablaParticion}{$particion} (SELECT {$columnasSelect} FROM {$tablaParticion}daop WHERE cdo_id={$documento->cdo_id})");
        }

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

            $builder->update("UPDATE " . self::TABLAFAT . " SET cdo_data_operativa=1 WHERE cdo_id={$documento->cdo_id}");
        // Si deshacer es false, se copiaron todos los registros de manera completa en todas las tablas, se deben eliminar todos los registros de las tablas daop
        } else {
            foreach($this->arrTablasParticionar as $tablaParticion) {
                $builder->delete("DELETE FROM {$tablaParticion}daop WHERE cdo_id={$documento->cdo_id}");
            }

            $builder->update("UPDATE " . self::TABLAFAT . " SET cdo_data_operativa=0 WHERE cdo_id={$documento->cdo_id}");
        }

        $builder->statement('SET FOREIGN_KEY_CHECKS=0');
    }

    /**
     * Obtiene los IDs de forma de pago conforme al código recibido.
     *
     * @param string $formaPago Código de la forma de Pago
     * @param string $tipoDocumento Tipo de documentos a los cuales aplica
     * @return array
     */
    private function obtenerIdsFormasPago(string $formaPago, string $tipoDocumento): array {
        return ParametrosFormaPago::select('fpa_id')
            ->where('fpa_codigo', $formaPago)
            ->where('fpa_aplica_para', 'LIKE', '%' . $tipoDocumento . '%')
            ->where('estado', 'ACTIVO')
            ->get()
            ->pluck('fpa_id')
            ->values()
            ->toArray();
    }

    /**
     * Establece algunos parámetros requeridos para la paginación por cursor.
     *
     * @param Request $request Parámetros de la petición
     * @return \stdClass
     */
    private function setParametrosPaginador(Request $request): \stdClass {
        $pagSiguiente = $request->filled('pag_siguiente') ? json_decode(base64_decode($request->pag_siguiente)) : json_decode(json_encode([]));
        $pagAnterior  = $request->filled('pag_anterior') ? json_decode(base64_decode($request->pag_anterior)) : json_decode(json_encode([]));

        if(!$request->filled('pag_siguiente') && !$request->filled('pag_anterior') && strtolower($request->ordenDireccion) == 'asc') {
            $signoComparacion = '>';
            $idComparacion    = 0;
            $ordenDireccion   = 'asc';
        } elseif(!$request->filled('pag_siguiente') && !$request->filled('pag_anterior') && strtolower($request->ordenDireccion) == 'desc') {
            $signoComparacion = '<=';
            $idComparacion    = RepFatDocumentoDaop::select('cdo_id')->orderBy('cdo_id', 'desc')->first()->cdo_id; // Último cdo_id de toda la tabla
            $ordenDireccion   = 'desc';
        }
        // Define signo de comparación para el cdo_id teniendo en cuenta:
        // - Si es página siguiente y el ordenamiento es asc, el signo es >
        // - Si es página anterior y el ordenamiento es asc, el signo es <
        // - Si es página siguiente y el ordenamiento es desc, el signo es <
        // - Si es página anterior y el ordenamiento es desc, el signo es >
        elseif(isset($pagSiguiente->apuntarSiguientes) && $pagSiguiente->apuntarSiguientes && $request->ordenDireccion == 'asc') {
            $signoComparacion = '>';
            $idComparacion    = $pagSiguiente->cdoId;
            $ordenDireccion   = 'asc';
        } elseif(isset($pagAnterior->apuntarSiguientes) && !$pagAnterior->apuntarSiguientes && $request->ordenDireccion == 'asc') {
            $signoComparacion = '<';
            $idComparacion    = $pagAnterior->cdoId;
            $ordenDireccion   = 'desc';
        } elseif(isset($pagSiguiente->apuntarSiguientes) && $pagSiguiente->apuntarSiguientes && $request->ordenDireccion == 'desc') {
            $signoComparacion = '<';
            $idComparacion    = $pagSiguiente->cdoId;
            $ordenDireccion   = 'desc';
        } elseif(isset($pagAnterior->apuntarSiguientes) && !$pagAnterior->apuntarSiguientes && $request->ordenDireccion == 'desc') {
            $signoComparacion = '>';
            $idComparacion    = $pagAnterior->cdoId;
            $ordenDireccion   = 'asc';
        }

        return json_decode(json_encode([
            'signoComparacion' => $signoComparacion,
            'idComparacion'    => $idComparacion,
            'ordenDireccion'   => $ordenDireccion
        ]));
    }

    /**
     * Teniendo en cuenta la consulta que se está realizando, se realiza una consulta adicional para determinar is existe información antes o depués
     * de los resultados a retornar para definir si se debe o no mostrar la información del link anterior o siguiente.
     *
     * @param EloquentBuilder $consultaDocumentos Clon de la consulta en procesamiento
     * @param integer $cdoId ID a tener en cuenta para la consulta
     * @param string $fechaDesde Fecha de inicio de consulta
     * @param string $fechaHasta Fecha de final de consulta
     * @param string $columnaOrdenamiento Columna mediante la cual se realiza el ordenamiento de la consulta
     * @param string $ordenamientoConsulta Ordenamiento de la consulta
     * @param string $tipoLink Tipo de link  a mostrar
     * @return bool
     */
    public function mostrarLinkAnteriorSiguiente(EloquentBuilder $consultaDocumentos, int $cdoId, string $fechaDesde, string $fechaHasta, string $columnaOrdenamiento, string $ordenamientoConsulta, string $tipoLink): bool {
        switch($tipoLink) {
            case 'anterior':
                $consulta = $consultaDocumentos->whereBetween('cdo_fecha', [$fechaDesde, $fechaHasta])
                    ->where('cdo_id', ($ordenamientoConsulta == 'asc' ? '<' : '>'), $cdoId);
                break;
            case 'siguiente':
                $consulta = $consultaDocumentos->whereBetween('cdo_fecha', [$fechaDesde, $fechaHasta])
                    ->where('cdo_id', ($ordenamientoConsulta == 'asc' ? '>' : '<'), $cdoId);
                break;
        }

        $consulta = $consulta->orderByColumn($columnaOrdenamiento, $ordenamientoConsulta)
            ->orderBy('cdo_id', $ordenamientoConsulta)
            ->first();

        if($consulta)
            return true;
        else
            return false;
    }

    /**
     * Permite filtrar los documentos electrónicos teniendo en cuenta la configuración de grupos de trabajo a nivel de usuario autenticado y proveedores.
     * 
     * Si el usuario autenticado esta configurado en algún grupo de trabajo, solamente se deben listar
     * documentos electrónicos de los proveedores asociados con ese mismo grupo o grupos de trabajo
     * Si el usuario autenticado no esta configurado en ningún grupo de trabajo, se verifica si el usuario está
     * relacionado directamente con algún proveedor para mostrar solamente documentos de esos proveedores
     * Si no se da ninguna de las anteriores condiciones, el usuario autenticado debe poder ver todos los documentos electrónicos de todos los proveedores
     *
     * @param Builder|EloquentBuilder $query Consulta que está en procesamiento
     * @param int $ofeId ID del OFE para el cual se está haciendo la consulta
     * @param bool $usuarioGestor Indica cuando se debe tener en cuenta que se trate de un usuario gestor
     * @param bool $usuarioValidador Indica cuando se debe tener en cuenta que se trate de un usuario validador
     * @param bool $queryBuilder Indica que el método es llamado desde una construcción con QueryBuilder y no con Eloquent
     * @param \StdClass $tablasProcesos Clase estándar que contiene las propiedades relacionadas con las tablas de openETL a utilizar en el proceso
     * @return Builder|EloquentBuilder Retorna una instancia del Query Builder cuando $queryBuilder es true, de lo contrario retorna una instancia de Eloquent Builder
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
                        SELECT gtp_id, gtr_id, pro_id FROM ' . $tablasProceso->tablaGruposTrabajoProveedor . '
                        WHERE ' . $tablasProceso->tablaProveedores . '.pro_id = ' . $tablasProceso->tablaGruposTrabajoProveedor . '.pro_id
                        AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.gtr_id IN (' . implode(',', $gruposTrabajoUsuario) . ')
                        AND ' . $tablasProceso->tablaGruposTrabajoProveedor . '.estado = "ACTIVO"
                    )');
            else
                $query->whereHas('getProveedorGruposTrabajo', function($gtrProveedor) use ($gruposTrabajoUsuario) {
                    $gtrProveedor->select(['gtp_id', 'gtr_id', 'pro_id'])
                        ->whereIn('gtr_id', $gruposTrabajoUsuario)
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
     * Retonar el modelo del OFE conla relación de los grupos de trabajo.
     *
     * @param int $ofeId ID del OFE
     * @return ConfiguracionObligadoFacturarElectronicamente
     */
    private function getOfeGrupos(int $ofeId): ConfiguracionObligadoFacturarElectronicamente {
        return ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_recepcion_fnc_activo', 'ofe_recepcion_fnc_configuracion'])
            ->where('ofe_id', $ofeId)
            ->with([
                'getGruposTrabajo' => function($query) {
                    $query->select(['gtr_id', 'ofe_id'])
                        ->where('estado', 'ACTIVO');
                }
            ])
            ->first();
    }

    /**
     * Consulta de los documentos recibidos.
     *
     * @param Request $request Parámetros de la petición
     * @return Collection
     */
    public function consultarDocumentosRecibidos(Request $request): Collection {
        $columnasSelect = [
            'rep_fat_documentos_daop.cdo_id',
            'rep_fat_documentos_daop.cdo_origen',
            'rep_fat_documentos_daop.cdo_clasificacion',
            'rep_fat_documentos_daop.gtr_id',
            'rep_fat_documentos_daop.tde_id',
            'rep_fat_documentos_daop.top_id',
            'rep_fat_documentos_daop.fpa_id',
            'rep_fat_documentos_daop.cdo_lote',
            'rep_fat_documentos_daop.ofe_id',
            'rep_fat_documentos_daop.pro_id',
            'rep_fat_documentos_daop.rfa_prefijo',
            'rep_fat_documentos_daop.cdo_consecutivo',
            'rep_fat_documentos_daop.cdo_fecha',
            'rep_fat_documentos_daop.cdo_cufe',
            'rep_fat_documentos_daop.mon_id',
            'rep_fat_documentos_daop.mon_id_extranjera',
            'rep_fat_documentos_daop.cdo_valor_a_pagar',
            'rep_fat_documentos_daop.cdo_valor_a_pagar_moneda_extranjera',
            'rep_fat_documentos_daop.cdo_documentos_anexos',
            'rep_fat_documentos_daop.cdo_rdi',
            'rep_fat_documentos_daop.cdo_fecha_validacion_dian',
            'rep_fat_documentos_daop.cdo_estado_dian',
            'rep_fat_documentos_daop.cdo_get_status',
            'rep_fat_documentos_daop.cdo_get_status_error',
            'rep_fat_documentos_daop.cdo_acuse_recibo',
            'rep_fat_documentos_daop.cdo_acuse_recibo_error',
            'rep_fat_documentos_daop.cdo_recibo_bien',
            'rep_fat_documentos_daop.cdo_recibo_bien_error',
            'rep_fat_documentos_daop.cdo_estado_eventos_dian',
            'rep_fat_documentos_daop.cdo_estado_eventos_dian_fecha',
            'rep_fat_documentos_daop.cdo_estado_eventos_dian_resultado',
            'rep_fat_documentos_daop.cdo_notificacion_evento_dian',
            'rep_fat_documentos_daop.cdo_notificacion_evento_dian_resultado',
            'rep_fat_documentos_daop.cdo_validacion',
            'rep_fat_documentos_daop.cdo_validacion_valor',
            'rep_fat_documentos_daop.cdo_transmision_erp',
            'rep_fat_documentos_daop.cdo_transmision_opencomex',
            'rep_fat_documentos_daop.cdo_usuario_responsable',
            'rep_fat_documentos_daop.cdo_usuario_responsable_recibidos',
            'rep_fat_documentos_daop.cdo_data_operativa',
            'rep_fat_documentos_daop.estado',
            'rep_fat_documentos_daop.fecha_creacion',
            'rep_fat_documentos_daop.cdo_validacion_valor_campos_adicionales_fondos',
            'rep_fat_documentos_daop.cdo_validacion_valor_campos_adicionales_oc_sap',
            'rep_fat_documentos_daop.cdo_validacion_valor_campos_adicionales_posicion',
            'rep_fat_documentos_daop.cdo_validacion_valor_campos_adicionales_hoja_de_entrada',
            'rep_fat_documentos_daop.cdo_validacion_valor_campos_adicionales_observacion_validacion',
        ];

        if($request->filled('excel') && $request->excel == true) {
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasCabeceraSelect('get_cabecera_documentos', [
                'rfa_resolucion',
                'cdo_hora',
                'cdo_vencimiento',
                'cdo_observacion',
                'cdo_valor_sin_impuestos',
                'cdo_impuestos',
                'cdo_cargos',
                'cdo_descuentos',
                'cdo_redondeo',
                'cdo_valor_a_pagar',
                'cdo_anticipo',
                'cdo_retenciones'
            ]));

            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasOtrasTablasTenantSelect('get_tipo_documento_electronico', ['tde_codigo', 'tde_descripcion']));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasOtrasTablasTenantSelect('get_tipo_operacion', ['top_codigo', 'top_descripcion']));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasOtrasTablasTenantSelect('get_moneda', ['mon_codigo']));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasEstadosSelect('get_rdi_exitoso'));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasEstadosSelect('get_estado_dian_aprobado'));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasEstadosSelect('get_estado_dian_aprobado_con_notificacion'));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasEstadosSelect('get_estado_dian_rechazado'));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasEstadosSelect('get_evento_dian_rechazo'));
            $columnasSelect = array_merge($columnasSelect, HelperRecepcionParticionamiento::agregarColumnasEstadosSelect('get_transmision_erp'));
        }

        $ofe                = $this->getOfeGrupos($request->ofe_id);
        $paginador          = $this->setParametrosPaginador($request);
        $consultaDocumentos = RepFatDocumentoDaop::select($columnasSelect)
            ->where('rep_fat_documentos_daop.ofe_id', $request->ofe_id)
            ->when($request->filled('pro_id') && !empty($request->pro_id), function ($query) use ($request) {
                $query->whereIn('rep_fat_documentos_daop.pro_id', $request->pro_id);
            })
            ->when($request->filled('cdo_lote'), function ($query) use ($request) {
                $query->where('rep_fat_documentos_daop.cdo_lote', $request->cdo_lote);
            })
            ->when($request->filled('cdo_cufe'), function ($query) use ($request) {
                $query->where('rep_fat_documentos_daop.cdo_cufe', $request->cdo_cufe);
            })
            ->when($request->filled('cdo_origen'), function ($query) use ($request) {
                $query->where('rep_fat_documentos_daop.cdo_origen', $request->cdo_origen);
            })
            ->when($request->filled('cdo_clasificacion'), function ($query) use ($request) {
                $query->where('rep_fat_documentos_daop.cdo_clasificacion', $request->cdo_clasificacion);
            })
            ->when($request->filled('estado'), function ($query) use ($request) {
                $query->where('rep_fat_documentos_daop.estado', $request->estado);
            })
            ->when($request->filled('rfa_prefijo'), function ($query) use ($request) {
                $query->where('rep_fat_documentos_daop.rfa_prefijo', $request->rfa_prefijo);
            })
            ->when($request->filled('cdo_consecutivo'), function ($query) use ($request) {
                $query->where('rep_fat_documentos_daop.cdo_consecutivo', $request->cdo_consecutivo);
            })
            ->when($request->filled('forma_pago'), function ($query) use ($request) {
                $query->whereIn('rep_fat_documentos_daop.fpa_id', $this->obtenerIdsFormasPago($request->forma_pago, 'DE'));
            })
            ->when($request->filled('cdo_fecha_validacion_dian_desde') && $request->filled('cdo_fecha_validacion_dian_hasta'), function ($query) use ($request) {
                $query->whereBetween('rep_fat_documentos_daop.cdo_fecha_validacion_dian', [$request->cdo_fecha_validacion_dian_desde . ' 00:00:00', $request->cdo_fecha_validacion_dian_hasta . ' 23:59:59']);
            })
            ->when($request->filled('estado_dian') && !empty($request->estado_dian), function($query) use ($request) {
                $query->whereIn('cdo_estado_dian', $request->estado_dian);
            })
            ->when($request->filled('estado_acuse_recibo'), function($query) use ($request) {
                if($request->estado_acuse_recibo == 'SI')
                    $query->whereNotNull('cdo_acuse_recibo');
                elseif($request->estado_acuse_recibo == 'NO')
                    $query->whereNull('cdo_acuse_recibo');
            })
            ->when($request->filled('estado_recibo_bien'), function($query) use ($request) {
                if($request->estado_recibo_bien == 'SI')
                    $query->whereNotNull('cdo_recibo_bien');
                elseif($request->estado_recibo_bien == 'NO')
                    $query->whereNull('cdo_recibo_bien');
            })
            ->when($request->filled('estado_eventos_dian') && !empty($request->estado_eventos_dian), function($query) use ($request) {
                $query->where(function($query) use ($request) {
                    foreach($request->estado_eventos_dian as $estadoEventoDian) {
                        if($estadoEventoDian == 'SINESTADO')
                            $query->orWhereNull('cdo_estado_eventos_dian');
                        else
                            $query->when($request->filled('resultado_eventos_dian'), function($query) use ($request, $estadoEventoDian) {
                                $query->orWhere(function($query) use ($request, $estadoEventoDian) {
                                    $query->where('cdo_estado_eventos_dian', $estadoEventoDian)
                                        ->where('cdo_estado_eventos_dian_resultado', $request->resultado_eventos_dian);
                                });
                            }, function($query) USE ($estadoEventoDian) {
                                $query->orWhere('cdo_estado_eventos_dian', $estadoEventoDian);
                            });
                    }
                });
            })
            ->when($request->filled('transmision_erp') && !empty($request->transmision_erp), function($query) use ($request) {
                $query->where(function($query) use ($request) {
                    foreach($request->transmision_erp as $index => $tipo) {
                        if($index == 0) {
                            if($tipo == 'SINESTADO')
                                $query->whereNull('cdo_transmision_erp');
                            else
                                $query->where('cdo_transmision_erp', $tipo);
                        } else {
                            if($tipo == 'SINESTADO')
                                $query->orWhereNull('cdo_transmision_erp');
                            else
                                $query->orWhere('cdo_transmision_erp', $tipo);
                        }
                    }
                });
            })
            ->when($request->filled('transmision_opencomex'), function($query) use ($request) {
                if($request->transmision_opencomex == 'SINESTADO')
                    $query->whereNull('cdo_transmision_opencomex');
                else
                    $query->where('cdo_transmision_opencomex', $request->transmision_opencomex);
            })
            // Filtros aplicables a OFEs de FNC
            ->when($ofe->ofe_recepcion_fnc_activo == 'SI', function($query) use ($request, $ofe) {
                $query->when($request->filled('filtro_grupos_trabajo_usuario'), function($query) use ($request) {
                    $query->filtroGruposTrabajoTracking($request);
                })
                ->when(!$request->filled('filtro_grupos_trabajo_usuario'), function($query) use ($request, $ofe) {
                    $gruposTrabajoUsuario = $this->getGruposTrabajoUsuarioAutenticado($ofe, true, false);
                    $query->filtroGruposTrabajoUsuarioAutenticado($request, $ofe, $gruposTrabajoUsuario);
                })
                ->when($request->filled('cdo_usuario_responsable_recibidos'), function($query) use ($request) {
                    $query->where('cdo_usuario_responsable_recibidos', $request->cdo_usuario_responsable_recibidos['usu_id']);
                })
                ->when($request->filled('estado_validacion') && !empty($request->estado_validacion), function($query) use ($request) {
                    $query->filtroEstadoValidacionDocumento($request);
                })
                ->when($request->filled('campo_validacion') && $request->filled('valor_campo_validacion'), function ($query) use ($request) {
                    $query->where('cdo_validacion_valor_campos_adicionales_' . $request->campo_validacion, $request->valor_campo_validacion);
                });
            })
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        'ofe_recepcion_fnc_activo',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                    ]);
                },
                'getConfiguracionProveedor' => function($query) use ($request) {
                    $query->select([
                            'pro_id',
                            'pro_identificacion',
                            DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                        ]);
                },
                'getConfiguracionProveedor.getProveedorGruposTrabajo' => function($query) {
                    $query->select(['pro_id', 'gtr_id'])
                        ->where('estado', 'ACTIVO')
                        ->with([
                            'getGrupoTrabajo' => function($query) {
                                $query->select('gtr_id', 'gtr_codigo', 'gtr_nombre')
                                    ->where('estado', 'ACTIVO');
                            }
                        ]);
                },
                'getUsuarioResponsable:usu_id,usu_nombre',
                'getTipoDocumentoElectronico:tde_id,tde_codigo,tde_descripcion',
                'getGrupoTrabajo:gtr_id,gtr_codigo,gtr_nombre',
                'getParametrosMoneda:mon_id,mon_codigo',
                'getParametrosMonedaExtranjera:mon_id,mon_codigo',
                'getParametrosFormaPago:fpa_id,fpa_codigo,fpa_descripcion'
            ])
            ->whereHas('getConfiguracionProveedor', function($query) use ($request) {
                $query->select([
                    'pro_id'
                ]);

                $query = $this->verificaRelacionUsuarioProveedor($query, $request->ofe_id, true, false, false);
                $query->when(filled($request->transmision_opencomex), function ($query) {
                        $nitsIntegracionOpenComex = explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION'));
                        $query->whereIn('pro_identificacion', $nitsIntegracionOpenComex);
                    });
            })
            ->rightJoin('etl_obligados_facturar_electronicamente', function($query) {
                $query->whereRaw('rep_fat_documentos_daop.ofe_id = etl_obligados_facturar_electronicamente.ofe_id')
                    ->where(function($query) {
                        if(!empty(auth()->user()->bdd_id_rg))
                            $query->where('etl_obligados_facturar_electronicamente' . '.bdd_id_rg', auth()->user()->bdd_id_rg);
                        else
                            $query->whereNull('etl_obligados_facturar_electronicamente' . '.bdd_id_rg');
                    });
            });

        $documentos = clone $consultaDocumentos;
        $documentos = $documentos->when($request->columnaOrden == 'cdo_fecha', function ($query) use ($request, $paginador) {
                $query->where(function($query) use ($request, $paginador) {
                    $query->where(function($query) use ($request, $paginador) {
                        $query->where(function($query) use ($request, $paginador) {
                            $query->where('rep_fat_documentos_daop.cdo_fecha', '>=', $request->cdo_fecha_desde)
                                ->where('rep_fat_documentos_daop.cdo_id', $paginador->signoComparacion, $paginador->idComparacion);
                        });
                    })
                    ->where(function($query) use ($request, $paginador) {
                        $query->where(function($query) use ($request, $paginador) {
                            $query->where('rep_fat_documentos_daop.cdo_fecha', '<=', $request->cdo_fecha_hasta)
                                ->where('rep_fat_documentos_daop.cdo_id', $paginador->signoComparacion, $paginador->idComparacion);
                        });
                    });
                });
            }, function($query) use ($request) {
                $query->whereBetween('rep_fat_documentos_daop.cdo_fecha', [$request->cdo_fecha_desde, $request->cdo_fecha_hasta]);
            })
            ->orderByColumn($request->columnaOrden, $paginador->ordenDireccion)
            ->orderBy('rep_fat_documentos_daop.cdo_id', $paginador->ordenDireccion);

        if($request->filled('excel') && $request->excel == true) {
            return collect([
                'query' => $documentos,
                'ofe'   => $ofe
            ]);
        } else {
            $documentos = $documentos->limit($request->length)
                ->get();

            return collect([
                'query'      => $consultaDocumentos,
                'documentos' => $request->ordenDireccion != $paginador->ordenDireccion ? $documentos->reverse()->values() : $documentos
            ]);
        }
    }

    /**
     * Agrega los Joins correspondientes a la consulta de los documentos recibidos para la generación del Excel.
     *
     * @param EloquentBuilder $query Consulta en procesamiento
     * @param string $particion Sufijo de la tabla sobre la cual se debe hacer el join
     * @return EloquentBuilder
     */
    public function joinsExcelDocumentosRecibidos(EloquentBuilder $query, string $particion): EloquentBuilder {
        TenantTrait::GetVariablesSistemaTenant();
        return $query
            // Este rightJoin garantiza que la consulta obtenga los documentos que existan en la partición que se está procesando
            ->rightJoin('rep_cabecera_documentos_' . $particion . ' as get_cabecera_documentos', function($query) {
                $query->where(function($query) {
                    // Columnas que no están presenten en la tabla FAT deben consultarse en la tabla de cabecera
                    $query = HelperRecepcionParticionamiento::relacionOtrasTablas($query, 'rep_fat_documentos_daop', 'get_cabecera_documentos', 'cdo_id', [
                        'rfa_resolucion',
                        'cdo_hora',
                        'cdo_vencimiento',
                        'cdo_observacion',
                        'cdo_valor_sin_impuestos',
                        'cdo_impuestos',
                        'cdo_cargos',
                        'cdo_descuentos',
                        'cdo_redondeo',
                        'cdo_valor_a_pagar',
                        'cdo_anticipo',
                        'cdo_retenciones'
                    ]);
                });
            })
            ->leftJoin('etl_openmain.etl_tipos_documentos_electronicos as get_tipo_documento_electronico', function($query) {
                $query->where(function($query) {
                    $query = HelperRecepcionParticionamiento::relacionOtrasTablas($query, 'rep_fat_documentos_daop', 'get_tipo_documento_electronico', 'tde_id', ['tde_codigo', 'tde_descripcion']);
                });
            })
            ->leftJoin('etl_openmain.etl_tipos_operacion as get_tipo_operacion', function($query) {
                $query->where(function($query) {
                    $query = HelperRecepcionParticionamiento::relacionOtrasTablas($query, 'rep_fat_documentos_daop', 'get_tipo_operacion', 'top_id', ['top_codigo', 'top_descripcion']);
                });
            })
            ->leftJoin('etl_openmain.etl_monedas as get_moneda', function($query) {
                $query->where(function($query) {
                    $query = HelperRecepcionParticionamiento::relacionOtrasTablas($query, 'rep_fat_documentos_daop', 'get_moneda', 'mon_id', ['mon_codigo']);
                });
            })
            ->leftJoin('rep_estados_documentos_' . $particion . ' as get_rdi_exitoso', function($query) use ($particion) {
                $query->where(function($query) use ($particion) {
                    $query = HelperRecepcionParticionamiento::relacionEstadoUltimo($query, 'rep_fat_documentos_daop', 'get_rdi_exitoso', 'rep_estados_documentos_' . $particion, 'RDI', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin('rep_estados_documentos_' . $particion . ' as get_estado_dian_aprobado', function($query) use ($particion) {
                $query->where(function($query) use ($particion) {
                    $query = HelperRecepcionParticionamiento::relacionEstadoUltimo($query, 'rep_fat_documentos_daop', 'get_estado_dian_aprobado', 'rep_estados_documentos_' . $particion, 'GETSTATUS', 'EXITOSO', 'FINALIZADO')
                        ->where(function($query) {
                            $query->where('get_estado_dian_aprobado.est_informacion_adicional', 'not like', '%conNotificacion%')
                                ->orWhere('get_estado_dian_aprobado.est_informacion_adicional->conNotificacion', 'false');
                        });
                });
            })
            ->leftJoin('rep_estados_documentos_' . $particion . ' as get_estado_dian_aprobado_con_notificacion', function($query) use ($particion) {
                $query->where(function($query) use ($particion) {
                    $query = HelperRecepcionParticionamiento::relacionEstadoUltimo($query, 'rep_fat_documentos_daop', 'get_estado_dian_aprobado_con_notificacion', 'rep_estados_documentos_' . $particion, 'GETSTATUS', 'EXITOSO', 'FINALIZADO')
                        ->where('get_estado_dian_aprobado_con_notificacion.est_informacion_adicional->conNotificacion', 'true');
                });
            })
            ->leftJoin('rep_estados_documentos_' . $particion . ' as get_estado_dian_rechazado', function($query) use ($particion) {
                $query->where(function($query) use ($particion) {
                    $query = HelperRecepcionParticionamiento::relacionEstadoUltimo($query, 'rep_fat_documentos_daop', 'get_estado_dian_rechazado', 'rep_estados_documentos_' . $particion, 'GETSTATUS', 'FALLIDO', 'FINALIZADO');
                });
            })
            ->leftJoin('rep_estados_documentos_' . $particion . ' as get_evento_dian_rechazo', function($query) use ($particion) {
                $query->where(function($query) use ($particion) {
                    $query = HelperRecepcionParticionamiento::relacionEstadoUltimo($query, 'rep_fat_documentos_daop', 'get_evento_dian_rechazo', 'rep_estados_documentos_' . $particion, 'RECHAZO', 'EXITOSO', 'FINALIZADO');
                });
            })
            ->leftJoin('rep_estados_documentos_' . $particion . ' as get_transmision_erp', function($query) use ($particion) {
                $query->where(function($query) use ($particion) {
                    $query = HelperRecepcionParticionamiento::relacionEstadoUltimo($query, 'rep_fat_documentos_daop', 'get_transmision_erp', 'rep_estados_documentos_' . $particion, 'TRANSMISIONERP', '', 'FINALIZADO');
                });
            });

    }

    /**
     * Filtra la información de lotes de acuerdo al parámetro recibido y retorna un array de resultados.
     *
     * @param string $lote Cadena mediante la cual se debe filtrar
     * @return array
     */
    public function autocompleteLote(string $lote): array {
        $listaLotes    = [];
        RepFatDocumentoDaop::select(DB::raw('DISTINCT(cdo_lote) as cdo_lote'))
            ->where('cdo_lote', 'like', '%' . $lote . '%')
            ->orderBy('cdo_id', 'asc')
            ->limit(20)
            ->get()
            ->map(function ($item) use (&$listaLotes) {
                $detalle = RepFatDocumentoDaop::select([
                        DB::raw('COUNT(*) as cantidad_documentos,
                        MIN(cdo_consecutivo) as consecutivo_inicial,
                        MAX(cdo_consecutivo) as consecutivo_final')
                    ])
                    ->where('cdo_lote', $item->cdo_lote)
                    ->first();
                    
                $listaLotes[] = [
                    'cdo_lote'            => $item->cdo_lote,
                    'cantidad_documentos' => $detalle->cantidad_documentos,
                    'consecutivo_inicial' => $detalle->consecutivo_inicial,
                    'consecutivo_final'   => $detalle->consecutivo_final,
                ];
            });

        return $listaLotes;
    }
}