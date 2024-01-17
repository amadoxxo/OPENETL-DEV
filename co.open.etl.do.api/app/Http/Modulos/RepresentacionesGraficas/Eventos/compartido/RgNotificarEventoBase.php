<?php
namespace App\Http\Modulos\RepresentacionesGraficas\Eventos\compartido;

use App\Http\Modulos\RepresentacionesGraficas\Eventos\compartido\RgNotificarEventoFpdf;

class RgNotificarEventoBase extends RgNotificarEventoFpdf {
    
    public function __construct($dataNotificarEvento) {
        parent::__construct($dataNotificarEvento);
    }

    function Header() {
        if(array_key_exists('ofeLogo', $this->dataNotificarEvento) && !empty($this->dataNotificarEvento['ofeLogo']))
            $this->Image($this->dataNotificarEvento['ofeLogo'], 20, 20, 45);
    }

    function Footer() {}

    public function generarRgNotificarEvento() {
        $lnSpace = 10;
        $this->fpdf->posx = 20;
        $this->fpdf->posy = 40;
        
        $this->fpdf->AcceptPageBreak();
        $this->fpdf->SetFont('Arial', '', 9);
        $this->fpdf->AliasNbPages();
        $this->fpdf->SetMargins(0, 0, 0);
        $this->fpdf->SetAutoPageBreak(true, 10);

        $this->fpdf->AddPage('P', 'Letter');

        $this->fpdf->setXY($this->fpdf->posx, $this->fpdf->posy);
        $this->fpdf->MultiCell(180, 5, "Estimado(a) proveedor.", 0, 'L');

        $this->fpdf->Ln($lnSpace);
        
        $frase = 'Acaba de recibir un documento con ' . utf8_decode($this->dataNotificarEvento['pronombreTipoDocumento']) . ' ' . utf8_decode($this->dataNotificarEvento['nombreTipoDocumento']) . ' (' . utf8_decode($this->dataNotificarEvento['codigoTipoDocumento']) .')';
        $this->fpdf->setXY($this->fpdf->posx, $this->fpdf->posy + 10);
        $this->fpdf->MultiCell(180, 5, $frase, 0, 'L');
        
        $this->fpdf->Ln($lnSpace);
        $this->fpdf->Ln($lnSpace);

        if(array_key_exists('conceptoReclamo', $this->dataNotificarEvento) && !empty($this->dataNotificarEvento['conceptoReclamo']))
            $widthCell = 37;
        else
            $widthCell = 30;

        $this->fpdf->setXY($this->fpdf->posx + 10, $this->fpdf->posy + 20);
		$this->fpdf->SetFont('Arial', 'B', 9);
		$this->fpdf->Cell($widthCell, 4, "Documento:", 0, 0, 'L');
		$this->fpdf->SetFont('Arial', '', 9);			
		$this->fpdf->MultiCell(45, 4, utf8_decode($this->dataNotificarEvento['documentoReferenciado']), 0, 'L');
		$this->fpdf->Ln($lnSpace);

        $this->fpdf->setXY($this->fpdf->posx + 10, $this->fpdf->posy + 28);
		$this->fpdf->SetFont('Arial', 'B', 9);
		$this->fpdf->Cell($widthCell, 4, "Adquirente:", 0, 0, 'L');
		$this->fpdf->SetFont('Arial', '', 9);			
		$this->fpdf->MultiCell(120, 4, utf8_decode($this->dataNotificarEvento['nombreGeneradorEvento']), 0, 'L');
		$this->fpdf->Ln($lnSpace);

        $this->fpdf->setXY($this->fpdf->posx + 10, $this->fpdf->posy + 36);
		$this->fpdf->SetFont('Arial', 'B', 9);
		$this->fpdf->Cell($widthCell, 4, utf8_decode("Fecha Emisión:"), 0, 0, 'L');
		$this->fpdf->SetFont('Arial', '', 9);			
		$this->fpdf->MultiCell(120, 4, utf8_decode($this->dataNotificarEvento['fechaEmision']), 0, 'L');
		$this->fpdf->Ln($lnSpace);

        $this->fpdf->setXY($this->fpdf->posx + 10, $this->fpdf->posy + 48);
		$this->fpdf->SetFont('Arial', 'B', 9);
		$this->fpdf->Cell($widthCell, 4, utf8_decode("Evento:"), 0, 0, 'L');
		$this->fpdf->SetFont('Arial', '', 9);			
		$this->fpdf->MultiCell(120, 4, utf8_decode($this->dataNotificarEvento['nombreTipoDocumento'] . ' (' . $this->dataNotificarEvento['codigoTipoDocumento'] .')'), 0, 'L');
		$this->fpdf->Ln($lnSpace);

        $this->fpdf->setXY($this->fpdf->posx + 10, $this->fpdf->posy + 56);
		$this->fpdf->SetFont('Arial', 'B', 9);
		$this->fpdf->Cell($widthCell, 4, utf8_decode("Fecha Evento:"), 0, 0, 'L');
		$this->fpdf->SetFont('Arial', '', 9);			
		$this->fpdf->MultiCell(120, 4, utf8_decode($this->dataNotificarEvento['fechaEvento']), 0, 'L');
		$this->fpdf->Ln($lnSpace);

        $posY = $this->fpdf->posy + 64;
        if(array_key_exists('conceptoReclamo', $this->dataNotificarEvento) && !empty($this->dataNotificarEvento['conceptoReclamo'])) {
            $this->fpdf->setXY($this->fpdf->posx + 10, $posY);
            $this->fpdf->SetFont('Arial', 'B', 9);
            $this->fpdf->Cell($widthCell, 4, utf8_decode("Concepto de Reclamo:"), 0, 0, 'L');
            $this->fpdf->SetFont('Arial', '', 9);			
            $this->fpdf->MultiCell(120, 4, utf8_decode($this->dataNotificarEvento['conceptoReclamo']), 0, 'L');
            $this->fpdf->Ln($lnSpace);

            $posY = $this->fpdf->posy + 72;
        }

        if(array_key_exists('observacion', $this->dataNotificarEvento) && !empty($this->dataNotificarEvento['observacion'])) {
            $this->fpdf->setXY($this->fpdf->posx + 10, $posY);
            $this->fpdf->SetFont('Arial', 'B', 9);
            $this->fpdf->Cell($widthCell, 4, utf8_decode("Observaciones:"), 0, 0, 'L');
            $this->fpdf->SetFont('Arial', '', 9);			
            $this->fpdf->MultiCell(120, 4, utf8_decode($this->dataNotificarEvento['observacion']), 0, 'L');
            $this->fpdf->Ln($lnSpace);
        }

        return $this->fpdf->Output('S');
    }
}
