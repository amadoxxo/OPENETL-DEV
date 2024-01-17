<?php
namespace App\Console\Commands\DHLExpress;

use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Documentos\EtlMediosPagoDocumentosDaop\EtlMediosPagoDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Comando para exportar la data a ser leida por los servicios de carga de documentos de DHLExpress (PDF, XML y TXT)
 * Class DhlExpressPutDocumentsCommand
 * @package App\Console\Commands
 */
class DhlExpressSalidaArchivosCommand extends Command {
    use DoTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhlexpress-salida-archivos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Coloca las representaciones graficas, xml firmados y archivos txt de los documentos notificados para que DhlExpress los pueda subir a sus servidores ftp';

    /**
     * @var int Total de registros a procesar
     */
    protected $total_procesar = 100;

    /**
     * Disco de trabajo para el procesamiento de los archivos
     * @var string
     */
    private $discoTrabajo = 'ftpDhlExpress';

    /**
     * @var string Nombre del directorio de la BD
     */
    protected $baseDatosDhlExpress = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * @var string Nombre del directorio del NIT de Express
     */
    protected $nitDhlExpress = ''; // Si coloca un valor en esta variable, debe agregar el / al final

    /**
     * @var array cdo_clasificacion de los documentos que aplican.
     */
    protected $arrCdoClasificacion = ['FC','NC','ND'];

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
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $usuarios = [
            'dhlexpress@openio.co',     //ETL3
            'dhlexpress-caia@openio.co' //ETL2
        ];

        foreach($usuarios as $usuario) { 
            // Obtiene el usuario relacionado con DhlExpress
            $user = User::where('usu_email', $usuario)
                ->where('estado', 'ACTIVO')
                ->with(['getBaseDatos'])
                ->first();

            if($user) {
                // Establecemos conexión a la BD
                TenantDatabase::setTenantConnection(
                    'conexion01', // Nombre de la conexión
                    $user->getBaseDatos->bdd_host, // Host de la base de datos
                    $user->getBaseDatos->bdd_nombre, // Base de datos
                    $user->getBaseDatos->bdd_usuario, // Usuario de la conexión
                    $user->getBaseDatos->bdd_password // Clave de conexión
                );

                if($user) {
                    // Generación del token conforme al usuario
                    $token = auth()->login($user);
                    DoTrait::setFilesystemsInfo();

                    // Obtiene la información de configuracion del SFTP par DHL Express
                    $ofes = ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id'])
                        ->where('estado', 'ACTIVO')
                        ->get()
                        ->toArray();

                    // Obtiene los documentos de DHL Express que cumplan con las siguientes condiciones
                    //  - Origen del documento debe ser INTEGRACION
                    //  - Los archivos de salida no hayan sido generados
                    //  - Tengan estado EDI procesado de manera exitosa
                    //  - Tengan estado UBL procesado de manera existosa
                    //  - Tengan estado NOTIFICACION procesado de manera existosa
                    //  - La clasificación del documento sea FC, NC o ND
                    $documentos = EtlCabeceraDocumentoDaop::select(['cdo_id', 'cdo_clasificacion', 'ofe_id', 'adq_id', 'rfa_prefijo', 'cdo_consecutivo', 'cdo_fecha', 'cdo_hora', 'tde_id', 'cdo_representacion_grafica_documento', 'cdo_fecha_archivo_salida', 'fecha_creacion'])
                        ->whereIn('cdo_clasificacion', $this->arrCdoClasificacion)
                        ->whereIn('ofe_id', $ofes)
                        ->whereIn('cdo_representacion_grafica_documento', [1,4,6,8])
                        ->whereNull('cdo_fecha_archivo_salida')
                        ->whereHas('getEdiDocumento')
                        ->whereHas('getUblDocumento')
                        ->whereHas('getNotificacionDocumento')
                        ->with([
                            'getEdiDocumento:est_id,cdo_id',
                            'getUblDocumento:est_id,cdo_id,est_informacion_adicional',
                            'getNotificacionDocumento:est_id,cdo_id,est_informacion_adicional',
                            'getDetalleDocumentosDaop:ddo_id,cdo_id,ddo_informacion_adicional',
                            'getDadDocumentosDaop:dad_id,cdo_id,cdo_informacion_adicional',
                            'getConfiguracionAdquirente:adq_id,adq_identificacion',
                            'getConfiguracionObligadoFacturarElectronicamente:ofe_id,ofe_identificacion,pai_id_domicilio_fiscal,ofe_conexion_ftp',
                            'getTipoDocumentoElectronico:tde_id,tde_codigo'
                        ])
                        ->take($this->total_procesar)
                        ->get();

                    foreach ($documentos as $documento) {
                        $this->enviarFtp(
                            $documento,
                            $user->usu_id,
                            date('Y-m-d H:i:s'),
                            microtime(true)
                        );
                    }
                }
                DB::purge('conexion01');
            }
        }
    }

    /**
     * Coloca el Archivo de entrada, PDF y XML firmado en la carpeta de salida de SFTP de DHL Express
     *
     * @param Illuminate\Database\Eloquent\Collection $documento Colección de información del documento
     * @param int $usu_id ID del usuario de DHL Express
     * @param datetime $fechaInicioProcesamiento Fecha y hora del inicio de procesamiento
     * @param timestamp $inicioProcesamiento Timestamp del inicio de procesamiento
     * @return void
     */
    private function enviarFtp($documento, $usu_id, $fechaInicioProcesamiento, $inicioProcesamiento) {
        try {
            if ($documento->cdo_clasificacion === 'FC')
                $remoteBase = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['salida_fc'];
            else
                $remoteBase = $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['salida_nc'];

            TenantTrait::GetVariablesSistemaTenant();
            $storagePath  = Storage::disk($this->discoTrabajo)->getDriver()->getAdapter()->getPathPrefix();
            $prefijoRuta  = $this->baseDatosDhlExpress . $this->nitDhlExpress . $remoteBase;
            $storedir     = $this->baseDatosDhlExpress . $this->nitDhlExpress;

            $file         = null;
            $fileFallidos = null;

            $nombreBase = $this->generarNombreBase($documento);
            if($documento->cdo_representacion_grafica_documento == 1) {
                $nombrePDF = $nombreBase . 'ORIG' . '_FRI_BOG_CO_IDI_' . date('Ymd') . '_' . date('His');
                $nombreXML = $nombreBase . 'DATA' . '_FRI_BOG_CO_IDI_' . date('Ymd') . '_' . date('His');
            } elseif($documento->cdo_representacion_grafica_documento == 8) { 
                $aplicacion = ($documento->getDadDocumentosDaop->cdo_informacion_adicional['aplicacion'] == 'COMEX') ? 'COMEX' : 'CAIA';
                $nombrePDF  = $nombreBase . 'ORIG' . '_DVI_BOG_CO_' . $aplicacion . '_' . date('Ymd') . '_' . date('His');
                $nombreXML  = $nombreBase . 'DATA' . '_DVI_BOG_CO_' . $aplicacion . '_' . date('Ymd') . '_' . date('His');
            } else {
                $nombrePDF = $nombreBase . 'ORIG' . '_DVI_BOG_CO_IDI_' . date('Ymd') . '_' . date('His');
                $nombreXML = $nombreBase . 'DATA' . '_DVI_BOG_CO_IDI_' . date('Ymd') . '_' . date('His');
            }

            $informacionAdicionalUbl = !empty($documento->getUblDocumento->est_informacion_adicional) ? json_decode($documento->getUblDocumento->est_informacion_adicional, true) : [];
            $informacionAdicionalRg  = !empty($documento->getNotificacionDocumento->est_informacion_adicional) ? json_decode($documento->getNotificacionDocumento->est_informacion_adicional, true) : [];

            $xmlUbl = $this->obtenerArchivoDeDisco(
                'emision',
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                $documento,
                array_key_exists('est_xml', $informacionAdicionalUbl) ? $informacionAdicionalUbl['est_xml'] : null
            );
            $xmlUbl = $this->eliminarCaracteresBOM($xmlUbl);

            $representacionGrafica = $this->obtenerArchivoDeDisco(
                'emision',
                $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion,
                $documento,
                array_key_exists('est_archivo', $informacionAdicionalRg) ? $informacionAdicionalRg['est_archivo'] : null
            );
            $representacionGrafica = $this->eliminarCaracteresBOM($representacionGrafica);

            Storage::disk($this->discoTrabajo)->put($prefijoRuta . $nombrePDF . '.pdf', $representacionGrafica);
            chown($storagePath . $prefijoRuta . $nombrePDF . '.pdf', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['usuario_so']);
            chgrp($storagePath . $prefijoRuta . $nombrePDF . '.pdf', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['grupo_so']); 
            chmod($storagePath . $prefijoRuta . $nombrePDF . '.pdf', 0755);

            Storage::disk($this->discoTrabajo)->put($prefijoRuta . $nombreXML . '.xml', $xmlUbl);
            chown($storagePath . $prefijoRuta . $nombreXML . '.xml', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['usuario_so']);
            chgrp($storagePath . $prefijoRuta . $nombreXML . '.xml', $documento->getConfiguracionObligadoFacturarElectronicamente->ofe_conexion_ftp['grupo_so']); 
            chmod($storagePath . $prefijoRuta . $nombreXML . '.xml', 0755);

            $estadoInformacionAdicional['archivo_pdf'] = $nombrePDF;
            $estadoInformacionAdicional['archivo_xml'] = $nombreXML;

            EtlEstadosDocumentoDaop::create([
                'cdo_id'                   => $documento->cdo_id,
                'est_estado'               => 'ENVIADO-FTP',
                'est_resultado'            => 'EXITOSO',
                'est_informacion_adicional'=> empty($estadoInformacionAdicional) ? null : json_encode($estadoInformacionAdicional),
                'est_inicio_proceso'       => $fechaInicioProcesamiento,
                'est_fin_proceso'          => date('Y-m-d H:i:s'),
                'est_tiempo_procesamiento' => number_format((microtime(true) - $inicioProcesamiento), 3, '.', ''),
                'est_ejecucion'            => 'FINALIZADO',
                'usuario_creacion'         => $usu_id,
                'estado'                   => 'ACTIVO',
            ]);

            $documento->update([
                'cdo_fecha_archivo_salida' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            EtlEstadosDocumentoDaop::create([
                'cdo_id'                    => $documento->cdo_id,
                'est_estado'                => 'ENVIADO-FTP',
                'est_resultado'             => 'FALLIDO',
                'est_mensaje_resultado'     => 'Error al procesar el documento',
                'est_inicio_proceso'        => $fechaInicioProcesamiento,
                'est_fin_proceso'           => date('Y-m-d H:i:s'),
                'est_tiempo_procesamiento'  => number_format((microtime(true) - $inicioProcesamiento), 3, '.', ''),
                'est_ejecucion'             => 'FINALIZADO',
                'est_informacion_adicional' => json_encode(['error' => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()]),
                'usuario_creacion'          => $usu_id,
                'estado'                    => 'ACTIVO',
            ]);

            $documento->update([
                'cdo_fecha_archivo_salida' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Genera el nombre de archivo que se asignará al PDF y XML que se colocan en el FTP
     *
     * @param Illuminate\Database\Eloquent\Collection $documento Colección de información del documento
     * @return string $nombreBase Nombre de base generado
     */
    private function generarNombreBase($documento) {
        $separador  = '^';
        $nombreBase = '';

        if($documento->cdo_representacion_grafica_documento == '8') {
            //Para los campos de guia, ciudad origen y ciudad destino, si hay mas de un DO se deben enviar vacios
            $cGuia          = (array_key_exists('guia', $documento->getDadDocumentosDaop->cdo_informacion_adicional)) ? $documento->getDadDocumentosDaop->cdo_informacion_adicional['guia'] : '';
            $cCiudadOrigen  = (array_key_exists('ciudad_origen', $documento->getDadDocumentosDaop->cdo_informacion_adicional)) ? $documento->getDadDocumentosDaop->cdo_informacion_adicional['ciudad_origen'] : '';
            $cCiudadDestino = (array_key_exists('ciudad_destino', $documento->getDadDocumentosDaop->cdo_informacion_adicional)) ? $documento->getDadDocumentosDaop->cdo_informacion_adicional['ciudad_destino'] : '';
            if (array_key_exists('cantidad_envios', $documento->getDadDocumentosDaop->cdo_informacion_adicional) && $documento->getDadDocumentosDaop->cdo_informacion_adicional['cantidad_envios'] > 1) {
                $cGuia          = '';
                $cCiudadOrigen  = '';
                $cCiudadDestino = '';
            }
            // Nombre Archivo de salida Agencia de Aduanas
            // Guia 
            $nombreBase .=  $cGuia . $separador;
            // Cuenta
            $nombreBase .= $documento->getDadDocumentosDaop->cdo_informacion_adicional['cuenta'] . $separador;
            // Factura 
            $nombreBase .= $documento->rfa_prefijo . $documento->cdo_consecutivo . $separador;
            // Fecha Emisión 
            $nombreBase .= str_replace('-', '', $documento->cdo_fecha) . $separador;
            // Ciudad Origen 
            $nombreBase .=  $cCiudadOrigen . $separador;
            // Ciudad Destino 
            $nombreBase .= $cCiudadDestino . $separador;
            // Forma Pago 
            $formaPago = [];
            $codigoFormaPago = EtlMediosPagoDocumentoDaop::select(['fpa_id', 'mpa_id'])
                ->where('cdo_id', $documento->cdo_id)
                ->with(['getFormaPago'])
                ->first();
            if($codigoFormaPago) {
                $formaPago = !is_null($codigoFormaPago->getFormaPago) ? $codigoFormaPago->getFormaPago->toArray() : [];
            }
            $nombreBase .= ((array_key_exists('fpa_codigo', $formaPago) && $formaPago['fpa_codigo'] == '1') ? 'CSH' : 'ACC') . $separador;
            // Factura 
            $nombreBase .= $documento->rfa_prefijo . $documento->cdo_consecutivo . $separador;
            // Fecha envío (Siempre Vacio)
            $nombreBase .= $separador;
            // Fecha envío (Siempre Vacio)
            $nombreBase .= $separador;
            // Adquirente 
            $nombreBase .= $documento->getConfiguracionAdquirente->adq_identificacion . $separador;
            // País OFE 
            $nombreBase .= $documento->getConfiguracionObligadoFacturarElectronicamente->getParametroDomicilioFiscalPais['pai_codigo'] . $separador;
            // Posición vacía 
            $nombreBase .= $separador;
            // Aplicación 
            $aplicacion = ($documento->getDadDocumentosDaop->cdo_informacion_adicional['aplicacion'] == 'COMEX') ? 'TD2' : 'TD1';
            $nombreBase .= $aplicacion . $separador;
            // Posición vacía 
            $nombreBase .= $separador;
            // Tipo documento
            $tipoDocumento = '';
            switch ($documento->getTipoDocumentoElectronico['tde_codigo']) {
                case '91':
                    $tipoDocumento = 'CRE';
                break;
                case '92':
                    $tipoDocumento = 'DEB';
                break;
                default:
                    //01,02,03,04
                    $tipoDocumento = 'BIV';
                break;
            }
            $nombreBase .= $tipoDocumento . $separador;
            // Tipo Facturación 
            $nombreBase .= 'IB' . $separador;
            // EBILL 
            $nombreBase .= 'EBILL' . $separador;
        } else {
            // Nombre Archivo de salida Dhl Express RG 1,4,6
            if($documento->cdo_representacion_grafica_documento == '1') {
                $envios = $documento->getDetalleDocumentosDaop->count();
            } else {
                $envios = $documento->getDadDocumentosDaop->cdo_informacion_adicional['cantidad_envios'];
            }

            if($envios == 1) {
                $informacionAdicionalItem = json_decode($documento->getDetalleDocumentosDaop[0]['ddo_informacion_adicional']);
            }

            if($envios == 1) {
                if($documento->cdo_representacion_grafica_documento == '1') {
                    $nombreBase .= $informacionAdicionalItem->guia . $separador;
                } else{
                    //Se debe validar si la informacion adicional se guardo como objeto o como array
                    if (isset($informacionAdicionalItem->numero_guia)) {
                        $info_adicional_numero_guia = $informacionAdicionalItem->numero_guia;
                    } elseif (isset($informacionAdicionalItem[0]->numero_guia)) {
                        $info_adicional_numero_guia = $informacionAdicionalItem[0]->numero_guia;
                    } else {
                        $info_adicional_numero_guia = "";
                    }
                    $nombreBase .= $info_adicional_numero_guia . $separador;
                }
            } else {
                $nombreBase .= $separador;
            }

            if($documento->cdo_representacion_grafica_documento == '1') {
                $nombreBase .= $documento->getDadDocumentosDaop->cdo_informacion_adicional['no_cuenta'] . $separador;
            } else {
                $nombreBase .= $documento->getDadDocumentosDaop->cdo_informacion_adicional['cuenta'] . $separador;
            }

            if($documento->cdo_representacion_grafica_documento == '1') {
                $nombreBase .= $documento->getDadDocumentosDaop->cdo_informacion_adicional['numero_interno'] . $separador;
            } else {
                $nombreBase .= $documento->rfa_prefijo . $documento->cdo_consecutivo . $separador;
            }
            
            $nombreBase .= str_replace('-', '', $documento->cdo_fecha) . $separador;

            if($envios == 1) {
                //Se debe validar si la informacion adicional se guardo como objeto o como array
                if (isset($informacionAdicionalItem->origen)) {
                    $info_adicional_origen = $informacionAdicionalItem->origen;
                } elseif (isset($informacionAdicionalItem[0]->origen)) {
                    $info_adicional_origen = $informacionAdicionalItem[0]->origen;
                } else {
                    $info_adicional_origen = "";
                }

                if($documento->cdo_representacion_grafica_documento == '1') {
                    $nombreBase .= $info_adicional_origen . $separador;
                } else {
                    $origen      = str_replace('  ', ' ', $info_adicional_origen);
                    $origen      = explode(' ', $origen);
                    $nombreBase .= $origen[1] . $separador;
                }
            } else {
                $nombreBase .= $separador;
            }

            if($envios == 1) {
                //Se debe validar si la informacion adicional se guardo como objeto o como array
                if (isset($informacionAdicionalItem->destino)) {
                    $info_adicional_destino = $informacionAdicionalItem->destino;
                } elseif (isset($informacionAdicionalItem[0]->destino)) {
                    $info_adicional_destino = $informacionAdicionalItem[0]->destino;
                } else {
                    $info_adicional_destino = "";
                }

                if($documento->cdo_representacion_grafica_documento == '1') {
                    $nombreBase .= $info_adicional_destino . $separador;
                } else {
                    $destino     = str_replace('  ', ' ', $info_adicional_destino);
                    $destino     = explode(' ', $destino);
                    $nombreBase .= $destino[1] . $separador;
                }
            } else {
                $nombreBase .= $separador;
            }

            $nombreBase .= 'ACC';
            $nombreBase .= $separador;
            $nombreBase .= $documento->rfa_prefijo . $documento->cdo_consecutivo;
            $nombreBase .= $separador . $separador . $separador;
            $nombreBase .= $documento->getConfiguracionAdquirente->adq_identificacion;
            $nombreBase .= $separador;
            $nombreBase .= 'CO';
            $nombreBase .= $separador . $separador;
            $nombreBase .= ($documento->cdo_representacion_grafica_documento == '1') ? 'TD1' : 'TD1';
            $nombreBase .= $separador . $separador;

            if($documento->cdo_clasificacion == 'FC') {
                $nombreBase .= 'BIV' . $separador;
            } else {
                $nombreBase .= 'CRE' . $separador;
            }

            if($documento->cdo_representacion_grafica_documento == '1') {
                if($envios == 1) {
                    //Se debe validar si la informacion adicional se guardo como objeto o como array
                    if (isset($informacionAdicionalItem->pais_origen)) {
                        $info_adicional_pais_origen = $informacionAdicionalItem->pais_origen;
                    } elseif (isset($informacionAdicionalItem[0]->pais_origen)) {
                        $info_adicional_pais_origen = $informacionAdicionalItem[0]->pais_origen;
                    } else {
                        $info_adicional_pais_origen = "";
                    }

                    if(strtolower($info_adicional_pais_origen) != 'colombia') {
                        $nombreBase .= 'IB' . $separador;
                    } else {
                        $nombreBase .= 'OB' . $separador;
                    }
                } else {
                    $nombreBase .= 'IB' . $separador;
                }
            } else {
                $nombreBase .= 'IB' . $separador;
            }

            $nombreBase .= 'EBILL';
            $nombreBase .= $separador;
        }
        return $nombreBase;
    }
}