<?php 
/**
 * User: Juan José Hernández
 * Date: 17/03/2023
 * Time: 08:12 AM
 */

namespace App\Http\Modulos\RepresentacionesGraficas\Documentos\etl_fedexxxx\rg901106968;

use App\Http\Modulos\RepresentacionesGraficas\Core\PDFBase;

class PDF901106968_1 extends PDFBase{

	function Header() {

        if($this->datosComprobante['signaturevalue'] == '' && $this->datosComprobante['qr'] ==''){
            $this->Image($this->datosComprobante['no_valido'], 20, 50, 180);
        }

        $strTitulo = ($this->datosComprobante['cdo_tipo'] == "FC") ? "FACTURA ELECTRÓNICA DE VENTA" : mb_strtoupper($this->datosComprobante['cdo_tipo_nombre'])." ELECTRÓNICA";

        $posx = 10;
        $posy = 5;
        $this->posx = $posx;
        $this->posy = $posy;

        $this->SetFont('Arial', '', 6);
        $this->TextWithDirection(207,50,utf8_decode("NOMBRE DEL FABRICANTE DEL SOFTWARE (PROVEEDOR TECNOLÓGICO): ".$this->datosComprobante['razon_social_pt']." NIT: ".$this->datosComprobante['nit_pt']." NOMBRE DEL SOFTWARE: ".$this->datosComprobante['nombre_software']),'D');

        $this->Image($this->imageHeader, $posx - 2, $posy + 2, 30);

        // INFORMACIÓN CABECERA
        $this->setXY($posx+64,$posy+5);
        $this->SetFont('Arial','',6.5);
        $this->MultiCell(70,3.2, utf8_decode($this->datosComprobante['cabecera']),0,"C");

        //FACTURA
        $this->setXY($posx+130,$posy+5);
        $this->SetFont('Arial','B',9);
        $this->MultiCell(60,5, utf8_decode($strTitulo),0,"C");
        $this->setX($posx+130);
        $this->SetFont('Arial','B',9);
        $this->Cell(60,5, $this->datosComprobante['rfa_prefijo']." - ".$this->datosComprobante['cdo_consecutivo'],1,0,"C");

        // INFORMACIÓN OFERENTE
        $posy += 23;
        $this->setXY($posx, $posy+1);
        $this->SetFont('Arial','B',6);
        $this->MultiCell(105,4, utf8_decode(mb_strtoupper($this->datosComprobante['oferente'])), 0,"L");
        $this->SetFont('Arial','',6);
        $this->setX($posx);
        $this->Cell(105,4,  utf8_decode("DIRECCIÓN: ".$this->datosComprobante['ofe_dir']), 0,0,"L");
        $this->Ln(3);
        $this->setX($posx);
        $this->Cell(105,4, "CIUDAD: ".utf8_decode($this->datosComprobante['ofe_ciudad_pais']), 0,0,"L");
        $this->Ln(3);
        $this->setX($posx);
        $this->Cell(105,4, "TELEFONO: ".$this->datosComprobante['ofe_tel'], 0,0,"L");
        $this->Ln(3);
        $this->setX($posx);
        $this->Cell(105,4, "NIT: ".$this->datosComprobante['ofe_nit'], 0,0,"L");

        // INFORMACIÓN ADQUIRENTE
        $this->setXY($posx+105, $posy+1);
        $this->Cell(11,3.5, "CLIENTE:", 0,0,"L");
        $this->SetFont('Arial','B',6);
        $this->Cell(79,3.5, utf8_decode($this->datosComprobante['adquirente']), 0,0,"L");
        $this->SetFont('Arial','',6);
        $this->Ln(3.5);
        $this->setX($posx+105);
        $this->Cell(90,3.5, utf8_decode("DIRECCIÓN: ".$this->datosComprobante['adq_dir']), 0, 0,"L");
        $this->Ln(3.5);
        $this->setX($posx+105);
        $this->Cell(90,3.5, "CIUDAD: ".utf8_decode($this->datosComprobante['adq_ciudad_pais']), 0, 0,"L");
        $this->Ln(3.5);
        $this->setX($posx+105);
        $this->Cell(90,3.5, utf8_decode("CÓDIGO POSTAL: ".$this->datosComprobante['adq_codigo_postal']), 0, 0,"L");
        $this->Ln(3.5);
        $this->setX($posx+105);
        $this->Cell(90,3.5, "NIT: ".$this->datosComprobante['adq_nit'], 0, 0,"L");

        $posy = $this->getY() + 8;

        $this->setXY($posx, $posy+1);
        $this->SetFont('Arial','B',6);
        $this->Cell(180,5, "DETALLE ".($this->datosComprobante['cdo_tipo'] == "FC" ? "FACTURA" : "NOTA"), 0,0,"L");
        $this->Line($posx, $posy + 5, $posx + 195, $posy + 5);

        $posy = $this->getY() + 4;
        $this->setXY($posx, $posy);
        $this->SetFont('Arial','',6);
        $this->Cell(120,3.5, "FECHA Y HORA DE ".utf8_decode(($this->datosComprobante['cdo_tipo'] == "FC" ? "FACTURACIÓN" : "NOTA") .": ".$this->datosComprobante['fecha_hora_documento']), 0,0,"L");
        $this->Cell(75,3.5, "VENCIMIENTO: ".$this->datosComprobante['fecha_vencimiento'], 0,0,"L");
        $this->Line($posx, $posy +4, $posx + 195, $posy + 4);

        $posy = $this->getY() + 4;
        $this->setXY($posx, $posy);
        $this->SetFont('Arial','',6);
        $this->Cell(60,3.5, "FORMA DE PAGO: ".utf8_decode(mb_strtoupper($this->datosComprobante['forma_pago'])), 0,0,"L");
        $this->Cell(60,3.5, "MEDIO DE PAGO: ".utf8_decode(mb_strtoupper($this->datosComprobante['medio_pago'])), 0,0,"L");
        $this->Cell(75,3.5, "MONEDA: ".mb_strtoupper($this->datosComprobante['moneda']), 0,0,"L");
        $this->Line($posx, $posy +4, $posx + 195, $posy + 4);
        
        // INFORMACIÓN ADICIONAL TODO: PENDIENTE
        $posy = $this->getY() + 4;
        $this->setXY($posx, $posy);
        $this->SetFont('Arial','',6);
        $this->Cell(120,3.5, utf8_decode("IMPORTADOR: FEDERAL EXPRESS CORPORATION SUCURSAL COLOMBIA"), 0,0,"L");
        $this->Cell(75,3.5, "DOCUMENTO TRANSPORTE: TYHY12324", 0,0,"L");
        $this->Ln(3);
        $this->setX($posx);
        $this->Cell(120,3.5, utf8_decode("DO: BOG-123412321-001"), 0,0,"L");
        $this->Cell(75,3.5, "PEDIDO: 83321", 0,0,"L");
        $this->Ln(3);
        $this->setX($posx);
        $this->Cell(120,3.5, utf8_decode("VALOR CIF: 4.450.000"), 0,0,"L");
        $this->Cell(75,3.5, "BULTOS/PIEZAS: 188", 0,0,"L");
        $this->Ln(1);
        $this->Line($posx, $this->getY() + 3, $posx + 195, $this->getY() + 3);

        $posy = $this->getY() + 3;
        $this->setXY($posx, $posy);
        $this->SetFont('Arial','B',6);
        $this->Cell(180,5, utf8_decode("DESCRIPCIÓN DE VALORES"), 0,0,"L");
        $this->SetFont('Arial','',6);
        $this->Ln(2);
        $this->Line($posx, $this->getY() + 3, $posx + 195, $this->getY() + 3);

        $posy = $this->getY() + 2;;
        $this->setXY($posx, $posy + 1);
        $this->SetFont('Arial','B',6);
        $this->Cell(10,5, "ITEM",0,0,'C');
        $this->Cell(20,5, "CODIGO",0,0,'C');
        $this->Cell(70,5, utf8_decode("DESCRIPCIÓN"), 0,0,'C');
        $this->Cell(20,5, "UNIDAD",0,0,'C');
        $this->Cell(15,5, "CANTIDAD",0,0,'C');
        $this->Cell(30,5, "VALOR UNITARIO",0,0,'R');
        $this->Cell(30,5, "VALOR TOTAL",0,0,'R');
        $this->ln(5);
        $this->Line($posx, $posy + 6, $posx + 195, $posy + 6);

        $this->nPosYFin = $posy+9;
    }

	function Footer() {

        $posx = 10;
        $posy = 272;

        //Paginacion
        $this->setXY($posx,$posy);           
        $this->SetFont('Arial','B',7);
        $this->Cell(194,4,utf8_decode('Página ').$this->PageNo(),0,0,'C');
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
