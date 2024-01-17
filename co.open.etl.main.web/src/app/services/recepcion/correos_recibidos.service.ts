import {Injectable, Inject, Optional} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {Documento, DocumentoInteface} from '../../main/models/documento.model';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class CorreosRecibidosService extends BaseService {

    // Usuario en línea
    public usuario: any;

    /**
     * Constructor de CorreosRecibidosService.
     * 
     * @param {HttpClient} authHttp
     * @memberof CorreosRecibidosService
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Obtiene una lista de documentos.
     *
     * @param {*} params
     * @return {*}  {Observable<any>}
     * @memberof CorreosRecibidosService
     */
    listar(params): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/correos-recibidos/listar`,
            params,
            {headers: headers}
        );
    }

    /**
     * Asocia los anexos de un correo recibido con un documento electrónico.
     *
     * @param  {*} epm_id Id del registro del correo procesado
     * @param  {*} cdo_id Id del documentro electrónico
     * @return {*} {Observable<any>}
     * @memberof CorreosRecibidosService
     */
    asociarAnexoCorreoDocumento(epm_id, cdo_id): Observable<any> {
        let params = {
            epm_id: epm_id,
            cdo_id: cdo_id
        }
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiDIUrl}recepcion/documentos/documentos-anexos/asociar`,
            params,
            {headers: headers}
        );
    }

    /**
     * Permite consultar los estados del documento.
     *
     * @param  {*} data Información a buscar
     * @return {*} {Observable<any>}
     * @memberof DocumentosRecibidosService
     */
    obtenerCorreoRecibido(data): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}recepcion/documentos/correos-recibidos/${data.epm_id}`,
            { headers: this.getHeaders() }
        );
    }

    /**
     * Realiza la descarga de documentos seleccionados.
     * 
     *  @param epm_id number Id del Oferente
     */
    descargarAnexosCorreo(epm_id: string): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('epm_id', epm_id);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiDIUrl}recepcion/correos-recibidos/descargar`,
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
            })
        );
    }
}
