<?php
namespace App\Http\Modulos\Recepcion\TransmisionErp\etl_valoremx\Valorem;

class ValoremXmlTransmitir {
    /**
     * Constructor de la clase
     *
     * @param Collection $oferente Colección con información del OFE en procesamiento
     */
    public function __construct() {
        //
    }

    /**
     * Arma el xml del documento que se debe transmitir al ERP
     *
     * @param Collection $documento Colección con la información del documento a transmitir
     * @return DOMDocument
     */
    public function armarXml($documento) {
        $oDomtree = new \DOMDocument('1.0', 'UTF-8');
        $oDomtree->xmlStandalone = false;

        $cVarNs   = "http://www.w3.org/2000/xmlns/";
        $oXmlRaiz = $oDomtree->createElementNs('urn:OpenETL.com:sd:DocumentosDian', 'urn:Invoice');
        $oDomtree->appendChild($oXmlRaiz);
        $oXmlRaiz->setAttributeNS($cVarNs ,'xmlns:soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $oXmlRaiz->setAttributeNS($cVarNs ,'xmlns:urn', 'urn:OpenETL.com:sd:DocumentosDian');

        // IDdoc
        $oXmlRaiz_IDdoc = $oDomtree->createElement('IDdoc');
        $oXmlRaiz->appendChild($oXmlRaiz_IDdoc);

        $oXmlRaiz_IDdoc_SerieNumero = $oDomtree->createElement('SerieNumero', $documento->rfa_prefijo . $documento->cdo_consecutivo);
        $oXmlRaiz_IDdoc->appendChild($oXmlRaiz_IDdoc_SerieNumero);

        $oXmlRaiz_IDdoc_FechaEmis = $oDomtree->createElement('FechaEmis', $documento->cdo_fecha);
        $oXmlRaiz_IDdoc->appendChild($oXmlRaiz_IDdoc_FechaEmis);

        $oXmlRaiz_IDdoc_HoraEmis = $oDomtree->createElement('HoraEmis', $documento->cdo_hora);
        $oXmlRaiz_IDdoc->appendChild($oXmlRaiz_IDdoc_HoraEmis);

        $oXmlRaiz_IDdoc_TipoDocText = $oDomtree->createElement('TipoDocText', $documento->getTipoDocumentoElectronico->tde_descripcion);
        $oXmlRaiz_IDdoc->appendChild($oXmlRaiz_IDdoc_TipoDocText);

        $oXmlRaiz_IDdoc_CUFE = $oDomtree->createElement('CUFE', $documento->cdo_cufe);
        $oXmlRaiz_IDdoc->appendChild($oXmlRaiz_IDdoc_CUFE);
        
        // Detalle
        $oXmlRaiz_Detalle = $oDomtree->createElement('Detalle');
        $oXmlRaiz->appendChild($oXmlRaiz_Detalle);

        if(!empty($documento->getRepDadDocumentosDaop->toArray())) {
            $ordenCompra = isset($documento->getRepDadDocumentosDaop[0]->dad_orden_referencia['referencia']) ? $documento->getRepDadDocumentosDaop[0]->dad_orden_referencia['referencia'] : '';
        } else {
            $ordenCompra = '';
        }
        $oXmlRaiz_Detalle_OrdenCompra = $oDomtree->createElement('OrdenCompra', $this->codificarCadena($ordenCompra));
        $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_OrdenCompra);

        if(!empty($documento->cdo_documento_referencia)) {
            $documentoReferencia = json_decode($documento->cdo_documento_referencia);
            $referencia = isset($documentoReferencia[0]->prefijo) && isset($documentoReferencia[0]->consecutivo) ? $documentoReferencia[0]->prefijo . $documentoReferencia[0]->consecutivo : '';
        } else {
            $referencia = '';
        }
        $oXmlRaiz_Detalle_Referencia = $oDomtree->createElement('Referencia', $this->codificarCadena($referencia));
        $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_Referencia);

        $oXmlRaiz_Detalle_NitEmisor = $oDomtree->createElement('NitEmisor', $this->codificarCadena($documento->getConfiguracionProveedor->pro_identificacion));
        $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_NitEmisor);

        $oXmlRaiz_Detalle_NmbEmisor = $oDomtree->createElement('NmbEmisor', $this->codificarCadena($documento->getConfiguracionProveedor->pro_razon_social));
        $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_NmbEmisor);

        // $oXmlRaiz_Detalle_NmbEmisor = $oDomtree->createElement('NmbEmisor');
        // $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_NmbEmisor);
        // $oXmlRaiz_Detalle_NmbEmisor_CData = $oDomtree->createCDATASection($documento->getConfiguracionProveedor->pro_razon_social);
        // $oXmlRaiz_Detalle_NmbEmisor->appendChild($oXmlRaiz_Detalle_NmbEmisor_CData);

        $oXmlRaiz_Detalle_NitReceptor = $oDomtree->createElement('NitReceptor', $this->codificarCadena($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_identificacion));
        $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_NitReceptor);

        $oXmlRaiz_Detalle_NmbRecep = $oDomtree->createElement('NmbRecep', $this->codificarCadena($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social));
        $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_NmbRecep);

        // $oXmlRaiz_Detalle_NmbRecep = $oDomtree->createElement('NmbRecep');
        // $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_NmbRecep);
        // $oXmlRaiz_Detalle_NmbRecep_CData = $oDomtree->createCDATASection($documento->getConfiguracionObligadoFacturarElectronicamente->ofe_razon_social);
        // $oXmlRaiz_Detalle_NmbRecep->appendChild($oXmlRaiz_Detalle_NmbRecep_CData);

        $oXmlRaiz_Detalle_Moneda = $oDomtree->createElement('Moneda', $documento->getParametrosMoneda->mon_codigo);
        $oXmlRaiz_Detalle->appendChild($oXmlRaiz_Detalle_Moneda);
        
        // Totales
        $oXmlRaiz_Totales = $oDomtree->createElement('Totales');
        $oXmlRaiz->appendChild($oXmlRaiz_Totales);

        $oXmlRaiz_Totales_VlrDescuento = $oDomtree->createElement('VlrDescuento', $documento->cdo_descuentos);
        $oXmlRaiz_Totales->appendChild($oXmlRaiz_Totales_VlrDescuento);

        $oXmlRaiz_Totales_VlrImpuestos = $oDomtree->createElement('VlrImpuestos', $documento->cdo_impuestos);
        $oXmlRaiz_Totales->appendChild($oXmlRaiz_Totales_VlrImpuestos);

        $oXmlRaiz_Totales_VlrAnticipo = $oDomtree->createElement('VlrAnticipo', $documento->cdo_anticipo);
        $oXmlRaiz_Totales->appendChild($oXmlRaiz_Totales_VlrAnticipo);

        $oXmlRaiz_Totales_VlrTotal = $oDomtree->createElement('VlrTotal', $documento->cdo_total);
        $oXmlRaiz_Totales->appendChild($oXmlRaiz_Totales_VlrTotal);
        
        $oDomtree->formatOutput = false;
        $xmlTransmitir = $oDomtree->saveXML($oDomtree->documentElement);

        $soapHeader    = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:OpenETL.com:sd:DocumentosDian"><soapenv:Header/><soapenv:Body>';
        $soapFooter    = '</soapenv:Body></soapenv:Envelope>';
        $xmlTransmitir = $soapHeader . $xmlTransmitir . $soapFooter; // Full SOAP Request

        return $xmlTransmitir;
    }

    /**
     * Codifica una cadena.
     * 
     * @param string $cadena Cadena a codificar
     * @return string $cadena Cadena codificada
     */
    public function codificarCadena($cadena){
      // return htmlspecialchars(utf8_encode($cadena), ENT_QUOTES, 'UTF-8');
      return htmlspecialchars($cadena, ENT_QUOTES, 'UTF-8');
    }
}