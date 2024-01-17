import {Observable} from 'rxjs';
import {map} from "rxjs/operators";
import {Injectable} from '@angular/core';
import {BaseService} from '../core/base.service';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Documento, DocumentoInteface} from '../../main/models/documento.model';

@Injectable()
export class RadianService extends BaseService {
    // Usuario en línea
    public usuario: any;

    /**
     * Constructor
     * @param authHttp Cliente http
     */
    constructor(public authHttp: HttpClient) {
        super();
    }

    /**
     * Actualiza un ACTOR.
     *
     * @param {*} act_identificacion Identificación del ACTOR
     * @param {*} values Valores a registrar
     * @return {*} {Observable<any>}
     * @memberof RadianService
     */
    update(act_identificacion, values): Observable<any> {
        const INPUT = new FormData();

        for (const prop in values) {
            INPUT.append(prop, values[prop]);
        }

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');
        return this.authHttp.put(
            `${this.apiUrl}configuracion/radian/actor/${act_identificacion}`,
            INPUT,
            {headers}
        );
    }

    /**
     * Guarda un ACTOR.
     * 
     * @param values Valores a registrar
     * @return {*} {Observable<any>}
     * @memberof RadianService
     */
    create(values): Observable<any> {
        const INPUT = new FormData();

        for (const prop in values) {
            INPUT.append(prop, values[prop]);
        }

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}configuracion/radian/actor`,
            INPUT,
            {headers}
        );
    }

    /**
     * Consulta un actor con sus roles asignados.
     * 
     * @return {*} {Observable<any>}
     * @memberof RadianService
     */
    listaActoresRoles(): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.get(
            `${this.apiUrl}radian/registro-documentos`,
            {headers}
        );
    }

    /**
     * Agenda el estado RADEDI en DI para el registro de un documento Radian
     *
     * @param {*} params Parámetros recibidos desde el componente
     * @return {*}  {Observable<any>}
     * @memberof RadianService
     */
    agendarEstadoRadEdi(params: any): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiDIUrl}radian/agendar-estado-documento-radian`,
            params,
            {headers}
        )
    }

    /**
     * Realiza una búsqueda predictiva de documentos desde un NgSelect.
     *
     * @param {string} campo_buscar Término a buscar
     * @param {string} valor Término a buscar
     * @param {string} filtro Tipo de filtro a aplicar
     * @returns {Observable<Documento[]>}
     * @memberof RadianService
     */
    searchDocumentosNgSelect(campo_buscar: string, valor: string, filtro = ''): Observable<Documento[]> {
        return this.authHttp.get(
            `${this.apiUrl}radian/documentos/${campo_buscar}/valor/${valor}/filtro/${filtro}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: DocumentoInteface) => rsp.data));
    }

    /**
     * Obtiene una lista de documentos de Radian.
     * 
     * @param {object} params Objeto que llega del componente para consultar
     * @returns {Observable<any>}
     * @memberof RadianService
     */
    listar(params: object): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}radian/documentos/lista-documentos-recibidos`,
            params,
            {headers: headers}
        );
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param {object} params Objeto con propiedades para descargar el excel de Documentos Radian
     * @returns {Observable<any>}
     * @memberof RadianService
     */
    descargarExcel(params: object): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');
        return this.authHttp.post<Blob>(
            `${this.apiUrl}radian/documentos/lista-documentos-recibidos`,
            params,
            {
                headers: headers,
                responseType: 'blob' as 'json',
                observe: 'response'
            }
        ).pipe(
            map(response => this.downloadFile(response))
        );
    }

    /**
     * Permite consultar los estados del documento.
     *
     * @param {object} valor Información a buscar
     * @return {Observable<any>}
     * @memberof RadianService
     */
    obtenerEstadosDocumento(valor: Object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}radian/documentos/estados-documento`,
            this._parseObject(valor),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite consultar los eventos que tiene asociados el ACTOR.
     *
     * @param {string} identificacion Identificación del Actor a buscar
     * @return {Observable<any>}
     * @memberof RadianService
     */
    obtenerEventosActor(identificacion: string): Observable<any> {
        return this.authHttp.get(
            `${this.apiUrl}radian/documentos/eventos-actor/${identificacion}`,
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite consultar el estado en la DIAN de los documentos seleccionados.
     * 
     * @param {number} act_id Id del Actor
     * @param {string} cdo_ids Ids de los Documentos a consultar en la DIAN
     * @return {Observable<any>}
     * @memberof RadianService
     */
    agendarConsultaEstadoDianDocumentosRadian(act_id: number, cdo_ids: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}radian/documentos/agendar-consulta-estado-dian`,
            this._parseObject({actId: act_id, cdoIds: cdo_ids}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar el Acuse del Recibo de los documentos seleccionados.
     * 
     * @param {number} act_id   Id del Actor
     * @param {string} cdo_ids  Ids de los Documentos cuyo Acuse de Recibo se realizará
     * @param {object} camposAcuseRadian Objeto con la información de la modal de acuse para Radian
     * @return {Observable<any>}
     * @memberof RadianService
     */
    agendarAcuseReciboDocumentosRadian(act_id: number, cdo_ids: string, camposAcuseRadian: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}radian/documentos/agendar-acuse-recibo`,
            this._parseObject({actId: act_id, cdoIds: cdo_ids, camposAcuseRadian: JSON.stringify(camposAcuseRadian) }),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar el recibo del bien de los documentos seleccionados.
     * 
     * @param {number} act_id   Id del Actor
     * @param {string} cdo_ids  Ids de los Documentos cuyo Recibo del Bien, se realizará
     * @param {object} camposAcuseRadian Objeto con la información de la modal de Recibo del Bien para Radian
     * @return {Observable<any>}
     * @memberof RadianService
     */
    agendarReciboBienDocumentosRadian(act_id: number, cdo_ids: string, camposAcuseRadian: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}radian/documentos/agendar-recibo-bien`,
            this._parseObject({actId: act_id, cdoIds: cdo_ids, camposAcuseRadian: JSON.stringify(camposAcuseRadian) }),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar la aceptación tácita de los documentos seleccionados.
     * 
     * @param {number} act_id      Id del Actor
     * @param {string} cdo_ids     Ids de los Documentos cuya Aceptación Expresa se realizará
     * @param {string} observacion Observación de los Documentos cuya Aceptación Expresa se realizará
     * @return {Observable<any>}
     * @memberof RadianService
     */
    agendarAceptacionTacitaDocumentosRadian(act_id: number, cdo_ids: string, observacion: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}radian/documentos/agendar-aceptacion-tacita`,
            this._parseObject({actId: act_id, cdoIds: cdo_ids, observacion: observacion}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar la aceptación expresa de los documentos seleccionados.
     * 
     * @param {number} act_id      Id del Actor
     * @param {string} cdo_ids     Ids de los Documentos cuya Aceptación Expresa se realizará
     * @param {string} observacion Observación de los Documentos cuya Aceptación Expresa se realizará
     * @return {Observable<any>}
     * @memberof RadianService
     */
    agendarAceptacionExpresaDocumentosRadian(act_id: number, cdo_ids: string, observacion: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}radian/documentos/agendar-aceptacion-expresa`,
            this._parseObject({actId: act_id, cdoIds: cdo_ids, observacion: observacion}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar el rechazo de los documentos seleccionados.
     * 
     * @param {number}  act_id           Id del Actor
     * @param {string}  cdo_ids          Ids de los Documentos cuyo rechazo de realizará
     * @param {string}  motivo_rechazo   Motivo de rechazo de los documentos
     * @param {string}  concepto_rechazo Concepto de rechazo de los documentos
     * @return {Observable<any>}
     * @memberof RadianService
     */
    agendarRechazoDocumentosRadian(act_id: number, cdo_ids: string, motivo_rechazo: string, concepto_rechazo: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}radian/documentos/agendar-rechazo`,
            this._parseObject({actId: act_id, cdoIds: cdo_ids, motivoRechazo: motivo_rechazo, conceptoRechazo: concepto_rechazo}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Realiza el reenvío de la notificación de eventos.
     *
     * @param {object} json Objeto con la información de los documentos y evento a procesar
     * @return {*}  {Observable<any>}
     * @memberof RadianService
     */
    reenvioNotificacion(json: object): Observable<any> {
        return this.authHttp.post(
            `${this.apiDOUrl}radian/documentos/reenvio-notificacion-evento`,
            json,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Realiza la descarga de documentos seleccionados.
     * 
     * @param {string} tipos_documentos Tipos de Documentos a descargar
     * @param {string} cdo_ids Ids de los Documentos a descargar
     * @param {string} act_id Id del Actor
     * @return {Observable<any>}
     * @memberof RadianService
     */
    descargarDocumentos(tipos_documentos: string, cdo_ids: string, act_id: string): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('tipos_documentos', tipos_documentos);
        INPUT.append('cdo_ids', cdo_ids);
        INPUT.append('act_id', act_id);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}radian/documentos/descargar`,
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
