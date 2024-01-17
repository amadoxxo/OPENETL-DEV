<?php


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
            'middleware' => ['api.auth', 'bindings']
        ],
        function ($api){
            // Recibe un Documento Json en el request para registrar un agendamiento para procesamiento del Json
            $api->post('agendar-edi', 'App\Http\Modulos\EtlDocumentoDaopController@agendarEdi');
            // Recibe un Documento Json en el request y registra los documentos electronicos que este contiene
            $api->post('registrar-documentos', 'App\Http\Modulos\EtlDocumentoDaopController@registrarDocumentos');
            // Consulta un Documento Json registrado previamente
            $api->post('consultar-documento-registrado', 'App\Http\Modulos\EtlDocumentoDaopController@consultarDocumentoRegistrado');
            // Cargar Factura
            $api->post('documentos/cargar-factura', 'App\Http\Modulos\EtlDocumentoDaopController@cargarFactura');
            // Cargar ND y NC
            $api->post('documentos/cargar-nd-nc', 'App\Http\Modulos\EtlDocumentoDaopController@cargarNdNc');

            // Cargar Factura para FNC
            $api->post('documentos/cargar-factura-fnc', 'App\Http\Modulos\EtlDocumentoDaopController@cargarFacturaFNC');
            // Cargar ND y NC De FNC
            $api->post('documentos/cargar-nd-nc-fnc', 'App\Http\Modulos\EtlDocumentoDaopController@cargarNdNcFNC');

            // Cargar Documentos Electrónicos para DHL Aero Expreso
            $api->post('documentos/cargar-documento-electronico-dhl-aero-expreso', 'App\Http\Modulos\EtlDocumentoDaopController@cargarDocumentoElectronicoDhlAeroExpreso');

            // Cargar archivo Pikcup Cash de DHL Express
            $api->post('documentos/cargar-archivo-pickup-cash-dhl-express', 'App\Http\Modulos\EtlDocumentoDaopController@cargarArchivoPickupCashDhlExpress');
        
            // Cargar Documento Soporte
            $api->post('documentos/cargar-documento-soporte', 'App\Http\Modulos\EtlDocumentoDaopController@cargarDocumentoSoporte');

            // Cargar Nota Crédito Documento Soporte
            $api->post('documentos/cargar-nota-credito-documento-soporte', 'App\Http\Modulos\EtlDocumentoDaopController@cargarNotaCreditoDocumentoSoporte');
        }
    );

    $api->group(
        [
            'middleware' => ['api.auth', 'bindings'],
            'prefix'     => 'emision'
        ],
        function ($api){
            // Endpoint similar a /api/registrar-documentos pero con diferencia en la respuesta que entrega
            $api->post('registrar-documentos', 'App\Http\Modulos\EtlDocumentoDaopController@emisionRegistrarDocumentos');
        }
    );

    $api->group(
        [
            'middleware' => ['api.auth', 'bindings'],
            'prefix'     => 'recepcion'
        ],
        function ($api){
            // Recibe un Documento Json en el request y registra el documento no electrónico
            $api->post('registrar-documento-no-electronico', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@registrarDocumentoNoElectronico');
            // Descarga los anexos de un correo recibido y procesado correctamente
            $api->post('correos-recibidos/descargar', 'App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManualController@descargarAnexosCorreo');
            // Asocia los anexos de un correo recibido con un documento electrónico
            $api->post('documentos/documentos-anexos/asociar', 'App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManualController@asociarAnexosCorreoDocumento');
        }
    );
});
