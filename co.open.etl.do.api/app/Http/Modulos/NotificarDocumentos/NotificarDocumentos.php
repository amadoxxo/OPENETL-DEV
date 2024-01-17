<?php
namespace App\Http\Modulos\NotificarDocumentos;

use ZipArchive;
use Aws\Sns\Message;
use GuzzleHttp\Client;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Aws\Sns\MessageValidator;
use Illuminate\Support\Carbon;
use App\Mail\notificarDocumento;
use App\Http\Modulos\RgController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use openEtl\Tenant\Traits\TenantSmtp;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Engines\Documentos\BuscadorDocumentosEngine;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Documentos\EtlEventosNotificacionDocumentosDaop\EtlEventoNotificacionDocumentoDaop;

class NotificarDocumentos extends Controller {
    use FechaVigenciaValidations, DoTrait;

    /**
     * NIT de AGENCIA DE ADUANAS DHL EXPRESS COLOMBIA LTDA
     *
     * @var string
     */
    protected $nitAgenciaAduanasDhl = '830076778';

    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh'])
            ->except([
                'snsProcesamiento'
            ]);
    }

    /**
     * Procesa los documentos que se notificarán.
     *
     * @param array $documentos Array que contiene los IDs de los documentos que se deben notificar
     * @param Illuminate\Database\Eloquent\Collection $user Usuario relacionado con el procesamiento
     * @param boolean $reenviar Indica si se trata del reenvío de correos
     * @param array $estadosNotificar Array que contiene información sobre el estado Notificacion
     * @param array $correos_adicionales Array con los correos que serán notificados adicionales a los relacionados con el documento
     * @return array Array que contiene información sobre el procesamiento de los documentos, ya sea por fallas/errores o por notificaciones efectivas
     */
    public function NotificarDocumentos($documentos, $user, $reenviar = false, $estadosNotificar = [], $correos_adicionales = []) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Verifica que el parámetro recibido sea un array
        if(!is_array($documentos)) {
            $frase = (!$reenviar) ? 'realizar la transmisión de documentos a la Dian' : 'reenviar los correos';
            return [
                0 => [
                    'success' => false,
                    'errors'   => [
                        'El parámetro recibido para ' . $frase . ' debe ser del tipo Array y por cada posición del array contener el ID del documento a procesar'
                    ]
                ]
            ];
        }

        // Métodos Base
        $metodosBase = new MetodosBase();

        // Array de objetos de paramétricas
        $parametricas = [
            'tipoDocumento' => ParametrosTipoDocumento::select('tdo_id', 'tdo_codigo', 'tdo_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')->where('estado', 'ACTIVO')
                ->get()->groupBy('tdo_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),
            'tipoDocumentoElectronico' => ParametrosTipoDocumentoElectronico::select('tde_id', 'tde_codigo', 'tde_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')->where('estado', 'ACTIVO')
                ->get()->groupBy('tde_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),
            'municipio' => ParametrosMunicipio::select('mun_id', 'pai_id', 'dep_id', 'mun_codigo', 'mun_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')->where('estado', 'ACTIVO')
                ->get()
                ->groupBy(function($item, $key) {
                    return $item->pai_id . '~' . $item->dep_id . '~' . $item->mun_codigo;
                })
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),
            'tributo' => ParametrosTributo::select('tri_id', 'tri_codigo', 'tri_nombre', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')->where('estado', 'ACTIVO')
                ->get()->groupBy('tri_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray(),
            'ambienteDestino' => ParametrosAmbienteDestinoDocumento::select('add_id', 'add_codigo', 'add_descripcion', 'add_url', 'add_url_qrcode', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')->where('estado', 'ACTIVO')
                ->get()->toArray()
        ];

        $procesados   = [];
        $rgController = new RgController();
        $relaciones   = [
            'getCabeceraDocumentosDaop:cdo_id,ofe_id,adq_id,tde_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_fecha,cdo_hora,cdo_representacion_grafica_documento,usuario_creacion,cdo_contingencia,cdo_nombre_archivos,fecha_creacion',
            'getCabeceraDocumentosDaop.getDadDocumentosDaop:dad_id,cdo_id,cdo_informacion_adicional',
            'getCabeceraDocumentosDaop.getDetalleDocumentosDaop:ddo_id,cdo_id,ddo_informacion_adicional',
            'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,tdo_id,ref_id,ofe_correos_notificacion,ofe_notificacion_un_solo_correo,ofe_correos_autorespuesta,ofe_direccion,mun_id,ofe_telefono,ofe_web,ofe_correo,ofe_facebook,ofe_twitter,ofe_asunto_correos,ofe_envio_notificacion_amazon_ses,ofe_conexion_smtp,ofe_tiene_representacion_grafica_personalizada,ofe_cadisoft_activo,bdd_id_rg,ofe_archivo_certificado,ofe_password_certificado',
            'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getTributos:toa_id,ofe_id,tri_id',
            'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid',
            'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid',
            'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre',
            'getCabeceraDocumentosDaop.getConfiguracionAdquirente:adq_id,adq_identificacion,adq_razon_social,adq_nombre_comercial,adq_primer_apellido,adq_segundo_apellido,adq_primer_nombre,adq_otros_nombres,tdo_id,ref_id,adq_correo,adq_correos_notificacion',
            'getCabeceraDocumentosDaop.getConfiguracionAdquirente.getTributos:toa_id,adq_id,tri_id',
        ];

        $documentosDhlExpressCbo    = [];
        $documentosReenvioAgendarAD = [];
        TenantTrait::GetVariablesSistemaTenant();
        $opencomexCboNitsIntegracion = array_map('trim', explode(',', config('variables_sistema_tenant.OPENCOMEX_CBO_NITS_INTEGRACION')));

        foreach($documentos as $cdo_id) {
            if(!empty($estadosNotificar)) {
                // Marca el inicio de procesamiento del documento en el estado correspondiente
                EtlEstadosDocumentoDaop::find($estadosNotificar[$cdo_id]['est_id'])
                    ->update([
                        'est_ejecucion'      => 'ENPROCESO',
                        'est_inicio_proceso' => date('Y-m-d H:i:s')
                    ]);

                // Actualiza el microtime de inicio de procesamiento del documento
                $estadosNotificar[$cdo_id]['inicio'] = microtime(true);
            }

            // Se consulta el prefijo y consecutivo del documento
            // Esta consulta se realiza aparte ya que las demás parten de los estados del documento
            // y se debe contar con esta información para poder retornarla en los mensajes de error
            // Incluye adicionalmente información sobre los nombres de archivos para el documento
            $consecutivo = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_representacion_grafica_documento', 'cdo_nombre_archivos', 'cdo_fecha_validacion_dian'])
                ->where('cdo_id', $cdo_id)
                ->first();

            $documentoHistorico = false;

            if(!$consecutivo) {
                $buscador    = new BuscadorDocumentosEngine();
                $consecutivo = $buscador->getDocumentoHistorico($cdo_id, ['getDadDocumentosDaop', 'getDetalleDocumentosDaop']);

                // Reorganización de data de relaciones para emular el resultado de la consulta a la data operativa
                $consecutivo->__set('getConfiguracionObligadoFacturarElectronicamente', $consecutivo->getConfiguracionObligadoFacturarElectronicamente);
                $consecutivo->__set('getConfiguracionAdquirente', $consecutivo->getConfiguracionAdquirente);

                if(isset($consecutivo->getDadDocumentosDaop[0])) {
                    $getDadDocumentosDaop = $consecutivo->getDadDocumentosDaop[0];
                    $consecutivo->__unset('getDadDocumentosDaop', $consecutivo->getDadDocumentosDaop[0]);
                    $consecutivo->__set('getDadDocumentosDaop', $getDadDocumentosDaop);
                }

                $documentoHistorico = true;
            }

            try {
                // Si se van a reenviar correos, se consulta el estado NOTIFICACION exitoso y finalizado
                // ya que dicho tiene relacionado el AttachedDocument y el PDF de la representación gráfica
                if($reenviar) {
                    $documento = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', 'NOTIFICACION')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->orderBy('est_id', 'desc')
                        ->with($relaciones)
                        ->first();

                    if(!$documento) {
                        $particion = Carbon::parse($consecutivo->cdo_fecha_validacion_dian)->format('Ym');

                        $tblEstados = new EtlEstadosDocumentoDaop;
                        $tblEstados->setTable('etl_estados_documentos_' . $particion);

                        $documento = $tblEstados->select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'NOTIFICACION')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->orderBy('est_id', 'desc')
                            ->first();

                        $documento->getCabeceraDocumentosDaop = $consecutivo;
                    }
                } else {
                    // Si no es reenvío se consulta el último estado NOTIFICACION sin procesar
                    // Ya que dicho estado tiene relacionado el AttachedDocument
                    $documento = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', 'NOTIFICACION')
                        ->whereNull('est_resultado')
                        ->orderBy('est_id', 'desc')
                        ->with($relaciones)
                        ->first();
                }

                // Obtiene el último estado DO EXITOSO, necesario para varias validaciones
                $do = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id'])
                    ->where('cdo_id', $cdo_id)
                    ->where('est_estado', 'DO')
                    ->where('est_resultado', 'EXITOSO')
                    ->where('est_ejecucion', 'FINALIZADO')
                    ->orderBy('est_id', 'desc')
                    ->first();

                if(!$do) {
                    $particion = Carbon::parse($consecutivo->cdo_fecha_validacion_dian)->format('Ym');

                    $tblEstados = new EtlEstadosDocumentoDaop;
                    $tblEstados->setTable('etl_estados_documentos_' . $particion);

                    $do = $tblEstados->select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', 'DO')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->orderBy('est_id', 'desc')
                        ->first();
                }

                // Si en información adicional del estado de notificación en ejecucicón NO existe el campo "contingencia"
                // o existe pero el valor de este de diferente de true:
                //      Si NO es reenviar y si el campo cdo_contingencia del documento es SI y NO existe estado en DO EXITOSO, se debe generar el PDF
                //      y al momento de construir el attached document, no debe incluirse la sección del application response y notificar normalmente
                $contingencia                = false;
                $contingenciaVuelveNotificar = false; // Esta variable es porque después de notificar a la DIAN es posible que se deba volver a notificar al cliente, cuando fue contingencia
                if(
                    !empty($estadosNotificar) &&
                    (
                        !array_key_exists('contingencia', $estadosNotificar[$cdo_id]['estadoInformacionAdicional']) ||
                        (
                            array_key_exists('contingencia', $estadosNotificar[$cdo_id]['estadoInformacionAdicional']) &&
                            $estadosNotificar[$cdo_id]['estadoInformacionAdicional']['contingencia'] != true
                        )
                    ) &&
                    !$reenviar &&
                    $documento &&
                    $documento->getCabeceraDocumentosDaop->cdo_contingencia == 'SI' &&
                    !$do
                ) {
                    $contingencia = true;
                } elseif(
                    !empty($estadosNotificar) &&
                    (
                        array_key_exists('contingencia', $estadosNotificar[$cdo_id]['estadoInformacionAdicional']) &&
                        $estadosNotificar[$cdo_id]['estadoInformacionAdicional']['contingencia'] == true
                    ) &&
                    !$reenviar &&
                    $documento &&
                    $documento->getCabeceraDocumentosDaop->cdo_contingencia == 'SI' &&
                    $do
                ) {
                    $contingenciaVuelveNotificar = true;
                    $attachedDocumentExitoso = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', 'UBLATTACHEDDOCUMENT')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->orderBy('est_id', 'desc')
                        ->with($relaciones)
                        ->first();

                    $notificacionExitoso = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', 'NOTIFICACION')
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->orderBy('est_id', 'desc')
                        ->with($relaciones)
                        ->first();
                }

                $errorRg = '';
                $saltarProceso = false;
                if(!$contingencia && !$contingenciaVuelveNotificar && (!$documento || !$do)) {
                    $procesados[$cdo_id] = [
                        'consecutivo' => ($consecutivo) ? ($consecutivo->rfa_prefijo ?? '') . $consecutivo->cdo_consecutivo : '',
                        'success'     => false,
                        'errors'      => [],
                    ];
                    if(!$do && !$reenviar) {
                        $procesados[$cdo_id]['errors'][] = 'No se encontró estado DO Exitoso para el documento';
                    } elseif (!$do && $reenviar) {
                        $procesados[$cdo_id]['errors'][] = 'El documento NO ha sido transmitido a la DIAN';
                    }

                    if(!$documento && !$reenviar) {
                        $procesados[$cdo_id]['errors'][] = 'No se encontró estado que contiene el AttachedDocument para el documento';
                    } elseif(!$documento && $reenviar && !$documentoHistorico) {
                        // Se esta intenado reenviar los correos pero el documento no tiene un estado NOTIFICACION, EXITOSO y FINALIZADO
                        // Por lo que se debe validar si ya se procesó el AttachedDocument y poder definir si se crea un nuevo agendamiento para AttachedDocument o para Notificación
                        $ublAD = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'UBLATTACHEDDOCUMENT')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->orderBy('est_id', 'desc')
                            ->first();

                        if($do && $ublAD) {
                            $agendamientoNotificacion = DoTrait::crearNuevoAgendamiento('NOTIFICACION', $user->usu_id, $user->getBaseDatos->bdd_id, 1, null);
                            $this->creaNuevoEstadoDocumento($cdo_id, 'NOTIFICACION', $agendamientoNotificacion->age_id, $user->usu_id, (!empty($ublAD->est_informacion_adicional)) ? json_decode($ublAD->est_informacion_adicional) : null);
                            $procesados[$cdo_id]['success']      = true;
                            $procesados[$cdo_id]['contingencia'] = $contingencia;
                        } elseif($do && !$ublAD) {
                            $documentosReenvioAgendarAD[] = [
                                'cdo_id'                => $cdo_id,
                                'informacion_adicional' => null
                            ];

                            $procesados[$cdo_id]['success']      = true;
                            $procesados[$cdo_id]['contingencia'] = $contingencia;
                        }

                        $saltarProceso = true;
                    }

                    if($procesados[$cdo_id]['success'] === false)
                        throw new \Exception(implode(' - ', $procesados[$cdo_id]['errors']));
                } elseif($documento && $reenviar && !$documentoHistorico) {
                    if($do) {
                        $informacionAdicional = !empty($documento->est_informacion_adicional) ? json_decode($documento->est_informacion_adicional, true) : [];
                        $informacionAdicional = [
                            "reenvio"     => true,
                            "est_archivo" => array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                        ];

                        if (!empty($correos_adicionales))
                            $informacionAdicional['correos'] = $correos_adicionales;

                        $documentosReenvioAgendarAD[] = [
                            'cdo_id'                => $cdo_id,
                            'informacion_adicional' => $informacionAdicional
                        ];

                        $procesados[$cdo_id]['success']      = true;
                        $procesados[$cdo_id]['contingencia'] = $contingencia;

                        $saltarProceso = true;
                    }
                }

                if(!$saltarProceso) {
                    if($documento->getCabeceraDocumentosDaop->cdo_clasificacion != 'DS' && $documento->getCabeceraDocumentosDaop->cdo_clasificacion != 'DS_NC') {
                        if(empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico))
                            throw new \Exception('El Software de Proveedor Tecnológico para Documento Electrónico no se encuentra parametrizado.');
            
                        $addCodigo = $metodosBase->obtieneDatoParametrico(
                            $parametricas['ambienteDestino'],
                            'add_id',
                            $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id,
                            'add_codigo'
                        );
                    } else {
                        if(empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs))
                            throw new \Exception('El Software de Proveedor Tecnológico para Documento Soporte no se encuentra parametrizado.');
            
                        $addCodigo = $metodosBase->obtieneDatoParametrico(
                            $parametricas['ambienteDestino'],
                            'add_id',
                            $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs->add_id,
                            'add_codigo'
                        );
                    }

                    if(!isset($addCodigo) || (isset($addCodigo) && $addCodigo == '')) {
                        throw new \Exception("No se encontró el código del ambiente de destino del documento");
                    }

                    if($reenviar) {
                        $informacionAdicional = !empty($documento->est_informacion_adicional) ? json_decode($documento->est_informacion_adicional, true) : [];

                        $xmlAttachedDocument = $this->obtenerArchivoDeDisco(
                            'emision',
                            $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento->getCabeceraDocumentosDaop,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );
                        $attachedDocument['attachedDocument'] = $this->eliminarCaracteresBOM($xmlAttachedDocument);

                        $representacionGrafica = $this->obtenerArchivoDeDisco(
                            'emision',
                            $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento->getCabeceraDocumentosDaop,
                            array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                        );
                        $representacionGrafica = $this->eliminarCaracteresBOM($representacionGrafica);
                    } elseif($contingenciaVuelveNotificar && $attachedDocumentExitoso && $notificacionExitoso) {
                        $informacionAdicionalAd = !empty($attachedDocumentExitoso->est_informacion_adicional) ? json_decode($attachedDocumentExitoso->est_informacion_adicional, true) : [];
                        
                        $xmlAttachedDocument = $this->obtenerArchivoDeDisco(
                            'emision',
                            $attachedDocumentExitoso->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $attachedDocumentExitoso->getCabeceraDocumentosDaop,
                            array_key_exists('est_xml', $informacionAdicionalAd) ? $informacionAdicionalAd['est_xml'] : null
                        );
                        $attachedDocument['attachedDocument'] = $this->eliminarCaracteresBOM($xmlAttachedDocument);
                        
                        $informacionAdicionalRg = !empty($notificacionExitoso->est_informacion_adicional) ? json_decode($notificacionExitoso->est_informacion_adicional, true) : [];
                        $representacionGrafica = $this->obtenerArchivoDeDisco(
                            'emision',
                            $notificacionExitoso->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $notificacionExitoso->getCabeceraDocumentosDaop,
                            array_key_exists('est_archivo', $informacionAdicionalRg) ? $informacionAdicionalRg['est_archivo'] : null
                        );
                        $representacionGrafica = $this->eliminarCaracteresBOM($representacionGrafica);
                    } else {
                        $informacionAdicional = !empty($documento->est_informacion_adicional) ? json_decode($documento->est_informacion_adicional, true) : [];
                        $xmlAttachedDocument = $this->obtenerArchivoDeDisco(
                            'emision',
                            $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            $documento->getCabeceraDocumentosDaop,
                            array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                        );
                        $attachedDocument['attachedDocument'] = $this->eliminarCaracteresBOM($xmlAttachedDocument);

                        $ublAD = EtlEstadosDocumentoDaop::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'UBLATTACHEDDOCUMENT')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->where('est_informacion_adicional', 'LIKE', '%"reenvio":true%')
                            ->orderBy('est_id', 'desc')
                            ->first();

                        if ($ublAD) {
                            $representacionGrafica = $this->obtenerArchivoDeDisco(
                                'emision',
                                $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                $documento->getCabeceraDocumentosDaop,
                                array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                            );
                            $representacionGrafica = $this->eliminarCaracteresBOM($representacionGrafica);
                        } else {
                            // Representación gráfica del documento
                            $request = new Request();
                            $arrRequest = [
                                'cdo_id' => $cdo_id
                            ];
                            $request->headers->add(['accept' => 'application/json']);
                            $request->headers->add(['x-requested-with' => 'XMLHttpRequest']);
                            $request->headers->add(['content-type' => 'application/json']);
                            $request->headers->add(['cache-control' => 'no-cache']);
                            $request->request->add($arrRequest);
                            $request->json()->add($arrRequest);
                            $getRepresentacionGrafica = $rgController->getPdfRepresentacionGraficaDocumento($request);
                            if(array_key_exists('data', $getRepresentacionGrafica->original) && array_key_exists('pdf', $getRepresentacionGrafica->original['data']) && !empty($getRepresentacionGrafica->original['data']['pdf'])) {
                                $representacionGrafica = base64_decode($getRepresentacionGrafica->original['data']['pdf']);
                            } else {
                                $errorRg = 'No Existe la Representación Gráfica ' . $consecutivo->cdo_representacion_grafica_documento . ' para el NIT ' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion;
                                throw new \Exception($errorRg);
                            }
                        }
                    }

                    switch($documento->getCabeceraDocumentosDaop->cdo_clasificacion) {
                        case 'FC':
                            $tipoDocumento = 'FACTURA';
                            break;
                        case 'NC':
                            $tipoDocumento = 'NOTA CRÉDITO';
                            break;
                        case 'ND':
                            $tipoDocumento = 'NOTA DÉBITO';
                            break;
                    }

                    // Para los nombres de los archivos se verifica si existen en la BD, sino existen se deben crear
                    $nombreArchivos = $consecutivo->cdo_nombre_archivos != '' ? json_decode($consecutivo->cdo_nombre_archivos) : json_decode(json_encode([]));
                    if(
                        $consecutivo->cdo_nombre_archivos != '' &&
                        (
                            (isset($nombreArchivos->xml_ubl) && $nombreArchivos->xml_ubl != '') ||
                            (isset($nombreArchivos->xml_ubl) && $nombreArchivos->pdf != '')
                        )
                    ) {
                        if (isset($nombreArchivos->xml_ubl) && $nombreArchivos->xml_ubl != '') {
                            $archivoPdf = str_replace('.xml', '.pdf', $nombreArchivos->xml_ubl);
                        } else {
                            $archivoPdf = $nombreArchivos->pdf;
                        }
                    } else {
                        $archivoPdf = $documento->getCabeceraDocumentosDaop->cdo_clasificacion . ($documento->getCabeceraDocumentosDaop->rfa_prefijo ?? '') . $documento->getCabeceraDocumentosDaop->cdo_consecutivo . '.pdf';
                    }

                    if(
                        $consecutivo->cdo_nombre_archivos != '' &&
                        isset($nombreArchivos->attached) &&
                        $nombreArchivos->attached != ''
                    ) {
                        $archivoAd = $nombreArchivos->attached;
                    } else {
                        $archivoAd = $documento->getCabeceraDocumentosDaop->cdo_clasificacion . ($documento->getCabeceraDocumentosDaop->rfa_prefijo ?? '') . $documento->getCabeceraDocumentosDaop->cdo_consecutivo . '.xml';
                    }

                    if(
                        $consecutivo->cdo_nombre_archivos != '' &&
                        isset($nombreArchivos->zip) &&
                        $nombreArchivos->zip != ''
                    ) {
                        $archivoZip = $nombreArchivos->zip;
                    } else {
                        $archivoZip = $documento->getCabeceraDocumentosDaop->cdo_clasificacion . ($documento->getCabeceraDocumentosDaop->rfa_prefijo ?? '') . $documento->getCabeceraDocumentosDaop->cdo_consecutivo . '.zip';
                    }

                    // Creando Directorio temporal para el documento
                    // Se crea el directorio id base de datos ~ id OFE ~ modulo ~ id Documento
                    $directorioDocumento = $user->getBaseDatos->bdd_id . "~" . 
                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_id . 
                        "~emision~" . 
                        $cdo_id;
                    Storage::makeDirectory($directorioDocumento, 0755);

                    // Valida si en la información adicional del documento existe el índice correos_receptor
                    // y si no está vacío las notificaciones son enviadas a esos correos
                    $destinatarios = [];
                    $zipCertifMandatoContent = '';
                    if($documento->getCabeceraDocumentosDaop->getDadDocumentosDaop->cdo_informacion_adicional != '') {
                        $informacionAdicional = $documento->getCabeceraDocumentosDaop->getDadDocumentosDaop->cdo_informacion_adicional;
                        if(array_key_exists('correos_receptor', $informacionAdicional) && $informacionAdicional['correos_receptor'] != '') {
                            $destinatarios = explode(';', $informacionAdicional['correos_receptor']); 
                        }

                        // Adjunta el certificado de mandato en un zip
                        if(array_key_exists('certificado_mandato', $informacionAdicional) && $informacionAdicional['certificado_mandato'] != '') {
                            // Obtiene el registro del estado EDI que es en donde se guarda el nombre del archivo que se contiene el certificado de mandato
                            $estadoDI = EtlEstadosDocumentoDaop::select('est_informacion_adicional')
                                ->where('cdo_id', $cdo_id)
                                ->where('est_estado', 'EDI')
                                ->where('est_resultado', 'EXITOSO')
                                ->where('est_ejecucion', 'FINALIZADO')
                                ->orderBy('est_id', 'desc')
                                ->first();

                            $informacionAdicionalDI = !empty($estadoDI->est_informacion_adicional) ? json_decode($estadoDI->est_informacion_adicional, true) : [];
                            $certificadoMandato = $this->obtenerArchivoDeDisco(
                                'emision',
                                $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                $documento->getCabeceraDocumentosDaop,
                                array_key_exists('certificado', $informacionAdicionalDI) ? $informacionAdicionalDI['certificado'] : null
                            );
                            $certificadoMandato = $this->eliminarCaracteresBOM($certificadoMandato);

                            $rutaAnexosZip    = storage_path().'/etl/descargas/'.$directorioDocumento.'/anexos.zip';
                            $zipCertifMandato = new ZipArchive();
                            $zipCertifMandato->open($rutaAnexosZip, ZipArchive::OVERWRITE | ZipArchive::CREATE);
                            $zipCertifMandato->addFromString('Certificado_' . ($documento->getCabeceraDocumentosDaop->rfa_prefijo ?? '') . $documento->getCabeceraDocumentosDaop->cdo_consecutivo . '.pdf', $certificadoMandato);
                            $zipCertifMandato->close();
                            
                            $zipCertifMandatoContent = file_get_contents($rutaAnexosZip);
                            @unlink($rutaAnexosZip);
                        }
                    }

                    if(empty($attachedDocument['attachedDocument']) || empty($representacionGrafica)) {
                        throw new \Exception("El AttachedDocument o Representación Gráfica están vacios");
                    }

                    // Agrega los archivos al ZIP que se enviará en el correo
                    $rutaArchivoZip = storage_path().'/etl/descargas/'.$directorioDocumento.'/'.$archivoZip;
                    $oZip = new ZipArchive();
                    $oZip->open($rutaArchivoZip, ZipArchive::OVERWRITE | ZipArchive::CREATE);
                    $oZip->addFromString($archivoAd, $attachedDocument['attachedDocument']);
                    $oZip->addFromString($archivoPdf, $representacionGrafica);
                    if(isset($zipCertifMandatoContent) && !empty($zipCertifMandatoContent))
                        $oZip->addFromString('anexos.zip', $zipCertifMandatoContent);
                    $oZip->close();
                    $tamanoZip  = File::size($rutaArchivoZip);
                    $zipContent = file_get_contents($rutaArchivoZip);
                    @unlink($rutaArchivoZip);
                    Storage::deleteDirectory($directorioDocumento);

                    $mensajeResultado = null;
                    if($tamanoZip > 2097152) {
                        $mensajeResultado = 'El tamaño del Archivo .zip adjunto al correo es superior a 2M por lo que no cumple con lo especificado en el Anexo Técnico de Factura Electrónica de Venta de la DIAN, sin embargo el documento fue notificado.';
                    }
                    $adjuntos['zip'] = [
                        'archivo' => $zipContent,
                        'nombre'  => $archivoZip,
                        'mime'    => 'application/zip' // , application/octet-stream
                    ];

                    if(count($destinatarios) == 0 && $documento->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_correos_notificacion != '') {
                        $destinatarios = explode(',', $documento->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_correos_notificacion);
                    } elseif (count($destinatarios) == 0 && $documento->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_correos_notificacion == '') {
                        $destinatarios[] = $documento->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_correo;
                    }

                    // Verifica si el OFE tiene configurados correos de notificacion
                    if($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correos_notificacion != '') {
                        $destinatarios = array_merge($destinatarios, explode(',', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correos_notificacion));
                    }

                    //Verifica si en la información adicional, está la llave correos, para adjuntarlos a los destinatarios
                    $informacionAdicionalCorreos = !empty($documento->est_informacion_adicional) ? json_decode($documento->est_informacion_adicional, true) : [];
                    if (array_key_exists('correos', $informacionAdicionalCorreos) && !empty($informacionAdicionalCorreos['correos'])) {
                        $destinatarios = array_merge($destinatarios, $informacionAdicionalCorreos['correos']);
                    }

                    if (!empty($correos_adicionales)) {
                        $destinatarios = array_merge($destinatarios, $correos_adicionales);
                    }

                    // Verifica si el OFE tiene configurados correos de autorespuesta
                    $correosAutorespuesta = [];
                    if($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correos_autorespuesta != '') {
                        $correosAutorespuesta = explode(',', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correos_autorespuesta);
                    }

                    if(isset($destinatarios) && !empty($destinatarios)) {
                        $destinatarios = array_unique($destinatarios);

                        //Base de datos asosicada al OFE
                        $baseDatos  = (!empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->bdd_id_rg)) ? $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->getBaseDatosRg->bdd_nombre : $user->getBaseDatos->bdd_nombre;
                        $baseDatos  = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos);
                        $sufijoLogo = ($documento->getCabeceraDocumentosDaop->cdo_clasificacion == 'DS' || $documento->getCabeceraDocumentosDaop->cdo_clasificacion == 'DS_NC') ? '_ds' : '';

                        // Aplica a Federación Nacional de Cafeteros
                        if($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == '860007538') {
                            if(array_key_exists('logo_cabecera', $informacionAdicional) && $informacionAdicional['logo_cabecera'] == 'BUENCAFE') {
                                $ofeLogo  = base_path().'/storage/app/public/ecm/assets-ofes/' . $baseDatos . '/' .
                                    $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                                    'logo_buencafe.png';
                            } else {
                                $ofeLogo  = base_path().'/storage/app/public/ecm/assets-ofes/' . $baseDatos . '/' .
                                    $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                                    'logo' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . $sufijoLogo . '.png';
                            }
                        // Aplica para Coomeva
                        } elseif($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == '805000427') {
                            if($documento->getCabeceraDocumentosDaop->cdo_representacion_grafica_documento == '2' || $documento->getCabeceraDocumentosDaop->cdo_representacion_grafica_documento == '3') {
                                $ofeLogo  = base_path().'/storage/app/public/ecm/assets-ofes/' . $baseDatos . '/' .
                                    $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                                    'logoPacCorreos805000427.png';
                            } else {
                                $ofeLogo  = base_path().'/storage/app/public/ecm/assets-ofes/' . $baseDatos . '/' .
                                    $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' . 
                                    'logo' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . $sufijoLogo . '.png';
                            }
                        // Aplica de manera general a todos los OFEs
                        } else {
                            if ($documento->getCabeceraDocumentosDaop->cdo_clasificacion == 'DS' || $documento->getCabeceraDocumentosDaop->cdo_clasificacion == 'DS_NC') {
                                if ($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_tiene_representacion_grafica_personalizada_ds === 'SI') {
                                    $ofeLogo  = base_path().'/storage/app/public/ecm/assets-ofes/' . $baseDatos . '/' .
                                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' .
                                        'logo' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '_ds.png';
                                } else {
                                    DoTrait::setFilesystemsInfo();
                                    $directorio = Storage::disk(config('variables_sistema.ETL_LOGOS_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
                                    $ofeLogo    = $directorio . $baseDatos . '/' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion .
                                        '/assets/' . 'logo' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '_ds.png';
                                }
                            } else {
                                if ($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_tiene_representacion_grafica_personalizada === 'SI') {
                                    $ofeLogo  = base_path().'/storage/app/public/ecm/assets-ofes/' . $baseDatos . '/' .
                                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/' .
                                        'logo' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '.png';
                                } else {
                                    DoTrait::setFilesystemsInfo();
                                    $directorio = Storage::disk(config('variables_sistema.ETL_LOGOS_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
                                    $ofeLogo    = $directorio . $baseDatos . '/' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion .
                                        '/assets/' . 'logo' . $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '.png';
                                }
                            }
                        }

                        $dvOfe = TenantTrait::calcularDV($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion);

                        // Arma la información del registro que se pasará al correo
                        $registro = [
                            // Info documento
                            'tipo_documento'         => $tipoDocumento,
                            'rfa_prefijo'            => ($documento->getCabeceraDocumentosDaop->rfa_prefijo ?? ''),
                            'consecutivo'            => $documento->getCabeceraDocumentosDaop->cdo_consecutivo,
                            'fecha_hora_documento'   => $documento->getCabeceraDocumentosDaop->cdo_fecha . ' ' . $documento->getCabeceraDocumentosDaop->cdo_hora,
                            'bdd_id'                 => $user->getBaseDatos->bdd_id,
                            'cdo_id'                 => $documento->getCabeceraDocumentosDaop->cdo_id,
                            'representacion_grafica' => $documento->getCabeceraDocumentosDaop->cdo_representacion_grafica_documento,
                            // Info general
                            "path"                   => base_path(),
                            "remite"                 => $metodosBase->obtenerNombre($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente, 'ofe'),
                            "ofe_base_datos"         => $baseDatos,
                            "ofe_identificacion"     => $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                            "ofe_logo"               => $ofeLogo,
                            "direccion"              => $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_direccion,
                            "ciudad"                 => $metodosBase->obtieneDatoParametrico($parametricas['municipio'], 'mun_id', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->mun_id, 'mun_descripcion'),
                            "telefono"               => $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_telefono,
                            "web"                    => $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_web,
                            "email"                  => $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correo,
                            "facebook"               => $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_facebook,
                            "twitter"                => $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_twitter,
                            "adquirente"             => $metodosBase->obtenerNombre($documento->getCabeceraDocumentosDaop->getConfiguracionAdquirente, 'adq'),
                            "correo_autorespuesta"   => !empty($correosAutorespuesta) && $documento->getCabeceraDocumentosDaop->cdo_clasificacion != 'DS' ? $correosAutorespuesta[0] : ''
                        ];

                        // Campos particulares para mostrar en el correo
                        if(isset($informacionAdicional)) {
                            $registro['razon_receptor'] = "";
                            // Válida si se envía la razón social del receptor para pintarla en la notificación
                            if (array_key_exists('receptor_notificacion', $informacionAdicional) && $informacionAdicional['receptor_notificacion'] != '') {
                                $registro['razon_receptor'] = $informacionAdicional['receptor_notificacion'];
                            }

                            switch ($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion) {
                                case "860030380":
                                case "830002397":
                                    // Aplica para DHLGlobal y DHLAduanas
                                    $registro['documento_transporte'] = (array_key_exists('guia_hija', $informacionAdicional) && $informacionAdicional['guia_hija'] != '') ? $informacionAdicional['guia_hija'] : '';
                                    $registro['documento_transporte'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['documento_transporte']));
                                    break;
                                case "800251957":
                                    // Aplica para Siaco
                                    $registro['numero_operacion'] = (array_key_exists('referencia', $informacionAdicional) && $informacionAdicional['referencia'] != '') ? $informacionAdicional['referencia'] : '';
                                    $registro['numero_operacion'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['numero_operacion']));
                                    break;
                                case "860079024":
                                case "900698414":
                                    // Aplica para Repremundo
                                    // Aplica para RIS
                                    $registro['numero_operacion'] = (array_key_exists('guia_numero', $informacionAdicional) && $informacionAdicional['guia_numero'] != '') ? $informacionAdicional['guia_numero'] : '';
                                    $registro['numero_operacion'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['numero_operacion']));
                                    break;
                                case "830104929":
                                    // Aplica para Logistica Repremundo
                                    $items = $documento->getCabeceraDocumentosDaop->getDetalleDocumentosDaop;
                                    $arrNumOperacion = array();
                                    foreach ($items as $item) {
                                        $informacionAdicionalItem = json_decode($item->ddo_informacion_adicional);
                                        if (isset($informacionAdicionalItem->remesa) && $informacionAdicionalItem->remesa == 'SI') {
                                            $arrNumOperacion[] = $item->ddo_codigo;
                                        }
                                    }
                                    $registro['numero_operacion'] = (count($arrNumOperacion) > 0) ? implode(', ', $arrNumOperacion) : '';
                                    $registro['numero_operacion'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['numero_operacion']));
                                    break;
                                case "830004237":
                                    // Aplica para MapCargo
                                    $registro['numero_operacion'] = (array_key_exists('posicion', $informacionAdicional) && $informacionAdicional['posicion'] != '') ? $informacionAdicional['posicion'] : '';
                                    $registro['numero_operacion'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['numero_operacion']));
                                    break;
                                case "811028981":
                                    // Aplica para Malco Cargo
                                    $registro['pedido'] = (array_key_exists('pedido', $informacionAdicional) && $informacionAdicional['pedido'] != '') ? $informacionAdicional['pedido'] : '';
                                    $registro['pedido'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['pedido']));
                                    break;
                                case "890902266":
                                    // Aplica para Mario Londoño
                                    $registro['pedido'] = (array_key_exists('pedido', $informacionAdicional) && $informacionAdicional['pedido'] != '') ? $informacionAdicional['pedido'] : '';
                                    $registro['tipo_operacion'] = (array_key_exists('tipo_operacion', $informacionAdicional) && $informacionAdicional['tipo_operacion'] != '') ? $informacionAdicional['tipo_operacion'] : '';

                                    $registro['pedido'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['pedido']));
                                    $registro['tipo_operacion'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['tipo_operacion']));
                                    break;
                                case "860028026":
                                    // Aplica para Aduanera
                                    $registro['pedidos_completos'] = (array_key_exists('pedidos_completos', $informacionAdicional) && $informacionAdicional['pedidos_completos'] != '') ? ((strlen($informacionAdicional['pedidos_completos']) > 100) ? substr($informacionAdicional['pedidos_completos'], 0, 100) : $informacionAdicional['pedidos_completos']) : '';
                                    $registro['pedidos_completos'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['pedidos_completos']));
                                    break;
                                case "800024075": // Coltrans
                                case "900841486": // Coldepositos Logistica
                                case "901016877": // Coldeposits Bodega Nacional
                                case "900451936": // Col OTM 
                                    //Para el grupo coltrans aplica la siguiente logica:
                                    // 1.	A nivel de OFE, el cliente parametrizará en la sección “PERSONALIZACIÓN DEL ASUNTO EN LOS CORREOS ENVIADOS A LOS ADQUIRENTES”, 
                                    // el texto a buscar en la información personalizada enviada y que se debe llevar al asunto del correo. 
                                    // 2.	Este texto debe buscarse en todos los campos de información personalizada a nivel de cabecera, 
                                    // y si alguno de ellos comienza por el texto parametrizado en el OFE, la información de este campo debe llevarse al asunto.  
                                    $registro['informacion_col'] = '';
                                    if (isset($informacionAdicional) && !empty($informacionAdicional)) {
                                        $campoAsunto = $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_asunto_correos;
                                        foreach ($informacionAdicional as $campo) {
                                            if ($campoAsunto == substr($campo, 0, strlen($campoAsunto))) {
                                                $registro['informacion_col'] = $campo;
                                                $registro['informacion_col'] = html_entity_decode(str_replace('  ', '&nbsp;&nbsp;', $registro['informacion_col']));
                                            }
                                        }
                                    }
                                    //Se asigna null para que no se lleve al asunto
                                    $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_asunto_correos = null;
                                    break;
                                default:
                                    //No hace nada
                                    break;
                            }
                        }

                        // Verifica si para el ofe se debe notificar en un solo correo o en varios
                        if($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_notificacion_un_solo_correo == 'SI') {
                            $arrTmp = [];
                            foreach ($destinatarios as $destinatario) {
                                if($destinatario != '' && filter_var($destinatario, FILTER_VALIDATE_EMAIL) !== false) {
                                    $arrTmp[] = $destinatario;
                                }
                            }
                            $destinatarios = $arrTmp;

                            if(
                                empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses) ||
                                $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses == 'NO'
                            ) {
                                //Configuracion de AWS
                                $awsSesConfigurationSet = null;

                                //Para los OFE que mantienen la configuracion del servicio de correo anterior a AWS
                                if (
                                    !empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp) &&
                                    (
                                        array_key_exists('driver', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp) || 
                                        array_key_exists('from_email', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)
                                    )
                                ) {
                                    // Establece el email del remitente del correo
                                    if(array_key_exists('from_email', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)) {
                                        $emailRemitente = $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp['from_email'];
                                    } else {
                                        $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;
                                    }
                                    
                                    // Verifica si existe conexión especial a un servidor SMTP del OFE
                                    if (array_key_exists('driver', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)) {
                                        TenantSmtp::setSmtpConnection($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp);
                                    } else {
                                        DoTrait::setMailInfo();
                                    }
                                } else {
                                    // Establece el email del remitente del correo
                                    $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;
                                    
                                    //Se va por el estandar
                                    DoTrait::setMailInfo();
                                }
                            } elseif(
                                $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses == 'SI' &&
                                !empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)
                            ) {
                                $conexionOfeAws = $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp;
                                DoTrait::servicioAwsSesDinamico($conexionOfeAws['AWS_ACCESS_KEY_ID'], $conexionOfeAws['AWS_SECRET_ACCESS_KEY'], $conexionOfeAws['AWS_REGION']);

                                $emailRemitente         = $conexionOfeAws['AWS_FROM_EMAIL'];
                                $awsSesConfigurationSet = $conexionOfeAws['AWS_SES_CONFIGURATION_SET'];
                            } else {
                                DoTrait::servicioAwsSesDinamico(config('variables_sistema.AWS_ACCESS_KEY_ID'), config('variables_sistema.AWS_SECRET_ACCESS_KEY'), config('variables_sistema.AWS_REGION'));
                                $emailRemitente         = config('variables_sistema.AWS_FROM_EMAIL');
                                $awsSesConfigurationSet = config('variables_sistema.AWS_SES_CONFIGURATION_SET');
                            }

                            if ($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_cadisoft_activo === 'SI'  && !isset($informacionAdicional['cdo_integracion'])) {
                                $rutaBlade = 'emails.etl_cadisoft.Cadisoft.notificarDocumentoCadisoftBase';
                            } else {
                                if ($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_tiene_representacion_grafica_personalizada === 'SI') {
                                    $rutaBlade = 'emails.' . $registro['ofe_base_datos'] . '.' . $registro['ofe_identificacion'] . '.notificarDocumento';
                                } else {
                                    $rutaBlade = 'emails.etl_generica.Generica.notificarDocumento';  
                                }
                            }

                            Mail::to($destinatarios)->send(new notificarDocumento(
                                $registro,
                                $adjuntos,
                                $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                $dvOfe,
                                $metodosBase->obtenerNombre($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente, 'ofe'),
                                $emailRemitente,
                                $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_asunto_correos,
                                $metodosBase->obtieneDatoParametrico($parametricas['tipoDocumentoElectronico'], 'tde_id', $documento->getCabeceraDocumentosDaop->tde_id, 'tde_codigo'),
                                $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_nombre_comercial,
                                !empty($correosAutorespuesta) ? $correosAutorespuesta[0] : null,
                                $rutaBlade,
                                $awsSesConfigurationSet
                            ));
                        } else {
                            foreach ($destinatarios as $destinatario) {
                                if($destinatario != '' && filter_var($destinatario, FILTER_VALIDATE_EMAIL) !== false) {
                                    if(
                                        empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses) ||
                                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses == 'NO'
                                    ) {
                                        //Configuracion de AWS
                                        $awsSesConfigurationSet = null;
            
                                        //Para los OFE que mantienen la configuracion del servicio de correo anterior a AWS
                                        if (
                                            !empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp) &&
                                            (
                                                array_key_exists('driver', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp) || 
                                                array_key_exists('from_email', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)
                                            )
                                        ) {
                                            // Establece el email del remitente del correo
                                            if(array_key_exists('from_email', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)) {
                                                $emailRemitente = $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp['from_email'];
                                            } else {
                                                $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;
                                            }
                                            
                                            // Verifica si existe conexión especial a un servidor SMTP del OFE
                                            if (array_key_exists('driver', $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)) {
                                                TenantSmtp::setSmtpConnection($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp);
                                            } else {
                                                DoTrait::setMailInfo();
                                            }
                                        } else {
                                            // Establece el email del remitente del correo
                                            $emailRemitente = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;
                                            
                                            //Se va por el estandar
                                            DoTrait::setMailInfo();
                                        }
                                    } elseif(
                                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses == 'SI' &&
                                        !empty($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)
                                    ) {
                                        $conexionOfeAws = $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp;
                                        DoTrait::servicioAwsSesDinamico($conexionOfeAws['AWS_ACCESS_KEY_ID'], $conexionOfeAws['AWS_SECRET_ACCESS_KEY'], $conexionOfeAws['AWS_REGION']);
            
                                        $emailRemitente         = $conexionOfeAws['AWS_FROM_EMAIL'];
                                        $awsSesConfigurationSet = $conexionOfeAws['AWS_SES_CONFIGURATION_SET'];
                                    } else {
                                        DoTrait::servicioAwsSesDinamico(config('variables_sistema.AWS_ACCESS_KEY_ID'), config('variables_sistema.AWS_SECRET_ACCESS_KEY'), config('variables_sistema.AWS_REGION'));
                                        $emailRemitente         = config('variables_sistema.AWS_FROM_EMAIL');
                                        $awsSesConfigurationSet = config('variables_sistema.AWS_SES_CONFIGURATION_SET');
                                    }

                                    if ($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_cadisoft_activo === 'SI' && !isset($informacionAdicional['cdo_integracion'])) {
                                        $rutaBlade = 'emails.etl_cadisoft.Cadisoft.notificarDocumentoCadisoftBase';
                                    } else {
                                        if ($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_tiene_representacion_grafica_personalizada === 'SI') {
                                            $rutaBlade = 'emails.' . $registro['ofe_base_datos'] . '.' . $registro['ofe_identificacion'] . '.notificarDocumento';
                                        } else {
                                            $rutaBlade = 'emails.etl_generica.Generica.notificarDocumento';  
                                        }
                                    }
                                    
                                    Mail::to($destinatario)->send(new notificarDocumento(
                                        $registro,
                                        $adjuntos,
                                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                        $dvOfe,
                                        $metodosBase->obtenerNombre($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente, 'ofe'),
                                        $emailRemitente,
                                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_asunto_correos,
                                        $metodosBase->obtieneDatoParametrico($parametricas['tipoDocumentoElectronico'], 'tde_id', $documento->getCabeceraDocumentosDaop->tde_id, 'tde_codigo'),
                                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_nombre_comercial,
                                        !empty($correosAutorespuesta) ? $correosAutorespuesta[0] : null,
                                        $rutaBlade,
                                        $awsSesConfigurationSet
                                    ));
                                }
                            }
                        }

                        $procesados[$cdo_id] = [
                            'consecutivo'           => ($consecutivo) ? ($consecutivo->rfa_prefijo ?? '') . $consecutivo->cdo_consecutivo : '',
                            'success'               => true,
                            'errors'                => [],
                            'contingencia'          => $contingencia,
                            'attachedDocument'      => $attachedDocument['attachedDocument'],
                            'representacionGrafica' => base64_encode($representacionGrafica),
                            'correosNotificados'    => implode(',', $destinatarios)
                        ];
                    } else {
                        $mensajeResultado = 'Documento procesado de manera exitosa, sin embargo no se encontraron correos a los cuales notificar';
                        $procesados[$cdo_id] = [
                            'consecutivo'           => ($consecutivo) ? ($consecutivo->rfa_prefijo ?? '') . $consecutivo->cdo_consecutivo : '',
                            'success'               => true,
                            'errors'                => [],
                            'contingencia'          => $contingencia,
                            'attachedDocument'      => $attachedDocument['attachedDocument'],
                            'representacionGrafica' => base64_encode($representacionGrafica),
                            'correosNotificados'    => ''
                        ];
                    }
                }
            } catch (\Exception $e) {
                $procesados[$cdo_id] = [
                    'consecutivo' => ($consecutivo) ? ($consecutivo->rfa_prefijo ?? '') . $consecutivo->cdo_consecutivo : '',
                    'success'     => false,
                    'errors'      => (isset($errorRg) && $errorRg != '') ? [$errorRg] : ['Se presentó un error en el proceso de notificación'],
                    'exceptions'  => [
                        'Notificación - Archivo: ' . $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                    ]
                ];
            }

            if(!empty($estadosNotificar)) {
                $nombreArchivoDisco = null;
                if($procesados[$cdo_id]['success']) {
                    $nombreArchivoDisco = $this->guardarArchivoEnDisco(
                        $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                        $documento->getCabeceraDocumentosDaop,
                        'emision',
                        'rg',
                        'pdf',
                        $procesados[$cdo_id]['representacionGrafica']
                    );

                    // Se valida si el OFE esta incluido dentro de los NITs de la integración CBO y el adquirente es la Agencia de Aduanas de DHL Express,
                    // se debe inicial el proceso para el registro del documento en openETL en el proceso de RECEPCION
                    if(
                        !$reenviar &&
                        in_array($documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion, $opencomexCboNitsIntegracion) &&
                        $documento->getCabeceraDocumentosDaop->getConfiguracionAdquirente->adq_identificacion == $this->nitAgenciaAduanasDhl
                    ) {
                        $documentosDhlExpressCbo[] = $cdo_id;
                    }
                }

                $this->actualizarEstadoNotificacion(
                    $cdo_id,
                    $estadosNotificar[$cdo_id]['est_id'],
                    ($procesados[$cdo_id]['success']) ? 'EXITOSO' : 'FALLIDO',
                    ($procesados[$cdo_id]['success']) ? $mensajeResultado : (!empty($procesados[$cdo_id]['errors']) ? implode(' // ', $procesados[$cdo_id]['errors']) : null),
                    ($procesados[$cdo_id]['success']) ? $procesados[$cdo_id]['correosNotificados'] : null,
                    date('Y-m-d H:i:s'),
                    number_format((microtime(true) - $estadosNotificar[$cdo_id]['inicio']), 3, '.', ''),
                    'FINALIZADO',
                    (!$procesados[$cdo_id]['success'] && array_key_exists('exceptions', $procesados[$cdo_id]) && $procesados[$cdo_id]['exceptions'] != '') ? json_encode($procesados[$cdo_id]['exceptions']) : json_encode(['est_archivo' => $nombreArchivoDisco])
                );

                // Si el documento se notificó correctamente, pero el documento fue notificado en contingencia se crea el estado CONTINGENCIA
                if($procesados[$cdo_id]['success'] && array_key_exists('contingencia', $procesados[$cdo_id]) && $procesados[$cdo_id]['contingencia']) {
                    $this->creaNuevoEstadoDocumento(
                        $cdo_id,
                        'CONTINGENCIA',
                        null,
                        $user->usu_id,
                        ['contingencia' => true]
                    );
                }
            }
        }

        if(!empty($documentosDhlExpressCbo))
            $this->procesoEspecialDhlExpressCbo($documentosDhlExpressCbo);

        if($reenviar) {
            if(!empty($documentosReenvioAgendarAD))
                $this->reenvioAgendarAD($documentosReenvioAgendarAD);

            return $procesados;
        }
    }

    private function reenvioAgendarAD($documentosAgendarAD) {
        if(!empty($documentosAgendarAD)) {
            $grupos = array_chunk($documentosAgendarAD, auth()->user()->getBaseDatos->bdd_cantidad_procesamiento_notificacion);

            foreach ($grupos as $grupo) {
                $agendamientoAD = DoTrait::crearNuevoAgendamiento('EUBLATTACHEDDOCUMENT', auth()->user()->usu_id, auth()->user()->getBaseDatos->bdd_id, count($grupo), null);

                foreach($grupo as $documento)
                    $this->creaNuevoEstadoDocumento($documento['cdo_id'], 'UBLATTACHEDDOCUMENT', $agendamientoAD->age_id, auth()->user()->usu_id, $documento['informacion_adicional']);
            }
        }
    }

    /**
     * Actualiza el estado de un documento procesado por parser y firma, del mismo modo actualiza el CUFE, el QR y el SignatureValue en Cabecera.
     *
     * @param int $cdo_id ID del documento procesado
     * @param int $est_id ID del estado que se actualizará
     * @param string $estadoResultado Resultado del procesamiento estado
     * @param string $mensajeResultado Mensaje del resultado de procesamiento del estado
     * @param string $correosNotificados Correos a los cuales se envió la notificación del documento
     * @param string $fechaHoraFinProceso Fecha y hora de finalización del procesamiento
     * @param float $tiempoProcesamiento Tiempo total del procesamiento en segundos
     * @param string $estadoEjecucion Estado de la ejecución del procesamiento del estado
     * @param string $estadoInformacionAdicional Información adicional del estado
     * @return void
     */
    public function actualizarEstadoNotificacion($cdo_id, $est_id, $estadoResultado, $mensajeResultado, $correosNotificados, $fechaHoraFinProceso, $tiempoProcesamiento, $estadoEjecucion, $estadoInformacionAdicional = null) {
        $estado = EtlEstadosDocumentoDaop::select(['est_id', 'est_informacion_adicional'])
            ->where('est_id', $est_id)
            ->first();

        // Si el estado tenia información registrada en información adicional se debe conservar
        $estadoInformacionAdicional = $estadoInformacionAdicional != null ? json_decode($estadoInformacionAdicional, true) : [];
        if($estado->est_informacion_adicional != '') {
            $informacionAdicionalExiste = json_decode($estado->est_informacion_adicional, true);
            $estadoInformacionAdicional = array_merge($informacionAdicionalExiste, $estadoInformacionAdicional);
        }

        $estado->update([
                'est_resultado'             => $estadoResultado,
                'est_mensaje_resultado'     => $mensajeResultado,
                'est_correos'               => $correosNotificados,
                'est_fin_proceso'           => $fechaHoraFinProceso,
                'est_tiempo_procesamiento'  => $tiempoProcesamiento,
                'est_ejecucion'             => $estadoEjecucion,
                'est_informacion_adicional' => empty($estadoInformacionAdicional) ? null : json_encode($estadoInformacionAdicional)
            ]);
    }

    /**
     * Procesa el reenvío de correos de documentos
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function reenviarEmailDocumentos(Request $request) {
        $user                = auth()->user();
        $cdoIds              = array_unique(explode(',', $request->cdo_ids));
        $correos_adicionales = ($request->filled('correos_adicionales') && $request->correos_adicionales != "null") ? array_unique(explode(',', $request->correos_adicionales)) : [];
        $reenviar            = $this->NotificarDocumentos($cdoIds, $user, true, [], $correos_adicionales);

        // Procesa la información de los documentos para los cuales se reenviaron los emails
        // Para retornar los registros que presentaron errores
        $errores = [];
        foreach($reenviar as $cdo_id => $resultado) {
            if(!$resultado['success']) { // Fallido
                $errores[] = ((array_key_exists('consecutivo', $resultado) && $resultado['consecutivo'] != '') ? $resultado['consecutivo'] : '') . ' ' . implode(' // ', $resultado['errors']);
            }
        }

        if(empty($errores)) {
            return response()->json([
                'message' => 'Reenvio de correos realizado'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Reenvío de Correos con Errores',
                'errors'  => $errores
            ], 400);
        }
    }

    /**
     * Crea un nuevo estado para un documento electrónico
     *
     * @param int    $cdo_id ID del documento para el cual se crea el estado
     * @param string $estado Nombre del estado a crear
     * @param int    $age_id ID del agendamiento relacionado con el estado
     * @param int    $usu_id ID del usuario que crea el nuevo estado
     * @param array  $estadoInformacionAdicional Información adicional del estado
     * @return void
     */
    public function creaNuevoEstadoDocumento($cdo_id, $estado, $age_id, $usu_id, $estadoInformacionAdicional = null) {
        EtlEstadosDocumentoDaop::create([
            'cdo_id'                    => $cdo_id,
            'est_estado'                => $estado,
            'age_id'                    => $age_id,
            'age_usu_id'                => $usu_id,
            'est_informacion_adicional' => ($estadoInformacionAdicional != null && !empty($estadoInformacionAdicional)) ? json_encode($estadoInformacionAdicional) : null,
            'usuario_creacion'          => $usu_id,
            'estado'                    => 'ACTIVO'
        ]);
    }

    /**
     * Procesa las peticiones realizadas desde Amazon SES - Servicio SNS
     *
     * @param Request $request
     * @return void
     */
    public function snsProcesamiento(Request $request) {
        try {
            // Obtiene el mensaje
            $message = Message::fromRawPostData();

            // Instancia del validador del mensaje
            $validator = new MessageValidator();

            // dump($message);
            // dump($validator);
            // dd($validator->isValid($message));

            // Validate the message
            if ($validator->isValid($message)) {
                if ($message['Type'] == 'SubscriptionConfirmation') {
                    // Accede al índice SubscribeURL para la comprobación de la URL de suscripción
                    $contentSubscribeUrl = file_get_contents($message['SubscribeURL']);
                } elseif ($message['Type'] == 'Notification') {
                    $messageData = json_decode($message['Message']);

                    // Evento recibido
                    if(isset($messageData->notificationType))
                        $evento = strtolower($messageData->notificationType);
                    else
                        $evento = strtolower($messageData->eventType);

                    // Obtiene la bdd_id y cdo_id del subject del mensaje
                    $subject = $messageData->mail->commonHeaders->subject;
                    list($subject, $identificadorCorreo) = explode('#', $subject);
                    list($id_servidor, $bdd_id, $cdo_id) = explode('.', $identificadorCorreo);

                    // Usuario OPERATIVO de la base de datos
                    $user = User::where('usu_type', 'OPERATIVO')
                        ->where('bdd_id', $bdd_id)
                        ->first();
                    
                    if(!$user) {
                        //Si no hay usuario operativo, se debe buscar en la base de datos de rg
                        $user = User::where('usu_type', 'OPERATIVO')
                            ->where('bdd_id_rg', $bdd_id)
                            ->first();
                    }

                    $token = auth()->login($user);

                    $documento = EtlCabeceraDocumentoDaop::select('cdo_id')
                        ->where('cdo_id', $cdo_id)
                        ->first();

                    if($documento) {
                        
                        if(isset($messageData->$evento->timestamp))
                            $mailTimestamp = strtotime($messageData->$evento->timestamp);
                        else
                            $mailTimestamp = strtotime($messageData->mail->timestamp);

                        date_default_timezone_set('America/Bogota');
                        $fechaHora = date('Y-m-d H:i:s', $mailTimestamp);

                        foreach($messageData->mail->destination as $email) {

                            $existeEstado = EtlEventoNotificacionDocumentoDaop::select(['evt_id'])
                                ->where('cdo_id', $cdo_id)
                                ->where('evt_evento', $evento)
                                ->where('evt_correos', $email)
                                ->first();

                            if(!$existeEstado) {
                                EtlEventoNotificacionDocumentoDaop::create([
                                    'cdo_id'             => $cdo_id,
                                    'evt_evento'         => $evento,
                                    'evt_correos'        => $email,
                                    'evt_amazonses_id'   => $messageData->mail->messageId,
                                    'evt_fecha_hora'     => $fechaHora,
                                    'evt_json'           => $message['Message'],
                                    'usuario_creacion'   => $user->usu_id,
                                    'estado'             => 'ACTIVO'
                                ]);
                            }
                        }
                    }
                }
            }

            return response()->json('ok', 200);
        } catch (\Exception $e) {
            config(['logging.channels.excepciones_aws_ses.path' => storage_path('logs/aws_ses/aws_ses_'.date('YmdHis').'.log')]);
            Log::channel('excepciones_aws_ses')->info([
                'proceso' => 'PROCESAMIENTO NOTIFICACION AWS SES',
                'request' => isset($request) ? $request->all() : '',
                'error'   => $e->getMessage(),
                'traza'   => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
            ]);

            return response()->json('ok', 400);
        }
    }

    /**
     * Realiza el proceso especial CBO de DHL Express.
     *
     * @param array $documentosDhlExpressCbo Array con los ID de los documentos electrónicos a procesar
     * @return void
     */
    private function procesoEspecialDhlExpressCbo(array $documentosDhlExpressCbo) {
        foreach($documentosDhlExpressCbo as $cdo_id) {
            $estRecepcion = EtlEstadosDocumentoDaop::create([
                'cdo_id'                    => $cdo_id,
                'est_estado'                => 'REGISTRORECEPCION',
                'usuario_creacion'          => auth()->user()->usu_id,
                'estado'                    => 'ACTIVO'
            ]);

            try {
                $recepcion = $this->registrarDocumentoRecepcion($cdo_id);
                if(!empty($recepcion) && array_key_exists('status', $recepcion)) {
                    if($recepcion['status'] != '200' && $recepcion['status'] != '201') {
                        $estRecepcion->update([
                            'est_resultado'             => 'FALLIDO',
                            'est_ejecucion'             => 'FINALIZADO',
                            'est_mensaje_resultado'     => array_key_exists('message', $recepcion['respuesta']) ? $recepcion['respuesta']['message'] : 'Error al enviar el documento a recepción',
                            'est_informacion_adicional' => array_key_exists('errors', $recepcion['respuesta']) ? json_encode(['errores_recepcion' => $recepcion['respuesta']['errors']]) : json_encode(['errores_recepcion' => $recepcion['respuesta']])
                        ]);
                    } else {
                        $estRecepcion->update([
                            'est_resultado'             => 'EXITOSO',
                            'est_ejecucion'             => 'FINALIZADO',
                            'est_mensaje_resultado'     => $recepcion['respuesta']['message'] . ' - Lotes Procesamiento: ' . implode(', ', $recepcion['respuesta']['lotes_procesamiento'])
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $estRecepcion->update([
                    'est_resultado'             => 'FALLIDO',
                    'est_ejecucion'             => 'FINALIZADO',
                    'est_mensaje_resultado'     => 'Error al enviar el documento a recepción',
                    'est_informacion_adicional' => json_encode(['traza' => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()])
                ]);
            }
        }
    }

    /**
     * Registra un documento electrónico en el proceso de Recepción.
     *
     * @param int $cdo_id ID del documento electrónico a registrar
     * @return array $respuesta Respuesta obtenida del proceso de registro del documento en recepción
     */
    private function registrarDocumentoRecepcion($cdo_id) {
        $documento = EtlCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'adq_id', 'rfa_prefijo', 'cdo_consecutivo', 'fecha_creacion'])
            ->where('cdo_id', $cdo_id)
            ->with([
                'getConfiguracionAdquirente:adq_id,adq_identificacion',
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                'getNotificacionDocumento:cdo_id,est_informacion_adicional'
            ])
            ->first();

        $respuesta = [];
        if($documento) {
            $strPrefijoConsecutivo = $documento->rfa_prefijo . $documento->cdo_consecutivo;
            $informacionAdicional  = !empty($documento->getNotificacionDocumento->est_informacion_adicional) ? json_decode($documento->getNotificacionDocumento->est_informacion_adicional, true) : [];

            //Autenticandonos al servidor de recepcion
            $arrParamsToken = [
                'http_errors' => false,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Accept' => 'application/json'
                ],
                'form_params' => [
                    'email'    => config('variables_sistema_tenant.OPENETL_CBO_API_USER'),
                    'password' => config('variables_sistema_tenant.OPENETL_CBO_API_PASSWORD')
                ]
            ];

            $cliente = new Client();
            $solicitarToken = $cliente->request(
                'POST',
                config('variables_sistema_tenant.OPENETL_CBO_API') . '/api/login',
                $arrParamsToken
            );

            $respuesta = [
                'status'    => $solicitarToken->getStatusCode(),
                'respuesta' => json_decode((string)$solicitarToken->getBody()->getContents(), true)
            ];

            if ($respuesta['status'] == 200) {
                // Enviando documento a Recepcion
                $arrParams = [
                    'http_errors' => false,
                    'headers' => [
                        'Accept'        => 'application/json',
                        'Authorization' => 'Bearer ' . trim($respuesta['respuesta']['token'])
                    ],
                    'multipart' => [
                        [
                            'name'     => 'ofe_identificacion',
                            'contents' => $documento->getConfiguracionAdquirente->adq_identificacion
                        ],
                        [
                            'name'     => 'documentos',
                            'contents' => $strPrefijoConsecutivo
                        ],
                        [
                            'name'     => $strPrefijoConsecutivo . '_pdf',
                            'contents' => $this->obtenerArchivoDeDisco(
                                'emision',
                                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                $documento,
                                array_key_exists('est_archivo', $informacionAdicional) ? $informacionAdicional['est_archivo'] : null
                            ),
                            'filename' => $strPrefijoConsecutivo . '.pdf'
                        ],
                        [
                            'name'     => $strPrefijoConsecutivo . '_xml',
                            'contents' => $this->obtenerArchivoDeDisco(
                                'emision',
                                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                                $documento,
                                array_key_exists('est_xml', $informacionAdicional) ? $informacionAdicional['est_xml'] : null
                            ),
                            'filename' => $strPrefijoConsecutivo . '.xml'
                        ],
                    ]
                ];

                $cliente = new Client();
                $transmision = $cliente->request(
                    'POST',
                    config('variables_sistema_tenant.OPENETL_CBO_API') . '/api/recepcion/documentos/documentos-manuales',
                    $arrParams
                );

                $respuesta = [
                    'status'    => $transmision->getStatusCode(),
                    'respuesta' => json_decode((string)$transmision->getBody()->getContents(), true)
                ];
            }
        }

        return $respuesta;
    }
}
