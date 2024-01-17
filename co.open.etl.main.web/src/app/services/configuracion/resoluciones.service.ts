import {Injectable} from '@angular/core';
import {BaseService} from '../core/base.service';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";

@Injectable()
export class ResolucionesService extends BaseService {
    // Usuario en línea
    public usuario: any;

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor(public authHttp: HttpClient) {
        super();
    }

    /**
     * Realiza la consulta al backend (microservicio DO) para ejecutar la consulta a la DIAN y obtener las resoluciones de facturación del OFE seleccionado.
     *
     * @param {object} parametros Parametros para la petición
     * @return {*}  {Observable<any>}
     * @memberof ResolucionesService
     */
    consultarResolucionDian(parametros: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiDOUrl}configuracion/resoluciones/consultar-resolucion-facturacion-dian`,
            this._parseObject(parametros),
            {headers: this.getHeaders()}
        );
    }

    /**
     * Permite la descarga en excel (Microservicio Main) de una consulta de resoluciones a la DIAN, complementada con información de openETL.
     *
     * @param params
     */
    descargarExcelResolucionesDian(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        
        return this.authHttp.post<Blob>(
            `${this.apiUrl}configuracion/resoluciones-facturacion/descargar-excel-consulta-dian`,
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