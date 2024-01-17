<?php

use Illuminate\Http\Request;

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
    // Proyectos Especiales
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'proyectos-especiales'
        ],
        function ($api){
            $api->group(['prefix' => 'emision'], function ($api){
                $api->group(['prefix' => 'dhl-express'], function ($api){
                    // Consulta una Guía en el sistema dentro del proyecto especial Pickup Cash de DHL Express
                    $api->post('documentos-cco/consultar-guia', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryPickupCashController@consultarGuia');

                    // RETORNAR DATOS PARAMETRICOS DEL PROYECTO
                    $api->group(['prefix' => 'documentos-cco/parametros'], function ($api){
                        // Retorna data paramétrica para la creación de documentos electrónicos en el proyecto especial Pickup Cash de DHL Express
                        $api->get('get-parametros-pickup-cash', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryPickupCashController@getParametrosPickupCash');
                    });

                    // DATOS COMUNES
                    $api->group(
                        [
                            'middleware' => ['bindings'],
                            'prefix' => 'documentos-cco/parametros/datos-comunes'
                        ], function ($api){
                            // Lista Paginada de Datos Comunes
                            $api->get('listar', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoComunPickupCashController@getListaDatoComunPickupCash');

                            // Retorna información relacionada con un Dato Común
                            $api->get('{PryDatoComunPickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoComunPickupCashController@getDatoComunPickupCash');

                            // Actualiza el valor asociado a un Dato Común
                            $api->put('{PryDatoComunPickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoComunPickupCashController@updateDatoComunPickupCash');
                    });

                    // DATOS FIJOS
                    $api->group(
                        [
                            'middleware' => ['bindings'],
                            'prefix' => 'documentos-cco/parametros/datos-fijos'
                        ], function ($api){
                            // Lista Paginada de Datos Fijos
                            $api->get('listar', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoFijoPickupCashController@getListaDatoFijoPickupCash');

                            // Retorna información relacionada con un Dato Fijo
                            $api->get('{PryDatoFijoPickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoFijoPickupCashController@getDatoFijoPickupCash');

                            // Actualiza el valor asociado a un Dato Fijo
                            $api->put('{PryDatoFijoPickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoFijoPickupCashController@updateDatoFijoPickupCash');
                    });

                    // DATOS VARIABLES
                    $api->group(
                        [
                            'middleware' => ['bindings'],
                            'prefix' => 'documentos-cco/parametros/datos-variables'
                        ], function ($api){
                            // Lista Paginada de Datos Variables
                            $api->get('listar', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoVariablePickupCashController@getListaDatoVariablePickupCash');

                            // Retorna información relacionada con un Dato Variable
                            $api->get('{PryDatoVariablePickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoVariablePickupCashController@getDatoVariablePickupCash');

                            // Actualiza el valor asociado a un Dato Variable
                            $api->put('{PryDatoVariablePickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryDatoVariablePickupCashController@updateDatoVariablePickupCash');
                    });

                    // EXTRACARGOS
                    $api->group(
                        [
                            'middleware' => ['bindings'],
                            'prefix' => 'documentos-cco/parametros/extracargos'
                        ], function ($api){
                            // Lista Paginada de Extracargos
                            $api->get('listar', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryCodigoHomologacionPickupCashController@getListaExtracargoPickupCash');

                            // Retorna información relacionada con un Extracargo
                            $api->get('{PryCodigoHomologacionPickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryCodigoHomologacionPickupCashController@getExtracargoPickupCash');

                            // Actualiza un Extracargo
                            $api->post('', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryCodigoHomologacionPickupCashController@createExtracargoPickupCash');

                            // Cambia el estado de un Extracargo
                            $api->post('/cambiar-estado', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryCodigoHomologacionPickupCashController@cambiarEstadoExtracargoPickupCash');

                            // Actualiza un Extracargo
                            $api->put('{PryCodigoHomologacionPickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryCodigoHomologacionPickupCashController@updateExtracargoPickupCash');
                    });

                    // PRODUCTOS
                    $api->group(
                        [
                            'middleware' => ['bindings'],
                            'prefix' => 'documentos-cco/parametros/productos'
                        ], function ($api){
                            // Lista Paginada de Productos
                            $api->get('listar', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryProductoPickupCashController@getListaProductoPickupCash');

                            // Retorna información relacionada con un Producto
                            $api->get('{PryProductoPickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryProductoPickupCashController@getProductoPickupCash');

                            // Crea un Producto
                            $api->post('', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryProductoPickupCashController@createProductoPickupCash');

                            // Cambia el estado de un Producto
                            $api->post('/cambiar-estado', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryProductoPickupCashController@cambiarEstadoProductoPickupCash');

                            // Actualiza un Producto
                            $api->put('{PryProductoPickupCash}', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryProductoPickupCashController@updateProductoPickupCash');

                            // Búsqueda predictiva de Producto
                            $api->post('search-productos-pickup-cash', 'App\Http\Modulos\ProyectosEspeciales\Emision\DHLExpress\DocumentosCCO\PryProductoPickupCashController@searchProductoPickupCash');
                    });
                });
            });

            // Rutas para proyecto especial de Validación de FNC en el módulo de Recepción
            $api->group(['prefix' => 'recepcion'], function ($api){
                $api->group(['prefix' => 'fnc'], function ($api){
                    // Retorna una lista de los datos parámetricos de validación conforme a la clasificación recibida cono parámetro
                    $api->get('validacion/listar-datos-parametricos-validacion/{campo}/{clasificacion}', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\FNC\Validacion\PryDatosParametricosValidacionController@listarDatosParametricosValidacion');

                    // Retorna una lista paginada de datos paramétricos de validación de acuerdo a la clasificación recibida en el request
                    $api->get('validacion/lista-paginada-datos-parametricos-validacion', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\FNC\Validacion\PryDatosParametricosValidacionController@listaPaginadaDatosParametricosValidacion');

                    // Crea un nuevo dato paramétrico de validación
                    $api->post('validacion/crear-datos-parametricos-validacion', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\FNC\Validacion\PryDatosParametricosValidacionController@crearDatosParametricosValidacion');

                    // Edita un dato paramétrico de validación
                    $api->put('validacion/editar-dato-parametrico-validacion/{dpv_id}', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\FNC\Validacion\PryDatosParametricosValidacionController@editarDatoParametricoValidacion');

                    // Retona el detalle de un dato paramétrico de validación
                    $api->get('validacion/ver-dato-parametrico-validacion/{dpv_id}', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\FNC\Validacion\PryDatosParametricosValidacionController@verDatoParametricoValidacion');

                    // Retona el detalle de un dato paramétrico de validación
                    $api->post('validacion/cambiar-estado-datos-parametricos-validacion', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\FNC\Validacion\PryDatosParametricosValidacionController@cambiarEstadoDatosParametricosValidacion');
                });

                // Grupo de rutas proyecto Emssanar - Gestión de Documentos
                $api->group(['prefix' => 'emssanar'], function ($api){
                    $api->group(['prefix' => 'configuracion'], function ($api){
                        // Retorna una lista de los datos parámetricos de validación conforme a la clasificación recibida como parámetro
                        $api->get('centros-costo/listar', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto\CentroCostoController@getListaCentrosCosto');
                        // Permite cambiar el estado de los centros de costo
                        $api->post('centros-costo/cambiar-estado', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto\CentroCostoController@cambiarEstado');
                        // Retorna los centros de costo en estado ACTIVO
                        $api->get('centros-costo/search-registros', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto\CentroCostoController@searchCentrosCosto');
                        // CRUD Centros de Costo
                        $api->resource('centros-costo', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosCosto\CentroCostoController', ['except' => [
                            'cambiarEstado',
                            'getListaCentrosCosto'
                        ]]);

                        // Retorna una lista de los datos parámetricos de validación conforme a la clasificación recibida como parámetro
                        $api->get('causales-devolucion/listar', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion\CausalDevolucionController@getListaCausalDevolucion');
                        // Permite cambiar el estado de los causales devolucion
                        $api->post('causales-devolucion/cambiar-estado', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion\CausalDevolucionController@cambiarEstado');
                        // Retorna las causales de devolucion en estado ACTIVO
                        $api->get('causales-devolucion/search-registros', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion\CausalDevolucionController@searchCausalesDevolucion');
                        // Retorna el endpoint de cambiarEstado y getListaCausalesDevolucion en la configuración causales-devolucion
                        $api->resource('causales-devolucion', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CausalesDevolucion\CausalDevolucionController', ['except' => [
                            'cambiarEstado',
                            'getListaCausalDevolucion'
                        ]]);

                        // Retorna una lista de los datos parámetricos de validación conforme a la clasificación recibida como parámetro
                        $api->get('centros-operacion/listar', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacionController@getListaCentroOperacion');
                        // Permite cambiar el estado de los centros operación
                        $api->post('centros-operacion/cambiar-estado', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacionController@cambiarEstado');
                        // Retorna los centros de operacion en estado ACTIVO
                        $api->get('centros-operacion/search-registros', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacionController@searchCentrosOperacion');
                        // CRUD Centros de Operación
                        $api->resource('centros-operacion', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Configuracion\CentrosOperaciones\CentroOperacionController', ['except' => [
                            'cambiarEstado',
                            'getListaCentroOperacion',
                            'searchCentrosOperacion'
                        ]]);
                    });

                    // Retorna una lista de gestión de documentos para una etapa específica
                    $api->post('gestion-documentos/lista-etapas', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoController@getListaEtapasGestionDocumentos');
                    // Busca coincidencias de adquirentes y proveedores para un OFE
                    $api->get('gestion-documentos/search-emisores','App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoController@searchEmisores');
                    // Realiza el proceso de gestionar a los documentos de una etapa específica
                    $api->post('gestion-documentos/gestionar-etapas', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoController@gestionarEtapas');
                    // Asigna el centro de operación a los documentos de la etapa 1
                    $api->post('gestion-documentos/asignar-centro-operaciones', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoController@asignarCentroOperaciones');
                    // Asigna el centro de costo a los documentos de la etapa 2
                    $api->post('gestion-documentos/asignar-centro-costo', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoController@asignarCentroCosto');
                    // Realiza la acción de siguiente etapa sobre los documentos de una etapa específica
                    $api->post('gestion-documentos/siguiente-etapa', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoController@siguienteEtapa');
                    // Realiza la acción de ver detalle sobre el documento de una etapa específica
                    $api->get('gestion-documentos/ver-detalle-etapas/{gdo_id}', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoController@verDetalleEtapas');
                    // Realiza la acción de datos contabilizado a la etapa 4
                    $api->post('gestion-documentos/datos-contabilizado', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepGestionDocumentoController@datosContabilizado');

                    // Autorizaciones
                    // Realiza el proceso de autorizar etapas
                    $api->post('autorizaciones/consultar-documento', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepAutorizacionEtapaController@consultarGestionDocumento');
                    // Realiza el proceso de autorizar etapas
                    $api->post('autorizaciones/autorizar-etapa', 'App\Http\Modulos\ProyectosEspeciales\Recepcion\Emssanar\GestionDocumentos\Documentos\RepGestionDocumentosDaop\RepAutorizacionEtapaController@autorizacionEtapas');

                });

                // Agendar un reporte de Gestión de Documentos
                $api->post('reportes/reporte-gestion-documentos/agendar-reporte', 'App\Http\Modulos\Recepcion\Reportes\RecepcionReporteGestionDocumentosController@agendarReporteGestionDocumentos');
                // Descargar un reporte procesado de Gestión de Documentos
                $api->post('reportes/reporte-gestion-documentos/descargar-reporte', 'App\Http\Modulos\Recepcion\Reportes\RecepcionReporteGestionDocumentosController@descargarReporteGestiondocumentos');
                // Lista los reportes agendados de Gestión de Documentos
                $api->post('reportes/reporte-gestion-documentos/listar-reportes', 'App\Http\Modulos\Recepcion\Reportes\RecepcionReporteGestionDocumentosController@listarReportesGestionDocumentos');
            });
        }
    );
});
