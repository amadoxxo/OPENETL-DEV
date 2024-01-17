<?php
namespace App\Traits;

use Ramsey\Uuid\Uuid;
use App\Traits\DiTrait;
use App\Http\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use openEtl\Main\Traits\PackageMainTrait;
use App\Http\Modulos\Recepcion\ParserXml\ParserXml;
use App\Http\Modulos\Sistema\EtlProcesamientoJson\EtlProcesamientoJson;
use App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedor;
use App\Http\Modulos\Recepcion\Documentos\RepDocumentosAnexosDaop\RepDocumentoAnexoDaop;
use App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManual;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuario;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

trait RecepcionTrait {

    use DiTrait, PackageMainTrait;

    /**
     * Extensiones válidas permitidas.
     *
     * @var array
     */
    protected $arrExtensionesValidas = ['xml', 'pdf', 'png', 'jpg', 'jpeg', 'tiff', 'doc', 'docx', 'xls', 'xlsx'];

    /**
     * Disco de trabajo creado en tiempo de ejecución en donde se almacenan finalmente los anexos.
     *
     * @var string
     */
    protected $discoAnexos = '';

    /**
     * Array de archivos extraidos de un zip que deben ser eliminados despues de ser procesados.
     *
     * @var array
     */
    protected $arrArchivosZipEliminar = [];

    /**
     * Array de errores que son generados en el procesamiento de cada carpeta, para almacenar un solo registro con todos los errores de una sola carpeta.
     *
     * @var array
     */
    protected $arrErroresPorDirectorio = [];

    /**
     * Instancia para el modelo de usuario que se autenticara en el comando.
     *
     * @var User
     */
    protected $user = null;

    /**
     * Indica cuando el proceso viene para asociar anexos con un documento.
     *
     * @var bool
     */
    protected $asociarDocumento = false;

    /**
     * Ubica el Logo del OFE en el proceso de recepción.
     * 
     * El logo en el proceso de recepción es el utilizado en la notificación de eventos y en las notificaciones de asociaciones de usuarios y proveedores a grupos de trabajo
     *
     * @param array $dataEmail Array con la información a enviar en el email
     * @param string $ofe_identificacion Identificación del OFE
     * @return void
     */
    private function logoNotificacionAsociacion(array &$dataEmail, string $ofe_identificacion): void {
        if(!empty(auth()->user()->bdd_id_rg)) {
            $baseDatos = auth()->user()->getBaseDatosRg->bdd_nombre;
        } else {
            $baseDatos = auth()->user()->getBaseDatos->bdd_nombre;
        }
        
        DiTrait::setFilesystemsInfo();
        $directorio = Storage::disk(config('variables_sistema.ETL_LOGOS_STORAGE'))->getDriver()->getAdapter()->getPathPrefix();
        $logoEvento = $directorio . $baseDatos . '/' . $ofe_identificacion . '/assets/' . 'logoevento' . $ofe_identificacion . '.png';

        if(File::exists($logoEvento))
            $dataEmail['ofe_logo'] = $logoEvento;
        else
            $dataEmail['ofe_logo'] = '';
    }

    /**
     * Realiza el envío de correo que notifica a los usuarios que pertenecen al grupo de trabajo que fue asociado el proveedor con información
     * sobre el nuevo documento electrónico creado desde el procesamiento de los correos electrónicos.
     *
     * @param RepCabeceraDocumentoDaop $documentoRecepcion Instancia del documento electrónico creado en recepción
     */
    public function notificarNuevoDocumentoCorreo(RepCabeceraDocumentoDaop $documentoRecepcion) {
        $dataEmail = [
            "ofe_razon_social"       => $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social,
            "pro_identificacion"     => $documentoRecepcion->getConfiguracionProveedor->pro_identificacion,
            "pro_razon_social"       => $documentoRecepcion->getConfiguracionProveedor->pro_razon_social,
            "tde_codigo_descripcion" => $documentoRecepcion->getTipoDocumentoElectronico->tde_codigo . ' ' . $documentoRecepcion->getTipoDocumentoElectronico->tde_descripcion,
            "rfa_prefijo"            => $documentoRecepcion->rfa_prefijo,
            "cdo_consecutivo"        => $documentoRecepcion->cdo_consecutivo,
            "cdo_fecha"              => $documentoRecepcion->cdo_fecha,
            "app_url"                => config('variables_sistema.APP_URL_WEB'),
            "remite"                 => config('variables_sistema.EMPRESA'),
            "direccion"              => config('variables_sistema.DIRECCION'),
            "ciudad"                 => config('variables_sistema.CIUDAD'),
            "telefono"               => config('variables_sistema.TELEFONO'),
            "web"                    => config('variables_sistema.WEB'),
            "email"                  => config('variables_sistema.EMAIL'),
            "facebook"               => config('variables_sistema.FACEBOOK'),
            "twitter"                => config('variables_sistema.TWITTER')
        ];

        $this->logoNotificacionAsociacion($dataEmail, $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion);
        
        $destinatarios = [];
        $idsGruposTrabajoProveedor = $documentoRecepcion->getConfiguracionProveedor->getProveedorGruposTrabajo
            ->pluck('gtr_id')
            ->values()
            ->toArray();

        ConfiguracionGrupoTrabajoUsuario::select('usu_id')
            ->whereIn('gtr_id', $idsGruposTrabajoProveedor)
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function ($usuarioAsociado) use (&$destinatarios) {
                $usuario = User::select(['usu_email'])
                    ->where('usu_id', $usuarioAsociado->usu_id)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if ($usuario) {
                    $destinatarios[] = $usuario->usu_email;
                }
            });

        foreach($documentoRecepcion->getConfiguracionProveedor->getProveedorGruposTrabajo as $grupoTrabajo) {
            if(!empty($grupoTrabajo->getGrupoTrabajo->gtr_correos_notificacion))
                $destinatarios = array_merge($destinatarios, explode(',', $grupoTrabajo->getGrupoTrabajo->gtr_correos_notificacion));
        }

        $destinatarios = array_unique($destinatarios);
        DiTrait::setMailInfo();
        foreach ($destinatarios as $correo) {
            Mail::send(
                'emails.recepcion.documentoElectronicoCreadoDesdeCorreo',
                $dataEmail,
                function ($message) use ($documentoRecepcion, $correo){
                    $message->from(
                        $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_correo,
                        $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social . ' (' . $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . ')'
                    );
                    $message->sender($documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_correo, $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social);
                    $message->subject('Documento electrónico ' . $documentoRecepcion->rfa_prefijo . $documentoRecepcion->cdo_consecutivo . ' creado en openETL');
                    $message->to($correo);
                }
            );
        }
    }

    /**
     * Descomprime un archivo zip en una ruta expecífica.
     * 
     * Este método puede incluir llamadas recursivas si dentro de un zip se encuentra otro zip.
     * Los archivos son extraidos en la ruta base del directorio del correo.
     *
     * @param string $prefijoConsecutivo Prefijo y consecutivo del documento electrónico en procesamiento
     * @param string $subjectCorreo Subject del correo electrónico
     * @param string $zip Ruta del archivo zip a descomprimir
     * @param string $pathDirPrincipal Path al directorio principal de procesamiento
     * @param string $directorio Ruta del directorio en el cual se descomprimirá el zip
     * @param bool   $zipDescomprimidoTotal Variable para indicar que ya se descomprime el zip en su totalidad
     * @param array  $arrArchivosZipEliminar Contiene los archivos que se deben de eliminar
     * @param object $rutas Contiene las rutas del disco a utilizar
     * @param array  $arrArchivosDescartar Array de archivos que NO deben ser tenidos en cuenta para guardarse como anexos
     * @param array  $arrErroresPorDirectorio Array de errores que son generados en el procesamiento de cada carpeta, para almacenar un solo registro con todos los errores de una sola carpeta.
     * @return array
     */
    public function unzip(string $prefijoConsecutivo, string $subjectCorreo, string $zip, string $pathDirPrincipal, string $directorio, bool $zipDescomprimidoTotal, array $arrArchivosZipEliminar, $rutas, array $arrArchivosDescartar, array $arrErroresPorDirectorio) {
        $cont   = 1;
        $tmpZip = new \ZipArchive;
        $this->arrArchivosZipEliminar = $arrArchivosZipEliminar;
        $this->arrErroresPorDirectorio = $arrErroresPorDirectorio;
        if($tmpZip->open($zip) === true) {
            for ($i = 0; $i < $tmpZip->numFiles; ++$i) {
                $pathFile = $tmpZip->getNameIndex($i);
                $ext      = pathinfo($pathFile, PATHINFO_EXTENSION);
                $basename = pathinfo($pathFile, PATHINFO_BASENAME );
                if(!in_array($ext, $this->arrExtensionesValidas) && $ext != 'zip')
                    continue;

                $archivoCopiar = $pathDirPrincipal . '/' . $directorio . '/' . $basename;
                if(file_exists($archivoCopiar)) {
                    $archivoCopiar = $pathDirPrincipal . '/' . $directorio . '/' . str_replace('.' . $ext, ' Copia ' . $cont . '.' . $ext, $basename);
                    $cont++;
                }
                if(copy( 'zip://' . $zip . '#' . $pathFile, $archivoCopiar)) {
                    $this->arrArchivosZipEliminar[] = $archivoCopiar;
                } else {
                    $zipDescomprimidoTotal     = false;
                    $this->arrErroresPorDirectorio[] = [
                        'documento'           => $prefijoConsecutivo,
                        'prefijo'             => null,
                        'consecutivo'         => null,
                        'errors' => [
                            'No fue posible copiar archivo [' . $basename . '] extraido del zip [' . (str_replace($pathDirPrincipal . '/' . $directorio . '/', '', $zip)) . ']'
                        ],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'archivo'             => $subjectCorreo,
                        'traza'               => [
                            'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                        ]
                    ];
                }

                if($ext == 'zip') {
                    $arrArchivosDescartar[] = $basename;
                    $this->unzip($prefijoConsecutivo, $subjectCorreo, $archivoCopiar, $pathDirPrincipal, $directorio, $zipDescomprimidoTotal, $this->arrArchivosZipEliminar, $rutas, $arrArchivosDescartar, $this->arrErroresPorDirectorio);
                }
            }
            $tmpZip->close();
        } else {
            $this->arrErroresPorDirectorio[] = [
                'documento'           => $prefijoConsecutivo,
                'prefijo'             => null,
                'consecutivo'         => null,
                'errors' => [
                    'No fue posible descomprimir el archivo'
                ],
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento'  => date('H:i:s'),
                'archivo'             => $subjectCorreo,
                'traza'               => [
                    'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                ]
            ];
        }

        // Construye un array para retornar los resultados del proceso
        $return = [
            'zipDescomprimidoTotal'   => $zipDescomprimidoTotal,
            'arrArchivosZipEliminar'  => $this->arrArchivosZipEliminar,
            'arrArchivosDescartar'    => $arrArchivosDescartar,
            'arrErroresPorDirectorio' => $this->arrErroresPorDirectorio
        ];
        return $return;
    }

    /**
     * Elimina los archivos que fueron extraidos de archivos zip.
     *
     * @param Array Archivos zip extraidos
     * @return Array Array sin elementos
     */
    public function borrarArchivosZipExtraidos($arrArchivosZipEliminar) {
        if(!empty($arrArchivosZipEliminar)) {
            foreach($arrArchivosZipEliminar as $archivoBorrar) {
                @unlink($archivoBorrar);
            }
        }

        return $arrArchivosZipEliminar;
    }

    /**
     * Permite la creación de registros en la tabla etl_procesamiento_json.
     * 
     * Utilizado en este comando para poder registrar los errores de procesamiento que se puedan generar.
     *
     * @param array $valores Array que contiene la información de los campos para la creación del registro
     * @return void
     */
    public function registrarErroresProcesamiento(array $valores){
        EtlProcesamientoJson::create([
            'pjj_tipo'                => $valores['pjj_tipo'],
            'pjj_json'                => $valores['pjj_json'],
            'pjj_procesado'           => $valores['pjj_procesado'],
            'pjj_errores'             => $valores['pjj_errores'],
            'age_id'                  => $valores['age_id'],
            'age_estado_proceso_json' => $valores['age_estado_proceso_json'],
            'usuario_creacion'        => $valores['usuario_creacion'],
            'estado'                  => $valores['estado']
        ]);
    }

    /**
     * Procesa un directorio que contiene los archivos relacionados con un correo recibido en recepción de FNC
     *
     * @param string $directorio Directorio a procesar
     * @param string $pathDirPrincipal Path al directorio principal de procesamiento
     * @param ParserXml $parserXml Instancia a ParserXml
     * @param object $rutas Contiene las rutas del disco a utilizar
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE del grupo de FNC
     * @param array $arrArchivosDescartar Arreglo de archivos que NO deben ser tenidos en cuenta para guardarse como anexos
     * @param bool $zipDescomprimidoTotal Variable para indicar que ya se descomprime el zip en su totalidad
     * @param array $arrArchivosZipEliminar Contiene los archivos que se deben de eliminar
     * @param array $arrErroresPorDirectorio Arreglo de errores que son generados en el procesamiento de cada carpeta, para almacenar un solo registro con todos los errores de una sola carpeta.
     * @param string $discoAnexos Disco para los anexos
     * @param string $lote Cadena de texto Uuid para identificar el lote
     * @param object $user Usuario autenticado en el sistema
     * @param bool $asociarDocumento Indica si va procesar el directorio para asociar con documento
     * @param string $cdo_id Id del documento a asociar
     * @return array
     */
    public function procesarDirectorio(string $directorio, string $pathDirPrincipal, ParserXml $parserXml, $rutas, ConfiguracionObligadoFacturarElectronicamente $ofe, array $arrArchivosDescartar, bool $zipDescomprimidoTotal, array $arrArchivosZipEliminar, array $arrErroresPorDirectorio, string $discoAnexos, string $lote, $user, bool $asociarDocumento = false, string $cdo_id = null) {
        $this->discoAnexos = $discoAnexos;
        $this->arrArchivosZipEliminar = $arrArchivosZipEliminar;
        $this->arrErroresPorDirectorio = $arrErroresPorDirectorio;
        $this->user = $user;
        $this->lote = $lote;
        $this->asociarDocumento = $asociarDocumento;

        $subjectFile   = glob($pathDirPrincipal . '/' . $directorio . '/subject.txt');
        $subjectCorreo = File::get($subjectFile[0]);
        $subjectParts  = explode(';', $subjectCorreo);

        if($asociarDocumento) {
            $documentoRecepcion = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_origen', 'ofe_id', 'pro_id', 'rfa_prefijo', 'cdo_consecutivo', 'estado'])
                ->where('cdo_id', $cdo_id)
                ->with('getConfiguracionProveedor:pro_id,pro_identificacion')
                ->first();
            
            $prefijoConsecutivo = $documentoRecepcion->rfa_prefijo . $documentoRecepcion->cdo_consecutivo;
        } else {
            if($subjectParts[0] == 'Soporte') {
                $stdSubject         = 'FNC';
                $pro_identificacion = $subjectParts[1];
                $prefijoConsecutivo = $subjectParts[2];
                $tdeCodigo          = $subjectParts[3];
            } else {
                $stdSubject         = 'DIAN';
                $pro_identificacion = $subjectParts[0];
                $prefijoConsecutivo = $subjectParts[2];
                $tdeCodigo          = $subjectParts[3];
            }
        }

        // Se verifica si en el correo existen archivos zip, en cuyo caso deben ser descomprimidos de manera recursiva
        $arrZip    = glob($pathDirPrincipal . '/' . $directorio . '/*.zip');
        foreach($arrZip as $zip) {
            $arrNombreZip = explode('/', $zip);

            if(!in_array($arrNombreZip[count($arrNombreZip) - 1], $arrArchivosDescartar)) {
                $arrArchivosDescartar[] = $arrNombreZip[count($arrNombreZip) - 1];
                $unzip = $this->unzip($prefijoConsecutivo, $subjectCorreo, $zip, $pathDirPrincipal, $directorio, $zipDescomprimidoTotal, $this->arrArchivosZipEliminar, $rutas, $arrArchivosDescartar, $arrErroresPorDirectorio);
                $zipDescomprimidoTotal         = $unzip['zipDescomprimidoTotal'];
                $arrArchivosDescartar          = $unzip['arrArchivosDescartar'];
                $this->arrErroresPorDirectorio = $unzip['arrErroresPorDirectorio'];
                $this->arrArchivosZipEliminar  = $unzip['arrArchivosZipEliminar'];
            }
        }

        if($asociarDocumento) {
            $this->procesarAnexos($documentoRecepcion, $directorio, $pathDirPrincipal, $rutas, $arrArchivosDescartar, '', $ofe);
            $this->arrArchivosZipEliminar = $this->borrarArchivosZipExtraidos($this->arrArchivosZipEliminar);

            return $this->arrErroresPorDirectorio;
        }

        if(!$zipDescomprimidoTotal) {
            $this->moverDirectorio($directorio, 'fallidos', $pathDirPrincipal, $rutas, $ofe, $this->arrErroresPorDirectorio);
            return $this->arrErroresPorDirectorio;
        }

        // Se debe verificar si dentro de los archivos se encuentra el archivo xml-ubl que puede corresponder a Invoice, DebitNote, CreditNote o AttachedDocument
        $arrPdf    = glob($pathDirPrincipal . '/' . $directorio . '/*.pdf');
        $arrXmlUbl = glob($pathDirPrincipal . '/' . $directorio . '/*.xml');

        $pdfRg  = '';
        $xmlUbl = '';

        // Si arrXmlUbl tiene más de un elemento, quiere decir que el correo llegó con varios anexos xml y no es posible definir cual es el principal
        // En ese caso se debe realizar la verificación de existencia del documento mediante el subject del correo y si el documento no existe deberá pasar a proceso manual
        if(count($arrXmlUbl) == 1) {
            $xmlUbl          = File::get($arrXmlUbl[0]);
            $tmpNombreXmlUbl = explode('/', $arrXmlUbl[0]);
            $nombreXmlUbl    = $tmpNombreXmlUbl[count($tmpNombreXmlUbl) - 1];
            $pdfRg           = !empty($arrPdf) || count($arrPdf) == 1 ? File::get($arrPdf[0]) : '';

            $parserXml->definirTipoDocumento($xmlUbl);

            if($parserXml->tipoDocumentoOriginal == 'AttachedDocument') {
                // Obtiene el xml-ubl dentro del AttachedDocument para poder continuar con el procesamiento
                $xmlUbl  = (string) $parserXml->getValueByXpath("//{$parserXml->tipoDocumento}/cac:Attachment/cac:ExternalReference/cbc:Description");
                $parserXml->definirTipoDocumento($xmlUbl);
            }

            $cufeCude           = ((string) $parserXml->getValueByXpath("//{$parserXml->tipoDocumento}/cbc:UUID")) ? (string) $parserXml->getValueByXpath("//{$parserXml->tipoDocumento}/cbc:UUID") : null;
            $documentoRecepcion = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_origen', 'ofe_id', 'pro_id', 'rfa_prefijo', 'cdo_consecutivo', 'estado', 'cdo_nombre_archivos', 'fecha_creacion'])
                ->where('cdo_cufe', $cufeCude)
                ->with([
                    'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion',
                    'getConfiguracionProveedor:pro_id,pro_identificacion',
                    'getRdi:est_id,cdo_id,est_estado,est_informacion_adicional',
                    'getAcuseRecibo:est_id,cdo_id',
                    'getReciboBien:est_id,cdo_id',
                    'getAceptado:est_id,cdo_id',
                    'getAceptadoT:est_id,cdo_id',
                    'getRechazado:est_id,cdo_id'
                ])
                ->first();
            if($documentoRecepcion && $documentoRecepcion->cdo_origen == 'CORREO') {
                // El documento existe con origen CORREO por lo que se debe actualizar si el estandar del subject es DIAN, si el subject es el estandar de FNC solo se procesan los anexos
                if($stdSubject == 'DIAN') {
                    $ofe_identificacion = ((string) $parserXml->getValueByXpath("//{$parserXml->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID")) ? (string) $parserXml->getValueByXpath("//{$parserXml->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID") : '';
                    if($ofe_identificacion == $ofe->ofe_identificacion) {
                        // Si el documento ya tiene algún estado de la DIAN Exitoso solamente se deben procesar los anexos
                        if($documentoRecepcion->getAcuseRecibo || $documentoRecepcion->getReciboBien || $documentoRecepcion->getAceptado || $documentoRecepcion->getAceptadoT || $documentoRecepcion->getRechazado) {
                            $this->procesarAnexos($documentoRecepcion, $directorio, $pathDirPrincipal, $rutas, $arrArchivosDescartar, $nombreXmlUbl, $ofe);
                        } else {
                            $documentoRecepcion->update(['estado' => 'INACTIVO']);
                            $this->crearActualizarDocumento($documentoRecepcion, $directorio, $nombreXmlUbl, 'CORREO', base64_encode($xmlUbl), (!empty($pdfRg) ? base64_encode($pdfRg) : null), $stdSubject, $parserXml, $pathDirPrincipal, $rutas, $arrArchivosDescartar);
                        }
                    } else {
                        $this->arrErroresPorDirectorio[] = [
                            'documento'           => $prefijoConsecutivo,
                            'prefijo'             => null,
                            'consecutivo'         => null,
                            'errors' => [
                                'El adquirente del documento electrónico en recepción [' . $ofe_identificacion . '] no corresponde al OFE con identificación [' . $ofe->ofe_identificacion . ']'
                            ],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s'),
                            'archivo'             => $subjectCorreo,
                            'traza'               => [
                                'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                            ]
                        ];

                        $this->moverDirectorio($directorio, 'fallidos', $pathDirPrincipal, $rutas, $ofe, $this->arrErroresPorDirectorio);
                        return $this->arrErroresPorDirectorio;
                    }
                } else {
                    $this->procesarAnexos($documentoRecepcion, $directorio, $pathDirPrincipal, $rutas, $arrArchivosDescartar, $nombreXmlUbl, $ofe);
                }
            } elseif($documentoRecepcion && ($documentoRecepcion->cdo_origen == 'RPA' || $documentoRecepcion->cdo_origen == 'MANUAL' || $documentoRecepcion->cdo_origen == 'NO-ELECTRONICO')) {
                // El documento existe con orien RPA o Manual entonces solamente se procesan los anexos
                $this->procesarAnexos($documentoRecepcion, $directorio, $pathDirPrincipal, $rutas, $arrArchivosDescartar, $nombreXmlUbl, $ofe);
            } elseif(!$documentoRecepcion) {
                // El documento electrónico no existe por lo que debe ser creado y luego se procesan los anexos si el adquirente del documento electrónico es FNC
                $ofe_identificacion = ((string) $parserXml->getValueByXpath("//{$parserXml->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID")) ? (string) $parserXml->getValueByXpath("//{$parserXml->tipoDocumento}/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID") : '';
                if($ofe_identificacion == $ofe->ofe_identificacion) {
                    $this->crearActualizarDocumento(null, $directorio, $nombreXmlUbl, 'CORREO', base64_encode($xmlUbl), (!empty($pdfRg) ? base64_encode($pdfRg) : null), $stdSubject, $parserXml, $pathDirPrincipal, $rutas, $arrArchivosDescartar);
                } else {
                    $this->arrErroresPorDirectorio[] = [
                        'documento'           => $prefijoConsecutivo,
                        'prefijo'             => null,
                        'consecutivo'         => null,
                        'errors' => [
                            'El adquirente del documento electrónico en recepción [' . $ofe_identificacion . '] no corresponde al OFE con identificación [' . $ofe->ofe_identificacion . ']'
                        ],
                        'fecha_procesamiento' => date('Y-m-d'),
                        'hora_procesamiento'  => date('H:i:s'),
                        'archivo'             => $subjectCorreo,
                        'traza'               => [
                            'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                        ]
                    ];

                    $this->moverDirectorio($directorio, 'fallidos', $pathDirPrincipal, $rutas, $ofe, $this->arrErroresPorDirectorio);

                    return $this->arrErroresPorDirectorio;
                }
            }

            return $this->arrErroresPorDirectorio;
        } elseif(count($arrXmlUbl) > 1) {

            $this->arrErroresPorDirectorio[] = [
                'documento'           => $prefijoConsecutivo,
                'prefijo'             => null,
                'consecutivo'         => null,
                'errors' => [
                    'El correo tiene más de un documento xml, no es posible definir cual corresponde al documento electrónico'
                ],
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento'  => date('H:i:s'),
                'archivo'             => $subjectCorreo,
                'traza'               => [
                    'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                ]
            ];

            $this->moverDirectorio($directorio, 'fallidos', $pathDirPrincipal, $rutas, $ofe, $this->arrErroresPorDirectorio);
            return $this->arrErroresPorDirectorio;
        }

        // Si el xml-ubl no existe en el directorio, es porque el correo solo contiene anexos y se debe consultar si el documento electrónico existe en openETL
        // teniendo en cuenta los datos del subject del correo (archivo subject.txt) obtenidos al inicio del procesamiento de la carpeta del correo
        if(empty($xmlUbl)) {
            $pro = ConfiguracionProveedor::select(['pro_id'])
                ->where('ofe_id', $ofe->ofe_id)
                ->where('pro_identificacion', $pro_identificacion)
                ->where('estado', 'ACTIVO')
                ->first();

            if(!$pro) {
                $this->arrErroresPorDirectorio[] = [
                    'documento'           => $prefijoConsecutivo,
                    'prefijo'             => null,
                    'consecutivo'         => null,
                    'errors' => [
                        'No existe el proveedor [' . $pro_identificacion . '] para el correo con subject [' . $subjectCorreo . '] '
                    ],
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s'),
                    'archivo'             => $subjectCorreo,
                    'traza'               => [
                        'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                    ]
                ];

                $this->moverDirectorio($directorio, 'fallidos', $pathDirPrincipal, $rutas, $ofe, $this->arrErroresPorDirectorio);
                return $this->arrErroresPorDirectorio;
            }

            $tipoDocumentoElectronico = ParametrosTipoDocumentoElectronico::select('tde_id', 'tde_codigo', 'tde_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta')
                ->where('tde_codigo', $tdeCodigo)
                ->where('estado', 'ACTIVO')
                ->get()->groupBy('tde_codigo')
                ->map(function ($item) {
                    $vigente = $this->validarVigenciaRegistroParametrica($item);
                    if($vigente['vigente'])
                        return $vigente['registro'];
                })->values()->toArray();

            if(empty($tipoDocumentoElectronico)) {
                $this->arrErroresPorDirectorio[] = [
                    'documento'           => $prefijoConsecutivo,
                    'prefijo'             => null,
                    'consecutivo'         => null,
                    'errors' => [
                        'El tipo de documento electrónico [' . $tdeCodigo . '] no existe o no se encuentra vigente. Documento [' . $prefijoConsecutivo . '] - Proveedor [' . $pro_identificacion . '] - Correo con subject [' . $subjectCorreo . '] '
                    ],
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s'),
                    'archivo'             => $subjectCorreo,
                    'traza'               => [
                        'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                    ]
                ];

                $this->moverDirectorio($directorio, 'fallidos', $pathDirPrincipal, $rutas, $ofe, $this->arrErroresPorDirectorio);
                return $this->arrErroresPorDirectorio;
            }

            $documentoRecepcion = RepCabeceraDocumentoDaop::select(['cdo_id', 'cdo_origen', 'ofe_id', 'pro_id', 'rfa_prefijo', 'cdo_consecutivo', 'estado'])
                ->where(DB::raw("CONCAT(`rfa_prefijo`, '', `cdo_consecutivo`)"), $prefijoConsecutivo)
                ->where('pro_id', $pro->pro_id)
                ->where('ofe_id', $ofe->ofe_id)
                ->where('tde_id', $tipoDocumentoElectronico[0]['tde_id'])
                ->with([
                    'getConfiguracionProveedor:pro_id,pro_identificacion'
                ])
                ->first();

            if(!$documentoRecepcion) {
                $this->arrErroresPorDirectorio[] = [
                    'documento'           => $prefijoConsecutivo,
                    'prefijo'             => null,
                    'consecutivo'         => null,
                    'errors' => [
                        'No existe el documento [' . $prefijoConsecutivo . '] para el proveedor [' . $pro_identificacion . '] del correo con subject [' . $subjectCorreo . ']'
                    ],
                    'fecha_procesamiento' => date('Y-m-d'),
                    'hora_procesamiento'  => date('H:i:s'),
                    'archivo'             => $subjectCorreo,
                    'traza'               => [
                        'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                    ]
                ];

                $this->moverDirectorio($directorio, 'fallidos', $pathDirPrincipal, $rutas, $ofe, $this->arrErroresPorDirectorio);
                return $this->arrErroresPorDirectorio;
            }

            $this->procesarAnexos($documentoRecepcion, $directorio, $pathDirPrincipal, $rutas, $arrArchivosDescartar, '', $ofe);
            return $this->arrErroresPorDirectorio;
        }
    }

    /**
     * Mueve un directorio de correo al directorio de exitosos o fallidos.
     *
     * Dentro del proceso se verifica si existen archivos extraidos de zip porque deben ser eliminados para dejar la carpeta en su estado inicial.
     *
     * @param string $directorio Directorio a mover
     * @param string $dirDestino Directorio destino (exitosos|fallidos)
     * @param string $pathDirPrincipal Path al directorio principal de procesamiento
     * @param object $rutas Contiene las rutas del disco a utilizar
     * @param array $arrErrors Contiene los errores a guardar
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE del grupo de FNC
     * @return void
     */
    public function moverDirectorio(string $directorio, string $dirDestino, string $pathDirPrincipal, $rutas, ConfiguracionObligadoFacturarElectronicamente $ofe, $arrErrors = []) {
        $this->arrArchivosZipEliminar = $this->borrarArchivosZipExtraidos($this->arrArchivosZipEliminar);

        if(isset($this->user))
            $discoCorreos = $this->user->getBaseDatos->bdd_nombre;
        else
            $discoCorreos = auth()->user()->getBaseDatos->bdd_nombre;

        if($dirDestino == 'exitosos') {
            if (File::exists($pathDirPrincipal . '/' . $directorio))
                Storage::disk($discoCorreos)->deleteDirectory($rutas->exitosos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio)));

            Storage::disk($discoCorreos)
                ->move($directorio, $rutas->exitosos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio)));
        } elseif($dirDestino == 'fallidos') {
            if (File::exists($pathDirPrincipal . '/' . $directorio))
                Storage::disk($discoCorreos)->deleteDirectory($rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio)));

            $arrErrObservaciones = [];
            foreach ($arrErrors as $errors) {
                if (array_key_exists('errors', $errors) && !empty($errors['errors'])) {
                    $arrErrObservaciones[] = $errors['errors'];
                }
            }

            $idCarpeta = explode('/', $directorio);
            $subject   = file_get_contents($pathDirPrincipal . '/' . $directorio . '/subject.txt');
            $bodyEmail = file_get_contents($pathDirPrincipal . '/' . $directorio . '/cuerpo_correo.txt');
            $dateEmail = file_get_contents($pathDirPrincipal . '/' . $directorio . '/fecha.txt');
            $insertEmail = [
                'ofe_identificacion'    => $ofe->ofe_identificacion,
                'epm_subject'           => $subject,
                'epm_id_carpeta'        => str_replace('.procesado', '', end($idCarpeta)),
                'epm_cuerpo_correo'     => (!empty($bodyEmail)) ? $bodyEmail : 'Correo sin contenido.',
                'epm_fecha_correo'      => $dateEmail,
                'epm_procesado'         => 'NO',
                'epm_procesado_usuario' => NULL,
                'epm_procesado_fecha'   => NULL,
                'epm_observaciones'     => (!empty($arrErrObservaciones)) ? json_encode($arrErrObservaciones) : NULL,
                'usuario_creacion'      => '1',
                'estado'                => 'ACTIVO'
            ];
            EtlEmailProcesamientoManual::create($insertEmail);

            Storage::disk($discoCorreos)
                ->move($directorio, $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio)));
        }
    }

    /**
     * Crea o actualiza un documento electrónico de recepción recibido mediante correo electrónico.
     *
     * @param RepCabeceraDocumentoDaop|null $documentoRecepcion Instancia del documento electrónico
     * @param string $directorio Directorio de correo electrónico que está siendo procesado
     * @param string $nombreXmlUbl Nombre del XmlUbl recibido en el correo
     * @param string $origen Origen de procesamiento
     * @param string $base64Xml Base64 del XmlUbl
     * @param string|null $base64Pdf Base64 del PDF (RG)
     * @param string $stdSubject Estandar del subject del correo (DIAN|FNC)
     * @param ParserXml $parserXml Instancia a ParserXml
     * @param string $pathDirPrincipal Path al directorio principal de procesamiento
     * @param object $rutas Contiene las rutas del disco a utilizar
     * @param array $arrArchivosDescartar Arreglo de archivos que NO deben ser tenidos en cuenta para guardarse como anexos
     * @return void
     */
    public function crearActualizarDocumento(RepCabeceraDocumentoDaop $documentoRecepcion = null, string $directorio, string $nombreXmlUbl, string $origen, string $base64Xml, string $base64Pdf = null, string $stdSubject, ParserXml $parserXml, string $pathDirPrincipal, $rutas, array $arrArchivosDescartar) {
        $nombreXmlUblSinExt = explode('.', $nombreXmlUbl);
        $nombreXmlUblSinExt = str_replace('.' . $nombreXmlUblSinExt[count($nombreXmlUblSinExt) - 1], '', $nombreXmlUbl);
        $documentoProcesado = $parserXml->Parser($origen, $base64Xml, $base64Pdf, $nombreXmlUblSinExt);
        $discoCorreos       = $this->user->getBaseDatos->bdd_nombre;
        if(array_key_exists('procesado', $documentoProcesado) && $documentoProcesado['procesado']) {
            // Crea un nuevo agendamiento para GETSTATUS para el documento recien creado
            $nuevoAgendamiento = $parserXml::creaNuevoAgendamiento($this->user->getBaseDatos->bdd_id, 'RGETSTATUS', 1);
            $parserXml::creaNuevoEstadoDocumentoRecepcion(
                $documentoProcesado['cdo_id'],
                'GETSTATUS',
                null,
                null,
                null,
                null,
                $nuevoAgendamiento,
                $this->user->usu_id,
                null,
                null
            );

            if(!$documentoRecepcion) {
                $documentoRecepcion = RepCabeceraDocumentoDaop::select(['cdo_id', 'tde_id', 'cdo_origen', 'ofe_id', 'pro_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha', 'estado'])
                    ->where('cdo_id', $documentoProcesado['cdo_id'])
                    ->with([
                        'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                            $query->select([
                                'ofe_id',
                                'ofe_identificacion',
                                'ofe_correo',
                                'ofe_recepcion_fnc_activo',
                                \DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_razon_social')
                            ]);
                        },
                        'getConfiguracionProveedor' => function($query) {
                            $query->select([
                                'pro_id',
                                'pro_identificacion',
                                \DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as pro_razon_social')
                            ]);
                        },
                        'getConfiguracionProveedor.getProveedorGruposTrabajo:gtp_id,gtr_id,pro_id',
                        'getConfiguracionProveedor.getProveedorGruposTrabajo.getGrupoTrabajo:gtr_id,gtr_nombre,gtr_correos_notificacion',
                        'getRdi:est_id,cdo_id,est_estado,est_informacion_adicional',
                        'getTipoDocumentoElectronico:tde_id,tde_codigo,tde_descripcion'
                    ])
                    ->first();

                // El documento electrónico fue creado por lo que se envía un correo a los usuarios asociados al grupo de trabajo con el que se asoció el proveedor
                TenantTrait::GetVariablesSistemaTenant();
                if(config('variables_sistema_tenant.NOTIFICAR_ASIGNACION_GRUPO_TRABAJO') == 'SI' && $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_fnc_activo == 'SI') {
                    DiTrait::setMailInfo();
                    $this->notificarNuevoDocumentoCorreo($documentoRecepcion);
                }
            } else {
                // Cuando el documento existe previamente, el estado RDI inicial se debe modificar para dejar registrado en información adicional del estado la propiedad origen con valor CORREO
                $rdiInfoAdicional = $documentoRecepcion->getRdi->est_informacion_adicional != '' ? json_decode($documentoRecepcion->getRdi->est_informacion_adicional, true) : [];
                $rdiInfoAdicional['origen'] = 'CORREO';

                $documentoRecepcion->getRdi->update([
                    'est_informacion_adicional' => json_encode($rdiInfoAdicional)
                ]);

                // Cuando el documento existe previamente y el subject del correo corresponde al estandar de la DIAN, el XmlUbl y RG previos deben ser guardados como anexos
                if($stdSubject == 'DIAN') {
                    $xmlUbl = $this->obtenerArchivoDeDisco(
                        'recepcion',
                        $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                        $documentoRecepcion,
                        array_key_exists('est_xml', $rdiInfoAdicional) ? $rdiInfoAdicional['est_xml'] : null
                    );

                    if(!empty($xmlUbl)) {
                        Storage::disk($discoCorreos)->put(
                            $directorio . '/' . $rdiInfoAdicional['est_xml'],
                            $xmlUbl
                        );

                        $this->arrArchivosZipEliminar[] = $pathDirPrincipal . '/' . $directorio . '/' . $rdiInfoAdicional['est_xml'];
                    }

                    $pdfRg = $this->obtenerArchivoDeDisco(
                        'recepcion',
                        $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                        $documentoRecepcion,
                        array_key_exists('est_archivo', $rdiInfoAdicional) ? $rdiInfoAdicional['est_archivo'] : null
                    );

                    if(!empty($pdfRg)) {
                        Storage::disk($discoCorreos)->put(
                            $directorio . '/' . $rdiInfoAdicional['est_archivo'],
                            $pdfRg
                        );

                        $this->arrArchivosZipEliminar[] = $pathDirPrincipal . '/' . $directorio . '/' . $rdiInfoAdicional['est_archivo'];
                    }
                }
            }
            $this->procesarAnexos($documentoRecepcion, $directorio, $pathDirPrincipal, $rutas, $arrArchivosDescartar, $nombreXmlUbl, $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente);
        }
    }

    /**
     * Procesa los anexos de un correo electrónico.
     * 
     * Dependiendo de si todos los anexos se procesaron bien, el directorio del correo se puede mover a existosos o fallidos
     *
     * @param RepCabeceraDocumentoDaop $documentoRecepcion Documento electrónico
     * @param string $directorio Directorio en donde se encuentran los anexos
     * @param string $pathDirPrincipal Path al directorio principal de procesamiento
     * @param object $rutas Contiene las rutas del disco a utilizar
     * @param array $arrArchivosDescartar Arreglo de archivos que NO deben ser tenidos en cuenta para guardarse como anexos
     * @param string $nombreXmlUbl Nombre del archivo xml-ubl
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE del grupo de FNC
     * @return void
     */
    public function procesarAnexos(RepCabeceraDocumentoDaop $documentoRecepcion, string $directorio, string $pathDirPrincipal, $rutas, array $arrArchivosDescartar, string $nombreXmlUbl = '', ConfiguracionObligadoFacturarElectronicamente $ofe) {
        if(!empty($nombreXmlUbl))
            $arrArchivosDescartar[] = $nombreXmlUbl;
        $discoCorreos = $this->user->getBaseDatos->bdd_nombre;
        // Busca los archivos que existen en la carpeta del correo
        $anexos = Storage::disk($discoCorreos)
            ->files($directorio);
        $contAnexosGuardados = 0;

        array_map(function($anexo) use ($documentoRecepcion, &$contAnexosGuardados, $directorio, &$arrArchivosDescartar, $pathDirPrincipal, $rutas, $ofe) {
            $tmpNombre = explode('/', $anexo);
            $tmpExtension = explode('.', $tmpNombre[count($tmpNombre) - 1]);

            if(
                !in_array($tmpNombre[count($tmpNombre) - 1], $arrArchivosDescartar) &&
                $anexo != $directorio . '/undefined' &&
                !empty($tmpExtension[0]) && // Si la primer posición del array es vacia el anexo no se guarda porque corresponde a un archivo oculto
                in_array($tmpExtension[count($tmpExtension) - 1], $this->arrExtensionesValidas)
            ) {
                $guardar = $this->guardarAnexo($documentoRecepcion, $directorio, $anexo, $pathDirPrincipal, $rutas, $ofe);

                if($guardar)
                    $contAnexosGuardados++;
            } elseif($anexo == $directorio . '/undefined' || empty($tmpExtension[0])) {
                $arrArchivosDescartar[] = $anexo;
            }
        }, $anexos);

        if(!$this->asociarDocumento) {
            if($contAnexosGuardados == (count($anexos) - count($arrArchivosDescartar))) {
                $this->moverDirectorio($directorio, 'exitosos', $pathDirPrincipal, $rutas, $ofe);
            } else {
                $this->moverDirectorio($directorio, 'fallidos', $pathDirPrincipal, $rutas, $ofe, $this->arrErroresPorDirectorio);
            }
        }
    }

    /**
     * Guarda un anexo en el sistema relacionándolo con el documento electrónico correspondiente.
     *
     * @param RepCabeceraDocumentoDaop $documentoRecepcion Documento electrónico
     * @param string $directorio Directorio en donde se encuentran los anexos
     * @param string $anexo Anexo del documento electrónico
     * @param string $pathDirPrincipal Path al directorio principal de procesamiento
     * @param object $rutas Contiene las rutas del disco a utilizar
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Instancia del OFE del grupo de FNC
     * @return bool
     */
    public function guardarAnexo(RepCabeceraDocumentoDaop $documentoRecepcion, string $directorio, string $anexo, string $pathDirPrincipal, $rutas, ConfiguracionObligadoFacturarElectronicamente $ofe) {
        $uuid         = Uuid::uuid4();
        $nuevoNombre  = $uuid->toString();
        $extension    = explode('.', $anexo);
        $discoCorreos = $this->user->getBaseDatos->bdd_nombre;
        DiTrait::crearDiscoDinamico($this->discoAnexos, config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION'));

        // Creando cada directorio
        $rutaAnexo = $this->user->getBaseDatos->bdd_nombre;
        $this->crearDirectorio(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo. '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        $rutaAnexo .= '/' . $ofe->ofe_identificacion;
        $this->crearDirectorio(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo. '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        $rutaAnexo .= '/' . $documentoRecepcion->getConfiguracionProveedor->pro_identificacion;
        $this->crearDirectorio(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo. '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        $rutaAnexo .= '/' . date('Y');
        $this->crearDirectorio(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo. '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        $rutaAnexo .='/' . date('m');
        $this->crearDirectorio(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo. '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        $rutaAnexo .= '/' . date('d');
        $this->crearDirectorio(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo. '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        $rutaAnexo .= '/' . date('H');
        $this->crearDirectorio(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo. '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        $rutaAnexo .= '/' . date('i');
        $this->crearDirectorio(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo. '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        $this->asignarPermisos(
            config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo . '/',
            config('variables_sistema.USUARIO_SO'),
            config('variables_sistema.GRUPO_SO'),
            0755
        );

        Storage::disk($this->discoAnexos)->put(
            $rutaAnexo . '/' . $nuevoNombre . '.' . $extension[count($extension) - 1],
            Storage::disk($discoCorreos)->get($anexo)
        );

        chown(config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo . '/' . $nuevoNombre . '.' . $extension[count($extension) - 1], config('variables_sistema.USUARIO_SO'));
        chgrp(config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo . '/' . $nuevoNombre . '.' . $extension[count($extension) - 1], config('variables_sistema.GRUPO_SO')); 
        chmod(config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION') . '/' . $rutaAnexo . '/' . $nuevoNombre . '.' . $extension[count($extension) - 1], 0755);

        $tamano            = filesize($pathDirPrincipal . '/' . $anexo);
        $nombreOriginal    = explode('/', $anexo);
        $nombreOriginal    = explode('.', $nombreOriginal[count($nombreOriginal) - 1]);
        $nombreOriginal    = $this->sanear_string($nombreOriginal[0]);
        $extensionOriginal = $extension[count($extension) - 1];

        // Verifica que el documento anexo no exista en la BD para el mismo documento
        $existe = RepDocumentoAnexoDaop::where('cdo_id', $documentoRecepcion->cdo_id)
            ->where('dan_nombre', $nombreOriginal . '.' . $extensionOriginal)
            ->where('dan_tamano', $tamano)
            ->first();

        if (!$existe) {
            RepDocumentoAnexoDaop::create([
                'cdo_id'           => $documentoRecepcion->cdo_id,
                'dan_lote'         => $this->lote,
                'dan_uuid'         => $nuevoNombre,
                'dan_tamano'       => $tamano,
                'dan_nombre'       => $nombreOriginal . '.' . $extensionOriginal,
                'dan_descripcion'  => $nombreOriginal,
                'estado'           => 'ACTIVO',
                'usuario_creacion' => $this->user->usu_id
            ]);

            return true;
        } else {
            $this->arrErroresPorDirectorio[] = [
                'documento'           => $documentoRecepcion->rfa_prefijo . $documentoRecepcion->cdo_consecutivo,
                'prefijo'             => $documentoRecepcion->rfa_prefijo,
                'consecutivo'         => $documentoRecepcion->cdo_consecutivo,
                'errors' => [
                    'El documento anexo [' . $nombreOriginal . '.' . $extensionOriginal . '] ya se encuentra registrado para el documento electrónico'
                ],
                'fecha_procesamiento' => date('Y-m-d'),
                'hora_procesamiento'  => date('H:i:s'),
                'archivo'             => $nombreOriginal . '.' . $extensionOriginal,
                'traza'               => [
                    'carpeta_correo' => $pathDirPrincipal . '/' . $rutas->fallidos . (str_replace([$rutas->entrada, '.procesado'], ['', ''], $directorio))
                ]
            ];

            return false;
        }
    }

    /**
     * Asigna permiso a los directorios y archivos de un directorio.
     *
     * @param string $ruta      Ruta del directorio
     * @param string $usuarioSo Usuario sistema operativo asignar al directorio
     * @param string $grupoSO   Grupo sistema operativo asignar al directorio
     * @param string $permiso   Permiso asignar al directorio
     * @return void
     */
    private function asignarPermisos(string $ruta, string $usuarioSo, string $grupoSO, string $permiso){
        // Abrir un directorio y listarlo recursivo
        if (is_dir($ruta)) {
            chown($ruta, $usuarioSo);
            chgrp($ruta, $grupoSO);
            chmod($ruta, $permiso);
            if ($dh = opendir($ruta)) {
                while (($file = readdir($dh)) !== false) {
                    chown($ruta . $file, $usuarioSo);
                    chgrp($ruta . $file, $grupoSO);
                    chmod($ruta . $file, $permiso);
                    // Mostraría tanto archivos como directorios
                    if (is_dir($ruta . $file) && $file!="." && $file!="..") {
                        // Solo si el archivo es un directorio, distinto que "." y ".."
                        $this->asignarPermisos($ruta . $file . '/', $usuarioSo, $grupoSO, $permiso);
                    }
                }
                closedir($dh);
            }
        } else {
            // Si es un archivo se asignan los permisos
            if (is_file($ruta)) {
                chown($ruta, $usuarioSo);
                chgrp($ruta, $grupoSO); 
                chmod($ruta, $permiso);
            }
        } 
    }

    /**
     * Actualiza los campos epm_procesado, epm_procesado_usuario, epm_procesado_fecha cuando se asocian a un documento.
     *
     * @param string $id Id del correo
     * @param string $procesado SI | NO
     * @return bool
     */
    public function actualizarCamposCorreo(string $id, string $procesado) {
        $correoRecibido = EtlEmailProcesamientoManual::select(['epm_id', 'epm_procesado', 'epm_procesado_usuario', 'epm_procesado_fecha'])
            ->where('epm_id', $id)
            ->first();

        $procesadoFecha = new \DateTime();
        $correoRecibido->epm_procesado = $procesado;
        $correoRecibido->epm_procesado_usuario = $this->user->usu_id;
        $correoRecibido->epm_procesado_fecha = $procesadoFecha->format('Y-m-d H:i:s');
        $correoRecibido->save();
    }

    /**
     * Asocia los anexos de un correo recibido con un documento.
     *
     * @param string $epmId Id del correo recibido
     * @param string $cdoId Id del documento
     * @param object $user Usuario autenticado en el sistema
     * @param string $origen API | COMMAND
     * @return
     */
    public function asociarAnexosCorreo(string $epmId, string $cdoId, $user, string $origen = 'API') {
        $this->user = $user;
        $arrErrors = [];
        // Se obtiene el correo de donde se van a tomar los anexos
        $correoRecibido = EtlEmailProcesamientoManual::select(['epm_id','epm_subject','epm_id_carpeta'])
            ->where('epm_id', $epmId)
            ->where('estado', 'ACTIVO')
            ->first();

        if(!$correoRecibido)
            $arrErrors[] = 'El correo al que intenta asociar los anexos no existe.';

        // Se obtiene el documento con el que se van asociar los anexos
        $documentoRecepcion = RepCabeceraDocumentoDaop::select(['cdo_id', 'ofe_id', 'rfa_prefijo', 'cdo_consecutivo'])
            ->where('cdo_id', $cdoId)
            ->where('estado', 'ACTIVO')
            ->with([
                'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,ofe_recepcion_fnc_configuracion'
            ])
            ->first();

        if(!$documentoRecepcion)
            $arrErrors[] = 'El documento al que intenta asociar los anexos, no existe o esta inactivo.';

        if (empty($documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_fnc_configuracion))
            $arrErrors[] = 'El OFE [' . $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion . '] no tiene configurada la información para el proceso de Recepción - Asociar Anexos Correos';

        if(!empty($arrErrors)) {
            if($origen === 'API'){
                return response()->json([
                    'message' => 'Error al procesar la información',
                    'errors'  => $arrErrors
                ], 404);
            } else {
                return;
            }
        }

        $rutas = json_decode($documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_recepcion_fnc_configuracion);

        // Obtiene fecha y hora del sistema para utlizarlos en el UUID del lote
        $dateObj = \DateTime::createFromFormat('U.u', microtime(TRUE));
        $msg     = $dateObj->format('u');
        $msg    /= 1000;
        $dateObj->setTimeZone(new \DateTimeZone('America/Bogota'));
        $dateTime = $dateObj->format('YmdHis') . intval($msg);

        $lote             = $dateTime . '_' . Uuid::uuid4()->toString();
        $discoCorreos     = $this->user->getBaseDatos->bdd_nombre;
        $pathDirPrincipal = $rutas->directorio . '/' . $this->user->getBaseDatos->bdd_nombre . '/' . $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion;
        $parserXml        = new ParserXml($lote);
        $discoAnexos      = 'documentos_anexos_recepcion';

        DiTrait::crearDiscoDinamico($discoCorreos, $pathDirPrincipal);

        // Variables de validación
        $zipDescomprimidoTotal   = true;
        $arrArchivosZipEliminar  = [];
        $arrErroresPorDirectorio = [];
        $arrArchivosDescartar    = [
            'correo_completo.txt',
            'cuerpo_correo.txt',
            'fecha.txt',
            'subject.txt'
        ];

        $directorio       = $rutas->fallidos . $correoRecibido->epm_id_carpeta;
        $directorioCorreo = '';
        // Valida la existencia de la carpeta del correo
        if(Storage::disk($discoCorreos)->exists($directorio . '/subject.txt'))
            $directorioCorreo = $directorio;

        if(!$directorioCorreo) {
            if($origen === 'API') {
                return response()->json([
                    'message' => 'Error al procesar la información',
                    'errors' => ['No fue posible encontrar la ruta de los anexos del correo recibido']
                ], 404);
            } else {
                return;
            }
        }

        // Procesa el directorio, indicando en el parametro 13 que se va asociar con un documento
        $arrErroresPorDirectorio = $this->procesarDirectorio(
            $directorioCorreo,
            $pathDirPrincipal,
            $parserXml,
            $rutas,
            $documentoRecepcion->getConfiguracionObligadoFacturarElectronicamente,
            $arrArchivosDescartar,
            $zipDescomprimidoTotal,
            $arrArchivosZipEliminar,
            $arrErroresPorDirectorio,
            $discoAnexos,
            $lote,
            $this->user,
            true,
            $documentoRecepcion->cdo_id
        );

        $this->actualizarCamposCorreo($correoRecibido->epm_id, 'SI');

        if(!empty($arrErroresPorDirectorio)) {
            if($origen === 'API') {
                return response()->json([
                    'message' => 'Error al procesar la información',
                    'errors' => $arrErroresPorDirectorio
                ], 400);
            } else {
                return;
            }
        } else {
            if($origen === 'API')
                return response()->json([
                    'message' => "Los anexos del correo [{$correoRecibido->epm_subject}] se asociaron correctamente al documento [{$documentoRecepcion->rfa_prefijo} {$documentoRecepcion->cdo_consecutivo}]"
                ], 200);
            else {
                return;
            }
        }
    }
}