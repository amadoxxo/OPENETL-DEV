<?php

namespace App\Http\Modulos\RepresentacionesGraficas\CoreEventos;

use openEtl\Main\Traits\PackageMainTrait;

/**
 * Clase base para la generación de representaciones gráficas de recepción en openETL.
 *
 * Class RgBase
 * @package App\Http\Modulos\RepresentacionesGraficas\CoreEventos
 */
class RgBase {
    use PackageMainTrait;

    /**
     * Data del documento.
     *
     * @var
     */
    private $datos;

    /**
     * Identificación del oferente
     *
     * @var
     */
    private $ofe_identificacion;

    /**
     * Base de datos actual
     *
     * @var
     */
    private $baseDeDatos;

    /**
     * Objeto gestor para elaborar el pdf, de este modo evitamos multiples instancias del mismo.
     *
     * @var
     */
    private $pdfManager;

    /**
     * Controlador de FPDF.
     *
     * @var PDFBase
     */
    public $fpdf;

    /**
     * RgBase constructor.
     *
     * @param $datos Información del documento
     */
    public function __construct($ofe_identificacion, $baseDeDatos, $datos) {
        $this->ofe_identificacion = $ofe_identificacion;
        $this->baseDeDatos        = $baseDeDatos;
        $this->datos              = $datos;
    }

    /**
     * Transforma un json codificado o un array a un objeto
     *
     * @param $in
     * @return mixed|object|null
     */
    public static function convertToObject($in) {
        if (is_string($in))
            return json_decode($in);
        elseif (is_array($in))
            return (object)$in;
        return null;
    }

    /**
     * Retorna los datos que serán usados en la representación gráfica.
     *
     * @return void
     */
    public function getDatos() {
        return $this->datos;
    }

    /**
     * Construye el pdf - este se sobreescribe por las clases particulares.
     * 
     * @return mixed
     */
    public function getPdf() {}

    /**
     * Obtiene un objeto gestor para poder elaborar un pdf.
     *
     * @return mixed
     */
    public function pdfManager() {
        if ($this->pdfManager === null)
            $this->pdfManager = PDFBase::buildPdfManager($this->ofe_identificacion, $this->baseDeDatos);
        return $this->pdfManager;
    }

}