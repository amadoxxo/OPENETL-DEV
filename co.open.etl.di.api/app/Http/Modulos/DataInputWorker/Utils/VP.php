<?php


namespace App\Http\Modulos\DataInputWorker\Utils;

/**
 * Contiene las funciones de soporte para VP
 *
 * Class VP
 * @package App\Http\Modulos\DataInputWorker\Utils
 */
class VP {
    /**
     * Calcula el redondeo de un flotante de acuerdo a estandar establecido por la DIAN en los anexos técnicos (NTC 3711).
     * 
     * @param float $in Número flotante a redondear
     * @param int $decimals Cantidas de decimales, por defecto 2
     * @return string Número flotante redondeado
     */
    public static function redondeo($in, $decimals = 2) {
        $number = $in;
        if (!is_string($number))
            $number = (string) $number;
        $componentes = explode('.', $number);

        // Si hay una parte decimal
        if (count($componentes) === 2) {
            $decimalPart = $componentes[1];

            if (strlen($decimalPart) > $decimals) {
                $digit = (int) $decimalPart[$decimals];

                if ($digit > 5)
                    return round($in, $decimals);

                if ($digit === 5 && strlen($decimalPart) >= $decimals + 2) {
                    $lastdigit = (int) $decimalPart[$decimals + 1];
                    if ($lastdigit % 2)
                        return round($in, $decimals);
                }
            }

            return $componentes[0] . '.' . substr($decimalPart, 0, $decimals);
        }

        return $in;
    }

    /**
     * Dado un array de valores flotantes, define cual es la mayor cantidad de decimales entre ellos.
     *
     * @param array $arrFlotantes Array de flotantes
     * @return int $maxDecimales Número máximo de decimales encontrados
     */
    public static function maxDecimales($arrFlotantes) {
        $maxDecimales = 2;

        foreach ($arrFlotantes as $flotante) {
            $componentes = explode('.', $flotante);

            if(count($componentes) === 2 && strlen($componentes[1]) > $maxDecimales)
                $maxDecimales = strlen($componentes[1]);
        }

        return $maxDecimales;
    }
}