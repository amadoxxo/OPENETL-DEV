import { Injectable } from '@angular/core';
import { BaseService } from '../core/base.service';
import { map, Observable } from 'rxjs';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';

@Injectable()
export class ReporteDependenciasService extends BaseService {
    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Genera un reporte de dependencias para procesar en background.
     *
     * @param {*} params
     * @return {Observable<any>}
     * @memberof ReporteDependenciasService
     */
    agendarReporteExcel(params): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/reportes/reporte-dependencias/generar`,
            this._parseObject(params),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la lista de reportes de dependencias agendados para generarse.
     * 
     * @param values Parámetros de filtrado de información
     * @memberof ReporteDependenciasService
     */
    listarReportesDescargar(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}recepcion/reportes/reporte-dependencias/listar`,
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
     * @memberof ReporteDependenciasService
     */
    descargarExcel(params): Observable<any> {
        let headers = new HttpHeaders()
            .set('Content-type', 'application/json')
            .set('X-Requested-Whith', 'XMLHttpRequest')
            .set('Cache-Control', 'no-cache, no-store, must-revalidate')
            .set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}recepcion/reportes/reporte-dependencias/descargar`,
            params,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response'
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }
}
