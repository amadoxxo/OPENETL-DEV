<?php
namespace App\Http\Modulos\Recepcion\TransmisionErp\etl_valoremx\Valorem;

class SoapClientValorem extends \SoapClient{
    private $timeout = 90;
    private $wsdl;
    private $options;

    function __construct($wsdl, $options) {
        parent::__construct($wsdl, $options);
        $this->wsdl = $wsdl;
        $this->options = $options;
    }

    public function __doRequest($request, $location, $action, $version, $one_way = FALSE) {
        try {
            if (!$this->timeout) {
                // Llamado normal sin requerir timeout
                $response = parent::__doRequest($request, $location, $action, $version, $one_way);
            } else {
                $headers = [
                    "Content-type: text/xml",
                    "Content-length: " . strlen($this->options['xml_transmitir']), "Connection: close",
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->options['location']);
                curl_setopt($ch, CURLOPT_VERBOSE, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->options['xml_transmitir']);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_USERPWD, $this->options['login'] . ":" . $this->options['password']);

                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    return [
                        'RegistroExitoso' => 'false',
                        'error' => curl_error($ch)
                    ];
                }
                curl_close($ch);
            }

            $response = preg_replace('/^(\x00\x00\xFE\xFF|\xFF\xFE\x00\x00|\xFE\xFF|\xFF\xFE|\xEF\xBB\xBF)/', "", $response);

            return $response;
        } catch (\Exception $e) {
            return [
                'RegistroExitoso' => 'false',
                'error' => $e->getMessage()
            ];
        }
    }
}