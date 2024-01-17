<?php
/**
 * User: Jhon Escobar
 * Date: 24/08/2020
 * Time: 02:30 PM
 */

namespace App\Http\Modulos\RepresentacionesGraficas\Documentos\etl_acodexxx\rg800116195;

use App\Http\Modulos\RepresentacionesGraficas\Core\RgBase;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use App\Http\Traits\NumToLetrasEngine;


class Rg800116195_1 extends RgBase
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
        $datosComprobante['cdo_tipo']    = $cdo_tipo;        
        $datosComprobante['ofe_nit']     = $ofe_nit;
        $datosComprobante['ofe_dir']     = $ofe_dir;
        $datosComprobante['ofe_tel']     = $ofe_tel;
        $datosComprobante['ofe_correo']  = $ofe_correo;
        $datosComprobante['adquirente']  = $adquirente;
        $datosComprobante['adq_dir']     = $adq_dir;
        $datosComprobante['adq_nit']     = $adq_nit;
        $datosComprobante['adq_mun']     = $adq_mun;

        $datosComprobante['adq_tel']     = $adq_tel;
        $datosComprobante['fecha_hora_documento'] = $fecha_hora_documento;
        $datosComprobante['fecha_vencimiento']    = $fecha_vencimiento;
        $datosComprobante['cdo_tipo_nombre']      = $cdo_tipo_nombre;
        $datosComprobante['rfa_prefijo']          = $rfa_prefijo;
        $datosComprobante['cdo_consecutivo']      = $cdo_consecutivo;
        $datosComprobante['cdo_trm']              = $cdo_trm;
        $datosComprobante['adq_codigo_postal']    = $adq_codigo_postal;
        $datosComprobante['ofe_codigo_postal']    = $ofe_codigo_postal;

        $datosComprobante['qr']              = "";
        $datosComprobante['signaturevalue']  = "";
        $datosComprobante['cufe']            = "";
        if($signaturevalue != '' && $qr !=''){
            $datosComprobante['qr']              = $qr;
            $datosComprobante['signaturevalue']  = $signaturevalue;
            $datosComprobante['cufe']            = $cufe;
        }

        $datosComprobante['razon_social_pt']      = $razon_social_pt;
        $datosComprobante['nit_pt']               = $nit_pt;

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

        $datosComprobante['texto_cabecera'] = "";
        if(isset($ofe_representacion_grafica->texto_cabecera) && $ofe_representacion_grafica->texto_cabecera != ""){
            $datosComprobante['texto_cabecera'] = $ofe_representacion_grafica->texto_cabecera;
        }
        
        $datosComprobante['actividad_economica'] = "";
        if(isset($ofe_representacion_grafica->actividad_economica) && $ofe_representacion_grafica->actividad_economica != ""){
            $datosComprobante['actividad_economica'] = $ofe_representacion_grafica->actividad_economica;
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
                "{res_prefijo}", 
                "{res_hasta}"
            );

            $datosComprobante['resolucion']  = array(
                $ofe_resolucion, 
                date("Y/m/d",strtotime($ofe_resolucion_fecha)), 
                date("Y/m/d", strtotime($ofe_resolucion_fecha_hasta)),
                $meses, 
                $ofe_resolucion_prefijo, 
                $ofe_resolucion_desde, 
                $ofe_resolucion_prefijo, 
                $ofe_resolucion_hasta
            ); 

            $datosComprobante['resolucion'] = str_replace($arrConv, $datosComprobante['resolucion'], $ofe_representacion_grafica->resolucion);
        }

        $datosComprobante['nota_final_1'] = "";
        if(isset($ofe_representacion_grafica->nota_final_1) && $ofe_representacion_grafica->nota_final_1 != ""){
            $datosComprobante['nota_final_1'] = $ofe_representacion_grafica->nota_final_1;
        }

        $datosComprobante['oficina_principal_1'] = "";
        if(isset($ofe_representacion_grafica->oficina_principal_1) && $ofe_representacion_grafica->oficina_principal_1 != ""){
            $datosComprobante['oficina_principal_1'] = $ofe_representacion_grafica->oficina_principal_1;
        }

        $datosComprobante['sucursales_1'] = "";
        if(isset($ofe_representacion_grafica->sucursales_1) && $ofe_representacion_grafica->sucursales_1 != ""){
            $datosComprobante['sucursales_1'] = $ofe_representacion_grafica->sucursales_1;
        }

        $datosComprobante['sucursales_2'] = "";
        if(isset($ofe_representacion_grafica->sucursales_2) && $ofe_representacion_grafica->sucursales_2 != ""){
            $datosComprobante['sucursales_2'] = $ofe_representacion_grafica->sucursales_2;
        }

        $datosComprobante['pagina'] = "";
        if(isset($ofe_representacion_grafica->pagina) && $ofe_representacion_grafica->pagina != ""){
            $datosComprobante['pagina'] = $ofe_representacion_grafica->pagina;
        }

        $datosComprobante['nota_final_2'] = "";
        if(isset($ofe_representacion_grafica->nota_final_2) && $ofe_representacion_grafica->nota_final_2 != ""){
            $datosComprobante['nota_final_2'] = str_replace("{ofe_correo}", $ofe_correo, $ofe_representacion_grafica->nota_final_2);
        }

        $datosComprobante['modo_transporte'] = "";
        if(isset($cdo_informacion_adicional->modo_transporte) && $cdo_informacion_adicional->modo_transporte != ""){
            $datosComprobante['modo_transporte'] = $cdo_informacion_adicional->modo_transporte;
        }

        $datosComprobante['do'] = "";
        if(isset($cdo_informacion_adicional->do) && $cdo_informacion_adicional->do != ""){
            $datosComprobante['do'] = $cdo_informacion_adicional->do;
        }

        $datosComprobante['valor_aduana_us'] = "";
        if(isset($cdo_informacion_adicional->valor_aduana_us) && $cdo_informacion_adicional->valor_aduana_us != ""){
            $datosComprobante['valor_aduana_us'] = $cdo_informacion_adicional->valor_aduana_us;
        }

        $datosComprobante['valor_aduana_pesos'] = "";
        if(isset($cdo_informacion_adicional->valor_aduana_pesos) && $cdo_informacion_adicional->valor_aduana_pesos != ""){
            $datosComprobante['valor_aduana_pesos'] = $cdo_informacion_adicional->valor_aduana_pesos;
        }

        $datosComprobante['peso_bruto'] = "";
        if(isset($cdo_informacion_adicional->peso_bruto) && $cdo_informacion_adicional->peso_bruto != ""){
            $datosComprobante['peso_bruto'] = $cdo_informacion_adicional->peso_bruto;
        }

        $datosComprobante['peso_neto'] = "";
        if(isset($cdo_informacion_adicional->peso_neto) && $cdo_informacion_adicional->peso_neto != ""){
            $datosComprobante['peso_neto'] = $cdo_informacion_adicional->peso_neto;
        }

        $datosComprobante['documento_transporte'] = "";
        if(isset($cdo_informacion_adicional->documento_transporte) && $cdo_informacion_adicional->documento_transporte != ""){
            $datosComprobante['documento_transporte'] = $cdo_informacion_adicional->documento_transporte;
        }

        $datosComprobante['valor_arancel'] = "";
        if(isset($cdo_informacion_adicional->valor_arancel) && $cdo_informacion_adicional->valor_arancel != ""){
            $datosComprobante['valor_arancel'] = $cdo_informacion_adicional->valor_arancel;
        }

        $datosComprobante['pedido'] = "";
        if(isset($cdo_informacion_adicional->pedido) && $cdo_informacion_adicional->pedido != ""){
            $datosComprobante['pedido'] = $cdo_informacion_adicional->pedido;
        }

        $datosComprobante['fecha_transporte'] = "";
        if(isset($cdo_informacion_adicional->fecha_transporte) && $cdo_informacion_adicional->fecha_transporte != ""){
            $datosComprobante['fecha_transporte'] = $cdo_informacion_adicional->fecha_transporte;
        }

        $datosComprobante['valor_iva'] = "";
        if(isset($cdo_informacion_adicional->valor_iva) && $cdo_informacion_adicional->valor_iva != ""){
            $datosComprobante['valor_iva'] = $cdo_informacion_adicional->valor_iva;
        }

        $datosComprobante['valor_fletes'] = "";
        if(isset($cdo_informacion_adicional->valor_fletes) && $cdo_informacion_adicional->valor_fletes != ""){
            $datosComprobante['valor_fletes'] = $cdo_informacion_adicional->valor_fletes;
        }

        $datosComprobante['valor_fob'] = "";
        if(isset($cdo_informacion_adicional->valor_fob) && $cdo_informacion_adicional->valor_fob != ""){
            $datosComprobante['valor_fob'] = $cdo_informacion_adicional->valor_fob;
        }

        $datosComprobante['valor_seguros'] = "";
        if(isset($cdo_informacion_adicional->valor_seguros) && $cdo_informacion_adicional->valor_seguros != ""){
            $datosComprobante['valor_seguros'] = $cdo_informacion_adicional->valor_seguros;
        }

        $datosComprobante['trm'] = "";
        if(isset($cdo_informacion_adicional->trm) && $cdo_informacion_adicional->trm != ""){
            $datosComprobante['trm'] = $cdo_informacion_adicional->trm;
        }

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

        if ($cdo_moneda_extranjera != 'COP' && $cdo_moneda_extranjera != "") {
            $intIva        = $this->parserNumberController($iva_moneda_extranjera);
            $intSubtotal   = $this->parserNumberController($subtotal_moneda_extranjera);
            $intTotalPagar    = $this->parserNumberController($valor_a_pagar_moneda_extranjera);
            $strMoneda     = $cdo_moneda_extranjera;
            $nDecimal      = 2;
        } else {
            $intIva        = $this->parserNumberController($iva);
            $intSubtotal   = $this->parserNumberController($subtotal);
            $intTotalPagar    = $this->parserNumberController($valor_a_pagar);
            $strMoneda     = $cdo_moneda;
            $nDecimal      = 0;
        }

        // Totales Retenciones
        $intTotalReteIva  = 0;
        $intTotalReteIca  = 0;
        $intPorcenReteIca = 0;
        $intPorcenReteIva = 0;
        $intTotalReteFte11  = 0;
        $intTotalReteFte4   = 0;
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
        $intConLines = 0;
        $intMaxLines = 35;
        $posx  = $fpdf->posx;
        $posy  = $fpdf->nPosYFin;
        $pyIni = ($posy - 8);
        $posfin = 203;

        // $items = array_merge($items,$items,$items);
        // $items = array_merge($items,$items,$items);
        // $items = array_merge($items,$items,$items);

        /*** Separo los items en PCC e IP. ***/
        $items_pcc = array_filter($items, function($item){
            return ($item['ddo_tipo_item'] == 'PCC' || $item['ddo_tipo_item'] == 'GMF'); 
        });
        $items_ip = array_filter($items, function($item){
            return ($item['ddo_tipo_item'] == 'IP' || $item['ddo_tipo_item'] == ''); 
        });

        // Contador de items
        $contItem = 0;

        // totales
        $intTotalPcc = 0;
        $intTotalIp = 0;

        //Items
        if (isset($items_pcc) && count($items_pcc) > 0) {
            $fpdf->SetFont('Arial','B',8);
            $fpdf->setXY($posx + 30, $posy - 3);
            $fpdf->Cell(65,6,"Ingresos para Terceros",0,0,'L');

            //Propiedades de la tabla
            $fpdf->SetWidths(array(10, 20, 70, 15, 20, 30, 30));
            $fpdf->SetAligns(array("C", "C", "L", "C", "C", "R", "R"));
            $fpdf->SetLineHeight(3.4);
            $fpdf->SetFont('Arial','',7);

            $fpdf->setXY($posx, $posy + 2);
            foreach ($items_pcc as $item) {
                $contItem++;
    
                if($fpdf->getY() > $posfin-3){
                    $fpdf->Rect($posx, $pyIni, 195, $posfin-$pyIni);
                    $fpdf->Line($posx + 165, $pyIni, $posx + 165, $posfin);
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
                    number_format($contItem),
                    utf8_decode($item['ddo_codigo']),
                    utf8_decode(mb_strtoupper($item['ddo_descripcion_uno'])),
                    number_format($item['ddo_cantidad'], 0),
                    utf8_decode(ucwords($this->getUnidad($item['und_id'], 'und_descripcion'))),
                    number_format($intVlrUnitario, $nDecimal, ',', '.'),
                    number_format($intVlrTotal, $nDecimal, ',', '.')
                ]);
            }

            $fpdf->SetFont('Arial','B',8);
            $fpdf->setXY($posx + 30, $fpdf->GetY()-1.5);
            $fpdf->Cell(135,6,"Total Ingresos para Terceros",0,0,'L');
            $fpdf->Cell(30,6,number_format($intTotalPcc, $nDecimal, ',', '.'),0,0,'R');
            $fpdf->Ln(3);
        }

        if (count($items_ip) > 0 && count($items_pcc) > 0) {
            $fpdf->Line($posx, $fpdf->GetY()+2.5, ($posx + 195), $fpdf->GetY()+2.5);
        }

        if (isset($items_ip) && count($items_ip) > 0) {            
            $fpdf->SetFont('Arial','B',8);
            $fpdf->setXY($posx + 30,$fpdf->GetY() + 2);
            $fpdf->Cell(65,6,"Ingresos Propios",0,0,'L');

            //Propiedades de la tabla
            $fpdf->SetWidths(array(10, 20, 70, 15, 20, 30, 30));
            $fpdf->SetAligns(array("C", "C", "L", "C", "C", "R", "R"));
            $fpdf->SetLineHeight(3.4);
            $fpdf->setXY($posx, $fpdf->GetY() + 5);
            $fpdf->SetFont('Arial','',7);

            foreach ($items_ip as $item) {
                $contItem++;
    
                if($fpdf->getY() > $posfin-3){
                    $fpdf->Rect($posx, $pyIni, 195, ($posfin - $pyIni));
                    $fpdf->Line($posx + 165, $pyIni, $posx + 165, $posfin);
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
                    number_format($contItem),
                    utf8_decode($item['ddo_codigo']),
                    utf8_decode(mb_strtoupper($item['ddo_descripcion_uno'])),
                    number_format($item['ddo_cantidad'], 0),
                    utf8_decode(ucwords($this->getUnidad($item['und_id'], 'und_descripcion'))),
                    number_format($intVlrUnitario, $nDecimal, ',', '.'),
                    number_format($intVlrTotal, $nDecimal, ',', '.')
                ]);
            }

            $fpdf->SetFont('Arial','B',8);
            $fpdf->setXY($posx + 30, $fpdf->GetY()-1.5);
            $fpdf->Cell(135,6,"Total Ingresos Propios",0,0,'L');
            $fpdf->Cell(30,6,number_format($intTotalIp, $nDecimal, ',', '.'),0,0,'R');
            $fpdf->Ln(3);
        }

        $fpdf->setXY($posx+1, $fpdf->GetY()-1);
        $fpdf->SetFont('Arial', 'B', 7);
        $fpdf->Cell(8, 3, $contItem, "T", 0, 'C');
        $fpdf->Ln(3);

        if ($fpdf->GetY() > 169) {
            $fpdf->Rect($posx, $pyIni, 195, (203 - $pyIni));
            $fpdf->Line($posx + 165, $pyIni, $posx + 165, 203);
            $fpdf->AddPage('P', 'Letter');
            $fpdf->posy = 212;
        }

        $fpdf->Rect($posx, $pyIni, 195, (169 - $pyIni));
        $fpdf->Line($posx + 165, $pyIni, $posx + 165, 169);

        $posy = 170;
        $posYIni = $posy;

        $fpdf->Line($posx, $posYIni, $posx + 195, $posYIni);
        $fpdf->setXY($posx, $posy);
        $fpdf->SetFont('Arial', 'B', 7);
        $fpdf->Cell(100, 5, utf8_decode("Fecha y Hora Validación DIAN. ").$strValidacionDian, 0, 0, 'L');
        $fpdf->Ln(6);
        $fpdf->setX($posx);
        $fpdf->SetFont('Arial', '', 8);
        $fpdf->Cell(20, 3.5, "Observaciones:", 0, 0, 'L');
        $fpdf->MultiCell(95, 3.5, utf8_decode(mb_strtoupper(implode("\n",$observacion_decode))), 0, 'L');
        $fpdf->Ln(5);

        $intTotalFactura  = ($intSubtotal + $intIva) - ($intTotalReteIva + $intTotalReteIca + $intTotalReteFte11 + $intTotalReteFte4);
        $nTotalneto     = $intTotalFactura >  0 ? $intTotalFactura : $intSaldoFavor;
        $strValorLetras = NumToLetrasEngine::num2letras(number_format($nTotalneto, $nDecimal, '.', ''), false, true, $strMoneda);
        $fpdf->setX($posx);
        $fpdf->SetFont('Arial', 'B', 7.5);
        $fpdf->Cell(8, 3.5, "SON: ", 0, 0, 'L');
        $fpdf->SetFont('Arial', '', 8);
        $fpdf->MultiCell(105, 3.5, utf8_decode($strValorLetras), 0, 'L');

        $fpdf->setXY($posx + 120, $posy);        
        $fpdf->Cell(45, 4, "Subtotal Factura:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intSubtotal, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Valor Iva ".number_format($porcentaje_iva, 2)."%/Propios:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intIva, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Valor ReteIva: ".number_format($intPorcenReteIva, 2)."%", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intTotalReteIva, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Valor ReteIca:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intTotalReteIca, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Retefuente 11.00%:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intTotalReteFte11, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Retefuente 4.00%:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intTotalReteFte4, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Total Factura:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intTotalFactura, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Menos Anticipo:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intTotalAnticipo, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Valor a Pagar:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intTotalPagar, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);
        $fpdf->setX($posx + 120);
        $fpdf->Cell(45, 4, "Saldo a su Favor:", 1, 0, 'R');
        $fpdf->Cell(30, 4, number_format($intSaldoFavor, $nDecimal, '.', ','), 1, 0, 'R');
        $fpdf->Ln(4);

        $fpdf->posy = $fpdf->getY()+2;

        return ['error' => false, 'pdf' => $fpdf->Output('S')];
    }
}