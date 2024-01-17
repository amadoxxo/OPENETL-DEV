<?php

namespace App\Console\Commands\ProcesosDataBase\p2021;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use App\Http\Modulos\RgController;
use Illuminate\Support\Facades\Validator;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class C2021_07_16_145208_GenerarPdfCadisoftCommand extends Command {

    use DoTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generar-pdf-cadisoft {baseDatos : Base de datos} {cdoIdInicial : Autoincremental del Documento} {cantRegistros : Cantidad de registros a procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para genear la representacion grafica de cadisoft, incluyendo el QR, Firma, fecha validacion y CUFE/CUDE';

    /**
     * Array para almecenar los documentos no procesados.
     *
     * @var array
     */
    protected $archivosFallidos = [];

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
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->line('Objetivo del comando: ' . $this->description);
        $this->line('');

        $this->line('baseDatos: '     . trim($this->argument('baseDatos')));
        $this->line('cdoIdInicial: '  . trim($this->argument('cdoIdInicial')));
        $this->line('cdoIdInicial: '  . trim($this->argument('cantRegistros')));
        $this->line('cantRegistros: ' . trim($this->argument('cantRegistros')));

        $baseDatos     = trim($this->argument('baseDatos'));
        $cdoIdInicial  = trim($this->argument('cdoIdInicial'));
        $cantRegistros = trim($this->argument('cantRegistros'));

        $validador = Validator::make([
            'baseDatos'     => $baseDatos,
            'cdoIdInicial'  => $cdoIdInicial,
            'cantRegistros' => $cantRegistros
        ], [
            'baseDatos'     => 'required|string',
            'cdoIdInicial'  => 'required|numeric|min:1',
            'cantRegistros' => 'required|numeric|min:1'
        ]);
        
        if($validador->fails()){
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }
            exit;
        }

        $bdd = AuthBaseDatos::select(['bdd_id'])
            ->where('bdd_nombre', $baseDatos)
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$bdd) {
            $this->error('La base de datos [' . $baseDatos . '] no existe o se encuentra inactiva');
            exit;
        }

        // Usuario a autenticar para poder acceder a los modelos tenant
        $bddIdRg = null;
        $user = User::where('bdd_id', $bdd->bdd_id)
            ->where('usu_type', 'ADMINISTRADOR')
            ->where('estado', 'ACTIVO')
            ->first();
        if(!$user) {
            $user = User::where('bdd_id_rg', $bdd->bdd_id)
                ->where('usu_type', 'ADMINISTRADOR')
                ->where('estado', 'ACTIVO')
                ->first();

            if (!$user) {
                $this->error('No se encontró el usuario [ADMINISTRADOR] de la base de datos [' . $baseDatos . '] o el usuario se encuentra inactivo');
                exit;
            }
            $bddIdRg = $bdd->bdd_id;
        }

        $auth = auth()->login($user);

        //Buscando los OFE asociados a la base de datos
        $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id']);
            if(!empty($bddIdRg)) {
                $ofes = $ofes->where('bdd_id_rg', $bddIdRg);
            } else {
                $ofes = $ofes->whereNull('bdd_id_rg');
            }
        $ofes = $ofes->where('estado', 'ACTIVO')
            ->get()
            ->toArray();

        // Obtiene la posición del registro dentro de la tabla para definir el punto de inicio de la consulta
        $start = EtlCabeceraDocumentoDaop::select('cdo_id')
            ->where('cdo_id', '<=', $cdoIdInicial)
            ->whereIn('ofe_id', $ofes);
        // dump($start->toSql());
        // dump($start->getBindings());
        $start = $start->count();
        $start = ($start - 1) < 0 ? 0 : ($start - 1);

        //clase para generar RG
        $rgController = new RgController();

        $documento = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_cufe', 'fecha_creacion'])
            ->whereIn('ofe_id', $ofes)
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion'
            ])
            ->skip($start)
            ->take($cantRegistros)
            ->orderBy('cdo_id', 'asc')
            ->get()
            ->map(function($documento) use ($rgController) {
                $obtenerBase64 = $this->obtenerBase64($documento);
                if(!empty($obtenerBase64)) {
                    $nombreArchivo = $this->guardarArchivoEnDisco(
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                        $documento,
                        'emision',
                        'rg',
                        'pdf',
                        $obtenerBase64
                    );

                    if(!empty($nombreArchivo)) {
                        //Si el DO tiene cufe, se incluyen estos datos en la RG
                        if(!empty($documento->cdo_cufe)) {
                            // Representación gráfica del documento
                            $request = new Request();
                            $arrRequest = [
                                'cdo_id' => $documento->cdo_id
                            ];
                            $request->headers->add(['accept' => 'application/json']);
                            $request->headers->add(['x-requested-with' => 'XMLHttpRequest']);
                            $request->headers->add(['content-type' => 'application/json']);
                            $request->headers->add(['cache-control' => 'no-cache']);
                            $request->request->add($arrRequest);
                            $request->json()->add($arrRequest);
                            $getRepresentacionGrafica = $rgController->getPdfRepresentacionGraficaDocumento($request);
                            if(array_key_exists('data', $getRepresentacionGrafica->original) && array_key_exists('pdf', $getRepresentacionGrafica->original['data']) && !empty($getRepresentacionGrafica->original['data']['pdf'])) {
                                $nombreArchivoDisco = $this->guardarArchivoEnDisco(
                                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                    $documento,
                                    'emision',
                                    'rg',
                                    'pdf',
                                    $getRepresentacionGrafica->original['data']['pdf']
                                );

                                if (!empty($nombreArchivo)) {
                                    dump($documento->cdo_id . '~' . $documento->rfa_prefijo . $documento->cdo_consecutivo . ': ' . $nombreArchivo);
                                } else {
                                    $this->archivosFallidos[] = $documento->cdo_id. '~' .$documento->rfa_prefijo. '~' .$documento->cdo_consecutivo . ': Insertando Datos DIAN';
                                }
                            } else {
                              $this->archivosFallidos[] = $documento->cdo_id. '~' .$documento->rfa_prefijo. '~' .$documento->cdo_consecutivo . ': Guardando Base64';
                            }
                        }
                    }
                } else {
                    $this->archivosFallidos[] = $documento->cdo_id. '~' .$documento->rfa_prefijo. '~' .$documento->cdo_consecutivo;
                }
            });

        if (!empty($this->archivosFallidos)) {
            $this->error('Los siguientes documentos no fueron procesados:');
            foreach ($this->archivosFallidos as $error) {
                $this->error($error);
            }
        }
    }

    /**
     * Obtiene el registro mas reciente del estado y documento recibidos por parámetro.
     *
     * @param  EtlCabeceraDocumentoDaop $documento Colección conteniendo información de cabecera del documento
     * @return string Archivo PDF en base 64
     */
    private function obtenerBase64($documento) {
        try {

            $nombreArchivo = 'json_' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '.json';

            $archivo = json_decode(
                $this->obtenerArchivoDeDisco(
                    'emision',
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                    $documento,
                    $nombreArchivo
                )
            );

            $pdf_base64 = null;
            if (!empty($archivo) && isset($archivo->cdo_informacion_adicional->pdf_base64) && $archivo->cdo_informacion_adicional->pdf_base64 != '')
                $pdf_base64 = $archivo->cdo_informacion_adicional->pdf_base64;

            return $pdf_base64 ? $pdf_base64 : null;
        } catch (\Exception $e) {
            $this->archivosFallidos[] = $documento->cdo_id. '~' .$documento->rfa_prefijo. '~' .$documento->cdo_consecutivo;
            return null;
        }
    }
}