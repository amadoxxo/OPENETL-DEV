<?php


namespace App\Http\Traits;

/**
 * Metodos generales para la gestión de Documentos.
 *
 * Trait DocumentosTrait
 * @package App\Http\Traits
 */
trait DocumentosTrait {
    /**
     * Retorna un array con el tipo de documento y un prefijo por defecto dependiendo del tipo de documento
     *
     * @param string $cdo_clasificacion
     * @param string $rfa_prefijo
     * @return array
     */
    public static function tipoDocumento($cdo_clasificacion, $rfa_prefijo) {
        $tipoDoc = null;
        $prefijo = null;
        switch ($cdo_clasificacion) {
            case 'FC':
                $tipoDoc = 'Factura';
                $prefijo = ($cdo_clasificacion == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
            case 'NC':
                $tipoDoc = 'Nota Crédito';
                $prefijo = ($cdo_clasificacion == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
            case 'ND':
                $tipoDoc = 'Nota Débito';
                $prefijo = ($cdo_clasificacion == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
            case 'DS':
                $tipoDoc = 'Documento Soporte en Adquisiciones Efectuadas a no Obligados a Facturar';
                $prefijo = ($cdo_clasificacion == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
            case 'DS_NC':
                $tipoDoc = 'Nota de Ajuste del Documento Soporte en Adquisiciones Efectuadas a no Obligados a Expedir Factura o Documento Equivalente';
                $prefijo = ($cdo_clasificacion == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
        }
        return [
            'clasificacionDoc' => $tipoDoc,
            'prefijo' => $prefijo
        ];
    }
}