<?php
namespace App\Console\Commands\Radian;

use Carbon\Carbon;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaop;

class RadianAceptacionDocumentosCommand extends Command {
    use DoTrait;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'radian-aceptacion-documentos
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Radian - Procesa la aceptación de documentos';

    /**
     * Indica si se debe notificar el evento al proveedor
     *
     * @var boolean
     */
    protected $notificarEvento = true;

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
            ->where('age_proceso', 'RADACEPTACION')
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
            auth()->login($user);

            $documentosAceptar = []; // Array que almacena los ID de los documentos que se aceptarán
            $estadosAceptar    = []; // Array que almacena los ID de los estados de los documentos que se aceptarán
            // Obtiene los estados de los documentos asociados con el agendamiento
            RadianEstadoDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                ->where('age_id', $this->argument('age_id'))
                ->where('estado', 'ACTIVO')
                ->whereNull('est_resultado')
                ->with([
                    'getRadCabeceraDocumentosDaop:cdo_id,act_id,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_nombre_archivos,fecha_creacion',
                    'getRadCabeceraDocumentosDaop.getRadActores:act_id,act_identificacion,sft_id,act_archivo_certificado,act_password_certificado,bdd_id_rg',
                    'getRadCabeceraDocumentosDaop.getRadActores.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getRadCabeceraDocumentosDaop.getRadActores.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->doesntHave('getRadCabeceraDocumentosDaop.getAceptado')
                ->doesntHave('getRadCabeceraDocumentosDaop.getAceptadoT')
                ->doesntHave('getRadCabeceraDocumentosDaop.getRechazado')
                ->get()
                ->map( function ($estado) use (&$documentosAceptar, &$estadosAceptar, $bdUser) {
                    // Se debe verificar que el documento tenga estados GETSTATUS y UBLACEPTACION exitosos
                    $getStatus = RadianEstadoDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'GETSTATUS')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    $ublAceptacion = RadianEstadoDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'UBLACEPTACION')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    if($getStatus && $ublAceptacion) {
                        if(!empty($estado->getRadCabeceraDocumentosDaop->getRadActores->bdd_id_rg)) {
                            $bddNombre = $estado->getRadCabeceraDocumentosDaop->getRadActores->getBaseDatosRg->bdd_nombre;
                        } else {
                            $bddNombre = $bdUser;
                        }

                        $bddNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);

                        $informacionAdicional = !empty($ublAceptacion->est_informacion_adicional) ? json_decode($ublAceptacion->est_informacion_adicional, true) : [];
                        $xmlUbl = $this->obtenerArchivoDeDisco(
                            'radian',
                            $estado->getRadCabeceraDocumentosDaop->getRadActores->act_identificacion,
                            $estado->getRadCabeceraDocumentosDaop,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );

                        $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

                        $documentosAceptar[$estado->cdo_id] = [
                            'act_id'                    => $estado->getRadCabeceraDocumentosDaop->act_id,
                            'rfa_prefijo'               => $estado->getRadCabeceraDocumentosDaop->rfa_prefijo,
                            'cdo_consecutivo'           => $estado->getRadCabeceraDocumentosDaop->cdo_consecutivo,
                            'cdo_cufe'                  => $estado->getRadCabeceraDocumentosDaop->cdo_cufe,
                            'act_identificacion'        => $estado->getRadCabeceraDocumentosDaop->getRadActores->act_identificacion,
                            'act_archivo_certificado'   => $estado->getRadCabeceraDocumentosDaop->getRadActores->act_archivo_certificado,
                            'act_password_certificado'  => $estado->getRadCabeceraDocumentosDaop->getRadActores->act_password_certificado,
                            'ambiente_destino'          => $estado->getRadCabeceraDocumentosDaop->getRadActores->getConfiguracionSoftwareProveedorTecnologico ? $estado->getRadCabeceraDocumentosDaop->getRadActores->getConfiguracionSoftwareProveedorTecnologico->add_id : null,
                            'test_set_id'               => $estado->getRadCabeceraDocumentosDaop->getRadActores->getConfiguracionSoftwareProveedorTecnologico ? $estado->getRadCabeceraDocumentosDaop->getRadActores->getConfiguracionSoftwareProveedorTecnologico->sft_testsetid : null,
                            'xml_ubl'                   => base64_encode($xmlUbl),
                            'nombre_archivos'           => (!empty($estado->getRadCabeceraDocumentosDaop->cdo_nombre_archivos)) ? json_decode($estado->getRadCabeceraDocumentosDaop->cdo_nombre_archivos, true) : [],
                            'est_informacion_adicional' => $estado->est_informacion_adicional,
                            'bdd_nombre'                => $bddNombre
                        ];

                        $estadosAceptar[$estado->cdo_id] = [
                            'est_id' => $estado->est_id,
                            'inicio' => microtime(true)
                        ];
                    }
                });

            if (!empty($documentosAceptar)) {
                // TODO: Pruebas: Marca el agendamiento en procesando 
                // $agendamiento->update([
                //     'age_proceso' => 'PROCESANDO-RADACEPTACION'
                // ]);

                $classAceptar  = new EventStatusUpdate('radian');
                $aceptaciones  = $classAceptar->sendEventStatusUpdate($agendamiento, $user, $documentosAceptar, $estadosAceptar, 'aceptacion', 'validacionDianAceptacion');
                // TODO: Pruebas: Acá quedé probando. Seguir acomodando hacia abajo el código. El eventStatus ya responde bien cuando un documento ya fue procesado, falta procesar uno que de resultado OK
                dd($aceptaciones);
                $documentosAgendarNotificacionEventos = [];

                foreach($aceptaciones as $cdo_id => $resultado) {
                    if($resultado['respuestaProcesada']['estado'] == 'EXITOSO') {
                        $aceptacionExitoso = RadianEstadoDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'UBLADACEPTACION')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->first();

                        if(!$aceptacionExitoso) {
                            $documentosAgendarNotificacionEventos[] = $cdo_id;
                        }
                    } elseif(
                        $resultado['respuestaProcesada']['estado'] != 'EXITOSO' &&
                        (
                            (
                                array_key_exists('message', $resultado['respuestaProcesada']) &&
                                (stristr($resultado['respuestaProcesada']['message'], 'Ha ocurrido un error. Por favor inténtelo de nuevo') || stristr($resultado['respuestaProcesada']['message'], 'timeout'))
                            ) ||
                            (
                                array_key_exists('ErrorMessage', $resultado['respuestaProcesada']) &&
                                (stristr($resultado['respuestaProcesada']['ErrorMessage'], 'Ha ocurrido un error. Por favor inténtelo de nuevo') || stristr($resultado['respuestaProcesada']['ErrorMessage'], 'timeout'))
                            )
                        )
                    ) {
                        $aceptacionFallidos = RadianEstadoDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'ACEPTACION')
                            ->where('est_resultado', 'FALLIDO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->count();

                        if($aceptacionFallidos < 3) {
                            // Verifica si el xmlRespuestaDian no está vacio y si es un XML válido
                            $esXml = true;
                            if(
                                $resultado['xmlRespuestaDian'] == '' || $resultado['xmlRespuestaDian'] == null || (
                                    array_key_exists('StatusCode', $resultado['respuestaProcesada']) && $resultado['respuestaProcesada']['StatusCode'] == '500'
                                )
                            ) {
                                $esXml = false;
                            } else {
                                $oXML        = new \SimpleXMLElement($resultado['xmlRespuestaDian']);
                                $vNameSpaces = $oXML->getNamespaces(true);
                                if(!is_array($vNameSpaces) || empty($vNameSpaces)) {
                                    $esXml = false;
                                }
                            }

                            if(!$esXml) {
                                $nuevoAgendamiento = $classAceptar->nuevoAgendamiento('RACEPTACION', $agendamiento, $user, 1);
                                $classAceptar->creaNuevoEstadoDocumento(
                                    $cdo_id,
                                    'UBLACEPTACION',
                                    'ACEPTACION',
                                    $nuevoAgendamiento->age_id,
                                    $user->usu_id,
                                    ['agendamiento' => 'ACEPTACIONR']
                                );
                            }
                        }
                    }
                }
                
                if ($this->notificarEvento) {
                    $this->agendamientosNotificacionEventos($classAceptar, $user, $agendamiento, $documentosAgendarNotificacionEventos);
                }

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);

                $this->verificarSgteEstado($classAceptar, $user, $agendamiento);
            } else {
                // El agendamiento no encontró coincidencias en el modelo Tenant RadEstadoDocumentoDaop
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

    /**
     * Procesa registros que deben ser agendados para UBLADACEPTACION.
     *
     * @param EventStatusUpdate $classAceptar Clase que procesa el envío de eventos a la DIAN
     * @param User $user Colección de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Colección con la información del agendamiento en proceso
     * @param array $documentosAgendarNotificacionEventos Array de documentos que se agendarán para UBLADACEPTACION
     * @return void
     */
    public function agendamientosNotificacionEventos(EventStatusUpdate $classAceptar, User $user, AdoAgendamiento $agendamiento, array $documentosAgendarNotificacionEventos) {
        if(!empty($documentosAgendarNotificacionEventos)) {
            $grupos = array_chunk($documentosAgendarNotificacionEventos, $user->getBaseDatos->bdd_cantidad_procesamiento_aceptacion);
            foreach ($grupos as $grupo) {
                $nuevoAgendamiento = $classAceptar->nuevoAgendamiento('RUBLADACEPTACION', $agendamiento, $user, count($grupo));
                foreach($grupo as $cdo_id) {
                    $classAceptar->creaNuevoEstadoDocumento(
                        $cdo_id,
                        'ACEPTACION',
                        'UBLADACEPTACION',
                        $nuevoAgendamiento->age_id,
                        $user->usu_id
                    );
                }
            }
        }
    }

    /**
     * Verifica que cada documento del agendamiento que haya sido procesado de manera correcta, cuente con el siguiente estado que le correspondía.
     *
     * @param EventStatusUpdate $classAceptacion Clase principal del procesamiento del comando
     * @param User $user Instancia de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Instancia del agendamiento en proceso
     * @return void
     */
    private function verificarSgteEstado(EventStatusUpdate $classAceptacion, User $user, AdoAgendamiento $agendamiento) {
        // Documentos asociados al agendamiento
        $docsAgendamiento = RadianEstadoDocumentoDaop::select(['cdo_id'])
            ->where('age_id', $agendamiento->age_id)
            ->where('est_estado', 'ACEPTACION')
            ->get()
            ->pluck('cdo_id')
            ->values()
            ->toArray();

        $documentosAgendarNotificacionEventos = [];
        RadianCabeceraDocumentoDaop::select(['cdo_id'])
            ->whereIn('cdo_id', $docsAgendamiento)
            ->with([
                'getEstadoAceptacion:est_id,cdo_id,est_resultado',
                'getEstadoUblAdAceptacion:est_id,cdo_id'
            ])
            ->get()
            ->map(function($documento) use (&$documentosAgendarNotificacionEventos) {
                // El estado RECIBOBIEN fue exitoso pero el documento no tiene estado UBLADRECIBOBIEN por lo que se debe agendar
                if($documento->getEstadoAceptacion->est_resultado == 'EXITOSO' && !$documento->getEstadoUblAdAceptacion)
                    $documentosAgendarNotificacionEventos[] = $documento->cdo_id;
            });

        if(!empty($documentosAgendarNotificacionEventos))
            $this->agendamientosNotificacionEventos($classAceptacion, $user, $agendamiento, $documentosAgendarNotificacionEventos);
    }
}
