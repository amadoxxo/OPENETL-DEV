<?php

namespace App\Http\Modulos;

use Mail;
use JWTAuth;
use Validator;
use App\Http\Models\User;
use App\Http\Traits\DoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Mail\notificarDocumento;
use App\Http\Controllers\Controller;
use openEtl\Tenant\Traits\TenantSmtp;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\Utils\OfeCustomizer;
use openEtl\Tenant\Traits\TenantDatabase;
use App\Http\Modulos\Utils\NumToLetrasEngine;
use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use App\Http\Modulos\Parametros\Monedas\ParametrosMoneda;
use App\Http\Modulos\RepresentacionesGraficas\Core\RgBase;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;
use App\Http\Modulos\Parametros\Tributos\ParametrosTributo;
use App\Http\Modulos\Documentos\EtlEstadosDocumentosDaop\EtlEstadosDocumentoDaop;
use App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaop;
use App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

/**
 * Controlador general de Notificaciones
 *
 * Class NotificarDocumentosController
 * @package App\Http\Modulos
 */
class NotificarDocumentosController extends Controller
{
    public $rulesFacturas;
    public $rulesNdNc;

    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh'])
            ->except([
                'notificacionDocumento'
            ]);

        // Reglas de validación para facturas
        $this->rulesFacturas = [
            'ofe_identificacion' => 'required|numeric',
            'rfa_prefijo' => 'nullable|string|max:5',
            'cdo_consecutivo' => 'required|string|max:20'
        ];

        // Reglas de validación para notas crédito
        $this->rulesNdNc = [
            'ofe_identificacion' => 'required|numeric',
            'cdo_consecutivo' => 'required|string|max:20'
        ];
    }

    /**
     * Obtiene el oferente de un documento
     *
     * @param integer $ofe_id
     * @return Illuminate\Support\Collection
     */
    public function getOferenteByIdentificacion($ofe_identificacion) {
        return ConfiguracionObligadoFacturarElectronicamente::select(['ofe_id', 'ofe_identificacion'])
            ->where('ofe_identificacion', $ofe_identificacion)
            ->validarAsociacionBaseDatos()
            ->first();
    }

    /**
     * Retorna un array con el tipo de documento y un prefijo por defecto dependiendo del tipo de documento
     *
     * @param string $cdo_clasificacion
     * @param string $rfa_prefijo
     * @return array
     */
    public function tipoDocumento($cdo_clasificacion, $rfa_prefijo) {
        $tipoDoc = null;
        $prefijo = null;
        switch ($cdo_clasificacion) {
            case 'FC':
                $tipoDoc = 'Factura';
                $prefijo = ($rfa_prefijo == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
            case 'NC':
                $tipoDoc = 'Nota Crédito';
                $prefijo = ($rfa_prefijo == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
            case 'ND':
                $tipoDoc = 'Nota Débito';
                $prefijo = ($rfa_prefijo == '') ? $cdo_clasificacion : $rfa_prefijo;
                break;
        }
        return [
            'clasificacionDoc' => $tipoDoc,
            'prefijo' => $prefijo
        ];
    }

}
