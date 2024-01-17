import {Injectable, Inject, Optional} from '@angular/core';
import {HttpClient, HttpHeaders, HttpParams} from '@angular/common/http';
import {Observable} from 'rxjs';
import {Documento, DocumentoInteface} from '../../main/models/documento.model';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosRecibidosService extends BaseService {

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
    listar(params): Observable<any> {

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/lista-documentos-recibidos`,
            params,
            {headers: headers}
        );
    }

    /**
     * Realiza una búsqueda predictiva de documentos desde un NgSelect.
     *
     * @param string campo_buscar Término a buscar
     * @param string valor Término a buscar
     * @param {*} [filtro=null] Tipo de filtro a aplicar
     * @returns {Observable<Documento[]>}
     * @memberof DocumentosRecibidosService
     */
    searchDocumentosNgSelect(campo_buscar, valor, filtro = null): Observable<Documento[]> {
        return this.authHttp.get(
            `${this.apiUrl}recepcion/documentos/${campo_buscar}/valor/${valor}/filtro/${filtro}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: DocumentoInteface) => rsp.data));
    }

    /**
     * Realiza una búsqueda predictiva de documentos desde un NgSelect.
     *
     * @param string campo_buscar Término a buscar
     * @param string valor Término a buscar
     * @param {*} [filtro=null] Tipo de filtro a aplicar
     * @returns {Observable<Documento[]>}
     * @memberof DocumentosRecibidosService
     */
    autocompleteLote(lote: string): Observable<Documento[]> {
        return this.authHttp.get(
            `${this.apiUrl}recepcion/documentos/autocomplete-lote/${lote}`,
            {headers: this.getHeaders()}
        ).pipe(map((rsp: DocumentoInteface) => rsp.data));
    }

    /**
     * Permite cambiar el estado de documentos recibidos.
     * 
     * @param cdo_ids
     */
    cambiarEstadoDocumentosRecibidos(cdo_ids: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/cambiar-estado-documentos`,
            this._parseObject({cdoIds: cdo_ids}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite consultar el estado en la DIAN de los documentos seleccionados.
     * 
     * @param cdo_ids string Ids de los Documentos a consultar en la DIAN
     */
    agendarConsultaEstadoDianDocumentosRecibidos(ofe_id: number, cdo_ids: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/agendar-consulta-estado-dian`,
            this._parseObject({ofeId: ofe_id, cdoIds: cdo_ids}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar el recibo del bien de los documentos seleccionados.
     * 
     * @param {string} ofe_id ID del OFE
     * @param {any} cdo_ids Ids de los Documentos cuyo acuse de recibo de realizará
     * @param {any} obervacion Observación para el recibo del bien
     * @param {any} campos_fnc Información de campos adicionales al recibo del bien
     */
    agendarReciboBienDocumentosRecibidos(ofe_id: number, cdo_ids: any, observacion: any, campos_fnc: any = null): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/agendar-recibo-bien`,
            this._parseObject({ofeId: ofe_id, cdoIds: cdo_ids, observacion: observacion, camposFnc: (campos_fnc !== null ? JSON.stringify(campos_fnc) : null)}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permiter crear estados de VALIDACION de documentos.
     *
     * @param {number} ofe_id ID del OFE
     * @param {*} cdo_ids Ids de los Documentos cuyo acuse de recibo de realizará
     * @param {*} [campos_fnc=null] Información de campos adicionales del estado
     * @param {string} accionBloque Acción en bloque que fue seleccionada
     * @param {string[]} [correosNotificar=[]] Lista de correos a notificar
     * @param {string} origen Componente desde donde se hace uso del método
     * @return {*}  {Observable<any>}
     * @memberof DocumentosRecibidosService
     */
    crearEstadoValidacionDocumentosRecibidos(ofe_id: number, cdo_ids: any, campos_fnc: any = null, accionBloque: string, correosNotificar: string[] = [], origen: string = ''): Observable<any> {
        let params = {
            ofeId: ofe_id,
            cdoIds: cdo_ids,
            camposFnc: (campos_fnc !== null ? JSON.stringify(campos_fnc) : null),
            accion: accionBloque,
            correos_notificar: null,
            origen: null
        }

        if((accionBloque === 'validar' || accionBloque === 'rechazar') && correosNotificar.length > 0)
            params.correos_notificar = correosNotificar;

        if(accionBloque === 'enproceso' && origen === 'validacion_documentos')
            params.origen = origen;

        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/crear-estado-validacion`,
            this._parseObject(params),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar el acuse del recibo de los documentos seleccionados.
     * 
     * @param cdo_ids string Ids de los Documentos cuyo recibo del bien de recibo de realizará
     * @param observacion string de los Documentos cuyo Acuse se realizará
     */
    agendarAcuseReciboDocumentosRecibidos(ofe_id: number, cdo_ids: any, observacion: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/agendar-acuse-recibo`,
            this._parseObject({ofeId: ofe_id, cdoIds: cdo_ids, observacion: observacion}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar la aceptación expresa de documentos de los documentos seleccionados.
     * 
     * @param cdo_ids string Ids de los Documentos cuya aceptación expresa de realizará
     * @param observacion string de los Documentos cuya Aceptacion Expresa se realizará
     */
    agendarAceptacionExpresaDocumentosRecibidos(ofe_id: number, cdo_ids: any, observacion: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/agendar-aceptacion-expresa`,
            this._parseObject({ofeId: ofe_id, cdoIds: cdo_ids, observacion: observacion}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar la aceptación tácita de los documentos recibidos seleccionados.
     * 
     * @param {number} ofe_id ID del OFE
     * @param {string} cdo_ids string Ids de los Documentos cuya aceptación expresa de realizará
     */
    agendarAceptacionTacitaDocumentosRecibidos(ofe_id: number, cdo_ids: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/agendar-aceptacion-tacita`,
            this._parseObject({ofeId: ofe_id, cdoIds: cdo_ids}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar el rechazo de documentos de los documentos seleccionados.
     * 
     * @param cdo_ids string Ids de los Documentos cuyo rechazo de realizará
     * @param motivo_rechazo string Motivo de rechazo de los documentos
     * @param concepto_rechazo string Concepto de rechazo de los documentos
     */
    agendarRechazoDocumentosRecibidos(ofe_id: number, cdo_ids: any, motivo_rechazo: any, concepto_rechazo: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/agendar-rechazo`,
            this._parseObject({ofeId: ofe_id, cdoIds: cdo_ids, motivoRechazo: motivo_rechazo, conceptoRechazo: concepto_rechazo}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite la transmisión de uno o varios documentos existentes en el sistema, al ERP del OFE seleccionado.
     * 
     * Aplica cuando el OFE tiene activada la opción ofe_recepcion_transmision_erp.
     * 
     * @param {string} ofe_id Id del OFE
     * @param {string} cdo_ids Ids de los Documentos a transmitir
     */
    transmitirErp(ofe_id: any, cdo_ids: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiDOUrl}recepcion/documentos/transmitir-erp`,
            this._parseObject({ofeId: ofe_id, cdoIds: cdo_ids}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite la transmisión a openComex de uno o varios documentos existentes en el sistema.
     * 
     * @param {string} cdo_ids Ids de los Documentos a transmitir
     */
    transmitirOpencomex(cdo_ids: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiDOUrl}recepcion/documentos/cbo-dhl-transmitir-opencomex`,
            this._parseObject({cdo_id: cdo_ids}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite la asignación de grupos de trabajo de uno o varios documentos existentes en el sistema.
     * 
     * @param {string} cdo_ids Ids de los Documentos a asignar
     * @param {number} gtr_id Id del grupo de trabajo
     * @param {number} pro_id Id del proveedor
     * @param {string} nombre_grupos_trabajo Nombre asignado por el OFE a los grupos de trabajo
     */
    asignarGrupoTrabajo(cdo_ids: string, gtr_id: number, observacion: string, pro_id: number, nombre_grupos_trabajo: string): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/asignar-grupo-trabajo`,
            this._parseObject({cdo_ids: cdo_ids, gtr_id: gtr_id, observacion: observacion, pro_id: pro_id, nombre_grupos_trabajo: nombre_grupos_trabajo}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite la descarga en excel de una consulta en pantalla.
     *
     * @param {*} params Parametros para generar la descarga del Excel
     * @param {boolean} excelValidacionDocumentos Indica que se va a descargar el Excel de Validación de Documentos
     */
    descargarExcel(params, excelValidacionDocumentos: boolean = false): Observable<any> {
        let headers = new HttpHeaders();
        headers.set('Content-type', 'application/json');
        headers.set('X-Requested-Whith', 'XMLHttpRequest');
        headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers.set('Expires', '0');

        let endpoint = 'recepcion/documentos/lista-documentos-recibidos';
        if (excelValidacionDocumentos)
            endpoint = 'recepcion/documentos/lista-validacion-documentos'; 

        return this.authHttp.post<Blob>(
            `${this.apiUrl}${endpoint}`,
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

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post<Blob>(
            `${this.apiUrl}recepcion/documentos/descargar`,
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

    /**
     * Realiza el reenvío de la notificación de eventos.
     * 
     *  @param {object} json Objeto con la información de los documentos y evento a procesar
     */
    reenvioNotificacion(json): Observable<any> {
        return this.authHttp.post(
            `${this.apiDOUrl}recepcion/documentos/reenvio-notificacion-evento`,
            json,
            { headers: this.getHeadersApplicationJSON() }
        );
    }

    /**
     * Permite consultar los estados del documento.
     *
     * @param {*} valor Información a buscar
     * @return {*}  {Observable<any>}
     * @memberof DocumentosRecibidosService
     */
    obtenerEstadosDocumento(valor): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/estados-documento`,
            this._parseObject(valor),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite consultar los documentos anexos del documento.
     *
     * @param {*} valor Información a buscar
     * @return {*}  {Observable<any>}
     * @memberof DocumentosRecibidosService
     */
    obtenerDocumentosAnexos(valor): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/documentos-anexos`,
            this._parseObject(valor),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Busca un documento en la BD
     * 
     * @param object values Objeto con valores de Ofe, Prefijo, Consecutivo y fechas
     * @returns Observable
     * @memberof DocumentosRecibidosService
     */
    encontrarDocumentoAnexo(values): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/encontrar-documento`,
            this._parseObject(values),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Envia la petición la backend para la carga de los documentos anexos.
     * 
     * @param {array} descripciones Descripciones para los documentos anexos cargados
     * @param {array} documentosAnexos Archivos anexos
     * @param {number} cdo_id Identificador del documento electrónico
     * @returns {Observable<any>} 
     * @memberof DocumentosRecibidosService
     */
    cargarDocumentosAnexo(descripciones, documentosAnexos, cdo_id): Observable<any> {
        const INPUT = new FormData();
        const totalDocumentosAnexos = documentosAnexos.length;
        let contadorDocumentosAnexo = 1;
        documentosAnexos.forEach(adjunto => {
            INPUT.append('archivo' + contadorDocumentosAnexo, adjunto);
            contadorDocumentosAnexo++;
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
            `${this.apiUrl}recepcion/documentos/cargar-documentos-anexos`,
            INPUT,
            { headers: headers }
        )
    }

    /**
     * Obtiene una lista de los documentos de validación.
     *
     * @param {*} params Parámetros de búsqueda
     * @return {*}  {Observable<any>}
     * @memberof DocumentosRecibidosService
     */
    listarValidacionDocumentos(params): Observable<any> {
        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}recepcion/documentos/lista-validacion-documentos`,
            params,
            {headers: headers}
        );
    }
}
