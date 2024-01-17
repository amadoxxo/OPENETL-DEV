import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable} from 'rxjs';
import {map} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosAnexosService extends BaseService {

    // Usuario en línea
    public usuario: any;

    /**
     * Constructor de DocumentosAnexosService.
     * 
     * @param authHttp Cliente http
     * @memberof DocumentosAnexosService
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Envía la petición al backend para la descarga de documentos anexos.
     * 
     * @param {string} ids Ids de los documentos anexos a descargar
     * @param {string} proceso Proceso al cual pertenece el documento electrónico (emision, recepcion)
     * @param {int} cdo_id Id del documento con el cual están relacionados los anexos, obligatorio para emisión
     * @returns {Observable<any>} 
     * @memberof DocumentosAnexosService
     */
    descargarDocumentosAnexos(ids, proceso, cdo_id = 0): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        let params = null;
        if(proceso == 'emision' || proceso == 'recepcion')
            params = cdo_id + '|' + ids;
        else
            params = ids

        return this.authHttp.get<Blob>(
            `${this.apiUrl}${proceso}/documentos/descargar-documentos-anexos/${params}`,
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

    /**
     * Envía la petición al backend para eliminar documentos anexos.
     * 
     * @param {string} ids Ids de los documentos anexos a descargar
     * @param {string} proceso Proceso al cual pertenece el documento electrónico (emision, recepcion)
     * @param {int} cdo_id Id del documento con el cual están relacionados los anexos, obligatorio para emisión
     * @returns {Observable<any>} 
     * @memberof DocumentosAnexosService
     */
    eliminarDocumentosAnexos(ids, proceso, cdo_id = 0): Observable<any> {
        let params;

        if(proceso == 'emision' || proceso == 'recepcion')
            params = {
                cdo_id: cdo_id,
                ids: ids
            }
        else
            params = {
                ids: ids
            }
            
        return this.authHttp.delete(
            `${this.apiUrl}${proceso}/documentos/eliminar-documentos-anexos`,
            {
                headers: this.getHeaders(),
                params: params
            }
        )
    }

    /**
     * Envia la petición la backend para la carga de los documentos anexos.
     * 
     * @param {any} descripciones 
     * @param {any} documentosAnexos 
     * @param {any} cdo_id 
     * @returns {Observable<any>} 
     * @memberof DocumentosAnexosService
     */
    cargarDocumentosAnexos(descripciones, documentosAnexos, cdo_id): Observable<any> {
        const INPUT = new FormData();
        const totalDocumentosAnexos = documentosAnexos.length;
        let contadorDocumentosAnexos = 1;
        documentosAnexos.forEach(adjunto => {
            INPUT.append('archivo' + contadorDocumentosAnexos, adjunto);
            contadorDocumentosAnexos++;
        });
        descripciones.forEach((descripcion, index) => {
            INPUT.append('descripcion' + (index + 1), descripcion);
        });
        INPUT.append('totalDocumentosAnexos', totalDocumentosAnexos);
        INPUT.append('cdo_id', cdo_id);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/cargar-documentos-anexos`,
            INPUT,
            { headers: headers }
        )
    }

    /**
     * Busca un documento en la BD
     * 
     * @param object values Objeto con valores de Ofe, Prefijo, Consecutivo y fechas
     * @returns Observable
     * @memberof DocumentosAnexosService
     */
    encontrarDocumento(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/encontrar-documento`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }
}
