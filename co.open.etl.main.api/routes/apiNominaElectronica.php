<?php

/*
|--------------------------------------------------------------------------
| API Routes - Nómina Electrónica
|--------------------------------------------------------------------------
|
| Rutas de la api en el microservicio main, relacionadas con nómina electrónica
|
*/

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['middleware' => 'cors'], function($api){
    // Nómina Electrónica
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'nomina-electronica'
        ],
        function ($api){
            // Lista los documentos sin envío
            $api->post('lista-documentos-sin-envio', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@getListaDocumentosSinEnvio');
            // Descarga en Excel la lista de documentos sin envío
            $api->post('descargar-lista-documentos-sin-envio','App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@descargarListaDocumentosSinEnvio');
            // Descargar documentos
            $api->post('documentos/descargar', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@descargarDocumentos');
            // Marcar documentos para envio, aplica solo para documentos sin envío
            $api->post('documentos/enviar', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@enviarDocumentos');
            // Permite buscar los documentos 
            $api->get('documentos/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@buscarDocumentos');
            // Cambia el estado a los documentos - Recibe IDs de los documentos en el request
            $api->post('documentos/cambiar-estado-documentos', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@cambiarEstadoDocumentos');
            // Lista los errores de los documentos
            $api->post('documentos/lista-errores', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@getListaErroresDocumentosNomina');
            // Descargar la Lista de los errores de los documentos
            $api->post('documentos/lista-errores/excel', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@descargarListaErroresDocumentosNomina');
            // Lista los documentos enviados
            $api->post('lista-documentos-enviados', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@getListaDocumentosEnviados');
            // Descarga en Excel la lista de documentos enviados
            $api->post('descargar-lista-documentos-enviados', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@descargarListaDocumentosEnviados');
            // Enviar documentos a la DIAN, aplica solo para documentos enviados
            $api->post('documentos/enviar-documentos-dian', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@enviarDocumentosDian');
            // Consulta de documentos de nómina electrónica
            $api->post('consulta-documentos', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@consultaDocumentos');
            // Consulta de estado documentos de nómina electrónica
            $api->post('consulta-estados-documentos', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@consultaEstadosDocumentos');
            // Cambio de estado de documentos de nómina electrónica
            $api->post('cambio-estado-documento', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@cambioEstadoDocumento');
            // Generar interface de excel para Nomina / Novedad / Ajuste
            $api->get('documentos/generar-interface-nomina/{emp_id}', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@generarInterfaceNomina');
            // Generar interface de excel para Nomina / Novedad / Ajuste
            $api->get('documentos/generar-interface-eliminar/{emp_id}', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@generarInterfaceEliminar');
            // Obtiene los estados de un documento en específico para ser procesados por el frontend
            $api->post('documentos/estados-documento', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@obtenerEstadosDocumento');
            // Reportes en Background de Nómina Electrónica
            $api->post('reportes/background/generar', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@agendarReporte');
            // Listado de los reportes generados en background de Nómina Electrónica
            $api->get('reportes/background/listar-reportes-descargar', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@listarReportesDescargar');
            // Descargar el reporte generado en background de Nómina Electrónica
            $api->post('reportes/background/descargar-reporte-background', 'App\Http\Modulos\NominaElectronica\DsnCabeceraDocumentosNominaDaop\DsnCabeceraDocumentoNominaDaopController@descargarReporte');
        }
    );

    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'parametros'
        ],
        function ($api){

            // Parametro Periodo Nomina
            // Lista Paginada Periodo Nomina
            $api->get('lista-periodo-nomina', 'App\Http\Modulos\Parametros\NominaElectronica\NominaPeriodos\ParametrosNominaPeriodoController@getListaPeriodosNomina');
            // Lista filtrada para campo de tipo select
            $api->get('periodo-nomina/listar-select','App\Http\Modulos\Parametros\NominaElectronica\NominaPeriodos\ParametrosNominaPeriodoController@listarSelectPeriodoNomina');
            // Cambio de estado de uno o varios Periodo Nomina ACTIVO-INACTIVO
            $api->post('periodo-nomina/cambiar-estado', 'App\Http\Modulos\Parametros\NominaElectronica\NominaPeriodos\ParametrosNominaPeriodoController@cambiarEstado');
            // Busqueda
            $api->get('periodo-nomina/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\NominaElectronica\NominaPeriodos\ParametrosNominaPeriodoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('periodo-nomina/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\NominaElectronica\NominaPeriodos\ParametrosNominaPeriodoController@consultaRegistroParametrica');
            // CRUD Periodo Nomina
            $api->resource('periodo-nomina', 'App\Http\Modulos\Parametros\NominaElectronica\NominaPeriodos\ParametrosNominaPeriodoController', ['except' => [
                'getListaPeriodosNomina',
                'listarSelectPeriodoNomina',
                'cambiarEstado',
                'busqueda'
                ]]
            );

            // Parametro Tipo Hora Extra Recargo
            // Lista Paginada Tipo Hora Extra Recargo
            $api->get('lista-tipo-hora-extra-recargo', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\ParametrosNominaTipoHoraExtraRecargoController@getListaTipoHoraExtraRecargo');
            // Lista filtrada para campo de tipo select
            $api->get('tipo-hora-extra-recargo/listar-select','App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\ParametrosNominaTipoHoraExtraRecargoController@listarSelectTipoHoraExtraRecargo');
            // Cambio de estado de uno o varios Tipo Hora Extra Recargo ACTIVO-INACTIVO
            $api->post('tipo-hora-extra-recargo/cambiar-estado', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\ParametrosNominaTipoHoraExtraRecargoController@cambiarEstado');
            // Busqueda
            $api->get('tipo-hora-extra-recargo/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\ParametrosNominaTipoHoraExtraRecargoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tipo-hora-extra-recargo/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\ParametrosNominaTipoHoraExtraRecargoController@consultaRegistroParametrica');
            // CRUD Tipo Hora Extra Recargo
            $api->resource('tipo-hora-extra-recargo', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoHoraExtraRecargo\ParametrosNominaTipoHoraExtraRecargoController', ['except' => [
                'getListaTipoHoraExtraRecargo',
                'listarSelectTipoHoraExtraRecargo',
                'cambiarEstado',
                'busqueda'
                ]]
            );

            // Parametro Tipo Incapacidad
            // Lista Paginada Tipo Incapacidad
            $api->get('lista-tipo-incapacidad', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoIncapacidad\ParametrosNominaTipoIncapacidadController@getListaTipoIncapacidades');
            // Lista filtrada para campo de tipo select
            $api->get('tipo-incapacidad/listar-select','App\Http\Modulos\Parametros\NominaElectronica\NominaTipoIncapacidad\ParametrosNominaTipoIncapacidadController@listarSelectTipoIncapacidad');
            // Cambio de estado de uno o varios Tipo Incapacidad ACTIVO-INACTIVO
            $api->post('tipo-incapacidad/cambiar-estado', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoIncapacidad\ParametrosNominaTipoIncapacidadController@cambiarEstado');
            // Busqueda
            $api->get('tipo-incapacidad/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoIncapacidad\ParametrosNominaTipoIncapacidadController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tipo-incapacidad/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoIncapacidad\ParametrosNominaTipoIncapacidadController@consultaRegistroParametrica');
            // CRUD Tipo Incapacidad
            $api->resource('tipo-incapacidad', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoIncapacidad\ParametrosNominaTipoIncapacidadController', ['except' => [
                'getListaTipoIncapacidades',
                'listarSelectTipoIncapacidad',
                'cambiarEstado',
                'busqueda'
                ]]
            );

            // Parametro Tipo Nota
            // Lista Paginada Tipo Nota
            $api->get('lista-tipo-nota', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoNota\ParametrosNominaTipoNotaController@getListaTiposNotas');
            // Lista filtrada para campo de tipo select
            $api->get('tipo-nota/listar-select','App\Http\Modulos\Parametros\NominaElectronica\NominaTipoNota\ParametrosNominaTipoNotaController@listarSelectTipoNota');
            // Cambio de estado de uno o varios Tipo Nota ACTIVO-INACTIVO
            $api->post('tipo-nota/cambiar-estado', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoNota\ParametrosNominaTipoNotaController@cambiarEstado');
            // Busqueda
            $api->get('tipo-nota/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoNota\ParametrosNominaTipoNotaController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tipo-nota/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoNota\ParametrosNominaTipoNotaController@consultaRegistroParametrica');
            // CRUD Tipo Nota
            $api->resource('tipo-nota', 'App\Http\Modulos\Parametros\NominaElectronica\NominaTipoNota\ParametrosNominaTipoNotaController', ['except' => [
                'getListaTiposNotas',
                'listarSelectTipoNota',
                'cambiarEstado',
                'busqueda'
                ]]
            );

            // Parametro Tipo Contrato
            // Lista Paginada Tipo Contrato
            $api->get('lista-tipo-contrato', 'App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContratoController@getListaTiposContratos');
            // Lista filtrada para campo de tipo select
            $api->get('tipo-contrato/listar-select','App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContratoController@listarSelectTipoContrato');
            // Cambio de estado de uno o varios Tipo Contrato ACTIVO-INACTIVO
            $api->post('tipo-contrato/cambiar-estado', 'App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContratoController@cambiarEstado');
            // Busqueda
            $api->get('tipo-contrato/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContratoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tipo-contrato/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContratoController@consultaRegistroParametrica');
            // CRUD Tipo Contrato
            $api->resource('tipo-contrato', 'App\Http\Modulos\Parametros\NominaElectronica\TipoContrato\ParametrosTipoContratoController', ['except' => [
                'getListaTiposContratos',
                'listarSelectTipoContrato',
                'cambiarEstado',
                'busqueda'
                ]]
            );

            // Parametro Tipo Trabajador
            // Lista Paginada Tipo Trabajador
            $api->get('lista-tipo-trabajador', 'App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajadorController@getListaTiposTrabajador');
            // Lista filtrada para campo de tipo select
            $api->get('tipo-trabajador/listar-select','App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajadorController@listarSelectTipoTrabajador');
            // Cambio de estado de uno o varios Tipo Trabajador ACTIVO-INACTIVO
            $api->post('tipo-trabajador/cambiar-estado', 'App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajadorController@cambiarEstado');
            // Busqueda
            $api->get('tipo-trabajador/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajadorController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('tipo-trabajador/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajadorController@consultaRegistroParametrica');
            // CRUD Tipo Trabajador
            $api->resource('tipo-trabajador', 'App\Http\Modulos\Parametros\NominaElectronica\TipoTrabajador\ParametrosTipoTrabajadorController', ['except' => [
                'getListaTiposTrabajador',
                'listarSelectTipoTrabajador',
                'cambiarEstado',
                'busqueda'
                ]]
            );

            // Parametro Subtipo Trabajador
            // Lista Paginada Subtipo Trabajador
            $api->get('lista-subtipo-trabajador', 'App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajadorController@getListaSubtiposTrabajador');
            // Lista filtrada para campo de tipo select
            $api->get('subtipo-trabajador/listar-select','App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajadorController@listarSelectSubtipoTrabajador');
            // Cambio de estado de uno o varios Subtipo Trabajador ACTIVO-INACTIVO
            $api->post('subtipo-trabajador/cambiar-estado', 'App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajadorController@cambiarEstado');
            // Busqueda
            $api->get('subtipo-trabajador/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajadorController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('subtipo-trabajador/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajadorController@consultaRegistroParametrica');
            // CRUD Subtipo Trabajador
            $api->resource('subtipo-trabajador', 'App\Http\Modulos\Parametros\NominaElectronica\SubtipoTrabajador\ParametrosSubtipoTrabajadorController', ['except' => [
                'getListaSubtiposTrabajador',
                'listarSelectSubtipoTrabajador',
                'cambiarEstado',
                'busqueda'
                ]]
            );

            // Lista Paginada Formas de Pago de registros que aplican para Nomina Electrónica
            $api->get('lista-formas-pago-dn', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController@getListaFormasPagoDn');
            // Busqueda en Parametro Forma de Pago en registros que aplican para Nomina Electrónica
            $api->get('formas-pago-dn/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController@busquedaDn');
            // Lista Paginada tipos documentos electronicos de registros que aplican para Nomina Electrónica
            $api->get('lista-tipos-documentos-electronicos-dn', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController@getListaTiposDocumentosElectronicosDn');
            // Busqueda en Parametro Tipos Documentos Electronicos en registros que aplican para Nomina Electrónica
            $api->get('tipos-documentos-electronicos-dn/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController@busquedaDn');

        }
    );

    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'configuracion'
        ],
        function ($api){

            $api->group(['prefix' => 'nomina-electronica'], function ($api){
                // Generar interface excel de Empleadores
                $api->post('empleadores/generar-interface-empleadores', 'App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController@generarInterfaceEmpleador');
                // Listado de errores en cargues masivos
                $api->post('empleadores/lista-errores','App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController@getListaErroresEmpleador');
                // Descargar errores en cargues masivos de Empleadores
                $api->post('empleadores/lista-errores/excel', 'App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController@descargarListaErroresEmpleador');
                // Cargar Empleadores mediante un excel
                $api->post('empleadores/cargar-empleadores', 'App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController@cargarEmpleadores');
                // Listado de Empleadores
                $api->get('lista-empleadores','App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController@getListaEmpleadores');
                // Cambiar estado de los resgitros ACTIVO-INACTIVO
                $api->post('empleadores/cambiar-estado', 'App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController@cambiarEstado');
                // Obtiene la lista de empleadores en base a busquedas particulares
                $api->get('buscar-empleadores/{buscarValor}', 'App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController@buscarEmpleadores');
                // Busqueda
                $api->get('empleadores/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController@busqueda');
                // CRUD de Empleadores
                $api->resource('empleadores', 'App\Http\Modulos\Configuracion\NominaElectronica\Empleadores\ConfiguracionEmpleadorController', ['except' => [
                    'generarInterfaceEmpleador', 
                    'getListaErroresEmpleador',
                    'descargarListaErroresEmpleador',
                    'cargarEmpleadores',
                    'getListaEmpleadores',
                    'cambiarEstado',
                    'buscarEmpleadores',
                    'busqueda'
                    ]]
                );

                // Generar interface excel de Trabajadores
                $api->post('trabajadores/generar-interface-trabajadores', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@generarInterfaceTrabajador');
                // Listado de errores en cargues masivos
                $api->post('trabajadores/lista-errores','App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@getListaErroresTrabajador');
                // Descargar errores en cargues masivos de Trabajadores
                $api->post('trabajadores/lista-errores/excel', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@descargarListaErroresTrabajador');
                // Cargar Trabajadores mediante un excel
                $api->post('trabajadores/cargar-trabajadores', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@cargarTrabajadores');
                // Listado de Trabajadores
                $api->get('lista-trabajadores','App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@getListaTrabajadores');
                // Cambiar estado de los resgitros ACTIVO-INACTIVO
                $api->post('trabajadores/cambiar-estado', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@cambiarEstado');
                // Realiza la búsqueda de los Trabajadores
                $api->get('trabajadores/search-trabajadores', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@searchTrabajadores');
                // Crear/Editar Trabajadores desde DI
                $api->post('trabajadores/di-gestion','App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@adminFromDI');
                // Busqueda
                $api->get('trabajadores/busqueda/{campoBuscar}/valor/{valorBuscar}/empleador/{valorEmpleador}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@busqueda');
                // Retorna un Trabajador
                $api->get('trabajadores/{emp_identificacion}/{tra_identificacion}', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@showCompuesto');
                // Actualiza un Trabajador
                $api->put('trabajadores/{emp_identificacion}/{tra_identificacion}', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController@updateCompuesto');
                // CRUD de Trabajadores
                $api->resource('trabajadores', 'App\Http\Modulos\Configuracion\NominaElectronica\Trabajadores\ConfiguracionTrabajadorController', ['except' => [
                    'generarInterfaceTrabajador',
                    'getListaErroresTrabajador',
                    'descargarListaErroresTrabajador',
                    'cargarTrabajadores',
                    'getListaTrabajadores',
                    'cambiarEstado',
                    'searchTrabajadores',
                    'adminFromDI',
                    'busqueda',
                    'showCompuesto',
                    'updateCompuesto'
                    ]]
                );
            });

            // Lista Paginada Software Proveedor Tecnologico de registros que aplican para Nomina Electrónica
            $api->get('lista-spt-dn', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@getListaSptDn');
            // Busqueda en Software Proveedor Tecnologico en registros que aplican para Nomina Electrónica
            $api->get('spt-dn/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@busquedaDn');
        }
    );
});
