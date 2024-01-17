import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { HttpInterceptor, HttpHandler, HttpRequest, HttpEvent, HttpResponse, HttpErrorResponse } from '@angular/common/http';
import {Observable} from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';
declare var swal: any;

@Injectable()
export class DownloadFileErrorInterceptor implements HttpInterceptor {
    private apiUrl = environment.API_ENDPOINT;
    
    constructor(
        private router: Router
    ) {}

    /**
     * Permite interceptar los request a la ruta de descarga de documentos sin envío y enviados
     * y en caso de un response de error interceptar la respuesta y mostrar el mensaje de error al usuario
     *
     * @param HttpRequest request
     * @param HttpHandler next
     * @return Observable<HttpEvent<any>>
     * @memberof DownloadFileInterceptor
     */
    intercept(
        request: HttpRequest<any>,
        next: HttpHandler
    ): Observable<HttpEvent<any>> {
        // Si la ruta del request no corresponde con ninguna de las rutas de descarga de 
        // documentos o adjuntos continúa con el siguiente interceptor
        if (
            request.url.indexOf(`obtener-documentos`) === -1 &&
            request.url.indexOf(`descargar-documentos-adquirente`) === -1 &&
            request.url.indexOf(`descargar-adjuntos`) === -1 &&
            request.url.indexOf(`descargar-adjuntos-documentos-adquirente`) === -1 &&
            request.url.indexOf(`descargar`) === -1 &&
            request.url.indexOf(`ver-representacion-grafica-documento`) === -1 
        ) {
            return next.handle(request); // continúa con el siguiente
        }

        // Si la ruta coincide   
        return next.handle(request).pipe(
            map((event: HttpEvent<HttpResponse<any>>) => {
            return event;
        }),
        catchError(error => {
            // Si se produce un error obtenemos la cabecera de error configurada en el api para poder ofrecer 
            // un mensaje más específico al usuario
            if (error instanceof HttpErrorResponse) {
                if (error.headers.get('X-Error-Message') || error.headers.get('x-error-message')) {
                    let txtError = error.headers.get('X-Error-Message') ? error.headers.get('X-Error-Message') : error.headers.get('x-error-message');
                    swal({
                        html: '<h5>Error en descarga</h5><p>' + txtError + '</p>',
                        type: 'error',
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonClass: 'btn btn-danger',
                        cancelButtonText: 'Cerrar',
                        buttonsStyling: false
                    }).catch(swal.noop);
                }
                return throwError(error);
            }
        })
        );
    }
}
