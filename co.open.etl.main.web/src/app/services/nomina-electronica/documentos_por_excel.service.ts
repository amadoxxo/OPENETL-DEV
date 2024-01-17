import {Injectable, Inject, Optional} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosPorExcelService extends BaseService {

    // Usuario en línea
    public usuario: any;
    public slug: string;

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    generarInterfaceNomina(emp_id) {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}nomina-electronica/documentos/generar-interface-nomina/${emp_id}`,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }

    generarInterfaceEliminar(emp_id) {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}nomina-electronica/documentos/generar-interface-eliminar/${emp_id}`,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }

    /**
     * Permite la carga de Documentos mediante Excel (Nomina / Novedad / Ajuste / Eliminar).
     *
     * @param {int} emp_id Id del Empleador
     * @param {*} fileToUpload any Documento manual a cargar
     * @param {string} evento string Tipo de archivo a cargar
     * @returns {Observable<any>}
     */
    cargarDocumentosPorExcel(emp_id, fileToUpload: any, evento: string): Observable<any> {
        let slug;
        const INPUT = new FormData();
        INPUT.append('archivo', fileToUpload);
        INPUT.append('emp_id', emp_id);

        const tipoProceso = evento === 'subir_nomina' ? 'nomina' : 'eliminar;'
        INPUT.append('tipo_proceso', tipoProceso);
        slug = 'nomina-electronica/documentos/cargar-excel';

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiDIUrl}${slug}`,
            INPUT,
            { headers: headers }
        );
    }

    /**
     * Descarga la consulta de registros en un archivo de Excel.
     * 
     * @param buscar
     */
    descargarExcelPost(buscar: string): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('buscar', buscar);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}descargar-excel-${this.slug}`,
            INPUT,
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
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param {object} params - parámetros de la petición
     * @returns {Observable<any>}
     */
    descargarExcelGet(params): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}configuracion/lista-${this.slug}`,
            {
                headers: headers,
                params: queryParams,
                responseType: 'blob' as 'json',
                observe: 'response',
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }

    /**
     * Obtiene el listado de Errores para el tracking.
     * 
     */
    getListaLogErrores(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Accept', 'application/json');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        headers.set('Pragma', 'no-cache');
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/lista-errores`,
            params,
            {headers: headers}
        );
    }

    descargarExcel(params): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}nomina-electronica/documentos/lista-errores/excel`,
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
