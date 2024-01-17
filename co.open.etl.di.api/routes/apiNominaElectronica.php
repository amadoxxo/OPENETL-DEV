<?php


/*
|--------------------------------------------------------------------------
| API Routes - Nomina Electrónica
|--------------------------------------------------------------------------
|
| Rutas de la API en el microservicio DI, relacionadas con Nómina Electrónica
|
*/

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['middleware' => 'cors'], function($api){
    $api->group(
        [
            'middleware' => ['api.auth'],
            'prefix'     => 'nomina-electronica'
        ],
        function ($api){
            // Recibe un Documento Json en el request y registra los documentos electronicos que este contiene
            $api->post('registrar-documentos', 'App\Http\Modulos\NominaElectronica\EtlNominaElectronicaController@registrarDocumentosNominaElectronica');

            // Cargar excel para Nomina / Novedad / Ajuste / Eliminar
            $api->post('documentos/cargar-excel', 'App\Http\Modulos\NominaElectronica\EtlNominaElectronicaController@cargarExcel');
        }
    );
});
