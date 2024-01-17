<?php

namespace App\Console\Commands\Recepcion;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class RecepcionTransmisionErpCommand extends Command {
    use DoTrait;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-transmision-erp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recepción - Transmisión ERP';

    /**
     * Límite de intenatos de transmisión.
     *
     * @var integer
     */
    protected $limiteIntentos = 5;

    /**
     * Array de equivalencias de los días de la semana
     *
     * @var array
     */
    protected $diasSemana = [
        0 => 'D',
        1 => 'L',
        2 => 'M',
        3 => 'X',
        4 => 'J',
        5 => 'V',
        6 => 'S',
    ];

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

        //Buscando todos los usuarios habilitados para el proceso de recepcion (integracion erp) para autenticarlo y poder acceder a los modelos tenant
        $users = User::select(['usu_id','usu_email','bdd_id','bdd_id_rg'])
                    ->where('usu_email', 'like', 'erp.%')
                    ->where('usu_email', 'like', '%@open.io')
                    ->where('estado', 'ACTIVO')
                    ->with([
                        'getBaseDatos:bdd_id,bdd_nombre,bdd_host,bdd_usuario,bdd_password'
                    ])
                    ->with([
                        'getBaseDatosRg:bdd_id,bdd_nombre,bdd_host,bdd_usuario,bdd_password'
                    ])
                    ->get();
        
        foreach($users as $user) {
            try {
                $token = auth()->login($user);

                // Base de datos del usuario
                $bdUser = $user->getBaseDatos->bdd_nombre;
                if(!empty($user->bdd_id_rg)) {
                    $bdUser = $user->getBaseDatosRg->bdd_nombre;
                }
                $bdUser = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bdUser);

                //Si el correo corresponde al estandar para recepcion y base de datos se procesa el usuario
                $email = 'erp.' . str_replace('etl_', '', $bdUser) . '@open.io';
                dump($email);

                if ($email != $user->usu_email)
                    continue;

                DB::disconnect('conexion01');
                $this->reiniciarConexion($user->getBaseDatos);

                $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion', 'ofe_recepcion', 'ofe_recepcion_transmision_erp', 'ofe_recepcion_conexion_erp'])
                    ->where('ofe_recepcion', 'SI')
                    ->where('ofe_recepcion_transmision_erp', 'SI')
                    ->whereNotNull('ofe_recepcion_conexion_erp')
                    ->validarAsociacionBaseDatos()
                    ->where('estado', 'ACTIVO')
                    ->get();

                if($ofes) {
                    foreach($ofes as $ofe) {
                        dump($ofe->ofe_identificacion);
                        if($this->debeEjecutarse($ofe->ofe_recepcion_conexion_erp)) {
                            // Clase personalizada de OFE
                            $clase = 'App\\Http\\Modulos\\Recepcion\\TransmisionErp\\' . $bdUser . '\\erp' . $ofe->ofe_identificacion . '\\' . 'RecepcionTransmisionErp' . $ofe->ofe_identificacion;
                            
                            if(!class_exists($clase)){
                                dump('No Existe la Clase '.$clase);
                                continue;
                            }

                            new $clase(
                                $this->limiteIntentos,
                                $ofe
                            );
                        }
                    }
                }
                DB::disconnect('conexion01');
            } catch (\Exception $e) {
                dump(['error' => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()]);
            }
        }
    }

    /**
     * Verifica si el comando debe ejecutarse o no, teniendo en cuenta los datos alojados en la conexión para las propiedades frecuencia_dias, frecuencia_dias_semana, frecuencia_horas y frecuencia_minutos
     *
     * @param array $conexion Array con los datos de conexión al ERP
     * @return void
     */
    private function debeEjecutarse($conexion) {
        // Validaciones de estrutura y datos alojados en la conexión para las propiedades frecuencia_dias, frecuencia_dias_semana, frecuencia_horas y frecuencia_minutos
        if(
            !array_key_exists('frecuencia_dias', $conexion) ||
            (
                !array_key_exists('frecuencia_dias_semana', $conexion) ||
                (
                    (array_key_exists('frecuencia_dias_semana', $conexion) && $conexion['frecuencia_dias_semana'] != '') &&
                    (
                        !strstr($conexion['frecuencia_dias_semana'], 'L') &&
                        !strstr($conexion['frecuencia_dias_semana'], 'M') &&
                        !strstr($conexion['frecuencia_dias_semana'], 'X') &&
                        !strstr($conexion['frecuencia_dias_semana'], 'J') &&
                        !strstr($conexion['frecuencia_dias_semana'], 'V') &&
                        !strstr($conexion['frecuencia_dias_semana'], 'S') &&
                        !strstr($conexion['frecuencia_dias_semana'], 'D')
                    )
                )
            ) ||
            !array_key_exists('frecuencia_horas', $conexion) ||
            (!array_key_exists('frecuencia_minutos', $conexion) || (array_key_exists('frecuencia_minutos', $conexion) && $conexion['frecuencia_minutos'] == ''))
        ) {
            return false;
        }

        // Si pasó las validaciones iniciales, se debe verificar frente a la fecha-hora actual y las propiedades frecuencia_dias, frecuencia_dias_semana, frecuencia_horas y frecuencia_minutos, si se debe ejcutar el comando
        // Datos de fecha y hora actual
        $dia       = date('d');
        $hora      = date('H');
        $minuto    = date('i');
        $diaSemana = $this->diasSemana[Carbon::now()->dayOfWeek];

        $frecuenciaDias       = !empty($conexion['frecuencia_dias']) ? explode(',', $conexion['frecuencia_dias']) : [];
        $frecuenciaDiasSemana = !empty($conexion['frecuencia_dias_semana']) ? explode(',', $conexion['frecuencia_dias_semana']) : [];
        $frecuenciaHoras      = !empty($conexion['frecuencia_horas']) ? explode(',', $conexion['frecuencia_horas']) : [];
        $frecuenciaMinutos    = !empty($conexion['frecuencia_minutos']) ? explode(',', $conexion['frecuencia_minutos']) : [];

        if(
            (empty($frecuenciaDias) && empty($frecuenciaDiasSemana) && empty($frecuenciaHoras) && !empty($frecuenciaMinutos) && in_array($minuto, $frecuenciaMinutos)) ||
            (
                empty($frecuenciaDias) && empty($frecuenciaHoras) &&
                !empty($frecuenciaDiasSemana) && in_array($diaSemana, $frecuenciaDiasSemana) &&
                !empty($frecuenciaMinutos) && in_array($minuto, $frecuenciaMinutos)
            ) ||
            (
                empty($frecuenciaDias) && empty($frecuenciaDiasSemana) &&
                !empty($frecuenciaHoras) && in_array($hora, $frecuenciaHoras) &&
                !empty($frecuenciaMinutos) && in_array($minuto, $frecuenciaMinutos)
            ) ||
            (
                empty($frecuenciaDiasSemana) && empty($frecuenciaHoras) &&
                !empty($frecuenciaDias) && in_array($dia, $frecuenciaDias) &&
                !empty($frecuenciaMinutos) && in_array($minuto, $frecuenciaMinutos)
            ) ||
            (
                empty($frecuenciaDias) &&
                !empty($frecuenciaDiasSemana) && in_array($diaSemana, $frecuenciaDiasSemana) &&
                !empty($frecuenciaHoras) && in_array($hora, $frecuenciaHoras) &&
                !empty($frecuenciaMinutos) && in_array($minuto, $frecuenciaMinutos)
            ) ||
            (
                empty($frecuenciaDiasSemana) &&
                !empty($frecuenciaDias) && in_array($dia, $frecuenciaDias) &&
                !empty($frecuenciaHoras) && in_array($hora, $frecuenciaHoras) &&
                !empty($frecuenciaMinutos) && in_array($minuto, $frecuenciaMinutos)
            ) ||
            (
                empty($frecuenciaHoras) &&
                !empty($frecuenciaDias) && in_array($dia, $frecuenciaDias) &&
                !empty($frecuenciaDiasSemana) && in_array($diaSemana, $frecuenciaDiasSemana) &&
                !empty($frecuenciaMinutos) && in_array($minuto, $frecuenciaMinutos)
            ) ||
            (
                !empty($frecuenciaDias) && in_array($dia, $frecuenciaDias) && 
                !empty($frecuenciaDiasSemana) && in_array($diaSemana, $frecuenciaDiasSemana) &&
                !empty($frecuenciaHoras) && in_array($hora, $frecuenciaHoras) &&
                !empty($frecuenciaMinutos) && in_array($minuto, $frecuenciaMinutos)
            )
        )
            return true;
        
        return false;
    }
}
