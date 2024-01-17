import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DhlExpressService extends BaseService {

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
     * Envía la infomración necesaria para crear un agendamiento de reporte de Excel.
     * 
     * @param values Valores a registrar
     */
    agendarReporteExcel(params): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/reportes/dhl-express`,
            this._parseObject(params),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la lista de documentos que el usuario autenticado puede descargar.
     * 
     * @param values Valores a registrar
     */
     listarReportesDescargar(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}emision/reportes/listar-reportes-descargar-dhl-express`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

     /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param params
     */
    descargarExcel(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}emision/reportes/descargar-reporte-dhl-express`,
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
