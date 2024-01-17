import {Injectable, Inject, Optional} from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {Observable} from 'rxjs';
import {DocumentoNominaElectronica, DocumentoNominaElectronicaInteface} from '../../main/models/documento-nomina-electronica.model';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class NominaElectronicaService extends BaseService {

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
     * @param {*} valor Término a buscar
     * @param {*} valorEnviados Tracking documentos sin envío || enviados
     * @return {*}  {Observable<Documento[]>}
     * @memberof NominaElectronicaService
     */
    searchDocumentosNgSelect(valor, valorEnviados): Observable<DocumentoNominaElectronica[]> {
        return this.authHttp.get(
            `${this.apiUrl}nomina-electronica/documentos/cdn_lote/valor/${valor}/filtro/basico?enviados=${valorEnviados}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: DocumentoNominaElectronicaInteface) => rsp.data));
    }

    /**
     * Permite consultar los estados del documento.
     *
     * @param {*} valor Información a buscar
     * @return {*} 
     * @memberof NominaElectronicaService
     */
    obtenerEstadosDocumento(valor): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}nomina-electronica/documentos/estados-documento`,
            this._parseObject(valor),
            { headers: this.getHeaders() }
        )
    }
}
