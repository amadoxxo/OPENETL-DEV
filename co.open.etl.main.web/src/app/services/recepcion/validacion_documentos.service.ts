import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {catchError, map, Observable, throwError} from 'rxjs';
import {BaseService} from '../core/base.service';

@Injectable()
export class ValidacionDocumentosService extends BaseService {
    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene una lista de usuarios relacionados con la base de datos del usuario autenticado para enviar la notificación de documento validado.
     *
     * @return {*}  {Observable<any>}
     * @memberof ValidacionDocumentosService
     */
    listaUsuariosNotificarValidacion(): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}recepcion/documentos/lista-usuarios-notificar-validacion`,
            {headers: this.getHeaders()}
        );
    }

    /**
     * Envía la información necesaria para crear un agendamiento de reporte de Excel.
     *
     * @param {*} params
     * @return {*}  {Observable<any>}
     * @memberof ValidacionDocumentosService
     */
    agendarReporteExcel(params): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/reportes/agendar-log-validacion-documentos`,
            this._parseObject(params),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la lista de reportes que el usuario autenticado puede descargar.
     * 
     * @param values Parámetros de filtrado de información
     * @memberof ValidacionDocumentosService
     */
    listarReportesDescargar(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}recepcion/reportes/log-validacion-documentos/listar`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Permite la descarga de un reporte previamente agendado.
     *
     * @param params Parámetros de la petición
     * @memberof ValidacionDocumentosService
     */
    descargarExcel(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}recepcion/reportes/log-validacion-documentos/descargar`,
            params,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response'
            }
        ).pipe(
            map(response => this.downloadFile(response)),
            catchError(err => {
            let error = err.message ? err.message : 'Error en la descarga del Excel';
                return throwError(() => new Error(error));
            })
        );
    }
}
