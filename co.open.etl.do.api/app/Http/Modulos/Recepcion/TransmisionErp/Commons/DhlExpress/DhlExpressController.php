<?php
namespace App\Http\Modulos\Recepcion\TransmisionErp\Commons\DhlExpress;

use App\Http\Traits\DoTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\RecepcionTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Mail\DhlExpress\notificarDocumentoRecepcionDhlExpress;
use App\Http\Modulos\Recepcion\TransmisionErp\Commons\MethodsTrait;
use App\Http\Modulos\ProyectosEspeciales\DHLExpress\FechasBassware\PryFechasBassware;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\ProyectosEspeciales\DHLExpress\CorreosNotificacionBasware\PryCorreoNotificacionBasware;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;

class DhlExpressController extends Controller {
    use DoTrait, MethodsTrait, RecepcionTrait;

    /**
     * Cantidad de documentos que se pueden consultar por procesamiento.
     *
     * @var int
     */
    private $cantidadDocumentosConsultar = 40;
    
    /**
     * Constructor de la clase
     *
     * @param Collection $oferente Colección con información del OFE en procesamiento
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Procesa los documentos que deban ser enviados.
     * 
     * @param integer $limiteIntentos Límite de inentos de transmisión
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Ofe relacionado con la transmisión
     * @param string $cdoIds IDs de documentos que se desean transmitir, este parámetro llega cuando el proceso es llamado desde el cliente web a través de la ruta /recepcion/documentos/transmitir-erp
     * @return void
     */
    public function procesar($limiteIntentos, $ofe, $cdoIds = null) {

        //Indica si se debe realizar la consulta a la base de datos
        $transmitir = false;

        //Incluyendo logica de control de fechas
        //Se deben incluir los documentos donde la fecha actual del sistema sea menor a la fecha de cierre
        //para notificarlos a bassware.
        //La consulta de los documentos solo se puede hacer desde la fecha de apertura, hasta la fecha de cierre del sistema Bassware
        //Si el periodo no existe se deben transmitir todo los documentos del mes sin importar la fecha.
        //Se transmite desde el comando
        $fechasBasware = PryFechasBassware::select(['fcb_periodo','fcb_fecha_apertura','fcb_fecha_cierre'])
            ->where('fcb_periodo', date('Ym'))
            ->where('estado', 'ACTIVO')
            ->first();
        
        //Trayendo el periodo anterior
        $periodoAnterior = date('Ym', strtotime('-1 month'));
        $fechasBaswarePeriodoAnterior = PryFechasBassware::select(['fcb_periodo','fcb_fecha_apertura','fcb_fecha_cierre'])
            ->where('fcb_periodo', $periodoAnterior)
            ->where('estado', 'ACTIVO')
            ->first();

        $fechaApertura = '';
        $fechaCierre   = '';
        //Si existe el registro se busca la fecha de apertura y la fecha de cierre
        if (!empty($fechasBasware)) {
            //La consulta solo se ejecutara si la fecha actual del sistema es mayor o igual a la fecha de aperura
            //y menor o igual a la fecha de cierre
            if (strtotime(date('Y-m-d')) >= strtotime($fechasBasware->fcb_fecha_apertura) &&
                strtotime(date('Y-m-d')) <= strtotime($fechasBasware->fcb_fecha_cierre)) {
                $transmitir = true;
                $fechaApertura = $fechasBasware->fcb_fecha_apertura;
                if (!empty($fechasBaswarePeriodoAnterior)) {
                    $fechaApertura = $fechasBaswarePeriodoAnterior->fcb_fecha_cierre;
                }
                $fechaCierre = $fechasBasware->fcb_fecha_cierre;
            }            
        } else {
            //Si no existe periodo aperturado, se transmiten todos los del mes
            //Si existe periodo anterior se incluyen los documentos que no se transmitieron de ese periodo
            $transmitir = true;
            $fechaApertura = date('Y-m-01');
            if (!empty($fechasBaswarePeriodoAnterior)) {
                $fechaApertura = $fechasBaswarePeriodoAnterior->fcb_fecha_cierre;
            }
            $fechaCierre = date('Y-m-d');
        }

        //Cuando la peticion se hace desde el tracking de documentos sin envio
        if ($cdoIds != null) {
            $transmitir = true;
        }

        $documentos = RepCabeceraDocumentoDaop::select([
            'cdo_id',
            'rfa_prefijo',
            'cdo_consecutivo',
            'cdo_clasificacion',
            'cdo_observacion',
            'ofe_id',
            'pro_id',
            'cdo_documento_referencia',
            'cdo_nombre_archivos',
            'fecha_creacion'
        ])
            ->whereHas('getRepEstadosDocumentoDaop', function($query) {
                $query->select(['est_id', 'cdo_id'])
                    ->where('est_estado', 'RDI')
                    ->where('est_resultado', 'EXITOSO')
                    ->where('est_ejecucion', 'FINALIZADO')
                    ->latest();
            });
            if ($cdoIds == null)
                $documentos = $documentos->whereDoesntHave('getRepEstadosDocumentoDaop', function($query) {
                    $query->select(['est_id', 'cdo_id'])
                        ->where('est_estado', 'TRANSMISIONERP')
                        ->where('est_ejecucion', 'ENPROCESO')
                        ->latest();
                });
            $documentos = $documentos->with([
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_razon_social'),
                        'ofe_recepcion_conexion_erp',
                        'ofe_envio_notificacion_amazon_ses',
                        'ofe_conexion_smtp'
                    ]);
                },
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                        'pro_id',
                        'pro_identificacion',
                        'pro_integracion_erp',
                        'pro_correos_notificacion',
                        DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as pro_razon_social')
                    ]);
                },
                'getRepEstadosDocumentoDaop:est_id,cdo_id,est_estado,est_informacion_adicional'
            ])
            ->withCount([
                'getTransmisionErpFallido' => function($query) {
                    $query->where('est_estado', 'TRANSMISIONERP')
                        ->where('est_resultado', 'FALLIDO')
                        ->where('est_ejecucion', 'FINALIZADO');
                }
            ])
            ->where('ofe_id', $ofe->ofe_id);

        if ($cdoIds != null) {
            $documentos = $documentos->whereIn('cdo_id', explode(',', $cdoIds));
        } else {
            $documentos = $documentos->doesntHave('getTransmisionErpExitoso')
                ->doesntHave('getTransmisionErpExcluido');
        }
        // dump($documentos->toSql());
        // dump($documentos->getBindings());
        $registrosProcesados = 0;
        $documentos = $documentos->get()
            ->map(function($documento) use ($limiteIntentos, &$transmitir, $ofe, $cdoIds, $fechaApertura, $fechaCierre, &$registrosProcesados) {
                $registrosProcesados++;
                if($registrosProcesados == $this->cantidadDocumentosConsultar) {
                    $registrosProcesados = 0;
                    $this->reiniciarConexion(auth()->user()->getBaseDatos);
                }

                if(
                    ($documento->get_transmision_erp_fallido_count < $limiteIntentos && $cdoIds == null) ||
                    $cdoIds != null
                ) {
                    //Inicio del proceso
                    $inicioMicrotime = microtime(true);

                    $estTranmisionErp = RepEstadoDocumentoDaop::select(['est_id', 'est_estado', 'est_resultado', 'est_ejecucion'])
                        ->where('cdo_id', $documento->cdo_id)
                        ->where('est_estado', 'TRANSMISIONERP')
                        ->orderBy('est_id', 'desc')
                        ->first();

                    $procesar = true;
                    if(!$estTranmisionErp || $cdoIds != null) {
                        $estTranmisionErp = $this->creaNuevoEstadoDocumentoRecepcion(
                            $documento->cdo_id,
                            'TRANSMISIONERP',
                            null,
                            null,
                            date('Y-m-d H:i:s'),
                            null,
                            null,
                            0,
                            auth()->user()->usu_id,
                            null,
                            'ENPROCESO'
                        );
                    } else {
                        if(
                            $estTranmisionErp->est_ejecucion == 'ENPROCESO' || 
                            ($estTranmisionErp->est_resultado == 'FALLIDO'  && $estTranmisionErp->est_ejecucion == 'FINALIZADO') ||
                            ($estTranmisionErp->est_resultado == 'EXITOSO'  && $estTranmisionErp->est_ejecucion == 'FINALIZADO') ||
                            ($estTranmisionErp->est_resultado == 'EXCLUIDO' && $estTranmisionErp->est_ejecucion == 'FINALIZADO')
                        )
                            $procesar = false;
                    }

                    if($procesar) {
                        $excluirCierre = $transmitir;

                        //Buscando el ultimo estado RDI exitoso
                        $estadoRDI = [];
                        foreach($documento->getRepEstadosDocumentoDaop as $estado) {
                            if ($estado->est_estado == "RDI")
                                $estadoRDI = $estado->est_informacion_adicional;
                        }
                        $informacionAdicional = !empty($estadoRDI) ? json_decode($estadoRDI, true) : [];

                        // Se obtiene el archivo xml-ubl del documento guardado en disco
                        $xmlUbl = $this->obtenerArchivoDeDisco(
                            'recepcion',
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );
                        $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

                        // Si la fecha de cierre es vacía se debe verificar si el Oferente tiene reglas para EXCLUIR_CIERRE
                        if ($fechaCierre == '') {
                            $respuesta = $this->verificaReglasXpath('EXCLUIR_CIERRE', $ofe->ofe_id, $documento->cdo_clasificacion, base64_encode($xmlUbl));
                        }

                        if (isset($respuesta) && $respuesta['aplica_regla']) {
                            $excluirCierre = true;
                        } elseif ($excluirCierre && isset($respuesta) && !$respuesta['aplica_regla'] && $cdoIds != null) {
                            $excluirCierre = ($documento->fecha_creacion > $fechaApertura.' 00:00:00' && $documento->fecha_creacion <= $fechaCierre.' 23:59:59') ? true : false;
                        }

                        if($excluirCierre || $cdoIds != null) {
                            try {
                                //Se envia correo de notificacion a Bassware si el campo
                                //pro_integracion_erp es igual a SI, si el valor es NO se descarta el proveedor,
                                //Si el valor es null, se realiza la siguiente logica:
                                //se debe buscar el NIT del proveedor en la tabla pry_correos_notificacion_basware 
                                //por el campo pro_identificacion, si este existe en la tabla se envia el correo,
                                //de lo NO
                                if ($documento->getConfiguracionProveedor->pro_integracion_erp == 'SI') {
                                    $enviar = true;
                                } elseif ($documento->getConfiguracionProveedor->pro_integracion_erp == 'NO') {
                                    $enviar = false;
                                } else {
                                    $correosBasware = PryCorreoNotificacionBasware::select(['pro_identificacion'])
                                        ->where('pro_identificacion', $documento->getConfiguracionProveedor->pro_identificacion)
                                        ->where('estado', 'ACTIVO')
                                        ->first();
                                    
                                    $enviar = !empty($correosBasware) ? true : false;
                                }

                                // Se verifica si el Oferente tiene tiene reglas para NO_TRANSMITIR
                                $respuesta = $this->verificaReglasXpath('NO_TRANSMITIR', $ofe->ofe_id, $documento->cdo_clasificacion, base64_encode($xmlUbl));

                                if(
                                    isset($ofe->ofe_recepcion_conexion_erp) &&
                                    !empty($ofe->ofe_recepcion_conexion_erp) &&
                                    array_key_exists('correo_notificacion_basware', $ofe->ofe_recepcion_conexion_erp) &&
                                    !empty($ofe->ofe_recepcion_conexion_erp['correo_notificacion_basware']) &&
                                    $enviar && !$respuesta['aplica_regla']
                                ) {
                                    // Se verifica si el Oferente tiene tiene reglas para NOTIFICAR
                                    $respuesta    = $this->verificaReglasXpath('NOTIFICAR', $ofe->ofe_id, $documento->cdo_clasificacion, base64_encode($xmlUbl));
                                    $arrNotificar = ($respuesta['aplica_regla'] == true && array_key_exists('notificar', $respuesta) && !empty($respuesta['notificar'])) ? $respuesta['notificar'] : [];
                                    $this->enviarCorreo($documento, $estTranmisionErp, $inicioMicrotime, $arrNotificar);
                                } else {
                                    if ($respuesta['aplica_regla'] || $documento->getConfiguracionProveedor->pro_integracion_erp == 'NO') {
                                        $strRespuestaERP     = '';
                                        $strMensajeResultado = '';
                                        //Se guarda como trasmitido excluido, pero se incluye la nota que no se transmite por las reglas y/o por la configuración del proveedor
                                        if ($respuesta['aplica_regla'] && $documento->getConfiguracionProveedor->pro_integracion_erp == 'NO') {
                                            $strRespuestaERP      = 'El documento [' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '] no fue enviado a Bassware, se excluyó por las siguientes reglas: ' . trim(implode(' // ', $respuesta['regla']));
                                            $strRespuestaERP     .= ' - se excluyó por la configuración del Proveedor, Notificar a Bassware parametrizado con NO.';
                                            $strMensajeResultado  = 'Proveedor excluido por las siguientes reglas: ' . trim(implode(', ', $respuesta['regla']));
                                            $strMensajeResultado .= ' - Proveedor excluido desde configuración, Notificar a Bassware parametrizado con NO.';
                                        } elseif ($documento->getConfiguracionProveedor->pro_integracion_erp == 'NO') {
                                            $strRespuestaERP     = 'El documento [' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '] no fue enviado a Bassware, se excluyó por la configuración del Proveedor, Notificar a Bassware parametrizado con NO.';
                                            $strMensajeResultado = 'Proveedor excluido desde configuración, Notificar a Bassware parametrizado con NO.';
                                        } elseif ($respuesta['aplica_regla']) {
                                            $strRespuestaERP     = 'El documento [' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '] no fue enviado a Bassware, se excluyó por las siguientes reglas: ' . trim(implode(' // ', $respuesta['regla']));
                                            $strMensajeResultado = 'Proveedor excluido por las siguientes reglas: ' . trim(implode(', ', $respuesta['regla']));
                                        }

                                        $arrRpta   = [];
                                        $arrRpta[] = [
                                            'respuesta_erp'        => ['Resultado' => $strRespuestaERP],
                                            'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
                                            'traza'                => null
                                        ];
                                        $this->actualizaEstadoDocumentoRecepcion(
                                            $estTranmisionErp->est_id,
                                            'EXCLUIDO',
                                            $strMensajeResultado,
                                            date('Y-m-d H:i:s'),
                                            number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                                            $arrRpta,
                                            'FINALIZADO'
                                        );
                                    }
                                }
                            } catch (\Exception $e) {
                                $arrExcepciones   = [];
                                $arrExcepciones[] = [
                                    'respuesta_erp'        => ['Resultado' => 'Envío fallido del documento [' . trim($documento->rfa_prefijo) . $documento->cdo_consecutivo . ']'],
                                    'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
                                    'traza'                => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                                ];
                                
                                $this->actualizaEstadoDocumentoRecepcion(
                                    $estTranmisionErp->est_id,
                                    'FALLIDO',
                                    $e->getMessage(),
                                    date('Y-m-d H:i:s'),
                                    number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                                    $arrExcepciones,
                                    'FINALIZADO'
                                );
                            }
                        } else {
                            $arrExcepciones   = [];
                            $arrExcepciones[] = [
                                'respuesta_erp'        => ['Resultado' => 'No Enviado por Exclusion Cierre. Documento [' . trim($documento->rfa_prefijo) . $documento->cdo_consecutivo . ']'],
                                'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
                                'traza'                => null
                            ];

                            $this->actualizaEstadoDocumentoRecepcion(
                                $estTranmisionErp->est_id,
                                'CIERRE',
                                'No Enviado por Exclusion Cierre.',
                                date('Y-m-d H:i:s'),
                                number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                                $arrExcepciones,
                                'FINALIZADO'
                            );
                        }
                    }
            }
        });
    }

    /**
     * Envía el correo con los documentos adjuntos (pdf y xml).
     *
     * @param RepCabeceraDocumentoDaop $documento Instancia del documento a enviar por correo
     * @param RepEstadoDocumentoDaop $estTranmisionErp Instancia del estado TRANSMISIONERP
     * @param integer  $inicioMicrotime Timestamp del inciio del proceso
     * @param array    $reglas Array con los titulos y descripción de las reglas
     * 
     * @return void
     */
    private function enviarCorreo($documento, $estTranmisionErp, $inicioMicrotime, $reglas) {
        if(!empty($documento->cdo_nombre_archivos)) {
            $nombreArchivos = json_decode($documento->cdo_nombre_archivos, true);
            $pdf            = array_key_exists('pdf', $nombreArchivos) && !empty($nombreArchivos['pdf']) ? $nombreArchivos['pdf'] : $documento->rfa_prefijo . $documento->cdo_consecutivo . '.pdf';
        } else {
            $pdf = $documento->rfa_prefijo . $documento->cdo_consecutivo . '.pdf';
        }

        //Buscando el ultimo estado RDI exitoso
        $estadoRDI = [];
        foreach($documento->getRepEstadosDocumentoDaop as $estado) {
            if ($estado->est_estado == "RDI") 
                $estadoRDI = $estado->est_informacion_adicional;
        }

        $informacionAdicional = !empty($estadoRDI) ? json_decode($estadoRDI, true) : [];
        
        $estArchivo = $this->obtenerArchivoDeDisco(
            'recepcion',
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
            $documento,
            array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
        );
        $estArchivo = $this->eliminarCaracteresBOM($estArchivo);

        $adjuntos['pdf'] = [
            'archivo' => $estArchivo,
            'nombre'  => $pdf,
            'mime'    => 'application/pdf'
        ];

        //Notas
        $notas = [];
        if ($documento->cdo_observacion != "") {
            $notas = $this->jsonToString($documento->cdo_observacion);
        }

        if (!empty(auth()->user()->bdd_id_rg))
            $baseDatos = auth()->user()->getBaseDatosRg->bdd_nombre;
        else
            $baseDatos = auth()->user()->getBaseDatos->bdd_nombre;

        $baseDatos        = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos);
        $canalEnvio       = DoTrait::establecerCanalEnvio($documento);
        $rutaBlade        = 'emails.' . $baseDatos . '.' . $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '.notificarDocumentoRecepcion';
        $destinatarios    = explode(',', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_conexion_erp['correo_notificacion_basware']);
        $correosProveedor = !empty($documento->getConfiguracionProveedor->pro_correos_notificacion) ? explode(',', $documento->getConfiguracionProveedor->pro_correos_notificacion) : [];

        // Si el proveedor no tiene correos de notificación se busca en la tabla de correos notificación bassware si el proveedor tiene correos internos
        if (empty($correosProveedor)) {
            $correosBasware = PryCorreoNotificacionBasware::select(['cnb_correo_interno'])
                ->where('pro_identificacion', $documento->getConfiguracionProveedor->pro_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();

            if (!empty($correosBasware)) {
                $correosProveedor = !empty($correosBasware->cnb_correo_interno) ? explode(',', $correosBasware->cnb_correo_interno) : [];
            }
        }

        $correosNotificacion = array_merge($destinatarios, $correosProveedor);
        $destinatarios       = array_unique($correosNotificacion);

        if (empty($destinatarios))
            throw new \Exception('No se encontro correo de Notificacion Basware.');

        $arrErroresCorreos = [];
        foreach ($destinatarios as $destinatario) {
            try {
                Mail::to($destinatario)
                    ->send(new notificarDocumentoRecepcionDhlExpress(
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_conexion_erp['codigo_compania'] . ' / ' .
                            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social . ' / ' .
                            $documento->cdo_clasificacion . ' / ' .
                            $documento->rfa_prefijo . $documento->cdo_consecutivo,
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social,
                        $canalEnvio['emailRemite'],
                        $notas,
                        $reglas,
                        $adjuntos,
                        $rutaBlade,
                        $canalEnvio['awsSesConfigurationSet']
                    ));
            } catch (\Exception $e) {
                $arrErroresCorreos[] = 'Error al enviar el correo al destinatario [' . $destinatario . ']: ' . $e->getMessage();
                continue;
            }
        }

        $arrRpta   = [];
        $arrRpta[] = [
            'respuesta_erp'        => ['Resultado' => 'Envío exitoso del documento [' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '] al correo [' . implode(',', $destinatarios) . ']'],
            'fecha_hora_respuesta' => date('Y-m-d H:i:s'),
            'traza'                => !empty($arrErroresCorreos) ? $arrErroresCorreos : null
        ];

        $this->actualizaEstadoDocumentoRecepcion(
            $estTranmisionErp->est_id,
            'EXITOSO',
            'FINALIZADO',
            date('Y-m-d H:i:s'),
            number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
            $arrRpta,
            'FINALIZADO'
        );
    }

    /**
     * Convierte un array en string.
     * 
     * @param string $json Cadena a codificar
     * @return string $cadena Cadena codificada
     */
    public function jsonToString($json){
        $json = json_decode($json, true);

        //si llega una cadena de texto en vez de un array, se convierte esta cadena a array
        //Control para la version 1 de la API, en donde estos campos eran tipo texto y no un array
        if (!is_array($json)) {
            $jsonDecode[] = $json;
        } else {
            $jsonDecode = $json;
        }
        return $jsonDecode;
    }
}
