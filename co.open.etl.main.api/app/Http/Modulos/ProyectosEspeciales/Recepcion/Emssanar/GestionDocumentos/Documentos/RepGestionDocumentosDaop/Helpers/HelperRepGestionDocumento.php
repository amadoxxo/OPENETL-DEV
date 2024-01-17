<?php
namespace App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\Helpers;

use App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoDaop;

class HelperRepGestionDocumento {

    /**
     * Almacena el nombre de las etapas.
     *
     * @var array 
     */
    public static array $arrNombreEtapas = [
        1 => 'Fe/Doc Soporte Electrónico',
        2 => 'Pendiente Revisión',
        3 => 'Pendiente Aprobar Conformidad',
        4 => 'Pendiente Reconocimiento Contable',
        5 => 'Pendiente Revisión de Impuestos',
        6 => 'Pendiente de Pago',
        7 => 'Fe/Doc Soporte Electrónico Gestionado'
    ];

    /**
     * Permite identificar el estado actual de un documento dependiendo de la etapa seleccionada.
     *
     * @param RepGestionDocumentoDaop $documento Información del documento en gestión
     * @param int $etapa Identificador de la etapa
     * @return string Contiene el estado de la etapa actual del documento
     */
    public static function obtieneEstadoActualEtapa(RepGestionDocumentoDaop $documento, int $etapa): string {
        // Obtiene el estado actual de la etapa
        switch ($etapa) {
            case 1:
                if ($documento->gdo_estado_etapa1 == 'SIN_GESTION')
                    return 'SIN_GESTION';
                elseif ($documento->gdo_estado_etapa1 == 'CONFORME' && $documento->gdo_estado_etapa2 != 'REVISION_NO_CONFORME')
                    return 'CONFORME';
                elseif ($documento->gdo_estado_etapa1 == 'NO_CONFORME' && $documento->gdo_estado_etapa2 != 'REVISION_NO_CONFORME')
                    return 'NO_CONFORME';
                elseif ($documento->gdo_estado_etapa2 == 'REVISION_NO_CONFORME')
                    return 'RECHAZADO';
            break;
            case 2:
                if ($documento->gdo_estado_etapa2 == 'SIN_GESTION')
                    return 'SIN_GESTION';
                elseif ($documento->gdo_estado_etapa2 == 'REVISION_CONFORME' && $documento->gdo_estado_etapa3 != 'APROBACION_NO_CONFORME')
                    return 'REVISION_CONFORME';
                elseif ($documento->gdo_estado_etapa2 == 'REVISION_NO_CONFORME' && $documento->gdo_estado_etapa3 != 'APROBACION_NO_CONFORME')
                    return 'REVISION_NO_CONFORME';
                elseif ($documento->gdo_estado_etapa3 == 'APROBACION_NO_CONFORME')
                    return 'RECHAZADO';
            break;
            case 3:
                if ($documento->gdo_estado_etapa3 == 'SIN_GESTION')
                    return 'SIN_GESTION';
                elseif ($documento->gdo_estado_etapa3 == 'APROBACION_CONFORME' && $documento->gdo_estado_etapa4 != 'NO_APROBADA_POR_CONTABILIDAD')
                    return 'APROBACION_CONFORME';
                elseif ($documento->gdo_estado_etapa3 == 'APROBACION_NO_CONFORME' && $documento->gdo_estado_etapa4 != 'NO_APROBADA_POR_CONTABILIDAD')
                    return 'APROBACION_NO_CONFORME';
                elseif ($documento->gdo_estado_etapa4 == 'NO_APROBADA_POR_CONTABILIDAD')
                    return 'RECHAZADO';
                break;
            case 4:
                if ($documento->gdo_estado_etapa4 == 'SIN_GESTION')
                    return 'SIN_GESTION';
                elseif ($documento->gdo_estado_etapa4 == 'APROBADA_POR_CONTABILIDAD' && $documento->gdo_estado_etapa5 != 'NO_APROBADA_POR_IMPUESTOS')
                    return 'APROBADA_POR_CONTABILIDAD';
                elseif ($documento->gdo_estado_etapa4 == 'NO_APROBADA_POR_CONTABILIDAD' && $documento->gdo_estado_etapa5 != 'NO_APROBADA_POR_IMPUESTOS')
                    return 'NO_APROBADA_POR_CONTABILIDAD';
                elseif ($documento->gdo_estado_etapa5 == 'NO_APROBADA_POR_IMPUESTOS')
                    return 'RECHAZADO';
                break;
            case 5:
                if ($documento->gdo_estado_etapa5 == 'SIN_GESTION')
                    return 'SIN_GESTION';
                elseif ($documento->gdo_estado_etapa5 == 'APROBADA_POR_IMPUESTOS' && $documento->gdo_estado_etapa6 != 'NO_APROBADA_PARA_PAGO')
                    return 'APROBADA_POR_IMPUESTOS';
                elseif ($documento->gdo_estado_etapa5 == 'NO_APROBADA_POR_IMPUESTOS' && $documento->gdo_estado_etapa6 != 'NO_APROBADA_PARA_PAGO')
                    return 'NO_APROBADA_POR_IMPUESTOS';
                elseif ($documento->gdo_estado_etapa6 == 'NO_APROBADA_PARA_PAGO')
                    return 'RECHAZADO';
            break;
            case 6:
                if ($documento->gdo_estado_etapa6 == 'SIN_GESTION')
                    return 'SIN_GESTION';
                elseif ($documento->gdo_estado_etapa6 == 'APROBADA_Y_PAGADA')
                    return 'APROBADA_Y_PAGADA';
                elseif ($documento->gdo_estado_etapa6 == 'NO_APROBADA_PARA_PAGO')
                    return 'NO_APROBADA_PARA_PAGO';
            break;
            case 7:
                if ($documento->gdo_estado_etapa7 == 'SIN_GESTION')
                    return 'APROBADA_Y_PAGADA';
                break;
            default:
            break;
        }

        return "";
    }

    /**
     * Permite identificar en que etapa se encuentra un documento.
     *
     * @param RepGestionDocumentoDaop $documento Información del documento en gestión
     * @return array Array con el nombre y el número de la etapa actual
     */
    public static function etapaActualDocumento(RepGestionDocumentoDaop $documento): array {
        if ($documento->gdo_estado_etapa2 == '' || $documento->gdo_estado_etapa2 == 'REVISION_NO_CONFORME')
            return [
                "nombre_etapa_actual" => self::$arrNombreEtapas[1],
                "numero_etapa_actual" => 1
            ];
        elseif ($documento->gdo_estado_etapa3 == '' || $documento->gdo_estado_etapa3 == 'APROBACION_NO_CONFORME')
            return [
                "nombre_etapa_actual" => self::$arrNombreEtapas[2],
                "numero_etapa_actual" => 2
            ];
        elseif ($documento->gdo_estado_etapa4 == '' || $documento->gdo_estado_etapa4 == 'NO_APROBADA_POR_CONTABILIDAD')
            return [
                "nombre_etapa_actual" => self::$arrNombreEtapas[3],
                "numero_etapa_actual" => 3
            ];
        elseif ($documento->gdo_estado_etapa5 == '' || $documento->gdo_estado_etapa5 == 'NO_APROBADA_POR_IMPUESTOS')
            return [
                "nombre_etapa_actual" => self::$arrNombreEtapas[4],
                "numero_etapa_actual" => 4
            ];
        elseif ($documento->gdo_estado_etapa6 == '' || $documento->gdo_estado_etapa6 == 'NO_APROBADA_PARA_PAGO')
            return [
                "nombre_etapa_actual" => self::$arrNombreEtapas[5],
                "numero_etapa_actual" => 5
            ];
        elseif ($documento->gdo_estado_etapa7 == '')
            return [
                "nombre_etapa_actual" => self::$arrNombreEtapas[6],
                "numero_etapa_actual" => 6
            ];
        elseif ($documento->gdo_estado_etapa7 == 'SIN_GESTION')
            return [
                "nombre_etapa_actual" => self::$arrNombreEtapas[7],
                "numero_etapa_actual" => 7
            ];

        return "";
    }

    /**
     * Permite identificar por etapa, cual es el valor de esa etapa.
     *
     * @param RepGestionDocumentoDaop $documento Información del documento en gestión
     * @param int $etapa Identificador de la etapa
     * @return string Contiene el nombre de la etapa 
     */
    public static function valorEtapaDocumento(RepGestionDocumentoDaop $documento, int $etapa): string {
        switch ($etapa) {
            case 1:
                if ($documento->gdo_estado_etapa2 == '' || $documento->gdo_estado_etapa2 == 'REVISION_NO_CONFORME')
                    return self::$arrNombreEtapas[1];
                break;
            case 2:
                if (!empty($documento->gdo_estado_etapa2) && ($documento->gdo_estado_etapa3 == '' || $documento->gdo_estado_etapa3 == 'APROBACION_NO_CONFORME'))
                    return self::$arrNombreEtapas[2];
                break;
            case 3:
                if (!empty($documento->gdo_estado_etapa3) && ($documento->gdo_estado_etapa4 == '' || $documento->gdo_estado_etapa4 == 'NO_APROBADA_POR_CONTABILIDAD'))
                    return self::$arrNombreEtapas[3];
                break;
            case 4:
                if (!empty($documento->gdo_estado_etapa4) && ($documento->gdo_estado_etapa5 == '' || $documento->gdo_estado_etapa5 == 'NO_APROBADA_POR_IMPUESTOS'))
                    return self::$arrNombreEtapas[4];
                break;
            case 5:
                if (!empty($documento->gdo_estado_etapa5) && ($documento->gdo_estado_etapa6 == '' || $documento->gdo_estado_etapa6 == 'NO_APROBADA_PARA_PAGO'))
                    return self::$arrNombreEtapas[5];
                break;
            case 6:
                if (!empty($documento->gdo_estado_etapa6) && $documento->gdo_estado_etapa7 == '')
                    return self::$arrNombreEtapas[6];
                break;
            case 7:
                if (!empty($documento->gdo_estado_etapa6) && $documento->gdo_estado_etapa7 == 'SIN_GESTION')
                    return self::$arrNombreEtapas[7];
                break;
        }

        return "";
    }
}