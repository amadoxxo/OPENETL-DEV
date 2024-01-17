<?php

namespace App\Http\Modulos\RepresentacionesGraficas\Eventos\etl_generica\rgGenerica;

use App\Http\Modulos\RepresentacionesGraficas\CoreEventos\PDFBase;

/**
 * Gestor para la generación de representaciones gráficas genéricas.
 *
 * Class PDFGENERICA
 * @package App\Http\Modulos\RepresentacionesGraficas\Eventos\etl_generica\rgGenerica
 */
class PDFGENERICA extends PDFBase
{
    /**
     * Imprime la cabecera de la representación gráfica.
     *
     * @return void
     */
    function Header() {
        $posx = 8;
        $posy = 10;
        $this->posx = $posx;
        $this->posy = $posy;

        $this->setXY($posx, $posy);
        $this->SetFont('Arial', '', 14);
        $this->Cell(199, 4, utf8_decode("Representación Gráfica"), 0, 0, 'C');
        $this->Ln(6);
        $this->setX($posx);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(199, 4, utf8_decode($this->datosComprobante['titulo_evento']), 0, 0, 'C');

        $this->SetLineWidth(1);
        $this->SetDrawColor(200);
        $this->Line($posx+80,$posy+12,$posx+119,$posy+12);
        $this->SetLineWidth(0.5);

        // Datos del evento
        $this->setXY($posx, $posy+20);
        $this->SetFillColor(200);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(199, 5, utf8_decode("Datos del Evento"), 0, 0, 'L', true);
        $this->Ln(7);
        $this->setX($posx);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(30, 4, utf8_decode("Código Único del Documento Electrónico - CUDE:"), 0, 0, 'L');
        $this->Ln(4);
        $this->setX($posx);
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(190, 4, $this->datosComprobante['cude'], 0, 'L');
        $this->Ln(2);
        $this->setX($posx);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(30, 4, utf8_decode("Número del Evento:"), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(30, 4, $this->datosComprobante['numero_evento'], 0, 0, 'L');
        $this->setX($posx+120);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(42, 4, utf8_decode("Fecha y Hora de Generación:"), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(30, 4, $this->datosComprobante['fecha_generacion'] . ' ' . $this->datosComprobante['hora_generacion'], 0, 0, 'L');

        // Datos del emisor
        $posy = $this->getY()+10;
        $this->setXY($posx, $posy);
        $this->SetFillColor(200);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(199, 5, utf8_decode("Datos del Emisor"), 0, 0, 'L', true);
        $this->Ln(7);
        $this->setX($posx);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(22, 4, utf8_decode("Razón Social:"), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(110, 4, utf8_decode($this->datosComprobante['emisor_razon_social']), 0, 'L');
        $posyFin = $this->getY()+6;
        $this->setXY($posx+120, $posy+7);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(25, 4, utf8_decode("Nit del emisor:"), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(30, 4, $this->datosComprobante['emisor_identificacion'], 0, 0, 'L');

        // Datos del receptor
        $posy = $posyFin;
        $this->setXY($posx, $posy);
        $this->SetFillColor(200);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(199, 5, utf8_decode("Datos del Receptor"), 0, 0, 'L', true);
        $this->Ln(7);
        $this->setX($posx);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(22, 4, utf8_decode("Razón Social:"), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(110, 4, utf8_decode($this->datosComprobante['receptor_razon_social']), 0, 'L');
        $posyFin = $this->getY()+6;
        $this->setXY($posx+120, $posy+7);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(25, 4, utf8_decode("Nit del receptor:"), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(30, 4, $this->datosComprobante['receptor_identificacion'], 0, 0, 'L');

        // Datos de referencia de la factura
        $posy = $posyFin;
        $this->setXY($posx, $posy);
        $this->SetFillColor(200);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(199, 5, utf8_decode("Datos de Referencia de la Factura"), 0, 0, 'L', true);
        $this->Ln(7);
        $this->setX($posx);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(30, 4, utf8_decode("Numero de Factura:"), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(30, 4, utf8_decode($this->datosComprobante['numero_factura']), 0, 0, 'L');
        $this->Ln(4);
        $this->setX($posx);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(32, 4, utf8_decode("CUFE:"), 0, 0, 'L');
        $this->Ln(4);
        $this->setX($posx);
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(190, 4, $this->datosComprobante['cufe_factura'], 0, 'L');

        // Observacion
        if (!empty($this->datosComprobante['observacion']) || !empty($this->datosComprobante['motivo_rechazo'])) {
            $posy = $this->getY()+6;
            $this->setXY($posx, $posy);
            $this->SetFillColor(200);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(199, 5, utf8_decode("Observación"), 0, 0, 'L', true);
            $this->Ln(7);
            $this->setX($posx);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(32, 4, utf8_decode("Observación:"), 0, 0, 'L');
            $this->setXY($posx+20, $posy+7);
            $this->SetFont('Arial', '', 8);
            if (!empty($this->datosComprobante['observacion'])) 
                $this->MultiCell(170, 4, utf8_decode($this->datosComprobante['observacion']), 0, 'L');
            elseif (!empty($this->datosComprobante['motivo_rechazo']))
                $this->MultiCell(170, 4, utf8_decode($this->datosComprobante['motivo_rechazo']), 0, 'L');
        } 

        if (!empty($this->datosComprobante['concepto_rechazo'])) {
            $posy = $this->getY()+6;
            $this->setXY($posx, $posy);
            $this->SetFillColor(200);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(199, 5, utf8_decode("Concepto de Rechazo"), 0, 0, 'L', true);
            $this->Ln(7);
            $this->setX($posx);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(27, 4, utf8_decode("Código rechazo:"), 0, 0, 'L');
            $this->SetFont('Arial', '', 8);
            $this->Cell(30, 4, utf8_decode($this->datosComprobante['concepto_rechazo']), 0, 0, 'L');
            $this->Ln(4);
            $this->setX($posx);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(27, 4, utf8_decode("Descripción:"), 0, 0, 'L');
            $this->SetFont('Arial', '', 8);
            $this->Cell(30, 4, utf8_decode($this->datosComprobante['descripcion_rechazo']), 0, 0, 'L');
            $this->Ln(4);
            $this->setX($posx);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(27, 4, utf8_decode("Observación:"), 0, 0, 'L');
            $this->SetFont('Arial', '', 8);
            $this->MultiCell(170, 4, utf8_decode($this->datosComprobante['motivo_rechazo']), 0, 'L');
        }

        // Datos del emisor
        $posy = $this->getY()+6;
        $this->setXY($posx, $posy);
        $this->SetFillColor(200);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(199, 5, utf8_decode("Datos Finales"), 0, 0, 'L', true);

        $dataURI = "data:image/png;base64, " . base64_encode(\QrCode::format('png')->size(85)->margin(0)->generate($this->datosComprobante['codigo_qr']));
        $pic = $this->getImage($dataURI);
        if ($pic !== false) $this->Image($pic[0], $posx + 85, $posy + 6, 33, 33, $pic[1]);

        $this->setXY($posx, $posy+40);
        $this->SetFont('Arial', '', 8);
        $this->Cell(199, 4, utf8_decode("Generado por: OPENTECNOLOGIA S.A."), 0, 0, 'C');
    }

    /**
     * Imprime el pie de página de la representación gráfica.
     *
     * @return void
     */
    function Footer() {}
}