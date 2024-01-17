<?php
namespace App\Console\Commands\Recepcion\FNC;

use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Repositories\Recepcion\RepCabeceraDocumentoRepository;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class FncReporteDependenciaDocumentosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fnc-reporte-dependencia-documentos {bdd_nombre : Nombre de la base de datos} {fecha_inicial : Fecha de inicio de consulta} {fecha_final : Fecha final de consulta}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera un reporte, entre el rango de fechas, de los documentos y las dependencias asignadas';

    /**
     * Instancia de la clase Controller de Laravel.
     * 
     * Esta clase es requerida para evitar duplicar código para la generación del Excel
     *
     * @var Controller
     */
    protected $controller;

    /**
     * Instancia del repositorio RepCabeceraDocumentoRepository
     *
     * @var RepCabeceraDocumentoRepository
     */
    protected $recepcionCabeceraRepository;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Controller $controller, RepCabeceraDocumentoRepository $recepcionCabeceraRepository) {
        parent::__construct();

        $this->controller = $controller;
        $this->recepcionCabeceraRepository = $recepcionCabeceraRepository;
    }

    /**
     * Execute the console command.
     * 
     * @return void
     */
    public function handle() {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        $baseDatos = AuthBaseDatos::select(['bdd_id', 'bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password', 'bdd_aplica_particionamiento_recepcion', 'bdd_inicio_particionamiento_recepcion'])
            ->where('bdd_nombre', $this->argument('bdd_nombre'))
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$baseDatos) {
            $this->error('La base de datos [' . $this->argument('bdd_nombre') . '] no existe o se encuentra inactiva');
            die();
        }

        // Ubica un usuario relacionado con la BD para poder autenticarlo y acceder a los modelos Tenant
        $user = User::where('bdd_id', $baseDatos->bdd_id)
            ->where('usu_type', 'ADMINISTRADOR')
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$user){
            $this->error('No se encontró un usuario activo del tipo [ADMINISTRADOR] relacionado con la base de datos [' . $this->argument('bdd_nombre') . ']');
            die();
        }

        auth()->login($user);
        $this->recepcionCabeceraRepository->generarConexionTenant($baseDatos);

        $arrFilasOfe = [];
        RepCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'pro_id', 'gtr_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha', 'cdo_cufe'])
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as nombre_completo')
                    ]);
                },
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                        'pro_id',
                        'pro_identificacion',
                        DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as nombre_completo')
                    ]);
                },
                'getGrupoTrabajo:gtr_id,gtr_codigo,gtr_nombre',
                'getConfiguracionProveedor.getProveedorGruposTrabajo' => function ($query) {
                    $query->select(['gtp_id' ,'gtr_id', 'pro_id'])
                        ->where('estado', 'ACTIVO');
                },
                'getConfiguracionProveedor.getProveedorGruposTrabajo.getGrupoTrabajo' => function ($query) {
                    $query->select(['gtr_id', 'gtr_codigo', 'gtr_nombre'])
                        ->where('estado', 'ACTIVO');
                }
            ])
            ->whereBetween('cdo_fecha', [$this->argument('fecha_inicial'), $this->argument('fecha_final')])
            ->get()
            ->map(function($documento) use (&$arrFilasOfe) {
                $indiceOfe = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '_' . str_replace([' ', '.', '&'], ['_', '', 'y'], $documento->getConfiguracionObligadoFacturarElectronicamente->nombre_completo);

                $grupoTrabajo = $this->definirGrupoTrabajo($documento);

                $arrFilasOfe[$indiceOfe][] = [
                    $documento->getConfiguracionProveedor->pro_identificacion,
                    $documento->getConfiguracionProveedor->nombre_completo,
                    $documento->rfa_prefijo,
                    $documento->cdo_consecutivo,
                    $documento->cdo_fecha,
                    $documento->cdo_cufe,
                    $grupoTrabajo['dependencia'],
                    $grupoTrabajo['asignacion']
                ];
            });

        $titulos = [
            'NIT',
            'PROVEEDOR' ,
            'PREFIJO' ,
            'FACTURA' ,
            'FECHA',
            'CUFE',
            'DEPENDEINCIA',
            'ASIGNACION'
        ];

        foreach($arrFilasOfe as $ofe => $filas) {
            date_default_timezone_set('America/Bogota');
            $nombreArchivo = $ofe . '_' . date('YmdHis');
            $archivoExcel  = $this->controller->toExcel($titulos, $filas, $nombreArchivo);

            // Renombra el archivo y lo mueve al disco de descargas ya que se crea sobre el disco local
            File::move($archivoExcel, storage_path('etl/descargas/' . $nombreArchivo . '.xlsx'));
            File::delete($archivoExcel);

            dump('Reporte generado: ' . $nombreArchivo);
        }
    }

    /**
     * Define el grupo de trabajo al cual está asociado un documento electrónico.
     * 
     * Si el documento esta asignado directamente a un grupo de trabajo se retorna el código y nombre del grupo
     * Sino, si el proveedor está asociado a un solo grupo de trabajo se retorna el código y nombre del grupo
     *       si el proveedor está asociado a varios grupos de trabajo se retorna un cadena separa por comas (,) con los códigos de los grupos
     * Si el documento no esta asociado a un grupo ni el proveedor esta asociado a un grupo se retornará un string vacio
     *
     * @param RepCabeceraDocumentoDaop $documento Información del documento que está siendo procesado
     * @return Array
     */
    private function definirGrupoTrabajo(RepCabeceraDocumentoDaop $documento) {
        $grupoTrabajo = [];
        $grupoTrabajo['dependencia'] = '';
        $grupoTrabajo['asignacion']  = '';
        if($documento->getGrupoTrabajo) {
            $grupoTrabajo['dependencia'] = $documento->getGrupoTrabajo->gtr_codigo . ' - ' .  $documento->getGrupoTrabajo->gtr_nombre;
            $grupoTrabajo['asignacion']  = 'UNICA';

            return $grupoTrabajo;
        } else {
            $gruposTrabajoProveedor = $documento->getConfiguracionProveedor->getProveedorGruposTrabajo
                ->where('getGrupoTrabajo', '!=', null);

            if($gruposTrabajoProveedor->count() == 1) {
                $grupoTrabajo['dependencia'] = $gruposTrabajoProveedor[0]->getGrupoTrabajo->gtr_codigo . ' - ' . 
                    $gruposTrabajoProveedor[0]->getGrupoTrabajo->gtr_nombre;
                $grupoTrabajo['asignacion']  = 'UNICA';

                return $grupoTrabajo;
            } else {
                $gtrCodigos = [];
                foreach($gruposTrabajoProveedor as $grupoTrabajo) {
                    if($grupoTrabajo->getGrupoTrabajo)
                        $gtrCodigos[] = trim($grupoTrabajo->getGrupoTrabajo->gtr_codigo);
                }

                $grupoTrabajo['dependencia'] = implode(',', $gtrCodigos);
                $grupoTrabajo['asignacion']  = 'COMPARTIDA';

                return $grupoTrabajo;
            }
        }

        return $grupoTrabajo;
    }
}
