import {Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {Observable} from 'rxjs';
import {Documento, DocumentoInteface} from '../../main/models/documento.model';
import {map} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosService extends BaseService{

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
     * Realiza una búsqueda predictiva de documentos desde un NgSelect.
     * 
     *  @param valor Término a buscar
     */
    searchDocumentosNgSelect(valor, valorEnviados): Observable<Documento[]> {
        return this.authHttp.get(
            `${this.apiUrl}emision/documentos/cdo_lote/valor/${valor}/filtro/basico?enviados=${valorEnviados}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: DocumentoInteface) => rsp.data));
    }

    /**
     * Permite consultar los estados del documento.
     *
     * @param {*} valor Información a buscar
     * @return {*} 
     * @memberof DocumentosService
     */
    obtenerEstadosDocumento(valor): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/estados-documento`,
            this._parseObject(valor),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite consultar los documentos anexos del documento.
     *
     * @param {*} valor Información a buscar
     * @return {*} 
     * @memberof DocumentosService
     */
    obtenerDocumentosAnexos(valor): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/documentos-anexos`,
            this._parseObject(valor),
            { headers: this.getHeaders() }
        )
    }
}
