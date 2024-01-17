<?php
namespace App\Console\Commands\Recepcion;

use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Console\Command;
use App\Http\Models\AdoAgendamiento;
use App\Http\Modulos\Recepcion\Recepcion;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Documentos\RepProcesarDocumentosDaop\RepProcesarDocumentoDaop;

class RecepcionProcesarArchivosPorCufeCommand extends Command {
    use DiTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-procesar-documentos-por-cufe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa mediante CUFE documentos registrados en el modelo RepProcesarDocumentoDaop';

    /**
     * Definine la cantidad de registros a procesar.
     *
     * @var int
     */
    protected $cantidadRegistrosProcesar = 1000;

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
     * @return void
     */
    public function handle() {
        // Se seleccionan los usuarios relacionados con el proceso de recepción, esto garantiza que el
        // comando se ejecute solamente sobre las bases de datos que tienen el servicio de recepción activo
        $usuarios = User::where('usu_type', 'INTEGRACION')
            ->where('usu_email', 'like', 'recepcion.%')
            ->where('usu_email', 'like', '%@open.io')
            ->where('estado', 'ACTIVO')
            ->get();

        foreach($usuarios as $user) {
            $baseDatos = (!empty($user->bdd_id_rg)) ? $user->getBaseDatosRg : $user->getBaseDatos;

            auth()->login($user);
            $this->reconectarDB();

            $docsProcesarAgrupados = RepProcesarDocumentoDaop::select(['pdo_id', 'ofe_id', 'cdo_cufe'])
                ->where('estado', 'ACTIVO')
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion'
                ])
                ->orderBy('fecha_creacion', 'asc')
                ->take($this->cantidadRegistrosProcesar)
                ->get()
                ->groupBy('ofe_id')
                ->toArray(); // Los documentos se agrupan por ofe_id ya que en la tabla pueden existir registros de diferentes OFEs

            foreach($docsProcesarAgrupados as $documentosProcesar) {
                $arrInfoPrincipal = [
                    'ofe_identificacion' => $documentosProcesar[0]['get_configuracion_obligado_facturar_electronicamente']['ofe_identificacion'],
                    'pro_identificacion' => '',
                    'usu_identificacion' => $user->usu_identificacion,
                    'lote_procesamiento' => '',
                    'origen'             => 'RPA'
                ];

                $contDocumentos        = 1;
                $totalProcesados       = 1;
                $arrRegistrosEliminar  = [];
                $arrDocumentosProcesar = [];
                foreach($documentosProcesar as $documentoProcesar) {
                    $arrRegistrosEliminar[] = $documentoProcesar['pdo_id'];

                    $arrDocumentosProcesar['documentos'][] = [
                        'nombre' => $documentoProcesar['cdo_cufe'],
                        'cufe'   => $documentoProcesar['cdo_cufe'],
                    ];

                    if($contDocumentos < $baseDatos->bdd_cantidad_procesamiento_rdi && $totalProcesados < count($documentosProcesar))
                        $contDocumentos++;
                    else {
                        $arrFinal = array_merge($arrInfoPrincipal, $arrDocumentosProcesar);
                        $arrFinal['lote_procesamiento'] = Recepcion::buildLote();

                        $crearAgendamiento = Recepcion::crearAgendamientoRdi($user, $arrFinal, $contDocumentos);
                        if($crearAgendamiento)
                            $this->eliminarRegistros($arrRegistrosEliminar);

                        $contDocumentos        = 1;
                        $arrRegistrosEliminar  = [];
                        $arrDocumentosProcesar = [];
                    }

                    $totalProcesados++;
                }
            }

            $this->reconectarDB();
        }
    }

    /**
     * Elimina los registro del modelo RepProcesarDocumentoDaop que fueron agendados.
     *
     * @param array $arrRegistrosEliminar Array con los IDs de los registros a eliminar
     * @return void
     */
    private function eliminarRegistros(array $arrRegistrosEliminar): void {
        RepProcesarDocumentoDaop::whereIn('pdo_id', $arrRegistrosEliminar)
            ->delete();
    }
}
