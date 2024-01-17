<?php

namespace App\Http\Modulos\RepresentacionesGraficas\CoreRecepcion;

use Codedge\Fpdf\Fpdf\Fpdf;
use App\Http\Traits\DoTrait;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Gestor principal para la generación de la representación gráfica en Recepción para los pdf.
 *
 * Class PDFBase
 * @package App\Http\Modulos\RepresentacionesGraficas\CoreRecepcion
 */
class PDFBase extends Fpdf {
    /**
     * Posicion actual en X para escribir en el PDF.
     *
     * @var int|float
     */
    public $posx;

    /**
     * Posicion actual en Y para escribir en el PDF.
     *
     * @var int|float
     */
    public $posy;

    /**
     * Objeto para los datos del comprobante.
     *
     * @var array
     */
    public $datosComprobante = [];

    /**
     * Ancho de columnas.
     *
     * @var mixed
     */
    private $widths;
    
    /**
     * Posición en Y determinada.
     *
     * @var mixed
     */
    public $nPosYDet;
    
    /**
     * Constructor de la clase.
     *
     * @return void
     */
    function __construct() {
        parent::__construct();

        DoTrait::setFilesystemsInfo();
        $assets = Storage::disk(config('variables_sistema.ETL_PUBLIC_STORAGE'))->getDriver()->getAdapter()->getPathPrefix(). 'ecm/assets-ofes/fonts/';
        $this->fontpath = $assets;
    }
    
    /**
     * Encabezado del pdf.
     *
     * @return void
     */
    function Header() {}
    
    /**
     * Pie de página del pdf.
     *
     * @return void
     */
    function Footer() {}
    
    /**
     * Asigna el ancho de las columnas.
     *
     * @param  mixed $w
     * @return void
     */
    function Setwidths($w) {
        //Set the array of column widths
        $this->widths=$w;
    }
    
    /**
     * Asigna la alineación de las columnas.
     *
     * @param  mixed $a
     * @return void
     */
    function SetAligns($a){
        //Set the array of column alignments
        $this->aligns=$a;
    }
    
    /**
     * Asigna el alto de las columnas.
     *
     * @param  mixed $h
     * @return void
     */
    function SetLineHeight($h) {
        //Set the Line Height, default value 0.2
        $this->lineHeight=$h;
    }

    function SetRectLineHeight($h=0.2) {
        //Set the Line Height of Rect
        $this->rectLineHeight=$h;
    }

    /**
     * Crea una fila con información.
     *
     * @param  mixed $data
     * @return void
     */
    function Row($data){
        //Calculate the height of the row
        $nb=0;
        for($i=0;$i<count($data);$i++)
            $nb=max($nb,$this->NbLines($this->widths[$i],$data[$i]));
        $h=($this->lineHeight+0.3)*$nb;
        //Issue a page break first if needed
        $this->CheckPageBreak($h);
        //Draw the cells of the row
        for($i=0;$i<count($data);$i++) {
            $w=$this->widths[$i];
            $a=isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            //Save the current position
            $x=$this->GetX();
            $y=$this->GetY();
            //Draw the border
            if (isset($this->rectLineHeight) && $this->rectLineHeight > 0) {
                $this->SetLineWidth($this->rectLineHeight);
                $this->Rect($x,$y,$w,$h);
            }

            //Print the text
            $this->MultiCell($w,$this->lineHeight,$data[$i],0,$a);
            //Put the position to the right of the cell
            $this->SetXY($x+$w,$y);
        }
        //Go to the next line
        $this->Ln($h);
    }
    
    /**
     * Valida si debe agregar una nueva página en el pdf.
     *
     * @param  mixed $h
     * @return void
     */
    function CheckPageBreak($h){
        //If the height h would cause an overflow, add a new page immediately
        if($this->GetY()+$h>$this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }

    /**
     * Calcula el numero de lineas de un texto.
     *
     * @param  mixed $w
     * @param  mixed $txt
     * @return void
     */
    function NbLines($w,$txt){
        //Computes the number of lines a MultiCell of width w will take
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
            $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',$txt);
        $nb=strlen($s);
        if($nb>0 and $s[$nb-1]=="\n")
            $nb--;
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $nl=1;
        while($i<$nb){
            $c=$s[$i];
            if($c=="\n"){
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep=$i;
                $l+=$cw[$c];
                if($l>$wmax){
                    if($sep==-1){
                        if($i==$j)
                            $i++;
                    }
                    else
                        $i=$sep+1;
                    $sep=-1;
                    $j=$i;
                    $l=0;
                    $nl++;
                }
                else
                    $i++;
            }
            return $nl;
    }

    /**
     * Obtiene la imagen en base64.
     * 
     * @param $dataURI
     * @return array|bool
     */
    function getImage($dataURI){
        $img = explode(',',$dataURI,2);
        $pic = 'data://text/plain;base64,'.$img[1];
        $type = explode("/", explode(':', substr($dataURI, 0, strpos($dataURI, ';')))[1])[1]; // get the image type
        if ($type=="png"||$type=="jpeg"||$type=="gif") return array($pic, $type);
        return false;
    }

    /**
     * Obtiene la clase gestor de FPDF para poder llevar a cabo la construcción de PDF.
     *
     * @return mixed
     */
    public static function buildPdfManager() {
        $clase = 'App\\Http\\Modulos\\RepresentacionesGraficas\\Documentos\\etl_generica\\rgRepGenerica\\PDFGENERICA_1';

        return new $clase;
    }

    /**
     * Retorna el ancho de un texto + 1.
     *
     * @param $str
     * @return float|int
     */
    public function getAnchoTexto($str) {
        return $this->GetStringWidth(utf8_decode($str)) + 1;
    }
}