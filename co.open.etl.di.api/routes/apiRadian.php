<?php

/*
|--------------------------------------------------------------------------
| API Routes - Radian
|--------------------------------------------------------------------------
|
| Rutas de la API en el microservicio DI, relacionadas con Radian
*/

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['middleware' => 'cors'], function($api){

    $api->group(
        [
            'middleware' => ['api.auth', 'bindings'],
            'prefix'     => 'radian'
        ],
        function ($api){
            // Recibe un Documento Json en el request y registra el documento no electrónico
            $api->post('agendar-estado-documento-radian', 'App\Http\Modulos\Radian\RadAgendarEstadoController@agendarRadEdi');

            // Recibe un Documento Json en el request y crea documentos en las tablas de un documento para Radian
            $api->post('registrar-documentos', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@registrarDocumentos');
        }
    );
});
