<?php
namespace App\Console\Commands\ProcesosDataBase\p2022;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Modulos\Parametros\Radian\TiposOperaciones\ParametrosRadianTipoOperacion;

class C2022_05_20_164820_AgregarRegistrosParametricaRadianTiposOperacionCommand extends Command {
    /**
     * The name and signature of the console Command.
     *
     * @var string
     */
    protected $signature = 'agregar-registros-parametrica-radian-tipos-operacion-2022-05-20';

    /**
     * The console Command description.
     *
     * @var string
     */
    protected $description = 'Agrega registros iniciales a la paramétrica Tipos Operacion Radian';

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
        $inserts = [
            [
                'tor_codigo'           => '035',
                'tor_descripcion'      => 'Aval',
                'ede_id'               => '1',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '361',
                'tor_descripcion'      => 'Primera inscripción de la factura electrónica de venta como título valor para Negociación General',
                'ede_id'               => '2',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '362',
                'tor_descripcion'      => 'Primera inscripción de la factura electrónica de venta como título valor para NegociaciónDirecta Previa',
                'ede_id'               => '2',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '363',
                'tor_descripcion'      => 'Inscripción posterior de la factura electrónica de venta como título valor para Negociación General',
                'ede_id'               => '2',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '364',
                'tor_descripcion'      => 'Inscripción posterior de la factura electrónica de venta como título valor para Negociación Directa Previa',
                'ede_id'               => '2',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '371',
                'tor_descripcion'      => 'Endoso con responsabilidad del endosante',
                'ede_id'               => '3',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '372',
                'tor_descripcion'      => 'Endoso sin responsabilidad del endosante',
                'ede_id'               => '3',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '038',
                'tor_descripcion'      => 'Endoso en Garantía',
                'ede_id'               => '3',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '039',
                'tor_descripcion'      => 'Endoso en Procuración',
                'ede_id'               => '5',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '401',
                'tor_descripcion'      => 'Cancelación del Endoso en Garantia',
                'ede_id'               => '6',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '402',
                'tor_descripcion'      => 'Cancelación del Endoso en Procuracion',
                'ede_id'               => '6',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '403',
                'tor_descripcion'      => 'Tacha de Endosos por Endoso en Retorno',
                'ede_id'               => '7',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '411',
                'tor_descripcion'      => 'Auto que decreta medida cautelar por embargo',
                'ede_id'               => '7',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '412',
                'tor_descripcion'      => 'Auto que decreta medida cautelar por mandamiento de pago',
                'ede_id'               => '7',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '421',
                'tor_descripcion'      => 'Terminación de limitación por sentencia',
                'ede_id'               => '8',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '422',
                'tor_descripcion'      => 'Terminación de limitación por terminación anticipada',
                'ede_id'               => '8',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '431',
                'tor_descripcion'      => 'Solicitud de inscripción de un Mandato Por documento General por Tiempo limitado',
                'ede_id'               => '9',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '432',
                'tor_descripcion'      => 'Solicitud de inscripción de un Mandato Por documento General porTiempo Ilimitado',
                'ede_id'               => '9',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '433',
                'tor_descripcion'      => 'Solicitud de inscripción de un Mandato Por documento limitado por tiempo limitado',
                'ede_id'               => '9',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '434',
                'tor_descripcion'      => 'Solicitud de inscripción de un Mandato Por documento limitado por tiempo Ilimitado',
                'ede_id'               => '9',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '441',
                'tor_descripcion'      => 'Terminacion del Mandato por Revocacion del Mandante',
                'ede_id'               => '10',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '442',
                'tor_descripcion'      => 'Terminacion del Mandato por Renuncia del mandatario',
                'ede_id'               => '10',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '451',
                'tor_descripcion'      => 'Notificación de pago parcial',
                'ede_id'               => '11',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '452',
                'tor_descripcion'      => 'Pago de la factura electrónica de venta como título valor',
                'ede_id'               => '11',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ],
            [
                'tor_codigo'           => '046',
                'tor_descripcion'      => 'Informe para el pago',
                'ede_id'               => '12',
                'fecha_vigencia_desde' => NULL,
                'fecha_vigencia_hasta' => NULL,
                'usuario_creacion'     => '1',
                'fecha_creacion'       => Carbon::now(),
                'fecha_modificacion'   => Carbon::now(),
                'estado'               => 'ACTIVO'
            ]
        ];

        foreach ($inserts as $insert) {
            $existe = ParametrosRadianTipoOperacion::where('tor_codigo', $insert['tor_codigo'])
                ->where('fecha_vigencia_desde', $insert['fecha_vigencia_desde'])
                ->where('fecha_vigencia_hasta', $insert['fecha_vigencia_hasta'])
                ->first();

            if(!$existe) {
                ParametrosRadianTipoOperacion::create($insert);
                $this->info('El registro fue Creado: ' . $insert['tor_codigo'] . ' - ' . $insert['tor_descripcion']);
            } else {
                $this->error('El registro ya Existe: ' . $insert['tor_codigo'] . ' - ' . $insert['tor_descripcion']);
            }
        }
    }
}
