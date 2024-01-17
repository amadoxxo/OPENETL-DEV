<?php
/**
 * Clase personalizada de excepciones para poder retornar información relacionada con el proceso y poder capturarla
 */

namespace App\Http\Modulos\Radian;

class RadianException extends \Exception {
    protected $_documento;
    public function __construct($message =" ", $code=0 , \Exception $previous = null, $documento = null) {
        $this->_documento = $documento;
        parent::__construct($message, $code, $previous);
    }

    public function getDocumento() {
        return $this->_documento;
    }
}
