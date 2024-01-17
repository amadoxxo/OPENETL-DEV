<?php
namespace App\Http\Modulos\Recepcion\TransmisionErp\etl_valoremx\Valorem;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Modulos\Recepcion\TransmisionErp\Commons\MethodsTrait;
use App\Http\Modulos\Recepcion\TransmisionErp\etl_valoremx\Valorem\SoapClientValorem;
use App\Http\Modulos\Recepcion\TransmisionErp\etl_valoremx\Valorem\ValoremXmlTransmitir;
use App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaop;

class ValoremController extends Controller {
    use MethodsTrait;
    
    /**
     * Constructor de la clase
     *
     * @param Collection $oferente Colección con información del OFE en procesamiento
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
    }

    /**
     * Procesa los documentos que deban ser transmitidos al ERP
     * 
     * @param integer $limiteIntentos Límite de inentos de transmisión
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Ofe relacionado con la transmisión
     * @param string $cdoIds IDs de documentos que se desean transmitir, este parámetro llega cuando el proceso es llamado desde el cliente web a través de la ruta /recepcion/documentos/transmitir-erp
     * @return void
     */
    public function procesar($limiteIntentos, $ofe, $cdoIds = null) {
        $documentos = RepCabeceraDocumentoDaop::select([
            'cdo_id',
            'rfa_prefijo',
            'cdo_consecutivo',
            'cdo_clasificacion',
            'tde_id',
            'cdo_fecha',
            'cdo_hora',
            'cdo_cufe',
            'ofe_id',
            'pro_id',
            'mon_id',
            'cdo_descuentos',
            'cdo_impuestos',
            'cdo_anticipo',
            'cdo_total',
            'cdo_documento_referencia'
        ])
            ->with([
                'getTipoDocumentoElectronico:tde_id,tde_descripcion',
                'getRepDadDocumentosDaop:dad_id,cdo_id,dad_orden_referencia',
                'getConfiguracionObligadoFacturarElectronicamente' => function($query) {
                    $query->select([
                        'ofe_id',
                        'ofe_identificacion',
                        DB::raw('IF(ofe_razon_social IS NULL OR ofe_razon_social = "", CONCAT(COALESCE(ofe_primer_nombre, ""), " ", COALESCE(ofe_otros_nombres, ""), " ", COALESCE(ofe_primer_apellido, ""), " ", COALESCE(ofe_segundo_apellido, "")), ofe_razon_social) as ofe_razon_social')
                    ]);
                },
                'getConfiguracionProveedor' => function($query) {
                    $query->select([
                        'pro_id',
                        'pro_identificacion',
                        DB::raw('IF(pro_razon_social IS NULL OR pro_razon_social = "", CONCAT(COALESCE(pro_primer_nombre, ""), " ", COALESCE(pro_otros_nombres, ""), " ", COALESCE(pro_primer_apellido, ""), " ", COALESCE(pro_segundo_apellido, "")), pro_razon_social) as pro_razon_social')
                    ]);
                },
                'getParametrosMoneda:mon_id,mon_codigo',
            ])
            ->withCount([
                'getTransmisionErpFallido' => function($query) {
                    $query->where('est_estado', 'TRANSMISIONERP')
                        ->where('est_resultado', 'FALLIDO')
                        ->where('est_ejecucion', 'FINALIZADO');
                }
            ])
            ->where('ofe_id', $ofe->ofe_id);

        if($cdoIds != null)
            $documentos = $documentos->whereIn('cdo_id', explode(',', $cdoIds));
        else
            $documentos = $documentos->doesntHave('getTransmisionErpExitoso');

        $documentos = $documentos->get()
            ->map(function($documento) use($limiteIntentos, $ofe, $cdoIds) {
                if(
                    ($documento->get_transmision_erp_fallido_count < $limiteIntentos && $cdoIds == null) ||
                    $cdoIds != null
                ) {
                    try {
                        $inicioMicrotime = microtime(true);
                        $fechaInicio     = date('Y-m-d H:i:s');
                        $xmlTransmitir   = new ValoremXmlTransmitir();
                        $xmlTransmitir   = $xmlTransmitir->armarXml($documento);

                        $transmision = new SoapClientValorem(null, array (
                            'uri'            => $ofe->ofe_recepcion_conexion_erp['wsdl'],
                            'location'       => $ofe->ofe_recepcion_conexion_erp['url_request'],
                            'login'          => $ofe->ofe_recepcion_conexion_erp['usuario'],
                            'password'       => $ofe->ofe_recepcion_conexion_erp['password'],
                            'soap_version'   => SOAP_1_1,
                            'cache_wsdl'     => WSDL_CACHE_NONE,
                            'trace'          => TRUE,
                            'encoding'       => 'UTF-8',
                            'compression'    => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                            'authentication' => SOAP_AUTHENTICATION_BASIC,
                            'stream_content' => stream_context_create(array(
                                'http' => array(
                                    'user_agent' => 'PHPSoapClient'
                                )
                            )),
                            'xml_transmitir' => $xmlTransmitir
                        ));

                        $rptaValorem = $transmision->__soapCall(
                            "OP_DocumentosDian",
                            array('Invoice' => $xmlTransmitir),
                            null,
                            null
                        );

                        $arrRpta   = [];
                        $arrRpta[] = [
                            'respuesta_erp'        => $rptaValorem,
                            'fecha_hora_respuesta' => date('Y-m-d H:i:s')
                        ];

                        $this->creaNuevoEstadoDocumentoRecepcion(
                            $documento->cdo_id,
                            'TRANSMISIONERP',
                            isset($rptaValorem['RegistroExitoso']) && $rptaValorem['RegistroExitoso'] == 'true' ? 'EXITOSO' : 'FALLIDO',
                            isset($rptaValorem['RegistroExitoso']) && $rptaValorem['RegistroExitoso'] == 'true' ? null : 'No se logró el registro exitoso en la transmisión al ERP',
                            // base64_encode($xmlTransmitir), // TODO: Se debe reemplazar esta línea porque el archivo ya no se almacena en la BD
                            $fechaInicio,
                            date('Y-m-d H:i:s'),
                            number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                            0,
                            auth()->user()->usu_id,
                            $arrRpta,
                            'FINALIZADO'
                        );
                    } catch (\Exception $e) {
                        $arrExcepciones   = [];
                        $arrExcepciones[] = [
                            'documento'           => $documento->cdo_clasificacion,
                            'consecutivo'         => $documento->cdo_consecutivo,
                            'prefijo'             => $documento->rfa_prefijo,
                            'errors'              => [$e->getMessage()],
                            'fecha_procesamiento' => date('Y-m-d'),
                            'hora_procesamiento'  => date('H:i:s'),
                            'detalle_webservice'  => isset($e->detail) ? array($e->detail) : null,
                            'traza'               => $e->getFile() . ' - Línea ' . $e->getLine() . ': ' . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString()
                        ];
                        
                        $this->creaNuevoEstadoDocumentoRecepcion(
                            $documento->cdo_id,
                            'TRANSMISIONERP',
                            'FALLIDO',
                            $e->getMessage(),
                            // base64_encode($xmlTransmitir), // TODO: Se debe reemplazar esta línea porque el archivo ya no se almacena en la BD
                            $fechaInicio,
                            date('Y-m-d H:i:s'),
                            number_format((microtime(true) - $inicioMicrotime), 3, '.', ''),
                            0,
                            auth()->user()->usu_id,
                            $arrExcepciones,
                            'FINALIZADO'
                        );
                    }
                }
            });
    }
}
