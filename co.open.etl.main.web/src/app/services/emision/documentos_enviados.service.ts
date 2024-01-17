import {Injectable} from '@angular/core';
import {HttpClient, HttpHeaders} from '@angular/common/http';
import {Observable, throwError} from 'rxjs';
import {map, catchError} from "rxjs/operators";
import {BaseService} from '../core/base.service';

@Injectable()
export class DocumentosEnviadosService extends BaseService {

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
            `${this.apiUrl}emision/lista-documentos-enviados`,
            params,
            {headers: headers}
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
        INPUT.append('documento_enviado', 'SI');

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
                let error = err.message ? err.message : 'Error en la descarga documentos';
                return throwError(() => new Error(error));
            })
        );
    }

    /**
     * Realiza el envío de los documentos seleccionados.
     * 
     *  @param cdo_ids string Ids de los Documentos a descargar
     *  @param correos_adicionales string Correos adicionales de los Documentos a notificar
     */
    enviarDocumentosPorCorreo(cdo_ids: string, correos_adicionales: string): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('cdo_ids', cdo_ids);
        if (correos_adicionales !== null)
            INPUT.append('correos_adicionales', correos_adicionales);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiDOUrl}documentos-enviados/reenviar-email-documentos`,
            INPUT,
            { headers: headers }
        );
    }

    /**
     * Permite enviar los documentos seleccionados a la DIAN (solo para documentos enviados previamente).
     * 
     * @param documentos Ids de los documentos a enviar
     */
    enviarDocumentosDian(documentos: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/enviar-documentos-dian`,
            this._parseObject(documentos),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite agendar los estados de Aceptacion Tacita para los documentos seleccionados.
     *
     * @param {*} cdo_ids Ids de los documentos a enviar
     * @return {*}  {Observable<any>}
     * @memberof DocumentosEnviadosService
     */
    agendarEstadosAceptacionTacita(cdo_ids: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/agendar-estados-aceptacion-tacita`,
            this._parseObject({cdoIds: cdo_ids}),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite modificar los documentos seleccionados para que puedan volver a ser editados y procesados.
     *
     * Esta opción solamente aplica para documentos rechazados por la DIAN que sean de DHL Express y cuya RG sea 9
     * 
     * @param documentos Ids de los documentos a enviar
     */
    modificarDocumentosDianPickupCash(documentos: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/modificar-documentos-pickup-cash`,
            this._parseObject(documentos),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite modificar los documentos seleccionados para que puedan volver a ser editados y procesados.
     *
     * Esta opción no aplica para documentos rechazados por la DIAN que sean de DHL Express y cuya RG sea 9
     * 
     * @param documentos Ids de los documentos a enviar
     */
    modificarDocumentosDian(documentos: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/documentos/modificar-documentos`,
            this._parseObject(documentos),
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite consultar el estado en la DIAN de los documentos seleccionados.
     * 
     * @param cdo_ids string Ids de los Documentos a consultar en la DIAN
     */
    agendarConsultaEstadoDianDocumentosEnviados(cdo_ids: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiDOUrl}documentos-enviados/agendar-consulta-estado-dian`,
            {
                cdoIds: cdo_ids
            },
            { headers: this.getHeaders() }
        )
    }

    /**
     * Permite cambiar el estado de documentos enviados.
     * 
     * @param cdoIds
     */
    cambiarEstadoDocumentosEnviados(cdoIds: any) {
        return this.cambiarEstadoDocumentos(this.authHttp, cdoIds, 'enviados');
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
            `${this.apiUrl}emision/lista-documentos-enviados`,
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
     * Obtiene el documento emitido
     *
     * @param {*} params Parámetros recibidos desde el componente
     * @return {*}  {Observable<any>}
     * @memberof DocumentosEnviadosService
     */
    getDocumentoEmitido(params: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiUrl}emision/get-documento-emitido`,
            params,
            {headers: this.getHeaders()}
        )
    }

    /**
     * Procesa el envío del PDF que reemplazara el archivo existente.
     *
     * @param {*} pdf Archivo PDF que se envía
     * @param {string} ofe_identificacion Identificación del OFE
     * @param {string} adq_identificacion Identificación del Adquirente
     * @param {string} prefijo Prefijo del documento para el cual se reemplazará el PDF
     * @param {string} consecutivo Consecutivo del documento para el cual se reemplazará el PDF
     * @return {*}  {Observable<any>}
     * @memberof DocumentosEnviadosService
     */
    reemplazarPdf(pdf: any, ofe_identificacion: string, adq_identificacion: string, prefijo: string, consecutivo: string): Observable<any> {
        const INPUT = new FormData();
        INPUT.append('pdf', pdf);
        INPUT.append('ofe_identificacion', ofe_identificacion);
        INPUT.append('adq_identificacion', adq_identificacion);
        INPUT.append('prefijo', prefijo);
        INPUT.append('consecutivo', consecutivo);

        let headers = new HttpHeaders();
        headers = headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
        headers = headers.set('Pragma', 'no-cache');
        headers = headers.set('Expires', '0');

        return this.authHttp.post(
            `${this.apiUrl}${'emision/documentos/reemplazar-pdf'}`,
            INPUT,
            { headers: headers }
        );
    }

    /**
     * Permite transmitir a EDM (DHL Global) los documentos seleccionados.
     * 
     * @param cdo_ids string Ids de los Documentos a transmitir
     */
    transmitirEdm(params: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiDOUrl}documentos-enviados/transmitir-edm`,
            this._parseObject(params),
            { headers: this.getHeaders() }
        )
    }

    /**
     *  Permite cambiar el tipo de operación de los documentos seleccionados.
     *
     * @param {*} documentos Ids de los documentos a procesar
     * @return {*}  {Observable<any>}
     * @memberof DocumentosEnviadosService
     */
    cambiarTipoOperacion(documentos: any): Observable<any> {
        return this.authHttp.post(
            `${this.apiDIUrl}emision/cambiar-tipo-operacion`,
            this._parseObject(documentos),
            { headers: this.getHeaders() }
        )
    }
}
