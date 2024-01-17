<?php
namespace App\Console\Commands\Emision;

use Validator;
use App\Http\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\TransmitirDocumentosDian\TransmitirDocumentosDian;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;

class EmisionValidacionHistoricaDianCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emision-validacion-historica-dian {baseDatos : Base de datos} {cdoIdInicial : Autoincremental del Documento} {cantRegistros : Cantidad de registros a procesar}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identifica documentos electrónicos procesados correctamente pero que NO existen en la DIAN';

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
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->line('Objetivo del comando: ' . $this->description);
        $this->line('');

        $this->line('baseDatos: '     . trim($this->argument('baseDatos')));
        $this->line('cdoIdInicial: '  . trim($this->argument('cdoIdInicial')));
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
        $user = User::where('bdd_id', $bdd->bdd_id)
            ->where('usu_type', 'ADMINISTRADOR')
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$user) {
            $this->error('No se encontró el usuario [ADMINISTRADOR] de la base de datos [' . $baseDatos . '] o el usuario se encuentra inactivo');
            exit;
        }

        auth()->login($user);

        // Obtiene la posición del registro dentro de la tabla para definir el punto de inicio de la consulta
        $start = EtlCabeceraDocumentoDaop::select('cdo_id')
            ->where('cdo_id', '<=', $cdoIdInicial)
            ->count();

        $start = ($start - 1) < 0 ? 0 : ($start - 1);

        $documentosConsultar = [];
        $estadosDocumentos   = [];
        EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'adq_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_cufe', 'cdo_fecha_validacion_dian'])
            ->with([
                'getDoDocumento',
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_reenvio_notificacion_contingencia',
                'getConfiguracionAdquirente:adq_id,adq_reenvio_notificacion_contingencia'
            ])
            ->skip($start)
            ->take($cantRegistros)
            ->orderBy('cdo_id', 'asc')
            ->get()
            ->map(function($documento) use ($baseDatos, &$documentosConsultar, &$estadosDocumentos) {
                if(
                    !empty($documento->cdo_fecha_validacion_dian) &&
                    isset($documento->getDoDocumento->est_object) &&
                    array_key_exists('XmlDocumentKey', $documento->getDoDocumento->est_object) &&
                    $documento->getDoDocumento->est_object['XmlDocumentKey'] != $documento->cdo_cufe
                ) {
                    $documentosConsultar[] = $documento->cdo_id;
                    $estadosDocumentos[$documento->cdo_id] = [
                        'rfa_prefijo'                => $documento->rfa_prefijo,
                        'cdo_consecutivo'            => $documento->cdo_consecutivo,
                        'est_id'                     => $documento->getDoDocumento->est_id,
                        'estadoInformacionAdicional' => $documento->getDoDocumento->est_informacion_adicional
                    ];
                }
            });

        $contenidoLog   = "\n";
        $classConsultar = new TransmitirDocumentosDian();
        $consultar      = $classConsultar->ConsultarDocumentosDian($documentosConsultar, $estadosDocumentos, auth()->user(), false);

        foreach($consultar as $cdo_id => $resultadoConsulta) {
            if($resultadoConsulta['respuestaProcesada']['estado'] == 'FALLIDO') {
                $contenidoLog .= $baseDatos . '|' . $cdo_id . '|' . $estadosDocumentos[$cdo_id]['rfa_prefijo'] . '|' . $estadosDocumentos[$cdo_id]['cdo_consecutivo'] . '|' . (!empty($resultadoConsulta['respuestaProcesada']['ErrorMessage']) ? $resultadoConsulta['respuestaProcesada']['ErrorMessage'] : $resultadoConsulta['respuestaProcesada']['StatusDescription']) . "\n" ; 
            }
        }

        if($contenidoLog != "\n") {
            config(['logging.channels.emision_validacion_historica_dian.path' => storage_path('logs/emision/emision_validacion_historica_dian_'.date('Ymd').'.log')]);
            Log::channel('emision_validacion_historica_dian')->info($contenidoLog);

            $this->line('');

            $this->line('Proceso finalizado, verifique el log registrado en [logs/emision/emision_validacion_historica_dian_'.date('Ymd').'.log]');
        } else {
            $this->line('');

            $this->line('Proceso finalizado, no se encontraron documentos para procesar y/o el proceso no generó información para ser registrada en el Log');
        }
    }
}
