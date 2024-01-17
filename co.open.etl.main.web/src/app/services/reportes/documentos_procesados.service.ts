import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosProcesadosService extends BaseService {

    // Usuario en línea
    public usuario: any;
    public slug   : string;

    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    set setSlug(slug){
        this.slug = slug;
    }

    /**
     * Envía la infomración necesaria para crear un agendamiento de reporte de Excel.
     * 
     * @param values Valores a registrar
     */
    agendarReporteExcel(params): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}${this.slug}/reportes/documentos-procesados/generar`,
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
            `${this.apiUrl}${this.slug}/reportes/documentos-procesados/listar-reportes-descargar`,
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
            `${this.apiUrl}${this.slug}/reportes/documentos-procesados/descargar-reporte`,
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
