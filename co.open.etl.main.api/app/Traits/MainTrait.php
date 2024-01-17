<?php

namespace App\Traits;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Exception\ClientException;
use App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatos;
use App\Repositories\Emision\EtlCabeceraDocumentoRepository;
use App\Repositories\Recepcion\RepCabeceraDocumentoRepository;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\NominaElectronica\DsnEstadosDocumentosDaop\DsnEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepEstadosDocumentosDaop\RepEstadoDocumentoDaop;
use App\Http\Modulos\Radian\Documentos\RadianEstadosDocumentosDaop\RadianEstadoDocumentoDaop;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

trait MainTrait {
    /**
     * Propiedad para almacenar las columnas reservadas a nivel de cabecera.
     *
     * @var Array
     */
    public static $columnasDefault = [
        'TIPO DOCUMENTO',
        'TIPO OPERACION',
        'NIT OFE',
        'NIT ADQUIRENTE',
        'NIT AUTORIZADO',
        'RESOLUCION FACTURACION',
        'PREFIJO',
        'CONSECUTIVO',
        'FECHA',
        'HORA',
        'FECHA VENCIMIENTO',
        'OBSERVACION',
        'COD FORMA DE PAGO',
        'COD MEDIO DE PAGO',
        'FECHA VENCIMIENTO PAGO',
        'IDENTIFICADOR PAGO',
        'COD MONEDA',
        'COD MONEDA EXTRANJERA',
        'TRM',
        'FECHA TRM',
        'ENVIAR A LA DIAN EN MONEDA EXTRANJERA',
        'REPRESENTACION GRAFICA DOCUMENTO',
        'REPRESENTACION GRAFICA ACUSE DE RECIBO',
    ];

    /**
     * Propiedad para almacenar las columnas reservadas a nivel de ítem para Documento Electrónico.
     *
     * @var Array
     */
    public static $columnasItemDefault = [
        'TIPO ITEM',
        'COD CLASIFICACION PRODUCTO',
        'COD PRODUCTO',
        'DESCRIPCION 1',
        'DESCRIPCION 2',
        'DESCRIPCION 3',
        'NOTAS',
        'CANTIDAD',
        'CANTIDAD PAQUETE',
        'COD UNIDAD MEDIDA',
        'VALOR UNITARIO',
        'TOTAL',
        'VALOR UNITARIO MONEDA EXTRANJERA',
        'TOTAL MONEDA EXTRANJERA',
        'MUESTRA COMERCIAL',
        'COD PRECIO REFERENCIA',
        'VALOR MUESTRA',
        'VALOR MUESTRA MONEDA EXTRANJERA',
        'DATOS TECNICOS',
        'NIT MANDATARIO',
        'COD TIPO DOCUMENTO MANDATARIO',
        ////////////////////////////////////
        'BASE IVA',
        '% IVA',
        'VALOR IVA',
        'BASE IVA MONEDA EXTRANJERA',
        'VALOR IVA MONEDA EXTRANJERA',
        'MOTIVO EXENCION IVA',
        'BASE IMPUESTO CONSUMO',
        '% IMPUESTO CONSUMO',
        'VALOR IMPUESTO CONSUMO',
        'BASE IMPUESTO CONSUMO MONEDA EXTRANJERA',
        'VALOR IMPUESTO CONSUMO MONEDA EXTRANJERA',
        'BASE ICA',
        '% ICA',
        'VALOR ICA',
        'BASE ICA MONEDA EXTRANJERA',
        'VALOR ICA MONEDA EXTRANJERA',
    ];

    /**
     * Propiedad para almacenar las columnas reservadas a nivel de ítem para Documento Soporte.
     *
     * @var Array
     */
    public static $columnasItemDefaultDS = [
        'TIPO ITEM',
        'COD CLASIFICACION PRODUCTO',
        'COD PRODUCTO',
        'DESCRIPCION 1',
        'DESCRIPCION 2',
        'DESCRIPCION 3',
        'NOTAS',
        'CANTIDAD',
        'CANTIDAD PAQUETE',
        'COD UNIDAD MEDIDA',
        'VALOR UNITARIO',
        'TOTAL',
        'VALOR UNITARIO MONEDA EXTRANJERA',
        'TOTAL MONEDA EXTRANJERA',
        'MUESTRA COMERCIAL',
        'COD PRECIO REFERENCIA',
        'VALOR MUESTRA',
        'VALOR MUESTRA MONEDA EXTRANJERA',
        'DATOS TECNICOS',
        'COD FORMA GENERACION Y TRANSMISION',
        ////////////////////////////////////
        'BASE IVA',
        '% IVA',
        'VALOR IVA',
        'BASE IVA MONEDA EXTRANJERA',
        'VALOR IVA MONEDA EXTRANJERA',
        'MOTIVO EXENCION IVA',
    ];

    /**
     * Propiedad para almacenar las columnas reservadas adicionales.
     *
     * @var Array
     */
    public static $columnasAdicionalesDefault = [
        'ID PERSONALIZADO',
        'ANTICIPO IDENTIFICADO PAGO',
        'ANTICIPO VALOR',
        'ANTICIPO VALOR MONEDA EXTRANJERA',
        'ANTICIPO FECHA RECIBIO',
        'CARGO DESCRIPCION',
        'CARGO PORCENTAJE',
        'CARGO BASE',
        'CARGO VALOR',
        'CARGO BASE MONEDA EXTRANJERA',
        'CARGO VALOR MONEDA EXTRANJERA',
        'DESCUENTO CODIGO',
        'DESCUENTO DESCRIPCION',
        'DESCUENTO PORCENTAJE',
        'DESCUENTO BASE',
        'DESCUENTO VALOR',
        'DESCUENTO BASE MONEDA EXTRANJERA',
        'DESCUENTO VALOR MONEDA EXTRANJERA',
        'RETENCION SUGERIDA RETEICA DESCRIPCION',
        'RETENCION SUGERIDA RETEICA PORCENTAJE',
        'RETENCION SUGERIDA RETEICA BASE',
        'RETENCION SUGERIDA RETEICA VALOR',
        'RETENCION SUGERIDA RETEICA BASE MONEDA EXTRANJERA',
        'RETENCION SUGERIDA RETEICA VALOR MONEDA EXTRANJERA',
        'RETENCION SUGERIDA RETEIVA DESCRIPCION',
        'RETENCION SUGERIDA RETEIVA PORCENTAJE',
        'RETENCION SUGERIDA RETEIVA BASE',
        'RETENCION SUGERIDA RETEIVA VALOR',
        'RETENCION SUGERIDA RETEIVA BASE MONEDA EXTRANJERA',
        'RETENCION SUGERIDA RETEIVA VALOR MONEDA EXTRANJERA',
        'RETENCION SUGERIDA RETEFUENTE DESCRIPCION',
        'RETENCION SUGERIDA RETEFUENTE PORCENTAJE',
        'RETENCION SUGERIDA RETEFUENTE BASE',
        'RETENCION SUGERIDA RETEFUENTE VALOR',
        'RETENCION SUGERIDA RETEFUENTE BASE MONEDA EXTRANJERA',
        'RETENCION SUGERIDA RETEFUENTE VALOR MONEDA EXTRANJERA'
    ];

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
        config([
            'filesystems.disks.public.url'                        => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.etl.url'                           => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.logos.root'                        => config('variables_sistema.RUTA_LOGOS_REPRESENTACIONES_GRAFICAS_ESTANDAR'),
            'filesystems.disks.logos.url'                         => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.assetsOfes.url'                    => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.adjuntos.url'                      => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.ftpOfes.url'                       => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.representaciones_graficas.root'    => config('variables_sistema.RUTA_DISCO_REPRESENTACIONES_GRAFICAS'),
            'filesystems.disks.representaciones_graficas.url'     => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.encriptados.url'                   => config('variables_sistema.APP_URL') . config('variables_sistema.MAIN_API_URL') . '/storage',
            'filesystems.disks.documentos_anexos_emision.root'    => config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_EMISION'),
            'filesystems.disks.documentos_anexos_recepcion.root ' => config('variables_sistema.RUTA_DOCUMENTOS_ANEXOS_RECEPCION')
        ]);
    }

    /**
     * Crea un disco dinámico en tiempo de ejecución.
     * 
     * Permite hacer uso de todos los métodos del Storage para rutas que no estan configuradas como discos con el Filesystems
     *
     * @param string $nombreDisco Nombre del disco
     * @param string $ruta Ruta absoluta a la carpeta principal del disco
     * @return void
     */
    public static function crearDiscoDinamico($nombreDisco, $ruta) {
        Config::set('filesystems.disks.' . $nombreDisco, array(
            'driver' => 'local',
            'root' => $ruta,
            'url' => null,
            'visibility' => 'private',
        ));
    }

    /**
     * Genera un token para poder efectuar una peticion a un microservicio.
     *
     * @return string
     */
    private static function getToken() {
        $user = auth()->user();
        if ($user)
            return auth()->tokenById($user->usu_id);
        return '';
    }

    /**
     * Construye un cliente de Guzzle para consumir los microservicios
     *
     * @param string $URI
     * @return Client
     */
    private static function getCliente(string $URI, $contentType) {
        return new Client([
            'base_uri' => $URI,
            'headers' => [
                'Content-Type'     => $contentType,
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept'           => 'application/json'
            ]
        ]);
    }

    /**
     * Realiza una petición a un microservicio
     *
     * @param string $microservicio Microservicio a consumir
     * @param string $metodoHttp Médoto HTTP del endpoint a consumir
     * @param string $endpoint Endpoint a consumir
     * @param array $parametros Array de parámetros a enviar
     * @param string $parametros Array de parámetros a enviar
     * @return array
     */
    public static function peticionMicroservicio($microservicio, $metodoHttp, $endpoint, $parametros, $tipoParams = 'json') {
        try {
            $arrParams = [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::getToken()
                ],
                'http_errors' => false
            ];

            switch($tipoParams){
                case('json'):
                    $arrParams['json'] = $parametros;
                    $contentType = 'application/json';
                    break;
                case('form_params'):
                    $arrParams['form_params'] = $parametros;
                    $contentType = 'application/json';
                    break;
                case('multipart'):
                    $arrParams['multipart'] = $parametros;
                    $contentType = 'multipart/form-data';
                    break;
            }

            // Accede al microservicio requerido para realizar la petición
            $peticionMicroservicio = self::getCliente(config('variables_sistema.APP_URL'), $contentType)
                ->request(
                    $metodoHttp,
                    config('variables_sistema.' . strtoupper($microservicio) . '_API_URL') . $endpoint,
                    $arrParams
                );

            $status    = $peticionMicroservicio->getStatusCode();
            $respuesta = json_decode((string)$peticionMicroservicio->getBody()->getContents(), true);

            if($status == '401') {
                return [
                    'message' => 'Error de Autenticacion en Microservicio ' . strtoupper($microservicio),
                    'errors'  => ['No fue posible autenticarse en el microservicio ' . strtoupper($microservicio)],
                    'status'  => 409
                ];
            } elseif($status != '200' && $status != '201' && $status != '401') {
                return [
                    'message' => $respuesta['message'],
                    'errors'  => $respuesta['errors'],
                    'status'  => $status
                ];
            }

            $respuesta['status']  = $status;

            return $respuesta;
        } catch (ClientException $e) {
            $response = $e->getResponse();

            if($e->getCode() == '401') {
                return [
                    'message' => 'Error de Autenticacion en Microservicio ' . strtoupper($microservicio),
                    'errors'  => ['No fue posible autenticarse en el microservicio ' . strtoupper($microservicio)],
                    'status'  => 409
                ];
            } else {
                return[
                    'message' => $e->getMessage(),
                    'errors'  => [$response->getBody()->getContents()],
                    'status'  => $e->getCode()
                ];
            }
        }
    }

    /**
     * Verifica que una base de datos aisgnada como bdd_id_rg exista.
     *
     * @param  \Illuminate\Http\Request $request
     * @param string $accion Acción que esta ejecutando 'crear o editar'
     * @return array|null Array con error indicando que no existe o null si la base de datos existe
     */
    public static function existeBddIdRg(Request $request, $accion) {
        if($request->has('bdd_id_rg') && !empty($request->bdd_id_rg)) {
            $bdExists = AuthBaseDatos::where('estado', 'ACTIVO')
                    ->where('bdd_id', $request->bdd_id_rg)
                    ->first();

            if(!$bdExists) {
                return [
                    'message' => "Error al {$accion} el registro",
                    'errors' => ['La base de datos seleccionada no existe o se encuentra inactiva']
                ];
            }
        }

        return null;
    }

    /**
     * Verifica que el parámetro bdd_id_rg corresponda con el mismo parámetro del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request $request
     * @param \App\Http\Models\User $authUser Usuario autenticado
     * @param string $accion Acción que esta ejecutando 'crear o editar'
     * @return array|null Array con error indicando que no existe o null si la base de datos existe
     */
    public static function bddIdRgCorresponde(Request $request, $authUser, $accion) {
        if(!empty($authUser->bdd_id_rg) && $request->has('bdd_id_rg') && !empty($request->bdd_id_rg) && $authUser->bdd_id_rg != $request->bdd_id_rg) {
            return [
                'message' => "Error al {$accion} el registro",
                'errors' => ['La base de datos seleccionada no corresponde con la base de datos del usuario autenticado']
            ];
        }

        return null;
    }

    /**
     * Permite validar el acceso del usuario de mesa de ayuda, en los métodos en donde se implemente
     *
     * @return array Array con la información de retorno del error del permiso cuando el rol de usuario es 'usuarioma', o array vacio en caso contrario
     */
    public static function validaAccesoUsuarioMA() {
        $userAuth = auth()->user();

        if($userAuth->esUsuarioMA()) {
            return [
                'message' => 'Error al Procesar la Petición',
                'errors'  => 'El rol Usuario Mesa de Ayuda no puede ejecutar la acción solicitada'
            ];
        } else {
            return [];
        }
    }

    /**
     * Permite definir la base de datos a la que pertenece el usuario autenticado.
     *
     * @return string Base de datos del usuario autenticado
     */
    public static function definirBaseDatosRutaDisco() {
        if (!empty(auth()->user()->bdd_id_rg))
            return str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', auth()->user()->getBaseDatosRg->bdd_nombre);
        else
            return str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', auth()->user()->getBaseDatos->bdd_nombre);
    }

    /**
     * Permite obtener el registro correspodiente a un estado de un documento electrónico en los procesos de emisión o recepción.
     *
     * @param string $proceso Proceso relacionado con la consulta, emisión o recepción
     * @param int $cdoId ID del documento electrónico
     * @param string $nombreEstado Estado del documento electrónico
     * @param array $resultadoEstado Resultados de estado del documento electrónico EXITOSO|FALLIDO
     * @return EtlEstadosDocumentoDaop/RepEstadoDocumentoDaop
     */
    public static function obtenerEstadoDocumento(string $proceso, int $cdoId, string $nombreEstado, array $resultadoEstado = ['EXITOSO']) {
        $idCabecera = 'cdo_id';
        if($proceso == 'emision') {
            $classEstados = EtlEstadosDocumentoDaop::class;
        }elseif($proceso == 'nomina') {
            $classEstados = DsnEstadoDocumentoDaop::class;
            $idCabecera = 'cdn_id';
        }elseif($proceso == 'radian') {
            $classEstados = RadianEstadoDocumentoDaop::class; 
        } else {
            $classEstados = RepEstadoDocumentoDaop::class;
        }

        $estado = $classEstados::select(['est_id', $idCabecera, 'est_informacion_adicional'])
            ->where($idCabecera, $cdoId)
            ->where('est_estado', $nombreEstado)
            ->whereIn('est_resultado', $resultadoEstado)
            ->where('est_ejecucion', 'FINALIZADO')
            ->orderBy('est_id', 'desc')
            ->first();

        if(!$estado && $proceso == 'emision') {
            $emisionCabeceraRepository = new EtlCabeceraDocumentoRepository;
            $docFat = $emisionCabeceraRepository->consultarDocumentoFatByCdoId($cdoId);

            if($docFat) {
                $particion = Carbon::parse($docFat->cdo_fecha_validacion_dian)->format('Ym');
                
                $tblEstados = new EtlEstadosDocumentoDaop();
                $tblEstados->setTable('etl_estados_documentos_' . $particion);
                $estado = $tblEstados->select(['est_id', $idCabecera, 'est_informacion_adicional'])
                    ->where($idCabecera, $cdoId)
                    ->where('est_estado', $nombreEstado)
                    ->whereIn('est_resultado', $resultadoEstado)
                    ->where('est_ejecucion', 'FINALIZADO')
                    ->orderBy('est_id', 'desc')
                    ->first();
            }
        } elseif(!$estado && $proceso == 'recepcion') {
            $recepcionCabeceraRepository = new RepCabeceraDocumentoRepository;
            $docFat = $recepcionCabeceraRepository->consultarDocumentoFatByCdoId($cdoId);

            if($docFat) {
                $particion = Carbon::parse($docFat->cdo_fecha)->format('Ym');
                
                $tblEstados = new RepEstadoDocumentoDaop();
                $tblEstados->setTable('rep_estados_documentos_' . $particion);
                $estado = $tblEstados->select(['est_id', $idCabecera, 'est_informacion_adicional'])
                    ->where($idCabecera, $cdoId)
                    ->where('est_estado', $nombreEstado)
                    ->whereIn('est_resultado', $resultadoEstado)
                    ->where('est_ejecucion', 'FINALIZADO')
                    ->orderBy('est_id', 'desc')
                    ->first();
            }
        }

        return $estado;
    }

    /**
     * Obtiene del disco un archivo relacionado con un documento electrónico.
     *
     * @param string $proceso Indica si el proceso corresponde a emisión o recepción
     * @param string $identificacionPrincipal Identificacion principal OFE|Empleador
     * @param EtlCabeceraDocumentoDaop|RepCabeceraDocumentoDaop|DsnEstadoDocumentoDaop $documento Colección con información del registro de cabecera del documento electrónico
     * @param string $nombreArchivo Nombre del archivo almacenado en disco
     * @return string Contenido del archivo
     */
    public static function obtenerArchivoDeDisco($proceso, $identificacionPrincipal, $documento, $nombreArchivo) {
        $idCabecera = ($proceso == 'nomina') ? 'cdn_id' : 'cdo_id';

        if($documento) {
            $fechaHoraDoc = explode(' ', $documento->fecha_creacion);
            $fechaDoc     = explode('-', $fechaHoraDoc[0]);
            $horaDoc      = explode(':', $fechaHoraDoc[1]);

            $ruta = config('variables_sistema.RUTA_DISCO_DOCUMENTOS_ELECTRONICOS') . '/' . 
                self::definirBaseDatosRutaDisco() . '/' . $identificacionPrincipal . '/' . $proceso . '/' .
                $fechaDoc[0] . '/' . $fechaDoc[1]  . '/' . $fechaDoc[2] . '/' . 
                $horaDoc[0] . '/' . $horaDoc[1] . '/' . $documento->{$idCabecera};

            if(File::isFile($ruta . '/' . $nombreArchivo))
                return File::get($ruta . '/' . $nombreArchivo);
        }

        return '';
    }

    /**
     * Permite guardar en disco los archivos derivados de procesos realizados en el microservicio.
     *
     * @param string $ofeIdentificacion Identificación del OFE
     * @param EtlCabeceraDocumentoDaop|RepCabeceraDocumentoDaop $documento Registro de cabecera creado para el documento
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
                $documento->cdo_id
            ];

            $ruta    = '';
            foreach ($directorios as $directorio) {
                $ruta .= (!empty($ruta)) ? '/' . $directorio : $directorio;
                self::crearDirectorio($ruta, config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), 0755);
            }

            if(empty($nombreArchivo))
                $nombreArchivo = $prefijoArchivo . '_' . trim($documento->rfa_prefijo) . $documento->cdo_consecutivo . '.' . $extensionArchivo;

            if($prefijoArchivo == 'validacionDian' && File::exists($ruta . '/' . $nombreArchivo)) {
                $uuid              = Uuid::uuid4()->toString();
                $nuevoNombre       = $prefijoArchivo . '_' . trim($cabecera->rfa_prefijo) . $cabecera->cdo_consecutivo . '_' . date('YmdHis') . '_' . $uuid . '.' . $extensionArchivo;
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
    public static function crearDirectorio($ruta, $usuarioSo, $grupoSo, $permiso) {
        if (!File::isDirectory($ruta)) {
            File::makeDirectory($ruta, $permiso, true, true);
            chown($ruta . '/', $usuarioSo);
            chgrp($ruta . '/', $grupoSo); 
            chmod($ruta . '/', $permiso);
        }
    }

    /**
     * Aplica formato numérico a un valor.
     *
     * @param float $valor Valor a formatear
     * @param boolean $calcular Indica si se deben calcular la cantidad de decimales para el formato,
     *                false no se calcula y se aplica redondeo a dos decimales,
     *                true se aplica el redondeo dependiendo de la cantidad de decimales del numero
     * @return float $valor Valor formateado
     */
    public static function formatearValor($valor, $calcular=false){
        if ($calcular == false) {
            return number_format($valor, 2, '.', '');
        } else {
            $valor = $valor + 0; //eliminando ceros a la izquierda de los decimales
            $parteDecimal = explode(".", $valor);
            $decimales = (isset($parteDecimal[1]) && $parteDecimal[1] > 0) ? strlen($parteDecimal[1]) : 2;
            return number_format($valor, $decimales, '.', '');
        }
    }
}
