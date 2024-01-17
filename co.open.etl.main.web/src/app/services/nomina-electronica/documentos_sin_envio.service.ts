import {Injectable, Inject, Optional} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosSinEnvioService extends BaseService{

    // Usuario en línea
    public usuario: any;

    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene una lista de documentos.
     *
     * @param {*} params Parámetros de búsqueda
     * @return {*}  {Observable<any>}
     * @memberof DocumentosSinEnvioService
     */
    listar(params): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}nomina-electronica/lista-documentos-sin-envio`,
            params,
            {headers: headers}
        );
    }

    /**
     * Realiza el envío de los documentos seleccionados.
     *
     * @param {*} values Object Documentos a enviar
     * @return {*}  {Observable<any>}
     * @memberof DocumentosSinEnvioService
     */
    enviarDocumentos(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}nomina-electronica/documentos/enviar`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param {*} params Parámetros de búsqueda
     * @return {*}  {Observable<any>}
     * @memberof DocumentosSinEnvioService
     */
    descargarExcel(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}nomina-electronica/descargar-lista-documentos-sin-envio`,
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

    /**
     * Permite cambiar el estado de documentos de nómina electrónica.
     *
     * @param {*} cdnIds Ids de los Documentos a cambiar estado
     * @return {*} 
     * @memberof DocumentosSinEnvioService
     */
    cambiarEstadoDocumentosSinEnvio(cdnIds: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}nomina-electronica/documentos/cambiar-estado-documentos`,
            {
                cdnIds: cdnIds,
                tipoEnvio: "sin-envio"
            },
            { headers: this.getHeadersApplicationJSON() }
        )
    }

    /**
     * Realiza la descarga de documentos seleccionados.
     *
     * @param {string} tipos_documentos Tipos de Documentos a descargar
     * @param {string} cdn_ids Ids de los Documentos a descargar
     * @param {*} emp_id Id del Empleador
     * @return {*}  {Observable<any>}
     * @memberof DocumentosSinEnvioService
     */
    descargarDocumentos(tipos_documentos: string, cdn_ids: string, emp_id): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('tipos_documentos', tipos_documentos);
        INPUT.append('cdn_ids', cdn_ids);
        INPUT.append('emp_id', emp_id);
        INPUT.append('documento_enviado', 'NO');

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}nomina-electronica/documentos/descargar`,
            INPUT,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        )
        .pipe(
            map((response) => {
                if (response.status === 200)
                    this.downloadFile(response);
            }),
            catchError(err => {
                let error = err.message ? err.message : 'Error en la descarga de documentoa';
                return throwError(() => new Error(error));
            })
        );
    }
}
