<?php
namespace App\Http\Modulos\RepresentacionesGraficas\Eventos\etl_generica\rgGenerica;

use Ramsey\Uuid\Uuid;
use App\Http\Traits\DoTrait;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\RepresentacionesGraficas\CoreEventos\RgBase;
use App\Http\Modulos\RepresentacionesGraficas\CoreEventos\PDFBase;
use App\Http\Modulos\RepresentacionesGraficas\Eventos\etl_generica\rgGenerica\PDFGENERICA;

/**
 * Contorlador para la generación de representaciones gráficas de indice 1
 *
 * Class RgGENERICA
 * @package App\Http\Modulos\RepresentacionesGraficas\Eventos\etl_generica\rgGenerica
 */
class RgGENERICA extends RgBase
{
    /**
     * RgGENERICA constructor.
     */
    public function initEngine(){
        //PDF
        $fpdf = $this->pdfManager();
        $fpdf->AcceptPageBreak();
        $fpdf->SetFont('Arial', '', 6);
        $fpdf->AliasNbPages();
        $fpdf->SetMargins(0, 0, 0);
        $fpdf->SetAutoPageBreak(true, 10);
        $this->fpdf = $fpdf;
    }

    /**
     * Proceso primario para la generación del PDF.
     *
     * @return array|mixed
     */
    public function getPdf() {
        extract($this->getDatos());
        $this->initEngine();
        $datosComprobante = [];

        $datosComprobante['titulo_evento']           = $titulo_evento;
        $datosComprobante['cude']                    = $cude;
        $datosComprobante['numero_evento']           = $numero_evento;
        $datosComprobante['fecha_generacion']        = $fecha_generacion;
        $datosComprobante['hora_generacion']         = $hora_generacion;
        $datosComprobante['emisor_razon_social']     = $emisor_razon_social;
        $datosComprobante['emisor_identificacion']   = $emisor_identificacion;
        $datosComprobante['receptor_razon_social']   = $receptor_razon_social;
        $datosComprobante['receptor_identificacion'] = $receptor_identificacion;
        $datosComprobante['numero_factura']          = $numero_factura;
        $datosComprobante['cufe_factura']            = $cufe_factura;
        $datosComprobante['codigo_qr']               = $codigo_qr;
        $datosComprobante['tipo_evento']             = $tipo_evento;
        $datosComprobante['observacion']             = array_key_exists('observacion', $est_motivo_rechazo) ? $est_motivo_rechazo['observacion'] : '';
        $datosComprobante['concepto_rechazo']        = array_key_exists('concepto_rechazo', $est_motivo_rechazo) ? $est_motivo_rechazo['concepto_rechazo'] : '';
        $datosComprobante['descripcion_rechazo']     = array_key_exists('descripcion_rechazo', $est_motivo_rechazo) ? $est_motivo_rechazo['descripcion_rechazo'] : '';
        $datosComprobante['motivo_rechazo']          = array_key_exists('motivo_rechazo', $est_motivo_rechazo) ? $est_motivo_rechazo['motivo_rechazo'] : '';

        $this->fpdf->datosComprobante = $datosComprobante;
        $this->fpdf->AddPage('P', 'Letter');
       
        return ['error' => false, 'pdf' => $this->fpdf->Output('S')];
    }
}