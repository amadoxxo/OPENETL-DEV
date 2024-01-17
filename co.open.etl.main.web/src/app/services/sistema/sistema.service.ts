import {Injectable, Inject, Optional} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class SistemaService extends BaseService{

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

    set setSlug(slug){
        this.slug  = slug;
    }

    /**
     * Obtiene un registro.
     * 
     * @param id Identificador del registro
     */
    get(id): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/${this.slug}/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Crea un nuevo registro.
     * 
     * @param values Valores a registrar
     */
    create(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/${this.slug}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Actualiza un registro.
     * 
     * @param values Valores a registrar
     * @param id Identificador del registro
     */
    update(values, id): Observable<any> {
        return this.authHttp.put(
            `${this.apiUrl}sistema/${this.slug}/${id}`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        );
    }

    /**
     * Obtiene una lista de registros.
     * 
     * @param params
     */
    listar(params: string): Observable<any> {
        const queryParams = new HttpParams({fromString: params});

        return this.authHttp.get(
            `${this.apiUrl}sistema/lista-${this.slug}`,
            {
                headers: this.getHeaders(),
                params: queryParams
            }
        );
    }

    /**
     * Obtiene registros para poblar select.
     * 
     * @param id Identificador del registro
     */
    listarSelect(ruta): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}sistema/${ruta}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Elimina un registro.
     * 
     * @param id
     */
    delete(id): Observable<any> {
        return this.authHttp.delete(
            `${this.apiUrl}sistema/${this.slug}/${id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de registros en lote.
     * 
     * @param values
     */
    cambiarEstado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/${this.slug}/cambiar-estado`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Cambia el estado (ACTIVO/INACTIVO) de registros en lote.
     * 
     * @param values
     */
    cambiarEstadoCodigos(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}sistema/${this.slug}/cambiar-estado`,
            values,
            { headers: this.getHeadersApplicationJSON() }
        )
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
     * @returns
     */
    descargarExcelGet(params): Observable<any> {
        const queryParams = new HttpParams({fromString: params});
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.get<Blob>(
            `${this.apiUrl}sistema/lista-${this.slug}`,
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
}
