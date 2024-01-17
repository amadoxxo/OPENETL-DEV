<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Modulos\EventStatusUpdate\EventStatusUpdate;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['middleware' => 'cors'], function($api){
    // EndPoint devuelve fecha y hora del servidor
    $api->get('fecha-hora', 
        function(){
            date_default_timezone_set('America/Bogota');
            $fecha_hora = [
                'fecha' => date('Y-m-d'),
                'hora' => date('H:i:s')
            ];
            return $fecha_hora;
        }
    );

    // EndPoint para Autenticación de usuarios
    $api->post('login', 'App\Http\Controllers\Auth\AuthenticationController@login');

    $api->group(
        [
            'middleware' => ['api.auth'],
            'prefix'     => 'configuracion'
        ],
        function ($api){
            // Consulta en la DIAN las resoluciones de facturación de un OFE
            $api->post('resoluciones/consultar-resolucion-facturacion-dian', 'App\Http\Modulos\GetNumberingRange\GetNumberingRangeController@consultarResolucionFacturacionDian');
        }
    );

    // Transmitir Documentos a la DIAN
    $api->group(
        [
            'middleware' => ['api.auth', 'bindings']
        ],
        function ($api){
            // Transmite documentos a la DIAN
            $api->post('transmitir-documentos', 'App\Http\Modulos\TransmitirDocumentosController@transmitirDocumentos');
            // Acuse de recibo para múltiples documentos con el mismo tipo de acuse
            $api->post('acuse-recibo', 'App\Http\Modulos\NotificarDocumentosController@acuseReciboDocumentos');
            // Consulta de eventos DIAN
            $api->post('consultar-eventos-dian', function(Request $request) {
                $classEventStatusUpdate = new EventStatusUpdate($request->proceso);
                return $classEventStatusUpdate->consultarEventosDian($request);
            });
        }
    );

    // Acceso a plantillas de Blade para PDF de representación gráfica de documentos y
    // Acuse de recibo de documento electrónico
    $api->group(
        [
            'middleware' => ['api.auth'],
            'prefix'     => 'blade'
        ],
        function ($api){
            // Retorna la vista de Blase en Base64 de la representación gráfica de un documento
            $api->post('representacion-grafica-documento', 'App\Http\Modulos\NotificarDocumentosController@getViewRepresentacionGraficaDocumento');
            // Retorna la vista de Blase en Base64 del acuse de recibo de un documento
            $api->post('acuse-recibo-documento', 'App\Http\Modulos\NotificarDocumentosController@getViewAcuseReciboDocumento');
            // Endpoint para el envío de emails para notificar aceptación tácita de documentos
            $api->post('enviar-email-aceptacion-tacita/{cdo_id}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\aceptaciontacita\AceptacionTacitaManager@enviarEmails');
        }
    );

    // Acceso a plantillas de Blade para PDF de representación gráfica de documentos
    $api->group(
        [
            'middleware' => ['api.auth'],
            'prefix'     => 'pdf'
        ],
        function ($api) {
            // Retorna el pdf de un documento generado con fpdf
            $api->post('pdf-representacion-grafica-documento', 'App\Http\Modulos\RgController@getPdfRepresentacionGraficaDocumento');
            // Facturacion Web - Ver RG - Retorna el pdf de facturación web en proceso de creación / modificación
            $api->post('facturacion-web/ver-representacion-grafica-documento', 'App\Http\Modulos\RgController@facturacionWebVerRg');
            // Recepción - Generar representación gráfica
            $api->post('recepcion/generar-representacion-grafica', 'App\Http\Modulos\RgController@recepcionGenerarRg');
        }
    );

    // Procesos con documentos que han sido enviados a la DIAN
    $api->group(
        [
            'middleware' => ['api.auth'],
            'prefix'     => 'documentos-enviados'
        ],
        function ($api){
            // Reenvio de emails de documentos enviados
            $api->post('reenviar-email-documentos', 'App\Http\Modulos\NotificarDocumentos\NotificarDocumentos@reenviarEmailDocumentos');
            // Consultar estado de documentos en la DIAN - Genera un agendamieto nuevo para DO
            $api->post('agendar-consulta-estado-dian', 'App\Http\Modulos\TransmitirDocumentosDian\TransmitirDocumentosDian@agendarConsultaDocumentosEstadoDian');
            // Transmite documentos electrónicos a EDM - Desarrollo especial de DHL Global
            $api->post('transmitir-edm', 'App\Http\Modulos\ProyectosEspeciales\DHLGlobal\InterfaceCargueFacturasEdm@transmitirEdm');
        }
    );

    // Notificación Eventos DIAN
    $api->group(
        [
            'prefix'     => 'notificacion-eventos-dian'
        ],
        function ($api){
            // Retorna el logo del OFE utilizado en la notificación de eventos DIAN
            $api->post('logo-ofe', 'App\Http\Modulos\NotificarEventosDian\NotificarEventosDianController@logoOfe');
            // Crea/actualiza carpetas y logo del OFE relacionados con la configuración de la notificación de eventos DIAN
            $api->post('configuracion', 'App\Http\Modulos\NotificarEventosDian\NotificarEventosDianController@configuracion');
        }
    );

    // Endpoints relacionados con AWS SES
    $api->group(
        [
            'prefix'     => 'amazon-ses'
        ],
        function ($api){
            // Verificación y procesamiento de notificaciones de documentos electrónicos
            $api->post('sns-procesamiento', 'App\Http\Modulos\NotificarDocumentos\NotificarDocumentos@snsProcesamiento');
        }
    );

    // Recepción
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'recepcion/documentos'
        ],
        function ($api){
            // Transmite la información de documentos existentes en el sistema a un ERP
            $api->post('transmitir-erp','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@transmitirErp');
            // Reenvío de Notificación
            $api->post('reenvio-notificacion-evento','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@reenvioNotificacionEvento');
            // Transmite un documento de recepción a openComex
            $api->post('cbo-dhl-transmitir-opencomex','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@cboDhltransmitirOpenComex');
            // Procesa una petición originada en el microservicio DI - proceso RDI para consultar un documento en la DIAN mediante el CUFE o CUDE
            $api->post('procesar-peticion-proceso-rdi','App\Http\Modulos\Recepcion\GetStatus@procesarPeticionProcesoRdi');
        }
    );

    // Nómina electrónica
    $api->group(
        [
            'middleware' => ['api.auth'],
            'prefix'     => 'nomina-electronica'
        ],
        function ($api){
            // Consultar estado de documentos de nómina electrónica en la DIAN - Genera un agendamieto nuevo para DO
            $api->post('agendar-consulta-estado-dian', 'App\Http\Modulos\NominaElectronica\TransmitirDocumentosNominaElectronicaDian@agendarConsultaDocumentosEnviadosDnEstadoDian');
        }
    );
});
