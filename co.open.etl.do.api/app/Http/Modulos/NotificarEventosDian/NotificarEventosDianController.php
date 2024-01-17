<?php
namespace App\Http\Modulos\NotificarEventosDian;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use App\Http\Modulos\RgController;
use App\Mail\notificarEventosDian;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\NotificarDocumentos\MetodosBase;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Parametros\Municipios\ParametrosMunicipio;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumento;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\ProyectosEspeciales\DHLExpress\CorreosNotificacionBasware\PryCorreoNotificacionBasware;

class NotificarEventosDianController extends Controller {
    use FechaVigenciaValidations, DoTrait;
    
    /**
     * XML en procesamiento.
     *
     * @var string
     */
    public $xml = null;

    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Reglas de validación para el request del método configuración.
     *
     * @var array
     */
    private $reglasRequestConfiguracion = [
        'bdd_id'                        => 'required|numeric',
        'bdd_id_rg'                     => 'nullable|numeric',
        'ofe_identificacion'            => 'required|string|max:20',
        'ofe_recepcion_correo_estandar' => 'nullable|string|max:2|in:SI,NO',
        'logoNotificacionEventosDian'   => 'nullable|file'
    ];

    /**
     * Reglas de validación para el logo a utilizar en la notificación de eventos DIAN.
     *
     * @return array Array con las reglas de validación
     */
    private static function reglasValidacionLogo() {
        return [
            "logoNotificacionEventosDian" => [
                function ($attribute, $value, $fail) {
                    try {
                        list($width, $heigth) = getimagesize($value->getRealPath());
                    } catch (\Exception $e) {
                        $width = $width ?? null;
                        $heigth = $heigth ?? null;
                    }

                    if ($value->getType() != 'file' && empty($width)) {
                        $fail('El logo no es una imagen.');
                    }

                    if ($width > 200 || $width < 1){
                        $fail('El logo no cumple con el ancho correcto de la imagen.');
                    }

                    if ($heigth > 150 || $heigth < 1){
                        $fail('El logo no cumple con el alto correcto de la imagen.');
                    }

                    if($value->getClientOriginalExtension() != 'png') {
                        $fail('PNG es la extensión válida para el logo a utilizar en la notificación de eventos DIAN.');
                    }
                },
            ]
        ];
    }

    /**
     * Retorna el logo del OFE utilizado en la notificación de eventos DIAN.
     *
     * @param Request $request
     * @return Response
     */
    public function logoOfe(Request $request) {
        if($request->has('bdd_id_rg') && !empty($request->bdd_id_rg))
                $baseDatos = AuthBaseDatos::select(['bdd_nombre'])
                    ->find($request->bdd_id_rg);
        elseif($request->has('bdd_id') && !empty($request->bdd_id))
            $baseDatos = AuthBaseDatos::select(['bdd_nombre'])
                ->find($request->bdd_id);

        DoTrait::setFilesystemsInfo();
        $disk       = config('variables_sistema.ETL_LOGOS_STORAGE');
        $nombreLogo = 'logoevento' . $request->ofe_identificacion . '.png';
        $pathLogo   = (str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos->bdd_nombre)) . '/'. $request->ofe_identificacion . '/assets/' . $nombreLogo;
        if (Storage::disk($disk)->exists($pathLogo)) {
            $contenido = Storage::disk($disk)->get($pathLogo);
            $fileInfo  = finfo_open();
            $mime_type = finfo_buffer($fileInfo, $contenido, FILEINFO_MIME_TYPE);
            $data_uri  = "data:". $mime_type . ";base64," . base64_encode($contenido);
            
            return response()->json([
                'data' => $data_uri
            ], 200);
        }

        return response()->json([
            'data' => ''
        ], 200);
    }

    /**
     * Crea/actualiza carpetas y logo del OFE relacionados con la configuración de la notificación de eventos DIAN.
     *
     * @param Request $request
     * @return Response
     */
    public function configuracion(Request $request) {
        $validacion = Validator::make($request->all(), $this->reglasRequestConfiguracion);
        if($validacion->fails()) {
            return response()->json([
                'message' => 'Error en Microservicio DO',
                'errors'  => $validacion->errors()->all()
            ], 400);
        }

        if($request->has('ofe_recepcion_correo_estandar')) {
            if($request->has('bdd_id_rg') && !empty($request->bdd_id_rg))
                $baseDatos = AuthBaseDatos::select(['bdd_nombre'])
                    ->find($request->bdd_id_rg);
            elseif($request->has('bdd_id') && !empty($request->bdd_id))
                $baseDatos = AuthBaseDatos::select(['bdd_nombre'])
                    ->find($request->bdd_id);
            else
                return response()->json([
                    'message' => 'Error en Microservicio DO',
                    'errors'  => ['No se encontró el ID de base de datos del usuario autenticado para crear estructura de carpetas para notificación de eventos DIAN del OFE']
                ], 400);

            $bdNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos->bdd_nombre);
            DoTrait::setFilesystemsInfo();
            $disk       = config('variables_sistema.ETL_LOGOS_STORAGE');
            $nombreLogo = 'logoevento' . $request->ofe_identificacion . '.png';
            if($request->hasFile('logoNotificacionEventosDian')) {
                $validadorLogo = Validator::make(['logoNotificacionEventosDian' => $request->file('logoNotificacionEventosDian')], self::reglasValidacionLogo());
                
                if ($validadorLogo->fails()) {
                    return response()->json([
                        'message' => 'Error en Microservicio DO',
                        'errors'  => $validadorLogo->errors()->all()
                    ], 409);
                }
                Storage::disk($disk)->put($bdNombre . '/'. $request->ofe_identificacion . '/assets/' . $nombreLogo, file_get_contents($request->file('logoNotificacionEventosDian')->getRealPath()));
            } else {
                // Elimina el logo del disco
                Storage::disk($disk)->delete($bdNombre . '/'. $request->ofe_identificacion . '/assets/' . $nombreLogo);
            }
        }

        return response()->json(['message' => 'Configuración realizada'], 200);
    }

    /**
     * Obtiene el valor de un nodo dado su XPATH.
     *
     * @param SimpleXMLElement $xml XML-UBL en procesamiento
     * @param string $xpath xPath desde donde se debe obtener data
     * @return SimpleXMLElement|null
     */
    public function getValueByXpath($xml, string $xpath) {
        $obj = $xml->xpath($xpath);
        if ($obj) {
            return trim($obj[0]);
        }

        return null;
    }

    /**
     * Extrae información desde un XML-UBL
     *
     * @param integer $cdo_id ID del documento en procesamiento
     * @param string $xmlUblEvento XML-UBL en base64 del evento en procesamiento
     * @return array $dataXmlUblEvento Array conteniendo la información extraida del XML-UBL
     */
    private function extraerDataXmlUblEvento($cdo_id, $xmlUblEvento) {
        $xmlUblEvento = base64_decode($xmlUblEvento);
        $xmlUblEvento = str_replace('xmlns=', 'ns=', $xmlUblEvento);
        $xmlUblEvento = new \SimpleXMLElement($xmlUblEvento, LIBXML_NOERROR);

        $dataXmlUblEvento = [
            'evento' => 'Evento',
        ];

        $dataXmlUblEvento['documentoReferenciado']      = (string) $this->getValueByXpath($xmlUblEvento, '//ApplicationResponse/cac:DocumentResponse/cac:DocumentReference/cbc:ID');
        $dataXmlUblEvento['nitGeneradorEvento']         = (string) $this->getValueByXpath($xmlUblEvento, '//ApplicationResponse/cac:SenderParty/cac:PartyTaxScheme/cbc:CompanyID');
        $dataXmlUblEvento['nombreGeneradorEvento']      = (string) $this->getValueByXpath($xmlUblEvento, '//ApplicationResponse/cac:SenderParty/cac:PartyTaxScheme/cbc:RegistrationName');
        $dataXmlUblEvento['numeroDocumentoElectronico'] = (string) $this->getValueByXpath($xmlUblEvento, '//ApplicationResponse/cbc:ID');
        $dataXmlUblEvento['codigoTipoDocumento']        = (string) $this->getValueByXpath($xmlUblEvento, '//ApplicationResponse/cac:DocumentResponse/cac:Response/cbc:ResponseCode');
        $dataXmlUblEvento['nombreTipoDocumento']        = (string) $this->getValueByXpath($xmlUblEvento, '//ApplicationResponse/cac:DocumentResponse/cac:Response/cbc:Description');
        $dataXmlUblEvento['internoOpen']                = '#' . config('variables_sistema.ID_SERVIDOR') . '.' . (auth()->user()->getBaseDatos->bdd_id) . '.' . $cdo_id;

        return $dataXmlUblEvento;
    }

    /**
     * Obtiene los detinatarios para la notificación del evento DIAN
     *
     * @param string $proceso Proceso de openETL en ejecución (emision/recepcion)
     * @param RepCabeceraDocumentoDaop/EtlCabeceraDocumentoDaop $documento Colección conteniendo información de cabecera del documento
     * @param string $codigoEventoDian Codigo del evento DIAN que se está notificando
     * @return void
     */
    private function obtenerDestinatarios($proceso, $documento, $codigoEventoDian) {
        $destinatarios = [];

        if(
            $proceso == 'recepcion' && 
            $codigoEventoDian == '031' &&
            ( // DHL Express
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == '860502609' || $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == '830076778'
            )
        ) {
            $destinatarioBaswareProveedor = [];
            $destinatarioBaswareInterno = [];
            $destinatarioCorreosNotificacion = [];

            $correosBasware = PryCorreoNotificacionBasware::select(['cnb_correo_proveedor', 'cnb_correo_interno'])
                ->where('pro_identificacion', $documento->getConfiguracionProveedor->pro_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();

            if($correosBasware && (!empty($correosBasware->cnb_correo_proveedor) || !empty($correosBasware->cnb_correo_interno))) {
                if(!empty($correosBasware->cnb_correo_proveedor))
                    $destinatarioBaswareProveedor = explode(',', $correosBasware->cnb_correo_proveedor);
                if(!empty($correosBasware->cnb_correo_interno))
                    $destinatarioBaswareInterno = explode(',', $correosBasware->cnb_correo_interno);
            }
            
            //Si el proveedor cuenta con correo se notifica al pro_correo
            //Si no se notifica a cnb_correo_proveedor
            if(!empty($documento->getConfiguracionProveedor->pro_correo)) {
                $destinatarios[] = $documento->getConfiguracionProveedor->pro_correo;
            } else {
                $destinatarios = $destinatarioBaswareProveedor;
            }
            
            //Si el proveedor cuenta con correos de notificacion, se notifica al pro_correos_notificacion
            //Si no se notifica a cnb_correo_interno
            if (!empty($documento->getConfiguracionProveedor->pro_correos_notificacion)) {
                $destinatarioCorreosNotificacion = explode(',', $documento->getConfiguracionProveedor->pro_correos_notificacion);
                $destinatarios = array_merge($destinatarios, $destinatarioCorreosNotificacion);
            } else {
                $destinatarios = array_merge($destinatarios, $destinatarioBaswareInterno);
            }
        } else {
            if($proceso == 'emision') {
                $destinatarios = explode(',', $documento->getConfiguracionAdquirente->adq_correos_notificacion);
            } else if($proceso == 'radian') {
                $destinatarios = explode(',', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_correo);
            } else {
                $destinatariosNotificacion = explode(',', $documento->getConfiguracionProveedor->pro_correos_notificacion);
                $destinatariosProveedor    = explode(',', $documento->getConfiguracionProveedor->pro_correo);
                $destinatarios             = array_merge($destinatariosNotificacion, $destinatariosProveedor);
            }
        }

        $destinatarios = array_unique($destinatarios);
        $arrTmp = [];
        foreach ($destinatarios as $destinatario) {
            if($destinatario != '' && filter_var($destinatario, FILTER_VALIDATE_EMAIL) !== false) {
                $arrTmp[] = $destinatario;
            }
        }
        $destinatarios = $arrTmp;

        return $destinatarios;
    }

    /**
     * Obtiene la data necesaria para la notificación del evemto del documento.
     *
     * @param string $proceso Proceso de openETL en ejecución (emision/recepcion)
     * @param integer $cdo_id
     * @param string $nombreUblEstado
     * @param RepCabeceraDocumentoDaop/EtlCabeceraDocumentoDaop/RadianCabeceraDocumentoDaop $documento Colección conteniendo información de cabecera del documento
     * @param RepEstadoDocumentoDaop/EtlEstadosDocumentoDaop/RadianEstadoDocumentoDaop $ublEvento Colección conteniendo información del evento UBL en donde se creó el ApplicartionResponse enviado a la DIAN
     * @param string $xmlAttachedDocument AttahcedDocument del evento
     * @return void
     */
    public function crearDataNotificarEvento(string $proceso, int $cdo_id, string $nombreUblEstado, $documento, $ublEvento, string $xmlAttachedDocument) {
        $crearDataNotificarEvento = $this->extraerDataXmlUblEvento($cdo_id, $xmlAttachedDocument);

        $crearDataNotificarEvento['subject'] = $crearDataNotificarEvento['evento'] . ';' . $crearDataNotificarEvento['documentoReferenciado'] . ';' . $crearDataNotificarEvento['nitGeneradorEvento'] . ';' . $crearDataNotificarEvento['nombreGeneradorEvento'] . ';' .
            $crearDataNotificarEvento['numeroDocumentoElectronico'] . ';' . $crearDataNotificarEvento['codigoTipoDocumento'] . ';' . $crearDataNotificarEvento['internoOpen'];

        $crearDataNotificarEvento['fechaEmision'] = $documento->cdo_fecha . ' ' . $documento->cdo_hora;

        if($crearDataNotificarEvento['codigoTipoDocumento'] == '031') { // Reclamo
            $crearDataNotificarEvento['conceptoReclamo']        = $ublEvento->est_motivo_rechazo['concepto_rechazo'] . " - " . $ublEvento->est_motivo_rechazo['descripcion_rechazo'];
            $crearDataNotificarEvento['observacion']            = (isset($ublEvento->est_motivo_rechazo) && array_key_exists('motivo_rechazo', $ublEvento->est_motivo_rechazo)) ? $ublEvento->est_motivo_rechazo['motivo_rechazo'] : '' ;
            $crearDataNotificarEvento['fechaEvento']            = $documento->cdo_fecha_estado;
            $crearDataNotificarEvento['pronombreTipoDocumento'] = 'el';
        } elseif($crearDataNotificarEvento['codigoTipoDocumento'] == '034') { // Aceptación Tácita
            $crearDataNotificarEvento['conceptoReclamo']        = '';
            $crearDataNotificarEvento['observacion']            = '';
            $crearDataNotificarEvento['fechaEvento']            = $documento->cdo_fecha_estado;
            $crearDataNotificarEvento['pronombreTipoDocumento'] = 'la';
        } elseif($crearDataNotificarEvento['codigoTipoDocumento'] == '033') { // Aceptación Expresa
            $crearDataNotificarEvento['conceptoReclamo']        = '';
            $crearDataNotificarEvento['observacion']            = (isset($ublEvento->est_motivo_rechazo) && array_key_exists('observacion', $ublEvento->est_motivo_rechazo)) ? $ublEvento->est_motivo_rechazo['observacion'] : '' ;
            $crearDataNotificarEvento['fechaEvento']            = $documento->cdo_fecha_estado;
            $crearDataNotificarEvento['pronombreTipoDocumento'] = 'la';
        } elseif($crearDataNotificarEvento['codigoTipoDocumento'] == '030') { // Acuse de Recibo
            $crearDataNotificarEvento['conceptoReclamo']        = '';
            $crearDataNotificarEvento['observacion']            = (isset($ublEvento->est_motivo_rechazo) && array_key_exists('observacion', $ublEvento->est_motivo_rechazo)) ? $ublEvento->est_motivo_rechazo['observacion'] : '' ;
            $crearDataNotificarEvento['fechaEvento']            = $documento->cdo_fecha_acuse;
            $crearDataNotificarEvento['pronombreTipoDocumento'] = 'el';
        } elseif($crearDataNotificarEvento['codigoTipoDocumento'] == '032') { // Recibo del bien
            $crearDataNotificarEvento['conceptoReclamo']        = '';
            $crearDataNotificarEvento['observacion']            = (isset($ublEvento->est_motivo_rechazo) && array_key_exists('observacion', $ublEvento->est_motivo_rechazo)) ? $ublEvento->est_motivo_rechazo['observacion'] : '' ;
            $crearDataNotificarEvento['fechaEvento']            = $documento->cdo_fecha_recibo_bien;
            $crearDataNotificarEvento['pronombreTipoDocumento'] = 'el';
        }

        if(!empty(auth()->user()->bdd_id_rg))
            $baseDatos = AuthBaseDatos::select(['bdd_nombre'])
                ->find(auth()->user()->bdd_id_rg);
        else
            $baseDatos = AuthBaseDatos::select(['bdd_nombre'])
                ->find(auth()->user()->bdd_id);

        $bdNombre = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos->bdd_nombre);
        $crearDataNotificarEvento['basePath']  = base_path();
        $crearDataNotificarEvento['baseDatos'] = $bdNombre;

        // TODO: Se comenta esta lógia porque por ahora se va todo por estandar aun no esta contemplado lo personalizado
        // if ($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_correo_estandar === 'SI')
        //     $crearDataNotificarEvento['rutaBlade'] = 'eventos.etl_generica.notificarEventoDian';
        // else 
        //     $crearDataNotificarEvento['rutaBlade'] = 'eventos.' . $bdNombre . '.notificarEventoDian';
        $crearDataNotificarEvento['rutaBlade'] = 'eventos.etl_generica.notificarEventoDian';

        DoTrait::setFilesystemsInfo();
        $disk       = config('variables_sistema.ETL_LOGOS_STORAGE');
        $pathApp    = Storage::disk($disk)->getDriver()->getAdapter()->getPathPrefix();
        $nombreLogo = 'logoevento' . $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '.png';
        $pathLogo   = $bdNombre . '/'. $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '/assets/' . $nombreLogo;
        $strCiudad  = ($proceso == 'radian') ? $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_mun_descripcion : $documento->getConfiguracionObligadoFacturarElectronicamente->getParametroMunicipio->mun_descripcion;

        $crearDataNotificarEvento['ofeIdentificacion'] = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion;
        $crearDataNotificarEvento['ofeLogo']           = Storage::disk($disk)->exists($pathLogo) ? $pathApp . $pathLogo : '';
        $crearDataNotificarEvento['ofeCorreo']         = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;
        $crearDataNotificarEvento['ofeDireccion']      = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_direccion;
        $crearDataNotificarEvento['ofeTelefono']       = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_telefono;
        $crearDataNotificarEvento['ofeCiudad']         = $strCiudad;
        $crearDataNotificarEvento['destinatarios']     = $this->obtenerDestinatarios($proceso, $documento, $crearDataNotificarEvento['codigoTipoDocumento']);

        $crearDataNotificarEvento = array_merge($crearDataNotificarEvento, DoTrait::establecerCanalEnvio($documento));

        return $crearDataNotificarEvento;
    }

    /**
     * Procesa los documentos para los cuales se notificarán los eventos DIAN.
     *
     * @param string|EtlCabeceraDocumentoDaop|RepCabeceraDocumentoDaop $classCabecera Clase de cabecera de información para los documentos
     * @param string|EtlEstadosDocumentoDaop|RepEstadoDocumentoDaop $classEstado Clase de estados de los documentos
     * @param string $proceso Proceso de openETL en ejecución (emision/recepcion)
     * @param array $documentosNotificarEventoDian Array que contiene los IDs de los documentos que se deben notificar
     * @param array $estadosNotificarEventoDian Array que contiene información sobre el estado de notificación del evento DIAN
     * @param User $user Usuario relacionado con el procesamiento
     * @param bool $reenvio Indica cuando se trata de un reenvío de notificaciones
     * @return void|array Array que contiene información sobre el procesamiento de los documentos, ya sea por fallas/errores o por notificaciones efectivas
     */
    public function notificarEventosDian($classCabecera, $classEstado, string $proceso, array $documentosNotificarEventoDian, array $estadosNotificarEventoDian, User $user, bool $reenvio = false) {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Inicialización de clases a reutilizar
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

        $relaciones = [];
        if($proceso !== 'radian') {
            $relaciones = [
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_envio_notificacion_amazon_ses,ofe_conexion_smtp,ofe_correo,ofe_recepcion_conexion_erp,ofe_direccion,ofe_telefono,mun_id,sft_id,sft_id_ds,tdo_id,ofe_recepcion_correo_estandar',
                'getConfiguracionObligadoFacturarElectronicamente.getParametroMunicipio',
                'getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,sft_identificador,sft_pin,add_id',
                'getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,sft_identificador,sft_pin,add_id'
            ];
        }

        if($proceso == 'emision') {
            $select           = ['cdo_id', 'ofe_id', 'adq_id', 'rfa_prefijo', 'cdo_clasificacion', 'cdo_consecutivo', 'cdo_nombre_archivos', 'cdo_fecha', 'cdo_hora', 'cdo_fecha_recibo_bien', 'cdo_fecha_acuse', 'cdo_fecha_estado', 'fecha_creacion'];
            $relaciones[]     = 'getConfiguracionAdquirente:adq_id,adq_identificacion,adq_correos_notificacion';
            $relacionesEvento = [
                'getCabeceraDocumentosDaop:cdo_id,ofe_id,adq_id,tde_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_fecha,cdo_hora,cdo_nombre_archivos,fecha_creacion',
                'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,tdo_id,bdd_id_rg,ofe_archivo_certificado,ofe_password_certificado',
                'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getTributos:toa_id,ofe_id,tri_id',
                'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid,sft_nit_proveedor_tecnologico',
                'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid,sft_nit_proveedor_tecnologico',
                'getCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre',
                'getCabeceraDocumentosDaop.getConfiguracionAdquirente:adq_id,adq_identificacion,adq_razon_social,adq_nombre_comercial,adq_primer_apellido,adq_segundo_apellido,adq_primer_nombre,adq_otros_nombres,tdo_id,ref_id,adq_correos_notificacion',
                'getCabeceraDocumentosDaop.getConfiguracionAdquirente.getTributos:toa_id,adq_id,tri_id'
            ];
        } elseif($proceso == 'radian') {
            $select           = ['cdo_id', 'act_id', 'ofe_identificacion', 'ofe_nombre', 'ofe_informacion_adicional', 'adq_identificacion', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_nombre_archivos', 'cdo_fecha', 'cdo_hora', 'cdo_fecha_recibo_bien', 'cdo_fecha_acuse', 'cdo_fecha_estado', 'fecha_creacion'];
            $relaciones[]     = 'getRadActores:act_id,act_identificacion';
            $relacionesEvento = [
                'getRadCabeceraDocumentosDaop:cdo_id,act_id,ofe_identificacion,adq_identificacion,tde_id,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_fecha,cdo_hora,cdo_nombre_archivos,fecha_creacion',
                'getRadCabeceraDocumentosDaop.getRadActores:act_id,act_identificacion'
            ];
        } else {
            $select           = ['cdo_id', 'ofe_id', 'pro_id', 'rfa_prefijo', 'cdo_clasificacion', 'cdo_consecutivo', 'cdo_nombre_archivos', 'cdo_fecha', 'cdo_hora', 'cdo_fecha_recibo_bien', 'cdo_fecha_acuse', 'cdo_fecha_estado', 'fecha_creacion'];
            $relaciones[]     = 'getConfiguracionProveedor:pro_id,pro_identificacion,pro_correo,pro_correos_notificacion';
            $relacionesEvento = [
                'getRepCabeceraDocumentosDaop:cdo_id,ofe_id,pro_id,tde_id,cdo_clasificacion,rfa_prefijo,cdo_consecutivo,cdo_cufe,cdo_fecha,cdo_hora,cdo_nombre_archivos,fecha_creacion',
                'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,sft_id,sft_id_ds,ofe_razon_social,ofe_nombre_comercial,ofe_primer_apellido,ofe_segundo_apellido,ofe_primer_nombre,ofe_otros_nombres,tdo_id,ref_id,bdd_id_rg,ofe_archivo_certificado,ofe_password_certificado',
                'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getTributos:toa_id,ofe_id,tri_id',
                'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologico:sft_id,add_id,sft_testsetid,sft_nit_proveedor_tecnologico',
                'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getConfiguracionSoftwareProveedorTecnologicoDs:sft_id,add_id,sft_testsetid,sft_nit_proveedor_tecnologico',
                'getRepCabeceraDocumentosDaop.getConfiguracionObligadoFacturarElectronicamente.getBaseDatosRg:bdd_id,bdd_nombre',
                'getRepCabeceraDocumentosDaop.getConfiguracionProveedor:pro_id,pro_identificacion,pro_razon_social,pro_nombre_comercial,pro_primer_apellido,pro_segundo_apellido,pro_primer_nombre,pro_otros_nombres,tdo_id,ref_id,pro_correos_notificacion'
            ];
        }

        $procesados = [];
        foreach($documentosNotificarEventoDian as $cdo_id) {
            try {
                if(!$reenvio) {
                    // Marca el inicio de procesamiento para el estado correspondiente
                    $classEstado::find($estadosNotificarEventoDian[$cdo_id]['est_id'])
                        ->update([
                            'est_ejecucion'      => 'ENPROCESO',
                            'est_inicio_proceso' => date('Y-m-d H:i:s')
                        ]);

                    // Actualiza el microtime de inicio de procesamiento del documento
                    $estadosNotificarEventoDian[$cdo_id]['inicio'] = microtime(true);
                } else {
                    if(array_key_exists('particion', $estadosNotificarEventoDian[$cdo_id]) && !empty($estadosNotificarEventoDian[$cdo_id]['particion'])) {
                        $tablaCabecera = 'rep_cabecera_documentos_' . $estadosNotificarEventoDian[$cdo_id]['particion'];
                        $tablaEstados  = 'rep_estados_documentos_' . $estadosNotificarEventoDian[$cdo_id]['particion'];
                    } else {
                        if($proceso == 'radian') {
                            $tablaCabecera = 'rad_cabecera_documentos_daop';
                            $tablaEstados  = 'rad_estados_documentos_daop';
                        } else {
                            $tablaCabecera = 'rep_cabecera_documentos_daop';
                            $tablaEstados  = 'rep_estados_documentos_daop';
                        }
                    }
                }

                if(!$reenvio) {
                    $documento = $classCabecera::select($select)
                        ->where('cdo_id', $cdo_id)
                        ->with($relaciones)
                        ->first();
                } else {
                    $documento = $classCabecera->setTable($tablaCabecera)
                        ->select($select)
                        ->where('cdo_id', $cdo_id)
                        ->with($relaciones)
                        ->first();
                }

                if ($proceso == 'radian') {
                    $ofeInfomracionAdicional = json_decode($documento->ofe_informacion_adicional);
                    $documento->setAttribute('getConfiguracionObligadoFacturarElectronicamente', $ofeInfomracionAdicional);
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion  = $documento->ofe_identificacion;
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_nombre          = $documento->ofe_nombre;
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_correo          = $ofeInfomracionAdicional->correo;
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_direccion       = $ofeInfomracionAdicional->direccion;
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_telefono        = $ofeInfomracionAdicional->telefono;
                    $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_mun_descripcion = $ofeInfomracionAdicional->mun_descripcion;
                }

                $nombreUblEstado = str_replace('NOT', 'UBL', $estadosNotificarEventoDian[$cdo_id]['est_estado']);
                if(!$reenvio) {
                    $ublEvento = $classEstado::select(['est_id', 'cdo_id', 'est_motivo_rechazo', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', $nombreUblEstado)
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->with($relacionesEvento)
                        ->orderBy('est_id', 'desc')
                        ->first();
                } else {
                    $ublEvento = $classEstado->setTable($tablaEstados)
                        ->select(['est_id', 'cdo_id', 'est_motivo_rechazo', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', $nombreUblEstado)
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->with($relacionesEvento)
                        ->orderBy('est_id', 'desc')
                        ->first();
                }
                
                $nombreRptaDianEstado = str_replace('NOT', '', $estadosNotificarEventoDian[$cdo_id]['est_estado']);
                if(!$reenvio) {
                    $arDian = $classEstado::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', $nombreRptaDianEstado)
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->orderBy('est_id', 'desc')
                        ->first();
                } else {
                    $arDian = $classEstado->setTable($tablaEstados)
                        ->select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', $nombreRptaDianEstado)
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->orderBy('est_id', 'desc')
                        ->first();
                }

                $nombreAdEvento = str_replace('NOT', 'UBLAD', $estadosNotificarEventoDian[$cdo_id]['est_estado']);
                if(!$reenvio) {
                    $adEvento = $classEstado::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', $nombreAdEvento)
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->orderBy('est_id', 'desc')
                        ->first();
                } else {
                    $adEvento = $classEstado->setTable($tablaEstados)
                        ->select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                        ->where('cdo_id', $cdo_id)
                        ->where('est_estado', $nombreAdEvento)
                        ->where('est_resultado', 'EXITOSO')
                        ->where('est_ejecucion', 'FINALIZADO')
                        ->orderBy('est_id', 'desc')
                        ->first();
                }

                //Para el cliente DHL Express debe enviarse el PDF del documento que se rechaza
                $pdfRechazado = false;
                if (
                    (
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == '860502609' ||
                        $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion == '830076778' 
                    ) &&
                    $estadosNotificarEventoDian[$cdo_id]['est_estado'] == 'NOTRECHAZO'
                ) {
                    $pdfRechazado = true;
                    if(!$reenvio) {
                        $rdiEvento = $classEstado::select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'RDI')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->orderBy('est_id', 'desc')
                            ->first();
                    } else {
                        $rdiEvento = $classEstado->setTable($tablaEstados)
                            ->select(['est_id', 'cdo_id', 'est_informacion_adicional'])
                            ->where('cdo_id', $cdo_id)
                            ->where('est_estado', 'RDI')
                            ->where('est_resultado', 'EXITOSO')
                            ->where('est_ejecucion', 'FINALIZADO')
                            ->orderBy('est_id', 'desc')
                            ->first();
                    }
                }
                
                if(!$documento || !$ublEvento || !$arDian) {
                    $procesados[$cdo_id] = [
                        'consecutivo' => ($documento) ? ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo : '',
                        'success'     => false,
                        'errors'      => [
                            'No se encontró la información del documento o del evento [' . $nombreUblEstado . '] o ApplicationResponse [' . $nombreRptaDianEstado . ']'
                        ]
                    ];
                    continue;
                }

                if(!$reenvio) {
                    if($documento->cdo_clasificacion != 'DS' && $documento->cdo_clasificacion != 'DS_NC') {
                        if(empty($documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico)) {
                            $procesados[$cdo_id] = [
                                'consecutivo' => ($documento) ? ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo : '',
                                'success'     => false,
                                'errors'      => [
                                    'El Software de Proveedor Tecnológico para Documento Electrónico no se encuentra parametrizado.'
                                ]
                            ];
                            continue;
                        }
            
                        // Obtiene el parametro de ambiente destino del documento
                        $addCodigo = $metodosBase->obtieneDatoParametrico(
                            $parametricas['ambienteDestino'],
                            'add_id',
                            $documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id,
                            'add_codigo'
                        );
                    } else {
                        if(empty($documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologicoDs)) {
                            $procesados[$cdo_id] = [
                                'consecutivo' => ($documento) ? ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo : '',
                                'success'     => false,
                                'errors'      => [
                                    'El Software de Proveedor Tecnológico para Documento Soporte no se encuentra parametrizado.'
                                ]
                            ];
                            continue;
                        }
            
                        // Obtiene el parametro de ambiente destino del documento
                        $addCodigo = $metodosBase->obtieneDatoParametrico(
                            $parametricas['ambienteDestino'],
                            'add_id',
                            $documento->getConfiguracionObligadoFacturarElectronicamente->getConfiguracionSoftwareProveedorTecnologico->add_id,
                            'add_codigo'
                        );
                    }

                    if(!isset($addCodigo) || (isset($addCodigo) && $addCodigo == '')) {
                        $procesados[$cdo_id] = [
                            'consecutivo' => ($documento) ? ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo : '',
                            'success'     => false,
                            'errors'      => [
                                'No se encontró el código del ambiente de destino del documento'
                            ]
                        ];
                        continue;
                    }
                }

                $informacionAdicionalAd = !empty($adEvento->est_informacion_adicional) ? json_decode($adEvento->est_informacion_adicional, true) : [];
                $xmlAttachedDocument  = $this->obtenerArchivoDeDisco(
                    $proceso,
                    ($proceso == 'radian') ? $documento->getRadActores->act_identificacion : $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                    $documento,
                    array_key_exists('est_xml', $informacionAdicionalAd) ? $informacionAdicionalAd['est_xml'] : null
                );
                $attachedDocument['attachedDocument'] = $this->eliminarCaracteresBOM($xmlAttachedDocument);

                $informacionAdicionalAr = !empty($ublEvento->est_informacion_adicional) ? json_decode($ublEvento->est_informacion_adicional, true) : [];
                $applicationResponse = $this->obtenerArchivoDeDisco(
                    $proceso,
                    ($proceso == 'radian') ? $documento->getRadActores->act_identificacion : $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                    $documento,
                    array_key_exists('est_xml', $informacionAdicionalAr) ? $informacionAdicionalAr['est_xml'] : null
                );
                $applicationResponse = $this->eliminarCaracteresBOM($applicationResponse);

                $dataNotificarEvento  = $this->crearDataNotificarEvento($proceso, $cdo_id, $nombreUblEstado, $documento, $ublEvento, base64_encode($applicationResponse));

                switch($estadosNotificarEventoDian[$cdo_id]['est_estado']) {
                    case 'NOTACUSERECIBO':
                        $sufijoNombre     = 'acuse';
                        $prefijoArchivoRg = 'rgAcuseRecibo';
                        break;
                    case 'NOTRECIBOBIEN':
                        $sufijoNombre     = 'recibo';
                        $prefijoArchivoRg = 'rgReciboBien';
                        break;
                    case 'NOTACEPTACION':
                        $sufijoNombre     = 'aceptacion';
                        $prefijoArchivoRg = 'rgAceptacion';
                        break;
                    case 'NOTRECHAZO':
                        $sufijoNombre     = 'rechazo';
                        $prefijoArchivoRg = 'rgReclamo';
                        break;
                    case 'NOTACEPTACIONT':
                        $sufijoNombre     = 'aceptaciont';
                        $prefijoArchivoRg = 'rgAceptacionT';
                        break;
                }

                $mensajeError = '';
                if(!$reenvio) {
                    $rgController = new RgController();
                    $getRepresentacionGrafica = $rgController->generarRgEventoDian(
                        $dataNotificarEvento['ofeIdentificacion'], 
                        $dataNotificarEvento['baseDatos'], 
                        $applicationResponse,
                        $estadosNotificarEventoDian[$cdo_id]['est_estado'],
                        !empty($ublEvento->est_motivo_rechazo) ? $ublEvento->est_motivo_rechazo : []
                    );

                    if(is_array($getRepresentacionGrafica) && !$getRepresentacionGrafica['error']) 
                        $rgNotificarEvento = $getRepresentacionGrafica['respuesta'];
                    else {
                        $mensajeError = $getRepresentacionGrafica['respuesta'];
                        throw new \Exception($getRepresentacionGrafica['respuesta']);
                    }

                    $nombreArchivoRgEvento = $this->guardarArchivoEnDisco(
                        $dataNotificarEvento['ofeIdentificacion'],
                        $documento,
                        $proceso,
                        $prefijoArchivoRg,
                        'pdf',
                        base64_encode($rgNotificarEvento)
                    );
                }

                // Nombre archivo representacion grafica evento
                $nombrePDF      = 'pdf_' . $sufijoNombre;
                $nombreArchivos = $documento->cdo_nombre_archivos != '' ? json_decode($documento->cdo_nombre_archivos) : json_decode(json_encode([]));
                if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->$nombrePDF)) {
                    $nombreArchivoPdf = $nombreArchivos->$nombrePDF;
                } else {
                    $nombreArchivoPdf = $sufijoNombre . '_' . $documento->cdo_clasificacion . ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo . '.pdf';
                }

                // Nombre archivo representacion grafica documento
                if ($pdfRechazado) {
                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->pdf)) {
                        $nombrePdfDocumento = $nombreArchivos->pdf;
                    } else {
                        $nombrePdfDocumento = $documento->cdo_clasificacion . ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo . '.pdf';
                    }
                }

                //Creando Directorio temporal para el documento
                //Se crea el directorio id bBase de datos ~ id OFE ~ modulo ~ id Documento
                $identificador = ($proceso == 'radian') ? $documento->getRadActores->act_id : $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_id;
                $directorioDocumento = $user->bdd_id . "~" . 
                    $identificador . 
                    "~" . $proceso . "~" . 
                    $documento->cdo_id;
                Storage::makeDirectory($directorioDocumento, 0755);

                if(!$reenvio) {
                    // Obtiene el consecutivo del AttachedDocument del XML-UBL generado
                    $oDomtree = new \DOMDocument();
                    $oDomtree->xmlStandalone = false;
                    $oDomtree->loadXML($attachedDocument['attachedDocument']);
                    $consecutivoAD = $oDomtree->getElementsByTagName('ID')->item(0)->nodeValue;

                    $indiceNombreAD  = 'attached_' . $sufijoNombre;
                    $nombreArchivoAD = 'ad' .str_pad($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion, 10, '0', STR_PAD_LEFT) .
                        config('variables_sistema.CODIGO_PT') .
                        date('y') .
                        DoTrait::DecToHex($consecutivoAD) . '.xml';

                    $indiceNombreZIP = 'zip_' . $sufijoNombre;
                    $consecutivoZIP   = DoTrait::obtenerConsecutivoArchivo($documento->ofe_id, auth()->user()->usu_id, 'z');
                    $nombreArchivoZIP = 'z' .str_pad($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion, 10, '0', STR_PAD_LEFT) .
                        config('variables_sistema.CODIGO_PT') .
                        date('y') .
                        DoTrait::DecToHex($consecutivoZIP) . '.zip';
                } else {
                    $nombreAD = 'attached_' . $sufijoNombre;
                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->$nombreAD)) {
                        $nombreArchivoAD = $nombreArchivos->$nombreAD;
                    } else {
                        $nombreArchivoAD = $sufijoNombre . '_' . $documento->cdo_clasificacion . ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo . '.pdf';
                    }

                    $nombreZIP = 'zip_' . $sufijoNombre;
                    if($documento->cdo_nombre_archivos != '' && isset($nombreArchivos->$nombreZIP)) {
                        $nombreArchivoZIP = $nombreArchivos->$nombreZIP;
                    } else {
                        $nombreArchivoZIP = $sufijoNombre . '_' . $documento->cdo_clasificacion . ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo . '.pdf';
                    }
                }

                $rutaArchivoZip = storage_path() . '/etl/descargas/' . $directorioDocumento . $nombreArchivoZIP;
                $oZip = new \ZipArchive();
                $oZip->open($rutaArchivoZip, \ZipArchive::OVERWRITE | \ZipArchive::CREATE);

                if($reenvio) {
                    $rgNotificarEvento = base64_decode($estadosNotificarEventoDian[$cdo_id]['attached_document_pdf']);
                }
                
                $oZip->addFromString($nombreArchivoAD, $attachedDocument['attachedDocument']);
                $oZip->addFromString($nombreArchivoPdf, $rgNotificarEvento);

                if ($pdfRechazado) {
                    $rdiInformacionAdicional = json_decode($rdiEvento->est_informacion_adicional, true);
                    $oZip->addFromString($nombrePdfDocumento, $this->obtenerArchivoDeDisco(
                            $proceso, 
                            ($proceso == 'radian') ? $documento->getRadActores->act_identificacion : $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion, 
                            $documento, 
                            array_key_exists('est_archivo', $rdiInformacionAdicional) ? $rdiInformacionAdicional['est_archivo'] : null
                        )
                    );
                }

                $oZip->close();

                $adjuntos['zip'] = [
                    'archivo' => file_get_contents($rutaArchivoZip),
                    'nombre'  => $nombreArchivoZIP,
                    'mime'    => 'application/zip'
                ];
                @unlink($rutaArchivoZip);
                Storage::deleteDirectory($directorioDocumento);
                
                if(!empty($dataNotificarEvento['destinatarios'])) {
                    foreach ($dataNotificarEvento['destinatarios'] as $destinatario) {
                        Mail::to($destinatario)
                            ->send(new notificarEventosDian(
                                $dataNotificarEvento,
                                $adjuntos
                            ));
                    }

                    $procesados[$cdo_id] = [
                        'mensajeResultado'      => 'Evento notificado de manera exitosa',
                        'consecutivo'           => ($documento) ? ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo : '',
                        'success'               => true,
                        'errors'                => [],
                        'representacionGrafica' => !$reenvio ? $nombreArchivoRgEvento : '',
                        'correosNotificados'    => implode(',', $dataNotificarEvento['destinatarios']),
                        'nombreArchivosAD'      => ''
                    ];

                    if(!$reenvio) {
                        $procesados[$cdo_id]['nombreArchivosAD'] = [
                            $indiceNombreAD  => $nombreArchivoAD,
                            $indiceNombreZIP => $nombreArchivoZIP
                        ];
                    }
                } else {
                    $procesados[$cdo_id] = [
                        'mensajeResultado'      => 'Notificación de evento procesada de manera exitosa, sin embargo no se encontraron correos a los cuales notificar',
                        'consecutivo'           => ($documento) ? ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo : '',
                        'success'               => true,
                        'errors'                => [],
                        'representacionGrafica' => !$reenvio ? $nombreArchivoRgEvento : '',
                        'correosNotificados'    => '',
                        'nombreArchivosAD'      => ''
                    ];

                    if(!$reenvio) {
                        $procesados[$cdo_id]['nombreArchivosAD'] = [
                            $indiceNombreAD  => $nombreArchivoAD,
                            $indiceNombreZIP => $nombreArchivoZIP
                        ];
                    }
                }
            } catch (\Exception $e) {
                $procesados[$cdo_id] = [
                    'mensajeResultado' => 'Error en el proceso de notificación del evento: '. $e->getMessage(),
                    'consecutivo'      => ($documento) ? ($documento->rfa_prefijo ?? '') . $documento->cdo_consecutivo : '',
                    'success'          => false,
                    'errors'           => !empty($mensajeError) ? [$mensajeError] : ['Se presentó un error en el proceso de notificación'],
                    'exceptions'       => [
                        'Notificación Evento DIAN - Archivo: ' . $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                    ]
                ];
            }
        }

        if(!$reenvio) {
            foreach($documentosNotificarEventoDian as $cdo_id) {
                if(!empty($estadosNotificarEventoDian)) {
                    $this->actualizarEstadoNotificacionEventoDian(
                        $classCabecera,
                        $classEstado,
                        $cdo_id,
                        $estadosNotificarEventoDian[$cdo_id]['est_id'],
                        ($procesados[$cdo_id]['success']) ? 'EXITOSO' : 'FALLIDO',
                        ($procesados[$cdo_id]['success']) ? $procesados[$cdo_id]['mensajeResultado'] : (!empty($procesados[$cdo_id]['errors']) ? implode(' // ', $procesados[$cdo_id]['errors']) : null),
                        ($procesados[$cdo_id]['success']) ? $procesados[$cdo_id]['representacionGrafica'] : null,
                        ($procesados[$cdo_id]['success']) ? $procesados[$cdo_id]['correosNotificados'] : null,
                        date('Y-m-d H:i:s'),
                        number_format((microtime(true) - $estadosNotificarEventoDian[$cdo_id]['inicio']), 3, '.', ''),
                        'FINALIZADO',
                        (!$procesados[$cdo_id]['success'] && array_key_exists('exceptions', $procesados[$cdo_id]) && $procesados[$cdo_id]['exceptions'] != '') ? json_encode($procesados[$cdo_id]['exceptions']) : null,
                        ($procesados[$cdo_id]['success']) ? (array_key_exists('nombreArchivosAD', $procesados[$cdo_id]) ? $procesados[$cdo_id]['nombreArchivosAD'] : []) : []
                    );
                }
            }
        } else {
            return $procesados;
        }
    }

    /**
     * Actualiza el estado de notificación de un evento DIAN.
     *
     * @param string $classCabecera Clase de cabecera de información para los documentos
     * @param string $classEstado Clase de estados de los documentos
     * @param int $cdo_id ID del documento que se actualizará
     * @param int $est_id ID del estado que se actualizará
     * @param string $estadoResultado Resultado del procesamiento estado
     * @param string $mensajeResultado Mensaje del resultado de procesamiento del estado
     * @param string $attachedDocument AttachedDocument Generado
     * @param string $representacionGrafica Nombre del archivo de la Representación Gráfica del evento
     * @param string $correosNotificados Correos a los cuales se envió la notificación del documento
     * @param string $fechaHoraFinProceso Fecha y hora de finalización del procesamiento
     * @param string $tiempoProcesamiento Tiempo total del procesamiento en segundos
     * @param string $estadoEjecucion Estado de la ejecución del procesamiento del estado
     * @param string $estadoInformacionAdicional Información adicional del estado
     * @param array $nombreArchivosAD Array con los nombres de los archivos generados durante el proceso
     * @return void
     */
    public function actualizarEstadoNotificacionEventoDian($classCabecera, $classEstado, $cdo_id, $est_id, $estadoResultado, $mensajeResultado, $representacionGrafica, $correosNotificados, $fechaHoraFinProceso, $tiempoProcesamiento, $estadoEjecucion, $estadoInformacionAdicional = null, $nombreArchivosAD = []) {
        $estado = $classEstado::select(['est_id', 'est_informacion_adicional'])
            ->where('est_id', $est_id)
            ->first();

        // Si el estado tenia información registrada en información adicional se debe conservar
        $estadoInformacionAdicional = $estadoInformacionAdicional != null ? json_decode($estadoInformacionAdicional, true) : [];
        if($estado->est_informacion_adicional != '') {
            $informacionAdicionalExiste = json_decode($estado->est_informacion_adicional, true);
            $estadoInformacionAdicional = array_merge($informacionAdicionalExiste, $estadoInformacionAdicional);
        }

        if(!empty($representacionGrafica))
            $estadoInformacionAdicional = array_merge($estadoInformacionAdicional, ['est_archivo' => $representacionGrafica]);

        $estado->update([
                'est_resultado'             => $estadoResultado,
                'est_mensaje_resultado'     => $mensajeResultado,
                'est_correos'               => $correosNotificados,
                'est_fin_proceso'           => $fechaHoraFinProceso,
                'est_tiempo_procesamiento'  => $tiempoProcesamiento,
                'est_ejecucion'             => $estadoEjecucion,
                'est_informacion_adicional' => empty($estadoInformacionAdicional) ? null : json_encode($estadoInformacionAdicional)
            ]);

        $documento = $classCabecera::select(['cdo_id', 'cdo_nombre_archivos'])
            ->find($cdo_id);

        // Si el documento tenía registrada información de los nombres de archivos se debe conservar
        if($documento->cdo_nombre_archivos != '') {
            $nombreArchivosExisten = array_merge($nombreArchivosAD, json_decode($documento->cdo_nombre_archivos, true));
        } else{
            $nombreArchivosExisten = $nombreArchivosAD;
        }

        $documento->update([
                'cdo_nombre_archivos' => json_encode($nombreArchivosExisten)
            ]);
    }
}
