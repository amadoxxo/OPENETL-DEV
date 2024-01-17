<?php
namespace App\Http\Modulos\Recepcion\TransmisionErp\etl_valoremx\erp830132841;

use App\Http\Modulos\Recepcion\TransmisionErp\etl_valoremx\Valorem\ValoremController;
class RecepcionTransmisionErp830132841 extends ValoremController {
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
        $this->procesar($limiteIntentos, $oferente, $cdoIds);
    }
}
