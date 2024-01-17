<?php

namespace App\Http\Traits;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;
use Aws\Laravel\AwsServiceProvider;
use Illuminate\Support\Facades\File;
use openEtl\Tenant\Traits\TenantSmtp;
use Illuminate\Support\Facades\Config;
use openEtl\Tenant\Traits\TenantTrait;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Sistema\VariablesSistema\VariableSistema;
use App\Http\Modulos\Configuracion\ConsecutivosArchivosEnviadosOfes\ConfiguracionConsecutivoArchivosEnviadosOfe;

/**
 * Traits de DO
 *
 * Trait DoTrait
 * @package App\Http\Traits
 */
trait DoTrait {
    /**
     * Crea un nuevo agendamiento para proceso PARSER
     *
     * @param string $tipoAgendamiento
     * @param integer $usu_id Id del usuario relacionado con el agendamiento
     * @param integer $bdd_id Id de la base de datos
     * @param integer $total Numero de documentos que gestioara el agendamiento
     * @param integer|null $prioridadAgendamiento Indica si es un agendamiento con prioridad
     * @return Illuminate\Database\Eloquent\Collection
     */
    public static function crearNuevoAgendamiento (string $tipoAgendamiento, $usu_id, $bdd_id, $total, $prioridadAgendamiento = null) {
        return AdoAgendamiento::create([
            'usu_id'                    => $usu_id,
            'bdd_id'                    => $bdd_id,
            'age_proceso'               => $tipoAgendamiento,
            'age_cantidad_documentos'   => $total,
            'usuario_creacion'          => $usu_id,
            'estado'                    => 'ACTIVO',
            'age_prioridad'             => $prioridadAgendamiento ?? null
        ]);
    }

    /**
     * Modifica en tiempo de ejecución las variables de conexión al servidor de correo desde las variables de sistema en el facade config
     *
     * @return void
     */
    public static function setMailInfo() {
        config([
            'mail.driver'     => config('variables_sistema.MAIL_MAILER'),
            'mail.host'       => config('variables_sistema.MAIL_HOST'),
            'mail.port'       => config('variables_sistema.MAIL_PORT'),
            'mail.address'    => config('variables_sistema.MAIL_FROM_ADDRESS'),
            'mail.name'       => config('variables_sistema.MAIL_FROM_NAME'),
            'mail.encryption' => config('variables_sistema.MAIL_ENCRYPTION') === 'null' ? null : config('variables_sistema.MAIL_ENCRYPTION'),
            'mail.username'   => config('variables_sistema.MAIL_USERNAME'),
            'mail.password'   => config('variables_sistema.MAIL_PASSWORD')
        ]);
    }

    /**
     * Modifica en tiempo de ejecución las variables de discos del sistema desde las variables de sistema en elfacade config
     *
     * @return void
     */
    public static function setFilesystemsInfo() {
        TenantTrait::GetVariablesSistemaTenant();
        config([
            'filesystems.disks.etl.url'                        => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.logos.root'                     => config('variables_sistema.RUTA_LOGOS_REPRESENTACIONES_GRAFICAS_ESTANDAR'),
            'filesystems.disks.logos.url'                      => config('variables_sistema.APP_URL') . config('variables_sistema.DO_API_URL') . '/storage',
            'filesystems.disks.representaciones_graficas.root' => config('variables_sistema.RUTA_DISCO_REPRESENTACIONES_GRAFICAS'),
            'filesystems.disks.representaciones_graficas.url'  => config('variables_sistema.APP_URL') . config('variables_sistema.DO_API_URL') . '/storage',
            'filesystems.disks.assetsOfes.url'                 => config('variables_sistema.APP_URL') . config('variables_sistema.DO_API_URL') . '/storage',
            'filesystems.disks.public.url'                     => config('variables_sistema.APP_URL') . config('variables_sistema.DO_API_URL') . '/storage',
            'filesystems.disks.ftpDhlExpress.root'             => config('variables_sistema_tenant.RUTA_DHL_EXPRESS_860502609'),
            'filesystems.disks.ftpOsram.root'                  => config('variables_sistema_tenant.RUTA_OSRAM_900058192')
        ]);
    }

    /**
     * Configuración dle servicio AWS SES en tiempo de ejecución.
     *
     * @param string $key Access Key de AWS
     * @param string $secret Secret Key de AWS
     * @param string $region Región de AWS
     * @return void
     */
    public static function servicioAwsSesDinamico($key, $secret, $region) {
        Config::set('services.ses', array(
            'key'    => $key,
            'secret' => $secret,
            'region' => $region
        ));

        Config::set('aws', array(
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
            'region' => $region,
            'version' => 'latest',
            'ua_append' => [
                'L5MOD/' . AwsServiceProvider::VERSION,
            ]
        ));

        config([
            'mail.driver' => 'ses'
        ]);
    }

    /**
     * Establece y configura en tiempo de ejecución, el canal a través del cual se enviará un correo electrónico
     *
     * @param EtlCabeceraDocumentosDaop/RepCabeceraDocumentoDaop $documento Instancia del documento a enviar por correo
     * @return array Array conteniendo el email remitente del correo y la configuración de AWS
     */
    public static function establecerCanalEnvio($documento) {
        if(
            (
                empty($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses) ||
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses == 'NO'
            )
        ) {
            //Configuracion de AWS
            $awsSesConfigurationSet = null;

            //Para los OFE que mantienen la configuracion del servicio de correo anterior a AWS
            if (
                !empty($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp) &&
                (
                    array_key_exists('driver', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp) || 
                    array_key_exists('from_email', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)
                )
            ) {
                // Establece el email del remitente del correo
                if(array_key_exists('from_email', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)) {
                    $emailRemite = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp['from_email'];
                } else {
                  $emailRemite = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;
                }

                // Verifica si existe conexión especial a un servidor SMTP del OFE
                if (array_key_exists('driver', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)) {
                    TenantSmtp::setSmtpConnection($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp);
                } else {
                    self::setMailInfo();
                }
            } else {
                // Establece el email del remitente del correo
                $emailRemite = (!empty(config('variables_sistema.MAIL_FROM_ADDRESS'))) ?  config('variables_sistema.MAIL_FROM_ADDRESS') : $documento->getCabeceraDocumentosDaop->getConfiguracionObligadoFacturarElectronicamente->ofe_correo;

                //Se va por el estandar
                DoTrait::setMailInfo();
            }
        } elseif(
            $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_envio_notificacion_amazon_ses == 'SI' &&
            !empty($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp)
        ) {
            $conexionOfeAws = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_smtp;
            self::servicioAwsSesDinamico($conexionOfeAws['AWS_ACCESS_KEY_ID'], $conexionOfeAws['AWS_SECRET_ACCESS_KEY'], $conexionOfeAws['AWS_REGION']);

            $emailRemite            = $conexionOfeAws['AWS_FROM_EMAIL'];
            $awsSesConfigurationSet = $conexionOfeAws['AWS_SES_CONFIGURATION_SET'];
        } else {
            self::servicioAwsSesDinamico(config('variables_sistema.AWS_ACCESS_KEY_ID'), config('variables_sistema.AWS_SECRET_ACCESS_KEY'), config('variables_sistema.AWS_REGION'));
            $emailRemite            = config('variables_sistema.AWS_FROM_EMAIL');
            $awsSesConfigurationSet = config('variables_sistema.AWS_SES_CONFIGURATION_SET');
        }

        return [
            'emailRemite'            => $emailRemite,
            'awsSesConfigurationSet' => $awsSesConfigurationSet
        ];
    }

    /**
     * Obtiene el consecutivo para un archivo zip.
     *
     * @param int $ofe_id ID del OFE relacionado con la transmisión de los documentos
     * @param int $user_id ID del usuario relacionado con el proceso de transmisión
     * @param string $tipoConsecutivo Tipo de consecutivo a obtener
     * @return string Consecutivo para el zip
     */
    public static function obtenerConsecutivoArchivo($ofe_id, $user_id, $tipoConsecutivo) {
        $columnaConsecutivo = 'cae_' . strtolower(str_replace('_', '', $tipoConsecutivo));
        $consecutivos = ConfiguracionConsecutivoArchivosEnviadosOfe::where('cae_anno', date('Y'))
            ->where('ofe_id', $ofe_id)
            ->where('estado', 'ACTIVO')
            ->lockForUpdate()
            ->first();

        if(!$consecutivos) {
            ConfiguracionConsecutivoArchivosEnviadosOfe::create([
                'ofe_id'            => $ofe_id,
                'emp_id'            => null,
                'cae_anno'          => date('Y'),
                'cae_fv'            => strtolower($tipoConsecutivo) == 'fv' ? 2 : 1,
                'cae_nc'            => strtolower($tipoConsecutivo) == 'nc' ? 2 : 1,
                'cae_nd'            => strtolower($tipoConsecutivo) == 'nd' ? 2 : 1,
                'cae_ds'            => strtolower($tipoConsecutivo) == 'ds' ? 2 : 1,
                'cae_dsnc'          => strtolower($tipoConsecutivo) == 'dsnc' ? 2 : 1,
                'cae_ar'            => strtolower($tipoConsecutivo) == 'ar' ? 2 : 1,
                'cae_ad'            => strtolower($tipoConsecutivo) == 'ad' ? 2 : 1,
                'cae_z'             => strtolower($tipoConsecutivo) == 'z' ? 2 : 1,
                'cae_nie'           => strtolower($tipoConsecutivo) == 'nie' ? 2 : 1,
                'cae_niae'          => strtolower($tipoConsecutivo) == 'niae' ? 2 : 1,
                'usuario_creacion'  => $user_id,
                'estado'            => 'ACTIVO',
            ]);
            $consecutivo = '00000001';
        } else {
            $consecutivo = str_pad($consecutivos->$columnaConsecutivo, 8, '0', STR_PAD_LEFT);
            $consecutivos->update([
                $columnaConsecutivo => ($consecutivos->$columnaConsecutivo + 1)
            ]);
        }

        return $consecutivo;
    }


    /**
     * Convierte un numero entero a su representación hexadecimal ajustado a la izquierda con ceros.
     *
     * @param integer | string $numero Numero a convertir
     * @return string Hexadecimal
     */
    public static function DecToHex ($numero) {
        return str_pad(dechex($numero), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Permite definir la base de datos a la que pertenece el usuario autenticado.
     *
     * @return string Base de datos del usuario autenticado
     */
    public function definirBaseDatosRutaDisco() {
        if (!empty(auth()->user()->bdd_id_rg))
            return str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', auth()->user()->getBaseDatosRg->bdd_nombre);
        else
            return str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', auth()->user()->getBaseDatos->bdd_nombre);
    }

    /**
     * Obtiene del disco un archivo relacionado con un documento electrónico.
     *
     * @param string $proceso Indica si el proceso corresponde a emisión o recepción
     * @param string $identificacionPrincipal Identificación del OFE o Empleador dependiendo del proceso
     * @param EtlCabeceraDocumentoDaop || RepCabeceraDocumentoDaop $documento Colección con información del registro de cabecera del documento electrónico
     * @param string $nombreArchivo Nombre del archivo almacenado en disco
     * @return string Contenido del archivo
     */
    public function obtenerArchivoDeDisco($proceso, $identificacionPrincipal, $documento, $nombreArchivo) {
        if($documento) {
            $fechaHoraDoc = explode(' ', $documento->fecha_creacion);
            $fechaDoc     = explode('-', $fechaHoraDoc[0]);
            $horaDoc      = explode(':', $fechaHoraDoc[1]);

            $ruta = config('variables_sistema.RUTA_DISCO_DOCUMENTOS_ELECTRONICOS') . '/' . 
                $this->definirBaseDatosRutaDisco() . '/' . $identificacionPrincipal . '/' . $proceso . '/' .
                $fechaDoc[0] . '/' . $fechaDoc[1]  . '/' . $fechaDoc[2] . '/' . 
                $horaDoc[0] . '/' . $horaDoc[1] . '/' . ($proceso == 'nomina' ? $documento->cdn_id : $documento->cdo_id);

            if(File::isFile($ruta . '/' . $nombreArchivo))
                return File::get($ruta . '/' . $nombreArchivo);
        }

        return '';
    }

    /**
     * Permite guardar en disco los archivos derivados de procesos realizados en el microservicio.
     *
     * @param string $ofeIdentificacion Identificación del OFE
     * @param EtlCabeceraDocumentoDaop | RepCabeceraDocumentoDaop $documento Registro de cabecera creado para el documento
     * @param string $proceso Indica si el proceso corresponde a emisión o recepción
     * @param string $prefijoArchivo Prefijo que se aplica al nombre del archivo que se creará
     * @param string $extensionArchivo Extensión del archivo que se creará
     * @param string $contenidoArchivo Contenido del archivo en base64
     * @param string $nombreArchivo Nombre con el que se debe almacenar el archivo
     * @return string Nombre del archivo almacenado en disco
     */
    public function guardarArchivoEnDisco($ofeIdentificacion, $documento, $proceso, $prefijoArchivo, $extensionArchivo, $contenidoArchivo, $nombreArchivo = null) {
        if($documento) {
            $fechaHoraDoc = explode(' ', $documento->fecha_creacion);
            $fechaDoc     = explode('-', $fechaHoraDoc[0]);
            $horaDoc      = explode(':', $fechaHoraDoc[1]);
            
            $directorios = [
                config('variables_sistema.RUTA_DISCO_DOCUMENTOS_ELECTRONICOS'),
                $this->definirBaseDatosRutaDisco(),
                $ofeIdentificacion,
                $proceso,
                $fechaDoc[0],
                $fechaDoc[1],
                $fechaDoc[2],
                $horaDoc[0],
                $horaDoc[1],
                $proceso == 'nomina' ? $documento->cdn_id : $documento->cdo_id
            ];

            $ruta = '';
            foreach ($directorios as $directorio) {
                $ruta .= (!empty($ruta)) ? '/' . $directorio : $directorio;
                $this->crearDirectorio($ruta, config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), 0755);
            }

            if($proceso == 'nomina') {
                $prefijo     = 'cdn_prefijo';
                $consecutivo = 'cdn_consecutivo';
            } else {
                $prefijo     = 'rfa_prefijo';
                $consecutivo = 'cdo_consecutivo';
            }

            if(empty($nombreArchivo))
                $nombreArchivo = $prefijoArchivo . '_' . trim($documento->$prefijo) . $documento->$consecutivo . '.' . $extensionArchivo;

            if($prefijoArchivo == 'validacionDian' && File::exists($ruta . '/' . $nombreArchivo)) {
                $uuid              = Uuid::uuid4()->toString();
                $nuevoNombre       = $prefijoArchivo . '_' . trim($documento->rfa_prefijo) . $documento->cdo_consecutivo . '_' . date('YmdHis') . '_' . $uuid . '.' . $extensionArchivo;
                $contenidoAnterior = File::get($ruta . '/' . $nombreArchivo);
                File::put($ruta . '/' . $nuevoNombre, $contenidoAnterior);
            }

            File::put($ruta . '/' . $nombreArchivo, base64_decode($contenidoArchivo));
            chown($ruta . '/' . $nombreArchivo, config('variables_sistema.USUARIO_SO'));
            chgrp($ruta . '/' . $nombreArchivo, config('variables_sistema.GRUPO_SO')); 
            chmod($ruta . '/' . $nombreArchivo, 0755);

            return $nombreArchivo;
        }

        return '';
    }

    /**
     * Asigna permiso a los directorios y archivos de un directorio
     *
     * @param string $ruta      ruta del directorio
     * @param string $usuarioSo Usuario sistema operativo asignar al directorio
     * @param string $grupoSo   Grupo sistema operativo asignar al directorio
     * @param string $permiso   permiso asignar al directorio
     * @return void
     */
    public function crearDirectorio($ruta, $usuarioSo, $grupoSo, $permiso) {
        if (!File::isDirectory($ruta)) {
            File::makeDirectory($ruta, $permiso, true, true);
            chown($ruta . '/', $usuarioSo);
            chgrp($ruta . '/', $grupoSo); 
            chmod($ruta . '/', $permiso);
        }
    }

    /**
     * Elimina caracteres BOM de los XML o PDF si los tiene.
     *
     * @param string $contenidoArchivo Contenido del archivo XML|PDF
     * @param string $contenidoArchivo
     */
    public function eliminarCaracteresBOM($contenidoArchivo) {
        if(strtolower(substr(bin2hex($contenidoArchivo), 0, 6)) === 'efbbbf')
            return substr($contenidoArchivo, 3);
        else
            return $contenidoArchivo;
    }

    /**
     * Reinica la conexion de la base de datos.
     *
     * @param $baseDatos Información de la base de datos
     * @return void
     */
    private function reiniciarConexion($baseDatos) {
        DB::disconnect('conexion01');

        // Se establece la conexión con la base de datos
        TenantDatabase::setTenantConnection(
            'conexion01',
            $baseDatos->bdd_host,
            $baseDatos->bdd_nombre,
            $baseDatos->bdd_usuario,
            $baseDatos->bdd_password
        );
    }

    /**
     * Guarda en el disco del servidor el XML de un evento obtenido en una consulta a la DIAN.
     *
     * @param EtlCabeceraDocumentoDaop|RepCabeceraDocumentoDaop $classCabecera Instancia de la clase de cabecera en emisión o recepción
     * @param integer $cdo_id ID del documento
     * @param string $ofeIdentificacion Identificación del OFE
     * @param array $estadoInformacionAdicional Información adicional del estado
     * @param string $rptaDian Respuesta de la DIAN a la consulta del XML del evento
     * @param string $metodoWsDian Método del WS de la DIAN utilizado para la consulta 
     * @return void
     */
    public function guardarXmlEvento($classCabecera, int $cdo_id, string $ofeIdentificacion, array $estadoInformacionAdicional, string $rptaDian, string $metodoWsDian): void {
        $xmlString = $this->obtenerXmlBytesBase64($rptaDian, $metodoWsDian);
        if (!$xmlString['error'] && !empty($xmlString['string'])) {
            $documento     = $classCabecera::select(['cdo_id', 'rfa_prefijo', 'cdo_consecutivo', 'fecha_creacion'])->find($cdo_id);
            $nombreArchivo = explode('.', $estadoInformacionAdicional['est_xml']);
            $nombreArchivo = explode('_', $nombreArchivo[0]);

            $this->guardarArchivoEnDisco(
                $ofeIdentificacion,
                $documento,
                $this->proceso,
                $nombreArchivo[0],
                'xml',
                $xmlString['string'],
                $estadoInformacionAdicional['est_xml']
            );
        }
    }

    /**
     * Procesa la respuesta de la DIAN para intentar obtener la cadena en base64 que corresponde a un XML.
     *
     * @param string $rptaDian Respuesta de la DIAN
     * @param string $metodoWsDian Método utilizado en la consulta al servicio web de la DIAN
     * @return array Array con un índice 'error' de tipo bool que indica si el proceso tuvo errores, y una índice 'string' del tipo string para retornar el XML o mensaje de error retornado por la DIAN
     */
    public function obtenerXmlBytesBase64(string $rptaDian, string $metodoWsDian): array {
        libxml_use_internal_errors(true);

        $oXML         = new \SimpleXMLElement($rptaDian);
        $vNameSpaces  = $oXML->getNamespaces(true);
        $nodoResponse = $metodoWsDian . 'Response';
        $nodoResult   = $metodoWsDian . 'Result';

        $oBody = $oXML->children($vNameSpaces['s'])
            ->Body
            ->children($vNameSpaces[''])
            ->$nodoResponse
            ->children($vNameSpaces[''])
            ->$nodoResult
            ->children($vNameSpaces['b']);

        if(
            (isset($oBody->Code) && $oBody->Code == '100') &&
            (
                (isset($oBody->XmlBase64Bytes) && $oBody->XmlBase64Bytes != '') ||
                (isset($oBody->XmlBytesBase64) && $oBody->XmlBytesBase64 != '')
            )
        ) {
            // Valida si el xml recibido en la respuesta contiene un string XML válido para retornarlo
            $xmlString = base64_encode($this->eliminarCaracteresBOM(base64_decode($oBody->XmlBase64Bytes ? $oBody->XmlBase64Bytes : $oBody->XmlBytesBase64)));
            $xmlObject = simplexml_load_string(base64_decode($xmlString));
            if ($xmlObject !== false)
                return [
                    'error'  => false,
                    'string' => $xmlString
                ];
            else
            return [
                'error'  => true,
                'string' => null
            ];
        } else {
            if(isset($oBody->Code) && $oBody->Code != '100' && isset($oBody->Message) && !empty($oBody->Message))
                return [
                    'error'  => true,
                    'string' => $oBody->Message
                ];
            else
                return [
                    'error'  => true,
                    'string' => null
            ];
        }
    }
}

