<?php
namespace App\Http\Modulos\NotificarDocumentos;

use Illuminate\Support\Arr;

class MetodosBase {
    public function __construct() {}

    /**
     * Codifica una cadena.
     * 
     * @param string $cadena Cadena a codificar
     * @return string $cadena Cadena codificada
     */
    public function codificarCadena($cadena){
        return htmlspecialchars(utf8_encode($cadena), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Obtiene el nombre de un OFE o Adquirente a partir de la información del registro.
     *
     * @param Illuminate\Database\Eloquent\Collection $coleccionPersona Colección de datos de la persona natural/juridica
     * @param string $prefijo Cadena que indica si el dato a obtener corresponde con un oferente (ofe) o con un adquirente (adq)
     * @return string Nombre del OFE o Adquirente
     */
    public function obtenerNombre($coleccionPersona, $prefijo) {
        $razonSocial     = $prefijo . '_razon_social';
        $primerNombre    = $prefijo . '_primer_nombre';
        $otrosNombres    = $prefijo . '_otros_nombres';
        $primerApellido  = $prefijo . '_primer_apellido';
        $segundoApellido = $prefijo . '_segundo_apellido';
        return trim($coleccionPersona->$razonSocial ?? $coleccionPersona->$primerNombre . ' ' . $coleccionPersona->$otrosNombres . ' ' .$coleccionPersona->$primerApellido . ' ' . $coleccionPersona->$segundoApellido);
    }

    /**
     * Obtiene el datos paramétrico requerido del array de paramétricas.
     * 
     * @param array $arrParametrica Array de la parametrica desde el cual se va a obtener el dato
     * @param string $campoParametrica Nombre del campo dentro del array de paramétricas
     * @param string $campoDcumento Valor del campo asociado al documento con el cual se va a comparar
     * @param string $campoRetornar Nombre del campo del array de paramétricas cuyo valor será devuelto
     * @return string $parametrica Valor de la paramétrica solicitada
     */
    public function obtieneDatoParametrico($arrParametrica, $campoParametrica, $campoDocumento, $campoRetornar) {
        $parametrica = Arr::where($arrParametrica, function ($value, $key) use ($campoParametrica, $campoDocumento) {
            return (isset($value[$campoParametrica]) && $value[$campoParametrica] == $campoDocumento);
        });
        $parametrica = Arr::pluck($parametrica, $campoRetornar);
        if(array_key_exists(0, $parametrica)) {
            return $parametrica[0];
        } else {
            return '';
        }
    }

    /**
     * Obtiene el ApplicationResponse contenido en el XML de respuesta de una transmisión a la DIAN.
     *
     * @param string $rptaDian Respuesta XML recibida desde el WS de la DIAN
     * @return array|null $infoApplicationResponse Array conteniendo el ApplicationResponse, código de resultado, fecha y hora del AR
     */
    public function obtenerApplicationResponse(string $rptaDian) {
        $oXML = new \SimpleXMLElement(base64_decode($rptaDian));
        $vNameSpaces = $oXML->getNamespaces(true);

        //Lectura de la applicationResponse cuando el servicio web consumido es el de registro
        if($oXML->children($vNameSpaces['s'])->Body->children($vNameSpaces[''])->SendBillSyncResponse) {
            $nodoResponse = 'SendBillSyncResponse';
            $nodoResult   = 'SendBillSyncResult';
        } elseif($oXML->children($vNameSpaces['s'])->Body->children($vNameSpaces[''])->GetStatusResponse) {
            $nodoResponse = 'GetStatusResponse';
            $nodoResult   = 'GetStatusResult';
        } elseif($oXML->children($vNameSpaces['s'])->Body->children($vNameSpaces[''])->GetStatusZipResponse) {
            $nodoResponse = 'GetStatusZipResponse';
            $nodoResult   = 'GetStatusZipResult';
        } elseif($oXML->children($vNameSpaces['s'])->Body->children($vNameSpaces[''])->SendEventUpdateStatusResponse) {
            $nodoResponse = 'SendEventUpdateStatusResponse';
            $nodoResult   = 'SendEventUpdateStatusResult';
        } elseif($oXML->children($vNameSpaces['s'])->Body->children($vNameSpaces[''])->GetStatusEventResponse) {
            $nodoResponse = 'GetStatusEventResponse';
            $nodoResult   = 'GetStatusEventResult';
        }

        $oBody   = $oXML->children($vNameSpaces['s'])
                    ->Body
                    ->children($vNameSpaces[''])
                    ->$nodoResponse
                    ->children($vNameSpaces[''])
                    ->$nodoResult
                    ->children($vNameSpaces['b']);

        // En la respuesta emitida por el proceso de habilitacion Metodo SendTestSetAsync, 
        // se incluye el nivel DianResponse
        if (isset($oBody->DianResponse)) {
            $oBody = $oBody->DianResponse;
        }

        if(isset($oBody->XmlBase64Bytes) && $oBody->XmlBase64Bytes != '') {
            $applicationResponse = base64_decode($oBody->XmlBase64Bytes);

            $oDomtree     = new \DOMDocument();
            $oDomtree->loadXML($applicationResponse);
            $responseCode = isset($oDomtree->getElementsByTagName('ResponseCode')->item(0)->nodeValue) ? $oDomtree->getElementsByTagName('ResponseCode')->item(0)->nodeValue : '';
            $issueDate    = isset($oDomtree->getElementsByTagName('IssueDate')->item(0)->nodeValue) ? $oDomtree->getElementsByTagName('IssueDate')->item(0)->nodeValue : '';
            $issueTime    = isset($oDomtree->getElementsByTagName('IssueTime')->item(0)->nodeValue) ? $oDomtree->getElementsByTagName('IssueTime')->item(0)->nodeValue : '';

            $infoApplicationResponse['applicationResponse']   = $applicationResponse;
            $infoApplicationResponse['responseCode']          = $responseCode;
            $infoApplicationResponse['issueDate']             = $issueDate;
            $infoApplicationResponse['issueTime']             = $issueTime;
        } else {
            $infoApplicationResponse = null;
        }

        return $infoApplicationResponse;
    }

    /**
     * Obtiene el valor de un nodo dado su XPATH.
     *
     * @param \SimpleXMLElement $xml XML-UBL en procesamiento
     * @param string $xpath xPath desde donde se debe obtener data
     * @return \SimpleXMLElement|null Valor del nodo
     */
    public function getValueByXpath(\SimpleXMLElement $xml, string $xpath) {
        $obj = $xml->xpath($xpath);
        if ($obj) {
            return trim($obj[0]);
        }

        return null;
    }
}
