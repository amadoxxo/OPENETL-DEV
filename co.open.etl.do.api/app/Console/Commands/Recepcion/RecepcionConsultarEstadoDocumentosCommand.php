<?php

namespace App\Console\Commands\Recepcion;

use Carbon\Carbon;
use App\Http\Models\User;
use Illuminate\Console\Command;
use App\Http\Modulos\Recepcion\GetStatus;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;

class RecepcionConsultarEstadoDocumentosCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-consultar-estado-documentos
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recepción - Consulta el estado de los documentos en la DIAN';

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
        // Se consulta el agendamiento
        $agendamiento = AdoAgendamiento::where('age_id', $this->argument('age_id'))
            ->where('age_proceso', 'RGETSTATUS')
            ->where('estado', 'ACTIVO')
            ->first();

        if($agendamiento) {
            // Obtiene el usuario relacionado con el agendamiento
            $user = User::find($agendamiento->usu_id);

            $bdUser = $user->getBaseDatos->bdd_nombre;
            if(!empty($user->bdd_id_rg)) {
                $bdUser = $user->getBaseDatosRg->bdd_nombre;
            }
                
            // Generación del token requerido para poder acceder a los modelos Tenant
            $token = auth()->login($user);

            $documentosConsultar = []; // Array que almacena los ID de los documentos a consultar
            $estadosConsultar    = []; // Array que almacena los ID de los estados de los documentos a consultar
            // Obtiene los estados de los documentos asociados con el agendamiento
            $documentosAgendamiento = RepEstadoDocumentoDaop::select(['est_id', 'cdo_id'])
                ->where('age_id', $this->argument('age_id'))
                ->where('estado', 'ACTIVO')
                ->whereNull('est_resultado')
                ->with([
                    'getRepCabeceraDocumentosDaop:cdo_id,ofe_id,cdo_clasificacion,cdo_origen,rfa_prefijo,cdo_consecutivo,cdo_cufe,fecha_creacion',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_archivo_certificado,ofe_password_certificado,ofe_recepcion_eventos_contratados_titulo_valor,bdd_id_rg',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->get()
                ->map( function ($estado) use (&$documentosConsultar, &$estadosConsultar, $bdUser) {
                    if(!empty($estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->bdd_id_rg)) {
                        $bddNombre = $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getBaseDatosRg->bdd_nombre;
                    } else {
                        $bddNombre = $bdUser;
                    }
                    
                    $bddNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);

                    $documentosConsultar[$estado->cdo_id] = [
                        'documento'                => $estado->getRepCabeceraDocumentosDaop,
                        'cdo_clasificacion'        => $estado->getRepCabeceraDocumentosDaop->cdo_clasificacion,
                        'rfa_prefijo'              => $estado->getRepCabeceraDocumentosDaop->rfa_prefijo,
                        'cdo_consecutivo'          => $estado->getRepCabeceraDocumentosDaop->cdo_consecutivo,
                        'cdo_origen'               => $estado->getRepCabeceraDocumentosDaop->cdo_origen,
                        'cdo_cufe'                 => $estado->getRepCabeceraDocumentosDaop->cdo_cufe,
                        'ofe_archivo_certificado'  => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_archivo_certificado,
                        'ofe_password_certificado' => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_password_certificado,
                        'ambiente_destino'         => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                            $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id : null,
                        'test_set_id'              => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                            $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->sft_testsetid : null,
                        'ambiente_destino_ds'      => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                            $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->add_id : null,
                        'test_set_id_ds'           => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                            $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->sft_testsetid : null,
                        'bdd_nombre'               => $bddNombre
                    ];

                    $estadosConsultar[$estado->cdo_id] = [
                        'est_id'                     => $estado->est_id,
                        'inicio'                     => microtime(true)
                    ];
                });

            if (!empty($documentosConsultar)) {
                // Marca el agendamiento en procesando
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-RGETSTATUS'
                ]);

                $classGetStatus = new GetStatus();
                $consultar      = $classGetStatus->consultarDocumentos($agendamiento, $user, $documentosConsultar, $estadosConsultar);

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);
            } else {
                // El agendamiento no encontró coincidencias en el modelo Tenant RepEstadoDocumentoDaop
                // Por lo que se valida el tiempo transcurrido desde su creación y si han pasado
                // más de 5 minutos se procede a finalizar el agendamiento.
                
                // A partir de la fecha y hora actual, se cálcula la fecha y hora restando 5 minutos
                $fecha = Carbon::now()->subSeconds(300);
                $fecha = $fecha->format('Y-m-d H:i:s');

                if($agendamiento->fecha_creacion->lt($fecha)){
                    $agendamiento->update([
                        'age_proceso' => 'FINALIZADO'
                    ]);
                }
            }
        }
    }
}
