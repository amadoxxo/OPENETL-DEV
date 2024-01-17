import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable} from 'rxjs';
import {tap, map} from 'rxjs/operators';
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosManualesService extends BaseService {

    constructor(private http: HttpClient) {
        super();
    }

    /**
     * Procesa documentos en lote de documentos manuales en recepción
     * @param payload
     */
    procesarDocumentos(payload): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.http.post(
            `${this.apiUrl}recepcion/documentos/documentos-manuales`,
            payload,
            {headers: headers}
        );
    }

    /**
     * Realiza la petición para generar la interface del registro de eventos.
     *
     * @return {*} 
     * @memberof DocumentosManualesService
     */
    generarInterfaceFacturas(): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.http.get<Blob>(
            `${this.apiUrl}recepcion/documentos/generar-interface-eventos`,
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
     * Permite la carga del excel para el registro de eventos.
     *
     * @param {*} fileToUpload Archivo para registrar los eventos
     * @return {*}  {Observable<any>}
     * @memberof DocumentosManualesService
     */
    cargarRegistroEventos(fileToUpload: any): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('archivo', fileToUpload);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.http.post(
            `${this.apiUrl}recepcion/documentos/cargar-registro-eventos`,
            INPUT,
            { headers: headers }
        );
    }
}
