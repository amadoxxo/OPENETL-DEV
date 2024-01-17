<?php

namespace App\Http\Modulos\facturacion\ReporteMensualTransacciones;

use JWTAuth;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Modulos\Sistema\Agendamiento\AdoAgendamiento;

class ReporteMensualTransaccionesController extends Controller {
    /**
     * Middlewares utilizados en el controlador
     */
    public function __construct() {
        $this->middleware(['jwt.auth', 'jwt.refresh']);
        $this->middleware([
            'VerificaMetodosRol:FacturacionReporteMensual'
        ])->only([
            'agendarReporteMensualTransacciones'
        ]);
    }

    /**
     * Permite la generación del reporte mensual de facturación
     * de acuerdo a los parametros recibidos
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function agendarReporteMensualTransacciones(Request $request) {
        // Obtencion del Token
        $user = auth()->user();

        /**
         OJO - Con agendamiento no se puede porque la tabla no tiene campos
         que permitan guardar los parametros recibidos
         Lo ideal es disparar en tiempo real un comando que reciba los parametros
         y que funcione en background
         */
        //Crea el agendamiento
        $agendamiento = AdoAgendamiento::create([
            'usu_id'                  => $user->usu_id,
            'age_proceso'             => 'REPORTE-MENSUAL-TRANSACCIONES',
            'age_cantidad_documentos' => '1',
            'age_prioridad'           => null,
            'usuario_creacion'        => $user->usu_id,
            'estado'                  => 'ACTIVO',
        ]);

        if($agendamiento) {
            return response()->json([
                'message' => 'El reporte mensual de transacciones será procesado en background, por favor consulte nuevamente para verificar la creación del archivo para su descarga.'
            ], 200);
        } else {
            return response()->json([
                'errors'  => ['No fue posible agendar la generación del reporte mensual de transacciones'],
                'message' => 'Error en agendamiento'
            ], 400);
        }
    }
}

