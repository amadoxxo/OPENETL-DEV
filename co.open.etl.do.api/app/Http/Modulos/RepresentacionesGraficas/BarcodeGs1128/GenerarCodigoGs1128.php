<?php
namespace App\Http\Modulos\RepresentacionesGraficas\BarcodeGs1128;

use BCGColor;
use BCGDrawing;
use BCGgs1128Barcode;

class GenerarCodigoGs1128 {
    public function __construct() {}

    /**
     * Genera un código de barras GS1-128 y lo almacena en un archivo
     *
     * @param string $archivo      Path al archivo que se creará con le código de barras
     * @param string $textoCodigo  Contenido del código de barras
     * @return void
     */
    public static function generarCodigoGs1128($archivo, $textoCodigo) {
        include_once(app_path() . '/Http/Modulos/RepresentacionesGraficas/BarcodeGs1128/class/BCGFontFile.php');
        include_once(app_path() . '/Http/Modulos/RepresentacionesGraficas/BarcodeGs1128/class/BCGDrawing.php');
        include_once(app_path() . '/Http/Modulos/RepresentacionesGraficas/BarcodeGs1128/class/BCGgs1128Barcode.php');

        // R, G, B para el color
        $color_black = new BCGColor(0, 0, 0);
        $color_white = new BCGColor(255, 255, 255);

        $drawException = null;
        try {
            $code = new BCGgs1128Barcode();
            $code->setScale(1);
            $code->setThickness(90);
            $code->setForegroundColor($color_black);
            $code->setBackgroundColor($color_white);
            $code->setFont(0);
            $code->parse($textoCodigo);
        } catch(\Exception $exception) {
            $drawException = $exception;
        }

        $drawing = new BCGDrawing($archivo, $color_white);
        if($drawException) {
            $drawing->drawException($drawException);
        } else {
            $drawing->setBarcode($code);
            $drawing->draw();
        }

        $drawing->finish(BCGDrawing::IMG_FORMAT_PNG);
    }
}