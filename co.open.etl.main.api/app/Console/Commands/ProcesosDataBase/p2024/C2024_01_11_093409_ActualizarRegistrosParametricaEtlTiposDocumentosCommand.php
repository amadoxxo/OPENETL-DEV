<?php
namespace App\Console\Commands\ProcesosDataBase\p2024;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;

class C2024_01_11_093409_ActualizarRegistrosParametricaEtlTiposDocumentosCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'actualizar-registros-parametrica-etl-tipos-documentos-2024-01-11';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los registros iniciales de la paramétrica tipos documentos';

    /**
     * Create a new Command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console Command.
     * 
     * @return mixed
     */
    public function handle() {

        ParametrosTipoDocumento::where('tdo_codigo', '47')
            ->first()
            ->update(['tdo_descripcion' => 'PEP (Permiso Especial de Permanencia)']);

        $inserts = [
            [
                'tdo_codigo'              => '48',
                'tdo_descripcion'         => 'PPT (Permiso Protección Temporal)',
                'tdo_aplica_para'         => 'DE,DS',
                'fecha_vigencia_desde'    => NULL,
                'fecha_vigencia_hasta'    => NULL,
                'usuario_creacion'        => '1',
                'fecha_creacion'          => Carbon::now(),
                'fecha_modificacion'      => Carbon::now(),
                'estado'                  => 'ACTIVO'
            ]
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosTipoDocumento::where('tdo_codigo', $insert['tdo_codigo'])
                ->whereNull('fecha_vigencia_desde')
                ->whereNull('fecha_vigencia_hasta')
                ->first();

            if(!$existe) {
                ParametrosTipoDocumento::create($insert);
                $this->info('El registro fue creado: ' . $insert['tdo_codigo'] . ' - ' . $insert['tdo_descripcion'] . ' - ' . $insert['tdo_aplica_para']);
            } else {
                $this->error('El registro ya existe: ' . $insert['tdo_codigo'] . ' - ' . $insert['tdo_descripcion'] . ' - ' . $insert['tdo_aplica_para']);
            }
        }
    }
}
