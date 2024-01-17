<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware para Particionamiento en Recepción.
 */
class RecepcionParticionamiento {
    /**
     * Contiene la lista de métodos de particionamiento con las rutas correspondientes.
     *
     * @var array
     */
    private $arrRedirectAction = [
        'getListaDocumentosRecibidos' => [
            'uses'       => '\App\Http\Modulos\Recepcion\Particionamiento\ParticionamientoRecepcionController@getListaDocumentosRecibidos',
            'controller' => '\App\Http\Modulos\Recepcion\Particionamiento\ParticionamientoRecepcionController@getListaDocumentosRecibidos'
        ],
        'getListaValidacionDocumentos' => [
            'uses'       => '\App\Http\Modulos\Recepcion\Particionamiento\ParticionamientoRecepcionValidacionDocumentoController@getListaValidacionDocumentos',
            'controller' => '\App\Http\Modulos\Recepcion\Particionamiento\ParticionamientoRecepcionValidacionDocumentoController@getListaValidacionDocumentos'
        ]
    ];

    /**
     * Modifica la acción de una ruta validando si la base de datos del usuario autenticada esta particionada para hacer uso de la lógica correspondiente.
     * 
     * @param Request $request Parámetros de la petición
     * @param Closure $next Evento del closure
     * @return $next
     */
    public function handle(Request $request, Closure $next) {
        $user        = auth()->user();
        $baseDatos   = (!empty($user->bdd_id_rg)) ? $user->getBaseDatosRg : $user->getBaseDatos;
        $route       = $request->route();
        $routeAction = $request->route()->getAction();

        if (in_array($route->getActionMethod(), array_keys($this->arrRedirectAction)) && $baseDatos->bdd_aplica_particionamiento_recepcion == 'SI') {
            $routeAction = array_merge($route->getAction(), $this->arrRedirectAction[$route->getActionMethod()]);

            $route->setAction($routeAction);
            $route->controller = false;
        }

        return $next($request);
    }
}
