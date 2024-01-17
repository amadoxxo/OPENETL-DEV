<?php
namespace App\Http\Modulos\Recepcion\TransmisionErp\etl_emssanar\erp901021565;

use App\Http\Modulos\Recepcion\TransmisionErp\Commons\Emssanar\EmssanarController;

class RecepcionTransmisionErp901021565 extends EmssanarController {
    /**
     * Constructor de la clase
     *
     * @param integer $limiteIntentos Límite de intentos de transmisión
     * @param ConfiguracionObligadoFacturarElectronicamente $ofe Ofe relacionado con la transmisión
     * @param string $cdoIds IDs de documentos que se desean transmitir, este parámetro llega cuando el proceso es llamado desde el cliente web a través de la ruta /recepcion/documentos/transmitir-erp
     */
    public function __construct(
        $limiteIntentos,
        $oferente,
        $cdoIds = null
    ) {
        $this->fechaInicioTransmision = '2023-02-24';
        $this->fechaFinTransmision    = null;
        $this->procesar($limiteIntentos, $oferente, $cdoIds);
    }
}
