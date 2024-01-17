import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class ReportesBackgroundService extends BaseService {

    // Usuario en línea
    public usuario: any;

    /**
     * Constructor de ReportesBackgroundService.
     * 
     * @param {HttpClient} authHttp
     * @memberof ReportesBackgroundService
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Envía la petición para crear un agendamiento de reporte de Excel.
     *
     * @param {*} params Parametros de busqueda
     * @return {*}  {Observable<any>}
     * @memberof ReportesBackgroundService
     */
    agendarReporteExcel(params: any): Observable<any> {
        let modulo = '';
        switch (params.tipo) {
            case 'emision-enviados':
            case 'emision-no-enviados':
                modulo = 'emision';
                break;

            case 'nomina-enviados':
            case 'nomina-no-enviados':
                modulo = 'nomina-electronica';
                break;

            case 'radian-documentos':
                modulo = 'radian';
                break;

            case 'adquirente':
            case 'autorizado':
            case 'responsable':
            case 'vendedor':
                modulo = 'configuracion/adquirentes';
                break;

            default:
                modulo = 'recepcion';
                break;
        }
        return this.authHttp.post(
            `${this.apiUrl}${modulo}/reportes/background/generar`,
            this._parseObject(params),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene la lista de los reportes generados en background.
     *
     * @param {*} params Parametros de busqueda
     * @param {string} modulo Modulo a consultar
     * @return {*}  {Observable<any>}
     * @memberof ReportesBackgroundService
     */
    listarReportesDescargar(params: any, modulo: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        return this.authHttp.get(
            `${this.apiUrl}${modulo}/reportes/background/listar-reportes-descargar`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param {*} params Parametros de busqueda
     * @param {*} modulo Modulo a consultar
     * @return {*}  {Observable<any>}
     * @memberof ReportesBackgroundService
     */
    descargarExcel(params: any, modulo: string): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}${modulo}/reportes/background/descargar-reporte-background`,
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
