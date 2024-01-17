<?php

namespace App\Console\Commands\Recepcion;

use Carbon\Carbon;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use openEtl\Tenant\Traits\TenantRecepcionTrait;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class RecepcionReciboBienDocumentosCommand extends Command {
    use DoTrait, TenantRecepcionTrait;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recepcion-recibo-bien-documentos
                            {age_id : ID de agendamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recepción - Procesa el recibo de bien y/o servicio para documentos electrónicos';

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
            ->where('age_proceso', 'RRECIBOBIEN')
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

            $documentosReciboBien = []; // Array que almacena los ID de los documentos a procesar
            $estadosReciboBien    = []; // Array que almacena los ID de los estados de los documentos a procesar
            // Obtiene los estados de los documentos asociados con el agendamiento
            $documentosAgendamiento = RepEstadoDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                ->where('age_id', $this->argument('age_id'))
                ->where('estado', 'ACTIVO')
                ->whereNull('est_resultado')
                ->with([
                    'getRepCabeceraDocumentosDaop:cdo_id,ofe_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_nombre_archivos,fecha_creacion',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_archivo_certificado,ofe_password_certificado,bdd_id_rg,ofe_recepcion_fnc_activo',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid',
                    'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre'
                ])
                ->get()
                ->map( function ($estado) use (&$documentosReciboBien, &$estadosReciboBien, $bdUser) {
                    // Se debe verificar que le documento tenga estados GETSTATUS y UBLRECIBOBIEN exitosos
                    $getStatus = RepEstadoDocumentoDaop::select(['est_id'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'GETSTATUS')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    $ublReciboBien = RepEstadoDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $estado->cdo_id)
                        ->where('est_estado', 'UBLRECIBOBIEN')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    if($getStatus && $ublReciboBien) {
                        if(!empty($estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->bdd_id_rg)) {
                            $bddNombre = $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getBaseDatosRg->bdd_nombre;
                        } else {
                            $bddNombre = $bdUser;
                        }

                        $bddNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $bddNombre);

                        $informacionAdicional = !empty($ublReciboBien->est_informacion_adicional) ? json_decode($ublReciboBien->est_informacion_adicional, true) : [];
                        $xmlUbl = $this->obtenerArchivoDeDisco(
                            'recepcion',
                            $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $estado->getRepCabeceraDocumentosDaop,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );
                        $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

                        $documentosReciboBien[$estado->cdo_id] = [
                            'ofe_id'                    => $estado->getRepCabeceraDocumentosDaop->ofe_id,
                            'cdo_clasificacion'         => $estado->getRepCabeceraDocumentosDaop->cdo_clasificacion,
                            'rfa_prefijo'               => $estado->getRepCabeceraDocumentosDaop->rfa_prefijo,
                            'cdo_consecutivo'           => $estado->getRepCabeceraDocumentosDaop->cdo_consecutivo,
                            'cdo_cufe'                  => $estado->getRepCabeceraDocumentosDaop->cdo_cufe,
                            'ofe_identificacion'        => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            'ofe_archivo_certificado'   => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_archivo_certificado,
                            'ofe_password_certificado'  => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_password_certificado,
                            'ofe_recepcion_fnc_activo'  => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_fnc_activo,
                            'ambiente_destino'          => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id : null,
                            'test_set_id'               => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->sft_testsetid : null,
                            'ambiente_destino_ds'       => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->add_id : null,
                            'test_set_id_ds'            => $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs ?
                                $estado->getRepCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->sft_testsetid : null,
                            'xml_ubl'                   => base64_encode($xmlUbl),
                            'nombre_archivos'           => (!empty($estado->getRepCabeceraDocumentosDaop->cdo_nombre_archivos)) ? json_decode($estado->getRepCabeceraDocumentosDaop->cdo_nombre_archivos, true) : [],
                            'est_informacion_adicional' => $estado->est_informacion_adicional,
                            'bdd_nombre'                => $bddNombre
                        ];

                        $estadosReciboBien[$estado->cdo_id] = [
                            'est_id'                     => $estado->est_id,
                            'inicio'                     => microtime(true)
                        ];
                    }
                });

            if (!empty($documentosReciboBien)) {
                // Marca el agendamiento en procesando
                $agendamiento->update([
                    'age_proceso' => 'PROCESANDO-RRECIBOBIEN'
                ]);

                $classReciboBien = new EventStatusUpdate('recepcion');
                $reciboBien      = $classReciboBien->sendEventStatusUpdate($agendamiento, $user, $documentosReciboBien, $estadosReciboBien, 'recibo', 'validacionDianReciboBien');
                $documentosAgendarNotificacionEventos = [];
                $documentosCrearEstadoValidacion      = [];

                foreach($reciboBien as $cdo_id => $resultado) {
                    if($resultado['respuestaProcesada']['estado'] == 'EXITOSO') {
                        $reciboBienExitoso = RepEstadoDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'UBLADRECIBOBIEN')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->first();

                        if(!$reciboBienExitoso) {
                            $documentosAgendarNotificacionEventos[] = $cdo_id;
                        }

                        if($documentosReciboBien[$cdo_id]['ofe_recepcion_fnc_activo'] == 'SI')
                            $documentosCrearEstadoValidacion[$cdo_id] = $documentosReciboBien[$cdo_id]['est_informacion_adicional'];
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
                        $reciboBienFallidos = RepEstadoDocumentoDaop::select(['est_id'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'RECIBOBIEN')
                            ->where('est_resultado', 'FALLIDO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->count();

                        if($reciboBienFallidos < 3) {
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
                                $nuevoAgendamiento = $classReciboBien->nuevoAgendamiento('RRECIBOBIEN', $agendamiento, $user, 1);
                                $classReciboBien->creaNuevoEstadoDocumento(
                                    $cdo_id,
                                    'UBLRECIBOBIEN',
                                    'RECIBOBIEN',
                                    $nuevoAgendamiento->age_id,
                                    $user->usu_id,
                                    ['agendamiento' => 'RECIBOBIENR']
                                );
                            }
                        }
                    }
                }
                
                $this->agendamientosNotificacionEventos($classReciboBien, $user, $agendamiento, $documentosAgendarNotificacionEventos);

                if(!empty($documentosCrearEstadoValidacion))
                    $this->crearEstadosValidacion($user, $documentosCrearEstadoValidacion);

                $agendamiento->update([
                    'age_proceso' => 'FINALIZADO'
                ]);

                $this->verificarSgteEstado($classReciboBien, $user, $agendamiento);
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

    /**
     * Crea estados VALIDACION para los documento recibidos en el array.
     *
     * @param User $user Colección de usuario relacionado con el agendamiento
     * @param array $documentosCrearEstadoValidacion Array de documentos que se agendarán para UBLADRECIBOBIEN
     * @return void
     */
    public function crearEstadosValidacion(User $user, array $documentosCrearEstadoValidacion): void {
        foreach($documentosCrearEstadoValidacion as $cdo_id => $informacionAdicional) {
            $documento = RepCabeceraDocumentoDaop::select(['cdo_id'])
                ->where('cdo_id', $cdo_id)
                ->with([
                    'getEstadoValidacionEnProcesoPendiente'
                ])
                ->first();

            $est_informacion_adicional = !empty($informacionAdicional) ? json_decode($informacionAdicional, true) : [];
            $estInformacionAdicional   = array_key_exists('campos_adicionales', $est_informacion_adicional) ? $est_informacion_adicional : ['campos_adicionales' => []];

            // Antes de crear el estado se debe revisar el estado VALIDACION PENDIENTE o ENPROCESO más reciente para comparar
            // la información recibida en $informacionAdicional con lo pre-existente en el estado para modificar o no cada campo adicional
            if($documento->getEstadoValidacionEnProcesoPendiente && $documento->getEstadoValidacionEnProcesoPendiente->est_informacion_adicional) {
                $informacionAdicionalEstadoPrevio = !empty($documento->getEstadoValidacionEnProcesoPendiente->est_informacion_adicional) ? 
                    json_decode($documento->getEstadoValidacionEnProcesoPendiente->est_informacion_adicional, true) :
                    ['campos_adicionales' => []];

                $est_informacion_adicional['campos_adicionales'] = $this->compararDatosEstadoPrevioValidacion(
                    $informacionAdicionalEstadoPrevio['campos_adicionales'],
                    $estInformacionAdicional['campos_adicionales']
                );
            } else {
                // Para la creación del estado la información de los campos adicionales debe llevar datos del usuario asociado al proceso
                $est_informacion_adicional['campos_adicionales'] = array_map(function($campoAdicional) {
                    return $this->agregarDatosUsuarioFechaCamposAdicionales($campoAdicional);
                }, $estInformacionAdicional['campos_adicionales']);
            }

            RepEstadoDocumentoDaop::create([
                'cdo_id'                    => $cdo_id,
                'est_estado'                => 'VALIDACION',
                'est_resultado'             => 'PENDIENTE',
                'est_ejecucion'             => 'FINALIZADO',
                'est_informacion_adicional' => array_key_exists('campos_adicionales', $est_informacion_adicional) ? json_encode(['campos_adicionales' => $est_informacion_adicional['campos_adicionales']]) : null,
                'usuario_creacion'          => $user->usu_id,
                'estado'                    => 'ACTIVO'
            ]);
        }
    }

    /**
     * Procesa registros que deben ser agendados para UBLADRECIBOBIEN.
     *
     * @param EventStatusUpdate $classReciboBien Clase que procesa el envío de eventos a la DIAN
     * @param User $user Colección de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Colección con la información del agendamiento en proceso
     * @param array $documentosAgendarNotificacionEventos Array de documentos que se agendarán para UBLADRECIBOBIEN
     * @return void
     */
    public function agendamientosNotificacionEventos(EventStatusUpdate $classReciboBien, User $user, AdoAgendamiento $agendamiento, array $documentosAgendarNotificacionEventos): void {
        if(!empty($documentosAgendarNotificacionEventos)) {
            $grupos = array_chunk($documentosAgendarNotificacionEventos, $user->getBaseDatos->bdd_cantidad_procesamiento_recibo);
            foreach ($grupos as $grupo) {
                $nuevoAgendamiento = $classReciboBien->nuevoAgendamiento('RUBLADRECIBOBIEN', $agendamiento, $user, count($grupo));
                foreach($grupo as $cdo_id) {
                    $classReciboBien->creaNuevoEstadoDocumento(
                        $cdo_id,
                        'RECIBOBIEN',
                        'UBLADRECIBOBIEN',
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
     * @param EventStatusUpdate $classReciboBien Clase principal del procesamiento del comando
     * @param User $user Instancia de usuario relacionado con el agendamiento
     * @param AdoAgendamiento $agendamiento Instancia del agendamiento en proceso
     * @return void
     */
    private function verificarSgteEstado(EventStatusUpdate $classReciboBien, User $user, AdoAgendamiento $agendamiento): void {
        // Documentos asociados al agendamiento
        $docsAgendamiento = RepEstadoDocumentoDaop::select(['cdo_id'])
            ->where('age_id', $agendamiento->age_id)
            ->where('est_estado', 'RECIBOBIEN')
            ->get()
            ->pluck('cdo_id')
            ->values()
            ->toArray();

        $documentosAgendarNotificacionEventos = [];
        RepCabeceraDocumentoDaop::select(['cdo_id'])
            ->whereIn('cdo_id', $docsAgendamiento)
            ->with([
                'getEstadoReciboBien:est_id,cdo_id,est_resultado',
                'getEstadoUblAdReciboBien:est_id,cdo_id'
            ])
            ->get()
            ->map(function($documento) use (&$documentosAgendarNotificacionEventos) {
                // El estado RECIBOBIEN fue exitoso pero el documento no tiene estado UBLADRECIBOBIEN por lo que se debe agendar
                if($documento->getEstadoReciboBien->est_resultado == 'EXITOSO' && !$documento->getEstadoUblAdReciboBien)
                    $documentosAgendarNotificacionEventos[] = $documento->cdo_id;
            });

        if(!empty($documentosAgendarNotificacionEventos))
            $this->agendamientosNotificacionEventos($classReciboBien, $user, $agendamiento, $documentosAgendarNotificacionEventos);
    }
}
