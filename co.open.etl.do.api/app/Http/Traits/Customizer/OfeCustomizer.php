<?php

namespace  App\Http\Traits\Customizer;

use App\Http\Modulos\DoConstantes;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Clase que se encarga de gestionar todas las propiedades o caractisticas que se pueden customizar a un ofe o retonar
 * valores genericos para todos los demas
 *
 * Class OfeCustomizer
 * @package App\Http\Modulos\Utils
 */
trait OfeCustomizer {

    /**
     * Retorna el logo que se debe incluir en la notificacion
     * @param ConfiguracionObligadoFacturarElectronicamente $oferente
     * @param $informacionAdicional
     * @return string
     */
    public static function getLogo(ConfiguracionObligadoFacturarElectronicamente $oferente, $informacionAdicional) {
        switch ($oferente->ofe_identificacion) {
            case DoConstantes::NIT_FEDERACION_NACIONAL:
                if (is_object($informacionAdicional) && isset($informacionAdicional->logo_cabecera) && $informacionAdicional->logo_cabecera == 'BUENCAFE')
                    return 'logo_buencafe.png';
                return 'logo' . $oferente->ofe_identificacion . '.png';
        }

        return 'logo' . $oferente->ofe_identificacion . '.png';
    }

    /**
     * Configura la notificacion para todos aquellos caso en que los ofes tienen campos personalizados.
     *
     * @param EtlCabeceraDocumentoDaop $documento
     * @param $informacionAdicional
     * @param $registro
     */
    public static function setCamposParticularesNotificacion(EtlCabeceraDocumentoDaop $documento, $informacionAdicional, &$registro) {
        if (isset($documento->getConfiguracionObligadoFacturarElectronicamente)) {
            $oferente = $documento->getConfiguracionObligadoFacturarElectronicamente;
            //Campos particulares para mostrar en el correo de re-envio
            switch ($oferente->ofe_identificacion) {
                case DoConstantes::NIT_DHLGLOBAL:
                case DoConstantes::NIT_DHLADUANAS:
                    $registro['documento_transporte'] = (is_object($informacionAdicional) && isset($informacionAdicional->guia_hija) && $informacionAdicional->guia_hija != '') ? $informacionAdicional->guia_hija : '';
                    break;
                case DoConstantes::NIT_SIACO:
                    $registro['numero_operacion'] = (is_object($informacionAdicional) && isset($informacionAdicional->pedido) && $informacionAdicional->pedido != '') ? $informacionAdicional->pedido : '';
                    break;
                case DoConstantes::NIT_REPREMUNDO:
                case DoConstantes::NIT_RIS:
                    $registro['numero_operacion'] = (is_object($informacionAdicional) && isset($informacionAdicional->guia_numero) && $informacionAdicional->guia_numero != '') ? $informacionAdicional->guia_numero : '';
                    break;
                case DoConstantes::NIT_LOGISTICA_REPREMUNDO:
                    $items = $documento->getDetalleDocumentosDaop;
                    $arrNumOperacion = array();
                    foreach ($items as $item) {
                        $informacionAdicionalItem = json_decode($item->ddo_informacion_adicional);
                        if ($informacionAdicionalItem && isset($informacionAdicionalItem->remesa) && $informacionAdicionalItem->remesa == 'SI') {
                            $arrNumOperacion[] = $item['ddo_codigo'];
                        }
                    }
                    $registro['numero_operacion'] = (count($arrNumOperacion) > 0) ? implode(', ', $arrNumOperacion) : '';
                    break;
                case DoConstantes::NIT_MAP_CARGO:
                    $registro['numero_operacion'] = (is_object($informacionAdicional) && isset($informacionAdicional->posicion) && $informacionAdicional->posicion != '') ? $informacionAdicional->posicion : '';
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Si el Ofe es DHLGlobal o DHLAduanas se debe crear
     * un archivo txt para colocarlo en el FTP de la empresa
     *
     * @param EtlCabeceraDocumentoDaop $documento
     */
    public static function subeFtpDhl(EtlCabeceraDocumentoDaop $documento) {
        if (isset($documento->getConfiguracionObligadoFacturarElectronicamente)) {
            $oferente = $documento->getConfiguracionObligadoFacturarElectronicamente;
            $compania = DoConstantes::DHL_COMPANIAS[$oferente->ofe_identificacion];
            $tipoDocumento = DoConstantes::DHL_TIPOS[$documento->cdo_clasificacion];

            $contenido = $compania . ';' . $tipoDocumento . ';' . $documento->rfa_prefijo . $documento->cdo_consecutivo . ';' . $documento->cdo_cufe;
            $nombreArchivo = $compania . '_' . $documento->rfa_prefijo . $documento->cdo_consecutivo . '.txt';

            Storage::disk('etl')->put($nombreArchivo, $contenido);

            // Establece la conexión al FTP del OFE
            $dhlFtp = ftp_connect($oferente->ofe_conexion_ftp['host']);

            // Inicia sesión
            $loginFtp = ftp_login($dhlFtp, $oferente->ofe_conexion_ftp['username'], $oferente->ofe_conexion_ftp['password']);

            // Método pasivo
            ftp_pasv($dhlFtp, true);

            // Ruta de la carpeta en donde se debe subir el archivo desde el disco
            $carpetaSalida = 'salida_' . strtolower($documento->cdo_tipo);
            ftp_put($dhlFtp, $oferente->ofe_conexion_ftp[$carpetaSalida] . '/' . $nombreArchivo, storage_path('xml/documentos/' . $nombreArchivo), FTP_BINARY);

            // Elimina el archivo del disco local
            Storage::disk('etl')->delete($nombreArchivo);

            // Cierra la conexión al FTP
            ftp_close($dhlFtp);
        }
    }

    /**
     * Calcula el valor a Pagar de un documento.
     *
     * @param EtlCabeceraDocumentoDaop $documento
     * @param $total
     * @param $totalMonedaExtranjera
     * @param $signo
     */
    public static function calcularValorDocumento(EtlCabeceraDocumentoDaop $documento, &$total, &$totalMonedaExtranjera, &$signo) {

        if (!isset($documento->getConfiguracionObligadoFacturarElectronicamente))
            return;

        $oferente = $documento->getConfiguracionObligadoFacturarElectronicamente;

        if (isset($documento->cdo_informacion_adicional) && !empty($documento->cdo_informacion_adicional))
            $docInformacionAdicional = json_decode($documento->cdo_informacion_adicional);
        else
            $docInformacionAdicional = '';

        //Calcular el valor de la factura segun el OFE
        if ($oferente->ofe_identificacion === DoConstantes::NIT_DHLGLOBAL || $oferente->ofe_identificacion === DoConstantes::NIT_DHLADUANAS) {
            $descuentoRetenciones = 0;
            if (isset($docInformacionAdicional->retencion_ica) && isset($docInformacionAdicional->retencion_ica->valor)) {
                if ($docInformacionAdicional->retencion_ica->valor != '') {
                    $descuentoRetenciones += $docInformacionAdicional->retencion_ica->valor;
                }
            }

            if (isset($docInformacionAdicional->retencion_iva) && isset($docInformacionAdicional->retencion_iva->valor)) {
                if ($docInformacionAdicional->retencion_iva->valor != '') {
                    $descuentoRetenciones += $docInformacionAdicional->retencion_iva->valor;
                }
            }

            if (isset($docInformacionAdicional->resolucion_facturacion)) {
                $docInformacionAdicional->resolucion_facturacion = str_replace('Numeracion', 'Numeración', $docInformacionAdicional->resolucion_facturacion);
            }

            $total = $documento->cdo_valor_sin_impuestos + $documento->cdo_impuestos - $descuentoRetenciones - $docInformacionAdicional->valor_anticipo;
            $signo = ($total >= 0) ? "POSITIVO" : "NEGATIVO";
            $total = ($total >= 0) ? $total : abs($total);

            $totalMonedaExtranjera = $documento->cdo_valor_sin_impuestos_moneda_extranjera + $documento->cdo_impuestos_moneda_extranjera - $descuentoRetenciones - $docInformacionAdicional->valor_anticipo_moneda_extranjera;
            $totalMonedaExtranjera = ($totalMonedaExtranjera > 0) ? $totalMonedaExtranjera : abs($totalMonedaExtranjera);
        }
        else {
            $anticipo = 0;
            if (
                isset($docInformacionAdicional->anticipo) &&
                $docInformacionAdicional->anticipo != '' &&
                $docInformacionAdicional->anticipo != null
            ) {
                $anticipo = $docInformacionAdicional->anticipo;
            }

            $descuento = 0;
            if (
                isset($docInformacionAdicional->valor_descuento) &&
                $docInformacionAdicional->valor_descuento != '' &&
                $docInformacionAdicional->valor_descuento != null
            ) {
                $descuento = $docInformacionAdicional->valor_descuento;
            }

            $rteIca = 0;
            if (
                isset($docInformacionAdicional->valor_reteica) &&
                $docInformacionAdicional->valor_reteica != '' &&
                $docInformacionAdicional->valor_reteica != null
            ) {
                $rteIca = $docInformacionAdicional->valor_reteica;
            }

            $rteIva = 0;
            if (
                isset($docInformacionAdicional->valor_reteiva) &&
                $docInformacionAdicional->valor_reteiva != '' &&
                $docInformacionAdicional->valor_reteiva != null
            ) {
                $rteIva = $docInformacionAdicional->valor_reteiva;
            }

            $rteFte = 0;
            if (
                isset($docInformacionAdicional->valor_retefuente) &&
                $docInformacionAdicional->valor_retefuente != '' &&
                $docInformacionAdicional->valor_retefuente != null
            ) {
                $rteFte = $docInformacionAdicional->valor_retefuente;
            }

            $totalDescuentos = ($anticipo + $descuento + $rteIca + $rteIva + $rteFte);
            $total = $documento->cdo_total - $totalDescuentos;
            $totalMonedaExtranjera = $documento->cdo_total_moneda_extranjera;

            $signo = ($total >= 0) ? "POSITIVO" : "NEGATIVO";
            $total = ($total >= 0) ? $total : abs($total);
        }
    }

}