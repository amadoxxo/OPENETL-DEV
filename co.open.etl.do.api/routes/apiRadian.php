<?php

/*
|--------------------------------------------------------------------------
| API Routes - Radian
|--------------------------------------------------------------------------
|
| Rutas de la api en el microservicio DI, relacionadas con Radian
*/

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['middleware' => 'cors'], function($api){

    $api->group(
        [
            'middleware' => ['api.auth', 'bindings'],
            'prefix'      => 'radian/documentos'
        ],
        function ($api){
            // Recibe un Documento Json en el request y registra el documento
            $api->post('procesar-peticion-proceso-radian-crear-documentos', 'App\Http\Modulos\Radian\GetStatus@procesarPeticionProcesoRadian');
            // Reenvío de Notificación
            $api->post('reenvio-notificacion-evento', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@reenvioNotificacionEvento');
        }
    );
});
