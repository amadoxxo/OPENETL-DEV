import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {Documento, DocumentoInteface} from '../../main/models/documento.model';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosSinEnvioService extends BaseService{

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
     * Obtiene una lista de documentos.
     * 
     * @param params
     */
    // listar(params: string): Observable<any> {
    //     const queryParams = new HttpParams({fromString: params});

    //     return this.authHttp.get(
    //         `${this.apiUrl}emision/lista-documentos`,
    //         {
    //             headers: this.getHeaders(),
    //             params: queryParams
    //         }
    //     );
    // }

    /**
     * Obtiene una lista de documentos.
     * 
     * @param params
     */
    listar(params): Observable<any> {

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}emision/lista-documentos`,
            params,
            {headers: headers}
        );
    }

    /**
     * Realiza una búsqueda predictiva de documentos desde un NgSelect.
     * 
     *  @param valor Término a buscar
     */
    searchDocumentosNgSelect(valor): Observable<Documento[]> {
        return this.authHttp.get(
            `${this.apiUrl}emision/documentos/cdo_lote/valor/${valor}/filtro/basico`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: DocumentoInteface) => rsp.data));
    }

    /**
     * Realiza la descarga de documentos seleccionados.
     * 
     *  @param tipos_documentos string Tipos de Documentos a descargar
     *  @param cdo_ids string Ids de los Documentos a descargar
     *  @param ofe_id number Id del Oferente
     */
    descargarDocumentos(tipos_documentos: string, cdo_ids: string, ofe_id): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('tipos_documentos', tipos_documentos);
        INPUT.append('cdo_ids', cdo_ids);
        INPUT.append('ofe_id', ofe_id);
        INPUT.append('documento_enviado', 'NO');

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}emision/documentos/descargar`,
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
            }),
            catchError(err => {
                let error = err.message ? err.message : 'Error en la descarga del Excel';
                return throwError(() => new Error(error));
            })
        );
    }

    /**
     * Realiza el envío de los documentos seleccionados.
     * 
     * @param values Object Documentos a enviar
     */
    enviarDocumentos(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/enviar`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite cambiar el estado de documentos enviados
     * @param cdoIds
     */
    cambiarEstadoDocumentosSinEnvio(cdoIds: any) {
        return this.cambiarEstadoDocumentos(this.authHttp, cdoIds, 'sin-envio');
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
            `${this.apiUrl}emision/descargar-lista-documentos-sin-envio`,
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

    /**
     * Consulta información adicional de un documento que esta siendo editado.
     * 
     * Aplica para DHL Express, documentos del proceso Pickup Cash
     * 
     * @param ofeId
     * @param cdoId
     * @param pickupcash
     */
    consultarDataDocumentoModificar(ofeId: any, cdoId: any, pickupcash: any): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('ofe_id', ofeId);
        INPUT.append('cdo_id', cdoId);
        INPUT.append('pickupcash', pickupcash);
        return this.authHttp.post(
            `${this.apiUrl}emision/modificar-documento/consultar-data`,
            INPUT,
            { headers: this.getHeadersApplicationJSON() }
        )
    }

    /**
     * Envia el json de un documento que ha sido modificado
     * 
     * Aplica para DHL Express, documentos del proceso Pickup Cash
     * 
     * @param jsonDocumento
     */
    enviarDocumentoModificado(jsonDocumento): Observable<any> {
        return this.authHttp.post(
            `${this.apiDIUrl}registrar-documentos`,
            jsonDocumento,
            { headers: this.getHeadersApplicationJSON() }
        )
    }
}
