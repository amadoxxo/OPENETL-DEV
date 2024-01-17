<?php
namespace App\Traits;

use Validator;
use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Models\AuthBaseDatos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use openEtl\Tenant\Traits\TenantTrait;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\DataInputWorker\DataBuilder;
use openEtl\Main\Traits\FechaVigenciaValidations;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\Parametros\Unidades\ParametrosUnidad;
use App\Http\Modulos\Parametros\Mandatos\ParametrosMandato;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPago;
use App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPago;
use App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostal;
use App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacion;
use App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumento;
use App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuento;
use App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargo;
use App\Http\Modulos\Parametros\PreciosReferencia\ParametrosPrecioReferencia;
use App\Http\Modulos\Parametros\CondicionesEntrega\ParametrosCondicionEntrega;
use App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccion;
use App\Http\Modulos\Parametros\SectorTransporte\Remesa\ParametrosTransporteRemesa;
use App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuento;
use App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProducto;
use App\Http\Modulos\Parametros\SectorTransporte\Registro\ParametrosTransporteRegistro;
use App\Http\Modulos\Parametros\NominaElectronica\NominaPeriodo\ParametrosNominaPeriodo;
use App\Http\Modulos\Parametros\NominaElectronica\NominaTipoNota\ParametrosNominaTipoNota;
use App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronico;
use App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmision;
use App\Http\Modulos\Parametros\SectorSalud\DocumentoReferenciado\ParametrosSaludDocumentoReferenciado;
use App\Http\Modulos\Parametros\NominaElectronica\NominaTipoIncapacidad\ParametrosNominaTipoIncapacidad;
use App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\ParametrosNominaTipoHoraExtraRecargo;

trait DiTrait {
    use FechaVigenciaValidations;

    /**
     * Mensaje personalizado para expresión regular que valida que campo sea un número positivo.
     *
     * @var array
     */
    private $mensajeNumerosPositivos = [
        'regex'                 => 'El campo :attribute no puede ser negativo o contener mas de la cantidad numerica o decimales permitidos.',
        'rfa_prefijo.regex'     => 'El campo :attribute contiene caracteres no permitidos.',
        'cdo_consecutivo.regex' => 'El campo :attribute contiene caracteres no permitidos.'
    ];

    /**
     * Mensaje personalizado para expresión regular que valida que campo sea un número positivo.
     *
     * @var array
     */
    private $mensajeNumerosPositivosRecepcion = [
        'regex' => 'El campo :attribute no puede ser negativo.',
    ];

    /**
     * Modifica en tiempo de ejecución las variables de conexión al servidor de correo desde las variables de sistema en el facade config.
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
     * Modifica en tiempo de ejecución las variables de discos del sistema desde las variables de sistema en elfacade config.
     *
     * @return void
     */
    public static function setFilesystemsInfo() {
        TenantTrait::GetVariablesSistemaTenant();
        config([
            'filesystems.disks.public.url'         => config('variables_sistema.APP_URL') . config('variables_sistema.DI_API_URL') . '/storage',
            'filesystems.disks.etl.url'            => config('variables_sistema.APP_URL') . config('variables_sistema.DI_API_URL') . '/storage',
            'filesystems.disks.encriptados.url'    => config('variables_sistema.APP_URL') . config('variables_sistema.DI_API_URL') . '/storage',
            'filesystems.disks.ftpDhlExpress.root' => config('variables_sistema_tenant.RUTA_DHL_EXPRESS_860502609'),
            'filesystems.disks.ftpPaisagro.root'   => config('variables_sistema_tenant.RUTA_PAISAGRO_830119738'),
            'filesystems.disks.ftpOsram.root'      => config('variables_sistema_tenant.RUTA_OSRAM_900058192'),
            'filesystems.disks.logos.root'         => config('variables_sistema.RUTA_LOGOS_REPRESENTACIONES_GRAFICAS_ESTANDAR'),
            'filesystems.disks.logos.url'          => config('variables_sistema.APP_URL') . config('variables_sistema.DI_API_URL') . '/storage'
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
     * Define la base de datos a la que pertenece el usuario autenticado.
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
     * @param string $identificacionPrincipal Identificacion principal OFE|Empleador
     * @param EtlCabeceraDocumentoDaop|RepCabeceraDocumentoDaop|DsnEstadoDocumentoDaop $documento Colección con información del registro de cabecera del documento electrónico
     * @param string $nombreArchivo Nombre del archivo almacenado en disco
     * @return string Contenido del archivo
     */
    public function obtenerArchivoDeDisco($proceso, $identificacionPrincipal, $documento, $nombreArchivo) {
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
     * Permite guardar en disco los archivos relacionados con el certificado de mandato y la RG del documento cuando es el OFE quíen los envía en cdo_informacion_adicional.
     *
     * @param string $ofeIdentificacion Identificación del OFE
     * @param EtlCabeceraDocumentoDaop $cabecera Registro de cabecera creado para el documento
     * @param array $document Array con información del documento procesado
     * @param string $proceso Indica si el proceso corresponde a emisión o recepción
     * @return array Array que contiene el nombre del archivo del certificado mandato y/o el nombre del archivo de la RG enviada por el OFE
     */
    public function guardarDocumentosCertificadoMandatoPdfbase64($ofeIdentificacion, $cabecera, &$document, $proceso) {
        if($cabecera) {
            $fechaHoraDoc = explode(' ', $cabecera->fecha_creacion);
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
                $cabecera->cdo_id
            ];

            // Se verifica si en información adicional viene información sobre el Certificado de Mandato para guardarlo en Disco
            if(
                $cabecera &&
                isset($document['dad']) &&
                isset($document['dad']['cdo_informacion_adicional']) &&
                array_key_exists('dad', $document) &&
                array_key_exists('cdo_informacion_adicional', $document['dad']) &&
                array_key_exists('certificado_mandato', $document['dad']['cdo_informacion_adicional']) &&
                $document['dad']['cdo_informacion_adicional']['certificado_mandato'] != ''
            ) {
                $nombreArchivoCertificado = 'certificado_' . trim($cabecera->rfa_prefijo) . $cabecera->cdo_consecutivo . '.pdf';

                $ruta = '';
                foreach ($directorios as $directorio) {
                    $ruta .= (!empty($ruta)) ? '/' . $directorio : $directorio;
                    $this->crearDirectorio($ruta, config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), 0755);
                }
                    
                File::put($ruta . '/' . $nombreArchivoCertificado, base64_decode($document['dad']['cdo_informacion_adicional']['certificado_mandato']));
                chown($ruta . '/' . $nombreArchivoCertificado, config('variables_sistema.USUARIO_SO'));
                chgrp($ruta . '/' . $nombreArchivoCertificado, config('variables_sistema.GRUPO_SO')); 
                chmod($ruta . '/' . $nombreArchivoCertificado, 0755);

                $document['dad']['cdo_informacion_adicional']['certificado_mandato'] = $nombreArchivoCertificado;
            }

            // Se verifica si en información adicional el OFE envió información sobre la RG del documento para guardarla en Disco
            if(
                $cabecera &&
                isset($document['dad']) &&
                isset($document['dad']['cdo_informacion_adicional']) &&
                array_key_exists('dad', $document) &&
                array_key_exists('cdo_informacion_adicional', $document['dad']) &&
                array_key_exists('pdf_base64', $document['dad']['cdo_informacion_adicional']) &&
                $document['dad']['cdo_informacion_adicional']['pdf_base64'] != ''
            ) {
                $nombreArchivoRg = 'rg_' . trim($cabecera->rfa_prefijo) . $cabecera->cdo_consecutivo . '.pdf';

                $ruta = '';
                foreach ($directorios as $directorio) {
                    $ruta .= (!empty($ruta)) ? '/' . $directorio : $directorio;
                    $this->crearDirectorio($ruta, config('variables_sistema.USUARIO_SO'), config('variables_sistema.GRUPO_SO'), 0755);
                }

                File::put($ruta . '/' . $nombreArchivoRg, base64_decode($document['dad']['cdo_informacion_adicional']['pdf_base64']));
                chown($ruta . '/' . $nombreArchivoRg, config('variables_sistema.USUARIO_SO'));
                chgrp($ruta . '/' . $nombreArchivoRg, config('variables_sistema.GRUPO_SO')); 
                chmod($ruta . '/' . $nombreArchivoRg, 0755);

                $document['dad']['cdo_informacion_adicional']['pdf_base64'] = $nombreArchivoRg;
            }

            return [
                'certificado' => isset($nombreArchivoCertificado) && !empty($nombreArchivoCertificado) ? $nombreArchivoCertificado : '',
                'est_archivo' => isset($nombreArchivoRg) && !empty($nombreArchivoRg) ? $nombreArchivoRg : ''
            ];
        }

        return [];
    }

    /**
     * Permite guardar en disco los archivos derivados de procesos realizados en el microservicio.
     *
     * @param string $identificacionPrincipal Identificación del OFE o Empleador dependiendo del proceso
     * @param object $cabecera Registro de cabecera creado para el documento
     * @param string $proceso Indica si el proceso corresponde a emisión o recepción
     * @param string $prefijoArchivo Prefijo que se aplica al nombre del archivo que se creará
     * @param string $extensionArchivo Extensión del archivo que se creará
     * @param string $contenidoArchivo Contenido del archivo en base64
     * @param string $nombreArchivo Nombre con el que se debe almacenar el archivo
     * @return string Nombre del archivo almacenado en disco
     */
    public function guardarArchivoEnDisco($identificacionPrincipal, $cabecera, $proceso, $prefijoArchivo, $extensionArchivo, $contenidoArchivo, $nombreArchivo = null) {
        if($cabecera) {
            $fechaHoraDoc = explode(' ', $cabecera->fecha_creacion);
            $fechaDoc     = explode('-', $fechaHoraDoc[0]);
            $horaDoc      = explode(':', $fechaHoraDoc[1]);
            
            $directorios = [
                config('variables_sistema.RUTA_DISCO_DOCUMENTOS_ELECTRONICOS'),
                $this->definirBaseDatosRutaDisco(),
                $identificacionPrincipal,
                $proceso,
                $fechaDoc[0],
                $fechaDoc[1],
                $fechaDoc[2],
                $horaDoc[0],
                $horaDoc[1],
                $proceso == 'nomina' ? $cabecera->cdn_id : $cabecera->cdo_id
            ];

            $ruta    = '';
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
                $nombreArchivo = $prefijoArchivo . '_' . trim($cabecera->$prefijo) . $cabecera->$consecutivo . '.' . $extensionArchivo;

            if($prefijoArchivo == 'validacionDian' && File::exists($ruta . '/' . $nombreArchivo)) {
                $uuid              = Uuid::uuid4()->toString();
                $nuevoNombre       = $prefijoArchivo . '_' . trim($cabecera->$prefijo) . $cabecera->$consecutivo . '_' . date('YmdHis') . '_' . $uuid . '.' . $extensionArchivo;
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
     * Asigna permiso a los directorios y archivos de un directorio.
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
     * Calcula el redondeo agregado conforme al valor, base y porcentaje de un impuesto.
     *
     * @param float $valor Valor reportado
     * @param float $base Base para el cálculo
     * @param float $porcentaje Porcentaje para el cálculo
     * @return float $redondeoAgregado Diferencia (valor agregado) entre el valor reportado y el valor real calculado
     */
    public function calcularRedondeoAgregado($valor, $base, $porcentaje) {
        $valor      = empty($valor) ? 0 : floatval($valor);
        $base       = empty($base) ? 0 : floatval($base);
        $porcentaje = empty($porcentaje) ? 0 : floatval($porcentaje);

        $calculoValorReal = ($base * $porcentaje)/100;
        $redondeoAgregado = number_format(($calculoValorReal - $valor), 6, '.', '');

        return $redondeoAgregado;
    }

    /**
     * Retorna un array con la información de paramétricas vigentes.
     *
     * @param array $arrParametricasRequeridas Array que indica las paramétricas que debn ser retornadas
     * @param string $aplicaPara Indica si debe filtrar en aplica para por algún documento en específico
     * @return array $parametricas Array de parametricas vigentes
     */
    public function parametricas(array $arrParametricasRequeridas, string $docType) {
        switch ($docType) {
            // Aplica para documento electrónico de factura de venta, nota crédito y nota débito
            case 'FC':
            case 'NC':
            case 'ND':
            case 'NC_ND':
            case 'DE':
                $aplicaPara = 'DE';
                break;
            // Aplica para documento soporte y documento soporte nota crédito
            case 'DS':
            case 'DS_NC':
                $aplicaPara = 'DS';
                break;
            // Aplica para documento soporte de nómina electrónica
            case 'DN':
                $aplicaPara = 'DN';
                break;
            // Aplica para todos los documentos
            default:
                $aplicaPara = 'ALL';
                break;
        }
        foreach ($arrParametricasRequeridas as $nombre) {
            switch($nombre) {
                case 'tipoDocumentoElectronico':
                case 'tipoDocumentosElectronico':
                    $parametricas[$nombre] = [];
                    $consulta = ParametrosTipoDocumentoElectronico::select(['tde_id', 'tde_codigo', 'tde_aplica_para', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('tde_id', 'desc');

                    if($aplicaPara !== 'ALL')
                        $consulta = $consulta->where('tde_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

                    $consulta = $consulta->get()
                        ->groupBy('tde_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->tde_codigo] = $vigente['registro'];
                        });
                    break;

                case 'tipoDocumentos':
                    $parametricas[$nombre] = [];
                    $tipoDocumentos = ParametrosTipoDocumento::select(['tdo_id', 'tdo_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta']);

                    if($aplicaPara !== 'ALL')
                        $tipoDocumentos = $tipoDocumentos->where('tdo_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

                    $tipoDocumentos = $tipoDocumentos->where('estado', 'ACTIVO')
                        ->orderBy('tdo_codigo', 'desc')
                        ->get()
                        ->groupBy('tdo_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->tdo_codigo] = $vigente['registro'];
                        });
                    break;

                case 'tipoOperacion':
                case 'tipoOperaciones':
                    $parametricas[$nombre] = [];
                    $consulta = ParametrosTipoOperacion::select(['top_id', 'top_codigo', 'top_aplica_para', 'top_sector', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('top_codigo', 'desc');

                    if($aplicaPara === 'DE')
                        $consulta = $consulta->whereIn('top_aplica_para', ['FC', 'NC', 'ND']);
                    elseif($aplicaPara !== 'ALL')
                        $consulta = $consulta->where('top_aplica_para', $aplicaPara);

                    $consulta = $consulta->get()
                        ->groupBy(function($item, $key) {
                            return $item->top_codigo . '~' . $item->top_aplica_para;
                        })
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->top_codigo . '~' . $vigente['registro']->top_aplica_para] = $vigente['registro'];
                        });
                    break;

                case 'condicionesEntrega':
                    $parametricas[$nombre] = [];
                    ParametrosCondicionEntrega::select(['cen_id', 'cen_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('cen_codigo', 'desc')
                        ->get()
                        ->groupBy('cen_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->cen_codigo] = $vigente['registro'];
                        });
                    break;

                case 'mediosPago':
                    $parametricas[$nombre] = [];
                    ParametrosMediosPago::select(['mpa_id', 'mpa_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('mpa_codigo', 'desc')
                        ->get()
                        ->groupBy('mpa_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->mpa_codigo] = $vigente['registro'];
                        });
                    break;

                case 'formasPago':
                    $parametricas[$nombre] = [];
                    $consulta = ParametrosFormaPago::select(['fpa_id', 'fpa_codigo', 'fpa_aplica_para', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('fpa_codigo', 'desc');

                    if($aplicaPara !== 'ALL')
                        $consulta = $consulta->where('fpa_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

                    $consulta = $consulta->get()
                        ->groupBy('fpa_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->fpa_codigo] = $vigente['registro'];
                        });
                    break;

                case 'codigosDescuentos':
                    $parametricas[$nombre] = [];
                    ParametrosCodigoDescuento::select(['cde_id', 'cde_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('cde_codigo', 'desc')
                        ->get()
                        ->groupBy('cde_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->cde_codigo] = $vigente['registro'];
                        });
                    break;

                case 'conceptosCorreccion':
                    $parametricas[$nombre] = [];
                    $consulta = ParametrosConceptoCorreccion::select(['cco_id', 'cco_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('cco_codigo', 'desc');

                        if($docType === 'DS_NC')
                            $consulta = $consulta->where('cco_tipo', 'DS');
                        else
                            $consulta = $consulta->where('cco_tipo', $docType);

                        $consulta = $consulta->get()
                            ->groupBy('cco_codigo')
                            ->map(function ($item) use (&$parametricas, $nombre) {
                                $vigente = $this->validarVigenciaRegistroParametrica($item);
                                if($vigente['vigente'])
                                    $parametricas[$nombre][$vigente['registro']->cco_codigo] = $vigente['registro'];
                            });
                    break;

                case 'tributos':
                    $parametricas[$nombre] = [];
                    $consulta = ParametrosTributo::select(['tri_id', 'tri_codigo', 'tri_tipo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('tri_aplica_tributo', 'SI')
                        ->where('estado', 'ACTIVO')
                        ->orderBy('tri_codigo', 'desc');

                    if($aplicaPara !== 'ALL')
                        $consulta = $consulta->where('tri_aplica_para_tributo', 'LIKE', '%'.$aplicaPara.'%');

                    $consulta = $consulta->get()
                        ->groupBy('tri_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->tri_codigo] = $vigente['registro'];
                        });
                    break;

                case 'codigosPostales':
                    $parametricas[$nombre] = [];
                    ParametrosCodigoPostal::select(['cpo_id', 'cpo_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('cpo_codigo', 'desc')
                        ->get()
                        ->groupBy('cpo_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->cpo_codigo] = $vigente['registro'];
                        });
                    break;

                case 'clasificacionProductos':
                    $parametricas[$nombre] = [];
                    ParametrosClasificacionProducto::select(['cpr_id', 'cpr_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('cpr_codigo', 'desc')
                        ->get()
                        ->groupBy('cpr_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->cpr_codigo] = $vigente['registro'];
                        });
                    break;

                case 'preciosReferencia':
                    $parametricas[$nombre] = [];
                    ParametrosPrecioReferencia::select(['pre_id', 'pre_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('pre_codigo', 'desc')
                        ->get()
                        ->groupBy('pre_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->pre_codigo] = $vigente['registro'];
                        });
                    break;
                case 'formaGeneracionTransmision':
                    $parametricas[$nombre] = [];
                    ParametrosFormaGeneracionTransmision::select(['fgt_id', 'fgt_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('fgt_codigo', 'desc')
                        ->get()
                        ->groupBy('fgt_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->fgt_codigo] = $vigente['registro'];
                        });
                    break;

                case 'unidades':
                    $parametricas[$nombre] = [];
                    ParametrosUnidad::select(['und_id', 'und_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('und_codigo', 'desc')
                        ->get()
                        ->groupBy('und_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->und_codigo] = $vigente['registro'];
                        });
                    break;

                case 'moneda':
                case 'monedas':
                    $parametricas[$nombre] = [];
                    ParametrosMoneda::select(['mon_id', 'mon_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('mon_codigo', 'desc')
                        ->get()
                        ->groupBy('mon_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->mon_codigo] = $vigente['registro'];
                        });
                    break;
                    
                case 'mandatos':
                    $parametricas[$nombre] = [];
                    ParametrosMandato::select(['man_id', 'man_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('man_codigo', 'desc')
                        ->get()
                        ->groupBy('man_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->man_codigo] = $vigente['registro'];
                        });
                    break;

                case 'transporteRegistro':
                    $parametricas[$nombre] = [];
                    ParametrosTransporteRegistro::select(['tre_id', 'tre_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('tre_codigo', 'desc')
                        ->get()
                        ->groupBy('tre_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->tre_codigo] = $vigente['registro'];
                        });
                    break;

                case 'transporteRemesa':
                    $parametricas[$nombre] = [];
                    ParametrosTransporteRemesa::select(['trm_id', 'trm_codigo', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('trm_codigo', 'desc')
                        ->get()
                        ->groupBy('trm_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->trm_codigo] = $vigente['registro'];
                        });
                    break;

                case 'facturacionWebCargos':
                    $parametricas[$nombre] = [];
                    $cargos = EtlFacturacionWebCargo::select(['dmc_id', 'dmc_aplica_para', 'ofe_id', 'dmc_codigo', 'dmc_descripcion', 'dmc_porcentaje'])
                        ->where('estado', 'ACTIVO')
                        ->with(['getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion']);

                    if($aplicaPara === 'DE' || $aplicaPara === 'DS')
                        $cargos = $cargos->where('dmc_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

                    $cargos = $cargos->orderBy('dmc_codigo', 'desc')
                        ->get()
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $parametricas[$nombre][$item->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion][$item->dmc_codigo] = $item;
                        });
                    break;

                case 'facturacionWebDescuentos':
                    $parametricas[$nombre] = [];
                    $descuentos = EtlFacturacionWebDescuento::select(['dmd_id', 'dmd_aplica_para', 'ofe_id', 'dmd_codigo', 'dmd_descripcion', 'dmd_porcentaje', 'cde_id'])
                        ->where('estado', 'ACTIVO')
                        ->with(['getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion']);

                    if($aplicaPara === 'DE' || $aplicaPara === 'DS')
                        $descuentos = $descuentos->where('dmd_aplica_para', 'LIKE', '%'.$aplicaPara.'%');

                    $descuentos = $descuentos->orderBy('dmd_codigo', 'desc')
                        ->get()
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $parametricas[$nombre][$item->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion][$item->dmd_codigo] = $item;
                        });
                    break;

                case 'nominaTipoNota':
                    $parametricas[$nombre] = [];
                    ParametrosNominaTipoNota::select(['ntn_id', 'ntn_codigo', 'ntn_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('ntn_codigo', 'desc')
                        ->get()
                        ->groupBy('ntn_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->ntn_codigo] = $vigente['registro'];
                        });
                    break;

                case 'nominaPeriodo':
                    $parametricas[$nombre] = [];
                    ParametrosNominaPeriodo::select(['npe_id', 'npe_codigo', 'npe_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('npe_codigo', 'desc')
                        ->get()
                        ->groupBy('npe_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->npe_codigo] = $vigente['registro'];
                        });
                    break;

                case 'tipoHoraExtraRecargo':
                    $parametricas[$nombre] = [];
                    ParametrosNominaTipoHoraExtraRecargo::select(['nth_id', 'nth_codigo', 'nth_descripcion', 'nth_porcentaje', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('nth_codigo', 'desc')
                        ->get()
                        ->groupBy('nth_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->nth_codigo] = $vigente['registro'];
                        });
                    break;

                case 'tipoIncapacidad':
                    $parametricas[$nombre] = [];
                    ParametrosNominaTipoIncapacidad::select(['nti_id', 'nti_codigo', 'nti_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('nti_codigo', 'desc')
                        ->get()
                        ->groupBy('nti_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->nti_codigo] = $vigente['registro'];
                        });
                    break;

                case 'saludDocumentoReferenciado':
                    $parametricas[$nombre] = [];
                    ParametrosSaludDocumentoReferenciado::select(['sdr_id', 'sdr_codigo', 'sdr_descripcion', 'fecha_vigencia_desde', 'fecha_vigencia_hasta'])
                        ->where('estado', 'ACTIVO')
                        ->orderBy('sdr_codigo', 'desc')
                        ->get()
                        ->groupBy('sdr_codigo')
                        ->map(function ($item) use (&$parametricas, $nombre) {
                            $vigente = $this->validarVigenciaRegistroParametrica($item);
                            if($vigente['vigente'])
                                $parametricas[$nombre][$vigente['registro']->sdr_codigo] = $vigente['registro'];
                        });
                    break;

            }
        }

        return $parametricas;
    }

    /**
     * Aplica formato numérico a un valor.
     * 
     * @param float $valor Valor a formatear
     * @param boolean $calcular Indica si se deben calcular la cantidad de decimales para el formato, false no se calcula y se aplica redondeo a dos decimales, true se aplica el redondeo dependiendo de la cantidad de decimales del numero
     * @param boolean $campoCantidad Indica si se trata de un campo _cantidad, en cuyo caso se debe verificar si tiene decimales mayores a cero se envían los decimales, sino, se envía el entero
     * @return float|int $valor Valor formateado
     */
    public function formatearValor($valor, $calcular = false, $campoCantidad = false){
        if($valor == '')
            return '0.00';
            
        if(!$campoCantidad){
            if (!$calcular) {
                return number_format($valor, 2, '.', '');
            } else {
                $valor = $valor + 0; //eliminando ceros a la izquierda de los decimales
                $parteDecimal = explode(".", $valor);
                $decimales = (isset($parteDecimal[1]) && $parteDecimal[1] > 0) ? strlen($parteDecimal[1]) : 2;
                return number_format($valor, $decimales, '.', '');
            }
        } else {
            $partes = explode('.', $valor);
            if(array_key_exists(1, $partes) && floatval($partes[1]) > 0) {
                $decimales = $valor = $valor + 0;
                $parteDecimal = explode(".", $valor);
                $decimales = (isset($parteDecimal[1]) && $parteDecimal[1] > 0) ? strlen($parteDecimal[1]) : 2;
                return number_format($valor, $decimales, '.', '');
            } else {
                return number_format($valor, 0, '', '');
            }
        }
    }

    /**
     * Crea una conexión dinámica a un servicio de correos Imap.
     *
     * @param string $nombreCuenta Nombre del disco
     * @param array $datosConexion Array con los datos de conexión a la cuenta
     * @return void
     */
    public static function crearConexionDinamicaImap($nombreCuenta, $datosConexion) {
        Config::set('imap.accounts.' . $nombreCuenta, array(
            'host'           => $datosConexion['host'],
            'port'           => $datosConexion['port'],
            'protocol'       => $datosConexion['protocol'],
            'encryption'     => $datosConexion['encryption'],
            'validate_cert'  => $datosConexion['validate_cert'],
            'username'       => $datosConexion['username'],
            'password'       => $datosConexion['password'],
            'authentication' => $datosConexion['authentication'],
            'proxy' => [
                'socket'          => null,
                'request_fulluri' => false,
                'username'        => null,
                'password'        => null,
            ]
        ));
    }

    /**
     * Realiza validaciones sobre campos personalizados de cabecera e items.
     *
     * @param array $configuracionCampo Configuración del campo personalizado a validar
     * @param mixed $valorCampo Valor recibido para el campo personalizado
     * @param bool $campoItem Indica si el campo personalizado corresponde o no a items
     * @param int $itemSecuencis Secuencia del item
     * @return array Array conteniendo indice bool que indica si pasó o no las validaciones y parte string que puede retornar el mensaje de error o el valor por defecto cuando aplica
     */
    public function validarCampoPersonalizado(array $configuracionCampo, $valorCampo, bool $campoItem = false, int $itemSecuencia = 0) {
        $complementoMsg = ' en información adicional de cabecera';
        if($campoItem)
            $complementoMsg = ' en información adicional del item ['. $itemSecuencia .']';

        switch($configuracionCampo['tipo']) {
            case 'texto':
                if(!empty($configuracionCampo['longitud'])) {
                    if(array_key_exists('exacta', $configuracionCampo) && $configuracionCampo['exacta'] == 'SI' && strlen($valorCampo) != $configuracionCampo['longitud'])
                        return [
                            'error' => true,
                            'message' => 'La longitud del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' debe ser exacta, cantidad de caracteres permitidos [' . $configuracionCampo['longitud'] . ']'
                        ];
                    elseif((!array_key_exists('exacta', $configuracionCampo) || (array_key_exists('exacta', $configuracionCampo) && $configuracionCampo['exacta'] == 'NO')) && strlen($valorCampo) > $configuracionCampo['longitud'])
                        return [
                            'error' => true,
                            'message' => 'La longitud del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' supera la cantidad de caracteres permitidos de [' . $configuracionCampo['longitud'] . ']'
                        ];
                }
                break;

            case 'por_defecto':
                if(!empty($configuracionCampo['longitud']) && strlen($valorCampo) > $configuracionCampo['longitud'])
                    return [
                        'error' => true,
                        'message' => 'La longitud del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' supera la cantidad de caracteres permitidos de [' . $configuracionCampo['longitud'] . ']'
                    ];
                break;

            case 'multiple':
                if(!in_array($valorCampo, $configuracionCampo['opciones']))
                    return [
                        'error' => true,
                        'message' => 'El valor del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' no corresponde a una de las opciones válidas'
                    ];
                break;

            case 'numerico':
                $errores = [];
                if(!is_nan(floatval($valorCampo))) {
                    list($parteEntera, $parteDecimal)           = strstr($configuracionCampo['longitud'], '.') ? explode('.', $configuracionCampo['longitud']) : [$configuracionCampo['longitud'], ''];
                    list($parteEnteraValor, $parteDecimalValor) = strstr($valorCampo, '.') ? explode('.', $valorCampo) : [$valorCampo, ''];

                    if(array_key_exists('exacta', $configuracionCampo) && $configuracionCampo['exacta'] == 'SI' && strlen($parteEnteraValor) != $parteEntera)
                        $errores[] = 'La parte entera del valor del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' debe contener [' . $parteEntera . '] caracteres';
                    elseif((!array_key_exists('exacta', $configuracionCampo) || (array_key_exists('exacta', $configuracionCampo) && $configuracionCampo['exacta'] == 'NO')) && strlen($parteEnteraValor) > $parteEntera)
                        $errores[] = 'La parte entera del valor del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' supera la cantidad de caracteres enteros permitidos de [' . $parteEntera . ']';

                    if(array_key_exists('exacta', $configuracionCampo) && $configuracionCampo['exacta'] == 'SI' && !empty($parteDecimal) && $parteDecimal > 0 && strlen($parteDecimalValor) != $parteDecimal)
                        $errores[] = 'La parte decimal del valor del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' debe conteneder [' . $parteDecimal . '] caracteres';
                    elseif((!array_key_exists('exacta', $configuracionCampo) || (array_key_exists('exacta', $configuracionCampo) && $configuracionCampo['exacta'] == 'NO')) && strlen($parteDecimalValor) > $parteDecimal)
                        $errores[] = 'La parte decimal del valor del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' supera la cantidad de caracteres decimales permitidos de [' . $parteDecimal . ']';
                } else
                    $errores[] = 'El valor del campo [' . $configuracionCampo['campo'] . ']' . $complementoMsg . ' debe ser númerico [' . $configuracionCampo['longitud'] . ']';

                if(!empty($errores)) return ['error' => true, 'message' => implode(', ', $errores)];
                break;

        }
        
        return [
            'error'             => false,
            'valor_por_defecto' => $configuracionCampo['tipo'] == 'por_defecto' ? $configuracionCampo['opciones'] : ''
        ]; 
    }

    /**
     * Registra documentos electrónicos en emisión.
     *
     * @param Request $request Parámetros de la petición
     * @param \stdClass $json Objeto que contiene la información del documento a procesar
     * @param string $cdo_origen Origen de procesamiento del documento
     * @return array|JsonResponse
     */
    public function registrarDocumentosEmision(Request $request, \stdClass $json, string $cdo_origen) {
        try {
            $user = auth()->user();
            $builder = new DataBuilder($user->usu_id, $json, $cdo_origen);
            $procesado = $builder->run();

            // La siguiente lógica aplica cuando el request llega de fuentes diferentes al método emisionRegistrarDocumentos()
            if(!$request->filled('retornar_id_lote') && array_key_exists('documentos_fallidos', $procesado) && !empty($procesado['documentos_fallidos'])) {
                $documentosFallidos = $procesado['documentos_fallidos'];
                $procesado['documentos_fallidos'] = [];
                foreach($documentosFallidos as $fallido) {
                    $fallido['errors'] = array_map(function($error) {
                        if(strstr($error, 'ya esta registrado') !== false) {
                            $errorTmp = explode('~', $error);
                            $error    = $errorTmp[0];
                        }

                        return $error;
                    }, $fallido['errors']);

                    $procesado['documentos_fallidos'][] = $fallido;
                }
            }

            if($request->filled('retornar_array') && $request->retornar_array == true)
                return $procesado;

            return response()->json($procesado, 201);
        } catch (\Exception $e) {
            $error = $e->getMessage();
            return response()->json([
                'message' => 'Error al registrar el Json',
                'errors'  => [$error]
            ], 400);
        }
    }

    /**
     * Establece una conexión a una base de datos Tenant.
     *
     * @param AuthBaseDatos $baseDatos Instancia de la base de datos a la que se debe conectar
     * @return void
     */
    public function reconectarDB($baseDatos = null): void {
        if(empty($baseDatos))
            $baseDatos = $this->getDataBase();

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
     * Retorna la información de conexión a una base de datos.
     * 
     * La base de datos que se debe buscar es la parametrizada en bdd_id del usuario autenticado,
     * que corresponde a la base de datos fisica donde se encuentra la informacion del cliente.
     * La base de datos bdd_id_rg hace referencia a las rutas en disco y en donde se encuentra
     * la representacion grafica del cliente.
     *
     * @return AuthBaseDatos
     */
    public function getDataBase(): AuthBaseDatos {
        return AuthBaseDatos::select(['bdd_nombre', 'bdd_host', 'bdd_usuario', 'bdd_password'])
            ->where('bdd_id', auth()->user()->bdd_id)
            ->where('estado', 'ACTIVO')
            ->first();
    }
}
