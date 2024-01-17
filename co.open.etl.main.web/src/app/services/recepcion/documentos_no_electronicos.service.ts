import { Injectable, Inject, Optional } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { BaseService } from '../core/base.service';

@Injectable()
export class DocumentosNoElectronicosService extends BaseService {

    /**
     * Constructor.
     * 
     * @param authHttp Cliente http
     */
    constructor (public authHttp: HttpClient) {
        super();
    }

    /**
     * Envía el json de un documento no electrónico para ser creado o actualizado
     *
     * @param {*} jsonDocumento Json con la información del documento
     * @return {*} {Observable<any>}
     * @memberof DocumentosNoElectronicosService
     */
    enviarDocumentoNoElectronico(jsonDocumento): Observable<any> {
        return this.authHttp.post(
            `${this.apiDIUrl}recepcion/registrar-documento-no-electronico`,
            jsonDocumento,
            { headers: this.getHeadersApplicationJSON() }
        )
    }

    /**
     * Obtiene la información de un documento no electrónico en específico.
     *
     * @param {*} values Valores para realizar la búsqueda
     * @return {*}  {Observable<any>}
     * @memberof DocumentosNoElectronicosService
     */
    obtenerDocumentoNoElectronicoSeleccionado(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/consultar-data-documento-no-electronico`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }
}
