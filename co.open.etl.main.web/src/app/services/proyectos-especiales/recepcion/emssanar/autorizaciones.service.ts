import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BaseService } from '../../../core/base.service';
import { Observable } from 'rxjs';

@Injectable({providedIn: 'root'})
export class AutorizacionesService extends BaseService {

    /**
     * Crea una instancia de AutorizacionesService.
     * @param {HttpClient} authHttp
     * @memberof AutorizacionesService
     */
    constructor (public authHttp: HttpClient) {
        super();
    }


    /**
     *  Realiza la consulta de un documento en el componente de Autorización Etapas.
     *
     * @param {object} params
     * @return {Observable<any>}
     * @memberof AutorizacionesService
     */
    consultarDocumento(params: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/autorizaciones/consultar-documento`,
            params,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Devuelve el documento obtenido a una anterior etapa.
     *
     * @param {object} params
     * @return {Observable<any>}
     * @memberof AutorizacionesService
     */
    autorizarEtapa(params: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}proyectos-especiales/recepcion/emssanar/autorizaciones/autorizar-etapa`,
            params,
            { headers: this.getHeadersApplicationJSON() }
        );
    }
}