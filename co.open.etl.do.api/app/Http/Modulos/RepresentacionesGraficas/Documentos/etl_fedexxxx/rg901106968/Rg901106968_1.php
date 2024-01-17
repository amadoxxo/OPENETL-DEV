<?php
/**
 * User: Juan José Hernández
 * Date: 17/03/2023
 * Time: 08:12 AM
 */

namespace App\Http\Modulos\RepresentacionesGraficas\Documentos\etl_fedexxxx\rg901106968;

use App\Http\Traits\NumToLetrasEngine;
use App\Http\Modulos\RepresentacionesGraficas\Core\RgBase;


class Rg901106968_1 extends RgBase
{

    public function getPdf() {

        //Extrayendo información de cabecera de la factura
        extract($this->getDatos());

        //PDF
        $fpdf = $this->pdfManager();
        $fpdf->AcceptPageBreak();
        $fpdf->SetFont('Arial','',8);
        $fpdf->AliasNbPages();
        $fpdf->SetMargins(0,0,0);
        $fpdf->SetAutoPageBreak(true,10);

        $fpdf->setImageHeader($this->getFullImage('logo'.$ofe_identificacion.'.png'));
        $datosComprobante['no_valido'] = $this->getFullImage("no_valido.png");

        //Encabezado
        $datosComprobante['cdo_tipo']             = $cdo_tipo;        
        $datosComprobante['ofe_nit']              = $ofe_nit;
        $datosComprobante['ofe_dir']              = $ofe_dir;
        $datosComprobante['ofe_tel']              = $ofe_tel;
        $datosComprobante['oferente']             = $oferente;
        $datosComprobante['ofe_ciudad_pais']      = "$ofe_mun, $ofe_pais";
        $datosComprobante['adquirente']           = $adquirente;
        $datosComprobante['adq_dir']              = $adq_dir;
        $datosComprobante['adq_nit']              = $adq_nit;
        $datosComprobante['adq_ciudad_pais']      = "$adq_mun, $adq_pais";
        $datosComprobante['adq_codigo_postal']    = $adq_codigo_postal;
        $datosComprobante['fecha_hora_documento'] = $fecha_hora_documento;
        $datosComprobante['fecha_vencimiento']    = $fecha_vencimiento;
        $datosComprobante['cdo_tipo_nombre']      = $cdo_tipo_nombre;
        $datosComprobante['rfa_prefijo']          = $rfa_prefijo;
        $datosComprobante['cdo_consecutivo']      = $cdo_consecutivo;

        $datosComprobante['qr']              = "";
        $datosComprobante['signaturevalue']  = "";
        $datosComprobante['cufe']            = "";
        if($signaturevalue != '' && $qr !=''){
            $datosComprobante['qr']              = $qr;
            $datosComprobante['signaturevalue']  = $signaturevalue;
            $datosComprobante['cufe']            = $cufe;
        }

        $datosComprobante['razon_social_pt'] = $razon_social_pt;
        $datosComprobante['nit_pt']          = $nit_pt;

        $datosComprobante['nombre_software'] = "";
        if (isset($software_pt->sft_nombre) && $software_pt->sft_nombre != "" ){
            $datosComprobante['nombre_software'] = $software_pt->sft_nombre;
        }

        //Extrayendo información de Forma y medios de pago
        $datosComprobante['forma_pago'] = "";
        $datosComprobante['medio_pago'] = "";
        foreach ($medios_pagos_documento as $key => $medios_pagos){
            //Forma
            $forma = $medios_pagos['forma'];
            $datosComprobante['forma_pago'] = (isset($forma['fpa_descripcion']) && $forma['fpa_descripcion'] != '') ? $forma['fpa_descripcion'] : '';
            //Medio
            $medio = $medios_pagos['medio'];
            $datosComprobante['medio_pago'] = (isset($medio['mpa_descripcion']) && $medio['mpa_descripcion'] != '') ? $medio['mpa_descripcion'] : '';
        }

        try {
            $observacion_decode = (array) json_decode($observacion);
        } catch (\Throwable $th) {
            $observacion_decode = [];
        }

        $strValidacionDian = "";
        if (isset($cdo_fecha_validacion_dian) && $cdo_fecha_validacion_dian != "") {
            $fecha_dian = explode(" ", $cdo_fecha_validacion_dian);
            $strValidacionDian = $fecha_dian[0] ." / ".$fecha_dian[1];
        }

        if ($cdo_tipo == "NC" || $cdo_tipo == "ND") {
            list($factura, $fecha, $cufe) = $this->getDocumentoReferencia($cdo_documento_referencia);
            $datosComprobante['consecutivo_ref']    = $factura;
            $datosComprobante['fecha_emision']      = $fecha;
            $datosComprobante['cufe_ref']           = $cufe;
        }

        $datosComprobante['cabecera'] = "";
        if(isset($ofe_representacion_grafica->cabecera) && $ofe_representacion_grafica->cabecera != ""){
            $datosComprobante['cabecera'] = $ofe_representacion_grafica->cabecera;
        }
        
        $datosComprobante['nota_final'] = "";
        if(isset($ofe_representacion_grafica->nota_final) && $ofe_representacion_grafica->nota_final != ""){
            $datosComprobante['nota_final'] = $ofe_representacion_grafica->nota_final;
        }

        $datosComprobante['resolucion'] = "";
        if(isset($ofe_representacion_grafica->resolucion) && $ofe_representacion_grafica->resolucion != ""){
            $date1  = strtotime($ofe_resolucion_fecha);
            $date2  = strtotime($ofe_resolucion_fecha_hasta);
            $diff   = $date2 - $date1;
            $meses  = (string) round($diff / (60 * 60 * 24 * 30.5));

            $arrConv = array(
                "{res}", 
                "{res_fecha_desde}", 
                "{res_fecha_hasta}",
                "{meses}", 
                "{res_prefijo}", 
                "{res_desde}", 
                "{res_hasta}"
            );

            $datosComprobante['resolucion']  = array(
                $ofe_resolucion, 
                date("Y-m-d",strtotime($ofe_resolucion_fecha)), 
                date("Y-m-d", strtotime($ofe_resolucion_fecha_hasta)),
                $meses, 
                $ofe_resolucion_prefijo, 
                $ofe_resolucion_desde, 
                $ofe_resolucion_hasta
            ); 

            $datosComprobante['resolucion'] = str_replace($arrConv, $datosComprobante['resolucion'], $ofe_representacion_grafica->resolucion);
        }

        // $datosComprobante['modo_transporte'] = "";
        // if(isset($cdo_informacion_adicional->modo_transporte) && $cdo_informacion_adicional->modo_transporte != ""){
        //     $datosComprobante['modo_transporte'] = $cdo_informacion_adicional->modo_transporte;
        // }

        if ($cdo_moneda_extranjera != 'COP' && $cdo_moneda_extranjera != "") {
            $intIva        = $this->parserNumberController($iva_moneda_extranjera);
            $intTotalPagar = $this->parserNumberController($valor_a_pagar_moneda_extranjera);
            $strMoneda     = $cdo_moneda_extranjera;
            $nDecimal      = 2;
        } else {
            $intIva        = $this->parserNumberController($iva);
            $intTotalPagar = $this->parserNumberController($valor_a_pagar);
            $strMoneda     = $cdo_moneda;
            $nDecimal      = 0;
        }
        $datosComprobante['moneda'] = $strMoneda;

        $fpdf->datosComprobante = $datosComprobante;

        //Notas finales
        $intTotalAnticipo = 0;
        if(isset($cdo_informacion_adicional->anticipo_recibido) && is_numeric($cdo_informacion_adicional->anticipo_recibido)){
            $intTotalAnticipo = $cdo_informacion_adicional->anticipo_recibido;
        }

        $intSaldoFavor = 0;
        if(isset($cdo_informacion_adicional->saldo_a_favor) && is_numeric($cdo_informacion_adicional->saldo_a_favor)){
            $intSaldoFavor = $cdo_informacion_adicional->saldo_a_favor;
        }

        // Totales Retenciones
        $intTotalReteIva   = 0;
        $intTotalReteIca   = 0;
        $intPorcenReteIca  = 0;
        $intPorcenReteIva  = 0;
        $intTotalReteFte11 = 0;
        $intTotalReteFte4  = 0;
        $data = $this->getCargoDescuentosRetencionesTipo($cdo_id, self::MODO_CONSULTA_CABECERA, self::MODO_PORCENTAJE_DETALLAR);
        foreach($data as $retencion => $grupo){
            foreach ($grupo as $porcentaje => $valores){
                $valores['valor'] = ($cdo_moneda_extranjera != 'COP' && $cdo_moneda_extranjera != "") ? $valores['valor_extranjera'] : $valores['valor'];

                switch($retencion){
                    case 'RETEIVA':
                        $intPorcenReteIva = $porcentaje;
                        $intTotalReteIva += $valores['valor'];
                    break;
                    case 'RETEICA':
                        $intPorcenReteIca = $porcentaje;
                        $intTotalReteIca += $valores['valor'];
                    break;
                    case 'RETEFUENTE':
                        if ($porcentaje == "4.00") {
                            $intTotalReteFte4 += $valores['valor'];
                        } elseif ($porcentaje == "11.00") {
                            $intTotalReteFte11 += $valores['valor'];
                        }
                    break;
                    default:
                    break;
                }
            }
        }

        $fpdf->AddPage('P','Letter');
        $posx  = $fpdf->posx;
        $posy  = $fpdf->nPosYFin;
        $posfin = 203;

        // $items = array_merge($items,$items);
        // $items = array_merge($items,$items,$items);
        // $items = array_merge($items,$items,$items);

        // Separo los items en PCC e IP
        $items_pcc = array_filter($items, function($item){
            return ($item['ddo_tipo_item'] == 'PCC' || $item['ddo_tipo_item'] == 'GMF');
        });
        $items_ip = array_filter($items, function($item){
            return ($item['ddo_tipo_item'] == 'IP' || $item['ddo_tipo_item'] == ''); 
        });

        // Contador de items
        $contItem = 0;

        // Totales
        $intTotalPcc = 0;
        $intTotalIp = 0;

        // Items
        if (count($items_pcc) > 0) {
            $fpdf->SetFont('Arial','B',6);
            $fpdf->setXY($posx, $posy - 3);
            $fpdf->Cell(65,6,"INGRESOS TERCEROS",0,0,'L');

            //Propiedades de la tabla
            $fpdf->SetWidths(array(10, 20, 70, 20, 15, 30, 30));
            $fpdf->SetAligns(array("C", "C", "L", "C", "C", "R", "R"));
            $fpdf->SetLineHeight(3.4);
            $fpdf->SetFont('Arial','',6);

            $fpdf->setXY($posx, $posy + 2);
            foreach ($items_pcc as $item) {
                $contItem++;
    
                if($fpdf->getY() > $posfin-3){
                    $fpdf->AddPage('P','Letter');
                    $fpdf->posy = 213;
                    $fpdf->setXY($posx,$posy);
                }

                if ($cdo_moneda_extranjera != 'COP' && $cdo_moneda_extranjera != "") {
                    $intVlrTotal    = $item['ddo_total_moneda_extranjera'];
                    $intVlrUnitario = $item['ddo_valor_unitario_moneda_extranjera'];
                } else {
                    $intVlrTotal    = $item['ddo_total'];
                    $intVlrUnitario = $item['ddo_valor_unitario'];
                }

                $intTotalPcc += $intVlrTotal;

                $fpdf->setX($posx);
                $fpdf->Row([
                    $contItem,
                    utf8_decode($item['ddo_codigo']),
                    utf8_decode(mb_strtoupper($item['ddo_descripcion_uno'])),
                    utf8_decode(ucwords($this->getUnidad($item['und_id'], 'und_descripcion'))),
                    number_format($item['ddo_cantidad'], 0),
                    number_format($intVlrUnitario, $nDecimal, ',', '.'),
                    number_format($intVlrTotal, $nDecimal, ',', '.')
                ]);
            }

            $fpdf->SetFont('Arial','B',6);
            $fpdf->setXY($posx, $fpdf->GetY());
            $fpdf->Cell(180,6,"TOTAL INGRESOS TERCEROS",0,0,'R');
            $fpdf->Cell(15,6,number_format($intTotalPcc, $nDecimal, ',', '.'),0,0,'R');
            $fpdf->Ln(3);
        }

        if (isset($items_ip) && count($items_ip) > 0) {            
            $fpdf->SetFont('Arial','B',6);
            $fpdf->setXY($posx,$fpdf->GetY() + 2);
            $fpdf->Cell(65,6,"INGRESOS PROPIOS",0,0,'L');

            // Propiedades de la tabla
            $fpdf->SetWidths(array(10, 20, 70, 20, 15, 30, 30));
            $fpdf->SetAligns(array("C", "C", "L", "C", "C", "R", "R"));
            $fpdf->SetLineHeight(3.4);
            $fpdf->setXY($posx, $fpdf->GetY() + 5);
            $fpdf->SetFont('Arial','',6);

            foreach ($items_ip as $item) {
                $contItem++;
    
                if($fpdf->getY() > $posfin-3){
                    $fpdf->AddPage('P','Letter');
                    $fpdf->posy = 213;
                    $fpdf->setXY($posx,$posy);
                }

                if ($cdo_moneda_extranjera != 'COP' && $cdo_moneda_extranjera != "") {
                    $intVlrTotal    = $item['ddo_total_moneda_extranjera'];
                    $intVlrUnitario = $item['ddo_valor_unitario_moneda_extranjera'];
                } else {
                    $intVlrTotal    = $item['ddo_total'];
                    $intVlrUnitario = $item['ddo_valor_unitario'];
                }

                $intTotalIp += $intVlrTotal;

                $fpdf->setX($posx);
                $fpdf->Row([
                    $contItem,
                    utf8_decode($item['ddo_codigo']),
                    utf8_decode(mb_strtoupper($item['ddo_descripcion_uno'])),
                    utf8_decode(ucwords($this->getUnidad($item['und_id'], 'und_descripcion'))),
                    number_format($item['ddo_cantidad'], 0),
                    number_format($intVlrUnitario, $nDecimal, ',', '.'),
                    number_format($intVlrTotal, $nDecimal, ',', '.')
                ]);
            }

            $fpdf->SetFont('Arial','B', 6);
            $fpdf->setXY($posx, $fpdf->GetY());
            $fpdf->Cell(180,6,"TOTAL INGRESOS PROPIOS",0,0,'R');
            $fpdf->Cell(15,6,number_format($intTotalIp, $nDecimal, ',', '.'),0,0,'R');
            $fpdf->Ln(3);
        }

        if ($fpdf->GetY() > 169) {
            $fpdf->AddPage('P', 'Letter');
            $fpdf->posy = 212;
        }

        $fpdf->setXY($posx+1, 166);
        $fpdf->SetFont('Arial', '', 6);
        $fpdf->Cell(8, 3, $contItem, 0, 0, 'C');

        $fpdf->Line($posx, 169, $posx + 195, 169);

        $posy = 170;

        // Cuadro de valores
        $nTotalIngProTer = $intTotalPcc + $intTotalIp;
        $nTotalRetencion = ($intTotalReteIva + $intTotalReteIca + $intTotalReteFte4);
        $nTotalFactura   = ($nTotalIngProTer + $intIva) - $nTotalRetencion;

        $fpdf->setXY($posx + 135, $posy + 1);
        $fpdf->SetFont('Arial', 'B', 6);
        $fpdf->Cell(40, 4, "ING. PRO Y TERCEROS", 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($nTotalIngProTer, $nDecimal, '.', ','), 0, 0, 'R');
        $fpdf->Ln(3);
        $fpdf->setX($posx + 135);
        $fpdf->SetFont('Arial', '', 6);
        $fpdf->Cell(40, 4, "IVA ".number_format($porcentaje_iva, 2)."%", 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($intIva, $nDecimal, '.', ','), 0, 0, 'R');

        $posy = $fpdf->GetY() + 4;
        $fpdf->setXY($posx + 135, $posy);
        $fpdf->Cell(40, 4, utf8_decode("RETENCIÓN FUENTE ").number_format($intTotalReteFte4, 2)."%", 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($intTotalReteFte4, $nDecimal, '.', ','), 0, 0, 'R');
        $fpdf->Ln(3);
        $fpdf->setX($posx + 135);
        $fpdf->Cell(40, 4, utf8_decode("RETENCIÓN IVA ").number_format($intPorcenReteIva, 2)."%", 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($intTotalReteIva, $nDecimal, '.', ','), 0, 0, 'R');
        $fpdf->Ln(3);
        $fpdf->setX($posx + 135);
        $fpdf->Cell(40, 4, utf8_decode("RETENCIÓN ICA ").number_format($intPorcenReteIca, 2)."%", 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($intTotalReteIca, $nDecimal, '.', ','), 0, 0, 'R');
        $fpdf->Ln(3);
        $fpdf->setX($posx + 135);
        $fpdf->SetFont('Arial', 'B', 6);
        $fpdf->Cell(40, 4, "TOTAL RETENCIONES", 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($nTotalRetencion, $nDecimal, '.', ','), 0, 0, 'R');
        
        $posy = $fpdf->GetY() + 4;
        $fpdf->setXY($posx + 135, $posy);
        $fpdf->Cell(40, 4, "TOTAL", 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($nTotalFactura, $nDecimal, '.', ','), 0, 0, 'R');
        $fpdf->Ln(3);
        $fpdf->setX($posx + 135);
        $fpdf->Cell(40, 4, "ANTICIPO", 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($intTotalAnticipo, $nDecimal, '.', ','), 0, 0, 'R');
        $fpdf->Ln(3);
        $fpdf->setX($posx + 135);
        $nSaldo = $intSaldoFavor > 0 ? $intSaldoFavor : $intTotalPagar;
        $fpdf->Cell(40, 4, "SALDO A ".($intSaldoFavor > 0 ? 'FAVOR' : 'PAGAR'), 0, 0, 'L');
        $fpdf->Cell(20, 4, number_format($nSaldo, $nDecimal, '.', ','), 0, 0, 'R');

        $posy = $fpdf->GetY() + 5;
        $fpdf->Line($posx, $posy, $posx + 195, $posy);

        $strValorLetras = NumToLetrasEngine::num2letras(number_format($nSaldo, $nDecimal, '.', ''), false, true, $strMoneda);
        $fpdf->setXY($posx, $posy + 1);
        $fpdf->SetFont('Arial', 'B', 6);
        $fpdf->Cell(20, 4, "VALOR EN LETRA: ", 0, 0, 'L');
        $fpdf->SetFont('Arial', '', 6);
        $fpdf->MultiCell(195, 4, utf8_decode($strValorLetras), 0, 'L');

        $posy = $fpdf->GetY() + 1;
        $fpdf->Line($posx, $posy, $posx + 195, $posy);

        $fpdf->setXY($posx, $posy + 1);
        $fpdf->MultiCell(195, 3, utf8_decode("OBSERVACIONES\n".implode("\n",$observacion_decode)), 0, 'L');

        $fpdf->Ln(3);
        $fpdf->setX($posx);
        $fpdf->MultiCell(195, 3, utf8_decode("Fecha y Hora Validación DIAN: ". $strValidacionDian), 0, 'L');

        $posy = $fpdf->GetY() + 1;
        $fpdf->Line($posx, $posy, $posx + 195, $posy);

        if($datosComprobante['cdo_tipo'] == "FC") {
            $fpdf->setXY($posx, $posy + 1);
            $fpdf->MultiCell(195, 3, utf8_decode($datosComprobante['nota_final']), 0, 'L');
    
            $posy = $fpdf->GetY() + 1;
            $fpdf->Line($posx, $posy, $posx + 195, $posy);
    
            $fpdf->setXY($posx, $posy + 1);
            $fpdf->MultiCell(195, 3, utf8_decode($datosComprobante['resolucion']), 0, 'L');
        } else {
            $fpdf->setXY($posx, $posy + 1);
            $fpdf->SetFont('Arial', 'B', 6);
            $fpdf->Cell(30, 4, "AFECTA DOCUMENTO: ", 0, 0, 'L');
            $fpdf->SetFont('Arial', '', 6);
            $fpdf->Cell(50, 4, $datosComprobante['consecutivo_ref'], 0, 0, 'L');

            $fpdf->SetFont('Arial', 'B', 6);
            $fpdf->Cell(30, 4, "FECHA DOCUMENTO: ", 0, 0, 'L');
            $fpdf->SetFont('Arial', '', 6);
            $fpdf->Cell(50, 4, $datosComprobante['fecha_emision'], 0, 0, 'L');

            $posy = $fpdf->GetY() + 6;
            $fpdf->setXY($posx, $posy);
            $fpdf->SetFont('Arial', 'B', 6);
            $fpdf->Cell(8, 4, "CUFE: ", 0, 0, 'L');
            $fpdf->SetFont('Arial', '', 6);
            $fpdf->Cell(187, 4, $datosComprobante['cufe_ref'], 0, 0, 'L');
            $fpdf->Ln(3);

            $posy = $fpdf->GetY() + 2;
            $fpdf->Line($posx, $posy, $posx + 195, $posy);
        }

        $posy = 235;
        if($datosComprobante['signaturevalue'] != "" && $datosComprobante['qr'] != ""){
            $dataURI = "data:image/png;base64, ".base64_encode((string) \QrCode::format('png')->size(82)->margin(0)->generate($datosComprobante['qr']));
            $pic = $fpdf->getImage($dataURI);
            if ($pic!==false) $fpdf->Image($pic[0], $posx + 140, $posy + 5,29,29, $pic[1]);
            $fpdf->setXY($posx, $posy + 6);
            $fpdf->SetFont('Arial','B',6);
            $fpdf->Cell(110,3,utf8_decode("REPRESENTACIÓN IMPRESA DE LA ". mb_strtoupper($datosComprobante['cdo_tipo_nombre'] ." ELECTRÓNICA")),0,0,'L');
            $fpdf->Ln(3);
            $fpdf->setX($posx);
            $fpdf->SetFont('Arial','B',6);
            $fpdf->Cell(110,3,utf8_decode("FIRMA ELECTRÓNICA:"),0,0,'L');
            $fpdf->Ln(4);
            $fpdf->setX($posx);
            $fpdf->SetFont('Arial','',6);
            $fpdf->MultiCell(120,3,$datosComprobante['signaturevalue'],0,'J');
            $fpdf->Ln(2);
            $fpdf->setXY($posx + 170, $posy + 6);
            $fpdf->SetFont('Arial','B',6);
            $fpdf->MultiCell(25,3, ($datosComprobante['cdo_tipo'] == "FC") ? "CUFE:" : "CUDE:",0,'L');
            $fpdf->setX($posx + 170);
            $fpdf->SetFont('Arial','',5);
            $fpdf->MultiCell(25,2.5,utf8_decode($datosComprobante['cufe']),0,'L');
        }

        return ['error' => false, 'pdf' => $fpdf->Output('S')];
    }
}