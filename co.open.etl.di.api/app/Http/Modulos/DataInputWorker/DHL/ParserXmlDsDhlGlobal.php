<?php
namespace App\Http\Modulos\DataInputWorker\DHL;

use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;

class ParserXmlDsDhlGlobal {
    /**
     * ParserXmlDsDHLGlobal constructor.
     * @param string $filename Nombre o ruta del archivo XML a procesar
     */
    public function __construct() {
        // Inhabilita los errores libxml y permite capturarlos para su manipulación
        libxml_use_internal_errors(true);
    }

    /**
     * Traduce el error generado al cargar intentar cargar un archivo XMl.
     *
     * @param array $erroresXml Array de errores generados para el XML
     * @param array $arrErroresDocumento Array par registro de errores del documento
     * @return void
     */
    private function errorXml(array $erroresXml, array &$arrErroresDocumento): void {
        foreach ($erroresXml as $error) {
            $stringError = '';
            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $stringError .= 'Advertencia ' . $error->code . ': ';
                    break;
                case LIBXML_ERR_ERROR:
                    $stringError .= 'Error ' . $error->code . ': ';
                    break;
                case LIBXML_ERR_FATAL:
                    $stringError .= 'Error Fatal ' . $error->code . ': ';
                    break;
            }

            $stringError .= trim($error->message) . ' en la Línea: ' . $error->line . ', Columna: ' . $error->column;

            if ($error->file)
                $stringError .= ' (Archivo: ' . $error->file . ')';

            $arrErroresDocumento[] = $stringError;
        }
    }

    /**
     * Extrae la información de un archivo XML hacia un array que será transformado en JSON para su posterior validación y creación del documento en el sistema.
     *
     * @param string $archivo Ruta del archivo XML a procesar
     * @param int $ofe_id ID del OFE
     * @return array Array conteniendo una posición json con la información del archivo XML y otra posición errors para el registro de errores del procesamiento
     */
    public function parserXml(string $archivo, int $ofe_id): array {
        $json              = [];
        $arrErrores        = [];
        $cdo_clasificacion = '';
        $xml               = simplexml_load_file($archivo);
        if (!$xml) {
            $this->errorXml(libxml_get_errors(), $arrErrores);
            return [
                'clasificacion' => $cdo_clasificacion,
                'json'          => $json,
                'errors'        => $arrErrores
            ];
        }

        try {
            $nodoRaiz = $xml->getName();
            if($nodoRaiz !== 'documentoSoporte') {
                $arrErrores[] = 'Nodo raíz del archivo XML no corresponde a [documentoSoporte]';
                return [
                    'clasificacion' => $cdo_clasificacion,
                    'json'          => $json,
                    'errors'        => $arrErrores
                ];
            }

            $this->armarCabecera($xml, $json, $cdo_clasificacion);
            $this->armarMediosPago($xml, $json);
            $this->armarDetalleRetencionesSugeridas($xml, $json);
            $this->armarItems($xml, $json);

            if($cdo_clasificacion == 'DS_NC') {
                $this->armarDocumentosReferencia($xml, $json, $ofe_id);
                $this->armarConceptosCorrecion($xml, $json);
            }
        } catch (\Exception $e) {
            if(!empty(libxml_get_errors()))
                $this->errorXml(libxml_get_errors(), $arrErrores);
            else
                $arrErrores[] = 'L' . $e->getLine() . ' - ' . $e->getMessage();
        }

        return [
            'clasificacion' => $cdo_clasificacion,
            'json'          => $json,
            'errors'        => $arrErrores
        ];
    }

    /**
     * Extrae la información que hace parte de la cabecera del documento.
     *
     * @param \SimpleXMLElement $xml Objeto XML desde el cual extraer la información
     * @param array $json Array en donde se guarda la información de la cabecera
     * @param string $cdoClasificacion Clasificación del documento
     * @return void
     */
    private function armarCabecera(\SimpleXMLElement $xml, array &$json, string &$cdoClasificacion): void {
        $tde_codigo = (string) $xml->xpath('//identificaciondeldocumento/tde_codigo')[0];

        if($tde_codigo != '05' && $tde_codigo != '95')
            throw new \Exception('Código [' . $tde_codigo . '] no corresponde a un tipo de documento electrónico válido para el XML');

        $cdoClasificacion = $tde_codigo == '05' ? 'DS' : 'DS_NC';
        $json = [
            'tde_codigo'                                  => $tde_codigo,
            'cdo_origen'                                  => auth()->user()->usu_type == 'INTEGRACION' ? 'INTEGRACION' : 'API',
            'top_codigo'                                  => (string) $xml->xpath('//identificaciondeldocumento/top_codigo')[0],
            'ofe_identificacion'                          => (string) $xml->xpath('//identificaciondeldocumento/ofe_identificacion')[0],
            'adq_identificacion'                          => (string) $xml->xpath('//identificaciondeldocumento/adq_identificacion')[0],
            'rfa_prefijo'                                 => (string) $xml->xpath('//identificaciondeldocumento/rfa_prefijo')[0],
            'cdo_consecutivo'                             => (string) $xml->xpath('//identificaciondeldocumento/cdo_consecutivo')[0],
            'cdo_fecha'                                   => date('Y-m-d'),
            'cdo_hora'                                    => date('H:i:s'),
            'cdo_vencimiento'                             => (string) $xml->xpath('//identificaciondeldocumento/cdo_vencimiento')[0],
            'cdo_observacion'                             => (string) $xml->xpath('//identificaciondeldocumento/cdo_observacion')[0],
            'cdo_representacion_grafica_documento'        => (string) $xml->xpath('//identificaciondeldocumento/cdo_representacion_grafica_documento')[0],
            'cdo_representacion_grafica_acuse'            => (string) $xml->xpath('//identificaciondeldocumento/cdo_representacion_grafica_acuse')[0],
            'mon_codigo'                                  => (string) $xml->xpath('//mon_codigo')[0],
            'mon_codigo_moneda_extranjera'                => !empty($xml->xpath('//mon_codigo_moneda_extranjera')) ? (string) $xml->xpath('//mon_codigo_moneda_extranjera')[0] : '0.00',
            'cdo_trm'                                     => !empty($xml->xpath('//cdo_trm')) ? (string) $xml->xpath('//cdo_trm')[0] : '0.00',
            'cdo_trm_fecha'                               => !empty($xml->xpath('//cdo_trm_fecha')) ? (string) $xml->xpath('//cdo_trm_fecha')[0] : '0.00',
            'cdo_valor_sin_impuestos'                     => (string) $xml->xpath('//cdo_valor_sin_impuestos')[0],
            'cdo_valor_sin_impuestos_moneda_extranjera'   => !empty($xml->xpath('//cdo_valor_sin_impuestos_moneda_extranjera')) ? (string) $xml->xpath('//cdo_valor_sin_impuestos_moneda_extranjera')[0] : '0.00',
            'cdo_impuestos'                               => (string) $xml->xpath('//cdo_impuestos')[0],
            'cdo_impuestos_moneda_extranjera'             => !empty($xml->xpath('//cdo_impuestos_moneda_extranjera')) ? (string) $xml->xpath('//cdo_impuestos_moneda_extranjera')[0] : '0.00',
            'cdo_total'                                   => (string) $xml->xpath('//cdo_total')[0],
            'cdo_total_moneda_extranjera'                 => !empty($xml->xpath('//cdo_total_moneda_extranjera')) ? (string) $xml->xpath('//cdo_total_moneda_extranjera')[0] : '0.00',
            'cdo_retenciones_sugeridas'                   => (string) $xml->xpath('//cdo_retenciones_sugeridas')[0],
            'cdo_retenciones_sugeridas_moneda_extranjera' => !empty($xml->xpath('//cdo_retenciones_sugeridas_moneda_extranjera')) ? (string) $xml->xpath('//cdo_retenciones_sugeridas_moneda_extranjera')[0] : '0.00',
            'cdo_redondeo'                                => (string) $xml->xpath('//cdo_redondeo')[0],
            'cdo_redondeo_moneda_extranjera'              => !empty($xml->xpath('//cdo_redondeo_moneda_extranjera')) ? (string) $xml->xpath('//cdo_redondeo_moneda_extranjera')[0] : '0.00',
            'cdo_informacion_adicional'                   => ['cdo_procesar_documento' => 'SI']
        ];

        if($cdoClasificacion == 'DS')
            $json['rfa_resolucion'] = (string) $xml->xpath('//identificaciondeldocumento/rfa_resolucion')[0];
    }

    /**
     * Extrae la información que hace parte de los medios de pago del documento.
     *
     * @param \SimpleXMLElement $xml Objeto XML desde el cual extraer la información
     * @param array $json Array en donde se guarda la información de los medios de pago
     * @return void
     */
    private function armarMediosPago(\SimpleXMLElement $xml, array &$json): void {
        $json['cdo_medios_pago'] = [];
        foreach($xml->xpath('//cdo_medios_pago') as $medioPago) {
            $json['cdo_medios_pago'][] = [
                'fpa_codigo'            => (string) $medioPago->xpath('fpa_codigo')[0],
                'mpa_codigo'            => (string) $medioPago->xpath('mpa_codigo')[0],
                'men_fecha_vencimiento' => (string) $medioPago->xpath('men_fecha_vencimiento')[0]
            ];
        }
    }

    /**
     * Extrae la información que hace parte del detalle de retenciones sugeridas del documento.
     *
     * @param \SimpleXMLElement $xml Objeto XML desde el cual extraer la información
     * @param array $json Array en donde se guarda la información del detalle de retenciones sugeridas
     * @return void
     */
    private function armarDetalleRetencionesSugeridas(\SimpleXMLElement $xml, array &$json): void {
        $json['cdo_detalle_retenciones_sugeridas'] = [];
        foreach($xml->xpath('//cdo_detalle_retenciones_sugeridas') as $retencionSugerida) {
            $valorMonedaNacional   = null;
            $valorMonedaExtranjera = null;

            if(!empty($retencionSugerida->xpath('valor_moneda_nacional/valor')))
                $valorMonedaNacional = [
                    'base'  => !empty($retencionSugerida->xpath('valor_moneda_nacional/base')) ? (string) $retencionSugerida->xpath('valor_moneda_nacional/base')[0] : '0.00',
                    'valor' => (string) $retencionSugerida->xpath('valor_moneda_nacional/valor')[0],
                ];

            if(!empty($retencionSugerida->xpath('valor_moneda_extranjera/valor')))
                $valorMonedaExtranjera = [
                    'base'  => !empty($retencionSugerida->xpath('valor_moneda_extranjera/base')) ? (string) $retencionSugerida->xpath('valor_moneda_extranjera/base')[0] : '0.00',
                    'valor' => (string) $retencionSugerida->xpath('valor_moneda_extranjera/valor')[0],
                ];

            $json['cdo_detalle_retenciones_sugeridas'][] = [
                'tipo'                    => (string) $retencionSugerida->xpath('tipo')[0],
                'razon'                   => (string) $retencionSugerida->xpath('razon')[0],
                'porcentaje'              => (string) $retencionSugerida->xpath('porcentaje')[0],
                'valor_moneda_nacional'   => $valorMonedaNacional,
                'valor_moneda_extranjera' => $valorMonedaExtranjera
            ];
        }
    }

    /**
     * Extrae la información que hace parte de los items y tributos del documento.
     *
     * @param \SimpleXMLElement $xml Objeto XML desde el cual extraer la información
     * @param array $json Array en donde se guarda la información de los items y tributos
     * @return void
     */
    private function armarItems(\SimpleXMLElement $xml, array &$json): void {
        $json['items']    = [];
        $json['tributos'] = [];
        foreach($xml->xpath('//detalle_documento') as $detalle) {
            foreach($detalle->xpath('item') as $item) {
                $json['items'][] = [
                    'ddo_tipo_item'                        => 'IP',
                    'ddo_secuencia'                        => (string) $item->xpath('ddo_secuencia')[0],
                    'cpr_codigo'                           => (string) $item->xpath('cpr_codigo')[0],
                    'ddo_codigo'                           => (string) $item->xpath('ddo_codigo')[0],
                    'ddo_descripcion_uno'                  => (string) $item->xpath('ddo_descripcion_uno')[0],
                    'und_codigo'                           => (string) $item->xpath('und_codigo')[0],
                    'ddo_valor_unitario'                   => (string) $item->xpath('ddo_valor_unitario')[0],
                    'ddo_valor_unitario_moneda_extranjera' => !empty($item->xpath('ddo_valor_unitario_moneda_extranjera')) ? (string) $item->xpath('ddo_valor_unitario_moneda_extranjera')[0] : '0.00',
                    'ddo_total'                            => (string) $item->xpath('ddo_total')[0],
                    'ddo_total_moneda_extranjera'          => !empty($item->xpath('ddo_total_moneda_extranjera')) ? (string) $item->xpath('ddo_total_moneda_extranjera')[0] : '0.00',
                    'ddo_cantidad'                         => '1',
                    'ddo_fecha_compra'                     => [
                        'fecha_compra' => date('Y-m-d'),
                        'codigo'       => '1'
                    ]
                ];
            }

            if(!empty($detalle->xpath('tributos'))) {
                foreach($detalle->xpath('tributos') as $tributo) {
                    $porcentaje = null;

                    if(!empty($tributo->xpath('iid_porcentaje')))
                        $porcentaje = [
                            'iid_base'                   => !empty($tributo->xpath('iid_base')) ? (string) $tributo->xpath('iid_base')[0] : '0.00',
                            'iid_base_moneda_extranjera' => !empty($tributo->xpath('iid_base_moneda_extranjera')) ? (string) $tributo->xpath('iid_base_moneda_extranjera')[0] : '0.00',
                            'iid_porcentaje'             => (string) $tributo->xpath('iid_porcentaje')[0]
                        ];

                    $json['tributos'][] = [
                        'ddo_secuencia'               => (string) $tributo->xpath('ddo_secuencia')[0],
                        'tri_codigo'                  => (string) $tributo->xpath('tri_codigo')[0],
                        'iid_valor'                   => (string) $tributo->xpath('iid_valor')[0],
                        'iid_valor_moneda_extranjera' => !empty($tributo->xpath('iid_valor_moneda_extranjera')) ? (string) $tributo->xpath('iid_valor_moneda_extranjera')[0] : '0.00',
                        'iid_porcentaje'              => $porcentaje
                    ];
                }
            }
        }
    }

    /**
     * Extrae la información que hace parte de los documentos referencia del documento cuando se trata de una Nota Cédito de Documento Soporte.
     *
     * @param \SimpleXMLElement $xml Objeto XML desde el cual extraer la información
     * @param array $json Array en donde se guarda la información de los documentosa referencia
     * @param int $ofe_id ID del OFE
     * @return void
     */
    private function armarDocumentosReferencia(\SimpleXMLElement $xml, array &$json, int $ofe_id): void {
        $json['cdo_documento_referencia'] = [];
        foreach($xml->xpath('//cdo_documento_referencia') as $documentoReferencia) {
            if(empty((string) $documentoReferencia->xpath('cufe')[0]))
                $cufe = $this->buscarCufeDocumentoReferencia(
                    $ofe_id,
                    (string) $documentoReferencia->xpath('prefijo')[0],
                    (string) $documentoReferencia->xpath('consecutivo')[0],
                    (string) $documentoReferencia->xpath('clasificacion')[0]
                );
            else
                $cufe = (string) $documentoReferencia->xpath('cufe')[0];

            $json['cdo_documento_referencia'][] = [
                'consecutivo'   => (string) $documentoReferencia->xpath('consecutivo')[0],
                'fecha_emision' => (string) $documentoReferencia->xpath('fecha_emision')[0],
                'prefijo'       => (string) $documentoReferencia->xpath('prefijo')[0],
                'clasificacion' => (string) $documentoReferencia->xpath('clasificacion')[0],
                'cufe'          => $cufe
            ];
        }
    }

    /**
     * Extrae la información que hace parte de los conceptos de corrección del documento cuando se trata de una Nota Cédito de Documento Soporte.
     *
     * @param \SimpleXMLElement $xml Objeto XML desde el cual extraer la información
     * @param array $json Array en donde se guarda la información de los conceptos de corrección
     * @return void
     */
    private function armarConceptosCorrecion(\SimpleXMLElement $xml, array &$json): void {
        $json['cdo_conceptos_correccion'] = [];
        foreach($xml->xpath('//cdo_conceptos_correccion') as $conceptoCorreccion) {
            $json['cdo_conceptos_correccion'][] = [
                'cco_codigo'                 => (string) $conceptoCorreccion->xpath('cco_codigo')[0],
                'cdo_observacion_correccion' => (string) $conceptoCorreccion->xpath('cdo_observacion_correccion')[0]
            ];
        }
    }

    /**
     * Busca el CUFE de un documento referencia.
     *
     * @param integer $ofe_id ID del OFE
     * @param string $prefijo Prefijo del documento referencia
     * @param string $consecutivo Consecutivo del documento referencia
     * @param string $clasificacion Clasificación del documento referencia
     * @return string
     */
    private function buscarCufeDocumentoReferencia(int $ofe_id, string $prefijo, string $consecutivo, string $clasificacion): string {
        $documento = EtlCabeceraDocumentoDaop::select(['cdo_cufe'])
            ->where('ofe_id', $ofe_id)
            ->where('rfa_prefijo', $prefijo)
            ->where('cdo_consecutivo', $consecutivo)
            ->where('cdo_clasificacion', $clasificacion)
            ->first();

        if($documento)
            return $documento->cdo_cufe;
        else {
            $documento = EtlFatDocumentoDaop::select(['cdo_cufe'])
                ->where('ofe_id', $ofe_id)
                ->where('rfa_prefijo', $prefijo)
                ->where('cdo_consecutivo', $consecutivo)
                ->where('cdo_clasificacion', $clasificacion)
                ->first();

            if($documento)
                return $documento->cdo_cufe;
            else
                return '';
        }
    }
}