import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class NotificacionDocumentosService extends BaseService {

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
     * Envía la información necesaria para crear un agendamiento del reporte notificación SMTP servidor openETL en Excel.
     * 
     * @param values Valores a registrar
     */
    agendarReporteSmtpExcel(params): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/reportes/eventos-notificacion/generar-smtp`,
            this._parseObject(params),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la lista de documentos que el usuario autenticado puede descargar para el reporte notificación SMTP servidor openETL.
     * 
     * @param values Valores a registrar
     */
     listarReportesSmtpDescargar(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}emision/reportes/eventos-notificacion/listar-reportes-smtp-descargar`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

     /**
     * Permite la descarga en excel de una consulta en pantalla del reporte notificación SMTP servidor openETL.
     *
     * @param params
     */
    descargarSmtpExcel(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}emision/reportes/eventos-notificacion/descargar-reporte-smtp`,
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
     * Envía la información necesaria para crear un agendamiento del reporte notificación por plataforma de servicio en Excel.
     * 
     * @param values Valores a registrar
     */
     agendarReportePlataformaExcel(params): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/reportes/eventos-notificacion/generar-plataforma`,
            this._parseObject(params),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la lista de documentos que el usuario autenticado puede descargar para el reporte notificación por plataforma de servicio.
     * 
     * @param values Valores a registrar
     */
     listarReportesPlataformaDescargar(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}emision/reportes/eventos-notificacion/listar-reportes-plataforma-descargar`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

     /**
     * Permite la descarga en excel de una consulta en pantalla del reporte notificación por plataforma de servicio.
     *
     * @param params
     */
    descargarPlataformaExcel(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}emision/reportes/eventos-notificacion/descargar-reporte-plataforma`,
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
