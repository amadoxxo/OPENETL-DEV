<?php
namespace App\Http\Modulos\DataInputWorker;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Http\Modulos\Documentos\EtlFatDocumentosDaop\EtlFatDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;

class helpers {

    /**
     * Reemplaza todos los acentos por sus equivalentes sin ellos
     *
     * @param $string
     *  string la cadena a sanear
     *
     * @return string
     */
    static function sanear_string($string)
    {
        $string = trim($string);
        $string = str_replace(['á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'], ['a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'], $string);
        $string = str_replace(['é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'], ['e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'], $string);
        $string = str_replace(['í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'], ['i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'], $string);
        $string = str_replace(['ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'], ['o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'], $string);
        $string = str_replace(['ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'], ['u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'], $string);
        $string = str_replace(['ñ', 'Ñ', 'ç', 'Ç'], ['n', 'N', 'c', 'C'], $string);
        $string = str_replace(['%', '(', ')', '/', '-', '+', ' '], ['porcentaje', '', '', '_', '_', '_', '_'], $string);
        return $string;
    }

    /**
     * Permite consultar un documento en la data operativa y en el histórico.
     *
     * @param string $tipoTabla Tipo de tabla a aconsultar (daop|historico)
     * @param int $tdeId ID del tipo de documento electrónico
     * @param int $ofeId ID del OFE
     * @param array $cabecer Array con información de cabecera del documento
     * @return mixed
     */
    public function consultarDocumento(string $tipoTabla, int $tdeId, int $ofeId, array $cabecera) {
        switch($tipoTabla) {
            case 'historico':
                $modeloCabecera = EtlFatDocumentoDaop::class;
                break;
            default:
            case 'daop':
                $modeloCabecera = EtlCabeceraDocumentoDaop::class;
                break;
        }

        $documento = $modeloCabecera::select(['cdo_id', 'cdo_lote', 'estado'])
            ->where('tde_id', $tdeId)
            ->where('ofe_id', $ofeId);

        if (!is_null($cabecera['rfa_prefijo']) && $cabecera['rfa_prefijo'] !== '')
            $documento->where('rfa_prefijo', $cabecera['rfa_prefijo']);
        else
            $documento->whereRaw("(rfa_prefijo IS NULL OR rfa_prefijo = '')");

        $documento->where('cdo_consecutivo', $cabecera['cdo_consecutivo']);
        $documento = $documento->first();

        return $documento;
    }

    /**
     * Verifica la existencia de una tabla de acuerdo a la conexión establecida.
     *
     * @param string $conexion Nombre de la conexión actual a la base de datos
     * @param string $tabla Nombre de la tabla a verificar
     * @return boolean
     */
    public function existeTabla(string $conexion, string $tabla): bool {
        return Schema::connection($conexion)->hasTable($tabla);
    }
}