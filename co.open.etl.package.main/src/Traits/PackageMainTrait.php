<?php

namespace openEtl\Main\Traits;

/**
 * Trait para sanitizar los strings.
 *
 * Trait PackageMainTrait
 * @package App\Main\Traits
 */
trait PackageMainTrait {

    /**
     * Reemplaza todos los acentos y los caracteres especiales de un string.
     *
     * @param $string Cadena a sanear
     *
     * @return string
     */
    public function sanear_string($string) {
        $string = trim($string);
        $string = str_replace(['á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'], ['a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'], $string);
        $string = str_replace(['é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'], ['e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'], $string);
        $string = str_replace(['í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'], ['i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'], $string);
        $string = str_replace(['ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'], ['o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'], $string);
        $string = str_replace(['ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'], ['u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'], $string);
        $string = str_replace(['ñ', 'Ñ', 'ç', 'Ç'], ['n', 'N', 'c', 'C'], $string);
        $string = str_replace(['%', '&', '\\', '(', ')', '/', '-', '+', ' '], ['porcentaje', 'y', '_', '', '', '_', '_', '_', '_'], $string);
        
        return $string;
    }

    /**
     * Realiza la validación de uno o varios email.
     *
     * @param  mixed $email Array|string Emails a validar
     * @return array
     */
    public function validationEmailRule($email) {
        $arrErroresEmail = [];
        // Si los emails que se reciben es un string y contiene más de un 
        // email separados por coma, se explota por coma
        if(!is_array($email))
            $email = explode(',', $email);

        // Recorre los emails para validarlo uno a uno
        foreach ($email as $item) {
            if($item != '' && !filter_var($item, FILTER_VALIDATE_EMAIL))
                $arrErroresEmail[] = 'El email ['. $item .'] no es válido.';
        }

        return (!empty($arrErroresEmail)) ? ['errors' => $arrErroresEmail] : [];
    }
}

