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

    // Generar UUID
    $api->get('uuid', 'App\Http\Controllers\UuidController@generarUUID');

    // EndPoint para Autenticación de usuarios
    $api->post('login', 'App\Http\Controllers\Auth\AuthenticationController@login');

    // Reestablecer clave de acceso y enviar un email con la misma
    $api->post('password/email', 'App\Http\Controllers\Auth\PasswordController@email');

    // Verifica si existe o no un Adquirente para consultas realizadas desde el microservicio DI de otro servidor ETL
    // esta ruta no está protegida por el middleware de autenticación toda vez que los IDs de los usuarios pueden ser 
    // diferentes entre servidores
    $api->post('configuracion/adquirentes/di-gestion-obtener-adquirente','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@obtenerAdquirenteDI');

    // Usuarios
    $api->group(
        [
            'middleware' => ['api.auth', 'bindings']
        ],
        function ($api){
            // Cambiar la clave de acceso, por ejemplo cuando el usuario la modifica desde el dashboard
            $api->post('password/change', 'App\Http\Controllers\Auth\PasswordController@change');
            // Lista de Base de datos
            $api->get('lista-base-datos', 'App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatosController@getListaBaseDatos');
            // Lista de Base de datos conforme al usuario autenticado
            $api->get('lista-base-datos-usuario', 'App\Http\Modulos\Sistema\AuthBaseDatos\AuthBaseDatosController@getListaBaseDatosUsuario');
            // Consulta el estado del certificado de vencimiento del usuario autenticado
            $api->get('vencimiento-certificado', 'App\Http\Modulos\Certificados\CertificadoController@getCertificadosVencimiento');
        }
    );

    // Sistema y ACLs
    $api->group(
        [
            'middleware' => ['api.auth', 'bindings'],
            'prefix' => 'sistema',        
        ],
        function ($api){

            //INICIO DE RUTAS DE USUARIOS
            // Cambio de estado de uno o varios usuarios ACTIVO-INACTIVO
            $api->post('usuarios/cambiar-estado', 'App\Http\Controllers\Users\UserController@cambiarEstado');
            // Cambio de password de un usuario
            $api->post('usuarios/cambiar-password/{user}', 'App\Http\Controllers\Users\UserController@cambiarPassword');
            // Obtiene el detalle de un usuario: Info básica, roles-permisos, compañias-clientes y procesos (Intenros)
            // $api->get('detalle-usuarios/{user}', 'App\Http\Controllers\Users\UserController@getDetalleUsuario');
            // Generar excel Usuario
            $api->post('usuarios/generar-interface-usuarios', 'App\Http\Controllers\Users\UserController@generarInterfaceUsuarios');
            // Descargar Excel de Usuarios
            $api->post('excel-usuarios','App\Http\Controllers\Users\UserController@descargarListaUsuarios');
            // Cargar Usuarios
            $api->post('usuarios/cargar-usuarios', 'App\Http\Controllers\Users\UserController@cargarUsuarios');
            // Listado de errores en cargues masivos
            $api->post('usuarios/lista-errores','App\Http\Controllers\Users\UserController@getListaErroresUsuarios');
            // Descargar Errores Usuarios
            $api->post('usuarios/lista-errores/excel', 'App\Http\Controllers\Users\UserController@descargarListaErroresUsuarios');
            // Obtiene usuarios en base a busquedas particulares
            $api->get('obtener-usuarios/{buscarValor}', 'App\Http\Controllers\Users\UserController@obtenerUsuarios');
            // Obtiene usuarios en base a busquedas particulares
            $api->get('obtener-lista-usuarios/{ofe_idententificacion}', 'App\Http\Controllers\Users\UserController@obtenerListaUsuarios');
            // Obtiene los ofes asociados a un usuario en openIDE para cargas masivas de documentos
            $api->get('obtener-ofes-ide-usuarios/{user}', 'App\Http\Controllers\Users\UserController@getOfesCargaMasiva');
            // Obtiene la lista de proveedores a los que el usuario le puede gestionar documentos
            $api->get('proveedores-usuarios/{usuario}', 'App\Http\Controllers\Users\UserController@proveedoresGestionables');
            // Lista paginada de usuarios
            $api->get('lista-usuarios', 'App\Http\Controllers\Users\UserController@getListaUsuarios');
            // Retorna los datos del usuario autenticado
            $api->get('usuarios/datos-usuario-autenticado', 'App\Http\Controllers\Users\UserController@getDatosUsuarioAutenticado');
            // Actualiza los datos del usuario autenticado
            $api->put('usuarios/actualizar-datos-usuario-autenticado', 'App\Http\Controllers\Users\UserController@updateDatosUsuarioAutenticado');
            // Obtiene la lista de usuarios en base a busquedas particulares
            $api->get('buscar-usuarios/{buscarValor}/{consultasAdicionales?}', 'App\Http\Controllers\Users\UserController@buscarUsuarios');
            // Generar la interfaz de excel para cargar roles a usuarios
            $api->post('usuarios/generar-interface-asignacion-roles', 'App\Http\Controllers\Users\UserController@generarInterfaceAsignacionRoles');
            // Descarga los roles asignados a los usuarios
            $api->get('usuarios/lista-roles-asignados', 'App\Http\Controllers\Users\UserController@getListaRolesUsuarios');
            // Cargar la asignación de roles a usuarios
            $api->post('usuarios/cargar-asignacion-roles', 'App\Http\Controllers\Users\UserController@cargarAsignacionRolesUsuarios');
            // Recurso del controlador UserController
            $api->resource('usuarios', 'App\Http\Controllers\Users\UserController', ['except' => [
                    'cambiarEstado',
                    'cambiarPassword',
                    'getDetalleUsuario',
                    'generarInterfaceUsuarios',
                    'descargarListaUsuarios',
                    'cargarUsuarios',
                    'getListaErroresUsuarios',
                    'descargarListaErroresUsuarios',
                    'obtenerUsuarios',
                    'obtenerListaUsuarios',
                    'getOfesCargaMasiva',
                    'proveedoresGestionables',
                    'getListaUsuarios',
                    'getDatosUsuarioAutenticado',
                    'updateDatosUsuarioAutenticado'
                ]]
            );
            //FIN DE LAS RUTAS DE USUARIOS


            // Lista paginada de festivos
            $api->get('lista-festivos', 'App\Http\Modulos\Sistema\Festivos\SistemaFestivoController@getListaFestivos');
            // Cambia el estado de un festivo
            $api->post('festivos/cambiar-estado', 'App\Http\Modulos\Sistema\Festivos\SistemaFestivoController@cambiarEstado');
            // Recurso del controlador SistemaFestivoController
            $api->resource('festivos', 'App\Http\Modulos\Sistema\Festivos\SistemaFestivoController', ['except' => [
                'getListaFestivos',
                'cambiarEstado'
            ]]);

            // Lista paginada de las variables del sistema
            $api->get('lista-variables-sistema', 'App\Http\Modulos\Sistema\VariablesSistema\VariableSistemaTenantController@getListaVariablesSistema');
            // Cambia el estado de la variable del sistema
            $api->post('variables-sistema/cambiar-estado', 'App\Http\Modulos\Sistema\VariablesSistema\VariableSistemaTenantController@cambiarEstado');
            // Recurso del controlador VariableSistemaTenantController
            $api->resource('variables-sistema', 'App\Http\Modulos\Sistema\VariablesSistema\VariableSistemaTenantController', ['except' => [
                'getListaVariablesSistema',
                'cambiarEstado'
            ]]);

            // Lista paginada de tiempos de aceptación tácita
            $api->get('lista-tiempos-aceptacion-tacita', 'App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacitaController@getListaTiemposAceptacionTacita');
            // Cambia el estado de un tiempo de aceptación tácita
            $api->post('tiempos-aceptacion-tacita/cambiar-estado', 'App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacitaController@cambiarEstado');
            // Lista de Tiempos de Aceptación Tácita para selects consultada desde Adquirentes y Ofes
            $api->get('tiempos-aceptacion-tacita/listar-select','App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacitaController@listarSelectTiemposAceptacionTacita');
            // Recurso del controlador SistemaTiempoAceptacionTacitaController
            $api->resource('tiempos-aceptacion-tacita', 'App\Http\Modulos\Sistema\TiemposAceptacionTacita\SistemaTiempoAceptacionTacitaController', ['except' => [
                'getListaTiemposAceptacionTacita',
                'cambiarEstado',
                'listarSelectTiemposAceptacionTacita'
            ]]);

            // Lista paginada de roles
            $api->get('lista-roles', 'App\Http\Modulos\Sistema\Roles\SistemaRolController@getListaRoles');
            // Cambia el estado de una lista de roles de usuario seleccionados
            $api->post('roles/cambiar-estado', 'App\Http\Modulos\Sistema\Roles\SistemaRolController@cambiarEstado');
            // Recurso del controlador SistemaRolController
            $api->resource('roles', 'App\Http\Modulos\Sistema\Roles\SistemaRolController', ['except' => [
                'getListaRoles'
            ]]);

            // Asigna un rol a un usuario
            $api->post('roles-usuarios/{sistemarol}/{user}', 'App\Http\Modulos\Sistema\RolesUsuario\SistemaRolesUsuarioController@setRolUsuario');
            // Elimina la asignación de un rol a un usuario
            $api->delete('roles-usuarios/{sistemarol}/{user}', 'App\Http\Modulos\Sistema\RolesUsuario\SistemaRolesUsuarioController@unsetRolUsuario');
            // Asigna múltiples roles a un usuario
            $api->post('roles-usuarios-multiple/{user}/{rolesId}', 'App\Http\Modulos\Sistema\RolesUsuario\SistemaRolesUsuarioController@setMultiplesRoles');
            // Elimina múltiples roles para un usuario
            $api->delete('roles-usuarios-multiple/{user}/{rolesId}', 'App\Http\Modulos\Sistema\RolesUsuario\SistemaRolesUsuarioController@unsetMultiplesRoles');

            // Asigna a un rol un permiso de acceso a un recurso
            $api->post('permisos/{sistemarol}/{sistemarecurso}', 'App\Http\Modulos\Sistema\Permisos\SistemaPermisoController@setPermiso');
            // Elimina un permiso de acceso a un recurso, en un rol
            $api->delete('permisos/{sistemarol}/{sistemarecurso}', 'App\Http\Modulos\Sistema\Permisos\SistemaPermisoController@unsetPermiso');
            // Asigna a un rol permisos de acceso a múltiples recursos
            $api->post('permisos-multiple/{sistemarol}/{recursosId}', 'App\Http\Modulos\Sistema\Permisos\SistemaPermisoController@setMultiplesPermisos');
            // Elimina múltiples permisos de acceso a recursos, en un rol
            $api->delete('permisos-multiple/{sistemarol}/{recursosId}', 'App\Http\Modulos\Sistema\Permisos\SistemaPermisoController@unsetMultiplesPermisos');

            // Lista paginada de recursos
            $api->get('lista-recursos', 'App\Http\Modulos\Sistema\Recursos\SistemaRecursoController@getListaRecursos');
        }
    );

    // Parámetros
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'parametros'
        ],
        function ($api){
            // Realiza búsquedas sobre las parámetricas de openETL teniendo en cuenta el parámetro tabla recibido en el request
            $api->post('search-parametricas', 'App\Http\Modulos\Commons\CommonsController@searchParametricas');
            // Lista para paises
            $api->get('lista-paises', 'App\Http\Modulos\Parametros\Paises\ParametrosPaisController@getListaPaises');
            // Cambio de estado de uno o varios paises ACTIVO-INACTIVO
            $api->post('paises/cambiar-estado', 'App\Http\Modulos\Parametros\Paises\ParametrosPaisController@cambiarEstado');
            // Ventana Modal Paises
            $api->get('paises/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Paises\ParametrosPaisController@buscarPaises');
            // Buscar Paises
            $api->get('search-paises/{valorBuscar}', 'App\Http\Modulos\Parametros\Paises\ParametrosPaisController@buscarPais');
            // Busqueda
            $api->get('paises/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Paises\ParametrosPaisController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('paises/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Paises\ParametrosPaisController@consultaRegistroParametrica');

            // CRUD Paises
            $api->resource('paises', 'App\Http\Modulos\Parametros\Paises\ParametrosPaisController', ['except' => [
                'cambiarEstado',
                'buscarPaises',
                'buscarPais',
                'getListaPaises',
                'busqueda'
                ]]
            );
            
            // Lista Paginada Departamentos  
            $api->get('lista-departamentos', 'App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamentoController@getListaDepartamentos');
            // Cambio de estado de uno o varios departamentos ACTIVO-INACTIVO
            $api->post('departamentos/cambiar-estado', 'App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamentoController@cambiarEstado');
            // Ventana Modal Departamentos
            $api->get('departamentos/{campoBuscar}/valor/{valorBuscar}/pais/{valorPais}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamentoController@buscarDepartamentos');
            // Buscar Departamentos
            $api->get('search-departamentos/{valorBuscar}/pais/{valorPais}', 'App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamentoController@buscarDepartamento');
            // Busqueda
            $api->get('departamentos/busqueda/{campoBuscar}/valor/{valorBuscar}/pais/{valorPais}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamentoController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('departamentos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamentoController@consultaRegistroParametrica');
            // CRUD Departamentos
            $api->resource('departamentos', 'App\Http\Modulos\Parametros\Departamentos\ParametrosDepartamentoController', ['except' => [
                'buscarDepartamentos',
                'buscarDepartamento',
                'cambiarEstado',
                'getListaDepartamentos',
                'busqueda'
                ]]
            );
            
            // Cambio de estado de uno o varios municipios ACTIVO-INACTIVO
            $api->get('lista-municipios', 'App\Http\Modulos\Parametros\Municipios\ParametrosMunicipioController@getListaMunicipios');
            // Cambio de estado de uno o varios municipios ACTIVO-INACTIVO
            $api->post('municipios/cambiar-estado', 'App\Http\Modulos\Parametros\Municipios\ParametrosMunicipioController@cambiarEstado');
            // Ventana Modal Municipios
            $api->get('municipios/{campoBuscar}/valor/{valorBuscar}/departamento/{valorDepartamento}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Municipios\ParametrosMunicipioController@buscarMunicipios');
            // Buscar Municipios
            $api->get('search-municipio/{valorBuscar}/pais/{valorPais}/departamento/{valorDepartamento}', 'App\Http\Modulos\Parametros\Municipios\ParametrosMunicipioController@buscarMunicipio');
            // Busqueda
            $api->get('municipios/busqueda/{campoBuscar}/valor/{valorBuscar}/pais/{valorPais}/departamento/{valorDepartamento}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Municipios\ParametrosMunicipioController@busqueda');
            // Obtiene un registro seleccionado
            $api->post('municipios/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Municipios\ParametrosMunicipioController@consultaRegistroParametrica');
            // CRUD Municipios
            $api->resource('municipios', 'App\Http\Modulos\Parametros\Municipios\ParametrosMunicipioController', ['except' => [
                'buscarMunicipios',
                'buscarMunicipio',
                'cambiarEstado',
                'getListaMunicipios',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Tipos de Documentos
            // Listado paginado de Tipos de Documentos
            $api->get('lista-tipos-documentos','App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumentoController@getListaTiposDocumentos');
            // Lista Paginada de tipos documentos con los registros que aplican para Documento Soporte
            $api->get('lista-tipos-documentos-ds', 'App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumentoController@getListaTiposDocumentosDs');
            // Cambia de estado ACTIVO-INACTIVO
            $api->post('tipos-documentos/cambiar-estado','App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumentoController@cambiarEstado');
            // Búsqueda que aplica para Documentos Electrónicos
            $api->get('tipos-documentos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumentoController@busqueda');
            // Búsqueda que aplica para Documentos Soporte
            $api->get('tipos-documentos-ds/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumentoController@busquedaDs');
            // Obtiene un registro seleccionado
            $api->post('tipos-documentos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumentoController@consultaRegistroParametrica');
            // CRUD Tipos documento
            $api->resource('tipos-documentos', 'App\Http\Modulos\Parametros\TiposDocumentos\ParametrosTipoDocumentoController', [ 'except' => [
                'getListaTiposDocumentos',
                'getListaTiposDocumentosDs',
                'cambiarEstado',
                'busqueda',
                'busquedaDs'
                ]]
            );

            // Rutas Paramétrica Clasificación de Productos
            // Lista Paginada Clasificacion de Productos
            $api->get('lista-clasificacion-productos', 'App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProductoController@getListaClasificacionProductos');
            
            // Cambio de estado de uno o varios Clasificacion de Productos ACTIVO-INACTIVO
            $api->post('clasificacion-productos/cambiar-estado', 'App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProductoController@cambiarEstado');

            // Busqueda
            $api->get('clasificacion-productos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProductoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('clasificacion-productos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProductoController@consultaRegistroParametrica');

            // CRUD Clasificacion de Productos
            $api->resource('clasificacion-productos', 'App\Http\Modulos\Parametros\ClasificacionProductos\ParametrosClasificacionProductoController', ['except' => [
                'cambiarEstado',
                'getListaClasificacionProductos',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Codigos Descuentos
            // Lista Paginada Codigos Descuentos
            $api->get('lista-codigos-descuentos', 'App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuentoController@getListaCodigosDescuentos');
            
            // Cambio de estado de uno o varios Codigos Descuentos ACTIVO-INACTIVO
            $api->post('codigos-descuentos/cambiar-estado', 'App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuentoController@cambiarEstado');

            // Busqueda
            $api->get('codigos-descuentos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuentoController@busqueda');

            // Retorna coincidencias en diferentes colummas de acuerdo al parámetro recibido
            $api->get('search-codigos-descuento/{valorBuscar}', 'App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuentoController@buscarCodigoDescuento');

            // Obtiene un registro seleccionado
            $api->post('codigos-descuentos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuentoController@consultaRegistroParametrica');

            // CRUD Codigos Descuentos
            $api->resource('codigos-descuentos', 'App\Http\Modulos\Parametros\CodigosDescuentos\ParametrosCodigoDescuentoController', ['except' => [
                'cambiarEstado',
                'getListaCodigosDescuentos',
                'busqueda',
                'buscarCodigoDescuento'
                ]]
            );


            // Rutas Paramétrica Codigos postales
            // Lista Paginada Codigos postales
            $api->get('lista-codigos-postales', 'App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostalController@getListaCodigosPostales');
            
            // Cambio de estado de uno o varios Codigos postales ACTIVO-INACTIVO
            $api->post('codigos-postales/cambiar-estado', 'App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostalController@cambiarEstado');

            // Busca un codigo postal dado un criterio
            $api->get('codigo-postal/buscar-ng-select/{valorBuscar}', 'App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostalController@buscarCodigoPostal');

            // Busqueda
            $api->get('codigos-postales/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostalController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('codigos-postales/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostalController@consultaRegistroParametrica');

            // CRUD Codigos postales
            $api->resource('codigos-postales', 'App\Http\Modulos\Parametros\CodigosPostales\ParametrosCodigoPostalController', ['except' => [
                'cambiarEstado',
                'getListaCodigosPostales',
                'busqueda'
                ]]
            );


            // Rutas Paramétrica condiciones entrega
            // Lista Paginada condiciones entrega
            $api->get('lista-condiciones-entrega', 'App\Http\Modulos\Parametros\CondicionesEntrega\ParametrosCondicionEntregaController@getListacondicionesentrega');
            
            // Cambio de estado de uno o varios condiciones entrega ACTIVO-INACTIVO
            $api->post('condiciones-entrega/cambiar-estado', 'App\Http\Modulos\Parametros\CondicionesEntrega\ParametrosCondicionEntregaController@cambiarEstado');

            // Busqueda
            $api->get('condiciones-entrega/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\CondicionesEntrega\ParametrosCondicionEntregaController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('condiciones-entrega/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\CondicionesEntrega\ParametrosCondicionEntregaController@consultaRegistroParametrica');

            // CRUD condiciones entrega
            $api->resource('condiciones-entrega', 'App\Http\Modulos\Parametros\CondicionesEntrega\ParametrosCondicionEntregaController', ['except' => [
                'cambiarEstado',
                'getListacondicionesentrega',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Colombia Compra Eficiente
            // Lista Paginada Colombia Compra Eficiente
            $api->get('lista-colombia-compra-eficiente', 'App\Http\Modulos\Parametros\ColombiaCompraEficiente\ParametrosColombiaCompraEficienteController@getListacolombiacompraeficiente');
            
            // Cambio de estado de uno o varios Colombia Compra Eficiente ACTIVO-INACTIVO
            $api->post('colombia-compra-eficiente/cambiar-estado', 'App\Http\Modulos\Parametros\ColombiaCompraEficiente\ParametrosColombiaCompraEficienteController@cambiarEstado');

            // Busqueda
            $api->get('colombia-compra-eficiente/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\ColombiaCompraEficiente\ParametrosColombiaCompraEficienteController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('colombia-compra-eficiente/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\ColombiaCompraEficiente\ParametrosColombiaCompraEficienteController@consultaRegistroParametrica');

            // CRUD Colombia Compra Eficiente
            $api->resource('colombia-compra-eficiente', 'App\Http\Modulos\Parametros\ColombiaCompraEficiente\ParametrosColombiaCompraEficienteController', ['except' => [
                'cambiarEstado',
                'getListacolombiacompraeficiente',
                'busqueda'
                ]]
            );


            // Rutas Paramétrica formas pago
            // Lista Paginada formas pago
            $api->get('lista-formas-pago', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController@getListaformaspago');

            // Cambio de estado de uno o varios formas pago ACTIVO-INACTIVO
            $api->post('formas-pago/cambiar-estado', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController@cambiarEstado');

            // Búsqueda
            $api->get('formas-pago/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('formas-pago/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController@consultaRegistroParametrica');

            // Lista Paginada de formas de pago con los registros que aplican para Documento Soporte
            $api->get('lista-formas-pago-ds', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController@getListaFormasPagoDs');

            // Búsqueda en formas de Pagos con registros que aplican para Documento Soporte
            $api->get('formas-pago-ds/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController@busquedaDs');

            // CRUD formas pago
            $api->resource('formas-pago', 'App\Http\Modulos\Parametros\FormasPago\ParametrosFormaPagoController', ['except' => [
                'cambiarEstado',
                'getListaformaspago',
                'getListaFormasPagoDs',
                'busqueda',
                'busquedaDs'
                ]]
            );

            // Rutas Paramétrica medios Pago
            // Lista Paginada medios Pago
            $api->get('lista-medios-pago', 'App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPagoController@getListamediospago');
            
            // Cambio de estado de uno o varios medios Pago ACTIVO-INACTIVO
            $api->post('medios-pago/cambiar-estado', 'App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPagoController@cambiarEstado');

            // Busqueda
            $api->get('medios-pago/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPagoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('medios-pago/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPagoController@consultaRegistroParametrica');

            // CRUD medios Pago
            $api->resource('medios-pago', 'App\Http\Modulos\Parametros\MediosPago\ParametrosMediosPagoController', ['except' => [
                'cambiarEstado',
                'getListamediospago',
                'busqueda'
                ]]
            );


            // Rutas Paramétrica monedas
            // Lista Paginada monedas
            $api->get('lista-monedas', 'App\Http\Modulos\Parametros\Monedas\ParametrosMonedaController@getListamonedas');
            
            // Cambio de estado de uno o varios monedas ACTIVO-INACTIVO
            $api->post('monedas/cambiar-estado', 'App\Http\Modulos\Parametros\Monedas\ParametrosMonedaController@cambiarEstado');

            // Busqueda
            $api->get('monedas/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Monedas\ParametrosMonedaController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('monedas/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Monedas\ParametrosMonedaController@consultaRegistroParametrica');

            // CRUD monedas
            $api->resource('monedas', 'App\Http\Modulos\Parametros\Monedas\ParametrosMonedaController', ['except' => [
                'cambiarEstado',
                'getListamonedas',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica partidas arancelaria
            // Lista Paginada partidas arancelaria
            $api->get('lista-partidas-arancelarias', 'App\Http\Modulos\Parametros\PartidasArancelarias\ParametrosPartidaArancelariaController@getListapartidasarancelarias');
            
            // Cambio de estado de uno o varios partidas-arancelarias ACTIVO-INACTIVO
            $api->post('partidas-arancelarias/cambiar-estado', 'App\Http\Modulos\Parametros\PartidasArancelarias\ParametrosPartidaArancelariaController@cambiarEstado');

            // Busqueda
            $api->get('partidas-arancelarias/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\PartidasArancelarias\ParametrosPartidaArancelariaController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('partidas-arancelarias/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\PartidasArancelarias\ParametrosPartidaArancelariaController@consultaRegistroParametrica');

            // CRUD partidas arancelaria
            $api->resource('partidas-arancelarias', 'App\Http\Modulos\Parametros\PartidasArancelarias\ParametrosPartidaArancelariaController', ['except' => [
                'cambiarEstado',
                'getListapartidasarancelarias',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Precios Referencia
            // Lista Paginada Precios Referencia
            $api->get('lista-precios-referencia', 'App\Http\Modulos\Parametros\PreciosReferencia\ParametrosPrecioReferenciaController@getListapreciosreferencia');
            
            // Cambio de estado de uno o varios precios-referencia ACTIVO-INACTIVO
            $api->post('precios-referencia/cambiar-estado', 'App\Http\Modulos\Parametros\PreciosReferencia\ParametrosPrecioReferenciaController@cambiarEstado');

            // Busqueda
            $api->get('precios-referencia/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\PreciosReferencia\ParametrosPrecioReferenciaController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('precios-referencia/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\PreciosReferencia\ParametrosPrecioReferenciaController@consultaRegistroParametrica');

            // CRUD Precios Referencia
            $api->resource('precios-referencia', 'App\Http\Modulos\Parametros\PreciosReferencia\ParametrosPrecioReferenciaController', ['except' => [
                'cambiarEstado',
                'getListapreciosreferencia',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Regimen Fiscal
            // Lista Paginada Regimen Fiscal
            $api->get('lista-regimen-fiscal', 'App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscalController@getListaregimenfiscal');
            
            // Cambio de estado de uno o varios regimen-fiscal ACTIVO-INACTIVO
            $api->post('regimen-fiscal/cambiar-estado', 'App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscalController@cambiarEstado');

            // Busqueda
            $api->get('regimen-fiscal/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscalController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('regimen-fiscal/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscalController@consultaRegistroParametrica');

            // CRUD Regimen Fiscal
            $api->resource('regimen-fiscal', 'App\Http\Modulos\Parametros\RegimenFiscal\ParametrosRegimenFiscalController', ['except' => [
                'cambiarEstado',
                'getListaregimenfiscal',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica responsabilidades fiscales
            // Lista Paginada responsabilidades fiscales
            $api->get('lista-responsabilidades-fiscales', 'App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscalController@getListaresponsabilidadesfiscales');

            // Buscar responsabilidades fiscales
            $api->get('search-responsabilidades-fiscales/{valorBuscar}', 'App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscalController@buscarResponsabilidadesFiscal');

            // Cambio de estado de uno o varios responsabilidades-fiscales ACTIVO-INACTIVO
            $api->post('responsabilidades-fiscales/cambiar-estado', 'App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscalController@cambiarEstado');

            // Busqueda
            $api->get('responsabilidades-fiscales/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscalController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('responsabilidades-fiscales/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscalController@consultaRegistroParametrica');

            // Lista Paginada de responsabilidades fiscales con los registros que aplican para Documento Soporte
            $api->get('lista-responsabilidades-fiscales-ds', 'App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscalController@getListaresponsabilidadesfiscalesDs');

            // Búsqueda en responsabilidades fiscales con registros que aplican para Documento Soporte
            $api->get('responsabilidades-fiscales-ds/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscalController@busquedaDs');

            // CRUD responsabilidades fiscales
            $api->resource('responsabilidades-fiscales', 'App\Http\Modulos\Parametros\ResponsabilidadesFiscales\ParametrosResponsabilidadFiscalController', ['except' => [
                'cambiarEstado',
                'getListaresponsabilidadesfiscales',
                'getListaresponsabilidadesfiscalesDs',
                'busqueda',
                'busquedaDs'
                ]]
            );

            // Rutas Paramétrica tipo organizacion juridica
            // Lista Paginada tipo organizacion juridica
            $api->get('lista-tipo-organizacion-juridica', 'App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridicaController@getListatipoorganizacionjuridica');
            
            // Cambio de estado de uno o varios tipo-organizacion-juridica ACTIVO-INACTIVO
            $api->post('tipo-organizacion-juridica/cambiar-estado', 'App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridicaController@cambiarEstado');

            // Busqueda
            $api->get('tipo-organizacion-juridica/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridicaController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('tipo-organizacion-juridica/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridicaController@consultaRegistroParametrica');

            // CRUD tipo organizacion juridica
            $api->resource('tipo-organizacion-juridica', 'App\Http\Modulos\Parametros\TipoOrganizacionJuridica\ParametrosTipoOrganizacionJuridicaController', ['except' => [
                'cambiarEstado',
                'getListatipoorganizacionjuridica',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica de procedencia vendedor
            // Lista Paginadade procedencia vendedor
            $api->get('lista-procedencia-vendedor', 'App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedorController@getListaProcedenciaVendedor');

            // Cambio de estado de uno o varios procedencia-vendedor ACTIVO-INACTIVO
            $api->post('procedencia-vendedor/cambiar-estado', 'App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedorController@cambiarEstado');

            // Busqueda
            $api->get('procedencia-vendedor/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedorController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('procedencia-vendedor/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedorController@consultaRegistroParametrica');

            // CRUD de procedencia vendedor
            $api->resource('procedencia-vendedor', 'App\Http\Modulos\Parametros\ProcedenciaVendedor\ParametrosProcedenciaVendedorController', ['except' => [
                'cambiarEstado',
                'getListaProcedenciaVendedor',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica tipos documentos electrónicos
            // Lista Paginada tipos documentos electrónicos
            $api->get('lista-tipos-documentos-electronicos', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController@getListatiposdocumentoselectronicos');

            // Cambio de estado de uno o varios tipos-documentos-electronicos ACTIVO-INACTIVO
            $api->post('tipos-documentos-electronicos/cambiar-estado', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController@cambiarEstado');

            // Búsqueda
            $api->get('tipos-documentos-electronicos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('tipos-documentos-electronicos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController@consultaRegistroParametrica');

            // Lista Paginada de tipos documentos electrónicos con los registros que aplican para Documento Soporte
            $api->get('lista-tipos-documentos-electronicos-ds', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController@getListaTiposDocumentosElectronicosDs');

            // Búsqueda en tipos documentos electrónicos con los registros que aplican para Documento Soporte
            $api->get('tipos-documentos-electronicos-ds/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController@busquedaDs');

            // CRUD tipos documentos electrónicos
            $api->resource('tipos-documentos-electronicos', 'App\Http\Modulos\Parametros\TiposDocumentosElectronicos\ParametrosTipoDocumentoElectronicoController', ['except' => [
                'cambiarEstado',
                'getListatiposdocumentoselectronicos',
                'getListaTiposDocumentosElectronicosDs',
                'busqueda',
                'busquedaDs'
                ]]
            );


            // Rutas Paramétrica tipos operación
            // Lista Paginada tipos operación
            $api->get('lista-tipos-operacion', 'App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacionController@getListatiposoperacion');
            
            // Cambio de estado de uno o varios tipos-operacion ACTIVO-INACTIVO
            $api->post('tipos-operacion/cambiar-estado', 'App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacionController@cambiarEstado');

            // Busqueda
            $api->get('tipos-operacion/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacionController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('tipos-operacion/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacionController@consultaRegistroParametrica');

            // Lista Paginada de tipos de operación con los registros que aplican para Documento Soporte
            $api->get('lista-tipos-operacion-ds ', 'App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacionController@getListaTiposOperacionDs');

            // Búsqueda en tipos de operación con registros que aplican para Documento Soporte
            $api->get('tipos-operacion-ds/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacionController@busquedaDs');

            // CRUD tipos operación
            $api->resource('tipos-operacion', 'App\Http\Modulos\Parametros\TiposOperacion\ParametrosTipoOperacionController', ['except' => [
                'getListatiposoperacion',
                'cambiarEstado',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Tributos
            // Lista Paginada Tributos
            $api->get('lista-tributos', 'App\Http\Modulos\Parametros\Tributos\ParametrosTributoController@getListaTributos');
                
            // Buscar tributos
            $api->get('search-tributos/{valorBuscar}', 'App\Http\Modulos\Parametros\Tributos\ParametrosTributoController@buscarTributos');

            // Cambio de estado de uno o varios tributos ACTIVO-INACTIVO
            $api->post('tributos/cambiar-estado', 'App\Http\Modulos\Parametros\Tributos\ParametrosTributoController@cambiarEstado');

            // Busqueda
            $api->get('tributos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Tributos\ParametrosTributoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('tributos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Tributos\ParametrosTributoController@consultaRegistroParametrica');

            // Lista Paginada de responsabilidades fiscales con los registros que aplican para Documento Soporte
            $api->get('lista-tributos-ds', 'App\Http\Modulos\Parametros\Tributos\ParametrosTributoController@getListaTributosDs');

            // Búsqueda en responsabilidades fiscales con registros que aplican para Documento Soporte
            $api->get('tributos-ds/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Tributos\ParametrosTributoController@busquedaDs');

            // CRUD Tributos
            $api->resource('tributos', 'App\Http\Modulos\Parametros\Tributos\ParametrosTributoController', ['except' => [
                'cambiarEstado',
                'getListaTributos',
                'getListaTributosDs',
                'buscarTributos',
                'busqueda',
                'busquedaDs'
                ]]
            );

            // Rutas Paramétrica tarifas impuesto
            // Lista Paginada tarifas impuesto
            $api->get('lista-tarifas-impuesto', 'App\Http\Modulos\Parametros\TarifasImpuesto\ParametrosTarifaImpuestoController@getListatarifasimpuesto');
            
            // Cambio de estado de uno o varios tarifas impuesto ACTIVO-INACTIVO
            $api->post('tarifas-impuesto/cambiar-estado', 'App\Http\Modulos\Parametros\TarifasImpuesto\ParametrosTarifaImpuestoController@cambiarEstado');

            // Busqueda
            $api->get('tarifas-impuesto/busqueda/{campoBuscar}/valor/{valorBuscar}/tributo/{valorTributo}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\TarifasImpuesto\ParametrosTarifaImpuestoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('tarifas-impuesto/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\TarifasImpuesto\ParametrosTarifaImpuestoController@consultaRegistroParametrica');

            // CRUD tarifas impuesto
            $api->resource('tarifas-impuesto', 'App\Http\Modulos\Parametros\TarifasImpuesto\ParametrosTarifaImpuestoController', ['except' => [
                'cambiarEstado',
                'getListatarifasimpuesto',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica unidades
            // Lista Paginada unidades
            $api->get('lista-unidades', 'App\Http\Modulos\Parametros\Unidades\ParametrosUnidadController@getListaunidades');
            
            // Cambio de estado de uno o varios unidades ACTIVO-INACTIVO
            $api->post('unidades/cambiar-estado', 'App\Http\Modulos\Parametros\Unidades\ParametrosUnidadController@cambiarEstado');

            // Busqueda
            $api->get('unidades/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Unidades\ParametrosUnidadController@busqueda');

            // Retorna coincidencias en diferentes colummas de acuerdo al parámetro recibido
            $api->get('search-unidades/{valorBuscar}', 'App\Http\Modulos\Parametros\Unidades\ParametrosUnidadController@buscarUnidad');

            // Obtiene un registro seleccionado
            $api->post('unidades/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Unidades\ParametrosUnidadController@consultaRegistroParametrica');

            // CRUD unidades
            $api->resource('unidades', 'App\Http\Modulos\Parametros\Unidades\ParametrosUnidadController', ['except' => [
                'cambiarEstado',
                'getListaunidades',
                'busqueda',
                'buscarUnidad'
                ]]
            );

            // Rutas Paramétrica Referencia Otros Documentos
            // Lista Paginada Referencia Otros Documentos
            $api->get('lista-referencia-otros-documentos', 'App\Http\Modulos\Parametros\ReferenciaOtrosDocumentos\ParametrosReferenciaOtroDocumentoController@getListaReferenciaOtrosDocumentos');
            
            // Cambio de estado de uno o varios Referencia Otros Documentos ACTIVO-INACTIVO
            $api->post('referencia-otros-documentos/cambiar-estado', 'App\Http\Modulos\Parametros\ReferenciaOtrosDocumentos\ParametrosReferenciaOtroDocumentoController@cambiarEstado');

            // Busqueda
            $api->get('referencia-otros-documentos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\ReferenciaOtrosDocumentos\ParametrosReferenciaOtroDocumentoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('referencia-otros-documentos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\ReferenciaOtrosDocumentos\ParametrosReferenciaOtroDocumentoController@consultaRegistroParametrica');

            // CRUD Referencia Otros Documentos
            $api->resource('referencia-otros-documentos', 'App\Http\Modulos\Parametros\ReferenciaOtrosDocumentos\ParametrosReferenciaOtroDocumentoController', ['except' => [
                'cambiarEstado',
                'getListaReferenciaOtrosDocumentos',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Concepto Correccion
            // Lista Paginada Concepto Correccion
            $api->get('lista-concepto-correccion', 'App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccionController@getListaconceptoscorreccion');
            
            // Cambio de estado de uno o varios Concepto Correccion ACTIVO-INACTIVO
            $api->post('concepto-correccion/cambiar-estado', 'App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccionController@cambiarEstado');

            // Busqueda
            $api->get('concepto-correccion/busqueda/{campoBuscar}/valor/{valorBuscar}/tipo/{valorTipo}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccionController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('concepto-correccion/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccionController@consultaRegistroParametrica');

            // Ng Selected para buscar un concepto de corrección
            $api->get('concepto-correccion/buscar-ng-select/{campoBuscar}/tipo/{tipo}/ofe/{ofe_id}', 'App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccionController@buscarConceptoCorreccionNgSelect');

            // CRUD Concepto Correccion
            $api->resource('concepto-correccion', 'App\Http\Modulos\Parametros\ConceptosCorreccion\ParametrosConceptoCorreccionController', ['except' => [
                'cambiarEstado',
                'getListaconceptoscorreccion',
                'busqueda',
                'buscarConceptoCorreccionNgSelect'
                ]]
            );

            // Rutas Paramétrica Concepto Rechazo
            // Lista Paginada Concepto Rechazo
            $api->get('lista-concepto-rechazo', 'App\Http\Modulos\Parametros\ConceptosRechazo\ParametrosConceptoRechazoController@getListaConceptosRechazo');
            
            // Cambio de estado de uno o varios Concepto Rechazo ACTIVO-INACTIVO
            $api->post('concepto-rechazo/cambiar-estado', 'App\Http\Modulos\Parametros\ConceptosRechazo\ParametrosConceptoRechazoController@cambiarEstado');

            // Busqueda
            $api->get('concepto-rechazo/busqueda/{campoBuscar}/valor/{valorBuscar}/tipo/{valorTipo}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\ConceptosRechazo\ParametrosConceptoRechazoController@busqueda');

            // Lista filtrada para campo de tipo select
            $api->get('concepto-rechazo/listar-select','App\Http\Modulos\Parametros\ConceptosRechazo\ParametrosConceptoRechazoController@listarSelect');

            // Obtiene un registro seleccionado
            $api->post('concepto-rechazo/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\ConceptosRechazo\ParametrosConceptoRechazoController@consultaRegistroParametrica');

            // CRUD Concepto Rechazo
            $api->resource('concepto-rechazo', 'App\Http\Modulos\Parametros\ConceptosRechazo\ParametrosConceptoRechazoController', ['except' => [
                'cambiarEstado',
                'getListaconceptosrechazo',
                'busqueda',
                'listarSelect'
                ]]
            );

            // Rutas Paramétrica ambiente destino documentos
            // Lista Paginada ambiente destino documentos
            $api->get('lista-ambiente-destino-documentos', 'App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumentoController@getListaAmbienteDestinoDocumentos');
            
            // Cambio de estado de uno o varios ambiente destino documentos ACTIVO-INACTIVO
            $api->post('ambiente-destino-documentos/cambiar-estado', 'App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumentoController@cambiarEstado');

            // Busqueda
            $api->get('ambiente-destino-documentos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumentoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('ambiente-destino-documentos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumentoController@consultaRegistroParametrica');

            // CRUD ambiente destino documentos
            $api->resource('ambiente-destino-documentos', 'App\Http\Modulos\Parametros\AmbienteDestinoDocumentos\ParametrosAmbienteDestinoDocumentoController', ['except' => [
                'cambiarEstado',
                'getListaambientedestinodocumentos',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Mandatos
            // Lista Paginada Mandatos
            $api->get('lista-mandatos', 'App\Http\Modulos\Parametros\Mandatos\ParametrosMandatoController@getListaMandatos');
            
            // Cambio de estado de uno o varios Mandatos ACTIVO-INACTIVO
            $api->post('mandatos/cambiar-estado', 'App\Http\Modulos\Parametros\Mandatos\ParametrosMandatoController@cambiarEstado');

            // Busqueda
            $api->get('mandatos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\Mandatos\ParametrosMandatoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('mandatos/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\Mandatos\ParametrosMandatoController@consultaRegistroParametrica');

            // CRUD Mandatos
            $api->resource('mandatos', 'App\Http\Modulos\Parametros\Mandatos\ParametrosMandatoController', ['except' => [
                'cambiarEstado',
                'getListaMandatos',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Documentos Identificacion (Sector Salud)
            // Lista Paginada Documentos Identificacion (Sector Salud)
            $api->get('lista-salud-documentos-identificacion', 'App\Http\Modulos\Parametros\SectorSalud\DocumentosIdentificacion\ParametrosSaludDocumentoIdentificacionController@getListaDocumentosIdentificacion');
            
            // Cambio de estado de uno o varios Documentos Identificacion (Sector Salud) ACTIVO-INACTIVO
            $api->post('salud-documentos-identificacion/cambiar-estado', 'App\Http\Modulos\Parametros\SectorSalud\DocumentosIdentificacion\ParametrosSaludDocumentoIdentificacionController@cambiarEstado');

            // Busqueda
            $api->get('salud-documentos-identificacion/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorSalud\DocumentosIdentificacion\ParametrosSaludDocumentoIdentificacionController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('salud-documentos-identificacion/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\SectorSalud\DocumentosIdentificacion\ParametrosSaludDocumentoIdentificacionController@consultaRegistroParametrica');

            // CRUD Documentos Identificacion (Sector Salud)
            $api->resource('salud-documentos-identificacion', 'App\Http\Modulos\Parametros\SectorSalud\DocumentosIdentificacion\ParametrosSaludDocumentoIdentificacionController', ['except' => [
                'cambiarEstado',
                'getListaDocumentosIdentificacion',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Tipos Usuario (Sector Salud)
            // Lista Paginada Tipos Usuario (Sector Salud)
            $api->get('lista-salud-tipos-usuario', 'App\Http\Modulos\Parametros\SectorSalud\TipoUsuario\ParametrosSaludTipoUsuarioController@getListaTiposUsuario');
            
            // Cambio de estado de uno o varios Tipos Usuario (Sector Salud) ACTIVO-INACTIVO
            $api->post('salud-tipos-usuario/cambiar-estado', 'App\Http\Modulos\Parametros\SectorSalud\TipoUsuario\ParametrosSaludTipoUsuarioController@cambiarEstado');

            // Busqueda
            $api->get('salud-tipos-usuario/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorSalud\TipoUsuario\ParametrosSaludTipoUsuarioController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('salud-tipos-usuario/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\SectorSalud\TipoUsuario\ParametrosSaludTipoUsuarioController@consultaRegistroParametrica');

            // CRUD Tipos Usuario (Sector Salud)
            $api->resource('salud-tipos-usuario', 'App\Http\Modulos\Parametros\SectorSalud\TipoUsuario\ParametrosSaludTipoUsuarioController', ['except' => [
                'cambiarEstado',
                'getListaTiposUsuario',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Modalidades (Sector Salud)
            // Lista Paginada Modalidades (Sector Salud)
            $api->get('lista-salud-modalidades', 'App\Http\Modulos\Parametros\SectorSalud\Modalidades\ParametrosSaludModalidadController@getListaModalidades');
            
            // Cambio de estado de uno o varios Modalidades (Sector Salud) ACTIVO-INACTIVO
            $api->post('salud-modalidades/cambiar-estado', 'App\Http\Modulos\Parametros\SectorSalud\Modalidades\ParametrosSaludModalidadController@cambiarEstado');

            // Busqueda
            $api->get('salud-modalidades/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorSalud\Modalidades\ParametrosSaludModalidadController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('salud-modalidades/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\SectorSalud\Modalidades\ParametrosSaludModalidadController@consultaRegistroParametrica');

            // CRUD Tipos Usuario (Sector Salud)
            $api->resource('salud-modalidades', 'App\Http\Modulos\Parametros\SectorSalud\Modalidades\ParametrosSaludModalidadController', ['except' => [
                'cambiarEstado',
                'getListaModalidades',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Cobertura (Sector Salud)
            // Lista Paginada Cobertura (Sector Salud)
            $api->get('lista-salud-cobertura', 'App\Http\Modulos\Parametros\SectorSalud\Cobertura\ParametrosSaludCoberturaController@getListaCobertura');
            
            // Cambio de estado de uno o varios Coberturas (Sector Salud) ACTIVO-INACTIVO
            $api->post('salud-cobertura/cambiar-estado', 'App\Http\Modulos\Parametros\SectorSalud\Cobertura\ParametrosSaludCoberturaController@cambiarEstado');

            // Busqueda
            $api->get('salud-cobertura/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorSalud\Cobertura\ParametrosSaludCoberturaController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('salud-cobertura/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\SectorSalud\Cobertura\ParametrosSaludCoberturaController@consultaRegistroParametrica');

            // CRUD Cobertura (Sector Salud)
            $api->resource('salud-cobertura', 'App\Http\Modulos\Parametros\SectorSalud\Cobertura\ParametrosSaludCoberturaController', ['except' => [
                'cambiarEstado',
                'getListaCobertura',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Documentos Referenciados (Sector Salud)
            // Lista Paginada Documentos Referenciados (Sector Salud)
            $api->get('lista-salud-documentos-referenciados', 'App\Http\Modulos\Parametros\SectorSalud\DocumentoReferenciado\ParametrosSaludDocumentoReferenciadoController@getListaDocumentosReferenciados');
            
            // Cambio de estado de uno o varios Documentos Referenciados (Sector Salud) ACTIVO-INACTIVO
            $api->post('salud-documentos-referenciados/cambiar-estado', 'App\Http\Modulos\Parametros\SectorSalud\DocumentoReferenciado\ParametrosSaludDocumentoReferenciadoController@cambiarEstado');

            // Busqueda
            $api->get('salud-documentos-referenciados/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorSalud\DocumentoReferenciado\ParametrosSaludDocumentoReferenciadoController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('salud-documentos-referenciados/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\SectorSalud\DocumentoReferenciado\ParametrosSaludDocumentoReferenciadoController@consultaRegistroParametrica');

            // CRUD Documentos Referenciados (Sector Salud)
            $api->resource('salud-documentos-referenciados', 'App\Http\Modulos\Parametros\SectorSalud\DocumentoReferenciado\ParametrosSaludDocumentoReferenciadoController', ['except' => [
                'cambiarEstado',
                'getListaDocumentosReferenciados',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Registros (Sector Transporte)
            // Lista Paginada Registros (Sector Transporte)
            $api->get('lista-transporte-registros', 'App\Http\Modulos\Parametros\SectorTransporte\Registro\ParametrosTransporteRegistroController@getListaRegistros');
            
            // Cambio de estado de uno o varios Registros (Sector Transporte) ACTIVO-INACTIVO
            $api->post('transporte-registros/cambiar-estado', 'App\Http\Modulos\Parametros\SectorTransporte\Registro\ParametrosTransporteRegistroController@cambiarEstado');

            // Busqueda
            $api->get('transporte-registros/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorTransporte\Registro\ParametrosTransporteRegistroController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('transporte-registros/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\SectorTransporte\Registro\ParametrosTransporteRegistroController@consultaRegistroParametrica');

            // CRUD Registros (Sector Transporte)
            $api->resource('transporte-registros', 'App\Http\Modulos\Parametros\SectorTransporte\Registro\ParametrosTransporteRegistroController', ['except' => [
                'cambiarEstado',
                'getListaRegistros',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Remesas (Sector Transporte)
            // Lista Paginada Remesas (Sector Transporte)
            $api->get('lista-transporte-remesas', 'App\Http\Modulos\Parametros\SectorTransporte\Remesa\ParametrosTransporteRemesaController@getListaRemesas');
            
            // Cambio de estado de uno o varios Remesas (Sector Transporte) ACTIVO-INACTIVO
            $api->post('transporte-remesas/cambiar-estado', 'App\Http\Modulos\Parametros\SectorTransporte\Remesa\ParametrosTransporteRemesaController@cambiarEstado');

            // Busqueda
            $api->get('transporte-remesas/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorTransporte\Remesa\ParametrosTransporteRemesaController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('transporte-remesas/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\SectorTransporte\Remesa\ParametrosTransporteRemesaController@consultaRegistroParametrica');

            // CRUD Remesas (Sector Transporte)
            $api->resource('transporte-remesas', 'App\Http\Modulos\Parametros\SectorTransporte\Remesa\ParametrosTransporteRemesaController', ['except' => [
                'cambiarEstado',
                'getListaRemesas',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Debida Diligenciada (Sector Cambiario)
            // Lista Paginada Debida Diligencia (Sector Cambiario)
            $api->get('lista-debida-diligencia', 'App\Http\Modulos\Parametros\SectorCambiario\DebidaDiligencia\ParametrosDebidaDiligenciaController@getListaParametrosDebidaDiligencia');
            
            // Cambio de estado de uno o varios Debida Diligencia (Sector Cambiario) ACTIVO-INACTIVO
            $api->post('debida-diligencia/cambiar-estado', 'App\Http\Modulos\Parametros\SectorCambiario\DebidaDiligencia\ParametrosDebidaDiligenciaController@cambiarEstado');

            // Busqueda
            $api->get('debida-diligencia/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorCambiario\DebidaDiligencia\ParametrosDebidaDiligenciaController@busqueda');

            // CRUD Debida Diligencia (Sector Cambiario)
            $api->resource('debida-diligencia', 'App\Http\Modulos\Parametros\SectorCambiario\DebidaDiligencia\ParametrosDebidaDiligenciaController', ['except' => [
                'cambiarEstado',
                'getListaParametrosDebidaDiligencia',
                'busqueda'
                ]]
            );

            // Rutas Paramétrica Mandato Profesional de Cambios (Sector Cambiario)
            // Lista Paginada Mandato Profesional de Cambios (Sector Cambiario)
            $api->get('lista-cambiario-mandatos-profesional', 'App\Http\Modulos\Parametros\SectorCambiario\MandatosProfesional\ParametrosMandatoProfesionalController@getListaMandatosProfesional');
            
            // Cambio de estado de uno o varios Mandatos Profesional de Cambios (Sector Cambiario) ACTIVO-INACTIVO
            $api->post('cambiario-mandatos-profesional/cambiar-estado', 'App\Http\Modulos\Parametros\SectorCambiario\MandatosProfesional\ParametrosMandatoProfesionalController@cambiarEstado');

            // Busqueda
            $api->get('cambiario-mandatos-profesional/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\SectorCambiario\MandatosProfesional\ParametrosMandatoProfesionalController@busqueda');

            // Obtiene un registro seleccionado
            $api->post('cambiario-mandatos-profesional/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\SectorCambiario\MandatosProfesional\ParametrosMandatoProfesionalController@consultaRegistroParametrica');

            // Rutas Paramétrica Forma de Generación y Transmisión
            // Lista Paginada Forma de Generación y Transmisión
            $api->get('lista-generacion-transmision', 'App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmisionController@getListaFormaGeneracionTransmision');

            // Cambio de estado de uno o varios Forma de Generación y Transmisión ACTIVO-INACTIVO
            $api->post('generacion-transmision/cambiar-estado', 'App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmisionController@cambiarEstado');

            // Busqueda
            $api->get('generacion-transmision/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmisionController@busqueda');

            // Retorna coincidencias en diferentes colummas de acuerdo al parámetro recibido
            $api->get('search-generacion-transmision/{valorBuscar}', 'App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmisionController@buscarFormaGeneracionTransmision');

            // Obtiene un registro seleccionado
            $api->post('generacion-transmision/consulta-registro-parametrica', 'App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmisionController@consultaRegistroParametrica');

            // CRUD Forma de Generación y Transmisión
            $api->resource('generacion-transmision', 'App\Http\Modulos\Parametros\FormaGeneracionTransmision\ParametrosFormaGeneracionTransmisionController', ['except' => [
                'cambiarEstado',
                'getListaFormaGeneracionTransmision',
                'busqueda',
                'buscarFormaGeneracionTransmision'
                ]]
            );
        }
    );

    // Configuración
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'configuracion'
        ],
        function ($api){

            // Generar excel Software Proveedor Tecnológico
            $api->post('spt/generar-interface-spt', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@generarInterfaceSpt');

            // Cargar Software Proveedor Tecnológico
            $api->post('spt/cargar-spt', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@cargarSpt');

            // Listado de errores en cargues masivos
            $api->post('spt/lista-errores','App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@getListaErroresSpt');

            // Descargar Errores Proveedores
            $api->post('spt/lista-errores/excel', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@descargarListaErroresSpt');

            // Lista para Software Proveedor Tecnológico
            $api->get('lista-spt', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@getListaSpt');
            // Cambiar estado ACTIVO-INACTIVO
            $api->post('spt/cambiar-estado', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@cambiarEstado');
            // Ventana Modal Software Proveedor Tecnológico
            $api->get('spt/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@buscarSoftware');
            // Ng Selected para buscar un SPT
            $api->get('spt/buscar-ng-select/{campoBuscar}/aplicaPara/{aplica_para}', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@buscarSptNgSelect');
            // Búsqueda
            $api->get('spt/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@busqueda');
            // Lista Paginada Software Proveedor Tecnológico de registros que aplican para Documento Soporte
            $api->get('lista-spt-ds', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@getListaSptDs');
            // Búsqueda en Software Proveedor Tecnológico con los registros que aplican para Documento Soporte
            $api->get('spt-ds/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController@busquedaDs');
            // CRUD Software Proveedor Tecnológico
            $api->resource('spt', 'App\Http\Modulos\Configuracion\SoftwareProveedoresTecnologicos\ConfiguracionSoftwareProveedorTecnologicoController', ['except' => [
                'buscarSoftware',
                'cambiarEstado',
                'getListaSpt',
                'getListaSptDs',
                'busqueda',
                'busquedaDs'
                ]]
            );

            // Generar excel oferentes
            $api->post('ofe/generar-interface-ofe', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@generarInterfaceOfe');
            // Cargar Oferentes
            $api->post('ofe/cargar-ofe', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@cargarOferentes');
            //Listado para OFE
            $api->get('lista-ofe','App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@getListaOfe');
            // Cambiar estado ACTIVO-INACTIVO
            $api->post('ofe/cambiar-estado', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@cambiarEstado');
            // Ng Selected para buscar un OFE
            $api->get('ofe/buscar-ng-select/{campoBuscar}', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@buscarOfeNgSelect');
            // Lista filtrada para campo de tipo select
            $api->get('listar-select','App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@listarSelectOfe');
            // Procesa información relacionada con la representación gráfica estándar de un OFE
            $api->post('ofe/actualizar-representacion-grafica-estandar', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@actualizarRepresentacionGraficaEstandar');
            // Procesa información relacionada con la configuración de documento electrónico de un OFE
            $api->post('ofe/configuracion-documento-electronico', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@configuracionDocumentoElectronico');
            // Procesa información relacionada con la configuración de documento soporte de un OFE
            $api->post('ofe/configuracion-documento-soporte', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@configuracionDocumentoSoporte');
            // Procesa información relacionada con los datos de documentos manuales
            $api->post('ofe/datos-facturacion-web', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@datosFacturacionWeb');
            // Busqueda
            $api->get('ofe/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@busqueda');
            // Crear Adquirente Consumidor Final
            $api->post('ofe/crear-adquirente-consumidor-final', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@crearAdquirenteConsumidorFinal');
            // Lista las resoluciones de facturación de un OFE
            $api->post('ofe/lista-resoluciones-facturacion', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@listaResolucionesFacturacion');
            // Procesa información relacionada con la configuración de los servicios de un OFE
            $api->post('ofe/configuracion-servicios', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@configuracionServicios');
            // Listado de errores en cargues masivos del oferente
            $api->post('ofe/lista-errores','App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@getListaErroresOfe');
            // Descargar Errores del oferente
            $api->post('ofe/lista-errores/excel', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController@descargarListaErroresOfe');

            // CRUD OFE
            $api->resource('ofe', 'App\Http\Modulos\Configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamenteController', ['except' => [
                'generarInterfaceOfe',
                'cargarOferentes',
                'getListaOfe',
                'buscarOfe',
                'cambiarEstado',
                'buscarOfeNgSelect',
                'listarSelectOfe',
                'actualizarRepresentacionGraficaEstandar',
                'configuracionDocumentoElectronico',
                'configuracionDocumentoSoporte',
                'datosFacturacionWeb',
                'busqueda',
                'crearAdquirenteConsumidorFinal',
                'listaResolucionesFacturacion',
                'configuracionServicios',
                'getListaErroresOfe',
                'descargarListaErroresOfe'
                ]]
            );

            // Descarga la interface para cargar las resoluciones de facturación
            $api->post('resoluciones-facturacion/generar-interface-resoluciones-facturacion', 'App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@generarInterfaceResolucionFacturacion');
            // Listado de errores en cargues masivos
            $api->post('resoluciones-facturacion/lista-errores','App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@getListaErroresResolucionFacturacion');
            // Descargar Errores Proveedores
            $api->post('resoluciones-facturacion/lista-errores/excel', 'App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@descargarListaErroresResolucionFacturacion');
            // Realiza la carga de las resoluciones de facturación
            $api->post('resoluciones-facturacion/cargar-resoluciones-facturacion', 'App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@cargarResolucionFacturacion');
            //Listado para Resoluciones de facturación
            $api->get('lista-resoluciones-facturacion','App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@getListaResolucionesFacturacion');
            // Cambiar estado ACTIVO-INACTIVO
            $api->post('resoluciones-facturacion/cambiar-estado', 'App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@cambiarEstado');
            // Busqueda
            $api->get('resoluciones-facturacion/busqueda/{campoBuscar}/valor/{valorBuscar}/prefijo/{valorPrefijo}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@busqueda');
            // Listado para el control de resoluciones vencidas
            $api->get('resoluciones-vencidas', 'App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@resolucionesVencidas');
            // Descarga un Excel con el listado de las resoluciones consultadas en la DIAN
            $api->post('resoluciones-facturacion/descargar-excel-consulta-dian', 'App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController@descargarExcelConsultaDian');

            // CRUD resoluciones de facturación
            $api->resource('resoluciones-facturacion', 'App\Http\Modulos\Configuracion\ResolucionesFacturacion\ConfiguracionResolucionesFacturacionController', ['except' => [
                'generarInterfaceResolucionFacturacion', 
                'getListaErroresResolucionFacturacion',
                'descargarListaErroresResolucionFacturacion',
                'cargarResolucionFacturacion',
                'getListaResolucionesFacturacion',
                'cambiarEstado',
                'busqueda',
                'resolucionesVencidas',
                'descargarExcelConsultaDian'
                ]]
            );

            // Crear/Editar Adquirentes DI
            $api->post('adquirentes/di-gestion','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@createFromDI');
            // Listado de Adquirentes
            $api->get('lista-adquirentes','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getListaAdquirentes');
            //Setea el tipo de adquirente
            $api->post('adquirentes/editar-tipo-adquirente','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@editarTipoAdquirente');
            // Cambiar estado de uno o varios Adquirentes ACTIVO-INACTIVO
            $api->post('adquirentes/cambiar-estado','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@cambiarEstado');
            //Se obtiene el tipo del adquirente dado el parametro identificacion
            $api->get('check-adq-identificacion/{ofe_identificacion}','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getTipoAdquirente');
            // Busca coincidencias de adquirentes para un OFE
            $api->get('adquirentes/search-adquirentes','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@searchAdquirentes');
            // Busca uno o varios adquirentes dependiendo de la identificación del Ofe (no obligatorio)
            $api->get('adquirentes/buscar-adquirente-ofe/{adq_identificacion}/ofe/{ofe_identificacion?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@buscarAdquirenteOfe');
            // Generar excel adquirentes
            $api->post('adquirentes/generar-interface-adquirentes', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@generarInterfaceAdquirentes');
            // Cargar Adquirentes
            $api->post('adquirentes/cargar-adquirentes', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@cargarAdquirentes');
            // Listado de errores en cargues masivos
            $api->post('adquirentes/lista-errores','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getListaErroresAdquirentes');
            // Descargar Errores Adquirentes
            $api->post('adquirentes/lista-errores/excel', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@descargarListaErroresAdquirentes');
            // Retorna un Adquirente
            $api->get('adquirentes/{ofe_identificacion}/{adq_identificacion}/{adq_id_personalizado?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@showCompuesto');
            // Actualiza un Adquirente
            $api->put('adquirentes/{ofe_identificacion}/{adq_identificacion}/{adq_id_personalizado?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@updateCompuesto');
            // Busqueda
            $api->get('adquirentes/busqueda/{campoBuscar}/valor/{valorBuscar}/ofe/{valorOfe}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@busqueda');
            // Adquirentes - Administrar Usuarios Portales - Permite la creación o la actualización de estados de los usuarios de portal clientes
            $api->post('adquirentes/administrar-usuarios-portales', 'App\Http\Modulos\Configuracion\UsuariosPortalClientes\EtlUsuarioPortalClientesController@administrarUsuariosPortales');
            // Adquirentes - Usuarios Portales
            $api->post('adquirentes/actualizar-usuarios-portales', 'App\Http\Modulos\Configuracion\UsuariosPortalClientes\EtlUsuarioPortalClientesController@actualizarUsuariosPortales');
            // Adquirentes - Estado Usuario Portales
            $api->post('adquirentes/actualizar-estado-usuario-portales', 'App\Http\Modulos\Configuracion\UsuariosPortalClientes\EtlUsuarioPortalClientesController@actualizarEstadoUsuarioPortales');
            // Adquirentes - Descargar listado de usuarios de portales
            $api->get('adquirentes/descargar-lista-usuarios-portales', 'App\Http\Modulos\Configuracion\UsuariosPortalClientes\EtlUsuarioPortalClientesController@descargarListaUsuariosPortales');
            // Generar interface excel Usuarios Portal Clientes
            $api->post('adquirentes/generar-interface-usuarios-portal-clientes', 'App\Http\Modulos\Configuracion\UsuariosPortalClientes\EtlUsuarioPortalClientesController@generarInterfaceUsuariosPortalClientes');
            // Cargar Usuarios Portal Clientes
            $api->post('adquirentes/cargar-usuarios-portal-clientes', 'App\Http\Modulos\Configuracion\UsuariosPortalClientes\EtlUsuarioPortalClientesController@cargarUsuariosPortalClientes');
            // Reporte en Background de Adquirentes
            $api->post('adquirentes/reportes/background/generar', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@agendarReporte');

            // CRUD Adquirentes
            $api->resource('adquirentes', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController', [ 'except' => [
                    'createFromDI',
                    'getListaAdquirentes',
                    'editarTipoAdquirente',
                    'cambiarEstado',
                    'getTipoAdquirente',
                    'searchAdquirentes',
                    'buscarAdquirenteOfe',
                    'generarInterfaceAdquirentes',
                    'cargarAdquirentes',
                    'getListaErroresAdquirentes',
                    'descargarListaErroresAdquirentes',
                    'showCompuesto',
                    'updateCompuesto',
                    'busqueda',
                    'agendarReporte'
                ]]
            );

            // Listado de Autorizados
            $api->get('lista-autorizados','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getListaAdquirentes');
            // Retorna un Autorizado
            $api->get('autorizados/{ofe_identificacion}/{adq_identificacion}/{adq_id_personalizado?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@showCompuesto');
            // Actualiza un Autorizados
            $api->put('autorizados/{ofe_identificacion}/{adq_identificacion}/{adq_id_personalizado?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@updateCompuesto');
            // Generar excel Autorizados
            $api->post('autorizados/generar-interface-autorizados', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@generarInterfaceAdquirentes');
            // Cargar Autorizado
            $api->post('autorizados/cargar-autorizados', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@cargarAdquirentes');
            // Listado de errores en cargues masivos Autorizados
            $api->post('autorizados/lista-errores','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getListaErroresAdquirentes');
            // Descargar Errores Autorizados
            $api->post('autorizados/lista-errores/excel', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@descargarListaErroresAdquirentes');
            // Busqueda
            $api->get('autorizados/busqueda/{campoBuscar}/valor/{valorBuscar}/ofe/{valorOfe}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@busqueda');
            // Cambiar estado de uno o varios Autorizados ACTIVO-INACTIVO
            $api->post('autorizados/cambiar-estado','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@cambiarEstado');

            // CRUD Autorizados
            $api->resource('autorizados', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController', [ 'except' => [
                    'getListaAdquirentes',
                    'showCompuesto',
                    'updateCompuesto',
                    'generarInterfaceAdquirentes',
                    'cargarAdquirentes',
                    'getListaErroresAdquirentes',
                    'descargarListaErroresAdquirentes',
                    'busqueda',
                    'cambiarEstado'
                ]]
            );

            // Listado de Responsables
            $api->get('lista-responsables','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getListaAdquirentes');
            // Retorna un Responsable
            $api->get('responsables/{ofe_identificacion}/{adq_identificacion}/{adq_id_personalizado?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@showCompuesto');
            // Actualiza un Responsable
            $api->put('responsables/{ofe_identificacion}/{adq_identificacion}/{adq_id_personalizado?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@updateCompuesto');
            // Generar excel Responsable
            $api->post('responsables/generar-interface-responsables', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@generarInterfaceAdquirentes');
            // Cargar Responsable
            $api->post('responsables/cargar-responsables', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@cargarAdquirentes');
            // Listado de errores en cargues masivos Responsables
            $api->post('responsables/lista-errores','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getListaErroresAdquirentes');
            // Descargar Errores Responsables
            $api->post('responsables/lista-errores/excel', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@descargarListaErroresAdquirentes');
            // Busqueda
            $api->get('responsables/busqueda/{campoBuscar}/valor/{valorBuscar}/ofe/{valorOfe}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@busqueda');
            // Cambiar estado de uno o varios Responsables ACTIVO-INACTIVO
            $api->post('responsables/cambiar-estado','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@cambiarEstado');

            // CRUD Responsables
            $api->resource('responsables', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController', [ 'except' => [
                    'getListaAdquirentes',
                    'showCompuesto',
                    'updateCompuesto',
                    'generarInterfaceAdquirentes',
                    'cargarAdquirentes',
                    'getListaErroresAdquirentes',
                    'descargarListaErroresAdquirentes',
                    'busqueda',
                    'cambiarEstado'
                ]]
            );

            // Listado de Vendedores
            $api->get('lista-vendedores-ds','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getListaAdquirentes');
            // Retorna un Vendedor cuando adq_tipo_vendedor_ds = SI
            $api->get('vendedores-ds/{ofe_identificacion}/{adq_identificacion}/{adq_id_personalizado?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@showCompuestoVendedor');
            // Actualiza un Vendedor
            $api->put('vendedores-ds/{ofe_identificacion}/{adq_identificacion}/{adq_id_personalizado?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@updateCompuesto');
            // Generar excel Vendedores
            $api->post('vendedores-ds/generar-interface-vendedores-ds', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@generarInterfaceAdquirentes');
            // Cargar Vendedor
            $api->post('vendedores-ds/cargar-vendedores-ds', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@cargarAdquirentes');
            // Listado de errores en cargues masivos Vendedores
            $api->post('vendedores-ds/lista-errores','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@getListaErroresAdquirentes');
            // Descargar Errores Vendedores
            $api->post('vendedores-ds/lista-errores/excel', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@descargarListaErroresAdquirentes');
            // Busqueda
            $api->get('vendedores-ds/busqueda/{campoBuscar}/valor/{valorBuscar}/ofe/{valorOfe}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@busqueda');
            // Busca uno o varios Vendedores cuando adq_tipo_vendedor_ds = SI, dependiendo de la identificación del Ofe (no obligatorio)
            $api->get('vendedores-ds/buscar-vendedores-ds-ofe/{adq_identificacion}/ofe/{ofe_identificacion?}', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@buscarVendedorOfe');
            // Crear/Editar Vendedores DI
            $api->post('vendedores-ds/di-gestion','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@createFromDI');
            // Cambiar estado de uno o varios Vendedores ACTIVO-INACTIVO
            $api->post('vendedores-ds/cambiar-estado','App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController@cambiarEstado');

            // CRUD Vendedores
            $api->resource('vendedores-ds', 'App\Http\Modulos\Configuracion\Adquirentes\ConfiguracionAdquirenteController', [ 'except' => [
                    'getListaAdquirentes',
                    'showCompuestoVendedor',
                    'updateCompuesto',
                    'generarInterfaceAdquirentes',
                    'cargarAdquirentes',
                    'getListaErroresAdquirentes',
                    'descargarListaErroresAdquirentes',
                    'busqueda',
                    'buscarVendedorOfe',
                    'createFromDI',
                    'cambiarEstado'
                ]]
            );

            // PROVEEDORES
            // Cambiar estado de uno o varios Proveedores ACTIVO-INACTIVO
            $api->post('proveedores/cambiar-estado','App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@cambiarEstado');
            // Listado de Proveedores
            $api->get('lista-proveedores','App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@getListaProveedores');
            // Generar interface excel Proveedores
            $api->post('proveedores/generar-interface-proveedores', 'App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@generarInterfaceProveedores');
            // Cargar Proveedores
            $api->post('proveedores/cargar-proveedores', 'App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@cargarProveedores');
            // Listado de errores en cargues masivos
            $api->post('proveedores/lista-errores','App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@getListaErroresProveedores');
            // Descargar Errores Proveedores
            $api->post('proveedores/lista-errores/excel', 'App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@descargarListaErroresProveedores');
            // Obtiene una lista de usuarios del sistema relacionados con la BD a la cual pertenece el usuario autenticacado
            $api->get('proveedores/obtener-usuarios/{ofe_id}/{text}', 'App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@obtenerUsuarios');
            // Busqueda de proveedores
            $api->get('proveedores/busqueda/{campoBuscar}/valor/{valorBuscar}/ofe/{valorOfe}/filtro/{filtroColumnas}', 'App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@busqueda');
            // Retorna un Proveedor
            $api->get('proveedores/{ofe_identificacion}/{pro_identificacion}', 'App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@showCompuesto');
            // Actualiza un Proveedor
            $api->put('proveedores/{ofe_identificacion}/{pro_identificacion}', 'App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@updateCompuesto');
            // Busca coincidencias de proveedores para un OFE
            $api->get('proveedores/search-proveedores','App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController@searchProveedores');

            // Proveedores - Administrar Usuarios Portales - Permite la creación o la actualización de estados de los usuarios de portal proveedores
            $api->post('proveedores/administrar-usuarios-portales', 'App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores\RepUsuarioPortalProveedoresController@administrarUsuariosPortales');
            // Proveedores - Usuarios Portales
            $api->post('proveedores/actualizar-usuarios-portales', 'App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores\RepUsuarioPortalProveedoresController@actualizarUsuariosPortales');
            // Proveedores - Estado Usuario Portales
            $api->post('proveedores/actualizar-estado-usuario-portales', 'App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores\RepUsuarioPortalProveedoresController@actualizarEstadoUsuarioPortales');
            // Proveedores - Descargar listado de usuarios de portales
            $api->get('proveedores/descargar-lista-usuarios-portales', 'App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores\RepUsuarioPortalProveedoresController@descargarListaUsuariosPortales');
            // Generar interface excel Usuarios Portal Proveedores
            $api->post('proveedores/generar-interface-usuarios-portal-proveedores', 'App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores\RepUsuarioPortalProveedoresController@generarInterfaceUsuariosPortalProveedores');
            // Cargar Usuarios Portal Proveedores
            $api->post('proveedores/cargar-usuarios-portal-proveedores', 'App\Http\Modulos\Recepcion\Configuracion\UsuariosPortalProveedores\RepUsuarioPortalProveedoresController@cargarUsuariosPortalProveedores');

            // CRUD Proveedores
            $api->resource('proveedores', 'App\Http\Modulos\Recepcion\Configuracion\Proveedores\ConfiguracionProveedorController', ['except' => [
                'cambiarEstado',
                'getListaProveedores',
                'generarInterfaceProveedores',
                'cargarProveedores',
                'getListaErroresProveedores',
                'descargarListaErroresProveedores',
                'obtenerUsuarios',
                'busqueda',
                'showCompuesto',
                'updateCompuesto',
                'searchProveedores'
                ]]
            );

            // Generar interface excel de Autorizaciones Eventos Dian
            $api->post('autorizaciones-eventos-dian/generar-interface-autorizaciones-eventos-dian', 'App\Http\Modulos\Configuracion\AutorizacionesEventosDian\ConfiguracionAutorizacionEventoDianController@generarInterfaceAutorizacionesEventosDian');
            // Listado de errores en cargues masivos
            $api->post('autorizaciones-eventos-dian/lista-errores','App\Http\Modulos\Configuracion\AutorizacionesEventosDian\ConfiguracionAutorizacionEventoDianController@getListaErroresAutorizacionesEventosDian');
            // Descargar errores en cargues masivos de Autorizaciones Eventos Dian
            $api->post('autorizaciones-eventos-dian/lista-errores/excel', 'App\Http\Modulos\Configuracion\AutorizacionesEventosDian\ConfiguracionAutorizacionEventoDianController@descargarListaErroresAutorizacionesEventosDian');
            // Cargar Autorizaciones Eventos Dian mediante un excel
            $api->post('autorizaciones-eventos-dian/cargar-autorizaciones-eventos-dian', 'App\Http\Modulos\Configuracion\AutorizacionesEventosDian\ConfiguracionAutorizacionEventoDianController@cargarAutorizacionesEventosDian');
            // Listado de Autorizaciones Eventos Dian
            $api->get('lista-autorizaciones-eventos-dian','App\Http\Modulos\Configuracion\AutorizacionesEventosDian\ConfiguracionAutorizacionEventoDianController@getListaAutorizacionesEventosDian');
            // Cambiar estado de los resgitros ACTIVO-INACTIVO
            $api->post('autorizaciones-eventos-dian/cambiar-estado', 'App\Http\Modulos\Configuracion\AutorizacionesEventosDian\ConfiguracionAutorizacionEventoDianController@cambiarEstado');
            // CRUD de Autorizaciones Eventos Dian
            $api->resource('autorizaciones-eventos-dian', 'App\Http\Modulos\Configuracion\AutorizacionesEventosDian\ConfiguracionAutorizacionEventoDianController', ['except' => [
                'cambiarEstado', 
                'getListaAutorizacionesEventosDian',
                'busqueda'
                ]]
            );

            // Listado de Administracion Recepcion ERP
            $api->get('lista-administracion-recepcion-erp', 'App\Http\Modulos\Configuracion\AdministracionRecepcionErp\ConfiguracionAdministracionRecepcionErpController@getListaAdministracionRecepcionErp');
            // Cambiar estado de los registros ACTIVO-INACTIVO
            $api->post('administracion-recepcion-erp/cambiar-estado', 'App\Http\Modulos\Configuracion\AdministracionRecepcionErp\ConfiguracionAdministracionRecepcionErpController@cambiarEstado');
            // Ng Selected para buscar una Condicion
            $api->get('administracion-recepcion-erp/buscar-ng-select/{campoBuscar}/{aplicaPara}/{ofe_identificacion}', 'App\Http\Modulos\Configuracion\AdministracionRecepcionErp\ConfiguracionAdministracionRecepcionErpController@buscarCondicionNgSelect');
            // CRUD de Administracion Recepcion ERP
            $api->resource('administracion-recepcion-erp', 'App\Http\Modulos\Configuracion\AdministracionRecepcionErp\ConfiguracionAdministracionRecepcionErpController', ['except' => [
                'getListaAdministracionRecepcionErp',
                'cambiarEstado',
                'buscarCondicionNgSelect'
                ]]
            );

            // Listado de los roles de openECM
            $api->get('lista-roles-ecm/{ofe_identificacion}', 'App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController@obtenerRolesEcm');
            // Obtiene la lista de los Ofes
            $api->get('lista-ofes/{usu_identificacion}', 'App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController@consultaOfes');
            // Listado de Usuarios openECM
            $api->get('lista-usuarios-ecm','App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController@getListaUsuariosEcm');
            // Cambiar estado de los resgitros ACTIVO-INACTIVO
            $api->post('usuarios-ecm/cambiar-estado', 'App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController@cambiarEstado');
            // Cargar Usuarios openECM mediante un excel
            $api->post('usuarios-ecm/cargar-usuarios-ecm', 'App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController@cargarUsuarioEcm');
            // Generar interface excel de Usuarios openECM
            $api->post('usuarios-ecm/generar-interface-usuarios-ecm', 'App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController@generarInterfaceUsuarioEcm');
            // Listado de errores en cargues masivos
            $api->post('usuarios-ecm/lista-errores','App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController@getListaErroresUsuarioEcm');
            // Descargar errores en cargues masivos de Usuarios openECM
            $api->post('usuarios-ecm/lista-errores/excel', 'App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController@descargarListaErroresUsuarioEcm');
            // CRUD de Usuarios openECM
            $api->resource('usuarios-ecm', 'App\Http\Modulos\Configuracion\UsuariosEcm\ConfiguracionUsuarioEcmController', ['except' => [
                'obtenerRolesEcm',
                'consultaOfes',
                'getListaUsuariosEcm',
                'cambiarEstado',
                'cargarUsuarioEcm',
                'generarInterfaceUsuarioEcm',
                'getListaErroresUsuarioEcm',
                'descargarListaErroresUsuarioEcm'
                ]]
            );

            // Listado de Grupos de Trabajo
            $api->get('lista-grupos-trabajo', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@getListaGruposTrabajo');
            // Generar excel Grupos de Trabajo
            $api->post('grupos-trabajo/generar-interface-grupos-trabajo', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@generarInterfaceGruposTrabajo');
            // Listado de errores en cargues masivos
            $api->post('grupos-trabajo/lista-errores', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@getListaErroresGruposTrabajo');
            // Descargar Errores al cargar Grupos de Trabajo
            $api->post('grupos-trabajo/lista-errores/excel', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@descargarListaErroresGruposTrabajo');
            // Cargar Grupos de Trabajo
            $api->post('grupos-trabajo/cargar-grupos-trabajo', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@cargarGruposTrabajo');
            // Busqueda de Grupos de Trabajo
            $api->get('grupos-trabajo/busqueda/{campoBuscar}/valor/{valorBuscar}/ofe/{valorOfe}/filtro/{filtroColumnas}', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@busqueda');
            // Cambiar estado de los resgitros ACTIVO-INACTIVO
            $api->post('grupos-trabajo/cambiar-estado', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@cambiarEstado');
            // Retorna un Grupo de Trabajo
            $api->get('grupos-trabajo/{ofe_identificacion}/{codigo_grupo}', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@showCompuesto');
            // Actualiza un Grupo de Trabajo
            $api->put('grupos-trabajo/{ofe_identificacion}/{codigo_grupo}', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@updateCompuesto');
            // Busca coincidencias de proveedores para un OFE
            $api->get('grupos-trabajo/search-grupos-trabajo', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController@searchGruposTrabajo');
            // CRUD de Grupos de Trabajo
            $api->resource('grupos-trabajo', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajo\ConfiguracionGrupoTrabajoController', ['except' => [
                'getListaGruposTrabajo',
                'generarInterfaceGruposTrabajo',
                'getListaErroresGruposTrabajo',
                'descargarListaErroresGruposTrabajo',
                'cargarGruposTrabajo',
                'busqueda',
                'cambiarEstado',
                'showCompuesto',
                'updateCompuesto',
                'searchGruposTrabajo'
                ]]
            );

            // Listado de Usuarios asociados a los Grupos de Trabajo
            $api->get('lista-grupos-trabajo-usuarios', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@getListaGruposTrabajoUsuarios');
            // Generar excel Grupos de Trabajo Usuarios
            $api->post('grupos-trabajo-usuarios/generar-interface-grupos-trabajo-usuarios', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@generarInterfaceGruposTrabajoUsuarios');
            // Listado de errores en cargues masivos
            $api->post('grupos-trabajo-usuarios/lista-errores', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@getListaErroresGruposTrabajoUsuarios');
            // Descargar Errores al cargar Grupos de Trabajo Usuarios
            $api->post('grupos-trabajo-usuarios/lista-errores/excel', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@descargarListaErroresGruposTrabajoUsuarios');
            // Cargar Grupos de Trabajo Usuarios
            $api->post('grupos-trabajo-usuarios/cargar-grupos-trabajo-usuarios', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@cargarGruposTrabajoUsuarios');
            // Cambiar estado de los resgitros ACTIVO-INACTIVO
            $api->post('grupos-trabajo-usuarios/cambiar-estado', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@cambiarEstado');
            // Retorna un Grupo de Trabajo de usuarios asociados
            $api->get('grupos-trabajo-usuarios/{gtr_codigo}/{ofe_identificacion}/{usu_email}', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@consultarGrupoUsuarioAsociado');
            // Actualiza un Grupo de Trabajo
            $api->put('grupos-trabajo-usuarios/{gtr_codigo}/{ofe_identificacion}/{usu_email}', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@actualizarGrupoUsuarioAsociado');
            // Listado de los Usuarios asociados a un Grupo de Trabajo específico
            $api->get('grupos-trabajo-usuarios/lista-usuarios-asociados', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@listarUsuariosAsociados');
            // Listado de los Usuarios gestores o validadores
            $api->post('grupos-trabajo-usuarios/search-usuarios-gestor-validador', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@searchUsuariosGestorValidador');
            // Listado de los Grupos de Trabajo a los cuales pertenece el usuario autenticado
            $api->post('grupos-trabajo-usuarios/grupos-trabajo', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController@gruposTrabajoUsuario');


            // CRUD de Grupos de Trabajo Usuarios
            $api->resource('grupos-trabajo-usuarios', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoUsuarios\ConfiguracionGrupoTrabajoUsuarioController', ['except' => [
                'getListaGruposTrabajoUsuarios',
                'generarInterfaceGruposTrabajoUsuarios',
                'getListaErroresGruposTrabajoUsuarios',
                'descargarListaErroresGruposTrabajoUsuarios',
                'cargarGruposTrabajoUsuarios',
                'cambiarEstado',
                'consultarGrupoUsuarioAsociado',
                'actualizarGrupoUsuarioAsociado',
                'listarUsuariosAsociados',
                'gruposTrabajoUsuario'
                ]]
            );

            // Listado de Proveedores asociados a los Grupos de Trabajo
            $api->get('lista-grupos-trabajo-proveedores', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController@getListaGruposTrabajoProveedores');
            // Generar excel Grupos de Trabajo Proveedores
            $api->post('grupos-trabajo-proveedores/generar-interface-grupos-trabajo-proveedores', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController@generarInterfaceGruposTrabajoProveedores');
            // Listado de errores en cargues masivos
            $api->post('grupos-trabajo-proveedores/lista-errores', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController@getListaErroresGruposTrabajoProveedores');
            // Descargar Errores al cargar Grupos de Trabajo Proveedores
            $api->post('grupos-trabajo-proveedores/lista-errores/excel', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController@descargarListaErroresGruposTrabajoProveedores');
            // Cargar Grupos de Trabajo Proveedores
            $api->post('grupos-trabajo-proveedores/cargar-grupos-trabajo-proveedores', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController@cargarGruposTrabajoProveedor');
            // Cambiar estado de los resgitros ACTIVO-INACTIVO
            $api->post('grupos-trabajo-proveedores/cambiar-estado', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController@cambiarEstado');
            // Listado de los Proveedores asociados a un Grupo de Trabajo específico
            $api->get('grupos-trabajo-proveedores/lista-proveedores-asociados', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController@listarProveedoresAsociados');
            // Listado de los Grupos de Trabajo asociados a un Proveedor
            $api->get('grupos-trabajo-proveedores/lista-grupos-trabajo-proveedor', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController@listarGruposTrabajoProveedor');
            // CRUD de Grupos de Trabajo Proveedores
            $api->resource('grupos-trabajo-proveedores', 'App\Http\Modulos\Configuracion\GruposTrabajo\GruposTrabajoProveedores\ConfiguracionGrupoTrabajoProveedorController', ['except' => [
                'getListaGruposTrabajoProveedores',
                'generarInterfaceGruposTrabajoProveedores',
                'getListaErroresGruposTrabajoProveedores',
                'descargarListaErroresGruposTrabajoProveedores',
                'cargarGruposTrabajoProveedor',
                'cambiarEstado',
                'listarProveedoresAsociados',
                'listarGruposTrabajoProveedor'
                ]]
            );

            // Listado de los XPath de Documentos Electrónicos Estándar
            $api->get('lista-xpath-documentos-estandar', 'App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoController@getListaConfiguracionEstandar');
            // Retorna un XPath de Documentos Electrónicos Estándar
            $api->get('xpath-documentos-estandar/{xde_id}', 'App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoController@showConfiguracionEstandar');
            // Documentos Electrónicos Estándar - Cambiar estado de los resgitros ACTIVO-INACTIVO
            $api->post('xpath-documentos-estandar/cambiar-estado', 'App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoController@cambiarEstadoConfiguracionEstandar');
            // CRUD de los XPath de Documentos Electrónicos Estándar
            $api->resource('xpath-documentos-estandar', 'App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoController', ['except' => [
                'getListaConfiguracionEstandar',
                'showConfiguracionEstandar',
                'cambiarEstadoConfiguracionEstandar'
                ]]
            );

            // Listado de los XPath de Documentos Electrónicos Personalizados
            $api->get('lista-xpath-documentos-personalizados', 'App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoController@getListaConfiguracionPersonalizada');
            // Retorna un XPath de Documentos Electrónicos Estándar
            $api->get('xpath-documentos-personalizados/{xde_id}', 'App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoController@showConfiguracionPersonalizada');
            // Documentos Electrónicos Personalizados - Cambiar estado de los resgitros ACTIVO-INACTIVO
            $api->post('xpath-documentos-personalizados/cambiar-estado', 'App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoController@cambiarEstadoConfiguracionPersonalizada');
            // CRUD de los XPath de Documentos Electrónicos Personalizados
            $api->resource('xpath-documentos-personalizados', 'App\Http\Modulos\Parametros\XpathDocumentosElectronicos\ParametrosXpathDocumentoElectronicoController', ['except' => [
                'getListaConfiguracionPersonalizada',
                'showConfiguracionPersonalizada',
                'cambiarEstadoConfiguracionPersonalizada'
                ]]
            );
        }
    );

    // Commons
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'commons'
        ],
        function ($api){
            // Rutas Documentos
            $api->get('get-data-init-for-build', 'App\Http\Modulos\Commons\CommonsController@getInitDataForBuild');
            // Rutas Documentos
            $api->get('get-digito-verificacion', 'App\Http\Modulos\Commons\CommonsController@getDigitoVerificacion');
            // Lista de errores para tracking de Log de Errores o para descarga de excel
            $api->post('log-errores', 'App\Http\Modulos\Commons\CommonsController@getListaErrores');
            // Retorna data paramétrica para la creación manual de documentos electrónicos
            $api->get('get-parametros-documentos-electronicos', 'App\Http\Modulos\Commons\CommonsController@getParametrosDocumentosElectronicos');
            // Lista de los oferentes que aplican para el módulo de emisión o recepción
            $api->get('get-ofes', 'App\Http\Modulos\Commons\CommonsController@getOferentes');
            // Listado de los reportes generados en background de Configuración
            $api->get('reportes/background/listar-reportes-descargar', 'App\Http\Modulos\Commons\CommonsController@listarReportesDescargar');
            // Descargar el reporte generado en background de Configuración
            $api->post('reportes/background/descargar-reporte-background', 'App\Http\Modulos\Commons\CommonsController@descargarReporte');
        }
    );

    // Facturación Web
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'facturacion-web'            
        ],
        function ($api){
            // Facturación Web - Control Consecutivos
            // Lista de control de consecutivos
            $api->get('parametros/lista-control-consecutivos', 'App\Http\Modulos\Documentos\EtlConsecutivosDocumentos\EtlConsecutivoDocumentoController@listarControlConsecutivos');
            // Cambio de estado de control de conscutivos
            $api->post('parametros/control-consecutivos/cambiar-estado', 'App\Http\Modulos\Documentos\EtlConsecutivosDocumentos\EtlConsecutivoDocumentoController@cambiarEstado');
            // Recurso de control de consecutivos
            $api->resource('parametros/control-consecutivos', 'App\Http\Modulos\Documentos\EtlConsecutivosDocumentos\EtlConsecutivoDocumentoController', ['except' => [
                'cambiarEstado',
                'listarControlConsecutivos'
            ]]);

            // Facturación Web - Cargos
            // Lista de Cargos
            $api->get('parametros/lista-cargos', 'App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController@getListaCargos');
            // Cambiar estado de Cargos
            $api->post('parametros/cargos/cambiar-estado', 'App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController@cambiarEstado');
            // Generar interface de Excel de Cargos
            $api->post('parametros/cargos/generar-interface', 'App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController@generarInterfaceCargos');
            // Cargar Cargos mediante Excel
            $api->post('parametros/cargos/cargar', 'App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController@cargarCargos');
            // Listado de errores en cargues masivos
            $api->post('parametros/cargos/lista-errores','App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController@getListaErroresCargos');
            // Descargar Errores de cargues de excel
            $api->post('parametros/cargos/lista-errores/excel', 'App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController@descargarListaErroresCargos');
            // Ng Selected para buscar un Cargo
            $api->get('parametros/cargos/buscar-ng-select/{campoBuscar}/ofe/{ofe_id}', 'App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController@buscarCargoNgSelect');
            // Busqueda de cargos
            // $api->get('parametros/cargos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController@busqueda');
            // Recurso de Cargos
            $api->resource('parametros/cargos', 'App\Http\Modulos\FacturacionWeb\Parametros\Cargos\EtlFacturacionWebCargoController', ['except' => [
                'getListaCargos',
                'cambiarEstado',
                'generarInterfaceCargos',
                'cargarCargos',
                'getListaErroresCargos',
                'descargarListaErroresCargos',
                'buscarCargoNgSelect',
                // 'busqueda'
            ]]);

            // Facturación Web - Descuentos
            // Lista de Descuentos
            $api->get('parametros/lista-descuentos', 'App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController@getListaDescuentos');
            // Cambiar estado de Descuentos
            $api->post('parametros/descuentos/cambiar-estado', 'App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController@cambiarEstado');
            // Generar interface de Excel de Descuentos
            $api->post('parametros/descuentos/generar-interface', 'App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController@generarInterfaceDescuentos');
            // Cargar Descuentos mediante Excel
            $api->post('parametros/descuentos/cargar', 'App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController@cargarDescuentos');
            // Listado de errores en cargues masivos
            $api->post('parametros/descuentos/lista-errores','App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController@getListaErroresDescuentos');
            // Descargar Errores de cargues de excel
            $api->post('parametros/descuentos/lista-errores/excel', 'App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController@descargarListaErroresDescuentos');
            // Ng Selected para buscar un Descuento
            $api->get('parametros/descuentos/buscar-ng-select/{campoBuscar}/ofe/{ofe_id}', 'App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController@buscarDescuentoNgSelect');
            // Busqueda de descuentos
            // $api->get('parametros/descuentos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController@busqueda');
            // Recurso de Descuentos
            $api->resource('parametros/descuentos', 'App\Http\Modulos\FacturacionWeb\Parametros\Descuentos\EtlFacturacionWebDescuentoController', ['except' => [
                'getListaDescuentos',
                'cambiarEstado',
                'generarInterfaceDescuentos',
                'cargarDescuentos',
                'getListaErroresDescuentos',
                'descargarListaErroresDescuentos',
                'buscarDescuentoNgSelect',
                // 'busqueda'
            ]]);

            // Emisión Facturación Web - Productos
            // Lista de Productos
            $api->get('parametros/lista-productos', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@getListaProductos');
            // Cambiar estado de Productos
            $api->post('parametros/productos/cambiar-estado', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@cambiarEstado');
            // Generar interface de Excel de Productos
            $api->post('parametros/productos/generar-interface', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@generarInterfaceProductos');
            // Consultar producto mediante la identificación dle OFE y el código del producto
            $api->post('parametros/productos/consultar-producto', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@consultarProducto');
            // Cargar Productos mediante Excel
            $api->post('parametros/productos/cargar', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@cargarProductos');
            // Listado de errores en cargues masivos
            $api->post('parametros/productos/lista-errores','App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@getListaErroresProductos');
            // Descargar Errores de cargues de excel
            $api->post('parametros/productos/lista-errores/excel', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@descargarListaErroresProductos');
            // Ng Selected para buscar un Producto
            $api->get('parametros/productos/buscar-ng-select/{campoBuscar}/ofe/{ofe_id}', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@buscarProductoNgSelect');
            // Busqueda de Productos
            // $api->get('parametros/productos/busqueda/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController@busqueda');
            // Recurso de Productos
            $api->resource('parametros/productos', 'App\Http\Modulos\FacturacionWeb\Parametros\Productos\EtlFacturacionWebProductoController', ['except' => [
                'getListaProductos',
                'cambiarEstado',
                'generarInterfaceProductos',
                'consultarProducto',
                'cargarProductos',
                'getListaErroresProductos',
                'descargarListaErroresProductos',
                'buscarProductoNgSelect',
                // 'busqueda'
            ]]);
        }
    );

    // Emisión
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'emision'            
        ],
        function ($api){
            // Rutas Documentos
            $api->post('lista-documentos', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@getListaDocumentos');
            // Lista para tracking de documentos enviados
            $api->post('lista-documentos-enviados','App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@getListaDocumentosEnviados');
            // Descarga en Excel la lista de documentos sin envío
            $api->post('descargar-lista-documentos-sin-envio','App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@descargarListaDocumentosSinEnvio');
            // Cambia estado a documentos - Recibe IDs de los documentos en el request
            $api->post('documentos/cambiar-estado-documentos','App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@cambiarEstadoDocumentos');
            // Cambia estado a documentos - Recibe en el request el ofe_identificacion, tipo_documento, prefijo, consecutivo y estado
            $api->post('documentos/cambio-estado-documento','App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@cambioEstadoDocumento');
            // Registrar gestión de documentos rechazados
            $api->post('registrar-gestion-documentos-rechazados','App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@registrarGestionDocumentosRechazados');
            // Generar excel factura
            $api->get('documentos/generar-interface-facturas/{ofe_id}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@generarInterfaceFacturas');
            // Generar excel nota credito
            $api->get('documentos/generar-interface-notas-credito-debito/{ofe_id}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@generarInterfaceNotasCreditoDebito');
            // Marcar documentos para envio, aplica solo para documentos sin envío
            $api->post('documentos/enviar', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@enviarDocumentos');
            // Crea estados de ACEPTACIONT
            $api->post('documentos/agendar-estados-aceptacion-tacita', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@agendarEstadosAceptacionTacita');
            // Enviar documentos a la DIAN, aplica solo para documentos enviados
            $api->post('documentos/enviar-documentos-dian', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@enviarDocumentosDian');
            // Modifica documentos para que puedan ser editados, aplica solamente para documentos de DHL Express rechazados por la DIAN y con RG 9
            $api->post('documentos/modificar-documentos-pickup-cash', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@modificarDocumentosPickupCash');
            // Modifica documentos para que puedan ser editados, NO aplica solamente para documentos de DHL Express rechazados por la DIAN y con RG 9
            $api->post('documentos/modificar-documentos', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@modificarDocumentos');
            // Modal documentos
            $api->get('documentos/{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@buscarDocumentos');
            // Consultar Documento
            $api->get('documentos/consultar/ofe/{ofe_identificacion}/prefijo/{prefijo}/consecutivo/{consecutivo}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@consultarDocumento');
            // Consultar Documento por tipo de documento electronico
            $api->get('documentos/consultar-documento/ofe/{ofe_identificacion}/tipo/{tipo}/prefijo/{prefijo}/consecutivo/{consecutivo}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@consultarDocumentoElectronico');
            // Consultar Eventos de Notificación de un documento electronico
            $api->get('documentos/consultar-eventos-notificacion/ofe/{ofe_identificacion}/tipo/{tipo}/prefijo/{prefijo}/consecutivo/{consecutivo}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@consultarEventosNotificacionDocumentoElectronico');
            // Consulta data adicional de documentos, aplica a DHL Express - Proceso Pickup Cash
            $api->post('modificar-documento/consultar-data', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@consultarDataDocumentoModificar');
            // Consultar Documento de Referencia
            $api->post('documentos/consultar-documentos-referencia', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@consultarDocumentoElectronicoReferencia');
            // Obtiene la data de un documento electrónico específico
            $api->post('documentos/consultar-data-documento-electronico', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@obtenerInformacionDocumentoElectronico');
            // Obtiene los estados de un documento en específico
            $api->post('documentos/estados-documento', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@obtenerEstadosDocumento');            
            // Obtiene los documentos anexos de un documento en específico
            $api->post('documentos/documentos-anexos', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@obtenerDocumentosAnexos');

            // DOCUMENTOS ANEXOS
            // Encuentra un documento en la BD
            $api->post('documentos/encontrar-documento', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@encontrarDocumento');
            // Cargar documentos anexos a un documento en el sistema
            $api->post('documentos/cargar-documentos-anexos', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@cargarDocumentosAnexos');
            // Descargar documentos anexos
            $api->get('documentos/descargar-documentos-anexos/{ids}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@descargarDocumentosAnexos');
            // Eliminar documentos anexos
            $api->delete('documentos/eliminar-documentos-anexos', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@eliminarDocumentosAnexos');

            // Descargar documentos
            $api->post('documentos/descargar', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@descargarDocumentos');
            
            // Descargar pdf
            $api->post('documentos/descargar-pdf', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@descargarPdf');
            // Descargar pdf enviado a la DIAN
            $api->post('documentos/obtener-pdf', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@obtenerPdfNotificacion');
            // Reemplazar PDF de documento electrónico
            $api->post('documentos/reemplazar-pdf', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@reemplazarPdf');
            // Descargar Xml-Ubl
            $api->post('documentos/obtener-xml-ubl', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@obtenerXmlUbl');
            // Descargar Attached Document
            $api->post('documentos/obtener-attached-document', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@obtenerAttachedDocument');
            // Errores Documentos
            $api->post('documentos/lista-errores', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@getListaErroresDocumentos');
            
            // Descargar Errores Documentos
            $api->post('documentos/lista-errores/excel', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@descargarListaErroresDocumentos');
            // Retorna el estado de proceso de un documento en el sistema
            $api->post('documentos/estado-documento', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@getEstadoDocumento');
            // Retorna el resultado de cada uno de los procesos por lo que pasa un documento
            $api->get('documentos/resultado-procesos/{id}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@getResultadosProcesos');

            // CRUD documentos
            $api->resource('documentos', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController', ['except' => [
                    'getListaDocumentos',
                    'getListaDocumentosRechazados',
                    'generarFactura',
                    'generarNotaCreditoDebito',
                    'enviarDocumentos',
                    'enviarDocumentosDian',
                    'descargarDocumentos',
                    'getListaErroresDocumentos',
                    'excelListaErroresDocumentos',
                    'encontrarDocumento',
                    'cargarDocumentosAnexos',
                    'descargarDocumentosAnexos',
                    'eliminarDocumentosAnexos',
                    'getEstadoDocumento',
                    'getResultadosProcesos',
                    'registrarGestionDocumentosRechazados',
                    'cambiarEstadoDocumentos',
                    'cambioEstadoDocumento',
                    'generarInterfaceFacturas',
                    'reemplazarPdf'
                ]]
            );

            // Endpoint para consultar un documento emitido por Interoperabilidad
            $api->post('get-documento-emitido', 'App\Http\Modulos\interoperabilidad\InteroperabilidadController@getDocumentoETL');

            // Permite la generación de archivos de salida para Ofes marcados para procesamiento FTP 
            $api->post('archivo-salida-ftp', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@archivoSalidaFtp');

            // Reportes
            $api->group(
                [
                    'middleware' => 'api.auth',
                    'prefix' => 'reportes'            
                ],
                function ($api){
                    // Reporte personalizado de DHL Express
                    $api->post('dhl-express', 'App\Http\Modulos\Emision\Reportes\DhlExpressController@dhlExpress');
                    // Listado de reportes de DHL Express que un usuario puede descargar
                    $api->get('listar-reportes-descargar-dhl-express', 'App\Http\Modulos\Emision\Reportes\DhlExpressController@dhlExpressListarReportesDescargas');
                    // Descargar reporte de DHL Express
                    $api->post('descargar-reporte-dhl-express', 'App\Http\Modulos\Emision\Reportes\DhlExpressController@dhlExpressDescargarReporte');

                    // Reporte de Documentos Procesados
                    $api->post('documentos-procesados/generar', 'App\Http\Modulos\Emision\Reportes\DocumentosProcesadosController@agendarReporte');
                    // Listado de reportes de Documentos Procesados que un usuario puede descargar
                    $api->get('documentos-procesados/listar-reportes-descargar', 'App\Http\Modulos\Emision\Reportes\DocumentosProcesadosController@listarReportesDescargar');
                    // Descargar reporte de Documentos Procesados
                    $api->post('documentos-procesados/descargar-reporte', 'App\Http\Modulos\Emision\Reportes\DocumentosProcesadosController@descargarReporte');

                    // Reporte Notificacion por Plataforma de Notificación Documentos 
                    $api->post('eventos-notificacion/generar-plataforma', 'App\Http\Modulos\Emision\Reportes\EventosNotificacionController@agendarReporte');
                    // Listado de reportes de Notificación Documentos que un usuario puede descargar para Notificación por Plataforma
                    $api->get('eventos-notificacion/listar-reportes-plataforma-descargar', 'App\Http\Modulos\Emision\Reportes\EventosNotificacionController@listarReportesDescargar');
                    // Descargar reporte de Notificación Documentos para Notificación por Plataforma
                    $api->post('eventos-notificacion/descargar-reporte-plataforma', 'App\Http\Modulos\Emision\Reportes\EventosNotificacionController@descargarReporte');
                    // Reporte Notificacion SMTP de Notificación Documentos 
                    $api->post('eventos-notificacion/generar-smtp', 'App\Http\Modulos\Emision\Reportes\EmailCertificationController@agendarReporte');
                    // Listado de reportes de Notificación Documentos que un usuario puede descargar para Notificación SMTP
                    $api->get('eventos-notificacion/listar-reportes-smtp-descargar', 'App\Http\Modulos\Emision\Reportes\EmailCertificationController@listarReportesDescargar');
                    // Descargar reporte de Notificación Documentos para Notificación SMTP
                    $api->post('eventos-notificacion/descargar-reporte-smtp', 'App\Http\Modulos\Emision\Reportes\EmailCertificationController@descargarReporte');

                    // Reporte en Background de Emisión
                    $api->post('background/generar', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@agendarReporte');
                    // Listado de los reportes generados en background de Emisión
                    $api->get('background/listar-reportes-descargar', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@listarReportesDescargar');
                    // Descargar el reporte generado en background de Emisión
                    $api->post('background/descargar-reporte-background', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@descargarReporte');
                }
            );
        }
    );

    // Recepción
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'recepcion/reportes'
        ],
        function ($api){
            // Reportes en Background de Recepción
            $api->post('background/generar', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@agendarReporte');
            // Listado de los reportes generados en background de Recepción
            $api->get('background/listar-reportes-descargar', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@listarReportesDescargar');
            // Descargar el reporte generado en background de Recepción
            $api->post('background/descargar-reporte-background', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@descargarReporte');
        
            // Reporte de Documentos Procesados
            $api->post('documentos-procesados/generar', 'App\Http\Modulos\Recepcion\Reportes\RecepcionDocumentosProcesadosController@agendarReporte');
            // Listado de reportes de Documentos Procesados que un usuario puede descargar
            $api->get('documentos-procesados/listar-reportes-descargar', 'App\Http\Modulos\Recepcion\Reportes\RecepcionDocumentosProcesadosController@listarReportesDescargar');
            // Descargar reporte de Documentos Procesados
            $api->post('documentos-procesados/descargar-reporte', 'App\Http\Modulos\Recepcion\Reportes\RecepcionDocumentosProcesadosController@descargarReporte');

            // Reporte Log Validación Documentos
            $api->post('agendar-log-validacion-documentos','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@agendarLogValidacionDocumentos');
            // Lista de reportes de Log Validación Documentos para descargar
            $api->get('log-validacion-documentos/listar','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@listarReportesDescargar');
            // Descarga de reportes de Log Validación Documentos
            $api->post('log-validacion-documentos/descargar','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@descargarReporte');

            // Agenda el Reporte de Dependencias para generarse en background
            $api->post('reporte-dependencias/generar', 'App\Http\Modulos\Recepcion\Reportes\RecepcionReporteDepencenciaController@agendarReporte');
            // Listado de los Reporte de Dependencias generados en background
            $api->get('reporte-dependencias/listar', 'App\Http\Modulos\Recepcion\Reportes\RecepcionReporteDepencenciaController@listarReportesDescargar');
            // Descarga el Reporte de dependencias generado
            $api->post('reporte-dependencias/descargar','App\Http\Modulos\Recepcion\Reportes\RecepcionReporteDepencenciaController@descargarReporte');
        }
    );

    // Recepción - Documentos
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'recepcion/documentos'
        ],
        function ($api){
            // Listado de documentos recibidos por fecha desde y fecha hasta en el campo fecha de creación
            $api->post('listar-documentos','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@listarDocumentosRecibidos');
            // Lista para tracking de documentos recibidos
            $api->post('lista-documentos-recibidos','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@getListaDocumentosRecibidos');
            // Búsqueda de documentos por campo-valor
            $api->get('{campoBuscar}/valor/{valorBuscar}/filtro/{filtroColumnas}', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@buscarDocumentos');
            // GET Consultar Documento
            $api->get('consultar/ofe/{ofe_identificacion}/prefijo/{prefijo}/consecutivo/{consecutivo}', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@consultarDocumento');
            // POST Consultar Documento
            $api->post('consulta-documentos', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@consultaDocumentos');
            // Descargar documentos
            $api->post('descargar', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@descargarDocumentos');
            // Descargar xml-eventos
            $api->post('descargar-xml-estado', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@descargarXmlEstado');
            // Agendar consulta de estado de coumentos en la DIAN
            $api->post('agendar-consulta-estado-dian', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@agendarConsultaEstadoDian');
            // Agendar acuse de recibo de documentos recibidos
            $api->post('agendar-acuse-recibo', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@agendarAcuseRecibo');
            // Agendar recibo bien de documentos recibidos
            $api->post('agendar-recibo-bien', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@agendarReciboBien');
            // Agendar aceptacion expresa de documentos recibidos
            $api->post('agendar-aceptacion-expresa', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@agendarAceptacionExpresa');
            // Agendar aceptación tácita de documentos recibidos
            $api->post('agendar-aceptacion-tacita', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@agendarAceptacionTacita');
            // Agendar rechazo de documentos recibidos
            $api->post('agendar-rechazo', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@agendarRechazo');
            // Crea un estado de VALIDACION
            $api->post('crear-estado-validacion', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@crearEstadoValidacionAccion');
            // Procesamiento de documentos manuales - Utilizado desde Frontend
            $api->post('documentos-manuales', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@documentosManuales');
            // Errores Documentos Anexos
            $api->post('documentos-anexos/lista-errores', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@getListaErroresDocumentosAnexos');
            // Errores Documentos Manuales
            $api->post('lista-errores', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@getListaErroresDocumentos');
            // Descargar Errores Documentos Manuales
            $api->post('lista-errores/excel', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@descargarListaErroresDocumentos');
            // Encuentra un documento en la BD
            $api->post('encontrar-documento', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@encontrarDocumento');
            // Cargar documentos anexos a un documento en el sistema
            $api->post('cargar-documentos-anexos', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@cargarDocumentosAnexos');
            // Eliminar documentos anexos
            $api->delete('eliminar-documentos-anexos', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@eliminarDocumentosAnexos');
            // Descargar Errores Documentos Anexos
            $api->post('documentos-anexos/lista-errores/excel', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@descargarListaErroresDocumentosAnexos');

            // Descargar documentos anexos
            $api->get('descargar-documentos-anexos/{ids}', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@descargarDocumentosAnexos');
            // Cambia estado a documentos
            $api->post('cambiar-estado-documentos','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@cambiarEstadoDocumentos');

            // Procesamiento de documentos manuales - Endpoint socializado con clientes conectados a través de la API
            $api->post('registrar-documentos-file', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@documentosManuales');
            // Procesamiento de documentos manuales - Endpoint socializado con clientes conectados a través de la API con un Json
            $api->post('registrar-documentos-json', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@documentosManualesJson');
            // Registro de eventos DIAN
            $api->post('registrar-evento', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@registrarEvento');
            // Obtiene los estados de un documento en específico
            $api->post('estados-documento', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@obtenerEstadosDocumento');
            // Obtiene los documentos anexos de un documento en específico
            $api->post('documentos-anexos', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@obtenerDocumentosAnexos');
            // Obtiene un documento no electrónico
            $api->post('consultar-data-documento-no-electronico', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@consultarDocumentoNoElectronico');
            // Asigna un grupo de trabajo a uno o varios documentos
            $api->post('asignar-grupo-trabajo', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@asignarGrupoTrabajoDocumentos');
            // Lista para tracking de validación documentos
            $api->post('lista-validacion-documentos','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@getListaValidacionDocumentos');
            // Lista para tracking de validación documentos
            $api->get('lista-usuarios-notificar-validacion','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@getListaUsuariosNotificarValidacion');
            // Generar interface para el registro de eventos DIAN
            $api->get('generar-interface-eventos','App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@generarInterfaceRegistroEventos');
            // Permite cargar la interface para el registro de eventos DIAN
            $api->post('cargar-registro-eventos', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController@cargarRegistroEventos');

            // Recurso Documentos
            $api->resource('documentos', 'App\Http\Modulos\Recepcion\Documentos\RepCabeceraDocumentosDaop\RepCabeceraDocumentoDaopController', ['except' => [
                    'getListaDocumentosRecibidos',
                    'buscarDocumentos',
                    'consultarDocumento',
                    'consultaDocumentos',
                    'descargarDocumentos',
                    'agendarAcuseRecibo',
                    'agendarAceptacionExpresa',
                    'agendarRechazo',
                    'documentosManuales',
                    'getListaErroresDocumentos',
                    'descargarListaErroresDocumentos',
                    'descargarDocumentosAnexos',
                    'cambiarEstadoDocumentos',
                    'crearEstadoValidacionAccion',
                    'crearEstadoValidacionValidado',
                    'getListaValidacionDocumentos',
                    'getListaUsuariosNotificarValidacion',
                    'generarInterfaceRegistroEventos',
                    'cargarRegistroEventos'
                ]]
            );

            // Correos Recibidos - Listar los correos recibidos
            $api->post('correos-recibidos/listar','App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManualController@getListaCorreosRecibidos');

            // Controlador tipo recurso para correos recibidos
            $api->resource('correos-recibidos', 'App\Http\Modulos\Recepcion\RPA\EtlEmailsProcesamientoManual\EtlEmailProcesamientoManualController', ['except' => [
                'getListaCorreosRecibidos'
                ]]
            );
        }
    );

    // Documento Soporte
    $api->group(
        [
            'middleware' => 'api.auth',
            'prefix' => 'documento-soporte'
        ],
        function ($api){
            // Generar excel documentos soporte
            $api->get('documentos/generar-interface-documento-soporte/{ofe_id}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@generarInterfaceDocumentosSoporte');
            // Generar excel documentos soporte
            $api->get('documentos/generar-interface-nota-credito-documento-soporte/{ofe_id}', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@generarInterfaceNotaCreditoDocumentosSoporte');
            // Errores documentos soporte
            $api->post('documentos/lista-errores', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@getListaErroresDocumentosSoporte');
            // Descargar errores documentos soporte
            $api->post('documentos/lista-errores/excel', 'App\Http\Modulos\Documentos\EtlCabeceraDocumentosDaop\EtlCabeceraDocumentoDaopController@descargarListaErroresDocumentosSoporte');
        }
    );

    // Facturacion
    $api->group(
        [
            'middleware' => ['api.auth'],
            'prefix' => 'facturacion'
        ],
        function ($api){
            // Agenda la generación de un reporte mensual de transacciones
            $api->post('reporte-mensual-transacciones', 'App\Http\Modulos\facturacion\ReporteMensualTransacciones\ReporteMensualTransaccionesController@agendarReporteMensualTransacciones');
        }
    );

    // Portales
    $api->group(
        [
            'middleware' => ['api.auth'],
            'prefix' => 'portales'            
        ],
        function ($api){
            $api->group(
                [
                    'prefix' => 'clientes'            
                ],
                function ($api){
                    // Agendar acuse de recibo de documentos recibidos por un cliente (adquirente)
                    $api->post('agendar-acuse-recibo', 'App\Http\Modulos\Portales\Clientes\ClienteController@agendarAcuseRecibo');
                    // Agendar aceptacion expresa de documentos recibidos por un cliente (adquirente)
                    $api->post('agendar-aceptacion-expresa', 'App\Http\Modulos\Portales\Clientes\ClienteController@agendarAceptacionExpresa');
                    // Agendar rechazo de documentos recibidos por un cliente (adquirente)
                    $api->post('agendar-rechazo', 'App\Http\Modulos\Portales\Clientes\ClienteController@agendarRechazo');
                    // Descarga de documentos anexos de documentos electrónicos recibidos por un cliente (adquirente)
                    $api->get('descargar-documentos-anexos/{ids}', 'App\Http\Modulos\Portales\Clientes\ClienteController@descargarDocumentosAnexos');
                    // Lista los documentos recibidos por un adquirente (cliente)
                    $api->post('lista-documentos-recibidos', 'App\Http\Modulos\Portales\Clientes\ClienteController@getListaDocumentosRecibidos');
                    // Descarga de documentos ()Xml, Pdf, AttachedDocument y ApplicationResponse
                    $api->post('descarga-documentos', 'App\Http\Modulos\Portales\Clientes\ClienteController@descargarDocumentos');
                }
            );

            $api->group(
                [
                    'prefix' => 'proveedores'            
                ],
                function ($api){
                    // Radicación de documentos
                    $api->post('radicar-documentos', 'App\Http\Modulos\Portales\Proveedores\ProveedorController@radicarDocumentos');
                    // Lista los documentos radicados por un proveedor
                    $api->post('lista-documentos-radicados', 'App\Http\Modulos\Portales\Proveedores\ProveedorController@getListaDocumentosRadicados');
                    // Carga de documentos anexos
                    $api->post('cargar-documentos-anexos', 'App\Http\Modulos\Portales\Proveedores\ProveedorController@cargarDocumentosAnexos');
                    // Descarga de documentos anexos
                    $api->get('descargar-documentos-anexos/{ids}', 'App\Http\Modulos\Portales\Proveedores\ProveedorController@descargarDocumentosAnexos');
                    // Eliminación de documentos anexos
                    $api->get('eliminar-documentos-anexos/{ids}', 'App\Http\Modulos\Portales\Proveedores\ProveedorController@eliminarDocumentosAnexos');
                    // Errores Radicación Documentos
                    $api->post('log-radicacion-documentos', 'App\Http\Modulos\Portales\Proveedores\ProveedorController@logRadicacionDocumentos');
                }
            );
        }
    );
});
