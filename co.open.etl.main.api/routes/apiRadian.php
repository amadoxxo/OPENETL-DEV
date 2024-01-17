<?php

/*
|--------------------------------------------------------------------------
| API Routes - Radian
|--------------------------------------------------------------------------
|
| Rutas de la api en el microservicio main, relacionadas con radian
*/

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['middleware' => 'cors'], function($api){
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'parametros/radian'
        ],
        function ($api){
            // Rutas Paramétrica de Referencia Documentos Electrónicos
            // Lista Paginada de Referencia Documentos Electrónicos
            $api->get('lista-referencia-documentos', 'App\Http\Modulos\Parametros\Radian\ReferenciaDocumentosElectronicos\ParametrosRadianReferenciaDocumentoElectronicoController@getListaReferenciaDocumentosElectronicos');
            // Cambio de estado de uno o varios referencia-documentos-electronicos ACTIVO-INACTIVO
            $api->post('referencia-documentos/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\ReferenciaDocumentosElectronicos\ParametrosRadianReferenciaDocumentoElectronicoController@cambiarEstado');
            // Búsqueda
            $api->get('referencia-documentos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\ReferenciaDocumentosElectronicos\ParametrosRadianReferenciaDocumentoElectronicoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('referencia-documentos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\ReferenciaDocumentosElectronicos\ParametrosRadianReferenciaDocumentoElectronicoController@consultaRegistroParametrica');
            // CRUD de Referencia Documentos Electrónicos
            $api->resource('referencia-documentos', 'App\Http\Modulos\Parametros\Radian\ReferenciaDocumentosElectronicos\ParametrosRadianReferenciaDocumentoElectronicoController', ['except' => [
                'cambiarEstado',
                'getListaReferenciaDocumentosElectronicos',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Tipos Pagos
            // Lista Paginada de Tipos Pagos
            $api->get('lista-tipos-pagos', 'App\Http\Modulos\Parametros\Radian\TiposPagos\ParametrosRadianTipoPagoController@getListaTiposPagos');
            // Cambio de estado de uno o varios tipos-pagos ACTIVO-INACTIVO
            $api->post('tipos-pagos/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\TiposPagos\ParametrosRadianTipoPagoController@cambiarEstado');
            // Búsqueda
            $api->get('tipos-pagos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\TiposPagos\ParametrosRadianTipoPagoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tipos-pagos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\TiposPagos\ParametrosRadianTipoPagoController@consultaRegistroParametrica');
            // CRUD de Tipos Pagos
            $api->resource('tipos-pagos', 'App\Http\Modulos\Parametros\Radian\TiposPagos\ParametrosRadianTipoPagoController', ['except' => [
                'cambiarEstado',
                'getListaTiposPagos',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Eventos Documento Electrónico
            // Lista Paginada de Eventos Documento Electrónico
            $api->get('lista-evento-documento-electronico', 'App\Http\Modulos\Parametros\Radian\EventosDocumentosElectronicos\ParametrosRadianEventoDocumentoElectronicoController@getListaEventoDocumentoElectronico');
            // Cambio de estado de uno o varios eventos-documentos-electronico ACTIVO-INACTIVO
            $api->post('evento-documento-electronico/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\EventosDocumentosElectronicos\ParametrosRadianEventoDocumentoElectronicoController@cambiarEstado');
            // Búsqueda
            $api->get('evento-documento-electronico/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\EventosDocumentosElectronicos\ParametrosRadianEventoDocumentoElectronicoController@busqueda');
            // Buscar Evento Documento Electrónico en select de Tipo Operación
            $api->get('search-evento-documento-electronico/{valorBuscar}', 'App\Http\Modulos\Parametros\Radian\EventosDocumentosElectronicos\ParametrosRadianEventoDocumentoElectronicoController@buscarEventoDocumentoElectronico');
            // Ventana Modal Evento Documento
            $api->get('evento-documento-electronico/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\EventosDocumentosElectronicos\ParametrosRadianEventoDocumentoElectronicoController@buscarEventosDocumentosElectronicos');
            // Obtiene un registro seleccionado
            $api->post('evento-documento-electronico/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\EventosDocumentosElectronicos\ParametrosRadianEventoDocumentoElectronicoController@consultaRegistroParametrica');
            // CRUD de Eventos Documento Electrónico
            $api->resource('evento-documento-electronico', 'App\Http\Modulos\Parametros\Radian\EventosDocumentosElectronicos\ParametrosRadianEventoDocumentoElectronicoController', ['except' => [
                'cambiarEstado',
                'getListaEventoDocumentoElectronico',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Tipos Operación Radian
            // Lista Paginada de Tipos Operación Radian
            $api->get('lista-tipo-operacion', 'App\Http\Modulos\Parametros\Radian\TiposOperaciones\ParametrosRadianTipoOperacionController@getListaTiposOperaciones');
            // Cambio de estado de uno o varios tipo-operacion ACTIVO-INACTIVO
            $api->post('tipo-operacion/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\TiposOperaciones\ParametrosRadianTipoOperacionController@cambiarEstado');
            // Búsqueda
            $api->get('tipo-operacion/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\TiposOperaciones\ParametrosRadianTipoOperacionController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tipo-operacion/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\TiposOperaciones\ParametrosRadianTipoOperacionController@consultaRegistroParametrica');
            // CRUD de Tipos Operación Radian
            $api->resource('tipo-operacion', 'App\Http\Modulos\Parametros\Radian\TiposOperaciones\ParametrosRadianTipoOperacionController', ['except' => [
                'cambiarEstado',
                'getListaTipoOperacion',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Factor
            // Lista Paginada de Factor
            $api->get('lista-factor', 'App\Http\Modulos\Parametros\Radian\Factor\ParametrosRadianFactorController@getListaFactor');
            // Cambio de estado de uno o varios factor ACTIVO-INACTIVO
            $api->post('factor/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\Factor\ParametrosRadianFactorController@cambiarEstado');
            // Búsqueda
            $api->get('factor/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\Factor\ParametrosRadianFactorController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('factor/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\Factor\ParametrosRadianFactorController@consultaRegistroParametrica');
            // CRUD de Factor
            $api->resource('factor', 'App\Http\Modulos\Parametros\Radian\Factor\ParametrosRadianFactorController', ['except' => [
                'cambiarEstado',
                'getListaFactor',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Roles
            // Lista Paginada de Roles
            $api->get('lista-roles', 'App\Http\Modulos\Parametros\Radian\Roles\ParametrosRadianRolesController@getListaRoles');
            // Cambio de estado de uno o varios factor ACTIVO-INACTIVO
            $api->post('roles/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\Roles\ParametrosRadianRolesController@cambiarEstado');
            // Búsqueda
            $api->get('roles/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\Roles\ParametrosRadianRolesController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('roles/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\Roles\ParametrosRadianRolesController@consultaRegistroParametrica');
            // Obtiene un registro de roles asociados a eventos de documento electronico y tipos de operación
            $api->get('roles-eventos/{id}', 'App\Http\Modulos\Parametros\Radian\Roles\ParametrosRadianRolesController@showRolesEventos');
            // CRUD de Roles
            $api->resource('roles', 'App\Http\Modulos\Parametros\Radian\Roles\ParametrosRadianRolesController', ['except' => [
                'cambiarEstado',
                'getListaRoles',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Endosos
            // Lista Paginada de Endosos
            $api->get('lista-endoso', 'App\Http\Modulos\Parametros\Radian\Endosos\ParametrosRadianEndosoController@getListaEndoso');
            // Cambio de estado de uno o varios Endosos ACTIVO-INACTIVO
            $api->post('endoso/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\Endosos\ParametrosRadianEndosoController@cambiarEstado');
            // Búsqueda
            $api->get('endoso/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\Endosos\ParametrosRadianEndosoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('endoso/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\Endosos\ParametrosRadianEndosoController@consultaRegistroParametrica');
            // CRUD de Endosos
            $api->resource('endoso', 'App\Http\Modulos\Parametros\Radian\Endosos\ParametrosRadianEndosoController', ['except' => [
                'cambiarEstado',
                'getListaEndoso',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Alcance Mandatos
            // Lista Paginada de ALcance Mandatos
            $api->get('lista-alcance-mandato', 'App\Http\Modulos\Parametros\Radian\AlcancesMandatos\ParametrosRadianAlcanceMandatoController@getListaAlcanceMandato');
            // Cambio de estado de uno o varios alcance-mandato ACTIVO-INACTIVO
            $api->post('alcance-mandato/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\AlcancesMandatos\ParametrosRadianAlcanceMandatoController@cambiarEstado');
            // Búsqueda
            $api->get('alcance-mandato/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\AlcancesMandatos\ParametrosRadianAlcanceMandatoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('alcance-mandato/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\AlcancesMandatos\ParametrosRadianAlcanceMandatoController@consultaRegistroParametrica');
            // CRUD de ALcance Mandatos
            $api->resource('alcance-mandato', 'App\Http\Modulos\Parametros\Radian\AlcancesMandatos\ParametrosRadianAlcanceMandatoController', ['except' => [
                'cambiarEstado',
                'getListaAlcanceMandato',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Tiempo Mandato
            // Lista Paginada de Tiempo Mandato
            $api->get('lista-tiempo-mandato', 'App\Http\Modulos\Parametros\Radian\TiemposMandatos\ParametrosRadianTiempoMandatoController@getListaTiempoMandato');
            // Cambio de estado de uno o varios tiempos-mandatos ACTIVO-INACTIVO
            $api->post('tiempo-mandato/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\TiemposMandatos\ParametrosRadianTiempoMandatoController@cambiarEstado');
            // Búsqueda
            $api->get('tiempo-mandato/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\TiemposMandatos\ParametrosRadianTiempoMandatoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tiempo-mandato/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\TiemposMandatos\ParametrosRadianTiempoMandatoController@consultaRegistroParametrica');
            // CRUD de Tiempo Mandato
            $api->resource('tiempo-mandato', 'App\Http\Modulos\Parametros\Radian\TiemposMandatos\ParametrosRadianTiempoMandatoController', ['except' => [
                'cambiarEstado',
                'getListaTiempoMandato',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica de Tipo Mandatario
            // Lista Paginada de Tipo Mandatario
            $api->get('lista-tipo-mandatario', 'App\Http\Modulos\Parametros\Radian\TiposMandatarios\ParametrosRadianTipoMandatarioController@getListaTipoMandatario');
            // Cambio de estado de uno o varios tipos-mandatarios ACTIVO-INACTIVO
            $api->post('tipo-mandatario/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\TiposMandatarios\ParametrosRadianTipoMandatarioController@cambiarEstado');
            // Búsqueda
            $api->get('tipo-mandatario/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\TiposMandatarios\ParametrosRadianTipoMandatarioController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tipo-mandatario/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\TiposMandatarios\ParametrosRadianTipoMandatarioController@consultaRegistroParametrica');
            // CRUD de Tipo Mandatario
            $api->resource('tipo-mandatario', 'App\Http\Modulos\Parametros\Radian\TiposMandatarios\ParametrosRadianTipoMandatarioController', ['except' => [
                'cambiarEstado',
                'getListaTipoMandatario',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica Naturaleza Mandatos
            // Lista Paginada Naturaleza Mandatos
            $api->get('lista-naturaleza-mandato', 'App\Http\Modulos\Parametros\Radian\NaturalezaMandatos\ParametrosRadianNaturalezaMandatoController@getListaNaturalezaMandato');
            // Cambio de estado de uno o varios registros ACTIVO-INACTIVO
            $api->post('naturaleza-mandato/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\NaturalezaMandatos\ParametrosRadianNaturalezaMandatoController@cambiarEstado');
            // Búsqueda indicando el campo y el texto a buscar
            $api->get('naturaleza-mandato/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\NaturalezaMandatos\ParametrosRadianNaturalezaMandatoController@busqueda');
            // Obtiene un registro según sus fechas de vigencia
            $api->post('naturaleza-mandato/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\NaturalezaMandatos\ParametrosRadianNaturalezaMandatoController@consultaRegistroParametrica');
            // CRUD Naturaleza Mandatos
            $api->resource('naturaleza-mandato', 'App\Http\Modulos\Parametros\Radian\NaturalezaMandatos\ParametrosRadianNaturalezaMandatoController', ['except' => [
                'cambiarEstado',
                'getListaNaturalezaMandato',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );

            // Rutas Paramétrica Tipo Mandante
            // Lista Paginada Tipo Mandante
            $api->get('lista-tipo-mandante', 'App\Http\Modulos\Parametros\Radian\TiposMandante\ParametrosRadianTipoMandanteController@getListaTipoMandante');
            // Cambio de estado de uno o varios registros ACTIVO-INACTIVO
            $api->post('tipo-mandante/cambiar-estado', 'App\Http\Modulos\Parametros\Radian\TiposMandante\ParametrosRadianTipoMandanteController@cambiarEstado');
            // Búsqueda indicando el campo y el texto a buscar
            $api->get('tipo-mandante/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Radian\TiposMandante\ParametrosRadianTipoMandanteController@busqueda');
            // Obtiene un registro según sus fechas de vigencia
            $api->post('tipo-mandante/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Radian\TiposMandante\ParametrosRadianTipoMandanteController@consultaRegistroParametrica');
            // CRUD Tipo Mandante
            $api->resource('tipo-mandante', 'App\Http\Modulos\Parametros\Radian\TiposMandante\ParametrosRadianTipoMandanteController', ['except' => [
                'cambiarEstado',
                'getListaTipoMandante',
                'busqueda',
                'consultaRegistroParametrica'
                ]]
            );
        }
    );

    // Configuración
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'configuracion/radian'
        ],
        function ($api){
            //Generar excel oferentes
            $api->post('actor/generar-interface-actor', 'App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActorController@generarInterfaceActor');
            // Cargar Actores
            $api->post('actor/cargar-actor', 'App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActorController@cargarActores');
            //Listado para Radian Actor
            $api->get('lista-actor','App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActorController@getListaActor');
            //Cambiar estado ACTIVO-INACTIVO
            $api->post('actor/cambiar-estado', 'App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActorController@cambiarEstado');
            // Listado de errores en cargues masivos del oferente
            $api->post('actor/lista-errores','App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActorController@getListaErroresActor');
            // Descargar Errores del oferente
            $api->post('actor/lista-errores/excel', 'App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActorController@descargarListaErroresActor');

            // CRUD OFE
            $api->resource('actor', 'App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActorController', ['except' => [
                'generarInterfaceActor',
                'cargarActores',
                'buscarOfe',
                'cambiarEstado',
                'busqueda',
                'getListaErroresActor',
                'descargarListaErroresActor'
                ]]
            );
        }
    );

    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'radian'
        ],
        function ($api){
            // Registro Documentos
            // Listado de errores radian registro documentos
            $api->get('registro-documentos','App\Http\Modulos\Radian\Configuracion\RadianActores\RadianActorController@listaActoresRoles');
            // Errores Registro Documentos
            $api->post('lista-errores', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@getListaLogErrores');
            // Descargar Errores del Registro Del Documento
            $api->post('lista-errores/excel', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@descargarListaErroresDocumentoRadian');
            // Búsqueda de documentos por campo-valor
            $api->get('/documentos/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@buscarDocumentos');
            // Lista para tracking de documentos Radian
            $api->post('documentos/lista-documentos-recibidos','App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@getListaDocumentosRadian');
            // Obtiene los estados de un documento en específico
            $api->post('documentos/estados-documento', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@obtenerEstadosDocumento');
            // Eventos que posee un actor
            $api->get('documentos/eventos-actor/{identificacion}', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@eventosActor');
            // Agendar consulta de estado de documentos en la DIAN
            $api->post('documentos/agendar-consulta-estado-dian', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@agendarConsultaEstadoDian');
            // Agendar acuse de recibo de documentos Radian
            $api->post('documentos/agendar-acuse-recibo', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@agendarAcuseRecibo');
            // Agendar recibo bien de documentos Radian
            $api->post('documentos/agendar-recibo-bien', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@agendarReciboBien');
            // Agendar aceptacion expresa de documentos Radian
            $api->post('documentos/agendar-aceptacion-expresa', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@agendarAceptacionExpresa');
            // Agendar rechazo de documentos Radian
            $api->post('documentos/agendar-rechazo', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@agendarRechazo');
            // Agendar aceptación tácita de documentos Radian
            $api->post('documentos/agendar-aceptacion-tacita', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@agendarAceptacionTacita');
            // Reportes en Background de Radian
            $api->post('reportes/background/generar', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@agendarReporte');
            // Listado de los reportes generados en background de Radian
            $api->get('reportes/background/listar-reportes-descargar', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@listarReportesDescargar');
            // Descargar el reporte generado en background de Radians
            $api->post('reportes/background/descargar-reporte-background', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@descargarReporte');
            // Descargar documentos 
            $api->post('documentos/descargar', 'App\Http\Modulos\Radian\Documentos\RadianCabeceraDocumentosDaop\RadianCabeceraDocumentoDaopController@descargarDocumentos');
        } 
    );

    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'commons'
        ],
        function ($api){
            // Rutas Documentos
            $api->get('get-data-init-for-build', 'App\Http\Modulos\Commons\CommonsController@getInitDataForBuild');
        }
    );
});
