<?php 
/**
 * User: Jhon Escobar
 * Date: 24/08/2020
 * Time: 02:30 PM
 */

namespace App\Http\Modulos\RepresentacionesGraficas\Documentos\etl_acodexxx\rg800116195;

use App\Http\Modulos\RepresentacionesGraficas\Core\PDFBase;

class PDF800116195_1 extends PDFBase{

	function Header() {

        if($this->datosComprobante['signaturevalue'] == '' && $this->datosComprobante['qr'] ==''){
            $this->Image($this->datosComprobante['no_valido'], 20, 50, 180);
        }

        $strTitulo = ($this->datosComprobante['cdo_tipo'] == "FC") ? "FACTURA ELECTRÓNICA DE VENTA" : mb_strtoupper($this->datosComprobante['cdo_tipo_nombre'])." ELECTRÓNICA";

        $posx = 10;
        $posy = 5;
        $posyLogo = ($this->datosComprobante['cdo_tipo'] == "FC") ? 3 : 7;
        $this->posx = $posx;
        $this->posy = $posy;

        $this->SetFont('Arial', '', 6);
        $this->TextWithDirection(207,50,utf8_decode("NOMBRE DEL FABRICANTE DEL SOFTWARE (PROVEEDOR TECNOLÓGICO): ".$this->datosComprobante['razon_social_pt']." NIT: ".$this->datosComprobante['nit_pt']." NOMBRE DEL SOFTWARE: ".$this->datosComprobante['nombre_software']),'D');

        $this->Image($this->imageHeader, $posx - 2, $posy + $posyLogo, 70);

        //resolucion
        $this->setXY($posx+64,$posy+4);
        $this->SetFont('Arial','',6.5);
        $this->MultiCell(70,3.2, utf8_decode($this->datosComprobante['resolucion']),0,"C");
        $this->setX($posx+64);
        $this->MultiCell(70,3.2, utf8_decode($this->datosComprobante['texto_cabecera']),0,"C");
        $this->setX($posx+64);
        $this->MultiCell(70,3.2, utf8_decode($this->datosComprobante['actividad_economica']),0,"C");

        //FACTURA
        $this->setXY($posx+120,$posy+19);
        $this->SetFont('Arial','B',10);
        $this->MultiCell(74,4.5, utf8_decode($strTitulo),0,"R");
        $this->setX($posx+140);
        $this->SetFont('Arial','B',11);
        $this->Cell(54,4, $this->datosComprobante['rfa_prefijo']." - ".$this->datosComprobante['cdo_consecutivo'],0,0,"R");

        //DATOS ADQUIRENTE
        $posy += 28;
        $posyIni = $posy;
        $this->setXY($posx, $posy+1);
        $this->SetFont('Arial','',8);
        $this->Cell(18,3.5, "Cliente:", 0,0,"L");
        $this->setX($posx+18);
        $this->MultiCell(90,3.5, utf8_decode(mb_strtoupper($this->datosComprobante['adquirente'])), 0,"L");
        $this->setX($posx);
        $this->Cell(18,3.5, "Nit:", 0,0,"L");
        $this->setX($posx+18);
        $this->MultiCell(90,3.5, utf8_decode($this->datosComprobante['adq_nit']), 0,"L");
        $this->setX($posx);
        $this->Cell(18,3.5, "Direccion:", 0,0,"L");
        $this->setX($posx+18);
        $this->MultiCell(90,3.5, utf8_decode(mb_strtoupper($this->datosComprobante['adq_dir'])), 0,"L");
        $this->setX($posx);
        $this->Cell(18,3.5, "Ciudad:", 0,0,"L");
        $this->setX($posx+18);
        $this->MultiCell(95,3.5, utf8_decode(mb_strtoupper($this->datosComprobante['adq_mun'])."        Código Postal: ".$this->datosComprobante['adq_codigo_postal']), 0,"L");
        $this->setX($posx);
        $this->Cell(18,3.5, "Telefono:", 0,0,"L");
        $this->setX($posx+18);
        $this->MultiCell(90,3.5, utf8_decode(mb_strtoupper($this->datosComprobante['adq_tel'])), 0,"L");
        $this->setX($posx);
        $this->Cell(18,3.5, "Mod Transp:", 0,0,"L");
        $this->setX($posx+18);
        $this->MultiCell(90,3.5, utf8_decode(mb_strtoupper($this->datosComprobante['modo_transporte'])), 0,"L");
        $this->setX($posx);
        $this->Cell(23,3.5, "Forma de Pago:", 0,0,"L");
        $this->setX($posx+23);
        $this->MultiCell(80,3.5, utf8_decode(mb_strtoupper($this->datosComprobante['forma_pago'])), 0,"L");
        $this->setX($posx);
        $this->Cell(23,3.5, "Medio de Pago:", 0,0,"L");
        $this->setX($posx+23);
        $this->MultiCell(80,3.5, utf8_decode(mb_strtoupper($this->datosComprobante['medio_pago'])), 0,"L");
        $posyFin = $this->getY()+1;

        $this->setXY($posx+120, $posy+1);
        $this->Cell(35,3.5, "DO:", 0,0,"L");
        $this->Cell(35,3.5, $this->datosComprobante['do'], 0, 0,"L");
        $this->Ln(3.5);
        $this->setX($posx+120);
        $this->Cell(35,3.5, ($this->datosComprobante['cdo_tipo'] == "FC") ? "Fecha y Hora Factura:" : "Fecha y Hora:", 0,0,"L");
        $this->Cell(35,3.5, $this->datosComprobante['fecha_hora_documento'], 0, 0,"L");
        $this->Ln(3.5);
        $this->setX($posx+120);
        $this->Cell(35,3.5, ($this->datosComprobante['cdo_tipo'] == "FC") ? "Vencimiento de la Factura:" : "Vencimiento", 0,0,"L");
        $this->Cell(35,3.5, $this->datosComprobante['fecha_vencimiento'], 0, 0,"L");
        $this->Ln(3.5);
        $this->setX($posx+120);
        $this->Cell(35,3.5, "Valor en Aduana:", 0,0,"L");
        $this->Cell(39,3.5, "$".$this->datosComprobante['valor_aduana_us'], 0, 0,"R");
        $this->Ln(3.5);
        $this->setX($posx+120);
        $this->Cell(35,3.5, "CIF:", 0,0,"L");
        $this->Cell(39,3.5, "$".$this->datosComprobante['valor_aduana_pesos'], 0, 0,"R");
        $this->Ln(3.5);
        $this->setX($posx+120);
        $this->Cell(35,3.5, "Peso bruto:", 0,0,"L");
        $this->Cell(39,3.5, ($this->datosComprobante['peso_bruto'] != "") ? number_format($this->datosComprobante['peso_bruto'], 2,',', '.') : "", 0, 0,"R");
        $this->Ln(3.5);
        $this->setX($posx+120);
        $this->Cell(35,3.5, "Peso Neto:", 0,0,"L");
        $this->Cell(39,3.5, ($this->datosComprobante['peso_bruto'] != "") ? number_format($this->datosComprobante['peso_neto'], 2,',', '.') : "", 0, 0,"R");
        
        $posy = ($posyFin > $this->getY()) ? $posyFin : $this->getY();

        $this->setXY($posx, $posy+1);
        $this->SetFont('Arial','',8);
        $this->Cell(24,3.5, "Doc. Transporte:", 0,0,"L");
        $this->MultiCell(30,3.5, $this->datosComprobante['documento_transporte'], 0,"R");
        $this->Ln(0.5);
        $this->setX($posx);
        $this->SetFont('Arial','',8);
        $this->Cell(24,3.5, "Arancel:", 0,0,"L");
        $this->Cell(30,3.5, "$".$this->datosComprobante['valor_arancel'], 0, 0,"R");
        $this->Ln(3.5);
        $this->setX($posx);
        $this->SetFont('Arial','',8);
        $this->Cell(34,3.5, "Su Pedido / Site Number:", 0,0,"L");
        $this->MultiCell(21,3.5, utf8_decode($this->datosComprobante['pedido']), 0,"R");
        $this->Ln(0.5);
        $nPosyFin = $this->getY();

        $this->setXY($posx+56, $posy+1);
        $this->SetFont('Arial','',8);
        $this->Cell(16,3.5, "F. Llegada:", 0,0,"L");
        $this->Cell(24,3.5, $this->datosComprobante['fecha_transporte'], 0, 0,"R");
        $this->Ln(3.5);
        $this->setX($posx+56);
        $this->SetFont('Arial','',8);
        $this->Cell(16,3.5, "Iva:", 0,0,"L");
        $this->Cell(24,3.5, "$".$this->datosComprobante['valor_iva'], 0, 0,"R");
        $this->Ln(3.5);

        $this->setXY($posx+104, $posy+1);
        $this->SetFont('Arial','',8);
        $this->Cell(16,3.5, "Tasa Cambio:", 0,0,"L");
        $this->Cell(24,3.5, ($this->datosComprobante['trm'] != "") ? "$".number_format($this->datosComprobante['trm'], 2, ',', '.') : "", 0, 0,"R");
        $this->Ln(3.5);
        $this->setX($posx+104);
        $this->SetFont('Arial','',8);
        $this->Cell(16,3.5, "Fletes:", 0,0,"L");
        $this->Cell(24,3.5, "$".$this->datosComprobante['valor_fletes'], 0, 0,"R");
        $this->Ln(3.5);

        $this->setXY($posx+154, $posy+1);
        $this->SetFont('Arial','',8);
        $this->Cell(16,3.5, "FOB:", 0,0,"L");
        $this->Cell(24,3.5, "$".$this->datosComprobante['valor_fob'], 0, 0,"R");
        $this->Ln(3.5);
        $this->setX($posx+154);
        $this->SetFont('Arial','',8);
        $this->Cell(16,3.5, "Seguros:", 0,0,"L");
        $this->Cell(24,3.5, "$".$this->datosComprobante['valor_seguros'], 0, 0,"R");
        $this->Ln(3.5);
        $this->Rect($posx, $posy, 195, $nPosyFin - $posy + 1);

        $posy = $nPosyFin;
        $this->setXY($posx, $posy + 1);
        $this->SetFont('Arial','B',8);
        $this->Cell(10,5, "ITEM",0,0,'C');
        $this->Cell(20,5, "CODIGO",0,0,'C');
        $this->Cell(70,5, utf8_decode("DESCRIPCIÓN"), 0,0,'C');
        $this->Cell(15,5, "CAT",0,0,'C');
        $this->Cell(20,5, "UNID",0,0,'C');
        $this->Cell(30,5, "VLR. UNITARIO",0,0,'C');
        $this->Cell(30,5, "VLR. TOTAL",0,0,'C');
        $this->ln(5);
        $this->Line($posx, $posy + 6, $posx + 195, $posy + 6);

        $this->nPosYFin = $posy+9;
    }

	function Footer() {

        //Datos QR y Firma
        $posx = 10;
        $posy = 211;

        $this->setXY($posx, $posy+1);
        $this->SetFont('Arial', '', 8);
        $this->setDrawColor(21,68,138);
        $this->setFillColor(21,68,138);     
        $this->setTextColor(255);
        $this->Rect($posx, $posy, 195, 10, true);
        if ($this->datosComprobante['cdo_tipo'] != "FC") {
            $posy += 1;
            $this->setXY($posx, $posy+1);
            $this->SetFont('Helvetica','',8);
            $this->Cell(21,3.5, "Afecta Factura:", 0,0,"L");
            $this->MultiCell(84,3.5, utf8_decode($this->datosComprobante['consecutivo_ref']), 0,"L");
            $this->Ln(1);
            $this->setXY($posx+90, $posy+1);
            $this->Cell(21,3.5, "Fecha Factura:", 0,0,"L");
            $this->MultiCell(90,3.5, utf8_decode($this->datosComprobante['fecha_emision']), 0,"L");
            $this->Ln(1);
            $this->setXY($posx,$posy+5);
            $this->Cell(10,3.5, "Cufe:", 0,0,"L");
            $this->MultiCell(178,3.5, utf8_decode($this->datosComprobante['cufe_ref']), 0,"L");
        } else {
            $this->MultiCell(194, 4, utf8_decode($this->datosComprobante['nota_final_1']), 0, 'L');
        }
        $this->setDrawColor(0);
        $this->setTextColor(21,68,138);
        $this->Ln(2);
        $this->setX($posx+70);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(43, 3.5, utf8_decode($this->datosComprobante['oficina_principal_1']), 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(13, 3.5, utf8_decode($this->datosComprobante['ofe_tel']), 0, 'L');
        $this->Ln(4);
        $this->setX($posx);
        $this->MultiCell(195, 3.5, utf8_decode($this->datosComprobante['ofe_dir']."   Código Postal: ".$this->datosComprobante['ofe_codigo_postal']), 0, 'C');
        $this->Ln(1);
        $this->setX($posx);
        $this->SetFont('Arial', 'B', 9);
        $this->MultiCell(195, 3.5, utf8_decode($this->datosComprobante['pagina']), 0, 'C');
        $this->Ln(1);
        $this->setX($posx);
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(195, 3.5, utf8_decode($this->datosComprobante['nota_final_2']), 0, 'C');
        $this->setTextColor(0);
        $nPosy = $this->getY()-5;
            
        if($this->datosComprobante['signaturevalue'] != "" && $this->datosComprobante['qr'] != ""){
            $dataURI = "data:image/png;base64, ".base64_encode((string) \QrCode::format('png')->size(82)->margin(0)->generate($this->datosComprobante['qr']));
            $pic = $this->getImage($dataURI);
            if ($pic!==false) $this->Image($pic[0], $posx + 160, $nPosy + 5,29,29, $pic[1]);
            $this->setXY($posx, $nPosy + 6);
            $this->SetFont('Arial','B',8);
            $this->Cell(110,3,utf8_decode("REPRESENTACIÓN IMPRESA DE LA ". mb_strtoupper($this->datosComprobante['cdo_tipo_nombre'])),0,0,'L');
            $this->Ln(4);
            $this->setX($posx);
            $this->SetFont('Arial','B',7);
            $this->Cell(110,3,utf8_decode("Firma Electrónica:"),0,0,'L');
            $this->Ln(4);
            $this->setX($posx);
            $this->SetFont('Arial','',6);
            $this->MultiCell(140,3,$this->datosComprobante['signaturevalue'],0,'J');
            $this->Ln(2);
            $this->setX($posx);
            $this->SetFont('Arial','B',7);
            $this->Cell(8,3, ($this->datosComprobante['cdo_tipo'] == "FC") ? "CUFE:" : "CUDE:",0,0,'L');
            $this->SetFont('Arial','',6);
            $this->MultiCell(150,2.5,utf8_decode($this->datosComprobante['cufe']),0,'L');
        }

        //Paginacion
        $this->setXY($posx,$posy+61);           
        $this->SetFont('Arial','B',7);
        $this->Cell(194,4,utf8_decode('Pág ').$this->PageNo().'/{nb}',0,0,'C');
    }

    function TextWithDirection($x, $y, $txt, $direction='U') {
        if ($direction=='R')
            $s=sprintf('BT %.2F %.2F %.2F %.2F %.2F %.2F Tm (%s) Tj ET',1,0,0,1,$x*$this->k,($this->h-$y)*$this->k,$this->_escape($txt));
        elseif ($direction=='L')
            $s=sprintf('BT %.2F %.2F %.2F %.2F %.2F %.2F Tm (%s) Tj ET',-1,0,0,-1,$x*$this->k,($this->h-$y)*$this->k,$this->_escape($txt));
        elseif ($direction=='U')
            $s=sprintf('BT %.2F %.2F %.2F %.2F %.2F %.2F Tm (%s) Tj ET',0,1,-1,0,$x*$this->k,($this->h-$y)*$this->k,$this->_escape($txt));
        elseif ($direction=='D')
            $s=sprintf('BT %.2F %.2F %.2F %.2F %.2F %.2F Tm (%s) Tj ET',0,-1,1,0,$x*$this->k,($this->h-$y)*$this->k,$this->_escape($txt));
        else
            $s=sprintf('BT %.2F %.2F Td (%s) Tj ET',$x*$this->k,($this->h-$y)*$this->k,$this->_escape($txt));
        if ($this->ColorFlag)
            $s='q '.$this->TextColor.' '.$s.' Q';
        $this->_out($s);
    }
}
